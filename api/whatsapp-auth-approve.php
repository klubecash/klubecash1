<?php

declare(strict_types=1);

use App\Core\RequestContext;
use App\Services\Store\StoreApiException;
use App\Services\WhatsApp\CurlWahaHttpClient;
use App\Services\WhatsApp\WahaConfig;
use App\Services\WhatsApp\WahaService;
use App\Services\WhatsApp\WhatsAppAuthService;
use App\Services\WhatsApp\WhatsAppMenuConfig;

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Vary: Cookie');

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../utils/Security.php';
require_once __DIR__ . '/../services/store/StoreApiException.php';

/** @param array<string,string[]> $errors */
function whatsappAuthRespond(int $httpStatus, bool $success, mixed $data = null, ?string $message = null, array $errors = []): never
{
    http_response_code($httpStatus);
    $response = [
        'status' => $success ? 'success' : 'error',
        'requestId' => RequestContext::id(),
        'generatedAt' => date(DATE_ATOM),
    ];
    if ($data !== null) { $response['data'] = $data; }
    if ($message !== null) { $response['message'] = $message; }
    if ($errors !== []) { $response['errors'] = $errors; }
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    whatsappAuthRespond(405, false, null, 'Metodo nao permitido.');
}
if (!AuthController::isAuthenticated() || !AuthController::hasStoreAccess()) {
    whatsappAuthRespond(401, false, null, 'Entre com uma conta lojista para continuar.');
}

$payload = [];
if ($method === 'POST') {
    $payload = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        whatsappAuthRespond(400, false, null, 'O corpo JSON e invalido.');
    }
    $csrf = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $payload['csrfToken'] ?? '');
    if (!Security::validateCSRFToken($csrf)) {
        whatsappAuthRespond(419, false, null, 'Sua sessao de seguranca expirou. Atualize a pagina.');
    }
}
$token = trim((string) ($method === 'GET' ? ($_GET['token'] ?? '') : ($payload['token'] ?? '')));
if ($token === '') {
    whatsappAuthRespond(422, false, null, 'Token de autorizacao ausente.');
}

try {
    $service = new WhatsAppAuthService(
        Database::getConnection(),
        new WahaService(WahaConfig::fromEnvironment(), new CurlWahaHttpClient()),
        WhatsAppMenuConfig::fromEnvironment()
    );
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $storeId = (int) (AuthController::getStoreId() ?? 0);
    if ($method === 'GET') {
        $data = $service->context($token, $userId, $storeId);
        $data['csrfToken'] = Security::generateCSRFToken();
        whatsappAuthRespond(200, true, $data);
    }
    whatsappAuthRespond(
        200,
        true,
        $service->approve($token, $userId, $storeId, RequestContext::id()),
        'WhatsApp autorizado com sucesso.'
    );
} catch (StoreApiException $exception) {
    whatsappAuthRespond($exception->httpStatus, false, null, $exception->getMessage(), $exception->errors);
} catch (Throwable) {
    whatsappAuthRespond(500, false, null, 'Nao foi possivel autorizar o WhatsApp. Tente novamente.');
}
