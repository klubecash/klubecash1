<?php
// views/admin/stores.php - Início do arquivo com melhor tratamento de erros

$activeMenu = 'lojas';

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../controllers/AuthController.php';
require_once '../../controllers/AdminController.php';

session_start();

// Verificar autenticação e permissão
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== USER_TYPE_ADMIN) {
    header("Location: /views/auth/login.php?error=acesso_restrito");
    exit;
}

// Obter parâmetros
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$category = trim($_GET['category'] ?? '');

// Preparar filtros
$filters = [];
if (!empty($search)) $filters['busca'] = $search;
if (!empty($status)) $filters['status'] = strtolower($status);
if (!empty($category)) $filters['categoria'] = $category;

try {
    // CORRIGIDO: Usar o método correto que funciona
    $result = AdminController::manageStoresWithBalance($filters, $page);
    
    if (!$result['status']) {
        throw new Exception($result['message']);
    }
    
    $stores = $result['data']['lojas'] ?? [];
    $statistics = $result['data']['estatisticas'] ?? [];
    $categories = $result['data']['categorias'] ?? [];
    $pagination = $result['data']['paginacao'] ?? [];
    $hasError = false;
    $errorMessage = '';
    
    // Debug para confirmar (remover depois)
    error_log("STORES.PHP - Total lojas: " . count($stores));
    error_log("STORES.PHP - Estatísticas: " . json_encode($statistics));
    
} catch (Exception $e) {
    error_log("Erro em stores.php: " . $e->getMessage());
    
    // Fallback: NÃO usar manageStores, é o método antigo
    $hasError = true;
    $errorMessage = 'Erro ao carregar dados das lojas: ' . $e->getMessage();
    $stores = [];
    $statistics = [];
    $categories = [];
    $pagination = [];

    
} catch (Exception $e) {
    error_log("Erro em stores.php: " . $e->getMessage());
    
    // Fallback: tentar método mais simples
    try {
        $result = AdminController::manageStoresWithBalance($filters, $page);
        
        if ($result['status']) {
            $stores = $result['data']['lojas'] ?? [];
            $statistics = $result['data']['estatisticas'] ?? [];
            $categories = $result['data']['categorias'] ?? [];
            $pagination = $result['data']['paginacao'] ?? [];
            $hasError = false;
            $errorMessage = '';
        } else {
            throw new Exception($result['message']);
        }
        
    } catch (Exception $e2) {
        $hasError = true;
        $errorMessage = 'Erro ao carregar dados das lojas: ' . $e2->getMessage();
        $stores = [];
        $statistics = [];
        $categories = [];
        $pagination = [];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Lojas - Klube Cash</title>
    <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
    <link rel="stylesheet" href="../../assets/css/views/admin/stores.css">
    <link rel="stylesheet" href="../../assets/css/layout-fix.css">
</head>
<body>
    <?php include_once '../components/sidebar.php'; ?>
    
    <div class="main-content" id="mainContent">
        <div class="page-wrapper">
            <!-- Cabeçalho -->
            <div class="page-header">
                <h1>Gestão de Lojas</h1>
                <button class="btn btn-primary" onclick="showStoreModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Nova Loja
                </button>
            </div>
            
            <?php if ($hasError): ?>
                <div class="alert alert-danger">
                    <strong>Erro:</strong> <?php echo htmlspecialchars($errorMessage); ?>
                    <br>
                    <small>Tentativa automática de recuperação foi realizada. Se o problema persistir, verifique os logs do servidor.</small>
                </div>
            <?php endif; ?>
            
            
            
            <!-- Estatísticas -->
            <?php if (!empty($statistics)): ?>
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon">🏪</div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($statistics['total_lojas'] ?? 0); ?></div>
                        <div class="stat-label">Total de Lojas</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($statistics['lojas_com_saldo'] ?? 0); ?></div>
                        <div class="stat-label">Lojas com Saldo Ativo</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-content">
                        <div class="stat-value">R$ <?php echo number_format($statistics['total_saldo_acumulado'] ?? 0, 2, ',', '.'); ?></div>
                        <div class="stat-label">Saldo Total Acumulado</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">🎯</div>
                    <div class="stat-content">
                        <div class="stat-value">R$ <?php echo number_format($statistics['total_saldo_usado'] ?? 0, 2, ',', '.'); ?></div>
                        <div class="stat-label">Saldo Total Usado</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Filtros -->
            <div class="filters-section">
                <form method="GET" class="filters-form">
                    <div class="search-container">
                        <input type="text" name="search" placeholder="Buscar por nome, email ou CNPJ..." 
                               value="<?php echo htmlspecialchars($search); ?>" class="search-input">
                        <button type="submit" class="search-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                        </button>
                    </div>
                    
                    <div class="filter-group">
                        <select name="status" class="filter-select">
                            <option value="">Todos os Status</option>
                            <option value="aprovado" <?php echo $status === 'aprovado' ? 'selected' : ''; ?>>Aprovado</option>
                            <option value="pendente" <?php echo $status === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                            <option value="rejeitado" <?php echo $status === 'rejeitado' ? 'selected' : ''; ?>>Rejeitado</option>
                        </select>
                        
                        <?php if (!empty($categories)): ?>
                        <select name="category" class="filter-select">
                            <option value="">Todas as Categorias</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst($cat)); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                        
                        <button type="submit" class="btn btn-secondary">Filtrar</button>
                        <a href="?" class="btn btn-outline">Limpar</a>
                    </div>
                </form>
            </div>
            
            <!-- Tabela Principal -->
            <div class="card">
                <div class="card-header">
                    <h3>Lista de Lojas</h3>
                    <div class="bulk-actions">
                        <button class="btn btn-outline" onclick="toggleSelectAll()">Selecionar Todos</button>
                        <button class="btn btn-primary" onclick="bulkApprove()" style="display: none;" id="bulkApproveBtn">Aprovar Selecionadas</button>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="checkbox-col">
                                    <input type="checkbox" id="selectAll" onchange="updateBulkActions()">
                                </th>
                                <th>Loja</th>
                                <th>Contato</th>
                                <th>Categoria</th>
                                <th>Status</th>
                                <th>Saldo de Clientes</th>
                                <th>Taxa de Uso</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($stores)): ?>
                                <tr>
                                    <td colspan="8" class="no-data">
                                        <div class="no-data-content">
                                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                                <circle cx="12" cy="12" r="10"></circle>
                                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                            </svg>
                                            <p>Nenhuma loja encontrada</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($stores as $store): ?>
                                    <tr class="table-row">
                                        <td>
                                            <input type="checkbox" class="store-checkbox" value="<?php echo $store['id']; ?>" onchange="updateBulkActions()">
                                        </td>
                                        <td class="store-info">
                                            <div class="store-name"><?php echo htmlspecialchars($store['nome_fantasia']); ?></div>
                                            <div class="store-details">
                                                CNPJ: <?php echo htmlspecialchars($store['cnpj']); ?>
                                                <?php if ($store['total_saldo_clientes'] > 0): ?>
                                                    <span class="balance-indicator" title="Clientes com saldo">💰</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="contact-info">
                                            <div><?php echo htmlspecialchars($store['email']); ?></div>
                                            <div class="phone"><?php echo htmlspecialchars($store['telefone']); ?></div>
                                        </td>
                                        <td>
                                            <span class="category-badge">
                                                <?php echo htmlspecialchars($store['categoria'] ?? 'Outros'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = '';
                                            $statusText = '';
                                            switch ($store['status']) {
                                                case 'aprovado':
                                                    $statusClass = 'status-approved';
                                                    $statusText = 'Aprovado';
                                                    break;
                                                case 'pendente':
                                                    $statusClass = 'status-pending';
                                                    $statusText = 'Pendente';
                                                    break;
                                                case 'rejeitado':
                                                    $statusClass = 'status-rejected';
                                                    $statusText = 'Rejeitado';
                                                    break;
                                            }
                                            ?>
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </td>
                                        <td class="balance-info">
                                            <?php if ($store['total_saldo_clientes'] > 0): ?>
                                                <div class="balance-amount">R$ <?php echo number_format($store['total_saldo_clientes'], 2, ',', '.'); ?></div>
                                                <div class="balance-clients"><?php echo $store['clientes_com_saldo']; ?> cliente<?php echo $store['clientes_com_saldo'] != 1 ? 's' : ''; ?></div>
                                            <?php else: ?>
                                                <span class="no-balance">Sem saldo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="usage-info">
                                            <?php if ($store['total_transacoes'] > 0): ?>
                                                <?php $percentualUso = ($store['transacoes_com_saldo'] / $store['total_transacoes']) * 100; ?>
                                                <div class="usage-percentage"><?php echo number_format($percentualUso, 1); ?>%</div>
                                                <div class="usage-details"><?php echo $store['transacoes_com_saldo']; ?>/<?php echo $store['total_transacoes']; ?> transações</div>
                                            <?php else: ?>
                                                <span class="no-usage">0%</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="actions">
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-primary" onclick="viewStoreDetails(<?php echo $store['id']; ?>)" title="Ver Detalhes">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                        <circle cx="12" cy="12" r="3"></circle>
                                                    </svg>
                                                </button>
                                                <?php if ($store['status'] === 'pendente'): ?>
                                                    <button class="btn btn-sm btn-success" onclick="approveStore(<?php echo $store['id']; ?>)" title="Aprovar">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <polyline points="20 6 9 17 4 12"></polyline>
                                                        </svg>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="rejectStore(<?php echo $store['id']; ?>)" title="Rejeitar">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <line x1="18" y1="6" x2="6" y2="18"></line>
                                                            <line x1="6" y1="6" x2="18" y2="18"></line>
                                                        </svg>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginação -->
                <?php if (!empty($pagination) && $pagination['total_paginas'] > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Página <?php echo $pagination['pagina_atual']; ?> de <?php echo $pagination['total_paginas']; ?>
                            (<?php echo number_format($pagination['total_itens']); ?> lojas no total)
                        </div>
                        <div class="pagination">
                            <?php
                            $baseUrl = "?search=" . urlencode($search) . "&status=" . urlencode($status) . "&category=" . urlencode($category);
                            $currentPage = $pagination['pagina_atual'];
                            $totalPages = $pagination['total_paginas'];
                            ?>
                            
                            <a href="<?php echo $baseUrl; ?>&page=<?php echo max(1, $currentPage - 1); ?>" 
                               class="pagination-btn <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="15 18 9 12 15 6"></polyline>
                                </svg>
                            </a>
                            
                            <?php
                            $startPage = max(1, $currentPage - 2);
                            $endPage = min($totalPages, $startPage + 4);
                            if ($endPage - $startPage < 4) {
                                $startPage = max(1, $endPage - 4);
                            }
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <a href="<?php echo $baseUrl; ?>&page=<?php echo $i; ?>" 
                                   class="pagination-btn <?php echo $i == $currentPage ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <a href="<?php echo $baseUrl; ?>&page=<?php echo min($totalPages, $currentPage + 1); ?>" 
                               class="pagination-btn <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="9 18 15 12 9 6"></polyline>
                                </svg>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal de Detalhes -->
    <div class="modal" id="storeDetailsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="storeDetailsTitle">Detalhes da Loja</h3>
                <button class="modal-close" onclick="hideStoreDetailsModal()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="modal-body" id="storeDetailsContent">
                <div class="loading">Carregando...</div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="hideStoreDetailsModal()">Fechar</button>
                <button class="btn btn-primary" id="editStoreBtn" onclick="editStore()">Editar</button>
            </div>
        </div>
    </div>
    
    <!-- Modal de Adicionar/Editar -->
    <div class="modal" id="storeModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="storeModalTitle">Nova Loja</h3>
                <button class="modal-close" onclick="hideStoreModal()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <form id="storeForm" onsubmit="submitStoreForm(event)">
                <div class="modal-body">
                    <input type="hidden" id="storeId" name="id">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Nome Fantasia *</label>
                            <input type="text" id="nomeFantasia" name="nome_fantasia" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Razão Social *</label>
                            <input type="text" id="razaoSocial" name="razao_social" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">CNPJ *</label>
                            <input type="text" id="cnpj" name="cnpj" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email *</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Telefone *</label>
                            <input type="text" id="telefone" name="telefone" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Categoria</label>
                            <select id="categoria" name="categoria" class="form-control">
                                <option value="Alimentação">Alimentação</option>
                                <option value="Vestuário">Vestuário</option>
                                <option value="Eletrônicos">Eletrônicos</option>
                                <option value="Beleza">Beleza</option>
                                <option value="Saúde">Saúde</option>
                                <option value="Serviços">Serviços</option>
                                <option value="Outros" selected>Outros</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Porcentagem Cashback (%)</label>
                            <input type="number" step="0.01" min="0" max="20" id="porcentagemCashback" 
                                   name="porcentagem_cashback" class="form-control" value="10.00">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select id="status" name="status" class="form-control">
                                <option value="pendente">Pendente</option>
                                <option value="aprovado">Aprovado</option>
                                <option value="rejeitado">Rejeitado</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideStoreModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../../assets/js/admin/stores.js"></script>
</body>
</html>