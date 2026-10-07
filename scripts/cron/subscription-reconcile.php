<?php

declare(strict_types=1);

/** Reconciles Mercado Pago subscription state outside page requests. */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../services/billing/BillingFeatureFlags.php';
require_once __DIR__ . '/../../services/billing/MercadoPagoSubscriptionClient.php';
require_once __DIR__ . '/../../services/billing/SubscriptionService.php';

use App\Services\Billing\BillingFeatureFlags;
use App\Services\Billing\MercadoPagoSubscriptionClient;
use App\Services\Billing\SubscriptionService;

if (!BillingFeatureFlags::mpEnabled()) { echo "billing disabled\n"; exit(0); }
$db = Database::getConnection();
if ((int) $db->query("SELECT GET_LOCK('klubecash_subscription_reconcile', 1)")->fetchColumn() !== 1) { echo "already running\n"; exit(0); }
try {
    $stmt = $db->query("SELECT id,gateway_subscription_id FROM assinaturas WHERE gateway='mercadopago' AND gateway_subscription_id IS NOT NULL AND status NOT IN ('cancelada') ORDER BY updated_at ASC LIMIT 100");
    $client = new MercadoPagoSubscriptionClient();
    $service = new SubscriptionService($db);
    $processed = 0; $failed = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        try { $resource = $client->getSubscription((string) $row['gateway_subscription_id']); $service->reconcileMercadoPago('subscription_preapproval', (string) $row['gateway_subscription_id'], $resource); $processed++; }
        catch (Throwable) { $failed++; }
    }
    echo json_encode(['processed' => $processed, 'failed' => $failed], JSON_UNESCAPED_UNICODE) . PHP_EOL;
} finally { $db->query("SELECT RELEASE_LOCK('klubecash_subscription_reconcile')"); }
