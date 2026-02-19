<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach($urls as $item)
    <url>
        <loc>{{ is_array($item) ? $item['url'] : $item }}</loc>
        <lastmod>{{ is_array($item) && isset($item['lastmod']) ? $item['lastmod'] : now()->format('Y-m-d') }}</lastmod>
        <changefreq>{{ is_array($item) && isset($item['changefreq']) ? $item['changefreq'] : 'weekly' }}</changefreq>
        <priority>{{ is_array($item) && isset($item['priority']) ? $item['priority'] : '0.8' }}</priority>
    </url>
    @endforeach
</urlset>
