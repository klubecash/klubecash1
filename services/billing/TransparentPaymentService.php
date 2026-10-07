<?php

declare(strict_types=1);

namespace App\Services\Billing;

use PDO;

/**
 * One-off subscription payments created by Checkout Transparente.
 *
 * The browser submits only tokenized payment data. Amount, ownership and
 * invoice state are always read under a row lock from the local database.
 */
final class TransparentPaymentService
{
    /** @var array<string, bool> */
    private array $columns = [];

    public function __construct(private PDO $db, private ?MercadoPagoSubscriptionClient $client = null)
    {
        $this->client ??= new MercadoPagoSubscriptionClient();
    }

    /** @return array<string, mixed> */
    public function create(int $storeId, int $userId, int $invoiceId, array $input, string $idempotencyKey): array
    {
        if (!BillingFeatureFlags::transparentCheckoutEnabled()) {
            throw new TransparentPaymentException('O Checkout Transparente ainda está desativado para esta implantação.', 503);
        }
        if (!$this->tableExists('subscription_payment_idempotency')) {
            throw new TransparentPaymentException('A migration do Checkout Transparente ainda não foi aplicada.', 503);
        }
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            throw new TransparentPaymentException('A chave de idempotência é obrigatória.', 400);
        }
        $invoiceId = max(0, $invoiceId);
        if ($invoiceId <= 0) {
            throw new TransparentPaymentException('Fatura inválida.', 422, ['invoiceId' => ['Informe uma fatura válida.']]);
        }

        $paymentMethodId = strtolower(trim((string) ($input['paymentMethodId'] ?? '')));
        $token = trim((string) ($input['token'] ?? ''));
        $installments = max(1, (int) ($input['installments'] ?? 1));
        if ($paymentMethodId === '') {
            throw new TransparentPaymentException('Método de pagamento inválido.', 422);
        }
        $isPix = $paymentMethodId === 'pix';
        $tokenOptional = in_array($paymentMethodId, ['pix', 'account_money', 'ticket', 'bolbradesco'], true);
        if (!$tokenOptional && $token === '') {
            throw new TransparentPaymentException('Não foi possível validar os dados do cartão.', 422);
        }
        if ($installments > 24) {
            throw new TransparentPaymentException('Número de parcelas inválido.', 422);
        }

        $requestHash = hash('sha256', json_encode([
            'invoiceId' => $invoiceId,
            'paymentMethodId' => $paymentMethodId,
            'token' => $token,
            'issuerId' => (string) ($input['issuerId'] ?? ''),
            'installments' => $installments,
        ], JSON_UNESCAPED_SLASHES));

        $invoice = null;
        $replayed = null;
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'SELECT f.*,a.loja_id,a.status subscription_status,u.email payer_email
                 FROM faturas f
                 INNER JOIN assinaturas a ON a.id=f.assinatura_id
                 LEFT JOIN lojas l ON l.id=a.loja_id
                 LEFT JOIN usuarios u ON u.id=l.usuario_id
                 WHERE f.id=:invoice AND a.loja_id=:store LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([':invoice' => $invoiceId, ':store' => $storeId]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$invoice) {
                throw new TransparentPaymentException('Fatura não encontrada para esta loja.', 404);
            }
            $status = strtolower((string) ($invoice['status'] ?? ''));
            if ($status === 'paid') {
                throw new TransparentPaymentException('Esta fatura já está paga.', 409);
            }
            if (!in_array($status, ['pending', 'failed'], true)) {
                throw new TransparentPaymentException('Esta fatura não pode receber um novo pagamento.', 409);
            }
            $replayed = $this->findIdempotency($storeId, $invoiceId, $idempotencyKey, $requestHash);
            if (is_array($replayed)) {
                $this->db->commit();
                return $replayed + ['replayed' => true];
            }
            if ($status === 'pending' && trim((string) ($invoice['gateway_charge_id'] ?? '')) !== '') {
                throw new TransparentPaymentException('Esta fatura já possui um pagamento em processamento. Aguarde a confirmação antes de tentar novamente.', 409);
            }

