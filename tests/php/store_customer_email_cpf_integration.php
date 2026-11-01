<?php
declare(strict_types=1);

// The store registers a sale from the customer's e-mail, then completes the registration with the CPF.
// Intentionally refuses the application database: supply a disposable MySQL on loopback.
// Run: GIFTBACK_TEST_DSN="mysql:host=127.0.0.1;port=3399" GIFTBACK_TEST_PASSWORD=... php tests/php/store_customer_email_cpf_integration.php
$dsn = (string) getenv('GIFTBACK_TEST_DSN');
if (!preg_match('/^mysql:host=127\.0\.0\.1;port=\d+$/D', $dsn)) {
    fwrite(STDERR, "Required: GIFTBACK_TEST_DSN=mysql:host=127.0.0.1;port=<isolated test server port>\n");
    exit(2);
}
require_once __DIR__ . '/../../services/Giftback/GiftbackSchema.php';
require_once __DIR__ . '/../../services/Giftback/GiftbackLedger.php';
foreach (['StoreApiException', 'StoreMoney', 'StoreNetworkAccess', 'StoreCustomerService'] as $class) { require_once __DIR__ . '/../../services/store/' . $class . '.php'; }

use App\Services\Giftback\GiftbackSchema;
use App\Services\Store\StoreApiException;
use App\Services\Store\StoreCustomerService;

