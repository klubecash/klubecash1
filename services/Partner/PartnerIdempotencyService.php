<?php

declare(strict_types=1);

namespace App\Services\Partner;

use PDO;

require_once __DIR__ . '/PartnerApiException.php';

/**
 * Same contract as StoreIdempotencyService, scoped by partner key and endpoint:
 * a repeated key with the same body replays the stored answer, a different body
 * is a 409. Kept for 30 days so a partner outbox can retry for a long time.
 */
final class PartnerIdempotencyService
{
    public function __construct(private PDO $db)
    {
    }

    public static function validKey(string $key): bool
    {
        return strlen($key) >= 16 && strlen($key) <= 128 && preg_match('/^[A-Za-z0-9._:-]+$/', $key) === 1;
    }

    /** @return array{replayed:bool,status?:int,data?:array<string,mixed>} */
    public function begin(int $keyId, string $endpoint, string $key, array $payload): array
    {
        if (!self::validKey($key)) {
            throw new PartnerApiException('Envie o cabeçalho Idempotency-Key com 16 a 128 caracteres [A-Za-z0-9._:-].', 400, 'idempotency_key_required');
        }
        $hash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        try {
            $this->db->prepare("INSERT INTO partner_idempotency_keys(api_key_id,endpoint,idempotency_key,request_hash,status,created_at,updated_at,expires_at) VALUES(?,?,?,?,'processing',NOW(),NOW(),DATE_ADD(NOW(),INTERVAL 30 DAY))")
                ->execute([$keyId, $endpoint, $key, $hash]);
            return ['replayed' => false];
        } catch (\PDOException $exception) {
            if ((string) $exception->getCode() !== '23000') { throw $exception; }
        }
        $stmt = $this->db->prepare('SELECT request_hash,status,http_status,response_json,expires_at FROM partner_idempotency_keys WHERE api_key_id=? AND endpoint=? AND idempotency_key=? LIMIT 1');
        $stmt->execute([$keyId, $endpoint, $key]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing || !hash_equals((string) $existing['request_hash'], $hash)) {
            throw new PartnerApiException('Esta Idempotency-Key já foi usada com outro corpo.', 409, 'idempotency_conflict');
        }
        if ($existing['status'] === 'failed' || strtotime((string) $existing['expires_at']) < time()) {
            $this->db->prepare("UPDATE partner_idempotency_keys SET status='processing',http_status=NULL,response_json=NULL,updated_at=NOW(),expires_at=DATE_ADD(NOW(),INTERVAL 30 DAY) WHERE api_key_id=? AND endpoint=? AND idempotency_key=?")
                ->execute([$keyId, $endpoint, $key]);
            return ['replayed' => false];
        }
        if ($existing['status'] !== 'completed' || empty($existing['response_json'])) {
            throw new PartnerApiException('Esta operação ainda está em processamento.', 409, 'in_progress');
        }
        $data = json_decode((string) $existing['response_json'], true);
        return ['replayed' => true, 'status' => (int) $existing['http_status'], 'data' => is_array($data) ? $data : []];
    }

    public function complete(int $keyId, string $endpoint, string $key, int $status, array $response): void
    {
        $this->db->prepare("UPDATE partner_idempotency_keys SET status='completed',http_status=?,response_json=?,updated_at=NOW() WHERE api_key_id=? AND endpoint=? AND idempotency_key=?")
            ->execute([$status, json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $keyId, $endpoint, $key]);
    }

    public function fail(int $keyId, string $endpoint, string $key): void
    {
        $this->db->prepare("UPDATE partner_idempotency_keys SET status='failed',updated_at=NOW() WHERE api_key_id=? AND endpoint=? AND idempotency_key=?")
            ->execute([$keyId, $endpoint, $key]);
    }
}
