# 06 - Autenticação e Segurança

## 📋 Índice
- [Visão Geral](#visão-geral)
- [Autenticação](#autenticação)
- [Autorização](#autorização)
- [Segurança de APIs](#segurança-de-apis)
- [Criptografia](#criptografia)
- [Melhores Práticas](#melhores-práticas)
- [Vulnerabilidades Identificadas](#vulnerabilidades-identificadas)

---

## 🔐 Visão Geral

O sistema Klubecash implementa múltiplas camadas de segurança para proteger dados e transações dos usuários.

### Camadas de Segurança

```
┌─────────────────────────────────────┐
│     1. Camada de Rede              │
│     - HTTPS (TLS 1.2+)             │
│     - Firewall                      │
│     - Rate Limiting                 │
└─────────────────────────────────────┘
                ↓
┌─────────────────────────────────────┐
│     2. Camada de Aplicação         │
│     - JWT Authentication           │
│     - CSRF Protection              │
│     - Input Validation             │
└─────────────────────────────────────┘
                ↓
┌─────────────────────────────────────┐
│     3. Camada de Dados             │
│     - SQL Injection Prevention     │
│     - Password Hashing (bcrypt)    │
│     - Data Encryption              │
└─────────────────────────────────────┘
                ↓
┌─────────────────────────────────────┐
│     4. Camada de Auditoria         │
│     - Access Logs                  │
│     - Transaction Logs             │
│     - Error Monitoring             │
└─────────────────────────────────────┘
```

---

## 🎫 Autenticação

### Sistema Dual: JWT + Sessions

O sistema usa uma combinação de JWT tokens e sessões PHP.

#### JWT (JSON Web Tokens)

**Localização**: `/includes/jwt.php`

**Estrutura do Token**:
```json
{
  "header": {
    "alg": "HS256",
    "typ": "JWT"
  },
  "payload": {
    "user_id": 123,
    "email": "joao@email.com",
    "type": "user",
    "iat": 1700000000,
    "exp": 1700086400
  },
  "signature": "..."
}
```

**Geração**:
```php
// includes/jwt.php
function generateJWT($userId, $email, $type) {
    $issuedAt = time();
    $expirationTime = $issuedAt + 86400; // 24 horas

    $payload = [
        'user_id' => $userId,
        'email' => $email,
        'type' => $type,
        'iat' => $issuedAt,
        'exp' => $expirationTime
    ];

    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $payload = json_encode($payload);

    $base64UrlHeader = base64UrlEncode($header);
    $base64UrlPayload = base64UrlEncode($payload);

    $signature = hash_hmac(
        'sha256',
        $base64UrlHeader . "." . $base64UrlPayload,
        JWT_SECRET,
        true
    );
    $base64UrlSignature = base64UrlEncode($signature);

    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}
```

**Validação**:
```php
function validateJWT($token) {
    list($header, $payload, $signature) = explode('.', $token);

    // Verificar assinatura
    $expectedSignature = hash_hmac(
        'sha256',
        $header . "." . $payload,
        JWT_SECRET,
        true
    );

    if (!hash_equals($expectedSignature, base64UrlDecode($signature))) {
        return ['valid' => false, 'error' => 'Invalid signature'];
    }

    // Verificar expiração
    $data = json_decode(base64UrlDecode($payload), true);

    if ($data['exp'] < time()) {
        return ['valid' => false, 'error' => 'Token expired'];
    }

    return ['valid' => true, 'data' => $data];
}
```

#### Middleware de Autenticação

```php
// includes/auth.php
function requireAuth() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';

    if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['error' => 'No token provided']);
        exit;
    }

    $token = $matches[1];
    $validation = validateJWT($token);

    if (!$validation['valid']) {
        http_response_code(401);
        echo json_encode(['error' => $validation['error']]);
        exit;
    }

    return $validation['data'];
}

// Uso em APIs
$user = requireAuth();
$userId = $user['user_id'];
```

#### Sessões PHP

```php
// includes/session.php
function startSession() {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_secure' => true,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict'
    ]);
}

function setUserSession($userId, $userData) {
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_data'] = $userData;
    $_SESSION['last_activity'] = time();

    // Regenerar ID de sessão
    session_regenerate_id(true);
}

function checkSession() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    // Verificar timeout (30 minutos de inatividade)
    if (time() - $_SESSION['last_activity'] > 1800) {
        session_destroy();
        return false;
    }

    $_SESSION['last_activity'] = time();
    return true;
}
```

#### Refresh Tokens

```php
// api/auth/refresh.php
function refreshToken($oldToken) {
    $validation = validateJWT($oldToken);

    if (!$validation['valid']) {
        return ['error' => 'Invalid token'];
    }

    $data = $validation['data'];

    // Verificar se o token está próximo de expirar (última hora)
    $timeUntilExpiry = $data['exp'] - time();

    if ($timeUntilExpiry > 3600) {
        return ['error' => 'Token still valid', 'token' => $oldToken];
    }

    // Gerar novo token
    $newToken = generateJWT(
        $data['user_id'],
        $data['email'],
        $data['type']
    );

    return ['token' => $newToken];
}
```

---

## 🔑 Autorização

### Controle de Acesso Baseado em Papéis (RBAC)

```php
// includes/authorization.php
class Authorization {
    const ROLES = [
        'user' => ['view_profile', 'update_profile', 'view_transactions'],
        'merchant' => ['view_profile', 'update_profile', 'view_transactions',
                      'manage_store', 'view_reports', 'manage_employees'],
        'admin' => ['*']  // Todas as permissões
    ];

    public static function can($userType, $permission) {
        if ($userType === 'admin') {
            return true;
        }

        $permissions = self::ROLES[$userType] ?? [];
        return in_array($permission, $permissions);
    }

    public static function requirePermission($permission) {
        $user = requireAuth();

        if (!self::can($user['type'], $permission)) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }

        return $user;
    }
}

// Uso
// api/stores/approve.php
$user = Authorization::requirePermission('approve_stores');
```

### Verificação de Propriedade

```php
function verifyOwnership($resourceType, $resourceId, $userId) {
    $db = getDatabase();

    switch ($resourceType) {
        case 'store':
            $stmt = $db->prepare("SELECT owner_id FROM stores WHERE id = ?");
            $stmt->execute([$resourceId]);
            $store = $stmt->fetch();
            return $store && $store['owner_id'] == $userId;

        case 'transaction':
            $stmt = $db->prepare("SELECT user_id FROM transactions WHERE id = ?");
            $stmt->execute([$resourceId]);
            $txn = $stmt->fetch();
            return $txn && $txn['user_id'] == $userId;

        default:
            return false;
    }
}

// Uso
if (!verifyOwnership('store', $storeId, $user['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authorized']);
    exit;
}
```

---

## 🛡️ Segurança de APIs

### CSRF Protection

```php
// includes/csrf.php
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) &&
           hash_equals($_SESSION['csrf_token'], $token);
}

// Em formulários HTML
<input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

// Validação em APIs
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    http_response_code(403);
    exit;
}
```

### Rate Limiting

```php
// includes/rate_limit.php
class RateLimiter {
    private $redis;
    private $maxRequests = 100;
    private $windowSize = 60; // segundos

    public function check($identifier) {
        $key = "rate_limit:$identifier";
        $current = $this->redis->incr($key);

        if ($current === 1) {
            $this->redis->expire($key, $this->windowSize);
        }

        if ($current > $this->maxRequests) {
            http_response_code(429);
            echo json_encode(['error' => 'Too many requests']);
            exit;
        }

        return true;
    }
}

// Uso
$rateLimiter = new RateLimiter();
$rateLimiter->check($_SERVER['REMOTE_ADDR']);
```

### Input Validation

```php
// includes/validators.php
class Validator {
    public static function email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public static function cpf($cpf) {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);

        if (strlen($cpf) !== 11) {
            return false;
        }

        // Verificar dígitos verificadores
        // ... (lógica de validação de CPF)

        return true;
    }

    public static function cnpj($cnpj) {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);

        if (strlen($cnpj) !== 14) {
            return false;
        }

        // Verificar dígitos verificadores
        // ... (lógica de validação de CNPJ)

        return true;
    }

    public static function phone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return strlen($phone) >= 10 && strlen($phone) <= 11;
    }

    public static function amount($amount) {
        return is_numeric($amount) && $amount > 0;
    }
}
```

### Input Sanitization

```php
// includes/sanitizers.php
class Sanitizer {
    public static function string($value) {
        return htmlspecialchars(
            strip_tags($value),
            ENT_QUOTES,
            'UTF-8'
        );
    }

    public static function email($email) {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }

    public static function cpf($cpf) {
        return preg_replace('/[^0-9]/', '', $cpf);
    }

    public static function amount($amount) {
        return number_format(
            (float) $amount,
            2,
            '.',
            ''
        );
    }
}

// Uso
$name = Sanitizer::string($_POST['name']);
$email = Sanitizer::email($_POST['email']);
$cpf = Sanitizer::cpf($_POST['cpf']);
```

### SQL Injection Prevention

```php
// SEMPRE usar prepared statements
// ✅ CORRETO
$stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);

// ❌ INCORRETO - vulnerável a SQL injection
$query = "SELECT * FROM users WHERE email = '$email'";
$result = $db->query($query);
```

### XSS Prevention

```php
// Escapar output HTML
function escapeHtml($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// Em views
<p>Bem-vindo, <?= escapeHtml($userName) ?></p>

// Para JSON (já protegido por default)
echo json_encode(['name' => $userName]);
```

---

## 🔒 Criptografia

### Senhas

```php
// Hashing de senhas com bcrypt
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

// Verificação
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Verificar se precisa rehash (atualizar cost)
function needsRehash($hash) {
    return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
}

// Login
$user = getUserByEmail($email);

if (verifyPassword($password, $user['password'])) {
    // Verificar se precisa atualizar hash
    if (needsRehash($user['password'])) {
        $newHash = hashPassword($password);
        updateUserPassword($user['id'], $newHash);
    }

    // Login bem-sucedido
    return generateJWT($user['id'], $user['email'], $user['type']);
}
```

### Dados Sensíveis

```php
// Criptografia AES-256
function encrypt($data, $key) {
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt(
        $data,
        'AES-256-CBC',
        $key,
        0,
        $iv
    );
    return base64_encode($iv . $encrypted);
}

function decrypt($encrypted, $key) {
    $data = base64_decode($encrypted);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);

    return openssl_decrypt(
        $encrypted,
        'AES-256-CBC',
        $key,
        0,
        $iv
    );
}

// Uso para dados sensíveis
$encryptedCard = encrypt($cardNumber, ENCRYPTION_KEY);
```

### Tokens de Recuperação

```php
// Gerar token seguro
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Recuperação de senha
function createPasswordResetToken($userId) {
    $token = generateSecureToken();
    $hash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hora

    $stmt = $db->prepare("
        INSERT INTO password_resets (user_id, token_hash, expires_at)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$userId, $hash, $expiresAt]);

    return $token;
}
```

---

## ✅ Melhores Práticas

### Checklist de Segurança

#### Autenticação
- [x] Senhas hasheadas com bcrypt
- [x] JWT com assinatura HMAC SHA-256
- [x] Tokens com expiração (24h)
- [x] Refresh tokens implementados
- [x] Sessões com timeout (30min)
- [x] Regeneração de session ID após login

#### Autorização
- [x] RBAC implementado
- [x] Verificação de propriedade de recursos
- [x] Least privilege principle

#### Proteção de APIs
- [x] HTTPS obrigatório
- [x] CSRF protection
- [x] Rate limiting (100 req/min)
- [x] Input validation
- [x] Input sanitization
- [x] SQL injection prevention (prepared statements)
- [x] XSS prevention

#### Dados
- [x] Prepared statements (PDO)
- [x] Password hashing (bcrypt)
- [ ] Encryption at rest (a implementar)
- [x] Audit logs

#### Headers de Segurança
```php
// includes/security_headers.php
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Content-Security-Policy: default-src \'self\'');
```

---

## ⚠️ Vulnerabilidades Identificadas

### Críticas

#### 1. Credenciais Hardcoded

**Localização**: `config/database.php`, `config/constants.php`, `config/email.php`

**Risco**: Alto - Credenciais expostas no código

**Solução**:
```php
// ANTES (vulnerável)
define('DB_PASSWORD', 'senha123');
define('MP_ACCESS_TOKEN', 'APP_USR-xxx');

// DEPOIS (seguro)
define('DB_PASSWORD', getenv('DB_PASSWORD'));
define('MP_ACCESS_TOKEN', getenv('MP_ACCESS_TOKEN'));
```

**Recomendação**: Usar arquivo `.env` não versionado
```env
# .env
DB_HOST=localhost
DB_NAME=klube_cash
DB_USER=root
DB_PASSWORD=senha_segura

MP_ACCESS_TOKEN=APP_USR-xxx
STRIPE_SECRET_KEY=sk_live_xxx
SMTP_PASSWORD=senha_smtp

JWT_SECRET=chave_secreta_aleatoria_longa
ENCRYPTION_KEY=chave_criptografia_256bits
```

#### 2. JWT Secret Fraco

**Risco**: Médio - Secret pode ser adivinhado

**Solução**:
```php
// Gerar secret forte
$jwtSecret = bin2hex(random_bytes(32)); // 64 caracteres hexadecimais
```

### Médias

#### 3. Falta de Rate Limiting em Todos os Endpoints

**Risco**: Médio - Possível abuso de APIs

**Solução**: Implementar rate limiting global

#### 4. Logs com Dados Sensíveis

**Risco**: Médio - Exposição de dados em logs

**Solução**: Não logar senhas, tokens ou dados de cartão

### Recomendações Adicionais

1. **WAF (Web Application Firewall)**: Implementar Cloudflare ou similar
2. **2FA**: Adicionar autenticação de dois fatores
3. **Alertas**: Notificar logins de novos dispositivos
4. **Audit Trail**: Expandir logs de auditoria
5. **Penetration Testing**: Realizar testes de penetração regulares
6. **Dependency Scanning**: Verificar vulnerabilidades em dependências

---

## 📊 Monitoramento de Segurança

### Logs de Acesso

```sql
-- Tentativas de login falhadas
SELECT
    ip_address,
    COUNT(*) as failed_attempts
FROM access_logs
WHERE action = 'login_failed'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY ip_address
HAVING failed_attempts > 5;
```

### Detecção de Anomalias

```php
class SecurityMonitor {
    public function detectAnomalies() {
        // Múltiplas tentativas de login
        $this->checkFailedLogins();

        // Transações incomuns
        $this->checkUnusualTransactions();

        // Acessos de locais suspeitos
        $this->checkSuspiciousLocations();
    }

    private function checkFailedLogins() {
        // Bloquear IP após 10 tentativas em 1 hora
        $stmt = $this->db->query("
            SELECT ip_address, COUNT(*) as attempts
            FROM access_logs
            WHERE action = 'login_failed'
              AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            GROUP BY ip_address
            HAVING attempts >= 10
        ");

        foreach ($stmt->fetchAll() as $row) {
            $this->blockIP($row['ip_address']);
            $this->alertAdmin("IP bloqueado: " . $row['ip_address']);
        }
    }
}
```

---

## 📚 Próximos Passos

- **[[07-fluxos-negocio]]** - Entenda os fluxos principais
- **[[08-guia-desenvolvimento]]** - Comece a desenvolver com segurança
- **[[03-apis-endpoints]]** - Veja as APIs protegidas

---

**Última atualização**: 2025-11-17
