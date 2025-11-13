# 🎉 Sistema de Pagamento com Cartão de Crédito via Stripe

## ✅ Implementação Completa

O sistema de assinaturas do Klube Cash agora suporta **2 métodos de pagamento**:
1. **PIX** (via Abacate Pay) - Instantâneo e sem taxas
2. **Cartão de Crédito** (via Stripe) - Todas as bandeiras

---

## 📁 Arquivos Criados/Modificados

### ✨ Novos Arquivos

| Arquivo | Descrição |
|---------|-----------|
| `utils/StripePayClient.php` | Cliente PHP para API Stripe (Payment Intents, webhooks) |
| `api/stripe.php` | API endpoint para criação de Payment Intents e consultas |
| `api/stripe-webhook.php` | Webhook handler para confirmar pagamentos |
| `views/stores/invoice-payment.php` | Nova interface com tabs PIX + Cartão |

### 🔧 Arquivos Modificados

| Arquivo | Mudanças |
|---------|----------|
| `config/constants.php` | Adicionadas 7 constantes do Stripe |

---

## 🔑 Configuração Inicial

### 1. Obter Chaves do Stripe

1. Acesse: https://dashboard.stripe.com
2. Crie uma conta ou faça login
3. Vá em **Developers → API keys**
4. Copie as chaves:
   - **Publishable key** (começa com `pk_test_...` ou `pk_live_...`)
   - **Secret key** (começa com `sk_test_...` ou `sk_live_...`)

### 2. Configurar Chaves no Sistema

Edite o arquivo `config/constants.php` nas linhas 306-313:

```php
// === STRIPE CONFIGURAÇÕES (ASSINATURAS - CARTÃO DE CRÉDITO) ===
define('STRIPE_SECRET_KEY', 'sk_test_COLE_SUA_CHAVE_SECRETA_AQUI');
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_COLE_SUA_CHAVE_PUBLICA_AQUI');
define('STRIPE_WEBHOOK_SECRET', 'whsec_COLOCAR_SEU_WEBHOOK_SECRET_AQUI'); // Configurar no passo 3
```

**⚠️ IMPORTANTE:**
- Use chaves de **teste** (`sk_test_...`) durante desenvolvimento
- Use chaves de **produção** (`sk_live_...`) apenas no servidor final
- **NUNCA** commite estas chaves no Git!

### 3. Configurar Webhook no Stripe

O webhook é **ESSENCIAL** para confirmar pagamentos automaticamente.

#### 3.1. Criar Endpoint no Stripe Dashboard

1. Acesse: https://dashboard.stripe.com/webhooks
2. Clique em **"Add endpoint"**
3. Configure:
   - **Endpoint URL**: `https://klubecash.com/api/stripe-webhook.php`
   - **Description**: "Klube Cash - Confirmação de Assinaturas"
   - **Events to send**:
     - ✅ `payment_intent.succeeded`
     - ✅ `payment_intent.payment_failed`
     - ✅ `payment_intent.canceled`

#### 3.2. Obter Webhook Secret

1. Após criar o endpoint, clique nele
2. Copie o **Signing secret** (começa com `whsec_...`)
3. Cole no `constants.php`:

```php
define('STRIPE_WEBHOOK_SECRET', 'whsec_ABC123...'); // Seu secret aqui
```

#### 3.3. Testar Webhook (Desenvolvimento Local)

Para testar localmente, use o Stripe CLI:

```bash
# Instalar Stripe CLI
# Windows: https://github.com/stripe/stripe-cli/releases
# Linux/Mac: brew install stripe/stripe-cli/stripe

# Fazer login
stripe login

# Redirecionar webhooks para localhost
stripe listen --forward-to http://localhost:8000/api/stripe-webhook.php

# O CLI mostrará um webhook secret temporário (whsec_...)
# Use este secret em STRIPE_WEBHOOK_SECRET durante testes locais
```

---

## 🚀 Como Usar

### Para Lojistas

1. **Acessar Assinatura**
   - Login como lojista
   - Menu lateral → "Meu Plano"

