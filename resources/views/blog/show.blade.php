@extends('layouts.app')
@section('title', $post->seo_title)
@section('meta_description', $post->seo_description)
@section('meta_keywords', $post->meta_keywords ?: '')
@section('meta_type', 'article')
@if($post->featured_image_url)
@section('meta_image', $post->featured_image_url)
@endif

@section('schema_extra')
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $post->title,
    'description' => $post->seo_description,
    'image' => $post->featured_image_url ?: url('/images/og-default.jpg'),
    'datePublished' => $post->published_at?->toIso8601String(),
    'dateModified' => $post->updated_at->toIso8601String(),
    'author' => [
        '@type' => 'Organization',
        'name' => 'CV Optimizer ATS',
        'url' => url('/'),
    ],
    'publisher' => [
        '@type' => 'Organization',
        'name' => 'CV Optimizer ATS',
        'url' => url('/'),
    ],
    'mainEntityOfPage' => [
        '@type' => 'WebPage',
        '@id' => $post->url,
    ],
    'wordCount' => str_word_count(strip_tags($post->content)),
    'articleSection' => $post->category_label,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>

{{-- BreadcrumbList Schema --}}
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        [
            '@type' => 'ListItem',
            'position' => 1,
            'name' => 'Inicio',
            'item' => url('/'),
        ],
        [
            '@type' => 'ListItem',
            'position' => 2,
            'name' => 'Blog',
            'item' => route('blog.index'),
        ],
        [
            '@type' => 'ListItem',
            'position' => 3,
            'name' => $post->title,
            'item' => $post->url,
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endsection

@section('meta_extra')
<style>
    .article-header {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        color: white;
        padding: 40px 20px 60px;
    }
    .article-header-inner {
        max-width: 800px;
        margin: 0 auto;
    }
    .article-breadcrumb {
        font-size: 13px;
        opacity: 0.7;
        margin-bottom: 20px;
    }
    .article-breadcrumb a { color: white; text-decoration: none; }
    .article-breadcrumb a:hover { text-decoration: underline; }
    .article-category {
        display: inline-block;
        background: #0066ff;
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 16px;
    }
    @php $catColors = \App\Models\BlogCategory::getColorsArray(); @endphp
    @foreach($catColors as $catSlug => $catColor)
    .article-category.{{ $catSlug }} { background: {{ $catColor }}; }
    @endforeach

    .article-title {
        font-size: 2.5rem;
        font-weight: 800;
        line-height: 1.2;
        margin-bottom: 20px;
    }
    .article-meta {
        display: flex;
        align-items: center;
        gap: 24px;
        font-size: 14px;
        opacity: 0.8;
    }
    .article-meta span {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .article-meta svg { width: 16px; height: 16px; }

    .article-featured-image {
        max-width: 900px;
        margin: -40px auto 0;
        padding: 0 20px;
    }
    .article-featured-image img {
        width: 100%;
        height: auto;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }

    .article-content {
        max-width: 750px;
        margin: 0 auto;
        padding: 50px 20px;
        font-size: 1.1rem;
        line-height: 1.8;
        color: #333;
    }
    .article-content h2 {
        font-size: 1.8rem;
        font-weight: 700;
        color: #1a1a2e;
        margin: 40px 0 20px;
    }
    .article-content h3 {
        font-size: 1.4rem;
        font-weight: 600;
        color: #1a1a2e;
        margin: 32px 0 16px;
    }
    .article-content p { margin-bottom: 20px; }
    .article-content ul, .article-content ol {
        margin: 0 0 20px 24px;
    }
    .article-content li { margin-bottom: 8px; }
    .article-content a {
        color: #0066ff;
        text-decoration: underline;
    }
    .article-content img {
        max-width: 100%;
        height: auto;
        border-radius: 8px;
        margin: 24px 0;
    }
    .article-content blockquote {
        border-left: 4px solid #0066ff;
        padding: 16px 24px;
        margin: 24px 0;
        background: #f8f9fa;
        border-radius: 0 8px 8px 0;
        font-style: italic;
        color: #555;
    }
    .article-content pre {
        background: #1a1a2e;
        color: #e9ecef;
        padding: 20px;
        border-radius: 8px;
        overflow-x: auto;
        margin: 24px 0;
    }
    .article-content code {
        background: #f0f0f0;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 0.9em;
    }
    .article-content pre code {
        background: transparent;
        padding: 0;
    }

    /* Share & CTA */
    .article-footer {
        max-width: 750px;
        margin: 0 auto;
        padding: 0 20px 50px;
    }
    .article-share {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 24px 0;
        border-top: 1px solid #eee;
        border-bottom: 1px solid #eee;
    }
    .article-share span { font-weight: 600; color: #666; }
    .share-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        color: white;
        text-decoration: none;
        transition: transform 0.2s;
    }
    .share-btn:hover { transform: scale(1.1); }
    .share-btn svg { width: 20px; height: 20px; }
    .share-btn.twitter { background: #1da1f2; }
    .share-btn.facebook { background: #4267b2; }
    .share-btn.linkedin { background: #0077b5; }
    .share-btn.whatsapp { background: #25d366; }

    /* CTA Box */
    .article-cta {
        background: linear-gradient(135deg, #0066ff 0%, #0052cc 100%);
        color: white;
        border-radius: 16px;
        padding: 40px;
        text-align: center;
        margin: 40px 0;
    }
    .article-cta h3 { font-size: 1.5rem; margin-bottom: 12px; }
    .article-cta p { opacity: 0.9; margin-bottom: 24px; max-width: 500px; margin-left: auto; margin-right: auto; }
    .article-cta .btn {
        background: white;
        color: #0066ff;
        font-weight: 700;
        padding: 14px 32px;
        border-radius: 50px;
    }
    .article-cta .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.2); }

    /* Related Posts */
    .related-posts {
        background: #f8f9fa;
        padding: 60px 20px;
    }
    .related-posts-inner {
        max-width: 1100px;
        margin: 0 auto;
    }
    .related-posts h2 {
        text-align: center;
        font-size: 1.8rem;
        margin-bottom: 40px;
        color: #1a1a2e;
    }
    .related-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 24px;
    }
    .related-card {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        text-decoration: none;
        color: inherit;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        transition: transform 0.3s, box-shadow 0.3s;
    }
    .related-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 12px 30px rgba(0,0,0,0.12);
    }
    .related-card-image {
        height: 160px;
        overflow: hidden;
    }
    .related-card-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .related-card-image-placeholder {
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    .related-card-body {
        padding: 20px;
    }
    .related-card-title {
        font-size: 1rem;
        font-weight: 600;
        color: #1a1a2e;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .related-card-meta {
        font-size: 12px;
        color: #888;
        margin-top: 12px;
    }

    @media (max-width: 768px) {
        .article-title { font-size: 1.8rem; }
        .article-meta { flex-wrap: wrap; gap: 12px; }
        .article-content { font-size: 1rem; }
        .article-content h2 { font-size: 1.5rem; }
        .related-grid { grid-template-columns: 1fr; }
        .article-cta { padding: 30px 20px; }
    }
</style>
@endsection

@section('full_width')
{{-- Header --}}
<div class="article-header">
    <div class="article-header-inner">
        <div class="article-breadcrumb">
            <a href="{{ route('home') }}">Inicio</a> &rsaquo;
            <a href="{{ route('blog.index') }}">Blog</a> &rsaquo;
            {{ Str::limit($post->title, 40) }}
        </div>

        @if($post->category)
            <span class="article-category {{ $post->category }}">{{ $post->category_label }}</span>
        @endif

        <h1 class="article-title">{{ $post->title }}</h1>

        <div class="article-meta">
            <span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                {{ $post->formatted_date }}
            </span>
            <span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                {{ $post->reading_time }} min de lectura
            </span>
            <span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                {{ number_format($post->views_count) }} vistas
            </span>
        </div>
    </div>
</div>

{{-- Featured Image --}}
@if($post->featured_image_url)
<div class="article-featured-image">
    <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}">
</div>
@endif

{{-- Content --}}
<article class="article-content">
    {!! $post->content !!}
</article>

{{-- Footer --}}
<div class="article-footer">
    {{-- Share --}}
    <div class="article-share">
        <span>Compartir:</span>
        <a href="https://twitter.com/intent/tweet?url={{ urlencode($post->url) }}&text={{ urlencode($post->title) }}" target="_blank" rel="noopener" class="share-btn twitter" title="Compartir en Twitter">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"/></svg>
        </a>
        <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($post->url) }}" target="_blank" rel="noopener" class="share-btn facebook" title="Compartir en Facebook">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
        </a>
        <a href="https://www.linkedin.com/shareArticle?mini=true&url={{ urlencode($post->url) }}&title={{ urlencode($post->title) }}" target="_blank" rel="noopener" class="share-btn linkedin" title="Compartir en LinkedIn">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
        </a>
        <a href="https://wa.me/?text={{ urlencode($post->title . ' ' . $post->url) }}" target="_blank" rel="noopener" class="share-btn whatsapp" title="Compartir en WhatsApp">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        </a>
    </div>

    {{-- CTA --}}
    <div class="article-cta">
        <h3>Optimiza tu CV ahora</h3>
        <p>Aumenta tus posibilidades de conseguir entrevistas con un CV optimizado para sistemas ATS.</p>
        <a href="{{ route('upload.form') }}" class="btn">Optimizar mi CV</a>
    </div>
</div>

{{-- Related Posts --}}
@if($relatedPosts->count() > 0)
<div class="related-posts">
    <div class="related-posts-inner">
        <h2>Articulos Relacionados</h2>
        <div class="related-grid">
            @foreach($relatedPosts as $related)
            <a href="{{ $related->url }}" class="related-card">
                <div class="related-card-image">
                    @if($related->featured_image_url)
                        <img src="{{ $related->featured_image_url }}" alt="{{ $related->title }}" loading="lazy">
                    @else
                        <div class="related-card-image-placeholder"></div>
                    @endif
                </div>
                <div class="related-card-body">
                    <h3 class="related-card-title">{{ $related->title }}</h3>
                    <div class="related-card-meta">{{ $related->reading_time }} min de lectura</div>
                </div>
            </a>
            @endforeach
        </div>
    </div>
</div>
@endif
@endsection
