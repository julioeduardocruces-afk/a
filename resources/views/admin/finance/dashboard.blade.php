@extends('layouts.admin')
@section('title', 'Admin - Panel Financiero')
@section('admin-content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <h1 style="margin:0;">Panel Financiero</h1>
    <form method="GET" style="display:flex;gap:8px;align-items:center;">
        <label>Periodo:</label>
        <select name="period" onchange="this.form.submit()" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;">
            <option value="7" {{ $period == '7' ? 'selected' : '' }}>7 dias</option>
            <option value="30" {{ $period == '30' ? 'selected' : '' }}>30 dias</option>
            <option value="90" {{ $period == '90' ? 'selected' : '' }}>90 dias</option>
            <option value="365" {{ $period == '365' ? 'selected' : '' }}>365 dias</option>
        </select>
    </form>
</div>

{{-- Revenue KPIs --}}
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:20px;">
    <div class="card" style="text-align:center;border-left:4px solid #28a745;padding:16px;">
        <div style="font-size:1.6rem;font-weight:bold;color:#28a745;">${{ number_format($revenueStats['total_revenue'] ?? 0, 0, ',', '.') }}</div>
        <div style="color:#666;font-size:0.85rem;">Ingresos Brutos (CLP)</div>
        @if($revenueGrowth != 0)
            <div style="font-size:0.75rem;color:{{ $revenueGrowth > 0 ? '#28a745' : '#dc3545' }};margin-top:4px;">
                {{ $revenueGrowth > 0 ? '+' : '' }}{{ number_format($revenueGrowth, 1) }}% vs anterior
            </div>
        @endif
    </div>
    <div class="card" style="text-align:center;border-left:4px solid #0066ff;padding:16px;">
        <div style="font-size:1.6rem;font-weight:bold;color:#0066ff;">{{ $revenueStats['total_sales'] ?? 0 }}</div>
        <div style="color:#666;font-size:0.85rem;">Ventas</div>
        @if($salesGrowth != 0)
            <div style="font-size:0.75rem;color:{{ $salesGrowth > 0 ? '#28a745' : '#dc3545' }};margin-top:4px;">
                {{ $salesGrowth > 0 ? '+' : '' }}{{ number_format($salesGrowth, 1) }}% vs anterior
            </div>
        @endif
    </div>
    <div class="card" style="text-align:center;border-left:4px solid #17a2b8;padding:16px;">
        <div style="font-size:1.6rem;font-weight:bold;color:#17a2b8;">${{ number_format($revenueStats['avg_ticket'] ?? 0, 0, ',', '.') }}</div>
        <div style="color:#666;font-size:0.85rem;">Ticket Promedio</div>
    </div>
    <div class="card" style="text-align:center;border-left:4px solid #dc3545;padding:16px;">
        <div style="font-size:1.6rem;font-weight:bold;color:#dc3545;">{{ $refundStats['total_refunds'] ?? 0 }}</div>
        <div style="color:#666;font-size:0.85rem;">Reembolsos</div>
        <div style="font-size:0.75rem;color:#999;">${{ number_format($refundStats['total_refunded'] ?? 0, 0, ',', '.') }} CLP</div>
    </div>
    <div class="card" style="text-align:center;border-left:4px solid #ffc107;padding:16px;">
        <div style="font-size:1.6rem;font-weight:bold;color:#{{ $margin >= 50 ? '28a745' : ($margin >= 20 ? 'ffc107' : 'dc3545') }};">{{ number_format($margin, 1) }}%</div>
        <div style="color:#666;font-size:0.85rem;">Margen Neto</div>
    </div>
</div>

{{-- Payment Status Breakdown --}}
<div class="card" style="margin-bottom:20px;">
    <h3 style="margin-bottom:12px;">Estado de Pagos ({{ $periodDays }} dias)</h3>
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
        @php
            $statusConfig = [
                'paid' => ['label' => 'Pagados', 'color' => '#28a745'],
                'pending' => ['label' => 'Pendientes', 'color' => '#ffc107'],
                'failed' => ['label' => 'Fallidos', 'color' => '#dc3545'],
                'refunded' => ['label' => 'Reembolsados', 'color' => '#6c757d'],
            ];
        @endphp
        @foreach($statusConfig as $status => $cfg)
            @php $item = $paymentBreakdown[$status] ?? null; @endphp
            <div style="text-align:center;padding:10px 16px;background:{{ $cfg['color'] }}10;border-radius:8px;min-width:100px;flex:1;">
                <div style="font-size:1.3rem;font-weight:bold;color:{{ $cfg['color'] }};">{{ $item?->count ?? 0 }}</div>
                <div style="font-size:0.8rem;color:#666;">{{ $cfg['label'] }}</div>
                @if($item && $item->total > 0)
                    <div style="font-size:0.75rem;color:#999;">${{ number_format($item->total, 0, ',', '.') }}</div>
                @endif
            </div>
        @endforeach
    </div>
</div>

{{-- Profit Analysis --}}
<div class="card" style="margin-bottom:20px;">
    <h3 style="margin-bottom:12px;">Analisis de Rentabilidad ({{ $periodDays }} dias)</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;">
        <div>
            <div style="color:#666;font-size:0.85rem;">Ingresos Brutos</div>
            <div style="font-size:1.2rem;font-weight:bold;color:#28a745;">${{ number_format($totalRevenueCLP, 0, ',', '.') }}</div>
        </div>
        <div>
            <div style="color:#666;font-size:0.85rem;">Reembolsos</div>
            <div style="font-size:1.2rem;font-weight:bold;color:#dc3545;">-${{ number_format($totalRefundedCLP, 0, ',', '.') }}</div>
        </div>
        <div>
            <div style="color:#666;font-size:0.85rem;">Ingreso Neto</div>
            <div style="font-size:1.2rem;font-weight:bold;color:#0066ff;">${{ number_format($netRevenueCLP, 0, ',', '.') }}</div>
        </div>
        <div>
            <div style="color:#666;font-size:0.85rem;">Costo IA</div>
            <div style="font-size:1.2rem;font-weight:bold;color:#ffc107;">
                USD ${{ number_format($totalAiCostUSD / 100, 2) }}
                <span style="font-size:0.75rem;color:#999;">(~${{ number_format($aiCostCLP, 0, ',', '.') }} CLP)</span>
            </div>
        </div>
        <div>
            <div style="color:#666;font-size:0.85rem;">Ganancia Neta</div>
            <div style="font-size:1.2rem;font-weight:bold;color:{{ $grossProfit >= 0 ? '#28a745' : '#dc3545' }};">${{ number_format($grossProfit, 0, ',', '.') }} CLP</div>
        </div>
        <div>
            <div style="color:#666;font-size:0.85rem;">Costo por Venta</div>
            <div style="font-size:1.2rem;font-weight:bold;color:#6c757d;">
                @if(($revenueStats['total_sales'] ?? 0) > 0)
                    USD ${{ number_format(($totalAiCostUSD / 100) / $revenueStats['total_sales'], 2) }}
                @else
                    -
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Conversion Funnel --}}
<div class="card" style="margin-bottom:20px;">
    <h3 style="margin-bottom:12px;">Embudo de Conversion</h3>
    <div style="display:flex;flex-wrap:wrap;gap:4px;align-items:center;">
        @php
            $funnelSteps = [
                ['label' => 'Uploads', 'value' => $funnel['uploads'], 'color' => '#6c757d'],
                ['label' => 'Con Target', 'value' => $funnel['withTarget'], 'color' => '#17a2b8'],
                ['label' => 'Int. Pago', 'value' => $funnel['paymentAttempts'], 'color' => '#ffc107'],
                ['label' => 'Pagados', 'value' => $funnel['paid'], 'color' => '#28a745'],
                ['label' => 'Entregados', 'value' => $funnel['delivered'], 'color' => '#0066ff'],
                ['label' => 'Descargados', 'value' => $funnel['downloads'], 'color' => '#6f42c1'],
            ];
        @endphp
        @foreach($funnelSteps as $i => $step)
            <div style="flex:1;min-width:80px;text-align:center;">
                <div style="background:{{ $step['color'] }};color:white;padding:10px 4px;border-radius:6px;margin-bottom:4px;">
                    <div style="font-size:1.3rem;font-weight:bold;">{{ $step['value'] }}</div>
                    <div style="font-size:0.7rem;">{{ $step['label'] }}</div>
                </div>
                @if($i > 0 && $funnelSteps[$i-1]['value'] > 0)
                    <div style="font-size:0.7rem;color:#999;">{{ number_format(($step['value'] / $funnelSteps[$i-1]['value']) * 100, 1) }}%</div>
                @endif
            </div>
            @if($i < count($funnelSteps) - 1)
                <div style="color:#ccc;">&#8594;</div>
            @endif
        @endforeach
    </div>
    @if($funnel['uploads'] > 0)
        <div style="margin-top:8px;padding-top:8px;border-top:1px solid #eee;text-align:center;color:#666;font-size:0.85rem;">
            Conversion global (Upload -> Pago): <strong>{{ number_format(($funnel['paid'] / $funnel['uploads']) * 100, 1) }}%</strong>
        </div>
    @endif
