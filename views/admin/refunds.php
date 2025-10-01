<?php
// views/admin/refunds.php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../controllers/AuthController.php';

session_start();

if (!AuthController::isAuthenticated() || !AuthController::isAdmin()) {
    header("Location: " . LOGIN_URL . "?error=acesso_restrito");
    exit;
}

$activeMenu = 'refunds';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Devoluções - Klube Cash</title>
    <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
    <link rel="stylesheet" href="../../assets/css/views/admin/refunds.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include_once '../components/sidebar-admin.php'; ?>
    
    <div class="main-content" id="mainContent">
        <div class="dashboard-header">
            <h1>Gerenciar Devoluções PIX</h1>
            <p class="subtitle">Aprovar ou rejeitar solicitações de devolução</p>
        </div>
        
        <!-- Filtros -->
        <div class="card filter-card">
            <div class="card-header">
                <h2 class="card-title">Filtros</h2>
            </div>
            <div class="filters">
                <div class="filter-group">
                    <label for="statusFilter">Status:</label>
                    <select id="statusFilter">
                        <option value="">Todos</option>
                        <option value="solicitado">Solicitado</option>
                        <option value="processando">Processando</option>
                        <option value="aprovado">Aprovado</option>
                        <option value="rejeitado">Rejeitado</option>
                        <option value="erro">Erro</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="dateFrom">Data Início:</label>
                    <input type="date" id="dateFrom">
                </div>
                <div class="filter-group">
                    <label for="dateTo">Data Fim:</label>
                    <input type="date" id="dateTo">
                </div>
                <button class="btn btn-primary" onclick="loadRefunds()">Buscar</button>
            </div>
        </div>
        
        <!-- Lista de Devoluções -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Solicitações de Devolução</h2>
                <div class="stats-row">
                    <div class="stat-item">
                        <span class="stat-value" id="totalRefunds">0</span>
                        <span class="stat-label">Total</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value" id="pendingRefunds">0</span>
                        <span class="stat-label">Pendentes</span>
                    </div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table" id="refundsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Data</th>
                            <th>Loja</th>
                            <th>Pagamento</th>
                            <th>Valor</th>
                            <th>Tipo</th>
                            <th>Motivo</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Conteúdo carregado via JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal de Detalhes -->
    <div id="refundModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Detalhes da Devolução</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body" id="refundDetails">
                <!-- Conteúdo carregado via JS -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-success" onclick="approveRefund()" id="approveBtn">Aprovar</button>
                <button class="btn btn-danger" onclick="rejectRefund()" id="rejectBtn">Rejeitar</button>
                <button class="btn btn-secondary" onclick="closeModal()">Fechar</button>
            </div>
        </div>
    </div>
    
    <script>
        let currentRefundId = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            loadRefunds();
        });
        
        async function loadRefunds() {
            try {
                const status = document.getElementById('statusFilter').value;
                const dateFrom = document.getElementById('dateFrom').value;
                const dateTo = document.getElementById('dateTo').value;
                
                let url = '../../api/refunds.php?action=list&limit=50';
                if (status) url += `&status=${status}`;
                if (dateFrom) url += `&date_from=${dateFrom}`;
                if (dateTo) url += `&date_to=${dateTo}`;
                
                const response = await fetch(url);
                const result = await response.json();
                
                if (result.status) {
                    displayRefunds(result.data);
                    updateStats(result.data);
                } else {
                    showNotification('Erro ao carregar devoluções: ' + result.message, 'error');
                }
            } catch (error) {
                console.error('Erro:', error);
                showNotification('Erro de conexão', 'error');
            }
        }
        
        function displayRefunds(refunds) {
            const tbody = document.querySelector('#refundsTable tbody');
            tbody.innerHTML = '';
            
            refunds.forEach(refund => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>#${refund.id}</td>
                    <td>${formatDate(refund.data_solicitacao)}</td>
                    <td>Loja #${refund.loja_id}</td>
                    <td>R$ ${formatMoney(refund.pagamento_valor)}</td>
                    <td>R$ ${formatMoney(refund.valor_devolucao)}</td>
                    <td><span class="badge ${refund.tipo}">${refund.tipo}</span></td>
                    <td>${refund.motivo.substring(0, 50)}...</td>
                    <td><span class="status ${refund.status}">${getStatusText(refund.status)}</span></td>
                    <td>
                        <button class="btn btn-small" onclick="viewRefundDetails(${refund.id})">Ver</button>
                        ${refund.status === 'solicitado' ? 
                            `<button class="btn btn-small btn-success" onclick="showApproveModal(${refund.id})">Aprovar</button>
                             <button class="btn btn-small btn-danger" onclick="showRejectModal(${refund.id})">Rejeitar</button>` 
                            : ''}
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }
        
        function updateStats(refunds) {
            document.getElementById('totalRefunds').textContent = refunds.length;
            document.getElementById('pendingRefunds').textContent = 
                refunds.filter(r => r.status === 'solicitado').length;
        }
        
        async function viewRefundDetails(refundId) {
            try {
                const response = await fetch(`../../api/refunds.php?action=status&refund_id=${refundId}`);
                const result = await response.json();
                
                if (result.status) {
                    const refund = result.data;
                    currentRefundId = refundId;
                    
                    document.getElementById('refundDetails').innerHTML = `
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>ID da Devolução:</label>
                                <span>#${refund.id}</span>
                            </div>
                            <div class="detail-item">
                                <label>Status:</label>
                                <span class="status ${refund.status}">${getStatusText(refund.status)}</span>
                            </div>
                            <div class="detail-item">
                                <label>Tipo:</label>
                                <span>${refund.tipo}</span>
                            </div>
                            <div class="detail-item">
                                <label>Valor Original:</label>
                                <span>R$ ${formatMoney(refund.pagamento_valor)}</span>
                            </div>
                            <div class="detail-item">
                                <label>Valor da Devolução:</label>
                                <span>R$ ${formatMoney(refund.valor_devolucao)}</span>
                            </div>
                            <div class="detail-item">
                                <label>Data da Solicitação:</label>
                                <span>${formatDate(refund.data_solicitacao)}</span>
                            </div>
                            <div class="detail-item full-width">
                                <label>Motivo:</label>
                                <span>${refund.motivo}</span>
                            </div>
                            ${refund.observacao_admin ? `
                                <div class="detail-item full-width">
                                    <label>Observação do Admin:</label>
                                    <span>${refund.observacao_admin}</span>
                                </div>
                            ` : ''}
                            ${refund.mp_refund_id ? `
                                <div class="detail-item">
                                    <label>ID MP:</label>
                                    <span>${refund.mp_refund_id}</span>
                                </div>
                            ` : ''}
                        </div>
                    `;
                    
                    // Mostrar/esconder botões baseado no status
                    const approveBtn = document.getElementById('approveBtn');
                    const rejectBtn = document.getElementById('rejectBtn');
                    
                    if (refund.status === 'solicitado') {
                        approveBtn.style.display = 'inline-block';
                        rejectBtn.style.display = 'inline-block';
                    } else {
                        approveBtn.style.display = 'none';
                        rejectBtn.style.display = 'none';
                    }
                    
                    document.getElementById('refundModal').style.display = 'block';
                } else {
                    showNotification('Erro ao carregar detalhes: ' + result.message, 'error');
                }
            } catch (error) {
                console.error('Erro:', error);
                showNotification('Erro de conexão', 'error');
            }
        }
        
        async function approveRefund() {
            if (!currentRefundId) return;
            
            const observation = prompt('Observação (opcional):');
            
            try {
                // ESTE É O CÓDIGO DE EXEMPLO 2 EM USO REAL
                const response = await fetch('../../api/refunds.php?action=approve', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        refund_id: currentRefundId,
                        observation: observation || ''
                    })
                });
                
                const result = await response.json();
                
                if (result.status) {
                    showNotification('Devolução aprovada com sucesso!', 'success');
                    closeModal();
                    loadRefunds();
                } else {
                    showNotification('Erro ao aprovar devolução: ' + result.message, 'error');
                }
            } catch (error) {
                console.error('Erro:', error);
                showNotification('Erro de conexão', 'error');
            }
        }
        
        async function rejectRefund() {
            if (!currentRefundId) return;
            
            const reason = prompt('Motivo da rejeição:');
            if (!reason) return;
            
            try {
                const response = await fetch('../../api/refunds.php?action=reject', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        refund_id: currentRefundId,
                        reason: reason
                    })
                });
                
                const result = await response.json();
                
                if (result.status) {
                    showNotification('Devolução rejeitada com sucesso!', 'success');
                    closeModal();
                    loadRefunds();
                } else {
                    showNotification('Erro ao rejeitar devolução: ' + result.message, 'error');
                }
            } catch (error) {
                console.error('Erro:', error);
                showNotification('Erro de conexão', 'error');
            }
        }
        
        function closeModal() {
            document.getElementById('refundModal').style.display = 'none';
            currentRefundId = null;
        }
        
        function getStatusText(status) {
            const statusMap = {
                'solicitado': 'Solicitado',
                'processando': 'Processando',
                'aprovado': 'Aprovado',
                'rejeitado': 'Rejeitado',
                'erro': 'Erro'
            };
            return statusMap[status] || status;
        }
        
        function formatDate(dateString) {
            return new Date(dateString).toLocaleString('pt-BR');
        }
        
        function formatMoney(value) {
            return parseFloat(value).toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
        
        function showNotification(message, type) {
            // Implementar sistema de notificações
            alert(message);
        }
    </script>
</body>
</html>