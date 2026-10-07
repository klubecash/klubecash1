<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/Giftback/GiftbackSchema.php';

$apply = in_array('--apply', $argv, true);
if ($apply && !in_array('--acknowledge-writes-paused', $argv, true)) {
    fwrite(STDERR, "Suspenda os escritores financeiros e use --acknowledge-writes-paused para aplicar.\n");
    exit(2);
}
try {
    $result = \App\Services\Giftback\GiftbackSchema::migrate(Database::getConnection(), $apply);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
