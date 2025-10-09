<?php
// sest-senat/get-user.php

// Configurar sessão para compartilhar entre subdomínios
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '.klubecash.com', // Com ponto para incluir subdomínios
    'secure' => true,              // HTTPS
    'httponly' => true,
    'samesite' => 'None'           // Compartilhamento entre domínios
]);

session_start();

// Configurar CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://sest-senat.klubecash.com');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET');

// Debug: Log da sessão
error_log("GET-USER SEST-SENAT: Session ID: " . session_id());
error_log("GET-USER SEST-SENAT: Session Data: " . json_encode($_SESSION));

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'loggedIn' => false,
        'message' => 'Usuário não autenticado',
        'session_id' => session_id(),
        'debug' => [
            'cookie_params' => session_get_cookie_params(),
            'session_keys' => array_keys($_SESSION)
        ]
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
    ],
    'session_id' => session_id()
]);
