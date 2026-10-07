<?php

declare(strict_types=1);

namespace App\Services\Billing;

use DateTimeImmutable;
use PDO;
use RuntimeException;

final class SubscriptionService
{
    /** @var array<string, bool> */
    private array $columns = [];

    public function __construct(private PDO $db)
    {
    }

    /** @return array<string, mixed> */
    public function context(int $storeId): array
    {
        $subscription = $this->findLatest($storeId);
        $plans = $this->db->query(
            'SELECT id,nome,slug,preco_mensal,preco_anual,trial_dias,recorrencia,features_json FROM planos WHERE ativo=1 ORDER BY preco_mensal,id'
        )->fetchAll(PDO::FETCH_ASSOC);
        $access = $this->commercialAccess($storeId, $subscription);
        return [
            'dataState' => $subscription ? 'ready' : 'empty',
            'generatedAt' => date(DATE_ATOM),
            'checkout' => [
                'mode' => BillingFeatureFlags::checkoutMode(),
                'manualBilling' => BillingFeatureFlags::transparentCheckoutEnabled(),
                'publicKey' => defined('MP_PUBLIC_KEY') ? (string) MP_PUBLIC_KEY : '',
            ],
            'subscription' => $subscription ? $this->subscriptionRow($subscription, $access) : null,
            'invoices' => $subscription ? $this->invoices((int) $subscription['id']) : [],
            'salesAccess' => $access,
            'plans' => array_map(fn (array $plan): array => [
                'id' => (int) $plan['id'],
                'name' => (string) $plan['nome'],
                'slug' => (string) $plan['slug'],
                'monthlyPriceCents' => $this->cents($plan['preco_mensal'] ?? 0),
                'annualPriceCents' => $this->cents($plan['preco_anual'] ?? 0),
                'trialDays' => (int) ($plan['trial_dias'] ?? 0),
                'recurrence' => (string) ($plan['recorrencia'] ?? 'monthly'),
                'features' => $this->features($plan['features_json'] ?? null),
            ], $plans),
        ];
    }

    /** @return array<string, mixed> */
    public function commercialAccess(int $storeId, ?array $subscription = null): array
    {
        $subscription ??= $this->findLatest($storeId);
        if (!$subscription) {
            return ['canRegisterSales' => false, 'salesBlocked' => true, 'reason' => 'no_subscription', 'status' => null];
        }
        $status = (string) ($subscription['status'] ?? '');
        $active = in_array($status, ['trial', 'ativa', 'active'], true);
        $periodEnd = (string) ($subscription['current_period_end'] ?? '');
        if ($active && $periodEnd !== '' && strtotime($periodEnd . ' 23:59:59') < time()) {
            $status = 'inadimplente';
            // Manual billing keeps read access and sales enabled during the
            // configured three-day grace period. Once the pending invoice is
            // overdue, the commercial gate is closed.
            $active = BillingFeatureFlags::transparentCheckoutEnabled() && $this->withinGracePeriod($subscription);
        }
        // A Mercado Pago subscription cannot be considered commercially active
        // merely because a client-side action changed its local status. It must
        // have a paid invoice in the current cycle, unless an audited temporary
        // administrative grant is still valid.
        if ($active && !BillingFeatureFlags::transparentCheckoutEnabled() && strtolower((string) ($subscription['gateway'] ?? '')) === 'mercadopago' && $status === 'ativa') {
            $manualUntil = (string) ($subscription['manual_activation_until'] ?? '');
            $manualGrant = $manualUntil !== '' && strtotime($manualUntil) >= time();
            if (!$manualGrant && !$this->hasPaidInvoiceForCurrentCycle($subscription)) {
                $active = false;
                $status = 'pendente';
            }
        }
        if ($this->hasColumn('assinaturas', 'sales_blocked') && (int) ($subscription['sales_blocked'] ?? 0) === 1) {
            $active = false;
        }
        $reason = $active ? null : ((string) ($subscription['sales_block_reason'] ?? '') ?: $this->reasonForStatus($status));
        return ['canRegisterSales' => $active, 'salesBlocked' => !$active, 'reason' => $reason, 'status' => $status];
    }

