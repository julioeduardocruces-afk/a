@extends('layouts.app')
@section('title', 'Admin - CVs')
@section('content')
<h1 style="margin-bottom:16px;">Gestion de CVs</h1>

<div class="card" style="margin-bottom:16px;">
    <form method="GET" action="{{ route('admin.resumes') }}" style="display:flex;gap:12px;align-items:end;">
        <div class="form-group" style="margin-bottom:0;">
            <label>Filtrar por estado</label>
            <select name="status">
                <option value="all">Todos</option>
                @foreach(['draft','processing','preview_ready','paid','delivered','failed'] as $st)
                    <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>{{ $st }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
    </form>
</div>

<div class="card" style="overflow-x:auto;">
    <table>
        <thead>
            <tr>
                <th>ID</th><th>Usuario</th><th>Archivo</th><th>Rubro</th>
                <th>Estado</th><th>Score</th><th>Error</th><th>Fecha</th><th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($resumes as $r)
            <tr>
                <td>{{ $r->id }}</td>
                <td>{{ $r->user->email ?? 'N/A' }}</td>
                <td>{{ Str::limit($r->original_filename, 25) }}</td>
                <td>{{ Str::limit($r->target_industry ?? '-', 20) }}</td>
                <td><span class="badge badge-{{ $r->status->value }}">{{ $r->status->value }}</span></td>
                <td>{{ $r->latestVersion?->score_json['overall'] ?? '-' }}</td>
                <td style="color:#dc3545;" title="{{ $r->error_message ?? '' }}">{{ Str::limit($r->error_message ?? '', 30) }}</td>
                <td>{{ $r->created_at->format('d/m H:i') }}</td>
                <td style="display:flex;gap:4px;">
                    @if($r->status->value === 'failed')
                    <form method="POST" action="{{ route('admin.resumes.retry', $r->id) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-primary">Reintentar</button>
                    </form>
                    @endif
                    @if(in_array($r->status->value, ['paid', 'delivered']))
                    <form method="POST" action="{{ route('admin.resumes.resend-email', $r->id) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-secondary">Reenviar</button>
                    </form>
                    @endif
                    <form method="POST" action="{{ route('admin.resumes.destroy', $r->id) }}" onsubmit="return confirm('¿Eliminar este CV? Esta acción no se puede deshacer.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
{{ $resumes->appends(request()->query())->links() }}
@endsection
