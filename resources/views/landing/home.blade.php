@extends('layouts.app')

@section('title', 'Optimiza tu CV para ATS - Consigue mas Entrevistas')
@section('meta_description', 'Servicio especializado en optimizacion de CV para sistemas ATS. Compatible con Laborum, ChileTrabajos, Trabajando.com, LinkedIn, Indeed y Computrabajo. Mejora tu puntaje ATS y consigue mas entrevistas.')

@section('meta_extra')
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'WebApplication',
    'name' => 'CV Optimizer ATS',
    'description' => 'Servicio especializado en optimizacion de CV para sistemas ATS',
    'url' => url('/'),
    'applicationCategory' => 'BusinessApplication',
    'operatingSystem' => 'Web',
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
</script>
@endsection

@section('content')
{{-- Hero --}}
<div style="text-align:center;padding:60px 0 40px;">
    <h1 style="font-size:2.5rem;color:#1a1a2e;margin-bottom:16px;">Optimiza tu CV para Sistemas ATS</h1>
    <p style="font-size:1.2rem;color:#555;max-width:720px;margin:0 auto 12px;">
        Mas del 75% de los CVs son descartados automaticamente por los filtros ATS antes de que un reclutador los vea.
    </p>
    <p style="font-size:1.1rem;color:#333;max-width:720px;margin:0 auto 30px;font-weight:500;">
        Nuestro sistema especializado analiza, reestructura y optimiza tu CV para superar estos filtros y llegar directamente a manos del reclutador.
    </p>
    <div style="display:flex;gap:16px;justify-content:center;flex-wrap:wrap;">
        <a href="{{ route('upload.form') }}" class="btn btn-primary" style="font-size:1.1rem;padding:14px 36px;">Subir mi CV</a>
        <a href="{{ route('cv-builder.form') }}" class="btn btn-success" style="font-size:1.1rem;padding:14px 36px;">Crear CV desde Cero</a>
    </div>
    <p style="margin-top:12px;color:#888;font-size:0.9rem;">Sin registro. Sube tu CV o crealo con nuestro formulario. Resultado en minutos.</p>
</div>

