<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class Bootstrap
{
    private static bool $booted = false;

    public static function boot(string $root): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        date_default_timezone_set('America/Sao_Paulo');
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        ini_set('log_errors', '1');

        if (ob_get_level() === 0) {
            ob_start();
        }

        RequestContext::initialize();
        Logger::registerPhpHandlers();

        require_once $root . '/config/constants.php';
        require_once $root . '/config/database.php';

        self::configureSession();

        set_exception_handler(static function (Throwable $exception): void {
            Logger::error('request.unhandled_exception', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            if (headers_sent() === false) {
                http_response_code(500);
            }

            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
            if (str_starts_with($path, '/api/')) {
                if (headers_sent() === false) {
                    header('Content-Type: application/json; charset=UTF-8');
                }
                echo json_encode([
                    'success' => false,
                    'error' => [
                        'code' => 'INTERNAL_ERROR',
                        'message' => 'Não foi possível concluir a solicitação.',
                    ],
                    'request_id' => RequestContext::id(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                return;
            }

            if (headers_sent() === false) {
                header('Content-Type: text/html; charset=UTF-8');
            }
            echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8">'
                . '<title>Erro interno</title><body><h1>Não foi possível carregar esta página.</h1>'
                . '<p>Tente novamente em alguns instantes.</p></body></html>';
        });
    }

    private static function configureSession(): void
    {
        if (PHP_SAPI === 'cli' || session_status() !== PHP_SESSION_NONE) {
            return;
        }

        // Use a Klube Cash-specific cookie name so stale PHPSESSID cookies
        // left by the legacy hosting cannot override the current session.
        $sessionName = trim((string) (getenv('SESSION_NAME') ?: 'KLCSESSID'));
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]{0,31}$/', $sessionName) !== 1) {
            $sessionName = 'KLCSESSID';
        }
        session_name($sessionName);

        $host = preg_replace('/:\\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
        // Host-only para impedir que outros subdominios fixem a sessao.
        $cookieDomain = '';

        $forwardedProtocol = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        $secureCookie = $cookieDomain !== ''
            || $forwardedProtocol === 'https'
            || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || getenv('VERCEL') === '1';
        $sessionLifetime = defined('SESSION_LIFETIME') ? (int) SESSION_LIFETIME : 3600;

        $defaultDriver = getenv('VERCEL') === '1' ? 'database' : 'files';
        $sessionDriver = strtolower(trim((string) (getenv('SESSION_DRIVER') ?: $defaultDriver)));
        if ($sessionDriver === 'database') {
            $handler = new DatabaseSessionHandler(\Database::getConnection(), $sessionLifetime);
            session_set_save_handler($handler, true);
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => $cookieDomain,
            'secure' => $secureCookie,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string) $sessionLifetime);
        session_start();
    }
}
