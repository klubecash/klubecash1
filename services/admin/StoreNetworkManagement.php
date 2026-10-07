<?php

declare(strict_types=1);

namespace App\Services\Admin;

use PDO;
use Throwable;

final class StoreNetworkManagement
{
    public function __construct(private PDO $db, private int $actorId) {}

    private function admin(): void
    {
        $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE id=? AND tipo='admin' AND status='ativo'");
        $stmt->execute([$this->actorId]);
        if (!$stmt->fetchColumn()) { throw new AdminApiException('Administrador ativo obrigatório.', 403); }
    }

    private function event(int $networkId, ?int $storeId, string $action, string $reason, ?array $before, ?array $after): void
    {
        $stmt = $this->db->prepare('INSERT INTO store_network_events(network_id,store_id,actor_id,action,reason,before_json,after_json) VALUES(?,?,?,?,?,?,?)');
        $stmt->execute([$networkId, $storeId, $this->actorId, $action, $reason,
            $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE),
            $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE)]);
    }

    private function hasRepairAuditTable(): bool
    {
        $stmt = $this->db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='store_wallet_reconciliation_events' LIMIT 1");
        return (bool) $stmt->fetchColumn();
    }

    public function list(): array
    {
        $this->admin();
        $rows = $this->db->query("SELECT n.id,n.name,n.status,n.version,
            COUNT(CASE WHEN m.status='active' THEN 1 END) active_stores,
            COUNT(CASE WHEN m.status='suspended' THEN 1 END) suspended_stores
            FROM store_networks n LEFT JOIN store_network_memberships m ON m.network_id=n.id
            GROUP BY n.id ORDER BY n.name,n.id")->fetchAll(PDO::FETCH_ASSOC);
        return ['items' => $rows];
    }

    public function candidates(string $kind, string $search): array
    {
        $this->admin();
        $search = trim($search);
        if (mb_strlen($search) < 2) { return ['items' => []]; }
        $term = '%' . $search . '%';
        if ($kind === 'stores') {
            $stmt = $this->db->prepare("SELECT l.id,l.nome_fantasia name,l.cnpj,n.network_id existing_network_id
                FROM lojas l LEFT JOIN store_network_memberships n ON n.store_id=l.id
                WHERE l.status='aprovado' AND (l.nome_fantasia LIKE ? OR l.cnpj LIKE ?)
                ORDER BY l.nome_fantasia,l.id LIMIT 20");
            $stmt->execute([$term, $term]);
        } elseif ($kind === 'managers') {
            $stmt = $this->db->prepare("SELECT id,nome name,email,tipo account_type FROM usuarios
                WHERE status='ativo' AND tipo IN ('loja','funcionario') AND (nome LIKE ? OR email LIKE ?)
                ORDER BY nome,id LIMIT 20");
            $stmt->execute([$term, $term]);
        } else { throw new AdminApiException('Tipo de busca inválido.', 422); }
        return ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    /** Each case is a wallet that must be reviewed before joining or resuming a branch. */
    public function walletHealth(int $networkId, ?int $candidateStoreId = null): array
    {
        $this->admin();
        $stmt = $this->db->prepare('SELECT id FROM store_networks WHERE id=?');
        $stmt->execute([$networkId]);
        if (!$stmt->fetchColumn()) { throw new AdminApiException('Rede não encontrada.', 404); }
        $stmt = $this->db->prepare("SELECT nm.store_id FROM store_network_memberships nm
            JOIN lojas l ON l.id=nm.store_id AND l.status='aprovado'
            WHERE nm.network_id=? AND nm.status='active'");
        $stmt->execute([$networkId]);
        $stores = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if ($candidateStoreId !== null) {
            $stmt = $this->db->prepare("SELECT id FROM lojas WHERE id=? AND status='aprovado'");
            $stmt->execute([$candidateStoreId]);
            if (!$stmt->fetchColumn()) { throw new AdminApiException('Filial aprovada não encontrada.', 404); }
            $stores[] = $candidateStoreId;
        }
        $stores = array_values(array_unique($stores));
        sort($stores, SORT_NUMERIC);
        if (!$stores) { return ['healthy' => true, 'caseCount' => 0, 'cases' => [], 'storeIds' => [], 'repairEvents' => [], 'repairReady' => $this->hasRepairAuditTable()]; }
        $list = implode(',', $stores);
        $wallets = [];
        foreach ($this->db->query("SELECT usuario_id,loja_id,ROUND(saldo_disponivel*100) cents FROM cashback_saldos WHERE loja_id IN ({$list})") as $row) {
            $key = $row['usuario_id'] . ':' . $row['loja_id'];
            $wallets[$key] = (int) $row['cents'];
        }
        $credits = [];
        foreach ($this->db->query("SELECT usuario_id,loja_id,SUM(remaining_cents) cents,
            SUM(CASE WHEN original_cents<>remaining_cents+consumed_cents+expired_cents+revoked_cents
                OR LEAST(original_cents,remaining_cents,consumed_cents,expired_cents,revoked_cents)<0 THEN 1 ELSE 0 END) invalid,
            SUM(CASE WHEN kind<>'grant' THEN 1 ELSE 0 END) non_grant,
            SUM(original_cents-revoked_cents) credited_cents,SUM(consumed_cents) used_cents
            FROM cashback_creditos WHERE loja_id IN ({$list}) GROUP BY usuario_id,loja_id") as $row) {
            $credits[$row['usuario_id'] . ':' . $row['loja_id']] = $row;
        }
        $markers = [];
        foreach ($this->db->query("SELECT usuario_id,loja_id,cutoff_movement_id FROM cashback_credito_carteiras WHERE loja_id IN ({$list})") as $row) {
            $markers[$row['usuario_id'] . ':' . $row['loja_id']] = (int) $row['cutoff_movement_id'];
        }
        $movements = [];
        foreach ($this->db->query("SELECT m.usuario_id,m.loja_id,m.id,ROUND(m.saldo_atual*100) cents
            FROM cashback_movimentacoes m JOIN (SELECT usuario_id,loja_id,MAX(id) id FROM cashback_movimentacoes
                WHERE loja_id IN ({$list}) GROUP BY usuario_id,loja_id) recent ON recent.id=m.id") as $row) {
            $movements[$row['usuario_id'] . ':' . $row['loja_id']] = ['id' => (int) $row['id'], 'cents' => (int) $row['cents']];
        }
        $keys = array_unique(array_merge(array_keys($wallets), array_keys($credits), array_keys($markers)));
        sort($keys);
        $cases = [];
        foreach ($keys as $key) {
            [$user, $store] = array_map('intval', explode(':', $key));
            $credit = $credits[$key] ?? null;
            $balance = $wallets[$key] ?? null;
            $creditCents = (int) ($credit['cents'] ?? 0);
            $issues = [];
            if ($balance === null) { $issues[] = 'missing_wallet'; }
            if (!array_key_exists($key, $markers)) { $issues[] = 'missing_marker'; }
            if ((int) ($credit['invalid'] ?? 0) > 0) { $issues[] = 'invalid_credit'; }
            if ($balance !== null && $balance !== $creditCents) { $issues[] = 'balance_mismatch'; }
            $last = $movements[$key] ?? null;
            if ($last !== null && array_key_exists($key, $markers) && $last['id'] > $markers[$key]
                && $last['cents'] !== $creditCents) { $issues[] = 'movement_mismatch'; }
            if ($issues) {
                $cases[] = ['userId' => $user, 'storeId' => $store, 'issues' => $issues,
                    'walletCents' => $balance, 'creditCents' => $creditCents,
                    'lastMovementCents' => $last['cents'] ?? null,
                    'canRepairMissingWallet' => $balance === null && array_key_exists($key, $markers)
                        && $credit !== null && (int) $credit['invalid'] === 0 && (int) $credit['non_grant'] === 0
                        && $last !== null && $last['cents'] === $creditCents];
            }
        }
        $repairReady = $this->hasRepairAuditTable();
        $events = [];
        if ($repairReady) {
            $events = $this->db->query("SELECT e.id,e.user_id,e.store_id,e.action,e.reason,e.before_json,e.after_json,e.occurred_at,u.nome actor_name
                FROM store_wallet_reconciliation_events e JOIN usuarios u ON u.id=e.actor_id
                WHERE e.network_id={$networkId} AND e.store_id IN ({$list}) ORDER BY e.id DESC LIMIT 30")
                ->fetchAll(PDO::FETCH_ASSOC);
        }
        return ['healthy' => $cases === [], 'caseCount' => count($cases), 'cases' => array_slice($cases, 0, 100),
            'truncated' => count($cases) > 100, 'storeIds' => $stores, 'repairReady' => $repairReady, 'repairEvents' => $events];
    }

    /** Repair only a missing aggregate row whose grant ledger and last movement agree. */
    public function repairMissingWallet(int $networkId, int $userId, int $storeId, int $expectedCents, string $evidence): array
    {
        $this->admin();
        if ($userId <= 0 || $storeId <= 0 || $expectedCents < 0 || mb_strlen(trim($evidence)) < 15 || mb_strlen(trim($evidence)) > 1000) {
            throw new AdminApiException('Informe carteira, valor esperado e evidência de 15 a 1000 caracteres.', 422);
        }
        if (!$this->hasRepairAuditTable()) { throw new AdminApiException('Instale a migração de auditoria antes de reparar carteiras.', 503); }
        $this->db->beginTransaction();
        try {
            $network = $this->db->prepare('SELECT id FROM store_networks WHERE id=? FOR UPDATE');
            $network->execute([$networkId]);
            if (!$network->fetchColumn()) { throw new AdminApiException('Rede não encontrada.', 404); }
            $store = $this->db->prepare("SELECT id FROM lojas WHERE id=? AND status='aprovado' FOR UPDATE");
            $store->execute([$storeId]);
            if (!$store->fetchColumn()) { throw new AdminApiException('Filial indisponível.', 404); }
            $membership = $this->db->prepare('SELECT network_id FROM store_network_memberships WHERE store_id=? FOR UPDATE');
            $membership->execute([$storeId]);
            $existingNetwork = $membership->fetchColumn();
            if ($existingNetwork !== false && (int) $existingNetwork !== $networkId) {
                throw new AdminApiException('Filial vinculada a outra rede.', 409);
            }
            $user = $this->db->prepare('SELECT id FROM usuarios WHERE id=? FOR UPDATE');
            $user->execute([$userId]);
            if (!$user->fetchColumn()) { throw new AdminApiException('Cliente inexistente; revisão manual obrigatória.', 409); }
            $wallet = $this->db->prepare('SELECT id FROM cashback_saldos WHERE usuario_id=? AND loja_id=? FOR UPDATE');
            $wallet->execute([$userId, $storeId]);
            if ($wallet->fetchColumn()) { throw new AdminApiException('Carteira agregada já existe; atualize o diagnóstico.', 409); }
            $marker = $this->db->prepare('SELECT 1 FROM cashback_credito_carteiras WHERE usuario_id=? AND loja_id=? FOR UPDATE');
            $marker->execute([$userId, $storeId]);
            if (!$marker->fetchColumn()) { throw new AdminApiException('Marcador de migração ausente; revisão manual obrigatória.', 409); }
            $credits = $this->db->prepare('SELECT kind,original_cents,remaining_cents,consumed_cents,expired_cents,revoked_cents FROM cashback_creditos WHERE usuario_id=? AND loja_id=? FOR UPDATE');
            $credits->execute([$userId, $storeId]);
            $remaining = 0; $credited = 0; $used = 0; $count = 0;
            foreach ($credits->fetchAll(PDO::FETCH_ASSOC) as $credit) {
                $count++;
                $parts = array_map('intval', [$credit['original_cents'], $credit['remaining_cents'], $credit['consumed_cents'], $credit['expired_cents'], $credit['revoked_cents']]);
                if ($credit['kind'] !== 'grant' || min($parts) < 0 || $parts[0] !== array_sum(array_slice($parts, 1))) {
                    throw new AdminApiException('Crédito legado ou inconsistente; revisão manual obrigatória.', 409);
                }
                $remaining += $parts[1]; $credited += $parts[0] - $parts[4]; $used += $parts[2];
            }
            if (!$count || $remaining !== $expectedCents) { throw new AdminApiException('Créditos alterados. Atualize o diagnóstico.', 409); }
            $last = $this->db->prepare('SELECT saldo_atual FROM cashback_movimentacoes WHERE usuario_id=? AND loja_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE');
            $last->execute([$userId, $storeId]);
            $movementBalance = $last->fetchColumn();
            if ($movementBalance === false || (int) round((float) $movementBalance * 100) !== $remaining) {
                throw new AdminApiException('Última movimentação não confirma o saldo; revisão manual obrigatória.', 409);
            }
            $decimal = static fn (int $cents): string => number_format($cents / 100, 2, '.', '');
            $this->db->prepare('INSERT INTO cashback_saldos(usuario_id,loja_id,saldo_disponivel,total_creditado,total_usado,ultima_atualizacao) VALUES(?,?,?,?,?,NOW())')
                ->execute([$userId, $storeId, $decimal($remaining), $decimal($credited), $decimal($used)]);
            $this->db->prepare('INSERT INTO store_wallet_reconciliation_events(network_id,user_id,store_id,actor_id,action,reason,before_json,after_json) VALUES(?,?,?,?,?,?,?,?)')
                ->execute([$networkId, $userId, $storeId, $this->actorId, 'missing_wallet_repaired', trim($evidence),
                    json_encode(['wallet' => null, 'creditCents' => $remaining, 'lastMovementCents' => $remaining]),
                    json_encode(['walletCents' => $remaining, 'creditedCents' => $credited, 'usedCents' => $used])]);
            $this->db->commit();
            return ['userId' => $userId, 'storeId' => $storeId, 'walletCents' => $remaining];
        } catch (Throwable $error) { $this->db->rollBack(); throw $error; }
    }

    public function detail(int $networkId): array
    {
        $this->admin();
        $stmt = $this->db->prepare('SELECT * FROM store_networks WHERE id=?');
        $stmt->execute([$networkId]);
        $network = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$network) { throw new AdminApiException('Rede não encontrada.', 404); }
        $stmt = $this->db->prepare('SELECT m.store_id,m.status,l.nome_fantasia store_name,l.cnpj FROM store_network_memberships m JOIN lojas l ON l.id=m.store_id WHERE m.network_id=? ORDER BY l.nome_fantasia');
        $stmt->execute([$networkId]);
        $network['stores'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $this->db->prepare('SELECT gm.user_id,u.nome,u.email,u.status account_status FROM store_network_managers gm JOIN usuarios u ON u.id=gm.user_id WHERE gm.network_id=? ORDER BY u.nome');
        $stmt->execute([$networkId]);
        $network['managers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $this->db->prepare('SELECT e.* FROM store_network_events e WHERE e.network_id=? ORDER BY e.id DESC LIMIT 100');
        $stmt->execute([$networkId]);
        $network['events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $network;
    }

    public function create(string $name, string $reason): array
    {
        $this->admin();
        $name = trim($name); $reason = trim($reason);
        if (mb_strlen($name) < 3 || mb_strlen($name) > 160 || mb_strlen($reason) < 5) { throw new AdminApiException('Informe nome e motivo válidos.', 422); }
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('INSERT INTO store_networks(name,created_by) VALUES(?,?)');
            $stmt->execute([$name, $this->actorId]);
            $id = (int) $this->db->lastInsertId();
            $this->event($id, null, 'created', $reason, null, ['name' => $name]);
            $this->db->commit();
            return $this->detail($id);
        } catch (Throwable $error) { $this->db->rollBack(); throw $error; }
    }

    public function branch(int $networkId, int $storeId, string $action, string $reason, bool $reconciled = false): array
    {
        $this->admin();
        if (!in_array($action, ['join', 'suspend', 'resume', 'detach'], true) || mb_strlen(trim($reason)) < 5) {
            throw new AdminApiException('Ação ou motivo inválido.', 422);
        }
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT * FROM store_networks WHERE id=? FOR UPDATE');
            $stmt->execute([$networkId]);
            if (!$stmt->fetch(PDO::FETCH_ASSOC)) { throw new AdminApiException('Rede não encontrada.', 404); }
            $stmt = $this->db->prepare("SELECT id,status FROM lojas WHERE id=? FOR UPDATE");
            $stmt->execute([$storeId]);
            $store = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$store || $store['status'] !== 'aprovado') { throw new AdminApiException('Apenas filial aprovada pode entrar na rede.', 422); }
            if (in_array($action, ['join', 'resume'], true)) {
                $health = $this->walletHealth($networkId, $storeId);
                if (!$health['healthy']) {
                    throw new AdminApiException('Conciliação das carteiras destas filiais pendente (' . $health['caseCount'] . ' caso(s)).', 409);
                }
            }
            $stmt = $this->db->prepare('SELECT * FROM store_network_memberships WHERE store_id=? FOR UPDATE');
            $stmt->execute([$storeId]);
            $before = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($action === 'join') {
                if ($before) { throw new AdminApiException('Filial já pertence a uma rede; suspenda e concilie antes de alterar.', 409); }
                $stmt = $this->db->prepare("INSERT INTO store_network_memberships(store_id,network_id,status) VALUES(?,?,'active')");
                $stmt->execute([$storeId, $networkId]);
                $after = ['store_id' => $storeId, 'network_id' => $networkId, 'status' => 'active'];
            } else {
                if (!$before || (int) $before['network_id'] !== $networkId) { throw new AdminApiException('Filial não pertence a esta rede.', 404); }
                if ($action === 'detach') {
                    if ($before['status'] !== 'suspended' || !$reconciled) { throw new AdminApiException('Suspenda e confirme a conciliação antes de separar.', 409); }
                    $reconciliation = $this->reconciliation($networkId, $storeId);
                    $stmt = $this->db->prepare('DELETE FROM store_network_memberships WHERE store_id=?');
                    $stmt->execute([$storeId]);
                    $after = ['status' => 'detached', 'approvedBy' => $this->actorId,
                        'reconciliationAtApproval' => $reconciliation];
                } else {
                    $status = $action === 'suspend' ? 'suspended' : 'active';
                    $stmt = $this->db->prepare('UPDATE store_network_memberships SET status=? WHERE store_id=?');
                    $stmt->execute([$status, $storeId]);
                    $after = [...$before, 'status' => $status];
                }
            }
            $this->db->prepare('UPDATE store_networks SET version=version+1 WHERE id=?')->execute([$networkId]);
            $this->event($networkId, $storeId, $action, trim($reason), $before, $after);
            $this->db->commit();
            return $this->detail($networkId);
        } catch (Throwable $error) { $this->db->rollBack(); throw $error; }
    }

    public function manager(int $networkId, int $userId, bool $grant, string $reason): array
    {
        $this->admin();
        if (mb_strlen(trim($reason)) < 5) { throw new AdminApiException('Informe o motivo.', 422); }
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT id FROM store_networks WHERE id=? FOR UPDATE');
            $stmt->execute([$networkId]);
            if (!$stmt->fetchColumn()) { throw new AdminApiException('Rede não encontrada.', 404); }
            $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE id=? AND status='ativo' AND tipo IN ('loja','funcionario')");
            $stmt->execute([$userId]);
            if (!$stmt->fetchColumn()) { throw new AdminApiException('Conta gestora inválida.', 422); }
            if ($grant) {
                $this->db->prepare('INSERT IGNORE INTO store_network_managers(network_id,user_id,granted_by) VALUES(?,?,?)')->execute([$networkId, $userId, $this->actorId]);
            } else {
                $this->db->prepare('DELETE FROM store_network_managers WHERE network_id=? AND user_id=?')->execute([$networkId, $userId]);
            }
            $this->event($networkId, null, $grant ? 'manager_granted' : 'manager_revoked', trim($reason), null, ['userId' => $userId]);
            $this->db->commit();
            return $this->detail($networkId);
        } catch (Throwable $error) { $this->db->rollBack(); throw $error; }
    }

    public function reconciliation(int $networkId, int $storeId): array
    {
        $this->admin();
        $stmt = $this->db->prepare("SELECT m.loja_id origin_store_id,m.redemption_store_id,COUNT(DISTINCT m.transacao_uso_id) sales,
            COALESCE(SUM(a.amount_cents),0) used_cents,COALESCE(SUM(a.refunded_cents),0) refunded_cents,
            COALESCE(SUM(a.amount_cents-a.refunded_cents),0) net_used_cents
            FROM cashback_movimentacoes m JOIN cashback_credito_alocacoes a ON a.movement_id=m.id
            WHERE m.network_id_snapshot=? AND m.tipo_operacao='uso' AND m.redemption_store_id IS NOT NULL
            AND m.loja_id<>m.redemption_store_id AND (m.loja_id=? OR m.redemption_store_id=?)
            GROUP BY m.loja_id,m.redemption_store_id ORDER BY m.loja_id,m.redemption_store_id");
        $stmt->execute([$networkId, $storeId, $storeId]);
        return ['storeId' => $storeId, 'networkId' => $networkId, 'crossBranchUsage' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    public function correctSeller(int $transactionId, int $sellerId, ?int $expectedSellerId, string $reason): array
    {
        $this->admin();
        if (mb_strlen(trim($reason)) < 10) { throw new AdminApiException('Descreva a evidência da correção (mínimo de 10 caracteres).', 422); }
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT id,loja_id,vendedor_id FROM transacoes_cashback WHERE id=? FOR UPDATE');
            $stmt->execute([$transactionId]);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$sale) { throw new AdminApiException('Venda não encontrada.', 404); }
            $current = $sale['vendedor_id'] === null ? null : (int) $sale['vendedor_id'];
            if ($current !== $expectedSellerId) { throw new AdminApiException('A atribuição mudou. Atualize a venda.', 409); }
            $stmt = $this->db->prepare("SELECT u.nome FROM usuarios u
                LEFT JOIN store_user_memberships m ON m.user_id=u.id AND m.store_id=?
                LEFT JOIN store_network_managers gm ON gm.user_id=u.id AND gm.network_id=(
                    SELECT network_id_snapshot FROM transacoes_cashback WHERE id=?)
                WHERE u.id=? AND u.tipo IN ('loja','funcionario')
                  AND (m.user_id IS NOT NULL OR gm.user_id IS NOT NULL) LIMIT 1");
            $stmt->execute([(int) $sale['loja_id'], $transactionId, $sellerId]);
            $name = $stmt->fetchColumn();
            if (!$name) { throw new AdminApiException('Vendedor sem vínculo comprovado com a filial.', 422); }
            $this->db->prepare('UPDATE transacoes_cashback SET vendedor_id=?,vendedor_nome_snapshot=? WHERE id=?')
                ->execute([$sellerId, $name, $transactionId]);
            $this->db->prepare('INSERT INTO store_sale_attribution_events(transaction_id,previous_seller_id,new_seller_id,actor_id,reason) VALUES(?,?,?,?,?)')
                ->execute([$transactionId, $current, $sellerId, $this->actorId, trim($reason)]);
            $this->db->commit();
            return ['transactionId' => $transactionId, 'sellerId' => $sellerId, 'sellerName' => $name];
        } catch (Throwable $error) { $this->db->rollBack(); throw $error; }
    }
}
