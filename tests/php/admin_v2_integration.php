<?php

declare(strict_types=1);

/**
 * Integração controlada do Admin Master v2.
 *
 * Todas as fixtures são criadas em uma única transação e revertidas no final.
 * Provedores externos permanecem explicitamente desativados.
 * Execute: php -d extension=openssl -d extension=pdo_mysql tests/php/admin_v2_integration.php
 */

require dirname(__DIR__, 2) . '/bootstrap/app.php';

foreach ([
    'AdminApiException',
    'AdminMoney',
    'AdminAuditService',
    'AdminIdempotencyService',
    'AdminReadService',
    'AdminMutationService',
    'AdminMarketingService',
] as $service) {
    require_once dirname(__DIR__, 2) . '/services/admin/' . $service . '.php';
}

use App\Services\Admin\AdminApiException;
use App\Services\Admin\AdminIdempotencyService;
use App\Services\Admin\AdminMarketingService;
use App\Services\Admin\AdminMutationService;
use App\Services\Admin\AdminReadService;

putenv('EMAIL_DELIVERY_ENABLED=0');
putenv('WHATSAPP_ENABLED=0');
putenv('PIX_ENABLED=0');
putenv('STRIPE_ENABLED=0');

$db = Database::getConnection();
$runId = 'admin_v2_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
$rolledBack = false;

function adminExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function adminScalar(PDO $db, string $sql, array $params = []): mixed
{
    $statement = $db->prepare($sql);
    $statement->execute($params);
    return $statement->fetchColumn();
}

/** @param callable():void $callback */
function expectAdminException(callable $callback, int $httpStatus, string $message): void
{
    try {
        $callback();
    } catch (AdminApiException $exception) {
        adminExpect($exception->httpStatus === $httpStatus, $message . ' (status inesperado)');
        return;
    }
    throw new RuntimeException($message . ' (nenhuma exceção)');
}

register_shutdown_function(static function () use ($db, &$rolledBack): void {
    if (!$rolledBack && $db->inTransaction()) {
        $db->rollBack();
    }
});

