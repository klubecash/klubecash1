<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/StoreWallet/StoreWalletSchema.php';
require_once __DIR__ . '/../../services/Giftback/GiftbackSchema.php';

try {
    $db = Database::getConnection();
    $apply = in_array('--apply', $argv, true);
    if ($apply) { \App\Services\Giftback\GiftbackSchema::assertReconciled($db); }
    $result = \App\Services\StoreWallet\StoreWalletSchema::migrate($db, $apply);
    if ($apply) { \App\Services\Giftback\GiftbackSchema::assertReconciled($db); $result['giftbackReconciled'] = true; }
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
