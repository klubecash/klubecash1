<?php
// controllers/TransactionController.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../config/whatsapp.php';
require_once __DIR__ . '/AuthController.php';
require_once __DIR__ . '/StoreController.php';
require_once __DIR__ . '/../utils/Validator.php';


/**
 * Controlador de Transa��es
 * Gerencia opera��es relacionadas a transa��es, comiss�es e cashback
 */
class TransactionController {
    // Adicionar este m�todo no TransactionController.php
   /**
    * Obt�m todas as transa��es de uma loja com filtros
    * 
    * @param int $storeId ID da loja
    * @param array $filters Filtros para a listagem
    * @param int $page P�gina atual
    * @return array Lista de transa��es
    */
    public static function getStoreTransactions($storeId, $filters = [], $page = 1) {
        try {
            // Verificar se o usu�rio est� autenticado
            if (!AuthController::isAuthenticated()) {
                return ['status' => false, 'message' => 'Usu�rio n�o autenticado.'];
            }
            
            $db = Database::getConnection();
            
            // Verificar permiss�es - apenas a loja dona das transa��es ou admin podem acessar
            if (AuthController::isStore()) {
                $currentUserId = AuthController::getCurrentUserId();
                $storeOwnerQuery = $db->prepare("SELECT usuario_id FROM lojas WHERE id = :loja_id");
                $storeOwnerQuery->bindParam(':loja_id', $storeId);
                $storeOwnerQuery->execute();
                $storeOwner = $storeOwnerQuery->fetch(PDO::FETCH_ASSOC);
                
                if (!$storeOwner || $storeOwner['usuario_id'] != $currentUserId) {
                    return ['status' => false, 'message' => 'Acesso n�o autorizado a esta loja.'];
                }
            } elseif (!AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Acesso n�o autorizado.'];
            }
            
            // Verificar se a loja existe
            $storeStmt = $db->prepare("SELECT id, nome_fantasia FROM lojas WHERE id = :loja_id");
            $storeStmt->bindParam(':loja_id', $storeId);
            $storeStmt->execute();
            $store = $storeStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$store) {
                return ['status' => false, 'message' => 'Loja n�o encontrada.'];
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
                
                // Filtro por per�odo
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
                
                // Filtro por valor m�nimo
                if (isset($filters['valor_min']) && !empty($filters['valor_min'])) {
                    $query .= " AND t.valor_total >= :valor_min";
                    $params[':valor_min'] = $filters['valor_min'];
                }
                
                // Filtro por valor m�ximo
                if (isset($filters['valor_max']) && !empty($filters['valor_max'])) {
                    $query .= " AND t.valor_total <= :valor_max";
                    $params[':valor_max'] = $filters['valor_max'];
                }
            }
            
            // Ordena��o
            $query .= " ORDER BY t.data_transacao DESC";
            
            // Contagem total para pagina��o
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
            
            // Pagina��o
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
            error_log('Erro ao obter transa��es da loja: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao obter transa��es. Tente novamente.'];
        }
    }

    /**
    * Obt�m hist�rico de pagamentos com informa��es de saldo usado
    * 
    * @param int $storeId ID da loja
    * @param array $filters Filtros adicionais
    * @param int $page P�gina atual para pagina��o
    * @return array Resultado da opera��o
    */
    public static function getPaymentHistoryWithBalance($storeId, $filters = [], $page = 1) {
        try {
            if (!AuthController::isAuthenticated()) {
                return ['status' => false, 'message' => 'Usu�rio n�o autenticado.'];
            }
            
            $db = Database::getConnection();
            $limit = ITEMS_PER_PAGE;
            $offset = ($page - 1) * $limit;
            
            // CORRE��O MVP: Verificar se a loja � MVP para incluir transa��es aprovadas automaticamente
            $storeMvpQuery = "SELECT u.mvp FROM lojas l JOIN usuarios u ON l.usuario_id = u.id WHERE l.id = :store_id";
            $storeMvpStmt = $db->prepare($storeMvpQuery);
            $storeMvpStmt->bindParam(':store_id', $storeId);
            $storeMvpStmt->execute();
            $storeMvpResult = $storeMvpStmt->fetch(PDO::FETCH_ASSOC);
            $isStoreMvp = ($storeMvpResult && $storeMvpResult['mvp'] === 'sim');
            
            error_log("PAYMENT HISTORY DEBUG: Loja {$storeId} - MVP: " . ($isStoreMvp ? 'SIM' : 'N�O'));
            
            if ($isStoreMvp) {
                // LOJA MVP: Mostrar transa��es aprovadas como "pagamentos" virtuais sem cobran�a
                error_log("PAYMENT HISTORY DEBUG: Usando query MVP para transa��es aprovadas");
                
                // Para MVP, construir condi��es baseadas nas transa��es aprovadas
                $whereConditions = ["t.loja_id = :loja_id", "t.status = 'aprovado'"];
                $params = [':loja_id' => $storeId];
                
                // Aplicar filtros nas transa��es
                if (!empty($filters['data_inicio'])) {
                    $whereConditions[] = "DATE(t.data_transacao) >= :data_inicio";
                    $params[':data_inicio'] = $filters['data_inicio'];
                }
                
                if (!empty($filters['data_fim'])) {
                    $whereConditions[] = "DATE(t.data_transacao) <= :data_fim";
                    $params[':data_fim'] = $filters['data_fim'];
                }
                
                // Para MVP, n�o h� status de pagamento real, ent�o ignorar esse filtro ou mapear para aprovado
                if (!empty($filters['status']) && $filters['status'] !== 'aprovado') {
                    // Se filtrar por pendente ou rejeitado, n�o mostrar nada para MVP
                    $whereConditions[] = "1 = 0"; // Condi��o imposs�vel
                }
                
                $whereClause = "WHERE " . implode(" AND ", $whereConditions);
                
                // Query para transa��es MVP aprovadas (simular como pagamentos virtuais)
                $paymentsQuery = "
                    SELECT 
                        t.id as id,
                        'mvp_aprovado' as metodo_pagamento,
                        0.00 as valor_total,
                        t.data_transacao as data_registro,
                        t.data_transacao as data_aprovacao,
                        'aprovado' as status,
                        'Transa��o MVP - Aprovada automaticamente (sem cobran�a de comiss�o)' as observacao,
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
                // LOJA NORMAL: Query original com pagamentos de comiss�o reais
                error_log("PAYMENT HISTORY DEBUG: Usando query normal para pagamentos de comiss�o");
                
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
                
                // Query original para pagamentos com informa��es agregadas de saldo
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
                // Para MVP, contar transa��es aprovadas
                $countQuery = "
                    SELECT COUNT(*) as total
                    FROM transacoes_cashback t
                    JOIN usuarios u ON t.usuario_id = u.id
                    $whereClause
                ";
            } else {
                // Para loja normal, contar pagamentos de comiss�o
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
            
            // Calcular pagina��o
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
            error_log('Erro ao buscar hist�rico de pagamentos com saldo: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao buscar hist�rico de pagamentos.'];
        }
    }
    /**
     * Envia notifica��o WhatsApp para nova transa��o
     * Integra com o sistema existente de notifica��es
     */
    private static function sendWhatsAppNotificationNewTransaction($userId, $transactionData) {
        try {
            // Verificar se WhatsApp est� habilitado
            if (!defined('WHATSAPP_ENABLED') || !WHATSAPP_ENABLED) {
                return ['status' => false, 'message' => 'WhatsApp desabilitado'];
            }
            
            // Incluir a classe WhatsApp se ainda n�o estiver carregada
            if (!class_exists('WhatsAppBot')) {
                require_once __DIR__ . '/../utils/WhatsAppBot.php';
            }
            
            // Obter telefone do usu�rio
            $db = Database::getConnection();
            $userStmt = $db->prepare("SELECT telefone FROM usuarios WHERE id = ?");
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || empty($user['telefone'])) {
                error_log("WhatsApp: Usu�rio {$userId} sem telefone cadastrado");
                return ['status' => false, 'message' => 'Usu�rio sem telefone'];
            }
            
            // Enviar notifica��o via WhatsApp
            $result = WhatsAppBot::sendNewTransactionNotification($user['telefone'], $transactionData);
            
            if ($result['success']) {
                error_log("WhatsApp: Notifica��o de nova transa��o enviada para {$user['telefone']}");
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("WhatsApp: Erro ao enviar notifica��o de nova transa��o: " . $e->getMessage());
            return ['status' => false, 'error' => $e->getMessage()];
        }
    }
    /**
     * NOVO M�TODO: createNewPixPayment
     * Gera uma nova transa��o PIX a cada clique, mantendo transa��es pendentes vis�veis
     */
    public static function createNewPixPayment($paymentId, $storeId) {
        try {
            if (!AuthController::isAuthenticated() || !AuthController::isStore()) {
                return ['status' => false, 'message' => 'Acesso n�o autorizado.'];
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
                return ['status' => false, 'message' => 'Pagamento n�o encontrado ou j� processado.'];
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
            
            // Log da nova transa��o
            error_log("? NOVO PIX GERADO - Payment ID: {$paymentId}, MP ID: {$pixData['data']['mp_payment_id']}, Valor: R$ {$existingPayment['valor_total']}");
            
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
     * M�TODO AUXILIAR: generateMercadoPagoPix
     * Integra��o otimizada com Mercado Pago
     */
    private static function generateMercadoPagoPix($amount, $paymentId) {
        try {
            $postData = [
                'transaction_amount' => (float) $amount,
                'description' => "Comiss�o Klube Cash - Pagamento #{$paymentId}",
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
                error_log("Resposta MP inv�lida: " . $response);
                return ['status' => false, 'message' => 'Dados PIX inv�lidos recebidos.'];
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
            error_log("Exce��o ao gerar PIX: " . $e->getMessage());
            return ['status' => false, 'message' => 'Erro interno.'];
        }
    }
    /**
    * Obt�m detalhes completos de uma transa��o espec�fica
    * 
    * @param int $transactionId ID da transa��o
    * @return array Detalhes da transa��o
    */
    public static function getTransactionDetails($transactionId) {
        try {
            if (!AuthController::isAuthenticated()) {
                return ['status' => false, 'message' => 'Usu�rio n�o autenticado.'];
            }
            
            $db = Database::getConnection();
            
            // Buscar detalhes completos da transa��o
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
                return ['status' => false, 'message' => 'Transa��o n�o encontrada.'];
            }
            
            // Verificar permiss�es - apenas admin ou loja propriet�ria
            $currentUserId = AuthController::getCurrentUserId();
            
            if (!AuthController::isAdmin()) {
                if (AuthController::isStore()) {
                    // Verificar se � a loja propriet�ria
                    $storeCheckStmt = $db->prepare("SELECT usuario_id FROM lojas WHERE id = ?");
                    $storeCheckStmt->execute([$transaction['loja_id']]);
                    $storeCheck = $storeCheckStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$storeCheck || $storeCheck['usuario_id'] != $currentUserId) {
                        return ['status' => false, 'message' => 'Acesso n�o autorizado a esta transa��o.'];
                    }
                } else {
                    return ['status' => false, 'message' => 'Acesso n�o autorizado.'];
                }
            }
            
            return [
                'status' => true,
                'data' => $transaction
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao obter detalhes da transa��o: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao obter detalhes da transa��o.'];
        }
    }

    /**
    * Obt�m detalhes de um pagamento espec�fico com informa��es de saldo
    * 
    * @param int $paymentId ID do pagamento
    * @return array Resultado da opera��o
    */
    public static function getPaymentDetailsWithBalance($paymentId) {
        try {
            if (!AuthController::isAuthenticated()) {
                return ['status' => false, 'message' => 'Usu�rio n�o autenticado.'];
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
                return ['status' => false, 'message' => 'Pagamento n�o encontrado.'];
            }
            
            // Verificar permiss�es - apenas admin ou loja propriet�ria
            $currentUserId = AuthController::getCurrentUserId();
            
            if (!AuthController::isAdmin()) {
                if (AuthController::isStore()) {
                    // Verificar se � a loja propriet�ria
                    $storeCheckStmt = $db->prepare("SELECT usuario_id FROM lojas WHERE id = ?");
                    $storeCheckStmt->execute([$payment['loja_id']]);
                    $storeCheck = $storeCheckStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$storeCheck || $storeCheck['usuario_id'] != $currentUserId) {
                        return ['status' => false, 'message' => 'Acesso n�o autorizado a este pagamento.'];
                    }
                } else {
                    return ['status' => false, 'message' => 'Acesso n�o autorizado.'];
                }
            }
            
            // Buscar transa��es do pagamento com informa��es de saldo
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
    * Obt�m transa��es pendentes com informa��es de saldo usado
    * 
    * @param int $storeId ID da loja
    * @param array $filters Filtros adicionais
    * @param int $page P�gina atual para pagina��o
    * @return array Resultado da opera��o
    */
    public static function getPendingTransactionsWithBalance($storeId, $filters = [], $page = 1) {
        try {
            if (!AuthController::isAuthenticated()) {
                return ['status' => false, 'message' => 'Usu�rio n�o autenticado.'];
            }
            
            $db = Database::getConnection();
            $limit = ITEMS_PER_PAGE;
            $offset = ($page - 1) * $limit;
            
            // Construir condi��es WHERE - EXCLUIR TRANSA��ES MVP APROVADAS
            // Para lojas MVP, transa��es pendentes indicam problema no sistema
            // Transa��es de lojas MVP deveriam estar automaticamente aprovadas
            $whereConditions = [
                "t.loja_id = :loja_id", 
                "t.status = :status"
            ];
            $params = [
                ':loja_id' => $storeId,
                ':status' => TRANSACTION_PENDING
            ];
            
            // CORRE��O: Verificar se a loja � MVP e ajustar a consulta
            $storeMvpQuery = "SELECT u.mvp FROM lojas l JOIN usuarios u ON l.usuario_id = u.id WHERE l.id = :store_id";
            $storeMvpStmt = $db->prepare($storeMvpQuery);
            $storeMvpStmt->bindParam(':store_id', $storeId);
            $storeMvpStmt->execute();
            $storeMvpResult = $storeMvpStmt->fetch(PDO::FETCH_ASSOC);
            $isStoreMvp = ($storeMvpResult && $storeMvpResult['mvp'] === 'sim');
            
            // Se for loja MVP, OCULTAR transa��es pendentes pois elas deveriam estar aprovadas
            if ($isStoreMvp) {
                error_log("PENDENTES DEBUG: Loja {$storeId} � MVP - OCULTANDO todas as transa��es pendentes desta tela");
                // Para lojas MVP, for�ar query que n�o retorna nada
                $whereConditions[] = "1 = 0"; // Condi��o que nunca � verdadeira = n�o mostra nada
            } else {
                error_log("PENDENTES DEBUG: Loja {$storeId} n�o � MVP - Mostrando todas as pendentes normalmente");
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
            
            // Query para obter transa��es com informa��es de saldo usado
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
            
            // Query para contar total de transa��es
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
                    -- CORRE��O: Calcular comiss�o total como 10% do valor efetivamente cobrado
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
            
            // Calcular pagina��o
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
            error_log('Erro ao buscar transa��es pendentes com saldo: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao buscar transa��es pendentes.'];
        }
    }
    /**
    * Registra uma nova transa��o de cashback
    * 
    * @param array $data Dados da transa��o
    * @return array Resultado da opera��o
    */
    public static function registerTransaction($data) {
        try {
            // Validar dados obrigat�rios
            $requiredFields = ['loja_id', 'usuario_id', 'valor_total', 'codigo_transacao'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return ['status' => false, 'message' => 'Dados da transa��o incompletos. Campo faltante: ' . $field];
                }
            }
            
            // Verificar se o usu�rio est� autenticado e � loja ou admin
            if (!AuthController::isAuthenticated()) {
                return ['status' => false, 'message' => 'Usu�rio n�o autenticado.'];
            }
            
            if (!AuthController::isStore() && !AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Apenas lojas e administradores podem registrar transa��es.'];
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
                return ['status' => false, 'message' => 'Cliente n�o encontrado ou inativo.'];
            }
            
            // Verificar se a loja existe e est� aprovada
            $isStoreMvp = false; // Default para n�o-MVP
            
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
                // Se falhar (campo MVP n�o existe), usar query b�sica
                error_log("MVP FIELD ERROR: " . $e->getMessage() . " - Usando query b�sica");
                
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
                
                $isStoreMvp = false; // Campo MVP n�o existe, ent�o n�o � MVP
            }
            
            if (!$store) {
                return ['status' => false, 'message' => 'Loja n�o encontrada ou n�o aprovada.'];
            }
            
            // Log de debug
            error_log("MVP CHECK: Loja ID {$data['loja_id']} - MVP: " . ($isStoreMvp ? 'SIM' : 'N�O') . " (store_mvp: " . ($store['store_mvp'] ?? 'NULL') . ")");
            
            // Verificar se o valor da transa��o � v�lido
            if (!is_numeric($data['valor_total']) || $data['valor_total'] <= 0) {
                return ['status' => false, 'message' => 'Valor da transa��o inv�lido.'];
            }
            
            // CORRE��O 1: Verificar se vai usar saldo do cliente (aceita tanto string 'sim' quanto boolean true)
            $usarSaldo = (isset($data['usar_saldo']) && ($data['usar_saldo'] === 'sim' || $data['usar_saldo'] === true));
            $valorSaldoUsado = floatval($data['valor_saldo_usado'] ?? 0);
            $valorOriginal = $data['valor_total']; // Guardar valor original para refer�ncia
            
            // CORRE��O 2: Definir $balanceModel ANTES de usar
            require_once __DIR__ . '/../models/CashbackBalance.php';
            $balanceModel = new CashbackBalance();
            
            // Valida��es de saldo
            if ($usarSaldo && $valorSaldoUsado > 0) {
                // Verificar se o cliente tem saldo suficiente
                $saldoDisponivel = $balanceModel->getStoreBalance($data['usuario_id'], $data['loja_id']);
                
                if ($saldoDisponivel < $valorSaldoUsado) {
                    return [
                        'status' => false, 
                        'message' => 'Saldo insuficiente. Cliente possui R$ ' . number_format($saldoDisponivel, 2, ',', '.') . ' dispon�vel.'
                    ];
                }
                
                // Validar se o valor do saldo usado n�o � maior que o valor total
                if ($valorSaldoUsado > $data['valor_total']) {
                    return [
                        'status' => false, 
                        'message' => 'O valor do saldo usado n�o pode ser maior que o valor total da venda.'
                    ];
                }
            }
            
            // CORRE��O 3: Calcular valor efetivo SEM alterar $data['valor_total']
            $valorEfetivamentePago = $data['valor_total'] - $valorSaldoUsado;
            
            // Verificar valor m�nimo ap�s desconto do saldo
            if ($valorEfetivamentePago < 0) {
                return ['status' => false, 'message' => 'Valor da transa��o ap�s desconto do saldo n�o pode ser negativo.'];
            }
            
            // Se sobrou algum valor ap�s usar saldo, verificar valor m�nimo
            if ($valorEfetivamentePago > 0 && $valorEfetivamentePago < MIN_TRANSACTION_VALUE) {
                return ['status' => false, 'message' => 'Valor m�nimo para transa��o (ap�s desconto do saldo) � R$ ' . number_format(MIN_TRANSACTION_VALUE, 2, ',', '.')];
            }
            
            // Verificar se j� existe uma transa��o com o mesmo c�digo
            $checkStmt = $db->prepare("
                SELECT id FROM transacoes_cashback 
                WHERE codigo_transacao = :codigo_transacao AND loja_id = :loja_id
            ");
            $checkStmt->bindParam(':codigo_transacao', $data['codigo_transacao']);
            $checkStmt->bindParam(':loja_id', $data['loja_id']);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                return ['status' => false, 'message' => 'J� existe uma transa��o com este c�digo.'];
            }
            
            // Obter configura��es de cashback
            $configStmt = $db->prepare("SELECT * FROM configuracoes_cashback ORDER BY id DESC LIMIT 1");
            $configStmt->execute();
            $config = $configStmt->fetch(PDO::FETCH_ASSOC);
            
            // CORRE��O 4: Sempre usar 10% como valor total de cashback (comiss�o da loja)
            $porcentagemTotal = DEFAULT_CASHBACK_TOTAL; // Sempre 10%
            
            // CORRE��O: Garantir que a divis�o � sempre 5% cliente, 5% admin
            $porcentagemCliente = DEFAULT_CASHBACK_CLIENT; // 5%
            $porcentagemAdmin = DEFAULT_CASHBACK_ADMIN; // 5%
            
            // CORRE��O: Remover qualquer personaliza��o de porcentagem por loja
            // Se a configura��o do sistema for diferente do padr�o, aplicar ajuste proporcional
            if (isset($config['porcentagem_cliente']) && isset($config['porcentagem_admin'])) {
                // Verificar se o total configurado � 10%
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
            
            // Iniciar transa��o no banco de dados
            $db->beginTransaction();
            
            try {
                // Definir o status da transa��o
                // ?? MVP: Aprovar automaticamente transa��es de lojas MVP
                if ($isStoreMvp) {
                    $transactionStatus = TRANSACTION_APPROVED;
                    error_log("MVP AUTO-APPROVAL: Transa��o automaticamente aprovada para loja MVP ID {$data['loja_id']}");
                } else {
                    $transactionStatus = isset($data['status']) ? $data['status'] : TRANSACTION_PENDING;
                }
                
                // Preparar descri��o da transa��o
                $descricao = isset($data['descricao']) ? $data['descricao'] : 'Compra na ' . $store['nome_fantasia'];
                if ($usarSaldo && $valorSaldoUsado > 0) {
                    $descricao .= " (Usado R$ " . number_format($valorSaldoUsado, 2, ',', '.') . " do saldo)";
                }
                
                // Registrar transa��o principal (com valor original para hist�rico)
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
                
                // === MARCADOR DE TRACE: TransactionController - Nova transa��o criada ===
                if (file_exists('trace-integration.php')) {
                    error_log("[TRACE] TransactionController::registerTransaction() - Transa��o criada com ID: {$transactionId}", 3, 'integration_trace.log');
                }
                
                // === INTEGRA��O AUTOM�TICA: Sistema de Notifica��o Corrigido ===
                // Disparar notifica��o para transa��es pendentes E aprovadas
                if ($transactionStatus === TRANSACTION_PENDING || $transactionStatus === TRANSACTION_APPROVED) {
                    try {
                        // Log de in�cio da notifica��o
                        error_log("[FIXED] TransactionController::registerTransaction() - Iniciando notifica��o para ID: {$transactionId}, status: {$transactionStatus}");

                        // NOTIFICA��O ULTRA DIRETA VIA WHATSAPP (M�xima Prioridade)
                        $ultraDirectPath = __DIR__ . '/../classes/UltraDirectNotifier.php';
                        $immediateSystemPath = __DIR__ . '/../classes/ImmediateNotificationSystem.php';
                        $fallbackSystemPath = __DIR__ . '/../classes/FixedBrutalNotificationSystem.php';

                        $result = ['success' => false, 'message' => 'Nenhum sistema encontrado'];
                        $systemUsed = 'none';

                        // 1?? PRIORIDADE M�XIMA: UltraDirectNotifier (Direto no bot)
                        if (file_exists($ultraDirectPath)) {
                            require_once $ultraDirectPath;
                            if (class_exists('UltraDirectNotifier')) {
                                error_log("[ULTRA] Usando UltraDirectNotifier para transa��o {$transactionId}");
                                $notifier = new UltraDirectNotifier();

                                // Buscar dados da transa��o para envio (m�todo est�tico)
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

                        // 2?? Fallback: Sistema imediato
                        if (!$result['success'] && file_exists($immediateSystemPath)) {
                            require_once $immediateSystemPath;
                            if (class_exists('ImmediateNotificationSystem')) {
                                error_log("[IMMEDIATE] Usando sistema de notifica��o imediata para transa��o {$transactionId}");
                                $notificationSystem = new ImmediateNotificationSystem();
                                $result = $notificationSystem->sendImmediateNotification($transactionId);
                                $systemUsed = 'ImmediateNotificationSystem (fallback)';
                            }
                        }

                        // Se sistema imediato falhou, usar fallback
                        if (!$result['success'] && file_exists($fallbackSystemPath)) {
                            require_once $fallbackSystemPath;
                            if (class_exists('FixedBrutalNotificationSystem')) {
                                error_log("[FALLBACK] Usando sistema fallback para transa��o {$transactionId}");
                                $notificationSystem = new FixedBrutalNotificationSystem();
                                $result = $notificationSystem->forceNotifyTransaction($transactionId);
                                $systemUsed = 'FixedBrutalNotificationSystem (fallback)';
                            }
                        }

                        // Log detalhado do resultado
                        if ($result['success']) {
                            $method = $result['method_used'] ?? 'unknown';
                            $timeInfo = isset($result['all_results']) ? $this->getTimeInfo($result['all_results']) : '';
                            error_log("[SUCCESS] TransactionController - Notifica��o enviada via {$systemUsed} usando m�todo {$method} para transa��o {$transactionId}{$timeInfo}");
                        } else {
                            error_log("[FAIL] TransactionController - Falha na notifica��o para transa��o {$transactionId} via {$systemUsed}: " . $result['message']);
                        }

                    } catch (Exception $e) {
                        // Log de erro mas n�o quebrar o fluxo principal
                        error_log("[FIXED] TransactionController - Erro na notifica��o para transa��o {$transactionId}: " . $e->getMessage());
                    }
                }
                
                // MVP ser� processado AP�S o commit para evitar transa��es aninhadas
                
                // CORRE��O 5: Se usou saldo, debitar do saldo do cliente IMEDIATAMENTE
                if ($usarSaldo && $valorSaldoUsado > 0) {
                    $descricaoUso = "Uso do saldo na compra - C�digo: " . $data['codigo_transacao'] . " - Transa��o #" . $transactionId;
                    
                    error_log("REGISTRO: Tentando debitar saldo - Usuario: {$data['usuario_id']}, Loja: {$data['loja_id']}, Valor: {$valorSaldoUsado}");
                    
                    $debitResult = $balanceModel->useBalance($data['usuario_id'], $data['loja_id'], $valorSaldoUsado, $descricaoUso, $transactionId);
                    
                    if (!$debitResult) {
                        // Se falhou ao debitar saldo, reverter transa��o
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        error_log("REGISTRO: FALHA ao debitar saldo - revertendo transa��o");
                        return ['status' => false, 'message' => 'Erro ao debitar saldo do cliente. Transa��o cancelada.'];
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
                
                // Registrar comiss�o para o administrador (sobre valor efetivamente pago)
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
                    $adminId = 1; // Administrador padr�o
                    
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
                    $successMessage = '?? Transa��o MVP aprovada instantaneamente! Cashback creditado automaticamente.';
                } else {
                    $successMessage = 'Transa��o registrada com sucesso!';
                }
                
                if ($usarSaldo && $valorSaldoUsado > 0) {
                    $successMessage .= ' Saldo de R$ ' . number_format($valorSaldoUsado, 2, ',', '.') . ' foi usado na compra.';
                }
                
                // Criar notifica��o para o cliente (apenas se n�o for MVP, pois MVP j� criou notifica��o especial)
                if (!$isStoreMvp) {
                    $notificationMessage = 'Voc� tem um novo cashback de R$ ' . number_format($valorCashbackCliente, 2, ',', '.') . ' pendente da loja ' . $store['nome_fantasia'];
                    if ($usarSaldo && $valorSaldoUsado > 0) {
                        $notificationMessage .= '. Voc� usou R$ ' . number_format($valorSaldoUsado, 2, ',', '.') . ' do seu saldo nesta compra.';
                    }
                    
                    // self::createNotification(
                    //     $data['usuario_id'],
                    //     'Nova transa��o registrada',
                    //     $notificationMessage,
                    //     'info'
                    // );
                }
                // INTEGRA��O WHATSAPP: Com tratamento de erro aprimorado
                if (defined('WHATSAPP_ENABLED') && WHATSAPP_ENABLED && $valorCashbackCliente > 0) {
                    try {
                        // Carregar as classes necess�rias para WhatsApp
                        if (!class_exists('WhatsAppBot')) {
                            require_once __DIR__ . '/../utils/WhatsAppBot.php';
                        }
                        
                        // Buscar o telefone do cliente que fez a compra
                        $userStmt = $db->prepare("SELECT telefone, nome FROM usuarios WHERE id = ?");
                        $userStmt->execute([$data['usuario_id']]);
                        $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Verificar se o cliente tem WhatsApp cadastrado
                        if ($userData && !empty($userData['telefone'])) {
                            // Preparar as informa��es da transa��o para a mensagem WhatsApp
                            $whatsappData = [
                                'valor_cashback' => $valorCashbackCliente, // Valor do cashback desta transa��o
                                'valor_usado' => $valorSaldoUsado ?? 0, // Valor usado do saldo (se aplic�vel)
                                'nome_loja' => $store['nome_fantasia'] // Nome da loja onde a compra foi realizada
                            ];
                            
                            // Enviar a notifica��o via WhatsApp usando nosso template espec�fico
                            $whatsappResult = WhatsAppBot::sendNewTransactionNotification(
                                $userData['telefone'], 
                                $whatsappData
                            );
                            
                            // O resultado ser� automaticamente registrado em nosso sistema de logs
                            // Voc� poder� acompanhar o sucesso ou falha na interface de monitoramento
                        }
                    } catch (Throwable $e) {
                        // Capturar TODOS os tipos de erro (Exception, Error, etc.) sem interromper a transa��o
                        // Isso garante que o sistema principal continue funcionando mesmo se houver problema cr�tico com WhatsApp
                        error_log("WhatsApp Nova Transa��o - Erro cr�tico: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine());
                        // N�o relan�ar a exce��o para n�o afetar o fluxo principal
                    }
                }
                // Enviar email para o cliente (opcional, pode remover se n�o quiser)
                if (!empty($user['email'])) {
                    $subject = 'Novo Cashback Pendente - Klube Cash';
                    $emailMessage = "
                        <h3>Ol�, {$user['nome']}!</h3>
                        <p>Uma nova transa��o foi registrada em sua conta no Klube Cash.</p>
                        <p><strong>Loja:</strong> {$store['nome_fantasia']}</p>
                        <p><strong>Valor total da compra:</strong> R$ " . number_format($valorOriginal, 2, ',', '.') . "</p>";
                    
                    if ($usarSaldo && $valorSaldoUsado > 0) {
                        $emailMessage .= "<p><strong>Saldo usado:</strong> R$ " . number_format($valorSaldoUsado, 2, ',', '.') . "</p>";
                        $emailMessage .= "<p><strong>Valor pago:</strong> R$ " . number_format($valorEfetivamentePago, 2, ',', '.') . "</p>";
                    }
                    
                    $emailMessage .= "
                        <p><strong>Cashback (pendente):</strong> R$ " . number_format($valorCashbackCliente, 2, ',', '.') . "</p>
                        <p><strong>Data:</strong> " . date('d/m/Y H:i', strtotime($dataTransacao)) . "</p>
                        <p>O cashback ser� disponibilizado assim que a loja confirmar o pagamento da comiss�o.</p>
                        <p>Atenciosamente,<br>Equipe Klube Cash</p>
                    ";
                    
                    // Email::send($user['email'], $subject, $emailMessage, $user['nome']); // Descomente se quiser enviar email
                }
                
                // Confirmar transa��o
                $db->commit();
                
                // ?? MVP: Processar cashback instantaneamente AP�S commit para evitar transa��es aninhadas
                if ($isStoreMvp && $valorCashbackCliente > 0) {
                    error_log("MVP CASHBACK: Processando cashback instant�neo para loja MVP - Valor: R$ {$valorCashbackCliente}");
                    
                    // Creditar cashback imediatamente
                    $descricaoCashback = "Cashback MVP - Compra aprovada instantaneamente - C�digo: " . $data['codigo_transacao'];
                    $creditResult = $balanceModel->addBalance(
                        $data['usuario_id'], 
                        $data['loja_id'], 
                        $valorCashbackCliente, 
                        $descricaoCashback, 
                        $transactionId
                    );
                    
                    if ($creditResult) {
                        error_log("MVP CASHBACK: Cashback creditado com sucesso - R$ {$valorCashbackCliente} para usu�rio {$data['usuario_id']}");
                        
                        // Criar notifica��o especial para MVP (temporariamente comentado)
                        // self::createNotification(
                        //     $data['usuario_id'],
                        //     'Cashback MVP Creditado! ??',
                        //     "Seu cashback de R$ " . number_format($valorCashbackCliente, 2, ',', '.') . " foi creditado instantaneamente! Loja MVP: " . $store['nome_fantasia'],
                        //     'success'
                        // );
                    } else {
                        error_log("MVP CASHBACK: ERRO ao creditar cashback para usu�rio {$data['usuario_id']}");
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
                // Reverter transa��o em caso de erro
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }
            
        } catch (Exception $e) {
            // Reverter transa��o em caso de erro
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            
            error_log('ERRO CAPTURADO na registerTransaction: ' . $e->getMessage());
            error_log('Arquivo: ' . $e->getFile() . ' - Linha: ' . $e->getLine());
            error_log('Stack trace completo: ' . $e->getTraceAsString());
            
            // Se for o erro espec�fico de transa��o, vamos dar mais detalhes
            if (strpos($e->getMessage(), 'There is no active transaction') !== false) {
                error_log('PROBLEMA DE TRANSA��O DETECTADO - Investigando origem...');
                error_log('Estado atual do DB: inTransaction = ' . ($db->inTransaction() ? 'SIM' : 'N�O'));
            }
            
            return ['status' => false, 'message' => 'Erro: ' . $e->getMessage()];
        }
    }
    
    /**
     * Vers�o limpa e funcional do registro de transa��es com funcionalidade MVP
     */
    public static function registerTransactionFixed($data) {
        try {
            // Validar dados obrigat�rios
            $requiredFields = ['loja_id', 'usuario_id', 'valor_total', 'codigo_transacao'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return ['status' => false, 'message' => 'Dados da transa��o incompletos. Campo faltante: ' . $field];
                }
            }

            // Verificar autentica��o
            if (!AuthController::isAuthenticated()) {
                return ['status' => false, 'message' => 'Usu�rio n�o autenticado.'];
            }

            if (!AuthController::isStore() && !AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Apenas lojas e administradores podem registrar transa��es.'];
            }

            $db = Database::getConnection();

            // Verificar cliente
            $userStmt = $db->prepare("SELECT id, nome, email, telefone FROM usuarios WHERE id = ? AND tipo = ? AND status = ?");
            $userStmt->execute([$data['usuario_id'], USER_TYPE_CLIENT, USER_ACTIVE]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return ['status' => false, 'message' => 'Cliente n�o encontrado ou inativo.'];
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
                return ['status' => false, 'message' => 'Loja n�o encontrada ou n�o aprovada.'];
            }

            // Obter configura��es de cashback da loja
            $storeConfigQuery = $db->prepare("
                SELECT l.*, u.mvp, u.senat,
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
                return ['status' => false, 'message' => 'Loja n�o encontrada.'];
            }

            if ($storeConfig['cashback_ativo'] != 1) {
                return ['status' => false, 'message' => 'Esta loja n�o oferece cashback no momento.'];
            }

            $isStoreMvp = ($storeConfig['mvp'] === 'sim');

            // NOVA FUNCIONALIDADE: Se o lojista tem senat='Sim', o cliente tamb�m deve ter
            // IMPORTANTE: A coluna senat � enum('Sim','N�o') com S mai�sculo
            if (isset($storeConfig['senat']) && $storeConfig['senat'] === 'Sim') {
                try {
                    // Verificar se o cliente j� tem senat='Sim'
                    $checkClientSenatStmt = $db->prepare("SELECT senat FROM usuarios WHERE id = ?");
                    $checkClientSenatStmt->execute([$data['usuario_id']]);
                    $clientSenat = $checkClientSenatStmt->fetch(PDO::FETCH_ASSOC);

                    // Se o cliente n�o tem senat='Sim', atualizar para 'Sim'
                    if ($clientSenat && $clientSenat['senat'] !== 'Sim') {
                        $updateClientSenatStmt = $db->prepare("UPDATE usuarios SET senat = 'Sim' WHERE id = ?");
                        $updateClientSenatStmt->execute([$data['usuario_id']]);
                        error_log("SENAT UPDATE: Cliente ID {$data['usuario_id']} atualizado para senat='Sim' pois lojista ID {$data['loja_id']} � senat='Sim'");
                    } else {
                        error_log("SENAT UPDATE: Cliente ID {$data['usuario_id']} j� possui senat='Sim', nenhuma atualiza��o necess�ria");
                    }
                } catch (Exception $e) {
                    error_log("SENAT UPDATE ERROR: Erro ao atualizar senat do cliente: " . $e->getMessage());
                    // N�o retornar erro, apenas logar - a transa��o deve continuar
                }
            }

            // Validar valor da transa��o
            $valorOriginal = (float) $data['valor_total'];
            if (!is_numeric($valorOriginal) || $valorOriginal <= 0) {
                return ['status' => false, 'message' => 'Valor da transa��o inv�lido.'];
            }

            if ($valorOriginal < MIN_TRANSACTION_VALUE) {
                return ['status' => false, 'message' => 'Valor m�nimo para transa��o � R$ ' . number_format(MIN_TRANSACTION_VALUE, 2, ',', '.')];
            }

            // Verificar c�digo duplicado
            $checkStmt = $db->prepare("SELECT id FROM transacoes_cashback WHERE codigo_transacao = ? AND loja_id = ?");
            $checkStmt->execute([$data['codigo_transacao'], $data['loja_id']]);

            if ($checkStmt->rowCount() > 0) {
                return ['status' => false, 'message' => 'J� existe uma transa��o com este c�digo.'];
            }

            // Preparar uso de saldo
            $balanceModel = null;
            $usarSaldo = false;
            if (isset($data['usar_saldo'])) {
                $usarSaldoValor = $data['usar_saldo'];
                $usarSaldo = ($usarSaldoValor === 'sim' || $usarSaldoValor === true || $usarSaldoValor === 1 || $usarSaldoValor === '1');
            }

            $valorSaldoUsado = 0.00;
            if ($usarSaldo) {
                $valorSaldoUsado = round((float) ($data['valor_saldo_usado'] ?? 0), 2);
                if ($valorSaldoUsado <= 0) {
                    $usarSaldo = false;
                    $valorSaldoUsado = 0.00;
                }
            }

            $valorEfetivamentePago = $valorOriginal;
            if ($usarSaldo) {
                if ($valorSaldoUsado > $valorOriginal) {
                    return ['status' => false, 'message' => 'O valor do saldo usado n�o pode ser maior que o valor total da venda.'];
                }

                if (!class_exists('CashbackBalance')) {
                    require_once __DIR__ . '/../models/CashbackBalance.php';
                }

                $balanceModel = new CashbackBalance();
                $saldoDisponivel = $balanceModel->getStoreBalance($data['usuario_id'], $data['loja_id']);

                if ($saldoDisponivel + 0.0001 < $valorSaldoUsado) {
                    return [
                        'status' => false,
                        'message' => 'Saldo insuficiente. Cliente possui R$ ' . number_format($saldoDisponivel, 2, ',', '.') . ' dispon�vel.'
                    ];
                }

                $valorEfetivamentePago = max(0, round($valorOriginal - $valorSaldoUsado, 2));

                if ($valorEfetivamentePago > 0 && $valorEfetivamentePago < MIN_TRANSACTION_VALUE) {
                    return ['status' => false, 'message' => 'Valor m�nimo para transa��o (ap�s desconto do saldo) � R$ ' . number_format(MIN_TRANSACTION_VALUE, 2, ',', '.')];
                }
            }

            // Calcular cashback com base no valor efetivamente pago
            $porcentagemCliente = (float) $storeConfig['porcentagem_cliente'];
            $porcentagemAdmin = (float) $storeConfig['porcentagem_admin'];
            $porcentagemTotal = $porcentagemCliente + $porcentagemAdmin;

            $valorCashbackCliente = round(($valorEfetivamentePago * $porcentagemCliente) / 100, 2);
            $valorCashbackAdmin = round(($valorEfetivamentePago * $porcentagemAdmin) / 100, 2);
            $valorCashbackTotal = $valorCashbackCliente + $valorCashbackAdmin;
            $valorLoja = 0.00;

            error_log('CASHBACK CONFIG: Loja ' . $data['loja_id'] . ' - Cliente: ' . $porcentagemCliente . '%, Admin: ' . $porcentagemAdmin . '%, MVP: ' . ($isStoreMvp ? 'SIM' : 'N�O') . ', Base c�lculo: R$ ' . number_format($valorEfetivamentePago, 2, ',', '.'));

            // Definir status da transa��o
            $transactionStatus = $isStoreMvp ? TRANSACTION_APPROVED : (isset($data['status']) ? $data['status'] : TRANSACTION_PENDING);

            // Preparar descri��o e data
            $descricao = isset($data['descricao']) ? $data['descricao'] : 'Compra na ' . $store['nome_fantasia'];
            if ($usarSaldo && $valorSaldoUsado > 0) {
                $descricao .= ' (Usado R$ ' . number_format($valorSaldoUsado, 2, ',', '.') . ' do saldo)';
            }
            $dataTransacao = isset($data['data_transacao']) ? $data['data_transacao'] : date('Y-m-d H:i:s');

            // Inserir transa��o
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
                $valorOriginal,
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
                return ['status' => false, 'message' => 'Falha ao inserir transa��o no banco.'];
            }

            $transactionId = $db->lastInsertId();

            // Se usou saldo, debitar imediatamente
            if ($usarSaldo && $valorSaldoUsado > 0) {
                if ($balanceModel === null) {
                    if (!class_exists('CashbackBalance')) {
                        require_once __DIR__ . '/../models/CashbackBalance.php';
                    }
                    $balanceModel = new CashbackBalance();
                }

                $descricaoUso = 'Uso do saldo na compra - C�digo: ' . $data['codigo_transacao'] . ' - Transa��o #' . $transactionId;
                if (!$balanceModel->useBalance($data['usuario_id'], $data['loja_id'], $valorSaldoUsado, $descricaoUso, $transactionId)) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    return ['status' => false, 'message' => 'Erro ao debitar saldo do cliente. Transa��o cancelada.'];
                }

                $useSaldoStmt = $db->prepare("
                    INSERT INTO transacoes_saldo_usado (transacao_id, usuario_id, loja_id, valor_usado)
                    VALUES (:transacao_id, :usuario_id, :loja_id, :valor_usado)
                ");
                $useSaldoStmt->bindParam(':transacao_id', $transactionId);
                $useSaldoStmt->bindParam(':usuario_id', $data['usuario_id']);
                $useSaldoStmt->bindParam(':loja_id', $data['loja_id']);
                $useSaldoStmt->bindParam(':valor_usado', $valorSaldoUsado);
                $useSaldoStmt->execute();

                try {
                    $updateSaldoColStmt = $db->prepare('UPDATE transacoes_cashback SET saldo_usado = :valor WHERE id = :id');
                    $updateSaldoColStmt->bindParam(':valor', $valorSaldoUsado);
                    $updateSaldoColStmt->bindParam(':id', $transactionId, PDO::PARAM_INT);
                    $updateSaldoColStmt->execute();
                } catch (PDOException $saldoColumnException) {
                    error_log('registerTransactionFixed: coluna saldo_usado indispon�vel - ' . $saldoColumnException->getMessage());
                }
            }

            // Integra��o UltraDirectNotifier (prioridade m�xima)
            try {
                error_log('[ULTRA] TransactionController::registerTransactionFixed() - Disparando notifica��o ULTRA para transa��o ' . $transactionId);

                $ultraPath = __DIR__ . '/../classes/UltraDirectNotifier.php';
                if (file_exists($ultraPath)) {
                    require_once $ultraPath;
                    if (class_exists('UltraDirectNotifier')) {
                        $notifier = new UltraDirectNotifier();

                        $transactionData = [
                            'transaction_id' => $transactionId,
                            'cliente_telefone' => 'brutal_system',
                            'additional_data' => json_encode([
                                'transaction_id' => $transactionId,
                                'system' => 'registerTransactionFixed',
                                'timestamp' => date('Y-m-d H:i:s')
                            ])
                        ];

                        $result = $notifier->notifyTransaction($transactionData);
                        error_log('[ULTRA] registerTransactionFixed - Resultado: ' . ($result['success'] ? 'SUCESSO' : 'FALHA') . ' em ' . ($result['time_ms'] ?? 0) . 'ms');
                    } else {
                        error_log('[ULTRA] TransactionController::registerTransactionFixed() - Classe UltraDirectNotifier n�o encontrada');
                        $result = ['success' => false, 'message' => 'Classe UltraDirectNotifier n�o encontrada'];
                    }
                } else {
                    error_log('[ULTRA] TransactionController::registerTransactionFixed() - Arquivo n�o encontrado: ' . $ultraPath);
                    $result = ['success' => false, 'message' => 'UltraDirectNotifier n�o encontrado'];
                }

                if ($result['success']) {
                    error_log('[ULTRA] TransactionController::registerTransactionFixed() - Notifica��o ULTRA enviada com sucesso!');
                } else {
                    error_log('[ULTRA] TransactionController::registerTransactionFixed() - Falha na notifica��o ULTRA: ' . ($result['error'] ?? $result['message']));
                }

            } catch (Exception $e) {
                error_log('[ULTRA] TransactionController::registerTransactionFixed() - Erro na notifica��o ULTRA: ' . $e->getMessage());
            }

            $db->commit();

            $successMessage = $isStoreMvp ?
                '?? Transa��o MVP aprovada instantaneamente! Cashback creditado automaticamente.' :
                'Transa��o registrada com sucesso!';

            if ($usarSaldo && $valorSaldoUsado > 0) {
                $successMessage .= ' Saldo de R$ ' . number_format($valorSaldoUsado, 2, ',', '.') . ' foi usado na compra.';
            }

            $cashbackCreditado = false;
            if ($isStoreMvp && $valorCashbackCliente > 0) {
                if ($balanceModel === null) {
                    if (!class_exists('CashbackBalance')) {
                        require_once __DIR__ . '/../models/CashbackBalance.php';
                    }
                    $balanceModel = new CashbackBalance();
                }

                $descricaoCashback = 'Cashback MVP instantaneo - Codigo: ' . $data['codigo_transacao'];
                $creditResult = $balanceModel->addBalance(
                    $data['usuario_id'],
                    $data['loja_id'],
                    $valorCashbackCliente,
                    $descricaoCashback,
                    $transactionId
                );

                $cashbackCreditado = $creditResult;
            }

            $whatsappConfirmation = null;
            $whatsappAck = null;
            if ($isStoreMvp && defined('WHATSAPP_ENABLED') && WHATSAPP_ENABLED && $valorCashbackCliente > 0) {
                try {
                    if (!class_exists('WhatsAppBot')) {
                        require_once __DIR__ . '/../utils/WhatsAppBot.php';
                    }

                    if (!empty($user['telefone'])) {
                        $whatsAppData = [
                            'nome_loja' => $store['nome_fantasia'] ?? 'Loja parceira',
                            'valor_total' => $valorOriginal,
                            'valor_cashback' => $valorCashbackCliente,
                            'valor_usado' => $valorSaldoUsado,
                            'valor_pago' => $valorEfetivamentePago,
                            'codigo_transacao' => $data['codigo_transacao'],
                        ];

                        $whatsAppOptions = [
                            'custom_footer' => 'Transacao confirmada! Seu cashback ja esta disponivel.',
                            'tag' => 'transaction:mvp_confirmation',
                        ];

                        $whatsAppResult = WhatsAppBot::sendNewTransactionNotification(
                            $user['telefone'],
                            $whatsAppData,
                            $whatsAppOptions
                        );

                        $whatsappConfirmation = $whatsAppResult['success'];
                        $whatsappAck = $whatsAppResult['ack'] ?? null;

                        if (!$whatsAppResult['success']) {
                            error_log('WhatsApp MVP Confirmation - Falha: ' . ($whatsAppResult['message'] ?? 'sem mensagem'));
                        }
                    } else {
                        error_log('WhatsApp MVP Confirmation - Cliente sem telefone cadastrado: ' . $data['usuario_id']);
                    }
                } catch (Throwable $whatsException) {
                    error_log('WhatsApp MVP Confirmation - Erro: ' . $whatsException->getMessage());
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
                    'cashback_creditado' => $cashbackCreditado,
                    'whatsapp_confirmation' => $whatsappConfirmation,
                    'whatsapp_ack' => $whatsappAck
                ]
            ];

        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }

            error_log('Erro em registerTransactionFixed: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao registrar transa��o. Tente novamente.'];
        }
    }

    


    /**
     * Processa transa��es em lote a partir de um arquivo CSV
     * 
     * @param array $file Arquivo enviado ($_FILES['arquivo'])
     * @param int $storeId ID da loja
     * @return array Resultado da opera��o
     */
    public static function processBatchTransactions($file, $storeId) {
        try {
            // Verificar se o usu�rio est� autenticado e � loja ou admin
            if (!AuthController::isAuthenticated()) {
                return ['status' => false, 'message' => 'Usu�rio n�o autenticado.'];
            }
            
            if (!AuthController::isStore() && !AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Apenas lojas e administradores podem registrar transa��es em lote.'];
            }
            
            // Validar o arquivo
            if (!isset($file) || !is_array($file) || $file['error'] !== UPLOAD_ERR_OK) {
                return ['status' => false, 'message' => 'Erro no upload do arquivo.'];
            }
            
            // Verificar extens�o
            $fileInfo = pathinfo($file['name']);
            $extension = strtolower($fileInfo['extension']);
            
            if ($extension !== 'csv') {
                return ['status' => false, 'message' => 'Apenas arquivos CSV s�o permitidos.'];
            }
            
            // Verificar se a loja existe
            $db = Database::getConnection();
            $storeStmt = $db->prepare("SELECT id, nome_fantasia FROM lojas WHERE id = :loja_id AND status = :status");
            $storeStmt->bindParam(':loja_id', $storeId);
            $statusAprovado = STORE_APPROVED;
            $storeStmt->bindParam(':status', $statusAprovado);
            $storeStmt->execute();
            
            if ($storeStmt->rowCount() == 0) {
                return ['status' => false, 'message' => 'Loja n�o encontrada ou n�o aprovada.'];
            }
            
            // Ler o arquivo CSV
            $filePath = $file['tmp_name'];
            $handle = fopen($filePath, 'r');
            
            if (!$handle) {
                return ['status' => false, 'message' => 'N�o foi poss�vel abrir o arquivo.'];
            }
            
            // Ler cabe�alho
            $header = fgetcsv($handle, 1000, ',');
            
            if (!$header || count($header) < 3) {
                fclose($handle);
                return ['status' => false, 'message' => 'Formato de arquivo inv�lido. Verifique o modelo.'];
            }
            
            // Verificar colunas necess�rias
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
                    return ['status' => false, 'message' => 'Coluna obrigat�ria n�o encontrada: ' . $required];
                }
            }
            
            // Iniciar processamento
            $totalProcessed = 0;
            $successCount = 0;
            $errorCount = 0;
            $errors = [];
            
            // Iniciar transa��o de banco de dados
            $db->beginTransaction();
            
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                $totalProcessed++;
                
                // Extrair dados
                $email = trim($row[$headerMap['email']]);
                $valor = str_replace(['R$', '.', ','], ['', '', '.'], trim($row[$headerMap['valor']]));
                $codigoTransacao = trim($row[$headerMap['codigo_transacao']]);
                
                // Obter descri��o se existir
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
                
                // Valida��es b�sicas
                if (empty($email) || empty($valor) || empty($codigoTransacao)) {
                    $errorCount++;
                    $errors[] = "Linha {$totalProcessed}: Dados incompletos";
                    continue;
                }
                
                if (!is_numeric($valor) || $valor <= 0) {
                    $errorCount++;
                    $errors[] = "Linha {$totalProcessed}: Valor inv�lido";
                    continue;
                }
                
                // Buscar ID do usu�rio pelo email
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
                    $errors[] = "Linha {$totalProcessed}: Cliente com email {$email} n�o encontrado ou inativo";
                    continue;
                }
                
                // Verificar se j� existe transa��o com este c�digo
                $checkStmt = $db->prepare("
                    SELECT id FROM transacoes_cashback 
                    WHERE codigo_transacao = :codigo_transacao AND loja_id = :loja_id
                ");
                $checkStmt->bindParam(':codigo_transacao', $codigoTransacao);
                $checkStmt->bindParam(':loja_id', $storeId);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() > 0) {
                    $errorCount++;
                    $errors[] = "Linha {$totalProcessed}: Transa��o com c�digo {$codigoTransacao} j� existe";
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
                
                // Registrar transa��o
                $result = self::registerTransaction($transactionData);
                
                if ($result['status']) {
                    $successCount++;
                } else {
                    $errorCount++;
                    $errors[] = "Linha {$totalProcessed}: " . $result['message'];
                }
            }
            
            fclose($handle);
            
            // Finalizar transa��o
            if ($errorCount == 0) {
                $db->commit();
                return [
                    'status' => true,
                    'message' => "Processamento conclu�do com sucesso. {$successCount} transa��es registradas.",
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
                    'message' => "Processamento conclu�do com erros. Nenhuma transa��o foi registrada.",
                    'data' => [
                        'total_processado' => $totalProcessed,
                        'sucesso' => 0,
                        'erros' => $errorCount,
                        'detalhes_erros' => $errors
                    ]
                ];
            }
            
        } catch (Exception $e) {
            // Reverter transa��o em caso de erro
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            
            error_log('Erro ao processar transa��es em lote: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao processar transa��es em lote. Tente novamente.'];
        }
    }
    
    /**
    * Registra pagamento de comiss�es (VERS�O CORRIGIDA)
    * 
    * @param array $data Dados do pagamento
    * @return array Resultado da opera��o
    */
    public static function registerPayment($data) {
        try {
            error_log("registerPayment - Dados recebidos: " . print_r($data, true));
            
            // Valida��o b�sica
            if (!isset($data['loja_id']) || !isset($data['transacoes']) || !isset($data['valor_total'])) {
                return ['status' => false, 'message' => 'Dados obrigat�rios faltando'];
            }
            
            // Verificar se o usu�rio est� autenticado e � loja ou admin
            if (!AuthController::isAuthenticated()) {
                return ['status' => false, 'message' => 'Usu�rio n�o autenticado.'];
            }
            
            if (!AuthController::isStore() && !AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Apenas lojas e administradores podem registrar pagamentos.'];
            }
            
            $db = Database::getConnection();
            
            // Converter transa��es para array se necess�rio
            $transactionIds = is_array($data['transacoes']) ? $data['transacoes'] : explode(',', $data['transacoes']);
            $transactionIds = array_map('intval', $transactionIds);
            
            if (empty($transactionIds)) {
                return ['status' => false, 'message' => 'Nenhuma transa��o selecionada'];
            }
            
            error_log("registerPayment - IDs: " . implode(',', $transactionIds));
            
            // CORRE��O: Validar se todas as transa��es existem e calcular valor total correto
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
            
            // Verificar se todas as transa��es foram encontradas
            if (count($transactions) !== count($transactionIds)) {
                return [
                    'status' => false, 
                    'message' => 'Algumas transa��es n�o foram encontradas ou n�o est�o pendentes. Esperado: ' . count($transactionIds) . ', Encontrado: ' . count($transactions)
                ];
            }
            
            // CORRE��O: Calcular valor total correto (soma das comiss�es totais)
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
                    ') n�o confere com o valor das transa��es selecionadas (R$ ' . number_format($totalCalculated, 2, ',', '.') . ')'
                ];
            }
            
            // Validar valores num�ricos
            if ($valorInformado <= 0) {
                return ['status' => false, 'message' => 'Valor total deve ser maior que zero'];
            }
            
            // Iniciar transa��o no banco de dados
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
                    $totalCalculated, // Usar valor calculado para garantir precis�o
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
                
                // 2. Associar transa��es ao pagamento
                $assocStmt = $db->prepare("INSERT INTO pagamentos_transacoes (pagamento_id, transacao_id) VALUES (?, ?)");
                
                foreach ($transactionIds as $transId) {
                    $assocResult = $assocStmt->execute([$paymentId, $transId]);
                    if (!$assocResult) {
                        throw new Exception("Erro ao associar transa��o $transId");
                    }
                    error_log("registerPayment - Transa��o $transId associada");
                }
                
                // 3. Atualizar status das transa��es
                $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
                $updateStmt = $db->prepare("UPDATE transacoes_cashback SET status = 'pagamento_pendente' WHERE id IN ($placeholders)");
                
                $updateResult = $updateStmt->execute($transactionIds);
                if (!$updateResult) {
                    throw new Exception('Erro ao atualizar status das transa��es');
                }
                
                // 4. Criar notifica��o para admin
                self::createNotification(
                    1, // Admin padr�o
                    'Novo pagamento registrado',
                    'Nova solicita��o de pagamento de comiss�o de R$ ' . number_format($totalCalculated, 2, ',', '.') . ' aguardando aprova��o.',
                    'info'
                );
                
                // 5. Log de sucesso
                error_log("registerPayment - Pagamento registrado com sucesso: ID=$paymentId, Valor=$totalCalculated, Transa��es=" . implode(',', $transactionIds));
                
                // Commit da transa��o
                $db->commit();
                error_log("registerPayment - Sucesso total!");
                
                return [
                    'status' => true,
                    'message' => 'Pagamento registrado com sucesso! Aguardando aprova��o da administra��o.',
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
                error_log("registerPayment - Erro durante transa��o: " . $e->getMessage());
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
    * Aprova um pagamento de comiss�o
    * 
    * @param int $paymentId ID do pagamento
    * @param string $observacao Observa��o opcional
    * @return array Resultado da opera��o
    */
    public static function approvePayment($paymentId, $observacao = '') {
        try {
            // Verificar se o usu�rio est� autenticado e � administrador
            if (!AuthController::isAuthenticated() || !AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            $db = Database::getConnection();
            
            // Verificar se o pagamento existe e est� pendente
            $paymentStmt = $db->prepare("
                SELECT p.*, l.nome_fantasia as loja_nome
                FROM pagamentos_comissao p
                JOIN lojas l ON p.loja_id = l.id
                WHERE p.id = ? AND p.status = 'pendente'
            ");
            $paymentStmt->execute([$paymentId]);
            $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payment) {
                return ['status' => false, 'message' => 'Pagamento n�o encontrado ou n�o est� pendente.'];
            }
            
            // Obter transa��es associadas ao pagamento ANTES de iniciar a transa��o
            $transStmt = $db->prepare("
                SELECT t.id, t.usuario_id, t.loja_id, t.valor_cliente
                FROM pagamentos_transacoes pt
                JOIN transacoes_cashback t ON pt.transacao_id = t.id
                WHERE pt.pagamento_id = ?
            ");
            $transStmt->execute([$paymentId]);
            $transactions = $transStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($transactions) == 0) {
                return ['status' => false, 'message' => 'Nenhuma transa��o encontrada para este pagamento.'];
            }
            
            // Iniciar transa��o principal
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
                
                // 2. Atualizar status das transa��es
                $transactionIds = array_column($transactions, 'id');
                $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
                
                $updateTransStmt = $db->prepare("
                    UPDATE transacoes_cashback 
                    SET status = 'aprovado' 
                    WHERE id IN ($placeholders)
                ");
                $updateTransResult = $updateTransStmt->execute($transactionIds);
                
                if (!$updateTransResult) {
                    throw new Exception('Erro ao atualizar status das transa��es');
                }
                
                // 3. Atualizar comiss�es
                $updateCommissionStmt = $db->prepare("
                    UPDATE transacoes_comissao 
                    SET status = 'aprovado' 
                    WHERE transacao_id IN ($placeholders)
                ");
                $updateCommissionResult = $updateCommissionStmt->execute($transactionIds);
                
                if (!$updateCommissionResult) {
                    throw new Exception('Erro ao atualizar status das comiss�es');
                }
                
                // Commit da transa��o principal ANTES de creditar saldos
                $db->commit();
                error_log("APROVA��O: Transa��o principal commitada com sucesso");
                
                // 4. Creditar saldos FORA da transa��o principal para evitar conflitos
                require_once __DIR__ . '/../models/CashbackBalance.php';
                require_once __DIR__ . '/AdminController.php';
                $balanceModel = new CashbackBalance();
                $saldosCreditados = 0;
                $totalCashbackReservado = 0; // NOVO: controlar total para reserva
                
                foreach ($transactions as $transaction) {
                    if ($transaction['valor_cliente'] > 0) {
                        $description = "Cashback da compra - Transa��o #{$transaction['id']} (Pagamento #{$paymentId} aprovado)";
                        
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
                            error_log("APROVA��O: Saldo creditado com sucesso - Transa��o: {$transaction['id']}");
                        } else {
                            error_log("APROVA��O: ERRO ao creditar saldo - Transa��o: {$transaction['id']}");
                        }
                    }
                }
                
                // NOVO: 5. Criar reserva de cashback ap�s creditar saldos dos clientes
                if ($totalCashbackReservado > 0) {
                    $reservaResult = self::createCashbackReserve(
                        $totalCashbackReservado, 
                        $paymentId, 
                        "Reserva de cashback - Pagamento #{$paymentId} aprovado - Total de clientes: {$saldosCreditados}"
                    );
                    
                    if (!$reservaResult) {
                        error_log("APROVA��O: ERRO ao criar reserva de cashback para pagamento #{$paymentId}");
                    } else {
                        error_log("APROVA��O: Reserva de cashback criada: R$ {$totalCashbackReservado}");
                    }
                }
                
                // Atualizar saldo do administrador (ap�s o commit principal)
                foreach ($transactions as $transaction) {
                    // Obter valor da comiss�o do admin para esta transa��o
                    $adminComissionStmt = $db->prepare("
                        SELECT valor_comissao 
                        FROM transacoes_comissao 
                        WHERE transacao_id = ? AND tipo_usuario = 'admin'
                    ");
                    $adminComissionStmt->execute([$transaction['id']]);
                    $adminComission = $adminComissionStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($adminComission && $adminComission['valor_comissao'] > 0) {
                        $descricao = "Comiss�o da transa��o #{$transaction['id']} - Pagamento #{$paymentId} aprovado";
                        
                        $updateResult = AdminController::updateAdminBalance(
                            $adminComission['valor_comissao'],
                            $transaction['id'],
                            $descricao
                        );
                        
                        if (!$updateResult) {
                            error_log("APROVA��O: Falha ao atualizar saldo admin para transa��o #{$transaction['id']}");
                        }
                    }
                }
                
                // 5. Criar notifica��es (fora da transa��o)
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
                            
                            // Criar notifica��o
                            if ($clientTrans['total_trans'] > 0) {
                                self::createNotification(
                                    $transaction['usuario_id'],
                                    'Cashback dispon�vel!',
                                    'Seu cashback de R$ ' . number_format($clientTrans['total_cashback'], 2, ',', '.') . 
                                    ' da loja ' . $payment['loja_nome'] . ' est� dispon�vel.',
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
                        'Seu pagamento de comiss�o no valor de R$ ' . number_format($payment['valor_total'], 2, ',', '.') . ' foi aprovado.',
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
                // Rollback apenas se a transa��o ainda estiver ativa
                if ($db->inTransaction()) {
                    $db->rollBack();
                    error_log('APROVA��O: Rollback executado devido ao erro: ' . $e->getMessage());
                }
                throw $e;
            }
            
        } catch (Exception $e) {
            error_log('APROVA��O: Erro geral ao aprovar pagamento: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao aprovar pagamento: ' . $e->getMessage()];
        }
    }
    /**
    * NOVO M�TODO: Cria reserva de cashback
    * 
    * @param float $valor Valor a ser reservado
    * @param int $transacaoId ID da transa��o relacionada
    * @param string $descricao Descri��o da opera��o
    * @return bool Resultado da opera��o
    */
    private static function createCashbackReserve($valor, $transacaoId = null, $descricao = '') {
        try {
            $db = Database::getConnection();
            
            // Obter ou criar registro da reserva
            $reservaStmt = $db->prepare("SELECT * FROM admin_reserva_cashback WHERE id = 1");
            $reservaStmt->execute();
            $reserva = $reservaStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$reserva) {
                // Criar registro inicial se n�o existir
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
            
            // Calcular novos valores (CR�DITO = cashback disponibilizado para clientes)
            $novoTotal = $reserva['valor_total'] + $valor;
            $novoDisponivel = $reserva['valor_disponivel'] + $valor;
            $novoUsado = $reserva['valor_usado']; // N�o muda ainda
            
            // Atualizar reserva
            $updateStmt = $db->prepare("
                UPDATE admin_reserva_cashback 
                SET valor_total = ?, valor_disponivel = ?, valor_usado = ?, ultima_atualizacao = NOW() 
                WHERE id = 1
            ");
            $updateStmt->execute([$novoTotal, $novoDisponivel, $novoUsado]);
            
            // Registrar movimenta��o
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
            
            // Registrar movimenta��o
            $movStmt = $db->prepare("
                INSERT INTO admin_saldo_movimentacoes (transacao_id, valor, tipo, descricao) 
                VALUES (?, ?, 'credito', ?)
            ");
            $descricao = "Comiss�o da transa��o #{$transacaoId} - Pagamento PIX #{$paymentId} aprovado automaticamente";
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
            
            // Iniciar transa��o
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
                return ['status' => false, 'message' => 'Pagamento n�o encontrado ou j� processado'];
            }
            
            // Buscar transa��es relacionadas
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
                return ['status' => false, 'message' => 'Nenhuma transa��o encontrada para este pagamento'];
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
            
            // Processar cada transa��o
            foreach ($transactions as $transaction) {
                // Atualizar status da transa��o para 'aprovado'
                $updateTransactionStmt = $db->prepare("
                    UPDATE transacoes_cashback 
                    SET status = 'aprovado' 
                    WHERE id = ?
                ");
                $updateTransactionStmt->execute([$transaction['id']]);
                
                // Liberar cashback para o cliente
                $cashbackValue = $transaction['valor_cliente'];
                $totalCashbackLiberado += $cashbackValue;
                
                // Verificar se o saldo j� existe
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
                
                // Registrar movimenta��o de cashback
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
                self::updateAdminBalance($cashbackValue, 'credito', "Comiss�o recebida - Transa��o {$transaction['id']}");
                
                $transacoesAprovadas++;
                
                // Enviar notifica��o para o cliente
                self::sendCashbackNotification($transaction['usuario_id'], $cashbackValue, $payment['loja_id']);
            }
            
            // Commit da transa��o
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
     * Enviar notifica��o de cashback liberado para o cliente
     * Vers�o integrada que inclui notifica��o autom�tica via WhatsApp
     */
    private static function sendCashbackNotification($userId, $cashbackValue, $lojaId) {
        try {
            $db = Database::getConnection();
            
            // Buscar informa��es completas da loja e do cliente em uma consulta otimizada
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
            
            // FUNCIONALIDADE EXISTENTE: Criar notifica��o interna (preservada integralmente)
            $notifStmt = $db->prepare("
                INSERT INTO notificacoes (usuario_id, titulo, mensagem, tipo) 
                VALUES (?, ?, ?, 'success')
            ");
            $notifStmt->execute([
                $userId,
                'Cashback Liberado!',
                "Seu cashback de R$ " . number_format($cashbackValue, 2, ',', '.') . 
                " da loja {$nomeLoja} foi liberado e est� dispon�vel para uso!"
            ]);
            
            // NOVA FUNCIONALIDADE: Notifica��o autom�tica via WhatsApp
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
                    
                    // Enviar notifica��o via WhatsApp usando template espec�fico
                    WhatsAppBot::sendCashbackReleasedNotification(
                        $notificationData['cliente_telefone'], 
                        $whatsappTransactionData
                    );
                    
                    // O resultado ser� automaticamente registrado em nosso sistema de logs
                    // Voc� poder� monitorar o sucesso na interface que acabamos de validar
                    
                } catch (Exception $whatsappException) {
                    // Log espec�fico para erros de WhatsApp sem afetar o fluxo principal
                    error_log("WhatsApp Cashback Liberado - Erro: " . $whatsappException->getMessage());
                }
            }
            
        } catch (Exception $e) {
            // Log de erro geral mantendo a funcionalidade do sistema intacta
            error_log('Erro na notifica��o de cashback liberado: ' . $e->getMessage());
        }
    }

    /**
     * Rejeita um pagamento de comiss�o
     * 
     * @param int $paymentId ID do pagamento
     * @param string $motivo Motivo da rejei��o
     * @return array Resultado da opera��o
     */
    public static function rejectPayment($paymentId, $motivo) {
        try {
            // Verificar se o usu�rio est� autenticado e � administrador
            if (!AuthController::isAuthenticated() || !AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            if (empty($motivo)) {
                return ['status' => false, 'message' => '� necess�rio informar o motivo da rejei��o.'];
            }
            
            $db = Database::getConnection();
            
            // Verificar se o pagamento existe e est� pendente
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
                return ['status' => false, 'message' => 'Pagamento n�o encontrado ou n�o est� pendente.'];
            }
            
            // Iniciar transa��o
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
            
            // Obter transa��es associadas ao pagamento
            $transStmt = $db->prepare("
                SELECT t.id, t.usuario_id, t.valor_total, t.valor_cliente
                FROM pagamentos_transacoes pt
                JOIN transacoes_cashback t ON pt.transacao_id = t.id
                WHERE pt.pagamento_id = :payment_id
            ");
            $transStmt->bindParam(':payment_id', $paymentId);
            $transStmt->execute();
            $transactions = $transStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Atualizar status das transa��es para pendente novamente
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
                // Notifica��o no sistema (se houver usu�rio vinculado)
                if (!empty($storeNotify['usuario_id'])) {
                    self::createNotification(
                        $storeNotify['usuario_id'],
                        'Pagamento rejeitado',
                        'Seu pagamento de comiss�o no valor de R$ ' . number_format($payment['valor_total'], 2, ',', '.') . 
                        ' foi rejeitado. Motivo: ' . $motivo,
                        'error'
                    );
                }
                
                // Email
                if (!empty($storeNotify['email'])) {
                    $subject = 'Pagamento Rejeitado - Klube Cash';
                    $message = "
                        <h3>Ol�, {$payment['loja_nome']}!</h3>
                        <p>Infelizmente, seu pagamento de comiss�o foi rejeitado.</p>
                        <p><strong>Valor:</strong> R$ " . number_format($payment['valor_total'], 2, ',', '.') . "</p>
                        <p><strong>M�todo:</strong> {$payment['metodo_pagamento']}</p>
                        <p><strong>Data:</strong> " . date('d/m/Y H:i:s') . "</p>
                        <p><strong>Motivo da rejei��o:</strong> " . nl2br(htmlspecialchars($motivo)) . "</p>
                        <p>Por favor, verifique o motivo da rejei��o e registre um novo pagamento.</p>
                        <p>Atenciosamente,<br>Equipe Klube Cash</p>
                    ";
                    
                    Email::send($storeNotify['email'], $subject, $message, $payment['loja_nome']);
                }
            }
            
            // Confirmar transa��o
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
            // Reverter transa��o em caso de erro
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            
            error_log('Erro ao rejeitar pagamento: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao rejeitar pagamento. Tente novamente.'];
        }
    }
    
    /**
    * Obt�m lista de transa��es pendentes para uma loja
    * 
    * @param int $storeId ID da loja
    * @param array $filters Filtros adicionais
    * @param int $page P�gina atual
    * @return array Lista de transa��es pendentes
    */
    public static function getPendingTransactions($storeId, $filters = [], $page = 1) {
        try {
            // Verificar se o usu�rio est� autenticado
            if (!AuthController::isAuthenticated()) {
                return ['status' => false, 'message' => 'Usu�rio n�o autenticado.'];
            }
            
            $db = Database::getConnection();
            
            // Verificar permiss�es - apenas a loja dona das transa��es ou admin podem acessar
            if (AuthController::isStore()) {
                $currentUserId = AuthController::getCurrentUserId();
                $storeOwnerQuery = $db->prepare("SELECT usuario_id FROM lojas WHERE id = :loja_id");
                $storeOwnerQuery->bindParam(':loja_id', $storeId);
                $storeOwnerQuery->execute();
                $storeOwner = $storeOwnerQuery->fetch(PDO::FETCH_ASSOC);
                
                if (!$storeOwner || $storeOwner['usuario_id'] != $currentUserId) {
                    return ['status' => false, 'message' => 'Acesso n�o autorizado a esta loja.'];
                }
            } elseif (!AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Acesso n�o autorizado.'];
            }
            
            // Verificar se a loja existe
            $storeStmt = $db->prepare("SELECT id FROM lojas WHERE id = :loja_id");
            $storeStmt->bindParam(':loja_id', $storeId);
            $storeStmt->execute();
            
            if ($storeStmt->rowCount() == 0) {
                return ['status' => false, 'message' => 'Loja n�o encontrada.'];
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
                // Filtro por per�odo
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
                
                // Filtro por valor m�nimo
                if (isset($filters['valor_min']) && !empty($filters['valor_min'])) {
                    $query .= " AND t.valor_total >= :valor_min";
                    $params[':valor_min'] = $filters['valor_min'];
                }
                
                // Filtro por valor m�ximo
                if (isset($filters['valor_max']) && !empty($filters['valor_max'])) {
                    $query .= " AND t.valor_total <= :valor_max";
                    $params[':valor_max'] = $filters['valor_max'];
                }
            }
            
            // Ordena��o
            $query .= " ORDER BY t.data_transacao DESC";
            
            // Contagem total para pagina��o
            $countQuery = str_replace("t.*, u.nome as cliente_nome, u.email as cliente_email", "COUNT(*) as total", $query);
            $countStmt = $db->prepare($countQuery);
            
            foreach ($params as $param => $value) {
                $countStmt->bindValue($param, $value);
            }
            
            $countStmt->execute();
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Pagina��o
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
            error_log('Erro ao obter transa��es pendentes: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao obter transa��es pendentes. Tente novamente.'];
        }
    }
    
    /**
    * Obt�m detalhes de um pagamento
    * 
    * @param int $paymentId ID do pagamento
    * @return array Detalhes do pagamento
    */
    public static function getPaymentDetails($paymentId) {
        try {
            // Verificar se o usu�rio est� autenticado
            if (!AuthController::isAuthenticated()) {
                return ['status' => false, 'message' => 'Usu�rio n�o autenticado.'];
            }
            
            $db = Database::getConnection();
            
            // Obter dados do pagamento com mais informa��es
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
                return ['status' => false, 'message' => 'Pagamento n�o encontrado.'];
            }
            
            // Verificar permiss�es - admin ou a pr�pria loja
            $currentUserId = AuthController::getCurrentUserId();
            if (!AuthController::isAdmin()) {
                if (AuthController::isStore()) {
                    // Verificar se � a loja dona do pagamento
                    $storeCheckStmt = $db->prepare("SELECT usuario_id FROM lojas WHERE id = :loja_id");
                    $storeCheckStmt->bindParam(':loja_id', $payment['loja_id']);
                    $storeCheckStmt->execute();
                    $storeCheck = $storeCheckStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$storeCheck || $storeCheck['usuario_id'] != $currentUserId) {
                        return ['status' => false, 'message' => 'Acesso n�o autorizado.'];
                    }
                } else {
                    return ['status' => false, 'message' => 'Acesso n�o autorizado.'];
                }
            }
            
            // Obter transa��es associadas com mais detalhes
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
     * Cria uma notifica��o para um usu�rio
     * 
     * @param int $userId ID do usu�rio
     * @param string $titulo T�tulo da notifica��o
     * @param string $mensagem Mensagem da notifica��o
     * @param string $tipo Tipo da notifica��o (info, success, warning, error)
     * @return bool Verdadeiro se a notifica��o foi criada
     */
    private static function createNotification($userId, $titulo, $mensagem, $tipo = 'info') {
        try {
            $db = Database::getConnection();
            
            // Verificar se a tabela existe, criar se n�o existir
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
            error_log('Erro ao criar notifica��o: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
    * Obt�m hist�rico de pagamentos de uma loja
    * 
    * @param int $storeId ID da loja
    * @param array $filters Filtros adicionais
    * @param int $page P�gina atual
    * @return array Hist�rico de pagamentos
    */
    public static function getPaymentHistory($storeId, $filters = [], $page = 1) {
        try {
            // Verificar se o usu�rio est� autenticado
            if (!AuthController::isAuthenticated()) {
                return ['status' => false, 'message' => 'Usu�rio n�o autenticado.'];
            }
            
            $db = Database::getConnection();
            
            // Verificar permiss�es - apenas a loja dona dos pagamentos ou admin podem acessar
            if (AuthController::isStore()) {
                $currentUserId = AuthController::getCurrentUserId();
                $storeOwnerQuery = $db->prepare("SELECT usuario_id FROM lojas WHERE id = :loja_id");
                $storeOwnerQuery->bindParam(':loja_id', $storeId);
                $storeOwnerQuery->execute();
                $storeOwner = $storeOwnerQuery->fetch(PDO::FETCH_ASSOC);
                
                if (!$storeOwner || $storeOwner['usuario_id'] != $currentUserId) {
                    return ['status' => false, 'message' => 'Acesso n�o autorizado a esta loja.'];
                }
            } elseif (!AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Acesso n�o autorizado.'];
            }
            
            // Verificar se a loja existe
            $storeStmt = $db->prepare("SELECT id, nome_fantasia FROM lojas WHERE id = :loja_id");
            $storeStmt->bindParam(':loja_id', $storeId);
            $storeStmt->execute();
            
            if ($storeStmt->rowCount() == 0) {
                return ['status' => false, 'message' => 'Loja n�o encontrada.'];
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
                
                // Filtro por per�odo
                if (isset($filters['data_inicio']) && !empty($filters['data_inicio'])) {
                    $query .= " AND p.data_registro >= :data_inicio";
                    $params[':data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
                }
                
                if (isset($filters['data_fim']) && !empty($filters['data_fim'])) {
                    $query .= " AND p.data_registro <= :data_fim";
                    $params[':data_fim'] = $filters['data_fim'] . ' 23:59:59';
                }
                
                // Filtro por m�todo de pagamento
                if (isset($filters['metodo_pagamento']) && !empty($filters['metodo_pagamento'])) {
                    $query .= " AND p.metodo_pagamento = :metodo_pagamento";
                    $params[':metodo_pagamento'] = $filters['metodo_pagamento'];
                }
            }
            
            // Ordena��o
            $query .= " ORDER BY p.data_registro DESC";
            
            // Contagem total para pagina��o
            $countQuery = str_replace("p.*, (SELECT COUNT(*) FROM pagamentos_transacoes WHERE pagamento_id = p.id) as qtd_transacoes", "COUNT(*) as total", $query);
            $countStmt = $db->prepare($countQuery);
            
            foreach ($params as $param => $value) {
                $countStmt->bindValue($param, $value);
            }
            
            $countStmt->execute();
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Pagina��o
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
            error_log('Erro ao obter hist�rico de pagamentos: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao obter hist�rico de pagamentos. Tente novamente.'];
        }
    }

    /**
     * M�todo auxiliar para extrair informa��es de tempo dos resultados de notifica��o
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

// Processar requisi��es diretas de acesso ao controlador
if (basename($_SERVER['PHP_SELF']) === 'TransactionController.php') {
     // Verificar se � requisi��o AJAX
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    // Verificar se o usu�rio est� autenticado
    if (!AuthController::isAuthenticated()) {
        if ($isAjax) {
            echo json_encode(['status' => false, 'message' => 'Sess�o expirada. Fa�a login novamente.']);
            exit;
        } else {
            header('Location: ' . LOGIN_URL . '?error=' . urlencode('Voc� precisa fazer login para acessar esta p�gina.'));
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
                echo json_encode(['status' => false, 'message' => 'ID da transa��o inv�lido']);
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
                echo json_encode(['status' => false, 'message' => 'ID do pagamento inv�lido']);
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
            // Debug da sess�o
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
            
            // M�todo simplificado para testar
            try {
                $db = Database::getConnection();
                
                // Query melhorada que inclui informa��es de loja MVP
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
                
                // CORRE��O: Calcular total de pendentes excluindo MVP aprovadas
                $totalPendentes = 0;
                foreach ($transactions as $transaction) {
                    // Se � uma transa��o pendente E n�o � uma loja MVP (que seria aprovada automaticamente)
                    if ($transaction['status'] == 'pendente') {
                        // Para lojas MVP, as transa��es deveriam estar aprovadas, 
                        // ent�o se est�o pendentes, algo deu errado e devem aparecer
                        $totalPendentes++;
                    }
                    // Transa��es MVP aprovadas n�o contam como pendentes (comportamento correto)
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
            // Acesso inv�lido ao controlador
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

