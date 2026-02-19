<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $__seoTitle = View::yieldContent('title', 'CV Optimizer ATS');
        $__seoFullTitle = $__seoTitle . ' - Optimiza tu CV';
        $__seoDescription = View::yieldContent('meta_description', 'Optimiza tu CV para sistemas ATS. Mejora tus posibilidades en Laborum, ChileTrabajos y portales de empleo en Chile.');
        $__seoKeywords = View::yieldContent('meta_keywords', 'CV ATS, curriculum vitae, optimizar CV, Laborum, ChileTrabajos, trabajos Chile, curriculum optimizado, ATS Chile');
        $__seoImage = View::yieldContent('meta_image', url('/images/og-default.jpg'));
        $__seoUrl = url()->current();
        $__seoType = View::yieldContent('meta_type', 'website');
        $__siteName = 'CV Optimizer ATS';
    @endphp

    <title>{{ $__seoFullTitle }}</title>
    <meta name="description" content="{{ $__seoDescription }}">
    <meta name="keywords" content="{{ $__seoKeywords }}">
    <meta name="author" content="{{ $__siteName }}">
    <meta name="robots" content="index, follow">

    <!-- Canonical URL -->
    <link rel="canonical" href="{{ $__seoUrl }}">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="{{ $__seoType }}">
    <meta property="og:url" content="{{ $__seoUrl }}">
    <meta property="og:title" content="{{ $__seoFullTitle }}">
    <meta property="og:description" content="{{ $__seoDescription }}">
    <meta property="og:image" content="{{ $__seoImage }}">
    <meta property="og:site_name" content="{{ $__siteName }}">
    <meta property="og:locale" content="es_CL">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="{{ $__seoUrl }}">
    <meta name="twitter:title" content="{{ $__seoFullTitle }}">
    <meta name="twitter:description" content="{{ $__seoDescription }}">
    <meta name="twitter:image" content="{{ $__seoImage }}">

    <!-- JSON-LD Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "{{ $__siteName }}",
        "url": "{{ url('/') }}",
        "description": "{{ $__seoDescription }}",
        "potentialAction": {
            "@type": "SearchAction",
            "target": "{{ url('/') }}?q={search_term_string}",
            "query-input": "required name=search_term_string"
        }
    }
    </script>
    @hasSection('schema_extra')
    @yield('schema_extra')
    @else
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "ProfessionalService",
        "name": "{{ $__siteName }}",
        "description": "Servicio de optimizacion de CV para sistemas ATS (Applicant Tracking System). Mejora tu curriculum para portales de empleo como Laborum y ChileTrabajos.",
        "url": "{{ url('/') }}",
        "priceRange": "$$",
        "areaServed": {
            "@type": "Country",
            "name": "Chile"
        },
        "serviceType": "Optimizacion de Curriculum Vitae",
        "hasOfferCatalog": {
            "@type": "OfferCatalog",
            "name": "Servicios de CV",
            "itemListElement": [
                {
                    "@type": "Offer",
                    "itemOffered": {
                        "@type": "Service",
                        "name": "Optimizacion CV ATS",
                        "description": "Analisis y optimizacion de tu CV para sistemas de seguimiento de candidatos"
                    }
                }
            ]
        }
    }
    </script>
    @endif

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; background: #f8f9fa; }
        .container { max-width: 1100px; margin: 0 auto; padding: 0 20px; }
        nav { background: #1a1a2e; color: white; padding: 15px 0; }
        nav .container { display: flex; justify-content: space-between; align-items: center; }
        nav a { color: white; text-decoration: none; margin-left: 20px; }
        nav a:hover { text-decoration: underline; }
        .nav-brand { font-size: 1.3rem; font-weight: bold; }
        .btn { display: inline-block; padding: 10px 24px; border-radius: 6px; text-decoration: none; font-weight: 600; border: none; cursor: pointer; font-size: 1rem; }
        .btn-primary { background: #0066ff; color: white; }
        .btn-primary:hover { background: #0052cc; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-sm { padding: 6px 14px; font-size: 0.875rem; }
        .card { background: white; border-radius: 8px; padding: 24px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 4px; font-weight: 600; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem; }
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        footer { background: #1a1a2e; color: #aaa; padding: 30px 0; margin-top: 40px; text-align: center; }
        footer a { color: #ccc; }
        .main-content { min-height: 60vh; padding: 30px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f1f3f5; font-weight: 600; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; }
        .badge-draft { background: #e9ecef; color: #495057; }
        .badge-processing { background: #fff3cd; color: #856404; }
        .badge-preview_ready { background: #d1ecf1; color: #0c5460; }
        .badge-paid { background: #d4edda; color: #155724; }
        .badge-delivered { background: #cce5ff; color: #004085; }
        .badge-failed { background: #f8d7da; color: #721c24; }
        .score-badge { display: inline-block; width: 48px; height: 48px; line-height: 48px; text-align: center; border-radius: 50%; font-weight: bold; font-size: 1.1rem; color: white; }
        /* Admin sidebar */
        .admin-layout { display: flex; gap: 0; width: 100vw; margin-left: calc(-50vw + 50%); }
        .admin-sidebar { width: 230px; min-width: 230px; background: #1a1a2e; min-height: calc(100vh - 60px); padding: 20px 0; }
        .admin-sidebar a { display: block; color: #ccc; text-decoration: none; padding: 8px 20px; font-size: 0.9rem; border-left: 3px solid transparent; }
        .admin-sidebar a:hover { background: rgba(255,255,255,0.05); color: white; }
        .admin-sidebar a.active { background: rgba(255,255,255,0.1); color: white; border-left-color: #0066ff; font-weight: 600; }
        .admin-sidebar .sidebar-section { color: #666; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 16px 20px 4px; }
        .admin-main { flex: 1; min-width: 0; overflow-x: hidden; }
        .admin-main table { font-size: 0.88rem; }
        .admin-main table th, .admin-main table td { padding: 8px 10px; }
    </style>
    @yield('meta_extra')
    @php
        $__fbPixelId = \App\Models\Setting::getValue('meta_pixel_id', '');
        $__fbPixelOn = \App\Models\Setting::getValue('meta_pixel_enabled', '0') === '1';
        $__isAdmin = request()->is('admin', 'admin/*');
    @endphp
    @if($__fbPixelOn && $__fbPixelId && !$__isAdmin)
    <!-- Meta Pixel Code -->
    <script>
    !function(f,b,e,v,n,t,s)
    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window, document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
    fbq('consent', 'grant');
    fbq('dataProcessingOptions', []);
    fbq('init', {!! json_encode($__fbPixelId) !!});
    fbq('track', 'PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none"
    src="https://www.facebook.com/tr?id={{ urlencode($__fbPixelId) }}&ev=PageView&noscript=1"
    /></noscript>
    <!-- End Meta Pixel Code -->
    @endif
</head>
<body>
    <nav>
        <div class="container">
            <a href="{{ route('home') }}" class="nav-brand">CV Optimizer ATS</a>
            <button class="nav-toggle" onclick="document.querySelector('.nav-links').classList.toggle('open')" aria-label="Menu">&#9776;</button>
            <div class="nav-links">
                <a href="{{ route('upload.form') }}">Optimizar CV</a>
                <a href="{{ route('how-it-works') }}">Como Funciona</a>
                <a href="{{ route('faq') }}">FAQ</a>
                <a href="{{ route('blog.index') }}">Blog</a>
                @auth
                    @if(auth()->user()->is_admin)
                        <a href="{{ route('admin.dashboard') }}">Admin</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}" style="display:inline">
                        @csrf
                        <button type="submit" style="background:none;border:none;color:white;cursor:pointer;margin-left:20px;">Salir</button>
                    </form>
                @endauth
            </div>
        </div>
    </nav>

    <div class="main-content">
        @hasSection('full_width')
            @yield('full_width')
        @else
            <div class="container">
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if(session('info'))
                    <div class="alert alert-info">{{ session('info') }}</div>
                @endif
                @if($errors->any())
                    <div class="alert alert-error">
                        <ul style="margin:0;padding-left:20px;">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
        @endif
    </div>

    <footer>
        <div class="container">
            <p>&copy; {{ date('Y') }} CV Optimizer ATS. Optimiza tu curriculum para sistemas de seguimiento de candidatos.</p>
            <p style="margin-top:8px;">
                <a href="{{ route('home') }}">Inicio</a> |
                <a href="{{ route('how-it-works') }}">Como Funciona</a> |
                <a href="{{ route('faq') }}">FAQ</a> |
                <a href="{{ route('blog.index') }}">Blog</a>
            </p>
        </div>
    </footer>
    @stack('fb_events')
</body>
</html>
