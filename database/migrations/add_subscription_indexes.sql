-- =====================================================
-- INDEXES PARA SISTEMA DE ASSINATURAS - KLUBE CASH
-- Previne duplicações e melhora performance
-- =====================================================

-- Prevenir faturas duplicadas no mesmo mês para mesma assinatura
-- Nota: O controle de duplicação é feito pela aplicação (SubscriptionController)
-- Este index otimiza a query de verificação
ALTER TABLE `faturas`
ADD INDEX `idx_assinatura_status_created` (
    `assinatura_id`,
    `status`,
    `created_at`
);

-- Otimizar busca de faturas pendentes por data
ALTER TABLE `faturas`
ADD INDEX `idx_status_due_date` (`status`, `due_date`);

-- Otimizar busca de assinaturas para renovação (usado pelo cron)
ALTER TABLE `assinaturas`
ADD INDEX `idx_billing` (`next_invoice_date`, `status`, `cancel_at`);

-- Melhorar performance de busca de assinatura ativa por loja
ALTER TABLE `assinaturas`
ADD INDEX `idx_loja_status_tipo` (`loja_id`, `status`, `tipo`);

-- Index para gateway IDs (pagamentos externos)
ALTER TABLE `faturas`
ADD INDEX `idx_gateway_ids` (`gateway_charge_id`, `gateway_invoice_id`);

-- COMENTÁRIO: Não adicionamos UNIQUE constraint em loja_id + status
-- porque uma loja pode ter múltiplas assinaturas (histórico)
-- O controle de duplicação é feito pela aplicação
