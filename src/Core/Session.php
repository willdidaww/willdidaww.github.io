<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Wrapper session PHP + flash message + CSRF token.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $cfg = App::config('session');
        session_name($cfg['name'] ?? 'akap_session');
        session_set_cookie_params([
            'lifetime' => $cfg['lifetime'] ?? 43200,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            // 'secure' => true, // aktifkan saat HTTPS (produksi/staging)
        ]);
        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    // --- Flash messages ---
    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function takeFlash(): array
    {
        $flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flash;
    }

    // --- CSRF ---
    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        return is_string($token)
            && !empty($_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $token);
    }
}
