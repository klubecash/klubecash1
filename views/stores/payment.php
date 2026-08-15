<?php
// views/stores/payment.php
// Incluir arquivos necessários
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../controllers/AuthController.php';
require_once '../../controllers/TransactionController.php';
require_once '../../models/CashbackBalance.php';
require_once '../../models/PaymentReceipt.php';

// Iniciar sessão
session_start();

// Verificar se o usuário está logado e é uma loja
if (!AuthController::hasStoreAccess()) {
    header("Location: " . LOGIN_URL . "?error=acesso_restrito");
    exit;
}

// Obter ID do usuário logado
$storeId = (int) AuthController::getStoreId();

// Obter dados da loja associada ao usuário
$db = Database::getConnection();
$activeMenu = 'pagamentos';
$storeQuery = $db->prepare("SELECT id, nome_fantasia FROM lojas WHERE id = :loja_id");
$storeQuery->bindParam(':loja_id', $storeId, PDO::PARAM_INT);
$storeQuery->execute();

// Verificar se o usuário tem uma loja associada
if ($storeQuery->rowCount() == 0) {
    header('Location: ' . LOGIN_URL . '?error=' . urlencode('Sua conta não está associada a nenhuma loja. Entre em contato com o suporte.'));
    exit;
}

// Obter os dados da loja
$store = $storeQuery->fetch(PDO::FETCH_ASSOC);
$storeId = (int) $store['id'];
$storeName = $store['nome_fantasia'];

