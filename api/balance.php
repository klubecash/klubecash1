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
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
}

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir arquivos necessários
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/ClientController.php';
require_once __DIR__ . '/../models/CashbackBalance.php';
require_once __DIR__ . '/../utils/Security.php'; // Incluir para usar Security::validateJWT

// ✅ CORREÇÃO: Bloco de Autenticação Híbrida
$isAuthenticated = false;

// 1. Tenta autenticar via SESSÃO (método antigo)
if (AuthController::isAuthenticated()) {
    $isAuthenticated = true;
} 
// 2. Se a sessão falhar, TENTA AUTENTICAR VIA TOKEN JWT (método novo)
else {
    $token = $_COOKIE['jwt_token'] ?? '';
    if (!empty($token)) {
        $tokenData = Security::validateJWT($token);
        if ($tokenData) {
            // Se o token for válido, recria a sessão para esta requisição
            $_SESSION['user_id'] = is_array($tokenData) ? $tokenData['id'] : $tokenData->id;
            $_SESSION['user_type'] = is_array($tokenData) ? $tokenData['tipo'] : $tokenData->tipo;
            $isAuthenticated = true;
        }
    }
}

// 3. Se NENHUM dos métodos funcionar, retorna erro
if (!$isAuthenticated) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Usuário não autenticado. Sessão inválida ou expirada.']);
    exit;
}
// Fim da correção

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
            http_response_code(405);
            echo json_encode(['status' => false, 'message' => 'Método não permitido']);
            break;
    }
} catch (Exception $e) {
    error_log('Erro na API de saldo: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Erro interno do servidor']);
}

function handleGetRequest($userId) {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'balance_details':
            // Esta chamada agora deve funcionar corretamente.
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
            http_response_code(404);
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
            http_response_code(404);
            echo json_encode(['status' => false, 'message' => 'Ação não encontrada']);
            break;
    }
}
?>
