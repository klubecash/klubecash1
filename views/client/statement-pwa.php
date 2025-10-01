<?php
session_start();
require_once '../../config/database.php';
require_once '../../controllers/TransactionController.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'cliente') {
    header('Location: ../../views/auth/login.php');
    exit;
}

$transactionController = new TransactionController();
$database = new Database();
$conn = $database->getConnection();

// Parâmetros de paginação e filtros
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20; // Itens por página
$offset = ($page - 1) * $limit;

// Filtros
$filtros = [
    'data_inicio' => $_GET['data_inicio'] ?? '',
    'data_fim' => $_GET['data_fim'] ?? '',
    'status' => $_GET['status'] ?? '',
    'loja_id' => $_GET['loja_id'] ?? '',
    'tipo' => $_GET['tipo'] ?? '' // 'cashback', 'uso_saldo', 'todos'
];

// Se for requisição AJAX para carregar mais itens
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    try {
        $transacoes = $transactionController->getClientTransactionsPWA($_SESSION['user_id'], $filtros, $limit, $offset);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $transacoes,
            'hasMore' => count($transacoes) === $limit
        ]);
        exit;
    } catch (Exception $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Carregar estatísticas e dados iniciais
try {
    // Estatísticas do saldo
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN tipo = 'cashback' AND status = 'aprovado' THEN valor_cashback ELSE 0 END), 0) as total_creditado,
            COALESCE(SUM(CASE WHEN saldo_usado > 0 THEN saldo_usado ELSE 0 END), 0) as total_usado,
            COALESCE(SUM(CASE WHEN tipo = 'estorno' THEN valor_cashback ELSE 0 END), 0) as total_estornado,
            COUNT(CASE WHEN saldo_usado > 0 THEN 1 END) as qtd_usos
        FROM transacoes_cashback 
        WHERE usuario_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $saldoEstatisticas = $stmt->fetch(PDO::FETCH_ASSOC);

    // Saldo atual
    $stmt = $conn->prepare("
        SELECT COALESCE(saldo_atual, 0) as saldo_atual 
        FROM usuarios 
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $saldoAtual = $stmt->fetchColumn() ?: 0;

    // Carregar lojas para filtros
    $stmt = $conn->prepare("
        SELECT DISTINCT l.id, l.nome_fantasia 
        FROM lojas l 
        INNER JOIN transacoes_cashback t ON l.id = t.loja_id 
        WHERE t.usuario_id = ? 
        ORDER BY l.nome_fantasia
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $lojas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Carregar primeira página de transações
    $transacoesIniciais = $transactionController->getClientTransactionsPWA($_SESSION['user_id'], $filtros, $limit, 0);
    $hasMore = count($transacoesIniciais) === $limit;

} catch (Exception $e) {
    error_log('Erro ao carregar dados PWA: ' . $e->getMessage());
    $saldoEstatisticas = ['total_creditado' => 0, 'total_usado' => 0, 'total_estornado' => 0, 'qtd_usos' => 0];
    $saldoAtual = 0;
    $lojas = [];
    $transacoesIniciais = [];
    $hasMore = false;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#2E7D32">
    <title>Meu Extrato - Klube Cash</title>
    
    <!-- PWA Meta Tags -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Klube Cash">
    
    <!-- Icons -->
    <link rel="shortcut icon" href="../../assets/images/icons/KlubeCashLOGO.ico">
    <link rel="apple-touch-icon" href="../../assets/icons/icon-192x192.png">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="../../assets/css/pwa.css">
    <link rel="stylesheet" href="../../assets/css/mobile-first.css">
    <link rel="stylesheet" href="../../assets/css/animations.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* Estilos específicos da página de extrato PWA */
        .statement-pwa {
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding-bottom: 80px; /* Espaço para bottom nav */
        }

        .pwa-header {
            background: linear-gradient(135deg, #2E7D32 0%, #4CAF50 100%);
            color: white;
            padding: 20px 16px 24px;
            border-radius: 0 0 24px 24px;
            box-shadow: 0 4px 12px rgba(46, 125, 50, 0.3);
        }

        .header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .back-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 12px;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .back-btn:active {
            transform: scale(0.95);
            background: rgba(255, 255, 255, 0.3);
        }

        .filter-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 12px;
            padding: 8px 16px;
            color: white;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .filter-btn:active {
            transform: scale(0.95);
            background: rgba(255, 255, 255, 0.3);
        }

        .filter-btn.active {
            background: rgba(255, 255, 255, 0.9);
            color: #2E7D32;
        }

        .header-title {
            text-align: center;
            margin-bottom: 20px;
        }

        .header-title h1 {
            font-size: 24px;
            font-weight: 600;
            margin: 0 0 4px 0;
        }

        .header-title p {
            font-size: 14px;
            opacity: 0.9;
            margin: 0;
        }

        .balance-summary {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 16px;
        }

        .balance-card {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 16px;
            padding: 16px;
            text-align: center;
            backdrop-filter: blur(10px);
        }

        .balance-card .label {
            font-size: 12px;
            opacity: 0.8;
            margin-bottom: 4px;
        }

        .balance-card .value {
            font-size: 18px;
            font-weight: 600;
        }

        .content-area {
            padding: 20px 16px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.2s ease;
        }

        .stat-card:active {
            transform: scale(0.98);
        }

        .stat-card .icon {
            font-size: 24px;
            margin-bottom: 8px;
        }

        .stat-card .value {
            font-size: 18px;
            font-weight: 600;
            color: #2E7D32;
            margin-bottom: 4px;
        }

        .stat-card .label {
            font-size: 12px;
            color: #666;
        }

        .transactions-section {
            background: white;
            border-radius: 20px 20px 0 0;
            margin: 0 -16px;
            padding: 20px 16px 0;
            min-height: 400px;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .view-toggle {
            display: flex;
            background: #f5f5f5;
            border-radius: 12px;
            padding: 4px;
        }

        .toggle-btn {
            padding: 8px 12px;
            border: none;
            background: transparent;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            color: #666;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .toggle-btn.active {
            background: white;
            color: #2E7D32;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Lista de Transações */
        .transactions-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .transaction-item {
            background: #f8f9fa;
            border-radius: 16px;
            margin-bottom: 12px;
            padding: 16px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .transaction-item:active {
            transform: scale(0.98);
            background: #e9ecef;
        }

        .transaction-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .store-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .store-avatar {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        .store-details h4 {
            margin: 0 0 2px 0;
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }

        .store-details span {
            font-size: 12px;
            color: #666;
        }

        .transaction-amount {
            text-align: right;
        }

        .amount-value {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .amount-value.positive {
            color: #2E7D32;
        }

        .amount-value.negative {
            color: #d32f2f;
        }

        .amount-type {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .transaction-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e0e0e0;
        }

        .detail-item {
            text-align: center;
        }

        .detail-item .label {
            font-size: 11px;
            color: #666;
            margin-bottom: 2px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-item .value {
            font-size: 14px;
            font-weight: 500;
            color: #333;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.pendente {
            background: #fff3e0;
            color: #f57c00;
        }

        .status-badge.aprovado {
            background: #e8f5e8;
            color: #2e7d32;
        }

        .status-badge.cancelado {
            background: #ffebee;
            color: #d32f2f;
        }

        /* Skeleton Loading */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: skeleton-loading 1.5s infinite;
            border-radius: 8px;
        }

        @keyframes skeleton-loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        .skeleton-item {
            background: white;
            border-radius: 16px;
            margin-bottom: 12px;
            padding: 16px;
        }

        .skeleton-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .skeleton-avatar {
            width: 40px;
            height: 40px;
            border-radius: 12px;
        }

        .skeleton-store {
            flex: 1;
        }

        .skeleton-store-name {
            height: 16px;
            width: 60%;
            margin-bottom: 6px;
        }

        .skeleton-store-date {
            height: 12px;
            width: 40%;
        }

        .skeleton-amount {
            height: 20px;
            width: 80px;
        }

        .skeleton-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e0e0e0;
        }

        .skeleton-detail {
            height: 14px;
        }

        /* Bottom Sheet para Filtros */
        .bottom-sheet {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-radius: 24px 24px 0 0;
            transform: translateY(100%);
            transition: transform 0.3s ease;
            z-index: 1000;
            max-height: 80vh;
            overflow-y: auto;
        }

        .bottom-sheet.active {
            transform: translateY(0);
        }

        .bottom-sheet-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 999;
        }

        .bottom-sheet-backdrop.active {
            opacity: 1;
            visibility: visible;
        }

        .sheet-handle {
            width: 40px;
            height: 4px;
            background: #ddd;
            border-radius: 2px;
            margin: 12px auto;
        }

        .sheet-header {
            padding: 0 20px 16px;
            border-bottom: 1px solid #e0e0e0;
        }

        .sheet-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            text-align: center;
        }

        .sheet-content {
            padding: 20px;
        }

        .filter-group {
            margin-bottom: 24px;
        }

        .filter-label {
            font-size: 14px;
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }

        .filter-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            background: #f8f9fa;
            box-sizing: border-box;
        }

        .filter-input:focus {
            outline: none;
            border-color: #2E7D32;
            background: white;
        }

        .filter-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .filter-chip {
            padding: 8px 16px;
            border: 1px solid #e0e0e0;
            border-radius: 20px;
            background: white;
            font-size: 14px;
            color: #666;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .filter-chip.active {
            background: #2E7D32;
            color: white;
            border-color: #2E7D32;
        }

        .filter-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .btn {
            flex: 1;
            padding: 14px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #666;
        }

        .btn-primary {
            background: #2E7D32;
            color: white;
        }

        .btn:active {
            transform: scale(0.98);
        }

        /* Loading Spinner */
        .loading-spinner {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .spinner {
            width: 24px;
            height: 24px;
            border: 2px solid #e0e0e0;
            border-top: 2px solid #2E7D32;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }

        .empty-state .icon {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 18px;
            color: #333;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #666;
            font-size: 14px;
        }

        /* Pull to Refresh */
        .pull-refresh {
            position: absolute;
            top: -60px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 50%;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .pull-refresh.visible {
            top: 20px;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .balance-summary {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .transaction-details {
                grid-template-columns: 1fr;
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="statement-pwa">
        <!-- Header PWA -->
        <header class="pwa-header">
            <div class="header-top">
                <button class="back-btn" onclick="goBack()">
                    ←
                </button>
                <button class="filter-btn" id="filterBtn" onclick="toggleFilters()">
                    <span>🔍</span>
                    Filtros
                </button>
            </div>
            
            <div class="header-title">
                <h1>Meu Extrato</h1>
                <p>Histórico completo de cashback e movimentações</p>
            </div>
            
            <div class="balance-summary">
                <div class="balance-card">
                    <div class="label">Saldo Atual</div>
                    <div class="value">R$ <?= number_format($saldoAtual, 2, ',', '.') ?></div>
                </div>
                <div class="balance-card">
                    <div class="label">Total Ganho</div>
                    <div class="value">R$ <?= number_format($saldoEstatisticas['total_creditado'], 2, ',', '.') ?></div>
                </div>
            </div>
        </header>

        <!-- Área de Conteúdo -->
        <div class="content-area">
            <!-- Estatísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="icon">💰</div>
                    <div class="value">R$ <?= number_format($saldoEstatisticas['total_creditado'], 2, ',', '.') ?></div>
                    <div class="label">Cashback Recebido</div>
                </div>
                <div class="stat-card">
                    <div class="icon">💸</div>
                    <div class="value">R$ <?= number_format($saldoEstatisticas['total_usado'], 2, ',', '.') ?></div>
                    <div class="label">Saldo Utilizado</div>
                </div>
                <div class="stat-card">
                    <div class="icon">📊</div>
                    <div class="value"><?= $saldoEstatisticas['qtd_usos'] ?></div>
                    <div class="label">Compras com Saldo</div>
                </div>
                <div class="stat-card">
                    <div class="icon">↩️</div>
                    <div class="value">R$ <?= number_format($saldoEstatisticas['total_estornado'], 2, ',', '.') ?></div>
                    <div class="label">Estornos</div>
                </div>
            </div>

            <!-- Seção de Transações -->
            <div class="transactions-section">
                <div class="section-header">
                    <h2 class="section-title">Movimentações</h2>
                    <div class="view-toggle">
                        <button class="toggle-btn active" data-view="all">Todas</button>
                        <button class="toggle-btn" data-view="cashback">Cashback</button>
                        <button class="toggle-btn" data-view="usage">Usos</button>
                    </div>
                </div>

                <!-- Pull to Refresh Indicator -->
                <div class="pull-refresh" id="pullRefresh">
                    <div class="spinner"></div>
                </div>

                <!-- Lista de Transações -->
                <ul class="transactions-list" id="transactionsList">
                    <?php if (empty($transacoesIniciais)): ?>
                        <div class="empty-state">
                            <div class="icon">📭</div>
                            <h3>Nenhuma movimentação encontrada</h3>
                            <p>Suas transações aparecerão aqui quando você começar a usar o Klube Cash</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($transacoesIniciais as $transacao): ?>
                            <?php
                            $isCashback = $transacao['tipo'] === 'cashback';
                            $usouSaldo = $transacao['saldo_usado'] > 0;
                            $valorPrincipal = $isCashback ? $transacao['valor_cashback'] : $transacao['saldo_usado'];
                            $isPositive = $isCashback;
                            ?>
                            <li class="transaction-item" onclick="showTransactionDetails('<?= $transacao['id'] ?>')">
                                <div class="transaction-header">
                                    <div class="store-info">
                                        <div class="store-avatar">
                                            <?= strtoupper(substr($transacao['nome_loja'], 0, 2)) ?>
                                        </div>
                                        <div class="store-details">
                                            <h4><?= htmlspecialchars($transacao['nome_loja']) ?></h4>
                                            <span><?= date('d/m/Y às H:i', strtotime($transacao['data_transacao'])) ?></span>
                                        </div>
                                    </div>
                                    <div class="transaction-amount">
                                        <div class="amount-value <?= $isPositive ? 'positive' : 'negative' ?>">
                                            <?= $isPositive ? '+' : '-' ?>R$ <?= number_format($valorPrincipal, 2, ',', '.') ?>
                                        </div>
                                        <div class="amount-type">
                                            <?= $isCashback ? 'Cashback' : 'Uso do Saldo' ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="transaction-details">
                                    <div class="detail-item">
                                        <div class="label">Valor da Compra</div>
                                        <div class="value">R$ <?= number_format($transacao['valor_total'], 2, ',', '.') ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="label">Status</div>
                                        <div class="value">
                                            <span class="status-badge <?= $transacao['status'] ?>">
                                                <?= ucfirst($transacao['status']) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>

                <!-- Loading Indicator -->
                <div class="loading-spinner" id="loadingIndicator" style="display: none;">
                    <div class="spinner"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Sheet para Filtros -->
    <div class="bottom-sheet-backdrop" id="filterBackdrop" onclick="closeFilters()"></div>
    <div class="bottom-sheet" id="filterSheet">
        <div class="sheet-handle"></div>
        <div class="sheet-header">
            <h3 class="sheet-title">Filtrar Transações</h3>
        </div>
        <div class="sheet-content">
            <form id="filterForm">
                <div class="filter-group">
                    <label class="filter-label">Data Inicial</label>
                    <input type="date" class="filter-input" name="data_inicio" value="<?= $filtros['data_inicio'] ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Data Final</label>
                    <input type="date" class="filter-input" name="data_fim" value="<?= $filtros['data_fim'] ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Status</label>
                    <div class="filter-chips">
                        <button type="button" class="filter-chip <?= $filtros['status'] === '' ? 'active' : '' ?>" data-value="">Todos</button>
                        <button type="button" class="filter-chip <?= $filtros['status'] === 'pendente' ? 'active' : '' ?>" data-value="pendente">Pendente</button>
                        <button type="button" class="filter-chip <?= $filtros['status'] === 'aprovado' ? 'active' : '' ?>" data-value="aprovado">Aprovado</button>
                        <button type="button" class="filter-chip <?= $filtros['status'] === 'cancelado' ? 'active' : '' ?>" data-value="cancelado">Cancelado</button>
                    </div>
                    <input type="hidden" name="status" value="<?= $filtros['status'] ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Tipo</label>
                    <div class="filter-chips">
                        <button type="button" class="filter-chip <?= $filtros['tipo'] === '' ? 'active' : '' ?>" data-value="">Todos</button>
                        <button type="button" class="filter-chip <?= $filtros['tipo'] === 'cashback' ? 'active' : '' ?>" data-value="cashback">Cashback</button>
                        <button type="button" class="filter-chip <?= $filtros['tipo'] === 'uso_saldo' ? 'active' : '' ?>" data-value="uso_saldo">Uso do Saldo</button>
                    </div>
                    <input type="hidden" name="tipo" value="<?= $filtros['tipo'] ?>">
                </div>
                
                <?php if (!empty($lojas)): ?>
                <div class="filter-group">
                    <label class="filter-label">Loja</label>
                    <select class="filter-input" name="loja_id">
                        <option value="">Todas as lojas</option>
                        <?php foreach ($lojas as $loja): ?>
                            <option value="<?= $loja['id'] ?>" <?= $filtros['loja_id'] == $loja['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($loja['nome_fantasia']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="filter-actions">
                    <button type="button" class="btn btn-secondary" onclick="clearFilters()">Limpar</button>
                    <button type="button" class="btn btn-primary" onclick="applyFilters()">Aplicar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript PWA -->
    <script>
        let currentPage = 1;
        let loading = false;
        let hasMoreData = <?= $hasMore ? 'true' : 'false' ?>;
        let currentFilters = <?= json_encode($filtros) ?>;
        let pullRefreshActive = false;
        let touchStartY = 0;
        let touchEndY = 0;

        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            initInfiniteScroll();
            initPullToRefresh();
            initFilterChips();
            initViewToggle();
        });

        // Infinite Scroll
        function initInfiniteScroll() {
            window.addEventListener('scroll', function() {
                if (loading || !hasMoreData) return;
                
                const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                const windowHeight = window.innerHeight;
                const documentHeight = document.documentElement.scrollHeight;
                
                if (scrollTop + windowHeight >= documentHeight - 100) {
                    loadMoreTransactions();
                }
            });
        }

        function loadMoreTransactions() {
            if (loading || !hasMoreData) return;
            
            loading = true;
            currentPage++;
            showLoading();
            
            const params = new URLSearchParams(currentFilters);
            params.append('page', currentPage);
            params.append('ajax', '1');
            
            fetch(`statement-pwa.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        appendTransactions(data.data);
                        hasMoreData = data.hasMore;
                    } else {
                        showError('Erro ao carregar mais transações');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showError('Erro de conexão');
                })
                .finally(() => {
                    loading = false;
                    hideLoading();
                });
        }

        function appendTransactions(transactions) {
            const list = document.getElementById('transactionsList');
            
            transactions.forEach(transacao => {
                const li = createTransactionElement(transacao);
                list.appendChild(li);
            });
        }

        function createTransactionElement(transacao) {
            const li = document.createElement('li');
            li.className = 'transaction-item';
            li.onclick = () => showTransactionDetails(transacao.id);
            
            const isCashback = transacao.tipo === 'cashback';
            const usouSaldo = transacao.saldo_usado > 0;
            const valorPrincipal = isCashback ? transacao.valor_cashback : transacao.saldo_usado;
            const isPositive = isCashback;
            
            li.innerHTML = `
                <div class="transaction-header">
                    <div class="store-info">
                        <div class="store-avatar">
                            ${transacao.nome_loja.substring(0, 2).toUpperCase()}
                        </div>
                        <div class="store-details">
                            <h4>${transacao.nome_loja}</h4>
                            <span>${formatDateTime(transacao.data_transacao)}</span>
                        </div>
                    </div>
                    <div class="transaction-amount">
                        <div class="amount-value ${isPositive ? 'positive' : 'negative'}">
                            ${isPositive ? '+' : '-'}R$ ${formatCurrency(valorPrincipal)}
                        </div>
                        <div class="amount-type">
                            ${isCashback ? 'Cashback' : 'Uso do Saldo'}
                        </div>
                    </div>
                </div>
                
                <div class="transaction-details">
                    <div class="detail-item">
                        <div class="label">Valor da Compra</div>
                        <div class="value">R$ ${formatCurrency(transacao.valor_total)}</div>
                    </div>
                    <div class="detail-item">
                        <div class="label">Status</div>
                        <div class="value">
                            <span class="status-badge ${transacao.status}">
                                ${capitalizeFirst(transacao.status)}
                            </span>
                        </div>
                    </div>
                </div>
            `;
            
            return li;
        }

        // Pull to Refresh
        function initPullToRefresh() {
            const container = document.querySelector('.content-area');
            const pullRefresh = document.getElementById('pullRefresh');
            
            container.addEventListener('touchstart', function(e) {
                touchStartY = e.touches[0].clientY;
            });
            
            container.addEventListener('touchmove', function(e) {
                touchEndY = e.touches[0].clientY;
                const pullDistance = touchEndY - touchStartY;
                
                if (window.scrollY === 0 && pullDistance > 0) {
                    e.preventDefault();
                    
                    if (pullDistance > 80) {
                        pullRefresh.classList.add('visible');
                        pullRefreshActive = true;
                    } else {
                        pullRefresh.classList.remove('visible');
                        pullRefreshActive = false;
                    }
                }
            });
            
            container.addEventListener('touchend', function() {
                if (pullRefreshActive) {
                    refreshData();
                }
                pullRefresh.classList.remove('visible');
                pullRefreshActive = false;
            });
        }

        function refreshData() {
            // Reset pagination
            currentPage = 1;
            hasMoreData = true;
            
            // Show skeleton loading
            showSkeletonLoading();
            
            // Load fresh data
            const params = new URLSearchParams(currentFilters);
            params.append('page', 1);
            params.append('ajax', '1');
            
            fetch(`statement-pwa.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        replaceTransactions(data.data);
                        hasMoreData = data.hasMore;
                        showSuccess('Dados atualizados');
                    } else {
                        showError('Erro ao atualizar dados');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showError('Erro de conexão');
                })
                .finally(() => {
                    hideSkeletonLoading();
                });
        }

        function replaceTransactions(transactions) {
            const list = document.getElementById('transactionsList');
            list.innerHTML = '';
            
            if (transactions.length === 0) {
                list.innerHTML = `
                    <div class="empty-state">
                        <div class="icon">📭</div>
                        <h3>Nenhuma movimentação encontrada</h3>
                        <p>Suas transações aparecerão aqui quando você começar a usar o Klube Cash</p>
                    </div>
                `;
            } else {
                transactions.forEach(transacao => {
                    const li = createTransactionElement(transacao);
                    list.appendChild(li);
                });
            }
        }

        // Skeleton Loading
        function showSkeletonLoading() {
            const list = document.getElementById('transactionsList');
            list.innerHTML = '';
            
            for (let i = 0; i < 5; i++) {
                const skeletonItem = document.createElement('li');
                skeletonItem.className = 'skeleton-item';
                skeletonItem.innerHTML = `
                    <div class="skeleton-header">
                        <div class="skeleton skeleton-avatar"></div>
                        <div class="skeleton-store">
                            <div class="skeleton skeleton-store-name"></div>
                            <div class="skeleton skeleton-store-date"></div>
                        </div>
                        <div class="skeleton skeleton-amount"></div>
                    </div>
                    <div class="skeleton-details">
                        <div class="skeleton skeleton-detail"></div>
                        <div class="skeleton skeleton-detail"></div>
                    </div>
                `;
                list.appendChild(skeletonItem);
            }
        }

        function hideSkeletonLoading() {
            // A função replaceTransactions já remove o skeleton
        }

        // Filtros
        function initFilterChips() {
            const chips = document.querySelectorAll('.filter-chip');
            chips.forEach(chip => {
                chip.addEventListener('click', function() {
                    const group = this.closest('.filter-group');
                    const hiddenInput = group.querySelector('input[type="hidden"]');
                    
                    // Remove active class from siblings
                    group.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
                    
                    // Add active class to clicked chip
                    this.classList.add('active');
                    
                    // Update hidden input
                    hiddenInput.value = this.dataset.value;
                });
            });
        }

        function toggleFilters() {
            const backdrop = document.getElementById('filterBackdrop');
            const sheet = document.getElementById('filterSheet');
            const btn = document.getElementById('filterBtn');
            
            backdrop.classList.add('active');
            sheet.classList.add('active');
            btn.classList.add('active');
            
            // Prevent body scroll
            document.body.style.overflow = 'hidden';
        }

        function closeFilters() {
            const backdrop = document.getElementById('filterBackdrop');
            const sheet = document.getElementById('filterSheet');
            const btn = document.getElementById('filterBtn');
            
            backdrop.classList.remove('active');
            sheet.classList.remove('active');
            btn.classList.remove('active');
            
            // Restore body scroll
            document.body.style.overflow = '';
        }

        function clearFilters() {
            const form = document.getElementById('filterForm');
            const inputs = form.querySelectorAll('input, select');
            
            inputs.forEach(input => {
                if (input.type === 'hidden') {
                    input.value = '';
                } else {
                    input.value = '';
                }
            });
            
            // Reset chips
            document.querySelectorAll('.filter-chip').forEach(chip => {
                chip.classList.remove('active');
                if (chip.dataset.value === '') {
                    chip.classList.add('active');
                }
            });
        }

        function applyFilters() {
            const form = document.getElementById('filterForm');
            const formData = new FormData(form);
            
            // Update current filters
            currentFilters = {};
            for (let [key, value] of formData.entries()) {
                currentFilters[key] = value;
            }
            
            // Reset pagination and reload
            currentPage = 1;
            hasMoreData = true;
            
            showSkeletonLoading();
            
            const params = new URLSearchParams(currentFilters);
            params.append('page', 1);
            params.append('ajax', '1');
            
            fetch(`statement-pwa.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        replaceTransactions(data.data);
                        hasMoreData = data.hasMore;
                        closeFilters();
                        
                        // Update filter button if filters are active
                        const hasActiveFilters = Object.values(currentFilters).some(value => value !== '');
                        const filterBtn = document.getElementById('filterBtn');
                        if (hasActiveFilters) {
                            filterBtn.classList.add('active');
                        } else {
                            filterBtn.classList.remove('active');
                        }
                    } else {
                        showError('Erro ao aplicar filtros');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showError('Erro de conexão');
                })
                .finally(() => {
                    hideSkeletonLoading();
                });
        }

        // View Toggle
        function initViewToggle() {
            const toggleBtns = document.querySelectorAll('.toggle-btn');
            toggleBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    toggleBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    const view = this.dataset.view;
                    filterByView(view);
                });
            });
        }

        function filterByView(view) {
            currentFilters.tipo = view === 'all' ? '' : (view === 'cashback' ? 'cashback' : 'uso_saldo');
            
            // Reset pagination and reload
            currentPage = 1;
            hasMoreData = true;
            
            showSkeletonLoading();
            
            const params = new URLSearchParams(currentFilters);
            params.append('page', 1);
            params.append('ajax', '1');
            
            fetch(`statement-pwa.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        replaceTransactions(data.data);
                        hasMoreData = data.hasMore;
                    } else {
                        showError('Erro ao filtrar transações');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showError('Erro de conexão');
                })
                .finally(() => {
                    hideSkeletonLoading();
                });
        }

        // Loading States
        function showLoading() {
            document.getElementById('loadingIndicator').style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loadingIndicator').style.display = 'none';
        }

        // Navigation
        function goBack() {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = 'dashboard-pwa.php';
            }
        }

        function showTransactionDetails(transactionId) {
            // Implementar modal de detalhes ou navegar para página de detalhes
            console.log('Mostrar detalhes da transação:', transactionId);
            // Por enquanto, vamos apenas vibrar o dispositivo (se suportado)
            if (navigator.vibrate) {
                navigator.vibrate(50);
            }
        }

        // Utility Functions
        function formatCurrency(value) {
            return parseFloat(value).toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('pt-BR') + ' às ' + date.toLocaleTimeString('pt-BR', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function capitalizeFirst(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

        // Toast Messages
        function showSuccess(message) {
            showToast(message, 'success');
        }

        function showError(message) {
            showToast(message, 'error');
        }

        function showToast(message, type) {
            // Implementar sistema de toast
            console.log(`${type.toUpperCase()}: ${message}`);
            
            // Vibração para feedback
            if (navigator.vibrate) {
                navigator.vibrate(type === 'error' ? [100, 50, 100] : 50);
            }
        }

        // Service Worker Registration (se disponível)
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('../../pwa/sw.js')
                .then(registration => console.log('SW registrado'))
                .catch(error => console.log('Erro no SW:', error));
        }
    </script>
</body>
</html>