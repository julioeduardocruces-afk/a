<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Auto-detect public directory when app is outside the web root
        // (e.g., ats-app/ + public_html/ on shared hosting).
        // If the standard public/ dir doesn't exist, scan for public_html/ or similar.
        $standardPublic = $this->app->basePath('public');
        if (!is_dir($standardPublic) || !file_exists($standardPublic . '/index.php')) {
            $parent = dirname($this->app->basePath());
            foreach (['public_html', 'www', 'htdocs', 'public'] as $candidate) {
                $path = $parent . '/' . $candidate;
                if (is_dir($path) && file_exists($path . '/index.php')) {
                    $this->app->usePublicPath($path);
                    break;
                }
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
