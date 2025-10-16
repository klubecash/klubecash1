<?php
/**
 * Helper para verificações simplificadas - Klube Cash v2.1
 * Sistema ultrarrápido que substitui toda complexidade de permissões
 */

require_once __DIR__ . '/../config/constants.php';

class StoreHelper {
    
    /**
     * Verificação obrigatória para páginas da loja - USA EM TODOS OS ARQUIVOS
     * Substitui todas as verificações complexas de permissão
     */
    public static function requireStoreAccess() {
        // Garantir que a sessão esteja iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Verificar autenticação básica
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
            header("Location: /login?error=session_expired");
            exit;
        }
        
        $userType = $_SESSION['user_type'];
        $userId = $_SESSION['user_id'];
        
        // Verificar tipo de usuário permitido
        $allowedTypes = [
            defined('USER_TYPE_STORE') ? USER_TYPE_STORE : 'loja',
            defined('USER_TYPE_EMPLOYEE') ? USER_TYPE_EMPLOYEE : 'funcionario'
        ];
        
        if (!in_array($userType, $allowedTypes)) {
            error_log("ACESSO NEGADO: Usuário {$userId} tipo '{$userType}' tentou acessar área da loja");
            header("Location: /login?error=access_denied");
            exit;
        }
        
        // CRÍTICO: Verificar se store_id existe
        if (!isset($_SESSION['store_id']) || empty($_SESSION['store_id'])) {
            error_log("ERRO CRÍTICO: store_id não definido para usuário {$userId} tipo {$userType}");
            // Log adicional para debug
            error_log("SESSION DEBUG: " . json_encode($_SESSION));

            // CORREÇÃO: Destruir sessão completamente antes de redirecionar
            session_destroy();
            session_write_close();
            setcookie(session_name(), '', 0, '/');

            header("Location: " . (defined('LOGIN_URL') ? LOGIN_URL : '/login') . "?error=" . urlencode("Sessão inválida. Faça login novamente."));
            exit;
        }
        
