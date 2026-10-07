<?php

declare(strict_types=1);

// Only an explicitly supplied loopback, disposable MySQL server is permitted.
require_once __DIR__ . '/../../services/Giftback/GiftbackSchema.php';
require_once __DIR__ . '/../../services/Giftback/GiftbackLedger.php';
require_once __DIR__ . '/../../services/StoreWallet/StoreWalletSchema.php';
require_once __DIR__ . '/../../services/StoreWallet/StoreWalletService.php';

use App\Services\Giftback\GiftbackSchema;
use App\Services\Giftback\GiftbackLedger;
use App\Services\StoreWallet\StoreWalletSchema;
use App\Services\StoreWallet\StoreWalletService;
use App\Services\StoreWallet\StoreWalletError;

$dsn = (string) getenv('WALLET_LINK_TEST_DSN');
if (!preg_match('/^mysql:host=127\.0\.0\.1;port=\d+$/D', $dsn)) { fwrite(STDERR, "Set WALLET_LINK_TEST_DSN to a disposable loopback MySQL server.\n"); exit(2); }
$db = new PDO($dsn, getenv('WALLET_LINK_TEST_USER') ?: 'root', getenv('WALLET_LINK_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$name = 'wallet_link_test_' . bin2hex(random_bytes(5));
$db->exec('CREATE DATABASE ' . $name . ' CHARACTER SET utf8mb4');
$db->exec('USE ' . $name);
$results = [];
function check(bool $condition, string $name): void { global $results; if (!$condition) throw new RuntimeException($name); $results[] = 'OK ' . $name; }
function forbidden(callable $fn, int $status, string $name): void { try { $fn(); } catch (StoreWalletError $e) { check($e->httpStatus === $status, $name); return; } throw new RuntimeException($name); }
function query(PDO $db, string $sql, array $params = []): mixed { $s = $db->prepare($sql); $s->execute($params); return $s->fetchColumn(); }
try {
    $db->exec("CREATE TABLE usuarios(id INT PRIMARY KEY,nome VARCHAR(100),email VARCHAR(255) UNIQUE,telefone VARCHAR(20),senha_hash VARCHAR(255),tipo VARCHAR(20),tipo_cliente VARCHAR(20),loja_criadora_id INT NULL,status VARCHAR(20),provider VARCHAR(20),email_verified TINYINT) ENGINE=InnoDB;
    CREATE TABLE lojas(id INT PRIMARY KEY,nome_fantasia VARCHAR(100),logo VARCHAR(255),descricao TEXT,status VARCHAR(20)) ENGINE=InnoDB;
    CREATE TABLE cashback_saldos(id INT AUTO_INCREMENT PRIMARY KEY,usuario_id INT,loja_id INT,saldo_disponivel DECIMAL(10,2) DEFAULT 0,total_creditado DECIMAL(10,2) DEFAULT 0,total_usado DECIMAL(10,2) DEFAULT 0,ultima_atualizacao DATETIME NULL,UNIQUE(usuario_id,loja_id)) ENGINE=InnoDB;
    CREATE TABLE cashback_movimentacoes(id INT AUTO_INCREMENT PRIMARY KEY,usuario_id INT,loja_id INT,criado_por INT NULL,tipo_operacao ENUM('credito','uso','estorno'),valor DECIMAL(10,2),saldo_anterior DECIMAL(10,2),saldo_atual DECIMAL(10,2),descricao VARCHAR(255),transacao_origem_id INT NULL,transacao_uso_id INT NULL,data_operacao DATETIME NULL) ENGINE=InnoDB;
    CREATE TABLE transacoes_cashback(id INT AUTO_INCREMENT PRIMARY KEY,usuario_id INT,loja_id INT,valor_cliente DECIMAL(10,2),status VARCHAR(30)) ENGINE=InnoDB;
    CREATE TABLE transacoes_saldo_usado(id INT AUTO_INCREMENT PRIMARY KEY,transacao_id INT,usuario_id INT,loja_id INT,valor_usado DECIMAL(10,2),data_uso DATETIME) ENGINE=InnoDB");
    $db->exec("INSERT INTO lojas VALUES(1,'Loja Um',NULL,NULL,'aprovado'),(2,'Loja Dois',NULL,NULL,'aprovado'),(3,'Pendente',NULL,NULL,'pendente')");
    $password = password_hash('Senha-segura-123', PASSWORD_DEFAULT);
    $insert = $db->prepare('INSERT INTO usuarios(id,nome,email,telefone,senha_hash,tipo,tipo_cliente,loja_criadora_id,status,provider,email_verified) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
    $insert->execute([1,'Visitante Um','visitante_11999999999_loja_1@klubecash.local','11999999999',null,'cliente','visitante',1,'ativo','local',0]);
    $insert->execute([2,'Conta Completa','conta@example.test','11988888888',$password,'cliente','completo',null,'ativo','local',0]);
    $insert->execute([3,'Visitante Dois','visitante_11999999999_loja_2@klubecash.local','11999999999',null,'cliente','visitante',2,'ativo','local',0]);
    $insert->execute([4,'Visitante Cadastro','visitante_11977777777_loja_2@klubecash.local','11977777777',null,'cliente','visitante',2,'ativo','local',0]);
    $insert->execute([5,'Visitante Vencido','visitante_11966666666_loja_1@klubecash.local','11966666666',null,'cliente','visitante',1,'ativo','local',0]);
    GiftbackSchema::migrate($db, true);
    check(StoreWalletSchema::migrate($db)['apply'] === false, 'migração simula antes de aplicar');
    StoreWalletSchema::migrate($db, true);
    StoreWalletSchema::migrate($db, true);
    check((int) query($db, 'SELECT COUNT(*) FROM store_wallet_links') === 2, 'migração reexecutável e só lojas aprovadas');
    $service = new StoreWalletService($db); $ledger = new GiftbackLedger($db);
    $token1 = (string) query($db, 'SELECT token FROM store_wallet_links WHERE store_id=1');
    $token2 = (string) query($db, 'SELECT token FROM store_wallet_links WHERE store_id=2');
    check($token1 !== $token2, 'links distintos');
    $ledger->credit(1, 1, 10000, 'Visitante', null, null, 'test-v1');
    $ledger->credit(2, 1, 5000, 'Conta', null, null, 'test-a1');
    $ledger->credit(3, 2, 2000, 'Outra loja', null, null, 'test-v2');
    $ledger->credit(4, 2, 2300, 'Saldo para completar cadastro', null, null, 'test-v4');
    $db->exec('UPDATE lojas SET giftback_expiration_days=1 WHERE id=1');
    (new GiftbackLedger($db, new DateTimeImmutable('2026-01-01', new DateTimeZone('America/Sao_Paulo'))))->credit(5,1,900,'Crédito antigo',null,null,'test-expired');
    $db->exec('UPDATE lojas SET giftback_expiration_days=NULL WHERE id=1');
    check($ledger->wallet(5,1)['availableCents'] === 0, 'crédito vencido não aparece como saldo');
    $db->exec("INSERT INTO transacoes_cashback(usuario_id,loja_id,valor_cliente,status) VALUES(1,1,0,'aprovado')");
    $sale = (int) $db->lastInsertId();
    $ledger->spend(1, 1, 3000, 'Uso parcial', $sale);
    $db->prepare('INSERT INTO transacoes_saldo_usado(transacao_id,usuario_id,loja_id,valor_usado,data_uso) VALUES(?,?,?,?,NOW())')->execute([$sale,1,1,'30.00']);
    session_start(); putenv('JWT_SECRET=isolated-test-secret-not-production');
    forbidden(fn () => $service->wallet($token1), 401, 'posse do link não revela saldo');
    $code = '123456'; $phone = '5511999999999';
    $seed = $db->prepare('INSERT INTO store_wallet_challenges(store_id,session_hash,purpose,phone,code_hash,expires_at,sent_at) VALUES(?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 5 MINUTE),NOW())');
    $seed->execute([1,hash('sha256', session_id()),'visitor',$phone,hash_hmac('sha256',$code,(string) getenv('JWT_SECRET'))]);
    forbidden(fn () => $service->verifyCode($token1,$phone,'visitor','000000','Visitante Um'), 422, 'código errado negado');
    $attempts = (int) query($db, 'SELECT attempts FROM store_wallet_challenges ORDER BY id DESC LIMIT 1');
    check($attempts === 1, 'tentativa errada contabilizada (' . $attempts . ')');
    $service->verifyCode($token1,$phone,'visitor',$code,'Visitante Um');
    check($service->wallet($token1)['wallet']['availableCents'] === 7000, 'visitante confirmado vê só saldo válido da loja');
    forbidden(fn () => $service->wallet($token2), 401, 'sessão visitante isolada da segunda loja');
    $db->prepare('INSERT INTO store_wallet_challenges(store_id,session_hash,purpose,phone,code_hash,expires_at,sent_at) VALUES(?,?,?,?,?,DATE_SUB(NOW(),INTERVAL 1 MINUTE),NOW())')->execute([2,hash('sha256',session_id()),'signup','5511988888888',hash_hmac('sha256','654321',(string) getenv('JWT_SECRET'))]);
    forbidden(fn () => $service->verifyCode($token2,'5511988888888','signup','654321'), 422, 'código expirado não autoriza cadastro');
    $service->login($token1,'conta@example.test','Senha-segura-123');
    check($service->wallet($token1)['wallet']['availableCents'] === 5000, 'login mostra carteira da conta ativa');
    check($service->context($token1)['hasPreviousPhoneBalance'] === false, 'conta sem saldo anterior não recebe convite de vinculação');
    $db->exec("UPDATE usuarios SET telefone='11999999999' WHERE id=2");
    check($service->context($token1)['hasPreviousPhoneBalance'] === true, 'convite aparece para saldo válido no telefone da conta');
    $db->exec("UPDATE usuarios SET telefone='11977777777' WHERE id=2");
    check($service->context($token1)['hasPreviousPhoneBalance'] === false, 'saldo anterior de outra loja não gera convite');
    check($service->context($token2)['hasPreviousPhoneBalance'] === true, 'saldo anterior fica limitado à loja do link');
    $db->exec("UPDATE usuarios SET telefone='11966666666' WHERE id=2");
    check($service->context($token1)['hasPreviousPhoneBalance'] === false, 'crédito já vencido não gera convite');
    $db->exec("UPDATE usuarios SET telefone='11988888888' WHERE id=2");
    $_SESSION['wallet_phone_proof'] = ['storeId'=>1,'phone'=>$phone,'purpose'=>'claim','challengeId'=>1,'accountId'=>2,'until'=>time()+300];
    $claim = $service->claim($token1);
    check($claim['balanceCents'] === 12000, 'vinculação soma carteiras com uso parcial preservado');
    check($service->claim($token1)['replayed'] === true, 'clique repetido não duplica saldo');
    check((int) query($db, 'SELECT COUNT(*) FROM cashback_creditos WHERE usuario_id=3 AND loja_id=2') === 1, 'outra loja não é vinculada');
    check($ledger->spend(2,1,3000,'repetição',$sale)['replayed'] === true, 'webhook de uso anterior não desconta duas vezes');
    check($ledger->reverseSale(2,1,$sale,'Estorno')['restoredBalanceUsedCents'] === 3000, 'estorno preservado após vinculação');
    check($ledger->wallet(2,1)['availableCents'] === 15000, 'estorno retorna ao crédito correto');
    $changed = $service->changeLink(1,'rotate',1,2);
    forbidden(fn () => $service->publicStore($token1), 404, 'troca invalida link antigo');
    $service->changeLink(1,'disable',$changed['version'],2);
    forbidden(fn () => $service->publicStore($changed['token']), 404, 'desativação fecha a consulta');
    check((int) query($db, 'SELECT COUNT(*) FROM store_wallet_link_events WHERE store_id=1') === 2, 'alterações do link auditadas');
    unset($_SESSION['user_id'], $_SESSION['user_type'], $_SESSION['user_name'], $_SESSION['user_email']);
    $_SESSION['wallet_phone_proof'] = ['storeId'=>2,'phone'=>'5511977777777','purpose'=>'signup','challengeId'=>1,'until'=>time()+300];
    forbidden(fn () => $service->signup($token2,['name'=>'Cliente Novo','email'=>'novo@example.test','password'=>'Senha-segura-123','confirmation'=>'Outra-senha-123']), 422, 'duas senhas diferentes recusadas');
    $service->signup($token2,['name'=>'Cliente Novo','email'=>'novo@example.test','password'=>'Senha-segura-123','confirmation'=>'Senha-segura-123']);
    check($service->wallet($token2)['wallet']['availableCents'] === 2300, 'cadastro na página preserva carteira do visitante');
    check((int) query($db, "SELECT COUNT(*) FROM usuarios WHERE id=4 AND tipo_cliente='completo' AND email='novo@example.test'") === 1, 'visitante completado sem usuário duplicado');
    GiftbackSchema::assertReconciled($db);
    $results[] = 'OK reconciliação global';
    echo implode(PHP_EOL, $results), PHP_EOL;
} finally {
    $db->exec('DROP DATABASE ' . $name);
}
