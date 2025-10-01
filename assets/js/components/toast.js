// assets/js/components/toast.js
class KlubeToast {
    constructor() {
        this.container = null;
        this.init();
    }

    init() {
        // Criar container se não existir
        if (!document.querySelector('.toast-container')) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        } else {
            this.container = document.querySelector('.toast-container');
        }
    }

    show(message, type = 'info', options = {}) {
        const defaultOptions = {
            title: this.getDefaultTitle(type),
            duration: 5000,
            closable: true,
            progress: true
        };

        const config = { ...defaultOptions, ...options };

        // Criar elemento do toast
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;

        // HTML interno do toast
        toast.innerHTML = `
            <div class="toast-icon">${this.getIcon(type)}</div>
            <div class="toast-content">
                <div class="toast-title">${config.title}</div>
                <div class="toast-message">${message}</div>
            </div>
            ${config.closable ? '<button class="toast-close" type="button">&times;</button>' : ''}
            ${config.progress ? '<div class="toast-progress"></div>' : ''}
        `;

        // Adicionar ao container
        this.container.appendChild(toast);

        // Configurar barra de progresso
        const progressBar = toast.querySelector('.toast-progress');
        if (progressBar && config.duration > 0) {
            progressBar.style.width = '100%';
            progressBar.style.transitionDuration = `${config.duration}ms`;
            
            // Animar progresso
            setTimeout(() => {
                progressBar.style.width = '0%';
            }, 10);
        }

        // Configurar botão de fechar
        const closeBtn = toast.querySelector('.toast-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                this.hide(toast);
            });
        }

        // Mostrar toast
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);

        // Auto remover
        if (config.duration > 0) {
            setTimeout(() => {
                this.hide(toast);
            }, config.duration);
        }

        // Pausar progresso no hover
        if (progressBar) {
            toast.addEventListener('mouseenter', () => {
                progressBar.style.animationPlayState = 'paused';
            });

            toast.addEventListener('mouseleave', () => {
                progressBar.style.animationPlayState = 'running';
            });
        }

        return toast;
    }

    hide(toast) {
        toast.classList.add('hide');
        toast.classList.remove('show');
        
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }

    getIcon(type) {
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        return icons[type] || icons.info;
    }

    getDefaultTitle(type) {
        const titles = {
            success: 'Sucesso!',
            error: 'Erro!',
            warning: 'Atenção!',
            info: 'Informação'
        };
        return titles[type] || titles.info;
    }

    // Métodos de conveniência
    success(message, options = {}) {
        return this.show(message, 'success', options);
    }

    error(message, options = {}) {
        return this.show(message, 'error', options);
    }

    warning(message, options = {}) {
        return this.show(message, 'warning', options);
    }

    info(message, options = {}) {
        return this.show(message, 'info', options);
    }
}

// Classe para gerenciar spinner
class KlubeSpinner {
    constructor() {
        this.overlay = null;
        this.init();
    }

    init() {
        // Criar overlay do spinner se não existir
        if (!document.querySelector('.spinner-overlay')) {
            this.overlay = document.createElement('div');
            this.overlay.className = 'spinner-overlay';
            this.overlay.innerHTML = '<span class="loader"></span>';
            document.body.appendChild(this.overlay);
        } else {
            this.overlay = document.querySelector('.spinner-overlay');
        }
    }

    show() {
        this.overlay.classList.add('show');
        document.body.style.overflow = 'hidden'; // Prevenir scroll
    }

    hide() {
        this.overlay.classList.remove('show');
        document.body.style.overflow = ''; // Restaurar scroll
    }
}

// Instâncias globais
window.KlubeToast = new KlubeToast();
window.KlubeSpinner = new KlubeSpinner();

// Compatibilidade com código existente
window.showToast = (message, type = 'info', options = {}) => {
    return window.KlubeToast.show(message, type, options);
};

window.showSpinner = () => window.KlubeSpinner.show();
window.hideSpinner = () => window.KlubeSpinner.hide();