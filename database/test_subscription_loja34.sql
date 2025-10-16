-- Dados de Teste para Sistema de Assinaturas
-- LOJA ID 34: Kaua Matheus da Silva Lopes

-- ========================================
-- CONFIGURAÇÃO FIXA - LOJA ID 34
-- ========================================
SET @loja_id = 34;
-- ========================================

-- Verificar dados da loja
SELECT '========================================' AS '';
SELECT 'VERIFICANDO LOJA ID 34' AS '';
SELECT '========================================' AS '';

SELECT
    id,
    nome_fantasia,
    razao_social,
    email,
    cnpj,
    telefone,
    status
FROM lojas
WHERE id = @loja_id;

-- Verificar se a loja existe
SET @loja_existe = (SELECT COUNT(*) FROM lojas WHERE id = @loja_id);

SELECT
    CASE
        WHEN @loja_existe > 0 THEN '✅ Loja ID 34 encontrada!'
        ELSE '❌ ERRO: Loja ID 34 não encontrada!'
    END AS resultado;

-- 1. Obter ID do plano Start
SET @plano_id = (SELECT id FROM planos WHERE slug = 'klube-start' LIMIT 1);

-- Verificar se o plano existe
SELECT
    CASE
        WHEN @plano_id IS NULL THEN '❌ ERRO: Plano Start não encontrado! Execute seeds_planos.sql primeiro.'
        ELSE CONCAT('✅ Plano Start encontrado (ID: ', @plano_id, ')')
    END AS resultado;

-- 2. Deletar assinatura e faturas antigas da loja 34 (se existirem)
DELETE FROM faturas WHERE assinatura_id IN (
    SELECT id FROM assinaturas WHERE loja_id = @loja_id
);

DELETE FROM assinaturas WHERE loja_id = @loja_id;

SELECT '✅ Assinaturas e faturas antigas removidas (se existiam)' AS status;

-- 3. Criar nova assinatura trial para a loja 34
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
WHERE @loja_id IS NOT NULL AND @plano_id IS NOT NULL;

-- Obter ID da assinatura criada
SET @assinatura_id = (SELECT id FROM assinaturas WHERE loja_id = @loja_id LIMIT 1);

-- 4. Criar fatura pendente para teste de pagamento PIX
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
    CONCAT('INV-LOJA34-', UNIX_TIMESTAMP()),
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

SELECT '' AS '';
SELECT '========================================' AS '';
SELECT '✅ ASSINATURA CRIADA PARA LOJA ID 34!' AS '';
SELECT '========================================' AS '';

SELECT '' AS '';
SELECT 'DADOS DA LOJA:' AS info;
SELECT
    l.id AS loja_id,
    l.nome_fantasia,
    l.razao_social,
    l.email,
    l.cnpj,
    l.telefone,
    l.status
FROM lojas l
WHERE l.id = @loja_id;

SELECT '' AS '';
SELECT 'ENDEREÇO DA LOJA:' AS info;
SELECT
    e.cep,
    e.logradouro,
    e.numero,
    e.complemento,
    e.bairro,
    e.cidade,
    e.estado
FROM lojas_endereco e
WHERE e.loja_id = @loja_id;

SELECT '' AS '';
SELECT 'ASSINATURA CRIADA:' AS info;
SELECT
    a.id AS assinatura_id,
    a.status,
    a.ciclo,
    DATE_FORMAT(a.trial_end, '%d/%m/%Y') AS fim_trial,
    DATE_FORMAT(a.current_period_start, '%d/%m/%Y') AS periodo_inicio,
    DATE_FORMAT(a.current_period_end, '%d/%m/%Y') AS periodo_fim,
    DATE_FORMAT(a.next_invoice_date, '%d/%m/%Y') AS proxima_fatura,
    p.nome AS plano_nome,
    CONCAT('R$ ', FORMAT(p.preco_mensal, 2, 'pt_BR')) AS preco
FROM assinaturas a
JOIN planos p ON a.plano_id = p.id
WHERE a.id = @assinatura_id;

SELECT '' AS '';
SELECT 'FATURA PENDENTE PARA TESTE PIX:' AS info;
SELECT
    f.id AS fatura_id,
    f.numero AS numero_fatura,
    CONCAT('R$ ', FORMAT(f.amount, 2, 'pt_BR')) AS valor,
    f.status,
    DATE_FORMAT(f.due_date, '%d/%m/%Y') AS vencimento,
    f.gateway
FROM faturas f
WHERE f.assinatura_id = @assinatura_id
ORDER BY f.id DESC
LIMIT 1;

SELECT '' AS '';
SELECT '========================================' AS '';
SELECT 'INFORMAÇÕES PARA TESTES:' AS '';
SELECT '========================================' AS '';

SELECT '' AS '';
SELECT 'USE ESTAS INFORMAÇÕES NO POSTMAN:' AS info;

SELECT
    'LOGIN' AS endpoint,
    l.email AS email,
    'use a senha cadastrada da loja' AS senha,
    'loja' AS tipo
FROM lojas l
WHERE l.id = @loja_id;

SELECT '' AS '';
SELECT 'CRIAR PIX (use a fatura_id acima):' AS info;
SELECT
    'POST /api/abacatepay.php?action=create_invoice_pix' AS endpoint,
    f.id AS invoice_id,
    CONCAT('R$ ', FORMAT(f.amount, 2, 'pt_BR')) AS valor,
    f.numero AS numero_fatura
FROM faturas f
WHERE f.assinatura_id = @assinatura_id
ORDER BY f.id DESC
LIMIT 1;

SELECT '' AS '';
SELECT '========================================' AS '';
SELECT 'PRÓXIMOS PASSOS:' AS '';
SELECT '========================================' AS '';
SELECT '1. Faça login com o email da loja ID 34' AS '';
SELECT '2. Acesse o menu "Meu Plano"' AS '';
SELECT '3. Verifique status: Trial (7 dias)' AS '';
SELECT '4. Clique em "Pagar com PIX" na fatura pendente' AS '';
SELECT '5. Teste geração do QR Code PIX' AS '';
