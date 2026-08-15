<?php
/**
 * Sidebar Responsiva para Lojistas - Klube Cash
 * Versão Ultra Responsiva e Profissional
 */

// Verificações de segurança
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (
    !isset($_SESSION['user_id'], $_SESSION['store_id'])
    || !in_array($_SESSION['user_type'] ?? '', ['loja', 'funcionario'], true)
) {
    header('Location: ' . LOGIN_URL . '?error=acesso_restrito');
    exit;
}

$menu_ativo = $activeMenu ?? 'dashboard';
$nome_usuario = $_SESSION['user_name'] ?? 'Lojista';
$email_usuario = $_SESSION['user_email'] ?? '';

// Gerar iniciais do usuário
$iniciais_usuario = '';
$partes_nome = explode(' ', $nome_usuario);
if (count($partes_nome) >= 2) {
    $iniciais_usuario = substr($partes_nome[0], 0, 1) . substr($partes_nome[1], 0, 1);
} else {
    $iniciais_usuario = substr($nome_usuario, 0, 2);
}
$iniciais_usuario = strtoupper($iniciais_usuario);

// Itens do menu principal
$itens_menu_principal = [
    [
        'identificacao' => 'dashboard',
        'titulo' => 'Dashboard',
        'url' => STORE_DASHBOARD_URL,
        'icone' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v6H8V5z"/>'
    ],
    [
        'identificacao' => 'nova-venda',
        'titulo' => 'Nova Venda',
        'url' => STORE_REGISTER_TRANSACTION_URL,
        'icone' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>'
    ],
    [
        'identificacao' => 'transactions',
        'titulo' => 'Transações',
        'url' => STORE_TRANSACTIONS_URL,
        'speculate' => true,
        'icone' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h10"/>'
    ],
    [
        'identificacao' => 'batch-upload',
        'titulo' => 'Upload em Lote',
        'url' => STORE_BATCH_UPLOAD_URL,
        'speculate' => true,
        'icone' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16V4m0 0L7 9m5-5l5 5M5 15v4h14v-4"/>'
    ],
    [
        'identificacao' => 'funcionarios',
        'titulo' => 'Funcionários',
        'url' => STORE_EMPLOYEES_URL,
        'speculate' => true,
        'icone' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>'
    ],
    [
        'identificacao' => 'pagamentos',
        'titulo' => 'Pagamentos',
        'url' => STORE_PAYMENT_HISTORY_URL,
        'icone' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>'
    ],
    [
        'identificacao' => 'pendentes-pagamento',
        'titulo' => 'Pendentes de Pagamentos',
        'url' => STORE_PENDING_TRANSACTIONS_URL,
        'speculate' => true,
        'icone' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>',
        'contador' => 0 // Será preenchido dinamicamente se necessário
    ],
    [
        'identificacao' => 'meu-plano',
        'titulo' => 'Meu Plano',
        'url' => STORE_SUBSCRIPTION_URL,
        'speculate' => true,
        'icone' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>'
    ]
];
?>

<div class="store-app-progress" aria-hidden="true"></div>
<div class="store-app-status" id="storeAppStatus" role="status" aria-live="polite" aria-atomic="true"></div>

<!-- Overlay para Mobile -->
<div class="overlay-sidebar-mobile" id="overlaySidebarMobile"></div>

<!-- Toggle Mobile -->
<button class="botao-toggle-mobile" id="botaoToggleMobile" type="button" aria-label="Abrir menu" aria-controls="sidebarLojistaResponsiva" aria-expanded="false">
    <svg class="icone-hamburguer" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
    </svg>
</button>

