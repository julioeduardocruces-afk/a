@extends('layouts.admin')
@section('title', 'Admin - Credenciales')
@section('admin-content')
<h1 style="margin-bottom:24px;">Gestion de Credenciales</h1>

@if(session('success'))
    <div style="background:#d4edda;color:#155724;padding:12px;border-radius:6px;margin-bottom:16px;">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div style="background:#f8d7da;color:#721c24;padding:12px;border-radius:6px;margin-bottom:16px;">
        @foreach($errors->all() as $error)
            <p style="margin:0;">{{ $error }}</p>
        @endforeach
    </div>
@endif

{{-- ════════════════════════════════════════ --}}
{{-- CONFIGURACION DE IA                      --}}
{{-- ════════════════════════════════════════ --}}
<div class="card">
    <h3 style="margin-bottom:16px;">Configuracion de IA</h3>
    <form method="POST" action="{{ route('admin.credentials.store') }}">
        @csrf
        <input type="hidden" name="form_type" value="ai">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group">
                <label for="ai_provider">Proveedor de IA</label>
                <select name="ai_provider" id="ai_provider" onchange="toggleAiFields()">
                    <option value="openai" {{ old('ai_provider', $currentAiProvider ?? 'openai') === 'openai' ? 'selected' : '' }}>OpenAI (ChatGPT)</option>
                    <option value="gemini" {{ old('ai_provider', $currentAiProvider ?? '') === 'gemini' ? 'selected' : '' }}>Google Gemini</option>
                </select>
            </div>
            <div class="form-group">
                <label for="ai_model">Modelo</label>
                <select name="ai_model" id="ai_model">
                    @php $currentModel = old('ai_model', $currentAi['model'] ?? 'gpt-4o-mini'); @endphp
                    <option value="gpt-4o-mini" {{ $currentModel === 'gpt-4o-mini' ? 'selected' : '' }}>GPT-4o Mini (Recomendado)</option>
                    <option value="gpt-4o" {{ $currentModel === 'gpt-4o' ? 'selected' : '' }}>GPT-4o</option>
                    <option value="gpt-4-turbo" {{ $currentModel === 'gpt-4-turbo' ? 'selected' : '' }}>GPT-4 Turbo</option>
                    <option value="gemini-1.5-pro" {{ $currentModel === 'gemini-1.5-pro' ? 'selected' : '' }}>Gemini 1.5 Pro</option>
                    <option value="gemini-1.5-flash" {{ $currentModel === 'gemini-1.5-flash' ? 'selected' : '' }}>Gemini 1.5 Flash</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="ai_api_key">API Key</label>
            <input type="password" name="ai_api_key" id="ai_api_key" placeholder="{{ !empty($currentAi['api_key']) ? '••••••••••• (clave guardada)' : 'Tu clave API (se almacena encriptada)' }}" maxlength="500">
            <small style="color:#666;">Dejar vacio para mantener el valor actual</small>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group">
                <label for="ai_max_tokens">Maximo de Tokens</label>
                <input type="number" name="ai_max_tokens" id="ai_max_tokens" value="{{ old('ai_max_tokens', $currentAi['max_tokens'] ?? 1000) }}" min="100" max="8000">
            </div>
            <div class="form-group">
                <label for="ai_temperature">Temperatura</label>
                <input type="text" name="ai_temperature" id="ai_temperature" value="{{ old('ai_temperature', $currentAi['temperature'] ?? '0.7') }}" placeholder="0 = enfocado, 2 = creativo">
                <small style="color:#666;">0 = enfocado, 2 = creativo</small>
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Guardar IA</button>
    </form>
</div>

{{-- ════════════════════════════════════════ --}}
{{-- CONFIGURACION DEL PROMPT IA              --}}
{{-- ════════════════════════════════════════ --}}
<div class="card">
    <h3 style="margin-bottom:16px;">Prompt del Sistema (IA)</h3>
    <p style="color:#666;margin-bottom:12px;">Este es el prompt que se envia a la IA para optimizar los CVs. Puedes personalizarlo segun tus necesidades. Si lo dejas vacio se usara el prompt por defecto.</p>
    <form method="POST" action="{{ route('admin.credentials.store') }}">
        @csrf
        <input type="hidden" name="form_type" value="prompt">
        <div class="form-group">
            <label for="system_prompt">Prompt del Sistema</label>
            <textarea name="system_prompt" id="system_prompt" rows="14" style="width:100%;font-family:monospace;font-size:0.85rem;line-height:1.4;">{{ old('system_prompt', $systemPrompt ?? '') }}</textarea>
        </div>
        <div style="display:flex;gap:12px;align-items:center;">
            <button type="submit" class="btn btn-primary btn-sm">Guardar Prompt</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="restoreDefaultPrompt()">Restaurar por Defecto</button>
        </div>
    </form>
</div>

