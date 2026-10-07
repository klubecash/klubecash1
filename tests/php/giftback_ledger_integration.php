<?php
declare(strict_types=1);

// Never boot the application or load .env. This test creates its own empty DB
// on an explicitly supplied loopback server; no production fixtures are used.
require_once __DIR__ . '/../../services/Giftback/GiftbackSchema.php';
require_once __DIR__ . '/../../services/Giftback/GiftbackLedger.php';
use App\Services\Giftback\GiftbackSchema;
use App\Services\Giftback\GiftbackLedger;
use App\Services\Giftback\GiftbackException;

$dsn = (string) getenv('GIFTBACK_TEST_DSN');
if (!preg_match('/^mysql:host=127\.0\.0\.1;port=\d+$/D', $dsn)) {
    fwrite(STDERR, "Required: GIFTBACK_TEST_DSN=mysql:host=127.0.0.1;port=<isolated test server port>\n"); exit(2);
}
$db = new PDO($dsn, getenv('GIFTBACK_TEST_USER') ?: 'root', getenv('GIFTBACK_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$db->exec("SET time_zone='-03:00'");
$zone = new DateTimeZone('America/Sao_Paulo');
function at(string $date): GiftbackLedger { global $db, $zone; return new GiftbackLedger($db, new DateTimeImmutable($date, $zone)); }
function eq(mixed $expected, mixed $actual, string $name): void { if ($expected !== $actual) { throw new RuntimeException($name . ': expected ' . json_encode($expected) . ', got ' . json_encode($actual)); } echo "OK: {$name}\n"; }
function denied(callable $fn, int $status, string $name): void { try { $fn(); } catch (GiftbackException $error) { eq($status, $error->httpStatus, $name); return; } throw new RuntimeException('Expected rejection: ' . $name); }
function sale(int $user, int $store, int $value): int { global $db; $db->prepare("INSERT INTO transacoes_cashback(usuario_id,loja_id,valor_cliente,status) VALUES(?,?,?,'aprovado')")->execute([$user,$store,$value/100]); return (int) $db->lastInsertId(); }
function scalar(string $sql): mixed { global $db; return $db->query($sql)->fetchColumn(); }

if (($argv[1] ?? '') === '--race') {
    $name = $argv[2] ?? '';
    if (!preg_match('/^giftback_test_[a-f0-9]+$/D', $name)) { exit(2); }
    $db->exec('USE ' . $name);
    try {
        if ($argv[3] === 'spend') { at('2026-02-01')->spend(40,1,7000,'Concurrent purchase',null,null,$argv[4]); }
        elseif ($argv[3] === 'restore') { at('2026-03-01')->restoreCredit((int)$argv[4],(int)$argv[5],90,'2026-04-01','Individual concurrent restore',(int)$argv[6],$argv[7]); }
        elseif ($argv[3] === 'expire') { at('2026-02-20')->expireDue(); }
        elseif ($argv[3] === 'extend') { at('2026-02-19 23:59:59')->extendCredit((int)$argv[4],90,'2026-03-15','Concurrent extension',(int)$argv[5],'race-extend'); }
        echo 'SUCCESS';
    } catch (GiftbackException $error) { echo 'REJECTED:' . $error->httpStatus; }
    exit;
}

$name = 'giftback_test_' . bin2hex(random_bytes(6));
$db->exec('CREATE DATABASE ' . $name . ' CHARACTER SET utf8mb4');
$db->exec('USE ' . $name);
echo "Isolated database: {$name}\n";
$db->exec("CREATE TABLE usuarios(id INT PRIMARY KEY,nome VARCHAR(255),tipo VARCHAR(30),status VARCHAR(30)) ENGINE=InnoDB;
CREATE TABLE lojas(id INT PRIMARY KEY,nome_fantasia VARCHAR(255)) ENGINE=InnoDB;
CREATE TABLE cashback_saldos(id INT AUTO_INCREMENT PRIMARY KEY,usuario_id INT,loja_id INT,saldo_disponivel DECIMAL(10,2) DEFAULT 0,total_creditado DECIMAL(10,2) DEFAULT 0,total_usado DECIMAL(10,2) DEFAULT 0,ultima_atualizacao DATETIME NULL,UNIQUE(usuario_id,loja_id)) ENGINE=InnoDB;
CREATE TABLE cashback_movimentacoes(id INT AUTO_INCREMENT PRIMARY KEY,usuario_id INT,loja_id INT,criado_por INT NULL,tipo_operacao ENUM('credito','uso','estorno'),valor DECIMAL(10,2),saldo_anterior DECIMAL(10,2),saldo_atual DECIMAL(10,2),descricao VARCHAR(255),transacao_origem_id INT NULL,transacao_uso_id INT NULL,data_operacao DATETIME NULL,pagamento_id INT NULL) ENGINE=InnoDB;
CREATE TABLE transacoes_cashback(id INT AUTO_INCREMENT PRIMARY KEY,usuario_id INT,loja_id INT,valor_cliente DECIMAL(10,2),status VARCHAR(30)) ENGINE=InnoDB;
CREATE TABLE admin_audit_logs(id INT AUTO_INCREMENT PRIMARY KEY,actor_id INT,action VARCHAR(100),entity_type VARCHAR(80),entity_id VARCHAR(100),result VARCHAR(20),before_json JSON,after_json JSON,request_id VARCHAR(64)) ENGINE=InnoDB");
for ($i=1;$i<=100;$i++) { $db->prepare('INSERT INTO usuarios VALUES(?,?,?,?)')->execute([$i,'Fixture '.$i,$i>=90?'admin':'cliente',$i===91?'inativo':'ativo']); }
$db->exec("INSERT INTO lojas VALUES(1,'Test A'),(2,'Test B'); INSERT INTO cashback_saldos(usuario_id,loja_id,saldo_disponivel,total_creditado) VALUES(2,1,100,100),(3,1,0,0)");
$oldSale = sale(2,1,10000);
$db->prepare("INSERT INTO cashback_movimentacoes(usuario_id,loja_id,tipo_operacao,valor,saldo_anterior,saldo_atual,transacao_origem_id) VALUES(2,1,'credito',100,0,100,?)")->execute([$oldSale]);
$oldMixed=sale(12,1,5000);
$db->exec('INSERT INTO cashback_saldos(usuario_id,loja_id,saldo_disponivel,total_creditado,total_usado) VALUES(12,1,10,50,40),(13,1,0,100,100)');
$db->prepare("INSERT INTO cashback_movimentacoes(usuario_id,loja_id,tipo_operacao,valor,saldo_anterior,saldo_atual,transacao_origem_id) VALUES(12,1,'credito',50,0,50,?)")->execute([$oldMixed]);
$oldUsage=sale(13,1,0);
$db->prepare("INSERT INTO cashback_movimentacoes(usuario_id,loja_id,tipo_operacao,valor,saldo_anterior,saldo_atual,transacao_uso_id) VALUES(13,1,'uso',100,100,0,?)")->execute([$oldUsage]);
eq(false, GiftbackSchema::migrate($db)['apply'], 'migration dry run');
GiftbackSchema::migrate($db,true);
GiftbackSchema::migrate($db,true);
eq(4,(int) scalar('SELECT COUNT(*) FROM cashback_credito_carteiras'),'migration replay including zero wallet');
eq(10000,at('2026-01-20')->settleWallet(2,1),'opening preserves balance');
eq(null,at('2026-01-20')->wallet(2,1)['credits'][0]['validUntil'],'opening has no expiry');
eq(true,at('2026-01-20')->credit(2,1,10000,'legacy replay',$oldSale)['replayed'],'pre-cutover credit replay');
$db->exec('UPDATE lojas SET giftback_expiration_days=30 WHERE id=1');
$saleA = sale(1,1,10000); $saleB = sale(1,1,5000);
$a=at('2026-01-20 14:00:00')->credit(1,1,10000,'A',$saleA);
$b=at('2026-01-22 10:00:00')->credit(1,1,5000,'B',$saleB);
eq('2026-02-19',at('2026-01-22')->creditDetail($a['creditId'])['validUntil'],'30 calendar days from Jan 20');
eq('2026-02-21',at('2026-01-22')->creditDetail($b['creditId'])['validUntil'],'separate second deadline');
at('2026-01-25')->spend(1,1,4000,'partial',null,null,'partial');
eq(6000,at('2026-01-25')->creditDetail($a['creditId'])['remainingCents'],'FEFO partial');
eq(11000,at('2026-02-19 23:59:59')->settleWallet(1,1),'valid through last second');
eq(5000,at('2026-02-20 00:00:00')->settleWallet(1,1),'only first remainder expires');
eq(4000,(int) round((float) scalar('SELECT total_usado FROM cashback_saldos WHERE usuario_id=1 AND loja_id=1')*100),'expiry is not usage');
$expired=at('2026-02-20')->creditDetail($a['creditId']);
$event=array_values(array_filter($expired['events'],fn($e)=>$e['type']==='expiracao'))[0];
denied(fn()=>at('2026-02-20')->extendCredit($a['creditId'],90,'2026-03-15','Too late extension',$expired['version'],'late'),409,'expired requires restore');
denied(fn()=>at('2026-02-20')->restoreCredit($a['creditId'],$event['id'],1,'2026-03-15','Customer attempt',$expired['version'],'denied'),403,'customer cannot restore');
denied(fn()=>at('2026-02-20')->restoreCredit($a['creditId'],$event['id'],91,'2026-03-15','Inactive admin',$expired['version'],'inactive'),403,'inactive admin cannot restore');
denied(fn()=>at('2026-02-20')->creditDetail($a['creditId'],2),404,'customer scope enforced');
$restored=at('2026-02-20')->restoreCredit($a['creditId'],$event['id'],90,'2026-03-15','Individual restoration',$expired['version'],'restore-one');
eq(6000,$restored['remainingCents'],'restore expired remainder only');
eq(11000,at('2026-02-20')->settleWallet(1,1),'restored balance');
eq(10000,at('2026-02-20')->settleWallet(2,1),'other customer unchanged');
eq(true,at('2026-02-20')->restoreCredit($a['creditId'],$event['id'],90,'2026-03-15','Individual restoration',$expired['version'],'restore-one')['replayed'],'restore replay');
denied(fn()=>at('2026-02-20')->restoreCredit($a['creditId'],$event['id'],90,'2026-03-15','Another attempt',$restored['version'],'restore-two'),409,'same expiration cannot restore twice');
eq(1,at('2026-03-16')->expireDue()['processed'],'worker settles due wallet');
eq(0,at('2026-03-16')->expireDue()['expiredCents'],'repeated worker is idempotent');
eq(0,at('2026-03-16')->wallet(1,1)['availableCents'],'zero balance never resurrects purchases');
eq(2,count(at('2026-03-16')->wallet(1,1)['credits']),'history remains visible at zero');
$again=at('2026-03-16')->creditDetail($a['creditId']);
eq(2,count(array_filter($again['events'],fn($e)=>$e['type']==='expiracao')),'multiple expiration cycles');
$secondEvent=array_values(array_filter($again['events'],fn($e)=>$e['reversibleCents']>0))[0];
at('2026-03-16')->restoreCredit($a['creditId'],$secondEvent['id'],90,'2026-04-01','Second cycle restored',$again['version'],'second-cycle');

// Policy snapshots, leap day, and spend order versus perpetual funds.
$c=at('2024-01-30')->credit(4,1,2000,'Leap',sale(4,1,2000));
eq('2024-02-29',at('2024-02-01')->creditDetail($c['creditId'])['validUntil'],'leap year validity');
$db->exec('UPDATE lojas SET giftback_expiration_days=NULL WHERE id=1');
eq('2024-02-29',at('2024-02-01')->creditDetail($c['creditId'])['validUntil'],'policy change does not rewrite credit');
$perpetual=at('2024-02-01')->credit(4,1,3000,'No deadline',sale(4,1,3000));
eq(null,at('2024-02-01')->creditDetail($perpetual['creditId'])['validUntil'],'disabled policy for new grants');
at('2024-02-02')->spend(4,1,2500,'FEFO selection');
eq(2500,at('2024-02-02')->creditDetail($perpetual['creditId'])['remainingCents'],'perpetual funds used last');
$db->exec('UPDATE lojas SET giftback_expiration_days=30 WHERE id=1');
$extended=at('2026-01-01')->credit(5,1,1000,'Extension',sale(5,1,1000));
$ext=at('2026-01-02')->extendCredit($extended['creditId'],90,'2026-03-01','Extra time for customer',1,'extension');
eq('2026-03-01',$ext['validUntil'],'extension updates deadline');
eq(1000,at('2026-02-01')->settleWallet(5,1),'extension prevents original expiration');
denied(fn()=>at('2026-02-01')->extendCredit($extended['creditId'],90,'2026-04-01','Stale version',1,'stale'),409,'optimistic version conflict');

// Revoke expired origin without touching a later deposit, then forbid restore.
$old=sale(6,1,1000); $oldCredit=at('2026-01-01')->credit(6,1,1000,'Old',$old);
at('2026-02-01')->credit(6,1,2500,'Later',sale(6,1,2500));
eq(2500,at('2026-02-02')->reverseSale(6,1,$old,'Cancel expired source',90)['balanceCents'],'expired sale reversal preserves later credit');
$revoked=at('2026-02-02')->creditDetail($oldCredit['creditId']);
$oldExpiry=array_values(array_filter($revoked['events'],fn($e)=>$e['type']==='expiracao'))[0];
denied(fn()=>at('2026-02-02')->restoreCredit($oldCredit['creditId'],$oldExpiry['id'],90,'2026-03-15','Cannot restore cancellation',$revoked['version'],'cancelled'),409,'cancelled credit cannot be reactivated');

// Usage refund goes back to its own lots; an expired one immediately expires.
$g=at('2026-01-01')->credit(7,1,10000,'Grant',sale(7,1,10000));
$usedSale=sale(7,1,0);
at('2026-01-02')->spend(7,1,8000,'Spend',$usedSale);
eq(0,at('2026-02-02')->reverseSale(7,1,$usedSale,'Return after deadline',90)['balanceCents'],'refund does not silently extend deadline');
eq(10000,at('2026-02-02')->creditDetail($g['creditId'])['expiredCents'],'refunded expired portion traced');

// An already-used grant cannot be clawed back from a new grant.
$consumed=sale(8,1,3000); at('2026-01-01')->credit(8,1,3000,'Original',$consumed);
at('2026-01-02')->spend(8,1,2000,'Used');
at('2026-01-03')->credit(8,1,10000,'New',sale(8,1,10000));
denied(fn()=>at('2026-01-04')->reverseSale(8,1,$consumed,'Cannot take other funds',90),409,'consumed original requires manual review');
eq(11000,at('2026-01-04')->settleWallet(8,1),'rejected reversal is atomic');

// If audit cannot be persisted, restoring funds must roll back as well.
$auditCredit=at('2026-01-01')->credit(9,1,1000,'Audit',sale(9,1,1000));
$auditDetail=at('2026-02-01')->creditDetail($auditCredit['creditId']);
$auditEvent=array_values(array_filter($auditDetail['events'],fn($e)=>$e['type']==='expiracao'))[0];
$db->exec('RENAME TABLE admin_audit_logs TO admin_audit_logs_test_unavailable');
try { at('2026-02-01')->restoreCredit($auditCredit['creditId'],$auditEvent['id'],90,'2026-03-01','Audit failure rollback',$auditDetail['version'],'audit-fail'); throw new RuntimeException('Audit failure was swallowed'); }
catch (PDOException $expected) { echo "OK: audit failure propagated\n"; }
finally { $db->exec('RENAME TABLE admin_audit_logs_test_unavailable TO admin_audit_logs'); }
eq(0,at('2026-02-01')->settleWallet(9,1),'audit failure rolls back financial change');
eq($auditDetail['version'],at('2026-02-01')->creditDetail($auditCredit['creditId'])['version'],'audit failure preserves version');

// Outer rollback owns the complete operation, despite ledger savepoints.
$outerSale=sale(10,1,1500);
$db->beginTransaction(); at('2026-01-01')->credit(10,1,1500,'Outer',$outerSale); $db->rollBack();
eq(0,at('2026-01-01')->settleWallet(10,1),'outer transaction rollback');

// Cross-store isolation.
at('2026-01-01')->credit(11,1,1000,'Expiring',sale(11,1,1000));
at('2026-01-01')->credit(11,2,2000,'Perpetual elsewhere',sale(11,2,2000));
eq(0,at('2026-02-01')->settleWallet(11,1),'first store expired');
eq(2000,at('2026-02-01')->settleWallet(11,2),'other store preserved');

// Two independent connections contend on one wallet: exactly one can spend.
at('2026-01-15')->credit(40,1,10000,'Race',sale(40,1,10000));
$workers=[];
$db->beginTransaction(); $db->query('SELECT * FROM cashback_saldos WHERE usuario_id=40 AND loja_id=1 FOR UPDATE');
foreach (['one','two'] as $key) {
    $pipes=[]; $proc=proc_open([PHP_BINARY,__FILE__,'--race',$name,'spend',$key],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $workers[]=[$proc,$pipes];
}
usleep(200000); $db->commit();
$success=0; $rejected=0;
foreach($workers as [$proc,$pipes]) { $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($proc); if($err!==''){throw new RuntimeException($err);} eq(0,$exit,'race worker exit'); $success+=str_contains($out,'SUCCESS')?1:0; $rejected+=str_contains($out,'REJECTED:409')?1:0; }
eq(1,$success,'only one concurrent purchase succeeds'); eq(1,$rejected,'other concurrent purchase rejected');
eq(3000,at('2026-02-01')->settleWallet(40,1),'concurrent use cannot overdraw');

at('2026-01-01')->credit(12,1,10000,'New funds',sale(12,1,10000));
denied(fn()=>at('2026-01-02')->reverseSale(12,1,$oldMixed,'Legacy insufficient',90),409,'legacy reversal cannot take newer funds');
eq(11000,at('2026-01-02')->settleWallet(12,1),'legacy rejection preserves all funds');
eq(10000,at('2026-01-02')->reverseSale(13,1,$oldUsage,'Restore legacy use',90)['balanceCents'],'legacy refund may exceed opening amount');
eq(null,at('2026-01-02')->wallet(13,1)['nextExpirationDate'],'legacy refund keeps perpetual validity');
GiftbackSchema::migrate($db,true);
eq(11000,at('2026-01-02')->settleWallet(12,1),'migration rerun never snapshots newer grants twice');

// Two administrators cannot restore the same expired funds twice.
$raceCredit=at('2026-01-01')->credit(41,1,1000,'Restore race',sale(41,1,1000));
$raceDetail=at('2026-03-01')->creditDetail($raceCredit['creditId']);
$raceEvent=array_values(array_filter($raceDetail['events'],fn($e)=>$e['type']==='expiracao'))[0];
$workers=[];
foreach(['restore-a','restore-b'] as $key) {
    $pipes=[]; $proc=proc_open([PHP_BINARY,__FILE__,'--race',$name,'restore',(string)$raceCredit['creditId'],(string)$raceEvent['id'],(string)$raceDetail['version'],$key],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $workers[]=[$proc,$pipes];
}
$success=0;$rejected=0;
foreach($workers as [$proc,$pipes]) { $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);if($err!==''){throw new RuntimeException($err);}eq(0,$exit,'restore race exit');$success+=str_contains($out,'SUCCESS')?1:0;$rejected+=str_contains($out,'REJECTED:409')?1:0; }
eq(1,$success,'one concurrent restore succeeds');eq(1,$rejected,'duplicate concurrent restore rejected');
eq(1000,at('2026-03-01')->settleWallet(41,1),'concurrent restore exactly once');
GiftbackSchema::assertReconciled($db);
// Exercise the real sale and administrative services against the same isolated DB.
$beforeDryRun=(int)scalar('SELECT COUNT(*) FROM cashback_movimentacoes');
eq(true,at('2027-01-01')->expireDue(100,true)['dryRun'],'worker dry run');
eq($beforeDryRun,(int)scalar('SELECT COUNT(*) FROM cashback_movimentacoes'),'dry run does not write');
$db->exec("CREATE TRIGGER giftback_test_fail_movement BEFORE INSERT ON cashback_movimentacoes FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected ledger failure'");
try { at('2026-01-01')->credit(42,1,1000,'Must roll back',sale(42,1,1000));throw new RuntimeException('Ledger write failure swallowed'); } catch(PDOException $expected) { echo "OK: movement failure aborts credit\n"; }
$db->exec('DROP TRIGGER giftback_test_fail_movement');
eq(0,at('2026-01-01')->settleWallet(42,1),'movement failure rolls back wallet');
// A failing wallet is rolled back without stopping expiration for other customers.
at('2026-01-01')->credit(43,1,1000,'Worker failure fixture',sale(43,1,1000));
at('2026-01-01')->credit(44,1,2000,'Worker healthy fixture',sale(44,1,2000));
$db->exec("CREATE TRIGGER giftback_test_fail_expiration BEFORE INSERT ON cashback_movimentacoes FOR EACH ROW BEGIN IF NEW.usuario_id=43 AND NEW.tipo_operacao='expiracao' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected expiration failure'; END IF; END");
try {
    $batch=at('2027-01-01')->expireDue();
    eq(1,$batch['failed'],'worker reports isolated failure');
    eq([['userId'=>43,'storeId'=>1]],$batch['failedWallets'],'worker identifies failed wallet');
    eq(1000,(int)scalar('SELECT remaining_cents FROM cashback_creditos WHERE usuario_id=43 AND loja_id=1'),'failed expiration preserves credit');
    eq(1000,(int)round((float)scalar('SELECT saldo_disponivel FROM cashback_saldos WHERE usuario_id=43 AND loja_id=1')*100),'failed expiration preserves aggregate');
    eq(0,at('2027-01-01')->settleWallet(44,1),'other customer expires despite batch failure');
    eq(0,(int)scalar("SELECT COUNT(*) FROM cashback_credito_eventos e JOIN cashback_creditos c ON c.id=e.credit_id WHERE c.usuario_id=43 AND e.type='expiracao'"),'failed expiration leaves no partial event');
} finally { $db->exec('DROP TRIGGER giftback_test_fail_expiration'); }
$retried=at('2027-01-01')->expireDue();
eq(0,$retried['failed'],'worker retry succeeds');
eq(1000,$retried['expiredCents'],'worker retry expires only failed remainder');
GiftbackSchema::assertReconciled($db);
putenv('GIFTBACK_WRITES_PAUSED=1');
denied(fn()=>at('2026-01-01'),503,'cutover maintenance guard');
putenv('GIFTBACK_WRITES_PAUSED=0');
require_once __DIR__ . '/../../app/Core/RequestContext.php';
\App\Core\RequestContext::initialize();
foreach(['AdminApiException','AdminMoney','AdminAuditService','AdminIdempotencyService','AdminMutationService'] as $class) { require_once __DIR__.'/../../services/admin/'.$class.'.php'; }
foreach(['StoreApiException','StoreMoney','StoreIdempotencyService','StoreTransactionService'] as $class) { require_once __DIR__.'/../../services/store/'.$class.'.php'; }
require_once __DIR__.'/../../services/billing/BillingFeatureFlags.php';
putenv('COMMERCIAL_SALES_GATE_ENABLED=0');
$db->exec("ALTER TABLE lojas ADD razao_social VARCHAR(255) DEFAULT 'Test', ADD email VARCHAR(255) DEFAULT 'fixture@example.test', ADD telefone VARCHAR(40) DEFAULT '', ADD categoria VARCHAR(50) DEFAULT '', ADD descricao TEXT, ADD website VARCHAR(255) DEFAULT '', ADD porcentagem_cliente DECIMAL(5,2) DEFAULT 5, ADD porcentagem_admin DECIMAL(5,2) DEFAULT 0, ADD porcentagem_cashback DECIMAL(5,2) DEFAULT 5, ADD cashback_ativo INT DEFAULT 1, ADD data_config_cashback DATETIME NULL, ADD status VARCHAR(30) DEFAULT 'aprovado', ADD updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE transacoes_cashback ADD criado_por INT, ADD valor_total DECIMAL(10,2), ADD valor_cashback DECIMAL(10,2), ADD valor_admin DECIMAL(10,2), ADD valor_loja DECIMAL(10,2), ADD codigo_transacao VARCHAR(50), ADD descricao VARCHAR(500), ADD data_transacao DATETIME, ADD financial_model VARCHAR(32) DEFAULT 'subscription_cashback', ADD cashback_credited_at DATETIME NULL;
ALTER TABLE admin_audit_logs ADD ip_hash CHAR(64) NULL;
CREATE TABLE admin_idempotency_keys(id INT AUTO_INCREMENT PRIMARY KEY,actor_id INT,scope VARCHAR(80),idempotency_key VARCHAR(128),request_hash CHAR(64),status VARCHAR(20),response_json LONGTEXT,expires_at DATETIME,UNIQUE(actor_id,scope,idempotency_key)) ENGINE=InnoDB;
CREATE TABLE store_idempotency_keys(id INT AUTO_INCREMENT PRIMARY KEY,scope VARCHAR(80),loja_id INT,usuario_id INT,idempotency_key VARCHAR(128),request_hash CHAR(64),status VARCHAR(20),response_json LONGTEXT,expires_at DATETIME,updated_at DATETIME,UNIQUE(scope,loja_id,idempotency_key)) ENGINE=InnoDB;
CREATE TABLE configuracoes_saldo(id INT PRIMARY KEY,permitir_uso_saldo INT,valor_minimo_uso DECIMAL(10,2),percentual_maximo_uso INT);
INSERT INTO configuracoes_saldo VALUES(1,1,1,100);
CREATE TABLE transacoes_saldo_usado(id INT AUTO_INCREMENT PRIMARY KEY,transacao_id INT,usuario_id INT,loja_id INT,valor_usado DECIMAL(10,2));
CREATE TABLE store_event_outbox(id INT AUTO_INCREMENT PRIMARY KEY,event_type VARCHAR(80),aggregate_id INT,loja_id INT,payload_json LONGTEXT,status VARCHAR(30),UNIQUE(event_type,aggregate_id)) ENGINE=InnoDB");
$admin=new \App\Services\Admin\AdminMutationService($db,90);
eq(3,$admin->updateStore(1,['giftbackExpirationDays'=>3])['giftbackExpirationDays'],'admin configures store policy');
eq(3,$admin->updateStore(1,[])['giftbackExpirationDays'],'omitted policy preserved');
foreach([0,-1,1.5,'30'] as $invalid) { try {$admin->updateStore(1,['giftbackExpirationDays'=>$invalid]);throw new RuntimeException('Invalid policy accepted');}catch(\App\Services\Admin\AdminApiException $error){eq(422,$error->httpStatus,'invalid policy rejected');} }
foreach([1,91] as $actor) { try {(new \App\Services\Admin\AdminMutationService($db,$actor))->updateStore(1,['giftbackExpirationDays'=>1]);throw new RuntimeException('Unauthorized policy accepted');}catch(\App\Services\Admin\AdminApiException $error){eq(403,$error->httpStatus,'only active admin configures policy');} }
$sales=new \App\Services\Store\StoreTransactionService($db);
$saleInput=['customerId'=>50,'grossAmountCents'=>10000,'balanceUsedCents'=>0,'code'=>'GIFTBACK-001','occurredAt'=>'2026-01-01 12:00:00'];
$created=$sales->create(1,90,$saleInput,'test-sale-giftback-001');
eq(500,$created['customerBalanceCents'],'actual sale creates ledger credit');
eq(true,$sales->create(1,90,$saleInput,'test-sale-giftback-001')['replayed'],'actual sale is idempotent');
$liveLedger=new GiftbackLedger($db);
$liveCredit=$liveLedger->wallet(50,1)['credits'][0];
eq((new DateTimeImmutable('now',$zone))->modify('+3 days')->format('Y-m-d'),$liveCredit['validUntil'],'deadline uses actual release, not backdated purchase');
$saleInput['balanceUsedCents']=200;$saleInput['code']='GIFTBACK-002';
$created2=$sales->create(1,90,$saleInput,'test-sale-giftback-002');
eq(790,$created2['customerBalanceCents'],'actual sale consumes prior lot and adds separate credit');
$reversed=$admin->reverseCurrentTransaction($created2['id'],'Integration reversal','admin-reverse-test');
eq(200,$reversed['restoredBalanceUsedCents'],'actual admin returns consumed amounts');
eq(490,$reversed['reversedCashbackCents'],'actual admin revokes own credit');
eq(500,$liveLedger->settleWallet(50,1),'actual sale reversal reconciles');
eq(true,$admin->reverseCurrentTransaction($created2['id'],'Integration reversal','admin-reverse-test')['replayed'],'actual admin reversal replay');
try { $admin->changeGiftbackCredit($liveCredit['id'],'extend',['userId'=>51,'storeId'=>1,'expectedVersion'=>$liveCredit['version'],'validUntil'=>'2090-01-01','reason'=>'Wrong customer'],'wrong-customer');throw new RuntimeException('Wrong customer accepted'); } catch(GiftbackException $error){eq(404,$error->httpStatus,'admin action validates individual customer');}
eq(null,$admin->updateStore(1,['giftbackExpirationDays'=>null])['giftbackExpirationDays'],'admin disables only future expiry');
eq($liveCredit['validUntil'],$liveLedger->creditDetail($liveCredit['id'])['validUntil'],'disabling policy preserves old deadline');
GiftbackSchema::assertReconciled($db);
echo "PASS: ledger, migration, permissions, expiry cycles, audit rollback and concurrent use.\n";
