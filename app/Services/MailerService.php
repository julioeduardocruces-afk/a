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
        $token = DownloadToken::generate($resume->id, $user->id, $ttlMinutes);

        $downloadUrl = route('download.token', ['token' => $token->token]);

        try {
            Mail::send(
                'emails.cv-ready',
                [
                    'userName' => $user->name,
                    'downloadUrl' => $downloadUrl,
                    'resumeId' => $resume->id,
                    'expiresAt' => $token->expires_at->format('d/m/Y H:i'),
                ],
                function ($message) use ($user, $resume) {
                    $message->to($user->email, $user->name)
                        ->subject('Tu CV Optimizado ATS esta listo - #' . $resume->id);
                }
            );

            Log::info('CV email sent', [
                'user_id' => $user->id,
                'resume_id' => $resume->id,
                'token_id' => $token->id,
            ]);
        } catch (\Exception $e) {
            // Invalidate the orphan token so it can't be used and doesn't
            // pollute the DB on each retry attempt
            $token->update(['used' => true]);

            Log::error('Failed to send CV email, token invalidated', [
                'user_id' => $user->id,
                'resume_id' => $resume->id,
                'token_id' => $token->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
