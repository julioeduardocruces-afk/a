@extends('layouts.app')

@section('title', 'Optimiza tu CV para ATS')
@section('meta_description', 'Optimiza tu curriculum vitae para sistemas ATS como Laborum, ChileTrabajos, Trabajando.com. Mejora tu puntaje ATS y consigue mas entrevistas.')

@section('meta_extra')
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'WebApplication',
    'name' => 'CV Optimizer ATS',
    'description' => 'Servicio de optimizacion de CV para sistemas ATS',
    'url' => url('/'),
    'applicationCategory' => 'BusinessApplication',
    'operatingSystem' => 'Web',
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
</script>
@endsection

@section('content')
<div style="text-align:center;padding:60px 0 40px;">
    <h1 style="font-size:2.5rem;color:#1a1a2e;margin-bottom:16px;">Optimiza tu CV para Sistemas ATS</h1>
    <p style="font-size:1.2rem;color:#555;max-width:700px;margin:0 auto 30px;">
        Los sistemas ATS filtran el 75% de los CVs antes de que un reclutador los vea.
        Nuestro servicio usa inteligencia artificial para optimizar tu CV y aumentar tu puntaje ATS.
    </p>
    <div style="display:flex;gap:16px;justify-content:center;flex-wrap:wrap;">
        <a href="{{ route('upload.form') }}" class="btn btn-primary" style="font-size:1.1rem;padding:14px 36px;">Subir mi CV</a>
        <a href="{{ route('cv-builder.form') }}" class="btn btn-success" style="font-size:1.1rem;padding:14px 36px;">Crear CV desde Cero</a>
    </div>
    <p style="margin-top:12px;color:#888;font-size:0.9rem;">Sin registro. Sube tu CV o crealo con nuestro formulario. Resultado en minutos.</p>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;margin:40px 0;">
    <div class="card" style="text-align:center;">
        <h3 style="margin-bottom:8px;">1. Sube o Crea tu CV</h3>
        <p>Sube tu CV en PDF/DOCX o crealo desde cero con nuestro formulario. Sin necesidad de crear cuenta.</p>
    </div>
    <div class="card" style="text-align:center;">
        <h3 style="margin-bottom:8px;">2. Elige tu Objetivo</h3>
        <p>Selecciona el rubro y cargo al que postulas: TI, Salud, Ventas, Finanzas y mas.</p>
    </div>
    <div class="card" style="text-align:center;">
        <h3 style="margin-bottom:8px;">3. Recibe tu CV Optimizado</h3>
        <p>La IA optimiza keywords, estructura y formato ATS. Paga y recibe PDF y DOCX listos para postular en tu email.</p>
    </div>
</div>

<div class="card" style="margin:40px 0;">
    <h2 style="margin-bottom:24px;text-align:center;">Compatible con las Principales Plataformas de Empleo</h2>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:24px 32px;align-items:center;justify-items:center;max-width:650px;margin:0 auto;">
        <div style="width:140px;height:60px;display:flex;align-items:center;justify-content:center;">
            <img src="{{ asset('img/logos/LinkedIn.webp') }}" alt="LinkedIn" style="max-width:100%;max-height:100%;object-fit:contain;">
        </div>
        <div style="width:140px;height:60px;display:flex;align-items:center;justify-content:center;">
            <img src="{{ asset('img/logos/indeed.png') }}" alt="Indeed" style="max-width:100%;max-height:100%;object-fit:contain;">
        </div>
        <div style="width:140px;height:60px;display:flex;align-items:center;justify-content:center;">
            <img src="{{ asset('img/logos/Computrabajo.png') }}" alt="Computrabajo" style="max-width:100%;max-height:100%;object-fit:contain;">
        </div>
        <div style="width:140px;height:60px;display:flex;align-items:center;justify-content:center;">
            <img src="{{ asset('img/logos/Laaborum.png') }}" alt="Laborum" style="max-width:100%;max-height:100%;object-fit:contain;">
        </div>
        <div style="width:140px;height:60px;display:flex;align-items:center;justify-content:center;">
            <img src="{{ asset('img/logos/Chiletrabajos.png') }}" alt="ChileTrabajos" style="max-width:100%;max-height:100%;object-fit:contain;">
        </div>
        <div style="width:140px;height:60px;display:flex;align-items:center;justify-content:center;">
            <img src="{{ asset('img/logos/trabajando.webp') }}" alt="Trabajando.com" style="max-width:100%;max-height:100%;object-fit:contain;">
        </div>
    </div>
</div>

<div class="card" style="margin:40px 0;">
    <h2 style="margin-bottom:16px;">Rubros Disponibles</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:8px;">
        <span>Tecnologias de la Informacion (TI)</span>
        <span>Salud / Hemodialisis</span>
        <span>Ventas y Comercial</span>
        <span>Finanzas / Factoring</span>
        <span>Ingenieria</span>
        <span>Educacion</span>
        <span>Logistica y Transporte</span>
        <span>Marketing Digital</span>
        <span>Recursos Humanos</span>
        <span>Administracion</span>
        <span>Construccion</span>
        <span>Y muchos mas...</span>
    </div>
</div>

<div style="text-align:center;padding:40px 0;">
    <h2>Preguntas Frecuentes</h2>
    <div style="max-width:700px;margin:20px auto;text-align:left;" itemscope itemtype="https://schema.org/FAQPage">
        <div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
            <h3 itemprop="name">Que es un sistema ATS?</h3>
            <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                <p itemprop="text">ATS (Applicant Tracking System) es el software que usan las empresas para filtrar CVs automaticamente. Si tu CV no esta optimizado, puede ser rechazado antes de que un humano lo lea.</p>
            </div>
        </div>
        <div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
            <h3 itemprop="name">Necesito crear una cuenta?</h3>
            <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                <p itemprop="text">No. Nuestro servicio funciona sin registro. Solo sube tu CV, elige el rubro, y recibe el resultado en tu email despues del pago.</p>
            </div>
        </div>
        <div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
            <h3 itemprop="name">Que formatos acepta?</h3>
            <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                <p itemprop="text">PDF y DOCX (max 10 MB). Si no tienes tu CV en archivo, puedes crearlo directamente desde nuestro formulario.</p>
            </div>
        </div>
    </div>
    <a href="{{ route('faq') }}">Ver todas las preguntas frecuentes</a>
</div>
@endsection
