# Testes com Loja ID 34 (Kaua Matheus da Silva Lopes)

## ✅ Tudo Pronto para Testar!

Este guia usa a **Loja ID 34** para todos os testes do sistema de assinaturas.

---

## 🚀 Quick Start (2 passos)

### Passo 1: Executar SQLs
Execute estes 2 arquivos no phpMyAdmin, nesta ordem:

```sql
1. database/seeds_planos.sql
2. database/test_subscription_loja34.sql
```

**Pronto!** Não precisa editar nem configurar nada.

### Passo 2: Testar
Acesse o sistema e faça login com a conta da Loja ID 34, depois clique em "Meu Plano".

---

## 📋 O que o SQL cria

Quando você executar `test_subscription_loja34.sql`:

✅ **Remove dados antigos** (se existirem)
- Deleta assinaturas antigas da loja 34
- Deleta faturas antigas da loja 34

✅ **Cria novos dados de teste**
- Assinatura trial de 7 dias no plano **Klube Start** (R$ 149/mês)
- Status: `trial`
- Validade: 7 dias a partir de hoje
- Próxima fatura: daqui a 7 dias

✅ **Cria fatura para teste PIX**
- Valor: R$ 149,00
- Status: `pending` (aguardando pagamento)
- Vencimento: daqui a 7 dias
- Gateway: Abacate Pay

✅ **Exibe todas as informações**
- Dados da loja (nome, email, CNPJ, telefone)
- Endereço completo
- Detalhes da assinatura
- Detalhes da fatura
- Credenciais para login
- ID da fatura para usar no Postman

---

## 🎯 Cenários de Teste

### 1. Testar Interface Admin

**URL:** `https://klubecash.com/admin/assinaturas`

**O que verificar:**
- ✅ Página carrega sem erro 500
- ✅ Lista mostra assinatura da Loja ID 34
- ✅ Status: Trial
- ✅ Plano: Klube Start
- ✅ Próxima fatura em 7 dias
- ✅ Botão "Ver Detalhes" funciona

**Detalhes da assinatura:**
- ✅ Mostra informações completas
- ✅ Mostra fatura pendente de R$ 149,00
- ✅ Botão "Gerar Fatura Manual" disponível

---

### 2. Testar Interface Lojista

**Login:** Use as credenciais da Loja ID 34

**Menu:** Procure o link **"Meu Plano"** no sidebar

**O que verificar:**

✅ **Página "Meu Plano"**
- Status: Trial
- Plano: Klube Start (R$ 149,00/mês)
- Dias restantes de trial: ~7 dias
- Fatura pendente aparece

✅ **Card da Fatura**
- Número da fatura: INV-LOJA34-...
- Valor: R$ 149,00
- Status: Pendente
- Vencimento: (data daqui a 7 dias)
- Botão "Pagar com PIX" disponível

---

### 3. Testar Geração de PIX

#### Opção A: Via Interface (Recomendado)

1. **Login** como lojista (Loja ID 34)
2. **Menu** → "Meu Plano"
3. **Fatura pendente** → Clique em "Pagar com PIX"
4. **Página PIX** → Clique em "Gerar PIX"

**O que deve acontecer:**
- ✅ Requisição POST para `/api/abacatepay.php`
- ✅ Retorna JSON com `qr_code_base64`, `copia_cola`, `expires_at`
- ✅ QR Code aparece na tela (imagem base64)
- ✅ Botão "Copiar Código" funciona
- ✅ Dados salvos no banco (`gateway_charge_id`, `pix_qr_code`, etc)

**Verificar no banco:**
```sql
SELECT
    id,
    invoice_number,
    amount,
    status,
    gateway_charge_id,
    pix_qr_code IS NOT NULL AS tem_qr,
    pix_copia_cola IS NOT NULL AS tem_copia,
    pix_expires_at
FROM faturas
WHERE assinatura_id = (
    SELECT id FROM assinaturas WHERE loja_id = 34
)
ORDER BY id DESC
LIMIT 1;
```

Deve mostrar:
- `gateway_charge_id`: ID retornado pela Abacate Pay
- `tem_qr`: 1
- `tem_copia`: 1
- `pix_expires_at`: data/hora válida

---

#### Opção B: Via Postman (Para Devs)

**1. Login**
```
POST https://klubecash.com/controllers/AuthController.php?action=login

Headers:
  Content-Type: application/json

Body (JSON):
{
  "email": "email_da_loja34@exemplo.com",
  "senha": "senha_da_loja_34",
  "tipo": "loja"
}
```

**Salve o cookie `PHPSESSID` da resposta!**

**2. Obter ID da fatura**

Execute o SQL `test_subscription_loja34.sql` e veja o output na seção "INFORMAÇÕES PARA TESTES". Você verá algo como:

```
CRIAR PIX (use a fatura_id acima):
endpoint: POST /api/abacatepay.php?action=create_invoice_pix
invoice_id: 123
valor: R$ 149,00
numero_fatura: INV-LOJA34-1234567890
```

**3. Criar PIX**
```
POST https://klubecash.com/api/abacatepay.php?action=create_invoice_pix

Headers:
  Content-Type: application/json
  Cookie: PHPSESSID=<sessao_do_login>

Body (JSON):
{
  "invoice_id": 123
}
```

**Resposta esperada:**
```json
{
  "success": true,
  "data": {
    "qr_code_base64": "data:image/png;base64,iVBORw0KG...",
    "copia_cola": "00020126580014br.gov.bcb.pix...",
    "expires_at": "2025-10-16 18:30:00",
    "amount": 149.00
  }
}
```

