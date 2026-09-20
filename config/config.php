<?php
declare(strict_types=1);

/**
 * Konfigurasi aplikasi. Nilai bisa dioverride via file .env (KEY=VALUE per baris).
 */

$root = dirname(__DIR__);

// --- muat .env sederhana ---
$env = [];
$envFile = $root . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
        $env[trim($k)] = trim($v, " \t\"'");
    }
}

$get = static fn(string $key, $default = null) => $env[$key] ?? getenv($key) ?: $default;

return [
    'app' => [
        'name'  => $get('APP_NAME', 'Manajemen Biaya Bus AKAP'),
        'env'   => $get('APP_ENV', 'dev'),        // dev | staging | prod
        'debug' => filter_var($get('APP_DEBUG', 'true'), FILTER_VALIDATE_BOOL),
        'url'   => $get('APP_URL', 'http://127.0.0.1:8000'),
        'key'   => $get('APP_KEY', 'base64:devkey-change-me-in-production'),
        'tz'    => $get('APP_TZ', 'Asia/Jakarta'),
    ],
    'db' => [
        // Default SQLite (tanpa server). Untuk PostgreSQL set DB_DSN, DB_USER, DB_PASS.
        'dsn'  => $get('DB_DSN', 'sqlite:' . $root . '/storage/app.sqlite'),
        'user' => $get('DB_USER', null),
        'pass' => $get('DB_PASS', null),
    ],
    'session' => [
        'name'     => 'akap_session',
        'lifetime' => (int) $get('SESSION_LIFETIME', 60 * 60 * 12), // 12 jam
    ],
    'storage' => [
        'root'    => $root . '/storage',
        'uploads' => $root . '/storage/uploads',
        'logs'    => $root . '/storage/logs',
        // Untuk S3-compatible nanti: isi driver=s3 + kredensial.
        'driver'  => $get('STORAGE_DRIVER', 'local'),
    ],
    'security' => [
        'login_max_attempts' => (int) $get('LOGIN_MAX_ATTEMPTS', 5),
        'login_window_sec'   => (int) $get('LOGIN_WINDOW_SEC', 300),
        'upload_max_bytes'   => (int) $get('UPLOAD_MAX_BYTES', 5 * 1024 * 1024),
        'upload_allowed'     => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
    ],
    'root' => $root,
];
