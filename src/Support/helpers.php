<?php
declare(strict_types=1);

use App\Core\View;
use App\Core\App;

/**
 * Helper global. Di-require dari bootstrap/public.
 */

if (!function_exists('e')) {
    function e(mixed $value): string { return View::e($value); }
}

if (!function_exists('url')) {
    function url(string $path = '/'): string
    {
        return rtrim((string) App::config('app.url'), '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('old')) {
    /** Ambil input lama dari session (untuk repopulate form setelah error validasi). */
    function old(string $key, mixed $default = ''): mixed
    {
        $old = \App\Core\Session::get('_old', []);
        return $old[$key] ?? $default;
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(\App\Core\Session::csrfToken()) . '">';
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e($method) . '">';
    }
}

if (!function_exists('rupiah')) {
    function rupiah(int|float|string|null $n): string
    {
        return 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');
    }
}

if (!function_exists('tgl')) {
    function tgl(?string $iso, bool $withTime = false): string
    {
        if (!$iso) { return '-'; }
        $ts = strtotime($iso);
        if ($ts === false) { return e($iso); }
        return date($withTime ? 'd/m/Y H:i' : 'd/m/Y', $ts);
    }
}

if (!function_exists('badge_class')) {
    /** Map status ke kelas warna badge. */
    function badge_class(string $status): string
    {
        return match ($status) {
            'aktif', 'disetujui', 'lunas', 'selesai', 'approved' => 'ok',
            'menunggu', 'belum_cair', 'dilaporkan', 'pending'     => 'warn',
            'ditolak', 'nonaktif', 'batal', 'anomali', 'rejected' => 'bad',
            default => 'muted',
        };
    }
}
