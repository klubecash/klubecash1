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
use App\Services\Billing\SubscriptionService;
use App\Services\Billing\BillingFeatureFlags;

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
require_once __DIR__ . '/../services/store/StoreNetworkAccess.php';
require_once __DIR__ . '/../services/store/StoreNetworkHub.php';
require_once __DIR__ . '/../services/store/StoreCustomerService.php';
require_once __DIR__ . '/../services/store/StoreManagementService.php';
require_once __DIR__ . '/../services/store/StoreWhatsAppNotificationService.php';
require_once __DIR__ . '/../services/billing/BillingFeatureFlags.php';
require_once __DIR__ . '/../services/billing/MercadoPagoSubscriptionClient.php';
require_once __DIR__ . '/../services/billing/SubscriptionService.php';
require_once __DIR__ . '/../services/billing/TransparentPaymentException.php';
require_once __DIR__ . '/../services/billing/TransparentPaymentService.php';
require_once __DIR__ . '/../services/StoreWallet/StoreWalletService.php';

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

$userId = (int) ($_SESSION['user_id'] ?? 0);
$resource = trim((string) ($_GET['resource'] ?? ''), '/');
$segments = $resource === '' ? [] : explode('/', $resource);
$db = Database::getConnection();
$access = new \App\Services\Store\StoreNetworkAccess($db);
$availableStores = $access->stores($userId);
$storeId = (int) (AuthController::getStoreId() ?? 0);
if ($userId <= 0 || $availableStores === []) {
    storeV2Respond(403, false, null, 'Conta sem filial ativa associada.');
}
if ($method === 'GET' && $resource === 'stores') {
    storeV2Respond(200, true, ['stores' => $availableStores, 'activeStoreId' => $storeId ?: null, 'csrfToken' => Security::generateCSRFToken()]);
}
if ($method === 'POST' && $resource === 'active-store') {
    $payload = storeV2Payload();
    storeV2Csrf($payload);
    try { $selected = $access->select($userId, (int) ($payload['storeId'] ?? 0)); }
    catch (StoreApiException $error) { storeV2Respond($error->httpStatus, false, null, $error->getMessage()); }
    storeV2Respond(200, true, ['store' => $selected]);
}
if ($storeId <= 0) {
    storeV2Respond(409, false, ['stores' => $availableStores], 'Escolha uma filial antes de continuar.');
}
$activeMembership = $access->membership($userId, $storeId);
if (!$activeMembership) {
    storeV2Respond(409, false, ['stores' => $availableStores], 'Vínculo com a filial ativa indisponível. Escolha outra filial.');
}
$forcedSellerId = ($activeMembership['role'] ?? '') === 'vendedor' ? $userId : null;
$scopeStores = ($activeMembership['role'] ?? '') === 'gestor_rede' && ($_GET['scope'] ?? '') === 'network'
    ? $access->walletStores($storeId) : [$storeId];
if (isset($_GET['storeId']) && $_GET['storeId'] !== '') {
    $filteredStoreId = (int) $_GET['storeId'];
    if (!in_array($filteredStoreId, $scopeStores, true)) {
        storeV2Respond(403, false, null, 'Filial fora da visão autorizada.');
    }
    $scopeStores = [$filteredStoreId];
}
$read = new StoreReadService($db);
$customers = new StoreCustomerService($db);
$transactions = new StoreTransactionService($db);
$management = new StoreManagementService($db);
$networkHub = new \App\Services\Store\StoreNetworkHub($db);
$whatsAppNotifications = new StoreWhatsAppNotificationService($db);
$billing = new SubscriptionService($db);
$transparentPayments = new \App\Services\Billing\TransparentPaymentService($db);

