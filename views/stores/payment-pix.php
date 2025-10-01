<?php
// views/stores/payment-pix.php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../controllers/AuthController.php';

session_start();

if (!AuthController::isAuthenticated() || !AuthController::isStore()) {
    header("Location: " . LOGIN_URL . "?error=acesso_restrito");
    exit;
}

$userId = AuthController::getCurrentUserId();
$db = Database::getConnection();

// Obter dados da loja
$storeQuery = $db->prepare("SELECT id, nome_fantasia FROM lojas WHERE usuario_id = ?");
$storeQuery->execute([$userId]);
$store = $storeQuery->fetch(PDO::FETCH_ASSOC);

if (!$store) {
    header('Location: ' . LOGIN_URL . '?error=loja_nao_encontrada');
    exit;
}

$paymentId = $_GET['payment_id'] ?? 0;
$storeId = $store['id'];

// Buscar dados do pagamento
$paymentStmt = $db->prepare("
    SELECT * FROM pagamentos_comissao 
    WHERE id = ? AND loja_id = ? AND status IN ('pendente', 'pix_aguardando')
");
$paymentStmt->execute([$paymentId, $storeId]);
$payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    header('Location: ' . STORE_PENDING_TRANSACTIONS_URL . '?error=pagamento_nao_encontrado');
    exit;
}

// Verificar se já existe PIX gerado (para recuperar estado)
$hasExistingPix = !empty($payment['mp_payment_id']) && !empty($payment['mp_qr_code']) && !empty($payment['mp_qr_code_base64']);

$activeMenu = 'payment-pix';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento PIX - Klube Cash</title>
    <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
    <link rel="stylesheet" href="../../assets/css/views/stores/payment-pix.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="../../assets/js/mercadopago-sdk.js?v=2.1.0"></script>
