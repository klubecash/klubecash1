<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/PaymentReceipt.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$respondError = static function (int $status, string $message): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    echo json_encode(
        ['status' => false, 'message' => $message],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    $respondError(405, 'Metodo nao permitido.');
}

if (!AuthController::isAuthenticated()) {
    $respondError(401, 'Autenticacao necessaria.');
}

if (!AuthController::hasStoreAccess() && !AuthController::isAdmin()) {
    $respondError(403, 'Acesso nao autorizado.');
}

$paymentId = filter_input(
    INPUT_GET,
    'payment_id',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($paymentId === false || $paymentId === null) {
    $respondError(400, 'Pagamento invalido.');
}

try {
    $db = Database::getConnection();
    $paymentStmt = $db->prepare(
        'SELECT pc.id, pc.loja_id, pc.comprovante, l.usuario_id AS store_user_id
         FROM pagamentos_comissao pc
         INNER JOIN lojas l ON l.id = pc.loja_id
         WHERE pc.id = :payment_id
         LIMIT 1'
    );
    $paymentStmt->execute([':payment_id' => $paymentId]);
    $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        $respondError(404, 'Comprovante nao encontrado.');
    }

    if (
        AuthController::hasStoreAccess()
        && (int) $payment['loja_id'] !== (int) AuthController::getStoreId()
    ) {
        $respondError(403, 'Voce nao tem acesso a este comprovante.');
    }

    $receipt = PaymentReceipt::findByPaymentId((int) $paymentId);

    if ($receipt === null && !empty($payment['comprovante'])) {
        $receipt = PaymentReceipt::loadLegacyFile(
            dirname(__DIR__) . '/uploads/comprovantes',
            (string) $payment['comprovante']
        );
    }

    if ($receipt === null) {
        $respondError(404, 'Comprovante nao encontrado.');
    }

    $contents = (string) $receipt['contents'];
    $mimeType = (string) ($receipt['mime_type'] ?? '');
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'application/pdf'];
    $declaredSize = (int) ($receipt['file_size'] ?? 0);

    if (
        !in_array($mimeType, $allowedMimeTypes, true)
        || $declaredSize <= 0
        || $declaredSize > PaymentReceipt::MAX_FILE_SIZE
        || strlen($contents) !== $declaredSize
    ) {
        error_log('Metadados invalidos no comprovante do pagamento ' . $paymentId);
        $respondError(500, 'Nao foi possivel abrir o comprovante.');
    }

    $storedHash = (string) ($receipt['sha256'] ?? '');
    if ($storedHash === '' || !hash_equals($storedHash, hash('sha256', $contents))) {
        error_log('Integridade invalida no comprovante do pagamento ' . $paymentId);
        $respondError(500, 'Nao foi possivel abrir o comprovante.');
    }

    $originalName = (string) $receipt['original_name'];
    $asciiName = (string) preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
    if ($asciiName === '' || $asciiName === '.' || $asciiName === '..') {
        $asciiName = 'comprovante';
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . strlen($contents));
    header('Content-Disposition: inline; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($originalName));
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');

    echo $contents;
} catch (Throwable $exception) {
    error_log('Erro ao servir comprovante do pagamento ' . $paymentId . ': ' . $exception->getMessage());
    $respondError(500, 'Nao foi possivel abrir o comprovante.');
}
