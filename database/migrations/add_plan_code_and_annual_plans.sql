-- =====================================================
-- ADICIONA CÓDIGOS DE PLANO E PLANOS ANUAIS
-- =====================================================

-- Adicionar campo 'codigo' na tabela planos (se não existir)
-- Para re-executar este script sem erro, verificamos primeiro se a coluna existe
SET @dbname = DATABASE();
SET @tablename = 'planos';
SET @columnname = 'codigo';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE (table_name = @tablename)
       AND (table_schema = @dbname)
       AND (column_name = @columnname)) > 0,
    "SELECT 1", -- Coluna já existe, não faz nada
    CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " VARCHAR(20) UNIQUE DEFAULT NULL COMMENT 'Código único para ativação do plano' AFTER slug")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Gerar códigos para planos existentes (mensais)
UPDATE `planos` SET `codigo` = 'KLUBE-BASIC-M' WHERE `slug` = 'basico';
UPDATE `planos` SET `codigo` = 'KLUBE-PRO-M' WHERE `slug` = 'profissional';
UPDATE `planos` SET `codigo` = 'KLUBE-ENTERPRISE-M' WHERE `slug` = 'empresarial';

-- Inserir PLANOS ANUAIS como registros separados (usando INSERT IGNORE para evitar duplicação)
-- Plano Básico Anual
INSERT IGNORE INTO `planos` (`nome`, `slug`, `codigo`, `descricao`, `preco_mensal`, `preco_anual`, `moeda`, `trial_dias`, `recorrencia`, `features_json`, `ativo`, `created_at`, `updated_at`)
VALUES (
    'Básico Anual',
    'basico-anual',
    'KLUBE-BASIC-Y',
    'Plano básico com pagamento anual (16% de desconto)',
    NULL, -- Não tem preço mensal (é exclusivamente anual)
    499.00,
    'BRL',
    7,
    'yearly',
    '[\"Até 100 transações/mês\", \"Suporte por email\", \"Dashboard básico\"]',
    1,
    NOW(),
    NOW()
);

-- Plano Profissional Anual
INSERT IGNORE INTO `planos` (`nome`, `slug`, `codigo`, `descricao`, `preco_mensal`, `preco_anual`, `moeda`, `trial_dias`, `recorrencia`, `features_json`, `ativo`, `created_at`, `updated_at`)
VALUES (
    'Profissional Anual',
    'profissional-anual',
    'KLUBE-PRO-Y',
    'Plano completo com pagamento anual (16% de desconto)',
    NULL,
    999.00,
    'BRL',
    14,
    'yearly',
    '[\"Transações ilimitadas\", \"Suporte prioritário\", \"Relatórios avançados\", \"API de integração\"]',
    1,
    NOW(),
    NOW()
);

-- Plano Empresarial Anual
INSERT IGNORE INTO `planos` (`nome`, `slug`, `codigo`, `descricao`, `preco_mensal`, `preco_anual`, `moeda`, `trial_dias`, `recorrencia`, `features_json`, `ativo`, `created_at`, `updated_at`)
VALUES (
    'Empresarial Anual',
    'empresarial-anual',
    'KLUBE-ENTERPRISE-Y',
    'Plano para grandes empresas com pagamento anual (16% de desconto)',
    NULL,
    1999.00,
    'BRL',
    30,
    'yearly',
    '[\"Tudo do Profissional\", \"Gerente de conta dedicado\", \"Múltiplas lojas\", \"White label\"]',
    1,
    NOW(),
    NOW()
);

-- CÓDIGOS PROMOCIONAIS ESPECIAIS (opcionais - admin pode criar)
-- Estes são exemplos que admin pode usar para dar descontos ou trials estendidos

INSERT IGNORE INTO `planos` (`nome`, `slug`, `codigo`, `descricao`, `preco_mensal`, `preco_anual`, `moeda`, `trial_dias`, `recorrencia`, `features_json`, `ativo`, `created_at`, `updated_at`)
VALUES (
    'Básico - Trial 30 Dias',
    'basico-trial-30',
    'KLUBE-TRIAL30',
    'Plano básico com 30 dias de trial gratuito',
    49.90,
    499.00,
    'BRL',
    30,
    'both',
    '[\"Até 100 transações/mês\", \"Suporte por email\", \"Dashboard básico\", \"30 dias grátis\"]',
    1,
    NOW(),
    NOW()
);

-- Index no campo codigo para busca rápida (se não existir)
SET @dbname = DATABASE();
SET @tablename = 'planos';
SET @indexname = 'idx_codigo';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE table_schema = @dbname
       AND table_name = @tablename
       AND index_name = @indexname) > 0,
    "SELECT 1", -- Index já existe
    CONCAT("ALTER TABLE ", @tablename, " ADD INDEX ", @indexname, " (codigo)")
));
PREPARE createIndexIfNotExists FROM @preparedStatement;
EXECUTE createIndexIfNotExists;
DEALLOCATE PREPARE createIndexIfNotExists;

-- Comentários
-- Códigos de planos seguem padrão: KLUBE-[TIPO]-[CICLO]
-- Tipo: BASIC, PRO, ENTERPRISE
-- Ciclo: M (monthly), Y (yearly)
-- Admin pode compartilhar esses códigos com lojistas para auto-ativação
