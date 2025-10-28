    <?php
    // views/stores/batch-upload.php
    // Incluir arquivos de configuração
    require_once '../../config/constants.php';
    require_once '../../config/database.php';
    require_once '../../controllers/AuthController.php';
    require_once '../../controllers/StoreController.php';
    require_once '../../controllers/TransactionController.php';
    require_once '../../models/CashbackBalance.php';

    // Iniciar sessão e verificar autenticação
    session_start();

    // Verificar se o usuário está logado
    if (!AuthController::isAuthenticated()) {
        header('Location: ' . LOGIN_URL . '?error=' . urlencode('Você precisa fazer login para acessar esta página.'));
        exit;
    }

    // Verificar se o usuário é do tipo loja
    if (!AuthController::isStore()) {
        header('Location: ' . CLIENT_DASHBOARD_URL . '?error=' . urlencode('Acesso restrito a lojas parceiras.'));
        exit;
    }

    // Obter ID do usuário logado
    $userId = AuthController::getCurrentUserId();

    // Obter dados da loja associada ao usuário
    $db = Database::getConnection();
    $storeQuery = $db->prepare("SELECT * FROM lojas WHERE usuario_id = :usuario_id");
    $storeQuery->bindParam(':usuario_id', $userId);
    $storeQuery->execute();

    // Verificar se o usuário tem uma loja associada
    if ($storeQuery->rowCount() == 0) {
        header('Location: ' . LOGIN_URL . '?error=' . urlencode('Sua conta não está associada a nenhuma loja. Entre em contato com o suporte.'));
        exit;
    }

    // Obter os dados da loja
    $store = $storeQuery->fetch(PDO::FETCH_ASSOC);
    $storeId = $store['id'];

    // Variáveis de controle
    $uploadResult = null;
    $error = '';
    $processedCount = 0;
    $errorCount = 0;
    $skippedCount = 0;
    $detailedResults = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['batch_file'])) {
        $uploadResult = processBatchUpload($_FILES['batch_file'], $storeId);
    }

    function processBatchUpload($file, $storeId) {
        global $processedCount, $errorCount, $skippedCount, $detailedResults;
        
        // Verificar se o arquivo foi enviado corretamente
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['status' => false, 'message' => 'Erro no upload do arquivo'];
        }
        
        // Verificar tipo do arquivo
        $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileType, ['csv'])) {
            return ['status' => false, 'message' => 'Arquivo deve ser CSV'];
        }
        
        // Processar conforme o tipo
        try {
            $data = readCSV($file['tmp_name']);
            
            if (empty($data)) {
                return ['status' => false, 'message' => 'Arquivo vazio ou formato inválido'];
            }
            
            // Processar cada linha
            $processedCount = 0;
            $errorCount = 0;
            $skippedCount = 0;
            $detailedResults = [];
            
            foreach ($data as $index => $row) {
                $lineNumber = $index + 2; // +2 porque começamos do índice 0 e temos cabeçalho
                $result = processTransactionRow($row, $storeId, $lineNumber);
                
                $detailedResults[] = [
                    'linha' => $lineNumber,
                    'dados' => $row,
                    'resultado' => $result
                ];
                
                if ($result['status']) {
                    $processedCount++;
                } elseif ($result['skipped']) {
                    $skippedCount++;
                } else {
                    $errorCount++;
                }
            }
            
            return [
                'status' => true,
                'message' => 'Processamento concluído',
                'stats' => [
                    'total' => count($data),
                    'processadas' => $processedCount,
                    'erros' => $errorCount,
                    'ignoradas' => $skippedCount
                ]
            ];
            
        } catch (Exception $e) {
            error_log('Erro no upload em lote: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao processar arquivo: ' . $e->getMessage()];
        }
    }

    function readCSV($filePath) {
        $data = [];
        
        if (($handle = fopen($filePath, 'r')) !== FALSE) {
            // Ler cabeçalho
            $header = fgetcsv($handle, 1000, ',');
            
            // Ler dados
            while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
                if (count($row) >= 3) { // Mínimo: email, valor, código
                    $data[] = array_combine($header, $row);
                }
            }
            fclose($handle);
        }
        
        return $data;
    }

    function processTransactionRow($row, $storeId, $lineNumber) {
        try {
            // Verificar campos obrigatórios
            $requiredFields = ['email_cliente', 'valor_total', 'codigo_transacao'];
            foreach ($requiredFields as $field) {
                if (empty($row[$field])) {
                    return [
                        'status' => false,
                        'skipped' => false,
                        'message' => "Campo obrigatório '$field' vazio"
                    ];
                }
            }
            
            // Buscar cliente pelo email
            $db = Database::getConnection();
            $userQuery = $db->prepare("SELECT id, nome FROM usuarios WHERE email = :email AND tipo = :tipo AND status = :status");
            $userQuery->bindParam(':email', $row['email_cliente']);
            $tipoCliente = USER_TYPE_CLIENT;
            $userQuery->bindParam(':tipo', $tipoCliente);
            $status = USER_ACTIVE;
            $userQuery->bindParam(':status', $status);
            $userQuery->execute();
            
            if ($userQuery->rowCount() === 0) {
                return [
                    'status' => false,
                    'skipped' => true,
                    'message' => 'Cliente não encontrado ou inativo'
                ];
            }
            
            $client = $userQuery->fetch(PDO::FETCH_ASSOC);
            
            // Verificar se já existe transação com o mesmo código
            $checkStmt = $db->prepare("
                SELECT id FROM transacoes_cashback 
                WHERE codigo_transacao = :codigo AND loja_id = :loja_id
            ");
            $checkStmt->bindParam(':codigo', $row['codigo_transacao']);
            $checkStmt->bindParam(':loja_id', $storeId);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                return [
                    'status' => false,
                    'skipped' => true,
                    'message' => 'Transação já existe com este código'
                ];
            }
            
            // Processar uso de saldo (se especificado)
            $valorSaldoUsado = 0;
            $usarSaldo = false;
            
            if (!empty($row['valor_saldo_usado']) && floatval($row['valor_saldo_usado']) > 0) {
                $valorSaldoUsado = floatval($row['valor_saldo_usado']);
                
                // Verificar se cliente tem saldo suficiente
                $balanceModel = new CashbackBalance();
                $saldoDisponivel = $balanceModel->getStoreBalance($client['id'], $storeId);
                
                if ($saldoDisponivel < $valorSaldoUsado) {
                    return [
                        'status' => false,
                        'skipped' => false,
                        'message' => "Saldo insuficiente. Disponível: R$ " . number_format($saldoDisponivel, 2, ',', '.')
                    ];
                }
                
                if ($valorSaldoUsado > floatval($row['valor_total'])) {
                    return [
                        'status' => false,
                        'skipped' => false,
                        'message' => 'Valor do saldo usado maior que valor total da venda'
                    ];
                }
                
                $usarSaldo = true;
            }
            
            // Preparar dados da transação
            $transactionData = [
                'usuario_id' => $client['id'],
                'loja_id' => $storeId,
                'valor_total' => floatval($row['valor_total']),
                'codigo_transacao' => $row['codigo_transacao'],
                'descricao' => $row['descricao'] ?? 'Importação em lote',
                'data_transacao' => !empty($row['data_transacao']) ? $row['data_transacao'] : date('Y-m-d H:i:s'),
                'usar_saldo' => $usarSaldo ? 'sim' : 'nao',
                'valor_saldo_usado' => $valorSaldoUsado
            ];
            
            // Registrar transação
            $result = TransactionController::registerTransaction($transactionData);
            
            if ($result['status']) {
                $message = 'Transação registrada com sucesso';
                if ($usarSaldo && $valorSaldoUsado > 0) {
                    $message .= " (Saldo usado: R$ " . number_format($valorSaldoUsado, 2, ',', '.') . ")";
                }
                
                return [
                    'status' => true,
                    'skipped' => false,
                    'message' => $message,
                    'transaction_id' => $result['data']['transaction_id']
                ];
            } else {
                return [
                    'status' => false,
                    'skipped' => false,
                    'message' => $result['message']
                ];
            }
            
        } catch (Exception $e) {
            error_log("Erro ao processar linha $lineNumber: " . $e->getMessage());
            return [
                'status' => false,
                'skipped' => false,
                'message' => 'Erro interno: ' . $e->getMessage()
            ];
        }
    }

    // Definir menu ativo
    $activeMenu = 'batch-upload';
    ?>

    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Upload em Lote - Klube Cash</title>
        <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
        <link rel="stylesheet" href="/assets/css/sidebar-lojista_sest.css">
        <link rel="stylesheet" href="../../assets/css/views/stores/batch-upload.css">
    </head>
    <body>
        <div class="dashboard-container">
            <!-- Incluir sidebar/menu lateral -->
            <?php
            $activeMenu = 'nova-venda'; // Menu ativo para upload em lote
            include '../../views/components/sidebar-lojista-responsiva.php';
            ?>
            
            <div class="main-content" id="mainContent">
                <div class="dashboard-header">
                    <div>
                        <h1 class="dashboard-title">Upload em Lote</h1>
                        <p class="welcome-user">Importe múltiplas transações de uma vez</p>
                    </div>
                </div>
                
                <!-- Resultados do Upload -->
                <?php if ($uploadResult): ?>
                    <?php if ($uploadResult['status']): ?>
                    <div class="results-section">
                        <h3>Resultado do Processamento</h3>
                        <div class="results-stats">
                            <div class="results-stat success">
                                <span class="results-stat-number"><?php echo $processedCount; ?></span>
                                <span class="results-stat-label">Processadas</span>
                            </div>
                            <div class="results-stat error">
                                <span class="results-stat-number"><?php echo $errorCount; ?></span>
                                <span class="results-stat-label">Erros</span>
                            </div>
                            <div class="results-stat warning">
                                <span class="results-stat-number"><?php echo $skippedCount; ?></span>
                                <span class="results-stat-label">Ignoradas</span>
                            </div>
                            <div class="results-stat">
                                <span class="results-stat-number"><?php echo count($detailedResults); ?></span>
                                <span class="results-stat-label">Total</span>
                            </div>
                        </div>
                        
                        <div class="results-details">
                            <h4>Detalhes por Linha</h4>
                            <?php foreach ($detailedResults as $detail): ?>
                            <div class="result-item <?php echo $detail['resultado']['status'] ? 'success' : ($detail['resultado']['skipped'] ? 'skipped' : 'error'); ?>">
                                <div class="result-item-header">
                                    <span class="result-item-line">Linha <?php echo $detail['linha']; ?></span>
                                    <span class="result-item-status <?php echo $detail['resultado']['status'] ? 'success' : ($detail['resultado']['skipped'] ? 'skipped' : 'error'); ?>">
                                        <?php echo $detail['resultado']['status'] ? 'Sucesso' : ($detail['resultado']['skipped'] ? 'Ignorada' : 'Erro'); ?>
                                    </span>
                                </div>
                                <div class="result-item-message"><?php echo htmlspecialchars($detail['resultado']['message']); ?></div>
                                <div class="result-item-data">
                                    Cliente: <?php echo htmlspecialchars($detail['dados']['email_cliente']); ?> | 
                                    Valor: R$ <?php echo isset($detail['dados']['valor_total']) ? number_format($detail['dados']['valor_total'], 2, ',', '.') : '0,00'; ?> | 
                                    Código: <?php echo htmlspecialchars($detail['dados']['codigo_transacao']); ?>
                                    <?php if (!empty($detail['dados']['valor_saldo_usado']) && floatval($detail['dados']['valor_saldo_usado']) > 0): ?>
                                    | Saldo usado: R$ <?php echo number_format($detail['dados']['valor_saldo_usado'], 2, ',', '.'); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="alert error">
                        <div>
                            <h4>Erro no processamento</h4>
                            <p><?php echo htmlspecialchars($uploadResult['message']); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <!-- Template Download -->
                <div class="template-section">
                    <div class="template-header">
                        <h3>Template do Arquivo</h3>
                        <a href="../../downloads/template-upload-lote.csv" class="download-template-btn" download>
                            Baixar Template CSV
                        </a>
                    </div>
                    
                    <p>Use o template abaixo para organizar seus dados. Campos marcados com <span class="required">*</span> são obrigatórios.</p>
                    
                    <div class="template-preview">
                        <table class="template-table">
                            <thead>
                                <tr>
                                    <th>email_cliente <span class="required">*</span></th>
                                    <th>valor_total <span class="required">*</span></th>
                                    <th>codigo_transacao <span class="required">*</span></th>
                                    <th>data_transacao <span class="optional">(opcional)</span></th>
                                    <th>descricao <span class="optional">(opcional)</span></th>
                                    <th>valor_saldo_usado <span class="optional">(opcional)</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>cliente@email.com</td>
                                    <td>100.50</td>
                                    <td>VENDA001</td>
                                    <td>2025-01-15 14:30:00</td>
                                    <td>Compra produtos diversos</td>
                                    <td>25.00</td>
                                </tr>
                                <tr>
                                    <td>outro@email.com</td>
                                    <td>250.75</td>
                                    <td>VENDA002</td>
                                    <td></td>
                                    <td>Compra especial</td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="form-help">
                        <h4>Instruções:</h4>
                        <ul>
                            <li><strong>email_cliente:</strong> Email do cliente cadastrado no Klube Cash</li>
                            <li><strong>valor_total:</strong> Valor total da venda (use ponto como separador decimal)</li>
                            <li><strong>codigo_transacao:</strong> Código único da venda no seu sistema</li>
                            <li><strong>data_transacao:</strong> Data e hora da venda (formato: AAAA-MM-DD HH:MM:SS). Se vazio, usará a data/hora atual</li>
                            <li><strong>descricao:</strong> Descrição adicional da venda</li>
                            <li><strong>valor_saldo_usado:</strong> Valor do saldo do cliente usado nesta venda. O sistema verificará se o cliente tem saldo suficiente</li>
                        </ul>
                    </div>
                </div>
                
                <!-- Upload Area -->
                <div class="content-card">
                    <form id="uploadForm" method="POST" enctype="multipart/form-data">
                        <div class="upload-area" id="uploadArea">
                            <div class="upload-icon">📁</div>
                            <div class="upload-text">Clique ou arraste seu arquivo aqui</div>
                            <div class="upload-hint">Aceita arquivos CSV (máx. 10MB)</div>
                            <input type="file" id="batchFile" name="batch_file" class="file-input" accept=".csv">
                        </div>
                        
                        <div id="fileInfo" class="file-info" style="display: none;">
                            <div class="file-name" id="fileName"></div>
                            <div class="file-size" id="fileSize"></div>
                        </div>
                        
                        <div class="progress-container" id="progressContainer">
                            <div class="progress-bar">
                                <div class="progress-fill" id="progressFill">0%</div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" id="uploadBtn" class="btn btn-primary" disabled>
                                Processar Arquivo
                            </button>
                            <a href="<?php echo STORE_DASHBOARD_URL; ?>" class="btn btn-secondary">Voltar ao Dashboard</a>
                        </div>
                    </form>
                </div>
                
                <!-- Help Section -->
                <div class="help-section">
                    <h3>Dicas Importantes</h3>
                    <div class="accordion">
                        <div class="accordion-item">
                            <button class="accordion-header">
                                <span>Como funciona o uso de saldo?</span>
                                <span class="accordion-icon">+</span>
                            </button>
                            <div class="accordion-content">
                                <p>Você pode especificar um valor de saldo que o cliente usou na compra. O sistema verifica se o cliente tem saldo suficiente e debita automaticamente. O cashback é calculado apenas sobre o valor efetivamente pago (valor total - saldo usado).</p>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <button class="accordion-header">
                                <span>E se um cliente não for encontrado?</span>
                                <span class="accordion-icon">+</span>
                            </button>
                            <div class="accordion-content">
                                <p>Transações de clientes não cadastrados no Klube Cash serão ignoradas automaticamente. Apenas clientes ativos podem receber cashback. Você pode convidar esses clientes a se cadastrarem na plataforma.</p>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <button class="accordion-header">
                                <span>O que acontece com transações duplicadas?</span>
                                <span class="accordion-icon">+</span>
                            </button>
                            <div class="accordion-content">
                                <p>Transações com códigos já existentes são ignoradas para evitar duplicatas. Certifique-se de usar códigos únicos para cada venda.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const uploadArea = document.getElementById('uploadArea');
                const fileInput = document.getElementById('batchFile');
                const fileInfo = document.getElementById('fileInfo');
                const fileName = document.getElementById('fileName');
                const fileSize = document.getElementById('fileSize');
                const uploadBtn = document.getElementById('uploadBtn');
                const uploadForm = document.getElementById('uploadForm');
                const progressContainer = document.getElementById('progressContainer');
                const progressFill = document.getElementById('progressFill');
                
                // Click to upload
                uploadArea.addEventListener('click', () => fileInput.click());
                
                // Drag and drop
                uploadArea.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    uploadArea.classList.add('dragover');
                });
                
                uploadArea.addEventListener('dragleave', () => {
                    uploadArea.classList.remove('dragover');
                });
                
                uploadArea.addEventListener('drop', (e) => {
                    e.preventDefault();
                    uploadArea.classList.remove('dragover');
                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        fileInput.files = files;
                        handleFileSelect(files[0]);
                    }
                });
                
                // File selection
                fileInput.addEventListener('change', (e) => {
                    if (e.target.files.length > 0) {
                        handleFileSelect(e.target.files[0]);
                    }
                });
                
                function handleFileSelect(file) {
                    const maxSize = 10 * 1024 * 1024; // 10MB
                    
                    if (file.size > maxSize) {
                        alert('Arquivo muito grande. Tamanho máximo: 10MB');
                        fileInput.value = '';
                        return;
                    }
                    
                    if (!file.name.match(/\.csv$/i)) {
                        alert('Tipo de arquivo não suportado. Use apenas CSV');
                        fileInput.value = '';
                        return;
                    }
                    
                    fileName.textContent = file.name;
                    fileSize.textContent = formatFileSize(file.size);
                    fileInfo.style.display = 'block';
                    uploadBtn.disabled = false;
                }
                
                function formatFileSize(bytes) {
                    if (bytes === 0) return '0 Bytes';
                    const k = 1024;
                    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                    const i = Math.floor(Math.log(bytes) / Math.log(k));
                    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
                }
                
                // Form submission with progress
                uploadForm.addEventListener('submit', function(e) {
                    if (!fileInput.files[0]) {
                        e.preventDefault();
                        alert('Selecione um arquivo primeiro');
                        return;
                    }
                    
                    // Show progress bar
                    progressContainer.style.display = 'block';
                    uploadBtn.disabled = true;
                    uploadBtn.innerHTML = 'Processando...';
                    
                    // Simulate progress
                    let progress = 0;
                    const interval = setInterval(() => {
                        progress += 10;
                        progressFill.style.width = progress + '%';
                        progressFill.textContent = progress + '%';
                        
                        if (progress >= 90) {
                            clearInterval(interval);
                            progressFill.textContent = 'Quase pronto...';
                        }
                    }, 200);
                });
                
                // Setup accordion
                setupAccordion();
            });
            
            function setupAccordion() {
                const accordionItems = document.querySelectorAll('.accordion-item');
                
                accordionItems.forEach(item => {
                    const header = item.querySelector('.accordion-header');
                    const content = item.querySelector('.accordion-content');
                    const icon = item.querySelector('.accordion-icon');
                    
                    header.addEventListener('click', () => {
                        const isActive = item.classList.contains('active');
                        
                        // Close all items
                        accordionItems.forEach(i => {
                            i.classList.remove('active');
                            i.querySelector('.accordion-content').style.maxHeight = '0';
                            i.querySelector('.accordion-icon').textContent = '+';
                        });
                        
                        // Open clicked item if it wasn't active
                        if (!isActive) {
                            item.classList.add('active');
                            content.style.maxHeight = content.scrollHeight + 'px';
                            icon.textContent = '-';
                        }
                    });
                });
            }
        </script>
        <script src="/assets/js/sidebar-lojista.js"></script>
    </body>
    </html>