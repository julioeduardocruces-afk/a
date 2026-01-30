<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\ResumeStatus;
use App\Models\Resume;
use App\Services\PaymentFlowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentFlowService $paymentService,
    ) {}

    /**
     * POST /payments/flow/create - Initiate payment (anonymous).
     */
    public function create(Request $request)
    {
        $validated = $request->validate([
            'resume_id' => ['required', 'integer', 'exists:resumes,id'],
            'customer_email' => ['required', 'email', 'max:255'],
        ]);

        $resume = Resume::findOrFail($validated['resume_id']);

        // Ownership check via session tokens
        $sessionTokens = $request->session()->get('resume_tokens', []);
        if (!in_array($resume->access_token, $sessionTokens, true)) {
            abort(403);
        }

        if ($resume->status !== ResumeStatus::PreviewReady) {
            return back()->withErrors(['status' => 'El CV debe estar listo para pago.']);
        }

        // Store customer email for delivery
        $resume->update(['customer_email' => $validated['customer_email']]);

        $amount = (int)config('ats.price_clp', 4990);

        try {
            $result = $this->paymentService->createPayment($resume, $amount);
            return redirect()->away($result['redirect_url']);
        } catch (\Exception $e) {
            Log::error('Payment creation failed', [
                'resume_id' => $resume->id,
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['payment' => 'Error al crear el pago. Intente nuevamente.']);
        }
    }

    /**
     * POST /payments/flow/webhook - Flow webhook callback (no auth, verified by signature).
     */
    public function webhook(Request $request)
    {
        try {
            $this->paymentService->handleWebhook($request->all());

            return response('OK', 200);
        } catch (\Exception $e) {
            $safePayload = array_intersect_key($request->all(), array_flip(['token', 'commerceOrder', 'status']));
            Log::error('Flow webhook error', [
                'payload' => $safePayload,
                'error' => $e->getMessage(),
            ]);
            return response('Error', 400);
        }
    }

    /**
     * GET /payments/flow/return - User returns from Flow after payment.
     */
    public function returnFromFlow(Request $request, int $payment)
    {
        $paymentModel = \App\Models\Payment::with('resume')->findOrFail($payment);

        // Verify ownership via session tokens on the resume
        $resume = $paymentModel->resume;
        $sessionTokens = $request->session()->get('resume_tokens', []);
        if (!$resume || !in_array($resume->access_token, $sessionTokens, true)) {
            if (!$request->user()?->is_admin) {
                abort(403);
            }
        }

        if ($paymentModel->status === PaymentStatus::Paid) {
            return redirect()->route('resumes.status', $paymentModel->resume_id)
                ->with('success', 'Pago confirmado. Tu CV optimizado sera generado y enviado a tu email.');
        }

        return redirect()->route('resumes.preview-page', $paymentModel->resume_id)
            ->with('info', 'Pago pendiente o no completado. Puedes intentar nuevamente.');
    }
}
