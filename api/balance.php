<?php
// api/users.php
// API para gerenciar usuários do sistema Klube Cash

// Configurações iniciais
header('Content-Type: application/json; charset=UTF-8');

// ✅ CORREÇÃO: O 'Access-Control-Allow-Origin' foi alterado de '*' para o domínio exato do seu app.
header('Access-Control-Allow-Origin: https://sest-senat.klubecash.com');

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
// O 'Access-Control-Allow-Credentials' é necessário para o navegador enviar o header 'Authorization'
header('Access-Control-Allow-Credentials: true');

// Se for requisição OPTIONS (preflight), encerra a execução
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Incluir arquivos necessários
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../controllers/AuthController.php';
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
    
    return (array) $decoded; // Converte para array para facilitar o uso
}

// ROTEAMENTO SIMPLIFICADO PARA O APP REACT (SEST-SENAT)
$action = $_GET['action'] ?? '';

// Rota para buscar os detalhes do usuário logado (usado pelo React)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get_details') {
    $tokenData = validateToken();
    $userId = $tokenData['id'];

    // Usamos uma função que busca os dados completos do usuário pelo ID
    $result = AdminController::getUserDetails($userId); 
    
    echo json_encode($result);
    exit;
}

// Rota para atualizar os detalhes do usuário (usado pelo React)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_details') {
    $tokenData = validateToken();
    $userId = $tokenData['id'];
    
    $postedData = json_decode(file_get_contents('php://input'), true);

    // TODO: Implementar a lógica de atualização no seu ClientController ou AdminController
    // Ex: $result = ClientController::updateUserDetails($userId, $postedData);
    
    // Resposta de exemplo (placeholder)
    $result = ['status' => true, 'message' => 'Dados do usuário atualizados com sucesso (implementação pendente).'];
    
    echo json_encode($result);
    exit;
}

// Rota específica para autenticação e obtenção de token JWT
if (isset($_GET['action']) && $_GET['action'] === 'login') {
    // Bypass do fluxo normal para processar login
    loginUser();
    exit;
}

// ===============================================
// A LÓGICA ABAIXO SERVE PARA OUTRAS PARTES DO SEU SISTEMA (EX: PAINEL ADMIN)
// E PODE SER MANTIDA COMO ESTÁ.
// ===============================================

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

// Função para tratar requisições GET (consulta de usuários para admin)
function handleGetRequest() {
    $userData = validateToken();
    
    if ($userData['tipo'] !== USER_TYPE_ADMIN) {
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Acesso não autorizado']);
        exit;
    }
    
    $userId = isset($_GET['id']) ? intval($_GET['id']) : null;
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $filters = [];
    
    if (isset($_GET['tipo'])) $filters['tipo'] = $_GET['tipo'];
    if (isset($_GET['status'])) $filters['status'] = $_GET['status'];
    if (isset($_GET['busca'])) $filters['busca'] = $_GET['busca'];
    
    if ($userId) {
        $result = AdminController::getUserDetails($userId);
    } else {
        $result = AdminController::manageUsers($filters, $page);
    }
    
    echo json_encode($result);
}
function handlePostRequest($userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'use_balance':
            $storeId = intval($input['store_id'] ?? 0);
            $amount = floatval($input['amount'] ?? 0);
            $description = trim($input['description'] ?? '');
            
            if ($storeId <= 0) {
                echo json_encode(['status' => false, 'message' => 'ID da loja inválido']);
                return;
            }
            
            if ($amount <= 0) {
                echo json_encode(['status' => false, 'message' => 'Valor deve ser maior que zero']);
                return;
            }
            
            // Verificar se é cliente
            if (!AuthController::isClient()) {
                echo json_encode(['status' => false, 'message' => 'Apenas clientes podem usar saldo']);
                return;
            }
            
            $result = ClientController::useClientBalance($userId, $storeId, $amount, $description);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['status' => false, 'message' => 'Ação não encontrada']);
            break;
    }
}
?>

