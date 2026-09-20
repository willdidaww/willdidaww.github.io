<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Abstraksi request HTTP.
 */
final class Request
{
    public string $method;
    public string $path;
    public array $query;
    public array $body;
    public array $files;
    public array $params = []; // parameter dari route (mis. /rit/{id})

    public static function capture(): self
    {
        $r = new self();
        $r->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $r->path = rtrim(parse_url($uri, PHP_URL_PATH) ?: '/', '/') ?: '/';
        $r->query = $_GET ?? [];
        $r->files = $_FILES ?? [];

        // Body: form-urlencoded / multipart -> $_POST; json -> decode
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input') ?: '';
            $r->body = json_decode($raw, true) ?: [];
        } else {
            $r->body = $_POST ?? [];
        }

        // Method override (form tidak bisa PUT/DELETE) via _method
        if ($r->method === 'POST' && !empty($r->body['_method'])) {
            $r->method = strtoupper((string) $r->body['_method']);
        }
        return $r;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function file(string $key): ?array
    {
        $f = $this->files[$key] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $f;
    }

    public function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xhr = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
        return $xhr || str_contains($accept, 'application/json') || str_starts_with($this->path, '/api/');
    }

    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function userAgent(): string
    {
        return substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);
    }
}
