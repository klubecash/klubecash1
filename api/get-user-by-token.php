<?php
// api/get-user-by-token.php
session_start();

header('Content-Type: application/json');

$token = $_GET['token'] ?? '';

if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Token não fornecido']);
    exit;
}

// Verificar se o token está na sessão e ainda é válido
if (isset($_SESSION['senat_token']) &&
    $_SESSION['senat_token'] === $token &&
    isset($_SESSION['senat_token_expiry']) &&
    $_SESSION['senat_token_expiry'] > time()) {

    // Token válido, retornar dados do usuário
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $_SESSION['user_id'] ?? null,
            'nome' => $_SESSION['user_name'] ?? 'Usuário',
            'email' => $_SESSION['user_email'] ?? null,
            'tipo' => $_SESSION['user_type'] ?? null,
            'senat' => $_SESSION['user_senat'] ?? 'Não'
        ]
    ]);

} else {
    echo json_encode([
        'success' => false,
        'message' => 'Token inválido ou expirado',
        'debug' => [
            'has_token' => isset($_SESSION['senat_token']),
            'token_match' => isset($_SESSION['senat_token']) && $_SESSION['senat_token'] === $token,
            'not_expired' => isset($_SESSION['senat_token_expiry']) && $_SESSION['senat_token_expiry'] > time(),
            'current_time' => time(),
            'expiry_time' => $_SESSION['senat_token_expiry'] ?? null
        ]
    ]);
}
