<?php
// controllers/StoreBalancePaymentController.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/AuthController.php';

/**
 * Controlador para gerenciar pagamentos de saldo às lojas
 * Gerencia o fluxo de reembolso às lojas quando clientes usam saldo de cashback
 */
class StoreBalancePaymentController {
    
    /**
    * Obtém pagamentos de saldo pendentes para lojas (VERSÃO CORRIGIDA)
    * 
    * A correção principal está na lógica da consulta SQL. Agora buscamos
    * diretamente as movimentações de uso de saldo, independentemente de 
    * já estarem vinculadas a um pagamento ou não.
    */
    public static function getPendingStoreBalancePayments($filters = [], $page = 1) {
        try {
            // Verificar se o usuário está autenticado e é administrador
            if (!AuthController::isAuthenticated() || !AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            $db = Database::getConnection();
            $limit = ITEMS_PER_PAGE;
            $offset = ($page - 1) * $limit;
            
            // Construir condições WHERE
            $whereConditions = ["cm.tipo_operacao = 'uso'", "cm.transacao_uso_id IS NOT NULL"];
            $params = [];
            
            // Aplicar filtros
            if (!empty($filters['loja_id'])) {
                $whereConditions[] = "cm.loja_id = :loja_id";
                $params[':loja_id'] = $filters['loja_id'];
            }
            
            if (!empty($filters['data_inicio'])) {
                $whereConditions[] = "DATE(cm.data_operacao) >= :data_inicio";
                $params[':data_inicio'] = $filters['data_inicio'];
            }
            
            if (!empty($filters['data_fim'])) {
                $whereConditions[] = "DATE(cm.data_operacao) <= :data_fim";
                $params[':data_fim'] = $filters['data_fim'];
            }
            
            // CORREÇÃO PRINCIPAL: Filtrar por status de pagamento de forma mais inteligente
            if (!empty($filters['status_pagamento'])) {
                if ($filters['status_pagamento'] === 'pendente') {
                    $whereConditions[] = "(sbp.status IS NULL OR sbp.status = 'pendente')";
                } else {
                    $whereConditions[] = "sbp.status = :status_pagamento";
                    $params[':status_pagamento'] = $filters['status_pagamento'];
                }
            } else {
                // Por padrão, mostrar pendentes (incluindo os sem pagamento)
                $whereConditions[] = "(sbp.status IS NULL OR sbp.status IN ('pendente', 'em_processamento'))";
            }
            
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            
            // CONSULTA SQL CORRIGIDA: Agrupa por loja e mostra o que precisa ser pago
            $query = "
                SELECT 
                    cm.loja_id,
                    l.nome_fantasia as loja_nome,
                    l.email as loja_email,
                    COUNT(DISTINCT cm.id) as total_transacoes,
                    SUM(cm.valor) as valor_total_saldo,
                    MIN(cm.data_operacao) as data_mais_antiga,
                    MAX(cm.data_operacao) as data_mais_recente,
                    -- Informações do pagamento (se existir)
                    MAX(COALESCE(sbp.id, 0)) as pagamento_id,
                    MAX(COALESCE(sbp.status, 'pendente')) as status_pagamento,
                    MAX(sbp.data_criacao) as data_pagamento,
                    -- Contar quantas movimentações ainda não têm pagamento
                    COUNT(CASE WHEN cm.pagamento_id IS NULL THEN 1 END) as movimentacoes_sem_pagamento
                FROM cashback_movimentacoes cm
                JOIN lojas l ON cm.loja_id = l.id
                LEFT JOIN store_balance_payments sbp ON cm.pagamento_id = sbp.id
                $whereClause
                GROUP BY cm.loja_id, l.nome_fantasia, l.email
                -- Mostrar apenas lojas que têm movimentações pendentes
                HAVING valor_total_saldo > 0
                ORDER BY data_mais_recente DESC, valor_total_saldo DESC
                LIMIT :limit OFFSET :offset
            ";
            
            // Query para contar total de registros
            $countQuery = "
                SELECT COUNT(DISTINCT cm.loja_id) as total
                FROM cashback_movimentacoes cm
                JOIN lojas l ON cm.loja_id = l.id
                LEFT JOIN store_balance_payments sbp ON cm.pagamento_id = sbp.id
                $whereClause
            ";
            
            // Executar query de contagem
            $countStmt = $db->prepare($countQuery);
            foreach ($params as $param => $value) {
                $countStmt->bindValue($param, $value);
            }
            $countStmt->execute();
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Executar query principal
            $stmt = $db->prepare($query);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // CONSULTA DE ESTATÍSTICAS CORRIGIDA
            $totalQuery = "
                SELECT 
                    COUNT(DISTINCT cm.loja_id) as total_lojas,
                    COUNT(DISTINCT cm.id) as total_transacoes,
                    SUM(CASE 
                        WHEN sbp.status IS NULL OR sbp.status IN ('pendente', 'em_processamento') 
                        THEN cm.valor 
                        ELSE 0 
                    END) as valor_total_pendente,
                    SUM(CASE 
                        WHEN sbp.status = 'aprovado' 
                        THEN cm.valor 
                        ELSE 0 
                    END) as valor_total_pago
                FROM cashback_movimentacoes cm
                JOIN lojas l ON cm.loja_id = l.id
                LEFT JOIN store_balance_payments sbp ON cm.pagamento_id = sbp.id
                WHERE cm.tipo_operacao = 'uso'
                AND cm.transacao_uso_id IS NOT NULL
            ";
            
            $totalStmt = $db->query($totalQuery);
            $totals = $totalStmt->fetch(PDO::FETCH_ASSOC);
            
            // Calcular paginação
            $totalPages = ceil($totalCount / $limit);
            
            return [
                'status' => true,
                'data' => [
                    'pagamentos' => $payments,
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
            error_log('Erro ao obter pagamentos de saldo pendentes: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao carregar pagamentos pendentes.'];
        }
    }
    /**
 * Obtém histórico de repasses de saldo para uma loja específica
 * 
 * @param int $storeId ID da loja
 * @param array $filters Filtros adicionais
 * @param int $page Página atual para paginação
 * @return array Resultado da operação
 */
public static function getStoreBalanceHistory($storeId, $filters = [], $page = 1) {
    try {
        $db = Database::getConnection();
        $limit = ITEMS_PER_PAGE;
        $offset = ($page - 1) * $limit;
        
        // Construir condições WHERE
        $whereConditions = ["sbp.loja_id = :loja_id"];
        $params = [':loja_id' => $storeId];
        
        // Aplicar filtros
        if (!empty($filters['status'])) {
            $whereConditions[] = "sbp.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['data_inicio'])) {
            $whereConditions[] = "DATE(sbp.data_criacao) >= :data_inicio";
            $params[':data_inicio'] = $filters['data_inicio'];
        }
        
        if (!empty($filters['data_fim'])) {
            $whereConditions[] = "DATE(sbp.data_criacao) <= :data_fim";
            $params[':data_fim'] = $filters['data_fim'];
        }
        
        $whereClause = "WHERE " . implode(" AND ", $whereConditions);
        
        // Query principal para buscar repasses
        $query = "
            SELECT 
                sbp.*,
                l.nome_fantasia as loja_nome,
                COUNT(cm.id) as total_transacoes_relacionadas,
                SUM(cm.valor) as valor_total_saldo_usado
            FROM store_balance_payments sbp
            JOIN lojas l ON sbp.loja_id = l.id
            LEFT JOIN cashback_movimentacoes cm ON cm.pagamento_id = sbp.id
            {$whereClause}
            GROUP BY sbp.id
            ORDER BY sbp.data_criacao DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $repasses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Query para contar total de registros
        $countQuery = "
            SELECT COUNT(DISTINCT sbp.id) as total
            FROM store_balance_payments sbp
            JOIN lojas l ON sbp.loja_id = l.id
            {$whereClause}
        ";
        
        $countStmt = $db->prepare($countQuery);
        $countStmt->execute($params);
        $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Calcular estatísticas
        $statsQuery = "
            SELECT 
                COUNT(*) as total_repasses,
                SUM(CASE WHEN sbp.status = 'aprovado' THEN sbp.valor_total ELSE 0 END) as valor_total_aprovado,
                SUM(CASE WHEN sbp.status = 'pendente' THEN sbp.valor_total ELSE 0 END) as valor_total_pendente,
                SUM(CASE WHEN sbp.status = 'em_processamento' THEN sbp.valor_total ELSE 0 END) as valor_total_processamento,
                COUNT(CASE WHEN sbp.status = 'aprovado' THEN 1 END) as total_aprovados,
                COUNT(CASE WHEN sbp.status = 'pendente' THEN 1 END) as total_pendentes,
                COUNT(CASE WHEN sbp.status = 'em_processamento' THEN 1 END) as total_processamento
            FROM store_balance_payments sbp
            WHERE sbp.loja_id = :loja_id
        ";
        
        $statsStmt = $db->prepare($statsQuery);
        $statsStmt->execute([':loja_id' => $storeId]);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'status' => true,
            'data' => [
                'repasses' => $repasses,
                'estatisticas' => $stats,
                'paginacao' => [
                    'pagina_atual' => $page,
                    'total_registros' => intval($totalRecords),
                    'registros_por_pagina' => $limit,
                    'total_paginas' => ceil($totalRecords / $limit)
                ]
            ]
        ];
        
    } catch (PDOException $e) {
        error_log('Erro ao obter histórico de repasses: ' . $e->getMessage());
        return ['status' => false, 'message' => 'Erro ao obter histórico de repasses.'];
    }
}

/**
 * Obtém detalhes de um repasse específico para a loja
 * 
 * @param int $repasseId ID do repasse
 * @param int $storeId ID da loja (para verificação de segurança)
 * @return array Detalhes do repasse
 */
public static function getStoreBalanceRepasseDetails($repasseId, $storeId) {
    try {
        $db = Database::getConnection();
        
        // Buscar dados do repasse
        $repasseStmt = $db->prepare("
            SELECT sbp.*, l.nome_fantasia as loja_nome
            FROM store_balance_payments sbp
            JOIN lojas l ON sbp.loja_id = l.id
            WHERE sbp.id = ? AND sbp.loja_id = ?
        ");
        $repasseStmt->execute([$repasseId, $storeId]);
        $repasse = $repasseStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$repasse) {
            return ['status' => false, 'message' => 'Repasse não encontrado.'];
        }
        
        // Buscar transações relacionadas ao repasse
        $transacoesStmt = $db->prepare("
            SELECT 
                cm.valor as valor_saldo_usado,
                cm.data_operacao,
                t.codigo_transacao,
                t.valor_total as valor_venda,
                u.nome as cliente_nome,
                u.email as cliente_email
            FROM cashback_movimentacoes cm
            JOIN transacoes_cashback t ON cm.transacao_uso_id = t.id
            JOIN usuarios u ON cm.usuario_id = u.id
            WHERE cm.pagamento_id = ?
            ORDER BY cm.data_operacao DESC
        ");
        $transacoesStmt->execute([$repasseId]);
        $transacoes = $transacoesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'status' => true,
            'data' => [
                'repasse' => $repasse,
                'transacoes' => $transacoes
            ]
        ];
        
    } catch (PDOException $e) {
        error_log('Erro ao obter detalhes do repasse: ' . $e->getMessage());
        return ['status' => false, 'message' => 'Erro ao obter detalhes do repasse.'];
    }
}
    /**
     * Obtém detalhes do uso de saldo para uma loja
     * 
     * @param int $lojaId ID da loja
     * @param array $filters Filtros para a listagem
     * @return array Detalhes do uso de saldo
     */
    public static function getStoreBalanceDetails($lojaId, $filters = []) {
        try {
            // Verificar se o usuário está autenticado e é administrador
            if (!AuthController::isAuthenticated() || !AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            $db = Database::getConnection();
            
            // Verificar se a loja existe
            $storeStmt = $db->prepare("SELECT id, nome_fantasia, email FROM lojas WHERE id = ?");
            $storeStmt->execute([$lojaId]);
            $store = $storeStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$store) {
                return ['status' => false, 'message' => 'Loja não encontrada.'];
            }
            
            // Construir condições WHERE
            $whereConditions = ["cm.loja_id = :loja_id"];
            $params = [':loja_id' => $lojaId];
            
            // Aplicar filtros
            if (!empty($filters['data_inicio'])) {
                $whereConditions[] = "DATE(cm.data_operacao) >= :data_inicio";
                $params[':data_inicio'] = $filters['data_inicio'];
            }
            
            if (!empty($filters['data_fim'])) {
                $whereConditions[] = "DATE(cm.data_operacao) <= :data_fim";
                $params[':data_fim'] = $filters['data_fim'];
            }
            
            if (!empty($filters['status_pagamento'])) {
                $whereConditions[] = "COALESCE(sbp.status, 'pendente') = :status_pagamento";
                $params[':status_pagamento'] = $filters['status_pagamento'];
            }
            
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            
            // Query para obter transações de uso de saldo
            $query = "
                SELECT 
                    cm.id as movimentacao_id,
                    cm.usuario_id,
                    u.nome as cliente_nome,
                    t.id as transacao_id,
                    t.codigo_transacao,
                    t.valor_total as valor_venda,
                    cm.valor as valor_saldo_usado,
                    cm.data_operacao,
                    COALESCE(sbp.id, 0) as pagamento_id,
                    COALESCE(sbp.status, 'pendente') as status_pagamento,
                    sbp.metodo_pagamento,
                    sbp.data_criacao as data_pagamento
                FROM cashback_movimentacoes cm
                JOIN usuarios u ON cm.usuario_id = u.id
                JOIN transacoes_cashback t ON cm.transacao_uso_id = t.id
                LEFT JOIN store_balance_payments sbp ON 
                    (cm.loja_id = sbp.loja_id AND 
                     cm.id = sbp.movimentacao_id)
                $whereClause
                AND cm.tipo_operacao = 'uso'
                AND cm.transacao_uso_id IS NOT NULL
                ORDER BY cm.data_operacao DESC
            ";
            
            $stmt = $db->prepare($query);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            $stmt->execute();
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular totais
            $totalQuery = "
                SELECT 
                    COUNT(*) as total_transacoes,
                    SUM(cm.valor) as valor_total_saldo,
                    COUNT(CASE WHEN COALESCE(sbp.status, 'pendente') = 'pendente' THEN 1 END) as total_pendentes,
                    SUM(CASE WHEN COALESCE(sbp.status, 'pendente') = 'pendente' THEN cm.valor ELSE 0 END) as valor_pendente,
                    COUNT(CASE WHEN sbp.status = 'aprovado' THEN 1 END) as total_pagos,
                    SUM(CASE WHEN sbp.status = 'aprovado' THEN cm.valor ELSE 0 END) as valor_pago
                FROM cashback_movimentacoes cm
                LEFT JOIN store_balance_payments sbp ON 
                    (cm.loja_id = sbp.loja_id AND 
                     cm.id = sbp.movimentacao_id)
                $whereClause
                AND cm.tipo_operacao = 'uso'
                AND cm.transacao_uso_id IS NOT NULL
            ";
            
            $totalStmt = $db->prepare($totalQuery);
            foreach ($params as $param => $value) {
                $totalStmt->bindValue($param, $value);
            }
            $totalStmt->execute();
            $totals = $totalStmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'status' => true,
                'data' => [
                    'loja' => $store,
                    'transacoes' => $transactions,
                    'totais' => $totals
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao obter detalhes de saldo da loja: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao carregar detalhes de saldo.'];
        }
    }
    
    /**
     * Processa o pagamento de saldo para uma loja
     * 
     * @param array $data Dados do pagamento
     * @return array Resultado da operação
     */
    public static function processStoreBalancePayment($data) {
        $db = null;
        
        try {
            // Verificar se o usuário está autenticado e é administrador
            if (!AuthController::isAuthenticated() || !AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            // Validar dados obrigatórios
            $requiredFields = ['loja_id', 'movimentacoes', 'valor_total', 'metodo_pagamento'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return ['status' => false, 'message' => 'Dados incompletos. Campo faltando: ' . $field];
                }
            }
            
            $db = Database::getConnection();
            
            // Verificar se a loja existe
            $storeStmt = $db->prepare("SELECT id, nome_fantasia, email FROM lojas WHERE id = ?");
            $storeStmt->execute([$data['loja_id']]);
            $store = $storeStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$store) {
                return ['status' => false, 'message' => 'Loja não encontrada.'];
            }
            
            // Converter movimentações para array se necessário
            $movimentacaoIds = is_array($data['movimentacoes']) ? $data['movimentacoes'] : explode(',', $data['movimentacoes']);
            $movimentacaoIds = array_map('intval', $movimentacaoIds);
            
            if (empty($movimentacaoIds)) {
                return ['status' => false, 'message' => 'Nenhuma movimentação selecionada.'];
            }
            
            // Verificar se as movimentações existem e são válidas
            $placeholders = implode(',', array_fill(0, count($movimentacaoIds), '?'));
            $validateStmt = $db->prepare("
                SELECT 
                    id, valor, loja_id, tipo_operacao, transacao_uso_id
                FROM cashback_movimentacoes 
                WHERE id IN ($placeholders) 
                AND loja_id = ? 
                AND tipo_operacao = 'uso'
                AND transacao_uso_id IS NOT NULL
            ");
            
            $validateParams = array_merge($movimentacaoIds, [$data['loja_id']]);
            $validateStmt->execute($validateParams);
            $movimentacoes = $validateStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($movimentacoes) !== count($movimentacaoIds)) {
                return [
                    'status' => false, 
                    'message' => 'Algumas movimentações não foram encontradas ou não são válidas.'
                ];
            }
            
            // Calcular valor total
            $totalCalculado = 0;
            foreach ($movimentacoes as $mov) {
                $totalCalculado += floatval($mov['valor']);
            }
            
            // Validar valor total
            $valorInformado = floatval($data['valor_total']);
            if (abs($totalCalculado - $valorInformado) > 0.01) {
                return [
                    'status' => false, 
                    'message' => 'Valor total informado (R$ ' . number_format($valorInformado, 2, ',', '.') . 
                               ') não confere com o valor das movimentações (R$ ' . number_format($totalCalculado, 2, ',', '.') . ')'
                ];
            }
            
            // Processar upload de comprovante se fornecido
            $comprovantePath = '';
            if (isset($data['comprovante']) && $data['comprovante']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = ROOT_DIR . '/uploads/comprovantes_saldo/';
                
                // Criar diretório se não existir
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileName = 'saldo_' . time() . '_' . basename($data['comprovante']['name']);
                $targetFile = $uploadDir . $fileName;
                
                if (move_uploaded_file($data['comprovante']['tmp_name'], $targetFile)) {
                    $comprovantePath = $fileName;
                } else {
                    return ['status' => false, 'message' => 'Erro ao fazer upload do comprovante.'];
                }
            }
            
            // Iniciar transação no banco de dados
            $db->beginTransaction();
            
            // 1. Criar registro do pagamento
            $paymentStmt = $db->prepare("
                INSERT INTO store_balance_payments (
                    loja_id, valor_total, metodo_pagamento, 
                    numero_referencia, comprovante, observacao, 
                    status, data_criacao
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, NOW()
                )
            ");
            
            $status = isset($data['aprovar_automaticamente']) && $data['aprovar_automaticamente'] ? 'aprovado' : 'em_processamento';
            
            $paymentStmt->execute([
                $data['loja_id'],
                $totalCalculado,
                $data['metodo_pagamento'],
                $data['numero_referencia'] ?? '',
                $comprovantePath,
                $data['observacao'] ?? '',
                $status
            ]);
            
            $paymentId = $db->lastInsertId();
            
            // 2. Vincular movimentações ao pagamento
            foreach ($movimentacaoIds as $movId) {
                $linkStmt = $db->prepare("
                    UPDATE cashback_movimentacoes
                    SET pagamento_id = ?
                    WHERE id = ?
                ");
                $linkStmt->execute([$paymentId, $movId]);
            }
            
            // 3. CORREÇÃO CRÍTICA: Registrar movimentação na reserva de cashback (saída)
            // Este é o ponto onde o sistema não estava funcionando corretamente
            self::updateCashbackReserveOnUse($db, $totalCalculado, $paymentId, "Reembolso à loja {$store['nome_fantasia']} - Pagamento ID #{$paymentId}");
            
            // 4. Criar notificação para loja
            $storeUserStmt = $db->prepare("SELECT usuario_id FROM lojas WHERE id = ?");
            $storeUserStmt->execute([$data['loja_id']]);
            $storeUser = $storeUserStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($storeUser && !empty($storeUser['usuario_id'])) {
                self::createNotification(
                    $db,
                    $storeUser['usuario_id'],
                    'Pagamento de saldo recebido',
                    'Você recebeu um pagamento de R$ ' . number_format($totalCalculado, 2, ',', '.') . 
                    ' referente ao saldo de cashback usado pelos clientes.',
                    $status == 'aprovado' ? 'success' : 'info'
                );
            }
            
            // Commit da transação ANTES de enviar email
            $db->commit();
            
            // 5. Enviar email para loja (após commit)
            if (!empty($store['email'])) {
                try {
                    $subject = 'Pagamento de Saldo - Klube Cash';
                    $statusText = $status == 'aprovado' ? 'aprovado e processado' : 'registrado e está em processamento';
                    
                    $message = "
                        <h3>Olá, {$store['nome_fantasia']}!</h3>
                        <p>Um pagamento referente ao saldo de cashback usado pelos seus clientes foi $statusText.</p>
                        <p><strong>Valor:</strong> R$ " . number_format($totalCalculado, 2, ',', '.') . "</p>
                        <p><strong>Método:</strong> " . ucfirst($data['metodo_pagamento']) . "</p>
                        <p><strong>Data:</strong> " . date('d/m/Y H:i:s') . "</p>
                    ";
                    
                    if (!empty($data['numero_referencia'])) {
                        $message .= "<p><strong>Referência:</strong> {$data['numero_referencia']}</p>";
                    }
                    
                    if (!empty($data['observacao'])) {
                        $message .= "<p><strong>Observação:</strong> {$data['observacao']}</p>";
                    }
                    
                    $message .= "<p>Este pagamento refere-se ao reembolso dos valores de cashback que seus clientes utilizaram nas compras.</p>";
                    $message .= "<p>Atenciosamente,<br>Equipe Klube Cash</p>";
                    
                    Email::send($store['email'], $subject, $message, $store['nome_fantasia']);
                } catch (Exception $emailError) {
                    // Log do erro de email mas não falha o processo
                    error_log('Erro ao enviar email: ' . $emailError->getMessage());
                }
            }
            
            return [
                'status' => true,
                'message' => 'Pagamento processado com sucesso!',
                'data' => [
                    'payment_id' => $paymentId,
                    'valor_total' => $totalCalculado,
                    'status' => $status
                ]
            ];
            
        } catch (Exception $e) {
            // Rollback em caso de erro (apenas se a transação ainda estiver ativa)
            if ($db && $db->inTransaction()) {
                $db->rollBack();
            }
            
            error_log('Erro ao processar pagamento de saldo: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao processar pagamento: ' . $e->getMessage()];
        }
    }
    
    /**
     * MÉTODO NOVO E CRÍTICO: Atualiza a reserva de cashback quando clientes USAM saldo
     * 
     * Este método resolve o problema principal do sistema. Quando um cliente usa saldo,
     * precisamos debitar da reserva de cashback, pois esse valor será reembolsado à loja.
     * 
     * FLUXO CORRETO:
     * 1. Transação aprovada → Cria reserva (crédito) ✅
     * 2. Cliente usa saldo → Debita da reserva (débito) ✅ ← ESTE MÉTODO
     * 3. Loja recebe reembolso → Não mexe na reserva (correto)
     * 
     * @param PDO $db Conexão de banco de dados
     * @param float $valor Valor usado pelos clientes (sempre positivo)
     * @param int|null $transacaoId ID da transação relacionada
     * @param string $descricao Descrição da operação
     * @return bool Resultado da operação
     */
    private static function updateCashbackReserveOnUse($db, $valor, $transacaoId = null, $descricao = '') {
        try {
            error_log("RESERVA USE: Processando uso de saldo - Valor: R$ {$valor}");
            
            // Obter reserva atual
            $reservaStmt = $db->prepare("SELECT * FROM admin_reserva_cashback WHERE id = 1");
            $reservaStmt->execute();
            $reserva = $reservaStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$reserva) {
                // Se não existe reserva, criar registro inicial
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
                error_log("RESERVA USE: Reserva criada inicialmente");
            }
            
            // DÉBITO: Cliente usou saldo, reduzir disponível e aumentar usado
            $valorUso = abs($valor); // Garantir que seja positivo
            $novoTotal = $reserva['valor_total']; // Total não muda no uso
            $novoDisponivel = $reserva['valor_disponivel'] - $valorUso; // Reduz disponível
            $novoUsado = $reserva['valor_usado'] + $valorUso; // Aumenta usado
            
            error_log("RESERVA USE: Calculando novos valores - Disponível: {$reserva['valor_disponivel']} → {$novoDisponivel}, Usado: {$reserva['valor_usado']} → {$novoUsado}");
            
            // Validar se tem saldo suficiente na reserva
            if ($novoDisponivel < 0) {
                error_log("RESERVA USE: AVISO - Saldo da reserva ficará negativo. Disponível atual: {$reserva['valor_disponivel']}, Tentando debitar: {$valorUso}");
                // Não falhar aqui, apenas registrar o aviso para investigação
            }
            
            // Atualizar reserva
            $updateStmt = $db->prepare("
                UPDATE admin_reserva_cashback 
                SET valor_disponivel = ?, valor_usado = ?, ultima_atualizacao = NOW() 
                WHERE id = 1
            ");
            $updateResult = $updateStmt->execute([$novoDisponivel, $novoUsado]);
            
            if (!$updateResult) {
                error_log("RESERVA USE: ERRO ao atualizar reserva");
                return false;
            }
            
            // Registrar movimentação (sempre como débito para uso de saldo)
            $movStmt = $db->prepare("
                INSERT INTO admin_reserva_movimentacoes (transacao_id, valor, tipo, descricao) 
                VALUES (?, ?, 'debito', ?)
            ");
            $movResult = $movStmt->execute([$transacaoId, $valorUso, $descricao]);
            
            if (!$movResult) {
                error_log("RESERVA USE: ERRO ao registrar movimentação");
                return false;
            }
            
            error_log("RESERVA USE: Sucesso - Reserva atualizada. Novo disponível: R$ {$novoDisponivel}, Novo usado: R$ {$novoUsado}");
            return true;
            
        } catch (Exception $e) {
            error_log('RESERVA USE: Erro ao atualizar reserva de cashback no uso: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Atualiza a reserva de cashback (versão compatível com transação)
     * 
     * MÉTODO CORRIGIDO: Este método é usado quando cashback é DISPONIBILIZADO (crédito)
     * É chamado quando pagamentos são aprovados e saldo é liberado para clientes
     */
    private static function updateCashbackReserve($db, $valor, $transacaoId = null, $descricao = '') {
        try {
            error_log("RESERVA CREDIT: Processando crédito de reserva - Valor: R$ {$valor}");
            
            // Obter ou criar registro da reserva
            $reservaStmt = $db->prepare("SELECT * FROM admin_reserva_cashback WHERE id = 1");
            $reservaStmt->execute();
            $reserva = $reservaStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$reserva) {
                // Criar registro inicial
                $createStmt = $db->prepare("
                    INSERT INTO admin_reserva_cashback (id, valor_total, valor_disponivel, valor_usado) 
                    VALUES (1, 0, 0, 0)
                ");
                $createStmt->execute();
                $reserva = ['valor_total' => 0, 'valor_disponivel' => 0, 'valor_usado' => 0];
                error_log("RESERVA CREDIT: Reserva criada inicialmente");
            }
            
            // Determinar se é crédito ou débito baseado no sinal do valor
            if ($valor > 0) {
                // CRÉDITO: Cashback disponibilizado para clientes
                $novoTotal = $reserva['valor_total'] + $valor;
                $novoDisponivel = $reserva['valor_disponivel'] + $valor;
                $novoUsado = $reserva['valor_usado']; // Não muda
                $tipoOperacao = 'credito';
                error_log("RESERVA CREDIT: Operação de crédito - Adicionando R$ {$valor} à reserva");
            } else {
                // DÉBITO: Cashback usado (este caso normalmente é tratado pelo método updateCashbackReserveOnUse)
                $valorAbs = abs($valor);
                $novoTotal = $reserva['valor_total']; // Total não muda no débito
                $novoDisponivel = $reserva['valor_disponivel'] - $valorAbs;
                $novoUsado = $reserva['valor_usado'] + $valorAbs;
                $tipoOperacao = 'debito';
                error_log("RESERVA CREDIT: Operação de débito - Removendo R$ {$valorAbs} da reserva");
            }
            
            // Atualizar reserva
            $updateStmt = $db->prepare("
                UPDATE admin_reserva_cashback 
                SET valor_total = ?, valor_disponivel = ?, valor_usado = ?, ultima_atualizacao = NOW() 
                WHERE id = 1
            ");
            $updateResult = $updateStmt->execute([$novoTotal, $novoDisponivel, $novoUsado]);
            
            if (!$updateResult) {
                error_log("RESERVA CREDIT: ERRO ao atualizar reserva");
                return false;
            }
            
            // Registrar movimentação
            $movStmt = $db->prepare("
                INSERT INTO admin_reserva_movimentacoes (transacao_id, valor, tipo, descricao) 
                VALUES (?, ?, ?, ?)
            ");
            
            $valorAbs = abs($valor);
            $movResult = $movStmt->execute([$transacaoId, $valorAbs, $tipoOperacao, $descricao]);
            
            if (!$movResult) {
                error_log("RESERVA CREDIT: ERRO ao registrar movimentação");
                return false;
            }
            
            error_log("RESERVA CREDIT: Sucesso - Reserva atualizada. Novo total: R$ {$novoTotal}, Disponível: R$ {$novoDisponivel}, Usado: R$ {$novoUsado}");
            return true;
            
        } catch (Exception $e) {
            error_log('RESERVA CREDIT: Erro ao atualizar reserva de cashback: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Atualiza o saldo do administrador (versão compatível com transação)
     * 
     * @param PDO $db Conexão de banco de dados
     * @param float $valor Valor a ser adicionado/subtraído
     * @param int|null $transacaoId ID da transação relacionada
     * @param string $descricao Descrição da movimentação
     * @return bool Resultado da operação
     */
    private static function updateAdminBalance($db, $valor, $transacaoId = null, $descricao = '') {
        try {
            // Obter ou criar registro do saldo admin
            $saldoStmt = $db->prepare("SELECT * FROM admin_saldo WHERE id = 1");
            $saldoStmt->execute();
            $saldo = $saldoStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$saldo) {
                // Criar registro inicial
                $createStmt = $db->prepare("
                    INSERT INTO admin_saldo (id, valor_total, valor_disponivel, valor_pendente) 
                    VALUES (1, 0, 0, 0)
                ");
                $createStmt->execute();
                $saldo = ['valor_total' => 0, 'valor_disponivel' => 0, 'valor_pendente' => 0];
            }
            
            // Calcular novos valores
            $novoTotal = $saldo['valor_total'] + $valor;
            $novoDisponivel = $saldo['valor_disponivel'] + $valor;
            
            // Atualizar saldo
            $updateStmt = $db->prepare("
                UPDATE admin_saldo 
                SET valor_total = ?, valor_disponivel = ?, ultima_atualizacao = NOW() 
                WHERE id = 1
            ");
            $updateStmt->execute([$novoTotal, $novoDisponivel]);
            
            // Registrar movimentação
            $movStmt = $db->prepare("
                INSERT INTO admin_saldo_movimentacoes (transacao_id, valor, tipo, descricao) 
                VALUES (?, ?, ?, ?)
            ");
            
            $tipo = $valor >= 0 ? 'credito' : 'debito';
            $valorAbs = abs($valor);
            
            $movStmt->execute([$transacaoId, $valorAbs, $tipo, $descricao]);
            
            return true;
            
        } catch (Exception $e) {
            error_log('Erro ao atualizar saldo admin: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Cria uma notificação para um usuário (versão compatível com transação)
     * 
     * @param PDO $db Conexão de banco de dados
     * @param int $userId ID do usuário
     * @param string $titulo Título da notificação
     * @param string $mensagem Mensagem da notificação
     * @param string $tipo Tipo da notificação (info, success, warning, error)
     * @return bool Verdadeiro se a notificação foi criada
     */
    private static function createNotification($db, $userId, $titulo, $mensagem, $tipo = 'info') {
        try {
            $stmt = $db->prepare("
                INSERT INTO notificacoes (usuario_id, titulo, mensagem, tipo, data_criacao, lida)
                VALUES (?, ?, ?, ?, NOW(), 0)
            ");
            
            return $stmt->execute([$userId, $titulo, $mensagem, $tipo]);
            
        } catch (PDOException $e) {
            error_log('Erro ao criar notificação: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
    * Obtém estatísticas gerais dos pagamentos de saldo
    * 
    * @return array Estatísticas consolidadas
    */
    public static function getBalanceStatistics() {
        try {
            // Verificar se o usuário está autenticado e é administrador
            if (!AuthController::isAuthenticated() || !AuthController::isAdmin()) {
                return [
                    'total_lojas' => 0,
                    'total_transacoes' => 0,
                    'valor_total_pendente' => 0,
                    'valor_total_pago' => 0
                ];
            }
            
            $db = Database::getConnection();
            
            // Query para obter estatísticas consolidadas
            $query = "
                SELECT 
                    COUNT(DISTINCT cm.loja_id) as total_lojas,
                    COUNT(DISTINCT cm.id) as total_transacoes,
                    SUM(CASE 
                        WHEN COALESCE(sbp.status, 'pendente') IN ('pendente', 'em_processamento') 
                        THEN cm.valor 
                        ELSE 0 
                    END) as valor_total_pendente,
                    SUM(CASE 
                        WHEN sbp.status = 'aprovado' 
                        THEN cm.valor 
                        ELSE 0 
                    END) as valor_total_pago
                FROM cashback_movimentacoes cm
                JOIN lojas l ON cm.loja_id = l.id
                LEFT JOIN store_balance_payments sbp ON 
                    (cm.loja_id = sbp.loja_id AND 
                    cm.id = sbp.movimentacao_id)
                WHERE cm.tipo_operacao = 'uso'
                AND cm.transacao_uso_id IS NOT NULL
            ";
            
            $stmt = $db->query($query);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Garantir que os valores não sejam nulos
            return [
                'total_lojas' => intval($stats['total_lojas'] ?? 0),
                'total_transacoes' => intval($stats['total_transacoes'] ?? 0),
                'valor_total_pendente' => floatval($stats['valor_total_pendente'] ?? 0),
                'valor_total_pago' => floatval($stats['valor_total_pago'] ?? 0)
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao obter estatísticas de saldo: ' . $e->getMessage());
            // Retornar valores padrão em caso de erro
            return [
                'total_lojas' => 0,
                'total_transacoes' => 0,
                'valor_total_pendente' => 0,
                'valor_total_pago' => 0
            ];
        }
    }
    
    /**
     * Obtém movimentações pendentes para uma loja específica
     * 
     * @param int $lojaId ID da loja
     * @return array Lista de IDs das movimentações pendentes
     */
    public static function getPendingMovimentacoes($lojaId) {
        try {
            // Verificar se o usuário está autenticado e é administrador
            if (!AuthController::isAuthenticated() || !AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            $db = Database::getConnection();
            
            // Buscar movimentações pendentes da loja
            $query = "
                SELECT cm.id
                FROM cashback_movimentacoes cm
                LEFT JOIN store_balance_payments sbp ON cm.pagamento_id = sbp.id
                WHERE cm.loja_id = :loja_id 
                AND cm.tipo_operacao = 'uso'
                AND cm.transacao_uso_id IS NOT NULL
                AND (cm.pagamento_id IS NULL OR sbp.status = 'pendente')
                ORDER BY cm.data_operacao ASC
            ";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':loja_id', $lojaId);
            $stmt->execute();
            
            $movimentacoes = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            return [
                'status' => true,
                'data' => [
                    'movimentacoes' => $movimentacoes
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao obter movimentações pendentes: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao obter movimentações pendentes.'];
        }
    }
    
    /**
     * Obtém detalhes de um pagamento de saldo específico
     * 
     * @param int $paymentId ID do pagamento
     * @return array Detalhes do pagamento
     */
    public static function getBalancePaymentDetails($paymentId) {
        try {
            // Verificar se o usuário está autenticado e é administrador
            if (!AuthController::isAuthenticated() || !AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            $db = Database::getConnection();
            
            // Buscar dados do pagamento
            $paymentStmt = $db->prepare("
                SELECT sbp.*, l.nome_fantasia as loja_nome
                FROM store_balance_payments sbp
                JOIN lojas l ON sbp.loja_id = l.id
                WHERE sbp.id = ?
            ");
            $paymentStmt->execute([$paymentId]);
            $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payment) {
                return ['status' => false, 'message' => 'Pagamento não encontrado.'];
            }
            
            // Buscar transações relacionadas
            $transactionsStmt = $db->prepare("
                SELECT 
                    cm.valor as valor_saldo_usado,
                    cm.data_operacao,
                    t.codigo_transacao,
                    t.valor_total as valor_venda,
                    u.nome as cliente_nome
                FROM cashback_movimentacoes cm
                JOIN transacoes_cashback t ON cm.transacao_uso_id = t.id
                JOIN usuarios u ON cm.usuario_id = u.id
                WHERE cm.pagamento_id = ?
                ORDER BY cm.data_operacao DESC
            ");
            $transactionsStmt->execute([$paymentId]);
            $transactions = $transactionsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'status' => true,
                'data' => [
                    'pagamento' => $payment,
                    'transacoes' => $transactions
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao obter detalhes do pagamento de saldo: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao obter detalhes do pagamento.'];
        }
    }
}

// Processar requisições diretas de acesso ao controlador
if (basename($_SERVER['PHP_SELF']) === 'StoreBalancePaymentController.php') {
    // Verificar se o usuário está autenticado
    if (!AuthController::isAuthenticated()) {
        header('Location: ' . LOGIN_URL . '?error=' . urlencode('Você precisa fazer login para acessar esta página.'));
        exit;
    }
    
    $action = $_REQUEST['action'] ?? '';
    
    switch ($action) {
        case 'get_pending_store_balance_payments':
            $filters = $_POST['filters'] ?? [];
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $result = StoreBalancePaymentController::getPendingStoreBalancePayments($filters, $page);
            echo json_encode($result);
            break;
            
        case 'get_store_balance_details':
            $lojaId = isset($_POST['loja_id']) ? intval($_POST['loja_id']) : 0;
            $filters = $_POST['filters'] ?? [];
            $result = StoreBalancePaymentController::getStoreBalanceDetails($lojaId, $filters);
            echo json_encode($result);
            break;
        case 'get_store_balance_repasse_details':
            $repasseId = isset($_POST['repasse_id']) ? intval($_POST['repasse_id']) : 0;
            $storeId = isset($_POST['store_id']) ? intval($_POST['store_id']) : 0;
            $result = StoreBalancePaymentController::getStoreBalanceRepasseDetails($repasseId, $storeId);
            echo json_encode($result);
            break;
                
        case 'get_pending_movimentacoes':
            $lojaId = isset($_POST['loja_id']) ? intval($_POST['loja_id']) : 0;
            $result = StoreBalancePaymentController::getPendingMovimentacoes($lojaId);
            echo json_encode($result);
            break;
            
        case 'process_store_balance_payment':
            $data = $_POST;
            if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] === UPLOAD_ERR_OK) {
                $data['comprovante'] = $_FILES['comprovante'];
            }
            $result = StoreBalancePaymentController::processStoreBalancePayment($data);
            echo json_encode($result);
            break;
            
        case 'get_balance_payment_details':
            $paymentId = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
            $result = StoreBalancePaymentController::getBalancePaymentDetails($paymentId);
            echo json_encode($result);
            break;
            
        case 'get_balance_statistics':
            $result = StoreBalancePaymentController::getBalanceStatistics();
            echo json_encode(['status' => true, 'data' => $result]);
            break;
            
        default:
            // Acesso inválido ao controlador, redirecionar baseado no tipo de usuário
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