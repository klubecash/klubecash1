<?php

declare(strict_types=1);

use App\Core\RequestContext;
use App\Core\Logger;
use App\Services\Store\StoreApiException;
use App\Services\Store\StoreCustomerService;
use App\Services\Store\StoreIdempotencyService;
use App\Services\Store\StoreManagementService;
use App\Services\Store\StoreMoney;
use App\Services\Store\StoreReadService;
use App\Services\Store\StoreTransactionService;
use App\Services\Store\StoreWhatsAppNotificationService;

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Vary: Cookie');

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/SubscriptionController.php';
require_once __DIR__ . '/../utils/FeatureGate.php';
require_once __DIR__ . '/../utils/Security.php';
require_once __DIR__ . '/../services/store/StoreApiException.php';
require_once __DIR__ . '/../services/store/StoreMoney.php';
require_once __DIR__ . '/../services/store/StoreIdempotencyService.php';
require_once __DIR__ . '/../services/store/StoreTransactionService.php';
require_once __DIR__ . '/../services/store/StoreReadService.php';
require_once __DIR__ . '/../services/store/StoreCustomerService.php';
require_once __DIR__ . '/../services/store/StoreManagementService.php';
require_once __DIR__ . '/../services/store/StoreWhatsAppNotificationService.php';

/** @param array<string, string[]> $errors */
function storeV2Respond(int $httpStatus, bool $success, mixed $data = null, ?string $message = null, array $errors = []): never
{
    http_response_code($httpStatus);
    $response = [
        'status' => $success ? 'success' : 'error',
        'requestId' => RequestContext::id(),
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    if ($message !== null && $message !== '') {
        $response['message'] = $message;
    }
    if ($errors !== []) {
        $response['errors'] = $errors;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/** @return array<string, mixed> */
function storeV2Payload(): array
{
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($decoded)) {
            throw new StoreApiException('O corpo JSON é inválido.', 400);
        }
        return $decoded;
    }
    return $_POST;
}

function storeV2Csrf(array $payload): void
{
    $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $payload['csrfToken'] ?? '');
    if (!Security::validateCSRFToken($token)) {
        throw new StoreApiException('Sua sessão de segurança expirou. Atualize a página e tente novamente.', 419);
    }
}

/** @return array<string, string> */
function storeV2Filters(array $names): array
{
    $filters = [];
    foreach ($names as $name) {
        $value = trim((string) ($_GET[$name] ?? ''));
        if ($value !== '') {
            $filters[$name] = $value;
        }
    }
    return $filters;
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'OPTIONS') {
    header('Allow: GET, POST, PATCH, DELETE, OPTIONS');
    http_response_code(204);
    exit;
}

if (!AuthController::isAuthenticated()) {
    storeV2Respond(401, false, null, 'Sessão expirada. Faça login novamente.');
}
if (!AuthController::hasStoreAccess()) {
    storeV2Respond(403, false, null, 'Acesso restrito à área lojista.');
}

$storeId = (int) (AuthController::getStoreId() ?? 0);
$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($storeId <= 0 || $userId <= 0) {
    storeV2Respond(422, false, null, 'Conta sem loja associada.');
}

$resource = trim((string) ($_GET['resource'] ?? ''), '/');
$segments = $resource === '' ? [] : explode('/', $resource);
$db = Database::getConnection();
$read = new StoreReadService($db);
$customers = new StoreCustomerService($db);
$transactions = new StoreTransactionService($db);
$management = new StoreManagementService($db);
$whatsAppNotifications = new StoreWhatsAppNotificationService($db);

