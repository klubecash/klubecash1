<?php
// views/admin/transaction-details.php
// Definir o menu ativo na sidebar
$activeMenu = 'compras';

// Incluir conexão com o banco de dados e arquivos necessários
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../controllers/AuthController.php';
require_once '../../controllers/AdminController.php';

// Iniciar sessão
session_start();

// Verificar se o usuário está logado e é administrador
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== USER_TYPE_ADMIN) {
    header("Location: " . LOGIN_URL . "?error=acesso_restrito");
    exit;
}

// Obter ID da transação
$transactionId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($transactionId <= 0) {
    header("Location: " . ADMIN_TRANSACTIONS_URL . "?error=transacao_invalida");
    exit;
}

try {
    // Obter detalhes da transação com informações de saldo
    $result = AdminController::getTransactionDetailsWithBalance($transactionId);
    
    if (!$result['status']) {
        $hasError = true;
        $errorMessage = $result['message'];
        $transaction = null;
    } else {
        $hasError = false;
        $transaction = $result['data'];
    }
} catch (Exception $e) {
    $hasError = true;
    $errorMessage = "Erro ao carregar dados da transação: " . $e->getMessage();
    $transaction = null;
}

// Função para formatar data
function formatDate($date) {
    return date('d/m/Y H:i:s', strtotime($date));
}

