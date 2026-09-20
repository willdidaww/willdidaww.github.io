<?php
declare(strict_types=1);

/**
 * Front controller — semua request masuk ke sini.
 */

use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;

$config = require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/Support/helpers.php';

View::setPath($config['root'] . '/resources/views');
Session::start();

// Serve file upload (di produksi ini dilayani oleh web server / S3 langsung)
$req = Request::capture();
if (str_starts_with($req->path, '/uploads/')) {
    $file = $config['storage']['root'] . '/' . ltrim($req->path, '/');
    $real = realpath($file);
    $base = realpath($config['storage']['uploads']);
    if ($real && $base && str_starts_with($real, $base) && is_file($real)) {
        $mime = mime_content_type($real) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($real));
        header('Cache-Control: private, max-age=3600');
        readfile($real);
        exit;
    }
    http_response_code(404);
    echo 'File tidak ditemukan';
    exit;
}

// Layani aset statis (css/js/manifest) saat memakai router script (php -S ... index.php).
// Di produksi, web server melayani /assets langsung tanpa PHP.
if (str_starts_with($req->path, '/assets/')) {
    $asset = __DIR__ . '/' . ltrim($req->path, '/');
    $real = realpath($asset);
    $base = realpath(__DIR__ . '/assets');
    if ($real && $base && str_starts_with($real, $base) && is_file($real)) {
        $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'css' => 'text/css', 'js' => 'application/javascript',
            'webmanifest', 'json' => 'application/json',
            'png' => 'image/png', 'jpg', 'jpeg' => 'image/jpeg', 'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
        header('Content-Type: ' . $mime . '; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        readfile($real);
        exit;
    }
    http_response_code(404);
    exit;
}

$router = new Router();
(require dirname(__DIR__) . '/routes/web.php')($router);

$response = $router->dispatch($req);
$response->send();
