<?php
// views/stores/transactions.php
// Incluir arquivos de configuração
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../controllers/AuthController.php';
require_once '../../controllers/TransactionController.php';

// Iniciar sessão e verificar autenticação
session_start();

// Verificar se o usuário está logado
if (!AuthController::isAuthenticated()) {
    header('Location: ' . LOGIN_URL . '?error=' . urlencode('Você precisa fazer login para acessar esta página.'));
    exit;
}

// Verificar se o usuário é do tipo loja
if (!AuthController::isStore()) {
    header('Location: ' . CLIENT_DASHBOARD_URL . '?error=' . urlencode('Acesso restrito a lojas parceiras.'));
    exit;
}

// Obter ID do usuário logado
$userId = AuthController::getCurrentUserId();

// Obter dados da loja associada ao usuário
$db = Database::getConnection();
$storeQuery = $db->prepare("SELECT * FROM lojas WHERE usuario_id = :usuario_id");
$storeQuery->bindParam(':usuario_id', $userId);
$storeQuery->execute();

// Verificar se o usuário tem uma loja associada
if ($storeQuery->rowCount() == 0) {
    header('Location: ' . LOGIN_URL . '?error=' . urlencode('Sua conta não está associada a nenhuma loja. Entre em contato com o suporte.'));
    exit;
}

// Obter os dados da loja
$store = $storeQuery->fetch(PDO::FETCH_ASSOC);
$storeId = $store['id'];

