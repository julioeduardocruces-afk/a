@extends('layouts.app')
@section('title', 'Subir CV')
@push('fb_events')
<script>if(typeof fbq==='function')fbq('track','ViewContent',{content_name:'CV Upload Page',content_category:'ATS Optimization'});</script>
@endpush
@section('content')
<div style="max-width:600px;margin:30px auto;">
    <h1 style="margin-bottom:8px;">Paso 1: Sube tu CV</h1>
    <p style="color:#666;margin-bottom:20px;">Sube tu CV y nosotros lo optimizamos para sistemas ATS. Sin registro necesario.</p>
    <div class="card">
        <form method="POST" action="{{ route('upload.store') }}" enctype="multipart/form-data" id="upload-form">
            @csrf
            <div class="form-group">
                <label for="cv_file">Archivo CV (PDF o DOCX, max 10 MB)</label>
                <input type="file" id="cv_file" name="cv_file" accept=".pdf,.docx" required>
            </div>
            <p style="font-size:0.9rem;color:#666;margin-bottom:16px;">
                Formatos aceptados: PDF, DOCX. Archivos DOC antiguos no son compatibles.
                El archivo se almacena de forma segura y privada.
            </p>
            <button type="submit" class="btn btn-primary" style="width:100%;">Subir CV</button>
        </form>
    </div>
    <div class="card" style="text-align:center;background:#f0f7ff;border:1px dashed #0066ff;">
        <p style="margin-bottom:8px;font-weight:600;">No tienes tu CV en archivo?</p>
        <a href="{{ route('cv-builder.form') }}" class="btn btn-success">Crear CV desde Formulario</a>
        <p style="font-size:0.85rem;color:#666;margin-top:8px;">Completa tus datos y nosotros generamos tu CV optimizado.</p>
    </div>
    <div style="margin-top:16px;text-align:center;">
        <a href="{{ route('home') }}">Volver al inicio</a>
    </div>
</div>
@endsection
