<?php
// api/balance.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://sest-senat.klubecash.com'); // ALTERAR PARA O DOMINIO DA VPS
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../controllers/AuthController.php';
require_once '../controllers/ClientController.php';
require_once '../models/CashbackBalance.php';


if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}
session_start();

// Verificar autenticação
if (!AuthController::isAuthenticated()) {
    echo json_encode(['status' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$userId = AuthController::getCurrentUserId();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($userId);
            break;
            
        case 'POST':
            handlePostRequest($userId);
            break;
            
        default:
            echo json_encode(['status' => false, 'message' => 'Método não permitido']);
            break;
    }
} catch (Exception $e) {
    error_log('Erro na API de saldo: ' . $e->getMessage());
    echo json_encode(['status' => false, 'message' => 'Erro interno do servidor']);
}

function handleGetRequest($userId) {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'balance_details':
            $result = ClientController::getClientBalanceDetails($userId);
            echo json_encode($result);
            break;
            
        case 'store_balance':
            $storeId = intval($_GET['store_id'] ?? 0);
            if ($storeId <= 0) {
                echo json_encode(['status' => false, 'message' => 'ID da loja inválido']);
                return;
            }
            
            $balanceModel = new CashbackBalance();
            $balance = $balanceModel->getStoreBalance($userId, $storeId);
            echo json_encode(['status' => true, 'data' => ['balance' => $balance]]);
            break;
            
        case 'movement_history':
            $storeId = intval($_GET['store_id'] ?? 0);
            $page = intval($_GET['page'] ?? 1);
            
            if ($storeId <= 0) {
                echo json_encode(['status' => false, 'message' => 'ID da loja inválido']);
                return;
            }
            
            $result = ClientController::getBalanceHistory($userId, $storeId, $page);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['status' => false, 'message' => 'Ação não encontrada']);
            break;
    }
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