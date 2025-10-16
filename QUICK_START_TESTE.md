# Quick Start - Testar Sistema de Assinaturas

## ✅ Todas as correções foram aplicadas

1. ✅ Rotas criadas no .htaccess
2. ✅ Erro 500 corrigido em subscriptions.php
3. ✅ Link "Meu Plano" adicionado ao sidebar correto (sidebar-lojista-responsiva.php)

---

## 🚀 Passo a Passo para Testar AGORA

### Passo 1: Executar SQLs no Banco de Dados

Execute via phpMyAdmin ou MySQL Workbench:

#### 1. Criar os Planos (OBRIGATÓRIO)
```bash
database/seeds_planos.sql
```
Este SQL cria os 4 planos: Start, Plus, Pro, Enterprise.

#### 2. Criar Dados de Teste (escolha UMA opção)

**Opção A: Usar Loja ID 34 (Kaua Matheus) - RECOMENDADO** ⭐
```bash
database/test_subscription_loja34.sql
```
**Pronto para executar!** Não precisa editar nada.

Cria:
- Assinatura trial (7 dias) no plano Start
- Fatura pendente de R$ 149,00 para testar PIX
- Remove assinaturas antigas da loja 34 (se existirem)
- Usa a loja ID 34 existente (Kaua Matheus da Silva Lopes)

**Opção B: Criar nova loja de teste**
```bash
database/test_subscription_data.sql
```
Cria:
- Loja de teste: `teste@syncholding.com.br` / senha: `password`
- Endereço da loja (tabela lojas_endereco)
- Assinatura trial (7 dias) no plano Start
- Fatura pendente de R$ 149,00 para testar PIX

**Opção C: Usar outra loja existente**
```bash
database/test_subscription_existing_store.sql
```
**ANTES de executar**, abra o arquivo e altere a linha 11:
```sql
SET @loja_email = 'seuemail@syncholding.com.br';  -- <-- ALTERE AQUI
```
Coloque o email de uma loja que já existe no seu banco.

Este SQL cria:
- Assinatura trial para a loja escolhida
- Fatura pendente de R$ 149,00 para testar PIX

---

### Passo 2: Testar Interface Admin

1. Faça login como admin em: `https://klubecash.com/admin`
2. No menu lateral, clique em **"Assinaturas"**
3. Você deve ver a assinatura trial da "Loja Teste Assinatura"
4. Clique em **"Ver Detalhes"**
5. Verifique se a página de detalhes carrega sem erros

**O que verificar:**
- ✅ Página carrega sem erro 500
- ✅ Mostra informações da assinatura
- ✅ Mostra fatura pendente
- ✅ Botões de ação disponíveis

---

### Passo 3: Testar Interface Lojista

1. **Logout do admin**
2. Faça login como lojista:
   - Email: `loja.teste@klubecash.com`
   - Senha: `password`
   - Tipo: Loja
3. No menu lateral, procure o link **"Meu Plano"** (deve estar visível agora)
4. Clique em "Meu Plano"
5. Você deve ver:
   - Status: Trial
   - Plano: Klube Start
   - Dias restantes de trial
   - Fatura pendente de R$ 149,00

**O que verificar:**
- ✅ Link "Meu Plano" aparece no sidebar
- ✅ Página carrega sem erros
- ✅ Informações corretas da assinatura
- ✅ Botão "Pagar com PIX" disponível

---

### Passo 4: Testar Geração de PIX

#### Opção A: Via Interface (Mais Fácil)

1. Logado como lojista, em "Meu Plano"
2. Clique no botão **"Pagar com PIX"** da fatura pendente
3. Na página de pagamento, clique em **"Gerar PIX"**
4. Deve aparecer:
   - ✅ QR Code para escanear
   - ✅ Código "Copia e Cola"
   - ✅ Valor: R$ 149,00
   - ✅ Validade do PIX

#### Opção B: Via Postman (Para Devs)

Siga o arquivo `POSTMAN_TESTES.md` completo.

**Resumo rápido:**

**1. Login**
```
POST https://klubecash.com/controllers/AuthController.php?action=login
Body (JSON):
{
  "email": "loja.teste@klubecash.com",
  "senha": "password",
  "tipo": "loja"
}
```

