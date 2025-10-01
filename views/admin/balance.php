<?php
// views/admin/balance.php
$activeMenu = 'saldo';

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../controllers/AdminController.php';
require_once '../../controllers/StoreBalancePaymentController.php';

session_start();

// Verificar se o usuário está logado e é administrador
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: " . LOGIN_URL . "?error=acesso_restrito");
    exit;
}

// Obter dados do saldo do administrador
$error = '';
$balanceData = [];
$saldoTotal = 0;
$saldoPendente = 0;
$movimentacoes = [];
$estatisticas = [];

try {
    $db = Database::getConnection();
    
    // 1. Obter saldo admin
    $saldoStmt = $db->prepare("SELECT * FROM admin_saldo WHERE id = 1");
    $saldoStmt->execute();
    $saldoAdmin = $saldoStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$saldoAdmin) {
        // Criar registro inicial se não existir
        $createStmt = $db->prepare("
            INSERT INTO admin_saldo (id, valor_total, valor_disponivel, valor_pendente) 
            VALUES (1, 0, 0, 0)
        ");
        $createStmt->execute();
        $saldoAdmin = [
            'valor_total' => 0,
            'valor_disponivel' => 0,
            'valor_pendente' => 0,
            'ultima_atualizacao' => date('Y-m-d H:i:s')
        ];
    }
    
    // 2. Obter movimentações do saldo admin
    $movStmt = $db->prepare("
        SELECT 
            asm.*,
            tc.codigo_transacao,
            l.nome_fantasia as loja_nome
        FROM admin_saldo_movimentacoes asm
        LEFT JOIN transacoes_cashback tc ON asm.transacao_id = tc.id
        LEFT JOIN lojas l ON tc.loja_id = l.id
        ORDER BY asm.data_operacao DESC
        LIMIT 50
    ");
    $movStmt->execute();
    $movimentacoes = $movStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. CORRIGIDO: Obter estatísticas incluindo transações MVP
    $statsStmt = $db->prepare("
        SELECT 
            (
                -- Transações com comissão (lojas normais)
                SELECT COUNT(DISTINCT tcom.id)
                FROM transacoes_comissao tcom
                JOIN transacoes_cashback tc ON tcom.transacao_id = tc.id
                WHERE tcom.tipo_usuario = 'admin'
            ) + (
                -- Transações MVP aprovadas (sem comissão)
                SELECT COUNT(*)
                FROM transacoes_cashback tc
                JOIN lojas l ON tc.loja_id = l.id
                JOIN usuarios u ON l.usuario_id = u.id
                WHERE tc.status = 'aprovado' 
                AND u.mvp = 'sim'
                AND tc.id NOT IN (
                    SELECT transacao_id FROM transacoes_comissao WHERE tipo_usuario = 'admin'
                )
            ) as total_transacoes,
            
            COALESCE((
                SELECT SUM(CASE WHEN tc.status = 'aprovado' THEN tcom.valor_comissao ELSE 0 END)
                FROM transacoes_comissao tcom
                JOIN transacoes_cashback tc ON tcom.transacao_id = tc.id
                WHERE tcom.tipo_usuario = 'admin'
            ), 0) as comissoes_aprovadas,
            
            (
                SELECT COUNT(CASE WHEN tc.status = 'pendente' THEN 1 END)
                FROM transacoes_comissao tcom
                JOIN transacoes_cashback tc ON tcom.transacao_id = tc.id
                WHERE tcom.tipo_usuario = 'admin'
            ) as transacoes_pendentes,
            
            COALESCE((
                SELECT SUM(CASE WHEN tc.status = 'pendente' THEN tcom.valor_comissao ELSE 0 END)
                FROM transacoes_comissao tcom
                JOIN transacoes_cashback tc ON tcom.transacao_id = tc.id
                WHERE tcom.tipo_usuario = 'admin'
            ), 0) as comissoes_pendentes,
            
            (
                -- Transações aprovadas com comissão
                SELECT COUNT(CASE WHEN tc.status = 'aprovado' THEN 1 END)
                FROM transacoes_comissao tcom
                JOIN transacoes_cashback tc ON tcom.transacao_id = tc.id
                WHERE tcom.tipo_usuario = 'admin'
            ) + (
                -- Transações MVP aprovadas (contam como aprovadas)
                SELECT COUNT(*)
                FROM transacoes_cashback tc
                JOIN lojas l ON tc.loja_id = l.id
                JOIN usuarios u ON l.usuario_id = u.id
                WHERE tc.status = 'aprovado' 
                AND u.mvp = 'sim'
                AND tc.id NOT IN (
                    SELECT transacao_id FROM transacoes_comissao WHERE tipo_usuario = 'admin'
                )
            ) as transacoes_aprovadas,
            
            COALESCE((
                SELECT SUM(tcom.valor_comissao)
                FROM transacoes_comissao tcom
                WHERE tcom.tipo_usuario = 'admin'
            ), 0) as total_comissoes_recebidas
    ");
    $statsStmt->execute();
    $estatisticas = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Garantir que não há valores nulos
    foreach ($estatisticas as $key => $value) {
        if ($value === null) {
            $estatisticas[$key] = 0;
        }
    }
    
    // 4. Obter dados mensais para gráfico
    $monthlyStmt = $db->prepare("
        SELECT 
            DATE_FORMAT(asm.data_operacao, '%Y-%m') as mes,
            COALESCE(SUM(CASE WHEN asm.tipo = 'credito' THEN asm.valor ELSE 0 END), 0) as entrada,
            COALESCE(SUM(CASE WHEN asm.tipo = 'debito' THEN asm.valor ELSE 0 END), 0) as saida,
            COALESCE(SUM(CASE WHEN asm.tipo = 'credito' THEN asm.valor ELSE -asm.valor END), 0) as saldo_liquido
        FROM admin_saldo_movimentacoes asm
        WHERE asm.data_operacao >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(asm.data_operacao, '%Y-%m')
        ORDER BY mes ASC
        LIMIT 12
    ");
    $monthlyStmt->execute();
    $mensal = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);

    // Se não há dados mensais, criar array com mês atual zerado
    if (empty($mensal)) {
        $mensal = [
            [
                'mes' => date('Y-m'),
                'entrada' => 0,
                'saida' => 0,
                'saldo_liquido' => 0
            ]
        ];
    }

    // Garantir que temos pelo menos os últimos 6 meses para o gráfico
    $mesesCompletos = [];
    for ($i = 5; $i >= 0; $i--) {
        $mesRef = date('Y-m', strtotime("-$i month"));
        $mesEncontrado = false;
        
        foreach ($mensal as $dadosMes) {
            if ($dadosMes['mes'] === $mesRef) {
                $mesesCompletos[] = $dadosMes;
                $mesEncontrado = true;
                break;
            }
        }
        
        if (!$mesEncontrado) {
            $mesesCompletos[] = [
                'mes' => $mesRef,
                'entrada' => 0,
                'saida' => 0,
                'saldo_liquido' => 0
            ];
        }
    }

    $mensal = $mesesCompletos;
    
    // 5. CORRIGIDO: Obter top lojas incluindo MVP (por volume de transações)
    $topLojasStmt = $db->prepare("
        SELECT 
            l.id,
            l.nome_fantasia,
            u.mvp,
            (
                -- Transações com comissão
                SELECT COUNT(DISTINCT tcom.id)
                FROM transacoes_comissao tcom
                JOIN transacoes_cashback tc2 ON tcom.transacao_id = tc2.id
                WHERE tcom.tipo_usuario = 'admin' AND tc2.loja_id = l.id
                AND tc2.status IN ('aprovado', 'pendente')
            ) + (
                -- Transações MVP aprovadas (sem comissão)
                SELECT COUNT(*)
                FROM transacoes_cashback tc3
                WHERE tc3.loja_id = l.id 
                AND tc3.status = 'aprovado'
                AND u.mvp = 'sim'
                AND tc3.id NOT IN (
                    SELECT transacao_id FROM transacoes_comissao WHERE tipo_usuario = 'admin'
                )
            ) as quantidade_transacoes,
            
            COALESCE((
                SELECT SUM(tcom.valor_comissao)
                FROM transacoes_comissao tcom
                JOIN transacoes_cashback tc2 ON tcom.transacao_id = tc2.id
                WHERE tcom.tipo_usuario = 'admin' AND tc2.loja_id = l.id
                AND tc2.status IN ('aprovado', 'pendente')
            ), 0) as total_comissoes,
            
            -- Volume total de vendas (incluindo MVP)
            COALESCE((
                SELECT SUM(tc4.valor_total)
                FROM transacoes_cashback tc4
                WHERE tc4.loja_id = l.id 
                AND tc4.status IN ('aprovado', 'pendente')
            ), 0) as volume_vendas
            
        FROM lojas l
        JOIN usuarios u ON l.usuario_id = u.id
        WHERE EXISTS (
            -- Tem transações com comissão OU é MVP com transações
            SELECT 1 FROM transacoes_cashback tc 
            WHERE tc.loja_id = l.id 
            AND tc.status IN ('aprovado', 'pendente')
        )
        GROUP BY l.id, l.nome_fantasia, u.mvp
        HAVING quantidade_transacoes > 0
        ORDER BY volume_vendas DESC, quantidade_transacoes DESC
        LIMIT 10
    ");
    $topLojasStmt->execute();
    $topLojas = $topLojasStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 6. Obter estatísticas de pagamentos de saldo
    $balanceStats = StoreBalancePaymentController::getBalanceStatistics();

    // 7. CORRIGIDO: Calcular RESERVA REAL baseada nos saldos dos clientes
    try {
        // Calcular reserva atual (soma de todos os saldos disponíveis dos clientes)
        $reservaRealStmt = $db->prepare("
            SELECT 
                COALESCE(SUM(saldo_disponivel), 0) as valor_disponivel_real,
                COALESCE(SUM(total_creditado), 0) as valor_total_creditado,
                COALESCE(SUM(total_usado), 0) as valor_total_usado,
                COUNT(*) as total_contas
            FROM cashback_saldos
            WHERE saldo_disponivel > 0 OR total_creditado > 0
        ");
        $reservaRealStmt->execute();
        $reservaCalculada = $reservaRealStmt->fetch(PDO::FETCH_ASSOC);
        
        // CORRIGIDO: Buscar movimentações de uso de saldo mais detalhadas
        $usoSaldoStmt = $db->prepare("
            SELECT 
                cm.*,
                t.codigo_transacao,
                COALESCE(l.nome_fantasia, 'Loja não encontrada') as loja_nome,
                COALESCE(u.nome, 'Cliente não encontrado') as cliente_nome,
                CASE 
                    WHEN cm.pagamento_id IS NOT NULL THEN 'Reembolsado'
                    ELSE 'Pendente reembolso'
                END as status_reembolso
            FROM cashback_movimentacoes cm
            LEFT JOIN transacoes_cashback t ON cm.transacao_uso_id = t.id
            LEFT JOIN lojas l ON cm.loja_id = l.id
            LEFT JOIN usuarios u ON cm.usuario_id = u.id
            WHERE cm.tipo_operacao = 'uso'
            AND cm.valor > 0
            ORDER BY cm.data_operacao DESC
            LIMIT 20
        ");
        $usoSaldoStmt->execute();
        $movimentacoesUso = $usoSaldoStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Estruturar dados da reserva baseados nos valores REAIS
        $balanceData['reserva_cashback'] = [
            'reserva' => [
                'valor_total' => floatval($reservaCalculada['valor_total_creditado']), // Total já creditado
                'valor_disponivel' => floatval($reservaCalculada['valor_disponivel_real']), // Disponível nos saldos
                'valor_usado' => floatval($reservaCalculada['valor_total_usado']), // Total usado pelos clientes
                'total_contas' => intval($reservaCalculada['total_contas']) // Quantidade de contas com saldo
            ],
            'movimentacoes' => $movimentacoesUso
        ];
        
        // Log para debugar valores
        error_log("RESERVA DEBUG - Disponível: {$reservaCalculada['valor_disponivel_real']}, Usado: {$reservaCalculada['valor_total_usado']}, Total: {$reservaCalculada['valor_total_creditado']}");
        
    } catch (Exception $e) {
        error_log("Erro ao calcular reserva real: " . $e->getMessage());
        // Fallback para valores zero em caso de erro
        $balanceData['reserva_cashback'] = [
            'reserva' => ['valor_total' => 0, 'valor_disponivel' => 0, 'valor_usado' => 0, 'total_contas' => 0],
            'movimentacoes' => []
        ];
    }

    // 8. NOVO: Obter estatísticas consolidadas de reembolsos
    try {
        $reembolsoStmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT sbp.id) as total_pagamentos_reembolso,
                COALESCE(SUM(CASE WHEN sbp.status = 'pendente' THEN sbp.valor_total ELSE 0 END), 0) as valor_pendente_reembolso,
                COALESCE(SUM(CASE WHEN sbp.status = 'aprovado' THEN sbp.valor_total ELSE 0 END), 0) as valor_pago_reembolso,
                COUNT(DISTINCT CASE WHEN sbp.status = 'pendente' THEN sbp.loja_id END) as lojas_com_reembolso_pendente
            FROM store_balance_payments sbp
        ");
        $reembolsoStmt->execute();
        $statsReembolso = $reembolsoStmt->fetch(PDO::FETCH_ASSOC);
        
        // Garantir que não há valores nulos
        foreach ($statsReembolso as $key => $value) {
            if ($value === null) {
                $statsReembolso[$key] = 0;
            }
        }
        
    } catch (Exception $e) {
        error_log("Erro ao obter estatísticas de reembolso: " . $e->getMessage());
        $statsReembolso = [
            'total_pagamentos_reembolso' => 0,
            'valor_pendente_reembolso' => 0,
            'valor_pago_reembolso' => 0,
            'lojas_com_reembolso_pendente' => 0
        ];
    }

    // Preparar dados para a view
    $balanceData = [
        'saldo_admin' => $saldoAdmin,
        'movimentacoes' => $movimentacoes,
        'estatisticas' => $estatisticas,
        'stats_reembolso' => $statsReembolso, // NOVO
        'mensal' => $mensal,
        'top_lojas' => $topLojas,
        'balance_stats' => $balanceStats,
        'reserva_cashback' => $balanceData['reserva_cashback']
    ];
    
    $saldoTotal = $saldoAdmin['valor_disponivel'];
    $saldoPendente = $saldoAdmin['valor_pendente'];
    
    
} catch (Exception $e) {
    $error = "Erro ao carregar dados do saldo: " . $e->getMessage();
    error_log("Erro em balance.php: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
    <title>Saldo da Administração - Klube Cash</title>
    <link rel="stylesheet" href="../../assets/css/views/admin/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/layout-fix.css">
    <link rel="stylesheet" href="../../assets/css/views/admin/balance.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #FF7A00;
        }
        
        .stat-card.balance {
            border-left-color: #28a745;
        }
        
        .stat-card.pending {
            border-left-color: #ffc107;
        }
        
        .stat-card.outgoing {
            border-left-color: #dc3545;
        }
        
        .stat-card.info {
            border-left-color: #17a2b8;
        }
        
        .stat-card-title {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .stat-card-value {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-card-subtitle {
            font-size: 12px;
            color: #999;
        }
        
        .two-column-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            padding: 20px;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        
        .alert.error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .movement-type {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .movement-type.credito {
            background-color: #d4edda;
            color: #155724;
        }
        
        .movement-type.debito {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .movement-type.uso {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .balance-section {
            background: linear-gradient(135deg, #FF7A00 0%, #ffc107 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .balance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .balance-item {
            text-align: center;
            padding: 15px;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        
        .balance-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 8px;
        }
        
        .balance-value {
            font-size: 24px;
            font-weight: 700;
        }
        
        .quick-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #fff;
            color: #FF7A00;
        }
        
        .btn-secondary {
            background: #17a2b8;
            color: white;
        }
        
        .btn-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-pendente {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-reembolsado {
            background-color: #d4edda;
            color: #155724;
        }
        
        @media (max-width: 768px) {
            .two-column-layout {
                grid-template-columns: 1fr;
            }
            
            .balance-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include_once '../components/sidebar.php'; ?>
    
    <!-- Conteúdo Principal -->
    <div class="main-content" id="mainContent">
        <div class="dashboard-wrapper">
            <!-- Cabeçalho -->
            <div class="dashboard-header">
                <h1>💰 Saldo da Administração</h1>
                <p class="subtitle">Visão geral das receitas e movimentações financeiras da plataforma</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert error">
                    <strong>Erro:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php else: ?>
            
            <!-- Seção Principal do Saldo -->
            <div class="balance-section">
                <h2>💼 Resumo Financeiro</h2>
                <div class="balance-grid">
                    <div class="balance-item">
                        <div class="balance-label">💵 Receita da Administração</div>
                        <div class="balance-value">R$ <?php echo number_format($balanceData['saldo_admin']['valor_disponivel'], 2, ',', '.'); ?></div>
                        <small>Comissões da plataforma</small>
                    </div>
                    
                    <div class="balance-item">
                        <div class="balance-label">🎁 Reserva de Cashback</div>
                        <div class="balance-value">R$ <?php echo number_format($balanceData['reserva_cashback']['reserva']['valor_disponivel'], 2, ',', '.'); ?></div>
                        <small>Disponível para clientes</small>
                    </div>
                    
                    <div class="balance-item">
                        <div class="balance-label">💸 Cashback Usado</div>
                        <div class="balance-value">R$ <?php echo number_format($balanceData['reserva_cashback']['reserva']['valor_usado'], 2, ',', '.'); ?></div>
                        <small>Usado pelos clientes</small>
                    </div>
                    
                    <div class="balance-item">
                        <div class="balance-label">👥 Contas Ativas</div>
                        <div class="balance-value"><?php echo number_format($balanceData['reserva_cashback']['reserva']['total_contas']); ?></div>
                        <small>Clientes com saldo</small>
                    </div>
                </div>
                
                <div class="quick-actions">
                    <a href="<?php echo ADMIN_PAYMENTS_URL; ?>" class="btn-action btn-primary">
                        📋 Gerenciar Pagamentos
                    </a>
                    <a href="<?php echo ADMIN_PAYMENTS_URL; ?>?tab=balance" class="btn-action btn-secondary">
                        💳 Pagamentos de Saldo
                    </a>
                </div>
            </div>
            
            <!-- Seção Explicativa sobre Reembolsos -->
            <div class="info-section" style="background: #e8f4fd; border: 1px solid #bee1f4; border-radius: 10px; padding: 20px; margin-bottom: 30px;">
                <h3 style="color: #0c5460; margin-bottom: 15px;">ℹ️ Sobre os Reembolsos às Lojas</h3>
                <p style="color: #0c5460; margin-bottom: 10px;">
                    <strong>Os reembolsos não afetam a receita da administração.</strong> 
                    Quando clientes usam cashback nas compras, as lojas recebem menos dinheiro efetivamente. 
                    O sistema processa o reembolso desses valores para que as lojas recebam o valor integral de suas vendas.
                </p>
                <p style="color: #0c5460; margin: 0;">
                    <strong>Exemplo:</strong> Cliente compra R$ 100, usa R$ 20 de cashback → Loja recebe R$ 80 + R$ 20 de reembolso = R$ 100 total
                </p>
            </div>
            
            <!-- Cards de estatísticas principais -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-title">📈 Total de Transações</div>
                    <div class="stat-card-value"><?php echo number_format($balanceData['estatisticas']['total_transacoes'] ?? 0); ?></div>
                    <div class="stat-card-subtitle">Comissões processadas</div>
                </div>
                
                <div class="stat-card balance">
                    <div class="stat-card-title">💰 Comissões Aprovadas</div>
                    <div class="stat-card-value">R$ <?php echo number_format($balanceData['estatisticas']['comissoes_aprovadas'] ?? 0, 2, ',', '.'); ?></div>
                    <div class="stat-card-subtitle"><?php echo number_format($balanceData['estatisticas']['transacoes_aprovadas'] ?? 0); ?> transações</div>
                </div>
                
                <div class="stat-card pending">
                    <div class="stat-card-title">⏳ Comissões Pendentes</div>
                    <div class="stat-card-value">R$ <?php echo number_format($balanceData['estatisticas']['comissoes_pendentes'] ?? 0, 2, ',', '.'); ?></div>
                    <div class="stat-card-subtitle"><?php echo number_format($balanceData['estatisticas']['transacoes_pendentes'] ?? 0); ?> transações</div>
                </div>
                
                <div class="stat-card info">
                    <div class="stat-card-title">🏪 Reembolsos Pendentes</div>
                    <div class="stat-card-value">R$ <?php echo number_format($balanceData['stats_reembolso']['valor_pendente_reembolso'] ?? 0, 2, ',', '.'); ?></div>
                    <div class="stat-card-subtitle"><?php echo number_format($balanceData['stats_reembolso']['lojas_com_reembolso_pendente'] ?? 0); ?> lojas</div>
                </div>
                
                <div class="stat-card outgoing">
                    <div class="stat-card-title">💳 Reembolsos Pagos</div>
                    <div class="stat-card-value">R$ <?php echo number_format($balanceData['stats_reembolso']['valor_pago_reembolso'] ?? 0, 2, ',', '.'); ?></div>
                    <div class="stat-card-subtitle">Total reembolsado</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-title">🏪 Lojas Ativas</div>
                    <div class="stat-card-value"><?php echo count($balanceData['top_lojas']); ?></div>
                    <div class="stat-card-subtitle">Gerando comissões</div>
                </div>
            </div>

            <!-- Gráficos e estatísticas -->
            <div class="two-column-layout">
                <!-- Gráfico mensal -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">📊 Movimentação Mensal (Receita Admin)</div>
                        <?php if (empty($mensal) || array_sum(array_column($mensal, 'entrada')) == 0): ?>
                            <small style="color: #666;">Aguardando primeiras movimentações</small>
                        <?php endif; ?>
                    </div>
                    <div class="chart-container">
                        <canvas id="monthlyChart"></canvas>
                        <?php if (empty($mensal) || array_sum(array_column($mensal, 'entrada')) == 0): ?>
                            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; color: #666;">
                                <strong>📊 Sem dados para exibir</strong><br>
                                <small>O gráfico será preenchido conforme as transações forem processadas</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Top lojas -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">🏆 Top Lojas (Comissões)</div>
                    </div>
                    
                    <div class="top-stores-list">
                        <?php if (!empty($balanceData['top_lojas'])): ?>
                            <?php foreach ($balanceData['top_lojas'] as $index => $loja): ?>
                                <div class="store-item">
                                    <div class="store-rank">#<?php echo $index + 1; ?></div>
                                    <div class="store-info">
                                        <div class="store-name"><?php echo htmlspecialchars($loja['nome_fantasia']); ?></div>
                                        <div class="store-details">
                                            R$ <?php echo number_format($loja['total_comissoes'], 2, ',', '.'); ?> 
                                            (<?php echo $loja['quantidade_transacoes']; ?> transações)
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="store-item">
                                <div class="store-info">
                                    <div class="store-name">Nenhuma loja encontrada</div>
                                    <div class="store-details">Aguardando primeiras transações</div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Histórico de Movimentações da Receita Admin -->
            <div class="card transactions-container">
                <div class="card-header">
                    <div class="card-title">📋 Últimas Movimentações - Receita da Administração</div>
                </div>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Descrição</th>
                                <th>Tipo</th>
                                <th>Valor</th>
                                <th>Transação</th>
                                <th>Loja</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($balanceData['movimentacoes'])): ?>
                                <?php foreach ($balanceData['movimentacoes'] as $mov): ?>
                                    <tr>
                                        <td>
                                            <?php 
                                            // Subtrai 3 horas do fuso horário
                                            echo date('d/m/Y H:i', strtotime($mov['data_operacao']) - (3 * 60 * 60)); 
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($mov['descricao']); ?></td>
                                        <td>
                                            <span class="movement-type <?php echo $mov['tipo']; ?>">
                                                <?php echo $mov['tipo'] == 'credito' ? '📈 Entrada' : '📉 Saída'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="color: <?php echo $mov['tipo'] == 'credito' ? '#28a745' : '#dc3545'; ?>; font-weight: 600;">
                                                <?php echo $mov['tipo'] == 'credito' ? '+' : '-'; ?>R$ <?php echo number_format($mov['valor'], 2, ',', '.'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($mov['codigo_transacao'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($mov['loja_nome'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 40px;">
                                        <div style="color: #666;">
                                            <strong>📭 Nenhuma movimentação encontrada</strong><br>
                                            <small>Movimentações aparecerão aqui conforme as transações forem processadas</small>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Histórico de Movimentações da Reserva de Cashback -->
            <div class="card transactions-container">
                <div class="card-header">
                    <div class="card-title">🎁 Últimas Movimentações - Uso de Cashback</div>
                </div>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Cliente</th>
                                <th>Loja</th>
                                <th>Valor Usado</th>
                                <th>Transação</th>
                                <th>Status Reembolso</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($balanceData['reserva_cashback']['movimentacoes'])): ?>
                                <?php foreach ($balanceData['reserva_cashback']['movimentacoes'] as $mov): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($mov['data_operacao'])); ?></td>
                                        <td><?php echo htmlspecialchars($mov['cliente_nome']); ?></td>
                                        <td><?php echo htmlspecialchars($mov['loja_nome']); ?></td>
                                        <td>
                                            <span style="color: #dc3545; font-weight: 600;">
                                                -R$ <?php echo number_format($mov['valor'], 2, ',', '.'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($mov['codigo_transacao'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '', $mov['status_reembolso'])); ?>">
                                                <?php echo $mov['status_reembolso']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 40px;">
                                        <div style="color: #666;">
                                            <strong>🎁 Nenhuma movimentação de cashback encontrada</strong><br>
                                            <small>Movimentações aparecerão aqui conforme o cashback for usado pelos clientes</small>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Dados para o gráfico mensal (apenas receita admin)
        const monthlyData = {
            labels: [
                <?php 
                    $monthLabels = [];
                    foreach ($mensal as $item) {
                        $date = DateTime::createFromFormat('Y-m', $item['mes']);
                        if ($date) {
                            $monthLabels[] = "'" . $date->format('M/Y') . "'";
                        }
                    }
                    echo !empty($monthLabels) ? implode(', ', $monthLabels) : "'Sem dados'";
                ?>
            ],
            entrada: [
                <?php 
                    $entradas = [];
                    foreach ($mensal as $item) {
                        $entradas[] = floatval($item['entrada']);
                    }
                    echo !empty($entradas) ? implode(', ', $entradas) : '0';
                ?>
            ],
            saida: [
                <?php 
                    $saidas = [];
                    foreach ($mensal as $item) {
                        $saidas[] = floatval($item['saida']);
                    }
                    echo !empty($saidas) ? implode(', ', $saidas) : '0';
                ?>
            ]
        };
        
        // Debug dos dados no console
        console.log('Dados do gráfico mensal:', monthlyData);
        
        // Verificar se temos dados válidos
        const hasData = monthlyData.entrada.some(value => value > 0) || monthlyData.saida.some(value => value > 0);
        
        // Inicializar gráfico mensal
        const ctxMonthly = document.getElementById('monthlyChart').getContext('2d');
        
        if (!hasData) {
            // Se não há dados, mostrar mensagem
            ctxMonthly.font = '16px Arial';
            ctxMonthly.fillStyle = '#666';
            ctxMonthly.textAlign = 'center';
            ctxMonthly.fillText('Nenhuma movimentação encontrada nos últimos 6 meses', 
                            ctxMonthly.canvas.width / 2, 
                            ctxMonthly.canvas.height / 2);
        } else {
            // Criar gráfico normal
            const monthlyChart = new Chart(ctxMonthly, {
                type: 'bar',
                data: {
                    labels: monthlyData.labels,
                    datasets: [
                        {
                            label: 'Comissões Recebidas (R$)',
                            data: monthlyData.entrada,
                            backgroundColor: 'rgba(40, 167, 69, 0.8)',
                            borderColor: '#28a745',
                            borderWidth: 2
                        },
                        {
                            label: 'Saídas (R$)',
                            data: monthlyData.saida,
                            backgroundColor: 'rgba(220, 53, 69, 0.8)',
                            borderColor: '#dc3545',
                            borderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Movimentações da Receita Administrativa - Últimos 6 Meses'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + value.toLocaleString('pt-BR', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': R$ ' + 
                                        context.parsed.y.toLocaleString('pt-BR', {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2
                                        });
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Auto-refresh da página a cada 5 minutos para manter dados atualizados
        setTimeout(function() {
            location.reload();
        }, 300000); // 5 minutos
    </script>
</body>
</html>