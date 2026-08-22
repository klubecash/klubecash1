<?php

declare(strict_types=1);

use App\Core\RequestContext;

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Vary: Cookie');

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/StoreController.php';
require_once __DIR__ . '/../controllers/TransactionController.php';
require_once __DIR__ . '/../controllers/SubscriptionController.php';
require_once __DIR__ . '/../controllers/StoreBalancePaymentController.php';
require_once __DIR__ . '/../models/CashbackBalance.php';
require_once __DIR__ . '/../utils/FeatureGate.php';
require_once __DIR__ . '/../utils/Security.php';

function storeAppRespond(int $httpStatus, bool $ok, mixed $data = null, ?string $message = null, array $errors = []): never
{
    http_response_code($httpStatus);
    $payload = [
        'status' => $ok ? 'success' : 'error',
        'requestId' => RequestContext::id(),
    ];
    if ($data !== null) {
        $payload['data'] = $data;
    }
    if ($message !== null && $message !== '') {
        $payload['message'] = $message;
    }
    if ($errors !== []) {
        $payload['errors'] = $errors;
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function storeAppPayload(): array
{
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : [];
    }
    return $_POST;
}

function storeAppInput(string $key, mixed $default = null): mixed
{
    return $_GET[$key] ?? $default;
}

function storeAppFilters(array $allowed): array
{
    $filters = [];
    foreach ($allowed as $key) {
        $value = trim((string) ($_GET[$key] ?? ''));
        if ($value !== '') {
            $filters[$key] = $value;
        }
    }
    return $filters;
}

function storeAppLegacyResult(array $result): never
{
    if (!($result['status'] ?? false)) {
        storeAppRespond(422, false, null, (string) ($result['message'] ?? 'N\u00e3o foi poss\u00edvel concluir a solicita\u00e7\u00e3o.'));
    }
    storeAppRespond(200, true, $result['data'] ?? $result, (string) ($result['message'] ?? ''));
}

function storeAppInitial(string $name): string
{
    return function_exists('mb_strtoupper')
        ? mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8')
        : strtoupper(substr($name, 0, 1));
}

function storeAppValidateCsrf(array $payload): void
{
    $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $payload['csrfToken'] ?? '');
    if (!Security::validateCSRFToken($token)) {
        storeAppRespond(419, false, null, 'Sua sess\u00e3o de seguran\u00e7a expirou. Atualize a p\u00e1gina e tente novamente.');
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    header('Allow: GET, POST, PATCH, DELETE, OPTIONS');
    http_response_code(204);
    exit;
}

if (!AuthController::isAuthenticated()) {
    storeAppRespond(401, false, null, 'Sess\u00e3o expirada. Fa\u00e7a login novamente.');
}
if (!AuthController::hasStoreAccess()) {
    storeAppRespond(403, false, null, 'Acesso restrito a lojas parceiras.');
}

$storeId = (int) (AuthController::getStoreId() ?? 0);
$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($storeId <= 0 || $userId <= 0) {
    storeAppRespond(422, false, null, 'Conta sem loja associada.');
}

$db = Database::getConnection();
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$resource = trim((string) storeAppInput('resource', 'context'));

try {
    if ($method === 'GET') {
        switch ($resource) {
            case 'context':
                $statement = $db->prepare("SELECT l.id, l.nome_fantasia, l.status, l.logo, l.porcentagem_cashback,
                    COALESCE(l.porcentagem_cliente, 5.00) porcentagem_cliente,
                    COALESCE(l.porcentagem_admin, 5.00) porcentagem_admin,
                    COALESCE(l.cashback_ativo, 1) cashback_ativo,
                    COALESCE(u.mvp, 'nao') mvp
                    FROM lojas l JOIN usuarios u ON u.id = l.usuario_id WHERE l.id = :store_id LIMIT 1");
                $statement->execute([':store_id' => $storeId]);
                $store = $statement->fetch(PDO::FETCH_ASSOC);
                if (!$store) {
                    storeAppRespond(404, false, null, 'Loja n\u00e3o encontrada.');
                }
                $userType = (string) ($_SESSION['user_type'] ?? '');
                $subtype = $userType === 'funcionario'
                    ? (string) ($_SESSION['subtipo_funcionario'] ?? $_SESSION['employee_subtype'] ?? 'funcionario')
                    : null;
                $planInfo = FeatureGate::getPlanInfo($storeId);
                storeAppRespond(200, true, [
                    'store' => [
                        'name' => (string) $store['nome_fantasia'],
                        'status' => (string) $store['status'],
                        'logoUrl' => !empty($store['logo']) ? '/uploads/store_logos/' . basename((string) $store['logo']) : null,
                        'cashbackPercent' => (float) $store['porcentagem_cashback'],
                        'customerPercent' => (float) $store['porcentagem_cliente'],
                        'adminPercent' => (float) $store['porcentagem_admin'],
                        'cashbackEnabled' => (bool) $store['cashback_ativo'],
                        'mvp' => $store['mvp'] === 'sim',
                    ],
                    'user' => [
                        'name' => (string) ($_SESSION['user_name'] ?? 'Usu\u00e1rio'),
                        'type' => $userType,
                        'subtype' => $subtype,
                        'avatarInitial' => storeAppInitial((string) ($_SESSION['user_name'] ?? 'U')),
                    ],
                    'permissions' => [
                        'manageEmployees' => AuthController::canManageEmployees(),
                        'deactivateEmployees' => AuthController::isStore(),
                    ],
                    'subscription' => [
                        'active' => FeatureGate::isActive($storeId),
                        'status' => $planInfo['status'] ?? null,
                        'planName' => $planInfo['plano_nome'] ?? null,
                    ],
                    'csrfToken' => Security::generateCSRFToken(),
                ]);

            case 'dashboard':
                $stats = $db->prepare("SELECT COUNT(*) total_sales,
                    COALESCE(SUM(valor_total),0) total_value,
                    COALESCE(SUM(valor_cashback),0) total_cashback,
                    SUM(status = 'pendente') pending_count,
                    COALESCE(SUM(CASE WHEN status = 'pendente' THEN valor_cashback ELSE 0 END),0) pending_value
                    FROM transacoes_cashback WHERE loja_id = :store_id");
                $stats->execute([':store_id' => $storeId]);
                $recent = $db->prepare("SELECT t.id, t.codigo_transacao code, t.valor_total total, t.valor_cashback cashback,
                    t.status, t.data_transacao date, u.nome customer
                    FROM transacoes_cashback t JOIN usuarios u ON u.id=t.usuario_id
                    WHERE t.loja_id=:store_id ORDER BY t.data_transacao DESC LIMIT 6");
                $recent->execute([':store_id' => $storeId]);
                $monthly = $db->prepare("SELECT DATE_FORMAT(data_transacao,'%Y-%m') month, COUNT(*) sales,
                    COALESCE(SUM(valor_total),0) total FROM transacoes_cashback
                    WHERE loja_id=:store_id AND data_transacao >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
                    GROUP BY DATE_FORMAT(data_transacao,'%Y-%m') ORDER BY month");
                $monthly->execute([':store_id' => $storeId]);
                storeAppRespond(200, true, [
                    'summary' => $stats->fetch(PDO::FETCH_ASSOC),
                    'recentTransactions' => $recent->fetchAll(PDO::FETCH_ASSOC),
                    'monthlySales' => $monthly->fetchAll(PDO::FETCH_ASSOC),
                ]);

            case 'transactions':
                storeAppLegacyResult(TransactionController::getStoreTransactions(
                    $storeId,
                    storeAppFilters(['data_inicio', 'data_fim', 'status', 'cliente', 'valor_min', 'valor_max']),
                    max(1, (int) storeAppInput('page', 1))
                ));

            case 'pending':
                storeAppLegacyResult(TransactionController::getPendingTransactionsWithBalance(
                    $storeId,
                    storeAppFilters(['data_inicio', 'data_fim', 'valor_min', 'valor_max']),
                    max(1, (int) storeAppInput('page', 1))
                ));

            case 'payment_history':
                storeAppLegacyResult(TransactionController::getPaymentHistoryWithBalance(
                    $storeId,
                    storeAppFilters(['data_inicio', 'data_fim', 'status', 'metodo_pagamento']),
                    max(1, (int) storeAppInput('page', 1))
                ));

            case 'balance_history':
                storeAppLegacyResult(StoreBalancePaymentController::getStoreBalanceHistory(
                    $storeId,
                    storeAppFilters(['status', 'data_inicio', 'data_fim']),
                    max(1, (int) storeAppInput('page', 1))
                ));

            case 'payment_pix':
                $paymentId = max(0, (int) storeAppInput('payment_id', 0));
                $payment = $db->prepare("SELECT id, valor_total, status, metodo_pagamento, data_registro,
                    mp_payment_id, mp_qr_code, mp_qr_code_base64
                    FROM pagamentos_comissao WHERE id=:payment_id AND loja_id=:store_id LIMIT 1");
                $payment->execute([':payment_id'=>$paymentId, ':store_id'=>$storeId]);
                $paymentData = $payment->fetch(PDO::FETCH_ASSOC);
                if (!$paymentData) {
                    storeAppRespond(404, false, null, 'Pagamento n\u00e3o encontrado.');
                }
                $count = $db->prepare('SELECT COUNT(*) FROM pagamentos_transacoes WHERE pagamento_id=:payment_id');
                $count->execute([':payment_id'=>$paymentId]);
                $paymentData['transaction_count'] = (int) $count->fetchColumn();
                storeAppRespond(200, true, $paymentData);

            case 'employees':
                storeAppLegacyResult(StoreController::getEmployees(
                    storeAppFilters(['subtipo', 'status', 'busca']),
                    max(1, (int) storeAppInput('page', 1))
                ));

            case 'profile':
                $profile = $db->prepare("SELECT l.nome_fantasia, l.razao_social, l.cnpj, l.telefone, l.website, l.descricao,
                    l.porcentagem_cashback, l.status, l.data_cadastro, u.email,
                    e.cep, e.logradouro, e.numero, e.complemento, e.bairro, e.cidade, e.estado
                    FROM lojas l JOIN usuarios u ON u.id=l.usuario_id
                    LEFT JOIN lojas_endereco e ON e.loja_id=l.id WHERE l.id=:store_id LIMIT 1");
                $profile->execute([':store_id' => $storeId]);
                storeAppRespond(200, true, $profile->fetch(PDO::FETCH_ASSOC));

            case 'subscription':
                $controller = new SubscriptionController($db);
                $subscription = $controller->getActiveSubscriptionByStore($storeId);
                $plans = $db->query("SELECT nome, slug, preco_mensal, preco_anual, trial_dias, features_json FROM planos WHERE ativo=1 ORDER BY preco_mensal")->fetchAll(PDO::FETCH_ASSOC);
                storeAppRespond(200, true, [
                    'subscription' => $subscription ?: null,
                    'planInfo' => FeatureGate::getPlanInfo($storeId),
                    'plans' => $plans,
                ]);

            default:
                storeAppRespond(404, false, null, 'Recurso n\u00e3o encontrado.');
        }
    }

    $payload = storeAppPayload();
    storeAppValidateCsrf($payload);
    $action = trim((string) ($payload['action'] ?? $resource));

    switch ($action) {
        case 'batch_upload':
            $file = $_FILES['file'] ?? null;
            if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                storeAppRespond(422, false, null, 'Selecione um arquivo CSV v\u00e1lido.');
            }
            if ((int) ($file['size'] ?? 0) > MAX_UPLOAD_SIZE) {
                storeAppRespond(413, false, null, 'O arquivo excede o limite de 10 MB.');
            }
            if (strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)) !== 'csv') {
                storeAppRespond(422, false, null, 'O arquivo deve estar no formato CSV.');
            }
            $handle = fopen((string) $file['tmp_name'], 'rb');
            if ($handle === false) {
                storeAppRespond(422, false, null, 'N\u00e3o foi poss\u00edvel ler o arquivo.');
            }
            $headers = fgetcsv($handle, 4096, ',');
            $expected = ['email_cliente', 'valor_total', 'codigo_transacao'];
            if (!is_array($headers) || array_diff($expected, array_map('trim', $headers)) !== []) {
                fclose($handle);
                storeAppRespond(422, false, null, 'Cabe\u00e7alho inv\u00e1lido. Use email_cliente, valor_total e codigo_transacao.');
            }
            $headers = array_map('trim', $headers);
            $results = [];
            $processed = $skipped = $failed = 0;
            $line = 1;
            while (($row = fgetcsv($handle, 4096, ',')) !== false) {
                $line++;
                if ($line > 501) {
                    $failed++;
                    $results[] = ['line' => $line, 'status' => 'error', 'message' => 'Limite de 500 linhas por arquivo atingido.'];
                    break;
                }
                if (count($row) !== count($headers)) {
                    $failed++;
                    $results[] = ['line'=>$line, 'status'=>'error', 'message'=>'Quantidade de colunas inv\u00e1lida.'];
                    continue;
                }
                $record = array_combine($headers, $row);
                if (!is_array($record) || empty($record['email_cliente']) || empty($record['valor_total']) || empty($record['codigo_transacao'])) {
                    $failed++;
                    $results[] = ['line'=>$line, 'status'=>'error', 'message'=>'Campos obrigat\u00f3rios ausentes.'];
                    continue;
                }
                $clientStatement = $db->prepare("SELECT id FROM usuarios WHERE email=:email AND tipo='cliente' AND status='ativo' LIMIT 1");
                $clientStatement->execute([':email'=>trim((string) $record['email_cliente'])]);
                $clientId = (int) ($clientStatement->fetchColumn() ?: 0);
                if ($clientId <= 0) {
                    $skipped++;
                    $results[] = ['line'=>$line, 'status'=>'skipped', 'message'=>'Cliente n\u00e3o encontrado ou inativo.'];
                    continue;
                }
                $result = TransactionController::registerTransactionFixed([
                    'usuario_id'=>$clientId,
                    'loja_id'=>$storeId,
                    'valor_total'=>(float) str_replace(',', '.', (string) $record['valor_total']),
                    'codigo_transacao'=>trim((string) $record['codigo_transacao']),
                    'descricao'=>trim((string) ($record['descricao'] ?? 'Importa\u00e7\u00e3o em lote')),
                    'data_transacao'=>trim((string) ($record['data_transacao'] ?? '')) ?: date('Y-m-d H:i:s'),
                    'usar_saldo'=>(float) ($record['valor_saldo_usado'] ?? 0) > 0,
                    'valor_saldo_usado'=>(float) str_replace(',', '.', (string) ($record['valor_saldo_usado'] ?? 0)),
                ]);
                if ($result['status'] ?? false) {
                    $processed++;
                    $results[] = ['line'=>$line, 'status'=>'success', 'message'=>'Transa\u00e7\u00e3o registrada.'];
                } else {
                    $failed++;
                    $results[] = ['line'=>$line, 'status'=>'error', 'message'=>(string) ($result['message'] ?? 'Falha ao registrar.')];
                }
            }
            fclose($handle);
            storeAppRespond(200, true, [
                'summary'=>['total'=>$processed+$skipped+$failed,'processed'=>$processed,'skipped'=>$skipped,'failed'=>$failed],
                'results'=>$results,
            ], 'Processamento conclu\u00eddo.');

        case 'register_transaction':
            $transaction = [
                'usuario_id' => (int) ($payload['customerId'] ?? 0),
                'loja_id' => $storeId,
                'valor_total' => (float) ($payload['total'] ?? 0),
                'codigo_transacao' => trim((string) ($payload['code'] ?? '')),
                'descricao' => trim((string) ($payload['description'] ?? '')),
                'data_transacao' => trim((string) ($payload['date'] ?? date('Y-m-d H:i:s'))),
                'usar_saldo' => !empty($payload['useBalance']),
                'valor_saldo_usado' => (float) ($payload['balanceAmount'] ?? 0),
            ];
            storeAppLegacyResult(TransactionController::registerTransactionFixed($transaction));

        case 'update_contact':
            $phone = preg_replace('/\D+/', '', (string) ($payload['phone'] ?? ''));
            $website = trim((string) ($payload['website'] ?? ''));
            $description = trim((string) ($payload['description'] ?? ''));
            if (strlen($phone) < 10 || strlen($phone) > 11) {
                storeAppRespond(422, false, null, 'Informe um telefone v\u00e1lido com DDD.');
            }
            if ($website !== '' && filter_var($website, FILTER_VALIDATE_URL) === false) {
                storeAppRespond(422, false, null, 'Informe uma URL completa e v\u00e1lida.');
            }
            $update = $db->prepare('UPDATE lojas SET telefone=:phone, website=:website, descricao=:description WHERE id=:store_id');
            $update->execute([':phone' => $phone, ':website' => $website, ':description' => $description, ':store_id' => $storeId]);
            storeAppRespond(200, true, null, 'Informa\u00e7\u00f5es atualizadas com sucesso.');

        case 'update_address':
            $required = ['cep', 'street', 'number', 'neighborhood', 'city', 'state'];
            foreach ($required as $field) {
                if (trim((string) ($payload[$field] ?? '')) === '') {
                    storeAppRespond(422, false, null, 'Preencha todos os campos obrigat\u00f3rios do endere\u00e7o.');
                }
            }
            $addressValues = [
                ':store_id'=>$storeId, ':cep'=>preg_replace('/\D+/', '', (string) $payload['cep']),
                ':street'=>trim((string) $payload['street']), ':number'=>trim((string) $payload['number']),
                ':complement'=>trim((string) ($payload['complement'] ?? '')), ':neighborhood'=>trim((string) $payload['neighborhood']),
                ':city'=>trim((string) $payload['city']), ':state'=>strtoupper(substr(trim((string) $payload['state']), 0, 2)),
            ];
            $addressExists = $db->prepare('SELECT id FROM lojas_endereco WHERE loja_id=:store_id LIMIT 1');
            $addressExists->execute([':store_id' => $storeId]);
            if ($addressExists->fetchColumn()) {
                $update = $db->prepare("UPDATE lojas_endereco SET cep=:cep, logradouro=:street, numero=:number,
                    complemento=:complement, bairro=:neighborhood, cidade=:city, estado=:state WHERE loja_id=:store_id");
            } else {
                $update = $db->prepare("INSERT INTO lojas_endereco (loja_id,cep,logradouro,numero,complemento,bairro,cidade,estado)
                    VALUES (:store_id,:cep,:street,:number,:complement,:neighborhood,:city,:state)");
            }
            $update->execute($addressValues);
            storeAppRespond(200, true, null, 'Endere\u00e7o atualizado com sucesso.');

        case 'change_password':
            $current = (string) ($payload['currentPassword'] ?? '');
            $password = (string) ($payload['newPassword'] ?? '');
            $confirm = (string) ($payload['confirmPassword'] ?? '');
            if (strlen($password) < PASSWORD_MIN_LENGTH || $password !== $confirm) {
                storeAppRespond(422, false, null, 'A nova senha deve ter ao menos 8 caracteres e a confirma\u00e7\u00e3o deve coincidir.');
            }
            $check = $db->prepare('SELECT senha_hash FROM usuarios WHERE id=:user_id');
            $check->execute([':user_id' => $userId]);
            if (!password_verify($current, (string) $check->fetchColumn())) {
                storeAppRespond(422, false, null, 'Senha atual incorreta.');
            }
            $update = $db->prepare('UPDATE usuarios SET senha_hash=:hash WHERE id=:user_id');
            $update->execute([':hash'=>password_hash($password, PASSWORD_DEFAULT, ['cost'=>12]), ':user_id'=>$userId]);
            storeAppRespond(200, true, null, 'Senha alterada com sucesso.');

        case 'create_employee':
            storeAppLegacyResult(StoreController::createEmployee([
                'nome' => trim((string) ($payload['name'] ?? '')),
                'email' => trim((string) ($payload['email'] ?? '')),
                'telefone' => preg_replace('/\D+/', '', (string) ($payload['phone'] ?? '')),
                'subtipo_funcionario' => (string) ($payload['subtype'] ?? ''),
                'senha' => (string) ($payload['password'] ?? ''),
            ]));

        case 'update_employee':
            storeAppLegacyResult(StoreController::updateEmployee((int) ($payload['id'] ?? 0), [
                'nome' => trim((string) ($payload['name'] ?? '')),
                'email' => trim((string) ($payload['email'] ?? '')),
                'telefone' => preg_replace('/\D+/', '', (string) ($payload['phone'] ?? '')),
                'subtipo_funcionario' => (string) ($payload['subtype'] ?? ''),
                'status' => (string) ($payload['employeeStatus'] ?? ''),
                'senha' => (string) ($payload['password'] ?? ''),
            ]));

        case 'delete_employee':
            if (!AuthController::isStore()) {
                storeAppRespond(403, false, null, 'Apenas o lojista titular pode desativar funcion\u00e1rios.');
            }
            storeAppLegacyResult(StoreController::deleteEmployee((int) ($payload['id'] ?? 0)));

        case 'redeem_plan':
            $existing = (new SubscriptionController($db))->getActiveSubscriptionByStore($storeId);
            if ($existing) {
                storeAppRespond(422, false, null, 'Voc\u00ea j\u00e1 possui uma assinatura ativa. Entre em contato com o suporte para mudar de plano.');
            }
            $code = strtoupper(trim((string) ($payload['code'] ?? '')));
            $planStatement = $db->prepare('SELECT slug, nome, recorrencia FROM planos WHERE codigo=:code AND ativo=1 LIMIT 1');
            $planStatement->execute([':code' => $code]);
            $plan = $planStatement->fetch(PDO::FETCH_ASSOC);
            if (!$plan) {
                storeAppRespond(422, false, null, 'C\u00f3digo de plano inv\u00e1lido ou expirado.');
            }
            $result = (new SubscriptionController($db))->assignPlanToStore(
                $storeId,
                (string) $plan['slug'],
                null,
                $plan['recorrencia'] === 'yearly' ? 'yearly' : 'monthly'
            );
            if (!($result['success'] ?? false)) {
                storeAppRespond(422, false, null, (string) ($result['message'] ?? 'N\u00e3o foi poss\u00edvel ativar o plano.'));
            }
            FeatureGate::clearCache($storeId);
            storeAppRespond(200, true, ['subscriptionId' => (int) $result['assinatura_id']], 'Plano ativado com sucesso.');

        default:
            storeAppRespond(400, false, null, 'A\u00e7\u00e3o inv\u00e1lida.');
    }
} catch (Throwable $error) {
    error_log('Store app API [' . RequestContext::id() . ']: ' . $error->getMessage());
    storeAppRespond(500, false, null, 'N\u00e3o foi poss\u00edvel concluir a solicita\u00e7\u00e3o.');
}
