<?php

declare(strict_types=1);

namespace App\Services\Giftback;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

require_once __DIR__ . '/GiftbackException.php';

/**
 * One wallet lock serializes credits, FEFO consumption, expiry and reversals.
 * Caller-owned transactions are respected using savepoints; errors never commit
 * a partial ledger. Dates use Sao Paulo and an exclusive next-day boundary.
 */
final class GiftbackLedger
{
    private int $savepoint = 0;
    private DateTimeZone $zone;

    public function __construct(private PDO $db, private ?DateTimeImmutable $now = null)
    {
        $this->zone = new DateTimeZone('America/Sao_Paulo');
        if (filter_var(getenv('GIFTBACK_WRITES_PAUSED') ?: '0', FILTER_VALIDATE_BOOL)) {
            throw new GiftbackException('Carteira em manutenção. Tente novamente em instantes.', 503);
        }
        try {
            $ready = $db->query('SELECT ready FROM giftback_rollout WHERE id=1')->fetchColumn();
        } catch (\PDOException $error) {
            throw new GiftbackException('O controle de giftback precisa da migração de banco antes de operar.', 503);
        }
        if ((int) $ready !== 1) { throw new GiftbackException('O controle de giftback está em manutenção.', 503); }
    }

    public static function cents(int|float|string|null $value): int
    {
        return (int) round((float) ($value ?? 0) * 100, 0, PHP_ROUND_HALF_UP);
    }

    private static function decimal(int $value): string { return number_format($value / 100, 2, '.', ''); }
    private function clock(): DateTimeImmutable { return ($this->now ?? new DateTimeImmutable('now', $this->zone))->setTimezone($this->zone); }
    private function timestamp(): string { return $this->clock()->format('Y-m-d H:i:s'); }