            $this->reserveIdempotency($storeId, $invoiceId, $idempotencyKey, $requestHash);
            $set = ['attempts=COALESCE(attempts,0)+1', 'payment_method=:method', 'gateway=:gateway', 'updated_at=NOW()'];
            $params = [':method' => $isPix ? 'pix' : 'card', ':gateway' => 'mercadopago', ':id' => $invoiceId];
            if ($this->hasColumn('faturas', 'payment_status_detail')) {
                $set[] = 'payment_status_detail=NULL';
            }
            if ($this->hasColumn('faturas', 'idempotency_key')) {
                $set[] = 'idempotency_key=:key';
                $params[':key'] = substr($idempotencyKey, 0, 128);
            }
            $this->db->prepare('UPDATE faturas SET ' . implode(',', $set) . ' WHERE id=:id')->execute($params);
            $this->db->commit();
        } catch (TransparentPaymentException $exception) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $exception;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $exception;
        }

        $amount = (float) ($invoice['amount'] ?? 0);
        if ($amount <= 0) {
            throw new TransparentPaymentException('A fatura possui um valor inválido.', 422);
        }
        $payerEmail = trim((string) ($invoice['payer_email'] ?? ''));
        if (!filter_var($payerEmail, FILTER_VALIDATE_EMAIL)) {
            throw new TransparentPaymentException('Não foi possível identificar o e-mail da conta da loja.', 422);
        }
        $gatewayPayload = [
            'transaction_amount' => round($amount, 2),
            'description' => 'Assinatura KlubeCash - fatura ' . (string) ($invoice['numero'] ?? $invoiceId),
            'payment_method_id' => $paymentMethodId,
            'payer' => ['email' => $payerEmail],
            'external_reference' => 'fatura:' . $invoiceId,
        ];
        if ($token !== '') { $gatewayPayload['token'] = $token; }
        if (!empty($input['issuerId'])) { $gatewayPayload['issuer_id'] = (int) $input['issuerId']; }
        if (!$isPix) { $gatewayPayload['installments'] = $installments; }
        if (!empty($input['deviceId'])) { $gatewayPayload['device_id'] = substr(trim((string) $input['deviceId']), 0, 128); }

        try {
            $gateway = $this->client->createPayment($gatewayPayload, $idempotencyKey);
        } catch (\Throwable $exception) {
            $this->markAttemptFailed($invoiceId, $exception->getMessage());
            $this->completeIdempotency($storeId, $invoiceId, $idempotencyKey, null, 'failed');
            throw new TransparentPaymentException('O Mercado Pago não conseguiu iniciar o pagamento. Tente novamente.', 502);
        }

        $gatewayAmount = (float) ($gateway['transaction_amount'] ?? $gateway['transaction_details']['total_paid_amount'] ?? $amount);
        if (abs($gatewayAmount - $amount) > 0.01) {
            $this->markAttemptFailed($invoiceId, 'Valor retornado pelo gateway diverge da fatura.');
            $this->completeIdempotency($storeId, $invoiceId, $idempotencyKey, null, 'failed');
            throw new TransparentPaymentException('O valor confirmado pelo Mercado Pago não corresponde à fatura.', 502);
        }

        $result = $this->persistGatewayResult($storeId, $invoiceId, $idempotencyKey, $gateway);
        return $result;
    }

    /** @return array<string, mixed> */
    public function status(int $storeId, int $invoiceId): array
    {
        $stmt = $this->db->prepare(
            'SELECT f.id,f.status,f.gateway_charge_id,f.payment_method,failure_code
             FROM faturas f INNER JOIN assinaturas a ON a.id=f.assinatura_id
             WHERE f.id=:invoice AND a.loja_id=:store LIMIT 1'
        );
        $stmt->execute([':invoice' => $invoiceId, ':store' => $storeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new TransparentPaymentException('Fatura não encontrada para esta loja.', 404); }
        $paymentId = trim((string) ($row['gateway_charge_id'] ?? ''));
        if ($paymentId === '') {
            return ['invoiceId' => $invoiceId, 'status' => (string) $row['status']];
        }
        try {
            $gateway = $this->client->getPayment($paymentId);
            return $this->persistGatewayResult($storeId, $invoiceId, null, $gateway);
        } catch (\Throwable) {
            return ['invoiceId' => $invoiceId, 'status' => (string) $row['status'], 'paymentId' => $paymentId];
        }
    }

    /** @return array<string, mixed> */
    public function reconcileExternalPayment(string $paymentId, array $gateway): array
    {
        $paymentId = trim($paymentId);
        $externalReference = trim((string) ($gateway['external_reference'] ?? ''));
        $invoiceId = 0;
        if (preg_match('/^fatura:(\d+)$/', $externalReference, $match)) {
            $invoiceId = (int) $match[1];
        }
        if ($invoiceId <= 0) {
            $stmt = $this->db->prepare('SELECT id,assinatura_id FROM faturas WHERE gateway_charge_id=:payment LIMIT 1');
            $stmt->execute([':payment' => $paymentId]);
            $invoiceId = (int) ($stmt->fetchColumn() ?: 0);
        }
        if ($invoiceId <= 0) { return ['matched' => false, 'reason' => 'invoice_not_found']; }
        $stmt = $this->db->prepare('SELECT a.loja_id FROM faturas f INNER JOIN assinaturas a ON a.id=f.assinatura_id WHERE f.id=:invoice LIMIT 1');
        $stmt->execute([':invoice' => $invoiceId]);
        $storeId = (int) ($stmt->fetchColumn() ?: 0);
        if ($storeId <= 0) { return ['matched' => false, 'reason' => 'store_not_found']; }
        return ['matched' => true] + $this->persistGatewayResult($storeId, $invoiceId, null, $gateway);
    }

    /** @return array<string, mixed> */
    private function persistGatewayResult(int $storeId, int $invoiceId, ?string $idempotencyKey, array $gateway): array
    {
        $paymentId = trim((string) ($gateway['id'] ?? ''));
        if ($paymentId === '') { throw new TransparentPaymentException('Mercado Pago retornou um pagamento inválido.', 502); }
        $gatewayStatus = strtolower((string) ($gateway['status'] ?? 'pending'));
        $status = match ($gatewayStatus) {
            'approved', 'authorized', 'paid' => 'paid',
            'rejected', 'cancelled', 'canceled' => 'failed',
            default => 'pending',
        };
        $detail = trim((string) ($gateway['status_detail'] ?? ''));
        $pix = is_array($gateway['point_of_interaction']['transaction_data'] ?? null)
            ? $gateway['point_of_interaction']['transaction_data'] : [];

        $this->db->beginTransaction();
        try {
            $invoiceStmt = $this->db->prepare(
                'SELECT f.id,f.status,f.assinatura_id,f.period_start,f.period_end,a.loja_id FROM faturas f INNER JOIN assinaturas a ON a.id=f.assinatura_id WHERE f.id=:invoice AND a.loja_id=:store LIMIT 1 FOR UPDATE'
            );
            $invoiceStmt->execute([':invoice' => $invoiceId, ':store' => $storeId]);
            $invoice = $invoiceStmt->fetch(PDO::FETCH_ASSOC);
            if (!$invoice) { throw new TransparentPaymentException('Fatura não encontrada para esta loja.', 404); }
            $current = strtolower((string) $invoice['status']);
            if ($current === 'paid') { $status = 'paid'; }
            $set = ['gateway_charge_id=:payment', 'status=:status', 'updated_at=NOW()'];
            $params = [':payment' => $paymentId, ':status' => $status, ':id' => $invoiceId];
            if ($status === 'paid') { $set[] = 'paid_at=COALESCE(paid_at,NOW())'; }
            if ($this->hasColumn('faturas', 'payment_status_detail')) { $set[] = 'payment_status_detail=:detail'; $params[':detail'] = $detail !== '' ? substr($detail, 0, 160) : null; }
            if ($this->hasColumn('faturas', 'payment_type')) { $set[] = 'payment_type=:type'; $params[':type'] = substr((string) ($gateway['payment_type_id'] ?? ''), 0, 40) ?: null; }
            if ($pix !== []) {
                if ($this->hasColumn('faturas', 'pix_copia_cola')) { $set[] = 'pix_copia_cola=:copy'; $params[':copy'] = $pix['qr_code'] ?? null; }
                if ($this->hasColumn('faturas', 'pix_qr_code')) { $set[] = 'pix_qr_code=:qr'; $params[':qr'] = $pix['qr_code_base64'] ?? null; }
            }
            $this->db->prepare('UPDATE faturas SET ' . implode(',', $set) . ' WHERE id=:id')->execute($params);
            if ($status === 'paid') {
                $subscriptionSet = ['status=\'ativa\'', 'updated_at=NOW()'];
                if ($this->hasColumn('assinaturas', 'sales_blocked')) { $subscriptionSet[] = 'sales_blocked=0'; }
                if ($this->hasColumn('assinaturas', 'sales_block_reason')) { $subscriptionSet[] = 'sales_block_reason=NULL'; }
                $subscriptionParams = [':id' => (int) $invoice['assinatura_id']];
                if ($this->hasColumn('assinaturas', 'current_period_start') && !empty($invoice['period_start'])) { $subscriptionSet[] = 'current_period_start=:period_start'; $subscriptionParams[':period_start'] = $invoice['period_start']; }
                if ($this->hasColumn('assinaturas', 'current_period_end') && !empty($invoice['period_end'])) { $subscriptionSet[] = 'current_period_end=:period_end'; $subscriptionParams[':period_end'] = $invoice['period_end']; }
                if ($this->hasColumn('assinaturas', 'next_invoice_date') && !empty($invoice['period_end'])) { $subscriptionSet[] = 'next_invoice_date=DATE_ADD(:next_date,INTERVAL 1 DAY)'; $subscriptionParams[':next_date'] = $invoice['period_end']; }
                $subscriptionUpdate = $this->db->prepare('UPDATE assinaturas SET ' . implode(',', $subscriptionSet) . ' WHERE id=:id');
                $subscriptionUpdate->execute($subscriptionParams);
            }
            $result = [
                'invoiceId' => $invoiceId,
                'paymentId' => $paymentId,
                'status' => $status,
                'statusDetail' => $detail !== '' ? $detail : null,
                'pixQrCode' => $pix['qr_code_base64'] ?? null,
                'pixCopyPaste' => $pix['qr_code'] ?? null,
            ];
            if ($idempotencyKey !== null) { $this->completeIdempotency($storeId, $invoiceId, $idempotencyKey, $result, 'completed'); }
            $this->db->commit();
            return $result;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $exception;
        }
    }

    private function markAttemptFailed(int $invoiceId, string $message): void
    {
        try {
            $set = ['status=\'pending\'', 'updated_at=NOW()'];
            $params = [':id' => $invoiceId];
            if ($this->hasColumn('faturas', 'payment_status_detail')) { $set[] = 'payment_status_detail=:detail'; $params[':detail'] = substr($message, 0, 160); }
            $this->db->prepare('UPDATE faturas SET ' . implode(',', $set) . ' WHERE id=:id')->execute($params);
        } catch (\Throwable) { }
    }

    /** @return array<string, mixed>|null */
    private function findIdempotency(int $storeId, int $invoiceId, string $key, string $hash): ?array
    {
        if (!$this->tableExists('subscription_payment_idempotency')) { return null; }
        $stmt = $this->db->prepare('SELECT request_hash,status,response_json FROM subscription_payment_idempotency WHERE store_id=:store AND invoice_id=:invoice AND idempotency_key=:key LIMIT 1 FOR UPDATE');
        $stmt->execute([':store' => $storeId, ':invoice' => $invoiceId, ':key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { return null; }
        if (!hash_equals((string) $row['request_hash'], $hash)) { throw new TransparentPaymentException('A chave de idempotência já foi usada para outro pagamento.', 409); }
        if ((string) $row['status'] === 'completed' && is_string($row['response_json'])) { return json_decode($row['response_json'], true) ?: null; }
        throw new TransparentPaymentException('Este pagamento já está sendo processado. Aguarde alguns instantes.', 409);
    }

    private function reserveIdempotency(int $storeId, int $invoiceId, string $key, string $hash): void
    {
        if (!$this->tableExists('subscription_payment_idempotency')) { return; }
        $stmt = $this->db->prepare("INSERT INTO subscription_payment_idempotency (store_id,invoice_id,idempotency_key,request_hash,status,expires_at) VALUES (:store,:invoice,:key,:hash,'processing',DATE_ADD(NOW(),INTERVAL 24 HOUR))");
        try { $stmt->execute([':store' => $storeId, ':invoice' => $invoiceId, ':key' => $key, ':hash' => $hash]); }
        catch (\PDOException $exception) { throw new TransparentPaymentException('Este pagamento já está sendo processado. Aguarde alguns instantes.', 409); }
    }

    private function completeIdempotency(int $storeId, int $invoiceId, string $key, ?array $response, string $status): void
    {
        if (!$this->tableExists('subscription_payment_idempotency')) { return; }
        $stmt = $this->db->prepare('UPDATE subscription_payment_idempotency SET status=:status,response_json=:response,updated_at=NOW() WHERE store_id=:store AND invoice_id=:invoice AND idempotency_key=:key');
        $stmt->execute([':status' => $status, ':response' => $response ? json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null, ':store' => $storeId, ':invoice' => $invoiceId, ':key' => $key]);
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

    private function tableExists(string $table): bool
    {
        try {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table');
            $stmt->execute([':table' => $table]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (\Throwable) { return false; }
    }
}
