<?php

declare(strict_types=1);

// Integração do Klube Pass: simula por padrão; --apply grava. Requer a migração
// de giftback aplicada antes. --deactivate-legacy-wildcard-keys desativa chaves
// antigas de api_keys com permissão "*" (a kc_live do dump), só quando pedido.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/Partner/PassIntegrationSchema.php';

$args = $argv ?? [];
try {
    $result = \App\Services\Partner\PassIntegrationSchema::migrate(
        Database::getConnection(),
        in_array('--apply', $args, true),
        in_array('--deactivate-legacy-wildcard-keys', $args, true)
    );
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
