<?php
/**
 * Componente de Navbar Reestruturada - Klube Cash
 * 
 * Navbar intuitiva e responsiva para todos os tipos de usuário
 * Mantém toda a lógica original com melhorias na experiência do usuário
 */

// Verificar se as constantes estão definidas
if (!defined('SITE_URL')) {
    require_once(__DIR__ . '/../../config/constants.php');
}

// Iniciar sessão se não estiver ativa - mantendo lógica original
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar login e tipo de usuário - lógica preservada
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? $_SESSION['user_name'] ?? 'Usuário' : '';
$userType = $isLoggedIn ? $_SESSION['user_type'] ?? '' : '';
$userSenat = $isLoggedIn ? $_SESSION['user_senat'] ?? 'Não' : 'Não';

// Identificar tipo de usuário - mantendo classificação original
$isAdmin = $userType === 'admin';
$isClient = $userType === 'cliente';
$isStore = $userType === 'loja';
$isFuncionario = $userType === 'funcionario';
$hasSenat = $userSenat === 'Sim';
?>

<style>
    /* === VARIÁVEIS CSS PARA CONSISTÊNCIA === */
    :root {
        --primary-color: #FF7A00;
        --primary-light: #FFB366;
        --primary-dark: #E65C00;
        --secondary-color: #2563eb;
        --secondary-light: #3b82f6;
        --secondary-dark: #1d4ed8;
        --white: #FFFFFF;
        --gray-50: #F9FAFB;
        --gray-100: #F3F4F6;
        --gray-200: #E5E7EB;
        --gray-300: #D1D5DB;
        --gray-400: #9CA3AF;
        --gray-500: #6B7280;
        --gray-600: #4B5563;
        --gray-700: #374151;
        --gray-800: #1F2937;
        --gray-900: #111827;
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        --nav-height: 70px;
        --transition-fast: 0.15s ease;
        --transition-normal: 0.3s ease;
        --border-radius: 0.5rem;
        --border-radius-lg: 0.75rem;
    }

    /* === RESET E BASE === */
    * {
        box-sizing: border-box;
    }

    /* === CORREÇÃO PARA BODY COM NAVBAR FIXA === */
    body {
        padding-top: var(--nav-height);
        margin: 0;
    }

    /* === NAVBAR PRINCIPAL === */
    .klube-navbar {
        background: linear-gradient(135deg, var(--white) 0%, var(--gray-50) 100%);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid var(--gray-200);
        box-shadow: var(--shadow-md);
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1000;
        height: var(--nav-height);
        display: flex;
        align-items: center;
        padding: 0 1rem;
    }

    /* === CONTAINER PRINCIPAL === */
    .navbar-container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        max-width: 1200px;
        margin: 0 auto;
    }

    /* === SEÇÃO DA MARCA/LOGO === */
    .navbar-brand-section {
        display: flex;
        align-items: center;
        flex-shrink: 0;
    }

    .navbar-brand-link {
        display: flex;
        align-items: center;
        text-decoration: none;
        transition: var(--transition-fast);
        padding: 0.5rem;
        border-radius: var(--border-radius);
    }

    .navbar-brand-link:hover {
        background-color: var(--gray-100);
        transform: translateY(-1px);
    }

    .navbar-logo {
        height: 40px;
        width: auto;
        margin-right: 0.75rem;
    }

    .navbar-title {
        font-size: 1.5rem;
        font-weight: 700;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        letter-spacing: -0.025em;
    }

    /* === NAVEGAÇÃO PRINCIPAL === */
    .navbar-navigation {
        display: flex;
        align-items: center;
        flex: 1;
        justify-content: center;
    }

    .navbar-menu {
        display: flex;
        align-items: center;
        list-style: none;
        margin: 0;
        padding: 0;
        gap: 0.25rem;
    }

    .navbar-menu-item {
        position: relative;
    }

    .navbar-menu-link {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1rem;
        text-decoration: none;
        color: var(--gray-700);
        font-weight: 500;
        font-size: 0.875rem;
        border-radius: var(--border-radius);
        transition: var(--transition-normal);
        position: relative;
        overflow: hidden;
    }

    /* Efeito hover mais suave e moderno */
    .navbar-menu-link::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(37, 99, 235, 0.1), transparent);
        transition: var(--transition-normal);
    }

    .navbar-menu-link:hover::before {
        left: 100%;
    }

    .navbar-menu-link:hover {
        color: var(--primary-color);
        background-color: rgba(37, 99, 235, 0.05);
        transform: translateY(-1px);
    }

    .navbar-menu-icon {
        width: 18px;
        height: 18px;
        transition: var(--transition-fast);
    }

    .navbar-menu-link:hover .navbar-menu-icon {
        transform: scale(1.1);
    }

    /* === ÁREA DO USUÁRIO === */
    .navbar-user-section {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-shrink: 0;
    }

    /* Botão de login melhorado para visitantes */
    .navbar-login-btn {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.625rem 1.25rem;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: var(--white);
        text-decoration: none;
        border-radius: var(--border-radius-lg);
        font-weight: 600;
        font-size: 0.875rem;
        transition: var(--transition-normal);
        box-shadow: var(--shadow-sm);
    }

    .navbar-login-btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
        background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
    }

    .navbar-register-btn {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.625rem 1.25rem;
        background: transparent;
        color: var(--primary-color);
        text-decoration: none;
        border: 2px solid var(--primary-color);
        border-radius: var(--border-radius-lg);
        font-weight: 600;
        font-size: 0.875rem;
        transition: var(--transition-normal);
    }

    .navbar-register-btn:hover {
        background: var(--primary-color);
        color: var(--white);
        transform: translateY(-1px);
    }

    /* === PERFIL DO USUÁRIO LOGADO === */
    .navbar-user-profile {
        position: relative;
        display: flex;
        align-items: center;
    }

    .navbar-user-trigger {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.5rem;
        background: var(--white);
        border: 2px solid var(--gray-200);
        border-radius: var(--border-radius-lg);
        cursor: pointer;
        transition: var(--transition-normal);
        box-shadow: var(--shadow-sm);
    }

    .navbar-user-trigger:hover {
        border-color: var(--primary-color);
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .navbar-user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--white);
        font-weight: 700;
        font-size: 0.875rem;
        text-transform: uppercase;
    }

    .navbar-user-info {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }

    .navbar-user-name {
        font-weight: 600;
        font-size: 0.875rem;
        color: var(--gray-800);
        line-height: 1.2;
    }

    .navbar-user-type {
        font-size: 0.75rem;
        color: var(--gray-600);
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }

    .navbar-chevron {
        width: 16px;
        height: 16px;
        color: var(--gray-600);
        transition: var(--transition-fast);
    }

    .navbar-user-trigger:hover .navbar-chevron {
        transform: rotate(180deg);
    }

    /* === DROPDOWN DO USUÁRIO === */
    .navbar-user-dropdown {
        position: absolute;
        top: calc(100% + 0.5rem);
        right: 0;
        background: var(--white);
        border: 1px solid var(--gray-200);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-xl);
        min-width: 220px;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: var(--transition-normal);
        z-index: 100;
    }

    .navbar-user-dropdown.open {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .navbar-dropdown-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        color: var(--gray-700);
        text-decoration: none;
        font-size: 0.875rem;
        transition: var(--transition-fast);
        border-bottom: 1px solid var(--gray-100);
    }

    .navbar-dropdown-item:last-child {
        border-bottom: none;
    }

    .navbar-dropdown-item:hover {
        background-color: var(--gray-50);
        color: var(--primary-color);
    }

    .navbar-dropdown-item:first-child {
        border-top-left-radius: var(--border-radius-lg);
        border-top-right-radius: var(--border-radius-lg);
    }

    .navbar-dropdown-item:last-child {
        border-bottom-left-radius: var(--border-radius-lg);
        border-bottom-right-radius: var(--border-radius-lg);
    }

    .navbar-dropdown-icon {
        width: 18px;
        height: 18px;
        color: var(--gray-500);
        transition: var(--transition-fast);
    }

    .navbar-dropdown-item:hover .navbar-dropdown-icon {
        color: var(--primary-color);
        transform: scale(1.1);
    }

    /* === BOTÃO MOBILE === */
    .navbar-mobile-toggle {
        display: none;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        background: var(--white);
        border: 2px solid var(--gray-200);
        border-radius: var(--border-radius);
        cursor: pointer;
        transition: var(--transition-normal);
        position: relative;
        overflow: hidden;
    }

    .navbar-mobile-toggle:hover {
        border-color: var(--primary-color);
        background-color: var(--gray-50);
    }

    /* Animação do hambúrguer */
    .navbar-hamburger {
        width: 20px;
        height: 20px;
        position: relative;
        transform: rotate(0deg);
        transition: var(--transition-normal);
    }

    .navbar-hamburger span {
        display: block;
        position: absolute;
        height: 2px;
        width: 100%;
        background: var(--gray-700);
        border-radius: 1px;
        opacity: 1;
        left: 0;
        transform: rotate(0deg);
        transition: var(--transition-normal);
    }

    .navbar-hamburger span:nth-child(1) { top: 4px; }
    .navbar-hamburger span:nth-child(2) { top: 9px; }
    .navbar-hamburger span:nth-child(3) { top: 14px; }

    .navbar-mobile-toggle.open .navbar-hamburger span:nth-child(1) {
        top: 9px;
        transform: rotate(135deg);
    }

    .navbar-mobile-toggle.open .navbar-hamburger span:nth-child(2) {
        opacity: 0;
        left: -20px;
    }

    .navbar-mobile-toggle.open .navbar-hamburger span:nth-child(3) {
        top: 9px;
        transform: rotate(-135deg);
    }

    /* === RESPONSIVIDADE === */
    @media (max-width: 768px) {
        body {
            padding-top: 60px;
        }

        .klube-navbar {
            padding: 0 1rem;
            height: 60px;
        }

        .navbar-mobile-toggle {
            display: flex;
        }

        .navbar-navigation {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--white);
            border-top: 1px solid var(--gray-200);
            box-shadow: var(--shadow-lg);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-20px);
            transition: var(--transition-normal);
        }

        .navbar-navigation.open {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .navbar-menu {
            flex-direction: column;
            padding: 1rem;
            gap: 0.5rem;
        }

        .navbar-menu-item {
            width: 100%;
        }

        .navbar-menu-link {
            justify-content: flex-start;
            padding: 1rem;
            border-radius: var(--border-radius);
            width: 100%;
        }

        .navbar-user-info {
            display: none;
        }

        .navbar-user-dropdown {
            right: 0;
            left: auto;
            min-width: 200px;
        }

        /* Ocultar botões de login/registro em mobile se logado */
        .navbar-login-btn,
        .navbar-register-btn {
            display: none;
        }

        /* Mostrar apenas se não logado */
        .navbar-user-section:not(.logged-in) .navbar-login-btn {
            display: flex;
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }
    }

    @media (max-width: 480px) {
        .navbar-title {
            font-size: 1.25rem;
        }

        .navbar-logo {
            height: 32px;
        }

        .navbar-user-dropdown {
            min-width: 180px;
        }
    }

    /* === MELHORIAS DE ACESSIBILIDADE === */
    @media (prefers-reduced-motion: reduce) {
        * {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
        }
    }

    /* Focus states para acessibilidade */
    .navbar-menu-link:focus,
    .navbar-user-trigger:focus,
    .navbar-mobile-toggle:focus {
        outline: 2px solid var(--primary-color);
        outline-offset: 2px;
    }