{{-- Como funciona --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin:40px 0;">
    <div class="card" style="text-align:center;">
        <h3 style="margin-bottom:8px;">1. Sube o Crea tu CV</h3>
        <p>Sube tu CV en PDF o DOCX, o crealo desde cero con nuestro formulario guiado. Sin necesidad de crear cuenta.</p>
    </div>
    <div class="card" style="text-align:center;">
        <h3 style="margin-bottom:8px;">2. Elige tu Objetivo</h3>
        <p>Selecciona el rubro y cargo al que postulas. Nuestro sistema adapta tu CV a los requisitos especificos de tu industria.</p>
    </div>
    <div class="card" style="text-align:center;">
        <h3 style="margin-bottom:8px;">3. Recibe tu CV Optimizado</h3>
        <p>El sistema optimiza palabras clave, estructura y formato ATS. Recibe tu CV en PDF y Word listo para postular.</p>
    </div>
</div>

{{-- Que incluye --}}
<div class="card" style="margin:40px 0;">
    <h2 style="margin-bottom:16px;text-align:center;">Que incluye la optimizacion?</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;max-width:800px;margin:0 auto;">
        <div>
            <h4 style="color:#1a1a2e;margin-bottom:4px;">Palabras clave estrategicas</h4>
            <p style="color:#555;font-size:0.95rem;">Incorporamos los terminos que los sistemas ATS buscan para tu rubro y cargo, aumentando tu puntaje de compatibilidad.</p>
        </div>
        <div>
            <h4 style="color:#1a1a2e;margin-bottom:4px;">Estructura compatible ATS</h4>
            <p style="color:#555;font-size:0.95rem;">Reorganizamos secciones, encabezados y formato para que los lectores automaticos procesen tu CV sin errores.</p>
        </div>
        <div>
            <h4 style="color:#1a1a2e;margin-bottom:4px;">Redaccion orientada a resultados</h4>
            <p style="color:#555;font-size:0.95rem;">Transformamos descripciones genericas en logros concretos y medibles que destacan ante reclutadores.</p>
        </div>
        <div>
            <h4 style="color:#1a1a2e;margin-bottom:4px;">Formato profesional limpio</h4>
            <p style="color:#555;font-size:0.95rem;">Entregamos tu CV en PDF y Word con diseno profesional, margenes correctos y tipografia optimizada para lectura digital.</p>
        </div>
    </div>
</div>

{{-- Por que necesitas esto --}}
<div class="card" style="margin:40px 0;background:#f8f9fa;">
    <h2 style="margin-bottom:16px;text-align:center;">Por que necesitas un CV optimizado para ATS?</h2>
    <div style="max-width:750px;margin:0 auto;">
        <p style="color:#444;font-size:1rem;line-height:1.7;margin-bottom:12px;">
            Las empresas en Chile y el mundo utilizan sistemas ATS (Applicant Tracking System) para filtrar automaticamente los cientos de CVs que reciben por cada oferta laboral.
            Estos sistemas analizan tu CV buscando palabras clave, formato compatible y estructura especifica. <strong>Si tu CV no cumple con estos criterios, es descartado automaticamente sin que ningun reclutador lo lea.</strong>
        </p>
        <p style="color:#444;font-size:1rem;line-height:1.7;margin-bottom:12px;">
            El problema es que la mayoria de las personas redactan su CV pensando en que lo leera una persona, no una maquina. Usan formatos creativos, tablas, columnas, imagenes o encabezados que los sistemas ATS no pueden interpretar. El resultado: tu CV queda fuera del proceso aunque estes perfectamente calificado para el cargo.
        </p>
        <p style="color:#444;font-size:1rem;line-height:1.7;">
            Nuestro servicio resuelve este problema. Analizamos tu CV y lo reestructuramos para que cumpla con los estandares que exigen los sistemas ATS de las principales plataformas de empleo en Chile y Latinoamerica.
        </p>
    </div>
</div>

{{-- Logos plataformas --}}
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
    <p style="text-align:center;color:#888;font-size:0.85rem;margin-top:16px;">
        Optimizamos tu CV para que sea 100% compatible con los filtros ATS de estas y otras plataformas.
    </p>
</div>

{{-- Rubros --}}
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

{{-- FAQ --}}
<div style="text-align:center;padding:40px 0;">
    <h2>Preguntas Frecuentes</h2>
    <div style="max-width:700px;margin:20px auto;text-align:left;" itemscope itemtype="https://schema.org/FAQPage">
        <div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
            <h3 itemprop="name">Que es un sistema ATS?</h3>
            <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                <p itemprop="text">ATS (Applicant Tracking System) es el software que usan las empresas para filtrar CVs automaticamente. Si tu CV no esta optimizado para estos sistemas, puede ser rechazado antes de que un reclutador lo lea, incluso si cumples con todos los requisitos del cargo.</p>
            </div>
        </div>
        <div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
            <h3 itemprop="name">Que hace diferente a este servicio?</h3>
            <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                <p itemprop="text">Nuestro sistema esta especializado exclusivamente en optimizacion para ATS. No es un simple cambio de formato: analizamos tu contenido, incorporamos las palabras clave que buscan los reclutadores en tu industria, reestructuramos las secciones y optimizamos la redaccion para maximizar tu puntaje de compatibilidad.</p>
            </div>
        </div>
        <div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
            <h3 itemprop="name">Necesito crear una cuenta?</h3>
            <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                <p itemprop="text">No. Nuestro servicio funciona sin registro. Solo sube tu CV o crealo con nuestro formulario, elige el rubro, y recibe el resultado optimizado en tu email despues del pago.</p>
            </div>
        </div>
        <div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
            <h3 itemprop="name">Que formatos acepta y entrega?</h3>
            <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                <p itemprop="text">Aceptamos PDF y DOCX (max 10 MB). Si no tienes tu CV en archivo, puedes crearlo con nuestro formulario. Entregamos tu CV optimizado en ambos formatos: PDF y Word, listos para adjuntar en cualquier portal de empleo.</p>
            </div>
        </div>
        <div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
            <h3 itemprop="name">Solo sirve para Chile?</h3>
            <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                <p itemprop="text">Estamos enfocados en el mercado chileno y latinoamericano, pero la optimizacion ATS funciona para cualquier plataforma global como LinkedIn e Indeed. Los sistemas ATS funcionan de la misma manera en todo el mundo.</p>
            </div>
        </div>
    </div>
    <a href="{{ route('faq') }}">Ver todas las preguntas frecuentes</a>
</div>

{{-- CTA final --}}
<div style="text-align:center;padding:30px 0 60px;">
    <h2 style="color:#1a1a2e;margin-bottom:12px;">Deja de ser filtrado. Empieza a ser contactado.</h2>
    <p style="color:#555;max-width:600px;margin:0 auto 24px;font-size:1.05rem;">
        Tu experiencia merece ser vista. Optimiza tu CV hoy y aumenta tus posibilidades de conseguir entrevistas.
    </p>
    <div style="display:flex;gap:16px;justify-content:center;flex-wrap:wrap;">
        <a href="{{ route('upload.form') }}" class="btn btn-primary" style="font-size:1.1rem;padding:14px 36px;">Subir mi CV</a>
        <a href="{{ route('cv-builder.form') }}" class="btn btn-success" style="font-size:1.1rem;padding:14px 36px;">Crear CV desde Cero</a>
    </div>
</div>
@endsection
