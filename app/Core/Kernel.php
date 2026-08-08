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
            foreach ($match->parameters as $name => $value) {
                $_GET[$name] = $value;
            }
            $this->execute($match->route->target);
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
            '#^/((?:api/[^/]+)|(?:controllers/[a-zA-Z0-9_.-]+)|(?:views/(?:auth|client|stores|admin|blog|marketing)/[a-zA-Z0-9_.-]+)|politica-de-privacidade)\.php$#',
            $path,
            $match
        )) {
            return null;
        }

        $target = $match[1] . '.php';
        return is_file($this->root . '/' . $target) ? $target : null;
    }

    private function execute(string $target): void
    {
        $absolute = $this->root . '/' . ltrim($target, '/');
        if (!is_file($absolute)) {
            $this->respondError(404, 'Página não encontrada.');
            return;
        }

        $_SERVER['SCRIPT_FILENAME'] = $absolute;
        $_SERVER['SCRIPT_NAME'] = '/' . ltrim($target, '/');
        chdir(dirname($absolute));
        require $absolute;
    }

    private function respondError(int $status, string $message): void
    {
        http_response_code($status);
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        if (str_starts_with($path, '/api/')) {
            header('Content-Type: application/json; charset=UTF-8');
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
