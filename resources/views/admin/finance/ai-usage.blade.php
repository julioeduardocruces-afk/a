@extends('layouts.admin')
@section('title', 'Admin - Uso de Tokens IA')
@section('admin-content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <h1>Uso de Tokens IA</h1>
    <a href="{{ route('admin.finance.export-ai-usage', request()->query()) }}" class="btn btn-success btn-sm">Exportar CSV</a>
</div>

{{-- Global KPIs --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px;">
    <div class="card" style="text-align:center;border-left:4px solid #0066ff;">
        <div style="font-size:1.8rem;font-weight:bold;color:#0066ff;">{{ number_format($totalTokens) }}</div>
        <div style="color:#666;">Tokens Totales</div>
    </div>
    <div class="card" style="text-align:center;border-left:4px solid #dc3545;">
        <div style="font-size:1.8rem;font-weight:bold;color:#dc3545;">USD ${{ number_format($totalCostCents / 100, 2) }}</div>
        <div style="color:#666;">Costo Total Estimado</div>
    </div>
</div>

{{-- By Provider/Model Summary --}}
<div class="card" style="margin-bottom:24px;">
    <h3 style="margin-bottom:12px;">Resumen por Proveedor / Modelo</h3>
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>Proveedor</th>
                    <th>Modelo</th>
                    <th>Llamadas</th>
                    <th>Exitosas</th>
                    <th>Fallidas</th>
                    <th>Tokens Prompt</th>
                    <th>Tokens Resp.</th>
                    <th>Tokens Total</th>
                    <th>Costo (USD)</th>
                    <th>Resp. Prom.</th>
                </tr>
            </thead>
            <tbody>
                @forelse($byProvider as $row)
                <tr>
                    <td><strong>{{ $row->provider }}</strong></td>
                    <td>{{ $row->model }}</td>
                    <td>{{ $row->calls }}</td>
                    <td style="color:#28a745;">{{ $row->ok }}</td>
                    <td style="color:#dc3545;">{{ $row->failed }}</td>
                    <td>{{ number_format($row->prompt_tokens) }}</td>
                    <td>{{ number_format($row->completion_tokens) }}</td>
                    <td><strong>{{ number_format($row->total_tokens) }}</strong></td>
                    <td style="font-weight:bold;">${{ number_format($row->cost_cents / 100, 3) }}</td>
                    <td>{{ number_format($row->avg_ms / 1000, 1) }}s</td>
                </tr>
                @empty
                <tr><td colspan="10" style="text-align:center;color:#999;">Sin datos de uso.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Filters --}}
<div class="card" style="margin-bottom:20px;">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;">
        <div class="form-group" style="margin-bottom:0;">
            <label>Proveedor</label>
            <select name="provider" style="padding:8px;border:1px solid #ddd;border-radius:6px;">
                <option value="all">Todos</option>
                <option value="openai" {{ request('provider') === 'openai' ? 'selected' : '' }}>OpenAI</option>
                <option value="gemini" {{ request('provider') === 'gemini' ? 'selected' : '' }}>Gemini</option>
            </select>
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label>Estado</label>
            <select name="success" style="padding:8px;border:1px solid #ddd;border-radius:6px;">
                <option value="all">Todos</option>
                <option value="1" {{ request('success') === '1' ? 'selected' : '' }}>Exitoso</option>
                <option value="0" {{ request('success') === '0' ? 'selected' : '' }}>Fallido</option>
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
        <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        <a href="{{ route('admin.finance.ai-usage') }}" class="btn btn-secondary btn-sm">Limpiar</a>
    </form>
</div>

{{-- Detailed Log Table --}}
<div class="card">
    <h3 style="margin-bottom:12px;">Historial Detallado</h3>
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>CV ID</th>
                    <th>Proveedor</th>
                    <th>Modelo</th>
                    <th>Prompt Tk</th>
                    <th>Resp. Tk</th>
                    <th>Total Tk</th>
                    <th>Costo (USD)</th>
                    <th>Tiempo</th>
                    <th>Estado</th>
                    <th>Error</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td>{{ $log->id }}</td>
                    <td>{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                    <td>{{ $log->resume_id ?? '-' }}</td>
                    <td>{{ $log->provider }}</td>
                    <td style="font-size:0.85rem;">{{ $log->model }}</td>
                    <td>{{ number_format($log->prompt_tokens) }}</td>
                    <td>{{ number_format($log->completion_tokens) }}</td>
                    <td><strong>{{ number_format($log->total_tokens) }}</strong></td>
                    <td>${{ number_format($log->cost_usd_cents / 100, 4) }}</td>
                    <td>{{ number_format($log->response_time_ms / 1000, 1) }}s</td>
                    <td>
                        @if($log->success)
                            <span style="color:#28a745;font-weight:600;">OK</span>
                        @else
                            <span style="color:#dc3545;font-weight:600;">FAIL</span>
                        @endif
                    </td>
                    <td style="font-size:0.8rem;color:#999;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $log->error_message }}">{{ $log->error_message ?? '-' }}</td>
                </tr>
                @empty
                <tr><td colspan="12" style="text-align:center;color:#999;">No hay registros de uso.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px;">{{ $logs->links() }}</div>
</div>
@endsection