try {
    if ($method === 'GET' && $resource === 'context') {
        $data = $read->context($storeId, $_SESSION);
        $data['csrfToken'] = Security::generateCSRFToken();
        storeV2Respond(200, true, $data);
    }
    if ($method === 'GET' && $resource === 'dashboard') {
        storeV2Respond(200, true, $read->dashboard($storeId));
    }
    if ($method === 'GET' && $resource === 'transactions') {
        storeV2Respond(200, true, $read->transactions(
            $storeId,
            storeV2Filters(['status', 'startDate', 'endDate', 'customer', 'minimumCents', 'maximumCents']),
            max(1, (int) ($_GET['page'] ?? 1))
        ));
    }
    if ($method === 'GET' && count($segments) === 2 && $segments[0] === 'transactions') {
        storeV2Respond(200, true, $read->transaction($storeId, (int) $segments[1]));
    }
    if ($method === 'GET' && $resource === 'customers/search') {
        storeV2Respond(200, true, $customers->search($storeId, (string) ($_GET['query'] ?? '')));
    }
    if ($method === 'GET' && $resource === 'employees') {
        if (!AuthController::canManageEmployees()) {
            throw new StoreApiException('Acesso restrito ao titular e aos gerentes.', 403);
        }
        storeV2Respond(200, true, $management->employees(
            $storeId,
            storeV2Filters(['subtype', 'status', 'search']),
            max(1, (int) ($_GET['page'] ?? 1))
        ));
    }
    if ($method === 'GET' && $resource === 'profile') {
        storeV2Respond(200, true, $read->profile($storeId));
    }
    if ($method === 'GET' && $resource === 'subscription') {
        storeV2Respond(200, true, $read->subscription($storeId));
    }

    if ($method === 'GET') {
        storeV2Respond(404, false, null, 'Recurso não encontrado.');
    }
    if (!in_array($method, ['POST', 'PATCH', 'DELETE'], true)) {
        storeV2Respond(405, false, null, 'Método não permitido.');
    }

    $payload = storeV2Payload();
    storeV2Csrf($payload);

    if ($method === 'POST' && $resource === 'customers/visitor') {
        storeV2Respond(201, true, $customers->createVisitor(
            $storeId,
            (string) ($payload['name'] ?? ''),
            (string) ($payload['phone'] ?? '')
        ), 'Visitante criado com sucesso.');
    }
    if ($method === 'POST' && $resource === 'transactions') {
        $key = (string) ($_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? '');
        $sale = $transactions->create($storeId, $userId, $payload, $key);
        try {
            $sale['whatsappNotification'] = [
                'status' => $whatsAppNotifications->queueAndProcess((int) $sale['id'], $storeId),
            ];
        } catch (Throwable $exception) {
            // A notificacao nunca pode desfazer ou esconder uma venda ja aprovada.
            Logger::warning('waha.sale_notification.queue_failed', [
                'transaction_id' => (int) $sale['id'],
                'exception' => get_class($exception),
            ]);
            $sale['whatsappNotification'] = ['status' => 'unavailable'];
        }
        storeV2Respond(201, true, $sale, 'Venda aprovada e cashback creditado.');
    }
    if ($method === 'POST' && $resource === 'transactions/batch') {
        $key = (string) ($_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? '');
        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new StoreApiException('Selecione um arquivo CSV válido.', 422, ['file' => ['Arquivo obrigatório.']]);
        }
        if ((int) ($file['size'] ?? 0) > 10 * 1024 * 1024) {
            throw new StoreApiException('O arquivo excede o limite de 10 MB.', 413);
        }
        if (strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)) !== 'csv') {
            throw new StoreApiException('O arquivo deve estar no formato CSV.', 422);
        }
        $handle = fopen((string) $file['tmp_name'], 'rb');
        if ($handle === false) {
            throw new StoreApiException('Não foi possível ler o arquivo.', 422);
        }
        $headers = fgetcsv($handle, 4096, ',');
        $required = ['email_cliente', 'valor_total', 'codigo_transacao'];
        $headers = is_array($headers) ? array_map(static fn ($value) => trim((string) $value), $headers) : [];
        if (array_diff($required, $headers) !== []) {
            fclose($handle);
            throw new StoreApiException('Cabeçalho inválido. Use email_cliente, valor_total e codigo_transacao.', 422);
        }
        $records = [];
        $line = 1;
        while (($row = fgetcsv($handle, 4096, ',')) !== false) {
            $line++;
            if ($line > 501) {
                fclose($handle);
                throw new StoreApiException('O arquivo aceita no máximo 500 registros.', 422);
            }
            $records[] = ['line' => $line, 'values' => count($row) === count($headers) ? array_combine($headers, $row) : null];
        }
        fclose($handle);

        $idempotency = new StoreIdempotencyService($db);
        $batchRequest = ['fileHash' => hash_file('sha256', (string) $file['tmp_name']), 'records' => count($records)];
        $batchState = $idempotency->begin('store_batch', $storeId, $userId, $key, $batchRequest);
        if ($batchState['replayed']) {
            storeV2Respond(200, true, [...($batchState['data'] ?? []), 'replayed' => true]);
        }

        $resultRows = [];
        $processed = $skipped = $failed = 0;
        try {
            foreach ($records as $record) {
                $values = $record['values'];
                if (!is_array($values)) {
                    $failed++;
                    $resultRows[] = ['line' => $record['line'], 'status' => 'error', 'message' => 'Quantidade de colunas inválida.'];
                    continue;
                }
                $email = strtolower(trim((string) ($values['email_cliente'] ?? '')));
                $customer = $db->prepare("SELECT id FROM usuarios WHERE email=:email AND tipo='cliente' AND status='ativo' LIMIT 1");
                $customer->execute([':email' => $email]);
                $customerId = (int) ($customer->fetchColumn() ?: 0);
                if ($customerId <= 0) {
                    $skipped++;
                    $resultRows[] = ['line' => $record['line'], 'status' => 'skipped', 'message' => 'Cliente não encontrado ou inativo.'];
                    continue;
                }
                try {
                    $sale = $transactions->create($storeId, $userId, [
                        'customerId' => $customerId,
                        'grossAmountCents' => StoreMoney::toCents($values['valor_total'] ?? 0),
                        'balanceUsedCents' => StoreMoney::toCents($values['valor_saldo_usado'] ?? 0),
                        'code' => (string) ($values['codigo_transacao'] ?? ''),
                        'description' => (string) ($values['descricao'] ?? 'Importação em lote'),
                        'occurredAt' => (string) ($values['data_transacao'] ?? date(DATE_ATOM)),
                    ], $key . ':' . $record['line']);
                    try {
                        $whatsAppNotifications->queue((int) $sale['id'], $storeId);
                    } catch (Throwable $exception) {
                        Logger::warning('waha.sale_notification.queue_failed', [
                            'transaction_id' => (int) $sale['id'],
                            'exception' => get_class($exception),
                        ]);
                    }
                    $processed++;
                    $resultRows[] = ['line' => $record['line'], 'status' => 'success', 'transactionId' => $sale['id']];
                } catch (StoreApiException $exception) {
                    $failed++;
                    $resultRows[] = ['line' => $record['line'], 'status' => 'error', 'message' => $exception->getMessage()];
                }
            }
            $batchResponse = [
                'dataState' => $processed > 0 ? 'ready' : 'empty',
                'generatedAt' => date(DATE_ATOM),
                'summary' => ['total' => count($records), 'processed' => $processed, 'skipped' => $skipped, 'failed' => $failed],
                'items' => $resultRows,
                'replayed' => false,
            ];
            // O BFF dispara o processador depois de devolver a resposta. Assim um
            // CSV grande nao fica aguardando chamadas externas ao WhatsApp.
            $batchResponse['whatsappNotifications'] = ['status' => 'queued'];
            $idempotency->complete('store_batch', $storeId, $key, $batchResponse);
            storeV2Respond(200, true, $batchResponse, 'Processamento concluído.');
        } catch (Throwable $exception) {
            $idempotency->fail('store_batch', $storeId, $key);
            throw $exception;
        }
    }
    if ($method === 'POST' && $resource === 'profile/contact') {
        $management->updateContact($storeId, $payload);
        storeV2Respond(200, true, ['updated' => true], 'Informações atualizadas.');
    }
    if ($method === 'POST' && $resource === 'profile/address') {
        $management->updateAddress($storeId, $payload);
        storeV2Respond(200, true, ['updated' => true], 'Endereço atualizado.');
    }
    if ($method === 'POST' && $resource === 'profile/password') {
        $management->changePassword(
            $userId,
            (string) ($payload['currentPassword'] ?? ''),
            (string) ($payload['newPassword'] ?? ''),
            (string) ($payload['confirmation'] ?? '')
        );
        storeV2Respond(200, true, ['updated' => true], 'Senha alterada com sucesso.');
    }
    if ($method === 'POST' && $resource === 'employees') {
        if (!AuthController::canManageEmployees()) {
            throw new StoreApiException('Acesso restrito ao titular e aos gerentes.', 403);
        }
        storeV2Respond(201, true, $management->createEmployee($storeId, AuthController::isStore(), $payload), 'Funcionário criado.');
    }
    if ($method === 'PATCH' && count($segments) === 2 && $segments[0] === 'employees') {
        if (!AuthController::canManageEmployees()) {
            throw new StoreApiException('Acesso restrito ao titular e aos gerentes.', 403);
        }
        $management->updateEmployee($storeId, AuthController::isStore(), (int) $segments[1], $payload);
        storeV2Respond(200, true, ['updated' => true], 'Funcionário atualizado.');
    }
    if ($method === 'DELETE' && count($segments) === 2 && $segments[0] === 'employees') {
        if (!AuthController::isStore()) {
            throw new StoreApiException('Somente o titular pode desativar funcionários.', 403);
        }
        $management->deactivateEmployee($storeId, (int) $segments[1]);
        storeV2Respond(200, true, ['updated' => true], 'Funcionário desativado.');
    }
    if ($method === 'POST' && $resource === 'subscription/redeem') {
        storeV2Respond(200, true, $management->redeemPlan($storeId, (string) ($payload['code'] ?? '')), 'Plano ativado.');
    }

    storeV2Respond(404, false, null, 'Recurso não encontrado.');
} catch (StoreApiException $exception) {
    storeV2Respond($exception->httpStatus, false, null, $exception->getMessage(), $exception->errors);
} catch (Throwable $exception) {
    Logger::error('store.v2.failure', [
        'exception' => get_class($exception),
        'message' => $exception->getMessage(),
    ]);
    storeV2Respond(500, false, null, 'Não foi possível concluir a solicitação.');
}