$db = new PDO($dsn, getenv('GIFTBACK_TEST_USER') ?: 'root', getenv('GIFTBACK_TEST_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$name = 'customer_test_' . bin2hex(random_bytes(6));
$db->exec('CREATE DATABASE ' . $name . ' CHARACTER SET utf8mb4');
$db->exec('USE ' . $name);

$check = static function (bool $condition, string $label): void {
    if (!$condition) { throw new RuntimeException('FALHOU: ' . $label); }
    echo "OK {$label}\n";
};
$refuses = static function (callable $action, int $status, string $label): void {
    try { $action(); } catch (StoreApiException $error) {
        if ($error->httpStatus !== $status) { throw new RuntimeException("FALHOU ({$error->httpStatus}): {$label}"); }
        echo "OK {$label}\n";
        return;
    }
    throw new RuntimeException('FALHOU (aceitou): ' . $label);
};
$scalar = static function (string $sql, array $params = []) use ($db): mixed {
    $statement = $db->prepare($sql);
    $statement->execute($params);
    return $statement->fetchColumn();
};

try {
    $db->exec("CREATE TABLE usuarios(id INT AUTO_INCREMENT PRIMARY KEY,nome VARCHAR(100) NOT NULL,email VARCHAR(255) DEFAULT NULL,telefone VARCHAR(20) DEFAULT NULL,
            cpf VARCHAR(14) DEFAULT NULL,senha_hash VARCHAR(255) DEFAULT NULL,status ENUM('ativo','inativo','bloqueado') DEFAULT 'ativo',
            tipo ENUM('cliente','admin','loja','funcionario') DEFAULT 'cliente',tipo_cliente ENUM('completo','visitante') DEFAULT 'completo',
            loja_criadora_id INT DEFAULT NULL,provider ENUM('local','google') DEFAULT 'local',email_verified TINYINT(1) DEFAULT 0,
            UNIQUE KEY uk_cpf_unique(cpf),UNIQUE KEY unique_email_not_null(email)) ENGINE=InnoDB;
        CREATE TABLE lojas(id INT PRIMARY KEY,nome_fantasia VARCHAR(160),razao_social VARCHAR(160) DEFAULT 'Teste',email VARCHAR(160) DEFAULT 'loja@example.test',
            telefone VARCHAR(30) DEFAULT '',categoria VARCHAR(50) DEFAULT '',descricao TEXT,website VARCHAR(160) DEFAULT '',status VARCHAR(30) DEFAULT 'aprovado',
            porcentagem_cliente DECIMAL(5,2) DEFAULT 5,porcentagem_admin DECIMAL(5,2) DEFAULT 0,porcentagem_cashback DECIMAL(5,2) DEFAULT 5,cashback_ativo INT DEFAULT 1,
            data_config_cashback DATETIME NULL,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB;
        CREATE TABLE cashback_saldos(id INT AUTO_INCREMENT PRIMARY KEY,usuario_id INT,loja_id INT,saldo_disponivel DECIMAL(10,2) DEFAULT 0,total_creditado DECIMAL(10,2) DEFAULT 0,total_usado DECIMAL(10,2) DEFAULT 0,ultima_atualizacao DATETIME NULL,UNIQUE(usuario_id,loja_id)) ENGINE=InnoDB;
        CREATE TABLE cashback_movimentacoes(id INT AUTO_INCREMENT PRIMARY KEY,usuario_id INT,loja_id INT,criado_por INT NULL,tipo_operacao ENUM('credito','uso','estorno'),valor DECIMAL(10,2),saldo_anterior DECIMAL(10,2),saldo_atual DECIMAL(10,2),descricao VARCHAR(255),transacao_origem_id INT NULL,transacao_uso_id INT NULL,data_operacao DATETIME NULL,pagamento_id INT NULL,redemption_store_id INT NULL,network_id_snapshot INT NULL) ENGINE=InnoDB;
        CREATE TABLE transacoes_cashback(id INT AUTO_INCREMENT PRIMARY KEY,usuario_id INT,loja_id INT,criado_por INT,valor_total DECIMAL(10,2),valor_cashback DECIMAL(10,2),valor_cliente DECIMAL(10,2),
            valor_admin DECIMAL(10,2),valor_loja DECIMAL(10,2),codigo_transacao VARCHAR(50),descricao VARCHAR(500),data_transacao DATETIME,status VARCHAR(30)) ENGINE=InnoDB;
        CREATE TABLE store_networks(id INT PRIMARY KEY,name VARCHAR(160),status VARCHAR(20),version INT,created_by INT);
        CREATE TABLE store_network_memberships(store_id INT PRIMARY KEY,network_id INT,status VARCHAR(20));
        CREATE TABLE network_visitor_verifications(network_id INT,user_id INT,challenge_id INT,verified_until DATETIME,PRIMARY KEY(network_id,user_id));
        CREATE TABLE store_wallet_claims(id INT AUTO_INCREMENT PRIMARY KEY,store_id INT,phone VARCHAR(20),account_id INT)");
    $db->exec("INSERT INTO lojas(id,nome_fantasia) VALUES(1,'Loja Sozinha'),(2,'Filial da Rede'),(3,'Outra Filial');
        INSERT INTO store_networks VALUES(7,'Rede Ficticia','active',1,1);
        INSERT INTO store_network_memberships VALUES(2,7,'active'),(3,7,'active');
        INSERT INTO usuarios(nome,email,tipo) VALUES('Dono Ficticio','dono@example.test','loja');
        INSERT INTO usuarios(nome,email,status) VALUES('Cliente Inativo','inativo@example.test','inativo');
        INSERT INTO usuarios(nome,email,cpf) VALUES('Cliente Antiga','antiga@example.test','52998224725')");
    GiftbackSchema::migrate($db, true);
    $customers = new StoreCustomerService($db);

    // E-mail first: unknown e-mail answers empty; staff and inactive accounts never become a buyer.
    $check($customers->findByEmail(1, 'Nova@Example.test')['customer'] === null, 'e-mail sem cadastro volta vazio para cadastrar');
    $refuses(fn () => $customers->findByEmail(1, 'dono@example.test'), 409, 'e-mail de lojista não vira cliente');
    $refuses(fn () => $customers->findByEmail(1, 'inativo@example.test'), 409, 'e-mail de conta inativa não vira cliente');
    $refuses(fn () => $customers->findByEmail(1, 'sem-arroba'), 422, 'e-mail inválido é recusado');
    $refuses(fn () => $customers->findByEmail(1, 'visitante_11999990000_loja_1@klubecash.local'), 422, 'e-mail fictício de visitante é recusado');

    // A new customer: registered with the real e-mail, no password, and the CPF still missing.
    $new = $customers->register(1, 'Maria Ficticia', 'Nova@Example.test', '');
    $check($new['customer']['email'] === 'nova@example.test' && $new['customer']['type'] === 'registered' && $new['customer']['needsCpf'] === true, 'cliente novo é cadastrado com e-mail e pede CPF');
    $check($scalar("SELECT CONCAT(tipo_cliente,'|',loja_criadora_id,'|',COALESCE(senha_hash,'sem')) FROM usuarios WHERE email='nova@example.test'") === 'completo|1|sem', 'cadastro completo, da loja, sem senha');
    $again = $customers->register(1, 'Maria Ficticia', 'nova@example.test', '');
    $check($again['customer']['id'] === $new['customer']['id'] && (int) $scalar("SELECT COUNT(*) FROM usuarios WHERE email='nova@example.test'") === 1, 'cadastrar de novo o mesmo e-mail não duplica');
    $refuses(fn () => $customers->register(1, 'Al', 'curto@example.test', ''), 422, 'nome curto é recusado');
    $refuses(fn () => $customers->register(1, 'Telefone Ruim', 'tel@example.test', '123'), 422, 'telefone incompleto é recusado');

    // CPF: valid, unique, and only fills an empty CPF.
    $refuses(fn () => $customers->saveCpf(1, $new['customer']['id'], '111.111.111-11'), 422, 'CPF inválido é recusado');
    $refuses(fn () => $customers->saveCpf(1, $new['customer']['id'], '529.982.247-25'), 409, 'CPF de outro cliente é recusado');
    $saved = $customers->saveCpf(1, $new['customer']['id'], '168.995.350-09');
    $check($saved['customer']['cpf'] === '16899535009' && $saved['customer']['needsCpf'] === false, 'CPF salvo só com números e cadastro completo');
    $check($customers->saveCpf(1, $new['customer']['id'], '16899535009')['customer']['needsCpf'] === false, 'repetir o mesmo CPF não dá erro');
    $refuses(fn () => $customers->saveCpf(1, $new['customer']['id'], '39053344705'), 409, 'loja não troca um CPF já informado');
    $check($customers->findByEmail(1, 'nova@example.test')['customer']['needsCpf'] === false, 'busca seguinte já não pede CPF');

    // A visitor of this store with the same phone is completed, keeping the account (and its balance and purchases).
    $visitor = $customers->createVisitor(1, 'Visitante Ficticio', '11988887777');
    $completed = $customers->register(1, 'Joao Ficticio', 'joao@example.test', '(11) 98888-7777');
    $check($completed['customer']['id'] === $visitor['customer']['id'] && $completed['customer']['type'] === 'registered', 'visitante da loja vira cliente completo com o mesmo telefone');
    $check((int) $scalar("SELECT COUNT(*) FROM usuarios WHERE telefone='11988887777'") === 1, 'sem conta duplicada para o visitante');

    // A network visitor that never confirmed the phone is not taken over by typing its phone.
    $networkVisitor = $customers->createVisitor(2, 'Visitante da Rede', '11977776666');
    $refuses(fn () => $customers->register(2, 'Outra Pessoa', 'outra@example.test', '11977776666'), 409, 'visitante da rede sem telefone confirmado não é assumido');
    $check($scalar('SELECT tipo_cliente FROM usuarios WHERE id=?', [$networkVisitor['customer']['id']]) === 'visitante', 'visitante da rede continua intacto');
    $db->exec('INSERT INTO network_visitor_verifications VALUES(7,' . (int) $networkVisitor['customer']['id'] . ',1,NOW() + INTERVAL 1 DAY)');
    $check($customers->register(2, 'Ana Ficticia', 'ana@example.test', '11977776666')['customer']['id'] === $networkVisitor['customer']['id'], 'com o telefone confirmado, o visitante da rede é completado');

    echo "OK: cadastro da loja por e-mail e CPF validado.\n";
} finally {
    $db->exec('DROP DATABASE ' . $name);
}
