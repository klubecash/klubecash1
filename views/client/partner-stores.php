<?php
// views/client/partner-stores.php
// VERSÃO CORRIGIDA - SEM DUPLICAÇÃO E COM PRG PARA EVITAR REENVIO DE FORMULÁRIO

// Definir o menu ativo
$activeMenu = 'lojas';

// Incluir arquivos necessários
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../controllers/AuthController.php';
require_once '../../controllers/ClientController.php';

// Iniciar sessão
session_start();

// Verificar se o usuário está logado e é cliente
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== USER_TYPE_CLIENT) {
    header("Location: ../auth/login.php?error=acesso_restrito");
    exit;
}

// Obter dados do usuário
$userId = $_SESSION['user_id'];

// Processar adição/remoção de favoritos (AGORA COM PRG)
if (isset($_POST['toggle_favorite'])) {
    $storeId = isset($_POST['store_id']) ? (int)$_POST['store_id'] : 0;
    $isFavorite = isset($_POST['is_favorite']) ? (int)$_POST['is_favorite'] : 0;

    $favoriteResult = ClientController::toggleFavoriteStore($userId, $storeId, !$isFavorite);

    // Armazenar a mensagem na sessão para exibir após o redirecionamento
    $_SESSION['favorite_message'] = $favoriteResult['message'];
    $_SESSION['favorite_message_type'] = $favoriteResult['status'] ? 'success' : 'error'; // Adicionar tipo para CSS

    // Redirecionar para a mesma página via GET
    $currentQueryString = '';
    if (!empty($_SERVER['QUERY_STRING'])) {
        // Remover parâmetros POST se existirem (embora não deveriam após POST)
        $params = [];
        parse_str($_SERVER['QUERY_STRING'], $params);
        unset($params['toggle_favorite']); // Remover o parâmetro do POST para não confundir
        unset($params['store_id']);
        unset($params['is_favorite']);
        $currentQueryString = http_build_query($params);
    }
    
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?') . (!empty($currentQueryString) ? '?' . $currentQueryString : ''));
    exit;
}

// Recuperar mensagem da sessão se existir e limpar
$favoriteMessage = '';
$favoriteMessageType = '';
if (isset($_SESSION['favorite_message'])) {
    $favoriteMessage = $_SESSION['favorite_message'];
    $favoriteMessageType = $_SESSION['favorite_message_type'];
    unset($_SESSION['favorite_message']); // Limpar a mensagem da sessão
    unset($_SESSION['favorite_message_type']);
}


// Definir valores padrão para filtros e paginação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$filters = [];

// Processar filtros se submetidos
if (isset($_GET['filtrar'])) {
    if (!empty($_GET['categoria']) && $_GET['categoria'] != 'todas') {
        $filters['categoria'] = $_GET['categoria'];
    }

    if (!empty($_GET['nome'])) {
        $filters['nome'] = $_GET['nome'];
    }

    if (!empty($_GET['cashback_min'])) {
        $filters['cashback_min'] = $_GET['cashback_min'];
    }

    if (!empty($_GET['ordenar']) && $_GET['ordenar'] != 'nome') {
        $filters['ordenar'] = $_GET['ordenar'];
    }
}

