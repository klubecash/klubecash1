<?php

declare(strict_types=1);

// Intentionally refuses the application database. Supply a disposable MySQL
// instance on loopback, exactly like giftback_ledger_integration.php.
$dsn = (string) getenv('GIFTBACK_TEST_DSN');
if (!preg_match('/^mysql:host=127\.0\.0\.1;port=\d+$/D', $dsn)) {
    fwrite(STDERR, "Required: GIFTBACK_TEST_DSN=mysql:host=127.0.0.1;port=<isolated test server port>\n");
    exit(2);
}
require_once __DIR__ . '/../../services/Giftback/GiftbackSchema.php';
require_once __DIR__ . '/../../services/Giftback/GiftbackLedger.php';
require_once __DIR__ . '/../../services/store/StoreApiException.php';
require_once __DIR__ . '/../../services/store/StoreNetworkAccess.php';

use App\Services\Giftback\GiftbackLedger;
use App\Services\Giftback\GiftbackSchema;
use App\Services\Store\StoreNetworkAccess;

$db = new PDO($dsn, getenv('GIFTBACK_TEST_USER') ?: 'root', getenv('GIFTBACK_TEST_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$name = 'giftback_test_' . bin2hex(random_bytes(6));
$db->exec('CREATE DATABASE ' . $name . ' CHARACTER SET utf8mb4');
$db->exec('USE ' . $name);
$zone = new DateTimeZone('America/Sao_Paulo');
$at = static fn (string $time): GiftbackLedger => new GiftbackLedger($db, new DateTimeImmutable($time, $zone));
$eq = static function (mixed $want, mixed $got, string $label): void {
    if ($want !== $got) { throw new RuntimeException($label . ': expected ' . json_encode($want) . ', got ' . json_encode($got)); }
    echo "OK {$label}\n";
};
try {
    $db->exec("CREATE TABLE usuarios(id INT PRIMARY KEY,nome VARCHAR(160),tipo VARCHAR(30),status VARCHAR(30)) ENGINE=InnoDB;
        CREATE TABLE lojas(id INT PRIMARY KEY,nome_fantasia VARCHAR(160),status VARCHAR(30)) ENGINE=InnoDB;
        CREATE TABLE cashback_saldos(id INT AUTO_INCREMENT PRIMARY KEY,usuario_id INT,loja_id INT,saldo_disponivel DECIMAL(10,2) DEFAULT 0,total_creditado DECIMAL(10,2) DEFAULT 0,total_usado DECIMAL(10,2) DEFAULT 0,ultima_atualizacao DATETIME NULL,UNIQUE(usuario_id,loja_id)) ENGINE=InnoDB;
        CREATE TABLE cashback_movimentacoes(id INT AUTO_INCREMENT PRIMARY KEY,usuario_id INT,loja_id INT,criado_por INT NULL,tipo_operacao ENUM('credito','uso','estorno'),valor DECIMAL(10,2),saldo_anterior DECIMAL(10,2),saldo_atual DECIMAL(10,2),descricao VARCHAR(255),transacao_origem_id INT NULL,transacao_uso_id INT NULL,data_operacao DATETIME NULL,pagamento_id INT NULL) ENGINE=InnoDB;
        CREATE TABLE transacoes_cashback(id INT AUTO_INCREMENT PRIMARY KEY,usuario_id INT,loja_id INT,valor_cliente DECIMAL(10,2),status VARCHAR(30)) ENGINE=InnoDB;
        CREATE TABLE admin_audit_logs(id INT AUTO_INCREMENT PRIMARY KEY,actor_id INT,action VARCHAR(100),entity_type VARCHAR(80),entity_id VARCHAR(100),result VARCHAR(20),before_json JSON,after_json JSON,request_id VARCHAR(64)) ENGINE=InnoDB");
    $db->exec("INSERT INTO usuarios VALUES(1,'Cliente','cliente','ativo'),(10,'Dono','loja','ativo'),(11,'Vendedor','funcionario','ativo'),(12,'Gestor','funcionario','ativo');
        INSERT INTO lojas VALUES(1,'Filial A','aprovado'),(2,'Filial B','aprovado')");
    GiftbackSchema::migrate($db, true);
    $db->exec("ALTER TABLE cashback_movimentacoes ADD redemption_store_id INT NULL, ADD network_id_snapshot INT NULL;
        CREATE TABLE store_networks(id INT PRIMARY KEY,name VARCHAR(160),status VARCHAR(20),version INT,created_by INT);
        CREATE TABLE store_network_memberships(store_id INT PRIMARY KEY,network_id INT,status VARCHAR(20),KEY(network_id,status,store_id));
        CREATE TABLE store_network_managers(network_id INT,user_id INT,PRIMARY KEY(network_id,user_id));
        CREATE TABLE store_user_memberships(user_id INT,store_id INT,role VARCHAR(30),status VARCHAR(20),PRIMARY KEY(user_id,store_id));
        INSERT INTO store_networks VALUES(1,'Rede de teste','active',1,10);
        INSERT INTO store_network_memberships VALUES(1,1,'active'),(2,1,'active');
        INSERT INTO store_user_memberships VALUES(10,1,'titular','active'),(11,1,'vendedor','active'),(11,2,'vendedor','active');
        INSERT INTO store_network_managers VALUES(1,12)");
    $access = new StoreNetworkAccess($db);
    $eq([1,2], $access->walletStores(2), 'active network stores');
    $eq(2, count($access->stores(11)), 'one employee account, two branches');
    $eq(2, count($access->stores(12)), 'explicit manager sees network branches');
    $eq('Gestor', $access->assertSeller(12,2), 'manager can be identified as seller');
    $db->exec('UPDATE lojas SET giftback_expiration_days=30 WHERE id IN (1,2)');
    $sale = static function (int $store) use ($db): int {
        $db->prepare("INSERT INTO transacoes_cashback(usuario_id,loja_id,valor_cliente,status) VALUES(1,?,0,'aprovado')")->execute([$store]);
        return (int) $db->lastInsertId();
    };
    $a = $at('2026-01-20')->credit(1,1,10000,'A',$sale(1));
    $b = $at('2026-01-22')->credit(1,2,5000,'B',$sale(2));
    $eq(15000,$at('2026-01-23')->networkWallet(1,[1,2])['availableCents'],'network wallet sums origins once');
    $redemption = $sale(2);
    $usage = $at('2026-01-23')->spendNetwork(1,2,[1,2],12000,$redemption,11,1);
    $eq(2,count($usage['allocations']),'two origin allocations');
    $eq(10000,$usage['allocations'][0]['amountCents'],'earliest expiry used first');
    $eq(1,$usage['allocations'][0]['originStoreId'],'first credit origin retained');
    $eq(3000,$at('2026-01-23')->networkWallet(1,[1,2])['availableCents'],'remaining balance');
    $eq(true,$at('2026-01-23')->spendNetwork(1,2,[1,2],12000,$redemption,11,1)['replayed'],'repeat safe');
    $reverse = $at('2026-02-20')->reverseSale(1,2,$redemption,'Cancelamento de teste',10);
    $eq(12000,$reverse['restoredBalanceUsedCents'],'reverse restores source credits');
    $eq(5000,$at('2026-02-20')->networkWallet(1,[1,2])['availableCents'],'expired origin cannot be resurrected');
    $eq(0,$at('2026-02-20')->creditDetail($a['creditId'])['remainingCents'],'old origin expired');
    $eq(5000,$at('2026-02-20')->creditDetail($b['creditId'])['remainingCents'],'later credit remains');
    $db->exec("UPDATE store_network_memberships SET status='suspended' WHERE store_id=1");
    $eq([2],$access->walletStores(2),'suspension blocks future cross-branch use');
    $eq(1,count($access->stores(12)),'network manager loses suspended branch');
} finally {
    $db->exec('USE information_schema');
    $db->exec('DROP DATABASE ' . $name);
}
