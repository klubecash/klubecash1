<?php
/**
 * Klube Cash PWA - API de Sincronização v1.0
 * Endpoint para sincronização de dados offline
 * Upload/download de dados para PWA
 */

// Configurações iniciais
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Tratar requisições OPTIONS para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Incluir arquivos necessários
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../utils/Security.php';
require_once '../../controllers/AuthController.php';
require_once '../../controllers/ClientController.php';
require_once '../../controllers/TransactionController.php';
require_once '../../utils/Logger.php';

/**
 * Classe para gerenciar sincronização PWA
 */
class PWASyncController {
    private $pdo;
    private $userId;
    private $userType;
    private $logger;

    public function __construct($userId, $userType) {
        $this->pdo = Database::getInstance()->getConnection();
        $this->userId = $userId;
        $this->userType = $userType;
        $this->logger = new Logger('pwa_sync');
    }

    // === MÉTODO PRINCIPAL DE SINCRONIZAÇÃO ===
    public function sync($data) {
        try {
            $this->logger->info("Iniciando sincronização PWA", [
                'user_id' => $this->userId,
                'action' => $data['action'] ?? 'unknown'
            ]);

            $response = [
                'status' => true,
                'timestamp' => time(),
                'sync_id' => uniqid('sync_', true),
                'data' => []
            ];

            switch ($data['action']) {
                case 'full_sync':
                    $response['data'] = $this->performFullSync($data);
                    break;

                case 'incremental_sync':
                    $response['data'] = $this->performIncrementalSync($data);
                    break;

                case 'upload_pending':
                    $response['data'] = $this->uploadPendingData($data);
                    break;

                case 'download_updates':
                    $response['data'] = $this->downloadUpdates($data);
                    break;

                case 'get_sync_status':
                    $response['data'] = $this->getSyncStatus();
                    break;

                case 'resolve_conflicts':
                    $response['data'] = $this->resolveConflicts($data);
                    break;

                default:
                    throw new Exception('Ação de sincronização não reconhecida');
            }

            $this->logger->info("Sincronização PWA concluída com sucesso", [
                'user_id' => $this->userId,
                'sync_id' => $response['sync_id']
            ]);

            return $response;

        } catch (Exception $e) {
            $this->logger->error("Erro na sincronização PWA: " . $e->getMessage(), [
                'user_id' => $this->userId,
                'action' => $data['action'] ?? 'unknown'
            ]);

            return [
                'status' => false,
                'message' => 'Erro na sincronização: ' . $e->getMessage(),
                'timestamp' => time()
            ];
        }
    }

    // === SINCRONIZAÇÃO COMPLETA ===
    private function performFullSync($data) {
        $syncData = [];

        // Dados do usuário
        $syncData['user'] = $this->getUserData();

        // Transações de cashback
        $syncData['transactions'] = $this->getTransactions();

        // Lojas parceiras
        $syncData['stores'] = $this->getPartnerStores();

        // Saldos
        $syncData['balances'] = $this->getBalances();

        // Configurações
        $syncData['settings'] = $this->getUserSettings();

        // Notificações
        $syncData['notifications'] = $this->getNotifications();

        // Hash para verificação de integridade
        $syncData['checksum'] = $this->generateChecksum($syncData);

        // Salvar timestamp da última sincronização
        $this->updateLastSyncTimestamp();

        return [
            'type' => 'full_sync',
            'data' => $syncData,
            'total_records' => $this->countTotalRecords($syncData)
        ];
    }

    // === SINCRONIZAÇÃO INCREMENTAL ===
    private function performIncrementalSync($data) {
        $lastSync = $data['last_sync'] ?? 0;
        $syncData = [];

        // Buscar apenas dados modificados desde a última sincronização
        $syncData['transactions'] = $this->getModifiedTransactions($lastSync);
        $syncData['stores'] = $this->getModifiedStores($lastSync);
        $syncData['notifications'] = $this->getNewNotifications($lastSync);
        $syncData['balances'] = $this->getBalances(); // Sempre atual

        // Dados removidos
        $syncData['deleted'] = $this->getDeletedRecords($lastSync);

        $this->updateLastSyncTimestamp();

        return [
            'type' => 'incremental_sync',
            'since' => $lastSync,
            'data' => $syncData,
            'changes_count' => $this->countChanges($syncData)
        ];
    }

