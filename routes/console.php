<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Process queued jobs via scheduler (for shared hosting without persistent workers).
// Cron entry: * * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3 --backoff=10')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