</div>

{{-- AI Usage KPIs --}}
<div class="card" style="margin-bottom:20px;">
    <h3 style="margin-bottom:12px;">Uso de IA ({{ $periodDays }} dias)</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:12px;">
        <div style="text-align:center;">
            <div style="font-size:1.3rem;font-weight:bold;">{{ $aiCosts['total_calls'] ?? 0 }}</div>
            <div style="color:#666;font-size:0.8rem;">Llamadas API</div>
        </div>
        <div style="text-align:center;">
            <div style="font-size:1.3rem;font-weight:bold;color:#28a745;">{{ $aiCosts['successful_calls'] ?? 0 }}</div>
            <div style="color:#666;font-size:0.8rem;">Exitosas</div>
        </div>
        <div style="text-align:center;">
            <div style="font-size:1.3rem;font-weight:bold;color:#dc3545;">{{ $aiCosts['failed_calls'] ?? 0 }}</div>
            <div style="color:#666;font-size:0.8rem;">Fallidas</div>
        </div>
        <div style="text-align:center;">
            <div style="font-size:1.3rem;font-weight:bold;">{{ number_format($aiCosts['total_tokens'] ?? 0) }}</div>
            <div style="color:#666;font-size:0.8rem;">Tokens</div>
        </div>
        <div style="text-align:center;">
            <div style="font-size:1.3rem;font-weight:bold;">{{ number_format(($aiCosts['avg_response_ms'] ?? 0) / 1000, 1) }}s</div>
            <div style="color:#666;font-size:0.8rem;">Resp. Prom.</div>
        </div>
        <div style="text-align:center;">
            <div style="font-size:1.3rem;font-weight:bold;color:#dc3545;">USD ${{ number_format(($aiCosts['total_cost_cents'] ?? 0) / 100, 2) }}</div>
            <div style="color:#666;font-size:0.8rem;">Costo Total</div>
        </div>
    </div>
