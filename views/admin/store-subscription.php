<?php
/**
 * Admin - Detalhes da Assinatura e Gerenciamento
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../controllers/SubscriptionController.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ' . LOGIN_URL);
    exit;
}

$db = (new Database())->getConnection();
$subscriptionController = new SubscriptionController($db);

$assinaturaId = $_GET['id'] ?? null;
$lojaId = $_GET['loja_id'] ?? null;
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'assign_plan') {
        $result = $subscriptionController->assignPlanToStore(
            $_POST['loja_id'],
            $_POST['plano_slug'],
            $_POST['trial_days'] ?? null,
            $_POST['ciclo'] ?? 'monthly'
        );

        if ($result['success']) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $result['assinatura_id'] . '&message=Plano atribuído');
            exit;
        } else {
            $error = $result['message'];
        }
    } elseif ($action === 'generate_invoice' && $assinaturaId) {
        $result = $subscriptionController->generateInvoiceForSubscription($assinaturaId);
        if ($result['success']) {
            $message = 'Fatura gerada: ' . $result['numero'];
        } else {
            $error = $result['message'];
        }
    } elseif ($action === 'suspend' && $assinaturaId) {
        $subscriptionController->suspendSubscription($assinaturaId);
        $message = 'Assinatura suspensa';
    } elseif ($action === 'cancel' && $assinaturaId) {
        $subscriptionController->cancelSubscription($assinaturaId);
        $message = 'Assinatura cancelada';
    }
}

// Buscar dados
$assinatura = null;
$faturas = [];
if ($assinaturaId) {
    $assinatura = $subscriptionController->getSubscriptionById($assinaturaId);
    $faturas = $subscriptionController->getInvoicesBySubscription($assinaturaId);
    $lojaId = $assinatura['loja_id'] ?? null;
}

// Buscar planos disponíveis
$sqlPlanos = "SELECT * FROM planos WHERE ativo = 1 ORDER BY preco_mensal ASC";
$stmtPlanos = $db->prepare($sqlPlanos);
$stmtPlanos->execute();
$planos = $stmtPlanos->fetchAll(PDO::FETCH_ASSOC);

// Buscar dados da loja se tiver ID
$loja = null;
if ($lojaId) {
    $sqlLoja = "SELECT * FROM lojas WHERE id = ?";
    $stmtLoja = $db->prepare($sqlLoja);
    $stmtLoja->execute([$lojaId]);
    $loja = $stmtLoja->fetch(PDO::FETCH_ASSOC);
}

$activeMenu = 'assinaturas';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Assinatura - <?php echo SYSTEM_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <?php include __DIR__ . '/../components/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>Gerenciar Assinatura</h1>
            <a href="<?php echo ADMIN_SUBSCRIPTIONS_URL; ?>" class="btn btn-secondary">← Voltar</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Informações da Loja -->
        <?php if ($loja): ?>
            <div class="info-card">
                <h2>Loja: <?php echo htmlspecialchars($loja['nome_loja']); ?></h2>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($loja['email']); ?></p>
                <p><strong>Telefone:</strong> <?php echo htmlspecialchars($loja['telefone'] ?? '-'); ?></p>
            </div>
        <?php endif; ?>

        <!-- Assinatura Atual -->
        <?php if ($assinatura): ?>
            <div class="subscription-card">
                <h2>Assinatura Atual</h2>
                <div class="subscription-details">
                    <p><strong>Plano:</strong> <?php echo htmlspecialchars($assinatura['plano_nome']); ?></p>
                    <p><strong>Status:</strong> <span class="badge badge-<?php echo $assinatura['status']; ?>"><?php echo ucfirst($assinatura['status']); ?></span></p>
                    <p><strong>Ciclo:</strong> <?php echo $assinatura['ciclo'] === 'monthly' ? 'Mensal' : 'Anual'; ?></p>
                    <?php if ($assinatura['trial_end']): ?>
                        <p><strong>Trial até:</strong> <?php echo date('d/m/Y', strtotime($assinatura['trial_end'])); ?></p>
                    <?php endif; ?>
                    <p><strong>Período atual:</strong> <?php echo date('d/m/Y', strtotime($assinatura['current_period_start'])); ?> a <?php echo date('d/m/Y', strtotime($assinatura['current_period_end'])); ?></p>
                    <p><strong>Próxima fatura:</strong> <?php echo $assinatura['next_invoice_date'] ? date('d/m/Y', strtotime($assinatura['next_invoice_date'])) : '-'; ?></p>
                </div>

                <div class="actions">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="generate_invoice">
                        <button type="submit" class="btn btn-primary">Gerar Fatura Manual</button>
                    </form>
                    <?php if ($assinatura['status'] !== 'suspensa'): ?>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Suspender assinatura?')">
                            <input type="hidden" name="action" value="suspend">
                            <button type="submit" class="btn btn-warning">Suspender</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($assinatura['status'] !== 'cancelada'): ?>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Cancelar assinatura?')">
                            <input type="hidden" name="action" value="cancel">
                            <button type="submit" class="btn btn-danger">Cancelar</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Histórico de Faturas -->
            <div class="invoices-card">
                <h2>Histórico de Faturas</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Valor</th>
                            <th>Status</th>
                            <th>Vencimento</th>
                            <th>Pagamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($faturas)): ?>
                            <tr><td colspan="5">Nenhuma fatura encontrada</td></tr>
                        <?php else: ?>
                            <?php foreach ($faturas as $fatura): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($fatura['numero']); ?></td>
                                    <td>R$ <?php echo number_format($fatura['amount'], 2, ',', '.'); ?></td>
                                    <td><span class="badge badge-<?php echo $fatura['status']; ?>"><?php echo ucfirst($fatura['status']); ?></span></td>
                                    <td><?php echo date('d/m/Y', strtotime($fatura['due_date'])); ?></td>
                                    <td><?php echo $fatura['paid_at'] ? date('d/m/Y H:i', strtotime($fatura['paid_at'])) : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Atribuir/Alterar Plano -->
        <div class="assign-plan-card">
            <h2><?php echo $assinatura ? 'Alterar Plano' : 'Atribuir Plano'; ?></h2>
            <form method="POST">
                <input type="hidden" name="action" value="assign_plan">
                <input type="hidden" name="loja_id" value="<?php echo $lojaId; ?>">

                <div class="form-group">
                    <label>Plano</label>
                    <select name="plano_slug" required>
                        <?php foreach ($planos as $plano): ?>
                            <option value="<?php echo $plano['slug']; ?>">
                                <?php echo htmlspecialchars($plano['nome']); ?> - R$ <?php echo number_format($plano['preco_mensal'], 2, ',', '.'); ?>/mês
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Ciclo</label>
                    <select name="ciclo">
                        <option value="monthly">Mensal</option>
                        <option value="yearly">Anual</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Dias de Trial (opcional)</label>
                    <input type="number" name="trial_days" min="0" max="90" placeholder="Deixe vazio para usar padrão do plano">
                </div>

                <button type="submit" class="btn btn-success">Aplicar Plano</button>
            </form>
        </div>
    </div>

    <style>
        .info-card, .subscription-card, .invoices-card, .assign-plan-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .alert {
            padding: 12px 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        .subscription-details p { margin: 8px 0; }
        .actions { margin-top: 15px; display: flex; gap: 10px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: <?php echo PRIMARY_COLOR; ?>; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #000; }
        .btn-danger { background: #dc3545; color: white; }
        .badge { padding: 4px 12px; border-radius: 12px; font-size: 12px; }
        .badge-trial { background: #fff3cd; color: #856404; }
        .badge-ativa { background: #d4edda; color: #155724; }
        .badge-inadimplente { background: #f8d7da; color: #721c24; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-paid { background: #d4edda; color: #155724; }
        .badge-failed { background: #f8d7da; color: #721c24; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .data-table th { background: #f8f9fa; font-weight: 600; }
    </style>
</body>
</html>
