<?php
/**
 * Menu "Apps" do Grupo Klube (trocar de aplicativo e voltar ao Portal) para as paginas em PHP.
 * A lista vem da sessao (gravada no login unico). Sem lista ou com menos de 2 apps, nao desenha nada.
 */

use App\Services\Auth\GrupoKlubeApps;

require_once __DIR__ . '/../../services/Auth/GrupoKlubeApps.php';

$kcAppsMenu = GrupoKlubeApps::menu();
if ($kcAppsMenu === null) {
    return;
}
$kcAppsIcons = require __DIR__ . '/apps-icons.php';
$kcAppsLogo = static function (string $slug, string $nome) use ($kcAppsIcons): string {
    if (isset($kcAppsIcons[$slug])) {
        return '<span class="kc-apps-icone logo" aria-hidden="true">' . $kcAppsIcons[$slug] . '</span>';
    }
    return '<span class="kc-apps-icone" aria-hidden="true">' . htmlspecialchars(mb_strtoupper(mb_substr($nome, 0, 1)), ENT_QUOTES, 'UTF-8') . '</span>';
};
?>
<style>
    .kc-apps-menu { position: relative; margin-right: 10px; }
    .kc-apps-btn { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border: 1px solid #E8E8EA; border-radius: 12px; background: #fff; color: #1E1E1E; font: inherit; font-weight: 600; cursor: pointer; list-style: none; min-height: 40px; }
    .kc-apps-btn::-webkit-details-marker { display: none; }
    .kc-apps-btn:hover, .kc-apps-menu[open] .kc-apps-btn { border-color: #FF7A00; color: #FF7A00; }
    .kc-apps-painel { position: absolute; right: 0; top: calc(100% + 8px); z-index: 1200; min-width: 270px; max-width: calc(100vw - 32px); padding: 8px; border: 1px solid #E8E8EA; border-radius: 16px; background: #fff; box-shadow: 0 12px 32px rgba(15, 25, 60, .16); display: grid; gap: 2px; }
    .kc-apps-item { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 12px; color: #1E1E1E; text-decoration: none; font-weight: 600; }
    .kc-apps-item small { display: block; font-weight: 400; color: #6B7280; }
    .kc-apps-item:hover { background: #F7F7F8; }
    .kc-apps-item[aria-current='true'] { background: #FFF1E3; color: #C25A00; }
    .kc-apps-icone { flex: none; width: 34px; height: 34px; border-radius: 10px; background: #FFF1E3; color: #C25A00; display: grid; place-items: center; font-weight: 800; }
    .kc-apps-icone.logo { background: none; padding: 0; border-radius: 22%; line-height: 0; overflow: hidden; }
    .kc-apps-icone.logo > svg { width: 100%; height: 100%; display: block; }
    .kc-apps-divisor { height: 1px; background: #E8E8EA; margin: 4px 6px; }
    @media (max-width: 768px) { .kc-apps-btn-texto { display: none; } .kc-apps-menu { position: static; } .kc-apps-painel { position: fixed; left: 12px; right: 12px; top: 72px; min-width: 0; max-width: none; } }
</style>
<details class="kc-apps-menu">
    <summary class="kc-apps-btn" aria-label="Trocar de aplicativo">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <?php foreach ([5, 12, 19] as $kcX): foreach ([5, 12, 19] as $kcY): ?>
                <circle cx="<?php echo $kcX; ?>" cy="<?php echo $kcY; ?>" r="1.8"/>
            <?php endforeach; endforeach; ?>
        </svg>
        <span class="kc-apps-btn-texto">Apps</span>
    </summary>
    <div class="kc-apps-painel" role="menu" aria-label="Aplicativos do Grupo Klube">
        <a role="menuitem" class="kc-apps-item" href="<?php echo htmlspecialchars($kcAppsMenu['portalUrl'], ENT_QUOTES, 'UTF-8'); ?>" rel="noopener noreferrer">
            <?php echo $kcAppsLogo('klube-intranet', 'Portal'); ?>
            <span>Portal Grupo Klube<small>Todos os seus aplicativos</small></span>
        </a>
        <div class="kc-apps-divisor" role="separator"></div>
        <?php foreach ($kcAppsMenu['itens'] as $kcItem): ?>
            <a role="menuitem" class="kc-apps-item" href="<?php echo htmlspecialchars($kcItem['url'], ENT_QUOTES, 'UTF-8'); ?>"<?php echo $kcItem['atual'] ? ' aria-current="true"' : ' rel="noopener noreferrer"'; ?>>
                <?php echo $kcAppsLogo($kcItem['slug'], $kcItem['nome']); ?>
                <span><?php echo htmlspecialchars($kcItem['nome'], ENT_QUOTES, 'UTF-8'); ?><?php echo $kcItem['atual'] ? '<small>Você está aqui</small>' : ''; ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</details>
