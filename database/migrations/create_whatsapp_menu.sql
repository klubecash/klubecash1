CREATE TABLE IF NOT EXISTS whatsapp_conversations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sender_key CHAR(64) NOT NULL,
    status ENUM('open','closed','human_paused') NOT NULL DEFAULT 'closed',
    state VARCHAR(64) NOT NULL DEFAULT 'idle',
    state_payload LONGTEXT NULL,
    authenticated_user_id INT NULL,
    loja_id INT NULL,
    auth_idle_expires_at DATETIME NULL,
    auth_absolute_expires_at DATETIME NULL,
    menu_expires_at DATETIME NULL,
    rate_window_started_at DATETIME NULL,
    rate_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    invalid_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    blocked_until DATETIME NULL,
    last_activity_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_whatsapp_conversation_sender (sender_key),
    KEY idx_whatsapp_conversation_auth (authenticated_user_id, loja_id),
    KEY idx_whatsapp_conversation_expiry (menu_expires_at, auth_idle_expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_auth_challenges (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    token_hash CHAR(64) NOT NULL,
    sender_key CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_whatsapp_auth_token (token_hash),
    KEY idx_whatsapp_auth_sender (sender_key, expires_at),
    KEY idx_whatsapp_auth_expiry (expires_at, consumed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_bot_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    action_key VARCHAR(191) NOT NULL,
    source_event_id BIGINT UNSIGNED NULL,
    sender_key CHAR(64) NOT NULL,
    delivery_payload LONGTEXT NULL,
    provider_message_id VARCHAR(191) NULL,
    status ENUM('pending','sent','failed','delivery_unknown') NOT NULL DEFAULT 'pending',
    last_error_code VARCHAR(64) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_whatsapp_bot_action (action_key),
    KEY idx_whatsapp_bot_provider (provider_message_id),
    KEY idx_whatsapp_bot_sender (sender_key, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_action_audit (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    action_key VARCHAR(191) NULL,
    sender_key CHAR(64) NOT NULL,
    usuario_id INT NULL,
    loja_id INT NULL,
    transacao_id INT NULL,
    action VARCHAR(80) NOT NULL,
    result VARCHAR(32) NOT NULL,
    request_id VARCHAR(191) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_whatsapp_audit_action (action_key),
    KEY idx_whatsapp_audit_sender (sender_key, created_at),
    KEY idx_whatsapp_audit_store (loja_id, created_at),
    KEY idx_whatsapp_audit_transaction (transacao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
