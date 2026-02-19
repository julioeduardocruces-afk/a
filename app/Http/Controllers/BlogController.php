<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $query = BlogPost::published()
            ->with('author')
            ->latest('published_at');

        // Category filter
        if ($request->filled('categoria')) {
            $query->where('category', $request->categoria);
        }

        $posts = $query->paginate(9);
        $categories = BlogPost::getCategories();

        // Get featured post (most recent)
        $featuredPost = null;
        if ($request->page <= 1 && !$request->filled('categoria')) {
            $featuredPost = $posts->first();
        }

        return view('blog.index', compact('posts', 'categories', 'featuredPost'));
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
