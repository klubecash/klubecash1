<?php

declare(strict_types=1);

// Menu "Apps" do Grupo Klube: lista do login unico (falso, sem rede) e regras de exibicao.
// Run: php tests/php/grupoklube_apps_unit.php

require dirname(__DIR__, 2) . '/services/Auth/GrupoKlubeSsoException.php';
require dirname(__DIR__, 2) . '/services/Auth/IdTokenVerifier.php';
require dirname(__DIR__, 2) . '/services/Auth/GrupoKlubeSso.php';
require dirname(__DIR__, 2) . '/services/Auth/GrupoKlubeApps.php';

use App\Services\Auth\GrupoKlubeApps;
use App\Services\Auth\GrupoKlubeSso;

function check(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException('FALHOU: ' . $label);
    }
    echo "OK {$label}\n";
}

const PORTAL = 'https://intranet.example.com';
$cash = ['slug' => 'klubecash', 'name' => 'KlubeCash', 'launchUrl' => 'https://cash.example.com/auth/login'];
$pass = ['slug' => 'klube-pass', 'name' => 'Klube Pass', 'launchUrl' => 'https://pass.example.com/auth/login'];
$portal = ['slug' => 'klube-intranet', 'name' => 'Portal', 'launchUrl' => null];

// --- regras de exibicao ---
check(GrupoKlubeApps::build([$cash], PORTAL) === null, 'um app so: sem menu');
check(GrupoKlubeApps::build([], PORTAL) === null, 'lista vazia: sem menu');
check(GrupoKlubeApps::build(null, PORTAL) === null, 'sem lista na sessao: sem menu');
check(GrupoKlubeApps::build('lixo', PORTAL) === null, 'lista invalida: sem menu');
check(GrupoKlubeApps::build([$portal, $cash], PORTAL) === null, 'Portal nao conta como app');

$menu = GrupoKlubeApps::build([$portal, $cash, $pass, $pass], PORTAL);
check($menu !== null && $menu['portalUrl'] === PORTAL, 'dois apps: menu com o endereco do Portal');
check($menu !== null && count($menu['itens']) === 2, 'repetidos e o Portal ficam de fora');
check($menu !== null && $menu['itens'][0] === ['slug' => 'klubecash', 'nome' => 'KlubeCash', 'url' => '/', 'atual' => true], 'o app atual aponta para a raiz e vem marcado');
check($menu !== null && $menu['itens'][1]['url'] === 'https://pass.example.com/auth/login' && $menu['itens'][1]['atual'] === false, 'os outros apps usam o endereco de entrada');

$perigoso = ['slug' => 'x', 'name' => 'X', 'launchUrl' => 'javascript:alert(1)'];
$semEndereco = ['slug' => 'y', 'name' => 'Y', 'launchUrl' => null];
check(GrupoKlubeApps::build([$cash, $perigoso, $semEndereco], PORTAL) === null, 'endereco que nao e http(s) ou ausente nao entra');
check(GrupoKlubeApps::build([$cash, $pass], 'javascript:alert(1)') === null, 'Portal com endereco perigoso: sem menu');
check(GrupoKlubeApps::build([['slug' => 1, 'name' => 'x'], $cash, $pass], PORTAL) !== null, 'entrada mal formada e ignorada');

// --- sessao ---
$_SESSION = [];
check(GrupoKlubeApps::menu() === null, 'sessao sem lista: sem menu');
GrupoKlubeApps::store([$cash, $pass]);
check(GrupoKlubeApps::menu() !== null, 'lista guardada na sessao: menu aparece');
GrupoKlubeApps::forget();
check(GrupoKlubeApps::menu() === null, 'depois de esquecer: sem menu');

// --- lista vinda do login unico (falso) ---
$calls = [];
$sso = new GrupoKlubeSso('https://login.example.com', 'klubecash', 'segredo', 'https://cash.example.com/cb', static function (string $method, string $url, array $headers, ?string $body) use (&$calls): array {
    $calls[] = [$method, $url, $headers];
    return ['status' => 200, 'body' => json_encode(['applications' => [
        ['slug' => 'klubecash', 'name' => 'KlubeCash', 'launchUrl' => 'https://cash.example.com/auth/login'],
        ['slug' => 'klube-pass', 'name' => 'Klube Pass', 'launchUrl' => null],
        ['slug' => 5, 'name' => 'ruim'],
        'lixo',
    ]])];
});
$apps = $sso->applications('token-de-acesso');
check(count($apps) === 2 && $apps[0]['slug'] === 'klubecash' && $apps[1]['launchUrl'] === null, 'lista do login unico: so entradas validas');
check($calls[0][0] === 'GET' && $calls[0][1] === 'https://login.example.com/v1/me/applications' && in_array('Authorization: Bearer token-de-acesso', $calls[0][2], true), 'chamada com o token de acesso no endereco certo');
check($sso->applications(null) === [] && $sso->applications('') === [], 'sem token: lista vazia, sem chamada');

$falha = new GrupoKlubeSso('https://login.example.com', 'klubecash', 'segredo', 'https://cash.example.com/cb', static fn (): array => ['status' => 401, 'body' => '']);
check($falha->applications('x') === [], 'login unico recusou: lista vazia');
$cai = new GrupoKlubeSso('https://login.example.com', 'klubecash', 'segredo', 'https://cash.example.com/cb', static function (): array { throw new RuntimeException('fora do ar'); });
check($cai->applications('x') === [], 'login unico fora do ar: lista vazia, sem erro');
$lixo = new GrupoKlubeSso('https://login.example.com', 'klubecash', 'segredo', 'https://cash.example.com/cb', static fn (): array => ['status' => 200, 'body' => 'nao e json']);
check($lixo->applications('x') === [], 'resposta que nao e JSON: lista vazia');

echo "Tudo certo.\n";
