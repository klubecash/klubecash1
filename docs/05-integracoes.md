# 05 - Integrações e Serviços Externos

## 📋 Índice
- [Visão Geral](#visão-geral)
- [Gateways de Pagamento](#gateways-de-pagamento)
- [Comunicação](#comunicação)
- [Webhooks](#webhooks)
- [Configuração](#configuração)

---

## 🌐 Visão Geral

O sistema Klubecash integra com diversos serviços externos para processar pagamentos e comunicação.

### Integrações Ativas

| Serviço | Tipo | Status | Uso |
|---------|------|--------|-----|
| **Mercado Pago** | Pagamentos | ✅ Ativo | PIX + Cartão (Principal) |
| **Abacate Pay** | Pagamentos | ✅ Ativo | PIX |
| **Stripe** | Pagamentos | 🧪 Teste | Cartão Internacional |
| **OpenPix** | Pagamentos | ⚙️ Configurado | PIX |
| **WPPConnect** | Comunicação | ✅ Ativo | WhatsApp |
| **SMTP** | Comunicação | ✅ Ativo | Email (Hostinger) |

---

## 💳 Gateways de Pagamento

### 1. Mercado Pago (Principal)

**Status**: ✅ Ativo - Principal gateway

**Localização**: `/services/MercadoPagoService.php`

#### Configuração

```php
// config/constants.php
define('MERCADOPAGO_ACCESS_TOKEN', 'APP_USR-xxx');
define('MERCADOPAGO_PUBLIC_KEY', 'APP_USR-xxx-xxx');
define('MERCADOPAGO_WEBHOOK_SECRET', 'xxx');
```

#### Métodos de Pagamento

##### PIX
```php
$mercadoPago = new MercadoPagoService();
$payment = $mercadoPago->createPixPayment([
    'transaction_amount' => 100.00,
    'description' => 'Recarga Klubecash',
    'payment_method_id' => 'pix',
    'payer' => [
        'email' => 'usuario@email.com',
        'identification' => [
            'type' => 'CPF',
            'number' => '12345678900'
        ]
    ]
]);

// Retorna QR Code e dados do PIX
$qrCode = $payment['point_of_interaction']['transaction_data']['qr_code'];
$qrCodeBase64 = $payment['point_of_interaction']['transaction_data']['qr_code_base64'];
```

##### Cartão de Crédito
```php
$payment = $mercadoPago->createCardPayment([
    'transaction_amount' => 100.00,
    'token' => $cardToken,  // Token do cartão (gerado no frontend)
    'installments' => 1,
    'payment_method_id' => 'visa',
    'payer' => [
        'email' => 'usuario@email.com',
        'identification' => [
            'type' => 'CPF',
            'number' => '12345678900'
        ]
    ]
]);
```

#### Webhooks

**URL**: `https://klubecash.com/webhooks/mercadopago.php`

**Eventos**:
```json
{
  "id": 123456789,
  "live_mode": true,
  "type": "payment",
  "date_created": "2025-11-17T10:00:00Z",
  "user_id": "USER_ID",
  "api_version": "v1",
  "action": "payment.updated",
  "data": {
    "id": "123456789"
  }
}
```

**Processamento**:
```php
// webhooks/mercadopago.php
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';

// Validar assinatura
if (!validateMercadoPagoSignature($payload, $signature)) {
    http_response_code(401);
    exit;
}

$data = json_decode($payload, true);

if ($data['type'] === 'payment') {
    $paymentId = $data['data']['id'];

    // Buscar detalhes do pagamento
    $payment = $mercadoPago->getPayment($paymentId);

    // Atualizar transação
    if ($payment['status'] === 'approved') {
        $transactionService->confirmPayment($payment['external_reference']);
    }
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
```

#### Tratamento de Erros

```php
// Códigos de erro comuns
switch ($errorCode) {
    case 'cc_rejected_insufficient_amount':
        return 'Saldo insuficiente no cartão';

    case 'cc_rejected_bad_filled_security_code':
        return 'Código de segurança inválido';

    case 'cc_rejected_call_for_authorize':
        return 'Pagamento rejeitado. Entre em contato com o banco.';

    default:
        return 'Erro ao processar pagamento';
}
```

---

### 2. Abacate Pay

**Status**: ✅ Ativo - PIX

**Localização**: `/services/AbacatePayService.php`

#### Configuração

```php
// config/constants.php
define('ABACATE_API_KEY', 'xxx');
define('ABACATE_BASE_URL', 'https://api.abacatepay.com/v1');
```

#### Criar Pagamento PIX

```php
$abacate = new AbacatePayService();
$payment = $abacate->createBilling([
    'frequency' => 'once',
    'methods' => ['PIX'],
    'products' => [
        [
            'externalId' => 'PROD_123',
            'name' => 'Recarga Klubecash',
            'quantity' => 1,
            'price' => 100.00
        ]
    ],
    'customer' => [
        'email' => 'usuario@email.com',
        'cellphone' => '11999999999',
        'taxId' => '12345678900'
    ]
]);

// Retorna URL e QR Code
$pixUrl = $payment['url'];
$qrCode = $payment['pix']['qrcode'];
```

#### Webhook

**URL**: `https://klubecash.com/webhooks/abacatepay.php`

**Eventos**:
```json
{
  "id": "bill_xxx",
  "status": "PAID",
  "amount": 100.00,
  "customer": {
    "email": "usuario@email.com"
  },
  "metadata": {
    "transaction_id": "TXN_123"
  }
}
```

---

### 3. Stripe

**Status**: 🧪 Teste - Cartão Internacional

**Localização**: `/services/StripeService.php`

#### Configuração

```php
// config/constants.php
define('STRIPE_SECRET_KEY', 'sk_test_xxx');
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_xxx');
define('STRIPE_WEBHOOK_SECRET', 'whsec_xxx');
```

#### Criar Payment Intent

```php
$stripe = new StripeService();
$intent = $stripe->createPaymentIntent([
    'amount' => 10000,  // Centavos (100.00 BRL)
    'currency' => 'brl',
    'payment_method_types' => ['card'],
    'metadata' => [
        'transaction_id' => 'TXN_123',
        'user_id' => '456'
    ]
]);

// Retornar client_secret para o frontend
$clientSecret = $intent['client_secret'];
```

#### Webhook

**URL**: `https://klubecash.com/webhooks/stripe.php`

**Eventos**:
- `payment_intent.succeeded`
- `payment_intent.payment_failed`
- `charge.refunded`

```php
// webhooks/stripe.php
$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'];

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sigHeader,
        STRIPE_WEBHOOK_SECRET
    );

    switch ($event->type) {
        case 'payment_intent.succeeded':
            $paymentIntent = $event->data->object;
            $transactionId = $paymentIntent->metadata->transaction_id;
            $transactionService->confirmPayment($transactionId);
            break;

        case 'payment_intent.payment_failed':
            $paymentIntent = $event->data->object;
            $transactionId = $paymentIntent->metadata->transaction_id;
            $transactionService->failPayment($transactionId);
            break;
    }

    http_response_code(200);
} catch (\Exception $e) {
    http_response_code(400);
    exit;
}
```

---

### 4. OpenPix

**Status**: ⚙️ Configurado - PIX

**Localização**: `/services/OpenPixService.php`

#### Configuração

```php
// config/constants.php
define('OPENPIX_APP_ID', 'xxx');
define('OPENPIX_API_KEY', 'xxx');
define('OPENPIX_BASE_URL', 'https://api.openpix.com.br/api/v1');
```

#### Criar Cobrança

```php
$openPix = new OpenPixService();
$charge = $openPix->createCharge([
    'correlationID' => 'TXN_123',
    'value' => 10000,  // Centavos
    'comment' => 'Recarga Klubecash',
    'customer' => [
        'name' => 'João Silva',
        'taxID' => '12345678900',
        'email' => 'joao@email.com'
    ]
]);

$qrCode = $charge['qrCodeImage'];
$pixKey = $charge['brCode'];
```

---

## 📱 Comunicação

### 5. WhatsApp (WPPConnect)

**Status**: ✅ Ativo

**Localização**: `/services/WhatsAppService.php`

#### Configuração

```php
// config/constants.php
define('WPPCONNECT_URL', 'http://localhost:21465');
define('WPPCONNECT_SECRET_KEY', 'xxx');
define('WPPCONNECT_SESSION', 'klubecash');
```

#### Enviar Mensagem

```php
$whatsapp = new WhatsAppService();

// Mensagem de texto
$whatsapp->sendMessage([
    'phone' => '5511999999999',
    'message' => 'Seu pagamento foi confirmado! ✅'
]);

// Mensagem com imagem
$whatsapp->sendImage([
    'phone' => '5511999999999',
    'image' => 'https://klubecash.com/receipt/123.jpg',
    'caption' => 'Aqui está seu comprovante'
]);
```

#### Notificações Automáticas

```php
class NotificationService {
    public function notifyPaymentConfirmed($userId, $amount) {
        $user = $this->userRepo->find($userId);

        $message = "🎉 Pagamento confirmado!\n\n";
        $message .= "Valor: R$ " . number_format($amount, 2, ',', '.') . "\n";
        $message .= "Seu saldo foi atualizado.\n\n";
        $message .= "Acesse: https://klubecash.com";

        $this->whatsapp->sendMessage([
            'phone' => $user['phone'],
            'message' => $message
        ]);
    }

    public function notifyCommissionReceived($userId, $amount) {
        $user = $this->userRepo->find($userId);

        $message = "💰 Nova comissão recebida!\n\n";
        $message .= "Valor: R$ " . number_format($amount, 2, ',', '.') . "\n";
        $message .= "Total acumulado: R$ " . number_format($user['wallet_balance'], 2, ',', '.');

        $this->whatsapp->sendMessage([
            'phone' => $user['phone'],
            'message' => $message
        ]);
    }
}
```

---

### 6. Email (SMTP)

**Status**: ✅ Ativo - Hostinger

**Localização**: `/services/EmailService.php`

#### Configuração

```php
// config/email.php
return [
    'host' => 'smtp.hostinger.com',
    'port' => 587,
    'username' => 'noreply@klubecash.com',
    'password' => 'xxx',
    'encryption' => 'tls',
    'from_email' => 'noreply@klubecash.com',
    'from_name' => 'Klubecash'
];
```

#### Enviar Email

```php
$email = new EmailService();

// Email simples
$email->send([
    'to' => 'usuario@email.com',
    'subject' => 'Bem-vindo à Klubecash!',
    'body' => $htmlContent
]);

// Email com anexo
$email->send([
    'to' => 'usuario@email.com',
    'subject' => 'Seu comprovante',
    'body' => $htmlContent,
    'attachments' => [
        '/path/to/receipt.pdf'
    ]
]);
```

#### Templates de Email

```php
class EmailTemplates {
    public static function welcome($userName) {
        return "
            <html>
            <body>
                <h1>Bem-vindo, {$userName}!</h1>
                <p>Obrigado por se cadastrar na Klubecash.</p>
                <a href='https://klubecash.com/login'>Acessar Plataforma</a>
            </body>
            </html>
        ";
    }

    public static function paymentReceipt($transaction) {
        return "
            <html>
            <body>
                <h1>Comprovante de Pagamento</h1>
                <p>Transação: {$transaction['id']}</p>
                <p>Valor: R$ {$transaction['amount']}</p>
                <p>Status: Confirmado</p>
            </body>
            </html>
        ";
    }

    public static function passwordReset($resetLink) {
        return "
            <html>
            <body>
                <h1>Redefinir Senha</h1>
                <p>Clique no link abaixo para redefinir sua senha:</p>
                <a href='{$resetLink}'>Redefinir Senha</a>
                <p>Link válido por 24 horas.</p>
            </body>
            </html>
        ";
    }
}
```

#### Fila de Emails

```php
// Email assíncrono via fila
class EmailQueue {
    public function enqueue($emailData) {
        $stmt = $this->db->prepare("
            INSERT INTO email_queue (to_email, subject, body, status)
            VALUES (?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $emailData['to'],
            $emailData['subject'],
            $emailData['body']
        ]);
    }

    public function process() {
        // Processar emails pendentes
        $emails = $this->db->query("
            SELECT * FROM email_queue
            WHERE status = 'pending'
            LIMIT 10
        ")->fetchAll();

        foreach ($emails as $email) {
            try {
                $this->emailService->send([
                    'to' => $email['to_email'],
                    'subject' => $email['subject'],
                    'body' => $email['body']
                ]);

                // Marcar como enviado
                $this->updateStatus($email['id'], 'sent');
            } catch (Exception $e) {
                // Marcar como falhou
                $this->updateStatus($email['id'], 'failed');
                $this->logError($email['id'], $e->getMessage());
            }
        }
    }
}
```

---

## 🔔 Webhooks

### Visão Geral

Webhooks são callbacks HTTP que os provedores externos usam para notificar eventos.

### Estrutura de Webhook

```php
// webhooks/base.php
abstract class WebhookHandler {
    abstract protected function validateSignature($payload, $signature);
    abstract protected function processEvent($event);

    public function handle() {
        // 1. Receber payload
        $payload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';

        // 2. Validar assinatura
        if (!$this->validateSignature($payload, $signature)) {
            $this->respond(401, ['error' => 'Invalid signature']);
            return;
        }

        // 3. Parse evento
        $event = json_decode($payload, true);

        // 4. Registrar log
        $this->logWebhook($event);

        // 5. Processar evento
        try {
            $this->processEvent($event);
            $this->respond(200, ['status' => 'ok']);
        } catch (Exception $e) {
            $this->logError($e);
            $this->respond(500, ['error' => 'Processing failed']);
        }
    }

    protected function logWebhook($event) {
        $stmt = $this->db->prepare("
            INSERT INTO webhook_logs (provider, event_type, payload, status)
            VALUES (?, ?, ?, 'received')
        ");
        $stmt->execute([
            $this->provider,
            $event['type'] ?? 'unknown',
            json_encode($event)
        ]);
    }

    protected function respond($code, $data) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
```

### Segurança de Webhooks

```php
// Validar assinatura Mercado Pago
function validateMercadoPagoSignature($payload, $signature) {
    $parts = explode(',', $signature);
    $ts = null;
    $hash = null;

    foreach ($parts as $part) {
        list($key, $value) = explode('=', $part);
        if ($key === 'ts') $ts = $value;
        if ($key === 'v1') $hash = $value;
    }

    $expected = hash_hmac('sha256', "id:$ts:url:$payload", MERCADOPAGO_WEBHOOK_SECRET);
    return hash_equals($expected, $hash);
}

// Validar assinatura Stripe
function validateStripeSignature($payload, $sigHeader) {
    try {
        \Stripe\Webhook::constructEvent(
            $payload,
            $sigHeader,
            STRIPE_WEBHOOK_SECRET
        );
        return true;
    } catch (\Exception $e) {
        return false;
    }
}
```

### Retry Logic

```php
class WebhookRetry {
    public function retry($webhookLogId) {
        $webhook = $this->getWebhook($webhookLogId);

        $maxRetries = 5;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                $this->processWebhook($webhook);
                $this->markAsProcessed($webhookLogId);
                return;
            } catch (Exception $e) {
                $attempt++;
                $delay = pow(2, $attempt); // Exponential backoff
                sleep($delay);
            }
        }

        $this->markAsFailed($webhookLogId);
    }
}
```

---

## ⚙️ Configuração

### Arquivo de Configuração

```php
// config/services.php
return [
    'mercadopago' => [
        'enabled' => true,
        'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
        'public_key' => env('MERCADOPAGO_PUBLIC_KEY'),
        'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),
        'test_mode' => false
    ],

    'stripe' => [
        'enabled' => false,  // Teste apenas
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'test_mode' => true
    ],

    'abacatepay' => [
        'enabled' => true,
        'api_key' => env('ABACATE_API_KEY')
    ],

    'openpix' => [
        'enabled' => true,
        'app_id' => env('OPENPIX_APP_ID'),
        'api_key' => env('OPENPIX_API_KEY')
    ],

    'whatsapp' => [
        'enabled' => true,
        'url' => env('WPPCONNECT_URL'),
        'secret_key' => env('WPPCONNECT_SECRET_KEY'),
        'session' => env('WPPCONNECT_SESSION')
    ],

    'email' => [
        'enabled' => true,
        'host' => env('SMTP_HOST'),
        'port' => env('SMTP_PORT'),
        'username' => env('SMTP_USERNAME'),
        'password' => env('SMTP_PASSWORD'),
        'encryption' => 'tls'
    ]
];
```

### Variáveis de Ambiente

```env
# .env
MERCADOPAGO_ACCESS_TOKEN=APP_USR-xxx
MERCADOPAGO_PUBLIC_KEY=APP_USR-xxx-xxx
MERCADOPAGO_WEBHOOK_SECRET=xxx

STRIPE_SECRET_KEY=sk_test_xxx
STRIPE_PUBLISHABLE_KEY=pk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx

ABACATE_API_KEY=xxx

OPENPIX_APP_ID=xxx
OPENPIX_API_KEY=xxx

WPPCONNECT_URL=http://localhost:21465
WPPCONNECT_SECRET_KEY=xxx
WPPCONNECT_SESSION=klubecash

SMTP_HOST=smtp.hostinger.com
SMTP_PORT=587
SMTP_USERNAME=noreply@klubecash.com
SMTP_PASSWORD=xxx
```

---

## 🔍 Monitoramento

### Logs de Integração

```sql
-- Verificar status dos webhooks
SELECT
    provider,
    status,
    COUNT(*) as total
FROM webhook_logs
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY provider, status;

-- Pagamentos pendentes há mais de 1 hora
SELECT *
FROM payments
WHERE status = 'pending'
  AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);
```

### Health Check

```php
// api/health/integrations.php
class IntegrationHealthCheck {
    public function check() {
        return [
            'mercadopago' => $this->checkMercadoPago(),
            'stripe' => $this->checkStripe(),
            'whatsapp' => $this->checkWhatsApp(),
            'email' => $this->checkEmail()
        ];
    }

    private function checkMercadoPago() {
        try {
            $mp = new MercadoPagoService();
            $mp->getPayment('test');
            return ['status' => 'ok'];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
```

---

## 📚 Próximos Passos

- **[[06-autenticacao-seguranca]]** - Entenda a segurança do sistema
- **[[07-fluxos-negocio]]** - Veja os fluxos principais
- **[[08-guia-desenvolvimento]]** - Comece a integrar

---

**Última atualização**: 2025-11-17
