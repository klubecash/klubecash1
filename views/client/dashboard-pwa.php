<?php
// Dashboard PWA otimizado para cliente mobile
// Inclui saldo de cashback, gráficos interativos e transações recentes

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'client') {
    header('Location: /login');
    exit;
}

// Obter dados do usuário
require_once __DIR__ . '/../../controllers/ClientController.php';
require_once __DIR__ . '/../../models/Transaction.php';

$clientController = new ClientController();
$transactionModel = new Transaction();

// Buscar dados do dashboard
$userId = $_SESSION['user_id'];
$userInfo = $clientController->getUserInfo($userId);
$cashbackSummary = $clientController->getCashbackSummary($userId);
$recentTransactions = $transactionModel->getRecentTransactions($userId, 5);
$chartData = $clientController->getCashbackChartData($userId, 30); // últimos 30 dias

// Dados para gráfico
$chartLabels = array_column($chartData, 'date');
$chartValues = array_column($chartData, 'cashback');
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#28A745">
    <title>Dashboard - Klube Cash</title>
    
    <!-- PWA Meta Tags -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Klube Cash">
    
    <!-- CSS -->
    <link rel="stylesheet" href="/assets/css/pwa.css">
    <link rel="stylesheet" href="/assets/css/mobile-first.css">
    <link rel="stylesheet" href="/assets/css/animations.css">
    
    <!-- Chart.js para gráficos touch-friendly -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    
    <style>
        /* Estilos específicos do dashboard PWA */
        .dashboard-pwa {
            min-height: 100vh;
            background: linear-gradient(135deg, #28A745 0%, #20B2AA 100%);
            padding-bottom: 80px; /* Espaço para bottom nav */
        }

        /* Header compacto mobile */
        .pwa-header {
            background: transparent;
            padding: 20px 16px 0;
            color: white;
            position: relative;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .user-greeting {
            flex: 1;
        }

        .greeting-text {
            font-size: 14px;
            opacity: 0.9;
            margin: 0;
        }

        .user-name {
            font-size: 20px;
            font-weight: 600;
            margin: 2px 0 0;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .notification-btn {
            position: relative;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 50%;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .notification-btn:active {
            transform: scale(0.95);
            background: rgba(255, 255, 255, 0.3);
        }

        .notification-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background: #FF4757;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.3);
            cursor: pointer;
        }

        /* Container principal */
        .dashboard-content {
            padding: 0 16px;
        }

        /* Card de saldo principal */
        .balance-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .balance-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #28A745, #20B2AA);
        }

        .balance-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .balance-title {
            font-size: 16px;
            color: #666;
            margin: 0;
        }

        .balance-toggle {
            background: none;
            border: none;
            color: #28A745;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: background 0.3s;
        }

        .balance-toggle:active {
            background: #f8f9fa;
        }

        .balance-main {
            margin-bottom: 20px;
        }

        .balance-amount {
            font-size: 36px;
            font-weight: 700;
            color: #28A745;
            margin: 0;
            animation: countUp 1s ease-out;
        }

        .balance-amount.hidden {
            font-family: monospace;
            letter-spacing: 4px;
        }

        .balance-subtitle {
            font-size: 14px;
            color: #666;
            margin: 4px 0 0;
        }

        .balance-pending {
            background: #FFF3CD;
            padding: 12px;
            border-radius: 12px;
            border-left: 4px solid #FFC107;
        }

        .pending-text {
            font-size: 14px;
            color: #856404;
            margin: 0;
        }

        .pending-amount {
            font-size: 16px;
            font-weight: 600;
            color: #B8860B;
            margin: 2px 0 0;
        }

        .balance-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .balance-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #28A745;
            color: white;
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #28A745;
            border: 1px solid #28A745;
        }

        .balance-btn:active {
            transform: scale(0.98);
        }

        /* Seção de gráfico */
        .chart-section {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .period-selector {
            display: flex;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 2px;
        }

        .period-btn {
            padding: 6px 12px;
            border: none;
            background: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .period-btn.active {
            background: #28A745;
            color: white;
        }

        .chart-container {
            position: relative;
            height: 200px;
            touch-action: pan-y; /* Permite gestos de zoom e pan */
        }

        /* Ações rápidas */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .action-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }

        .action-card:active {
            transform: scale(0.95);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .action-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #28A745, #20B2AA);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            color: white;
            font-size: 20px;
        }

        .action-title {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        /* Transações recentes */
        .recent-transactions {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .view-all-btn {
            font-size: 14px;
            color: #28A745;
            text-decoration: none;
            font-weight: 500;
        }

        .transaction-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .transaction-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .transaction-item:last-child {
            border-bottom: none;
        }

        .store-logo {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 16px;
            font-weight: 600;
            color: #666;
        }

        .transaction-info {
            flex: 1;
        }

        .store-name {
            font-size: 14px;
            font-weight: 500;
            color: #333;
            margin: 0 0 2px;
        }

        .transaction-date {
            font-size: 12px;
            color: #666;
            margin: 0;
        }

        .transaction-amount {
            text-align: right;
        }

        .cashback-value {
            font-size: 14px;
            font-weight: 600;
            color: #28A745;
            margin: 0;
        }

        .status-badge {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-top: 2px;
        }

        .status-pending {
            background: #FFF3CD;
            color: #856404;
        }

        .status-available {
            background: #D4EDDA;
            color: #155724;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .empty-icon {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-text {
            font-size: 16px;
            margin: 0 0 8px;
        }

        .empty-subtitle {
            font-size: 14px;
            margin: 0;
            opacity: 0.7;
        }

        /* Animações */
        @keyframes countUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .balance-card,
        .chart-section,
        .action-card,
        .recent-transactions {
            animation: slideInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        .action-card:nth-child(1) { animation-delay: 0.1s; }
        .action-card:nth-child(2) { animation-delay: 0.2s; }
        .action-card:nth-child(3) { animation-delay: 0.3s; }
        .action-card:nth-child(4) { animation-delay: 0.4s; }

        /* Responsividade para tablets */
        @media (min-width: 768px) {
            .dashboard-content {
                max-width: 600px;
                margin: 0 auto;
                padding: 0 20px;
            }
            
            .quick-actions {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .balance-card,
            .chart-section,
            .action-card,
            .recent-transactions {
                background: #1a1a1a;
                color: #ffffff;
            }
            
            .balance-title,
            .balance-subtitle,
            .transaction-date {
                color: #cccccc;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-pwa">
        <!-- Header Compacto -->
        <header class="pwa-header">
            <div class="header-content">
                <div class="user-greeting">
                    <p class="greeting-text">Olá,</p>
                    <h1 class="user-name"><?php echo htmlspecialchars($userInfo['name']); ?></h1>
                </div>
                <div class="header-actions">
                    <button class="notification-btn" onclick="openNotifications()">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>
                        </svg>
                        <?php if ($userInfo['unread_notifications'] > 0): ?>
                            <span class="notification-badge"><?php echo $userInfo['unread_notifications']; ?></span>
                        <?php endif; ?>
                    </button>
                    <img src="<?php echo $userInfo['avatar'] ?: '/assets/images/default-avatar.png'; ?>" 
                         alt="Avatar" class="user-avatar" onclick="openProfile()">
                </div>
            </div>
        </header>

        <!-- Conteúdo Principal -->
        <main class="dashboard-content">
            <!-- Card de Saldo Principal -->
            <div class="balance-card">
                <div class="balance-header">
                    <h2 class="balance-title">Seu Cashback</h2>
                    <button class="balance-toggle" onclick="toggleBalanceVisibility()">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                        </svg>
                    </button>
                </div>
                
                <div class="balance-main">
                    <h3 class="balance-amount" id="balanceAmount">
                        R$ <?php echo number_format($cashbackSummary['available_balance'], 2, ',', '.'); ?>
                    </h3>
                    <p class="balance-subtitle">Disponível para uso</p>
                </div>

                <?php if ($cashbackSummary['pending_balance'] > 0): ?>
                <div class="balance-pending">
                    <p class="pending-text">Aguardando liberação</p>
                    <p class="pending-amount">R$ <?php echo number_format($cashbackSummary['pending_balance'], 2, ',', '.'); ?></p>
                </div>
                <?php endif; ?>

                <div class="balance-actions">
                    <button class="balance-btn btn-primary" onclick="viewStatement()">
                        Ver Extrato
                    </button>
                    <button class="balance-btn btn-secondary" onclick="viewStores()">
                        Lojas Parceiras
                    </button>
                </div>
            </div>

            <!-- Gráfico de Evolução -->
            <div class="chart-section">
                <div class="chart-header">
                    <h3 class="chart-title">Evolução do Cashback</h3>
                    <div class="period-selector">
                        <button class="period-btn" onclick="changePeriod(7)">7d</button>
                        <button class="period-btn active" onclick="changePeriod(30)">30d</button>
                        <button class="period-btn" onclick="changePeriod(90)">90d</button>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="cashbackChart"></canvas>
                </div>
            </div>

            <!-- Ações Rápidas -->
            <div class="quick-actions">
                <a href="/client/partner-stores-pwa" class="action-card">
                    <div class="action-icon">🏪</div>
                    <h4 class="action-title">Lojas</h4>
                </a>
                <a href="/client/statement-pwa" class="action-card">
                    <div class="action-icon">📋</div>
                    <h4 class="action-title">Histórico</h4>
                </a>
                <a href="/client/referral" class="action-card">
                    <div class="action-icon">👥</div>
                    <h4 class="action-title">Indicar</h4>
                </a>
                <a href="/support" class="action-card">
                    <div class="action-icon">💬</div>
                    <h4 class="action-title">Ajuda</h4>
                </a>
            </div>

            <!-- Transações Recentes -->
            <div class="recent-transactions">
                <div class="section-header">
                    <h3 class="section-title">Últimas Transações</h3>
                    <a href="/client/statement-pwa" class="view-all-btn">Ver todas</a>
                </div>

                <?php if (empty($recentTransactions)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">🛍️</div>
                        <p class="empty-text">Nenhuma transação ainda</p>
                        <p class="empty-subtitle">Comece a comprar em nossas lojas parceiras!</p>
                    </div>
                <?php else: ?>
                    <ul class="transaction-list">
                        <?php foreach ($recentTransactions as $transaction): ?>
                            <li class="transaction-item" onclick="viewTransaction('<?php echo $transaction['id']; ?>')">
                                <div class="store-logo">
                                    <?php if ($transaction['store_logo']): ?>
                                        <img src="<?php echo $transaction['store_logo']; ?>" alt="<?php echo htmlspecialchars($transaction['store_name']); ?>">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($transaction['store_name'], 0, 2)); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="transaction-info">
                                    <p class="store-name"><?php echo htmlspecialchars($transaction['store_name']); ?></p>
                                    <p class="transaction-date"><?php echo date('d/m/Y', strtotime($transaction['created_at'])); ?></p>
                                </div>
                                <div class="transaction-amount">
                                    <p class="cashback-value">+R$ <?php echo number_format($transaction['cashback_amount'], 2, ',', '.'); ?></p>
                                    <span class="status-badge <?php echo $transaction['status'] === 'pending' ? 'status-pending' : 'status-available'; ?>">
                                        <?php echo $transaction['status'] === 'pending' ? 'Pendente' : 'Disponível'; ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Bottom Navigation (incluído via componente) -->
    <?php include __DIR__ . '/../components/bottom-nav.php'; ?>

    <script>
        // Dados do gráfico vindos do PHP
        const chartData = {
            labels: <?php echo json_encode($chartLabels); ?>,
            values: <?php echo json_encode($chartValues); ?>
        };

        // Configuração do gráfico touch-friendly
        let cashbackChart;
        
        function initChart() {
            const ctx = document.getElementById('cashbackChart').getContext('2d');
            
            cashbackChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Cashback',
                        data: chartData.values,
                        borderColor: '#28A745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 6,
                        pointBackgroundColor: '#28A745',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointHoverRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#ffffff',
                            bodyColor: '#ffffff',
                            borderColor: '#28A745',
                            borderWidth: 1,
                            cornerRadius: 8,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return 'R$ ' + context.parsed.y.toLocaleString('pt-BR', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            grid: {
                                display: false
                            },
                            ticks: {
                                maxTicksLimit: 6,
                                color: '#666'
                            }
                        },
                        y: {
                            display: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                color: '#666',
                                callback: function(value) {
                                    return 'R$ ' + value.toLocaleString('pt-BR');
                                }
                            }
                        }
                    },
                    // Configurações touch para mobile
                    animation: {
                        duration: 1000,
                        easing: 'easeOutQuart'
                    },
                    elements: {
                        point: {
                            hoverRadius: 12
                        }
                    }
                }
            });
        }

        // Função para alternar visibilidade do saldo
        function toggleBalanceVisibility() {
            const balanceElement = document.getElementById('balanceAmount');
            const isHidden = balanceElement.classList.contains('hidden');
            
            if (isHidden) {
                balanceElement.textContent = 'R$ <?php echo number_format($cashbackSummary['available_balance'], 2, ',', '.'); ?>';
                balanceElement.classList.remove('hidden');
            } else {
                balanceElement.textContent = '••••••';
                balanceElement.classList.add('hidden');
            }
        }

        // Função para mudar período do gráfico
        async function changePeriod(days) {
            // Atualizar botões ativos
            document.querySelectorAll('.period-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            try {
                // Buscar novos dados
                const response = await fetch(`/api/client/cashback-chart?period=${days}`);
                const newData = await response.json();
                
                // Atualizar gráfico
                cashbackChart.data.labels = newData.labels;
                cashbackChart.data.datasets[0].data = newData.values;
                cashbackChart.update('active');
            } catch (error) {
                console.error('Erro ao carregar dados do gráfico:', error);
            }
        }

        // Navegação
        function openNotifications() {
            window.location.href = '/client/notifications';
        }

        function openProfile() {
            window.location.href = '/client/profile-pwa';
        }

        function viewStatement() {
            window.location.href = '/client/statement-pwa';
        }

        function viewStores() {
            window.location.href = '/client/partner-stores-pwa';
        }

        function viewTransaction(id) {
            window.location.href = `/client/transaction/${id}`;
        }

        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            initChart();
            
            // Pull to refresh
            let startY = 0;
            let currentY = 0;
            let pullDistance = 0;
            let isRefreshing = false;
            
            document.addEventListener('touchstart', function(e) {
                if (window.pageYOffset === 0) {
                    startY = e.touches[0].pageY;
                }
            });
            
            document.addEventListener('touchmove', function(e) {
                if (window.pageYOffset === 0 && !isRefreshing) {
                    currentY = e.touches[0].pageY;
                    pullDistance = currentY - startY;
                    
                    if (pullDistance > 0) {
                        e.preventDefault();
                        
                        // Visual feedback do pull to refresh
                        if (pullDistance > 80) {
                            document.body.style.transform = `translateY(${Math.min(pullDistance * 0.5, 60)}px)`;
                        }
                    }
                }
            });
            
            document.addEventListener('touchend', function(e) {
                if (pullDistance > 80 && !isRefreshing) {
                    refreshDashboard();
                }
                
                // Reset visual
                document.body.style.transform = '';
                pullDistance = 0;
            });
        });

        // Função de refresh
        async function refreshDashboard() {
            if (isRefreshing) return;
            
            isRefreshing = true;
            
            try {
                // Feedback visual
                document.body.style.opacity = '0.7';
                
                // Recarregar dados
                const response = await fetch('/api/client/dashboard-refresh', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                if (response.ok) {
                    window.location.reload();
                }
            } catch (error) {
                console.error('Erro ao atualizar dashboard:', error);
            } finally {
                isRefreshing = false;
                document.body.style.opacity = '';
            }
        }

        // Haptic feedback para dispositivos suportados
        function hapticFeedback() {
            if (navigator.vibrate) {
                navigator.vibrate(10);
            }
        }

        // Adicionar haptic feedback aos botões
        document.querySelectorAll('button, .action-card').forEach(element => {
            element.addEventListener('touchstart', hapticFeedback);
        });

        // Service Worker para PWA
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/pwa/sw.js')
                .then(registration => console.log('SW registrado'))
                .catch(error => console.log('Erro no SW:', error));
        }
    </script>
</body>
</html>