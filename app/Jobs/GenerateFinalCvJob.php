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
        $initialStatus = $resume->status;

        // Allow Paid (first generation), Delivered (admin resend), or Failed with
        // a confirmed payment (admin resend after delivery_error marked resume Failed)
        $allowed = [ResumeStatus::Paid, ResumeStatus::Delivered];
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

            // Re-check status from DB before sending email. Between the initial
            // load and now, a concurrent job (from webhook crash-recovery re-dispatch)
            // may have already completed delivery. Without this refresh, both jobs
            // would send emails, resulting in duplicate emails with extra tokens.
            //
            // Only skip if the status CHANGED to Delivered during execution (concurrent
            // job). If the resume was already Delivered at entry ($initialStatus), this
            // is a legitimate admin resend — don't skip.
            $resume->refresh();

            if ($resume->status === ResumeStatus::Delivered && $initialStatus !== ResumeStatus::Delivered) {
                Log::info('Resume already delivered by concurrent job, skipping email', [
                    'resume_id' => $resume->id,
                ]);
                return;
            }

            // Send email FIRST — if it fails, don't transition to Delivered
            // so the job can be retried and the state remains recoverable
            $mailer->sendFinalCvEmail($resume);

            // Transition to Delivered AFTER email succeeds
            // Handles: Paid → Delivered (normal), Failed → Delivered (admin recovery)
            // Skips transition if already Delivered (admin resend scenario)
            if (in_array($resume->status, [ResumeStatus::Paid, ResumeStatus::Failed], true)) {
                $resume->error_code = null;
                $resume->error_message = null;
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

    /**
     * Called by Laravel when all retry attempts are exhausted.
     * Marks the resume as failed so the admin can see delivery failed post-payment.
     */
    public function failed(\Throwable $exception): void
    {
        $resume = Resume::find($this->resumeId);
        if (!$resume) {
            return;
        }

        // Mark as failed if still in Paid state (first delivery attempt exhausted).
        // Skip if already Delivered from a concurrent admin resend.
        if ($resume->status === ResumeStatus::Paid) {
            $resume->markFailed('delivery_error', $exception->getMessage());
        } elseif ($resume->status === ResumeStatus::Failed) {
            // Admin resend exhausted: update error info so admin sees the latest
            // failure reason, not the stale one from the original failure.
            $resume->update([
                'error_code' => 'delivery_error',
                'error_message' => mb_substr($exception->getMessage(), 0, 1000),
            ]);
        } else {
            // Already Delivered (concurrent job succeeded) — nothing to do
            return;
        }

        AuditLog::record('resume.delivery_exhausted', $resume->user_id, 'system', [
            'resume_id' => $resume->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
