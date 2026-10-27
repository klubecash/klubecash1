<?php
declare(strict_types=1);

// Klube Pass inside KlubeCash, against a throwaway database on a loopback MySQL.
// Never boots the app or reads .env. Covers the money rules 1, 2, 3, 4, 7 and 8.
//   GIFTBACK_TEST_DSN=mysql:host=127.0.0.1;port=3399 php tests/php/pass_integration_integration.php
$dsn = (string) getenv('GIFTBACK_TEST_DSN');
if (!preg_match('/^mysql:host=127\.0\.0\.1;port=\d+$/D', $dsn)) {
    fwrite(STDERR, "Required: GIFTBACK_TEST_DSN=mysql:host=127.0.0.1;port=<isolated test server port>\n");
    exit(2);
}
require_once __DIR__ . '/../../app/Core/RequestContext.php';
require_once __DIR__ . '/../../services/Giftback/GiftbackSchema.php';
require_once __DIR__ . '/../../services/Giftback/GiftbackLedger.php';
require_once __DIR__ . '/../../services/Partner/PassIntegrationSchema.php';
require_once __DIR__ . '/../../services/Partner/PartnerKeyService.php';
require_once __DIR__ . '/../../services/Partner/PartnerIdempotencyService.php';
require_once __DIR__ . '/../../services/Partner/PassPartnerService.php';
require_once __DIR__ . '/../../services/Partner/PassCreditNoticeService.php';
require_once __DIR__ . '/../../services/WhatsApp/WahaConfig.php';
require_once __DIR__ . '/../../services/WhatsApp/WahaException.php';
require_once __DIR__ . '/../../services/WhatsApp/WahaHttpClient.php';
require_once __DIR__ . '/../../services/WhatsApp/WahaService.php';
foreach (['StoreApiException', 'StoreMoney', 'StoreIdempotencyService', 'StoreNetworkAccess', 'StoreTransactionService'] as $class) { require_once __DIR__ . '/../../services/store/' . $class . '.php'; }
foreach (['AdminApiException', 'AdminMoney', 'AdminAuditService', 'AdminIdempotencyService', 'AdminMutationService'] as $class) { require_once __DIR__ . '/../../services/admin/' . $class . '.php'; }
require_once __DIR__ . '/../../services/billing/BillingFeatureFlags.php';

use App\Services\Giftback\GiftbackException;
use App\Services\Giftback\GiftbackSchema;
use App\Services\Partner\PartnerApiException;
use App\Services\Partner\PartnerIdempotencyService;
use App\Services\Partner\PartnerKeyService;
use App\Services\Partner\PassCreditNoticeService;
use App\Services\Partner\PassIntegrationSchema;
use App\Services\Partner\PassPartnerService;
use App\Services\Store\StoreTransactionService;
use App\Services\WhatsApp\WahaConfig;
use App\Services\WhatsApp\WahaHttpClient;
use App\Services\WhatsApp\WahaService;

$user = getenv('GIFTBACK_TEST_USER') ?: 'root';
$password = getenv('GIFTBACK_TEST_PASSWORD') ?: '';
$db = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
const PEPPER = 'test-only-pepper-0123456789abcdef0123456789';

// Rule 4 worker: one debit in its own process and connection.
if (($argv[1] ?? '') === '--race') {
    if (!preg_match('/^pass_test_[a-f0-9]+$/D', $argv[2] ?? '')) { exit(2); }
    $db->exec('USE ' . $argv[2]);
    try {
        (new PassPartnerService($db))->debit(['id' => (int) $argv[4], 'dailyDebitLimitCents' => 0, 'dailyCreditLimitCents' => 0],
            ['klubecash_user_id' => 5, 'organizer_id' => 'org-show-0001', 'amount_cents' => 5000, 'pass_ref' => $argv[3]]);
        echo 'SUCCESS';
    } catch (GiftbackException $error) { echo 'REJECTED:' . $error->httpStatus; }
    catch (PartnerApiException $error) { echo 'REJECTED:' . $error->httpStatus; }
    exit;
}

$check = static function (bool $condition, string $label): void {
    if (!$condition) { throw new RuntimeException('FALHOU: ' . $label); }
    echo "OK {$label}\n";
};
$fails = static function (callable $action, int $status, string $label) use ($check): void {
    try { $action(); } catch (PartnerApiException|GiftbackException|\App\Services\Store\StoreApiException|\App\Services\Admin\AdminApiException $error) {
        $check($error->httpStatus === $status, $label . ' (' . $error->httpStatus . ': ' . $error->getMessage() . ')'); return;
    }
    throw new RuntimeException('FALHOU (sem erro): ' . $label);
};
$scalar = static fn (string $sql): mixed => $db->query($sql)->fetchColumn();
$cents = static fn (string $sql): int => (int) round((float) $db->query($sql)->fetchColumn() * 100);

