<?php
// utils/PaymentProcessor.php
/**
 * Classe para processamento de pagamentos no sistema Klube Cash
 * Gerencia métodos de pagamento, cálculos de comissões e validações
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../models/Commission.php';

class PaymentProcessor {
    // Métodos de pagamento suportados
    private $supportedMethods = [
        'pix' => 'PIX',
        'transferencia' => 'Transferência Bancária',
        'boleto' => 'Boleto Bancário',
        'cartao' => 'Cartão de Crédito',
        'outro' => 'Outro'
    ];
    
    // Configurações do processador
    private $config = [
        'min_payment_value' => 10.00,  // Valor mínimo para pagamento
        'auto_approve' => false,       // Aprovar pagamentos automaticamente
        'log_transactions' => true,    // Registrar transações em log
        'notify_admin' => true         // Notificar administrador sobre novos pagamentos
    ];
    
    // Dados bancários para pagamentos
    private $bankDetails = [];
    
    /**
     * Construtor
     * 
     * @param array $config Configurações customizadas (opcional)
     */
    public function __construct($config = []) {
        // Mesclar configurações customizadas
        if (!empty($config)) {
            $this->config = array_merge($this->config, $config);
        }
        
        // Carregar dados bancários padrão
        $this->loadBankDetails();
    }
    
    /**
     * Carrega dados bancários para pagamentos
     */
    private function loadBankDetails() {
        // Dados bancários padrão (podem ser carregados de uma tabela no banco de dados)
        $this->bankDetails = [
            'pix' => [
                'chave' => 'exemplo@klubecash.com',
                'tipo_chave' => 'email',
                'beneficiario' => 'Klube Cash Serviços Digitais',
                'banco' => 'NuBank'
            ],
            'transferencia' => [
                'banco' => 'Banco do Brasil',
                'agencia' => '1234-5',
                'conta' => '12345-6',
                'tipo_conta' => 'Corrente',
                'beneficiario' => 'Klube Cash Serviços Digitais',
                'cnpj' => '12.345.678/0001-90'
            ],
            'boleto' => [
                'instituicao' => 'PagSeguro',
                'instrucoes' => 'Não receber após o vencimento'
            ]
        ];
    }
    
    /**
     * Registra um novo pagamento
     * 
     * @param array $paymentData Dados do pagamento
     * @param array $transactionIds IDs das transações associadas
     * @return array Resultado do processamento
     */
    public function registerPayment($paymentData, $transactionIds) {
        try {
            // Validar dados do pagamento
            $validationResult = $this->validatePaymentData($paymentData, $transactionIds);
            if (!$validationResult['status']) {
                return $validationResult;
            }
            
            // Verificar valor mínimo de pagamento
            if ($paymentData['valor_total'] < $this->config['min_payment_value']) {
                return [
                    'status' => false,
                    'message' => 'Valor do pagamento abaixo do mínimo de R$ ' . 
                                number_format($this->config['min_payment_value'], 2, ',', '.')
                ];
            }
            
            // Criar novo objeto de pagamento
            $payment = new Payment();
            $payment->setLojaId($paymentData['loja_id']);
            $payment->setValorTotal($paymentData['valor_total']);
            $payment->setMetodoPagamento($paymentData['metodo_pagamento']);
            
            if (isset($paymentData['numero_referencia'])) {
                $payment->setNumeroReferencia($paymentData['numero_referencia']);
            }
            
            if (isset($paymentData['observacao'])) {
                $payment->setObservacao($paymentData['observacao']);
            }
            
            if (isset($paymentData['comprovante'])) {
                $payment->setComprovante($paymentData['comprovante']);
            }
            
            // Status inicial: pendente ou aprovado se auto_approve estiver ativado
            $initialStatus = $this->config['auto_approve'] ? 'aprovado' : 'pendente';
            $payment->setStatus($initialStatus);
            
            if ($initialStatus === 'aprovado') {
                $payment->setDataAprovacao(date('Y-m-d H:i:s'));
            }
            
            // Salvar pagamento no banco de dados
            $paymentId = $payment->save();
            
            if (!$paymentId) {
                return [
                    'status' => false,
                    'message' => 'Erro ao salvar o pagamento no banco de dados'
                ];
            }
            
            // Associar transações ao pagamento
            $associationResult = $payment->associateTransactions($transactionIds);
            
            if (!$associationResult) {
                // Rollback manual (poderia ser melhor implementado com transações do banco)
                $this->logTransaction('Falha ao associar transações ao pagamento #' . $paymentId);
                return [
                    'status' => false,
                    'message' => 'Erro ao associar transações ao pagamento'
                ];
            }
            
            // Notificar administrador se configurado
            if ($this->config['notify_admin']) {
                $this->notifyAdmin($payment, $transactionIds);
            }
            
            // Registrar transação no log
            if ($this->config['log_transactions']) {
                $this->logTransaction('Novo pagamento #' . $paymentId . ' registrado. ' .
                                    'Valor: R$ ' . number_format($paymentData['valor_total'], 2, ',', '.') . '. ' .
                                    'Método: ' . $paymentData['metodo_pagamento'] . '. ' .
                                    'Status: ' . $initialStatus);
            }
            
            // Preparar resposta
            $responseMessage = 'Pagamento registrado com sucesso';
            if ($initialStatus === 'pendente') {
                $responseMessage .= '. Aguardando aprovação do administrador';
            } else {
                $responseMessage .= ' e aprovado automaticamente';
            }
            
            return [
                'status' => true,
                'message' => $responseMessage,
                'data' => [
                    'payment_id' => $paymentId,
                    'status' => $initialStatus,
                    'valor_total' => $paymentData['valor_total'],
                    'metodo_pagamento' => $paymentData['metodo_pagamento'],
                    'data_registro' => date('Y-m-d H:i:s'),
                    'transacoes_associadas' => count($transactionIds)
                ]
            ];
            
        } catch (Exception $e) {
            $this->logTransaction('Erro ao processar pagamento: ' . $e->getMessage());
            return [
                'status' => false,
                'message' => 'Erro ao processar pagamento: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Valida os dados do pagamento
     * 
     * @param array $paymentData Dados do pagamento
     * @param array $transactionIds IDs das transações
     * @return array Resultado da validação
     */
    private function validatePaymentData($paymentData, $transactionIds) {
        // Verificar campos obrigatórios
        $requiredFields = ['loja_id', 'valor_total', 'metodo_pagamento'];
        foreach ($requiredFields as $field) {
            if (!isset($paymentData[$field]) || empty($paymentData[$field])) {
                return [
                    'status' => false,
                    'message' => 'Campo obrigatório não fornecido: ' . $field
                ];
            }
        }
        
        // Validar IDs de transações
        if (!is_array($transactionIds) || empty($transactionIds)) {
            return [
                'status' => false,
                'message' => 'Nenhuma transação fornecida para o pagamento'
            ];
        }
        
        // Validar método de pagamento
        if (!array_key_exists($paymentData['metodo_pagamento'], $this->supportedMethods)) {
            return [
                'status' => false,
                'message' => 'Método de pagamento inválido ou não suportado'
            ];
        }
        
        // Validar valor total
        if (!is_numeric($paymentData['valor_total']) || $paymentData['valor_total'] <= 0) {
            return [
                'status' => false,
                'message' => 'Valor total inválido'
            ];
        }
        
        // Validar ID da loja
        if (!is_numeric($paymentData['loja_id']) || $paymentData['loja_id'] <= 0) {
            return [
                'status' => false,
                'message' => 'ID da loja inválido'
            ];
        }
        
        // Validações específicas para cada método de pagamento
        switch ($paymentData['metodo_pagamento']) {
            case 'pix':
                // Verificar se há número de referência para PIX
                if (isset($paymentData['comprovante']) && empty($paymentData['comprovante'])) {
                    return [
                        'status' => false,
                        'message' => 'Para pagamentos via PIX, o comprovante é obrigatório'
                    ];
                }
                break;
                
            case 'transferencia':
                // Verificar se há número de referência para transferência
                if (!isset($paymentData['numero_referencia']) || empty($paymentData['numero_referencia'])) {
                    return [
                        'status' => false,
                        'message' => 'Para transferências bancárias, o número de referência é obrigatório'
                    ];
                }
                break;
                
            case 'boleto':
                // Validações para boleto
                if (!isset($paymentData['numero_referencia']) || empty($paymentData['numero_referencia'])) {
                    return [
                        'status' => false,
                        'message' => 'Para boletos, o número do boleto é obrigatório no campo de referência'
                    ];
                }
                break;
        }
        
        // Todas as validações passaram
        return [
            'status' => true,
            'message' => 'Dados de pagamento validados com sucesso'
        ];
    }
    
    /**
    * Calcula o valor total das comissões para transações
    * 
    * @param array $transactionIds IDs das transações
    * @param int $storeId ID da loja
    * @return array Resultado com valor total e detalhes
    */
    public function calculateTotalCommissions($transactionIds, $storeId) {
        try {
            if (empty($transactionIds)) {
                return [
                    'status' => false,
                    'message' => 'Nenhuma transação fornecida para cálculo'
                ];
            }
            
            // Obter conexão com o banco de dados
            $db = Database::getConnection();
            
            // Construir placeholders para a query
            $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
            
            // CORREÇÃO: Buscar apenas valor_cliente (o que a loja deve pagar)
            // A loja paga o cashback do cliente, não recebe comissão
            $query = "
                SELECT 
                    id, valor_total, valor_cliente as valor_comissao, codigo_transacao 
                FROM transacoes_cashback 
                WHERE id IN ($placeholders) AND loja_id = ? AND status = ?
            ";
            
            // Preparar parâmetros
            $params = array_merge($transactionIds, [$storeId, TRANSACTION_PENDING]);
            
            // Executar consulta
            $stmt = $db->prepare($query);
            for ($i = 0; $i < count($params); $i++) {
                $stmt->bindValue($i + 1, $params[$i]);
            }
            $stmt->execute();
            
            // Obter resultados
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($transactions) !== count($transactionIds)) {
                return [
                    'status' => false,
                    'message' => 'Algumas transações não foram encontradas ou não estão pendentes'
                ];
            }
            
            // CORREÇÃO: Calcular apenas o que a loja deve pagar (cashback dos clientes)
            $totalValue = 0;
            $totalCommission = 0; // Valor que a loja deve pagar
            $transactionDetails = [];
            
            foreach ($transactions as $transaction) {
                $totalValue += $transaction['valor_total'];
                $totalCommission += $transaction['valor_comissao']; // valor_cliente
                
                $transactionDetails[] = [
                    'id' => $transaction['id'],
                    'codigo' => $transaction['codigo_transacao'],
                    'valor_total' => $transaction['valor_total'],
                    'valor_a_pagar' => $transaction['valor_comissao'] // O que a loja deve pagar
                ];
            }
            
            return [
                'status' => true,
                'data' => [
                    'total_transacoes' => count($transactions),
                    'valor_total_vendas' => $totalValue,
                    'valor_total_a_pagar' => $totalCommission, // O que a loja deve pagar
                    'transacoes' => $transactionDetails
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'status' => false,
                'message' => 'Erro ao calcular valor total das comissões: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Aprova um pagamento
     * 
     * @param int $paymentId ID do pagamento
     * @param string $observacao Observação opcional
     * @return array Resultado da operação
     */
    public function approvePayment($paymentId, $observacao = '') {
        try {
            // Carregar dados do pagamento
            $payment = new Payment();
            if (!$payment->loadById($paymentId)) {
                return [
                    'status' => false,
                    'message' => 'Pagamento não encontrado'
                ];
            }
            
            // Verificar se o pagamento já está aprovado
            if ($payment->getStatus() === 'aprovado') {
                return [
                    'status' => true,
                    'message' => 'Este pagamento já está aprovado',
                    'data' => ['payment_id' => $paymentId]
                ];
            }
            
            // Aprovar pagamento
            $result = $payment->approve($observacao);
            
            if (!$result) {
                return [
                    'status' => false,
                    'message' => 'Erro ao aprovar pagamento'
                ];
            }
            
            // Registrar no log
            if ($this->config['log_transactions']) {
                $this->logTransaction('Pagamento #' . $paymentId . ' aprovado. ' .
                                   'Valor: R$ ' . number_format($payment->getValorTotal(), 2, ',', '.') . '. ' .
                                   'Observação: ' . ($observacao ?: 'Nenhuma'));
            }
            
            return [
                'status' => true,
                'message' => 'Pagamento aprovado com sucesso',
                'data' => [
                    'payment_id' => $paymentId,
                    'valor_total' => $payment->getValorTotal(),
                    'metodo_pagamento' => $payment->getMetodoPagamento(),
                    'data_aprovacao' => date('Y-m-d H:i:s')
                ]
            ];
            
        } catch (Exception $e) {
            $this->logTransaction('Erro ao aprovar pagamento #' . $paymentId . ': ' . $e->getMessage());
            return [
                'status' => false,
                'message' => 'Erro ao aprovar pagamento: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Rejeita um pagamento
     * 
     * @param int $paymentId ID do pagamento
     * @param string $motivo Motivo da rejeição
     * @return array Resultado da operação
     */
    public function rejectPayment($paymentId, $motivo) {
        try {
            // Validar motivo
            if (empty($motivo)) {
                return [
                    'status' => false,
                    'message' => 'O motivo da rejeição é obrigatório'
                ];
            }
            
            // Carregar dados do pagamento
            $payment = new Payment();
            if (!$payment->loadById($paymentId)) {
                return [
                    'status' => false,
                    'message' => 'Pagamento não encontrado'
                ];
            }
            
            // Verificar se o pagamento já está rejeitado
            if ($payment->getStatus() === 'rejeitado') {
                return [
                    'status' => true,
                    'message' => 'Este pagamento já está rejeitado',
                    'data' => ['payment_id' => $paymentId]
                ];
            }
            
            // Rejeitar pagamento
            $result = $payment->reject($motivo);
            
            if (!$result) {
                return [
                    'status' => false,
                    'message' => 'Erro ao rejeitar pagamento'
                ];
            }
            
            // Registrar no log
            if ($this->config['log_transactions']) {
                $this->logTransaction('Pagamento #' . $paymentId . ' rejeitado. ' .
                                   'Valor: R$ ' . number_format($payment->getValorTotal(), 2, ',', '.') . '. ' .
                                   'Motivo: ' . $motivo);
            }
            
            return [
                'status' => true,
                'message' => 'Pagamento rejeitado com sucesso',
                'data' => [
                    'payment_id' => $paymentId,
                    'valor_total' => $payment->getValorTotal(),
                    'metodo_pagamento' => $payment->getMetodoPagamento(),
                    'data_rejeicao' => date('Y-m-d H:i:s'),
                    'motivo' => $motivo
                ]
            ];
            
        } catch (Exception $e) {
            $this->logTransaction('Erro ao rejeitar pagamento #' . $paymentId . ': ' . $e->getMessage());
            return [
                'status' => false,
                'message' => 'Erro ao rejeitar pagamento: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtém instruções para pagamento baseado no método selecionado
     * 
     * @param string $method Método de pagamento
     * @param float $amount Valor do pagamento
     * @return array Instruções de pagamento
     */
    public function getPaymentInstructions($method, $amount) {
        // Validar método de pagamento
        if (!array_key_exists($method, $this->supportedMethods)) {
            return [
                'status' => false,
                'message' => 'Método de pagamento inválido ou não suportado'
            ];
        }
        
        // Instruções específicas para cada método
        $instructions = [
            'title' => 'Instruções para pagamento via ' . $this->supportedMethods[$method],
            'amount' => $amount,
            'method' => $method,
            'steps' => []
        ];
        
        switch ($method) {
            case 'pix':
                $instructions['steps'] = [
                    'Abra o aplicativo do seu banco',
                    'Selecione a opção de pagamento via PIX',
                    'Utilize a chave PIX: ' . $this->bankDetails['pix']['chave'] . ' (Tipo: ' . $this->bankDetails['pix']['tipo_chave'] . ')',
                    'Informe o valor de R$ ' . number_format($amount, 2, ',', '.'),
                    'Confira os dados do beneficiário: ' . $this->bankDetails['pix']['beneficiario'],
                    'Confirme o pagamento',
                    'Faça o upload do comprovante para agilizar a aprovação'
                ];
                break;
                
            case 'transferencia':
                $instructions['steps'] = [
                    'Acesse o Internet Banking ou aplicativo do seu banco',
                    'Selecione a opção de transferência bancária',
                    'Use os seguintes dados para transferência:',
                    '   - Banco: ' . $this->bankDetails['transferencia']['banco'],
                    '   - Agência: ' . $this->bankDetails['transferencia']['agencia'],
                    '   - Conta: ' . $this->bankDetails['transferencia']['conta'] . ' (' . $this->bankDetails['transferencia']['tipo_conta'] . ')',
                    '   - Titular: ' . $this->bankDetails['transferencia']['beneficiario'],
                    '   - CNPJ: ' . $this->bankDetails['transferencia']['cnpj'],
                    'Informe o valor de R$ ' . number_format($amount, 2, ',', '.'),
                    'Confirme a transferência',
                    'Anote o número de confirmação/autenticação da transferência',
                    'Informe o número da transferência no sistema',
                    'Faça o upload do comprovante para agilizar a aprovação'
                ];
                break;
                
            case 'boleto':
                $instructions['steps'] = [
                    'Clique no botão "Gerar Boleto" abaixo',
                    'O boleto será gerado pelo ' . $this->bankDetails['boleto']['instituicao'],
                    'O valor do boleto será de R$ ' . number_format($amount, 2, ',', '.'),
                    'Você pode pagar o boleto em qualquer banco, internet banking ou casas lotéricas',
                    'Após o pagamento, o sistema será notificado automaticamente',
                    'Atenção: ' . $this->bankDetails['boleto']['instrucoes']
                ];
                break;
                
            case 'cartao':
                $instructions['steps'] = [
                    'Clique no botão "Pagar com Cartão" abaixo',
                    'Você será redirecionado para a página de pagamento segura',
                    'Informe os dados do seu cartão de crédito',
                    'Confirme o pagamento de R$ ' . number_format($amount, 2, ',', '.'),
                    'Aguarde a confirmação que aparecerá na tela',
                    'O sistema será notificado automaticamente'
                ];
                break;
                
            case 'outro':
                $instructions['steps'] = [
                    'Entre em contato com nossa equipe financeira para combinar o método de pagamento',
                    'Email: financeiro@klubecash.com',
                    'Telefone: (XX) XXXX-XXXX',
                    'Informe o valor de R$ ' . number_format($amount, 2, ',', '.') . ' que deseja pagar',
                    'Após o pagamento, informe o número de referência e faça upload do comprovante'
                ];
                break;
        }
        
        return [
            'status' => true,
            'data' => $instructions
        ];
    }
    
    /**
     * Notifica o administrador sobre um novo pagamento
     * 
     * @param Payment $payment Objeto de pagamento
     * @param array $transactionIds IDs das transações
     */
    private function notifyAdmin($payment, $transactionIds) {
        // Esta função poderia enviar email ou criar uma notificação no sistema
        // Como vou implementar a lógica de notificação, isso é apenas um esboço
        
        if (class_exists('Email')) {
            $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@klubecash.com';
            $subject = 'Novo Pagamento de Comissão #' . $payment->getId();
            
            // Obter nome da loja
            $db = Database::getConnection();
            $storeStmt = $db->prepare("SELECT nome_fantasia FROM lojas WHERE id = ?");
            $storeId = $payment->getLojaId();
            $storeStmt->execute([$storeId]);
            $store = $storeStmt->fetch(PDO::FETCH_ASSOC);
            $storeName = $store ? $store['nome_fantasia'] : 'Loja #' . $storeId;
            
            $message = "
                <h3>Novo Pagamento de Comissão</h3>
                <p>Um novo pagamento foi registrado e aguarda aprovação.</p>
                <p><strong>ID do Pagamento:</strong> {$payment->getId()}</p>
                <p><strong>Loja:</strong> {$storeName}</p>
                <p><strong>Valor:</strong> R$ " . number_format($payment->getValorTotal(), 2, ',', '.') . "</p>
                <p><strong>Método de Pagamento:</strong> {$this->supportedMethods[$payment->getMetodoPagamento()]}</p>
                <p><strong>Transações:</strong> " . count($transactionIds) . "</p>
                <p><strong>Data/Hora:</strong> " . date('d/m/Y H:i:s') . "</p>
                <p>Acesse o painel administrativo para revisar e aprovar este pagamento.</p>
            ";
            
            Email::send($adminEmail, $subject, $message);
        }
        
        // Registrar notificação no sistema (exemplo)
        if (class_exists('Notification')) {
            Notification::create(
                'admin', // Para todos os admins
                'Novo pagamento registrado',
                'Pagamento de R$ ' . number_format($payment->getValorTotal(), 2, ',', '.') . 
                ' da loja #' . $payment->getLojaId() . ' aguarda aprovação.',
                'payment', // Tipo de notificação
                $payment->getId() // ID de referência
            );
        }
    }
    
    /**
     * Registra transações no log
     * 
     * @param string $message Mensagem para o log
     */
    private function logTransaction($message) {
        $logDir = dirname(__DIR__) . '/logs';
        
        // Criar diretório de logs se não existir
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/payment_transactions.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message" . PHP_EOL;
        
        // Adicionar ao arquivo de log
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * Obtém os métodos de pagamento suportados
     * 
     * @return array Lista de métodos de pagamento
     */
    public function getSupportedMethods() {
        return $this->supportedMethods;
    }
    
    /**
     * Adiciona um novo método de pagamento
     * 
     * @param string $key Chave do método
     * @param string $name Nome do método
     */
    public function addPaymentMethod($key, $name) {
        $this->supportedMethods[$key] = $name;
    }
    
    /**
     * Atualiza os detalhes bancários para pagamentos
     * 
     * @param string $method Método de pagamento
     * @param array $details Novos detalhes
     */
    public function updateBankDetails($method, $details) {
        if (array_key_exists($method, $this->supportedMethods)) {
            $this->bankDetails[$method] = $details;
        }
    }
    
    /**
     * Obtém detalhes bancários para um método de pagamento
     * 
     * @param string $method Método de pagamento
     * @return array|null Detalhes bancários ou null se não existir
     */
    public function getBankDetails($method) {
        return $this->bankDetails[$method] ?? null;
    }
}