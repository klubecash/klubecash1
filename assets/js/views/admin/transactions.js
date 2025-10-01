/**
 * Modern Admin Transactions Management System
 * Enterprise-level JavaScript functionality
 */

class TransactionManager {
    constructor() {
        this.selectedTransactions = new Set();
        this.currentFilters = {};
        this.currentSort = { column: 'data_transacao', direction: 'desc' };
        this.currentPage = 1;
        this.itemsPerPage = 20;
        this.searchTimeout = null;
        
        this.init();
    }

    init() {
        this.bindEvents();
        this.initializeFilters();
        this.updateKPIs();
        this.loadTransactions();
    }

    bindEvents() {
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    this.handleSearch(e.target.value);
                }, 300);
            });
        }

        // Filter controls
        document.querySelectorAll('.form-select, .form-input').forEach(input => {
            input.addEventListener('change', () => this.applyFilters());
        });

        // Bulk actions
        document.getElementById('selectAll')?.addEventListener('change', (e) => {
            this.toggleSelectAll(e.target.checked);
        });

        document.getElementById('bulkApprove')?.addEventListener('click', () => {
            this.bulkAction('approve');
        });

        document.getElementById('bulkCancel')?.addEventListener('click', () => {
            this.bulkAction('cancel');
        });

        document.getElementById('bulkExport')?.addEventListener('click', () => {
            this.exportTransactions();
        });

        // Table sorting
        document.querySelectorAll('.data-table th[data-sort]').forEach(th => {
            th.addEventListener('click', () => {
                this.handleSort(th.dataset.sort);
            });
        });

        // Individual checkboxes
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('transaction-checkbox')) {
                this.toggleTransactionSelection(e.target.value, e.target.checked);
            }
        });

        // Action buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('action-btn')) {
                e.preventDefault();
                const action = e.target.dataset.action;
                const transactionId = e.target.dataset.id;
                this.handleTransactionAction(action, transactionId);
            }
        });

        // Modal controls
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-close') || e.target.classList.contains('modal')) {
                this.closeModal();
            }
        });

        // Escape key to close modal
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeModal();
            }
        });
    }

    handleSearch(query) {
        this.currentFilters.search = query;
        this.currentPage = 1;
        this.loadTransactions();
    }

    applyFilters() {
        const filters = {};
        
        // Date range
        const dateFrom = document.getElementById('dateFrom')?.value;
        const dateTo = document.getElementById('dateTo')?.value;
        if (dateFrom) filters.dateFrom = dateFrom;
        if (dateTo) filters.dateTo = dateTo;

        // Store filter
        const storeId = document.getElementById('storeFilter')?.value;
        if (storeId && storeId !== '') filters.storeId = storeId;

        // Status filter
        const status = document.getElementById('statusFilter')?.value;
        if (status && status !== '') filters.status = status;

        // Payment type filter
        const paymentType = document.getElementById('paymentFilter')?.value;
        if (paymentType && paymentType !== '') filters.paymentType = paymentType;

        // Amount range
        const amountMin = document.getElementById('amountMin')?.value;
        const amountMax = document.getElementById('amountMax')?.value;
        if (amountMin) filters.amountMin = parseFloat(amountMin);
        if (amountMax) filters.amountMax = parseFloat(amountMax);

        this.currentFilters = { ...this.currentFilters, ...filters };
        this.currentPage = 1;
        this.loadTransactions();
    }

    handleSort(column) {
        if (this.currentSort.column === column) {
            this.currentSort.direction = this.currentSort.direction === 'asc' ? 'desc' : 'asc';
        } else {
            this.currentSort.column = column;
            this.currentSort.direction = 'asc';
        }

        this.updateSortIcons();
        this.loadTransactions();
    }

    updateSortIcons() {
        document.querySelectorAll('.data-table th[data-sort] i').forEach(icon => {
            icon.className = 'fas fa-sort';
            icon.style.opacity = '0.5';
        });

        const currentHeader = document.querySelector(`[data-sort="${this.currentSort.column}"] i`);
        if (currentHeader) {
            currentHeader.className = `fas fa-sort-${this.currentSort.direction === 'asc' ? 'up' : 'down'}`;
            currentHeader.style.opacity = '1';
        }
    }

    toggleSelectAll(checked) {
        const checkboxes = document.querySelectorAll('.transaction-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = checked;
            if (checked) {
                this.selectedTransactions.add(checkbox.value);
            } else {
                this.selectedTransactions.delete(checkbox.value);
            }
        });
        this.updateBulkActions();
    }

    toggleTransactionSelection(transactionId, checked) {
        if (checked) {
            this.selectedTransactions.add(transactionId);
        } else {
            this.selectedTransactions.delete(transactionId);
        }
        this.updateBulkActions();
        this.updateSelectAll();
    }

    updateSelectAll() {
        const selectAllCheckbox = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.transaction-checkbox');
        const checkedBoxes = document.querySelectorAll('.transaction-checkbox:checked');
        
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = checkboxes.length > 0 && checkboxes.length === checkedBoxes.length;
            selectAllCheckbox.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < checkboxes.length;
        }
    }

    updateBulkActions() {
        const count = this.selectedTransactions.size;
        const bulkButtons = document.querySelectorAll('.bulk-actions .btn');
        
        bulkButtons.forEach(btn => {
            btn.disabled = count === 0;
            if (count > 0) {
                const countSpan = btn.querySelector('.count');
                if (countSpan) {
                    countSpan.textContent = `(${count})`;
                }
            }
        });
    }

    async bulkAction(action) {
        if (this.selectedTransactions.size === 0) {
            this.showNotification('Selecione pelo menos uma transação', 'warning');
            return;
        }

        const actionText = {
            approve: 'aprovar',
            cancel: 'cancelar'
        }[action];

        if (!confirm(`Tem certeza que deseja ${actionText} ${this.selectedTransactions.size} transação(ões)?`)) {
            return;
        }

        try {
            this.showLoading(true);
            
            const response = await fetch('transactions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: `bulk_${action}`,
                    transactions: Array.from(this.selectedTransactions)
                })
            });

            const result = await response.json();
            
            if (result.success) {
                this.showNotification(`${this.selectedTransactions.size} transação(ões) ${actionText}da(s) com sucesso!`, 'success');
                this.selectedTransactions.clear();
                this.loadTransactions();
                this.updateKPIs();
            } else {
                throw new Error(result.message || 'Erro ao processar ação em lote');
            }
        } catch (error) {
            this.showNotification(`Erro: ${error.message}`, 'error');
        } finally {
            this.showLoading(false);
        }
    }

    async handleTransactionAction(action, transactionId) {
        const actions = {
            view: () => this.viewTransaction(transactionId),
            edit: () => this.editTransaction(transactionId),
            approve: () => this.approveTransaction(transactionId),
            cancel: () => this.cancelTransaction(transactionId),
            delete: () => this.deleteTransaction(transactionId)
        };

        if (actions[action]) {
            await actions[action]();
        }
    }

    async viewTransaction(transactionId) {
        try {
            this.showLoading(true);
            
            const response = await fetch(`transactions.php?action=get_transaction&id=${transactionId}`);
            const transaction = await response.json();
            
            if (transaction.success) {
                this.showTransactionModal(transaction.data);
            } else {
                throw new Error(transaction.message || 'Erro ao carregar transação');
            }
        } catch (error) {
            this.showNotification(`Erro: ${error.message}`, 'error');
        } finally {
            this.showLoading(false);
        }
    }

    async approveTransaction(transactionId) {
        if (!confirm('Tem certeza que deseja aprovar esta transação?')) {
            return;
        }

        try {
            this.showLoading(true);
            
            const response = await fetch('transactions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'approve',
                    transaction_id: transactionId
                })
            });

            const result = await response.json();
            
            if (result.success) {
                this.showNotification('Transação aprovada com sucesso!', 'success');
                this.loadTransactions();
                this.updateKPIs();
            } else {
                throw new Error(result.message || 'Erro ao aprovar transação');
            }
        } catch (error) {
            this.showNotification(`Erro: ${error.message}`, 'error');
        } finally {
            this.showLoading(false);
        }
    }

    async cancelTransaction(transactionId) {
        if (!confirm('Tem certeza que deseja cancelar esta transação?')) {
            return;
        }

        try {
            this.showLoading(true);
            
            const response = await fetch('transactions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'cancel',
                    transaction_id: transactionId
                })
            });

            const result = await response.json();
            
            if (result.success) {
                this.showNotification('Transação cancelada com sucesso!', 'success');
                this.loadTransactions();
                this.updateKPIs();
            } else {
                throw new Error(result.message || 'Erro ao cancelar transação');
            }
        } catch (error) {
            this.showNotification(`Erro: ${error.message}`, 'error');
        } finally {
            this.showLoading(false);
        }
    }

    async exportTransactions() {
        try {
            this.showLoading(true);
            
            const params = new URLSearchParams({
                action: 'export',
                format: 'excel',
                ...this.currentFilters
            });

            if (this.selectedTransactions.size > 0) {
                params.set('selected', Array.from(this.selectedTransactions).join(','));
            }

            const response = await fetch(`transactions.php?${params}`);
            
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `transacoes_${new Date().toISOString().split('T')[0]}.xlsx`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                this.showNotification('Exportação realizada com sucesso!', 'success');
            } else {
                throw new Error('Erro ao exportar transações');
            }
        } catch (error) {
            this.showNotification(`Erro: ${error.message}`, 'error');
        } finally {
            this.showLoading(false);
        }
    }

    async loadTransactions() {
        try {
            this.showLoading(true);
            
            const params = new URLSearchParams({
                action: 'list',
                page: this.currentPage,
                per_page: this.itemsPerPage,
                sort_column: this.currentSort.column,
                sort_direction: this.currentSort.direction,
                ...this.currentFilters
            });

            const response = await fetch(`transactions.php?${params}`);
            const result = await response.json();
            
            if (result.success) {
                this.renderTransactions(result.data);
                this.renderPagination(result.pagination);
            } else {
                throw new Error(result.message || 'Erro ao carregar transações');
            }
        } catch (error) {
            this.showNotification(`Erro: ${error.message}`, 'error');
        } finally {
            this.showLoading(false);
        }
    }

    renderTransactions(transactions) {
        const tbody = document.querySelector('.data-table tbody');
        if (!tbody) return;

        tbody.innerHTML = transactions.map(transaction => `
            <tr>
                <td>
                    <div class="custom-checkbox">
                        <input type="checkbox" class="transaction-checkbox" value="${transaction.id}">
                        <span class="checkmark"></span>
                    </div>
                </td>
                <td>#${transaction.id}</td>
                <td>
                    <div class="user-info">
                        <strong>${transaction.cliente_nome}</strong>
                        <small>${transaction.cliente_email}</small>
                    </div>
                </td>
                <td>${transaction.loja_nome}</td>
                <td>R$ ${parseFloat(transaction.valor).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</td>
                <td>R$ ${parseFloat(transaction.cashback_valor).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</td>
                <td><span class="status-badge ${transaction.status.toLowerCase()}">${this.getStatusText(transaction.status)}</span></td>
                <td>${this.formatDate(transaction.data_transacao)}</td>
                <td>
                    <div class="transaction-actions">
                        <button class="action-btn view" data-action="view" data-id="${transaction.id}" title="Visualizar">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${transaction.status === 'pending' ? `
                            <button class="action-btn edit" data-action="approve" data-id="${transaction.id}" title="Aprovar">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="action-btn delete" data-action="cancel" data-id="${transaction.id}" title="Cancelar">
                                <i class="fas fa-times"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `).join('');

        this.updateSelectAll();
        this.updateBulkActions();
    }

    async updateKPIs() {
        try {
            const response = await fetch('transactions.php?action=get_kpis');
            const result = await response.json();
            
            if (result.success) {
                const kpis = result.data;
                
                // Update KPI values
                document.getElementById('totalTransactions').textContent = kpis.total_transactions.toLocaleString('pt-BR');
                document.getElementById('totalValue').textContent = `R$ ${kpis.total_value.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
                document.getElementById('totalCashback').textContent = `R$ ${kpis.total_cashback.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
                document.getElementById('pendingTransactions').textContent = kpis.pending_count.toLocaleString('pt-BR');
                document.getElementById('avgTransaction').textContent = `R$ ${kpis.avg_transaction.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
                document.getElementById('conversionRate').textContent = `${kpis.conversion_rate.toFixed(1)}%`;
                
                // Update trends
                this.updateTrends(kpis.trends);
            }
        } catch (error) {
            console.error('Erro ao atualizar KPIs:', error);
        }
    }

    updateTrends(trends) {
        Object.keys(trends).forEach(kpi => {
            const element = document.getElementById(`${kpi}Trend`);
            if (element) {
                const trend = trends[kpi];
                element.className = `kpi-change ${trend.direction}`;
                element.innerHTML = `
                    <i class="fas fa-arrow-${trend.direction === 'positive' ? 'up' : 'down'}"></i>
                    ${Math.abs(trend.percentage).toFixed(1)}%
                `;
            }
        });
    }

    showTransactionModal(transaction) {
        const modal = document.getElementById('transactionModal');
        if (!modal) return;

        const modalContent = modal.querySelector('.modal-content');
        modalContent.innerHTML = `
            <div class="modal-header">
                <h2 class="modal-title">Detalhes da Transação #${transaction.id}</h2>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="detail-grid">
                    <div class="detail-section">
                        <h3>Informações da Transação</h3>
                        <div class="detail-item">
                            <span class="detail-label">ID:</span>
                            <span class="detail-value">#${transaction.id}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Data:</span>
                            <span class="detail-value">${this.formatDate(transaction.data_transacao)}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value">
                                <span class="status-badge ${transaction.status.toLowerCase()}">${this.getStatusText(transaction.status)}</span>
                            </span>
                        </div>
                    </div>
                    <div class="detail-section">
                        <h3>Informações Financeiras</h3>
                        <div class="detail-item">
                            <span class="detail-label">Valor da Compra:</span>
                            <span class="detail-value">R$ ${parseFloat(transaction.valor).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Cashback:</span>
                            <span class="detail-value">R$ ${parseFloat(transaction.cashback_valor).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Percentual:</span>
                            <span class="detail-value">${transaction.porcentagem_cashback}%</span>
                        </div>
                    </div>
                </div>
            </div>
        `;

        modal.style.display = 'flex';
        modal.classList.add('active');
    }

    closeModal() {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.style.display = 'none';
            modal.classList.remove('active');
        });
    }

    getStatusText(status) {
        const statusTexts = {
            'pending': 'Pendente',
            'processing': 'Processando',
            'completed': 'Concluída',
            'cancelled': 'Cancelada'
        };
        return statusTexts[status.toLowerCase()] || status;
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }

    initializeFilters() {
        // Set default date range (last 30 days)
        const today = new Date();
        const thirtyDaysAgo = new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000));
        
        const dateFromInput = document.getElementById('dateFrom');
        const dateToInput = document.getElementById('dateTo');
        
        if (dateFromInput) dateFromInput.value = thirtyDaysAgo.toISOString().split('T')[0];
        if (dateToInput) dateToInput.value = today.toISOString().split('T')[0];
    }

    showLoading(show) {
        const loader = document.getElementById('loadingIndicator');
        if (loader) {
            loader.style.display = show ? 'block' : 'none';
        }
        
        // Disable/enable interactive elements
        const interactiveElements = document.querySelectorAll('button, input, select');
        interactiveElements.forEach(element => {
            element.disabled = show;
        });
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${this.getNotificationIcon(type)}"></i>
                <span>${message}</span>
            </div>
            <button class="notification-close">&times;</button>
        `;

        // Add to page
        document.body.appendChild(notification);

        // Position and show
        setTimeout(() => notification.classList.add('show'), 100);

        // Auto remove
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 5000);

        // Close button
        notification.querySelector('.notification-close').addEventListener('click', () => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        });
    }

    getNotificationIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    renderPagination(pagination) {
        const paginationContainer = document.getElementById('pagination');
        if (!paginationContainer || !pagination) return;

        const { currentPage, totalPages, hasNext, hasPrev } = pagination;
        
        let paginationHTML = `
            <button class="pagination-btn" ${!hasPrev ? 'disabled' : ''} onclick="transactionManager.goToPage(${currentPage - 1})">
                <i class="fas fa-chevron-left"></i> Anterior
            </button>
        `;

        // Page numbers
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);

        if (startPage > 1) {
            paginationHTML += `<button class="pagination-btn" onclick="transactionManager.goToPage(1)">1</button>`;
            if (startPage > 2) {
                paginationHTML += `<span class="pagination-dots">...</span>`;
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            paginationHTML += `
                <button class="pagination-btn ${i === currentPage ? 'active' : ''}" 
                        onclick="transactionManager.goToPage(${i})">${i}</button>
            `;
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                paginationHTML += `<span class="pagination-dots">...</span>`;
            }
            paginationHTML += `<button class="pagination-btn" onclick="transactionManager.goToPage(${totalPages})">${totalPages}</button>`;
        }

        paginationHTML += `
            <button class="pagination-btn" ${!hasNext ? 'disabled' : ''} onclick="transactionManager.goToPage(${currentPage + 1})">
                Próximo <i class="fas fa-chevron-right"></i>
            </button>
        `;

        paginationContainer.innerHTML = paginationHTML;
    }

    goToPage(page) {
        this.currentPage = page;
        this.loadTransactions();
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.transactionManager = new TransactionManager();
});

// Add notification styles dynamically
const notificationStyles = `
<style>
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    padding: 16px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-width: 300px;
    transform: translateX(400px);
    opacity: 0;
    transition: all 0.3s ease;
    z-index: 10000;
    border-left: 4px solid;
}

.notification.show {
    transform: translateX(0);
    opacity: 1;
}

.notification-success {
    border-left-color: #10b981;
}

.notification-error {
    border-left-color: #ef4444;
}

.notification-warning {
    border-left-color: #f59e0b;
}

.notification-info {
    border-left-color: #3b82f6;
}

.notification-content {
    display: flex;
    align-items: center;
    gap: 12px;
}

.notification-content i {
    font-size: 18px;
}

.notification-success i {
    color: #10b981;
}

.notification-error i {
    color: #ef4444;
}

.notification-warning i {
    color: #f59e0b;
}

.notification-info i {
    color: #3b82f6;
}

.notification-close {
    background: none;
    border: none;
    font-size: 20px;
    color: #94a3b8;
    cursor: pointer;
    padding: 0;
    margin-left: 16px;
}

.notification-close:hover {
    color: #64748b;
}

.pagination-dots {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem;
    color: var(--text-muted);
}

#loadingIndicator {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #2563eb;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>
`;

document.head.insertAdjacentHTML('beforeend', notificationStyles);