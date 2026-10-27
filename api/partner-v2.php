<?php

declare(strict_types=1);

use App\Core\Logger;
use App\Core\RequestContext;
use App\Services\Giftback\GiftbackException;
use App\Services\Partner\PartnerApiException;
use App\Services\Partner\PartnerIdempotencyService;
use App\Services\Partner\PartnerKeyService;
use App\Services\Partner\PartnerRequestLog;
use App\Services\Partner\PassPartnerService;

// Server-to-server API for partners (Klube Pass). No session, no cookies:
// a per-partner key in Authorization, Idempotency-Key on every write.
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../services/Partner/PartnerApiException.php';
require_once __DIR__ . '/../services/Partner/PartnerKeyService.php';
require_once __DIR__ . '/../services/Partner/PartnerIdempotencyService.php';
require_once __DIR__ . '/../services/Partner/PartnerRequestLog.php';
require_once __DIR__ . '/../services/Partner/PassPartnerService.php';
require_once __DIR__ . '/../services/Security/VpsRuntimeKeys.php';

// The Kernel requires this file inside a method, so the request log lives in
// $GLOBALS explicitly for the helper functions below.
$GLOBALS['partnerLog'] =['requestId' => RequestContext::id(), 'method' => strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'))];

function partnerRespond(int $status, array $body): never
{
    $GLOBALS['partnerLog']['status'] = $status;
    $GLOBALS['partnerLog']['durationMs'] = RequestContext::durationMs();
    if (isset($GLOBALS['partnerLog']['keyId'])) {
        PartnerRequestLog::write(Database::getConnection(), $GLOBALS['partnerLog'], \App\Services\Security\VpsRuntimeKeys::partnerApiPepper());
    }
    http_response_code($status);
    echo json_encode($body + ['request_id' => RequestContext::id()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function partnerError(int $status, string $code, string $message): never
{
    $GLOBALS['partnerLog']['errorCode'] = $code;
    partnerRespond($status, ['success' => false, 'error' => ['code' => $code, 'message' => $message]]);
}

/** @return array<string, mixed> */
function partnerPayload(): array
{
    if (!str_contains(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
        throw new PartnerApiException('Envie o corpo em JSON (Content-Type: application/json).', 415, 'unsupported_media_type');
    }
    $decoded = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($decoded) || array_is_list($decoded)) { throw new PartnerApiException('O corpo JSON é inválido.', 400, 'invalid_json'); }
    return $decoded;
}

$method = $GLOBALS['partnerLog']['method'];
if ($method === 'OPTIONS') {
    header('Allow: GET, POST, OPTIONS');
    http_response_code(204);
    exit;
}

$resource = trim((string) ($_GET['resource'] ?? ''), '/');
$GLOBALS['partnerLog']['endpoint'] = $resource;
$writes = ['pass/debit' => 'pass:debit', 'pass/credit' => 'pass:credit', 'pass/reverse' => 'pass:reverse'];

try {
    $db = Database::getConnection();
    $keys = PartnerKeyService::fromEnvironment($db);
    $key = $keys->authenticate((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    $GLOBALS['partnerLog']['keyId'] = $key['id'];
    $service = new PassPartnerService($db);
    $keys->countRequest($key, $service->today());

    if ($resource === 'pass/balance') {
        if ($method !== 'GET') { partnerError(405, 'method_not_allowed', 'Use GET.'); }
        PartnerKeyService::requirePermission($key, 'pass:balance');
        $data = $service->balance($_GET);
        $GLOBALS['partnerLog']['userId'] = $data['klubecash_user_id'];
        partnerRespond(200, ['success' => true, 'data' => $data]);
    }

    if (!isset($writes[$resource])) { partnerError(404, 'not_found', 'Recurso não encontrado.'); }
    if ($method !== 'POST') { partnerError(405, 'method_not_allowed', 'Use POST.'); }
    PartnerKeyService::requirePermission($key, $writes[$resource]);
    $payload = partnerPayload();
    $idempotencyKey = trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? ''));
    $GLOBALS['partnerLog']['idempotencyKey'] = $idempotencyKey;
    $GLOBALS['partnerLog']['passRef'] = is_string($payload['pass_ref'] ?? null) ? substr($payload['pass_ref'], 0, 100) : null;

    $idempotency = new PartnerIdempotencyService($db);
    $begin = $idempotency->begin($key['id'], $resource, $idempotencyKey, $payload);
    if ($begin['replayed']) {
        $GLOBALS['partnerLog']['replayed'] = true;
        partnerRespond($begin['status'] ?: 200, $begin['data']);
    }
    try {
        $data = match ($resource) {
            'pass/debit' => $service->debit($key, $payload),
            'pass/credit' => $service->credit($key, $payload),
            'pass/reverse' => $service->reverse($key, $payload),
        };
    } catch (Throwable $error) {
        $idempotency->fail($key['id'], $resource, $idempotencyKey);
        throw $error;
    }
    $GLOBALS['partnerLog'] = array_replace($GLOBALS['partnerLog'], $data['_log'] ?? [], ['replayed' => (bool) ($data['replayed'] ?? false)]);
    unset($data['_log']);
    $body = ['success' => true, 'data' => $data];
    $idempotency->complete($key['id'], $resource, $idempotencyKey, 200, $body);
    partnerRespond(200, $body);
} catch (PartnerApiException $error) {
    partnerError($error->httpStatus, $error->errorCode, $error->getMessage());
} catch (GiftbackException $error) {
    partnerError($error->httpStatus, $error->httpStatus === 409 ? 'ledger_conflict' : 'ledger_rejected', $error->getMessage());
} catch (Throwable $error) {
    Logger::error('partner.v2.failure', ['exception' => get_class($error), 'endpoint' => $resource]);
    partnerError(500, 'internal_error', 'Não foi possível concluir a solicitação.');
}
