@extends('layouts.app')
@section('title', 'Preview CV Optimizado')
@section('content')
<div style="max-width:900px;margin:30px auto;">
    <h1 style="margin-bottom:8px;">Preview - CV Optimizado ATS</h1>
    <p style="color:#666;margin-bottom:20px;">{{ $resume->target_industry }} / {{ $resume->target_role }}</p>

    {{-- Score display --}}
    @if(!empty($score))
    <div class="card" style="display:flex;gap:20px;align-items:center;flex-wrap:wrap;">
        <div style="text-align:center;">
            @php $s = $score['overall'] ?? 0; @endphp
            <div class="score-badge" style="width:64px;height:64px;line-height:64px;font-size:1.4rem;background:{{ $s >= 70 ? '#28a745' : ($s >= 50 ? '#ffc107' : '#dc3545') }}">
                {{ $s }}
            </div>
            <div style="margin-top:4px;font-weight:600;">Score ATS</div>
        </div>
        <div style="flex:1;display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;">
            @foreach(['headline_match' => 'Headline', 'keyword_density' => 'Keywords', 'sections_present' => 'Secciones', 'length' => 'Longitud', 'structure' => 'Estructura'] as $key => $label)
                @if(isset($score[$key]))
                <div>
                    <div style="font-size:0.85rem;color:#666;">{{ $label }}</div>
                    <div style="background:#e9ecef;border-radius:4px;height:8px;margin-top:2px;">
                        <div style="background:{{ $score[$key] >= 70 ? '#28a745' : ($score[$key] >= 50 ? '#ffc107' : '#dc3545') }};height:100%;border-radius:4px;width:{{ $score[$key] }}%;"></div>
                    </div>
                    <div style="font-size:0.8rem;">{{ $score[$key] }}/100</div>
                </div>
                @endif
            @endforeach
        </div>
    </div>
    @endif

    {{-- Preview images (rasterized, no text) --}}
    <div class="card">
        <div style="text-align:center;margin-bottom:12px;">
            <p style="color:#999;font-size:0.9rem;">
                Preview protegido. El texto no es seleccionable. Paga para descargar el documento final.
            </p>
        </div>

        @for($p = 1; $p <= $pageCount; $p++)
        <div style="border:1px solid #ddd;margin-bottom:12px;background:#fff;">
            <img src="{{ route('resumes.preview', ['id' => $resume->id, 'page' => $p]) }}"
                 alt="Preview pagina {{ $p }}"
                 style="width:100%;display:block;user-select:none;pointer-events:none;"
                 draggable="false"
                 oncontextmenu="return false;">
        </div>
        @endfor
    </div>

    {{-- Payment CTA --}}
    @if($resume->status->value === 'preview_ready')
    <div class="card" style="text-align:center;background:#f0f7ff;border:2px solid #0066ff;">
        <h2 style="margin-bottom:8px;">Desbloquear CV Optimizado</h2>
        <p style="margin-bottom:16px;">Obtiene tu CV final en PDF y DOCX, sin watermark, listo para postular.</p>
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
    @endif

    <div style="margin-top:20px;">
        <a href="{{ route('home') }}">Volver al inicio</a>
    </div>
</div>

{{-- Anti-copy JS (defense in depth, not primary protection) --}}
<script>
document.addEventListener('contextmenu', function(e){ e.preventDefault(); });
document.addEventListener('keydown', function(e){
    if((e.ctrlKey || e.metaKey) && (e.key === 'c' || e.key === 'p' || e.key === 's' || e.key === 'a')){
        e.preventDefault();
    }
    if(e.key === 'PrintScreen'){ e.preventDefault(); }
});
document.addEventListener('copy', function(e){ e.preventDefault(); });
document.addEventListener('selectstart', function(e){ e.preventDefault(); });
</script>
@endsection
