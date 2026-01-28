@extends('layouts.app')
@section('title', 'Subir CV')
@section('content')
<div style="max-width:600px;margin:30px auto;">
    <h1 style="margin-bottom:20px;">Subir tu CV</h1>
    <div class="card">
        <form method="POST" action="{{ route('upload.store') }}" enctype="multipart/form-data">
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
</div>
@endsection
