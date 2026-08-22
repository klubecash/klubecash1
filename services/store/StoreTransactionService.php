<?php

declare(strict_types=1);

namespace App\Services\Store;

use DateTimeImmutable;
use PDO;
use PDOException;
use Throwable;

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
    public function create(int $storeId, int $actorId, array $input, string $idempotencyKey): array
    {
        $request = $this->validateInput($input);
        $idempotency = $this->idempotency->begin('store_sale', $storeId, $actorId, $idempotencyKey, $request);
        if ($idempotency['replayed']) {
            return [...($idempotency['data'] ?? []), 'replayed' => true];
        }

        try {
            $this->db->beginTransaction();

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

            $customerStatement = $this->db->prepare(
                "SELECT id FROM usuarios WHERE id=:customer_id AND tipo='cliente' AND status='ativo' LIMIT 1"
            );
            $customerStatement->execute([':customer_id' => $request['customerId']]);
            if (!$customerStatement->fetchColumn()) {
                throw new StoreApiException('Cliente não encontrado ou inativo.', 422);
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

            $this->ensureBalanceRow($request['customerId'], $storeId);
            $balance = $this->lockBalance($request['customerId'], $storeId);
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
                . '(usuario_id, loja_id, criado_por, valor_total, valor_cashback, valor_cliente, valor_admin, '
                . 'valor_loja, codigo_transacao, descricao, data_transacao, status, financial_model) '
                . "VALUES (:customer_id,:store_id,:actor_id,:gross,:cashback,:customer_cashback,'0.00','0.00',"
                . ":code,:description,:occurred_at,'aprovado','subscription_cashback')"
            );
            $insert->execute([
                ':customer_id' => $request['customerId'],
                ':store_id' => $storeId,
                ':actor_id' => $actorId,
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
                $newBalanceCents = $runningBalanceCents - $balanceUsedCents;
                $update = $this->db->prepare(
                    'UPDATE cashback_saldos SET saldo_disponivel=:available, total_usado=total_usado+:used, '
                    . 'ultima_atualizacao=NOW() WHERE usuario_id=:customer_id AND loja_id=:store_id'
                );
                $update->execute([
                    ':available' => StoreMoney::decimal($newBalanceCents),
                    ':used' => StoreMoney::decimal($balanceUsedCents),
                    ':customer_id' => $request['customerId'],
                    ':store_id' => $storeId,
                ]);
                $movement = $this->db->prepare(
                    "INSERT INTO cashback_movimentacoes (usuario_id,loja_id,criado_por,tipo_operacao,valor,"
                    . "saldo_anterior,saldo_atual,descricao,transacao_uso_id) "
                    . "VALUES (:customer_id,:store_id,:actor_id,'uso',:amount,:before,:after,:description,:transaction_id)"
                );
                $movement->execute([
                    ':customer_id' => $request['customerId'],
                    ':store_id' => $storeId,
                    ':actor_id' => $actorId,
                    ':amount' => StoreMoney::decimal($balanceUsedCents),
                    ':before' => StoreMoney::decimal($runningBalanceCents),
                    ':after' => StoreMoney::decimal($newBalanceCents),
                    ':description' => 'Uso de cashback na venda ' . $request['code'],
                    ':transaction_id' => $transactionId,
                ]);
                $used = $this->db->prepare(
                    'INSERT INTO transacoes_saldo_usado (transacao_id,usuario_id,loja_id,valor_usado) '
                    . 'VALUES (:transaction_id,:customer_id,:store_id,:amount)'
                );
                $used->execute([
                    ':transaction_id' => $transactionId,
                    ':customer_id' => $request['customerId'],
                    ':store_id' => $storeId,
                    ':amount' => StoreMoney::decimal($balanceUsedCents),
                ]);
                $runningBalanceCents = $newBalanceCents;
            }

            if ($cashbackCents > 0) {
                $newBalanceCents = $runningBalanceCents + $cashbackCents;
                $update = $this->db->prepare(
                    'UPDATE cashback_saldos SET saldo_disponivel=:available, total_creditado=total_creditado+:cashback, '
                    . 'ultima_atualizacao=NOW() WHERE usuario_id=:customer_id AND loja_id=:store_id'
                );
                $update->execute([
                    ':available' => StoreMoney::decimal($newBalanceCents),
                    ':cashback' => StoreMoney::decimal($cashbackCents),
                    ':customer_id' => $request['customerId'],
                    ':store_id' => $storeId,
                ]);
                $movement = $this->db->prepare(
                    "INSERT INTO cashback_movimentacoes (usuario_id,loja_id,criado_por,tipo_operacao,valor,"
                    . "saldo_anterior,saldo_atual,descricao,transacao_origem_id) "
                    . "VALUES (:customer_id,:store_id,:actor_id,'credito',:amount,:before,:after,:description,:transaction_id)"
                );
                $movement->execute([
                    ':customer_id' => $request['customerId'],
                    ':store_id' => $storeId,
                    ':actor_id' => $actorId,
                    ':amount' => StoreMoney::decimal($cashbackCents),
                    ':before' => StoreMoney::decimal($runningBalanceCents),
                    ':after' => StoreMoney::decimal($newBalanceCents),
                    ':description' => 'Cashback da venda ' . $request['code'],
                    ':transaction_id' => $transactionId,
                ]);
                $runningBalanceCents = $newBalanceCents;
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
            if ($exception instanceof StoreApiException) {
                throw $exception;
            }
            if ($exception instanceof PDOException && (string) $exception->getCode() === '23000') {
                throw new StoreApiException('A venda já foi registrada.', 409);
            }
            throw $exception;
        }
    }

    private function ensureBalanceRow(int $customerId, int $storeId): void
    {
        $statement = $this->db->prepare(
            'INSERT IGNORE INTO cashback_saldos (usuario_id,loja_id,saldo_disponivel,total_creditado,total_usado) '
            . "VALUES (:customer_id,:store_id,'0.00','0.00','0.00')"
        );
        $statement->execute([':customer_id' => $customerId, ':store_id' => $storeId]);
    }

    /** @return array{availableCents: int} */
    private function lockBalance(int $customerId, int $storeId): array
    {
        $statement = $this->db->prepare(
            'SELECT saldo_disponivel FROM cashback_saldos '
            . 'WHERE usuario_id=:customer_id AND loja_id=:store_id LIMIT 1 FOR UPDATE'
        );
        $statement->execute([':customer_id' => $customerId, ':store_id' => $storeId]);
        return ['availableCents' => StoreMoney::toCents($statement->fetchColumn() ?: 0)];
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
