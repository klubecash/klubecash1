<?php
declare(strict_types=1);

// Regras puras das vendas de parceiro (Klube Ops). Sem banco.
require_once __DIR__ . '/../../services/Partner/PartnerKeyService.php';
require_once __DIR__ . '/../../services/Partner/StoreSalesPartnerService.php';

use App\Services\Partner\PartnerApiException;
use App\Services\Partner\PartnerKeyService;
use App\Services\Partner\StoreSalesPartnerService;

$check = static function (bool $condition, string $label): void {
    if (!$condition) { throw new RuntimeException('FALHOU: ' . $label); }
    echo "OK {$label}\n";
};
$rejects = static function (callable $action, int $status, string $label) use ($check): void {
    try { $action(); } catch (PartnerApiException $error) { $check($error->httpStatus === $status, $label . " ({$error->errorCode})"); return; }
    throw new RuntimeException('FALHOU (sem erro): ' . $label);
};

$check(StoreSalesPartnerService::parseBindings('7:12|15, 9:20,lixo,8:abc,7:16') === [7 => [12, 15, 16], 9 => [20]], 'vínculos chave→lojas lidos e lixo ignorado');
StoreSalesPartnerService::assertStoreAllowed(['id' => 7], 15, '7:12|15');
$check(true, 'loja liberada passa');
$rejects(fn () => StoreSalesPartnerService::assertStoreAllowed(['id' => 7], 99, '7:12|15'), 403, 'loja não liberada recusada');
$rejects(fn () => StoreSalesPartnerService::assertStoreAllowed(['id' => 8], 12, '7:12|15'), 403, 'chave sem vínculo recusada');
$rejects(fn () => StoreSalesPartnerService::assertStoreAllowed(['id' => 7], 12, ''), 403, 'sem configuração ninguém vende');

$public = StoreSalesPartnerService::publicCustomer(['id' => 5, 'name' => 'Ana Souza Lima', 'phone' => '(34) 99999-1234', 'email' => 'x@y', 'cpf' => '123', 'balanceCents' => 870]);
$check($public === ['customerId' => 5, 'firstName' => 'Ana', 'phoneEnd' => '1234', 'balanceCents' => 870, 'requiresPhoneVerification' => false], 'cliente sai só com primeiro nome e final do telefone');
$check(!str_contains(json_encode($public), 'x@y') && !str_contains(json_encode($public), '"123"'), 'e-mail e CPF nunca saem');

$check(PartnerKeyService::normalizePermissions(['sales:create', 'customers:lookup']) === ['customers:lookup', 'sales:create'], 'novas permissões emitíveis');
$rejects(fn () => PartnerKeyService::requirePermission(['permissions' => ['pass:debit']], 'sales:create'), 403, 'chave do Pass não vende em loja');
echo "Tudo certo.\n";
