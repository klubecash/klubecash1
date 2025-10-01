<?php
// views/client/statement.php
// Definir o menu ativo para a navbar
$activeMenu = 'extrato';

// Incluir arquivos necessários
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../controllers/AuthController.php';
require_once '../../controllers/ClientController.php';

// Iniciar sessão
session_start();

// Verificar se o usuário está logado e é cliente
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== USER_TYPE_CLIENT) {
    header("Location: " . LOGIN_URL . "?error=acesso_restrito");
    exit;
}

// Obter dados do usuário
$userId = $_SESSION['user_id'];

// Definir valores padrão para filtros e paginação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$filters = [];

// Processar filtros se submetidos
if (isset($_GET['filtrar'])) {
    if (!empty($_GET['data_inicio'])) {
        $filters['data_inicio'] = $_GET['data_inicio'];
    }
    
    if (!empty($_GET['data_fim'])) {
        $filters['data_fim'] = $_GET['data_fim'];
    }
    
    if (!empty($_GET['loja_id']) && $_GET['loja_id'] != 'todas') {
        $filters['loja_id'] = $_GET['loja_id'];
    }
    
    if (!empty($_GET['status']) && $_GET['status'] != 'todos') {
        $filters['status'] = $_GET['status'];
    }
    
    if (!empty($_GET['tipo_transacao']) && $_GET['tipo_transacao'] != 'todos') {
        $filters['tipo_transacao'] = $_GET['tipo_transacao'];
    }
}

// Obter dados do extrato
$result = ClientController::getStatement($userId, $filters, $page);

// Verificar se houve erro
$hasError = !$result['status'];
$errorMessage = $hasError ? $result['message'] : '';

// Dados para exibição
$statementData = $hasError ? [] : $result['data'];

