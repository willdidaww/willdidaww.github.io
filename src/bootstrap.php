<?php
declare(strict_types=1);

/**
 * Bootstrap aplikasi: autoloader, konfigurasi, container ringan.
 */

$root = dirname(__DIR__);

// --- Autoloader PSR-4 sederhana untuk namespace App\ -> src/ ---
spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = $root . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

/** @var array $config */
$config = require $root . '/config/config.php';

date_default_timezone_set($config['app']['tz'] ?? 'UTC');

// Pastikan folder storage ada
foreach ([$config['storage']['uploads'], $config['storage']['logs']] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

// Error handling terpusat (analog Sentry: di produksi kirim ke error monitoring)
$logger = new App\Core\Logger($config['storage']['logs'] . '/app.log');

set_error_handler(static function (int $severity, string $message, string $file, int $line) use ($logger): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    $logger->error("PHP error [$severity] $message in $file:$line");
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $e) use ($logger, $config): void {
    $logger->error('Uncaught: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (!empty($config['app']['debug'])) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "500 Internal Server Error\n\n" . $e . "\n";
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<h1>500 — Terjadi kesalahan</h1><p>Silakan coba lagi atau hubungi administrator.</p>';
    }
});

// Inisialisasi container global sederhana
App\Core\App::init($config, $logger);

return $config;
