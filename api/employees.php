<?php
// api/employees.php - VERSÃO CORRIGIDA
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/StoreController.php';

session_start();

// CORREÇÃO PRINCIPAL: Trocar isLoggedIn() por isAuthenticated()
if (!AuthController::isAuthenticated() || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== USER_TYPE_STORE) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Acesso não autorizado']);
    exit;
}

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

function handleGetRequest() {
    $employeeId = isset($_GET['id']) ? intval($_GET['id']) : null;
    
    if ($employeeId) {
        // Buscar funcionário específico
        try {
            $db = Database::getConnection();
            $storeId = getStoreId();
            
            if (!$storeId) {
                echo json_encode(['status' => false, 'message' => 'Loja não encontrada']);
                return;
            }
            
            $stmt = $db->prepare("
                SELECT id, nome, email, telefone, subtipo_funcionario, status, data_criacao
                FROM usuarios 
                WHERE id = ? AND loja_vinculada_id = ? AND tipo = 'funcionario'
            ");
            $stmt->execute([$employeeId, $storeId]);
            
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($employee) {
                echo json_encode(['status' => true, 'data' => ['funcionario' => $employee]]);
            } else {
                echo json_encode(['status' => false, 'message' => 'Funcionário não encontrado']);
            }
            
        } catch (PDOException $e) {
            error_log('Erro ao buscar funcionário: ' . $e->getMessage());
            echo json_encode(['status' => false, 'message' => 'Erro interno do servidor']);
        }
    } else {
        // Listar funcionários
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $filters = [];
        
        if (!empty($_GET['subtipo']) && $_GET['subtipo'] !== 'todos') {
            $filters['subtipo'] = $_GET['subtipo'];
        }
        if (!empty($_GET['status']) && $_GET['status'] !== 'todos') {
            $filters['status'] = $_GET['status'];
        }
        if (!empty($_GET['busca'])) {
            $filters['busca'] = trim($_GET['busca']);
        }
        
        $result = StoreController::getEmployees($filters, $page);
        echo json_encode($result);
    }
}

function handlePostRequest() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        echo json_encode(['status' => false, 'message' => 'Dados não fornecidos']);
        return;
    }
    
    $result = StoreController::createEmployee($data);
    echo json_encode($result);
}

function handlePutRequest() {
    $employeeId = isset($_GET['id']) ? intval($_GET['id']) : null;
    
    if (!$employeeId) {
        echo json_encode(['status' => false, 'message' => 'ID do funcionário não fornecido']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        echo json_encode(['status' => false, 'message' => 'Dados não fornecidos']);
        return;
    }
    
    // Corrigir a chamada do método (passando dois parâmetros)
    $result = StoreController::updateEmployee($employeeId, $data);
    echo json_encode($result);
}

function handleDeleteRequest() {
    $employeeId = isset($_GET['id']) ? intval($_GET['id']) : null;
    
    if (!$employeeId) {
        echo json_encode(['status' => false, 'message' => 'ID do funcionário não fornecido']);
        return;
    }
    
    $result = StoreController::deleteEmployee($employeeId);
    echo json_encode($result);
}

function getStoreId() {
    try {
        $db = Database::getConnection();
        $userId = $_SESSION['user_id'];
        
        $stmt = $db->prepare("SELECT id FROM lojas WHERE usuario_id = ?");
        $stmt->execute([$userId]);
        
        $store = $stmt->fetch(PDO::FETCH_ASSOC);
        return $store ? $store['id'] : null;
        
    } catch (PDOException $e) {
        error_log('Erro ao obter ID da loja: ' . $e->getMessage());
        return null;
    }
}
?>