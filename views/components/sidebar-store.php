
<?php
/**
 * Sidebar da Loja - Versão Corrigida
 * Sistema perfeito de posicionamento sem sobreposições
 */

// Verificações básicas
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'loja') {
    header('Location: ' . LOGIN_URL . '?error=acesso_restrito');
    exit;
}

$activeMenu = $activeMenu ?? 'dashboard';
$userName = $_SESSION['user_name'] ?? 'Lojista';

// Iniciais do usuário
$initials = '';
$nameParts = explode(' ', $userName);
if (count($nameParts) >= 2) {
    $initials = substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1);
} else {
    $initials = substr($userName, 0, 2);
}
$initials = strtoupper($initials);

// Menu items
$menuItems = [
    [
        'id' => 'dashboard', 
        'title' => 'Dashboard', 
        'url' => STORE_DASHBOARD_URL,
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v6H8V5z"/>'
    ],
    [
        'id' => 'register-transaction', 
        'title' => 'Nova Venda', 
        'url' => STORE_REGISTER_TRANSACTION_URL,
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>'
    ],
    [
        'id' => 'funcionarios', 
        'title' => 'Funcionários', 
        'url' => STORE_EMPLOYEES_URL,
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>'
    ],
    /*
    // VENDAS - OCULTADO CONFORME SOLICITADO
    [
        'id' => 'transactions', 
        'title' => 'Vendas', 
        'url' => STORE_TRANSACTIONS_URL,
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>'
    ],
    */
    /*
    // UPLOAD EM LOTE - OCULTADO CONFORME SOLICITADO
    [
        'id' => 'batch-upload', 
        'title' => 'Upload em Lote', 
        'url' => STORE_BATCH_UPLOAD_URL,
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>'
    ],
    */
    [
        'id' => 'payment-history',
        'title' => 'Pagamentos', 
        'url' => STORE_PAYMENT_HISTORY_URL, 
        'badge' => 3,
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>'
    ],
    [
        'id' => 'saldos', 
        'title' => 'Pendentes de Pagamento', 
        'url' => STORE_PENDING_TRANSACTIONS_URL,
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>'
    ],
    [
        'id' => 'profile', 
        'title' => 'Perfil', 
        'url' => STORE_PROFILE_URL,
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>'
    ]
];
?>

<?php
// Injeta assets do build (/dist) quando presentes
include_once __DIR__ . '/dist-loader.php';
?>

<!-- CSS da Sidebar Incorporado -->
<link rel="stylesheet" href="../../assets/css/sidebar-store-perfect.css">

<!-- Mobile Toggle -->
<button class="klube-mobile-toggle" id="klubeMobileToggle" aria-label="Abrir menu">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
    </svg>
</button>

<!-- Overlay -->
<div class="klube-overlay" id="klubeOverlay"></div>

<!-- Sidebar -->
<aside class="klube-sidebar" id="klubeSidebar">
    
    <!-- Header -->
    <header class="klube-sidebar-header">
        <div class="klube-logo-container">
            <img src="../../assets/images/logo-icon.png" alt="Klube Cash" class="klube-logo">
            <span class="klube-logo-text">Klube Cash</span>
        </div>
        <button class="klube-collapse-btn" id="klubeCollapseBtn" aria-label="Recolher menu">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15,18 9,12 15,6"></polyline>
            </svg>
        </button>
    </header>

    <!-- Perfil do usuário -->
    <div class="klube-user-profile">
        <div class="klube-avatar"><?= $initials ?></div>
        <div class="klube-user-info">
            <div class="klube-user-name"><?= htmlspecialchars($userName) ?></div>
            <div class="klube-user-role">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                Lojista
            </div>
        </div>
    </div>

    <!-- Navegação -->
    <nav class="klube-nav" role="navigation">
        <div class="klube-nav-section">
            <h3 class="klube-section-title">Menu Principal</h3>
            <ul class="klube-menu">
                <?php foreach ($menuItems as $item): ?>
                    <li class="klube-menu-item">
                        <a href="<?= $item['url'] ?>" 
                           class="klube-menu-link <?= ($activeMenu === $item['id']) ? 'active' : '' ?>"
                           data-page="<?= $item['id'] ?>"
                           aria-current="<?= ($activeMenu === $item['id']) ? 'page' : 'false' ?>">
                            <span class="klube-menu-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <?= $item['icon'] ?>
                                </svg>
                            </span>
                            <span class="klube-menu-text"><?= $item['title'] ?></span>
                            <?php if (isset($item['badge']) && $item['badge'] > 0): ?>
                                <span class="klube-badge"><?= $item['badge'] ?></span>
                            <?php endif; ?>
                            <span class="klube-tooltip"><?= $item['title'] ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </nav>

    <!-- Footer -->
   <footer class="klube-sidebar-footer">
        <a href="<?php echo LOGOUT_URL; ?>" class="klube-logout-btn" onclick="return confirm('Tem certeza que deseja sair?')">
            <svg class="klube-logout-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4m7 14l5-5-5-5m5 5H9"/>
            </svg>
            <span class="klube-logout-text">Sair</span>
        </a>
    </footer>

