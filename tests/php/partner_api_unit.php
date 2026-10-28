<?php
declare(strict_types=1);

// Pure checks for the Klube Pass partner API: key format, permissions, refs,
// return addresses and the giftback rule. No database.
require_once __DIR__ . '/../../services/Partner/PartnerKeyService.php';
require_once __DIR__ . '/../../services/Partner/PassPartnerService.php';
require_once __DIR__ . '/../../services/Partner/PassReturnUrl.php';
require_once __DIR__ . '/../../services/Partner/PassStorePolicy.php';
require_once __DIR__ . '/../../services/Partner/PassCreditNoticeService.php';

use App\Services\Partner\PartnerApiException;
use App\Services\Partner\PartnerKeyService;
use App\Services\Partner\PassCreditNoticeService;
use App\Services\Partner\PassPartnerService;
use App\Services\Partner\PassReturnUrl;
use App\Services\Partner\PassStorePolicy;

$check = static function (bool $condition, string $label): void {
    if (!$condition) { throw new RuntimeException('FALHOU: ' . $label); }
    echo "OK {$label}\n";
};
$rejects = static function (callable $action, int $status, string $label) use ($check): void {
    try { $action(); } catch (PartnerApiException $error) { $check($error->httpStatus === $status, $label . " ({$error->errorCode})"); return; }
    throw new RuntimeException('FALHOU (sem erro): ' . $label);
};

$secret = str_repeat('A', 43);
$check(PartnerKeyService::parse('Bearer kcp_0123456789abcdef_' . $secret) === ['publicId' => '0123456789abcdef', 'secret' => $secret], 'chave bem formada');
foreach (['', 'Bearer kc_live_x', 'kcp_0123456789abcdef_' . $secret, 'Bearer kcp_0123456789ABCDEF_' . $secret, 'Bearer kcp_0123456789abcdef_' . $secret . 'x'] as $bad) {
    $check(PartnerKeyService::parse($bad) === null, 'chave rejeitada: ' . substr($bad, 0, 20));
}
$rejects(fn () => new PartnerKeyService((new ReflectionClass(PDO::class))->newInstanceWithoutConstructor(), 'curto'), 503, 'pepper curto recusa a API');

$check(PartnerKeyService::normalizePermissions(['pass:debit', 'pass:balance', 'pass:debit']) === ['pass:balance', 'pass:debit'], 'permissões normalizadas');
$rejects(fn () => PartnerKeyService::normalizePermissions(['*']), 422, 'regra 8: curinga nunca é emitido');
$rejects(fn () => PartnerKeyService::normalizePermissions(['pass:debit', 'admin']), 422, 'permissão desconhecida recusada');
$rejects(fn () => PartnerKeyService::normalizePermissions([]), 422, 'chave sem permissão recusada');
$rejects(fn () => PartnerKeyService::requirePermission(['permissions' => ['pass:balance', 'pass:debit']], 'pass:credit'), 403, 'regra 8: sem pass:credit recebe 403');
$rejects(fn () => PartnerKeyService::requirePermission(['permissions' => ['*']], 'pass:credit'), 403, 'regra 8: curinga gravado não vale');
PartnerKeyService::requirePermission(['permissions' => ['pass:credit']], 'pass:credit');
$check(true, 'permissão exata aceita');

$check(PassPartnerService::passRef('leftover.123-abc') === 'leftover.123-abc', 'pass_ref válido');
foreach (['curto', 'com espaço aqui', 'tem:dois-pontos', str_repeat('a', 81), 123] as $bad) {
    $rejects(fn () => PassPartnerService::passRef($bad), 422, 'pass_ref inválido: ' . (is_string($bad) ? substr($bad, 0, 15) : 'número'));
}

putenv('PASS_RETURN_ORIGINS=https://pass.grupoklube.online,http://localhost:3300');
$check(PassReturnUrl::safe('https://pass.grupoklube.online/entrar?x=1') === 'https://pass.grupoklube.online/entrar?x=1', 'volta ao Pass aceita');
$check(PassReturnUrl::safe('http://localhost:3300/') === 'http://localhost:3300/', 'origem local da lista aceita');
foreach (['https://pass.grupoklube.online.evil.test/', 'http://pass.grupoklube.online/', 'https://evil.test/', 'https://user:pw@pass.grupoklube.online/', '//pass.grupoklube.online', "https://pass.grupoklube.online/\n", null] as $bad) {
    $check(PassReturnUrl::safe($bad) === null, 'volta recusada: ' . json_encode($bad));
}

// Regra 2: R$ 100 = R$ 40 de saldo + R$ 60 em dinheiro/Pix, 5% de giftback.
$regular = ['promoter' => false, 'giftbackOnCash' => false];
$check(PassStorePolicy::saleGiftbackCents($regular, true, 6000, '5.00') === 300, 'loja comum: giftback sobre a parte paga');
$check(PassStorePolicy::saleGiftbackCents($regular, false, 6000, '5.00') === 0, 'loja comum com giftback desligado: zero');
$check(PassStorePolicy::saleGiftbackCents(['promoter' => true, 'giftbackOnCash' => true], false, 6000, '5.00') === 300, 'regra 2: promotor dá giftback só sobre os R$ 60');
$check(PassStorePolicy::saleGiftbackCents(['promoter' => true, 'giftbackOnCash' => false], true, 6000, '5.00') === 0, 'promotor sem giftback_sobre_dinheiro: zero');

$message = PassCreditNoticeService::message('Ana Fictícia Souza', 'Promotor Teste', 4250, '31/12/2026');
$check(str_contains($message, 'Ana!') && str_contains($message, 'R$ 42,50') && str_contains($message, '31/12/2026'), 'aviso da sobra com nome, valor e validade');
$check(!str_contains(PassCreditNoticeService::message('', 'Loja', 100, null), 'vale ate'), 'aviso sem validade');

echo "PASS: partner_api_unit\n";
