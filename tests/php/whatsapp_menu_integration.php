<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Services\WhatsApp\WahaConfig;
use App\Services\WhatsApp\WahaHttpClient;
use App\Services\WhatsApp\WahaService;
use App\Services\WhatsApp\WhatsAppAuthService;
use App\Services\WhatsApp\WhatsAppMenuConfig;
use App\Services\WhatsApp\WhatsAppMenuService;
use App\Services\WhatsApp\WhatsAppMenuStore;

final class WhatsAppMenuFakeHttp implements WahaHttpClient
{
    /** @var list<array<string,mixed>> */
    public array $requests = [];
    private int $message = 0;

    public function request(string $method, string $url, array $headers, ?string $body, int $timeoutSeconds): array
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body', 'timeoutSeconds');
        if (str_contains($url, '/api/contacts/check-exists')) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            return ['status' => 200, 'body' => json_encode([
                'numberExists' => true,
                'chatId' => (string) ($query['phone'] ?? '') . '@c.us',
            ])];
        }
        if (str_contains($url, '/api/sendText')) {
            $this->message++;
            return ['status' => 200, 'body' => json_encode(['id' => 'fixture-message-' . $this->message])];
        }
        return ['status' => 404, 'body' => '{}'];
    }

    /** @return list<string> */
    public function messages(): array
    {
        $messages = [];
        foreach ($this->requests as $request) {
            if (!str_contains((string) $request['url'], '/api/sendText')) {
                continue;
            }
            $payload = json_decode((string) $request['body'], true);
            if (is_array($payload)) {
                $messages[] = (string) ($payload['text'] ?? '');
            }
        }
        return $messages;
    }
}

function waExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function waScalar(PDO $db, string $sql, array $params = []): mixed
{
    $statement = $db->prepare($sql);
    $statement->execute($params);
    return $statement->fetchColumn();
}

/** @param array<string,mixed> $fixture */
function waCleanup(PDO $db, array $fixture): void
{
    $storeId = (int) ($fixture['storeId'] ?? 0);
    $ownerId = (int) ($fixture['ownerId'] ?? 0);
    $customerId = (int) ($fixture['customerId'] ?? 0);
    $senderKeys = array_values(array_filter($fixture['senderKeys'] ?? []));

    foreach ($senderKeys as $key) {
        foreach (['whatsapp_bot_messages', 'whatsapp_auth_challenges', 'whatsapp_action_audit', 'whatsapp_conversations'] as $table) {
            $column = $table === 'whatsapp_conversations' ? 'sender_key' : 'sender_key';
            $db->prepare("DELETE FROM {$table} WHERE {$column}=:sender")->execute([':sender' => $key]);
        }
    }
    if ($storeId > 0) {
        foreach ([
            'DELETE FROM whatsapp_action_audit WHERE loja_id=:store',
            'DELETE FROM store_whatsapp_deliveries WHERE loja_id=:store',
            'DELETE FROM store_event_outbox WHERE loja_id=:store',
            'DELETE FROM cashback_movimentacoes WHERE loja_id=:store',
            'DELETE FROM transacoes_saldo_usado WHERE loja_id=:store',
            'DELETE FROM transacoes_cashback WHERE loja_id=:store',
            'DELETE FROM store_idempotency_keys WHERE loja_id=:store',
            'DELETE FROM cashback_saldos WHERE loja_id=:store',
            'DELETE FROM lojas_endereco WHERE loja_id=:store',
            'DELETE FROM assinaturas WHERE loja_id=:store',
            'DELETE FROM usuarios WHERE loja_vinculada_id=:store',
            'DELETE FROM usuarios WHERE loja_criadora_id=:store',
            'DELETE FROM lojas WHERE id=:store',
        ] as $sql) {
            $db->prepare($sql)->execute([':store' => $storeId]);
        }
    }
    if ($customerId > 0) {
        $db->prepare('DELETE FROM cashback_saldos WHERE usuario_id=:user')->execute([':user' => $customerId]);
        $db->prepare('DELETE FROM usuarios WHERE id=:user')->execute([':user' => $customerId]);
    }
    if ($ownerId > 0) {
        $db->prepare('DELETE FROM usuarios WHERE id=:user')->execute([':user' => $ownerId]);
    }
}

