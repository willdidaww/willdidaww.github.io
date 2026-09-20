<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Renderer view berbasis PHP template + layout.
 */
final class View
{
    private static string $viewsPath = '';

    public static function setPath(string $path): void
    {
        self::$viewsPath = rtrim($path, '/');
    }

    /** Render view tanpa layout. */
    public static function render(string $name, array $data = []): string
    {
        $file = self::$viewsPath . '/' . str_replace('.', '/', $name) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View tidak ditemukan: $name ($file)");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }

    /** Render view di dalam layout (default: layouts/app). Konten -> $slot. */
    public static function page(string $name, array $data = [], string $layout = 'layouts/app'): string
    {
        $slot = self::render($name, $data);
        return self::render($layout, array_merge($data, ['slot' => $slot]));
    }

    /** Escape HTML. */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
