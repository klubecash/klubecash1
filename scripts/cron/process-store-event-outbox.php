<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap/app.php';

$requested = in_array('--process', $argv ?? [], true);
$enabled = filter_var((string) getenv('STORE_OUTBOX_PROCESSING_ENABLED'), FILTER_VALIDATE_BOOL);
$limit = 25;
foreach ($argv ?? [] as $argument) {
    if (str_starts_with((string) $argument, '--limit=')) {
        $limit = max(1, min(100, (int) substr((string) $argument, 8)));
    }
}

$db = Database::getConnection();
$items = $db->query(
    "SELECT id,event_type,aggregate_id,loja_id,attempts FROM store_event_outbox "
    . "WHERE status='pending' AND available_at<=NOW() ORDER BY created_at,id LIMIT {$limit}"
)->fetchAll(PDO::FETCH_ASSOC);

$processed = $queued = $failed = 0;
if ($requested && $enabled) {
    foreach ($items as $item) {
        try {
            $db->beginTransaction();
            $claim = $db->prepare("UPDATE store_event_outbox SET status='processing' WHERE id=:id AND status='pending'");
            $claim->execute([':id' => $item['id']]);
            if ($claim->rowCount() !== 1) { $db->rollBack(); continue; }
            $processed++;

            if ($item['event_type'] !== 'cashback.sale.approved') {
                throw new RuntimeException('Tipo de evento não suportado.');
            }
            $notification = $db->query('SELECT email_nova_transacao FROM configuracoes_notificacao ORDER BY id DESC LIMIT 1')->fetchColumn();
            if ((int) $notification !== 1) {
                $db->prepare("UPDATE store_event_outbox SET status='sent',processed_at=NOW() WHERE id=:id")->execute([':id' => $item['id']]);
                $db->commit();
                continue;
            }
            $details = $db->prepare(
                'SELECT t.codigo_transacao,t.valor_total,t.valor_cliente,u.id customer_id,u.nome customer_name,u.email customer_email,l.nome_fantasia store_name '
                . 'FROM transacoes_cashback t JOIN usuarios u ON u.id=t.usuario_id JOIN lojas l ON l.id=t.loja_id '
                . 'WHERE t.id=:transaction AND t.loja_id=:store LIMIT 1'
            );
            $details->execute([':transaction' => $item['aggregate_id'], ':store' => $item['loja_id']]);
            $row = $details->fetch(PDO::FETCH_ASSOC);
            if (!$row || !filter_var((string) $row['customer_email'], FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Destinatário da venda indisponível.');
            }
            $safeName = htmlspecialchars((string) $row['customer_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeStore = htmlspecialchars((string) $row['store_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeCode = htmlspecialchars((string) $row['codigo_transacao'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $purchase = number_format((float) $row['valor_total'], 2, ',', '.');
            $cashback = number_format((float) $row['valor_cliente'], 2, ',', '.');
            $message = "<h2>Olá, {$safeName}!</h2><p>Sua compra na {$safeStore} foi aprovada.</p>"
                . "<p>Código: <strong>{$safeCode}</strong><br>Compra: R$ {$purchase}<br>Cashback: R$ {$cashback}</p>";
            $queue = $db->prepare(
                "INSERT INTO email_queue (campaign_id,recipient_id,to_email,to_name,subject,message,status,attempts,next_attempt_at) "
                . "VALUES (NULL,:recipient,:email,:name,'Cashback aprovado - Klube Cash',:message,'pending',0,NOW())"
            );
            $queue->execute([':recipient' => $row['customer_id'], ':email' => $row['customer_email'], ':name' => $row['customer_name'], ':message' => $message]);
            $db->prepare("UPDATE store_event_outbox SET status='sent',attempts=attempts+1,processed_at=NOW() WHERE id=:id")->execute([':id' => $item['id']]);
            $db->commit();
            $queued++;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) { $db->rollBack(); }
            $attempts = (int) $item['attempts'] + 1;
            $status = $attempts >= 3 ? 'failed' : 'pending';
            $delay = min(60, 5 * (2 ** max(0, $attempts - 1)));
            $failure = $db->prepare("UPDATE store_event_outbox SET status=:status,attempts=:attempts,available_at=DATE_ADD(NOW(),INTERVAL {$delay} MINUTE) WHERE id=:id");
            $failure->execute([':status' => $status, ':attempts' => $attempts, ':id' => $item['id']]);
            $failed++;
        }
    }
}

echo json_encode([
    'dryRun' => !$requested || !$enabled,
    'available' => count($items),
    'processed' => $processed,
    'queued' => $queued,
    'failed' => $failed,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
