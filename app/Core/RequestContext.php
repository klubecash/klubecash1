<?php

declare(strict_types=1);

namespace App\Core;

final class RequestContext
{
    private static string $id = '';
    private static float $startedAt = 0.0;

    public static function initialize(): void
    {
        if (self::$id !== '') {
            return;
        }

        self::$startedAt = microtime(true);
        self::$id = self::sanitize($_SERVER['HTTP_X_REQUEST_ID'] ?? '')
            ?: self::sanitize($_SERVER['HTTP_X_VERCEL_ID'] ?? '')
            ?: bin2hex(random_bytes(12));

        if (PHP_SAPI !== 'cli' && headers_sent() === false) {
            header('X-Request-ID: ' . self::$id);
        }
    }

    public static function id(): string
    {
        return self::$id;
    }

    public static function durationMs(): int
    {
        return (int) round((microtime(true) - self::$startedAt) * 1000);
    }

    private static function sanitize(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9:_-]/', '', substr($value, 0, 160)) ?: '';
    }
}
