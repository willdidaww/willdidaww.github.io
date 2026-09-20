<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Logger file sederhana (terpusat). Di produksi bisa diarahkan ke Sentry/stderr.
 */
final class Logger
{
    public function __construct(private string $file) {}

    public function info(string $msg): void  { $this->write('INFO', $msg); }
    public function warn(string $msg): void  { $this->write('WARN', $msg); }
    public function error(string $msg): void { $this->write('ERROR', $msg); }

    private function write(string $level, string $msg): void
    {
        $line = sprintf("[%s] %s: %s\n", date('c'), $level, $msg);
        @file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX);
    }
}
