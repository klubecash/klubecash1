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
            $file = $root . '/services/' . str_replace('\\', '/', substr($class, strlen($servicePrefix))) . '.php';
            if (is_file($file)) require_once $file;
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
