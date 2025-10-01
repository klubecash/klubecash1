<?php
// api/commissions.php
// API para gerenciar comissões do sistema Klube Cash

// Configurações iniciais
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Se for requisição OPTIONS (preflight), encerra a execução
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Incluir arquivos necessários
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/CommissionController.php';
require_once __DIR__ . '/../controllers/AdminController.php';
require_once __DIR__ . '/../utils/Security.php';
require_once __DIR__ . '/../utils/Validator.php';

// Função para validar token JWT
function validateToken() {
    // Obter token do cabeçalho Authorization
    $headers = getallheaders();
    $auth = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
    if (empty($auth) || !preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
        http_response_code(401);
        echo json_encode(['status' => false, 'message' => 'Token de autenticação não fornecido']);
        exit;
    }
    
    $token = $matches[1];
    
    // Validar token
    $decoded = Security::validateJWT($token);
    
    if (!$decoded) {
        http_response_code(401);
        echo json_encode(['status' => false, 'message' => 'Token de autenticação inválido ou expirado']);
        exit;
    }
    
    return $decoded;
}

// Processar a requisição com base no método HTTP
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetRequest();
        break;
    case 'POST':
        handlePostRequest();
        break;
    case 'PUT':
        handlePutRequest();
        break;
    case 'DELETE':
        handleDeleteRequest();
        break;
    default:
        http_response_code(405);
        echo json_encode(['status' => false, 'message' => 'Método não permitido']);
        break;
}

// Função para tratar requisições GET (consulta de comissões)
function handleGetRequest() {
    // Validar token
    $userData = validateToken();
    
    // Obter parâmetros da URL
    $commissionId = isset($_GET['id']) ? intval($_GET['id']) : null;
    $storeId = isset($_GET['loja_id']) ? intval($_GET['loja_id']) : null;
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $filters = [];
    
    // Aplicar filtros se fornecidos
    if (isset($_GET['status'])) $filters['status'] = $_GET['status'];
    if (isset($_GET['tipo_usuario'])) $filters['tipo_usuario'] = $_GET['tipo_usuario'];
    if (isset($_GET['data_inicio'])) $filters['data_inicio'] = $_GET['data_inicio'];
    if (isset($_GET['data_fim'])) $filters['data_fim'] = $_GET['data_fim'];
    if (isset($_GET['valor_min'])) $filters['valor_min'] = floatval($_GET['valor_min']);
    if (isset($_GET['valor_max'])) $filters['valor_max'] = floatval($_GET['valor_max']);
    
    // Verificar tipo de usuário e redirecionar para o processamento apropriado
    if ($userData['tipo'] === USER_TYPE_ADMIN) {
        // Administrador pode ver todas as comissões
        if ($commissionId) {
            // Detalhes de uma comissão específica
            $commission = new Commission();
            if ($commission->loadById($commissionId)) {
                echo json_encode(['status' => true, 'data' => $commission->toArray()]);
            } else {
                http_response_code(404);
                echo json_encode(['status' => false, 'message' => 'Comissão não encontrada']);
            }
        } else if (isset($_GET['summary']) && $_GET['summary'] === 'true') {
            // Resumo geral de comissões
            $result = CommissionController::getCommissionSummary($filters);
            echo json_encode($result);
        } else if (isset($_GET['admin_commissions']) && $_GET['admin_commissions'] === 'true') {
            // Comissões do administrador
            $result = CommissionController::getAdminCommissions($filters, $page);
            echo json_encode($result);
        } else if ($storeId) {
            // Comissões de uma loja específica
            $result = CommissionController::getStoreCommissions($storeId, $filters, $page);
            echo json_encode($result);
        } else {
            // Busca geral de comissões com paginação
            $result = Commission::find($filters, $page);
            echo json_encode(['status' => true, 'data' => [
                'comissoes' => array_map(function($commission) {
                    return $commission->toArray();
                }, $result['commissions']),
                'paginacao' => $result['pagination']
            ]]);
        }
    } else if ($userData['tipo'] === USER_TYPE_STORE) {
        // Loja só pode ver suas próprias comissões
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id FROM lojas WHERE usuario_id = ?");
        $stmt->execute([$userData['id']]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$store) {
            http_response_code(404);
            echo json_encode(['status' => false, 'message' => 'Loja não encontrada para este usuário']);
            exit;
        }
        
        $storeId = $store['id'];
        $result = CommissionController::getStoreCommissions($storeId, $filters, $page);
        echo json_encode($result);
    } else {
        // Clientes não têm acesso a comissões
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Acesso não autorizado para o tipo de usuário']);
    }
}

