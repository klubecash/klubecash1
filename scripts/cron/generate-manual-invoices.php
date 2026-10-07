<?php

declare(strict_types=1);

/** Generates one pending invoice per manual billing cycle. */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../services/billing/BillingFeatureFlags.php';

use App\Services\Billing\BillingFeatureFlags;

if (!BillingFeatureFlags::transparentCheckoutEnabled()) { echo "manual billing disabled\n"; exit(0); }
$db = Database::getConnection();
if ((int) $db->query("SELECT GET_LOCK('klubecash_manual_invoice_generation', 1)")->fetchColumn() !== 1) { echo "already running\n"; exit(0); }

$created = 0; $blocked = 0; $failed = 0;
try {
    $rows = $db->query(
        "SELECT a.id,a.loja_id,a.ciclo,a.current_period_end,a.next_invoice_date,
                p.preco_mensal,p.preco_anual
         FROM assinaturas a
         INNER JOIN planos p ON p.id=a.plano_id
         WHERE a.billing_mode='manual'
           AND a.status NOT IN ('cancelada')
           AND a.next_invoice_date IS NOT NULL
           AND a.next_invoice_date <= CURDATE()
         ORDER BY a.next_invoice_date,a.id
         LIMIT 200"
    );
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $db->beginTransaction();
        try {
            $lock = $db->prepare('SELECT * FROM assinaturas WHERE id=:id LIMIT 1 FOR UPDATE');
            $lock->execute([':id' => (int) $row['id']]);
            $subscription = $lock->fetch(PDO::FETCH_ASSOC);
            if (!$subscription) { $db->rollBack(); $failed++; continue; }
            $cycle = (string) ($subscription['ciclo'] ?? 'monthly');
            $start = date('Y-m-d', strtotime(((string) $subscription['current_period_end']) . ' +1 day'));
            $end = $cycle === 'yearly' ? date('Y-m-d', strtotime($start . ' +1 year -1 day')) : date('Y-m-d', strtotime($start . ' +1 month -1 day'));
            $exists = $db->prepare('SELECT id FROM faturas WHERE assinatura_id=:subscription AND period_start=:start AND period_end=:end LIMIT 1 FOR UPDATE');
            $exists->execute([':subscription' => (int) $subscription['id'], ':start' => $start, ':end' => $end]);
            if (!$exists->fetchColumn()) {
                $amount = $cycle === 'yearly' ? (float) $row['preco_anual'] : (float) $row['preco_mensal'];
                $insert = $db->prepare(
                    "INSERT INTO faturas (assinatura_id,numero,amount,currency,status,due_date,period_start,period_end,period_key,gateway,created_at,updated_at)
                     VALUES (:subscription,:number,:amount,'BRL','pending',DATE_ADD(:start,INTERVAL 3 DAY),:start,:end,:period,'mercadopago',NOW(),NOW())"
                );
                $insert->execute([
                    ':subscription' => (int) $subscription['id'],
                    ':number' => 'INV-' . (int) $subscription['id'] . '-' . date('YmdHis'),
                    ':amount' => number_format($amount, 2, '.', ''),
                    ':start' => $start,
                    ':end' => $end,
                    ':period' => (int) $subscription['id'] . ':' . $start . ':' . $end,
                ]);
                $created++;
            }
            $today = new DateTimeImmutable('today');
            $due = new DateTimeImmutable($start . ' +3 days');
            $set = ['next_invoice_date=:next', 'updated_at=NOW()'];
            $params = [':next' => $start, ':id' => (int) $subscription['id']];
            if ($today > $due) {
                $set[] = "status='inadimplente'";
                if ((int) ($subscription['sales_blocked'] ?? 0) !== 0 || array_key_exists('sales_blocked', $subscription)) { $set[] = 'sales_blocked=1'; $blocked++; }
            }
            $db->prepare('UPDATE assinaturas SET ' . implode(',', $set) . ' WHERE id=:id')->execute($params);
            $db->commit();
        } catch (Throwable) {
            if ($db->inTransaction()) { $db->rollBack(); }
            $failed++;
        }
    }
} finally {
    $db->query("SELECT RELEASE_LOCK('klubecash_manual_invoice_generation')");
}
echo json_encode(['created' => $created, 'blocked' => $blocked, 'failed' => $failed], JSON_UNESCAPED_UNICODE) . PHP_EOL;
