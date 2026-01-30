<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiCredential;
use App\Models\AuditLog;
use App\Models\MetricsDaily;
use App\Models\Resume;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\AiOptimizerService;
use App\Enums\ResumeStatus;
use App\Jobs\ProcessResumeJob;
use App\Jobs\GenerateFinalCvJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $metrics = MetricsDaily::orderByDesc('date')->limit(30)->get();

        // DB-level aggregation instead of loading rows into PHP and summing.
        // Cache for 2 minutes to avoid running the aggregate on every admin page load.
        $totals = cache()->remember('admin:metrics_totals_30d', 120, function () {
            return MetricsDaily::where('date', '>=', now()->subDays(30)->toDateString())
                ->selectRaw('COALESCE(SUM(uploads),0) as uploads, COALESCE(SUM(previews),0) as previews, COALESCE(SUM(paid),0) as paid, COALESCE(SUM(revenue),0) as revenue')
                ->first()
                ?->toArray() ?? ['uploads' => 0, 'previews' => 0, 'paid' => 0, 'revenue' => 0];
        });

        $recentResumes = Resume::with('user')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        // Cache status counts for 2 minutes — this is a full-table GROUP BY.
        $statusCounts = cache()->remember('admin:status_counts', 120, function () {
            return Resume::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status');
        });

        return view('admin.dashboard', compact('metrics', 'totals', 'recentResumes', 'statusCounts'));
    }

    // --- Credentials Management ---

    public function credentials()
    {
        $credentials = ApiCredential::orderBy('provider')->orderBy('name')->get();

        // Load current active configs for pre-populating forms
        $activeAi = ApiCredential::whereIn('provider', ['openai', 'gemini'])
            ->where('is_active', true)->first();
        $activeFlow = ApiCredential::where('provider', 'flow')->where('is_active', true)->first();

        $currentAi = $activeAi ? $activeAi->getDecryptedCredentials() : [];
        $currentAiProvider = $activeAi?->provider;
        $currentFlow = $activeFlow ? $activeFlow->getDecryptedCredentials() : [];
        $flowEnabled = $activeFlow?->is_active ?? false;

        $defaultPrompt = AiOptimizerService::DEFAULT_SYSTEM_PROMPT;
        $systemPrompt = Setting::getValue('ai_system_prompt', $defaultPrompt);

        return view('admin.credentials', compact(
            'credentials', 'currentAi', 'currentAiProvider', 'currentFlow', 'flowEnabled',
            'systemPrompt', 'defaultPrompt'
        ));
    }

    public function storeCredential(Request $request)
    {
        $formType = $request->input('form_type');

        if ($formType === 'ai') {
            return $this->storeAiCredential($request);
        }

        if ($formType === 'flow') {
            return $this->storeFlowCredential($request);
        }

        if ($formType === 'prompt') {
            return $this->storeSystemPrompt($request);
        }

        return back()->withErrors(['form_type' => 'Tipo de formulario no reconocido.']);
    }

    private function storeAiCredential(Request $request)
    {
        $validated = $request->validate([
            'ai_provider' => ['required', 'in:openai,gemini'],
            'ai_model' => ['required', 'string', 'max:100'],
            'ai_api_key' => ['nullable', 'string', 'max:500'],
            'ai_max_tokens' => ['required', 'integer', 'min:100', 'max:8000'],
            'ai_temperature' => ['required', 'numeric', 'min:0', 'max:2'],
        ]);

        // Validate model matches provider
        $allowedModels = [
            'openai' => ['gpt-4o-mini', 'gpt-4o', 'gpt-4-turbo'],
            'gemini' => ['gemini-1.5-pro', 'gemini-1.5-flash'],
        ];

        $provider = $validated['ai_provider'];
        if (!in_array($validated['ai_model'], $allowedModels[$provider] ?? [], true)) {
            return back()->withErrors(['ai_model' => 'Modelo no valido para el proveedor seleccionado.'])->withInput();
        }

        $credData = [
            'model' => $validated['ai_model'],
            'max_tokens' => (int) $validated['ai_max_tokens'],
            'temperature' => (float) $validated['ai_temperature'],
        ];

        // Only include API key if provided (allows updating other fields without changing key)
        if (!empty($validated['ai_api_key'])) {
            $credData['api_key'] = $validated['ai_api_key'];
        } else {
            // Check if there's an existing credential to preserve the key
            $existing = ApiCredential::where('provider', $provider)->where('is_active', true)->first();
            if ($existing) {
                $existingData = $existing->getDecryptedCredentials();
                if (!empty($existingData['api_key'])) {
                    $credData['api_key'] = $existingData['api_key'];
                }
            }
            if (empty($credData['api_key'])) {
                return back()->withErrors(['ai_api_key' => 'Se requiere una API Key.'])->withInput();
            }
        }

        $providerLabels = ['openai' => 'OpenAI', 'gemini' => 'Google Gemini'];

        $cred = new ApiCredential();
        $cred->provider = $provider;
        $cred->name = ($providerLabels[$provider] ?? $provider) . ' - ' . $validated['ai_model'];
        $cred->setCredentials($credData);
        $cred->is_active = true;
        $cred->save();

        AuditLog::record('admin.credential_created', $request->user()->id, 'admin', [
            'credential_id' => $cred->id,
            'provider' => $cred->provider,
        ], $request->ip());

        return back()->with('success', 'Credencial de IA guardada exitosamente.');
    }

    private function storeFlowCredential(Request $request)
    {
        $validated = $request->validate([
            'flow_enabled' => ['nullable'],
            'flow_environment' => ['required', 'in:sandbox,production'],
            'flow_api_key' => ['nullable', 'string', 'max:500'],
            'flow_secret_key' => ['nullable', 'string', 'max:500'],
        ]);

        $environment = $validated['flow_environment'];
        $apiUrls = [
            'sandbox' => 'https://sandbox.flow.cl/api',
            'production' => 'https://www.flow.cl/api',
        ];

        $credData = [
            'environment' => $environment,
            'api_url' => $apiUrls[$environment],
        ];

        // Preserve existing keys if not provided
        $existing = ApiCredential::where('provider', 'flow')->where('is_active', true)->first();
        $existingData = $existing ? $existing->getDecryptedCredentials() : [];

        if (!empty($validated['flow_api_key'])) {
            $credData['api_key'] = $validated['flow_api_key'];
        } elseif (!empty($existingData['api_key'])) {
            $credData['api_key'] = $existingData['api_key'];
        } else {
            return back()->withErrors(['flow_api_key' => 'Se requiere una API Key de Flow.'])->withInput();
        }

        if (!empty($validated['flow_secret_key'])) {
            $credData['secret_key'] = $validated['flow_secret_key'];
        } elseif (!empty($existingData['secret_key'])) {
            $credData['secret_key'] = $existingData['secret_key'];
        } else {
            return back()->withErrors(['flow_secret_key' => 'Se requiere un Secret Key de Flow.'])->withInput();
        }

        $cred = new ApiCredential();
        $cred->provider = 'flow';
        $cred->name = 'Flow - ' . ucfirst($environment);
        $cred->setCredentials($credData);
        $cred->is_active = (bool) $request->input('flow_enabled', false);
        $cred->save();

        AuditLog::record('admin.credential_created', $request->user()->id, 'admin', [
            'credential_id' => $cred->id,
            'provider' => 'flow',
        ], $request->ip());

        return back()->with('success', 'Credencial de Flow guardada exitosamente.');
    }

    private function storeSystemPrompt(Request $request)
    {
        $validated = $request->validate([
            'system_prompt' => ['nullable', 'string', 'max:10000'],
        ]);

        $prompt = trim($validated['system_prompt'] ?? '');

        // If empty, remove custom prompt (will use default)
        if (empty($prompt)) {
            Setting::setValue('ai_system_prompt', null);
        } else {
            Setting::setValue('ai_system_prompt', $prompt);
        }

        AuditLog::record('admin.system_prompt_updated', $request->user()->id, 'admin', [
            'prompt_length' => strlen($prompt),
        ], $request->ip());

        return back()->with('success', 'Prompt del sistema actualizado exitosamente.');
    }

    public function toggleCredential(Request $request, int $id)
    {
        $cred = ApiCredential::findOrFail($id);

        // Prevent deactivating the last active credential for a provider.
        // Without this guard, admin can accidentally break all CV processing
        // or payment creation by deactivating every credential for a provider.
        if ($cred->is_active) {
            $activeCount = ApiCredential::where('provider', $cred->provider)
                ->where('is_active', true)
                ->count();

            if ($activeCount <= 1) {
                return back()->withErrors([
                    'credential' => "No se puede desactivar la unica credencial activa para {$cred->provider}.",
                ]);
            }
        }

        $cred->update(['is_active' => !$cred->is_active]);

        AuditLog::record('admin.credential_toggled', $request->user()->id, 'admin', [
            'credential_id' => $id,
            'is_active' => $cred->is_active,
        ], $request->ip());

        return back()->with('success', 'Credencial actualizada.');
    }

    public function destroyCredential(Request $request, int $id)
    {
        $cred = ApiCredential::findOrFail($id);

        // Prevent deleting the last active credential for a provider.
        // toggleCredential() blocks deactivation, but without this guard
        // an admin could bypass that protection by deleting instead.
        if ($cred->is_active) {
            $activeCount = ApiCredential::where('provider', $cred->provider)
                ->where('is_active', true)
                ->count();

            if ($activeCount <= 1) {
                return back()->withErrors([
                    'credential' => "No se puede eliminar la unica credencial activa para {$cred->provider}. Desactivela primero o agregue otra.",
                ]);
            }
        }

        $cred->delete();

        AuditLog::record('admin.credential_deleted', $request->user()->id, 'admin', [
            'credential_id' => $id,
        ], $request->ip());

        return back()->with('success', 'Credencial eliminada.');
    }

    // --- Resume Management ---

    public function resumes(Request $request)
    {
        $query = Resume::with('user', 'latestVersion');

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $resumes = $query->orderByDesc('created_at')->paginate(25);

        return view('admin.resumes', compact('resumes'));
    }

    public function retryResume(Request $request, int $id)
    {
        $resume = Resume::findOrFail($id);

        if ($resume->status !== ResumeStatus::Failed) {
            return back()->withErrors(['status' => 'Solo CVs fallidos pueden reintentarse.']);
        }

        // Prevent reprocessing a resume that already has a confirmed payment.
        // If the failure was during delivery (delivery_error), the admin should
        // use "Resend Email" instead. Reprocessing would send the user back to
        // PreviewReady, losing their paid status and forcing them to pay again.
        $hasPaidPayment = Payment::where('resume_id', $resume->id)
            ->where('status', \App\Enums\PaymentStatus::Paid)
            ->exists();

        if ($hasPaidPayment) {
            return back()->withErrors([
                'status' => 'Este CV tiene un pago confirmado. Use "Reenviar Email" en vez de reprocesar.',
            ]);
        }

        // Set error fields as dirty attributes — transitionTo() calls save()
        // which persists ALL dirty attributes in one query, avoiding a separate UPDATE.
        $resume->error_code = null;
        $resume->error_message = null;
        $resume->transitionTo(ResumeStatus::Processing);

        ProcessResumeJob::dispatch($resume->id);

        AuditLog::record('admin.resume_retried', $request->user()->id, 'admin', [
            'resume_id' => $id,
        ], $request->ip());

        return back()->with('success', 'Procesamiento reintentado.');
    }

    public function resendEmail(Request $request, int $id)
    {
        $resume = Resume::findOrFail($id);

        // Allow resend for: Paid (first attempt), Delivered (re-send), or Failed
        // with a confirmed payment (delivery_error — the resume was Paid but
        // delivery failed and it transitioned to Failed)
        $allowedDirectStatuses = [ResumeStatus::Paid, ResumeStatus::Delivered];

        if ($resume->status === ResumeStatus::Failed) {
            $hasPaidPayment = Payment::where('resume_id', $resume->id)
                ->where('status', \App\Enums\PaymentStatus::Paid)
                ->exists();
            if (!$hasPaidPayment) {
                return back()->withErrors(['status' => 'Este CV fallido no tiene pago confirmado.']);
            }
            // Clear error state; GenerateFinalCvJob accepts Failed resumes
            // with confirmed payment (see job's status check)
        } elseif (!in_array($resume->status, $allowedDirectStatuses)) {
            return back()->withErrors(['status' => 'Solo CVs pagados/entregados/fallidos con pago.']);
        }

        GenerateFinalCvJob::dispatch($resume->id);

        AuditLog::record('admin.email_resent', $request->user()->id, 'admin', [
            'resume_id' => $id,
        ], $request->ip());

        return back()->with('success', 'Reenvio de email programado.');
    }

    public function destroyResume(Request $request, int $id)
    {
        $resume = Resume::findOrFail($id);

        // Delete associated file from storage
        if ($resume->original_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($resume->original_path)) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($resume->original_path);
        }

        // Delete related records
        $resume->versions()->delete();
        $resume->payments()->delete();
        $resume->delete();

        AuditLog::record('admin.resume_deleted', $request->user()->id, 'admin', [
            'resume_id' => $id,
        ], $request->ip());

        return back()->with('success', 'CV eliminado exitosamente.');
    }

    // --- Audit Logs ---

    public function auditLogs(Request $request)
    {
        $logs = AuditLog::orderByDesc('created_at')->paginate(50);
        return view('admin.audit-logs', compact('logs'));
    }

    // --- Metrics ---

    public function metrics()
    {
        $daily = MetricsDaily::orderByDesc('date')->limit(90)->get();

        $byIndustry = Resume::selectRaw('target_industry, COUNT(*) as count')
            ->whereNotNull('target_industry')
            ->groupBy('target_industry')
            ->orderByDesc('count')
            ->limit(20)
            ->get();

        $errorsByStage = Resume::where('status', 'failed')
            ->selectRaw('error_code, COUNT(*) as count')
            ->groupBy('error_code')
            ->orderByDesc('count')
            ->limit(20)
            ->get();

        return view('admin.metrics', compact('daily', 'byIndustry', 'errorsByStage'));
    }
}
