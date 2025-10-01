<?php
// models/Transaction.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

/**
 * Classe Transaction - Modelo de Transação de Cashback
 * 
 * Esta classe representa uma transação de cashback no sistema Klube Cash
 * e contém métodos para gerenciar transações e distribuição de valores.
 */
class Transaction {
    // Propriedades da transação
    private $id;
    private $usuarioId;
    private $lojaId;
    private $valorTotal;
    private $valorCashback;
    private $valorCliente;
    private $valorAdmin;
    private $valorLoja;
    private $dataTransacao;
    private $status;
    
    // Conexão com o banco de dados
    private $db;
    
    /**
     * Construtor da classe
     * 
     * @param int $id ID da transação (opcional)
     */
    public function __construct($id = null) {
        $this->db = Database::getConnection();
        
        if ($id) {
            $this->id = $id;
            $this->loadTransactionData();
        } else {
            // Valores padrão para nova transação
            $this->dataTransacao = date('Y-m-d H:i:s');
            $this->status = TRANSACTION_PENDING;
        }
    }
    
    /**
     * Carrega os dados da transação do banco de dados
     * 
     * @return bool Verdadeiro se a transação foi encontrada
     */
    private function loadTransactionData() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM transacoes_cashback WHERE id = :id");
            $stmt->bindParam(':id', $this->id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $transactionData = $stmt->fetch(PDO::FETCH_ASSOC);
                $this->usuarioId = $transactionData['usuario_id'];
                $this->lojaId = $transactionData['loja_id'];
                $this->valorTotal = $transactionData['valor_total'];
                $this->valorCashback = $transactionData['valor_cashback'];
                $this->valorCliente = $transactionData['valor_cliente'];
                $this->valorAdmin = $transactionData['valor_admin'];
                $this->valorLoja = $transactionData['valor_loja'];
                $this->dataTransacao = $transactionData['data_transacao'];
                $this->status = $transactionData['status'];
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log('Erro ao carregar dados da transação: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
    * Calcula a distribuição do cashback entre cliente, admin e loja
    * 
    * @param float $porcentagemTotal Porcentagem total de cashback
    * @param float $porcentagemCliente Porcentagem destinada ao cliente
    * @param float $porcentagemAdmin Porcentagem destinada ao admin
    * @param float $porcentagemLoja Porcentagem destinada à loja
    * @return bool Verdadeiro se o cálculo foi realizado com sucesso
    */
    public function calcularDistribuicao($porcentagemTotal = null, $porcentagemCliente = null, $porcentagemAdmin = null, $porcentagemLoja = null) {
        try {
            // Se nenhuma porcentagem específica foi informada, usa as configurações padrão
            if ($porcentagemTotal === null || $porcentagemCliente === null || 
                $porcentagemAdmin === null || $porcentagemLoja === null) {
                
                // Obter configurações de cashback do sistema
                $stmt = $this->db->query("SELECT * FROM configuracoes_cashback ORDER BY id DESC LIMIT 1");
                $config = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $porcentagemTotal = DEFAULT_CASHBACK_TOTAL;
                $porcentagemCliente = isset($config['porcentagem_cliente']) ? $config['porcentagem_cliente'] : DEFAULT_CASHBACK_CLIENT;
                $porcentagemAdmin = isset($config['porcentagem_admin']) ? $config['porcentagem_admin'] : DEFAULT_CASHBACK_ADMIN;
                $porcentagemLoja = 0.00; // Loja sempre recebe 0%
            }
            
            // Calcular valor total de cashback
            $this->valorCashback = round($this->valorTotal * ($porcentagemTotal / 100), 2);
            
            // Calcular a distribuição
            $this->valorCliente = round($this->valorTotal * ($porcentagemCliente / 100), 2);
            $this->valorAdmin = round($this->valorTotal * ($porcentagemAdmin / 100), 2);
            $this->valorLoja = 0.00; // Loja não recebe cashback
            
            return true;
        } catch (Exception $e) {
            error_log('Erro ao calcular distribuição de cashback: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Salva a transação no banco de dados
     * 
     * @return bool Verdadeiro se os dados foram salvos com sucesso
     */
    public function save() {
        try {
            // === MARCADOR DE TRACE: Início do processo de salvamento ===
            if (file_exists('trace-integration.php')) {
                error_log("[TRACE] Transaction::save() - Iniciando salvamento. Status: {$this->status}, ID atual: " . ($this->id ?: 'novo'), 3, 'integration_trace.log');
            }
            
            // Validação básica
            if ($this->valorTotal < MIN_TRANSACTION_VALUE) {
                throw new Exception("Valor mínimo para transação é de R$ " . MIN_TRANSACTION_VALUE);
            }
            
            if (!$this->usuarioId || !$this->lojaId) {
                throw new Exception("Usuário e loja são obrigatórios");
            }
            
            // Se não tiver calculado a distribuição, calcular com valores padrão
            if ($this->valorCashback === null) {
                $this->calcularDistribuicao();
            }
            
            // === MARCADOR DE TRACE: Dados validados e preparados ===
            if (file_exists('trace-integration.php')) {
                error_log("[TRACE] Transaction::save() - Dados validados. Usuario: {$this->usuarioId}, Loja: {$this->lojaId}, Valor: {$this->valorTotal}", 3, 'integration_trace.log');
            }
            
            // Se já existe um ID, atualizar o registro
            if ($this->id) {
                if (file_exists('trace-integration.php')) {
                    error_log("[TRACE] Transaction::save() - Atualizando transação existente ID: {$this->id}", 3, 'integration_trace.log');
                }
                
                $stmt = $this->db->prepare("
                    UPDATE transacoes_cashback 
                    SET usuario_id = :usuario_id, 
                        loja_id = :loja_id,
                        valor_total = :valor_total,
                        valor_cashback = :valor_cashback,
                        valor_cliente = :valor_cliente,
                        valor_admin = :valor_admin,
                        valor_loja = :valor_loja,
                        data_transacao = :data_transacao,
                        status = :status
                    WHERE id = :id
                ");
                $stmt->bindParam(':id', $this->id);
            } else {
                if (file_exists('trace-integration.php')) {
                    error_log("[TRACE] Transaction::save() - Criando nova transação", 3, 'integration_trace.log');
                }
                
                // Caso contrário, inserir novo registro
                $stmt = $this->db->prepare("
                    INSERT INTO transacoes_cashback (
                        usuario_id, loja_id, valor_total, valor_cashback,
                        valor_cliente, valor_admin, valor_loja, 
                        data_transacao, status
                    ) VALUES (
                        :usuario_id, :loja_id, :valor_total, :valor_cashback,
                        :valor_cliente, :valor_admin, :valor_loja,
                        :data_transacao, :status
                    )
                ");
            }
            
            // Bind dos parâmetros
            $stmt->bindParam(':usuario_id', $this->usuarioId);
            $stmt->bindParam(':loja_id', $this->lojaId);
            $stmt->bindParam(':valor_total', $this->valorTotal);
            $stmt->bindParam(':valor_cashback', $this->valorCashback);
            $stmt->bindParam(':valor_cliente', $this->valorCliente);
            $stmt->bindParam(':valor_admin', $this->valorAdmin);
            $stmt->bindParam(':valor_loja', $this->valorLoja);
            $stmt->bindParam(':data_transacao', $this->dataTransacao);
            $stmt->bindParam(':status', $this->status);
            
            $result = $stmt->execute();
            
            // === MARCADOR DE TRACE: Query executada ===
            if (file_exists('trace-integration.php')) {
                error_log("[TRACE] Transaction::save() - Query executada. Resultado: " . ($result ? 'SUCESSO' : 'FALHA'), 3, 'integration_trace.log');
            }
            
            // Se for um novo registro, obter o ID gerado
            if (!$this->id && $result) {
                $this->id = $this->db->lastInsertId();
                
                // === MARCADOR DE TRACE: ID gerado ===
                if (file_exists('trace-integration.php')) {
                    error_log("[TRACE] Transaction::save() - Novo ID gerado: {$this->id}. Status da transação: {$this->status}", 3, 'integration_trace.log');
                }
                
                // === INTEGRAÇÃO AUTOMÁTICA: Sistema de Notificação Corrigido ===
                // Disparar notificação para transações pendentes E aprovadas (novas transações)
                if ($this->status === TRANSACTION_PENDING || $this->status === TRANSACTION_APPROVED) {
                    try {
                        error_log("[FIXED] Transaction::save() - Disparando notificação para nova transação ID: {$this->id}, status: {$this->status}");

                        require_once __DIR__ . '/../classes/FixedBrutalNotificationSystem.php';
                        $notificationSystem = new FixedBrutalNotificationSystem();
                        $result = $notificationSystem->forceNotifyTransaction($this->id);

                        if ($result['success']) {
                            error_log("[FIXED] Transaction::save() - Notificação enviada com sucesso: " . $result['message']);
                        } else {
                            error_log("[FIXED] Transaction::save() - Falha na notificação: " . $result['message']);
                        }

                    } catch (Exception $e) {
                        error_log("[FIXED] Transaction::save() - Erro na notificação para transação {$this->id}: " . $e->getMessage());
                        // Não quebrar o fluxo principal se a notificação falhar
                    }
                }
            } else if ($this->id) {
                if (file_exists('trace-integration.php')) {
                    error_log("[TRACE] Transaction::save() - Transação atualizada (não enviando notificação para update)", 3, 'integration_trace.log');
                }
            }
            
            if (file_exists('trace-integration.php')) {
                error_log("[TRACE] Transaction::save() - Processo concluído com sucesso. ID final: {$this->id}", 3, 'integration_trace.log');
            }
            
            return $result;
        } catch (Exception $e) {
            if (file_exists('trace-integration.php')) {
                error_log("[TRACE] Transaction::save() - ERRO FATAL: " . $e->getMessage(), 3, 'integration_trace.log');
            }
            error_log('Erro ao salvar transação: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Aprova a transação
     * 
     * @return bool Verdadeiro se aprovada com sucesso
     */
    public function aprovar() {
        if ($this->status !== TRANSACTION_PENDING && $this->status !== TRANSACTION_PAYMENT_PENDING) {
            error_log("Tentativa de aprovar transação com status inválido: " . $this->status);
            return false;
        }
        
        try {
            $this->db->beginTransaction();
            
            // 1. Primeiro, aprovar a transação
            $this->status = TRANSACTION_APPROVED;
            if (!$this->save()) {
                throw new Exception('Erro ao salvar transação');
            }
            
            // 2. Creditar cashback no saldo do cliente
            require_once __DIR__ . '/CashbackBalance.php';
            $balanceModel = new CashbackBalance();
            
            $description = "Cashback da compra - Transação #" . $this->id . " (" . ($this->codigo_transacao ?: 'Sem código') . ")";
            
            // Debug: Log da operação
            error_log("Creditando saldo - Usuario: {$this->usuarioId}, Loja: {$this->lojaId}, Valor: {$this->valorCliente}");
            
            $creditResult = $balanceModel->addBalance(
                $this->usuarioId, 
                $this->lojaId, 
                $this->valorCliente, 
                $description, 
                $this->id
            );
            
            if (!$creditResult) {
                error_log("Erro ao creditar cashback no saldo - Transação: " . $this->id);
                throw new Exception('Erro ao creditar cashback no saldo');
            }
            
            // 3. Log de sucesso
            error_log("Transação aprovada com sucesso - ID: {$this->id}, Cashback creditado: {$this->valorCliente}");
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Erro ao aprovar transação: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Cancela a transação
     * 
     * @return bool Verdadeiro se cancelada com sucesso
     */
    public function cancelar() {
    if ($this->status === TRANSACTION_CANCELED) {
        return false;
    }
    
    try {
        $this->db->beginTransaction();
        
        // Se a transação estava aprovada, estornar cashback
        if ($this->status === TRANSACTION_APPROVED) {
            require_once __DIR__ . '/CashbackBalance.php';
            $balanceModel = new CashbackBalance();
            
            $description = "Estorno - Cancelamento da transação " . $this->id;
            $balanceModel->refundBalance($this->usuarioId, $this->lojaId, $this->valorCliente, $description, $this->id);
        }
        
        // Cancelar a transação
        $this->status = TRANSACTION_CANCELED;
        if (!$this->save()) {
            throw new Exception('Erro ao salvar transação cancelada');
        }
        
        $this->db->commit();
        return true;
        
    } catch (Exception $e) {
        $this->db->rollBack();
        error_log('Erro ao cancelar transação: ' . $e->getMessage());
        return false;
    }
}
    
    /**
     * Obtém detalhes completos da transação incluindo dados da loja e usuário
     * 
     * @return array Dados detalhados da transação
     */
    public function getDetalhesCompletos() {
        try {
            $query = "
                SELECT t.*, 
                       u.nome as nome_usuario, u.email as email_usuario,
                       l.nome_fantasia as nome_loja, l.email as email_loja
                FROM transacoes_cashback t
                JOIN usuarios u ON t.usuario_id = u.id
                JOIN lojas l ON t.loja_id = l.id
                WHERE t.id = :id
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $this->id);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Erro ao obter detalhes da transação: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Lista transações com filtros opcionais
     * 
     * @param array $filters Filtros para a listagem
     * @param int $page Página atual
     * @param int $perPage Itens por página
     * @return array Lista de transações e informações de paginação
     */
    public static function getAll($filters = [], $page = 1, $perPage = null) {
        try {
            if ($perPage === null) {
                $perPage = ITEMS_PER_PAGE;
            }
            
            $db = Database::getConnection();
            
            // Construir consulta base
            $query = "
                SELECT t.*, 
                       u.nome as nome_usuario,
                       l.nome_fantasia as nome_loja
                FROM transacoes_cashback t
                JOIN usuarios u ON t.usuario_id = u.id
                JOIN lojas l ON t.loja_id = l.id
                WHERE 1=1
            ";
            
            $params = [];
            
            // Aplicar filtros
            if (!empty($filters)) {
                if (isset($filters['usuario_id']) && $filters['usuario_id']) {
                    $query .= " AND t.usuario_id = :usuario_id";
                    $params[':usuario_id'] = $filters['usuario_id'];
                }
                
                if (isset($filters['loja_id']) && $filters['loja_id']) {
                    $query .= " AND t.loja_id = :loja_id";
                    $params[':loja_id'] = $filters['loja_id'];
                }
                
                if (isset($filters['status']) && $filters['status']) {
                    $query .= " AND t.status = :status";
                    $params[':status'] = $filters['status'];
                }
                
                if (isset($filters['data_inicio']) && $filters['data_inicio']) {
                    $query .= " AND t.data_transacao >= :data_inicio";
                    $params[':data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
                }
                
                if (isset($filters['data_fim']) && $filters['data_fim']) {
                    $query .= " AND t.data_transacao <= :data_fim";
                    $params[':data_fim'] = $filters['data_fim'] . ' 23:59:59';
                }
                
                if (isset($filters['valor_minimo']) && $filters['valor_minimo']) {
                    $query .= " AND t.valor_total >= :valor_minimo";
                    $params[':valor_minimo'] = $filters['valor_minimo'];
                }
                
                if (isset($filters['valor_maximo']) && $filters['valor_maximo']) {
                    $query .= " AND t.valor_total <= :valor_maximo";
                    $params[':valor_maximo'] = $filters['valor_maximo'];
                }
            }
            
            // Calcular total para paginação
            $countQuery = str_replace("t.*, u.nome as nome_usuario, l.nome_fantasia as nome_loja", "COUNT(*) as total", $query);
            $countStmt = $db->prepare($countQuery);
            foreach ($params as $param => $value) {
                $countStmt->bindValue($param, $value);
            }
            $countStmt->execute();
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Adicionar ordenação e paginação
            $query .= " ORDER BY t.data_transacao DESC";
            $offset = ($page - 1) * $perPage;
            $query .= " LIMIT $offset, $perPage";
            
            // Executar consulta
            $stmt = $db->prepare($query);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            $stmt->execute();
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular páginas
            $totalPages = ceil($totalCount / $perPage);
            
            return [
                'transacoes' => $transactions,
                'paginacao' => [
                    'total' => $totalCount,
                    'por_pagina' => $perPage,
                    'pagina_atual' => $page,
                    'total_paginas' => $totalPages
                ]
            ];
        } catch (PDOException $e) {
            error_log('Erro ao listar transações: ' . $e->getMessage());
            return [
                'transacoes' => [],
                'paginacao' => [
                    'total' => 0,
                    'por_pagina' => $perPage,
                    'pagina_atual' => $page,
                    'total_paginas' => 0
                ]
            ];
        }
    }
    
    /**
     * Calcula estatísticas de transações
     * 
     * @param array $filters Filtros para as estatísticas
     * @return array Estatísticas calculadas
     */
    public static function getStatistics($filters = []) {
        try {
            $db = Database::getConnection();
            
            // Construir consulta base
            $query = "
                SELECT 
                    COUNT(*) as total_transacoes,
                    SUM(valor_total) as total_vendas,
                    SUM(valor_cashback) as total_cashback,
                    SUM(valor_cliente) as total_cliente,
                    SUM(valor_admin) as total_admin,
                    SUM(valor_loja) as total_loja,
                    AVG(valor_total) as media_venda,
                    MAX(valor_total) as maior_venda,
                    MIN(valor_total) as menor_venda
                FROM transacoes_cashback
                WHERE status = :status
            ";
            
            $params = [':status' => TRANSACTION_APPROVED];
            
            // Aplicar filtros
            if (!empty($filters)) {
                if (isset($filters['usuario_id']) && $filters['usuario_id']) {
                    $query .= " AND usuario_id = :usuario_id";
                    $params[':usuario_id'] = $filters['usuario_id'];
                }
                
                if (isset($filters['loja_id']) && $filters['loja_id']) {
                    $query .= " AND loja_id = :loja_id";
                    $params[':loja_id'] = $filters['loja_id'];
                }
                
                if (isset($filters['data_inicio']) && $filters['data_inicio']) {
                    $query .= " AND data_transacao >= :data_inicio";
                    $params[':data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
                }
                
                if (isset($filters['data_fim']) && $filters['data_fim']) {
                    $query .= " AND data_transacao <= :data_fim";
                    $params[':data_fim'] = $filters['data_fim'] . ' 23:59:59';
                }
            }
            
            // Executar consulta
            $stmt = $db->prepare($query);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Adicionar estatísticas por período
            if (empty($filters['por_periodo'])) {
                return $stats;
            }
            
            // Estatísticas por período (dia, mês, ano)
            $periodQuery = "
                SELECT 
                    DATE_FORMAT(data_transacao, :format) as periodo,
                    COUNT(*) as total_transacoes,
                    SUM(valor_total) as total_vendas,
                    SUM(valor_cashback) as total_cashback
                FROM transacoes_cashback
                WHERE status = :status
            ";
            
            // Aplicar os mesmos filtros da consulta anterior
            foreach ($params as $param => $value) {
                if ($param != ':status') {
                    $periodQuery .= " AND " . str_replace(':', '', $param) . " = " . $param;
                }
            }
            
            // Definir formato do período
            $format = '%Y-%m-%d'; // Padrão: diário
            if ($filters['por_periodo'] === 'mes') {
                $format = '%Y-%m';
            } elseif ($filters['por_periodo'] === 'ano') {
                $format = '%Y';
            }
            
            $periodQuery .= " GROUP BY periodo ORDER BY periodo";
            
            $periodStmt = $db->prepare($periodQuery);
            $periodStmt->bindValue(':format', $format);
            $periodStmt->bindValue(':status', TRANSACTION_APPROVED);
            
            foreach ($params as $param => $value) {
                if ($param != ':status') {
                    $periodStmt->bindValue($param, $value);
                }
            }
            
            $periodStmt->execute();
            $stats['por_periodo'] = $periodStmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $stats;
        } catch (PDOException $e) {
            error_log('Erro ao obter estatísticas: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtém o saldo total de cashback de um usuário
     * 
     * @param int $usuarioId ID do usuário
     * @return float Saldo total
     */
    public static function getSaldoUsuario($usuarioId) {
        try {
            $db = Database::getConnection();
            
            $query = "
                SELECT SUM(valor_cliente) as saldo
                FROM transacoes_cashback
                WHERE usuario_id = :usuario_id
                AND status = :status
            ";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':usuario_id', $usuarioId);
            $status = TRANSACTION_APPROVED;
            $stmt->bindParam(':status', $status);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['saldo'] ?? 0;
        } catch (PDOException $e) {
            error_log('Erro ao obter saldo do usuário: ' . $e->getMessage());
            return 0;
        }
    }
    
    // Métodos Getters e Setters
    
    public function getId() {
        return $this->id;
    }
    
    public function getUsuarioId() {
        return $this->usuarioId;
    }
    
    public function setUsuarioId($usuarioId) {
        $this->usuarioId = $usuarioId;
    }
    
    public function getLojaId() {
        return $this->lojaId;
    }
    
    public function setLojaId($lojaId) {
        $this->lojaId = $lojaId;
    }
    
    public function getValorTotal() {
        return $this->valorTotal;
    }
    
    public function setValorTotal($valorTotal) {
        $this->valorTotal = $valorTotal;
    }
    
    public function getValorCashback() {
        return $this->valorCashback;
    }
    
    public function getValorCliente() {
        return $this->valorCliente;
    }
    
    public function getValorAdmin() {
        return $this->valorAdmin;
    }
    
    public function getValorLoja() {
        return $this->valorLoja;
    }
    
    public function getDataTransacao() {
        return $this->dataTransacao;
    }
    
    public function setDataTransacao($dataTransacao) {
        $this->dataTransacao = $dataTransacao;
    }
    
    public function getStatus() {
        return $this->status;
    }
    
    public function setStatus($status) {
        $this->status = $status;
    }
}
?>