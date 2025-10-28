-- =====================================================
-- SEPARAR PLANOS MENSAIS E ANUAIS COMPLETAMENTE
-- =====================================================
-- Remove o conflito de planos "both" que têm preço mensal e anual
-- Cada plano deve ser UM registro separado (mensal OU anual)

-- 1. Criar planos MENSAIS separados para os planos que eram "both"
INSERT IGNORE INTO `planos` (`nome`, `slug`, `codigo`, `descricao`, `preco_mensal`, `preco_anual`, `moeda`, `trial_dias`, `recorrencia`, `features_json`, `ativo`, `created_at`, `updated_at`)
SELECT
    CONCAT(nome, ' - Mensal'),
    CONCAT(slug, '-mensal'),
    CASE
        WHEN slug = 'basico' THEN 'KLUBE-BASIC-M'
        WHEN slug = 'profissional' THEN 'KLUBE-PRO-M'
        WHEN slug = 'empresarial' THEN 'KLUBE-ENTERPRISE-M'
        ELSE CONCAT('KLUBE-', UPPER(slug), '-M')
    END,
    CONCAT(descricao, ' - Cobrança mensal'),
    preco_mensal,
    NULL, -- Plano mensal não tem preço anual
    moeda,
    trial_dias,
    'monthly',
    features_json,
    ativo,
    NOW(),
    NOW()
FROM planos
WHERE recorrencia = 'both'
    AND preco_mensal IS NOT NULL
    AND slug IN ('basico', 'profissional', 'empresarial');

-- 2. Criar planos ANUAIS separados (já existem alguns, vamos garantir que todos existam)
-- Para o plano Básico Anual (já existe, mas vamos garantir dados corretos)
UPDATE `planos`
SET
    preco_mensal = NULL,
    preco_anual = 499.00,
    recorrencia = 'yearly',
    nome = 'Básico - Anual',
    descricao = 'Plano básico com pagamento anual (economia de 16% vs mensal)'
WHERE codigo = 'KLUBE-BASIC-Y';

-- Para o plano Profissional Anual
UPDATE `planos`
SET
    preco_mensal = NULL,
    preco_anual = 999.00,
    recorrencia = 'yearly',
    nome = 'Profissional - Anual',
    descricao = 'Plano profissional com pagamento anual (economia de 16% vs mensal)'
WHERE codigo = 'KLUBE-PRO-Y';

-- Para o plano Empresarial Anual
UPDATE `planos`
SET
    preco_mensal = NULL,
    preco_anual = 1999.00,
    recorrencia = 'yearly',
    nome = 'Empresarial - Anual',
    descricao = 'Plano empresarial com pagamento anual (economia de 16% vs mensal)'
WHERE codigo = 'KLUBE-ENTERPRISE-Y';

-- 3. DESATIVAR os planos antigos que eram "both" (os originais com ID 1, 2, 3)
UPDATE `planos`
SET
    ativo = 0,
    codigo = NULL -- Remove código para não conflitar
WHERE id IN (1, 2, 3)
    AND recorrencia = 'both';

-- 4. Desativar planos sem código (Klube Start, Plus, Pro, Enterprise antigos)
UPDATE `planos`
SET ativo = 0
WHERE codigo IS NULL OR codigo = '';

-- 5. Ajustar o plano TRIAL30 para ser apenas MENSAL
UPDATE `planos`
SET
    recorrencia = 'monthly',
    preco_anual = NULL,
    nome = 'Básico - Trial 30 Dias',
    descricao = 'Plano básico mensal com 30 dias de trial gratuito (promoção especial)'
WHERE codigo = 'KLUBE-TRIAL30';

-- =====================================================
-- RESULTADO ESPERADO:
-- =====================================================
-- KLUBE-BASIC-M      → Básico - Mensal (R$ 1,00/mês)
-- KLUBE-PRO-M        → Profissional - Mensal (R$ 99,90/mês)
-- KLUBE-ENTERPRISE-M → Empresarial - Mensal (R$ 199,90/mês)
-- KLUBE-BASIC-Y      → Básico - Anual (R$ 499,00/ano)
-- KLUBE-PRO-Y        → Profissional - Anual (R$ 999,00/ano)
-- KLUBE-ENTERPRISE-Y → Empresarial - Anual (R$ 1.999,00/ano)
-- KLUBE-TRIAL30      → Básico - Trial 30 Dias (R$ 49,90/mês)
