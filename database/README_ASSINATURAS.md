# Scripts SQL - Sistema de Assinaturas

Este diretório contém os scripts SQL necessários para configurar e testar o sistema de assinaturas.

## 📋 Arquivos Disponíveis

### 1. `seeds_planos.sql` ⭐ OBRIGATÓRIO
**Executar primeiro!**

Cria os 4 planos de assinatura no banco de dados:

| Plano | Preço Mensal | Features |
|-------|--------------|----------|
| **Klube Start** | R$ 149,00 | 3 funcionários, 1000 clientes, suporte email |
| **Klube Plus** | R$ 299,00 | 10 funcionários, 5000 clientes, acesso API, suporte prioritário |
| **Klube Pro** | R$ 549,00 | 50 funcionários, 25000 clientes, análises avançadas, onboarding |
| **Klube Enterprise** | R$ 999,00 | Funcionários ilimitados, clientes ilimitados, suporte 24/7, gerente dedicado |

**Importante:**
- Usa `ON DUPLICATE KEY UPDATE` para ser idempotente (pode executar várias vezes sem duplicar)
- Identifica os planos pelo campo `slug` (único)

### 2. `test_subscription_data.sql`
**Para testes com NOVA LOJA**

Cria uma loja de teste completa do zero + assinatura + fatura.

**O que cria:**
- ✅ Loja de teste na tabela `lojas`
  - Email: `loja.teste@klubecash.com`
  - Senha: `password`
  - CNPJ: 12345678000199
  - Status: aprovado
- ✅ Endereço da loja na tabela `lojas_endereco`
- ✅ Assinatura trial (7 dias) no plano Start
- ✅ Fatura pendente de R$ 149,00 para testar PIX

**Quando usar:**
- Quando você quer testar sem afetar dados reais
- Quando não tem lojas cadastradas ainda
- Para ambiente de desenvolvimento/staging

**Campos da tabela lojas usados:**
```sql
nome_fantasia, razao_social, email, senha_hash, cnpj, telefone,
categoria, porcentagem_cashback, porcentagem_cliente, porcentagem_admin,
cashback_ativo, status, data_cadastro
```

### 3. `test_subscription_loja34.sql` ⭐ RECOMENDADO
**Para testes com LOJA ID 34 (Kaua Matheus da Silva Lopes)**

**Pronto para executar!** Não precisa editar nada.

Adiciona assinatura + fatura à loja ID 34 existente no banco.

**O que faz:**
- ✅ Remove assinaturas antigas da loja 34 (se existirem)
- ✅ Cria assinatura trial (7 dias) no plano Start
- ✅ Cria fatura pendente de R$ 149,00 para testar PIX
- ✅ Exibe todos os dados da loja (nome, email, endereço)
- ✅ Exibe informações completas para login e teste

**Quando usar:**
- **Primeira opção para testes!**
- Quando você quer testar rápido sem configurar nada
- Usa loja real já cadastrada no sistema

**Vantagens:**
- ⚡ Não precisa editar nem configurar
- ⚡ Remove dados antigos automaticamente
- ⚡ Mostra todas informações necessárias para teste
- ⚡ Validações incluídas (verifica se loja e plano existem)

### 4. `test_subscription_data.sql`
**Para testes com NOVA LOJA**

Cria uma loja de teste completa do zero + assinatura + fatura.

**O que cria:**
- ✅ Loja de teste na tabela `lojas`
  - Email: `teste@syncholding.com.br`
  - Senha: `password`
  - CNPJ: 12345678000199
  - Status: aprovado
- ✅ Endereço da loja na tabela `lojas_endereco`
- ✅ Assinatura trial (7 dias) no plano Start
- ✅ Fatura pendente de R$ 149,00 para testar PIX

**Quando usar:**
- Quando você quer testar sem afetar dados reais
- Quando não tem lojas cadastradas ainda
- Para ambiente de desenvolvimento/staging

### 5. `test_subscription_existing_store.sql`
**Para testes com OUTRA LOJA EXISTENTE**

Adiciona assinatura + fatura a uma loja que já existe no banco.

**O que cria:**
- ✅ Assinatura trial (7 dias) no plano Start para a loja escolhida
- ✅ Fatura pendente de R$ 149,00 para testar PIX

**IMPORTANTE - Configuração necessária:**
```sql
-- Abra o arquivo e altere a linha 11:
SET @loja_email = 'seuemail@syncholding.com.br';  -- <-- ALTERE AQUI
```

**Quando usar:**
- Quando você já tem lojas cadastradas
- Para testar com dados reais (ambiente de produção)
- Para adicionar assinatura a uma loja específica que não seja a 34

**Validações incluídas:**
- ✅ Verifica se a loja existe (por email)
- ✅ Verifica se o plano Start existe
- ✅ Exibe mensagens claras de erro se algo não for encontrado
- ✅ Só cria dados se todas as validações passarem

### 6. `u383946504_klubecash.sql`
Dump completo do banco de dados (estrutura + dados).

---

## 🚀 Ordem de Execução

### Primeira vez (Setup inicial):

