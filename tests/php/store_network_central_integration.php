<?php

declare(strict_types=1);

// Never run against application data. Use only a disposable MySQL on loopback.
$dsn = (string) getenv('GIFTBACK_TEST_DSN');
if (!preg_match('/^mysql:host=127\.0\.0\.1;port=\d+$/D', $dsn)) {
    fwrite(STDERR, "Required: GIFTBACK_TEST_DSN=mysql:host=127.0.0.1;port=<isolated test server port>\n");
    exit(2);
}
require_once __DIR__ . '/../../services/admin/AdminApiException.php';
require_once __DIR__ . '/../../services/admin/StoreNetworkManagement.php';
require_once __DIR__ . '/../../services/store/StoreApiException.php';
require_once __DIR__ . '/../../services/store/StoreNetworkAccess.php';
require_once __DIR__ . '/../../services/store/StoreNetworkHub.php';
require_once __DIR__ . '/../../services/store/StoreManagementService.php';

use App\Services\Admin\StoreNetworkManagement;
use App\Services\Store\StoreNetworkAccess;
use App\Services\Store\StoreNetworkHub;
use App\Services\Store\StoreManagementService;

$db = new PDO($dsn, getenv('GIFTBACK_TEST_USER') ?: 'root', getenv('GIFTBACK_TEST_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$name = 'network_central_test_' . bin2hex(random_bytes(5));
$db->exec('CREATE DATABASE ' . $name . ' CHARACTER SET utf8mb4');
$db->exec('USE ' . $name);
$check = static function (bool $condition, string $label): void {
    if (!$condition) { throw new RuntimeException($label); }
    echo "OK {$label}\n";
};
try {
    $db->exec("CREATE TABLE usuarios(id INT PRIMARY KEY,nome VARCHAR(160),email VARCHAR(191),tipo VARCHAR(30),status VARCHAR(30)) ENGINE=InnoDB;
        CREATE TABLE lojas(id INT PRIMARY KEY,nome_fantasia VARCHAR(160),cnpj VARCHAR(20),status VARCHAR(30)) ENGINE=InnoDB;
        CREATE TABLE store_networks(id INT PRIMARY KEY,name VARCHAR(160),status VARCHAR(20),version INT,created_by INT) ENGINE=InnoDB;
        CREATE TABLE store_network_memberships(store_id INT PRIMARY KEY,network_id INT,status VARCHAR(20)) ENGINE=InnoDB;
        CREATE TABLE store_network_managers(network_id INT,user_id INT,PRIMARY KEY(network_id,user_id)) ENGINE=InnoDB;
        CREATE TABLE store_user_memberships(user_id INT,store_id INT,role VARCHAR(30),status VARCHAR(20),PRIMARY KEY(user_id,store_id)) ENGINE=InnoDB;
        CREATE TABLE store_user_membership_events(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,store_id INT,actor_id INT,action VARCHAR(40),old_role VARCHAR(30) NULL,new_role VARCHAR(30) NULL,reason VARCHAR(255)) ENGINE=InnoDB;
        CREATE TABLE sessoes(usuario_id INT) ENGINE=InnoDB;
        CREATE TABLE app_sessions(user_id INT) ENGINE=InnoDB;
        CREATE TABLE store_network_events(id INT AUTO_INCREMENT PRIMARY KEY,network_id INT,store_id INT NULL,actor_id INT,action VARCHAR(40),reason VARCHAR(1000),before_json JSON NULL,after_json JSON NULL,occurred_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;
        CREATE TABLE cashback_saldos(id INT AUTO_INCREMENT PRIMARY KEY,usuario_id INT,loja_id INT,saldo_disponivel DECIMAL(10,2),total_creditado DECIMAL(10,2),total_usado DECIMAL(10,2),ultima_atualizacao DATETIME,UNIQUE(usuario_id,loja_id)) ENGINE=InnoDB;
        CREATE TABLE cashback_credito_carteiras(usuario_id INT,loja_id INT,cutoff_movement_id INT,PRIMARY KEY(usuario_id,loja_id)) ENGINE=InnoDB;
        CREATE TABLE cashback_creditos(id INT AUTO_INCREMENT PRIMARY KEY,usuario_id INT,loja_id INT,kind VARCHAR(30),original_cents BIGINT,remaining_cents BIGINT,consumed_cents BIGINT,expired_cents BIGINT,revoked_cents BIGINT) ENGINE=InnoDB;
        CREATE TABLE cashback_movimentacoes(id INT AUTO_INCREMENT PRIMARY KEY,usuario_id INT,loja_id INT,saldo_atual DECIMAL(10,2)) ENGINE=InnoDB;
        CREATE TABLE cashback_credito_alocacoes(id INT AUTO_INCREMENT PRIMARY KEY,movement_id INT,amount_cents BIGINT,refunded_cents BIGINT) ENGINE=InnoDB;
        CREATE TABLE transacoes_cashback(id INT AUTO_INCREMENT PRIMARY KEY,loja_id INT,status VARCHAR(30),valor_total DECIMAL(10,2)) ENGINE=InnoDB;
        CREATE TABLE store_wallet_reconciliation_events(id INT AUTO_INCREMENT PRIMARY KEY,network_id INT,user_id INT,store_id INT,actor_id INT,action VARCHAR(48),reason VARCHAR(1000),before_json JSON,after_json JSON,occurred_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;
        INSERT INTO usuarios VALUES(1,'Admin','admin@example.test','admin','ativo'),(2,'Cliente','cliente@example.test','cliente','ativo'),(3,'Gestora','gestora@example.test','funcionario','ativo'),(4,'Vendedora','vendedora@example.test','funcionario','ativo');
        INSERT INTO lojas VALUES(1,'Centro','11111111111111','aprovado'),(2,'Shopping','22222222222222','aprovado'),(3,'Outra','33333333333333','aprovado');
        INSERT INTO store_networks VALUES(8,'Rede Sol','active',1,1);
        INSERT INTO store_network_memberships VALUES(1,8,'active');
        INSERT INTO cashback_credito_carteiras VALUES(2,1,0),(2,3,0);
        INSERT INTO cashback_creditos(usuario_id,loja_id,kind,original_cents,remaining_cents,consumed_cents,expired_cents,revoked_cents) VALUES(2,1,'grant',3000,2500,500,0,0),(2,3,'grant',1000,1000,0,0,0);
        INSERT INTO cashback_movimentacoes(usuario_id,loja_id,saldo_atual) VALUES(2,1,25.00),(2,3,10.00);
        INSERT INTO cashback_saldos(usuario_id,loja_id,saldo_disponivel,total_creditado,total_usado) VALUES(2,3,1.00,10.00,0.00)");
    $service = new StoreNetworkManagement($db, 1);
    $health = $service->walletHealth(8);
    $check(!$health['healthy'] && $health['caseCount'] === 1 && $health['cases'][0]['canRepairMissingWallet'], 'detects scoped missing aggregate');
    $check($service->repairMissingWallet(8, 2, 1, 2500, 'Crédito e última movimentação confirmados no extrato')['walletCents'] === 2500, 'audited deterministic repair');
    $check($service->walletHealth(8)['healthy'], 'network health reconciled');
    $check((int) $db->query('SELECT COUNT(*) FROM store_wallet_reconciliation_events')->fetchColumn() === 1, 'repair audit persisted');
    $service->branch(8, 2, 'join', 'Entrada aprovada após conciliação');
    $check((int) $db->query('SELECT COUNT(*) FROM store_network_memberships WHERE store_id=2')->fetchColumn() === 1,
        'unrelated store mismatch does not block network');
    $db->exec("INSERT INTO store_network_managers VALUES(8,3);
        INSERT INTO store_user_memberships VALUES(4,1,'vendedor','active'),(4,2,'vendedor','active');
        INSERT INTO sessoes VALUES(4); INSERT INTO app_sessions VALUES(4)");
    $access = new StoreNetworkAccess($db);
    $access->assertManagedBranch(3, 8, 2);
    try { $access->assertManagedBranch(3, 8, 3); throw new RuntimeException('Out-of-network branch allowed'); }
    catch (\App\Services\Store\StoreApiException $expected) { $check($expected->httpStatus === 403, 'manager cannot alter another network'); }
    $hub = new StoreNetworkHub($db);
    $team = $hub->team(3, 8, '', 1);
    $check(count($team['items']) === 1 && count($team['items'][0]['branches']) === 2, 'one account has two branch links');
    $management = new StoreManagementService($db);
    $management->changeBranchRole(2, 4, 3, 'gerente', 'vendedor');
    $check((int) $db->query('SELECT COUNT(*) FROM store_user_membership_events')->fetchColumn() === 1, 'role change audited');
    $check((int) $db->query('SELECT COUNT(*) FROM sessoes')->fetchColumn() === 0, 'old employee sessions revoked');
    try { $management->changeBranchRole(2, 4, 3, 'financeiro', 'vendedor'); throw new RuntimeException('Stale role allowed'); }
    catch (\App\Services\Store\StoreApiException $expected) { $check($expected->httpStatus === 409, 'stale role rejected'); }
    $db->exec('UPDATE cashback_saldos SET saldo_disponivel=24.00 WHERE usuario_id=2 AND loja_id=1');
    try { $service->branch(8, 2, 'suspend', 'Teste de suspensão'); $service->branch(8, 2, 'resume', 'Teste de retomada');
        throw new RuntimeException('Divergent wallet was allowed'); }
    catch (\App\Services\Admin\AdminApiException $expected) {
        $check($expected->getCode() !== 0 || str_contains($expected->getMessage(), 'Conciliação'), 'resume blocked by mismatch');
    }
} finally {
    $db->exec('USE information_schema');
    $db->exec('DROP DATABASE ' . $name);
}
