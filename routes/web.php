<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ResumeController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Middleware\AuditRequest;
use App\Http\Middleware\EnsureIsAdmin;
use App\Http\Middleware\EnsureOwnsResume;
use Illuminate\Support\Facades\Route;

// ──────────────────────────────────────────────
// PUBLIC LANDING (SEO)
// ──────────────────────────────────────────────
Route::get('/', [LandingController::class, 'home'])->name('home');
Route::get('/como-funciona', [LandingController::class, 'howItWorks'])->name('how-it-works');
Route::get('/preguntas-frecuentes', [LandingController::class, 'faq'])->name('faq');

// Sitemap & robots
Route::get('/sitemap.xml', function () {
    $urls = [
        url('/'),
        url('/como-funciona'),
        url('/preguntas-frecuentes'),
    ];
    return response()->view('seo.sitemap', compact('urls'))
        ->header('Content-Type', 'application/xml');
})->name('sitemap');

Route::get('/robots.txt', function () {
    return response("User-agent: *\nAllow: /\nSitemap: " . route('sitemap'), 200)
        ->header('Content-Type', 'text/plain');
});

// ──────────────────────────────────────────────
// AUTH
// ──────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/registro', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/registro', [AuthController::class, 'register'])
        ->middleware('throttle:5,1'); // 5 registrations per minute per IP
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1'); // 5 login attempts per minute per IP
    Route::post('/magic-link', [AuthController::class, 'sendMagicLink'])
        ->middleware('throttle:3,5') // max 3 attempts per 5 minutes
        ->name('magic-link.send');
    Route::get('/magic-link/{token}', [AuthController::class, 'loginWithMagicToken'])
        ->middleware('throttle:10,5') // 10 attempts per 5 minutes per IP
        ->name('magic-link.verify');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// ──────────────────────────────────────────────
// APP (Authenticated user)
// ──────────────────────────────────────────────
Route::middleware(['auth', AuditRequest::class])->group(function () {

    Route::get('/dashboard', [ResumeController::class, 'dashboard'])->name('dashboard');

    // Upload
    Route::get('/upload', [ResumeController::class, 'showUpload'])->name('upload.form');
    Route::post('/upload', [ResumeController::class, 'upload'])
        ->middleware('throttle:10,1') // 10 per minute
        ->name('upload.store');

    // Resume operations (IDOR-protected)
    Route::middleware(EnsureOwnsResume::class)->group(function () {
        Route::get('/resumes/{id}/target-role', [ResumeController::class, 'showTargetRole'])
            ->name('resumes.target-role');
        Route::post('/resumes/{id}/target-role', [ResumeController::class, 'setTargetRole'])
            ->name('resumes.set-target-role');
        Route::post('/resumes/{id}/process', [ResumeController::class, 'process'])
            ->middleware('throttle:5,1')
            ->name('resumes.process');
        Route::get('/resumes/{id}/status', [ResumeController::class, 'status'])
            ->middleware('throttle:60,1') // polling endpoint: 60/min/user
            ->name('resumes.status');
        Route::get('/resumes/{id}/preview', [ResumeController::class, 'preview'])
            ->middleware('throttle:30,1')
            ->name('resumes.preview');
        Route::get('/resumes/{id}/preview-page', [ResumeController::class, 'previewPage'])
            ->middleware('throttle:20,1') // page loads: 20/min/user
            ->name('resumes.preview-page');
    });

    // Payments
    Route::post('/payments/flow/create', [PaymentController::class, 'create'])
        ->middleware('throttle:5,1')
        ->name('payments.flow.create');
    Route::get('/payments/flow/return/{payment}', [PaymentController::class, 'returnFromFlow'])
        ->middleware('throttle:10,1') // return from payment: 10/min/user
        ->name('payments.flow.return');
});

// Flow webhook (no auth - verified by signature; CSRF excluded in bootstrap/app.php)
Route::post('/payments/flow/webhook', [PaymentController::class, 'webhook'])
    ->name('payments.flow.webhook');

// Download (token-based, no session auth required)
Route::get('/download/{token}', [DownloadController::class, 'download'])
    ->middleware('throttle:10,1')
    ->name('download.token');

// ──────────────────────────────────────────────
// ADMIN
// ──────────────────────────────────────────────
Route::middleware(['auth', EnsureIsAdmin::class, AuditRequest::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Credentials
        Route::get('/credentials', [AdminDashboardController::class, 'credentials'])->name('credentials');
        Route::post('/credentials', [AdminDashboardController::class, 'storeCredential'])->name('credentials.store');
        Route::post('/credentials/{id}/toggle', [AdminDashboardController::class, 'toggleCredential'])->name('credentials.toggle');
        Route::delete('/credentials/{id}', [AdminDashboardController::class, 'destroyCredential'])->name('credentials.destroy');

        // Resume management
        Route::get('/resumes', [AdminDashboardController::class, 'resumes'])->name('resumes');
        Route::post('/resumes/{id}/retry', [AdminDashboardController::class, 'retryResume'])->name('resumes.retry');
        Route::post('/resumes/{id}/resend-email', [AdminDashboardController::class, 'resendEmail'])->name('resumes.resend-email');

        // Audit & Metrics
        Route::get('/audit-logs', [AdminDashboardController::class, 'auditLogs'])->name('audit-logs');
        Route::get('/metrics', [AdminDashboardController::class, 'metrics'])->name('metrics');
    });
