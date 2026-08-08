<?php
/**
 * Thin Vercel adapter. Application behavior lives in App\Core\Kernel.
 */

declare(strict_types=1);

use App\Core\Kernel;

$root = dirname(__DIR__);
chdir($root);

$canonicalUrl = rtrim(getenv('SITE_URL') ?: 'https://www.klubecash.com', '/');
$canonicalHost = parse_url($canonicalUrl, PHP_URL_HOST);
$requestHost = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');

// Production must never leak deployment-specific *.vercel.app addresses.
if (
    getenv('VERCEL_ENV') === 'production'
    && $canonicalHost
    && $requestHost !== $canonicalHost
    && str_ends_with($requestHost, '.vercel.app')
) {
    header('Location: ' . $canonicalUrl . ($_SERVER['REQUEST_URI'] ?? '/'), true, 308);
    exit;
}

require_once $root . '/bootstrap/app.php';

(new Kernel($root))->handle();
