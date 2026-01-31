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
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminFinanceController extends Controller
{
    /**
     * Financial dashboard with KPIs and period comparison.
     */
    public function dashboard(Request $request)
    {
        $period = $request->input('period', '30');
        $periodDays = in_array($period, ['7', '30', '90', '365']) ? (int) $period : 30;
        $fromDate = now()->subDays($periodDays)->toDateString();
        $prevFromDate = now()->subDays($periodDays * 2)->toDateString();
        $prevToDate = now()->subDays($periodDays)->toDateString();

        // Current period revenue
        $revenueStats = $this->getRevenueStats($fromDate);

        // Previous period revenue (for comparison)
        $prevRevenueStats = $this->getRevenueStats($prevFromDate, $prevToDate);

        // Refund stats
        $refundStats = cache()->remember("finance:refunds_{$periodDays}", 120, function () use ($fromDate) {
            return Payment::where('status', PaymentStatus::Refunded)
                ->where('created_at', '>=', $fromDate)
                ->selectRaw('
                    COUNT(*) as total_refunds,
                    COALESCE(SUM(COALESCE(refund_amount, amount)), 0) as total_refunded
                ')
                ->first()
                ?->toArray() ?? [];
        });

        // Payment status breakdown
        $paymentBreakdown = cache()->remember("finance:payment_breakdown_{$periodDays}", 120, function () use ($fromDate) {
            return Payment::where('created_at', '>=', $fromDate)
                ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(amount), 0) as total')
                ->groupBy('status')
                ->get()
                ->keyBy('status');
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

        // Daily revenue trend
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

        // Profit calculation (now includes refunds)
        $totalRevenueCLP = ($revenueStats['total_revenue'] ?? 0);
        $totalRefundedCLP = ($refundStats['total_refunded'] ?? 0);
        $netRevenueCLP = $totalRevenueCLP - $totalRefundedCLP;
        $totalAiCostUSD = ($aiCosts['total_cost_cents'] ?? 0);
        $usdToClp = (float) config('ats.usd_to_clp', 950);
        $aiCostCLP = ($totalAiCostUSD / 100) * $usdToClp * 100;
        $grossProfit = $netRevenueCLP - $aiCostCLP;
        $margin = $netRevenueCLP > 0 ? ($grossProfit / $netRevenueCLP) * 100 : 0;

        // Period comparison deltas
        $prevRevenue = $prevRevenueStats['total_revenue'] ?? 0;
        $prevSales = $prevRevenueStats['total_sales'] ?? 0;
        $revenueGrowth = $prevRevenue > 0
            ? (($totalRevenueCLP - $prevRevenue) / $prevRevenue) * 100 : 0;
        $salesGrowth = $prevSales > 0
            ? ((($revenueStats['total_sales'] ?? 0) - $prevSales) / $prevSales) * 100 : 0;

        return view('admin.finance.dashboard', compact(
            'period', 'periodDays', 'revenueStats', 'prevRevenueStats',
            'refundStats', 'paymentBreakdown', 'funnel',
            'aiCosts', 'dailyRevenue', 'dailyAiCost',
            'totalRevenueCLP', 'totalRefundedCLP', 'netRevenueCLP',
            'totalAiCostUSD', 'aiCostCLP',
            'grossProfit', 'margin', 'usdToClp',
            'revenueGrowth', 'salesGrowth'
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

        // Summary using a separate base query with same filters
        $baseQuery = Payment::query();
        if ($request->filled('status') && $request->status !== 'all') {
            $baseQuery->where('status', $request->status);
        }
        if ($request->filled('from')) {
            $baseQuery->where('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $baseQuery->where('created_at', '<=', $request->to . ' 23:59:59');
        }
        if ($request->filled('email')) {
            $email = $request->email;
            $baseQuery->where(function ($q) use ($email) {
                $q->whereHas('resume', fn($r) => $r->where('customer_email', 'like', "%{$email}%"))
                  ->orWhereHas('user', fn($u) => $u->where('email', 'like', "%{$email}%"));
            });
        }

        $summary = (object) [
            'total_paid' => (clone $baseQuery)->where('status', PaymentStatus::Paid)->count(),
            'total_revenue' => (clone $baseQuery)->where('status', PaymentStatus::Paid)->sum('amount'),
            'total_refunded' => (clone $baseQuery)->where('status', PaymentStatus::Refunded)->count(),
            'total_refund_amount' => (clone $baseQuery)->where('status', PaymentStatus::Refunded)
                ->selectRaw('COALESCE(SUM(COALESCE(refund_amount, amount)), 0) as total')->value('total') ?? 0,
            'total_failed' => (clone $baseQuery)->where('status', PaymentStatus::Failed)->count(),
            'total_pending' => (clone $baseQuery)->where('status', PaymentStatus::Pending)->count(),
        ];
        $summary->net_revenue = $summary->total_revenue - $summary->total_refund_amount;

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

        // Count by format using PHP (SQLite-compatible, avoids JSON_EXTRACT)
        $allDownloads = AuditLog::where('action', 'resume.downloaded')->get();
        $totalDownloads = $allDownloads->count();
        $pdfCount = $allDownloads->filter(fn($d) => ($d->metadata_json['format'] ?? 'pdf') === 'pdf')->count();
        $docxCount = $allDownloads->filter(fn($d) => ($d->metadata_json['format'] ?? '') === 'docx')->count();

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

        $totalCostCents = AiUsageLog::sum('cost_usd_cents');
        $totalTokens = AiUsageLog::sum('total_tokens');

        return view('admin.finance.ai-usage', compact(
            'logs', 'byProvider', 'totalCostCents', 'totalTokens'
        ));
    }

    /**
     * Settlement / Balance report.
     */
    public function settlement(Request $request)
    {
        $months = $request->input('months', 6);
        $months = in_array($months, [3, 6, 12, 24]) ? $months : 6;

        $usdToClp = (float) config('ats.usd_to_clp', 950);

        // Monthly breakdown
        $monthly = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = now()->subMonths($i)->startOfMonth()->toDateString();
            $monthEnd = now()->subMonths($i)->endOfMonth()->toDateString();
            $label = now()->subMonths($i)->format('Y-m');

            $revenue = Payment::where('status', PaymentStatus::Paid)
                ->whereBetween('created_at', [$monthStart, $monthEnd . ' 23:59:59'])
                ->sum('amount');

            $refunds = Payment::where('status', PaymentStatus::Refunded)
                ->whereBetween('created_at', [$monthStart, $monthEnd . ' 23:59:59'])
                ->selectRaw('COALESCE(SUM(COALESCE(refund_amount, amount)), 0) as total')
                ->value('total') ?? 0;

            $aiCostCents = AiUsageLog::whereBetween('created_at', [$monthStart, $monthEnd . ' 23:59:59'])
                ->sum('cost_usd_cents');

            $aiCostCLP = ($aiCostCents / 100) * $usdToClp * 100;
            $net = $revenue - $refunds;
            $profit = $net - $aiCostCLP;

            $sales = Payment::where('status', PaymentStatus::Paid)
                ->whereBetween('created_at', [$monthStart, $monthEnd . ' 23:59:59'])->count();
            $refundCount = Payment::where('status', PaymentStatus::Refunded)
                ->whereBetween('created_at', [$monthStart, $monthEnd . ' 23:59:59'])->count();

            $monthly[] = (object) compact(
                'label', 'revenue', 'refunds', 'net', 'aiCostCents', 'aiCostCLP', 'profit',
                'sales', 'refundCount'
            );
        }

        // All-time totals
        $allTimeRevenue = Payment::where('status', PaymentStatus::Paid)->sum('amount');
        $allTimeRefunds = Payment::where('status', PaymentStatus::Refunded)
            ->selectRaw('COALESCE(SUM(COALESCE(refund_amount, amount)), 0) as total')
            ->value('total') ?? 0;
        $allTimeAiCostCents = AiUsageLog::sum('cost_usd_cents');
        $allTimeAiCostCLP = ($allTimeAiCostCents / 100) * $usdToClp * 100;
        $allTimeNet = $allTimeRevenue - $allTimeRefunds;
        $allTimeProfit = $allTimeNet - $allTimeAiCostCLP;

        return view('admin.finance.settlement', compact(
            'monthly', 'months', 'usdToClp',
            'allTimeRevenue', 'allTimeRefunds', 'allTimeNet',
            'allTimeAiCostCents', 'allTimeAiCostCLP', 'allTimeProfit'
        ));
    }

    /**
     * Refund management page.
     */
    public function refunds(Request $request)
    {
        $query = Payment::with(['resume', 'user'])
            ->where('status', PaymentStatus::Refunded);

        if ($request->filled('from')) {
            $query->where('refunded_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('refunded_at', '<=', $request->to . ' 23:59:59');
        }

        $refunds = $query->orderByDesc('refunded_at')->paginate(30)->withQueryString();

        // Refundable payments (paid, not yet refunded)
        $refundable = Payment::with(['resume', 'user'])
            ->where('status', PaymentStatus::Paid)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $totalRefunds = Payment::where('status', PaymentStatus::Refunded)->count();
        $totalRefundAmount = Payment::where('status', PaymentStatus::Refunded)
            ->selectRaw('COALESCE(SUM(COALESCE(refund_amount, amount)), 0) as total')
            ->value('total') ?? 0;
        $totalPaid = Payment::where('status', PaymentStatus::Paid)->count();
        $refundRate = ($totalPaid + $totalRefunds) > 0
            ? ($totalRefunds / ($totalPaid + $totalRefunds)) * 100 : 0;

        return view('admin.finance.refunds', compact(
            'refunds', 'refundable', 'totalRefunds', 'totalRefundAmount', 'refundRate'
        ));
    }

    /**
     * Process a refund on a paid payment.
     */
    public function processRefund(Request $request, Payment $payment)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
            'amount' => 'nullable|integer|min:1|max:' . $payment->amount,
        ]);

        if ($payment->status !== PaymentStatus::Paid) {
            return back()->with('error', 'Solo se pueden reembolsar pagos con estado "pagado".');
        }

        $refundAmount = $request->input('amount', $payment->amount);
        $payment->markRefunded($request->reason, (int) $refundAmount);

        AuditLog::record('payment.refunded', auth()->id(), 'admin', [
            'payment_id' => $payment->id,
            'resume_id' => $payment->resume_id,
            'amount' => $refundAmount,
            'reason' => $request->reason,
        ]);

        return back()->with('success', "Pago #{$payment->id} reembolsado correctamente.");
    }

    // ── CSV Exports ──────────────────────────────────────────

    public function exportSales(Request $request): StreamedResponse
    {
        $query = Payment::with(['resume', 'user'])->orderByDesc('created_at');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->to . ' 23:59:59');
        }

        return $this->streamCsv('ventas_' . date('Y-m-d') . '.csv', function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Fecha', 'Email', 'CV ID', 'Rubro', 'Monto CLP', 'Estado', 'Proveedor', 'Flow Order', 'Razon Fallo', 'Razon Reembolso', 'Monto Reembolso', 'Fecha Reembolso']);

            $query->chunk(100, function ($payments) use ($handle) {
                foreach ($payments as $p) {
                    fputcsv($handle, [
                        $p->id,
                        $p->created_at->format('Y-m-d H:i:s'),
                        $p->resume?->customer_email ?? $p->user?->email ?? '',
                        $p->resume_id,
                        $p->resume?->target_industry ?? '',
                        $p->amount / 100,
                        $p->status->value ?? $p->status,
                        $p->provider,
                        $p->flow_order ?? '',
                        $p->failure_reason ?? '',
                        $p->refund_reason ?? '',
                        $p->refund_amount ? $p->refund_amount / 100 : '',
                        $p->refunded_at?->format('Y-m-d H:i:s') ?? '',
                    ]);
                }
            });

            fclose($handle);
        });
    }

    public function exportDownloads(Request $request): StreamedResponse
    {
        $query = AuditLog::where('action', 'resume.downloaded')->orderByDesc('created_at');

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->to . ' 23:59:59');
        }

        return $this->streamCsv('descargas_' . date('Y-m-d') . '.csv', function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Fecha', 'Actor Tipo', 'Actor ID', 'CV ID', 'Token ID', 'Formato', 'IP']);

            $query->chunk(100, function ($downloads) use ($handle) {
                foreach ($downloads as $d) {
                    $meta = $d->metadata_json ?? [];
                    fputcsv($handle, [
                        $d->created_at->format('Y-m-d H:i:s'),
                        $d->actor_type,
                        $d->actor_id ?? '',
                        $meta['resume_id'] ?? '',
                        $meta['token_id'] ?? '',
                        $meta['format'] ?? 'pdf',
                        $d->ip ?? '',
                    ]);
                }
            });

            fclose($handle);
        });
    }

    public function exportAiUsage(Request $request): StreamedResponse
    {
        $query = AiUsageLog::orderByDesc('created_at');

        if ($request->filled('provider') && $request->provider !== 'all') {
            $query->where('provider', $request->provider);
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->to . ' 23:59:59');
        }

        return $this->streamCsv('uso_ia_' . date('Y-m-d') . '.csv', function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Fecha', 'CV ID', 'Proveedor', 'Modelo', 'Tokens Prompt', 'Tokens Resp', 'Tokens Total', 'Costo USD', 'Tiempo ms', 'Exitoso', 'Error']);

            $query->chunk(100, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->created_at->format('Y-m-d H:i:s'),
                        $log->resume_id ?? '',
                        $log->provider,
                        $log->model,
                        $log->prompt_tokens,
                        $log->completion_tokens,
                        $log->total_tokens,
                        $log->cost_usd_cents / 100,
                        $log->response_time_ms,
                        $log->success ? 'Si' : 'No',
                        $log->error_message ?? '',
                    ]);
                }
            });

            fclose($handle);
        });
    }

    // ── Helpers ───────────────────────────────────────────────

    private function getRevenueStats(string $from, ?string $to = null): array
    {
        $query = Payment::where('status', PaymentStatus::Paid)
            ->where('created_at', '>=', $from);

        if ($to) {
            $query->where('created_at', '<=', $to . ' 23:59:59');
        }

        return $query->selectRaw('
                COUNT(*) as total_sales,
                COALESCE(SUM(amount), 0) as total_revenue,
                COALESCE(AVG(amount), 0) as avg_ticket,
                COALESCE(MIN(amount), 0) as min_ticket,
                COALESCE(MAX(amount), 0) as max_ticket
            ')
            ->first()
            ?->toArray() ?? [];
    }

    private function streamCsv(string $filename, callable $callback): StreamedResponse
    {
        return response()->streamDownload(function () use ($callback) {
            $callback();
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
