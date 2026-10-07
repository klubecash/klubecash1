<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/Giftback/GiftbackLedger.php';
try {
    $result = (new \App\Services\Giftback\GiftbackLedger(Database::getConnection()))->expireDue(100, in_array('--dry-run', $argv, true));
    echo json_encode($result, JSON_UNESCAPED_UNICODE), PHP_EOL;
    if ($result['failed'] > 0) { exit(1); }
} catch (Throwable $error) {
    fwrite(STDERR, 'giftback.expiration.failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
