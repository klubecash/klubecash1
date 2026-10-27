<?php

declare(strict_types=1);

namespace App\Services\Partner;

use PDO;

require_once __DIR__ . '/PartnerApiException.php';

/**
 * Per-partner API keys: kcp_<public id>_<secret>. Only an HMAC of the secret is
 * stored; permissions are an exact allow-list and a wildcard is never honoured.
 */
final class PartnerKeyService
{
    public const PERMISSIONS = ['pass:balance', 'pass:debit', 'pass:credit', 'pass:reverse'];

    public function __construct(private PDO $db, private string $pepper)
    {
        if (strlen($pepper) < 32) { throw new PartnerApiException('Chaves de parceiro não configuradas neste ambiente.', 503, 'not_configured'); }
    }

    public static function fromEnvironment(PDO $db): self
    {
        require_once __DIR__ . '/../Security/VpsRuntimeKeys.php';
        return new self($db, \App\Services\Security\VpsRuntimeKeys::partnerApiPepper());
    }

    /** @return array{publicId:string,secret:string}|null */
    public static function parse(string $authorization): ?array
    {
        if (preg_match('/^Bearer kcp_([a-f0-9]{16})_([A-Za-z0-9_-]{43})$/D', trim($authorization), $match) !== 1) { return null; }
        return ['publicId' => $match[1], 'secret' => $match[2]];
    }

    public function hash(string $secret): string
    {
        return hash_hmac('sha256', $secret, $this->pepper);
    }

    /** @param list<string> $permissions */
    public static function normalizePermissions(array $permissions): array
    {
        $clean = array_values(array_unique(array_map(static fn ($value): string => trim((string) $value), $permissions)));
        sort($clean);
        if ($clean === [] || array_diff($clean, self::PERMISSIONS) !== []) {
            throw new PartnerApiException('Permissões válidas: ' . implode(', ', self::PERMISSIONS) . '.', 422, 'invalid_permissions');
        }
        return $clean;
    }

    /**
     * @param list<string> $permissions
     * @return array{id:int,publicId:string,key:string}
     */
    public function issue(string $partnerName, array $permissions, int $dailyRequests, int $dailyDebitCents, int $dailyCreditCents, ?string $expiresAt, ?string $createdBy): array
    {
        $partnerName = trim($partnerName);
        if ($partnerName === '' || iconv_strlen($partnerName, 'UTF-8') > 120) { throw new PartnerApiException('Nome do parceiro obrigatório (até 120 caracteres).'); }
        if ($dailyRequests < 1 || $dailyDebitCents < 0 || $dailyCreditCents < 0) { throw new PartnerApiException('Limites diários inválidos.'); }
        $publicId = bin2hex(random_bytes(8));
        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->db->prepare('INSERT INTO partner_api_keys(public_id,secret_hash,partner_name,permissions,daily_request_limit,daily_debit_limit_cents,daily_credit_limit_cents,expires_at,created_by,created_at) VALUES(?,?,?,?,?,?,?,?,?,NOW())')
            ->execute([$publicId, $this->hash($secret), $partnerName, json_encode(self::normalizePermissions($permissions)), $dailyRequests, $dailyDebitCents, $dailyCreditCents, $expiresAt, $createdBy]);
        return ['id' => (int) $this->db->lastInsertId(), 'publicId' => $publicId, 'key' => 'kcp_' . $publicId . '_' . $secret];
    }

    /** @return array{id:int,partnerName:string,permissions:list<string>,dailyRequestLimit:int,dailyDebitLimitCents:int,dailyCreditLimitCents:int} */
    public function authenticate(string $authorization): array
    {
        $parsed = self::parse($authorization);
        // Same answer for every failure, so a caller learns nothing about which keys exist.
        $denied = new PartnerApiException('Chave de parceiro inválida.', 401, 'unauthorized');
        if ($parsed === null) { throw $denied; }
        $stmt = $this->db->prepare('SELECT * FROM partner_api_keys WHERE public_id=? LIMIT 1');
        $stmt->execute([$parsed['publicId']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $hash = $this->hash($parsed['secret']);
        if (!$row || !hash_equals((string) $row['secret_hash'], $hash)) { throw $denied; }
        if ((int) $row['is_active'] !== 1 || $row['revoked_at'] !== null || ($row['expires_at'] !== null && strtotime((string) $row['expires_at']) <= time())) { throw $denied; }
        $permissions = json_decode((string) $row['permissions'], true);
        $this->db->prepare('UPDATE partner_api_keys SET last_used_at=NOW() WHERE id=?')->execute([$row['id']]);
        return [
            'id' => (int) $row['id'], 'partnerName' => (string) $row['partner_name'],
            'permissions' => is_array($permissions) ? array_values(array_intersect($permissions, self::PERMISSIONS)) : [],
            'dailyRequestLimit' => (int) $row['daily_request_limit'],
            'dailyDebitLimitCents' => (int) $row['daily_debit_limit_cents'],
            'dailyCreditLimitCents' => (int) $row['daily_credit_limit_cents'],
        ];
    }

    public static function requirePermission(array $key, string $permission): void
    {
        if (!in_array($permission, self::PERMISSIONS, true) || !in_array($permission, $key['permissions'], true)) {
            throw new PartnerApiException('Esta chave não tem a permissão ' . $permission . '.', 403, 'forbidden');
        }
    }

    /** Counts the request against the key's daily quota before any work is done. */
    public function countRequest(array $key, string $today): void
    {
        $this->db->prepare('INSERT INTO partner_api_usage_daily(api_key_id,usage_date,requests) VALUES(?,?,1) ON DUPLICATE KEY UPDATE requests=requests+1')->execute([$key['id'], $today]);
        $stmt = $this->db->prepare('SELECT requests FROM partner_api_usage_daily WHERE api_key_id=? AND usage_date=?');
        $stmt->execute([$key['id'], $today]);
        if ((int) $stmt->fetchColumn() > $key['dailyRequestLimit']) {
            throw new PartnerApiException('Limite diário de requisições desta chave atingido.', 429, 'rate_limited');
        }
    }
}
