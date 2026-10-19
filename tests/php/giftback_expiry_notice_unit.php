<?php

declare(strict_types=1);

// Teste puro: marcos, agenda e texto dos avisos. Nao abre banco nem a aplicacao.
require_once dirname(__DIR__, 2) . '/services/Giftback/GiftbackExpiryNoticeService.php';

use App\Services\Giftback\GiftbackExpiryNoticeService as Notice;

date_default_timezone_set('America/Sao_Paulo');

function noticeExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

// Marcos por validade (somente ate metade do prazo).
$expected = [null => [], 0 => [], 1 => [], 2 => [1], 3 => [1], 7 => [1], 13 => [1], 14 => [7, 1], 27 => [7, 1], 28 => [14, 7, 1], 39 => [14, 7, 1], 40 => [20, 14, 7, 1], 365 => [20, 14, 7, 1]];
foreach ($expected as $days => $marcos) {
    noticeExpect(Notice::milestones($days === '' ? null : (int) $days) === $marcos, "Marcos errados para {$days} dias");
}
noticeExpect(Notice::milestones(null) === [], 'Sem validade nao recebe aviso');

$zone = new DateTimeZone('America/Sao_Paulo');
$now = new DateTimeImmutable('2026-10-18 08:00:00', $zone);

// Credito novo de 40 dias: liberado em 18/10, vence (limite exclusivo) em 27/11 00:00, ultimo dia 26/11.
$plan = Notice::plan(40, '2026-11-27 00:00:00', $now);
$dates = array_column($plan, 'scheduledFor', 'marco');
noticeExpect(array_column($plan, 'marco') === [20, 14, 7, 1], 'Credito novo deve ter 4 avisos');
noticeExpect($dates[20] === '2026-11-06 10:00:00', '20 dias antes do ultimo dia (26/11) e 06/11: ' . $dates[20]);
noticeExpect($dates[14] === '2026-11-12 10:00:00' && $dates[7] === '2026-11-19 10:00:00' && $dates[1] === '2026-11-25 10:00:00', 'Datas dos marcos');
noticeExpect(array_unique(array_column($plan, 'status')) === ['pending'], 'Todos pendentes quando nenhum passou');

// Backfill: faltam 10 dias (ultimo dia 28/10). Marcos 20 e 14 passaram; 7 e 1 estao no futuro.
$plan = Notice::plan(40, '2026-10-29 00:00:00', $now);
$status = array_column($plan, 'status', 'marco');
noticeExpect($status[20] === 'skipped' && $status[14] === 'pending', 'Entre os marcos passados so o mais proximo (14) e enviado');
noticeExpect($status[7] === 'pending' && $status[1] === 'pending', 'Marcos futuros ficam pendentes');
$sentNow = array_column($plan, 'scheduledFor', 'marco');
noticeExpect($sentNow[14] === '2026-10-18 08:00:00', 'Marco atrasado sai na rodada atual');

// Ultimo dia: faltam menos de 1 dia, todos passados, so o aviso de 1 dia sai.
$plan = Notice::plan(40, '2026-10-19 00:00:00', $now);
$status = array_column($plan, 'status', 'marco');
noticeExpect($status === [20 => 'skipped', 14 => 'skipped', 7 => 'skipped', 1 => 'pending'], 'Apenas o aviso mais proximo no ultimo dia');

// Credito ja vencido ou sem validade util nao gera agenda.
noticeExpect(Notice::plan(40, '2026-10-18 00:00:00', $now) === [], 'Vencido nao gera aviso');
noticeExpect(Notice::plan(1, '2026-10-20 00:00:00', $now) === [], 'Validade de 1 dia nao gera aviso');

// Textos.
$one = Notice::message('Ana Maria Souza', [['storeName' => 'Loja X', 'cents' => 1250, 'validUntil' => '25/10/2026', 'daysLeft' => 7]]);
noticeExpect(str_contains($one, 'Ola, Ana!') && str_contains($one, '*Loja X*') && str_contains($one, 'R$ 12,50') && str_contains($one, 'vence em *7 dias*') && str_contains($one, '25/10/2026'), 'Mensagem de uma loja');
noticeExpect(str_contains(Notice::message('', [['storeName' => 'L', 'cents' => 100, 'validUntil' => '19/10/2026', 'daysLeft' => 1]]), 'vence *amanha*'), 'Texto de 1 dia');
noticeExpect(str_contains(Notice::message('Ana', [['storeName' => 'L', 'cents' => 100, 'validUntil' => '18/10/2026', 'daysLeft' => 0]]), 'vence *hoje*'), 'Texto de hoje');
$many = [];
for ($i = 1; $i <= 7; $i++) {
    $many[] = ['storeName' => "Loja {$i}", 'cents' => $i * 100, 'validUntil' => '25/10/2026', 'daysLeft' => 7];
}
$text = Notice::message('Bia', $many);
noticeExpect(str_contains($text, 'Loja 5') && !str_contains($text, 'Loja 6') && str_contains($text, 'e mais 2 loja(s)'), 'Lista longa e resumida');

echo "giftback_expiry_notice_unit: ok\n";
