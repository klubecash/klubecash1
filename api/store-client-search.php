<?php
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../controllers/AuthController.php';
require_once '../models/CashbackBalance.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function respondClientSearchError(int $statusCode, string $message): void
{
    http_response_code($statusCode);
    echo json_encode([
        'status' => false,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function resolveClientSearchStoreId(PDO $db, int $userId, string $userType): ?int
{
    if ($userType === USER_TYPE_STORE) {
        $stmt = $db->prepare("
            SELECT l.id
            FROM lojas l
            INNER JOIN usuarios u ON u.id = l.usuario_id
            WHERE u.id = :user_id
              AND u.tipo = :user_type
              AND u.status = :user_status
              AND l.status = 'aprovado'
            ORDER BY l.id ASC
            LIMIT 1
        ");
    } else {
        $stmt = $db->prepare("
            SELECT l.id
            FROM usuarios u
            INNER JOIN lojas l ON l.id = u.loja_vinculada_id
            WHERE u.id = :user_id
              AND u.tipo = :user_type
              AND u.status = :user_status
              AND l.status = 'aprovado'
            LIMIT 1
        ");
    }

    $stmt->execute([
        ':user_id' => $userId,
        ':user_type' => $userType,
        ':user_status' => USER_ACTIVE,
    ]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);

    return $store && (int) $store['id'] > 0 ? (int) $store['id'] : null;
}

// Verificar autenticação
if (!AuthController::isAuthenticated()) {
    error_log("API CLIENT SEARCH - Usuário não autenticado");
    respondClientSearchError(401, 'Usuário não autenticado');
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userType = $_SESSION['user_type'] ?? '';

if ($userId <= 0) {
    respondClientSearchError(401, 'Usuário não autenticado');
}

if (!in_array($userType, [USER_TYPE_STORE, USER_TYPE_EMPLOYEE], true)) {
    error_log("API CLIENT SEARCH - Tipo de usuário sem acesso à loja");
    respondClientSearchError(403, 'Acesso restrito a lojas e funcionários autorizados');
}

try {
    $db = Database::getConnection();
    $authenticatedStoreId = resolveClientSearchStoreId($db, $userId, $userType);
} catch (Throwable $e) {
    error_log('API CLIENT SEARCH - Erro ao validar associação com a loja: ' . $e->getMessage());
    respondClientSearchError(500, 'Erro interno do servidor. Tente novamente.');
}

if (!$authenticatedStoreId) {
    error_log("API CLIENT SEARCH - Usuário {$userId} sem loja ativa vinculada");
    respondClientSearchError(403, 'Usuário sem loja ativa vinculada');
}

$_SESSION['store_id'] = $authenticatedStoreId;
$_SESSION['loja_vinculada_id'] = $authenticatedStoreId;

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';


// === AÇÃO PRINCIPAL: BUSCAR CLIENTE (VERSÃO UNIVERSAL) ===
if ($action === 'search_client') {
    $searchTerm = trim($input['search_term'] ?? '');
    $storeId = $authenticatedStoreId;

    if (empty($searchTerm)) {
        echo json_encode(['status' => false, 'message' => 'Termo de busca (Email, CPF ou Telefone) é obrigatório']);
        exit;
    }

    // Limpar telefone e CPF de possíveis formatações
    $phoneSearch = preg_replace('/[^0-9]/', '', $searchTerm);
    $cpfSearch = preg_replace('/[^0-9]/', '', $searchTerm);

    try {
        $db = Database::getConnection();
        
        // BUSCA UNIVERSAL: procurar cliente em qualquer lugar
        $stmt = $db->prepare("
            SELECT id, nome, email, telefone, cpf, status, data_criacao, tipo_cliente, loja_criadora_id
            FROM usuarios
            WHERE (email = :searchTerm OR cpf = :cpfSearch OR telefone = :phoneSearch) 
            AND tipo = :tipo
            ORDER BY 
                CASE 
                    WHEN loja_criadora_id = :storeId THEN 1  -- Prioridade para clientes da própria loja
                    WHEN tipo_cliente = 'completo' THEN 2    -- Depois clientes completos
                    ELSE 3                                   -- Por último visitantes de outras lojas
                END
            LIMIT 1
        ");
        $stmt->bindParam(':searchTerm', $searchTerm);
        $stmt->bindParam(':cpfSearch', $cpfSearch);
        $stmt->bindParam(':phoneSearch', $phoneSearch);
        $tipo = USER_TYPE_CLIENT;
        $stmt->bindParam(':tipo', $tipo);
        $stmt->bindParam(':storeId', $storeId);
        $stmt->execute();
        
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$client) {
            // Cliente não encontrado - retornar opção de criar visitante
            echo json_encode([
                'status' => false,
                'code' => CLIENT_SEARCH_NOT_FOUND,
                'message' => 'Cliente não encontrado. Verifique se o email, CPF ou telefone está correto ou crie um cliente visitante.',
                'can_create_visitor' => true,
                'search_term' => $searchTerm,
                'search_type' => determineSearchType($searchTerm)
            ]);
            exit;
        }
        
        if ($client['status'] !== USER_ACTIVE) {
            echo json_encode([
                'status' => false,
                'code' => CLIENT_SEARCH_INACTIVE,
                'message' => 'Cliente encontrado, mas sua conta não está ativa.'
            ]);
            exit;
        }
        
        // DETERMINAR STATUS DO CLIENTE PARA ESTA LOJA
        $isOwnClient = ($client['loja_criadora_id'] == $storeId);
        $isVisitorFromOtherStore = ($client['tipo_cliente'] === CLIENT_TYPE_VISITOR && !$isOwnClient);
        $isCompleteClient = ($client['tipo_cliente'] === CLIENT_TYPE_COMPLETE || $client['tipo_cliente'] === 'completo');
        
        // Obter saldo específico desta loja
        $balanceModel = new CashbackBalance();
        $saldo = $balanceModel->getStoreBalance($client['id'], $storeId);
        
        // Obter estatísticas específicas desta loja
        $statsStmt = $db->prepare("
            SELECT 
                COUNT(*) as total_compras,
                SUM(valor_total) as total_gasto,
                SUM(valor_cliente) as total_cashback_recebido,
                MAX(data_transacao) as ultima_compra
            FROM transacoes_cashback 
            WHERE usuario_id = :usuario_id AND loja_id = :loja_id AND status = 'aprovado'
        ");
        $statsStmt->bindParam(':usuario_id', $client['id']);
        $statsStmt->bindParam(':loja_id', $storeId);
        $statsStmt->execute();
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        
        // DETERMINAR TIPO DE EXIBIÇÃO E MENSAGEM
        if ($isCompleteClient) {
            $clientType = 'cadastrado';
            $clientTypeLabel = 'Cliente Cadastrado';
            $accessMessage = 'Cliente encontrado com acesso completo ao sistema.';
        } elseif ($isOwnClient) {
            $clientType = 'visitante_proprio';
            $clientTypeLabel = 'Cliente Visitante (Sua Loja)';
            $accessMessage = 'Cliente visitante criado pela sua loja.';
        } else {
            $clientType = 'visitante_universal';
            $clientTypeLabel = 'Cliente Visitante (Acesso Universal)';
            $accessMessage = 'Cliente visitante de outra loja, agora disponível para sua loja também!';
        }
        
        // Verificar se é primeira compra nesta loja
        $isFirstPurchaseInStore = ($stats['total_compras'] == 0);
        
        echo json_encode([
            'status' => true,
            'code' => CLIENT_SEARCH_FOUND,
            'message' => 'Cliente encontrado com sucesso',
            'data' => [
                'id' => $client['id'],
                'nome' => $client['nome'],
                'email' => $client['email'],
                'telefone' => $client['telefone'],
                'cpf' => $client['cpf'] ?? null,
                'status' => $client['status'],
                'tipo_cliente' => $clientType,
                'tipo_cliente_label' => $clientTypeLabel,
                'access_message' => $accessMessage,
                'is_own_client' => $isOwnClient,
                'is_first_purchase_in_store' => $isFirstPurchaseInStore,
                'loja_criadora_id' => $client['loja_criadora_id'],
                'data_cadastro' => date('d/m/Y', strtotime($client['data_criacao'])),
                'saldo' => $saldo,
                'estatisticas' => [
                    'total_compras' => $stats['total_compras'] ?? 0,
                    'total_gasto' => $stats['total_gasto'] ?? 0,
                    'total_cashback_recebido' => $stats['total_cashback_recebido'] ?? 0,
                    'ultima_compra' => $stats['ultima_compra'] ? date('d/m/Y', strtotime($stats['ultima_compra'])) : null
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        error_log('Erro ao buscar cliente: ' . $e->getMessage());
        echo json_encode([
            'status' => false, 
            'message' => 'Erro interno do servidor. Tente novamente.'
        ]);
    }
}

// === NOVA AÇÃO: CRIAR CLIENTE VISITANTE (VERSÃO FINAL CORRIGIDA) ===
elseif ($action === 'create_visitor_client') {
    error_log("API CLIENT SEARCH - Criando cliente visitante");
    
    $nome = preg_replace('/\s+/u', ' ', trim((string) ($input['nome'] ?? '')));
    $telefone = preg_replace('/[^0-9]/', '', $input['telefone'] ?? '');
    $storeId = $authenticatedStoreId;

    // Validações básicas
    $nameLength = function_exists('mb_strlen') ? mb_strlen($nome, 'UTF-8') : strlen($nome);
    if (
        $nameLength < 2
        || $nameLength > 120
        || !preg_match("/^[\\p{L}\\p{M} .'-]+$/u", $nome)
    ) {
        error_log("API CLIENT SEARCH - Erro: Nome inválido");
        echo json_encode(['status' => false, 'message' => 'Informe um nome válido entre 2 e 120 caracteres.']);
        exit;
    }

    if (empty($telefone) || strlen($telefone) < VISITOR_PHONE_MIN_LENGTH || strlen($telefone) > 15) {
        error_log("API CLIENT SEARCH - Erro: Telefone inválido");
        echo json_encode(['status' => false, 'message' => 'Informe um telefone válido com 10 a 15 dígitos.']);
        exit;
    }

    try {
        $db = Database::getConnection();

        error_log("API CLIENT SEARCH - Usando store_id final: $storeId");
        
        // Verificar se já existe cliente visitante com este telefone nesta loja
        $checkStmt = $db->prepare("
            SELECT id FROM usuarios 
            WHERE telefone = :telefone 
            AND tipo = :tipo 
            AND tipo_cliente = :tipo_cliente 
            AND loja_criadora_id = :loja_id
        ");
        $checkStmt->bindParam(':telefone', $telefone);
        $tipo = USER_TYPE_CLIENT;
        $checkStmt->bindParam(':tipo', $tipo);
        $tipoCliente = CLIENT_TYPE_VISITOR;
        $checkStmt->bindParam(':tipo_cliente', $tipoCliente);
        $checkStmt->bindParam(':loja_id', $storeId);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            error_log("API CLIENT SEARCH - Cliente visitante já existe");
            echo json_encode(['status' => false, 'message' => MSG_VISITOR_EXISTS]);
            exit;
        }
        
        // Gerar email fictício único para evitar constraint violation
        $emailFicticio = 'visitante_' . $telefone . '_loja_' . $storeId . '@klubecash.local';
        
        // Criar cliente visitante
        $insertStmt = $db->prepare("
            INSERT INTO usuarios (nome, email, telefone, tipo, tipo_cliente, loja_criadora_id, status, data_criacao)
            VALUES (:nome, :email, :telefone, :tipo, :tipo_cliente, :loja_id, :status, NOW())
        ");
        $insertStmt->bindParam(':nome', $nome);
        $insertStmt->bindParam(':email', $emailFicticio);
        $insertStmt->bindParam(':telefone', $telefone);
        $insertStmt->bindParam(':tipo', $tipo);
        $insertStmt->bindParam(':tipo_cliente', $tipoCliente);
        $insertStmt->bindParam(':loja_id', $storeId);
        $status = USER_ACTIVE;
        $insertStmt->bindParam(':status', $status);
        
        $result = $insertStmt->execute();
        
        if (!$result) {
            $errorInfo = $insertStmt->errorInfo();
            error_log("API CLIENT SEARCH - Erro ao inserir no banco: " . print_r($errorInfo, true));
            http_response_code(500);
            echo json_encode(['status' => false, 'message' => 'Erro interno do servidor. Tente novamente.']);
            exit;
        }
        
        $clientId = $db->lastInsertId();
        
        error_log("API CLIENT SEARCH - ✅ Cliente visitante criado com sucesso! ID: $clientId");
        
        echo json_encode([
            'status' => true,
            'message' => MSG_VISITOR_CREATED,
            'data' => [
                'id' => $clientId,
                'nome' => $nome,
                'email' => null,  // Não mostrar o email fictício
                'telefone' => $telefone,
                'tipo_cliente' => 'visitante',
                'tipo_cliente_label' => 'Cliente Visitante',
                'saldo' => 0,
                'data_cadastro' => date('d/m/Y'),
                'estatisticas' => [
                    'total_compras' => 0,
                    'total_gasto' => 0,
                    'total_cashback_recebido' => 0,
                    'ultima_compra' => null
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        error_log('API CLIENT SEARCH - ❌ Erro crítico: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'status' => false,
            'message' => 'Erro interno do servidor. Tente novamente.'
        ]);
    }
}

else {
    error_log("API CLIENT SEARCH - Ação inválida: " . $action);
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Ação inválida.']);
    exit;
}

// === FUNÇÃO AUXILIAR ===
function determineSearchType($searchTerm) {
    $cleaned = preg_replace('/[^0-9]/', '', $searchTerm);
    
    if (filter_var($searchTerm, FILTER_VALIDATE_EMAIL)) {
        return 'email';
    } elseif (strlen($cleaned) == 11 && substr($cleaned, 0, 1) != '0') {
        return 'telefone';
    } elseif (strlen($cleaned) == 11) {
        return 'cpf';
    } else {
        return 'unknown';
    }
}

error_log("API CLIENT SEARCH - Fim da requisição");
?>
