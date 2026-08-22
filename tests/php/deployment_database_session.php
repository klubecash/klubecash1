<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__, 2) . '/bootstrap/app.php';

$database = (new Database())->getConnection();
$action = (string) ($argv[1] ?? 'create-admin');

if ($action === 'destroy') {
    $sessionId = (string) ($argv[2] ?? '');
    if (preg_match('/^deploy-(?:admin|store)-[a-f0-9]{16}$/', $sessionId) !== 1) {
        throw new InvalidArgumentException('Identificador de sessão de deploy inválido.');
    }
    $statement = $database->prepare('DELETE FROM app_sessions WHERE id=:id');
    $statement->execute([':id' => $sessionId]);
    echo "destroyed\n";
    exit;
}

$role = $action === 'create-store' ? 'store' : 'admin';
if ($role === 'admin') {
    $statement = $database->query(
        "SELECT id,nome,NULL store_id FROM usuarios WHERE tipo='admin' AND status='ativo' ORDER BY id LIMIT 1"
    );
    $userType = 'admin';
} else {
    $statement = $database->query(
        "SELECT u.id,u.nome,l.id store_id FROM usuarios u JOIN lojas l ON l.usuario_id=u.id "
        . "WHERE u.tipo='loja' AND u.status='ativo' AND l.status='aprovado' ORDER BY l.id LIMIT 1"
    );
    $userType = 'loja';
}
$user = $statement->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    throw new RuntimeException('Usuário ativo não encontrado para o teste de deploy.');
}

$sessionId = 'deploy-' . $role . '-' . bin2hex(random_bytes(8));
$values = [
    'user_id' => (int) $user['id'],
    'user_type' => $userType,
    'user_name' => (string) $user['nome'],
    'last_activity' => time(),
    'csrf_token' => bin2hex(random_bytes(32)),
];
if ($role === 'store') {
    $values['store_id'] = (int) $user['store_id'];
}

$payload = '';
foreach ($values as $key => $value) {
    $payload .= $key . '|';
    $payload .= is_int($value)
        ? 'i:' . $value . ';'
        : 's:' . strlen($value) . ':"' . $value . '";';
}

$insert = $database->prepare(
    'INSERT INTO app_sessions (id,user_id,payload,last_activity) VALUES (:id,:user,:payload,:activity)'
);
$insert->execute([
    ':id' => $sessionId,
    ':user' => (int) $user['id'],
    ':payload' => $payload,
    ':activity' => time(),
]);

echo json_encode([
    'sessionId' => $sessionId,
    'csrfToken' => $values['csrf_token'],
], JSON_UNESCAPED_SLASHES), PHP_EOL;
