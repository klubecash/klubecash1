-- Script para verificar usuários disponíveis para login no Klube Cash Portal

-- ====================
-- USUÁRIOS DO TIPO LOJA
-- ====================
SELECT
    '=== USUÁRIOS LOJISTAS ===' as info;

SELECT
    u.id,
    u.nome,
    u.email,
    u.tipo,
    u.status,
    u.data_criacao,
    u.ultimo_login,
    l.id as loja_id,
    l.nome_fantasia as nome_loja,
    l.status as status_loja
FROM usuarios u
LEFT JOIN lojas l ON l.usuario_id = u.id
WHERE u.tipo = 'loja'
AND u.status = 'ativo'
ORDER BY u.id DESC
LIMIT 10;

-- ====================
-- USUÁRIOS DO TIPO FUNCIONÁRIO
-- ====================
SELECT
    '=== USUÁRIOS FUNCIONÁRIOS ===' as info;

SELECT
    u.id,
    u.nome,
    u.email,
    u.tipo,
    u.status,
    u.loja_vinculada_id,
    u.subtipo_funcionario,
    u.data_criacao,
    u.ultimo_login,
    l.nome_fantasia as nome_loja_vinculada
FROM usuarios u
LEFT JOIN lojas l ON l.id = u.loja_vinculada_id
WHERE u.tipo = 'funcionario'
AND u.status = 'ativo'
ORDER BY u.id DESC
LIMIT 10;

-- ====================
-- TODAS AS LOJAS APROVADAS
-- ====================
SELECT
    '=== LOJAS APROVADAS ===' as info;

SELECT
    l.id,
    l.nome_fantasia,
    l.email,
    l.categoria,
    l.porcentagem_cashback,
    l.usuario_id,
    l.status,
    u.nome as nome_responsavel,
    u.email as email_responsavel
FROM lojas l
LEFT JOIN usuarios u ON u.id = l.usuario_id
WHERE l.status = 'aprovado'
ORDER BY l.id DESC
LIMIT 10;

-- ====================
-- ESTATÍSTICAS GERAIS
-- ====================
SELECT
    '=== ESTATÍSTICAS ===' as info;

SELECT
    'Total de usuários ativos' as metrica,
    COUNT(*) as valor
FROM usuarios
WHERE status = 'ativo'
UNION ALL
SELECT
    'Usuários lojistas ativos' as metrica,
    COUNT(*) as valor
FROM usuarios
WHERE tipo = 'loja' AND status = 'ativo'
UNION ALL
SELECT
    'Usuários funcionários ativos' as metrica,
    COUNT(*) as valor
FROM usuarios
WHERE tipo = 'funcionario' AND status = 'ativo'
UNION ALL
SELECT
    'Lojas aprovadas' as metrica,
    COUNT(*) as valor
FROM lojas
WHERE status = 'aprovado'
UNION ALL
SELECT
    'Total de transações' as metrica,
    COUNT(*) as valor
FROM transacoes;

-- ====================
-- VERIFICAR SE HÁ USUÁRIOS SEM SENHA
-- ====================
SELECT
    '=== USUÁRIOS SEM SENHA (PRECISAM SER CORRIGIDOS) ===' as info;

SELECT
    id,
    nome,
    email,
    tipo,
    status
FROM usuarios
WHERE (senha_hash IS NULL OR senha_hash = '')
AND tipo IN ('loja', 'funcionario')
LIMIT 10;

-- ====================
-- LOJAS SEM USUÁRIO VINCULADO
-- ====================
SELECT
    '=== LOJAS SEM USUÁRIO VINCULADO ===' as info;

SELECT
    id,
    nome_fantasia,
    email,
    status
FROM lojas
WHERE usuario_id IS NULL OR usuario_id = 0
AND status = 'aprovado'
LIMIT 10;
