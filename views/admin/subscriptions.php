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
        return stripos($sub['nome_loja'], $searchTerm) !== false;
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
            <h1>Gerenciar Assinaturas</h1>
            <p>Visualize e gerencie assinaturas das lojas</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

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
                                <td><strong><?php echo htmlspecialchars($sub['nome_loja'] ?? 'Loja #' . $sub['loja_id']); ?></strong></td>
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
        .main-content { margin-left: 280px; padding: 20px; }
    </style>
</body>
</html>
