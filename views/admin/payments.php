<?php
// views/admin/payments.php
// Definir o menu ativo na sidebar
$activeMenu = 'pagamentos';

// Incluir arquivos necessários
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../controllers/AuthController.php';
require_once '../../controllers/TransactionController.php';
require_once '../../models/CashbackBalance.php';
require_once '../../controllers/StoreBalancePaymentController.php';

// Iniciar sessão
session_start();

// Habilitar logs de erro e debug inicial
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

error_log("payments.php - Início da execução. Method: " . $_SERVER['REQUEST_METHOD']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    error_log("payments.php - POST data: " . print_r($_POST, true));
}

// Verificar se o usuário está logado e é administrador
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: " . LOGIN_URL . "?error=acesso_restrito");
    exit;
}

// Processar ações (aprovar/rejeitar)
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'approve' && isset($_POST['payment_id'])) {
            $paymentId = intval($_POST['payment_id']);
            $observacao = $_POST['observacao'] ?? '';
            $result = TransactionController::approvePayment($paymentId, $observacao);
            
            if ($result['status']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        } elseif ($_POST['action'] === 'reject' && isset($_POST['payment_id'])) {
            $paymentId = intval($_POST['payment_id']);
            $motivo = $_POST['motivo'] ?? '';
            $result = TransactionController::rejectPayment($paymentId, $motivo);
            
            if ($result['status']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        } elseif ($_POST['action'] === 'process_balance_payment') {
            // Processar pagamento de saldo às lojas
            $data = $_POST;
            if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] === UPLOAD_ERR_OK) {
                $data['comprovante'] = $_FILES['comprovante'];
            }
            
            $result = StoreBalancePaymentController::processStoreBalancePayment($data);
            
            if ($result['status']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Obter lista de pagamentos
$filters = [];
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;

// Aplicar filtros se fornecidos
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}
if (isset($_GET['data_inicio']) && !empty($_GET['data_inicio'])) {
    $filters['data_inicio'] = $_GET['data_inicio'];
}
if (isset($_GET['data_fim']) && !empty($_GET['data_fim'])) {
    $filters['data_fim'] = $_GET['data_fim'];
}

$db = Database::getConnection();

// --- Construção da Query com informações de saldo ---
$selectPart = "SELECT 
    p.*, 
    l.nome_fantasia, 
    l.email as loja_email,
    COALESCE((SELECT COUNT(*) FROM pagamentos_transacoes pt WHERE pt.pagamento_id = p.id), 0) as total_transacoes,
    COALESCE(SUM(t_vendas.valor_total), 0) as valor_vendas_originais,
    COALESCE(SUM(
        (SELECT SUM(cm.valor) 
         FROM cashback_movimentacoes cm 
         WHERE cm.transacao_uso_id = t_vendas.id AND cm.tipo_operacao = 'uso')
    ), 0) as total_saldo_usado,
    COUNT(CASE WHEN EXISTS(
        SELECT 1 FROM cashback_movimentacoes cm2 
        WHERE cm2.transacao_uso_id = t_vendas.id AND cm2.tipo_operacao = 'uso'
    ) THEN 1 END) as transacoes_com_saldo";

$fromPart = "FROM pagamentos_comissao p 
JOIN lojas l ON p.loja_id = l.id
LEFT JOIN pagamentos_transacoes pt ON p.id = pt.pagamento_id
LEFT JOIN transacoes_cashback t_vendas ON pt.transacao_id = t_vendas.id";

$wherePart = "WHERE 1=1";
$paramsForWhere = [];

// Aplicar filtros à cláusula WHERE
if (!empty($filters['status'])) {
    $wherePart .= " AND p.status = :status";
    $paramsForWhere[':status'] = $filters['status'];
}
if (!empty($filters['data_inicio'])) {
    $wherePart .= " AND p.data_registro >= :data_inicio";
    $paramsForWhere[':data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
}
if (!empty($filters['data_fim'])) {
    $wherePart .= " AND p.data_registro <= :data_fim";
    $paramsForWhere[':data_fim'] = $filters['data_fim'] . ' 23:59:59';
}

$groupPart = "GROUP BY p.id";

// Contagem para paginação
$countQuery = "SELECT COUNT(DISTINCT p.id) as total 
               FROM pagamentos_comissao p 
               JOIN lojas l ON p.loja_id = l.id " . 
               str_replace("WHERE 1=1", "WHERE 1=1", $wherePart);

$countStmt = $db->prepare($countQuery);
foreach ($paramsForWhere as $paramName => $paramValue) {
    $countStmt->bindValue($paramName, $paramValue);
}
$countStmt->execute();
$resultCount = $countStmt->fetch(PDO::FETCH_ASSOC);
$totalCount = $resultCount ? (int)$resultCount['total'] : 0;

// Paginação
$perPage = defined('ITEMS_PER_PAGE') ? ITEMS_PER_PAGE : 10;
$totalPages = ($totalCount > 0) ? ceil($totalCount / $perPage) : 1;
$page = max(1, min($page, $totalPages));
$offset = ($page - 1) * $perPage;

// Query principal para buscar dados
$mainQuery = $selectPart . " " . $fromPart . " " . $wherePart . " " . $groupPart . " ORDER BY p.data_registro DESC LIMIT :offset, :limit";
$stmt = $db->prepare($mainQuery);

// Bind parâmetros para a query principal (filtros + paginação)
$paramsForMainQuery = $paramsForWhere;
$paramsForMainQuery[':offset'] = $offset;
$paramsForMainQuery[':limit'] = $perPage;

foreach ($paramsForMainQuery as $paramName => $paramValue) {
    if ($paramName == ':offset' || $paramName == ':limit') {
        $stmt->bindValue($paramName, (int)$paramValue, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($paramName, $paramValue);
    }
}
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas globais com informações de saldo
$statsQuery = "
    SELECT 
        COUNT(*) as total_pagamentos,
        SUM(p.valor_total) as valor_total_comissoes,
        SUM(CASE WHEN p.status = 'pendente' THEN p.valor_total ELSE 0 END) as valor_pendente,
        SUM(CASE WHEN p.status = 'aprovado' THEN p.valor_total ELSE 0 END) as valor_aprovado,
        COUNT(CASE WHEN p.status = 'pendente' THEN 1 END) as count_pendente,
        COUNT(CASE WHEN p.status = 'aprovado' THEN 1 END) as count_aprovado,
        COUNT(CASE WHEN p.status = 'rejeitado' THEN 1 END) as count_rejeitado,
        COALESCE(SUM(sub.valor_vendas_originais), 0) as total_vendas_originais,
        COALESCE(SUM(sub.total_saldo_usado), 0) as total_saldo_usado_sistema
    FROM pagamentos_comissao p
    LEFT JOIN (
        SELECT 
            pt.pagamento_id,
            SUM(t.valor_total) as valor_vendas_originais,
            SUM(COALESCE(
                (SELECT SUM(cm.valor) 
                 FROM cashback_movimentacoes cm 
                 WHERE cm.transacao_uso_id = t.id AND cm.tipo_operacao = 'uso'), 0
            )) as total_saldo_usado
        FROM pagamentos_transacoes pt
        JOIN transacoes_cashback t ON pt.transacao_id = t.id
        GROUP BY pt.pagamento_id
    ) sub ON p.id = sub.pagamento_id
";

$statsStmt = $db->query($statsQuery);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Carregar dados para a aba de pagamentos de saldo
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'commissions';

// Dados para a aba de pagamentos de saldo
if ($activeTab === 'balance') {
    $balanceFilters = [];
    
    if (isset($_GET['status_pagamento']) && !empty($_GET['status_pagamento'])) {
        $balanceFilters['status_pagamento'] = $_GET['status_pagamento'];
    }
    if (isset($_GET['data_inicio_saldo']) && !empty($_GET['data_inicio_saldo'])) {
        $balanceFilters['data_inicio'] = $_GET['data_inicio_saldo'];
    }
    if (isset($_GET['data_fim_saldo']) && !empty($_GET['data_fim_saldo'])) {
        $balanceFilters['data_fim'] = $_GET['data_fim_saldo'];
    }
    if (isset($_GET['loja_id']) && !empty($_GET['loja_id'])) {
        $balanceFilters['loja_id'] = intval($_GET['loja_id']);
    }
    
    $balancePage = isset($_GET['balance_page']) ? intval($_GET['balance_page']) : 1;
    $storeBalancePayments = StoreBalancePaymentController::getPendingStoreBalancePayments($balanceFilters, $balancePage);
    
    // Buscar lista de todas as lojas para o filtro
    $storesQuery = "SELECT id, nome_fantasia FROM lojas WHERE status = 'aprovado' ORDER BY nome_fantasia ASC";
    $storesStmt = $db->query($storesQuery);
    $stores = $storesStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
    <title>Gerenciar Pagamentos - Klube Cash</title>
    <link rel="stylesheet" href="../../assets/css/views/admin/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/views/admin/payments.css">
    <link rel="stylesheet" href="../../assets/css/layout-fix.css">
    <style>
        
/* Estilos para as abas */
        .tabs-container {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        
        .tab {
            padding: 12px 20px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            color: #555;
        }
        
        .tab:hover {
            background-color: #f9f9f9;
        }
        
        .tab.active {
            border-bottom: 3px solid <?php echo PRIMARY_COLOR; ?>;
            color: <?php echo PRIMARY_COLOR; ?>;
        }
        
        .tab-content {
            margin-top: 20px;
        }
        
        .tab-pane {
            display: block;
        }
        
        .tab-pane.hidden {
            display: none;
        }
        
        /* Estilos para a tabela de saldo */
        .saldo-usado {
            color: #28a745;
            font-weight: 600;
        }
        
        .sem-saldo {
            color: #6c757d;
        }
        
        .economia-badge {
            background-color: #e8f7ed;
            color: #28a745;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
            display: inline-block;
            margin-top: 3px;
        }
        
        .balance-indicator {
            color: #28a745;
            margin-left: 5px;
            font-size: 14px;
        }
        
        /* Estilos para o modal de pagamento */
        .modal-lg {
            width: 90%;
            max-width: 900px;
        }
        
        .payment-summary {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid <?php echo PRIMARY_COLOR; ?>;
        }
        
        .detail-section {
            margin-bottom: 25px;
        }
        
        .detail-actions {
            margin-top: 25px;
            text-align: right;
        }
        
        .form-check {
            display: flex;
            align-items: center;
            margin: 15px 0;
        }
        
        .form-check input {
            margin-right: 10px;
        }
        
        /* Estilos para pagamentos de saldo */
        .saldo-section {
            background-color: #f1f9ff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .saldo-info {
            margin-top: 5px;
            font-size: 12px;
            color: #28a745;
        }
    </style>
</head>
<body>
    <?php include_once '../components/sidebar.php'; ?>
    
    <div class="main-content" id="mainContent">
        <div class="dashboard-wrapper">
            <div class="dashboard-header">
                <h1>Gerenciar Pagamentos</h1>
                <p class="subtitle">Aprovar ou rejeitar pagamentos e gerenciar reembolsos de saldo</p>
            </div>
            
            <?php if (!empty($success)): ?>
                <div class="alert success">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert error">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- Tabs de navegação -->
            <div class="tabs-container">
                <div class="tab <?php echo $activeTab === 'commissions' ? 'active' : ''; ?>" 
                     onclick="location.href='<?php echo ADMIN_PAYMENTS_URL; ?>?tab=commissions'">
                    Pagamentos de Comissões
                </div>
                <div class="tab <?php echo $activeTab === 'balance' ? 'active' : ''; ?>"
                     onclick="location.href='<?php echo ADMIN_PAYMENTS_URL; ?>?tab=balance'">
                    Pagamentos de Saldo às Lojas
                </div>
            </div>
            
            <!-- Conteúdo das abas -->
            <div class="tab-content">
                <!-- Aba de Comissões -->
                <div id="commissions-tab" class="tab-pane <?php echo $activeTab === 'commissions' ? 'active' : 'hidden'; ?>">
                    <!-- Estatísticas de comissões -->
                    <div class="stats-container">
                        <div class="stat-card">
                            <div class="stat-card-title">Total de Pagamentos</div>
                            <div class="stat-card-value"><?php echo number_format($stats['total_pagamentos']); ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-card-title">Pendentes</div>
                            <div class="stat-card-value"><?php echo number_format($stats['count_pendente']); ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-card-title">Valor Pendente</div>
                            <div class="stat-card-value">R$ <?php echo number_format($stats['valor_pendente'], 2, ',', '.'); ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-card-title">Valor Total Aprovado</div>
                            <div class="stat-card-value">R$ <?php echo number_format($stats['valor_aprovado'], 2, ',', '.'); ?></div>
                        </div>
                        <div class="stat-card stat-card-balance">
                            <div class="stat-card-title">Vendas Originais</div>
                            <div class="stat-card-value">R$ <?php echo number_format($stats['total_vendas_originais'], 2, ',', '.'); ?></div>
                            <div class="stat-card-subtitle">Valor total das vendas</div>
                        </div>
                        <div class="stat-card stat-card-balance">
                            <div class="stat-card-title">Economia Clientes</div>
                            <div class="stat-card-value">R$ <?php echo number_format($stats['total_saldo_usado_sistema'], 2, ',', '.'); ?></div>
                            <div class="stat-card-subtitle">Saldo usado em compras</div>
                        </div>
                    </div>
                    
                    <!-- Filtros de comissões -->
                    <div class="filter-container">
                        <form method="GET" action="" class="filter-form">
                            <input type="hidden" name="tab" value="commissions">
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status">
                                    <option value="">Todos</option>
                                    <option value="pendente" <?php echo (isset($filters['status']) && $filters['status'] === 'pendente') ? 'selected' : ''; ?>>Pendente</option>
                                    <option value="aprovado" <?php echo (isset($filters['status']) && $filters['status'] === 'aprovado') ? 'selected' : ''; ?>>Aprovado</option>
                                    <option value="rejeitado" <?php echo (isset($filters['status']) && $filters['status'] === 'rejeitado') ? 'selected' : ''; ?>>Rejeitado</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="data_inicio">Data Início</label>
                                <input type="date" id="data_inicio" name="data_inicio" value="<?php echo isset($filters['data_inicio']) ? htmlspecialchars($filters['data_inicio']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="data_fim">Data Fim</label>
                                <input type="date" id="data_fim" name="data_fim" value="<?php echo isset($filters['data_fim']) ? htmlspecialchars($filters['data_fim']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Filtrar</button>
                                <a href="<?php echo ADMIN_PAYMENTS_URL; ?>?tab=commissions" class="btn btn-secondary">Limpar</a>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Tabela de comissões -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">Lista de Pagamentos de Comissões</div>
                        </div>
                        
                        <?php if (count($payments) > 0): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>#ID</th>
                                            <th>Loja</th>
                                            <th>Valor Original</th>
                                            <th>Saldo Usado</th>
                                            <th>Comissão</th>
                                            <th>Método</th>
                                            <th>Data</th>
                                            <th>Transações</th>
                                            <th>Status</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payments as $payment): ?>
                                            <tr>
                                                <td><?php echo $payment['id']; ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($payment['nome_fantasia']); ?>
                                                    <?php if ($payment['total_saldo_usado'] > 0): ?>
                                                        <span class="balance-indicator" title="Clientes usaram saldo">💰</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div>
                                                        <span class="valor-original">R$ <?php echo number_format($payment['valor_vendas_originais'], 2, ',', '.'); ?></span>
                                                        <small class="valor-detail">Vendas</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($payment['total_saldo_usado'] > 0): ?>
                                                        <span class="saldo-usado">R$ <?php echo number_format($payment['total_saldo_usado'], 2, ',', '.'); ?></span>
                                                        <?php if ($payment['transacoes_com_saldo'] > 0): ?>
                                                            <small class="economia-badge"><?php echo $payment['transacoes_com_saldo']; ?> uso<?php echo $payment['transacoes_com_saldo'] > 1 ? 's' : ''; ?></small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="sem-saldo">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div>
                                                        <span class="valor-liquido">R$ <?php echo number_format($payment['valor_total'], 2, ',', '.'); ?></span>
                                                        <small class="valor-detail">Paga</small>
                                                        <?php if ($payment['total_saldo_usado'] > 0): ?>
                                                            <div class="saldo-info">
                                                                <small>Economia: R$ <?php echo number_format($payment['total_saldo_usado'], 2, ',', '.'); ?></small>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td><?php echo ucfirst($payment['metodo_pagamento']); ?></td>
                                                <td>
                                                    <?php 
                                                    // Aplica a subtração de 3 horas no fuso
                                                    echo date('d/m/Y H:i', strtotime($payment['data_registro']) - (3 * 60 * 60)); 
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php echo $payment['total_transacoes']; ?> vendas
                                                    <?php if ($payment['transacoes_com_saldo'] > 0): ?>
                                                        <br><small class="economia-badge"><?php echo $payment['transacoes_com_saldo']; ?> c/ saldo</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $payment['status']; ?>">
                                                        <?php echo ucfirst($payment['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn-action btn-view" onclick="viewPaymentDetails(<?php echo $payment['id']; ?>)">
                                                        Ver Detalhes
                                                    </button>
                                                    <?php if ($payment['status'] === 'pendente'): ?>
                                                        <button class="btn-action btn-approve" onclick="showApproveModal(<?php echo $payment['id']; ?>)">
                                                            Aprovar
                                                        </button>
                                                        <button class="btn-action btn-reject" onclick="showRejectModal(<?php echo $payment['id']; ?>)">
                                                            Rejeitar
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if (!empty($payment['comprovante'])): ?>
                                                        <button class="btn-action btn-view" onclick="viewReceipt('<?php echo htmlspecialchars($payment['comprovante']); ?>')">
                                                            Comprovante
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php if ($totalPages > 1): ?>
                                <div class="pagination">
                                    <div class="pagination-info">
                                        Página <?php echo $page; ?> de <?php echo $totalPages; ?> (<?php echo $totalCount; ?> itens)
                                    </div>
                                    <div class="pagination-links">
                                        <?php if ($page > 1): ?>
                                            <a href="?tab=commissions&page=1<?php echo !empty($filters['status']) ? '&status=' . urlencode($filters['status']) : ''; ?><?php echo !empty($filters['data_inicio']) ? '&data_inicio=' . urlencode($filters['data_inicio']) : ''; ?><?php echo !empty($filters['data_fim']) ? '&data_fim=' . urlencode($filters['data_fim']) : ''; ?>" class="page-link">
                                                Primeira
                                            </a>
                                            <a href="?tab=commissions&page=<?php echo $page - 1; ?><?php echo !empty($filters['status']) ? '&status=' . urlencode($filters['status']) : ''; ?><?php echo !empty($filters['data_inicio']) ? '&data_inicio=' . urlencode($filters['data_inicio']) : ''; ?><?php echo !empty($filters['data_fim']) ? '&data_fim=' . urlencode($filters['data_fim']) : ''; ?>" class="page-link">
                                                Anterior
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($page < $totalPages): ?>
                                            <a href="?tab=commissions&page=<?php echo $page + 1; ?><?php echo !empty($filters['status']) ? '&status=' . urlencode($filters['status']) : ''; ?><?php echo !empty($filters['data_inicio']) ? '&data_inicio=' . urlencode($filters['data_inicio']) : ''; ?><?php echo !empty($filters['data_fim']) ? '&data_fim=' . urlencode($filters['data_fim']) : ''; ?>" class="page-link">
                                                Próxima
                                            </a>
                                            <a href="?tab=commissions&page=<?php echo $totalPages; ?><?php echo !empty($filters['status']) ? '&status=' . urlencode($filters['status']) : ''; ?><?php echo !empty($filters['data_inicio']) ? '&data_inicio=' . urlencode($filters['data_inicio']) : ''; ?><?php echo !empty($filters['data_fim']) ? '&data_fim=' . urlencode($filters['data_fim']) : ''; ?>" class="page-link">
                                                Última
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                                        <line x1="2" y1="9" x2="22" y2="9"></line>
                                    </svg>
                                </div>
                                <h3>Nenhum pagamento encontrado</h3>
                                <p>Não foram encontrados pagamentos com os filtros aplicados.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Aba de Pagamentos de Saldo às Lojas -->
                <div id="balance-tab" class="tab-pane <?php echo $activeTab === 'balance' ? 'active' : 'hidden'; ?>">
                    <?php if ($activeTab === 'balance'): ?>
                        <?php 
                            // Obter estatísticas de saldo
                            $balanceStats = StoreBalancePaymentController::getBalanceStatistics();
                        ?>
                        
                        <div class="stats-container">
                            <div class="stat-card">
                                <div class="stat-card-title">Lojas com Saldo a Receber</div>
                                <div class="stat-card-value"><?php echo number_format($balanceStats['total_lojas']); ?></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-card-title">Transações Pendentes</div>
                                <div class="stat-card-value"><?php echo number_format($balanceStats['total_transacoes']); ?></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-card-title">Valor Total a Pagar</div>
                                <div class="stat-card-value">R$ <?php echo number_format($balanceStats['valor_total_pendente'], 2, ',', '.'); ?></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-card-title">Valor Já Pago</div>
                                <div class="stat-card-value">R$ <?php echo number_format($balanceStats['valor_total_pago'], 2, ',', '.'); ?></div>
                            </div>
                        </div>
                        
                        <!-- Filtros para saldo -->
                        <div class="filter-container">
                            <form method="GET" action="" class="filter-form">
                                <input type="hidden" name="tab" value="balance">
                                <div class="form-group">
                                    <label for="loja_id">Loja</label>
                                    <select id="loja_id" name="loja_id">
                                        <option value="">Todas as Lojas</option>
                                        <?php foreach ($stores as $store): ?>
                                            <option value="<?php echo $store['id']; ?>" <?php echo (isset($balanceFilters['loja_id']) && $balanceFilters['loja_id'] == $store['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($store['nome_fantasia']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="status_pagamento">Status</label>
                                    <select id="status_pagamento" name="status_pagamento">
                                        <option value="">Todos</option>
                                        <option value="pendente" <?php echo (isset($balanceFilters['status_pagamento']) && $balanceFilters['status_pagamento'] === 'pendente') ? 'selected' : ''; ?>>Pendente</option>
                                        <option value="em_processamento" <?php echo (isset($balanceFilters['status_pagamento']) && $balanceFilters['status_pagamento'] === 'em_processamento') ? 'selected' : ''; ?>>Em Processamento</option>
                                        <option value="aprovado" <?php echo (isset($balanceFilters['status_pagamento']) && $balanceFilters['status_pagamento'] === 'aprovado') ? 'selected' : ''; ?>>Aprovado</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="data_inicio_saldo">Data Início</label>
                                    <input type="date" id="data_inicio_saldo" name="data_inicio_saldo" value="<?php echo isset($balanceFilters['data_inicio']) ? htmlspecialchars($balanceFilters['data_inicio']) : ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="data_fim_saldo">Data Fim</label>
                                    <input type="date" id="data_fim_saldo" name="data_fim_saldo" value="<?php echo isset($balanceFilters['data_fim']) ? htmlspecialchars($balanceFilters['data_fim']) : ''; ?>">
                                </div>
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">Filtrar</button>
                                    <a href="<?php echo ADMIN_PAYMENTS_URL; ?>?tab=balance" class="btn btn-secondary">Limpar</a>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Listagem de Saldo -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">Reembolsos de Saldo Pendentes para Lojas</div>
                            </div>
                            
                            <?php if ($storeBalancePayments['status'] && count($storeBalancePayments['data']['pagamentos']) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Loja</th>
                                                <th>Transações</th>
                                                <th>Valor Total a Pagar</th>
                                                <th>Data Mais Antiga</th>
                                                <th>Data Mais Recente</th>
                                                <th>Status</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($storeBalancePayments['data']['pagamentos'] as $balancePayment): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($balancePayment['loja_nome']); ?></td>
                                                    <td><?php echo $balancePayment['total_transacoes']; ?> transação(ões)</td>
                                                    <td><span class="saldo-usado">R$ <?php echo number_format($balancePayment['valor_total_saldo'], 2, ',', '.'); ?></span></td>
                                                    <td><?php echo date('d/m/Y', strtotime($balancePayment['data_mais_antiga'])); ?></td>
                                                    <td><?php echo date('d/m/Y', strtotime($balancePayment['data_mais_recente'])); ?></td>
                                                    <td>
                                                        <span class="status-badge status-<?php echo $balancePayment['status_pagamento']; ?>">
                                                            <?php 
                                                                $statusText = $balancePayment['status_pagamento'];
                                                                if ($statusText === 'pendente') echo 'Pendente';
                                                                else if ($statusText === 'em_processamento') echo 'Em Processamento';
                                                                else if ($statusText === 'aprovado') echo 'Aprovado';
                                                                else if ($statusText === 'rejeitado') echo 'Rejeitado';
                                                                else echo ucfirst($statusText);
                                                            ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button class="btn-action btn-view" 
                                                                onclick="viewStoreBalanceDetails(<?php echo $balancePayment['loja_id']; ?>)">
                                                            Ver Detalhes
                                                        </button>
                                                        
                                                        <?php if ($balancePayment['status_pagamento'] === 'pendente'): ?>
                                                            <button class="btn-action btn-approve" 
                                                                    onclick="showProcessBalancePaymentModal(
                                                                        <?php echo $balancePayment['loja_id']; ?>, 
                                                                        <?php echo $balancePayment['valor_total_saldo']; ?>, 
                                                                        '<?php echo addslashes($balancePayment['loja_nome']); ?>'
                                                                    )">
                                                                Processar Pagamento
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($balancePayment['pagamento_id'] > 0): ?>
                                                            <button class="btn-action btn-view" 
                                                                    onclick="viewBalancePaymentDetails(<?php echo $balancePayment['pagamento_id']; ?>)">
                                                                Ver Pagamento
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <?php if ($storeBalancePayments['data']['paginacao']['total_paginas'] > 1): ?>
                                    <div class="pagination">
                                        <div class="pagination-info">
                                            Página <?php echo $storeBalancePayments['data']['paginacao']['pagina_atual']; ?> 
                                            de <?php echo $storeBalancePayments['data']['paginacao']['total_paginas']; ?> 
                                            (<?php echo $storeBalancePayments['data']['paginacao']['total_itens']; ?> itens)
                                        </div>
                                        <div class="pagination-links">
                                            <?php if ($storeBalancePayments['data']['paginacao']['pagina_atual'] > 1): ?>
                                                <a href="?tab=balance&balance_page=1<?php echo isset($balanceFilters['status_pagamento']) ? '&status_pagamento=' . $balanceFilters['status_pagamento'] : ''; ?><?php echo isset($balanceFilters['data_inicio']) ? '&data_inicio_saldo=' . $balanceFilters['data_inicio'] : ''; ?><?php echo isset($balanceFilters['data_fim']) ? '&data_fim_saldo=' . $balanceFilters['data_fim'] : ''; ?><?php echo isset($balanceFilters['loja_id']) ? '&loja_id=' . $balanceFilters['loja_id'] : ''; ?>" class="page-link">
                                                    Primeira
                                                </a>
                                                <a href="?tab=balance&balance_page=<?php echo $storeBalancePayments['data']['paginacao']['pagina_atual'] - 1; ?><?php echo isset($balanceFilters['status_pagamento']) ? '&status_pagamento=' . $balanceFilters['status_pagamento'] : ''; ?><?php echo isset($balanceFilters['data_inicio']) ? '&data_inicio_saldo=' . $balanceFilters['data_inicio'] : ''; ?><?php echo isset($balanceFilters['data_fim']) ? '&data_fim_saldo=' . $balanceFilters['data_fim'] : ''; ?><?php echo isset($balanceFilters['loja_id']) ? '&loja_id=' . $balanceFilters['loja_id'] : ''; ?>" class="page-link">
                                                    Anterior
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($storeBalancePayments['data']['paginacao']['pagina_atual'] < $storeBalancePayments['data']['paginacao']['total_paginas']): ?>
                                                <a href="?tab=balance&balance_page=<?php echo $storeBalancePayments['data']['paginacao']['pagina_atual'] + 1; ?><?php echo isset($balanceFilters['status_pagamento']) ? '&status_pagamento=' . $balanceFilters['status_pagamento'] : ''; ?><?php echo isset($balanceFilters['data_inicio']) ? '&data_inicio_saldo=' . $balanceFilters['data_inicio'] : ''; ?><?php echo isset($balanceFilters['data_fim']) ? '&data_fim_saldo=' . $balanceFilters['data_fim'] : ''; ?><?php echo isset($balanceFilters['loja_id']) ? '&loja_id=' . $balanceFilters['loja_id'] : ''; ?>" class="page-link">
                                                    Próxima
                                                </a>
                                                <a href="?tab=balance&balance_page=<?php echo $storeBalancePayments['data']['paginacao']['total_paginas']; ?><?php echo isset($balanceFilters['status_pagamento']) ? '&status_pagamento=' . $balanceFilters['status_pagamento'] : ''; ?><?php echo isset($balanceFilters['data_inicio']) ? '&data_inicio_saldo=' . $balanceFilters['data_inicio'] : ''; ?><?php echo isset($balanceFilters['data_fim']) ? '&data_fim_saldo=' . $balanceFilters['data_fim'] : ''; ?><?php echo isset($balanceFilters['loja_id']) ? '&loja_id=' . $balanceFilters['loja_id'] : ''; ?>" class="page-link">
                                                    Última
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                            <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                                            <line x1="2" y1="9" x2="22" y2="9"></line>
                                        </svg>
                                    </div>
                                    <h3>Nenhum pagamento de saldo pendente</h3>
                                    <p>Não foram encontrados saldos pendentes para pagamento às lojas.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modais para Pagamentos de Comissões -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Detalhes do Pagamento</h2>
                <span class="close" onclick="closeModal('detailsModal')">&times;</span>
            </div>
            <div id="detailsContent" class="modal-body">
                <p>Carregando...</p>
            </div>
        </div>
    </div>
    
    <div id="approveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Aprovar Pagamento</h2>
                <span class="close" onclick="closeModal('approveModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="payment_id" id="approve_payment_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="observacao">Observação (opcional)</label>
                        <textarea id="observacao" name="observacao" rows="3" placeholder="Adicione uma observação se necessário..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('approveModal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Confirmar Aprovação</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Rejeitar Pagamento</h2>
                <span class="close" onclick="closeModal('rejectModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="payment_id" id="reject_payment_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="motivo">Motivo da rejeição *</label>
                        <textarea id="motivo" name="motivo" rows="3" placeholder="Informe o motivo da rejeição..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Confirmar Rejeição</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="receiptModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Comprovante de Pagamento</h2>
                <span class="close" onclick="closeModal('receiptModal')">&times;</span>
            </div>
            <div id="receiptContent" class="modal-body">
                <img id="receiptImage" src="" alt="Comprovante" style="max-width: 100%; height: auto;">
            </div>
        </div>
    </div>
    
    <!-- Modais para Pagamentos de Saldo -->
    <div id="storeBalanceDetailsModal" class="modal">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h2>Detalhes do Uso de Saldo</h2>
                <span class="close" onclick="closeModal('storeBalanceDetailsModal')">&times;</span>
            </div>
            <div id="storeBalanceDetailsContent" class="modal-body">
                <p>Carregando...</p>
            </div>
        </div>
    </div>
    
    <div id="processBalancePaymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Processar Pagamento de Saldo</h2>
                <span class="close" onclick="closeModal('processBalancePaymentModal')">&times;</span>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="process_balance_payment">
                <input type="hidden" name="loja_id" id="balance_loja_id">
                <input type="hidden" name="movimentacoes" id="balance_movimentacoes">
                <input type="hidden" name="valor_total" id="balance_valor_total">
                
                <div class="modal-body">
                    <div class="payment-summary">
                        <h3>Resumo do Pagamento</h3>
                        <p><strong>Loja:</strong> <span id="balance_loja_nome"></span></p>
                        <p><strong>Valor Total:</strong> R$ <span id="balance_valor_formatado"></span></p>
                    </div>
                    
                    <div class="form-group">
                        <label for="metodo_pagamento">Método de Pagamento</label>
                        <select id="metodo_pagamento" name="metodo_pagamento" required>
                            <option value="pix">PIX</option>
                            <option value="transferencia">Transferência Bancária</option>
                            <option value="deposito">Depósito</option>
                            <option value="outro">Outro</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="numero_referencia">Número de Referência</label>
                        <input type="text" id="numero_referencia" name="numero_referencia" placeholder="ID da transação, número do comprovante, etc.">
                    </div>
                    
                    <div class="form-group">
                        <label for="comprovante">Comprovante (opcional)</label>
                        <input type="file" id="comprovante" name="comprovante" accept=".jpg,.jpeg,.png,.pdf">
                    </div>
                    
                    <div class="form-group">
                        <label for="observacao_balance">Observação</label>
                        <textarea id="observacao_balance" name="observacao" rows="3" placeholder="Observações sobre o pagamento..."></textarea>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" id="aprovar_automaticamente" name="aprovar_automaticamente" value="1" checked>
                        <label for="aprovar_automaticamente">Aprovar automaticamente</label>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('processBalancePaymentModal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Processar Pagamento</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="balancePaymentDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Detalhes do Pagamento de Saldo</h2>
                <span class="close" onclick="closeModal('balancePaymentDetailsModal')">&times;</span>
            </div>
            <div id="balancePaymentDetailsContent" class="modal-body">
                <p>Carregando...</p>
            </div>
        </div>
    </div>
    
    <script>
        // Funções para pagamentos de comissões
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function showApproveModal(paymentId) {
            document.getElementById('approve_payment_id').value = paymentId;
            document.getElementById('approveModal').style.display = 'block';
        }
        
        function showRejectModal(paymentId) {
            document.getElementById('reject_payment_id').value = paymentId;
            document.getElementById('rejectModal').style.display = 'block';
        }
        
        function viewPaymentDetails(paymentId) {
            const modal = document.getElementById('detailsModal');
            const content = document.getElementById('detailsContent');
            modal.style.display = 'block';
            content.innerHTML = '<p>Carregando detalhes...</p>';
            
            fetch('../../controllers/TransactionController.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=payment_details_with_balance&payment_id=' + paymentId
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    renderPaymentDetailsWithBalance(data.data, content);
                } else {
                    content.innerHTML = '<p class="error">Erro ao carregar detalhes: ' + (data.message || 'Erro desconhecido.') + '</p>';
                }
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
                content.innerHTML = '<p class="error">Erro de conexão. Tente novamente.</p>';
            });
        }
        
        function renderPaymentDetailsWithBalance(data, contentElement) {
            const payment = data.pagamento;
            const transactions = data.transacoes;
            
            let html = `
                <div style="margin-bottom: 20px;">
                    <h3>Informações do Pagamento</h3>
                    <p><strong>ID:</strong> ${payment.id}</p>
                    <p><strong>Loja:</strong> ${payment.loja_nome || 'N/A'}</p>
                    <p><strong>Valor Original das Vendas:</strong> R$ ${parseFloat(payment.valor_vendas_originais || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</p>
                    <p><strong>Total Saldo Usado:</strong> <span style="color: #28a745; font-weight: 600;">R$ ${parseFloat(payment.total_saldo_usado || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span></p>
                    <p><strong>Comissão Paga:</strong> R$ ${parseFloat(payment.valor_total).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</p>
                    <p><strong>Método:</strong> ${payment.metodo_pagamento || 'N/A'}</p>
                    <p><strong>Data:</strong> ${payment.data_registro ? new Date(payment.data_registro).toLocaleString('pt-BR') : 'N/A'}</p>
                    ${payment.numero_referencia ? `<p><strong>Referência:</strong> ${payment.numero_referencia}</p>` : ''}
                    ${payment.observacao ? `<p><strong>Observação (Loja):</strong> ${payment.observacao}</p>` : ''}
                    ${payment.observacao_admin ? `<p><strong>Observação (Admin):</strong> ${payment.observacao_admin}</p>` : ''}
                </div>
                
                <div>
                    <h3>Transações Incluídas (${transactions.length})</h3>`;
            
            if (transactions.length > 0) {
                html += `<div style="max-height: 300px; overflow-y: auto;">
                            <table class="table" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Cliente</th>
                                        <th>Data Trans.</th>
                                        <th>Valor Original</th>
                                        <th>Saldo Usado</th>
                                        <th>Valor Pago</th>
                                        <th>Cashback Cliente</th>
                                    </tr>
                                </thead>
                                <tbody>`;
                transactions.forEach(transaction => {
                    const saldoUsado = parseFloat(transaction.saldo_usado || 0);
                    const valorPago = parseFloat(transaction.valor_total) - saldoUsado;
                    
                    html += `
                        <tr>
                            <td>${transaction.cliente_nome || 'N/A'} ${saldoUsado > 0 ? '💰' : ''}</td>
                            <td>${transaction.data_transacao ? new Date(transaction.data_transacao).toLocaleDateString('pt-BR') : 'N/A'}</td>
                            <td>R$ ${parseFloat(transaction.valor_total).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                            <td>${saldoUsado > 0 ? 'R$ ' + saldoUsado.toLocaleString('pt-BR', {minimumFractionDigits: 2}) : '-'}</td>
                            <td>R$ ${valorPago.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                            <td>R$ ${parseFloat(transaction.valor_cliente).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                        </tr>
                    `;
                });
                html += `       </tbody>
                            </table>
                        </div>`;
            } else {
                html += '<p>Nenhuma transação associada a este pagamento.</p>';
            }
            
            // Resumo do impacto do saldo
            if (payment.total_saldo_usado > 0) {
                html += `
                    <div style="background: #f8fff8; padding: 15px; border-radius: 8px; margin-top: 15px; border-left: 4px solid #28a745;">
                        <h4 style="margin-top: 0; color: #28a745;">💰 Impacto do Saldo</h4>
                        <p><strong>Economia gerada aos clientes:</strong> R$ ${parseFloat(payment.total_saldo_usado).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</p>
                        <p><strong>Redução na comissão:</strong> R$ ${(parseFloat(payment.total_saldo_usado) * 0.1).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</p>
                        <p><small>A comissão foi calculada sobre o valor efetivamente pago pelos clientes (original - saldo usado)</small></p>
                    </div>
                `;
            }
            
            html += `</div>`;
            
            contentElement.innerHTML = html;
        }
        
        function viewReceipt(filename) {
            if (!filename) return;
            document.getElementById('receiptImage').src = '../../uploads/comprovantes/' + filename;
            document.getElementById('receiptModal').style.display = 'block';
        }
        
        // Funções para pagamentos de saldo às lojas
        function viewStoreBalanceDetails(lojaId) {
            const modal = document.getElementById('storeBalanceDetailsModal');
            const content = document.getElementById('storeBalanceDetailsContent');
            modal.style.display = 'block';
            content.innerHTML = '<p>Carregando detalhes...</p>';
            
            fetch('../../controllers/StoreBalancePaymentController.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_store_balance_details&loja_id=' + lojaId
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    renderStoreBalanceDetails(data.data, content, lojaId);
                } else {
                    content.innerHTML = '<p class="error">Erro ao carregar detalhes: ' + (data.message || 'Erro desconhecido.') + '</p>';
                }
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
                content.innerHTML = '<p class="error">Erro de conexão. Tente novamente.</p>';
            });
        }
        
        function renderStoreBalanceDetails(data, contentElement, lojaId) {
            const loja = data.loja;
            const transactions = data.transacoes;
            const totals = data.totais;
            
            let pendingTransactions = transactions.filter(t => t.status_pagamento === 'pendente');
            let movimentacaoIds = pendingTransactions.map(t => t.movimentacao_id);
            
            let html = `
                <div class="detail-section">
                    <h3>Informações da Loja</h3>
                    <p><strong>Nome:</strong> ${loja.nome_fantasia}</p>
                    <p><strong>Email:</strong> ${loja.email}</p>
                    <p><strong>Total de Transações:</strong> ${totals.total_transacoes}</p>
                    <p><strong>Valor Total de Saldo Usado:</strong> R$ ${parseFloat(totals.valor_total_saldo).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</p>
                </div>
                
                <div class="detail-section">
                    <h3>Transações com Uso de Saldo</h3>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Código Transação</th>
                                    <th>Valor da Venda</th>
                                    <th>Saldo Usado</th>
                                    <th>Data</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            if (transactions.length > 0) {
                transactions.forEach(transaction => {
                    html += `
                        <tr>
                            <td>${transaction.cliente_nome}</td>
                            <td>${transaction.codigo_transacao || 'N/A'}</td>
                            <td>R$ ${parseFloat(transaction.valor_venda).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                            <td>R$ ${parseFloat(transaction.valor_saldo_usado).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                            <td>${new Date(transaction.data_operacao).toLocaleDateString('pt-BR')}</td>
                            <td>
                                <span class="status-badge status-${transaction.status_pagamento}">
                                    ${transaction.status_pagamento === 'pendente' ? 'Pendente' : 
                                      transaction.status_pagamento === 'em_processamento' ? 'Em Processamento' : 
                                      transaction.status_pagamento === 'aprovado' ? 'Pago' : 'Rejeitado'}
                                </span>
                            </td>
                        </tr>
                    `;
                });
            } else {
                html += `
                    <tr>
                        <td colspan="6" class="text-center">Nenhuma transação encontrada</td>
                    </tr>
                `;
            }
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            if (pendingTransactions.length > 0) {
                html += `
                    <div class="detail-actions">
                        <button class="btn btn-primary" onclick="showProcessBalancePaymentModal(
                            ${lojaId}, 
                            ${pendingTransactions.reduce((sum, t) => sum + parseFloat(t.valor_saldo_usado), 0)}, 
                            '${loja.nome_fantasia}', 
                            '${movimentacaoIds.join(',')}'
                        )">
                            Processar Pagamento para ${pendingTransactions.length} transação(ões) pendente(s)
                        </button>
                    </div>
                `;
            }
            
            contentElement.innerHTML = html;
        }
        
        function showProcessBalancePaymentModal(lojaId, valorTotal, lojaNome, movimentacoes = null) {
            const modal = document.getElementById('processBalancePaymentModal');
            
            // Preencher campos do modal
            document.getElementById('balance_loja_id').value = lojaId;
            document.getElementById('balance_valor_total').value = valorTotal;
            document.getElementById('balance_loja_nome').textContent = lojaNome;
            document.getElementById('balance_valor_formatado').textContent = valorTotal.toLocaleString('pt-BR', {minimumFractionDigits: 2});
            
            // Se movimentações não foram fornecidas, buscar do backend
            if (!movimentacoes) {
                fetch('../../controllers/StoreBalancePaymentController.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_pending_movimentacoes&loja_id=' + lojaId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status && data.data.movimentacoes.length > 0) {
                        document.getElementById('balance_movimentacoes').value = data.data.movimentacoes.join(',');
                        modal.style.display = 'block';
                    } else {
                        alert('Não foram encontradas movimentações pendentes para esta loja.');
                    }
                })
                .catch(error => {
                    console.error('Erro na requisição:', error);
                    alert('Erro de conexão. Tente novamente.');
                });
            } else {
                document.getElementById('balance_movimentacoes').value = movimentacoes;
                modal.style.display = 'block';
            }
        }
        
        function viewBalancePaymentDetails(paymentId) {
            const modal = document.getElementById('balancePaymentDetailsModal');
            const content = document.getElementById('balancePaymentDetailsContent');
            modal.style.display = 'block';
            content.innerHTML = '<p>Carregando detalhes...</p>';
            
            fetch('../../controllers/StoreBalancePaymentController.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_balance_payment_details&payment_id=' + paymentId
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    renderBalancePaymentDetails(data.data, content);
                } else {
                    content.innerHTML = '<p class="error">Erro ao carregar detalhes: ' + (data.message || 'Erro desconhecido.') + '</p>';
                }
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
                content.innerHTML = '<p class="error">Erro de conexão. Tente novamente.</p>';
            });
        }
        
        function renderBalancePaymentDetails(data, contentElement) {
            const payment = data.pagamento;
            const transactions = data.transacoes || [];
            
            let html = `
                <div style="margin-bottom: 20px;">
                    <h3>Informações do Pagamento</h3>
                    <p><strong>ID:</strong> ${payment.id}</p>
                    <p><strong>Loja:</strong> ${payment.loja_nome || 'N/A'}</p>
                    <p><strong>Valor Total:</strong> R$ ${parseFloat(payment.valor_total).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</p>
                    <p><strong>Método:</strong> ${payment.metodo_pagamento || 'N/A'}</p>
                    <p><strong>Status:</strong> ${payment.status === 'aprovado' ? 'Aprovado' : 
                                                payment.status === 'em_processamento' ? 'Em Processamento' : 
                                                payment.status === 'pendente' ? 'Pendente' : 'Rejeitado'}</p>
                    <p><strong>Data:</strong> ${payment.data_criacao ? new Date(payment.data_criacao).toLocaleString('pt-BR') : 'N/A'}</p>
                    ${payment.numero_referencia ? `<p><strong>Referência:</strong> ${payment.numero_referencia}</p>` : ''}
                    ${payment.observacao ? `<p><strong>Observação:</strong> ${payment.observacao}</p>` : ''}
                </div>
            `;
            
            if (transactions.length > 0) {
                html += `
                    <div>
                        <h3>Transações Incluídas</h3>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <table class="table" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Cliente</th>
                                        <th>Código Transação</th>
                                        <th>Valor da Venda</th>
                                        <th>Saldo Usado</th>
                                        <th>Data</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;
                
                transactions.forEach(transaction => {
                    html += `
                        <tr>
                            <td>${transaction.cliente_nome || 'N/A'}</td>
                            <td>${transaction.codigo_transacao || 'N/A'}</td>
                            <td>R$ ${parseFloat(transaction.valor_venda).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                            <td>R$ ${parseFloat(transaction.valor_saldo_usado).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                            <td>${transaction.data_operacao ? new Date(transaction.data_operacao).toLocaleDateString('pt-BR') : 'N/A'}</td>
                        </tr>
                    `;
                });
                
                html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
            }
            
            if (payment.comprovante) {
                html += `
                    <div style="margin-top: 20px;">
                        <h3>Comprovante de Pagamento</h3>
                        <img src="../../uploads/comprovantes_saldo/${payment.comprovante}" alt="Comprovante" style="max-width: 100%; border: 1px solid #ddd; border-radius: 4px; padding: 4px;">
                    </div>
                `;
            }
            
            contentElement.innerHTML = html;
        }
    </script>
</body>
</html>