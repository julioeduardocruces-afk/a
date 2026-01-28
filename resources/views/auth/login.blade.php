@extends('layouts.app')
@section('title', 'Iniciar Sesion')
@section('content')
<div style="max-width:450px;margin:40px auto;">
    <div class="card">
        <h1 style="margin-bottom:20px;">Iniciar Sesion</h1>
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Contrasena</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="remember"> Recordarme
                </label>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Entrar</button>
        </form>

        <hr style="margin:20px 0;">
        <p style="text-align:center;margin-bottom:12px;">O accede con magic link:</p>
        <form method="POST" action="{{ route('magic-link.send') }}">
            @csrf
            <div class="form-group">
                <input type="email" name="email" placeholder="Tu email registrado" required>
            </div>
            <button type="submit" class="btn btn-secondary" style="width:100%;">Enviar enlace de acceso</button>
        </form>
        @if(session('status'))
            <div class="alert alert-success" style="margin-top:12px;">{{ session('status') }}</div>
        @endif

        <p style="margin-top:16px;text-align:center;">
            No tienes cuenta? <a href="{{ route('register') }}">Registrarse</a>
        </p>
    </div>
</div>
@endsection
