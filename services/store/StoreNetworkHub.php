<?php

declare(strict_types=1);

namespace App\Services\Store;

use PDO;

require_once __DIR__ . '/StoreMoney.php';

/** Read model for the merchant's network; authorization is always database-backed. */
final class StoreNetworkHub
{
    public function __construct(private PDO $db) {}

    private function assertManager(int $userId, int $networkId): void
    {
        $stmt = $this->db->prepare("SELECT 1 FROM store_network_managers gm
            JOIN store_networks n ON n.id=gm.network_id AND n.status='active'
            JOIN usuarios u ON u.id=gm.user_id AND u.status='ativo' AND u.tipo IN ('loja','funcionario')
            WHERE gm.network_id=? AND gm.user_id=? LIMIT 1");
        $stmt->execute([$networkId, $userId]);
        if (!$stmt->fetchColumn()) { throw new StoreApiException('Gestão da rede não autorizada.', 403); }
    }

    public function overview(int $userId, int $networkId): array
    {
        $this->assertManager($userId, $networkId);
        $stmt = $this->db->prepare("SELECT l.id store_id,l.nome_fantasia store_name,l.cnpj,
            (SELECT COUNT(*) FROM store_user_memberships m WHERE m.store_id=l.id AND m.role<>'titular' AND m.status='active') active_staff,
            (SELECT COUNT(*) FROM store_user_memberships m WHERE m.store_id=l.id AND m.role<>'titular' AND m.status='pending') pending_staff,
            (SELECT COUNT(*) FROM transacoes_cashback t WHERE t.loja_id=l.id AND t.status='aprovado') approved_sales,
            (SELECT COALESCE(SUM(t.valor_total),0) FROM transacoes_cashback t WHERE t.loja_id=l.id AND t.status='aprovado') approved_total
            FROM store_networks n JOIN store_network_memberships nm ON nm.network_id=n.id AND nm.status='active'
            JOIN lojas l ON l.id=nm.store_id AND l.status='aprovado'
            WHERE n.id=? AND n.status='active'
            ORDER BY l.nome_fantasia,l.id");
        $stmt->execute([$networkId]);
        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $items[] = [
                'id' => (int) $row['store_id'], 'name' => $row['store_name'], 'cnpj' => $row['cnpj'],
                'activeStaff' => (int) $row['active_staff'], 'pendingStaff' => (int) $row['pending_staff'],
                'approvedSales' => (int) $row['approved_sales'],
                'approvedAmountCents' => StoreMoney::toCents($row['approved_total']),
            ];
        }
        $managers = $this->db->prepare('SELECT u.id,u.nome name FROM store_network_managers gm JOIN usuarios u ON u.id=gm.user_id WHERE gm.network_id=? AND u.status=\'ativo\' ORDER BY u.nome');
        $managers->execute([$networkId]);
        return ['networkId' => $networkId, 'networkName' => $this->name($networkId),
            'branches' => $items, 'managers' => $managers->fetchAll(PDO::FETCH_ASSOC),
            'dataState' => $items ? 'ready' : 'empty'];
    }

    private function name(int $networkId): string
    {
        $stmt = $this->db->prepare('SELECT name FROM store_networks WHERE id=?');
        $stmt->execute([$networkId]);
        return (string) ($stmt->fetchColumn() ?: 'Rede');
    }

    public function team(int $userId, int $networkId, string $search, int $page): array
    {
        $this->assertManager($userId, $networkId);
        $search = trim($search);
        $page = max(1, $page);
        $where = "nm.network_id=? AND nm.status='active' AND l.status='aprovado' AND u.tipo='funcionario'";
        $params = [$networkId];
        if ($search !== '') {
            $where .= ' AND (u.nome LIKE ? OR u.email LIKE ?)';
            $params[] = '%' . $search . '%'; $params[] = '%' . $search . '%';
        }
        $count = $this->db->prepare("SELECT COUNT(DISTINCT u.id) FROM usuarios u
            JOIN store_user_memberships m ON m.user_id=u.id
            JOIN store_network_memberships nm ON nm.store_id=m.store_id
            JOIN lojas l ON l.id=nm.store_id WHERE {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $pageSize = 20;
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $ids = $this->db->prepare("SELECT DISTINCT u.id FROM usuarios u
            JOIN store_user_memberships m ON m.user_id=u.id
            JOIN store_network_memberships nm ON nm.store_id=m.store_id
            JOIN lojas l ON l.id=nm.store_id WHERE {$where}
            ORDER BY u.id DESC LIMIT {$pageSize} OFFSET " . (($page - 1) * $pageSize));
        $ids->execute($params);
        $userIds = array_map('intval', $ids->fetchAll(PDO::FETCH_COLUMN));
        if ($userIds === []) { return ['items' => [], 'pagination' => ['page' => $page, 'pageSize' => $pageSize, 'totalItems' => $total, 'totalPages' => $pages]]; }
        $list = implode(',', $userIds);
        $stmt = $this->db->prepare("SELECT u.id,u.nome,u.email,u.status user_status,m.store_id,l.nome_fantasia store_name,m.role,m.status,
            EXISTS(SELECT 1 FROM store_network_managers gm WHERE gm.network_id=nm.network_id AND gm.user_id=u.id) network_manager
            FROM usuarios u JOIN store_user_memberships m ON m.user_id=u.id
            JOIN store_network_memberships nm ON nm.store_id=m.store_id AND nm.network_id=? AND nm.status='active'
            JOIN lojas l ON l.id=m.store_id AND l.status='aprovado'
            WHERE u.id IN ({$list}) ORDER BY u.nome,l.nome_fantasia");
        $stmt->execute([$networkId]);
        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (int) $row['id'];
            $grouped[$id] ??= ['id' => $id, 'name' => $row['nome'], 'email' => $row['email'],
                'accountStatus' => $row['user_status'], 'networkManager' => (bool) $row['network_manager'], 'branches' => []];
            $grouped[$id]['branches'][] = ['storeId' => (int) $row['store_id'], 'storeName' => $row['store_name'],
                'role' => $row['role'], 'status' => $row['status']];
        }
        return ['items' => array_values($grouped), 'pagination' => ['page' => $page, 'pageSize' => $pageSize,
            'totalItems' => $total, 'totalPages' => $pages]];
    }
}
