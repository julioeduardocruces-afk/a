@extends('layouts.admin')
@section('title', 'Admin - Blog')
@section('admin-content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <h1>Gestion de Blog</h1>
    <a href="{{ route('admin.blog.create') }}" class="btn btn-primary">+ Nuevo Articulo</a>
</div>

{{-- Filters --}}
<div class="card" style="margin-bottom:16px;">
    <form method="GET" action="{{ route('admin.blog.index') }}" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;">
        <div class="form-group" style="margin-bottom:0;">
            <label>Buscar</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Titulo o contenido..." style="width:200px;">
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label>Estado</label>
            <select name="status">
                <option value="">Todos</option>
                <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Borrador</option>
                <option value="published" {{ request('status') === 'published' ? 'selected' : '' }}>Publicado</option>
            </select>
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label>Categoria</label>
            <select name="category">
                <option value="">Todas</option>
                @foreach($categories as $key => $label)
                    <option value="{{ $key }}" {{ request('category') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
        @if(request()->hasAny(['search', 'status', 'category']))
            <a href="{{ route('admin.blog.index') }}" class="btn btn-sm btn-secondary">Limpiar</a>
        @endif
    </form>
</div>

{{-- Posts Table --}}
<div class="card" style="overflow-x:auto;">
    <table>
        <thead>
            <tr>
                <th style="width:50px;">Imagen</th>
                <th>Titulo</th>
                <th>Categoria</th>
                <th>Estado</th>
                <th>Vistas</th>
                <th>Fecha</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($posts as $post)
            <tr>
                <td>
                    @if($post->featured_image_url)
                        <img src="{{ $post->featured_image_url }}" alt="" style="width:50px;height:35px;object-fit:cover;border-radius:4px;">
                    @else
                        <div style="width:50px;height:35px;background:#e9ecef;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:10px;">
                            Sin img
                        </div>
                    @endif
                </td>
                <td>
                    <a href="{{ route('admin.blog.edit', $post->id) }}" style="color:#0066ff;text-decoration:none;font-weight:500;">
                        {{ Str::limit($post->title, 50) }}
                    </a>
                    <div style="font-size:11px;color:#888;">{{ $post->slug }}</div>
                </td>
                <td>
                    <span style="background:#e9ecef;padding:2px 8px;border-radius:4px;font-size:12px;">
                        {{ $post->category_label }}
                    </span>
                </td>
                <td>
                    @if($post->status === 'published')
                        <span class="badge" style="background:#d4edda;color:#155724;">Publicado</span>
                    @else
                        <span class="badge" style="background:#fff3cd;color:#856404;">Borrador</span>
                    @endif
                </td>
                <td style="text-align:center;">{{ number_format($post->views_count) }}</td>
                <td style="font-size:12px;">
                    {{ $post->published_at ? $post->published_at->format('d/m/Y') : '-' }}
                </td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <a href="{{ route('admin.blog.edit', $post->id) }}" class="btn btn-sm btn-primary">Editar</a>
                        @if($post->status === 'published')
                            <a href="{{ route('blog.show', $post->slug) }}" target="_blank" class="btn btn-sm btn-secondary">Ver</a>
                        @endif
                        <form method="POST" action="{{ route('admin.blog.destroy', $post->id) }}" onsubmit="return confirm('Eliminar este articulo?');" style="margin:0;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="text-align:center;padding:40px;color:#888;">
                    No hay articulos. <a href="{{ route('admin.blog.create') }}">Crear el primero</a>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $posts->appends(request()->query())->links() }}
@endsection
