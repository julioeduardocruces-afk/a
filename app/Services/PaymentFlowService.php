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

        $credential = ApiCredential::getNextForProvider('flow');
        if (!$credential) {
            throw new RuntimeException('No hay credenciales Flow activas.');
        }

        $creds = $credential->getDecryptedCredentials();
        $apiKey = $creds['api_key'] ?? '';
        $secretKey = $creds['secret_key'] ?? '';
        $apiUrl = $creds['api_url'] ?? 'https://www.flow.cl/api';

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

        // Sign the request
        ksort($params);
        $toSign = '';
        foreach ($params as $key => $value) {
            $toSign .= "{$key}{$value}";
        }
        $params['s'] = hash_hmac('sha256', $toSign, $secretKey);

        $response = Http::asForm()->post("{$apiUrl}/payment/create", $params);

        if (!$response->successful()) {
            $payment->update(['status' => PaymentStatus::Failed, 'raw_payload_json' => $response->json()]);
            throw new RuntimeException('Error al crear pago en Flow: ' . $response->body());
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
     */
    public function handleWebhook(array $payload): Payment
    {
        $token = $payload['token'] ?? null;
        if (empty($token)) {
            throw new RuntimeException('Webhook sin token.');
        }

        // Verify the payment status with Flow
        $credential = ApiCredential::getNextForProvider('flow');
        if (!$credential) {
            throw new RuntimeException('No hay credenciales Flow activas para verificar.');
        }

        $creds = $credential->getDecryptedCredentials();
        $apiKey = $creds['api_key'] ?? '';
        $secretKey = $creds['secret_key'] ?? '';
        $apiUrl = $creds['api_url'] ?? 'https://www.flow.cl/api';

        $params = [
            'apiKey' => $apiKey,
            'token' => $token,
        ];

        ksort($params);
        $toSign = '';
        foreach ($params as $key => $value) {
            $toSign .= "{$key}{$value}";
        }
        $params['s'] = hash_hmac('sha256', $toSign, $secretKey);

        $response = Http::asForm()->get("{$apiUrl}/payment/getStatus", $params);

        if (!$response->successful()) {
            throw new RuntimeException('Error verificando pago con Flow: ' . $response->body());
        }

        $flowData = $response->json();
        $flowOrder = (string)($flowData['flowOrder'] ?? '');
        $commerceOrder = (string)($flowData['commerceOrder'] ?? '');
        $flowStatus = (int)($flowData['status'] ?? 0);

        // Find payment - idempotency: use flow_order or commerce_order
        $payment = Payment::where('flow_token', $token)
            ->orWhere('flow_order', $flowOrder)
            ->orWhere('id', $commerceOrder)
            ->first();

        if (!$payment) {
            throw new RuntimeException("Pago no encontrado para token: {$token}");
        }

        // Idempotency: if already paid, return early
        if ($payment->status === PaymentStatus::Paid) {
            Log::info('Webhook duplicado ignorado', ['payment_id' => $payment->id]);
            return $payment;
        }

        return DB::transaction(function () use ($payment, $flowData, $flowOrder, $flowStatus) {
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
}
