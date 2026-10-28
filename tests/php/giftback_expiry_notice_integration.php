<?php

declare(strict_types=1);

// Nunca carrega .env nem o bootstrap: cria um banco vazio em um MySQL descartavel em loopback.
//   GIFTBACK_TEST_DSN=mysql:host=127.0.0.1;port=33367 php tests/php/giftback_expiry_notice_integration.php
require_once __DIR__ . '/../../services/Giftback/GiftbackExpiryNoticeService.php';
require_once __DIR__ . '/../../services/WhatsApp/WahaConfig.php';
require_once __DIR__ . '/../../services/WhatsApp/WahaException.php';
require_once __DIR__ . '/../../services/WhatsApp/WahaHttpClient.php';
require_once __DIR__ . '/../../services/WhatsApp/WahaService.php';

use App\Services\Giftback\GiftbackExpiryNoticeService as Notice;
use App\Services\WhatsApp\WahaConfig;
use App\Services\WhatsApp\WahaException;
use App\Services\WhatsApp\WahaHttpClient;
use App\Services\WhatsApp\WahaService;

$dsn = (string) getenv('GIFTBACK_TEST_DSN');
if (!preg_match('/^mysql:host=127\.0\.0\.1;port=\d+$/D', $dsn)) {
    fwrite(STDERR, "Required: GIFTBACK_TEST_DSN=mysql:host=127.0.0.1;port=<isolated test server port>\n");
    exit(2);
}
putenv('GIFTBACK_NOTICES_ENABLED=true');

final class NoticeFakeHttp implements WahaHttpClient
{
    public array $requests = [];
    public array $responses = [];
    public function request(string $method, string $url, array $headers, ?string $body, int $timeoutSeconds): array
    {
        $this->requests[] = compact('method', 'url', 'body');
        $next = array_shift($this->responses);
        if ($next instanceof Throwable) { throw $next; }
        return $next ?? ['status' => 200, 'body' => '{"id":"msg-default"}'];
    }
    /** Resposta de check-exists seguida da resposta do envio. */
    public function queueSend(Throwable|array|null $send = null): void
    {
        $this->responses[] = ['status' => 200, 'body' => '{"numberExists":true,"chatId":"5511999999999@c.us"}'];
        $this->responses[] = $send ?? ['status' => 200, 'body' => '{"id":"msg-' . (count($this->requests) + 1) . '"}'];
    }
}

function expect(mixed $expected, mixed $actual, string $name): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($name . ': esperado ' . json_encode($expected) . ', recebido ' . json_encode($actual));
    }
}

