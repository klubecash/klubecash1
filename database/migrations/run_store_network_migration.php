<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

$schemaOnly = in_array('--apply-schema-only', $argv ?? [], true);
$apply = $schemaOnly || in_array('--apply', $argv ?? [], true);
try {
    $db = Database::getConnection();
} catch (Throwable $error) {
    fwrite(STDERR, "Banco indisponível. Configure DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME e DB_PASSWORD no ambiente de execução.\n");
    exit(2);
}
$schema = (string) $db->query('SELECT DATABASE()')->fetchColumn();
$changes = [];
$exists = static function (string $kind, string $table, ?string $name = null) use ($db, $schema): bool {
    $catalog = match ($kind) {
        'table' => ['information_schema.TABLES', 'TABLE_NAME'],
        'column' => ['information_schema.COLUMNS', 'COLUMN_NAME'],
        'index' => ['information_schema.STATISTICS', 'INDEX_NAME'],
    };
    $sql = 'SELECT 1 FROM ' . $catalog[0] . ' WHERE TABLE_SCHEMA=? AND TABLE_NAME=?';
    $args = [$schema, $table];
    if ($name !== null) { $sql .= ' AND ' . $catalog[1] . '=?'; $args[] = $name; }
    $sql .= ' LIMIT 1';
    $stmt = $db->prepare($sql);
    $stmt->execute($args);
    return (bool) $stmt->fetchColumn();
};
$run = static function (string $label, string $sql) use ($db, $apply, &$changes): void {
    $changes[] = $label;
    if ($apply) { $db->exec($sql); }
};