// Verificar se viemos da página de comissões pendentes
$selectedTransactions = [];
$totalValue = 0;
$totalOriginalValue = 0;
$totalBalanceUsed = 0;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'payment_form') {
        // Receber transações selecionadas da página anterior
        if (isset($_POST['transacoes']) && is_array($_POST['transacoes'])) {
            $selectedTransactions = $_POST['transacoes'];
            
            // Buscar dados das transações selecionadas com informações de saldo
            if (!empty($selectedTransactions)) {
                $placeholders = implode(',', array_fill(0, count($selectedTransactions), '?'));
                $stmt = $db->prepare("
                    SELECT 
                        t.*, 
                        u.nome as cliente_nome,
                        COALESCE(
                            (SELECT SUM(cm.valor) 
                             FROM cashback_movimentacoes cm 
                             WHERE cm.usuario_id = t.usuario_id 
                             AND cm.loja_id = t.loja_id 
                             AND cm.tipo_operacao = 'uso'
                             AND cm.transacao_uso_id = t.id), 0
                        ) as saldo_usado
                    FROM transacoes_cashback t
                    JOIN usuarios u ON t.usuario_id = u.id
                    WHERE t.id IN ($placeholders) AND t.loja_id = ? AND t.status = 'pendente'
                ");
                
                $params = array_merge($selectedTransactions, [$storeId]);
                $stmt->execute($params);
                $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($transactions as $transaction) {
                    $saldoUsado = $transaction['saldo_usado'] ?? 0;
                    $valorOriginal = $transaction['valor_total'];
                    $valorCobrado = $valorOriginal - $saldoUsado;

                    $totalOriginalValue += $valorOriginal;
                    $totalBalanceUsed += $saldoUsado;
                    $totalValue += $transaction['valor_cashback']; // Comissão Total a Pagar
                }
            }
        } else {
            $error = 'Nenhuma transação selecionada.';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'process_payment') {
        // Processar o pagamento
        $transactionIds = $_POST['transaction_ids'] ?? '';
        $metodoPagamento = $_POST['metodo_pagamento'] ?? '';
        $numeroReferencia = $_POST['numero_referencia'] ?? '';
        $observacao = $_POST['observacao'] ?? '';
        $valorTotal = floatval($_POST['valor_total'] ?? 0);
        
        if (empty($transactionIds) || empty($metodoPagamento) || $valorTotal <= 0) {
            $error = 'Dados obrigatórios não informados.';
        } else {
            $receiptData = null;

            try {
                $receiptData = PaymentReceipt::validateUpload($_FILES['comprovante'] ?? []);
                // Falha antes do pagamento caso a tabela ainda nao possa ser criada/acessada.
                PaymentReceipt::ensureStorageAvailable();
            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            } catch (Throwable $exception) {
                error_log('Erro ao preparar armazenamento do comprovante: ' . $exception->getMessage());
                $error = 'Não foi possível preparar o comprovante. Tente novamente em instantes.';
            }
            
            if (empty($error)) {
                // Preparar dados do pagamento
                $paymentData = [
                    'loja_id' => $storeId,
                    'transacoes' => explode(',', $transactionIds),
                    'valor_total' => $valorTotal,
                    'metodo_pagamento' => $metodoPagamento,
                    'numero_referencia' => $numeroReferencia,
                    // O marcador so e gravado depois que o controlador confirmar o payment_id.
                    'comprovante' => '',
                    'observacao' => $observacao,
                    'receipt_data' => $receiptData
                ];
                
                // Registrar pagamento
                $result = TransactionController::registerPayment($paymentData);
                
                if ($result['status']) {
                    $success = $result['message'];
                    // Pagamento, associações e comprovante confirmados na mesma transação.
                    $selectedTransactions = [];
                    $totalValue = 0;
                    $totalOriginalValue = 0;
                    $totalBalanceUsed = 0;
                } else {
                    $error = $result['message'];
                }
            }
        }
    }
}

// Se não temos transações selecionadas, redirecionar
if (empty($selectedTransactions) && !$success && !$error) {
    header('Location: ' . STORE_PENDING_TRANSACTIONS_URL);
    exit;
}

// Buscar dados das transações para exibir com informações de saldo
$transactions = [];
if (!empty($selectedTransactions)) {
    $placeholders = implode(',', array_fill(0, count($selectedTransactions), '?'));
    $stmt = $db->prepare("
        SELECT 
            t.*, 
            u.nome as cliente_nome,
            COALESCE(
                (SELECT SUM(cm.valor) 
                 FROM cashback_movimentacoes cm 
                 WHERE cm.usuario_id = t.usuario_id 
                 AND cm.loja_id = t.loja_id 
                 AND cm.tipo_operacao = 'uso'
                 AND cm.transacao_uso_id = t.id), 0
            ) as saldo_usado
        FROM transacoes_cashback t
        JOIN usuarios u ON t.usuario_id = u.id
        WHERE t.id IN ($placeholders) AND t.loja_id = ?
    ");
    
    $params = array_merge($selectedTransactions, [$storeId]);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recalcular totais
    $totalValue = 0;
    $totalOriginalValue = 0;
    $totalBalanceUsed = 0;
    foreach ($transactions as $transaction) {
        $saldoUsado = $transaction['saldo_usado'] ?? 0;
        $totalOriginalValue += $transaction['valor_total'];
        $totalBalanceUsed += $saldoUsado;
        $totalValue += $transaction['valor_cashback']; // Comissão Total a Pagar
    }
}

$activeMenu = 'pagamentos';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
    <title>Realizar Pagamento - Klube Cash</title>
    <link rel="stylesheet" href="../../assets/css/views/stores/payment.css">
    <link rel="stylesheet" href="/assets/css/sidebar-lojista.css">
    <?php include __DIR__ . '/../components/store-app-head.php'; ?>
</head>
<body>
    <?php include '../../views/components/sidebar-lojista-responsiva.php'; ?>
    
    <div class="main-content" id="mainContent">
        <div class="dashboard-header">
            <h1>Realizar Pagamento</h1>
            <p class="subtitle">Pague as comissões devidas para liberar o cashback aos seus clientes</p>
        </div>
        
        <?php if (!empty($success)): ?>
            <div class="alert success">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <?php echo htmlspecialchars($success); ?>
                <div style="margin-left: auto;">
                    <a href="<?php echo STORE_PAYMENT_HISTORY_URL; ?>" class="btn btn-secondary">Ver Histórico</a>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert error">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($transactions)): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Transações Selecionadas</h2>
                </div>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Cliente</th>
                                <th>Data</th>
                                <th>Valor Original</th>
                                <th>Saldo Usado</th>
                                <th>Valor Cobrado</th>
                                <th>Comissão</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                                <?php 
                                $saldoUsado = $transaction['saldo_usado'] ?? 0;
                                $valorOriginal = $transaction['valor_total'];
                                $valorCobrado = $valorOriginal - $saldoUsado;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($transaction['codigo_transacao'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($transaction['cliente_nome']); ?>
                                        <?php if ($saldoUsado > 0): ?>
                                            <span class="balance-indicator" title="Cliente usou saldo">💰</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($transaction['data_transacao'])); ?></td>
                                    <td>R$ <?php echo number_format($valorOriginal, 2, ',', '.'); ?></td>
                                    <td>
                                        <?php if ($saldoUsado > 0): ?>
                                            <span class="saldo-usado">R$ <?php echo number_format($saldoUsado, 2, ',', '.'); ?></span>
                                        <?php else: ?>
                                            <span class="sem-saldo">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong>R$ <?php echo number_format($valorCobrado, 2, ',', '.'); ?></strong>
                                        <?php if ($valorCobrado < $valorOriginal): ?>
                                            <small class="desconto">(com desconto)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong>R$ <?php echo number_format($transaction['valor_cashback'], 2, ',', '.'); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="payment-summary">
                    <div class="summary-item">
                        <span>Transações selecionadas:</span>
                        <span><?php echo count($transactions); ?></span>
                    </div>
                    <div class="summary-item">
                        <span>Valor total das vendas:</span>
                        <span class="original-value">R$ <?php echo number_format($totalOriginalValue, 2, ',', '.'); ?></span>
                    </div>
                    <div class="summary-item">
                        <span>Total saldo usado pelos clientes:</span>
                        <span class="balance-used">R$ <?php echo number_format($totalBalanceUsed, 2, ',', '.'); ?></span>
                    </div>
                    <div class="summary-item">
                        <span>Valor efetivamente cobrado:</span>
                        <span class="charged-value">R$ <?php echo number_format($totalOriginalValue - $totalBalanceUsed, 2, ',', '.'); ?></span>
                    </div>
                    <div class="summary-item total">
                        <span>Valor total a pagar ao Klube Cash:</span>
                        <span>R$ <?php echo number_format($totalValue, 2, ',', '.'); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Dados do Pagamento</h2>
                </div>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="process_payment">
                    <input type="hidden" name="transaction_ids" value="<?php echo implode(',', $selectedTransactions); ?>">
                    <input type="hidden" name="valor_total" value="<?php echo $totalValue; ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="metodo_pagamento">Método de Pagamento *</label>
                            <select id="metodo_pagamento" name="metodo_pagamento" required>
                                <option value="">Selecione o método</option>
                                <option value="pix">PIX</option>
                                <option value="transferencia">Transferência Bancária</option>
                                <option value="ted">TED</option>
                                <option value="boleto">Boleto</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="numero_referencia">Número de Referência</label>
                            <input type="text" id="numero_referencia" name="numero_referencia" 
                                   placeholder="Número da transação, ID do PIX, etc.">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="comprovante">Comprovante de Pagamento *</label>
                        <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo PaymentReceipt::MAX_FILE_SIZE; ?>">
                        <input type="file" id="comprovante" name="comprovante"
                               accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" required>
                        <small style="display: block; margin-top: 0.5rem; color: var(--medium-gray);">
                            Formatos aceitos: JPG, PNG, PDF (máx. 4MB)
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="observacao">Observações</label>
                        <textarea id="observacao" name="observacao" rows="3" 
                                  placeholder="Informações adicionais sobre o pagamento..."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Confirmar Pagamento</button>
                        <a href="<?php echo STORE_PENDING_TRANSACTIONS_URL; ?>" class="btn btn-secondary">Voltar</a>
                    </div>
                </form>
            </div>
            
            
            <div class="card info-card">
                <div class="card-header dropdown-header" onclick="toggleDropdown('payment-info')">
                    <h2 class="card-title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="info-icon">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        Entenda seu Pagamento
                    </h2>
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="dropdown-arrow" id="payment-info-arrow">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </div>
                
                <div class="dropdown-content" id="payment-info-content" style="display: none;">
                    <div class="info-content">
                        <div class="info-section">
                            <h3>📊 Como são calculadas as comissões:</h3>
                            <ul>
                                <li><strong>Valor original das vendas:</strong> Total das vendas registradas (R$ <?php echo number_format($totalOriginalValue, 2, ',', '.'); ?>)</li>
                                <li><strong>Saldo usado pelos clientes:</strong> Cashback usado como desconto (R$ <?php echo number_format($totalBalanceUsed, 2, ',', '.'); ?>)</li>
                                <li><strong>Valor efetivamente cobrado:</strong> O que realmente foi pago pelos clientes (R$ <?php echo number_format($totalOriginalValue - $totalBalanceUsed, 2, ',', '.'); ?>)</li>
                                <li><strong>Comissão devida:</strong> 10% sobre o valor efetivamente cobrado (R$ <?php echo number_format($totalValue, 2, ',', '.'); ?>)</li>
                            </ul>
                        </div>
                        
                        <div class="info-section">
                            <h3>💰 Sobre o uso de saldo pelos clientes:</h3>
                            <ul>
                                <li>Quando um cliente usa seu saldo de cashback, ele recebe desconto na compra</li>
                                <li>A comissão é calculada apenas sobre o valor que o cliente efetivamente pagou</li>
                                <li>Isso é justo para você, pois você paga comissão apenas sobre o que realmente recebeu</li>
                                <li>O cliente ainda ganha cashback normal sobre a nova compra (5% do valor pago)</li>
                                <li><strong>Importante:</strong> O saldo do cliente só pode ser usado na sua loja</li>
                            </ul>
                        </div>
                        
                        <div class="info-section">
                            <h3>🔄 Distribuição da sua comissão de 10%:</h3>
                            <ul>
                                <li><strong>5% para o cliente:</strong> Vira cashback para usar na sua loja</li>
                                <li><strong>5% para o Klube Cash:</strong> Nossa receita pela plataforma</li>
                                <li><strong>0% para sua loja:</strong> Você não recebe cashback</li>
                            </ul>
                        </div>
                        
                        <div class="info-section">
                            <h3>🔄 Processo após o pagamento:</h3>
                            <ol>
                                <li>Sua confirmação de pagamento será analisada em até 24 horas</li>
                                <li>Após aprovação, o cashback será liberado automaticamente para os clientes</li>
                                <li>Os clientes poderão usar o cashback apenas na sua loja</li>
                                <li>Em caso de rejeição, você receberá notificação e poderá enviar novo comprovante</li>
                                <li>Mantenha o comprovante original até a confirmação da aprovação</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>


        <?php endif; ?>
    </div>
    
    <script>
        // Função para controlar dropdown
        function toggleDropdown(dropdownId) {
            const content = document.getElementById(dropdownId + '-content');
            const arrow = document.getElementById(dropdownId + '-arrow');
            
            if (content.style.display === 'none' || content.style.display === '') {
                content.style.display = 'block';
                arrow.style.transform = 'rotate(180deg)';
            } else {
                content.style.display = 'none';
                arrow.style.transform = 'rotate(0deg)';
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            // Validação do formulário
            const form = document.querySelector('form[method="POST"]');
            const comprovanteInput = document.getElementById('comprovante');
            
            if (form) {
                form.addEventListener('submit', function(e) {
                    // Validar arquivo
                    if (comprovanteInput && comprovanteInput.files.length > 0) {
                        const file = comprovanteInput.files[0];
                        const maxSize = 4 * 1024 * 1024; // 4MB
                        
                        if (file.size > maxSize) {
                            e.preventDefault();
                            alert('O arquivo do comprovante é muito grande. Tamanho máximo: 4MB');
                            return;
                        }
                        
                        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                        if (!allowedTypes.includes(file.type)) {
                            e.preventDefault();
                            alert('Tipo de arquivo não permitido. Use apenas JPG, PNG ou PDF');
                            return;
                        }
                    }
                });
            }
            
            // Preview do arquivo selecionado
            if (comprovanteInput) {
                comprovanteInput.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        const fileName = file.name;
                        const fileSize = (file.size / 1024 / 1024).toFixed(2) + ' MB';
                        
                        // Criar ou atualizar preview
                        let preview = document.getElementById('file-preview');
                        if (!preview) {
                            preview = document.createElement('div');
                            preview.id = 'file-preview';
                            preview.style.marginTop = '10px';
                            preview.style.padding = '10px';
                            preview.style.backgroundColor = '#f8f9fa';
                            preview.style.borderRadius = '5px';
                            preview.style.fontSize = '14px';
                            this.parentNode.appendChild(preview);
                        }
                        
                        preview.innerHTML = `
                            <strong>Arquivo selecionado:</strong><br>
                            📄 ${fileName}<br>
                            📏 ${fileSize}
                        `;
                    }
                });
            }
        });
    </script>
    
</body>
</html>