{{-- ════════════════════════════════════════ --}}
{{-- CONFIGURACION DE PASARELAS DE PAGO       --}}
{{-- ════════════════════════════════════════ --}}
<div class="card">
    <h3 style="margin-bottom:16px;">Configuracion de Pasarelas de Pago</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
        {{-- Flow --}}
        <div style="border:1px solid #ddd;border-radius:8px;padding:16px;">
            <h4 style="margin-bottom:12px;">Flow</h4>
            <form method="POST" action="{{ route('admin.credentials.store') }}">
                @csrf
                <input type="hidden" name="form_type" value="flow">
                <div class="form-group" style="text-align:center;">
                    <label><input type="checkbox" name="flow_enabled" value="1" {{ old('flow_enabled', $flowEnabled) ? 'checked' : '' }}> Habilitado</label>
                </div>
                <div class="form-group">
                    <label for="flow_environment">Ambiente</label>
                    <select name="flow_environment" id="flow_environment">
                        @php $currentEnv = old('flow_environment', $currentFlow['environment'] ?? 'sandbox'); @endphp
                        <option value="sandbox" {{ $currentEnv === 'sandbox' ? 'selected' : '' }}>Sandbox (Testing)</option>
                        <option value="production" {{ $currentEnv === 'production' ? 'selected' : '' }}>Produccion</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="flow_api_key">API Key</label>
                    <input type="text" name="flow_api_key" id="flow_api_key" placeholder="{{ !empty($currentFlow['api_key']) ? '••••• (clave guardada)' : 'Ej: 27F2A61F-82D3-...' }}" maxlength="500">
                    <small style="color:#666;">Dejar vacio para mantener el valor actual</small>
                </div>
                <div class="form-group">
                    <label for="flow_secret_key">Secret Key</label>
                    <input type="password" name="flow_secret_key" id="flow_secret_key" placeholder="{{ !empty($currentFlow['secret_key']) ? '••••• (clave guardada)' : 'Tu Secret Key' }}" maxlength="500">
                    <small style="color:#666;">Dejar vacio para mantener el valor actual</small>
                </div>
                <button type="submit" class="btn btn-primary btn-sm" style="width:100%;">Guardar Flow</button>
            </form>
        </div>

        {{-- Webpay (placeholder) --}}
        <div style="border:1px solid #ddd;border-radius:8px;padding:16px;opacity:0.5;">
            <h4 style="margin-bottom:12px;">Webpay Plus (Transbank)</h4>
            <p style="color:#666;text-align:center;padding:40px 0;">Proximamente</p>
        </div>
    </div>
</div>

{{-- ════════════════════════════════════════ --}}
{{-- CONFIGURACION SMTP                       --}}
{{-- ════════════════════════════════════════ --}}
<div class="card">
    <h3 style="margin-bottom:16px;">Configuracion SMTP (Email)</h3>
    <p style="color:#666;margin-bottom:12px;">La configuracion SMTP se define en el archivo <code>.env</code> durante la instalacion. Para cambiarla, edita las variables <code>MAIL_*</code> en el archivo <code>.env</code> del servidor.</p>
    <div style="background:#f8f9fa;padding:12px;border-radius:6px;">
        <table style="font-size:0.9rem;">
            <tr><td style="padding:4px 12px;font-weight:600;">MAIL_HOST</td><td>{{ config('mail.mailers.smtp.host', '-') }}</td></tr>
            <tr><td style="padding:4px 12px;font-weight:600;">MAIL_PORT</td><td>{{ config('mail.mailers.smtp.port', '-') }}</td></tr>
            <tr><td style="padding:4px 12px;font-weight:600;">MAIL_USERNAME</td><td>{{ config('mail.mailers.smtp.username') ? Str::mask(config('mail.mailers.smtp.username'), '*', 3) : '-' }}</td></tr>
            <tr><td style="padding:4px 12px;font-weight:600;">MAIL_ENCRYPTION</td><td>{{ config('mail.mailers.smtp.encryption', '-') }}</td></tr>
            <tr><td style="padding:4px 12px;font-weight:600;">MAIL_FROM</td><td>{{ config('mail.from.address', '-') }}</td></tr>
        </table>
    </div>
</div>

{{-- ════════════════════════════════════════ --}}
{{-- CREDENCIALES EXISTENTES                  --}}
{{-- ════════════════════════════════════════ --}}
<div class="card">
    <h3 style="margin-bottom:12px;">Credenciales Existentes</h3>
    @if($credentials->isEmpty())
        <p style="color:#999;text-align:center;padding:20px;">No hay credenciales registradas. Agrega una arriba.</p>
    @else
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
                    <form method="POST" action="{{ route('admin.credentials.destroy', $cred->id) }}" onsubmit="return confirm('Seguro que deseas eliminar esta credencial?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>

<script>
function toggleAiFields() {
    var provider = document.getElementById('ai_provider').value;
    var modelSelect = document.getElementById('ai_model');

    for (var i = 0; i < modelSelect.options.length; i++) {
        var opt = modelSelect.options[i];
        var isOpenai = opt.value.startsWith('gpt-');
        var isGemini = opt.value.startsWith('gemini-');
        opt.style.display = (provider === 'openai' && isOpenai) || (provider === 'gemini' && isGemini) ? '' : 'none';
    }
    for (var i = 0; i < modelSelect.options.length; i++) {
        if (modelSelect.options[i].style.display !== 'none') {
            modelSelect.selectedIndex = i;
            break;
        }
    }
}
toggleAiFields();

function restoreDefaultPrompt() {
    if (!confirm('Restaurar el prompt por defecto? Se perdera el prompt actual al guardar.')) return;
    document.getElementById('system_prompt').value = @json($defaultPrompt ?? '');
}
</script>
@endsection
