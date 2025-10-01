<?php
// views/admin/transactions.php - Gestão Moderna de Transações
$activeMenu = 'transacoes';

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../controllers/AuthController.php';
require_once '../../controllers/AdminController.php';
require_once '../../models/CashbackBalance.php';

session_start();

// Verificar autenticação
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== USER_TYPE_ADMIN) {
    header("Location: " . LOGIN_URL . "?error=acesso_restrito");
    exit;
}

// Inicializar variáveis
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$filters = [];
$bulkAction = '';

// Processar ações em lote
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && isset($_POST['selected_transactions'])) {
    try {
        $db = Database::getConnection();
        $selectedIds = array_map('intval', $_POST['selected_transactions']);
        $action = $_POST['bulk_action'];
        
        switch ($action) {
            case 'approve':
                $stmt = $db->prepare("UPDATE transacoes_cashback SET status = 'aprovado', data_aprovacao = NOW() WHERE id IN (" . implode(',', $selectedIds) . ")");
                $stmt->execute();
                $message = count($selectedIds) . " transação(ões) aprovada(s) com sucesso!";
                $messageType = 'success';
                break;
                
            case 'cancel':
                $stmt = $db->prepare("UPDATE transacoes_cashback SET status = 'cancelado', data_cancelamento = NOW() WHERE id IN (" . implode(',', $selectedIds) . ")");
                $stmt->execute();
                $message = count($selectedIds) . " transação(ões) cancelada(s) com sucesso!";
                $messageType = 'success';
                break;
                
            case 'export':
                // Implementar exportação das transações selecionadas
                $message = "Exportação iniciada para " . count($selectedIds) . " transação(ões)";
                $messageType = 'info';
                break;
        }
    } catch (Exception $e) {
        $message = "Erro ao processar ação em lote: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Processar filtros
if (isset($_GET['data_inicio']) && !empty($_GET['data_inicio'])) {
    $filters['data_inicio'] = $_GET['data_inicio'];
}
if (isset($_GET['data_fim']) && !empty($_GET['data_fim'])) {
    $filters['data_fim'] = $_GET['data_fim'];
}
if (isset($_GET['loja_id']) && !empty($_GET['loja_id'])) {
    $filters['loja_id'] = $_GET['loja_id'];
}
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}
if (isset($_GET['busca']) && !empty($_GET['busca'])) {
    $filters['busca'] = $_GET['busca'];
}
if (isset($_GET['tipo_pagamento']) && !empty($_GET['tipo_pagamento'])) {
    $filters['tipo_pagamento'] = $_GET['tipo_pagamento'];
}

try {
    // Obter dados das transações com informações detalhadas
    $result = AdminController::manageTransactionsWithBalance($filters, $page);
    
    $hasError = !$result['status'];
    $errorMessage = $hasError ? $result['message'] : '';
    
    $transactions = $hasError ? [] : $result['data']['transacoes'];
    $stores = $hasError ? [] : $result['data']['lojas'];
    $statistics = $hasError ? [] : $result['data']['estatisticas'];
    $pagination = $hasError ? [] : $result['data']['paginacao'];
    
} catch (Exception $e) {
    $hasError = true;
    $errorMessage = "Erro ao processar a requisição: " . $e->getMessage();
    $transactions = [];
    $stores = [];
    $statistics = [];
    $pagination = [];
}

// Funções auxiliares
function formatDate($date) {
    return date('d/m/Y H:i', strtotime($date));
}

function formatCurrency($value) {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function buildQueryString($exclude = []) {
    $params = $_GET;
    foreach ($exclude as $key) {
        unset($params[$key]);
    }
    return $params ? '&' . http_build_query($params) : '';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Transações - Klube Cash</title>
    <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
    <link rel="stylesheet" href="../../assets/css/views/admin/transactions.css">
    <link rel="stylesheet" href="../../assets/css/layout-fix.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include_once '../components/sidebar.php'; ?>
    
    <div class="main-content" id="mainContent">
        <div class="page-wrapper">
            <!-- Header Executivo -->
            <div class="page-header">
                <div class="header-content">
                    <div class="header-main">
                        <h1 class="page-title">
                            <i class="fas fa-exchange-alt"></i>
                            Gestão de Transações
                        </h1>
                        <p class="page-subtitle">Painel completo para gerenciar e analisar todas as transações do sistema</p>
                        
                        <!-- Breadcrumb -->
                        <nav class="breadcrumb">
                            <a href="/admin/dashboard"><i class="fas fa-home"></i> Dashboard</a>
                            <i class="fas fa-chevron-right"></i>
                            <span>Transações</span>
                        </nav>
                    </div>
                    <div class="header-actions">
                        <button class="btn-action" onclick="refreshData()">
                            <i class="fas fa-sync-alt"></i>
                            Atualizar
                        </button>
                        <button class="btn-action" onclick="exportAllData()">
                            <i class="fas fa-file-export"></i>
                            Exportar
                        </button>
                        <div class="view-toggle">
                            <button class="toggle-btn active" data-view="table">
                                <i class="fas fa-table"></i>
                            </button>
                            <button class="toggle-btn" data-view="cards">
                                <i class="fas fa-th-large"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alertas -->
            <?php if (isset($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'error' ? 'exclamation-triangle' : 'info-circle'); ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($hasError): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Erro:</strong> <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php else: ?>

            <!-- KPIs Dashboard -->
            <div class="kpi-dashboard">
                <div class="kpi-card total">
                    <div class="kpi-header">
                        <div class="kpi-icon">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="kpi-trend positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>+15.2%</span>
                        </div>
                    </div>
                    <div class="kpi-content">
                        <div class="kpi-value"><?php echo number_format($statistics['total_transacoes'] ?? 0); ?></div>
                        <div class="kpi-label">Total de Transações</div>
                        <div class="kpi-subtitle">No período selecionado</div>
                    </div>
                </div>

                <div class="kpi-card revenue">
                    <div class="kpi-header">
                        <div class="kpi-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="kpi-trend positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>+8.7%</span>
                        </div>
                    </div>
                    <div class="kpi-content">
                        <div class="kpi-value"><?php echo formatCurrency($statistics['valor_vendas_originais'] ?? 0); ?></div>
                        <div class="kpi-label">Volume Total</div>
                        <div class="kpi-subtitle">Valor bruto transacionado</div>
                    </div>
                </div>

                <div class="kpi-card cashback">
                    <div class="kpi-header">
                        <div class="kpi-icon">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="kpi-trend positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>+22.3%</span>
                        </div>
                    </div>
                    <div class="kpi-content">
                        <div class="kpi-value"><?php echo formatCurrency($statistics['total_saldo_usado'] ?? 0); ?></div>
                        <div class="kpi-label">Saldo Utilizado</div>
                        <div class="kpi-subtitle">Economia dos clientes</div>
                    </div>
                </div>

                <div class="kpi-card efficiency">
                    <div class="kpi-header">
                        <div class="kpi-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="kpi-trend neutral">
                            <i class="fas fa-minus"></i>
                            <span>0.0%</span>
                        </div>
                    </div>
                    <div class="kpi-content">
                        <div class="kpi-value"><?php echo number_format($statistics['percentual_uso_saldo'] ?? 0, 1); ?>%</div>
                        <div class="kpi-label">Taxa de Uso</div>
                        <div class="kpi-subtitle">Transações com saldo</div>
                    </div>
                </div>

                <div class="kpi-card net">
                    <div class="kpi-header">
                        <div class="kpi-icon">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <div class="kpi-trend positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>+12.1%</span>
                        </div>
                    </div>
                    <div class="kpi-content">
                        <div class="kpi-value"><?php echo formatCurrency($statistics['valor_liquido_pago'] ?? 0); ?></div>
                        <div class="kpi-label">Valor Líquido</div>
                        <div class="kpi-subtitle">Após desconto de saldo</div>
                    </div>
                </div>

                <div class="kpi-card balance">
                    <div class="kpi-header">
                        <div class="kpi-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="kpi-trend positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>+18.9%</span>
                        </div>
                    </div>
                    <div class="kpi-content">
                        <div class="kpi-value"><?php echo formatCurrency($statistics['total_cashback'] ?? 0); ?></div>
                        <div class="kpi-label">Cashback Gerado</div>
                        <div class="kpi-subtitle">Para clientes e sistema</div>
                    </div>
                </div>
            </div>

            <!-- Filtros Avançados -->
            <div class="filters-section">
                <div class="filters-header">
                    <div class="filters-title">
                        <i class="fas fa-filter"></i>
                        <h3>Filtros Inteligentes</h3>
                    </div>
                    <div class="filters-actions">
                        <button class="filter-preset active" onclick="setQuickFilter('today')">Hoje</button>
                        <button class="filter-preset" onclick="setQuickFilter('week')">7 dias</button>
                        <button class="filter-preset" onclick="setQuickFilter('month')">30 dias</button>
                        <button class="filter-preset" onclick="setQuickFilter('custom')">Personalizado</button>
                    </div>
                </div>

                <form method="GET" action="" id="filtersForm" class="filters-form">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label class="filter-label">
                                <i class="fas fa-calendar-alt"></i>
                                Período
                            </label>
                            <div class="date-range">
                                <input type="date" name="data_inicio" class="filter-input" value="<?php echo $_GET['data_inicio'] ?? ''; ?>" placeholder="Data início">
                                <span class="date-separator">até</span>
                                <input type="date" name="data_fim" class="filter-input" value="<?php echo $_GET['data_fim'] ?? ''; ?>" placeholder="Data fim">
                            </div>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">
                                <i class="fas fa-store"></i>
                                Loja
                            </label>
                            <select name="loja_id" class="filter-select">
                                <option value="">Todas as lojas</option>
                                <?php foreach ($stores as $store): ?>
                                    <option value="<?php echo $store['id']; ?>" <?php echo (isset($_GET['loja_id']) && $_GET['loja_id'] == $store['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($store['nome_fantasia']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">
                                <i class="fas fa-info-circle"></i>
                                Status
                            </label>
                            <select name="status" class="filter-select">
                                <option value="">Todos os status</option>
                                <option value="pendente" <?php echo (isset($_GET['status']) && $_GET['status'] === 'pendente') ? 'selected' : ''; ?>>Pendente</option>
                                <option value="aprovado" <?php echo (isset($_GET['status']) && $_GET['status'] === 'aprovado') ? 'selected' : ''; ?>>Aprovado</option>
                                <option value="cancelado" <?php echo (isset($_GET['status']) && $_GET['status'] === 'cancelado') ? 'selected' : ''; ?>>Cancelado</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">
                                <i class="fas fa-credit-card"></i>
                                Tipo de Pagamento
                            </label>
                            <select name="tipo_pagamento" class="filter-select">
                                <option value="">Todos os tipos</option>
                                <option value="com_saldo" <?php echo (isset($_GET['tipo_pagamento']) && $_GET['tipo_pagamento'] === 'com_saldo') ? 'selected' : ''; ?>>Com uso de saldo</option>
                                <option value="sem_saldo" <?php echo (isset($_GET['tipo_pagamento']) && $_GET['tipo_pagamento'] === 'sem_saldo') ? 'selected' : ''; ?>>Sem uso de saldo</option>
                                <option value="mvp" <?php echo (isset($_GET['tipo_pagamento']) && $_GET['tipo_pagamento'] === 'mvp') ? 'selected' : ''; ?>>Lojas MVP</option>
                            </select>
                        </div>

                        <div class="filter-group search-group">
                            <label class="filter-label">
                                <i class="fas fa-search"></i>
                                Buscar
                            </label>
                            <div class="search-input-wrapper">
                                <input type="text" name="busca" class="filter-input search-input" 
                                       placeholder="ID, cliente, loja, valor..." 
                                       value="<?php echo htmlspecialchars($_GET['busca'] ?? ''); ?>">
                                <button type="submit" class="search-btn">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="filters-actions">
                        <button type="button" class="btn-secondary" onclick="clearFilters()">
                            <i class="fas fa-eraser"></i>
                            Limpar Filtros
                        </button>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-filter"></i>
                            Aplicar Filtros
                        </button>
                    </div>
                </form>
            </div>

            <!-- Ações em Lote -->
            <div class="bulk-actions" id="bulkActions" style="display: none;">
                <form method="POST" id="bulkForm">
                    <div class="bulk-info">
                        <i class="fas fa-check-square"></i>
                        <span id="selectedCount">0</span> transação(ões) selecionada(s)
                    </div>
                    <div class="bulk-buttons">
                        <select name="bulk_action" class="bulk-select" required>
                            <option value="">Escolha uma ação</option>
                            <option value="approve">Aprovar selecionadas</option>
                            <option value="cancel">Cancelar selecionadas</option>
                            <option value="export">Exportar selecionadas</option>
                        </select>
                        <button type="submit" class="btn-bulk">
                            <i class="fas fa-play"></i>
                            Executar
                        </button>
                        <button type="button" class="btn-cancel" onclick="clearSelection()">
                            <i class="fas fa-times"></i>
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tabela de Transações -->
            <div class="transactions-container" id="transactionsTable">
                <div class="container-header">
                    <div class="header-info">
                        <h3>
                            <i class="fas fa-list"></i>
                            Lista de Transações
                        </h3>
                        <div class="results-info">
                            <?php if (!empty($pagination)): ?>
                                Mostrando <?php echo (($page - 1) * ITEMS_PER_PAGE) + 1; ?> - <?php echo min($page * ITEMS_PER_PAGE, $pagination['total_registros']); ?> 
                                de <?php echo number_format($pagination['total_registros']); ?> transações
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="header-controls">
                        <div class="items-per-page">
                            <label>Itens por página:</label>
                            <select onchange="changeItemsPerPage(this.value)">
                                <option value="25" <?php echo (ITEMS_PER_PAGE == 25) ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo (ITEMS_PER_PAGE == 50) ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo (ITEMS_PER_PAGE == 100) ? 'selected' : ''; ?>>100</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="table-wrapper">
                    <table class="transactions-table">
                        <thead>
                            <tr>
                                <th class="checkbox-column">
                                    <div class="checkbox-wrapper">
                                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                        <label for="selectAll"></label>
                                    </div>
                                </th>
                                <th class="sortable" onclick="sortTable('id')">
                                    ID <i class="fas fa-sort"></i>
                                </th>
                                <th class="sortable" onclick="sortTable('cliente')">
                                    Cliente <i class="fas fa-sort"></i>
                                </th>
                                <th class="sortable" onclick="sortTable('loja')">
                                    Loja <i class="fas fa-sort"></i>
                                </th>
                                <th class="sortable numeric" onclick="sortTable('valor_original')">
                                    Valor Original <i class="fas fa-sort"></i>
                                </th>
                                <th class="sortable numeric" onclick="sortTable('saldo_usado')">
                                    Saldo Usado <i class="fas fa-sort"></i>
                                </th>
                                <th class="sortable numeric" onclick="sortTable('valor_pago')">
                                    Valor Pago <i class="fas fa-sort"></i>
                                </th>
                                <th class="sortable numeric" onclick="sortTable('cashback')">
                                    Cashback <i class="fas fa-sort"></i>
                                </th>
                                <th class="sortable" onclick="sortTable('data')">
                                    Data <i class="fas fa-sort"></i>
                                </th>
                                <th class="sortable" onclick="sortTable('status')">
                                    Status <i class="fas fa-sort"></i>
                                </th>
                                <th class="actions-column">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr class="empty-state">
                                    <td colspan="11">
                                        <div class="empty-content">
                                            <i class="fas fa-inbox"></i>
                                            <h3>Nenhuma transação encontrada</h3>
                                            <p>Não há transações que correspondam aos filtros aplicados.</p>
                                            <button class="btn-primary" onclick="clearFilters()">
                                                <i class="fas fa-eraser"></i>
                                                Limpar Filtros
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $transaction): ?>
                                    <?php 
                                    $saldoUsado = floatval($transaction['saldo_usado'] ?? 0);
                                    $valorOriginal = floatval($transaction['valor_total']);
                                    $valorPago = $valorOriginal - $saldoUsado;
                                    $totalCashback = floatval($transaction['valor_cliente']) + floatval($transaction['valor_admin']) + floatval($transaction['valor_loja']);
                                    ?>
                                    <tr class="transaction-row <?php echo $saldoUsado > 0 ? 'has-balance' : ''; ?>">
                                        <td class="checkbox-column">
                                            <div class="checkbox-wrapper">
                                                <input type="checkbox" id="trans_<?php echo $transaction['id']; ?>" 
                                                       class="transaction-checkbox" value="<?php echo $transaction['id']; ?>">
                                                <label for="trans_<?php echo $transaction['id']; ?>"></label>
                                            </div>
                                        </td>
                                        <td class="id-column">
                                            <div class="transaction-id">
                                                #<?php echo $transaction['id']; ?>
                                                <?php if ($saldoUsado > 0): ?>
                                                    <div class="balance-badge">
                                                        <i class="fas fa-wallet"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="client-column">
                                            <div class="client-info">
                                                <div class="client-name"><?php echo htmlspecialchars($transaction['cliente_nome']); ?></div>
                                                <?php if (!empty($transaction['cliente_email'])): ?>
                                                    <div class="client-email"><?php echo htmlspecialchars($transaction['cliente_email']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="store-column">
                                            <div class="store-info">
                                                <div class="store-name"><?php echo htmlspecialchars($transaction['loja_nome']); ?></div>
                                                <?php if (isset($transaction['loja_categoria']) && !empty($transaction['loja_categoria'])): ?>
                                                    <div class="store-category"><?php echo htmlspecialchars($transaction['loja_categoria']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="numeric value-original">
                                            <?php echo formatCurrency($valorOriginal); ?>
                                        </td>
                                        <td class="numeric value-used">
                                            <?php if ($saldoUsado > 0): ?>
                                                <span class="negative">-<?php echo formatCurrency($saldoUsado); ?></span>
                                                <div class="economy-indicator">
                                                    <i class="fas fa-piggy-bank"></i>
                                                    Economizou
                                                </div>
                                            <?php else: ?>
                                                <span class="no-value">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="numeric value-paid">
                                            <span class="main-value"><?php echo formatCurrency($valorPago); ?></span>
                                            <?php if ($saldoUsado > 0): ?>
                                                <div class="savings-percent">
                                                    -<?php echo number_format(($saldoUsado / $valorOriginal) * 100, 1); ?>%
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="numeric cashback-column">
                                            <div class="cashback-breakdown">
                                                <div class="main-cashback"><?php echo formatCurrency($totalCashback); ?></div>
                                                <div class="cashback-detail">
                                                    <small>
                                                        Cliente: <?php echo formatCurrency($transaction['valor_cliente']); ?>
                                                        <?php if ($transaction['valor_admin'] > 0): ?>
                                                            | Admin: <?php echo formatCurrency($transaction['valor_admin']); ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="date-column">
                                            <div class="date-info">
                                                <div class="date-main"><?php echo date('d/m/Y', strtotime($transaction['data_transacao'])); ?></div>
                                                <div class="time-info"><?php echo date('H:i', strtotime($transaction['data_transacao'])); ?></div>
                                            </div>
                                        </td>
                                        <td class="status-column">
                                            <?php 
                                            $statusClass = [
                                                'aprovado' => 'status-approved',
                                                'pendente' => 'status-pending',
                                                'cancelado' => 'status-canceled'
                                            ][$transaction['status']] ?? 'status-pending';
                                            
                                            $statusText = [
                                                'aprovado' => 'Aprovado',
                                                'pendente' => 'Pendente',
                                                'cancelado' => 'Cancelado'
                                            ][$transaction['status']] ?? ucfirst($transaction['status']);
                                            ?>
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </td>
                                        <td class="actions-column">
                                            <div class="action-buttons">
                                                <button class="btn-action-small" onclick="viewTransaction(<?php echo $transaction['id']; ?>)" title="Visualizar detalhes">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($transaction['status'] === 'pendente'): ?>
                                                    <button class="btn-action-small approve" onclick="approveTransaction(<?php echo $transaction['id']; ?>)" title="Aprovar">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button class="btn-action-small cancel" onclick="cancelTransaction(<?php echo $transaction['id']; ?>)" title="Cancelar">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <div class="dropdown">
                                                    <button class="btn-action-small" onclick="toggleDropdown(this)" title="Mais opções">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <div class="dropdown-content">
                                                        <a href="#" onclick="exportTransaction(<?php echo $transaction['id']; ?>)">
                                                            <i class="fas fa-download"></i>
                                                            Exportar
                                                        </a>
                                                        <a href="#" onclick="duplicateTransaction(<?php echo $transaction['id']; ?>)">
                                                            <i class="fas fa-copy"></i>
                                                            Duplicar
                                                        </a>
                                                        <a href="#" onclick="viewHistory(<?php echo $transaction['id']; ?>)">
                                                            <i class="fas fa-history"></i>
                                                            Histórico
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Paginação Moderna -->
            <?php if (!empty($pagination) && $pagination['total_paginas'] > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-info">
                        Mostrando <?php echo (($page - 1) * ITEMS_PER_PAGE) + 1; ?> - <?php echo min($page * ITEMS_PER_PAGE, $pagination['total_registros']); ?> 
                        de <?php echo number_format($pagination['total_registros']); ?> resultados
                    </div>
                    
                    <nav class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=1<?php echo buildQueryString(['page']); ?>" class="page-link first" title="Primeira página">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="?page=<?php echo $page - 1; ?><?php echo buildQueryString(['page']); ?>" class="page-link prev" title="Página anterior">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php 
                        $startPage = max(1, $page - 2);
                        $endPage = min($pagination['total_paginas'], $startPage + 4);
                        if ($endPage - $startPage < 4) {
                            $startPage = max(1, $endPage - 4);
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++): 
                        ?>
                            <a href="?page=<?php echo $i; ?><?php echo buildQueryString(['page']); ?>" 
                               class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $pagination['total_paginas']): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo buildQueryString(['page']); ?>" class="page-link next" title="Próxima página">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="?page=<?php echo $pagination['total_paginas']; ?><?php echo buildQueryString(['page']); ?>" class="page-link last" title="Última página">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>

            <!-- Insights e Análises -->
            <?php if (!empty($statistics) && $statistics['total_saldo_usado'] > 0): ?>
            <div class="insights-section">
                <div class="insights-header">
                    <h3>
                        <i class="fas fa-chart-pie"></i>
                        Análise de Impacto
                    </h3>
                    <p>Insights sobre o uso de saldo e comportamento dos clientes</p>
                </div>
                
                <div class="insights-grid">
                    <div class="insight-card">
                        <div class="insight-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="insight-content">
                            <div class="insight-value"><?php echo number_format($statistics['transacoes_com_saldo']); ?></div>
                            <div class="insight-label">Clientes usaram saldo</div>
                            <div class="insight-detail">
                                <?php echo number_format(($statistics['transacoes_com_saldo'] / $statistics['total_transacoes']) * 100, 1); ?>% de adoção
                            </div>
                        </div>
                    </div>
                    
                    <div class="insight-card">
                        <div class="insight-icon">
                            <i class="fas fa-piggy-bank"></i>
                        </div>
                        <div class="insight-content">
                            <div class="insight-value"><?php echo formatCurrency($statistics['total_saldo_usado']); ?></div>
                            <div class="insight-label">Economia total dos clientes</div>
                            <div class="insight-detail">
                                Média de <?php echo formatCurrency($statistics['total_saldo_usado'] / $statistics['transacoes_com_saldo']); ?> por uso
                            </div>
                        </div>
                    </div>
                    
                    <div class="insight-card">
                        <div class="insight-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="insight-content">
                            <div class="insight-value"><?php echo number_format(($statistics['total_saldo_usado'] / $statistics['valor_vendas_originais']) * 100, 1); ?>%</div>
                            <div class="insight-label">Redução no faturamento</div>
                            <div class="insight-detail">
                                Impacto do sistema de saldo
                            </div>
                        </div>
                    </div>
                    
                    <div class="insight-card">
                        <div class="insight-icon">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="insight-content">
                            <div class="insight-value"><?php echo formatCurrency($statistics['total_cashback']); ?></div>
                            <div class="insight-label">Cashback distribuído</div>
                            <div class="insight-detail">
                                <?php echo number_format(($statistics['total_cashback'] / $statistics['valor_vendas_originais']) * 100, 1); ?>% do volume total
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>

    <!-- Loading Indicator -->
    <div id="loadingIndicator" style="display: none;">
        <div class="loading-spinner"></div>
    </div>

    <!-- Modal de Detalhes da Transação -->
    <div id="transactionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Detalhes da Transação</h2>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body" id="modalContent">
                <div class="loading-content">
                    <i class="fas fa-spinner fa-spin"></i>
                    Carregando detalhes...
                </div>
            </div>
        </div>
    </div>

    <!-- Include the modern JavaScript -->
    <script src="../../assets/js/views/admin/transactions.js"></script>
    
    <!-- Legacy compatibility scripts -->
    <script>
    // Legacy functions for backward compatibility with existing onclick handlers
    function viewTransaction(id) {
        if (window.transactionManager) {
            window.transactionManager.viewTransaction(id);
        }
    }
    
    function approveTransaction(id) {
        if (window.transactionManager) {
            window.transactionManager.approveTransaction(id);
        }
    }
    
    function cancelTransaction(id) {
        if (window.transactionManager) {
            window.transactionManager.cancelTransaction(id);
        }
    }
    
    function exportTransaction(id) {
        if (window.transactionManager) {
            window.transactionManager.exportTransactions([id]);
        }
    }
    
    function toggleSelectAll() {
        const checkbox = document.getElementById('selectAll');
        if (window.transactionManager && checkbox) {
            window.transactionManager.toggleSelectAll(checkbox.checked);
        }
    }
    
    function clearSelection() {
        if (window.transactionManager) {
            window.transactionManager.selectedTransactions.clear();
            window.transactionManager.updateBulkActions();
            document.querySelectorAll('.transaction-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('selectAll').checked = false;
        }
    }
    
    function clearFilters() {
        // Reset all filter inputs
        document.querySelectorAll('.form-input, .form-select').forEach(input => {
            if (input.type === 'checkbox') {
                input.checked = false;
            } else {
                input.value = '';
            }
        });
        
        if (window.transactionManager) {
            window.transactionManager.currentFilters = {};
            window.transactionManager.loadTransactions();
        }
    }
    
    function sortTable(column) {
        if (window.transactionManager) {
            window.transactionManager.handleSort(column);
        }
    }
    
    function changeItemsPerPage(value) {
        if (window.transactionManager) {
            window.transactionManager.itemsPerPage = parseInt(value);
            window.transactionManager.currentPage = 1;
            window.transactionManager.loadTransactions();
        }
    }
    
    function closeModal() {
        if (window.transactionManager) {
            window.transactionManager.closeModal();
        }
    }
    
    function toggleDropdown(button) {
        const dropdown = button.parentNode;
        const dropdownContent = dropdown.querySelector('.dropdown-content');
        
        // Close all other dropdowns
        document.querySelectorAll('.dropdown-content').forEach(content => {
            if (content !== dropdownContent) {
                content.style.display = 'none';
            }
        });
        
        // Toggle current dropdown
        dropdownContent.style.display = dropdownContent.style.display === 'block' ? 'none' : 'block';
    }
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
        if (!event.target.matches('.btn-action-small')) {
            document.querySelectorAll('.dropdown-content').forEach(content => {
                content.style.display = 'none';
            });
        }
    });
    </script>
</body>
</html>