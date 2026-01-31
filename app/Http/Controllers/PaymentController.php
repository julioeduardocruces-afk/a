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

        // Payment allowed from Draft (normal flow) or Failed (retry after failure)
        if (!in_array($resume->status, [ResumeStatus::Draft, ResumeStatus::Failed])) {
            return back()->withErrors(['status' => 'El CV no esta en un estado valido para pago.']);
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

        // This route only performs redirects (no sensitive data exposed).
        // Payment confirmation is handled securely via the webhook endpoint.
        // Session tokens may be lost after external redirect to Flow gateway,
        // so we restore the resume token only for recent payments (< 30 min)
        // to limit the window if someone guesses a payment ID.
        $resume = $paymentModel->resume;
        if ($resume && $paymentModel->created_at->diffInMinutes(now()) < 30) {
            $tokens = $request->session()->get('resume_tokens', []);
            if (!in_array($resume->access_token, $tokens, true)) {
                $tokens[] = $resume->access_token;
                $request->session()->put('resume_tokens', $tokens);
            }
        }

        if ($paymentModel->status === PaymentStatus::Paid) {
            return redirect()->route('resumes.status', $paymentModel->resume_id)
                ->with('success', 'Pago confirmado. Tu CV optimizado sera generado y enviado a tu email.');
        }

        // If not yet paid, the webhook may still be processing
        return redirect()->route('resumes.status', $paymentModel->resume_id)
            ->with('info', 'Pago pendiente de confirmacion. Recibiras un email cuando tu CV este listo.');
    }
}