</div>

{{-- Daily Tables --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px;">
    <div class="card" style="padding:16px;">
        <h3 style="margin-bottom:8px;font-size:1rem;">Ingresos Diarios</h3>
        <div style="overflow-x:auto;max-height:300px;overflow-y:auto;">
            <table>
                <thead><tr><th>Fecha</th><th>Ventas</th><th>Ingresos</th></tr></thead>
                <tbody>
                    @forelse($dailyRevenue->reverse() as $day)
                    <tr>
                        <td>{{ $day->date }}</td>
                        <td>{{ $day->sales }}</td>
                        <td>${{ number_format($day->revenue, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" style="text-align:center;color:#999;">Sin datos</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card" style="padding:16px;">
        <h3 style="margin-bottom:8px;font-size:1rem;">Costos IA Diarios</h3>
        <div style="overflow-x:auto;max-height:300px;overflow-y:auto;">
            <table>
                <thead><tr><th>Fecha</th><th>Llamadas</th><th>Tokens</th><th>USD</th></tr></thead>
                <tbody>
                    @forelse($dailyAiCost->reverse() as $day)
                    <tr>
                        <td>{{ $day->date }}</td>
                        <td>{{ $day->calls }}</td>
                        <td>{{ number_format($day->tokens) }}</td>
                        <td>${{ number_format($day->cost_cents / 100, 3) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" style="text-align:center;color:#999;">Sin datos</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
