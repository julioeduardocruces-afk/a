<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Resume;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AdminNotificationService
{
    /**
     * Notify admin about a post-payment error so they can process it manually.
     *
     * @param Resume  $resume    The resume that failed
     * @param string  $stage     Where the error occurred (webhook_transition, processing, delivery)
     * @param string  $error     Error message
     * @param array   $extra     Additional context
     */
    public static function notifyPostPaymentError(
        Resume $resume,
        string $stage,
        string $error,
        array $extra = [],
    ): void {
        $adminEmail = config('ats.admin_notification_email');

        if (empty($adminEmail)) {
            Log::warning('AdminNotification: no admin email configured, skipping notification', [
                'resume_id' => $resume->id,
                'stage' => $stage,
            ]);
            return;
        }

        $payment = Payment::where('resume_id', $resume->id)
            ->where('status', \App\Enums\PaymentStatus::Paid)
            ->latest()
            ->first();

        $stageLabels = [
            'webhook_transition' => 'Transicion de estado post-pago (webhook)',
            'processing' => 'Procesamiento AI del CV',
            'delivery' => 'Generacion/envio del CV final',
        ];

        $viewData = [
            'resume' => $resume,
            'payment' => $payment,
            'stage' => $stage,
            'stageLabel' => $stageLabels[$stage] ?? $stage,
            'error' => $error,
            'extra' => $extra,
            'adminUrl' => url('/admin/resumes'),
            'customerEmail' => $resume->getEmail() ?? 'No disponible',
        ];

        try {
            Mail::send(
                'emails.admin-post-payment-error',
                $viewData,
                function ($message) use ($adminEmail, $resume, $stage) {
                    $message->to($adminEmail)
                        ->subject("[ALERTA] Error post-pago - Resume #{$resume->id} - {$stage}");
                }
            );

            Log::info('Admin notified of post-payment error', [
                'resume_id' => $resume->id,
                'stage' => $stage,
                'admin_email' => $adminEmail,
            ]);
        } catch (\Exception $e) {
            // Don't let notification failure break the flow — just log it
            Log::error('Failed to send admin notification email', [
                'resume_id' => $resume->id,
                'stage' => $stage,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
