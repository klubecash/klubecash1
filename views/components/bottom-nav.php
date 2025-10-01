<?php
// Bottom Navigation para PWA
// Navegação inferior mobile-friendly

$currentPage = basename($_SERVER['REQUEST_URI']);
?>

<nav class="bottom-navigation">
    <a href="/client/dashboard-pwa" class="nav-item <?php echo $currentPage === 'dashboard-pwa' ? 'active' : ''; ?>">
        <div class="nav-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
            </svg>
        </div>
        <span class="nav-label">Início</span>
    </a>
    
    <a href="/client/statement-pwa" class="nav-item <?php echo $currentPage === 'statement-pwa' ? 'active' : ''; ?>">
        <div class="nav-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
            </svg>
        </div>
        <span class="nav-label">Histórico</span>
    </a>
    
    <a href="/client/partner-stores-pwa" class="nav-item <?php echo $currentPage === 'partner-stores-pwa' ? 'active' : ''; ?>">
        <div class="nav-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <path d="M20 4H4v2h16V4zm1 10v-2l-1-5H4l-1 5v2h1v6h10v-6h4v6h2v-6h1zm-9 4H6v-4h6v4z"/>
            </svg>
        </div>
        <span class="nav-label">Lojas</span>
    </a>
    
    <a href="/client/profile-pwa" class="nav-item <?php echo $currentPage === 'profile-pwa' ? 'active' : ''; ?>">
        <div class="nav-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
            </svg>
        </div>
        <span class="nav-label">Perfil</span>
    </a>
</nav>

<style>
/* Bottom Navigation Styles */
.bottom-navigation {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: white;
    border-top: 1px solid #e0e0e0;
    display: flex;
    height: 70px;
    z-index: 1000;
    padding: 0 env(safe-area-inset-left) env(safe-area-inset-bottom) env(safe-area-inset-right);
}

.nav-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    color: #666;
    transition: all 0.3s ease;
    padding: 8px 4px;
    position: relative;
}

.nav-item.active {
    color: #28A745;
}

.nav-item.active::before {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 24px;
    height: 3px;
    background: #28A745;
    border-radius: 0 0 3px 3px;
}

.nav-icon {
    margin-bottom: 4px;
    transition: transform 0.2s ease;
}

.nav-item:active .nav-icon {
    transform: scale(0.9);
}

.nav-label {
    font-size: 10px;
    font-weight: 500;
    text-align: center;
}

/* Dark mode */
@media (prefers-color-scheme: dark) {
    .bottom-navigation {
        background: #1a1a1a;
        border-top-color: #333;
    }
    
    .nav-item {
        color: #ccc;
    }
    
    .nav-item.active {
        color: #28A745;
    }
}

/* Vibração haptic */
.nav-item:active {
    background: rgba(40, 167, 69, 0.1);
    border-radius: 12px;
}
</style>

<script>
// Adicionar haptic feedback na navegação
document.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('touchstart', function() {
        if (navigator.vibrate) {
            navigator.vibrate(10);
        }
    });
});
</script>