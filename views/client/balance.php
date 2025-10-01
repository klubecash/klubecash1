<?php
// views/client/balance.php - Versão Reformulada
// Definir o menu ativo na sidebar
$activeMenu = 'saldo';

// Incluir conexão com o banco de dados e arquivos necessários
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../controllers/ClientController.php';
require_once '../../controllers/AuthController.php';

// Iniciar sessão
session_start();

// Verificar se o usuário está logado e é cliente
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== USER_TYPE_CLIENT) {
    header("Location: " . LOGIN_URL . "?error=acesso_restrito");
    exit;
}

// Obter saldo do cliente logado (mantendo toda a lógica original)
$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Cliente';

try {
    $db = Database::getConnection();
    
    // Saldo total disponível
    $saldoTotalQuery = "
        SELECT 
            SUM(saldo_disponivel) as total_disponivel,
            COUNT(DISTINCT loja_id) as total_lojas_com_saldo
        FROM cashback_saldos 
        WHERE usuario_id = :user_id AND saldo_disponivel > 0
    ";
    $saldoTotalStmt = $db->prepare($saldoTotalQuery);
    $saldoTotalStmt->bindParam(':user_id', $userId);
    $saldoTotalStmt->execute();
    $saldoTotal = $saldoTotalStmt->fetch(PDO::FETCH_ASSOC);
    
    // Saldos por loja (mantendo consulta original)
    $saldosPorLojaQuery = "
        SELECT 
            cs.*,
            l.nome_fantasia,
            l.categoria,
            l.porcentagem_cashback,
            l.logo,
            l.website,
            l.descricao,
            (SELECT COUNT(*) FROM cashback_movimentacoes cm 
             WHERE cm.usuario_id = cs.usuario_id AND cm.loja_id = cs.loja_id) as total_movimentacoes,
            (SELECT MAX(data_operacao) FROM cashback_movimentacoes cm 
             WHERE cm.usuario_id = cs.usuario_id AND cm.loja_id = cs.loja_id) as ultima_movimentacao
        FROM cashback_saldos cs
        JOIN lojas l ON cs.loja_id = l.id
        WHERE cs.usuario_id = :user_id
        ORDER BY cs.saldo_disponivel DESC
    ";
    $saldosPorLojaStmt = $db->prepare($saldosPorLojaQuery);
    $saldosPorLojaStmt->bindParam(':user_id', $userId);
    $saldosPorLojaStmt->execute();
    $saldosPorLoja = $saldosPorLojaStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Saldos pendentes (mantendo consulta original)
    $saldoPendenteQuery = "
        SELECT 
            SUM(t.valor_cliente) as total_pendente,
            l.nome_fantasia,
            COUNT(*) as qtd_transacoes,
            l.id as loja_id
        FROM transacoes_cashback t
        JOIN lojas l ON t.loja_id = l.id
        WHERE t.usuario_id = :user_id 
        AND t.status = 'pendente'
        GROUP BY l.id, l.nome_fantasia
        ORDER BY total_pendente DESC
    ";
    $saldoPendenteStmt = $db->prepare($saldoPendenteQuery);
    $saldoPendenteStmt->bindParam(':user_id', $userId);
    $saldoPendenteStmt->execute();
    $saldosPendentes = $saldoPendenteStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalSaldoPendente = array_sum(array_column($saldosPendentes, 'total_pendente'));
    
    // Estatísticas gerais (mantendo consulta original)
    $estatisticasQuery = "
        SELECT 
            SUM(CASE WHEN tipo_operacao = 'credito' THEN valor ELSE 0 END) as total_creditado,
            SUM(CASE WHEN tipo_operacao = 'uso' THEN valor ELSE 0 END) as total_usado,
            SUM(CASE WHEN tipo_operacao = 'estorno' THEN valor ELSE 0 END) as total_estornado,
            COUNT(CASE WHEN tipo_operacao = 'uso' THEN 1 END) as total_usos,
            COUNT(CASE WHEN tipo_operacao = 'credito' THEN 1 END) as total_creditos,
            COUNT(DISTINCT loja_id) as total_lojas_utilizadas
        FROM cashback_movimentacoes 
        WHERE usuario_id = :user_id
    ";
    $estatisticasStmt = $db->prepare($estatisticasQuery);
    $estatisticasStmt->bindParam(':user_id', $userId);
    $estatisticasStmt->execute();
    $estatisticas = $estatisticasStmt->fetch(PDO::FETCH_ASSOC);
    
    // Movimentações recentes (mantendo consulta original)
    $movimentacoesQuery = "
        SELECT 
            cm.*,
            l.nome_fantasia as loja_nome
        FROM cashback_movimentacoes cm
        JOIN lojas l ON cm.loja_id = l.id
        WHERE cm.usuario_id = :user_id
        ORDER BY cm.data_operacao DESC
        LIMIT 10
    ";
    $movimentacoesStmt = $db->prepare($movimentacoesQuery);
    $movimentacoesStmt->bindParam(':user_id', $userId);
    $movimentacoesStmt->execute();
    $movimentacoesRecentes = $movimentacoesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Dados mensais para gráfico (mantendo consulta original)
    $dadosMensaisQuery = "
        SELECT 
            DATE_FORMAT(data_operacao, '%Y-%m') as mes,
            SUM(CASE WHEN tipo_operacao = 'credito' THEN valor ELSE 0 END) as creditos,
            SUM(CASE WHEN tipo_operacao = 'uso' THEN valor ELSE 0 END) as usos,
            SUM(CASE WHEN tipo_operacao = 'estorno' THEN valor ELSE 0 END) as estornos
        FROM cashback_movimentacoes
        WHERE usuario_id = :user_id
        AND data_operacao >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(data_operacao, '%Y-%m')
        ORDER BY mes ASC
    ";
    $dadosMensaisStmt = $db->prepare($dadosMensaisQuery);
    $dadosMensaisStmt->bindParam(':user_id', $userId);
    $dadosMensaisStmt->execute();
    $dadosMensais = $dadosMensaisStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasError = false;
    $errorMessage = '';
    
} catch (Exception $e) {
    error_log('Erro ao carregar dados de saldo: ' . $e->getMessage());
    $hasError = true;
    $errorMessage = 'Ops! Não conseguimos carregar seus saldos no momento.';
    
    // Valores padrão em caso de erro
    $saldoTotal = ['total_disponivel' => 0, 'total_lojas_com_saldo' => 0];
    $saldosPorLoja = [];
    $saldosPendentes = [];
    $totalSaldoPendente = 0;
    $estatisticas = [
        'total_creditado' => 0, 'total_usado' => 0, 'total_estornado' => 0,
        'total_usos' => 0, 'total_creditos' => 0, 'total_lojas_utilizadas' => 0
    ];
    $movimentacoesRecentes = [];
    $dadosMensais = [];
}

