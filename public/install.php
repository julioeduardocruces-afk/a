<?php
/**
 * ============================================================
 *  ATS CV Optimizer — Auto-Installer (Universal)
 * ============================================================
 *
 *  Funciona en cualquier hosting: Hostinger, VPS, cPanel, etc.
 *  Auto-detecta donde esta la app Laravel buscando el archivo artisan.
 *
 *  Sube los archivos al hosting.
 *  Navega a: https://tu-dominio.com/install.php
 *  Completa el formulario. No necesitas SSH, Artisan ni phpMyAdmin.
 *
 *  Este archivo se auto-elimina al finalizar la instalacion.
 * ============================================================
 */

// ─── Seguridad basica ────────────────────────────────────
error_reporting(E_ALL);
ini_set('display_errors', '0');
set_time_limit(300);

// ─── Auto-detectar ruta de la app Laravel ────────────────
// Busca el archivo artisan en: directorio padre, ../ats-app/, o cualquier
// carpeta hermana. Funciona en cualquier estructura de hosting.
$basePath = null;
$_candidates = [
    dirname(__DIR__),                   // Standard: public/ dentro del proyecto
    dirname(__DIR__) . '/ats-app',      // Hostinger: ats-app/ + public_html/
];
// Escanear carpetas hermanas
$_parentDir = dirname(__DIR__);
foreach (@scandir($_parentDir) ?: [] as $_entry) {
    if ($_entry === '.' || $_entry === '..' || $_entry === basename(__DIR__)) continue;
    $_p = $_parentDir . '/' . $_entry;
    if (is_dir($_p) && !in_array($_p, $_candidates)) {
        $_candidates[] = $_p;
    }
}
foreach ($_candidates as $_c) {
    $_real = @realpath($_c);
    if ($_real && file_exists($_real . '/artisan') && file_exists($_real . '/bootstrap/app.php')) {
        $basePath = $_real;
        break;
    }
}

if ($basePath === null) {
    http_response_code(500);
    die('Error: No se encontro la app Laravel. Asegurate de que la carpeta con artisan y bootstrap/ existe junto a ' . basename(__DIR__) . '/');
}

$envPath  = $basePath . '/.env';
$lockFile = $basePath . '/storage/installed.lock';

if (file_exists($lockFile)) {
    http_response_code(403);
    die('La aplicacion ya fue instalada. Elimina storage/installed.lock para reinstalar.');
}

// ─── Funciones auxiliares ─────────────────────────────────

function checkRequirements(string $basePath): array
{
    $checks = [];

    // PHP version
    $checks[] = [
        'name'     => 'PHP >= 8.2',
        'ok'       => version_compare(PHP_VERSION, '8.2.0', '>='),
        'actual'   => PHP_VERSION,
        'required' => true,
    ];

    // App path detected
    $checks[] = [
        'name'     => 'App Laravel detectada',
        'ok'       => true,
        'actual'   => $basePath,
        'required' => true,
    ];

    // Extensions
    $requiredExts = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'json', 'ctype', 'xml', 'fileinfo', 'gd'];
    foreach ($requiredExts as $ext) {
        $checks[] = [
            'name'     => "ext-{$ext}",
            'ok'       => extension_loaded($ext),
            'actual'   => extension_loaded($ext) ? 'Instalada' : 'NO encontrada',
            'required' => true,
        ];
    }

    // Optional
    $checks[] = [
        'name'     => 'ext-zip (DOCX processing)',
        'ok'       => extension_loaded('zip'),
        'actual'   => extension_loaded('zip') ? 'Instalada' : 'NO encontrada',
        'required' => false,
    ];

    $checks[] = [
        'name'     => 'ext-redis (opcional, se usa DB si no hay)',
        'ok'       => extension_loaded('redis'),
        'actual'   => extension_loaded('redis') ? 'Instalada' : 'Usando database driver',
        'required' => false,
    ];

    // Directorios escribibles
    $dirs = ['storage', 'storage/app', 'storage/framework', 'storage/logs', 'bootstrap/cache'];
    foreach ($dirs as $dir) {
        $full = $basePath . '/' . $dir;
        $writable = is_dir($full) && is_writable($full);
        $checks[] = [
            'name'     => "{$dir}/ escribible",
            'ok'       => $writable,
            'actual'   => $writable ? 'OK' : 'SIN PERMISOS',
            'required' => true,
        ];
    }

    // Composer vendor
    $checks[] = [
        'name'     => 'vendor/autoload.php existe',
        'ok'       => file_exists($basePath . '/vendor/autoload.php'),
        'actual'   => file_exists($basePath . '/vendor/autoload.php') ? 'OK' : 'Ejecuta: composer install',
        'required' => true,
    ];

    // wkhtmltoimage (optional)
    $wkPath = '/usr/local/bin/wkhtmltoimage';
    $checks[] = [
        'name'     => 'wkhtmltoimage (opcional, hay fallback GD)',
        'ok'       => file_exists($wkPath) && is_executable($wkPath),
        'actual'   => file_exists($wkPath) ? 'Disponible' : 'No disponible (se usara GD)',
        'required' => false,
    ];

    return $checks;
}

