<?php

declare(strict_types=1);

/**
 * Integração controlada da API lojista v2.
 *
 * Este teste usa somente registros identificados por um test_run_id e remove
 * todos eles no finally. Nenhum provedor externo é chamado pelo serviço v2.
 * Execute: php -d extension=openssl -d extension=pdo_mysql tests/php/store_v2_integration.php
 */

require dirname(__DIR__, 2) . '/bootstrap/app.php';
require_once dirname(__DIR__, 2) . '/controllers/AuthController.php';
require_once dirname(__DIR__, 2) . '/controllers/SubscriptionController.php';
require_once dirname(__DIR__, 2) . '/utils/FeatureGate.php';

foreach ([
    'StoreApiException',
    'StoreMoney',
    'StoreIdempotencyService',
    'StoreTransactionService',
    'StoreReadService',
    'StoreCustomerService',
    'StoreManagementService',
] as $service) {
    require_once dirname(__DIR__, 2) . '/services/store/' . $service . '.php';
}

use App\Services\Store\StoreApiException;
use App\Services\Store\StoreCustomerService;
use App\Services\Store\StoreManagementService;
use App\Services\Store\StoreReadService;
use App\Services\Store\StoreTransactionService;

$db = Database::getConnection();
$runId = 'store_v2_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
$fixture = ['storeId' => 0, 'ownerId' => 0, 'customerId' => 0];
$cleaned = false;

function expectTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function scalar(PDO $db, string $sql, array $params = []): mixed
{
    $statement = $db->prepare($sql);
    $statement->execute($params);
    return $statement->fetchColumn();
}

function cleanupFixture(PDO $db, array $fixture): void
{
    $storeId = (int) ($fixture['storeId'] ?? 0);
    $ownerId = (int) ($fixture['ownerId'] ?? 0);
    $customerId = (int) ($fixture['customerId'] ?? 0);
    if ($storeId <= 0) {
        return;
    }

    $statements = [
        'DELETE s FROM sessoes s JOIN usuarios u ON u.id=s.usuario_id WHERE u.loja_vinculada_id=:store_id',
        'DELETE a FROM app_sessions a JOIN usuarios u ON u.id=a.user_id WHERE u.loja_vinculada_id=:store_id',
        'DELETE pt FROM pagamentos_transacoes pt JOIN pagamentos_comissao pc ON pc.id=pt.pagamento_id WHERE pc.loja_id=:store_id',
        'DELETE FROM pagamentos_comissao WHERE loja_id=:store_id',
        'DELETE FROM store_balance_payments WHERE loja_id=:store_id',
        'DELETE FROM store_event_outbox WHERE loja_id=:store_id',
        'DELETE FROM cashback_movimentacoes WHERE loja_id=:store_id',
        'DELETE FROM transacoes_saldo_usado WHERE loja_id=:store_id',
        'DELETE FROM transacoes_cashback WHERE loja_id=:store_id',
        'DELETE FROM store_idempotency_keys WHERE loja_id=:store_id',
        'DELETE FROM cashback_saldos WHERE loja_id=:store_id',
        'DELETE FROM lojas_endereco WHERE loja_id=:store_id',
        'DELETE FROM assinaturas WHERE loja_id=:store_id',
        'DELETE FROM usuarios WHERE loja_vinculada_id=:store_id',
        'DELETE FROM usuarios WHERE loja_criadora_id=:store_id',
        'DELETE FROM lojas WHERE id=:store_id',
    ];
    foreach ($statements as $sql) {
        $statement = $db->prepare($sql);
        $statement->execute([':store_id' => $storeId]);
    }
    if ($ownerId > 0) {
        $statement = $db->prepare('DELETE FROM usuarios WHERE id=:owner_id');
        $statement->execute([':owner_id' => $ownerId]);
    }
    if ($customerId > 0) {
        $statement = $db->prepare('DELETE FROM usuarios WHERE id=:customer_id');
        $statement->execute([':customer_id' => $customerId]);
    }
}

