<?php
// controllers/TransactionController.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/AuthController.php';
require_once __DIR__ . '/StoreController.php';
require_once __DIR__ . '/../utils/Validator.php';


/**
 * Controlador de Transações
 * Gerencia operações relacionadas a transações, comissões e cashback
 */
class TransactionController {
    // Adicionar este método no TransactionController.php
   /**
    * Obtém todas as transações de uma loja com filtros
    * 
    * @param int $storeId ID da loja
    * @param array $filters Filtros para a listagem
    * @param int $page Página atual
    * @return array Lista de transações
    */
    public static function getStoreTransactions($storeId, $filters = [], $page = 1) {
        try {
            // Verificar se o usuário está autenticado
            if (!AuthController::isAuthenticated()) {
                return ['status' => false, 'message' => 'Usuário não autenticado.'];
            }
            
            $db = Database::getConnection();
            
            // Verificar permissões - apenas a loja dona das transações ou admin podem acessar
            if (AuthController::isStore()) {
                $currentUserId = AuthController::getCurrentUserId();
                $storeOwnerQuery = $db->prepare("SELECT usuario_id FROM lojas WHERE id = :loja_id");
                $storeOwnerQuery->bindParam(':loja_id', $storeId);
                $storeOwnerQuery->execute();
                $storeOwner = $storeOwnerQuery->fetch(PDO::FETCH_ASSOC);
                
                if (!$storeOwner || $storeOwner['usuario_id'] != $currentUserId) {
                    return ['status' => false, 'message' => 'Acesso não autorizado a esta loja.'];
                }
            } elseif (!AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Acesso não autorizado.'];
            }
            
            // Verificar se a loja existe
            $storeStmt = $db->prepare("SELECT id, nome_fantasia FROM lojas WHERE id = :loja_id");
            $storeStmt->bindParam(':loja_id', $storeId);
            $storeStmt->execute();
            $store = $storeStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$store) {
                return ['status' => false, 'message' => 'Loja não encontrada.'];
            }
            
            // Construir consulta
            $query = "
                SELECT t.*, u.nome as cliente_nome, u.email as cliente_email,
                    pc.id as pagamento_id, pc.status as status_pagamento,
                    pc.data_aprovacao as data_pagamento
                FROM transacoes_cashback t
                JOIN usuarios u ON t.usuario_id = u.id
                LEFT JOIN pagamentos_transacoes pt ON t.id = pt.transacao_id
                LEFT JOIN pagamentos_comissao pc ON pt.pagamento_id = pc.id
                WHERE t.loja_id = :loja_id
            ";
            
            $params = [':loja_id' => $storeId];
            
            // Aplicar filtros
            if (!empty($filters)) {
                // Filtro por status
                if (isset($filters['status']) && !empty($filters['status'])) {
                    $query .= " AND t.status = :status";
                    $params[':status'] = $filters['status'];
                }
                
                // Filtro por período
                if (isset($filters['data_inicio']) && !empty($filters['data_inicio'])) {
                    $query .= " AND t.data_transacao >= :data_inicio";
                    $params[':data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
                }
                
                if (isset($filters['data_fim']) && !empty($filters['data_fim'])) {
                    $query .= " AND t.data_transacao <= :data_fim";
                    $params[':data_fim'] = $filters['data_fim'] . ' 23:59:59';
                }
                
                // Filtro por cliente
                if (isset($filters['cliente']) && !empty($filters['cliente'])) {
                    $query .= " AND (u.nome LIKE :cliente OR u.email LIKE :cliente)";
                    $params[':cliente'] = '%' . $filters['cliente'] . '%';
                }
                
                // Filtro por valor mínimo
                if (isset($filters['valor_min']) && !empty($filters['valor_min'])) {
                    $query .= " AND t.valor_total >= :valor_min";
                    $params[':valor_min'] = $filters['valor_min'];
                }
                
                // Filtro por valor máximo
                if (isset($filters['valor_max']) && !empty($filters['valor_max'])) {
                    $query .= " AND t.valor_total <= :valor_max";
                    $params[':valor_max'] = $filters['valor_max'];
                }
            }
            
            // Ordenação
            $query .= " ORDER BY t.data_transacao DESC";
            
            // Contagem total para paginação
            $countQuery = str_replace(
                "t.*, u.nome as cliente_nome, u.email as cliente_email, pc.id as pagamento_id, pc.status as status_pagamento, pc.data_aprovacao as data_pagamento", 
                "COUNT(*) as total", 
                $query
            );
            $countStmt = $db->prepare($countQuery);
            
            foreach ($params as $param => $value) {
                $countStmt->bindValue($param, $value);
            }
            
            $countStmt->execute();
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Paginação
            $perPage = defined('ITEMS_PER_PAGE') ? ITEMS_PER_PAGE : 10;
            $totalPages = ceil($totalCount / $perPage);
            $page = max(1, min($page, $totalPages));
            $offset = ($page - 1) * $perPage;
            
            $query .= " LIMIT :offset, :limit";
            $params[':offset'] = $offset;
            $params[':limit'] = $perPage;
            
            // Executar consulta
            $stmt = $db->prepare($query);
            
            // Bind manual para offset e limit
            foreach ($params as $param => $value) {
                if ($param == ':offset' || $param == ':limit') {
                    $stmt->bindValue($param, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($param, $value);
                }
            }
            
            $stmt->execute();
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular totais
            $totalValorVendas = 0;
            $totalComissoes = 0;
            $totalPendentes = 0;
            $totalAprovadas = 0;
            
            foreach ($transactions as $transaction) {
                $totalValorVendas += $transaction['valor_total'];
                $totalComissoes += $transaction['valor_cashback'];
                
                if ($transaction['status'] === 'aprovado') {
                    $totalAprovadas++;
                } elseif ($transaction['status'] === 'pendente') {
                    $totalPendentes++;
                }
            }
            
            return [
                'status' => true,
                'data' => [
                    'loja' => $store,
                    'transacoes' => $transactions,
                    'totais' => [
                        'total_transacoes' => count($transactions),
                        'valor_total_vendas' => $totalValorVendas,
                        'total_comissoes' => $totalComissoes,
                        'total_pendentes' => $totalPendentes,
                        'total_aprovadas' => $totalAprovadas
                    ],
                    'paginacao' => [
                        'total' => $totalCount,
                        'por_pagina' => $perPage,
                        'pagina_atual' => $page,
                        'total_paginas' => $totalPages
                    ]
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao obter transações da loja: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao obter transações. Tente novamente.'];
        }
    }

    /**
    * Obtém histórico de pagamentos com informações de saldo usado
    * 
    * @param int $storeId ID da loja
    * @param array $filters Filtros adicionais
    * @param int $page Página atual para paginação
    * @return array Resultado da operação
    */
    public static function getPaymentHistoryWithBalance($storeId, $filters = [], $page = 1) {
        try {
            if (!AuthController::isAuthenticated()) {
                return ['status' => false, 'message' => 'Usuário não autenticado.'];
            }
            
            $db = Database::getConnection();
            $limit = ITEMS_PER_PAGE;
            $offset = ($page - 1) * $limit;
            
            // CORREÇÃO MVP: Verificar se a loja é MVP para incluir transações aprovadas automaticamente
            $storeMvpQuery = "SELECT u.mvp FROM lojas l JOIN usuarios u ON l.usuario_id = u.id WHERE l.id = :store_id";
            $storeMvpStmt = $db->prepare($storeMvpQuery);
            $storeMvpStmt->bindParam(':store_id', $storeId);
            $storeMvpStmt->execute();
            $storeMvpResult = $storeMvpStmt->fetch(PDO::FETCH_ASSOC);
            $isStoreMvp = ($storeMvpResult && $storeMvpResult['mvp'] === 'sim');
            
            error_log("PAYMENT HISTORY DEBUG: Loja {$storeId} - MVP: " . ($isStoreMvp ? 'SIM' : 'NÃO'));
            
            if ($isStoreMvp) {
                // LOJA MVP: Mostrar transações aprovadas como "pagamentos" virtuais sem cobrança
                error_log("PAYMENT HISTORY DEBUG: Usando query MVP para transações aprovadas");
                
                // Para MVP, construir condições baseadas nas transações aprovadas
                $whereConditions = ["t.loja_id = :loja_id", "t.status = 'aprovado'"];
                $params = [':loja_id' => $storeId];
                
                // Aplicar filtros nas transações
                if (!empty($filters['data_inicio'])) {
                    $whereConditions[] = "DATE(t.data_transacao) >= :data_inicio";
                    $params[':data_inicio'] = $filters['data_inicio'];
                }
                
                if (!empty($filters['data_fim'])) {
                    $whereConditions[] = "DATE(t.data_transacao) <= :data_fim";
                    $params[':data_fim'] = $filters['data_fim'];
                }
                
                // Para MVP, não há status de pagamento real, então ignorar esse filtro ou mapear para aprovado
                if (!empty($filters['status']) && $filters['status'] !== 'aprovado') {
                    // Se filtrar por pendente ou rejeitado, não mostrar nada para MVP
                    $whereConditions[] = "1 = 0"; // Condição impossível
                }
                
                $whereClause = "WHERE " . implode(" AND ", $whereConditions);
                
                // Query para transações MVP aprovadas (simular como pagamentos virtuais)
                $paymentsQuery = "
                    SELECT 
                        t.id as id,
                        'mvp_aprovado' as metodo_pagamento,
                        0.00 as valor_total,
                        t.data_transacao as data_registro,
                        t.data_transacao as data_aprovacao,
                        'aprovado' as status,
                        'Transação MVP - Aprovada automaticamente (sem cobrança de comissão)' as observacao,
                        1 as qtd_transacoes,
                        t.valor_total as valor_vendas_originais,
                        COALESCE((SELECT SUM(cm.valor) 
                                FROM cashback_movimentacoes cm 
                                WHERE cm.usuario_id = t.usuario_id 
                                AND cm.loja_id = t.loja_id 
                                AND cm.tipo_operacao = 'uso'
                                AND cm.transacao_uso_id = t.id), 0) as total_saldo_usado,
                        CASE WHEN EXISTS(
                            SELECT 1 FROM cashback_movimentacoes cm2 
                            WHERE cm2.usuario_id = t.usuario_id 
                            AND cm2.loja_id = t.loja_id 
                            AND cm2.tipo_operacao = 'uso'
                            AND cm2.transacao_uso_id = t.id
                        ) THEN 1 ELSE 0 END as qtd_com_saldo
                    FROM transacoes_cashback t
                    JOIN usuarios u ON t.usuario_id = u.id
                    $whereClause
                    ORDER BY t.data_transacao DESC
                    LIMIT :limit OFFSET :offset
                ";
            } else {
                // LOJA NORMAL: Query original com pagamentos de comissão reais
                error_log("PAYMENT HISTORY DEBUG: Usando query normal para pagamentos de comissão");
                
                $whereConditions = ["pc.loja_id = :loja_id"];
                $params = [':loja_id' => $storeId];
                
                // Aplicar filtros
                if (!empty($filters['status'])) {
                    $whereConditions[] = "pc.status = :status";
                    $params[':status'] = $filters['status'];
                }
                
                if (!empty($filters['data_inicio'])) {
                    $whereConditions[] = "DATE(pc.data_registro) >= :data_inicio";
                    $params[':data_inicio'] = $filters['data_inicio'];
                }
                
                if (!empty($filters['data_fim'])) {
                    $whereConditions[] = "DATE(pc.data_registro) <= :data_fim";
                    $params[':data_fim'] = $filters['data_fim'];
                }
                
                if (!empty($filters['metodo_pagamento'])) {
                    $whereConditions[] = "pc.metodo_pagamento = :metodo_pagamento";
                    $params[':metodo_pagamento'] = $filters['metodo_pagamento'];
                }
                
                $whereClause = "WHERE " . implode(" AND ", $whereConditions);
                
                // Query original para pagamentos com informações agregadas de saldo
                $paymentsQuery = "
                    SELECT 
                        pc.*,
                        COUNT(pt.transacao_id) as qtd_transacoes,
                        SUM(t.valor_total) as valor_vendas_originais,
                        COALESCE(SUM(
                            (SELECT SUM(cm.valor) 
                            FROM cashback_movimentacoes cm 
                            WHERE cm.usuario_id = t.usuario_id 
                            AND cm.loja_id = t.loja_id 
                            AND cm.tipo_operacao = 'uso'
                            AND cm.transacao_uso_id = t.id)
                        ), 0) as total_saldo_usado,
                        SUM(CASE WHEN EXISTS(
                            SELECT 1 FROM cashback_movimentacoes cm2 
                            WHERE cm2.usuario_id = t.usuario_id 
                            AND cm2.loja_id = t.loja_id 
                            AND cm2.tipo_operacao = 'uso'
                            AND cm2.transacao_uso_id = t.id
                        ) THEN 1 ELSE 0 END) as qtd_com_saldo
                    FROM pagamentos_comissao pc
                    LEFT JOIN pagamentos_transacoes pt ON pc.id = pt.pagamento_id
                    LEFT JOIN transacoes_cashback t ON pt.transacao_id = t.id
                    $whereClause
                    GROUP BY pc.id
                    ORDER BY pc.data_registro DESC
                    LIMIT :limit OFFSET :offset
                ";
            }
            
            $stmt = $db->prepare($paymentsQuery);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Query para contar total - DIFERENTES PARA MVP E NORMAL
            if ($isStoreMvp) {
                // Para MVP, contar transações aprovadas
                $countQuery = "
                    SELECT COUNT(*) as total
                    FROM transacoes_cashback t
                    JOIN usuarios u ON t.usuario_id = u.id
                    $whereClause
                ";
            } else {
                // Para loja normal, contar pagamentos de comissão
                $countQuery = "
                    SELECT COUNT(DISTINCT pc.id) as total
                    FROM pagamentos_comissao pc
                    LEFT JOIN pagamentos_transacoes pt ON pc.id = pt.pagamento_id
                    LEFT JOIN transacoes_cashback t ON pt.transacao_id = t.id
                    $whereClause
                ";
            }
            
            $countStmt = $db->prepare($countQuery);
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Calcular paginação
            $totalPages = ceil($totalCount / $limit);
            
            return [
                'status' => true,
                'data' => [
                    'pagamentos' => $payments,
                    'paginacao' => [
                        'pagina_atual' => $page,
                        'total_paginas' => $totalPages,
                        'total_itens' => $totalCount,
                        'itens_por_pagina' => $limit
                    ]
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao buscar histórico de pagamentos com saldo: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao buscar histórico de pagamentos.'];
        }
    }
    /**
     * Envia notificação WhatsApp para nova transação
     * Integra com o sistema existente de notificações
     */
    private static function sendWhatsAppNotificationNewTransaction($userId, $transactionData) {
        try {
            // Verificar se WhatsApp está habilitado
            if (!defined('WHATSAPP_ENABLED') || !WHATSAPP_ENABLED) {
                return ['status' => false, 'message' => 'WhatsApp desabilitado'];
            }
            
            // Incluir a classe WhatsApp se ainda não estiver carregada
            if (!class_exists('WhatsAppBot')) {
                require_once __DIR__ . '/../utils/WhatsAppBot.php';
            }
            
            // Obter telefone do usuário
            $db = Database::getConnection();
            $userStmt = $db->prepare("SELECT telefone FROM usuarios WHERE id = ?");
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || empty($user['telefone'])) {
                error_log("WhatsApp: Usuário {$userId} sem telefone cadastrado");
                return ['status' => false, 'message' => 'Usuário sem telefone'];
            }
            
            // Enviar notificação via WhatsApp
            $result = WhatsAppBot::sendNewTransactionNotification($user['telefone'], $transactionData);
            
            if ($result['success']) {
                error_log("WhatsApp: Notificação de nova transação enviada para {$user['telefone']}");
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("WhatsApp: Erro ao enviar notificação de nova transação: " . $e->getMessage());
            return ['status' => false, 'error' => $e->getMessage()];
        }
    }
    /**
     * NOVO MÉTODO: createNewPixPayment
     * Gera uma nova transação PIX a cada clique, mantendo transações pendentes visíveis
     */
    public static function createNewPixPayment($paymentId, $storeId) {
        try {
            if (!AuthController::isAuthenticated() || !AuthController::isStore()) {
                return ['status' => false, 'message' => 'Acesso não autorizado.'];
            }
            
            $db = Database::getConnection();
            
            // Verificar se existe pagamento pendente
            $checkStmt = $db->prepare("
                SELECT id, valor_total, mp_payment_id, mp_status
                FROM pagamentos_comissao 
                WHERE id = ? AND loja_id = ? 
                AND status IN ('pendente', 'pix_aguardando')
            ");
            $checkStmt->execute([$paymentId, $storeId]);
            $existingPayment = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$existingPayment) {
                return ['status' => false, 'message' => 'Pagamento não encontrado ou já processado.'];
            }
            
            // NOVIDADE: Expirar PIX anterior se existir
            if (!empty($existingPayment['mp_payment_id'])) {
                $expireOldStmt = $db->prepare("
                    UPDATE pagamentos_comissao 
                    SET mp_status = 'expired', 
                        observacao = CONCAT(IFNULL(observacao, ''), ' | PIX anterior expirado em ', NOW())
                    WHERE id = ?
                ");
                $expireOldStmt->execute([$paymentId]);
                
                error_log("PIX anterior expirado: Payment ID {$paymentId}, MP ID: {$existingPayment['mp_payment_id']}");
            }
            
            // Gerar novo PIX via Mercado Pago
            $pixData = self::generateMercadoPagoPix($existingPayment['valor_total'], $paymentId);
            
            if (!$pixData['status']) {
                return $pixData;
            }
            
            // Atualizar com novos dados do PIX
            $updateStmt = $db->prepare("
                UPDATE pagamentos_comissao 
                SET mp_payment_id = ?, 
                    mp_qr_code = ?, 
                    mp_qr_code_base64 = ?,
                    mp_status = 'pending',
                    status = 'pix_aguardando',
                    data_registro = NOW(),
                    observacao = CONCAT(IFNULL(observacao, ''), ' | Novo PIX gerado em ', NOW())
                WHERE id = ?
            ");
            
            $updateSuccess = $updateStmt->execute([
                $pixData['data']['mp_payment_id'],
                $pixData['data']['qr_code'],
                $pixData['data']['qr_code_base64'],
                $paymentId
            ]);
            
            if (!$updateSuccess) {
                error_log("Erro ao atualizar PIX no banco: " . implode(' | ', $db->errorInfo()));
                return ['status' => false, 'message' => 'Erro interno. Tente novamente.'];
            }
            
            // Log da nova transação
            error_log("✅ NOVO PIX GERADO - Payment ID: {$paymentId}, MP ID: {$pixData['data']['mp_payment_id']}, Valor: R$ {$existingPayment['valor_total']}");
            
            return [
                'status' => true,
                'message' => 'Novo PIX gerado com sucesso!',
                'data' => [
                    'payment_id' => $paymentId,
                    'mp_payment_id' => $pixData['data']['mp_payment_id'],
                    'qr_code' => $pixData['data']['qr_code'],
                    'qr_code_base64' => $pixData['data']['qr_code_base64'],
                    'expires_in' => PIX_EXPIRATION_MINUTES * 60 // em segundos
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Erro ao gerar novo PIX: " . $e->getMessage());
            return ['status' => false, 'message' => 'Erro interno do servidor.'];
        }
    }

    /**
     * MÉTODO AUXILIAR: generateMercadoPagoPix
     * Integração otimizada com Mercado Pago
     */
    private static function generateMercadoPagoPix($amount, $paymentId) {
        try {
            $postData = [
                'transaction_amount' => (float) $amount,
                'description' => "Comissão Klube Cash - Pagamento #{$paymentId}",
                'payment_method_id' => 'pix',
                'external_reference' => "KLUBE_PAYMENT_{$paymentId}_" . time(),
                'notification_url' => MP_WEBHOOK_URL,
                'date_of_expiration' => date('c', strtotime('+' . PIX_EXPIRATION_MINUTES . ' minutes')),
                'payer' => [
                    'email' => 'loja@klubecash.com',
                    'identification' => [
                        'type' => 'CPF',
                        'number' => '00000000000'
                    ]
                ]
            ];
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.mercadopago.com/v1/payments',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($postData),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . MP_ACCESS_TOKEN,
                    'Content-Type: application/json',
                    'X-Idempotency-Key: KLUBE_' . $paymentId . '_' . time()
                ],
                CURLOPT_TIMEOUT => 30
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 201) {
                error_log("Erro MP: HTTP {$httpCode} - {$response}");
                return ['status' => false, 'message' => 'Erro ao gerar PIX. Tente novamente.'];
            }
            
            $data = json_decode($response, true);
            
            if (!isset($data['id']) || !isset($data['point_of_interaction']['transaction_data']['qr_code'])) {
                error_log("Resposta MP inválida: " . $response);
                return ['status' => false, 'message' => 'Dados PIX inválidos recebidos.'];
            }
            
            return [
                'status' => true,
                'data' => [
                    'mp_payment_id' => $data['id'],
                    'qr_code' => $data['point_of_interaction']['transaction_data']['qr_code'],
                    'qr_code_base64' => $data['point_of_interaction']['transaction_data']['qr_code_base64']
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Exceção ao gerar PIX: " . $e->getMessage());
            return ['status' => false, 'message' => 'Erro interno.'];
        }
    }
    /**
    * Obtém detalhes completos de uma transação específica
    * 
    * @param int $transactionId ID da transação
    * @return array Detalhes da transação
    */
    public static function getTransactionDetails($transactionId) {
        try {
            if (!AuthController::isAuthenticated()) {
                return ['status' => false, 'message' => 'Usuário não autenticado.'];
            }
            
            $db = Database::getConnection();
            
            // Buscar detalhes completos da transação
            $stmt = $db->prepare("
                SELECT 
                    t.*, 
                    u.nome as cliente_nome, 
                    u.email as cliente_email,
                    l.nome_fantasia as loja_nome,
                    pc.id as pagamento_id, 
                    pc.status as status_pagamento,
                    pc.data_aprovacao as data_pagamento,
                    COALESCE(tsu.valor_usado, 0) as valor_saldo_usado
                FROM transacoes_cashback t
                JOIN usuarios u ON t.usuario_id = u.id
                JOIN lojas l ON t.loja_id = l.id
                LEFT JOIN pagamentos_transacoes pt ON t.id = pt.transacao_id
                LEFT JOIN pagamentos_comissao pc ON pt.pagamento_id = pc.id
                LEFT JOIN transacoes_saldo_usado tsu ON t.id = tsu.transacao_id
                WHERE t.id = ?
            ");
            
            $stmt->execute([$transactionId]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$transaction) {
                return ['status' => false, 'message' => 'Transação não encontrada.'];
            }
            
            // Verificar permissões - apenas admin ou loja proprietária
            $currentUserId = AuthController::getCurrentUserId();
            
            if (!AuthController::isAdmin()) {
                if (AuthController::isStore()) {
                    // Verificar se é a loja proprietária
                    $storeCheckStmt = $db->prepare("SELECT usuario_id FROM lojas WHERE id = ?");
                    $storeCheckStmt->execute([$transaction['loja_id']]);
                    $storeCheck = $storeCheckStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$storeCheck || $storeCheck['usuario_id'] != $currentUserId) {
                        return ['status' => false, 'message' => 'Acesso não autorizado a esta transação.'];
                    }
                } else {
                    return ['status' => false, 'message' => 'Acesso não autorizado.'];
                }
            }
            
            return [
                'status' => true,
                'data' => $transaction
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao obter detalhes da transação: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao obter detalhes da transação.'];
        }
    }

    /**
    * Obtém detalhes de um pagamento específico com informações de saldo
    * 
    * @param int $paymentId ID do pagamento
    * @return array Resultado da operação
    */
    public static function getPaymentDetailsWithBalance($paymentId) {
        try {
            if (!AuthController::isAuthenticated()) {
                return ['status' => false, 'message' => 'Usuário não autenticado.'];
            }
            
            $db = Database::getConnection();
            
            // Buscar dados do pagamento
            $paymentQuery = "
                SELECT 
                    pc.*,
                    SUM(t.valor_total) as valor_vendas_originais,
                    COALESCE(SUM(
                        (SELECT SUM(cm.valor) 
                        FROM cashback_movimentacoes cm 
                        WHERE cm.usuario_id = t.usuario_id 
                        AND cm.loja_id = t.loja_id 
                        AND cm.tipo_operacao = 'uso'
                        AND cm.transacao_uso_id = t.id)
                    ), 0) as total_saldo_usado
                FROM pagamentos_comissao pc
                LEFT JOIN pagamentos_transacoes pt ON pc.id = pt.pagamento_id
                LEFT JOIN transacoes_cashback t ON pt.transacao_id = t.id
                WHERE pc.id = :payment_id
                GROUP BY pc.id
            ";
            
            $paymentStmt = $db->prepare($paymentQuery);
            $paymentStmt->bindParam(':payment_id', $paymentId);
            $paymentStmt->execute();
            
            $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payment) {
                return ['status' => false, 'message' => 'Pagamento não encontrado.'];
            }
            
            // Verificar permissões - apenas admin ou loja proprietária
            $currentUserId = AuthController::getCurrentUserId();
            
            if (!AuthController::isAdmin()) {
                if (AuthController::isStore()) {
                    // Verificar se é a loja proprietária
                    $storeCheckStmt = $db->prepare("SELECT usuario_id FROM lojas WHERE id = ?");
                    $storeCheckStmt->execute([$payment['loja_id']]);
                    $storeCheck = $storeCheckStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$storeCheck || $storeCheck['usuario_id'] != $currentUserId) {
                        return ['status' => false, 'message' => 'Acesso não autorizado a este pagamento.'];
                    }
                } else {
                    return ['status' => false, 'message' => 'Acesso não autorizado.'];
                }
            }
            
            // Buscar transações do pagamento com informações de saldo
            $transactionsQuery = "
                SELECT 
                    t.*,
                    u.nome as cliente_nome,
                    u.email as cliente_email,
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
                JOIN pagamentos_transacoes pt ON t.id = pt.transacao_id
                WHERE pt.pagamento_id = :payment_id
                ORDER BY t.data_transacao DESC
            ";
            
            $transactionsStmt = $db->prepare($transactionsQuery);
            $transactionsStmt->bindParam(':payment_id', $paymentId);
            $transactionsStmt->execute();
            
            $transactions = $transactionsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'status' => true,
                'data' => [
                    'pagamento' => $payment,
                    'transacoes' => $transactions
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao buscar detalhes do pagamento: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao buscar detalhes do pagamento.'];
        }
    }

    /**
    * Obtém transações pendentes com informações de saldo usado
    * 
    * @param int $storeId ID da loja
    * @param array $filters Filtros adicionais
    * @param int $page Página atual para paginação
    * @return array Resultado da operação
    */
    public static function getPendingTransactionsWithBalance($storeId, $filters = [], $page = 1) {
        try {
            if (!AuthController::isAuthenticated()) {
                return ['status' => false, 'message' => 'Usuário não autenticado.'];
            }
            
            $db = Database::getConnection();
            $limit = ITEMS_PER_PAGE;
            $offset = ($page - 1) * $limit;
            
            // Construir condições WHERE - EXCLUIR TRANSAÇÕES MVP APROVADAS
            // Para lojas MVP, transações pendentes indicam problema no sistema
            // Transações de lojas MVP deveriam estar automaticamente aprovadas
            $whereConditions = [
                "t.loja_id = :loja_id", 
                "t.status = :status"
            ];
            $params = [
                ':loja_id' => $storeId,
                ':status' => TRANSACTION_PENDING
            ];
            
            // CORREÇÃO: Verificar se a loja é MVP e ajustar a consulta
            $storeMvpQuery = "SELECT u.mvp FROM lojas l JOIN usuarios u ON l.usuario_id = u.id WHERE l.id = :store_id";
            $storeMvpStmt = $db->prepare($storeMvpQuery);
            $storeMvpStmt->bindParam(':store_id', $storeId);
            $storeMvpStmt->execute();
            $storeMvpResult = $storeMvpStmt->fetch(PDO::FETCH_ASSOC);
            $isStoreMvp = ($storeMvpResult && $storeMvpResult['mvp'] === 'sim');
            
            // Se for loja MVP, OCULTAR transações pendentes pois elas deveriam estar aprovadas
            if ($isStoreMvp) {
                error_log("PENDENTES DEBUG: Loja {$storeId} é MVP - OCULTANDO todas as transações pendentes desta tela");
                // Para lojas MVP, forçar query que não retorna nada
                $whereConditions[] = "1 = 0"; // Condição que nunca é verdadeira = não mostra nada
            } else {
                error_log("PENDENTES DEBUG: Loja {$storeId} não é MVP - Mostrando todas as pendentes normalmente");
            }
            
            // Aplicar filtros
            if (!empty($filters['data_inicio'])) {
                $whereConditions[] = "DATE(t.data_transacao) >= :data_inicio";
                $params[':data_inicio'] = $filters['data_inicio'];
            }
            
            if (!empty($filters['data_fim'])) {
                $whereConditions[] = "DATE(t.data_transacao) <= :data_fim";
                $params[':data_fim'] = $filters['data_fim'];
            }
            
            if (!empty($filters['valor_min'])) {
                $whereConditions[] = "t.valor_total >= :valor_min";
                $params[':valor_min'] = $filters['valor_min'];
            }
            
            if (!empty($filters['valor_max'])) {
                $whereConditions[] = "t.valor_total <= :valor_max";
                $params[':valor_max'] = $filters['valor_max'];
            }
            
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            
            // Query para obter transações com informações de saldo usado
            $transactionsQuery = "
                SELECT 
                    t.*,
                    u.nome as cliente_nome,
                    u.email as cliente_email,
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
                $whereClause
                ORDER BY t.data_transacao DESC
                LIMIT :limit OFFSET :offset
            ";
            
            $stmt = $db->prepare($transactionsQuery);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Query para contar total de transações
            $countQuery = "
                SELECT COUNT(*) as total
                FROM transacoes_cashback t
                JOIN usuarios u ON t.usuario_id = u.id
                $whereClause
            ";
            
            $countStmt = $db->prepare($countQuery);
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Query para totais - COMPLETAMENTE CORRIGIDA
            $totalsQuery = "
                SELECT 
                    COUNT(*) as total_transacoes,
                    SUM(t.valor_total) as total_valor_vendas_originais,
                    COALESCE(SUM(
                        (SELECT SUM(cm.valor) 
                        FROM cashback_movimentacoes cm 
                        WHERE cm.usuario_id = t.usuario_id 
                        AND cm.loja_id = t.loja_id 
                        AND cm.tipo_operacao = 'uso'
                        AND cm.transacao_uso_id = t.id)
                    ), 0) as total_saldo_usado,
                    -- CORREÇÃO: Calcular comissão total como 10% do valor efetivamente cobrado
                    SUM(
                        (t.valor_total - COALESCE(
                            (SELECT SUM(cm.valor) 
                            FROM cashback_movimentacoes cm 
                            WHERE cm.usuario_id = t.usuario_id 
                            AND cm.loja_id = t.loja_id 
                            AND cm.tipo_operacao = 'uso'
                            AND cm.transacao_uso_id = t.id), 0
                        )) * 0.10
                    ) as total_valor_comissoes
                FROM transacoes_cashback t
                JOIN usuarios u ON t.usuario_id = u.id
                $whereClause
            ";
            
            $totalsStmt = $db->prepare($totalsQuery);
            foreach ($params as $key => $value) {
                $totalsStmt->bindValue($key, $value);
            }
            $totalsStmt->execute();
            $totals = $totalsStmt->fetch(PDO::FETCH_ASSOC);
            
            // Calcular paginação
            $totalPages = ceil($totalCount / $limit);
            
            return [
                'status' => true,
                'data' => [
                    'transacoes' => $transactions,
                    'totais' => $totals,
                    'paginacao' => [
                        'pagina_atual' => $page,
                        'total_paginas' => $totalPages,
                        'total_itens' => $totalCount,
                        'itens_por_pagina' => $limit
                    ]
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao buscar transações pendentes com saldo: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao buscar transações pendentes.'];
        }
    }
    /**
    * Registra uma nova transação de cashback
    * 
    * @param array $data Dados da transação
    * @return array Resultado da operação
    */
    public static function registerTransaction($data) {
        try {
            // Validar dados obrigatórios
            $requiredFields = ['loja_id', 'usuario_id', 'valor_total', 'codigo_transacao'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return ['status' => false, 'message' => 'Dados da transação incompletos. Campo faltante: ' . $field];
                }
            }
            
            // Verificar se o usuário está autenticado e é loja ou admin
            if (!AuthController::isAuthenticated()) {
                return ['status' => false, 'message' => 'Usuário não autenticado.'];
            }
            
            if (!AuthController::isStore() && !AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Apenas lojas e administradores podem registrar transações.'];
            }
            
            $db = Database::getConnection();
            
            // Verificar se o cliente existe
            $userStmt = $db->prepare("SELECT id, nome, email FROM usuarios WHERE id = :usuario_id AND tipo = :tipo AND status = :status");
            $userStmt->bindParam(':usuario_id', $data['usuario_id']);
            $tipoCliente = USER_TYPE_CLIENT;
            $userStmt->bindParam(':tipo', $tipoCliente);
            $statusAtivo = USER_ACTIVE;
            $userStmt->bindParam(':status', $statusAtivo);
            $userStmt->execute();
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['status' => false, 'message' => 'Cliente não encontrado ou inativo.'];
            }
            
            // Verificar se a loja existe e está aprovada
            $isStoreMvp = false; // Default para não-MVP
            
            try {
                // Tentar query com campo MVP primeiro
                $storeStmt = $db->prepare("
                    SELECT l.*, 
                           COALESCE(u.mvp, 'nao') as store_mvp 
                    FROM lojas l 
                    JOIN usuarios u ON l.usuario_id = u.id 
                    WHERE l.id = :loja_id AND l.status = :status
                ");
                $storeStmt->bindParam(':loja_id', $data['loja_id']);
                $statusAprovado = STORE_APPROVED;
                $storeStmt->bindParam(':status', $statusAprovado);
                $storeStmt->execute();
                $store = $storeStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($store) {
                    $isStoreMvp = (isset($store['store_mvp']) && $store['store_mvp'] === 'sim');
                }
                
            } catch (PDOException $e) {
                // Se falhar (campo MVP não existe), usar query básica
                error_log("MVP FIELD ERROR: " . $e->getMessage() . " - Usando query básica");
                
                $storeStmt = $db->prepare("
                    SELECT l.*
                    FROM lojas l 
                    WHERE l.id = :loja_id AND l.status = :status
                ");
                $storeStmt->bindParam(':loja_id', $data['loja_id']);
                $statusAprovado = STORE_APPROVED;
                $storeStmt->bindParam(':status', $statusAprovado);
                $storeStmt->execute();
                $store = $storeStmt->fetch(PDO::FETCH_ASSOC);
                
                $isStoreMvp = false; // Campo MVP não existe, então não é MVP
            }
            
            if (!$store) {
                return ['status' => false, 'message' => 'Loja não encontrada ou não aprovada.'];
            }
            
            // Log de debug
            error_log("MVP CHECK: Loja ID {$data['loja_id']} - MVP: " . ($isStoreMvp ? 'SIM' : 'NÃO') . " (store_mvp: " . ($store['store_mvp'] ?? 'NULL') . ")");
            
            // Verificar se o valor da transação é válido
            if (!is_numeric($data['valor_total']) || $data['valor_total'] <= 0) {
                return ['status' => false, 'message' => 'Valor da transação inválido.'];
            }
            
            // CORREÇÃO 1: Verificar se vai usar saldo do cliente (aceita tanto string 'sim' quanto boolean true)
            $usarSaldo = (isset($data['usar_saldo']) && ($data['usar_saldo'] === 'sim' || $data['usar_saldo'] === true));
            $valorSaldoUsado = floatval($data['valor_saldo_usado'] ?? 0);
            $valorOriginal = $data['valor_total']; // Guardar valor original para referência
            
            // CORREÇÃO 2: Definir $balanceModel ANTES de usar
            require_once __DIR__ . '/../models/CashbackBalance.php';
            $balanceModel = new CashbackBalance();
            
            // Validações de saldo
            if ($usarSaldo && $valorSaldoUsado > 0) {
                // Verificar se o cliente tem saldo suficiente
                $saldoDisponivel = $balanceModel->getStoreBalance($data['usuario_id'], $data['loja_id']);
                
                if ($saldoDisponivel < $valorSaldoUsado) {
                    return [
                        'status' => false, 
                        'message' => 'Saldo insuficiente. Cliente possui R$ ' . number_format($saldoDisponivel, 2, ',', '.') . ' disponível.'
                    ];
                }
                
                // Validar se o valor do saldo usado não é maior que o valor total
                if ($valorSaldoUsado > $data['valor_total']) {
                    return [
                        'status' => false, 
                        'message' => 'O valor do saldo usado não pode ser maior que o valor total da venda.'
                    ];
                }
            }
            
            // CORREÇÃO 3: Calcular valor efetivo SEM alterar $data['valor_total']
            $valorEfetivamentePago = $data['valor_total'] - $valorSaldoUsado;
            
            // Verificar valor mínimo após desconto do saldo
            if ($valorEfetivamentePago < 0) {
                return ['status' => false, 'message' => 'Valor da transação após desconto do saldo não pode ser negativo.'];
            }
            
            // Se sobrou algum valor após usar saldo, verificar valor mínimo
            if ($valorEfetivamentePago > 0 && $valorEfetivamentePago < MIN_TRANSACTION_VALUE) {
                return ['status' => false, 'message' => 'Valor mínimo para transação (após desconto do saldo) é R$ ' . number_format(MIN_TRANSACTION_VALUE, 2, ',', '.')];
            }
            
            // Verificar se já existe uma transação com o mesmo código
            $checkStmt = $db->prepare("
                SELECT id FROM transacoes_cashback 
                WHERE codigo_transacao = :codigo_transacao AND loja_id = :loja_id
            ");
            $checkStmt->bindParam(':codigo_transacao', $data['codigo_transacao']);
            $checkStmt->bindParam(':loja_id', $data['loja_id']);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                return ['status' => false, 'message' => 'Já existe uma transação com este código.'];
            }
            
            // Obter configurações de cashback
            $configStmt = $db->prepare("SELECT * FROM configuracoes_cashback ORDER BY id DESC LIMIT 1");
            $configStmt->execute();
            $config = $configStmt->fetch(PDO::FETCH_ASSOC);
            
            // CORREÇÃO 4: Sempre usar 10% como valor total de cashback (comissão da loja)
            $porcentagemTotal = DEFAULT_CASHBACK_TOTAL; // Sempre 10%
            
            // CORREÇÃO: Garantir que a divisão é sempre 5% cliente, 5% admin
            $porcentagemCliente = DEFAULT_CASHBACK_CLIENT; // 5%
            $porcentagemAdmin = DEFAULT_CASHBACK_ADMIN; // 5%
            
            // CORREÇÃO: Remover qualquer personalização de porcentagem por loja
            // Se a configuração do sistema for diferente do padrão, aplicar ajuste proporcional
            if (isset($config['porcentagem_cliente']) && isset($config['porcentagem_admin'])) {
                // Verificar se o total configurado é 10%
                $configTotal = $config['porcentagem_cliente'] + $config['porcentagem_admin'];
                
                if ($configTotal == 10.00) {
                    $porcentagemCliente = $config['porcentagem_cliente'];
                    $porcentagemAdmin = $config['porcentagem_admin'];
                } else {
                    // Ajustar proporcionalmente para manter a soma em 10%
                    $fator = 10.00 / $configTotal;
                    $porcentagemCliente = $config['porcentagem_cliente'] * $fator;
                    $porcentagemAdmin = $config['porcentagem_admin'] * $fator;
                }
            }
            
            // Calcular valores de cashback sobre o valor EFETIVAMENTE PAGO
            $valorCashbackTotal = ($valorEfetivamentePago * $porcentagemTotal) / 100;
            $valorCashbackCliente = ($valorEfetivamentePago * $porcentagemCliente) / 100;
            $valorCashbackAdmin = ($valorEfetivamentePago * $porcentagemAdmin) / 100;
            // Valor da loja sempre zero
            $valorLoja = 0.00;
            
            // Iniciar transação no banco de dados
            $db->beginTransaction();
            
            try {
                // Definir o status da transação
                // 🎯 MVP: Aprovar automaticamente transações de lojas MVP
                if ($isStoreMvp) {
                    $transactionStatus = TRANSACTION_APPROVED;
                    error_log("MVP AUTO-APPROVAL: Transação automaticamente aprovada para loja MVP ID {$data['loja_id']}");
                } else {
                    $transactionStatus = isset($data['status']) ? $data['status'] : TRANSACTION_PENDING;
                }
                
                // Preparar descrição da transação
                $descricao = isset($data['descricao']) ? $data['descricao'] : 'Compra na ' . $store['nome_fantasia'];
                if ($usarSaldo && $valorSaldoUsado > 0) {
                    $descricao .= " (Usado R$ " . number_format($valorSaldoUsado, 2, ',', '.') . " do saldo)";
                }
                
                // Registrar transação principal (com valor original para histórico)
                $stmt = $db->prepare("
                    INSERT INTO transacoes_cashback (
                        usuario_id, loja_id, valor_total, valor_cashback,
                        valor_cliente, valor_admin, valor_loja, codigo_transacao, 
                        data_transacao, status, descricao
                    ) VALUES (
                        :usuario_id, :loja_id, :valor_original, :valor_cashback,
                        :valor_cliente, :valor_admin, :valor_loja, :codigo_transacao, 
                        :data_transacao, :status, :descricao
                    )
                ");
                
                $stmt->bindParam(':usuario_id', $data['usuario_id']);
                $stmt->bindParam(':loja_id', $data['loja_id']);
                $stmt->bindParam(':valor_original', $valorOriginal); // Valor original da compra
                $stmt->bindParam(':valor_cashback', $valorCashbackTotal);
                $stmt->bindParam(':valor_cliente', $valorCashbackCliente);
                $stmt->bindParam(':valor_admin', $valorCashbackAdmin);
                $stmt->bindParam(':valor_loja', $valorLoja); // Sempre 0.00
                $stmt->bindParam(':codigo_transacao', $data['codigo_transacao']);
                
                $dataTransacao = isset($data['data_transacao']) ? $data['data_transacao'] : date('Y-m-d H:i:s');
                $stmt->bindParam(':data_transacao', $dataTransacao);
                $stmt->bindParam(':status', $transactionStatus);
                $stmt->bindParam(':descricao', $descricao);
                
                $stmt->execute();
                $transactionId = $db->lastInsertId();
                
                // === MARCADOR DE TRACE: TransactionController - Nova transação criada ===
                if (file_exists('trace-integration.php')) {
                    error_log("[TRACE] TransactionController::registerTransaction() - Transação criada com ID: {$transactionId}", 3, 'integration_trace.log');
                }
                
                // === INTEGRAÇÃO AUTOMÁTICA: Sistema de Notificação Corrigido ===
                // Disparar notificação para transações pendentes E aprovadas
                if ($transactionStatus === TRANSACTION_PENDING || $transactionStatus === TRANSACTION_APPROVED) {
                    try {
                        // Log de início da notificação
                        error_log("[FIXED] TransactionController::registerTransaction() - Iniciando notificação para ID: {$transactionId}, status: {$transactionStatus}");

                        // NOTIFICAÇÃO ULTRA DIRETA VIA WHATSAPP (Máxima Prioridade)
                        $ultraDirectPath = __DIR__ . '/../classes/UltraDirectNotifier.php';
                        $immediateSystemPath = __DIR__ . '/../classes/ImmediateNotificationSystem.php';
                        $fallbackSystemPath = __DIR__ . '/../classes/FixedBrutalNotificationSystem.php';

                        $result = ['success' => false, 'message' => 'Nenhum sistema encontrado'];
                        $systemUsed = 'none';

                        // 1️⃣ PRIORIDADE MÁXIMA: UltraDirectNotifier (Direto no bot)
                        if (file_exists($ultraDirectPath)) {
                            require_once $ultraDirectPath;
                            if (class_exists('UltraDirectNotifier')) {
                                error_log("[ULTRA] Usando UltraDirectNotifier para transação {$transactionId}");
                                $notifier = new UltraDirectNotifier();

                                // Buscar dados da transação para envio (método estático)
                                $db = Database::getConnection();
                                $stmt = $db->prepare("
                                    SELECT t.*, u.nome as cliente_nome, u.telefone as cliente_telefone, l.nome_fantasia as loja_nome
                                    FROM transacoes_cashback t
                                    LEFT JOIN usuarios u ON t.usuario_id = u.id
                                    LEFT JOIN lojas l ON t.loja_id = l.id
                                    WHERE t.id = :id
                                ");
                                $stmt->bindParam(':id', $transactionId);
                                $stmt->execute();
                                $transactionData = $stmt->fetch(PDO::FETCH_ASSOC);

                                if ($transactionData && !empty($transactionData['cliente_telefone'])) {
                                    $result = $notifier->notifyTransaction($transactionData);
                                    $systemUsed = 'UltraDirectNotifier';
                                    error_log("[ULTRA] Resultado: " . ($result['success'] ? 'SUCESSO' : 'FALHA') . " em " . ($result['time_ms'] ?? 0) . "ms");
                                } else {
                                    error_log("[ULTRA] Dados insuficientes para UltraDirectNotifier");
                                }
                            }
                        }

                        // 2️⃣ Fallback: Sistema imediato
                        if (!$result['success'] && file_exists($immediateSystemPath)) {
                            require_once $immediateSystemPath;
                            if (class_exists('ImmediateNotificationSystem')) {
                                error_log("[IMMEDIATE] Usando sistema de notificação imediata para transação {$transactionId}");
                                $notificationSystem = new ImmediateNotificationSystem();
                                $result = $notificationSystem->sendImmediateNotification($transactionId);
                                $systemUsed = 'ImmediateNotificationSystem (fallback)';
                            }
                        }

                        // Se sistema imediato falhou, usar fallback
                        if (!$result['success'] && file_exists($fallbackSystemPath)) {
                            require_once $fallbackSystemPath;
                            if (class_exists('FixedBrutalNotificationSystem')) {
                                error_log("[FALLBACK] Usando sistema fallback para transação {$transactionId}");
                                $notificationSystem = new FixedBrutalNotificationSystem();
                                $result = $notificationSystem->forceNotifyTransaction($transactionId);
                                $systemUsed = 'FixedBrutalNotificationSystem (fallback)';
                            }
                        }

                        // Log detalhado do resultado
                        if ($result['success']) {
                            $method = $result['method_used'] ?? 'unknown';
                            $timeInfo = isset($result['all_results']) ? $this->getTimeInfo($result['all_results']) : '';
                            error_log("[SUCCESS] TransactionController - Notificação enviada via {$systemUsed} usando método {$method} para transação {$transactionId}{$timeInfo}");
                        } else {
                            error_log("[FAIL] TransactionController - Falha na notificação para transação {$transactionId} via {$systemUsed}: " . $result['message']);
                        }

                    } catch (Exception $e) {
                        // Log de erro mas não quebrar o fluxo principal
                        error_log("[FIXED] TransactionController - Erro na notificação para transação {$transactionId}: " . $e->getMessage());
                    }
                }
                
                // MVP será processado APÓS o commit para evitar transações aninhadas
                
                // CORREÇÃO 5: Se usou saldo, debitar do saldo do cliente IMEDIATAMENTE
                if ($usarSaldo && $valorSaldoUsado > 0) {
                    $descricaoUso = "Uso do saldo na compra - Código: " . $data['codigo_transacao'] . " - Transação #" . $transactionId;
                    
                    error_log("REGISTRO: Tentando debitar saldo - Usuario: {$data['usuario_id']}, Loja: {$data['loja_id']}, Valor: {$valorSaldoUsado}");
                    
                    $debitResult = $balanceModel->useBalance($data['usuario_id'], $data['loja_id'], $valorSaldoUsado, $descricaoUso, $transactionId);
                    
                    if (!$debitResult) {
                        // Se falhou ao debitar saldo, reverter transação
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        error_log("REGISTRO: FALHA ao debitar saldo - revertendo transação");
                        return ['status' => false, 'message' => 'Erro ao debitar saldo do cliente. Transação cancelada.'];
                    }
                    
                    error_log("REGISTRO: Saldo debitado com sucesso");
                    
                    // Registrar uso de saldo na tabela auxiliar
                    $useSaldoStmt = $db->prepare("
                        INSERT INTO transacoes_saldo_usado (transacao_id, usuario_id, loja_id, valor_usado)
                        VALUES (:transacao_id, :usuario_id, :loja_id, :valor_usado)
                    ");
                    $useSaldoStmt->bindParam(':transacao_id', $transactionId);
                    $useSaldoStmt->bindParam(':usuario_id', $data['usuario_id']);
                    $useSaldoStmt->bindParam(':loja_id', $data['loja_id']);
                    $useSaldoStmt->bindParam(':valor_usado', $valorSaldoUsado);
                    $useSaldoStmt->execute();
                }
                
                // Registrar comissão para o administrador (sobre valor efetivamente pago)
                if ($valorCashbackAdmin > 0) {
                    $comissionAdminStmt = $db->prepare("
                        INSERT INTO transacoes_comissao (
                            tipo_usuario, usuario_id, loja_id, transacao_id,
                            valor_total, valor_comissao, data_transacao, status
                        ) VALUES (
                            :tipo_usuario, :usuario_id, :loja_id, :transacao_id,
                            :valor_total, :valor_comissao, :data_transacao, :status
                        )
                    ");
                    
                    $tipoAdmin = USER_TYPE_ADMIN;
                    $adminId = 1; // Administrador padrão
                    
                    $comissionAdminStmt->bindParam(':tipo_usuario', $tipoAdmin);
                    $comissionAdminStmt->bindParam(':usuario_id', $adminId);
                    $comissionAdminStmt->bindParam(':loja_id', $data['loja_id']);
                    $comissionAdminStmt->bindParam(':transacao_id', $transactionId);
                    $comissionAdminStmt->bindParam(':valor_total', $valorEfetivamentePago); // Valor efetivamente pago
                    $comissionAdminStmt->bindParam(':valor_comissao', $valorCashbackAdmin);
                    $comissionAdminStmt->bindParam(':data_transacao', $dataTransacao);
                    $comissionAdminStmt->bindParam(':status', $transactionStatus);
                    
                    $comissionAdminStmt->execute();
                }
                
                // Preparar mensagem de sucesso
                if ($isStoreMvp) {
                    $successMessage = '🎉 Transação MVP aprovada instantaneamente! Cashback creditado automaticamente.';
                } else {
                    $successMessage = 'Transação registrada com sucesso!';
                }
                
                if ($usarSaldo && $valorSaldoUsado > 0) {
                    $successMessage .= ' Saldo de R$ ' . number_format($valorSaldoUsado, 2, ',', '.') . ' foi usado na compra.';
                }
                
                // Criar notificação para o cliente (apenas se não for MVP, pois MVP já criou notificação especial)
                if (!$isStoreMvp) {
                    $notificationMessage = 'Você tem um novo cashback de R$ ' . number_format($valorCashbackCliente, 2, ',', '.') . ' pendente da loja ' . $store['nome_fantasia'];
                    if ($usarSaldo && $valorSaldoUsado > 0) {
                        $notificationMessage .= '. Você usou R$ ' . number_format($valorSaldoUsado, 2, ',', '.') . ' do seu saldo nesta compra.';
                    }
                    
                    // self::createNotification(
                    //     $data['usuario_id'],
                    //     'Nova transação registrada',
                    //     $notificationMessage,
                    //     'info'
                    // );
                }
                // INTEGRAÇÃO WHATSAPP: Com tratamento de erro aprimorado
                if (defined('WHATSAPP_ENABLED') && WHATSAPP_ENABLED) {
                    try {
                        // Carregar as classes necessárias para WhatsApp
                        if (!class_exists('WhatsAppBot')) {
                            require_once __DIR__ . '/../utils/WhatsAppBot.php';
                        }
                        
                        // Buscar o telefone do cliente que fez a compra
                        $userStmt = $db->prepare("SELECT telefone, nome FROM usuarios WHERE id = ?");
                        $userStmt->execute([$data['usuario_id']]);
                        $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Verificar se o cliente tem WhatsApp cadastrado
                        if ($userData && !empty($userData['telefone'])) {
                            // Preparar as informações da transação para a mensagem WhatsApp
                            $whatsappData = [
                                'valor_cashback' => $valorCashbackCliente, // Valor do cashback desta transação
                                'valor_usado' => $valorSaldoUsado ?? 0, // Valor usado do saldo (se aplicável)
                                'nome_loja' => $store['nome_fantasia'] // Nome da loja onde a compra foi realizada
                            ];
                            
                            // Enviar a notificação via WhatsApp usando nosso template específico
                            $whatsappResult = WhatsAppBot::sendNewTransactionNotification(
                                $userData['telefone'], 
                                $whatsappData
                            );
                            
                            // O resultado será automaticamente registrado em nosso sistema de logs
                            // Você poderá acompanhar o sucesso ou falha na interface de monitoramento
                        }
                    } catch (Throwable $e) {
                        // Capturar TODOS os tipos de erro (Exception, Error, etc.) sem interromper a transação
                        // Isso garante que o sistema principal continue funcionando mesmo se houver problema crítico com WhatsApp
                        error_log("WhatsApp Nova Transação - Erro crítico: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine());
                        // Não relançar a exceção para não afetar o fluxo principal
                    }
                }
                // Enviar email para o cliente (opcional, pode remover se não quiser)
                if (!empty($user['email'])) {
                    $subject = 'Novo Cashback Pendente - Klube Cash';
                    $emailMessage = "
                        <h3>Olá, {$user['nome']}!</h3>
                        <p>Uma nova transação foi registrada em sua conta no Klube Cash.</p>
                        <p><strong>Loja:</strong> {$store['nome_fantasia']}</p>
                        <p><strong>Valor total da compra:</strong> R$ " . number_format($valorOriginal, 2, ',', '.') . "</p>";
                    
                    if ($usarSaldo && $valorSaldoUsado > 0) {
                        $emailMessage .= "<p><strong>Saldo usado:</strong> R$ " . number_format($valorSaldoUsado, 2, ',', '.') . "</p>";
                        $emailMessage .= "<p><strong>Valor pago:</strong> R$ " . number_format($valorEfetivamentePago, 2, ',', '.') . "</p>";
                    }
                    
                    $emailMessage .= "
                        <p><strong>Cashback (pendente):</strong> R$ " . number_format($valorCashbackCliente, 2, ',', '.') . "</p>
                        <p><strong>Data:</strong> " . date('d/m/Y H:i', strtotime($dataTransacao)) . "</p>
                        <p>O cashback será disponibilizado assim que a loja confirmar o pagamento da comissão.</p>
                        <p>Atenciosamente,<br>Equipe Klube Cash</p>
                    ";
                    
                    // Email::send($user['email'], $subject, $emailMessage, $user['nome']); // Descomente se quiser enviar email
                }
                
                // Confirmar transação
                $db->commit();
                
                // 🎯 MVP: Processar cashback instantaneamente APÓS commit para evitar transações aninhadas
                if ($isStoreMvp && $valorCashbackCliente > 0) {
                    error_log("MVP CASHBACK: Processando cashback instantâneo para loja MVP - Valor: R$ {$valorCashbackCliente}");
                    
                    // Creditar cashback imediatamente
                    $descricaoCashback = "Cashback MVP - Compra aprovada instantaneamente - Código: " . $data['codigo_transacao'];
                    $creditResult = $balanceModel->addBalance(
                        $data['usuario_id'], 
                        $data['loja_id'], 
                        $valorCashbackCliente, 
                        $descricaoCashback, 
                        $transactionId
                    );
                    
                    if ($creditResult) {
                        error_log("MVP CASHBACK: Cashback creditado com sucesso - R$ {$valorCashbackCliente} para usuário {$data['usuario_id']}");
                        
                        // Criar notificação especial para MVP (temporariamente comentado)
                        // self::createNotification(
                        //     $data['usuario_id'],
                        //     'Cashback MVP Creditado! 🎉',
                        //     "Seu cashback de R$ " . number_format($valorCashbackCliente, 2, ',', '.') . " foi creditado instantaneamente! Loja MVP: " . $store['nome_fantasia'],
                        //     'success'
                        // );
                    } else {
                        error_log("MVP CASHBACK: ERRO ao creditar cashback para usuário {$data['usuario_id']}");
                    }
                }
                
                return [
                    'status' => true, 
                    'message' => $successMessage,
                    'data' => [
                        'transaction_id' => $transactionId,
                        'valor_original' => $valorOriginal,
                        'valor_efetivamente_pago' => $valorEfetivamentePago,
                        'valor_saldo_usado' => $valorSaldoUsado,
                        'valor_cashback' => $valorCashbackCliente,
                        'valor_comissao' => $valorCashbackTotal,
                        'is_mvp' => $isStoreMvp,
                        'status_transacao' => $transactionStatus,
                        'cashback_creditado' => $isStoreMvp
                    ]
                ];
                
            } catch (Exception $e) {
                // Reverter transação em caso de erro
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }
            
        } catch (Exception $e) {
            // Reverter transação em caso de erro
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            
            error_log('ERRO CAPTURADO na registerTransaction: ' . $e->getMessage());
            error_log('Arquivo: ' . $e->getFile() . ' - Linha: ' . $e->getLine());
            error_log('Stack trace completo: ' . $e->getTraceAsString());
            
            // Se for o erro específico de transação, vamos dar mais detalhes
            if (strpos($e->getMessage(), 'There is no active transaction') !== false) {
                error_log('PROBLEMA DE TRANSAÇÃO DETECTADO - Investigando origem...');
                error_log('Estado atual do DB: inTransaction = ' . ($db->inTransaction() ? 'SIM' : 'NÃO'));
            }
            
            return ['status' => false, 'message' => 'Erro: ' . $e->getMessage()];
        }
    }
    
    /**
     * Versão limpa e funcional do registro de transações com funcionalidade MVP
     */
    public static function registerTransactionFixed($data) {
        try {
            // Validar dados obrigatórios
            $requiredFields = ['loja_id', 'usuario_id', 'valor_total', 'codigo_transacao'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return ['status' => false, 'message' => 'Dados da transação incompletos. Campo faltante: ' . $field];
                }
            }
            
            // Verificar autenticação
            if (!AuthController::isAuthenticated()) {
                return ['status' => false, 'message' => 'Usuário não autenticado.'];
            }
            
            if (!AuthController::isStore() && !AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Apenas lojas e administradores podem registrar transações.'];
            }
            
            $db = Database::getConnection();
            
            // Verificar cliente
            $userStmt = $db->prepare("SELECT id, nome, email FROM usuarios WHERE id = ? AND tipo = ? AND status = ?");
            $userStmt->execute([$data['usuario_id'], USER_TYPE_CLIENT, USER_ACTIVE]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['status' => false, 'message' => 'Cliente não encontrado ou inativo.'];
            }
            
            // Verificar loja e MVP
            $storeStmt = $db->prepare("
                SELECT l.*, COALESCE(u.mvp, 'nao') as store_mvp 
                FROM lojas l 
                JOIN usuarios u ON l.usuario_id = u.id 
                WHERE l.id = ? AND l.status = ?
            ");
            $storeStmt->execute([$data['loja_id'], STORE_APPROVED]);
            $store = $storeStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$store) {
                return ['status' => false, 'message' => 'Loja não encontrada ou não aprovada.'];
            }
            
            $isStoreMvp = ($store['store_mvp'] === 'sim');
            
            // Validar valor
            if (!is_numeric($data['valor_total']) || $data['valor_total'] <= 0) {
                return ['status' => false, 'message' => 'Valor da transação inválido.'];
            }
            
            if ($data['valor_total'] < MIN_TRANSACTION_VALUE) {
                return ['status' => false, 'message' => 'Valor mínimo para transação é R$ ' . number_format(MIN_TRANSACTION_VALUE, 2, ',', '.')];
            }
            
            // Verificar código duplicado
            $checkStmt = $db->prepare("SELECT id FROM transacoes_cashback WHERE codigo_transacao = ? AND loja_id = ?");
            $checkStmt->execute([$data['codigo_transacao'], $data['loja_id']]);
            
            if ($checkStmt->rowCount() > 0) {
                return ['status' => false, 'message' => 'Já existe uma transação com este código.'];
            }
            
            // NOVO: Obter configurações de cashback da loja
            $storeConfigQuery = $db->prepare("
                SELECT l.*, u.mvp,
                       COALESCE(l.porcentagem_cliente, 5.00) as porcentagem_cliente,
                       COALESCE(l.porcentagem_admin, 5.00) as porcentagem_admin,
                       COALESCE(l.cashback_ativo, 1) as cashback_ativo
                FROM lojas l 
                JOIN usuarios u ON l.usuario_id = u.id 
                WHERE l.id = :loja_id
            ");
            $storeConfigQuery->bindParam(':loja_id', $data['loja_id']);
            $storeConfigQuery->execute();
            $storeConfig = $storeConfigQuery->fetch(PDO::FETCH_ASSOC);
            
            if (!$storeConfig) {
                return ['status' => false, 'message' => 'Loja não encontrada.'];
            }
            
            // Verificar se cashback está ativo para esta loja
            if ($storeConfig['cashback_ativo'] != 1) {
                return ['status' => false, 'message' => 'Esta loja não oferece cashback no momento.'];
            }
            
            // Verificar se é loja MVP
            $isStoreMvp = ($storeConfig['mvp'] === 'sim');
            
            // Calcular cashback usando configurações específicas da loja
            $porcentagemCliente = (float) $storeConfig['porcentagem_cliente'];
            $porcentagemAdmin = (float) $storeConfig['porcentagem_admin'];
            $porcentagemTotal = $porcentagemCliente + $porcentagemAdmin;
            
            $valorCashbackCliente = ($data['valor_total'] * $porcentagemCliente) / 100;
            $valorCashbackAdmin = ($data['valor_total'] * $porcentagemAdmin) / 100;
            $valorCashbackTotal = $valorCashbackCliente + $valorCashbackAdmin;
            $valorLoja = 0.00;
            
            // Log para debug das configurações
            error_log("CASHBACK CONFIG: Loja {$data['loja_id']} - Cliente: {$porcentagemCliente}%, Admin: {$porcentagemAdmin}%, MVP: " . ($isStoreMvp ? 'SIM' : 'NÃO'));
            
            // Definir status - MVP é aprovado automaticamente
            $transactionStatus = $isStoreMvp ? TRANSACTION_APPROVED : TRANSACTION_PENDING;
            
            // Preparar dados
            $descricao = isset($data['descricao']) ? $data['descricao'] : 'Compra na ' . $store['nome_fantasia'];
            $dataTransacao = isset($data['data_transacao']) ? $data['data_transacao'] : date('Y-m-d H:i:s');
            
            // Inserir transação
            $db->beginTransaction();
            
            $insertStmt = $db->prepare("
                INSERT INTO transacoes_cashback (
                    usuario_id, loja_id, valor_total, valor_cashback,
                    valor_cliente, valor_admin, valor_loja, codigo_transacao,
                    data_transacao, status, descricao
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $insertStmt->execute([
                $data['usuario_id'],
                $data['loja_id'],
                $data['valor_total'],
                $valorCashbackTotal,
                $valorCashbackCliente,
                $valorCashbackAdmin,
                $valorLoja,
                $data['codigo_transacao'],
                $dataTransacao,
                $transactionStatus,
                $descricao
            ]);
            
            if (!$result) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                return ['status' => false, 'message' => 'Falha ao inserir transação no banco.'];
            }
            
            $transactionId = $db->lastInsertId();

            // === INTEGRAÇÃO AUTOMÁTICA: UltraDirectNotifier (PRIORIDADE MÁXIMA) ===
            try {
                error_log("[ULTRA] TransactionController::registerTransactionFixed() - Disparando notificação ULTRA para transação {$transactionId}");

                // 🚀 PRIORIDADE 1: UltraDirectNotifier (Direto no bot)
                $ultraPath = __DIR__ . '/../classes/UltraDirectNotifier.php';
                if (file_exists($ultraPath)) {
                    require_once $ultraPath;
                    if (class_exists('UltraDirectNotifier')) {
                        $notifier = new UltraDirectNotifier();

                        // Preparar dados da transação (usando ID recém-criado)
                        $transactionData = [
                            'transaction_id' => $transactionId,
                            'cliente_telefone' => 'brutal_system', // Será resolvido pelo UltraDirectNotifier
                            'additional_data' => json_encode([
                                'transaction_id' => $transactionId,
                                'system' => 'registerTransactionFixed',
                                'timestamp' => date('Y-m-d H:i:s')
                            ])
                        ];

                        $result = $notifier->notifyTransaction($transactionData);
                        error_log("[ULTRA] registerTransactionFixed - Resultado: " . ($result['success'] ? 'SUCESSO' : 'FALHA') . " em " . ($result['time_ms'] ?? 0) . "ms");
                    } else {
                        error_log("[ULTRA] TransactionController::registerTransactionFixed() - Classe UltraDirectNotifier não encontrada");
                        $result = ['success' => false, 'message' => 'Classe UltraDirectNotifier não encontrada'];
                    }
                } else {
                    error_log("[ULTRA] TransactionController::registerTransactionFixed() - Arquivo não encontrado: {$ultraPath}");
                    $result = ['success' => false, 'message' => 'UltraDirectNotifier não encontrado'];
                }

                if ($result['success']) {
                    error_log("[ULTRA] TransactionController::registerTransactionFixed() - Notificação ULTRA enviada com sucesso!");
                } else {
                    error_log("[ULTRA] TransactionController::registerTransactionFixed() - Falha na notificação ULTRA: " . ($result['error'] ?? $result['message']));
                }

            } catch (Exception $e) {
                error_log("[ULTRA] TransactionController::registerTransactionFixed() - Erro na notificação ULTRA: " . $e->getMessage());
            }

            // Commit
            $db->commit();
            
            // Mensagem de sucesso
            $successMessage = $isStoreMvp ? 
                '🎉 Transação MVP aprovada instantaneamente! Cashback creditado automaticamente.' :
                'Transação registrada com sucesso!';
            
            // Se MVP, creditar cashback
            $cashbackCreditado = false;
            if ($isStoreMvp && $valorCashbackCliente > 0) {
                require_once __DIR__ . '/../models/CashbackBalance.php';
                $balanceModel = new CashbackBalance();
                $descricaoCashback = "Cashback MVP instantâneo - Código: " . $data['codigo_transacao'];
                
                $creditResult = $balanceModel->addBalance(
                    $data['usuario_id'],
                    $data['loja_id'],
                    $valorCashbackCliente,
                    $descricaoCashback,
                    $transactionId
                );
                
                $cashbackCreditado = $creditResult;
            }
            
            return [
                'status' => true,
                'message' => $successMessage,
                'data' => [
                    'transaction_id' => $transactionId,
                    'valor_original' => $data['valor_total'],
                    'valor_cashback' => $valorCashbackCliente,
                    'is_mvp' => $isStoreMvp,
                    'status_transacao' => $transactionStatus,
                    'cashback_creditado' => $cashbackCreditado
                ]
            ];
            
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            
            error_log('Erro em registerTransactionFixed: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao registrar transação. Tente novamente.'];
        }
    }
    
    public function getClientTransactionsPWA($clientId, $filtros = [], $limit = 20, $offset = 0) {
        try {
            $sql = "
                SELECT 
                    t.id,
                    t.valor_total,
                    t.valor_cashback,
                    t.saldo_usado,
                    t.data_transacao,
                    t.status,
                    t.tipo,
                    l.nome_fantasia as nome_loja,
                    l.id as loja_id
                FROM transacoes_cashback t
                INNER JOIN lojas l ON t.loja_id = l.id
                WHERE t.usuario_id = ?
            ";
            
            $params = [$clientId];
            
            // Aplicar filtros
            if (!empty($filtros['data_inicio'])) {
                $sql .= " AND DATE(t.data_transacao) >= ?";
                $params[] = $filtros['data_inicio'];
            }
            
            if (!empty($filtros['data_fim'])) {
                $sql .= " AND DATE(t.data_transacao) <= ?";
                $params[] = $filtros['data_fim'];
            }
            
            if (!empty($filtros['status'])) {
                $sql .= " AND t.status = ?";
                $params[] = $filtros['status'];
            }
            
            if (!empty($filtros['loja_id'])) {
                $sql .= " AND t.loja_id = ?";
                $params[] = $filtros['loja_id'];
            }
            
            if (!empty($filtros['tipo'])) {
                if ($filtros['tipo'] === 'cashback') {
                    $sql .= " AND t.tipo = 'cashback'";
                } elseif ($filtros['tipo'] === 'uso_saldo') {
                    $sql .= " AND t.saldo_usado > 0";
                }
            }
            
            $sql .= " ORDER BY t.data_transacao DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log('Erro ao buscar transações PWA: ' . $e->getMessage());
            return [];
        }
    }


    /**
     * Processa transações em lote a partir de um arquivo CSV
     * 
     * @param array $file Arquivo enviado ($_FILES['arquivo'])
     * @param int $storeId ID da loja
     * @return array Resultado da operação
     */
    public static function processBatchTransactions($file, $storeId) {
        try {
            // Verificar se o usuário está autenticado e é loja ou admin
            if (!AuthController::isAuthenticated()) {
                return ['status' => false, 'message' => 'Usuário não autenticado.'];
            }
            
            if (!AuthController::isStore() && !AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Apenas lojas e administradores podem registrar transações em lote.'];
            }
            
            // Validar o arquivo
            if (!isset($file) || !is_array($file) || $file['error'] !== UPLOAD_ERR_OK) {
                return ['status' => false, 'message' => 'Erro no upload do arquivo.'];
            }
            
            // Verificar extensão
            $fileInfo = pathinfo($file['name']);
            $extension = strtolower($fileInfo['extension']);
            
            if ($extension !== 'csv') {
                return ['status' => false, 'message' => 'Apenas arquivos CSV são permitidos.'];
            }
            
            // Verificar se a loja existe
            $db = Database::getConnection();
            $storeStmt = $db->prepare("SELECT id, nome_fantasia FROM lojas WHERE id = :loja_id AND status = :status");
            $storeStmt->bindParam(':loja_id', $storeId);
            $statusAprovado = STORE_APPROVED;
            $storeStmt->bindParam(':status', $statusAprovado);
            $storeStmt->execute();
            
            if ($storeStmt->rowCount() == 0) {
                return ['status' => false, 'message' => 'Loja não encontrada ou não aprovada.'];
            }
            
            // Ler o arquivo CSV
            $filePath = $file['tmp_name'];
            $handle = fopen($filePath, 'r');
            
            if (!$handle) {
                return ['status' => false, 'message' => 'Não foi possível abrir o arquivo.'];
            }
            
            // Ler cabeçalho
            $header = fgetcsv($handle, 1000, ',');
            
            if (!$header || count($header) < 3) {
                fclose($handle);
                return ['status' => false, 'message' => 'Formato de arquivo inválido. Verifique o modelo.'];
            }
            
            // Verificar colunas necessárias
            $requiredColumns = ['email', 'valor', 'codigo_transacao'];
            $headerMap = [];
            
            foreach ($requiredColumns as $required) {
                $found = false;
                
                foreach ($header as $index => $column) {
                    if (strtolower(trim($column)) === $required) {
                        $headerMap[$required] = $index;
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    fclose($handle);
                    return ['status' => false, 'message' => 'Coluna obrigatória não encontrada: ' . $required];
                }
            }
            
            // Iniciar processamento
            $totalProcessed = 0;
            $successCount = 0;
            $errorCount = 0;
            $errors = [];
            
            // Iniciar transação de banco de dados
            $db->beginTransaction();
            
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                $totalProcessed++;
                
                // Extrair dados
                $email = trim($row[$headerMap['email']]);
                $valor = str_replace(['R$', '.', ','], ['', '', '.'], trim($row[$headerMap['valor']]));
                $codigoTransacao = trim($row[$headerMap['codigo_transacao']]);
                
                // Obter descrição se existir
                $descricao = '';
                if (isset($headerMap['descricao']) && isset($row[$headerMap['descricao']])) {
                    $descricao = trim($row[$headerMap['descricao']]);
                }
                
                // Obter data se existir
                $dataTransacao = date('Y-m-d H:i:s');
                if (isset($headerMap['data']) && isset($row[$headerMap['data']])) {
                    $dataStr = trim($row[$headerMap['data']]);
                    if (!empty($dataStr)) {
                        $timestamp = strtotime($dataStr);
                        if ($timestamp !== false) {
                            $dataTransacao = date('Y-m-d H:i:s', $timestamp);
                        }
                    }
                }
                
                // Validações básicas
                if (empty($email) || empty($valor) || empty($codigoTransacao)) {
                    $errorCount++;
                    $errors[] = "Linha {$totalProcessed}: Dados incompletos";
                    continue;
                }
                
                if (!is_numeric($valor) || $valor <= 0) {
                    $errorCount++;
                    $errors[] = "Linha {$totalProcessed}: Valor inválido";
                    continue;
                }
                
                // Buscar ID do usuário pelo email
                $userStmt = $db->prepare("SELECT id FROM usuarios WHERE email = :email AND tipo = :tipo AND status = :status");
                $userStmt->bindParam(':email', $email);
                $tipoCliente = USER_TYPE_CLIENT;
                $userStmt->bindParam(':tipo', $tipoCliente);
                $statusAtivo = USER_ACTIVE;
                $userStmt->bindParam(':status', $statusAtivo);
                $userStmt->execute();
                $user = $userStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user) {
                    $errorCount++;
                    $errors[] = "Linha {$totalProcessed}: Cliente com email {$email} não encontrado ou inativo";
                    continue;
                }
                
                // Verificar se já existe transação com este código
                $checkStmt = $db->prepare("
                    SELECT id FROM transacoes_cashback 
                    WHERE codigo_transacao = :codigo_transacao AND loja_id = :loja_id
                ");
                $checkStmt->bindParam(':codigo_transacao', $codigoTransacao);
                $checkStmt->bindParam(':loja_id', $storeId);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() > 0) {
                    $errorCount++;
                    $errors[] = "Linha {$totalProcessed}: Transação com código {$codigoTransacao} já existe";
                    continue;
                }
                
                // Preparar dados para registro
                $transactionData = [
                    'usuario_id' => $user['id'],
                    'loja_id' => $storeId,
                    'valor_total' => $valor,
                    'codigo_transacao' => $codigoTransacao,
                    'descricao' => $descricao,
                    'data_transacao' => $dataTransacao
                ];
                
                // Registrar transação
                $result = self::registerTransaction($transactionData);
                
                if ($result['status']) {
                    $successCount++;
                } else {
                    $errorCount++;
                    $errors[] = "Linha {$totalProcessed}: " . $result['message'];
                }
            }
            
            fclose($handle);
            
            // Finalizar transação
            if ($errorCount == 0) {
                $db->commit();
                return [
                    'status' => true,
                    'message' => "Processamento concluído com sucesso. {$successCount} transações registradas.",
                    'data' => [
                        'total_processado' => $totalProcessed,
                        'sucesso' => $successCount,
                        'erros' => $errorCount
                    ]
                ];
            } else {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                return [
                    'status' => false,
                    'message' => "Processamento concluído com erros. Nenhuma transação foi registrada.",
                    'data' => [
                        'total_processado' => $totalProcessed,
                        'sucesso' => 0,
                        'erros' => $errorCount,
                        'detalhes_erros' => $errors
                    ]
                ];
            }
            
        } catch (Exception $e) {
            // Reverter transação em caso de erro
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            
            error_log('Erro ao processar transações em lote: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao processar transações em lote. Tente novamente.'];
        }
    }
    
    /**
    * Registra pagamento de comissões (VERSÃO CORRIGIDA)
    * 
    * @param array $data Dados do pagamento
    * @return array Resultado da operação
    */
    public static function registerPayment($data) {
        try {
            error_log("registerPayment - Dados recebidos: " . print_r($data, true));
            
            // Validação básica
            if (!isset($data['loja_id']) || !isset($data['transacoes']) || !isset($data['valor_total'])) {
                return ['status' => false, 'message' => 'Dados obrigatórios faltando'];
            }
            
            // Verificar se o usuário está autenticado e é loja ou admin
            if (!AuthController::isAuthenticated()) {
                return ['status' => false, 'message' => 'Usuário não autenticado.'];
            }
            
            if (!AuthController::isStore() && !AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Apenas lojas e administradores podem registrar pagamentos.'];
            }
            
            $db = Database::getConnection();
            
            // Converter transações para array se necessário
            $transactionIds = is_array($data['transacoes']) ? $data['transacoes'] : explode(',', $data['transacoes']);
            $transactionIds = array_map('intval', $transactionIds);
            
            if (empty($transactionIds)) {
                return ['status' => false, 'message' => 'Nenhuma transação selecionada'];
            }
            
            error_log("registerPayment - IDs: " . implode(',', $transactionIds));
            
            // CORREÇÃO: Validar se todas as transações existem e calcular valor total correto
            $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
            $validateStmt = $db->prepare("
                SELECT 
                    id,
                    (valor_cliente + valor_admin) as comissao_total,
                    status,
                    loja_id
                FROM transacoes_cashback 
                WHERE id IN ($placeholders) AND loja_id = ? AND status = ?
            ");
            
            $validateParams = array_merge($transactionIds, [$data['loja_id'], TRANSACTION_PENDING]);
            $validateStmt->execute($validateParams);
            $transactions = $validateStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Verificar se todas as transações foram encontradas
            if (count($transactions) !== count($transactionIds)) {
                return [
                    'status' => false, 
                    'message' => 'Algumas transações não foram encontradas ou não estão pendentes. Esperado: ' . count($transactionIds) . ', Encontrado: ' . count($transactions)
                ];
            }
            
            // CORREÇÃO: Calcular valor total correto (soma das comissões totais)
            $totalCalculated = 0;
            foreach ($transactions as $transaction) {
                $totalCalculated += $transaction['comissao_total'];
            }
            
            // Validar se o valor informado bate com o calculado
            $valorInformado = floatval($data['valor_total']);
            if (abs($totalCalculated - $valorInformado) > 0.01) {
                error_log("registerPayment - Erro valor: Calculado=$totalCalculated, Informado=$valorInformado");
                return [
                    'status' => false, 
                    'message' => 'Valor total informado (R$ ' . number_format($valorInformado, 2, ',', '.') . 
                    ') não confere com o valor das transações selecionadas (R$ ' . number_format($totalCalculated, 2, ',', '.') . ')'
                ];
            }
            
            // Validar valores numéricos
            if ($valorInformado <= 0) {
                return ['status' => false, 'message' => 'Valor total deve ser maior que zero'];
            }
            
            // Iniciar transação no banco de dados
            $db->beginTransaction();
            
            try {
                // 1. Inserir o pagamento
                $stmt = $db->prepare("
                    INSERT INTO pagamentos_comissao 
                    (loja_id, valor_total, metodo_pagamento, numero_referencia, comprovante, observacao, status, data_registro) 
                    VALUES (?, ?, ?, ?, ?, ?, 'pendente', NOW())
                ");
                
                $result = $stmt->execute([
                    $data['loja_id'],
                    $totalCalculated, // Usar valor calculado para garantir precisão
                    $data['metodo_pagamento'] ?? 'pix',
                    $data['numero_referencia'] ?? '',
                    $data['comprovante'] ?? '',
                    $data['observacao'] ?? ''
                ]);
                
                if (!$result) {
                    throw new Exception('Erro ao inserir pagamento');
                }
                
                $paymentId = $db->lastInsertId();
                error_log("registerPayment - Payment ID criado: $paymentId");
                
                // 2. Associar transações ao pagamento
                $assocStmt = $db->prepare("INSERT INTO pagamentos_transacoes (pagamento_id, transacao_id) VALUES (?, ?)");
                
                foreach ($transactionIds as $transId) {
                    $assocResult = $assocStmt->execute([$paymentId, $transId]);
                    if (!$assocResult) {
                        throw new Exception("Erro ao associar transação $transId");
                    }
                    error_log("registerPayment - Transação $transId associada");
                }
                
                // 3. Atualizar status das transações
                $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
                $updateStmt = $db->prepare("UPDATE transacoes_cashback SET status = 'pagamento_pendente' WHERE id IN ($placeholders)");
                
                $updateResult = $updateStmt->execute($transactionIds);
                if (!$updateResult) {
                    throw new Exception('Erro ao atualizar status das transações');
                }
                
                // 4. Criar notificação para admin
                self::createNotification(
                    1, // Admin padrão
                    'Novo pagamento registrado',
                    'Nova solicitação de pagamento de comissão de R$ ' . number_format($totalCalculated, 2, ',', '.') . ' aguardando aprovação.',
                    'info'
                );
                
                // 5. Log de sucesso
                error_log("registerPayment - Pagamento registrado com sucesso: ID=$paymentId, Valor=$totalCalculated, Transações=" . implode(',', $transactionIds));
                
                // Commit da transação
                $db->commit();
                error_log("registerPayment - Sucesso total!");
                
                return [
                    'status' => true,
                    'message' => 'Pagamento registrado com sucesso! Aguardando aprovação da administração.',
                    'data' => [
                        'payment_id' => $paymentId,
                        'valor_total' => $totalCalculated,
                        'total_transacoes' => count($transactionIds)
                    ]
                ];
                
            } catch (Exception $e) {
                // Rollback em caso de erro
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log("registerPayment - Erro durante transação: " . $e->getMessage());
                throw $e;
            }
            
        } catch (Exception $e) {
            error_log("registerPayment - ERRO: " . $e->getMessage());
            return [
                'status' => false, 
                'message' => 'Erro ao registrar pagamento: ' . $e->getMessage()
            ];
        }
    }
    



    
    /**
    * Aprova um pagamento de comissão
    * 
    * @param int $paymentId ID do pagamento
    * @param string $observacao Observação opcional
    * @return array Resultado da operação
    */
    public static function approvePayment($paymentId, $observacao = '') {
        try {
            // Verificar se o usuário está autenticado e é administrador
            if (!AuthController::isAuthenticated() || !AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            $db = Database::getConnection();
            
            // Verificar se o pagamento existe e está pendente
            $paymentStmt = $db->prepare("
                SELECT p.*, l.nome_fantasia as loja_nome
                FROM pagamentos_comissao p
                JOIN lojas l ON p.loja_id = l.id
                WHERE p.id = ? AND p.status = 'pendente'
            ");
            $paymentStmt->execute([$paymentId]);
            $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payment) {
                return ['status' => false, 'message' => 'Pagamento não encontrado ou não está pendente.'];
            }
            
            // Obter transações associadas ao pagamento ANTES de iniciar a transação
            $transStmt = $db->prepare("
                SELECT t.id, t.usuario_id, t.loja_id, t.valor_cliente
                FROM pagamentos_transacoes pt
                JOIN transacoes_cashback t ON pt.transacao_id = t.id
                WHERE pt.pagamento_id = ?
            ");
            $transStmt->execute([$paymentId]);
            $transactions = $transStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($transactions) == 0) {
                return ['status' => false, 'message' => 'Nenhuma transação encontrada para este pagamento.'];
            }
            
            // Iniciar transação principal
            $db->beginTransaction();
            
            try {
                // 1. Atualizar status do pagamento
                $updatePaymentStmt = $db->prepare("
                    UPDATE pagamentos_comissao
                    SET status = 'aprovado', data_aprovacao = NOW(), observacao_admin = ?
                    WHERE id = ?
                ");
                $updatePaymentResult = $updatePaymentStmt->execute([$observacao, $paymentId]);
                
                if (!$updatePaymentResult) {
                    throw new Exception('Erro ao atualizar status do pagamento');
                }
                
                // 2. Atualizar status das transações
                $transactionIds = array_column($transactions, 'id');
                $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
                
                $updateTransStmt = $db->prepare("
                    UPDATE transacoes_cashback 
                    SET status = 'aprovado' 
                    WHERE id IN ($placeholders)
                ");
                $updateTransResult = $updateTransStmt->execute($transactionIds);
                
                if (!$updateTransResult) {
                    throw new Exception('Erro ao atualizar status das transações');
                }
                
                // 3. Atualizar comissões
                $updateCommissionStmt = $db->prepare("
                    UPDATE transacoes_comissao 
                    SET status = 'aprovado' 
                    WHERE transacao_id IN ($placeholders)
                ");
                $updateCommissionResult = $updateCommissionStmt->execute($transactionIds);
                
                if (!$updateCommissionResult) {
                    throw new Exception('Erro ao atualizar status das comissões');
                }
                
                // Commit da transação principal ANTES de creditar saldos
                $db->commit();
                error_log("APROVAÇÃO: Transação principal commitada com sucesso");
                
                // 4. Creditar saldos FORA da transação principal para evitar conflitos
                require_once __DIR__ . '/../models/CashbackBalance.php';
                require_once __DIR__ . '/AdminController.php';
                $balanceModel = new CashbackBalance();
                $saldosCreditados = 0;
                $totalCashbackReservado = 0; // NOVO: controlar total para reserva
                
                foreach ($transactions as $transaction) {
                    if ($transaction['valor_cliente'] > 0) {
                        $description = "Cashback da compra - Transação #{$transaction['id']} (Pagamento #{$paymentId} aprovado)";
                        
                        $creditResult = $balanceModel->addBalance(
                            $transaction['usuario_id'],
                            $transaction['loja_id'],
                            $transaction['valor_cliente'],
                            $description,
                            $transaction['id']
                        );
                        
                        if ($creditResult) {
                            $saldosCreditados++;
                            $totalCashbackReservado += $transaction['valor_cliente']; // NOVO
                            error_log("APROVAÇÃO: Saldo creditado com sucesso - Transação: {$transaction['id']}");
                        } else {
                            error_log("APROVAÇÃO: ERRO ao creditar saldo - Transação: {$transaction['id']}");
                        }
                    }
                }
                
                // NOVO: 5. Criar reserva de cashback após creditar saldos dos clientes
                if ($totalCashbackReservado > 0) {
                    $reservaResult = self::createCashbackReserve(
                        $totalCashbackReservado, 
                        $paymentId, 
                        "Reserva de cashback - Pagamento #{$paymentId} aprovado - Total de clientes: {$saldosCreditados}"
                    );
                    
                    if (!$reservaResult) {
                        error_log("APROVAÇÃO: ERRO ao criar reserva de cashback para pagamento #{$paymentId}");
                    } else {
                        error_log("APROVAÇÃO: Reserva de cashback criada: R$ {$totalCashbackReservado}");
                    }
                }
                
                // Atualizar saldo do administrador (após o commit principal)
                foreach ($transactions as $transaction) {
                    // Obter valor da comissão do admin para esta transação
                    $adminComissionStmt = $db->prepare("
                        SELECT valor_comissao 
                        FROM transacoes_comissao 
                        WHERE transacao_id = ? AND tipo_usuario = 'admin'
                    ");
                    $adminComissionStmt->execute([$transaction['id']]);
                    $adminComission = $adminComissionStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($adminComission && $adminComission['valor_comissao'] > 0) {
                        $descricao = "Comissão da transação #{$transaction['id']} - Pagamento #{$paymentId} aprovado";
                        
                        $updateResult = AdminController::updateAdminBalance(
                            $adminComission['valor_comissao'],
                            $transaction['id'],
                            $descricao
                        );
                        
                        if (!$updateResult) {
                            error_log("APROVAÇÃO: Falha ao atualizar saldo admin para transação #{$transaction['id']}");
                        }
                    }
                }
                
                // 5. Criar notificações (fora da transação)
                $clienteNotificados = [];
                foreach ($transactions as $transaction) {
                    if (!in_array($transaction['usuario_id'], $clienteNotificados)) {
                        // Obter detalhes do cliente
                        $clientStmt = $db->prepare("SELECT nome, email FROM usuarios WHERE id = ?");
                        $clientStmt->execute([$transaction['usuario_id']]);
                        $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($client) {
                            // Calcular total de cashback para este cliente
                            $clientTransStmt = $db->prepare("
                                SELECT COUNT(*) as total_trans, SUM(valor_cliente) as total_cashback
                                FROM transacoes_cashback
                                WHERE id IN ($placeholders) AND usuario_id = ?
                            ");
                            $params = array_merge($transactionIds, [$transaction['usuario_id']]);
                            $clientTransStmt->execute($params);
                            $clientTrans = $clientTransStmt->fetch(PDO::FETCH_ASSOC);
                            
                            // Criar notificação
                            if ($clientTrans['total_trans'] > 0) {
                                self::createNotification(
                                    $transaction['usuario_id'],
                                    'Cashback disponível!',
                                    'Seu cashback de R$ ' . number_format($clientTrans['total_cashback'], 2, ',', '.') . 
                                    ' da loja ' . $payment['loja_nome'] . ' está disponível.',
                                    'success'
                                );
                                
                                $clienteNotificados[] = $transaction['usuario_id'];
                            }
                        }
                    }
                }
                
                // 6. Notificar loja
                $storeUserStmt = $db->prepare("SELECT usuario_id FROM lojas WHERE id = ?");
                $storeUserStmt->execute([$payment['loja_id']]);
                $storeUser = $storeUserStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($storeUser && !empty($storeUser['usuario_id'])) {
                    self::createNotification(
                        $storeUser['usuario_id'],
                        'Pagamento aprovado',
                        'Seu pagamento de comissão no valor de R$ ' . number_format($payment['valor_total'], 2, ',', '.') . ' foi aprovado.',
                        'success'
                    );
                }
                
                return [
                    'status' => true,
                    'message' => 'Pagamento aprovado com sucesso! Cashback liberado para os clientes.',
                    'data' => [
                        'payment_id' => $paymentId,
                        'transacoes_atualizadas' => count($transactions),
                        'saldos_creditados' => $saldosCreditados
                    ]
                ];
                
            } catch (Exception $e) {
                // Rollback apenas se a transação ainda estiver ativa
                if ($db->inTransaction()) {
                    $db->rollBack();
                    error_log('APROVAÇÃO: Rollback executado devido ao erro: ' . $e->getMessage());
                }
                throw $e;
            }
            
        } catch (Exception $e) {
            error_log('APROVAÇÃO: Erro geral ao aprovar pagamento: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao aprovar pagamento: ' . $e->getMessage()];
        }
    }
    /**
    * NOVO MÉTODO: Cria reserva de cashback
    * 
    * @param float $valor Valor a ser reservado
    * @param int $transacaoId ID da transação relacionada
    * @param string $descricao Descrição da operação
    * @return bool Resultado da operação
    */
    private static function createCashbackReserve($valor, $transacaoId = null, $descricao = '') {
        try {
            $db = Database::getConnection();
            
            // Obter ou criar registro da reserva
            $reservaStmt = $db->prepare("SELECT * FROM admin_reserva_cashback WHERE id = 1");
            $reservaStmt->execute();
            $reserva = $reservaStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$reserva) {
                // Criar registro inicial se não existir
                $createStmt = $db->prepare("
                    INSERT INTO admin_reserva_cashback (id, valor_total, valor_disponivel, valor_usado) 
                    VALUES (1, 0, 0, 0)
                ");
                $createStmt->execute();
                $reserva = [
                    'valor_total' => 0,
                    'valor_disponivel' => 0,
                    'valor_usado' => 0
                ];
            }
            
            // Calcular novos valores (CRÉDITO = cashback disponibilizado para clientes)
            $novoTotal = $reserva['valor_total'] + $valor;
            $novoDisponivel = $reserva['valor_disponivel'] + $valor;
            $novoUsado = $reserva['valor_usado']; // Não muda ainda
            
            // Atualizar reserva
            $updateStmt = $db->prepare("
                UPDATE admin_reserva_cashback 
                SET valor_total = ?, valor_disponivel = ?, valor_usado = ?, ultima_atualizacao = NOW() 
                WHERE id = 1
            ");
            $updateStmt->execute([$novoTotal, $novoDisponivel, $novoUsado]);
            
            // Registrar movimentação
            $movStmt = $db->prepare("
                INSERT INTO admin_reserva_movimentacoes (transacao_id, valor, tipo, descricao) 
                VALUES (?, ?, 'credito', ?)
            ");
            $movStmt->execute([$transacaoId, $valor, $descricao]);
            
            return true;
            
        } catch (Exception $e) {
            error_log('Erro ao criar reserva de cashback: ' . $e->getMessage());
            return false;
        }
    }
    private static function updateAdminBalance($valor, $transacaoId, $paymentId) {
        try {
            $db = Database::getConnection();
            
            // Verificar/criar registro na tabela admin_saldo
            $adminSaldoStmt = $db->prepare("SELECT * FROM admin_saldo WHERE id = 1");
            $adminSaldoStmt->execute();
            $adminSaldo = $adminSaldoStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$adminSaldo) {
                $createStmt = $db->prepare("
                    INSERT INTO admin_saldo (id, valor_total, valor_disponivel, valor_pendente) 
                    VALUES (1, 0, 0, 0)
                ");
                $createStmt->execute();
            }
            
            // Atualizar saldo
            $updateStmt = $db->prepare("
                UPDATE admin_saldo 
                SET valor_total = valor_total + ?, 
                    valor_disponivel = valor_disponivel + ?,
                    ultima_atualizacao = NOW()
                WHERE id = 1
            ");
            $updateStmt->execute([$valor, $valor]);
            
            // Registrar movimentação
            $movStmt = $db->prepare("
                INSERT INTO admin_saldo_movimentacoes (transacao_id, valor, tipo, descricao) 
                VALUES (?, ?, 'credito', ?)
            ");
            $descricao = "Comissão da transação #{$transacaoId} - Pagamento PIX #{$paymentId} aprovado automaticamente";
            $movStmt->execute([$transacaoId, $valor, $descricao]);
            
            return true;
            
        } catch (Exception $e) {
            error_log('Erro ao atualizar saldo admin: ' . $e->getMessage());
            return false;
        }
    }
    public static function approvePaymentAutomatically($paymentId, $observacao = '') {
        try {
            $db = Database::getConnection();
            
            // Iniciar transação
            $db->beginTransaction();
            
            // Buscar dados do pagamento
            $stmt = $db->prepare("
                SELECT * FROM pagamentos_comissao 
                WHERE id = ? AND status IN ('pendente', 'pix_aguardando')
            ");
            $stmt->execute([$paymentId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payment) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                return ['status' => false, 'message' => 'Pagamento não encontrado ou já processado'];
            }
            
            // Buscar transações relacionadas
            $transactionsStmt = $db->prepare("
                SELECT tc.*, cm.valor_usado 
                FROM pagamentos_transacoes pt
                JOIN transacoes_cashback tc ON pt.transacao_id = tc.id
                LEFT JOIN (
                    SELECT transacao_uso_id, SUM(valor) as valor_usado 
                    FROM cashback_movimentacoes 
                    WHERE tipo_operacao = 'uso' 
                    GROUP BY transacao_uso_id
                ) cm ON tc.id = cm.transacao_uso_id
                WHERE pt.pagamento_id = ?
            ");
            $transactionsStmt->execute([$paymentId]);
            $transactions = $transactionsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($transactions)) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                return ['status' => false, 'message' => 'Nenhuma transação encontrada para este pagamento'];
            }
            
            // Atualizar status do pagamento
            $updatePaymentStmt = $db->prepare("
                UPDATE pagamentos_comissao 
                SET status = 'aprovado',
                    data_aprovacao = NOW(),
                    observacao_admin = ?
                WHERE id = ?
            ");
            $updatePaymentStmt->execute([$observacao, $paymentId]);
            
            $totalCashbackLiberado = 0;
            $transacoesAprovadas = 0;
            
            // Processar cada transação
            foreach ($transactions as $transaction) {
                // Atualizar status da transação para 'aprovado'
                $updateTransactionStmt = $db->prepare("
                    UPDATE transacoes_cashback 
                    SET status = 'aprovado' 
                    WHERE id = ?
                ");
                $updateTransactionStmt->execute([$transaction['id']]);
                
                // Liberar cashback para o cliente
                $cashbackValue = $transaction['valor_cliente'];
                $totalCashbackLiberado += $cashbackValue;
                
                // Verificar se o saldo já existe
                $saldoCheckStmt = $db->prepare("
                    SELECT id FROM cashback_saldos 
                    WHERE usuario_id = ? AND loja_id = ?
                ");
                $saldoCheckStmt->execute([$transaction['usuario_id'], $transaction['loja_id']]);
                
                if ($saldoCheckStmt->rowCount() > 0) {
                    // Atualizar saldo existente
                    $updateSaldoStmt = $db->prepare("
                        UPDATE cashback_saldos 
                        SET saldo_disponivel = saldo_disponivel + ?,
                            total_creditado = total_creditado + ?,
                            ultima_atualizacao = NOW()
                        WHERE usuario_id = ? AND loja_id = ?
                    ");
                    $updateSaldoStmt->execute([
                        $cashbackValue, 
                        $cashbackValue, 
                        $transaction['usuario_id'], 
                        $transaction['loja_id']
                    ]);
                } else {
                    // Criar novo saldo
                    $insertSaldoStmt = $db->prepare("
                        INSERT INTO cashback_saldos 
                        (usuario_id, loja_id, saldo_disponivel, total_creditado) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $insertSaldoStmt->execute([
                        $transaction['usuario_id'], 
                        $transaction['loja_id'], 
                        $cashbackValue, 
                        $cashbackValue
                    ]);
                }
                
                // Registrar movimentação de cashback
                $insertMovStmt = $db->prepare("
                    INSERT INTO cashback_movimentacoes 
                    (usuario_id, loja_id, tipo_operacao, valor, saldo_anterior, saldo_atual, 
                    descricao, transacao_origem_id, pagamento_id) 
                    VALUES (?, ?, 'credito', ?, 
                            COALESCE((SELECT saldo_disponivel FROM cashback_saldos WHERE usuario_id = ? AND loja_id = ? LIMIT 1), 0) - ?,
                            COALESCE((SELECT saldo_disponivel FROM cashback_saldos WHERE usuario_id = ? AND loja_id = ? LIMIT 1), 0),
                            ?, ?, ?)
                ");
                $insertMovStmt->execute([
                    $transaction['usuario_id'],
                    $transaction['loja_id'],
                    $cashbackValue,
                    $transaction['usuario_id'],
                    $transaction['loja_id'],
                    $cashbackValue,
                    $transaction['usuario_id'],
                    $transaction['loja_id'],
                    'Cashback liberado - Pagamento aprovado automaticamente',
                    $transaction['id'],
                    $paymentId
                ]);
                
                // Atualizar saldo do admin
                self::updateAdminBalance($cashbackValue, 'credito', "Comissão recebida - Transação {$transaction['id']}");
                
                $transacoesAprovadas++;
                
                // Enviar notificação para o cliente
                self::sendCashbackNotification($transaction['usuario_id'], $cashbackValue, $payment['loja_id']);
            }
            
            // Commit da transação
            $db->commit();
            
            return [
                'status' => true,
                'message' => 'Pagamento aprovado e cashback liberado automaticamente',
                'data' => [
                    'pagamento_id' => $paymentId,
                    'transacoes_aprovadas' => $transacoesAprovadas,
                    'cashback_liberado' => $totalCashbackLiberado
                ]
            ];
            
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Erro ao aprovar pagamento automaticamente: " . $e->getMessage());
            return ['status' => false, 'message' => 'Erro interno: ' . $e->getMessage()];
        }
    }

    /**
     * Enviar notificação de cashback liberado para o cliente
     * Versão integrada que inclui notificação automática via WhatsApp
     */
    private static function sendCashbackNotification($userId, $cashbackValue, $lojaId) {
        try {
            $db = Database::getConnection();
            
            // Buscar informações completas da loja e do cliente em uma consulta otimizada
            $stmt = $db->prepare("
                SELECT 
                    l.nome_fantasia as loja_nome,
                    u.telefone as cliente_telefone,
                    u.nome as cliente_nome
                FROM lojas l
                CROSS JOIN usuarios u 
                WHERE l.id = ? AND u.id = ?
            ");
            $stmt->execute([$lojaId, $userId]);
            $notificationData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $nomeLoja = $notificationData ? $notificationData['loja_nome'] : 'Loja Parceira';
            
            // FUNCIONALIDADE EXISTENTE: Criar notificação interna (preservada integralmente)
            $notifStmt = $db->prepare("
                INSERT INTO notificacoes (usuario_id, titulo, mensagem, tipo) 
                VALUES (?, ?, ?, 'success')
            ");
            $notifStmt->execute([
                $userId,
                'Cashback Liberado!',
                "Seu cashback de R$ " . number_format($cashbackValue, 2, ',', '.') . 
                " da loja {$nomeLoja} foi liberado e está disponível para uso!"
            ]);
            
            // NOVA FUNCIONALIDADE: Notificação automática via WhatsApp
            if (defined('WHATSAPP_ENABLED') && WHATSAPP_ENABLED && 
                $notificationData && !empty($notificationData['cliente_telefone'])) {
                
                try {
                    // Carregar a classe WhatsApp
                    if (!class_exists('WhatsAppBot')) {
                        require_once __DIR__ . '/../utils/WhatsAppBot.php';
                    }
                    
                    // Preparar dados estruturados para o template de cashback liberado
                    $whatsappTransactionData = [
                        'valor_cashback' => $cashbackValue,
                        'nome_loja' => $nomeLoja
                    ];
                    
                    // Enviar notificação via WhatsApp usando template específico
                    WhatsAppBot::sendCashbackReleasedNotification(
                        $notificationData['cliente_telefone'], 
                        $whatsappTransactionData
                    );
                    
                    // O resultado será automaticamente registrado em nosso sistema de logs
                    // Você poderá monitorar o sucesso na interface que acabamos de validar
                    
                } catch (Exception $whatsappException) {
                    // Log específico para erros de WhatsApp sem afetar o fluxo principal
                    error_log("WhatsApp Cashback Liberado - Erro: " . $whatsappException->getMessage());
                }
            }
            
        } catch (Exception $e) {
            // Log de erro geral mantendo a funcionalidade do sistema intacta
            error_log('Erro na notificação de cashback liberado: ' . $e->getMessage());
        }
    }

    /**
     * Rejeita um pagamento de comissão
     * 
     * @param int $paymentId ID do pagamento
     * @param string $motivo Motivo da rejeição
     * @return array Resultado da operação
     */
    public static function rejectPayment($paymentId, $motivo) {
        try {
            // Verificar se o usuário está autenticado e é administrador
            if (!AuthController::isAuthenticated() || !AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            if (empty($motivo)) {
                return ['status' => false, 'message' => 'É necessário informar o motivo da rejeição.'];
            }
            
            $db = Database::getConnection();
            
            // Verificar se o pagamento existe e está pendente
            $paymentStmt = $db->prepare("
                SELECT p.*, l.nome_fantasia as loja_nome
                FROM pagamentos_comissao p
                JOIN lojas l ON p.loja_id = l.id
                WHERE p.id = :payment_id AND p.status = 'pendente'
            ");
            $paymentStmt->bindParam(':payment_id', $paymentId);
            $paymentStmt->execute();
            $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payment) {
                return ['status' => false, 'message' => 'Pagamento não encontrado ou não está pendente.'];
            }
            
            // Iniciar transação
            $db->beginTransaction();
            
            // Atualizar status do pagamento
            $updatePaymentStmt = $db->prepare("
                UPDATE pagamentos_comissao
                SET status = :status, data_aprovacao = NOW(), observacao_admin = :observacao
                WHERE id = :payment_id
            ");
            $status = 'rejeitado';
            $updatePaymentStmt->bindParam(':status', $status);
            $updatePaymentStmt->bindParam(':observacao', $motivo);
            $updatePaymentStmt->bindParam(':payment_id', $paymentId);
            $updatePaymentStmt->execute();
            
            // Obter transações associadas ao pagamento
            $transStmt = $db->prepare("
                SELECT t.id, t.usuario_id, t.valor_total, t.valor_cliente
                FROM pagamentos_transacoes pt
                JOIN transacoes_cashback t ON pt.transacao_id = t.id
                WHERE pt.pagamento_id = :payment_id
            ");
            $transStmt->bindParam(':payment_id', $paymentId);
            $transStmt->execute();
            $transactions = $transStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Atualizar status das transações para pendente novamente
            if (count($transactions) > 0) {
                $transactionIds = array_column($transactions, 'id');
                $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
                
                $updateTransStmt = $db->prepare("
                    UPDATE transacoes_cashback 
                    SET status = :novo_status 
                    WHERE id IN ($placeholders)
                ");
                
                $novoStatus = TRANSACTION_PENDING;
                $updateTransStmt->bindParam(':novo_status', $novoStatus);
                
                for ($i = 0; $i < count($transactionIds); $i++) {
                    $updateTransStmt->bindValue($i + 1, $transactionIds[$i]);
                }
                
                $updateTransStmt->execute();
            }
            
            // Notificar loja
            $storeNotifyStmt = $db->prepare("
                SELECT id, usuario_id, email FROM lojas WHERE id = :loja_id
            ");
            $storeNotifyStmt->bindParam(':loja_id', $payment['loja_id']);
            $storeNotifyStmt->execute();
            $storeNotify = $storeNotifyStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($storeNotify) {
                // Notificação no sistema (se houver usuário vinculado)
                if (!empty($storeNotify['usuario_id'])) {
                    self::createNotification(
                        $storeNotify['usuario_id'],
                        'Pagamento rejeitado',
                        'Seu pagamento de comissão no valor de R$ ' . number_format($payment['valor_total'], 2, ',', '.') . 
                        ' foi rejeitado. Motivo: ' . $motivo,
                        'error'
                    );
                }
                
                // Email
                if (!empty($storeNotify['email'])) {
                    $subject = 'Pagamento Rejeitado - Klube Cash';
                    $message = "
                        <h3>Olá, {$payment['loja_nome']}!</h3>
                        <p>Infelizmente, seu pagamento de comissão foi rejeitado.</p>
                        <p><strong>Valor:</strong> R$ " . number_format($payment['valor_total'], 2, ',', '.') . "</p>
                        <p><strong>Método:</strong> {$payment['metodo_pagamento']}</p>
                        <p><strong>Data:</strong> " . date('d/m/Y H:i:s') . "</p>
                        <p><strong>Motivo da rejeição:</strong> " . nl2br(htmlspecialchars($motivo)) . "</p>
                        <p>Por favor, verifique o motivo da rejeição e registre um novo pagamento.</p>
                        <p>Atenciosamente,<br>Equipe Klube Cash</p>
                    ";
                    
                    Email::send($storeNotify['email'], $subject, $message, $payment['loja_nome']);
                }
            }
            
            // Confirmar transação
            $db->commit();
            
            return [
                'status' => true,
                'message' => 'Pagamento rejeitado com sucesso.',
                'data' => [
                    'payment_id' => $paymentId,
                    'transacoes_atualizadas' => count($transactions)
                ]
            ];
            
        } catch (PDOException $e) {
            // Reverter transação em caso de erro
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            
            error_log('Erro ao rejeitar pagamento: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao rejeitar pagamento. Tente novamente.'];
        }
    }
    
    /**
    * Obtém lista de transações pendentes para uma loja
    * 
    * @param int $storeId ID da loja
    * @param array $filters Filtros adicionais
    * @param int $page Página atual
    * @return array Lista de transações pendentes
    */
    public static function getPendingTransactions($storeId, $filters = [], $page = 1) {
        try {
            // Verificar se o usuário está autenticado
            if (!AuthController::isAuthenticated()) {
                return ['status' => false, 'message' => 'Usuário não autenticado.'];
            }
            
            $db = Database::getConnection();
            
            // Verificar permissões - apenas a loja dona das transações ou admin podem acessar
            if (AuthController::isStore()) {
                $currentUserId = AuthController::getCurrentUserId();
                $storeOwnerQuery = $db->prepare("SELECT usuario_id FROM lojas WHERE id = :loja_id");
                $storeOwnerQuery->bindParam(':loja_id', $storeId);
                $storeOwnerQuery->execute();
                $storeOwner = $storeOwnerQuery->fetch(PDO::FETCH_ASSOC);
                
                if (!$storeOwner || $storeOwner['usuario_id'] != $currentUserId) {
                    return ['status' => false, 'message' => 'Acesso não autorizado a esta loja.'];
                }
            } elseif (!AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Acesso não autorizado.'];
            }
            
            // Verificar se a loja existe
            $storeStmt = $db->prepare("SELECT id FROM lojas WHERE id = :loja_id");
            $storeStmt->bindParam(':loja_id', $storeId);
            $storeStmt->execute();
            
            if ($storeStmt->rowCount() == 0) {
                return ['status' => false, 'message' => 'Loja não encontrada.'];
            }
            
            // Construir consulta
            $query = "
                SELECT t.*, u.nome as cliente_nome, u.email as cliente_email
                FROM transacoes_cashback t
                JOIN usuarios u ON t.usuario_id = u.id
                WHERE t.loja_id = :loja_id AND t.status = :status
            ";
            
            $params = [
                ':loja_id' => $storeId,
                ':status' => TRANSACTION_PENDING
            ];
            
            // Aplicar filtros
            if (!empty($filters)) {
                // Filtro por período
                if (isset($filters['data_inicio']) && !empty($filters['data_inicio'])) {
                    $query .= " AND t.data_transacao >= :data_inicio";
                    $params[':data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
                }
                
                if (isset($filters['data_fim']) && !empty($filters['data_fim'])) {
                    $query .= " AND t.data_transacao <= :data_fim";
                    $params[':data_fim'] = $filters['data_fim'] . ' 23:59:59';
                }
                
                // Filtro por cliente
                if (isset($filters['cliente']) && !empty($filters['cliente'])) {
                    $query .= " AND (u.nome LIKE :cliente OR u.email LIKE :cliente)";
                    $params[':cliente'] = '%' . $filters['cliente'] . '%';
                }
                
                // Filtro por valor mínimo
                if (isset($filters['valor_min']) && !empty($filters['valor_min'])) {
                    $query .= " AND t.valor_total >= :valor_min";
                    $params[':valor_min'] = $filters['valor_min'];
                }
                
                // Filtro por valor máximo
                if (isset($filters['valor_max']) && !empty($filters['valor_max'])) {
                    $query .= " AND t.valor_total <= :valor_max";
                    $params[':valor_max'] = $filters['valor_max'];
                }
            }
            
            // Ordenação
            $query .= " ORDER BY t.data_transacao DESC";
            
            // Contagem total para paginação
            $countQuery = str_replace("t.*, u.nome as cliente_nome, u.email as cliente_email", "COUNT(*) as total", $query);
            $countStmt = $db->prepare($countQuery);
            
            foreach ($params as $param => $value) {
                $countStmt->bindValue($param, $value);
            }
            
            $countStmt->execute();
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Paginação
            $perPage = defined('ITEMS_PER_PAGE') ? ITEMS_PER_PAGE : 10;
            $totalPages = ceil($totalCount / $perPage);
            $page = max(1, min($page, $totalPages));
            $offset = ($page - 1) * $perPage;
            
            $query .= " LIMIT :offset, :limit";
            $params[':offset'] = $offset;
            $params[':limit'] = $perPage;
            
            // Executar consulta
            $stmt = $db->prepare($query);
            
            // Bind manual para offset e limit
            foreach ($params as $param => $value) {
                if ($param == ':offset' || $param == ':limit') {
                    $stmt->bindValue($param, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($param, $value);
                }
            }
            
            $stmt->execute();
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular totais
            $totalValorCompras = 0;
            $totalValorComissoes = 0;
            
            foreach ($transactions as $transaction) {
                $totalValorCompras += $transaction['valor_total'];
                $totalValorComissoes += $transaction['valor_cashback'];
            }
            
            return [
                'status' => true,
                'data' => [
                    'transacoes' => $transactions,
                    'totais' => [
                        'total_transacoes' => count($transactions),
                        'total_valor_compras' => $totalValorCompras,
                        'total_valor_comissoes' => $totalValorComissoes
                    ],
                    'paginacao' => [
                        'total' => $totalCount,
                        'por_pagina' => $perPage,
                        'pagina_atual' => $page,
                        'total_paginas' => $totalPages
                    ]
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao obter transações pendentes: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao obter transações pendentes. Tente novamente.'];
        }
    }
    
    /**
    * Obtém detalhes de um pagamento
    * 
    * @param int $paymentId ID do pagamento
    * @return array Detalhes do pagamento
    */
    public static function getPaymentDetails($paymentId) {
        try {
            // Verificar se o usuário está autenticado
            if (!AuthController::isAuthenticated()) {
                return ['status' => false, 'message' => 'Usuário não autenticado.'];
            }
            
            $db = Database::getConnection();
            
            // Obter dados do pagamento com mais informações
            $paymentStmt = $db->prepare("
                SELECT p.*, l.nome_fantasia as loja_nome, l.email as loja_email,
                    (SELECT COUNT(*) FROM pagamentos_transacoes pt WHERE pt.pagamento_id = p.id) as total_transacoes
                FROM pagamentos_comissao p
                JOIN lojas l ON p.loja_id = l.id
                WHERE p.id = :payment_id
            ");
            $paymentStmt->bindParam(':payment_id', $paymentId);
            $paymentStmt->execute();
            $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payment) {
                return ['status' => false, 'message' => 'Pagamento não encontrado.'];
            }
            
            // Verificar permissões - admin ou a própria loja
            $currentUserId = AuthController::getCurrentUserId();
            if (!AuthController::isAdmin()) {
                if (AuthController::isStore()) {
                    // Verificar se é a loja dona do pagamento
                    $storeCheckStmt = $db->prepare("SELECT usuario_id FROM lojas WHERE id = :loja_id");
                    $storeCheckStmt->bindParam(':loja_id', $payment['loja_id']);
                    $storeCheckStmt->execute();
                    $storeCheck = $storeCheckStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$storeCheck || $storeCheck['usuario_id'] != $currentUserId) {
                        return ['status' => false, 'message' => 'Acesso não autorizado.'];
                    }
                } else {
                    return ['status' => false, 'message' => 'Acesso não autorizado.'];
                }
            }
            
            // Obter transações associadas com mais detalhes
            $transStmt = $db->prepare("
                SELECT t.*, u.nome as cliente_nome, u.email as cliente_email
                FROM pagamentos_transacoes pt
                JOIN transacoes_cashback t ON pt.transacao_id = t.id
                JOIN usuarios u ON t.usuario_id = u.id
                WHERE pt.pagamento_id = :payment_id
                ORDER BY t.data_transacao DESC
            ");
            $transStmt->bindParam(':payment_id', $paymentId);
            $transStmt->execute();
            $transactions = $transStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular totais detalhados
            $totalValorCompras = 0;
            $totalValorComissoes = 0;
            $totalCashbackClientes = 0;
            
            foreach ($transactions as $transaction) {
                $totalValorCompras += $transaction['valor_total'];
                $totalValorComissoes += $transaction['valor_cashback'];
                $totalCashbackClientes += $transaction['valor_cliente'];
            }
            
            return [
                'status' => true,
                'data' => [
                    'pagamento' => $payment,
                    'transacoes' => $transactions,
                    'totais' => [
                        'total_transacoes' => count($transactions),
                        'total_valor_compras' => $totalValorCompras,
                        'total_valor_comissoes' => $totalValorComissoes,
                        'total_cashback_clientes' => $totalCashbackClientes
                    ]
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao obter detalhes do pagamento: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro interno do servidor. Tente novamente.'];
        }
    }
    /**
     * Cria uma notificação para um usuário
     * 
     * @param int $userId ID do usuário
     * @param string $titulo Título da notificação
     * @param string $mensagem Mensagem da notificação
     * @param string $tipo Tipo da notificação (info, success, warning, error)
     * @return bool Verdadeiro se a notificação foi criada
     */
    private static function createNotification($userId, $titulo, $mensagem, $tipo = 'info') {
        try {
            $db = Database::getConnection();
            
            // Verificar se a tabela existe, criar se não existir
            $tableCheckStmt = $db->prepare("SHOW TABLES LIKE 'notificacoes'");
            $tableCheckStmt->execute();
            
            if ($tableCheckStmt->rowCount() == 0) {
                $createTableQuery = "
                    CREATE TABLE notificacoes (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        usuario_id INT NOT NULL,
                        titulo VARCHAR(100) NOT NULL,
                        mensagem TEXT NOT NULL,
                        tipo ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
                        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        lida TINYINT(1) DEFAULT 0,
                        data_leitura TIMESTAMP NULL,
                        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
                    )
                ";
                $db->exec($createTableQuery);
            }
            
            $stmt = $db->prepare("
                INSERT INTO notificacoes (usuario_id, titulo, mensagem, tipo, data_criacao, lida)
                VALUES (:usuario_id, :titulo, :mensagem, :tipo, NOW(), 0)
            ");
            
            $stmt->bindParam(':usuario_id', $userId);
            $stmt->bindParam(':titulo', $titulo);
            $stmt->bindParam(':mensagem', $mensagem);
            $stmt->bindParam(':tipo', $tipo);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log('Erro ao criar notificação: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
    * Obtém histórico de pagamentos de uma loja
    * 
    * @param int $storeId ID da loja
    * @param array $filters Filtros adicionais
    * @param int $page Página atual
    * @return array Histórico de pagamentos
    */
    public static function getPaymentHistory($storeId, $filters = [], $page = 1) {
        try {
            // Verificar se o usuário está autenticado
            if (!AuthController::isAuthenticated()) {
                return ['status' => false, 'message' => 'Usuário não autenticado.'];
            }
            
            $db = Database::getConnection();
            
            // Verificar permissões - apenas a loja dona dos pagamentos ou admin podem acessar
            if (AuthController::isStore()) {
                $currentUserId = AuthController::getCurrentUserId();
                $storeOwnerQuery = $db->prepare("SELECT usuario_id FROM lojas WHERE id = :loja_id");
                $storeOwnerQuery->bindParam(':loja_id', $storeId);
                $storeOwnerQuery->execute();
                $storeOwner = $storeOwnerQuery->fetch(PDO::FETCH_ASSOC);
                
                if (!$storeOwner || $storeOwner['usuario_id'] != $currentUserId) {
                    return ['status' => false, 'message' => 'Acesso não autorizado a esta loja.'];
                }
            } elseif (!AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Acesso não autorizado.'];
            }
            
            // Verificar se a loja existe
            $storeStmt = $db->prepare("SELECT id, nome_fantasia FROM lojas WHERE id = :loja_id");
            $storeStmt->bindParam(':loja_id', $storeId);
            $storeStmt->execute();
            
            if ($storeStmt->rowCount() == 0) {
                return ['status' => false, 'message' => 'Loja não encontrada.'];
            }
            
            // Construir consulta
            $query = "
                SELECT p.*,
                    (SELECT COUNT(*) FROM pagamentos_transacoes WHERE pagamento_id = p.id) as qtd_transacoes
                FROM pagamentos_comissao p
                WHERE p.loja_id = :loja_id
            ";
            
            $params = [
                ':loja_id' => $storeId
            ];
            
            // Aplicar filtros
            if (!empty($filters)) {
                // Filtro por status
                if (isset($filters['status']) && !empty($filters['status'])) {
                    $query .= " AND p.status = :status";
                    $params[':status'] = $filters['status'];
                }
                
                // Filtro por período
                if (isset($filters['data_inicio']) && !empty($filters['data_inicio'])) {
                    $query .= " AND p.data_registro >= :data_inicio";
                    $params[':data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
                }
                
                if (isset($filters['data_fim']) && !empty($filters['data_fim'])) {
                    $query .= " AND p.data_registro <= :data_fim";
                    $params[':data_fim'] = $filters['data_fim'] . ' 23:59:59';
                }
                
                // Filtro por método de pagamento
                if (isset($filters['metodo_pagamento']) && !empty($filters['metodo_pagamento'])) {
                    $query .= " AND p.metodo_pagamento = :metodo_pagamento";
                    $params[':metodo_pagamento'] = $filters['metodo_pagamento'];
                }
            }
            
            // Ordenação
            $query .= " ORDER BY p.data_registro DESC";
            
            // Contagem total para paginação
            $countQuery = str_replace("p.*, (SELECT COUNT(*) FROM pagamentos_transacoes WHERE pagamento_id = p.id) as qtd_transacoes", "COUNT(*) as total", $query);
            $countStmt = $db->prepare($countQuery);
            
            foreach ($params as $param => $value) {
                $countStmt->bindValue($param, $value);
            }
            
            $countStmt->execute();
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Paginação
            $perPage = defined('ITEMS_PER_PAGE') ? ITEMS_PER_PAGE : 10;
            $totalPages = ceil($totalCount / $perPage);
            $page = max(1, min($page, $totalPages));
            $offset = ($page - 1) * $perPage;
            
            $query .= " LIMIT :offset, :limit";
            $params[':offset'] = $offset;
            $params[':limit'] = $perPage;
            
            // Executar consulta
            $stmt = $db->prepare($query);
            
            // Bind manual para offset e limit
            foreach ($params as $param => $value) {
                if ($param == ':offset' || $param == ':limit') {
                    $stmt->bindValue($param, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($param, $value);
                }
            }
            
            $stmt->execute();
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular totais
            $totalValorPagamentos = 0;
            $totalAprovados = 0;
            $totalPendentes = 0;
            $totalRejeitados = 0;
            
            foreach ($payments as $payment) {
                $totalValorPagamentos += $payment['valor_total'];
                
                if ($payment['status'] == 'aprovado') {
                    $totalAprovados++;
                } elseif ($payment['status'] == 'pendente') {
                    $totalPendentes++;
                } elseif ($payment['status'] == 'rejeitado') {
                    $totalRejeitados++;
                }
            }
            
            return [
                'status' => true,
                'data' => [
                    'pagamentos' => $payments,
                    'totais' => [
                        'total_pagamentos' => count($payments),
                        'total_valor' => $totalValorPagamentos,
                        'total_aprovados' => $totalAprovados,
                        'total_pendentes' => $totalPendentes,
                        'total_rejeitados' => $totalRejeitados
                    ],
                    'paginacao' => [
                        'total' => $totalCount,
                        'por_pagina' => $perPage,
                        'pagina_atual' => $page,
                        'total_paginas' => $totalPages
                    ]
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao obter histórico de pagamentos: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao obter histórico de pagamentos. Tente novamente.'];
        }
    }

    /**
     * Método auxiliar para extrair informações de tempo dos resultados de notificação
     */
    private function getTimeInfo($allResults) {
        if (!is_array($allResults)) {
            return '';
        }

        $times = [];
        foreach ($allResults as $method => $result) {
            if (isset($result['response_time_ms'])) {
                $times[] = "{$method}:{$result['response_time_ms']}ms";
            }
        }

        return empty($times) ? '' : ' (' . implode(', ', $times) . ')';
    }
}

// Processar requisições diretas de acesso ao controlador
if (basename($_SERVER['PHP_SELF']) === 'TransactionController.php') {
     // Verificar se é requisição AJAX
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    // Verificar se o usuário está autenticado
    if (!AuthController::isAuthenticated()) {
        if ($isAjax) {
            echo json_encode(['status' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
            exit;
        } else {
            header('Location: ' . LOGIN_URL . '?error=' . urlencode('Você precisa fazer login para acessar esta página.'));
            exit;
        }
    }
    
    $action = $_REQUEST['action'] ?? '';
    
    switch ($action) {
        case 'approve_payment_pix':
            $input = json_decode(file_get_contents('php://input'), true);
            $paymentId = $input['payment_id'] ?? 0;
            
            $result = TransactionController::approvePaymentAutomatically(
                $paymentId, 
                'Pagamento PIX confirmado manualmente'
            );
            echo json_encode($result);
            break;
        case 'transaction_details':
            $transactionId = isset($_POST['transaction_id']) ? intval($_POST['transaction_id']) : 0;
            if ($transactionId <= 0) {
                echo json_encode(['status' => false, 'message' => 'ID da transação inválido']);
                return;
            }
            
            $result = TransactionController::getTransactionDetails($transactionId);
            echo json_encode($result);
            break;

        case 'register':
            $data = $_POST;
            $result = TransactionController::registerTransaction($data);
            echo json_encode($result);
            break;
        // Adicionar este case no switch do TransactionController.php

        case 'payment_details_with_balance':
            $paymentId = intval($_POST['payment_id'] ?? 0);
            if ($paymentId <= 0) {
                echo json_encode(['status' => false, 'message' => 'ID do pagamento inválido']);
                return;
            }
            
            $result = TransactionController::getPaymentDetailsWithBalance($paymentId);
            echo json_encode($result);
            break;

        case 'process_batch':
            $file = $_FILES['arquivo'] ?? null;
            $storeId = isset($_POST['loja_id']) ? intval($_POST['loja_id']) : 0;
            $result = TransactionController::processBatchTransactions($file, $storeId);
            echo json_encode($result);
            break;
            
        case 'pending_transactions':
            $storeId = isset($_POST['loja_id']) ? intval($_POST['loja_id']) : 0;
            $filters = $_POST['filters'] ?? [];
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $result = TransactionController::getPendingTransactions($storeId, $filters, $page);
            echo json_encode($result);
            break;
            
        case 'register_payment':
            // Debug da sessão
            error_log("Session data: " . print_r($_SESSION, true));
            error_log("Auth check: " . (AuthController::isAuthenticated() ? 'true' : 'false'));
            error_log("Store check: " . (AuthController::isStore() ? 'true' : 'false'));
            
            $data = $_POST;
            $result = TransactionController::registerPayment($data);
            echo json_encode($result);
            break;
            
        case 'approve_payment':
            // Apenas admin pode aprovar pagamentos
            if (!AuthController::isAdmin()) {
                echo json_encode(['status' => false, 'message' => 'Acesso restrito a administradores.']);
                exit;
            }
            
            $paymentId = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
            $observacao = $_POST['observacao'] ?? '';
            $result = TransactionController::approvePayment($paymentId, $observacao);
            echo json_encode($result);
            break;
            
        case 'reject_payment':
            // Apenas admin pode rejeitar pagamentos
            if (!AuthController::isAdmin()) {
                echo json_encode(['status' => false, 'message' => 'Acesso restrito a administradores.']);
                exit;
            }
            
            $paymentId = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
            $motivo = $_POST['motivo'] ?? '';
            $result = TransactionController::rejectPayment($paymentId, $motivo);
            echo json_encode($result);
            break;
            
            case 'payment_details':
                $paymentId = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0);
                $result = TransactionController::getPaymentDetails($paymentId);
                echo json_encode($result);
                break;
            
        case 'payment_history':
            $storeId = isset($_POST['loja_id']) ? intval($_POST['loja_id']) : 0;
            $filters = $_POST['filters'] ?? [];
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $result = TransactionController::getPaymentHistory($storeId, $filters, $page);
            echo json_encode($result);
            break;
            
        case 'store_transactions':
            $storeId = isset($_POST['loja_id']) ? intval($_POST['loja_id']) : 0;
            $filters = $_POST['filters'] ?? [];
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            
            // Método simplificado para testar
            try {
                $db = Database::getConnection();
                
                // Query melhorada que inclui informações de loja MVP
                $stmt = $db->prepare("
                    SELECT t.*, u.nome as cliente_nome, u.email as cliente_email, 
                           COALESCE(loja_user.mvp, 'nao') as loja_mvp
                    FROM transacoes_cashback t
                    JOIN usuarios u ON t.usuario_id = u.id
                    JOIN lojas l ON t.loja_id = l.id
                    JOIN usuarios loja_user ON l.usuario_id = loja_user.id
                    WHERE t.loja_id = ?
                    ORDER BY t.data_transacao DESC
                    LIMIT 10
                ");
                $stmt->execute([$storeId]);
                $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // CORREÇÃO: Calcular total de pendentes excluindo MVP aprovadas
                $totalPendentes = 0;
                foreach ($transactions as $transaction) {
                    // Se é uma transação pendente E não é uma loja MVP (que seria aprovada automaticamente)
                    if ($transaction['status'] == 'pendente') {
                        // Para lojas MVP, as transações deveriam estar aprovadas, 
                        // então se estão pendentes, algo deu errado e devem aparecer
                        $totalPendentes++;
                    }
                    // Transações MVP aprovadas não contam como pendentes (comportamento correto)
                }
                
                $totals = [
                    'total_transacoes' => count($transactions),
                    'valor_total_vendas' => array_sum(array_column($transactions, 'valor_total')),
                    'total_comissoes' => array_sum(array_column($transactions, 'valor_cashback')),
                    'total_pendentes' => $totalPendentes
                ];
                
                // Log para debug
                error_log("TRANSACOES DEBUG: Store {$storeId} - Total pendentes calculado: {$totalPendentes}");
                
                echo json_encode([
                    'status' => true,
                    'data' => [
                        'transacoes' => $transactions,
                        'totais' => $totals,
                        'paginacao' => ['total_paginas' => 1, 'pagina_atual' => 1]
                    ]
                ]);
            } catch (Exception $e) {
                error_log("ERRO store_transactions: " . $e->getMessage());
                echo json_encode(['status' => false, 'message' => $e->getMessage()]);
            }
            break;
            
        default:
            // Acesso inválido ao controlador
            if (AuthController::isAdmin()) {
                header('Location: ' . ADMIN_DASHBOARD_URL);
            } elseif (AuthController::isStore()) {
                header('Location: ' . STORE_DASHBOARD_URL);
            } else {
                header('Location: ' . CLIENT_DASHBOARD_URL);
            }
            exit;
    }
}

?>