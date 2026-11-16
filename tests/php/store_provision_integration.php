<?php

declare(strict_types=1);

// Provisionamento pela Loja do Grupo Klube (loja aprovada, ligação ao login único, assinatura) e cadastro em etapas.
// Recusa o banco da aplicação: use um MySQL descartável em loopback (igual aos outros testes de integração).
// Rodar: GIFTBACK_TEST_DSN="mysql:host=127.0.0.1;port=3399" php tests/php/store_provision_integration.php
$dsn = (string) getenv('GIFTBACK_TEST_DSN');
if (!preg_match('/^mysql:host=127\.0\.0\.1;port=\d+$/D', $dsn)) {
    fwrite(STDERR, "Required: GIFTBACK_TEST_DSN=mysql:host=127.0.0.1;port=<isolated test server port>\n");
    exit(2);
}

require_once __DIR__ . '/../../services/Partner/PartnerApiException.php';
require_once __DIR__ . '/../../services/StoreProvision/StoreProvisionService.php';
require_once __DIR__ . '/../../services/StoreProvision/StoreOnboardingService.php';

use App\Services\Partner\PartnerApiException;
use App\Services\StoreProvision\StoreOnboardingService;
use App\Services\StoreProvision\StoreProvisionService;

$user = getenv('GIFTBACK_TEST_USER') ?: 'root';
$pass = getenv('GIFTBACK_TEST_PASSWORD') ?: '';
$opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false];
$db = new PDO($dsn, $user, $pass, $opts);
$name = 'store_provision_test_' . bin2hex(random_bytes(6));
$db->exec('CREATE DATABASE ' . $name . ' CHARACTER SET utf8mb4');
$db->exec('USE ' . $name);
// Uma segunda conexão (como a do login único, só leitura): só enxerga o que já foi CONFIRMADO.
$other = new PDO($dsn . ';dbname=' . $name, $user, $pass, $opts);

