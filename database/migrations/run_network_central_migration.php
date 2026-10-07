<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

$apply = in_array('--apply', $argv ?? [], true);
try {
    $db = Database::getConnection();
    $schema = (string) $db->query('SELECT DATABASE()')->fetchColumn();
    $stmt = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME='store_wallet_reconciliation_events'");
    $stmt->execute([$schema]);
    $exists = (bool) $stmt->fetchColumn();
    if ($apply && !$exists) {
        $db->exec("CREATE TABLE store_wallet_reconciliation_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            network_id INT NOT NULL, user_id INT NOT NULL, store_id INT NOT NULL,
            actor_id INT NOT NULL, action VARCHAR(48) NOT NULL, reason VARCHAR(1000) NOT NULL,
            before_json JSON NOT NULL, after_json JSON NOT NULL,
            occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_reconciliation_network (network_id,store_id,id),
            KEY idx_reconciliation_wallet (user_id,store_id,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    echo json_encode(['mode' => $apply ? 'applied' : 'dry-run', 'database' => $schema,
        'missingTable' => !$exists, 'changes' => !$exists ? ['store_wallet_reconciliation_events'] : []],
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
