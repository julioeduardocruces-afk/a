<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach($urls as $index => $url)
    <url>
        <loc>{{ $url }}</loc>
        <lastmod>{{ now()->format('Y-m-d') }}</lastmod>
        <changefreq>{{ $index === 0 ? 'daily' : 'weekly' }}</changefreq>
        <priority>{{ $index === 0 ? '1.0' : '0.8' }}</priority>
    </url>
    @endforeach
</urlset>
