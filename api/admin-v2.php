<?php

declare(strict_types=1);

use App\Core\Logger;
use App\Core\RequestContext;
use App\Services\Admin\AdminApiException;
use App\Services\Admin\AdminMarketingService;
use App\Services\Admin\AdminMutationService;
use App\Services\Admin\AdminReadService;

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Vary: Cookie');

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../utils/Security.php';
require_once __DIR__ . '/../services/admin/AdminApiException.php';
require_once __DIR__ . '/../services/admin/AdminMoney.php';
require_once __DIR__ . '/../services/admin/AdminAuditService.php';
require_once __DIR__ . '/../services/admin/AdminIdempotencyService.php';
require_once __DIR__ . '/../services/admin/AdminReadService.php';
require_once __DIR__ . '/../services/admin/AdminMutationService.php';
require_once __DIR__ . '/../services/admin/AdminMarketingService.php';

/** @param array<string, string[]> $errors */
function adminV2Respond(int $httpStatus, bool $success, mixed $data = null, ?string $message = null, array $errors = []): never
{
    http_response_code($httpStatus);
    $response = [
        'status' => $success ? 'success' : 'error',
        'requestId' => RequestContext::id(),
        'generatedAt' => date(DATE_ATOM),
    ];
    if ($data !== null) {
        $response['data'] = $data;
        if (is_array($data) && isset($data['dataState'])) {
            $response['dataState'] = $data['dataState'];
        }
    }
    if ($message !== null && $message !== '') { $response['message'] = $message; }
    if ($errors !== []) { $response['errors'] = $errors; }
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/** @return array<string, mixed> */
function adminV2Payload(): array
{
    $decoded = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($decoded)) {
        throw new AdminApiException('O corpo JSON é inválido.', 400);
    }
    return $decoded;
}

function adminV2Csrf(array $payload): void
{
    $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $payload['csrfToken'] ?? '');
    if (!Security::validateCSRFToken($token)) {
        throw new AdminApiException('Sua sessão de segurança expirou. Atualize a página.', 419);
    }
}

/** @return array<string, string> */
function adminV2Filters(array $names): array
{
    $filters = [];
    foreach ($names as $name) {
        $value = trim((string) ($_GET[$name] ?? ''));
        if ($value !== '') { $filters[$name] = $value; }
    }
    return $filters;
}

function adminV2IdempotencyKey(): string
{
    return trim((string) ($_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? ''));
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'OPTIONS') {
    header('Allow: GET, POST, PATCH, DELETE, OPTIONS');
    http_response_code(204);
    exit;
}
if (!AuthController::isAuthenticated()) { adminV2Respond(401, false, null, 'Sessão expirada. Faça login novamente.'); }
if (!AuthController::isAdmin()) { adminV2Respond(403, false, null, 'Acesso restrito aos administradores.'); }

$actorId = (int) ($_SESSION['user_id'] ?? 0);
$resource = trim((string) ($_GET['resource'] ?? ''), '/');
$segments = $resource === '' ? [] : explode('/', $resource);
$db = Database::getConnection();
$read = new AdminReadService($db);
$mutations = new AdminMutationService($db, $actorId);
$marketing = new AdminMarketingService($db, $actorId, $read);
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = max(10, min(100, (int) ($_GET['pageSize'] ?? 20)));