    // === UPLOAD DE DADOS PENDENTES ===
    private function uploadPendingData($data) {
        $pendingData = $data['pending_data'] ?? [];
        $processedItems = [];
        $errors = [];

        foreach ($pendingData as $item) {
            try {
                switch ($item['type']) {
                    case 'transaction_view':
                        $this->recordTransactionView($item['data']);
                        break;

                    case 'notification_read':
                        $this->markNotificationAsRead($item['data']);
                        break;

                    case 'user_settings':
                        $this->updateUserSettings($item['data']);
                        break;

                    case 'analytics_event':
                        $this->recordAnalyticsEvent($item['data']);
                        break;
                }

                $processedItems[] = $item['id'];

            } catch (Exception $e) {
                $errors[] = [
                    'item_id' => $item['id'],
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'type' => 'upload_completed',
            'processed_items' => $processedItems,
            'errors' => $errors,
            'processed_count' => count($processedItems),
            'error_count' => count($errors)
        ];
    }

    // === DOWNLOAD DE ATUALIZAÇÕES ===
    private function downloadUpdates($data) {
        $clientVersion = $data['client_version'] ?? '1.0.0';
        $updates = [];

        // Verificar se há atualizações disponíveis
        $updates['app_config'] = $this->getAppConfigUpdates($clientVersion);
        $updates['store_updates'] = $this->getStoreUpdates();
        $updates['promotion_updates'] = $this->getPromotionUpdates();

        return [
            'type' => 'updates_available',
            'client_version' => $clientVersion,
            'server_version' => SYSTEM_VERSION,
            'updates' => $updates,
            'requires_restart' => $this->checkIfRestartRequired($updates)
        ];
    }

    // === STATUS DE SINCRONIZAÇÃO ===
    private function getSyncStatus() {
        $stmt = $this->pdo->prepare("
            SELECT last_sync, sync_count, last_full_sync 
            FROM pwa_sync_status 
            WHERE user_id = ?
        ");
        $stmt->execute([$this->userId]);
        $status = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$status) {
            // Criar registro inicial
            $this->initializeSyncStatus();
            $status = [
                'last_sync' => null,
                'sync_count' => 0,
                'last_full_sync' => null
            ];
        }

        return [
            'last_sync' => $status['last_sync'],
            'sync_count' => $status['sync_count'],
            'last_full_sync' => $status['last_full_sync'],
            'server_time' => time(),
            'needs_full_sync' => $this->needsFullSync($status)
        ];
    }

    // === RESOLUÇÃO DE CONFLITOS ===
    private function resolveConflicts($data) {
        $conflicts = $data['conflicts'] ?? [];
        $resolutions = [];

        foreach ($conflicts as $conflict) {
            $resolution = $this->resolveConflict($conflict);
            $resolutions[] = $resolution;
        }

        return [
            'type' => 'conflicts_resolved',
            'resolutions' => $resolutions,
            'resolved_count' => count($resolutions)
        ];
    }

    // === MÉTODOS AUXILIARES - DADOS DO USUÁRIO ===
    private function getUserData() {
        $stmt = $this->pdo->prepare("
            SELECT id, nome, email, cpf, telefone, 
                   foto_perfil, data_criacao, ultimo_login, status
            FROM usuarios 
            WHERE id = ?
        ");
        $stmt->execute([$this->userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getTransactions() {
        $stmt = $this->pdo->prepare("
            SELECT t.*, l.nome_fantasia as loja_nome, l.logo as loja_logo
            FROM transacoes_cashback t
            LEFT JOIN lojas l ON t.loja_id = l.id
            WHERE t.usuario_id = ?
            ORDER BY t.data_transacao DESC
            LIMIT 100
        ");
        $stmt->execute([$this->userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getPartnerStores() {
        $stmt = $this->pdo->prepare("
            SELECT id, nome_fantasia, logo, categoria, 
                   porcentagem_cashback, status, descricao
            FROM lojas 
            WHERE status = 'aprovado'
            ORDER BY nome_fantasia
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getBalances() {
        $stmt = $this->pdo->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN t.status = 'aprovado' THEN t.valor_cashback ELSE 0 END), 0) as saldo_disponivel,
                COALESCE(SUM(CASE WHEN t.status = 'pendente' THEN t.valor_cashback ELSE 0 END), 0) as saldo_pendente,
                COALESCE(SUM(t.valor_cashback), 0) as total_acumulado
            FROM transacoes_cashback t
            WHERE t.usuario_id = ?
        ");
        $stmt->execute([$this->userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getUserSettings() {
        $stmt = $this->pdo->prepare("
            SELECT configuracao, valor 
            FROM usuario_configuracoes 
            WHERE usuario_id = ?
        ");
        $stmt->execute([$this->userId]);
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formattedSettings = [];
        foreach ($settings as $setting) {
            $formattedSettings[$setting['configuracao']] = $setting['valor'];
        }

        return $formattedSettings;
    }

    private function getNotifications() {
        $stmt = $this->pdo->prepare("
            SELECT id, titulo, mensagem, tipo, lida, 
                   data_criacao, data_leitura
            FROM notificacoes 
            WHERE usuario_id = ?
            ORDER BY data_criacao DESC
            LIMIT 50
        ");
        $stmt->execute([$this->userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // === MÉTODOS AUXILIARES - SINCRONIZAÇÃO INCREMENTAL ===
    private function getModifiedTransactions($lastSync) {
        $stmt = $this->pdo->prepare("
            SELECT t.*, l.nome_fantasia as loja_nome, l.logo as loja_logo
            FROM transacoes_cashback t
            LEFT JOIN lojas l ON t.loja_id = l.id
            WHERE t.usuario_id = ? AND t.data_atualizacao > FROM_UNIXTIME(?)
            ORDER BY t.data_transacao DESC
        ");
        $stmt->execute([$this->userId, $lastSync]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getModifiedStores($lastSync) {
        $stmt = $this->pdo->prepare("
            SELECT id, nome_fantasia, logo, categoria, 
                   porcentagem_cashback, status, descricao
            FROM lojas 
            WHERE status = 'aprovado' AND data_atualizacao > FROM_UNIXTIME(?)
            ORDER BY nome_fantasia
        ");
        $stmt->execute([$lastSync]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getNewNotifications($lastSync) {
        $stmt = $this->pdo->prepare("
            SELECT id, titulo, mensagem, tipo, lida, 
                   data_criacao, data_leitura
            FROM notificacoes 
            WHERE usuario_id = ? AND data_criacao > FROM_UNIXTIME(?)
            ORDER BY data_criacao DESC
        ");
        $stmt->execute([$this->userId, $lastSync]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getDeletedRecords($lastSync) {
        // Implementar lógica para registros deletados (soft delete)
        return [
            'transactions' => [],
            'stores' => [],
            'notifications' => []
        ];
    }

    // === MÉTODOS AUXILIARES - UPLOAD ===
    private function recordTransactionView($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO transacao_visualizacoes (usuario_id, transacao_id, data_visualizacao)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE data_visualizacao = NOW()
        ");
        $stmt->execute([$this->userId, $data['transaction_id']]);
    }

    private function markNotificationAsRead($data) {
        $stmt = $this->pdo->prepare("
            UPDATE notificacoes 
            SET lida = 1, data_leitura = NOW()
            WHERE id = ? AND usuario_id = ?
        ");
        $stmt->execute([$data['notification_id'], $this->userId]);
    }

    private function updateUserSettings($data) {
        foreach ($data as $key => $value) {
            $stmt = $this->pdo->prepare("
                INSERT INTO usuario_configuracoes (usuario_id, configuracao, valor)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE valor = ?, data_atualizacao = NOW()
            ");
            $stmt->execute([$this->userId, $key, $value, $value]);
        }
    }

    private function recordAnalyticsEvent($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO pwa_analytics (usuario_id, evento, dados, data_evento)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([
            $this->userId, 
            $data['event'], 
            json_encode($data['data'])
        ]);
    }

    // === MÉTODOS AUXILIARES - UPDATES ===
    private function getAppConfigUpdates($clientVersion) {
        // Retornar configurações atualizadas do app
        return [
            'cashback_percentages' => $this->getCashbackPercentages(),
            'app_settings' => $this->getGlobalAppSettings(),
            'feature_flags' => $this->getFeatureFlags()
        ];
    }

    private function getStoreUpdates() {
        // Buscar atualizações de lojas (novas, modificadas)
        $stmt = $this->pdo->prepare("
            SELECT id, nome_fantasia, logo, categoria, porcentagem_cashback
            FROM lojas 
            WHERE status = 'aprovado' AND data_atualizacao > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getPromotionUpdates() {
        // Buscar promoções ativas
        $stmt = $this->pdo->prepare("
            SELECT id, titulo, descricao, loja_id, cashback_extra, 
                   data_inicio, data_fim
            FROM promocoes 
            WHERE status = 'ativo' AND data_fim > NOW()
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // === MÉTODOS AUXILIARES - UTILITIES ===
    private function generateChecksum($data) {
        return md5(json_encode($data));
    }

    private function updateLastSyncTimestamp() {
        $stmt = $this->pdo->prepare("
            INSERT INTO pwa_sync_status (user_id, last_sync, sync_count, last_full_sync)
            VALUES (?, UNIX_TIMESTAMP(), 1, UNIX_TIMESTAMP())
            ON DUPLICATE KEY UPDATE 
                last_sync = UNIX_TIMESTAMP(),
                sync_count = sync_count + 1
        ");
        $stmt->execute([$this->userId]);
    }

    private function initializeSyncStatus() {
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO pwa_sync_status (user_id, last_sync, sync_count, last_full_sync)
            VALUES (?, NULL, 0, NULL)
        ");
        $stmt->execute([$this->userId]);
    }

    private function needsFullSync($status) {
        // Verificar se precisa de sincronização completa
        $lastFullSync = $status['last_full_sync'] ?? 0;
        $daysSinceFullSync = (time() - $lastFullSync) / (24 * 60 * 60);
        
        return $daysSinceFullSync > 7; // Full sync a cada 7 dias
    }

    private function countTotalRecords($syncData) {
        $total = 0;
        foreach ($syncData as $key => $data) {
            if (is_array($data)) {
                $total += count($data);
            }
        }
        return $total;
    }

    private function countChanges($syncData) {
        return $this->countTotalRecords($syncData);
    }

    private function checkIfRestartRequired($updates) {
        // Verificar se as atualizações requerem reinicialização do app
        return !empty($updates['app_config']) || !empty($updates['feature_flags']);
    }

    private function resolveConflict($conflict) {
        // Implementar lógica de resolução de conflitos
        // Por enquanto, server sempre ganha
        return [
            'conflict_id' => $conflict['id'],
            'resolution' => 'server_wins',
            'resolved_data' => $conflict['server_data']
        ];
    }

    private function getCashbackPercentages() {
        $stmt = $this->pdo->prepare("
            SELECT porcentagem_cliente, porcentagem_admin, porcentagem_loja
            FROM configuracoes_cashback 
            ORDER BY id DESC 
            LIMIT 1
        ");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getGlobalAppSettings() {
        return [
            'maintenance_mode' => false,
            'min_app_version' => '1.0.0',
            'force_update' => false,
            'api_version' => '2.1.0'
        ];
    }

    private function getFeatureFlags() {
        return [
            'notifications_enabled' => true,
            'dark_mode_available' => true,
            'biometric_login' => true,
            'offline_mode' => true
        ];
    }
}

// === PROCESSAMENTO DA REQUISIÇÃO ===
try {
    // Verificar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido. Use POST.');
    }

    // Obter dados da requisição
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception('Dados inválidos na requisição.');
    }

    // Verificar autenticação
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
        throw new Exception('Token de autenticação não fornecido.');
    }

    $token = substr($authHeader, 7);
    $tokenData = Security::validateJWT($token);

    if (!$tokenData) {
        throw new Exception('Token de autenticação inválido.');
    }

    // Verificar se é um cliente
    if ($tokenData['tipo'] !== USER_TYPE_CLIENT) {
        throw new Exception('Acesso negado. Apenas clientes podem sincronizar dados.');
    }

    // Processar sincronização
    $syncController = new PWASyncController($tokenData['id'], $tokenData['tipo']);
    $result = $syncController->sync($data);

    // Retornar resposta
    http_response_code($result['status'] ? 200 : 400);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    // Log do erro
    error_log("Erro PWA Sync: " . $e->getMessage());

    // Resposta de erro
    http_response_code(500);
    echo json_encode([
        'status' => false,
        'message' => $e->getMessage(),
        'timestamp' => time()
    ], JSON_UNESCAPED_UNICODE);
}

// === FUNÇÃO AUXILIAR PARA HEADERS ===
function getallheaders() {
    if (function_exists('getallheaders')) {
        return getallheaders();
    }
    
    $headers = [];
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
            $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
            $headers[$headerName] = $value;
        }
    }
    return $headers;
}

// === CRIAÇÃO DAS TABELAS NECESSÁRIAS (EXECUTAR UMA VEZ) ===
/*
-- Tabela para status de sincronização
CREATE TABLE IF NOT EXISTS `pwa_sync_status` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `last_sync` int(11) DEFAULT NULL,
    `sync_count` int(11) DEFAULT 0,
    `last_full_sync` int(11) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_id` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para analytics PWA
CREATE TABLE IF NOT EXISTS `pwa_analytics` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `usuario_id` int(11) NOT NULL,
    `evento` varchar(100) NOT NULL,
    `dados` longtext DEFAULT NULL,
    `data_evento` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `usuario_id` (`usuario_id`),
    KEY `evento` (`evento`),
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para visualizações de transações
CREATE TABLE IF NOT EXISTS `transacao_visualizacoes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `usuario_id` int(11) NOT NULL,
    `transacao_id` int(11) NOT NULL,
    `data_visualizacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `usuario_transacao` (`usuario_id`, `transacao_id`),
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`transacao_id`) REFERENCES `transacoes_cashback`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para configurações do usuário
CREATE TABLE IF NOT EXISTS `usuario_configuracoes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `usuario_id` int(11) NOT NULL,
    `configuracao` varchar(100) NOT NULL,
    `valor` text DEFAULT NULL,
    `data_criacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `data_atualizacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `usuario_config` (`usuario_id`, `configuracao`),
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para promoções
CREATE TABLE IF NOT EXISTS `promocoes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `titulo` varchar(255) NOT NULL,
    `descricao` text DEFAULT NULL,
    `loja_id` int(11) DEFAULT NULL,
    `cashback_extra` decimal(5,2) DEFAULT 0.00,
    `data_inicio` datetime NOT NULL,
    `data_fim` datetime NOT NULL,
    `status` enum('ativo','inativo','expirado') DEFAULT 'ativo',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `loja_id` (`loja_id`),
    KEY `status` (`status`),
    FOREIGN KEY (`loja_id`) REFERENCES `lojas`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
*/

// Log de acesso
error_log("PWA Sync endpoint acessado - " . date('Y-m-d H:i:s'));
?>