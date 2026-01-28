@extends('layouts.app')
@section('title', 'Admin - Credenciales')
@section('content')
<h1 style="margin-bottom:24px;">Gestion de Credenciales</h1>

<div class="card">
    <h3 style="margin-bottom:12px;">Agregar Nueva Credencial</h3>
    <form method="POST" action="{{ route('admin.credentials.store') }}">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr 2fr;gap:12px;align-items:end;">
            <div class="form-group">
                <label for="provider">Proveedor</label>
                <select name="provider" id="provider" required>
                    <option value="openai">OpenAI</option>
                    <option value="gemini">Gemini</option>
                    <option value="flow">Flow (Webpay)</option>
                    <option value="smtp">SMTP Email</option>
                </select>
            </div>
            <div class="form-group">
                <label for="name">Nombre / Etiqueta</label>
                <input type="text" name="name" id="name" required placeholder="Ej: OpenAI Key #1" maxlength="255">
            </div>
            <div class="form-group">
                <label for="credentials">Credenciales (JSON)</label>
                <textarea name="credentials" id="credentials" rows="3" required
                    placeholder='{"api_key":"sk-...","model":"gpt-4o"}'></textarea>
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Guardar Credencial</button>
    </form>
    <details style="margin-top:12px;">
        <summary style="cursor:pointer;color:#666;">Formato JSON por proveedor</summary>
        <pre style="background:#f8f9fa;padding:12px;border-radius:4px;margin-top:8px;font-size:0.85rem;">
OpenAI:  {"api_key": "sk-...", "model": "gpt-4o"}
Gemini:  {"api_key": "AIza...", "model": "gemini-1.5-pro"}
Flow:    {"api_key": "...", "secret_key": "...", "api_url": "https://www.flow.cl/api"}
SMTP:    {"host": "smtp.example.com", "port": 587, "username": "...", "password": "...", "encryption": "tls"}
        </pre>
    </details>
</div>

<div class="card">
    <h3 style="margin-bottom:12px;">Credenciales Existentes</h3>
    <table>
        <thead>
            <tr><th>ID</th><th>Proveedor</th><th>Nombre</th><th>Estado</th><th>Usos</th><th>Ultimo Uso</th><th>Acciones</th></tr>
        </thead>
        <tbody>
            @foreach($credentials as $cred)
            <tr>
                <td>{{ $cred->id }}</td>
                <td><span class="badge badge-{{ $cred->is_active ? 'paid' : 'failed' }}">{{ $cred->provider }}</span></td>
                <td>{{ $cred->name }}</td>
                <td>{{ $cred->is_active ? 'Activa' : 'Inactiva' }}</td>
                <td>{{ $cred->usage_count }}</td>
                <td>{{ $cred->last_used_at?->format('d/m H:i') ?? '-' }}</td>
                <td style="display:flex;gap:4px;">
                    <form method="POST" action="{{ route('admin.credentials.toggle', $cred->id) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm {{ $cred->is_active ? 'btn-secondary' : 'btn-success' }}">
                            {{ $cred->is_active ? 'Desactivar' : 'Activar' }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.credentials.destroy', $cred->id) }}" onsubmit="return confirm('Seguro?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
