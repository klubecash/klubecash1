<?php

declare(strict_types=1);

// Only a disposable loopback MySQL may run this test. Never use application data.
$dsn = (string) getenv('GIFTBACK_TEST_DSN');
if (!preg_match('/^mysql:host=127\.0\.0\.1;port=\d+$/D', $dsn)) {
    fwrite(STDERR, "Required: GIFTBACK_TEST_DSN=mysql:host=127.0.0.1;port=<isolated port>\n");
    exit(2);
}
require_once __DIR__ . '/../../services/store/StoreApiException.php';
require_once __DIR__ . '/../../services/store/StoreMoney.php';
require_once __DIR__ . '/../../services/store/StoreReadService.php';

use App\Services\Store\StoreReadService;

$db = new PDO($dsn, getenv('GIFTBACK_TEST_USER') ?: 'root', getenv('GIFTBACK_TEST_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$schema = 'store_sales_test_' . bin2hex(random_bytes(5));
$db->exec('CREATE DATABASE ' . $schema . ' CHARACTER SET utf8mb4');
$db->exec('USE ' . $schema);
$check = static function (bool $condition, string $label): void {
    if (!$condition) { throw new RuntimeException($label); }
    echo "OK {$label}\n";
};
try {
    $db->exec("CREATE TABLE usuarios(id INT PRIMARY KEY,nome VARCHAR(160),email VARCHAR(160)) ENGINE=InnoDB;
        CREATE TABLE lojas(id INT PRIMARY KEY,nome_fantasia VARCHAR(160)) ENGINE=InnoDB;
        CREATE TABLE transacoes_cashback(id INT PRIMARY KEY,loja_id INT,usuario_id INT,vendedor_id INT NULL,criado_por INT NULL,
            vendedor_nome_snapshot VARCHAR(160) NULL,registrado_por_nome_snapshot VARCHAR(160) NULL,source_channel VARCHAR(32) NULL,
            codigo_transacao VARCHAR(50),descricao TEXT,valor_total DECIMAL(10,2),valor_cliente DECIMAL(10,2),
            status VARCHAR(30),data_transacao DATETIME,financial_model VARCHAR(40)) ENGINE=InnoDB;
        CREATE TABLE transacoes_saldo_usado(transacao_id INT,valor_usado DECIMAL(10,2)) ENGINE=InnoDB;
        CREATE TABLE store_sale_attribution_events(id BIGINT AUTO_INCREMENT PRIMARY KEY,transaction_id INT,previous_seller_id INT NULL,
            new_seller_id INT,occurred_at DATETIME) ENGINE=InnoDB;
        CREATE TABLE cashback_movimentacoes(id INT PRIMARY KEY,loja_id INT,tipo_operacao VARCHAR(30),valor DECIMAL(10,2),saldo_anterior DECIMAL(10,2),saldo_atual DECIMAL(10,2),
            data_operacao DATETIME,transacao_origem_id INT NULL,transacao_uso_id INT NULL) ENGINE=InnoDB;
        CREATE TABLE admin_audit_logs(id BIGINT AUTO_INCREMENT PRIMARY KEY,entity_type VARCHAR(80),entity_id VARCHAR(100),
            action VARCHAR(100),result VARCHAR(20),created_at DATETIME) ENGINE=InnoDB;
        INSERT INTO usuarios VALUES(1,'Cliente A','a@example.test'),(2,'Cliente B','b@example.test');
        INSERT INTO lojas VALUES(1,'Filial A'),(2,'Filial B');
        INSERT INTO transacoes_cashback VALUES
            (101,1,1,11,10,'Ana','Gestor','manual','A101','Venda com itens',100.00,5.00,'aprovado','2026-10-01 10:00:00','subscription_cashback'),
            (102,2,2,11,11,'Ana','Ana','csv','B102','CSV',50.00,2.50,'aprovado','2026-10-02 10:00:00','subscription_cashback'),
            (103,1,1,11,10,'Ana','Gestor','manual','A103','Cancelada',80.00,0.00,'cancelado','2026-10-03 10:00:00','subscription_cashback'),
            (104,1,2,NULL,NULL,NULL,NULL,NULL,'A104','Legada',20.00,1.00,'aprovado','2026-10-04 10:00:00','commission_legacy');
        INSERT INTO transacoes_saldo_usado VALUES(101,20.00),(102,0.00);
        INSERT INTO cashback_movimentacoes VALUES(1,1,'credito',5.00,0.00,5.00,'2026-10-01 10:01:00',101,NULL);
        INSERT INTO admin_audit_logs(entity_type,entity_id,action,result,created_at) VALUES('transaction','103','transaction.reverse','success','2026-10-03 11:00:00')");
    putenv('DB_HOST=127.0.0.1'); putenv('DB_PORT=' . (int) substr(strrchr($dsn, '='), 1));
    putenv('DB_DATABASE=' . $schema); putenv('DB_USERNAME=' . (getenv('GIFTBACK_TEST_USER') ?: 'root'));
    putenv('DB_PASSWORD=' . (getenv('GIFTBACK_TEST_PASSWORD') ?: ''));
    putenv('DB_SSL_MODE=disable');
    $runMigration = static function (bool $apply): array {
        $argv = $apply ? ['migration', '--apply'] : ['migration'];
        ob_start();
        include __DIR__ . '/../../database/migrations/run_store_sale_items_migration.php';
        return json_decode((string) ob_get_clean(), true);
    };
    $dryRun = $runMigration(false);
    $check($dryRun['mode'] === 'dry-run' && $dryRun['changes'] === ['store_sale_items'], 'migration dry-run identifies missing table');
    $applied = $runMigration(true);
    $check($applied['mode'] === 'applied' && $applied['changes'] === ['store_sale_items'], 'additive migration creates item table');
    $again = $runMigration(true);
    $check($again['changes'] === [], 'migration is repeatable');
    $db->exec("INSERT INTO store_sale_items(transaction_id,line_number,item_name,quantity,unit_price_cents,total_cents) VALUES(101,1,'Serviço',2,5000,10000)");
    $read = new StoreReadService($db);
    $report = $read->sellerReport([1,2], null, '2026-10-01', '2026-10-31', '11');
    $check(count($report['items']) === 2 && $report['people'][0]['salesCount'] === 2, 'one seller in two branches without duplicate sales');
    $person = $read->personReport([1,2], null, '11', '2026-10-01', '2026-10-31');
    $check($person['summary']['grossAmountCents'] === 15000 && $person['summary']['outsideBalanceCents'] === 13000,
        'gross and outside-balance metrics match');
    $check($person['summary']['recordedSalesCount'] === 1 && $person['summary']['cancelledCount'] === 1,
        'recording and cancellations are distinct');
    $unknown = $read->transactions(1, ['sellerId' => 'unknown'], 1, 10, [1,2]);
    $check($unknown['pagination']['totalItems'] === 1 && $unknown['items'][0]['sellerName'] === 'Vendedor não identificado',
        'unidentified historical seller selectable');
    $detail = $read->transaction(1, 101, [1,2]);
    $check(count($detail['items']) === 1 && count($detail['giftbackMovements']) === 1, 'sale detail contains items and ledger');
    $check(count($read->saleItemsBatch([101,102])[101]) === 1, 'item export uses scoped sale ids');
    try {
        $read->transaction(1, 102, [1], null);
        throw new RuntimeException('Cross-branch sale detail was accessible.');
    } catch (\App\Services\Store\StoreApiException $exception) {
        $check($exception->httpStatus === 404, 'cross-branch sale detail denied');
    }
} finally {
    $db->exec('USE information_schema');
    $db->exec('DROP DATABASE ' . $schema);
}
