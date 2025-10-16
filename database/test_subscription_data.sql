-- Dados de Teste para Sistema de Assinaturas
-- Execute este SQL após seeds_planos.sql

-- 1. Criar uma loja de teste (se não existir)
INSERT INTO lojas (
    nome_fantasia,
    razao_social,
    email,
    senha_hash,
    cnpj,
    telefone,
    categoria,
    porcentagem_cashback,
    porcentagem_cliente,
    porcentagem_admin,
    cashback_ativo,
    status,
    data_cadastro
)
VALUES (
    'SyncHolding Teste',
    'SyncHolding Tecnologia LTDA',
    'teste@syncholding.com.br',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- senha: password
    '12345678000199',
    '11999999999',
    'Tecnologia',
    5.00,
    5.00,
    5.00,
    1,
    'aprovado',
    NOW()
)
ON DUPLICATE KEY UPDATE email = email;

-- Criar endereço da loja
INSERT INTO lojas_endereco (loja_id, cep, logradouro, numero, bairro, cidade, estado)
VALUES (
    (SELECT id FROM lojas WHERE email = 'teste@syncholding.com.br' LIMIT 1),
    '01234-567',
    'Rua Teste',
    '123',
    'Centro',
    'São Paulo',
    'SP'
)
ON DUPLICATE KEY UPDATE cep = cep;

-- Obter o ID da loja
SET @loja_id = (SELECT id FROM lojas WHERE email = 'teste@syncholding.com.br' LIMIT 1);

-- 2. Obter ID do plano Start
SET @plano_id = (SELECT id FROM planos WHERE slug = 'klube-start' LIMIT 1);

-- 3. Criar assinatura trial
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
VALUES (
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
)
ON DUPLICATE KEY UPDATE
    status = VALUES(status),
    updated_at = NOW();

-- Obter ID da assinatura
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
VALUES (
    @assinatura_id,
    CONCAT('INV-TEST-', UNIX_TIMESTAMP()),
    149.00,
    'BRL',
    'pending',
    DATE_ADD(CURDATE(), INTERVAL 7 DAY),
    'abacate',
    NOW(),
    NOW()
);

-- Exibir resultados
SELECT 'Loja criada/atualizada:' AS status;
SELECT id, nome_fantasia, email, status FROM lojas WHERE email = 'teste@syncholding.com.br';

SELECT 'Assinatura criada:' AS status;
SELECT a.id, a.status, a.ciclo, a.trial_end, p.nome AS plano_nome
FROM assinaturas a
JOIN planos p ON a.plano_id = p.id
WHERE a.loja_id = @loja_id;

SELECT 'Fatura criada para teste PIX:' AS status;
SELECT f.id, f.numero, f.amount, f.status, f.due_date
FROM faturas f
WHERE f.assinatura_id = @assinatura_id
ORDER BY f.id DESC
LIMIT 1;

-- Informações para uso no Postman
SELECT
    'USE ESTAS INFORMAÇÕES NO POSTMAN:' AS info,
    '' AS separador;

SELECT
    'LOGIN' AS endpoint,
    'teste@syncholding.com.br' AS email,
    'password' AS senha,
    'loja' AS tipo;

SELECT
    'CRIAR PIX' AS endpoint,
    f.id AS invoice_id,
    f.amount AS valor,
    f.numero AS numero_fatura
FROM faturas f
WHERE f.assinatura_id = @assinatura_id
ORDER BY f.id DESC
LIMIT 1;
