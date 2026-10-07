<?php
// models/CashbackBalance.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../services/Giftback/GiftbackLedger.php';
require_once __DIR__ . '/../services/store/StoreNetworkAccess.php';

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

    private function ledger(): \App\Services\Giftback\GiftbackLedger {
        return new \App\Services\Giftback\GiftbackLedger($this->db);
    }
    
    /**
     * Obtém o saldo disponível de um usuário em uma loja específica
     * 
     * Filiais da mesma rede compartilham disponibilidade; as origens financeiras
     * permanecem separadas e são conciliadas pelo ledger.
     * 
     * @param int $userId ID do usuário
     * @param int $storeId ID da loja
     * @return float Saldo disponível nesta loja específica
     */
    public function getStoreBalance($userId, $storeId) {
        $stores = (new \App\Services\Store\StoreNetworkAccess($this->db))->walletStores((int) $storeId);
        return $this->ledger()->networkWallet((int) $userId, $stores)['availableCents'] / 100;
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
        $this->ledger()->settleUser((int) $userId);
        $stmt = $this->db->prepare("SELECT cs.loja_id,l.nome_fantasia,l.logo,l.categoria,l.porcentagem_cashback,cs.saldo_disponivel FROM cashback_saldos cs JOIN lojas l ON l.id=cs.loja_id WHERE cs.usuario_id=? ORDER BY cs.saldo_disponivel DESC");
        $stmt->execute([(int) $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        $this->ledger()->settleUser((int) $userId);
        $stmt = $this->db->prepare('SELECT COALESCE(SUM(saldo_disponivel),0) FROM cashback_saldos WHERE usuario_id=?');
        $stmt->execute([(int) $userId]);
        return (float) $stmt->fetchColumn();
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
        $this->ledger()->credit((int) $userId, (int) $storeId, \App\Services\Giftback\GiftbackLedger::cents($amount), (string) $description, $transactionId === null ? null : (int) $transactionId);
        return true;
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
        $own = !$this->db->inTransaction();
        if ($own) { $this->db->beginTransaction(); }
        try {
            $access = new \App\Services\Store\StoreNetworkAccess($this->db);
            $stores = $access->walletStores((int) $storeId);
            if (count($stores) > 1) {
                if ($useTransactionId === null) { throw new RuntimeException('Uso na rede requer transação de venda rastreável.'); }
                $result = $this->ledger()->spendNetwork((int) $userId, (int) $storeId, $stores,
                    \App\Services\Giftback\GiftbackLedger::cents($amount), (int) $useTransactionId, null, $access->networkId((int) $storeId));
            } else {
                $result = $this->ledger()->spend((int) $userId, (int) $storeId, \App\Services\Giftback\GiftbackLedger::cents($amount), (string) $description, $useTransactionId === null ? null : (int) $useTransactionId);
                if (!$result['replayed']) { $this->createStoreReimbursementRecord($storeId, $amount, $useTransactionId, $userId); }
            }
            if ($own) { $this->db->commit(); }
            return true;
        } catch (Throwable $error) {
            if ($own && $this->db->inTransaction()) { $this->db->rollBack(); }
            throw $error;
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
            throw $e;
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
        // Historical callers used this for sale cancellation. Both legs must be
        // reversed together: return consumed credits, then revoke the grant.
        if (!$transactionId) { throw new \App\Services\Giftback\GiftbackException('Informe a transação original para estornar o saldo.'); }
        $this->ledger()->reverseSale((int) $userId, (int) $storeId, (int) $transactionId, (string) $description);
        return true;
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
        $this->ledger()->settleWallet((int) $userId, (int) $storeId);
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
        $this->ledger()->settleWallet((int) $userId, (int) $storeId);
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
        // Compatibility endpoint: settling the ledger never rebuilds spent funds.
        if ($userId !== null) { $this->ledger()->settleUser((int) $userId); }
        else {
            foreach ($this->db->query('SELECT DISTINCT usuario_id FROM cashback_saldos ORDER BY usuario_id')->fetchAll(PDO::FETCH_COLUMN) as $id) { $this->ledger()->settleUser((int) $id); }
        }
        return true;
    }
}
?>
