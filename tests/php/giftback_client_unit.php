<?php

declare(strict_types=1);

// Pure projection/render test. Does not bootstrap the application or open a database connection.
require_once dirname(__DIR__, 2) . '/services/Giftback/GiftbackClientReadService.php';

use App\Services\Giftback\GiftbackClientReadService;

date_default_timezone_set('America/Sao_Paulo');

function giftbackClientExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$credit = [
    'id' => 15, 'userId' => 80, 'customerName' => 'Dado privado', 'storeId' => 4,
    'storeName' => '<script>injected()</script>', 'originalCents' => 10000, 'remainingCents' => 0,
    'consumedCents' => 3000, 'expiredCents' => 7000, 'revokedCents' => 0,
    'creditedAt' => '2026-01-20T12:00:00-03:00', 'validUntil' => '2026-02-19',
    'expiresAt' => '2026-02-20T00:00:00-03:00', 'status' => 'expirado', 'kind' => 'credito',
    'reason' => 'MOTIVO_INTERNO', 'events' => [
        ['id' => 1, 'type' => 'expiracao', 'amountCents' => 7000, 'previousCents' => 7000, 'currentCents' => 0,
            'oldValidUntil' => '2026-02-19', 'newValidUntil' => '2026-02-19', 'occurredAt' => '2026-02-20T00:00:00-03:00',
            'actorId' => 123, 'actorName' => 'ADMIN_PRIVADO', 'reason' => 'MOTIVO_INTERNO', 'reversibleCents' => 7000],
        ['id' => 2, 'type' => 'reversao_expiracao', 'amountCents' => 7000, 'previousCents' => 0, 'currentCents' => 7000,
            'oldValidUntil' => '2026-02-19', 'newValidUntil' => '2026-03-20', 'occurredAt' => '2026-02-21T10:00:00-03:00'],
        ['id' => 3, 'type' => 'prorrogacao', 'amountCents' => 7000, 'previousCents' => 7000, 'currentCents' => 7000,
            'oldValidUntil' => '2026-03-20', 'newValidUntil' => '2026-04-20', 'occurredAt' => '2026-02-21T10:01:00-03:00'],
    ],
];
$public = GiftbackClientReadService::publicCredit($credit);
$json = json_encode($public, JSON_THROW_ON_ERROR);
foreach (['actorId', 'actorName', 'reason', 'customerName', 'userId', 'reversibleCents', 'MOTIVO_INTERNO', 'ADMIN_PRIVADO'] as $privateField) {
    giftbackClientExpect(!str_contains($json, $privateField), 'Public projection leaked ' . $privateField);
}
giftbackClientExpect($public['events'][0]['deltaCents'] === -7000, 'Expiry must subtract only remaining credit');
giftbackClientExpect($public['events'][1]['deltaCents'] === 7000, 'Reversal must appear as a positive restoration');
giftbackClientExpect($public['events'][2]['deltaCents'] === 0, 'Extension must not appear as money earned');

$_GET = ['loja_id' => '4'];
$giftbackCredits = ['items' => [$public], 'total' => 21, 'page' => 1, 'pageSize' => 20];
ob_start();
require dirname(__DIR__, 2) . '/views/components/giftback-credits.php';
$html = (string) ob_get_clean();
giftbackClientExpect(str_contains($html, 'Crédito #15'), 'Zero-balance credit history must stay visible');
giftbackClientExpect(str_contains($html, 'Giftback expirado') && str_contains($html, 'Expiração revertida') && str_contains($html, 'Validade prorrogada'), 'History labels missing');
giftbackClientExpect(str_contains($html, '19/02/2026') && str_contains($html, 'horário de Brasília'), 'Validity boundary must be explicit and local');
giftbackClientExpect(str_contains($html, 'Sem alteração de saldo'), 'Extension amount must be neutral');
giftbackClientExpect(!str_contains($html, '<script>injected()'), 'Store name must be HTML escaped');
giftbackClientExpect(str_contains($html, 'loja_id=4&amp;giftback_page=2'), 'Pagination must preserve the selected store');
giftbackClientExpect(!str_contains($html, 'ADMIN_PRIVADO') && !str_contains($html, 'MOTIVO_INTERNO'), 'Private audit fields leaked into HTML');

$giftbackExport = true;
ob_start();
require dirname(__DIR__, 2) . '/views/components/giftback-credits.php';
$export = (string) ob_get_clean();
giftbackClientExpect(str_contains($export, '<details class="giftback-credit" open>'), 'Export must show expanded history');
giftbackClientExpect(!str_contains($export, '<nav class="giftback-pagination"'), 'Export must not include unusable pagination');

echo "Giftback customer projection and rendering: OK\n";
