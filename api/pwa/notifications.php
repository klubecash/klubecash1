<?php
/**
 * KLUBE CASH - API PUSH NOTIFICATIONS
 * Gerenciamento completo de push notifications para PWA
 * Registro de dispositivos, validação de tokens e envio de notificações
 * 
 * @version 2.0
 * @author Klube Cash Development Team
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../utils/Security.php';
require_once __DIR__ . '/../../utils/Validator.php';
require_once __DIR__ . '/../../utils/Logger.php';

// Headers para PWA e CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . SITE_URL);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Verificar se push notifications estão habilitadas
if (!push_notifications_enabled()) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Push notifications não estão disponíveis no momento',
        'code' => 'NOTIFICATIONS_DISABLED'
    ]);
    exit;
}

// Handles preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

class NotificationManager {
    private $db;
    private $logger;
    private $validator;
    private $security;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->logger = new Logger();
        $this->validator = new Validator();
        $this->security = new Security();
        
        // Verificar tabelas necessárias
        $this->ensureTablesExist();
    }
    
    /**
     * Roteamento principal
     */
    public function handleRequest() {
        try {
            $action = $_GET['action'] ?? 'config';
            $method = $_SERVER['REQUEST_METHOD'];
            
            // Log da requisição
            $this->logger->info("Notification API: {$method} /{$action}", [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
            switch ($action) {
                case 'config':
                    return $this->getConfig();
                    
                case 'register':
                    if ($method !== 'POST') {
                        return $this->errorResponse('Método não permitido', 405);
                    }
                    return $this->registerSubscription();
                    
                case 'unregister':
                    if ($method !== 'POST') {
                        return $this->errorResponse('Método não permitido', 405);
                    }
                    return $this->unregisterSubscription();
                    
                case 'validate':
                    if ($method !== 'POST') {
                        return $this->errorResponse('Método não permitido', 405);
                    }
                    return $this->validateSubscription();
                    
                case 'preferences':
                    if ($method !== 'POST') {
                        return $this->errorResponse('Método não permitido', 405);
                    }
                    return $this->updatePreferences();
                    
                case 'sync':
                    return $this->syncNotifications();
                    
                case 'test':
                    if ($method !== 'POST') {
                        return $this->errorResponse('Método não permitido', 405);
                    }
                    return $this->testNotification();
                    
                default:
                    return $this->errorResponse('Ação não encontrada', 404);
            }
            
        } catch (Exception $e) {
            $this->logger->error("Notification API Error: " . $e->getMessage(), [
                'action' => $action ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse('Erro interno do servidor', 500);
        }
    }
    
    /**
     * Configuração do sistema de notificações
     */
    private function getConfig() {
        try {
            $config = [
                'vapidPublicKey' => VAPID_PUBLIC_KEY,
                'supported' => true,
                'endpoints' => [
                    'register' => PUSH_REGISTER_URL,
                    'unregister' => PUSH_UNREGISTER_URL,
                    'validate' => PUSH_VALIDATE_URL,
                    'preferences' => PUSH_PREFERENCES_URL,
                    'sync' => PUSH_SYNC_URL
                ],
                'settings' => [
                    'userVisibleOnly' => true,
                    'showBadge' => true,
                    'enableVibration' => true,
                    'enableSound' => true
                ],
                'limits' => [
                    'dailyLimit' => NOTIFICATION_DAILY_LIMIT_PER_USER,
                    'hourlyLimit' => NOTIFICATION_HOURLY_LIMIT_PER_USER,
                    'cooldown' => NOTIFICATION_SAME_TYPE_COOLDOWN
                ],
                'types' => [
                    'cashback_received' => [
                        'name' => 'Cashback Recebido',
                        'description' => 'Quando você recebe cashback de uma compra',
                        'default' => true
                    ],
                    'cashback_available' => [
                        'name' => 'Cashback Disponível',
                        'description' => 'Quando seu cashback fica disponível para uso',
                        'default' => true
                    ],
                    'payment_confirmed' => [
                        'name' => 'Pagamento Confirmado',
                        'description' => 'Confirmação de pagamentos e transações',
                        'default' => true
                    ],
                    'promotional' => [
                        'name' => 'Promoções',
                        'description' => 'Ofertas especiais e promoções',
                        'default' => false
                    ],
                    'system_alerts' => [
                        'name' => 'Alertas do Sistema',
                        'description' => 'Informações importantes sobre sua conta',
                        'default' => true
                    ],
                    'store_updates' => [
                        'name' => 'Atualizações de Lojas',
                        'description' => 'Novidades das suas lojas favoritas',
                        'default' => false
                    ]
                ]
            ];
            
            return $this->successResponse($config);
            
        } catch (Exception $e) {
            $this->logger->error("Erro ao obter configuração: " . $e->getMessage());
            return $this->errorResponse('Erro ao carregar configurações');
        }
    }
    
    /**
     * Registro de nova subscrição
     */
    private function registerSubscription() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                return $this->errorResponse('Dados inválidos');
            }
            
            // Validar dados obrigatórios
            $required = ['subscription', 'userId', 'deviceId'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    return $this->errorResponse("Campo obrigatório: {$field}");
                }
            }
            
            $subscription = $input['subscription'];
            $userId = (int) $input['userId'];
            $deviceId = $this->security->sanitize($input['deviceId']);
            $userAgent = $input['userAgent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '';
            $preferences = $input['preferences'] ?? [];
            
            // Validar subscription
            if (empty($subscription['endpoint']) || empty($subscription['keys'])) {
                return $this->errorResponse('Dados de subscrição inválidos');
            }
            
            // Verificar se usuário existe
            if (!$this->userExists($userId)) {
                return $this->errorResponse('Usuário não encontrado');
            }
            
            // Verificar se já existe subscrição para este dispositivo
            $existingId = $this->getExistingSubscription($userId, $deviceId);
            
            if ($existingId) {
                // Atualizar subscrição existente
                $subscriptionId = $this->updateSubscription(
                    $existingId,
                    $subscription,
                    $userAgent,
                    $preferences
                );
            } else {
                // Criar nova subscrição
                $subscriptionId = $this->createSubscription(
                    $userId,
                    $deviceId,
                    $subscription,
                    $userAgent,
                    $preferences
                );
            }
            
            if ($subscriptionId) {
                $this->logger->info("Subscrição registrada", [
                    'subscription_id' => $subscriptionId,
                    'user_id' => $userId,
                    'device_id' => $deviceId
                ]);
                
                return $this->successResponse([
                    'subscriptionId' => $subscriptionId,
                    'message' => 'Subscrição registrada com sucesso'
                ]);
            } else {
                throw new Exception('Falha ao salvar subscrição');
            }
            
        } catch (Exception $e) {
            $this->logger->error("Erro ao registrar subscrição: " . $e->getMessage());
            return $this->errorResponse('Erro ao registrar subscrição');
        }
    }
    
    /**
     * Cancelamento de subscrição
     */
    private function unregisterSubscription() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['userId']) || empty($input['deviceId'])) {
                return $this->errorResponse('userId e deviceId são obrigatórios');
            }
            
            $userId = (int) $input['userId'];
            $deviceId = $this->security->sanitize($input['deviceId']);
            
            $stmt = $this->db->prepare("
                UPDATE push_subscriptions 
                SET status = 'cancelled', 
                    updated_at = NOW() 
                WHERE user_id = ? 
                AND device_id = ? 
                AND status = 'active'
            ");
            
            $stmt->execute([$userId, $deviceId]);
            
            if ($stmt->rowCount() > 0) {
                $this->logger->info("Subscrição cancelada", [
                    'user_id' => $userId,
                    'device_id' => $deviceId
                ]);
                
                return $this->successResponse([
                    'message' => 'Subscrição cancelada com sucesso'
                ]);
            } else {
                return $this->errorResponse('Subscrição não encontrada');
            }
            
        } catch (Exception $e) {
            $this->logger->error("Erro ao cancelar subscrição: " . $e->getMessage());
            return $this->errorResponse('Erro ao cancelar subscrição');
        }
    }
    
    /**
     * Validação de subscrição
     */
    private function validateSubscription() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['userId']) || empty($input['deviceId'])) {
                return $this->errorResponse('userId e deviceId são obrigatórios');
            }
            
            $userId = (int) $input['userId'];
            $deviceId = $this->security->sanitize($input['deviceId']);
            
            $stmt = $this->db->prepare("
                SELECT id, endpoint, p256dh_key, auth_key, 
                       preferences, created_at, last_used
                FROM push_subscriptions 
                WHERE user_id = ? 
                AND device_id = ? 
                AND status = 'active'
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            
            $stmt->execute([$userId, $deviceId]);
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($subscription) {
                // Atualizar último uso
                $updateStmt = $this->db->prepare("
                    UPDATE push_subscriptions 
                    SET last_used = NOW() 
                    WHERE id = ?
                ");
                $updateStmt->execute([$subscription['id']]);
                
                return $this->successResponse([
                    'valid' => true,
                    'subscriptionId' => $subscription['id'],
                    'preferences' => json_decode($subscription['preferences'], true),
                    'registeredAt' => $subscription['created_at']
                ]);
            } else {
                return $this->successResponse([
                    'valid' => false,
                    'message' => 'Subscrição não encontrada'
                ]);
            }
            
        } catch (Exception $e) {
            $this->logger->error("Erro ao validar subscrição: " . $e->getMessage());
            return $this->errorResponse('Erro ao validar subscrição');
        }
    }
    
    /**
     * Atualização de preferências
     */
    private function updatePreferences() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['userId']) || empty($input['deviceId']) || !isset($input['preferences'])) {
                return $this->errorResponse('userId, deviceId e preferences são obrigatórios');
            }
            
            $userId = (int) $input['userId'];
            $deviceId = $this->security->sanitize($input['deviceId']);
            $preferences = json_encode($input['preferences']);
            
            $stmt = $this->db->prepare("
                UPDATE push_subscriptions 
                SET preferences = ?, 
                    updated_at = NOW() 
                WHERE user_id = ? 
                AND device_id = ? 
                AND status = 'active'
            ");
            
            $stmt->execute([$preferences, $userId, $deviceId]);
            
            if ($stmt->rowCount() > 0) {
                return $this->successResponse([
                    'message' => 'Preferências atualizadas com sucesso'
                ]);
            } else {
                return $this->errorResponse('Subscrição não encontrada');
            }
            
        } catch (Exception $e) {
            $this->logger->error("Erro ao atualizar preferências: " . $e->getMessage());
            return $this->errorResponse('Erro ao atualizar preferências');
        }
    }
    
    /**
     * Sincronização de notificações
     */
    private function syncNotifications() {
        try {
            $userId = $_GET['userId'] ?? null;
            
            if (!$userId) {
                return $this->errorResponse('userId é obrigatório');
            }
            
            $userId = (int) $userId;
            $limit = min((int) ($_GET['limit'] ?? 50), 100);
            $offset = max((int) ($_GET['offset'] ?? 0), 0);
            
            $stmt = $this->db->prepare("
                SELECT id, title, body, type, data, 
                       created_at, read_at, clicked_at
                FROM user_notifications 
                WHERE user_id = ?
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?
            ");
            
            $stmt->execute([$userId, $limit, $offset]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Contar total
            $countStmt = $this->db->prepare("
                SELECT COUNT(*) as total
                FROM user_notifications 
                WHERE user_id = ?
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            
            $countStmt->execute([$userId]);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            return $this->successResponse([
                'notifications' => $notifications,
                'pagination' => [
                    'total' => (int) $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'hasMore' => ($offset + $limit) < $total
                ]
            ]);
            
        } catch (Exception $e) {
            $this->logger->error("Erro ao sincronizar notificações: " . $e->getMessage());
            return $this->errorResponse('Erro ao sincronizar notificações');
        }
    }
    
    /**
     * Teste de notificação
     */
    private function testNotification() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['userId'])) {
                return $this->errorResponse('userId é obrigatório');
            }
            
            $userId = (int) $input['userId'];
            $deviceId = $input['deviceId'] ?? null;
            
            // Buscar subscrições ativas
            $query = "
                SELECT id, endpoint, p256dh_key, auth_key 
                FROM push_subscriptions 
                WHERE user_id = ? 
                AND status = 'active'
            ";
            
            $params = [$userId];
            
            if ($deviceId) {
                $query .= " AND device_id = ?";
                $params[] = $deviceId;
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($subscriptions)) {
                return $this->errorResponse('Nenhuma subscrição ativa encontrada');
            }
            
            $sent = 0;
            $failed = 0;
            
            foreach ($subscriptions as $subscription) {
                $payload = [
                    'title' => 'Teste - Klube Cash',
                    'body' => 'Esta é uma notificação de teste. Tudo funcionando perfeitamente! 🎉',
                    'icon' => NOTIFICATION_ICON_DEFAULT,
                    'badge' => NOTIFICATION_BADGE_DEFAULT,
                    'data' => [
                        'type' => 'test',
                        'timestamp' => time(),
                        'url' => '/client/dashboard'
                    ]
                ];
                
                if ($this->sendPushNotification($subscription, $payload)) {
                    $sent++;
                } else {
                    $failed++;
                }
            }
            
            return $this->successResponse([
                'message' => 'Teste de notificação enviado',
                'sent' => $sent,
                'failed' => $failed,
                'total' => count($subscriptions)
            ]);
            
        } catch (Exception $e) {
            $this->logger->error("Erro ao enviar teste: " . $e->getMessage());
            return $this->errorResponse('Erro ao enviar notificação de teste');
        }
    }
    
    /**
     * Helpers privados
     */
    private function userExists($userId) {
        $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->rowCount() > 0;
    }
    
    private function getExistingSubscription($userId, $deviceId) {
        $stmt = $this->db->prepare("
            SELECT id FROM push_subscriptions 
            WHERE user_id = ? AND device_id = ? AND status = 'active'
        ");
        $stmt->execute([$userId, $deviceId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['id'] : null;
    }
    
    private function createSubscription($userId, $deviceId, $subscription, $userAgent, $preferences) {
        $stmt = $this->db->prepare("
            INSERT INTO push_subscriptions 
            (user_id, device_id, endpoint, p256dh_key, auth_key, user_agent, preferences, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        
        $stmt->execute([
            $userId,
            $deviceId,
            $subscription['endpoint'],
            $subscription['keys']['p256dh'],
            $subscription['keys']['auth'],
            $userAgent,
            json_encode($preferences)
        ]);
        
        return $this->db->lastInsertId();
    }
    
    private function updateSubscription($subscriptionId, $subscription, $userAgent, $preferences) {
        $stmt = $this->db->prepare("
            UPDATE push_subscriptions 
            SET endpoint = ?, p256dh_key = ?, auth_key = ?, 
                user_agent = ?, preferences = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $subscription['endpoint'],
            $subscription['keys']['p256dh'],
            $subscription['keys']['auth'],
            $userAgent,
            json_encode($preferences),
            $subscriptionId
        ]);
        
        return $subscriptionId;
    }
    
    private function sendPushNotification($subscription, $payload) {
        // Implementação básica - em produção usar biblioteca como web-push
        try {
            $headers = [
                'Content-Type: application/json',
                'TTL: 86400'
            ];
            
            if (!empty(VAPID_PRIVATE_KEY)) {
                // Aqui seria implementada a assinatura VAPID
                // Por simplicidade, implementação básica
            }
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => implode("\r\n", $headers),
                    'content' => json_encode($payload)
                ]
            ]);
            
            $result = @file_get_contents($subscription['endpoint'], false, $context);
            
            return $result !== false;
            
        } catch (Exception $e) {
            $this->logger->error("Erro ao enviar push: " . $e->getMessage());
            return false;
        }
    }
    
    private function ensureTablesExist() {
        try {
            // Criar tabela de subscrições se não existir
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS push_subscriptions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    device_id VARCHAR(100) NOT NULL,
                    endpoint TEXT NOT NULL,
                    p256dh_key VARCHAR(255) NOT NULL,
                    auth_key VARCHAR(255) NOT NULL,
                    user_agent TEXT,
                    preferences JSON,
                    status ENUM('active', 'cancelled', 'expired') DEFAULT 'active',
                    last_used TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_user_device (user_id, device_id),
                    INDEX idx_status (status),
                    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // Criar tabela de notificações de usuário se não existir
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS user_notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    body TEXT NOT NULL,
                    type VARCHAR(50) NOT NULL,
                    data JSON,
                    read_at TIMESTAMP NULL,
                    clicked_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_created (user_id, created_at),
                    INDEX idx_type (type),
                    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
        } catch (Exception $e) {
            $this->logger->error("Erro ao criar tabelas: " . $e->getMessage());
        }
    }
    
    private function successResponse($data = null) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $data,
            'timestamp' => date('c')
        ]);
    }
    
    private function errorResponse($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'timestamp' => date('c')
        ]);
    }
}

// Executar
try {
    $manager = new NotificationManager();
    $manager->handleRequest();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor',
        'timestamp' => date('c')
    ]);
}
?>