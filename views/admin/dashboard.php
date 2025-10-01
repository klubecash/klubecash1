<?php
//views/admin/dashboard.php
// Definir o menu ativo na sidebar
$activeMenu = 'painel';

// Incluir conexão com o banco de dados
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../models/CashbackBalance.php';

// Iniciar sessão
session_start();

// Verificar se o usuário está logado e é administrador
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: " . LOGIN_URL . "?error=acesso_restrito");
    exit;
}

// Obter dados do usuário logado e todas as estatísticas (mantendo toda a lógica original)
try {
    $db = Database::getConnection();
    
    // Buscar informações do usuário
    $userId = $_SESSION['user_id'];
    $userStmt = $db->prepare("SELECT nome FROM usuarios WHERE id = ? AND tipo = 'admin' AND status = 'ativo'");
    $userStmt->execute([$userId]);
    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        header("Location: " . LOGIN_URL . "?error=acesso_restrito");
        exit;
    }
    
    $adminName = $userData['nome'];
    
    // Total de usuários
    $userCountStmt = $db->query("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'cliente'");
    $totalUsers = $userCountStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total de lojas
    $storeStmt = $db->query("SELECT COUNT(*) as total FROM lojas WHERE status = 'aprovado'");
    $totalStores = $storeStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total de cashback
    $cashbackStmt = $db->query("SELECT SUM(valor_cashback) as total FROM transacoes_cashback");
    $totalCashback = $cashbackStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Estatísticas de saldo - total de saldo disponível em todas as lojas
    $saldoDisponivelStmt = $db->query("SELECT SUM(saldo_disponivel) as total FROM cashback_saldos");
    $totalSaldoDisponivel = $saldoDisponivelStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Total de saldo usado pelos clientes
    $saldoUsadoStmt = $db->query("
        SELECT SUM(valor) as total 
        FROM cashback_movimentacoes 
        WHERE tipo_operacao = 'uso'
    ");
    $totalSaldoUsado = $saldoUsadoStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Estatísticas de transações com saldo
    $transacoesComSaldoStmt = $db->query("
        SELECT 
            COUNT(DISTINCT t.id) as total_transacoes,
            COUNT(DISTINCT CASE WHEN cm.id IS NOT NULL THEN t.id END) as transacoes_com_saldo,
            COUNT(DISTINCT CASE WHEN cm.id IS NULL THEN t.id END) as transacoes_sem_saldo
        FROM transacoes_cashback t
        LEFT JOIN cashback_movimentacoes cm ON t.id = cm.transacao_uso_id AND cm.tipo_operacao = 'uso'
        WHERE t.status = 'aprovado'
    ");
    $estatisticasSaldo = $transacoesComSaldoStmt->fetch(PDO::FETCH_ASSOC);
    
    // Percentual de transações com uso de saldo
    $percentualComSaldo = $estatisticasSaldo['total_transacoes'] > 0 ? 
        ($estatisticasSaldo['transacoes_com_saldo'] / $estatisticasSaldo['total_transacoes']) * 100 : 0;
    
    // Lojas pendentes de aprovação
    $pendingStores = $db->query("
        SELECT id, nome_fantasia, razao_social, cnpj 
        FROM lojas 
        WHERE status = 'pendente' 
        ORDER BY data_cadastro DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Últimas transações com informações de saldo usado
    $recentTransactions = $db->query("
        SELECT 
            t.id, 
            t.valor_total, 
            t.valor_cashback, 
            t.codigo_transacao,
            t.data_transacao,
            u.nome as usuario, 
            l.nome_fantasia as loja, 
            l.razao_social,
            COALESCE(
                (SELECT SUM(cm.valor) 
                 FROM cashback_movimentacoes cm 
                 WHERE cm.transacao_uso_id = t.id AND cm.tipo_operacao = 'uso'), 0
            ) as saldo_usado
        FROM transacoes_cashback t
        JOIN usuarios u ON t.usuario_id = u.id
        JOIN lojas l ON t.loja_id = l.id
        ORDER BY t.data_transacao DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Impacto financeiro do saldo no sistema
    $impactoFinanceiroStmt = $db->query("
        SELECT 
            SUM(t.valor_total) as valor_vendas_originais,
            SUM(t.valor_total - COALESCE(cm_sum.saldo_usado, 0)) as valor_vendas_liquidas,
            SUM(t.valor_cashback) as comissoes_recebidas
        FROM transacoes_cashback t
        LEFT JOIN (
            SELECT 
                transacao_uso_id,
                SUM(valor) as saldo_usado
            FROM cashback_movimentacoes 
            WHERE tipo_operacao = 'uso'
            GROUP BY transacao_uso_id
        ) cm_sum ON t.id = cm_sum.transacao_uso_id
        WHERE t.status = 'aprovado'
    ");
    $impactoFinanceiro = $impactoFinanceiroStmt->fetch(PDO::FETCH_ASSOC);
    
    // Top 5 clientes que mais usaram saldo
    $topClientesSaldoStmt = $db->query("
        SELECT 
            u.nome,
            u.email,
            SUM(cm.valor) as total_saldo_usado,
            COUNT(cm.id) as vezes_usado
        FROM cashback_movimentacoes cm
        JOIN usuarios u ON cm.usuario_id = u.id
        WHERE cm.tipo_operacao = 'uso'
        GROUP BY u.id, u.nome, u.email
        ORDER BY total_saldo_usado DESC
        LIMIT 5
    ");
    $topClientesSaldo = $topClientesSaldoStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Erro ao carregar estatísticas: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
    <title>Dashboard - Klube Cash</title>
    <link rel="stylesheet" href="../../assets/css/views/admin/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/views/admin/dashboard1.css">
    <link rel="stylesheet" href="../../assets/css/layout-fix.css">
    
    <!-- Fonte moderna para melhor legibilidade -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <?php include_once '../components/sidebar.php'; ?>
    
    <!-- Conteúdo Principal -->
    <div class="main-content" id="mainContent">
        <div class="dashboard-wrapper">
            <!-- Cabeçalho Responsivo -->
            <div class="dashboard-header">
                <div class="header-content">
                    <div class="welcome-section">
                        <h1 class="main-title">Bem-vindo, <?php echo htmlspecialchars($adminName); ?>!</h1>
                        <p class="subtitle">Painel de controle administrativo</p>
                    </div>
                    <div class="header-actions">
                        <div class="quick-stats">
                            <div class="quick-stat-item">
                                <span class="stat-number"><?php echo number_format($totalUsers); ?></span>
                                <span class="stat-label">Usuários</span>
                            </div>
                            <div class="quick-stat-item">
                                <span class="stat-number"><?php echo number_format($totalStores); ?></span>
                                <span class="stat-label">Lojas</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php else: ?>
            
            <!-- Cards de estatísticas principais - Layout Responsivo Melhorado -->
            <div class="stats-grid">
                <div class="stat-card primary-card">
                    <div class="stat-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($totalUsers); ?></div>
                        <div class="stat-title">Usuários Registrados</div>
                        <div class="stat-subtitle">Clientes ativos na plataforma</div>
                    </div>
                </div>
                
                <div class="stat-card success-card">
                    <div class="stat-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 3h18l-2 13H5L3 3z"></path>
                            <path d="M16 16a4 4 0 0 1-8 0"></path>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($totalStores); ?></div>
                        <div class="stat-title">Lojas Parceiras</div>
                        <div class="stat-subtitle">Estabelecimentos aprovados</div>
                    </div>
                </div>
                
                <div class="stat-card info-card">
                    <div class="stat-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="1" x2="12" y2="23"></line>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">R$ <?php echo number_format($totalCashback, 2, ',', '.'); ?></div>
                        <div class="stat-title">Total de Cashback</div>
                        <div class="stat-subtitle">Creditado aos clientes</div>
                    </div>
                </div>
                
                <div class="stat-card warning-card">
                    <div class="stat-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                            <line x1="1" y1="10" x2="23" y2="10"></line>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">R$ <?php echo number_format($totalSaldoDisponivel, 2, ',', '.'); ?></div>
                        <div class="stat-title">Saldo Disponível</div>
                        <div class="stat-subtitle">Acumulado pelos clientes</div>
                    </div>
                </div>
            </div>
            
            <!-- Cards de estatísticas de saldo - Seção especial -->
            <div class="balance-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M12 6v6l4 2"></path>
                        </svg>
                        Análise de Saldo
                    </h2>
                    <p class="section-subtitle">Estatísticas detalhadas do uso de saldo pelos clientes</p>
                </div>
                
                <div class="balance-stats-grid">
                    <div class="balance-stat-card">
                        <div class="balance-stat-icon used">💰</div>
                        <div class="balance-stat-content">
                            <div class="balance-stat-value">R$ <?php echo number_format($totalSaldoUsado, 2, ',', '.'); ?></div>
                            <div class="balance-stat-title">Saldo Usado</div>
                            <div class="balance-stat-subtitle">Total utilizado pelos clientes</div>
                        </div>
                    </div>
                    
                    <div class="balance-stat-card">
                        <div class="balance-stat-icon transactions">🏪</div>
                        <div class="balance-stat-content">
                            <div class="balance-stat-value"><?php echo number_format($estatisticasSaldo['transacoes_com_saldo']); ?></div>
                            <div class="balance-stat-title">Transações c/ Saldo</div>
                            <div class="balance-stat-subtitle"><?php echo number_format($percentualComSaldo, 1); ?>% do total</div>
                        </div>
                    </div>
                    
                    <div class="balance-stat-card">
                        <div class="balance-stat-icon rate">📊</div>
                        <div class="balance-stat-content">
                            <div class="balance-stat-value"><?php echo number_format($percentualComSaldo, 1); ?>%</div>
                            <div class="balance-stat-title">Taxa de Uso</div>
                            <div class="balance-stat-subtitle">Clientes usando saldo</div>
                        </div>
                    </div>
                    
                    <div class="balance-stat-card">
                        <div class="balance-stat-icon savings">💸</div>
                        <div class="balance-stat-content">
                            <div class="balance-stat-value">R$ <?php echo number_format($totalSaldoUsado, 2, ',', '.'); ?></div>
                            <div class="balance-stat-title">Economia Clientes</div>
                            <div class="balance-stat-subtitle">Total economizado</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Layout de duas colunas responsivo -->
            <div class="dashboard-grid">
                <!-- Coluna Esquerda -->
                <div class="dashboard-column">
                    <!-- Impacto Financeiro -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <div class="card-title">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="1" x2="12" y2="23"></line>
                                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                </svg>
                                Impacto Financeiro do Saldo
                            </div>
                        </div>
                        <div class="financial-impact">
                            <div class="impact-item">
                                <span class="impact-label">Valor original das vendas</span>
                                <span class="impact-value">R$ <?php echo number_format($impactoFinanceiro['valor_vendas_originais'] ?? 0, 2, ',', '.'); ?></span>
                            </div>
                            <div class="impact-item">
                                <span class="impact-label">Desconto via saldo</span>
                                <span class="impact-value balance-discount">- R$ <?php echo number_format($totalSaldoUsado, 2, ',', '.'); ?></span>
                            </div>
                            <div class="impact-item">
                                <span class="impact-label">Valor líquido das vendas</span>
                                <span class="impact-value">R$ <?php echo number_format($impactoFinanceiro['valor_vendas_liquidas'] ?? 0, 2, ',', '.'); ?></span>
                            </div>
                            <div class="impact-item total">
                                <span class="impact-label">Comissões recebidas</span>
                                <span class="impact-value">R$ <?php echo number_format($impactoFinanceiro['comissoes_recebidas'] ?? 0, 2, ',', '.'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Aprovar Lojas -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <div class="card-title">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9 12l2 2 4-4"></path>
                                    <path d="M21 12c-1 0-3-1-3-3s2-3 3-3 3 1 3 3-2 3-3 3"></path>
                                    <path d="M3 12c1 0 3-1 3-3s-2-3-3-3-3 1-3 3 2 3 3 3"></path>
                                </svg>
                                Aprovar Lojas
                            </div>
                        </div>
                        
                        <div class="responsive-table-container">
                            <table class="responsive-table">
                                <thead>
                                    <tr>
                                        <th>Nome da Loja</th>
                                        <th class="desktop-only">Tipo</th>
                                        <th>Ação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($pendingStores)): ?>
                                        <tr>
                                            <td colspan="3" class="empty-state">
                                                <div class="empty-content">
                                                    <span class="empty-icon">✅</span>
                                                    <span>Nenhuma loja pendente</span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($pendingStores as $store): ?>
                                            <tr>
                                                <td>
                                                    <div class="store-info">
                                                        <div class="store-name"><?php echo htmlspecialchars($store['nome_fantasia']); ?></div>
                                                        <div class="store-details mobile-only">Varejo</div>
                                                    </div>
                                                </td>
                                                <td class="desktop-only">Varejo</td>
                                                <td>
                                                    <button class="btn btn-primary btn-sm" onclick="approveStore(<?php echo $store['id']; ?>)">
                                                        Aprovar
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Coluna Direita -->
                <div class="dashboard-column">
                    <!-- Top Clientes -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <div class="card-title">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="8.5" cy="7" r="4"></circle>
                                    <line x1="20" y1="8" x2="20" y2="14"></line>
                                    <line x1="23" y1="11" x2="17" y2="11"></line>
                                </svg>
                                Top Clientes - Uso de Saldo
                            </div>
                        </div>
                        
                        <?php if (!empty($topClientesSaldo)): ?>
                            <div class="top-clients-list">
                                <?php foreach ($topClientesSaldo as $index => $cliente): ?>
                                    <div class="client-item">
                                        <div class="client-rank">#<?php echo $index + 1; ?></div>
                                        <div class="client-info">
                                            <div class="client-name"><?php echo htmlspecialchars($cliente['nome']); ?></div>
                                            <div class="client-details">
                                                R$ <?php echo number_format($cliente['total_saldo_usado'], 2, ',', '.'); ?> 
                                                (<?php echo $cliente['vezes_usado']; ?> uso<?php echo $cliente['vezes_usado'] > 1 ? 's' : ''; ?>)
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state-small">
                                <span class="empty-icon">👥</span>
                                <p>Nenhum cliente usou saldo ainda.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Notificações -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <div class="card-title">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                                </svg>
                                Notificações
                            </div>
                        </div>
                        
                        <div class="notifications-container">
                            <div class="empty-state-small">
                                <span class="empty-icon">🔔</span>
                                <p>Nenhuma notificação</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Últimas Transações - Tabela Responsiva Completa -->
            <div class="dashboard-card transactions-card">
                <div class="card-header">
                    <div class="card-title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                        Últimas Transações
                    </div>
                </div>
                
                <div class="responsive-table-container">
                    <table class="responsive-table transactions-table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Cliente</th>
                                <th class="desktop-only">Loja</th>
                                <th>Valor</th>
                                <th class="desktop-only">Saldo Usado</th>
                                <th class="desktop-only">Cashback</th>
                                <th class="mobile-only">Data</th>
                                <th class="desktop-only">Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentTransactions)): ?>
                                <tr>
                                    <td colspan="9" class="empty-state">
                                        <div class="empty-content">
                                            <span class="empty-icon">📝</span>
                                            <span>Nenhuma transação encontrada</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentTransactions as $transaction): ?>
                                    <tr>
                                        <td>
                                            <div class="transaction-code">
                                                <?php echo htmlspecialchars($transaction['codigo_transacao'] ?? 'N/A'); ?>
                                                <?php if ($transaction['saldo_usado'] > 0): ?>
                                                    <span class="balance-indicator" title="Cliente usou saldo">💰</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="client-info">
                                                <div class="client-name"><?php echo htmlspecialchars($transaction['usuario']); ?></div>
                                                <div class="mobile-only transaction-details">
                                                    <div class="mobile-detail">
                                                        <strong>Loja:</strong> <?php echo htmlspecialchars($transaction['loja']); ?>
                                                    </div>
                                                    <?php if ($transaction['saldo_usado'] > 0): ?>
                                                        <div class="mobile-detail">
                                                            <strong>Saldo:</strong> R$ <?php echo number_format($transaction['saldo_usado'], 2, ',', '.'); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="mobile-detail">
                                                        <strong>Cashback:</strong> R$ <?php echo number_format($transaction['valor_cashback'], 2, ',', '.'); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="desktop-only">
                                            <div class="store-info">
                                                <?php echo htmlspecialchars($transaction['loja']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="value-display">
                                                R$ <?php echo number_format($transaction['valor_total'], 2, ',', '.'); ?>
                                            </div>
                                        </td>
                                        <td class="desktop-only">
                                            <?php if ($transaction['saldo_usado'] > 0): ?>
                                                <span class="saldo-usado">R$ <?php echo number_format($transaction['saldo_usado'], 2, ',', '.'); ?></span>
                                            <?php else: ?>
                                                <span class="sem-saldo">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="desktop-only">
                                            <div class="cashback-value">
                                                R$ <?php echo number_format($transaction['valor_cashback'], 2, ',', '.'); ?>
                                            </div>
                                        </td>
                                        <td class="mobile-only">
                                            <div class="date-compact">
                                                <?php echo date('d/m', strtotime($transaction['data_transacao'])); ?>
                                            </div>
                                        </td>
                                        <td class="desktop-only">
                                            <div class="date-full">
                                                <?php echo date('d/m/Y H:i', strtotime($transaction['data_transacao'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <button class="btn btn-secondary btn-sm" onclick="viewTransaction(<?php echo $transaction['id']; ?>)">
                                                <span class="desktop-only">Detalhar</span>
                                                <span class="mobile-only">Ver</span>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Mantendo toda a funcionalidade JavaScript original
        function approveStore(storeId) {
            if (confirm('Tem certeza que deseja aprovar esta loja?')) {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', '<?php echo SITE_URL; ?>/controllers/StoreController.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (this.status === 200) {
                        alert('Loja aprovada com sucesso!');
                        location.reload();
                    } else {
                        alert('Erro ao aprovar loja. Por favor, tente novamente.');
                    }
                };
                xhr.send('action=approve&id=' + storeId);
            }
        }
        
        function viewTransaction(transactionId) {
            window.location.href = '<?php echo SITE_URL; ?>/admin/transacao/' + transactionId;
        }
        
        // Animações e melhorias de UX
        document.addEventListener('DOMContentLoaded', function() {
            // Animar números nos cards
            animateNumbers();
            
            // Adicionar interatividade aos cards
            addCardInteractions();
            
            // Melhorar responsividade da tabela
            handleTableResponsiveness();
        });
        
        function animateNumbers() {
            const statValues = document.querySelectorAll('.stat-value, .balance-stat-value');
            statValues.forEach((element, index) => {
                element.style.opacity = '0';
                element.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    element.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }, index * 100);
            });
        }
        
        function addCardInteractions() {
            const cards = document.querySelectorAll('.stat-card, .balance-stat-card, .dashboard-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        }
        
        function handleTableResponsiveness() {
            const tables = document.querySelectorAll('.responsive-table');
            tables.forEach(table => {
                // Adicionar indicador de scroll horizontal em mobile
                const container = table.parentElement;
                if (container.scrollWidth > container.clientWidth) {
                    container.classList.add('has-scroll');
                }
            });
        }
        
        // Função para detectar mudanças de orientação em mobile
        window.addEventListener('orientationchange', function() {
            setTimeout(() => {
                handleTableResponsiveness();
            }, 100);
        });
    </script>
</body>
</html>