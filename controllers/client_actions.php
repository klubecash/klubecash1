<?php
// controllers/client_actions.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/ClientController.php';
require_once __DIR__ . '/AuthController.php';

// Log para debug
error_log('CLIENT_ACTIONS: Iniciando processamento');
error_log('CLIENT_ACTIONS: REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);
error_log('CLIENT_ACTIONS: REQUEST_URI: ' . $_SERVER['REQUEST_URI']);
error_log('CLIENT_ACTIONS: GET params: ' . print_r($_GET, true));
error_log('CLIENT_ACTIONS: POST params: ' . print_r($_POST, true));

// Iniciar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Definir header JSON
header('Content-Type: application/json');

try {
    // Verificar se o usuário está autenticado e é cliente
    if (!AuthController::isAuthenticated() || !AuthController::isClient()) {
        error_log('CLIENT_ACTIONS: Usuário não autenticado ou não é cliente');
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Acesso negado.']);
        exit;
    }

    $userId = AuthController::getCurrentUserId();
    error_log('CLIENT_ACTIONS: User ID: ' . $userId);

    // Verificar se há uma ação solicitada
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    error_log('CLIENT_ACTIONS: Action solicitada: ' . $action);

    if (empty($action)) {
        echo json_encode(['status' => false, 'message' => 'Nenhuma ação especificada']);
        exit;
    }

    // Processar a ação
    switch ($action) {
        case 'store_balance_details':
            $lojaId = isset($_GET['loja_id']) ? intval($_GET['loja_id']) : 0;
            error_log('CLIENT_ACTIONS: Loja ID recebido: ' . $lojaId);
            
            if ($lojaId <= 0) {
                error_log('CLIENT_ACTIONS: ID da loja inválido');
                echo json_encode(['status' => false, 'message' => 'ID da loja inválido']);
                exit;
            }
            
            error_log('CLIENT_ACTIONS: Chamando getStoreBalanceDetails...');
            $result = ClientController::getStoreBalanceDetails($userId, $lojaId);
            error_log('CLIENT_ACTIONS: Resultado: ' . print_r($result, true));
            echo json_encode($result);
            break;
            
        case 'simulate_balance_use':
            $lojaId = isset($_POST['loja_id']) ? intval($_POST['loja_id']) : 0;
            $valor = isset($_POST['valor']) ? floatval($_POST['valor']) : 0;
            
            if ($lojaId <= 0) {
                echo json_encode(['status' => false, 'message' => 'ID da loja inválido']);
                exit;
            }
            
            $result = ClientController::simulateBalanceUse($userId, $lojaId, $valor);
            echo json_encode($result);
            break;
            
        default:
            error_log('CLIENT_ACTIONS: Ação não reconhecida: ' . $action);
            echo json_encode(['status' => false, 'message' => 'Ação não encontrada: ' . $action]);
            break;
    }

} catch (Exception $e) {
    error_log('CLIENT_ACTIONS: Exceção capturada: ' . $e->getMessage());
    error_log('CLIENT_ACTIONS: Stack trace: ' . $e->getTraceAsString());
    echo json_encode(['status' => false, 'message' => 'Erro interno do servidor: ' . $e->getMessage()]);
}
?>