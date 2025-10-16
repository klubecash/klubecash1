# Testes via Postman - Sistema de Assinaturas

## 🎯 Requisito: Autenticação

Todas as requisições precisam de autenticação. Você precisa fazer login primeiro para obter a sessão.

### Login (Obrigatório Primeiro)

**Endpoint:** `POST https://klubecash.com/controllers/AuthController.php?action=login`

**Body (form-data ou JSON):**
```json
{
  "email": "seu_email@loja.com",
  "senha": "sua_senha",
  "tipo": "loja"
}
```

Após o login, o Postman salvará automaticamente os cookies de sessão.

---

## 🧪 Teste 1: Criar PIX para Fatura

### Request

**Método:** `POST`

**URL:** `https://klubecash.com/api/abacatepay.php?action=create_invoice_pix`

**Headers:**
```
Content-Type: application/json
Cookie: PHPSESSID=<valor_da_sessao_apos_login>
```

**Body (raw JSON):**
```json
{
  "invoice_id": 1
}
```
> ⚠️ Substitua `1` pelo ID da fatura que você criou no SQL

### Response Esperada (Sucesso)

```json
{
  "success": true,
  "message": "PIX gerado com sucesso",
  "pix": {
    "qr_code": "iVBORw0KGgoAAAANSUhEUgAA...", // Base64 do QR Code
    "copia_cola": "00020126580014br.gov.bcb.pix...",
    "expires_at": "2025-10-17 12:00:00",
    "amount": 149.00
  }
}
```

### Response (Erro - Não Autenticado)

```json
{
  "success": false,
  "message": "Não autenticado"
}
```

### Response (Erro - Fatura Não Encontrada)

```json
{
  "success": false,
  "message": "Fatura não encontrada"
}
```

---

## 🧪 Teste 2: Consultar Status do Pagamento

### Request

**Método:** `GET`

**URL:** `https://klubecash.com/api/abacatepay.php?action=status&charge_id=CHARGE_ID_DO_PIX`

**Headers:**
```
Cookie: PHPSESSID=<valor_da_sessao>
```

> ⚠️ Substitua `CHARGE_ID_DO_PIX` pelo `gateway_charge_id` retornado no teste anterior

### Response Esperada

```json
{
  "success": true,
  "status": "pending",
  "paid_at": null,
  "data": {
    "id": "charge_abc123",
    "status": "pending",
    "amount": 14900,
    "paid_at": null,
    "external_id": "INV-TEST-123",
    "raw": { ... }
  }
}
```

### Response (Após Pagar)

```json
{
  "success": true,
  "status": "paid",
  "paid_at": "2025-10-16 14:30:00",
  "data": {
    "id": "charge_abc123",
    "status": "paid",
    "amount": 14900,
    "paid_at": "2025-10-16 14:30:00"
  }
}
```

---

## 🧪 Teste 3: Simular Webhook de Pagamento

Para testar o webhook localmente, você pode simular um evento do Abacate Pay.

### Request

**Método:** `POST`

**URL:** `https://klubecash.com/api/abacatepay-webhook.php`

**Headers:**
```
Content-Type: application/json
X-Webhook-Signature: <calcular_hmac_sha256>
```

**Body (raw JSON):**
```json
{
  "type": "charge.paid",
  "id": "evt_test_123456",
  "data": {
    "id": "charge_abc123",
    "status": "paid",
    "amount": 14900,
    "paidAt": 1697000000,
    "externalId": "INV-TEST-123"
  }
}
```

### Como Calcular o HMAC (Assinatura)

**Em PHP:**
```php
$secret = 'klubecash2025'; // ABACATE_WEBHOOK_SECRET
$rawBody = '{"type":"charge.paid","id":"evt_test_123456",...}';
$signature = hash_hmac('sha256', $rawBody, $secret);
echo $signature;
```

**Em Node.js:**
```javascript
const crypto = require('crypto');
const secret = 'klubecash2025';
const rawBody = '{"type":"charge.paid","id":"evt_test_123456",...}';
const signature = crypto.createHmac('sha256', secret)
  .update(rawBody)
  .digest('hex');
console.log(signature);
```

**Online:**
1. Acesse: https://www.freeformatter.com/hmac-generator.html
2. Input: Cole o JSON exato (sem espaços extras)
3. Secret Key: `klubecash2025`
4. Algorithm: SHA-256
5. Copie o hash gerado

### Response Esperada

```json
{
  "status": 200,
  "message": "Webhook processed"
}
```

