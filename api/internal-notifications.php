<?php

declare(strict_types=1);

use App\Core\Logger;
use App\Services\Giftback\GiftbackExpiryNoticeService;
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

if (getenv('VPS_WORKERS_ENABLED') === 'false') {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Processador desativado neste ambiente.']);
    exit;
}

require_once __DIR__ . '/../services/Security/VpsRuntimeKeys.php';
$secret = \App\Services\Security\VpsRuntimeKeys::cronSecret();
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
    $webhook = getenv('WAHA_MANAGE_WEBHOOK') === 'false'
        ? ['managed' => false]
        : $waha->ensureWebhook($siteUrl . '/api/webhooks/waha');
    $outbound = (new StoreWhatsAppNotificationService($db))->processPending($limit);
    $menuConfig = WhatsAppMenuConfig::fromEnvironment();
    $menuService = new WhatsAppMenuService($db, $waha, $menuConfig);
    $inbound = (new WahaInboundProcessor($db, $waha, $menuConfig))->processPending($limit);
    $menuReplies = $menuConfig->menuEnabled
        ? $menuService->processPendingReplies($limit)
        : ['available' => 0, 'sent' => 0, 'pending' => 0, 'failed' => 0];

    // Avisos de vencimento de giftback: desligados por padrao e isolados, uma falha aqui
    // nao afeta as demais filas do WhatsApp.
    $expiryNotices = ['enabled' => false];
    if ($connection['available']) {
        try {
            require_once __DIR__ . '/../services/Giftback/GiftbackExpiryNoticeService.php';
            $expiryNotices = (new GiftbackExpiryNoticeService($db, $waha))->run(30);
        } catch (Throwable $exception) {
            Logger::error('giftback.notice.worker_failed', ['exception' => get_class($exception)]);
            $expiryNotices = ['enabled' => true, 'error' => 'notice_processor_failed'];
        }
    }

    // Avisos de sobra do Klube Pass: desligados por padrao; o e-mail vai para a fila mesmo sem WhatsApp.
    $passNotices = ['enabled' => false];
    try {
        require_once __DIR__ . '/../services/Partner/PassCreditNoticeService.php';
        $passNotices = (new \App\Services\Partner\PassCreditNoticeService($db, $connection['available'] ? $waha : null))->run(30);
    } catch (Throwable $exception) {
        Logger::error('pass.notice.worker_failed', ['exception' => get_class($exception)]);
        $passNotices = ['enabled' => true, 'error' => 'notice_processor_failed'];
    }

    http_response_code($connection['available'] ? 200 : 503);
    echo json_encode([
        'success' => $connection['available'],
        'connection' => $connection,
        'webhook' => $webhook,
        'outbound' => $outbound,
        'inbound' => $inbound,
        'menuReplies' => $menuReplies,
        'expiryNotices' => $expiryNotices,
        'passNotices' => $passNotices,
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
