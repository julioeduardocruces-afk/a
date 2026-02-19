<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BlogCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminBlogCategoryController extends Controller
{
    public function index()
    {
        $categories = BlogCategory::ordered()
            ->withCount('posts')
            ->get();

        return view('admin.blog.categories.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.blog.categories.form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['slug'] = BlogCategory::generateUniqueSlug($validated['name']);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $category = BlogCategory::create($validated);

        BlogCategory::clearCache();

        AuditLog::record('admin.blog_category_created', $request->user()->id, 'admin', [
            'category_id' => $category->id,
            'slug' => $category->slug,
        ], $request->ip());

        return redirect()->route('admin.blog.categories.index')
            ->with('success', 'Categoria creada exitosamente.');
    }

    public function edit(int $id)
    {
        $category = BlogCategory::findOrFail($id);

        return view('admin.blog.categories.form', compact('category'));
    }

    public function update(Request $request, int $id)
    {
        $category = BlogCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', Rule::unique('blog_categories', 'slug')->ignore($category->id)],
            'color' => ['required', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $oldSlug = $category->slug;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $category->update($validated);

        // Update blog posts that use the old slug
        if ($oldSlug !== $validated['slug']) {
            \App\Models\BlogPost::where('category', $oldSlug)
                ->update(['category' => $validated['slug']]);
        }

        BlogCategory::clearCache();

        AuditLog::record('admin.blog_category_updated', $request->user()->id, 'admin', [
            'category_id' => $category->id,
            'old_slug' => $oldSlug,
            'new_slug' => $category->slug,
        ], $request->ip());

        return back()->with('success', 'Categoria actualizada exitosamente.');
    }

    public function destroy(Request $request, int $id)
    {
        $category = BlogCategory::findOrFail($id);

        // Nullify category on associated posts
        \App\Models\BlogPost::where('category', $category->slug)
            ->update(['category' => null]);

        $slug = $category->slug;
        $category->delete();

        BlogCategory::clearCache();

        AuditLog::record('admin.blog_category_deleted', $request->user()->id, 'admin', [
            'category_id' => $id,
            'slug' => $slug,
        ], $request->ip());

        return redirect()->route('admin.blog.categories.index')
            ->with('success', 'Categoria eliminada. Los articulos asociados quedaron sin categoria.');
    }
}
