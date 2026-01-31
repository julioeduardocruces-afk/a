@extends('layouts.app')
@section('title', 'Admin - Panel Financiero')
@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <h1>Panel Financiero</h1>
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
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px;">
    <div class="card" style="text-align:center;border-left:4px solid #28a745;">
        <div style="font-size:2rem;font-weight:bold;color:#28a745;">${{ number_format(($revenueStats['total_revenue'] ?? 0) / 100, 0, ',', '.') }}</div>
        <div style="color:#666;">Ingresos (CLP)</div>
    </div>
    <div class="card" style="text-align:center;border-left:4px solid #0066ff;">
        <div style="font-size:2rem;font-weight:bold;color:#0066ff;">{{ $revenueStats['total_sales'] ?? 0 }}</div>
        <div style="color:#666;">Ventas</div>
    </div>
    <div class="card" style="text-align:center;border-left:4px solid #17a2b8;">
        <div style="font-size:2rem;font-weight:bold;color:#17a2b8;">${{ number_format(($revenueStats['avg_ticket'] ?? 0) / 100, 0, ',', '.') }}</div>
        <div style="color:#666;">Ticket Promedio</div>
    </div>
    <div class="card" style="text-align:center;border-left:4px solid #ffc107;">
        <div style="font-size:2rem;font-weight:bold;color:#{{ $margin >= 50 ? '28a745' : ($margin >= 20 ? 'ffc107' : 'dc3545') }};">{{ number_format($margin, 1) }}%</div>
        <div style="color:#666;">Margen Bruto</div>
    </div>
</div>

{{-- Profit Analysis --}}
<div class="card" style="margin-bottom:24px;">
    <h3 style="margin-bottom:16px;">Analisis de Rentabilidad ({{ $periodDays }} dias)</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">
        <div>
            <div style="color:#666;font-size:0.9rem;">Ingresos Brutos</div>
            <div style="font-size:1.4rem;font-weight:bold;color:#28a745;">${{ number_format($totalRevenueCLP / 100, 0, ',', '.') }} CLP</div>
        </div>
        <div>
            <div style="color:#666;font-size:0.9rem;">Costo IA (estimado)</div>
            <div style="font-size:1.4rem;font-weight:bold;color:#dc3545;">
                USD ${{ number_format($totalAiCostUSD / 100, 2) }}
                <span style="font-size:0.8rem;color:#999;">(~${{ number_format($aiCostCLP / 100, 0, ',', '.') }} CLP @ ${{ number_format($usdToClp, 0) }})</span>
            </div>
        </div>
        <div>
            <div style="color:#666;font-size:0.9rem;">Ganancia Bruta</div>
            <div style="font-size:1.4rem;font-weight:bold;color:{{ $grossProfit >= 0 ? '#28a745' : '#dc3545' }};">${{ number_format($grossProfit / 100, 0, ',', '.') }} CLP</div>
        </div>
        <div>
            <div style="color:#666;font-size:0.9rem;">Costo por Venta</div>
            <div style="font-size:1.4rem;font-weight:bold;color:#6c757d;">
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
<div class="card" style="margin-bottom:24px;">
    <h3 style="margin-bottom:16px;">Embudo de Conversion</h3>
    <div style="display:flex;flex-wrap:wrap;gap:4px;align-items:center;">
        @php
            $funnelSteps = [
                ['label' => 'Uploads', 'value' => $funnel['uploads'], 'color' => '#6c757d'],
                ['label' => 'Con Target', 'value' => $funnel['withTarget'], 'color' => '#17a2b8'],
                ['label' => 'Intent. Pago', 'value' => $funnel['paymentAttempts'], 'color' => '#ffc107'],
                ['label' => 'Pagados', 'value' => $funnel['paid'], 'color' => '#28a745'],
                ['label' => 'Entregados', 'value' => $funnel['delivered'], 'color' => '#0066ff'],
                ['label' => 'Descargados', 'value' => $funnel['downloads'], 'color' => '#6f42c1'],
            ];
            $maxVal = max(1, $funnel['uploads']);
        @endphp
        @foreach($funnelSteps as $i => $step)
            <div style="flex:1;min-width:120px;text-align:center;">
                <div style="background:{{ $step['color'] }};color:white;padding:12px 8px;border-radius:6px;margin-bottom:4px;">
                    <div style="font-size:1.5rem;font-weight:bold;">{{ $step['value'] }}</div>
                    <div style="font-size:0.8rem;">{{ $step['label'] }}</div>
                </div>
                @if($i > 0 && $funnelSteps[$i-1]['value'] > 0)
                    <div style="font-size:0.75rem;color:#999;">{{ number_format(($step['value'] / $funnelSteps[$i-1]['value']) * 100, 1) }}%</div>
                @endif
            </div>
            @if($i < count($funnelSteps) - 1)
                <div style="font-size:1.2rem;color:#ccc;">&#8594;</div>
            @endif
        @endforeach
    </div>
    @if($funnel['uploads'] > 0)
        <div style="margin-top:12px;padding-top:12px;border-top:1px solid #eee;text-align:center;color:#666;">
            Tasa de conversion global (Upload a Pago): <strong>{{ number_format(($funnel['paid'] / $funnel['uploads']) * 100, 1) }}%</strong>
        </div>
    @endif
