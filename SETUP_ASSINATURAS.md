# Sistema de Assinaturas - Klube Cash

## ✅ Implementação Completa

Sistema completo de assinaturas com planos (Start/Plus/Pro/Enterprise), pagamento PIX via Abacate Pay, webhooks e renovação automática.

---

## 📦 Arquivos Criados/Modificados

### Backend Core
- ✅ `config/constants.php` - Configurações Abacate Pay
- ✅ `utils/AbacatePayClient.php` - Cliente HTTP PIX
- ✅ `controllers/SubscriptionController.php` - Lógica de negócio
- ✅ `utils/FeatureGate.php` - Controle de acesso por features

### APIs
- ✅ `api/abacatepay.php` - Criar PIX e consultar status
- ✅ `api/abacatepay-webhook.php` - Processar eventos

### Views Admin
- ✅ `views/admin/subscriptions.php` - Listagem de assinaturas
- ✅ `views/admin/store-subscription.php` - Detalhes + ações
- ✅ `views/components/sidebar.php` - Link "Assinaturas" adicionado

### Views Loja
- ✅ `views/stores/subscription.php` - Status do plano
- ✅ `views/stores/invoice-pix.php` - Pagamento PIX
- ✅ `views/components/sidebar-store.php` - Link "Meu Plano" adicionado

### Automação
- ✅ `scripts/cron/billing.php` - Gerar faturas automaticamente
- ✅ `scripts/cron/dunning.php` - Marcar inadimplentes

### Database
- ✅ `database/seeds_planos.sql` - Seeds dos 4 planos

---

## 🚀 Configuração Inicial

### 1. Executar Seeds dos Planos

```bash
mysql -u seu_usuario -p u383946504_klubecash < database/seeds_planos.sql
```

Ou executar manualmente no phpMyAdmin/MySQL Workbench.

### 2. Configurar Credenciais Abacate Pay

Editar `config/constants.php`:

```php
define('ABACATE_API_KEY', 'SUA_CHAVE_AQUI'); // Obtida no painel Abacate Pay
define('ABACATE_WEBHOOK_SECRET', 'SEU_SECRET_AQUI'); // Gerado no passo 3
```

### 3. Configurar Webhook no Abacate Pay

1. Acesse o painel: https://painel.abacatepay.com (ou URL do painel)
2. Vá em **Desenvolvedores → Webhooks**
3. Clique em **Adicionar Endpoint**
4. Configure:
   - **URL**: `https://klubecash.com/api/abacatepay-webhook`
   - **Eventos**: Selecione:
     - `charge.created`
     - `charge.paid`
     - `charge.expired`
     - `charge.failed`
5. Clique em **Gerar Secret** e copie o valor
6. Cole o secret em `ABACATE_WEBHOOK_SECRET` no constants.php
7. Ative o webhook

### 4. Configurar Crons (Opcional mas Recomendado)

Adicionar no crontab do servidor:

```bash
# Gerar faturas (diariamente à meia-noite)
0 0 * * * php /caminho/completo/klubecash1/scripts/cron/billing.php

# Marcar inadimplentes (diariamente às 6h)
0 6 * * * php /caminho/completo/klubecash1/scripts/cron/dunning.php
```

### 5. Criar Diretório de Logs

```bash
mkdir -p logs
chmod 777 logs
```

---

## 📋 Fluxo de Uso

### Admin: Atribuir Plano a uma Loja

1. Acessar **Admin → Assinaturas**
2. Clicar em uma assinatura ou loja
3. Escolher plano (Start/Plus/Pro/Enterprise)
4. Definir ciclo (mensal/anual) e trial (opcional)
5. Clicar em **Aplicar Plano**

### Admin: Gerar Fatura Manual

1. Acessar detalhes da assinatura
2. Clicar em **Gerar Fatura Manual**
3. Sistema cria fatura pendente

### Lojista: Ver Status da Assinatura

1. Acessar **Meu Plano** no menu
2. Visualizar:
   - Plano atual
   - Status (trial/ativa/inadimplente)
   - Dias restantes de trial
   - Próxima cobrança
   - Features disponíveis

### Lojista: Pagar Fatura com PIX

1. Em **Meu Plano**, clicar em **Pagar com PIX** na fatura pendente
2. Clicar em **Gerar PIX**
3. Escanear QR Code ou copiar código "copia e cola"
4. Pagar no app do banco
5. Sistema recebe webhook e confirma automaticamente
6. Período da assinatura é avançado automaticamente

---

## 🔧 Feature Gate - Controle de Acesso

Usar `FeatureGate` em qualquer lugar do código:

```php
require_once 'utils/FeatureGate.php';

// Verificar se tem acesso
if (FeatureGate::allows($lojaId, 'api_access')) {
    // Permitir acesso à API
}

// Verificar limite
if (FeatureGate::withinLimit($lojaId, 'employees_limit', $currentEmployees)) {
    // Permitir adicionar funcionário
} else {
    echo FeatureGate::getBlockMessage('employees_limit');
}

// Obter valor do limite
$maxEmployees = FeatureGate::getLimit($lojaId, 'employees_limit', 1);

// Verificar se está ativo
if (!FeatureGate::isActive($lojaId)) {
    // Redirecionar para página de assinatura
}
```

