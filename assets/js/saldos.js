// assets/js/saldos.js

class SaldosManager {
    constructor() {
        this.storeId = null;
        this.init();
    }

    init() {
        this.loadSaldos();
        this.loadHistorico();
        
        // Atualizar dados a cada 30 segundos
        setInterval(() => {
            this.loadSaldos();
        }, 30000);
    }

    async loadSaldos() {
        try {
            const response = await fetch('../../api/store-saldos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_saldos'
                })
            });

            const data = await response.json();

            if (data.status) {
                this.updateSaldosUI(data.data);
            } else {
                this.showAlert('error', data.message || 'Erro ao carregar saldos');
            }
        } catch (error) {
            console.error('Erro ao carregar saldos:', error);
            this.showAlert('error', 'Erro de conexão. Tente novamente.');
        }
    }

    async loadHistorico() {
        try {
            const response = await fetch('../../api/store-saldos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_historico'
                })
            });

            const data = await response.json();

            if (data.status) {
                this.updateHistoricoUI(data.data);
            } else {
                this.showAlert('error', data.message || 'Erro ao carregar histórico');
            }
        } catch (error) {
            console.error('Erro ao carregar histórico:', error);
            document.getElementById('historicoTableBody').innerHTML = 
                '<tr><td colspan="5" class="text-center text-muted">Erro ao carregar dados</td></tr>';
        }
    }

    updateSaldosUI(data) {
        // Saldo com clientes
        const saldoClientesEl = document.getElementById('saldoClientes');
        const totalClientesEl = document.getElementById('totalClientes');
        const saldoMedioEl = document.getElementById('saldoMedio');

        if (saldoClientesEl) {
            saldoClientesEl.innerHTML = this.formatCurrency(data.saldo_clientes || 0);
        }
        if (totalClientesEl) {
            totalClientesEl.textContent = data.total_clientes || '0';
        }
        if (saldoMedioEl) {
            saldoMedioEl.innerHTML = this.formatCurrency(data.saldo_medio || 0);
        }

        // Saldo de devolução
        const saldoDevolucaoEl = document.getElementById('saldoDevolucao');
        const devolucaoMesEl = document.getElementById('devolucaoMes');
        const totalDevolvidoEl = document.getElementById('totalDevolvido');

        if (saldoDevolucaoEl) {
            saldoDevolucaoEl.innerHTML = this.formatCurrency(data.saldo_devolucao || 0);
        }
        if (devolucaoMesEl) {
            devolucaoMesEl.innerHTML = this.formatCurrency(data.devolucao_mes || 0);
        }
        if (totalDevolvidoEl) {
            totalDevolvidoEl.innerHTML = this.formatCurrency(data.total_devolvido || 0);
        }
    }

    updateHistoricoUI(historico) {
        const tbody = document.getElementById('historicoTableBody');
        
        if (!historico || historico.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Nenhuma movimentação encontrada</td></tr>';
            return;
        }

        const rows = historico.map(item => {
            const statusClass = this.getStatusClass(item.tipo);
            const statusText = this.getStatusText(item.tipo);
            
            return `
                <tr>
                    <td>${this.formatDate(item.data)}</td>
                    <td>${item.cliente_nome || 'N/A'}</td>
                    <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                    <td>${this.formatCurrency(item.valor)}</td>
                    <td>${item.status || 'N/A'}</td>
                </tr>
            `;
        }).join('');

        tbody.innerHTML = rows;
    }

    getStatusClass(tipo) {
        switch (tipo) {
            case 'cashback_gerado':
                return 'status-disponivel';
            case 'cashback_utilizado':
                return 'status-utilizado';
            case 'devolucao':
                return 'status-devolvido';
            default:
                return 'status-disponivel';
        }
    }

    getStatusText(tipo) {
        switch (tipo) {
            case 'cashback_gerado':
                return 'Cashback Gerado';
            case 'cashback_utilizado':
                return 'Cashback Utilizado';
            case 'devolucao':
                return 'Devolução';
            default:
                return 'Outros';
        }
    }

    formatCurrency(value) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(value);
    }

    formatDate(dateString) {
        return new Date(dateString).toLocaleDateString('pt-BR');
    }

    showAlert(type, message) {
        const alertContainer = document.getElementById('alertContainer');
        const alertClass = type === 'error' ? 'alert-danger' : 'alert-success';
        
        const alertHTML = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        alertContainer.innerHTML = alertHTML;
        
        // Auto-remover após 5 segundos
        setTimeout(() => {
            alertContainer.innerHTML = '';
        }, 5000);
    }
}

// Inicializar quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    new SaldosManager();
});