</head>
<body>
    <?php include_once '../components/sidebar-store.php'; ?>
    
    <div class="main-content" id="mainContent">
        <!-- Header Moderno -->
        <div class="pix-header">
            <div class="header-content">
                <div class="header-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                        <line x1="1" y1="10" x2="23" y2="10"/>
                    </svg>
                </div>
                <div class="header-text">
                    <h1>Pagamento via PIX</h1>
                    <p>Pague suas comissões de forma rápida e segura</p>
                </div>
                <div class="header-amount">
                    <span class="amount-label">Valor total</span>
                    <span class="amount-value">R$ <?php echo number_format($payment['valor_total'], 2, ',', '.'); ?></span>
                </div>
            </div>
        </div>

        <!-- Container Principal -->
        <div class="pix-container">
            <!-- Painel de Etapas -->
            <div class="steps-panel">
                <div class="step" id="step1" data-step="1">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3>Gerar PIX</h3>
                        <p>Criar código de pagamento</p>
                    </div>
                </div>
                
                <div class="step" id="step2" data-step="2">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3>Pagar</h3>
                        <p>Usar app do seu banco</p>
                    </div>
                </div>
                
                <div class="step" id="step3" data-step="3">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h3>Confirmado</h3>
                        <p>Cashback liberado</p>
                    </div>
                </div>
            </div>

            <!-- Painel Principal de Conteúdo -->
            <div class="content-panel">
                <!-- Estado Inicial -->
                <div class="payment-state" id="initialState">
                    <div class="state-icon">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M12 2v4"/>
                            <path d="m16.2 7.8 2.9-2.9"/>
                            <path d="M18 12h4"/>
                            <path d="m16.2 16.2 2.9 2.9"/>
                            <path d="M12 18v4"/>
                            <path d="m4.9 19.1 2.9-2.9"/>
                            <path d="M2 12h4"/>
                            <path d="m4.9 4.9 2.9 2.9"/>
                        </svg>
                    </div>
                    <h2>Vamos gerar seu PIX?</h2>
                    <p class="state-description">
                        Clique no botão abaixo para criar o código PIX. 
                        Em seguida, você poderá pagar usando o app do seu banco.
                    </p>
                    <div class="payment-details-summary">
                        <div class="detail-item">
                            <span class="label">Transações:</span>
                            <span class="value" id="transactionCount">Carregando...</span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Método:</span>
                            <span class="value">PIX Instantâneo</span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Aprovação:</span>
                            <span class="value">Automática</span>
                        </div>
                    </div>
                    
                    <button class="pix-action-btn primary" onclick="generatePix()" id="generatePixBtn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                            <line x1="1" y1="10" x2="23" y2="10"/>
                        </svg>
                        Gerar PIX Agora
                    </button>
                </div>

                <!-- Estado do QR Code -->
                <div class="payment-state" id="qrCodeState" style="display: none;">
                    <div class="qr-section">
                        <h2>Escaneie o QR Code</h2>
                        <p class="qr-instruction">
                            Abra o app do seu banco e escaneie o código abaixo, 
                            ou copie e cole o código PIX.
                        </p>
                        
                        <div class="qr-display">
                            <div class="qr-image-container">
                                <img id="qrCodeImage" src="" alt="QR Code PIX" style="display: none;">
                                <div class="qr-loading" id="qrLoading">
                                    <div class="spinner"></div>
                                    <span>Gerando QR Code...</span>
                                </div>
                            </div>
                            
                            <div class="qr-code-section">
                                <label for="pixCode" class="code-label">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                                        <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
                                    </svg>
                                    Código PIX
                                </label>
                                <div class="code-input-container">
                                    <textarea id="pixCode" readonly placeholder="Código PIX será exibido aqui..."></textarea>
                                    <button class="copy-btn" onclick="copyPixCode()" id="copyBtn" disabled>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                                        </svg>
                                        Copiar
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="qr-actions">
                            <button class="pix-action-btn secondary" onclick="checkPaymentStatus()">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 12c-1 0-3-1-3-3s2-3 3-3 3 1 3 3-2 3-3 3"/>
                                    <path d="M9 12c1 0 3-1 3-3s-2-3-3-3-3 1-3 3 2 3 3 3"/>
                                    <path d="M21 12c-1 0-3 1-3 3s2 3 3 3 3-1 3-3-2-3-3-3"/>
                                    <path d="M9 12c1 0 3 1 3 3s-2 3-3 3-3-1-3-3 2-3 3-3"/>
                                </svg>
                                Verificar Pagamento
                            </button>
                        </div>

                        <div class="payment-timer">
                            <div class="timer-icon">⏱️</div>
                            <span>Aguardando pagamento...</span>
                            <div class="pulse-indicator"></div>
                        </div>
                    </div>
                </div>

                <!-- Estado de Sucesso -->
                <div class="payment-state success-state" id="successState" style="display: none;">
                    <div class="success-animation">
                        <div class="success-icon">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                        </div>
                    </div>
                    <h2>Pagamento Confirmado!</h2>
                    <p class="success-description">
                        Seu PIX foi processado com sucesso. 
                        O cashback foi liberado automaticamente para seus clientes.
                    </p>
                    <div class="success-actions">
                        <a href="<?php echo STORE_PAYMENT_HISTORY_URL; ?>" class="pix-action-btn primary">
                            Ver Histórico de Pagamentos
                        </a>
                        <a href="<?php echo STORE_PENDING_TRANSACTIONS_URL; ?>" class="pix-action-btn secondary">
                            Voltar às Comissões
                        </a>
                    </div>
                </div>
            </div>

            <!-- Painel de Informações -->
            <div class="info-panel">
                <div class="info-section">
                    <h3>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="16" x2="12" y2="12"/>
                            <line x1="12" y1="8" x2="12.01" y2="8"/>
                        </svg>
                        Como funciona
                    </h3>
                    <div class="info-list">
                        <div class="info-item">
                            <span class="info-number">1</span>
                            <div class="info-text">
                                <strong>Gere o PIX</strong>
                                <p>Clique para criar o código de pagamento instantâneo</p>
                            </div>
                        </div>
                        <div class="info-item">
                            <span class="info-number">2</span>
                            <div class="info-text">
                                <strong>Pague pelo app</strong>
                                <p>Use qualquer banco para escanear ou colar o código</p>
                            </div>
                        </div>
                        <div class="info-item">
                            <span class="info-number">3</span>
                            <div class="info-text">
                                <strong>Aprovação automática</strong>
                                <p>Em até 2 minutos o cashback é liberado</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="security-info">
                    <div class="security-badge">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/>
                            <path d="M9 12l2 2 4-4"/>
                        </svg>
                        Seguro
                    </div>
                    <span>Transação protegida pelo Mercado Pago</span>
                </div>
            </div>
        </div>

        <!-- Botão de Voltar Fixo -->
        <div class="fixed-back-btn">
            <a href="<?php echo STORE_PENDING_TRANSACTIONS_URL; ?>" class="back-btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Voltar
            </a>
        </div>
    </div>
    
    <!-- Dados ocultos para JavaScript -->
    <input type="hidden" id="mpPaymentId" value="">
    <input type="hidden" id="paymentId" value="<?php echo $paymentId; ?>">
    
    <script>
        // Variáveis globais - mantendo a lógica original
        const paymentId = document.getElementById('paymentId').value;
        let pollingInterval = null;
        
        // Elementos do DOM
        const initialState = document.getElementById('initialState');
        const qrCodeState = document.getElementById('qrCodeState');
        const successState = document.getElementById('successState');
        const generatePixBtn = document.getElementById('generatePixBtn');
        const qrCodeImage = document.getElementById('qrCodeImage');
        const qrLoading = document.getElementById('qrLoading');
        const pixCodeTextarea = document.getElementById('pixCode');
        const copyBtn = document.getElementById('copyBtn');
        const mpPaymentIdInput = document.getElementById('mpPaymentId');
        
        // Função para atualizar etapas visuais
        function updateStep(stepNumber) {
            // Remove active de todas as etapas
            document.querySelectorAll('.step').forEach(step => {
                step.classList.remove('active', 'completed');
            });
            
            // Marca etapas anteriores como completadas
            for (let i = 1; i < stepNumber; i++) {
                const step = document.getElementById(`step${i}`);
                if (step) step.classList.add('completed');
            }
            
            // Marca etapa atual como ativa
            const currentStep = document.getElementById(`step${stepNumber}`);
            if (currentStep) currentStep.classList.add('active');
        }
        
        // Função para mostrar estado específico
        function showState(stateName) {
            // Esconder todos os estados
            initialState.style.display = 'none';
            qrCodeState.style.display = 'none';
            successState.style.display = 'none';
            
            // Mostrar estado solicitado
            switch(stateName) {
                case 'initial':
                    initialState.style.display = 'block';
                    updateStep(1);
                    break;
                case 'qrcode':
                    qrCodeState.style.display = 'block';
                    updateStep(2);
                    break;
                case 'success':
                    successState.style.display = 'block';
                    updateStep(3);
                    break;
            }
        }
        
        // Gerar PIX - mantendo lógica original com melhorias visuais
        async function generatePix() {
            generatePixBtn.disabled = true;
            generatePixBtn.innerHTML = `
                <div class="btn-spinner"></div>
                Gerando PIX...
            `;
            
            console.log('Iniciando geração PIX para payment_id:', paymentId);
            
            // OBTER DEVICE ID PARA MELHOR APROVAÇÃO
            const deviceId = getPaymentDeviceId();
            console.log('Device ID gerado:', deviceId);
            
            try {
                const response = await fetch('<?php echo MP_CREATE_PAYMENT_URL; ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        payment_id: paymentId,
                        device_id: deviceId // ADICIONAR DEVICE ID
                    })
                });
                
                console.log('Response status:', response.status);
                
                const responseText = await response.text();
                console.log('Response text:', responseText);
                
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (e) {
                    console.error('Erro ao fazer parse da resposta:', e);
                    showError('Erro: Resposta inválida do servidor');
                    return;
                }
                
                console.log('Parsed result:', result);
                
                if (result.status) {
                    // Mostrar estado do QR Code
                    showState('qrcode');
                    
                    // Simular carregamento do QR
                    setTimeout(() => {
                        // Exibir QR Code
                        qrCodeImage.src = 'data:image/png;base64,' + result.data.qr_code_base64;
                        qrCodeImage.style.display = 'block';
                        qrLoading.style.display = 'none';
                        
                        // Preencher código PIX
                        pixCodeTextarea.value = result.data.qr_code;
                        copyBtn.disabled = false;
                        
                        // Salvar ID do pagamento MP
                        mpPaymentIdInput.value = result.data.mp_payment_id;
                        
                        // Iniciar polling automático
                        startPaymentPolling();
                        
                        // Mostrar notificação de sucesso
                        showNotification('QR Code gerado com sucesso!', 'success');
                    }, 1500);
                    
                } else {
                    console.error('Erro na API:', result);
                    showError('Erro ao gerar PIX: ' + result.message);
                }
                
            } catch (error) {
                console.error('Erro de conexão:', error);
                showError('Erro de conexão: ' + error.message);
            }
        }
        
        // Copiar código PIX - mantendo lógica original
        function copyPixCode() {
            const pixCode = pixCodeTextarea.value;
            navigator.clipboard.writeText(pixCode).then(() => {
                // Feedback visual no botão
                const originalText = copyBtn.innerHTML;
                copyBtn.innerHTML = `
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    Copiado!
                `;
                copyBtn.classList.add('copied');
                
                setTimeout(() => {
                    copyBtn.innerHTML = originalText;
                    copyBtn.classList.remove('copied');
                }, 2000);
                
                showNotification('Código PIX copiado!', 'success');
            }).catch(() => {
                showNotification('Erro ao copiar código', 'error');
            });
        }
        
        // Verificar status do pagamento - mantendo lógica original
        async function checkPaymentStatus() {
            const mpPaymentId = mpPaymentIdInput.value;
            
            if (!mpPaymentId) {
                showNotification('PIX não foi gerado ainda', 'warning');
                return;
            }
            
            try {
                const response = await fetch(`<?php echo MP_CHECK_STATUS_URL; ?>&mp_payment_id=${mpPaymentId}`);
                const result = await response.json();
                
                if (result.status && result.data.status === 'approved') {
                    handlePaymentCompleted();
                } else if (result.data.status === 'rejected') {
                    clearInterval(pollingInterval);
                    showError('❌ Pagamento foi rejeitado. Tente novamente.');
                    setTimeout(() => window.location.reload(), 3000);
                } else {
                    showNotification('Pagamento ainda pendente', 'info');
                }
            } catch (error) {
                console.error('Erro ao verificar status:', error);
                showNotification('Erro ao verificar status', 'error');
            }
        }
        
        // Iniciar polling automático - mantendo lógica original
        function startPaymentPolling() {
            pollingInterval = setInterval(() => {
                checkPaymentStatus();
            }, 10000); // Verificar a cada 10 segundos
        }
        
        // Quando pagamento for confirmado - mantendo lógica original
        function handlePaymentCompleted() {
            clearInterval(pollingInterval);
            
            // Mostrar estado de sucesso
            showState('success');
            
            // Animação de sucesso
            setTimeout(() => {
                document.querySelector('.success-animation').classList.add('animate');
            }, 500);
            
            showNotification('✅ Pagamento PIX confirmado! O cashback foi liberado para os clientes.', 'success');
            
            // Redirecionar após alguns segundos
            setTimeout(() => {
                window.location.href = '<?php echo STORE_PAYMENT_HISTORY_URL; ?>';
            }, 5000);
        }
        
        // Sistema de notificações
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <div class="notification-content">
                    <span class="notification-icon">
                        ${type === 'success' ? '✅' : type === 'error' ? '❌' : type === 'warning' ? '⚠️' : 'ℹ️'}
                    </span>
                    <span class="notification-message">${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Animar entrada
            setTimeout(() => notification.classList.add('show'), 100);
            
            // Remover após 4 segundos
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => document.body.removeChild(notification), 300);
            }, 4000);
        }
        
        // Mostrar erro e voltar ao estado inicial
        function showError(message) {
            showNotification(message, 'error');
            
            // Restaurar botão
            generatePixBtn.disabled = false;
            generatePixBtn.innerHTML = `
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                    <line x1="1" y1="10" x2="23" y2="10"/>
                </svg>
                Gerar PIX Agora
            `;
        }
        
        // Buscar quantidade de transações e verificar estado existente
        document.addEventListener('DOMContentLoaded', async function() {
            try {
                const response = await fetch(`../../controllers/TransactionController.php?action=payment_details&payment_id=${paymentId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'payment_id=' + paymentId
                });
                const result = await response.json();
                
                if (result.status) {
                    document.getElementById('transactionCount').textContent = result.data.totais.total_transacoes;
                }
            } catch (error) {
                console.error('Erro ao buscar detalhes:', error);
                document.getElementById('transactionCount').textContent = 'N/A';
            }
            
            // Verificar se já existe PIX gerado
            <?php if ($hasExistingPix): ?>
                // Restaurar estado do QR Code existente
                console.log('PIX já foi gerado anteriormente, restaurando estado...');
                restoreExistingPix();
            <?php else: ?>
                // Inicializar no estado inicial
                showState('initial');
            <?php endif; ?>
        });

        // Função para restaurar PIX existente
        function restoreExistingPix() {
            // Dados do PIX existente do PHP
            const existingPixData = {
                mp_payment_id: '<?php echo $payment['mp_payment_id'] ?? ''; ?>',
                qr_code: '<?php echo addslashes($payment['mp_qr_code'] ?? ''); ?>',
                qr_code_base64: '<?php echo $payment['mp_qr_code_base64'] ?? ''; ?>'
            };
            
            if (existingPixData.mp_payment_id && existingPixData.qr_code && existingPixData.qr_code_base64) {
                // Mostrar estado do QR Code
                showState('qrcode');
                
                // Preencher dados
                setTimeout(() => {
                    qrCodeImage.src = 'data:image/png;base64,' + existingPixData.qr_code_base64;
                    qrCodeImage.style.display = 'block';
                    qrLoading.style.display = 'none';
                    
                    pixCodeTextarea.value = existingPixData.qr_code;
                    copyBtn.disabled = false;
                    
                    mpPaymentIdInput.value = existingPixData.mp_payment_id;
                    
                    // Iniciar polling automático
                    startPaymentPolling();
                    
                    showNotification('PIX restaurado! Você pode continuar o pagamento.', 'info');
                }, 500);
            } else {
                console.log('Dados do PIX incompletos, iniciando novo PIX');
                showState('initial');
            }
        }
    </script>
    <style>
/* Botão de continuar pagamento PIX */
.btn-success {
    background-color: #28a745;
    border-color: #28a745;
    color: white;
}

.btn-success:hover {
    background-color: #218838;
    border-color: #1e7e34;
}

.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 5px;
    min-width: 150px;
}

.action-buttons .btn {
    width: 100%;
    font-size: 0.875rem;
    padding: 0.4rem 0.6rem;
}

/* Responsivo para mobile */
@media (max-width: 768px) {
    .action-buttons {
        min-width: 120px;
    }
    
    .action-buttons .btn {
        font-size: 0.8rem;
        padding: 0.3rem 0.5rem;
    }
}
</style>
</body>
</html>