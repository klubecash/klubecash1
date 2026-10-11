<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

$apply = in_array('--apply', $argv ?? [], true);
try {
    $db = Database::getConnection();
    $schema = (string) $db->query('SELECT DATABASE()')->fetchColumn();
    $check = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME='store_sale_items'");
    $check->execute([$schema]);
    $missing = !$check->fetchColumn();
    if ($apply && $missing) {
        $db->exec("CREATE TABLE store_sale_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            transaction_id INT NOT NULL,
            line_number SMALLINT UNSIGNED NOT NULL,
            item_name VARCHAR(200) NOT NULL,
            quantity INT UNSIGNED NOT NULL,
            unit_price_cents BIGINT UNSIGNED NOT NULL,
            total_cents BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_store_sale_item_line (transaction_id,line_number),
            KEY idx_store_sale_items_transaction (transaction_id),
            CONSTRAINT fk_store_sale_items_transaction FOREIGN KEY (transaction_id) REFERENCES transacoes_cashback(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    echo json_encode(['mode' => $apply ? 'applied' : 'dry-run', 'database' => $schema,
        'changes' => $missing ? ['store_sale_items'] : [], 'existingSales' => (int) $db->query('SELECT COUNT(*) FROM transacoes_cashback')->fetchColumn()],
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
