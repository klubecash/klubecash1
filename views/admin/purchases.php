<?php
// views/admin/purchases.php
// Definir o menu ativo na sidebar
$activeMenu = 'compras';

// Incluir conexão com o banco de dados e arquivos necessários
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../controllers/AuthController.php';
require_once '../../controllers/AdminController.php';
require_once '../../models/CashbackBalance.php';

// Iniciar sessão
session_start();

// Verificar se o usuário está logado e é administrador
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== USER_TYPE_ADMIN) {
    header("Location: " . LOGIN_URL . "?error=acesso_restrito");
    exit;
}

// Inicializar variáveis de paginação e filtros
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$filters = [];

// Processar filtros se enviados
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

try {
    // Obter dados das transações com informações de saldo
    $result = AdminController::manageTransactionsWithBalance($filters, $page);

    // Verificar se houve erro
    $hasError = !$result['status'];
    $errorMessage = $hasError ? $result['message'] : '';

    // Dados para exibição na página
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

// Função para formatar data
function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

// Função para formatar valor
function formatCurrency($value) {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

// Função auxiliar para construir query string preservando filtros existentes
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
    <title>Compras - Klube Cash</title>
    <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
    <link rel="stylesheet" href="../../assets/css/views/admin/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/views/admin/purchases.css">
    <link rel="stylesheet" href="../../assets/css/layout-fix.css">
    
</head>
<body>
    <?php include_once '../components/sidebar.php'; ?>
    
    <div class="main-content" id="mainContent">
        <div class="page-wrapper">
            <!-- Cabeçalho da Página -->
            <div class="page-header">
                <h1 class="page-title">💳 Compras & Transações</h1>
                <p class="page-subtitle">Gerencie todas as transações e analise o uso de saldo dos clientes</p>
            </div>
            
            <?php if ($hasError): ?>
                <div class="alert alert-danger">
                    <strong>Ops!</strong> <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php else: ?>
            
            <!-- Cards de Estatísticas -->
            <?php if (!empty($statistics)): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Total de Transações</span>
                        <div class="stat-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                                <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($statistics['total_transacoes']); ?></div>
                    <div class="stat-subtitle">Registradas no período</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Valor Original Total</span>
                        <div class="stat-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M16 8l-4 4-2-2"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo formatCurrency($statistics['valor_vendas_originais']); ?></div>
                    <div class="stat-subtitle">Antes de descontos</div>
                </div>
                
                <div class="stat-card balance-card">
                    <div class="stat-header">
                        <span class="stat-title">Saldo Usado</span>
                        <div class="stat-icon success">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="1" x2="12" y2="23"/>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo formatCurrency($statistics['total_saldo_usado']); ?></div>
                    <div class="stat-subtitle">Economia dos clientes</div>
                    <div class="stat-change">
                        <?php echo number_format($statistics['percentual_uso_saldo'], 1); ?>% das transações
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Valor Efetivo Pago</span>
                        <div class="stat-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                                <line x1="1" y1="10" x2="23" y2="10"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo formatCurrency($statistics['valor_liquido_pago']); ?></div>
                    <div class="stat-subtitle">Após uso de saldo</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Cashback Total</span>
                        <div class="stat-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2L2 7v10c0 5.55 3.84 10 9 10s9-4.45 9-10V7l-10-5z"/>
                                <path d="M9 12l2 2 4-4"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo formatCurrency($statistics['total_cashback']); ?></div>
                    <div class="stat-subtitle">Gerado para clientes</div>
                </div>
                
                <div class="stat-card balance-card">
                    <div class="stat-header">
                        <span class="stat-title">Transações c/ Saldo</span>
                        <div class="stat-icon success">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                <polyline points="22,4 12,14.01 9,11.01"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($statistics['transacoes_com_saldo']); ?></div>
                    <div class="stat-subtitle"><?php echo number_format($statistics['percentual_uso_saldo'], 1); ?>% do total</div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Seção de Filtros -->
            <div class="filters-section">
                <div class="filters-header">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                    </svg>
                    <h3>Filtros Avançados</h3>
                </div>
                
                <form method="GET" action="" id="filtersForm">
                    <div class="filters-grid">
                        <!-- Filtro de Data -->
                        <div class="filter-group">
                            <label class="filter-label">Período</label>
                            <select class="filter-input" id="dataFilter" name="data_periodo" onchange="handleDateFilter()">
                                <option value="">Todas as datas</option>
                                <option value="today">Hoje</option>
                                <option value="yesterday">Ontem</option>
                                <option value="last_week">Última semana</option>
                                <option value="last_month">Último mês</option>
                                <option value="custom">Personalizado</option>
                            </select>
                        </div>
                        
                        <!-- Datas Personalizadas -->
                        <div class="filter-group" id="customDatesGroup" style="display: none;">
                            <label class="filter-label">Data Início</label>
                            <input type="date" class="filter-input" name="data_inicio" value="<?php echo $_GET['data_inicio'] ?? ''; ?>">
                        </div>
                        
                        <div class="filter-group" id="customDatesGroup2" style="display: none;">
                            <label class="filter-label">Data Fim</label>
                            <input type="date" class="filter-input" name="data_fim" value="<?php echo $_GET['data_fim'] ?? ''; ?>">
                        </div>
                        
                        <!-- Filtro de Loja -->
                        <div class="filter-group">
                            <label class="filter-label">Loja</label>
                            <select class="filter-input" name="loja_id">
                                <option value="">Todas as lojas</option>
                                <?php foreach ($stores as $store): ?>
                                    <option value="<?php echo $store['id']; ?>" <?php echo (isset($_GET['loja_id']) && $_GET['loja_id'] == $store['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($store['nome_fantasia']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Filtro de Status -->
                        <div class="filter-group">
                            <label class="filter-label">Status</label>
                            <select class="filter-input" name="status">
                                <option value="">Todos os status</option>
                                <option value="pendente" <?php echo (isset($_GET['status']) && $_GET['status'] === 'pendente') ? 'selected' : ''; ?>>Pendente</option>
                                <option value="aprovado" <?php echo (isset($_GET['status']) && $_GET['status'] === 'aprovado') ? 'selected' : ''; ?>>Aprovado</option>
                                <option value="cancelado" <?php echo (isset($_GET['status']) && $_GET['status'] === 'cancelado') ? 'selected' : ''; ?>>Cancelado</option>
                            </select>
                        </div>
                        
                        <!-- Busca -->
                        <div class="search-container">
                            <label class="filter-label">Buscar</label>
                            <div style="position: relative;">
                                <input type="text" class="filter-input search-input" name="busca" placeholder="ID, cliente, loja..." value="<?php echo htmlspecialchars($_GET['busca'] ?? ''); ?>">
                                <div class="search-icon">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="11" cy="11" r="8"/>
                                        <path d="m21 21-4.35-4.35"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="button" class="btn btn-secondary" onclick="clearFilters()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                            Limpar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                            </svg>
                            Aplicar Filtros
                        </button>
                        <button type="button" class="btn btn-outline" onclick="exportData()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="7 10 12 15 17 10"/>
                                <line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                            Exportar
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Tabela de Transações -->
            <div class="table-container">
                <div class="table-header">
                    <h3>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14,2 14,8 20,8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                            <polyline points="10,9 9,9 8,9"/>
                        </svg>
                        Lista de Transações
                    </h3>
                </div>
                
                <div class="table-wrapper">
                    <table class="transactions-table">
                        <thead>
                            <tr>
                                <th>
                                    <div class="checkbox-container">
                                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                        <span class="checkbox-mark"></span>
                                    </div>
                                </th>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Loja</th>
                                <th>Valor Original</th>
                                <th>Saldo Usado</th>
                                <th>Valor Pago</th>
                                <th>Cashback</th>
                                <th>Data</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="11">
                                        <div class="empty-state">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.68 0C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1v7z"/>
                                                <polyline points="9 12 11 14 15 10"/>
                                            </svg>
                                            <h3>Nenhuma transação encontrada</h3>
                                            <p>Não há transações que correspondam aos filtros aplicados.</p>
                                            <button class="btn btn-primary" onclick="clearFilters()">
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
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="checkbox-container">
                                                <input type="checkbox" class="transaction-checkbox" value="<?php echo $transaction['id']; ?>">
                                                <span class="checkbox-mark"></span>
                                            </div>
                                        </td>
                                        <td>
                                            <strong>#<?php echo $transaction['id']; ?></strong>
                                        </td>
                                        <td>
                                            <div>
                                                <?php echo htmlspecialchars($transaction['cliente_nome']); ?>
                                                <?php if ($saldoUsado > 0): ?>
                                                    <div class="balance-indicator">
                                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <circle cx="12" cy="12" r="10"/>
                                                            <line x1="12" y1="6" x2="12" y2="18"/>
                                                            <line x1="6" y1="12" x2="18" y2="12"/>
                                                        </svg>
                                                        Usou Saldo
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($transaction['loja_nome']); ?></td>
                                        <td>
                                            <span class="value-display value-original"><?php echo formatCurrency($valorOriginal); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($saldoUsado > 0): ?>
                                                <span class="value-display value-used">-<?php echo formatCurrency($saldoUsado); ?></span>
                                            <?php else: ?>
                                                <span style="color: #999; font-style: italic;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div>
                                                <span class="value-display value-paid"><?php echo formatCurrency($valorPago); ?></span>
                                                <?php if ($saldoUsado > 0): ?>
                                                    <span class="economy-badge">Economizou</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="value-display"><?php echo formatCurrency($transaction['valor_cliente']); ?></span>
                                            <?php if ($transaction['valor_admin'] > 0 || $transaction['valor_loja'] > 0): ?>
                                                <br>
                                                <small style="color: #666; font-size: 11px;">
                                                    Admin: <?php echo formatCurrency($transaction['valor_admin']); ?>
                                                    <?php if ($transaction['valor_loja'] > 0): ?>
                                                        | Loja: <?php echo formatCurrency($transaction['valor_loja']); ?>
                                                    <?php endif; ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo formatDate($transaction['data_transacao']); ?></td>
                                        <td>
                                            <?php 
                                                $statusMap = [
                                                    'aprovado' => ['class' => 'status-approved', 'text' => 'Aprovado'],
                                                    'pendente' => ['class' => 'status-pending', 'text' => 'Pendente'],
                                                    'cancelado' => ['class' => 'status-canceled', 'text' => 'Cancelado']
                                                ];
                                                $status = $statusMap[$transaction['status']] ?? ['class' => 'status-pending', 'text' => ucfirst($transaction['status'])];
                                            ?>
                                            <span class="status-badge <?php echo $status['class']; ?>">
                                                <?php echo $status['text']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="color: #999; font-style: italic;">—</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Paginação -->
            <?php if (!empty($pagination) && $pagination['total_paginas'] > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1<?php echo buildQueryString(['page']); ?>" class="arrow" title="Primeira página">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="11 17 6 12 11 7"/>
                                <polyline points="18 17 13 12 18 7"/>
                            </svg>
                        </a>
                        <a href="?page=<?php echo $page - 1; ?><?php echo buildQueryString(['page']); ?>" class="arrow" title="Página anterior">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="15 18 9 12 15 6"/>
                            </svg>
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
                        <a href="?page=<?php echo $i; ?><?php echo buildQueryString(['page']); ?>" class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $pagination['total_paginas']): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo buildQueryString(['page']); ?>" class="arrow" title="Próxima página">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"/>
                            </svg>
                        </a>
                        <a href="?page=<?php echo $pagination['total_paginas']; ?><?php echo buildQueryString(['page']); ?>" class="arrow" title="Última página">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 17 11 12 6 7"/>
                                <polyline points="13 17 18 12 13 7"/>
                            </svg>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Seção de Impacto do Saldo -->
            <?php if (!empty($statistics) && $statistics['total_saldo_usado'] > 0): ?>
            <div class="impact-section">
                <div class="impact-header">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M8 14s1.5 2 4 2 4-2 4-2"/>
                        <line x1="9" y1="9" x2="9.01" y2="9"/>
                        <line x1="15" y1="9" x2="15.01" y2="9"/>
                    </svg>
                    <h4>💰 Análise do Impacto do Sistema de Saldo</h4>
                </div>
                
                <div class="impact-grid">
                    <div class="impact-item">
                        <div class="impact-label">Economia dos Clientes</div>
                        <div class="impact-value"><?php echo formatCurrency($statistics['total_saldo_usado']); ?></div>
                    </div>
                    
                    <div class="impact-item">
                        <div class="impact-label">Redução na Receita das Lojas</div>
                        <div class="impact-value"><?php echo formatCurrency($statistics['total_saldo_usado']); ?></div>
                    </div>
                    
                    <div class="impact-item">
                        <div class="impact-label">Impacto na Comissão Klube Cash</div>
                        <div class="impact-value"><?php echo formatCurrency($statistics['total_saldo_usado'] * 0.1); ?></div>
                    </div>
                    
                    <div class="impact-item">
                        <div class="impact-label">Taxa de Adoção do Saldo</div>
                        <div class="impact-value"><?php echo number_format($statistics['percentual_uso_saldo'], 1); ?>%</div>
                    </div>
                </div>
                
                <div class="impact-insights">
                    <div class="insight">
                        <strong>💡 Insight:</strong> Os clientes economizaram significativamente usando o saldo acumulado, 
                        demonstrando alta adoção do sistema de cashback. Isso indica engajamento e fidelização efetivos.
                    </div>
                    
                    <div class="insight">
                        <strong>📊 Análise:</strong> A taxa de <?php echo number_format($statistics['percentual_uso_saldo'], 1); ?>% 
                        de uso do saldo indica que o sistema está funcionando corretamente e incentivando retorno dos clientes.
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Ações em Lote -->
            <div class="bulk-actions" id="bulkActions" style="display: none;">
                <div class="bulk-actions-content">
                    <span class="bulk-counter">
                        <span id="selectedCount">0</span> transações selecionadas
                    </span>
                    <div class="bulk-buttons">
                        <button class="btn btn-outline" onclick="clearSelection()">Limpar Seleção</button>
                        <button class="btn btn-primary" onclick="exportSelected()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="7 10 12 15 17 10"/>
                                <line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                            Exportar Selecionadas
                        </button>
                    </div>
                </div>
            </div>
            
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Funções JavaScript para interatividade
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.transaction-checkbox');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            const count = selectAll.checked ? checkboxes.length : 0;
            selectedCount.textContent = count;
            bulkActions.style.display = count > 0 ? 'block' : 'none';
        }
        
        function updateSelection() {
            const checkboxes = document.querySelectorAll('.transaction-checkbox');
            const selectAll = document.getElementById('selectAll');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            
            const checkedBoxes = document.querySelectorAll('.transaction-checkbox:checked');
            const count = checkedBoxes.length;
            
            selectAll.checked = count === checkboxes.length;
            selectAll.indeterminate = count > 0 && count < checkboxes.length;
            
            selectedCount.textContent = count;
            bulkActions.style.display = count > 0 ? 'block' : 'none';
        }
        
        // Adicionar event listeners aos checkboxes
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.transaction-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSelection);
            });
        });
        
        function clearSelection() {
            const checkboxes = document.querySelectorAll('.transaction-checkbox');
            const selectAll = document.getElementById('selectAll');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            selectAll.checked = false;
            selectAll.indeterminate = false;
            
            document.getElementById('bulkActions').style.display = 'none';
            document.getElementById('selectedCount').textContent = '0';
        }
        
        function clearFilters() {
            window.location.href = '?';
        }
        
        function exportData() {
            alert('Funcionalidade de exportação será implementada em breve.');
        }
        
        function exportSelected() {
            const selectedIds = Array.from(document.querySelectorAll('.transaction-checkbox:checked'))
                .map(checkbox => checkbox.value);
                
            if (selectedIds.length === 0) {
                alert('Nenhuma transação selecionada.');
                return;
            }
            
            alert(`Exportando ${selectedIds.length} transações selecionadas. Funcionalidade será implementada em breve.`);
        }
        
        function handleDateFilter() {
            const filterSelect = document.getElementById('dataFilter');
            const customDatesGroup = document.getElementById('customDatesGroup');
            const customDatesGroup2 = document.getElementById('customDatesGroup2');
            
            if (filterSelect.value === 'custom') {
                customDatesGroup.style.display = 'block';
                customDatesGroup2.style.display = 'block';
            } else {
                customDatesGroup.style.display = 'none';
                customDatesGroup2.style.display = 'none';
                
                // Se não for custom, definir datas automaticamente
                if (filterSelect.value !== '' && filterSelect.value !== 'custom') {
                    const today = new Date();
                    const dateFrom = document.querySelector('input[name="data_inicio"]');
                    const dateTo = document.querySelector('input[name="data_fim"]');
                    
                    switch(filterSelect.value) {
                        case 'today':
                            dateFrom.value = today.toISOString().split('T')[0];
                            dateTo.value = today.toISOString().split('T')[0];
                            break;
                        case 'yesterday':
                            const yesterday = new Date(today);
                            yesterday.setDate(yesterday.getDate() - 1);
                            dateFrom.value = yesterday.toISOString().split('T')[0];
                            dateTo.value = yesterday.toISOString().split('T')[0];
                            break;
                        case 'last_week':
                            const weekAgo = new Date(today);
                            weekAgo.setDate(weekAgo.getDate() - 7);
                            dateFrom.value = weekAgo.toISOString().split('T')[0];
                            dateTo.value = today.toISOString().split('T')[0];
                            break;
                        case 'last_month':
                            const monthAgo = new Date(today);
                            monthAgo.setMonth(monthAgo.getMonth() - 1);
                            dateFrom.value = monthAgo.toISOString().split('T')[0];
                            dateTo.value = today.toISOString().split('T')[0];
                            break;
                    }
                }
            }
        }
    </script>
</body>
</html>