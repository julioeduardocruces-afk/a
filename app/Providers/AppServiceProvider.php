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
        $this->ensureStorageDirs();
    }

    /**
     * Ensure required storage directories exist.
     * Runs once per deployment — checked via a simple flag file.
     */
    private function ensureStorageDirs(): void
    {
        $flag = storage_path('framework/.dirs_created');
        if (file_exists($flag)) {
            return;
        }

        $dirs = [
            storage_path('app/uploads'),
            storage_path('app/uploads/anonymous'),
            storage_path('app/finals'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }

        @file_put_contents($flag, date('Y-m-d H:i:s'));
    }
}
