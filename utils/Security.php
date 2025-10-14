<?php
// utils/Security.php
require_once __DIR__ . '/../config/constants.php';

/**
 * Classe Security - Utilitários de segurança
 * * Esta classe fornece métodos para garantir a segurança do sistema,
 * incluindo proteção contra CSRF, sanitização de entradas, 
 * criptografia e gestão segura de sessões e tokens JWT.
 */
class Security {

    // ==================================================================
    // ✅ MÉTODOS JWT (JSON WEB TOKEN) ADICIONADOS
    // ==================================================================

    /**
     * Gera um Token JWT (JSON Web Token).
     *
     * @param array $payload Os dados a serem incluídos no token.
     * @return string O token JWT gerado.
     */
    public static function generateJWT(array $payload): string {
        // Cabeçalho do token
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        
        // Codificar o cabeçalho e o payload em Base64URL
        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode(json_encode($payload));
        
        // Criar a assinatura usando a chave secreta definida em constants.php
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
        
        // Codificar a assinatura em Base64URL
        $base64UrlSignature = self::base64UrlEncode($signature);
        
        // Montar e retornar o token completo
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    /**
     * Valida um Token JWT.
     *
     * @param string $jwt O token a ser validado.
     * @return object|false Os dados do payload se o token for válido, ou false caso contrário.
     */
    public static function validateJWT(string $jwt) {
        // Dividir o token em 3 partes
        $tokenParts = explode('.', $jwt);
        if (count($tokenParts) !== 3) {
            return false;
        }

        $header = self::base64UrlDecode($tokenParts[0]);
        $payload = self::base64UrlDecode($tokenParts[1]);
        $signatureProvided = $tokenParts[2];

        // Se a decodificação falhar, o token é inválido
        if ($header === false || $payload === false) {
            return false;
        }

        // Verificar expiração do token (se existir no payload)
        $payloadData = json_decode($payload);
        if (isset($payloadData->exp) && $payloadData->exp < time()) {
            return false; // Token expirado
        }

        // Recriar a assinatura para verificação
        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payload);
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        // Comparar as assinaturas de forma segura para evitar timing attacks
        if (hash_equals($base64UrlSignature, $signatureProvided)) {
            return $payloadData; // Token é válido, retorna os dados
        }

        return false; // Assinatura inválida
    }