// Função para formatar valor
function formatCurrency($value) {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

// Função para formatar status
function getStatusBadge($status) {
    $badges = [
        'pendente' => ['class' => 'status-pending', 'text' => 'Pendente'],
        'aprovado' => ['class' => 'status-approved', 'text' => 'Aprovado'],
        'cancelado' => ['class' => 'status-canceled', 'text' => 'Cancelado'],
        'pagamento_pendente' => ['class' => 'status-payment', 'text' => 'Aguardando Pagamento']
    ];
    
    $badge = $badges[$status] ?? ['class' => 'status-pending', 'text' => ucfirst($status)];
    return '<span class="status-badge ' . $badge['class'] . '">' . $badge['text'] . '</span>';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes da Transação #<?php echo $transactionId; ?> - Klube Cash</title>
    <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
    <link rel="stylesheet" href="../../assets/css/views/admin/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/layout-fix.css">
    <link rel="stylesheet" href="../../assets/css/views/admin/transactions.css">
    
</head>
<body>
    <?php include_once '../components/sidebar.php'; ?>
    
    <div class="main-content" id="mainContent">
        <div class="page-wrapper">
            <div class="transaction-details">
                <!-- Voltar -->
                <a href="<?php echo ADMIN_TRANSACTIONS_URL; ?>" class="back-link">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5"/>
                        <path d="M12 19l-7-7 7-7"/>
                    </svg>
                    Voltar às Transações
                </a>
                
                <?php if ($hasError): ?>
                    <div class="error-message">
                        <strong>Erro:</strong> <?php echo htmlspecialchars($errorMessage); ?>
                    </div>
                    
                    <div class="action-buttons">
                        <div class="btn-group">
                            <a href="<?php echo ADMIN_TRANSACTIONS_URL; ?>" class="btn btn-secondary">
                                Voltar às Transações
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Cabeçalho da Transação -->
                    <div class="detail-header">
                        <h1>Transação #<?php echo $transaction['id']; ?></h1>
                        <div class="transaction-code">
                            <?php if ($transaction['codigo_transacao']): ?>
                                Código: <?php echo htmlspecialchars($transaction['codigo_transacao']); ?>
                            <?php endif; ?>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value">
                                <?php echo getStatusBadge($transaction['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Cards de Detalhes -->
                    <div class="detail-grid">
                        <!-- Informações Básicas -->
                        <div class="detail-card">
                            <h3>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14,2 14,8 20,8"/>
                                    <line x1="16" y1="13" x2="8" y2="13"/>
                                    <line x1="16" y1="17" x2="8" y2="17"/>
                                    <polyline points="10,9 9,9 8,9"/>
                                </svg>
                                Informações Básicas
                            </h3>
                            
                            <div class="detail-item">
                                <span class="detail-label">Data da Transação:</span>
                                <span class="detail-value"><?php echo formatDate($transaction['data_transacao']); ?></span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="detail-label">Cliente:</span>
                                <span class="detail-value">
                                    <?php echo htmlspecialchars($transaction['cliente_nome']); ?>
                                    <?php if ($transaction['saldo_usado'] > 0): ?>
                                        <span class="economy-badge">Usou Saldo</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="detail-label">Email do Cliente:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($transaction['cliente_email']); ?></span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="detail-label">Loja:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($transaction['loja_nome']); ?></span>
                            </div>
                            
                            <?php if ($transaction['descricao']): ?>
                            <div class="detail-item">
                                <span class="detail-label">Descrição:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($transaction['descricao']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Informações Financeiras -->
                        <div class="detail-card">
                            <h3>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="1" x2="12" y2="23"/>
                                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                </svg>
                                Informações Financeiras
                            </h3>
                            
                            <?php 
                            $saldoUsado = floatval($transaction['saldo_usado'] ?? 0);
                            $valorOriginal = floatval($transaction['valor_total']);
                            $valorPago = $valorOriginal - $saldoUsado;
                            ?>
                            
                            <div class="detail-item">
                                <span class="detail-label">Valor Original:</span>
                                <span class="detail-value value-highlight"><?php echo formatCurrency($valorOriginal); ?></span>
                            </div>
                            
                            <?php if ($saldoUsado > 0): ?>
                            <div class="saldo-highlight">
                                <h4>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                        <path d="M16 12l-4-4-4 4"/>
                                        <path d="M12 16V8"/>
                                    </svg>
                                    Uso de Saldo
                                </h4>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Saldo Usado:</span>
                                    <span class="detail-value" style="color: #4caf50; font-weight: 600;">
                                        -<?php echo formatCurrency($saldoUsado); ?>
                                    </span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Valor Efetivamente Pago:</span>
                                    <span class="detail-value value-highlight"><?php echo formatCurrency($valorPago); ?></span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Economia do Cliente:</span>
                                    <span class="detail-value" style="color: #4caf50; font-weight: 600;">
                                        <?php echo formatCurrency($saldoUsado); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="financial-breakdown">
                                <h4 style="margin-bottom: 10px; color: #2e7d32;">Distribuição de Cashback</h4>
                                
                                <div class="breakdown-item">
                                    <span>Cashback do Cliente:</span>
                                    <span class="value"><?php echo formatCurrency($transaction['valor_cliente']); ?></span>
                                </div>
                                
                                <div class="breakdown-item">
                                    <span>Comissão Klube Cash:</span>
                                    <span class="value"><?php echo formatCurrency($transaction['valor_admin']); ?></span>
                                </div>
                                
                                <div class="breakdown-item">
                                    <span>Valor Loja:</span>
                                    <span class="value"><?php echo formatCurrency($transaction['valor_loja']); ?></span>
                                </div>
                                
                                <div class="breakdown-item total">
                                    <span>Total de Cashback:</span>
                                    <span class="value"><?php echo formatCurrency($transaction['valor_cashback']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($saldoUsado > 0): ?>
                    <!-- Impacto do Sistema de Saldo -->
                    <div class="detail-card" style="margin-bottom: 20px;">
                        <h3>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                                <point cx="12" cy="17"/>
                            </svg>
                            💰 Análise do Uso de Saldo
                        </h3>
                        
                        <div style="background: #f8fff8; padding: 15px; border-radius: 10px; border-left: 4px solid #4caf50;">
                            <div class="detail-item">
                                <span class="detail-label">Impacto na Receita da Loja:</span>
                                <span class="detail-value">
                                    Redução de <?php echo formatCurrency($saldoUsado); ?> 
                                    (<?php echo number_format(($saldoUsado / $valorOriginal) * 100, 1); ?>%)
                                </span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="detail-label">Impacto na Comissão Klube Cash:</span>
                                <span class="detail-value">
                                    Redução de <?php echo formatCurrency($saldoUsado * 0.1); ?> (10% do saldo usado)
                                </span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="detail-label">Benefício:</span>
                                <span class="detail-value">Cliente economiza e loja mantém fidelidade</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Botões de Ação -->
                    <div class="action-buttons">
                        <h3 style="margin-bottom: 15px; color: var(--primary-color);">Ações</h3>
                        <div class="btn-group">
                            <?php if ($transaction['status'] === 'pendente'): ?>
                                <button class="btn btn-success" onclick="updateTransactionStatus(<?php echo $transaction['id']; ?>, 'aprovado')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    Aprovar Transação
                                </button>
                                
                                <button class="btn btn-danger" onclick="updateTransactionStatus(<?php echo $transaction['id']; ?>, 'cancelado')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="18" y1="6" x2="6" y2="18"/>
                                        <line x1="6" y1="6" x2="18" y2="18"/>
                                    </svg>
                                    Cancelar Transação
                                </button>
                            <?php endif; ?>
                            
                            <a href="<?php echo ADMIN_TRANSACTIONS_URL; ?>?loja_id=<?php echo $transaction['loja_id']; ?>" class="btn btn-secondary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                    <line x1="9" y1="9" x2="15" y2="15"/>
                                    <line x1="15" y1="9" x2="9" y2="15"/>
                                </svg>
                                Ver Outras Transações desta Loja
                            </a>
                            
                            <a href="<?php echo ADMIN_USERS_URL; ?>?search=<?php echo urlencode($transaction['cliente_email']); ?>" class="btn btn-secondary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                                Ver Perfil do Cliente
                            </a>
                            
                            <button class="btn btn-primary" onclick="exportTransaction(<?php echo $transaction['id']; ?>)">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <polyline points="7 10 12 15 17 10"/>
                                    <line x1="12" y1="15" x2="12" y2="3"/>
                                </svg>
                                Exportar Dados
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal de Confirmação -->
    <div id="confirmModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 10px; max-width: 400px; width: 90%;">
            <h3 id="modalTitle" style="margin-bottom: 15px; color: var(--primary-color);"></h3>
            <p id="modalMessage" style="margin-bottom: 20px; color: var(--medium-gray);"></p>
            <div>
                <label for="modalObservacao" style="display: block; margin-bottom: 5px; font-weight: 600;">Observação (opcional):</label>
                <textarea id="modalObservacao" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px; min-height: 80px;"></textarea>
            </div>
            <div style="margin-top: 20px; text-align: right;">
                <button onclick="closeModal()" style="margin-right: 10px; padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer;">Cancelar</button>
                <button id="confirmButton" style="padding: 8px 16px; background: var(--primary-color); color: white; border: none; border-radius: 5px; cursor: pointer;">Confirmar</button>
            </div>
        </div>
    </div>
    
    <script>
        let currentTransactionId = null;
        let currentAction = null;
        
        function updateTransactionStatus(transactionId, newStatus) {
            currentTransactionId = transactionId;
            currentAction = newStatus;
            
            const modal = document.getElementById('confirmModal');
            const title = document.getElementById('modalTitle');
            const message = document.getElementById('modalMessage');
            const confirmBtn = document.getElementById('confirmButton');
            
            if (newStatus === 'aprovado') {
                title.textContent = 'Aprovar Transação';
                message.textContent = 'Tem certeza que deseja aprovar esta transação? O cashback será liberado para o cliente.';
                confirmBtn.style.background = '#28a745';
                confirmBtn.textContent = 'Aprovar';
            } else if (newStatus === 'cancelado') {
                title.textContent = 'Cancelar Transação';
                message.textContent = 'Tem certeza que deseja cancelar esta transação? Esta ação não pode ser desfeita.';
                confirmBtn.style.background = '#dc3545';
                confirmBtn.textContent = 'Cancelar Transação';
            }
            
            modal.style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('confirmModal').style.display = 'none';
            document.getElementById('modalObservacao').value = '';
            currentTransactionId = null;
            currentAction = null;
        }
        
        function exportTransaction(transactionId) {
            // Implementar exportação da transação
            const url = '/admin/ajax/transactions';
            const params = new URLSearchParams({
                action: 'export_transaction',
                transaction_id: transactionId
            });
            
            window.open(url + '?' + params.toString(), '_blank');
        }
        
        // Confirmar ação
        document.getElementById('confirmButton').addEventListener('click', function() {
            if (!currentTransactionId || !currentAction) return;
            
            const observacao = document.getElementById('modalObservacao').value;
            
            fetch('/admin/ajax/transactions', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_transaction_status&transaction_id=${currentTransactionId}&status=${currentAction}&observacao=${encodeURIComponent(observacao)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    alert('Status da transação atualizado com sucesso!');
                    window.location.reload();
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao atualizar status da transação.');
            })
            .finally(() => {
                closeModal();
            });
        });
        
        // Fechar modal ao clicar fora
        document.getElementById('confirmModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
