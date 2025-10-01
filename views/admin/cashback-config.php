<?php
// views/admin/cashback-config.php
$activeMenu = 'cashback-config';

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../controllers/AuthController.php';

session_start();

// Verificar se o usuário está logado e é administrador
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: " . LOGIN_URL . "?error=acesso_restrito");
    exit;
}

$error = '';
$success = '';
$lojas = [];

// Processar formulário de atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $db = Database::getConnection();
        
        if ($_POST['action'] === 'update_loja') {
            $lojaId = intval($_POST['loja_id']);
            $porcentagemCliente = floatval($_POST['porcentagem_cliente']);
            $porcentagemAdmin = floatval($_POST['porcentagem_admin']);
            $cashbackAtivo = intval($_POST['cashback_ativo']);
            
            // Validações
            if ($porcentagemCliente < 0 || $porcentagemCliente > 50) {
                throw new Exception('Percentual do cliente deve estar entre 0% e 50%');
            }
            if ($porcentagemAdmin < 0 || $porcentagemAdmin > 50) {
                throw new Exception('Percentual da plataforma deve estar entre 0% e 50%');
            }
            if (($porcentagemCliente + $porcentagemAdmin) > 100) {
                throw new Exception('Soma dos percentuais não pode exceder 100%');
            }
            
            // Atualizar a tabela lojas
            $updateStmt = $db->prepare("
                UPDATE lojas 
                SET porcentagem_cliente = :porcentagem_cliente,
                    porcentagem_admin = :porcentagem_admin,
                    cashback_ativo = :cashback_ativo,
                    data_config_cashback = NOW(),
                    porcentagem_cashback = :porcentagem_cashback
                WHERE id = :loja_id
            ");
            
            $updateStmt->bindParam(':loja_id', $lojaId);
            $updateStmt->bindParam(':porcentagem_cliente', $porcentagemCliente);
            $updateStmt->bindParam(':porcentagem_admin', $porcentagemAdmin);
            $updateStmt->bindParam(':cashback_ativo', $cashbackAtivo);
            $updateStmt->bindParam(':porcentagem_cashback', $porcentagemCliente);
            
            if ($updateStmt->execute()) {
                // Atualizar também a tabela configuracoes_cashback global se necessário
                $configStmt = $db->prepare("
                    UPDATE configuracoes_cashback 
                    SET porcentagem_cliente = :porcentagem_cliente,
                        porcentagem_admin = :porcentagem_admin,
                        data_atualizacao = NOW()
                    WHERE id = 1
                ");
                $configStmt->bindParam(':porcentagem_cliente', $porcentagemCliente);
                $configStmt->bindParam(':porcentagem_admin', $porcentagemAdmin);
                $configStmt->execute();
                
                $success = 'Configurações de cashback atualizadas com sucesso!';
            } else {
                throw new Exception('Erro ao atualizar configurações');
            }
        }
        
        if ($_POST['action'] === 'bulk_update') {
            $porcentagemCliente = floatval($_POST['bulk_porcentagem_cliente']);
            $porcentagemAdmin = floatval($_POST['bulk_porcentagem_admin']);
            $aplicarTodas = isset($_POST['aplicar_todas']);
            $aplicarMvp = isset($_POST['aplicar_mvp']);
            $aplicarNormais = isset($_POST['aplicar_normais']);
            
            if (!$aplicarTodas && !$aplicarMvp && !$aplicarNormais) {
                throw new Exception('Selecione pelo menos uma opção para aplicar as configurações');
            }
            
            $whereConditions = [];
            if ($aplicarMvp && !$aplicarNormais) {
                $whereConditions[] = "u.mvp = 'sim'";
            } elseif ($aplicarNormais && !$aplicarMvp) {
                $whereConditions[] = "u.mvp = 'nao'";
            }
            
            $whereClause = '';
            if (!empty($whereConditions)) {
                $whereClause = ' AND ' . implode(' AND ', $whereConditions);
            }
            
            $bulkUpdateStmt = $db->prepare("
                UPDATE lojas l
                JOIN usuarios u ON l.usuario_id = u.id
                SET l.porcentagem_cliente = :porcentagem_cliente,
                    l.porcentagem_admin = :porcentagem_admin,
                    l.data_config_cashback = NOW(),
                    l.porcentagem_cashback = :porcentagem_cashback_bulk
                WHERE l.status = 'aprovado' {$whereClause}
            ");
            
            $bulkUpdateStmt->bindParam(':porcentagem_cliente', $porcentagemCliente);
            $bulkUpdateStmt->bindParam(':porcentagem_admin', $porcentagemAdmin);
            $bulkUpdateStmt->bindParam(':porcentagem_cashback_bulk', $porcentagemCliente);
            
            if ($bulkUpdateStmt->execute()) {
                $affected = $bulkUpdateStmt->rowCount();
                
                // Atualizar também a tabela configuracoes_cashback global
                $configStmt = $db->prepare("
                    UPDATE configuracoes_cashback 
                    SET porcentagem_cliente = :porcentagem_cliente,
                        porcentagem_admin = :porcentagem_admin,
                        data_atualizacao = NOW()
                    WHERE id = 1
                ");
                $configStmt->bindParam(':porcentagem_cliente', $porcentagemCliente);
                $configStmt->bindParam(':porcentagem_admin', $porcentagemAdmin);
                $configStmt->execute();
                
                $success = "Configurações aplicadas a {$affected} loja(s) com sucesso!";
            } else {
                throw new Exception('Erro ao aplicar configurações em lote');
            }
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obter lista de lojas com configurações atuais
try {
    $db = Database::getConnection();
    $lojasStmt = $db->prepare("
        SELECT 
            l.id,
            l.nome_fantasia,
            l.status,
            u.mvp,
            COALESCE(l.porcentagem_cliente, 5.00) as porcentagem_cliente,
            COALESCE(l.porcentagem_admin, 5.00) as porcentagem_admin,
            COALESCE(l.cashback_ativo, 1) as cashback_ativo,
            l.data_config_cashback,
            COUNT(t.id) as total_transacoes,
            COALESCE(SUM(t.valor_total), 0) as volume_vendas
        FROM lojas l
        JOIN usuarios u ON l.usuario_id = u.id
        LEFT JOIN transacoes_cashback t ON l.id = t.loja_id
        WHERE l.status = 'aprovado'
        GROUP BY l.id, l.nome_fantasia, l.status, u.mvp, l.porcentagem_cliente, l.porcentagem_admin, l.cashback_ativo, l.data_config_cashback
        ORDER BY volume_vendas DESC, l.nome_fantasia ASC
    ");
    
    $lojasStmt->execute();
    $lojas = $lojasStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Erro ao carregar dados das lojas: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuração de Cashback - Klube Cash</title>
    <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
    <link rel="stylesheet" href="../../assets/css/views/admin/base.css">
    <style>
        .config-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .config-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .bulk-config {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid #007bff;
        }
        
        .bulk-form {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .form-group label {
            font-weight: 600;
            font-size: 0.9em;
        }
        
        .form-group input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 100px;
        }
        
        .checkbox-group {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: normal;
        }
        
        .stores-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stores-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .stores-table th,
        .stores-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .stores-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .mvp-badge {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .normal-badge {
            background: #6c757d;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .status-active {
            color: #28a745;
            font-weight: 600;
        }
        
        .status-inactive {
            color: #dc3545;
            font-weight: 600;
        }
        
        .config-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .config-form input {
            width: 60px;
            padding: 4px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #1e7e34;
        }
        
        .btn-sm {
            padding: 4px 12px;
            font-size: 0.9em;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .stats-row {
            font-size: 0.9em;
            color: #666;
        }
    </style>
</head>
<body>
    <?php include_once '../components/sidebar.php'; ?>
    
    <div class="main-content" id="mainContent">
        <div class="config-container">
            <div class="config-header">
                <div>
                    <h1>Configuração de Cashback por Loja</h1>
                    <p>Configure os percentuais de cashback individuais para cada loja</p>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <strong>Erro:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <strong>Sucesso:</strong> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <!-- Configuração em Lote -->
            <div class="bulk-config">
                <h3>Configuração em Lote</h3>
                <form method="POST" class="bulk-form">
                    <input type="hidden" name="action" value="bulk_update">
                    
                    <div class="form-group">
                        <label>Cliente (%)</label>
                        <input type="number" name="bulk_porcentagem_cliente" step="0.01" min="0" max="50" value="5.00" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Plataforma (%)</label>
                        <input type="number" name="bulk_porcentagem_admin" step="0.01" min="0" max="50" value="5.00" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Aplicar a:</label>
                        <div class="checkbox-group">
                            <label><input type="checkbox" name="aplicar_todas"> Todas</label>
                            <label><input type="checkbox" name="aplicar_mvp"> Só MVP</label>
                            <label><input type="checkbox" name="aplicar_normais"> Só Normais</label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Aplicar em Lote</button>
                </form>
            </div>
            
            <!-- Tabela de Lojas -->
            <div class="stores-table">
                <table>
                    <thead>
                        <tr>
                            <th>Loja</th>
                            <th>Tipo</th>
                            <th>Status</th>
                            <th>Cliente (%)</th>
                            <th>Plataforma (%)</th>
                            <th>Total (%)</th>
                            <th>Ativo</th>
                            <th>Estatísticas</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lojas as $loja): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($loja['nome_fantasia']); ?></strong>
                                </td>
                                <td>
                                    <?php if ($loja['mvp'] === 'sim'): ?>
                                        <span class="mvp-badge">MVP</span>
                                    <?php else: ?>
                                        <span class="normal-badge">Normal</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($loja['cashback_ativo'] == 1): ?>
                                        <span class="status-active">Ativo</span>
                                    <?php else: ?>
                                        <span class="status-inactive">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" class="config-form">
                                        <input type="hidden" name="action" value="update_loja">
                                        <input type="hidden" name="loja_id" value="<?php echo $loja['id']; ?>">
                                        <input type="number" name="porcentagem_cliente" step="0.01" min="0" max="50" 
                                               value="<?php echo number_format($loja['porcentagem_cliente'], 2, '.', ''); ?>">
                                </td>
                                <td>
                                        <input type="number" name="porcentagem_admin" step="0.01" min="0" max="50" 
                                               value="<?php echo number_format($loja['porcentagem_admin'], 2, '.', ''); ?>">
                                </td>
                                <td>
                                    <strong><?php echo number_format($loja['porcentagem_cliente'] + $loja['porcentagem_admin'], 2); ?>%</strong>
                                </td>
                                <td>
                                        <select name="cashback_ativo">
                                            <option value="1" <?php echo $loja['cashback_ativo'] == 1 ? 'selected' : ''; ?>>Ativo</option>
                                            <option value="0" <?php echo $loja['cashback_ativo'] == 0 ? 'selected' : ''; ?>>Inativo</option>
                                        </select>
                                </td>
                                <td>
                                    <div class="stats-row">
                                        <?php echo $loja['total_transacoes']; ?> transações<br>
                                        R$ <?php echo number_format($loja['volume_vendas'], 2, ',', '.'); ?>
                                    </div>
                                </td>
                                <td>
                                        <button type="submit" class="btn btn-success btn-sm">Salvar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($lojas)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 30px; color: #666;">
                                    Nenhuma loja aprovada encontrada.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>