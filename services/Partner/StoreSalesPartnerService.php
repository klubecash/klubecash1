<?php

declare(strict_types=1);

namespace App\Services\Partner;

use App\Services\Store\StoreApiException;
use App\Services\Store\StoreCustomerService;
use App\Services\Store\StoreTransactionService;
use PDO;

require_once __DIR__ . '/PartnerApiException.php';
require_once __DIR__ . '/../store/StoreApiException.php';
require_once __DIR__ . '/../store/StoreCustomerService.php';
require_once __DIR__ . '/../store/StoreTransactionService.php';

/**
 * Vendas de parceiros (Klube Ops) em lojas KlubeCash: buscar cliente e registrar a venda que gera cashback.
 * Cada chave só vende nas lojas liberadas em PARTNER_SALES_STORES ("idDaChave:loja|loja,outraChave:loja").
 * A venda é registrada em nome do titular da loja e passa pelas mesmas regras do painel do lojista.
 */
final class StoreSalesPartnerService
{
    public function __construct(private PDO $db) {}

    /** @return array<int, list<int>> idDaChave => lojas */
    public static function parseBindings(string $raw): array
    {
        $out = [];
        foreach (explode(',', $raw) as $part) {
            $part = trim($part);
            if (!preg_match('/^(\d{1,9}):(\d{1,9}(?:\|\d{1,9})*)$/', $part, $m)) { continue; }
            $stores = array_values(array_unique(array_map('intval', explode('|', $m[2]))));
            $out[(int) $m[1]] = array_values(array_unique([...($out[(int) $m[1]] ?? []), ...$stores]));
        }
        return $out;
    }

    /** @param array{id:int} $key */
    public static function assertStoreAllowed(array $key, int $storeId, ?string $raw = null): void
    {
        $bindings = self::parseBindings($raw ?? (string) (getenv('PARTNER_SALES_STORES') ?: ''));
        if ($storeId <= 0 || !in_array($storeId, $bindings[$key['id']] ?? [], true)) {
            throw new PartnerApiException('Loja não liberada para esta chave.', 403, 'store_forbidden');
        }
    }

    /** Primeiro nome e telefone mascarado: o caixa confirma a pessoa sem ver dados completos. */
    public static function publicCustomer(array $c): array
    {
        $name = trim((string) ($c['name'] ?? ''));
        $phone = preg_replace('/\D+/', '', (string) ($c['phone'] ?? ''));
        return [
            'customerId' => (int) ($c['id'] ?? 0),
            'firstName' => $name === '' ? '' : explode(' ', $name)[0],
            'phoneEnd' => $phone === '' ? null : substr($phone, -4),
            'balanceCents' => (int) ($c['balanceCents'] ?? 0),
            'requiresPhoneVerification' => (bool) ($c['requiresPhoneVerification'] ?? false),
        ];
    }

    private function ownerOf(int $storeId): int
    {
        $stmt = $this->db->prepare("SELECT usuario_id FROM lojas WHERE id=? AND status='aprovado' LIMIT 1");
        $stmt->execute([$storeId]);
        $owner = (int) $stmt->fetchColumn();
        if ($owner <= 0) { throw new PartnerApiException('Loja não encontrada ou não aprovada.', 422, 'store_unavailable'); }
        return $owner;
    }

    /** @param array{id:int} $key */
    public function lookup(array $key, array $query): array
    {
        $storeId = (int) ($query['store_id'] ?? 0);
        self::assertStoreAllowed($key, $storeId);
        $term = trim((string) ($query['q'] ?? ''));
        try {
            $rows = (new StoreCustomerService($this->db))->search($storeId, $term);
        } catch (StoreApiException $error) {
            throw new PartnerApiException($error->getMessage(), $error->httpStatus, 'invalid_query');
        }
        $customer = is_array($rows['customer'] ?? null) ? $rows['customer'] : null;
        return ['customer' => $customer ? self::publicCustomer($customer) : null];
    }

    /** @param array{id:int} $key */
    public function createSale(array $key, array $payload, string $idempotencyKey): array
    {
        $storeId = (int) ($payload['store_id'] ?? 0);
        self::assertStoreAllowed($key, $storeId);
        if (!preg_match('/^[A-Za-z0-9_-]{8,100}$/', $idempotencyKey)) {
            throw new PartnerApiException('Idempotency-Key obrigatória (8 a 100 caracteres).', 400, 'idempotency_key_required');
        }
        $items = [];
        foreach ((array) ($payload['items'] ?? []) as $item) {
            if (!is_array($item)) { continue; }
            $items[] = ['name' => (string) ($item['name'] ?? ''), 'quantity' => $item['quantity'] ?? null, 'unitPriceCents' => $item['unit_price_cents'] ?? null];
        }
        $input = [
            'customerId' => (int) ($payload['customer_id'] ?? 0),
            'grossAmountCents' => (int) ($payload['gross_amount_cents'] ?? 0),
            'balanceUsedCents' => (int) ($payload['balance_used_cents'] ?? 0),
            'code' => (string) ($payload['code'] ?? ''),
            'description' => mb_substr('Klube Ops ' . (string) ($payload['description'] ?? ''), 0, 500),
            'items' => $items,
        ];
        try {
            $result = (new StoreTransactionService($this->db))->create($storeId, $this->ownerOf($storeId), $input, 'ops-' . $idempotencyKey, 'manual');
        } catch (StoreApiException $error) {
            throw new PartnerApiException($error->getMessage(), $error->httpStatus, 'sale_rejected');
        }
        return $result;
    }
}
