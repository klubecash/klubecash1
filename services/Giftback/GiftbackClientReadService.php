<?php

declare(strict_types=1);

namespace App\Services\Giftback;

use PDO;
use App\Services\Store\StoreNetworkAccess;

require_once __DIR__ . '/GiftbackLedger.php';
require_once __DIR__ . '/../store/StoreNetworkAccess.php';

/** Public customer projection: administrative actors and reasons never leave this boundary. */
final class GiftbackClientReadService
{
    private GiftbackLedger $ledger;
    private StoreNetworkAccess $access;

    public function __construct(PDO $db)
    {
        $this->ledger = new GiftbackLedger($db);
        $this->access = new StoreNetworkAccess($db);
    }

    public function credits(int $userId, ?int $storeId = null, int $page = 1, int $pageSize = 20): array
    {
        $result = $storeId === null
            ? $this->ledger->listCredits($userId, null, [], max(1, $page), max(1, min(100, $pageSize)))
            : $this->ledger->listCreditsForStores($userId, $this->access->walletStores($storeId), max(1, $page), max(1, min(100, $pageSize)));
        return [
            'items' => array_map([self::class, 'publicCredit'], $result['items']),
            'total' => (int) $result['total'], 'page' => (int) $result['page'], 'pageSize' => (int) $result['pageSize'],
        ];
    }

    public function detail(int $creditId, int $userId): array
    {
        return self::publicCredit($this->ledger->creditDetail($creditId, $userId));
    }

    public function wallet(int $userId, int $storeId): array
    {
        $stores = $this->access->walletStores($storeId);
        $wallet = $this->ledger->networkWallet($userId, $stores);
        return [
            'availableCents' => (int) $wallet['availableCents'],
            'nextExpirationDate' => $wallet['nextExpirationDate'] ?? null,
            'nextExpirationCents' => (int) ($wallet['nextExpirationCents'] ?? 0),
            'credits' => array_map([self::class, 'publicCredit'], $wallet['credits'] ?? []),
            'scope' => count($stores) > 1 ? 'network' : 'store',
            'storeIds' => $stores,
        ];
    }

    public static function publicCredit(array $credit): array
    {
        $fields = ['id', 'storeId', 'storeName', 'originalCents', 'remainingCents', 'consumedCents',
            'expiredCents', 'revokedCents', 'creditedAt', 'validUntil', 'expiresAt', 'kind', 'status'];
        $public = array_intersect_key($credit, array_flip($fields));
        $public['events'] = array_map(static function (array $event): array {
            $safe = array_intersect_key($event, array_flip(['id', 'type', 'amountCents', 'previousCents',
                'currentCents', 'oldValidUntil', 'newValidUntil', 'occurredAt']));
            $safe['label'] = self::eventLabel((string) ($event['type'] ?? ''));
            $safe['deltaCents'] = (int) ($event['currentCents'] ?? 0) - (int) ($event['previousCents'] ?? 0);
            return $safe;
        }, $credit['events'] ?? []);
        return $public;
    }

    public static function eventLabel(string $type): string
    {
        return [
            'credito' => 'Giftback recebido', 'abertura' => 'Saldo anterior registrado',
            'uso' => 'Saldo utilizado', 'estorno' => 'Estorno de saldo',
            'expiracao' => 'Giftback expirado', 'prorrogacao' => 'Validade prorrogada',
            'reversao_expiracao' => 'Expiração revertida', 'revogacao' => 'Crédito cancelado',
        ][$type] ?? 'Atualização de saldo';
    }

    public static function publicMovement(array $movement): array
    {
        $safe = array_intersect_key($movement, array_flip(['id', 'loja_id', 'redemption_store_id', 'tipo_operacao', 'valor',
            'saldo_anterior', 'saldo_atual', 'data_operacao', 'transacao_origem_id', 'transacao_uso_id',
            'transacao_origem_codigo', 'transacao_origem_valor', 'transacao_origem_data',
            'transacao_uso_codigo', 'transacao_uso_valor', 'transacao_uso_data']));
        $safe['descricao'] = self::eventLabel((string) ($movement['tipo_operacao'] ?? ''));
        return $safe;
    }
}