        // Log de acesso bem-sucedido
        if (defined('TRACK_USER_ACTIONS') && TRACK_USER_ACTIONS) {
            self::logUserAction($userId, 'store_access', [
                'page' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'store_id' => $_SESSION['store_id']
            ]);
        }
    }
    
    /**
     * Verifica se o usuário tem acesso à loja específica
     * SISTEMA SIMPLIFICADO: funcionários = lojistas (mesmo acesso)
     */
    public static function hasStoreAccess($userType = null, $userStoreId = null, $requiredStoreId = null) {
        // Se não passar parâmetros, usar dados da sessão atual
        if ($userType === null) {
            $userType = $_SESSION['user_type'] ?? '';
        }
        if ($userStoreId === null) {
            $userStoreId = self::getCurrentStoreId();
        }
        if ($requiredStoreId === null) {
            $requiredStoreId = $userStoreId; // Verificar acesso à própria loja
        }
        
        // Tipos permitidos
        $allowedTypes = [
            defined('USER_TYPE_STORE') ? USER_TYPE_STORE : 'loja',
            defined('USER_TYPE_EMPLOYEE') ? USER_TYPE_EMPLOYEE : 'funcionario'
        ];
        
        return in_array($userType, $allowedTypes) && 
               !empty($userStoreId) && 
               $userStoreId == $requiredStoreId;
    }
    
    /**
     * Obtém ID da loja do usuário atual
     * CORRIGIDO: Sempre usar store_id para consistência
     */
    public static function getCurrentStoreId() {
        if (!isset($_SESSION['user_type'])) {
            return null;
        }
        
        $userType = $_SESSION['user_type'];
        
        // Para lojistas e funcionários, usar store_id (setado no login)
        if (in_array($userType, ['loja', 'funcionario']) || 
            (defined('USER_TYPE_STORE') && $userType === USER_TYPE_STORE) ||
            (defined('USER_TYPE_EMPLOYEE') && $userType === USER_TYPE_EMPLOYEE)) {
            
            // Priorizar store_id, mas fallback para loja_vinculada_id se necessário
            return $_SESSION['store_id'] ?? $_SESSION['loja_vinculada_id'] ?? null;
        }
        
        return null;
    }
    
    /**
     * Verifica se pode gerenciar funcionários
     * SISTEMA SIMPLIFICADO: Todos têm mesmo acesso, mas mantém diferenciação visual
     */
    public static function canManageEmployees() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $userType = $_SESSION['user_type'] ?? '';
        
        // Sistema simplificado: lojistas sempre podem
        if ($userType === 'loja' || (defined('USER_TYPE_STORE') && $userType === USER_TYPE_STORE)) {
            return true;
        }
        
        // Funcionários também têm acesso igual (sistema simplificado)
        if ($userType === 'funcionario' || (defined('USER_TYPE_EMPLOYEE') && $userType === USER_TYPE_EMPLOYEE)) {
            // Opcional: manter diferenciação por subtipo para organização
            $subtipo = $_SESSION['subtipo_funcionario'] ?? $_SESSION['employee_subtype'] ?? 'funcionario';
            
            // No sistema simplificado, todos podem, mas log diferenciado
            if (defined('TRACK_USER_ACTIONS') && TRACK_USER_ACTIONS) {
                self::logUserAction($_SESSION['user_id'], 'employee_management_access', [
                    'subtipo' => $subtipo,
                    'access_level' => 'full' // Sistema simplificado
                ]);
            }
            
            return true; // SISTEMA SIMPLIFICADO: todos funcionários podem
        }
        
        return false;
    }
    
    /**
     * Registra ação do usuário para auditoria
     * SISTEMA COMPLETO de auditoria
     */
    public static function logUserAction($userId, $action, $details = []) {
        if (!defined('TRACK_USER_ACTIONS') || !TRACK_USER_ACTIONS) {
            return;
        }
        
        $logData = [
            'usuario_id' => $userId,
            'acao' => $action,
            'detalhes' => json_encode($details, JSON_UNESCAPED_UNICODE),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 200),
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'direct',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'session_id' => session_id(),
            'timestamp' => time(),
            'data_hora' => date('Y-m-d H:i:s')
        ];
        
        // Log para arquivo
        error_log("KLUBE_AUDIT: " . json_encode($logData));
        
        // Opcional: salvar no banco de dados se tabela existir
        try {
            if (class_exists('Database')) {
                $db = Database::getConnection();
                $checkTable = $db->query("SHOW TABLES LIKE 'audit_log'");
                
                if ($checkTable->rowCount() > 0) {
                    $stmt = $db->prepare("
                        INSERT INTO audit_log (usuario_id, acao, detalhes, ip, user_agent, data_hora)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $userId, 
                        $action, 
                        $logData['detalhes'], 
                        $logData['ip'], 
                        $logData['user_agent'], 
                        $logData['data_hora']
                    ]);
                }
            }
        } catch (Exception $e) {
            error_log("Erro ao salvar audit_log no banco: " . $e->getMessage());
        }
    }
    
    /**
     * Adiciona campo criado_por em transações/pagamentos
     * Para auditoria completa - saber quem criou cada registro
     */
    public static function addCreatedByField($data) {
        $constantsToCheck = [
            'LOG_TRANSACTION_CREATOR',
            'LOG_PAYMENT_CREATOR', 
            'LOG_EMPLOYEE_CREATOR'
        ];
        
        $shouldAddCreator = false;
        foreach ($constantsToCheck as $constant) {
            if (defined($constant) && constant($constant)) {
                $shouldAddCreator = true;
                break;
            }
        }
        
        if ($shouldAddCreator) {
            $createdByField = defined('AUDIT_CREATED_BY') ? AUDIT_CREATED_BY : 'criado_por';
            $data[$createdByField] = $_SESSION['user_id'] ?? null;
            
            // Adicionar timestamp se configurado
            if (defined('AUDIT_CREATED_AT')) {
                $data[AUDIT_CREATED_AT] = date('Y-m-d H:i:s');
            }
        }
        
        return $data;
    }
    
    /**
     * Obtém nome de exibição do usuário atual
     */
    public static function getCurrentUserName() {
        return $_SESSION['user_name'] ?? $_SESSION['user_email'] ?? 'Usuário';
    }
    
    /**
     * Obtém tipo de usuário atual
     */
    public static function getCurrentUserType() {
        return $_SESSION['user_type'] ?? null;
    }
    
    /**
     * Verifica se é lojista
     */
    public static function isStoreOwner() {
        $userType = $_SESSION['user_type'] ?? '';
        return $userType === 'loja' || (defined('USER_TYPE_STORE') && $userType === USER_TYPE_STORE);
    }
    
    /**
     * Verifica se é funcionário
     */
    public static function isEmployee() {
        $userType = $_SESSION['user_type'] ?? '';
        return $userType === 'funcionario' || (defined('USER_TYPE_EMPLOYEE') && $userType === USER_TYPE_EMPLOYEE);
    }
}
?>