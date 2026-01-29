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
        $ttlMinutes = (int) config('ats.download_token_ttl', 15);

        // Generate separate tokens for each format
        $pdfToken = DownloadToken::generate($resume->id, $resume->user_id, $ttlMinutes);
        $docxToken = DownloadToken::generate($resume->id, $resume->user_id, $ttlMinutes);

        $downloadPdfUrl = route('download.token', ['token' => $pdfToken->token]);
        $downloadDocxUrl = route('download.token', ['token' => $docxToken->token, 'format' => 'docx']);

        try {
            Mail::send(
                'emails.cv-ready',
                [
                    'userName' => $displayName,
                    'downloadPdfUrl' => $downloadPdfUrl,
                    'downloadDocxUrl' => $downloadDocxUrl,
                    'resumeId' => $resume->id,
                    'expiresAt' => $pdfToken->expires_at->format('d/m/Y H:i'),
                ],
                function ($message) use ($email, $displayName, $resume) {
                    $message->to($email, $displayName)
                        ->subject('Tu CV Optimizado ATS esta listo - #' . $resume->id);
                }
            );

            Log::info('CV email sent', [
                'email' => $email,
                'resume_id' => $resume->id,
                'pdf_token_id' => $pdfToken->id,
                'docx_token_id' => $docxToken->id,
            ]);
        } catch (\Exception $e) {
            $pdfToken->update(['used' => true]);
            $docxToken->update(['used' => true]);

            Log::error('Failed to send CV email, tokens invalidated', [
                'email' => $email,
                'resume_id' => $resume->id,
                'pdf_token_id' => $pdfToken->id,
                'docx_token_id' => $docxToken->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