```bash
# 1. OBRIGATÓRIO - Criar os planos
database/seeds_planos.sql

# 2. Escolha UMA opção:

# Opção A: Loja ID 34 (RECOMENDADO) ⭐
database/test_subscription_loja34.sql

# Opção B: Nova loja de teste
database/test_subscription_data.sql

# Opção C: Outra loja existente (lembre de editar o email primeiro!)
database/test_subscription_existing_store.sql
```

### Se quiser resetar os dados de teste:

**Para Loja ID 34:**
```sql
-- Deletar dados de teste da loja 34
DELETE FROM faturas WHERE assinatura_id IN (
    SELECT id FROM assinaturas WHERE loja_id = 34
);

DELETE FROM assinaturas WHERE loja_id = 34;
```
Depois execute `test_subscription_loja34.sql` novamente.

**Para loja de teste syncholding:**
```sql
-- Deletar dados de teste da loja teste
DELETE FROM faturas WHERE assinatura_id IN (
    SELECT id FROM assinaturas WHERE loja_id = (
        SELECT id FROM lojas WHERE email = 'teste@syncholding.com.br'
    )
);

DELETE FROM assinaturas WHERE loja_id = (
    SELECT id FROM lojas WHERE email = 'teste@syncholding.com.br'
);

DELETE FROM lojas_endereco WHERE loja_id = (
    SELECT id FROM lojas WHERE email = 'teste@syncholding.com.br'
);

DELETE FROM lojas WHERE email = 'teste@syncholding.com.br';
```
Depois execute `test_subscription_data.sql` novamente.

---

## 🔍 Verificar o que foi criado

### Ver os planos:
```sql
SELECT id, nome, slug, preco_mensal, ciclo, features
FROM planos
ORDER BY preco_mensal;
```

### Ver assinaturas criadas:
```sql
SELECT
    a.id,
    l.nome_fantasia AS loja,
    l.email,
    p.nome AS plano,
    a.status,
    a.trial_end,
    a.next_invoice_date
FROM assinaturas a
JOIN lojas l ON a.loja_id = l.id
JOIN planos p ON a.plano_id = p.id
ORDER BY a.id DESC;
```

### Ver faturas pendentes:
```sql
SELECT
    f.id,
    f.invoice_number,
    l.nome_fantasia AS loja,
    f.amount,
    f.status,
    f.due_date,
    f.gateway_charge_id IS NOT NULL AS pix_gerado
FROM faturas f
JOIN assinaturas a ON f.assinatura_id = a.id
JOIN lojas l ON a.loja_id = l.id
WHERE f.status = 'pending'
ORDER BY f.id DESC;
```

---

## ⚠️ Troubleshooting

### Erro: "Coluna 'nome_loja' desconhecida"
**Causa:** Você está usando `test_subscription_data.sql` de uma versão antiga.

**Solução:** Baixe a versão atualizada que usa os campos corretos:
- `nome_fantasia` e `razao_social` (não `nome_loja`)
- `senha_hash` (não `senha`)

### Erro: "Plano Start não encontrado"
**Causa:** Você não executou `seeds_planos.sql` primeiro.

**Solução:** Execute `seeds_planos.sql` antes dos scripts de teste.

### Erro: "Loja não encontrada" (no script existing_store)
**Causa:** O email configurado não existe no banco.

**Solução:**
1. Liste lojas existentes:
```sql
SELECT id, nome_fantasia, email FROM lojas LIMIT 10;
```
2. Copie um email real da lista
3. Edite `test_subscription_existing_store.sql` na linha 11
4. Execute novamente

---

## 📊 Estrutura das Tabelas

### `planos`
```sql
id, nome, slug, descricao, preco_mensal, preco_anual, ciclo,
trial_days, features (JSON), is_active, created_at, updated_at
```

### `assinaturas`
```sql
id, tipo, usuario_id, loja_id, plano_id, status, ciclo, trial_end,
current_period_start, current_period_end, next_invoice_date,
gateway, gateway_subscription_id, canceled_at, cancel_reason,
created_at, updated_at
```

### `faturas`
```sql
id, assinatura_id, invoice_number, amount, currency, status, due_date,
paid_at, gateway, gateway_charge_id, pix_qr_code, pix_copia_cola,
pix_expires_at, failure_reason, attempt_count, created_at, updated_at
```

---

## 🎯 Próximos Passos Após Executar os SQLs

1. ✅ **Testar Admin Interface**
   - Acesse: `https://klubecash.com/admin/assinaturas`
   - Verifique se a assinatura aparece

2. ✅ **Testar Loja Interface**
   - Login: `loja.teste@klubecash.com` / `password`
   - Menu: "Meu Plano"
   - Verifique status trial

3. ✅ **Testar Geração de PIX**
   - Clique em "Pagar com PIX" na fatura
   - Verifique se QR Code é gerado
   - Confira se dados salvam no banco

4. ✅ **Configurar Webhook** (depois dos testes)
   - Registrar URL no painel Abacate Pay
   - Atualizar `ABACATE_WEBHOOK_SECRET` em `config/constants.php`

Veja `QUICK_START_TESTE.md` para guia completo!

---

**Dúvidas?** Verifique os logs em:
- `logs/abacatepay.log` - Logs das chamadas à API
- Apache/PHP error logs - Erros de execução