try {
    $db = Database::getConnection();
    
    // Obter estatísticas de saldo para o período filtrado
    $saldoStatQuery = "
        SELECT 
            SUM(CASE WHEN cm.tipo_operacao = 'credito' THEN cm.valor ELSE 0 END) as total_creditado,
            SUM(CASE WHEN cm.tipo_operacao = 'uso' THEN cm.valor ELSE 0 END) as total_usado,
            SUM(CASE WHEN cm.tipo_operacao = 'estorno' THEN cm.valor ELSE 0 END) as total_estornado,
            COUNT(CASE WHEN cm.tipo_operacao = 'uso' THEN 1 END) as qtd_usos
        FROM cashback_movimentacoes cm
        WHERE cm.usuario_id = :user_id
    ";

    // Aplicar filtros de data se existirem
    $saldoParams = [':user_id' => $userId];
    if (!empty($filters['data_inicio'])) {
        $saldoStatQuery .= " AND cm.data_operacao >= :data_inicio";
        $saldoParams[':data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
    }
    if (!empty($filters['data_fim'])) {
        $saldoStatQuery .= " AND cm.data_operacao <= :data_fim";
        $saldoParams[':data_fim'] = $filters['data_fim'] . ' 23:59:59';
    }

    $saldoStatStmt = $db->prepare($saldoStatQuery);
    foreach ($saldoParams as $param => $value) {
        $saldoStatStmt->bindValue($param, $value);
    }
    $saldoStatStmt->execute();
    $saldoEstatisticas = $saldoStatStmt->fetch(PDO::FETCH_ASSOC);

    // Obter transações com informações de saldo usado
    if (!$hasError && !empty($statementData['transacoes'])) {
        foreach ($statementData['transacoes'] as &$transacao) {
            // Buscar saldo usado nesta transação
            $saldoUsadoQuery = "
                SELECT SUM(valor) as saldo_usado 
                FROM cashback_movimentacoes 
                WHERE transacao_uso_id = :transacao_id AND tipo_operacao = 'uso'
            ";
            $saldoUsadoStmt = $db->prepare($saldoUsadoQuery);
            $saldoUsadoStmt->bindParam(':transacao_id', $transacao['id']);
            $saldoUsadoStmt->execute();
            $saldoUsado = $saldoUsadoStmt->fetch(PDO::FETCH_ASSOC);
            
            $transacao['saldo_usado'] = $saldoUsado['saldo_usado'] ?? 0;
            $transacao['valor_pago'] = $transacao['valor_total'] - $transacao['saldo_usado'];
        }
    }
} catch (Exception $e) {
    error_log('Erro ao carregar estatísticas de saldo: ' . $e->getMessage());
    $saldoEstatisticas = [
        'total_creditado' => 0,
        'total_usado' => 0,
        'total_estornado' => 0,
        'qtd_usos' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Histórico de Cashback - Klube Cash</title>
    <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
    <link rel="stylesheet" href="../../assets/css/views/client/statement.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Incluir navbar -->
    <?php include_once '../components/navbar.php'; ?>
    
    <div class="statement-container">
        <!-- Header renovado -->
        <div class="statement-header">
            <div class="header-content">
                <div class="header-text">
                    <h1>
                        <span class="money-icon">💰</span>
                        Meu Histórico de Cashback
                    </h1>
                    <p class="header-subtitle">Acompanhe todo o dinheiro que você ganhou de volta nas suas compras</p>
                </div>
                <div class="header-actions">
                    <button class="action-btn secondary" onclick="toggleFilters()">
                        <span class="btn-icon">🔍</span>
                        Buscar
                    </button>
                    <button class="action-btn primary" onclick="exportarExtrato()">
                        <span class="btn-icon">📄</span>
                        Baixar Relatório
                    </button>
                </div>
            </div>
            
            <!-- Guia explicativo -->
            <div class="info-guide">
                <div class="guide-item">
                    <span class="guide-icon">🛍️</span>
                    <span class="guide-text">Compras que você fez</span>
                </div>
                <div class="guide-item">
                    <span class="guide-icon">💸</span>
                    <span class="guide-text">Dinheiro que usou do saldo</span>
                </div>
                <div class="guide-item">
                    <span class="guide-icon">🎁</span>
                    <span class="guide-text">Cashback que ganhou</span>
                </div>
            </div>
        </div>

        <?php if ($hasError): ?>
            <div class="error-message">
                <span class="error-icon">⚠️</span>
                <div class="error-content">
                    <h3>Ops! Algo deu errado</h3>
                    <p><?php echo htmlspecialchars($errorMessage); ?></p>
                </div>
            </div>
        <?php else: ?>

        <!-- Painel de filtros colapsável -->
        <div class="filters-panel" id="filtersPanel" style="display: none;">
            <div class="filters-header">
                <h3>🔍 Buscar no seu histórico</h3>
                <button class="close-filters" onclick="toggleFilters()">✕</button>
            </div>
            <form action="" method="GET" class="filters-form">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label>📅 De quando?</label>
                        <input type="date" name="data_inicio" value="<?php echo $filters['data_inicio'] ?? ''; ?>" class="filter-input">
                    </div>
                    
                    <div class="filter-group">
                        <label>📅 Até quando?</label>
                        <input type="date" name="data_fim" value="<?php echo $filters['data_fim'] ?? ''; ?>" class="filter-input">
                    </div>
                    
                    <div class="filter-group">
                        <label>🏪 Em qual loja?</label>
                        <select name="loja_id" class="filter-input">
                            <option value="todas">Todas as lojas</option>
                            <!-- Opções dinâmicas serão inseridas aqui -->
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>📊 Qual situação?</label>
                        <select name="status" class="filter-input">
                            <option value="todos">Todas</option>
                            <option value="aprovado" <?php echo (isset($filters['status']) && $filters['status'] == 'aprovado') ? 'selected' : ''; ?>>✅ Confirmado</option>
                            <option value="pendente" <?php echo (isset($filters['status']) && $filters['status'] == 'pendente') ? 'selected' : ''; ?>>⏳ Aguardando</option>
                            <option value="cancelado" <?php echo (isset($filters['status']) && $filters['status'] == 'cancelado') ? 'selected' : ''; ?>>❌ Cancelado</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" name="filtrar" value="1" class="action-btn primary">
                        <span class="btn-icon">🔍</span>
                        Buscar
                    </button>
                    <button type="button" onclick="limparFiltros()" class="action-btn secondary">
                        <span class="btn-icon">🗑️</span>
                        Limpar
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Resumo visual em cards grandes -->
        <div class="summary-dashboard">
                        
            <div class="summary-card total-cashback">
                <div class="card-header">
                    <span class="card-icon">🎁</span>
                    <h3>Cashback Ganho</h3>
                </div>
                <div class="card-value">R$ <?php echo number_format(($statementData['estatisticas']['total_cashback'] ?? 0) / 2, 2, ',', '.'); ?></div>
                <div class="card-description">Dinheiro que você ganhou de volta</div>
            </div>
            
            <div class="summary-card balance-used">
                <div class="card-header">
                    <span class="card-icon">💸</span>
                    <h3>Saldo Usado</h3>
                </div>
                <div class="card-value">R$ <?php echo number_format($saldoEstatisticas['total_usado'] ?? 0, 2, ',', '.'); ?></div>
                <div class="card-description">Cashback que você já usou</div>
            </div>
            
            
        </div>
        
        <!-- Lista de transações reformulada -->
        <div class="transactions-section">
            <div class="section-header">
                <h2>📋 Suas Compras e Cashback</h2>
                <p class="section-subtitle">Cada linha mostra uma compra que você fez e o cashback que ganhou</p>
            </div>
            
            <?php if (empty($statementData['transacoes'])): ?>
                <div class="empty-state">
                    <div class="empty-icon">🛍️</div>
                    <h3>Nenhuma compra encontrada</h3>
                    <p>Não encontramos compras no período selecionado. Que tal fazer uma compra em uma de nossas lojas parceiras?</p>
                    <a href="<?php echo CLIENT_STORES_URL; ?>" class="action-btn primary">
                        <span class="btn-icon">🏪</span>
                        Ver Lojas Parceiras
                    </a>
                </div>
            <?php else: ?>
                <div class="transactions-list">
                    <?php foreach ($statementData['transacoes'] as $transacao): ?>
                        <div class="transaction-card" onclick="verDetalhes(<?php echo $transacao['id']; ?>)">
                            <div class="transaction-main">
                                <div class="transaction-info">
                                    <div class="transaction-store">
                                        <span class="store-icon">🏪</span>
                                        <span class="store-name"><?php echo htmlspecialchars($transacao['loja_nome']); ?></span>
                                    </div>
                                    <div class="transaction-date">
                                        📅 <?php echo date('d/m/Y', strtotime($transacao['data_transacao'])); ?>
                                        <span class="transaction-time">às <?php echo date('H:i', strtotime($transacao['data_transacao'])); ?></span>
                                    </div>
                                </div>
                                
                                <div class="transaction-status">
                                    <?php
                                    $statusConfig = [
                                        'aprovado' => ['icon' => '✅', 'text' => 'Confirmado', 'class' => 'approved'],
                                        'pendente' => ['icon' => '⏳', 'text' => 'Aguardando', 'class' => 'pending'],
                                        'cancelado' => ['icon' => '❌', 'text' => 'Cancelado', 'class' => 'cancelled']
                                    ];
                                    $status = $statusConfig[$transacao['status']] ?? $statusConfig['pendente'];
                                    ?>
                                    <span class="status-badge <?php echo $status['class']; ?>">
                                        <?php echo $status['icon']; ?> <?php echo $status['text']; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="transaction-values">
                                <div class="value-row">
                                    <div class="value-item primary">
                                        <span class="value-label">💰 Valor da compra</span>
                                        <span class="value-amount">R$ <?php echo number_format($transacao['valor_total'], 2, ',', '.'); ?></span>
                                    </div>
                                    
                                    <?php if ($transacao['saldo_usado'] > 0): ?>
                                        <div class="value-item discount">
                                            <span class="value-label">💸 Saldo usado</span>
                                            <span class="value-amount">- R$ <?php echo number_format($transacao['saldo_usado'], 2, ',', '.'); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="value-item paid">
                                        <span class="value-label">💳 Você pagou</span>
                                        <span class="value-amount">R$ <?php echo number_format($transacao['valor_pago'], 2, ',', '.'); ?></span>
                                    </div>
                                    
                                    <div class="value-item cashback">
                                        <span class="value-label">🎁 Cashback ganho</span>
                                        <span class="value-amount">R$ <?php echo number_format($transacao['valor_cliente'], 2, ',', '.'); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="transaction-actions">
                                <button class="detail-btn">
                                    <span>Ver detalhes</span>
                                    <span class="arrow">→</span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Paginação melhorada -->
            <?php if (!empty($statementData['paginacao']) && $statementData['paginacao']['total_paginas'] > 1): ?>
                <div class="pagination-section">
                    <div class="pagination-info">
                        Mostrando página <?php echo $statementData['paginacao']['pagina_atual']; ?> de <?php echo $statementData['paginacao']['total_paginas']; ?>
                        (<?php echo $statementData['paginacao']['total']; ?> compras no total)
                    </div>
                    
                    <div class="pagination-controls">
                        <?php 
                        $currentPage = $statementData['paginacao']['pagina_atual'];
                        $totalPages = $statementData['paginacao']['total_paginas'];
                        
                        // Construir parâmetros da URL
                        $urlParams = [];
                        foreach ($filters as $key => $value) {
                            $urlParams[] = "$key=" . urlencode($value);
                        }
                        $urlParams[] = "filtrar=1";
                        $queryString = !empty($urlParams) ? '&' . implode('&', $urlParams) : '';
                        
                        // Anterior
                        if ($currentPage > 1): 
                        ?>
                            <a href="?page=<?php echo $currentPage - 1 . $queryString; ?>" class="pagination-btn prev">
                                ← Anterior
                            </a>
                        <?php endif; ?>
                        
                        <div class="pagination-numbers">
                            <?php 
                            $start = max(1, $currentPage - 2);
                            $end = min($totalPages, $start + 4);
                            
                            for ($i = $start; $i <= $end; $i++): 
                            ?>
                                <a href="?page=<?php echo $i . $queryString; ?>" class="pagination-number <?php echo ($i == $currentPage) ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                        
                        <?php 
                        // Próximo
                        if ($currentPage < $totalPages): 
                        ?>
                            <a href="?page=<?php echo $currentPage + 1 . $queryString; ?>" class="pagination-btn next">
                                Próxima →
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Informações educativas sobre saldo -->
        <?php if (!empty($saldoEstatisticas['qtd_usos']) && $saldoEstatisticas['qtd_usos'] > 0): ?>
        <div class="education-section">
            <div class="education-header">
                <h3>💡 Entenda como funciona o seu saldo</h3>
            </div>
            
            <div class="education-cards">
                <div class="education-card">
                    <div class="education-icon">🎁</div>
                    <h4>Você recebeu</h4>
                    <div class="education-value">R$ <?php echo number_format($saldoEstatisticas['total_creditado'] ?? 0, 2, ',', '.'); ?></div>
                    <p>Total de cashback que você ganhou</p>
                </div>
                
                <div class="education-card">
                    <div class="education-icon">💸</div>
                    <h4>Você usou</h4>
                    <div class="education-value">R$ <?php echo number_format($saldoEstatisticas['total_usado'] ?? 0, 2, ',', '.'); ?></div>
                    <p>Cashback que você já usou como desconto</p>
                </div>
                
                <div class="education-card">
                    <div class="education-icon">🔄</div>
                    <h4>Você economizou</h4>
                    <div class="education-value"><?php echo $saldoEstatisticas['qtd_usos'] ?? 0; ?>x</div>
                    <p>Vezes que você usou o saldo para economizar</p>
                </div>
            </div>
            
            <div class="education-explanation">
                <div class="explanation-item">
                    <span class="explanation-icon">ℹ️</span>
                    <p><strong>Lembre-se:</strong> Você pode usar o saldo de cashback de cada loja apenas na própria loja onde foi gerado. É como ter um "crédito" exclusivo em cada estabelecimento!</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>
    
    <!-- Modal de detalhes redesenhado -->
    <div class="modal-overlay" id="detalheModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>📋 Detalhes da Compra</h2>
                <button class="modal-close" onclick="fecharModal()">✕</button>
            </div>
            
            <div class="modal-body" id="detalheConteudo">
                <div class="detail-section">
                    <h4>🏪 Informações da Loja</h4>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Loja:</span>
                            <span class="detail-value" id="transacao-loja"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Data da compra:</span>
                            <span class="detail-value" id="transacao-data"></span>
                        </div>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h4>💰 Valores da Transação</h4>
                    <div class="detail-grid">
                        <div class="detail-item highlight">
                            <span class="detail-label">🛒 Valor total da compra:</span>
                            <span class="detail-value large" id="transacao-valor-original"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">💸 Saldo usado:</span>
                            <span class="detail-value" id="transacao-saldo-usado"></span>
                        </div>
                        <div class="detail-item highlight">
                            <span class="detail-label">💳 Valor que você pagou:</span>
                            <span class="detail-value large" id="transacao-valor-pago"></span>
                        </div>
                        <div class="detail-item cashback">
                            <span class="detail-label">🎁 Cashback recebido:</span>
                            <span class="detail-value large" id="transacao-cashback"></span>
                        </div>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h4>📊 Status e Informações</h4>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value" id="transacao-status"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Percentual de cashback:</span>
                            <span class="detail-value" id="transacao-percentual"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">ID da transação:</span>
                            <span class="detail-value small" id="transacao-id"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
    /* CSS completamente redesenhado para ser mais intuitivo e responsivo */
    :root {
        --primary-color: #FF7A00;
        --primary-light: #FFE8D4;
        --success-color: #22C55E;
        --success-light: #DCFCE7;
        --warning-color: #F59E0B;
        --warning-light: #FEF3C7;
        --danger-color: #EF4444;
        --danger-light: #FEE2E2;
        --gray-50: #F9FAFB;
        --gray-100: #F3F4F6;
        --gray-200: #E5E7EB;
        --gray-300: #D1D5DB;
        --gray-500: #6B7280;
        --gray-700: #374151;
        --gray-900: #111827;
        --white: #FFFFFF;
        --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
        --radius: 12px;
        --radius-lg: 16px;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        background: linear-gradient(135deg, #FFF9F2 0%, #FFF5E6 100%);
        min-height: 100vh;
        color: var(--gray-900);
        line-height: 1.6;
    }

    .statement-container {
        max-width: 1200px;
        margin: 80px auto 0;
        padding: 20px;
        min-height: calc(100vh - 80px);
    }

    /* Header renovado */
    .statement-header {
        background: linear-gradient(135deg, var(--primary-color) 0%, #E06E00 100%);
        color: white;
        padding: 32px;
        border-radius: var(--radius-lg);
        margin-bottom: 32px;
        box-shadow: var(--shadow-lg);
    }

    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 24px;
        gap: 20px;
    }

    .header-text h1 {
        font-size: clamp(1.75rem, 4vw, 2.5rem);
        font-weight: 700;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .money-icon {
        font-size: 1.2em;
    }

    .header-subtitle {
        font-size: 1.1rem;
        opacity: 0.9;
        max-width: 500px;
    }

    .header-actions {
        display: flex;
        gap: 12px;
        flex-shrink: 0;
    }

    .action-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 20px;
        border: none;
        border-radius: var(--radius);
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        font-size: 0.95rem;
        white-space: nowrap;
    }

    .action-btn.primary {
        background: white;
        color: var(--primary-color);
    }

    .action-btn.primary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .action-btn.secondary {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .action-btn.secondary:hover {
        background: rgba(255, 255, 255, 0.3);
    }

    .btn-icon {
        font-size: 1.1em;
    }

    /* Guia explicativo */
    .info-guide {
        display: flex;
        gap: 24px;
        flex-wrap: wrap;
    }

    .guide-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.95rem;
        opacity: 0.9;
    }

    .guide-icon {
        font-size: 1.2em;
    }

    /* Mensagem de erro */
    .error-message {
        background: var(--danger-light);
        border: 1px solid var(--danger-color);
        border-radius: var(--radius);
        padding: 24px;
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 32px;
    }

    .error-icon {
        font-size: 2rem;
        flex-shrink: 0;
    }

    .error-content h3 {
        color: var(--danger-color);
        margin-bottom: 8px;
    }

    /* Painel de filtros */
    .filters-panel {
        background: white;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-md);
        margin-bottom: 32px;
        overflow: hidden;
        border: 1px solid var(--gray-200);
    }

    .filters-header {
        background: var(--gray-50);
        padding: 20px 32px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid var(--gray-200);
    }

    .filters-header h3 {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--gray-700);
    }

    .close-filters {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--gray-500);
        padding: 4px;
        border-radius: 4px;
    }

    .close-filters:hover {
        background: var(--gray-100);
    }

    .filters-form {
        padding: 32px;
    }

    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 24px;
        margin-bottom: 32px;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .filter-group label {
        font-weight: 600;
        color: var(--gray-700);
        font-size: 0.95rem;
    }

    .filter-input {
        padding: 12px 16px;
        border: 2px solid var(--gray-200);
        border-radius: var(--radius);
        font-size: 0.95rem;
        transition: border-color 0.2s ease;
        background: white;
    }

    .filter-input:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px var(--primary-light);
    }

    .filter-actions {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
    }

    /* Dashboard de resumo */
    .summary-dashboard {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 24px;
        margin-bottom: 40px;
    }

    .summary-card {
        background: white;
        border-radius: var(--radius-lg);
        padding: 28px;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .summary-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
    }

    .card-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
    }

    .card-icon {
        font-size: 2rem;
    }

    .card-header h3 {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--gray-700);
    }

    .card-value {
        font-size: 2.25rem;
        font-weight: 700;
        margin-bottom: 8px;
        color: var(--gray-900);
    }

    .card-description {
        color: var(--gray-500);
        font-size: 0.9rem;
    }

    .summary-card.total-spent .card-icon { color: #3B82F6; }
    .summary-card.total-cashback .card-icon { color: var(--success-color); }
    .summary-card.balance-used .card-icon { color: var(--warning-color); }
    .summary-card.total-transactions .card-icon { color: #8B5CF6; }

    .summary-card.total-spent .card-value { color: #3B82F6; }
    .summary-card.total-cashback .card-value { color: var(--success-color); }
    .summary-card.balance-used .card-value { color: var(--warning-color); }
    .summary-card.total-transactions .card-value { color: #8B5CF6; }

    /* Seção de transações */
    .transactions-section {
        background: white;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        overflow: hidden;
    }

    .section-header {
        padding: 32px;
        border-bottom: 1px solid var(--gray-200);
        background: var(--gray-50);
    }

    .section-header h2 {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 8px;
        color: var(--gray-900);
    }

    .section-subtitle {
        color: var(--gray-500);
        font-size: 1rem;
    }

    /* Estado vazio */
    .empty-state {
        text-align: center;
        padding: 64px 32px;
        color: var(--gray-500);
    }

    .empty-icon {
        font-size: 4rem;
        margin-bottom: 24px;
    }

    .empty-state h3 {
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 12px;
        color: var(--gray-700);
    }

    .empty-state p {
        font-size: 1.1rem;
        margin-bottom: 32px;
        max-width: 400px;
        margin-left: auto;
        margin-right: auto;
    }

    /* Lista de transações */
    .transactions-list {
        padding: 32px;
    }

    .transaction-card {
        background: var(--gray-50);
        border: 2px solid var(--gray-200);
        border-radius: var(--radius-lg);
        padding: 24px;
        margin-bottom: 20px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .transaction-card:hover {
        border-color: var(--primary-color);
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
    }

    .transaction-card:last-child {
        margin-bottom: 0;
    }

    .transaction-main {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 20px;
        gap: 16px;
    }

    .transaction-info {
        flex: 1;
    }

    .transaction-store {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
    }

    .store-icon {
        font-size: 1.2rem;
    }

    .store-name {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--gray-900);
    }

    .transaction-date {
        color: var(--gray-500);
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .transaction-time {
        opacity: 0.8;
    }

    .status-badge {
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
    }

    .status-badge.approved {
        background: var(--success-light);
        color: var(--success-color);
    }

    .status-badge.pending {
        background: var(--warning-light);
        color: var(--warning-color);
    }

    .status-badge.cancelled {
        background: var(--danger-light);
        color: var(--danger-color);
    }

    .transaction-values {
        margin-bottom: 20px;
    }

    .value-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
    }

    .value-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .value-label {
        font-size: 0.85rem;
        color: var(--gray-500);
        font-weight: 500;
    }

    .value-amount {
        font-size: 1.1rem;
        font-weight: 700;
    }

    .value-item.primary .value-amount { color: #3B82F6; }
    .value-item.discount .value-amount { color: var(--warning-color); }
    .value-item.paid .value-amount { color: var(--gray-700); }
    .value-item.cashback .value-amount { color: var(--success-color); }

    .transaction-actions {
        display: flex;
        justify-content: flex-end;
    }

    .detail-btn {
        background: none;
        border: 2px solid var(--primary-color);
        color: var(--primary-color);
        padding: 8px 16px;
        border-radius: var(--radius);
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
    }

    .detail-btn:hover {
        background: var(--primary-color);
        color: white;
    }

    .arrow {
        transition: transform 0.2s ease;
    }

    .detail-btn:hover .arrow {
        transform: translateX(4px);
    }

    /* Paginação */
    .pagination-section {
        padding: 32px;
        border-top: 1px solid var(--gray-200);
        background: var(--gray-50);
    }

    .pagination-info {
        text-align: center;
        color: var(--gray-500);
        margin-bottom: 20px;
        font-size: 0.95rem;
    }

    .pagination-controls {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }

    .pagination-btn, .pagination-number {
        padding: 8px 16px;
        border: 2px solid var(--gray-200);
        background: white;
        color: var(--gray-700);
        text-decoration: none;
        border-radius: var(--radius);
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .pagination-btn:hover, .pagination-number:hover {
        border-color: var(--primary-color);
        color: var(--primary-color);
    }

    .pagination-numbers {
        display: flex;
        gap: 8px;
    }

    .pagination-number.active {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
    }

    /* Seção educativa */
    .education-section {
        background: white;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        margin-top: 32px;
        overflow: hidden;
    }

    .education-header {
        background: linear-gradient(135deg, #F0F9FF 0%, #E0F2FE 100%);
        padding: 24px 32px;
        border-bottom: 1px solid var(--gray-200);
    }

    .education-header h3 {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--gray-900);
    }

    .education-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 24px;
        padding: 32px;
    }

    .education-card {
        text-align: center;
        padding: 24px;
        background: var(--gray-50);
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
    }

    .education-icon {
        font-size: 2.5rem;
        margin-bottom: 12px;
    }

    .education-card h4 {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 8px;
        color: var(--gray-700);
    }

    .education-value {
        font-size: 1.75rem;
        font-weight: 700;
        margin-bottom: 8px;
        color: var(--primary-color);
    }

    .education-card p {
        font-size: 0.9rem;
        color: var(--gray-500);
    }

    .education-explanation {
        padding: 24px 32px;
        background: var(--primary-light);
        border-top: 1px solid var(--gray-200);
    }

    .explanation-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .explanation-icon {
        font-size: 1.2rem;
        flex-shrink: 0;
        margin-top: 2px;
    }

    .explanation-item p {
        color: var(--gray-700);
        line-height: 1.6;
    }

    /* Modal */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(4px);
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .modal-overlay.show {
        display: flex;
    }

    .modal-content {
        background: white;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        width: 100%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
    }

    .modal-header {
        padding: 24px 32px;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--gray-50);
    }

    .modal-header h2 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--gray-900);
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--gray-500);
        padding: 4px;
        border-radius: 4px;
    }

    .modal-close:hover {
        background: var(--gray-200);
    }

    .modal-body {
        padding: 32px;
    }

    .detail-section {
        margin-bottom: 32px;
    }

    .detail-section:last-child {
        margin-bottom: 0;
    }

    .detail-section h4 {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 16px;
        color: var(--gray-900);
        padding-bottom: 8px;
        border-bottom: 2px solid var(--gray-200);
    }

    .detail-grid {
        display: grid;
        gap: 16px;
    }

    .detail-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        background: var(--gray-50);
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
    }

    .detail-item.highlight {
        background: var(--primary-light);
        border-color: var(--primary-color);
    }

    .detail-item.cashback {
        background: var(--success-light);
        border-color: var(--success-color);
    }

    .detail-label {
        font-weight: 600;
        color: var(--gray-700);
        font-size: 0.95rem;
    }

    .detail-value {
        font-weight: 600;
        color: var(--gray-900);
    }

    .detail-value.large {
        font-size: 1.1rem;
        font-weight: 700;
    }

    .detail-value.small {
        font-size: 0.85rem;
        font-family: monospace;
        color: var(--gray-500);
    }

    /* Responsividade */
    @media (max-width: 768px) {
        .statement-container {
            padding: 16px;
            margin-top: 70px;
        }

        .statement-header {
            padding: 24px 20px;
        }

        .header-content {
            flex-direction: column;
            align-items: stretch;
            gap: 16px;
        }

        .header-actions {
            justify-content: center;
        }

        .info-guide {
            justify-content: center;
            gap: 16px;
        }

        .guide-item {
            font-size: 0.85rem;
        }

        .summary-dashboard {
            grid-template-columns: 1fr;
            gap: 16px;
        }

        .summary-card {
            padding: 20px;
        }

        .card-value {
            font-size: 1.75rem;
        }

        .filters-form {
            padding: 20px;
        }

        .filter-grid {
            grid-template-columns: 1fr;
            gap: 16px;
        }

        .filter-actions {
            justify-content: stretch;
        }

        .filter-actions .action-btn {
            flex: 1;
        }

        .section-header {
            padding: 20px;
        }

        .section-header h2 {
            font-size: 1.25rem;
        }

        .transactions-list {
            padding: 20px;
        }

        .transaction-card {
            padding: 20px;
        }

        .transaction-main {
            flex-direction: column;
            gap: 12px;
        }

        .value-row {
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .value-item {
            padding: 12px;
            background: white;
            border-radius: var(--radius);
            border: 1px solid var(--gray-200);
        }

        .pagination-controls {
            flex-direction: column;
            gap: 12px;
        }

        .pagination-numbers {
            order: -1;
        }

        .education-cards {
            grid-template-columns: 1fr;
            padding: 20px;
            gap: 16px;
        }

        .education-explanation {
            padding: 20px;
        }

        .modal-content {
            margin: 10px;
            max-height: calc(100vh - 20px);
        }

        .modal-header {
            padding: 20px;
        }

        .modal-body {
            padding: 20px;
        }

        .detail-item {
            flex-direction: column;
            align-items: stretch;
            gap: 8px;
            text-align: center;
        }
    }

    @media (max-width: 480px) {
        .header-text h1 {
            font-size: 1.5rem;
        }

        .header-subtitle {
            font-size: 1rem;
        }

        .action-btn {
            padding: 10px 16px;
            font-size: 0.9rem;
        }

        .card-value {
            font-size: 1.5rem;
        }

        .transaction-card {
            padding: 16px;
        }

        .store-name {
            font-size: 1rem;
        }

        .value-amount {
            font-size: 1rem;
        }
    }
    </style>

    <script>
        // Função para alternar a exibição dos filtros
        function toggleFilters() {
            const panel = document.getElementById('filtersPanel');
            const isVisible = panel.style.display !== 'none';
            panel.style.display = isVisible ? 'none' : 'block';
            
            // Animar a entrada
            if (!isVisible) {
                panel.style.opacity = '0';
                panel.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    panel.style.transition = 'all 0.3s ease';
                    panel.style.opacity = '1';
                    panel.style.transform = 'translateY(0)';
                }, 10);
            }
        }

        // Função para limpar filtros
        function limparFiltros() {
            const form = document.querySelector('.filters-form');
            const inputs = form.querySelectorAll('input, select');
            inputs.forEach(input => {
                if (input.type === 'date' || input.type === 'text') {
                    input.value = '';
                } else if (input.tagName === 'SELECT') {
                    input.selectedIndex = 0;
                }
            });
            
            // Submeter formulário para aplicar a limpeza
            window.location.href = window.location.pathname;
        }

        // CORREÇÃO: Função para exportar extrato em HTML editável
        function exportarExtrato() {
            // Coletar filtros ativos
            const filtros = {
                data_inicio: document.getElementById('data_inicio')?.value || '',
                data_fim: document.getElementById('data_fim')?.value || '',
                loja_id: document.querySelector('select[name="loja_id"]')?.value || '',
                status: document.querySelector('select[name="status"]')?.value || '',
                tipo_transacao: document.querySelector('select[name="tipo_transacao"]')?.value || ''
            };
            
            // Construir parâmetros da URL
            const params = new URLSearchParams();
            Object.keys(filtros).forEach(key => {
                if (filtros[key] && filtros[key] !== 'todas' && filtros[key] !== 'todos') {
                    params.append(key, filtros[key]);
                }
            });
            
            // Adicionar action para exportar
            params.append('action', 'export_statement');
            
            // Redirecionar para a exportação
            window.open(`<?php echo SITE_URL; ?>/controllers/ClientController.php?${params.toString()}`, '_blank');
        }
        
        // CORREÇÃO: Função para exibir detalhes da transação
        function verDetalhes(transacaoId) {
            // Mostrar loading no modal
            const modal = document.getElementById('detalheModal');
            modal.classList.add('show');
            
            // Criar FormData para enviar via POST
            const formData = new FormData();
            formData.append('action', 'transaction');
            formData.append('transaction_id', transacaoId);
            
            fetch('<?php echo SITE_URL; ?>/controllers/ClientController.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    const transacao = data.data.transacao;
                    
                    // Preencher os campos do modal
                    document.getElementById('transacao-id').textContent = transacao.id;
                    document.getElementById('transacao-data').textContent = formatarData(transacao.data_transacao);
                    document.getElementById('transacao-loja').textContent = transacao.loja_nome;
                    
                    // Valores originais e calculados
                    const valorOriginal = parseFloat(transacao.valor_total);
                    const saldoUsado = parseFloat(transacao.saldo_usado || 0);
                    const valorPago = valorOriginal - saldoUsado;
                    
                    document.getElementById('transacao-valor-original').textContent = 'R$ ' + formatarValor(valorOriginal);
                    document.getElementById('transacao-saldo-usado').textContent = saldoUsado > 0 ? 'R$ ' + formatarValor(saldoUsado) : 'Não usado';
                    document.getElementById('transacao-valor-pago').textContent = 'R$ ' + formatarValor(valorPago);
                    
                    // CORREÇÃO: Mostrar o cashback que o CLIENTE recebeu
                    document.getElementById('transacao-cashback').textContent = 'R$ ' + formatarValor(transacao.valor_cliente);
                    
                    // Calcular percentual baseado no valor do cliente, não valor total de cashback
                    const percentual = (transacao.valor_cliente / transacao.valor_total) * 100;
                    document.getElementById('transacao-percentual').textContent = formatarValor(percentual) + '%';
                    
                    // Status com formatação adequada
                    const statusElement = document.getElementById('transacao-status');
                    const statusConfig = {
                        'aprovado': { text: '✅ Confirmado', class: 'approved' },
                        'pendente': { text: '⏳ Aguardando', class: 'pending' },
                        'cancelado': { text: '❌ Cancelado', class: 'cancelled' }
                    };
                    
                    const status = statusConfig[transacao.status] || statusConfig['pendente'];
                    statusElement.innerHTML = `<span class="status-badge ${status.class}">${status.text}</span>`;
                } else {
                    alert('Erro ao buscar detalhes da transação: ' + data.message);
                    fecharModal();
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao buscar detalhes da transação');
                fecharModal();
            });
        }
        
        // Função para fechar o modal
        function fecharModal() {
            const modal = document.getElementById('detalheModal');
            modal.classList.remove('show');
        }
        
        // Utilitários
        function formatarData(dataString) {
            const data = new Date(dataString);
            return data.toLocaleString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        function formatarValor(valor) {
            return parseFloat(valor).toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
        
        // Fechar modal ao clicar fora dele
        window.onclick = function(event) {
            const modal = document.getElementById('detalheModal');
            if (event.target === modal) {
                fecharModal();
            }
        };

        // Fechar filtros ao pressionar ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const panel = document.getElementById('filtersPanel');
                if (panel.style.display !== 'none') {
                    toggleFilters();
                }
                
                const modal = document.getElementById('detalheModal');
                if (modal.classList.contains('show')) {
                    fecharModal();
                }
            }
        });

        // Animações suaves ao carregar a página
        document.addEventListener('DOMContentLoaded', function() {
            // Animar cards do resumo
            const summaryCards = document.querySelectorAll('.summary-card');
            summaryCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Animar cards de transação
            const transactionCards = document.querySelectorAll('.transaction-card');
            transactionCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateX(-20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.3s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateX(0)';
                }, 200 + (index * 50));
            });
        });
    </script>
</body>
</html>