---

## 📦 Postman Collection (Importar)

Salve este JSON e importe no Postman:

```json
{
  "info": {
    "name": "Klube Cash - Assinaturas",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "1. Login Lojista",
      "request": {
        "method": "POST",
        "header": [],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"email\": \"loja@teste.com\",\n  \"senha\": \"senha123\",\n  \"tipo\": \"loja\"\n}",
          "options": {
            "raw": {
              "language": "json"
            }
          }
        },
        "url": {
          "raw": "https://klubecash.com/controllers/AuthController.php?action=login",
          "protocol": "https",
          "host": ["klubecash", "com"],
          "path": ["controllers", "AuthController.php"],
          "query": [{"key": "action", "value": "login"}]
        }
      }
    },
    {
      "name": "2. Criar PIX",
      "request": {
        "method": "POST",
        "header": [
          {
            "key": "Content-Type",
            "value": "application/json"
          }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"invoice_id\": 1\n}",
          "options": {
            "raw": {
              "language": "json"
            }
          }
        },
        "url": {
          "raw": "https://klubecash.com/api/abacatepay.php?action=create_invoice_pix",
          "protocol": "https",
          "host": ["klubecash", "com"],
          "path": ["api", "abacatepay.php"],
          "query": [{"key": "action", "value": "create_invoice_pix"}]
        }
      }
    },
    {
      "name": "3. Consultar Status",
      "request": {
        "method": "GET",
        "header": [],
        "url": {
          "raw": "https://klubecash.com/api/abacatepay.php?action=status&charge_id=CHARGE_ID",
          "protocol": "https",
          "host": ["klubecash", "com"],
          "path": ["api", "abacatepay.php"],
          "query": [
            {"key": "action", "value": "status"},
            {"key": "charge_id", "value": "CHARGE_ID"}
          ]
        }
      }
    },
    {
      "name": "4. Webhook Simulado",
      "request": {
        "method": "POST",
        "header": [
          {
            "key": "Content-Type",
            "value": "application/json"
          },
          {
            "key": "X-Webhook-Signature",
            "value": "CALCULAR_HMAC"
          }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"type\": \"charge.paid\",\n  \"id\": \"evt_test_123\",\n  \"data\": {\n    \"id\": \"charge_abc123\",\n    \"status\": \"paid\",\n    \"amount\": 14900,\n    \"paidAt\": 1697000000\n  }\n}",
          "options": {
            "raw": {
              "language": "json"
            }
          }
        },
        "url": {
          "raw": "https://klubecash.com/api/abacatepay-webhook.php",
          "protocol": "https",
          "host": ["klubecash", "com"],
          "path": ["api", "abacatepay-webhook.php"]
        }
      }
    }
  ]
}
```

---

## 🐛 Troubleshooting

### Erro: "Não autenticado"
**Solução:** Faça login primeiro (requisição 1) e certifique-se que o Postman está salvando cookies automaticamente.

**Em Settings do Postman:**
- Settings → General → Enable "Automatically follow redirects"
- Settings → General → Enable "Send cookies with requests"

### Erro: "Fatura não encontrada"
**Solução:** Verifique se:
1. A fatura existe: `SELECT * FROM faturas WHERE id = 1;`
2. A fatura pertence à loja logada
3. Execute o SQL de criação de dados de teste novamente

### Erro: "Invalid signature" no webhook
**Solução:**
1. Certifique-se que o JSON está exatamente igual (sem espaços extras)
2. Calcule o HMAC corretamente usando o secret `klubecash2025`
3. O body usado para calcular o HMAC deve ser o mesmo enviado na requisição

### Erro: "ABACATE_API_KEY não configurada"
**Solução:** Verifique se o `constants.php` tem a chave configurada na linha 301

---

## ✅ Checklist de Testes

- [ ] Login funcionando
- [ ] Criar PIX retorna QR Code
- [ ] QR Code está em Base64 válido
- [ ] Copia e cola do PIX está preenchido
- [ ] Consultar status retorna "pending"
- [ ] Webhook recebe e processa evento
- [ ] Fatura é marcada como "paid" após webhook
- [ ] Período da assinatura avança após pagamento

---

## 📝 Logs para Debug

Após executar os testes, verifique os logs:

```bash
# Logs da API
tail -f logs/abacatepay.log

# Logs do webhook
tail -f logs/abacate_webhook.log
```

Se não existir o diretório `logs/`, crie:
```bash
mkdir -p logs
chmod 777 logs
```
