@extends('layouts.admin')
@section('title', 'Admin Dashboard')
@section('admin-content')
<h1 style="margin-bottom:24px;">Panel de Administracion</h1>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;">
    <div class="card" style="text-align:center;">
        <div style="font-size:2rem;font-weight:bold;color:#0066ff;">{{ $totals['uploads'] }}</div>
        <div>Uploads (30 dias)</div>
    </div>
    <div class="card" style="text-align:center;">
        <div style="font-size:2rem;font-weight:bold;color:#17a2b8;">{{ $totals['previews'] }}</div>
        <div>Previews</div>
    </div>
    <div class="card" style="text-align:center;">
        <div style="font-size:2rem;font-weight:bold;color:#28a745;">{{ $totals['paid'] }}</div>
        <div>Pagados</div>
    </div>
    <div class="card" style="text-align:center;">
        <div style="font-size:2rem;font-weight:bold;color:#28a745;">${{ number_format($totals['revenue'] / 100, 0, ',', '.') }}</div>
        <div>Revenue (CLP)</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">
    <div class="card">
        <h3 style="margin-bottom:12px;">Estados de CVs</h3>
        <table>
            @foreach($statusCounts as $status => $count)
            <tr>
                <td><span class="badge badge-{{ $status }}">{{ $status }}</span></td>
                <td style="text-align:right;">{{ $count }}</td>
            </tr>
            @endforeach
        </table>
    </div>
    <div class="card">
        <h3 style="margin-bottom:12px;">Accesos Rapidos</h3>
        <ul style="list-style:none;padding:0;">
            <li style="margin-bottom:8px;"><a href="{{ route('admin.finance.dashboard') }}" style="font-weight:bold;color:#28a745;">Panel Financiero (Ventas, Costos, Rentabilidad)</a></li>
            <li style="margin-bottom:8px;"><a href="{{ route('admin.finance.sales') }}">Historial de Ventas</a></li>
            <li style="margin-bottom:8px;"><a href="{{ route('admin.finance.downloads') }}">Historial de Descargas</a></li>
            <li style="margin-bottom:8px;"><a href="{{ route('admin.finance.ai-usage') }}">Uso de Tokens IA</a></li>
            <li style="margin-bottom:8px;"><a href="{{ route('admin.credentials') }}">Gestionar Credenciales (IA / Flow / SMTP)</a></li>
            <li style="margin-bottom:8px;"><a href="{{ route('admin.resumes') }}">Gestionar CVs</a></li>
            <li style="margin-bottom:8px;"><a href="{{ route('admin.metrics') }}">Metricas Operativas</a></li>
            <li style="margin-bottom:8px;"><a href="{{ route('admin.audit-logs') }}">Audit Logs</a></li>
        </ul>
    </div>
</div>

<div class="card">
    <h3 style="margin-bottom:12px;">CVs Recientes</h3>
    <table>
        <thead>
            <tr><th>ID</th><th>Usuario</th><th>Rubro</th><th>Estado</th><th>Fecha</th></tr>
        </thead>
        <tbody>
            @foreach($recentResumes as $r)
            <tr>
                <td>{{ $r->id }}</td>
                <td>{{ $r->user->email ?? 'N/A' }}</td>
                <td>{{ $r->target_industry ?? '-' }}</td>
                <td><span class="badge badge-{{ $r->status->value }}">{{ $r->status->value }}</span></td>
                <td>{{ $r->created_at->format('d/m H:i') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
