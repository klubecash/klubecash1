<?php
/**
 * Loja - Pagamento PIX da Fatura
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';

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

$invoiceId = $_GET['invoice_id'] ?? null;

if (!$invoiceId) {
    header('Location: ' . STORE_SUBSCRIPTION_URL);
    exit;
}

$db = (new Database())->getConnection();

// Buscar fatura com verificação de propriedade
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

// Gerar PIX se ainda não foi gerado
$needsPixGeneration = empty($fatura['pix_qr_code']) || empty($fatura['pix_copia_cola']);

$activeMenu = 'meu-plano';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento PIX - <?php echo SYSTEM_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/store.css">
</head>
<body>
    <?php include __DIR__ . '/../components/sidebar-store.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>Pagamento via PIX</h1>
            <a href="<?php echo STORE_SUBSCRIPTION_URL; ?>" class="btn btn-secondary">← Voltar</a>
        </div>

        <div class="payment-container">
            <div class="invoice-summary">
                <h2>Fatura <?php echo htmlspecialchars($fatura['numero']); ?></h2>
                <div class="amount-display">
                    <span class="amount-label">Valor a pagar</span>
                    <span class="amount-value">R$ <?php echo number_format($fatura['amount'], 2, ',', '.'); ?></span>
                </div>
                <div class="invoice-details">
                    <p><strong>Vencimento:</strong> <?php echo date('d/m/Y', strtotime($fatura['due_date'])); ?></p>
                    <p><strong>Status:</strong> <span class="badge badge-<?php echo $fatura['status']; ?>"><?php echo ucfirst($fatura['status']); ?></span></p>
                </div>
            </div>

            <?php if ($fatura['status'] === 'paid'): ?>
                <!-- Fatura já paga -->
                <div class="alert alert-success">
                    <h3>✓ Fatura Paga</h3>
                    <p>Esta fatura já foi paga em <?php echo date('d/m/Y H:i', strtotime($fatura['paid_at'])); ?>.</p>
                </div>
            <?php else: ?>
                <!-- Área do PIX -->
                <div id="pixArea" class="pix-card">
                    <?php if ($needsPixGeneration): ?>
                        <!-- Gerar PIX -->
                        <div id="generatePixSection">
                            <h3>Gerar QR Code PIX</h3>
                            <p>Clique no botão abaixo para gerar o código PIX para pagamento.</p>
                            <button id="btnGeneratePix" class="btn btn-primary btn-large">Gerar PIX</button>
                        </div>
                    <?php else: ?>
                        <!-- Exibir PIX gerado -->
                        <div id="pixDisplaySection">
                            <h3>Pague com PIX</h3>
                            <div class="qr-code-container">
                                <img src="data:image/png;base64,<?php echo $fatura['pix_qr_code']; ?>" alt="QR Code PIX" class="qr-code">
                            </div>
                            <div class="pix-copy-section">
                                <label>Código PIX Copia e Cola:</label>
                                <div class="copy-container">
                                    <input type="text" id="pixCode" value="<?php echo htmlspecialchars($fatura['pix_copia_cola']); ?>" readonly>
                                    <button id="btnCopyPix" class="btn btn-secondary">Copiar</button>
                                </div>
                            </div>
                            <?php if ($fatura['pix_expires_at']): ?>
                                <p class="expiration-notice">
                                    <strong>Válido até:</strong> <?php echo date('d/m/Y H:i', strtotime($fatura['pix_expires_at'])); ?>
                                </p>
                            <?php endif; ?>
                            <button id="btnCheckStatus" class="btn btn-outline">Verificar Pagamento</button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Instruções -->
                <div class="instructions-card">
                    <h3>Como pagar com PIX</h3>
                    <ol>
                        <li>Abra o aplicativo do seu banco</li>
                        <li>Escolha a opção PIX</li>
                        <li>Escaneie o QR Code ou cole o código</li>
                        <li>Confirme o pagamento</li>
                        <li>Aguarde a confirmação (geralmente instantânea)</li>
                    </ol>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Gerar PIX
        const btnGeneratePix = document.getElementById('btnGeneratePix');
        if (btnGeneratePix) {
            btnGeneratePix.addEventListener('click', async function() {
                this.disabled = true;
                this.textContent = 'Gerando PIX...';

                try {
                    console.log('Iniciando geração de PIX para invoice_id:', <?php echo $invoiceId; ?>);

                    const response = await fetch('<?php echo SITE_URL; ?>/api/abacatepay.php?action=create_invoice_pix', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ invoice_id: <?php echo $invoiceId; ?> })
                    });

                    console.log('Response status:', response.status);

                    if (!response.ok) {
                        const errorText = await response.text();
                        console.error('Erro HTTP:', response.status, errorText);
                        throw new Error(`Erro HTTP ${response.status}: ${errorText}`);
                    }

                    const data = await response.json();
                    console.log('Response data:', data);

                    if (data.success && data.pix) {
                        alert('PIX gerado com sucesso! Recarregando página...');
                        window.location.reload();
                    } else {
                        const errorMsg = data.message || data.error || 'Erro desconhecido ao gerar PIX';
                        console.error('Erro na resposta:', data);
                        alert('Erro ao gerar PIX: ' + errorMsg);
                        this.disabled = false;
                        this.textContent = 'Gerar PIX';
                    }
                } catch (error) {
                    console.error('Erro na requisição:', error);
                    alert('Erro na requisição: ' + error.message + '\n\nVerifique o console do navegador para mais detalhes.');
                    this.disabled = false;
                    this.textContent = 'Gerar PIX';
                }
            });
        }

        // Copiar código PIX
        const btnCopyPix = document.getElementById('btnCopyPix');
        if (btnCopyPix) {
            btnCopyPix.addEventListener('click', function() {
                const pixCode = document.getElementById('pixCode');
                pixCode.select();
                pixCode.setSelectionRange(0, 99999);
                document.execCommand('copy');

                this.textContent = 'Copiado!';
                setTimeout(() => {
                    this.textContent = 'Copiar';
                }, 2000);
            });
        }

        // Verificar status do pagamento
        const btnCheckStatus = document.getElementById('btnCheckStatus');
        if (btnCheckStatus) {
            btnCheckStatus.addEventListener('click', async function() {
                this.disabled = true;
                this.textContent = 'Verificando...';

                try {
                    const response = await fetch('<?php echo SITE_URL; ?>/api/abacatepay.php?action=status&charge_id=<?php echo $fatura['gateway_charge_id'] ?? ''; ?>');
                    const data = await response.json();

                    if (data.success && data.status === 'paid') {
                        alert('Pagamento confirmado! Recarregando...');
                        window.location.reload();
                    } else {
                        alert('Pagamento ainda não foi confirmado. Por favor, aguarde alguns instantes.');
                    }
                } catch (error) {
                    alert('Erro ao verificar status: ' + error.message);
                } finally {
                    this.disabled = false;
                    this.textContent = 'Verificar Pagamento';
                }
            });
        }
    </script>

    <style>
        .main-content { padding: 20px; margin-left: 280px; }
        .payment-container { max-width: 800px; margin: 0 auto; }
        .invoice-summary, .pix-card, .instructions-card {
            background: white;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .amount-display {
            text-align: center;
            padding: 20px 0;
            border-top: 2px solid #f0f0f0;
            border-bottom: 2px solid #f0f0f0;
            margin: 20px 0;
        }
        .amount-label { display: block; font-size: 14px; color: #666; margin-bottom: 8px; }
        .amount-value { display: block; font-size: 36px; font-weight: 700; color: <?php echo PRIMARY_COLOR; ?>; }
        .qr-code-container {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 20px 0;
        }
        .qr-code { max-width: 300px; width: 100%; }
        .pix-copy-section { margin: 20px 0; }
        .pix-copy-section label { display: block; margin-bottom: 8px; font-weight: 500; }
        .copy-container { display: flex; gap: 10px; }
        .copy-container input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: monospace;
        }
        .expiration-notice {
            text-align: center;
            padding: 12px;
            background: #fff3cd;
            border-radius: 4px;
            margin: 20px 0;
        }
        .instructions-card ol { padding-left: 24px; }
        .instructions-card li { margin: 8px 0; }
        .alert { padding: 20px; border-radius: 8px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .badge { padding: 4px 12px; border-radius: 12px; font-size: 12px; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-paid { background: #d4edda; color: #155724; }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary { background: <?php echo PRIMARY_COLOR; ?>; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-outline { background: white; border: 2px solid <?php echo PRIMARY_COLOR; ?>; color: <?php echo PRIMARY_COLOR; ?>; }
        .btn-large { width: 100%; padding: 16px; font-size: 18px; }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }
    </style>
</body>
</html>