</style>

<!-- === NAVBAR HTML === -->
<nav class="klube-navbar" role="navigation" aria-label="Navegação principal">
    <div class="navbar-container">
        
        <!-- === SEÇÃO DA MARCA === -->
        <div class="navbar-brand-section">
            <a href="<?php echo $isLoggedIn ? ($isAdmin ? SITE_URL . '/views/admin/dashboard.php' : ($isStore || $isFuncionario ? SITE_URL . '/views/stores/dashboard.php' : SITE_URL . '/views/client/dashboard.php')) : SITE_URL; ?>" 
               class="navbar-brand-link" 
               aria-label="Ir para página inicial do Klube Cash">
                <img src="<?php echo SITE_URL; ?>/assets/images/logolaranja.png" 
                     alt="Logo Klube Cash" 
                     class="navbar-logo"
                     onerror="this.src='<?php echo SITE_URL; ?>/assets/images/icons/KlubeCashLOGO.ico'">
            </a>
        </div>

        <!-- === BOTÃO MOBILE === -->
        <button class="navbar-mobile-toggle" 
                id="mobileToggle" 
                aria-label="Abrir menu de navegação"
                aria-expanded="false">
            <div class="navbar-hamburger">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </button>

        <!-- === NAVEGAÇÃO PRINCIPAL === -->
        <div class="navbar-navigation" id="navbarMenu">
            <ul class="navbar-menu">
                <?php if (!$isLoggedIn): ?>
                    <!-- Menu para visitantes -->
                    <li class="navbar-menu-item">
                        <a href="<?php echo SITE_URL; ?>" class="navbar-menu-link">
                            <svg class="navbar-menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                            </svg>
                            <span>Início</span>
                        </a>
                    </li>
                    <li class="navbar-menu-item">
                        <a href="<?php echo SITE_URL; ?>/views/public/about.php" class="navbar-menu-link">
                            <svg class="navbar-menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>Sobre</span>
                        </a>
                    </li>
                    <li class="navbar-menu-item">
                        <a href="<?php echo SITE_URL; ?>/views/public/partner-registration.php" class="navbar-menu-link">
                            <svg class="navbar-menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                            <span>Seja Parceiro</span>
                        </a>
                    </li>

                <?php elseif ($isClient): ?>
                    <!-- Menu para clientes -->
                    <li class="navbar-menu-item">
                        <a href="<?php echo SITE_URL; ?>/views/client/dashboard.php" class="navbar-menu-link">
                            <svg class="navbar-menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v0M8 5a2 2 0 000 4h8a2 2 0 000-4M8 5v0"></path>
                            </svg>
                            <span>Meu Painel</span>
                        </a>
                    </li>
                    <li class="navbar-menu-item">
                        <a href="<?php echo SITE_URL; ?>/views/client/balance.php" class="navbar-menu-link">
                            <svg class="navbar-menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>Meu Saldo</span>
                        </a>
                    </li>
                    <li class="navbar-menu-item">
                        <a href="<?php echo SITE_URL; ?>/views/client/statement.php" class="navbar-menu-link">
                            <svg class="navbar-menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span>Meu Extrato</span>
                        </a>
                    </li>
                    <?php if ($hasSenat): ?>
                    <li class="navbar-menu-item">
                        <a href="<?php echo SITE_URL; ?>/views/auth/wallet-select.php" class="navbar-menu-link">
                            <svg class="navbar-menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                            </svg>
                            <span>Trocar Carteira</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="navbar-menu-item">
                        <a href="<?php echo SITE_URL; ?>/views/client/partner-stores.php" class="navbar-menu-link">
                            <svg class="navbar-menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                            <span>Lojas Parceiras</span>
                        </a>
                    </li>

                <?php elseif ($isAdmin): ?>
                    <!-- Menu simplificado para admin -->
                    <li class="navbar-menu-item">
                        <a href="<?php echo SITE_URL; ?>/views/admin/dashboard.php" class="navbar-menu-link">
                            <svg class="navbar-menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                            <span>Dashboard Admin</span>
                        </a>
                    </li>
                    <li class="navbar-menu-item">
                        <a href="<?php echo SITE_URL; ?>/views/admin/users.php" class="navbar-menu-link">
                            <svg class="navbar-menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                            </svg>
                            <span>Usuários</span>
                        </a>
                    </li>

                <?php elseif ($isStore || $isFuncionario): ?>
                    <!-- Menu para lojas e funcionários -->
                    <li class="navbar-menu-item">
                        <a href="<?php echo SITE_URL; ?>/views/stores/dashboard.php" class="navbar-menu-link">
                            <svg class="navbar-menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                            <span>Minha Loja</span>
                        </a>
                    </li>
                    <li class="navbar-menu-item">
                        <a href="<?php echo SITE_URL; ?>/views/stores/register-transaction.php" class="navbar-menu-link">
                            <svg class="navbar-menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            <span>Nova Venda</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- === SEÇÃO DO USUÁRIO === -->
        <div class="navbar-user-section <?php echo $isLoggedIn ? 'logged-in' : ''; ?>">
            <?php if ($isLoggedIn): ?>
                <!-- Perfil do usuário logado -->
                <div class="navbar-user-profile">
                    <button class="navbar-user-trigger" 
                            id="userDropdownToggle" 
                            aria-label="Abrir menu do usuário"
                            aria-expanded="false">
                        <div class="navbar-user-avatar">
                            <?php echo strtoupper(substr($userName, 0, 1)); ?>
                        </div>
                        <div class="navbar-user-info">
                            <span class="navbar-user-name"><?php echo htmlspecialchars($userName); ?></span>
                            <span class="navbar-user-type">
                                <?php 
                                switch($userType) {
                                    case 'admin':
                                        echo 'Administrador';
                                        break;
                                    case 'loja':
                                        echo 'Loja Parceira';
                                        break;
                                    case 'funcionario':
                                        echo 'Funcionário';
                                        break;
                                    default:
                                        echo 'Cliente';
                                }
                                ?>
                            </span>
                        </div>
                        <svg class="navbar-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>

                    <!-- Dropdown do usuário -->
                    <div class="navbar-user-dropdown" id="userDropdown">
                        <?php if ($isClient): ?>
                            <a href="<?php echo SITE_URL; ?>/views/client/profile.php" class="navbar-dropdown-item">
                                <svg class="navbar-dropdown-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                <span>Meu Perfil</span>
                            </a>
                        <?php elseif ($isAdmin): ?>
                            <a href="<?php echo SITE_URL; ?>/views/admin/settings.php" class="navbar-dropdown-item">
                                <svg class="navbar-dropdown-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <span>Configurações</span>
                            </a>
                        <?php elseif ($isStore || $isFuncionario): ?>
                            <a href="<?php echo SITE_URL; ?>/views/stores/profile.php" class="navbar-dropdown-item">
                                <svg class="navbar-dropdown-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                <span>Perfil da Loja</span>
                            </a>
                        <?php endif; ?>
                        
                        <a href="<?php echo SITE_URL; ?>/controllers/AuthController.php?action=logout" 
                           class="navbar-dropdown-item"
                           onclick="return confirm('Tem certeza que deseja sair?')">
                            <svg class="navbar-dropdown-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                            </svg>
                            <span>Sair</span>
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Botões para visitantes -->
                <a href="<?php echo SITE_URL; ?>/views/auth/login.php" class="navbar-login-btn">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                    </svg>
                    <span>Entrar</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/views/auth/register.php" class="navbar-register-btn">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                    </svg>
                    <span>Cadastrar</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // === ELEMENTOS DOM ===
    const mobileToggle = document.getElementById('mobileToggle');
    const navbarMenu = document.getElementById('navbarMenu');
    const userDropdownToggle = document.getElementById('userDropdownToggle');
    const userDropdown = document.getElementById('userDropdown');

    // === TOGGLE MENU MOBILE ===
    if (mobileToggle && navbarMenu) {
        mobileToggle.addEventListener('click', function() {
            const isOpen = navbarMenu.classList.contains('open');
            
            // Toggle classes
            navbarMenu.classList.toggle('open');
            mobileToggle.classList.toggle('open');
            
            // Update ARIA attributes
            mobileToggle.setAttribute('aria-expanded', !isOpen);
            mobileToggle.setAttribute('aria-label', isOpen ? 'Abrir menu de navegação' : 'Fechar menu de navegação');
        });
    }

    // === DROPDOWN DO USUÁRIO ===
    if (userDropdownToggle && userDropdown) {
        userDropdownToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            const isOpen = userDropdown.classList.contains('open');
            
            // Toggle dropdown
            userDropdown.classList.toggle('open');
            
            // Update ARIA attributes
            userDropdownToggle.setAttribute('aria-expanded', !isOpen);
        });
    }

    // === FECHAR DROPDOWN AO CLICAR FORA ===
    document.addEventListener('click', function(e) {
        // Fechar dropdown do usuário
        if (userDropdown && userDropdownToggle) {
            if (!userDropdown.contains(e.target) && !userDropdownToggle.contains(e.target)) {
                userDropdown.classList.remove('open');
                userDropdownToggle.setAttribute('aria-expanded', 'false');
            }
        }
        
        // Fechar menu mobile ao clicar em link
        if (navbarMenu && mobileToggle) {
            if (e.target.closest('.navbar-menu-link')) {
                navbarMenu.classList.remove('open');
                mobileToggle.classList.remove('open');
                mobileToggle.setAttribute('aria-expanded', 'false');
                mobileToggle.setAttribute('aria-label', 'Abrir menu de navegação');
            }
        }
    });

    // === FECHAR MENU MOBILE NO REDIMENSIONAMENTO ===
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            if (navbarMenu && mobileToggle) {
                navbarMenu.classList.remove('open');
                mobileToggle.classList.remove('open');
                mobileToggle.setAttribute('aria-expanded', 'false');
                mobileToggle.setAttribute('aria-label', 'Abrir menu de navegação');
            }
        }
    });

    // === SMOOTH SCROLL PARA ÂNCORAS ===
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // === DETECTAR PÁGINA ATIVA === 
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.navbar-menu-link');
    
    navLinks.forEach(link => {
        const linkPath = new URL(link.href).pathname;
        if (currentPath === linkPath || (currentPath.includes(linkPath) && linkPath !== '/')) {
            link.style.color = 'var(--primary-color)';
            link.style.backgroundColor = 'rgba(37, 99, 235, 0.1)';
        }
    });
});
</script>