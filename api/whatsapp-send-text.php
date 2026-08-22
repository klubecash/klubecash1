<?php

declare(strict_types=1);

use App\Core\Logger;
use App\Services\WhatsApp\CurlWahaHttpClient;
use App\Services\WhatsApp\WahaConfig;
use App\Services\WhatsApp\WahaException;
use App\Services\WhatsApp\WahaService;

header('Content-Type: application/json; charset=UTF-8');

try {
    $rawBody = (string) file_get_contents('php://input');
    $input = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($input)) {
        throw new InvalidArgumentException('Corpo da requisicao invalido.');
    }

    $phone = (string) ($input['phone'] ?? '');
    $text = (string) ($input['text'] ?? '');
    $response = (new WahaService(
        WahaConfig::fromEnvironment(),
        new CurlWahaHttpClient()
    ))->sendText($phone, $text);

    Logger::info('whatsapp.admin_send.accepted', [
        'message_id' => isset($response['id']) && is_scalar($response['id'])
            ? (string) $response['id']
            : null,
    ]);
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'accepted' => true,
            'messageId' => isset($response['id']) && is_scalar($response['id'])
                ? (string) $response['id']
                : null,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (JsonException | InvalidArgumentException $exception) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'VALIDATION_ERROR', 'message' => $exception->getMessage()],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (WahaException $exception) {
    Logger::warning('whatsapp.admin_send.failed', [
        'upstream_status' => $exception->httpStatus,
        'transient' => $exception->transient,
    ]);
    http_response_code($exception->httpStatus);
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'WHATSAPP_ERROR', 'message' => $exception->getMessage()],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    Logger::error('whatsapp.admin_send.unexpected', ['exception' => get_class($exception)]);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Nao foi possivel enviar a mensagem.'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