</aside>

<!-- Script da Sidebar -->
<script>
(function() {
    'use strict';
    
    // Elementos
    const sidebar = document.getElementById('klubeSidebar');
    const collapseBtn = document.getElementById('klubeCollapseBtn');
    const mobileToggle = document.getElementById('klubeMobileToggle');
    const overlay = document.getElementById('klubeOverlay');
    
    if (!sidebar) return;
    
    // Estado
    let isCollapsed = localStorage.getItem('klubeSidebarCollapsed') === 'true';
    let isMobileOpen = false;
    
    // Funções utilitárias
    function isMobile() { 
        return window.innerWidth <= 768; 
    }
    
    function adjustMainContent() {
        // Aguarda um pouco para garantir que a sidebar foi renderizada
        setTimeout(() => {
            const mainContent = document.querySelector('.main-content, .content, .page-content, main');
            if (mainContent) {
                if (isMobile()) {
                    mainContent.style.marginLeft = '0';
                    mainContent.style.paddingLeft = '0';
                } else {
                    const sidebarWidth = isCollapsed ? '80px' : '280px';
                    mainContent.style.marginLeft = sidebarWidth;
                    mainContent.style.paddingLeft = '0';
                    mainContent.style.transition = 'margin-left 0.3s ease';
                }
                
                // Adiciona classe especial para identificação
                mainContent.classList.add('klube-main-adjusted');
            }
        }, 50);
    }
    
    // Toggle desktop
    function toggleDesktop() {
        if (isMobile()) return;
        
        isCollapsed = !isCollapsed;
        sidebar.classList.toggle('collapsed', isCollapsed);
        localStorage.setItem('klubeSidebarCollapsed', isCollapsed);
        adjustMainContent();
    }
    
    // Toggle mobile
    function toggleMobile() {
        if (!isMobile()) return;
        
        isMobileOpen = !isMobileOpen;
        sidebar.classList.toggle('mobile-open', isMobileOpen);
        overlay.classList.toggle('active', isMobileOpen);
        document.body.classList.toggle('klube-mobile-menu-open', isMobileOpen);
    }
    
    function closeMobile() {
        if (!isMobile()) return;
        
        isMobileOpen = false;
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('active');
        document.body.classList.remove('klube-mobile-menu-open');
    }
    
    // Event listeners
    if (collapseBtn) {
        collapseBtn.addEventListener('click', toggleDesktop);
    }
    
    if (mobileToggle) {
        mobileToggle.addEventListener('click', toggleMobile);
    }
    
    if (overlay) {
        overlay.addEventListener('click', closeMobile);
    }
    
    // Clicks fora da sidebar em mobile
    document.addEventListener('click', function(e) {
        if (isMobile() && isMobileOpen && 
            !sidebar.contains(e.target) && 
            !mobileToggle.contains(e.target)) {
            closeMobile();
        }
    });
    
    // Resize handler
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            if (isMobile()) {
                sidebar.classList.remove('collapsed');
                closeMobile();
            } else {
                sidebar.classList.toggle('collapsed', isCollapsed);
            }
            adjustMainContent();
        }, 100);
    });
    
    // Teclas de atalho
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'b' && !isMobile()) {
            e.preventDefault();
            toggleDesktop();
        }
        if (e.key === 'Escape' && isMobile() && isMobileOpen) {
            closeMobile();
        }
    });
    
    // Inicialização
    function initialize() {
        if (!isMobile() && isCollapsed) {
            sidebar.classList.add('collapsed');
        }
        adjustMainContent();
        
        // Observer para mudanças no DOM
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    adjustMainContent();
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
    
    // Garantir inicialização após DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
    
    console.log('✅ Sidebar carregada com posicionamento perfeito');
    
})();
</script>
