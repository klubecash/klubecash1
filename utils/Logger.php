<?php
// utils/Logger.php
require_once __DIR__ . '/../config/constants.php';

/**
 * Classe Logger - Sistema de registro de logs
 * 
 * Esta classe gerencia o registro de logs do sistema Klube Cash,
 * permitindo rastrear erros, atividades de usuários e transações.
 */
class Logger {
    // Constantes para níveis de log
    const ERROR = 'ERROR';
    const WARNING = 'WARNING';
    const INFO = 'INFO';
    const DEBUG = 'DEBUG';
    
    // Diretório de logs
    private static $logDir;
    
    // Nome do arquivo de log atual
    private static $currentLogFile;
    
    // Nível mínimo de log para registro
    private static $minLevel;
    
    /**
     * Inicializa o logger
     * 
     * @return void
     */
    private static function init() {
        // Definir diretório de logs
        self::$logDir = defined('LOGS_DIR') ? LOGS_DIR : __DIR__ . '/../logs';
        
        // Verificar se o diretório existe, caso contrário, criar
        if (!is_dir(self::$logDir)) {
            if (!mkdir(self::$logDir, 0755, true)) {
                error_log('Não foi possível criar o diretório de logs: ' . self::$logDir);
            }
        }
        
        // Definir o nome do arquivo baseado na data atual
        self::$currentLogFile = self::$logDir . '/log-' . date('Y-m-d') . '.txt';
        
        // Definir nível mínimo de log
        self::$minLevel = defined('LOG_LEVEL') ? LOG_LEVEL : self::INFO;
    }
    
    /**
     * Determina se um nível de log deve ser registrado
     * 
     * @param string $level Nível de log a verificar
     * @return bool Verdadeiro se o nível deve ser registrado
     */
    private static function shouldLog($level) {
        $levels = [
            self::DEBUG => 0,
            self::INFO => 1,
            self::WARNING => 2,
            self::ERROR => 3
        ];
        
        // Se o nível não for reconhecido, registrar por segurança
        if (!isset($levels[$level])) {
            return true;
        }
        
        // Só registrar se o nível for maior ou igual ao mínimo
        return $levels[$level] >= $levels[self::$minLevel];
    }
    
