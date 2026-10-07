<?php

declare(strict_types=1);

/**
 * Subscription billing v2 migration.
 *
 * Dry-run is the default. Use --apply only from an operator shell after the
 * duplicate audit has been reviewed. No page request executes DDL.
 */
require_once __DIR__ . '/../../config/database.php';

$apply = in_array('--apply', $argv ?? [], true);
$db = Database::getConnection();
$schema = (string) $db->query('SELECT DATABASE()')->fetchColumn();
$changes = [];

$columnExists = static function (string $table, string $column) use ($db, $schema): bool {
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=:schema AND TABLE_NAME=:table AND COLUMN_NAME=:column');
    $stmt->execute([':schema' => $schema, ':table' => $table, ':column' => $column]);
    return (int) $stmt->fetchColumn() > 0;
};
$tableExists = static function (string $table) use ($db, $schema): bool {
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=:schema AND TABLE_NAME=:table');
    $stmt->execute([':schema' => $schema, ':table' => $table]);
    return (int) $stmt->fetchColumn() > 0;
};
$indexExists = static function (string $table, string $index) use ($db, $schema): bool {
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=:schema AND TABLE_NAME=:table AND INDEX_NAME=:index');
    $stmt->execute([':schema' => $schema, ':table' => $table, ':index' => $index]);
    return (int) $stmt->fetchColumn() > 0;
};
$columnType = static function (string $table, string $column) use ($db, $schema): string {
    $stmt = $db->prepare('SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=:schema AND TABLE_NAME=:table AND COLUMN_NAME=:column');
    $stmt->execute([':schema' => $schema, ':table' => $table, ':column' => $column]);
    return strtolower((string) $stmt->fetchColumn());
};
$execute = static function (string $label, string $sql) use ($db, $apply, &$changes): void {
    $changes[] = $label;
    if ($apply) { $db->exec($sql); }
};

foreach ([
    ['assinaturas', 'sales_blocked', "ALTER TABLE assinaturas ADD COLUMN sales_blocked TINYINT(1) NOT NULL DEFAULT 0 AFTER status"],
    ['assinaturas', 'sales_block_reason', "ALTER TABLE assinaturas ADD COLUMN sales_block_reason VARCHAR(160) NULL AFTER sales_blocked"],
    ['assinaturas', 'manual_activation_until', "ALTER TABLE assinaturas ADD COLUMN manual_activation_until DATETIME NULL AFTER sales_block_reason"],
    ['assinaturas', 'pending_plan_id', "ALTER TABLE assinaturas ADD COLUMN pending_plan_id INT NULL AFTER plano_id"],
    ['assinaturas', 'pending_cycle', "ALTER TABLE assinaturas ADD COLUMN pending_cycle ENUM('monthly','yearly') NULL AFTER ciclo"],
    ['assinaturas', 'gateway_plan_id', "ALTER TABLE assinaturas ADD COLUMN gateway_plan_id VARCHAR(255) NULL AFTER gateway_subscription_id"],
    ['faturas', 'period_start', "ALTER TABLE faturas ADD COLUMN period_start DATE NULL AFTER due_date"],
    ['faturas', 'period_end', "ALTER TABLE faturas ADD COLUMN period_end DATE NULL AFTER period_start"],
    ['faturas', 'payment_url', "ALTER TABLE faturas ADD COLUMN payment_url TEXT NULL AFTER pix_expires_at"],
    ['faturas', 'failure_code', "ALTER TABLE faturas ADD COLUMN failure_code VARCHAR(100) NULL AFTER payment_url"],
    ['faturas', 'attempts', "ALTER TABLE faturas ADD COLUMN attempts INT NOT NULL DEFAULT 0 AFTER failure_code"],
    ['faturas', 'idempotency_key', "ALTER TABLE faturas ADD COLUMN idempotency_key VARCHAR(128) NULL AFTER attempts"],
    ['faturas', 'gateway_event_id', "ALTER TABLE faturas ADD COLUMN gateway_event_id VARCHAR(255) NULL AFTER gateway_charge_id"],
] as [$table, $column, $sql]) {
    if (!$columnExists($table, $column)) { $execute("{$table}.{$column}", $sql); }
}
if ($columnExists('assinaturas', 'gateway') && !str_contains($columnType('assinaturas', 'gateway'), "'mercadopago'")) {
    $execute('assinaturas.gateway.mercadopago', "ALTER TABLE assinaturas MODIFY COLUMN gateway ENUM('abacate','stripe','mercadopago') DEFAULT NULL");
}
if ($columnExists('assinaturas', 'status') && !str_contains($columnType('assinaturas', 'status'), "'pendente'")) {
    $execute('assinaturas.status.lifecycle', "ALTER TABLE assinaturas MODIFY COLUMN status ENUM('pendente','trial','ativa','inadimplente','cancelada','suspensa','pausada') DEFAULT 'pendente'");
}
if ($columnExists('faturas', 'gateway') && !str_contains($columnType('faturas', 'gateway'), "'mercadopago'")) {
    $execute('faturas.gateway.mercadopago', "ALTER TABLE faturas MODIFY COLUMN gateway ENUM('abacate','stripe','mercadopago') DEFAULT NULL");
}

