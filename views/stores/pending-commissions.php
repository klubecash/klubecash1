<?php
// views/stores/pending-commissions.php
$activeMenu = 'pendentes-pagamento';

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../controllers/AuthController.php';
require_once '../../controllers/TransactionController.php';
require_once '../../models/CashbackBalance.php';

session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'loja') {
    header("Location: " . LOGIN_URL . "?error=acesso_restrito");
    exit;
}

$userId = $_SESSION['user_id'];

$db = Database::getConnection();
$storeQuery = $db->prepare("SELECT id, nome_fantasia FROM lojas WHERE usuario_id = :usuario_id");
$storeQuery->bindParam(':usuario_id', $userId);
$storeQuery->execute();

if ($storeQuery->rowCount() == 0) {
    header('Location: ' . LOGIN_URL . '?error=' . urlencode('Sua conta não está associada a nenhuma loja. Entre em contato com o suporte.'));
    exit;
}

$store = $storeQuery->fetch(PDO::FETCH_ASSOC);
$storeId = $store['id'];
$storeName = $store['nome_fantasia'];

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$filters = [];

if (isset($_GET['data_inicio']) && !empty($_GET['data_inicio'])) {
    $filters['data_inicio'] = $_GET['data_inicio'];
}
if (isset($_GET['data_fim']) && !empty($_GET['data_fim'])) {
    $filters['data_fim'] = $_GET['data_fim'];
}
if (isset($_GET['valor_min']) && !empty($_GET['valor_min'])) {
    $filters['valor_min'] = floatval($_GET['valor_min']);
}
if (isset($_GET['valor_max']) && !empty($_GET['valor_max'])) {
    $filters['valor_max'] = floatval($_GET['valor_max']);
}

$result = TransactionController::getPendingTransactionsWithBalance($storeId, $filters, $page);

$totalTransacoes = 0;
$totalValorVendas = 0;
$totalValorComissoes = 0;
$totalSaldoUsado = 0;

