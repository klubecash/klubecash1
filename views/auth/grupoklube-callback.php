<?php
// views/auth/grupoklube-callback.php
// Return from the Grupo Klube central login: confirms the login and opens the KlubeCash session
// of the account the central login verified (by its KlubeCash id, never by e-mail).

declare(strict_types=1);

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../services/Auth/GrupoKlubeSsoException.php';
require_once __DIR__ . '/../../services/Auth/IdTokenVerifier.php';
require_once __DIR__ . '/../../services/Auth/GrupoKlubeSso.php';
require_once __DIR__ . '/../../services/Auth/GrupoKlubeAccounts.php';

use App\Services\Auth\GrupoKlubeAccounts;
use App\Services\Auth\GrupoKlubeSso;
use App\Services\Auth\GrupoKlubeSsoException;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Cache-Control: no-store');

$fail = static function (string $message): never {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    header('Location: ' . LOGIN_URL . '?error=' . rawurlencode($message), true, 302);
    exit;
};

$sso = GrupoKlubeSso::fromEnvironment(SITE_URL);
if ($sso === null) {
    $fail('O login do Grupo Klube não está disponível no momento.');
}

try {
    $login = $sso->complete($_GET);
    $db = Database::getConnection();
    $account = (new GrupoKlubeAccounts($db))->forLogin($login, [$sso, 'registerLink']);
    if ($account['outcome'] === GrupoKlubeAccounts::NEEDS_PASSWORD) {
        // This e-mail already has a KlubeCash account: the central login asks for its password once, which links it.
        $authorizationUrl = $sso->begin($login['returnTo'], null, true);
        session_write_close();
        header('Location: ' . $authorizationUrl, true, 302);
        exit;
    }
    $user = $account['user'];
    if (!$user || ($user['tipo_cliente'] ?? null) === 'visitante') {
        error_log('auth.grupoklube.account_unavailable');
        $fail('Sua conta KlubeCash não está disponível para este login.');
    }

    $result = AuthController::startSessionForUser($db, $user);
    if (!$result['status']) {
        $fail($result['message']);
    }
    // Kept for the logout, which also ends the central session.
    $_SESSION['login_method'] = 'grupoklube';
    $_SESSION['grupoklube_id_token'] = $login['idToken'];
    if (!AuthController::persistLogin($result['token'] ?? '')) {
        $fail('Não foi possível concluir sua sessão. Tente novamente.');
    }
    error_log($account['outcome'] === GrupoKlubeAccounts::CREATED ? 'auth.grupoklube.account_created' : 'auth.grupoklube.login_completed');
    header('Location: ' . ($login['returnTo'] ?? AuthController::dashboardFor((string) $user['tipo'])), true, 302);
    exit;
} catch (GrupoKlubeSsoException $error) {
    error_log('auth.grupoklube.login_refused');
    $fail($error->getMessage());
} catch (Throwable $error) {
    error_log('auth.grupoklube.login_failed ' . get_class($error));
    $fail('Não foi possível concluir o login. Tente novamente em instantes.');
}