$adminIdsBefore = $db->query("SELECT id FROM usuarios WHERE tipo='admin' AND status='ativo' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
adminExpect(count($adminIdsBefore) === 2, 'A linha de base deve preservar exatamente os dois administradores ativos.');
$actorId = (int) $adminIdsBefore[0];
$storeId = (int) $db->query("SELECT id FROM lojas WHERE status='aprovado' ORDER BY id LIMIT 1")->fetchColumn();
adminExpect($storeId > 0, 'Nenhuma loja aprovada disponível para o teste de segmentação.');

$db->beginTransaction();
try {
    $read = new AdminReadService($db);
    $mutations = new AdminMutationService($db, $actorId);
    $marketing = new AdminMarketingService($db, $actorId, $read);
    $idempotency = new AdminIdempotencyService($db, $actorId);

    $db->prepare('INSERT INTO admin_test_runs (test_run_id,entity_type,entity_id) VALUES (:run,\'test_run\',:entity)')
        ->execute([':run' => $runId, ':entity' => $runId]);

    $context = $read->context(['user_id' => $actorId, 'user_type' => 'admin']);
    adminExpect($context['financialModel'] === 'subscription_cashback', 'O contexto não informou o modelo financeiro atual.');
    adminExpect(!empty($context['permissions']['manageUsers']), 'A permissão administrativa não foi retornada.');

    $dashboard = $read->dashboard();
    adminExpect(isset($dashboard['summary'], $dashboard['recentTransactions'], $dashboard['pendingStores']), 'O dashboard não respeitou o contrato v2.');
    adminExpect(isset($dashboard['dataState'], $dashboard['generatedAt']), 'O dashboard não informa estado e data de geração.');

    $created = $mutations->createUser([
        'name' => 'Fixture Admin v2 ' . substr($runId, -8),
        'email' => $runId . '@example.test',
        'phone' => '11999990000',
        'type' => 'cliente',
        'customerType' => 'completo',
    ]);
    $fixtureUserId = (int) $created['id'];
    $db->prepare('INSERT INTO admin_test_runs (test_run_id,entity_type,entity_id) VALUES (:run,\'user\',:entity)')
        ->execute([':run' => $runId, ':entity' => (string) $fixtureUserId]);
    adminExpect($created['passwordResetRequired'] === true, 'A criação administrativa deve exigir recuperação de senha.');

    $page = $read->users(['search' => $runId], 1, 20);
    adminExpect($page['pagination']['totalItems'] === 1 && $page['items'][0]['id'] === $fixtureUserId, 'O usuário criado não apareceu na busca paginada.');
    $staleVersion = (string) $page['items'][0]['updatedAt'];
    $db->prepare('UPDATE usuarios SET updated_at=DATE_ADD(updated_at,INTERVAL 2 SECOND) WHERE id=:id')->execute([':id' => $fixtureUserId]);
    expectAdminException(
        static fn () => $mutations->updateUser($fixtureUserId, ['name' => 'Atualização obsoleta', 'updatedAt' => $staleVersion]),
        409,
        'Uma atualização concorrente deveria retornar conflito.'
    );
    $passwordRecovery = $mutations->requestUserPasswordReset($fixtureUserId);
    adminExpect($passwordRecovery['queued'] === true, 'A recuperação administrativa não foi enfileirada.');
    adminExpect((int) adminScalar($db, 'SELECT COUNT(*) FROM recuperacao_senha WHERE usuario_id=:id AND usado=0', [':id' => $fixtureUserId]) === 1, 'O token de recuperação não foi persistido de forma segura.');
    adminExpect((int) adminScalar($db, 'SELECT COUNT(*) FROM email_queue WHERE to_email=:email AND status=\'pending\'', [':email' => $runId . '@example.test']) === 1, 'A mensagem de recuperação não entrou na fila persistente.');
    $mutations->updateUserStatus($fixtureUserId, 'bloqueado');
    adminExpect(adminScalar($db, 'SELECT status FROM usuarios WHERE id=:id', [':id' => $fixtureUserId]) === 'bloqueado', 'Bloqueio de usuário não foi persistido.');

    expectAdminException(
        static fn () => $mutations->updateUserStatus($actorId, 'inativo'),
        403,
        'A autodesativação administrativa deveria ser bloqueada.'
    );
    expectAdminException(
        static fn () => $mutations->createUser(['name' => 'Admin indevido', 'email' => $runId . '.admin@example.test', 'type' => 'admin']),
        403,
        'A promoção/criação de administrador deveria ser bloqueada.'
    );

    $key = $runId . ':idempotency';
    $first = $idempotency->begin('integration_test', $key, ['value' => 1]);
    adminExpect($first['replayed'] === false, 'A primeira execução idempotente foi marcada como replay.');
    $idempotency->complete('integration_test', $key, ['saved' => true]);
    $replay = $idempotency->begin('integration_test', $key, ['value' => 1]);
    adminExpect($replay['replayed'] === true && $replay['data']['saved'] === true, 'O replay não devolveu o resultado anterior.');
    expectAdminException(
        static fn () => $idempotency->begin('integration_test', $key, ['value' => 2]),
        409,
        'Uma chave reutilizada com conteúdo diferente deveria retornar conflito.'
    );

    $retryKey = $runId . ':retry';
    $idempotency->begin('integration_retry', $retryKey, ['value' => 1]);
    $idempotency->fail('integration_retry', $retryKey);
    $retry = $idempotency->begin('integration_retry', $retryKey, ['value' => 1]);
    adminExpect($retry['replayed'] === false, 'Uma tentativa falha não pôde ser retomada com a mesma chave.');

    $template = $marketing->saveTemplate(null, [
        'name' => 'Template ' . $runId,
        'subject' => 'Assunto seguro',
        'html' => '<h1>Teste</h1><script>alert(1)</script><a href="javascript:alert(2)" onclick="alert(3)">Abrir</a>',
        'type' => 'newsletter',
        'active' => true,
    ]);
    $storedHtml = (string) adminScalar($db, 'SELECT conteudo_html FROM email_templates WHERE id=:id', [':id' => $template['id']]);
    adminExpect(!str_contains(strtolower($storedHtml), '<script'), 'O template preservou uma tag script.');
    adminExpect(!str_contains(strtolower($storedHtml), 'onclick='), 'O template preservou um evento inline.');
    adminExpect(!str_contains(strtolower($storedHtml), 'javascript:'), 'O template preservou uma URL javascript.');

    $baselineAudience = $read->audienceCount(['types' => ['funcionario'], 'storeId' => $storeId, 'status' => 'ativo']);
    $employeeInsert = $db->prepare(
        "INSERT INTO usuarios (nome,email,telefone,senha_hash,tipo,status,loja_vinculada_id,subtipo_funcionario) "
        . "VALUES (:name,:email,'',:password,'funcionario','ativo',:store,'funcionario')"
    );
    $employeeInsert->execute([':name' => 'Destinatário válido', ':email' => $runId . '.employee@example.test', ':password' => password_hash('Fixture123!', PASSWORD_DEFAULT), ':store' => $storeId]);
    $employeeInsert->execute([':name' => 'Destinatário inválido', ':email' => $runId . '.invalid-email', ':password' => password_hash('Fixture123!', PASSWORD_DEFAULT), ':store' => $storeId]);
    $audience = $read->audienceCount(['types' => ['funcionario'], 'storeId' => $storeId, 'status' => 'ativo']);
    adminExpect($audience === $baselineAudience + 1, 'A segmentação não excluiu o endereço inválido.');

    $campaign = $marketing->saveCampaign(null, [
        'title' => 'Campanha ' . $runId,
        'subject' => 'Campanha de integração',
        'html' => '<h1>Integração</h1><p>Envio desativado.</p>',
        'audience' => ['types' => ['funcionario'], 'storeId' => $storeId, 'status' => 'ativo'],
    ]);
    adminExpect($campaign['status'] === 'rascunho' && $campaign['recipientCount'] === $audience, 'A campanha não foi salva como rascunho com a prévia correta.');
    adminExpect((int) adminScalar($db, 'SELECT COUNT(*) FROM email_queue WHERE campaign_id=:id', [':id' => $campaign['id']]) === 0, 'Salvar rascunho enfileirou mensagens indevidamente.');
    $testEmail = $marketing->queueTestEmail((int) $campaign['id'], ['email' => $runId . '.preview@example.test']);
    adminExpect(
        adminScalar($db, 'SELECT status FROM email_queue WHERE id=:id', [':id' => $testEmail['queueId']]) === 'pending',
        'O e-mail de teste não entrou na fila persistente.'
    );

    $auditCount = (int) adminScalar($db, 'SELECT COUNT(*) FROM admin_audit_logs WHERE actor_id=:actor', [':actor' => $actorId]);
    adminExpect($auditCount >= 4, 'As mutações sensíveis não produziram auditoria.');
    $auditPayload = (string) adminScalar($db, "SELECT after_json FROM admin_audit_logs WHERE action='user.create' AND entity_id=:id ORDER BY id DESC LIMIT 1", [':id' => (string) $fixtureUserId]);
    adminExpect(str_contains($auditPayload, '[REDACTED]') && !str_contains($auditPayload, $runId . '@example.test'), 'A auditoria armazenou e-mail em texto puro.');

    $finance = $read->finance([], 1, 20);
    foreach ([...$finance['commissionPayments'], ...$finance['balancePayments']] as $financialItem) {
        adminExpect(array_key_exists('reviewRequired', $financialItem), 'A auditoria de integridade financeira não foi informada.');
        adminExpect(array_key_exists('reviewReason', $financialItem), 'O motivo de revisão financeira não foi informado.');
    }
    $reports = $read->reports([]);
    $subscriptions = $read->subscriptions([], 1, 20);
    $plans = $read->plans();
    adminExpect(isset($finance['summary'], $reports['monthly'], $subscriptions['pagination'], $plans['items']), 'Um dos contratos administrativos de leitura está incompleto.');

    echo json_encode([
        'status' => 'success',
        'testRunId' => $runId,
        'checks' => [
            'twoProtectedAdmins', 'context', 'dashboard', 'userCreate', 'userBlock',
            'selfDeactivationProtection', 'adminCreationProtection', 'concurrentUpdateProtection', 'passwordRecoveryQueue', 'idempotencyReplay',
            'idempotencyConflict', 'idempotencyRetry', 'templateSanitization',
            'audienceValidation', 'campaignDraft', 'campaignTestQueue', 'externalDeliveryDisabled',
            'auditRedaction', 'finance', 'financeIntegrityReview', 'reports', 'subscriptions', 'plans',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $rolledBack = true;
    $adminIdsAfter = $db->query("SELECT id FROM usuarios WHERE tipo='admin' AND status='ativo' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    adminExpect($adminIdsAfter === $adminIdsBefore, 'A lista de administradores foi alterada pelo teste.');
    adminExpect((int) adminScalar($db, 'SELECT COUNT(*) FROM admin_test_runs WHERE test_run_id=:run', [':run' => $runId]) === 0, 'As fixtures administrativas não foram revertidas.');
}
