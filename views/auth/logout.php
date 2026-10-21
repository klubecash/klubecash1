<?php
/**
 * Logout - Klube Cash
 * views/auth/logout.php
 */

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../controllers/AuthController.php';

require_once __DIR__ . '/../../services/Auth/GrupoKlubeSsoException.php';
require_once __DIR__ . '/../../services/Auth/IdTokenVerifier.php';
require_once __DIR__ . '/../../services/Auth/GrupoKlubeSso.php';

// Iniciar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Quem entrou pelo login do Grupo Klube também encerra a sessão central ao sair.
$centralIdToken = ($_SESSION['login_method'] ?? null) === 'grupoklube' ? (string) ($_SESSION['grupoklube_id_token'] ?? '') : '';

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

$centralLogin = $centralIdToken !== '' ? \App\Services\Auth\GrupoKlubeSso::fromEnvironment(SITE_URL) : null;
if ($centralLogin !== null) {
    header('Location: ' . $centralLogin->logoutUrl($centralIdToken, SITE_URL . LOGIN_URL));
    exit;
}

// Redirecionar para login
header('Location: /login?success=' . urlencode('Logout realizado com sucesso!'));
exit;
?>
