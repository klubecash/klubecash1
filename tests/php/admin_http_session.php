<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$action = (string) ($argv[1] ?? 'create');
if ($action === 'destroy') {
    $sessionId = (string) ($argv[2] ?? '');
    if (preg_match('/^admin-e2e-[a-f0-9]{16}$/', $sessionId) !== 1) {
        throw new InvalidArgumentException('Identificador de sessão de teste inválido.');
    }
    session_name('KLCSESSID');
    session_id($sessionId);
    session_start();
    $_SESSION = [];
    session_destroy();
    echo "destroyed\n";
    exit;
}

require dirname(__DIR__, 2) . '/bootstrap/app.php';
require_once dirname(__DIR__, 2) . '/utils/Security.php';

$database = (new Database())->getConnection();
$adminId = (int) $database
    ->query("SELECT id FROM usuarios WHERE tipo='admin' AND status='ativo' ORDER BY id LIMIT 1")
    ->fetchColumn();
if ($adminId <= 0) {
    throw new RuntimeException('Nenhum administrador ativo disponível para o teste E2E.');
}

$sessionId = 'admin-e2e-' . bin2hex(random_bytes(8));
session_name('KLCSESSID');
session_id($sessionId);
session_start();
$_SESSION['user_id'] = $adminId;
$_SESSION['user_type'] = 'admin';
$_SESSION['user_name'] = 'Admin E2E';
$_SESSION['last_activity'] = time();
Security::generateCSRFToken();
session_write_close();

echo json_encode(['sessionId' => $sessionId], JSON_UNESCAPED_SLASHES), PHP_EOL;
