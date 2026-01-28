@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <h1>Mis CVs</h1>
    <a href="{{ route('upload.form') }}" class="btn btn-primary">Subir Nuevo CV</a>
</div>

@if($resumes->isEmpty())
    <div class="card" style="text-align:center;padding:60px;">
        <h2>Aun no has subido ningun CV</h2>
        <p style="margin:12px 0 24px;">Sube tu CV y optimizalo para sistemas ATS.</p>
        <a href="{{ route('upload.form') }}" class="btn btn-primary">Subir mi primer CV</a>
    </div>
@else
    <div class="card" style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Archivo</th>
                    <th>Rubro / Cargo</th>
                    <th>Estado</th>
                    <th>Score ATS</th>
                    <th>Fecha</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($resumes as $resume)
                <tr>
                    <td>{{ $resume->id }}</td>
                    <td>{{ Str::limit($resume->original_filename, 30) }}</td>
                    <td>
                        @if($resume->target_industry)
                            {{ $resume->target_industry }}<br>
                            <small>{{ $resume->target_role }}</small>
                        @else
                            <span style="color:#999;">Sin definir</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge badge-{{ $resume->status->value }}">
                            {{ $resume->status->value }}
                        </span>
                    </td>
                    <td>
                        @if($resume->latestVersion && isset($resume->latestVersion->score_json['overall']))
                            @php $s = $resume->latestVersion->score_json['overall']; @endphp
                            <span class="score-badge" style="background:{{ $s >= 70 ? '#28a745' : ($s >= 50 ? '#ffc107' : '#dc3545') }}">
                                {{ $s }}
                            </span>
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $resume->created_at->format('d/m/Y H:i') }}</td>
                    <td>
                        @switch($resume->status->value)
                            @case('draft')
                                <a href="{{ route('resumes.target-role', $resume->id) }}" class="btn btn-sm btn-primary">Configurar</a>
                                @break
                            @case('processing')
                                <a href="{{ route('resumes.status', $resume->id) }}" class="btn btn-sm btn-secondary">Ver Estado</a>
                                @break
                            @case('preview_ready')
                                <a href="{{ route('resumes.preview-page', $resume->id) }}" class="btn btn-sm btn-success">Ver Preview</a>
                                @break
                            @case('paid')
                            @case('delivered')
                                <a href="{{ route('resumes.status', $resume->id) }}" class="btn btn-sm btn-success">Ver Resultado</a>
                                @break
                            @case('failed')
                                <a href="{{ route('resumes.target-role', $resume->id) }}" class="btn btn-sm btn-danger">Reintentar</a>
                                @break
                        @endswitch
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $resumes->links() }}
@endif
@endsection
