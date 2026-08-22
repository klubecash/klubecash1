<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

$apply = in_array('--apply', $argv ?? [], true);
$db = Database::getConnection();
$schema = (string) $db->query('SELECT DATABASE()')->fetchColumn();
$changes = [];

$tableExists = static function (string $table) use ($db, $schema): bool {
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=:schema AND TABLE_NAME=:table');
    $stmt->execute([':schema' => $schema, ':table' => $table]);
    return (int) $stmt->fetchColumn() > 0;
};

$columnExists = static function (string $table, string $column) use ($db, $schema): bool {
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=:schema AND TABLE_NAME=:table AND COLUMN_NAME=:column');
    $stmt->execute([':schema' => $schema, ':table' => $table, ':column' => $column]);
    return (int) $stmt->fetchColumn() > 0;
};

$indexExists = static function (string $table, string $index) use ($db, $schema): bool {
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=:schema AND TABLE_NAME=:table AND INDEX_NAME=:index');
    $stmt->execute([':schema' => $schema, ':table' => $table, ':index' => $index]);
    return (int) $stmt->fetchColumn() > 0;
};

$execute = static function (string $label, string $sql) use ($db, $apply, &$changes): void {
    $changes[] = $label;
    if ($apply) {
        $db->exec($sql);
    }
};

if (!$tableExists('admin_audit_logs')) {
    $execute('admin_audit_logs', "CREATE TABLE admin_audit_logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        actor_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        entity_type VARCHAR(80) NOT NULL,
        entity_id VARCHAR(100) NULL,
        result ENUM('success','error','denied') NOT NULL DEFAULT 'success',
        before_json JSON NULL,
        after_json JSON NULL,
        request_id VARCHAR(64) NOT NULL,
        ip_hash CHAR(64) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_admin_audit_actor_date (actor_id,created_at),
        INDEX idx_admin_audit_entity (entity_type,entity_id),
        INDEX idx_admin_audit_request (request_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

if (!$tableExists('admin_idempotency_keys')) {
    $execute('admin_idempotency_keys', "CREATE TABLE admin_idempotency_keys (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        actor_id INT NOT NULL,
        scope VARCHAR(80) NOT NULL,
        idempotency_key VARCHAR(128) NOT NULL,
        request_hash CHAR(64) NOT NULL,
        status ENUM('processing','completed','failed') NOT NULL DEFAULT 'processing',
        response_json JSON NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_admin_idempotency (actor_id,scope,idempotency_key),
        INDEX idx_admin_idempotency_expiry (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

if (!$tableExists('admin_test_runs')) {
    $execute('admin_test_runs', "CREATE TABLE admin_test_runs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        test_run_id VARCHAR(80) NOT NULL,
        entity_type VARCHAR(80) NOT NULL,
        entity_id VARCHAR(100) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_admin_test_entity (test_run_id,entity_type,entity_id),
        INDEX idx_admin_test_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

if (!$columnExists('email_campaigns', 'audience_json')) {
    $execute('email_campaigns.audience_json', 'ALTER TABLE email_campaigns ADD COLUMN audience_json JSON NULL AFTER conteudo_texto');
}
if (!$columnExists('email_campaigns', 'requires_review')) {
    $execute('email_campaigns.requires_review', 'ALTER TABLE email_campaigns ADD COLUMN requires_review TINYINT(1) NOT NULL DEFAULT 0 AFTER status');
}
if (!$columnExists('email_campaigns', 'updated_at')) {
    $execute('email_campaigns.updated_at', 'ALTER TABLE email_campaigns ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
}
if (!$columnExists('email_templates', 'archived_at')) {
    $execute('email_templates.archived_at', 'ALTER TABLE email_templates ADD COLUMN archived_at DATETIME NULL AFTER ativo');
}
if (!$columnExists('email_templates', 'updated_at')) {
    $execute('email_templates.updated_at', 'ALTER TABLE email_templates ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
}
if (!$columnExists('usuarios', 'updated_at')) {
    $execute('usuarios.updated_at', 'ALTER TABLE usuarios ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
}
if (!$columnExists('lojas', 'updated_at')) {
    $execute('lojas.updated_at', 'ALTER TABLE lojas ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
}

if ($tableExists('email_queue')) {
    $emailQueueColumns = [
        ['campaign_id', 'ALTER TABLE email_queue ADD COLUMN campaign_id INT NULL AFTER id'],
        ['recipient_id', 'ALTER TABLE email_queue ADD COLUMN recipient_id INT NULL AFTER campaign_id'],
        ['next_attempt_at', 'ALTER TABLE email_queue ADD COLUMN next_attempt_at DATETIME NULL AFTER last_attempt'],
        ['locked_at', 'ALTER TABLE email_queue ADD COLUMN locked_at DATETIME NULL AFTER next_attempt_at'],
        ['sent_at', 'ALTER TABLE email_queue ADD COLUMN sent_at DATETIME NULL AFTER locked_at'],
        ['error_message', 'ALTER TABLE email_queue ADD COLUMN error_message TEXT NULL AFTER sent_at'],
    ];
    foreach ($emailQueueColumns as [$column, $sql]) {
        if (!$columnExists('email_queue', $column)) {
            $execute("email_queue.{$column}", $sql);
        }
    }
    if (!$indexExists('email_queue', 'idx_email_queue_schedule')) {
        $execute('email_queue.idx_email_queue_schedule', 'ALTER TABLE email_queue ADD INDEX idx_email_queue_schedule (status,next_attempt_at,created_at)');
    }
    if (!$indexExists('email_queue', 'uk_email_queue_campaign_recipient')) {
        $execute('email_queue.uk_email_queue_campaign_recipient', 'ALTER TABLE email_queue ADD UNIQUE INDEX uk_email_queue_campaign_recipient (campaign_id,to_email)');
    }
}

$indexes = [
    ['usuarios', 'idx_admin_users_filter', 'ALTER TABLE usuarios ADD INDEX idx_admin_users_filter (tipo,status,data_criacao)'],
    ['lojas', 'idx_admin_stores_filter', 'ALTER TABLE lojas ADD INDEX idx_admin_stores_filter (status,categoria,data_cadastro)'],
    ['assinaturas', 'idx_admin_subscription_filter', 'ALTER TABLE assinaturas ADD INDEX idx_admin_subscription_filter (status,created_at,loja_id)'],
    ['email_campaigns', 'idx_admin_campaign_status_date', 'ALTER TABLE email_campaigns ADD INDEX idx_admin_campaign_status_date (status,data_agendamento)'],
];
foreach ($indexes as [$table, $index, $sql]) {
    if ($tableExists($table) && !$indexExists($table, $index)) {
        $execute("{$table}.{$index}", $sql);
    }
}

if ($apply && $columnExists('email_campaigns', 'requires_review')) {
    $db->exec("UPDATE email_campaigns SET requires_review=1 WHERE status IN ('agendado','enviando') AND requires_review=0");
}

echo json_encode([
    'mode' => $apply ? 'applied' : 'dry-run',
    'database' => $schema,
    'changes' => $changes,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
