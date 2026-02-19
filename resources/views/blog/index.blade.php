@extends('layouts.app')
@section('title', 'Blog')
@section('meta_description', 'Consejos, guias y recursos para optimizar tu CV, superar sistemas ATS y conseguir el trabajo que deseas en Chile.')
@section('meta_keywords', 'blog CV, consejos curriculum, tips entrevistas, busqueda empleo Chile, ATS tips')

@section('meta_extra')
<style>
    .blog-hero {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        color: white;
        padding: 60px 20px;
        text-align: center;
    }
    .blog-hero h1 { font-size: 2.5rem; margin-bottom: 12px; }
    .blog-hero p { opacity: 0.8; font-size: 1.1rem; max-width: 600px; margin: 0 auto; }

    .blog-content { background: #f8f9fa; padding: 50px 20px; min-height: 60vh; }
    .blog-container { max-width: 1200px; margin: 0 auto; }

    /* Category Filter */
    .blog-filters {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: center;
        margin-bottom: 40px;
    }
    .filter-btn {
        padding: 8px 20px;
        border-radius: 50px;
        background: white;
        color: #333;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        transition: all 0.2s;
        border: 2px solid transparent;
    }
    .filter-btn:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); transform: translateY(-1px); }
    .filter-btn.active { background: #0066ff; color: white; }

    /* Post Cards Grid */
    .posts-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 30px;
    }

    /* Post Card */
    .post-card {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        text-decoration: none;
        color: inherit;
        display: flex;
        flex-direction: column;
    }
    .post-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 40px rgba(0,0,0,0.15);
    }

    .post-card-image {
        position: relative;
        width: 100%;
        height: 200px;
        overflow: hidden;
    }
    .post-card-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }
    .post-card:hover .post-card-image img {
        transform: scale(1.05);
    }
    .post-card-image-placeholder {
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .post-card-image-placeholder svg {
        width: 60px;
        height: 60px;
        color: rgba(255,255,255,0.3);
    }

    .post-card-category {
        position: absolute;
        top: 16px;
        left: 16px;
        background: #0066ff;
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .post-card-category.cv-tips { background: #28a745; }
    .post-card-category.ats { background: #0066ff; }
    .post-card-category.busqueda-empleo { background: #f59e0b; }
    .post-card-category.entrevistas { background: #8b5cf6; }
    .post-card-category.carrera { background: #ec4899; }
    .post-card-category.noticias { background: #64748b; }

    .post-card-body {
        padding: 24px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .post-card-title {
        font-size: 1.2rem;
        font-weight: 700;
        color: #1a1a2e;
        margin-bottom: 12px;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .post-card-excerpt {
        color: #666;
        font-size: 0.95rem;
        line-height: 1.6;
        flex: 1;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        margin-bottom: 20px;
    }

    .post-card-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 16px;
        border-top: 1px solid #f0f0f0;
    }

    .post-card-meta {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 13px;
        color: #888;
    }
    .post-card-meta span {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .post-card-meta svg {
        width: 14px;
        height: 14px;
    }

    .post-card-read {
        color: #0066ff;
        font-weight: 600;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .post-card-read svg {
        width: 16px;
        height: 16px;
        transition: transform 0.2s;
    }
    .post-card:hover .post-card-read svg {
        transform: translateX(4px);
    }

    /* Featured Post */
    .featured-post {
        grid-column: span 3;
        display: grid;
        grid-template-columns: 1.2fr 1fr;
        gap: 0;
    }
    .featured-post .post-card-image {
        height: 100%;
        min-height: 350px;
        border-radius: 16px 0 0 16px;
    }
    .featured-post .post-card-body {
        padding: 40px;
        justify-content: center;
    }
    .featured-post .post-card-title {
        font-size: 1.8rem;
        -webkit-line-clamp: 3;
    }
    .featured-post .post-card-excerpt {
        font-size: 1rem;
        -webkit-line-clamp: 4;
    }

    /* Pagination */
    .blog-pagination {
        display: flex;
        justify-content: center;
        gap: 8px;
        margin-top: 50px;
    }
    .blog-pagination a, .blog-pagination span {
        padding: 10px 16px;
        background: white;
        border-radius: 8px;
        text-decoration: none;
        color: #333;
        font-weight: 500;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .blog-pagination a:hover { background: #0066ff; color: white; }
    .blog-pagination .active { background: #0066ff; color: white; }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 80px 20px;
        color: #666;
    }
    .empty-state svg { width: 80px; height: 80px; color: #ddd; margin-bottom: 20px; }

    /* Responsive */
    @media (max-width: 1024px) {
        .posts-grid { grid-template-columns: repeat(2, 1fr); }
        .featured-post { grid-column: span 2; grid-template-columns: 1fr; }
        .featured-post .post-card-image { border-radius: 16px 16px 0 0; min-height: 250px; }
    }
    @media (max-width: 640px) {
        .posts-grid { grid-template-columns: 1fr; }
        .featured-post { grid-column: span 1; }
        .blog-hero h1 { font-size: 1.8rem; }
        .post-card-body { padding: 20px; }
        .featured-post .post-card-body { padding: 24px; }
        .featured-post .post-card-title { font-size: 1.4rem; }
    }
</style>
@endsection

@section('full_width')
{{-- Hero --}}
<div class="blog-hero">
    <h1>Blog</h1>
    <p>Consejos, guias y recursos para impulsar tu carrera profesional</p>
</div>

{{-- Content --}}
<div class="blog-content">
    <div class="blog-container">
        {{-- Category Filters --}}
        <div class="blog-filters">
            <a href="{{ route('blog.index') }}" class="filter-btn {{ !request('categoria') ? 'active' : '' }}">Todos</a>
            @foreach($categories as $key => $label)
                <a href="{{ route('blog.index', ['categoria' => $key]) }}" class="filter-btn {{ request('categoria') === $key ? 'active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>

        @if($posts->count() > 0)
        <div class="posts-grid">
            @foreach($posts as $index => $post)
                @if($index === 0 && $featuredPost && $posts->currentPage() === 1)
                {{-- Featured Post (first on page 1) --}}
                <a href="{{ $post->url }}" class="post-card featured-post">
                    <div class="post-card-image">
                        @if($post->featured_image_url)
                            <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}">
                        @else
                            <div class="post-card-image-placeholder">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                            </div>
                        @endif
                        @if($post->category)
                            <span class="post-card-category {{ $post->category }}">{{ $post->category_label }}</span>
                        @endif
                    </div>
                    <div class="post-card-body">
                        <h2 class="post-card-title">{{ $post->title }}</h2>
                        <p class="post-card-excerpt">{{ $post->excerpt ?: Str::limit(strip_tags($post->content), 200) }}</p>
                        <div class="post-card-footer">
                            <div class="post-card-meta">
                                <span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                                    {{ $post->formatted_date }}
                                </span>
                                <span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                                    {{ $post->reading_time }} min
                                </span>
                            </div>
                            <span class="post-card-read">
                                Leer mas
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                            </span>
                        </div>
                    </div>
                </a>
                @else
                {{-- Regular Post Card --}}
                <a href="{{ $post->url }}" class="post-card">
                    <div class="post-card-image">
                        @if($post->featured_image_url)
                            <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" loading="lazy">
                        @else
                            <div class="post-card-image-placeholder">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                            </div>
                        @endif
                        @if($post->category)
                            <span class="post-card-category {{ $post->category }}">{{ $post->category_label }}</span>
                        @endif
                    </div>
                    <div class="post-card-body">
                        <h2 class="post-card-title">{{ $post->title }}</h2>
                        <p class="post-card-excerpt">{{ $post->excerpt ?: Str::limit(strip_tags($post->content), 120) }}</p>
                        <div class="post-card-footer">
                            <div class="post-card-meta">
                                <span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                                    {{ $post->reading_time }} min
                                </span>
                            </div>
                            <span class="post-card-read">
                                Leer
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                            </span>
                        </div>
                    </div>
                </a>
                @endif
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($posts->hasPages())
        <div class="blog-pagination">
            {{ $posts->appends(request()->query())->links('pagination::simple-default') }}
        </div>
        @endif

        @else
        {{-- Empty State --}}
        <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V9a2 2 0 012-2h2a2 2 0 012 2v9a2 2 0 01-2 2h-2z"/></svg>
            <h3>No hay articulos disponibles</h3>
            <p>Pronto publicaremos contenido interesante para ti.</p>
        </div>
        @endif
    </div>
</div>
@endsection