// Funções auxiliares (mantendo as originais)
function formatCurrency($value) {
    return 'R$ ' . number_format($value ?: 0, 2, ',', '.');
}

function formatDate($date) {
    if (!$date) return 'Nunca';
    return date('d/m/Y', strtotime($date));
}

function formatDateTime($datetime) {
    if (!$datetime) return 'Nunca';
    return date('d/m/Y H:i', strtotime($datetime));
}

function formatMonth($yearMonth) {
    if (!$yearMonth) return '';
    $parts = explode('-', $yearMonth);
    $year = $parts[0];
    $month = $parts[1];
    
    $monthNames = [
        '01' => 'Jan', '02' => 'Fev', '03' => 'Mar', '04' => 'Abr', 
        '05' => 'Mai', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago', 
        '09' => 'Set', '10' => 'Out', '11' => 'Nov', '12' => 'Dez'
    ];
    
    return $monthNames[$month] . '/' . substr($year, 2);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Saldos - Klube Cash</title>
    <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
    <link rel="stylesheet" href="../../assets/css/views/client/balance-new.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Incluir navegação navbar -->
    <?php include_once '../components/navbar.php'; ?>
    
    <div class="balance-container">
        <!-- Cabeçalho Explicativo -->
        <div class="balance-header">
            <div class="header-content">
                <h1>💰 Seus Saldos de Cashback</h1>
                <p class="header-subtitle">
                    Aqui você encontra todo o dinheiro que ganhou de volta nas suas compras. 
                    <strong>Lembre-se:</strong> cada loja tem sua própria "carteira" de saldo!
                </p>
            </div>
            <div class="header-actions">
                <a href="<?php echo CLIENT_STATEMENT_URL; ?>" class="action-btn secondary">
                    📄 Ver Extrato Completo
                </a>
                <a href="<?php echo CLIENT_DASHBOARD_URL; ?>" class="action-btn primary">
                    🏠 Voltar ao Início
                </a>
            </div>
        </div>

        <?php if ($hasError): ?>
            <div class="error-card">
                <div class="error-icon">⚠️</div>
                <div class="error-content">
                    <h3>Oops! Algo deu errado</h3>
                    <p><?php echo htmlspecialchars($errorMessage); ?></p>
                    <button onclick="location.reload()" class="retry-btn">Tentar Novamente</button>
                </div>
            </div>
        <?php else: ?>

        <!-- Cards de Resumo Principal -->
        <div class="summary-cards">
            <!-- Saldo Total Disponível -->
            <div class="summary-card main-balance">
                crítica    <div class="card-content">
                    <div class="card-icon">💳</div>
                    <div class="card-info">
                        <h3>Dinheiro Pronto para Usar</h3>
                        <p>Valor que você pode usar nas suas próximas compras</p>
                    </div>
                </div>
                <div class="card-value">
                    <span class="currency">R$</span>
                    <span class="amount" data-value="<?php echo $saldoTotal['total_disponivel']; ?>">
                        <?php echo number_format($saldoTotal['total_disponivel'], 2, ',', '.'); ?>
                    </span>
                </div>
                <div class="card-stats">
                    <div class="stat">
                        <span class="stat-value"><?php echo $saldoTotal['total_lojas_com_saldo']; ?></span>
                        <span class="stat-label">
                            <?php echo $saldoTotal['total_lojas_com_saldo'] == 1 ? 'loja com saldo' : 'lojas com saldo'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Saldo Aguardando -->
            <?php if ($totalSaldoPendente > 0): ?>
            <div class="summary-card pending-balance">
                <div class="card-content">
                    <div class="card-icon">⏳</div>
                    <div class="card-info">
                        <h3>Chegando em Breve</h3>
                        <p>Cashback que ainda vai ser liberado pelas lojas</p>
                    </div>
                </div>
                <div class="card-value pending">
                    <span class="currency">R$</span>
                    <span class="amount"><?php echo number_format($totalSaldoPendente, 2, ',', '.'); ?></span>
                </div>
                <div class="pending-details">
                    <?php foreach (array_slice($saldosPendentes, 0, 2) as $pendente): ?>
                        <div class="pending-item">
                            <span class="store-name"><?php echo htmlspecialchars($pendente['nome_fantasia']); ?></span>
                            <span class="pending-amount"><?php echo formatCurrency($pendente['total_pendente']); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (count($saldosPendentes) > 2): ?>
                        <div class="pending-more">
                            +<?php echo count($saldosPendentes) - 2; ?> outras lojas
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Total Já Economizado -->
            <div class="summary-card total-savings">
                <div class="card-content">
                    <div class="card-icon">🎯</div>
                    <div class="card-info">
                        <h3>Total que Você Economizou</h3>
                        <p>Quanto dinheiro você já ganhou de volta</p>
                    </div>
                </div>
                <div class="card-value">
                    <span class="currency">R$</span>
                    <span class="amount"><?php echo number_format($estatisticas['total_creditado'], 2, ',', '.'); ?></span>
                </div>
                <div class="savings-breakdown">
                    <div class="breakdown-row">
                        <span class="label">💰 Já usei:</span>
                        <span class="value used"><?php echo formatCurrency($estatisticas['total_usado']); ?></span>
                    </div>
                    <div class="breakdown-row">
                        <span class="label">🏦 Disponível:</span>
                        <span class="value available"><?php echo formatCurrency($saldoTotal['total_disponivel']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Como Usar Seu Saldo -->
        <div class="info-section">
            <div class="info-card how-to-use">
                <h3>🤔 Como Usar Meu Saldo?</h3>
                <div class="usage-steps">
                    <div class="step">
                        <div class="step-icon">1️⃣</div>
                        <div class="step-content">
                            <strong>Escolha a loja</strong>
                            <p>Vá até uma das lojas onde você tem saldo</p>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-icon">2️⃣</div>
                        <div class="step-content">
                            <strong>Mostre que é cliente Klube Cash</strong>
                            <p>Informe na hora do pagamento que quer usar o saldo</p>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-icon">3️⃣</div>
                        <div class="step-content">
                            <strong>Economize na compra!</strong>
                            <p>O valor do seu saldo será descontado do total</p>
                        </div>
                    </div>
                </div>
                <div class="important-note">
                    <div class="note-icon">💡</div>
                    <p>
                        <strong>Importante:</strong> Você só pode usar o saldo na mesma loja onde o ganhou. 
                        É como ter uma carteira separada para cada loja!
                    </p>
                </div>
            </div>
        </div>

        <!-- Seus Saldos por Loja -->
        <div class="stores-section">
            <div class="section-header">
                <h2>🏪 Suas Carteiras por Loja</h2>
                <p class="section-subtitle">Cada loja tem seu próprio saldo que você pode usar</p>
            </div>
            
            <?php if (empty($saldosPorLoja)): ?>
                <div class="empty-state">
                    <div class="empty-illustration">
                        <div class="empty-icon">🛍️</div>
                        <div class="empty-coins">💰💰💰</div>
                    </div>
                    <h3>Você ainda não tem saldo em nenhuma loja</h3>
                    <p>
                        Quando fizer compras nas lojas parceiras, o cashback aparecerá aqui. 
                        Que tal começar a economizar agora?
                    </p>
                    <a href="<?php echo CLIENT_STORES_URL; ?>" class="start-shopping-btn">
                        🛒 Ver Lojas Parceiras
                    </a>
                </div>
            <?php else: ?>
                <div class="stores-grid">
                    <?php foreach ($saldosPorLoja as $loja): ?>
                    <div class="store-card <?php echo $loja['saldo_disponivel'] <= 0 ? 'no-balance' : ''; ?>">
                        <div class="store-header">
                            <div class="store-logo">
                                <?php if (!empty($loja['logo']) && file_exists('../../uploads/store_logos/' . $loja['logo'])): ?>
                                    <img src="../../uploads/store_logos/<?php echo htmlspecialchars($loja['logo']); ?>" 
                                         alt="<?php echo htmlspecialchars($loja['nome_fantasia']); ?>" 
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="store-initial" style="display: none;">
                                        <?php echo strtoupper(substr($loja['nome_fantasia'], 0, 1)); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="store-initial">
                                        <?php echo strtoupper(substr($loja['nome_fantasia'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="store-info">
                                <h3 class="store-name"><?php echo htmlspecialchars($loja['nome_fantasia']); ?></h3>
                                <span class="store-category">
                                    <?php echo htmlspecialchars($loja['categoria'] ?? 'Geral'); ?>
                                </span>
                                <div class="cashback-rate">
                                    Você ganha <?php echo number_format($loja['porcentagem_cashback'] / 2, 1); ?>% de volta
                                </div>
                            </div>
                        </div>
                        
                        <div class="store-balance">
                            <?php if ($loja['saldo_disponivel'] > 0): ?>
                                <div class="balance-available">
                                    <div class="balance-label">Você pode usar:</div>
                                    <div class="balance-amount">
                                        <?php echo formatCurrency($loja['saldo_disponivel']); ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="balance-empty">
                                    <div class="empty-message">Sem saldo disponível</div>
                                    <div class="encourage-text">Faça compras para ganhar cashback! 🛍️</div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="store-stats">
                            <div class="stat-item">
                                <span class="stat-icon">💰</span>
                                <div class="stat-info">
                                    <span class="stat-value"><?php echo formatCurrency($loja['total_creditado']); ?></span>
                                    <span class="stat-label">Total recebido</span>
                                </div>
                            </div>
                            <div class="stat-item">
                                <span class="stat-icon">🛒</span>
                                <div class="stat-info">
                                    <span class="stat-value"><?php echo formatCurrency($loja['total_usado']); ?></span>
                                    <span class="stat-label">Já usado</span>
                                </div>
                            </div>
                        </div>

                        <div class="store-actions">
                            <button class="details-btn" onclick="viewStoreDetails(<?php echo $loja['loja_id']; ?>)">
                                📊 Ver Detalhes
                            </button>
                            <?php if (!empty($loja['website'])): ?>
                                <a href="<?php echo htmlspecialchars($loja['website']); ?>" 
                                   target="_blank" class="visit-btn">
                                    🌐 Visitar Loja
                                </a>
                            <?php endif; ?>
                        </div>

                        <?php if ($loja['ultima_movimentacao']): ?>
                            <div class="last-activity">
                                Última atividade: <?php echo formatDate($loja['ultima_movimentacao']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Seção de Atividades e Estatísticas -->
        <div class="activity-section">
            <!-- Suas Últimas Atividades -->
            <div class="activity-card">
                <div class="activity-header">
                    <h3>📈 Suas Últimas Atividades</h3>
                    <a href="<?php echo CLIENT_STATEMENT_URL; ?>" class="see-all-link">Ver Todas →</a>
                </div>
                
                <?php if (empty($movimentacoesRecentes)): ?>
                    <div class="no-activity">
                        <div class="no-activity-icon">📊</div>
                        <p>Ainda não há atividades para mostrar</p>
                        <span class="tip">Faça compras para ver suas movimentações aqui!</span>
                    </div>
                <?php else: ?>
                    <div class="activities-list">
                        <?php foreach ($movimentacoesRecentes as $movimento): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <?php 
                                    switch ($movimento['tipo_operacao']) {
                                        case 'credito': echo '💰'; break;
                                        case 'uso': echo '🛒'; break;
                                        case 'estorno': echo '↩️'; break;
                                        default: echo '📊';
                                    }
                                    ?>
                                </div>
                                <div class="activity-details">
                                    <div class="activity-description">
                                        <?php 
                                        switch ($movimento['tipo_operacao']) {
                                            case 'credito':
                                                echo 'Você ganhou cashback';
                                                break;
                                            case 'uso':
                                                echo 'Você usou seu saldo';
                                                break;
                                            case 'estorno':
                                                echo 'Estorno de saldo';
                                                break;
                                        }
                                        ?>
                                        <span class="store-name-small"> - <?php echo htmlspecialchars($movimento['loja_nome']); ?></span>
                                    </div>
                                    <div class="activity-date">
                                        <?php echo formatDateTime($movimento['data_operacao']); ?>
                                    </div>
                                </div>
                                <div class="activity-amount">
                                    <span class="amount-value <?php echo $movimento['tipo_operacao'] === 'uso' ? 'negative' : 'positive'; ?>">
                                        <?php echo $movimento['tipo_operacao'] === 'uso' ? '-' : '+'; ?>
                                        <?php echo formatCurrency($movimento['valor']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Gráfico de Evolução -->
            <?php if (!empty($dadosMensais)): ?>
            <div class="chart-card">
                <h3>📊 Evolução dos Últimos 6 Meses</h3>
                <div class="chart-container">
                    <canvas id="balanceChart"></canvas>
                </div>
                <div class="chart-legend">
                    <div class="legend-item">
                        <span class="legend-color earned"></span>
                        <span>Cashback Recebido</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-color used"></span>
                        <span>Saldo Usado</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Estatísticas Resumidas -->
        <div class="stats-section">
            <h3>📈 Resumo da Sua Jornada</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">🏆</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $estatisticas['total_creditos']; ?></div>
                        <div class="stat-description">vezes que você ganhou cashback</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🛍️</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $estatisticas['total_usos']; ?></div>
                        <div class="stat-description">vezes que você usou o saldo</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🏪</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $estatisticas['total_lojas_utilizadas']; ?></div>
                        <div class="stat-description">
                            <?php echo $estatisticas['total_lojas_utilizadas'] == 1 ? 'loja diferente' : 'lojas diferentes'; ?>
                        </div>
                    </div>
                </div>
                <div class="stat-card highlight">
                    <div class="stat-icon">💎</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo formatCurrency($estatisticas['total_creditado']); ?></div>
                        <div class="stat-description">total economizado até hoje</div>
                    </div>
                </div>
            </div>
        </div>

        <?php endif; ?>
    </div>
    
    <!-- Modal de Detalhes da Loja (mantendo funcionalidade original) -->
    <div id="storeDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalStoreTitle">Detalhes da Loja</h3>
                <button class="modal-close" onclick="closeStoreModal()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="modal-body" id="modalStoreContent">
                <!-- Conteúdo será carregado via JavaScript -->
            </div>
        </div>
    </div>
    
    <script src="../../assets/js/client/balance-new.js"></script>
</body>
</html>