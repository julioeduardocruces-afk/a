<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ResumeController;
use App\Http\Controllers\Admin\AdminBillingController;
use App\Http\Controllers\Admin\AdminBlogController;
use App\Http\Controllers\Admin\AdminBlogCategoryController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminFinanceController;
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

// Blog
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/categoria/{category}', [BlogController::class, 'byCategory'])->name('blog.category');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

// Serve uploaded files via PHP (avoids symlink issues on shared hosting)
Route::get('/media/{path}', function (string $path) {
    $fullPath = storage_path('app/public/' . $path);

    if (!file_exists($fullPath)) {
        abort(404);
    }

    // Prevent directory traversal
    $realPath = realpath($fullPath);
    $basePath = realpath(storage_path('app/public'));
    if (!$realPath || !$basePath || !str_starts_with($realPath, $basePath)) {
        abort(403);
    }

    return response()->file($realPath, [
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->where('path', '.*')->name('media.serve');

// Sitemap & robots
Route::get('/sitemap.xml', function () {
    $urls = [
        ['url' => url('/'), 'priority' => '1.0', 'changefreq' => 'daily'],
        ['url' => url('/como-funciona'), 'priority' => '0.8', 'changefreq' => 'weekly'],
        ['url' => url('/preguntas-frecuentes'), 'priority' => '0.8', 'changefreq' => 'weekly'],
        ['url' => url('/subir-cv'), 'priority' => '0.9', 'changefreq' => 'weekly'],
        ['url' => url('/crear-cv'), 'priority' => '0.9', 'changefreq' => 'weekly'],
        ['url' => url('/blog'), 'priority' => '0.8', 'changefreq' => 'daily'],
    ];

    // Add blog category pages
    $blogCategories = \App\Models\BlogCategory::ordered()->get();
    foreach ($blogCategories as $cat) {
        $urls[] = [
            'url' => route('blog.category', $cat->slug),
            'priority' => '0.7',
            'changefreq' => 'weekly',
        ];
    }

    // Add blog posts
    $posts = \App\Models\BlogPost::published()->latest('published_at')->get();
    foreach ($posts as $post) {
        $urls[] = [
            'url' => $post->url,
            'priority' => '0.7',
            'changefreq' => 'monthly',
            'lastmod' => $post->updated_at->format('Y-m-d'),
        ];
    }

    return response()->view('seo.sitemap', compact('urls'))
        ->header('Content-Type', 'application/xml');
})->name('sitemap');

Route::get('/robots.txt', function () {
    return response("User-agent: *\nAllow: /\nSitemap: " . route('sitemap'), 200)
        ->header('Content-Type', 'text/plain');
});

// ──────────────────────────────────────────────
// AUTH (admin-only login)
// ──────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Health check / diagnostics (remove after deploy is stable)
Route::get('/health-check', function () {
    $checks = [];

    // DB
    try {
        \Illuminate\Support\Facades\DB::select('SELECT 1');
        $checks['database'] = 'OK';
    } catch (\Throwable $e) {
        $checks['database'] = 'FAIL: ' . $e->getMessage();
    }

    // Sessions table (for database driver)
    try {
        $driver = config('session.driver');
        $checks['session_driver'] = $driver;
        if ($driver === 'database') {
            \Illuminate\Support\Facades\DB::select('SELECT COUNT(*) as c FROM sessions');
            $checks['sessions_table'] = 'OK';
        } else {
            $checks['sessions_dir'] = is_writable(storage_path('framework/sessions')) ? 'OK' : 'NOT WRITABLE';
        }
    } catch (\Throwable $e) {
        $checks['sessions_table'] = 'FAIL: ' . $e->getMessage();
    }

    // Storage writable
    $checks['storage_path'] = storage_path();
    $checks['base_path'] = base_path();
    $checks['storage_app'] = is_writable(storage_path('app')) ? 'OK' : 'NOT WRITABLE';
    $checks['storage_uploads'] = is_writable(storage_path('app/uploads')) ? 'OK' : (is_dir(storage_path('app/uploads')) ? 'NOT WRITABLE' : 'MISSING');
    $checks['storage_uploads_anon'] = is_writable(storage_path('app/uploads/anonymous')) ? 'OK' : (is_dir(storage_path('app/uploads/anonymous')) ? 'NOT WRITABLE' : 'MISSING');
    $checks['storage_logs'] = is_writable(storage_path('logs')) ? 'OK' : 'NOT WRITABLE';

    // Storage write/read test — writes a temp file and verifies it exists on disk
    try {
        $testFile = 'uploads/anonymous/_health_test_' . uniqid() . '.txt';
        \Illuminate\Support\Facades\Storage::put($testFile, 'health-check');
        $fullTestPath = storage_path('app/' . $testFile);
        if (file_exists($fullTestPath)) {
            $checks['storage_write_test'] = 'OK';
        } else {
            $checks['storage_write_test'] = 'FAIL: Storage::put succeeded but file not at ' . $fullTestPath;
            // Check if it ended up somewhere else
            $diskRoot = config('filesystems.disks.local.root', storage_path('app'));
            $altPath = $diskRoot . '/' . $testFile;
            $checks['storage_disk_root'] = $diskRoot;
            if ($altPath !== $fullTestPath && file_exists($altPath)) {
                $checks['storage_write_test'] .= ' — FOUND at ' . $altPath;
            }
        }
        \Illuminate\Support\Facades\Storage::delete($testFile);
    } catch (\Throwable $e) {
        $checks['storage_write_test'] = 'FAIL: ' . $e->getMessage();
    }

    // APP_KEY
    $checks['app_key'] = config('app.key') ? 'SET (' . substr(config('app.key'), 0, 10) . '...)' : 'MISSING';

    // Encryption
    try {
        $enc = encrypt('test');
        decrypt($enc);
        $checks['encryption'] = 'OK';
    } catch (\Throwable $e) {
        $checks['encryption'] = 'FAIL: ' . $e->getMessage();
    }

    // PHP extensions
    $checks['ext_fileinfo'] = extension_loaded('fileinfo') ? 'OK' : 'MISSING';
    $checks['ext_pdo_mysql'] = extension_loaded('pdo_mysql') ? 'OK' : 'MISSING';
    $checks['ext_mbstring'] = extension_loaded('mbstring') ? 'OK' : 'MISSING';

    // Cache
    try {
        cache()->put('_health', 'ok', 10);
        $checks['cache'] = cache()->get('_health') === 'ok' ? 'OK' : 'FAIL: read mismatch';
        cache()->forget('_health');
    } catch (\Throwable $e) {
        $checks['cache'] = 'FAIL: ' . $e->getMessage();
    }

    // Resumes table
    try {
        $cols = \Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM resumes LIKE 'access_token'");
        $checks['resumes_access_token'] = count($cols) > 0 ? 'OK' : 'COLUMN MISSING';
    } catch (\Throwable $e) {
        $checks['resumes_table'] = 'FAIL: ' . $e->getMessage();
    }

    return response()->json($checks);
});

// ──────────────────────────────────────────────
// PUBLIC CV FLOW (anonymous, no login required)
// ──────────────────────────────────────────────
Route::middleware(AuditRequest::class)->group(function () {

    // Upload (file)
    Route::get('/upload', [ResumeController::class, 'showUpload'])->name('upload.form');
    Route::post('/upload', [ResumeController::class, 'upload'])
        ->middleware('throttle:10,1')
        ->name('upload.store');

    // CV Builder (form-based alternative)
    Route::get('/cv-builder', [ResumeController::class, 'showCvBuilder'])->name('cv-builder.form');
    Route::post('/cv-builder', [ResumeController::class, 'storeCvBuilder'])
        ->middleware('throttle:10,1')
        ->name('cv-builder.store');

    // Resume operations (ownership verified via session access_token)
    Route::middleware(EnsureOwnsResume::class)->group(function () {
        Route::get('/resumes/{id}/target-role', [ResumeController::class, 'showTargetRole'])
            ->name('resumes.target-role');
        Route::post('/resumes/{id}/target-role', [ResumeController::class, 'setTargetRole'])
            ->name('resumes.set-target-role');
        Route::get('/resumes/{id}/payment', [ResumeController::class, 'showPayment'])
            ->name('resumes.payment');
        Route::post('/resumes/{id}/process', [ResumeController::class, 'process'])
            ->middleware('throttle:5,1')
            ->name('resumes.process');
        Route::get('/resumes/{id}/status', [ResumeController::class, 'status'])
            ->middleware('throttle:60,1')
            ->name('resumes.status');
        Route::get('/resumes/{id}/preview', [ResumeController::class, 'preview'])
            ->middleware('throttle:30,1')
            ->name('resumes.preview');
        Route::get('/resumes/{id}/preview-page', [ResumeController::class, 'previewPage'])
            ->middleware('throttle:20,1')
            ->name('resumes.preview-page');
    });

    // Payments
    Route::post('/payments/flow/create', [PaymentController::class, 'create'])
        ->middleware('throttle:5,1')
        ->name('payments.flow.create');
    Route::match(['get', 'post'], '/payments/flow/return/{payment}', [PaymentController::class, 'returnFromFlow'])
        ->middleware('throttle:10,1')
        ->name('payments.flow.return');
});

// Flow webhook (no auth - verified by signature; CSRF excluded in bootstrap/app.php)
Route::post('/payments/flow/webhook', [PaymentController::class, 'webhook'])
    ->middleware('throttle:30,1')
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
        Route::delete('/resumes/{id}', [AdminDashboardController::class, 'destroyResume'])->name('resumes.destroy');

        // Email Template
        Route::get('/email-template', [AdminDashboardController::class, 'emailTemplate'])->name('email-template');
        Route::post('/email-template', [AdminDashboardController::class, 'updateEmailTemplate'])->name('email-template.update');

        // Audit & Metrics
        Route::get('/audit-logs', [AdminDashboardController::class, 'auditLogs'])->name('audit-logs');
        Route::get('/metrics', [AdminDashboardController::class, 'metrics'])->name('metrics');

        // Finance
        Route::prefix('finance')->name('finance.')->group(function () {
            Route::get('/', [AdminFinanceController::class, 'dashboard'])->name('dashboard');
            Route::get('/sales', [AdminFinanceController::class, 'sales'])->name('sales');
            Route::get('/downloads', [AdminFinanceController::class, 'downloads'])->name('downloads');
            Route::get('/ai-usage', [AdminFinanceController::class, 'aiUsage'])->name('ai-usage');
            Route::get('/settlement', [AdminFinanceController::class, 'settlement'])->name('settlement');
            Route::get('/refunds', [AdminFinanceController::class, 'refunds'])->name('refunds');
            Route::post('/refunds/{payment}/process', [AdminFinanceController::class, 'processRefund'])->name('process-refund');

            // CSV Exports
            Route::get('/export/sales', [AdminFinanceController::class, 'exportSales'])->name('export-sales');
            Route::get('/export/downloads', [AdminFinanceController::class, 'exportDownloads'])->name('export-downloads');
            Route::get('/export/ai-usage', [AdminFinanceController::class, 'exportAiUsage'])->name('export-ai-usage');
        });

        // Free CV Processing (no payment required)
        Route::get('/free-process', [AdminDashboardController::class, 'freeProcess'])->name('free-process');
        Route::post('/free-process/upload', [AdminDashboardController::class, 'freeProcessUpload'])->name('free-process.upload');
        Route::post('/free-process/builder', [AdminDashboardController::class, 'freeProcessBuilder'])->name('free-process.builder');
        Route::post('/resumes/{id}/process-free', [AdminDashboardController::class, 'processResumeFree'])->name('resumes.process-free');

        // Blog Management
        Route::prefix('blog')->name('blog.')->group(function () {
            Route::get('/', [AdminBlogController::class, 'index'])->name('index');
            Route::get('/create', [AdminBlogController::class, 'create'])->name('create');
            Route::post('/', [AdminBlogController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [AdminBlogController::class, 'edit'])->name('edit');
            Route::put('/{id}', [AdminBlogController::class, 'update'])->name('update');
            Route::delete('/{id}', [AdminBlogController::class, 'destroy'])->name('destroy');
            Route::post('/upload-image', [AdminBlogController::class, 'uploadEditorImage'])->name('upload-image');

            // Categories CRUD
            Route::prefix('categories')->name('categories.')->group(function () {
                Route::get('/', [AdminBlogCategoryController::class, 'index'])->name('index');
                Route::get('/create', [AdminBlogCategoryController::class, 'create'])->name('create');
                Route::post('/', [AdminBlogCategoryController::class, 'store'])->name('store');
                Route::get('/{id}/edit', [AdminBlogCategoryController::class, 'edit'])->name('edit');
                Route::put('/{id}', [AdminBlogCategoryController::class, 'update'])->name('update');
                Route::delete('/{id}', [AdminBlogCategoryController::class, 'destroy'])->name('destroy');
            });
        });

        // Billing / Invoicing
        Route::prefix('billing')->name('billing.')->group(function () {
            Route::get('/', [AdminBillingController::class, 'index'])->name('index');
            Route::get('/{id}', [AdminBillingController::class, 'show'])->name('show');
            Route::post('/{id}/mark-issued', [AdminBillingController::class, 'markIssued'])->name('mark-issued');
            Route::post('/{id}/upload-invoice', [AdminBillingController::class, 'uploadInvoice'])->name('upload-invoice');
            Route::post('/{id}/send-invoice', [AdminBillingController::class, 'sendInvoice'])->name('send-invoice');
            Route::delete('/{id}/remove-invoice', [AdminBillingController::class, 'removeInvoice'])->name('remove-invoice');
        });
    });
