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
        $resume = Resume::with('latestVersion')->findOrFail($this->resumeId);
        $initialStatus = $resume->status;

        $allowed = [ResumeStatus::Processing, ResumeStatus::Paid, ResumeStatus::Delivered];
        $isFailedWithPayment = false;

        if ($resume->status === ResumeStatus::Failed) {
            $isFailedWithPayment = \App\Models\Payment::where('resume_id', $resume->id)
                ->where('status', \App\Enums\PaymentStatus::Paid)
                ->exists();
            if (!$isFailedWithPayment) {
                Log::warning('Resume failed without paid payment, skipping generation', [
                    'resume_id' => $resume->id,
                ]);
                return;
            }
        } elseif (!in_array($resume->status, $allowed, true)) {
            Log::warning('Resume not in valid state for final generation', [
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

            $displayName = $resume->getDisplayName();

            // Generate final HTML then PDF
            $finalHtml = $renderer->renderFinalPdf(
                $version->optimized_text_md,
                $displayName,
            );
            $pdfContent = $pdfGenerator->generatePdf($finalHtml);

            // Store final PDF
            $finalPath = "finals/{$resume->id}/cv_optimizado_{$resume->id}.pdf";
            Storage::put($finalPath, $pdfContent);

            // Generate DOCX too
            $docxContent = $pdfGenerator->generateDocx(
                $version->optimized_text_plain,
                $displayName,
            );
            $docxPath = "finals/{$resume->id}/cv_optimizado_{$resume->id}.docx";
            Storage::put($docxPath, $docxContent);

            // Re-check status from DB before sending email
            $resume->refresh();

            if ($resume->status === ResumeStatus::Delivered && $initialStatus !== ResumeStatus::Delivered) {
                Log::info('Resume already delivered by concurrent job, skipping email', [
                    'resume_id' => $resume->id,
                ]);
                return;
            }

            // Send email FIRST — if it fails, don't transition to Delivered
            $mailer->sendFinalCvEmail($resume);

            // Transition to Delivered AFTER email succeeds
            if (in_array($resume->status, [ResumeStatus::Processing, ResumeStatus::Paid, ResumeStatus::Failed], true)) {
                $resume->error_code = null;
                $resume->error_message = null;
                $resume->transitionTo(ResumeStatus::Delivered);
            }

            AuditLog::record('resume.delivered', $resume->user_id, $resume->user_id ? 'user' : 'system', [
                'resume_id' => $resume->id,
                'pdf_path' => $finalPath,
                'docx_path' => $docxPath,
            ]);

        } catch (\Exception $e) {
            Log::error('Final CV generation failed', [
                'resume_id' => $resume->id,
                'error' => $e->getMessage(),
            ]);

            AuditLog::record('resume.delivery_failed', $resume->user_id, $resume->user_id ? 'user' : 'system', [
                'resume_id' => $resume->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Called by Laravel when all retry attempts are exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        $resume = Resume::find($this->resumeId);
        if (!$resume) {
            return;
        }

        if (in_array($resume->status, [ResumeStatus::Processing, ResumeStatus::Paid], true)) {
            $resume->markFailed('delivery_error', $exception->getMessage());
        } elseif ($resume->status === ResumeStatus::Failed) {
            $resume->update([
                'error_code' => 'delivery_error',
                'error_message' => mb_substr($exception->getMessage(), 0, 1000),
            ]);
        } else {
            return;
        }

        AuditLog::record('resume.delivery_exhausted', $resume->user_id, $resume->user_id ? 'user' : 'system', [
            'resume_id' => $resume->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
