<?php

declare(strict_types=1);

namespace App\Services\Store;

use PDO;

/** Database-backed authorization; the active store in a session is only a hint. */
final class StoreNetworkAccess
{
    public function __construct(private PDO $db) {}

    public function stores(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT DISTINCT l.id,l.nome_fantasia name,CASE WHEN gm.user_id IS NOT NULL THEN 'gestor_rede' ELSE m.role END role,r.id network_id
             FROM lojas l
             LEFT JOIN store_user_memberships m ON m.store_id=l.id AND m.user_id=? AND m.status='active'
             LEFT JOIN store_network_memberships n ON n.store_id=l.id AND n.status='active'
             LEFT JOIN store_networks r ON r.id=n.network_id AND r.status='active'
             LEFT JOIN store_network_managers gm ON gm.network_id=r.id AND gm.user_id=?
             JOIN usuarios u ON u.id=? AND u.status='ativo' AND u.tipo IN ('loja','funcionario')
             WHERE l.status='aprovado' AND (m.user_id IS NOT NULL OR gm.user_id IS NOT NULL)
             ORDER BY l.nome_fantasia,l.id"
        );
        $stmt->execute([$userId, $userId, $userId]);
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'], 'name' => $row['name'], 'role' => $row['role'],
            'networkId' => $row['network_id'] === null ? null : (int) $row['network_id'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function membership(int $userId, int $storeId): ?array
    {
        foreach ($this->stores($userId) as $store) {
            if ($store['id'] === $storeId) { return $store; }
        }
        return null;
    }

    public function select(int $userId, int $storeId): array
    {
        $membership = $this->membership($userId, $storeId);
        if (!$membership) { throw new StoreApiException('Filial indisponível para esta conta.', 403); }
        $_SESSION['store_id'] = $storeId;
        $_SESSION['loja_vinculada_id'] = $storeId;
        $_SESSION['store_name'] = $membership['name'];
        $_SESSION['employee_subtype'] = $membership['role'];
        $_SESSION['subtipo_funcionario'] = $membership['role'];
        return $membership;
    }

    /** Active branches in the same network; standalone stores never share. */
    public function walletStores(int $storeId): array
    {
        $stmt = $this->db->prepare("SELECT network_id FROM store_network_memberships WHERE store_id=? AND status='active'");
        $stmt->execute([$storeId]);
        $network = $stmt->fetchColumn();
        if ($network === false) { return [$storeId]; }
        $stmt = $this->db->prepare("SELECT n.store_id FROM store_network_memberships n JOIN lojas l ON l.id=n.store_id
            JOIN store_networks r ON r.id=n.network_id WHERE n.network_id=? AND n.status='active'
            AND r.status='active' AND l.status='aprovado' ORDER BY n.store_id");
        $stmt->execute([(int) $network]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        return in_array($storeId, $ids, true) ? $ids : [$storeId];
    }

    public function networkId(int $storeId): ?int
    {
        $stmt = $this->db->prepare("SELECT n.network_id FROM store_network_memberships n JOIN store_networks r ON r.id=n.network_id
            WHERE n.store_id=? AND n.status='active' AND r.status='active'");
        $stmt->execute([$storeId]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /** A network manager may operate only on approved, active branches of their network. */
    public function assertManagedBranch(int $userId, int $networkId, int $storeId): void
    {
        $stmt = $this->db->prepare("SELECT 1 FROM store_network_managers gm
            JOIN store_networks r ON r.id=gm.network_id AND r.status='active'
            JOIN store_network_memberships m ON m.network_id=r.id AND m.status='active'
            JOIN lojas l ON l.id=m.store_id AND l.status='aprovado'
            JOIN usuarios u ON u.id=gm.user_id AND u.status='ativo' AND u.tipo IN ('loja','funcionario')
            WHERE gm.user_id=? AND gm.network_id=? AND m.store_id=? LIMIT 1");
        $stmt->execute([$userId, $networkId, $storeId]);
        if (!$stmt->fetchColumn()) { throw new StoreApiException('Filial fora da sua rede ou indisponível.', 403); }
    }

    public function assertSeller(int $sellerId, int $storeId): string
    {
        $membership = $this->membership($sellerId, $storeId);
        if (!$membership) { throw new StoreApiException('Vendedor não está ativo nesta filial.', 422); }
        $stmt = $this->db->prepare("SELECT nome FROM usuarios WHERE id=? AND status='ativo' AND tipo IN ('loja','funcionario')");
        $stmt->execute([$sellerId]);
        $name = $stmt->fetchColumn();
        if (!$name) { throw new StoreApiException('Vendedor indisponível.', 422); }
        return (string) $name;
    }

    public function actorName(int $userId): string
    {
        $stmt = $this->db->prepare('SELECT nome FROM usuarios WHERE id=?');
        $stmt->execute([$userId]);
        return (string) ($stmt->fetchColumn() ?: 'Não identificado');
    }
}
