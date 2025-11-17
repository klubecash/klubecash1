# 02 - Arquitetura do Sistema

## 📋 Índice
- [Visão Geral da Arquitetura](#visão-geral-da-arquitetura)
- [Estrutura de Diretórios](#estrutura-de-diretórios)
- [Padrões de Projeto](#padrões-de-projeto)
- [Camadas da Aplicação](#camadas-da-aplicação)
- [Fluxo de Requisição](#fluxo-de-requisição)
- [Componentes Principais](#componentes-principais)

---

## 🏗️ Visão Geral da Arquitetura

O backend da Klubecash utiliza uma **arquitetura MVC (Model-View-Controller)** implementada em PHP puro, com separação clara de responsabilidades entre as camadas.

### Diagrama de Alto Nível

```
┌─────────────────────────────────────────────────┐
│              Cliente (Browser/App)              │
└────────────────────┬────────────────────────────┘
                     │ HTTPS/JSON
┌────────────────────▼────────────────────────────┐
│              API Gateway / Router               │
│              (index.php / .htaccess)            │
└────────────────────┬────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────┐
│                 Controllers                     │
│  ┌──────────┬──────────┬──────────┬─────────┐  │
│  │  User    │  Store   │  Trans-  │  Admin  │  │
│  │Controller│Controller│action    │Controller│ │
│  └──────────┴──────────┴──────────┴─────────┘  │
└────────────────────┬────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────┐
│                   Models                        │
│  ┌──────────┬──────────┬──────────┬─────────┐  │
│  │  User    │  Store   │  Trans-  │  Wallet │  │
│  │  Model   │  Model   │  action  │  Model  │  │
│  └──────────┴──────────┴──────────┴─────────┘  │
└────────────────────┬────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────┐
│              Database Layer (PDO)               │
└────────────────────┬────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────┐
│                MySQL Database                   │
│              (54 tabelas)                       │
└─────────────────────────────────────────────────┘

        External Services
┌─────────────┬─────────────┬──────────────┐
│  Mercado    │  WhatsApp   │    Email     │
│   Pago      │ (WPPConnect)│    (SMTP)    │
└─────────────┴─────────────┴──────────────┘
```

---

## 📂 Estrutura de Diretórios

```
klubecash1/
│
├── api/                          # APIs RESTful (24 endpoints)
│   ├── auth/                     # Autenticação
│   │   ├── login.php
│   │   ├── register.php
│   │   └── logout.php
│   │
│   ├── users/                    # Gestão de usuários
│   │   ├── profile.php
│   │   ├── update.php
│   │   └── list.php
│   │
│   ├── stores/                   # Gestão de lojas
│   │   ├── create.php
│   │   ├── approve.php
│   │   └── list.php
│   │
│   ├── transactions/             # Transações financeiras
│   │   ├── create.php
│   │   ├── list.php
│   │   └── history.php
│   │
│   ├── subscriptions/            # Assinaturas
│   │   ├── create.php
│   │   ├── upgrade.php
│   │   └── cancel.php
│   │
│   ├── payments/                 # Pagamentos
│   │   ├── create_payment.php
│   │   └── process_webhook.php
│   │
│   └── employees/                # Funcionários
│       ├── create.php
│       └── list.php
│
├── controllers/                  # Controllers MVC (9 arquivos)
│   ├── UserController.php        # Lógica de usuários
│   ├── StoreController.php       # Lógica de lojas
│   ├── TransactionController.php # Lógica de transações
│   ├── SubscriptionController.php# Lógica de assinaturas
│   ├── PaymentController.php     # Lógica de pagamentos
│   ├── AdminController.php       # Lógica administrativa
│   ├── AuthController.php        # Lógica de autenticação
│   ├── CommissionController.php  # Lógica de comissões
│   └── EmployeeController.php    # Lógica de funcionários
│
├── models/                       # Models de dados (7 arquivos)
│   ├── User.php                  # Modelo de usuário
│   ├── Store.php                 # Modelo de loja
│   ├── Transaction.php           # Modelo de transação
│   ├── Wallet.php                # Modelo de carteira
│   ├── Subscription.php          # Modelo de assinatura
│   ├── Commission.php            # Modelo de comissão
│   └── Employee.php              # Modelo de funcionário
│
├── config/                       # Configurações
│   ├── database.php              # Conexão MySQL
│   ├── constants.php             # Constantes e API keys
│   ├── email.php                 # Configuração SMTP
│   └── cors.php                  # CORS headers
│
├── includes/                     # Utilitários e helpers
│   ├── auth.php                  # Funções de autenticação
│   ├── jwt.php                   # Geração e validação JWT
│   ├── validators.php            # Validações
│   ├── sanitizers.php            # Sanitização de dados
│   └── helpers.php               # Funções auxiliares
│
├── services/                     # Serviços externos
│   ├── MercadoPagoService.php    # Integração Mercado Pago
│   ├── StripeService.php         # Integração Stripe
│   ├── AbacatePayService.php     # Integração Abacate Pay
│   ├── OpenPixService.php        # Integração OpenPix
│   ├── WhatsAppService.php       # Integração WhatsApp
│   └── EmailService.php          # Serviço de email
│
├── webhooks/                     # Processamento de webhooks
│   ├── mercadopago.php
│   ├── stripe.php
│   ├── abacatepay.php
│   └── openpix.php
│
├── public/                       # Arquivos públicos
│   ├── index.php                 # Ponto de entrada principal
│   ├── .htaccess                 # Rewrite rules
│   └── assets/                   # CSS, JS, imagens
│
├── logs/                         # Logs da aplicação
│   ├── error.log
│   ├── access.log
│   └── webhook.log
│
├── tests/                        # Testes (a implementar)
│   ├── unit/
│   └── integration/
│
├── docs/                         # Documentação (esta pasta)
│
├── .env.example                  # Exemplo de variáveis de ambiente
├── composer.json                 # Dependências PHP
└── README.md                     # Readme do projeto
```

---

## 🎨 Padrões de Projeto

### 1. MVC (Model-View-Controller)

**Model**: Representa os dados e lógica de negócio
```php
// models/User.php
class User {
    public function findById($id) {
        // Busca usuário no banco
    }

    public function create($data) {
        // Cria novo usuário
    }
}
```

**Controller**: Orquestra Model e View, processa requisições
```php
// controllers/UserController.php
class UserController {
    public function getProfile($userId) {
        $user = new User();
        $data = $user->findById($userId);
        return json_encode($data);
    }
}
```

**View**: Resposta JSON (no caso de API REST)
```json
{
  "success": true,
  "data": { "id": 1, "name": "João" }
}
```

### 2. Repository Pattern

Abstração da camada de dados:

```php
// models/UserRepository.php
class UserRepository {
    private $db;

    public function find($id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
```

### 3. Service Layer

Lógica de negócio complexa encapsulada:

```php
// services/TransactionService.php
class TransactionService {
    public function processPayment($userId, $amount) {
        // 1. Validar dados
        // 2. Criar transação
        // 3. Atualizar saldo
        // 4. Gerar comissões
        // 5. Enviar notificações
    }
}
```

### 4. Dependency Injection

Injeção de dependências para facilitar testes:

```php
class PaymentController {
    private $paymentService;

    public function __construct(PaymentService $paymentService) {
        $this->paymentService = $paymentService;
    }
}
```

### 5. Factory Pattern

Criação de objetos de serviços de pagamento:

```php
class PaymentServiceFactory {
    public static function create($provider) {
        switch($provider) {
            case 'mercadopago':
                return new MercadoPagoService();
            case 'stripe':
                return new StripeService();
            default:
                throw new Exception("Provider not found");
        }
    }
}
```

---

## 📊 Camadas da Aplicação

### 1. Camada de Apresentação (API Layer)

**Responsabilidade**: Receber requisições HTTP e retornar respostas JSON

**Arquivos**: `/api/**/*.php`

**Características**:
- Validação básica de entrada
- Parsing de JSON
- Headers HTTP
- Códigos de status apropriados

**Exemplo**:
```php
// api/users/profile.php
header('Content-Type: application/json');

// Validar autenticação
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = $_GET['user_id'];
$controller = new UserController();
$result = $controller->getProfile($userId);

echo $result;
```

### 2. Camada de Controle (Controller Layer)

**Responsabilidade**: Orquestrar lógica de negócio

**Arquivos**: `/controllers/*.php`

**Características**:
- Recebe dados da API layer
- Chama models e services
- Trata exceções
- Retorna dados formatados

**Exemplo**:
```php
// controllers/UserController.php
class UserController {
    public function getProfile($userId) {
        try {
            $userModel = new User();
            $user = $userModel->findById($userId);

            if (!$user) {
                return json_encode([
                    'success' => false,
                    'error' => 'User not found'
                ]);
            }

            return json_encode([
                'success' => true,
                'data' => $user
            ]);
        } catch (Exception $e) {
            return json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}
```

### 3. Camada de Negócio (Business Logic Layer)

**Responsabilidade**: Implementar regras de negócio

**Arquivos**: `/services/*.php`

**Características**:
- Lógica complexa de domínio
- Validações de negócio
- Integrações externas
- Cálculos e processamentos

**Exemplo**:
```php
// services/CommissionService.php
class CommissionService {
    public function calculateAndDistribute($transactionId) {
        // 1. Buscar transação
        $transaction = $this->transactionRepo->find($transactionId);

        // 2. Calcular comissões
        $commissions = $this->calculateCommissions($transaction);

        // 3. Distribuir para carteiras
        foreach ($commissions as $commission) {
            $this->walletService->addFunds(
                $commission['user_id'],
                $commission['amount']
            );
        }

        // 4. Registrar auditoria
        $this->auditService->log('commission_distributed', $transactionId);
    }
}
```

### 4. Camada de Dados (Data Layer)

**Responsabilidade**: Acesso ao banco de dados

**Arquivos**: `/models/*.php`

**Características**:
- Queries SQL
- Prepared statements
- Mapeamento objeto-relacional
- Transações de banco

**Exemplo**:
```php
// models/Transaction.php
class Transaction {
    private $db;

    public function create($data) {
        $sql = "INSERT INTO transactions
                (user_id, amount, type, status, created_at)
                VALUES (?, ?, ?, ?, NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['user_id'],
            $data['amount'],
            $data['type'],
            'pending'
        ]);

        return $this->db->lastInsertId();
    }
}
```

### 5. Camada de Integração (Integration Layer)

**Responsabilidade**: Comunicação com serviços externos

**Arquivos**: `/services/*Service.php`

**Características**:
- APIs REST externas
- Webhooks
- Rate limiting
- Retry logic

**Exemplo**:
```php
// services/MercadoPagoService.php
class MercadoPagoService {
    private $apiKey;
    private $baseUrl = 'https://api.mercadopago.com';

    public function createPayment($data) {
        $url = $this->baseUrl . '/v1/payments';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);

        $response = curl_exec($ch);
        return json_decode($response, true);
    }
}
```

---

## 🔄 Fluxo de Requisição

### Fluxo Completo de uma Requisição API

```
1. Cliente faz requisição HTTP
   ↓
2. Apache/Nginx recebe e roteia (.htaccess)
   ↓
3. index.php ou arquivo API específico
   ↓
4. Middleware de autenticação
   ↓
5. Validação de entrada (sanitize)
   ↓
6. Controller processa requisição
   ↓
7. Service aplica lógica de negócio
   ↓
8. Model acessa banco de dados
   ↓
9. Resposta sobe pela stack
   ↓
10. JSON é retornado ao cliente
```

### Exemplo Prático: Criar Transação

```php
// 1. Requisição
POST /api/transactions/create.php
{
  "user_id": 123,
  "amount": 100.00,
  "type": "deposit"
}

// 2. API Layer (api/transactions/create.php)
require_once '../../controllers/TransactionController.php';

$data = json_decode(file_get_contents('php://input'), true);
$controller = new TransactionController();
$result = $controller->create($data);
echo $result;

// 3. Controller Layer (controllers/TransactionController.php)
public function create($data) {
    $service = new TransactionService();
    return $service->processTransaction($data);
}

// 4. Service Layer (services/TransactionService.php)
public function processTransaction($data) {
    // Validar
    $this->validate($data);

    // Criar transação
    $transactionId = $this->transactionModel->create($data);

    // Atualizar carteira
    $this->walletService->updateBalance($data['user_id'], $data['amount']);

    // Notificar
    $this->notificationService->send($data['user_id'], 'Transaction created');

    return ['success' => true, 'transaction_id' => $transactionId];
}

// 5. Model Layer (models/Transaction.php)
public function create($data) {
    $sql = "INSERT INTO transactions (...) VALUES (...)";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([...]);
    return $this->db->lastInsertId();
}
```

---

## 🧩 Componentes Principais

### 1. Sistema de Autenticação

**Localização**: `/includes/auth.php`, `/includes/jwt.php`

**Funcionamento**:
- Login com CPF/Email + Senha
- Geração de JWT token
- Validação em cada requisição
- Refresh token para sessões longas

```php
// includes/jwt.php
function generateJWT($userId) {
    $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64_encode(json_encode([
        'user_id' => $userId,
        'exp' => time() + 86400 // 24 horas
    ]));

    $signature = hash_hmac('sha256', "$header.$payload", JWT_SECRET);

    return "$header.$payload.$signature";
}
```

### 2. Gerenciador de Transações

**Localização**: `/services/TransactionService.php`

**Funcionalidades**:
- Criar transação atômica
- Validar saldo
- Atualizar carteiras
- Registrar auditoria
- Rollback em caso de erro

### 3. Processador de Webhooks

**Localização**: `/webhooks/*.php`

**Funcionamento**:
- Recebe notificações de pagamento
- Valida assinatura
- Atualiza status de transação
- Dispara eventos internos
- Registra log

```php
// webhooks/mercadopago.php
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_SIGNATURE'];

// Validar assinatura
if (!validateSignature($payload, $signature)) {
    http_response_code(401);
    exit;
}

$data = json_decode($payload, true);

// Processar evento
switch ($data['type']) {
    case 'payment':
        $paymentService->processPaymentNotification($data);
        break;
}
```

### 4. Calculadora de Comissões

**Localização**: `/services/CommissionService.php`

**Lógica**:
- Regras de comissão por loja
- Distribuição multinível
- Cashback para consumidor
- Registro de todas as comissões

### 5. Gerenciador de Assinaturas

**Localização**: `/services/SubscriptionService.php`

**Funcionalidades**:
- Criação de assinatura
- Renovação automática
- Upgrade com cálculo proporcional
- Cancelamento e reembolso

---

## 🔒 Segurança na Arquitetura

### Camadas de Segurança

```
1. Network Layer
   └── HTTPS obrigatório (TLS 1.2+)
   └── Firewall rules

2. Application Layer
   └── CSRF tokens
   └── Rate limiting
   └── Input validation

3. Authentication Layer
   └── JWT tokens
   └── Password hashing (bcrypt)
   └── Session management

4. Data Layer
   └── Prepared statements (SQL injection prevention)
   └── Encryption at rest
   └── Audit logs
```

### Princípios Aplicados

- **Defense in Depth**: Múltiplas camadas de segurança
- **Principle of Least Privilege**: Cada componente tem apenas as permissões necessárias
- **Fail Securely**: Em caso de erro, falha de forma segura
- **Don't Trust User Input**: Toda entrada é validada e sanitizada

---

## 📈 Escalabilidade

### Estratégias Implementadas

1. **Database Indexing**: Índices em colunas frequentemente consultadas
2. **Connection Pooling**: Reutilização de conexões de banco
3. **Stateless API**: Facilita balanceamento de carga

### Melhorias Futuras

1. **Caching**: Redis para dados frequentes
2. **Load Balancing**: Múltiplas instâncias da aplicação
3. **CDN**: Para assets estáticos
4. **Database Sharding**: Particionamento de dados

---

## 🔧 Configuração e Deploy

### Requisitos de Sistema

```
- PHP >= 7.4 (ideal 8.0+)
- MySQL >= 5.7
- Apache/Nginx com mod_rewrite
- SSL certificate
- 2GB RAM mínimo
- 10GB disco
```

### Variáveis de Ambiente

```env
DB_HOST=localhost
DB_NAME=klube_cash
DB_USER=root
DB_PASS=secret

JWT_SECRET=your-secret-key

MP_ACCESS_TOKEN=mercadopago-token
STRIPE_SECRET_KEY=stripe-secret

SMTP_HOST=smtp.hostinger.com
SMTP_PORT=587
SMTP_USER=noreply@klubecash.com
SMTP_PASS=smtp-password
```

---

## 📚 Próximos Passos

- **[[03-apis-endpoints]]** - Explore todas as APIs disponíveis
- **[[04-banco-de-dados]]** - Entenda a estrutura de dados
- **[[08-guia-desenvolvimento]]** - Comece a desenvolver

---

**Última atualização**: 2025-11-17
