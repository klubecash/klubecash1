<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Core\Logger;
use PDO;
use Throwable;

final class WahaInboundProcessor
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array{available:int,processed:int,matched:int,failed:int} */
    public function processPending(int $limit = 50): array
    {
        (new WahaSchemaManager($this->db))->migrate();
        $limit = max(1, min(100, $limit));
        $events = $this->db->query(
            "SELECT id,event_type,payload_json,attempts FROM waha_webhook_events
             WHERE status='pending' AND available_at<=NOW() ORDER BY id LIMIT {$limit}"
        )->fetchAll(PDO::FETCH_ASSOC);
        $stats = ['available' => count($events), 'processed' => 0, 'matched' => 0, 'failed' => 0];

        foreach ($events as $item) {
            $claim = $this->db->prepare(
                "UPDATE waha_webhook_events SET status='processing',attempts=attempts+1
                 WHERE id=:id AND status='pending'"
            );
            $claim->execute([':id' => $item['id']]);
            if ($claim->rowCount() !== 1) {
                continue;
            }

            try {
                $event = json_decode((string) $item['payload_json'], true, 512, JSON_THROW_ON_ERROR);
                $userId = $item['event_type'] === 'message' ? $this->matchUser($event) : null;
                $done = $this->db->prepare(
                    "UPDATE waha_webhook_events
                     SET status='processed',associated_user_id=:user_id,processed_at=NOW() WHERE id=:id"
                );
                $done->bindValue(':user_id', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $done->bindValue(':id', (int) $item['id'], PDO::PARAM_INT);
                $done->execute();
                $stats['processed']++;
                if ($userId !== null) {
                    $stats['matched']++;
                }
            } catch (Throwable $exception) {
                $attempts = (int) $item['attempts'] + 1;
                $status = $attempts >= 5 ? 'failed' : 'pending';
                $delay = min(60, 2 ** $attempts);
                $failure = $this->db->prepare(
                    "UPDATE waha_webhook_events
                     SET status=:status,available_at=DATE_ADD(NOW(),INTERVAL {$delay} MINUTE) WHERE id=:id"
                );
                $failure->execute([':status' => $status, ':id' => $item['id']]);
                $stats['failed']++;
                Logger::warning('waha.webhook.processing_failed', [
                    'event_type' => $item['event_type'],
                    'exception' => get_class($exception),
                ]);
            }
        }

        return $stats;
    }

    /** @param array<string,mixed> $event */
    private function matchUser(array $event): ?int
    {
        $fromMe = ($event['payload']['fromMe'] ?? $event['payload']['key']['fromMe'] ?? false) === true;
        if ($fromMe) {
            return null;
        }

        $sender = (string) ($event['payload']['from'] ?? $event['payload']['key']['remoteJid'] ?? '');
        if (!str_ends_with($sender, '@c.us')) {
            return null;
        }
        $full = preg_replace('/\D+/', '', preg_replace('/@.+$/', '', $sender) ?? '') ?? '';
        if ($full === '') {
            return null;
        }
        $national = str_starts_with($full, '55') ? substr($full, 2) : $full;
        $statement = $this->db->prepare(
            "SELECT id FROM usuarios
             WHERE status='ativo' AND telefone IS NOT NULL AND telefone<>''
               AND REGEXP_REPLACE(telefone,'[^0-9]','') IN (:full,:national)
             LIMIT 1"
        );
        $statement->execute([':full' => $full, ':national' => $national]);
        $id = (int) ($statement->fetchColumn() ?: 0);
        return $id > 0 ? $id : null;
    }
}
