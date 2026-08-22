<?php

declare(strict_types=1);

use App\Core\Logger;
use App\Services\Admin\AdminApiException;
use App\Services\Admin\AdminIdempotencyService;
use App\Services\WhatsApp\CurlWahaHttpClient;
use App\Services\WhatsApp\WahaConfig;
use App\Services\WhatsApp\WahaException;
use App\Services\WhatsApp\WahaService;

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Vary: Cookie');

require_once __DIR__ . '/../utils/Security.php';
require_once __DIR__ . '/../services/admin/AdminApiException.php';
require_once __DIR__ . '/../services/admin/AdminIdempotencyService.php';

$idempotency = null;
$idempotencyKey = '';

try {
    $rawBody = (string) file_get_contents('php://input');
    $input = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($input)) {
        throw new InvalidArgumentException('Corpo da requisicao invalido.');
    }

    $csrfToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrfToken'] ?? '');
    if (!Security::validateCSRFToken($csrfToken)) {
        throw new AdminApiException('Sua sessao de seguranca expirou. Atualize a pagina.', 419);
    }

    $phone = trim((string) ($input['phone'] ?? ''));
    $text = trim((string) ($input['text'] ?? ''));
    if ($text === '' || mb_strlen($text) > 4000) {
        throw new InvalidArgumentException('A mensagem deve conter entre 1 e 4000 caracteres.');
    }

    $idempotencyKey = trim((string) ($_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? ''));
    $idempotency = new AdminIdempotencyService(Database::getConnection(), (int) ($_SESSION['user_id'] ?? 0));
    $state = $idempotency->begin('whatsapp_send_text', $idempotencyKey, ['phone' => $phone, 'text' => $text]);
    if ($state['replayed']) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => [...($state['data'] ?? []), 'replayed' => true],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $response = (new WahaService(
        WahaConfig::fromEnvironment(),
        new CurlWahaHttpClient()
    ))->sendText($phone, $text);

    $result = [
        'accepted' => true,
        'messageId' => isset($response['id']) && is_scalar($response['id'])
            ? (string) $response['id']
            : null,
        'replayed' => false,
    ];
    $idempotency->complete('whatsapp_send_text', $idempotencyKey, $result);
    Logger::info('whatsapp.admin_send.accepted', ['message_id' => $result['messageId']]);
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $result,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (AdminApiException $exception) {
    http_response_code($exception->httpStatus);
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'ADMIN_REQUEST_ERROR', 'message' => $exception->getMessage()],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (JsonException | InvalidArgumentException $exception) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'VALIDATION_ERROR', 'message' => $exception->getMessage()],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (WahaException $exception) {
    // Timeout/rede pode acontecer depois de o WhatsApp aceitar a mensagem.
    // Nessa situacao a chave permanece em processamento e bloqueia reenvio.
    if (!$exception->deliveryUnknown && $idempotency instanceof AdminIdempotencyService && $idempotencyKey !== '') {
        $idempotency->fail('whatsapp_send_text', $idempotencyKey);
    }
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