if ($tableExists('assinaturas') && !$indexExists('assinaturas', 'idx_subscription_sales_access')) {
    $execute('assinaturas.idx_subscription_sales_access', 'ALTER TABLE assinaturas ADD INDEX idx_subscription_sales_access (loja_id,status,sales_blocked,current_period_end)');
}
if ($tableExists('faturas') && !$indexExists('faturas', 'idx_invoice_period')) {
    $execute('faturas.idx_invoice_period', 'ALTER TABLE faturas ADD INDEX idx_invoice_period (assinatura_id,period_start,period_end,status)');
}

if (!$tableExists('subscription_audit_logs')) {
    $execute('subscription_audit_logs', "CREATE TABLE subscription_audit_logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        subscription_id INT NOT NULL,
        actor_id INT NULL,
        action VARCHAR(100) NOT NULL,
        before_json JSON NULL,
        after_json JSON NULL,
        request_id VARCHAR(64) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_subscription_audit_subscription (subscription_id,created_at),
        INDEX idx_subscription_audit_actor (actor_id,created_at),
        INDEX idx_subscription_audit_request (request_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
if (!$tableExists('subscription_webhook_events')) {
    $execute('subscription_webhook_events', "CREATE TABLE subscription_webhook_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        gateway VARCHAR(40) NOT NULL,
        event_type VARCHAR(100) NOT NULL,
        external_id VARCHAR(255) NOT NULL,
        resource_id VARCHAR(255) NULL,
        payload_hash CHAR(64) NOT NULL,
        processed_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_subscription_webhook (gateway,external_id),
        INDEX idx_subscription_webhook_resource (gateway,resource_id),
        INDEX idx_subscription_webhook_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
if (!$tableExists('subscription_idempotency_keys')) {
    $execute('subscription_idempotency_keys', "CREATE TABLE subscription_idempotency_keys (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        store_id INT NOT NULL,
        actor_id INT NULL,
        scope VARCHAR(80) NOT NULL,
        idempotency_key VARCHAR(128) NOT NULL,
        request_hash CHAR(64) NOT NULL,
        status ENUM('processing','completed','failed') NOT NULL DEFAULT 'processing',
        response_json JSON NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_subscription_idempotency (store_id,scope,idempotency_key),
        INDEX idx_subscription_idempotency_expiry (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// This is intentionally a report, not an automatic cleanup. Review before
// adding a unique billing-period constraint in a later migration.
$duplicates = [];
if ($tableExists('faturas')) {
    $rows = $db->query("SELECT assinatura_id, YEAR(created_at) billing_year, MONTH(created_at) billing_month, COUNT(*) total FROM faturas GROUP BY assinatura_id,YEAR(created_at),MONTH(created_at) HAVING COUNT(*) > 1 LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    $duplicates = array_map(static fn (array $row): array => [
        'subscriptionId' => (int) $row['assinatura_id'], 'year' => (int) $row['billing_year'], 'month' => (int) $row['billing_month'], 'count' => (int) $row['total'],
    ], $rows);
}

echo json_encode(['mode' => $apply ? 'applied' : 'dry-run', 'database' => $schema, 'changes' => $changes, 'duplicateAudit' => $duplicates], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
