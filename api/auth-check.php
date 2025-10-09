<?php
// api/auth-check.php

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

header('Access-Control-Allow-Methods: GET, OPTIONS', true);
header('Access-Control-Allow-Headers: Content-Type, Authorization', true);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

session_start();

require_once __DIR__ . '/../controllers/AuthController.php';

try {
    if (AuthController::isAuthenticated()) {
        echo json_encode([
            'status' => true,
            'authenticated' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'] ?? '',
                'email' => $_SESSION['user_email'] ?? '',
                'type' => $_SESSION['user_type'] ?? '',
                'store_id' => $_SESSION['store_id'] ?? null,
                'store_name' => $_SESSION['store_name'] ?? null
            ]
        ]);
    } else {
        echo json_encode([
            'status' => false,
            'authenticated' => false
        ]);
    }
} catch (Exception $e) {
    error_log('Erro no check de autenticação: ' . $e->getMessage());
    echo json_encode([
        'status' => false,
        'authenticated' => false,
        'message' => 'Erro ao verificar autenticação'
    ]);
}
?>
