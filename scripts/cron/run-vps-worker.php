<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$job = $argv[1] ?? '';
$paths = [
    'giftback-expiration' => '/api/internal/giftback-expiration?limit=100',
    'notifications' => '/api/internal/notifications?limit=50',
];
if (!isset($paths[$job])) {
    fwrite(STDERR, "Uso: php run-vps-worker.php giftback-expiration|notifications\n");
    exit(2);
}

// Scheduled tasks may be installed before the Vercel-to-VPS handoff.
if (getenv('VPS_WORKERS_ENABLED') !== 'true') {
    echo "Worker desativado neste ambiente.\n";
    exit(0);
}

$secret = trim((string) getenv('CRON_SECRET'));
if (strlen($secret) < 32) {
    fwrite(STDERR, "CRON_SECRET da VPS ausente ou curto demais.\n");
    exit(1);
}

$request = curl_init('http://legacy-http:8080' . $paths[$job]);
if ($request === false) {
    fwrite(STDERR, "Nao foi possivel iniciar a chamada interna.\n");
    exit(1);
}
curl_setopt_array($request, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $secret],
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 55,
]);
$response = curl_exec($request);
$status = (int) curl_getinfo($request, CURLINFO_RESPONSE_CODE);
curl_close($request);

$payload = is_string($response) ? json_decode($response, true) : null;
if ($status !== 200 || !is_array($payload) || ($payload['success'] ?? false) !== true) {
    // Never log the bearer secret or the response body: they may contain private data.
    fwrite(STDERR, "Worker {$job} falhou (HTTP {$status}).\n");
    exit(1);
}

echo "Worker {$job} concluido.\n";
