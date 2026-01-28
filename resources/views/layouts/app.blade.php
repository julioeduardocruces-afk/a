<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CV Optimizer ATS') - Optimiza tu CV</title>
    <meta name="description" content="@yield('meta_description', 'Optimiza tu CV para sistemas ATS. Mejora tus posibilidades en Laborum, ChileTrabajos y mas.')">
    @yield('meta_extra')
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
    </style>
</head>
<body>
    <nav>
        <div class="container">
            <a href="{{ route('home') }}" class="nav-brand">CV Optimizer ATS</a>
            <div>
                @auth
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <a href="{{ route('upload.form') }}">Subir CV</a>
                    @if(auth()->user()->is_admin)
                        <a href="{{ route('admin.dashboard') }}">Admin</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}" style="display:inline">
                        @csrf
                        <button type="submit" style="background:none;border:none;color:white;cursor:pointer;margin-left:20px;">Salir</button>
                    </form>
                @else
                    <a href="{{ route('login') }}">Iniciar Sesion</a>
                    <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Registrarse</a>
                @endauth
            </div>
        </div>
    </nav>

    <div class="main-content">
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
    </div>

    <footer>
        <div class="container">
            <p>&copy; {{ date('Y') }} CV Optimizer ATS. Optimiza tu curriculum para sistemas de seguimiento de candidatos.</p>
            <p style="margin-top:8px;">
                <a href="{{ route('home') }}">Inicio</a> |
                <a href="{{ route('how-it-works') }}">Como Funciona</a> |
                <a href="{{ route('faq') }}">FAQ</a>
            </p>
        </div>
    </footer>
</body>
</html>
