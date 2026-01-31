@extends('layouts.admin')
@section('title', 'Admin - Historial de Descargas')
@section('admin-content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <h1>Historial de Descargas</h1>
    <a href="{{ route('admin.finance.export-downloads', request()->query()) }}" class="btn btn-success btn-sm">Exportar CSV</a>
</div>

{{-- Summary KPIs --}}
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:12px;margin-bottom:20px;">
    <div class="card" style="text-align:center;border-left:4px solid #0066ff;padding:16px;">
        <div style="font-size:1.4rem;font-weight:bold;color:#0066ff;">{{ $totalDownloads }}</div>
        <div style="color:#666;font-size:0.85rem;">Total Descargas</div>
    </div>
    <div class="card" style="text-align:center;border-left:4px solid #dc3545;padding:16px;">
        <div style="font-size:1.4rem;font-weight:bold;color:#dc3545;">{{ $pdfCount }}</div>
        <div style="color:#666;font-size:0.85rem;">PDF</div>
    </div>
    <div class="card" style="text-align:center;border-left:4px solid #17a2b8;padding:16px;">
        <div style="font-size:1.4rem;font-weight:bold;color:#17a2b8;">{{ $docxCount }}</div>
        <div style="color:#666;font-size:0.85rem;">DOCX</div>
    </div>
    <div class="card" style="text-align:center;border-left:4px solid #28a745;padding:16px;">
        <div style="font-size:1.4rem;font-weight:bold;color:#28a745;">{{ $tokensGenerated }}</div>
        <div style="color:#666;font-size:0.85rem;">Tokens Generados</div>
    </div>
    <div class="card" style="text-align:center;border-left:4px solid #ffc107;padding:16px;">
        <div style="font-size:1.4rem;font-weight:bold;color:#ffc107;">{{ $tokensUsed }}</div>
        <div style="color:#666;font-size:0.85rem;">Tokens Usados</div>
    </div>
    <div class="card" style="text-align:center;border-left:4px solid #6c757d;padding:16px;">
        <div style="font-size:1.4rem;font-weight:bold;color:#6c757d;">{{ $tokensExpired }}</div>
        <div style="color:#666;font-size:0.85rem;">Tokens Expirados</div>
    </div>
</div>

{{-- Filters --}}
<div class="card" style="margin-bottom:20px;">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;">
        <div class="form-group" style="margin-bottom:0;">
            <label>Desde</label>
            <input type="date" name="from" value="{{ request('from') }}" style="padding:8px;border:1px solid #ddd;border-radius:6px;">
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label>Hasta</label>
            <input type="date" name="to" value="{{ request('to') }}" style="padding:8px;border:1px solid #ddd;border-radius:6px;">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        <a href="{{ route('admin.finance.downloads') }}" class="btn btn-secondary btn-sm">Limpiar</a>
    </form>
</div>

{{-- Table --}}
<div class="card">
    <h3 style="margin-bottom:12px;">Registro de Descargas</h3>
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Actor</th>
                    <th>CV ID</th>
                    <th>Token ID</th>
                    <th>Formato</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse($downloads as $d)
                @php $meta = $d->metadata_json ?? []; @endphp
                <tr>
                    <td>{{ $d->created_at->format('d/m/Y H:i:s') }}</td>
                    <td>
                        <span style="font-size:0.8rem;color:#666;">{{ $d->actor_type }}</span>
                        {{ $d->actor_id ?? '-' }}
                    </td>
                    <td>{{ $meta['resume_id'] ?? '-' }}</td>
                    <td>{{ $meta['token_id'] ?? '-' }}</td>
                    <td>
                        @php $fmt = $meta['format'] ?? 'pdf'; @endphp
                        <span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:0.8rem;font-weight:600;background:{{ $fmt === 'pdf' ? '#dc354520' : '#17a2b820' }};color:{{ $fmt === 'pdf' ? '#dc3545' : '#17a2b8' }};">{{ strtoupper($fmt) }}</span>
                    </td>
                    <td style="font-size:0.85rem;color:#666;">{{ $d->ip ?? '-' }}</td>
                </tr>
                @empty
                <tr><td colspan="6" style="text-align:center;color:#999;">No hay descargas registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px;">{{ $downloads->links() }}</div>
</div>
@endsection
