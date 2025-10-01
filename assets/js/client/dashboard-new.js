// assets/js/client/dashboard-new.js

// Animações e interatividade do dashboard
document.addEventListener('DOMContentLoaded', function() {
    
    // Animar números dos cards ao carregar
    animateNumbers();
    
    // Adicionar tooltips informativos
    initializeTooltips();
    
    // Smooth scroll para links internos
    initializeSmoothScroll();
    
    // Atualizar dados dinamicamente (opcional)
    // setInterval(updateDashboardData, 300000); // A cada 5 minutos
});

/**
 * Anima os números dos valores nos cards principais
 */
function animateNumbers() {
    const numberElements = document.querySelectorAll('.amount');
    
    numberElements.forEach(element => {
        const finalValue = parseFloat(element.textContent.replace(/[^\d,]/g, '').replace(',', '.'));
        if (isNaN(finalValue)) return;
        
        let currentValue = 0;
        const increment = finalValue / 50; // 50 steps para a animação
        const timer = setInterval(() => {
            currentValue += increment;
            if (currentValue >= finalValue) {
                currentValue = finalValue;
                clearInterval(timer);
            }
            
            element.textContent = formatCurrency(currentValue);
        }, 20);
    });
}

/**
 * Formata valor como moeda brasileira
 */
function formatCurrency(value) {
    return value.toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

/**
 * Inicializa tooltips explicativos
 */
function initializeTooltips() {
    const tooltips = document.querySelectorAll('[title]');
    
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

/**
 * Mostra tooltip personalizado
 */
function showTooltip(event) {
    const tooltip = document.createElement('div');
    tooltip.className = 'custom-tooltip';
    tooltip.textContent = event.target.getAttribute('title');
    
    // Remove o title para não mostrar o tooltip padrão
    event.target.setAttribute('data-title', event.target.getAttribute('title'));
    event.target.removeAttribute('title');
    
    document.body.appendChild(tooltip);
    
    // Posiciona o tooltip
    const rect = event.target.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
    
    // Anima a entrada
    setTimeout(() => tooltip.classList.add('show'), 10);
}

/**
 * Esconde tooltip personalizado
 */
function hideTooltip(event) {
    const tooltip = document.querySelector('.custom-tooltip');
    if (tooltip) {
        tooltip.remove();
    }
    
    // Restaura o title
    if (event.target.getAttribute('data-title')) {
        event.target.setAttribute('title', event.target.getAttribute('data-title'));
        event.target.removeAttribute('data-title');
    }
}

/**
 * Inicializa smooth scroll para links internos
 */
function initializeSmoothScroll() {
    const links = document.querySelectorAll('a[href^="#"]');
    
    links.forEach(link => {
        link.addEventListener('click', function(e) {
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
}

/**
 * Atualiza dados do dashboard dinamicamente (opcional)
 */
function updateDashboardData() {
    // Esta função pode ser usada para atualizar dados sem recarregar a página
    console.log('Verificando atualizações...');
    
    // Exemplo de como buscar dados atualizados:
    /*
    fetch('/api/dashboard-update')
        .then(response => response.json())
        .then(data => {
            if (data.needsUpdate) {
                // Atualizar elementos específicos
                updateBalanceDisplay(data.newBalance);
                updatePendingAmount(data.newPending);
            }
        })
        .catch(error => console.log('Erro ao atualizar dados:', error));
    */
}

/**
 * Atualiza exibição do saldo (para uso com atualizações dinâmicas)
 */
function updateBalanceDisplay(newBalance) {
    const balanceElement = document.querySelector('.main-balance .amount');
    if (balanceElement) {
        // Anima a mudança do valor
        balanceElement.style.transition = 'all 0.3s ease';
        balanceElement.style.transform = 'scale(1.1)';
        
        setTimeout(() => {
            balanceElement.textContent = formatCurrency(newBalance);
            balanceElement.style.transform = 'scale(1)';
        }, 150);
    }
}

/**
 * Feedback visual para ações do usuário
 */
function showSuccessMessage(message) {
    const notification = document.createElement('div');
    notification.className = 'success-notification';
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Adicionar estilos para tooltips e notificações
const style = document.createElement('style');
style.textContent = `
    .custom-tooltip {
        position: absolute;
        background: rgba(0, 0, 0, 0.9);
        color: white;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 0.8rem;
        z-index: 1000;
        opacity: 0;
        transform: translateY(5px);
        transition: all 0.2s ease;
        pointer-events: none;
        white-space: nowrap;
    }
    
    .custom-tooltip.show {
        opacity: 1;
        transform: translateY(0);
    }
    
    .custom-tooltip::after {
        content: '';
        position: absolute;
        top: 100%;
        left: 50%;
        transform: translateX(-50%);
        border: 5px solid transparent;
        border-top-color: rgba(0, 0, 0, 0.9);
    }
    
    .success-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: var(--success-color);
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        font-weight: 600;
        z-index: 1000;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
    }
    
    .success-notification.show {
        opacity: 1;
        transform: translateX(0);
    }
`;
document.head.appendChild(style);