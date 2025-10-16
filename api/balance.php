<?php
// api/balance.php

// Lista de domínios permitidos para acessar esta API
$allowed_origins = [
    'https://sest-senat.klubecash.com',
    'https://sdk.mercadopago.com'
    // Adicione outros domínios se necessário, ex: 'http://localhost:5173'
];

// Verifica a origem da requisição
if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    // Se a origem for permitida, responda autorizando especificamente ela
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
}

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Configura o cookie de sessão para funcionar em todos os subdomínios
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '.klubecash.com',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'None'
]);

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

session_start();

// Incluir arquivos necessários
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../controllers/AuthController.php';
require_once '../controllers/ClientController.php';
require_once '../models/CashbackBalance.php';

// Verificar autenticação
if (!AuthController::isAuthenticated()) {
    echo json_encode(['status' => false, 'message' => 'Usuário não autenticado. Sessão não encontrada.']);
    exit;
}

// O restante do seu código continua exatamente o mesmo...
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