---

## 📊 Planos Disponíveis

| Plano | Preço/mês | Trial | Funcionários | API | White Label |
|-------|-----------|-------|--------------|-----|-------------|
| **Start** | R$ 149 | 7 dias | 1 | Não | Não |
| **Plus** | R$ 299 | 7 dias | 3 | Básica | Não |
| **Pro** | R$ 549 | 14 dias | 10 | Completa | Não |
| **Enterprise** | R$ 999 | 30 dias | Ilimitado | Completa | Sim |

---

## 🔄 Ciclo de Vida da Assinatura

```
[Criação] → trial (7-30 dias)
    ↓
[Trial Expira] → Gera fatura → status: ativa
    ↓
[Pagamento PIX] → Webhook confirma → Avança período
    ↓
[Vencimento] → Gera nova fatura
    ↓
[Não Paga] → Grace period (3 dias) → status: inadimplente
    ↓
[15 dias inadimplente] → status: suspensa
```

---

## 🧪 Testar o Sistema

### 1. Criar Assinatura Trial

```sql
-- Via SQL (ou usar interface admin)
INSERT INTO assinaturas (tipo, loja_id, plano_id, status, ciclo, trial_end, current_period_start, current_period_end, next_invoice_date, gateway)
SELECT 'loja', 1, id, 'trial', 'monthly', DATE_ADD(CURDATE(), INTERVAL 7 DAY), CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 MONTH), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'abacate'
FROM planos WHERE slug = 'klube-start';
```

### 2. Gerar Fatura

Executar manualmente:
```bash
php scripts/cron/billing.php
```

Ou pela interface admin: **Gerar Fatura Manual**

### 3. Simular Pagamento (Webhook)

Criar um teste de webhook local:

```bash
curl -X POST https://klubecash.com/api/abacatepay-webhook \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Signature: CALCULAR_HMAC" \
  -d '{
    "type": "charge.paid",
    "id": "evt_123",
    "data": {
      "id": "charge_abc",
      "status": "paid",
      "paidAt": 1697000000
    }
  }'
```

---

## 📝 Logs e Monitoramento

### Logs Disponíveis

- `logs/abacatepay.log` - Cliente HTTP
- `logs/abacate_webhook.log` - Eventos de webhook
- `logs/cron_billing.log` - Execução do billing
- `logs/cron_dunning.log` - Execução do dunning

### Monitorar Webhooks

```sql
SELECT * FROM webhook_events ORDER BY created_at DESC LIMIT 10;
```

### Ver Assinaturas Inadimplentes

```sql
SELECT a.*, l.nome_loja
FROM assinaturas a
JOIN lojas l ON a.loja_id = l.id
WHERE a.status = 'inadimplente';
```

---

## 🐛 Troubleshooting

### PIX não está sendo gerado

1. Verificar se `ABACATE_API_KEY` está definida
2. Ver logs em `logs/abacatepay.log`
3. Testar conexão: `curl -H "Authorization: Bearer SUA_CHAVE" https://api.abacatepay.com/v1/ping`

### Webhook não está funcionando

1. Verificar se `ABACATE_WEBHOOK_SECRET` está correto
2. Ver logs em `logs/abacate_webhook.log`
3. Testar se o endpoint está acessível publicamente
4. Verificar se SSL está ativo (HTTPS obrigatório)

### Fatura não avança período após pagamento

1. Verificar se webhook foi recebido em `webhook_events`
2. Ver se `processed_at` está preenchido
3. Executar manualmente:
```php
$subscriptionController->advancePeriodOnPaid($faturaId);
```

---

## 🔐 Segurança

- ✅ Webhook validado por assinatura HMAC
- ✅ Verificação de propriedade (loja só vê suas faturas)
- ✅ Idempotência nos webhooks (evita duplicação)
- ✅ HTTPS obrigatório para webhooks
- ✅ Logs de todas as operações

---

## 🎯 Próximas Melhorias (Opcional)

- [ ] Pagamento por cartão de crédito
- [ ] Assinatura anual com desconto
- [ ] Cupons de desconto
- [ ] Upgrade/downgrade com cobrança proporcional
- [ ] Dashboard de métricas de assinaturas
- [ ] Notificações por email/WhatsApp
- [ ] Histórico de mudanças de plano
- [ ] Cancelamento self-service

---

## 📞 Suporte

Em caso de dúvidas:
1. Verificar logs em `logs/`
2. Revisar esta documentação
3. Consultar docs do Abacate Pay: https://docs.abacatepay.com

---

**Implementado com sucesso! 🎉**

Sistema completo de assinaturas pronto para uso em produção.
