<?php

namespace App\Jobs;

use App\Enums\ResumeStatus;
use App\Models\AuditLog;
use App\Models\MetricsDaily;
use App\Models\Resume;
use App\Models\ResumeVersion;
use App\Services\AiOptimizerService;
use App\Services\AtsScoreService;
use App\Services\TextExtractorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessResumeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;
    public array $backoff = [10, 60];

    public function __construct(
        private readonly int $resumeId,
    ) {}

    public function handle(
        TextExtractorService $extractor,
        AiOptimizerService $aiOptimizer,
        AtsScoreService $scorer,
    ): void {
        $startTime = microtime(true);

        $resume = Resume::findOrFail($this->resumeId);

        try {
            // Validate state
            if ($resume->status !== ResumeStatus::Processing) {
                Log::warning('Resume not in processing state', [
                    'resume_id' => $resume->id,
                    'status' => $resume->status->value,
                ]);
                return;
            }

            // Step 1: Extract text if not done
            if (empty($resume->extracted_text)) {
                $text = $extractor->extract($resume->original_path, $resume->original_mime);
                $structured = $extractor->structureText($text);

                $resume->update([
                    'extracted_text' => $text,
                    'structured_json' => $structured,
                ]);
            }

            $text = $resume->extracted_text;
            $structured = $resume->structured_json ?? [];

            // Step 2: AI Optimization
            $aiResult = $aiOptimizer->optimize(
                $text,
                $structured,
                $resume->target_industry ?? '',
                $resume->target_role ?? '',
            );

            // Step 3: Compute ATS score (independent heuristic)
            $score = $scorer->score(
                $aiResult['optimized_text_plain'],
                $resume->target_role ?? '',
                $resume->target_industry ?? '',
                $aiResult['ats_keywords'] ?? [],
            );

            // Step 4: Save version (atomic with DB lock to prevent race condition)
            $versionNum = DB::transaction(function () use ($resume, $aiResult, $score) {
                $versionNum = ResumeVersion::where('resume_id', $resume->id)
                    ->lockForUpdate()
                    ->max('version') ?? 0;

                ResumeVersion::create([
                    'resume_id' => $resume->id,
                    'version' => $versionNum + 1,
                    'optimized_text_md' => $aiResult['optimized_text_md'],
                    'optimized_text_plain' => $aiResult['optimized_text_plain'],
                    'ats_keywords_json' => $aiResult['ats_keywords'] ?? [],
                    'score_json' => $score,
                    'consistency_report_json' => $aiResult['consistency_report'] ?? null,
                    'created_at' => now(),
                ]);

                return $versionNum + 1;
            });

            // Step 5: Transition to preview_ready
            $resume->transitionTo(ResumeStatus::PreviewReady);

            MetricsDaily::incrementToday('previews');

            $elapsed = (int)((microtime(true) - $startTime) * 1000);
            AuditLog::record('resume.processed', $resume->user_id, 'system', [
                'resume_id' => $resume->id,
                'version' => $versionNum,
                'score' => $score['overall'] ?? 0,
                'elapsed_ms' => $elapsed,
            ]);

        } catch (\Exception $e) {
            Log::error('Resume processing failed', [
                'resume_id' => $resume->id,
                'error' => $e->getMessage(),
            ]);

            $resume->markFailed('processing_error', $e->getMessage());

            AuditLog::record('resume.failed', $resume->user_id, 'system', [
                'resume_id' => $resume->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
