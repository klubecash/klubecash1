<?php

declare(strict_types=1);

namespace App\Core;

use ErrorException;

final class Logger
{
    public static function registerPhpHandlers(): void
    {
        set_error_handler(static function (
            int $severity,
            string $message,
            string $file,
            int $line
        ): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            self::warning('php.runtime', [
                'severity' => $severity,
                'message' => $message,
                'file' => $file,
                'line' => $line,
            ]);
            return true;
        });

        register_shutdown_function(static function (): void {
            $error = error_get_last();
            if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }

            self::error('php.fatal', [
                'severity' => $error['type'],
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
            ]);
        });
    }

    public static function info(string $event, array $context = []): void
    {
        self::write('info', $event, $context);
    }

    public static function warning(string $event, array $context = []): void
    {
        self::write('warning', $event, $context);
    }

    public static function error(string $event, array $context = []): void
    {
        self::write('error', $event, $context);
    }

    private static function write(string $level, string $event, array $context): void
    {
        unset($context['password'], $context['senha'], $context['token'], $context['secret']);

        error_log((string) json_encode([
            'timestamp' => gmdate('c'),
            'level' => $level,
            'event' => $event,
            'request_id' => RequestContext::id(),
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'path' => parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH),
            'user_id' => $_SESSION['user_id'] ?? null,
            'duration_ms' => RequestContext::durationMs(),
            'context' => $context,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
