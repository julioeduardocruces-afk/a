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
        $payment = DB::transaction(function () use ($resume, $amount) {
            $existingPayment = Payment::where('resume_id', $resume->id)
                ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Paid])
                ->lockForUpdate()
                ->first();

            if ($existingPayment?->status === PaymentStatus::Paid) {
                throw new RuntimeException('Este CV ya fue pagado.');
            }

            if ($existingPayment?->status === PaymentStatus::Pending) {
                $existingPayment->update(['status' => PaymentStatus::Failed]);
            }

            return Payment::create([
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
            'email' => $resume->getEmail() ?? 'noreply@cvoptimizer.cl',
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

        AuditLog::record('payment.created', null, 'anonymous', [
            'payment_id' => $payment->id,
            'resume_id' => $resume->id,
            'amount' => $amount,
        ]);

        // Validate redirect URL comes from expected Flow domain
        $rawUrl = $data['url'] ?? $apiUrl . '/payment/pay';
        $parsedHost = parse_url($rawUrl, PHP_URL_HOST);
        $allowedHosts = ['www.flow.cl', 'flow.cl', 'sandbox.flow.cl'];
        if (!$parsedHost || !in_array($parsedHost, $allowedHosts, true)) {
            Log::warning('Flow returned unexpected redirect URL', [
                'payment_id' => $payment->id,
                'url' => $rawUrl,
            ]);
            throw new RuntimeException('URL de pago no confiable.');
        }

        $redirectUrl = $rawUrl . '?token=' . urlencode($data['token']);

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
        $flowOrder = !empty($flowData['flowOrder']) ? (string)$flowData['flowOrder'] : null;
        $commerceOrder = (string)($flowData['commerceOrder'] ?? '');
        $flowStatus = (int)($flowData['status'] ?? 0);

        $payment = Payment::with('resume')->where('flow_token', $token)->first();
        if (!$payment && !empty($commerceOrder)) {
            $payment = Payment::with('resume')->find((int)$commerceOrder);
        }

        if (!$payment) {
            throw new RuntimeException("Pago no encontrado para token: {$token}");
        }

        // Idempotency: if already paid, check if the delivery job needs re-dispatch
        if ($payment->status === PaymentStatus::Paid) {
            $resume = $payment->resume;
            $resume?->refresh();
            if ($resume && $resume->status === ResumeStatus::PreviewReady) {
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

        if (in_array($payment->status, [PaymentStatus::Failed, PaymentStatus::Refunded], true)) {
            Log::warning('Webhook para pago cancelado/refunded ignorado', [
                'payment_id' => $payment->id,
                'payment_status' => $payment->status->value,
                'flow_status' => $flowStatus,
            ]);

            if ($flowStatus === 2) {
                Log::critical('Flow confirmed a locally-cancelled payment — potential double charge, refund needed', [
                    'payment_id' => $payment->id,
                    'flow_order' => $flowOrder,
                    'amount' => $payment->amount,
                ]);

                AuditLog::record('payment.cancelled_but_charged', null, 'system', [
                    'payment_id' => $payment->id,
                    'flow_order' => $flowOrder,
                    'amount' => $payment->amount,
                ]);
            }

            return $payment;
        }

        // Step 1: Atomically update payment status
        $payment = DB::transaction(function () use ($payment, $flowData, $flowOrder, $flowStatus) {
            $payment = Payment::lockForUpdate()->find($payment->id);

            if ($payment->status === PaymentStatus::Paid) {
                return $payment;
            }

            $newStatus = $flowStatus === 2 ? PaymentStatus::Paid : PaymentStatus::Failed;

            $payment->update([
                'flow_order' => $flowOrder,
                'raw_payload_json' => $flowData,
                'status' => $newStatus,
            ]);

            if ($newStatus === PaymentStatus::Failed) {
                AuditLog::record('payment.failed', null, 'system', [
                    'payment_id' => $payment->id,
                    'flow_status' => $flowStatus,
                ]);
            }

            return $payment;
        });

        // Step 2: If payment confirmed, transition resume and dispatch job
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

                AuditLog::record('payment.confirmed_transition_failed', null, 'system', [
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

            AuditLog::record('payment.confirmed', null, 'system', [
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
