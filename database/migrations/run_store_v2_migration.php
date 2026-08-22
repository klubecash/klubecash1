<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

$apply = in_array('--apply', $argv ?? [], true);
$db = Database::getConnection();
$databaseName = (string) $db->query('SELECT DATABASE()')->fetchColumn();
$changes = [];

$columnExists = static function (string $table, string $column) use ($db, $databaseName): bool {
    $statement = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=:schema AND TABLE_NAME=:table AND COLUMN_NAME=:column'
    );
    $statement->execute([':schema' => $databaseName, ':table' => $table, ':column' => $column]);
    return (int) $statement->fetchColumn() > 0;
};

$indexExists = static function (string $table, string $index) use ($db, $databaseName): bool {
    $statement = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=:schema AND TABLE_NAME=:table AND INDEX_NAME=:index'
    );
    $statement->execute([':schema' => $databaseName, ':table' => $table, ':index' => $index]);
    return (int) $statement->fetchColumn() > 0;
};

$tableExists = static function (string $table) use ($db, $databaseName): bool {
    $statement = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=:schema AND TABLE_NAME=:table'
    );
    $statement->execute([':schema' => $databaseName, ':table' => $table]);
    return (int) $statement->fetchColumn() > 0;
};

$execute = static function (string $label, string $sql) use ($db, $apply, &$changes): void {
    $changes[] = $label;
    if ($apply) {
        $db->exec($sql);
    }
};

if (!$columnExists('transacoes_cashback', 'financial_model')) {
    $execute(
        'transacoes_cashback.financial_model',
        "ALTER TABLE transacoes_cashback ADD COLUMN financial_model VARCHAR(32) NOT NULL DEFAULT 'commission_legacy' AFTER status"
    );
}
if (!$columnExists('transacoes_cashback', 'cashback_credited_at')) {
    $execute(
        'transacoes_cashback.cashback_credited_at',
        'ALTER TABLE transacoes_cashback ADD COLUMN cashback_credited_at DATETIME NULL AFTER financial_model'
    );
}
if (!$columnExists('planos', 'codigo')) {
    $execute(
        'planos.codigo',
        "ALTER TABLE planos ADD COLUMN codigo VARCHAR(32) NULL AFTER slug"
    );
}

if (!$tableExists('store_idempotency_keys')) {
    $execute(
        'store_idempotency_keys',
        "CREATE TABLE store_idempotency_keys (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            scope VARCHAR(50) NOT NULL,
            loja_id INT NOT NULL,
            usuario_id INT NOT NULL,
            idempotency_key VARCHAR(128) NOT NULL,
            request_hash CHAR(64) NOT NULL,
            status ENUM('processing','completed','failed') NOT NULL DEFAULT 'processing',
            response_json JSON NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_store_idempotency (scope,loja_id,idempotency_key),
            INDEX idx_store_idempotency_expiry (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

if (!$tableExists('store_event_outbox')) {
    $execute(
        'store_event_outbox',
        "CREATE TABLE store_event_outbox (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type VARCHAR(80) NOT NULL,
            aggregate_id BIGINT NOT NULL,
            loja_id INT NOT NULL,
            payload_json JSON NOT NULL,
            status ENUM('pending','processing','sent','failed') NOT NULL DEFAULT 'pending',
            attempts INT NOT NULL DEFAULT 0,
            available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            processed_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_store_event (event_type,aggregate_id),
            INDEX idx_store_event_queue (status,available_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

$duplicateCodes = (int) $db->query(
    "SELECT COUNT(*) FROM (SELECT loja_id,codigo_transacao FROM transacoes_cashback "
    . "WHERE codigo_transacao IS NOT NULL AND codigo_transacao<>'' GROUP BY loja_id,codigo_transacao HAVING COUNT(*)>1) duplicate_codes"
)->fetchColumn();
if ($duplicateCodes > 0) {
    throw new RuntimeException('Há códigos de transação duplicados; a migration foi interrompida sem removê-los.');
}

$duplicateAddresses = (int) $db->query(
    'SELECT COUNT(*) FROM (SELECT loja_id FROM lojas_endereco GROUP BY loja_id HAVING COUNT(*)>1) duplicate_addresses'
)->fetchColumn();
if ($duplicateAddresses > 0) {
    throw new RuntimeException('Há endereços duplicados por loja; a migration foi interrompida sem removê-los.');
}

$indexes = [
    ['transacoes_cashback', 'uk_store_transaction_code', 'UNIQUE INDEX uk_store_transaction_code (loja_id,codigo_transacao)'],
    ['transacoes_cashback', 'idx_store_status_date', 'INDEX idx_store_status_date (loja_id,status,data_transacao)'],
    ['transacoes_cashback', 'idx_store_date', 'INDEX idx_store_date (loja_id,data_transacao)'],
    ['pagamentos_comissao', 'idx_commission_store_date', 'INDEX idx_commission_store_date (loja_id,data_registro)'],
    ['store_balance_payments', 'idx_balance_store_date', 'INDEX idx_balance_store_date (loja_id,data_criacao)'],
    ['cashback_movimentacoes', 'idx_balance_use_lookup', 'INDEX idx_balance_use_lookup (transacao_uso_id,usuario_id,loja_id,tipo_operacao)'],
    ['usuarios', 'idx_store_employee', 'INDEX idx_store_employee (loja_vinculada_id,tipo,status)'],
    ['lojas_endereco', 'uk_store_address', 'UNIQUE INDEX uk_store_address (loja_id)'],
    ['planos', 'uk_plan_code', 'UNIQUE INDEX uk_plan_code (codigo)'],
];
foreach ($indexes as [$table, $name, $definition]) {
    if (!$indexExists($table, $name)) {
        $execute("{$table}.{$name}", "ALTER TABLE {$table} ADD {$definition}");
    }
}

if ($apply && $columnExists('planos', 'codigo')) {
    $updates = [
        'basico' => 'KLUBE-BASIC-M',
        'profissional' => 'KLUBE-PRO-M',
        'empresarial' => 'KLUBE-ENTERPRISE-M',
    ];
    $statement = $db->prepare('UPDATE planos SET codigo=:code WHERE slug=:slug AND (codigo IS NULL OR codigo=\'\')');
    foreach ($updates as $slug => $code) {
        $statement->execute([':code' => $code, ':slug' => $slug]);
    }
}

echo json_encode([
    'mode' => $apply ? 'applied' : 'dry-run',
    'database' => $databaseName,
    'changes' => $changes,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
