@extends('layouts.app')

@section('title', 'Optimiza tu CV para ATS - Consigue mas Entrevistas')
@section('meta_description', 'Servicio especializado en optimizacion de CV para sistemas ATS. Compatible con Laborum, ChileTrabajos, Trabajando.com, LinkedIn, Indeed y Computrabajo.')
@section('meta_keywords', 'CV ATS Chile, optimizar curriculum vitae, CV Laborum, CV ChileTrabajos, curriculum profesional, mejorar CV, CV compatible ATS, conseguir trabajo Chile')

@section('schema_extra')
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
<style>
    .main-content { padding: 0; min-height: 0; }
    footer { margin-top: 0; }
    .landing-hero {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
        color: white; text-align: center; padding: 70px 20px 60px;
        position: relative; overflow: hidden;
    }
    .landing-hero::before {
        content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
        background: radial-gradient(circle at 30% 50%, rgba(0,102,255,0.15) 0%, transparent 50%),
                    radial-gradient(circle at 70% 80%, rgba(40,167,69,0.1) 0%, transparent 50%);
        pointer-events: none;
    }
    .landing-hero h1 { font-size: 2.6rem; margin-bottom: 16px; position: relative; font-weight: 800; }
    .landing-hero h1 span { color: #4da3ff; }
    .landing-hero p { position: relative; }
    .hero-buttons { display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; position: relative; }
    .hero-buttons .btn { padding: 16px 40px; font-size: 1.1rem; border-radius: 50px; transition: transform 0.2s, box-shadow 0.2s; }
    .hero-buttons .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.3); }
    .hero-buttons .btn-primary { background: linear-gradient(135deg, #0066ff, #0052cc); }
    .hero-buttons .btn-success { background: linear-gradient(135deg, #28a745, #1e7e34); }

    .section-alt { background: #f0f4f8; padding: 50px 20px; }
    .section-white { background: white; padding: 50px 20px; }
    .section-dark { background: #1a1a2e; color: white; padding: 50px 20px; }
    .section-title { text-align: center; font-size: 1.8rem; color: #1a1a2e; margin-bottom: 12px; font-weight: 700; }
    .section-dark .section-title { color: white; }
    .section-subtitle { text-align: center; color: #666; font-size: 1.05rem; max-width: 650px; margin: 0 auto 36px; }
    .section-dark .section-subtitle { color: #aab; }

    .steps-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; max-width: 950px; margin: 0 auto; }
    .step-card {
        background: white; border-radius: 12px; padding: 32px 24px; text-align: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08); transition: transform 0.2s; position: relative; border-top: 4px solid #0066ff;
    }
    .step-card:hover { transform: translateY(-4px); }
    .step-card:nth-child(2) { border-top-color: #28a745; }
    .step-card:nth-child(3) { border-top-color: #f59e0b; }
    .step-number {
        width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, #0066ff, #0052cc);
        color: white; font-size: 1.3rem; font-weight: 800; display: flex; align-items: center; justify-content: center;
        margin: 0 auto 16px;
    }
    .step-card:nth-child(2) .step-number { background: linear-gradient(135deg, #28a745, #1e7e34); }
    .step-card:nth-child(3) .step-number { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .step-card h3 { font-size: 1.1rem; color: #1a1a2e; margin-bottom: 8px; }
    .step-card p { color: #555; font-size: 0.95rem; line-height: 1.6; }

    .features-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; max-width: 900px; margin: 0 auto; }
    .feature-card {
        background: white; border-radius: 12px; padding: 28px; box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        display: flex; gap: 16px; align-items: flex-start; transition: transform 0.2s;
    }
    .feature-card:hover { transform: translateY(-3px); }
    .feature-icon {
        width: 50px; height: 50px; min-width: 50px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center; font-size: 1.4rem; font-weight: 800;
    }
    .feature-icon.blue { background: #e8f0fe; color: #0066ff; }
    .feature-icon.green { background: #e6f7ed; color: #28a745; }
    .feature-icon.orange { background: #fef3e2; color: #f59e0b; }
    .feature-icon.purple { background: #f0e6ff; color: #7c3aed; }
    .feature-card h4 { font-size: 1rem; color: #1a1a2e; margin-bottom: 6px; }
    .feature-card p { color: #555; font-size: 0.9rem; line-height: 1.6; }

    .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; max-width: 850px; margin: 0 auto; }
    .stat-card {
        background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.12);
        border-radius: 12px; padding: 28px 16px; text-align: center;
    }
    .stat-number { font-size: 2.2rem; font-weight: 800; color: #4da3ff; margin-bottom: 4px; }
    .stat-label { font-size: 0.85rem; color: #aab; line-height: 1.4; }

    .logos-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; align-items: center; max-width: 750px; margin: 0 auto; }
    .logo-item {
        height: 100px; display: flex; align-items: center; justify-content: center;
        background: white; border-radius: 12px; padding: 16px 28px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .logo-item img { max-width: 160px; max-height: 60px; object-fit: contain; filter: grayscale(30%); transition: filter 0.2s; }
    .logo-item:hover img { filter: grayscale(0%); }

    .info-block { max-width: 750px; margin: 0 auto; }
    .info-block p { color: #444; font-size: 1rem; line-height: 1.8; margin-bottom: 14px; }

    .rubros-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; max-width: 950px; margin: 0 auto; }
    .rubro-item {
        background: white; padding: 20px 18px; border-radius: 12px; font-size: 0.95rem; color: #333;
        box-shadow: 0 2px 10px rgba(0,0,0,0.07); text-align: center; transition: transform 0.2s, box-shadow 0.2s;
        border-bottom: 3px solid transparent;
    }
    .rubro-item:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.1); border-bottom-color: #0066ff; }
    .rubro-icon { font-size: 1.8rem; margin-bottom: 8px; display: block; }
    .rubro-item span { font-weight: 600; color: #1a1a2e; }

    .faq-container { max-width: 700px; margin: 0 auto; }
    .faq-item { background: white; border-radius: 10px; padding: 20px 24px; margin-bottom: 12px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
    .faq-item h3 { font-size: 1rem; color: #1a1a2e; margin-bottom: 8px; }
    .faq-item p { color: #555; font-size: 0.93rem; line-height: 1.6; }

    .cta-section {
        background: linear-gradient(135deg, #0066ff 0%, #0052cc 100%);
        color: white; text-align: center; padding: 50px 20px;
    }
    .cta-section h2 { font-size: 1.8rem; margin-bottom: 12px; font-weight: 700; }
    .cta-section p { max-width: 550px; margin: 0 auto 28px; font-size: 1.05rem; opacity: 0.9; }
    .cta-section .btn { background: white; color: #0066ff; font-weight: 700; border-radius: 50px; padding: 16px 36px; }
    .cta-section .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.2); }
    .cta-section .btn-outline { background: transparent; color: white; border: 2px solid rgba(255,255,255,0.7); }
    .cta-section .btn-outline:hover { background: rgba(255,255,255,0.1); border-color: white; }

    .nav-toggle { display: none; background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; padding: 4px 8px; line-height: 1; }

    @media (max-width: 768px) {
        .landing-hero h1 { font-size: 1.8rem; }
        .steps-grid { grid-template-columns: 1fr; max-width: 400px; }
        .features-grid { grid-template-columns: 1fr; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .logos-grid { grid-template-columns: repeat(2, 1fr); }
        .rubros-grid { grid-template-columns: repeat(2, 1fr); }
        .nav-toggle { display: block; }
        .nav-links { display: none; position: absolute; top: 100%; left: 0; right: 0; background: #1a1a2e; padding: 12px 0; border-top: 1px solid rgba(255,255,255,0.1); z-index: 100; }
        .nav-links.open { display: block; }
        .nav-links a { display: block; margin: 0 !important; padding: 12px 24px; font-size: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .nav-links a:hover { background: rgba(255,255,255,0.05); }
        nav .container { position: relative; }
    }
</style>
@endsection

@section('full_width')
{{-- Hero --}}
<div class="landing-hero">
    <h1>Optimiza tu CV para <span>Sistemas ATS</span></h1>
    <p style="font-size:1.15rem;max-width:680px;margin:0 auto 10px;opacity:0.85;">
        Mas del 75% de los CVs son descartados automaticamente antes de que un reclutador los vea.
    </p>
    <p style="font-size:1.1rem;max-width:680px;margin:0 auto 32px;font-weight:500;">
        Nuestro sistema especializado reestructura y optimiza tu CV para superar estos filtros y llegar a manos del reclutador.
    </p>
    <div class="hero-buttons">
        <a href="{{ route('upload.form') }}" class="btn btn-primary">Subir mi CV</a>
        <a href="{{ route('cv-builder.form') }}" class="btn btn-success">Crear CV desde Cero</a>
    </div>
    <p style="margin-top:16px;opacity:0.5;font-size:0.9rem;">Sin registro. Resultado en minutos.</p>
</div>

{{-- Como funciona --}}
<div class="section-alt">
    <div class="section-title">Como Funciona</div>
    <div class="section-subtitle">Tres pasos simples para transformar tu CV</div>
    <div class="steps-grid">
        <div class="step-card">
            <div class="step-number">1</div>
            <h3>Sube o Crea tu CV</h3>
            <p>Sube tu CV en PDF o DOCX, o crealo desde cero con nuestro formulario guiado. Sin necesidad de crear cuenta.</p>
        </div>
        <div class="step-card">
            <div class="step-number">2</div>
            <h3>Elige tu Objetivo</h3>
            <p>Selecciona el rubro y cargo al que postulas. El sistema adapta tu CV a los requisitos de tu industria.</p>
        </div>
        <div class="step-card">
            <div class="step-number">3</div>
            <h3>Recibe tu CV Optimizado</h3>
            <p>Recibe tu CV optimizado en PDF y Word listo para postular en cualquier plataforma de empleo.</p>
        </div>
    </div>
</div>

{{-- Que incluye --}}
<div class="section-white">
    <div class="section-title">Que incluye la optimizacion?</div>
    <div class="section-subtitle">Tu CV sera transformado para maximizar su compatibilidad con filtros ATS</div>
    <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon blue">K</div>
            <div>
                <h4>Palabras clave estrategicas</h4>
                <p>Incorporamos los terminos que los sistemas ATS buscan para tu rubro y cargo, aumentando tu puntaje de compatibilidad.</p>
            </div>
        </div>
        <div class="feature-card">
            <div class="feature-icon green">E</div>
            <div>
                <h4>Estructura compatible ATS</h4>
                <p>Reorganizamos secciones, encabezados y formato para que los lectores automaticos procesen tu CV sin errores.</p>
            </div>
        </div>
        <div class="feature-card">
            <div class="feature-icon orange">R</div>
            <div>
                <h4>Redaccion orientada a resultados</h4>
                <p>Transformamos descripciones genericas en logros concretos y medibles que destacan ante reclutadores.</p>
            </div>
        </div>
        <div class="feature-card">
            <div class="feature-icon purple">F</div>
            <div>
                <h4>Formato profesional limpio</h4>
                <p>Entregamos tu CV en PDF y Word con diseno profesional, margenes correctos y tipografia optimizada.</p>
            </div>
        </div>
    </div>
</div>

{{-- Estadisticas --}}
<div class="section-dark">
    <div class="section-title">Numeros que Importan</div>
    <div class="section-subtitle">Los datos detras de por que necesitas optimizar tu CV</div>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number">75%</div>
            <div class="stat-label">De los CVs son filtrados automaticamente por sistemas ATS</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">90%</div>
            <div class="stat-label">De las grandes empresas usan software ATS para reclutar</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">7seg</div>
            <div class="stat-label">Tiempo promedio que un reclutador mira un CV</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">3x</div>
            <div class="stat-label">Mas probabilidades de entrevista con un CV optimizado</div>
        </div>
    </div>
</div>

{{-- Por que necesitas esto --}}
<div class="section-white">
    <div class="section-title">Por que necesitas un CV optimizado para ATS?</div>
    <div class="section-subtitle"></div>
    <div class="info-block">
        <p>
            Las empresas en Chile y el mundo utilizan sistemas ATS (Applicant Tracking System) para filtrar automaticamente los cientos de CVs que reciben por cada oferta.
            Estos sistemas analizan tu CV buscando palabras clave, formato compatible y estructura especifica. <strong>Si tu CV no cumple con estos criterios, es descartado sin que ningun reclutador lo lea.</strong>
        </p>
        <p>
            La mayoria de las personas redactan su CV pensando en que lo leera una persona, no una maquina. Usan formatos creativos, tablas, columnas o imagenes que los sistemas ATS no pueden interpretar. El resultado: tu CV queda fuera del proceso aunque estes perfectamente calificado.
        </p>
        <p>
            Nuestro servicio resuelve este problema. Analizamos tu CV y lo reestructuramos para que cumpla con los estandares que exigen los filtros ATS de las principales plataformas de empleo.
        </p>
    </div>
</div>

{{-- Logos plataformas --}}
<div class="section-alt">
    <div class="section-title">Compatible con las Principales Plataformas</div>
    <div class="section-subtitle">Optimizamos tu CV para que pase los filtros de estas y otras plataformas</div>
    <div class="logos-grid">
        <div class="logo-item"><img src="{{ asset('img/logos/LinkedIn.webp') }}" alt="LinkedIn"></div>
        <div class="logo-item"><img src="{{ asset('img/logos/indeed.png') }}" alt="Indeed"></div>
        <div class="logo-item"><img src="{{ asset('img/logos/Computrabajo.png') }}" alt="Computrabajo"></div>
        <div class="logo-item"><img src="{{ asset('img/logos/Laaborum.png') }}" alt="Laborum"></div>
        <div class="logo-item"><img src="{{ asset('img/logos/Chiletrabajos.png') }}" alt="ChileTrabajos"></div>
        <div class="logo-item"><img src="{{ asset('img/logos/trabajando.webp') }}" alt="Trabajando.com"></div>
    </div>
</div>

{{-- Rubros --}}
<div class="section-white">
    <div class="section-title">Rubros Disponibles</div>
    <div class="section-subtitle">Optimizacion especializada para cada industria</div>
    <div class="rubros-grid">
        <div class="rubro-item"><div class="rubro-icon">&#128187;</div><span>Tecnologias de la Informacion</span></div>
        <div class="rubro-item"><div class="rubro-icon">&#9764;&#65039;</div><span>Salud / Hemodialisis</span></div>
        <div class="rubro-item"><div class="rubro-icon">&#128200;</div><span>Ventas y Comercial</span></div>
        <div class="rubro-item"><div class="rubro-icon">&#128176;</div><span>Finanzas / Factoring</span></div>
        <div class="rubro-item"><div class="rubro-icon">&#9881;&#65039;</div><span>Ingenieria</span></div>
        <div class="rubro-item"><div class="rubro-icon">&#127891;</div><span>Educacion</span></div>
        <div class="rubro-item"><div class="rubro-icon">&#128666;</div><span>Logistica y Transporte</span></div>
        <div class="rubro-item"><div class="rubro-icon">&#128241;</div><span>Marketing Digital</span></div>
        <div class="rubro-item"><div class="rubro-icon">&#128101;</div><span>Recursos Humanos</span></div>
        <div class="rubro-item"><div class="rubro-icon">&#128203;</div><span>Administracion</span></div>
        <div class="rubro-item"><div class="rubro-icon">&#127959;&#65039;</div><span>Construccion</span></div>
        <div class="rubro-item"><div class="rubro-icon">&#10024;</div><span>Y muchos mas...</span></div>
    </div>
</div>

{{-- FAQ --}}
<div class="section-alt">
    <div class="section-title">Preguntas Frecuentes</div>
    <div class="section-subtitle"></div>
    <div class="faq-container" itemscope itemtype="https://schema.org/FAQPage">
        <div class="faq-item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
            <h3 itemprop="name">Que es un sistema ATS?</h3>
            <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                <p itemprop="text">ATS (Applicant Tracking System) es el software que usan las empresas para filtrar CVs automaticamente. Si tu CV no esta optimizado, puede ser rechazado antes de que un reclutador lo lea, incluso si cumples con todos los requisitos.</p>
            </div>
        </div>
        <div class="faq-item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
            <h3 itemprop="name">Que hace diferente a este servicio?</h3>
            <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                <p itemprop="text">Nuestro sistema esta especializado exclusivamente en optimizacion para ATS. No es un simple cambio de formato: analizamos tu contenido, incorporamos palabras clave de tu industria, reestructuramos las secciones y optimizamos la redaccion para maximizar tu puntaje.</p>
            </div>
        </div>
        <div class="faq-item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
            <h3 itemprop="name">Necesito crear una cuenta?</h3>
            <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                <p itemprop="text">No. Funciona sin registro. Solo sube tu CV o crealo con nuestro formulario, elige el rubro, y recibe el resultado en tu email despues del pago.</p>
            </div>
        </div>
        <div class="faq-item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
            <h3 itemprop="name">Que formatos acepta y entrega?</h3>
            <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                <p itemprop="text">Aceptamos PDF y DOCX (max 10 MB). Tambien puedes crear tu CV desde nuestro formulario. Entregamos el CV optimizado en PDF y Word.</p>
            </div>
        </div>
        <div class="faq-item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
            <h3 itemprop="name">Solo sirve para Chile?</h3>
            <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                <p itemprop="text">Estamos enfocados en Chile y Latinoamerica, pero la optimizacion funciona para cualquier plataforma global como LinkedIn e Indeed.</p>
            </div>
        </div>
    </div>
    <div style="text-align:center;margin-top:20px;">
        <a href="{{ route('faq') }}" style="color:#0066ff;font-weight:600;">Ver todas las preguntas frecuentes</a>
    </div>
</div>

{{-- CTA final --}}
<div class="cta-section">
    <h2>Deja de ser filtrado. Empieza a ser contactado.</h2>
    <p>Tu experiencia merece ser vista. Optimiza tu CV hoy y aumenta tus posibilidades de conseguir entrevistas.</p>
    <div class="hero-buttons">
        <a href="{{ route('upload.form') }}" class="btn">Subir mi CV</a>
        <a href="{{ route('cv-builder.form') }}" class="btn btn-outline">Crear CV desde Cero</a>
    </div>
</div>
@endsection
