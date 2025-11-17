# 08 - Guia de Desenvolvimento

## 📋 Índice
- [Setup do Ambiente](#setup-do-ambiente)
- [Convenções de Código](#convenções-de-código)
- [Desenvolvendo Features](#desenvolvendo-features)
- [Testes](#testes)
- [Debugging](#debugging)
- [Deploy](#deploy)
- [Troubleshooting](#troubleshooting)

---

## 🚀 Setup do Ambiente

### Requisitos

```bash
- PHP >= 7.4 (recomendado 8.0+)
- MySQL >= 5.7
- Composer
- Apache/Nginx com mod_rewrite
- Git
```

### Instalação Local

#### 1. Clonar Repositório

```bash
git clone https://github.com/sua-empresa/klubecash.git
cd klubecash
```

#### 2. Configurar Banco de Dados

```bash
# Criar banco de dados
mysql -u root -p
```

```sql
CREATE DATABASE klube_cash CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'klube_user'@'localhost' IDENTIFIED BY 'senha_segura';
GRANT ALL PRIVILEGES ON klube_cash.* TO 'klube_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### 3. Importar Schema

```bash
mysql -u klube_user -p klube_cash < database/schema.sql
mysql -u klube_user -p klube_cash < database/seeds.sql
```

#### 4. Configurar Variáveis de Ambiente

```bash
# Copiar exemplo
cp .env.example .env

# Editar .env
nano .env
```

```env
# .env
# Database
DB_HOST=localhost
DB_NAME=klube_cash
DB_USER=klube_user
DB_PASSWORD=senha_segura

# JWT
JWT_SECRET=sua_chave_secreta_muito_longa_e_aleatoria

# Mercado Pago
MP_ACCESS_TOKEN=TEST-xxx
MP_PUBLIC_KEY=TEST-xxx
MP_WEBHOOK_SECRET=xxx

# Stripe
STRIPE_SECRET_KEY=sk_test_xxx
STRIPE_PUBLISHABLE_KEY=pk_test_xxx

# Email
SMTP_HOST=smtp.hostinger.com
SMTP_PORT=587
SMTP_USERNAME=noreply@klubecash.com
SMTP_PASSWORD=xxx

# WhatsApp
WPPCONNECT_URL=http://localhost:21465
WPPCONNECT_SECRET_KEY=xxx

# App
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000
```

#### 5. Instalar Dependências

```bash
composer install
```

#### 6. Configurar Permissions

```bash
# Linux/Mac
chmod -R 755 storage/
chmod -R 755 logs/
chmod -R 755 uploads/

chown -R www-data:www-data storage/
chown -R www-data:www-data logs/
chown -R www-data:www-data uploads/
```

#### 7. Rodar Servidor de Desenvolvimento

```bash
# PHP Built-in Server
php -S localhost:8000 -t public/

# Ou Apache/Nginx
# Configurar virtual host apontando para /public
```

#### 8. Testar Instalação

```bash
# Verificar saúde da API
curl http://localhost:8000/api/health.php

# Resposta esperada:
# {"status":"ok","database":"connected","version":"1.0.0"}
```

---

## 📝 Convenções de Código

### Padrões PHP

#### PSR-12: Extended Coding Style

```php
<?php

namespace App\Controllers;

use App\Models\User;
use App\Services\EmailService;

class UserController
{
    private $userModel;
    private $emailService;

    public function __construct(User $userModel, EmailService $emailService)
    {
        $this->userModel = $userModel;
        $this->emailService = $emailService;
    }

    public function getProfile(int $userId): array
    {
        $user = $this->userModel->find($userId);

        if (!$user) {
            throw new NotFoundException('User not found');
        }

        return [
            'success' => true,
            'data' => $user
        ];
    }
}
```

### Nomenclatura

#### Variáveis e Funções

```php
// camelCase para variáveis e funções
$userId = 123;
$userEmail = 'user@email.com';

function getUserById($id) {
    // ...
}

function calculateCommission($amount, $rate) {
    // ...
}
```

#### Classes

```php
// PascalCase para classes
class UserController {}
class TransactionService {}
class EmailNotification {}
```

#### Constantes

```php
// UPPER_CASE para constantes
define('MAX_LOGIN_ATTEMPTS', 5);
define('JWT_EXPIRATION_TIME', 86400);

const DEFAULT_COMMISSION_RATE = 5.0;
```

#### Banco de Dados

```sql
-- snake_case para tabelas e colunas
CREATE TABLE user_profiles (
    user_id BIGINT,
    created_at TIMESTAMP
);
```

### Estrutura de Arquivos

```
feature/
├── controller/
│   └── FeatureController.php
├── model/
│   └── Feature.php
├── service/
│   └── FeatureService.php
├── api/
│   └── feature/
│       ├── create.php
│       └── list.php
└── tests/
    └── FeatureTest.php
```

### Comentários e Documentação

```php
/**
 * Processa pagamento via gateway externo
 *
 * @param int $userId ID do usuário
 * @param float $amount Valor a ser pago
 * @param string $method Método de pagamento (pix, credit_card)
 * @return array Resultado do processamento
 * @throws PaymentException Se o pagamento falhar
 */
function processPayment(int $userId, float $amount, string $method): array
{
    // Validar entrada
    if ($amount <= 0) {
        throw new InvalidArgumentException('Amount must be positive');
    }

    // Processar pagamento
    // ...

    return [
        'success' => true,
        'payment_id' => $paymentId
    ];
}
```

---

## 🛠️ Desenvolvendo Features

### Workflow de Desenvolvimento

#### 1. Criar Branch

```bash
# Feature branch
git checkout -b feature/nome-da-feature

# Bugfix branch
git checkout -b bugfix/nome-do-bug

# Hotfix branch
git checkout -b hotfix/nome-do-hotfix
```

#### 2. Desenvolver Feature

##### Exemplo: Nova API de Notificações

**Passo 1: Criar Model**

```php
// models/Notification.php
<?php

class Notification
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO notifications (user_id, type, title, message, status)
            VALUES (?, ?, ?, ?, 'unread')
        ");

        $stmt->execute([
            $data['user_id'],
            $data['type'],
            $data['title'],
            $data['message']
        ]);

        return $this->db->lastInsertId();
    }

    public function getUserNotifications(int $userId, string $status = null): array
    {
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        $params = [$userId];

        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markAsRead(int $notificationId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE notifications
            SET status = 'read', read_at = NOW()
            WHERE id = ?
        ");

        return $stmt->execute([$notificationId]);
    }
}
```

**Passo 2: Criar Controller**

```php
// controllers/NotificationController.php
<?php

require_once __DIR__ . '/../models/Notification.php';

class NotificationController
{
    private $notificationModel;

    public function __construct($db)
    {
        $this->notificationModel = new Notification($db);
    }

    public function getUserNotifications(int $userId, string $status = null): string
    {
        try {
            $notifications = $this->notificationModel->getUserNotifications($userId, $status);

            return json_encode([
                'success' => true,
                'data' => $notifications
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            return json_encode([
                'success' => false,
                'error' => 'Failed to fetch notifications'
            ]);
        }
    }

    public function markAsRead(int $userId, int $notificationId): string
    {
        try {
            // Verificar se a notificação pertence ao usuário
            $notification = $this->notificationModel->find($notificationId);

            if (!$notification || $notification['user_id'] != $userId) {
                http_response_code(403);
                return json_encode([
                    'success' => false,
                    'error' => 'Forbidden'
                ]);
            }

            $this->notificationModel->markAsRead($notificationId);

            return json_encode([
                'success' => true,
                'message' => 'Notification marked as read'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            return json_encode([
                'success' => false,
                'error' => 'Failed to update notification'
            ]);
        }
    }
}
```

**Passo 3: Criar API Endpoint**

```php
// api/notifications/list.php
<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../controllers/NotificationController.php';

header('Content-Type: application/json');

// Autenticar
$user = requireAuth();

// Instanciar controller
$db = getDatabase();
$controller = new NotificationController($db);

// Obter parâmetros
$status = $_GET['status'] ?? null;

// Processar requisição
echo $controller->getUserNotifications($user['user_id'], $status);
```

**Passo 4: Criar Migration**

```sql
-- database/migrations/2025_11_17_create_notifications_table.sql
CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('unread', 'read') DEFAULT 'unread',
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3. Testar Feature

```bash
# Testar API
curl -X GET "http://localhost:8000/api/notifications/list.php" \
  -H "Authorization: Bearer {token}"

# Marcar como lida
curl -X POST "http://localhost:8000/api/notifications/mark-read.php" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"notification_id": 123}'
```

#### 4. Commit e Push

```bash
# Adicionar arquivos
git add .

# Commit
git commit -m "feat: add notifications API

- Create Notification model
- Create NotificationController
- Add list and mark-read endpoints
- Create notifications table migration"

# Push
git push origin feature/notifications
```

#### 5. Criar Pull Request

```bash
# Via GitHub CLI
gh pr create --title "Feature: Notifications API" \
  --body "Adds notification system with list and mark-read functionality"

# Ou via interface web do GitHub
```

---

## 🧪 Testes

### Estrutura de Testes

```
tests/
├── unit/
│   ├── models/
│   │   ├── UserTest.php
│   │   └── TransactionTest.php
│   ├── services/
│   │   └── CommissionServiceTest.php
│   └── helpers/
│       └── ValidatorTest.php
├── integration/
│   ├── api/
│   │   ├── AuthTest.php
│   │   └── TransactionTest.php
│   └── webhooks/
│       └── MercadoPagoTest.php
└── bootstrap.php
```

### PHPUnit

#### Instalação

```bash
composer require --dev phpunit/phpunit
```

#### Configuração

```xml
<!-- phpunit.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="tests/bootstrap.php"
         colors="true"
         verbose="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/integration</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

#### Exemplo de Teste

```php
// tests/unit/services/CommissionServiceTest.php
<?php

use PHPUnit\Framework\TestCase;

class CommissionServiceTest extends TestCase
{
    private $commissionService;

    protected function setUp(): void
    {
        $this->commissionService = new CommissionService();
    }

    public function testCalculateStoreCommission()
    {
        $amount = 100.00;
        $rate = 5.0;

        $commission = $this->commissionService->calculateCommission($amount, $rate);

        $this->assertEquals(5.00, $commission);
    }

    public function testDistributeCommissions()
    {
        // Mock dependencies
        $transactionId = 'TXN_TEST_123';

        // Execute
        $result = $this->commissionService->distributeCommissions($transactionId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('commissions', $result);
    }
}
```

#### Rodar Testes

```bash
# Todos os testes
./vendor/bin/phpunit

# Testes unitários apenas
./vendor/bin/phpunit --testsuite Unit

# Testes de integração
./vendor/bin/phpunit --testsuite Integration

# Teste específico
./vendor/bin/phpunit tests/unit/services/CommissionServiceTest.php

# Com coverage
./vendor/bin/phpunit --coverage-html coverage/
```

---

## 🐛 Debugging

### Logs

```php
// includes/logger.php
function logError($message, $context = [])
{
    $logFile = __DIR__ . '/../logs/error.log';

    $timestamp = date('Y-m-d H:i:s');
    $contextJson = json_encode($context);

    $logMessage = "[$timestamp] $message | Context: $contextJson\n";

    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

function logInfo($message, $context = [])
{
    $logFile = __DIR__ . '/../logs/app.log';

    $timestamp = date('Y-m-d H:i:s');
    $contextJson = json_encode($context);

    $logMessage = "[$timestamp] INFO: $message | Context: $contextJson\n";

    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Uso
try {
    // código
} catch (Exception $e) {
    logError('Payment failed', [
        'user_id' => $userId,
        'amount' => $amount,
        'error' => $e->getMessage()
    ]);
}
```

### Xdebug

```ini
; php.ini
[xdebug]
zend_extension=xdebug.so
xdebug.mode=debug
xdebug.start_with_request=yes
xdebug.client_host=127.0.0.1
xdebug.client_port=9003
```

### Debug em Produção

```php
// NUNCA fazer isso em produção:
// var_dump($data);
// print_r($data);

// Em vez disso, logar:
logInfo('Debug data', ['data' => $data]);
```

---

## 🚀 Deploy

### Checklist Pré-Deploy

- [ ] Todos os testes passando
- [ ] Code review aprovado
- [ ] Migrations testadas
- [ ] .env de produção configurado
- [ ] Backup do banco de dados
- [ ] Variáveis sensíveis não commitadas

### Deploy para Produção

```bash
# 1. Conectar ao servidor
ssh user@klubecash.com

# 2. Navegar para o projeto
cd /var/www/klubecash

# 3. Fazer backup
mysqldump -u root -p klube_cash > backup_$(date +%Y%m%d_%H%M%S).sql

# 4. Pull do código
git pull origin main

# 5. Atualizar dependências
composer install --no-dev --optimize-autoloader

# 6. Rodar migrations
php database/migrate.php

# 7. Limpar cache (se houver)
php artisan cache:clear

# 8. Verificar saúde
curl https://klubecash.com/api/health.php

# 9. Monitorar logs
tail -f logs/error.log
```

### CI/CD (GitHub Actions)

```yaml
# .github/workflows/deploy.yml
name: Deploy to Production

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest

    steps:
    - uses: actions/checkout@v2

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.0'

    - name: Install dependencies
      run: composer install --prefer-dist --no-progress

    - name: Run tests
      run: ./vendor/bin/phpunit

    - name: Deploy to server
      uses: easingthemes/ssh-deploy@main
      env:
        SSH_PRIVATE_KEY: ${{ secrets.SSH_PRIVATE_KEY }}
        REMOTE_HOST: ${{ secrets.REMOTE_HOST }}
        REMOTE_USER: ${{ secrets.REMOTE_USER }}
        TARGET: /var/www/klubecash
```

---

## 🔧 Troubleshooting

### Problemas Comuns

#### 1. Erro de Conexão ao Banco

```
SQLSTATE[HY000] [2002] Connection refused
```

**Solução**:
```bash
# Verificar se MySQL está rodando
sudo systemctl status mysql

# Iniciar MySQL
sudo systemctl start mysql

# Verificar credenciais em .env
```

#### 2. Permissões de Arquivo

```
Warning: file_put_contents(): failed to open stream
```

**Solução**:
```bash
chmod -R 755 storage/ logs/
chown -R www-data:www-data storage/ logs/
```

#### 3. Token JWT Inválido

```json
{"error": "Invalid signature"}
```

**Solução**:
- Verificar se JWT_SECRET está correto em ambos os ambientes
- Verificar se o token não expirou
- Verificar formato do header Authorization

#### 4. Webhook Não Processando

**Debug**:
```php
// webhooks/mercadopago.php
// Adicionar no início:
file_put_contents(
    'logs/webhook_debug.log',
    date('Y-m-d H:i:s') . ' | ' . file_get_contents('php://input') . "\n",
    FILE_APPEND
);
```

---

## 📚 Recursos Adicionais

### Documentação Relacionada

- **[[01-visao-geral]]** - Entenda o sistema
- **[[02-arquitetura]]** - Arquitetura técnica
- **[[03-apis-endpoints]]** - APIs disponíveis
- **[[06-autenticacao-seguranca]]** - Práticas de segurança

### Links Úteis

- PHP Documentation: https://www.php.net/docs.php
- MySQL Documentation: https://dev.mysql.com/doc/
- Mercado Pago API: https://www.mercadopago.com.br/developers
- Stripe API: https://stripe.com/docs/api

### Comunidade

- Slack: #dev-klubecash
- Issues: GitHub Issues
- Wiki: GitHub Wiki

---

**Última atualização**: 2025-11-17

**Boa codificação! 🚀**
