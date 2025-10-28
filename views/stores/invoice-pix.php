<?php
/**
 * Loja - Pagamento PIX da Fatura
 * Interface moderna com verificação automática de pagamento
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
    'expired' => 'Expirado'
];

$activeMenu = 'meu-plano';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento via PIX - <?php echo SYSTEM_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/store.css">
    <link rel="stylesheet" href="/assets/css/sidebar-lojista_sest.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background: #f5f7fa; }
        .main-content { padding: 20px; margin-left: 280px; min-height: 100vh; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-header h1 { font-size: 28px; color: #1a1a1a; font-weight: 600; }
        .payment-container { max-width: 700px; margin: 0 auto; }

        /* Cards */
        .card { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); margin-bottom: 20px; }

        /* Resumo da Fatura */
        .invoice-summary { text-align: center; }
        .invoice-summary h2 { font-size: 20px; color: #1a1a1a; margin-bottom: 16px; }
        .amount-display { padding: 24px; background: linear-gradient(135deg, <?php echo PRIMARY_COLOR; ?> 0%, #ff9533 100%); border-radius: 12px; margin: 20px 0; }
        .amount-label { display: block; font-size: 14px; color: rgba(255,255,255,0.9); margin-bottom: 8px; }
        .amount-value { display: block; font-size: 42px; font-weight: 700; color: white; }
        .invoice-details { display: flex; justify-content: space-around; padding-top: 16px; border-top: 1px solid #e0e0e0; margin-top: 16px; }

        /* Badge de Status */
        .badge { padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 500; display: inline-block; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-paid { background: #d4edda; color: #155724; }
        .badge-failed { background: #f8d7da; color: #721c24; }

        /* Área do PIX */
        .pix-card { position: relative; }
        .qr-code-container { text-align: center; padding: 30px; background: #f8f9fa; border-radius: 12px; margin: 24px 0; position: relative; }
        .qr-code { max-width: 280px; width: 100%; border: 4px solid white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }

        /* Status de Verificação */
        .checking-status { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(255,255,255,0.95); padding: 24px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); text-align: center; display: none; z-index: 10; min-width: 250px; }
        .checking-status.active { display: block; }
        .spinner { border: 3px solid #f3f3f3; border-top: 3px solid <?php echo PRIMARY_COLOR; ?>; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 12px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        /* Código Copia e Cola */
        .pix-copy-section { margin: 24px 0; }
        .pix-copy-section label { display: block; margin-bottom: 10px; font-weight: 500; color: #333; font-size: 14px; }
        .copy-container { display: flex; gap: 12px; }
        .copy-container input { flex: 1; padding: 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 13px; transition: border-color 0.3s; }
        .copy-container input:focus { outline: none; border-color: <?php echo PRIMARY_COLOR; ?>; }

        /* Botões */
        .btn { padding: 14px 28px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 15px; transition: all 0.3s; text-decoration: none; display: inline-block; }
        .btn-primary { background: <?php echo PRIMARY_COLOR; ?>; color: white; }
        .btn-primary:hover { background: #e66d00; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(255,122,0,0.3); }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        .btn-outline { background: white; border: 2px solid <?php echo PRIMARY_COLOR; ?>; color: <?php echo PRIMARY_COLOR; ?>; }
        .btn-outline:hover { background: <?php echo PRIMARY_COLOR; ?>; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-large { width: 100%; padding: 18px; font-size: 16px; }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }

        /* Aviso de Expiração */
        .expiration-notice { text-align: center; padding: 14px; background: #fff3cd; border-radius: 8px; margin: 20px 0; color: #856404; font-size: 14px; }

        /* Instruções */
        .instructions-card h3 { font-size: 18px; margin-bottom: 16px; color: #1a1a1a; }
        .instructions-card ol { padding-left: 24px; }
        .instructions-card li { margin: 10px 0; line-height: 1.6; color: #555; }

        /* Alert de Sucesso */
        .alert-success { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 24px; border-radius: 12px; text-align: center; }
        .alert-success h3 { font-size: 24px; margin-bottom: 8px; }
        .alert-success .success-icon { font-size: 48px; margin-bottom: 12px; }

        /* Verificação automática */
        .auto-check-status { text-align: center; padding: 16px; background: #e7f3ff; border-radius: 8px; margin-top: 20px; }
        .auto-check-status.checking { background: #fff3cd; }
        .auto-check-status.success { background: #d4edda; animation: pulse 0.5s; }
        @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.02); } }

        /* Responsivo */
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px; }
            .page-header { flex-direction: column; gap: 16px; }
            .amount-value { font-size: 32px; }
        }
    </style>
</head>
<body>
    <?php
    $activeMenu = 'meu-plano'; // Menu ativo
    include '../../views/components/sidebar-lojista-responsiva.php';
    ?>

    <div class="main-content">
        <div class="page-header">
            <h1>💳 Pagamento via PIX</h1>
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
                </div>
            <?php else: ?>
                <!-- Área do PIX -->
                <div class="card pix-card">
                    <?php if ($needsPixGeneration): ?>
                        <!-- Gerar PIX -->
                        <div id="generatePixSection">
                            <h3 style="margin-bottom: 12px;">🔐 Gerar QR Code PIX</h3>
                            <p style="color: #666; margin-bottom: 20px;">Clique no botão abaixo para gerar seu código PIX e realizar o pagamento.</p>
                            <button id="btnGeneratePix" class="btn btn-primary btn-large">
                                🎯 Gerar PIX Agora
                            </button>
                        </div>
                    <?php else: ?>
                        <!-- Exibir PIX Gerado -->
                        <div id="pixDisplaySection">
                            <h3 style="margin-bottom: 20px; text-align: center;">📱 Pague com PIX</h3>

                            <div class="qr-code-container" id="qrContainer">
                                <img src="<?php echo $fatura['pix_qr_code']; ?>" alt="QR Code PIX" class="qr-code">

                                <!-- Status de Verificação -->
                                <div class="checking-status" id="checkingStatus">
                                    <div class="spinner"></div>
                                    <p style="font-weight: 500; color: #333;">Verificando pagamento...</p>
                                    <p style="font-size: 13px; color: #666; margin-top: 8px;">Aguarde alguns instantes</p>
                                </div>
                            </div>

                            <!-- Código Copia e Cola -->
                            <div class="pix-copy-section">
                                <label>📋 Código PIX Copia e Cola:</label>
                                <div class="copy-container">
                                    <input type="text" id="pixCode" value="<?php echo htmlspecialchars($fatura['pix_copia_cola']); ?>" readonly>
                                    <button id="btnCopyPix" class="btn btn-secondary">Copiar</button>
                                </div>
                            </div>

                            <?php if ($fatura['pix_expires_at']): ?>
                                <div class="expiration-notice">
                                    ⏰ <strong>Válido até:</strong> <?php echo date('d/m/Y \à\s H:i', strtotime($fatura['pix_expires_at'])); ?>
                                </div>
                            <?php endif; ?>

                            <!-- Status de Verificação Automática -->
                            <div class="auto-check-status" id="autoCheckStatus">
                                <p>🔄 Verificando pagamento automaticamente...</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Instruções -->
                <div class="card instructions-card">
                    <h3>💡 Como pagar com PIX</h3>
                    <ol>
                        <li>Abra o aplicativo do seu banco no celular</li>
                        <li>Selecione a opção <strong>Pix</strong></li>
                        <li>Escolha <strong>Pagar com QR Code</strong> ou <strong>Pix Copia e Cola</strong></li>
                        <li>Escaneie o QR Code acima ou cole o código</li>
                        <li>Confirme os dados e finalize o pagamento</li>
                        <li>✨ A confirmação é <strong>automática e instantânea</strong>!</li>
                    </ol>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // ===================================
        // GERAR PIX
        // ===================================
        const btnGeneratePix = document.getElementById('btnGeneratePix');
        if (btnGeneratePix) {
            btnGeneratePix.addEventListener('click', async function() {
                this.disabled = true;
                this.innerHTML = '<div style="display:inline-block;width:16px;height:16px;border:2px solid white;border-top-color:transparent;border-radius:50%;animation:spin 0.8s linear infinite;margin-right:8px;"></div> Gerando PIX...';

                try {
                    const response = await fetch('<?php echo SITE_URL; ?>/api/abacatepay.php?action=create_invoice_pix', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ invoice_id: <?php echo $invoiceId; ?> })
                    });

                    const data = await response.json();

                    if (data.success && data.pix) {
                        // Sucesso - recarregar página
                        window.location.reload();
                    } else {
                        alert('❌ Erro ao gerar PIX: ' + (data.message || 'Erro desconhecido'));
                        this.disabled = false;
                        this.innerHTML = '🎯 Gerar PIX Agora';
                    }
                } catch (error) {
                    alert('❌ Erro na requisição: ' + error.message);
                    this.disabled = false;
                    this.innerHTML = '🎯 Gerar PIX Agora';
                }
            });
        }

        // ===================================
        // COPIAR CÓDIGO PIX
        // ===================================
        const btnCopyPix = document.getElementById('btnCopyPix');
        if (btnCopyPix) {
            btnCopyPix.addEventListener('click', async function() {
                const pixCode = document.getElementById('pixCode');

                try {
                    // Usar Clipboard API moderna
                    await navigator.clipboard.writeText(pixCode.value);

                    // Feedback visual
                    this.innerHTML = '✓ Copiado!';
                    this.style.background = '#28a745';

                    setTimeout(() => {
                        this.innerHTML = 'Copiar';
                        this.style.background = '';
                    }, 2000);
                } catch (err) {
                    // Fallback para navegadores antigos
                    pixCode.select();
                    pixCode.setSelectionRange(0, 99999);
                    document.execCommand('copy');

                    this.innerHTML = '✓ Copiado!';
                    setTimeout(() => { this.innerHTML = 'Copiar'; }, 2000);
                }
            });
        }

        // ===================================
        // VERIFICAÇÃO AUTOMÁTICA DE PAGAMENTO
        // ===================================
        <?php if (!$needsPixGeneration && $fatura['status'] === 'pending'): ?>
        let checkInterval = null;
        let checkCount = 0;
        const maxChecks = 120; // 10 minutos (120 x 5 segundos)
        const chargeId = '<?php echo $fatura['gateway_charge_id'] ?? ''; ?>';

        async function checkPaymentStatus() {
            if (!chargeId) return;

            checkCount++;

            // Parar após 10 minutos
            if (checkCount > maxChecks) {
                clearInterval(checkInterval);
                document.getElementById('autoCheckStatus').innerHTML =
                    '<p style="color:#856404;">⏱️ Verificação automática encerrada. Clique em "Verificar Pagamento" para atualizar.</p>';
                return;
            }

            try {
                const response = await fetch('<?php echo SITE_URL; ?>/api/abacatepay.php?action=check_payment&invoice_id=<?php echo $invoiceId; ?>');
                const data = await response.json();

                if (data.success) {
                    if (data.status === 'paid') {
                        // PAGAMENTO CONFIRMADO!
                        clearInterval(checkInterval);

                        const statusDiv = document.getElementById('autoCheckStatus');
                        statusDiv.className = 'auto-check-status success';
                        statusDiv.innerHTML = '<p style="color:#155724;font-weight:600;font-size:16px;">✅ Pagamento Confirmado! Redirecionando...</p>';

                        // Mostrar overlay de sucesso
                        const checkingStatus = document.getElementById('checkingStatus');
                        checkingStatus.innerHTML = `
                            <div style="color:#28a745;font-size:48px;margin-bottom:12px;">✓</div>
                            <p style="font-weight:600;color:#28a745;font-size:18px;">Pagamento Confirmado!</p>
                            <p style="font-size:14px;color:#666;margin-top:8px;">Atualizando...</p>
                        `;
                        checkingStatus.classList.add('active');

                        // Recarregar após 2 segundos
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        // Atualizar contador
                        const timeWaiting = Math.floor(checkCount * 5 / 60);
                        document.getElementById('autoCheckStatus').innerHTML =
                            `<p>🔄 Aguardando pagamento... (${timeWaiting} min)</p>`;
                    }
                }
            } catch (error) {
                console.error('Erro ao verificar pagamento:', error);
            }
        }

        // Verificar a cada 5 segundos
        checkInterval = setInterval(checkPaymentStatus, 5000);

        // Primeira verificação imediata
        checkPaymentStatus();
        <?php endif; ?>
    </script>
    <script src="/assets/js/sidebar-lojista.js"></script>
</body>
</html>
