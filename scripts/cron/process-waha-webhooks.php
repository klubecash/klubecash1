<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Services\WhatsApp\CurlWahaHttpClient;
use App\Services\WhatsApp\WahaConfig;
use App\Services\WhatsApp\WahaInboundProcessor;
use App\Services\WhatsApp\WahaService;
use App\Services\WhatsApp\WhatsAppMenuConfig;

$result = (new WahaInboundProcessor(
    Database::getConnection(),
    new WahaService(WahaConfig::fromEnvironment(), new CurlWahaHttpClient()),
    WhatsAppMenuConfig::fromEnvironment()
))->processPending(50);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
