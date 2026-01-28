<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\ResumeStatus;
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

        // Prevent double payment: check if there's already a pending/paid payment
        $existingPayment = Payment::where('resume_id', $resume->id)
            ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Paid])
            ->first();

        if ($existingPayment?->status === PaymentStatus::Paid) {
            throw new RuntimeException('Este CV ya fue pagado.');
        }

        // If there's a pending payment, cancel it before creating a new one
        if ($existingPayment?->status === PaymentStatus::Pending) {
            $existingPayment->update(['status' => PaymentStatus::Failed]);
        }

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

        // Create payment record first
        $payment = Payment::create([
            'user_id' => $resume->user_id,
            'resume_id' => $resume->id,
            'provider' => 'flow',
            'amount' => $amount,
            'currency' => 'CLP',
            'status' => PaymentStatus::Pending,
        ]);

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
        $payment->update([
            'flow_token' => $data['token'] ?? null,
            'flow_order' => $data['flowOrder'] ?? null,
            'raw_payload_json' => $data,
        ]);

        $credential->recordUsage();

        AuditLog::record('payment.created', $resume->user_id, 'user', [
            'payment_id' => $payment->id,
            'resume_id' => $resume->id,
            'amount' => $amount,
        ]);

        $redirectUrl = ($data['url'] ?? $apiUrl . '/payment/pay') . '?token=' . ($data['token'] ?? '');

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
        $flowOrder = (string)($flowData['flowOrder'] ?? '');
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

        // Idempotency: if already paid, return early (no state change)
        if ($payment->status === PaymentStatus::Paid) {
            Log::info('Webhook duplicado ignorado', ['payment_id' => $payment->id]);
            return $payment;
        }

        // Also skip if payment already failed or refunded (no going backward)
        if (in_array($payment->status, [PaymentStatus::Refunded], true)) {
            Log::info('Webhook para pago refunded ignorado', ['payment_id' => $payment->id]);
            return $payment;
        }

        return DB::transaction(function () use ($payment, $flowData, $flowOrder, $flowStatus) {
            // Re-lock payment row inside transaction to prevent race conditions
            $payment = Payment::lockForUpdate()->find($payment->id);

            // Double-check idempotency inside transaction
            if ($payment->status === PaymentStatus::Paid) {
                return $payment;
            }

            $payment->update([
                'flow_order' => $flowOrder,
                'raw_payload_json' => $flowData,
            ]);

            // Flow status: 2 = paid, 3 = rejected, 4 = cancelled
            if ($flowStatus === 2) {
                $payment->update(['status' => PaymentStatus::Paid]);

                $resume = $payment->resume;
                $resume->transitionTo(ResumeStatus::Paid);

                MetricsDaily::incrementToday('paid');
                MetricsDaily::incrementToday('revenue', $payment->amount);

                AuditLog::record('payment.confirmed', $payment->user_id, 'system', [
                    'payment_id' => $payment->id,
                    'resume_id' => $resume->id,
                    'flow_order' => $flowOrder,
                ]);
            } else {
                $payment->update(['status' => PaymentStatus::Failed]);

                AuditLog::record('payment.failed', $payment->user_id, 'system', [
                    'payment_id' => $payment->id,
                    'flow_status' => $flowStatus,
                ]);
            }

            return $payment->fresh();
        });
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
