<?php
// api/get-store-id.php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

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
    $db = Database::getConnection();

    if ($userType === USER_TYPE_STORE) {
        $stmt = $db->prepare("
            SELECT l.id
            FROM lojas l
            INNER JOIN usuarios u ON u.id = l.usuario_id
            WHERE u.id = :user_id
              AND u.tipo = :user_type
              AND u.status = :user_status
              AND l.status = 'aprovado'
            ORDER BY l.id ASC
            LIMIT 1
        ");
    } else {
        $stmt = $db->prepare("
            SELECT l.id
            FROM usuarios u
            INNER JOIN lojas l ON l.id = u.loja_vinculada_id
            WHERE u.id = :user_id
              AND u.tipo = :user_type
              AND u.status = :user_status
              AND l.status = 'aprovado'
            LIMIT 1
        ");
    }

    $stmt->execute([
        ':user_id' => $userId,
        ':user_type' => $userType,
        ':user_status' => USER_ACTIVE,
    ]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$store || (int) $store['id'] <= 0) {
        respondStoreIdError(403, 'Usuário sem loja ativa vinculada');
    }

    $storeId = (int) $store['id'];
    $_SESSION['store_id'] = $storeId;
    $_SESSION['loja_vinculada_id'] = $storeId;

    // Mantém o contrato de sucesso consumido pelo frontend.
    echo json_encode(['store_id' => $storeId]);
} catch (Throwable $e) {
    error_log('Erro ao detectar store_id autenticado: ' . $e->getMessage());
    respondStoreIdError(500, 'Erro interno do servidor');
}
