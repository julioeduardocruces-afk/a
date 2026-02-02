@extends('layouts.app')
@section('title', 'Estado del CV')
@section('content')
<div style="max-width:700px;margin:30px auto;">
    <h1 style="margin-bottom:20px;">Estado de tu CV</h1>

    @if($errors->any())
    <div class="card" style="border-left:4px solid #dc3545;background:#fff5f5;margin-bottom:16px;">
        @foreach($errors->all() as $error)
            <p style="color:#dc3545;margin:4px 0;">{{ $error }}</p>
        @endforeach
    </div>
    <script>console.error('[ATS Debug] Form errors:', @json($errors->all()));</script>
    @endif

    @if(session('success'))
    <div class="card" style="border-left:4px solid #28a745;background:#f0fff4;margin-bottom:16px;">
        <p style="color:#28a745;margin:0;">{{ session('success') }}</p>
    </div>
    @endif

    <div class="card">
        <table>
            <tr><th style="width:150px;">Archivo</th><td>{{ $resume->original_filename }}</td></tr>
            <tr><th>Rubro</th><td>{{ $resume->target_industry ?? '-' }}</td></tr>
            <tr><th>Cargo</th><td>{{ $resume->target_role ?? '-' }}</td></tr>
            <tr>
                <th>Estado</th>
                <td>
                    <span class="badge badge-{{ $resume->status->value }}">{{ $resume->status->value }}</span>
                    @if($resume->error_code)
                        <br><small style="color:#dc3545;">Ocurrio un error al procesar tu CV ({{ $resume->error_code }}). Puedes reintentar.</small>
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

    @if($resume->status->value === 'paid')
        <div class="card" style="text-align:center;background:#d4edda;">
            <h2>Pago confirmado</h2>
            <p>Tu CV esta siendo procesado por nuestro sistema especializado. Esto puede tomar unos minutos.</p>
            <p style="margin-top:12px;">Esta pagina se actualiza automaticamente.</p>
        </div>
        <script>setTimeout(function(){ location.reload(); }, 8000);</script>
    @endif

    @if($resume->status->value === 'processing')
        <div class="card" style="text-align:center;">
            <h2>Procesando tu CV...</h2>
            <p>Estamos optimizando tu CV con nuestro sistema especializado en ATS. Esto puede tomar unos minutos.</p>
            <p style="margin-top:12px;">Esta pagina se actualiza automaticamente.</p>
        </div>
        <script>setTimeout(function(){ location.reload(); }, 8000);</script>
    @endif

    @if($resume->status->value === 'delivered')
        <div class="card" style="background:#d4edda;">
            <h2>CV Optimizado Entregado</h2>
            <p>Tu CV optimizado ha sido generado y enviado a tu email. Revisa tu bandeja de entrada.</p>
        </div>
        @push('fb_events')
        <script>
        (function(){
            var key='fbq_purchase_{{ $resume->id }}';
            if(typeof fbq==='function'&&!localStorage.getItem(key)){
                fbq('track','Purchase',{value:{{ (int) config('ats.price_clp', 4990) }},currency:'CLP',content_name:'CV ATS Optimization',content_category:{!! json_encode($resume->target_industry ?? 'General') !!},content_ids:[{!! json_encode((string)$resume->id) !!}]},{eventID:'purchase_{{ $resume->id }}'});
                localStorage.setItem(key,'1');
            }
        })();
        </script>
        @endpush
    @endif

    @if($resume->status->value === 'draft')
        <div style="margin-top:16px;">
            <a href="{{ route('resumes.payment', $resume->id) }}" class="btn btn-primary">Ir al pago</a>
        </div>
    @endif

    @if($resume->status->value === 'failed')
        <div style="margin-top:16px;">
            <form method="POST" action="{{ route('resumes.process', $resume->id) }}">
                @csrf
                <button type="submit" class="btn btn-danger">Reintentar Procesamiento</button>
            </form>
        </div>
        <script>
            console.error('[ATS Debug] Resume #{{ $resume->id }} failed');
            console.error('[ATS Debug] error_code:', @json($resume->error_code));
            console.error('[ATS Debug] error_message:', @json($resume->error_message));
        </script>
    @endif

    <div style="margin-top:20px;">
        <a href="{{ route('home') }}">Volver al inicio</a>
    </div>
</div>
@endsection
