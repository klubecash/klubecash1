<?php
// api/auth-logout.php

// Remover headers CORS existentes para evitar duplicação
header_remove('Access-Control-Allow-Origin');
header_remove('Access-Control-Allow-Methods');
header_remove('Access-Control-Allow-Headers');
header_remove('Access-Control-Allow-Credentials');

// Definir headers CORS corretos
header('Content-Type: application/json; charset=UTF-8');

$allowedOrigins = ['http://localhost:5173', 'http://localhost:3000', 'https://klubecash.com'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin", true);
    header('Access-Control-Allow-Credentials: true', true);
} else {
    header('Access-Control-Allow-Origin: *', true);
}

header('Access-Control-Allow-Methods: POST, OPTIONS', true);
header('Access-Control-Allow-Headers: Content-Type, Authorization', true);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
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
