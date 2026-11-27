<?php
declare(strict_types=1);

// Regras puras da listagem de lojas para parceiro (Klube Ops). Sem banco.
require_once __DIR__ . '/../../services/Partner/PartnerKeyService.php';
require_once __DIR__ . '/../../services/Partner/StoreListPartnerService.php';

use App\Services\Partner\PartnerApiException;
use App\Services\Partner\PartnerKeyService;
use App\Services\Partner\StoreListPartnerService;

$check = static function (bool $condition, string $label): void {
    if (!$condition) { throw new RuntimeException('FALHOU: ' . $label); }
    echo "OK {$label}\n";
};
$rejects = static function (callable $action, int $status, string $label) use ($check): void {
    try { $action(); } catch (PartnerApiException $error) { $check($error->httpStatus === $status, $label . " ({$error->errorCode})"); return; }
    throw new RuntimeException('FALHOU (sem erro): ' . $label);
};

$q = StoreListPartnerService::parseQuery(['identity_sub' => '3F2504E0-4F89-41D3-9A0C-0305E82C3301', 'email' => ' Ana@Exemplo.com ']);
$check($q === ['identity_sub' => '3f2504e0-4f89-41d3-9a0c-0305e82c3301', 'email' => 'ana@exemplo.com'], 'código e e-mail normalizados');
$check(StoreListPartnerService::parseQuery(['email' => 'a@b.co'])['identity_sub'] === null, 'só e-mail vale');
$rejects(fn () => StoreListPartnerService::parseQuery([]), 400, 'sem pessoa não lista');
$rejects(fn () => StoreListPartnerService::parseQuery(['identity_sub' => 'x']), 400, 'código inválido recusado');
$rejects(fn () => StoreListPartnerService::parseQuery(['email' => 'nao-e-email']), 400, 'e-mail inválido recusado');

$shape = StoreListPartnerService::shape([
    ['id' => 12, 'nome_fantasia' => 'Bar do Zé', 'razao_social' => 'ZE LTDA', 'cnpj' => '1', 'email' => 'x@y'],
    ['id' => 12, 'nome_fantasia' => 'Bar do Zé', 'razao_social' => 'ZE LTDA'],
    ['id' => 15, 'nome_fantasia' => '', 'razao_social' => 'Padaria Sol LTDA'],
    ['id' => 16, 'nome_fantasia' => '', 'razao_social' => ''],
], [12]);
$check($shape === [
    ['storeId' => 12, 'name' => 'Bar do Zé', 'allowed' => true],
    ['storeId' => 15, 'name' => 'Padaria Sol LTDA', 'allowed' => false],
    ['storeId' => 16, 'name' => 'Loja 16', 'allowed' => false],
], 'sem repetir, nome com reserva e liberada para a chave');
$check(!str_contains(json_encode($shape), 'x@y') && !str_contains(json_encode($shape), 'cnpj'), 'CNPJ e e-mail nunca saem');
$check(in_array('stores:list', PartnerKeyService::PERMISSIONS, true), 'permissão stores:list existe');
$check(PartnerKeyService::normalizePermissions(['stores:list', 'sales:create']) === ['sales:create', 'stores:list'], 'stores:list emitível');
$rejects(fn () => PartnerKeyService::requirePermission(['permissions' => ['sales:create']], 'stores:list'), 403, 'chave sem permissão não lista');
echo "Tudo certo.\n";
