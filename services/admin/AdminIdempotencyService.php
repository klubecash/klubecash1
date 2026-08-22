<?php

declare(strict_types=1);

namespace App\Services\Admin;

use PDO;

final class AdminIdempotencyService
{
    public function __construct(private PDO $db, private int $actorId)
    {
    }

    /** @param array<string, mixed> $payload @return array{replayed:bool,data?:array<string,mixed>} */
    public function begin(string $scope, string $key, array $payload): array
    {
        $key = trim($key);
        if ($key === '' || strlen($key) > 128) {
            throw new AdminApiException('Informe uma chave de idempotência válida.', 400);
        }
        $hash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO admin_idempotency_keys (actor_id,scope,idempotency_key,request_hash,status,expires_at) "
                . "VALUES (:actor,:scope,:key,:hash,'processing',DATE_ADD(NOW(),INTERVAL 24 HOUR))"
            );
            $stmt->execute([':actor' => $this->actorId, ':scope' => $scope, ':key' => $key, ':hash' => $hash]);
            return ['replayed' => false];
        } catch (\PDOException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }
            $stmt = $this->db->prepare('SELECT request_hash,status,response_json FROM admin_idempotency_keys WHERE actor_id=:actor AND scope=:scope AND idempotency_key=:key LIMIT 1');
            $stmt->execute([':actor' => $this->actorId, ':scope' => $scope, ':key' => $key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || !hash_equals((string) $row['request_hash'], $hash)) {
                throw new AdminApiException('Esta chave de idempotência já foi usada com dados diferentes.', 409);
            }
            if ($row['status'] === 'completed') {
                return ['replayed' => true, 'data' => (array) json_decode((string) $row['response_json'], true)];
            }
            if ($row['status'] === 'failed') {
                $retry = $this->db->prepare(
                    "UPDATE admin_idempotency_keys SET status='processing',response_json=NULL,expires_at=DATE_ADD(NOW(),INTERVAL 24 HOUR) "
                    . "WHERE actor_id=:actor AND scope=:scope AND idempotency_key=:key AND status='failed'"
                );
                $retry->execute([':actor' => $this->actorId, ':scope' => $scope, ':key' => $key]);
                if ($retry->rowCount() === 1) {
                    return ['replayed' => false];
                }
            }
            throw new AdminApiException('A operação já está sendo processada.', 409);
        }
    }

    /** @param array<string, mixed> $response */
    public function complete(string $scope, string $key, array $response): void
    {
        $stmt = $this->db->prepare("UPDATE admin_idempotency_keys SET status='completed',response_json=:response WHERE actor_id=:actor AND scope=:scope AND idempotency_key=:key");
        $stmt->execute([':response' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ':actor' => $this->actorId, ':scope' => $scope, ':key' => $key]);
    }

    public function fail(string $scope, string $key): void
    {
        $stmt = $this->db->prepare("UPDATE admin_idempotency_keys SET status='failed' WHERE actor_id=:actor AND scope=:scope AND idempotency_key=:key");
        $stmt->execute([':actor' => $this->actorId, ':scope' => $scope, ':key' => $key]);
    }
}