$db->exec("CREATE TABLE usuarios (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(100) NOT NULL, email VARCHAR(255) DEFAULT NULL UNIQUE,
  telefone VARCHAR(20) DEFAULT NULL, senha_hash VARCHAR(255) DEFAULT NULL, data_criacao TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_login TIMESTAMP NULL DEFAULT NULL, status ENUM('ativo','inativo','bloqueado') DEFAULT 'ativo',
  tipo ENUM('cliente','admin','loja','funcionario') DEFAULT 'cliente', provider ENUM('local','google') DEFAULT 'local'
) ENGINE=InnoDB");
$db->exec("CREATE TABLE lojas (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, usuario_id INT DEFAULT NULL, nome_fantasia VARCHAR(100) NOT NULL, razao_social VARCHAR(150) NOT NULL,
  cnpj VARCHAR(20) NOT NULL UNIQUE, email VARCHAR(100) NOT NULL, telefone VARCHAR(20) NOT NULL, categoria VARCHAR(50) DEFAULT 'Outros',
  porcentagem_cashback DECIMAL(5,2) NOT NULL, descricao TEXT DEFAULT NULL, website VARCHAR(255) DEFAULT NULL,
  status ENUM('pendente','aprovado','rejeitado') DEFAULT 'pendente', data_cadastro TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, data_aprovacao TIMESTAMP NULL DEFAULT NULL,
  porcentagem_cliente DECIMAL(5,2) DEFAULT 5.00, porcentagem_admin DECIMAL(5,2) DEFAULT 5.00, cashback_ativo TINYINT(1) DEFAULT 1, data_config_cashback TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB");
$db->exec('CREATE TABLE lojas_endereco (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, loja_id INT NOT NULL, cep VARCHAR(10), logradouro VARCHAR(255), numero VARCHAR(20), complemento VARCHAR(100), bairro VARCHAR(100), cidade VARCHAR(100), estado VARCHAR(50)) ENGINE=InnoDB');
$db->exec('CREATE TABLE cashback_saldos (id INT AUTO_INCREMENT PRIMARY KEY, usuario_id INT NOT NULL, loja_id INT NOT NULL, saldo_disponivel DECIMAL(10,2) DEFAULT 0) ENGINE=InnoDB');
$db->exec("CREATE TABLE planos (id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(100), slug VARCHAR(100), preco_mensal DECIMAL(10,2), ativo TINYINT(1) DEFAULT 1) ENGINE=InnoDB");
$db->exec("CREATE TABLE assinaturas (
  id INT AUTO_INCREMENT PRIMARY KEY, tipo ENUM('loja','membro') NOT NULL, loja_id INT NOT NULL, plano_id INT NOT NULL,
  status ENUM('trial','ativa','inadimplente','cancelada','suspensa') DEFAULT 'trial', ciclo ENUM('monthly','yearly') NOT NULL,
  current_period_start DATE NOT NULL, current_period_end DATE NOT NULL, next_invoice_date DATE DEFAULT NULL,
  sales_blocked TINYINT(1) DEFAULT 0, sales_block_reason VARCHAR(255) DEFAULT NULL, manual_activation_until DATE DEFAULT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB");
$db->exec("INSERT INTO planos (nome, slug, preco_mensal) VALUES ('Klube Start', 'KLUBE-START-M', 149.00), ('Essencial', 'essencial', 69.00)");

$check = static function (bool $condition, string $label): void {
    if (!$condition) { throw new RuntimeException('FALHOU: ' . $label); }
    echo "OK {$label}\n";
};
$one = static fn (string $sql, array $p = []): mixed => (static function () use ($db, $sql, $p) { $s = $db->prepare($sql); $s->execute($p); return $s->fetchColumn(); })();
$refuses = static function (callable $action, int $status, string $label): void {
    try { $action(); } catch (PartnerApiException $e) { if ($e->httpStatus !== $status) { throw new RuntimeException("FALHOU (status {$e->httpStatus}): {$label}"); } echo "OK {$label}\n"; return; }
    throw new RuntimeException('FALHOU (aceitou): ' . $label);
};
$payload = static fn (array $over = []): array => array_replace_recursive([
    'ref' => 'sub-0001-aaaa', 'identitySub' => '11111111-2222-3333-4444-555555555555',
    'customer' => ['name' => 'Ana Souza', 'email' => 'ana@exemplo.test'],
    'business' => ['name' => 'Mercadinho da Ana', 'docType' => 'cnpj', 'docNumber' => '11.222.333/0001-81', 'phone' => '(34) 99999-0000', 'city' => 'Patos de Minas', 'state' => 'MG'],
    'plan' => ['name' => 'Essencial', 'code' => null], 'validUntil' => date('Y-m-d', strtotime('+30 days')),
], $over);

try {
    $svc = new StoreProvisionService($db);
    $links = [];
    $visibleToOther = null;
    $link = static function (string $sub, int $id) use (&$links, &$visibleToOther, $other): void {
        $links[] = [$sub, $id];
        $st = $other->prepare("SELECT tipo FROM usuarios WHERE id = ?");
        $st->execute([$id]);
        $visibleToOther = $st->fetchColumn(); // o login único só liga o que já está confirmado
    };

    // 1) Assinante novo: conta, loja aprovada, cadastro pendente, assinatura ativa.
    $r = $svc->activate($payload(), $link);
    $check($r['created'] === true && $r['onboarding_pending'] === true, 'conta e loja criadas, cadastro em etapas pendente');
    $check($visibleToOther === 'cliente', 'a conta já estava confirmada (como cliente) quando o login único foi chamado');
    $check(count($links) === 1 && $links[0][0] === '11111111-2222-3333-4444-555555555555', 'ligou a identidade ao login único');
    $check($one("SELECT tipo FROM usuarios WHERE email='ana@exemplo.test'") === 'loja', 'depois de ligada, a conta virou loja');
    $check($one("SELECT status FROM lojas WHERE id=?", [$r['store_id']]) === 'aprovado', 'a loja nasce APROVADA, sem pedir aprovação');
    $check((int) $one("SELECT cashback_ativo FROM lojas WHERE id=?", [$r['store_id']]) === 0, 'cashback só liga no fim do cadastro');
    $check($one("SELECT cnpj FROM lojas WHERE id=?", [$r['store_id']]) === '11222333000181', 'CNPJ guardado só com números');
    $check($one("SELECT status FROM assinaturas WHERE loja_id=?", [$r['store_id']]) === 'ativa', 'assinatura comercial ativa');
    $check((int) $one("SELECT plano_id FROM assinaturas WHERE loja_id=?", [$r['store_id']]) === 2, 'plano escolhido pelo nome');
    $check((int) $one("SELECT sales_blocked FROM assinaturas WHERE loja_id=?", [$r['store_id']]) === 0, 'vendas liberadas');
    $storeId = (int) $r['store_id'];

    // 2) Repetir não duplica nada.
    $again = $svc->activate($payload(), $link);
    $check($again['created'] === false && (int) $again['store_id'] === $storeId, 'repetir a mesma referência é idempotente');
    $check((int) $one('SELECT COUNT(*) FROM lojas') === 1 && (int) $one('SELECT COUNT(*) FROM assinaturas') === 1 && count($links) === 1, 'nada duplicado e nenhuma nova ligação');

    // 3) Renovação: a validade anda.
    $new = date('Y-m-d', strtotime('+60 days'));
    $svc->activate($payload(['validUntil' => $new]), $link);
    $check($one('SELECT current_period_end FROM assinaturas WHERE loja_id=?', [$storeId]) === $new, 'a renovação atualiza a validade');

    // 3b) A loja manda no plano: o KlubeCash cria o plano dela (slug loja-<código>) e a assinatura passa a segui-lo.
    $svc->activate($payload(['plan' => ['name' => 'Pro', 'code' => 'pro', 'priceCents' => 12000]]), $link);
    $proId = (int) $one("SELECT id FROM planos WHERE slug='loja-pro'");
    $check($proId > 0 && $one('SELECT preco_mensal FROM planos WHERE id=?', [$proId]) === '120.00', 'plano da loja criado com o preço dela');
    $check((int) $one('SELECT plano_id FROM assinaturas WHERE loja_id=?', [$storeId]) === $proId, 'a assinatura passou para o plano da loja');
    $svc->activate($payload(['plan' => ['name' => 'Pro', 'code' => 'pro', 'priceCents' => 13500]]), $link);
    $check($one('SELECT preco_mensal FROM planos WHERE id=?', [$proId]) === '135.00' && (int) $one("SELECT COUNT(*) FROM planos WHERE slug='loja-pro'") === 1, 'mudou o preço na loja: o mesmo plano é atualizado, sem duplicar');
    $check((int) $one("SELECT COUNT(*) FROM planos WHERE slug IN ('essencial','KLUBE-START-M')") === 2, 'os planos próprios do KlubeCash não foram tocados');

    // 4) Suspender bloqueia só as vendas novas; reativar volta.
    $s = $svc->suspend(['ref' => 'sub-0001-aaaa', 'reason' => 'Assinatura em atraso']);
    $check($s['suspended'] === true && $one('SELECT status FROM assinaturas WHERE loja_id=?', [$storeId]) === 'suspensa', 'suspender coloca a assinatura em suspensa');
    $check((int) $one('SELECT sales_blocked FROM assinaturas WHERE loja_id=?', [$storeId]) === 1, 'vendas novas bloqueadas');
    $check($one('SELECT status FROM lojas WHERE id=?', [$storeId]) === 'aprovado', 'a loja continua aprovada (dados e leitura preservados)');
    $svc->activate($payload(), $link);
    $check($one('SELECT status FROM assinaturas WHERE loja_id=?', [$storeId]) === 'ativa' && (int) $one('SELECT sales_blocked FROM assinaturas WHERE loja_id=?', [$storeId]) === 0, 'reativar libera as vendas');
    $check($svc->suspend(['ref' => 'sub-9999-zzzz'])['found'] === false, 'suspender referência desconhecida não faz nada');

    // 5) Conta de cliente com senha: nunca converte.
    $db->exec("INSERT INTO usuarios (nome, email, senha_hash, tipo) VALUES ('Beto', 'beto@exemplo.test', 'hash', 'cliente')");
    $refuses(fn () => $svc->activate($payload(['ref' => 'sub-0002-bbbb', 'customer' => ['email' => 'beto@exemplo.test'], 'business' => ['docNumber' => '22333444000195']]), $link), 409, 'não converte conta de cliente com senha em loja');
    $check((int) $one('SELECT COUNT(*) FROM lojas') === 1, 'nenhuma loja criada na recusa');

    // 6) Sobra sem senha, sem login e sem saldo: aproveita.
    $db->exec("INSERT INTO usuarios (nome, email, tipo) VALUES ('Carla', 'carla@exemplo.test', 'cliente')");
    $c = $svc->activate($payload(['ref' => 'sub-0003-cccc', 'identitySub' => '99999999-2222-3333-4444-555555555555', 'customer' => ['email' => 'carla@exemplo.test'], 'business' => ['docNumber' => '33444555000106']]), $link);
    $check($c['created'] === false && $one("SELECT tipo FROM usuarios WHERE email='carla@exemplo.test'") === 'loja', 'aproveita conta nova sem uso e a promove a loja');
    // ...mas não se ela tem saldo.
    $db->exec("INSERT INTO usuarios (nome, email, tipo) VALUES ('Davi', 'davi@exemplo.test', 'cliente')");
    $db->exec("INSERT INTO cashback_saldos (usuario_id, loja_id, saldo_disponivel) VALUES (" . (int) $one("SELECT id FROM usuarios WHERE email='davi@exemplo.test'") . ", 1, 12.50)");
    $refuses(fn () => $svc->activate($payload(['ref' => 'sub-0004-dddd', 'customer' => ['email' => 'davi@exemplo.test'], 'business' => ['docNumber' => '44555666000117']]), $link), 409, 'recusa conta com saldo de cashback');

    // 7) Falha ao ligar ao login único: nada fica para trás.
    $before = (int) $one('SELECT COUNT(*) FROM usuarios');
    $refuses(fn () => $svc->activate($payload(['ref' => 'sub-0005-eeee', 'customer' => ['email' => 'erica@exemplo.test'], 'business' => ['docNumber' => '55666777000128']]), static function (): void { throw new RuntimeException('login único fora do ar'); }), 502, 'falha na ligação vira erro claro');
    $check((int) $one('SELECT COUNT(*) FROM usuarios') === $before, 'a conta criada para a tentativa é removida');

    // 8) CNPJ de outra loja.
    $refuses(fn () => $svc->activate($payload(['ref' => 'sub-0006-ffff', 'customer' => ['email' => 'fabio@exemplo.test'], 'business' => ['docNumber' => '11222333000181']]), $link), 409, 'recusa CNPJ que já é de outra loja');

    // 9) Validação.
    $refuses(fn () => $svc->activate($payload(['ref' => 'x']), $link), 422, 'referência curta recusada');
    $refuses(fn () => $svc->activate($payload(['identitySub' => 'nao-e-uuid']), $link), 422, 'identidade inválida recusada');
    $refuses(fn () => $svc->activate($payload(['business' => ['docNumber' => '123']]), $link), 422, 'CPF/CNPJ inválido recusado');

    // 10) Cadastro em etapas.
    $ob = new StoreOnboardingService($db);
    $check($ob->pending($storeId) === true, 'cadastro pendente trava o painel');
    $check($ob->pending(99999) === false, 'loja sem cadastro pendente não é travada');
    $bad = $ob->save($storeId, 1, ['nome_fantasia' => 'A', 'razao_social' => '', 'telefone' => '12', 'categoria' => 'Qualquer']);
    $check(!$bad['ok'] && count($bad['errors']) === 4, 'etapa 1 inválida lista os erros');
    $ok1 = $ob->save($storeId, 1, ['nome_fantasia' => 'Mercadinho da Ana', 'razao_social' => 'Ana Souza ME', 'telefone' => '(34) 99999-0000', 'categoria' => 'Alimentação', 'descricao' => 'Hortifruti']);
    $check($ok1['ok'] && $ok1['step'] === 2, 'etapa 1 grava e avança');
    $check($ob->complete($storeId)['ok'] === false, 'não conclui sem o endereço');
    $bad2 = $ob->save($storeId, 2, ['cep' => '123', 'estado' => 'XX']);
    $check(!$bad2['ok'], 'etapa 2 inválida recusada');
    $ok2 = $ob->save($storeId, 2, ['cep' => '38700-000', 'logradouro' => 'Rua A', 'numero' => '10', 'bairro' => 'Centro', 'cidade' => 'Patos de Minas', 'estado' => 'MG']);
    $check($ok2['ok'] && $ok2['step'] === 3, 'etapa 2 grava e avança');
    $ok3 = $ob->save($storeId, 3, ['cashback_ativo' => '1']);
    $check($ok3['ok'] && $ok3['step'] === 4 && (int) $one('SELECT cashback_ativo FROM lojas WHERE id=?', [$storeId]) === 1, 'etapa 3 liga o cashback');
    $check($ob->load($storeId)['step'] === 4, 'volta de onde parou');
    $done = $ob->save($storeId, 4, []);
    $check($done['ok'] && $ob->pending($storeId) === false, 'etapa 4 conclui e libera o painel');
    $check($one('SELECT razao_social FROM lojas WHERE id=?', [$storeId]) === 'Ana Souza ME', 'os dados ficaram na loja');
    $check($svc->status('sub-0001-aaaa')['onboarding_pending'] === false, 'o estado da assinatura reflete o cadastro concluído');
    echo "TUDO OK\n";
} finally {
    $db->exec('DROP DATABASE IF EXISTS ' . $name);
}
