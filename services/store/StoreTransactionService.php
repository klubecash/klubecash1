<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Services\Billing\BillingFeatureFlags;
use App\Services\Billing\SubscriptionService;
use DateTimeImmutable;
use PDO;
use PDOException;
use Throwable;
use App\Services\Giftback\GiftbackLedger;
use App\Services\Giftback\GiftbackException;

require_once __DIR__ . '/../Giftback/GiftbackLedger.php';
require_once __DIR__ . '/StoreNetworkAccess.php';

final class StoreTransactionService
{
    private StoreIdempotencyService $idempotency;

    public function __construct(private PDO $db)
    {
        $this->idempotency = new StoreIdempotencyService($db);
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function create(int $storeId, int $actorId, array $input, string $idempotencyKey, string $channel = 'manual'): array
    {
        $request = $this->validateInput($input);
        $access = new StoreNetworkAccess($this->db);
        $actorMembership = $access->membership($actorId, $storeId);
        if (!$actorMembership) { throw new StoreApiException('Conta sem acesso ativo à filial.', 403); }
        if ($actorMembership['role'] === 'financeiro') { throw new StoreApiException('Esta função não pode registrar vendas.', 403); }
        if (!in_array($channel, ['manual', 'csv', 'whatsapp'], true)) { throw new StoreApiException('Canal de venda inválido.', 422); }
        if ($channel === 'csv' && !in_array($actorMembership['role'], ['titular','gerente','gestor_rede'], true)) {
            throw new StoreApiException('Importação de vendas restrita à gestão.', 403);
        }
        $sellerId = (int) ($input['sellerId'] ?? ($channel === 'csv' ? 0 : $actorId));
        if ($sellerId <= 0 && $channel !== 'csv') { throw new StoreApiException('Informe o vendedor da venda.', 422); }
        if ($sellerId > 0 && $sellerId !== $actorId && !in_array($actorMembership['role'], ['titular', 'gerente', 'gestor_rede'], true)) {
            throw new StoreApiException('Somente gestor pode registrar venda de outro vendedor.', 403);
        }
        $sellerName = $sellerId > 0 ? $access->assertSeller($sellerId, $storeId) : null;
        $networkId = $access->networkId($storeId);
        $walletStores = $access->walletStores($storeId);
        $request['sellerId'] = $sellerId ?: null;
        $request['channel'] = $channel;
        // A subscription interruption never removes historical data or read
        // access. It only gates every new sale channel when the rollout flag
        // is enabled. Keeping the check here makes the rule impossible to
        // bypass through CSV, WhatsApp or a future API consumer.
        if (BillingFeatureFlags::commercialSalesGateEnabled()) {
            (new SubscriptionService($this->db))->assertCanRegisterSales($storeId);
        }
        $idempotency = $this->idempotency->begin('store_sale', $storeId, $actorId, $idempotencyKey, $request);
        if ($idempotency['replayed']) {
            return [...($idempotency['data'] ?? []), 'replayed' => true];
        }

        try {
            $this->db->beginTransaction();

            if ($networkId !== null) {
                $networkLock = $this->db->prepare('SELECT id FROM store_networks WHERE id=? AND status=\'active\' LOCK IN SHARE MODE');
                $networkLock->execute([$networkId]);
                if (!$networkLock->fetchColumn()) { throw new StoreApiException('A rede mudou. Atualize e tente novamente.', 409); }
            }

            $storeStatement = $this->db->prepare(
                "SELECT id, nome_fantasia, status, cashback_ativo, COALESCE(porcentagem_cliente, 5.00) customer_percentage "
                . 'FROM lojas WHERE id=:store_id LIMIT 1 FOR UPDATE'
            );
            $storeStatement->execute([':store_id' => $storeId]);
            $store = $storeStatement->fetch(PDO::FETCH_ASSOC);
            if (!$store || $store['status'] !== 'aprovado') {
                throw new StoreApiException('Loja não encontrada ou não aprovada.', 422);
            }
            if ((int) $store['cashback_ativo'] !== 1) {
                throw new StoreApiException('Esta loja não oferece cashback no momento.', 422);
            }
            if ($access->networkId($storeId) !== $networkId || $access->walletStores($storeId) !== $walletStores || !$access->membership($actorId, $storeId)) {
                throw new StoreApiException('A filial ou seu acesso mudou. Atualize e tente novamente.', 409);
            }

            $customerStatement = $this->db->prepare(
                "SELECT id,tipo_cliente FROM usuarios WHERE id=:customer_id AND tipo='cliente' AND status='ativo' LIMIT 1"
            );
            $customerStatement->execute([':customer_id' => $request['customerId']]);
            $customer = $customerStatement->fetch(PDO::FETCH_ASSOC);
            if (!$customer) {
                throw new StoreApiException('Cliente não encontrado ou inativo.', 422);
            }
            if ($networkId !== null && $request['balanceUsedCents'] > 0 && ($customer['tipo_cliente'] ?? '') === 'visitante') {
                $proof = $this->db->prepare('SELECT challenge_id FROM network_visitor_verifications WHERE network_id=? AND user_id=? AND verified_until>NOW() LIMIT 1');
                $proof->execute([$networkId, $request['customerId']]);
                if (!$proof->fetchColumn()) { throw new StoreApiException('Cliente visitante precisa confirmar o telefone no link da filial antes de usar saldo da rede.', 403); }
            }

            $duplicateStatement = $this->db->prepare(
                'SELECT id FROM transacoes_cashback WHERE loja_id=:store_id AND codigo_transacao=:code LIMIT 1'
            );
            $duplicateStatement->execute([':store_id' => $storeId, ':code' => $request['code']]);
            if ($duplicateStatement->fetchColumn()) {
                throw new StoreApiException('Já existe uma venda com este código.', 409);
            }

            $grossCents = $request['grossAmountCents'];
            $balanceUsedCents = $request['balanceUsedCents'];
            $minimumCents = StoreMoney::toCents(defined('MIN_TRANSACTION_VALUE') ? MIN_TRANSACTION_VALUE : 5);
            if ($grossCents < $minimumCents) {
                throw new StoreApiException('O valor mínimo da venda é R$ ' . StoreMoney::decimal($minimumCents) . '.', 422);
            }
            if ($balanceUsedCents > $grossCents) {
                throw new StoreApiException('O saldo usado não pode superar o valor da venda.', 422);
            }

            $balanceSettingsStatement = $this->db->query(
                'SELECT permitir_uso_saldo,valor_minimo_uso,percentual_maximo_uso '
                . 'FROM configuracoes_saldo ORDER BY id DESC LIMIT 1'
            );
            $balanceSettings = $balanceSettingsStatement->fetch(PDO::FETCH_ASSOC) ?: [
                'permitir_uso_saldo' => 1,
                'valor_minimo_uso' => 1,
                'percentual_maximo_uso' => 100,
            ];
            if ($balanceUsedCents > 0 && (int) $balanceSettings['permitir_uso_saldo'] !== 1) {
                throw new StoreApiException('O uso de saldo está temporariamente desativado.', 422);
            }
            $minimumBalanceUseCents = StoreMoney::toCents($balanceSettings['valor_minimo_uso'] ?? 1);
            if ($balanceUsedCents > 0 && $balanceUsedCents < $minimumBalanceUseCents) {
                throw new StoreApiException(
                    'O uso mínimo de saldo é R$ ' . StoreMoney::decimal($minimumBalanceUseCents) . '.',
                    422
                );
            }
            $maximumBalanceUseCents = StoreMoney::percentage(
                $grossCents,
                max(0, min(100, (float) ($balanceSettings['percentual_maximo_uso'] ?? 100)))
            );
            if ($balanceUsedCents > $maximumBalanceUseCents) {
                throw new StoreApiException('O saldo utilizado supera o limite permitido para esta compra.', 422);
            }

            $ledger = new GiftbackLedger($this->db);
            $balance = $ledger->networkWallet($request['customerId'], $walletStores);
            if ($balanceUsedCents > $balance['availableCents']) {
                throw new StoreApiException(
                    'Saldo insuficiente. Disponível: R$ ' . StoreMoney::decimal($balance['availableCents']) . '.',
                    422
                );
            }

            $paidCents = $grossCents - $balanceUsedCents;
            if ($paidCents > 0 && $paidCents < $minimumCents) {
                throw new StoreApiException(
                    'O valor pago após o uso do saldo deve ser zero ou pelo menos R$ '
                    . StoreMoney::decimal($minimumCents) . '.',
                    422
                );
            }

            $cashbackCents = StoreMoney::percentage($paidCents, $store['customer_percentage']);
            $insert = $this->db->prepare(
                'INSERT INTO transacoes_cashback '
                . '(usuario_id, loja_id, criado_por, vendedor_id, network_id_snapshot, vendedor_nome_snapshot, registrado_por_nome_snapshot, source_channel, valor_total, valor_cashback, valor_cliente, valor_admin, '
                . 'valor_loja, codigo_transacao, descricao, data_transacao, status, financial_model) '
                . "VALUES (:customer_id,:store_id,:actor_id,:seller_id,:network_id,:seller_name,:actor_name,:channel,:gross,:cashback,:customer_cashback,'0.00','0.00',"
                . ":code,:description,:occurred_at,'aprovado','subscription_cashback')"
            );
            $insert->execute([
                ':customer_id' => $request['customerId'],
                ':store_id' => $storeId,
                ':actor_id' => $actorId,
                ':seller_id' => $sellerId ?: null,
                ':network_id' => $networkId,
                ':seller_name' => $sellerName,
                ':actor_name' => $access->actorName($actorId),
                ':channel' => $channel,
                ':gross' => StoreMoney::decimal($grossCents),
                ':cashback' => StoreMoney::decimal($cashbackCents),
                ':customer_cashback' => StoreMoney::decimal($cashbackCents),
                ':code' => $request['code'],
                ':description' => $request['description'] !== ''
                    ? $request['description']
                    : 'Compra na ' . $store['nome_fantasia'],
                ':occurred_at' => $request['occurredAt'],
            ]);
            $transactionId = (int) $this->db->lastInsertId();

            $runningBalanceCents = $balance['availableCents'];
            if ($balanceUsedCents > 0) {
                $debit = $ledger->spendNetwork($request['customerId'], $storeId, $walletStores, $balanceUsedCents, $transactionId, $actorId, $networkId);
                $used = $this->db->prepare('INSERT INTO transacoes_saldo_usado (transacao_id,usuario_id,loja_id,valor_usado) VALUES (?,?,?,?)');
                $used->execute([$transactionId, $request['customerId'], $storeId, StoreMoney::decimal($balanceUsedCents)]);
                $runningBalanceCents = $debit['balanceCents'];
            }
            if ($cashbackCents > 0) {
                $grant = $ledger->credit($request['customerId'], $storeId, $cashbackCents, 'Giftback da venda ' . $request['code'], $transactionId, $actorId);
                $runningBalanceCents += $cashbackCents;
            }

            $credit = $this->db->prepare('UPDATE transacoes_cashback SET cashback_credited_at=NOW() WHERE id=:id');
            $credit->execute([':id' => $transactionId]);

            $outbox = $this->db->prepare(
                "INSERT INTO store_event_outbox (event_type, aggregate_id, loja_id, payload_json, status) "
                . "VALUES ('cashback.sale.approved',:transaction_id,:store_id,:payload,'pending')"
            );
            $outbox->execute([
                ':transaction_id' => $transactionId,
                ':store_id' => $storeId,
                ':payload' => json_encode([
                    'transactionId' => $transactionId,
                    'customerId' => $request['customerId'],
                    'cashbackCents' => $cashbackCents,
                ], JSON_UNESCAPED_SLASHES),
            ]);

            $response = [
                'id' => $transactionId,
                'status' => 'approved',
                'grossAmountCents' => $grossCents,
                'paidAmountCents' => $paidCents,
                'balanceUsedCents' => $balanceUsedCents,
                'cashbackGrantedCents' => $cashbackCents,
                'customerBalanceCents' => $runningBalanceCents,
                'sellerId' => $sellerId ?: null,
                'recordedById' => $actorId,
                'networkId' => $networkId,
                'replayed' => false,
            ];
            $this->idempotency->complete('store_sale', $storeId, $idempotencyKey, $response);
            $this->db->commit();
            return $response;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->idempotency->fail('store_sale', $storeId, $idempotencyKey);
            if ($exception instanceof GiftbackException) {
                throw new StoreApiException($exception->getMessage(), $exception->httpStatus);
            }
            if ($exception instanceof StoreApiException) {
                throw $exception;
            }
            if ($exception instanceof PDOException && (string) $exception->getCode() === '23000') {
                throw new StoreApiException('A venda já foi registrada.', 409);
            }
            throw $exception;
        }
    }


    /** @param array<string, mixed> $input
     *  @return array{customerId:int,grossAmountCents:int,balanceUsedCents:int,code:string,description:string,occurredAt:string}
     */
    private function validateInput(array $input): array
    {
        $errors = [];
        $customerId = (int) ($input['customerId'] ?? 0);
        $grossAmountCents = (int) ($input['grossAmountCents'] ?? 0);
        $balanceUsedCents = (int) ($input['balanceUsedCents'] ?? 0);
        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        $description = trim((string) ($input['description'] ?? ''));
        if ($customerId <= 0) {
            $errors['customerId'] = ['Selecione um cliente válido.'];
        }
        if ($grossAmountCents <= 0) {
            $errors['grossAmountCents'] = ['Informe um valor de venda válido.'];
        }
        if ($balanceUsedCents < 0) {
            $errors['balanceUsedCents'] = ['O saldo utilizado não pode ser negativo.'];
        }
        if (strlen($code) < 3 || strlen($code) > 50) {
            $errors['code'] = ['Use um código entre 3 e 50 caracteres.'];
        }
        if (strlen($description) > 500) {
            $errors['description'] = ['A descrição deve ter no máximo 500 caracteres.'];
        }

        try {
            $occurredAt = new DateTimeImmutable((string) ($input['occurredAt'] ?? 'now'));
        } catch (Throwable) {
            $errors['occurredAt'] = ['Informe uma data válida.'];
            $occurredAt = new DateTimeImmutable();
        }
        if ($errors !== []) {
            throw new StoreApiException('Revise os dados da venda.', 422, $errors);
        }

        return [
            'customerId' => $customerId,
            'grossAmountCents' => $grossAmountCents,
            'balanceUsedCents' => $balanceUsedCents,
            'code' => $code,
            'description' => $description,
            'occurredAt' => $occurredAt->format('Y-m-d H:i:s'),
        ];
    }
}
