<?php

namespace App\Services;

use App\Models\DownloadToken;
use App\Models\Resume;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailerService
{
    /**
     * Send the final CV download link to the user.
     */
    public function sendFinalCvEmail(Resume $resume): void
    {
        $user = $resume->user;
        $ttlMinutes = (int) config('ats.download_token_ttl', 15);

        // Generate separate tokens for each format so the user can download
        // both PDF and DOCX. Each token is single-use (consumed on download),
        // preventing link sharing while still allowing both formats.
        $pdfToken = DownloadToken::generate($resume->id, $user->id, $ttlMinutes);
        $docxToken = DownloadToken::generate($resume->id, $user->id, $ttlMinutes);

        $downloadPdfUrl = route('download.token', ['token' => $pdfToken->token]);
        $downloadDocxUrl = route('download.token', ['token' => $docxToken->token, 'format' => 'docx']);

        try {
            Mail::send(
                'emails.cv-ready',
                [
                    'userName' => $user->name,
                    'downloadPdfUrl' => $downloadPdfUrl,
                    'downloadDocxUrl' => $downloadDocxUrl,
                    'resumeId' => $resume->id,
                    'expiresAt' => $pdfToken->expires_at->format('d/m/Y H:i'),
                ],
                function ($message) use ($user, $resume) {
                    $message->to($user->email, $user->name)
                        ->subject('Tu CV Optimizado ATS esta listo - #' . $resume->id);
                }
            );

            Log::info('CV email sent', [
                'user_id' => $user->id,
                'resume_id' => $resume->id,
                'pdf_token_id' => $pdfToken->id,
                'docx_token_id' => $docxToken->id,
            ]);
        } catch (\Exception $e) {
            // Invalidate both orphan tokens so they can't be used and don't
            // pollute the DB on each retry attempt
            $pdfToken->update(['used' => true]);
            $docxToken->update(['used' => true]);

            Log::error('Failed to send CV email, tokens invalidated', [
                'user_id' => $user->id,
                'resume_id' => $resume->id,
                'pdf_token_id' => $pdfToken->id,
                'docx_token_id' => $docxToken->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
