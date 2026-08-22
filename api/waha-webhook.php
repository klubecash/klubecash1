<?php
declare(strict_types=1);
use App\Core\Logger;
use App\Services\WhatsApp\PdoWahaWebhookStore;
use App\Services\WhatsApp\WahaConfig;
use App\Services\WhatsApp\WahaWebhookHandler;
header('Content-Type: application/json; charset=UTF-8');
$rawBody = (string) file_get_contents('php://input');
$signature = (string) ($_SERVER['HTTP_X_WEBHOOK_HMAC'] ?? '');
$requestId = (string) ($_SERVER['HTTP_X_WEBHOOK_REQUEST_ID'] ?? '');
try {
    $handler = new WahaWebhookHandler(WahaConfig::fromEnvironment(), new PdoWahaWebhookStore(Database::getConnection()));
    $response = $handler->handle($rawBody, $signature, $requestId);
    http_response_code($response['status']);
    echo json_encode($response['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    Logger::error('waha.webhook.enqueue_failed', ['exception' => get_class($exception)]);
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Webhook temporariamente indisponivel.']);
}
