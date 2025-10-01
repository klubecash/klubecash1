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

// Limpar cookies
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Redirecionar para login
header('Location: /login?success=' . urlencode('Logout realizado com sucesso!'));
exit;
?>