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

        $host = preg_replace('/:\\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
        $cookieDomain = str_ends_with($host, 'klubecash.com') ? '.klubecash.com' : '';

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => $cookieDomain,
            'secure' => $cookieDomain !== '',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string) (defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 3600));
        session_start();
    }
}
