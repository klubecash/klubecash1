<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Email.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);
$limit = 25;
foreach ($argv ?? [] as $argument) {
    if (str_starts_with((string) $argument, '--limit=')) {
        $limit = max(1, min(100, (int) substr((string) $argument, 8)));
    }
}

$db = Database::getConnection();
$deliveryEnabled = !$dryRun && filter_var((string) getenv('EMAIL_DELIVERY_ENABLED'), FILTER_VALIDATE_BOOL);
$items = $db->query(
    "SELECT id,campaign_id,to_email,to_name,subject,message,attempts FROM email_queue "
    . "WHERE status='pending' AND (next_attempt_at IS NULL OR next_attempt_at<=NOW()) ORDER BY created_at,id LIMIT {$limit}"
)->fetchAll(PDO::FETCH_ASSOC);

$processed = $sent = $failed = 0;
foreach ($items as $item) {
    if (!$deliveryEnabled) {
        continue;
    }
    $claim = $db->prepare("UPDATE email_queue SET status='sending',locked_at=NOW() WHERE id=:id AND status='pending'");
    $claim->execute([':id' => $item['id']]);
    if ($claim->rowCount() !== 1) {
        continue;
    }
    $processed++;
    $ok = Email::send((string) $item['to_email'], (string) $item['subject'], (string) $item['message'], (string) ($item['to_name'] ?? ''));
    $attempts = (int) $item['attempts'] + 1;
    if ($ok) {
        $sent++;
        $db->prepare("UPDATE email_queue SET status='sent',attempts=:attempts,last_attempt=NOW(),sent_at=NOW(),locked_at=NULL WHERE id=:id")->execute([':attempts' => $attempts, ':id' => $item['id']]);
        if ($item['campaign_id']) {
            $db->prepare("UPDATE email_envios SET status='enviado',tentativas=:attempts,data_envio=NOW(),erro_mensagem=NULL WHERE campaign_id=:campaign AND email=:email")->execute([':attempts' => $attempts, ':campaign' => $item['campaign_id'], ':email' => $item['to_email']]);
        }
    } else {
        $failed++;
        $status = $attempts >= 3 ? 'failed' : 'pending';
        $delayMinutes = min(60, 5 * (2 ** max(0, $attempts - 1)));
        $db->prepare("UPDATE email_queue SET status=:status,attempts=:attempts,last_attempt=NOW(),next_attempt_at=DATE_ADD(NOW(),INTERVAL {$delayMinutes} MINUTE),locked_at=NULL,error_message='Falha do provedor' WHERE id=:id")->execute([':status' => $status, ':attempts' => $attempts, ':id' => $item['id']]);
        if ($status === 'failed' && $item['campaign_id']) {
            $db->prepare("UPDATE email_envios SET status='falhou',tentativas=:attempts,erro_mensagem='Falha do provedor' WHERE campaign_id=:campaign AND email=:email")->execute([':attempts' => $attempts, ':campaign' => $item['campaign_id'], ':email' => $item['to_email']]);
        }
    }
}

if ($deliveryEnabled) {
    $db->exec("UPDATE email_campaigns c SET emails_enviados=(SELECT COUNT(*) FROM email_envios e WHERE e.campaign_id=c.id AND e.status='enviado'),emails_falharam=(SELECT COUNT(*) FROM email_envios e WHERE e.campaign_id=c.id AND e.status IN ('falhou','bounce')) WHERE c.status IN ('agendado','enviando') AND c.requires_review=0");
    $db->exec("UPDATE email_campaigns c SET status='enviado' WHERE c.status IN ('agendado','enviando') AND c.requires_review=0 AND c.total_emails>0 AND NOT EXISTS (SELECT 1 FROM email_envios e WHERE e.campaign_id=c.id AND e.status='pendente')");
}

echo json_encode(['dryRun' => $dryRun || !$deliveryEnabled, 'available' => count($items), 'processed' => $processed, 'sent' => $sent, 'failed' => $failed], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
