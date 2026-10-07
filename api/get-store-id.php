<?php
// api/get-store-id.php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../controllers/AuthController.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function respondStoreIdError(int $statusCode, string $message): void
{
    http_response_code($statusCode);
    echo json_encode([
        'status' => false,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$userType = $_SESSION['user_type'] ?? null;

if ($userId <= 0 || !$userType) {
    respondStoreIdError(401, 'Usuário não autenticado');
}

if (!in_array($userType, [USER_TYPE_STORE, USER_TYPE_EMPLOYEE], true)) {
    respondStoreIdError(403, 'Acesso restrito a lojas e funcionários autorizados');
}

try {
    $storeId = (int) (AuthController::getStoreId() ?? 0);
    if ($storeId <= 0) { respondStoreIdError(409, 'Escolha uma filial antes de continuar.'); }

    // Mantém o contrato de sucesso consumido pelo frontend.
    echo json_encode(['store_id' => $storeId]);
} catch (Throwable $e) {
    error_log('Erro ao detectar store_id autenticado: ' . $e->getMessage());
    respondStoreIdError(500, 'Erro interno do servidor');
}