2. **Pagar Fatura**
   - Clique em "Pagar" na fatura pendente
   - Escolha entre **2 métodos**:
     - **PIX**: Escanear QR Code ou copiar código
     - **Cartão**: Preencher dados do cartão

3. **Cartão de Crédito**
   - Digite número do cartão, validade e CVV
   - Clique em "Pagar R$ XX,XX"
   - Aguarde confirmação (2-5 segundos)
   - ✅ Pagamento aprovado automaticamente!

### Fluxo Técnico (Cartão)

```
1. Lojista clica em "Pagar com Cartão"
   └─ Frontend: views/stores/invoice-payment.php

2. Frontend chama: POST /api/stripe.php?action=create_payment_intent
   └─ Backend cria Payment Intent no Stripe
   └─ Retorna client_secret

3. Frontend usa Stripe.js para confirmar pagamento
   └─ stripe.confirmCardPayment(client_secret, card_data)
   └─ Stripe processa pagamento (3D Secure se necessário)

4. Stripe envia webhook: payment_intent.succeeded
   └─ Webhook handler: api/stripe-webhook.php
   └─ Atualiza fatura: status = 'paid'
   └─ Extrai dados do cartão: brand, last4
   └─ Avança período da assinatura

5. Frontend detecta sucesso
   └─ Mostra "✓ Pagamento Confirmado!"
   └─ Recarrega página após 2 segundos
```

---

## 🔒 Segurança

### Boas Práticas Implementadas

✅ **PCI Compliance**: Dados do cartão nunca passam pelo servidor
- Stripe.js coleta dados do cartão diretamente no navegador
- Apenas tokens são enviados ao backend

✅ **Webhook Signature Validation**: Previne fraudes
- Todas as requisições de webhook são validadas via HMAC-SHA256
- Rejeita webhooks sem assinatura válida

✅ **Idempotência**: Previne processamento duplicado
- Webhooks com mesmo `event_id` são processados apenas uma vez
- Registro em `webhook_events` garante histórico completo

✅ **SSL/TLS**: Todas as comunicações criptografadas
- API Stripe: HTTPS obrigatório
- Webhook: Valida origem e assinatura

### Em Produção

Antes de ir para produção, **certifique-se**:

1. ✅ Usar chaves de produção (`sk_live_...`, `pk_live_...`)
2. ✅ Webhook configurado com URL de produção (`https://klubecash.com`)
3. ✅ Validação de webhook **HABILITADA**:
   ```php
   define('STRIPE_VALIDATE_WEBHOOK', true); // NUNCA false em produção!
   ```
4. ✅ Logs habilitados:
   ```bash
   # Criar diretório de logs se não existir
   mkdir -p logs
   chmod 755 logs
   ```

---

## 📊 Monitoramento

### Logs do Sistema

Os logs são salvos em:
- `logs/stripe_api.log` - Requisições à API Stripe
- `logs/stripe_webhook.log` - Eventos de webhook recebidos

### Stripe Dashboard

Monitore pagamentos em tempo real:
- **Payments**: https://dashboard.stripe.com/payments
- **Webhooks**: https://dashboard.stripe.com/webhooks
- **Logs**: https://dashboard.stripe.com/logs

---

## 🧪 Testes

### Cartões de Teste

Use estes cartões no ambiente de teste:

| Cartão | Número | Resultado |
|--------|--------|-----------|
| Visa | `4242 4242 4242 4242` | ✅ Aprovado |
| Visa (3D Secure) | `4000 0027 6000 3184` | ✅ Aprovado após autenticação |
| Mastercard | `5555 5555 5555 4444` | ✅ Aprovado |
| Visa Declined | `4000 0000 0000 0002` | ❌ Recusado |
| Insufficient Funds | `4000 0000 0000 9995` | ❌ Saldo insuficiente |

**Para todos os cartões de teste:**
- **Validade**: Qualquer data futura (ex: 12/25)
- **CVV**: Qualquer 3 dígitos (ex: 123)
- **CEP**: Qualquer (ex: 12345)

Mais cartões: https://stripe.com/docs/testing#cards