try {
    if ($method === 'GET' && $resource === 'context') {
        $data = $read->context($_SESSION); $data['csrfToken'] = Security::generateCSRFToken(); adminV2Respond(200, true, $data);
    }
    if ($method === 'GET' && $resource === 'dashboard') { adminV2Respond(200, true, $read->dashboard()); }
    if ($method === 'GET' && $resource === 'users') { adminV2Respond(200, true, $read->users(adminV2Filters(['search', 'type', 'status']), $page, $pageSize)); }
    if ($method === 'GET' && count($segments) === 2 && $segments[0] === 'users') { adminV2Respond(200, true, $read->user((int) $segments[1])); }
    if ($method === 'GET' && $resource === 'stores') { adminV2Respond(200, true, $read->stores(adminV2Filters(['search', 'status', 'category']), $page, $pageSize)); }
    if ($method === 'GET' && count($segments) === 2 && $segments[0] === 'stores') { adminV2Respond(200, true, $read->store((int) $segments[1])); }
    if ($method === 'GET' && $resource === 'transactions/export') {
        $data = $read->transactions(adminV2Filters(['search', 'status', 'model', 'storeId', 'startDate', 'endDate', 'balance']), 1, 5000);
        header_remove('Content-Type'); header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="transacoes-klubecash.csv"');
        $output = fopen('php://output', 'wb'); fputs($output, "\xEF\xBB\xBF");
        fputcsv($output, ['ID', 'Código', 'Cliente', 'Loja', 'Valor', 'Saldo usado', 'Pago', 'Cashback', 'Status', 'Modelo', 'Data']);
        foreach ((array) ($data['items'] ?? []) as $item) {
            fputcsv($output, [$item['id'], $item['code'], $item['customerName'], $item['storeName'], $item['grossAmountCents'] / 100, $item['balanceUsedCents'] / 100, $item['paidAmountCents'] / 100, $item['cashbackAmountCents'] / 100, $item['status'], $item['financialModel'], $item['occurredAt']]);
        }
        fclose($output); exit;
    }
    if ($method === 'GET' && $resource === 'transactions') { adminV2Respond(200, true, $read->transactions(adminV2Filters(['search', 'status', 'model', 'storeId', 'startDate', 'endDate', 'balance']), $page, $pageSize)); }
    if ($method === 'GET' && count($segments) === 2 && $segments[0] === 'transactions') { adminV2Respond(200, true, $read->transaction((int) $segments[1])); }
    if ($method === 'GET' && $resource === 'finance') { adminV2Respond(200, true, $read->finance(adminV2Filters(['status']), $page, $pageSize)); }
    if ($method === 'GET' && $resource === 'reports') { adminV2Respond(200, true, $read->reports(adminV2Filters(['startDate', 'endDate', 'storeId']))); }
    if ($method === 'GET' && $resource === 'settings') { adminV2Respond(200, true, $read->settings()); }
    if ($method === 'GET' && $resource === 'subscriptions') { adminV2Respond(200, true, $read->subscriptions(adminV2Filters(['search', 'status']), $page, $pageSize)); }
    if ($method === 'GET' && count($segments) === 2 && $segments[0] === 'subscriptions') { adminV2Respond(200, true, $read->subscription((int) $segments[1])); }
    if ($method === 'GET' && $resource === 'plans') { adminV2Respond(200, true, $read->plans()); }
    if ($method === 'GET' && $resource === 'campaigns') { adminV2Respond(200, true, $read->campaigns($page, $pageSize)); }
    if ($method === 'GET' && count($segments) === 2 && $segments[0] === 'campaigns') { adminV2Respond(200, true, $read->campaign((int) $segments[1])); }
    if ($method === 'GET' && $resource === 'templates') { adminV2Respond(200, true, $read->templates()); }
    if ($method === 'GET' && $resource === 'audit') { adminV2Respond(200, true, $read->audit(adminV2Filters(['action', 'entityType']), $page, $pageSize)); }
    if ($method === 'GET') { adminV2Respond(404, false, null, 'Recurso administrativo não encontrado.'); }

    if (!in_array($method, ['POST', 'PATCH', 'DELETE'], true)) { adminV2Respond(405, false, null, 'Método não permitido.'); }
    $payload = adminV2Payload(); adminV2Csrf($payload);

    if ($method === 'POST' && $resource === 'users') { adminV2Respond(201, true, $mutations->createUser($payload), 'Usuário criado. Envie o fluxo de recuperação para definir a senha.'); }
    if ($method === 'PATCH' && count($segments) === 2 && $segments[0] === 'users') { adminV2Respond(200, true, $mutations->updateUser((int) $segments[1], $payload), 'Usuário atualizado.'); }
    if ($method === 'POST' && count($segments) === 3 && $segments[0] === 'users' && $segments[2] === 'status') { adminV2Respond(200, true, $mutations->updateUserStatus((int) $segments[1], (string) ($payload['status'] ?? '')), 'Status atualizado.'); }
    if ($method === 'POST' && count($segments) === 3 && $segments[0] === 'users' && $segments[2] === 'password-reset') { adminV2Respond(202, true, $mutations->requestUserPasswordReset((int) $segments[1]), 'Recuperação de senha adicionada à fila.'); }
    if ($method === 'PATCH' && count($segments) === 2 && $segments[0] === 'stores') { adminV2Respond(200, true, $mutations->updateStore((int) $segments[1], $payload), 'Loja atualizada.'); }
    if ($method === 'POST' && count($segments) === 3 && $segments[0] === 'stores' && $segments[2] === 'status') { adminV2Respond(200, true, $mutations->updateStoreStatus((int) $segments[1], (string) ($payload['status'] ?? ''), (string) ($payload['notes'] ?? ''), adminV2IdempotencyKey(), isset($payload['updatedAt']) ? (string) $payload['updatedAt'] : null), 'Status da loja atualizado.'); }
    if ($method === 'POST' && count($segments) === 3 && $segments[0] === 'transactions' && $segments[2] === 'status') { adminV2Respond(200, true, $mutations->legacyTransactionStatus((int) $segments[1], (string) ($payload['status'] ?? ''), (string) ($payload['notes'] ?? ''), adminV2IdempotencyKey()), 'Transação legada atualizada.'); }
    if ($method === 'POST' && count($segments) === 3 && $segments[0] === 'transactions' && $segments[2] === 'reverse') { adminV2Respond(200, true, $mutations->reverseCurrentTransaction((int) $segments[1], (string) ($payload['reason'] ?? ''), adminV2IdempotencyKey()), 'Venda estornada.'); }
    if ($method === 'POST' && count($segments) === 3 && $segments[0] === 'finance') { adminV2Respond(200, true, $mutations->processLegacyPayment((string) $segments[1], (int) $segments[2], (string) ($payload['decision'] ?? ''), (string) ($payload['notes'] ?? ''), adminV2IdempotencyKey()), 'Pendência legada processada.'); }
    if ($method === 'POST' && $resource === 'settings') { adminV2Respond(200, true, $mutations->updateSettings($payload), 'Configurações atualizadas.'); }
    if ($method === 'PATCH' && count($segments) === 2 && $segments[0] === 'plans') { adminV2Respond(200, true, $mutations->updatePlan((int) $segments[1], $payload), 'Plano atualizado.'); }
    if ($method === 'POST' && $resource === 'subscriptions') { adminV2Respond(201, true, $mutations->assignSubscription($payload, adminV2IdempotencyKey()), 'Plano atribuído.'); }
    if ($method === 'POST' && count($segments) === 3 && $segments[0] === 'subscriptions' && $segments[2] === 'status') { adminV2Respond(200, true, $mutations->subscriptionStatus((int) $segments[1], (string) ($payload['action'] ?? ''), adminV2IdempotencyKey(), isset($payload['updatedAt']) ? (string) $payload['updatedAt'] : null), 'Assinatura atualizada.'); }
    if ($method === 'POST' && $resource === 'marketing/audience') { adminV2Respond(200, true, $marketing->audiencePreview((array) ($payload['audience'] ?? []))); }
    if ($method === 'POST' && $resource === 'campaigns') { adminV2Respond(201, true, $marketing->saveCampaign(null, $payload), 'Campanha salva como rascunho.'); }
    if ($method === 'PATCH' && count($segments) === 2 && $segments[0] === 'campaigns') { adminV2Respond(200, true, $marketing->saveCampaign((int) $segments[1], $payload), 'Campanha atualizada.'); }
    if ($method === 'POST' && count($segments) === 3 && $segments[0] === 'campaigns' && $segments[2] === 'schedule') { adminV2Respond(200, true, $marketing->scheduleCampaign((int) $segments[1], $payload, adminV2IdempotencyKey()), 'Campanha agendada.'); }
    if ($method === 'POST' && count($segments) === 3 && $segments[0] === 'campaigns' && $segments[2] === 'cancel') { adminV2Respond(200, true, $marketing->cancelCampaign((int) $segments[1], isset($payload['updatedAt']) ? (string) $payload['updatedAt'] : null), 'Campanha cancelada.'); }
    if ($method === 'POST' && count($segments) === 3 && $segments[0] === 'campaigns' && $segments[2] === 'test') { adminV2Respond(202, true, $marketing->queueTestEmail((int) $segments[1], $payload), 'E-mail de teste adicionado à fila.'); }
    if ($method === 'POST' && $resource === 'templates') { adminV2Respond(201, true, $marketing->saveTemplate(null, $payload), 'Template criado.'); }
    if ($method === 'PATCH' && count($segments) === 2 && $segments[0] === 'templates') { adminV2Respond(200, true, $marketing->saveTemplate((int) $segments[1], $payload), 'Template atualizado.'); }
    if ($method === 'DELETE' && count($segments) === 2 && $segments[0] === 'templates') { adminV2Respond(200, true, $marketing->archiveTemplate((int) $segments[1], isset($payload['updatedAt']) ? (string) $payload['updatedAt'] : null), 'Template arquivado.'); }

    adminV2Respond(404, false, null, 'Recurso administrativo não encontrado.');
} catch (AdminApiException $exception) {
    adminV2Respond($exception->httpStatus, false, null, $exception->getMessage(), $exception->errors);
} catch (Throwable $exception) {
    Logger::error('admin.v2.failure', ['exception' => get_class($exception), 'message' => $exception->getMessage()]);
    adminV2Respond(500, false, null, 'Não foi possível concluir a solicitação administrativa.');
}
