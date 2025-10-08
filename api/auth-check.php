<?php
// api/auth-check.php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
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
