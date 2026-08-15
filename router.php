<?php

declare(strict_types=1);

$root = __DIR__;
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

// Nunca permita que o servidor local entregue .env, .git ou outros dotfiles.
if (preg_match('#(?:^|/)\.[^/]+(?:/|$)#', $path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Página não encontrada.';
    return true;
}

if (preg_match('#^/(?:config|database|logs|scripts/quality)(?:/|$)#', $path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Página não encontrada.';
    return true;
}

$requestedFile = realpath($root . $path);

if (
    $path !== '/'
    && $requestedFile !== false
    && str_starts_with($requestedFile, $root)
    && is_file($requestedFile)
    && strtolower((string) pathinfo($requestedFile, PATHINFO_EXTENSION)) !== 'php'
) {
    return false;
}

require $root . '/api/vercel-router.php';
