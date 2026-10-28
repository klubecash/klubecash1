<?php

declare(strict_types=1);

namespace App\Services\Partner;

use PDO;
use RuntimeException;

/**
 * Explicit, additive migration for the Klube Pass integration. Every step checks
 * information_schema first, so running it again changes nothing. Never run DDL
 * from a request; the runner in database/migrations is the only entry point.
 */
final class PassIntegrationSchema
{
    public const MOVEMENT_TYPES = ['credito_sobra_evento', 'estorno_sobra_evento'];

    /** @return array{mode:string,database:string,changes:list<string>,legacyWildcardKeys:int} */
    public static function migrate(PDO $db, bool $apply = false, bool $deactivateLegacyWildcardKeys = false): array
    {
        if ($apply && $db->inTransaction()) { throw new RuntimeException('A migração requer conexão sem transação aberta.'); }
        $schema = (string) $db->query('SELECT DATABASE()')->fetchColumn();
        $table = static function (string $name) use ($db): bool {
            $stmt = $db->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
            $stmt->execute([$name]);
            return (bool) $stmt->fetchColumn();
        };
        $column = static function (string $tableName, string $name) use ($db): ?array {
            $stmt = $db->prepare('SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
            $stmt->execute([$tableName, $name]);
            $type = $stmt->fetchColumn();
            return $type === false ? null : ['type' => (string) $type];
        };
        $index = static function (string $tableName, string $name) use ($db): bool {
            $stmt = $db->prepare('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
            $stmt->execute([$tableName, $name]);
            return (bool) $stmt->fetchColumn();
        };
        foreach (['lojas', 'cashback_movimentacoes', 'cashback_creditos'] as $required) {
            if (!$table($required)) { throw new RuntimeException("Tabela {$required} ausente: aplique antes a migração de giftback."); }
        }

        $changes = [];
        $steps = [];
        $add = static function (string $label, string $sql) use (&$changes, &$steps): void { $changes[] = $label; $steps[] = $sql; };

        if ($column('lojas', 'tipo_parceiro') === null) {
            $add('lojas.tipo_parceiro', "ALTER TABLE lojas ADD COLUMN tipo_parceiro ENUM('comercio','promotor_eventos') NOT NULL DEFAULT 'comercio'");
        }
        if ($column('lojas', 'pass_organizer_id') === null) {
            $add('lojas.pass_organizer_id', 'ALTER TABLE lojas ADD COLUMN pass_organizer_id VARCHAR(36) NULL DEFAULT NULL');
        }
        if (!$index('lojas', 'uk_lojas_pass_organizer')) {
            $add('lojas.uk_lojas_pass_organizer', 'ALTER TABLE lojas ADD UNIQUE KEY uk_lojas_pass_organizer (pass_organizer_id)');
        }
        if ($column('lojas', 'giftback_sobre_dinheiro') === null) {
            $add('lojas.giftback_sobre_dinheiro', 'ALTER TABLE lojas ADD COLUMN giftback_sobre_dinheiro TINYINT(1) NOT NULL DEFAULT 0');
        }

        // The enum is read back so values added by other migrations are kept.
        $movementType = $column('cashback_movimentacoes', 'tipo_operacao');
        if ($movementType === null) { throw new RuntimeException('cashback_movimentacoes.tipo_operacao ausente.'); }
        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $movementType['type'], $matches);
        $values = $matches[1];
        $missing = array_values(array_diff(self::MOVEMENT_TYPES, $values));
        if ($missing !== []) {
            $all = array_merge($values, $missing);
            $add('cashback_movimentacoes.tipo_operacao', 'ALTER TABLE cashback_movimentacoes MODIFY tipo_operacao ENUM('
                . implode(',', array_map(static fn (string $value): string => $db->quote($value), $all)) . ') NOT NULL');
        }
        if ($column('cashback_movimentacoes', 'origem') === null) {
            $add('cashback_movimentacoes.origem', "ALTER TABLE cashback_movimentacoes ADD COLUMN origem VARCHAR(20) NOT NULL DEFAULT 'klubecash'");
        }
        if ($column('cashback_movimentacoes', 'pass_ref') === null) {
            $add('cashback_movimentacoes.pass_ref', 'ALTER TABLE cashback_movimentacoes ADD COLUMN pass_ref VARCHAR(100) NULL DEFAULT NULL');
        }
        if (!$index('cashback_movimentacoes', 'uk_movimentacoes_pass_ref')) {
            $add('cashback_movimentacoes.uk_movimentacoes_pass_ref', 'ALTER TABLE cashback_movimentacoes ADD UNIQUE KEY uk_movimentacoes_pass_ref (pass_ref)');
        }

        $tables = [
            'partner_api_keys' => "CREATE TABLE partner_api_keys (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(16) NOT NULL, secret_hash CHAR(64) NOT NULL,
                partner_name VARCHAR(120) NOT NULL, permissions JSON NOT NULL,
                daily_request_limit INT UNSIGNED NOT NULL DEFAULT 20000, daily_debit_limit_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
                daily_credit_limit_cents BIGINT UNSIGNED NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1,
                expires_at DATETIME NULL, last_used_at DATETIME NULL, created_by VARCHAR(120) NULL,
                created_at DATETIME NOT NULL, revoked_at DATETIME NULL,
                UNIQUE KEY uk_partner_api_keys_public (public_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'partner_idempotency_keys' => "CREATE TABLE partner_idempotency_keys (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, api_key_id INT UNSIGNED NOT NULL, endpoint VARCHAR(60) NOT NULL,
                idempotency_key VARCHAR(128) NOT NULL, request_hash CHAR(64) NOT NULL,
                status ENUM('processing','completed','failed') NOT NULL DEFAULT 'processing', http_status SMALLINT UNSIGNED NULL,
                response_json LONGTEXT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, expires_at DATETIME NOT NULL,
                UNIQUE KEY uk_partner_idempotency (api_key_id,endpoint,idempotency_key), KEY idx_partner_idempotency_expiry (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'partner_api_usage_daily' => "CREATE TABLE partner_api_usage_daily (
                api_key_id INT UNSIGNED NOT NULL, usage_date DATE NOT NULL, requests INT UNSIGNED NOT NULL DEFAULT 0,
                debit_cents BIGINT UNSIGNED NOT NULL DEFAULT 0, credit_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (api_key_id,usage_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'pass_user_daily_usage' => "CREATE TABLE pass_user_daily_usage (
                usuario_id INT NOT NULL, usage_date DATE NOT NULL, debit_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (usuario_id,usage_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'pass_integration_log' => "CREATE TABLE pass_integration_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, request_id VARCHAR(64) NOT NULL, api_key_id INT UNSIGNED NULL,
                endpoint VARCHAR(60) NOT NULL, method VARCHAR(8) NOT NULL, http_status SMALLINT UNSIGNED NOT NULL,
                error_code VARCHAR(60) NULL, idempotency_hash CHAR(64) NULL, usuario_id INT NULL, loja_id INT NULL,
                amount_cents BIGINT NULL, items SMALLINT UNSIGNED NULL, pass_ref VARCHAR(100) NULL, replayed TINYINT(1) NOT NULL DEFAULT 0,
                ip_hash CHAR(64) NULL, duration_ms INT UNSIGNED NULL, created_at DATETIME NOT NULL,
                KEY idx_pass_log_key_date (api_key_id,created_at), KEY idx_pass_log_ref (pass_ref)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'pass_credit_notices' => "CREATE TABLE pass_credit_notices (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, movement_id INT NOT NULL, usuario_id INT NOT NULL, loja_id INT NOT NULL,
                channel ENUM('whatsapp','email') NOT NULL, amount_cents BIGINT NOT NULL, expires_at DATETIME NULL,
                status ENUM('pending','processing','sent','skipped','failed','delivery_unknown') NOT NULL DEFAULT 'pending',
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0, scheduled_for DATETIME NOT NULL, provider_message_id VARCHAR(191) NULL,
                last_error_code VARCHAR(64) NULL, sent_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
                UNIQUE KEY uk_pass_notice (movement_id,channel), KEY idx_pass_notice_queue (status,scheduled_for)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];
        foreach ($tables as $name => $sql) {
            if (!$table($name)) { $add($name, $sql); }
        }

        $legacy = 0;
        if ($table('api_keys')) {
            $legacy = (int) $db->query("SELECT COUNT(*) FROM api_keys WHERE is_active=1 AND permissions LIKE '%\"*\"%'")->fetchColumn();
        }

        if ($apply) {
            foreach ($steps as $sql) { $db->exec($sql); }
            if ($deactivateLegacyWildcardKeys && $legacy > 0) {
                $db->exec("UPDATE api_keys SET is_active=0 WHERE is_active=1 AND permissions LIKE '%\"*\"%'");
                $changes[] = 'api_keys.deactivate_wildcard';
            }
        }

        return ['mode' => $apply ? 'applied' : 'dry-run', 'database' => $schema, 'changes' => $changes, 'legacyWildcardKeys' => $legacy];
    }
}
