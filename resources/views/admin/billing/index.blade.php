@extends('layouts.admin')
@section('title', 'Admin - Facturacion')
@section('admin-content')
<h1 style="margin-bottom:16px;">Facturacion</h1>

{{-- Stats --}}
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;">
    @php
        $pendingCount = \App\Models\Resume::whereHas('payments', fn($q) => $q->where('status', 'paid'))->where('invoice_status', 'pending')->count();
        $issuedCount = \App\Models\Resume::whereHas('payments', fn($q) => $q->where('status', 'paid'))->where('invoice_status', 'issued')->count();
        $sentCount = \App\Models\Resume::whereHas('payments', fn($q) => $q->where('status', 'paid'))->where('invoice_status', 'sent')->count();
    @endphp
    <div class="card" style="text-align:center;padding:20px;">
        <div style="font-size:2rem;font-weight:bold;color:#f59e0b;">{{ $pendingCount }}</div>
        <div style="color:#666;font-size:0.9rem;">Pendientes</div>
    </div>
    <div class="card" style="text-align:center;padding:20px;">
        <div style="font-size:2rem;font-weight:bold;color:#0066ff;">{{ $issuedCount }}</div>
        <div style="color:#666;font-size:0.9rem;">Emitidas</div>
    </div>
    <div class="card" style="text-align:center;padding:20px;">
        <div style="font-size:2rem;font-weight:bold;color:#28a745;">{{ $sentCount }}</div>
        <div style="color:#666;font-size:0.9rem;">Enviadas</div>
    </div>
</div>

{{-- Filters --}}
<div class="card" style="margin-bottom:16px;">
    <form method="GET" action="{{ route('admin.billing.index') }}" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;">
        <div class="form-group" style="margin-bottom:0;">
            <label>Buscar</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Nombre, email o RUT..." style="width:220px;">
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label>Estado Factura</label>
            <select name="invoice_status">
                <option value="">Todos</option>
                <option value="pending" {{ request('invoice_status') === 'pending' ? 'selected' : '' }}>Pendiente</option>
                <option value="issued" {{ request('invoice_status') === 'issued' ? 'selected' : '' }}>Emitida</option>
                <option value="sent" {{ request('invoice_status') === 'sent' ? 'selected' : '' }}>Enviada</option>
            </select>
        </div>
        <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
        @if(request()->hasAny(['search', 'invoice_status']))
            <a href="{{ route('admin.billing.index') }}" class="btn btn-sm btn-secondary">Limpiar</a>
        @endif
    </form>
</div>

{{-- Table --}}
<div class="card" style="overflow-x:auto;">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Cliente</th>
                <th>RUT</th>
                <th>Email</th>
                <th>Monto</th>
                <th>Fecha Pago</th>
                <th>Estado Factura</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($resumes as $resume)
            @php
                $payment = $resume->payments->first();
            @endphp
            <tr>
                <td>{{ $resume->id }}</td>
                <td>
                    <strong>{{ $resume->billing_name ?: 'Sin datos' }}</strong>
                    @if($resume->billing_city)
                        <br><span style="font-size:11px;color:#888;">{{ $resume->billing_city }}</span>
                    @endif
                </td>
                <td style="font-family:monospace;">{{ $resume->getFormattedRut() ?: '-' }}</td>
                <td style="font-size:12px;">{{ $resume->customer_email ?: '-' }}</td>
                <td style="font-weight:600;">${{ number_format($payment?->amount ?? 0, 0, ',', '.') }}</td>
                <td style="font-size:12px;">{{ $payment?->paid_at?->format('d/m/Y H:i') ?? '-' }}</td>
                <td>
                    @if($resume->invoice_status === 'sent')
                        <span class="badge" style="background:#d4edda;color:#155724;">
                            Enviada
                            @if($resume->invoice_sent_at)
                                <br><small>{{ $resume->invoice_sent_at->format('d/m') }}</small>
                            @endif
                        </span>
                    @elseif($resume->invoice_status === 'issued')
                        <span class="badge" style="background:#cce5ff;color:#004085;">
                            Emitida
                            @if($resume->invoice_file)
                                <br><small>Con archivo</small>
                            @endif
                        </span>
                    @else
                        <span class="badge" style="background:#fff3cd;color:#856404;">Pendiente</span>
                    @endif
                </td>
                <td>
                    <a href="{{ route('admin.billing.show', $resume->id) }}" class="btn btn-sm btn-primary">
                        Ver / Facturar
                    </a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" style="text-align:center;padding:40px;color:#888;">
                    No hay ventas para facturar.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $resumes->appends(request()->query())->links() }}
@endsection
