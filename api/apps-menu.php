<?php

declare(strict_types=1);

// Menu "Apps" para os paineis em Next (lojista e admin): devolve os apps do Grupo Klube que a pessoa logada pode abrir.
// A lista foi guardada na sessao no login unico; sem ela (login por senha, sessao antiga) responde { "menu": null }.

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store');

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../services/Auth/GrupoKlubeApps.php';

use App\Services\Auth\GrupoKlubeApps;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Metodo nao permitido.']);
    exit;
}

if (!AuthController::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Sessao expirada. Faca login novamente.']);
    exit;
}

echo json_encode(['status' => true, 'menu' => GrupoKlubeApps::menu()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
