<?php

declare(strict_types=1);

// "Entrar com Grupo Klube": id_token validation and the Authorization Code + PKCE flow, with a fake
// central login (no network). Run: php tests/php/grupoklube_sso_unit.php

require dirname(__DIR__, 2) . '/services/Auth/GrupoKlubeSsoException.php';
require dirname(__DIR__, 2) . '/services/Auth/IdTokenVerifier.php';
require dirname(__DIR__, 2) . '/services/Auth/GrupoKlubeSso.php';

use App\Services\Auth\GrupoKlubeSso;
use App\Services\Auth\GrupoKlubeSsoException;
use App\Services\Auth\IdTokenVerifier;

function check(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException('FALHOU: ' . $label);
    }
    echo "OK {$label}\n";
}

function refuses(callable $action, string $label): void
{
    try {
        $action();
    } catch (GrupoKlubeSsoException) {
        echo "OK {$label}\n";
        return;
    }
    throw new RuntimeException('FALHOU (aceitou): ' . $label);
}

/** Windows builds of PHP may lack an openssl.cnf; a minimal one is enough to create test keys. */
function rsaKey(): OpenSSLAsymmetricKey
{
    $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
    $key = openssl_pkey_new($options);
    if ($key === false) {
        $config = tempnam(sys_get_temp_dir(), 'ossl');
        file_put_contents($config, "[req]\ndistinguished_name=dn\n[dn]\n");
        $key = openssl_pkey_new($options + ['config' => $config]);
        unlink($config);
    }
    if ($key === false) {
        throw new RuntimeException('Não foi possível gerar a chave RSA de teste.');
    }
    return $key;
}

$issuer = 'https://login.grupoklube.test';
$clientId = 'klubecash';
$now = 1_800_000_000;
$key = rsaKey();
$details = openssl_pkey_get_details($key);
$jwk = ['kty' => 'RSA', 'kid' => 'k1', 'use' => 'sig', 'alg' => 'RS256',
    'n' => IdTokenVerifier::base64UrlEncode($details['rsa']['n']), 'e' => IdTokenVerifier::base64UrlEncode($details['rsa']['e'])];
$jwks = ['keys' => [$jwk]];

$sign = static function (array $claims, array $header = ['alg' => 'RS256', 'kid' => 'k1'], ?OpenSSLAsymmetricKey $with = null) use ($key): string {
    $input = IdTokenVerifier::base64UrlEncode(json_encode($header)) . '.' . IdTokenVerifier::base64UrlEncode(json_encode($claims));
    openssl_sign($input, $signature, $with ?? $key, OPENSSL_ALGO_SHA256);
    return $input . '.' . IdTokenVerifier::base64UrlEncode($signature);
};
$claims = ['iss' => $issuer, 'aud' => $clientId, 'sub' => 'identity-1', 'nonce' => 'n-1', 'iat' => $now, 'exp' => $now + 300, 'klubecash_user_id' => 11];

// --- IdTokenVerifier
$pem = IdTokenVerifier::jwkToPem($jwk);
check(openssl_pkey_get_details(openssl_pkey_get_public($pem))['rsa']['n'] === $details['rsa']['n'], 'JWK convertida para a mesma chave pública');
check(IdTokenVerifier::verify($sign($claims), $jwks, $issuer, $clientId, 'n-1', $now)['klubecash_user_id'] === 11, 'id_token válido aceito');
refuses(fn () => IdTokenVerifier::verify($sign(['iss' => 'https://outro.test'] + $claims), $jwks, $issuer, $clientId, 'n-1', $now), 'emissor diferente recusado');
refuses(fn () => IdTokenVerifier::verify($sign(['aud' => 'outro-app'] + $claims), $jwks, $issuer, $clientId, 'n-1', $now), 'token de outro aplicativo recusado');
refuses(fn () => IdTokenVerifier::verify($sign(['aud' => [$clientId, 'outro-app']] + $claims), $jwks, $issuer, $clientId, 'n-1', $now), 'várias audiências sem azp recusadas');
refuses(fn () => IdTokenVerifier::verify($sign(['exp' => $now - 120] + $claims), $jwks, $issuer, $clientId, 'n-1', $now), 'token expirado recusado');
refuses(fn () => IdTokenVerifier::verify($sign($claims), $jwks, $issuer, $clientId, 'outro-nonce', $now), 'nonce diferente recusado');
refuses(fn () => IdTokenVerifier::verify($sign($claims, ['alg' => 'HS256', 'kid' => 'k1']), $jwks, $issuer, $clientId, 'n-1', $now), 'algoritmo HS256 recusado');
refuses(fn () => IdTokenVerifier::verify($sign($claims, ['alg' => 'none']), $jwks, $issuer, $clientId, 'n-1', $now), 'algoritmo none recusado');
refuses(fn () => IdTokenVerifier::verify($sign($claims, ['alg' => 'RS256', 'kid' => 'outra']), $jwks, $issuer, $clientId, 'n-1', $now), 'chave desconhecida recusada');
refuses(fn () => IdTokenVerifier::verify($sign($claims, ['alg' => 'RS256', 'kid' => 'k1'], rsaKey()), $jwks, $issuer, $clientId, 'n-1', $now), 'assinatura de outra chave recusada');
[$h, , $s] = explode('.', $sign($claims));
$forged = $h . '.' . IdTokenVerifier::base64UrlEncode(json_encode(['klubecash_user_id' => 1] + $claims)) . '.' . $s;
refuses(fn () => IdTokenVerifier::verify($forged, $jwks, $issuer, $clientId, 'n-1', $now), 'conteúdo adulterado recusado');
refuses(fn () => IdTokenVerifier::verify('abc.def', $jwks, $issuer, $clientId, 'n-1', $now), 'token malformado recusado');

