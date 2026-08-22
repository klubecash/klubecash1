<?php

declare(strict_types=1);

namespace App\Services\Admin;

use PDO;

final class AdminReadService
{
    public function __construct(private PDO $db)
    {
    }

    /** @param array<string, mixed> $session @return array<string, mixed> */
    public function context(array $session): array
    {
        $stmt = $this->db->prepare("SELECT id,nome,email FROM usuarios WHERE id=:id AND tipo='admin' AND status='ativo' LIMIT 1");
        $stmt->execute([':id' => (int) ($session['user_id'] ?? 0)]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$admin) {
            throw new AdminApiException('Administrador não encontrado ou inativo.', 403);
        }
        $name = trim((string) $admin['nome']);
        return $this->state([
            'user' => [
                'id' => (int) $admin['id'],
                'name' => $name,
                'email' => (string) $admin['email'],
                'avatarInitial' => $this->initial($name),
            ],
            'permissions' => [
                'manageUsers' => true,
                'manageStores' => true,
                'manageLegacyFinance' => true,
                'manageSubscriptions' => true,
                'manageMarketing' => true,
            ],
            'financialModel' => 'subscription_cashback',
        ]);
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $summary = $this->db->query(
            "SELECT
                (SELECT COUNT(*) FROM usuarios WHERE tipo='cliente') customers,
                (SELECT COUNT(*) FROM lojas WHERE status='aprovado') approved_stores,
                (SELECT COUNT(*) FROM lojas WHERE status='pendente') pending_stores,
                (SELECT COUNT(*) FROM transacoes_cashback WHERE status='aprovado') sales_count,
                (SELECT COALESCE(SUM(valor_total),0) FROM transacoes_cashback WHERE status='aprovado') gross_amount,
                (SELECT COALESCE(SUM(valor_cliente),0) FROM transacoes_cashback WHERE status='aprovado') cashback_amount,
                (SELECT COUNT(*) FROM transacoes_cashback WHERE status='aprovado' AND financial_model='subscription_cashback') current_sales_count,
                (SELECT COALESCE(SUM(valor_total),0) FROM transacoes_cashback WHERE status='aprovado' AND financial_model='subscription_cashback') current_gross_amount,
                (SELECT COALESCE(SUM(valor_cliente),0) FROM transacoes_cashback WHERE status='aprovado' AND financial_model='subscription_cashback') current_cashback_amount,
                (SELECT COUNT(*) FROM transacoes_cashback WHERE status='aprovado' AND COALESCE(financial_model,'commission_legacy')='commission_legacy') legacy_sales_count,
                (SELECT COALESCE(SUM(valor_total),0) FROM transacoes_cashback WHERE status='aprovado' AND COALESCE(financial_model,'commission_legacy')='commission_legacy') legacy_gross_amount,
                (SELECT COALESCE(SUM(valor_cliente),0) FROM transacoes_cashback WHERE status='aprovado' AND COALESCE(financial_model,'commission_legacy')='commission_legacy') legacy_cashback_amount,
                (SELECT COUNT(*) FROM assinaturas WHERE status IN ('ativa','trial')) active_subscriptions,
                (SELECT COUNT(*) FROM pagamentos_comissao WHERE status='pendente') pending_commission_payments,
                (SELECT COUNT(*) FROM store_balance_payments WHERE status IN ('pendente','em_processamento')) pending_balance_payments"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $recent = $this->db->query(
            "SELECT t.id,t.codigo_transacao,t.valor_total,t.valor_cliente,t.status,t.financial_model,t.data_transacao,
                    u.nome customer_name,l.nome_fantasia store_name
             FROM transacoes_cashback t
             JOIN usuarios u ON u.id=t.usuario_id
             JOIN lojas l ON l.id=t.loja_id
             ORDER BY t.data_transacao DESC,t.id DESC LIMIT 8"
        )->fetchAll(PDO::FETCH_ASSOC);

        $pendingStores = $this->db->query(
            "SELECT id,nome_fantasia,cnpj,categoria,data_cadastro FROM lojas WHERE status='pendente' ORDER BY data_cadastro ASC LIMIT 6"
        )->fetchAll(PDO::FETCH_ASSOC);

        $monthly = $this->db->query(
            "SELECT DATE_FORMAT(data_transacao,'%Y-%m') month,COUNT(*) sales_count,COALESCE(SUM(valor_total),0) gross_amount,
                    COALESCE(SUM(valor_cliente),0) cashback_amount
             FROM transacoes_cashback
             WHERE status='aprovado' AND financial_model='subscription_cashback' AND data_transacao>=DATE_SUB(CURDATE(),INTERVAL 11 MONTH)
             GROUP BY DATE_FORMAT(data_transacao,'%Y-%m') ORDER BY month"
        )->fetchAll(PDO::FETCH_ASSOC);

        return $this->state([
            'summary' => [
                'customers' => (int) ($summary['customers'] ?? 0),
                'approvedStores' => (int) ($summary['approved_stores'] ?? 0),
                'pendingStores' => (int) ($summary['pending_stores'] ?? 0),
                'salesCount' => (int) ($summary['sales_count'] ?? 0),
                'grossAmountCents' => AdminMoney::cents($summary['gross_amount'] ?? 0),
                'cashbackAmountCents' => AdminMoney::cents($summary['cashback_amount'] ?? 0),
                'currentSalesCount' => (int) ($summary['current_sales_count'] ?? 0),
                'currentGrossAmountCents' => AdminMoney::cents($summary['current_gross_amount'] ?? 0),
                'currentCashbackAmountCents' => AdminMoney::cents($summary['current_cashback_amount'] ?? 0),
                'legacySalesCount' => (int) ($summary['legacy_sales_count'] ?? 0),
                'legacyGrossAmountCents' => AdminMoney::cents($summary['legacy_gross_amount'] ?? 0),
                'legacyCashbackAmountCents' => AdminMoney::cents($summary['legacy_cashback_amount'] ?? 0),
                'activeSubscriptions' => (int) ($summary['active_subscriptions'] ?? 0),
                'pendingLegacyItems' => (int) ($summary['pending_commission_payments'] ?? 0) + (int) ($summary['pending_balance_payments'] ?? 0),
            ],
            'recentTransactions' => array_map(fn (array $row): array => $this->transactionRow($row), $recent),
            'pendingStores' => array_map(fn (array $row): array => [
                'id' => (int) $row['id'],
                'name' => (string) $row['nome_fantasia'],
                'cnpj' => (string) $row['cnpj'],
                'category' => (string) $row['categoria'],
                'registeredAt' => $this->iso($row['data_cadastro']),
            ], $pendingStores),
            'monthly' => array_map(fn (array $row): array => [
                'month' => (string) $row['month'],
                'salesCount' => (int) $row['sales_count'],
                'grossAmountCents' => AdminMoney::cents($row['gross_amount']),
                'cashbackAmountCents' => AdminMoney::cents($row['cashback_amount']),
            ], $monthly),
        ]);
    }

