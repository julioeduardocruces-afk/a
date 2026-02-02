@extends('layouts.app')
@section('title', 'Pagar - CV Optimizado ATS')
@push('fb_events')
<script>
(function(){
    var key='fbq_checkout_{{ $resume->id }}';
    if(typeof fbq==='function'&&!sessionStorage.getItem(key)){
        fbq('track','InitiateCheckout',{value:{{ (int) config('ats.price_clp', 4990) }},currency:'CLP',content_name:'CV ATS Optimization',content_category:{!! json_encode($resume->target_industry ?? 'General') !!},content_type:'product',content_ids:[{!! json_encode((string)$resume->id) !!}],num_items:1},{eventID:'checkout_{{ $resume->id }}'});
        sessionStorage.setItem(key,'1');
    }
})();
</script>
@endpush
@section('content')
<div style="max-width:700px;margin:30px auto;">
    <h1 style="margin-bottom:8px;">Paso 3: Pagar y Optimizar</h1>
    <p style="color:#666;margin-bottom:20px;">Tu CV sera optimizado por nuestro sistema especializado para superar los filtros ATS.</p>

    @if($errors->any())
    <div class="card" style="border-left:4px solid #dc3545;background:#fff5f5;margin-bottom:16px;">
        @foreach($errors->all() as $error)
            <p style="color:#dc3545;margin:4px 0;">{{ $error }}</p>
        @endforeach
    </div>
    @endif

    <div class="card">
        <table>
            <tr><th style="width:150px;">Archivo</th><td>{{ $resume->original_filename }}</td></tr>
            <tr><th>Rubro</th><td>{{ $resume->target_industry }}</td></tr>
            <tr><th>Cargo</th><td>{{ $resume->target_role }}</td></tr>
        </table>
    </div>

    <div class="card" style="text-align:center;background:#f0f7ff;border:2px solid #0066ff;">
        <h2 style="margin-bottom:8px;">Optimizar CV para ATS</h2>
        <p style="margin-bottom:16px;">Nuestro sistema de IA optimizara tu CV para pasar filtros ATS. Recibiras el CV final en PDF y DOCX listo para postular.</p>
        <p style="font-size:1.5rem;font-weight:bold;color:#0066ff;margin-bottom:16px;">
            ${{ number_format(config('ats.price_clp', 4990), 0, ',', '.') }} CLP
        </p>
        <form method="POST" action="{{ route('payments.flow.create') }}">
            @csrf
            <input type="hidden" name="resume_id" value="{{ $resume->id }}">
            <div class="form-group" style="max-width:400px;margin:0 auto 16px;">
                <label for="customer_email">Tu email (para recibir el CV optimizado)</label>
                <input type="email" id="customer_email" name="customer_email"
                       value="{{ old('customer_email', $resume->customer_email) }}"
                       placeholder="tu@email.com" required>
            </div>
            <button type="submit" class="btn btn-primary" style="font-size:1.1rem;padding:14px 48px;">
                Pagar con Webpay
            </button>
        </form>
        <p style="font-size:0.8rem;color:#999;margin-top:8px;">Pago seguro via Flow / Webpay. Recibiras el CV en tu email.</p>
    </div>

    <div style="margin-top:20px;">
        <a href="{{ route('home') }}">Volver al inicio</a>
    </div>
</div>
@endsection
