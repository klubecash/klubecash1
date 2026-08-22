<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/bootstrap/app.php';
use App\Core\Logger;
use App\Services\WhatsApp\WahaService;
$db = Database::getConnection();
$events = $db->query("SELECT id,event_type,payload_json,attempts FROM waha_webhook_events WHERE status='pending' AND available_at<=NOW() ORDER BY id LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
foreach ($events as $item) {
    $claim = $db->prepare("UPDATE waha_webhook_events SET status='processing',attempts=attempts+1 WHERE id=:id AND status='pending'");
    $claim->execute([':id' => $item['id']]);
    if ($claim->rowCount() !== 1) continue;
    try {
        $event = json_decode((string) $item['payload_json'], true, 512, JSON_THROW_ON_ERROR);
        $userId = null;
        if ($item['event_type'] === 'message' && ($event['payload']['fromMe'] ?? false) !== true) {
            $sender = preg_replace('/@.+$/', '', (string) ($event['payload']['from'] ?? '')) ?? '';
            $users = $db->query("SELECT id,telefone FROM usuarios WHERE telefone IS NOT NULL AND telefone<>'' AND status='ativo'")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($users as $user) {
                try {
                    if (WahaService::normalizePhone((string) $user['telefone']) === $sender . '@c.us') { $userId = (int) $user['id']; break; }
                } catch (InvalidArgumentException) { continue; }
            }
        }
        $done = $db->prepare("UPDATE waha_webhook_events SET status='processed',associated_user_id=:user_id,processed_at=NOW() WHERE id=:id");
        $done->bindValue(':user_id', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $done->bindValue(':id', (int) $item['id'], PDO::PARAM_INT);
        $done->execute();
        Logger::info('waha.webhook.processed', ['event_type' => $item['event_type'], 'matched' => $userId !== null]);
    } catch (Throwable $exception) {
        $attempts = (int) $item['attempts'] + 1;
        $status = $attempts >= 5 ? 'failed' : 'pending';
        $delay = min(60, 2 ** $attempts);
        $failure = $db->prepare("UPDATE waha_webhook_events SET status=:status,available_at=DATE_ADD(NOW(),INTERVAL {$delay} MINUTE) WHERE id=:id");
        $failure->execute([':status' => $status, ':id' => $item['id']]);
        Logger::warning('waha.webhook.processing_failed', ['event_type' => $item['event_type'], 'exception' => get_class($exception)]);
    }
}
echo json_encode(['processed' => count($events)], JSON_UNESCAPED_SLASHES) . PHP_EOL;
