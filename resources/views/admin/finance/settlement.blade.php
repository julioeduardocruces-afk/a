@extends('layouts.admin')
@section('title', 'Admin - Balance y Liquidacion')
@section('admin-content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <h1>Balance y Liquidacion</h1>
    <form method="GET" style="display:flex;gap:8px;align-items:center;">
        <label>Meses:</label>
        <select name="months" onchange="this.form.submit()" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;">
            <option value="3" {{ $months == 3 ? 'selected' : '' }}>3 meses</option>
            <option value="6" {{ $months == 6 ? 'selected' : '' }}>6 meses</option>
            <option value="12" {{ $months == 12 ? 'selected' : '' }}>12 meses</option>
            <option value="24" {{ $months == 24 ? 'selected' : '' }}>24 meses</option>
        </select>
    </form>
</div>

{{-- All-time Totals --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px;">
    <div class="card" style="text-align:center;border-left:4px solid #28a745;">
        <div style="font-size:1.6rem;font-weight:bold;color:#28a745;">${{ number_format($allTimeRevenue / 100, 0, ',', '.') }}</div>
        <div style="color:#666;">Ingresos Totales (CLP)</div>
    </div>
    <div class="card" style="text-align:center;border-left:4px solid #dc3545;">
        <div style="font-size:1.6rem;font-weight:bold;color:#dc3545;">-${{ number_format($allTimeRefunds / 100, 0, ',', '.') }}</div>
        <div style="color:#666;">Reembolsos Totales</div>
    </div>
    <div class="card" style="text-align:center;border-left:4px solid #0066ff;">
        <div style="font-size:1.6rem;font-weight:bold;color:#0066ff;">${{ number_format($allTimeNet / 100, 0, ',', '.') }}</div>
        <div style="color:#666;">Ingreso Neto</div>
    </div>
    <div class="card" style="text-align:center;border-left:4px solid #ffc107;">
        <div style="font-size:1.6rem;font-weight:bold;color:#ffc107;">USD ${{ number_format($allTimeAiCostCents / 100, 2) }}</div>
        <div style="color:#666;">Costo IA Total</div>
    </div>
    <div class="card" style="text-align:center;border-left:4px solid {{ $allTimeProfit >= 0 ? '#28a745' : '#dc3545' }};">
        <div style="font-size:1.6rem;font-weight:bold;color:{{ $allTimeProfit >= 0 ? '#28a745' : '#dc3545' }};">${{ number_format($allTimeProfit / 100, 0, ',', '.') }}</div>
        <div style="color:#666;">Ganancia Neta (CLP)</div>
    </div>
</div>

{{-- Monthly Breakdown Table --}}
<div class="card">
    <h3 style="margin-bottom:12px;">Desglose Mensual</h3>
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>Mes</th>
                    <th>Ventas</th>
                    <th>Ingresos (CLP)</th>
                    <th>Reembolsos</th>
                    <th>Monto Reemb. (CLP)</th>
                    <th>Neto (CLP)</th>
                    <th>Costo IA (USD)</th>
                    <th>Costo IA (CLP)</th>
                    <th>Ganancia (CLP)</th>
                    <th>Margen</th>
                </tr>
            </thead>
            <tbody>
                @foreach($monthly as $m)
                <tr>
                    <td><strong>{{ $m->label }}</strong></td>
                    <td>{{ $m->sales }}</td>
                    <td style="color:#28a745;">${{ number_format($m->revenue / 100, 0, ',', '.') }}</td>
                    <td>{{ $m->refundCount }}</td>
                    <td style="color:#dc3545;">{{ $m->refunds > 0 ? '-$' . number_format($m->refunds / 100, 0, ',', '.') : '-' }}</td>
                    <td style="font-weight:bold;">${{ number_format($m->net / 100, 0, ',', '.') }}</td>
                    <td style="color:#ffc107;">${{ number_format($m->aiCostCents / 100, 2) }}</td>
                    <td style="color:#ffc107;">${{ number_format($m->aiCostCLP / 100, 0, ',', '.') }}</td>
                    <td style="font-weight:bold;color:{{ $m->profit >= 0 ? '#28a745' : '#dc3545' }};">
                        ${{ number_format($m->profit / 100, 0, ',', '.') }}
                    </td>
                    <td>
                        @if($m->net > 0)
                            {{ number_format(($m->profit / $m->net) * 100, 1) }}%
                        @else
                            -
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot style="background:#f8f9fa;font-weight:bold;">
                <tr>
                    <td>TOTAL</td>
                    <td>{{ collect($monthly)->sum('sales') }}</td>
                    <td style="color:#28a745;">${{ number_format(collect($monthly)->sum('revenue') / 100, 0, ',', '.') }}</td>
                    <td>{{ collect($monthly)->sum('refundCount') }}</td>
                    <td style="color:#dc3545;">-${{ number_format(collect($monthly)->sum('refunds') / 100, 0, ',', '.') }}</td>
                    <td>${{ number_format(collect($monthly)->sum('net') / 100, 0, ',', '.') }}</td>
                    <td style="color:#ffc107;">${{ number_format(collect($monthly)->sum('aiCostCents') / 100, 2) }}</td>
                    <td style="color:#ffc107;">${{ number_format(collect($monthly)->sum('aiCostCLP') / 100, 0, ',', '.') }}</td>
                    <td style="color:{{ collect($monthly)->sum('profit') >= 0 ? '#28a745' : '#dc3545' }};">${{ number_format(collect($monthly)->sum('profit') / 100, 0, ',', '.') }}</td>
                    <td>
                        @php $totalNet = collect($monthly)->sum('net'); @endphp
                        @if($totalNet > 0)
                            {{ number_format((collect($monthly)->sum('profit') / $totalNet) * 100, 1) }}%
                        @else
                            -
                        @endif
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    <p style="margin-top:12px;color:#999;font-size:0.85rem;">
        * Tipo de cambio USD/CLP: ${{ number_format($usdToClp, 0) }} (configurable via ATS_USD_TO_CLP).
        Los costos IA son estimaciones basadas en precios de lista de los proveedores.
    </p>
</div>
@endsection
