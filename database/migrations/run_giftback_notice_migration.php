<?php

declare(strict_types=1);

// Cria somente a tabela cashback_credito_avisos. Nao altera saldos nem creditos existentes.
// Sem --apply apenas simula e informa se a tabela ja existe.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/Giftback/GiftbackExpiryNoticeService.php';

$apply = in_array('--apply', $argv, true);
try {
    $result = \App\Services\Giftback\GiftbackExpiryNoticeService::migrate(Database::getConnection(), $apply);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
