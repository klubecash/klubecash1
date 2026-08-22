<?php

declare(strict_types=1);

use App\Core\Logger;
use App\Services\Store\StoreWhatsAppNotificationService;
use App\Services\WhatsApp\CurlWahaHttpClient;
use App\Services\WhatsApp\WahaConfig;
use App\Services\WhatsApp\WahaInboundProcessor;
use App\Services\WhatsApp\WahaSchemaManager;
use App\Services\WhatsApp\WahaService;
use App\Services\WhatsApp\WhatsAppMenuConfig;
use App\Services\WhatsApp\WhatsAppMenuService;

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store');

$secret = trim((string) getenv('CRON_SECRET'));
$authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
if ($secret === '') {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Processador interno nao configurado.']);
    exit;
}
if (!hash_equals('Bearer ' . $secret, $authorization)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nao autorizado.']);
    exit;
}

require_once __DIR__ . '/../services/store/StoreWhatsAppNotificationService.php';

try {
    $db = Database::getConnection();
    (new WahaSchemaManager($db))->migrate();

    $limit = max(1, min(100, (int) ($_GET['limit'] ?? 100)));

    $waha = new WahaService(WahaConfig::fromEnvironment(), new CurlWahaHttpClient());
    $connection = $waha->connectionStatus();
    $siteUrl = rtrim((string) (getenv('SITE_URL') ?: (defined('SITE_URL') ? SITE_URL : 'https://www.klubecash.com')), '/');
    $webhook = $waha->ensureWebhook($siteUrl . '/api/webhooks/waha');
    $outbound = (new StoreWhatsAppNotificationService($db))->processPending($limit);
    $menuConfig = WhatsAppMenuConfig::fromEnvironment();
    $menuService = new WhatsAppMenuService($db, $waha, $menuConfig);
    $inbound = (new WahaInboundProcessor($db, $waha, $menuConfig))->processPending($limit);
    $menuReplies = $menuConfig->menuEnabled
        ? $menuService->processPendingReplies($limit)
        : ['available' => 0, 'sent' => 0, 'pending' => 0, 'failed' => 0];

    http_response_code($connection['available'] ? 200 : 503);
    echo json_encode([
        'success' => $connection['available'],
        'connection' => $connection,
        'webhook' => $webhook,
        'outbound' => $outbound,
        'inbound' => $inbound,
        'menuReplies' => $menuReplies,
        'processedAt' => date(DATE_ATOM),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    Logger::error('waha.internal_processor.failed', ['exception' => get_class($exception)]);
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'error' => 'Nao foi possivel processar as notificacoes do WhatsApp.',
        'processedAt' => date(DATE_ATOM),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