// --- safeReturnTo
check(GrupoKlubeSso::safeReturnTo('/admin/dashboard?x=1') === '/admin/dashboard?x=1', 'retorno para página do próprio site');
foreach (['//evil.test', 'https://evil.test', '/\\evil.test', '\\\\evil', '/a b', '', null, 42] as $bad) {
    check(GrupoKlubeSso::safeReturnTo($bad) === null, 'retorno externo recusado: ' . json_encode($bad));
}

// --- Authorization Code + PKCE against a fake central login
$requests = [];
$tokenClaims = $claims;
$http = static function (string $method, string $url, array $headers, ?string $body) use (&$requests, &$tokenClaims, $sign, $jwks, $issuer): array {
    $requests[] = compact('method', 'url', 'headers', 'body');
    if ($url === $issuer . '/token') {
        return ['status' => 200, 'body' => json_encode(['id_token' => $sign($tokenClaims), 'access_token' => 'x', 'token_type' => 'Bearer'])];
    }
    if ($url === $issuer . '/jwks') {
        return ['status' => 200, 'body' => json_encode($jwks)];
    }
    return ['status' => 404, 'body' => '{}'];
};
$sso = new GrupoKlubeSso($issuer, $clientId, 'segredo-de-teste', 'https://klubecash.test/auth/grupoklube/callback', $http);

$_SESSION = [];
$url = $sso->begin('/admin/dashboard', $now);
parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
check(str_starts_with($url, $issuer . '/auth?') && $params['client_id'] === $clientId && $params['scope'] === 'openid profile email klubecash', 'redireciona ao login central com o escopo klubecash');
check($params['code_challenge_method'] === 'S256' && $params['redirect_uri'] === 'https://klubecash.test/auth/grupoklube/callback', 'PKCE S256 e endereço de retorno fixo');
$tokenClaims = ['nonce' => $params['nonce']] + $claims;
$done = $sso->complete(['code' => 'abc', 'state' => $params['state']], $now + 5);
check($done['klubecashUserId'] === 11 && $done['returnTo'] === '/admin/dashboard', 'login concluído com a conta KlubeCash do token');
$tokenRequest = $requests[0];
parse_str((string) $tokenRequest['body'], $form);
check(in_array('Authorization: Basic ' . base64_encode('klubecash:segredo-de-teste'), $tokenRequest['headers'], true), 'segredo enviado só servidor a servidor (Basic)');
check(IdTokenVerifier::base64UrlEncode(hash('sha256', $form['code_verifier'], true)) === $params['code_challenge'], 'verificador PKCE confere com o desafio');
check(!isset($_SESSION['grupoklube_sso']), 'login pendente apagado depois do uso');
refuses(fn () => $sso->complete(['code' => 'abc', 'state' => $params['state']], $now + 6), 'mesmo retorno usado duas vezes recusado');

$url = $sso->begin(null, $now);
parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
refuses(fn () => $sso->complete(['code' => 'abc', 'state' => 'outro'], $now), 'state diferente recusado');
$sso->begin(null, $now);
$state = $_SESSION['grupoklube_sso']['state'];
refuses(fn () => $sso->complete(['code' => 'abc', 'state' => $state], $now + 601), 'login com mais de 10 minutos recusado');
$sso->begin(null, $now);
refuses(fn () => $sso->complete(['error' => 'access_denied', 'state' => $_SESSION['grupoklube_sso']['state']], $now), 'erro do login central mostrado como recusa');
$url = $sso->begin(null, $now);
parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
$tokenClaims = ['nonce' => $params['nonce']] + $claims;
unset($tokenClaims['klubecash_user_id']);
refuses(fn () => $sso->complete(['code' => 'abc', 'state' => $params['state']], $now), 'conta central sem conta KlubeCash recusada');
$url = $sso->begin(null, $now);
parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
$tokenClaims = $claims; // nonce from an older login
refuses(fn () => $sso->complete(['code' => 'abc', 'state' => $params['state']], $now), 'token de outro login (nonce antigo) recusado');

// --- configuration
putenv('GRUPOKLUBE_CLIENT_SECRET=');
check(GrupoKlubeSso::fromEnvironment('https://klubecash.com') === null, 'sem segredo configurado o login central fica desligado');
putenv('GRUPOKLUBE_CLIENT_SECRET=s');
putenv('GRUPOKLUBE_ISSUER=http://login.grupoklube.online');
check(GrupoKlubeSso::fromEnvironment('https://klubecash.com') === null, 'emissor sem https recusado');
putenv('GRUPOKLUBE_ISSUER=');
$configured = GrupoKlubeSso::fromEnvironment('https://klubecash.com/');
check($configured?->issuer === 'https://login.grupoklube.online' && $configured->redirectUri === 'https://klubecash.com/auth/grupoklube/callback', 'padrões de produção');
check(str_starts_with($configured->logoutUrl('t', 'https://klubecash.com/login'), 'https://login.grupoklube.online/session/end?id_token_hint=t&post_logout_redirect_uri=https%3A%2F%2Fklubecash.com%2Flogin'), 'saída também encerra a sessão central');

echo "OK: login do Grupo Klube validado.\n";
