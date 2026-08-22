<?php

declare(strict_types=1);

namespace App\Services\Store;

use PDO;
use Throwable;

final class StoreCustomerService
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array<string, mixed> */
    public function search(int $storeId, string $term): array
    {
        $term = trim($term);
        if (strlen($term) < 3) {
            throw new StoreApiException('Digite pelo menos três caracteres para buscar.', 422, [
                'query' => ['Digite pelo menos três caracteres.'],
            ]);
        }
        $digits = preg_replace('/\D+/', '', $term);
        $statement = $this->db->prepare(
            "SELECT u.id,u.nome,u.email,u.telefone,u.cpf,u.tipo_cliente,u.loja_criadora_id,"
            . 'COALESCE(MAX(cs.saldo_disponivel),0) balance,COUNT(t.id) purchases,COALESCE(SUM(t.valor_total),0) spent '
            . 'FROM usuarios u LEFT JOIN cashback_saldos cs ON cs.usuario_id=u.id AND cs.loja_id=:store_id '
            . "LEFT JOIN transacoes_cashback t ON t.usuario_id=u.id AND t.loja_id=:store_id_tx AND t.status='aprovado' "
            . "WHERE u.tipo='cliente' AND u.status='ativo' AND (u.email=:term OR u.cpf=:digits OR u.telefone=:digits_phone) "
            . 'GROUP BY u.id ORDER BY (u.loja_criadora_id=:priority_store) DESC,u.id DESC LIMIT 1'
        );
        $statement->execute([
            ':store_id' => $storeId,
            ':store_id_tx' => $storeId,
            ':term' => strtolower($term),
            ':digits' => $digits,
            ':digits_phone' => $digits,
            ':priority_store' => $storeId,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return [
                'dataState' => 'empty',
                'generatedAt' => date(DATE_ATOM),
                'customer' => null,
                'canCreateVisitor' => strlen((string) $digits) >= 10,
                'suggestedPhone' => strlen((string) $digits) >= 10 ? $digits : '',
            ];
        }

        return [
            'dataState' => 'ready',
            'generatedAt' => date(DATE_ATOM),
            'customer' => $this->row($row, $storeId),
            'canCreateVisitor' => false,
            'suggestedPhone' => '',
        ];
    }

    /** @return array<string, mixed> */
    public function createVisitor(int $storeId, string $name, string $phone): array
    {
        $name = trim($name);
        $phone = preg_replace('/\D+/', '', $phone) ?? '';
        $errors = [];
        if (strlen($name) < 3 || strlen($name) > 100) {
            $errors['name'] = ['Informe um nome entre 3 e 100 caracteres.'];
        }
        if (strlen($phone) < 10 || strlen($phone) > 11) {
            $errors['phone'] = ['Informe um telefone válido com DDD.'];
        }
        if ($errors !== []) {
            throw new StoreApiException('Revise os dados do visitante.', 422, $errors);
        }

        $existing = $this->db->prepare(
            "SELECT id FROM usuarios WHERE loja_criadora_id=:store_id AND tipo='cliente' AND telefone=:phone LIMIT 1"
        );
        $existing->execute([':store_id' => $storeId, ':phone' => $phone]);
        $existingId = (int) ($existing->fetchColumn() ?: 0);
        if ($existingId > 0) {
            return $this->get($storeId, $existingId);
        }

        $email = sprintf('visitante_%s_loja_%d@klubecash.local', $phone, $storeId);
        try {
            $this->db->beginTransaction();
            $insert = $this->db->prepare(
                "INSERT INTO usuarios (nome,email,telefone,tipo,tipo_cliente,loja_criadora_id,status,provider,email_verified) "
                . "VALUES (:name,:email,:phone,'cliente','visitante',:store_id,'ativo','local',0)"
            );
            $insert->execute([':name' => $name, ':email' => $email, ':phone' => $phone, ':store_id' => $storeId]);
            $customerId = (int) $this->db->lastInsertId();
            $balance = $this->db->prepare(
                "INSERT IGNORE INTO cashback_saldos (usuario_id,loja_id,saldo_disponivel,total_creditado,total_usado) "
                . "VALUES (:customer_id,:store_id,'0.00','0.00','0.00')"
            );
            $balance->execute([':customer_id' => $customerId, ':store_id' => $storeId]);
            $this->db->commit();
            return $this->get($storeId, $customerId);
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function get(int $storeId, int $customerId): array
    {
        $statement = $this->db->prepare(
            "SELECT u.id,u.nome,u.email,u.telefone,u.cpf,u.tipo_cliente,u.loja_criadora_id,"
            . 'COALESCE(MAX(cs.saldo_disponivel),0) balance,COUNT(t.id) purchases,COALESCE(SUM(t.valor_total),0) spent '
            . 'FROM usuarios u LEFT JOIN cashback_saldos cs ON cs.usuario_id=u.id AND cs.loja_id=:store_id '
            . "LEFT JOIN transacoes_cashback t ON t.usuario_id=u.id AND t.loja_id=:store_id_tx AND t.status='aprovado' "
            . "WHERE u.id=:customer_id AND u.tipo='cliente' AND u.status='ativo' GROUP BY u.id LIMIT 1"
        );
        $statement->execute([':store_id' => $storeId, ':store_id_tx' => $storeId, ':customer_id' => $customerId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new StoreApiException('Cliente não encontrado.', 404);
        }
        return [
            'dataState' => 'ready',
            'generatedAt' => date(DATE_ATOM),
            'customer' => $this->row($row, $storeId),
        ];
    }

    /** @param array<string, mixed> $row
     *  @return array<string, mixed>
     */
    private function row(array $row, int $storeId): array
    {
        $visitor = ($row['tipo_cliente'] ?? '') === 'visitante';
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['nome'],
            'email' => $visitor ? null : (string) ($row['email'] ?? ''),
            'phone' => (string) ($row['telefone'] ?? ''),
            'cpf' => $visitor ? null : ($row['cpf'] ?: null),
            'type' => $visitor ? 'visitor' : 'registered',
            'createdByThisStore' => (int) ($row['loja_criadora_id'] ?? 0) === $storeId,
            'balanceCents' => StoreMoney::toCents($row['balance'] ?? 0),
            'purchaseCount' => (int) ($row['purchases'] ?? 0),
            'spentAmountCents' => StoreMoney::toCents($row['spent'] ?? 0),
        ];
    }
}
