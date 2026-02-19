@extends('layouts.admin')
@section('title', 'Admin - Categorias del Blog')
@section('admin-content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <h1>Categorias del Blog</h1>
    <a href="{{ route('admin.blog.categories.create') }}" class="btn btn-primary">+ Nueva Categoria</a>
</div>

<div class="card" style="overflow-x:auto;">
    <table>
        <thead>
            <tr>
                <th style="width:40px;">Orden</th>
                <th>Color</th>
                <th>Nombre</th>
                <th>Slug</th>
                <th>Articulos</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($categories as $category)
            <tr>
                <td style="text-align:center;">{{ $category->sort_order }}</td>
                <td>
                    <span style="display:inline-block;width:24px;height:24px;border-radius:4px;background:{{ $category->color }};vertical-align:middle;"></span>
                </td>
                <td style="font-weight:500;">{{ $category->name }}</td>
                <td style="font-size:12px;color:#888;">{{ $category->slug }}</td>
                <td style="text-align:center;">{{ $category->posts_count }}</td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <a href="{{ route('admin.blog.categories.edit', $category->id) }}" class="btn btn-sm btn-primary">Editar</a>
                        <form method="POST" action="{{ route('admin.blog.categories.destroy', $category->id) }}" onsubmit="return confirm('Eliminar esta categoria? Los articulos asociados quedaran sin categoria.');" style="margin:0;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align:center;padding:40px;color:#888;">
                    No hay categorias. <a href="{{ route('admin.blog.categories.create') }}">Crear la primera</a>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
