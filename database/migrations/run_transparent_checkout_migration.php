<?php

declare(strict_types=1);

/**
 * Checkout Transparente / cobrança manual migration.
 *
 * Dry-run is the default. DDL is only executed with --apply from an operator
 * shell; HTTP requests never invoke this file.
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
    ['assinaturas', 'billing_mode', "ALTER TABLE assinaturas ADD COLUMN billing_mode ENUM('manual','preapproval_legacy') NOT NULL DEFAULT 'preapproval_legacy' AFTER gateway"],
    ['faturas', 'payment_status_detail', 'ALTER TABLE faturas ADD COLUMN payment_status_detail VARCHAR(160) NULL AFTER payment_method'],
    ['faturas', 'payment_type', 'ALTER TABLE faturas ADD COLUMN payment_type VARCHAR(40) NULL AFTER payment_status_detail'],
    ['faturas', 'period_key', 'ALTER TABLE faturas ADD COLUMN period_key VARCHAR(120) NULL AFTER period_end'],
] as [$table, $column, $sql]) {
    if (!$columnExists($table, $column)) { $execute("{$table}.{$column}", $sql); }
}
if ($columnExists('faturas', 'gateway') && !str_contains($columnType('faturas', 'gateway'), "'mercadopago'")) {
    $execute('faturas.gateway.mercadopago', "ALTER TABLE faturas MODIFY COLUMN gateway ENUM('abacate','stripe','mercadopago') DEFAULT NULL");
}
if ($columnExists('assinaturas', 'gateway') && !str_contains($columnType('assinaturas', 'gateway'), "'mercadopago'")) {
    $execute('assinaturas.gateway.mercadopago', "ALTER TABLE assinaturas MODIFY COLUMN gateway ENUM('abacate','stripe','mercadopago') DEFAULT NULL");
}

if ($tableExists('assinaturas') && !$indexExists('assinaturas', 'idx_subscription_billing_mode')) {
    $execute('assinaturas.idx_subscription_billing_mode', 'ALTER TABLE assinaturas ADD INDEX idx_subscription_billing_mode (billing_mode,status,current_period_end)');
}
if ($tableExists('faturas') && !$indexExists('faturas', 'idx_invoice_payment_status')) {
    $execute('faturas.idx_invoice_payment_status', 'ALTER TABLE faturas ADD INDEX idx_invoice_payment_status (assinatura_id,status,due_date)');
}

if (!$tableExists('subscription_payment_idempotency')) {
    $execute('subscription_payment_idempotency', "CREATE TABLE subscription_payment_idempotency (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        store_id INT NOT NULL,
        invoice_id INT NOT NULL,
        idempotency_key VARCHAR(128) NOT NULL,
        request_hash CHAR(64) NOT NULL,
        status ENUM('processing','completed','failed') NOT NULL DEFAULT 'processing',
        response_json JSON NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_subscription_payment_idempotency (store_id,invoice_id,idempotency_key),
        INDEX idx_subscription_payment_expiry (expires_at),
        INDEX idx_subscription_payment_invoice (invoice_id,status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
if (!$tableExists('subscription_payment_events')) {
    $execute('subscription_payment_events', "CREATE TABLE subscription_payment_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        gateway VARCHAR(40) NOT NULL,
        event_id VARCHAR(255) NOT NULL,
        payment_id VARCHAR(255) NOT NULL,
        event_type VARCHAR(100) NOT NULL,
        payload_hash CHAR(64) NOT NULL,
        processed_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_subscription_payment_event (gateway,event_id),
        INDEX idx_subscription_payment_event_payment (gateway,payment_id),
        INDEX idx_subscription_payment_event_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// Audit duplicate periods before an operator adds a stricter unique key later.
$duplicates = [];
if ($tableExists('faturas') && $columnExists('faturas', 'period_start') && $columnExists('faturas', 'period_end')) {
    $rows = $db->query("SELECT assinatura_id,period_start,period_end,COUNT(*) total FROM faturas WHERE period_start IS NOT NULL AND period_end IS NOT NULL GROUP BY assinatura_id,period_start,period_end HAVING COUNT(*) > 1 LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    $duplicates = array_map(static fn (array $row): array => [
        'subscriptionId' => (int) $row['assinatura_id'],
        'periodStart' => (string) $row['period_start'],
        'periodEnd' => (string) $row['period_end'],
        'count' => (int) $row['total'],
    ], $rows);
}

echo json_encode([
    'mode' => $apply ? 'applied' : 'dry-run',
    'database' => $schema,
    'changes' => $changes,
    'duplicateAudit' => $duplicates,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