try {
    $db = Database::getConnection();

    // Obter dados das lojas parceiras
    $result = ClientController::getPartnerStores($userId, $filters, $page);

    // Verificar se houve erro
    $hasError = !$result['status'];
    $errorMessage = $hasError ? $result['message'] : '';

    // Dados para exibição
    $storesData = $hasError ? [] : $result['data'];

    // Obter estatísticas gerais do saldo do cliente
    if (!$hasError) {
        $estatisticasQuery = "
            SELECT
                COUNT(DISTINCT cs.loja_id) as lojas_com_saldo,
                COALESCE(SUM(cs.saldo_disponivel), 0) as total_saldo_disponivel,
                COALESCE(SUM(cs.total_usado), 0) as total_usado_geral,
                COUNT(DISTINCT CASE WHEN cs.saldo_disponivel > 0 THEN cs.loja_id END) as lojas_saldo_disponivel
            FROM cashback_saldos cs
            WHERE cs.usuario_id = ?
        ";
        $estatisticasStmt = $db->prepare($estatisticasQuery);
        $estatisticasStmt->execute([$userId]);
        $estatisticasGerais = $estatisticasStmt->fetch(PDO::FETCH_ASSOC);

        // Se não houver dados, definir valores padrão
        if (!$estatisticasGerais || $estatisticasGerais['lojas_com_saldo'] === null) {
            $estatisticasGerais = [
                'lojas_com_saldo' => 0,
                'total_saldo_disponivel' => 0,
                'total_usado_geral' => 0,
                'lojas_saldo_disponivel' => 0
            ];
        }

        // ENRIQUECER DADOS DAS LOJAS COM SALDO DO CLIENTE (CORRIGIDO)
        if (!empty($storesData['lojas'])) {
            foreach ($storesData['lojas'] as &$loja) {
                // Buscar saldo específico desta loja para o cliente
                $saldoQuery = "
                    SELECT
                        saldo_disponivel,
                        total_creditado,
                        total_usado
                    FROM cashback_saldos
                    WHERE usuario_id = ? AND loja_id = ?
                ";

                $saldoStmt = $db->prepare($saldoQuery);
                $saldoStmt->execute([$userId, $loja['id']]);
                $saldoInfo = $saldoStmt->fetch(PDO::FETCH_ASSOC);

                if ($saldoInfo) {
                    $loja['saldo_disponivel'] = $saldoInfo['saldo_disponivel'];
                    $loja['total_creditado'] = $saldoInfo['total_creditado'];
                    $loja['total_usado'] = $saldoInfo['total_usado'];
                } else {
                    $loja['saldo_disponivel'] = 0;
                    $loja['total_creditado'] = 0;
                    $loja['total_usado'] = 0;
                }

                // Buscar último uso (CORRIGIDO)
                $ultimoUsoQuery = "
                    SELECT MAX(data_operacao) as ultima_data
                    FROM cashback_movimentacoes
                    WHERE usuario_id = ? AND loja_id = ? AND tipo_operacao = 'uso'
                ";
                $ultimoUsoStmt = $db->prepare($ultimoUsoQuery);
                $ultimoUsoStmt->execute([$userId, $loja['id']]);
                $ultimoUsoInfo = $ultimoUsoStmt->fetch(PDO::FETCH_ASSOC);
                $loja['ultimo_uso'] = $ultimoUsoInfo['ultima_data'] ?? null;

                // Buscar total de usos
                $totalUsosQuery = "
                    SELECT COUNT(*) as total_usos
                    FROM cashback_movimentacoes
                    WHERE usuario_id = ? AND loja_id = ? AND tipo_operacao = 'uso'
                ";
                $totalUsosStmt = $db->prepare($totalUsosQuery);
                $totalUsosStmt->execute([$userId, $loja['id']]);
                $totalUsosInfo = $totalUsosStmt->fetch(PDO::FETCH_ASSOC);
                $loja['total_usos'] = $totalUsosInfo['total_usos'] ?? 0;
            }
        }
    } else {
        $estatisticasGerais = [
            'lojas_com_saldo' => 0,
            'total_saldo_disponivel' => 0,
            'total_usado_geral' => 0,
            'lojas_saldo_disponivel' => 0
        ];
    }

} catch (Exception $e) {
    error_log('Erro ao carregar lojas parceiras: ' . $e->getMessage());
    $hasError = true;
    $errorMessage = 'Erro ao carregar dados das lojas parceiras.';
    $storesData = [];
    $estatisticasGerais = [
        'lojas_com_saldo' => 0,
        'total_saldo_disponivel' => 0,
        'total_usado_geral' => 0,
        'lojas_saldo_disponivel' => 0
    ];
}


// Função para formatar valor
function formatCurrency($value) {
    return 'R$ ' . number_format($value ?: 0, 2, ',', '.');
}

