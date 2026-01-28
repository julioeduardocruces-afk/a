@extends('layouts.app')
@section('title', 'Estado del CV')
@section('content')
<div style="max-width:700px;margin:30px auto;">
    <h1 style="margin-bottom:20px;">Estado de tu CV</h1>

    <div class="card">
        <table>
            <tr><th style="width:150px;">Archivo</th><td>{{ $resume->original_filename }}</td></tr>
            <tr><th>Rubro</th><td>{{ $resume->target_industry ?? '-' }}</td></tr>
            <tr><th>Cargo</th><td>{{ $resume->target_role ?? '-' }}</td></tr>
            <tr>
                <th>Estado</th>
                <td>
                    <span class="badge badge-{{ $resume->status->value }}">{{ $resume->status->value }}</span>
                    @if($resume->error_message)
                        <br><small style="color:#dc3545;">{{ $resume->error_message }}</small>
                    @endif
                </td>
            </tr>
            @if($resume->latestVersion && isset($resume->latestVersion->score_json['overall']))
                <tr>
                    <th>Score ATS</th>
                    <td>
                        @php $s = $resume->latestVersion->score_json['overall']; @endphp
                        <span class="score-badge" style="background:{{ $s >= 70 ? '#28a745' : ($s >= 50 ? '#ffc107' : '#dc3545') }}">
                            {{ $s }}
                        </span>
                        <span style="margin-left:8px;">/100</span>
                    </td>
                </tr>
            @endif
        </table>
    </div>

    @if($resume->status->value === 'processing')
        <div class="card" style="text-align:center;">
            <h2>Procesando tu CV...</h2>
            <p>Estamos optimizando tu CV con inteligencia artificial. Esto puede tomar unos minutos.</p>
            <p style="margin-top:12px;">Esta pagina se actualiza automaticamente.</p>
        </div>
        <script>setTimeout(function(){ location.reload(); }, 8000);</script>
    @endif

    @if(in_array($resume->status->value, ['preview_ready']))
        <div style="display:flex;gap:12px;margin-top:16px;">
            <a href="{{ route('resumes.preview-page', $resume->id) }}" class="btn btn-primary">Ver Preview</a>
        </div>
    @endif

    @if(in_array($resume->status->value, ['paid', 'delivered']))
        <div class="card" style="background:#d4edda;">
            <h2>Pago confirmado</h2>
            <p>Tu CV optimizado ha sido generado y enviado a tu email. Revisa tu bandeja de entrada.</p>
        </div>
    @endif

    @if($resume->status->value === 'failed')
        <div style="margin-top:16px;">
            <form method="POST" action="{{ route('resumes.process', $resume->id) }}">
                @csrf
                <button type="submit" class="btn btn-danger">Reintentar Procesamiento</button>
            </form>
        </div>
    @endif

    <div style="margin-top:20px;">
        <a href="{{ route('dashboard') }}">Volver al dashboard</a>
    </div>
</div>
@endsection