</div>

{{-- AI Usage KPIs --}}
<div class="card" style="margin-bottom:24px;">
    <h3 style="margin-bottom:16px;">Uso de IA ({{ $periodDays }} dias)</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;">
        <div style="text-align:center;">
            <div style="font-size:1.5rem;font-weight:bold;">{{ $aiCosts['total_calls'] ?? 0 }}</div>
            <div style="color:#666;">Llamadas API</div>
        </div>
        <div style="text-align:center;">
            <div style="font-size:1.5rem;font-weight:bold;color:#28a745;">{{ $aiCosts['successful_calls'] ?? 0 }}</div>
            <div style="color:#666;">Exitosas</div>
        </div>
        <div style="text-align:center;">
            <div style="font-size:1.5rem;font-weight:bold;color:#dc3545;">{{ $aiCosts['failed_calls'] ?? 0 }}</div>
            <div style="color:#666;">Fallidas</div>
        </div>
        <div style="text-align:center;">
            <div style="font-size:1.5rem;font-weight:bold;">{{ number_format($aiCosts['total_tokens'] ?? 0) }}</div>
            <div style="color:#666;">Tokens Totales</div>
        </div>
        <div style="text-align:center;">
            <div style="font-size:1.5rem;font-weight:bold;">{{ number_format(($aiCosts['avg_response_ms'] ?? 0) / 1000, 1) }}s</div>
            <div style="color:#666;">Resp. Promedio</div>
        </div>
        <div style="text-align:center;">
            <div style="font-size:1.5rem;font-weight:bold;color:#dc3545;">USD ${{ number_format(($aiCosts['total_cost_cents'] ?? 0) / 100, 2) }}</div>
            <div style="color:#666;">Costo Total</div>
        </div>
    </div>
</div>

{{-- Daily Revenue Table --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">
    <div class="card">
        <h3 style="margin-bottom:12px;">Ingresos Diarios</h3>
        <div style="overflow-x:auto;max-height:400px;overflow-y:auto;">
            <table>
                <thead><tr><th>Fecha</th><th>Ventas</th><th>Ingresos (CLP)</th></tr></thead>
                <tbody>
                    @forelse($dailyRevenue->reverse() as $day)
                    <tr>
                        <td>{{ $day->date }}</td>
                        <td>{{ $day->sales }}</td>
                        <td>${{ number_format($day->revenue / 100, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" style="text-align:center;color:#999;">Sin datos</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <h3 style="margin-bottom:12px;">Costos IA Diarios</h3>
        <div style="overflow-x:auto;max-height:400px;overflow-y:auto;">
            <table>
                <thead><tr><th>Fecha</th><th>Llamadas</th><th>Tokens</th><th>Costo (USD)</th></tr></thead>
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

{{-- Quick Links --}}
<div class="card">
    <h3 style="margin-bottom:12px;">Reportes Detallados</h3>
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <a href="{{ route('admin.finance.sales') }}" class="btn btn-primary btn-sm">Historial de Ventas</a>
        <a href="{{ route('admin.finance.downloads') }}" class="btn btn-secondary btn-sm">Historial de Descargas</a>
        <a href="{{ route('admin.finance.ai-usage') }}" class="btn btn-secondary btn-sm">Uso de Tokens IA</a>
        <a href="{{ route('admin.metrics') }}" class="btn btn-secondary btn-sm">Metricas Operativas</a>
        <a href="{{ route('admin.audit-logs') }}" class="btn btn-secondary btn-sm">Audit Logs</a>
    </div>
</div>
@endsection
