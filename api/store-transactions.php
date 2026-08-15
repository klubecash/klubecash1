<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/TransactionController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['status' => false, 'message' => 'Metodo nao permitido.']);
    exit;
}

if (!AuthController::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Sessao expirada. Faca login novamente.']);
    exit;
}

if (!AuthController::hasStoreAccess()) {
    http_response_code(403);
    echo json_encode(['status' => false, 'message' => 'Acesso restrito a lojas parceiras.']);
    exit;
}

// O identificador da loja nunca vem do navegador. Isso impede que um lojista
// consulte transacoes de outra conta alterando o formulario da pagina.
$storeId = (int) (AuthController::getStoreId() ?? 0);
if ($storeId <= 0) {
    http_response_code(422);
    echo json_encode(['status' => false, 'message' => 'Conta sem loja associada.']);
    exit;
}

$filters = is_array($_POST['filters'] ?? null) ? $_POST['filters'] : [];
$page = max(1, (int) ($_POST['page'] ?? 1));

echo json_encode(
    TransactionController::getStoreTransactions($storeId, $filters, $page),
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
