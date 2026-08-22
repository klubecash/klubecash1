<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Services\WhatsApp\WahaInboundProcessor;

$result = (new WahaInboundProcessor(Database::getConnection()))->processPending(50);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
