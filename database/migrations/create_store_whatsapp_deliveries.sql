CREATE TABLE IF NOT EXISTS store_whatsapp_deliveries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    transaction_id INT NOT NULL,
    loja_id INT NOT NULL,
    status ENUM('pending','processing','sent','ignored','failed') NOT NULL DEFAULT 'pending',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL,
    provider_message_id VARCHAR(191) NULL,
    last_error_code VARCHAR(64) NULL,
    processed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_store_whatsapp_transaction (transaction_id),
    KEY idx_store_whatsapp_queue (status, available_at),
    KEY idx_store_whatsapp_store (loja_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
