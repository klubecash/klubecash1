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

// CORREÇÃO: Usar store_id ou loja_id (compatibilidade)
$lojaId = $_SESSION['store_id'] ?? $_SESSION['loja_id'] ?? $_SESSION['user_id'] ?? null;

if (!$lojaId) {
    header('Location: ' . LOGIN_URL . '?error=' . urlencode('Erro ao identificar loja'));
    exit;
}

$db = (new Database())->getConnection();
$subscriptionController = new SubscriptionController($db);

// Buscar assinatura e faturas pendentes
$assinatura = $subscriptionController->getActiveSubscriptionByStore($lojaId);

// DEBUG: Log para verificar
error_log("SUBSCRIPTION PAGE - Loja ID: {$lojaId}, Assinatura encontrada: " . ($assinatura ? 'SIM (ID: ' . $assinatura['id'] . ')' : 'NÃO'));
$planInfo = FeatureGate::getPlanInfo($lojaId);

$faturasPendentes = [];
if ($assinatura) {
    $sqlFaturas = "SELECT * FROM faturas WHERE assinatura_id = ? AND status = 'pending' ORDER BY due_date ASC";
    $stmtFaturas = $db->prepare($sqlFaturas);
    $stmtFaturas->execute([$assinatura['id']]);
    $faturasPendentes = $stmtFaturas->fetchAll(PDO::FETCH_ASSOC);
}

