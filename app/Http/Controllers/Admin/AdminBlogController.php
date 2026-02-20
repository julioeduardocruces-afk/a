<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminBlogController extends Controller
{
    public function index(Request $request)
    {
        $query = BlogPost::with('author')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $posts = $query->paginate(20);
        $categories = BlogPost::getCategories();

        return view('admin.blog.index', compact('posts', 'categories'));
    }

    public function create()
    {
        $categories = BlogPost::getCategories();
        return view('admin.blog.form', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $this->validatePost($request);

        // Handle featured image upload
        if ($request->hasFile('featured_image')) {
            $validated['featured_image'] = $this->uploadImage($request->file('featured_image'));
        }

        // Generate slug
        $validated['slug'] = BlogPost::generateUniqueSlug($validated['title']);

        // Set author
        $validated['author_id'] = $request->user()->id;

        // Handle publish date
        if ($validated['status'] === 'published' && empty($validated['published_at'])) {
            $validated['published_at'] = now();
        }

        $post = BlogPost::create($validated);

        // Calculate reading time
        $post->updateReadingTime();

        return redirect()->route('admin.blog.edit', $post->id)
            ->with('success', 'Articulo creado exitosamente.');
    }

    public function edit(int $id)
    {
        $post = BlogPost::findOrFail($id);
        $categories = BlogPost::getCategories();

        return view('admin.blog.form', compact('post', 'categories'));
    }

    public function update(Request $request, int $id)
    {
        $post = BlogPost::findOrFail($id);

        $validated = $this->validatePost($request, $post->id);

        // Handle featured image upload
        if ($request->hasFile('featured_image')) {
            // Delete old image
            if ($post->featured_image) {
                Storage::disk('public')->delete($post->featured_image);
            }
            $validated['featured_image'] = $this->uploadImage($request->file('featured_image'));
        } elseif ($request->boolean('remove_image')) {
            if ($post->featured_image) {
                Storage::disk('public')->delete($post->featured_image);
            }
            $validated['featured_image'] = null;
        }

        // Update slug if title changed
        if ($request->filled('custom_slug') && $request->custom_slug !== $post->slug) {
            $validated['slug'] = BlogPost::generateUniqueSlug($request->custom_slug, $post->id);
        } elseif ($validated['title'] !== $post->title && !$request->filled('custom_slug')) {
            // Only auto-update slug if explicitly requested
            if ($request->boolean('update_slug')) {
                $validated['slug'] = BlogPost::generateUniqueSlug($validated['title'], $post->id);
            }
        }

        // Handle publish date
        if ($validated['status'] === 'published' && !$post->published_at) {
            $validated['published_at'] = now();
        }

        $post->update($validated);

        // Recalculate reading time
        $post->updateReadingTime();

        return back()->with('success', 'Articulo actualizado exitosamente.');
    }

    public function destroy(int $id)
    {
        $post = BlogPost::findOrFail($id);

        // Delete featured image
        if ($post->featured_image) {
            Storage::disk('public')->delete($post->featured_image);
        }

        $post->delete();

        return redirect()->route('admin.blog.index')
            ->with('success', 'Articulo eliminado.');
    }

    public function uploadEditorImage(Request $request)
    {
        $request->validate([
            'file' => ['required', 'image', 'max:5120', 'mimes:jpg,jpeg,png,gif,webp'],
        ]);

        $path = $request->file('file')->store('blog/editor', 'public');

        return response()->json([
            'location' => url('media/' . $path),
        ]);
    }

    private function validatePost(Request $request, ?int $excludeId = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'featured_image' => ['nullable', 'image', 'max:5120', 'mimes:jpg,jpeg,png,gif,webp'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
            'focus_keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'published_at' => ['nullable', 'date'],
        ]);
    }

    private function uploadImage($file): string
    {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        return $file->storeAs('blog/featured', $filename, 'public');
    }
}