    /**
     * Codifica uma string para o formato Base64URL (seguro para URLs).
     */
    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decodifica uma string do formato Base64URL.
     */
    private static function base64UrlDecode(string $data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    // ==================================================================
    // MÉTODOS DE SEGURANÇA EXISTENTES (MANTIDOS)
    // ==================================================================

    /**
     * Gera um token CSRF
     * @return string Token CSRF
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verifica se o token CSRF é válido
     * @param string $token Token a ser verificado
     * @return bool Verdadeiro se o token for válido
     */
    public static function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Regenera o token CSRF
     * @return string Novo token CSRF
     */
    public static function regenerateCSRFToken() {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Sanitiza uma string para evitar injeção de código
     * @param string|array $input String ou array a ser sanitizado
     * @return string|array String ou array sanitizado
     */
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                $input[$key] = self::sanitizeInput($value);
            }
            return $input;
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Sanitiza um valor para uso seguro em SQL
     * @param mixed $input Valor a ser sanitizado
     * @param PDO $pdo Conexão PDO para escapar o valor
     * @return string Valor sanitizado para SQL
     */
    public static function sanitizeForSQL($input, $pdo) {
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                $input[$key] = self::sanitizeForSQL($value, $pdo);
            }
            return $input;
        }
        return $pdo->quote($input);
    }
    
    /**
     * Gera um hash seguro para senha
     * @param string $password Senha em texto puro
     * @return string Hash da senha
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
    }
    
    /**
     * Verifica se a senha corresponde ao hash armazenado
     * @param string $password Senha em texto puro
     * @param string $hash Hash armazenado
     * @return bool Verdadeiro se a senha corresponder ao hash
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Verifica se é necessário atualizar o hash da senha
     * @param string $hash Hash atual da senha
     * @return bool Verdadeiro se o hash precisar ser atualizado
     */
    public static function passwordNeedsRehash($hash) {
        return password_needs_rehash($hash, PASSWORD_DEFAULT, ['cost' => 12]);
    }
    
    /**
     * Gera um token seguro para recuperação de senha, etc.
     * @param int $length Comprimento do token (opcional)
     * @return string Token gerado
     */
    public static function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Verifica a força da senha
     * @param string $password Senha a ser verificada
     * @return array Resultado com pontuação e feedback
     */
    public static function checkPasswordStrength($password) {
        $score = 0;
        $feedback = [];
        if (defined('PASSWORD_MIN_LENGTH') && strlen($password) < PASSWORD_MIN_LENGTH) {
            $feedback[] = 'A senha deve ter pelo menos ' . PASSWORD_MIN_LENGTH . ' caracteres.';
        } else {
            $score += 1;
        }
        if (preg_match('/[A-Z]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Inclua pelo menos uma letra maiúscula.';
        }
        if (preg_match('/[a-z]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Inclua pelo menos uma letra minúscula.';
        }
        if (preg_match('/[0-9]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Inclua pelo menos um número.';
        }
        if (preg_match('/[^A-Za-z0-9]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Inclua pelo menos um caractere especial.';
        }
        
        $level = 'fraca';
        if ($score > 4) {
            $level = 'forte';
        } elseif ($score > 3) {
            $level = 'boa';
        } elseif ($score > 2) {
            $level = 'média';
        }
        
        return [
            'score' => $score,
            'level' => $level,
            'feedback' => $feedback
        ];
    }
    
    /**
     * Criptografa dados sensíveis
     * @param string $data Dados a serem criptografados
     * @param string $key Chave de criptografia (opcional)
     * @return string Dados criptografados
     */
    public static function encrypt($data, $key = null) {
        if ($key === null) {
            $key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'klube_cash_default_key';
        }
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Descriptografa dados
     * @param string $data Dados criptografados
     * @param string $key Chave de criptografia (opcional)
     * @return string|false Dados descriptografados ou falso em caso de erro
     */
    public static function decrypt($data, $key = null) {
        if ($key === null) {
            $key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'klube_cash_default_key';
        }
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Inicia uma sessão segura
     */
    public static function secureSessionStart() {
        ini_set('session.cookie_httponly', 1);
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', 1);
        }
        if (defined('SESSION_LIFETIME')) {
           ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['last_regeneration']) || (time() - $_SESSION['last_regeneration']) > 1800) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
    
    /**
     * Verifica se o endereço IP está bloqueado por tentativas excessivas de login
     * @param string $ip Endereço IP para verificar
     * @param PDO $pdo Conexão com o banco de dados
     * @return bool Verdadeiro se o IP estiver bloqueado
     */
    public static function isIPBlocked($ip, $pdo) {
        try {
            self::createIPBlockTableIfNotExists($pdo);
            $cleanupStmt = $pdo->prepare("DELETE FROM ip_block WHERE block_expiry < NOW()");
            $cleanupStmt->execute();
            $stmt = $pdo->prepare("SELECT * FROM ip_block WHERE ip = :ip AND block_expiry > NOW()");
            $stmt->bindParam(':ip', $ip);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('Erro ao verificar bloqueio de IP: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registra uma tentativa de login malsucedida
     * @param string $ip Endereço IP
     * @param PDO $pdo Conexão com o banco de dados
     */
    public static function registerFailedLogin($ip, $pdo) {
        try {
            self::createFailedLoginsTableIfNotExists($pdo);
            $cleanupStmt = $pdo->prepare("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $cleanupStmt->execute();
            $stmt = $pdo->prepare("INSERT INTO login_attempts (ip, attempt_time) VALUES (:ip, NOW())");
            $stmt->bindParam(':ip', $ip);
            $stmt->execute();
            
            $checkStmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM login_attempts WHERE ip = :ip AND attempt_time > DATE_SUB(NOW(), INTERVAL 2 HOUR)");
            $checkStmt->bindParam(':ip', $ip);
            $checkStmt->execute();
            $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['attempts'] >= 5) {
                self::blockIP($ip, 30, $pdo);
            }
        } catch (PDOException $e) {
            error_log('Erro ao registrar tentativa de login: ' . $e->getMessage());
        }
    }
    
    /**
     * Bloqueia um endereço IP
     * @param string $ip Endereço IP
     * @param int $minutes Duração do bloqueio em minutos
     * @param PDO $pdo Conexão com o banco de dados
     * @return bool Verdadeiro se o bloqueio foi bem-sucedido
     */
    public static function blockIP($ip, $minutes, $pdo) {
        try {
            self::createIPBlockTableIfNotExists($pdo);
            $expiry = date('Y-m-d H:i:s', strtotime("+{$minutes} minutes"));
            $checkStmt = $pdo->prepare("SELECT * FROM ip_block WHERE ip = :ip");
            $checkStmt->bindParam(':ip', $ip);
            $checkStmt->execute();
            if ($checkStmt->rowCount() > 0) {
                $stmt = $pdo->prepare("UPDATE ip_block SET block_expiry = :expiry WHERE ip = :ip");
            } else {
                $stmt = $pdo->prepare("INSERT INTO ip_block (ip, block_expiry) VALUES (:ip, :expiry)");
            }
            $stmt->bindParam(':ip', $ip);
            $stmt->bindParam(':expiry', $expiry);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Erro ao bloquear IP: ' . $e->getMessage());
            return false;
        }
    }
    
    private static function createIPBlockTableIfNotExists($pdo) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ip_block (id INT AUTO_INCREMENT PRIMARY KEY, ip VARCHAR(45) NOT NULL, block_expiry DATETIME NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX (ip), INDEX (block_expiry))");
        } catch (PDOException $e) {
            error_log('Erro ao criar tabela de bloqueio de IP: ' . $e->getMessage());
        }
    }
    
    private static function createFailedLoginsTableIfNotExists($pdo) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (id INT AUTO_INCREMENT PRIMARY KEY, ip VARCHAR(45) NOT NULL, attempt_time DATETIME NOT NULL, INDEX (ip), INDEX (attempt_time))");
        } catch (PDOException $e) {
            error_log('Erro ao criar tabela de tentativas de login: ' . $e->getMessage());
        }
    }
    
    public static function getClientIP() {
        $ip = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ipList[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
    
    public static function isAjaxRequest() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }
    
    public static function isSecureConnection() {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https');
    }
    
    public static function redirect($url, $statusCode = 302) {
        if (substr($url, 0, 4) !== 'http' && substr($url, 0, 1) !== '/') {
            $url = '/' . $url;
        }
        $url = str_replace(["\r", "\n", '%0d', '%0a'], '', $url);
        http_response_code($statusCode);
        header('Location: ' . $url);
        exit;
    }
}
?>