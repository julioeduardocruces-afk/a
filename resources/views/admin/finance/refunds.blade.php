@extends('layouts.admin')
@section('title', 'Admin - Gestion de Reembolsos')
@section('admin-content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <h1>Gestion de Reembolsos</h1>
    <a href="{{ route('admin.finance.dashboard') }}" class="btn btn-secondary btn-sm">&larr; Panel Financiero</a>
</div>

{{-- KPIs --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;">
    <div class="card" style="text-align:center;border-left:4px solid #dc3545;">
        <div style="font-size:1.8rem;font-weight:bold;color:#dc3545;">{{ $totalRefunds }}</div>
        <div style="color:#666;">Total Reembolsos</div>
    </div>
    <div class="card" style="text-align:center;border-left:4px solid #ffc107;">
        <div style="font-size:1.8rem;font-weight:bold;color:#ffc107;">${{ number_format($totalRefundAmount, 0, ',', '.') }}</div>
        <div style="color:#666;">Monto Reembolsado (CLP)</div>
    </div>
    <div class="card" style="text-align:center;border-left:4px solid #6c757d;">
        <div style="font-size:1.8rem;font-weight:bold;color:#6c757d;">{{ number_format($refundRate, 1) }}%</div>
        <div style="color:#666;">Tasa de Reembolso</div>
    </div>
</div>

{{-- Process Refund Section --}}
<div class="card" style="margin-bottom:24px;">
    <h3 style="margin-bottom:12px;">Procesar Reembolso</h3>
    @if($refundable->isEmpty())
        <p style="color:#999;">No hay pagos elegibles para reembolso.</p>
    @else
        <div style="overflow-x:auto;max-height:400px;overflow-y:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Pago ID</th>
                        <th>Fecha</th>
                        <th>Email</th>
                        <th>CV ID</th>
                        <th>Monto (CLP)</th>
                        <th>Accion</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($refundable as $p)
                    <tr>
                        <td>{{ $p->id }}</td>
                        <td>{{ $p->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ $p->resume?->customer_email ?? $p->user?->email ?? 'N/A' }}</td>
                        <td>{{ $p->resume_id }}</td>
                        <td style="font-weight:bold;">${{ number_format($p->amount, 0, ',', '.') }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.finance.process-refund', $p) }}" style="display:flex;gap:4px;align-items:center;" onsubmit="return confirm('Confirmar reembolso del pago #{{ $p->id }} por ${{ number_format($p->amount, 0, ',', '.') }} CLP?')">
                                @csrf
                                <input type="text" name="reason" placeholder="Razon del reembolso" required style="padding:4px 8px;border:1px solid #ddd;border-radius:4px;width:200px;font-size:0.85rem;">
                                <button type="submit" class="btn btn-danger btn-sm">Reembolsar</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- Refund History --}}
<div class="card">
    <h3 style="margin-bottom:12px;">Historial de Reembolsos</h3>

    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;margin-bottom:16px;">
        <div class="form-group" style="margin-bottom:0;">
            <label>Desde</label>
            <input type="date" name="from" value="{{ request('from') }}" style="padding:8px;border:1px solid #ddd;border-radius:6px;">
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label>Hasta</label>
            <input type="date" name="to" value="{{ request('to') }}" style="padding:8px;border:1px solid #ddd;border-radius:6px;">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        <a href="{{ route('admin.finance.refunds') }}" class="btn btn-secondary btn-sm">Limpiar</a>
    </form>

    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>Pago ID</th>
                    <th>Fecha Pago</th>
                    <th>Fecha Reembolso</th>
                    <th>Email</th>
                    <th>CV ID</th>
                    <th>Monto Original</th>
                    <th>Monto Reemb.</th>
                    <th>Razon</th>
                </tr>
            </thead>
            <tbody>
                @forelse($refunds as $r)
                <tr>
                    <td>{{ $r->id }}</td>
                    <td>{{ $r->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $r->refunded_at?->format('d/m/Y H:i') ?? '-' }}</td>
                    <td>{{ $r->resume?->customer_email ?? $r->user?->email ?? 'N/A' }}</td>
                    <td>{{ $r->resume_id }}</td>
                    <td>${{ number_format($r->amount, 0, ',', '.') }}</td>
                    <td style="color:#dc3545;font-weight:bold;">${{ number_format($r->refund_amount ?? $r->amount, 0, ',', '.') }}</td>
                    <td style="font-size:0.85rem;">{{ $r->refund_reason ?? '-' }}</td>
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center;color:#999;">No hay reembolsos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px;">{{ $refunds->links() }}</div>
</div>
@endsection