    /** @param array<string, string> $filters @return array<string, mixed> */
    public function users(array $filters, int $page, int $pageSize = 20): array
    {
        [$where, $params] = $this->where($filters, [
            'type' => ['u.tipo = :type', 'type'],
            'status' => ['u.status = :status', 'status'],
        ]);
        if (($filters['search'] ?? '') !== '') {
            $where[] = '(u.nome LIKE :search_name OR u.email LIKE :search_email OR u.telefone LIKE :search_phone)';
            $search = '%' . $filters['search'] . '%';
            $params[':search_name'] = $search;
            $params[':search_email'] = $search;
            $params[':search_phone'] = $search;
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $count = $this->db->prepare('SELECT COUNT(*) FROM usuarios u' . $whereSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $offset = ($page - 1) * $pageSize;
        $stmt = $this->db->prepare(
            "SELECT u.id,u.nome,u.email,u.telefone,u.status,u.tipo,u.tipo_cliente,u.loja_vinculada_id,u.subtipo_funcionario,
                    u.data_criacao,u.ultimo_login,u.updated_at,l.nome_fantasia linked_store
             FROM usuarios u LEFT JOIN lojas l ON l.id=u.loja_vinculada_id{$whereSql}
             ORDER BY u.data_criacao DESC,u.id DESC LIMIT {$pageSize} OFFSET {$offset}"
        );
        $stmt->execute($params);
        $items = array_map(fn (array $row): array => $this->userRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
        return $this->page($items, $page, $pageSize, $total);
    }

    /** @return array<string, mixed> */
    public function user(int $id): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id,u.nome,u.email,u.telefone,u.status,u.tipo,u.tipo_cliente,u.loja_vinculada_id,u.subtipo_funcionario,'
            . 'u.data_criacao,u.ultimo_login,u.updated_at,l.nome_fantasia linked_store FROM usuarios u LEFT JOIN lojas l ON l.id=u.loja_vinculada_id WHERE u.id=:id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new AdminApiException('Usuário não encontrado.', 404);
        }
        return $this->state(['item' => $this->userRow($row)]);
    }

    /** @param array<string, string> $filters @return array<string, mixed> */
    public function stores(array $filters, int $page, int $pageSize = 20): array
    {
        [$where, $params] = $this->where($filters, [
            'status' => ['l.status = :status', 'status'],
            'category' => ['l.categoria = :category', 'category'],
        ]);
        if (($filters['search'] ?? '') !== '') {
            $where[] = '(l.nome_fantasia LIKE :search_name OR l.razao_social LIKE :search_legal OR l.cnpj LIKE :search_cnpj OR l.email LIKE :search_email)';
            $search = '%' . $filters['search'] . '%';
            $params[':search_name'] = $search;
            $params[':search_legal'] = $search;
            $params[':search_cnpj'] = $search;
            $params[':search_email'] = $search;
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $count = $this->db->prepare('SELECT COUNT(*) FROM lojas l' . $whereSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $offset = ($page - 1) * $pageSize;
        $stmt = $this->db->prepare(
            "SELECT l.id,l.nome_fantasia,l.razao_social,l.cnpj,l.email,l.telefone,l.categoria,l.status,l.observacao,
                    l.porcentagem_cliente,l.cashback_ativo,l.data_cadastro,l.data_aprovacao,l.updated_at,u.nome owner_name,
                    (SELECT COUNT(*) FROM transacoes_cashback t WHERE t.loja_id=l.id) transactions_count,
                    (SELECT COALESCE(SUM(t.valor_total),0) FROM transacoes_cashback t WHERE t.loja_id=l.id AND t.status='aprovado') gross_amount
             FROM lojas l LEFT JOIN usuarios u ON u.id=l.usuario_id{$whereSql}
             ORDER BY l.data_cadastro DESC,l.id DESC LIMIT {$pageSize} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->page(array_map(fn (array $row): array => $this->storeRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC)), $page, $pageSize, $total);
    }

    /** @return array<string, mixed> */
    public function store(int $id): array
    {
        $stmt = $this->db->prepare(
            "SELECT l.*,u.nome owner_name,u.email owner_email,e.cep,e.logradouro,e.numero,e.complemento,e.bairro,e.cidade,e.estado,
                    (SELECT COUNT(*) FROM usuarios f WHERE f.loja_vinculada_id=l.id AND f.tipo='funcionario' AND f.status='ativo') employees_count,
                    (SELECT COUNT(*) FROM transacoes_cashback t WHERE t.loja_id=l.id) transactions_count,
                    (SELECT COALESCE(SUM(t.valor_total),0) FROM transacoes_cashback t WHERE t.loja_id=l.id AND t.status='aprovado') gross_amount
             FROM lojas l LEFT JOIN usuarios u ON u.id=l.usuario_id LEFT JOIN lojas_endereco e ON e.loja_id=l.id WHERE l.id=:id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new AdminApiException('Loja não encontrada.', 404);
        }
        $item = $this->storeRow($row) + [
            'description' => $row['descricao'],
            'website' => $row['website'],
            'logoUrl' => $row['logo'],
            'owner' => ['id' => (int) ($row['usuario_id'] ?? 0), 'name' => (string) ($row['owner_name'] ?? ''), 'email' => (string) ($row['owner_email'] ?? '')],
            'address' => [
                'postalCode' => (string) ($row['cep'] ?? ''), 'street' => (string) ($row['logradouro'] ?? ''),
                'number' => (string) ($row['numero'] ?? ''), 'complement' => (string) ($row['complemento'] ?? ''),
                'district' => (string) ($row['bairro'] ?? ''), 'city' => (string) ($row['cidade'] ?? ''), 'state' => (string) ($row['estado'] ?? ''),
            ],
            'employeesCount' => (int) $row['employees_count'],
        ];
        $employees = $this->db->prepare(
            "SELECT id,nome,email,status,subtipo_funcionario FROM usuarios "
            . "WHERE loja_vinculada_id=:store AND tipo='funcionario' ORDER BY status='ativo' DESC,nome"
        );
        $employees->execute([':store' => $id]);
        $item['employees'] = array_map(static fn (array $employee): array => [
            'id' => (int) $employee['id'],
            'name' => (string) $employee['nome'],
            'email' => (string) $employee['email'],
            'status' => (string) $employee['status'],
            'subtype' => (string) ($employee['subtipo_funcionario'] ?? 'funcionario'),
        ], $employees->fetchAll(PDO::FETCH_ASSOC));
        $subscription = $this->db->prepare(
            'SELECT a.id,a.status,a.ciclo,a.current_period_end,p.nome plan_name FROM assinaturas a '
            . 'JOIN planos p ON p.id=a.plano_id WHERE a.loja_id=:store ORDER BY a.updated_at DESC,a.id DESC LIMIT 1'
        );
        $subscription->execute([':store' => $id]);
        $subscriptionRow = $subscription->fetch(PDO::FETCH_ASSOC);
        $item['subscription'] = $subscriptionRow ? [
            'id' => (int) $subscriptionRow['id'],
            'planName' => (string) $subscriptionRow['plan_name'],
            'status' => (string) $subscriptionRow['status'],
            'cycle' => (string) $subscriptionRow['ciclo'],
            'periodEnd' => $this->iso($subscriptionRow['current_period_end']),
        ] : null;
        return $this->state(['item' => $item]);
    }

    /** @param array<string, string> $filters @return array<string, mixed> */
    public function transactions(array $filters, int $page, int $pageSize = 20): array
    {
        [$where, $params] = $this->where($filters, [
            'status' => ['t.status = :status', 'status'],
            'model' => ["COALESCE(t.financial_model,'commission_legacy') = :model", 'model'],
            'storeId' => ['t.loja_id = :storeId', 'storeId'],
        ]);
        if (($filters['search'] ?? '') !== '') {
            $where[] = '(t.codigo_transacao LIKE :search_code OR u.nome LIKE :search_name OR u.email LIKE :search_email OR l.nome_fantasia LIKE :search_store)';
            $search = '%' . $filters['search'] . '%';
            $params[':search_code'] = $search;
            $params[':search_name'] = $search;
            $params[':search_email'] = $search;
            $params[':search_store'] = $search;
        }
        if (($filters['startDate'] ?? '') !== '') {
            $where[] = 't.data_transacao >= :startDate';
            $params[':startDate'] = $filters['startDate'] . ' 00:00:00';
        }
        if (($filters['endDate'] ?? '') !== '') {
            $where[] = 't.data_transacao <= :endDate';
            $params[':endDate'] = $filters['endDate'] . ' 23:59:59';
        }
        if (($filters['balance'] ?? '') === 'used') {
            $where[] = "EXISTS (SELECT 1 FROM cashback_movimentacoes cm WHERE cm.transacao_uso_id=t.id AND cm.tipo_operacao='uso')";
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $count = $this->db->prepare('SELECT COUNT(*) FROM transacoes_cashback t JOIN usuarios u ON u.id=t.usuario_id JOIN lojas l ON l.id=t.loja_id' . $whereSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $offset = ($page - 1) * $pageSize;
        $stmt = $this->db->prepare(
            "SELECT t.id,t.codigo_transacao,t.valor_total,t.valor_cliente,t.valor_admin,t.valor_loja,t.valor_cashback,t.status,
                    t.financial_model,t.data_transacao,t.descricao,u.nome customer_name,u.email customer_email,l.nome_fantasia store_name,
                    COALESCE((SELECT SUM(cm.valor) FROM cashback_movimentacoes cm WHERE cm.transacao_uso_id=t.id AND cm.tipo_operacao='uso'),0) balance_used
             FROM transacoes_cashback t JOIN usuarios u ON u.id=t.usuario_id JOIN lojas l ON l.id=t.loja_id{$whereSql}
             ORDER BY t.data_transacao DESC,t.id DESC LIMIT {$pageSize} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->page(array_map(fn (array $row): array => $this->transactionRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC)), $page, $pageSize, $total);
    }

    /** @return array<string, mixed> */
    public function transaction(int $id): array
    {
        $stmt = $this->db->prepare(
            "SELECT t.*,u.nome customer_name,u.email customer_email,l.nome_fantasia store_name,
                    COALESCE((SELECT SUM(cm.valor) FROM cashback_movimentacoes cm WHERE cm.transacao_uso_id=t.id AND cm.tipo_operacao='uso'),0) balance_used
             FROM transacoes_cashback t JOIN usuarios u ON u.id=t.usuario_id JOIN lojas l ON l.id=t.loja_id WHERE t.id=:id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new AdminApiException('Transação não encontrada.', 404);
        }
        $movements = $this->db->prepare('SELECT id,tipo_operacao,valor,saldo_anterior,saldo_atual,descricao,data_operacao FROM cashback_movimentacoes WHERE transacao_origem_id=:origin_id OR transacao_uso_id=:usage_id ORDER BY data_operacao,id');
        $movements->execute([':origin_id' => $id, ':usage_id' => $id]);
        $item = $this->transactionRow($row) + [
            'description' => (string) ($row['descricao'] ?? ''),
            'customerEmail' => (string) $row['customer_email'],
            'adminAmountCents' => AdminMoney::cents($row['valor_admin'] ?? 0),
            'storeAmountCents' => AdminMoney::cents($row['valor_loja'] ?? 0),
            'cashbackCreditedAt' => $this->iso($row['cashback_credited_at'] ?? null),
            'movements' => array_map(fn (array $movement): array => [
                'id' => (int) $movement['id'], 'type' => (string) $movement['tipo_operacao'],
                'amountCents' => AdminMoney::cents($movement['valor']), 'previousCents' => AdminMoney::cents($movement['saldo_anterior']),
                'currentCents' => AdminMoney::cents($movement['saldo_atual']), 'description' => (string) ($movement['descricao'] ?? ''),
                'occurredAt' => $this->iso($movement['data_operacao']),
            ], $movements->fetchAll(PDO::FETCH_ASSOC)),
        ];
        return $this->state(['item' => $item]);
    }

    /** @param array<string, string> $filters @return array<string, mixed> */
    public function finance(array $filters, int $page, int $pageSize = 20): array
    {
        $status = trim((string) ($filters['status'] ?? ''));
        $statusSql = $status === '' ? '' : ' AND p.status=:status';
        $params = $status === '' ? [] : [':status' => $status];
        $offset = ($page - 1) * $pageSize;
        $commissionCount = $this->db->prepare('SELECT COUNT(*) FROM pagamentos_comissao p WHERE 1=1' . $statusSql);
        $commissionCount->execute($params);
        $commissionStmt = $this->db->prepare(
            "SELECT p.id,p.loja_id,p.valor_total,p.metodo_pagamento,p.numero_referencia,p.observacao,p.observacao_admin,p.status,p.data_registro,p.data_aprovacao,l.nome_fantasia,
                    (SELECT COUNT(*) FROM pagamentos_transacoes pt WHERE pt.pagamento_id=p.id) transaction_count,
                    (SELECT COUNT(*) FROM pagamentos_transacoes pt JOIN transacoes_cashback t ON t.id=pt.transacao_id AND t.loja_id=p.loja_id WHERE pt.pagamento_id=p.id) valid_transaction_count
             FROM pagamentos_comissao p LEFT JOIN lojas l ON l.id=p.loja_id WHERE 1=1{$statusSql} ORDER BY p.data_registro DESC,p.id DESC LIMIT {$pageSize} OFFSET {$offset}"
        );
        $commissionStmt->execute($params);

        $balanceStatusSql = $status === '' ? '' : ' WHERE p.status=:balance_status';
        $balanceStmt = $this->db->prepare(
            "SELECT p.id,p.loja_id,p.valor_total,p.metodo_pagamento,p.numero_referencia,p.observacao,p.status,p.data_criacao,p.data_processamento,l.nome_fantasia
             FROM store_balance_payments p LEFT JOIN lojas l ON l.id=p.loja_id{$balanceStatusSql} ORDER BY p.data_criacao DESC,p.id DESC LIMIT 100"
        );
        $balanceStmt->execute($status === '' ? [] : [':balance_status' => $status]);
        $stats = $this->db->query(
            "SELECT
                (SELECT COALESCE(SUM(valor_total),0) FROM pagamentos_comissao WHERE status='aprovado') commission_paid,
                (SELECT COALESCE(SUM(valor_total),0) FROM pagamentos_comissao WHERE status='pendente') commission_pending,
                (SELECT COALESCE(SUM(valor_total),0) FROM store_balance_payments WHERE status='aprovado') balance_paid,
                (SELECT COALESCE(SUM(valor_total),0) FROM store_balance_payments WHERE status IN ('pendente','em_processamento')) balance_pending"
        )->fetch(PDO::FETCH_ASSOC) ?: [];
        $commissions = array_map(fn (array $row): array => [
            'id' => (int) $row['id'], 'kind' => 'commission', 'storeId' => (int) $row['loja_id'], 'storeName' => (string) ($row['nome_fantasia'] ?? 'Loja não encontrada'),
            'amountCents' => AdminMoney::cents($row['valor_total']), 'method' => (string) $row['metodo_pagamento'],
            'reference' => $row['numero_referencia'], 'status' => (string) $row['status'], 'transactionCount' => (int) $row['transaction_count'],
            'reviewRequired' => $row['nome_fantasia'] === null || (float) $row['valor_total'] <= 0 || (int) $row['transaction_count'] === 0 || (int) $row['valid_transaction_count'] !== (int) $row['transaction_count'],
            'reviewReason' => $row['nome_fantasia'] === null ? 'Loja ausente' : ((int) $row['transaction_count'] === 0 ? 'Sem transações relacionadas' : ((int) $row['valid_transaction_count'] !== (int) $row['transaction_count'] ? 'Vínculos inconsistentes' : ((float) $row['valor_total'] <= 0 ? 'Valor inválido' : null))),
            'notes' => $row['observacao'], 'adminNotes' => $row['observacao_admin'], 'createdAt' => $this->iso($row['data_registro']), 'processedAt' => $this->iso($row['data_aprovacao']),
        ], $commissionStmt->fetchAll(PDO::FETCH_ASSOC));
        $balances = array_map(fn (array $row): array => [
            'id' => (int) $row['id'], 'kind' => 'balance_refund', 'storeId' => (int) $row['loja_id'], 'storeName' => (string) ($row['nome_fantasia'] ?? 'Loja não encontrada'),
            'amountCents' => AdminMoney::cents($row['valor_total']), 'method' => (string) $row['metodo_pagamento'],
            'reference' => $row['numero_referencia'], 'status' => (string) $row['status'], 'notes' => $row['observacao'],
            'reviewRequired' => $row['nome_fantasia'] === null || (float) $row['valor_total'] <= 0,
            'reviewReason' => $row['nome_fantasia'] === null ? 'Loja ausente' : ((float) $row['valor_total'] <= 0 ? 'Valor inválido' : null),
            'createdAt' => $this->iso($row['data_criacao']), 'processedAt' => $this->iso($row['data_processamento']),
        ], $balanceStmt->fetchAll(PDO::FETCH_ASSOC));
        return $this->state([
            'summary' => [
                'commissionPaidCents' => AdminMoney::cents($stats['commission_paid'] ?? 0),
                'commissionPendingCents' => AdminMoney::cents($stats['commission_pending'] ?? 0),
                'balancePaidCents' => AdminMoney::cents($stats['balance_paid'] ?? 0),
                'balancePendingCents' => AdminMoney::cents($stats['balance_pending'] ?? 0),
            ],
            'commissionPayments' => $commissions,
            'balancePayments' => $balances,
            'pagination' => $this->pagination($page, $pageSize, (int) $commissionCount->fetchColumn()),
        ]);
    }

    /** @param array<string, string> $filters @return array<string, mixed> */
    public function reports(array $filters): array
    {
        $start = ($filters['startDate'] ?? '') !== '' ? $filters['startDate'] . ' 00:00:00' : date('Y-m-01 00:00:00', strtotime('-11 months'));
        $end = ($filters['endDate'] ?? '') !== '' ? $filters['endDate'] . ' 23:59:59' : date('Y-m-d 23:59:59');
        $storeId = (int) ($filters['storeId'] ?? 0);
        $storeSql = $storeId > 0 ? ' AND loja_id=:store' : '';
        $params = [':start' => $start, ':end' => $end] + ($storeId > 0 ? [':store' => $storeId] : []);
        $stmt = $this->db->prepare(
            "SELECT DATE_FORMAT(data_transacao,'%Y-%m') month,financial_model,COUNT(*) sales_count,COALESCE(SUM(valor_total),0) gross_amount,
                    COALESCE(SUM(valor_cliente),0) cashback_amount
             FROM transacoes_cashback WHERE status='aprovado' AND data_transacao BETWEEN :start AND :end{$storeSql}
             GROUP BY DATE_FORMAT(data_transacao,'%Y-%m'),financial_model ORDER BY month"
        );
        $stmt->execute($params);
        $rankStoreSql = $storeId > 0 ? ' AND l.id=:rank_store' : '';
        $stores = $this->db->prepare(
            "SELECT l.id,l.nome_fantasia,COUNT(t.id) sales_count,COALESCE(SUM(t.valor_total),0) gross_amount,COALESCE(SUM(t.valor_cliente),0) cashback_amount
             FROM lojas l LEFT JOIN transacoes_cashback t ON t.loja_id=l.id AND t.status='aprovado' AND t.data_transacao BETWEEN :start AND :end
             WHERE 1=1{$rankStoreSql} GROUP BY l.id,l.nome_fantasia ORDER BY gross_amount DESC LIMIT 12"
        );
        $stores->execute([':start' => $start, ':end' => $end] + ($storeId > 0 ? [':rank_store' => $storeId] : []));
        return $this->state([
            'period' => ['start' => $this->iso($start), 'end' => $this->iso($end)],
            'monthly' => array_map(fn (array $row): array => [
                'month' => (string) $row['month'], 'model' => (string) $row['financial_model'], 'salesCount' => (int) $row['sales_count'],
                'grossAmountCents' => AdminMoney::cents($row['gross_amount']), 'cashbackAmountCents' => AdminMoney::cents($row['cashback_amount']),
            ], $stmt->fetchAll(PDO::FETCH_ASSOC)),
            'stores' => array_map(fn (array $row): array => [
                'id' => (int) $row['id'], 'name' => (string) $row['nome_fantasia'], 'salesCount' => (int) $row['sales_count'],
                'grossAmountCents' => AdminMoney::cents($row['gross_amount']), 'cashbackAmountCents' => AdminMoney::cents($row['cashback_amount']),
            ], $stores->fetchAll(PDO::FETCH_ASSOC)),
        ]);
    }

    /** @return array<string, mixed> */
    public function settings(): array
    {
        $cashback = $this->db->query('SELECT * FROM configuracoes_cashback ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [];
        $balance = $this->db->query('SELECT * FROM configuracoes_saldo ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [];
        $notifications = $this->db->query('SELECT * FROM configuracoes_notificacao ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [];
        return $this->state([
            'cashback' => [
                'customerPercentage' => (float) ($cashback['porcentagem_cliente'] ?? 5),
                'legacyAdminPercentage' => (float) ($cashback['porcentagem_admin'] ?? 0),
                'legacyStorePercentage' => (float) ($cashback['porcentagem_loja'] ?? 0),
            ],
            'balance' => [
                'enabled' => (bool) ($balance['permitir_uso_saldo'] ?? true),
                'minimumUseCents' => AdminMoney::cents($balance['valor_minimo_uso'] ?? 1),
                'maximumPurchasePercentage' => (float) ($balance['percentual_maximo_uso'] ?? 100),
                'lowBalanceNotification' => (bool) ($balance['notificar_saldo_baixo'] ?? true),
                'lowBalanceThresholdCents' => AdminMoney::cents($balance['limite_saldo_baixo'] ?? 10),
            ],
            'notifications' => [
                'newTransactionEmail' => (bool) ($notifications['email_nova_transacao'] ?? true),
                'approvedPaymentEmail' => (bool) ($notifications['email_pagamento_aprovado'] ?? true),
                'availableBalanceEmail' => (bool) ($notifications['email_saldo_disponivel'] ?? true),
                'lowBalanceEmail' => (bool) ($notifications['email_saldo_baixo'] ?? true),
                'expiredBalanceEmail' => (bool) ($notifications['email_saldo_expirado'] ?? true),
            ],
        ]);
    }

    /** @param array<string, string> $filters @return array<string, mixed> */
    public function subscriptions(array $filters, int $page, int $pageSize = 20): array
    {
        $where = []; $params = [];
        if (($filters['status'] ?? '') !== '') { $where[] = 'a.status=:status'; $params[':status'] = $filters['status']; }
        if (($filters['search'] ?? '') !== '') {
            $where[] = '(l.nome_fantasia LIKE :search_store OR l.email LIKE :search_email OR p.nome LIKE :search_plan)';
            $search = '%' . $filters['search'] . '%';
            $params[':search_store'] = $search; $params[':search_email'] = $search; $params[':search_plan'] = $search;
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $count = $this->db->prepare('SELECT COUNT(*) FROM assinaturas a JOIN lojas l ON l.id=a.loja_id JOIN planos p ON p.id=a.plano_id' . $whereSql);
        $count->execute($params); $total = (int) $count->fetchColumn(); $offset = ($page - 1) * $pageSize;
        $stmt = $this->db->prepare(
            "SELECT a.id,a.loja_id,a.status,a.ciclo,a.trial_end,a.current_period_start,a.current_period_end,a.next_invoice_date,a.created_at,a.updated_at,
                    l.nome_fantasia store_name,p.nome plan_name,p.slug plan_slug
             FROM assinaturas a JOIN lojas l ON l.id=a.loja_id JOIN planos p ON p.id=a.plano_id{$whereSql}
             ORDER BY a.updated_at DESC,a.id DESC LIMIT {$pageSize} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->page(array_map(fn (array $row): array => $this->subscriptionRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC)), $page, $pageSize, $total);
    }

    /** @return array<string, mixed> */
    public function subscription(int $id): array
    {
        $stmt = $this->db->prepare(
            'SELECT a.*,l.nome_fantasia store_name,l.email store_email,p.nome plan_name,p.slug plan_slug FROM assinaturas a JOIN lojas l ON l.id=a.loja_id JOIN planos p ON p.id=a.plano_id WHERE a.id=:id LIMIT 1'
        );
        $stmt->execute([':id' => $id]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new AdminApiException('Assinatura não encontrada.', 404); }
        $invoice = $this->db->prepare('SELECT id,numero,amount,status,due_date,paid_at,payment_method,created_at FROM faturas WHERE assinatura_id=:id ORDER BY created_at DESC');
        $invoice->execute([':id' => $id]);
        return $this->state(['item' => $this->subscriptionRow($row) + [
            'storeEmail' => (string) $row['store_email'],
            'invoices' => array_map(fn (array $item): array => [
                'id' => (int) $item['id'], 'number' => (string) $item['numero'], 'amountCents' => AdminMoney::cents($item['amount']),
                'status' => (string) $item['status'], 'dueDate' => $this->iso($item['due_date']), 'paidAt' => $this->iso($item['paid_at']),
                'paymentMethod' => $item['payment_method'], 'createdAt' => $this->iso($item['created_at']),
            ], $invoice->fetchAll(PDO::FETCH_ASSOC)),
        ]]);
    }

    /** @return array<string, mixed> */
    public function plans(): array
    {
        $items = $this->db->query('SELECT id,nome,slug,codigo,descricao,preco_mensal,preco_anual,trial_dias,recorrencia,features_json,ativo,created_at,updated_at FROM planos ORDER BY ativo DESC,preco_mensal,id')->fetchAll(PDO::FETCH_ASSOC);
        return $this->state(['items' => array_map(fn (array $row): array => [
            'id' => (int) $row['id'], 'name' => (string) $row['nome'], 'slug' => (string) $row['slug'], 'code' => $row['codigo'],
            'description' => $row['descricao'], 'monthlyPriceCents' => AdminMoney::cents($row['preco_mensal']), 'annualPriceCents' => AdminMoney::cents($row['preco_anual']),
            'trialDays' => (int) $row['trial_dias'], 'recurrence' => (string) $row['recorrencia'],
            'features' => (array) (json_decode((string) ($row['features_json'] ?? '[]'), true) ?: []), 'active' => (bool) $row['ativo'], 'updatedAt' => $this->iso($row['updated_at']),
        ], $items)]);
    }

    /** @return array<string, mixed> */
    public function campaigns(int $page, int $pageSize = 20): array
    {
        $total = (int) $this->db->query('SELECT COUNT(*) FROM email_campaigns')->fetchColumn(); $offset = ($page - 1) * $pageSize;
        $stmt = $this->db->query("SELECT id,titulo,assunto,data_criacao,data_agendamento,status,requires_review,total_emails,emails_enviados,emails_falharam,updated_at FROM email_campaigns ORDER BY data_criacao DESC,id DESC LIMIT {$pageSize} OFFSET {$offset}");
        return $this->page(array_map(fn (array $row): array => [
            'id' => (int) $row['id'], 'title' => (string) $row['titulo'], 'subject' => (string) $row['assunto'], 'status' => (string) $row['status'],
            'requiresReview' => (bool) $row['requires_review'], 'totalRecipients' => (int) $row['total_emails'], 'sent' => (int) $row['emails_enviados'],
            'failed' => (int) $row['emails_falharam'], 'scheduledAt' => $this->iso($row['data_agendamento']), 'createdAt' => $this->iso($row['data_criacao']), 'updatedAt' => $this->iso($row['updated_at']),
        ], $stmt->fetchAll(PDO::FETCH_ASSOC)), $page, $pageSize, $total);
    }

    /** @return array<string, mixed> */
    public function campaign(int $id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM email_campaigns WHERE id=:id LIMIT 1'); $stmt->execute([':id' => $id]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new AdminApiException('Campanha não encontrada.', 404); }
        return $this->state(['item' => [
            'id' => (int) $row['id'], 'title' => (string) $row['titulo'], 'subject' => (string) $row['assunto'],
            'html' => (string) $row['conteudo_html'], 'text' => (string) ($row['conteudo_texto'] ?? ''),
            'audience' => (array) (json_decode((string) ($row['audience_json'] ?? '{}'), true) ?: []), 'status' => (string) $row['status'],
            'requiresReview' => (bool) $row['requires_review'], 'scheduledAt' => $this->iso($row['data_agendamento']), 'updatedAt' => $this->iso($row['updated_at']),
        ]]);
    }

    /** @return array<string, mixed> */
    public function templates(): array
    {
        $rows = $this->db->query('SELECT id,nome,assunto_padrao,conteudo_html,tipo,ativo,archived_at,data_criacao,updated_at FROM email_templates WHERE archived_at IS NULL ORDER BY ativo DESC,nome')->fetchAll(PDO::FETCH_ASSOC);
        return $this->state(['items' => array_map(fn (array $row): array => [
            'id' => (int) $row['id'], 'name' => (string) $row['nome'], 'subject' => (string) ($row['assunto_padrao'] ?? ''),
            'html' => (string) $row['conteudo_html'], 'type' => (string) $row['tipo'], 'active' => (bool) $row['ativo'], 'updatedAt' => $this->iso($row['updated_at']),
        ], $rows)]);
    }

    /** @param array<string, string> $filters @return array<string, mixed> */
    public function audit(array $filters, int $page, int $pageSize = 30): array
    {
        $where = []; $params = [];
        if (($filters['action'] ?? '') !== '') { $where[] = 'a.action LIKE :action'; $params[':action'] = '%' . $filters['action'] . '%'; }
        if (($filters['entityType'] ?? '') !== '') { $where[] = 'a.entity_type=:type'; $params[':type'] = $filters['entityType']; }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $count = $this->db->prepare('SELECT COUNT(*) FROM admin_audit_logs a' . $whereSql); $count->execute($params); $total = (int) $count->fetchColumn();
        $offset = ($page - 1) * $pageSize;
        $stmt = $this->db->prepare("SELECT a.id,a.action,a.entity_type,a.entity_id,a.result,a.request_id,a.created_at,u.nome actor_name FROM admin_audit_logs a JOIN usuarios u ON u.id=a.actor_id{$whereSql} ORDER BY a.created_at DESC,a.id DESC LIMIT {$pageSize} OFFSET {$offset}");
        $stmt->execute($params);
        return $this->page(array_map(fn (array $row): array => [
            'id' => (int) $row['id'], 'action' => (string) $row['action'], 'entityType' => (string) $row['entity_type'], 'entityId' => $row['entity_id'],
            'result' => (string) $row['result'], 'requestId' => (string) $row['request_id'], 'actorName' => (string) $row['actor_name'], 'createdAt' => $this->iso($row['created_at']),
        ], $stmt->fetchAll(PDO::FETCH_ASSOC)), $page, $pageSize, $total);
    }

    /** @param array<string, mixed> $audience */
    public function audienceCount(array $audience): int
    {
        [$sql, $params] = $this->audienceQuery($audience, true);
        $stmt = $this->db->prepare($sql); $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @param array<string, mixed> $audience @return array{0:string,1:array<string,mixed>} */
    public function audienceQuery(array $audience, bool $count = false): array
    {
        $types = array_values(array_intersect((array) ($audience['types'] ?? ['cliente']), ['cliente', 'loja', 'funcionario']));
        if ($types === []) { throw new AdminApiException('Selecione pelo menos um público.', 422, ['types' => ['Público obrigatório.']]); }
        $params = []; $placeholders = [];
        foreach ($types as $index => $type) { $placeholders[] = ':type' . $index; $params[':type' . $index] = $type; }
        $where = [
            'u.email IS NOT NULL',
            "u.email<>''",
            "u.email REGEXP '^[^[:space:]@]+@[^[:space:]@]+[.][^[:space:]@]+$'",
            'u.tipo IN (' . implode(',', $placeholders) . ')',
        ];
        if (($audience['status'] ?? '') !== '') { $where[] = 'u.status=:status'; $params[':status'] = (string) $audience['status']; }
        if ((int) ($audience['storeId'] ?? 0) > 0) {
            $where[] = '(u.loja_vinculada_id=:employee_store OR l.id=:owner_store)';
            $params[':employee_store'] = (int) $audience['storeId'];
            $params[':owner_store'] = (int) $audience['storeId'];
        }
        if (($audience['registeredAfter'] ?? '') !== '') { $where[] = 'u.data_criacao>=:after'; $params[':after'] = (string) $audience['registeredAfter'] . ' 00:00:00'; }
        $select = $count ? 'COUNT(DISTINCT LOWER(u.email))' : 'MIN(u.id) id,MIN(u.nome) nome,LOWER(u.email) email';
        return ["SELECT {$select} FROM usuarios u LEFT JOIN lojas l ON l.usuario_id=u.id WHERE " . implode(' AND ', $where) . ($count ? '' : ' GROUP BY LOWER(u.email) ORDER BY MIN(u.id)'), $params];
    }

    /** @param array<string, mixed> $extra @return array<string, mixed> */
    private function state(array $extra): array
    {
        $dataState = 'ready';
        foreach (['items', 'recentTransactions', 'monthly'] as $key) {
            if (array_key_exists($key, $extra) && is_array($extra[$key]) && $extra[$key] === []) { $dataState = 'empty'; break; }
        }
        if (
            array_key_exists('commissionPayments', $extra)
            && array_key_exists('balancePayments', $extra)
            && $extra['commissionPayments'] === []
            && $extra['balancePayments'] === []
        ) {
            $dataState = 'empty';
        }
        return ['dataState' => $dataState, 'generatedAt' => date(DATE_ATOM)] + $extra;
    }

    /** @param array<int, array<string, mixed>> $items @return array<string, mixed> */
    private function page(array $items, int $page, int $pageSize, int $total): array
    {
        return $this->state(['items' => $items, 'pagination' => $this->pagination($page, $pageSize, $total)]);
    }

    /** @return array<string, int> */
    private function pagination(int $page, int $pageSize, int $total): array
    {
        return ['page' => $page, 'pageSize' => $pageSize, 'totalItems' => $total, 'totalPages' => max(1, (int) ceil($total / $pageSize))];
    }

    /** @param array<string, string> $filters @param array<string, array{0:string,1:string}> $map @return array{0:array<int,string>,1:array<string,mixed>} */
    private function where(array $filters, array $map): array
    {
        $where = []; $params = [];
        foreach ($map as $name => [$sql, $parameter]) {
            if (($filters[$name] ?? '') !== '') { $where[] = $sql; $params[':' . $parameter] = $filters[$name]; }
        }
        return [$where, $params];
    }

    /** @return array<string, mixed> */
    private function userRow(array $row): array
    {
        return [
            'id' => (int) $row['id'], 'name' => (string) $row['nome'], 'email' => (string) ($row['email'] ?? ''), 'phone' => (string) ($row['telefone'] ?? ''),
            'status' => (string) $row['status'], 'type' => (string) $row['tipo'], 'customerType' => $row['tipo_cliente'],
            'linkedStoreId' => $row['loja_vinculada_id'] === null ? null : (int) $row['loja_vinculada_id'], 'linkedStoreName' => $row['linked_store'] ?? null,
            'employeeSubtype' => $row['subtipo_funcionario'] ?? null, 'createdAt' => $this->iso($row['data_criacao']), 'lastLoginAt' => $this->iso($row['ultimo_login']),
            'updatedAt' => $this->iso($row['updated_at']),
        ];
    }

    /** @return array<string, mixed> */
    private function storeRow(array $row): array
    {
        return [
            'id' => (int) $row['id'], 'name' => (string) $row['nome_fantasia'], 'legalName' => (string) $row['razao_social'], 'cnpj' => (string) $row['cnpj'],
            'email' => (string) $row['email'], 'phone' => (string) $row['telefone'], 'category' => (string) $row['categoria'], 'status' => (string) $row['status'],
            'notes' => $row['observacao'] ?? null, 'customerCashbackPercentage' => (float) $row['porcentagem_cliente'], 'cashbackEnabled' => (bool) $row['cashback_ativo'],
            'ownerName' => (string) ($row['owner_name'] ?? ''), 'transactionsCount' => (int) ($row['transactions_count'] ?? 0),
            'grossAmountCents' => AdminMoney::cents($row['gross_amount'] ?? 0), 'registeredAt' => $this->iso($row['data_cadastro']), 'approvedAt' => $this->iso($row['data_aprovacao']),
            'updatedAt' => $this->iso($row['updated_at']),
        ];
    }

    /** @return array<string, mixed> */
    private function transactionRow(array $row): array
    {
        $gross = AdminMoney::cents($row['valor_total']); $balance = AdminMoney::cents($row['balance_used'] ?? 0);
        return [
            'id' => (int) $row['id'], 'code' => (string) ($row['codigo_transacao'] ?? ''), 'customerName' => (string) $row['customer_name'],
            'storeName' => (string) $row['store_name'], 'grossAmountCents' => $gross, 'balanceUsedCents' => $balance,
            'paidAmountCents' => max(0, $gross - $balance), 'cashbackAmountCents' => AdminMoney::cents($row['valor_cliente'] ?? $row['valor_cashback'] ?? 0),
            'status' => (string) $row['status'], 'financialModel' => (string) ($row['financial_model'] ?? 'commission_legacy'), 'occurredAt' => $this->iso($row['data_transacao']),
        ];
    }

    /** @return array<string, mixed> */
    private function subscriptionRow(array $row): array
    {
        return [
            'id' => (int) $row['id'], 'storeId' => (int) $row['loja_id'], 'storeName' => (string) $row['store_name'], 'planName' => (string) $row['plan_name'],
            'planSlug' => (string) $row['plan_slug'], 'status' => (string) $row['status'], 'cycle' => (string) $row['ciclo'], 'trialEnd' => $this->iso($row['trial_end']),
            'periodStart' => $this->iso($row['current_period_start']), 'periodEnd' => $this->iso($row['current_period_end']), 'nextInvoiceDate' => $this->iso($row['next_invoice_date']),
            'createdAt' => $this->iso($row['created_at']), 'updatedAt' => $this->iso($row['updated_at']),
        ];
    }

    private function iso(mixed $value): ?string
    {
        if ($value === null || $value === '') { return null; }
        $time = strtotime((string) $value); return $time === false ? null : date(DATE_ATOM, $time);
    }

    private function initial(string $name): string
    {
        $first = function_exists('mb_substr') ? mb_substr(trim($name), 0, 1, 'UTF-8') : substr(trim($name), 0, 1);
        return function_exists('mb_strtoupper') ? mb_strtoupper($first, 'UTF-8') : strtoupper($first);
    }
}