$activeMenu = 'meu-plano';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Plano - <?php echo SYSTEM_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/store.css">
</head>
<body>
    <?php include __DIR__ . '/../components/sidebar-store.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>Meu Plano</h1>
            <p>Gerencie sua assinatura do Klube Cash</p>
        </div>

        <?php if (!$assinatura): ?>
            <!-- Sem Assinatura -->
            <div class="alert alert-warning">
                <h3>Você ainda não possui um plano ativo</h3>
                <p>Entre em contato com o suporte para ativar sua assinatura.</p>
            </div>
        <?php else: ?>
            <!-- Status do Plano -->
            <div class="plan-card">
                <div class="plan-header">
                    <div>
                        <h2><?php echo htmlspecialchars($planInfo['plano_nome'] ?? 'Plano Ativo'); ?></h2>
                        <p class="plan-price">R$ <?php echo number_format($planInfo['preco_mensal'] ?? 0, 2, ',', '.'); ?>/mês</p>
                    </div>
                    <div class="plan-status">
                        <?php
                        $statusBadge = [
                            'trial' => '<span class="badge badge-warning">Em Trial</span>',
                            'ativa' => '<span class="badge badge-success">Ativa</span>',
                            'inadimplente' => '<span class="badge badge-danger">Inadimplente</span>',
                            'suspensa' => '<span class="badge badge-secondary">Suspensa</span>',
                        ];
                        echo $statusBadge[$assinatura['status']] ?? '';
                        ?>
                    </div>
                </div>

                <div class="plan-details">
                    <?php if ($assinatura['status'] === 'trial' && $assinatura['trial_end']): ?>
                        <?php
                        $daysLeft = max(0, floor((strtotime($assinatura['trial_end']) - time()) / 86400));
                        ?>
                        <div class="trial-notice">
                            <strong>Período de Teste</strong>
                            <p>Seu trial expira em <?php echo $daysLeft; ?> dia(s) - <?php echo date('d/m/Y', strtotime($assinatura['trial_end'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="plan-info-grid">
                        <div class="info-item">
                            <span class="label">Ciclo de Pagamento</span>
                            <span class="value"><?php echo $assinatura['ciclo'] === 'monthly' ? 'Mensal' : 'Anual'; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Período Atual</span>
                            <span class="value">
                                <?php echo date('d/m/Y', strtotime($assinatura['current_period_start'])); ?> até
                                <?php echo date('d/m/Y', strtotime($assinatura['current_period_end'])); ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="label">Próxima Cobrança</span>
                            <span class="value">
                                <?php echo $assinatura['next_invoice_date'] ? date('d/m/Y', strtotime($assinatura['next_invoice_date'])) : '-'; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Features do Plano -->
                <?php if (!empty($planInfo['features'])): ?>
                    <div class="plan-features">
                        <h3>Recursos do seu plano</h3>
                        <ul>
                            <?php
                            $features = $planInfo['features'];
                            $featureLabels = [
                                'employees_limit' => 'Funcionários',
                                'analytics_level' => 'Nível de Análise',
                                'api_access' => 'Acesso à API',
                                'white_label' => 'White Label',
                                'support_level' => 'Suporte'
                            ];
                            foreach ($features as $key => $value):
                                if (isset($featureLabels[$key])):
                                    $displayValue = $value;
                                    if ($value === true) $displayValue = 'Sim';
                                    elseif ($value === false) $displayValue = 'Não';
                                    elseif ($value === 'unlimited') $displayValue = 'Ilimitado';
                            ?>
                                    <li><?php echo $featureLabels[$key]; ?>: <strong><?php echo $displayValue; ?></strong></li>
                            <?php
                                endif;
                            endforeach;
                            ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Faturas Pendentes -->
            <?php if (!empty($faturasPendentes)): ?>
                <div class="pending-invoices-card">
                    <h2>Faturas Pendentes</h2>
                    <?php foreach ($faturasPendentes as $fatura): ?>
                        <?php
                        $isOverdue = strtotime($fatura['due_date']) < time();
                        ?>
                        <div class="invoice-item <?php echo $isOverdue ? 'overdue' : ''; ?>">
                            <div class="invoice-info">
                                <strong>Fatura <?php echo htmlspecialchars($fatura['numero']); ?></strong>
                                <p>Vencimento: <?php echo date('d/m/Y', strtotime($fatura['due_date'])); ?></p>
                                <p class="invoice-amount">R$ <?php echo number_format($fatura['amount'], 2, ',', '.'); ?></p>
                                <?php if ($isOverdue): ?>
                                    <span class="badge badge-danger">Vencida</span>
                                <?php endif; ?>
                            </div>
                            <div class="invoice-actions">
                                <a href="<?php echo STORE_INVOICE_PIX_URL; ?>?invoice_id=<?php echo $fatura['id']; ?>" class="btn btn-primary">
                                    Pagar com PIX
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Mensagem de Inadimplência -->
            <?php if ($assinatura['status'] === 'inadimplente'): ?>
                <div class="alert alert-danger">
                    <h3>Atenção: Assinatura Inadimplente</h3>
                    <p>Sua assinatura está com pagamentos em atraso. Por favor, regularize sua situação para continuar utilizando todos os recursos da plataforma.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <style>
        .main-content { padding: 20px; margin-left: 280px; }
        .plan-card, .pending-invoices-card {
            background: white;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .plan-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        .plan-price { font-size: 24px; font-weight: 700; color: <?php echo PRIMARY_COLOR; ?>; margin-top: 8px; }
        .trial-notice {
            background: #fff3cd;
            padding: 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }
        .plan-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .info-item { display: flex; flex-direction: column; }
        .info-item .label { font-size: 13px; color: #666; margin-bottom: 4px; }
        .info-item .value { font-size: 16px; font-weight: 500; }
        .plan-features { margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; }
        .plan-features ul { list-style: none; padding: 0; }
        .plan-features li { padding: 8px 0; display: flex; align-items: center; }
        .plan-features li:before { content: "✓"; color: <?php echo SUCCESS_COLOR; ?>; font-weight: bold; margin-right: 10px; }
        .invoice-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-bottom: 12px;
        }
        .invoice-item.overdue { border-color: #dc3545; background: #fff5f5; }
        .invoice-amount { font-size: 20px; font-weight: 700; color: <?php echo PRIMARY_COLOR; ?>; margin: 8px 0; }
        .alert { padding: 16px 20px; border-radius: 6px; margin-bottom: 20px; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .badge { padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-secondary { background: #e2e3e5; color: #383d41; }
        .btn { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; font-weight: 500; }
        .btn-primary { background: <?php echo PRIMARY_COLOR; ?>; color: white; }
        .btn-primary:hover { background: #e66d00; }
    </style>
</body>
</html>
