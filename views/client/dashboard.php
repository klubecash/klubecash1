<?php
// views/client/dashboard.php
session_start();

// Verificações de segurança (mantendo a lógica original)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'cliente') {
    header("Location: " . LOGIN_URL . "?error=acesso_restrito");
    exit;
}

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../controllers/ClientController.php';

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Cliente';

// Obter dados do dashboard (mantendo a lógica original)
try {
    $db = Database::getConnection();
    
    // Saldo total de cashback disponível
    $saldoStmt = $db->prepare("
        SELECT SUM(saldo_disponivel) as total_disponivel
        FROM cashback_saldos 
        WHERE usuario_id = ?
    ");
    $saldoStmt->execute([$userId]);
    $saldoTotal = $saldoStmt->fetch(PDO::FETCH_ASSOC)['total_disponivel'] ?? 0;
    
    // Saldo pendente (aguardando aprovação)
    $pendingStmt = $db->prepare("
        SELECT SUM(valor_cliente) as total_pendente
        FROM transacoes_cashback
        WHERE usuario_id = ? AND status IN ('pendente', 'pagamento_pendente')
    ");
    $pendingStmt->execute([$userId]);
    $saldoPendente = $pendingStmt->fetch(PDO::FETCH_ASSOC)['total_pendente'] ?? 0;
    
    // Total já ganho em cashback
    $totalStmt = $db->prepare("
        SELECT SUM(valor_cliente) as total_ganho
        FROM transacoes_cashback 
        WHERE usuario_id = ? AND status = 'aprovado'
    ");
    $totalStmt->execute([$userId]);
    $totalGanho = $totalStmt->fetch(PDO::FETCH_ASSOC)['total_ganho'] ?? 0;
    
    // Saldo usado
    $usedStmt = $db->prepare("
        SELECT SUM(valor) as total_usado
        FROM cashback_movimentacoes 
        WHERE usuario_id = ? AND tipo_operacao = 'uso'
    ");
    $usedStmt->execute([$userId]);
    $saldoUsado = $usedStmt->fetch(PDO::FETCH_ASSOC)['total_usado'] ?? 0;
    
    // Últimas transações com saldo usado
    $transactionsStmt = $db->prepare("
        SELECT 
            t.*,
            l.nome_fantasia as loja_nome,
            l.logo as loja_logo,
            COALESCE(su.valor_usado, 0) as saldo_usado
        FROM transacoes_cashback t
        JOIN lojas l ON t.loja_id = l.id
        LEFT JOIN transacoes_saldo_usado su ON t.id = su.transacao_id
        WHERE t.usuario_id = ?
        ORDER BY t.data_transacao DESC
        LIMIT 5
    ");
    $transactionsStmt->execute([$userId]);
    $recentTransactions = $transactionsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Saldos por loja
    $balanceStmt = $db->prepare("
        SELECT 
            cs.saldo_disponivel,
            l.nome_fantasia,
            l.logo,
            l.categoria,
            COUNT(t.id) as total_compras
        FROM cashback_saldos cs
        JOIN lojas l ON cs.loja_id = l.id
        LEFT JOIN transacoes_cashback t ON l.id = t.loja_id AND t.usuario_id = cs.usuario_id
        WHERE cs.usuario_id = ? AND cs.saldo_disponivel > 0
        GROUP BY cs.id, l.id
        ORDER BY cs.saldo_disponivel DESC
        LIMIT 5
    ");
    $balanceStmt->execute([$userId]);
    $storeBalances = $balanceStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Erro ao carregar seus dados: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Klube Cash - Dashboard</title>
    <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
    <link rel="stylesheet" href="../../assets/css/views/client/dashboard-new.css">
</head>
<body>
    <?php include_once '../components/navbar.php'; ?>
    
    <div class="dashboard-container">
        <!-- Cabeçalho de Boas-vindas -->
        <div class="welcome-section">
            <div class="welcome-content">
                <h1>Olá, <?php echo htmlspecialchars(explode(' ', $userName)[0]); ?>! 👋</h1>
                <p class="welcome-subtitle">Aqui está um resumo do seu cashback e economia</p>
            </div>
            <div class="quick-actions">
                <a href="<?php echo CLIENT_BALANCE_URL; ?>" class="action-btn primary">
                    <span class="btn-icon">💰</span>
                    Meus Saldos
                </a>
                <a href="<?php echo CLIENT_STORES_URL; ?>" class="action-btn secondary">
                    <span class="btn-icon">🏪</span>
                    Lojas Parceiras
                </a>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="error-message">
                <span class="error-icon">⚠️</span>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Cards Principais de Resumo -->
        <div class="main-stats">
            <!-- Saldo Disponível -->
            <div class="stat-card main-balance">
                <div class="card-header">
                    <div class="card-icon">💳</div>
                    <div class="card-info">
                        <h3>Seu Saldo para Usar</h3>
                        <p class="help-text">Dinheiro que você pode usar em compras</p>
                    </div>
                </div>
                <div class="card-value">
                    <span class="currency">R$</span>
                    <span class="amount"><?php echo number_format($saldoTotal, 2, ',', '.'); ?></span>
                </div>
                <div class="card-action">
                    <a href="<?php echo CLIENT_BALANCE_URL; ?>" class="use-balance-btn">
                        Ver Como Usar →
                    </a>
                </div>
            </div>

            <!-- Cashback Aguardando -->
            <div class="stat-card pending-card">
                <div class="card-header">
                    <div class="card-icon">⏳</div>
                    <div class="card-info">
                        <h3>Aguardando Liberação</h3>
                        <p class="help-text">Cashback que ainda vai ser liberado</p>
                    </div>
                </div>
                <div class="card-value pending">
                    <span class="currency">R$</span>
                    <span class="amount"><?php echo number_format($saldoPendente, 2, ',', '.'); ?></span>
                </div>
                <?php if ($saldoPendente > 0): ?>
                    <div class="card-info-extra">
                        <span class="pending-info">✨ Em breve na sua conta!</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Total Economizado -->
            <div class="stat-card savings-card">
                <div class="card-header">
                    <div class="card-icon">🎯</div>
                    <div class="card-info">
                        <h3>Total Economizado</h3>
                        <p class="help-text">Quanto você já economizou com cashback</p>
                    </div>
                </div>
                <div class="card-value">
                    <span class="currency">R$</span>
                    <span class="amount"><?php echo number_format($totalGanho, 2, ',', '.'); ?></span>
                </div>
                <div class="savings-breakdown">
                    <div class="breakdown-item">
                        <span class="label">Usado:</span>
                        <span class="value">R$ <?php echo number_format($saldoUsado, 2, ',', '.'); ?></span>
                    </div>
                    <div class="breakdown-item">
                        <span class="label">Disponível:</span>
                        <span class="value">R$ <?php echo number_format($saldoTotal, 2, ',', '.'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Layout de Duas Colunas -->
        <div class="dashboard-grid">
            <!-- Coluna Principal -->
            <div class="main-column">
                <!-- Como Funciona o Cashback -->
                <div class="info-card how-it-works">
                    <h3>💡 Como Funciona o Seu Cashback</h3>
                    <div class="steps">
                        <div class="step">
                            <span class="step-number">1</span>
                            <div class="step-content">
                                <strong>Você compra</strong>
                                <p>Faça compras nas lojas parceiras</p>
                            </div>
                        </div>
                        <div class="step">
                            <span class="step-number">2</span>
                            <div class="step-content">
                                <strong>Recebe cashback</strong>
                                <p>Ganha de volta parte do valor</p>
                            </div>
                        </div>
                        <div class="step">
                            <span class="step-number">3</span>
                            <div class="step-content">
                                <strong>Usa o saldo</strong>
                                <p>Desconto na próxima compra na mesma loja</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Suas Últimas Compras -->
                <div class="section-card recent-transactions">
                    <div class="section-header">
                        <h3>🛍️ Suas Últimas Compras</h3>
                        <a href="<?php echo CLIENT_STATEMENT_URL; ?>" class="see-all">Ver Todas</a>
                    </div>
                    
                    <?php if (empty($recentTransactions)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">🛒</div>
                            <h4>Ainda não há compras</h4>
                            <p>Quando você fizer compras nas lojas parceiras, elas aparecerão aqui</p>
                            <a href="<?php echo CLIENT_STORES_URL; ?>" class="cta-btn">Ver Lojas Parceiras</a>
                        </div>
                    <?php else: ?>
                        <div class="transactions-list">
                            <?php foreach ($recentTransactions as $transaction): ?>
                                <div class="transaction-item">
                                    <div class="transaction-main">
                                        <div class="store-info">
                                            <div class="store-avatar">
                                                <?php if (!empty($transaction['loja_logo'])): ?>
                                                    <img src="../../uploads/store_logos/<?php echo htmlspecialchars($transaction['loja_logo']); ?>" 
                                                         alt="<?php echo htmlspecialchars($transaction['loja_nome']); ?>">
                                                <?php else: ?>
                                                    <span><?php echo strtoupper(substr($transaction['loja_nome'], 0, 1)); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="store-details">
                                                <strong><?php echo htmlspecialchars($transaction['loja_nome']); ?></strong>
                                                <span class="transaction-date">
                                                    <?php echo date('d/m/Y', strtotime($transaction['data_transacao'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="transaction-values">
                                            <div class="purchase-value">
                                                <span class="label">Compra:</span>
                                                <span class="value">R$ <?php echo number_format($transaction['valor_total'], 2, ',', '.'); ?></span>
                                            </div>
                                            <?php if ($transaction['saldo_usado'] > 0): ?>
                                                <div class="balance-used">
                                                    <span class="label">Usou saldo:</span>
                                                    <span class="value used">- R$ <?php echo number_format($transaction['saldo_usado'], 2, ',', '.'); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <div class="cashback-earned">
                                                <span class="label">Cashback:</span>
                                                <span class="value earned">+ R$ <?php echo number_format($transaction['valor_cliente'], 2, ',', '.'); ?></span>
                                                <span class="status-badge <?php echo $transaction['status']; ?>">
                                                    <?php 
                                                    switch($transaction['status']) {
                                                        case 'aprovado': echo '✅ Liberado'; break;
                                                        case 'pendente': echo '⏳ Aguardando'; break;
                                                        default: echo ucfirst($transaction['status']);
                                                    }
                                                    ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Coluna Lateral -->
            <div class="sidebar-column">
                <!-- Seus Saldos por Loja -->
                <div class="section-card store-balances">
                    <div class="section-header">
                        <h3>💰 Seus Saldos por Loja</h3>
                        <span class="info-tooltip" title="Cada loja tem seu próprio saldo">ℹ️</span>
                    </div>
                    
                    <?php if (empty($storeBalances)): ?>
                        <div class="empty-state-small">
                            <p>Você ainda não tem saldo</p>
                            <span class="tip">Faça compras para ganhar cashback!</span>
                        </div>
                    <?php else: ?>
                        <div class="store-balance-list">
                            <?php foreach ($storeBalances as $balance): ?>
                                <div class="balance-item">
                                    <div class="store-avatar-small">
                                        <?php if (!empty($balance['logo'])): ?>
                                            <img src="../../uploads/store_logos/<?php echo htmlspecialchars($balance['logo']); ?>" 
                                                 alt="<?php echo htmlspecialchars($balance['nome_fantasia']); ?>">
                                        <?php else: ?>
                                            <span><?php echo strtoupper(substr($balance['nome_fantasia'], 0, 1)); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="balance-details">
                                        <div class="store-name"><?php echo htmlspecialchars($balance['nome_fantasia']); ?></div>
                                        <div class="balance-amount">R$ <?php echo number_format($balance['saldo_disponivel'], 2, ',', '.'); ?></div>
                                        <div class="balance-info"><?php echo $balance['total_compras']; ?> compra(s)</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <a href="<?php echo CLIENT_BALANCE_URL; ?>" class="view-all-balances">Ver Todos os Saldos →</a>
                    <?php endif; ?>
                </div>

                <!-- Dica do Dia -->
                <div class="tip-card">
                    <div class="tip-header">
                        <span class="tip-icon">💡</span>
                        <h4>Dica do Dia</h4>
                    </div>
                    <div class="tip-content">
                        <p><strong>Importante:</strong> Seu saldo só pode ser usado na loja onde foi ganho. É como ter uma "carteira" separada para cada loja!</p>
                    </div>
                </div>

                <!-- Meta de Economia -->
                <?php if ($totalGanho > 0): ?>
                    <div class="goal-card">
                        <h4>🎯 Sua Jornada de Economia</h4>
                        <div class="progress-info">
                            <p>Você já economizou</p>
                            <div class="progress-amount">R$ <?php echo number_format($totalGanho, 2, ',', '.'); ?></div>
                        </div>
                        
                        <?php 
                        $nextGoal = 100;
                        if ($totalGanho >= 100) $nextGoal = 250;
                        if ($totalGanho >= 250) $nextGoal = 500;
                        if ($totalGanho >= 500) $nextGoal = 1000;
                        
                        $progress = min(($totalGanho / $nextGoal) * 100, 100);
                        ?>
                        
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                        </div>
                        <p class="progress-text">
                            Próxima meta: R$ <?php echo number_format($nextGoal, 0, ',', '.'); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../../assets/js/client/dashboard-new.js"></script>
</body>
</html>