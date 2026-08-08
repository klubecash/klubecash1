<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$startedAt = microtime(true);
$databaseStatus = 'unavailable';
$httpStatus = 503;

try {
    require_once __DIR__ . '/../config/database.php';
    $db = Database::getConnection();
    $db->query('SELECT 1')->fetchColumn();
    $databaseStatus = 'ok';
    $httpStatus = 200;
} catch (Throwable $exception) {
    error_log(json_encode([
        'event' => 'health.database_failed',
        'request_id' => $_SERVER['HTTP_X_VERCEL_ID'] ?? null,
        'exception' => get_class($exception),
    ], JSON_UNESCAPED_SLASHES));
}

http_response_code($httpStatus);

echo json_encode([
    'status' => $httpStatus === 200 ? 'ok' : 'degraded',
    'version' => getenv('VERCEL_GIT_COMMIT_SHA') ?: 'local',
    'environment' => getenv('VERCEL_ENV') ?: 'local',
    'checks' => [
        'database' => $databaseStatus,
    ],
    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
], JSON_UNESCAPED_SLASHES);