function generateAppKey(): string
{
    return 'base64:' . base64_encode(random_bytes(32));
}

function buildEnvContent(array $data): string
{
    $appKey = generateAppKey();

    // Test actual Redis connection, not just extension
    $hasRedis = false;
    if (extension_loaded('redis')) {
        try {
            $redis = new \Redis();
            $hasRedis = @$redis->connect('127.0.0.1', 6379, 2); // 2s timeout
            @$redis->close();
        } catch (\Throwable $e) {
            $hasRedis = false;
        }
    }

    // Queue: use redis if available, otherwise sync (shared hosting has no queue worker)
    $queueDriver = $hasRedis ? 'redis' : 'sync';
    $cacheDriver  = $hasRedis ? 'redis' : 'database';

    return <<<ENV
APP_NAME="CV Optimizer ATS"
APP_ENV=production
APP_KEY={$appKey}
APP_DEBUG=false
APP_URL={$data['app_url']}

APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_FAKER_LOCALE=es_CL
APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST={$data['db_host']}
DB_PORT={$data['db_port']}
DB_DATABASE={$data['db_name']}
DB_USERNAME={$data['db_user']}
DB_PASSWORD="{$data['db_pass']}"

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION={$queueDriver}
CACHE_STORE={$cacheDriver}

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST={$data['mail_host']}
MAIL_PORT={$data['mail_port']}
MAIL_USERNAME="{$data['mail_user']}"
MAIL_PASSWORD="{$data['mail_pass']}"
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="{$data['mail_from']}"
MAIL_FROM_NAME="CV Optimizer ATS"

ATS_AI_PROVIDER={$data['ai_provider']}
ATS_PRICE_CLP={$data['price_clp']}
ATS_MAX_UPLOAD_MB=10
ATS_DOWNLOAD_TOKEN_TTL=15
WKHTMLTOIMAGE_PATH=/usr/local/bin/wkhtmltoimage

VITE_APP_NAME="CV Optimizer ATS"
ENV;
}

function testDbConnection(string $host, string $port, string $dbname, string $user, string $pass): string|true
{
    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE    => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT    => 10,
        ]);
        $pdo->query('SELECT 1');
        return true;
    } catch (PDOException $e) {
        return $e->getMessage();
    }
}

