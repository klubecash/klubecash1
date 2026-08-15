<?php

declare(strict_types=1);

$api = static fn (
    string $path,
    string $target,
    string $name,
    array $methods,
    array $middleware = ['api']
): array => compact('methods', 'path', 'target', 'name', 'middleware');

return [
    $api('/api/health', 'api/health.php', 'api.health', ['GET']),
    $api('/api/homepage-context', 'api/homepage-context.php', 'api.homepage-context', ['GET']),
    $api('/api/store-client-search', 'api/store-client-search.php', 'api.store-client-search', ['GET', 'POST', 'OPTIONS'], ['api', 'store']),
    $api('/api/mercadopago', 'api/mercadopago.php', 'api.mercadopago', ['GET', 'POST', 'OPTIONS'], ['api', 'store']),
    $api('/api/mercadopago-webhook', 'api/mercadopago-webhook.php', 'api.mercadopago.webhook', ['POST']),
    $api('/api/store-payment', 'api/store-payment.php', 'api.store-payment', ['POST', 'OPTIONS'], ['api', 'store']),
    $api('/api/payment-receipt', 'api/payment-receipt.php', 'api.payment-receipt', ['GET'], ['api', 'auth']),
    $api('/api/employees', 'api/employees.php', 'api.employees', ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'], ['api', 'store']),
    $api('/api/openpix', 'api/openpix.php', 'api.openpix', ['POST', 'OPTIONS'], ['api', 'store']),
    $api('/api/abacatepay', 'api/abacatepay.php', 'api.abacatepay', ['GET', 'POST', 'OPTIONS'], ['api', 'store']),
    $api('/api/abacatepay-webhook', 'api/abacatepay-webhook.php', 'api.abacatepay.webhook', ['POST']),
    $api('/api/search-stores', 'api/search-stores.php', 'api.search-stores', ['GET'], ['api', 'auth']),
    $api('/api/users', 'api/users.php', 'api.users', ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'], ['api', 'admin']),
    $api('/api/users/{id:\\d+}', 'api/users.php', 'api.users.show', ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'], ['api', 'admin']),
    $api('/api/transactions', 'api/transactions.php', 'api.transactions', ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'], ['api', 'auth']),
    $api('/api/store-transactions', 'api/store-transactions.php', 'api.store-transactions', ['POST'], ['api', 'auth', 'store']),
    $api('/api/store-details', 'api/store-details.php', 'api.store-details', ['POST'], ['api', 'auth', 'store']),
    $api('/api/balance', 'api/balance.php', 'api.balance', ['GET', 'POST', 'OPTIONS'], ['api', 'auth']),
    $api('/api/commissions', 'api/commissions.php', 'api.commissions', ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'], ['api', 'admin']),
    $api('/api/funcionarios', 'api/funcionarios.php', 'api.funcionarios', ['GET', 'POST', 'OPTIONS'], ['api', 'store']),
    $api('/api/get-store-id', 'api/get-store-id.php', 'api.store-id', ['GET'], ['api', 'auth']),
    $api('/api/payments', 'api/payments.php', 'api.payments', ['POST', 'OPTIONS'], ['api', 'store']),
    $api('/api/store_details', 'api/store_details.php', 'api.store-details.legacy', ['GET'], ['api']),
    $api('/api/stores', 'api/stores.php', 'api.stores', ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'], ['api', 'admin']),
    $api('/api/stripe', 'api/stripe.php', 'api.stripe', ['GET', 'POST', 'OPTIONS'], ['api', 'store']),
    $api('/api/stripe-webhook', 'api/stripe-webhook.php', 'api.stripe.webhook', ['POST']),
];
