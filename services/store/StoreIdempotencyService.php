<?php

declare(strict_types=1);

namespace App\Services\Store;

use PDO;

final class StoreIdempotencyService
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array{replayed: bool, data?: array<string, mixed>} */
    public function begin(string $scope, int $storeId, int $userId, string $key, array $payload): array
    {
        $key = trim($key);
        if (strlen($key) < 16 || strlen($key) > 128 || preg_match('/^[A-Za-z0-9._:-]+$/', $key) !== 1) {
            throw new StoreApiException('Chave de idempotência inválida.', 422);
        }

        $hash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        try {
            $statement = $this->db->prepare(
                'INSERT INTO store_idempotency_keys '
                . '(scope, loja_id, usuario_id, idempotency_key, request_hash, status, expires_at) '
                . "VALUES (:scope, :store_id, :user_id, :key, :hash, 'processing', DATE_ADD(NOW(), INTERVAL 24 HOUR))"
            );
            $statement->execute([
                ':scope' => $scope,
                ':store_id' => $storeId,
                ':user_id' => $userId,
                ':key' => $key,
                ':hash' => $hash,
            ]);
            return ['replayed' => false];
        } catch (\PDOException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }
        }

        $statement = $this->db->prepare(
            'SELECT request_hash, status, response_json, expires_at FROM store_idempotency_keys '
            . 'WHERE scope=:scope AND loja_id=:store_id AND idempotency_key=:key LIMIT 1'
        );
        $statement->execute([':scope' => $scope, ':store_id' => $storeId, ':key' => $key]);
        $existing = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$existing || !hash_equals((string) $existing['request_hash'], $hash)) {
            throw new StoreApiException('A chave de idempotência já foi usada com dados diferentes.', 409);
        }
        if ($existing['status'] === 'failed' || strtotime((string) $existing['expires_at']) < time()) {
            $retry = $this->db->prepare(
                "UPDATE store_idempotency_keys SET status='processing',response_json=NULL,"
                . 'expires_at=DATE_ADD(NOW(),INTERVAL 24 HOUR),updated_at=NOW() '
                . 'WHERE scope=:scope AND loja_id=:store_id AND idempotency_key=:key'
            );
            $retry->execute([':scope' => $scope, ':store_id' => $storeId, ':key' => $key]);
            return ['replayed' => false];
        }
        if ($existing['status'] !== 'completed' || empty($existing['response_json'])) {
            throw new StoreApiException('Esta operação já está sendo processada.', 409);
        }

        $data = json_decode((string) $existing['response_json'], true);
        return ['replayed' => true, 'data' => is_array($data) ? $data : []];
    }

    /** @param array<string, mixed> $response */
    public function complete(string $scope, int $storeId, string $key, array $response): void
    {
        $statement = $this->db->prepare(
            "UPDATE store_idempotency_keys SET status='completed', response_json=:response, updated_at=NOW() "
            . 'WHERE scope=:scope AND loja_id=:store_id AND idempotency_key=:key'
        );
        $statement->execute([
            ':response' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':scope' => $scope,
            ':store_id' => $storeId,
            ':key' => $key,
        ]);
    }

    public function fail(string $scope, int $storeId, string $key): void
    {
        $statement = $this->db->prepare(
            "UPDATE store_idempotency_keys SET status='failed', updated_at=NOW() "
            . 'WHERE scope=:scope AND loja_id=:store_id AND idempotency_key=:key'
        );
        $statement->execute([':scope' => $scope, ':store_id' => $storeId, ':key' => $key]);
    }
}
