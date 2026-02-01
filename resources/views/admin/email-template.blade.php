@extends('layouts.admin')
@section('title', 'Admin - Plantilla de Correo')
@section('admin-content')
<h1 style="margin-bottom:24px;">Plantilla de Correo - CV Optimizado</h1>

<p style="color:#666;margin-bottom:20px;">
    Edita el contenido del correo que reciben los clientes cuando su CV optimizado esta listo.
    Los cambios se aplican inmediatamente a los proximos envios.
</p>

@if($errors->any())
    <div style="background:#f8d7da;color:#721c24;padding:12px;border-radius:6px;margin-bottom:16px;">
        @foreach($errors->all() as $error)
            <p style="margin:0;">{{ $error }}</p>
        @endforeach
    </div>
@endif

<form method="POST" action="{{ route('admin.email-template.update') }}">
    @csrf

    {{-- Subject --}}
    <div class="card" style="margin-bottom:20px;">
        <h3 style="margin-bottom:12px;">Asunto del correo</h3>
        <div class="form-group">
            <label for="email_subject">Asunto (se agrega automaticamente " - #ID" al final)</label>
            <input type="text" id="email_subject" name="email_subject"
                   value="{{ old('email_subject', $fields['email_subject']) }}"
                   style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;"
                   maxlength="200" required>
        </div>
    </div>

    {{-- Intro --}}
    <div class="card" style="margin-bottom:20px;">
        <h3 style="margin-bottom:12px;">Texto de introduccion</h3>
        <p style="color:#888;font-size:13px;margin-bottom:8px;">Se muestra justo despues del saludo "Hola [nombre],"</p>
        <div class="form-group">
            <textarea id="email_intro" name="email_intro" rows="4"
                      style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;font-family:inherit;"
                      required>{{ old('email_intro', $fields['email_intro']) }}</textarea>
        </div>
    </div>

    {{-- ATS Section --}}
    <div class="card" style="margin-bottom:20px;">
        <h3 style="margin-bottom:12px;">Seccion: Explicacion ATS</h3>
        <p style="color:#888;font-size:13px;margin-bottom:8px;">Explica al usuario que significa la optimizacion ATS y que incluye su CV.</p>
        <div class="form-group" style="margin-bottom:12px;">
            <label for="email_section_ats_title">Titulo de la seccion</label>
            <input type="text" id="email_section_ats_title" name="email_section_ats_title"
                   value="{{ old('email_section_ats_title', $fields['email_section_ats_title']) }}"
                   style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;"
                   maxlength="200" required>
        </div>
        <div class="form-group">
            <label for="email_section_ats_body">Contenido (usa saltos de linea para separar parrafos)</label>
            <textarea id="email_section_ats_body" name="email_section_ats_body" rows="8"
                      style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;font-family:inherit;"
                      required>{{ old('email_section_ats_body', $fields['email_section_ats_body']) }}</textarea>
        </div>
    </div>

    {{-- Tips Section --}}
    <div class="card" style="margin-bottom:20px;">
        <h3 style="margin-bottom:12px;">Seccion: Recomendaciones</h3>
        <p style="color:#888;font-size:13px;margin-bottom:8px;">Consejos practicos para que el usuario aproveche al maximo su CV optimizado.</p>
        <div class="form-group" style="margin-bottom:12px;">
            <label for="email_section_tips_title">Titulo de la seccion</label>
            <input type="text" id="email_section_tips_title" name="email_section_tips_title"
                   value="{{ old('email_section_tips_title', $fields['email_section_tips_title']) }}"
                   style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;"
                   maxlength="200" required>
        </div>
        <div class="form-group">
            <label for="email_section_tips_body">Contenido</label>
            <textarea id="email_section_tips_body" name="email_section_tips_body" rows="14"
                      style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;font-family:inherit;"
                      required>{{ old('email_section_tips_body', $fields['email_section_tips_body']) }}</textarea>
        </div>
    </div>

    {{-- Footer --}}
    <div class="card" style="margin-bottom:20px;">
        <h3 style="margin-bottom:12px;">Texto de cierre</h3>
        <div class="form-group">
            <textarea id="email_footer" name="email_footer" rows="3"
                      style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;font-family:inherit;"
                      required>{{ old('email_footer', $fields['email_footer']) }}</textarea>
        </div>
    </div>

    {{-- Actions --}}
    <div style="display:flex;gap:12px;align-items:center;">
        <button type="submit" class="btn btn-primary"
                style="padding:12px 32px;background:#0066ff;color:white;border:none;border-radius:8px;font-size:15px;font-weight:bold;cursor:pointer;">
            Guardar Cambios
        </button>
        <button type="submit" name="action" value="reset" class="btn"
                style="padding:12px 24px;background:#dc3545;color:white;border:none;border-radius:8px;font-size:14px;cursor:pointer;"
                onclick="return confirm('Esto restaurara todos los campos a los valores originales. Continuar?');">
            Restaurar Valores Originales
        </button>
        <button type="submit" name="action" value="preview" class="btn"
                style="padding:12px 24px;background:#6c757d;color:white;border:none;border-radius:8px;font-size:14px;cursor:pointer;"
                formtarget="_blank">
            Vista Previa
        </button>
    </div>
</form>
@endsection