if ($result['status'] && isset($result['data']['totais'])) {
    $totalTransacoes = $result['data']['totais']['total_transacoes'];
    $totalValorVendas = $result['data']['totais']['total_valor_vendas_originais'];
    $totalSaldoUsado = $result['data']['totais']['total_saldo_usado'];
    
    // CORREÇÃO: Calcular total de comissões baseado nos valores reais das transações
    $totalValorComissoes = 0;
    if ($result['status'] && isset($result['data']['transacoes'])) {
        foreach ($result['data']['transacoes'] as $transaction) {
            $totalValorComissoes += floatval($transaction['valor_cashback']);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
    <title>Comissões Pendentes - Klube Cash</title>
    
    <?php
    // Determinar qual CSS carregar baseado no campo senat do usuário
    $pendingCommissionsCssFile = 'pending-commissions.css'; // CSS padrão
    $sidebarCssFile = 'sidebar-lojista.css'; // CSS da sidebar padrão

    if (isset($_SESSION['user_senat']) && ($_SESSION['user_senat'] === 'sim' || $_SESSION['user_senat'] === 'Sim')) {
        $pendingCommissionsCssFile = 'pending-commissions_sest.css'; // CSS para usuários senat=sim
        $sidebarCssFile = 'sidebar-lojista_sest.css'; // CSS da sidebar para usuários senat=sim
    }
    ?>
    <link rel="stylesheet" href="../../assets/css/views/stores/<?php echo htmlspecialchars($pendingCommissionsCssFile); ?>">
    <link rel="stylesheet" href="../../assets/css/openpix-styles.css">

    <link rel="stylesheet" href="/assets/css/<?php echo htmlspecialchars($sidebarCssFile); ?>">
</head>
<body>
    <?php include '../../views/components/sidebar-lojista-responsiva.php'; ?>
    
    <div class="container main-content">
        <div class="dashboard-wrapper">
            <div class="dashboard-header">
                <h1>Comissões Pendentes</h1>
                <p class="subtitle">Gerenciar comissões pendentes de pagamento para <?php echo htmlspecialchars($storeName); ?></p>
            </div>
            
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-card-title">Transações Pendentes</div>
                    <div class="stat-card-value"><?php echo number_format($totalTransacoes); ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-title">Valor Total de Vendas</div>
                    <div class="stat-card-value">R$ <?php echo number_format($totalValorVendas, 2, ',', '.'); ?></div>
                    <div class="stat-card-subtitle">Valor original das vendas</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-title">Total Saldo Usado</div>
                    <div class="stat-card-value">R$ <?php echo number_format($totalSaldoUsado, 2, ',', '.'); ?></div>
                    <div class="stat-card-subtitle">Desconto aplicado pelos clientes</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-title">Comissão Total a Pagar</div>
                    <div class="stat-card-value">R$ <?php echo number_format($totalValorComissoes, 2, ',', '.'); ?></div>
                    <div class="stat-card-subtitle">Valor total devido ao Klube Cash</div>
                </div>
            </div>
            
            <div class="card filter-container">
                <div class="card-header">
                    <div class="card-title">Filtros</div>
                </div>
                <div class="filter-form">
                    <form method="GET" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="data_inicio">Data Início</label>
                                <input type="date" id="data_inicio" name="data_inicio" value="<?php echo isset($_GET['data_inicio']) ? htmlspecialchars($_GET['data_inicio']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="data_fim">Data Fim</label>
                                <input type="date" id="data_fim" name="data_fim" value="<?php echo isset($_GET['data_fim']) ? htmlspecialchars($_GET['data_fim']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="valor_min">Valor Mínimo</label>
                                <input type="number" id="valor_min" name="valor_min" step="0.01" min="0" value="<?php echo isset($_GET['valor_min']) ? htmlspecialchars($_GET['valor_min']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="valor_max">Valor Máximo</label>
                                <input type="number" id="valor_max" name="valor_max" step="0.01" min="0" value="<?php echo isset($_GET['valor_max']) ? htmlspecialchars($_GET['valor_max']) : ''; ?>">
                            </div>
                            
                            <div class="form-buttons">
                                <button type="submit" class="btn btn-primary">Filtrar</button>
                                <a href="<?php echo STORE_PENDING_TRANSACTIONS_URL; ?>" class="btn btn-secondary">Limpar</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card transactions-container">
                <div class="card-header">
                    <div class="card-title">Transações Pendentes de Pagamento</div>
                    <?php if ($totalTransacoes > 0): ?>
                    <div style="display: flex; gap: 1rem;">
                        <!--<button id="paySelectedBtn" class="btn btn-primary" disabled>Pagar Selecionadas</button>-->
                        <button id="payPixBtn" class="btn btn-success" disabled>Pagar via PIX</button>
                        <!--<button type="button" class="btn btn-success" id="btnPixOpenpix">
                            🔥 Pagar via PIX 2
                        </button>-->
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($result['status'] && count($result['data']['transacoes']) > 0): ?>
                    <form id="paymentForm" method="POST" action="<?php echo STORE_PAYMENT_URL; ?>">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th width="40">
                                            <input type="checkbox" id="selectAll">
                                        </th>
                                        <th>Código</th>
                                        <th>Cliente</th>
                                        <th>Data</th>
                                        <th>Valor Original</th>
                                        <th>Saldo Usado</th>
                                        <th>Valor Cobrado</th>
                                        <th>Comissão Total</th>
                                        <th>Cashback Cliente</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($result['data']['transacoes'])): ?>
                                        <tr>
                                            <td colspan="9" style="text-align: center;">Nenhuma transação encontrada</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($result['data']['transacoes'] as $transaction): ?>
                                            <?php 
                                            $valorOriginal = floatval($transaction['valor_total']);
                                            $saldoUsado = floatval($transaction['saldo_usado'] ?? 0);
                                            $valorCobrado = $valorOriginal - $saldoUsado;
                                            
                                            // CORREÇÃO: Usar o valor_cashback da transação como Comissão Total a Pagar
                                            $comissaoTotal = floatval($transaction['valor_cashback']);
                                            $cashbackCliente = floatval($transaction['valor_cliente']);
                                            ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="transacoes[]" value="<?php echo $transaction['id']; ?>" 
                                                        class="transaction-checkbox" 
                                                        data-value="<?php echo number_format($comissaoTotal, 2, '.', ''); ?>">
                                                </td>
                                                <td><?php echo htmlspecialchars($transaction['codigo_transacao'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($transaction['cliente_nome']); ?>
                                                    <?php if ($saldoUsado > 0): ?>
                                                        <span class="balance-used-badge" title="Cliente usou saldo">💰</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($transaction['data_transacao'])); ?></td>
                                                <td>R$ <?php echo number_format($valorOriginal, 2, ',', '.'); ?></td>
                                                <td>
                                                    <?php if ($saldoUsado > 0): ?>
                                                        <span class="saldo-usado">R$ <?php echo number_format($saldoUsado, 2, ',', '.'); ?></span>
                                                    <?php else: ?>
                                                        <span class="sem-saldo">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong>R$ <?php echo number_format($valorCobrado, 2, ',', '.'); ?></strong>
                                                    <?php if ($valorCobrado < $valorOriginal): ?>
                                                        <small class="desconto">(com desconto)</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><strong>R$ <?php echo number_format($comissaoTotal, 2, ',', '.'); ?></strong></td>
                                                <td>R$ <?php echo number_format($cashbackCliente, 2, ',', '.'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <input type="hidden" name="loja_id" value="<?php echo $storeId; ?>">
                        <input type="hidden" name="action" value="payment_form">
                        
                        <div class="payment-summary" id="paymentSummary">
                            <div class="summary-content">
                                <div class="summary-item">
                                    <span class="label">Transações selecionadas:</span>
                                    <span class="value" id="selectedCount">0</span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Valor total das vendas:</span>
                                    <span class="value" id="totalSalesValue">R$ 0,00</span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Total saldo usado:</span>
                                    <span class="value" id="totalBalanceUsed">R$ 0,00</span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Valor total a pagar:</span>
                                    <span class="value" id="totalCommissionValue">R$ 0,00</span>
                                </div>
                            </div>
                        </div>
                    </form>
                    
                    <?php if ($result['data']['paginacao']['total_paginas'] > 1): ?>
                        <div class="pagination">
                            <div class="pagination-info">
                                Página <?php echo $result['data']['paginacao']['pagina_atual']; ?> de <?php echo $result['data']['paginacao']['total_paginas']; ?>
                            </div>
                            <div class="pagination-links">
                                <?php if ($result['data']['paginacao']['pagina_atual'] > 1): ?>
                                    <a href="?page=1<?php echo isset($_GET['data_inicio']) ? '&data_inicio=' . urlencode($_GET['data_inicio']) : ''; ?><?php echo isset($_GET['data_fim']) ? '&data_fim=' . urlencode($_GET['data_fim']) : ''; ?><?php echo isset($_GET['valor_min']) ? '&valor_min=' . urlencode($_GET['valor_min']) : ''; ?><?php echo isset($_GET['valor_max']) ? '&valor_max=' . urlencode($_GET['valor_max']) : ''; ?>" class="page-link">Primeira</a>
                                    <a href="?page=<?php echo $result['data']['paginacao']['pagina_atual'] - 1; ?><?php echo isset($_GET['data_inicio']) ? '&data_inicio=' . urlencode($_GET['data_inicio']) : ''; ?><?php echo isset($_GET['data_fim']) ? '&data_fim=' . urlencode($_GET['data_fim']) : ''; ?><?php echo isset($_GET['valor_min']) ? '&valor_min=' . urlencode($_GET['valor_min']) : ''; ?><?php echo isset($_GET['valor_max']) ? '&valor_max=' . urlencode($_GET['valor_max']) : ''; ?>" class="page-link">Anterior</a>
                                <?php endif; ?>
                                
                                <?php if ($result['data']['paginacao']['pagina_atual'] < $result['data']['paginacao']['total_paginas']): ?>
                                    <a href="?page=<?php echo $result['data']['paginacao']['pagina_atual'] + 1; ?><?php echo isset($_GET['data_inicio']) ? '&data_inicio=' . urlencode($_GET['data_inicio']) : ''; ?><?php echo isset($_GET['data_fim']) ? '&data_fim=' . urlencode($_GET['data_fim']) : ''; ?><?php echo isset($_GET['valor_min']) ? '&valor_min=' . urlencode($_GET['valor_min']) : ''; ?><?php echo isset($_GET['valor_max']) ? '&valor_max=' . urlencode($_GET['valor_max']) : ''; ?>" class="page-link">Próxima</a>
                                    <a href="?page=<?php echo $result['data']['paginacao']['total_paginas']; ?><?php echo isset($_GET['data_inicio']) ? '&data_inicio=' . urlencode($_GET['data_inicio']) : ''; ?><?php echo isset($_GET['data_fim']) ? '&data_fim=' . urlencode($_GET['data_fim']) : ''; ?><?php echo isset($_GET['valor_min']) ? '&valor_min=' . urlencode($_GET['valor_min']) : ''; ?><?php echo isset($_GET['valor_max']) ? '&valor_max=' . urlencode($_GET['valor_max']) : ''; ?>" class="page-link">Última</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                        </div>
                        <h3>Nenhuma comissão pendente</h3>
                        <p>Não existem transações pendentes de pagamento no momento.</p>
                        <a href="<?php echo STORE_REGISTER_TRANSACTION_URL; ?>" class="btn btn-primary">Registrar Nova Venda</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="card info-card collapsible-card">
                <div class="card-header collapsible-header" onclick="toggleInfoSection()">
                    <div class="card-title">
                        <span>📋 Informações sobre Saldo e Comissões</span>
                        <span class="dropdown-icon" id="infoDropdownIcon">▼</span>
                    </div>
                </div>
                <div class="collapsible-content" id="infoSectionContent" style="display: none;">
                    <div class="info-content">
                        <div class="info-section">
                            <h4>📊 Como são calculadas as comissões:</h4>
                            <ul>
                                <li>A comissão é de <strong>10%</strong> calculada apenas sobre o valor efetivamente cobrado do cliente</li>
                                <li>Se o cliente usou saldo, o valor é descontado antes do cálculo da comissão</li>
                                <li>Exemplo: Venda de R$ 100,00 - Saldo usado R$ 20,00 = Comissão sobre R$ 80,00 (R$ 8,00)</li>
                                <li><strong>Sua loja não recebe cashback</strong> - você apenas paga a comissão</li>
                            </ul>
                        </div>
                        
                        <div class="info-section">
                            <h4>💰 Sobre o uso de saldo pelo cliente:</h4>
                            <ul>
                                <li>Clientes podem usar o cashback recebido para desconto em novas compras <strong>na sua loja</strong></li>
                                <li>O saldo usado é identificado pelo ícone 💰 ao lado do nome do cliente</li>
                                <li>O cliente ainda recebe cashback normal sobre o valor que ele efetivamente pagou</li>
                                <li>Você paga comissão apenas sobre o valor que efetivamente recebeu</li>
                            </ul>
                        </div>
                        
                        <div class="info-section">
                            <h4>🔔 Distribuição dos 10% de comissão:</h4>
                            <ul>
                                <li><strong>5% para o cliente:</strong> Vira cashback disponível para usar na sua loja</li>
                                <li><strong>5% para o Klube Cash:</strong> Nossa receita pela plataforma</li>
                                <li><strong>0% para sua loja:</strong> Você não recebe cashback, apenas oferece o benefício</li>
                            </ul>
                        </div>
                        
                        <div class="info-section">
                            <h4>🔄 Processo de pagamento:</h4>
                            <ul>
                                <li>Selecione as transações que deseja quitar</li>
                                <li>O valor total será a soma das comissões de todas as transações selecionadas</li>
                                <li>Após o pagamento e aprovação, o cashback será liberado para os clientes</li>
                                <li>Clientes poderão usar o cashback apenas na sua loja</li>
                            </ul>
                        </div>
                        
                        <div class="info-section">
                            <h4>ℹ️ Dicas Importantes:</h4>
                            <ul>
                                <li>Realize pagamentos regularmente para manter o fluxo de cashback dos clientes</li>
                                <li>Monitore vendas com uso de saldo - indicam clientes fidelizados</li>
                                <li>O valor da economia gerada aos clientes também beneficia sua loja com mais vendas</li>
                                <li>Clientes com saldo disponível tendem a retornar mais à sua loja</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../../assets/js/openpix-integration.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAllCheckbox = document.getElementById('selectAll');
        const transactionCheckboxes = document.querySelectorAll('.transaction-checkbox');
        const paySelectedBtn = document.getElementById('paySelectedBtn');
        const payPixBtn = document.getElementById('payPixBtn');
        const btnPixOpenpix = document.getElementById('btnPixOpenpix');
        const paymentForm = document.getElementById('paymentForm');
        const paymentSummary = document.getElementById('paymentSummary');
        
        function formatCurrency(value) {
            return value.toLocaleString('pt-BR', {
                style: 'currency',
                currency: 'BRL',
                minimumFractionDigits: 2
            });
        }
        
        function updatePaymentSummary() {
            const selectedCheckboxes = document.querySelectorAll('.transaction-checkbox:checked');
            const selectedCount = selectedCheckboxes.length;
            let totalCommission = 0;
            let totalSalesValue = 0;
            let totalBalanceUsed = 0;
            
            selectedCheckboxes.forEach(checkbox => {
                const commission = parseFloat(checkbox.getAttribute('data-value'));
                totalCommission += commission;
                
                const row = checkbox.closest('tr');
                const cells = row.querySelectorAll('td');
                
                const originalValueText = cells[4].textContent.replace('R$ ', '').replace(/\./g, '').replace(',', '.');
                const originalValue = parseFloat(originalValueText);
                
                const balanceUsedElement = cells[5].querySelector('.saldo-usado');
                const balanceUsed = balanceUsedElement ? 
                    parseFloat(balanceUsedElement.textContent.replace('R$ ', '').replace(/\./g, '').replace(',', '.')) : 0;
                
                totalSalesValue += originalValue;
                totalBalanceUsed += balanceUsed;
            });
            
            document.getElementById('selectedCount').textContent = selectedCount;
            document.getElementById('totalSalesValue').textContent = formatCurrency(totalSalesValue);
            document.getElementById('totalBalanceUsed').textContent = formatCurrency(totalBalanceUsed);
            document.getElementById('totalCommissionValue').textContent = formatCurrency(totalCommission);
            
            if (paySelectedBtn) paySelectedBtn.disabled = selectedCount === 0;
            if (payPixBtn) payPixBtn.disabled = selectedCount === 0;
            if (btnPixOpenpix) btnPixOpenpix.disabled = selectedCount === 0;
            
            if (selectedCount > 0) {
                paymentSummary.style.display = 'block';
            } else {
                paymentSummary.style.display = 'none';
            }
        }
        
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                transactionCheckboxes.forEach(checkbox => {
                    checkbox.checked = selectAllCheckbox.checked;
                });
                updatePaymentSummary();
            });
        }
        
        transactionCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allChecked = Array.from(transactionCheckboxes).every(cb => cb.checked);
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = allChecked;
                }
                updatePaymentSummary();
            });
        });
        
        if (paySelectedBtn) {
            paySelectedBtn.addEventListener('click', function() {
                if (document.querySelectorAll('.transaction-checkbox:checked').length > 0) {
                    paymentForm.submit();
                }
            });
        }
        
        if (payPixBtn) {
            payPixBtn.addEventListener('click', function() {
                const selected = document.querySelectorAll('.transaction-checkbox:checked');
                if (selected.length > 0) {
                    createPixPayment();
                }
            });
        }

        // OpenPix Button Handler
        if (btnPixOpenpix) {
            btnPixOpenpix.addEventListener('click', function() {
                const selectedCheckboxes = document.querySelectorAll('.transaction-checkbox:checked');
                
                if (selectedCheckboxes.length === 0) {
                    alert('Selecione pelo menos uma transação para pagar');
                    return;
                }
                
                if (selectedCheckboxes.length > 1) {
                    alert('OpenPix: Selecione apenas uma transação por vez para pagamento via PIX');
                    return;
                }
                
                createPaymentAndUseOpenPix();
            });
        }

        async function createPixPayment() {
            const selectedCheckboxes = document.querySelectorAll('.transaction-checkbox:checked');
            let totalCommission = 0;
            
            selectedCheckboxes.forEach(checkbox => {
                totalCommission += parseFloat(checkbox.getAttribute('data-value'));
            });

            if (totalCommission <= 0) {
                alert('Selecione pelo menos uma transação');
                return;
            }

            const formData = new FormData(paymentForm);
            formData.append('metodo_pagamento', 'pix_mercadopago');
            formData.append('valor_total', totalCommission.toFixed(2));

            try {
                const response = await fetch('../../api/store-payment.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin' 
                });

                const result = await response.json();
                
                if (result.status) {
                    window.location.href = `../../store/pagamento-pix?payment_id=${result.data.payment_id}`;
                } else {
                    alert('Erro: ' + result.message);
                }
            } catch (error) {
                console.error('Erro na requisição:', error);
                alert('Erro de conexão: ' + error.message);
            }
        }

        function createPaymentAndUseOpenPix() {
            const formData = new FormData();
            formData.append('action', 'criar_pagamento');
            formData.append('metodo_pagamento', 'pix_openpix');
            formData.append('loja_id', '<?php echo $storeId; ?>');
            
            document.querySelectorAll('.transaction-checkbox:checked').forEach(checkbox => {
                formData.append('transacoes[]', checkbox.value);
            });
            
            // CORREÇÃO: Usar caminho absoluto
            fetch('/api/payments.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    openPixIntegration.createCharge(data.payment_id);
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(error => {
                alert('Erro de conexão');
                console.error(error);
            });
        }
        
        updatePaymentSummary();
        
        const savedState = localStorage.getItem('pendingCommissionsInfoOpen');
        const content = document.getElementById('infoSectionContent');
        const icon = document.getElementById('infoDropdownIcon');
        const card = content ? content.closest('.collapsible-card') : null;
        
        if (savedState === 'true' && content && icon && card) {
            content.style.display = 'block';
            icon.classList.add('open');
            card.classList.add('expanded');
        }
    });

    function toggleInfoSection() {
        const content = document.getElementById('infoSectionContent');
        const icon = document.getElementById('infoDropdownIcon');
        const card = content.closest('.collapsible-card');
        
        if (content.style.display === 'none' || content.style.display === '') {
            content.style.display = 'block';
            content.classList.add('opening');
            content.classList.remove('closing');
            icon.classList.add('open');
            card.classList.add('expanded');
            
            setTimeout(() => {
                content.classList.remove('opening');
            }, 400);
            
            localStorage.setItem('pendingCommissionsInfoOpen', 'true');
        } else {
            content.classList.add('closing');
            content.classList.remove('opening');
            icon.classList.remove('open');
            card.classList.remove('expanded');
            
            setTimeout(() => {
                content.style.display = 'none';
                content.classList.remove('closing');
            }, 400);
            
            localStorage.setItem('pendingCommissionsInfoOpen', 'false');
        }
    }
    </script>
    
    <style>
        .balance-used-badge {
            margin-left: 5px;
            font-size: 0.8rem;
        }
        
        .saldo-usado {
            color: #28a745;
            font-weight: 600;
        }
        
        .sem-saldo {
            color: #6c757d;
            font-style: italic;
        }
        
        .desconto {
            color: #28a745;
            font-size: 0.8rem;
            display: block;
        }
        
        .info-section {
            margin-bottom: 20px;
        }
        
        .info-section h4 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .info-section ul {
            list-style-type: none;
            padding-left: 0;
        }
        
        .info-section li {
            margin-bottom: 8px;
            padding-left: 20px;
            position: relative;
        }
        
        .info-section li::before {
            content: "•";
            color: #FF7A00;
            font-weight: bold;
            position: absolute;
            left: 0;
        }
        
        .stat-card-subtitle {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 5px;
        }

        .collapsible-card {
            transition: all 0.3s ease;
        }

        .collapsible-header {
            cursor: pointer;
            user-select: none;
            transition: background-color 0.3s ease;
            position: relative;
            padding: 1.25rem 1.5rem;
            border-bottom: none;
        }

        .collapsible-header:hover {
            background-color: #f8f9fa;
        }

        .collapsible-header .card-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--secondary-color);
        }

        .dropdown-icon {
            font-size: 14px;
            font-weight: bold;
            color: var(--primary-color);
            transition: transform 0.3s ease;
            margin-left: 10px;
        }

        .dropdown-icon.open {
            transform: rotate(180deg);
        }

        .collapsible-content {
            overflow: hidden;
            transition: all 0.4s ease;
            border-top: 1px solid #eee;
            margin-top: 0;
        }

        .collapsible-content.opening {
            animation: slideDown 0.4s ease-out;
        }

        .collapsible-content.closing {
            animation: slideUp 0.4s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                max-height: 0;
                padding-top: 0;
                padding-bottom: 0;
            }
            to {
                opacity: 1;
                max-height: 1000px;
                padding-top: 20px;
                padding-bottom: 20px;
            }
        }

        @keyframes slideUp {
            from {
                opacity: 1;
                max-height: 1000px;
                padding-top: 20px;
                padding-bottom: 20px;
            }
            to {
                opacity: 0;
                max-height: 0;
                padding-top: 0;
                padding-bottom: 0;
            }
        }

        .collapsible-card.expanded {
            border-left: 4px solid var(--primary-color);
        }

        .collapsible-card.expanded .collapsible-header {
            background-color: var(--primary-light);
        }

        .info-content {
            padding: 1.5rem;
            color: var(--medium-gray);
        }

        .info-section {
            margin-bottom: 25px;
        }

        .info-section:last-child {
            margin-bottom: 0;
        }

        .info-section h4 {
            color: #333;
            margin-bottom: 12px;
            font-size: 1rem;
            font-weight: 600;
        }

        .info-section ul {
            list-style-type: none;
            padding-left: 0;
            margin: 0;
        }

        .info-section li {
            margin-bottom: 10px;
            padding-left: 20px;
            position: relative;
            line-height: 1.5;
        }

        .info-section li::before {
            content: "•";
            color: var(--primary-color);
            font-weight: bold;
            position: absolute;
            left: 0;
            top: 0;
        }

        @media (max-width: 768px) {
            .collapsible-header {
                padding: 1rem;
            }
            
            .collapsible-header .card-title {
                font-size: 1rem;
            }
            
            .dropdown-icon {
                font-size: 12px;
            }
            
            .info-content {
                padding: 1rem;
            }
            
            .info-section h4 {
                font-size: 0.9rem;
            }
            
            .info-section li {
                font-size: 0.9rem;
                margin-bottom: 8px;
            }
        }

        @media (max-width: 575.98px) {
            .collapsible-header .card-title span:first-child {
                font-size: 0.95rem;
            }
            
            .info-section li {
                padding-left: 15px;
            }
        }

        .collapsible-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background-color: var(--primary-color);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .collapsible-card.expanded .collapsible-header::after {
            width: 90%;
        }

        .info-section li strong {
            color: var(--primary-color);
            font-weight: 600;
        }

        .info-section h4::before {
            margin-right: 8px;
        }
    </style>
    <script>
// Teste de sessão
function testSession() {
    console.log('=== TESTE DE SESSÃO ===');
    
    fetch('/test-session.php', {
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        console.log('1. Status da sessão:', data);
        
        // Teste API
        const formData = new FormData();
        formData.append('action', 'test');
        
        return fetch('../../api/payments.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
    })
    .then(response => response.json())
    .then(data => {
        console.log('2. Resposta da API:', data);
    })
    .catch(error => {
        console.error('Erro no teste:', error);
    });
}

// Criar botão após DOM carregado

</script>
<script src="/assets/js/sidebar-lojista.js"></script>
</body>
</html>