function runMigrations(PDO $pdo): array
{
    $log = [];

    $sqls = [
        // ── users, password_reset_tokens, sessions ──
        "CREATE TABLE IF NOT EXISTS `users` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `email` VARCHAR(255) NOT NULL,
            `password` VARCHAR(255) NOT NULL COMMENT 'bcrypt hash',
            `magic_token` VARCHAR(64) NULL,
            `magic_token_expires_at` TIMESTAMP NULL,
            `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
            `remember_token` VARCHAR(100) NULL,
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
            UNIQUE KEY `users_email_unique` (`email`),
            UNIQUE KEY `users_magic_token_unique` (`magic_token`),
            INDEX `users_email_index` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
            `email` VARCHAR(255) NOT NULL,
            `token` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP NULL,
            PRIMARY KEY (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `sessions` (
            `id` VARCHAR(255) NOT NULL,
            `user_id` BIGINT UNSIGNED NULL,
            `ip_address` VARCHAR(45) NULL,
            `user_agent` TEXT NULL,
            `payload` LONGTEXT NOT NULL,
            `last_activity` INT NOT NULL,
            PRIMARY KEY (`id`),
            INDEX `sessions_user_id_index` (`user_id`),
            INDEX `sessions_last_activity_index` (`last_activity`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── cache ──
        "CREATE TABLE IF NOT EXISTS `cache` (
            `key` VARCHAR(255) NOT NULL,
            `value` MEDIUMTEXT NOT NULL,
            `expiration` INT NOT NULL,
            PRIMARY KEY (`key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `cache_locks` (
            `key` VARCHAR(255) NOT NULL,
            `owner` VARCHAR(255) NOT NULL,
            `expiration` INT NOT NULL,
            PRIMARY KEY (`key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── jobs ──
        "CREATE TABLE IF NOT EXISTS `jobs` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `queue` VARCHAR(255) NOT NULL,
            `payload` LONGTEXT NOT NULL,
            `attempts` TINYINT UNSIGNED NOT NULL,
            `reserved_at` INT UNSIGNED NULL,
            `available_at` INT UNSIGNED NOT NULL,
            `created_at` INT UNSIGNED NOT NULL,
            INDEX `jobs_queue_index` (`queue`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `job_batches` (
            `id` VARCHAR(255) NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `total_jobs` INT NOT NULL,
            `pending_jobs` INT NOT NULL,
            `failed_jobs` INT NOT NULL,
            `failed_job_ids` LONGTEXT NOT NULL,
            `options` MEDIUMTEXT NULL,
            `cancelled_at` INT NULL,
            `created_at` INT NOT NULL,
            `finished_at` INT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `failed_jobs` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `uuid` VARCHAR(255) NOT NULL,
            `connection` TEXT NOT NULL,
            `queue` TEXT NOT NULL,
            `payload` LONGTEXT NOT NULL,
            `exception` LONGTEXT NOT NULL,
            `failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── resumes ──
        "CREATE TABLE IF NOT EXISTS `resumes` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `user_id` BIGINT UNSIGNED NULL,
            `access_token` VARCHAR(64) NULL,
            `original_filename` VARCHAR(255) NOT NULL,
            `original_mime` VARCHAR(100) NOT NULL,
            `original_path` VARCHAR(500) NOT NULL COMMENT 'Storage path, never public',
            `extracted_text` LONGTEXT NULL,
            `structured_json` JSON NULL COMMENT 'Parsed sections',
            `target_industry` VARCHAR(255) NULL COMMENT 'Rubro objetivo',
            `target_role` VARCHAR(255) NULL COMMENT 'Cargo objetivo',
            `customer_email` VARCHAR(255) NULL COMMENT 'Email for anonymous delivery',
            `status` ENUM('draft','processing','preview_ready','paid','delivered','failed') NOT NULL DEFAULT 'draft',
            `error_code` VARCHAR(100) NULL,
            `error_message` TEXT NULL,
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
            UNIQUE KEY `resumes_access_token_unique` (`access_token`),
            INDEX `resumes_user_id_status_index` (`user_id`, `status`),
            INDEX `resumes_status_index` (`status`),
            CONSTRAINT `resumes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── resume_versions ──
        "CREATE TABLE IF NOT EXISTS `resume_versions` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `resume_id` BIGINT UNSIGNED NOT NULL,
            `version` INT UNSIGNED NOT NULL DEFAULT 1,
            `optimized_text_md` LONGTEXT NOT NULL,
            `optimized_text_plain` LONGTEXT NOT NULL,
            `ats_keywords_json` JSON NULL,
            `score_json` JSON NULL COMMENT 'ATS heuristic score breakdown',
            `consistency_report_json` JSON NULL COMMENT 'IA consistency check',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `resume_versions_resume_id_version_unique` (`resume_id`, `version`),
            INDEX `resume_versions_resume_id_index` (`resume_id`),
            CONSTRAINT `resume_versions_resume_id_foreign` FOREIGN KEY (`resume_id`) REFERENCES `resumes` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── payments ──
        "CREATE TABLE IF NOT EXISTS `payments` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `user_id` BIGINT UNSIGNED NULL,
            `resume_id` BIGINT UNSIGNED NOT NULL,
            `provider` ENUM('flow') NOT NULL DEFAULT 'flow',
            `amount` INT UNSIGNED NOT NULL COMMENT 'Amount in CLP',
            `currency` VARCHAR(3) NOT NULL DEFAULT 'CLP',
            `status` ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
            `flow_token` VARCHAR(255) NULL,
            `flow_order` VARCHAR(255) NULL,
            `raw_payload_json` JSON NULL,
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
            UNIQUE KEY `payments_flow_token_unique` (`flow_token`),
            UNIQUE KEY `payments_flow_order_unique` (`flow_order`),
            INDEX `payments_user_id_resume_id_index` (`user_id`, `resume_id`),
            INDEX `payments_status_index` (`status`),
            CONSTRAINT `payments_resume_id_foreign` FOREIGN KEY (`resume_id`) REFERENCES `resumes` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── api_credentials ──
        "CREATE TABLE IF NOT EXISTS `api_credentials` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `provider` ENUM('openai','gemini','flow','smtp') NOT NULL,
            `name` VARCHAR(255) NOT NULL COMMENT 'Human-readable label',
            `encrypted_json` TEXT NOT NULL COMMENT 'JSON credentials (plain text)',
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `usage_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `last_used_at` TIMESTAMP NULL,
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
            INDEX `api_credentials_provider_is_active_index` (`provider`, `is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── audit_logs ──
        "CREATE TABLE IF NOT EXISTS `audit_logs` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `actor_type` VARCHAR(50) NOT NULL COMMENT 'user|admin|system',
            `actor_id` BIGINT UNSIGNED NULL,
            `action` VARCHAR(255) NOT NULL,
            `metadata_json` JSON NULL,
            `ip` VARCHAR(45) NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `audit_logs_actor_type_actor_id_index` (`actor_type`, `actor_id`),
            INDEX `audit_logs_action_index` (`action`),
            INDEX `audit_logs_created_at_index` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── metrics_daily ──
        "CREATE TABLE IF NOT EXISTS `metrics_daily` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `date` DATE NOT NULL,
            `uploads` INT UNSIGNED NOT NULL DEFAULT 0,
            `previews` INT UNSIGNED NOT NULL DEFAULT 0,
            `paid` INT UNSIGNED NOT NULL DEFAULT 0,
            `revenue` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'CLP',
            `total_process_time_ms` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `processed_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
            UNIQUE KEY `metrics_daily_date_unique` (`date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── download_tokens ──
        "CREATE TABLE IF NOT EXISTS `download_tokens` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `resume_id` BIGINT UNSIGNED NOT NULL,
            `user_id` BIGINT UNSIGNED NULL,
            `token` VARCHAR(64) NOT NULL,
            `used` TINYINT(1) NOT NULL DEFAULT 0,
            `download_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `max_downloads` TINYINT UNSIGNED NOT NULL DEFAULT 3,
            `expires_at` TIMESTAMP NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `download_tokens_token_unique` (`token`),
            INDEX `download_tokens_token_used_index` (`token`, `used`),
            CONSTRAINT `download_tokens_resume_id_foreign` FOREIGN KEY (`resume_id`) REFERENCES `resumes` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── settings ──
        "CREATE TABLE IF NOT EXISTS `settings` (
            `key` VARCHAR(100) NOT NULL,
            `value` LONGTEXT NULL,
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
            PRIMARY KEY (`key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── download_tokens: add download_count/max_downloads (idempotent) ──
        "ALTER TABLE `download_tokens` ADD COLUMN IF NOT EXISTS `download_count` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `used`",
        "ALTER TABLE `download_tokens` ADD COLUMN IF NOT EXISTS `max_downloads` TINYINT UNSIGNED NOT NULL DEFAULT 3 AFTER `download_count`",

        // ── performance indexes ──
        "CREATE INDEX `payments_resume_id_status_index` ON `payments` (`resume_id`, `status`)",

        // ── scalability indexes ──
        "CREATE INDEX `resumes_customer_email_index` ON `resumes` (`customer_email`)",
        "CREATE INDEX `download_tokens_expires_at_index` ON `download_tokens` (`expires_at`)",
        "CREATE INDEX `payments_refunded_at_index` ON `payments` (`refunded_at`)",
        "CREATE INDEX `payments_created_at_index` ON `payments` (`created_at`)",
        "CREATE INDEX `audit_logs_action_created_at_index` ON `audit_logs` (`action`, `created_at`)",

        // ── migrations table (so Artisan knows migrations ran) ──
        "CREATE TABLE IF NOT EXISTS `migrations` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `migration` VARCHAR(255) NOT NULL,
            `batch` INT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    // Migrations registry
    $migrationNames = [
        '0001_01_01_000000_create_users_table',
        '0001_01_01_000001_create_cache_table',
        '0001_01_01_000002_create_jobs_table',
        '2024_01_02_000001_create_resumes_table',
        '2024_01_02_000002_create_resume_versions_table',
        '2024_01_02_000003_create_payments_table',
        '2024_01_02_000004_create_api_credentials_table',
        '2024_01_02_000005_create_audit_logs_table',
        '2024_01_02_000006_create_metrics_daily_table',
        '2024_01_02_000007_create_download_tokens_table',
        '2024_01_02_000008_add_performance_indexes',
        '2024_01_02_000009_add_anonymous_flow_fields',
        '2024_01_02_000010_create_settings_table',
        '2024_01_02_000013_add_download_count_to_download_tokens',
        '2024_01_02_000014_add_scalability_indexes',
    ];

    foreach ($sqls as $i => $sql) {
        try {
            $pdo->exec($sql);
            $log[] = ['ok' => true, 'msg' => "OK: " . mb_substr($sql, 0, 60) . '...'];
        } catch (PDOException $e) {
            // Duplicate index/column/entry is OK (re-run safe)
            if (str_contains($e->getMessage(), 'Duplicate key name') || str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'Duplicate column')) {
                $log[] = ['ok' => true, 'msg' => "SKIP (ya existe): " . mb_substr($sql, 0, 60) . '...'];
            } else {
                $log[] = ['ok' => false, 'msg' => "ERROR: " . $e->getMessage()];
            }
        }
    }

    // Register migrations so `php artisan migrate:status` shows them as ran
    try {
        foreach ($migrationNames as $idx => $name) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO `migrations` (`migration`, `batch`) VALUES (?, 1)");
            $stmt->execute([$name]);
        }
        $log[] = ['ok' => true, 'msg' => 'Migraciones registradas en tabla migrations.'];
    } catch (PDOException $e) {
        $log[] = ['ok' => false, 'msg' => "Error registrando migrations: " . $e->getMessage()];
    }

    return $log;
}

function createAdminUser(PDO $pdo, string $name, string $email, string $password): string|true
{
    try {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            "INSERT INTO `users` (`name`, `email`, `password`, `is_admin`, `created_at`, `updated_at`)
             VALUES (?, ?, ?, 1, ?, ?)
             ON DUPLICATE KEY UPDATE `is_admin` = 1, `password` = VALUES(`password`), `updated_at` = VALUES(`updated_at`)"
        );
        $stmt->execute([$name, $email, $hash, $now, $now]);
        return true;
    } catch (PDOException $e) {
        return $e->getMessage();
    }
}

function createStorageDirs(string $basePath): void
{
    $dirs = [
        'storage/app/uploads',
        'storage/app/uploads/anonymous',
        'storage/app/finals',
        'storage/framework/cache',
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/logs',
    ];
    foreach ($dirs as $dir) {
        $full = $basePath . '/' . $dir;
        if (!is_dir($full)) {
            @mkdir($full, 0775, true);
        }
    }

    // storage/app/.gitignore
    $gitignore = $basePath . '/storage/app/.gitignore';
    if (!file_exists($gitignore)) {
        @file_put_contents($gitignore, "*\n!.gitignore\n");
    }
}

function createSymlink(string $basePath): bool
{
    $target = $basePath . '/storage/app/public';
    $link   = __DIR__ . '/storage';  // symlink in same dir as install.php (public_html/)

    if (is_link($link)) return true;
    if (!is_dir($target)) @mkdir($target, 0775, true);

    return @symlink($target, $link);
}

// ─── PROCESAR FORMULARIO ──────────────────────────────────
$step    = $_POST['step'] ?? $_GET['step'] ?? 'check';
$error   = '';
$success = '';
$migLog  = [];

if ($step === 'install' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validar CSRF token
    session_start();
    if (!isset($_POST['_token']) || $_POST['_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die('Token CSRF invalido. Recarga la pagina.');
    }

    // Recoger datos
    $data = [
        'app_url'     => rtrim(trim($_POST['app_url'] ?? ''), '/'),
        'db_host'     => trim($_POST['db_host'] ?? 'localhost'),
        'db_port'     => trim($_POST['db_port'] ?? '3306'),
        'db_name'     => trim($_POST['db_name'] ?? ''),
        'db_user'     => trim($_POST['db_user'] ?? ''),
        'db_pass'     => $_POST['db_pass'] ?? '',
        'mail_host'   => trim($_POST['mail_host'] ?? ''),
        'mail_port'   => trim($_POST['mail_port'] ?? '465'),
        'mail_user'   => trim($_POST['mail_user'] ?? ''),
        'mail_pass'   => $_POST['mail_pass'] ?? '',
        'mail_from'   => trim($_POST['mail_from'] ?? ''),
        'ai_provider' => in_array($_POST['ai_provider'] ?? '', ['openai', 'gemini']) ? $_POST['ai_provider'] : 'openai',
        'price_clp'   => max(100, (int)($_POST['price_clp'] ?? 4990)),
        'admin_name'  => trim($_POST['admin_name'] ?? ''),
        'admin_email' => trim($_POST['admin_email'] ?? ''),
        'admin_pass'  => $_POST['admin_pass'] ?? '',
    ];

    // Validaciones basicas
    if (empty($data['db_name']) || empty($data['db_user'])) {
        $error = 'Base de datos: nombre y usuario son obligatorios.';
    } elseif (empty($data['admin_email']) || empty($data['admin_pass'])) {
        $error = 'Admin: email y password son obligatorios.';
    } elseif (strlen($data['admin_pass']) < 8) {
        $error = 'Admin: password debe tener al menos 8 caracteres.';
    } elseif (!filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Admin: email invalido.';
    } else {
        // 1. Test DB connection
        $dbTest = testDbConnection($data['db_host'], $data['db_port'], $data['db_name'], $data['db_user'], $data['db_pass']);
        if ($dbTest !== true) {
            $error = "Error de conexion MySQL: {$dbTest}";
        }
    }

    if (empty($error)) {
        // 2. Write .env
        $envContent = buildEnvContent($data);
        if (!@file_put_contents($envPath, $envContent, LOCK_EX)) {
            $error = "No se pudo escribir .env — verifica permisos en: {$basePath}";
        }
    }

    if (empty($error)) {
        // 3. Create storage dirs
        createStorageDirs($basePath);

        // 4. Create storage symlink
        createSymlink($basePath);

        // 5. Run migrations
        $dsn = "mysql:host={$data['db_host']};port={$data['db_port']};dbname={$data['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $data['db_user'], $data['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $migLog = runMigrations($pdo);

        // Check for critical errors
        $hasCriticalError = false;
        foreach ($migLog as $entry) {
            if (!$entry['ok']) {
                $hasCriticalError = true;
                break;
            }
        }

        if ($hasCriticalError) {
            $error = 'Hubo errores en las migraciones. Revisa el log abajo.';
        }
    }

    if (empty($error)) {
        // 6. Create admin user
        $adminResult = createAdminUser($pdo, $data['admin_name'] ?: 'Admin', $data['admin_email'], $data['admin_pass']);
        if ($adminResult !== true) {
            $error = "Error creando admin: {$adminResult}";
        }
    }

    if (empty($error)) {
        // 7. Optimizar Laravel (config/route cache via artisan)
        $artisan = $basePath . '/artisan';
        if (file_exists($artisan)) {
            $phpBin = PHP_BINARY ?: 'php';
            @exec("{$phpBin} {$artisan} config:cache 2>&1");
            @exec("{$phpBin} {$artisan} route:cache 2>&1");
            @exec("{$phpBin} {$artisan} view:cache 2>&1");
        }

        // 8. Create lock file
        @file_put_contents($lockFile, json_encode([
            'installed_at' => date('Y-m-d H:i:s'),
            'admin_email'  => $data['admin_email'],
            'php_version'  => PHP_VERSION,
        ]));

        $step    = 'done';
        $success = 'Instalacion completada exitosamente.';
    }
}

// CSRF token
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$checks = checkRequirements($basePath);
$allRequired = !in_array(false, array_map(fn($c) => !$c['required'] || $c['ok'], $checks));

// Auto-detect URL
$proto   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
$autoUrl = "{$proto}://{$host}";

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Instalador - CV Optimizer ATS</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f0f2f5;color:#1a1a2e;line-height:1.6}
.container{max-width:860px;margin:30px auto;padding:0 20px}
.card{background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.08);padding:36px;margin-bottom:24px}
h1{font-size:28px;margin-bottom:6px;color:#16213e}
.subtitle{color:#666;font-size:14px;margin-bottom:30px}
h2{font-size:20px;margin-bottom:16px;color:#16213e;padding-bottom:8px;border-bottom:2px solid #e8e8e8}
h3{font-size:16px;margin:20px 0 10px;color:#333}
.check-grid{display:grid;gap:8px;margin-bottom:24px}
.check-item{display:flex;align-items:center;gap:10px;padding:8px 12px;background:#f8f9fa;border-radius:6px;font-size:14px}
.check-item .icon{font-size:18px;width:24px;text-align:center;flex-shrink:0}
.check-item .name{font-weight:600;min-width:200px}
.check-item .val{color:#666}
.ok .icon{color:#22c55e}
.fail .icon{color:#ef4444}
.warn .icon{color:#f59e0b}
.form-group{margin-bottom:18px}
.form-group label{display:block;font-weight:600;margin-bottom:5px;font-size:14px;color:#333}
.form-group small{display:block;color:#888;font-size:12px;margin-top:3px}
.form-group input,.form-group select{width:100%;padding:10px 14px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;transition:border-color .2s}
.form-group input:focus,.form-group select:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media(max-width:600px){.form-row{grid-template-columns:1fr}}
.btn{display:inline-block;padding:12px 32px;background:#3b82f6;color:#fff;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;transition:background .2s}
.btn:hover{background:#2563eb}
.btn:disabled{background:#94a3b8;cursor:not-allowed}
.btn-danger{background:#ef4444}
.btn-danger:hover{background:#dc2626}
.btn-success{background:#22c55e}
.alert{padding:14px 18px;border-radius:8px;margin-bottom:20px;font-size:14px}
.alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.alert-success{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}
.alert-info{background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe}
.migration-log{background:#1a1a2e;color:#e2e8f0;border-radius:8px;padding:16px;font-family:'Fira Code',monospace;font-size:12px;max-height:300px;overflow-y:auto;margin:16px 0}
.migration-log .ok{color:#4ade80}
.migration-log .err{color:#f87171}
.section-divider{border-top:2px solid #e5e7eb;margin:28px 0;padding-top:20px}
.done-box{text-align:center;padding:40px}
.done-box .icon{font-size:64px;color:#22c55e;margin-bottom:16px}
.done-box h2{border:none;text-align:center}
.done-box p{color:#666;margin-bottom:20px}
.done-links{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:20px}
.done-links a{padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px}
.done-links .primary{background:#3b82f6;color:#fff}
.done-links .secondary{background:#e5e7eb;color:#333}
.warn-box{background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:14px;margin:16px 0;font-size:13px;color:#92400e}
</style>
</head>
<body>
<div class="container">
<div class="card">
    <h1>CV Optimizer ATS</h1>
    <p class="subtitle">Instalador automatico &mdash; detecta rutas automaticamente</p>

<?php if ($step === 'done'): ?>
    <!-- ════════ PASO FINAL: COMPLETADO ════════ -->
    <div class="done-box">
        <div class="icon">&#10004;</div>
        <h2>Instalacion Completada</h2>
        <p>Tu aplicacion esta lista. Configura las credenciales API desde el panel admin.</p>

        <div class="alert alert-info">
            <strong>Cron Job obligatorio</strong> — Ve a Panel Hostinger &gt; Avanzado &gt; Cron Jobs y agrega:<br>
            <code style="background:#dbeafe;padding:4px 8px;border-radius:4px;font-size:12px;display:inline-block;margin-top:6px">
            * * * * * cd <?= htmlspecialchars($basePath) ?> && php artisan schedule:run >> /dev/null 2>&1
            </code>
        </div>

        <div class="warn-box">
            <strong>Siguiente paso:</strong> Entra al panel admin y agrega credenciales para OpenAI/Gemini y Flow (Webpay).
            Sin credenciales, el sistema no puede procesar CVs ni recibir pagos.
        </div>

        <div class="done-links">
            <a href="<?= htmlspecialchars($autoUrl) ?>/admin" class="primary">Panel Admin</a>
            <a href="<?= htmlspecialchars($autoUrl) ?>" class="secondary">Ir al sitio</a>
        </div>

        <div class="warn-box" style="margin-top:24px;background:#fef2f2;border-color:#fecaca;color:#991b1b">
            <strong>SEGURIDAD:</strong> Este archivo se debe eliminar.
            <form method="post" action="install.php?step=selfdelete" style="display:inline">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <button type="submit" class="btn btn-danger" style="padding:6px 16px;font-size:13px;margin-left:8px">
                    Eliminar install.php ahora
                </button>
            </form>
        </div>
    </div>

    <?php if (!empty($migLog)): ?>
    <h3>Log de migraciones</h3>
    <div class="migration-log">
    <?php foreach ($migLog as $entry): ?>
        <div class="<?= $entry['ok'] ? 'ok' : 'err' ?>"><?= htmlspecialchars($entry['msg']) ?></div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>

<?php elseif ($step === 'selfdelete'): ?>
    <?php
    // Self-delete
    session_start();
    if (($_POST['_token'] ?? '') === ($_SESSION['csrf_token'] ?? '')) {
        $deleted = @unlink(__FILE__);
        if ($deleted) {
            echo '<div class="alert alert-success"><strong>install.php eliminado.</strong> Redirigiendo...</div>';
            echo '<script>setTimeout(function(){window.location="' . htmlspecialchars($autoUrl) . '/admin"},2000)</script>';
        } else {
            echo '<div class="alert alert-error">No se pudo eliminar. Eliminalo manualmente via FTP.</div>';
        }
    } else {
        echo '<div class="alert alert-error">Token CSRF invalido.</div>';
    }
    ?>

<?php else: ?>
    <!-- ════════ PASO 1: REQUISITOS + FORMULARIO ════════ -->

    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <h2>1. Requisitos del servidor</h2>
    <div class="check-grid">
    <?php foreach ($checks as $c): ?>
        <?php
        $class = $c['ok'] ? 'ok' : ($c['required'] ? 'fail' : 'warn');
        $icon  = $c['ok'] ? '&#10004;' : ($c['required'] ? '&#10008;' : '&#9888;');
        ?>
        <div class="check-item <?= $class ?>">
            <span class="icon"><?= $icon ?></span>
            <span class="name"><?= htmlspecialchars($c['name']) ?></span>
            <span class="val"><?= htmlspecialchars($c['actual']) ?></span>
        </div>
    <?php endforeach; ?>
    </div>

    <?php if (!$allRequired): ?>
    <div class="alert alert-error">
        Algunos requisitos obligatorios no se cumplen. Corrige los errores marcados con &#10008; antes de continuar.
    </div>
    <?php else: ?>

    <form method="post" action="install.php">
    <input type="hidden" name="step" value="install">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">

    <!-- ── DB ── -->
    <div class="section-divider">
        <h2>2. Base de datos MySQL</h2>
        <small style="color:#666">Crea la BD y usuario desde Panel Hostinger &gt; Bases de datos &gt; MySQL</small>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>Host</label>
            <input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
        </div>
        <div class="form-group">
            <label>Puerto</label>
            <input type="text" name="db_port" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>" required>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Nombre BD</label>
            <input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" required placeholder="u123456789_ats">
        </div>
        <div class="form-group">
            <label>Usuario BD</label>
            <input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required placeholder="u123456789_ats_user">
        </div>
    </div>
    <div class="form-group">
        <label>Password BD</label>
        <input type="password" name="db_pass" value="" required>
    </div>

    <!-- ── APP ── -->
    <div class="section-divider">
        <h2>3. Configuracion de la aplicacion</h2>
    </div>

    <div class="form-group">
        <label>URL del sitio</label>
        <input type="url" name="app_url" value="<?= htmlspecialchars($_POST['app_url'] ?? $autoUrl) ?>" required>
        <small>Sin / al final. Ej: https://tu-dominio.com</small>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Proveedor IA</label>
            <select name="ai_provider">
                <option value="openai" <?= ($_POST['ai_provider'] ?? 'openai') === 'openai' ? 'selected' : '' ?>>OpenAI (GPT-4o)</option>
                <option value="gemini" <?= ($_POST['ai_provider'] ?? '') === 'gemini' ? 'selected' : '' ?>>Google Gemini</option>
            </select>
        </div>
        <div class="form-group">
            <label>Precio CLP</label>
            <input type="number" name="price_clp" value="<?= htmlspecialchars($_POST['price_clp'] ?? '4990') ?>" min="100" required>
        </div>
    </div>

    <!-- ── EMAIL ── -->
    <div class="section-divider">
        <h2>4. Correo SMTP</h2>
        <small style="color:#666">Hostinger SMTP: smtp.hostinger.com puerto 465</small>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>Host SMTP</label>
            <input type="text" name="mail_host" value="<?= htmlspecialchars($_POST['mail_host'] ?? 'smtp.hostinger.com') ?>">
        </div>
        <div class="form-group">
            <label>Puerto SMTP</label>
            <input type="text" name="mail_port" value="<?= htmlspecialchars($_POST['mail_port'] ?? '465') ?>">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Usuario SMTP</label>
            <input type="text" name="mail_user" value="<?= htmlspecialchars($_POST['mail_user'] ?? '') ?>" placeholder="info@tu-dominio.com">
        </div>
        <div class="form-group">
            <label>Password SMTP</label>
            <input type="password" name="mail_pass">
        </div>
    </div>
    <div class="form-group">
        <label>Email remitente (From)</label>
        <input type="email" name="mail_from" value="<?= htmlspecialchars($_POST['mail_from'] ?? '') ?>" placeholder="noreply@tu-dominio.com">
    </div>

    <!-- ── ADMIN ── -->
    <div class="section-divider">
        <h2>5. Usuario administrador</h2>
    </div>

    <div class="form-group">
        <label>Nombre</label>
        <input type="text" name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? 'Administrador') ?>" required>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Email admin</label>
            <input type="email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" required placeholder="admin@tu-dominio.com">
        </div>
        <div class="form-group">
            <label>Password admin</label>
            <input type="password" name="admin_pass" required minlength="8">
            <small>Minimo 8 caracteres</small>
        </div>
    </div>

    <!-- ── SUBMIT ── -->
    <div style="margin-top:30px;text-align:center">
        <button type="submit" class="btn" <?= $allRequired ? '' : 'disabled' ?>>
            Instalar ahora
        </button>
    </div>
    </form>

    <?php endif; // allRequired ?>

<?php endif; // step ?>

</div><!-- /card -->

<p style="text-align:center;color:#999;font-size:12px;padding:10px 0">
    CV Optimizer ATS &mdash; Instalador v1.0
</p>
</div><!-- /container -->
</body>
</html>