$name = 'pass_test_' . bin2hex(random_bytes(6));
$db->exec('CREATE DATABASE ' . $name . ' CHARACTER SET utf8mb4');
$db->exec('USE ' . $name);
echo "Isolated database: {$name}\n";
try {
    $db->exec("CREATE TABLE usuarios(id INT PRIMARY KEY,nome VARCHAR(160),email VARCHAR(160),telefone VARCHAR(30),tipo VARCHAR(30),status VARCHAR(30),tipo_cliente VARCHAR(30) DEFAULT 'completo') ENGINE=InnoDB;
        CREATE TABLE lojas(id INT PRIMARY KEY,nome_fantasia VARCHAR(160),razao_social VARCHAR(160) DEFAULT 'Teste',email VARCHAR(160) DEFAULT 'loja@example.test',
            telefone VARCHAR(30) DEFAULT '',categoria VARCHAR(50) DEFAULT '',descricao TEXT,website VARCHAR(160) DEFAULT '',status VARCHAR(30) DEFAULT 'aprovado',
            porcentagem_cliente DECIMAL(5,2) DEFAULT 5,porcentagem_admin DECIMAL(5,2) DEFAULT 0,porcentagem_cashback DECIMAL(5,2) DEFAULT 5,cashback_ativo INT DEFAULT 1,
            data_config_cashback DATETIME NULL,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB;
        CREATE TABLE cashback_saldos(id INT AUTO_INCREMENT PRIMARY KEY,usuario_id INT,loja_id INT,saldo_disponivel DECIMAL(10,2) DEFAULT 0,total_creditado DECIMAL(10,2) DEFAULT 0,total_usado DECIMAL(10,2) DEFAULT 0,ultima_atualizacao DATETIME NULL,UNIQUE(usuario_id,loja_id)) ENGINE=InnoDB;
        CREATE TABLE cashback_movimentacoes(id INT AUTO_INCREMENT PRIMARY KEY,usuario_id INT,loja_id INT,criado_por INT NULL,tipo_operacao ENUM('credito','uso','estorno'),valor DECIMAL(10,2),saldo_anterior DECIMAL(10,2),saldo_atual DECIMAL(10,2),descricao VARCHAR(255),transacao_origem_id INT NULL,transacao_uso_id INT NULL,data_operacao DATETIME NULL,pagamento_id INT NULL,redemption_store_id INT NULL,network_id_snapshot INT NULL) ENGINE=InnoDB;
        CREATE TABLE transacoes_cashback(id INT AUTO_INCREMENT PRIMARY KEY,usuario_id INT,loja_id INT,criado_por INT,vendedor_id INT NULL,network_id_snapshot INT NULL,vendedor_nome_snapshot VARCHAR(160) NULL,
            registrado_por_nome_snapshot VARCHAR(160) NULL,source_channel VARCHAR(32) NULL,valor_total DECIMAL(10,2),valor_cashback DECIMAL(10,2),valor_cliente DECIMAL(10,2),valor_admin DECIMAL(10,2),valor_loja DECIMAL(10,2),
            codigo_transacao VARCHAR(50),descricao VARCHAR(500),data_transacao DATETIME,status VARCHAR(30),financial_model VARCHAR(32),cashback_credited_at DATETIME NULL,UNIQUE(loja_id,codigo_transacao)) ENGINE=InnoDB;
        CREATE TABLE transacoes_saldo_usado(id INT AUTO_INCREMENT PRIMARY KEY,transacao_id INT,usuario_id INT,loja_id INT,valor_usado DECIMAL(10,2)) ENGINE=InnoDB;
        CREATE TABLE store_balance_payments(id INT AUTO_INCREMENT PRIMARY KEY,loja_id INT,valor_total DECIMAL(10,2),metodo_pagamento VARCHAR(50),observacao TEXT,status VARCHAR(30),data_criacao DATETIME) ENGINE=InnoDB;
        CREATE TABLE store_idempotency_keys(id INT AUTO_INCREMENT PRIMARY KEY,scope VARCHAR(80),loja_id INT,usuario_id INT,idempotency_key VARCHAR(128),request_hash CHAR(64),status VARCHAR(20),response_json LONGTEXT,expires_at DATETIME,updated_at DATETIME,UNIQUE(scope,loja_id,idempotency_key)) ENGINE=InnoDB;
        CREATE TABLE configuracoes_saldo(id INT PRIMARY KEY,permitir_uso_saldo INT,valor_minimo_uso DECIMAL(10,2),percentual_maximo_uso INT);
        INSERT INTO configuracoes_saldo VALUES(1,1,1,100);
        CREATE TABLE store_event_outbox(id INT AUTO_INCREMENT PRIMARY KEY,event_type VARCHAR(80),aggregate_id INT,loja_id INT,payload_json LONGTEXT,status VARCHAR(30),UNIQUE(event_type,aggregate_id)) ENGINE=InnoDB;
        CREATE TABLE store_networks(id INT PRIMARY KEY,name VARCHAR(160),status VARCHAR(20),version INT,created_by INT);
        CREATE TABLE store_network_memberships(store_id INT PRIMARY KEY,network_id INT,status VARCHAR(20));
        CREATE TABLE store_network_managers(network_id INT,user_id INT,PRIMARY KEY(network_id,user_id));
        CREATE TABLE store_user_memberships(user_id INT,store_id INT,role VARCHAR(30),status VARCHAR(20),PRIMARY KEY(user_id,store_id));
        CREATE TABLE admin_audit_logs(id INT AUTO_INCREMENT PRIMARY KEY,actor_id INT,action VARCHAR(100),entity_type VARCHAR(80),entity_id VARCHAR(100),result VARCHAR(20),before_json JSON,after_json JSON,request_id VARCHAR(64),ip_hash CHAR(64) NULL) ENGINE=InnoDB;
        CREATE TABLE admin_idempotency_keys(id INT AUTO_INCREMENT PRIMARY KEY,actor_id INT,scope VARCHAR(80),idempotency_key VARCHAR(128),request_hash CHAR(64),status VARCHAR(20),response_json LONGTEXT,expires_at DATETIME,UNIQUE(actor_id,scope,idempotency_key)) ENGINE=InnoDB;
        CREATE TABLE email_queue(id INT AUTO_INCREMENT PRIMARY KEY,to_email VARCHAR(160),to_name VARCHAR(160),subject VARCHAR(255),message TEXT,status VARCHAR(20)) ENGINE=InnoDB;
        CREATE TABLE api_keys(id INT AUTO_INCREMENT PRIMARY KEY,key_prefix VARCHAR(10),permissions TEXT,is_active TINYINT(1));
        INSERT INTO api_keys(key_prefix,permissions,is_active) VALUES('kc_live','[\"*\"]',1),('kc_ok','[\"balance\"]',1)");
    $people = [];
    for ($id = 1; $id <= 9; $id++) { $people[] = "({$id},'Cliente Ficticio {$id}','cliente{$id}@example.test','(11) 90000-000{$id}','cliente','ativo','completo')"; }
    $db->exec('INSERT INTO usuarios(id,nome,email,telefone,tipo,status,tipo_cliente) VALUES' . implode(',', $people)
        . ",(20,'Cliente Inativo','inativo@example.test','','cliente','inativo','completo'),(10,'Dono Ficticio','dono@example.test','','loja','ativo','completo'),(90,'Admin Ficticio','admin@example.test','','admin','ativo','completo')");
    $db->exec("INSERT INTO lojas(id,nome_fantasia,cashback_ativo) VALUES(1,'Promotor Show',0),(2,'Promotor Sem Giftback',1),(3,'Loja Comum',1),(4,'Loja Comum Desligada',0);
        INSERT INTO store_user_memberships VALUES(10,1,'titular','active'),(10,2,'titular','active'),(10,3,'titular','active'),(10,4,'titular','active')");
    GiftbackSchema::migrate($db, true);

    // Additive, repeatable migration through the real runner (config/database.php reads these).
    putenv('DB_HOST=127.0.0.1'); putenv('DB_PORT=' . (int) substr(strrchr($dsn, '='), 1));
    putenv('DB_DATABASE=' . $name); putenv('DB_USERNAME=' . $user); putenv('DB_PASSWORD=' . $password); putenv('DB_SSL_MODE=disable');
    $runMigration = static function (array $flags): array {
        $argv = ['migration', ...$flags];
        ob_start();
        include __DIR__ . '/../../database/migrations/run_pass_integration_migration.php';
        return json_decode((string) ob_get_clean(), true);
    };
    $dry = $runMigration([]);
    $check($dry['mode'] === 'dry-run' && in_array('lojas.tipo_parceiro', $dry['changes'], true) && in_array('partner_api_keys', $dry['changes'], true), 'migração simulada lista as mudanças');
    $check((int) $dry['legacyWildcardKeys'] === 1, 'migração encontra a chave antiga com curinga');
    $check((int) $scalar("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='lojas' AND COLUMN_NAME='tipo_parceiro'") === 0, 'simulação não grava');
    $applied = $runMigration(['--apply']);
    $check($applied['mode'] === 'applied' && count($applied['changes']) === count($dry['changes']), 'migração aplicada');
    $check((int) $scalar("SELECT is_active FROM api_keys WHERE key_prefix='kc_live'") === 1, 'curinga antigo só sai quando pedido');
    $again = $runMigration(['--apply', '--deactivate-legacy-wildcard-keys']);
    $check($again['changes'] === ['api_keys.deactivate_wildcard'], 'migração repetida não muda o schema');
    $check((int) $scalar("SELECT is_active FROM api_keys WHERE key_prefix='kc_live'") === 0 && (int) $scalar("SELECT is_active FROM api_keys WHERE key_prefix='kc_ok'") === 1, 'kc_live desativada, outras chaves intactas');
    $type = (string) $scalar("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cashback_movimentacoes' AND COLUMN_NAME='tipo_operacao'");
    $check(str_contains($type, "'revogacao'") && str_contains($type, "'credito_sobra_evento'") && str_contains($type, "'estorno_sobra_evento'"), 'enum mantém os tipos do giftback e ganha os do Pass');
    $check(PassIntegrationSchema::migrate($db)['changes'] === [], 'nada pendente depois de aplicar');

    $db->exec("UPDATE lojas SET tipo_parceiro='promotor_eventos',pass_organizer_id='org-show-0001',giftback_sobre_dinheiro=1 WHERE id=1;
        UPDATE lojas SET tipo_parceiro='promotor_eventos',pass_organizer_id='org-plain-0002',giftback_sobre_dinheiro=0 WHERE id=2;
        UPDATE lojas SET pass_organizer_id='org-common-0003' WHERE id=3");

    // Keys (rule 8).
    $keys = new PartnerKeyService($db, PEPPER);
    $full = $keys->issue('Klube Pass', PartnerKeyService::PERMISSIONS, 1000, 0, 0, null, 'teste');
    $noCredit = $keys->issue('Pass só leitura e débito', ['pass:balance', 'pass:debit'], 1000, 0, 0, null, 'teste');
    $capped = $keys->issue('Pass com teto', ['pass:debit'], 1000, 1500, 0, null, 'teste');
    $fullKey = $keys->authenticate('Bearer ' . $full['key']);
    $check(count($fullKey['permissions']) === 4 && array_diff(PartnerKeyService::PERMISSIONS, $fullKey['permissions']) === [], 'chave autentica com as 4 permissões');
    $check(strlen((string) $scalar("SELECT secret_hash FROM partner_api_keys WHERE id={$full['id']}")) === 64 && !str_contains((string) $scalar("SELECT secret_hash FROM partner_api_keys WHERE id={$full['id']}"), substr($full['key'], -43)), 'só o HMAC do segredo fica no banco');
    $fails(fn () => $keys->authenticate('Bearer ' . substr($full['key'], 0, -1) . 'x'), 401, 'segredo errado recusado');
    $fails(fn () => (new PartnerKeyService($db, PEPPER . 'outro'))->authenticate('Bearer ' . $full['key']), 401, 'pepper diferente recusa');
    $noCreditKey = $keys->authenticate('Bearer ' . $noCredit['key']);
    $fails(fn () => PartnerKeyService::requirePermission($noCreditKey, 'pass:credit'), 403, 'regra 8: chave sem pass:credit recebe 403 ao creditar');
    $db->exec("UPDATE partner_api_keys SET permissions='[\"*\"]' WHERE id={$noCredit['id']}");
    $wild = $keys->authenticate('Bearer ' . $noCredit['key']);
    $fails(fn () => PartnerKeyService::requirePermission($wild, 'pass:debit'), 403, 'regra 8: curinga gravado à mão não vale');
    $db->exec("UPDATE partner_api_keys SET is_active=0 WHERE id={$noCredit['id']}");
    $fails(fn () => $keys->authenticate('Bearer ' . $noCredit['key']), 401, 'chave revogada recusada');
    $db->exec("UPDATE partner_api_keys SET daily_request_limit=2 WHERE id={$capped['id']}");
    $cappedKey = $keys->authenticate('Bearer ' . $capped['key']);
    $keys->countRequest($cappedKey, '2026-10-27'); $keys->countRequest($cappedKey, '2026-10-27');
    $fails(fn () => $keys->countRequest($cappedKey, '2026-10-27'), 429, 'limite diário de requisições da chave');

    $pass = new PassPartnerService($db);
    $leftover = static fn (int $userId, int $amount, string $ref, ?string $validUntil = null, string $organizer = 'org-show-0001') => $pass->credit($fullKey,
        ['organizer_id' => $organizer, 'event_name' => 'Show Ficticio', 'items' => [['klubecash_user_id' => $userId, 'amount_cents' => $amount, 'pass_ref' => $ref, 'valid_until' => $validUntil]]]);
    $grants = static fn (int $userId): int => (int) $db->query("SELECT COUNT(*) FROM cashback_creditos WHERE usuario_id={$userId} AND kind='grant'")->fetchColumn();

    // Lookups.
    $fails(fn () => $pass->balance(['klubecash_user_id' => '1', 'organizer_id' => 'org-missing-9999']), 404, 'organizador sem loja ligada');
    $fails(fn () => $pass->balance(['klubecash_user_id' => '1', 'organizer_id' => 'org-common-0003']), 409, 'loja comum não opera pelo Pass');
    $fails(fn () => $pass->balance(['klubecash_user_id' => '20', 'organizer_id' => 'org-show-0001']), 404, 'cliente inativo recusado');
    $rejected = $leftover(20, 1000, 'leftover-inactive-1');
    $check($rejected['rejected'] === 1 && $rejected['items'][0]['error'] === 'customer_not_found', 'lote marca item recusado sem parar o resto');

    // Rule 1: leftover is its own lot, no giftback, and using it creates no reimbursement.
    putenv('PASS_CREDIT_NOTICES_ENABLED=true');
    $first = $leftover(1, 5000, 'leftover-user1-a', '2099-12-31');
    putenv('PASS_CREDIT_NOTICES_ENABLED=false');
    $check($first['credited'] === 1 && $first['credited_cents'] === 5000 && $first['items'][0]['balance_cents'] === 5000, 'sobra creditada como saldo na loja promotora');
    $movement = $db->query("SELECT tipo_operacao,origem,pass_ref FROM cashback_movimentacoes WHERE pass_ref='leftover-user1-a'")->fetch(PDO::FETCH_ASSOC);
    $check($movement === ['tipo_operacao' => 'credito_sobra_evento', 'origem' => 'klube_pass', 'pass_ref' => 'leftover-user1-a'], 'movimento com tipo, origem e pass_ref do Pass');
    $check($scalar("SELECT kind FROM cashback_creditos WHERE source_key='pass:leftover-user1-a'") === 'pass_leftover' && $scalar("SELECT DATE(expires_at) FROM cashback_creditos WHERE source_key='pass:leftover-user1-a'") === '2100-01-01', 'lote próprio com a validade pedida');
    $check($grants(1) === 0, 'regra 1: sobra não gera giftback');
    $check((int) $scalar('SELECT COUNT(*) FROM pass_credit_notices') === 2, 'aviso por WhatsApp e e-mail enfileirado com o crédito');
    $balanceBefore = (int) $scalar('SELECT COUNT(*) FROM store_balance_payments');
    $sales = new StoreTransactionService($db);
    putenv('COMMERCIAL_SALES_GATE_ENABLED=0');
    $sale = $sales->create(1, 10, ['customerId' => 1, 'grossAmountCents' => 3000, 'balanceUsedCents' => 3000, 'code' => 'SHOW-0001', 'occurredAt' => date('Y-m-d H:i:s')], 'test-pass-sale-000000001');
    $check($sale['cashbackGrantedCents'] === 0 && $sale['customerBalanceCents'] === 2000, 'regra 1: venda paga com sobra não gera giftback');
    require_once __DIR__ . '/../../models/CashbackBalance.php';
    (new CashbackBalance())->useBalance(1, 1, 5.00, 'Uso pelo caminho antigo', null);
    $check((int) $scalar('SELECT COUNT(*) FROM store_balance_payments') === $balanceBefore, 'regra 1: uso de sobra no promotor não cria reembolso (venda e caminho antigo)');
    (new App\Services\Giftback\GiftbackLedger($db))->credit(9, 3, 1000, 'Giftback comum', null, null, 'common-grant-9');
    (new CashbackBalance())->useBalance(9, 3, 5.00, 'Uso em loja comum', null);
    $check((int) $scalar('SELECT COUNT(*) FROM store_balance_payments') === $balanceBefore + 1, 'loja comum continua gerando reembolso (a guarda não é geral)');

    // Rule 2: R$ 100 = R$ 40 of balance + R$ 60 in money.
    $leftover(2, 4000, 'leftover-user2-a');
    $promoterSale = $sales->create(1, 10, ['customerId' => 2, 'grossAmountCents' => 10000, 'balanceUsedCents' => 4000, 'code' => 'SHOW-0002', 'occurredAt' => date('Y-m-d H:i:s')], 'test-pass-sale-000000002');
    $check($promoterSale['cashbackGrantedCents'] === 300, 'regra 2: venda no promotor (cashback_ativo=0) dá giftback só sobre os R$ 60');
    $leftover(3, 4000, 'leftover-user3-a', null, 'org-plain-0002');
    $plainSale = $sales->create(2, 10, ['customerId' => 3, 'grossAmountCents' => 10000, 'balanceUsedCents' => 4000, 'code' => 'PLAIN-0001', 'occurredAt' => date('Y-m-d H:i:s')], 'test-pass-sale-000000003');
    $check($plainSale['cashbackGrantedCents'] === 0, 'promotor com giftback_sobre_dinheiro=0 não dá giftback');
    $commonSale = $sales->create(3, 10, ['customerId' => 8, 'grossAmountCents' => 10000, 'balanceUsedCents' => 0, 'code' => 'COMMON-0001', 'occurredAt' => date('Y-m-d H:i:s')], 'test-pass-sale-000000004');
    $check($commonSale['cashbackGrantedCents'] === 500, 'loja comum continua igual');
    $fails(fn () => $sales->create(4, 10, ['customerId' => 8, 'grossAmountCents' => 10000, 'balanceUsedCents' => 0, 'code' => 'OFF-0001', 'occurredAt' => date('Y-m-d H:i:s')], 'test-pass-sale-000000005'), 422, 'loja comum com cashback desligado continua sem vender');
    $leftover(4, 4000, 'leftover-user4-a');
    $debit = $pass->debit($fullKey, ['klubecash_user_id' => 4, 'organizer_id' => 'org-show-0001', 'amount_cents' => 4000, 'cash_cents' => 6000, 'pass_ref' => 'topup-user4-0001']);
    $check($debit['giftback_cents'] === 300 && $debit['balance_cents'] === 300, 'regra 2: recarga com R$ 40 de saldo + R$ 60 Pix dá giftback sobre R$ 60');

    // Rule 3: the same operation never doubles.
    $again = $pass->debit($fullKey, ['klubecash_user_id' => 4, 'organizer_id' => 'org-show-0001', 'amount_cents' => 4000, 'cash_cents' => 6000, 'pass_ref' => 'topup-user4-0001']);
    $check($again['replayed'] === true && $again['giftback_cents'] === 300 && $again['balance_cents'] === 300, 'regra 3: débito repetido devolve o mesmo resultado');
    $fails(fn () => $pass->debit($fullKey, ['klubecash_user_id' => 4, 'organizer_id' => 'org-show-0001', 'amount_cents' => 100, 'pass_ref' => 'topup-user4-0001']), 409, 'regra 3: mesmo pass_ref com outro valor');
    $fails(fn () => $pass->debit($fullKey, ['klubecash_user_id' => 2, 'organizer_id' => 'org-show-0001', 'amount_cents' => 100, 'pass_ref' => 'leftover-user1-a']), 409, 'pass_ref de outra operação recusado');
    $repeat = $leftover(1, 5000, 'leftover-user1-a', '2099-12-31');
    $check($repeat['replayed'] === 1 && $repeat['credited_cents'] === 0, 'regra 3: crédito repetido não duplica');
    $check((int) $scalar("SELECT COUNT(*) FROM cashback_movimentacoes WHERE pass_ref LIKE 'topup-user4-0001%'") === 2, 'um débito e um giftback, sem duplicata');
    $idem = new PartnerIdempotencyService($db);
    $payload = ['pass_ref' => 'x'];
    $check($idem->begin($fullKey['id'], 'pass/debit', 'idem-key-0000000001', $payload)['replayed'] === false, 'idempotência: primeira chamada');
    $idem->complete($fullKey['id'], 'pass/debit', 'idem-key-0000000001', 200, ['success' => true, 'data' => ['ok' => 1]]);
    $replay = $idem->begin($fullKey['id'], 'pass/debit', 'idem-key-0000000001', $payload);
    $check($replay['replayed'] === true && $replay['status'] === 200 && $replay['data']['data']['ok'] === 1, 'regra 3: mesma Idempotency-Key devolve a resposta guardada');
    $fails(fn () => $idem->begin($fullKey['id'], 'pass/debit', 'idem-key-0000000001', ['pass_ref' => 'y']), 409, 'mesma Idempotency-Key com outro corpo');
    $fails(fn () => $idem->begin($fullKey['id'], 'pass/debit', 'curta', $payload), 400, 'Idempotency-Key obrigatória');

    // Limits.
    putenv('PASS_USER_DAILY_DEBIT_LIMIT_CENTS=6000');
    $leftover(6, 20000, 'leftover-user6-a');
    $pass->debit($fullKey, ['klubecash_user_id' => 6, 'organizer_id' => 'org-show-0001', 'amount_cents' => 5000, 'pass_ref' => 'topup-user6-0001']);
    $fails(fn () => $pass->debit($fullKey, ['klubecash_user_id' => 6, 'organizer_id' => 'org-show-0001', 'amount_cents' => 2000, 'pass_ref' => 'topup-user6-0002']), 429, 'limite diário por cliente');
    putenv('PASS_USER_DAILY_DEBIT_LIMIT_CENTS');
    $fails(fn () => $pass->debit(['id' => $capped['id'], 'dailyDebitLimitCents' => 1500, 'dailyCreditLimitCents' => 0], ['klubecash_user_id' => 6, 'organizer_id' => 'org-show-0001', 'amount_cents' => 2000, 'pass_ref' => 'topup-user6-0003']), 429, 'limite diário de débito da chave');
    $check((int) $scalar("SELECT COUNT(*) FROM cashback_movimentacoes WHERE pass_ref IN ('topup-user6-0002','topup-user6-0003')") === 0, 'débito barrado pelo limite não grava nada');

    // Reversals.
    $undo = $pass->reverse($fullKey, ['pass_ref' => 'topup-user4-0001', 'reason' => 'Recarga cancelada no teste']);
    $check($undo['type'] === 'debit' && $undo['reversed_cents'] === 4000 && $undo['balance_cents'] === 4000, 'estorno de recarga devolve o saldo e revoga o giftback');
    $check($pass->reverse($fullKey, ['pass_ref' => 'topup-user4-0001', 'reason' => 'Recarga cancelada no teste'])['replayed'] === true, 'estorno repetido não duplica');
    $leftover(7, 3000, 'leftover-user7-a');
    $check($pass->reverse($fullKey, ['pass_ref' => 'leftover-user7-a', 'reason' => 'Sobra lançada errada'])['reversed_cents'] === 3000, 'sobra ainda não usada pode ser estornada');
    $check($scalar("SELECT tipo_operacao FROM cashback_movimentacoes WHERE pass_ref='leftover-user7-a:estorno'") === 'estorno_sobra_evento', 'estorno da sobra com tipo próprio');
    $fails(fn () => $pass->reverse($fullKey, ['pass_ref' => 'leftover-user1-a', 'reason' => 'Tentativa sobre sobra usada']), 409, 'sobra já usada pede revisão manual');
    $fails(fn () => $pass->reverse($fullKey, ['pass_ref' => 'nao-existe-0001', 'reason' => 'Operação inexistente']), 404, 'estorno de operação inexistente');

    // Balance read.
    $read = $pass->balance(['klubecash_user_id' => '4', 'organizer_id' => 'org-show-0001']);
    $check($read['balance_cents'] === 4000 && $read['giftback_on_cash'] === true && $read['giftback_percentage'] === 5.0, 'consulta de saldo do cliente no promotor');

    // Rule 4: two debits at the same time on one wallet never overdraw.
    $leftover(5, 7000, 'leftover-user5-a');
    $workers = [];
    $db->beginTransaction();
    $db->query('SELECT id FROM lojas WHERE id=1 FOR UPDATE');
    foreach (['race-debit-0001', 'race-debit-0002'] as $ref) {
        $pipes = [];
        $workers[] = [proc_open([PHP_BINARY, __FILE__, '--race', $name, $ref, (string) $fullKey['id']], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes), $pipes];
    }
    usleep(300000);
    $db->commit();
    $outcomes = [];
    foreach ($workers as [$process, $pipes]) {
        $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
        if ($err !== '') { throw new RuntimeException($err); }
        $outcomes[] = $out;
    }
    sort($outcomes);
    $check($outcomes === ['REJECTED:409', 'SUCCESS'], 'regra 4: de dois débitos simultâneos só um passa');
    $check($cents('SELECT saldo_disponivel FROM cashback_saldos WHERE usuario_id=5 AND loja_id=1') === 2000, 'regra 4: saldo nunca fica negativo');

    // Rule 7: after a random sequence, the ledger matches every wallet.
    mt_srand(20261027);
    $refs = [];
    for ($i = 0; $i < 40; $i++) {
        $op = mt_rand(1, 3);
        try {
            if ($op === 1) { $leftover(8, mt_rand(100, 3000), 'random-credit-' . $i); }
            elseif ($op === 2) { $pass->debit($fullKey, ['klubecash_user_id' => 8, 'organizer_id' => 'org-show-0001', 'amount_cents' => mt_rand(100, 2000), 'cash_cents' => mt_rand(0, 2000), 'pass_ref' => 'random-debit-' . $i]); $refs[] = 'random-debit-' . $i; }
            elseif ($refs !== []) { $pass->reverse($fullKey, ['pass_ref' => $refs[array_rand($refs)], 'reason' => 'Estorno aleatório']); }
        } catch (GiftbackException|PartnerApiException) { /* insufficient balance or used giftback: expected in a random walk */ }
    }
    GiftbackSchema::assertReconciled($db);
    $signed = $cents("SELECT SUM(CASE WHEN tipo_operacao IN ('credito','credito_sobra_evento','estorno','reversao_expiracao') THEN valor ELSE -valor END) FROM cashback_movimentacoes WHERE usuario_id=8 AND loja_id=1");
    $check($signed === $cents('SELECT saldo_disponivel FROM cashback_saldos WHERE usuario_id=8 AND loja_id=1'), 'regra 7: soma do extrato bate com o saldo');
    $check((int) $scalar('SELECT COUNT(*) FROM cashback_saldos WHERE saldo_disponivel<0') === 0, 'regra 7: nenhuma carteira negativa');

    // Admin can mark a promoter only after the migration, with a unique organizer.
    \App\Core\RequestContext::initialize();
    $admin = new \App\Services\Admin\AdminMutationService($db, 90);
    $fails(fn () => $admin->updateStore(3, ['eventPromoter' => true, 'passOrganizerId' => 'org-show-0001']), 409, 'organizador já ligado a outra loja');
    $fails(fn () => $admin->updateStore(3, ['eventPromoter' => true, 'passOrganizerId' => '']), 422, 'promotor precisa do organizador');
    $marked = $admin->updateStore(4, ['eventPromoter' => true, 'passOrganizerId' => 'org-new-0004', 'giftbackOnCash' => true]);
    $check($marked['eventPromoter'] === true && $marked['passOrganizerId'] === 'org-new-0004', 'admin marca loja como promotora');
    $check((int) $scalar("SELECT COUNT(*) FROM admin_audit_logs WHERE action='store.pass_policy.update'") === 1, 'mudança de promotor auditada');
    $check(!array_key_exists('eventPromoter', $admin->updateStore(3, [])), 'edição sem campos do Pass não mexe neles');

    // Notices: e-mail joins the existing queue, WhatsApp goes through WAHA within the window.
    putenv('PASS_CREDIT_NOTICES_ENABLED=true');
    $db->exec("UPDATE pass_credit_notices SET scheduled_for='2000-01-01 00:00:00'");
    $http = new class implements WahaHttpClient {
        public array $bodies = [];
        public function request(string $method, string $url, array $headers, ?string $body, int $timeoutSeconds): array
        {
            $this->bodies[] = (string) $body;
            return str_contains($url, 'checkNumberStatus') || str_contains($url, 'check-exists')
                ? ['status' => 200, 'body' => '{"numberExists":true,"chatId":"5511900000001@c.us"}']
                : ['status' => 200, 'body' => '{"id":"msg-pass-1"}'];
        }
    };
    $waha = new WahaService(new WahaConfig('https://waha.example.test', 'test-api-key', 'klubecash', 'test-hmac'), $http);
    $night = (new PassCreditNoticeService($db, $waha, new DateTimeImmutable('2026-10-27 23:00:00', new DateTimeZone('America/Sao_Paulo'))))->run();
    $check($night['queuedEmail'] === 1 && $night['outsideWindow'] === true && $night['sent'] === 0, 'à noite só o e-mail vai para a fila');
    $day = (new PassCreditNoticeService($db, $waha, new DateTimeImmutable('2026-10-28 10:00:00', new DateTimeZone('America/Sao_Paulo'))))->run();
    $check($day['sent'] === 1 && (int) $scalar("SELECT COUNT(*) FROM pass_credit_notices WHERE status='sent'") === 2, 'WhatsApp enviado de dia');
    $check(str_contains((string) $scalar('SELECT message FROM email_queue LIMIT 1'), 'R$ 50,00'), 'e-mail com o valor da sobra');
    $check(count(array_filter($http->bodies, static fn (string $body): bool => str_contains($body, 'R$ 50,00'))) === 1, 'mensagem de WhatsApp com o valor da sobra');
    putenv('PASS_CREDIT_NOTICES_ENABLED=false');
    $check((new PassCreditNoticeService($db, $waha))->run() === ['enabled' => false], 'avisos desligados por padrão');

    echo "PASS: Klube Pass no KlubeCash (regras 1, 2, 3, 4, 7 e 8, migração, limites, estornos, admin e avisos)\n";
} finally {
    $db->exec('USE information_schema');
    $db->exec('DROP DATABASE ' . $name);
}
