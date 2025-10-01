<?php
// utils/Security.php
require_once __DIR__ . '/../config/constants.php';

/**
 * Classe Security - Utilitários de segurança
 * 
 * Esta classe fornece métodos para garantir a segurança do sistema,
 * incluindo proteção contra CSRF, sanitização de entradas, 
 * criptografia e gestão segura de sessões.
 */
class Security {
    /**
     * Gera um token CSRF
     * 
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
     * 
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
     * 
     * @return string Novo token CSRF
     */
    public static function regenerateCSRFToken() {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Sanitiza uma string para evitar injeção de código
     * 
     * @param string $input String a ser sanitizada
     * @return string String sanitizada
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
     * 
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
        
        // Para PDO, usamos prepared statements, mas isso ajuda com strings em queries dinâmicas
        return $pdo->quote($input);
    }
    
    /**
     * Gera um hash seguro para senha
     * 
     * @param string $password Senha em texto puro
     * @return string Hash da senha
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
    }
    
    /**
     * Verifica se a senha corresponde ao hash armazenado
     * 
     * @param string $password Senha em texto puro
     * @param string $hash Hash armazenado
     * @return bool Verdadeiro se a senha corresponder ao hash
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Verifica se é necessário atualizar o hash da senha
     * 
     * @param string $hash Hash atual da senha
     * @return bool Verdadeiro se o hash precisar ser atualizado
     */
    public static function passwordNeedsRehash($hash) {
        return password_needs_rehash($hash, PASSWORD_DEFAULT, ['cost' => 12]);
    }
    
    /**
     * Gera um token seguro para recuperação de senha, autenticação de dois fatores, etc.
     * 
     * @param int $length Comprimento do token (opcional)
     * @return string Token gerado
     */
    public static function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Verifica a força da senha
     * 
     * @param string $password Senha a ser verificada
     * @return array Resultado com pontuação e feedback
     */
    public static function checkPasswordStrength($password) {
        $score = 0;
        $feedback = [];
        
        // Comprimento mínimo
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            $feedback[] = 'A senha deve ter pelo menos ' . PASSWORD_MIN_LENGTH . ' caracteres.';
        } else {
            $score += 1;
        }
        
