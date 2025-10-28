-- =====================================================
-- TABELA DE HISTÓRICO DE MUDANÇAS DE ASSINATURAS
-- Rastreabilidade completa de upgrades/downgrades
-- =====================================================

CREATE TABLE IF NOT EXISTS `assinaturas_historico` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `assinatura_id` INT(11) NOT NULL COMMENT 'ID da assinatura que mudou',
  `plano_antigo_id` INT(11) DEFAULT NULL COMMENT 'ID do plano anterior (NULL se primeira assinatura)',
  `plano_novo_id` INT(11) NOT NULL COMMENT 'ID do novo plano',
  `ciclo_antigo` ENUM('monthly', 'yearly') DEFAULT NULL COMMENT 'Ciclo anterior',
  `ciclo_novo` ENUM('monthly', 'yearly') NOT NULL COMMENT 'Novo ciclo',
  `tipo_mudanca` ENUM('upgrade', 'downgrade', 'change_cycle', 'new') NOT NULL COMMENT 'Tipo de mudança',
  `valor_ajuste` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Valor proporcional cobrado (positivo) ou creditado (negativo)',
  `motivo` VARCHAR(255) DEFAULT NULL COMMENT 'Motivo da mudança (opcional)',
  `alterado_por` INT(11) DEFAULT NULL COMMENT 'ID do usuário que fez a alteração (admin ou lojista)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_assinatura` (`assinatura_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_tipo_mudanca` (`tipo_mudanca`),
  CONSTRAINT `fk_historico_assinatura` FOREIGN KEY (`assinatura_id`) REFERENCES `assinaturas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_historico_plano_antigo` FOREIGN KEY (`plano_antigo_id`) REFERENCES `planos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_historico_plano_novo` FOREIGN KEY (`plano_novo_id`) REFERENCES `planos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico de mudanças de planos e ciclos';

-- Comentário: Esta tabela permite auditoria completa de todas as mudanças
-- Útil para suporte ao cliente, análises de churn e relatórios financeiros
