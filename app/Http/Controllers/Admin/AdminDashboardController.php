<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiCredential;
use App\Models\AuditLog;
use App\Models\MetricsDaily;
use App\Models\Resume;
use App\Models\Payment;
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
        return view('admin.credentials', compact('credentials'));
    }

    public function storeCredential(Request $request)
    {
        $validated = $request->validate([
            'provider' => ['required', 'in:openai,gemini,flow,smtp'],
            'name' => ['required', 'string', 'max:255'],
            'credentials' => ['required', 'string'], // JSON string
        ]);

        $jsonData = json_decode($validated['credentials'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return back()->withErrors(['credentials' => 'JSON invalido.']);
        }

        $cred = new ApiCredential();
        $cred->provider = $validated['provider'];
        $cred->name = $validated['name'];
        $cred->setCredentials($jsonData);
        $cred->is_active = true;
        $cred->save();

        AuditLog::record('admin.credential_created', $request->user()->id, 'admin', [
            'credential_id' => $cred->id,
            'provider' => $cred->provider,
        ], $request->ip());

        return back()->with('success', 'Credencial creada exitosamente.');
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
            ->get();

        return view('admin.metrics', compact('daily', 'byIndustry', 'errorsByStage'));
    }
}
