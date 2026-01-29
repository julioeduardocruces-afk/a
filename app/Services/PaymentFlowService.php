<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\ResumeStatus;
use App\Jobs\GenerateFinalCvJob;
use App\Models\ApiCredential;
use App\Models\AuditLog;
use App\Models\MetricsDaily;
use App\Models\Payment;
use App\Models\Resume;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaymentFlowService
{
    /**
     * Create a payment order via Flow and return the redirect URL.
     */
    public function createPayment(Resume $resume, int $amount): array
    {
        if ($resume->status !== ResumeStatus::PreviewReady) {
            throw new RuntimeException('El CV debe estar en estado preview_ready para pagar.');
        }

        // Prevent double payment with locking to avoid race conditions
        // (concurrent requests could both pass the check without a lock)
        $payment = DB::transaction(function () use ($resume, $amount) {
            // Lock existing payments for this resume to prevent duplicates
            $existingPayment = Payment::where('resume_id', $resume->id)
                ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Paid])
                ->lockForUpdate()
                ->first();

            if ($existingPayment?->status === PaymentStatus::Paid) {
                throw new RuntimeException('Este CV ya fue pagado.');
            }

            // If there's a pending payment, cancel it before creating a new one
            if ($existingPayment?->status === PaymentStatus::Pending) {
                $existingPayment->update(['status' => PaymentStatus::Failed]);
            }

            return Payment::create([
                'user_id' => $resume->user_id,
                'resume_id' => $resume->id,
                'provider' => 'flow',
                'amount' => $amount,
                'currency' => 'CLP',
                'status' => PaymentStatus::Pending,
            ]);
        });

        $credential = ApiCredential::getNextForProvider('flow');
        if (!$credential) {
            throw new RuntimeException('No hay credenciales Flow activas.');
        }

        $creds = $credential->getDecryptedCredentials();
        $apiKey = $creds['api_key'] ?? '';
        $secretKey = $creds['secret_key'] ?? '';
        $apiUrl = $creds['api_url'] ?? 'https://www.flow.cl/api';

        if (empty($apiKey) || empty($secretKey)) {
            throw new RuntimeException('Credenciales Flow incompletas.');
        }

        $params = [
            'apiKey' => $apiKey,
            'commerceOrder' => (string)$payment->id,
            'subject' => 'Optimizacion CV ATS - #' . $resume->id,
            'currency' => 'CLP',
            'amount' => $amount,
            'email' => $resume->user->email,
            'urlConfirmation' => route('payments.flow.webhook'),
            'urlReturn' => route('payments.flow.return', ['payment' => $payment->id]),
        ];

        // Sign the request (HMAC-SHA256 per Flow docs)
        $params['s'] = $this->signParams($params, $secretKey);

        $response = Http::asForm()->post("{$apiUrl}/payment/create", $params);

        if (!$response->successful()) {
            $payment->update(['status' => PaymentStatus::Failed, 'raw_payload_json' => $response->json()]);
            throw new RuntimeException('Error al crear pago en Flow.');
        }

        $data = $response->json();

        // Validate Flow returned a payment token. If the API returns 200
        // but omits the token, the user would be redirected to an invalid
        // URL and the payment stays Pending forever with no recovery path.
        if (empty($data['token'])) {
            $payment->update(['status' => PaymentStatus::Failed, 'raw_payload_json' => $data]);
            throw new RuntimeException('Flow API no devolvio token de pago.');
        }

        $payment->update([
            'flow_token' => $data['token'],
            'flow_order' => $data['flowOrder'] ?? null,
            'raw_payload_json' => $data,
        ]);

        $credential->recordUsage();

        AuditLog::record('payment.created', $resume->user_id, 'user', [
            'payment_id' => $payment->id,
            'resume_id' => $resume->id,
            'amount' => $amount,
        ]);

        $redirectUrl = ($data['url'] ?? $apiUrl . '/payment/pay') . '?token=' . $data['token'];

        return [
            'payment_id' => $payment->id,
            'redirect_url' => $redirectUrl,
        ];
    }

    /**
     * Handle Flow webhook confirmation (idempotent).
     * Flow sends token via POST. We verify by calling getStatus with HMAC.
     */
    public function handleWebhook(array $payload): Payment
    {
        $token = $payload['token'] ?? null;
        if (empty($token)) {
            throw new RuntimeException('Webhook sin token.');
        }

        // Verify the payment status with Flow API (server-to-server verification)
        $credential = ApiCredential::getNextForProvider('flow');
        if (!$credential) {
            throw new RuntimeException('No hay credenciales Flow activas para verificar.');
        }

        $creds = $credential->getDecryptedCredentials();
        $apiKey = $creds['api_key'] ?? '';
        $secretKey = $creds['secret_key'] ?? '';
        $apiUrl = $creds['api_url'] ?? 'https://www.flow.cl/api';

        if (empty($apiKey) || empty($secretKey)) {
            throw new RuntimeException('Credenciales Flow incompletas para verificacion.');
        }

        $params = [
            'apiKey' => $apiKey,
            'token' => $token,
        ];
        $params['s'] = $this->signParams($params, $secretKey);

        $response = Http::asForm()->get("{$apiUrl}/payment/getStatus", $params);

        if (!$response->successful()) {
            Log::error('Flow getStatus failed', ['status' => $response->status()]);
            throw new RuntimeException('Error verificando pago con Flow.');
        }

        $flowData = $response->json();
        // Use null instead of empty string for missing flowOrder.
        // The flow_order column has a UNIQUE constraint: NULL is allowed
        // multiple times but '' (empty string) is not, so storing '' for
        // every payment without a flowOrder would cause a constraint violation.
        $flowOrder = !empty($flowData['flowOrder']) ? (string)$flowData['flowOrder'] : null;
        $commerceOrder = (string)($flowData['commerceOrder'] ?? '');
        $flowStatus = (int)($flowData['status'] ?? 0);

        // Find payment by flow_token first (most specific), then by commerce order ID
        $payment = Payment::where('flow_token', $token)->first();
        if (!$payment && !empty($commerceOrder)) {
            $payment = Payment::find((int)$commerceOrder);
        }

        if (!$payment) {
            throw new RuntimeException("Pago no encontrado para token: {$token}");
        }

        // Idempotency: if already paid, check if the delivery job needs re-dispatch.
        // This handles two crash-recovery scenarios:
        // 1. Crash after payment commit but BEFORE resume transition (resume stuck in PreviewReady)
        // 2. Crash after resume transition but BEFORE job dispatch (resume stuck in Paid)
        if ($payment->status === PaymentStatus::Paid) {
            $resume = $payment->resume;
            // Refresh to get current DB state — without this, a concurrent
            // admin action (e.g. markFailed) could be overwritten by stale data.
            $resume?->refresh();
            if ($resume && $resume->status === ResumeStatus::PreviewReady) {
                // Resume stuck in PreviewReady = crash between payment commit and
                // resume transition. Complete the transition and dispatch.
                Log::warning('Webhook duplicado: recovering stuck PreviewReady resume with Paid payment', [
                    'payment_id' => $payment->id,
                    'resume_id' => $resume->id,
                ]);
                try {
                    $resume->transitionTo(ResumeStatus::Paid);
                    MetricsDaily::batchIncrementToday([
                        'paid' => 1,
                        'revenue' => $payment->amount,
                    ]);
                } catch (\InvalidArgumentException $e) {
                    Log::error('Recovery transition failed for PreviewReady resume', [
                        'payment_id' => $payment->id,
                        'resume_id' => $resume->id,
                        'error' => $e->getMessage(),
                    ]);
                    return $payment;
                }
                GenerateFinalCvJob::dispatch($resume->id);
            } elseif ($resume && $resume->status === ResumeStatus::Paid) {
                // Resume stuck in Paid = job was never dispatched or failed silently.
                // Re-dispatch is safe: GenerateFinalCvJob is idempotent for Paid state.
                Log::info('Webhook duplicado: re-dispatching job for stuck Paid resume', [
                    'payment_id' => $payment->id,
                    'resume_id' => $resume->id,
                ]);
                GenerateFinalCvJob::dispatch($resume->id);
            } else {
                Log::info('Webhook duplicado ignorado', ['payment_id' => $payment->id]);
            }
            return $payment;
        }

        // Skip if payment was locally cancelled (Failed) or refunded.
        // A Failed payment means it was superseded by a newer payment attempt
        // (see createPayment). Even if Flow confirms it, we must not resurrect it
        // because the user may have already paid via the replacement payment,
        // which would result in double-charging.
        if (in_array($payment->status, [PaymentStatus::Failed, PaymentStatus::Refunded], true)) {
            Log::warning('Webhook para pago cancelado/refunded ignorado', [
                'payment_id' => $payment->id,
                'payment_status' => $payment->status->value,
                'flow_status' => $flowStatus,
            ]);

            // If Flow says this cancelled payment was actually charged (status=2),
            // log a critical alert so admin can issue a refund
            if ($flowStatus === 2) {
                Log::critical('Flow confirmed a locally-cancelled payment — potential double charge, refund needed', [
                    'payment_id' => $payment->id,
                    'flow_order' => $flowOrder,
                    'amount' => $payment->amount,
                ]);

                AuditLog::record('payment.cancelled_but_charged', $payment->user_id, 'system', [
                    'payment_id' => $payment->id,
                    'flow_order' => $flowOrder,
                    'amount' => $payment->amount,
                ]);
            }

            return $payment;
        }

        // Step 1: Atomically update payment status (committed separately so that
        // a confirmed payment is NEVER lost if the resume transition fails below)
        $payment = DB::transaction(function () use ($payment, $flowData, $flowOrder, $flowStatus) {
            // Re-lock payment row inside transaction to prevent race conditions
            $payment = Payment::lockForUpdate()->find($payment->id);

            // Double-check idempotency inside transaction
            if ($payment->status === PaymentStatus::Paid) {
                return $payment;
            }

            // Single UPDATE instead of two separate calls — reduces round-trips
            // inside the locked transaction from 2 to 1.
            $newStatus = $flowStatus === 2 ? PaymentStatus::Paid : PaymentStatus::Failed;

            $payment->update([
                'flow_order' => $flowOrder,
                'raw_payload_json' => $flowData,
                'status' => $newStatus,
            ]);

            if ($newStatus === PaymentStatus::Failed) {
                AuditLog::record('payment.failed', $payment->user_id, 'system', [
                    'payment_id' => $payment->id,
                    'flow_status' => $flowStatus,
                ]);
            }

            return $payment->fresh();
        });

        // Step 2: If payment was confirmed, transition resume and dispatch job.
        // This runs OUTSIDE the payment transaction so that a resume transition
        // failure (e.g. resume left PreviewReady during payment) never rolls back
        // the payment status — confirmed money must always be recorded.
        if ($payment->status === PaymentStatus::Paid) {
            $resume = $payment->resume;

            try {
                $resume->transitionTo(ResumeStatus::Paid);
            } catch (\InvalidArgumentException $e) {
                Log::warning('Payment confirmed but resume transition failed', [
                    'payment_id' => $payment->id,
                    'resume_id' => $resume->id,
                    'resume_status' => $resume->status->value,
                    'error' => $e->getMessage(),
                ]);

                AuditLog::record('payment.confirmed_transition_failed', $payment->user_id, 'system', [
                    'payment_id' => $payment->id,
                    'resume_id' => $resume->id,
                    'resume_status' => $resume->status->value,
                ]);

                return $payment;
            }

            MetricsDaily::batchIncrementToday([
                'paid' => 1,
                'revenue' => $payment->amount,
            ]);

            AuditLog::record('payment.confirmed', $payment->user_id, 'system', [
                'payment_id' => $payment->id,
                'resume_id' => $resume->id,
                'flow_order' => $flowOrder,
            ]);

            GenerateFinalCvJob::dispatch($resume->id);
        }

        return $payment;
    }

    /**
     * Sign parameters using HMAC-SHA256 per Flow API specification.
     */
    private function signParams(array $params, string $secretKey): string
    {
        ksort($params);
        $toSign = '';
        foreach ($params as $key => $value) {
            $toSign .= "{$key}{$value}";
        }
        return hash_hmac('sha256', $toSign, $secretKey);
    }
}