    public function assertCanRegisterSales(int $storeId): void
    {
        if (!BillingFeatureFlags::commercialSalesGateEnabled()) {
            return;
        }
        $access = $this->commercialAccess($storeId);
        if (!$access['canRegisterSales']) {
            throw new \App\Services\Store\StoreApiException('As novas vendas estão bloqueadas porque a assinatura da loja não está liberada. Consulte o plano para regularizar o acesso.', 402);
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function invoices(int $subscriptionId): array
    {
        $columns = 'id,numero,amount,status,due_date,paid_at,payment_method,gateway,gateway_invoice_id,gateway_charge_id,created_at,updated_at';
        foreach (['period_start', 'period_end', 'payment_url', 'failure_code', 'attempts', 'idempotency_key', 'payment_status_detail', 'payment_type', 'period_key'] as $column) {
            if ($this->hasColumn('faturas', $column)) {
                $columns .= ',' . $column;
            }
        }
        $stmt = $this->db->prepare("SELECT {$columns} FROM faturas WHERE assinatura_id=:id ORDER BY created_at DESC,id DESC");
        $stmt->execute([':id' => $subscriptionId]);
        return array_map(fn (array $row): array => [
            'id' => (int) $row['id'],
            'number' => (string) ($row['numero'] ?? ''),
            'amountCents' => $this->cents($row['amount'] ?? 0),
            'status' => (string) ($row['status'] ?? 'pending'),
            'dueDate' => $this->iso($row['due_date'] ?? null),
            'paidAt' => $this->iso($row['paid_at'] ?? null),
            'paymentMethod' => $row['payment_method'] ?? null,
            'gateway' => $row['gateway'] ?? null,
            'paymentUrl' => $row['payment_url'] ?? null,
            'failureCode' => $row['failure_code'] ?? null,
            'paymentStatusDetail' => $row['payment_status_detail'] ?? null,
            'paymentType' => $row['payment_type'] ?? null,
            'periodKey' => $row['period_key'] ?? null,
            'attempts' => isset($row['attempts']) ? (int) $row['attempts'] : 0,
            'createdAt' => $this->iso($row['created_at'] ?? null),
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function manualAction(int $subscriptionId, string $action, int $actorId, string $reason, ?string $until = null, ?string $expectedUpdatedAt = null, bool $requirePaidInvoice = false): array
    {
        if (!in_array($action, ['activate', 'block', 'unblock', 'pause', 'cancel', 'reactivate'], true)) {
            throw new RuntimeException('Ação de assinatura inválida.');
        }
        if (in_array($action, ['activate', 'block', 'pause', 'cancel'], true) && trim($reason) === '') {
            throw new RuntimeException('Informe o motivo da ação administrativa.');
        }
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT * FROM assinaturas WHERE id=:id LIMIT 1 FOR UPDATE');
            $stmt->execute([':id' => $subscriptionId]);
            $before = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$before) {
                throw new RuntimeException('Assinatura não encontrada.');
            }
            if ($expectedUpdatedAt !== null && $expectedUpdatedAt !== '' && strtotime((string) $before['updated_at']) !== strtotime($expectedUpdatedAt)) {
                throw new RuntimeException('A assinatura foi alterada em outra sessão. Atualize a página.');
            }
            // A conta da loja nunca pode ser reativada apenas pela interface:
            // o desbloqueio comercial precisa de uma fatura do ciclo atual paga
            // e confirmada pelo gateway. Ações administrativas continuam usando
            // o parâmetro padrão false e exigem a auditoria própria do admin.
            if ($action === 'reactivate' && $requirePaidInvoice) {
                $paidAfter = (string) ($before['current_period_start'] ?? $before['created_at'] ?? '1970-01-01 00:00:00');
                $paid = $this->db->prepare(
                    "SELECT id FROM faturas
                     WHERE assinatura_id=:subscription AND status='paid'
                       AND paid_at IS NOT NULL AND paid_at >= :paid_after
                     ORDER BY paid_at DESC LIMIT 1 FOR UPDATE"
                );
                $paid->execute([':subscription' => $subscriptionId, ':paid_after' => $paidAfter]);
                if (!$paid->fetchColumn()) {
                    throw new RuntimeException('A reativação só é permitida após a confirmação do pagamento. Use Pagar com PIX ou conclua o checkout da assinatura.');
                }
            }
            $status = match ($action) {
                'activate', 'unblock', 'reactivate' => 'ativa',
                'cancel' => 'cancelada',
                default => 'suspensa',
            };
            $set = ['status=:status', 'updated_at=NOW()'];
            $params = [':status' => $status, ':id' => $subscriptionId];
            if ($action === 'cancel') { $set[] = 'cancel_at=CURDATE()'; $set[] = 'canceled_at=NOW()'; }
            if ($this->hasColumn('assinaturas', 'sales_blocked')) {
                $set[] = 'sales_blocked=:sales_blocked';
                $params[':sales_blocked'] = in_array($action, ['block', 'pause', 'cancel'], true) ? 1 : 0;
            }
            if ($this->hasColumn('assinaturas', 'sales_block_reason')) {
                $set[] = 'sales_block_reason=:reason'; $params[':reason'] = trim($reason) !== '' ? trim($reason) : null;
            }
            if ($this->hasColumn('assinaturas', 'manual_activation_until')) {
                $set[] = 'manual_activation_until=:until'; $params[':until'] = $until ?: null;
            }
            $update = $this->db->prepare('UPDATE assinaturas SET ' . implode(',', $set) . ' WHERE id=:id');
            $update->execute($params);
            $after = ['id' => $subscriptionId, 'status' => $status, 'action' => $action, 'reason' => trim($reason), 'actorId' => $actorId];
            $this->writeAudit($subscriptionId, $actorId, 'subscription.' . $action, $before, $after);
            $this->db->commit();
            return $after;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function recoveryCheckout(int $storeId, int $invoiceId, string $idempotencyKey): array
    {
        if (!BillingFeatureFlags::mpEnabled()) {
            throw new RuntimeException('O pagamento online de assinaturas ainda está em preparação.');
        }
        $stmt = $this->db->prepare(
            'SELECT f.*,a.loja_id,p.nome plan_name FROM faturas f JOIN assinaturas a ON a.id=f.assinatura_id JOIN planos p ON p.id=a.plano_id WHERE f.id=:invoice AND a.loja_id=:store LIMIT 1'
        );
        $stmt->execute([':invoice' => $invoiceId, ':store' => $storeId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$invoice || (string) $invoice['status'] !== 'pending') { throw new RuntimeException('Fatura pendente não encontrada.'); }
        $client = new MercadoPagoSubscriptionClient();
        $preference = $client->createRecoveryPreference([
            'items' => [[ 'title' => 'Assinatura ' . (string) $invoice['plan_name'], 'quantity' => 1, 'currency_id' => 'BRL', 'unit_price' => (float) $invoice['amount'] ]],
            'external_reference' => 'fatura:' . (int) $invoice['id'],
            'notification_url' => defined('SITE_URL') ? rtrim((string) SITE_URL, '/') . '/api/mercadopago-subscriptions-webhook' : null,
        ], $idempotencyKey);
        if ($this->hasColumn('faturas', 'payment_url')) {
            $update = $this->db->prepare('UPDATE faturas SET payment_url=:url,gateway=:gateway,updated_at=NOW() WHERE id=:id');
            $update->execute([':url' => (string) ($preference['init_point'] ?? $preference['sandbox_init_point'] ?? ''), ':gateway' => 'mercadopago', ':id' => $invoiceId]);
        }
        return ['invoiceId' => $invoiceId, 'checkoutUrl' => (string) ($preference['init_point'] ?? $preference['sandbox_init_point'] ?? ''), 'preferenceId' => (string) ($preference['id'] ?? '')];
    }

    /**
     * Creates the first recovery invoice for a pending subscription when the
     * preapproval link was abandoned before a fatura existed. The invoice is
     * created once and its Checkout Pro URL is reused on retries, so the PIX
     * action cannot create duplicate invoices or preferences.
     *
     * @return array<string, mixed>
     */
    public function initialCheckout(int $storeId, string $idempotencyKey): array
    {
        if (!BillingFeatureFlags::mpEnabled()) {
            throw new RuntimeException('O pagamento online de assinaturas ainda está em preparação.');
        }
        if (trim($idempotencyKey) === '') {
            throw new RuntimeException('A chave de idempotência é obrigatória.');
        }

        $stmt = $this->db->prepare(
            "SELECT a.id,a.ciclo,p.nome AS plan_name,p.preco_mensal,p.preco_anual
             FROM assinaturas a JOIN planos p ON p.id=a.plano_id
             WHERE a.loja_id=:store AND a.gateway='mercadopago'
               AND a.status IN ('pendente','inadimplente')
             ORDER BY a.updated_at DESC,a.id DESC LIMIT 1"
        );
        $stmt->execute([':store' => $storeId]);
        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$subscription) {
            throw new RuntimeException('Nenhuma assinatura aguardando pagamento foi encontrada.');
        }

        $subscriptionId = (int) $subscription['id'];
        $invoiceId = 0;
        $existingUrl = '';
        $this->db->beginTransaction();
        try {
            $invoiceStmt = $this->db->prepare(
                'SELECT id,payment_url FROM faturas WHERE assinatura_id=:subscription AND status=\'pending\' ORDER BY id DESC LIMIT 1 FOR UPDATE'
            );
            $invoiceStmt->execute([':subscription' => $subscriptionId]);
            $invoice = $invoiceStmt->fetch(PDO::FETCH_ASSOC);
            if ($invoice) {
                $invoiceId = (int) $invoice['id'];
                $existingUrl = trim((string) ($invoice['payment_url'] ?? ''));
            } else {
                $cycle = (string) ($subscription['ciclo'] ?? 'monthly');
                $amount = $cycle === 'yearly' ? (float) $subscription['preco_anual'] : (float) $subscription['preco_mensal'];
                $insert = $this->db->prepare(
                    "INSERT INTO faturas (assinatura_id,numero,amount,currency,status,due_date,gateway,created_at,updated_at)
                     VALUES (:subscription,:number,:amount,'BRL','pending',DATE_ADD(CURDATE(),INTERVAL 3 DAY),'mercadopago',NOW(),NOW())"
                );
                $insert->execute([
                    ':subscription' => $subscriptionId,
                    ':number' => 'INV-' . $subscriptionId . '-' . date('YmdHis'),
                    ':amount' => number_format($amount, 2, '.', ''),
                ]);
                $invoiceId = (int) $this->db->lastInsertId();
            }
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $exception;
        }

        if ($existingUrl !== '') {
            return ['invoiceId' => $invoiceId, 'checkoutUrl' => $existingUrl, 'replayed' => true];
        }

        $result = $this->recoveryCheckout($storeId, $invoiceId, $idempotencyKey);
        return $result + ['invoiceId' => $invoiceId];
    }

    /** @return array<string, mixed> */
    public function startSubscription(int $storeId, string $planSlug, string $cycle, string $idempotencyKey): array
    {
        if (BillingFeatureFlags::transparentCheckoutEnabled()) {
            return $this->startManualSubscription($storeId, $planSlug, $cycle, $idempotencyKey);
        }
        if (!BillingFeatureFlags::mpEnabled()) { throw new RuntimeException('O pagamento de assinaturas ainda está desativado para esta implantação.'); }
        if (!in_array($cycle, ['monthly', 'yearly'], true)) { throw new RuntimeException('Ciclo de assinatura inválido.'); }
        if (!$this->gatewaySupportsMercadoPago() || !$this->hasColumn('assinaturas', 'sales_blocked') || !$this->tableExists('subscription_idempotency_keys')) { throw new RuntimeException('A migration financeira ainda não foi aplicada.'); }
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') { throw new RuntimeException('A chave de idempotência é obrigatória.'); }
        $requestHash = hash('sha256', json_encode(['storeId' => $storeId, 'planSlug' => $planSlug, 'cycle' => $cycle], JSON_UNESCAPED_SLASHES));
        $existing = $this->db->prepare("SELECT request_hash,status,response_json FROM subscription_idempotency_keys WHERE store_id=:store AND scope='subscription_start' AND idempotency_key=:key AND expires_at>NOW() LIMIT 1"); $existing->execute([':store' => $storeId, ':key' => $idempotencyKey]); $replay = $existing->fetch(PDO::FETCH_ASSOC);
        if ($replay) { if (!hash_equals((string) $replay['request_hash'], $requestHash)) { throw new RuntimeException('A chave de idempotência já foi usada para outra assinatura.'); } if ($replay['status'] === 'completed') { return (array) json_decode((string) $replay['response_json'], true) + ['replayed' => true]; } }
        $reserve = $this->db->prepare("INSERT INTO subscription_idempotency_keys (store_id,scope,idempotency_key,request_hash,status,expires_at) VALUES (:store,'subscription_start',:key,:hash,'processing',DATE_ADD(NOW(),INTERVAL 24 HOUR)) ON DUPLICATE KEY UPDATE request_hash=VALUES(request_hash)"); $reserve->execute([':store' => $storeId, ':key' => $idempotencyKey, ':hash' => $requestHash]);
        $planStmt = $this->db->prepare('SELECT * FROM planos WHERE slug=:slug AND ativo=1 LIMIT 1'); $planStmt->execute([':slug' => $planSlug]); $plan = $planStmt->fetch(PDO::FETCH_ASSOC);
        if (!$plan) { throw new RuntimeException('Plano não encontrado.'); }
        $storeStmt = $this->db->prepare('SELECT l.id,l.usuario_id,l.nome_fantasia,u.email FROM lojas l LEFT JOIN usuarios u ON u.id=l.usuario_id WHERE l.id=:id AND l.status=\'aprovado\' LIMIT 1'); $storeStmt->execute([':id' => $storeId]); $store = $storeStmt->fetch(PDO::FETCH_ASSOC);
        if (!$store || !filter_var((string) ($store['email'] ?? ''), FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('Não foi possível identificar o e-mail da conta da loja.'); }
        $price = $cycle === 'yearly' ? (float) $plan['preco_anual'] : (float) $plan['preco_mensal'];
        $periodEnd = $cycle === 'yearly' ? date('Y-m-d', strtotime('+1 year')) : date('Y-m-d', strtotime('+1 month'));
        $this->db->beginTransaction();
        try {
            $insert = $this->db->prepare("INSERT INTO assinaturas (tipo,loja_id,plano_id,status,ciclo,current_period_start,current_period_end,next_invoice_date,gateway,sales_blocked,sales_block_reason) VALUES ('loja',:store,:plan,'pendente',:cycle,:period_start,:period_end,:next_invoice,:gateway,1,'pending_payment')");
            $insert->execute([
                ':store' => $storeId,
                ':plan' => (int) $plan['id'],
                ':cycle' => $cycle,
                ':period_start' => date('Y-m-d'),
                ':period_end' => $periodEnd,
                ':next_invoice' => $periodEnd,
                ':gateway' => 'mercadopago',
            ]);
            $subscriptionId = (int) $this->db->lastInsertId();
            $client = new MercadoPagoSubscriptionClient();
            $external = $client->createSubscription([
                'reason' => 'Assinatura ' . (string) $plan['nome'] . ' - KlubeCash',
                'external_reference' => 'assinatura:' . $subscriptionId,
                'payer_email' => (string) $store['email'],
                'auto_recurring' => ['frequency' => $cycle === 'yearly' ? 12 : 1, 'frequency_type' => 'months', 'transaction_amount' => $price, 'currency_id' => 'BRL'],
                'back_url' => defined('SITE_URL') ? rtrim((string) SITE_URL, '/') . '/store/meu-plano' : null,
                'notification_url' => defined('MP_SUBSCRIPTIONS_WEBHOOK_URL') ? MP_SUBSCRIPTIONS_WEBHOOK_URL : null,
                'status' => 'pending',
            ], $idempotencyKey);
            $externalId = (string) ($external['id'] ?? '');
            if ($externalId === '') { throw new RuntimeException('Mercado Pago não retornou o identificador da assinatura.'); }
            $update = $this->db->prepare('UPDATE assinaturas SET gateway_subscription_id=:external,updated_at=NOW() WHERE id=:id'); $update->execute([':external' => $externalId, ':id' => $subscriptionId]);
            $this->db->commit();
            $response = ['id' => $subscriptionId, 'externalId' => $externalId, 'status' => 'pendente', 'checkoutUrl' => (string) ($external['init_point'] ?? $external['sandbox_init_point'] ?? '')];
            $complete = $this->db->prepare("UPDATE subscription_idempotency_keys SET status='completed',response_json=:response,updated_at=NOW() WHERE store_id=:store AND scope='subscription_start' AND idempotency_key=:key"); $complete->execute([':response' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ':store' => $storeId, ':key' => $idempotencyKey]);
            return $response;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            try { $failed = $this->db->prepare("UPDATE subscription_idempotency_keys SET status='failed',updated_at=NOW() WHERE store_id=:store AND scope='subscription_start' AND idempotency_key=:key"); $failed->execute([':store' => $storeId, ':key' => $idempotencyKey]); } catch (\Throwable) { }
            throw $exception;
        }
    }

    /**
     * Starts a local subscription and its first manual invoice. No Mercado
     * Pago preapproval is created; the invoice is paid later through the
     * Checkout Transparente route.
     *
     * @return array<string, mixed>
     */
    private function startManualSubscription(int $storeId, string $planSlug, string $cycle, string $idempotencyKey): array
    {
        if (!in_array($cycle, ['monthly', 'yearly'], true)) { throw new RuntimeException('Ciclo de assinatura inválido.'); }
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '' || !$this->tableExists('subscription_idempotency_keys') || !$this->hasColumn('assinaturas', 'billing_mode') || !$this->hasColumn('faturas', 'period_start') || !$this->hasColumn('faturas', 'period_end')) { throw new RuntimeException('A migration financeira ainda não foi aplicada.'); }
        $requestHash = hash('sha256', json_encode(['storeId' => $storeId, 'planSlug' => $planSlug, 'cycle' => $cycle, 'mode' => 'manual'], JSON_UNESCAPED_SLASHES));
        $existing = $this->db->prepare("SELECT request_hash,status,response_json FROM subscription_idempotency_keys WHERE store_id=:store AND scope='subscription_start' AND idempotency_key=:key AND expires_at>NOW() LIMIT 1");
        $existing->execute([':store' => $storeId, ':key' => $idempotencyKey]);
        $replay = $existing->fetch(PDO::FETCH_ASSOC);
        if ($replay) {
            if (!hash_equals((string) $replay['request_hash'], $requestHash)) { throw new RuntimeException('A chave de idempotência já foi usada para outra assinatura.'); }
            if ((string) $replay['status'] === 'completed') { return (array) json_decode((string) $replay['response_json'], true) + ['replayed' => true]; }
        }
        $planStmt = $this->db->prepare('SELECT * FROM planos WHERE slug=:slug AND ativo=1 LIMIT 1');
        $planStmt->execute([':slug' => $planSlug]);
        $plan = $planStmt->fetch(PDO::FETCH_ASSOC);
        if (!$plan) { throw new RuntimeException('Plano não encontrado.'); }
        $price = $cycle === 'yearly' ? (float) $plan['preco_anual'] : (float) $plan['preco_mensal'];
        $periodStart = date('Y-m-d');
        $periodEnd = $cycle === 'yearly' ? date('Y-m-d', strtotime('+1 year -1 day')) : date('Y-m-d', strtotime('+1 month -1 day'));

        $this->db->beginTransaction();
        try {
            $reserve = $this->db->prepare("INSERT INTO subscription_idempotency_keys (store_id,scope,idempotency_key,request_hash,status,expires_at) VALUES (:store,'subscription_start',:key,:hash,'processing',DATE_ADD(NOW(),INTERVAL 24 HOUR))");
            try { $reserve->execute([':store' => $storeId, ':key' => $idempotencyKey, ':hash' => $requestHash]); }
            catch (\PDOException $exception) { throw new RuntimeException('Esta assinatura já está sendo processada. Aguarde alguns instantes.'); }

            $subscriptionColumns = ['tipo', 'loja_id', 'plano_id', 'status', 'ciclo', 'current_period_start', 'current_period_end', 'next_invoice_date', 'gateway', 'sales_blocked', 'sales_block_reason'];
            $subscriptionValues = ["'loja'", ':store', ':plan', "'pendente'", ':cycle', ':period_start', ':period_end', ':next_invoice', "'mercadopago'", '1', "'pending_payment'"];
            $params = [':store' => $storeId, ':plan' => (int) $plan['id'], ':cycle' => $cycle, ':period_start' => $periodStart, ':period_end' => $periodEnd, ':next_invoice' => $periodEnd];
            if ($this->hasColumn('assinaturas', 'billing_mode')) { $subscriptionColumns[] = 'billing_mode'; $subscriptionValues[] = "'manual'"; }
            $insert = $this->db->prepare('INSERT INTO assinaturas (' . implode(',', $subscriptionColumns) . ') VALUES (' . implode(',', $subscriptionValues) . ')');
            $insert->execute($params);
            $subscriptionId = (int) $this->db->lastInsertId();

            $invoiceColumns = ['assinatura_id', 'numero', 'amount', 'currency', 'status', 'due_date', 'gateway', 'created_at', 'updated_at'];
            $invoiceValues = [':subscription', ':number', ':amount', "'BRL'", "'pending'", 'DATE_ADD(CURDATE(),INTERVAL 3 DAY)', "'mercadopago'", 'NOW()', 'NOW()'];
            $invoiceParams = [':subscription' => $subscriptionId, ':number' => 'INV-' . $subscriptionId . '-' . date('YmdHis'), ':amount' => number_format($price, 2, '.', '')];
            if ($this->hasColumn('faturas', 'period_start')) { $invoiceColumns[] = 'period_start'; $invoiceValues[] = ':period_start'; $invoiceParams[':period_start'] = $periodStart; }
            if ($this->hasColumn('faturas', 'period_end')) { $invoiceColumns[] = 'period_end'; $invoiceValues[] = ':period_end'; $invoiceParams[':period_end'] = $periodEnd; }
            if ($this->hasColumn('faturas', 'idempotency_key')) { $invoiceColumns[] = 'idempotency_key'; $invoiceValues[] = ':invoice_key'; $invoiceParams[':invoice_key'] = substr($idempotencyKey . ':invoice', 0, 128); }
            $invoiceInsert = $this->db->prepare('INSERT INTO faturas (' . implode(',', $invoiceColumns) . ') VALUES (' . implode(',', $invoiceValues) . ')');
            $invoiceInsert->execute($invoiceParams);
            $invoiceId = (int) $this->db->lastInsertId();
            if ($this->hasColumn('faturas', 'period_key') && $this->hasColumn('faturas', 'period_start') && $this->hasColumn('faturas', 'period_end')) {
                $period = $this->db->prepare('UPDATE faturas SET period_key=:period WHERE id=:id');
                $period->execute([':period' => $subscriptionId . ':' . $periodStart . ':' . $periodEnd, ':id' => $invoiceId]);
            }
            $response = ['id' => $subscriptionId, 'invoiceId' => $invoiceId, 'status' => 'pendente', 'paymentUrl' => '/store/meu-plano/pagamento?invoiceId=' . $invoiceId, 'checkoutMode' => 'transparent'];
            $complete = $this->db->prepare("UPDATE subscription_idempotency_keys SET status='completed',response_json=:response,updated_at=NOW() WHERE store_id=:store AND scope='subscription_start' AND idempotency_key=:key");
            $complete->execute([':response' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ':store' => $storeId, ':key' => $idempotencyKey]);
            $this->db->commit();
            return $response;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function requestPlanChange(int $storeId, string $planSlug, string $cycle, int $actorId): array
    {
        if (!$this->hasColumn('assinaturas', 'pending_plan_id') || !$this->hasColumn('assinaturas', 'pending_cycle')) {
            throw new RuntimeException('A migration de troca de plano ainda não foi aplicada.');
        }
        $plan = $this->db->prepare('SELECT id,nome,slug FROM planos WHERE slug=:slug AND ativo=1 LIMIT 1'); $plan->execute([':slug' => $planSlug]); $target = $plan->fetch(PDO::FETCH_ASSOC);
        if (!$target) { throw new RuntimeException('Plano selecionado não encontrado.'); }
        $current = $this->db->prepare("SELECT id,status FROM assinaturas WHERE loja_id=:store AND tipo='loja' ORDER BY updated_at DESC,id DESC LIMIT 1 FOR UPDATE"); $current->execute([':store' => $storeId]); $subscription = $current->fetch(PDO::FETCH_ASSOC);
        if (!$subscription) { throw new RuntimeException('A loja não possui assinatura.'); }
        $update = $this->db->prepare('UPDATE assinaturas SET pending_plan_id=:plan,pending_cycle=:cycle,updated_at=NOW() WHERE id=:id'); $update->execute([':plan' => (int) $target['id'], ':cycle' => $cycle, ':id' => (int) $subscription['id']]);
        $this->writeAudit((int) $subscription['id'], $actorId, 'subscription.plan_change_scheduled', [], ['planSlug' => $planSlug, 'cycle' => $cycle]);
        return ['id' => (int) $subscription['id'], 'pendingPlanId' => (int) $target['id'], 'pendingPlanName' => (string) $target['nome'], 'pendingCycle' => $cycle, 'effectiveAt' => 'next_renewal'];
    }

    /** @return array<string, mixed> */
    public function reconcileMercadoPago(string $eventType, string $resourceId, array $resource): array
    {
        $status = strtolower((string) ($resource['status'] ?? $resource['state'] ?? ''));
        $externalSubscriptionId = (string) ($resource['id'] ?? ($resource['preapproval_id'] ?? $resourceId));
        if ($eventType === 'subscription_preapproval' && $this->hasColumn('assinaturas', 'gateway_subscription_id')) {
            $stmt = $this->db->prepare('SELECT id,status FROM assinaturas WHERE gateway_subscription_id=:external LIMIT 1 FOR UPDATE');
            $stmt->execute([':external' => $externalSubscriptionId]);
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$subscription) { return ['matched' => false, 'reason' => 'subscription_not_found']; }
            $localStatus = match ($status) {
                'authorized', 'active' => 'ativa',
                'paused' => 'suspensa',
                'cancelled', 'canceled' => 'cancelada',
                default => (string) $subscription['status'],
            };
            $set = ['status=:status', 'updated_at=NOW()'];
            $params = [':status' => $localStatus, ':id' => (int) $subscription['id']];
            if ($this->hasColumn('assinaturas', 'sales_blocked')) { $set[] = 'sales_blocked=:blocked'; $params[':blocked'] = in_array($localStatus, ['ativa', 'trial'], true) ? 0 : 1; }
            $update = $this->db->prepare('UPDATE assinaturas SET ' . implode(',', $set) . ' WHERE id=:id');
            $update->execute($params);
            return ['matched' => true, 'subscriptionId' => (int) $subscription['id'], 'status' => $localStatus];
        }
        if (str_contains($eventType, 'authorized_payment') || str_contains($eventType, 'payment')) {
            $paymentStatus = strtolower((string) ($resource['status'] ?? ''));
            if ($this->hasColumn('faturas', 'gateway_charge_id')) {
                $stmt = $this->db->prepare('SELECT id,assinatura_id FROM faturas WHERE gateway_charge_id=:external OR gateway_event_id=:external LIMIT 1 FOR UPDATE');
                $stmt->execute([':external' => $resourceId]);
                $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($invoice) {
                    $invoiceStatus = in_array($paymentStatus, ['approved', 'authorized', 'paid'], true) ? 'paid' : (in_array($paymentStatus, ['rejected', 'cancelled', 'canceled'], true) ? 'failed' : 'pending');
                    $set = ['status=:status', 'updated_at=NOW()']; $params = [':status' => $invoiceStatus, ':id' => (int) $invoice['id']];
                    if ($invoiceStatus === 'paid') { $set[] = 'paid_at=COALESCE(paid_at,NOW())'; }
                    $update = $this->db->prepare('UPDATE faturas SET ' . implode(',', $set) . ' WHERE id=:id'); $update->execute($params);
                    return ['matched' => true, 'invoiceId' => (int) $invoice['id'], 'status' => $invoiceStatus];
                }
            }
        }
        return ['matched' => false, 'reason' => 'unsupported_event'];
    }

    /** @return array<string, mixed>|null */
    private function findLatest(int $storeId): ?array
    {
        $stmt = $this->db->prepare("SELECT a.*,p.nome plano_nome,p.slug plano_slug,p.preco_mensal,p.preco_anual,p.features_json FROM assinaturas a JOIN planos p ON p.id=a.plano_id WHERE a.loja_id=:store AND a.tipo='loja' ORDER BY a.updated_at DESC,a.id DESC LIMIT 1");
        $stmt->execute([':store' => $storeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string, mixed> */
    private function subscriptionRow(array $row, array $access): array
    {
        return [
            'id' => (int) $row['id'], 'status' => (string) (($access['status'] ?? null) ?: $row['status']), 'cycle' => (string) $row['ciclo'],
            'planName' => (string) $row['plano_nome'], 'planSlug' => (string) $row['plano_slug'],
            'currentPeriodStart' => $this->iso($row['current_period_start'] ?? null), 'currentPeriodEnd' => $this->iso($row['current_period_end'] ?? null),
            'nextInvoiceDate' => $this->iso($row['next_invoice_date'] ?? null), 'trialEnd' => $this->iso($row['trial_end'] ?? null),
            'monthlyPriceCents' => $this->cents($row['preco_mensal'] ?? 0), 'annualPriceCents' => $this->cents($row['preco_anual'] ?? 0),
            'features' => $this->features($row['features_json'] ?? null), 'salesAccess' => $access,
            'gateway' => $row['gateway'] ?? null, 'gatewaySubscriptionId' => $row['gateway_subscription_id'] ?? null,
            'billingMode' => $row['billing_mode'] ?? (BillingFeatureFlags::transparentCheckoutEnabled() ? 'manual' : 'preapproval_legacy'),
            'updatedAt' => $this->iso($row['updated_at'] ?? null),
        ];
    }

    private function hasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, $this->columns)) { return $this->columns[$key]; }
        try {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND COLUMN_NAME=:column');
            $stmt->execute([':table' => $table, ':column' => $column]);
            return $this->columns[$key] = (int) $stmt->fetchColumn() > 0;
        } catch (\Throwable) { return $this->columns[$key] = false; }
    }

    private function gatewaySupportsMercadoPago(): bool
    {
        try {
            $stmt = $this->db->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='assinaturas' AND COLUMN_NAME='gateway'");
            $stmt->execute();
            return str_contains(strtolower((string) $stmt->fetchColumn()), "'mercadopago'");
        } catch (\Throwable) { return false; }
    }

    private function writeAudit(int $subscriptionId, int $actorId, string $action, array $before, array $after): void
    {
        if (!$this->tableExists('subscription_audit_logs')) { return; }
        $stmt = $this->db->prepare('INSERT INTO subscription_audit_logs (subscription_id,actor_id,action,before_json,after_json,request_id) VALUES (:subscription,:actor,:action,:before,:after,:request)');
        $stmt->execute([':subscription' => $subscriptionId, ':actor' => $actorId, ':action' => $action, ':before' => json_encode($this->safe($before), JSON_UNESCAPED_UNICODE), ':after' => json_encode($this->safe($after), JSON_UNESCAPED_UNICODE), ':request' => substr((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? bin2hex(random_bytes(8))), 0, 64)]);
    }

    private function tableExists(string $table): bool
    {
        try { $stmt = $this->db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table'); $stmt->execute([':table' => $table]); return (int) $stmt->fetchColumn() > 0; } catch (\Throwable) { return false; }
    }

    /** @return array<string, mixed> */
    private function safe(array $data): array
    {
        unset($data['senha'], $data['senha_hash'], $data['token'], $data['gateway_customer_id']);
        return $data;
    }

    private function reasonForStatus(string $status): string
    {
        return match ($status) { 'cancelada' => 'subscription_canceled', 'suspensa' => 'subscription_suspended', 'inadimplente' => 'invoice_overdue', default => 'subscription_unavailable' };
    }

    /** @param array<string, mixed> $subscription */
    private function hasPaidInvoiceForCurrentCycle(array $subscription): bool
    {
        try {
            $paidAfter = (string) ($subscription['current_period_start'] ?? $subscription['created_at'] ?? '1970-01-01 00:00:00');
            $stmt = $this->db->prepare(
                "SELECT id FROM faturas
                 WHERE assinatura_id=:subscription AND status='paid'
                   AND paid_at IS NOT NULL AND paid_at >= :paid_after
                 ORDER BY paid_at DESC LIMIT 1"
            );
            $stmt->execute([':subscription' => (int) ($subscription['id'] ?? 0), ':paid_after' => $paidAfter]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            // A billing read failure must never unlock sales.
            return false;
        }
    }

    /** @param array<string, mixed> $subscription */
    private function withinGracePeriod(array $subscription): bool
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT due_date FROM faturas
                 WHERE assinatura_id=:subscription AND status='pending'
                 ORDER BY due_date ASC,id ASC LIMIT 1"
            );
            $stmt->execute([':subscription' => (int) ($subscription['id'] ?? 0)]);
            $due = (string) ($stmt->fetchColumn() ?: '');
            return $due !== '' && strtotime($due . ' 23:59:59') >= time();
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<int, string> */
    private function features(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;
        if (!is_array($decoded)) { return []; }
        return array_map(static fn ($key, $item): string => is_int($key) ? (string) $item : $key . ': ' . (string) $item, array_keys($decoded), array_values($decoded));
    }

    private function cents(mixed $value): int { return (int) round(((float) $value) * 100); }
    private function iso(mixed $value): ?string { if ($value === null || $value === '') { return null; } $time = strtotime((string) $value); return $time === false ? null : date(DATE_ATOM, $time); }
}
