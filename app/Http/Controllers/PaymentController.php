<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\ResumeStatus;
use App\Jobs\GenerateFinalCvJob;
use App\Models\AuditLog;
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
     * POST /payments/flow/create - Initiate payment.
     */
    public function create(Request $request)
    {
        $validated = $request->validate([
            'resume_id' => ['required', 'integer', 'exists:resumes,id'],
        ]);

        $resume = Resume::findOrFail($validated['resume_id']);

        // IDOR check
        if (!$resume->belongsToUser($request->user()->id)) {
            abort(403);
        }

        if ($resume->status !== ResumeStatus::PreviewReady) {
            return back()->withErrors(['status' => 'El CV debe estar listo para pago.']);
        }

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
            $payment = $this->paymentService->handleWebhook($request->all());

            // If payment was successful, dispatch final CV generation
            if ($payment->status === PaymentStatus::Paid) {
                GenerateFinalCvJob::dispatch($payment->resume_id);
            }

            return response('OK', 200);
        } catch (\Exception $e) {
            Log::error('Flow webhook error', [
                'payload' => $request->all(),
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
        $paymentModel = \App\Models\Payment::findOrFail($payment);

        if (!$paymentModel->user_id === $request->user()?->id) {
            abort(403);
        }

        if ($paymentModel->status === PaymentStatus::Paid) {
            return redirect()->route('resumes.status', $paymentModel->resume_id)
                ->with('success', 'Pago confirmado. Tu CV optimizado sera generado y enviado a tu email.');
        }

        return redirect()->route('resumes.preview-page', $paymentModel->resume_id)
            ->with('info', 'Pago pendiente o no completado. Puedes intentar nuevamente.');
    }
}
