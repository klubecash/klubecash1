<?php

declare(strict_types=1);

// Opening the KlubeCash session, shared by the password login and "Entrar com Grupo Klube".
// Intentionally refuses the application database: supply a disposable MySQL on loopback,
// exactly like store_network_integration.php.
$dsn = (string) getenv('GIFTBACK_TEST_DSN');
if (!preg_match('/^mysql:host=127\.0\.0\.1;port=\d+$/D', $dsn)) {
    fwrite(STDERR, "Required: GIFTBACK_TEST_DSN=mysql:host=127.0.0.1;port=<isolated test server port>\n");
    exit(2);
}
putenv('JWT_SECRET=' . str_repeat('t', 64));
putenv('DB_HOST=127.0.0.1'); // never the real database, even if something reached for it
require_once __DIR__ . '/../../controllers/AuthController.php';
// The application bootstrap hides errors behind its HTML handler; a test must show why it failed.
restore_exception_handler();
ini_set('display_errors', 'stderr');

$db = new PDO($dsn, getenv('GIFTBACK_TEST_USER') ?: 'root', getenv('GIFTBACK_TEST_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$name = 'session_test_' . bin2hex(random_bytes(6));
$db->exec('CREATE DATABASE ' . $name . ' CHARACTER SET utf8mb4');
$db->exec('USE ' . $name);
$check = static function (bool $condition, string $label): void {
    if (!$condition) { throw new RuntimeException('FALHOU: ' . $label); }
    echo "OK {$label}\n";
};

// Progress lines are buffered: in the CLI, printed output counts as sent headers and blocks session_regenerate_id().
ob_start();
session_save_path(sys_get_temp_dir());
session_start();
$login = static function (int $id) use ($db): array {
    $_SESSION = ['user_id' => 999, 'store_id' => 77, 'login_method' => 'grupoklube', 'grupoklube_id_token' => 'antigo'];
    $user = $db->query('SELECT id,nome,email,tipo,status FROM usuarios WHERE id=' . $id)->fetch(PDO::FETCH_ASSOC);
    return AuthController::startSessionForUser($db, $user);
};

try {
    $db->exec("CREATE TABLE usuarios(id INT PRIMARY KEY,nome VARCHAR(160),email VARCHAR(160),tipo VARCHAR(30),status VARCHAR(30),ultimo_login DATETIME NULL) ENGINE=InnoDB;
        CREATE TABLE lojas(id INT PRIMARY KEY,nome_fantasia VARCHAR(160),status VARCHAR(30)) ENGINE=InnoDB;
        CREATE TABLE store_networks(id INT PRIMARY KEY,name VARCHAR(160),status VARCHAR(20));
        CREATE TABLE store_network_memberships(store_id INT PRIMARY KEY,network_id INT,status VARCHAR(20));
        CREATE TABLE store_network_managers(network_id INT,user_id INT,PRIMARY KEY(network_id,user_id));
        CREATE TABLE store_user_memberships(user_id INT,store_id INT,role VARCHAR(30),status VARCHAR(20),PRIMARY KEY(user_id,store_id));
        INSERT INTO usuarios(id,nome,email,tipo,status) VALUES
          (11,'Admin','admin@example.test','admin','ativo'),(20,'Dona','dona@example.test','loja','ativo'),
          (21,'Vendedor','vendedor@example.test','funcionario','ativo'),(22,'Sem filial','sem@example.test','loja','ativo'),
          (23,'Inativa','inativa@example.test','loja','inativo'),(30,'Cliente','cliente@example.test','cliente','ativo');
        INSERT INTO lojas VALUES(1,'Filial A','aprovado'),(2,'Filial B','aprovado');
        INSERT INTO store_user_memberships VALUES(20,1,'titular','active'),(21,1,'vendedor','active'),(21,2,'vendedor','active')");

    $result = $login(11);
    $check($result['status'] && $_SESSION['user_id'] === 11 && $_SESSION['user_type'] === 'admin', 'administrador entra');
    $check(!isset($_SESSION['store_id']) && !isset($_SESSION['login_method']) && !isset($_SESSION['grupoklube_id_token']), 'nada da sessão anterior sobra');
    $check(count(explode('.', $result['token'])) === 3 && (int) $db->query('SELECT ultimo_login IS NOT NULL FROM usuarios WHERE id=11')->fetchColumn() === 1, 'JWT emitido e último login gravado');
    $check(AuthController::dashboardFor('admin') === '/admin/dashboard', 'administrador vai para o painel admin');

    $result = $login(20);
    $check($result['status'] && $_SESSION['store_id'] === 1 && $_SESSION['store_name'] === 'Filial A', 'loja de uma filial entra com a filial escolhida');
    $check(AuthController::dashboardFor('loja') === '/store', 'loja vai para o painel da loja');

    $result = $login(21);
    $check($result['status'] && !isset($_SESSION['store_id']), 'funcionário de duas filiais entra e escolhe a filial depois');

    $result = $login(22);
    $check(!$result['status'] && ($_SESSION['user_id'] ?? null) === 999 && ($_SESSION['store_id'] ?? null) === 77, 'conta sem filial recusada sem abrir sessão nova');
    $result = $login(23);
    $check(!$result['status'] && ($_SESSION['user_id'] ?? null) === 999, 'conta inativa recusada sem abrir sessão nova');

    $result = $login(30);
    $check($result['status'] && $_SESSION['user_type'] === 'cliente' && AuthController::dashboardFor('cliente') === '/cliente/dashboard', 'cliente entra no painel do cliente');
} finally {
    $db->exec('DROP DATABASE ' . $name);
    session_destroy();
}

echo "OK: abertura de sessão do KlubeCash validada.\n";
