<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Services\Billing\SubscriptionService;
use PDO;

require_once __DIR__ . '/StoreNetworkAccess.php';
require_once __DIR__ . '/../Giftback/GiftbackLedger.php';

final class StoreReadService
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array<string, mixed> */
    public function context(int $storeId, array $session): array
    {
        $statement = $this->db->prepare(
            "SELECT l.id,l.nome_fantasia,l.status,l.logo,l.porcentagem_cashback,"
            . "COALESCE(l.porcentagem_cliente,5.00) customer_percentage,l.cashback_ativo,"
            . "COALESCE(u.mvp,'nao') mvp FROM lojas l JOIN usuarios u ON u.id=l.usuario_id "
            . 'WHERE l.id=:store_id LIMIT 1'
        );
        $statement->execute([':store_id' => $storeId]);
        $store = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$store) {
            throw new StoreApiException('Loja não encontrada.', 404);
        }

        $plan = \FeatureGate::getPlanInfo($storeId);
        $type = (string) ($session['user_type'] ?? '');
        $membership = (new StoreNetworkAccess($this->db))->membership((int) ($session['user_id'] ?? 0), $storeId);
        $subtype = $membership['role'] ?? null;

        $billingContext = (new SubscriptionService($this->db))->context($storeId);
        $billingSubscription = $billingContext['subscription'] ?? null;
        $billingAccess = $billingContext['salesAccess'] ?? ['canRegisterSales' => false, 'salesBlocked' => true, 'reason' => 'no_subscription', 'status' => null];
        return [
            'dataState' => 'ready',
            'generatedAt' => date(DATE_ATOM),
            'store' => [
                'id' => (int) $store['id'],
                'name' => (string) $store['nome_fantasia'],
                'status' => (string) $store['status'],
                'logoUrl' => !empty($store['logo']) ? '/uploads/store_logos/' . basename((string) $store['logo']) : null,
                'customerCashbackPercentage' => (float) $store['customer_percentage'],
                'cashbackEnabled' => (bool) $store['cashback_ativo'],
                'mvp' => $store['mvp'] === 'sim',
                'financialModel' => 'subscription_cashback',
            ],
            'user' => [
                'id' => (int) ($session['user_id'] ?? 0),
                'name' => (string) ($session['user_name'] ?? 'Usuário'),
                'type' => $type,
                'subtype' => $subtype,
                'avatarInitial' => $this->initial((string) ($session['user_name'] ?? 'U')),
            ],
            'permissions' => [
                'manageEmployees' => \AuthController::canManageEmployees(),
                'deactivateEmployees' => in_array((string) $subtype, ['titular','gestor_rede'], true),
                'assignSeller' => \AuthController::canManageEmployees(),
            ],
            'subscription' => [
                'active' => (bool) ($billingAccess['canRegisterSales'] ?? false),
                'salesBlocked' => (bool) ($billingAccess['salesBlocked'] ?? true),
                'reason' => $billingAccess['reason'] ?? null,
                'status' => $billingSubscription['status'] ?? ($plan['status'] ?? null),
                'planName' => $billingSubscription['planName'] ?? ($plan['plano_nome'] ?? null),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function dashboard(int $storeId, ?array $scopeStores = null, ?int $sellerId = null, array $filters = []): array
    {
        $ids = array_values(array_unique(array_map('intval', $scopeStores ?? [$storeId])));
        $storeList = implode(',', $ids ?: [$storeId]);
        $sellerClause = $sellerId === null ? '' : ' AND vendedor_id=' . (int) $sellerId;
        $recentSellerClause = $sellerId === null ? '' : ' AND t.vendedor_id=' . (int) $sellerId;
        $dateClause = '';
        $recentDateClause = '';
        foreach (['startDate' => '>=', 'endDate' => '<='] as $key => $operator) {
            $date = (string) ($filters[$key] ?? '');
            if ($date === '') { continue; }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
                throw new StoreApiException('Período inválido.', 422);
            }
            $bound = $date . ($key === 'startDate' ? ' 00:00:00' : ' 23:59:59');
            $dateClause .= " AND data_transacao{$operator}'{$bound}'";
            $recentDateClause .= " AND t.data_transacao{$operator}'{$bound}'";
        }
        $recentStatusClause = '';
        if (($filters['status'] ?? '') !== '') {
            if (!in_array($filters['status'], ['aprovado', 'cancelado', 'pendente'], true)) { throw new StoreApiException('Status inválido.', 422); }
            $recentStatusClause = " AND t.status='" . $filters['status'] . "'";
        }
        // Um único round-trip ao banco remoto substitui as três consultas
        // sequenciais que faziam o dashboard levar vários segundos.
        $statement = $this->db->prepare(
            "SELECT "
            . "(SELECT JSON_OBJECT('salesCount',COUNT(*),'grossTotal',COALESCE(SUM(valor_total),0),"
            . "'cashbackTotal',COALESCE(SUM(valor_cliente),0),'customersCount',COUNT(DISTINCT usuario_id),"
            . "'lastTransactionAt',MAX(data_transacao)) FROM transacoes_cashback WHERE loja_id IN ({$storeList}) AND status='aprovado'{$sellerClause}{$dateClause}) summary_json,"
            . "(SELECT COALESCE(JSON_ARRAYAGG(JSON_OBJECT('id',r.id,'code',r.codigo_transacao,"
            . "'grossTotal',r.valor_total,'cashbackTotal',r.valor_cliente,'status',r.status,"
            . "'occurredAt',r.data_transacao,'customerName',r.customer_name,'balanceUsed',r.balance_used,"
            . "'sellerName',r.seller_name,'recordedByName',r.recorded_by_name,'storeName',r.store_name)),JSON_ARRAY()) "
            . "FROM (SELECT t.id,t.codigo_transacao,t.valor_total,t.valor_cliente,t.status,t.data_transacao,"
            . "u.nome customer_name,l.nome_fantasia store_name,t.vendedor_nome_snapshot seller_name,"
            . "t.registrado_por_nome_snapshot recorded_by_name,COALESCE(su.balance_used,0) balance_used FROM transacoes_cashback t "
            . "JOIN usuarios u ON u.id=t.usuario_id JOIN lojas l ON l.id=t.loja_id LEFT JOIN (SELECT transacao_id,SUM(valor_usado) balance_used "
            . "FROM transacoes_saldo_usado GROUP BY transacao_id) su ON su.transacao_id=t.id "
            . "WHERE t.loja_id IN ({$storeList}){$recentSellerClause}{$recentDateClause}{$recentStatusClause} ORDER BY t.data_transacao DESC,t.id DESC LIMIT 6) r) recent_json,"
            . "(SELECT COALESCE(JSON_ARRAYAGG(JSON_OBJECT('month',m.month,'sales',m.sales,'grossTotal',m.gross_total)),JSON_ARRAY()) "
            . "FROM (SELECT DATE_FORMAT(data_transacao,'%Y-%m') month,COUNT(*) sales,COALESCE(SUM(valor_total),0) gross_total "
            . "FROM transacoes_cashback WHERE loja_id IN ({$storeList}) AND status='aprovado'{$sellerClause}{$dateClause} "
            . "AND data_transacao>=DATE_FORMAT(DATE_SUB(CURRENT_DATE(),INTERVAL 5 MONTH),'%Y-%m-01') "
            . "GROUP BY DATE_FORMAT(data_transacao,'%Y-%m') ORDER BY month) m) monthly_json"
        );
        $statement->execute();
        $queryData = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
        $summaryData = json_decode((string) ($queryData['summary_json'] ?? '{}'), true) ?: [];
        $recentRows = json_decode((string) ($queryData['recent_json'] ?? '[]'), true) ?: [];
        $monthlyRows = json_decode((string) ($queryData['monthly_json'] ?? '[]'), true) ?: [];
        $monthsByKey = [];
        foreach ($monthlyRows as $row) {
            $monthsByKey[$row['month']] = $row;
        }
        $monthlyData = [];
        for ($offset = 5; $offset >= 0; $offset--) {
            $key = date('Y-m', strtotime("-{$offset} months"));
            $row = $monthsByKey[$key] ?? null;
            $monthlyData[] = [
                'month' => $key,
                'salesCount' => (int) ($row['sales'] ?? 0),
                'grossAmountCents' => StoreMoney::toCents($row['grossTotal'] ?? 0),
            ];
        }

        $recentData = array_map(fn (array $row): array => [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'customerName' => (string) $row['customerName'],
            'grossAmountCents' => StoreMoney::toCents($row['grossTotal']),
            'balanceUsedCents' => StoreMoney::toCents($row['balanceUsed']),
            'paidAmountCents' => max(0, StoreMoney::toCents($row['grossTotal']) - StoreMoney::toCents($row['balanceUsed'])),
            'cashbackGrantedCents' => StoreMoney::toCents($row['cashbackTotal']),
            'status' => (string) $row['status'],
            'sellerName' => $row['sellerName'] ?: 'Vendedor não identificado',
            'recordedByName' => $row['recordedByName'] ?: 'Responsável não identificado',
            'storeName' => $row['storeName'],
            'occurredAt' => $this->iso($row['occurredAt']),
        ], $recentRows);

        usort($recentData, static fn (array $left, array $right): int => strcmp((string) $right['occurredAt'], (string) $left['occurredAt']));
        $salesCount = (int) ($summaryData['salesCount'] ?? 0);
        return [
            'dataState' => $salesCount > 0 ? 'ready' : 'empty',
            'generatedAt' => date(DATE_ATOM),
            'summary' => [
                'salesCount' => $salesCount,
                'grossAmountCents' => StoreMoney::toCents($summaryData['grossTotal'] ?? 0),
                'cashbackGrantedCents' => StoreMoney::toCents($summaryData['cashbackTotal'] ?? 0),
                'customersCount' => (int) ($summaryData['customersCount'] ?? 0),
                'lastTransactionAt' => $this->iso($summaryData['lastTransactionAt'] ?? null),
            ],
            'recentTransactions' => $recentData,
            'monthlySales' => $monthlyData,
        ];
    }

    /** @param array<string, string> $filters
     *  @return array<string, mixed>
     */
    public function transactions(int $storeId, array $filters, int $page, int $pageSize = 10, ?array $scopeStores = null, ?int $forcedSellerId = null): array
    {
        $ids = array_values(array_unique(array_map('intval', $scopeStores ?? [$storeId])));
        $conditions = ['t.loja_id IN (' . implode(',', $ids ?: [$storeId]) . ')'];
        $params = [];
        if ($forcedSellerId !== null) { $conditions[] = 't.vendedor_id=:seller_id'; $params[':seller_id'] = $forcedSellerId; }
        elseif (($filters['sellerId'] ?? '') !== '') { $conditions[] = 't.vendedor_id=:seller_id'; $params[':seller_id'] = (int) $filters['sellerId']; }
        if (($filters['status'] ?? '') !== '') {
            $conditions[] = 't.status=:status';
            $params[':status'] = $filters['status'];
        }
        if (($filters['startDate'] ?? '') !== '') {
            $conditions[] = 't.data_transacao>=:start_date';
            $params[':start_date'] = $filters['startDate'] . ' 00:00:00';
        }
        if (($filters['endDate'] ?? '') !== '') {
            $conditions[] = 't.data_transacao<=:end_date';
            $params[':end_date'] = $filters['endDate'] . ' 23:59:59';
        }
        if (($filters['customer'] ?? '') !== '') {
            $conditions[] = '(u.nome LIKE :customer OR u.email LIKE :customer)';
            $params[':customer'] = '%' . $filters['customer'] . '%';
        }
        if (($filters['minimumCents'] ?? '') !== '') {
            $conditions[] = 't.valor_total>=:minimum';
            $params[':minimum'] = StoreMoney::decimal(max(0, (int) $filters['minimumCents']));
        }
        if (($filters['maximumCents'] ?? '') !== '') {
            $conditions[] = 't.valor_total<=:maximum';
            $params[':maximum'] = StoreMoney::decimal(max(0, (int) $filters['maximumCents']));
        }
        $where = implode(' AND ', $conditions);

        $count = $this->db->prepare('SELECT COUNT(*) FROM transacoes_cashback t JOIN usuarios u ON u.id=t.usuario_id WHERE ' . $where);
        $count->execute($params);
        $totalItems = (int) $count->fetchColumn();
        $totalPages = max(1, (int) ceil($totalItems / $pageSize));
        $page = max(1, min($page, $totalPages));

        $summary = $this->db->prepare(
            'SELECT COUNT(*) sales_count,COALESCE(SUM(t.valor_total),0) gross_total,'
            . 'COALESCE(SUM(t.valor_cliente),0) cashback_total,COALESCE(SUM(su.balance_used),0) balance_used_total '
            . 'FROM transacoes_cashback t JOIN usuarios u ON u.id=t.usuario_id '
            . 'LEFT JOIN (SELECT transacao_id,SUM(valor_usado) balance_used FROM transacoes_saldo_usado GROUP BY transacao_id) su '
            . "ON su.transacao_id=t.id WHERE " . $where . " AND t.status='aprovado'"
        );
        $summary->execute($params);
        $summaryData = $summary->fetch(PDO::FETCH_ASSOC) ?: [];

        $query = $this->db->prepare(
            'SELECT t.id,t.codigo_transacao,t.descricao,t.valor_total,t.valor_cliente,t.status,t.data_transacao,'
            . "COALESCE(t.financial_model,'commission_legacy') financial_model,u.nome customer_name,u.email customer_email,"
            . "t.loja_id,l.nome_fantasia store_name,t.vendedor_id,t.vendedor_nome_snapshot,t.criado_por,t.registrado_por_nome_snapshot,"
            . 'COALESCE(su.balance_used,0) balance_used FROM transacoes_cashback t '
            . 'JOIN usuarios u ON u.id=t.usuario_id JOIN lojas l ON l.id=t.loja_id '
            . 'LEFT JOIN (SELECT transacao_id,SUM(valor_usado) balance_used FROM transacoes_saldo_usado GROUP BY transacao_id) su '
            . 'ON su.transacao_id=t.id WHERE ' . $where . ' ORDER BY t.data_transacao DESC,t.id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $query->bindValue($key, $value);
        }
        $query->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $query->bindValue(':offset', ($page - 1) * $pageSize, PDO::PARAM_INT);
        $query->execute();
        $items = array_map(fn (array $row): array => $this->transactionRow($row), $query->fetchAll(PDO::FETCH_ASSOC));

        return [
            'dataState' => $totalItems > 0 ? 'ready' : 'empty',
            'generatedAt' => date(DATE_ATOM),
            'items' => $items,
            'summary' => [
                'salesCount' => (int) ($summaryData['sales_count'] ?? 0),
                'grossAmountCents' => StoreMoney::toCents($summaryData['gross_total'] ?? 0),
                'cashbackGrantedCents' => StoreMoney::toCents($summaryData['cashback_total'] ?? 0),
                'balanceUsedCents' => StoreMoney::toCents($summaryData['balance_used_total'] ?? 0),
            ],
            'pagination' => compact('page', 'pageSize', 'totalItems', 'totalPages'),
        ];
    }

    /** @return array<string, mixed> */
    public function transaction(int $storeId, int $transactionId, ?array $scopeStores = null, ?int $forcedSellerId = null): array
    {
        $ids = array_values(array_unique(array_map('intval', $scopeStores ?? [$storeId])));
        $storeList = implode(',', $ids ?: [$storeId]);
        $sellerCondition = $forcedSellerId === null ? '' : ' AND t.vendedor_id=' . (int) $forcedSellerId;
        $statement = $this->db->prepare(
            'SELECT t.id,t.codigo_transacao,t.descricao,t.valor_total,t.valor_cliente,t.status,t.data_transacao,'
            . "COALESCE(t.financial_model,'commission_legacy') financial_model,u.nome customer_name,u.email customer_email,"
            . "t.loja_id,l.nome_fantasia store_name,t.vendedor_id,t.vendedor_nome_snapshot,t.criado_por,t.registrado_por_nome_snapshot,"
            . 'COALESCE(su.balance_used,0) balance_used FROM transacoes_cashback t '
            . 'JOIN usuarios u ON u.id=t.usuario_id JOIN lojas l ON l.id=t.loja_id '
            . 'LEFT JOIN (SELECT transacao_id,SUM(valor_usado) balance_used FROM transacoes_saldo_usado GROUP BY transacao_id) su '
            . "ON su.transacao_id=t.id WHERE t.id=:id AND t.loja_id IN ({$storeList}){$sellerCondition} LIMIT 1"
        );
        $statement->execute([':id' => $transactionId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new StoreApiException('Venda não encontrada.', 404);
        }
        return $this->transactionRow($row);
    }

    public function sellerReport(array $scopeStores, ?int $forcedSellerId, ?string $startDate, ?string $endDate): array
    {
        $ids = array_values(array_unique(array_map('intval', $scopeStores)));
        if ($ids === []) { throw new StoreApiException('Filial inválida.', 422); }
        $where = ['t.loja_id IN (' . implode(',', $ids) . ')', "t.status='aprovado'"];
        $params = [];
        if ($forcedSellerId !== null) { $where[] = 't.vendedor_id=?'; $params[] = $forcedSellerId; }
        if ($startDate) { $where[] = 't.data_transacao>=?'; $params[] = $startDate . ' 00:00:00'; }
        if ($endDate) { $where[] = 't.data_transacao<=?'; $params[] = $endDate . ' 23:59:59'; }
        $stmt = $this->db->prepare('SELECT t.loja_id,l.nome_fantasia store_name,t.vendedor_id,
            COALESCE(MAX(t.vendedor_nome_snapshot),\'Vendedor não identificado\') seller_name,
            COUNT(*) sales_count,COALESCE(SUM(t.valor_total),0) gross_total,
            COALESCE(SUM(t.valor_cliente),0) giftback_issued,
            COALESCE(SUM(su.balance_used),0) balance_redeemed
            FROM transacoes_cashback t JOIN lojas l ON l.id=t.loja_id
            LEFT JOIN (SELECT transacao_id,SUM(valor_usado) balance_used FROM transacoes_saldo_usado GROUP BY transacao_id) su ON su.transacao_id=t.id
            WHERE ' . implode(' AND ', $where) . ' GROUP BY t.loja_id,l.nome_fantasia,t.vendedor_id ORDER BY gross_total DESC');
        $stmt->execute($params);
        return ['items' => array_map(static fn (array $row): array => [
            'storeId' => (int) $row['loja_id'], 'storeName' => $row['store_name'],
            'sellerId' => $row['vendedor_id'] === null ? null : (int) $row['vendedor_id'],
            'sellerName' => $row['seller_name'], 'salesCount' => (int) $row['sales_count'],
            'grossAmountCents' => StoreMoney::toCents($row['gross_total']),
            'giftbackIssuedCents' => StoreMoney::toCents($row['giftback_issued']),
            'balanceRedeemedCents' => StoreMoney::toCents($row['balance_redeemed']),
        ], $stmt->fetchAll(PDO::FETCH_ASSOC))];
    }

    public function giftbackReport(array $scopeStores): array
    {
        $ids = array_values(array_unique(array_map('intval', $scopeStores)));
        if ($ids === []) { throw new StoreApiException('Filial inválida.', 422); }
        $ledger = new \App\Services\Giftback\GiftbackLedger($this->db);
        foreach ($ids as $id) { $ledger->settleStore($id); }
        $list = implode(',', $ids);
        $issued = $this->db->query("SELECT c.loja_id,l.nome_fantasia store_name,
            COALESCE(SUM(CASE WHEN c.kind='grant' THEN c.original_cents ELSE 0 END),0) issued_cents,
            COALESCE(SUM(c.remaining_cents),0) remaining_cents,
            COALESCE(SUM(c.expired_cents),0) expired_cents FROM cashback_creditos c JOIN lojas l ON l.id=c.loja_id
            WHERE c.loja_id IN ({$list}) GROUP BY c.loja_id,l.nome_fantasia ORDER BY l.nome_fantasia")
            ->fetchAll(PDO::FETCH_ASSOC);
        $usage = $this->db->query("SELECT m.loja_id origin_store_id,origin.nome_fantasia origin_store_name,
            COALESCE(m.redemption_store_id,m.loja_id) redemption_store_id,redemption.nome_fantasia redemption_store_name,
            COALESCE(SUM(a.amount_cents-a.refunded_cents),0) used_cents
            FROM cashback_movimentacoes m JOIN cashback_credito_alocacoes a ON a.movement_id=m.id
            JOIN lojas origin ON origin.id=m.loja_id
            JOIN lojas redemption ON redemption.id=COALESCE(m.redemption_store_id,m.loja_id)
            WHERE m.tipo_operacao='uso' AND (m.loja_id IN ({$list}) OR m.redemption_store_id IN ({$list}))
            GROUP BY m.loja_id,origin.nome_fantasia,COALESCE(m.redemption_store_id,m.loja_id),redemption.nome_fantasia
            ORDER BY m.loja_id,redemption_store_id")->fetchAll(PDO::FETCH_ASSOC);
        return ['issuedByOrigin' => $issued, 'usageByOriginAndRedemption' => $usage];
    }

    /** @return array<string, mixed> */
    public function profile(int $storeId): array
    {
        $statement = $this->db->prepare(
            'SELECT l.nome_fantasia,l.razao_social,l.cnpj,l.telefone,l.website,l.descricao,'
            . 'l.porcentagem_cliente,l.status,l.data_cadastro,u.email,e.cep,e.logradouro,e.numero,'
            . 'e.complemento,e.bairro,e.cidade,e.estado FROM lojas l JOIN usuarios u ON u.id=l.usuario_id '
            . 'LEFT JOIN lojas_endereco e ON e.loja_id=l.id WHERE l.id=:store_id LIMIT 1'
        );
        $statement->execute([':store_id' => $storeId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new StoreApiException('Loja não encontrada.', 404);
        }
        return [
            'dataState' => 'ready',
            'generatedAt' => date(DATE_ATOM),
            'company' => [
                'tradeName' => (string) $row['nome_fantasia'],
                'legalName' => (string) $row['razao_social'],
                'cnpj' => (string) $row['cnpj'],
                'email' => (string) $row['email'],
                'phone' => (string) ($row['telefone'] ?? ''),
                'website' => (string) ($row['website'] ?? ''),
                'description' => (string) ($row['descricao'] ?? ''),
                'customerCashbackPercentage' => (float) $row['porcentagem_cliente'],
                'status' => (string) $row['status'],
                'createdAt' => $this->iso($row['data_cadastro']),
            ],
            'address' => [
                'postalCode' => (string) ($row['cep'] ?? ''),
                'street' => (string) ($row['logradouro'] ?? ''),
                'number' => (string) ($row['numero'] ?? ''),
                'complement' => (string) ($row['complemento'] ?? ''),
                'neighborhood' => (string) ($row['bairro'] ?? ''),
                'city' => (string) ($row['cidade'] ?? ''),
                'state' => (string) ($row['estado'] ?? ''),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function subscription(int $storeId): array
    {
        // Keep all subscription reads in one service so the UI cannot confuse
        // a legacy invoice with the current commercial access state.
        return (new SubscriptionService($this->db))->context($storeId);
    }

    /** @param array<string, mixed> $row
     *  @return array<string, mixed>
     */
    private function transactionRow(array $row): array
    {
        $gross = StoreMoney::toCents($row['valor_total']);
        $balance = StoreMoney::toCents($row['balance_used']);
        return [
            'id' => (int) $row['id'],
            'code' => (string) $row['codigo_transacao'],
            'description' => (string) ($row['descricao'] ?? ''),
            'customerName' => (string) $row['customer_name'],
            'customerEmail' => (string) ($row['customer_email'] ?? ''),
            'storeId' => (int) ($row['loja_id'] ?? 0),
            'storeName' => (string) ($row['store_name'] ?? ''),
            'sellerId' => isset($row['vendedor_id']) ? (int) $row['vendedor_id'] : null,
            'sellerName' => (string) ($row['vendedor_nome_snapshot'] ?: 'Vendedor não identificado'),
            'recordedById' => isset($row['criado_por']) ? (int) $row['criado_por'] : null,
            'recordedByName' => (string) ($row['registrado_por_nome_snapshot'] ?: 'Responsável não identificado'),
            'grossAmountCents' => $gross,
            'balanceUsedCents' => $balance,
            'paidAmountCents' => max(0, $gross - $balance),
            'cashbackGrantedCents' => StoreMoney::toCents($row['valor_cliente']),
            'status' => (string) $row['status'],
            'financialModel' => (string) $row['financial_model'],
            'occurredAt' => $this->iso($row['data_transacao']),
        ];
    }

    private function iso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $timestamp = strtotime((string) $value);
        return $timestamp === false ? null : date(DATE_ATOM, $timestamp);
    }

    private function initial(string $name): string
    {
        return function_exists('mb_strtoupper')
            ? mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8')
            : strtoupper(substr($name, 0, 1));
    }

    /** @return array<int|string, mixed> */
    private function features(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
