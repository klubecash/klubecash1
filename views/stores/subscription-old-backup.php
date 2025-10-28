<?php
/**
 * Loja - Meu Plano (Visualização da Assinatura)
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../controllers/SubscriptionController.php';
require_once __DIR__ . '/../../utils/FeatureGate.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'loja') {
    header('Location: ' . LOGIN_URL);
    exit;
}

$lojaId = $_SESSION['store_id'] ?? $_SESSION['loja_id'] ?? $_SESSION['user_id'] ?? null;

if (!$lojaId) {
    header('Location: ' . LOGIN_URL . '?error=' . urlencode('Erro ao identificar loja'));
    exit;
}

$db = (new Database())->getConnection();
$subscriptionController = new SubscriptionController($db);

$message = '';
$error = '';

// Processar ações (upgrade de plano ou troca de ciclo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $assinaturaAtual = $subscriptionController->getActiveSubscriptionByStore($lojaId);

    if (!$assinaturaAtual) {
        $error = 'Você não possui uma assinatura ativa';
    } else {
        if ($_POST['action'] === 'upgrade') {
            $result = $subscriptionController->upgradeSubscription(
                $assinaturaAtual['id'],
                $_POST['plano_slug'],
                $_POST['ciclo'] ?? null
            );

            if ($result['success']) {
                $message = $result['message'];
                if ($result['valor_proporcional'] > 0) {
                    $message .= sprintf(' Valor proporcional: R$ %.2f', $result['valor_proporcional']);
                    // Redirecionar para pagamento se gerou fatura
                    if ($result['fatura_id']) {
                        header('Location: ' . STORE_INVOICE_PIX_URL . '?invoice_id=' . $result['fatura_id']);
                        exit;
                    }
                }
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Buscar assinatura e faturas pendentes
$assinatura = $subscriptionController->getActiveSubscriptionByStore($lojaId);
$planInfo = FeatureGate::getPlanInfo($lojaId);

$faturasPendentes = [];
if ($assinatura) {
    $sqlFaturas = "SELECT * FROM faturas WHERE assinatura_id = ? AND status = 'pending' ORDER BY due_date ASC";
    $stmtFaturas = $db->prepare($sqlFaturas);
    $stmtFaturas->execute([$assinatura['id']]);
    $faturasPendentes = $stmtFaturas->fetchAll(PDO::FETCH_ASSOC);
}

// Buscar todos os planos disponíveis para upgrade
$sqlPlanos = "SELECT * FROM planos WHERE ativo = 1 ORDER BY preco_mensal ASC";
$stmtPlanos = $db->prepare($sqlPlanos);
$stmtPlanos->execute();
$planosDisponiveis = $stmtPlanos->fetchAll(PDO::FETCH_ASSOC);

$activeMenu = 'meu-plano';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Plano - <?php echo SYSTEM_NAME; ?></title>

    <?php
    // Determinar qual CSS da sidebar carregar baseado no campo senat do usuário
    $sidebarCssFile = 'sidebar-lojista.css'; // CSS da sidebar padrão
    if (isset($_SESSION['user_senat']) && ($_SESSION['user_senat'] === 'sim' || $_SESSION['user_senat'] === 'Sim')) {
        $sidebarCssFile = 'sidebar-lojista_sest.css'; // CSS da sidebar para usuários senat=sim
    }
    ?>
    <link rel="stylesheet" href="/assets/css/<?php echo htmlspecialchars($sidebarCssFile); ?>">

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f7fa;
            color: #1a1a1a;
        }

        /* Container Principal */
        .main-content {
            margin-left: 280px;
            padding: 24px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        /* Quando sidebar colapsada */
        body.sidebar-colapsada .main-content {
            margin-left: 80px;
        }

        /* Mobile */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 16px;
            }
        }

        /* Header da Página */
        .page-header {
            margin-bottom: 32px;
        }
        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        .page-header p {
            font-size: 16px;
            color: #666;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 24px;
        }

        /* Alert */
        .alert {
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #28a745;
            color: #155724;
        }
        .alert h3 {
            font-size: 18px;
            margin-bottom: 8px;
        }

        /* Plan Card */
        .plan-card {
            background: linear-gradient(135deg, <?php echo PRIMARY_COLOR; ?> 0%, #ff9533 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }
        .plan-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        .plan-header {
            position: relative;
            z-index: 1;
            margin-bottom: 24px;
        }
        .plan-name {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .plan-price {
            font-size: 20px;
            opacity: 0.9;
        }
        .plan-features {
            position: relative;
            z-index: 1;
        }
        .plan-feature {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-top: 1px solid rgba(255,255,255,0.2);
        }
        .plan-feature svg {
            width: 20px;
            height: 20px;
            margin-right: 12px;
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            gap: 6px;
        }
        .status-ativa {
            background: #d4edda;
            color: #155724;
        }
        .status-pendente {
            background: #fff3cd;
            color: #856404;
        }

        /* Invoices Table */
        .invoices-section {
            margin-top: 32px;
        }
        .section-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #1a1a1a;
        }
        .invoices-table {
            width: 100%;
            border-collapse: collapse;
        }
        .invoices-table thead {
            background: #f8f9fa;
        }
        .invoices-table th {
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            color: #666;
        }
        .invoices-table td {
            padding: 16px;
            border-top: 1px solid #e0e0e0;
        }
        .invoices-table tbody tr:hover {
            background: #f8f9fa;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
        }
        .btn-primary {
            background: <?php echo PRIMARY_COLOR; ?>;
            color: white;
        }
        .btn-primary:hover {
            background: #e66d00;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255,122,0,0.3);
        }
        .btn-outline {
            background: white;
            border: 2px solid #e0e0e0;
            color: #666;
        }
        .btn-outline:hover {
            border-color: <?php echo PRIMARY_COLOR; ?>;
            color: <?php echo PRIMARY_COLOR; ?>;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 48px 24px;
            color: #999;
        }
        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header h1 { font-size: 24px; }
            .card { padding: 16px; }
            .invoices-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <?php include '../../views/components/sidebar-lojista-responsiva.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>📋 Meu Plano</h1>
            <p>Gerencie sua assinatura do Klube Cash</p>
        </div>

        <?php if (!$assinatura): ?>
            <!-- Sem Assinatura -->
            <div class="alert alert-warning">
                <h3>⚠️ Você ainda não possui um plano ativo</h3>
                <p>Entre em contato com o suporte para ativar sua assinatura.</p>
            </div>
        <?php else: ?>
            <!-- Informações do Plano -->
            <div class="card plan-card">
                <div class="plan-header">
                    <div class="plan-name">
                        <?php echo htmlspecialchars($assinatura['plan_name'] ?? 'Plano Padrão'); ?>
                    </div>
                    <div class="plan-price">
                        R$ <?php echo number_format($assinatura['amount'], 2, ',', '.'); ?>/mês
                    </div>
                    <div style="margin-top: 12px;">
                        <span class="status-badge status-<?php echo $assinatura['status'] === 'ativa' ? 'ativa' : 'pendente'; ?>">
                            <?php if ($assinatura['status'] === 'ativa'): ?>
                                ✓ Ativa
                            <?php else: ?>
                                ⏳ <?php echo ucfirst($assinatura['status']); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <div class="plan-features">
                    <div class="plan-feature">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Período: <?php echo date('d/m/Y', strtotime($assinatura['current_period_start'])); ?> até <?php echo date('d/m/Y', strtotime($assinatura['current_period_end'])); ?></span>
                    </div>
                    <div class="plan-feature">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                        </svg>
                        <span>Renovação automática mensal</span>
                    </div>
                    <div class="plan-feature">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        <span>Suporte técnico incluído</span>
                    </div>
                </div>
            </div>

            <!-- Faturas Pendentes -->
            <?php if (!empty($faturasPendentes)): ?>
            <div class="invoices-section">
                <h2 class="section-title">💳 Faturas Pendentes</h2>
                <div class="card">
                    <table class="invoices-table">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Vencimento</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($faturasPendentes as $fatura): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($fatura['numero']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($fatura['due_date'])); ?></td>
                                <td style="font-weight: 600;">R$ <?php echo number_format($fatura['amount'], 2, ',', '.'); ?></td>
                                <td>
                                    <span class="status-badge status-pendente">Pendente</span>
                                </td>
                                <td>
                                    <a href="<?php echo STORE_INVOICE_PIX_URL; ?>?invoice_id=<?php echo $fatura['id']; ?>"
                                       class="btn btn-primary">
                                        💳 Pagar com PIX
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="invoices-section">
                <h2 class="section-title">💳 Faturas</h2>
                <div class="card empty-state">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p style="font-size: 16px; color: #666;">✓ Sem faturas pendentes</p>
                    <p style="font-size: 14px; color: #999; margin-top: 8px;">Sua assinatura está em dia!</p>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="/assets/js/sidebar-lojista.js"></script>
</body>
</html>
