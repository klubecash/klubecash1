<?php
// api/auth-logout.php
header('Content-Type: application/json; charset=UTF-8');

// Permitir CORS de qualquer origem
$allowedOrigins = ['http://localhost:5173', 'http://localhost:3000', 'https://klubecash.com'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

require_once __DIR__ . '/../controllers/AuthController.php';

try {
    $result = AuthController::logout();
    echo json_encode($result);
} catch (Exception $e) {
    error_log('Erro no logout API: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => false,
        'message' => 'Erro interno do servidor'
    ]);
}
?>
