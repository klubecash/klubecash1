<?php
/** @var array $giftbackCredits Public projection from GiftbackClientReadService. */
$giftbackEscape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$giftbackMoney = static fn ($cents): string => 'R$ ' . number_format(((int) $cents) / 100, 2, ',', '.');
$giftbackDate = static fn ($date): string => $date ? date('d/m/Y', strtotime((string) $date)) : 'Sem vencimento';
$giftbackStatuses = ['ativo' => 'Disponível', 'active' => 'Disponível', 'expirado' => 'Expirado', 'expired' => 'Expirado',
    'consumido' => 'Utilizado', 'consumed' => 'Utilizado', 'exhausted' => 'Sem saldo', 'revogado' => 'Cancelado', 'revoked' => 'Cancelado', 'esgotado' => 'Sem saldo'];
?>
<style>
.giftback-credits{margin:24px 0;padding:24px;background:var(--card-bg,#fff);color:var(--text-primary,#273342);border:1px solid var(--border-color,#e5e7eb);border-radius:16px}.giftback-credits h2{font-size:1.25rem;margin:0 0 8px}.giftback-credits p{margin:8px 0;line-height:1.5}.giftback-credit{padding:16px 0;border-top:1px solid #e5e7eb}.giftback-credit summary{cursor:pointer;display:flex;flex-wrap:wrap;gap:8px 20px;align-items:center;list-style:revert}.giftback-credit summary strong{flex:1;min-width:150px}.giftback-credit dl{display:grid;grid-template-columns:repeat(auto-fit,minmax(135px,1fr));gap:12px;margin:16px 0}.giftback-credit dt{font-size:.85rem;color:#667085}.giftback-credit dd{margin:4px 0;font-weight:600}.giftback-events{list-style:none;padding:0;margin:12px 0}.giftback-events li{display:flex;flex-wrap:wrap;justify-content:space-between;gap:6px 16px;padding:10px 0;border-top:1px solid #eee}.giftback-events small{display:block;color:#667085}.giftback-credit .negative{color:#b42318}.giftback-credit .positive{color:#067647}.giftback-pagination{display:flex;gap:18px;align-items:center;margin-top:18px;flex-wrap:wrap}.giftback-expiry{color:#9a4d00;font-size:.9rem;line-height:1.4;margin-top:8px}@media print{.giftback-credit details,.giftback-credit{break-inside:avoid}.giftback-pagination{display:none}}
</style>
<section class="giftback-credits" aria-labelledby="giftback-credits-title">
    <h2 id="giftback-credits-title">Créditos e validade do giftback</h2>
    <p>Cada crédito tem sua própria validade. O valor indicado pode ser usado até o fim do dia informado, no horário de Brasília. O histórico permanece disponível após o saldo zerar.</p>
    <?php if (!empty($giftbackFilterStoreId) && empty($giftbackExport)): ?>
        <p>Mostrando créditos da loja selecionada. <a href="?<?= $giftbackEscape(http_build_query(array_diff_key($_GET, ['loja_id' => true, 'giftback_page' => true]))) ?>#giftback-credits-title">Ver todas as lojas</a></p>
    <?php endif; ?>
    <?php if (empty($giftbackCredits['items'])): ?>
        <p>Nenhum crédito registrado<?= !empty($giftbackFilterStoreId) ? ' nesta loja' : '' ?>.</p>
    <?php else: ?>
        <?php foreach ($giftbackCredits['items'] as $giftbackCredit): ?>
            <details class="giftback-credit" <?= !empty($giftbackExport) ? 'open' : '' ?>>
                <summary>
                    <strong><?= $giftbackEscape($giftbackCredit['storeName'] ?? 'Loja') ?> · Crédito #<?= (int) $giftbackCredit['id'] ?></strong>
                    <span><?= $giftbackMoney($giftbackCredit['remainingCents']) ?> disponíveis</span>
                    <span><?= !empty($giftbackCredit['validUntil']) ? 'Válido até ' . $giftbackDate($giftbackCredit['validUntil']) : 'Sem vencimento' ?></span>
                </summary>
                <dl>
                    <div><dt>Recebido em</dt><dd><?= $giftbackDate($giftbackCredit['creditedAt'] ?? null) ?></dd></div>
                    <div><dt>Valor original</dt><dd><?= $giftbackMoney($giftbackCredit['originalCents']) ?></dd></div>
                    <div><dt>Utilizado</dt><dd><?= $giftbackMoney($giftbackCredit['consumedCents'] ?? 0) ?></dd></div>
                    <div><dt>Expirado</dt><dd><?= $giftbackMoney($giftbackCredit['expiredCents'] ?? 0) ?></dd></div>
                    <div><dt>Situação</dt><dd><?= $giftbackEscape($giftbackStatuses[$giftbackCredit['status'] ?? ''] ?? ((int) $giftbackCredit['remainingCents'] > 0 ? 'Disponível' : 'Sem saldo')) ?></dd></div>
                </dl>
                <h3>Histórico deste crédito</h3>
                <ul class="giftback-events">
                    <?php foreach ($giftbackCredit['events'] ?? [] as $giftbackEvent): $giftbackDelta = (int) ($giftbackEvent['deltaCents'] ?? 0); ?>
                        <li><span><?= $giftbackEscape($giftbackEvent['label']) ?><small><?= $giftbackEscape(date('d/m/Y H:i', strtotime((string) $giftbackEvent['occurredAt']))) ?></small>
                        <?php if (in_array($giftbackEvent['type'], ['prorrogacao', 'reversao_expiracao'], true)): ?><small>Validade: <?= $giftbackDate($giftbackEvent['oldValidUntil'] ?? null) ?> → <?= $giftbackDate($giftbackEvent['newValidUntil'] ?? null) ?></small><?php endif; ?></span>
                        <span class="<?= $giftbackDelta < 0 ? 'negative' : ($giftbackDelta > 0 ? 'positive' : '') ?>"><?= $giftbackDelta === 0 ? 'Sem alteração de saldo' : ($giftbackDelta < 0 ? '− ' : '+ ') . $giftbackMoney(abs($giftbackDelta)) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endforeach; ?>
        <?php if (empty($giftbackExport) && (int) ($giftbackCredits['total'] ?? 0) > (int) ($giftbackCredits['pageSize'] ?? 20)):
            $giftbackPage = (int) ($giftbackCredits['page'] ?? 1);
            $giftbackPages = (int) ceil($giftbackCredits['total'] / $giftbackCredits['pageSize']); ?>
            <nav class="giftback-pagination" aria-label="Páginas de créditos">
                <?php if ($giftbackPage > 1): ?><a href="?<?= $giftbackEscape(http_build_query(array_merge($_GET, ['giftback_page' => $giftbackPage - 1]))) ?>#giftback-credits-title">Anteriores</a><?php endif; ?>
                <span>Página <?= $giftbackPage ?> de <?= $giftbackPages ?></span>
                <?php if ($giftbackPage < $giftbackPages): ?><a href="?<?= $giftbackEscape(http_build_query(array_merge($_GET, ['giftback_page' => $giftbackPage + 1]))) ?>#giftback-credits-title">Próximos</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
