<?php

declare(strict_types=1);

// "Entrar com Grupo Klube" for someone with no KlubeCash account yet: the customer account is created and tied
// to the login único, matched by e-mail only when the person proves the password. Intentionally refuses the
// application database: supply a disposable MySQL on loopback, like grupoklube_session_integration.php.
// Run: GIFTBACK_TEST_DSN="mysql:host=127.0.0.1;port=3399" GIFTBACK_TEST_PASSWORD=... php tests/php/grupoklube_accounts_integration.php
$dsn = (string) getenv('GIFTBACK_TEST_DSN');
if (!preg_match('/^mysql:host=127\.0\.0\.1;port=\d+$/D', $dsn)) {
    fwrite(STDERR, "Required: GIFTBACK_TEST_DSN=mysql:host=127.0.0.1;port=<isolated test server port>\n");
    exit(2);
}

require_once __DIR__ . '/../../services/Auth/GrupoKlubeSsoException.php';
require_once __DIR__ . '/../../services/Auth/GrupoKlubeAccounts.php';

use App\Services\Auth\GrupoKlubeAccounts;
use App\Services\Auth\GrupoKlubeSsoException;

$db = new PDO($dsn, getenv('GIFTBACK_TEST_USER') ?: 'root', getenv('GIFTBACK_TEST_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$name = 'accounts_test_' . bin2hex(random_bytes(6));
$db->exec('CREATE DATABASE ' . $name . ' CHARACTER SET utf8mb4');
$db->exec('USE ' . $name);
$db->exec("CREATE TABLE usuarios (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(100) NOT NULL, email VARCHAR(255) DEFAULT NULL,
  telefone VARCHAR(20) DEFAULT NULL, senha_hash VARCHAR(255) DEFAULT NULL, data_criacao TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_login TIMESTAMP NULL DEFAULT NULL, status ENUM('ativo','inativo','bloqueado') DEFAULT 'ativo',
  tipo ENUM('cliente','admin','loja','funcionario') DEFAULT 'cliente', tipo_cliente ENUM('completo','visitante') DEFAULT 'completo',
  provider ENUM('local','google') DEFAULT 'local', email_verified TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$check = static function (bool $condition, string $label): void {
    if (!$condition) { throw new RuntimeException('FALHOU: ' . $label); }
    echo "OK {$label}\n";
};
$refuses = static function (callable $action, string $label): void {
    try { $action(); } catch (GrupoKlubeSsoException) { echo "OK {$label}\n"; return; }
    throw new RuntimeException('FALHOU (aceitou): ' . $label);
};
$login = static fn (array $over = []): array => $over + ['klubecashUserId' => null, 'sub' => 'identity-1', 'email' => 'maria@example.com', 'name' => 'Maria Cliente', 'linkAttempted' => false];

try {
    $accounts = new GrupoKlubeAccounts($db);
    $linked = [];
    $link = static function (string $sub, int $id) use (&$linked): void { $linked[] = [$sub, $id]; };

    // New e-mail: a customer is created, active, without a password, and the link is registered for exactly that account.
    $result = $accounts->forLogin($login(), $link);
    $row = $db->query("SELECT * FROM usuarios WHERE email = 'maria@example.com'")->fetch(PDO::FETCH_ASSOC);
    $check($result['outcome'] === GrupoKlubeAccounts::CREATED && $result['user']['id'] === (int) $row['id'], 'e-mail novo cria a conta KlubeCash');
    $check($row['tipo'] === 'cliente' && $row['status'] === 'ativo' && $row['senha_hash'] === null && $row['nome'] === 'Maria Cliente', 'conta criada é cliente ativo, sem senha própria');
    $check($linked === [['identity-1', (int) $row['id']]], 'a ligação é pedida só para a conta recém-criada');

    // The same e-mail again, not linked: nothing is matched by e-mail; the password is asked once.
    $before = (int) $db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    $result = $accounts->forLogin($login(), $link);
    $check($result['outcome'] === GrupoKlubeAccounts::NEEDS_PASSWORD && $result['user'] === null && count($linked) === 1, 'e-mail que já tem conta pede a senha uma vez, sem juntar por e-mail');
    $check((int) $db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() === $before, 'nenhuma conta nova para e-mail existente');
    $refuses(fn () => $accounts->forLogin($login(['linkAttempted' => true]), $link), 'depois de pedir a ligação, não repete em laço');

    // Case-insensitive e-mail is normalised, a missing name falls back to the e-mail's first part.
    $accounts->forLogin($login(['email' => 'Joao.Silva@Example.com', 'name' => '', 'sub' => 'identity-2']), $link);
    $joao = $db->query("SELECT nome, email FROM usuarios WHERE email = 'joao.silva@example.com'")->fetch(PDO::FETCH_ASSOC);
    $check($joao !== false && $joao['nome'] === 'Joao.silva', 'e-mail em minúsculas e nome de reserva');

    // The central login refuses the link: the account made a moment ago disappears, so a retry starts clean.
    $refusal = static function (): void { throw new GrupoKlubeSsoException('recusado'); };
    $refuses(fn () => $accounts->forLogin($login(['email' => 'ana@example.com', 'sub' => 'identity-3']), $refusal), 'ligação recusada vira erro');
    $check((int) $db->query("SELECT COUNT(*) FROM usuarios WHERE email = 'ana@example.com'")->fetchColumn() === 0, 'conta criada é removida quando a ligação falha');
    $db->exec("INSERT INTO usuarios (nome, email, senha_hash, ultimo_login, data_criacao) VALUES ('Antiga', 'antiga@example.com', 'x', NOW(), NOW() - INTERVAL 3 DAY)");
    $old = (int) $db->lastInsertId();
    $db->prepare("DELETE FROM usuarios WHERE id = ? AND tipo = 'cliente' AND provider = 'local' AND senha_hash IS NULL AND ultimo_login IS NULL AND data_criacao >= NOW() - INTERVAL 10 MINUTE")->execute([$old]);
    $check((int) $db->query("SELECT COUNT(*) FROM usuarios WHERE id = {$old}")->fetchColumn() === 1, 'a regra de limpeza nunca apaga conta com senha ou antiga');

    // Invalid e-mail never creates anything.
    $refuses(fn () => $accounts->forLogin($login(['email' => 'sem-arroba']), $link), 'e-mail inválido é recusado');
    $refuses(fn () => $accounts->forLogin($login(['email' => '']), $link), 'e-mail ausente é recusado');

    // The KlubeCash account the central login already knows is opened as before; admin and store accounts included.
    $db->exec("INSERT INTO usuarios (nome, email, senha_hash, tipo) VALUES ('Loja Teste', 'loja@example.com', 'x', 'loja')");
    $lojaId = (int) $db->lastInsertId();
    $result = $accounts->forLogin($login(['klubecashUserId' => $lojaId]), $link);
    $check($result['outcome'] === GrupoKlubeAccounts::EXISTING && $result['user']['tipo'] === 'loja', 'conta já ligada abre pelo id, como antes');
    $result = $accounts->forLogin($login(['klubecashUserId' => 999999]), $link);
    $check($result['outcome'] === GrupoKlubeAccounts::EXISTING && $result['user'] === null, 'id inexistente não cria nada');

    echo "OK: contas do Grupo Klube validadas.\n";
} finally {
    $db->exec('DROP DATABASE ' . $name);
}
