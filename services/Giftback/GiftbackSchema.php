<?php

declare(strict_types=1);

namespace App\Services\Giftback;

use PDO;
use RuntimeException;

/** Explicit, additive migration. Never run DDL from a customer request. */
final class GiftbackSchema
{
    public static function migrate(PDO $db, bool $apply = false): array
    {
        $tables = [
            'giftback_rollout' => "CREATE TABLE IF NOT EXISTS giftback_rollout (id TINYINT PRIMARY KEY, ready TINYINT NOT NULL DEFAULT 0, activated_at DATETIME NULL) ENGINE=InnoDB",
            'cashback_credito_carteiras' => "CREATE TABLE IF NOT EXISTS cashback_credito_carteiras (
                usuario_id INT NOT NULL, loja_id INT NOT NULL, cutoff_movement_id BIGINT NOT NULL,
                initialized_at DATETIME NOT NULL, PRIMARY KEY(usuario_id,loja_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'cashback_creditos' => "CREATE TABLE IF NOT EXISTS cashback_creditos (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, usuario_id INT NOT NULL, loja_id INT NOT NULL,
                source_key VARCHAR(191) NOT NULL, transacao_id INT NULL, origin_movement_id INT NULL,
                kind VARCHAR(30) NOT NULL, original_cents BIGINT NOT NULL, remaining_cents BIGINT NOT NULL,
                consumed_cents BIGINT NOT NULL DEFAULT 0, expired_cents BIGINT NOT NULL DEFAULT 0,
                revoked_cents BIGINT NOT NULL DEFAULT 0, credited_at DATETIME NOT NULL, expires_at DATETIME NULL,
                policy_days INT NULL, version INT NOT NULL DEFAULT 1,
                UNIQUE KEY uk_giftback_source(usuario_id,loja_id,source_key),
                KEY idx_giftback_expiry(expires_at,remaining_cents),
                KEY idx_giftback_wallet(usuario_id,loja_id,id), KEY idx_giftback_store(loja_id,usuario_id,id),
                KEY idx_giftback_transaction(transacao_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'cashback_credito_alocacoes' => "CREATE TABLE IF NOT EXISTS cashback_credito_alocacoes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, movement_id INT NOT NULL,
                credit_id BIGINT UNSIGNED NOT NULL, amount_cents BIGINT NOT NULL,
                refunded_cents BIGINT NOT NULL DEFAULT 0,
                UNIQUE KEY uk_giftback_allocation(movement_id,credit_id), KEY idx_allocation_credit(credit_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'cashback_credito_eventos' => "CREATE TABLE IF NOT EXISTS cashback_credito_eventos (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, credit_id BIGINT UNSIGNED NOT NULL,
                type VARCHAR(32) NOT NULL, amount_cents BIGINT NOT NULL, previous_cents BIGINT NOT NULL,
                current_cents BIGINT NOT NULL, old_expires_at DATETIME NULL, new_expires_at DATETIME NULL,
                actor_id INT NULL, actor_name VARCHAR(255) NULL, reason TEXT NOT NULL,
                movement_id INT NULL, related_event_id BIGINT UNSIGNED NULL, occurred_at DATETIME NOT NULL,
                KEY idx_giftback_events(credit_id,id),
                UNIQUE KEY uk_giftback_reversal(type,related_event_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'cashback_credito_operacoes' => "CREATE TABLE IF NOT EXISTS cashback_credito_operacoes (
                operation_key VARCHAR(191) PRIMARY KEY, request_hash CHAR(64) NOT NULL,
                response_json LONGTEXT NOT NULL, created_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];
        $column = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $column->execute(['lojas', 'giftback_expiration_days']);
        $addDays = !(int) $column->fetchColumn();
        $counts = $db->query('SELECT COUNT(*) wallets, COALESCE(SUM(saldo_disponivel),0) balance FROM cashback_saldos')->fetch(PDO::FETCH_ASSOC);
        $result = ['apply' => $apply, 'wallets' => (int) $counts['wallets'], 'balance' => $counts['balance'], 'tables' => array_keys($tables), 'addStorePolicy' => $addDays];
        if (!$apply) { return $result; }
        if ($db->inTransaction()) { throw new RuntimeException('A migração requer conexão sem transação aberta.'); }
        if ($addDays) { $db->exec('ALTER TABLE lojas ADD COLUMN giftback_expiration_days INT NULL DEFAULT NULL'); }
        foreach ($tables as $sql) { $db->exec($sql); }
        // Expanding the enum retains every historical movement and its original type.
        $db->exec("ALTER TABLE cashback_movimentacoes MODIFY tipo_operacao ENUM('credito','uso','estorno','expiracao','reversao_expiracao','revogacao') NOT NULL");
        $db->exec('INSERT IGNORE INTO giftback_rollout(id,ready) VALUES(1,0)');
        $db->beginTransaction();
        try {
            $wallets = $db->query('SELECT * FROM cashback_saldos ORDER BY usuario_id,loja_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($wallets as $wallet) {
                $user = (int) $wallet['usuario_id']; $store = (int) $wallet['loja_id'];
                $check = $db->prepare('SELECT 1 FROM cashback_credito_carteiras WHERE usuario_id=? AND loja_id=?');
                $check->execute([$user, $store]);
                if ($check->fetchColumn()) { continue; }
                $cents = (int) round((float) $wallet['saldo_disponivel'] * 100);
                if ($cents < 0) { throw new RuntimeException("Carteira {$user}/{$store} negativa: reconciliação manual necessária."); }
                $cutoff = $db->prepare('SELECT COALESCE(MAX(id),0) FROM cashback_movimentacoes WHERE usuario_id=? AND loja_id=?');
                $cutoff->execute([$user, $store]);
                $db->prepare('INSERT INTO cashback_credito_carteiras VALUES(?,?,?,NOW())')->execute([$user, $store, (int) $cutoff->fetchColumn()]);
                // A zero opening is intentional: the marker must survive a later deposit.
                $db->prepare("INSERT INTO cashback_creditos(usuario_id,loja_id,source_key,kind,original_cents,remaining_cents,credited_at) VALUES(?,?,'opening','opening',?,?,NOW())")->execute([$user, $store, $cents, $cents]);
                $creditId = (int) $db->lastInsertId();
                $db->prepare("INSERT INTO cashback_credito_eventos(credit_id,type,amount_cents,previous_cents,current_cents,reason,occurred_at) VALUES(?,'abertura',?,?,?,?,NOW())")->execute([$creditId, $cents, $cents, $cents, 'Saldo anterior à ativação do controle por crédito; sem vencimento.']);
            }
            self::assertReconciled($db);
            $db->exec('UPDATE giftback_rollout SET ready=1,activated_at=COALESCE(activated_at,NOW()) WHERE id=1');
            $db->commit();
        } catch (\Throwable $error) {
            if ($db->inTransaction()) { $db->rollBack(); }
            throw $error;
        }
        return $result + ['ready' => true];
    }

    public static function assertReconciled(PDO $db): void
    {
        $bad = $db->query('SELECT COUNT(*) FROM cashback_saldos s LEFT JOIN (SELECT usuario_id,loja_id,SUM(remaining_cents) cents FROM cashback_creditos GROUP BY usuario_id,loja_id) c ON c.usuario_id=s.usuario_id AND c.loja_id=s.loja_id WHERE ROUND(s.saldo_disponivel*100)<>COALESCE(c.cents,0)')->fetchColumn();
        $invalid = $db->query('SELECT COUNT(*) FROM cashback_creditos WHERE original_cents<>remaining_cents+consumed_cents+expired_cents+revoked_cents OR LEAST(original_cents,remaining_cents,consumed_cents,expired_cents,revoked_cents)<0')->fetchColumn();
        if ((int) $bad || (int) $invalid) { throw new RuntimeException('Reconciliação de giftback falhou; nenhum ajuste automático de saldo foi realizado.'); }
    }
}
