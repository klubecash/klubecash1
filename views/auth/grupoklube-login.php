<?php
// views/auth/grupoklube-login.php
// "Entrar com Grupo Klube": sends the person to the central login (also the Portal's "Abrir" address).

declare(strict_types=1);

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../services/Auth/GrupoKlubeSsoException.php';
require_once __DIR__ . '/../../services/Auth/IdTokenVerifier.php';
require_once __DIR__ . '/../../services/Auth/GrupoKlubeSso.php';

use App\Services\Auth\GrupoKlubeSso;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Cache-Control: no-store');

$returnTo = GrupoKlubeSso::safeReturnTo($_GET['returnTo'] ?? null);

// Already signed in to KlubeCash: open the panel directly.
if (isset($_SESSION['user_id']) && (string) ($_SESSION['user_type'] ?? '') !== '') {
    header('Location: ' . ($returnTo ?? AuthController::dashboardFor((string) $_SESSION['user_type'])), true, 302);
    exit;
}

$sso = GrupoKlubeSso::fromEnvironment(SITE_URL);
if ($sso === null) {
    header('Location: ' . LOGIN_URL, true, 302);
    exit;
}

$authorizationUrl = $sso->begin($returnTo);
session_write_close();
header('Location: ' . $authorizationUrl, true, 302);
exit;
