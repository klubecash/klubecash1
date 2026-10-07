<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store');
if (getenv('VPS_WORKERS_ENABLED') === 'false') {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Processador desativado neste ambiente.']);
    exit;
}
$secret = trim((string) getenv('CRON_SECRET'));
if ($secret === '' || !hash_equals('Bearer ' . $secret, (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''))) {
    http_response_code($secret === '' ? 503 : 401);
    echo json_encode(['success' => false, 'message' => 'Processador interno indisponível ou acesso não autorizado.']);
    exit;
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/Giftback/GiftbackLedger.php';
try {
    $result = (new \App\Services\Giftback\GiftbackLedger(Database::getConnection()))->expireDue(max(1, min(100, (int) ($_GET['limit'] ?? 100))));
    error_log('giftback.expiration ' . json_encode($result));
    http_response_code($result['failed'] > 0 ? 503 : 200);
    echo json_encode(['success' => $result['failed'] === 0, 'data' => $result]);
} catch (Throwable $error) {
    error_log('giftback.expiration.failed ' . get_class($error));
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Não foi possível processar os vencimentos.']);
}
