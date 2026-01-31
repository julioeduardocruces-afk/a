@extends('layouts.admin')
@section('title', 'Admin - Historial de Ventas')
@section('admin-content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <h1>Historial de Ventas</h1>
    <a href="{{ route('admin.finance.export-sales', request()->query()) }}" class="btn btn-success btn-sm">Exportar CSV</a>
</div>

{{-- Summary --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:24px;">
    <div class="card" style="text-align:center;border-left:4px solid #28a745;">
        <div style="font-size:1.6rem;font-weight:bold;color:#28a745;">{{ $summary->total_paid }}</div>
        <div style="color:#666;">Pagados</div>
    </div>
    <div class="card" style="text-align:center;border-left:4px solid #0066ff;">
        <div style="font-size:1.6rem;font-weight:bold;color:#0066ff;">${{ number_format($summary->total_revenue, 0, ',', '.') }}</div>
        <div style="color:#666;">Ingresos Brutos</div>
    </div>
    <div class="card" style="text-align:center;border-left:4px solid #dc3545;">
        <div style="font-size:1.6rem;font-weight:bold;color:#dc3545;">{{ $summary->total_refunded }}</div>
        <div style="color:#666;">Reembolsados</div>
        <div style="font-size:0.8rem;color:#999;">${{ number_format($summary->total_refund_amount, 0, ',', '.') }}</div>
    </div>
    <div class="card" style="text-align:center;border-left:4px solid #17a2b8;">
        <div style="font-size:1.6rem;font-weight:bold;color:#17a2b8;">${{ number_format($summary->net_revenue, 0, ',', '.') }}</div>
        <div style="color:#666;">Ingreso Neto</div>
    </div>
    <div class="card" style="text-align:center;border-left:4px solid #ffc107;">
        <div style="font-size:1.6rem;font-weight:bold;color:#ffc107;">{{ $summary->total_failed }}</div>
        <div style="color:#666;">Fallidos</div>
    </div>
    <div class="card" style="text-align:center;border-left:4px solid #6c757d;">
        <div style="font-size:1.6rem;font-weight:bold;color:#6c757d;">{{ $summary->total_pending }}</div>
        <div style="color:#666;">Pendientes</div>
    </div>
</div>

{{-- Filters --}}
<div class="card" style="margin-bottom:20px;">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;">
        <div class="form-group" style="margin-bottom:0;">
            <label>Estado</label>
            <select name="status" style="padding:8px;border:1px solid #ddd;border-radius:6px;">
                <option value="all">Todos</option>
                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pendiente</option>
                <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Pagado</option>
                <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Fallido</option>
                <option value="refunded" {{ request('status') === 'refunded' ? 'selected' : '' }}>Reembolsado</option>
            </select>
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label>Desde</label>
            <input type="date" name="from" value="{{ request('from') }}" style="padding:8px;border:1px solid #ddd;border-radius:6px;">
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label>Hasta</label>
            <input type="date" name="to" value="{{ request('to') }}" style="padding:8px;border:1px solid #ddd;border-radius:6px;">
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label>Email</label>
            <input type="text" name="email" value="{{ request('email') }}" placeholder="Buscar por email" style="padding:8px;border:1px solid #ddd;border-radius:6px;">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        <a href="{{ route('admin.finance.sales') }}" class="btn btn-secondary btn-sm">Limpiar</a>
    </form>
</div>

{{-- Table --}}
<div class="card">
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Email Cliente</th>
                    <th>CV ID</th>
                    <th>Rubro</th>
                    <th>Monto</th>
                    <th>Estado</th>
                    <th>Detalle</th>
                    <th>Flow Order</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $p)
                <tr>
                    <td>{{ $p->id }}</td>
                    <td>{{ $p->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $p->resume?->customer_email ?? $p->user?->email ?? 'N/A' }}</td>
                    <td>{{ $p->resume_id }}</td>
                    <td>{{ $p->resume?->target_industry ?? '-' }}</td>
                    <td style="font-weight:bold;">${{ number_format($p->amount, 0, ',', '.') }}</td>
                    <td>
                        @php
                            $statusColors = ['pending' => '#ffc107', 'paid' => '#28a745', 'failed' => '#dc3545', 'refunded' => '#6c757d'];
                            $statusVal = $p->status->value ?? $p->status;
                        @endphp
                        <span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:0.8rem;font-weight:600;background:{{ $statusColors[$statusVal] ?? '#999' }}20;color:{{ $statusColors[$statusVal] ?? '#999' }};">{{ $statusVal }}</span>
                    </td>
                    <td style="font-size:0.8rem;color:#999;">
                        @if($p->failure_reason)
                            <span style="color:#dc3545;" title="{{ $p->failure_reason }}">Fallo: {{ Str::limit($p->failure_reason, 30) }}</span>
                        @elseif($p->refund_reason)
                            <span style="color:#6c757d;" title="{{ $p->refund_reason }}">Reemb: {{ Str::limit($p->refund_reason, 30) }}</span>
                            @if($p->refunded_at)
                                <br>{{ $p->refunded_at->format('d/m H:i') }}
                            @endif
                        @else
                            -
                        @endif
                    </td>
                    <td style="font-size:0.85rem;color:#666;">{{ $p->flow_order ?? '-' }}</td>
                </tr>
                @empty
                <tr><td colspan="9" style="text-align:center;color:#999;">No hay pagos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px;">{{ $payments->links() }}</div>
</div>
@endsection
