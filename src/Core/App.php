<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Container/service-locator ringan. Menyimpan config, DB, logger secara global.
 */
final class App
{
    private static array $config = [];
    private static ?Database $db = null;
    private static ?Logger $logger = null;

    public static function init(array $config, Logger $logger): void
    {
        self::$config = $config;
        self::$logger = $logger;
    }

    public static function config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return self::$config;
        }
        $value = self::$config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public static function db(): Database
    {
        if (self::$db === null) {
            self::$db = new Database(
                (string) self::config('db.dsn'),
                self::config('db.user'),
                self::config('db.pass'),
            );
        }
        return self::$db;
    }

    public static function logger(): Logger
    {
        return self::$logger;
    }
}
