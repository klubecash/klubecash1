// assets/js/client/balance-new.js

// Inicialização quando a página carrega
document.addEventListener('DOMContentLoaded', function() {
    console.log('💰 Página de saldos carregada');
    
    // Inicializar todas as funcionalidades
    initializeAnimations();
    initializeChart();
    initializeTooltips();
    initializeModal();
    
    // Adicionar listeners para interatividade
    addInteractivity();
});

/**
 * Inicializa as animações dos números e elementos
 */
function initializeAnimations() {
    // Animar números dos valores principais
    const amountElements = document.querySelectorAll('.amount[data-value]'); //
    
    amountElements.forEach(element => {
        const finalValue = parseFloat(element.getAttribute('data-value') || element.textContent.replace(/[^\d,]/g, '').replace(',', '.')); //
        if (isNaN(finalValue)) return;
        
        // ---- CORRECTION ----
        // Pass true for the isCurrency parameter for .amount elements
        animateNumber(element, 0, finalValue, 1500, true); // 
        // ---- END CORRECTION ----
    });
    
    // Animar cards de estatísticas
    const statNumbers = document.querySelectorAll('.stat-number'); //
    
    statNumbers.forEach(element => {
        const text = element.textContent.trim();
        const number = parseFloat(text.replace(/[^\d,]/g, '').replace(',', '.'));
        
        if (!isNaN(number)) {
            animateNumber(element, 0, number, 1000, text.includes('R$')); //
        }
    });
}

/**
 * Anima um número de um valor inicial até um valor final
 */
function animateNumber(element, startValue, endValue, duration, isCurrency = false) { //
    const startTime = performance.now();
    const difference = endValue - startValue;
    
    function updateNumber(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        // Usar easing para animação mais suave
        const easedProgress = easeOutQuart(progress); //
        const currentValue = startValue + (difference * easedProgress);
        
        if (isCurrency) { //
            element.textContent = formatCurrency(currentValue); //
        } else {
            element.textContent = Math.round(currentValue).toString(); //
        }
        
        if (progress < 1) {
            requestAnimationFrame(updateNumber);
        }
    }
    
    requestAnimationFrame(updateNumber);
}

/**
 * Função de easing para animações mais suaves
 */
function easeOutQuart(t) { //
    return 1 - Math.pow(1 - t, 4);
}

/**
 * Formata valor como moeda brasileira
 */
function formatCurrency(value) { //
    return value.toLocaleString('pt-BR', { //
        minimumFractionDigits: 2, //
        maximumFractionDigits: 2 //
    });
}

/**
 * Inicializa o gráfico de evolução dos saldos
 */
function initializeChart() {
    const chartCanvas = document.getElementById('balanceChart');
    if (!chartCanvas) return;
    
    // Dados do PHP convertidos para JavaScript (mantendo lógica original)
    const chartData = window.chartData || {
        labels: [],
        creditos: [],
        usos: []
    };
    
    if (chartData.labels.length === 0) {
        chartCanvas.parentElement.innerHTML = '<p style="text-align: center; color: #6B7280; padding: 40px;">Não há dados suficientes para exibir o gráfico</p>';
        return;
    }
    
    new Chart(chartCanvas, {
        type: 'line',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'Cashback Recebido',
                data: chartData.creditos,
                borderColor: '#10B981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#10B981',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }, {
                label: 'Saldo Usado',
                data: chartData.usos,
                borderColor: '#F59E0B',
                backgroundColor: 'rgba(245, 158, 11, 0.1)',
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#F59E0B',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    display: false // Usando legenda customizada
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: '#4B5563',
                    borderWidth: 1,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': R$ ' + 
                                   context.parsed.y.toLocaleString('pt-BR', {
                                       minimumFractionDigits: 2,
                                       maximumFractionDigits: 2
                                   });
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        color: '#F3F4F6',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#6B7280',
                        font: {
                            size: 12
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#F3F4F6',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#6B7280',
                        font: {
                            size: 12
                        },
                        callback: function(value) {
                            return 'R$ ' + value.toLocaleString('pt-BR', {
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0
                            });
                        }
                    }
                }
            },
            elements: {
                line: {
                    borderWidth: 3
                },
                point: {
                    hoverBorderWidth: 3
                }
            }
        }
    });
}

