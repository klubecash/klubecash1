-- =====================================================
-- ATUALIZAR PARA NOVA ESTRUTURA DE PLANOS
-- =====================================================
-- Planos: Start, Plus, Pro, Enterprise
-- Cada plano terá versão mensal e anual

-- 1. DESATIVAR TODOS OS PLANOS ANTIGOS
UPDATE `planos` SET `ativo` = 0;

-- 2. CRIAR PLANOS MENSAIS
-- Klube Start - Mensal
INSERT INTO `planos` (`nome`, `slug`, `codigo`, `descricao`, `preco_mensal`, `preco_anual`, `moeda`, `trial_dias`, `recorrencia`, `features_json`, `ativo`, `created_at`, `updated_at`)
VALUES (
    'Klube Start - Mensal',
    'klube-start-mensal',
    'KLUBE-START-M',
    'Para quem está começando a fidelizar - Microempresas e MEIs (até R$ 30k/mês)',
    149.00,
    NULL,
    'BRL',
    7,
    'monthly',
    '["Até 100 transações/mês", "Suporte por email", "Dashboard básico", "Programa de fidelidade simples"]',
    1,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    preco_mensal = VALUES(preco_mensal),
    preco_anual = VALUES(preco_anual),
    features_json = VALUES(features_json),
    ativo = 1;

-- Klube Plus - Mensal
INSERT INTO `planos` (`nome`, `slug`, `codigo`, `descricao`, `preco_mensal`, `preco_anual`, `moeda`, `trial_dias`, `recorrencia`, `features_json`, `ativo`, `created_at`, `updated_at`)
VALUES (
    'Klube Plus - Mensal',
    'klube-plus-mensal',
    'KLUBE-PLUS-M',
    'Para quem quer crescer com inteligência - Pequenas empresas (R$ 30k-100k/mês)',
    299.00,
    NULL,
    'BRL',
    14,
    'monthly',
    '["Transações ilimitadas", "Suporte prioritário", "Relatórios avançados", "Campanhas automatizadas", "Integração API"]',
    1,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    preco_mensal = VALUES(preco_mensal),
    preco_anual = VALUES(preco_anual),
    features_json = VALUES(features_json),
    ativo = 1;

-- Klube Pro - Mensal
INSERT INTO `planos` (`nome`, `slug`, `codigo`, `descricao`, `preco_mensal`, `preco_anual`, `moeda`, `trial_dias`, `recorrencia`, `features_json`, `ativo`, `created_at`, `updated_at`)
VALUES (
    'Klube Pro - Mensal',
    'klube-pro-mensal',
    'KLUBE-PRO-M',
    'Para quem busca performance máxima - Médias empresas (R$ 100k-400k/mês)',
    549.00,
    NULL,
    'BRL',
    21,
    'monthly',
    '["Clientes e transações ilimitados", "Suporte 24/7", "Análise preditiva", "Gamificação avançada", "Multi-lojas", "App customizado"]',
    1,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    preco_mensal = VALUES(preco_mensal),
    preco_anual = VALUES(preco_anual),
    features_json = VALUES(features_json),
    ativo = 1;

-- Klube Enterprise - Mensal
INSERT INTO `planos` (`nome`, `slug`, `codigo`, `descricao`, `preco_mensal`, `preco_anual`, `moeda`, `trial_dias`, `recorrencia`, `features_json`, `ativo`, `created_at`, `updated_at`)
VALUES (
    'Klube Enterprise - Mensal',
    'klube-enterprise-mensal',
    'KLUBE-ENTERPRISE-M',
    'Para quem domina o mercado - Grandes empresas (acima de R$ 400k/mês)',
    999.00,
    NULL,
    'BRL',
    30,
    'monthly',
    '["Tudo ilimitado", "White label completo", "Consultoria estratégica", "Gerente de conta dedicado", "SLA premium", "Integrações customizadas"]',
    1,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    preco_mensal = VALUES(preco_mensal),
    preco_anual = VALUES(preco_anual),
    features_json = VALUES(features_json),
    ativo = 1;

-- 3. CRIAR PLANOS ANUAIS (com 16% de desconto)
-- Klube Start - Anual (R$ 149 x 12 = R$ 1.788 - 16% = R$ 1.502)
INSERT INTO `planos` (`nome`, `slug`, `codigo`, `descricao`, `preco_mensal`, `preco_anual`, `moeda`, `trial_dias`, `recorrencia`, `features_json`, `ativo`, `created_at`, `updated_at`)
VALUES (
    'Klube Start - Anual',
    'klube-start-anual',
    'KLUBE-START-Y',
    'Para quem está começando a fidelizar - Microempresas e MEIs (até R$ 30k/mês) - Pagamento anual com 16% desconto',
    NULL,
    1502.00,
    'BRL',
    7,
    'yearly',
    '["Até 100 transações/mês", "Suporte por email", "Dashboard básico", "Programa de fidelidade simples", "Economia de 16% vs mensal"]',
    1,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    preco_mensal = VALUES(preco_mensal),
    preco_anual = VALUES(preco_anual),
    features_json = VALUES(features_json),
    ativo = 1;

-- Klube Plus - Anual (R$ 299 x 12 = R$ 3.588 - 16% = R$ 3.014)
INSERT INTO `planos` (`nome`, `slug`, `codigo`, `descricao`, `preco_mensal`, `preco_anual`, `moeda`, `trial_dias`, `recorrencia`, `features_json`, `ativo`, `created_at`, `updated_at`)
VALUES (
    'Klube Plus - Anual',
    'klube-plus-anual',
    'KLUBE-PLUS-Y',
    'Para quem quer crescer com inteligência - Pequenas empresas (R$ 30k-100k/mês) - Pagamento anual com 16% desconto',
    NULL,
    3014.00,
    'BRL',
    14,
    'yearly',
    '["Transações ilimitadas", "Suporte prioritário", "Relatórios avançados", "Campanhas automatizadas", "Integração API", "Economia de 16% vs mensal"]',
    1,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    preco_mensal = VALUES(preco_mensal),
    preco_anual = VALUES(preco_anual),
    features_json = VALUES(features_json),
    ativo = 1;

-- Klube Pro - Anual (R$ 549 x 12 = R$ 6.588 - 16% = R$ 5.534)
INSERT INTO `planos` (`nome`, `slug`, `codigo`, `descricao`, `preco_mensal`, `preco_anual`, `moeda`, `trial_dias`, `recorrencia`, `features_json`, `ativo`, `created_at`, `updated_at`)
VALUES (
    'Klube Pro - Anual',
    'klube-pro-anual',
    'KLUBE-PRO-Y',
    'Para quem busca performance máxima - Médias empresas (R$ 100k-400k/mês) - Pagamento anual com 16% desconto',
    NULL,
    5534.00,
    'BRL',
    21,
    'yearly',
    '["Clientes e transações ilimitados", "Suporte 24/7", "Análise preditiva", "Gamificação avançada", "Multi-lojas", "App customizado", "Economia de 16% vs mensal"]',
    1,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    preco_mensal = VALUES(preco_mensal),
    preco_anual = VALUES(preco_anual),
    features_json = VALUES(features_json),
    ativo = 1;

-- Klube Enterprise - Anual (R$ 999 x 12 = R$ 11.988 - 16% = R$ 10.070)
INSERT INTO `planos` (`nome`, `slug`, `codigo`, `descricao`, `preco_mensal`, `preco_anual`, `moeda`, `trial_dias`, `recorrencia`, `features_json`, `ativo`, `created_at`, `updated_at`)
VALUES (
    'Klube Enterprise - Anual',
    'klube-enterprise-anual',
    'KLUBE-ENTERPRISE-Y',
    'Para quem domina o mercado - Grandes empresas (acima de R$ 400k/mês) - Pagamento anual com 16% desconto',
    NULL,
    10070.00,
    'BRL',
    30,
    'yearly',
    '["Tudo ilimitado", "White label completo", "Consultoria estratégica", "Gerente de conta dedicado", "SLA premium", "Integrações customizadas", "Economia de 16% vs mensal"]',
    1,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    preco_mensal = VALUES(preco_mensal),
    preco_anual = VALUES(preco_anual),
    features_json = VALUES(features_json),
    ativo = 1;

-- =====================================================
-- RESULTADO ESPERADO:
-- =====================================================
-- KLUBE-START-M       → Klube Start - Mensal (R$ 149,00/mês)
-- KLUBE-PLUS-M        → Klube Plus - Mensal (R$ 299,00/mês)
-- KLUBE-PRO-M         → Klube Pro - Mensal (R$ 549,00/mês)
-- KLUBE-ENTERPRISE-M  → Klube Enterprise - Mensal (R$ 999,00/mês)
-- KLUBE-START-Y       → Klube Start - Anual (R$ 1.502,00/ano)
-- KLUBE-PLUS-Y        → Klube Plus - Anual (R$ 3.014,00/ano)
-- KLUBE-PRO-Y         → Klube Pro - Anual (R$ 5.534,00/ano)
-- KLUBE-ENTERPRISE-Y  → Klube Enterprise - Anual (R$ 10.070,00/ano)
