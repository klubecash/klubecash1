<?php
declare(strict_types=1);
use App\Services\WhatsApp\CurlWahaHttpClient;
use App\Services\WhatsApp\WahaConfig;
use App\Services\WhatsApp\WahaException;
use App\Services\WhatsApp\WahaService;
header('Content-Type: application/json; charset=UTF-8');
try {
    $result = (new WahaService(WahaConfig::fromEnvironment(), new CurlWahaHttpClient()))->connectionStatus();
    http_response_code($result['available'] ? 200 : 503);
    echo json_encode(['success' => true, 'data' => $result], JSON_UNESCAPED_UNICODE);
} catch (WahaException $exception) {
    http_response_code($exception->httpStatus);
    echo json_encode(['success' => false, 'error' => ['code' => 'WHATSAPP_UNAVAILABLE', 'message' => $exception->getMessage()]], JSON_UNESCAPED_UNICODE);
}
