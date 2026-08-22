<?php
declare(strict_types=1);
use App\Core\Logger;
use App\Services\WhatsApp\PdoWahaWebhookStore;
use App\Services\WhatsApp\WahaConfig;
use App\Services\WhatsApp\CurlWahaHttpClient;
use App\Services\WhatsApp\WahaInboundProcessor;
use App\Services\WhatsApp\WhatsAppMenuConfig;
use App\Services\WhatsApp\WahaService;
use App\Services\WhatsApp\WahaWebhookHandler;
use App\Services\WhatsApp\WahaSchemaManager;
header('Content-Type: application/json; charset=UTF-8');
$rawBody = (string) file_get_contents('php://input');
$signature = (string) ($_SERVER['HTTP_X_WEBHOOK_HMAC'] ?? '');
$requestId = (string) ($_SERVER['HTTP_X_WEBHOOK_REQUEST_ID'] ?? '');
try {
    $db = Database::getConnection();
    (new WahaSchemaManager($db))->migrate();
    $handler = new WahaWebhookHandler(WahaConfig::fromEnvironment(), new PdoWahaWebhookStore($db));
    $response = $handler->handle($rawBody, $signature, $requestId);
    $menuConfig = WhatsAppMenuConfig::fromEnvironment();
    $queueId = (int) ($response['body']['_queueId'] ?? 0);
    unset($response['body']['_queueId']);
    if ($response['status'] === 200 && $menuConfig->menuEnabled && $queueId > 0) {
        $waha = new WahaService(WahaConfig::fromEnvironment(), new CurlWahaHttpClient());
        // O comando recem-recebido nao pode ficar atras de eventos ACK antigos.
        (new WahaInboundProcessor($db, $waha, $menuConfig))->processPending(1, $queueId);
    }
    http_response_code($response['status']);
    echo json_encode($response['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    Logger::error('waha.webhook.enqueue_failed', ['exception' => get_class($exception)]);
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Webhook temporariamente indisponivel.']);
}
