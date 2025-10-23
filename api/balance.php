<?php
// api/balance.php

// Configurações iniciais
header('Content-Type: application/json; charset=UTF-8');

$allowed_origins = [
    'https://sest-senat.klubecash.com',
    'https://sdk.mercadopago.com'
    // Adicione outros domínios se necessário, ex: 'http://localhost:5173'
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

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

// ✅ CORREÇÃO FINAL: Bloco de Autenticação Híbrida Refatorado
$userId = null;

// 1. Tenta obter o ID do usuário diretamente da sessão PHP, se já existir.
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
} 
// 2. Se a sessão não existir, tenta validar o token JWT do cookie.
else {
    $token = $_COOKIE['jwt_token'] ?? '';
    if (!empty($token)) {
        $validationResult = Security::validateJWT($token);
        if ($validationResult) {
            $tokenData = (array) $validationResult;
            $userId = $tokenData['id']; // Pega o ID diretamente do token validado.
            
            // Recria a sessão para garantir consistência com outras partes do sistema.
            $_SESSION['user_id'] = $tokenData['id'];
            $_SESSION['user_type'] = $tokenData['tipo'];
        }
    }
}

// 3. Se, após as duas tentativas, não houver um ID de usuário, a autenticação falhou.
if ($userId === null) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
    exit;
}
// Fim da correção

// O restante do seu código continua exatamente o mesmo...
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
            
            // Esta verificação usa a sessão, que agora está sincronizada.
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


