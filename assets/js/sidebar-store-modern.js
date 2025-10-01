/**
 * Sidebar Moderna para Lojas - JavaScript ATUALIZADO
 * Sistema completo de controle da sidebar responsiva
 */

class ModernSidebar {
    constructor() {
        this.sidebar = document.getElementById('sidebarContainer');
        this.toggleBtn = document.getElementById('toggleBtn');
        this.mobileToggle = document.getElementById('mobileToggle');
        this.expandBtn = document.getElementById('expandBtn');
        this.mobileOverlay = document.getElementById('mobileOverlay');
        this.body = document.body;
        this.navLinks = document.querySelectorAll('.nav-link');
        
        this.isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        this.isMobileOpen = false;
        this.hideTimeout = null;
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.initState();
        this.setupAccessibility();
        this.setupNotifications();
        this.setupAdvancedFeatures();
    }
    
    bindEvents() {
        // Desktop toggle
        this.toggleBtn?.addEventListener('click', () => this.toggleDesktop());
        this.expandBtn?.addEventListener('click', () => this.toggleDesktop());
        
        // Mobile toggle
        this.mobileToggle?.addEventListener('click', () => this.toggleMobile());
        
        // Mobile overlay
        this.mobileOverlay?.addEventListener('click', () => this.closeMobile());
        
        // Navigation links
        this.navLinks.forEach(link => {
            link.addEventListener('click', (e) => this.handleNavClick(e, link));
        });
        
        // Window resize
        window.addEventListener('resize', () => this.handleResize());
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));
        
        // Touch events para mobile
        this.setupTouchEvents();
        
        // NOVO: Cliques fora da sidebar
        this.setupOutsideClicks();
        
        // NOVO: Hover events para sidebar
        this.setupHoverEvents();
    }
    
    setupAdvancedFeatures() {
        // NOVO: Auto-hide mobile toggle quando sidebar aberta
        this.setupAutoHideMobileToggle();
        
        // NOVO: Smooth animations
        this.setupSmoothAnimations();
        
        // NOVO: Performance optimizations
        this.setupPerformanceOptimizations();
    }
    
    setupOutsideClicks() {
        document.addEventListener('click', (e) => {
            if (!this.isMobile() || !this.isMobileOpen) return;
            
            const clickedInsideSidebar = this.sidebar.contains(e.target);
            const clickedOnToggle = this.mobileToggle.contains(e.target);
            
            if (!clickedInsideSidebar && !clickedOnToggle) {
                this.closeMobile();
            }
        });
    }
    
    setupHoverEvents() {
        this.sidebar.addEventListener('mouseenter', () => {
            if (this.isMobile() && this.isMobileOpen) {
                this.clearHideTimeout();
                this.showMobileToggle();
            }
        });
        
        this.sidebar.addEventListener('mouseleave', () => {
            if (this.isMobile() && this.isMobileOpen) {
                this.hideMobileToggleDelayed();
            }
        });
    }
    
    setupAutoHideMobileToggle() {
        // Auto-hide mobile toggle after sidebar opens
        this.sidebar.addEventListener('transitionend', (e) => {
            if (e.propertyName === 'transform' && this.isMobile() && this.isMobileOpen) {
                this.hideMobileToggleDelayed();
            }
        });
    }
    
    setupSmoothAnimations() {
        // Add smooth page transitions
        this.navLinks.forEach(link => {
            link.addEventListener('click', () => {
                document.body.classList.add('page-loading');
                setTimeout(() => {
                    document.body.classList.remove('page-loading');
                }, 500);
            });
        });
    }
    
    setupPerformanceOptimizations() {
        // Throttle resize events
        let resizeTimeout;
        const originalHandleResize = this.handleResize.bind(this);
        this.handleResize = () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(originalHandleResize, 100);
        };
    }
    
    hideMobileToggle() {
        this.mobileToggle?.classList.add('hidden');
    }
    
    showMobileToggle() {
        this.mobileToggle?.classList.remove('hidden');
    }
    
    hideMobileToggleDelayed(delay = 2000) {
        this.clearHideTimeout();
        this.hideTimeout = setTimeout(() => {
            if (this.isMobileOpen) {
                this.hideMobileToggle();
            }
        }, delay);
    }
    
    clearHideTimeout() {
        if (this.hideTimeout) {
            clearTimeout(this.hideTimeout);
            this.hideTimeout = null;
        }
    }
    
    initState() {
        if (!this.isMobile() && this.isCollapsed) {
            this.sidebar.classList.add('collapsed');
        }
        
        this.updateMainContent();
        this.updateToggleVisibility();
    }
    
    updateToggleVisibility() {
        if (this.isMobile()) {
            if (this.isMobileOpen) {
                this.hideMobileToggleDelayed();
            } else {
                this.showMobileToggle();
            }
        } else {
            this.showMobileToggle();
        }
    }
    
    setupAccessibility() {
        // ARIA labels
        this.sidebar.setAttribute('role', 'navigation');
        this.sidebar.setAttribute('aria-label', 'Menu principal');
        
        if (this.isMobile()) {
            this.sidebar.setAttribute('aria-hidden', 'true');
            this.mobileToggle.setAttribute('aria-expanded', 'false');
        }
        
        // Focus trap para mobile
        this.setupFocusTrap();
        
        // NOVO: Keyboard navigation dentro da sidebar
        this.setupKeyboardNavigation();
    }
    
    setupKeyboardNavigation() {
        this.navLinks.forEach((link, index) => {
            link.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    const nextLink = this.navLinks[index + 1];
                    if (nextLink) nextLink.focus();
                }
                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    const prevLink = this.navLinks[index - 1];
                    if (prevLink) prevLink.focus();
                }
            });
        });
    }
    
    setupFocusTrap() {
        const focusableElements = this.sidebar.querySelectorAll(
            'a, button, [tabindex]:not([tabindex="-1"])'
        );
        
        if (focusableElements.length === 0) return;
        
        const firstFocusable = focusableElements[0];
        const lastFocusable = focusableElements[focusableElements.length - 1];
        
        this.sidebar.addEventListener('keydown', (e) => {
            if (e.key === 'Tab' && this.isMobileOpen) {
                if (e.shiftKey) {
                    if (document.activeElement === firstFocusable) {
                        e.preventDefault();
                        lastFocusable.focus();
                    }
                } else {
                    if (document.activeElement === lastFocusable) {
                        e.preventDefault();
                        firstFocusable.focus();
                    }
                }
            }
        });
    }
    
    setupTouchEvents() {
        let startX = 0;
        let currentX = 0;
        let isDragging = false;
        
        // Swipe para abrir sidebar
        document.addEventListener('touchstart', (e) => {
            if (this.isMobile() && e.touches[0].clientX < 50) {
                startX = e.touches[0].clientX;
                isDragging = true;
            }
        });
        
        document.addEventListener('touchmove', (e) => {
            if (!isDragging) return;
            currentX = e.touches[0].clientX;
            
            // Prevent scrolling while swiping
            if (Math.abs(currentX - startX) > 10) {
                e.preventDefault();
            }
        }, { passive: false });
        
        document.addEventListener('touchend', () => {
            if (!isDragging) return;
            
            const diff = currentX - startX;
            if (diff > 50 && !this.isMobileOpen) {
                this.openMobile();
            }
            
            isDragging = false;
        });
        
        // Swipe para fechar sidebar
        this.sidebar.addEventListener('touchstart', (e) => {
            if (this.isMobile() && this.isMobileOpen) {
                startX = e.touches[0].clientX;
                isDragging = true;
            }
        });
        
        this.sidebar.addEventListener('touchmove', (e) => {
            if (!isDragging) return;
            currentX = e.touches[0].clientX;
        });
        
        this.sidebar.addEventListener('touchend', () => {
            if (!isDragging) return;
            
            const diff = startX - currentX;
            if (diff > 100) {
                this.closeMobile();
            }
            
            isDragging = false;
        });
    }
    
    setupNotifications() {
        // Simular notificações em tempo real
        this.checkNotifications();
        setInterval(() => this.checkNotifications(), 30000); // A cada 30 segundos
    }
    
    checkNotifications() {
        // Aqui você pode fazer uma chamada AJAX para verificar notificações
        // Exemplo simulado:
        const pendingPayments = Math.floor(Math.random() * 5);
        this.updateBadge('payment-history', pendingPayments);
    }
    
    updateBadge(menuId, count) {
        const navLink = this.sidebar.querySelector(`[data-page="${menuId}"]`);
        if (!navLink) return;
        
        let badge = navLink.querySelector('.nav-badge');
        
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'nav-badge';
                navLink.appendChild(badge);
            }
            badge.textContent = count > 99 ? '99+' : count;
            badge.setAttribute('aria-label', `${count} itens pendentes`);
        } else if (badge) {
            badge.remove();
        }
    }
    
    toggleDesktop() {
        if (this.isMobile()) return;
        
        this.isCollapsed = !this.isCollapsed;
        this.sidebar.classList.toggle('collapsed', this.isCollapsed);
        localStorage.setItem('sidebarCollapsed', this.isCollapsed);
        
        this.updateMainContent();
        this.dispatchToggleEvent();
        
        // NOVO: Feedback visual
        this.showFeedback(this.isCollapsed ? 'Menu minimizado' : 'Menu expandido');
    }
    
    toggleMobile() {
        if (!this.isMobile()) return;
        
        if (this.isMobileOpen) {
            this.closeMobile();
        } else {
            this.openMobile();
        }
    }
    
    openMobile() {
        this.isMobileOpen = true;
        this.sidebar.classList.add('open');
        this.mobileOverlay.classList.add('active');
        this.body.classList.add('sidebar-open');
        
        this.hideMobileToggleDelayed();
        
        this.sidebar.setAttribute('aria-hidden', 'false');
        this.mobileToggle.setAttribute('aria-expanded', 'true');
        
        // Focus no primeiro item do menu
        const firstNavLink = this.sidebar.querySelector('.nav-link');
        if (firstNavLink) {
            setTimeout(() => firstNavLink.focus(), 100);
        }
    }
    
    closeMobile() {
        this.isMobileOpen = false;
        this.sidebar.classList.remove('open');
        this.mobileOverlay.classList.remove('active');
        this.body.classList.remove('sidebar-open');
        
        this.clearHideTimeout();
        this.showMobileToggle();
        
        this.sidebar.setAttribute('aria-hidden', 'true');
        this.mobileToggle.setAttribute('aria-expanded', 'false');
    }
    
    handleNavClick(e, link) {
        // Adicionar estado de loading
        link.classList.add('loading');
        
        // Fechar sidebar mobile se aberta
        if (this.isMobile() && this.isMobileOpen) {
            setTimeout(() => this.closeMobile(), 150);
        }
        
        // Remover loading após navegação
        setTimeout(() => {
            link.classList.remove('loading');
        }, 2000);
        
        // Analytics/tracking
        this.trackNavigation(link.dataset.page);
        
        // NOVO: Feedback visual de navegação
        this.showNavigationFeedback(link.querySelector('.nav-text').textContent);
    }
    
    handleResize() {
        if (this.isMobile()) {
            this.sidebar.classList.remove('collapsed');
            if (this.isMobileOpen) {
                this.body.classList.add('sidebar-open');
                this.hideMobileToggleDelayed();
            } else {
                this.showMobileToggle();
            }
        } else {
            this.closeMobile();
            this.showMobileToggle();
            if (this.isCollapsed) {
                this.sidebar.classList.add('collapsed');
            }
        }
        this.updateMainContent();
        this.updateToggleVisibility();
    }
    
    handleKeyboard(e) {
        // ESC para fechar sidebar mobile
        if (e.key === 'Escape' && this.isMobile() && this.isMobileOpen) {
            this.closeMobile();
        }
        
        // Ctrl+B para toggle sidebar desktop
        if (e.ctrlKey && e.key === 'b' && !this.isMobile()) {
            e.preventDefault();
            this.toggleDesktop();
        }
        
        // M para mostrar mobile toggle quando oculto
        if (e.key === 'm' && this.isMobile() && this.isMobileOpen) {
            this.showMobileToggle();
            this.clearHideTimeout();
        }
        
        // NOVO: Alt+M para toggle mobile
        if (e.altKey && e.key === 'm' && this.isMobile()) {
            e.preventDefault();
            this.toggleMobile();
        }
    }
    
    updateMainContent() {
        const mainContent = document.querySelector('.main-content, .content, .page-content');
        if (!mainContent) return;
        
        if (this.isMobile()) {
            mainContent.style.marginLeft = '0';
        } else {
            const marginLeft = this.isCollapsed ? '80px' : '280px';
            mainContent.style.marginLeft = marginLeft;
        }
        
        mainContent.style.transition = 'margin-left 0.3s ease';
    }
    
    dispatchToggleEvent() {
        window.dispatchEvent(new CustomEvent('sidebarToggle', {
            detail: {
                collapsed: this.isCollapsed,
                width: this.isCollapsed ? 80 : 280
            }
        }));
    }
    
    trackNavigation(page) {
        // Aqui você pode adicionar tracking/analytics
        console.log('Navegação:', page);
        
        // Exemplo de tracking com Google Analytics
        if (typeof gtag !== 'undefined') {
            gtag('event', 'navigation', {
                'page': page,
                'source': 'sidebar'
            });
        }
    }
    
    // NOVO: Feedback visual
    showFeedback(message, type = 'info', duration = 2000) {
        const feedback = document.createElement('div');
        feedback.className = `sidebar-feedback feedback-${type}`;
        feedback.textContent = message;
        feedback.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? '#10B981' : '#3B82F6'};
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            animation: slideInRight 0.3s ease-out;
        `;
        
        document.body.appendChild(feedback);
        
        setTimeout(() => {
            feedback.style.animation = 'slideOutRight 0.3s ease-in forwards';
            setTimeout(() => feedback.remove(), 300);
        }, duration);
    }
    
    showNavigationFeedback(pageName) {
        this.showFeedback(`Navegando para ${pageName}...`, 'info', 1500);
    }
    
    isMobile() {
        return window.innerWidth <= 768;
    }
    
    // Métodos públicos para controle externo
    collapse() {
        if (!this.isMobile() && !this.isCollapsed) {
            this.toggleDesktop();
        }
    }
    
    expand() {
        if (!this.isMobile() && this.isCollapsed) {
            this.toggleDesktop();
        }
    }
    
    open() {
        if (this.isMobile() && !this.isMobileOpen) {
            this.openMobile();
        }
    }
    
    close() {
        if (this.isMobile() && this.isMobileOpen) {
            this.closeMobile();
        }
    }
    
    setActive(menuId) {
        // Remove active de todos
        this.navLinks.forEach(link => link.classList.remove('active'));
        
        // Adiciona active ao menu específico
        const activeLink = this.sidebar.querySelector(`[data-page="${menuId}"]`);
        if (activeLink) {
            activeLink.classList.add('active');
            activeLink.setAttribute('aria-current', 'page');
        }
    }
    
    showNotification(message, type = 'info') {
        // Criar notificação temporária na sidebar
        const notification = document.createElement('div');
        notification.className = `sidebar-notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <span class="notification-message">${message}</span>
                <button class="notification-close" aria-label="Fechar">×</button>
            </div>
        `;
        
        const userProfile = this.sidebar.querySelector('.user-profile');
        userProfile.insertAdjacentElement('afterend', notification);
        
        // Auto remover após 5 segundos
        setTimeout(() => {
            notification.remove();
        }, 5000);
        
        // Evento de fechar
        notification.querySelector('.notification-close').addEventListener('click', () => {
            notification.remove();
        });
    }
    
    // NOVO: Controles específicos do mobile toggle
    getToggleControls() {
        return {
            show: () => this.showMobileToggle(),
            hide: () => this.hideMobileToggle(),
            hideDelayed: (delay) => this.hideMobileToggleDelayed(delay)
        };
    }
    
    destroy() {
        // Cleanup para caso seja necessário destruir a instância
        this.clearHideTimeout();
        
        this.navLinks.forEach(link => {
            link.removeEventListener('click', this.handleNavClick);
        });
        
        window.removeEventListener('resize', this.handleResize);
        document.removeEventListener('keydown', this.handleKeyboard);
    }
}

// Adicionar estilos para animações de feedback
const feedbackStyles = document.createElement('style');
feedbackStyles.textContent = `
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100%);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes slideOutRight {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(100%);
        }
    }
`;
document.head.appendChild(feedbackStyles);

// Inicializar quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    window.modernSidebar = new ModernSidebar();
    
    // Expor controles globais
    window.sidebarControls = {
        toggleMobile: () => window.modernSidebar.getToggleControls(),
        showToggle: () => window.modernSidebar.showMobileToggle(),
        hideToggle: () => window.modernSidebar.hideMobileToggle(),
        close: () => window.modernSidebar.closeMobile(),
        setActive: (menuId) => window.modernSidebar.setActive(menuId)
    };
});

// Exportar para uso externo
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ModernSidebar;
}