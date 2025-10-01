/**
 * UI Interactions - Klube Cash PWA
 * Gerencia gestos touch, pull-to-refresh e sistema de modais
 * Comentários em português conforme solicitado
 */

class UIInteractions {
    constructor() {
        this.touchStartY = 0;
        this.touchStartX = 0;
        this.touchEndY = 0;
        this.touchEndX = 0;
        this.isRefreshing = false;
        this.pullThreshold = 80;
        this.refreshElement = null;
        this.modalsStack = [];
        this.activeModal = null;
        
        // Configurações de gestos
        this.swipeThreshold = 50;
        this.tapTimeout = null;
        this.longPressTimeout = null;
        this.longPressDuration = 500;
        
        this.initialize();
    }

    /**
     * Inicializa todas as funcionalidades de interação
     */
    initialize() {
        this.initializePullToRefresh();
        this.initializeModalSystem();
        this.initializeTouchGestures();
        this.initializeKeyboardHandlers();
        
        console.log('UI Interactions inicializado para Klube Cash PWA');
    }

    // =============================================
    // PULL TO REFRESH
    // =============================================

    /**
     * Inicializa o sistema de pull-to-refresh
     */
    initializePullToRefresh() {
        // Criar elemento de refresh se não existir
        if (!document.querySelector('.pull-refresh-indicator')) {
            this.createRefreshIndicator();
        }

        this.refreshElement = document.querySelector('.pull-refresh-indicator');

        // Adicionar listeners de touch para pull-to-refresh
        document.addEventListener('touchstart', this.handlePullStart.bind(this), { passive: false });
        document.addEventListener('touchmove', this.handlePullMove.bind(this), { passive: false });
        document.addEventListener('touchend', this.handlePullEnd.bind(this), { passive: false });
    }

    /**
     * Cria o indicador visual de pull-to-refresh
     */
    createRefreshIndicator() {
        const indicator = document.createElement('div');
        indicator.className = 'pull-refresh-indicator';
        indicator.innerHTML = `
            <div class="refresh-spinner">
                <svg class="refresh-icon" viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM12 20c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>
                    <path d="M12 6v6l4 2" stroke="currentColor" stroke-width="2" fill="none"/>
                </svg>
                <span class="refresh-text">Puxe para atualizar</span>
            </div>
        `;

        // Inserir no início do body
        document.body.insertBefore(indicator, document.body.firstChild);

        // Adicionar estilos CSS dinamicamente
        this.addRefreshStyles();
    }

