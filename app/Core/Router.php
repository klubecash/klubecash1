<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var RouteDefinition[] */
    private array $routes = [];

    public function add(RouteDefinition $route): void
    {
        $this->routes[] = $route;
    }

    public function match(string $method, string $path): ?RouteMatch
    {
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            $parameters = $this->matchPath($route->path, $path);
            if ($parameters === null) {
                continue;
            }

            if (!in_array(strtoupper($method), $route->methods, true)) {
                $allowedMethods = array_merge($allowedMethods, $route->methods);
                continue;
            }

            return new RouteMatch($route, $parameters);
        }

        if ($allowedMethods !== []) {
            $allowedMethods = array_values(array_unique($allowedMethods));
            header('Allow: ' . implode(', ', $allowedMethods));
            http_response_code(405);
        }

        return null;
    }

    /** @return RouteDefinition[] */
    public function all(): array
    {
        return $this->routes;
    }

    public function canonicalPathForTarget(string $target, string $method): ?string
    {
        $method = strtoupper($method);

        foreach ($this->routes as $route) {
            if (
                $route->target === $target
                && !str_contains($route->path, '{')
                && in_array($method, $route->methods, true)
            ) {
                return $route->path;
            }
        }

        return null;
    }

    private function matchPath(string $routePath, string $requestPath): ?array
    {
        $parameterNames = [];
        $pattern = '';
        $offset = 0;

        preg_match_all(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}/',
            $routePath,
            $placeholders,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        foreach ($placeholders as $placeholder) {
            $token = $placeholder[0][0];
            $position = $placeholder[0][1];
            $name = $placeholder[1][0];
            $expression = isset($placeholder[2][0]) && $placeholder[2][0] !== ''
                ? $placeholder[2][0]
                : '[^/]+';

            $pattern .= preg_quote(substr($routePath, $offset, $position - $offset), '#');
            $pattern .= '(' . $expression . ')';
            $parameterNames[] = $name;
            $offset = $position + strlen($token);
        }

        $pattern .= preg_quote(substr($routePath, $offset), '#');

        if (!preg_match('#^' . $pattern . '/?$#', $requestPath, $matches)) {
            return null;
        }

        array_shift($matches);
        return array_combine($parameterNames, array_map('rawurldecode', $matches)) ?: [];
    }
}
