<?php

declare(strict_types=1);

namespace App\Services\Auth;

/**
 * "Entrar com Grupo Klube": OpenID Connect Authorization Code with PKCE against the central login
 * (login.grupoklube.online). KlubeCash is a confidential client; the secret only travels server to server.
 *
 * The central login answers with the claim klubecash_user_id, the KlubeCash account it already verified
 * with this account's own password. The account is opened by that id, never matched by e-mail.
 */
final class GrupoKlubeSso
{
    private const SESSION_KEY = 'grupoklube_sso';
    private const MAX_AGE = 600;
    private const SCOPE = 'openid profile email klubecash';

    /** @var callable(string, string, list<string>, ?string): array{status: int, body: string} */
    private $http;

    public function __construct(
        public readonly string $issuer,
        public readonly string $clientId,
        #[\SensitiveParameter] private readonly string $clientSecret,
        public readonly string $redirectUri,
        ?callable $http = null,
    ) {
        $this->http = $http ?? [self::class, 'curl'];
    }

    /** Null while the client secret is not configured: the routes then fall back to the normal login. */
    public static function fromEnvironment(string $siteUrl): ?self
    {
        $secret = trim((string) getenv('GRUPOKLUBE_CLIENT_SECRET'));
        if ($secret === '') {
            return null;
        }
        $issuer = rtrim(trim((string) (getenv('GRUPOKLUBE_ISSUER') ?: 'https://login.grupoklube.online')), '/');
        $host = (string) parse_url($issuer, PHP_URL_HOST);
        $scheme = (string) parse_url($issuer, PHP_URL_SCHEME);
        if ($host === '' || ($scheme !== 'https' && !($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1'], true)))) {
            return null;
        }
        $clientId = trim((string) (getenv('GRUPOKLUBE_CLIENT_ID') ?: 'klubecash'));
        return new self($issuer, $clientId, $secret, rtrim($siteUrl, '/') . '/auth/grupoklube/callback');
    }

    /** Same rule as the login page: a path on this site only. */
    public static function safeReturnTo(mixed $value): ?string
    {
        if (!is_string($value) || $value === '' || strlen($value) > 500) {
            return null;
        }
        return preg_match('#^/(?![/\\\\])[^\\\\\s]*$#', $value) === 1 ? $value : null;
    }

    /** Remembers state, nonce and the PKCE verifier in the session and returns the central login URL. */
    public function begin(?string $returnTo, ?int $now = null): string
    {
        $state = self::random();
        $nonce = self::random();
        $verifier = self::random();
        $_SESSION[self::SESSION_KEY] = [
            'state' => $state,
            'nonce' => $nonce,
            'verifier' => $verifier,
            'returnTo' => self::safeReturnTo($returnTo),
            'createdAt' => $now ?? time(),
        ];
        return $this->issuer . '/auth?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => self::SCOPE,
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => IdTokenVerifier::base64UrlEncode(hash('sha256', $verifier, true)),
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Finishes the login started by begin(). The pending login is single use.
     *
     * @param array<string, mixed> $query the callback query string
     * @return array{klubecashUserId: int, idToken: string, returnTo: ?string}
     */
    public function complete(array $query, ?int $now = null): array
    {
        $now ??= time();
        $pending = $_SESSION[self::SESSION_KEY] ?? null;
        unset($_SESSION[self::SESSION_KEY]);
        if (!is_array($pending)) {
            throw new GrupoKlubeSsoException('Este login expirou ou já foi usado. Tente entrar de novo.');
        }
        if (isset($query['error'])) {
            throw new GrupoKlubeSsoException('O login do Grupo Klube foi cancelado ou não foi autorizado.');
        }
        $state = $query['state'] ?? null;
        $code = $query['code'] ?? null;
        if (!is_string($state) || !hash_equals((string) $pending['state'], $state) || !is_string($code) || $code === '' || strlen($code) > 512) {
            throw new GrupoKlubeSsoException('Não foi possível confirmar este login. Tente entrar de novo.');
        }
        if ($now - (int) $pending['createdAt'] > self::MAX_AGE) {
            throw new GrupoKlubeSsoException('O login demorou demais e expirou. Tente entrar de novo.');
        }

        $tokens = $this->json('POST', $this->issuer . '/token', [
            'Authorization: Basic ' . base64_encode(rawurlencode($this->clientId) . ':' . rawurlencode($this->clientSecret)),
            'Content-Type: application/x-www-form-urlencoded',
        ], http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'code_verifier' => (string) $pending['verifier'],
        ], '', '&', PHP_QUERY_RFC3986));
        $idToken = $tokens['id_token'] ?? null;
        if (!is_string($idToken)) {
            throw new GrupoKlubeSsoException('Não foi possível confirmar o login do Grupo Klube. Tente de novo.');
        }

        $claims = IdTokenVerifier::verify($idToken, $this->json('GET', $this->issuer . '/jwks', [], null), $this->issuer, $this->clientId, (string) $pending['nonce'], $now);
        $userId = $claims['klubecash_user_id'] ?? null;
        if (!is_int($userId) || $userId <= 0) {
            throw new GrupoKlubeSsoException('Sua conta do Grupo Klube não está ligada a uma conta KlubeCash.');
        }
        return ['klubecashUserId' => $userId, 'idToken' => $idToken, 'returnTo' => $pending['returnTo'] ?? null];
    }

    /** Ends the central session too, then comes back to this site. */
    public function logoutUrl(string $idToken, string $postLogoutRedirectUri): string
    {
        return $this->issuer . '/session/end?' . http_build_query([
            'id_token_hint' => $idToken,
            'post_logout_redirect_uri' => $postLogoutRedirectUri,
            'client_id' => $this->clientId,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param list<string> $headers
     * @return array<string, mixed>
     */
    private function json(string $method, string $url, array $headers, ?string $body): array
    {
        $response = ($this->http)($method, $url, [...$headers, 'Accept: application/json'], $body);
        $payload = json_decode($response['body'], true);
        if ($response['status'] !== 200 || !is_array($payload)) {
            error_log('auth.grupoklube.http_failed status=' . $response['status'] . ' endpoint=' . (string) parse_url($url, PHP_URL_PATH));
            throw new GrupoKlubeSsoException('Não foi possível confirmar o login do Grupo Klube. Tente de novo.');
        }
        return $payload;
    }

    /**
     * @param list<string> $headers
     * @return array{status: int, body: string}
     */
    public static function curl(string $method, string $url, array $headers, ?string $body): array
    {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
        ]);
        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }
        $raw = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        if ($raw === false) {
            error_log('auth.grupoklube.unreachable errno=' . curl_errno($handle));
            throw new GrupoKlubeSsoException('O login do Grupo Klube não respondeu. Tente de novo em instantes.');
        }
        return ['status' => $status, 'body' => (string) $raw];
    }

    private static function random(): string
    {
        return IdTokenVerifier::base64UrlEncode(random_bytes(32));
    }
}