    /**
     * Adiciona estilos CSS para o pull-to-refresh
     */
    addRefreshStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .pull-refresh-indicator {
                position: fixed;
                top: -80px;
                left: 0;
                right: 0;
                height: 80px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: var(--primary-color, #2E7D32);
                color: white;
                z-index: 9999;
                transition: transform 0.3s ease;
            }

            .pull-refresh-indicator.visible {
                transform: translateY(80px);
            }

            .pull-refresh-indicator.refreshing .refresh-icon {
                animation: spin 1s linear infinite;
            }

            .refresh-spinner {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 8px;
            }

            .refresh-icon {
                width: 24px;
                height: 24px;
                fill: currentColor;
            }

            .refresh-text {
                font-size: 12px;
                font-weight: 500;
            }

            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Inicia o processo de pull
     */
    handlePullStart(e) {
        if (this.isRefreshing || window.scrollY > 0) return;
        
        this.touchStartY = e.touches[0].clientY;
    }

    /**
     * Processa o movimento durante o pull
     */
    handlePullMove(e) {
        if (this.isRefreshing || window.scrollY > 0) return;

        const touchY = e.touches[0].clientY;
        const pullDistance = Math.max(0, touchY - this.touchStartY);

        if (pullDistance > 10) {
            e.preventDefault(); // Previne scroll padrão
            
            const progress = Math.min(pullDistance / this.pullThreshold, 1);
            this.updateRefreshIndicator(progress, pullDistance);
        }
    }

    /**
     * Finaliza o pull e decide se deve fazer refresh
     */
    handlePullEnd(e) {
        if (this.isRefreshing || window.scrollY > 0) return;

        const touchY = e.changedTouches[0].clientY;
        const pullDistance = touchY - this.touchStartY;

        if (pullDistance >= this.pullThreshold) {
            this.triggerRefresh();
        } else {
            this.resetRefreshIndicator();
        }
    }

    /**
     * Atualiza o indicador visual durante o pull
     */
    updateRefreshIndicator(progress, distance) {
        if (!this.refreshElement) return;

        const text = this.refreshElement.querySelector('.refresh-text');
        
        if (progress >= 1) {
            text.textContent = 'Solte para atualizar';
            this.refreshElement.classList.add('ready');
        } else {
            text.textContent = 'Puxe para atualizar';
            this.refreshElement.classList.remove('ready');
        }

        this.refreshElement.style.transform = `translateY(${Math.min(distance, this.pullThreshold)}px)`;
    }

    /**
     * Executa o refresh propriamente dito
     */
    triggerRefresh() {
        if (this.isRefreshing) return;

        this.isRefreshing = true;
        this.refreshElement.classList.add('visible', 'refreshing');
        
        const text = this.refreshElement.querySelector('.refresh-text');
        text.textContent = 'Atualizando...';

        // Emitir evento customizado para que outras partes da aplicação possam reagir
        const refreshEvent = new CustomEvent('pullRefresh', {
            detail: { timestamp: Date.now() }
        });
        document.dispatchEvent(refreshEvent);

        // Auto-hide após 2 segundos se não for manualmente escondido
        setTimeout(() => {
            if (this.isRefreshing) {
                this.finishRefresh();
            }
        }, 2000);
    }

    /**
     * Finaliza o processo de refresh
     */
    finishRefresh() {
        this.isRefreshing = false;
        this.refreshElement.classList.remove('visible', 'refreshing', 'ready');
        this.refreshElement.style.transform = '';
        
        const text = this.refreshElement.querySelector('.refresh-text');
        text.textContent = 'Puxe para atualizar';
    }

    /**
     * Reseta o indicador de refresh
     */
    resetRefreshIndicator() {
        this.refreshElement.classList.remove('ready');
        this.refreshElement.style.transform = '';
    }

    // =============================================
    // SISTEMA DE MODAIS
    // =============================================

    /**
     * Inicializa o sistema de modais
     */
    initializeModalSystem() {
        // Adicionar estilos para modais
        this.addModalStyles();
        
        // Interceptar cliques em elementos com data-modal
        document.addEventListener('click', this.handleModalTrigger.bind(this));
        
        // Fechar modal com ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.activeModal) {
                this.closeModal(this.activeModal);
            }
        });
    }

    /**
     * Adiciona estilos CSS para o sistema de modais
     */
    addModalStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(4px);
                z-index: 10000;
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
            }

            .modal-overlay.active {
                opacity: 1;
                visibility: visible;
            }

            .modal-container {
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                background: white;
                border-radius: 16px 16px 0 0;
                max-height: 90vh;
                transform: translateY(100%);
                transition: transform 0.3s ease;
                overflow-y: auto;
            }

            .modal-overlay.active .modal-container {
                transform: translateY(0);
            }

            .modal-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 16px 20px;
                border-bottom: 1px solid #eee;
                position: sticky;
                top: 0;
                background: white;
                z-index: 1;
            }

            .modal-title {
                font-size: 18px;
                font-weight: 600;
                margin: 0;
                color: #333;
            }

            .modal-close {
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                padding: 0;
                width: 32px;
                height: 32px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                color: #666;
                transition: background-color 0.2s ease;
            }

            .modal-close:hover {
                background-color: #f5f5f5;
            }

            .modal-body {
                padding: 20px;
            }

            .modal-drag-indicator {
                width: 40px;
                height: 4px;
                background: #ccc;
                border-radius: 2px;
                margin: 8px auto;
                cursor: grab;
            }

            @media (min-width: 768px) {
                .modal-container {
                    position: absolute;
                    bottom: auto;
                    top: 50%;
                    left: 50%;
                    right: auto;
                    width: 500px;
                    max-width: 90vw;
                    transform: translate(-50%, -50%) scale(0.9);
                    border-radius: 16px;
                }

                .modal-overlay.active .modal-container {
                    transform: translate(-50%, -50%) scale(1);
                }
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Manipula cliques em triggers de modal
     */
    handleModalTrigger(e) {
        const trigger = e.target.closest('[data-modal]');
        if (!trigger) return;

        e.preventDefault();
        
        const modalId = trigger.dataset.modal;
        const modalData = trigger.dataset.modalData ? JSON.parse(trigger.dataset.modalData) : {};
        
        this.openModal(modalId, modalData);
    }

    /**
     * Abre um modal
     */
    openModal(modalId, data = {}) {
        // Se já existe um modal ativo, fechar primeiro
        if (this.activeModal) {
            this.closeModal(this.activeModal);
        }

        // Criar modal dinamicamente
        const modal = this.createModal(modalId, data);
        document.body.appendChild(modal);

        // Ativar modal após um frame para permitir animação
        requestAnimationFrame(() => {
            modal.classList.add('active');
        });

        this.activeModal = modal;
        this.modalsStack.push(modal);

        // Prevenir scroll do body
        document.body.style.overflow = 'hidden';

        // Adicionar listeners de gesture para fechar
        this.addModalGestureListeners(modal);

        // Emitir evento
        const openEvent = new CustomEvent('modalOpen', {
            detail: { modalId, data }
        });
        document.dispatchEvent(openEvent);
    }

    /**
     * Cria um modal dinamicamente
     */
    createModal(modalId, data) {
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        overlay.dataset.modalId = modalId;

        overlay.innerHTML = `
            <div class="modal-container">
                <div class="modal-drag-indicator"></div>
                <div class="modal-header">
                    <h3 class="modal-title">${data.title || 'Modal'}</h3>
                    <button class="modal-close" data-action="close-modal">&times;</button>
                </div>
                <div class="modal-body">
                    ${this.getModalContent(modalId, data)}
                </div>
            </div>
        `;

        // Listener para fechar ao clicar no overlay
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                this.closeModal(overlay);
            }
        });

        // Listener para botão de fechar
        overlay.querySelector('.modal-close').addEventListener('click', () => {
            this.closeModal(overlay);
        });

        return overlay;
    }

    /**
     * Obtém o conteúdo específico de cada modal
     */
    getModalContent(modalId, data) {
        switch (modalId) {
            case 'transaction-details':
                return this.getTransactionDetailsContent(data);
            case 'store-details':
                return this.getStoreDetailsContent(data);
            case 'filter-options':
                return this.getFilterOptionsContent(data);
            default:
                return `<p>Conteúdo do modal ${modalId}</p>`;
        }
    }

    /**
     * Conteúdo para modal de detalhes de transação
     */
    getTransactionDetailsContent(data) {
        return `
            <div class="transaction-details">
                <div class="store-info">
                    <img src="${data.storeLogo || '/assets/images/store-placeholder.png'}" 
                         alt="${data.storeName}" class="store-logo">
                    <div>
                        <h4>${data.storeName || 'Loja'}</h4>
                        <p class="transaction-date">${data.date || 'Data não informada'}</p>
                    </div>
                </div>
                
                <div class="transaction-amount">
                    <span class="label">Valor da compra:</span>
                    <span class="value">R$ ${data.amount || '0,00'}</span>
                </div>
                
                <div class="cashback-amount">
                    <span class="label">Cashback:</span>
                    <span class="value highlight">R$ ${data.cashback || '0,00'}</span>
                </div>
                
                <div class="transaction-status">
                    <span class="label">Status:</span>
                    <span class="status-badge ${data.status || 'pending'}">${data.statusText || 'Pendente'}</span>
                </div>
                
                ${data.transactionId ? `
                    <div class="transaction-id">
                        <span class="label">ID da transação:</span>
                        <span class="value">${data.transactionId}</span>
                    </div>
                ` : ''}
            </div>
        `;
    }

    /**
     * Conteúdo para modal de detalhes da loja
     */
    getStoreDetailsContent(data) {
        return `
            <div class="store-details">
                <div class="store-header">
                    <img src="${data.logo || '/assets/images/store-placeholder.png'}" 
                         alt="${data.name}" class="store-logo-large">
                    <div>
                        <h3>${data.name || 'Loja'}</h3>
                        <p class="store-category">${data.category || 'Categoria'}</p>
                        <div class="cashback-percentage">
                            ${data.cashbackPercentage || '0'}% de cashback
                        </div>
                    </div>
                </div>
                
                ${data.description ? `
                    <div class="store-description">
                        <h4>Sobre a loja</h4>
                        <p>${data.description}</p>
                    </div>
                ` : ''}
                
                <div class="store-actions">
                    <button class="btn btn-primary" onclick="window.open('${data.website || '#'}', '_blank')">
                        Visitar Site
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Conteúdo para modal de filtros
     */
    getFilterOptionsContent(data) {
        return `
            <div class="filter-options">
                <div class="filter-group">
                    <label>Período</label>
                    <select class="filter-select" data-filter="period">
                        <option value="all">Todos os períodos</option>
                        <option value="7">Últimos 7 dias</option>
                        <option value="30">Últimos 30 dias</option>
                        <option value="90">Últimos 90 dias</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Status</label>
                    <div class="filter-chips">
                        <label class="filter-chip">
                            <input type="checkbox" value="pending" data-filter="status">
                            <span>Pendente</span>
                        </label>
                        <label class="filter-chip">
                            <input type="checkbox" value="available" data-filter="status">
                            <span>Disponível</span>
                        </label>
                        <label class="filter-chip">
                            <input type="checkbox" value="expired" data-filter="status">
                            <span>Expirado</span>
                        </label>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button class="btn btn-outline" data-action="clear-filters">
                        Limpar filtros
                    </button>
                    <button class="btn btn-primary" data-action="apply-filters">
                        Aplicar filtros
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Adiciona listeners de gesture para o modal
     */
    addModalGestureListeners(modal) {
        const container = modal.querySelector('.modal-container');
        const dragIndicator = modal.querySelector('.modal-drag-indicator');
        
        let startY = 0;
        let currentY = 0;
        let isDragging = false;

        const handleStart = (e) => {
            startY = e.touches ? e.touches[0].clientY : e.clientY;
            isDragging = true;
            container.style.transition = 'none';
        };

        const handleMove = (e) => {
            if (!isDragging) return;
            
            currentY = e.touches ? e.touches[0].clientY : e.clientY;
            const diff = currentY - startY;
            
            if (diff > 0) {
                container.style.transform = `translateY(${diff}px)`;
            }
        };

        const handleEnd = () => {
            if (!isDragging) return;
            
            isDragging = false;
            container.style.transition = '';
            
            const diff = currentY - startY;
            
            if (diff > 100) {
                this.closeModal(modal);
            } else {
                container.style.transform = '';
            }
        };

        // Touch events
        dragIndicator.addEventListener('touchstart', handleStart, { passive: true });
        modal.addEventListener('touchmove', handleMove, { passive: true });
        modal.addEventListener('touchend', handleEnd, { passive: true });

        // Mouse events (para desktop)
        dragIndicator.addEventListener('mousedown', handleStart);
        modal.addEventListener('mousemove', handleMove);
        modal.addEventListener('mouseup', handleEnd);
    }

    /**
     * Fecha um modal
     */
    closeModal(modal) {
        if (!modal) return;

        modal.classList.remove('active');

        setTimeout(() => {
            if (modal.parentNode) {
                modal.parentNode.removeChild(modal);
            }
        }, 300);

        // Remover da stack
        this.modalsStack = this.modalsStack.filter(m => m !== modal);
        this.activeModal = this.modalsStack[this.modalsStack.length - 1] || null;

        // Restaurar scroll do body se não há mais modais
        if (this.modalsStack.length === 0) {
            document.body.style.overflow = '';
        }

        // Emitir evento
        const closeEvent = new CustomEvent('modalClose', {
            detail: { modalId: modal.dataset.modalId }
        });
        document.dispatchEvent(closeEvent);
    }

    // =============================================
    // GESTOS TOUCH
    // =============================================

    /**
     * Inicializa os gestos touch
     */
    initializeTouchGestures() {
        document.addEventListener('touchstart', this.handleTouchStart.bind(this), { passive: true });
        document.addEventListener('touchmove', this.handleTouchMove.bind(this), { passive: true });
        document.addEventListener('touchend', this.handleTouchEnd.bind(this), { passive: true });
    }

    /**
     * Inicia o tracking de touch
     */
    handleTouchStart(e) {
        this.touchStartX = e.touches[0].clientX;
        this.touchStartY = e.touches[0].clientY;
        
        // Long press detection
        const target = e.target;
        this.longPressTimeout = setTimeout(() => {
            this.handleLongPress(target, e);
        }, this.longPressDuration);
    }

    /**
     * Processa movimento do touch
     */
    handleTouchMove(e) {
        // Cancelar long press se o dedo se mover muito
        const moveX = Math.abs(e.touches[0].clientX - this.touchStartX);
        const moveY = Math.abs(e.touches[0].clientY - this.touchStartY);
        
        if (moveX > 10 || moveY > 10) {
            clearTimeout(this.longPressTimeout);
        }
    }

    /**
     * Finaliza o touch e detecta gestos
     */
    handleTouchEnd(e) {
        clearTimeout(this.longPressTimeout);
        
        this.touchEndX = e.changedTouches[0].clientX;
        this.touchEndY = e.changedTouches[0].clientY;
        
        this.detectSwipe(e);
        this.detectTap(e);
    }

    /**
     * Detecta gestos de swipe
     */
    detectSwipe(e) {
        const deltaX = this.touchEndX - this.touchStartX;
        const deltaY = this.touchEndY - this.touchStartY;
        
        const absDeltaX = Math.abs(deltaX);
        const absDeltaY = Math.abs(deltaY);
        
        // Verificar se é um swipe válido
        if (Math.max(absDeltaX, absDeltaY) < this.swipeThreshold) {
            return;
        }
        
        let direction = '';
        
        if (absDeltaX > absDeltaY) {
            // Swipe horizontal
            direction = deltaX > 0 ? 'right' : 'left';
        } else {
            // Swipe vertical
            direction = deltaY > 0 ? 'down' : 'up';
        }
        
        this.handleSwipe(direction, e);
    }

    /**
     * Detecta taps (simples e duplo)
     */
    detectTap(e) {
        const deltaX = Math.abs(this.touchEndX - this.touchStartX);
        const deltaY = Math.abs(this.touchEndY - this.touchStartY);
        
        // Verificar se é um tap (movimento mínimo)
        if (deltaX < 10 && deltaY < 10) {
            if (this.tapTimeout) {
                // Double tap
                clearTimeout(this.tapTimeout);
                this.tapTimeout = null;
                this.handleDoubleTap(e);
            } else {
                // Single tap (com delay para detectar double tap)
                this.tapTimeout = setTimeout(() => {
                    this.handleTap(e);
                    this.tapTimeout = null;
                }, 300);
            }
        }
    }

    /**
     * Manipula evento de swipe
     */
    handleSwipe(direction, e) {
        const swipeEvent = new CustomEvent('swipe', {
            detail: {
                direction,
                target: e.target,
                startX: this.touchStartX,
                startY: this.touchStartY,
                endX: this.touchEndX,
                endY: this.touchEndY
            }
        });
        
        e.target.dispatchEvent(swipeEvent);
        
        // Log para debug
        console.log(`Swipe ${direction} detectado`, e.target);
    }

    /**
     * Manipula evento de tap
     */
    handleTap(e) {
        const tapEvent = new CustomEvent('tap', {
            detail: {
                target: e.target,
                x: this.touchEndX,
                y: this.touchEndY
            }
        });
        
        e.target.dispatchEvent(tapEvent);
    }

    /**
     * Manipula evento de double tap
     */
    handleDoubleTap(e) {
        const doubleTapEvent = new CustomEvent('doubleTap', {
            detail: {
                target: e.target,
                x: this.touchEndX,
                y: this.touchEndY
            }
        });
        
        e.target.dispatchEvent(doubleTapEvent);
        
        console.log('Double tap detectado', e.target);
    }

    /**
     * Manipula evento de long press
     */
    handleLongPress(target, e) {
        const longPressEvent = new CustomEvent('longPress', {
            detail: {
                target,
                x: this.touchStartX,
                y: this.touchStartY
            }
        });
        
        target.dispatchEvent(longPressEvent);
        
        // Haptic feedback se disponível
        if (navigator.vibrate) {
            navigator.vibrate(50);
        }
        
        console.log('Long press detectado', target);
    }

    // =============================================
    // MANIPULADORES DE TECLADO
    // =============================================

    /**
     * Inicializa manipuladores de teclado
     */
    initializeKeyboardHandlers() {
        document.addEventListener('keydown', (e) => {
            // Fechar modal com ESC
            if (e.key === 'Escape' && this.activeModal) {
                this.closeModal(this.activeModal);
            }
            
            // Navegação com Tab em modais
            if (e.key === 'Tab' && this.activeModal) {
                this.handleTabNavigation(e);
            }
        });
    }

    /**
     * Manipula navegação por Tab em modais
     */
    handleTabNavigation(e) {
        const modal = this.activeModal;
        const focusableElements = modal.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];
        
        if (e.shiftKey) {
            // Shift + Tab
            if (document.activeElement === firstElement) {
                e.preventDefault();
                lastElement.focus();
            }
        } else {
            // Tab
            if (document.activeElement === lastElement) {
                e.preventDefault();
                firstElement.focus();
            }
        }
    }

    // =============================================
    // MÉTODOS PÚBLICOS
    // =============================================

    /**
     * API pública para abrir modal
     */
    modal(action, modalId, data = {}) {
        if (action === 'open') {
            this.openModal(modalId, data);
        } else if (action === 'close') {
            if (modalId) {
                const modal = document.querySelector(`.modal-overlay[data-modal-id="${modalId}"]`);
                if (modal) this.closeModal(modal);
            } else {
                this.closeModal(this.activeModal);
            }
        }
    }

    /**
     * API pública para controlar pull-to-refresh
     */
    refresh(action) {
        if (action === 'finish') {
            this.finishRefresh();
        } else if (action === 'trigger') {
            this.triggerRefresh();
        }
    }

    /**
     * Adiciona listener para eventos de UI
     */
    on(eventType, callback) {
        document.addEventListener(eventType, callback);
    }

    /**
     * Remove listener para eventos de UI
     */
    off(eventType, callback) {
        document.removeEventListener(eventType, callback);
    }
}

// =============================================
// INICIALIZAÇÃO GLOBAL
// =============================================

// Instância global
let uiInteractions = null;

// Inicializar quando DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        uiInteractions = new UIInteractions();
        window.UI = uiInteractions; // Expor API globalmente
    });
} else {
    uiInteractions = new UIInteractions();
    window.UI = uiInteractions;
}

// Export para módulos ES6 se necessário
if (typeof module !== 'undefined' && module.exports) {
    module.exports = UIInteractions;
}