### Testar Webhook Localmente

```bash
# Terminal 1: Rodar servidor local
php -S localhost:8000

# Terminal 2: Redirecionar webhooks
stripe listen --forward-to http://localhost:8000/api/stripe-webhook.php

# Terminal 3: Fazer pagamento teste
# (usar o site normalmente)

# Verificar logs em logs/stripe_webhook.log
```

---

## 🔄 Comparação PIX vs Cartão

| Aspecto | PIX (Abacate Pay) | Cartão (Stripe) |
|---------|-------------------|-----------------|
| **Velocidade** | Instantâneo | 2-5 segundos |
| **Confirmação** | Webhook imediato | Webhook após processamento |
| **Taxa** | Grátis ou baixa | ~2.99% + R$ 0,39 |
| **Aprovação** | ~99% | ~85-95% (depende do banco) |
| **Estorno** | Manual | Automático (chargeback) |
| **Experiência** | QR Code ou copia/cola | Formulário na página |
| **3D Secure** | N/A | Automático quando necessário |
| **Devices** | Mobile (app bancário) | Web + Mobile |

**Recomendação**: Oferecer ambos para maximizar conversão!

---

## 🛠️ Manutenção

### Atualizar Chaves

Se precisar trocar as chaves (ex: vazamento):

1. Gere novas chaves no Stripe Dashboard
2. Atualize `constants.php`
3. Reinicie o servidor web
4. **Não delete** chaves antigas até confirmar que a nova funciona

### Webhook Falhou

Se webhooks não estão sendo recebidos:

1. **Verifique URL**: Deve ser acessível publicamente
2. **Verifique SSL**: Stripe exige HTTPS
3. **Verifique Logs**: `logs/stripe_webhook.log`
4. **Teste Manualmente**: Stripe Dashboard → Webhooks → "Send test webhook"
5. **Verifique Firewall**: Liberar IPs do Stripe

IPs do Stripe (whitelist se necessário):
```
34.197.50.1/32
34.196.215.1/32
34.230.38.1/32
```

---

## 📞 Suporte

### Documentação Stripe

- **API Reference**: https://stripe.com/docs/api
- **Payment Intents**: https://stripe.com/docs/payments/payment-intents
- **Webhooks**: https://stripe.com/docs/webhooks
- **Testing**: https://stripe.com/docs/testing

### Contato Stripe

- **Email**: support@stripe.com
- **Chat**: Disponível no Dashboard
- **Phone**: Depende do país

### Problemas Comuns

#### "Invalid API Key"
- Verifique se copiou a chave completa
- Verifique se usou `sk_test_` em desenvolvimento

#### "Webhook signature verification failed"
- Verifique se `STRIPE_WEBHOOK_SECRET` está correto
- Certifique-se que não tem espaços antes/depois da chave

#### "Payment Intent requires payment method"
- Erro no Stripe.js, verifique se `cardElement` está montado
- Verifique console do navegador para erros JavaScript

#### "No such payment intent"
- Payment Intent pode ter expirado (válido por 24h)
- Gere novo pagamento

---

## 🎯 Próximos Passos (Opcional)

### Melhorias Sugeridas

1. **Salvar Cartões** (Tokenização)
   - Permitir lojista salvar cartão para próximos pagamentos
   - Usar Stripe Customer + PaymentMethod

2. **Pagamentos Recorrentes Automáticos**
   - Usar Stripe Subscriptions API
   - Cobrar automaticamente todo mês

3. **Notificações por Email**
   - Enviar email ao lojista após pagamento aprovado
   - Lembrete 3 dias antes do vencimento

4. **Retry Logic**
   - Tentar cobrar novamente se pagamento falhar
   - Implementar "dunning" inteligente

5. **Dashboard de Métricas**
   - Taxa de aprovação PIX vs Cartão
   - Tempo médio de pagamento
   - Métodos mais usados

---

## 📄 Licença

Este código é parte do sistema Klube Cash v2.1.

---

**Última atualização**: 2025-11-12
**Versão**: 1.0.0
**Desenvolvido por**: Claude Code (Anthropic)
