-- Dados de Teste para Sistema de Assinaturas
-- Use este SQL se você JÁ TEM uma loja cadastrada e quer adicionar assinatura a ela

-- INSTRUÇÕES:
-- 1. Substitua o email abaixo pelo email da loja que você quer usar
-- 2. Execute este SQL no phpMyAdmin

-- ========================================
-- CONFIGURAÇÃO: Altere o email aqui
-- ========================================
SET @loja_email = 'seuemail@syncholding.com.br';  -- <-- ALTERE AQUI
-- ========================================

-- Verificar se a loja existe
SELECT
    CASE
        WHEN COUNT(*) > 0 THEN CONCAT('✅ Loja encontrada: ', nome_fantasia)
        ELSE '❌ ERRO: Loja não encontrada! Verifique o email.'
    END AS resultado
FROM lojas
WHERE email = @loja_email;

-- Obter ID da loja
SET @loja_id = (SELECT id FROM lojas WHERE email = @loja_email LIMIT 1);

-- Verificar se encontrou a loja
SELECT
    CASE
        WHEN @loja_id IS NULL THEN 'ABORTANDO: Loja não encontrada!'
        ELSE CONCAT('Loja ID: ', @loja_id)
    END AS status;

-- Se não encontrou, parar aqui (o usuário verá a mensagem acima)

-- 1. Obter ID do plano Start
SET @plano_id = (SELECT id FROM planos WHERE slug = 'klube-start' LIMIT 1);

-- Verificar se o plano existe
SELECT
    CASE
        WHEN @plano_id IS NULL THEN '❌ ERRO: Plano Start não encontrado! Execute seeds_planos.sql primeiro.'
        ELSE CONCAT('✅ Plano Start encontrado: ', (SELECT nome FROM planos WHERE id = @plano_id))
    END AS resultado;

-- 2. Criar assinatura trial (apenas se loja E plano existem)
INSERT INTO assinaturas (
    tipo,
    loja_id,
    plano_id,
    status,
    ciclo,
    trial_end,
    current_period_start,
    current_period_end,
    next_invoice_date,
    gateway,
    created_at,
    updated_at
)
SELECT
    'loja',
    @loja_id,
    @plano_id,
    'trial',
    'monthly',
    DATE_ADD(CURDATE(), INTERVAL 7 DAY),
    CURDATE(),
    DATE_ADD(CURDATE(), INTERVAL 1 MONTH),
    DATE_ADD(CURDATE(), INTERVAL 7 DAY),
    'abacate',
    NOW(),
    NOW()
FROM DUAL
WHERE @loja_id IS NOT NULL AND @plano_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    status = VALUES(status),
    updated_at = NOW();

-- Obter ID da assinatura criada
SET @assinatura_id = (SELECT id FROM assinaturas WHERE loja_id = @loja_id LIMIT 1);

-- 3. Criar fatura pendente para teste de pagamento PIX
INSERT INTO faturas (
    assinatura_id,
    numero,
    amount,
    currency,
    status,
    due_date,
    gateway,
    created_at,
    updated_at
)
SELECT
    @assinatura_id,
    CONCAT('INV-TEST-', UNIX_TIMESTAMP()),
    149.00,
    'BRL',
    'pending',
    DATE_ADD(CURDATE(), INTERVAL 7 DAY),
    'abacate',
    NOW(),
    NOW()
FROM DUAL
WHERE @assinatura_id IS NOT NULL;

-- ========================================
-- RESULTADOS
-- ========================================

SELECT '========================================' AS '';
SELECT '✅ ASSINATURA CRIADA COM SUCESSO!' AS '';
SELECT '========================================' AS '';

SELECT 'DADOS DA LOJA:' AS info;
SELECT
    l.id,
    l.nome_fantasia,
    l.email,
    l.cnpj,
    l.status
FROM lojas l
WHERE l.id = @loja_id;

SELECT '' AS '';
SELECT 'ASSINATURA CRIADA:' AS info;
SELECT
    a.id AS assinatura_id,
    a.status,
    a.ciclo,
    DATE_FORMAT(a.trial_end, '%d/%m/%Y') AS fim_trial,
    p.nome AS plano_nome,
    CONCAT('R$ ', FORMAT(p.preco_mensal, 2, 'pt_BR')) AS preco
FROM assinaturas a
JOIN planos p ON a.plano_id = p.id
WHERE a.id = @assinatura_id;

SELECT '' AS '';
SELECT 'FATURA PENDENTE PARA TESTE:' AS info;
SELECT
    f.id AS fatura_id,
    f.numero AS numero_fatura,
    CONCAT('R$ ', FORMAT(f.amount, 2, 'pt_BR')) AS valor,
    f.status,
    DATE_FORMAT(f.due_date, '%d/%m/%Y') AS vencimento
FROM faturas f
WHERE f.assinatura_id = @assinatura_id
ORDER BY f.id DESC
LIMIT 1;

SELECT '' AS '';
SELECT '========================================' AS '';
SELECT 'USE ESTAS INFORMAÇÕES PARA LOGIN:' AS '';
SELECT '========================================' AS '';

SELECT
    email AS 'Email',
    'password ou a senha da loja' AS 'Senha',
    'loja' AS 'Tipo'
FROM lojas
WHERE id = @loja_id;

SELECT '' AS '';
SELECT 'PRÓXIMOS PASSOS:' AS '';
SELECT '1. Faça login como lojista com o email acima' AS '';
SELECT '2. Acesse o menu "Meu Plano"' AS '';
SELECT '3. Clique em "Pagar com PIX" na fatura pendente' AS '';