$db = new PDO($dsn, getenv('GIFTBACK_TEST_USER') ?: 'root', getenv('GIFTBACK_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$name = 'giftback_notice_' . bin2hex(random_bytes(6));
$db->exec('CREATE DATABASE ' . $name . ' CHARACTER SET utf8mb4');
$db->exec('USE ' . $name);
$db->exec('CREATE TABLE usuarios(id INT PRIMARY KEY, nome VARCHAR(100), telefone VARCHAR(30)) ENGINE=InnoDB');
$db->exec('CREATE TABLE lojas(id INT PRIMARY KEY, nome_fantasia VARCHAR(100)) ENGINE=InnoDB');
$db->exec('CREATE TABLE cashback_creditos (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, usuario_id INT NOT NULL, loja_id INT NOT NULL,
    remaining_cents BIGINT NOT NULL, credited_at DATETIME NOT NULL, expires_at DATETIME NULL, policy_days INT NULL) ENGINE=InnoDB');
$db->exec("INSERT INTO usuarios VALUES(1,'Ana Souza','(11) 99999-9999'),(2,'Sem Telefone','')");
$db->exec("INSERT INTO lojas VALUES(1,'Loja Um'),(2,'Loja Dois')");
// 1: 40 dias, vence 27/11 (ultimo dia 26/11). 2: 14 dias, vence 29/10. 3: sem telefone. 4: sem validade.
$db->exec("INSERT INTO cashback_creditos(usuario_id,loja_id,remaining_cents,credited_at,expires_at,policy_days) VALUES
    (1,1,1250,'2026-10-18 09:00:00','2026-11-27 00:00:00',40),
    (1,2,500,'2026-10-15 09:00:00','2026-10-29 00:00:00',14),
    (2,1,900,'2026-10-18 09:00:00','2026-11-27 00:00:00',40),
    (1,1,777,'2026-10-01 09:00:00',NULL,NULL)");

// A migration cria a tabela uma vez e nao falha ao repetir.
expect(false, Notice::migrate($db, false)['existed'], 'simulacao nao cria');
expect(true, Notice::migrate($db, true)['created'], 'primeira aplicacao');
expect(false, Notice::migrate($db, true)['created'], 'segunda aplicacao');

$zone = new DateTimeZone('America/Sao_Paulo');
$http = new NoticeFakeHttp();
$waha = new WahaService(new WahaConfig('https://waha.example.test', 'test-api-key', 'klubecash', 'test-hmac'), $http);
$run = fn (string $when, bool $dry = false): array => (new Notice($db, $waha, new DateTimeImmutable($when, $zone)))->run(30, $dry);
$count = fn (string $where): int => (int) $db->query("SELECT COUNT(*) FROM cashback_credito_avisos WHERE $where")->fetchColumn();

// Desligado por padrao.
putenv('GIFTBACK_NOTICES_ENABLED=false');
expect(['enabled' => false], $run('2026-10-22 11:00:00'), 'desligado');
putenv('GIFTBACK_NOTICES_ENABLED=true');

// Simulacao nao grava nada.
$dry = $run('2026-10-22 11:00:00', true);
expect(3, $dry['candidates'], 'dry-run candidatos');
expect(0, $count('1=1'), 'dry-run nao grava');

// 22/10 11:00: credito 2 (vence 29/10) tem o marco de 7 dias atrasado e sai agora; o de 1 dia fica futuro.
$http->queueSend();
$result = $run('2026-10-22 11:00:00');
expect(3, $result['candidates'], 'candidatos');
expect(1, $result['sent'], 'um envio');
expect(1, $count("status='sent'"), 'um aviso enviado');
$body = (string) end($http->requests)['body'];
expect(true, str_contains($body, 'Loja Dois') && str_contains($body, 'R$ 5,00') && str_contains($body, 'vence em *6 dias*'), 'texto do aviso: ' . $body);
expect(true, str_contains($body, '28/10/2026'), 'ultimo dia inclusivo');
expect(0, $count("credit_id=4"), 'credito sem validade nunca recebe aviso');

// Repetir a rodada nao duplica agenda nem envio.
$before = count($http->requests);
$again = $run('2026-10-22 11:30:00');
expect(0, $again['scheduled'], 'agenda idempotente');
expect(0, $again['sent'], 'sem reenvio');
expect($before, count($http->requests), 'nenhuma chamada nova ao WhatsApp');

// Fora da janela (07:00) nada e enviado, mesmo com aviso vencido.
expect(true, $run('2026-11-06 07:00:00')['outsideWindow'], 'fora da janela');
expect(0, $count("status='sent' AND credit_id=1"), 'nada enviado fora da janela');

// 06/11 10:30: o credito 2 ja venceu (29/10) e o de 1 dia nunca e enviado; o credito 1 envia o marco de 20 dias.
$http->queueSend();
$result = $run('2026-11-06 10:30:00');
expect(1, $result['sent'], 'envio do marco de 20 dias');
expect(1, $count("credit_id=2 AND marco_dias=1 AND status='skipped'"), 'credito vencido e descartado');
expect(1, $count("credit_id=1 AND marco_dias=20 AND status='sent'"), 'marco 20 enviado');

// Credito sem telefone e descartado sem travar a fila.
expect(true, $count("credit_id=3 AND status='skipped'") + $count("credit_id=3 AND status='pending'") >= 1, 'credito 3 agendado');

// Prorrogacao: o aviso antigo e descartado e uma agenda nova nasce para a nova validade.
$db->exec("UPDATE cashback_creditos SET expires_at='2026-12-10 00:00:00',policy_days=40 WHERE id=1");
$run('2026-11-12 10:30:00');
expect(1, $count("credit_id=1 AND marco_dias=14 AND expires_at='2026-11-27 00:00:00' AND status='skipped'"), 'aviso da validade antiga descartado');
expect(true, $count("credit_id=1 AND expires_at='2026-12-10 00:00:00'") > 0, 'nova agenda para a nova validade');

// Envio de resultado incerto nunca e repetido.
$http->queueSend(new WahaException('timeout', 503, true, true));
$result = $run('2026-12-01 10:30:00');
expect(1, $count("credit_id=1 AND status='delivery_unknown'") >= 1 ? 1 : 0, 'resultado incerto registrado');
$calls = count($http->requests);
$run('2026-12-01 11:00:00');
expect($calls, count($http->requests), 'delivery_unknown nao reenvia');

$db->exec('DROP DATABASE ' . $name);
echo "giftback_expiry_notice_integration: ok\n";