    private function atomic(callable $action): mixed
    {
        $own = !$this->db->inTransaction();
        $point = 'giftback_' . (++$this->savepoint);
        if ($own) { $this->db->beginTransaction(); } else { $this->db->exec('SAVEPOINT ' . $point); }
        try {
            $result = $action();
            if ($own) { $this->db->commit(); } else { $this->db->exec('RELEASE SAVEPOINT ' . $point); }
            return $result;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                if ($own) { $this->db->rollBack(); } else { $this->db->exec('ROLLBACK TO SAVEPOINT ' . $point); $this->db->exec('RELEASE SAVEPOINT ' . $point); }
            }
            throw $error;
        }
    }

    private function rows(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    private function row(string $sql, array $params = []): ?array { return $this->rows($sql, $params)[0] ?? null; }
    private function execute(string $sql, array $params = []): void { $stmt = $this->db->prepare($sql); $stmt->execute($params); }

    private function lockWallet(int $user, int $store): array
    {
        if ($user <= 0 || $store <= 0) { throw new GiftbackException('Cliente e loja são obrigatórios.'); }
        // ON DUPLICATE KEY takes an exclusive lock. INSERT IGNORE takes a
        // shared lock on an existing row and two spenders can deadlock when
        // both subsequently upgrade it with SELECT FOR UPDATE.
        $insert = $this->db->prepare('INSERT INTO cashback_saldos(usuario_id,loja_id,saldo_disponivel,total_creditado,total_usado) VALUES(?,?,0,0,0) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
        $insert->execute([$user, $store]); $new = $insert->rowCount() === 1;
        if ($new && (!$this->row('SELECT id FROM usuarios WHERE id=?', [$user]) || !$this->row('SELECT id FROM lojas WHERE id=?', [$store]))) {
            throw new GiftbackException('Cliente ou loja não encontrado.', 404);
        }
        $wallet = $this->row('SELECT * FROM cashback_saldos WHERE usuario_id=? AND loja_id=? FOR UPDATE', [$user, $store]);
        if (!$wallet) { throw new GiftbackException('Carteira não encontrada.', 409); }
        $marker = $this->row('SELECT * FROM cashback_credito_carteiras WHERE usuario_id=? AND loja_id=?', [$user, $store]);
        if (!$marker) {
            if (!$new) { throw new GiftbackException('Carteira não migrada; reconciliação administrativa necessária.', 503); }
            $max = $this->row('SELECT COALESCE(MAX(id),0) id FROM cashback_movimentacoes WHERE usuario_id=? AND loja_id=?', [$user, $store]);
            $this->execute('INSERT INTO cashback_credito_carteiras VALUES(?,?,?,?)', [$user, $store, (int) $max['id'], $this->timestamp()]);
        }
        $this->assertWallet($user, $store);
        return $wallet;
    }

    private function assertWallet(int $user, int $store): void
    {
        $state = $this->row('SELECT COALESCE(SUM(remaining_cents),0) remaining, COALESCE(SUM(ABS(original_cents-remaining_cents-consumed_cents-expired_cents-revoked_cents)),0) difference, COALESCE(MIN(LEAST(remaining_cents,consumed_cents,expired_cents,revoked_cents)),0) smallest FROM cashback_creditos WHERE usuario_id=? AND loja_id=?', [$user, $store]);
        $wallet = $this->row('SELECT saldo_disponivel FROM cashback_saldos WHERE usuario_id=? AND loja_id=?', [$user, $store]);
        if ((int) $state['remaining'] !== self::cents($wallet['saldo_disponivel']) || (int) $state['difference'] !== 0 || (int) $state['smallest'] < 0) {
            throw new GiftbackException('Saldo inconsistente; reconciliação administrativa necessária.', 409);
        }
    }

    private function available(int $user, int $store): int
    {
        return self::cents($this->row('SELECT saldo_disponivel FROM cashback_saldos WHERE usuario_id=? AND loja_id=?', [$user, $store])['saldo_disponivel'] ?? 0);
    }

    /** Returns [movement id, before, after]. Wallet and credit mutations share a transaction. */
    private function movement(int $user, int $store, string $type, int $delta, string $description, ?int $actor = null, ?int $origin = null, ?int $usage = null, int $creditDelta = 0, int $usedDelta = 0): array
    {
        $before = $this->available($user, $store); $after = $before + $delta;
        if ($after < 0) { throw new GiftbackException('Saldo insuficiente.', 409); }
        $this->execute('UPDATE cashback_saldos SET saldo_disponivel=?,total_creditado=GREATEST(0,total_creditado+?),total_usado=GREATEST(0,total_usado+?),ultima_atualizacao=? WHERE usuario_id=? AND loja_id=?', [self::decimal($after), self::decimal($creditDelta), self::decimal($usedDelta), $this->timestamp(), $user, $store]);
        $this->execute('INSERT INTO cashback_movimentacoes(usuario_id,loja_id,criado_por,tipo_operacao,valor,saldo_anterior,saldo_atual,descricao,transacao_origem_id,transacao_uso_id,data_operacao) VALUES(?,?,?,?,?,?,?,?,?,?,?)', [$user, $store, $actor, $type, self::decimal(abs($delta)), self::decimal($before), self::decimal($after), iconv_substr($description, 0, 255, 'UTF-8'), $origin, $usage, $this->timestamp()]);
        return [(int) $this->db->lastInsertId(), $before, $after];
    }

    private function event(int $credit, string $type, int $amount, int $before, int $after, string $reason, ?int $movement = null, ?int $actor = null, ?string $old = null, ?string $new = null, ?int $related = null): int
    {
        $actorName = $actor ? ($this->row('SELECT nome FROM usuarios WHERE id=?', [$actor])['nome'] ?? null) : null;
        $this->execute('INSERT INTO cashback_credito_eventos(credit_id,type,amount_cents,previous_cents,current_cents,old_expires_at,new_expires_at,actor_id,actor_name,reason,movement_id,related_event_id,occurred_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)', [$credit, $type, $amount, $before, $after, $old, $new, $actor, $actorName, $reason, $movement, $related, $this->timestamp()]);
        return (int) $this->db->lastInsertId();
    }

    private function operation(string $key, array $payload, callable $action): array
    {
        $hash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $existing = $this->row('SELECT * FROM cashback_credito_operacoes WHERE operation_key=? FOR UPDATE', [$key]);
        if ($existing) {
            if (!hash_equals($existing['request_hash'], $hash)) { throw new GiftbackException('Operação repetida com dados diferentes.', 409); }
            return array_replace((array) json_decode($existing['response_json'], true), ['replayed' => true]);
        }
        $result = $action();
        $result['replayed'] = false;
        $this->execute('INSERT INTO cashback_credito_operacoes VALUES(?,?,?,?)', [$key, $hash, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $this->timestamp()]);
        return $result;
    }

    private function verifyTransaction(?int $id, int $user, int $store): void
    {
        if ($id !== null && !$this->row('SELECT id FROM transacoes_cashback WHERE id=? AND usuario_id=? AND loja_id=?', [$id, $user, $store])) { throw new GiftbackException('A transação não pertence à carteira selecionada.', 409); }
    }

    public function credit(int $userId, int $storeId, int $amountCents, string $description = '', ?int $transactionId = null, ?int $actorId = null, ?string $sourceKey = null): array
    {
        if ($amountCents <= 0) { throw new GiftbackException('O crédito deve ser positivo.'); }
        $source = $transactionId ? 'sale:' . $transactionId : 'manual:' . ($sourceKey ?? bin2hex(random_bytes(16)));
        return $this->atomic(function () use ($userId, $storeId, $amountCents, $description, $transactionId, $actorId, $source) {
            // Policy and ledger use the same store->wallet lock order as sale creation.
            $store = $this->row('SELECT giftback_expiration_days FROM lojas WHERE id=? FOR UPDATE', [$storeId]);
            if (!$store) { throw new GiftbackException('Loja não encontrada.', 404); }
            $this->lockWallet($userId, $storeId);
            $this->expireLocked($userId, $storeId);
            $this->verifyTransaction($transactionId, $userId, $storeId);
            $existing = $this->row('SELECT * FROM cashback_creditos WHERE usuario_id=? AND loja_id=? AND source_key=?', [$userId, $storeId, $source]);
            if ($existing) {
                if ((int) $existing['original_cents'] !== $amountCents) { throw new GiftbackException('Origem já creditada com valor diferente.', 409); }
                return ['balanceCents' => $this->available($userId, $storeId), 'movementId' => (int) $existing['origin_movement_id'], 'creditId' => (int) $existing['id'], 'replayed' => true];
            }
            if ($transactionId) {
                $old = $this->row("SELECT m.* FROM cashback_movimentacoes m JOIN cashback_credito_carteiras w ON w.usuario_id=m.usuario_id AND w.loja_id=m.loja_id WHERE m.usuario_id=? AND m.loja_id=? AND m.transacao_origem_id=? AND m.tipo_operacao='credito' AND m.id<=w.cutoff_movement_id LIMIT 1", [$userId, $storeId, $transactionId]);
                if ($old) { return ['balanceCents' => $this->available($userId, $storeId), 'movementId' => (int) $old['id'], 'creditId' => null, 'replayed' => true]; }
            }
            $days = $store['giftback_expiration_days'] === null ? null : (int) $store['giftback_expiration_days'];
            if ($days !== null && ($days < 1 || $days > 36500)) { throw new GiftbackException('Prazo da loja inválido.', 409); }
            $expires = $days === null ? null : $this->clock()->setTime(0, 0)->modify('+' . ($days + 1) . ' days')->format('Y-m-d H:i:s');
            [$movement, $before, $after] = $this->movement($userId, $storeId, 'credito', $amountCents, $description, $actorId, $transactionId, null, $amountCents);
            $this->execute("INSERT INTO cashback_creditos(usuario_id,loja_id,source_key,transacao_id,origin_movement_id,kind,original_cents,remaining_cents,credited_at,expires_at,policy_days) VALUES(?,?,?,?,?,'grant',?,?,?,?,?)", [$userId, $storeId, $source, $transactionId, $movement, $amountCents, $amountCents, $this->timestamp(), $expires, $days]);
            $credit = (int) $this->db->lastInsertId();
            $this->event($credit, 'credito', $amountCents, $before, $after, $description, $movement, $actorId, null, $expires);
            $this->assertWallet($userId, $storeId);
            return ['balanceCents' => $after, 'movementId' => $movement, 'creditId' => $credit, 'replayed' => false];
        });
    }

    public function spend(int $userId, int $storeId, int $amountCents, string $description = '', ?int $transactionId = null, ?int $actorId = null, ?string $sourceKey = null): array
    {
        if ($amountCents <= 0) { throw new GiftbackException('O uso deve ser positivo.'); }
        $key = 'spend:' . $userId . ':' . $storeId . ':' . ($transactionId ?? ($sourceKey ?? bin2hex(random_bytes(16))));
        return $this->atomic(function () use ($userId, $storeId, $amountCents, $description, $transactionId, $actorId, $key) {
            $this->lockWallet($userId, $storeId); $this->expireLocked($userId, $storeId);
            $this->verifyTransaction($transactionId, $userId, $storeId);
            if ($transactionId !== null) {
                // A sale may have belonged to a store visitor before the wallet
                // was claimed. Its original idempotency key contains the old ID.
                $existingUse = $this->row("SELECT id,valor FROM cashback_movimentacoes WHERE usuario_id=? AND loja_id=? AND transacao_uso_id=? AND tipo_operacao='uso' LIMIT 1", [$userId, $storeId, $transactionId]);
                if ($existingUse) {
                    if (self::cents($existingUse['valor']) !== $amountCents) { throw new GiftbackException('Uso repetido com valor diferente.', 409); }
                    return ['balanceCents' => $this->available($userId, $storeId), 'movementId' => (int) $existingUse['id'], 'replayed' => true];
                }
            }
            return $this->operation($key, [$userId, $storeId, $amountCents, $transactionId], function () use ($userId, $storeId, $amountCents, $description, $transactionId, $actorId) {
                if ($this->available($userId, $storeId) < $amountCents) { throw new GiftbackException('Saldo insuficiente após considerar os vencimentos.', 409); }
                [$movement, $before, $after] = $this->movement($userId, $storeId, 'uso', -$amountCents, $description, $actorId, null, $transactionId, 0, $amountCents);
                $remaining = $amountCents; $running = $before;
                $credits = $this->rows('SELECT * FROM cashback_creditos WHERE usuario_id=? AND loja_id=? AND remaining_cents>0 ORDER BY expires_at IS NULL,expires_at,credited_at,id FOR UPDATE', [$userId, $storeId]);
                foreach ($credits as $credit) {
                    if ($remaining === 0) { break; }
                    $part = min($remaining, (int) $credit['remaining_cents']);
                    $this->execute('UPDATE cashback_creditos SET remaining_cents=remaining_cents-?,consumed_cents=consumed_cents+?,version=version+1 WHERE id=?', [$part, $part, $credit['id']]);
                    $this->execute('INSERT INTO cashback_credito_alocacoes(movement_id,credit_id,amount_cents) VALUES(?,?,?)', [$movement, $credit['id'], $part]);
                    $this->event((int) $credit['id'], 'uso', $part, $running, $running - $part, $description, $movement, $actorId);
                    $remaining -= $part; $running -= $part;
                }
                if ($remaining !== 0) { throw new GiftbackException('Alocação do saldo incompleta.', 409); }
                $this->assertWallet($userId, $storeId);
                return ['balanceCents' => $after, 'movementId' => $movement];
            });
        });
    }

    private function expireLocked(int $user, int $store): int
    {
        $expired = 0;
        $credits = $this->rows('SELECT * FROM cashback_creditos WHERE usuario_id=? AND loja_id=? AND remaining_cents>0 AND expires_at<=? ORDER BY id FOR UPDATE', [$user, $store, $this->timestamp()]);
        foreach ($credits as $credit) {
            $amount = (int) $credit['remaining_cents'];
            [$movement, $before, $after] = $this->movement($user, $store, 'expiracao', -$amount, 'Expiração do giftback #' . $credit['id'], null, $credit['transacao_id'] ? (int) $credit['transacao_id'] : null);
            $this->execute('UPDATE cashback_creditos SET remaining_cents=0,expired_cents=expired_cents+?,version=version+1 WHERE id=?', [$amount, $credit['id']]);
            $this->event((int) $credit['id'], 'expiracao', $amount, $before, $after, 'Prazo do crédito encerrado.', $movement, null, $credit['expires_at'], $credit['expires_at']);
            $expired += $amount;
        }
        return $expired;
    }

    public function settleWallet(int $userId, int $storeId): int
    {
        return $this->atomic(function () use ($userId, $storeId) {
            $this->lockWallet($userId, $storeId); $this->expireLocked($userId, $storeId); $this->assertWallet($userId, $storeId);
            return $this->available($userId, $storeId);
        });
    }

    /** The network is a view over origin wallets, not a transfer of credits. */
    public function networkWallet(int $userId, array $storeIds): array
    {
        $stores = array_values(array_unique(array_map('intval', $storeIds)));
        sort($stores, SORT_NUMERIC);
        if ($stores === []) { throw new GiftbackException('Rede sem filiais ativas.', 409); }
        return $this->atomic(function () use ($userId, $stores) {
            $available = 0; $credits = []; $nextDate = null; $nextAmount = 0;
            foreach ($stores as $store) {
                $wallet = $this->wallet($userId, $store);
                $available += $wallet['availableCents'];
                foreach ($wallet['credits'] as $credit) { $credits[] = $credit; }
                $date = $wallet['nextExpirationDate'];
                if ($date !== null && ($nextDate === null || $date < $nextDate)) {
                    $nextDate = $date; $nextAmount = $wallet['nextExpirationCents'];
                } elseif ($date !== null && $date === $nextDate) {
                    $nextAmount += $wallet['nextExpirationCents'];
                }
            }
            usort($credits, static fn (array $a, array $b): int => strcmp($b['creditedAt'], $a['creditedAt']) ?: ($b['id'] <=> $a['id']));
            return ['availableCents' => $available, 'nextExpirationDate' => $nextDate,
                'nextExpirationCents' => $nextAmount, 'credits' => $credits];
        });
    }

    public function spendNetwork(int $userId, int $redemptionStoreId, array $sourceStoreIds, int $amountCents, int $transactionId, ?int $actorId, ?int $networkId): array
    {
        if ($amountCents <= 0) { throw new GiftbackException('O uso deve ser positivo.'); }
        $stores = array_values(array_unique(array_map('intval', $sourceStoreIds)));
        sort($stores, SORT_NUMERIC);
        if (!in_array($redemptionStoreId, $stores, true)) { throw new GiftbackException('Filial fora da rede.', 403); }
        return $this->atomic(function () use ($userId, $redemptionStoreId, $stores, $amountCents, $transactionId, $actorId, $networkId) {
            $this->verifyTransaction($transactionId, $userId, $redemptionStoreId);
            foreach ($stores as $store) { $this->lockWallet($userId, $store); $this->expireLocked($userId, $store); }
            return $this->operation('network-spend:' . $transactionId,
                [$userId, $redemptionStoreId, $stores, $amountCents, $networkId],
                function () use ($userId, $redemptionStoreId, $stores, $amountCents, $transactionId, $actorId, $networkId) {
                    $total = 0; $lots = [];
                    foreach ($stores as $store) {
                        $total += $this->available($userId, $store);
                        foreach ($this->rows('SELECT * FROM cashback_creditos WHERE usuario_id=? AND loja_id=? AND remaining_cents>0 ORDER BY id FOR UPDATE', [$userId, $store]) as $lot) {
                            $lots[] = $lot;
                        }
                    }
                    if ($total < $amountCents) { throw new GiftbackException('Saldo insuficiente após os vencimentos.', 409); }
                    usort($lots, static function (array $a, array $b): int {
                        return ((int) ($a['expires_at'] === null) <=> (int) ($b['expires_at'] === null))
                            ?: strcmp((string) $a['expires_at'], (string) $b['expires_at'])
                            ?: strcmp($a['credited_at'], $b['credited_at']) ?: ((int) $a['id'] <=> (int) $b['id']);
                    });
                    $remaining = $amountCents; $allocations = [];
                    foreach ($lots as $lot) {
                        if ($remaining === 0) { break; }
                        $part = min($remaining, (int) $lot['remaining_cents']);
                        $sourceStore = (int) $lot['loja_id'];
                        [$movement, $before, $after] = $this->movement($userId, $sourceStore, 'uso', -$part,
                            'Uso de giftback na filial #' . $redemptionStoreId . ' venda #' . $transactionId,
                            $actorId, null, $transactionId, 0, $part);
                        $this->execute('UPDATE cashback_movimentacoes SET redemption_store_id=?,network_id_snapshot=? WHERE id=?',
                            [$redemptionStoreId, $networkId, $movement]);
                        $this->execute('UPDATE cashback_creditos SET remaining_cents=remaining_cents-?,consumed_cents=consumed_cents+?,version=version+1 WHERE id=?', [$part, $part, $lot['id']]);
                        $this->execute('INSERT INTO cashback_credito_alocacoes(movement_id,credit_id,amount_cents) VALUES(?,?,?)', [$movement, $lot['id'], $part]);
                        $this->event((int) $lot['id'], 'uso', $part, $before, $after,
                            'Uso na filial #' . $redemptionStoreId, $movement, $actorId);
                        $allocations[] = ['creditId' => (int) $lot['id'], 'originStoreId' => $sourceStore,
                            'redemptionStoreId' => $redemptionStoreId, 'amountCents' => $part, 'movementId' => $movement];
                        $remaining -= $part;
                    }
                    if ($remaining !== 0) { throw new GiftbackException('Alocação incompleta.', 409); }
                    foreach ($stores as $store) { $this->assertWallet($userId, $store); }
                    return ['balanceCents' => $total - $amountCents, 'allocations' => $allocations];
                });
        });
    }
    public function settleUser(int $userId): void
    {
        foreach ($this->rows('SELECT loja_id FROM cashback_saldos WHERE usuario_id=? ORDER BY loja_id', [$userId]) as $row) { $this->settleWallet($userId, (int) $row['loja_id']); }
    }
    public function settleStore(int $storeId): void
    {
        foreach ($this->rows('SELECT usuario_id FROM cashback_saldos WHERE loja_id=? ORDER BY usuario_id', [$storeId]) as $row) { $this->settleWallet((int) $row['usuario_id'], $storeId); }
    }

    public function wallet(int $userId, int $storeId): array
    {
        return $this->atomic(function () use ($userId, $storeId) {
            $balance = $this->settleWallet($userId, $storeId);
            $credits = $this->readCredits('c.usuario_id=? AND c.loja_id=?', [$userId, $storeId]);
            $date = null; $amount = 0;
            foreach ($credits as $credit) {
                if ($credit['remainingCents'] <= 0 || $credit['validUntil'] === null) { continue; }
                if ($date === null || $credit['validUntil'] < $date) { $date = $credit['validUntil']; $amount = 0; }
                if ($date === $credit['validUntil']) { $amount += $credit['remainingCents']; }
            }
            return ['availableCents' => $balance, 'nextExpirationDate' => $date, 'nextExpirationCents' => $amount, 'credits' => $credits];
        });
    }

    private function validUntil(?string $expires): ?string
    {
        return $expires ? (new DateTimeImmutable($expires, $this->zone))->modify('-1 day')->format('Y-m-d') : null;
    }
    private function iso(?string $value): ?string { return $value ? (new DateTimeImmutable($value, $this->zone))->format(DATE_ATOM) : null; }

    private function readCredits(string $where, array $params, ?int $limit = null, int $offset = 0): array
    {
        $sql = 'SELECT c.*,u.nome customer_name,l.nome_fantasia store_name FROM cashback_creditos c JOIN usuarios u ON u.id=c.usuario_id JOIN lojas l ON l.id=c.loja_id WHERE ' . $where . ' ORDER BY c.credited_at DESC,c.id DESC';
        if ($limit !== null) { $sql .= ' LIMIT ' . $limit . ' OFFSET ' . $offset; }
        $credits = $this->rows($sql, $params);
        if (!$credits) { return []; }
        $ids = array_column($credits, 'id'); $eventsByCredit = [];
        $events = $this->rows('SELECT e.*,r.id restored_id,c.revoked_cents FROM cashback_credito_eventos e JOIN cashback_creditos c ON c.id=e.credit_id LEFT JOIN cashback_credito_eventos r ON r.related_event_id=e.id AND r.type=\'reversao_expiracao\' WHERE e.credit_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY e.id', $ids);
        foreach ($events as $event) {
            $eventsByCredit[$event['credit_id']][] = [
                'id' => (int) $event['id'], 'type' => $event['type'], 'amountCents' => (int) $event['amount_cents'],
                'previousCents' => (int) $event['previous_cents'], 'currentCents' => (int) $event['current_cents'],
                'oldValidUntil' => $this->validUntil($event['old_expires_at']), 'newValidUntil' => $this->validUntil($event['new_expires_at']),
                'actorId' => $event['actor_id'] === null ? null : (int) $event['actor_id'], 'actorName' => $event['actor_name'],
                'reason' => $event['reason'], 'occurredAt' => $this->iso($event['occurred_at']),
                'reversibleCents' => $event['type'] === 'expiracao' && !$event['restored_id'] && !(int) $event['revoked_cents'] ? (int) $event['amount_cents'] : 0,
            ];
        }
        return array_map(function (array $credit) use ($eventsByCredit): array {
            $status = (int) $credit['remaining_cents'] > 0 ? 'active' : ((int) $credit['expired_cents'] > 0 ? 'expired' : ((int) $credit['revoked_cents'] > 0 && (int) $credit['consumed_cents'] === 0 ? 'revoked' : 'exhausted'));
            return [
                'id' => (int) $credit['id'], 'userId' => (int) $credit['usuario_id'], 'storeId' => (int) $credit['loja_id'],
                'customerName' => $credit['customer_name'], 'storeName' => $credit['store_name'], 'transactionId' => $credit['transacao_id'] === null ? null : (int) $credit['transacao_id'],
                'originalCents' => (int) $credit['original_cents'], 'remainingCents' => (int) $credit['remaining_cents'],
                'consumedCents' => (int) $credit['consumed_cents'], 'expiredCents' => (int) $credit['expired_cents'], 'revokedCents' => (int) $credit['revoked_cents'],
                'creditedAt' => $this->iso($credit['credited_at']), 'expiresAt' => $this->iso($credit['expires_at']), 'validUntil' => $this->validUntil($credit['expires_at']),
                'version' => (int) $credit['version'], 'kind' => $credit['kind'], 'status' => $status, 'events' => $eventsByCredit[$credit['id']] ?? [],
            ];
        }, $credits);
    }

    public function listCredits(?int $userId, ?int $storeId, array $filters = [], int $page = 1, int $pageSize = 20): array
    {
        if ($userId !== null && $storeId !== null) { $this->settleWallet($userId, $storeId); }
        elseif ($userId !== null) { $this->settleUser($userId); }
        elseif ($storeId !== null) { $this->settleStore($storeId); }
        else { throw new GiftbackException('Selecione um cliente ou uma loja.'); }
        $where = ['1=1']; $params = [];
        if ($userId !== null) { $where[] = 'c.usuario_id=?'; $params[] = $userId; }
        if ($storeId !== null) { $where[] = 'c.loja_id=?'; $params[] = $storeId; }
        $statuses = ['active' => 'c.remaining_cents>0', 'expired' => 'c.remaining_cents=0 AND c.expired_cents>0', 'exhausted' => 'c.remaining_cents=0 AND c.expired_cents=0 AND (c.revoked_cents=0 OR c.consumed_cents>0)', 'revoked' => 'c.remaining_cents=0 AND c.expired_cents=0 AND c.consumed_cents=0 AND c.revoked_cents>0'];
        if (!empty($filters['status'])) {
            if (!isset($statuses[$filters['status']])) { throw new GiftbackException('Situação de crédito inválida.'); }
            $where[] = $statuses[$filters['status']];
        }
        if (trim((string) ($filters['search'] ?? '')) !== '') { $where[] = 'EXISTS (SELECT 1 FROM usuarios u WHERE u.id=c.usuario_id AND u.nome LIKE ?)'; $params[] = '%' . trim($filters['search']) . '%'; }
        $clause = implode(' AND ', $where); $page = max(1, $page); $pageSize = max(1, min(100, $pageSize));
        $total = $this->row('SELECT COUNT(*) total FROM cashback_creditos c WHERE ' . $clause, $params);
        return ['items' => $this->readCredits($clause, $params, $pageSize, ($page - 1) * $pageSize), 'total' => (int) $total['total'], 'page' => $page, 'pageSize' => $pageSize];
    }

    public function listCreditsForStores(int $userId, array $storeIds, int $page = 1, int $pageSize = 20): array
    {
        $stores = array_values(array_unique(array_map('intval', $storeIds)));
        sort($stores, SORT_NUMERIC);
        if ($stores === []) { throw new GiftbackException('Selecione uma filial.', 422); }
        foreach ($stores as $store) { $this->settleWallet($userId, $store); }
        $where = 'c.usuario_id=? AND c.loja_id IN (' . implode(',', $stores) . ')';
        $page = max(1, $page); $pageSize = max(1, min(100, $pageSize));
        $total = $this->row('SELECT COUNT(*) total FROM cashback_creditos c WHERE ' . $where, [$userId]);
        return ['items' => $this->readCredits($where, [$userId], $pageSize, ($page - 1) * $pageSize),
            'total' => (int) $total['total'], 'page' => $page, 'pageSize' => $pageSize];
    }

    public function creditDetail(int $creditId, ?int $userId = null): array
    {
        $credit = $this->row('SELECT * FROM cashback_creditos WHERE id=?', [$creditId]);
        if (!$credit || ($userId !== null && (int) $credit['usuario_id'] !== $userId)) { throw new GiftbackException('Crédito não encontrado.', 404); }
        $this->settleWallet((int) $credit['usuario_id'], (int) $credit['loja_id']);
        return $this->readCredits('c.id=?', [$creditId])[0];
    }

    private function admin(int $actor): void
    {
        if (!$this->row("SELECT id FROM usuarios WHERE id=? AND tipo='admin' AND status='ativo'", [$actor])) { throw new GiftbackException('Ação exclusiva de administrador ativo da KlubeCash.', 403); }
    }
    private function newExpiry(string $validUntil): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $validUntil, $this->zone);
        if (!$date || $date->format('Y-m-d') !== $validUntil || $validUntil <= $this->clock()->format('Y-m-d')) { throw new GiftbackException('Escolha uma data futura válida.'); }
        return $date->modify('+1 day')->format('Y-m-d H:i:s');
    }
    private function audit(int $actor, string $action, int $id, array $before, array $after): void
    {
        $requestId = class_exists('App\\Core\\RequestContext') ? \App\Core\RequestContext::id() : bin2hex(random_bytes(16));
        $this->execute("INSERT INTO admin_audit_logs(actor_id,action,entity_type,entity_id,result,before_json,after_json,request_id) VALUES(?,?,'giftback_credit',?,'success',?,?,?)", [$actor, $action, (string) $id, json_encode($before, JSON_UNESCAPED_UNICODE), json_encode($after, JSON_UNESCAPED_UNICODE), $requestId]);
    }

    public function extendCredit(int $creditId, int $actorId, string $validUntil, string $reason, int $expectedVersion, string $key): array
    {
        return $this->adminChange($creditId, null, $actorId, $validUntil, $reason, $expectedVersion, $key);
    }
    public function restoreCredit(int $creditId, int $expirationEventId, int $actorId, string $validUntil, string $reason, int $expectedVersion, string $key): array
    {
        return $this->adminChange($creditId, $expirationEventId, $actorId, $validUntil, $reason, $expectedVersion, $key);
    }
    private function adminChange(int $creditId, ?int $expirationEvent, int $actor, string $date, string $reason, int $version, string $key): array
    {
        $this->admin($actor);
        if (strlen(trim($reason)) < 5 || strlen($reason) > 2000) { throw new GiftbackException('Informe um motivo entre 5 e 2000 caracteres.'); }
        if ($key === '' || strlen($key) > 128) { throw new GiftbackException('Chave de idempotência obrigatória.', 400); }
        $new = $this->newExpiry($date);
        $peek = $this->row('SELECT usuario_id,loja_id FROM cashback_creditos WHERE id=?', [$creditId]);
        if (!$peek) { throw new GiftbackException('Crédito não encontrado.', 404); }
        $user = (int) $peek['usuario_id']; $store = (int) $peek['loja_id'];
        // Persist expiry independently before rejecting a stale extend/version.
        $this->settleWallet($user, $store);
        return $this->atomic(function () use ($creditId, $expirationEvent, $actor, $date, $reason, $version, $key, $new, $user, $store) {
            $this->admin($actor); $this->lockWallet($user, $store); $this->expireLocked($user, $store);
            $action = $expirationEvent === null ? 'extend' : 'restore';
            $opKey = 'admin:' . $actor . ':' . $action . ':' . hash('sha256', $key);
            return $this->operation($opKey, [$creditId, $expirationEvent, $date, $reason, $version], function () use ($creditId, $expirationEvent, $actor, $reason, $version, $new, $user, $store, $action) {
                $credit = $this->row('SELECT * FROM cashback_creditos WHERE id=? FOR UPDATE', [$creditId]);
                if ((int) $credit['version'] !== $version) { throw new GiftbackException('O crédito mudou. Atualize os dados antes de continuar.', 409); }
                if ((int) $credit['revoked_cents'] > 0) { throw new GiftbackException('Crédito revogado não pode ser reativado ou prorrogado.', 409); }
                if ($expirationEvent === null) {
                    if (!$credit['expires_at'] || $credit['expires_at'] <= $this->timestamp() || (int) $credit['remaining_cents'] <= 0 || $new <= $credit['expires_at']) { throw new GiftbackException('Prorrogue somente crédito ainda válido, com saldo e uma data posterior.', 409); }
                    $balance = $this->available($user, $store);
                    $this->event($creditId, 'prorrogacao', 0, $balance, $balance, trim($reason), null, $actor, $credit['expires_at'], $new);
                } else {
                    $event = $this->row("SELECT * FROM cashback_credito_eventos WHERE id=? AND credit_id=? AND type='expiracao'", [$expirationEvent, $creditId]);
                    $reversed = $this->row("SELECT id FROM cashback_credito_eventos WHERE related_event_id=? AND type='reversao_expiracao'", [$expirationEvent]);
                    if (!$event || $reversed || (int) $event['amount_cents'] > (int) $credit['expired_cents']) { throw new GiftbackException('Esta expiração não possui valor disponível para reversão.', 409); }
                    if ((int) $credit['remaining_cents'] > 0 && $credit['expires_at'] && $new < $credit['expires_at']) { throw new GiftbackException('A nova data não pode reduzir a validade do saldo já reativado.'); }
                    $amount = (int) $event['amount_cents'];
                    [$movement, $before, $after] = $this->movement($user, $store, 'reversao_expiracao', $amount, 'Reativação administrativa do giftback #' . $creditId, $actor, $credit['transacao_id'] ? (int) $credit['transacao_id'] : null);
                    $this->execute('UPDATE cashback_creditos SET expired_cents=expired_cents-?,remaining_cents=remaining_cents+? WHERE id=?', [$amount, $amount, $creditId]);
                    $this->event($creditId, 'reversao_expiracao', $amount, $before, $after, trim($reason), $movement, $actor, $credit['expires_at'], $new, $expirationEvent);
                }
                $this->execute('UPDATE cashback_creditos SET expires_at=?,version=version+1 WHERE id=?', [$new, $creditId]);
                $after = $this->readCredits('c.id=?', [$creditId])[0];
                $this->audit($actor, 'giftback.credit.' . $action, $creditId, $credit, $after);
                $this->assertWallet($user, $store);
                return $after;
            });
        });
    }

    public function expireDue(int $limit = 100, bool $dryRun = false): array
    {
        $limit = max(1, min(1000, $limit));
        $wallets = $this->rows('SELECT usuario_id,loja_id,SUM(remaining_cents) amount FROM cashback_creditos WHERE remaining_cents>0 AND expires_at<=? GROUP BY usuario_id,loja_id ORDER BY usuario_id,loja_id LIMIT ' . $limit, [$this->timestamp()]);
        $processed = 0; $expired = 0; $failed = [];
        foreach ($wallets as $wallet) {
            if ($dryRun) { $expired += (int) $wallet['amount']; continue; }
            try {
                $expired += $this->atomic(function () use ($wallet) {
                    $user = (int) $wallet['usuario_id']; $store = (int) $wallet['loja_id'];
                    $this->lockWallet($user, $store); $amount = $this->expireLocked($user, $store); $this->assertWallet($user, $store); return $amount;
                });
                ++$processed;
            } catch (Throwable $error) {
                // One inconsistent wallet does not prevent other customers'
                // expiry. Report failure so operators can reconcile and retry.
                $failed[] = ['userId' => (int) $wallet['usuario_id'], 'storeId' => (int) $wallet['loja_id']];
                error_log('giftback.expiration.wallet_failed ' . json_encode(end($failed)) . ' ' . get_class($error));
            }
        }
        return ['dryRun' => $dryRun, 'candidates' => count($wallets), 'processed' => $processed, 'expiredCents' => $expired, 'failed' => count($failed), 'failedWallets' => $failed, 'processedAt' => $this->clock()->format(DATE_ATOM)];
    }

    /** Reverse financial legs, not the sale status (owned by caller). */
    public function reverseSale(int $userId, int $storeId, int $transactionId, string $reason, ?int $actorId = null): array
    {
        return $this->atomic(function () use ($userId, $storeId, $transactionId, $reason, $actorId) {
            $this->verifyTransaction($transactionId, $userId, $storeId);
            $sourceRows = $this->rows("SELECT DISTINCT loja_id FROM cashback_movimentacoes WHERE usuario_id=? AND transacao_uso_id=? AND tipo_operacao='uso'", [$userId, $transactionId]);
            $sourceStores = array_values(array_unique(array_merge([$storeId], array_map('intval', array_column($sourceRows, 'loja_id')))));
            sort($sourceStores, SORT_NUMERIC);
            foreach ($sourceStores as $sourceStore) { $this->lockWallet($userId, $sourceStore); $this->expireLocked($userId, $sourceStore); }
            $priorReversal = $this->row('SELECT response_json FROM cashback_credito_operacoes WHERE operation_key=? FOR UPDATE', ['reverse:' . $transactionId]);
            if ($priorReversal) {
                return array_replace((array) json_decode($priorReversal['response_json'], true), ['balanceCents' => $this->available($userId, $storeId), 'replayed' => true]);
            }
            return $this->operation('reverse:' . $transactionId, [$userId, $storeId, $transactionId], function () use ($userId, $storeId, $transactionId, $reason, $actorId, $sourceStores) {
                $marker = $this->row('SELECT cutoff_movement_id FROM cashback_credito_carteiras WHERE usuario_id=? AND loja_id=?', [$userId, $storeId]);
                $uses = $this->rows("SELECT * FROM cashback_movimentacoes WHERE usuario_id=? AND transacao_uso_id=? AND tipo_operacao='uso' ORDER BY loja_id,id", [$userId, $transactionId]);
                $restored = 0;
                foreach ($uses as $use) {
                    $sourceStore = (int) $use['loja_id'];
                    $sourceMarker = $this->row('SELECT cutoff_movement_id FROM cashback_credito_carteiras WHERE usuario_id=? AND loja_id=?', [$userId, $sourceStore]);
                    $allocations = $this->rows('SELECT a.*,c.expires_at,c.revoked_cents,c.kind FROM cashback_credito_alocacoes a JOIN cashback_creditos c ON c.id=a.credit_id WHERE a.movement_id=? ORDER BY a.credit_id FOR UPDATE', [$use['id']]);
                    if (!$allocations) {
                        if ((int) $use['id'] > (int) $sourceMarker['cutoff_movement_id']) { throw new GiftbackException('Uso sem alocação: revisão administrativa necessária.', 409); }
                        $amount = self::cents($use['valor']);
                        [$movement, $before, $after] = $this->movement($userId, $sourceStore, 'estorno', $amount, 'Restituição de uso anterior à migração', $actorId, null, $transactionId, 0, -$amount);
                        $this->execute("INSERT INTO cashback_creditos(usuario_id,loja_id,source_key,kind,original_cents,remaining_cents,credited_at,origin_movement_id) VALUES(?,?,?,'legacy_refund',?,?,?,?)", [$userId, $sourceStore, 'legacy-refund:' . $use['id'], $amount, $amount, $this->timestamp(), $movement]);
                        $this->event((int) $this->db->lastInsertId(), 'estorno', $amount, $before, $after, $reason, $movement, $actorId);
                        $restored += $amount;
                        continue;
                    }
                    foreach ($allocations as $allocation) {
                        $amount = (int) $allocation['amount_cents'] - (int) $allocation['refunded_cents'];
                        if ($amount <= 0) { continue; }
                        if ((int) $allocation['revoked_cents'] > 0 && $allocation['kind'] === 'grant') { throw new GiftbackException('Origem revogada; revisão administrativa necessária.', 409); }
                        [$movement, $before, $after] = $this->movement($userId, $sourceStore, 'estorno', $amount, 'Devolução de saldo usado na venda #' . $transactionId, $actorId, null, $transactionId, 0, -$amount);
                        $this->execute('UPDATE cashback_creditos SET remaining_cents=remaining_cents+?,consumed_cents=consumed_cents-?,version=version+1 WHERE id=?', [$amount, $amount, $allocation['credit_id']]);
                        $this->execute('UPDATE cashback_credito_alocacoes SET refunded_cents=refunded_cents+? WHERE id=?', [$amount, $allocation['id']]);
                        $this->event((int) $allocation['credit_id'], 'estorno', $amount, $before, $after, $reason, $movement, $actorId);
                        $restored += $amount;
                    }
                }
                // Returned funds retain their deadlines, including ones that already elapsed.
                foreach ($sourceStores as $sourceStore) { $this->expireLocked($userId, $sourceStore); }
                $grants = $this->rows("SELECT * FROM cashback_movimentacoes WHERE usuario_id=? AND loja_id=? AND transacao_origem_id=? AND tipo_operacao='credito' ORDER BY id", [$userId, $storeId, $transactionId]);
                $revoked = 0;
                foreach ($grants as $grant) {
                    $credit = $this->row('SELECT * FROM cashback_creditos WHERE origin_movement_id=? AND usuario_id=? AND loja_id=? FOR UPDATE', [$grant['id'], $userId, $storeId]);
                    $amount = self::cents($grant['valor']);
                    if ($credit) {
                        if ((int) $credit['consumed_cents'] > 0) { throw new GiftbackException('O giftback desta venda já foi utilizado e exige revisão manual.', 409); }
                        if ((int) $credit['revoked_cents'] > 0) { continue; }
                        $remaining = (int) $credit['remaining_cents'];
                        [$movement, $before, $after] = $this->movement($userId, $storeId, 'revogacao', -$remaining, 'Revogação do giftback da venda #' . $transactionId, $actorId, $transactionId, null, -$amount);
                        $this->execute('UPDATE cashback_creditos SET remaining_cents=0,expired_cents=0,revoked_cents=original_cents,version=version+1 WHERE id=?', [$credit['id']]);
                        $this->event((int) $credit['id'], 'revogacao', $amount, $before, $after, $reason, $movement, $actorId);
                    } else {
                        if ((int) $grant['id'] > (int) $marker['cutoff_movement_id']) { throw new GiftbackException('Crédito sem origem individual; revisão administrativa necessária.', 409); }
                        $legacy = $this->rows("SELECT * FROM cashback_creditos WHERE usuario_id=? AND loja_id=? AND kind IN ('opening','legacy_refund') AND remaining_cents>0 ORDER BY id FOR UPDATE", [$userId, $storeId]);
                        if (array_sum(array_column($legacy, 'remaining_cents')) < $amount) { throw new GiftbackException('Saldo legado insuficiente para este estorno; revisão manual necessária.', 409); }
                        $left = $amount;
                        foreach ($legacy as $lot) {
                            if ($left === 0) { break; }
                            $part = min($left, (int) $lot['remaining_cents']);
                            [$movement, $before, $after] = $this->movement($userId, $storeId, 'revogacao', -$part, 'Revogação de crédito anterior à migração', $actorId, $transactionId, null, -$part);
                            $this->execute('UPDATE cashback_creditos SET remaining_cents=remaining_cents-?,revoked_cents=revoked_cents+?,version=version+1 WHERE id=?', [$part, $part, $lot['id']]);
                            $this->event((int) $lot['id'], 'revogacao', $part, $before, $after, $reason, $movement, $actorId);
                            $left -= $part;
                        }
                    }
                    $revoked += $amount;
                }
                foreach ($sourceStores as $sourceStore) { $this->assertWallet($userId, $sourceStore); }
                return ['balanceCents' => $this->available($userId, $storeId), 'restoredBalanceUsedCents' => $restored, 'reversedCashbackCents' => $revoked];
            });
        });
    }
}
