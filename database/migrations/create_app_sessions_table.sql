-- Sessoes PHP duraveis para o runtime serverless da Vercel.
CREATE TABLE IF NOT EXISTS app_sessions (
    id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id INT NULL,
    payload MEDIUMBLOB NOT NULL,
    last_activity INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_app_sessions_user_id (user_id),
    INDEX idx_app_sessions_activity (last_activity)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