/** @return array<string,mixed> */
function waEvent(string $phone, string $body): array
{
    return [
        'event' => 'message',
        'session' => 'fixture',
        'payload' => [
            'id' => bin2hex(random_bytes(8)),
            'from' => $phone . '@c.us',
            'chatId' => $phone . '@c.us',
            'fromMe' => false,
            'type' => 'chat',
            'body' => $body,
        ],
    ];
}

/** @return array<string,mixed> */
function waLidEvent(string $phone, string $body): array
{
    $national = str_starts_with($phone, '55') ? substr($phone, 2) : $phone;
    $legacyPhone = strlen($national) === 11
        ? '55' . substr($national, 0, 2) . substr($national, 3)
        : $phone;
    return [
        'event' => 'message',
        'session' => 'fixture',
        'payload' => [
            'id' => bin2hex(random_bytes(8)),
            'from' => '123456789012345@lid',
            'fromMe' => false,
            'type' => 'chat',
            'source' => 'app',
            'body' => $body,
            '_data' => [
                'Info' => [
                    'Sender' => '123456789012345@lid',
                    'SenderAlt' => $legacyPhone . '@s.whatsapp.net',
                ],
            ],
        ],
    ];
}

$db = Database::getConnection();
$runId = 'wa_menu_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
$fixture = ['senderKeys' => []];
$cleaned = false;
$store34Before = (int) waScalar($db, 'SELECT COUNT(*) FROM transacoes_cashback WHERE loja_id=34');

register_shutdown_function(static function () use ($db, &$fixture, &$cleaned): void {
    if (!$cleaned) {
        try { waCleanup($db, $fixture); } catch (Throwable $exception) {
            fwrite(STDERR, 'Falha na limpeza da fixture WhatsApp: ' . get_class($exception) . PHP_EOL);
        }
    }
});