        // Verificar letras maiúsculas
        if (preg_match('/[A-Z]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Inclua pelo menos uma letra maiúscula.';
        }
        
        // Verificar letras minúsculas
        if (preg_match('/[a-z]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Inclua pelo menos uma letra minúscula.';
        }
        
        // Verificar números
        if (preg_match('/[0-9]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Inclua pelo menos um número.';
        }
        
        // Verificar caracteres especiais
        if (preg_match('/[^A-Za-z0-9]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Inclua pelo menos um caractere especial.';
        }
        
        // Definir nível com base na pontuação
        $level = '';
        if ($score <= 2) {
            $level = 'fraca';
        } elseif ($score <= 3) {
            $level = 'média';
        } elseif ($score <= 4) {
            $level = 'boa';
        } else {
            $level = 'forte';
        }
        
        return [
            'score' => $score,
            'level' => $level,
            'feedback' => $feedback
        ];
    }
    
    /**
     * Criptografa dados sensíveis
     * 
     * @param string $data Dados a serem criptografados
     * @param string $key Chave de criptografia (opcional)
     * @return string Dados criptografados
     */
    public static function encrypt($data, $key = null) {
        // Se a chave não for fornecida, usar uma chave padrão da aplicação
        if ($key === null) {
            $key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'klube_cash_default_key';
        }
        
        // Gerar um IV (Vetor de Inicialização) aleatório
        $iv = openssl_random_pseudo_bytes(16);
        
        // Criptografar os dados
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        // Combinar IV e dados criptografados em uma única string
        $result = base64_encode($iv . $encrypted);
        
        return $result;
    }
    
    /**
     * Descriptografa dados
     * 
     * @param string $data Dados criptografados
     * @param string $key Chave de criptografia (opcional)
     * @return string|false Dados descriptografados ou falso em caso de erro
     */
    public static function decrypt($data, $key = null) {
        // Se a chave não for fornecida, usar uma chave padrão da aplicação
        if ($key === null) {
            $key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'klube_cash_default_key';
        }
        
        // Decodificar a string base64
        $data = base64_decode($data);
        
        // Extrair o IV (primeiros 16 bytes)
        $iv = substr($data, 0, 16);
        
        // Extrair os dados criptografados
        $encrypted = substr($data, 16);
        
        // Descriptografar os dados
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
        
        return $decrypted;
    }
    
    /**
     * Inicia uma sessão segura
     * 
     * @return void
     */
    public static function secureSessionStart() {
        // Definir configurações de segurança para cookies de sessão
        ini_set('session.cookie_httponly', 1);
        
        // Se estiver em HTTPS, definir cookies seguros
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', 1);
        }
        
        // Definir o tempo de vida da sessão
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
        
        // Iniciar a sessão
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Regenerar ID da sessão a cada 30 minutos para prevenir fixação de sessão
        if (!isset($_SESSION['last_regeneration']) || 
            (time() - $_SESSION['last_regeneration']) > 1800) {
            
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
    
    /**
     * Verifica se o endereço IP está bloqueado por tentativas excessivas de login
     * 
     * @param string $ip Endereço IP para verificar
     * @param PDO $pdo Conexão com o banco de dados
     * @return bool Verdadeiro se o IP estiver bloqueado
     */
    public static function isIPBlocked($ip, $pdo) {
        try {
            // Verificar se a tabela existe
            self::createIPBlockTableIfNotExists($pdo);
            
            // Limpar registros antigos
            $cleanupStmt = $pdo->prepare("DELETE FROM ip_block WHERE block_expiry < NOW()");
            $cleanupStmt->execute();
            
            // Verificar se o IP está bloqueado
            $stmt = $pdo->prepare("SELECT * FROM ip_block WHERE ip = :ip AND block_expiry > NOW()");
            $stmt->bindParam(':ip', $ip);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('Erro ao verificar bloqueio de IP: ' . $e->getMessage());
            return false; // Em caso de erro, permitir o acesso
        }
    }
    
    /**
     * Registra uma tentativa de login malsucedida
     * 
     * @param string $ip Endereço IP
     * @param PDO $pdo Conexão com o banco de dados
     * @return void
     */
    public static function registerFailedLogin($ip, $pdo) {
        try {
            // Verificar se a tabela existe
            self::createFailedLoginsTableIfNotExists($pdo);
            
            // Limpar registros antigos (mais de 24 horas)
            $cleanupStmt = $pdo->prepare("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $cleanupStmt->execute();
            
            // Registrar nova tentativa
            $stmt = $pdo->prepare("INSERT INTO login_attempts (ip, attempt_time) VALUES (:ip, NOW())");
            $stmt->bindParam(':ip', $ip);
            $stmt->execute();
            
            // Verificar número de tentativas nas últimas 2 horas
            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) as attempts 
                FROM login_attempts 
                WHERE ip = :ip AND attempt_time > DATE_SUB(NOW(), INTERVAL 2 HOUR)
            ");
            $checkStmt->bindParam(':ip', $ip);
            $checkStmt->execute();
            $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            // Se houver mais de 5 tentativas, bloquear o IP por 30 minutos
            if ($result['attempts'] >= 5) {
                self::blockIP($ip, 30, $pdo);
            }
        } catch (PDOException $e) {
            error_log('Erro ao registrar tentativa de login: ' . $e->getMessage());
        }
    }
    
    /**
     * Bloqueia um endereço IP
     * 
     * @param string $ip Endereço IP
     * @param int $minutes Duração do bloqueio em minutos
     * @param PDO $pdo Conexão com o banco de dados
     * @return bool Verdadeiro se o bloqueio foi bem-sucedido
     */
    public static function blockIP($ip, $minutes, $pdo) {
        try {
            // Verificar se a tabela existe
            self::createIPBlockTableIfNotExists($pdo);
            
            // Calcular data de expiração
            $expiry = date('Y-m-d H:i:s', strtotime("+{$minutes} minutes"));
            
            // Verificar se o IP já está bloqueado
            $checkStmt = $pdo->prepare("SELECT * FROM ip_block WHERE ip = :ip");
            $checkStmt->bindParam(':ip', $ip);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                // Atualizar bloqueio existente
                $stmt = $pdo->prepare("UPDATE ip_block SET block_expiry = :expiry WHERE ip = :ip");
            } else {
                // Inserir novo bloqueio
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
    
    /**
     * Cria a tabela de bloqueio de IP se não existir
     * 
     * @param PDO $pdo Conexão com o banco de dados
     * @return void
     */
    private static function createIPBlockTableIfNotExists($pdo) {
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS ip_block (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ip VARCHAR(45) NOT NULL,
                    block_expiry DATETIME NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (ip),
                    INDEX (block_expiry)
                )
            ");
        } catch (PDOException $e) {
            error_log('Erro ao criar tabela de bloqueio de IP: ' . $e->getMessage());
        }
    }
    
    /**
     * Cria a tabela de tentativas de login se não existir
     * 
     * @param PDO $pdo Conexão com o banco de dados
     * @return void
     */
    private static function createFailedLoginsTableIfNotExists($pdo) {
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS login_attempts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ip VARCHAR(45) NOT NULL,
                    attempt_time DATETIME NOT NULL,
                    INDEX (ip),
                    INDEX (attempt_time)
                )
            ");
        } catch (PDOException $e) {
            error_log('Erro ao criar tabela de tentativas de login: ' . $e->getMessage());
        }
    }
    
    /**
     * Obtém o endereço IP real do usuário, levando em consideração proxies
     * 
     * @return string Endereço IP
     */
    public static function getClientIP() {
        $ip = '';
        
        // Verificar diferentes cabeçalhos HTTP que podem conter o IP real
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // HTTP_X_FORWARDED_FOR pode conter múltiplos IPs
            $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ipList[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        // Validar o IP
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
    
    /**
     * Verifica se a requisição é AJAX
     * 
     * @return bool Verdadeiro se for uma requisição AJAX
     */
    public static function isAjaxRequest() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }
    
    /**
     * Verifica se a requisição atual é HTTPS
     * 
     * @return bool Verdadeiro se for HTTPS
     */
    public static function isSecureConnection() {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
               ($_SERVER['SERVER_PORT'] == 443) ||
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https');
    }
    
    /**
     * Redirecionamento seguro
     * 
     * @param string $url URL para redirecionar
     * @param int $statusCode Código de status HTTP (opcional)
     * @return void
     */
    public static function redirect($url, $statusCode = 302) {
        // Certificar-se de que a URL começa com HTTP ou é uma URL relativa
        if (substr($url, 0, 4) !== 'http' && substr($url, 0, 1) !== '/') {
            $url = '/' . $url;
        }
        
        // Prevenir injeção de cabeçalho
        $url = str_replace(["\r", "\n", '%0d', '%0a'], '', $url);
        
        // Definir o código de status
        http_response_code($statusCode);
        
        // Redirecionar
        header('Location: ' . $url);
        exit;
    }
}
?>