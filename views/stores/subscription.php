<?php
/**
 * Loja - Meu Plano (Visualização e Gerenciamento da Assinatura)
 * VERSÃO COMPLETA com upgrade, escolha de ciclo e valor proporcional
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

$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

// Processar ações (código de plano, upgrade/downgrade ou troca de ciclo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $assinaturaAtual = $subscriptionController->getActiveSubscriptionByStore($lojaId);

    if ($_POST['action'] === 'redeem_code') {
        // RESGATAR CÓDIGO DE PLANO (auto-atribuição com código do admin)
        if ($assinaturaAtual) {
            $error = 'Você já possui uma assinatura ativa. Entre em contato com o suporte para mudar de plano.';
        } else {
            $codigo = strtoupper(trim($_POST['plan_code'] ?? ''));

            if (empty($codigo)) {
                $error = 'Por favor, informe o código do plano.';
            } else {
                // Buscar plano pelo código
                $sqlPlano = "SELECT * FROM planos WHERE codigo = ? AND ativo = 1";
                $stmtPlano = $db->prepare($sqlPlano);
                $stmtPlano->execute([$codigo]);
                $plano = $stmtPlano->fetch(PDO::FETCH_ASSOC);

                if (!$plano) {
                    $error = 'Código de plano inválido ou expirado. Verifique com o suporte.';
                } else {
                    // Atribuir plano usando o slug
                    $result = $subscriptionController->assignPlanToStore(
                        $lojaId,
                        $plano['slug'],
                        null, // trial padrão do plano
                        $plano['recorrencia'] === 'yearly' ? 'yearly' : 'monthly'
                    );

                    if ($result['success']) {
                        // Gerar primeira fatura
                        $faturaResult = $subscriptionController->generateInvoiceForSubscription(
                            $result['assinatura_id']
                        );

                        if ($faturaResult['success']) {
                            // Redirecionar para pagamento
                            header('Location: ' . STORE_INVOICE_PIX_URL . '?invoice_id=' . $faturaResult['fatura_id']);
                            exit;
                        } else {
                            $message = 'Plano ' . $plano['nome'] . ' ativado com sucesso!';
                            header('Location: ' . $_SERVER['PHP_SELF'] . '?message=' . urlencode($message));
                            exit;
                        }
                    } else {
                        $error = $result['message'];
                    }
                }
            }
        }
    } elseif ($_POST['action'] === 'change_plan') {
        // MUDAR DE PLANO (upgrade/downgrade ou troca de ciclo)
        if (!$assinaturaAtual) {
            $error = 'Você não possui uma assinatura ativa';
        } else {
            $result = $subscriptionController->upgradeSubscription(
                $assinaturaAtual['id'],
                $_POST['plano_slug'],
                $_POST['ciclo'] ?? null
            );

            if ($result['success']) {
                $message = $result['message'];
                if ($result['valor_proporcional'] > 0) {
                    $message .= sprintf(' - Valor proporcional: R$ %.2f', $result['valor_proporcional']);
                    // Redirecionar para pagamento se gerou fatura
                    if ($result['fatura_id']) {
                        header('Location: ' . STORE_INVOICE_PIX_URL . '?invoice_id=' . $result['fatura_id']);
                        exit;
                    }
                }
                // Recarregar página para mostrar mudanças
                header('Location: ' . $_SERVER['PHP_SELF'] . '?message=' . urlencode($message));
                exit;
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

// Buscar todos os planos disponíveis
$sqlPlanos = "SELECT * FROM planos WHERE ativo = 1 ORDER BY preco_mensal ASC";
$stmtPlanos = $db->prepare($sqlPlanos);
$stmtPlanos->execute();
$planosDisponiveis = $stmtPlanos->fetchAll(PDO::FETCH_ASSOC);

// Variáveis de exibição
$cicloAtual = $assinatura['ciclo'] ?? 'monthly';
$planoAtualId = $assinatura['plano_id'] ?? null;
$cicloLabel = $cicloAtual === 'yearly' ? '/ano' : '/mês';
$renovacao = $cicloAtual === 'yearly' ? 'anual' : 'mensal';

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
    $sidebarCssFile = 'sidebar-lojista.css';
    if (isset($_SESSION['user_senat']) && ($_SESSION['user_senat'] === 'sim' || $_SESSION['user_senat'] === 'Sim')) {
        $sidebarCssFile = 'sidebar-lojista_sest.css';
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

        body.sidebar-colapsada .main-content {
            margin-left: 80px;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 16px;
            }
        }

        /* Header */
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

        /* Alerts */
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #28a745;
            color: #155724;
        }
        .alert-error {
            background: #f8d7da;
            border: 1px solid #dc3545;
            color: #721c24;
        }
        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 24px;
        }

        /* Plan Card */
        .plan-card {
            background: linear-gradient(135deg, <?php echo PRIMARY_COLOR ?? '#ff7a00'; ?> 0%, #ff9533 100%);
            color: white;
        }
        .plan-header {
            margin-bottom: 20px;
        }
        .plan-name {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .plan-price {
            font-size: 24px;
            opacity: 0.95;
            font-weight: 600;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-top: 12px;
        }
        .status-ativa {
            background: #d4edda;
            color: #155724;
        }
        .status-trial {
            background: #cce5ff;
            color: #004085;
        }
        .plan-features {
            margin-top: 24px;
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
            flex-shrink: 0;
        }

        /* Cycle Toggle */
        .cycle-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.2);
        }
        .cycle-toggle {
            display: flex;
            gap: 12px;
            margin-top: 12px;
        }
        .cycle-option {
            flex: 1;
            padding: 12px 16px;
            background: rgba(255,255,255,0.1);
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        .cycle-option.active {
            background: rgba(255,255,255,0.25);
            border-color: rgba(255,255,255,0.6);
        }
        .cycle-price {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .cycle-label {
            font-size: 13px;
            opacity: 0.9;
        }
        .cycle-savings {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            margin-top: 4px;
        }

        /* Plans Grid */
        .section-title {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #1a1a1a;
        }
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        .plan-option {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 24px;
            transition: all 0.3s;
            cursor: pointer;
        }
        .plan-option:hover {
            border-color: <?php echo PRIMARY_COLOR ?? '#ff7a00'; ?>;
            box-shadow: 0 4px 16px rgba(255,122,0,0.15);
        }
        .plan-option.current {
            border-color: #28a745;
            background: #f8fff9;
        }
        .plan-option.recommended {
            border-color: <?php echo PRIMARY_COLOR ?? '#ff7a00'; ?>;
            position: relative;
        }
        .plan-badge {
            position: absolute;
            top: -12px;
            right: 20px;
            background: <?php echo PRIMARY_COLOR ?? '#ff7a00'; ?>;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
        }
        .plan-option h3 {
            font-size: 20px;
            margin-bottom: 8px;
        }
        .plan-pricing {
            margin: 16px 0;
        }
        .plan-pricing .price {
            font-size: 28px;
            font-weight: 700;
            color: <?php echo PRIMARY_COLOR ?? '#ff7a00'; ?>;
        }
        .plan-pricing .period {
            font-size: 14px;
            color: #666;
        }
        .plan-features-list {
            list-style: none;
            margin: 16px 0;
        }
        .plan-features-list li {
            padding: 8px 0;
            color: #555;
            font-size: 14px;
        }
        .plan-features-list li::before {
            content: "✓";
            color: #28a745;
            font-weight: 700;
            margin-right: 8px;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
        }
        .btn-primary {
            background: <?php echo PRIMARY_COLOR ?? '#ff7a00'; ?>;
            color: white;
        }
        .btn-primary:hover {
            background: #e66d00;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255,122,0,0.3);
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-outline {
            background: white;
            border: 2px solid #e0e0e0;
            color: #666;
        }
        .btn-outline:hover {
            border-color: <?php echo PRIMARY_COLOR ?? '#ff7a00'; ?>;
            color: <?php echo PRIMARY_COLOR ?? '#ff7a00'; ?>;
        }
        .btn-block {
            width: 100%;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 32px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            margin-bottom: 24px;
        }
        .modal-header h2 {
            font-size: 24px;
            margin-bottom: 8px;
        }
        .modal-close {
            float: right;
            font-size: 28px;
            font-weight: 700;
            color: #aaa;
            cursor: pointer;
        }
        .modal-close:hover {
            color: #000;
        }

        /* Table */
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
            .cycle-toggle { flex-direction: column; }
            .plans-grid { grid-template-columns: 1fr; }
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

        <?php if ($message): ?>
            <div class="alert alert-success">
                ✓ <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                ✕ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!$assinatura): ?>
            <!-- Sem Assinatura - ATIVAR COM CÓDIGO -->
            <div class="alert alert-warning">
                <h3>⚠️ Você ainda não possui um plano ativo</h3>
                <p>Entre em contato com o suporte para receber um código de ativação.</p>
            </div>

            <!-- Formulário de Código de Plano -->
            <div class="card" style="max-width: 600px; margin: 0 auto;">
                <h2 class="section-title" style="margin-bottom: 16px;">🔑 Ativar Plano com Código</h2>
                <p style="color: #666; margin-bottom: 24px; font-size: 14px;">
                    Se você recebeu um código de plano do nosso suporte, insira abaixo para ativar sua assinatura.
                </p>

                <form method="POST" style="display: flex; flex-direction: column; gap: 16px;">
                    <input type="hidden" name="action" value="redeem_code">

                    <div>
                        <label for="plan_code" style="display: block; font-weight: 600; margin-bottom: 8px; color: #333;">
                            Código do Plano
                        </label>
                        <input
                            type="text"
                            id="plan_code"
                            name="plan_code"
                            placeholder="Ex: KLUBE-PRO-M"
                            required
                            style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; font-weight: 600; text-transform: uppercase; font-family: monospace;"
                            pattern="[A-Z0-9\-]+"
                            maxlength="30"
                        >
                        <small style="color: #999; margin-top: 4px; display: block;">
                            Digite o código exatamente como recebeu do suporte
                        </small>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" style="padding: 14px;">
                        ✓ Ativar Plano
                    </button>
                </form>

                <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid #e0e0e0;">
                    <h4 style="font-size: 14px; font-weight: 600; margin-bottom: 12px; color: #333;">
                        Exemplos de Códigos de Plano:
                    </h4>
                    <ul style="list-style: none; padding: 0; font-size: 13px; color: #666;">
                        <li style="padding: 6px 0;">
                            <code style="background: #f8f9fa; padding: 4px 8px; border-radius: 4px; font-weight: 600; color: #000;">KLUBE-BASIC-M</code>
                            - Plano Básico Mensal
                        </li>
                        <li style="padding: 6px 0;">
                            <code style="background: #f8f9fa; padding: 4px 8px; border-radius: 4px; font-weight: 600; color: #000;">KLUBE-PRO-Y</code>
                            - Plano Profissional Anual
                        </li>
                        <li style="padding: 6px 0;">
                            <code style="background: #f8f9fa; padding: 4px 8px; border-radius: 4px; font-weight: 600; color: #000;">KLUBE-TRIAL30</code>
                            - Plano com Trial Especial
                        </li>
                    </ul>
                </div>

                <div style="margin-top: 24px; padding: 16px; background: #f8f9fa; border-radius: 8px; text-align: center;">
                    <p style="font-size: 13px; color: #666; margin: 0;">
                        📞 Não tem um código? <strong>Entre em contato com o suporte</strong>
                    </p>
                </div>
            </div>

        <?php else: ?>
            <!-- Plano Atual -->
            <div class="card plan-card">
                <div class="plan-header">
                    <div class="plan-name">
                        <?php echo htmlspecialchars($planInfo['plano_nome'] ?? 'Plano Padrão'); ?>
                    </div>
                    <div class="plan-price">
                        R$ <?php
                        $amount = $cicloAtual === 'yearly' ? ($planInfo['preco_anual'] ?? 0) : ($planInfo['preco_mensal'] ?? 0);
                        echo number_format($amount, 2, ',', '.');
                        echo htmlspecialchars($cicloLabel);
                        ?>
                    </div>
                    <div>
                        <span class="status-badge status-<?php echo $assinatura['status']; ?>">
                            <?php if ($assinatura['status'] === 'ativa'): ?>
                                ✓ Ativa
                            <?php elseif ($assinatura['status'] === 'trial'): ?>
                                🎯 Trial
                            <?php else: ?>
                                ⏳ <?php echo ucfirst($assinatura['status']); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <div class="plan-features">
                    <div class="plan-feature">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span>Período: <?php echo date('d/m/Y', strtotime($assinatura['current_period_start'])); ?> até <?php echo date('d/m/Y', strtotime($assinatura['current_period_end'])); ?></span>
                    </div>
                    <div class="plan-feature">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span>Renovação automática <?php echo htmlspecialchars($renovacao); ?></span>
                    </div>
                    <div class="plan-feature">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <span>Suporte técnico incluído</span>
                    </div>
                </div>

                <?php
                // Buscar plano completo para mostrar preços
                $sqlPlanoAtual = "SELECT * FROM planos WHERE id = ?";
                $stmtPlanoAtual = $db->prepare($sqlPlanoAtual);
                $stmtPlanoAtual->execute([$planoAtualId]);
                $planoAtualCompleto = $stmtPlanoAtual->fetch(PDO::FETCH_ASSOC);
                ?>
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
                                    <a href="<?php echo STORE_INVOICE_PIX_URL; ?>?invoice_id=<?php echo $fatura['id']; ?>"
                                       class="btn btn-success">
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
</body>
</html>