try {
    $ownerPhone = '11999990001';
    $clientPhone = '11988880001';
    $owner = $db->prepare(
        "INSERT INTO usuarios (nome,email,telefone,senha_hash,tipo,status,provider,email_verified) "
        . "VALUES (:name,:email,:phone,:password,'loja','ativo','local',1)"
    );
    $owner->execute([
        ':name' => 'Fixture WhatsApp ' . $runId,
        ':email' => $runId . '.owner@klubecash.test',
        ':phone' => $ownerPhone,
        ':password' => password_hash('Fixture123!', PASSWORD_DEFAULT),
    ]);
    $fixture['ownerId'] = (int) $db->lastInsertId();

    $store = $db->prepare(
        'INSERT INTO lojas (usuario_id,nome_fantasia,razao_social,cnpj,email,telefone,porcentagem_cashback,'
        . 'porcentagem_cliente,porcentagem_admin,cashback_ativo,status) '
        . "VALUES (:owner,:name,:legal,:cnpj,:email,:phone,'7.50','7.50','0.00',1,'aprovado')"
    );
    $store->execute([
        ':owner' => $fixture['ownerId'],
        ':name' => 'Loja WhatsApp ' . $runId,
        ':legal' => 'Fixture WhatsApp ' . $runId,
        ':cnpj' => substr(hash('sha256', $runId), 0, 14),
        ':email' => $runId . '.store@klubecash.test',
        ':phone' => $ownerPhone,
    ]);
    $fixture['storeId'] = (int) $db->lastInsertId();

    $customer = $db->prepare(
        "INSERT INTO usuarios (nome,email,telefone,senha_hash,tipo,tipo_cliente,status,provider,email_verified) "
        . "VALUES (:name,:email,:phone,:password,'cliente','completo','ativo','local',1)"
    );
    $customer->execute([
        ':name' => 'Cliente Integracao WhatsApp',
        ':email' => $runId . '.customer@klubecash.test',
        ':phone' => $clientPhone,
        ':password' => password_hash('Fixture123!', PASSWORD_DEFAULT),
    ]);
    $fixture['customerId'] = (int) $db->lastInsertId();
    $balance = $db->prepare(
        'INSERT INTO cashback_saldos (usuario_id,loja_id,saldo_disponivel,total_creditado,total_usado) '
        . "VALUES (:customer,:store,'50.00','50.00','0.00'),(:customer_second,34,'5.00','5.00','0.00')"
    );
    $balance->execute([
        ':customer' => $fixture['customerId'],
        ':customer_second' => $fixture['customerId'],
        ':store' => $fixture['storeId'],
    ]);
    $visitorAlias = $db->prepare(
        "INSERT INTO usuarios (nome,email,telefone,senha_hash,tipo,tipo_cliente,status,provider,email_verified,loja_criadora_id) "
        . "VALUES (:name,:email,:phone,:password,'cliente','visitante','ativo','local',1,:store)"
    );
    $visitorAlias->execute([
        ':name' => 'Cadastro Antigo Sem Saldo',
        ':email' => $runId . '.alias@klubecash.test',
        ':phone' => substr($clientPhone, 0, 2) . substr($clientPhone, 3),
        ':password' => password_hash('Fixture123!', PASSWORD_DEFAULT),
        ':store' => $fixture['storeId'],
    ]);

    $http = new WhatsAppMenuFakeHttp();
    $waha = new WahaService(new WahaConfig('https://waha.fixture.test', 'fixture-key', 'fixture', 'fixture-hmac'), $http);
    $config = new WhatsAppMenuConfig(true, true, true, str_repeat('w', 40), 'https://www.klubecash.com', '5511999990001');
    $menu = new WhatsAppMenuService($db, $waha, $config);
    $menuStore = new WhatsAppMenuStore($db, $config);
    $clientCanonical = WahaService::normalizePhone($clientPhone);
    $ownerCanonical = WahaService::normalizePhone($ownerPhone);
    $fixture['senderKeys'] = [$config->senderKey($clientCanonical), $config->senderKey($ownerCanonical)];

    $before = count($http->requests);
    $menu->process(900001, waEvent('5511777777777', 'conversa comum'), $runId . ':ordinary');
    waExpect(count($http->requests) === $before, 'Mensagem comum ativou o bot sem /klube.');

    $menu->process(900002, waLidEvent('55' . $clientPhone, '/klube'), $runId . ':menu');
    $menu->processPendingReplies(10, $config->senderKey($clientCanonical), 900002);
    $menu->process(900003, waEvent('55' . $clientPhone, '1'), $runId . ':balance');
    $menu->processPendingReplies(10, $config->senderKey($clientCanonical), 900003);
    $sentMessages = $http->messages();
    $balanceMessage = (string) end($sentMessages);
    waExpect(str_contains($balanceMessage, 'R$ 50,00') && str_contains($balanceMessage, 'R$ 5,00'), 'Saldos separados por loja nao foram exibidos.');
    waExpect(!str_contains($balanceMessage, 'R$ 55,00'), 'Os saldos de lojas diferentes foram somados.');

    $token = $menuStore->createChallenge($config->senderKey($ownerCanonical));
    $auth = new WhatsAppAuthService($db, $waha, $config);
    $authContext = $auth->context($token, $fixture['ownerId'], $fixture['storeId']);
    waExpect($authContext['canAuthorize'] === true, 'Telefone correto nao autorizou o contexto lojista.');
    $authResult = $auth->approve($token, $fixture['ownerId'], $fixture['storeId'], $runId . ':auth');
    waExpect($authResult['authorized'] === true, 'Autorizacao lojista falhou.');

    $menu->process(900006, waEvent('55' . $ownerPhone, '2'), $runId . ':lookup-menu');
    $menu->processPendingReplies(10, $config->senderKey($ownerCanonical), 900006);
    $menu->process(900007, waEvent('55' . $ownerPhone, $clientPhone), $runId . ':lookup-customer');
    $lookupConversation = $menuStore->conversation($config->senderKey($ownerCanonical));
    waExpect(($lookupConversation['state'] ?? '') === 'merchant_menu', 'Consulta do cliente nao retornou ao menu lojista.');
    $menu->processPendingReplies(10, $config->senderKey($ownerCanonical), 900007);
    $lookupMessages = $http->messages();
    $lookupMessage = (string) end($lookupMessages);
    waExpect(str_contains($lookupMessage, 'Cliente encontrado') && str_contains($lookupMessage, 'R$ 50,00'), 'Consulta do cliente nao exibiu o saldo da loja autenticada.');

    $menu->process(900008, waEvent('55' . $ownerPhone, '3'), $runId . ':recent-sales');
    $menu->processPendingReplies(10, $config->senderKey($ownerCanonical), 900008);
    $recentMessages = $http->messages();
    $recentMessage = (string) end($recentMessages);
    waExpect(str_contains($recentMessage, 'Ultimas vendas') && str_contains($recentMessage, 'Nenhuma venda encontrada'), 'Ultimas vendas nao respondeu ao lojista.');

    // O servico real de notificacao da venda fica desabilitado na fixture.
    $originalWahaBase = getenv('WAHA_BASE_URL');
    putenv('WAHA_BASE_URL=');
    $menu->process(900010, waEvent('55' . $ownerPhone, '1'), $runId . ':sale-menu');
    $menu->process(900011, waEvent('55' . $ownerPhone, $clientPhone), $runId . ':sale-customer');
    $menu->process(900012, waEvent('55' . $ownerPhone, '100,00'), $runId . ':sale-gross');
    $menu->process(900013, waEvent('55' . $ownerPhone, '20,00'), $runId . ':sale-balance');
    $conversation = $menuStore->conversation($config->senderKey($ownerCanonical));
    $draft = $config->decrypt((string) $conversation['state_payload']);
    waExpect(($conversation['state'] ?? '') === 'sale_confirm' && !empty($draft['confirmation']), 'Previa da venda nao chegou a confirmacao.');
    $menu->process(900014, waEvent('55' . $ownerPhone, 'CONFIRMAR ' . $draft['confirmation']), $runId . ':sale-confirm');
    $menu->processPendingReplies(50, $config->senderKey($ownerCanonical));
    if ($originalWahaBase !== false) { putenv('WAHA_BASE_URL=' . $originalWahaBase); }

    $transaction = $db->prepare(
        'SELECT id,status,financial_model,valor_total,valor_cliente,valor_admin,valor_loja FROM transacoes_cashback '
        . 'WHERE loja_id=:store AND codigo_transacao=:code LIMIT 1'
    );
    $transaction->execute([':store' => $fixture['storeId'], ':code' => $draft['code']]);
    $sale = $transaction->fetch(PDO::FETCH_ASSOC);
    waExpect((bool) $sale, 'Venda confirmada pelo WhatsApp nao foi persistida.');
    waExpect($sale['status'] === 'aprovado' && $sale['financial_model'] === 'subscription_cashback', 'Venda do WhatsApp nao preservou o modelo financeiro.');
    waExpect((float) $sale['valor_total'] === 100.0 && (float) $sale['valor_cliente'] === 6.0, 'Cashback da venda do WhatsApp foi calculado incorretamente.');
    waExpect((float) $sale['valor_admin'] === 0.0 && (float) $sale['valor_loja'] === 0.0, 'Venda do WhatsApp criou comissao.');
    waExpect((int) waScalar($db, 'SELECT COUNT(*) FROM pagamentos_comissao WHERE loja_id=:store', [':store' => $fixture['storeId']]) === 0, 'Venda do WhatsApp criou pagamento de comissao.');
    waExpect((int) waScalar($db, 'SELECT COUNT(*) FROM whatsapp_action_audit WHERE loja_id=:store AND action=\'sale.create\'', [':store' => $fixture['storeId']]) === 1, 'Venda do WhatsApp nao foi auditada uma unica vez.');

    $menu->process(900020, waEvent('55' . $clientPhone, '/klube'), $runId . ':duplicate-menu');
    $menu->processPendingReplies(10, $config->senderKey($clientCanonical), 900020);
    $menu->process(900021, waEvent('55' . $clientPhone, '1'), $runId . ':duplicate-balance');
    $menu->processPendingReplies(10, $config->senderKey($clientCanonical), 900021);
    $duplicateBalanceMessages = $http->messages();
    $duplicateBalanceMessage = (string) end($duplicateBalanceMessages);
    waExpect(!str_contains($duplicateBalanceMessage, 'mais de uma conta'), 'Alias visitante seguro bloqueou a consulta de saldo.');
    waExpect(str_contains($duplicateBalanceMessage, 'R$ 36,00'), 'Alias visitante nao preservou os saldos da identidade principal.');

    waCleanup($db, $fixture);
    $cleaned = true;
    waExpect((int) waScalar($db, 'SELECT COUNT(*) FROM transacoes_cashback WHERE loja_id=34') === $store34Before, 'A fixture alterou transacoes da loja 34.');
    echo "OK: menu WhatsApp integrado, isolado e sem chamadas externas.\n";
} finally {
    if (!$cleaned) {
        waCleanup($db, $fixture);
        $cleaned = true;
    }
}