// Definir menu ativo
$activeMenu = 'transactions';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transações - Klube Cash</title>
    <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
    <link rel="stylesheet" href="/assets/css/sidebar-lojista_sest.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../../assets/css/views/stores/transactions.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Incluir o componente sidebar -->
        <?php
        $activeMenu = 'dashboard'; // Menu ativo para transações
        include '../../views/components/sidebar-lojista-responsiva.php';
        ?>
        
        <div class="main-content" id="mainContent">
            <div class="dashboard-header">
                <div>
                    <h1 class="dashboard-title">Minhas Transações</h1>
                    <p class="welcome-user">Loja: <?php echo htmlspecialchars($store['nome_fantasia']); ?></p>
                </div>
            </div>
            
            <!-- Cards de estatísticas -->
            <div class="summary-cards">
                <div class="card">
                    <div class="card-content">
                        <h3>Total de Transações</h3>
                        <div class="card-value" id="totalTransactions">-</div>
                        <div class="card-period">Transações registradas</div>
                    </div>
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="1" x2="12" y2="23"></line>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-content">
                        <h3>Valor Total</h3>
                        <div class="card-value" id="totalSales">-</div>
                        <div class="card-period">Em vendas processadas</div>
                    </div>
                    <div class="card-icon success">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                        </svg>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-content">
                        <h3>Transações Pendentes</h3>
                        <div class="card-value" id="pendingTransactions">-</div>
                        <div class="card-period">Aguardando pagamento</div>
                    </div>
                    <div class="card-icon warning">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-content">
                        <h3>Total Comissões</h3>
                        <div class="card-value" id="totalCommissions">-</div>
                        <div class="card-period">Valor total de comissões</div>
                    </div>
                    <div class="card-icon info">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>
                </div>
            </div>
            
            <!-- Lista de Transações -->
            <div class="transactions-section">
                <div class="section-header">
                    <h2>Lista de Transações</h2>
                    <div>
                        <button class="btn btn-primary" onclick="openFilterModal()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 0.5rem;">
                                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                            </svg>
                            Filtros
                        </button>
                        <a href="<?php echo STORE_REGISTER_TRANSACTION_URL; ?>" class="btn btn-success">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 0.5rem;">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Nova Venda
                        </a>
                    </div>
                </div>
                
                <div id="loadingState" class="loading-state">
                    <div class="spinner"></div>
                    <p>Carregando transações...</p>
                </div>
                
                <div id="transactionsContent" style="display: none;">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Cliente</th>
                                    <th>Código</th>
                                    <th>Valor</th>
                                    <th>Comissão</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="transactionsTableBody">
                            </tbody>
                        </table>
                    </div>
                    
                    <div id="paginationContainer">
                        <!-- Paginação será inserida aqui -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Filtros -->
    <div class="modal-backdrop" id="filterModalBackdrop"></div>
    <div class="modal" id="filterModal">
        <div class="modal-header">
            <h3 class="modal-title">Filtros de Busca</h3>
            <button class="modal-close" onclick="closeFilterModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="filterForm">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Data Início</label>
                        <input type="date" class="form-control" name="data_inicio" id="filterDataInicio">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Data Fim</label>
                        <input type="date" class="form-control" name="data_fim" id="filterDataFim">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="form-control" name="status" id="filterStatus">
                            <option value="">Todos</option>
                            <option value="pendente">Pendente</option>
                            <option value="aprovado">Aprovado</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cliente</label>
                        <input type="text" class="form-control" name="cliente" id="filterCliente" placeholder="Nome ou email">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Valor Mínimo</label>
                        <input type="number" class="form-control" name="valor_min" id="filterValorMin" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Valor Máximo</label>
                        <input type="number" class="form-control" name="valor_max" id="filterValorMax" step="0.01" min="0">
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline-primary" onclick="clearFilters()">Limpar</button>
            <button class="btn btn-primary" onclick="applyFilters()">Aplicar Filtros</button>
        </div>
    </div>
    <!-- Modal de Detalhes da Transação -->
    <div class="modal-backdrop" id="detailsModalBackdrop"></div>
    <div class="modal" id="detailsModal">
        <div class="modal-header">
            <h3 class="modal-title">Detalhes da Transação</h3>
            <button class="modal-close" onclick="closeDetailsModal()">&times;</button>
        </div>
        <div class="modal-body" id="detailsModalContent">
            <div class="loading-state">
                <div class="spinner"></div>
                <p>Carregando detalhes...</p>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline-primary" onclick="closeDetailsModal()">Fechar</button>
        </div>
    </div>
    <script>
        // Variáveis globais
        const storeId = <?php echo $store['id']; ?>;
        let currentPage = 1;
        let currentFilters = {};

        // Carregar transações ao iniciar
        document.addEventListener('DOMContentLoaded', function() {
            loadTransactions();
        });

        // Função para carregar transações
        function loadTransactions(page = 1) {
            currentPage = page;
            
            // Mostrar loading
            document.getElementById('loadingState').style.display = 'block';
            document.getElementById('transactionsContent').style.display = 'none';
            
            const formData = new FormData();
            formData.append('action', 'store_transactions');
            formData.append('loja_id', storeId);
            formData.append('page', page);
            
            // Adicionar filtros
            Object.keys(currentFilters).forEach(key => {
                if (currentFilters[key]) {
                    formData.append(`filters[${key}]`, currentFilters[key]);
                }
            });

            fetch('../../controllers/TransactionController.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    displayTransactions(data.data);
                    updateSummaryCards(data.data.totais);
                    updatePagination(data.data.paginacao);
                } else {
                    showError('Erro ao carregar transações: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showError('Erro ao carregar transações');
            });
        }

        // Função para exibir transações
        function displayTransactions(data) {
            document.getElementById('loadingState').style.display = 'none';
            document.getElementById('transactionsContent').style.display = 'block';
            
            const tbody = document.getElementById('transactionsTableBody');
            tbody.innerHTML = '';

            if (!data.transacoes || data.transacoes.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="empty-state">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                                <polyline points="13 2 13 9 20 9"></polyline>
                            </svg>
                            <h3>Nenhuma transação encontrada</h3>
                            <p>Comece registrando sua primeira venda com cashback</p>
                            <a href="${'<?php echo STORE_REGISTER_TRANSACTION_URL; ?>'}" class="btn btn-primary">Registrar Venda</a>
                        </td>
                    </tr>
                `;
                return;
            }

            data.transacoes.forEach(transaction => {
                const row = document.createElement('tr');
                
                let statusBadge = '';
                switch (transaction.status) {
                    case 'pendente':
                        statusBadge = '<span class="status-badge pendente">Pendente</span>';
                        break;
                    case 'aprovado':
                        statusBadge = '<span class="status-badge aprovado">Aprovado</span>';
                        break;
                    case 'cancelado':
                        statusBadge = '<span class="status-badge cancelado">Cancelado</span>';
                        break;
                    default:
                        statusBadge = '<span class="status-badge">' + transaction.status + '</span>';
                }

                row.innerHTML = `
                    <td data-label="Data">${formatDateTime(transaction.data_transacao)}</td>
                    <td data-label="Cliente">
                        <div><strong>${transaction.cliente_nome}</strong></div>
                        <small style="color: var(--medium-gray);">${transaction.cliente_email}</small>
                    </td>
                    <td data-label="Código"><code>${transaction.codigo_transacao}</code></td>
                    <td data-label="Valor">R$ ${formatMoney(transaction.valor_total)}</td>
                    <td data-label="Comissão">R$ ${formatMoney(transaction.valor_cashback)}</td>
                    <td data-label="Status">${statusBadge}</td>
                    <td data-label="Ações">
                        <button class="btn btn-outline-primary btn-sm" onclick="viewTransactionDetails(${transaction.id})">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </td>
                `;
                
                tbody.appendChild(row);
            });
        }

        // Função para atualizar cards de resumo
        function updateSummaryCards(totais) {
            if (!totais) return;
            
            document.getElementById('totalTransactions').textContent = totais.total_transacoes || 0;
            document.getElementById('totalSales').textContent = 'R$ ' + formatMoney(totais.valor_total_vendas || 0);
            document.getElementById('pendingTransactions').textContent = totais.total_pendentes || 0;
            document.getElementById('totalCommissions').textContent = 'R$ ' + formatMoney(totais.total_comissoes || 0);
        }

        // Função para atualizar paginação
        function updatePagination(paginacao) {
            const container = document.getElementById('paginationContainer');
            
            if (!paginacao || paginacao.total_paginas <= 1) {
                container.innerHTML = '';
                return;
            }

            let paginationHTML = '<ul class="pagination">';

            // Botão Anterior
            if (paginacao.pagina_atual > 1) {
                paginationHTML += `<li><a href="#" onclick="loadTransactions(${paginacao.pagina_atual - 1}); return false;">« Anterior</a></li>`;
            }

            // Páginas numeradas
            for (let i = 1; i <= paginacao.total_paginas; i++) {
                const activeClass = i === paginacao.pagina_atual ? 'active' : '';
                paginationHTML += `<li><a href="#" class="${activeClass}" onclick="loadTransactions(${i}); return false;">${i}</a></li>`;
            }

            // Botão Próximo
            if (paginacao.pagina_atual < paginacao.total_paginas) {
                paginationHTML += `<li><a href="#" onclick="loadTransactions(${paginacao.pagina_atual + 1}); return false;">Próximo »</a></li>`;
            }

            paginationHTML += '</ul>';
            container.innerHTML = paginationHTML;
        }

        // Funções do modal
        function openFilterModal() {
            document.getElementById('filterModal').style.display = 'block';
            document.getElementById('filterModalBackdrop').style.display = 'block';
        }

        function closeFilterModal() {
            document.getElementById('filterModal').style.display = 'none';
            document.getElementById('filterModalBackdrop').style.display = 'none';
        }

        function applyFilters() {
            const form = document.getElementById('filterForm');
            const formData = new FormData(form);
            
            currentFilters = {};
            for (let [key, value] of formData.entries()) {
                if (value.trim() !== '') {
                    currentFilters[key] = value;
                }
            }
            
            currentPage = 1;
            loadTransactions(1);
            closeFilterModal();
        }

        function clearFilters() {
            document.getElementById('filterForm').reset();
            currentFilters = {};
            currentPage = 1;
            loadTransactions(1);
        }

        // Função para visualizar detalhes da transação
        function viewTransactionDetails(transactionId) {
            // Mostrar modal
            document.getElementById('detailsModal').style.display = 'block';
            document.getElementById('detailsModalBackdrop').style.display = 'block';
            document.getElementById('detailsModalContent').innerHTML = `
                <div class="loading-state">
                    <div class="spinner"></div>
                    <p>Carregando detalhes...</p>
                </div>
            `;

            // Buscar detalhes da transação
            const formData = new FormData();
            formData.append('action', 'transaction_details');
            formData.append('transaction_id', transactionId);

            fetch('../../controllers/TransactionController.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    displayTransactionDetails(data.data);
                } else {
                    document.getElementById('detailsModalContent').innerHTML = `
                        <div style="text-align: center; padding: 2rem; color: var(--danger-color);">
                            <h3>Erro ao carregar</h3>
                            <p>${data.message}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                document.getElementById('detailsModalContent').innerHTML = `
                    <div style="text-align: center; padding: 2rem; color: var(--danger-color);">
                        <h3>Erro de conexão</h3>
                        <p>Não foi possível carregar os detalhes da transação.</p>
                    </div>
                `;
            });
        }

        // Função para exibir os detalhes no modal
        function displayTransactionDetails(transaction) {
            const statusClass = {
                'pendente': 'pendente',
                'aprovado': 'aprovado',
                'cancelado': 'cancelado',
                'pagamento_pendente': 'warning'
            };

            const statusText = {
                'pendente': 'Pendente',
                'aprovado': 'Aprovado',
                'cancelado': 'Cancelado',
                'pagamento_pendente': 'Pagamento Pendente'
            };

            let saldoUsadoHtml = '';
            if (transaction.valor_saldo_usado && parseFloat(transaction.valor_saldo_usado) > 0) {
                saldoUsadoHtml = `
                    <div class="detail-card warning-card">
                        <h4>💰 Saldo Utilizado</h4>
                        <div class="detail-row">
                            <span>Valor do saldo usado:</span>
                            <strong>R$ ${formatMoney(transaction.valor_saldo_usado)}</strong>
                        </div>
                        <div class="detail-row">
                            <span>Valor efetivamente pago:</span>
                            <strong>R$ ${formatMoney(transaction.valor_total - transaction.valor_saldo_usado)}</strong>
                        </div>
                    </div>
                `;
            }

            let comissaoHtml = '';
            if (transaction.pagamento_id) {
                comissaoHtml = `
                    <div class="detail-card">
                        <h4>💼 Informações de Pagamento</h4>
                        <div class="detail-row">
                            <span>ID do Pagamento:</span>
                            <strong>#${transaction.pagamento_id}</strong>
                        </div>
                        <div class="detail-row">
                            <span>Status do Pagamento:</span>
                            <span class="status-badge ${transaction.status_pagamento || 'pendente'}">${transaction.status_pagamento || 'Pendente'}</span>
                        </div>
                        ${transaction.data_pagamento ? `
                        <div class="detail-row">
                            <span>Data do Pagamento:</span>
                            <strong>${formatDateTime(transaction.data_pagamento)}</strong>
                        </div>
                        ` : ''}
                    </div>
                `;
            }

            document.getElementById('detailsModalContent').innerHTML = `
                <div class="transaction-details">
                    <!-- Informações Básicas -->
                    <div class="detail-card primary-card">
                        <h4>📋 Informações Gerais</h4>
                        <div class="detail-row">
                            <span>Código da Transação:</span>
                            <strong><code>${transaction.codigo_transacao}</code></strong>
                        </div>
                        <div class="detail-row">
                            <span>Data:</span>
                            <strong>${formatDateTime(transaction.data_transacao)}</strong>
                        </div>
                        <div class="detail-row">
                            <span>Status:</span>
                            <span class="status-badge ${statusClass[transaction.status] || ''}">${statusText[transaction.status] || transaction.status}</span>
                        </div>
                        ${transaction.descricao ? `
                        <div class="detail-row">
                            <span>Descrição:</span>
                            <strong>${transaction.descricao}</strong>
                        </div>
                        ` : ''}
                    </div>

                    <!-- Informações do Cliente -->
                    <div class="detail-card">
                        <h4>👤 Cliente</h4>
                        <div class="detail-row">
                            <span>Nome:</span>
                            <strong>${transaction.cliente_nome}</strong>
                        </div>
                        <div class="detail-row">
                            <span>Email:</span>
                            <strong>${transaction.cliente_email}</strong>
                        </div>
                    </div>

                    <!-- Valores -->
                    <div class="detail-card success-card">
                        <h4>💰 Valores</h4>
                        <div class="detail-row">
                            <span>Valor Total da Compra:</span>
                            <strong>R$ ${formatMoney(transaction.valor_total)}</strong>
                        </div>
                        <div class="detail-row">
                            <span>Comissão Total (10%):</span>
                            <strong>R$ ${formatMoney(transaction.valor_cashback)}</strong>
                        </div>
                        <div class="detail-row">
                            <span>Cashback Cliente (5%):</span>
                            <strong>R$ ${formatMoney(transaction.valor_cliente)}</strong>
                        </div>
                        <div class="detail-row">
                            <span>Comissão Admin (5%):</span>
                            <strong>R$ ${formatMoney(transaction.valor_admin)}</strong>
                        </div>
                    </div>

                    ${saldoUsadoHtml}
                    ${comissaoHtml}
                </div>
            `;
        }

        // Função para fechar modal de detalhes
        function closeDetailsModal() {
            document.getElementById('detailsModal').style.display = 'none';
            document.getElementById('detailsModalBackdrop').style.display = 'none';
        }

        // Fechar modal clicando no backdrop
        document.getElementById('detailsModalBackdrop').addEventListener('click', closeDetailsModal);

        function showError(message) {
            document.getElementById('loadingState').innerHTML = `
                <div style="text-align: center; padding: 2rem; color: var(--danger-color);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 1rem;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg>
                    <h3>Erro ao carregar</h3>
                    <p>${message}</p>
                    <button class="btn btn-primary" onclick="loadTransactions()">Tentar Novamente</button>
                </div>
            `;
        }

        // Funções utilitárias
        function formatMoney(value) {
            return parseFloat(value || 0).toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('pt-BR');
        }

        // Fechar modal clicando no backdrop
        document.getElementById('filterModalBackdrop').addEventListener('click', closeFilterModal);
    </script>
    <script src="/assets/js/sidebar-lojista.js"></script>
</body>
</html>