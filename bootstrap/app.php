<?php

declare(strict_types=1);

use App\Core\Bootstrap;

if (defined('KLUBECASH_BOOTSTRAPPED')) {
    return;
}

define('KLUBECASH_BOOTSTRAPPED', true);

$root = dirname(__DIR__);
$composerAutoload = $root . '/vendor/autoload.php';

if (is_file($composerAutoload)) {
    require_once $composerAutoload;
} else {
    spl_autoload_register(static function (string $class) use ($root): void {
        $servicePrefix = 'App\\Services\\';
        if (str_starts_with($class, $servicePrefix)) {
            $relative = str_replace('\\', '/', substr($class, strlen($servicePrefix)));
            $file = $root . '/services/' . $relative . '.php';
            if (is_file($file)) {
                require_once $file;
                return;
            }

            // Os namespaces Store e Admin foram introduzidos sobre diretorios
            // legados em minusculas (services/store e services/admin). Windows
            // resolve essa diferenca de caixa, mas o Linux da producao nao.
            // Preserve os caminhos existentes e aplique a compatibilidade no
            // carregador central para todas as classes desses modulos.
            $segments = explode('/', $relative);
            $segments[0] = lcfirst($segments[0]);
            $legacyFile = $root . '/services/' . implode('/', $segments) . '.php';
            if (is_file($legacyFile)) {
                require_once $legacyFile;
            }
            return;
        }
        $prefix = 'App\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $file = $root . '/app/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    });
}

Bootstrap::boot($root);
