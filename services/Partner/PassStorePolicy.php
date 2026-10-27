<?php

declare(strict_types=1);

namespace App\Services\Partner;

use App\Services\Store\StoreMoney;
use PDO;
use PDOException;

require_once __DIR__ . '/../store/StoreMoney.php';

/**
 * How a store takes part in the Klube Pass. Works before the migration too:
 * without the columns every store is a regular one, so deploying code first is safe.
 */
final class PassStorePolicy
{
    /** @return array{promoter:bool,giftbackOnCash:bool} */
    public static function forStore(PDO $db, int $storeId, bool $lock = false): array
    {
        try {
            $stmt = $db->prepare('SELECT tipo_parceiro,giftback_sobre_dinheiro FROM lojas WHERE id=? LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
            $stmt->execute([$storeId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $error) {
            // 42S22: unknown column, i.e. the Pass migration is not applied yet.
            if ((string) $error->getCode() !== '42S22') { throw $error; }
            return ['promoter' => false, 'giftbackOnCash' => false];
        }
        $promoter = ($row['tipo_parceiro'] ?? '') === 'promotor_eventos';
        return ['promoter' => $promoter, 'giftbackOnCash' => $promoter && (int) ($row['giftback_sobre_dinheiro'] ?? 0) === 1];
    }

    /**
     * Giftback of a sale: a regular store needs cashback_ativo and pays on the paid
     * part; a promoter pays on the paid part only when giftback_sobre_dinheiro is on.
     */
    public static function saleGiftbackCents(array $policy, bool $cashbackActive, int $paidCents, int|float|string $percentage): int
    {
        if ($policy['promoter'] ? !$policy['giftbackOnCash'] : !$cashbackActive) { return 0; }
        return StoreMoney::percentage($paidCents, $percentage);
    }

    /** Whether the Pass columns exist on lojas (checked once per process). */
    public static function migrated(PDO $db): bool
    {
        static $has = null;
        if ($has === null) {
            $has = (bool) $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='lojas' AND COLUMN_NAME='tipo_parceiro'")->fetchColumn();
        }
        return $has;
    }

    /** SQL fragment that is true for stores outside the promoter model (reimbursement listings). */
    public static function notPromoterSql(PDO $db, string $storeIdExpression): string
    {
        return self::migrated($db) ? "NOT EXISTS (SELECT 1 FROM lojas pass_l WHERE pass_l.id={$storeIdExpression} AND pass_l.tipo_parceiro='promotor_eventos')" : '1=1';
    }
}