register_shutdown_function(static function () use ($db, &$fixture, &$cleaned): void {
    if (!$cleaned) {
        try {
            cleanupFixture($db, $fixture);
        } catch (Throwable $exception) {
            fwrite(STDERR, "Falha na limpeza de emergência: " . get_class($exception) . PHP_EOL);
        }
    }
});

$store34Before = (int) scalar($db, 'SELECT COUNT(*) FROM transacoes_cashback WHERE loja_id=34');

try {
    $owner = $db->prepare(
        "INSERT INTO usuarios (nome,email,telefone,senha_hash,tipo,status,provider,email_verified) "
        . "VALUES (:name,:email,'11999990000',:password,'loja','ativo','local',1)"
    );
    $owner->execute([
        ':name' => 'Fixture ' . $runId,
        ':email' => $runId . '.owner@klubecash.test',
        ':password' => password_hash('SenhaInicial123!', PASSWORD_DEFAULT),
    ]);
    $fixture['ownerId'] = (int) $db->lastInsertId();

    $store = $db->prepare(
        'INSERT INTO lojas (usuario_id,nome_fantasia,razao_social,cnpj,email,telefone,porcentagem_cashback,'
        . 'porcentagem_cliente,porcentagem_admin,cashback_ativo,status) '
        . "VALUES (:owner,:name,:legal,:cnpj,:email,'11999990000','19.00','7.50','11.50',1,'aprovado')"
    );
    $store->execute([
        ':owner' => $fixture['ownerId'],
        ':name' => 'Fixture ' . $runId,
        ':legal' => 'Fixture de integração ' . $runId,
        ':cnpj' => substr(hash('sha256', $runId), 0, 14),
        ':email' => $runId . '.store@klubecash.test',
    ]);
    $fixture['storeId'] = (int) $db->lastInsertId();

    $customer = $db->prepare(
        "INSERT INTO usuarios (nome,email,telefone,senha_hash,tipo,tipo_cliente,status,provider,email_verified) "
        . "VALUES (:name,:email,'11988880000',:password,'cliente','completo','ativo','local',1)"
    );
    $customer->execute([
        ':name' => 'Cliente ' . $runId,
        ':email' => $runId . '.customer@klubecash.test',
        ':password' => password_hash('Cliente123!', PASSWORD_DEFAULT),
    ]);
    $customerId = (int) $db->lastInsertId();
    $fixture['customerId'] = $customerId;
    $balance = $db->prepare(
        "INSERT INTO cashback_saldos (usuario_id,loja_id,saldo_disponivel,total_creditado,total_usado) "
        . "VALUES (:customer,:store,'50.00','50.00','0.00')"
    );
    $balance->execute([':customer' => $customerId, ':store' => $fixture['storeId']]);

    $transactions = new StoreTransactionService($db);
    $read = new StoreReadService($db);
    $customers = new StoreCustomerService($db);
    $management = new StoreManagementService($db);
    $salePayload = [
        'customerId' => $customerId,
        'grossAmountCents' => 10000,
        'balanceUsedCents' => 2000,
        'code' => strtoupper(substr($runId, -12)) . '-A',
        'description' => 'Fixture atômica ' . $runId,
        'occurredAt' => date(DATE_ATOM),
    ];

    $sale = $transactions->create($fixture['storeId'], $fixture['ownerId'], $salePayload, $runId . ':sale-a');
    expectTrue($sale['status'] === 'approved', 'A venda não foi aprovada imediatamente.');
    expectTrue($sale['cashbackGrantedCents'] === 600, 'O cashback não usou 7,5% do valor efetivamente pago.');
    expectTrue($sale['customerBalanceCents'] === 3600, 'O saldo final da venda parcial está incorreto.');

    $stored = $db->prepare(
        'SELECT status,financial_model,valor_cashback,valor_cliente,valor_admin,valor_loja,cashback_credited_at '
        . 'FROM transacoes_cashback WHERE id=:id'
    );
    $stored->execute([':id' => $sale['id']]);
    $storedSale = $stored->fetch(PDO::FETCH_ASSOC);
    expectTrue($storedSale['status'] === 'aprovado', 'O banco não persistiu o status aprovado.');
    expectTrue($storedSale['financial_model'] === 'subscription_cashback', 'O modelo financeiro novo não foi persistido.');
    expectTrue((float) $storedSale['valor_cashback'] === 6.0 && (float) $storedSale['valor_cliente'] === 6.0, 'O crédito do cliente está incorreto.');
    expectTrue((float) $storedSale['valor_admin'] === 0.0 && (float) $storedSale['valor_loja'] === 0.0, 'Uma comissão foi criada na venda nova.');
    expectTrue(!empty($storedSale['cashback_credited_at']), 'O crédito não foi marcado como concluído.');
    expectTrue((int) scalar($db, 'SELECT COUNT(*) FROM cashback_movimentacoes WHERE loja_id=:store', [':store' => $fixture['storeId']]) === 2, 'Débito e crédito não foram registrados atomicamente.');

    $replay = $transactions->create($fixture['storeId'], $fixture['ownerId'], $salePayload, $runId . ':sale-a');
    expectTrue($replay['id'] === $sale['id'] && $replay['replayed'] === true, 'A repetição idempotente duplicou ou não recuperou o resultado.');
    expectTrue((int) scalar($db, 'SELECT COUNT(*) FROM transacoes_cashback WHERE loja_id=:store', [':store' => $fixture['storeId']]) === 1, 'A venda idempotente foi duplicada.');

    try {
        $changedPayload = $salePayload;
        $changedPayload['grossAmountCents'] = 11000;
        $transactions->create($fixture['storeId'], $fixture['ownerId'], $changedPayload, $runId . ':sale-a');
        throw new RuntimeException('A chave idempotente reutilizada com outro conteúdo foi aceita.');
    } catch (StoreApiException $exception) {
        expectTrue($exception->httpStatus === 409, 'O conflito de idempotência não retornou 409.');
    }

    try {
        $transactions->create($fixture['storeId'], $fixture['ownerId'], [
            ...$salePayload,
            'balanceUsedCents' => 999999,
            'code' => strtoupper(substr($runId, -12)) . '-B',
        ], $runId . ':insufficient');
        throw new RuntimeException('Saldo insuficiente foi aceito.');
    } catch (StoreApiException $exception) {
        expectTrue($exception->httpStatus === 422, 'Saldo insuficiente não retornou erro de validação.');
    }
    expectTrue((int) scalar($db, 'SELECT COUNT(*) FROM transacoes_cashback WHERE loja_id=:store', [':store' => $fixture['storeId']]) === 1, 'Falha de saldo deixou uma venda parcial no banco.');

    $db->prepare("UPDATE lojas SET cashback_ativo=0 WHERE id=:store")->execute([':store' => $fixture['storeId']]);
    try {
        $transactions->create($fixture['storeId'], $fixture['ownerId'], [
            ...$salePayload,
            'balanceUsedCents' => 0,
            'code' => strtoupper(substr($runId, -12)) . '-C',
        ], $runId . ':disabled');
        throw new RuntimeException('Venda com cashback desativado foi aceita.');
    } catch (StoreApiException $exception) {
        expectTrue($exception->httpStatus === 422, 'Cashback desativado não retornou erro de validação.');
    } finally {
        $db->prepare("UPDATE lojas SET cashback_ativo=1 WHERE id=:store")->execute([':store' => $fixture['storeId']]);
    }

    $db->prepare("UPDATE cashback_saldos SET saldo_disponivel='200.00' WHERE usuario_id=:customer AND loja_id=:store")
        ->execute([':customer' => $customerId, ':store' => $fixture['storeId']]);
    $fullBalance = $transactions->create($fixture['storeId'], $fixture['ownerId'], [
        ...$salePayload,
        'grossAmountCents' => 10000,
        'balanceUsedCents' => 10000,
        'code' => strtoupper(substr($runId, -12)) . '-D',
    ], $runId . ':full-balance');
    expectTrue($fullBalance['paidAmountCents'] === 0 && $fullBalance['cashbackGrantedCents'] === 0, 'Uso integral de saldo gerou cashback ou valor pago.');
    expectTrue($fullBalance['customerBalanceCents'] === 10000, 'Saldo integral não foi debitado corretamente.');

    expectTrue((int) scalar($db, 'SELECT COUNT(*) FROM pagamentos_comissao WHERE loja_id=:store', [':store' => $fixture['storeId']]) === 0, 'A venda criou pagamento de comissão.');
    expectTrue((int) scalar($db, 'SELECT COUNT(*) FROM store_balance_payments WHERE loja_id=:store', [':store' => $fixture['storeId']]) === 0, 'O uso de saldo criou repasse.');

    $visitor = $customers->createVisitor($fixture['storeId'], 'Visitante ' . substr($runId, -8), '11977776666');
    expectTrue($visitor['customer']['type'] === 'visitor', 'O visitante não foi criado no contrato novo.');
    $visitorSearch = $customers->search($fixture['storeId'], '11977776666');
    expectTrue($visitorSearch['customer']['id'] === $visitor['customer']['id'], 'O visitante criado não ficou pesquisável.');

    $employee = $management->createEmployee($fixture['storeId'], true, [
        'name' => 'Gerente ' . substr($runId, -8),
        'email' => $runId . '.employee@klubecash.test',
        'phone' => '11966665555',
        'subtype' => 'gerente',
        'password' => 'Funcionario123!',
    ]);
    $legacySession = $db->prepare(
        'INSERT INTO sessoes (id,usuario_id,data_inicio,data_expiracao,ip,user_agent) '
        . 'VALUES (:id,:user,NOW(),DATE_ADD(NOW(),INTERVAL 1 HOUR),\'127.0.0.1\',\'integration-test\')'
    );
    $legacySession->execute([':id' => $runId . ':legacy-session', ':user' => $employee['id']]);
    $appSession = $db->prepare(
        'INSERT INTO app_sessions (id,user_id,payload,last_activity) VALUES (:id,:user,:payload,:activity)'
    );
    $appSession->execute([
        ':id' => $runId . ':app-session',
        ':user' => $employee['id'],
        ':payload' => 'employee-session-fixture',
        ':activity' => time(),
    ]);
    $management->updateEmployee($fixture['storeId'], true, (int) $employee['id'], [
        'name' => 'Financeiro ' . substr($runId, -8),
        'email' => $runId . '.employee@klubecash.test',
        'phone' => '11966665555',
        'subtype' => 'financeiro',
        'password' => '',
    ]);
    expectTrue((int) scalar($db, 'SELECT COUNT(*) FROM sessoes WHERE usuario_id=:id', [':id' => $employee['id']]) === 0, 'A sessão legada do funcionário não foi revogada após editar a permissão.');
    expectTrue((int) scalar($db, 'SELECT COUNT(*) FROM app_sessions WHERE user_id=:id', [':id' => $employee['id']]) === 0, 'A sessão da aplicação não foi revogada após editar a permissão.');
    $employeeList = $management->employees($fixture['storeId'], [], 1);
    expectTrue($employeeList['summary']['financial'] === 1, 'Cadastro ou edição de funcionário falhou.');
    $management->deactivateEmployee($fixture['storeId'], (int) $employee['id']);
    expectTrue((string) scalar($db, 'SELECT status FROM usuarios WHERE id=:id', [':id' => $employee['id']]) === 'inativo', 'Desativação de funcionário falhou.');

    $permissionResults = [];
    foreach ([
        ['type' => 'loja', 'subtype' => null, 'expected' => true],
        ['type' => 'funcionario', 'subtype' => 'gerente', 'expected' => true],
        ['type' => 'funcionario', 'subtype' => 'financeiro', 'expected' => false],
        ['type' => 'funcionario', 'subtype' => 'vendedor', 'expected' => false],
    ] as $role) {
        $_SESSION = [
            'user_id' => $fixture['ownerId'],
            'user_type' => $role['type'],
            'store_id' => $fixture['storeId'],
            'loja_vinculada_id' => $fixture['storeId'],
            'employee_subtype' => $role['subtype'],
            'subtipo_funcionario' => $role['subtype'],
        ];
        $permissionResults[] = AuthController::canManageEmployees() === $role['expected'];
    }
    $_SESSION = [];
    expectTrue(!in_array(false, $permissionResults, true), 'A matriz de permissões de funcionários está incorreta.');

    $management->updateContact($fixture['storeId'], [
        'phone' => '11955554444',
        'website' => 'https://example.test',
        'description' => 'Perfil ' . $runId,
    ]);
    $management->updateAddress($fixture['storeId'], [
        'postalCode' => '01001000', 'street' => 'Rua Fixture', 'number' => '10',
        'complement' => '', 'neighborhood' => 'Centro', 'city' => 'São Paulo', 'state' => 'SP',
    ]);
    $profile = $read->profile($fixture['storeId']);
    expectTrue($profile['company']['phone'] === '11955554444' && $profile['address']['postalCode'] === '01001000', 'Perfil ou endereço não foi persistido.');
    $management->changePassword($fixture['ownerId'], 'SenhaInicial123!', 'SenhaNova123!', 'SenhaNova123!');
    expectTrue(password_verify('SenhaNova123!', (string) scalar($db, 'SELECT senha_hash FROM usuarios WHERE id=:id', [':id' => $fixture['ownerId']])), 'Troca de senha falhou.');

    $planCode = (string) scalar($db, "SELECT codigo FROM planos WHERE ativo=1 AND codigo IS NOT NULL AND codigo<>'' ORDER BY id LIMIT 1");
    expectTrue($planCode !== '', 'Nenhum código de plano está disponível para o teste.');
    $redeemed = $management->redeemPlan($fixture['storeId'], $planCode);
    expectTrue($redeemed['status'] === 'active', 'O resgate de plano por código falhou.');

    $dashboard = $read->dashboard($fixture['storeId']);
    expectTrue($dashboard['summary']['salesCount'] === 2, 'O dashboard não refletiu as vendas reais.');
    $history = $read->transactions($fixture['storeId'], [], 1);
    expectTrue($history['pagination']['totalItems'] === 2, 'O histórico não refletiu as vendas reais.');
    expectTrue((int) scalar($db, 'SELECT COUNT(*) FROM transacoes_cashback WHERE loja_id=34') === $store34Before, 'A loja 34 foi alterada durante os testes.');

    echo json_encode([
        'status' => 'success',
        'testRunId' => $runId,
        'checks' => [
            'atomicSale', 'customerCashbackOnly', 'partialBalance', 'fullBalance',
            'insufficientBalanceRollback', 'cashbackDisabled', 'idempotencyReplay',
            'idempotencyConflict', 'noCommissionPayments', 'visitor', 'employees',
            'employeePermissions', 'profile', 'address', 'password', 'sessionRevocation', 'planRedemption', 'dashboard', 'history',
            'storeIsolation',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} finally {
    cleanupFixture($db, $fixture);
    $cleaned = true;
    expectTrue((int) scalar($db, 'SELECT COUNT(*) FROM transacoes_cashback WHERE loja_id=34') === $store34Before, 'A contagem da loja 34 mudou após a limpeza.');
    expectTrue((int) scalar($db, 'SELECT COUNT(*) FROM lojas WHERE id=:store', [':store' => $fixture['storeId']]) === 0, 'A fixture da loja não foi removida.');
}
