<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use PDO;

final class WahaSchemaManager
{
    private static bool $migrated = false;

    public function __construct(private PDO $db)
    {
    }

    public function migrate(): void
    {
        if (self::$migrated) {
            return;
        }

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS waha_webhook_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                request_id VARCHAR(191) NOT NULL,
                event_id VARCHAR(191) NOT NULL,
                event_type ENUM('message','message.ack','session.status') NOT NULL,
                payload_json JSON NOT NULL,
                from_me TINYINT(1) NOT NULL DEFAULT 0,
                associated_user_id INT NULL,
                status ENUM('pending','processing','processed','ignored','failed') NOT NULL DEFAULT 'pending',
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                available_at DATETIME NOT NULL,
                processed_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_waha_request_id (request_id),
                UNIQUE KEY uk_waha_event_id (event_id),
                KEY idx_waha_queue (status, available_at),
                KEY idx_waha_user (associated_user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS store_whatsapp_deliveries (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::$migrated = true;
    }
}
