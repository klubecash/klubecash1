<?php

declare(strict_types=1);

require_once __DIR__ . '/../../services/store/StoreApiException.php';
require_once __DIR__ . '/../../services/store/StoreIdempotencyService.php';
require_once __DIR__ . '/../../services/store/StoreTransactionService.php';

use App\Services\Store\StoreApiException;
use App\Services\Store\StoreTransactionService;

$service = (new ReflectionClass(StoreTransactionService::class))->newInstanceWithoutConstructor();
$validate = new ReflectionMethod(StoreTransactionService::class, 'validateInput');
$input = ['customerId' => 1, 'grossAmountCents' => 10000, 'balanceUsedCents' => 0,
    'code' => 'SALE-123', 'description' => 'Teste', 'occurredAt' => '2026-10-10T10:00:00-03:00',
    'items' => [['name' => 'Serviço A', 'quantity' => 2, 'unitPriceCents' => 3000],
        ['name' => 'Serviço B', 'quantity' => 1, 'unitPriceCents' => 4000]]];
$validated = $validate->invoke($service, $input);
if (count($validated['items']) !== 2 || $validated['items'][0]['totalCents'] !== 6000) {
    throw new RuntimeException('Itens e centavos não foram calculados corretamente.');
}
foreach ([
    ['grossAmountCents' => 10001],
    ['items' => [['name' => 'Inválido', 'quantity' => 0, 'unitPriceCents' => 10000]]],
    ['items' => [['name' => 'Inválido', 'quantity' => 1.5, 'unitPriceCents' => 10000]]],
] as $override) {
    try {
        $validate->invoke($service, array_replace($input, $override));
        throw new RuntimeException('Entrada inválida aceita.');
    } catch (StoreApiException $error) {
        if ($error->httpStatus !== 422) { throw $error; }
    }
}
echo "OK itens de venda: soma, centavos e validação\n";
