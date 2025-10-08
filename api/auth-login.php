<?php
// api/auth-login.php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

require_once __DIR__ . '/../controllers/AuthController.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => false, 'message' => 'Método não permitido']);
        exit;
    }

    // Obter dados do corpo da requisição
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['email']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Email e senha são obrigatórios']);
        exit;
    }

    $email = trim($data['email']);
    $password = $data['password'];

    // Fazer login usando o AuthController
    $result = AuthController::login($email, $password);

    if ($result['status']) {
        // Login bem-sucedido
        echo json_encode($result);
    } else {
        // Login falhou
        http_response_code(401);
        echo json_encode($result);
    }

} catch (Exception $e) {
    error_log('Erro no login API: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => false,
        'message' => 'Erro interno do servidor'
    ]);
}
?>
