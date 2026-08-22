<?php

declare(strict_types=1);

namespace App\Core;

final class Kernel
{
    private Router $router;

    public function __construct(private string $root)
    {
        $this->router = new Router();
        $this->loadRoutes($root . '/routes/web.php');
        $this->loadRoutes($root . '/routes/api.php');
    }

    public function handle(): void
    {
        $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $path = '/' . trim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        $match = $this->router->match($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
        if ($match !== null) {
            if (!$this->authorize($match->route, $path)) {
                return;
            }
            foreach ($match->parameters as $name => $value) {
                $_GET[$name] = $value;
            }
            $this->execute(
                $match->route->target,
                str_starts_with($match->route->target, 'controllers/')
            );
            return;
        }

        if (http_response_code() === 405) {
            $this->respondError(405, 'Método não permitido.');
            return;
        }

        $legacyTarget = $this->legacyTarget($path);
        if ($legacyTarget !== null) {
            $canonicalPath = $this->router->canonicalPathForTarget(
                $legacyTarget,
                $_SERVER['REQUEST_METHOD'] ?? 'GET'
            );
            if ($canonicalPath !== null) {
                $query = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
                header('Location: ' . $canonicalPath . ($query ? '?' . $query : ''), true, 308);
                return;
            }

            // APIs legadas so podem existir quando ha uma rota canonica
            // declarada. Arquivos de diagnostico soltos nunca sao executados.
            if (str_starts_with($legacyTarget, 'api/')) {
                $this->respondError(404, 'Pagina nao encontrada.');
                return;
            }

            $this->execute($legacyTarget);
            return;
        }

        $this->respondError(404, 'Página não encontrada.');
    }

    private function loadRoutes(string $file): void
    {
        $definitions = require $file;
        foreach ($definitions as $definition) {
            $this->router->add(new RouteDefinition(
                $definition['methods'],
                $definition['path'],
                $definition['target'],
                $definition['name'],
                $definition['middleware'] ?? []
            ));
        }
    }

    private function legacyTarget(string $path): ?string
    {
        if (!preg_match(
            '#^/((?:api/[^/]+)|(?:views/(?:auth|client|stores|admin|blog|marketing)/[a-zA-Z0-9_.-]+)|politica-de-privacidade)\.php$#',
            $path,
            $match
        )) {
            return null;
        }

        $target = $match[1] . '.php';
        return is_file($this->root . '/' . $target) ? $target : null;
    }

    private function authorize(RouteDefinition $route, string $path): bool
    {
        $middleware = $route->middleware;
        $userType = (string) ($_SESSION['user_type'] ?? '');
        $authenticated = isset($_SESSION['user_id']) && $userType !== '';

        $requiredRole = null;
        foreach (['admin', 'store', 'client'] as $role) {
            if (in_array($role, $middleware, true)) {
                $requiredRole = $role;
                break;
            }
        }

        $needsAuthentication = in_array('auth', $middleware, true) || $requiredRole !== null;
        if ($needsAuthentication && !$authenticated) {
            return $this->denyRoute($path, 401, 'Autenticacao necessaria.');
        }

        $allowed = match ($requiredRole) {
            'admin' => $userType === 'admin',
            'store' => in_array($userType, ['loja', 'funcionario'], true)
                && (int) ($_SESSION['store_id'] ?? 0) > 0,
            'client' => $userType === 'cliente',
            default => true,
        };

        if (!$allowed) {
            return $this->denyRoute($path, 403, 'Acesso nao autorizado.');
        }

        return true;
    }

    private function denyRoute(string $path, int $status, string $message): bool
    {
        if (
            str_starts_with($path, '/api/')
            || str_contains($path, '/ajax/')
            || $path === '/cliente/actions'
        ) {
            http_response_code($status);
            header('Content-Type: application/json; charset=UTF-8');
            if (str_starts_with($path, '/api/v2/store/')) {
                echo json_encode([
                    'status' => 'error',
                    'message' => $message,
                    'requestId' => RequestContext::id(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                return false;
            }
            echo json_encode([
                'status' => false,
                'message' => $message,
                'request_id' => RequestContext::id(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return false;
        }

        if ($status === 401) {
            header('Location: /login?error=' . rawurlencode($message), true, 302);
            return false;
        }

        $this->respondError($status, $message);
        return false;
    }

    private function execute(string $target, bool $activateController = false): void
    {
        $absolute = $this->root . '/' . ltrim($target, '/');
        if (!is_file($absolute)) {
            $this->respondError(404, 'Página não encontrada.');
            return;
        }

        $_SERVER['SCRIPT_FILENAME'] = $absolute;
        $_SERVER['SCRIPT_NAME'] = '/' . ltrim($target, '/');
        if ($activateController) {
            // Apenas rotas canonicas com middleware validado podem ativar os
            // switches legados de controller.
            $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
        }
        chdir(dirname($absolute));
        require $absolute;
    }

    private function respondError(int $status, string $message): void
    {
        http_response_code($status);
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        if (str_starts_with($path, '/api/')) {
            header('Content-Type: application/json; charset=UTF-8');
            if (str_starts_with($path, '/api/v2/store/')) {
                echo json_encode([
                    'status' => 'error',
                    'message' => $message,
                    'requestId' => RequestContext::id(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                return;
            }
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => $status === 404 ? 'NOT_FOUND' : 'METHOD_NOT_ALLOWED',
                    'message' => $message,
                ],
                'request_id' => RequestContext::id(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        header('Content-Type: text/plain; charset=UTF-8');
        echo $message;
    }
}
