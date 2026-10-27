<?php

declare(strict_types=1);

namespace App\Services\Auth;

/**
 * Validates an OpenID Connect id_token signed with RS256 by the Grupo Klube central login:
 * signature (public key from the issuer's JWKS), issuer, audience, expiry and nonce.
 */
final class IdTokenVerifier
{
    private const LEEWAY = 60;
    private const INVALID = 'Não foi possível confirmar o login do Grupo Klube. Tente de novo.';

    /**
     * @param array{keys?: list<array<string, mixed>>} $jwks
     * @return array<string, mixed> the verified claims
     */
    public static function verify(string $jwt, array $jwks, string $issuer, string $clientId, string $nonce, ?int $now = null): array
    {
        $now ??= time();
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new GrupoKlubeSsoException(self::INVALID);
        }
        [$encodedHeader, $encodedClaims, $encodedSignature] = $parts;
        $header = json_decode(self::base64UrlDecode($encodedHeader), true);
        $claims = json_decode(self::base64UrlDecode($encodedClaims), true);
        if (!is_array($header) || !is_array($claims) || ($header['alg'] ?? null) !== 'RS256') {
            throw new GrupoKlubeSsoException(self::INVALID);
        }

        $pem = self::jwkToPem(self::findKey($jwks, $header['kid'] ?? null));
        $signed = openssl_verify($encodedHeader . '.' . $encodedClaims, self::base64UrlDecode($encodedSignature), $pem, OPENSSL_ALGO_SHA256);
        if ($signed !== 1) {
            throw new GrupoKlubeSsoException(self::INVALID);
        }

        $audiences = is_array($claims['aud'] ?? null) ? $claims['aud'] : [$claims['aud'] ?? null];
        $valid = ($claims['iss'] ?? null) === $issuer
            && in_array($clientId, $audiences, true)
            && (count($audiences) === 1 || ($claims['azp'] ?? null) === $clientId)
            && is_int($claims['exp'] ?? null) && $claims['exp'] > $now - self::LEEWAY
            && (!isset($claims['iat']) || (is_int($claims['iat']) && $claims['iat'] <= $now + self::LEEWAY))
            && is_string($claims['nonce'] ?? null) && hash_equals($nonce, $claims['nonce'])
            && is_string($claims['sub'] ?? null) && $claims['sub'] !== '';
        if (!$valid) {
            throw new GrupoKlubeSsoException(self::INVALID);
        }
        return $claims;
    }

    /** @param array<string, mixed> $jwk an RSA public key (n, e) */
    public static function jwkToPem(array $jwk): string
    {
        if (($jwk['kty'] ?? null) !== 'RSA' || !is_string($jwk['n'] ?? null) || !is_string($jwk['e'] ?? null)) {
            throw new GrupoKlubeSsoException(self::INVALID);
        }
        $rsaKey = self::der(0x30, self::derInteger(self::base64UrlDecode($jwk['n'])) . self::derInteger(self::base64UrlDecode($jwk['e'])));
        $rsaEncryption = self::der(0x30, self::der(0x06, "\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01") . self::der(0x05, ''));
        $publicKeyInfo = self::der(0x30, $rsaEncryption . self::der(0x03, "\x00" . $rsaKey));
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($publicKeyInfo), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /**
     * @param array{keys?: list<array<string, mixed>>} $jwks
     * @return array<string, mixed>
     */
    private static function findKey(array $jwks, mixed $kid): array
    {
        $candidates = array_values(array_filter(
            $jwks['keys'] ?? [],
            static fn ($key): bool => is_array($key) && ($key['kty'] ?? null) === 'RSA' && ($key['use'] ?? 'sig') === 'sig'
                && ($kid === null || ($key['kid'] ?? null) === $kid),
        ));
        if (count($candidates) !== 1) {
            throw new GrupoKlubeSsoException(self::INVALID);
        }
        return $candidates[0];
    }

    private static function derInteger(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '' || ord($bytes[0]) > 0x7f) {
            $bytes = "\x00" . $bytes;
        }
        return self::der(0x02, $bytes);
    }

    private static function der(int $tag, string $value): string
    {
        $length = strlen($value);
        if ($length < 0x80) {
            return chr($tag) . chr($length) . $value;
        }
        $lengthBytes = ltrim(pack('N', $length), "\x00");
        return chr($tag) . chr(0x80 | strlen($lengthBytes)) . $lengthBytes . $value;
    }

    public static function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        if ($decoded === false) {
            throw new GrupoKlubeSsoException(self::INVALID);
        }
        return $decoded;
    }

    public static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