foreach (['usuarios', 'lojas', 'transacoes_cashback', 'cashback_movimentacoes',
    'cashback_saldos', 'cashback_creditos', 'cashback_credito_carteiras'] as $required) {
    if (!$exists('table', $required)) {
        throw new RuntimeException('Migração anterior obrigatória: tabela ' . $required . ' ausente.');
    }
}
$preflight = [
    'stores' => (int) $db->query("SELECT COUNT(*) FROM lojas WHERE status='aprovado'")->fetchColumn(),
    'employees' => (int) $db->query("SELECT COUNT(*) FROM usuarios WHERE tipo='funcionario' AND loja_vinculada_id IS NOT NULL")->fetchColumn(),
    'historicalSalesWithoutProvenSeller' => (int) $db->query('SELECT COUNT(*) FROM transacoes_cashback'
        . ($exists('column', 'transacoes_cashback', 'vendedor_id') ? ' WHERE vendedor_id IS NULL' : ''))->fetchColumn(),
];
// Network membership never moves a cent, but a dirty ledger must be reviewed
// before multi-store spending is enabled. Expire due credits first if needed.
$preflight['walletMismatches'] = (int) $db->query("SELECT COUNT(*) FROM cashback_saldos s
    LEFT JOIN (SELECT usuario_id,loja_id,SUM(remaining_cents) cents FROM cashback_creditos GROUP BY usuario_id,loja_id) c
        ON c.usuario_id=s.usuario_id AND c.loja_id=s.loja_id
    WHERE ROUND(s.saldo_disponivel*100)<>COALESCE(c.cents,0)")->fetchColumn();
$preflight['creditsWithoutWallet'] = (int) $db->query("SELECT COUNT(*) FROM cashback_creditos c
    LEFT JOIN cashback_saldos s ON s.usuario_id=c.usuario_id AND s.loja_id=c.loja_id
    WHERE s.id IS NULL")->fetchColumn();
if ($apply && !$schemaOnly && ($preflight['walletMismatches'] > 0 || $preflight['creditsWithoutWallet'] > 0)) {
    throw new RuntimeException('Conciliação de saldos pendente; simule e corrija antes de aplicar.');
}

if (!$exists('table', 'store_networks')) {
    $run('store_networks', "CREATE TABLE store_networks (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(160) NOT NULL,
        status ENUM('active','suspended') NOT NULL DEFAULT 'active',
        version INT NOT NULL DEFAULT 1,
        created_by INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
if (!$exists('table', 'store_network_memberships')) {
    $run('store_network_memberships', "CREATE TABLE store_network_memberships (
        store_id INT NOT NULL PRIMARY KEY,
        network_id INT NOT NULL,
        status ENUM('active','suspended') NOT NULL DEFAULT 'active',
        joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_network_status (network_id,status,store_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
if (!$exists('table', 'store_network_events')) {
    $run('store_network_events', "CREATE TABLE store_network_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        network_id INT NOT NULL, store_id INT NULL, actor_id INT NOT NULL,
        action VARCHAR(40) NOT NULL, reason VARCHAR(1000) NOT NULL,
        before_json JSON NULL, after_json JSON NULL,
        occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_network_events (network_id,id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
if (!$exists('table', 'store_user_memberships')) {
    $run('store_user_memberships', "CREATE TABLE store_user_memberships (
        user_id INT NOT NULL, store_id INT NOT NULL,
        role ENUM('titular','gerente','financeiro','vendedor') NOT NULL,
        status ENUM('pending','active','inactive') NOT NULL DEFAULT 'pending',
        invited_by INT NULL, accepted_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id,store_id),
        KEY idx_membership_store (store_id,status,role,user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
if (!$exists('table', 'store_user_membership_events')) {
    $run('store_user_membership_events', "CREATE TABLE store_user_membership_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL, store_id INT NOT NULL, actor_id INT NOT NULL,
        action VARCHAR(40) NOT NULL, old_role VARCHAR(20) NULL, new_role VARCHAR(20) NULL,
        reason VARCHAR(1000) NOT NULL, occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_member_events (user_id,store_id,id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
if (!$exists('table', 'store_network_managers')) {
    $run('store_network_managers', "CREATE TABLE store_network_managers (
        network_id INT NOT NULL, user_id INT NOT NULL,
        granted_by INT NOT NULL, granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (network_id,user_id), KEY idx_manager_user (user_id,network_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
if (!$exists('table', 'store_sale_attribution_events')) {
    $run('store_sale_attribution_events', "CREATE TABLE store_sale_attribution_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        transaction_id INT NOT NULL, previous_seller_id INT NULL, new_seller_id INT NOT NULL,
        actor_id INT NOT NULL, reason VARCHAR(1000) NOT NULL,
        occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_sale_attribution (transaction_id,id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
if (!$exists('table', 'network_wallet_merges')) {
    $run('network_wallet_merges', "CREATE TABLE network_wallet_merges (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        network_id INT NOT NULL, source_user_id INT NOT NULL, target_user_id INT NOT NULL,
        phone_hash CHAR(64) NOT NULL, challenge_id BIGINT UNSIGNED NOT NULL,
        balances_json JSON NOT NULL, merged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_network_merge_source (network_id,source_user_id),
        KEY idx_network_merge_target (network_id,target_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
if (!$exists('table', 'network_visitor_verifications')) {
    $run('network_visitor_verifications', "CREATE TABLE network_visitor_verifications (
        network_id INT NOT NULL, user_id INT NOT NULL, challenge_id BIGINT UNSIGNED NOT NULL,
        verified_until DATETIME NOT NULL, verified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (network_id,user_id), KEY idx_visitor_proof_expiry (verified_until)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
foreach ([
    'vendedor_id' => 'INT NULL',
    'network_id_snapshot' => 'INT NULL',
    'vendedor_nome_snapshot' => 'VARCHAR(160) NULL',
    'registrado_por_nome_snapshot' => 'VARCHAR(160) NULL',
    'source_channel' => 'VARCHAR(32) NULL',
] as $name => $definition) {
    if (!$exists('column', 'transacoes_cashback', $name)) {
        $run('transacoes_cashback.' . $name, 'ALTER TABLE transacoes_cashback ADD COLUMN ' . $name . ' ' . $definition);
    }
}
foreach (['redemption_store_id' => 'INT NULL', 'network_id_snapshot' => 'INT NULL'] as $name => $definition) {
    if (!$exists('column', 'cashback_movimentacoes', $name)) {
        $run('cashback_movimentacoes.' . $name, 'ALTER TABLE cashback_movimentacoes ADD COLUMN ' . $name . ' ' . $definition);
    }
}
if (!$exists('index', 'transacoes_cashback', 'idx_sale_seller_store_date')) {
    $run('transacoes_cashback.idx_sale_seller_store_date', 'ALTER TABLE transacoes_cashback ADD INDEX idx_sale_seller_store_date (vendedor_id,loja_id,data_transacao)');
}
if ($apply) {
    // Existing rows are deliberately NOT assigned a seller: criado_por may be
    // a CSV importer or an operator recording someone else's sale.
    $db->exec("INSERT IGNORE INTO store_user_memberships(user_id,store_id,role,status,accepted_at)
        SELECT u.id,l.id,'titular','active',NOW() FROM lojas l JOIN usuarios u ON u.id=l.usuario_id WHERE u.tipo='loja'");
    $db->exec("INSERT IGNORE INTO store_user_memberships(user_id,store_id,role,status,accepted_at)
        SELECT u.id,u.loja_vinculada_id,
        CASE WHEN u.subtipo_funcionario IN ('gerente','financeiro') THEN u.subtipo_funcionario ELSE 'vendedor' END,
        CASE WHEN u.status='ativo' THEN 'active' ELSE 'inactive' END,NOW()
        FROM usuarios u WHERE u.tipo='funcionario' AND u.loja_vinculada_id IS NOT NULL");
}
echo json_encode(['mode' => $schemaOnly ? 'schema-only-applied' : ($apply ? 'applied' : 'dry-run'), 'database' => $schema,
    'preflight' => $preflight, 'changes' => $changes], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
