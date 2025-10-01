<?php
// views/stores/dashboard.php
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../controllers/AuthController.php';
require_once '../../controllers/StoreController.php';
require_once '../../controllers/TransactionController.php';
require_once '../../utils/StoreHelper.php';

// Iniciar sessão
session_start();

// Verificação ultra-simples - substitui TODAS as verificações anteriores
StoreHelper::requireStoreAccess();

// Registrar acesso para auditoria
StoreHelper::logUserAction($_SESSION['user_id'], 'acessou_dashboard', [
    'loja_id' => StoreHelper::getCurrentStoreId()
]);

// Obter dados da loja - funciona para lojista E funcionário
$storeId = StoreHelper::getCurrentStoreId();
$store = AuthController::getStoreData();

if (!$storeId || !$store) {
    header('Location: ' . LOGIN_URL . '?error=' . urlencode('Erro ao acessar dados da loja.'));
    exit;
}

// Obter conexão com banco para as queries
$db = Database::getConnection();
$activeMenu = 'dashboard';

// Obter estatísticas da loja
// 1. Total de vendas registradas
$salesQuery = $db->prepare("
    SELECT COUNT(*) as total_vendas, 
           SUM(valor_total) as valor_total_vendas,
           SUM(valor_cashback) as valor_total_cashback,
           SUM(valor_cliente) as valor_total_cliente,
           SUM(valor_admin) as valor_total_admin
    FROM transacoes_cashback 
    WHERE loja_id = :loja_id
");
$salesQuery->bindParam(':loja_id', $storeId);
$salesQuery->execute();
$salesStats = $salesQuery->fetch(PDO::FETCH_ASSOC);

// 2. Comissões pendentes
$pendingQuery = $db->prepare("
    SELECT COUNT(*) as total_pendentes, 
           SUM(valor_cashback) as valor_pendente,
           SUM(valor_cliente) as valor_cliente_pendente,
           COUNT(DISTINCT usuario_id) as clientes_afetados
    FROM transacoes_cashback 
    WHERE loja_id = :loja_id AND status = :status
");

$paidQuery = $db->prepare("
    SELECT COUNT(*) as total_pagas, 
           SUM(valor_cashback) as valor_pago
    FROM transacoes_cashback 
    WHERE loja_id = :loja_id AND status = :status
");
$paidQuery->bindParam(':loja_id', $storeId);
$status = 'aprovado';
$paidQuery->bindParam(':status', $status);
$paidQuery->execute();
$paidStats = $paidQuery->fetch(PDO::FETCH_ASSOC);

$pendingQuery->bindParam(':loja_id', $storeId);
$status = 'pendente';
$pendingQuery->bindParam(':status', $status);
$pendingQuery->execute();
$pendingStats = $pendingQuery->fetch(PDO::FETCH_ASSOC);

// 4. Últimas transações
$recentQuery = $db->prepare("
    SELECT t.*, u.nome as cliente_nome
    FROM transacoes_cashback t
    JOIN usuarios u ON t.usuario_id = u.id
    WHERE t.loja_id = :loja_id
    ORDER BY t.data_transacao DESC
    LIMIT 5
");
$recentQuery->bindParam(':loja_id', $storeId);
$recentQuery->execute();
$recentTransactions = $recentQuery->fetchAll(PDO::FETCH_ASSOC);

// 5. Estatísticas de vendas por mês (últimos 6 meses)
$monthlyQuery = $db->prepare("
    SELECT 
        DATE_FORMAT(data_transacao, '%Y-%m') as mes,
        COUNT(*) as total_vendas,
        SUM(valor_total) as valor_total,
        SUM(valor_cashback) as valor_cashback
    FROM transacoes_cashback
    WHERE loja_id = :loja_id
    AND data_transacao >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(data_transacao, '%Y-%m')
    ORDER BY mes ASC
");
$monthlyQuery->bindParam(':loja_id', $storeId);
$monthlyQuery->execute();
$monthlyStats = $monthlyQuery->fetchAll(PDO::FETCH_ASSOC);

// Converter estatísticas mensais para formato adequado para gráficos
$chartLabels = [];
$chartData = [];
$monthNames = [
    '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', 
    '04' => 'Abril', '05' => 'Maio', '06' => 'Junho',
    '07' => 'Julho', '08' => 'Agosto', '09' => 'Setembro', 
    '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
];

foreach ($monthlyStats as $stat) {
    $yearMonth = explode('-', $stat['mes']);
    $monthName = $monthNames[$yearMonth[1]] . '/' . substr($yearMonth[0], 2, 2);
    $chartLabels[] = $monthName;
    $chartData[] = floatval($stat['valor_total']);
}

// Definir menu ativo
$activeMenu = 'dashboard';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard da Loja - Klube Cash</title>
    <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
    <?php
    // Determinar qual CSS carregar baseado no campo senat do usuário
    $cssFile = 'dashboard.css'; // CSS padrão
    if (isset($_SESSION['user_senat']) && ($_SESSION['user_senat'] === 'sim' || $_SESSION['user_senat'] === 'Sim')) {
        $cssFile = 'dashboard_sest.css'; // CSS para usuários senat=sim
    }
    ?>
    <link rel="stylesheet" href="../../assets/css/views/stores/<?php echo htmlspecialchars($cssFile); ?>">
    <?php
    // Determinar qual CSS da sidebar carregar baseado no campo senat do usuário
    $sidebarCssFile = 'sidebar-lojista.css'; // CSS da sidebar padrão
    if (isset($_SESSION['user_senat']) && ($_SESSION['user_senat'] === 'sim' || $_SESSION['user_senat'] === 'Sim')) {
        $sidebarCssFile = 'sidebar-lojista_sest.css'; // CSS da sidebar para usuários senat=sim
    }
    ?>
    <link rel="stylesheet" href="/assets/css/<?php echo htmlspecialchars($sidebarCssFile); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Incluir o componente sidebar -->
        <?php include '../../views/components/sidebar-lojista-responsiva.php'; ?>
        
        <div class="main-content" id="mainContent">
            <div class="dashboard-header">
                <div>
                    <h1 class="dashboard-title">Dashboard da Loja</h1>
                    <p class="welcome-user">Bem-vindo(a), <?php echo htmlspecialchars($store['nome_fantasia']); ?></p>
                </div>
            </div>
            
            <!-- Cards de estatísticas -->
            <div class="summary-cards">
                <div class="card">
                    <div class="card-content">
                        <h3>Total de Vendas</h3>
                        <div class="card-value"><?php echo number_format($salesStats['total_vendas'], 0, ',', '.'); ?></div>
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
                        <h3>Valor Total
                            <button class="value-toggle-btn" data-target="valor-total" onclick="toggleValueVisibility('valor-total')">
                                <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                </svg>
                            </button>
                        </h3>
                        <div class="card-value">
                            <span class="hideable-value" data-id="valor-total" data-original="R$ <?php echo number_format($salesStats['valor_total_vendas'], 2, ',', '.'); ?>">R$ <?php echo number_format($salesStats['valor_total_vendas'], 2, ',', '.'); ?></span>
                        </div>
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
                        <h3>Comissões Pendentes (10%)
                            <button class="value-toggle-btn" data-target="comissoes-pendentes" onclick="toggleValueVisibility('comissoes-pendentes')">
                                <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                </svg>
                            </button>
                        </h3>
                        <div class="card-value">
                            <span class="hideable-value" data-id="comissoes-pendentes" data-original="R$ <?php echo number_format($pendingStats['valor_pendente'], 2, ',', '.'); ?>">R$ <?php echo number_format($pendingStats['valor_pendente'], 2, ',', '.'); ?></span>
                        </div>
                        <div class="card-period"><?php echo number_format($pendingStats['total_pendentes'], 0, ',', '.'); ?> transações aguardando pagamento</div>
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
                        <h3>Cashback Gerado (5%)
                            <button class="value-toggle-btn" data-target="cashback-gerado" onclick="toggleValueVisibility('cashback-gerado')">
                                <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                </svg>
                            </button>
                        </h3>
                        <div class="card-value">
                            <span class="hideable-value" data-id="cashback-gerado" data-original="R$ <?php echo number_format($salesStats['valor_total_cliente'], 2, ',', '.'); ?>">R$ <?php echo number_format($salesStats['valor_total_cliente'], 2, ',', '.'); ?></span>
                        </div>
                        <div class="card-period">Destinado aos clientes</div>
                    </div>
                    <div class="card-icon info">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>
                </div>
            </div>
            
            <?php if ($pendingStats['total_pendentes'] > 0): ?>
            <div class="alert warning">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <div>
                    <h4>Comissões Pendentes</h4>
                    <p>Você tem <?php echo $pendingStats['total_pendentes']; ?> transações com pagamento pendente, totalizando R$ <?php echo number_format($pendingStats['valor_pendente'], 2, ',', '.'); ?>. 
                    Esta pendência afeta <?php echo $pendingStats['clientes_afetados']; ?> clientes que aguardam a liberação de R$ <?php echo number_format($pendingStats['valor_cliente_pendente'], 2, ',', '.'); ?> em cashback.</p>
                </div>
                <a href="<?php echo STORE_PENDING_TRANSACTIONS_URL; ?>" class="btn btn-warning">Pagar Comissões</a>
            </div>
            <?php endif; ?>

            
            <!-- Links rápidos para ações -->
            <div class="quick-actions">
                <h2>Ações Rápidas</h2>
                <div class="actions-grid">
                    <a href="<?php echo STORE_REGISTER_TRANSACTION_URL; ?>" class="action-card">
                        <div class="action-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                        </div>
                        <h3>Nova Transação</h3>
                        <p>Registrar uma nova venda</p>
                    </a>
                    <!--
                    <a href="<?php echo STORE_BATCH_UPLOAD_URL; ?>" class="action-card">
                        <div class="action-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                        </div>
                        <h3>Upload em Lote</h3>
                        <p>Importar múltiplas transações</p>
                    </a>-->
                    
                    <a href="<?php echo STORE_PENDING_TRANSACTIONS_URL; ?>" class="action-card">
                        <div class="action-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                        </div>
                        <h3>Comissões Pendentes</h3>
                        <p>Gerenciar pagamentos</p>
                    </a>
                    
                    <a href="<?php echo STORE_PAYMENT_HISTORY_URL; ?>" class="action-card">
                        <div class="action-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                                <line x1="1" y1="10" x2="23" y2="10"></line>
                            </svg>
                        </div>
                        <h3>Histórico de Pagamentos</h3>
                        <p>Visualizar pagamentos realizados</p>
                    </a>
                </div>
            </div>
            
            <!-- Gráficos -->
            <div class="chart-container">
                <h2>Vendas nos Últimos 6 Meses</h2>
                <div class="chart-wrapper">
                    <canvas id="salesChart" data-labels='<?php echo json_encode($chartLabels); ?>' data-data='<?php echo json_encode($chartData); ?>'></canvas>
                </div>
            </div>
            
            <!-- Últimas Transações -->
            <div class="recent-transactions">
                <div class="section-header">
                    <h2>Últimas Transações</h2>
                    <a href="<?php echo STORE_PAYMENT_HISTORY_URL; ?>" class="link-more">Ver Todas</a>
                </div>
                
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Cliente</th>
                                <th>Código</th>
                                <th>Valor
                                    <button class="value-toggle-btn table-header-toggle" data-target="table-valor" onclick="toggleTableColumnVisibility('table-valor')">
                                        <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                        <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                            <line x1="1" y1="1" x2="23" y2="23"></line>
                                        </svg>
                                    </button>
                                </th>
                                <th>Cashback
                                    <button class="value-toggle-btn table-header-toggle" data-target="table-cashback" onclick="toggleTableColumnVisibility('table-cashback')">
                                        <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                        <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                            <line x1="1" y1="1" x2="23" y2="23"></line>
                                        </svg>
                                    </button>
                                </th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($recentTransactions) > 0): ?>
                                <?php foreach ($recentTransactions as $transaction): ?>
                                    <tr>
                                        <td data-label="Data"><?php echo date('d/m/Y H:i', strtotime($transaction['data_transacao'])); ?></td>
                                        <td data-label="Cliente"><?php echo htmlspecialchars($transaction['cliente_nome']); ?></td>
                                        <td data-label="Código"><?php echo htmlspecialchars($transaction['codigo_transacao'] ?? 'N/A'); ?></td>
                                        <td data-label="Valor" class="table-hideable-column" data-column="table-valor">
                                            <span class="hideable-value table-value" data-id="table-valor" data-original="R$ <?php echo number_format($transaction['valor_total'], 2, ',', '.'); ?>">R$ <?php echo number_format($transaction['valor_total'], 2, ',', '.'); ?></span>
                                        </td>
                                        <td data-label="Cashback" class="table-hideable-column" data-column="table-cashback">
                                            <span class="hideable-value table-value" data-id="table-cashback" data-original="R$ <?php echo number_format($transaction['valor_cashback'], 2, ',', '.'); ?>">R$ <?php echo number_format($transaction['valor_cashback'], 2, ',', '.'); ?></span>
                                        </td>
                                        <td data-label="Status">
                                            <span class="status-badge <?php echo $transaction['status']; ?>">
                                                <?php 
                                                    switch ($transaction['status']) {
                                                        case 'pendente': echo 'Pendente'; break;
                                                        case 'aprovado': echo 'Aprovado'; break;
                                                        case 'cancelado': echo 'Cancelado'; break;
                                                        default: echo 'Pendente';
                                                    }
                                                ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="empty-state">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                                            <polyline points="13 2 13 9 20 9"></polyline>
                                        </svg>
                                        <h3>Nenhuma transação registrada</h3>
                                        <p>Comece registrando sua primeira venda com cashback</p>
                                        <a href="<?php echo STORE_REGISTER_TRANSACTION_URL; ?>" class="btn btn-primary">Registrar Venda</a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Adicionar novo card informativo sobre o fluxo de cashback -->
            <div class="info-card">
                <h3>Como Funciona o Sistema de Comissão no Klube Cash</h3>
                <div class="info-content">
                    <div class="info-item">
                        <span class="info-number">1</span>
                        <div>
                            <h4>Registro da Venda</h4>
                            <p>Você registra suas vendas no sistema com o valor total e identificação do cliente</p>
                        </div>
                    </div>
                    <div class="info-item">
                        <span class="info-number">2</span>
                        <div>
                            <h4>Pagamento da Comissão</h4>
                            <p>Você paga 10% de comissão sobre o valor efetivamente cobrado (descontando saldo usado)</p>
                        </div>
                    </div>
                    <div class="info-item">
                        <span class="info-number">3</span>
                        <div>
                            <h4>Distribuição dos 10%</h4>
                            <p>5% vira cashback para o cliente e 5% fica como receita do Klube Cash</p>
                        </div>
                    </div>
                    <div class="info-item">
                        <span class="info-number">4</span>
                        <div>
                            <h4>Liberação do Cashback</h4>
                            <p>Após aprovação do seu pagamento, o cashback é liberado para o cliente usar na sua loja</p>
                        </div>
                    </div>
                </div>
                
                <!-- ADICIONADO: Informação importante -->
                <div class="info-highlight">
                    <strong>💡 Importante:</strong> Sua loja não recebe cashback. O saldo do cliente só pode ser usado na sua própria loja, gerando uma nova comissão sobre o valor efetivamente pago.
                </div>
            </div>
        </div>
    </div>
    
    <script src="../../assets/js/views/stores/dashboard.js"></script>
    <script src="/assets/js/sidebar-lojista.js"></script>
</body>
</html>