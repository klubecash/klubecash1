<?php

declare(strict_types=1);

namespace App\Services\StoreProvision;

use App\Services\Partner\PartnerApiException;
use PDO;

require_once __DIR__ . '/../Partner/PartnerApiException.php';

/**
 * Provisionamento pela Loja do Grupo Klube: quem assina o KlubeCash na loja recebe a loja já APROVADA, ligada ao login único,
 * e cai no cadastro em etapas para completar os dados. Idempotente pela referência da assinatura (id da assinatura na loja).
 *
 * Regras de segurança:
 *  - nunca converte uma conta de CLIENTE existente em loja (pode ter saldo de cashback): recusa com motivo legível;
 *  - só usa tabelas NOVAS (loja_assinatura_externa, loja_onboarding); as tabelas existentes só recebem linhas novas
 *    (usuarios, lojas, assinaturas) ou o estado da assinatura que a própria loja controla.
 */
final class StoreProvisionService
{
    public function __construct(private PDO $db)
    {
    }

    public static function ensureSchema(PDO $db): void
    {
        $db->exec('CREATE TABLE IF NOT EXISTS loja_assinatura_externa (
            ref VARCHAR(80) NOT NULL PRIMARY KEY, store_id INT NOT NULL, user_id INT NOT NULL, identity_sub CHAR(36) NOT NULL,
            subscription_id INT NULL, last_action VARCHAR(16) NOT NULL, valid_until DATE NULL,
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            KEY idx_loja_ext_store(store_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $db->exec('CREATE TABLE IF NOT EXISTS loja_onboarding (
            store_id INT NOT NULL PRIMARY KEY, step TINYINT NOT NULL DEFAULT 1,
            started_at DATETIME NOT NULL, completed_at DATETIME NULL, updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    /**
     * @param array<string, mixed> $payload
     * @param callable(string, int): void $link liga a conta nova ao login único (lança exceção se recusar)
     * @return array<string, mixed>
     */
    public function activate(array $payload, callable $link): array
    {
        $in = $this->validate($payload);
        self::ensureSchema($this->db);
        $until = $in['validUntil'] ?? date('Y-m-d', strtotime('+35 days'));

        $existing = $this->external($in['ref']);
        if ($existing !== null) {
            $subscriptionId = $this->syncSubscription((int) $existing['store_id'], $in['plan'], $until, true, null);
            $this->touch($in['ref'], 'activate', $until, $subscriptionId);
            return $this->result((int) $existing['store_id'], (int) $existing['user_id'], false);
        }

        $email = $in['email'];
        $created = false;
        $needLink = false;

        // Fase 1 (fora de transação): resolve a conta. O login único lê o KlubeCash por outra conexão, então a conta
        // nova precisa estar CONFIRMADA no banco antes de pedir a ligação.
        $found = $this->db->prepare('SELECT id, tipo, status FROM usuarios WHERE email = ? LIMIT 1');
        $found->execute([$email]);
        $user = $found->fetch(PDO::FETCH_ASSOC);
        if ($user === false) {
            $userId = $this->createPendingUser($in['name'], $email, $in['phone']);
            $created = true;
            $needLink = true;
        } else {
            $userId = (int) $user['id'];
            if ((string) $user['tipo'] === 'loja' && (string) $user['status'] === 'ativo') {
                // Já é loja (ex.: tentativa anterior que parou no meio): segue.
            } elseif ((string) $user['tipo'] === 'cliente' && (string) $user['status'] === 'ativo' && $this->untouchedCustomer($userId)) {
                $needLink = true; // sobra de uma tentativa anterior, sem senha, sem login e sem saldo
            } else {
                throw new PartnerApiException('Já existe uma conta KlubeCash de cliente com este e-mail. Para não perder o saldo dela, o Grupo precisa ligar a loja a essa conta.', 409, 'account_conflict');
            }
        }
        if ($needLink) {
            try {
                $link($in['identitySub'], $userId);
            } catch (\Throwable $error) {
                if ($created) {
                    $this->db->prepare("DELETE FROM usuarios WHERE id = ? AND tipo = 'cliente' AND senha_hash IS NULL AND ultimo_login IS NULL")->execute([$userId]);
                }
                throw new PartnerApiException('Não foi possível ligar a conta ao login único agora. Tente de novo em instantes.', 502, 'link_failed');
            }
        }

        // Fase 2 (transação): vira loja, cria a loja aprovada, o cadastro em etapas e a assinatura.
        $conn = $this->db;
        $conn->beginTransaction();
        try {
            $conn->prepare("UPDATE usuarios SET tipo = 'loja' WHERE id = ? AND tipo = 'cliente'")->execute([$userId]);
            $store = $conn->prepare('SELECT id FROM lojas WHERE usuario_id = ? ORDER BY id LIMIT 1');
            $store->execute([$userId]);
            $storeId = (int) ($store->fetchColumn() ?: 0);
            if ($storeId === 0) {
                $dup = $conn->prepare('SELECT id FROM lojas WHERE cnpj = ? LIMIT 1');
                $dup->execute([$in['doc']]);
                if ($dup->fetchColumn()) {
                    throw new PartnerApiException('Já existe uma loja no KlubeCash com este CPF/CNPJ.', 409, 'store_conflict');
                }
                $conn->prepare("INSERT INTO lojas (usuario_id, nome_fantasia, razao_social, cnpj, email, telefone, categoria, porcentagem_cashback, porcentagem_cliente, porcentagem_admin, cashback_ativo, status, data_cadastro, data_aprovacao)
                    VALUES (?, ?, ?, ?, ?, ?, 'Outros', 10.00, 5.00, 5.00, 0, 'aprovado', NOW(), NOW())")
                    ->execute([$userId, $in['business'], $in['business'], $in['doc'], $email, $in['phone']]);
                $storeId = (int) $conn->lastInsertId();
                // Loja criada pela assinatura: o dono completa o cadastro em etapas antes de usar o painel.
                $conn->prepare('INSERT IGNORE INTO loja_onboarding (store_id, step, started_at, updated_at) VALUES (?, 1, NOW(), NOW())')->execute([$storeId]);
            }
            $subscriptionId = $this->syncSubscription($storeId, $in['plan'], $until, true, null);
            $conn->prepare('INSERT INTO loja_assinatura_externa (ref, store_id, user_id, identity_sub, subscription_id, last_action, valid_until, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())')
                ->execute([$in['ref'], $storeId, $userId, $in['identitySub'], $subscriptionId, 'activate', $until]);
            $conn->commit();
        } catch (\Throwable $error) {
            if ($conn->inTransaction()) { $conn->rollBack(); }
            throw $error;
        }
        return $this->result($storeId, $userId, $created);
    }

    /** Conta de cliente sem senha, sem nenhum login e sem saldo: só sobrou de uma tentativa anterior. */
    private function untouchedCustomer(int $userId): bool
    {
        $stmt = $this->db->prepare('SELECT senha_hash IS NULL AS no_password, ultimo_login IS NULL AS never_logged FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || (int) $row['no_password'] !== 1 || (int) $row['never_logged'] !== 1) { return false; }
        $balance = $this->db->prepare('SELECT 1 FROM cashback_saldos WHERE usuario_id = ? LIMIT 1');
        $balance->execute([$userId]);
        return !$balance->fetchColumn();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function suspend(array $payload): array
    {
        $ref = $this->ref($payload['ref'] ?? null);
        $reason = trim((string) ($payload['reason'] ?? 'Assinatura suspensa pela loja'));
        self::ensureSchema($this->db);
        $existing = $this->external($ref);
        if ($existing === null) {
            return ['found' => false, 'suspended' => false];
        }
        $subscriptionId = $this->syncSubscription((int) $existing['store_id'], ['name' => '', 'code' => null], null, false, mb_substr($reason, 0, 200));
        $this->touch($ref, 'suspend', null, $subscriptionId);
        return ['found' => true, 'suspended' => true, 'store_id' => (int) $existing['store_id']];
    }

    /** Estado atual da assinatura da referência (só leitura). @return array<string, mixed> */
    public function status(string $ref): array
    {
        $ref = $this->ref($ref);
        self::ensureSchema($this->db);
        $row = $this->external($ref);
        if ($row === null) { return ['exists' => false]; }
        $pending = $this->db->prepare('SELECT 1 FROM loja_onboarding WHERE store_id = ? AND completed_at IS NULL');
        $pending->execute([(int) $row['store_id']]);
        return ['exists' => true, 'store_id' => (int) $row['store_id'], 'last_action' => (string) $row['last_action'], 'onboarding_pending' => (bool) $pending->fetchColumn()];
    }

    /** @return array<string, mixed> */
    private function result(int $storeId, int $userId, bool $created): array
    {
        $pending = $this->db->prepare('SELECT 1 FROM loja_onboarding WHERE store_id = ? AND completed_at IS NULL');
        $pending->execute([$storeId]);
        return ['store_id' => $storeId, 'user_id' => $userId, 'created' => $created, 'onboarding_pending' => (bool) $pending->fetchColumn()];
    }

    /** @return ?array<string, mixed> */
    private function external(string $ref): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM loja_assinatura_externa WHERE ref = ? LIMIT 1');
        $stmt->execute([$ref]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function touch(string $ref, string $action, ?string $until, int $subscriptionId): void
    {
        $this->db->prepare('UPDATE loja_assinatura_externa SET last_action = ?, valid_until = COALESCE(?, valid_until), subscription_id = ?, updated_at = NOW() WHERE ref = ?')
            ->execute([$action, $until, $subscriptionId > 0 ? $subscriptionId : null, $ref]);
    }

    private function createPendingUser(string $name, string $email, string $phone): int
    {
        // Mesma forma da criação do login único (cliente, ativo, sem senha): é o que o login único aceita ligar; só depois vira loja.
        $this->db->prepare("INSERT INTO usuarios (nome, email, telefone, provider, tipo, status, data_criacao) VALUES (?, ?, ?, 'local', 'cliente', 'ativo', NOW())")
            ->execute([mb_substr($name, 0, 100), $email, $phone !== '' ? mb_substr($phone, 0, 20) : null]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Cria ou atualiza a assinatura comercial da loja, controlada pela Loja do Grupo Klube (sem gateway do KlubeCash).
     * Ativa: vendas liberadas até a validade. Suspensa: só as vendas novas ficam bloqueadas.
     * @param array{name:string, code:?string} $plan
     */
    private function syncSubscription(int $storeId, array $plan, ?string $until, bool $active, ?string $reason): int
    {
        $row = $this->db->prepare("SELECT id FROM assinaturas WHERE loja_id = ? AND tipo = 'loja' ORDER BY id DESC LIMIT 1");
        $row->execute([$storeId]);
        $id = (int) ($row->fetchColumn() ?: 0);
        $blocked = $active ? 0 : 1;
        if ($id === 0) {
            if (!$active) { return 0; }
            $planId = $this->planId($plan);
            $this->db->prepare("INSERT INTO assinaturas (tipo, loja_id, plano_id, status, ciclo, current_period_start, current_period_end, next_invoice_date)
                VALUES ('loja', ?, ?, 'ativa', 'monthly', CURDATE(), ?, ?)")->execute([$storeId, $planId, $until, $until]);
            $id = (int) $this->db->lastInsertId();
        } else {
            $set = ['status = ?', 'updated_at = NOW()'];
            $params = [$active ? 'ativa' : 'suspensa'];
            if ($active && $until !== null) {
                $set[] = 'current_period_end = ?'; $params[] = $until;
                $set[] = 'next_invoice_date = ?'; $params[] = $until;
            }
            $this->db->prepare('UPDATE assinaturas SET ' . implode(', ', $set) . ' WHERE id = ?')->execute([...$params, $id]);
        }
        // Colunas opcionais (dependem da versão do banco): só mexe nas que existem.
        foreach ([['sales_blocked', $blocked], ['sales_block_reason', $active ? null : ($reason ?? 'Assinatura suspensa pela loja')], ['manual_activation_until', $active ? $until : null]] as [$column, $value]) {
            if ($this->hasColumn('assinaturas', $column)) {
                $this->db->prepare("UPDATE assinaturas SET {$column} = ? WHERE id = ?")->execute([$value, $id]);
            }
        }
        return $id;
    }

    /** @param array{name:string, code:?string} $plan */
    private function planId(array $plan): int
    {
        $stmt = $this->db->prepare('SELECT id FROM planos WHERE ativo = 1 AND ((? <> \'\' AND slug = ?) OR (? <> \'\' AND LOWER(nome) = LOWER(?))) ORDER BY id LIMIT 1');
        $code = (string) ($plan['code'] ?? '');
        $stmt->execute([$code, $code, $plan['name'], $plan['name']]);
        $id = (int) ($stmt->fetchColumn() ?: 0);
        if ($id === 0) {
            $id = (int) ($this->db->query('SELECT id FROM planos WHERE ativo = 1 ORDER BY preco_mensal, id LIMIT 1')->fetchColumn() ?: 0);
        }
        if ($id === 0) { throw new PartnerApiException('Nenhum plano ativo cadastrado no KlubeCash.', 503, 'no_plan'); }
        return $id;
    }

    private function hasColumn(string $table, string $column): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }

    private function ref(mixed $value): string
    {
        $ref = is_string($value) ? trim($value) : '';
        if (preg_match('/^[A-Za-z0-9_.:-]{8,80}$/D', $ref) !== 1) { throw new PartnerApiException('Referência da assinatura inválida.', 422, 'invalid_ref'); }
        return $ref;
    }

    /**
     * @param array<string, mixed> $p
     * @return array{ref:string, identitySub:string, name:string, email:string, business:string, doc:string, phone:string, plan:array{name:string, code:?string}, validUntil:?string}
     */
    private function validate(array $p): array
    {
        $sub = is_string($p['identitySub'] ?? null) ? strtolower(trim($p['identitySub'])) : '';
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $sub) !== 1) { throw new PartnerApiException('Identificador do login único inválido.', 422, 'invalid_identity'); }
        $customer = is_array($p['customer'] ?? null) ? $p['customer'] : [];
        $business = is_array($p['business'] ?? null) ? $p['business'] : [];
        $email = strtolower(trim((string) ($customer['email'] ?? '')));
        if ($email === '' || strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) { throw new PartnerApiException('E-mail do assinante inválido.', 422, 'invalid_email'); }
        $name = trim((string) ($customer['name'] ?? ''));
        $bname = trim((string) ($business['name'] ?? ''));
        $doc = preg_replace('/\D+/', '', (string) ($business['docNumber'] ?? '')) ?? '';
        if (mb_strlen($bname) < 2 || mb_strlen($bname) > 100) { throw new PartnerApiException('Nome do negócio inválido.', 422, 'invalid_business'); }
        if (!in_array(strlen($doc), [11, 14], true)) { throw new PartnerApiException('CPF/CNPJ inválido.', 422, 'invalid_doc'); }
        $phone = preg_replace('/\D+/', '', (string) ($business['phone'] ?? '')) ?? '';
        $plan = is_array($p['plan'] ?? null) ? $p['plan'] : [];
        $until = null;
        if (is_string($p['validUntil'] ?? null) && $p['validUntil'] !== '') {
            $time = strtotime($p['validUntil']);
            if ($time === false || $time < time() - 86400) { throw new PartnerApiException('Validade da assinatura inválida.', 422, 'invalid_valid_until'); }
            $until = date('Y-m-d', $time);
        }
        return [
            'ref' => $this->ref($p['ref'] ?? null), 'identitySub' => $sub, 'name' => $name !== '' ? $name : $bname, 'email' => $email,
            'business' => $bname, 'doc' => $doc, 'phone' => substr($phone, 0, 20) ?: '00000000000',
            'plan' => ['name' => trim((string) ($plan['name'] ?? '')), 'code' => is_string($plan['code'] ?? null) ? trim($plan['code']) : null],
            'validUntil' => $until,
        ];
    }
}
