<?php
/**
 * Thin Vercel adapter. Application behavior lives in App\Core\Kernel.
 */

declare(strict_types=1);

use App\Core\Kernel;

$root = dirname(__DIR__);
chdir($root);

// Canonical host redirects belong to Vercel's public routing layer. This PHP
// service is also reached through a private service binding whose Host may be
// deployment-specific; redirecting here would break Next.js -> PHP requests.

require_once $root . '/bootstrap/app.php';

(new Kernel($root))->handle();
