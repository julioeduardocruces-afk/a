<?php

namespace App\Services;

use App\Models\DownloadToken;
use App\Models\Resume;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailerService
{
    /**
     * Send the final CV download link to the customer.
     * Works for both anonymous (customer_email) and registered users.
     */
    public function sendFinalCvEmail(Resume $resume): void
    {
        $email = $resume->getEmail();
        if (!$email) {
            throw new \RuntimeException('No email available for resume #' . $resume->id);
        }

        $displayName = $resume->getDisplayName();
        $ttlMinutes = (int) config('ats.download_token_ttl', 1440);
        $maxDownloads = (int) config('ats.max_downloads', 3);

        // Single token for both formats, allows up to max_downloads uses
        $token = DownloadToken::generate($resume->id, $resume->user_id, $ttlMinutes, $maxDownloads);

        $downloadPdfUrl = route('download.token', ['token' => $token->token]);
        $downloadDocxUrl = route('download.token', ['token' => $token->token, 'format' => 'docx']);

        try {
            Mail::send(
                'emails.cv-ready',
                [
                    'userName' => $displayName,
                    'downloadPdfUrl' => $downloadPdfUrl,
                    'downloadDocxUrl' => $downloadDocxUrl,
                    'resumeId' => $resume->id,
                    'expiresAt' => $token->expires_at->format('d/m/Y H:i'),
                    'maxDownloads' => $maxDownloads,
                ],
                function ($message) use ($email, $displayName, $resume) {
                    $message->to($email, $displayName)
                        ->subject('Tu CV Optimizado ATS esta listo - #' . $resume->id);
                }
            );

            Log::info('CV email sent', [
                'email' => $email,
                'resume_id' => $resume->id,
                'token_id' => $token->id,
                'max_downloads' => $maxDownloads,
            ]);
        } catch (\Exception $e) {
            $token->update(['used' => true]);

            Log::error('Failed to send CV email, token invalidated', [
                'email' => $email,
                'resume_id' => $resume->id,
                'token_id' => $token->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
