<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/TransactionController.php';
require_once __DIR__ . '/../controllers/StoreBalancePaymentController.php';

$respond = static function (int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    $respond(405, ['status' => false, 'message' => 'Metodo nao permitido.']);
}

if (!AuthController::isAuthenticated()) {
    $respond(401, ['status' => false, 'message' => 'Sessao expirada. Faca login novamente.']);
}

if (!AuthController::hasStoreAccess()) {
    $respond(403, ['status' => false, 'message' => 'Acesso restrito a lojas parceiras.']);
}

$storeId = (int) (AuthController::getStoreId() ?? 0);
if ($storeId <= 0) {
    $respond(422, ['status' => false, 'message' => 'Conta sem loja associada.']);
}

$action = trim((string) ($_POST['action'] ?? ''));

switch ($action) {
    case 'transaction_details':
        $transactionId = (int) ($_POST['transaction_id'] ?? 0);
        if ($transactionId <= 0) {
            $respond(422, ['status' => false, 'message' => 'Transacao invalida.']);
        }
        $result = TransactionController::getTransactionDetails($transactionId);
        break;

    case 'payment_details':
        $paymentId = (int) ($_POST['payment_id'] ?? 0);
        if ($paymentId <= 0) {
            $respond(422, ['status' => false, 'message' => 'Pagamento invalido.']);
        }
        $result = TransactionController::getPaymentDetails($paymentId);
        break;

    case 'payment_details_with_balance':
        $paymentId = (int) ($_POST['payment_id'] ?? 0);
        if ($paymentId <= 0) {
            $respond(422, ['status' => false, 'message' => 'Pagamento invalido.']);
        }
        $result = TransactionController::getPaymentDetailsWithBalance($paymentId);
        break;

    case 'get_store_balance_repasse_details':
        $repasseId = (int) ($_POST['repasse_id'] ?? 0);
        if ($repasseId <= 0) {
            $respond(422, ['status' => false, 'message' => 'Repasse invalido.']);
        }
        // O store_id e sempre derivado da sessao, nunca do corpo da requisicao.
        $result = StoreBalancePaymentController::getStoreBalanceRepasseDetails($repasseId, $storeId);
        break;

    default:
        $respond(400, ['status' => false, 'message' => 'Acao invalida.']);
}

if (!($result['status'] ?? false)) {
    $respond(404, $result);
}

$respond(200, $result);
