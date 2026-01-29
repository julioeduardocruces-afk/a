<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// ── Auto-detect Laravel app base path ──
// Works with any structure: standard (public/ inside project),
// Hostinger (ats-app/ + public_html/), VPS, etc.
$appBase = null;
$candidates = [
    __DIR__ . '/..',           // Standard Laravel: public/ is inside project
    __DIR__ . '/../ats-app',   // Hostinger: ats-app/ + public_html/
];

// Also scan sibling directories for artisan file
$parent = dirname(__DIR__);
foreach (scandir($parent) as $entry) {
    if ($entry === '.' || $entry === '..') continue;
    $path = $parent . '/' . $entry;
    if (is_dir($path) && !in_array($path, $candidates)) {
        $candidates[] = $path;
    }
}

foreach ($candidates as $candidate) {
    $real = realpath($candidate);
    if ($real && file_exists($real . '/artisan') && file_exists($real . '/bootstrap/app.php')) {
        $appBase = $real;
        break;
    }
}

if ($appBase === null) {
    http_response_code(500);
    die('Error: No se encontro la aplicacion Laravel. Verifica que la carpeta con artisan existe junto a public_html/.');
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $appBase.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $appBase.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $appBase.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
