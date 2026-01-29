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
        // Hostinger deploy: app lives in ats-app/, web root is ../public_html/
        $hostingerPublic = dirname($this->app->basePath()) . '/public_html';
        if (is_dir($hostingerPublic)) {
            $this->app->usePublicPath($hostingerPublic);
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
