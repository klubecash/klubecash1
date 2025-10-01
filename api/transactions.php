<?php
// api/transactions.php
// API para gerenciar transações do sistema Klube Cash

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
require_once __DIR__ . '/../controllers/ClientController.php';
require_once __DIR__ . '/../controllers/AdminController.php';
require_once __DIR__ . '/../utils/Security.php';

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
    
    // Validar token (usando uma função de exemplo, você precisará implementar seu próprio validador)
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

// Função para tratar requisições GET (consulta de transações)
function handleGetRequest() {
    // Validar token
    $userData = validateToken();
    
    // Obter parâmetros da URL
    $transactionId = isset($_GET['id']) ? intval($_GET['id']) : null;
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $filters = [];
    
    // Aplicar filtros se fornecidos
    if (isset($_GET['status'])) $filters['status'] = $_GET['status'];
    if (isset($_GET['loja_id'])) $filters['loja_id'] = intval($_GET['loja_id']);
    if (isset($_GET['data_inicio'])) $filters['data_inicio'] = $_GET['data_inicio'];
    if (isset($_GET['data_fim'])) $filters['data_fim'] = $_GET['data_fim'];
    if (isset($_GET['valor_min'])) $filters['valor_min'] = floatval($_GET['valor_min']);
    if (isset($_GET['valor_max'])) $filters['valor_max'] = floatval($_GET['valor_max']);
    
    // Verificar tipo de usuário e redirecionar para o controlador apropriado
    if ($userData['tipo'] === USER_TYPE_ADMIN) {
        // Administrador pode ver todas as transações ou detalhes de uma transação específica
        if ($transactionId) {
            $result = AdminController::getTransactionDetails($transactionId);
        } else {
            $result = AdminController::manageTransactions($filters, $page);
        }
    } else if ($userData['tipo'] === USER_TYPE_CLIENT) {
        // Cliente só pode ver suas próprias transações
        if ($transactionId) {
            $result = ClientController::getTransactionDetails($userData['id'], $transactionId);
        } else {
            $result = ClientController::getStatement($userData['id'], $filters, $page);
        }
    } else {
        // Loja só pode ver transações relacionadas a ela (implementar no futuro)
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Acesso não autorizado para o tipo de usuário']);
        exit;
    }
    
    // Retornar resultado
    echo json_encode($result);
}

// Função para tratar requisições POST (criar nova transação)
function handlePostRequest() {
    // Validar token
    $userData = validateToken();
    
    // Obter dados do corpo da requisição
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validar dados básicos
    if (!$data || !isset($data['loja_id']) || !isset($data['valor_total']) || !isset($data['codigo_transacao'])) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Dados incompletos para registrar transação']);
        exit;
    }
    
    // Configurar dados da transação
    $transactionData = [
        'usuario_id' => $userData['tipo'] === USER_TYPE_CLIENT ? $userData['id'] : $data['usuario_id'],
        'loja_id' => $data['loja_id'],
        'valor_total' => $data['valor_total'],
        'codigo_transacao' => $data['codigo_transacao'],
        'descricao' => $data['descricao'] ?? 'Transação via API'
    ];
    
    // Verificar tipo de usuário e redirecionar para o controlador apropriado
    if ($userData['tipo'] === USER_TYPE_ADMIN) {
        // Administrador pode registrar transação para qualquer usuário
        $result = AdminController::registerTransaction($transactionData);
    } else if ($userData['tipo'] === USER_TYPE_CLIENT) {
        // Cliente só pode registrar transação para si próprio
        $result = ClientController::registerTransaction($transactionData);
    } else {
        // Implementar lógica para loja no futuro
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Acesso não autorizado para o tipo de usuário']);
        exit;
    }
    
    // Retornar resultado
    echo json_encode($result);
}

// Função para tratar requisições PUT (atualizar status de transação)
function handlePutRequest() {
    // Validar token
    $userData = validateToken();
    
    // Apenas administradores podem atualizar status de transações
    if ($userData['tipo'] !== USER_TYPE_ADMIN) {
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Apenas administradores podem atualizar transações']);
        exit;
    }
    
    // Obter dados do corpo da requisição
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validar dados básicos
    if (!$data || !isset($data['id']) || !isset($data['status'])) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Dados incompletos para atualizar transação']);
        exit;
    }
    
    // Validar status
    $validStatus = [TRANSACTION_PENDING, TRANSACTION_APPROVED, TRANSACTION_CANCELED];
    if (!in_array($data['status'], $validStatus)) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Status inválido']);
        exit;
    }
    
    // Atualizar status da transação
    $result = AdminController::updateTransactionStatus(
        $data['id'], 
        $data['status'], 
        $data['observacao'] ?? ''
    );
    
    // Retornar resultado
    echo json_encode($result);
}

// Função para tratar requisições DELETE (cancelar transação)
function handleDeleteRequest() {
    // Validar token
    $userData = validateToken();
    
    // Apenas administradores podem cancelar transações
    if ($userData['tipo'] !== USER_TYPE_ADMIN) {
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Apenas administradores podem cancelar transações']);
        exit;
    }
    
    // Obter ID da transação da URL
    $transactionId = isset($_GET['id']) ? intval($_GET['id']) : null;
    
    if (!$transactionId) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'ID da transação não fornecido']);
        exit;
    }
    
    // Cancelar transação (definir status como cancelado)
    $result = AdminController::updateTransactionStatus(
        $transactionId, 
        TRANSACTION_CANCELED, 
        'Transação cancelada via API'
    );
    
    // Retornar resultado
    echo json_encode($result);
}

// Função para gerar relatório de transações
function generateTransactionReport($filters) {
    // Validar token
    $userData = validateToken();
    
    // Apenas administradores podem gerar relatórios
    if ($userData['tipo'] !== USER_TYPE_ADMIN) {
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Apenas administradores podem gerar relatórios']);
        exit;
    }
    
    // Gerar relatório financeiro
    $result = AdminController::generateReport('financeiro', $filters);
    
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