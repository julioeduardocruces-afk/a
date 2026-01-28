@extends('layouts.app')
@section('title', 'Registro')
@section('content')
<div style="max-width:450px;margin:40px auto;">
    <div class="card">
        <h1 style="margin-bottom:20px;">Crear Cuenta</h1>
        <form method="POST" action="{{ route('register') }}">
            @csrf
            <div class="form-group">
                <label for="name">Nombre completo</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus maxlength="255">
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required maxlength="255">
            </div>
            <div class="form-group">
                <label for="password">Contrasena (min 8 caracteres, mayusculas, numeros)</label>
                <input type="password" id="password" name="password" required minlength="8">
            </div>
            <div class="form-group">
                <label for="password_confirmation">Confirmar contrasena</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Registrarse</button>
        </form>
        <p style="margin-top:16px;text-align:center;">
            Ya tienes cuenta? <a href="{{ route('login') }}">Iniciar sesion</a>
        </p>
    </div>
</div>
@endsection