<!-- Sidebar Principal -->
<aside class="sidebar-lojista-responsiva" id="sidebarLojistaResponsiva" aria-label="Navegação da loja">
    
    <!-- Cabeçalho da Sidebar -->
    <div class="cabecalho-sidebar-lojista">
        <div class="container-logo-sidebar">
            <img src="/assets/images/logobranco.png" alt="Klube Cash" class="logo-sidebar-lojista" width="42" height="42">
            <span class="texto-logo-sidebar">KlubeCash</span>
        </div>

        <div class="acoes-sidebar-topo">
            <button class="botao-tema-sidebar" id="storeThemeToggle" type="button" aria-label="Ativar modo noturno" aria-pressed="false">
                <svg class="icone-tema-lua" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
                </svg>
                <svg class="icone-tema-sol" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <circle cx="12" cy="12" r="4" stroke-width="2"/>
                    <path stroke-linecap="round" stroke-width="2" d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3l1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3l1.42-1.42"/>
                </svg>
            </button>

            <!-- Botão Colapsar Desktop -->
            <button class="botao-colapsar-sidebar" id="botaoColapsarSidebar" type="button" aria-label="Minimizar menu" aria-controls="sidebarLojistaResponsiva" aria-expanded="true">
                <svg class="icone-colapsar" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Informações do Usuário -->
    <div class="info-usuario-sidebar">
        <div class="avatar-usuario-lojista">
            <?php echo $iniciais_usuario; ?>
        </div>
        <div class="dados-usuario-sidebar">
            <div class="nome-usuario-sidebar"><?php echo htmlspecialchars($nome_usuario); ?></div>
            <div class="tipo-usuario-sidebar">
                <svg class="icone-tipo-usuario" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <?php echo ($_SESSION['user_type'] ?? '') === 'funcionario' ? 'Funcionário' : 'Lojista'; ?>
            </div>
        </div>
    </div>

    <!-- Navegação Principal -->
    <nav class="navegacao-sidebar-lojista">
        <div class="secao-menu-sidebar">
            <h3 class="titulo-secao-menu">Principal</h3>
            <ul class="lista-menu-sidebar">
                <?php foreach ($itens_menu_principal as $item): ?>
                <li class="item-menu-sidebar">
                    <a href="<?php echo $item['url']; ?>" 
                       class="link-menu-sidebar <?php echo $menu_ativo === $item['identificacao'] ? 'menu-ativo' : ''; ?>"
                       data-menu="<?php echo $item['identificacao']; ?>"
                       title="<?php echo htmlspecialchars($item['titulo']); ?>"
                       <?php echo !empty($item['speculate']) ? 'data-store-speculate="safe"' : ''; ?>
                       <?php echo $menu_ativo === $item['identificacao'] ? 'aria-current="page"' : ''; ?>>
                        
                        <svg class="icone-menu-sidebar" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <?php echo $item['icone']; ?>
                        </svg>
                        
                        <span class="texto-menu-sidebar"><?php echo $item['titulo']; ?></span>
                        
                        <?php if (isset($item['contador']) && $item['contador'] > 0): ?>
                        <span class="contador-menu-sidebar" aria-label="<?php echo $item['contador']; ?> itens">
                            <?php echo $item['contador'] > 99 ? '99+' : $item['contador']; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Seção de Conta -->
        <div class="secao-menu-sidebar secao-conta-sidebar">
            <h3 class="titulo-secao-menu">Conta</h3>
            <ul class="lista-menu-sidebar">
                <li class="item-menu-sidebar">
                    <a href="<?php echo STORE_PROFILE_URL; ?>" 
                       class="link-menu-sidebar <?php echo $menu_ativo === 'perfil' ? 'menu-ativo' : ''; ?>"
                       data-menu="perfil"
                       title="Perfil"
                       <?php echo $menu_ativo === 'perfil' ? 'aria-current="page"' : ''; ?>>
                        
                        <svg class="icone-menu-sidebar" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        
                        <span class="texto-menu-sidebar">Perfil</span>
                    </a>
                </li>
                
                <li class="item-menu-sidebar">
                    <a href="<?php echo LOGOUT_URL; ?>"
                       class="link-menu-sidebar link-sair-sidebar"
                       data-menu="sair"
                       title="Sair"
                       data-no-store-transition="true"
                       onclick="return confirm('Tem certeza que deseja sair?')">

                        <svg class="icone-menu-sidebar" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>

                        <span class="texto-menu-sidebar">Sair</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

</aside>
