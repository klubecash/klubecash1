<?php

declare(strict_types=1);

namespace App\Services\StoreWallet;

use PDO;

final class StoreWalletSchema
{
    public static function migrate(PDO $db, bool $apply = false): array
    {
        $approved = (int) $db->query("SELECT COUNT(*) FROM lojas WHERE status='aprovado'")->fetchColumn();
        $tables = ['store_wallet_links', 'store_wallet_challenges', 'store_wallet_claims', 'store_wallet_login_attempts', 'store_wallet_link_events'];
        if (!$apply) {
            return ['apply' => false, 'approvedStores' => $approved, 'tables' => $tables];
        }
        $db->exec('CREATE TABLE IF NOT EXISTS store_wallet_links (
            store_id INT NOT NULL PRIMARY KEY, token CHAR(48) NOT NULL UNIQUE,
            enabled TINYINT NOT NULL DEFAULT 1, version INT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            updated_by INT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $db->exec('CREATE TABLE IF NOT EXISTS store_wallet_challenges (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, store_id INT NOT NULL,
            session_hash CHAR(64) NOT NULL, purpose VARCHAR(24) NOT NULL,
            phone CHAR(13) NOT NULL, code_hash CHAR(64) NOT NULL,
            attempts TINYINT NOT NULL DEFAULT 0, expires_at DATETIME NOT NULL,
            sent_at DATETIME NOT NULL, verified_at DATETIME NULL,
            KEY idx_challenge_session(store_id,session_hash,purpose,id),
            KEY idx_challenge_phone(phone,sent_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $db->exec('CREATE TABLE IF NOT EXISTS store_wallet_claims (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, store_id INT NOT NULL,
            visitor_id INT NOT NULL, account_id INT NOT NULL,
            phone CHAR(13) NOT NULL, available_cents BIGINT NOT NULL,
            target_before_cents BIGINT NOT NULL, target_after_cents BIGINT NOT NULL,
            credits_moved INT NOT NULL, movements_moved INT NOT NULL, claimed_at DATETIME NOT NULL,
            challenge_id BIGINT UNSIGNED NOT NULL, UNIQUE KEY uk_claim_visitor(store_id,visitor_id),
            KEY idx_claim_account(account_id,store_id), KEY idx_claim_phone(store_id,phone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $db->exec('CREATE TABLE IF NOT EXISTS store_wallet_login_attempts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, fingerprint CHAR(64) NOT NULL,
            attempted_at DATETIME NOT NULL, KEY idx_login_limit(fingerprint,attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $db->exec('CREATE TABLE IF NOT EXISTS store_wallet_link_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, store_id INT NOT NULL,
            actor_id INT NOT NULL, action VARCHAR(16) NOT NULL,
            previous_token_hash CHAR(64) NOT NULL, new_token_hash CHAR(64) NOT NULL,
            previous_enabled TINYINT NOT NULL, new_enabled TINYINT NOT NULL,
            occurred_at DATETIME NOT NULL, KEY idx_link_events(store_id,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $ids = $db->query("SELECT id FROM lojas WHERE status='aprovado' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        $insert = $db->prepare('INSERT IGNORE INTO store_wallet_links(store_id,token,enabled,created_at,updated_at) VALUES(?,?,1,NOW(),NOW())');
        $exists = $db->prepare('SELECT 1 FROM store_wallet_links WHERE store_id=?');
        foreach ($ids as $id) {
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $insert->execute([(int) $id, bin2hex(random_bytes(24))]);
                $exists->execute([(int) $id]);
                if ($exists->fetchColumn()) { continue 2; }
            }
            throw new \RuntimeException('Não foi possível gerar link para a loja ' . $id);
        }
        return ['apply' => true, 'approvedStores' => $approved, 'tables' => $tables, 'links' => (int) $db->query('SELECT COUNT(*) FROM store_wallet_links')->fetchColumn()];
    }
}
