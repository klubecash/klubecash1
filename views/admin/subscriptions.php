<?php
/**
 * Admin - Gerenciamento de Assinaturas
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../controllers/SubscriptionController.php';

session_start();

// Verificar autenticação
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ' . LOGIN_URL);
    exit;
}

// Mensagens de erro/sucesso via URL
$errorMessage = $_GET['error'] ?? '';
$successMessage = $_GET['message'] ?? '';

try {
    $db = (new Database())->getConnection();
    $subscriptionController = new SubscriptionController($db);

    // Filtros
    $statusFilter = $_GET['status'] ?? '';
    $searchTerm = $_GET['search'] ?? '';

    // Buscar assinaturas
    $filters = [];
    if ($statusFilter) {
        $filters['status'] = $statusFilter;
    }

    $subscriptions = $subscriptionController->listSubscriptions($filters);
} catch (Exception $e) {
    error_log("Erro em subscriptions.php: " . $e->getMessage());
    $subscriptions = [];
    $error = "Erro ao carregar assinaturas: " . $e->getMessage();
}

// Filtrar por busca (nome da loja)
if ($searchTerm) {
    $subscriptions = array_filter($subscriptions, function($sub) use ($searchTerm) {
        return stripos($sub['nome_fantasia'], $searchTerm) !== false;
    });
}

$activeMenu = 'assinaturas';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assinaturas - <?php echo SYSTEM_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <?php include __DIR__ . '/../components/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <div>
                <h1>Gerenciar Assinaturas</h1>
                <p>Visualize e gerencie assinaturas das lojas</p>
            </div>
            <button onclick="toggleAssignForm()" class="btn btn-success">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 5px;">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Nova Assinatura
            </button>
        </div>

        <?php if (isset($error) || $errorMessage): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error ?? $errorMessage); ?>
            </div>
        <?php endif; ?>

        <?php if ($successMessage): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>

        <!-- Formulário de Nova Assinatura (oculto por padrão) -->
        <div id="assignForm" class="assign-form-card" style="display: none;">
            <h3>Atribuir Plano a uma Loja</h3>
            <form method="GET" action="<?php echo SITE_URL; ?>/admin/store-subscription" class="search-store-form">
                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label>Buscar Loja por Nome ou Email</label>
                        <input type="text" id="storeSearch" placeholder="Digite o nome ou email da loja" autocomplete="off">
                        <div id="storeResults" class="search-results"></div>
                    </div>
                </div>
                <p class="help-text">Digite o nome ou email da loja para buscar. Clique em uma loja para atribuir um plano.</p>
            </form>
        </div>

        <!-- Filtros -->
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status" onchange="this.form.submit()">
                        <option value="">Todos</option>
                        <option value="trial" <?php echo $statusFilter === 'trial' ? 'selected' : ''; ?>>Trial</option>
                        <option value="ativa" <?php echo $statusFilter === 'ativa' ? 'selected' : ''; ?>>Ativa</option>
                        <option value="inadimplente" <?php echo $statusFilter === 'inadimplente' ? 'selected' : ''; ?>>Inadimplente</option>
                        <option value="cancelada" <?php echo $statusFilter === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                        <option value="suspensa" <?php echo $statusFilter === 'suspensa' ? 'selected' : ''; ?>>Suspensa</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Buscar Loja</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Nome da loja">
                </div>
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="<?php echo ADMIN_SUBSCRIPTIONS_URL; ?>" class="btn btn-secondary">Limpar</a>
            </form>
        </div>

        <!-- Tabela de Assinaturas -->
        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Loja</th>
                        <th>Plano</th>
                        <th>Status</th>
                        <th>Ciclo</th>
                        <th>Trial Até</th>
                        <th>Próximo Vencimento</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subscriptions)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center;">Nenhuma assinatura encontrada</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($subscriptions as $sub): ?>
                            <tr>
                                <td>#<?php echo $sub['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($sub['nome_fantasia'] ?? 'Loja #' . $sub['loja_id']); ?></strong></td>
                                <td><span class="badge badge-info"><?php echo htmlspecialchars($sub['plano_nome']); ?></span></td>
                                <td>
                                    <?php
                                    $statusClasses = [
                                        'trial' => 'badge-warning',
                                        'ativa' => 'badge-success',
                                        'inadimplente' => 'badge-danger',
                                        'cancelada' => 'badge-secondary',
                                        'suspensa' => 'badge-secondary'
                                    ];
                                    $badgeClass = $statusClasses[$sub['status']] ?? 'badge-secondary';
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($sub['status']); ?></span>
                                </td>
                                <td><?php echo $sub['ciclo'] === 'monthly' ? 'Mensal' : 'Anual'; ?></td>
                                <td><?php echo $sub['trial_end'] ? date('d/m/Y', strtotime($sub['trial_end'])) : '-'; ?></td>
                                <td><?php echo $sub['next_invoice_date'] ? date('d/m/Y', strtotime($sub['next_invoice_date'])) : '-'; ?></td>
                                <td>
                                    <a href="<?php echo SITE_URL; ?>/admin/store-subscription?id=<?php echo $sub['id']; ?>" class="btn btn-sm btn-primary">Ver Detalhes</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <style>
        .filters-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .filters-form {
            display: flex;
            gap: 15px;
            align-items: end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        .filter-group label {
            font-size: 14px;
            margin-bottom: 5px;
            color: #555;
        }
        .filter-group select,
        .filter-group input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .table-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .badge-secondary { background: #e2e3e5; color: #383d41; }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary { background: <?php echo PRIMARY_COLOR; ?>; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-sm { padding: 4px 8px; font-size: 13px; }
        .alert { padding: 12px 20px; border-radius: 6px; margin-bottom: 20px; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .main-content {
            margin-left: 280px;
            padding: 20px;
            position: relative;
            z-index: 1;
            min-height: 100vh;
        }

        .page-header {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-header h1 {
            font-size: 28px;
            color: #333;
            margin: 0 0 5px 0;
        }

        .page-header p {
            color: #666;
            font-size: 14px;
            margin: 0;
        }

        .btn-success {
            background: #28a745;
            color: white;
            font-weight: 500;
        }

        .btn-success:hover {
            background: #218838;
        }

        /* Formulário de Atribuição */
        .assign-form-card {
            background: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }

        .assign-form-card h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
        }

        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group {
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }

        .form-group input[type="text"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-group input[type="text"]:focus {
            outline: none;
            border-color: <?php echo PRIMARY_COLOR; ?>;
            box-shadow: 0 0 0 3px rgba(241, 120, 12, 0.1);
        }

        .help-text {
            color: #666;
            font-size: 13px;
            margin: 10px 0 0 0;
        }

        /* Resultados da Busca */
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 4px 4px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: none;
        }

        .search-results.active {
            display: block;
        }

        .search-result-item {
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            transition: background 0.2s;
        }

        .search-result-item:hover {
            background: #f8f9fa;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .store-name {
            font-weight: 500;
            color: #333;
            margin-bottom: 3px;
        }

        .store-email {
            font-size: 13px;
            color: #666;
        }

        .store-status {
            display: inline-block;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 10px;
        }

        .store-status.aprovado {
            background: #d4edda;
            color: #155724;
        }

        .store-status.pendente {
            background: #fff3cd;
            color: #856404;
        }

        .no-results {
            padding: 15px;
            text-align: center;
            color: #666;
            font-size: 14px;
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
                padding-top: 80px; /* Espaço para o toggle button */
            }

            .filters-form {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                width: 100%;
            }

            .table-card {
                overflow-x: auto;
            }

            .data-table {
                min-width: 800px;
            }

            .btn {
                width: 100%;
                margin-top: 10px;
            }

            /* Garantir que sidebar fique acima do conteúdo no mobile */
            .sidebar {
                z-index: 1000 !important;
            }

            .overlay {
                z-index: 999 !important;
            }

            .sidebar-toggle {
                z-index: 1001 !important;
            }
        }
    </style>

    <script>
        // Toggle do formulário de atribuição
        function toggleAssignForm() {
            const form = document.getElementById('assignForm');
            if (form.style.display === 'none') {
                form.style.display = 'block';
                document.getElementById('storeSearch').focus();
            } else {
                form.style.display = 'none';
            }
        }

        // Busca de lojas em tempo real
        let searchTimeout;
        const searchInput = document.getElementById('storeSearch');
        const searchResults = document.getElementById('storeResults');

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();

                if (query.length < 2) {
                    searchResults.classList.remove('active');
                    return;
                }

                searchTimeout = setTimeout(() => {
                    searchStores(query);
                }, 300);
            });

            // Fechar resultados ao clicar fora
            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                    searchResults.classList.remove('active');
                }
            });
        }

        function searchStores(query) {
            fetch(`<?php echo SITE_URL; ?>/api/search-stores.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    displaySearchResults(data);
                })
                .catch(error => {
                    console.error('Erro ao buscar lojas:', error);
                    searchResults.innerHTML = '<div class="no-results">Erro ao buscar lojas</div>';
                    searchResults.classList.add('active');
                });
        }

        function displaySearchResults(stores) {
            if (!stores || stores.length === 0) {
                searchResults.innerHTML = '<div class="no-results">Nenhuma loja encontrada</div>';
                searchResults.classList.add('active');
                return;
            }

            let html = '';
            stores.forEach(store => {
                const statusClass = store.status === 'aprovado' ? 'aprovado' : 'pendente';
                html += `
                    <div class="search-result-item" onclick="selectStore(${store.id})">
                        <div class="store-name">
                            ${escapeHtml(store.nome_fantasia)}
                            <span class="store-status ${statusClass}">${store.status}</span>
                        </div>
                        <div class="store-email">${escapeHtml(store.email)}</div>
                    </div>
                `;
            });

            searchResults.innerHTML = html;
            searchResults.classList.add('active');
        }

        function selectStore(storeId) {
            // Redirecionar para a página de gerenciamento da assinatura dessa loja
            window.location.href = `<?php echo SITE_URL; ?>/admin/store-subscription?loja_id=${storeId}`;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
