<?php
// api/validate-token.php
require_once __DIR__ . '/../config/database.php';

// Configurar sessão para compartilhar entre subdomínios
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '.klubecash.com',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None'
]);

session_start();

// Configurar CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://sest-senat.klubecash.com');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, GET');

// Receber token
$token = $_GET['token'] ?? $_POST['token'] ?? '';

if (empty($token)) {
    echo json_encode([
        'success' => false,
        'message' => 'Token não fornecido'
    ]);
    exit;
}

// Buscar usuário pelo token no banco de dados
try {
    $db = Database::getConnection();

    // Buscar token válido (criado nos últimos 5 minutos)
    $stmt = $db->prepare("
        SELECT u.id, u.nome, u.email, u.tipo, u.senat
        FROM usuarios u
        INNER JOIN sessoes s ON u.id = s.usuario_id
        WHERE s.id = ?
        AND s.data_expiracao > NOW()
        LIMIT 1
    ");

    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Se não encontrou no banco, tentar na sessão do servidor principal
    if (!$user) {
        // Fazer requisição para o domínio principal para validar o token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://klubecash.com/api/get-user-by-token.php?token=' . urlencode($token));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Apenas para desenvolvimento
        curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if ($data && isset($data['success']) && $data['success']) {
                $user = $data['user'];
            }
        }
    }

    if ($user) {
        // Criar sessão local para o subdomínio
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['nome'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_type'] = $user['tipo'];
        $_SESSION['user_senat'] = $user['senat'];

        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'name' => $user['nome'],
                'email' => $user['email'],
                'type' => $user['tipo'],
                'senat' => $user['senat']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Token inválido ou expirado'
        ]);
    }

} catch (Exception $e) {
    error_log('Erro ao validar token: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao validar token',
        'error' => $e->getMessage()
    ]);
}
