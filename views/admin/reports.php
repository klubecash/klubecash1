<?php
// views/admin/reports.php
$activeMenu = 'relatorios';

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../controllers/AuthController.php';
require_once '../../controllers/AdminController.php';

session_start();

// Verificar autenticação
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== USER_TYPE_ADMIN) {
    header("Location: " . LOGIN_URL . "?error=acesso_restrito");
    exit;
}

// Processar filtros
$filters = [];
if (isset($_GET['data_inicio']) && !empty($_GET['data_inicio'])) {
    $filters['data_inicio'] = $_GET['data_inicio'];
}
if (isset($_GET['data_fim']) && !empty($_GET['data_fim'])) {
    $filters['data_fim'] = $_GET['data_fim'];
}

// Período padrão (último mês) se nenhum filtro
if (empty($filters)) {
    $filters['data_inicio'] = date('Y-m-d', strtotime('-1 month'));
    $filters['data_fim'] = date('Y-m-d');
}

try {
    // Obter dados dos relatórios - USANDO O CONTROLLER
    $result = AdminController::getFinancialReports($filters);
    
    if (!$result['status']) {
        throw new Exception($result['message']);
    }
    
    $reportData = $result['data'];
    
    // Extrair dados para facilitar o uso na view
    $saldoStats = $reportData['saldo_stats'];
    $movimentacoesStats = $reportData['movimentacoes_stats'];
    $vendasComparacao = $reportData['vendas_comparacao'];
    $lojasSaldo = $reportData['lojas_saldo'];
    $financialData = $reportData['financial_data'];
    $adminComission = $reportData['admin_comission'];
    $monthlyData = $reportData['monthly_data'];
    
    $hasError = false;
    $errorMessage = '';
    
} catch (Exception $e) {
    $hasError = true;
    $errorMessage = "Erro ao processar a requisição: " . $e->getMessage();
    
    // Valores padrão em caso de erro
    $saldoStats = [];
    $movimentacoesStats = [];
    $vendasComparacao = [];
    $lojasSaldo = [];
    $financialData = ['total_cashback' => 0, 'total_vendas' => 0];
    $adminComission = 0;
    $monthlyData = [];
}

// Funções auxiliares
function formatCurrency($value) {
    return 'R$ ' . number_format($value ?: 0, 2, ',', '.');
}

function formatPercentage($value) {
    return number_format($value ?: 0, 1, ',', '.') . '%';
}