---

### 4. Testar Webhook (Simulação)

**Arquivo:** `api/abacatepay-webhook.php`

**Evento:** Pagamento confirmado

**Como testar:**

1. Pegue o `gateway_charge_id` da fatura (do banco)
2. Calcule o HMAC SHA256 do payload
3. Envie POST para `/api/abacatepay-webhook`

**Payload de exemplo:**
```json
{
  "event": "charge.paid",
  "data": {
    "id": "chr_abc123",
    "status": "paid",
    "amount": 14900,
    "externalId": "INV-LOJA34-1234567890",
    "paidAt": 1697475600
  }
}
```

**Header:**
```
X-Abacate-Signature: <hmac_sha256_do_payload>
```

**O que deve acontecer:**
- ✅ Webhook valida assinatura HMAC
- ✅ Verifica idempotência (não processa duplicados)
- ✅ Atualiza fatura para status `paid`
- ✅ Avança período da assinatura
- ✅ Status muda de `trial` para `active`
- ✅ Próxima fatura agendada para daqui a 1 mês

**Verificar no banco:**
```sql
SELECT
    a.id,
    a.status,
    a.current_period_start,
    a.current_period_end,
    a.next_invoice_date,
    f.status AS fatura_status,
    f.paid_at
FROM assinaturas a
LEFT JOIN faturas f ON f.assinatura_id = a.id
WHERE a.loja_id = 34
ORDER BY f.id DESC;
```

---

## 📊 Checklist Completo

### Setup
- [ ] Executar `seeds_planos.sql`
- [ ] Executar `test_subscription_loja34.sql`
- [ ] Verificar output do SQL (sem erros)

### Admin Interface
- [ ] `/admin/assinaturas` carrega
- [ ] Assinatura da Loja 34 aparece
- [ ] Status: Trial
- [ ] Plano: Klube Start
- [ ] Detalhes carregam
- [ ] Fatura pendente visível

### Lojista Interface
- [ ] Login com credenciais da Loja 34
- [ ] Link "Meu Plano" aparece no sidebar
- [ ] Página "Meu Plano" carrega
- [ ] Status trial correto
- [ ] Dias restantes mostrados
- [ ] Fatura pendente aparece
- [ ] Botão "Pagar com PIX" visível

### Geração PIX
- [ ] Clique em "Pagar com PIX"
- [ ] Página de pagamento carrega
- [ ] Botão "Gerar PIX" funciona
- [ ] QR Code aparece
- [ ] Código "Copia e Cola" disponível
- [ ] Botão "Copiar" funciona
- [ ] Dados salvos no banco

### Webhook (Opcional)
- [ ] Simular evento `charge.paid`
- [ ] Fatura atualiza para `paid`
- [ ] Assinatura muda para `active`
- [ ] Período avança 1 mês
- [ ] Próxima fatura agendada

---

## 🔧 Comandos Úteis

### Ver logs em tempo real
```bash
tail -f logs/abacatepay.log
```

### Verificar assinatura da Loja 34
```sql
SELECT
    a.*,
    p.nome AS plano_nome,
    p.preco_mensal
FROM assinaturas a
JOIN planos p ON a.plano_id = p.id
WHERE a.loja_id = 34;
```

### Ver faturas da Loja 34
```sql
SELECT
    f.*
FROM faturas f
JOIN assinaturas a ON f.assinatura_id = a.id
WHERE a.loja_id = 34
ORDER BY f.created_at DESC;
```

### Resetar testes (limpar dados)
```sql
DELETE FROM faturas WHERE assinatura_id IN (
    SELECT id FROM assinaturas WHERE loja_id = 34
);

DELETE FROM assinaturas WHERE loja_id = 34;
```

Depois execute `test_subscription_loja34.sql` novamente.

---

## 🐛 Troubleshooting

### Erro: "Loja ID 34 não encontrada"
**Causa:** A loja não existe no banco.

**Solução:** Verifique se a loja existe:
```sql
SELECT id, nome_fantasia, email FROM lojas WHERE id = 34;
```

Se não existir, use `test_subscription_data.sql` (cria loja nova) ou `test_subscription_existing_store.sql` (escolhe outra loja).

### Erro: "Plano Start não encontrado"
**Causa:** Você não executou `seeds_planos.sql`.

**Solução:** Execute `seeds_planos.sql` primeiro.

### Erro 500 na geração de PIX
**Causas possíveis:**
1. `ABACATE_API_KEY` não configurada em `config/constants.php`
2. API da Abacate Pay offline/inacessível
3. Sessão expirada (não está logado)

**Solução:**
1. Verificar `config/constants.php` linha 299-302
2. Ver logs em `logs/abacatepay.log`
3. Fazer logout e login novamente

### Link "Meu Plano" não aparece
**Causa:** Sidebar errado ou cache.

**Solução:**
1. Fazer logout completo
2. Limpar cache do navegador (Ctrl+Shift+Delete)
3. Fazer login novamente
4. Verificar se está usando `sidebar-lojista-responsiva.php`

---

## ✅ Tudo Pronto!

Execute os 2 SQLs e comece a testar. Qualquer problema, veja o Troubleshooting acima ou os arquivos:
- `QUICK_START_TESTE.md` - Guia completo de testes
- `database/README_ASSINATURAS.md` - Documentação dos SQLs
- `POSTMAN_TESTES.md` - Testes via Postman

**Boa sorte com os testes! 🚀**