try {
    if ($method === 'GET' && $resource === 'context') {
        $data = $read->context($storeId, $_SESSION);
        $data['stores'] = $availableStores;
        $data['networkId'] = $access->networkId($storeId);
        $data['networkName'] = null;
        if ($data['networkId'] !== null) {
            $name = $db->prepare('SELECT name FROM store_networks WHERE id=?');
            $name->execute([$data['networkId']]);
            $data['networkName'] = $name->fetchColumn() ?: null;
        }
        $data['activeStoreId'] = $storeId;
        $data['canViewNetwork'] = ($activeMembership['role'] ?? '') === 'gestor_rede' && $data['networkId'] !== null;
        $data['csrfToken'] = Security::generateCSRFToken();
        storeV2Respond(200, true, $data);
    }
    if ($method === 'GET' && $resource === 'network/overview') {
        if ($access->networkId($storeId) === null) { throw new StoreApiException('Esta filial não pertence a uma rede ativa.', 404); }
        storeV2Respond(200, true, $networkHub->overview($userId, (int) $access->networkId($storeId)));
    }
    if ($method === 'GET' && $resource === 'network/team') {
        if ($access->networkId($storeId) === null) { throw new StoreApiException('Rede indisponível.', 404); }
        storeV2Respond(200, true, $networkHub->team($userId, (int) $access->networkId($storeId),
            (string) ($_GET['search'] ?? ''), max(1, (int) ($_GET['page'] ?? 1))));
    }
    if ($method === 'GET' && $resource === 'dashboard') {
        $sellerValue = (string) ($_GET['sellerId'] ?? '');
        $sellerId = $forcedSellerId ?? ($sellerValue !== '' && $sellerValue !== 'unknown' ? (int) $sellerValue : null);
        if ($sellerValue !== '' && $sellerValue !== 'unknown' && $sellerId <= 0) { throw new StoreApiException('Vendedor inválido.', 422); }
        storeV2Respond(200, true, $read->dashboard($storeId, $scopeStores, $sellerId,
            storeV2Filters(['startDate', 'endDate', 'status', 'sellerId'])));
    }
    if ($method === 'GET' && $resource === 'reports/sellers') {
        storeV2Respond(200, true, $read->sellerReport($scopeStores,
            $forcedSellerId,
            isset($_GET['startDate']) ? (string) $_GET['startDate'] : null,
            isset($_GET['endDate']) ? (string) $_GET['endDate'] : null,
            isset($_GET['sellerId']) ? (string) $_GET['sellerId'] : null));
    }
    if ($method === 'GET' && count($segments) === 3 && $segments[0] === 'reports' && $segments[1] === 'people') {
        storeV2Respond(200, true, $read->personReport($scopeStores, $forcedSellerId, $segments[2],
            isset($_GET['startDate']) ? (string) $_GET['startDate'] : null,
            isset($_GET['endDate']) ? (string) $_GET['endDate'] : null));
    }
    if ($method === 'GET' && $resource === 'reports/giftback') {
        if ($forcedSellerId !== null) { throw new StoreApiException('Relatório financeiro restrito à gestão.', 403); }
        storeV2Respond(200, true, $read->giftbackReport($scopeStores));
    }
    if ($method === 'GET' && $resource === 'transactions/export') {
        $exportFilters = storeV2Filters(['status', 'startDate', 'endDate', 'customer', 'minimumCents', 'maximumCents', 'sellerId']);
        $format = (string) ($_GET['format'] ?? 'sales');
        if (!in_array($format, ['sales', 'items'], true)) { throw new StoreApiException('Formato de exportação inválido.', 422); }
        $report = $read->transactions($storeId, $exportFilters, 1, 1000, $scopeStores, $forcedSellerId);
        if ($report['pagination']['totalItems'] > 50000) { throw new StoreApiException('O recorte excede 50.000 vendas. Reduza o período ou a filial.', 422); }
        header_remove('Content-Type');
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . ($format === 'items' ? 'itens-vendas' : 'vendas-filiais') . '-klubecash.csv"');
        $output = fopen('php://output', 'wb'); fputs($output, "\xEF\xBB\xBF");
        fputcsv($output, $format === 'items'
            ? ['ID da venda','Código','Filial','Vendedor','Item','Quantidade','Preço unitário','Total do item','Status','Data']
            : ['ID','Código','Filial','Cliente','Vendedor','Registrado por','Canal','Descrição','Valor','Saldo usado','Fora do saldo','Giftback','Status','Data']);
        $safe = static fn (string $value): string => preg_match('/^[=+@\-]/', $value) ? "'" . $value : $value;
        $money = static fn (int $cents): string => number_format($cents / 100, 2, '.', '');
        for ($page = 1; $page <= $report['pagination']['totalPages']; $page++) {
            if ($page > 1) { $report = $read->transactions($storeId, $exportFilters, $page, 1000, $scopeStores, $forcedSellerId); }
            $bySale = $format === 'items' ? $read->saleItemsBatch(array_column($report['items'], 'id')) : [];
            foreach ($report['items'] as $item) {
                if ($format === 'items') {
                    foreach ($bySale[$item['id']] ?? [] as $line) {
                        fputcsv($output, [$item['id'],$safe($item['code']),$safe($item['storeName']),$safe($item['sellerName']),
                            $safe((string) $line['item_name']),(int) $line['quantity'],$money((int) $line['unit_price_cents']),
                            $money((int) $line['total_cents']),$item['status'],$item['occurredAt']]);
                    }
                } else {
                    fputcsv($output, [$item['id'],$safe($item['code']),$safe($item['storeName']),$safe($item['customerName']),
                        $safe($item['sellerName']),$safe($item['recordedByName']),$item['sourceChannel'],$safe($item['description']),
                        $money($item['grossAmountCents']),$money($item['balanceUsedCents']),$money($item['paidAmountCents']),
                        $money($item['cashbackGrantedCents']),$item['status'],$item['occurredAt']]);
                }
            }
            fflush($output);
        }
        fclose($output); exit;
    }
    if ($method === 'GET' && $resource === 'transactions') {
        storeV2Respond(200, true, $read->transactions(
            $storeId,
            storeV2Filters(['status', 'startDate', 'endDate', 'customer', 'minimumCents', 'maximumCents', 'sellerId']),
            max(1, (int) ($_GET['page'] ?? 1)), 10, $scopeStores, $forcedSellerId
        ));
    }
    if ($method === 'GET' && count($segments) === 2 && $segments[0] === 'transactions') {
        storeV2Respond(200, true, $read->transaction($storeId, (int) $segments[1], $scopeStores, $forcedSellerId));
    }
    if ($method === 'GET' && $resource === 'customers/search') {
        storeV2Respond(200, true, $customers->search($storeId, (string) ($_GET['query'] ?? '')));
    }
    if ($method === 'GET' && $resource === 'sellers') {
        if (($_GET['history'] ?? '') === '1') {
            if ($forcedSellerId !== null) { throw new StoreApiException('Consulta restrita à gestão.', 403); }
            storeV2Respond(200, true, $read->reportSellers($scopeStores,
                isset($_GET['startDate']) ? (string) $_GET['startDate'] : null,
                isset($_GET['endDate']) ? (string) $_GET['endDate'] : null));
        }
        $sellerStores = implode(',', array_map('intval', $scopeStores));
        $sellerStmt = $db->prepare("SELECT DISTINCT u.id,u.nome name,
            CASE WHEN gm.user_id IS NOT NULL THEN 'gestor_rede' ELSE m.role END role
            FROM usuarios u
            LEFT JOIN store_user_memberships m ON m.user_id=u.id AND m.store_id IN ({$sellerStores}) AND m.status='active'
            LEFT JOIN store_network_memberships n ON n.store_id IN ({$sellerStores}) AND n.status='active'
            LEFT JOIN store_network_managers gm ON gm.network_id=n.network_id AND gm.user_id=u.id
            WHERE u.status='ativo' AND u.tipo IN ('loja','funcionario')
              AND (m.user_id IS NOT NULL OR gm.user_id IS NOT NULL)
            ORDER BY u.nome,u.id");
        $sellerStmt->execute();
        storeV2Respond(200, true, ['items' => $sellerStmt->fetchAll(PDO::FETCH_ASSOC)]);
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
    if ($method === 'GET' && $resource === 'invitations') {
        storeV2Respond(200, true, $management->invitations($userId));
    }
    if ($method === 'GET' && $resource === 'wallet-link') {
        storeV2Respond(200, true, (new \App\Services\StoreWallet\StoreWalletService($db))->ownLink($storeId));
    }
    if ($method === 'GET' && $resource === 'subscription/invoices') {
        $context = $billing->context($storeId);
        storeV2Respond(200, true, [
            'dataState' => $context['invoices'] !== [] ? 'ready' : 'empty',
            'generatedAt' => date(DATE_ATOM),
            'items' => $context['invoices'],
        ]);
    }
    if ($method === 'GET' && count($segments) === 3 && $segments[0] === 'subscription' && $segments[1] === 'payment-status') {
        storeV2Respond(200, true, $transparentPayments->status($storeId, (int) $segments[2]));
    }

    if ($method === 'GET') {
        storeV2Respond(404, false, null, 'Recurso não encontrado.');
    }
    if (!in_array($method, ['POST', 'PATCH', 'DELETE'], true)) {
        storeV2Respond(405, false, null, 'Método não permitido.');
    }

    $payload = storeV2Payload();
    storeV2Csrf($payload);
    if (in_array($resource, ['network/team/invite', 'network/team/role', 'network/team/deactivate'], true)) {
        $networkId = $access->networkId($storeId);
        $targetStore = (int) ($payload['storeId'] ?? 0);
        if ($networkId === null) { throw new StoreApiException('Rede indisponível.', 404); }
        $access->assertManagedBranch($userId, $networkId, $targetStore);
        if ($method === 'POST' && $resource === 'network/team/invite') {
            storeV2Respond(201, true, $management->inviteExisting($targetStore, $userId,
                (string) ($payload['email'] ?? ''), (string) ($payload['role'] ?? '')), 'Convite criado. A conta precisa aceitar o vínculo.');
        }
        if ($method === 'PATCH' && $resource === 'network/team/role') {
            storeV2Respond(200, true, $management->changeBranchRole($targetStore, (int) ($payload['userId'] ?? 0), $userId,
                (string) ($payload['role'] ?? ''), (string) ($payload['expectedRole'] ?? '')), 'Função atualizada.');
        }
        if ($method === 'POST' && $resource === 'network/team/deactivate') {
            storeV2Respond(200, true, $management->deactivateBranchMember($targetStore,
                (int) ($payload['userId'] ?? 0), $userId), 'Vínculo desativado.');
        }
    }

    if ($method === 'POST' && in_array($resource, ['subscription/payment', 'subscription/payment-retry'], true)) {
        if (!AuthController::isStore()) {
            throw new StoreApiException('Somente o titular da loja pode realizar o pagamento.', 403);
        }
        $key = trim((string) ($_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? ''));
        if ($key === '') { throw new StoreApiException('A chave de idempotência é obrigatória.', 400); }
        try {
            $payment = $transparentPayments->create(
                $storeId,
                $userId,
                (int) ($payload['invoiceId'] ?? 0),
                [
                    'paymentMethodId' => $payload['paymentMethodId'] ?? '',
                    'token' => $payload['token'] ?? '',
                    'issuerId' => $payload['issuerId'] ?? null,
                    'installments' => $payload['installments'] ?? 1,
                    'deviceId' => $payload['deviceId'] ?? null,
                ],
                $key,
            );
            storeV2Respond(201, true, $payment, 'Pagamento enviado ao Mercado Pago.');
        } catch (\App\Services\Billing\TransparentPaymentException $exception) {
            throw new StoreApiException($exception->getMessage(), $exception->httpStatus, $exception->errors);
        }
    }

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
                    $sellerId = null;
                    if (trim((string) ($values['email_vendedor'] ?? '')) !== '') {
                        $sellerLookup = $db->prepare("SELECT id FROM usuarios WHERE LOWER(email)=LOWER(?) AND status='ativo' LIMIT 1");
                        $sellerLookup->execute([trim((string) $values['email_vendedor'])]);
                        $sellerId = (int) ($sellerLookup->fetchColumn() ?: 0);
                        if ($sellerId <= 0) { throw new StoreApiException('Vendedor não encontrado nesta filial.', 422); }
                        $access->assertSeller($sellerId, $storeId);
                    }
                    $sale = $transactions->create($storeId, $userId, [
                        'customerId' => $customerId,
                        'grossAmountCents' => StoreMoney::toCents($values['valor_total'] ?? 0),
                        'balanceUsedCents' => StoreMoney::toCents($values['valor_saldo_usado'] ?? 0),
                        'code' => (string) ($values['codigo_transacao'] ?? ''),
                        'description' => (string) ($values['descricao'] ?? 'Importação em lote'),
                        'occurredAt' => (string) ($values['data_transacao'] ?? date(DATE_ATOM)),
                        'sellerId' => $sellerId,
                    ], $key . ':' . $record['line'], 'csv');
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
        storeV2Respond(201, true, $management->createEmployee($storeId, in_array($activeMembership['role'], ['titular','gestor_rede'], true), $payload), 'Funcionário criado.');
    }
    if ($method === 'POST' && $resource === 'employees/invite') {
        if (!AuthController::canManageEmployees()) { throw new StoreApiException('Acesso restrito à gestão da filial.', 403); }
        if (($payload['role'] ?? '') === 'gerente' && !in_array($activeMembership['role'], ['titular','gestor_rede'], true)) {
            throw new StoreApiException('Somente titular ou gestor da rede pode convidar gerente.', 403);
        }
        storeV2Respond(201, true, $management->inviteExisting($storeId, $userId,
            (string) ($payload['email'] ?? ''), (string) ($payload['role'] ?? '')), 'Convite enviado para a conta existente.');
    }
    if ($method === 'POST' && count($segments) === 3 && $segments[0] === 'invitations' && $segments[2] === 'accept') {
        storeV2Respond(200, true, $management->acceptInvitation($userId, (int) $segments[1]), 'Filial vinculada.');
    }
    if ($method === 'PATCH' && count($segments) === 2 && $segments[0] === 'employees') {
        if (!AuthController::canManageEmployees()) {
            throw new StoreApiException('Acesso restrito ao titular e aos gerentes.', 403);
        }
        $management->updateEmployee($storeId, in_array($activeMembership['role'], ['titular','gestor_rede'], true), (int) $segments[1], $payload);
        storeV2Respond(200, true, ['updated' => true], 'Funcionário atualizado.');
    }
    if ($method === 'DELETE' && count($segments) === 2 && $segments[0] === 'employees') {
        if (!in_array($activeMembership['role'], ['titular','gestor_rede'], true)) {
            throw new StoreApiException('Somente o titular ou gestor da rede pode desativar funcionários.', 403);
        }
        $management->deactivateEmployee($storeId, (int) $segments[1]);
        storeV2Respond(200, true, ['updated' => true], 'Funcionário desativado.');
    }
    if ($method === 'POST' && $resource === 'subscription/redeem') {
        storeV2Respond(200, true, $management->redeemPlan($storeId, (string) ($payload['code'] ?? '')), 'Plano ativado.');
    }
    if ($method === 'POST' && $resource === 'subscription/checkout') {
        if (BillingFeatureFlags::transparentCheckoutEnabled()) { throw new StoreApiException('Use o Checkout Transparente para pagar esta fatura.', 409); }
        $key = (string) ($_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? '');
        if ($key === '') { throw new StoreApiException('A chave de idempotência é obrigatória.', 400); }
        storeV2Respond(200, true, $billing->recoveryCheckout($storeId, (int) ($payload['invoiceId'] ?? 0), $key), 'Link de pagamento gerado.');
    }
    if ($method === 'POST' && $resource === 'subscription/pix') {
        if (BillingFeatureFlags::transparentCheckoutEnabled()) { throw new StoreApiException('Use o Checkout Transparente para pagar esta assinatura.', 409); }
        if (!AuthController::isStore()) { throw new StoreApiException('Somente o titular da loja pode iniciar o pagamento.', 403); }
        $key = (string) ($_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? '');
        if ($key === '') { throw new StoreApiException('A chave de idempotência é obrigatória.', 400); }
        try {
            storeV2Respond(200, true, $billing->initialCheckout($storeId, $key), 'Link de pagamento PIX gerado.');
        } catch (RuntimeException $exception) { throw new StoreApiException($exception->getMessage(), 422); }
    }
    if ($method === 'POST' && $resource === 'subscription/start') {
        if (!AuthController::isStore()) { throw new StoreApiException('Somente o titular da loja pode iniciar uma assinatura.', 403); }
        $key = (string) ($_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? '');
        if ($key === '') { throw new StoreApiException('A chave de idempotência é obrigatória.', 400); }
        try {
            storeV2Respond(201, true, $billing->startSubscription($storeId, (string) ($payload['planSlug'] ?? ''), (string) ($payload['cycle'] ?? 'monthly'), $key), 'Assinatura criada. Conclua o pagamento para liberar novas vendas.');
        } catch (RuntimeException $exception) { throw new StoreApiException($exception->getMessage(), 422); }
    }
    if ($method === 'POST' && $resource === 'subscription/change-plan') {
        if (!AuthController::isStore()) { throw new StoreApiException('Somente o titular da loja pode alterar o plano.', 403); }
        try {
            storeV2Respond(200, true, $billing->requestPlanChange($storeId, (string) ($payload['planSlug'] ?? ''), (string) ($payload['cycle'] ?? 'monthly'), $userId), 'Troca de plano agendada para a próxima renovação.');
        } catch (RuntimeException $exception) { throw new StoreApiException($exception->getMessage(), 422); }
    }
    if ($method === 'POST' && $resource === 'subscription/action') {
        if (!in_array($activeMembership['role'], ['titular','gestor_rede'], true)) {
            throw new StoreApiException('Somente o titular da loja pode administrar a assinatura.', 403);
        }
        if (trim((string) ($_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? '')) === '') {
            throw new StoreApiException('A chave de idempotência é obrigatória.', 400);
        }
        $action = trim((string) ($payload['action'] ?? ''));
        if (!in_array($action, ['pause', 'cancel', 'reactivate'], true)) {
            throw new StoreApiException('Ação de assinatura inválida.', 422);
        }
        $context = $billing->context($storeId);
        $subscriptionId = (int) ($context['subscription']['id'] ?? 0);
        if ($subscriptionId <= 0) { throw new StoreApiException('A loja não possui assinatura.', 404); }
        try {
            $result = $billing->manualAction($subscriptionId, $action === 'pause' ? 'pause' : ($action === 'cancel' ? 'cancel' : 'reactivate'), $userId, (string) ($payload['reason'] ?? 'Solicitação do titular'), null, (string) ($payload['updatedAt'] ?? ''), $action === 'reactivate');
            storeV2Respond(200, true, $result, 'Assinatura atualizada.');
        } catch (RuntimeException $exception) {
            throw new StoreApiException($exception->getMessage(), 422);
        }
    }

    storeV2Respond(404, false, null, 'Recurso não encontrado.');
} catch (StoreApiException $exception) {
    storeV2Respond($exception->httpStatus, false, null, $exception->getMessage(), $exception->errors);
} catch (\App\Services\Billing\TransparentPaymentException $exception) {
    storeV2Respond($exception->httpStatus, false, null, $exception->getMessage(), $exception->errors);
} catch (Throwable $exception) {
    Logger::error('store.v2.failure', [
        'exception' => get_class($exception),
        'message' => $exception->getMessage(),
    ]);
    storeV2Respond(500, false, null, 'Não foi possível concluir a solicitação.');
}
