# 07 - Fluxos de Negócio

## 📋 Índice
- [Cadastro e Autenticação](#cadastro-e-autenticação)
- [Transações Financeiras](#transações-financeiras)
- [Gestão de Lojas](#gestão-de-lojas)
- [Sistema de Comissões](#sistema-de-comissões)
- [Assinaturas](#assinaturas)
- [Sistema SEST SENAT](#sistema-sest-senat)

---

## 👤 Cadastro e Autenticação

### Fluxo: Cadastro de Novo Usuário

```mermaid
sequenceDiagram
    participant U as Usuário
    participant F as Frontend
    participant API as API Backend
    participant DB as Database
    participant Email as Email Service

    U->>F: Preenche formulário
    F->>API: POST /api/auth/register.php
    API->>API: Validar dados
    API->>DB: Verificar CPF/Email únicos
    DB-->>API: OK
    API->>API: Hash senha (bcrypt)
    API->>API: Gerar referral_code
    API->>DB: INSERT users
    DB-->>API: user_id
    API->>DB: CREATE wallet
    DB-->>API: wallet_id
    API->>API: Gerar JWT
    API->>Email: Enviar email de boas-vindas
    API-->>F: {token, user_data}
    F-->>U: Redirecionar para dashboard
```

#### Código Simplificado

```php
// api/auth/register.php
function register($data) {
    // 1. Validar dados
    if (!Validator::email($data['email'])) {
        throw new Exception('Email inválido');
    }
    if (!Validator::cpf($data['cpf'])) {
        throw new Exception('CPF inválido');
    }

    // 2. Verificar unicidade
    if (userExists($data['cpf'], $data['email'])) {
        throw new Exception('CPF ou email já cadastrado');
    }

    // 3. Hash senha
    $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);

    // 4. Gerar código de indicação
    $referralCode = generateReferralCode();

    // 5. Criar usuário
    $userId = createUser([
        'name' => $data['name'],
        'email' => $data['email'],
        'cpf' => $data['cpf'],
        'phone' => $data['phone'],
        'password' => $passwordHash,
        'referral_code' => $referralCode,
        'referred_by' => $data['referral_code'] ?? null
    ]);

    // 6. Criar carteira
    createWallet($userId, 'personal');

    // 7. Processar indicação
    if (!empty($data['referral_code'])) {
        processReferral($data['referral_code'], $userId);
    }

    // 8. Enviar email
    sendWelcomeEmail($data['email'], $data['name']);

    // 9. Gerar token
    $token = generateJWT($userId, $data['email'], 'user');

    return [
        'user_id' => $userId,
        'token' => $token
    ];
}
```

---

### Fluxo: Login

```mermaid
sequenceDiagram
    participant U as Usuário
    participant F as Frontend
    participant API as API Backend
    participant DB as Database

    U->>F: Insere credenciais
    F->>API: POST /api/auth/login.php
    API->>DB: SELECT user WHERE cpf/email = ?
    DB-->>API: user_data
    API->>API: Verificar password_verify()
    alt Senha correta
        API->>API: Verificar password_needs_rehash()
        alt Precisa rehash
            API->>API: Novo hash
            API->>DB: UPDATE password
        end
        API->>API: Gerar JWT
        API->>DB: INSERT access_log (sucesso)
        API-->>F: {token, user_data}
        F-->>U: Redirecionar para dashboard
    else Senha incorreta
        API->>DB: INSERT access_log (falha)
        API-->>F: 401 Unauthorized
        F-->>U: Mostrar erro
    end
```

---

## 💰 Transações Financeiras

### Fluxo: Depósito via PIX

```mermaid
sequenceDiagram
    participant U as Usuário
    participant F as Frontend
    participant API as API Backend
    participant DB as Database
    participant MP as Mercado Pago
    participant WH as Webhook

    U->>F: Solicita depósito (R$ 100)
    F->>API: POST /api/payments/create.php
    API->>DB: CREATE transaction (pending)
    DB-->>API: transaction_id
    API->>MP: Criar pagamento PIX
    MP-->>API: {qr_code, payment_id}
    API->>DB: CREATE payment (pending)
    API-->>F: {qr_code, payment_id}
    F-->>U: Exibir QR Code

    Note over U,MP: Usuário paga via banco

    MP->>WH: POST /webhooks/mercadopago.php
    WH->>MP: GET payment details
    MP-->>WH: {status: approved}
    WH->>DB: UPDATE payment (approved)
    WH->>DB: UPDATE transaction (completed)
    WH->>DB: UPDATE wallet (+R$ 100)
    WH->>DB: INSERT audit_log
    WH->>API: Enviar notificação
    API->>U: WhatsApp/Email: Pagamento confirmado
```

#### Código Simplificado

```php
// api/payments/create.php
function createPixPayment($userId, $amount) {
    // 1. Criar transação
    $transactionId = createTransaction([
        'user_id' => $userId,
        'type' => 'deposit',
        'amount' => $amount,
        'status' => 'pending'
    ]);

    // 2. Chamar Mercado Pago
    $mercadoPago = new MercadoPagoService();
    $payment = $mercadoPago->createPixPayment([
        'transaction_amount' => $amount,
        'external_reference' => $transactionId,
        'payer' => getUserPayerInfo($userId)
    ]);

    // 3. Salvar pagamento
    createPayment([
        'payment_id' => uniqid('PAY_'),
        'user_id' => $userId,
        'transaction_id' => $transactionId,
        'provider' => 'mercadopago',
        'method' => 'pix',
        'amount' => $amount,
        'external_id' => $payment['id'],
        'pix_qr_code' => $payment['point_of_interaction']['transaction_data']['qr_code'],
        'status' => 'pending',
        'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour'))
    ]);

    return [
        'transaction_id' => $transactionId,
        'qr_code' => $payment['point_of_interaction']['transaction_data']['qr_code'],
        'qr_code_base64' => $payment['point_of_interaction']['transaction_data']['qr_code_base64']
    ];
}

// webhooks/mercadopago.php
function processWebhook($data) {
    // 1. Buscar pagamento no Mercado Pago
    $mercadoPago = new MercadoPagoService();
    $payment = $mercadoPago->getPayment($data['data']['id']);

    // 2. Buscar transação local
    $transactionId = $payment['external_reference'];
    $transaction = getTransaction($transactionId);

    // 3. Verificar se já foi processado
    if ($transaction['status'] === 'completed') {
        return; // Já processado
    }

    // 4. Processar conforme status
    if ($payment['status'] === 'approved') {
        DB::beginTransaction();
        try {
            // Atualizar pagamento
            updatePayment($transaction['payment_id'], [
                'status' => 'approved',
                'approved_at' => date('Y-m-d H:i:s')
            ]);

            // Atualizar transação
            updateTransaction($transactionId, [
                'status' => 'completed',
                'processed_at' => date('Y-m-d H:i:s')
            ]);

            // Atualizar saldo da carteira
            updateWalletBalance(
                $transaction['wallet_id'],
                $transaction['amount']
            );

            // Auditoria
            createAuditLog([
                'action' => 'payment_confirmed',
                'user_id' => $transaction['user_id'],
                'details' => ['transaction_id' => $transactionId]
            ]);

            DB::commit();

            // Notificar usuário
            notifyPaymentConfirmed($transaction['user_id'], $transaction['amount']);
        } catch (Exception $e) {
            DB::rollback();
            throw $e;
        }
    }
}
```

---

### Fluxo: Saque

```mermaid
sequenceDiagram
    participant U as Usuário
    participant F as Frontend
    participant API as API Backend
    participant DB as Database
    participant Admin as Admin
    participant Bank as Sistema Bancário

    U->>F: Solicita saque (R$ 500)
    F->>API: POST /api/withdrawals/create.php
    API->>DB: Verificar saldo
    alt Saldo suficiente
        API->>DB: CREATE withdrawal (pending)
        API->>DB: UPDATE wallet (bloquear R$ 500)
        API-->>F: Saque solicitado
        F-->>U: Aguardando aprovação

        Admin->>API: Aprovar saque
        API->>Bank: Realizar transferência TED/PIX
        Bank-->>API: Confirmação
        API->>DB: UPDATE withdrawal (completed)
        API->>DB: UPDATE wallet (debitar bloqueado)
        API->>DB: CREATE transaction (withdrawal)
        API->>U: Notificar: Saque realizado
    else Saldo insuficiente
        API-->>F: 400 Bad Request
        F-->>U: Saldo insuficiente
    end
```

---

## 🏪 Gestão de Lojas

### Fluxo: Cadastro de Loja

```mermaid
sequenceDiagram
    participant M as Lojista
    participant F as Frontend
    participant API as API Backend
    participant DB as Database
    participant Admin as Admin
    participant Email as Email Service

    M->>F: Preencher dados da loja
    F->>API: POST /api/stores/create.php
    API->>API: Validar CNPJ
    API->>DB: Verificar CNPJ único
    API->>DB: CREATE store (pending)
    DB-->>API: store_id
    API->>DB: CREATE store_address
    API->>Email: Notificar admin (nova loja)
    API-->>F: Loja criada, aguardando aprovação
    F-->>M: Mostrar status pendente

    Note over Admin: Admin revisa documentos

    Admin->>API: POST /api/stores/approve.php
    API->>DB: UPDATE store (approved)
    API->>DB: CREATE wallet (loja)
    API->>Email: Notificar lojista (aprovado)
    API->>M: Loja aprovada!
```

#### Código Simplificado

```php
// api/stores/create.php
function createStore($ownerId, $data) {
    // 1. Validar CNPJ
    if (!Validator::cnpj($data['cnpj'])) {
        throw new Exception('CNPJ inválido');
    }

    // 2. Verificar se CNPJ já existe
    if (storeExists($data['cnpj'])) {
        throw new Exception('CNPJ já cadastrado');
    }

    DB::beginTransaction();
    try {
        // 3. Criar loja
        $storeId = createStoreRecord([
            'owner_id' => $ownerId,
            'name' => $data['name'],
            'trade_name' => $data['trade_name'],
            'cnpj' => $data['cnpj'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'category_id' => $data['category_id'],
            'commission_rate' => $data['commission_rate'] ?? 5.0,
            'status' => 'pending'
        ]);

        // 4. Criar endereço
        createStoreAddress($storeId, $data['address']);

        // 5. Upload de documentos (se houver)
        if (!empty($data['documents'])) {
            uploadStoreDocuments($storeId, $data['documents']);
        }

        DB::commit();

        // 6. Notificar admin
        notifyAdminNewStore($storeId);

        return ['store_id' => $storeId, 'status' => 'pending'];
    } catch (Exception $e) {
        DB::rollback();
        throw $e;
    }
}

// api/stores/approve.php (admin apenas)
function approveStore($adminId, $storeId, $approved, $notes = '') {
    $store = getStore($storeId);

    if ($store['status'] !== 'pending') {
        throw new Exception('Loja já foi processada');
    }

    DB::beginTransaction();
    try {
        if ($approved) {
            // Aprovar loja
            updateStore($storeId, [
                'status' => 'approved',
                'approved_by' => $adminId,
                'approved_at' => date('Y-m-d H:i:s'),
                'approval_notes' => $notes
            ]);

            // Criar carteira para a loja
            createWallet($store['owner_id'], 'business', $storeId);

            // Notificar lojista
            notifyStoreApproved($store['owner_id'], $storeId);
        } else {
            // Rejeitar loja
            updateStore($storeId, [
                'status' => 'rejected',
                'approved_by' => $adminId,
                'approval_notes' => $notes
            ]);

            // Notificar lojista
            notifyStoreRejected($store['owner_id'], $storeId, $notes);
        }

        DB::commit();
    } catch (Exception $e) {
        DB::rollback();
        throw $e;
    }
}
```

---

### Fluxo: Registro de Venda

```mermaid
sequenceDiagram
    participant C as Consumidor
    participant S as Loja/Sistema
    participant API as API Backend
    participant DB as Database
    participant CS as Commission Service

    C->>S: Realiza compra (R$ 100)
    S->>API: POST /api/transactions/create.php
    API->>DB: CREATE transaction (venda)
    DB-->>API: transaction_id
    API->>CS: Calcular comissões
    CS->>CS: Comissão loja (5% = R$ 5)
    CS->>CS: Cashback cliente (2% = R$ 2)
    CS->>DB: CREATE commission (loja)
    CS->>DB: CREATE commission (cashback)
    CS->>DB: UPDATE wallets (distribuir)
    API->>C: Notificar: Cashback recebido
    API->>S: Notificar: Comissão registrada
```

---

## 💵 Sistema de Comissões

### Fluxo: Distribuição de Comissões

```mermaid
graph TD
    A[Venda R$ 100] --> B{Calcular Comissões}
    B --> C[Loja: 5% = R$ 5]
    B --> D[Cliente: 2% = R$ 2]
    B --> E{Cliente tem indicador?}

    E -->|Sim| F[Indicador Nível 1: 1% = R$ 1]
    F --> G{Indicador tem indicador?}
    G -->|Sim| H[Indicador Nível 2: 0.5% = R$ 0.50]
    G -->|Não| I[Fim]
    H --> I

    E -->|Não| I

    C --> J[Atualizar Carteira Loja]
    D --> K[Atualizar Carteira Cliente]
    F --> L[Atualizar Carteira Indicador 1]
    H --> M[Atualizar Carteira Indicador 2]

    J --> N[Registrar Auditoria]
    K --> N
    L --> N
    M --> N
```

#### Código Simplificado

```php
// services/CommissionService.php
class CommissionService {
    public function distributeCommissions($transactionId) {
        $transaction = $this->getTransaction($transactionId);
        $store = $this->getStore($transaction['store_id']);

        $commissions = [];

        // 1. Comissão da loja
        $storeCommission = $transaction['amount'] * ($store['commission_rate'] / 100);
        $commissions[] = [
            'user_id' => $store['owner_id'],
            'type' => 'sale',
            'amount' => $storeCommission,
            'rate' => $store['commission_rate'],
            'level' => 0
        ];

        // 2. Cashback do cliente
        $cashbackRate = 2.0; // 2%
        $cashback = $transaction['amount'] * ($cashbackRate / 100);
        $commissions[] = [
            'user_id' => $transaction['user_id'],
            'type' => 'cashback',
            'amount' => $cashback,
            'rate' => $cashbackRate,
            'level' => 0
        ];

        // 3. Comissões de indicação (multinível)
        $referralCommissions = $this->calculateReferralCommissions(
            $transaction['user_id'],
            $transaction['amount']
        );
        $commissions = array_merge($commissions, $referralCommissions);

        // 4. Distribuir comissões
        DB::beginTransaction();
        try {
            foreach ($commissions as $commission) {
                // Criar registro de comissão
                $commissionId = $this->createCommission([
                    'transaction_id' => $transactionId,
                    'user_id' => $commission['user_id'],
                    'type' => $commission['type'],
                    'amount' => $commission['amount'],
                    'rate' => $commission['rate'],
                    'level' => $commission['level'],
                    'status' => 'paid'
                ]);

                // Atualizar saldo da carteira
                $this->updateWalletBalance(
                    $commission['user_id'],
                    $commission['amount']
                );

                // Notificar usuário
                $this->notifyCommissionReceived(
                    $commission['user_id'],
                    $commission['amount']
                );
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    private function calculateReferralCommissions($userId, $amount) {
        $commissions = [];
        $rates = [1.0, 0.5, 0.25]; // Níveis 1, 2, 3

        $referrer = $this->getReferrer($userId);
        $level = 1;

        while ($referrer && $level <= 3) {
            $rate = $rates[$level - 1];
            $commission = $amount * ($rate / 100);

            $commissions[] = [
                'user_id' => $referrer['id'],
                'type' => 'referral',
                'amount' => $commission,
                'rate' => $rate,
                'level' => $level
            ];

            $referrer = $this->getReferrer($referrer['id']);
            $level++;
        }

        return $commissions;
    }
}
```

---

## 📅 Assinaturas

### Fluxo: Criar Assinatura

```mermaid
sequenceDiagram
    participant U as Usuário
    participant F as Frontend
    participant API as API Backend
    participant DB as Database
    participant MP as Mercado Pago

    U->>F: Escolher plano (mensal R$ 29,90)
    F->>API: POST /api/subscriptions/create.php
    API->>MP: Criar assinatura recorrente
    MP->>MP: Cobrar primeira mensalidade
    MP-->>API: Assinatura criada
    API->>DB: CREATE subscription (active)
    API->>DB: CREATE subscription_payment
    API-->>F: Assinatura ativa
    F-->>U: Bem-vindo ao plano premium!

    Note over API,MP: Mensalmente

    MP->>API: Webhook: Nova cobrança
    API->>DB: CREATE subscription_payment
    API->>DB: UPDATE subscription.next_billing_date
    API->>U: Notificar: Pagamento processado
```

---

### Fluxo: Upgrade de Plano

```mermaid
sequenceDiagram
    participant U as Usuário
    participant API as API Backend
    participant DB as Database

    U->>API: Upgrade mensal → anual
    API->>DB: SELECT subscription
    API->>API: Calcular dias restantes
    Note over API: 20 dias restantes no mês<br/>Valor pago: R$ 29,90<br/>Valor proporcional: R$ 20,00
    API->>API: Calcular crédito (R$ 20)
    API->>API: Calcular valor a pagar
    Note over API: Plano anual: R$ 289,90<br/>Crédito: R$ 20,00<br/>Total: R$ 269,90
    API->>DB: UPDATE subscription
    API->>DB: CREATE subscription_payment
    API-->>U: Upgrade realizado!<br/>Crédito: R$ 20<br/>Valor: R$ 269,90
```

#### Código Simplificado

```php
// api/subscriptions/upgrade.php
function upgradeSubscription($subscriptionId, $newPlanId) {
    $subscription = getSubscription($subscriptionId);
    $oldPlan = getSubscriptionPlan($subscription['plan_id']);
    $newPlan = getSubscriptionPlan($newPlanId);

    // 1. Calcular dias restantes
    $today = new DateTime();
    $nextBilling = new DateTime($subscription['next_billing_date']);
    $daysRemaining = $today->diff($nextBilling)->days;

    // 2. Calcular valor proporcional não utilizado
    $daysInCycle = ($oldPlan['billing_cycle'] === 'monthly') ? 30 : 365;
    $dailyRate = $subscription['amount'] / $daysInCycle;
    $credit = $dailyRate * $daysRemaining;

    // 3. Calcular valor a pagar
    $amountToPay = $newPlan['price'] - $credit;

    DB::beginTransaction();
    try {
        // 4. Processar pagamento
        $paymentResult = processSubscriptionPayment(
            $subscription['user_id'],
            $amountToPay
        );

        if (!$paymentResult['success']) {
            throw new Exception('Falha no pagamento');
        }

        // 5. Atualizar assinatura
        $nextBillingDate = $today->modify(
            '+' . ($newPlan['billing_cycle'] === 'monthly' ? '1 month' : '1 year')
        )->format('Y-m-d');

        updateSubscription($subscriptionId, [
            'plan_id' => $newPlanId,
            'amount' => $newPlan['price'],
            'billing_cycle' => $newPlan['billing_cycle'],
            'next_billing_date' => $nextBillingDate
        ]);

        // 6. Registrar pagamento
        createSubscriptionPayment([
            'subscription_id' => $subscriptionId,
            'amount' => $amountToPay,
            'type' => 'upgrade',
            'credit_applied' => $credit,
            'status' => 'paid'
        ]);

        DB::commit();

        return [
            'credit_applied' => $credit,
            'amount_paid' => $amountToPay,
            'next_billing_date' => $nextBillingDate
        ];
    } catch (Exception $e) {
        DB::rollback();
        throw $e;
    }
}
```

---

## 🏛️ Sistema SEST SENAT

### Fluxo: Seleção de Carteira SENAT

```mermaid
sequenceDiagram
    participant U as Usuário
    participant F as Frontend
    participant API as API Backend
    participant DB as Database

    U->>F: Acessar seleção SENAT
    F->>API: GET /api/senat/carteiras.php
    API->>DB: SELECT carteiras disponíveis
    DB-->>API: Lista de carteiras
    API-->>F: Carteiras
    F-->>U: Exibir opções

    U->>F: Selecionar carteira
    F->>API: POST /api/senat/select.php
    API->>DB: CREATE senat_selection
    API->>DB: UPDATE user preferências
    API-->>F: Seleção confirmada
    F-->>U: Carteira SENAT configurada

    Note over U,DB: Em transações futuras

    U->>API: Realizar transação
    API->>DB: Verificar senat_selection
    API->>DB: Direcionar para carteira SENAT
```

---

## 📚 Resumo dos Fluxos

### Principais Padrões

1. **Validação**: Sempre validar dados de entrada
2. **Transações DB**: Usar BEGIN/COMMIT/ROLLBACK
3. **Auditoria**: Registrar todas as ações importantes
4. **Notificações**: Informar usuário de eventos relevantes
5. **Idempotência**: Evitar processar duas vezes (verificar status)

### Tratamento de Erros

```php
try {
    DB::beginTransaction();

    // Operações
    // ...

    DB::commit();

    return ['success' => true];
} catch (ValidationException $e) {
    DB::rollback();
    return ['success' => false, 'error' => $e->getMessage()];
} catch (Exception $e) {
    DB::rollback();
    logError($e);
    return ['success' => false, 'error' => 'Erro interno'];
}
```

---

## 📚 Próximos Passos

- **[[08-guia-desenvolvimento]]** - Comece a desenvolver
- **[[03-apis-endpoints]]** - Veja as APIs relacionadas
- **[[04-banco-de-dados]]** - Entenda as tabelas envolvidas

---

**Última atualização**: 2025-11-17
