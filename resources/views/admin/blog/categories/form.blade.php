@extends('layouts.admin')
@section('title', isset($category) ? 'Editar Categoria' : 'Nueva Categoria')
@section('admin-content')
<div style="margin-bottom:20px;">
    <a href="{{ route('admin.blog.categories.index') }}" style="color:#666;text-decoration:none;font-size:13px;">&larr; Volver a categorias</a>
    <h1 style="margin-top:8px;">{{ isset($category) ? 'Editar Categoria' : 'Nueva Categoria' }}</h1>
</div>

<div class="card" style="max-width:600px;">
    <form method="POST" action="{{ isset($category) ? route('admin.blog.categories.update', $category->id) : route('admin.blog.categories.store') }}">
        @csrf
        @if(isset($category))
            @method('PUT')
        @endif

        <div class="form-group">
            <label for="name">Nombre</label>
            <input type="text" name="name" id="name" value="{{ old('name', $category->name ?? '') }}" required maxlength="255" placeholder="Ej: Consejos de CV">
            @error('name')
                <div style="color:#dc3545;font-size:12px;margin-top:4px;">{{ $message }}</div>
            @enderror
        </div>

        @if(isset($category))
        <div class="form-group">
            <label for="slug">Slug (URL)</label>
            <input type="text" name="slug" id="slug" value="{{ old('slug', $category->slug ?? '') }}" required maxlength="100">
            <small style="color:#666;">Se usa en las URLs y como identificador interno.</small>
            @error('slug')
                <div style="color:#dc3545;font-size:12px;margin-top:4px;">{{ $message }}</div>
            @enderror
        </div>
        @endif

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group">
                <label for="color">Color</label>
                <div style="display:flex;align-items:center;gap:8px;">
                    <input type="color" name="color" id="color" value="{{ old('color', $category->color ?? '#0066ff') }}" style="width:50px;height:38px;padding:2px;border:1px solid #ddd;border-radius:4px;cursor:pointer;">
                    <input type="text" id="color-hex" value="{{ old('color', $category->color ?? '#0066ff') }}" maxlength="7" style="width:100px;" readonly>
                </div>
                @error('color')
                    <div style="color:#dc3545;font-size:12px;margin-top:4px;">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label for="sort_order">Orden</label>
                <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}" min="0">
                <small style="color:#666;">Menor = aparece primero.</small>
            </div>
        </div>

        <div style="margin-top:8px;">
            <label>Vista previa:</label>
            <div style="margin-top:6px;">
                <span id="preview-badge" style="display:inline-block;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:white;background:{{ old('color', $category->color ?? '#0066ff') }};">
                    {{ old('name', $category->name ?? 'Nombre') }}
                </span>
            </div>
        </div>

        <div style="margin-top:20px;">
            <button type="submit" class="btn btn-primary">
                {{ isset($category) ? 'Guardar Cambios' : 'Crear Categoria' }}
            </button>
        </div>
    </form>
</div>

<script>
var colorInput = document.getElementById('color');
var colorHex = document.getElementById('color-hex');
var nameInput = document.getElementById('name');
var previewBadge = document.getElementById('preview-badge');

colorInput.addEventListener('input', function() {
    colorHex.value = this.value;
    previewBadge.style.background = this.value;
});

nameInput.addEventListener('input', function() {
    previewBadge.textContent = this.value || 'Nombre';
});
</script>
@endsection
