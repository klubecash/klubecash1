<?php
// models/CashbackBalance.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

/**
 * Modelo para gestão de saldo de cashback por loja
 * Controla créditos, usos e histórico do saldo de cada cliente por loja específica
 * 
 * FUNCIONALIDADES PRINCIPAIS:
 * - Gerenciar saldo de cashback por loja
 * - Controlar uso de saldo em compras
 * - Gerar registros de reembolso para lojas automaticamente
 * - Manter histórico completo de movimentações
 */
class CashbackBalance {
    private $db;
    
    public function __construct() {
        $this->db = Database::getConnection();
    }
    
    /**
     * Obtém o saldo disponível de um usuário em uma loja específica
     * 
     * Este método é fundamental pois o cashback no Klube Cash é isolado por loja.
     * Um cliente pode ter R$ 100 na Loja A e R$ 50 na Loja B, mas não pode
     * usar o saldo da Loja A para comprar na Loja B.
     * 
     * @param int $userId ID do usuário
     * @param int $storeId ID da loja
     * @return float Saldo disponível nesta loja específica
     */
    public function getStoreBalance($userId, $storeId) {
        try {
            error_log("DEBUG: Consultando saldo - Usuario: {$userId}, Loja: {$storeId}");
            
            $stmt = $this->db->prepare("
                SELECT saldo_disponivel 
                FROM cashback_saldos 
                WHERE usuario_id = :user_id AND loja_id = :store_id
            ");
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':store_id', $storeId);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $saldo = $result ? floatval($result['saldo_disponivel']) : 0.00;
            
            error_log("DEBUG: Saldo encontrado: R$ {$saldo}");
            return $saldo;
            
        } catch (PDOException $e) {
            error_log('Erro ao obter saldo da loja: ' . $e->getMessage());
            return 0.00;
        }
    }
    