function formatMonth($yearMonth) {
    $parts = explode('-', $yearMonth);
    $year = $parts[0];
    $month = $parts[1];
    
    $monthNames = [
        '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
        '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
        '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
    ];
    
    return $monthNames[$month] . '/' . $year;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics e Relatórios - Klube Cash</title>
    <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
    <link rel="stylesheet" href="../../assets/css/views/admin/reports.css">
    <link rel="stylesheet" href="../../assets/css/layout-fix.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include_once '../components/sidebar.php'; ?>
    
    <div class="main-content" id="mainContent">
        <div class="page-wrapper">
            <!-- Header da página -->
            <div class="page-header">
                <div class="header-content">
                    <div class="header-main">
                        <h1 class="page-title">
                            <i class="fas fa-chart-line"></i>
                            Analytics e Relatórios
                        </h1>
                        <p class="page-subtitle">Dashboard executivo com métricas e insights do negócio</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn-action" onclick="exportReport()">
                            <i class="fas fa-download"></i>
                            Exportar
                        </button>
                        <button class="btn-action" onclick="toggleDateFilter()">
                            <i class="fas fa-calendar-alt"></i>
                            Filtrar Período
                        </button>
                        <button class="btn-action btn-refresh" onclick="refreshData()">
                            <i class="fas fa-sync-alt"></i>
                            Atualizar
                        </button>
                    </div>
                </div>
                
                <!-- Filtro rápido de período -->
                <div class="quick-filters">
                    <button class="filter-chip active" onclick="setQuickFilter('today')">Hoje</button>
                    <button class="filter-chip" onclick="setQuickFilter('week')">7 dias</button>
                    <button class="filter-chip" onclick="setQuickFilter('month')">30 dias</button>
                    <button class="filter-chip" onclick="setQuickFilter('quarter')">3 meses</button>
                    <button class="filter-chip" onclick="setQuickFilter('year')">12 meses</button>
                    <button class="filter-chip" onclick="toggleDateFilter()">Personalizado</button>
                </div>
            </div>
            
            <?php if ($hasError): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php else: ?>
            
            <!-- KPIs Principais -->
            <div class="kpi-dashboard">
                <div class="kpi-card revenue">
                    <div class="kpi-header">
                        <div class="kpi-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="kpi-trend positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>+12.5%</span>
                        </div>
                    </div>
                    <div class="kpi-content">
                        <div class="kpi-value"><?php echo formatCurrency($adminComission); ?></div>
                        <div class="kpi-label">Receita Total</div>
                        <div class="kpi-subtitle">Comissão da plataforma</div>
                    </div>
                </div>
                
                <div class="kpi-card cashback">
                    <div class="kpi-header">
                        <div class="kpi-icon">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="kpi-trend positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>+8.3%</span>
                        </div>
                    </div>
                    <div class="kpi-content">
                        <div class="kpi-value"><?php echo formatCurrency($financialData['total_cashback']); ?></div>
                        <div class="kpi-label">Cashback Distribuído</div>
                        <div class="kpi-subtitle">Valor pago aos clientes</div>
                    </div>
                </div>
                
                <div class="kpi-card balance">
                    <div class="kpi-header">
                        <div class="kpi-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="kpi-trend neutral">
                            <i class="fas fa-minus"></i>
                            <span>0.0%</span>
                        </div>
                    </div>
                    <div class="kpi-content">
                        <div class="kpi-value"><?php echo formatCurrency($saldoStats['total_saldo_disponivel'] ?? 0); ?></div>
                        <div class="kpi-label">Saldo em Carteira</div>
                        <div class="kpi-subtitle">Disponível para uso</div>
                    </div>
                </div>
                
                <div class="kpi-card transactions">
                    <div class="kpi-header">
                        <div class="kpi-icon">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div class="kpi-trend positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>+24.1%</span>
                        </div>
                    </div>
                    <div class="kpi-content">
                        <div class="kpi-value"><?php echo formatCurrency($movimentacoesStats['usos_periodo'] ?? 0); ?></div>
                        <div class="kpi-label">Saldo Utilizado</div>
                        <div class="kpi-subtitle">Transações no período</div>
                    </div>
                </div>
            </div>
            
            <!-- Gráficos e Análises -->
            <div class="analytics-grid">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>Evolução da Receita</h3>
                        <div class="chart-controls">
                            <button class="chart-control active" data-period="month">Mensal</button>
                            <button class="chart-control" data-period="week">Semanal</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="revenueChart" width="400" height="200"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>Distribuição de Cashback</h3>
                        <div class="chart-info">
                            <i class="fas fa-info-circle" title="Comparativo entre cashback pago vs. saldo retido"></i>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="cashbackChart" width="400" height="200"></canvas>
                    </div>
                </div>
                
                <div class="chart-card full-width">
                    <div class="chart-header">
                        <h3>Performance por Loja</h3>
                        <div class="chart-controls">
                            <select class="chart-select" id="storeMetric">
                                <option value="revenue">Receita</option>
                                <option value="cashback">Cashback</option>
                                <option value="transactions">Transações</option>
                            </select>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="storeChart" width="800" height="300"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Métricas Detalhadas -->
            <div class="metrics-section">
                <h2 class="section-title">
                    <i class="fas fa-analytics"></i>
                    Métricas Detalhadas
                </h2>
                
                <div class="metrics-tabs">
                    <button class="tab-button active" onclick="showTab('saldo')">Gestão de Saldo</button>
                    <button class="tab-button" onclick="showTab('movimentacoes')">Movimentações</button>
                    <button class="tab-button" onclick="showTab('performance')">Performance</button>
                    <button class="tab-button" onclick="showTab('clientes')">Clientes</button>
                </div>
                
                <div class="tab-content active" id="saldo-tab">
                    <div class="metric-cards">
                        <div class="metric-card">
                            <div class="metric-icon saldo">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="metric-info">
                                <div class="metric-value"><?php echo number_format($saldoStats['total_clientes_com_saldo'] ?? 0, 0, ',', '.'); ?></div>
                                <div class="metric-label">Clientes com Saldo</div>
                                <div class="metric-change positive">+5.2% vs. mês anterior</div>
                            </div>
                        </div>
                        
                        <div class="metric-card">
                            <div class="metric-icon media">
                                <i class="fas fa-calculator"></i>
                            </div>
                            <div class="metric-info">
                                <div class="metric-value"><?php echo formatCurrency($saldoStats['media_saldo_por_cliente'] ?? 0); ?></div>
                                <div class="metric-label">Saldo Médio por Cliente</div>
                                <div class="metric-change neutral">0.0% vs. mês anterior</div>
                            </div>
                        </div>
                        
                        <div class="metric-card">
                            <div class="metric-icon historico">
                                <i class="fas fa-history"></i>
                            </div>
                            <div class="metric-info">
                                <div class="metric-value"><?php echo formatCurrency($saldoStats['total_saldo_creditado'] ?? 0); ?></div>
                                <div class="metric-label">Total Creditado</div>
                                <div class="metric-change positive">+18.3% vs. mês anterior</div>
                            </div>
                        </div>
                        
                        <div class="metric-card">
                            <div class="metric-icon usado">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="metric-info">
                                <div class="metric-value"><?php echo formatCurrency($saldoStats['total_saldo_usado'] ?? 0); ?></div>
                                <div class="metric-label">Total Usado</div>
                                <div class="metric-change positive">+22.7% vs. mês anterior</div>
                            </div>
                        </div>
                    </div>
                </div>
            
                <div class="tab-content" id="movimentacoes-tab">
                    <div class="movement-summary">
                        <div class="movement-card creditos">
                            <div class="movement-header">
                                <i class="fas fa-plus-circle"></i>
                                <span>Créditos</span>
                            </div>
                            <div class="movement-value"><?php echo formatCurrency($movimentacoesStats['creditos_periodo'] ?? 0); ?></div>
                            <div class="movement-detail"><?php echo $movimentacoesStats['qtd_creditos'] ?? 0; ?> operações</div>
                            <div class="movement-progress">
                                <div class="progress-bar" style="width: 75%"></div>
                            </div>
                        </div>
                        
                        <div class="movement-card usos">
                            <div class="movement-header">
                                <i class="fas fa-minus-circle"></i>
                                <span>Usos</span>
                            </div>
                            <div class="movement-value"><?php echo formatCurrency($movimentacoesStats['usos_periodo'] ?? 0); ?></div>
                            <div class="movement-detail"><?php echo $movimentacoesStats['qtd_usos'] ?? 0; ?> operações</div>
                            <div class="movement-progress">
                                <div class="progress-bar" style="width: 60%"></div>
                            </div>
                        </div>
                        
                        <div class="movement-card estornos">
                            <div class="movement-header">
                                <i class="fas fa-undo"></i>
                                <span>Estornos</span>
                            </div>
                            <div class="movement-value"><?php echo formatCurrency($movimentacoesStats['estornos_periodo'] ?? 0); ?></div>
                            <div class="movement-detail"><?php echo $movimentacoesStats['qtd_estornos'] ?? 0; ?> operações</div>
                            <div class="movement-progress">
                                <div class="progress-bar" style="width: 15%"></div>
                            </div>
                        </div>
                        
                        <div class="movement-card liquido">
                            <div class="movement-header">
                                <i class="fas fa-balance-scale"></i>
                                <span>Saldo Líquido</span>
                            </div>
                            <div class="movement-value">
                                <?php 
                                $saldoLiquido = ($movimentacoesStats['creditos_periodo'] ?? 0) - ($movimentacoesStats['usos_periodo'] ?? 0) + ($movimentacoesStats['estornos_periodo'] ?? 0);
                                echo formatCurrency($saldoLiquido);
                                ?>
                            </div>
                            <div class="movement-detail">Resultado do período</div>
                            <div class="movement-progress">
                                <div class="progress-bar <?php echo $saldoLiquido >= 0 ? 'positive' : 'negative'; ?>" style="width: <?php echo abs($saldoLiquido) > 0 ? min(100, abs($saldoLiquido) / 1000 * 100) : 0; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="tab-content" id="performance-tab">
                    <div class="performance-grid">
                        <div class="performance-metric">
                            <div class="perf-icon">
                                <i class="fas fa-percentage"></i>
                            </div>
                            <div class="perf-data">
                                <div class="perf-value">5.2%</div>
                                <div class="perf-label">Taxa de Conversão</div>
                            </div>
                        </div>
                        <div class="performance-metric">
                            <div class="perf-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="perf-data">
                                <div class="perf-value">3.2</div>
                                <div class="perf-label">Dias Médios p/ Uso</div>
                            </div>
                        </div>
                        <div class="performance-metric">
                            <div class="perf-icon">
                                <i class="fas fa-repeat"></i>
                            </div>
                            <div class="perf-data">
                                <div class="perf-value">68%</div>
                                <div class="perf-label">Taxa de Retenção</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="tab-content" id="clientes-tab">
                    <div class="client-insights">
                        <div class="insight-card">
                            <h4>Segmentação por Saldo</h4>
                            <div class="segment-list">
                                <div class="segment">
                                    <span class="segment-label">Alto valor (>R$100)</span>
                                    <span class="segment-value">152 clientes</span>
                                </div>
                                <div class="segment">
                                    <span class="segment-label">Médio valor (R$25-100)</span>
                                    <span class="segment-value">418 clientes</span>
                                </div>
                                <div class="segment">
                                    <span class="segment-label">Baixo valor (<R$25)</span>
                                    <span class="segment-value">291 clientes</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Comparação Vendas Originais vs Líquidas -->
            <h2 class="section-title">Vendas Originais X Vendas Líquidas (com desconto de saldo)</h2>
            <div class="table-container">
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Mês</th>
                            <th>Vendas Originais</th>
                            <th>Saldo Usado</th>
                            <th>Vendas Líquidas</th>
                            <th>% Desconto Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($vendasComparacao)): ?>
                            <tr>
                                <td colspan="5" class="no-data">Nenhum dado disponível para o período selecionado</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($vendasComparacao as $data): ?>
                                <?php
                                $vendasOriginais = $data['vendas_originais'] ?? 0;
                                $saldoUsado = $data['total_saldo_usado_mes'] ?? 0;
                                $vendasLiquidas = $data['vendas_liquidas'] ?? 0;
                                $percentualDesconto = $vendasOriginais > 0 ? ($saldoUsado / $vendasOriginais) * 100 : 0;
                                ?>
                                <tr>
                                    <td><?php echo formatMonth($data['mes']); ?></td>
                                    <td><?php echo formatCurrency($vendasOriginais); ?></td>
                                    <td><?php echo formatCurrency($saldoUsado); ?></td>
                                    <td><?php echo formatCurrency($vendasLiquidas); ?></td>
                                    <td><?php echo formatPercentage($percentualDesconto); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Ranking de Lojas por Uso de Saldo -->
            <h2 class="section-title">Top 10 Lojas - Uso de Saldo</h2>
            <div class="table-container">
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Loja</th>
                            <th>Saldo Atual</th>
                            <th>Total Creditado</th>
                            <th>Total Usado</th>
                            <th>% de Uso</th>
                            <th>Clientes com Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lojasSaldo)): ?>
                            <tr>
                                <td colspan="6" class="no-data">Nenhuma loja com movimentação de saldo</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lojasSaldo as $loja): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($loja['nome_fantasia']); ?></td>
                                    <td><?php echo formatCurrency($loja['saldo_atual']); ?></td>
                                    <td><?php echo formatCurrency($loja['total_creditado']); ?></td>
                                    <td><?php echo formatCurrency($loja['total_usado']); ?></td>
                                    <td><?php echo formatPercentage($loja['percentual_uso']); ?></td>
                                    <td><?php echo number_format($loja['clientes_com_saldo'], 0, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Tabela: Cashback Pago X Lucro -->
            <h2 class="section-title">Cashback Pago X Receita</h2>
            <div class="table-container">
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Mês</th>
                            <th>Cashback Pago</th>
                            <th>Receita</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($monthlyData)): ?>
                            <tr>
                                <td colspan="3" class="no-data">Nenhum dado disponível para o período selecionado</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($monthlyData as $data): ?>
                                <tr>
                                    <td><?php echo formatMonth($data['mes']); ?></td>
                                    <td><?php echo formatCurrency($data['total_cashback']); ?></td>
                                    <td><?php echo formatCurrency($data['comissao_admin']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal de Filtro de Data -->
    <div id="dateFilterModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Filtrar por Período</h2>
                <button class="close-button" onclick="toggleDateFilter()">&times;</button>
            </div>
            
            <form id="dateFilterForm" action="" method="get">
                <div class="form-group">
                    <label class="form-label" for="dataInicio">Data Inicial</label>
                    <input type="date" class="form-control" id="dataInicio" name="data_inicio" value="<?php echo $filters['data_inicio'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="dataFim">Data Final</label>
                    <input type="date" class="form-control" id="dataFim" name="data_fim" value="<?php echo $filters['data_fim'] ?? ''; ?>">
                </div>
                
                <div class="form-buttons">
                    <button type="button" class="btn btn-secondary" onclick="clearFilters()">Limpar</button>
                    <button type="submit" class="btn btn-primary">Aplicar</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Funções de controle de filtro
        function toggleDateFilter() {
            const modal = document.getElementById('dateFilterModal');
            modal.classList.toggle('active');
        }
        
        function clearFilters() {
            document.getElementById('dataInicio').value = '';
            document.getElementById('dataFim').value = '';
        }
        
        function setQuickFilter(period) {
            // Remove active de todos os chips
            document.querySelectorAll('.filter-chip').forEach(chip => chip.classList.remove('active'));
            event.target.classList.add('active');
            
            const today = new Date();
            let startDate = new Date();
            
            switch(period) {
                case 'today':
                    startDate = new Date();
                    break;
                case 'week':
                    startDate.setDate(today.getDate() - 7);
                    break;
                case 'month':
                    startDate.setMonth(today.getMonth() - 1);
                    break;
                case 'quarter':
                    startDate.setMonth(today.getMonth() - 3);
                    break;
                case 'year':
                    startDate.setFullYear(today.getFullYear() - 1);
                    break;
            }
            
            if (period !== 'custom') {
                // Aplicar filtro e recarregar dados
                window.location.href = `?data_inicio=${startDate.toISOString().split('T')[0]}&data_fim=${today.toISOString().split('T')[0]}`;
            }
        }
        
        function refreshData() {
            const btn = document.querySelector('.btn-refresh i');
            btn.classList.add('fa-spin');
            setTimeout(() => {
                window.location.reload();
            }, 500);
        }
        
        function exportReport() {
            alert('Funcionalidade de exportação em desenvolvimento');
        }
        
        function showTab(tabName) {
            // Remove active de todas as tabs
            document.querySelectorAll('.tab-button').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // Ativa a tab selecionada
            event.target.classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');
        }
        
        // Inicializar gráficos
        document.addEventListener('DOMContentLoaded', function() {
            initCharts();
        });
        
        function initCharts() {
            // Gráfico de Receita
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'],
                    datasets: [{
                        label: 'Receita',
                        data: [1200, 1900, 3000, 5000, 4200, 6000],
                        borderColor: '#FF7A00',
                        backgroundColor: 'rgba(255, 122, 0, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
            
            // Gráfico de Cashback
            const cashbackCtx = document.getElementById('cashbackChart').getContext('2d');
            new Chart(cashbackCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Pago', 'Em Carteira', 'Usado'],
                    datasets: [{
                        data: [65, 25, 10],
                        backgroundColor: ['#FF7A00', '#4CAF50', '#2196F3'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        }
                    }
                }
            });
            
            // Gráfico de Performance por Loja
            const storeCtx = document.getElementById('storeChart').getContext('2d');
            new Chart(storeCtx, {
                type: 'bar',
                data: {
                    labels: ['Loja A', 'Loja B', 'Loja C', 'Loja D', 'Loja E'],
                    datasets: [{
                        label: 'Receita',
                        data: [3000, 2500, 4000, 1800, 3200],
                        backgroundColor: '#FF7A00',
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
        
        // Controle de modal
        window.onclick = function(event) {
            const modal = document.getElementById('dateFilterModal');
            if (event.target == modal) {
                modal.classList.remove('active');
            }
        }
    </script>
</body>
</html>