// Função para formatar data
function formatDate($date) {
    if (!$date) return 'Nunca';
    return date('d/m/Y', strtotime($date));
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lojas Parceiras - Klube Cash</title>
    <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
    <link rel="stylesheet" href="../../assets/css/views/client/partner-stores.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include_once '../components/navbar.php'; ?>

    <div class="page-wrapper" style="margin-top: 80px;">
        <div class="hero-section">
            <div class="container">
                <div class="hero-content">
                    <div class="hero-text">
                        <h1><i class="fas fa-store"></i> Suas Lojas Parceiras</h1>
                        <p>Descubra onde você pode ganhar e usar seu cashback</p>
                    </div>
                    </div>
            </div>
        </div>

        <div class="container">
            <?php if (!empty($favoriteMessage)): ?>
                <div class="toast toast-<?php echo htmlspecialchars($favoriteMessageType); ?>">
                    <i class="fas <?php echo ($favoriteMessageType == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($favoriteMessage); ?>
                </div>
            <?php endif; ?>

            <?php if ($hasError): ?>
                <div class="toast toast-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php else: ?>

            <div class="filters-bar">
                <div class="filters-toggle">
                    <button id="toggleFilters" class="btn-filter-toggle">
                        <i class="fas fa-filter"></i>
                        Filtrar Lojas
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>

                <div class="filters-content" id="filtersContent">
                    <form action="" method="GET" class="filter-form">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label>Buscar Loja</label>
                                <div class="search-input">
                                    <i class="fas fa-search"></i>
                                    <input type="text" name="nome" value="<?php echo $filters['nome'] ?? ''; ?>" placeholder="Digite o nome da loja">
                                </div>
                            </div>

                            <div class="filter-group">
                                <label>Categoria</label>
                                <select name="categoria" class="filter-select">
                                    <option value="todas">Todas</option>
                                    <?php if (!empty($storesData['categorias'])): ?>
                                        <?php foreach ($storesData['categorias'] as $categoria): ?>
                                            <option value="<?php echo htmlspecialchars($categoria); ?>" <?php echo (isset($filters['categoria']) && $filters['categoria'] == $categoria) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($categoria); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label>Ordenar</label>
                                <select name="ordenar" class="filter-select">
                                    <option value="nome">Nome</option>
                                    <option value="cashback" <?php echo (isset($filters['ordenar']) && $filters['ordenar'] == 'cashback') ? 'selected' : ''; ?>>% Cashback</option>
                                    <option value="categoria" <?php echo (isset($filters['ordenar']) && $filters['ordenar'] == 'categoria') ? 'selected' : ''; ?>>Categoria</option>
                                </select>
                            </div>

                            <div class="filter-actions">
                                <button type="submit" name="filtrar" value="1" class="btn-apply-filter">
                                    <i class="fas fa-search"></i>
                                    Aplicar
                                </button>
                                <a href="?" class="btn-clear-filter">
                                    <i class="fas fa-times"></i>
                                    Limpar
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="stores-section">
                <div class="section-header">
                    <div class="section-title">
                        <h2>Suas Lojas Disponíveis</h2>
                        <span class="store-count"><?php echo $storesData['estatisticas']['total_lojas'] ?? 0; ?> lojas encontradas</span>
                    </div>

                    <?php if (!empty($storesData['lojas'])): ?>
                    <div class="quick-stats">
                        <div class="quick-stat">
                            <span class="stat-number">
                                <?php echo number_format(($storesData['estatisticas']['media_cashback'] ?? 0) - 5, 1); ?>%
                            </span>
                            <span class="stat-desc">Cashback médio</span>
                        </div>
                        <div class="quick-stat">
                            <span class="stat-number">
                                <?php echo number_format(($storesData['estatisticas']['maior_cashback'] ?? 0) - 5, 1); ?>%
                            </span>
                            <span class="stat-desc">Maior cashback</span>
                        </div>
                    </div>

                    <?php endif; ?>
                </div>

                <div class="stores-grid">
                    <?php if (empty($storesData['lojas'])): ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-store-slash"></i>
                            </div>
                            <h3>Nenhuma loja encontrada</h3>
                            <p>Tente ajustar os filtros ou remover algumas opções de busca</p>
                            <a href="?" class="btn-primary">Ver Todas as Lojas</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($storesData['lojas'] as $loja): ?>
                            <div class="store-card <?php echo $loja['saldo_disponivel'] > 0 ? 'has-balance' : ''; ?>">
                                <div class="store-card-header">
                                    <div class="store-avatar">
                                        <span><?php echo strtoupper(substr($loja['nome_fantasia'], 0, 2)); ?></span>
                                    </div>

                                    <div class="store-info">
                                        <h3 class="store-name"><?php echo htmlspecialchars($loja['nome_fantasia']); ?></h3>
                                        <span class="store-category">
                                            <i class="fas fa-tag"></i>
                                            <?php echo htmlspecialchars($loja['categoria']); ?>
                                        </span>
                                    </div>

                                    <form method="POST" class="favorite-form">
                                        <input type="hidden" name="store_id" value="<?php echo $loja['id']; ?>">
                                        <input type="hidden" name="is_favorite" value="<?php echo $loja['is_favorite'] ?? 0; ?>">
                                        <button type="submit" name="toggle_favorite" class="btn-favorite <?php echo (!empty($loja['is_favorite'])) ? 'favorited' : ''; ?>" title="<?php echo (!empty($loja['is_favorite'])) ? 'Remover dos favoritos' : 'Adicionar aos favoritos'; ?>">
                                            <i class="<?php echo (!empty($loja['is_favorite'])) ? 'fas fa-heart' : 'far fa-heart'; ?>"></i>
                                        </button>
                                    </form>
                                </div>

                                <div class="cashback-highlight">
                                    <div class="cashback-percentage">
                                        <span class="percentage">
                                            <?php echo number_format($loja['porcentagem_cashback'] - 5, 1); ?>%
                                        </span>

                                        <span class="label">de cashback</span>
                                    </div>
                                    <div class="cashback-explanation">
                                        <i class="fas fa-info-circle"></i>
                                        <span>Você ganha <?php echo number_format($loja['porcentagem_cashback'] / 2, 1); ?>% do valor da compra</span>
                                    </div>
                                </div>

                                <?php if ($loja['saldo_disponivel'] > 0): ?>
                                    <div class="balance-status available">
                                        <div class="balance-icon">
                                            <i class="fas fa-coins"></i>
                                        </div>
                                        <div class="balance-info">
                                            <span class="balance-label">Saldo Disponível</span>
                                            <span class="balance-amount"><?php echo formatCurrency($loja['saldo_disponivel']); ?></span>
                                        </div>
                                        <button class="btn-use-balance" onclick="usarSaldo(<?php echo $loja['id']; ?>, '<?php echo htmlspecialchars($loja['nome_fantasia']); ?>', <?php echo $loja['saldo_disponivel']; ?>)">
                                            <i class="fas fa-shopping-cart"></i>
                                            Usar Agora
                                        </button>
                                    </div>
                                <?php elseif ($loja['cashback_pendente'] > 0): ?>
                                    <div class="balance-status pending">
                                        <div class="balance-icon">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <div class="balance-info">
                                            <span class="balance-label">Cashback Pendente</span>
                                            <span class="balance-amount"><?php echo formatCurrency($loja['cashback_pendente']); ?></span>
                                        </div>
                                        <span class="status-badge pending">Aguardando</span>
                                    </div>
                                <?php elseif ($loja['total_usado'] > 0): ?>
                                    <div class="balance-status used">
                                        <div class="balance-icon">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div class="balance-info">
                                            <span class="balance-label">Já Utilizado</span>
                                            <span class="balance-amount"><?php echo formatCurrency($loja['total_usado']); ?></span>
                                        </div>
                                        <span class="usage-detail"><?php echo $loja['total_usos']; ?> vezes</span>
                                    </div>
                                <?php else: ?>
                                    <div class="balance-status none">
                                        <div class="balance-icon">
                                            <i class="fas fa-shopping-bag"></i>
                                        </div>
                                        <div class="balance-info">
                                            <span class="balance-label">Comece a Comprar</span>
                                            <span class="balance-description">Ganhe cashback nesta loja</span>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="card-actions">
                                    <button class="btn-secondary" onclick="verDetalhes(<?php echo $loja['id']; ?>)">
                                        <i class="fas fa-info-circle"></i>
                                        Ver Detalhes
                                    </button>

                                    <?php if ($loja['ultimo_uso']): ?>
                                        <div class="last-use">
                                            <i class="fas fa-calendar-alt"></i>
                                            Último uso: <?php echo formatDate($loja['ultimo_uso']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($storesData['paginacao']) && $storesData['paginacao']['total_paginas'] > 1): ?>
                <div class="pagination-wrapper">
                    <nav class="pagination">
                        <?php
                        $currentPage = $storesData['paginacao']['pagina_atual'];
                        $totalPages = $storesData['paginacao']['total_paginas'];

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
                                <i class="fas fa-chevron-left"></i>
                                Anterior
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
                                Próximo
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div id="storeModal" class="modal">
        <div class="modal-overlay" onclick="closeModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>Detalhes da Loja</h3>
                <button onclick="closeModal()" class="modal-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="storeDetails">
                    <div class="loading">
                        <i class="fas fa-spinner fa-spin"></i>
                        Carregando informações...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="useBalanceModal" class="modal">
        <div class="modal-overlay" onclick="closeUseBalanceModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>Usar Saldo de Cashback</h3>
                <button onclick="closeUseBalanceModal()" class="modal-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="useBalanceContent">
                    <div class="balance-usage-info">
                        <div class="store-info-modal">
                            <div class="store-avatar-modal">
                                <span id="modalStoreInitials"></span>
                            </div>
                            <div>
                                <h4 id="modalStoreName"></h4>
                                <p>Saldo disponível: <strong id="modalStoreBalance"></strong></p>
                            </div>
                        </div>

                        <div class="usage-instructions">
                            <div class="instruction-item">
                                <i class="fas fa-shopping-cart"></i>
                                <span>Vá até a loja e faça sua compra</span>
                            </div>
                            <div class="instruction-item">
                                <i class="fas fa-mobile-alt"></i>
                                <span>Informe que quer usar saldo do Klube Cash</span>
                            </div>
                            <div class="instruction-item">
                                <i class="fas fa-check-circle"></i>
                                <span>O valor será descontado automaticamente</span>
                            </div>
                        </div>

                        <div class="contact-store">
                            <p><strong>Dica:</strong> Entre em contato com a loja antes de ir para confirmar que aceita o uso do saldo Klube Cash.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // JavaScript permanece igual
        // Toggle dos filtros
        document.getElementById('toggleFilters').addEventListener('click', function() {
            const content = document.getElementById('filtersContent');
            const icon = this.querySelector('.fa-chevron-down');

            content.classList.toggle('active');
            icon.style.transform = content.classList.contains('active') ? 'rotate(180deg)' : 'rotate(0deg)';
        });

        // Função para exibir detalhes da loja
        function verDetalhes(storeId) {
            document.getElementById('storeModal').classList.add('active');

            // Simular carregamento de dados (em produção, faria uma chamada AJAX)
            setTimeout(() => {
                document.getElementById('storeDetails').innerHTML = `
                    <div class="store-details-content">
                        <div class="detail-section">
                            <h4><i class="fas fa-chart-line"></i> Histórico de Cashback</h4>
                            <p>Aqui você verá todo o histórico de cashback ganho nesta loja, incluindo valores pendentes e já utilizados.</p>
                        </div>

                        <div class="detail-section">
                            <h4><i class="fas fa-clock"></i> Movimentações Recentes</h4>
                            <p>Últimas 10 movimentações de cashback desta loja aparecerão aqui.</p>
                        </div>

                        <div class="detail-section">
                            <h4><i class="fas fa-info-circle"></i> Informações da Loja</h4>
                            <p>Dados de contato, endereço e outras informações relevantes.</p>
                        </div>

                        <div class="coming-soon">
                            <i class="fas fa-tools"></i>
                            <p>Esta funcionalidade está sendo desenvolvida e estará disponível em breve!</p>
                        </div>
                    </div>
                `;
            }, 500);
        }

        // Função para usar saldo
        function usarSaldo(storeId, storeName, balance) {
            document.getElementById('useBalanceModal').classList.add('active');

            // Preencher informações do modal
            document.getElementById('modalStoreInitials').textContent = storeName.substring(0, 2).toUpperCase();
            document.getElementById('modalStoreName').textContent = storeName;
            document.getElementById('modalStoreBalance').textContent = 'R$ ' + balance.toFixed(2).replace('.', ',');
        }

        // Função para fechar modal principal
        function closeModal() {
            document.getElementById('storeModal').classList.remove('active');
        }

        // Função para fechar modal de uso de saldo
        function closeUseBalanceModal() {
            document.getElementById('useBalanceModal').classList.remove('active');
        }

        // Auto-hide para toasts
        document.addEventListener('DOMContentLoaded', function() {
            const toasts = document.querySelectorAll('.toast');
            toasts.forEach(toast => {
                setTimeout(() => {
                    toast.style.opacity = '0';
                    setTimeout(() => toast.remove(), 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>