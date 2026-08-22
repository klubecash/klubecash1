<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\RequestContext;
use PDO;

final class AdminAuditService
{
    public function __construct(private PDO $db, private int $actorId)
    {
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    public function record(string $action, string $entityType, string|int|null $entityId, ?array $before, ?array $after, string $result = 'success'): void
    {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $stmt = $this->db->prepare(
            'INSERT INTO admin_audit_logs (actor_id,action,entity_type,entity_id,result,before_json,after_json,request_id,ip_hash) '
            . 'VALUES (:actor,:action,:type,:entity,:result,:before_json,:after_json,:request_id,:ip_hash)'
        );
        $stmt->execute([
            ':actor' => $this->actorId,
            ':action' => $action,
            ':type' => $entityType,
            ':entity' => $entityId === null ? null : (string) $entityId,
            ':result' => $result,
            ':before_json' => $before === null ? null : json_encode($this->redact($before), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':after_json' => $after === null ? null : json_encode($this->redact($after), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':request_id' => RequestContext::id(),
            ':ip_hash' => $ip === '' ? null : hash('sha256', $ip),
        ]);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function redact(array $data): array
    {
        $blocked = [
            'senha', 'password', 'senha_hash', 'token', 'csrfToken', 'csrf_token',
            'comprovante', 'pix_qr_code', 'pix_copia_cola', 'email', 'telefone',
            'phone', 'cpf', 'cnpj', 'endereco', 'address',
        ];
        foreach ($data as $key => $value) {
            if (in_array((string) $key, $blocked, true)) {
                $data[$key] = '[REDACTED]';
                continue;
            }
            if (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }
        return $data;
    }
}
