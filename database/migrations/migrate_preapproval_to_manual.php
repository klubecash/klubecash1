<?php

declare(strict_types=1);

/**
 * Migrates existing Mercado Pago preapproval subscriptions to manual billing.
 * The default is a read-only report. External cancellation only happens with
 * both --apply and --confirm.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../services/billing/MercadoPagoSubscriptionClient.php';

use App\Services\Billing\MercadoPagoSubscriptionClient;

$apply = in_array('--apply', $argv ?? [], true) && in_array('--confirm', $argv ?? [], true);
$db = Database::getConnection();
$hasColumn = static function (string $table, string $column) use ($db): bool {
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND COLUMN_NAME=:column');
    $stmt->execute([':table' => $table, ':column' => $column]);
    return (int) $stmt->fetchColumn() > 0;
};
$hasTable = static function (string $table) use ($db): bool {
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table');
    $stmt->execute([':table' => $table]);
    return (int) $stmt->fetchColumn() > 0;
};

if (!$hasColumn('assinaturas', 'billing_mode')) {
    fwrite(STDERR, "Execute run_transparent_checkout_migration.php --apply primeiro.\n");
    exit(2);
}

$rows = $db->query("SELECT a.id,a.loja_id,a.status,a.current_period_start,a.current_period_end,a.next_invoice_date,a.gateway_subscription_id,a.billing_mode,a.ciclo,p.preco_mensal,p.preco_anual FROM assinaturas a LEFT JOIN planos p ON p.id=a.plano_id WHERE a.gateway='mercadopago' AND a.gateway_subscription_id IS NOT NULL AND (a.billing_mode IS NULL OR a.billing_mode='preapproval_legacy') ORDER BY a.id")->fetchAll(PDO::FETCH_ASSOC);
$client = new MercadoPagoSubscriptionClient();
$report = [];
foreach ($rows as $row) {
    $externalId = (string) $row['gateway_subscription_id'];
    $remote = [];
    $remoteError = null;
    try { $remote = $client->getSubscription($externalId); } catch (Throwable $exception) { $remoteError = 'Mercado Pago indisponível'; }
    $remoteStatus = strtolower((string) ($remote['status'] ?? 'unknown'));
    $item = [
        'subscriptionId' => (int) $row['id'],
        'storeId' => (int) $row['loja_id'],
        'externalId' => $externalId,
        'remoteStatus' => $remoteStatus,
        'localStatus' => (string) $row['status'],
        'periodEnd' => $row['current_period_end'],
        'action' => $remoteError ? 'review' : ($remoteStatus === 'cancelled' || $remoteStatus === 'canceled' ? 'mark_manual' : 'cancel_remote_then_mark_manual'),
        'error' => $remoteError,
    ];
    if ($apply && $remoteError === null) {
        $db->beginTransaction();
        try {
            $lock = $db->prepare('SELECT * FROM assinaturas WHERE id=:id LIMIT 1 FOR UPDATE');
            $lock->execute([':id' => (int) $row['id']]);
            $before = $lock->fetch(PDO::FETCH_ASSOC);
            if (!$before || (string) ($before['billing_mode'] ?? '') === 'manual') { $db->commit(); $report[] = $item + ['applied' => false]; continue; }
            if (!in_array($remoteStatus, ['cancelled', 'canceled'], true)) {
                $client->updateSubscription($externalId, ['status' => 'cancelled'], 'migrate-manual-' . (int) $row['id']);
            }
            $set = ['billing_mode=\'manual\'', 'updated_at=NOW()'];
            $params = [':id' => (int) $row['id']];
            if ($hasColumn('assinaturas', 'sales_blocked') && (string) ($before['current_period_end'] ?? '') < date('Y-m-d')) { $set[] = 'sales_blocked=1'; }
            $db->prepare('UPDATE assinaturas SET ' . implode(',', $set) . ' WHERE id=:id')->execute($params);
            if ($hasColumn('faturas', 'period_start') && $hasColumn('faturas', 'period_end')) {
                $pending = $db->prepare("SELECT id FROM faturas WHERE assinatura_id=:subscription AND status='pending' LIMIT 1 FOR UPDATE");
                $pending->execute([':subscription' => (int) $row['id']]);
                if (!$pending->fetchColumn() && in_array((string) ($before['status'] ?? ''), ['pendente', 'inadimplente'], true)) {
                    $periodStart = (string) ($before['current_period_start'] ?? date('Y-m-d'));
                    $periodEnd = (string) ($before['current_period_end'] ?? date('Y-m-d'));
                    $amount = (string) (($row['ciclo'] ?? 'monthly') === 'yearly' ? ($row['preco_anual'] ?? 0) : ($row['preco_mensal'] ?? 0));
                    $insertInvoice = $db->prepare("INSERT INTO faturas (assinatura_id,numero,amount,currency,status,due_date,period_start,period_end,period_key,gateway,created_at,updated_at) VALUES (:subscription,:number,:amount,'BRL','pending',DATE_ADD(CURDATE(),INTERVAL 3 DAY),:period_start,:period_end,:period_key,'mercadopago',NOW(),NOW())");
                    $insertInvoice->execute([
                        ':subscription' => (int) $row['id'],
                        ':number' => 'INV-' . (int) $row['id'] . '-' . date('YmdHis'),
                        ':amount' => number_format((float) $amount, 2, '.', ''),
                        ':period_start' => $periodStart,
                        ':period_end' => $periodEnd,
                        ':period_key' => (int) $row['id'] . ':' . $periodStart . ':' . $periodEnd,
                    ]);
                }
            }
            if ($hasTable('subscription_audit_logs')) {
                $audit = $db->prepare('INSERT INTO subscription_audit_logs (subscription_id,actor_id,action,before_json,after_json,request_id) VALUES (:subscription,NULL,\'billing.migrate_to_manual\',:before,:after,:request)');
                $audit->execute([
                    ':subscription' => (int) $row['id'],
                    ':before' => json_encode(['billingMode' => $before['billing_mode'] ?? null, 'remoteStatus' => $remoteStatus], JSON_UNESCAPED_UNICODE),
                    ':after' => json_encode(['billingMode' => 'manual', 'externalCancelled' => !in_array($remoteStatus, ['cancelled', 'canceled'], true)], JSON_UNESCAPED_UNICODE),
                    ':request' => 'migration-' . bin2hex(random_bytes(8)),
                ]);
            }
            $db->commit();
            $item['applied'] = true;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) { $db->rollBack(); }
            $item['applied'] = false;
            $item['error'] = 'Falha ao migrar assinatura';
        }
    }
    $report[] = $item;
}

echo json_encode(['mode' => $apply ? 'applied' : 'dry-run', 'total' => count($report), 'items' => $report], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
