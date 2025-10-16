-- ============================================
-- Seeds para Planos de Assinatura Klube Cash
-- ============================================

-- Limpar planos antigos (apenas se necessário)
-- DELETE FROM planos WHERE slug IN ('klube-start', 'klube-plus', 'klube-pro', 'klube-enterprise');

-- Inserir/Atualizar Planos usando INSERT ... ON DUPLICATE KEY UPDATE

-- 1. Klube Start - R$ 149/mês
INSERT INTO planos (
    nome,
    slug,
    descricao,
    preco_mensal,
    preco_anual,
    moeda,
    trial_dias,
    recorrencia,
    features_json,
    ativo
) VALUES (
    'Klube Start',
    'klube-start',
    'Plano ideal para MEI e microempresas com faturamento até R$ 30k/mês',
    149.00,
    1490.00,
    'BRL',
    7,
    'both',
    '{"customers_limit":"unlimited","transactions_limit":"unlimited","employees_limit":1,"analytics_level":"basic","api_access":"none","white_label":false,"support_level":"standard"}',
    1
) ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    preco_mensal = VALUES(preco_mensal),
    preco_anual = VALUES(preco_anual),
    trial_dias = VALUES(trial_dias),
    features_json = VALUES(features_json),
    ativo = VALUES(ativo),
    updated_at = CURRENT_TIMESTAMP;

-- 2. Klube Plus - R$ 299/mês
INSERT INTO planos (
    nome,
    slug,
    descricao,
    preco_mensal,
    preco_anual,
    moeda,
    trial_dias,
    recorrencia,
    features_json,
    ativo
) VALUES (
    'Klube Plus',
    'klube-plus',
    'Plano para empresas com faturamento entre R$ 30k e R$ 100k/mês',
    299.00,
    2990.00,
    'BRL',
    7,
    'both',
    '{"customers_limit":"unlimited","transactions_limit":"unlimited","employees_limit":3,"analytics_level":"advanced","api_access":"basic","white_label":false,"support_level":"priority"}',
    1
) ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    preco_mensal = VALUES(preco_mensal),
    preco_anual = VALUES(preco_anual),
    trial_dias = VALUES(trial_dias),
    features_json = VALUES(features_json),
    ativo = VALUES(ativo),
    updated_at = CURRENT_TIMESTAMP;

-- 3. Klube Pro - R$ 549/mês
INSERT INTO planos (
    nome,
    slug,
    descricao,
    preco_mensal,
    preco_anual,
    moeda,
    trial_dias,
    recorrencia,
    features_json,
    ativo
) VALUES (
    'Klube Pro',
    'klube-pro',
    'Plano profissional para empresas com faturamento entre R$ 100k e R$ 400k/mês',
    549.00,
    5490.00,
    'BRL',
    14,
    'both',
    '{"customers_limit":"unlimited","transactions_limit":"unlimited","employees_limit":10,"analytics_level":"advanced","api_access":"full","white_label":false,"support_level":"priority"}',
    1
) ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    preco_mensal = VALUES(preco_mensal),
    preco_anual = VALUES(preco_anual),
    trial_dias = VALUES(trial_dias),
    features_json = VALUES(features_json),
    ativo = VALUES(ativo),
    updated_at = CURRENT_TIMESTAMP;

-- 4. Klube Enterprise - R$ 999/mês
INSERT INTO planos (
    nome,
    slug,
    descricao,
    preco_mensal,
    preco_anual,
    moeda,
    trial_dias,
    recorrencia,
    features_json,
    ativo
) VALUES (
    'Klube Enterprise',
    'klube-enterprise',
    'Plano enterprise para grandes empresas com faturamento acima de R$ 400k/mês',
    999.00,
    9990.00,
    'BRL',
    30,
    'both',
    '{"customers_limit":"unlimited","transactions_limit":"unlimited","employees_limit":"unlimited","analytics_level":"advanced","api_access":"full","white_label":true,"support_level":"dedicated"}',
    1
) ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    preco_mensal = VALUES(preco_mensal),
    preco_anual = VALUES(preco_anual),
    trial_dias = VALUES(trial_dias),
    features_json = VALUES(features_json),
    ativo = VALUES(ativo),
    updated_at = CURRENT_TIMESTAMP;

-- Verificar inserção
SELECT id, nome, slug, preco_mensal, trial_dias, ativo
FROM planos
WHERE slug IN ('klube-start', 'klube-plus', 'klube-pro', 'klube-enterprise')
ORDER BY preco_mensal ASC;
