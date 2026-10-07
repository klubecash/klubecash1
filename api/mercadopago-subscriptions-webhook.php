<?php

declare(strict_types=1);

use App\Services\Billing\MercadoPagoSubscriptionClient;
use App\Services\Billing\SubscriptionService;
use App\Services\Billing\TransparentPaymentService;

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store');

require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../services/billing/BillingFeatureFlags.php';
require_once __DIR__ . '/../services/billing/MercadoPagoSubscriptionClient.php';
require_once __DIR__ . '/../services/billing/SubscriptionService.php';
require_once __DIR__ . '/../services/billing/TransparentPaymentService.php';

$raw = (string) file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) { http_response_code(400); echo json_encode(['status' => 'error']); exit; }

$secret = trim((string) (getenv('MP_WEBHOOK_SECRET') ?: (defined('MP_WEBHOOK_SECRET') ? MP_WEBHOOK_SECRET : '')));
$signature = (string) ($_SERVER['HTTP_X_SIGNATURE'] ?? '');
$requestId = (string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? '');
if ($secret !== '' && !mpSubscriptionSignatureValid($signature, $requestId, $payload, $secret)) {
    http_response_code(401); echo json_encode(['status' => 'error']); exit;
}

$eventType = strtolower((string) ($payload['type'] ?? $payload['action'] ?? ''));
$resourceId = (string) ($payload['data']['id'] ?? $payload['id'] ?? '');
$externalEventId = (string) ($payload['id'] ?? ($eventType . ':' . $resourceId . ':' . ($payload['date_created'] ?? '')));
if ($eventType === '' || $resourceId === '') { http_response_code(202); echo json_encode(['status' => 'ignored']); exit; }

try {
    $db = Database::getConnection();
    $table = (int) $db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='subscription_webhook_events'")->fetchColumn() > 0;
    if (!$table) { http_response_code(202); echo json_encode(['status' => 'pending_migration']); exit; }
    $hash = hash('sha256', $raw);
    $insert = $db->prepare('INSERT INTO subscription_webhook_events (gateway,event_type,external_id,resource_id,payload_hash) VALUES (\'mercadopago\',:type,:external,:resource,:hash)');
    try { $insert->execute([':type' => $eventType, ':external' => $externalEventId, ':resource' => $resourceId, ':hash' => $hash]); }
    catch (PDOException $exception) { if ((string) $exception->getCode() === '23000') { http_response_code(200); echo json_encode(['status' => 'replayed']); exit; } throw $exception; }

    $resource = [];
    if (str_contains($eventType, 'preapproval')) {
        $resource = (new MercadoPagoSubscriptionClient())->getSubscription($resourceId);
    } elseif (str_contains($eventType, 'payment')) {
        try {
            $resource = (new MercadoPagoSubscriptionClient())->getPayment($resourceId);
            if (str_starts_with(trim((string) ($resource['external_reference'] ?? '')), 'fatura:')) {
                $result = (new TransparentPaymentService($db))->reconcileExternalPayment($resourceId, $resource);
                $done = $db->prepare('UPDATE subscription_webhook_events SET processed_at=NOW() WHERE gateway=\'mercadopago\' AND external_id=:external');
                $done->execute([':external' => $externalEventId]);
                http_response_code(200); echo json_encode(['status' => 'success', 'data' => $result]);
                exit;
            }
        } catch (Throwable) {
            // Recurring/preapproval payment events use the legacy reconciler.
        }
        $resource = is_array($payload['data'] ?? null) ? $payload['data'] : ['id' => $resourceId, 'status' => (string) ($payload['status'] ?? '')];
    }
    $result = (new SubscriptionService($db))->reconcileMercadoPago($eventType, $resourceId, $resource);
    $done = $db->prepare('UPDATE subscription_webhook_events SET processed_at=NOW() WHERE gateway=\'mercadopago\' AND external_id=:external');
    $done->execute([':external' => $externalEventId]);
    http_response_code(200); echo json_encode(['status' => 'success', 'data' => $result]);
} catch (Throwable $exception) {
    error_log('billing.mercadopago_webhook_failed type=' . get_class($exception));
    http_response_code(202); echo json_encode(['status' => 'pending']);
}

function mpSubscriptionSignatureValid(string $header, string $requestId, array $payload, string $secret): bool
{
    $parts = [];
    foreach (explode(',', $header) as $part) {
        [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($key !== '') { $parts[$key] = $value; }
    }
    $timestamp = (string) ($parts['ts'] ?? '');
    $version = (string) ($parts['v1'] ?? '');
    $dataId = strtolower((string) ($payload['data']['id'] ?? $payload['id'] ?? ''));
    if ($timestamp === '' || $version === '' || $dataId === '') { return false; }
    if (abs(time() - (int) $timestamp) > 600) { return false; }
    $manifest = 'id:' . $dataId . ';request-id:' . $requestId . ';ts:' . $timestamp . ';';
    return hash_equals($version, hash_hmac('sha256', $manifest, $secret));
}