// Função para tratar requisições POST (calcular ou exportar comissões)
function handlePostRequest() {
    // Validar token
    $userData = validateToken();
    
    // Obter dados do corpo da requisição
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Verificar ação solicitada
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    
    switch ($action) {
        case 'calculate':
            // Calcular distribuição de comissão para um valor
            if (!isset($data['valor_total']) || !is_numeric($data['valor_total']) || $data['valor_total'] <= 0) {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'Valor total inválido ou não fornecido']);
                exit;
            }
            
            $porcentagemCashback = isset($data['porcentagem_cashback']) ? floatval($data['porcentagem_cashback']) : null;
            $result = CommissionController::calculateCommissionDistribution($data['valor_total'], $porcentagemCashback);
            echo json_encode(['status' => true, 'data' => $result]);
            break;
            
        case 'export_csv':
            // Exportar comissões para CSV (apenas admin)
            if ($userData['tipo'] !== USER_TYPE_ADMIN) {
                http_response_code(403);
                echo json_encode(['status' => false, 'message' => 'Acesso restrito a administradores']);
                exit;
            }
            
            $filters = isset($data['filters']) ? $data['filters'] : [];
            $result = CommissionController::exportCommissionsCSV($filters);
            echo json_encode($result);
            break;
            
        case 'report':
            // Gerar relatório de comissões (apenas admin)
            if ($userData['tipo'] !== USER_TYPE_ADMIN) {
                http_response_code(403);
                echo json_encode(['status' => false, 'message' => 'Acesso restrito a administradores']);
                exit;
            }
            
            $filters = isset($data['filters']) ? $data['filters'] : [];
            $result = CommissionController::generateCommissionReport($filters);
            echo json_encode($result);
            break;
            
        case 'update_settings':
            // Atualizar configurações de comissão (apenas admin)
            if ($userData['tipo'] !== USER_TYPE_ADMIN) {
                http_response_code(403);
                echo json_encode(['status' => false, 'message' => 'Acesso restrito a administradores']);
                exit;
            }
            
            if (!isset($data['porcentagem_cliente']) || !isset($data['porcentagem_admin']) || !isset($data['porcentagem_loja'])) {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'Dados incompletos para atualização de configurações']);
                exit;
            }
            
            $result = CommissionController::updateCommissionSettings($data);
            echo json_encode($result);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Ação não especificada ou inválida']);
            break;
    }
}

// Função para tratar requisições PUT (atualizar status de comissão)
function handlePutRequest() {
    // Validar token
    $userData = validateToken();
    
    // Apenas administradores podem atualizar status de comissões
    if ($userData['tipo'] !== USER_TYPE_ADMIN) {
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Apenas administradores podem atualizar comissões']);
        exit;
    }
    
    // Obter dados do corpo da requisição
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validar dados básicos
    if (!$data || !isset($data['id']) || !isset($data['status'])) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Dados incompletos para atualizar comissão']);
        exit;
    }
    
    // Validar status
    $validStatus = [TRANSACTION_PENDING, TRANSACTION_APPROVED, TRANSACTION_CANCELED];
    if (!in_array($data['status'], $validStatus)) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Status inválido']);
        exit;
    }
    
    // Atualizar status da comissão
    $observacao = isset($data['observacao']) ? $data['observacao'] : '';
    $result = CommissionController::updateCommissionStatus(
        $data['id'],
        $data['status'],
        $observacao
    );
    
    // Retornar resultado
    echo json_encode($result);
}

// Função para tratar requisições DELETE (cancelar comissão)
function handleDeleteRequest() {
    // Validar token
    $userData = validateToken();
    
    // Apenas administradores podem cancelar comissões
    if ($userData['tipo'] !== USER_TYPE_ADMIN) {
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Apenas administradores podem cancelar comissões']);
        exit;
    }
    
    // Obter ID da comissão da URL
    $commissionId = isset($_GET['id']) ? intval($_GET['id']) : null;
    
    if (!$commissionId) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'ID da comissão não fornecido']);
        exit;
    }
    
    // Cancelar comissão (definir status como cancelado)
    $result = CommissionController::updateCommissionStatus(
        $commissionId,
        TRANSACTION_CANCELED,
        'Comissão cancelada via API'
    );
    
    // Retornar resultado
    echo json_encode($result);
}

// Para qualquer erro não tratado anteriormente
function handleError($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'status' => false, 
        'message' => 'Erro interno do servidor',
        'error' => $errstr,
        'file' => $errfile,
        'line' => $errline
    ]);
    exit;
}

// Registrar manipulador de erros
set_error_handler('handleError');