**2. Obter ID da fatura**
```sql
SELECT id FROM faturas WHERE assinatura_id = (
    SELECT id FROM assinaturas WHERE loja_id = (
        SELECT id FROM lojas WHERE email = 'loja.teste@klubecash.com'
    )
) ORDER BY id DESC LIMIT 1;
```

**3. Criar PIX**
```
POST https://klubecash.com/api/abacatepay.php?action=create_invoice_pix
Headers:
  Content-Type: application/json
  Cookie: PHPSESSID=<sessao_do_login>
Body (JSON):
{
  "invoice_id": 1
}
```

---

### Passo 5: Verificar PIX no Banco

Após gerar o PIX, verificar se salvou:

```sql
SELECT
    f.id,
    f.invoice_number,
    f.amount,
    f.status,
    f.gateway_charge_id,
    f.pix_qr_code IS NOT NULL AS tem_qr_code,
    f.pix_copia_cola IS NOT NULL AS tem_copia_cola,
    f.pix_expires_at
FROM faturas f
WHERE f.id = 1; -- Substitua pelo ID da sua fatura
```

**Deve retornar:**
- ✅ `gateway_charge_id`: preenchido com ID da Abacate
- ✅ `tem_qr_code`: 1 (tem QR code)
- ✅ `tem_copia_cola`: 1 (tem copia e cola)
- ✅ `pix_expires_at`: data/hora de expiração

---

## 🎯 Checklist de Testes

### Interface Admin
- [ ] `/admin/assinaturas` carrega sem erro 500
- [ ] Lista de assinaturas aparece
- [ ] Filtros funcionam (status, busca)
- [ ] Detalhes da assinatura carregam
- [ ] Botão "Gerar Fatura Manual" funciona

### Interface Lojista
- [ ] Link "Meu Plano" aparece no sidebar
- [ ] Página "Meu Plano" carrega
- [ ] Status da assinatura correto
- [ ] Fatura pendente aparece
- [ ] Botão "Pagar com PIX" funciona

### Geração de PIX
- [ ] PIX é gerado sem erros
- [ ] QR Code aparece (imagem base64)
- [ ] Código "Copia e Cola" está preenchido
- [ ] Dados salvos no banco (gateway_charge_id, etc)

### Logs
- [ ] `logs/abacatepay.log` registra a chamada
- [ ] Sem erros no log

---

## 🐛 Se algo der errado

### Erro 500 ainda aparece
```bash
# Verificar log de erros do PHP
tail -f /var/log/apache2/error.log
# ou
tail -f /var/log/php/error.log
```

### PIX não gera
1. Verificar se `ABACATE_API_KEY` está definida em `config/constants.php`
2. Ver logs em `logs/abacatepay.log`
3. Testar conexão com Abacate Pay manualmente

### Sidebar não mostra "Meu Plano"
1. Fazer logout completo
2. Limpar cache do navegador
3. Fazer login novamente como lojista
4. Verificar se o arquivo correto está sendo incluído

---

## 📝 Próximos Passos (Após Testes)

1. **Configurar Webhook**: Registrar URL no painel Abacate Pay
2. **Testar Webhook**: Simular evento de pagamento
3. **Testar Renovação**: Executar cron de billing
4. **Testar Inadimplência**: Executar cron de dunning

---

## 📞 Comandos Úteis

### Ver logs em tempo real
```bash
tail -f logs/abacatepay.log
```

### Resetar dados de teste
```sql
DELETE FROM faturas WHERE assinatura_id IN (
    SELECT id FROM assinaturas WHERE loja_id = (
        SELECT id FROM lojas WHERE email = 'loja.teste@klubecash.com'
    )
);
DELETE FROM assinaturas WHERE loja_id = (
    SELECT id FROM lojas WHERE email = 'loja.teste@klubecash.com'
);
DELETE FROM lojas WHERE email = 'loja.teste@klubecash.com';
```

Depois executar `test_subscription_data.sql` novamente.

---

**Pronto para começar! 🚀**

Execute os SQLs e comece testando pela interface admin primeiro.
