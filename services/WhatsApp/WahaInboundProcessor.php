<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Core\Logger;
use PDO;
use Throwable;

final class WahaInboundProcessor
{
    private WhatsAppMenuConfig $menuConfig;
    private ?WahaService $waha;

    public function __construct(private PDO $db, ?WahaService $waha = null, ?WhatsAppMenuConfig $menuConfig = null)
    {
        $this->menuConfig = $menuConfig ?? WhatsAppMenuConfig::fromEnvironment();
        $this->waha = $waha;
    }

    /** @return array{available:int,processed:int,matched:int,failed:int} */
    public function processPending(int $limit = 50, ?int $preferredEventId = null): array
    {
        $limit = max(1, min(100, $limit));
        $preferredEventId = max(0, (int) $preferredEventId);
        $events = $this->db->query(
            "SELECT id,request_id,event_type,payload_json,attempts FROM waha_webhook_events
             WHERE status='pending' AND available_at<=NOW()
             ORDER BY CASE WHEN id={$preferredEventId} THEN 0 WHEN event_type='message' THEN 1 ELSE 2 END,id DESC
             LIMIT {$limit}"
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
                $userId = null;
                if ($item['event_type'] === 'message') {
                    if ($this->menuConfig->menuEnabled) {
                        $waha = $this->waha ??= new WahaService(
                            WahaConfig::fromEnvironment(),
                            new CurlWahaHttpClient()
                        );
                        $userId = (new WhatsAppMenuService($this->db, $waha, $this->menuConfig))->process(
                            (int) $item['id'],
                            $event,
                            (string) ($item['request_id'] ?? '')
                        );
                    } else {
                        $userId = $this->matchUser($event);
                    }
                }
                $done = $this->db->prepare(
                    "UPDATE waha_webhook_events
                     SET status='processed',associated_user_id=:user_id,payload_json=:payload,processed_at=NOW() WHERE id=:id"
                );
                $done->bindValue(':user_id', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $done->bindValue(':payload', $this->sanitizedPayload($event));
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

        if ($this->menuConfig->menuEnabled) {
            (new WhatsAppMenuStore($this->db, $this->menuConfig))->purge();
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
             WHERE tipo='cliente' AND status='ativo' AND telefone IS NOT NULL AND telefone<>''
               AND REGEXP_REPLACE(telefone,'[^0-9]','') IN (:full,:national)
             ORDER BY id LIMIT 2"
        );
        $statement->execute([':full' => $full, ':national' => $national]);
        $ids = $statement->fetchAll(PDO::FETCH_COLUMN);
        return count($ids) === 1 ? (int) $ids[0] : null;
    }

    /** @param array<string,mixed> $event */
    private function sanitizedPayload(array $event): string
    {
        $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
        $minimal = [
            'event' => $event['event'] ?? null,
            'session' => $event['session'] ?? null,
            'payload' => [
                'id' => is_scalar($payload['id'] ?? null) ? (string) $payload['id'] : null,
                'fromMe' => ($payload['fromMe'] ?? $payload['key']['fromMe'] ?? false) === true,
                'source' => is_scalar($payload['source'] ?? null) ? (string) $payload['source'] : null,
                'type' => is_scalar($payload['type'] ?? null) ? (string) $payload['type'] : null,
                'body' => '[redacted]',
            ],
        ];
        return json_encode($minimal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
