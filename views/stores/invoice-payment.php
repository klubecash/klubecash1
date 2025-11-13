<?php
/**
 * Loja - Pagamento de Fatura (PIX ou Cartão de Crédito)
 * Interface moderna com tabs para múltiplos métodos de pagamento
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';

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

$invoiceId = $_GET['invoice_id'] ?? null;

if (!$invoiceId) {
    header('Location: ' . STORE_SUBSCRIPTION_URL);
    exit;
}

$db = (new Database())->getConnection();

// Buscar fatura
$sql = "SELECT f.*, a.loja_id, a.id as assinatura_id
        FROM faturas f
        JOIN assinaturas a ON f.assinatura_id = a.id
        WHERE f.id = ? AND a.loja_id = ?";
$stmt = $db->prepare($sql);
$stmt->execute([$invoiceId, $lojaId]);
$fatura = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fatura) {
    header('Location: ' . STORE_SUBSCRIPTION_URL . '?error=fatura_nao_encontrada');
    exit;
}

// Verificar se precisa gerar PIX
$needsPixGeneration = empty($fatura['pix_qr_code']) || empty($fatura['pix_copia_cola']);

// Traduzir status
$statusLabels = [
    'pending' => 'Pendente',
    'paid' => 'Pago',
    'failed' => 'Falhou',
    'expired' => 'Expirado',
    'canceled' => 'Cancelado'
];

$activeMenu = 'meu-plano';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento de Assinatura - <?php echo SYSTEM_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/store.css">
    <link rel="stylesheet" href="/assets/css/sidebar-lojista_sest.css">

    <!-- Stripe.js -->
    <script src="https://js.stripe.com/v3/"></script>

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background: #f5f7fa; }
        .main-content { padding: 20px; margin-left: 280px; min-height: 100vh; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-header h1 { font-size: 28px; color: #1a1a1a; font-weight: 600; }
        .payment-container { max-width: 800px; margin: 0 auto; }

        /* Cards */
        .card { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); margin-bottom: 20px; }

        /* Resumo da Fatura */
        .invoice-summary { text-align: center; }
        .invoice-summary h2 { font-size: 20px; color: #1a1a1a; margin-bottom: 16px; }
        .amount-display { padding: 24px; background: linear-gradient(135deg, <?php echo PRIMARY_COLOR; ?> 0%, #ff9533 100%); border-radius: 12px; margin: 20px 0; }
        .amount-label { display: block; font-size: 14px; color: rgba(255,255,255,0.9); margin-bottom: 8px; }
        .amount-value { display: block; font-size: 42px; font-weight: 700; color: white; }
        .invoice-details { display: flex; justify-content: space-around; padding-top: 16px; border-top: 1px solid #e0e0e0; margin-top: 16px; }

        /* Tabs de Método de Pagamento */
        .payment-tabs { display: flex; gap: 12px; margin-bottom: 24px; border-bottom: 2px solid #e0e0e0; }
        .tab-btn { flex: 1; padding: 16px; border: none; background: transparent; font-weight: 600; font-size: 15px; cursor: pointer; transition: all 0.3s; border-bottom: 3px solid transparent; color: #666; }
        .tab-btn:hover { color: <?php echo PRIMARY_COLOR; ?>; }
        .tab-btn.active { color: <?php echo PRIMARY_COLOR; ?>; border-bottom-color: <?php echo PRIMARY_COLOR; ?>; }
        .tab-btn i { margin-right: 8px; font-size: 18px; }

        /* Tab Content */
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.3s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* PIX Styles */
        .qr-code-container { text-align: center; padding: 30px; background: #f8f9fa; border-radius: 12px; margin: 24px 0; }
        .qr-code { max-width: 280px; width: 100%; border: 4px solid white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .pix-copy-section { margin: 24px 0; }
        .pix-copy-section label { display: block; margin-bottom: 10px; font-weight: 500; color: #333; font-size: 14px; }
        .copy-container { display: flex; gap: 12px; }
        .copy-container input { flex: 1; padding: 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 13px; }

        /* Card Payment Form */
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #333; font-size: 14px; }
        .form-control { width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; transition: border-color 0.3s; }
        .form-control:focus { outline: none; border-color: <?php echo PRIMARY_COLOR; ?>; }
        .form-row { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; }

        /* Stripe Elements Container */
        #card-element { padding: 14px; border: 2px solid #e0e0e0; border-radius: 8px; background: white; }
        #card-element.StripeElement--focus { border-color: <?php echo PRIMARY_COLOR; ?>; }
        #card-errors { color: #e53935; font-size: 14px; margin-top: 8px; min-height: 20px; }

        /* Botões */
        .btn { padding: 14px 28px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 15px; transition: all 0.3s; text-decoration: none; display: inline-block; text-align: center; }
        .btn-primary { background: <?php echo PRIMARY_COLOR; ?>; color: white; }
        .btn-primary:hover { background: #e66d00; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(255,122,0,0.3); }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-large { width: 100%; padding: 18px; font-size: 16px; }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        /* Badge de Status */
        .badge { padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 500; display: inline-block; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-paid { background: #d4edda; color: #155724; }
        .badge-failed { background: #f8d7da; color: #721c24; }

        /* Alert de Sucesso */
        .alert-success { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 24px; border-radius: 12px; text-align: center; }
        .alert-success h3 { font-size: 24px; margin-bottom: 8px; }
        .alert-success .success-icon { font-size: 48px; margin-bottom: 12px; }

        /* Loading Spinner */
        .spinner { border: 3px solid #f3f3f3; border-top: 3px solid <?php echo PRIMARY_COLOR; ?>; border-radius: 50%; width: 20px; height: 20px; animation: spin 0.8s linear infinite; display: inline-block; margin-right: 8px; vertical-align: middle; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        /* Security Badges */
        .security-badges { display: flex; justify-content: center; gap: 20px; margin-top: 24px; padding-top: 20px; border-top: 1px solid #e0e0e0; }
        .security-badge { display: flex; align-items: center; gap: 8px; color: #666; font-size: 13px; }
        .security-badge i { color: #28a745; font-size: 16px; }

        /* Card Brands */
        .card-brands { display: flex; gap: 12px; align-items: center; margin-top: 16px; padding-top: 16px; border-top: 1px solid #e0e0e0; }
        .card-brands span { color: #999; font-size: 13px; margin-right: 8px; }
        .card-brands img { height: 24px; opacity: 0.6; transition: opacity 0.3s; }
        .card-brands img:hover { opacity: 1; }

        /* Responsivo */
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px; }
            .page-header { flex-direction: column; gap: 16px; align-items: flex-start; }
            .amount-value { font-size: 32px; }
            .form-row { grid-template-columns: 1fr; }
            .payment-tabs { flex-direction: column; }
            .tab-btn { border-bottom: none; border-left: 3px solid transparent; }
            .tab-btn.active { border-left-color: <?php echo PRIMARY_COLOR; ?>; }
        }
    </style>
</head>
<body>
    <?php
    $activeMenu = 'meu-plano';
    include '../../views/components/sidebar-lojista-responsiva.php';
    ?>

    <div class="main-content">
        <div class="page-header">
            <h1>💰 Pagamento de Assinatura</h1>
            <a href="<?php echo STORE_SUBSCRIPTION_URL; ?>" class="btn btn-secondary">← Voltar</a>
        </div>

        <div class="payment-container">
            <!-- Resumo da Fatura -->
            <div class="card invoice-summary">
                <h2>Fatura <?php echo htmlspecialchars($fatura['numero']); ?></h2>
                <div class="amount-display">
                    <span class="amount-label">Valor a pagar</span>
                    <span class="amount-value">R$ <?php echo number_format($fatura['amount'], 2, ',', '.'); ?></span>
                </div>
                <div class="invoice-details">
                    <div>
                        <strong>Vencimento</strong><br>
                        <?php echo date('d/m/Y', strtotime($fatura['due_date'])); ?>
                    </div>
                    <div>
                        <strong>Status</strong><br>
                        <span class="badge badge-<?php echo $fatura['status']; ?>">
                            <?php echo $statusLabels[$fatura['status']] ?? 'Desconhecido'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <?php if ($fatura['status'] === 'paid'): ?>
                <!-- Fatura Paga -->
                <div class="card alert-success">
                    <div class="success-icon">✓</div>
                    <h3>Pagamento Confirmado!</h3>
                    <p>Esta fatura foi paga em <?php echo date('d/m/Y \à\s H:i', strtotime($fatura['paid_at'])); ?>.</p>
                    <?php if ($fatura['payment_method'] === 'card' && $fatura['card_brand']): ?>
                        <p style="margin-top: 12px; font-size: 14px;">
                            Método: Cartão <?php echo ucfirst($fatura['card_brand']); ?> •••• <?php echo $fatura['card_last4']; ?>
                        </p>
                    <?php elseif ($fatura['payment_method'] === 'pix'): ?>
                        <p style="margin-top: 12px; font-size: 14px;">Método: PIX</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Tabs de Método de Pagamento -->
                <div class="card">
                    <div class="payment-tabs">
                        <button class="tab-btn active" data-tab="pix">
                            <span style="font-size: 20px;">🏦</span> PIX (Instantâneo)
                        </button>
                        <button class="tab-btn" data-tab="card">
                            <span style="font-size: 20px;">💳</span> Cartão de Crédito
                        </button>
                    </div>

                    <!-- TAB: PIX -->
                    <div class="tab-content active" id="tab-pix">
                        <?php if ($needsPixGeneration): ?>
                            <div id="generatePixSection">
                                <h3 style="margin-bottom: 12px;">🔐 Gerar QR Code PIX</h3>
                                <p style="color: #666; margin-bottom: 20px;">Clique no botão abaixo para gerar seu código PIX e realizar o pagamento instantâneo.</p>
                                <button id="btnGeneratePix" class="btn btn-primary btn-large">
                                    🎯 Gerar PIX Agora
                                </button>
                            </div>
                        <?php else: ?>
                            <div id="pixDisplaySection">
                                <h3 style="margin-bottom: 20px; text-align: center;">📱 Pague com PIX</h3>

                                <div class="qr-code-container">
                                    <img src="<?php echo $fatura['pix_qr_code']; ?>" alt="QR Code PIX" class="qr-code">
                                </div>

                                <div class="pix-copy-section">
                                    <label>📋 Código PIX Copia e Cola:</label>
                                    <div class="copy-container">
                                        <input type="text" id="pixCode" value="<?php echo htmlspecialchars($fatura['pix_copia_cola']); ?>" readonly>
                                        <button id="btnCopyPix" class="btn btn-secondary">Copiar</button>
                                    </div>
                                </div>

                                <?php if ($fatura['pix_expires_at']): ?>
                                    <div style="text-align: center; padding: 14px; background: #fff3cd; border-radius: 8px; margin: 20px 0; color: #856404; font-size: 14px;">
                                        ⏰ <strong>Válido até:</strong> <?php echo date('d/m/Y \à\s H:i', strtotime($fatura['pix_expires_at'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- TAB: Cartão de Crédito -->
                    <div class="tab-content" id="tab-card">
                        <h3 style="margin-bottom: 20px;">💳 Pagamento com Cartão de Crédito</h3>

                        <form id="cardPaymentForm">
                            <div class="form-group">
                                <label for="card-element">Dados do Cartão</label>
                                <div id="card-element"></div>
                                <div id="card-errors" role="alert"></div>
                            </div>

                            <button type="submit" id="btnPayCard" class="btn btn-primary btn-large">
                                🔒 Pagar R$ <?php echo number_format($fatura['amount'], 2, ',', '.'); ?>
                            </button>

                            <div class="security-badges">
                                <div class="security-badge">
                                    <span style="color: #28a745;">🔒</span>
                                    <span>Pagamento seguro</span>
                                </div>
                                <div class="security-badge">
                                    <span style="color: #28a745;">✓</span>
                                    <span>Stripe certificado PCI</span>
                                </div>
                            </div>

                            <div class="card-brands">
                                <span>Aceitamos:</span>
                                <span>💳 Visa</span>
                                <span>💳 Mastercard</span>
                                <span>💳 Elo</span>
                                <span>💳 American Express</span>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // =============================================
        // TABS NAVIGATION
        // =============================================
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // Remove active from all tabs
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

                // Add active to clicked tab
                this.classList.add('active');
                const tabId = this.getAttribute('data-tab');
                document.getElementById('tab-' + tabId).classList.add('active');
            });
        });

        // =============================================
        // PIX: GERAR CÓDIGO
        // =============================================
        const btnGeneratePix = document.getElementById('btnGeneratePix');
        if (btnGeneratePix) {
            btnGeneratePix.addEventListener('click', async function() {
                this.disabled = true;
                this.innerHTML = '<div class="spinner"></div> Gerando PIX...';

                try {
                    const response = await fetch('<?php echo SITE_URL; ?>/api/abacatepay.php?action=create_invoice_pix', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ invoice_id: <?php echo $invoiceId; ?> })
                    });

                    const data = await response.json();

                    if (data.success && data.pix) {
                        window.location.reload();
                    } else {
                        alert('❌ Erro ao gerar PIX: ' + (data.message || 'Erro desconhecido'));
                        this.disabled = false;
                        this.innerHTML = '🎯 Gerar PIX Agora';
                    }
                } catch (error) {
                    alert('❌ Erro: ' + error.message);
                    this.disabled = false;
                    this.innerHTML = '🎯 Gerar PIX Agora';
                }
            });
        }

        // =============================================
        // PIX: COPIAR CÓDIGO
        // =============================================
        const btnCopyPix = document.getElementById('btnCopyPix');
        if (btnCopyPix) {
            btnCopyPix.addEventListener('click', async function() {
                const pixCode = document.getElementById('pixCode');
                try {
                    await navigator.clipboard.writeText(pixCode.value);
                    this.innerHTML = '✓ Copiado!';
                    this.style.background = '#28a745';
                    setTimeout(() => {
                        this.innerHTML = 'Copiar';
                        this.style.background = '';
                    }, 2000);
                } catch (err) {
                    pixCode.select();
                    document.execCommand('copy');
                    this.innerHTML = '✓ Copiado!';
                    setTimeout(() => { this.innerHTML = 'Copiar'; }, 2000);
                }
            });
        }

        // =============================================
        // STRIPE: INICIALIZAR
        // =============================================
        const stripe = Stripe('<?php echo STRIPE_PUBLISHABLE_KEY; ?>');
        const elements = stripe.elements();

        // Criar Stripe Card Element
        const cardElement = elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    color: '#32325d',
                    fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                    '::placeholder': {
                        color: '#aab7c4'
                    }
                },
                invalid: {
                    color: '#e53935',
                    iconColor: '#e53935'
                }
            }
        });

        cardElement.mount('#card-element');

        // Mostrar erros do cartão
        cardElement.on('change', function(event) {
            const displayError = document.getElementById('card-errors');
            if (event.error) {
                displayError.textContent = event.error.message;
            } else {
                displayError.textContent = '';
            }
        });

        // =============================================
        // STRIPE: PROCESSAR PAGAMENTO
        // =============================================
        const cardForm = document.getElementById('cardPaymentForm');
        cardForm.addEventListener('submit', async function(event) {
            event.preventDefault();

            const btnPayCard = document.getElementById('btnPayCard');
            btnPayCard.disabled = true;
            btnPayCard.innerHTML = '<div class="spinner"></div> Processando pagamento...';

            try {
                // 1. Criar Payment Intent no backend
                const response = await fetch('<?php echo SITE_URL; ?>/api/stripe.php?action=create_payment_intent&invoice_id=<?php echo $invoiceId; ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                });

                const data = await response.json();

                if (!data.success || !data.client_secret) {
                    throw new Error(data.message || 'Erro ao criar pagamento');
                }

                // 2. Confirmar pagamento com Stripe.js
                const result = await stripe.confirmCardPayment(data.client_secret, {
                    payment_method: {
                        card: cardElement
                    }
                });

                if (result.error) {
                    // Erro no pagamento
                    document.getElementById('card-errors').textContent = result.error.message;
                    btnPayCard.disabled = false;
                    btnPayCard.innerHTML = '🔒 Pagar R$ <?php echo number_format($fatura['amount'], 2, ',', '.'); ?>';
                } else {
                    // Pagamento bem-sucedido!
                    if (result.paymentIntent.status === 'succeeded') {
                        btnPayCard.innerHTML = '✓ Pagamento Confirmado!';
                        btnPayCard.style.background = '#28a745';

                        // Recarregar página após 2 segundos
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    }
                }
            } catch (error) {
                document.getElementById('card-errors').textContent = error.message;
                btnPayCard.disabled = false;
                btnPayCard.innerHTML = '🔒 Pagar R$ <?php echo number_format($fatura['amount'], 2, ',', '.'); ?>';
            }
        });
    </script>
    <script src="/assets/js/sidebar-lojista.js"></script>
</body>
</html>