    /**
     * Obtém todos os saldos de um usuário agrupados por loja
     * 
     * Útil para mostrar no dashboard do cliente uma visão completa
     * de todos os seus saldos disponíveis em diferentes lojas.
     * 
     * @param int $userId ID do usuário
     * @return array Saldos detalhados por loja
     */
    public function getAllUserBalances($userId) {
        try {
            error_log("=== BUSCA SALDOS NA TABELA CASHBACK_SALDOS (CORRETO) ===");
            
            // PRIMEIRO: Buscar na tabela cashback_saldos (método correto)
            $stmt = $this->db->prepare("
                SELECT 
                    cs.loja_id,
                    l.nome_fantasia,
                    l.logo,
                    l.categoria,
                    l.porcentagem_cashback,
                    cs.saldo_disponivel
                FROM cashback_saldos cs
                JOIN lojas l ON cs.loja_id = l.id
                WHERE cs.usuario_id = :user_id
                AND cs.saldo_disponivel > 0
                ORDER BY cs.saldo_disponivel DESC
            ");
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            $saldosTabela = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("SALDOS NA TABELA: " . count($saldosTabela));
            
            foreach ($saldosTabela as $index => $saldo) {
                error_log("TABELA[{$index}]: {$saldo['nome_fantasia']} - R$ {$saldo['saldo_disponivel']}");
            }
            
            // Se encontrou na tabela, retornar (método correto)
            if (!empty($saldosTabela)) {
                return $saldosTabela;
            }
            
            // FALLBACK: Buscar nas transações apenas se não tiver na tabela
            error_log("FALLBACK: Buscando nas transações...");
            
            $stmt = $this->db->prepare("
                SELECT 
                    t.loja_id,
                    l.nome_fantasia,
                    l.logo,
                    l.categoria,
                    l.porcentagem_cashback,
                    SUM(CASE WHEN t.status = 'aprovado' THEN t.valor_cliente ELSE 0 END) as saldo_disponivel
                FROM transacoes_cashback t
                INNER JOIN lojas l ON t.loja_id = l.id
                WHERE t.usuario_id = :user_id
                GROUP BY t.loja_id, l.nome_fantasia, l.logo, l.categoria, l.porcentagem_cashback
                HAVING saldo_disponivel > 0
                ORDER BY saldo_disponivel DESC
            ");
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            $saldosTransacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("FALLBACK: " . count($saldosTransacoes) . " lojas nas transações");
            
            return $saldosTransacoes;
            
        } catch (PDOException $e) {
            error_log('ERRO ao obter saldos: ' . $e->getMessage());
            return [];
        }
    }

    /** Obtém os saldos de um usuário APENAS de lojas marcadas como SENAT.
     * @param int $userId ID do usuário
     * @return array Saldos detalhados por loja SENAT
     */
public function getSenatStoreBalances($userId) {
        try {
            error_log("=== BUSCA SALDOS SENAT (CORRIGIDO) ===");
            
            // CORREÇÃO: Adiciona JOIN na tabela 'usuarios' (com alias 'u_loja')
            // para verificar se a loja é Senat.
            // **IMPORTANTE**: Confirme se 'l.id = u_loja.loja_vinculada_id' é a forma correta
            // de ligar a tabela 'lojas' à tabela 'usuarios' (tipo loja)
            $sqlTabela = "
                SELECT
                    cs.loja_id,
                    l.nome_fantasia,
                    l.logo,
                    l.categoria,
                    l.porcentagem_cashback,
                    cs.saldo_disponivel
                FROM cashback_saldos cs
                JOIN lojas l ON cs.loja_id = l.id
                JOIN usuarios u_loja ON l.usuario_id = u_loja.id
                WHERE cs.usuario_id = :user_id       -- Filtra pelo CLIENTE logado
                  AND cs.saldo_disponivel > 0
                  AND u_loja.tipo = 'loja'         -- Garante que o usuário é do tipo loja
                  AND LOWER(u_loja.senat) = 'sim'  -- Garante que a LOJA é Senat (case-insensitive)
                ORDER BY cs.saldo_disponivel DESC
            ";
            
            $stmt = $this->db->prepare($sqlTabela);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $saldosTabela = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("SALDOS SENAT NA TABELA: " . count($saldosTabela));
            
            if (!empty($saldosTabela)) {
                return $saldosTabela;
            }

            // FALLBACK: Buscar nas transações (também com filtro SENAT)
            error_log("FALLBACK SENAT (CORRIGIDO): Buscando nas transações...");
            
            $sqlTransacoes = "
                SELECT
                    t.loja_id,
                    l.nome_fantasia,
                    l.logo,
                    l.categoria,
                    l.porcentagem_cashback,
                    SUM(CASE WHEN t.status = 'aprovado' THEN t.valor_cliente ELSE 0 END) as saldo_disponivel
                FROM transacoes_cashback t
                INNER JOIN lojas l ON t.loja_id = l.id
                INNER JOIN usuarios u_loja ON l.usuario_id = u_loja.id
                WHERE t.usuario_id = :user_id      -- Filtra pelo CLIENTE logado
                  AND u_loja.tipo = 'loja'       -- Garante que o usuário é do tipo loja
                  AND LOWER(u_loja.senat) = 'sim' -- Garante que a LOJA é Senat (case-insensitive)
                GROUP BY t.loja_id, l.nome_fantasia, l.logo, l.categoria, l.porcentagem_cashback
                HAVING saldo_disponivel > 0
                ORDER BY saldo_disponivel DESC
            ";
            
            $stmt = $this->db->prepare($sqlTransacoes);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $saldosTransacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("FALLBACK SENAT: " . count($saldosTransacoes) . " lojas nas transações");
            
            return $saldosTransacoes;
            
        } catch (PDOException $e) {
            error_log('ERRO ao obter saldos SENAT: ' . $e->getMessage());
            // Lança a exceção para o Controller tratar (retornar 500 ou mensagem de erro)
            throw new Exception('Erro de banco de dados ao buscar saldos Senat: ' . $e->getMessage());
        }
    }



    /**
     * ✅ NOVO MÉTODO ADICIONADO
     * Obtém o saldo total consolidado de um usuário APENAS de lojas SENAT.
     *
     * @param int $userId ID do usuário
     * @return float Saldo total consolidado em lojas SENAT
     */
public function getTotalSenatBalance($userId) {
    try {
        // CORREÇÃO: Adiciona o JOIN e os WHEREs para filtrar lojas Senat
        $sql = "
            SELECT SUM(cs.saldo_disponivel) as total
            FROM cashback_saldos cs
            JOIN lojas l ON cs.loja_id = l.id
            JOIN usuarios u_loja ON l.usuario_id = u_loja.id
            WHERE cs.usuario_id = :user_id     -- Filtra pelo CLIENTE logado
              AND u_loja.tipo = 'loja'       -- Garante que o usuário é do tipo loja
              AND LOWER(u_loja.senat) = 'sim' -- Garante que a LOJA é Senat (case-insensitive)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? floatval($result['total']) : 0.00;

    } catch (PDOException $e) {
        error_log('Erro ao obter saldo total SENAT: ' . $e->getMessage());
        throw new Exception('Erro de banco de dados ao buscar saldo total Senat: ' . $e->getMessage());
    }
}
    /**
     * Obtém o saldo total consolidado de um usuário (soma de todas as lojas)
     * 
     * Mesmo que o saldo seja isolado por loja, às vezes é útil mostrar
     * o valor total que o cliente possui no sistema.
     * 
     * @param int $userId ID do usuário
     * @return float Saldo total consolidado
     */
    public function getTotalBalance($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT SUM(saldo_disponivel) as total
                FROM cashback_saldos 
                WHERE usuario_id = :user_id
            ");
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? floatval($result['total']) : 0.00;
            
        } catch (PDOException $e) {
            error_log('Erro ao obter saldo total: ' . $e->getMessage());
            return 0.00;
        }
    }
    
    /**
     * Adiciona saldo de cashback para um usuário em uma loja específica
     * 
     * Este método é chamado quando uma transação é aprovada e o cashback
     * é liberado para o cliente. Utiliza a técnica de INSERT ON DUPLICATE KEY
     * para criar ou atualizar o registro de saldo em uma única operação.
     * 
     * @param int $userId ID do usuário
     * @param int $storeId ID da loja
     * @param float $amount Valor a ser creditado
     * @param string $description Descrição da operação
     * @param int|null $transactionId ID da transação origem
     * @return bool Sucesso da operação
     */
    public function addBalance($userId, $storeId, $amount, $description = '', $transactionId = null) {
        if ($amount <= 0) {
            error_log("CASHBACK: Valor inválido: {$amount}");
            return false;
        }
        
        error_log("CASHBACK: Iniciando addBalance - User: {$userId}, Store: {$storeId}, Amount: {$amount}");
        
        try {
            // Obter saldo atual ANTES de iniciar a transação
            $currentBalance = $this->getStoreBalance($userId, $storeId);
            $newBalance = $currentBalance + $amount;
            
            error_log("CASHBACK: Saldo atual: {$currentBalance}, Novo saldo: {$newBalance}");
            
            // Verificar se já existe transação ativa para evitar transações aninhadas
            $useOwnTransaction = !$this->db->inTransaction();
            if ($useOwnTransaction) {
                $this->db->beginTransaction();
            }
            
            // 1. Atualizar/inserir saldo usando INSERT ON DUPLICATE KEY UPDATE
            // Esta técnica permite criar um novo registro ou atualizar um existente
            // em uma única operação, evitando problemas de concorrência
            $balanceStmt = $this->db->prepare("
                INSERT INTO cashback_saldos (usuario_id, loja_id, saldo_disponivel, total_creditado)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    saldo_disponivel = saldo_disponivel + VALUES(saldo_disponivel),
                    total_creditado = total_creditado + VALUES(total_creditado),
                    ultima_atualizacao = CURRENT_TIMESTAMP
            ");
            
            $balanceResult = $balanceStmt->execute([$userId, $storeId, $amount, $amount]);
            
            if (!$balanceResult) {
                if ($useOwnTransaction) {
                    $this->db->rollBack();
                }
                error_log("CASHBACK: Erro ao atualizar saldo");
                return false;
            }
            
            // 2. Registrar movimentação no histórico
            // Mantemos um log detalhado de todas as operações para auditoria
            $movStmt = $this->db->prepare("
                INSERT INTO cashback_movimentacoes (
                    usuario_id, loja_id, tipo_operacao, valor,
                    saldo_anterior, saldo_atual, descricao,
                    transacao_origem_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $movResult = $movStmt->execute([
                $userId,
                $storeId,
                'credito',
                $amount,
                $currentBalance,
                $newBalance,
                $description,
                $transactionId
            ]);
            
            if (!$movResult) {
                if ($useOwnTransaction) {
                    $this->db->rollBack();
                }
                error_log("CASHBACK: Erro ao registrar movimentação");
                return false;
            }
            
            // Commit da transação (apenas se for transação própria)
            if ($useOwnTransaction) {
                $this->db->commit();
            }
            error_log("CASHBACK: Saldo creditado com sucesso - Novo saldo: {$newBalance}");
            return true;
            
        } catch (Exception $e) {
            // Rollback em caso de erro (apenas se for transação própria)
            if (isset($useOwnTransaction) && $useOwnTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('CASHBACK: Erro ao adicionar saldo: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Usa saldo de cashback em uma compra na loja específica
     * 
     * CORREÇÃO PRINCIPAL: Este método agora também cria automaticamente
     * um registro de reembolso pendente para a loja, resolvendo o problema
     * onde os pagamentos de saldo às lojas não apareciam.
     * 
     * FLUXO COMPLETO:
     * 1. Verifica se há saldo suficiente
     * 2. Debita o saldo do cliente
     * 3. Registra a movimentação no histórico
     * 4. NOVO: Cria registro de reembolso para a loja
     * 
     * @param int $userId ID do usuário
     * @param int $storeId ID da loja
     * @param float $amount Valor a ser usado
     * @param string $description Descrição da operação
     * @param int|null $useTransactionId ID da transação de uso
     * @return bool Sucesso da operação
     */
    public function useBalance($userId, $storeId, $amount, $description = '', $useTransactionId = null) {
        if ($amount <= 0) {
            error_log("CASHBACK USE: Valor inválido: {$amount}");
            return false;
        }
        
        error_log("CASHBACK USE: Iniciando useBalance - User: {$userId}, Store: {$storeId}, Amount: {$amount}");
        
        try {
            // Verificar saldo disponível ANTES de qualquer operação
            $currentBalance = $this->getStoreBalance($userId, $storeId);
            
            if ($currentBalance < $amount) {
                error_log("CASHBACK USE: Saldo insuficiente - Disponível: {$currentBalance}, Solicitado: {$amount}");
                return false;
            }
            
            $newBalance = $currentBalance - $amount;
            error_log("CASHBACK USE: Saldo atual: {$currentBalance}, Novo saldo: {$newBalance}");
            
            // Detectar se já estamos em uma transação (importante para compatibilidade)
            $isInTransaction = $this->db->inTransaction();
            
            // Se não estamos em transação, iniciar uma
            if (!$isInTransaction) {
                $this->db->beginTransaction();
            }
            
            try {
                // 1. Debitar saldo do cliente
                $updateStmt = $this->db->prepare("
                    UPDATE cashback_saldos 
                    SET saldo_disponivel = saldo_disponivel - ?,
                        total_usado = total_usado + ?,
                        ultima_atualizacao = CURRENT_TIMESTAMP
                    WHERE usuario_id = ? AND loja_id = ?
                ");
                
                $updateResult = $updateStmt->execute([$amount, $amount, $userId, $storeId]);
                
                if (!$updateResult) {
                    error_log("CASHBACK USE: Erro no UPDATE do saldo");
                    throw new Exception('Erro no UPDATE do saldo');
                }
                
                $rowsAffected = $updateStmt->rowCount();
                if ($rowsAffected == 0) {
                    error_log("CASHBACK USE: Nenhuma linha foi atualizada");
                    throw new Exception('Nenhuma linha foi atualizada');
                }
                
                error_log("CASHBACK USE: UPDATE executado - {$rowsAffected} linhas afetadas");
                
                // 2. Registrar movimentação no histórico
                $movStmt = $this->db->prepare("
                    INSERT INTO cashback_movimentacoes (
                        usuario_id, loja_id, tipo_operacao, valor,
                        saldo_anterior, saldo_atual, descricao,
                        transacao_uso_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $movResult = $movStmt->execute([
                    $userId,
                    $storeId,
                    'uso',
                    $amount,
                    $currentBalance,
                    $newBalance,
                    $description,
                    $useTransactionId
                ]);
                
                if (!$movResult) {
                    error_log("CASHBACK USE: Erro ao registrar movimentação");
                    throw new Exception('Erro ao registrar movimentação');
                }
                
                error_log("CASHBACK USE: Movimentação registrada com sucesso");
                
                // 3. CORREÇÃO PRINCIPAL: Criar registro de reembolso para a loja
                // Esta é a funcionalidade que estava faltando!
                $this->createStoreReimbursementRecord($storeId, $amount, $useTransactionId, $userId);
                
                // Se iniciamos a transação, fazer commit
                if (!$isInTransaction) {
                    $this->db->commit();
                }
                
                error_log("CASHBACK USE: Saldo debitado com sucesso - Novo saldo: {$newBalance}");
                return true;
                
            } catch (Exception $e) {
                // Se iniciamos a transação, fazer rollback
                if (!$isInTransaction && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $e;
            }
            
        } catch (Exception $e) {
            error_log('CASHBACK USE: Erro ao debitar saldo: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * MÉTODO NOVO: Cria registro de reembolso pendente para a loja
     * 
     * Este é o método que resolve o problema principal! Quando um cliente
     * usa saldo, a loja precisa receber o reembolso da plataforma, pois
     * efetivamente a loja está "perdendo" esse valor na venda.
     * 
     * EXEMPLO PRÁTICO:
     * - Cliente compra R$ 1000, usa R$ 50 de saldo
     * - Cliente paga apenas R$ 950 para a loja
     * - Loja deve receber R$ 50 de reembolso da plataforma
     * - Este método cria esse registro de R$ 50 pendente
     * 
     * @param int $storeId ID da loja que deve receber o reembolso
     * @param float $amount Valor a ser reembolsado
     * @param int|null $transactionId ID da transação onde o saldo foi usado
     * @param int $userId ID do cliente que usou o saldo
     * @return void
     */
    private function createStoreReimbursementRecord($storeId, $amount, $transactionId, $userId) {
        try {
            error_log("REEMBOLSO: Criando registro - Loja: {$storeId}, Valor: {$amount}");
            
            // Verificar se já existe um registro pendente para esta loja
            // Isso permite agrupar múltiplos usos de saldo em um único pagamento
            $checkStmt = $this->db->prepare("
                SELECT id, valor_total FROM store_balance_payments 
                WHERE loja_id = ? AND status = 'pendente'
                ORDER BY data_criacao DESC LIMIT 1
            ");
            $checkStmt->execute([$storeId]);
            $existingPayment = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingPayment) {
                // Se já existe um pagamento pendente, somar o valor
                // Isso é mais eficiente que criar múltiplos pagamentos pequenos
                $updateStmt = $this->db->prepare("
                    UPDATE store_balance_payments 
                    SET valor_total = valor_total + ?,
                        observacao = CONCAT(COALESCE(observacao, ''), '\nReembolso adicional - Transação #', ?)
                    WHERE id = ?
                ");
                $updateStmt->execute([$amount, $transactionId, $existingPayment['id']]);
                
                $paymentId = $existingPayment['id'];
                error_log("REEMBOLSO: Valor adicionado ao pagamento existente - ID: {$paymentId}, Novo total: " . ($existingPayment['valor_total'] + $amount));
            } else {
                // Criar novo registro de pagamento pendente
                $insertStmt = $this->db->prepare("
                    INSERT INTO store_balance_payments 
                    (loja_id, valor_total, metodo_pagamento, observacao, status, data_criacao)
                    VALUES (?, ?, 'reembolso_saldo', ?, 'pendente', NOW())
                ");
                
                $observacao = "Reembolso de saldo usado pelo cliente - Transação #{$transactionId}";
                $insertStmt->execute([$storeId, $amount, $observacao]);
                
                $paymentId = $this->db->lastInsertId();
                error_log("REEMBOLSO: Novo registro criado - ID: {$paymentId}, Valor: {$amount}");
            }
            
            // Vincular a movimentação de uso ao pagamento
            // Isso permite rastrear quais usos de saldo estão incluídos em cada pagamento
            $updateMovStmt = $this->db->prepare("
                UPDATE cashback_movimentacoes 
                SET pagamento_id = ?
                WHERE transacao_uso_id = ? AND usuario_id = ? AND loja_id = ? AND tipo_operacao = 'uso'
                ORDER BY data_operacao DESC LIMIT 1
            ");
            $updateMovStmt->execute([$paymentId, $transactionId, $userId, $storeId]);
            
            error_log("REEMBOLSO: Movimentação vinculada ao pagamento {$paymentId}");
            
        } catch (Exception $e) {
            error_log('REEMBOLSO: Erro ao criar registro - ' . $e->getMessage());
            // IMPORTANTE: Não falhar a transação principal por causa de erro no reembolso
            // O uso do saldo é mais crítico que o registro do reembolso
        }
    }
    
    /**
     * Estorna o uso de saldo (reverter operação de uso)
     * 
     * Útil quando uma transação é cancelada e precisamos devolver
     * o saldo que foi usado pelo cliente.
     * 
     * @param int $userId ID do usuário
     * @param int $storeId ID da loja
     * @param float $amount Valor a ser estornado
     * @param string $description Descrição da operação
     * @param int|null $transactionId ID da transação relacionada
     * @return bool Sucesso da operação
     */
    public function refundBalance($userId, $storeId, $amount, $description = '', $transactionId = null) {
        if ($amount <= 0) {
            return false;
        }
        
        try {
            $this->db->beginTransaction();
            
            // Obter saldo atual
            $currentBalance = $this->getStoreBalance($userId, $storeId);
            $newBalance = $currentBalance + $amount;
            
            // Atualizar saldo (adicionar de volta o valor estornado)
            $stmt = $this->db->prepare("
                UPDATE cashback_saldos 
                SET saldo_disponivel = saldo_disponivel + :amount,
                    total_usado = total_usado - :amount,
                    ultima_atualizacao = CURRENT_TIMESTAMP
                WHERE usuario_id = :user_id AND loja_id = :store_id
            ");
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':store_id', $storeId);
            $stmt->bindParam(':amount', $amount);
            
            if (!$stmt->execute()) {
                throw new Exception('Erro ao atualizar saldo');
            }
            
            // Registrar movimentação de estorno
            $this->recordMovement($userId, $storeId, 'estorno', $amount, $currentBalance, $newBalance, $description, null, $transactionId);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Erro ao estornar saldo: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registra uma movimentação no histórico
     * 
     * Método auxiliar para manter consistência no registro de movimentações.
     * Todas as operações (crédito, uso, estorno) são registradas aqui.
     * 
     * @param int $userId
     * @param int $storeId
     * @param string $type Tipo da operação (credito, uso, estorno)
     * @param float $amount Valor da operação
     * @param float $previousBalance Saldo anterior
     * @param float $newBalance Novo saldo após a operação
     * @param string $description Descrição da operação
     * @param int|null $originTransactionId ID da transação origem (para créditos)
     * @param int|null $useTransactionId ID da transação de uso (para débitos)
     * @return bool Sucesso da operação
     */
    private function recordMovement($userId, $storeId, $type, $amount, $previousBalance, $newBalance, $description = '', $originTransactionId = null, $useTransactionId = null) {
        try {
            error_log("CASHBACK DEBUG: recordMovement - User: $userId, Store: $storeId, Type: $type, Amount: $amount");
            
            $stmt = $this->db->prepare("
                INSERT INTO cashback_movimentacoes (
                    usuario_id, loja_id, tipo_operacao, valor,
                    saldo_anterior, saldo_atual, descricao,
                    transacao_origem_id, transacao_uso_id
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?
                )
            ");
            
            $result = $stmt->execute([
                $userId,
                $storeId, 
                $type,
                $amount,
                $previousBalance,
                $newBalance,
                $description,
                $originTransactionId,
                $useTransactionId
            ]);
            
            if ($result) {
                error_log("CASHBACK DEBUG: Movimentação registrada com sucesso");
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("CASHBACK DEBUG: Erro ao registrar movimentação: " . json_encode($errorInfo));
            }
            
            return $result;
            
        } catch (PDOException $e) {
            error_log('CASHBACK DEBUG: Exceção ao registrar movimentação: ' . $e->getMessage());
            return true; // Não falhar por causa de erro no log
        }
    }
    
    /**
     * Obtém o histórico de movimentações de um usuário em uma loja
     * 
     * Retorna um histórico detalhado com informações das transações
     * relacionadas para facilitar a auditoria e o entendimento do cliente.
     * 
     * @param int $userId ID do usuário
     * @param int $storeId ID da loja
     * @param int $limit Limite de registros
     * @param int $offset Offset para paginação
     * @return array Histórico de movimentações
     */
    public function getMovementHistory($userId, $storeId, $limit = 50, $offset = 0) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    cm.*,
                    to_table.codigo_transacao as transacao_origem_codigo,
                    to_table.valor_total as transacao_origem_valor,
                    to_table.data_transacao as transacao_origem_data,
                    tu_table.codigo_transacao as transacao_uso_codigo,
                    tu_table.valor_total as transacao_uso_valor,
                    tu_table.data_transacao as transacao_uso_data
                FROM cashback_movimentacoes cm
                LEFT JOIN transacoes_cashback to_table ON cm.transacao_origem_id = to_table.id
                LEFT JOIN transacoes_cashback tu_table ON cm.transacao_uso_id = tu_table.id
                WHERE cm.usuario_id = :user_id AND cm.loja_id = :store_id
                ORDER BY cm.data_operacao DESC
                LIMIT :limit OFFSET :offset
            ");
            
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':store_id', $storeId);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log('Erro ao obter histórico: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtém estatísticas de uso do cashback por loja
     * 
     * Fornece informações úteis para análise do comportamento
     * do cliente e performance do sistema de cashback.
     * 
     * @param int $userId ID do usuário
     * @param int $storeId ID da loja
     * @return array Estatísticas detalhadas
     */
    public function getBalanceStatistics($userId, $storeId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    cs.*,
                    COUNT(cm.id) as total_movimentacoes,
                    MAX(cm.data_operacao) as ultima_movimentacao,
                    SUM(CASE WHEN cm.tipo_operacao = 'credito' THEN cm.valor ELSE 0 END) as total_creditado_historico,
                    SUM(CASE WHEN cm.tipo_operacao = 'uso' THEN cm.valor ELSE 0 END) as total_usado_historico,
                    AVG(CASE WHEN cm.tipo_operacao = 'credito' THEN cm.valor ELSE NULL END) as media_credito,
                    AVG(CASE WHEN cm.tipo_operacao = 'uso' THEN cm.valor ELSE NULL END) as media_uso
                FROM cashback_saldos cs
                LEFT JOIN cashback_movimentacoes cm ON cs.usuario_id = cm.usuario_id AND cs.loja_id = cm.loja_id
                WHERE cs.usuario_id = :user_id AND cs.loja_id = :store_id
                GROUP BY cs.id
            ");
            
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':store_id', $storeId);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            
        } catch (PDOException $e) {
            error_log('Erro ao obter estatísticas: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Sincroniza saldos com base nas transações aprovadas
     * 
     * Método útil para correções ou migrações de dados.
     * Recalcula todos os saldos baseado nas transações efetivamente aprovadas.
     * 
     * @param int|null $userId ID do usuário específico (null para todos)
     * @return bool Sucesso da operação
     */
    public function syncBalancesFromTransactions($userId = null) {
        try {
            $this->db->beginTransaction();
            
            // Query para recalcular saldos baseado em transações aprovadas
            $whereClause = $userId ? "WHERE t.usuario_id = :user_id" : "";
            
            $stmt = $this->db->prepare("
                INSERT INTO cashback_saldos (usuario_id, loja_id, saldo_disponivel, total_creditado)
                SELECT 
                    t.usuario_id,
                    t.loja_id,
                    SUM(t.valor_cliente) as saldo_disponivel,
                    SUM(t.valor_cliente) as total_creditado
                FROM transacoes_cashback t
                $whereClause
                AND t.status = 'aprovado'
                GROUP BY t.usuario_id, t.loja_id
                ON DUPLICATE KEY UPDATE
                    saldo_disponivel = VALUES(saldo_disponivel),
                    total_creditado = VALUES(total_creditado),
                    ultima_atualizacao = CURRENT_TIMESTAMP
            ");
            
            if ($userId) {
                $stmt->bindParam(':user_id', $userId);
            }
            
            $stmt->execute();
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Erro ao sincronizar saldos: ' . $e->getMessage());
            return false;
        }
    }
}
?>