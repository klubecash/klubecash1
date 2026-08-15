<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../controllers/AuthController.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * @param array<string, mixed> $payload
 */
function paymentsJsonResponse(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!AuthController::hasStoreAccess()) {
    paymentsJsonResponse([
        'status' => false,
        'message' => 'Autenticação necessária.',
    ], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    paymentsJsonResponse([
        'status' => false,
        'message' => 'Método não permitido.',
    ], 405);
}

$payload = $_POST;
if ($payload === [] && str_contains(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
    $decoded = json_decode((string) file_get_contents('php://input'), true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

if (($payload['action'] ?? '') !== 'criar_pagamento') {
    paymentsJsonResponse([
        'status' => false,
        'message' => 'Ação inválida.',
    ], 400);
}

if (($payload['metodo_pagamento'] ?? 'pix_openpix') !== 'pix_openpix') {
    paymentsJsonResponse([
        'status' => false,
        'message' => 'Método de pagamento inválido para este fluxo.',
    ], 400);
}

$rawTransactionIds = $payload['transacoes'] ?? [];
if (!is_array($rawTransactionIds) || $rawTransactionIds === [] || count($rawTransactionIds) > 200) {
    paymentsJsonResponse([
        'status' => false,
        'message' => 'Selecione entre 1 e 200 transações.',
    ], 400);
}

$transactionIds = [];
foreach ($rawTransactionIds as $rawId) {
    $id = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        paymentsJsonResponse([
            'status' => false,
            'message' => 'A lista de transações contém um identificador inválido.',
        ], 400);
    }
    $transactionIds[] = (int) $id;
}

$transactionIds = array_values(array_unique($transactionIds));
if (count($transactionIds) !== count($rawTransactionIds)) {
    paymentsJsonResponse([
        'status' => false,
        'message' => 'A lista de transações contém identificadores duplicados.',
    ], 400);
}

$storeId = (int) AuthController::getStoreId();
$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($storeId <= 0 || $userId <= 0) {
    paymentsJsonResponse([
        'status' => false,
        'message' => 'A sessão não está associada a uma loja.',
    ], 403);
}

$db = null;

try {
    $db = Database::getConnection();
    $db->beginTransaction();

    $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
    $lockStmt = $db->prepare("
        SELECT id, valor_cliente, valor_admin
        FROM transacoes_cashback
        WHERE id IN ($placeholders)
          AND loja_id = ?
          AND status = 'pendente'
        FOR UPDATE
    ");
    $lockStmt->execute(array_merge($transactionIds, [$storeId]));
    $transactions = $lockStmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($transactions) !== count($transactionIds)) {
        $db->rollBack();
        paymentsJsonResponse([
            'status' => false,
            'message' => 'Uma ou mais transações não pertencem à loja ou deixaram de estar pendentes.',
        ], 409);
    }

    $totalCents = 0;
    foreach ($transactions as $transaction) {
        $totalCents += (int) round((float) $transaction['valor_cliente'] * 100);
        $totalCents += (int) round((float) $transaction['valor_admin'] * 100);
    }

    if ($totalCents <= 0) {
        $db->rollBack();
        paymentsJsonResponse([
            'status' => false,
            'message' => 'As transações selecionadas não possuem comissão a pagar.',
        ], 422);
    }

    $paymentStmt = $db->prepare("
        INSERT INTO pagamentos_comissao
            (loja_id, criado_por, valor_total, metodo_pagamento, status, data_registro)
        VALUES
            (?, ?, ?, 'pix_openpix', 'pendente', NOW())
    ");
    $paymentStmt->execute([$storeId, $userId, number_format($totalCents / 100, 2, '.', '')]);
    $paymentId = (int) $db->lastInsertId();

    $associationStmt = $db->prepare("
        INSERT INTO pagamentos_transacoes (pagamento_id, transacao_id)
        VALUES (?, ?)
    ");
    foreach ($transactionIds as $transactionId) {
        $associationStmt->execute([$paymentId, $transactionId]);
    }

    $updateStmt = $db->prepare("
        UPDATE transacoes_cashback
        SET status = 'pagamento_pendente'
        WHERE id IN ($placeholders)
          AND loja_id = ?
          AND status = 'pendente'
    ");
    $updateStmt->execute(array_merge($transactionIds, [$storeId]));
    if ($updateStmt->rowCount() !== count($transactionIds)) {
        throw new RuntimeException('Falha de concorrência ao reservar as transações.');
    }

    $db->commit();

    paymentsJsonResponse([
        'status' => true,
        'payment_id' => $paymentId,
        'message' => 'Pagamento criado com sucesso.',
    ], 201);
} catch (Throwable $e) {
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log('Payments API error: ' . $e->getMessage());
    paymentsJsonResponse([
        'status' => false,
        'message' => 'Não foi possível criar o pagamento. Tente novamente.',
    ], 500);
}
