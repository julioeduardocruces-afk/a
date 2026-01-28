@extends('layouts.app')
@section('title', 'Admin - Metricas')
@section('content')
<h1 style="margin-bottom:24px;">Metricas</h1>

<div class="card">
    <h3 style="margin-bottom:12px;">Metricas Diarias (ultimos 90 dias)</h3>
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr><th>Fecha</th><th>Uploads</th><th>Previews</th><th>Pagados</th><th>Revenue (CLP)</th><th>Tiempo Prom (ms)</th></tr>
            </thead>
            <tbody>
                @foreach($daily as $m)
                <tr>
                    <td>{{ $m->date->format('d/m/Y') }}</td>
                    <td>{{ $m->uploads }}</td>
                    <td>{{ $m->previews }}</td>
                    <td>{{ $m->paid }}</td>
                    <td>${{ number_format($m->revenue / 100, 0, ',', '.') }}</td>
                    <td>{{ number_format($m->avg_process_time_ms) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:20px;">
    <div class="card">
        <h3 style="margin-bottom:12px;">CVs por Rubro</h3>
        <table>
            <thead><tr><th>Rubro</th><th>Cantidad</th></tr></thead>
            <tbody>
                @foreach($byIndustry as $item)
                <tr><td>{{ $item->target_industry }}</td><td>{{ $item->count }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-bottom:12px;">Errores por Etapa</h3>
        <table>
            <thead><tr><th>Codigo Error</th><th>Cantidad</th></tr></thead>
            <tbody>
                @foreach($errorsByStage as $item)
                <tr><td>{{ $item->error_code ?? 'sin codigo' }}</td><td>{{ $item->count }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