    /**
     * Registra uma mensagem de log
     * 
     * @param string $level Nível de log
     * @param string $message Mensagem a ser registrada
     * @param array $context Dados contextuais adicionais
     * @return bool Verdadeiro se o log foi registrado com sucesso
     */
    public static function log($level, $message, $context = []) {
        // Inicializar se necessário
        if (!isset(self::$logDir)) {
            self::init();
        }
        
        // Verificar se este nível deve ser registrado
        if (!self::shouldLog($level)) {
            return false;
        }
        
        // Formatar a data e hora
        $dateTime = date('Y-m-d H:i:s');
        
        // Adicionar informações de IP e sessão aos dados de contexto
        if (!isset($context['ip'])) {
            $context['ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
        
        if (!isset($context['session_id']) && session_status() === PHP_SESSION_ACTIVE) {
            $context['session_id'] = session_id();
        }
        
        if (!isset($context['user_id']) && isset($_SESSION['user_id'])) {
            $context['user_id'] = $_SESSION['user_id'];
        }
        
        // Formatar a mensagem de log
        $logEntry = sprintf(
            "[%s] [%s] %s %s\n",
            $dateTime,
            $level,
            $message,
            !empty($context) ? json_encode($context) : ''
        );
        
        // Escrever no arquivo de log
        $result = file_put_contents(self::$currentLogFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        return $result !== false;
    }
    
    /**
     * Registra um erro
     * 
     * @param string $message Mensagem de erro
     * @param array $context Dados contextuais
     * @return bool Resultado da operação
     */
    public static function error($message, $context = []) {
        return self::log(self::ERROR, $message, $context);
    }
    
    /**
     * Registra um aviso
     * 
     * @param string $message Mensagem de aviso
     * @param array $context Dados contextuais
     * @return bool Resultado da operação
     */
    public static function warning($message, $context = []) {
        return self::log(self::WARNING, $message, $context);
    }
    
    /**
     * Registra uma informação
     * 
     * @param string $message Mensagem informativa
     * @param array $context Dados contextuais
     * @return bool Resultado da operação
     */
    public static function info($message, $context = []) {
        return self::log(self::INFO, $message, $context);
    }
    
    /**
     * Registra uma mensagem de debug
     * 
     * @param string $message Mensagem de debug
     * @param array $context Dados contextuais
     * @return bool Resultado da operação
     */
    public static function debug($message, $context = []) {
        return self::log(self::DEBUG, $message, $context);
    }
    
    /**
     * Registra uma ação do usuário
     * 
     * @param int $userId ID do usuário
     * @param string $action Ação realizada
     * @param array $details Detalhes adicionais
     * @return bool Resultado da operação
     */
    public static function logUserAction($userId, $action, $details = []) {
        $context = [
            'user_id' => $userId,
            'details' => $details
        ];
        
        return self::info("User Action: {$action}", $context);
    }
    
    /**
     * Registra uma transação de cashback
     * 
     * @param int $transactionId ID da transação
     * @param int $userId ID do usuário
     * @param int $storeId ID da loja
     * @param float $amount Valor da transação
     * @param float $cashback Valor do cashback
     * @param string $status Status da transação
     * @return bool Resultado da operação
     */
    public static function logTransaction($transactionId, $userId, $storeId, $amount, $cashback, $status) {
        $context = [
            'transaction_id' => $transactionId,
            'user_id' => $userId,
            'store_id' => $storeId,
            'amount' => $amount,
            'cashback' => $cashback,
            'status' => $status
        ];
        
        return self::info("Transaction: {$status}", $context);
    }
    
    /**
     * Registra uma atividade relacionada a uma loja
     * 
     * @param int $storeId ID da loja
     * @param string $action Ação realizada
     * @param array $details Detalhes adicionais
     * @return bool Resultado da operação
     */
    public static function logStoreActivity($storeId, $action, $details = []) {
        $context = [
            'store_id' => $storeId,
            'details' => $details
        ];
        
        return self::info("Store Activity: {$action}", $context);
    }
    
    /**
     * Registra um erro de sistema
     * 
     * @param string $errorMessage Mensagem de erro
     * @param string $file Arquivo onde ocorreu o erro
     * @param int $line Linha onde ocorreu o erro
     * @param array $trace Rastro da pilha (opcional)
     * @return bool Resultado da operação
     */
    public static function logSystemError($errorMessage, $file, $line, $trace = null) {
        $context = [
            'file' => $file,
            'line' => $line
        ];
        
        if ($trace !== null) {
            $context['trace'] = $trace;
        }
        
        return self::error("System Error: {$errorMessage}", $context);
    }
    
    /**
     * Registra uma tentativa de login
     * 
     * @param string $email Email utilizado
     * @param bool $success Indica se o login foi bem-sucedido
     * @param string $ip Endereço IP (opcional)
     * @return bool Resultado da operação
     */
    public static function logLoginAttempt($email, $success, $ip = null) {
        $action = $success ? 'Login Success' : 'Login Failed';
        
        $context = [
            'email' => $email,
            'success' => $success
        ];
        
        if ($ip !== null) {
            $context['ip'] = $ip;
        }
        
        return self::info("Auth: {$action}", $context);
    }
    
    /**
     * Limpa logs antigos
     * 
     * @param int $daysToKeep Número de dias para manter os logs
     * @return int Número de arquivos removidos
     */
    public static function cleanOldLogs($daysToKeep = 30) {
        // Inicializar se necessário
        if (!isset(self::$logDir)) {
            self::init();
        }
        
        $counter = 0;
        $cutoffDate = time() - ($daysToKeep * 86400);
        
        // Listar arquivos de log
        $files = glob(self::$logDir . '/log-*.txt');
        
        foreach ($files as $file) {
            // Extrair a data do nome do arquivo
            if (preg_match('/log-(\d{4}-\d{2}-\d{2})\.txt$/', $file, $matches)) {
                $fileDate = strtotime($matches[1]);
                
                // Se o arquivo for mais antigo que a data de corte, remover
                if ($fileDate < $cutoffDate) {
                    if (unlink($file)) {
                        $counter++;
                    }
                }
            }
        }
        
        return $counter;
    }
    
    /**
     * Obtém o log atual como um array
     * 
     * @param int $lines Número de linhas para retornar (0 = todas)
     * @return array Linhas do log
     */
    public static function getLogLines($lines = 100) {
        // Inicializar se necessário
        if (!isset(self::$logDir)) {
            self::init();
        }
        
        // Verificar se o arquivo existe
        if (!file_exists(self::$currentLogFile)) {
            return [];
        }
        
        $content = file(self::$currentLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($lines > 0 && count($content) > $lines) {
            // Retornar apenas as últimas linhas
            return array_slice($content, -$lines);
        }
        
        return $content;
    }
    
    /**
     * Obtém logs de um período específico
     * 
     * @param string $startDate Data inicial (formato: Y-m-d)
     * @param string $endDate Data final (formato: Y-m-d)
     * @param string $level Nível de log (opcional)
     * @return array Logs do período
     */
    public static function getLogsByDateRange($startDate, $endDate, $level = null) {
        // Inicializar se necessário
        if (!isset(self::$logDir)) {
            self::init();
        }
        
        $logs = [];
        $start = strtotime($startDate);
        $end = strtotime($endDate);
        
        // Percorrer o intervalo de datas
        for ($date = $start; $date <= $end; $date = strtotime('+1 day', $date)) {
            $logFile = self::$logDir . '/log-' . date('Y-m-d', $date) . '.txt';
            
            if (file_exists($logFile)) {
                $content = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                
                foreach ($content as $line) {
                    // Se um nível específico foi solicitado, filtrar
                    if ($level !== null && !strpos($line, "[{$level}]")) {
                        continue;
                    }
                    
                    $logs[] = $line;
                }
            }
        }
        
        return $logs;
    }
}
?>