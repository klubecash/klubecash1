<?php

declare(strict_types=1);

namespace App\Services\Partner;

use PDO;
use Throwable;

/** One row per partner call: ids and amounts only, never the body, names, phones or the raw IP. */
final class PartnerRequestLog
{
    public static function write(PDO $db, array $entry, string $pepper): void
    {
        try {
            $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
            $db->prepare('INSERT INTO pass_integration_log(request_id,api_key_id,endpoint,method,http_status,error_code,idempotency_hash,usuario_id,loja_id,amount_cents,items,pass_ref,replayed,ip_hash,duration_ms,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())')
                ->execute([
                    substr((string) ($entry['requestId'] ?? ''), 0, 64), $entry['keyId'] ?? null, substr((string) ($entry['endpoint'] ?? ''), 0, 60),
                    substr((string) ($entry['method'] ?? ''), 0, 8), (int) ($entry['status'] ?? 0), $entry['errorCode'] ?? null,
                    isset($entry['idempotencyKey']) && $entry['idempotencyKey'] !== '' ? hash('sha256', (string) $entry['idempotencyKey']) : null,
                    $entry['userId'] ?? null, $entry['storeId'] ?? null, $entry['amountCents'] ?? null, $entry['items'] ?? null,
                    $entry['passRef'] ?? null, !empty($entry['replayed']) ? 1 : 0,
                    $ip !== '' && $pepper !== '' ? hash_hmac('sha256', $ip, $pepper) : null, $entry['durationMs'] ?? null,
                ]);
        } catch (Throwable $error) {
            // Logging never changes the answer the partner receives.
            error_log('partner.log.failed ' . get_class($error));
        }
    }
}
