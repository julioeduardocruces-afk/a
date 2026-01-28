<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\ResumeStatus;
use App\Jobs\GenerateFinalCvJob;
use App\Models\ApiCredential;
use App\Models\Payment;
use App\Models\Resume;
use App\Models\User;
use App\Services\PaymentFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function setupPaymentScenario(): Payment
    {
        $user = User::factory()->create();

        $resume = Resume::create([
            'user_id' => $user->id,
            'original_filename' => 'test.pdf',
            'original_mime' => 'application/pdf',
            'original_path' => 'uploads/test.pdf',
            'status' => ResumeStatus::PreviewReady,
            'target_role' => 'Developer',
            'target_industry' => 'TI',
        ]);

        $payment = Payment::create([
            'user_id' => $user->id,
            'resume_id' => $resume->id,
            'provider' => 'flow',
            'amount' => 4990,
            'currency' => 'CLP',
            'status' => PaymentStatus::Pending,
            'flow_token' => 'test-flow-token-123',
            'flow_order' => 'FO-12345',
        ]);

        // Create Flow credential
        $cred = new ApiCredential();
        $cred->provider = 'flow';
        $cred->name = 'Test Flow';
        $cred->setCredentials([
            'api_key' => 'test-key',
            'secret_key' => 'test-secret',
            'api_url' => 'https://www.flow.cl/api',
        ]);
        $cred->is_active = true;
        $cred->save();

        return $payment;
    }

    public function test_webhook_idempotency_paid_stays_paid(): void
    {
        Queue::fake();

        $payment = $this->setupPaymentScenario();

        // First: manually mark as paid (simulate first webhook)
        $payment->update(['status' => PaymentStatus::Paid]);
        $payment->resume->update(['status' => ResumeStatus::Paid]);

        // Mock Flow API response
        Http::fake([
            'www.flow.cl/api/*' => Http::response([
                'flowOrder' => $payment->flow_order,
                'commerceOrder' => (string)$payment->id,
                'status' => 2, // paid
            ]),
        ]);

        $service = app(PaymentFlowService::class);

        // Second webhook (duplicate) should not throw
        $result = $service->handleWebhook(['token' => $payment->flow_token]);

        $this->assertEquals(PaymentStatus::Paid, $result->status);
        // Resume should still be paid (not re-transitioned)
        $this->assertEquals(ResumeStatus::Paid, $payment->resume->fresh()->status);

        // Duplicate webhook should re-dispatch the job for a stuck Paid resume
        // (recovery mechanism for crash between payment commit and job dispatch)
        Queue::assertPushed(GenerateFinalCvJob::class, function ($job) use ($payment) {
            return true; // Job was dispatched for the stuck resume
        });
    }

    public function test_webhook_missing_token_throws(): void
    {
        $this->expectException(\RuntimeException::class);

        $service = app(PaymentFlowService::class);
        $service->handleWebhook([]);
    }
}
