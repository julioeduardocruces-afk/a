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
        $totals = [
            'uploads' => $metrics->sum('uploads'),
            'previews' => $metrics->sum('previews'),
            'paid' => $metrics->sum('paid'),
            'revenue' => $metrics->sum('revenue'),
        ];

        $recentResumes = Resume::with('user')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $statusCounts = Resume::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

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

        $resume->update([
            'status' => ResumeStatus::Processing,
            'error_code' => null,
            'error_message' => null,
        ]);

        ProcessResumeJob::dispatch($resume->id);

        AuditLog::record('admin.resume_retried', $request->user()->id, 'admin', [
            'resume_id' => $id,
        ], $request->ip());

        return back()->with('success', 'Procesamiento reintentado.');
    }

    public function resendEmail(Request $request, int $id)
    {
        $resume = Resume::findOrFail($id);

        if (!in_array($resume->status, [ResumeStatus::Paid, ResumeStatus::Delivered])) {
            return back()->withErrors(['status' => 'Solo CVs pagados/entregados.']);
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