/**
 * Inicializa tooltips informativos
 */
function initializeTooltips() {
    // Adicionar tooltips para elementos que precisam de explicação
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

/**
 * Inicializa o modal de detalhes da loja (mantendo funcionalidade original)
 */
function initializeModal() {
    const modal = document.getElementById('storeDetailsModal');
    if (!modal) return;
    
    // Fechar modal ao clicar fora
    window.onclick = function(event) {
        if (event.target === modal) {
            closeStoreModal();
        }
    };
    
    // Fechar modal com ESC
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && modal.style.display === 'block') {
            closeStoreModal();
        }
    });
}

/**
 * Adiciona interatividade aos elementos da página
 */
function addInteractivity() {
    // Adicionar efeitos hover aos cards
    const cards = document.querySelectorAll('.summary-card, .store-card, .stat-card');
    
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-4px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Smooth scroll para links internos
    const internalLinks = document.querySelectorAll('a[href^="#"]');
    
    internalLinks.forEach(link => {
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
 * Função para visualizar detalhes da loja (mantendo lógica original)
 */
function viewStoreDetails(storeId) {
    console.log('Abrindo detalhes da loja:', storeId);
    
    const modal = document.getElementById('storeDetailsModal');
    const modalTitle = document.getElementById('modalStoreTitle');
    const modalContent = document.getElementById('modalStoreContent');
    
    if (!modal || !modalTitle || !modalContent) {
        console.error('Elementos do modal não encontrados');
        return;
    }
    
    // Mostrar modal e loading
    modal.style.display = 'block';
    modalTitle.textContent = 'Carregando...';
    modalContent.innerHTML = '<div class="loading-state">🔄 Buscando informações da loja...</div>';
    
    // Fazer requisição para obter detalhes (mantendo URL original)
    fetch(`../../controllers/client_actions.php?action=store_balance_details&loja_id=${storeId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Dados recebidos:', data);
            
            if (data.status) {
                renderStoreDetails(data.data);
            } else {
                modalContent.innerHTML = `<div class="error-state">❌ ${data.message}</div>`;
            }
        })
        .catch(error => {
            console.error('Erro ao carregar detalhes:', error);
            modalContent.innerHTML = `<div class="error-state">⚠️ Erro ao carregar informações da loja</div>`;
        });
}

/**
 * Renderiza os detalhes da loja no modal
 */
function renderStoreDetails(data) {
    const modalTitle = document.getElementById('modalStoreTitle');
    const modalContent = document.getElementById('modalStoreContent');
    
    modalTitle.textContent = data.loja.nome_fantasia;
    
    const logoHtml = data.loja.logo ? 
        `<img src="../../uploads/store_logos/${data.loja.logo}" alt="${data.loja.nome_fantasia}" class="modal-store-logo">` : 
        `<div class="modal-store-initial">${data.loja.nome_fantasia.charAt(0).toUpperCase()}</div>`;
    
    const html = `
        <div class="modal-store-details">
            <div class="modal-store-header">
                ${logoHtml}
                <div class="modal-store-info">
                    <h4>${data.loja.nome_fantasia}</h4>
                    <p class="store-category">${data.loja.categoria || 'Categoria não informada'}</p>
                    <div class="cashback-info">
                        Você ganha <strong>${(parseFloat(data.loja.porcentagem_cashback) / 2).toFixed(1)}%</strong> de cashback
                    </div>
                </div>
            </div>
            
            <div class="modal-balance-summary">
                <div class="balance-item">
                    <span class="balance-label">💰 Disponível para usar</span>
                    <span class="balance-value">R$ ${parseFloat(data.saldo.saldo_disponivel || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
                </div>
                <div class="balance-item">
                    <span class="balance-label">📈 Total recebido</span>
                    <span class="balance-value">R$ ${parseFloat(data.saldo.total_creditado || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
                </div>
                <div class="balance-item">
                    <span class="balance-label">🛍️ Total usado</span>
                    <span class="balance-value">R$ ${parseFloat(data.saldo.total_usado || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
                </div>
            </div>
            
            ${data.movimentacoes && data.movimentacoes.length > 0 ? `
            <div class="modal-recent-activities">
                <h5>📊 Atividades Recentes</h5>
                <div class="modal-activities-list">
                    ${data.movimentacoes.slice(0, 5).map(mov => `
                        <div class="modal-activity-item">
                            <span class="activity-icon-small">
                                ${mov.tipo_operacao === 'credito' ? '💰' : 
                                  mov.tipo_operacao === 'uso' ? '🛒' : '↩️'}
                            </span>
                            <div class="activity-details-small">
                                <span class="activity-desc">
                                    ${mov.tipo_operacao === 'credito' ? 'Cashback recebido' : 
                                      mov.tipo_operacao === 'uso' ? 'Saldo usado' : 'Estorno'}
                                </span>
                                <span class="activity-date-small">${formatDateTime(mov.data_operacao)}</span>
                            </div>
                            <span class="activity-amount-small ${mov.tipo_operacao === 'uso' ? 'negative' : 'positive'}">
                                ${mov.tipo_operacao === 'uso' ? '-' : '+'}R$ ${parseFloat(mov.valor).toLocaleString('pt-BR', {minimumFractionDigits: 2})}
                            </span>
                        </div>
                    `).join('')}
                </div>
            </div>
            ` : '<p class="no-activities">Ainda não há atividades nesta loja</p>'}
            
            ${data.loja.website ? `
            <div class="modal-actions">
                <a href="${data.loja.website}" target="_blank" class="visit-store-btn">
                    🌐 Visitar Site da Loja
                </a>
            </div>
            ` : ''}
        </div>
    `;
    
    modalContent.innerHTML = html;
}

/**
 * Fecha o modal de detalhes
 */
function closeStoreModal() {
    const modal = document.getElementById('storeDetailsModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Formata data e hora para exibição
 */
function formatDateTime(datetime) {
    if (!datetime) return 'Data não informada';
    const date = new Date(datetime);
    return date.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Função para notificações de sucesso
 */
function showSuccessNotification(message) {
    const notification = document.createElement('div');
    notification.className = 'success-notification';
    notification.innerHTML = `
        <span class="notification-icon">✅</span>
        <span class="notification-message">${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    // Animar entrada
    setTimeout(() => notification.classList.add('show'), 10);
    
    // Remover após 4 segundos
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}

/**
 * Função para notificações de erro
 */
function showErrorNotification(message) {
    const notification = document.createElement('div');
    notification.className = 'error-notification';
    notification.innerHTML = `
        <span class="notification-icon">❌</span>
        <span class="notification-message">${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => notification.classList.add('show'), 10);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}

// Estilos para notificações e modal
const additionalStyles = `
<style>
.loading-state, .error-state {
    text-align: center;
    padding: 40px;
    font-size: 1.1rem;
    color: var(--text-muted);
}

.error-state {
    color: var(--danger-color);
}

.modal-store-details {
    max-height: 70vh;
    overflow-y: auto;
}

.modal-store-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border-light);
}

.modal-store-logo {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    object-fit: cover;
}

.modal-store-initial {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    font-size: 1.5rem;
    font-weight: 700;
}

.modal-store-info h4 {
    margin: 0 0 4px 0;
    color: var(--text-primary);
}

.cashback-info {
    color: var(--success-color);
    font-size: 0.9rem;
    font-weight: 600;
}

.modal-balance-summary {
    background: var(--bg-secondary);
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 24px;
}

.balance-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.balance-item:last-child {
    margin-bottom: 0;
}

.balance-label {
    color: var(--text-muted);
    font-size: 0.9rem;
}

.balance-value {
    font-weight: 700;
    color: var(--primary-color);
}

.modal-recent-activities h5 {
    margin-bottom: 16px;
    color: var(--text-primary);
}

.modal-activities-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.modal-activity-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: var(--bg-secondary);
    border-radius: 8px;
}

.activity-icon-small {
    font-size: 1.2rem;
}

.activity-details-small {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.activity-desc {
    font-weight: 500;
    color: var(--text-primary);
    font-size: 0.9rem;
}

.activity-date-small {
    font-size: 0.8rem;
    color: var(--text-muted);
}

.activity-amount-small {
    font-weight: 700;
    font-size: 0.9rem;
}

.activity-amount-small.positive {
    color: var(--success-color);
}

.activity-amount-small.negative {
    color: var(--danger-color);
}

.no-activities {
    text-align: center;
    padding: 20px;
    color: var(--text-muted);
    font-style: italic;
}

.modal-actions {
    text-align: center;
    margin-top: 24px;
    padding-top: 16px;
    border-top: 1px solid var(--border-light);
}

.visit-store-btn {
    background: var(--primary-color);
    color: white;
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    display: inline-block;
    transition: background-color 0.3s ease;
}

.visit-store-btn:hover {
    background: var(--primary-dark);
}

.success-notification, .error-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: var(--bg-primary);
    border: 2px solid;
    padding: 16px 20px;
    border-radius: 12px;
    box-shadow: var(--shadow-xl);
    z-index: 2000;
    display: flex;
    align-items: center;
    gap: 12px;
    max-width: 400px;
    opacity: 0;
    transform: translateX(100%);
    transition: all 0.3s ease;
}

.success-notification {
    border-color: var(--success-color);
}

.error-notification {
    border-color: var(--danger-color);
}

.success-notification.show, .error-notification.show {
    opacity: 1;
    transform: translateX(0);
}

.notification-icon {
    font-size: 1.2rem;
}

.notification-message {
    font-weight: 600;
    color: var(--text-primary);
}

@media (max-width: 768px) {
    .modal-store-header {
        flex-direction: column;
        text-align: center;
    }
    
    .modal-activity-item {
        flex-direction: column;
        text-align: center;
        gap: 8px;
    }
    
    .activity-details-small {
        align-items: center;
    }
    
    .success-notification, .error-notification {
        right: 10px;
        left: 10px;
        max-width: none;
        transform: translateY(-100%);
    }
    
    .success-notification.show, .error-notification.show {
        transform: translateY(0);
    }
}
</style>
`;
// The showTooltip and hideTooltip functions were missing in the provided code.
// Adding them here as placeholders if they are needed, or they can be removed if not used.
function showTooltip(event) {
    // Placeholder: Implement tooltip display logic
    const tooltipText = event.target.getAttribute('data-tooltip');
    if (tooltipText) {
        // Example: Create and show a simple tooltip
        const tooltip = document.createElement('div');
        tooltip.className = 'simple-tooltip';
        tooltip.textContent = tooltipText;
        document.body.appendChild(tooltip);
        const rect = event.target.getBoundingClientRect();
        tooltip.style.left = `${rect.left + window.scrollX}px`;
        tooltip.style.top = `${rect.bottom + window.scrollY + 5}px`; // Position below the element
        event.target.tooltipElement = tooltip; // Store reference
    }
}

function hideTooltip(event) {
    // Placeholder: Implement tooltip hiding logic
    if (event.target.tooltipElement) {
        event.target.tooltipElement.remove();
        event.target.tooltipElement = null;
    }
}