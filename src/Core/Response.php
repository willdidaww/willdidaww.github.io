<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = [],
    ) {}

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    public static function redirect(string $to, int $status = 302): self
    {
        return new self('', $status, ['Location' => $to]);
    }

    public static function download(string $content, string $filename, string $mime): self
    {
        return new self($content, 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => (string) strlen($content),
        ]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $k => $v) {
            header("$k: $v");
        }
        // Header keamanan dasar
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: same-origin');
        echo $this->body;
    }
}
