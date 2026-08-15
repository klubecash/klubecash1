<?php
/**
 * Logout - Klube Cash
 * views/auth/logout.php
 */

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../controllers/AuthController.php';

// Iniciar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fazer logout através do AuthController
$result = AuthController::logout();

// Remover os cookies de autenticacao host-only. Durante a transicao,
// expirar tambem as variantes antigas compartilhadas pelo dominio.
$secureCookie = getenv('VERCEL') === '1'
    || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
$expiredCookie = [
    'expires' => time() - 3600,
    'path' => '/',
    'domain' => '',
    'secure' => $secureCookie,
    'httponly' => true,
    'samesite' => 'Lax',
];

foreach (array_unique([session_name(), 'KLCSESSID', 'PHPSESSID', 'jwt_token']) as $cookieName) {
    setcookie($cookieName, '', $expiredCookie);
}

$siteHost = strtolower((string) parse_url(SITE_URL, PHP_URL_HOST));
$legacyDomain = preg_replace('/^www\./', '', $siteHost);
if ($legacyDomain !== '' && filter_var($legacyDomain, FILTER_VALIDATE_IP) === false) {
    $expiredCookie['domain'] = '.' . $legacyDomain;
    foreach (array_unique([session_name(), 'KLCSESSID', 'PHPSESSID', 'jwt_token']) as $cookieName) {
        setcookie($cookieName, '', $expiredCookie);
    }
}

// Redirecionar para login
header('Location: /login?success=' . urlencode('Logout realizado com sucesso!'));
exit;
?>
