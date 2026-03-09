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

    <!-- Sitemap -->
    <link rel="sitemap" type="application/xml" title="Sitemap" href="{{ route('sitemap') }}">

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
        .main-content { min-height: 60vh; }
        .main-content-padded { padding: 30px 0; }
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

    <div class="main-content{{ !View::hasSection('full_width') ? ' main-content-padded' : '' }}">
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

                @if(session('extraction_debug'))
                    <details style="margin:15px 0;background:#1e1e1e;color:#d4d4d4;border-radius:8px;padding:15px;font-family:monospace;font-size:13px;">
                        <summary style="cursor:pointer;color:#ff6b6b;font-weight:bold;font-size:14px;">DEBUG: Detalle del error de extraccion (click para expandir)</summary>
                        <table style="width:100%;margin-top:12px;border-collapse:collapse;">
                            @foreach(session('extraction_debug') as $key => $value)
                                <tr style="border-bottom:1px solid #333;">
                                    <td style="padding:6px 10px;color:#9cdcfe;white-space:nowrap;vertical-align:top;">{{ $key }}</td>
                                    <td style="padding:6px 10px;color:#ce9178;word-break:break-all;">
                                        @if(is_bool($value))
                                            <span style="color:{{ $value ? '#4ec9b0' : '#ff6b6b' }}">{{ $value ? 'true' : 'false' }}</span>
                                        @elseif(is_null($value))
                                            <span style="color:#666;">null</span>
                                        @else
                                            {{ $value }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </details>
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
            @php $__contactEmail = \App\Models\Setting::getValue('contact_email', ''); @endphp
            @if($__contactEmail)
            <p style="margin-top:10px;">
                <a href="mailto:{{ $__contactEmail }}" style="color:#ccc;">{{ $__contactEmail }}</a>
            </p>
            @endif
        </div>
    </footer>

    {{-- WhatsApp Widget --}}
    @php
        $__waNumber = \App\Models\Setting::getValue('whatsapp_number', '');
        $__waEnabled = \App\Models\Setting::getValue('whatsapp_enabled', '0') === '1';
        $__waMessage = \App\Models\Setting::getValue('whatsapp_message', '');
    @endphp
    @if($__waEnabled && $__waNumber && !($__isAdmin ?? false))
    <a href="https://wa.me/{{ $__waNumber }}{{ $__waMessage ? '?text=' . urlencode($__waMessage) : '' }}"
       target="_blank" rel="noopener"
       id="whatsapp-widget"
       title="Contactar por WhatsApp"
       style="position:fixed;bottom:24px;right:24px;width:60px;height:60px;background:#25d366;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(0,0,0,0.25);z-index:9999;transition:transform 0.2s,box-shadow 0.2s;text-decoration:none;">
        <svg viewBox="0 0 24 24" fill="white" style="width:32px;height:32px;">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
        </svg>
    </a>
    <style>
        #whatsapp-widget:hover { transform: scale(1.1); box-shadow: 0 6px 20px rgba(0,0,0,0.3); }
        @media (max-width: 640px) { #whatsapp-widget { width: 52px; height: 52px; bottom: 16px; right: 16px; } #whatsapp-widget svg { width: 28px; height: 28px; } }
    </style>
    @endif

    @stack('fb_events')
</body>
</html>
