<?php

declare(strict_types=1);

namespace App\Services\Store;

use PDO;

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
        $subtype = $type === 'funcionario'
            ? (string) ($session['employee_subtype'] ?? $session['subtipo_funcionario'] ?? 'funcionario')
            : null;

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
                'name' => (string) ($session['user_name'] ?? 'Usuário'),
                'type' => $type,
                'subtype' => $subtype,
                'avatarInitial' => $this->initial((string) ($session['user_name'] ?? 'U')),
            ],
            'permissions' => [
                'manageEmployees' => \AuthController::canManageEmployees(),
                'deactivateEmployees' => \AuthController::isStore(),
            ],
            'subscription' => [
                'active' => \FeatureGate::isActive($storeId),
                'status' => $plan['status'] ?? null,
                'planName' => $plan['plano_nome'] ?? null,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function dashboard(int $storeId): array
    {
        // Um único round-trip ao banco remoto substitui as três consultas
        // sequenciais que faziam o dashboard levar vários segundos.
        $statement = $this->db->prepare(
            "SELECT "
            . "(SELECT JSON_OBJECT('salesCount',COUNT(*),'grossTotal',COALESCE(SUM(valor_total),0),"
            . "'cashbackTotal',COALESCE(SUM(valor_cliente),0),'customersCount',COUNT(DISTINCT usuario_id),"
            . "'lastTransactionAt',MAX(data_transacao)) FROM transacoes_cashback WHERE loja_id=:summary_store AND status='aprovado') summary_json,"
            . "(SELECT COALESCE(JSON_ARRAYAGG(JSON_OBJECT('id',r.id,'code',r.codigo_transacao,"
            . "'grossTotal',r.valor_total,'cashbackTotal',r.valor_cliente,'status',r.status,"
            . "'occurredAt',r.data_transacao,'customerName',r.customer_name,'balanceUsed',r.balance_used)),JSON_ARRAY()) "
            . "FROM (SELECT t.id,t.codigo_transacao,t.valor_total,t.valor_cliente,t.status,t.data_transacao,"
            . "u.nome customer_name,COALESCE(su.balance_used,0) balance_used FROM transacoes_cashback t "
            . "JOIN usuarios u ON u.id=t.usuario_id LEFT JOIN (SELECT transacao_id,SUM(valor_usado) balance_used "
            . "FROM transacoes_saldo_usado GROUP BY transacao_id) su ON su.transacao_id=t.id "
            . "WHERE t.loja_id=:recent_store ORDER BY t.data_transacao DESC,t.id DESC LIMIT 6) r) recent_json,"
            . "(SELECT COALESCE(JSON_ARRAYAGG(JSON_OBJECT('month',m.month,'sales',m.sales,'grossTotal',m.gross_total)),JSON_ARRAY()) "
            . "FROM (SELECT DATE_FORMAT(data_transacao,'%Y-%m') month,COUNT(*) sales,COALESCE(SUM(valor_total),0) gross_total "
            . "FROM transacoes_cashback WHERE loja_id=:monthly_store AND status='aprovado' "
            . "AND data_transacao>=DATE_FORMAT(DATE_SUB(CURRENT_DATE(),INTERVAL 5 MONTH),'%Y-%m-01') "
            . "GROUP BY DATE_FORMAT(data_transacao,'%Y-%m') ORDER BY month) m) monthly_json"
        );
        $statement->execute([
            ':summary_store' => $storeId,
            ':recent_store' => $storeId,
            ':monthly_store' => $storeId,
        ]);
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
    public function transactions(int $storeId, array $filters, int $page, int $pageSize = 10): array
    {
        $conditions = ['t.loja_id=:store_id'];
        $params = [':store_id' => $storeId];
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
            . 'ON su.transacao_id=t.id WHERE ' . $where
        );
        $summary->execute($params);
        $summaryData = $summary->fetch(PDO::FETCH_ASSOC) ?: [];

        $query = $this->db->prepare(
            'SELECT t.id,t.codigo_transacao,t.descricao,t.valor_total,t.valor_cliente,t.status,t.data_transacao,'
            . "COALESCE(t.financial_model,'commission_legacy') financial_model,u.nome customer_name,u.email customer_email,"
            . 'COALESCE(su.balance_used,0) balance_used FROM transacoes_cashback t '
            . 'JOIN usuarios u ON u.id=t.usuario_id '
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
    public function transaction(int $storeId, int $transactionId): array
    {
        $statement = $this->db->prepare(
            'SELECT t.id,t.codigo_transacao,t.descricao,t.valor_total,t.valor_cliente,t.status,t.data_transacao,'
            . "COALESCE(t.financial_model,'commission_legacy') financial_model,u.nome customer_name,u.email customer_email,"
            . 'COALESCE(su.balance_used,0) balance_used FROM transacoes_cashback t '
            . 'JOIN usuarios u ON u.id=t.usuario_id '
            . 'LEFT JOIN (SELECT transacao_id,SUM(valor_usado) balance_used FROM transacoes_saldo_usado GROUP BY transacao_id) su '
            . 'ON su.transacao_id=t.id WHERE t.id=:id AND t.loja_id=:store_id LIMIT 1'
        );
        $statement->execute([':id' => $transactionId, ':store_id' => $storeId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new StoreApiException('Venda não encontrada.', 404);
        }
        return $this->transactionRow($row);
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
        $subscriptionStatement = $this->db->prepare(
            "SELECT a.*,p.nome plano_nome,p.slug plano_slug,p.preco_mensal,p.features_json "
            . "FROM assinaturas a JOIN planos p ON p.id=a.plano_id WHERE a.loja_id=:store_id "
            . "AND a.tipo='loja' AND a.status IN ('trial','ativa') ORDER BY a.created_at DESC LIMIT 1"
        );
        $subscriptionStatement->execute([':store_id' => $storeId]);
        $subscription = $subscriptionStatement->fetch(PDO::FETCH_ASSOC);
        $plans = $this->db->query(
            'SELECT nome,slug,preco_mensal,preco_anual,trial_dias,features_json FROM planos WHERE ativo=1 ORDER BY preco_mensal'
        )->fetchAll(PDO::FETCH_ASSOC);
        return [
            'dataState' => $subscription ? 'ready' : 'empty',
            'generatedAt' => date(DATE_ATOM),
            'subscription' => $subscription ? [
                'status' => (string) $subscription['status'],
                'cycle' => (string) $subscription['ciclo'],
                'planName' => (string) $subscription['plano_nome'],
                'planSlug' => (string) $subscription['plano_slug'],
                'currentPeriodEnd' => $subscription['current_period_end'] ?? null,
                'trialEnd' => $subscription['trial_end'] ?? null,
                'monthlyPriceCents' => StoreMoney::toCents($subscription['preco_mensal'] ?? 0),
                'features' => $this->features($subscription['features_json'] ?? null),
            ] : null,
            'plans' => array_map(fn (array $plan): array => [
                'name' => (string) $plan['nome'],
                'slug' => (string) $plan['slug'],
                'monthlyPriceCents' => StoreMoney::toCents($plan['preco_mensal'] ?? 0),
                'annualPriceCents' => StoreMoney::toCents($plan['preco_anual'] ?? 0),
                'trialDays' => (int) $plan['trial_dias'],
                'features' => $this->features($plan['features_json'] ?? null),
            ], $plans),
        ];
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
