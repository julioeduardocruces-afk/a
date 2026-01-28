<?php

namespace App\Jobs;

use App\Enums\ResumeStatus;
use App\Models\AuditLog;
use App\Models\Resume;
use App\Services\MailerService;
use App\Services\PdfGeneratorService;
use App\Services\ResumeRendererService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateFinalCvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public array $backoff = [10, 60];

    public function __construct(
        private readonly int $resumeId,
    ) {}

    public function handle(
        ResumeRendererService $renderer,
        PdfGeneratorService $pdfGenerator,
        MailerService $mailer,
    ): void {
        $resume = Resume::with('latestVersion', 'user')->findOrFail($this->resumeId);

        // Allow both Paid (first generation) and Delivered (admin resend email)
        if (!in_array($resume->status, [ResumeStatus::Paid, ResumeStatus::Delivered], true)) {
            Log::warning('Resume not in paid/delivered state for final generation', [
                'resume_id' => $resume->id,
                'status' => $resume->status->value,
            ]);
            return;
        }

        try {
            $version = $resume->latestVersion;
            if (!$version) {
                throw new \RuntimeException('No version found for resume.');
            }

            // Generate final HTML then PDF
            $finalHtml = $renderer->renderFinalPdf(
                $version->optimized_text_md,
                $resume->user->name,
            );
            $pdfContent = $pdfGenerator->generatePdf($finalHtml);

            // Store final PDF
            $finalPath = "finals/{$resume->id}/cv_optimizado_{$resume->id}.pdf";
            Storage::put($finalPath, $pdfContent);

            // Generate DOCX too
            $docxContent = $pdfGenerator->generateDocx(
                $version->optimized_text_plain,
                $resume->user->name,
            );
            $docxPath = "finals/{$resume->id}/cv_optimizado_{$resume->id}.docx";
            Storage::put($docxPath, $docxContent);

            // Send email FIRST — if it fails, don't transition to Delivered
            // so the job can be retried and the state remains recoverable
            $mailer->sendFinalCvEmail($resume);

            // Only transition to Delivered AFTER email succeeds
            // Skip transition if already Delivered (admin resend scenario)
            if ($resume->status === ResumeStatus::Paid) {
                $resume->transitionTo(ResumeStatus::Delivered);
            }

            AuditLog::record('resume.delivered', $resume->user_id, 'system', [
                'resume_id' => $resume->id,
                'pdf_path' => $finalPath,
                'docx_path' => $docxPath,
            ]);

        } catch (\Exception $e) {
            Log::error('Final CV generation failed', [
                'resume_id' => $resume->id,
                'error' => $e->getMessage(),
            ]);

            AuditLog::record('resume.delivery_failed', $resume->user_id, 'system', [
                'resume_id' => $resume->id,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw so the job retries via $tries/$backoff
        }
    }
}
