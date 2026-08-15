-- Índices necessários para validar e bloquear tokens sem varrer a tabela inteira.
-- Execute uma vez no banco de produção antes de publicar o novo fluxo.

SET @has_token_index = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'recuperacao_senha'
      AND index_name = 'uq_recuperacao_senha_token'
);
SET @token_index_sql = IF(
    @has_token_index = 0,
    'ALTER TABLE recuperacao_senha ADD UNIQUE KEY uq_recuperacao_senha_token (token)',
    'SELECT 1'
);
PREPARE token_index_stmt FROM @token_index_sql;
EXECUTE token_index_stmt;
DEALLOCATE PREPARE token_index_stmt;

SET @has_expiry_index = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'recuperacao_senha'
      AND index_name = 'idx_recuperacao_senha_expiracao'
);
SET @expiry_index_sql = IF(
    @has_expiry_index = 0,
    'ALTER TABLE recuperacao_senha ADD KEY idx_recuperacao_senha_expiracao (data_expiracao)',
    'SELECT 1'
);
PREPARE expiry_index_stmt FROM @expiry_index_sql;
EXECUTE expiry_index_stmt;
DEALLOCATE PREPARE expiry_index_stmt;
