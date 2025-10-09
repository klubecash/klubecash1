<?php
// sest-senat/get-user.php
session_start();

// Configurar CORS se necessário
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'loggedIn' => false,
        'message' => 'Usuário não autenticado'
    ]);
    exit;
}

// Retornar dados do usuário
echo json_encode([
    'loggedIn' => true,
    'user' => [
        'id' => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['user_name'] ?? 'Usuário',
        'email' => $_SESSION['user_email'] ?? null,
        'type' => $_SESSION['user_type'] ?? null,
        'senat' => $_SESSION['user_senat'] ?? 'Não'
    ]
]);
