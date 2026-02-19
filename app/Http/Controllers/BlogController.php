<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        // Redirect old query-param URLs to the new friendly route
        if ($request->filled('categoria')) {
            return redirect()->route('blog.category', $request->categoria, 301);
        }

        $query = BlogPost::published()
            ->with('author')
            ->latest('published_at');

        $posts = $query->paginate(9);
        $categories = BlogPost::getCategories();

        $featuredPost = null;
        $activeCategory = null;
        if ($request->page <= 1) {
            $featuredPost = $posts->first();
        }

        return view('blog.index', compact('posts', 'categories', 'featuredPost', 'activeCategory'));
    }

    public function byCategory(Request $request, string $category)
    {
        $categories = BlogPost::getCategories();

        // 404 if category doesn't exist
        if (!array_key_exists($category, $categories)) {
            abort(404);
        }

        $query = BlogPost::published()
            ->with('author')
            ->where('category', $category)
            ->latest('published_at');

        $posts = $query->paginate(9);

        $featuredPost = null;
        $activeCategory = $category;

        return view('blog.index', compact('posts', 'categories', 'featuredPost', 'activeCategory'));
    }

    public function show(string $slug)
    {
        $post = BlogPost::where('slug', $slug)
            ->published()
            ->firstOrFail();

        // Increment view count
        $post->incrementViews();

        // Get related posts (same category, exclude current)
        $relatedPosts = BlogPost::published()
            ->where('id', '!=', $post->id)
            ->when($post->category, function ($q) use ($post) {
                $q->where('category', $post->category);
            })
            ->latest('published_at')
            ->limit(3)
            ->get();

        // If not enough related posts, fill with recent posts
        if ($relatedPosts->count() < 3) {
            $morePostsNeeded = 3 - $relatedPosts->count();
            $excludeIds = $relatedPosts->pluck('id')->push($post->id);

            $morePosts = BlogPost::published()
                ->whereNotIn('id', $excludeIds)
                ->latest('published_at')
                ->limit($morePostsNeeded)
                ->get();

            $relatedPosts = $relatedPosts->concat($morePosts);
        }

        return view('blog.show', compact('post', 'relatedPosts'));
    }
}
