<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\PaymentStatus;
use App\Models\AiUsageLog;
use App\Models\AuditLog;
use App\Models\DownloadToken;
use App\Models\MetricsDaily;
use App\Models\Payment;
use App\Models\Resume;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminFinanceController extends Controller
{
    /**
     * Financial dashboard with KPIs.
     */
    public function dashboard(Request $request)
    {
        $period = $request->input('period', '30');
        $periodDays = in_array($period, ['7', '30', '90', '365']) ? (int) $period : 30;
        $fromDate = now()->subDays($periodDays)->toDateString();

        // Revenue KPIs
        $revenueStats = cache()->remember("finance:revenue_{$periodDays}", 120, function () use ($fromDate) {
            return Payment::where('status', PaymentStatus::Paid)
                ->where('created_at', '>=', $fromDate)
                ->selectRaw('
                    COUNT(*) as total_sales,
                    COALESCE(SUM(amount), 0) as total_revenue,
                    COALESCE(AVG(amount), 0) as avg_ticket,
                    COALESCE(MIN(amount), 0) as min_ticket,
                    COALESCE(MAX(amount), 0) as max_ticket
                ')
                ->first()
                ?->toArray() ?? [];
        });

        // Conversion funnel
        $funnel = cache()->remember("finance:funnel_{$periodDays}", 120, function () use ($fromDate) {
            $uploads = Resume::where('created_at', '>=', $fromDate)->count();
            $withTarget = Resume::where('created_at', '>=', $fromDate)
                ->whereNotNull('target_role')->count();
            $paymentAttempts = Payment::where('created_at', '>=', $fromDate)->count();
            $paid = Payment::where('status', PaymentStatus::Paid)
                ->where('created_at', '>=', $fromDate)->count();
            $delivered = Resume::where('created_at', '>=', $fromDate)
                ->where('status', 'delivered')->count();
            $downloads = AuditLog::where('action', 'resume.downloaded')
                ->where('created_at', '>=', $fromDate)->count();

            return compact('uploads', 'withTarget', 'paymentAttempts', 'paid', 'delivered', 'downloads');
        });

        // AI costs
        $aiCosts = cache()->remember("finance:ai_costs_{$periodDays}", 120, function () use ($fromDate) {
            return AiUsageLog::where('created_at', '>=', $fromDate)
                ->selectRaw('
                    COUNT(*) as total_calls,
                    SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful_calls,
                    SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed_calls,
                    COALESCE(SUM(total_tokens), 0) as total_tokens,
                    COALESCE(SUM(cost_usd_cents), 0) as total_cost_cents,
                    COALESCE(AVG(response_time_ms), 0) as avg_response_ms
                ')
                ->first()
                ?->toArray() ?? [];
        });

        // Daily revenue trend (for chart)
        $dailyRevenue = Payment::where('status', PaymentStatus::Paid)
            ->where('created_at', '>=', $fromDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as sales, SUM(amount) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Daily AI cost trend
        $dailyAiCost = AiUsageLog::where('created_at', '>=', $fromDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as calls, SUM(cost_usd_cents) as cost_cents, SUM(total_tokens) as tokens')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Profit estimation (revenue - AI costs converted to CLP)
        $totalRevenueCLP = ($revenueStats['total_revenue'] ?? 0); // in CLP cents
        $totalAiCostUSD = ($aiCosts['total_cost_cents'] ?? 0); // in USD cents
        // Approximate USD to CLP (configurable via env)
        $usdToClp = (float) config('ats.usd_to_clp', 950);
        $aiCostCLP = ($totalAiCostUSD / 100) * $usdToClp * 100; // convert to CLP cents
        $grossProfit = $totalRevenueCLP - $aiCostCLP;
        $margin = $totalRevenueCLP > 0 ? ($grossProfit / $totalRevenueCLP) * 100 : 0;

        return view('admin.finance.dashboard', compact(
            'period', 'periodDays', 'revenueStats', 'funnel',
            'aiCosts', 'dailyRevenue', 'dailyAiCost',
            'totalRevenueCLP', 'totalAiCostUSD', 'aiCostCLP',
            'grossProfit', 'margin', 'usdToClp'
        ));
    }

    /**
     * Sales history (paginated payments).
     */
    public function sales(Request $request)
    {
        $query = Payment::with(['resume', 'user']);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->to . ' 23:59:59');
        }

        if ($request->filled('email')) {
            $email = $request->email;
            $query->where(function ($q) use ($email) {
                $q->whereHas('resume', fn($r) => $r->where('customer_email', 'like', "%{$email}%"))
                  ->orWhereHas('user', fn($u) => $u->where('email', 'like', "%{$email}%"));
            });
        }

        $payments = $query->orderByDesc('created_at')->paginate(30)->withQueryString();

        // Summary for filtered results
        $summaryQuery = Payment::where('status', PaymentStatus::Paid);
        if ($request->filled('from')) {
            $summaryQuery->where('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $summaryQuery->where('created_at', '<=', $request->to . ' 23:59:59');
        }
        $summary = $summaryQuery->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as total')
            ->first();

        return view('admin.finance.sales', compact('payments', 'summary'));
    }

    /**
     * Download history.
     */
    public function downloads(Request $request)
    {
        $query = AuditLog::where('action', 'resume.downloaded')
            ->orderByDesc('created_at');

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->to . ' 23:59:59');
        }

        $downloads = $query->paginate(30)->withQueryString();

        // Summary
        $totalDownloads = AuditLog::where('action', 'resume.downloaded')->count();
        $pdfCount = AuditLog::where('action', 'resume.downloaded')
            ->whereRaw("JSON_EXTRACT(metadata_json, '$.format') = '\"pdf\"'")->count();
        $docxCount = AuditLog::where('action', 'resume.downloaded')
            ->whereRaw("JSON_EXTRACT(metadata_json, '$.format') = '\"docx\"'")->count();

        // Tokens generated vs used
        $tokensGenerated = DownloadToken::count();
        $tokensUsed = DownloadToken::where('used', true)->count();
        $tokensExpired = DownloadToken::where('used', false)
            ->where('expires_at', '<', now())->count();

        return view('admin.finance.downloads', compact(
            'downloads', 'totalDownloads', 'pdfCount', 'docxCount',
            'tokensGenerated', 'tokensUsed', 'tokensExpired'
        ));
    }

    /**
     * AI token usage history.
     */
    public function aiUsage(Request $request)
    {
        $query = AiUsageLog::orderByDesc('created_at');

        if ($request->filled('provider') && $request->provider !== 'all') {
            $query->where('provider', $request->provider);
        }
        if ($request->filled('success') && $request->success !== 'all') {
            $query->where('success', $request->success === '1');
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->to . ' 23:59:59');
        }

        $logs = $query->paginate(30)->withQueryString();

        // Summaries by provider
        $byProvider = AiUsageLog::selectRaw('
                provider, model,
                COUNT(*) as calls,
                SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as ok,
                SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed,
                COALESCE(SUM(prompt_tokens), 0) as prompt_tokens,
                COALESCE(SUM(completion_tokens), 0) as completion_tokens,
                COALESCE(SUM(total_tokens), 0) as total_tokens,
                COALESCE(SUM(cost_usd_cents), 0) as cost_cents,
                COALESCE(AVG(response_time_ms), 0) as avg_ms
            ')
            ->groupBy('provider', 'model')
            ->orderByDesc('calls')
            ->get();

        // Total costs
        $totalCostCents = AiUsageLog::sum('cost_usd_cents');
        $totalTokens = AiUsageLog::sum('total_tokens');

        return view('admin.finance.ai-usage', compact(
            'logs', 'byProvider', 'totalCostCents', 'totalTokens'
        ));
    }
}
