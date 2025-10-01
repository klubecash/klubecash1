<?php
// models/Commission.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

/**
 * Modelo de Comissão
 * Representa e manipula os dados relacionados às comissões no sistema de cashback
 */
class Commission {
    // Propriedades correspondentes às colunas da tabela transacoes_comissao
    private $id;
    private $tipo_usuario;  // 'admin' ou 'loja'
    private $usuario_id;
    private $loja_id;
    private $transacao_id;
    private $valor_total;
    private $valor_comissao;
    private $data_transacao;
    private $status;

    // Propriedades adicionais para relacionamentos e cálculos
    private $loja_nome;
    private $cliente_nome;
    private $codigo_transacao;
    private $historico_status;

    /**
     * Construtor
     * 
     * @param array $data Dados da comissão (opcional)
     */
    public function __construct($data = null) {
        if ($data) {
            $this->fillFromArray($data);
        }
    }

    /**
     * Preenche as propriedades do objeto a partir de um array
     * 
     * @param array $data Dados da comissão
     * @return void
     */
    public function fillFromArray($data) {
        if (isset($data['id'])) $this->id = $data['id'];
        if (isset($data['tipo_usuario'])) $this->tipo_usuario = $data['tipo_usuario'];
        if (isset($data['usuario_id'])) $this->usuario_id = $data['usuario_id'];
        if (isset($data['loja_id'])) $this->loja_id = $data['loja_id'];
        if (isset($data['transacao_id'])) $this->transacao_id = $data['transacao_id'];
        if (isset($data['valor_total'])) $this->valor_total = $data['valor_total'];
        if (isset($data['valor_comissao'])) $this->valor_comissao = $data['valor_comissao'];
        if (isset($data['data_transacao'])) $this->data_transacao = $data['data_transacao'];
        if (isset($data['status'])) $this->status = $data['status'];
        
        // Propriedades relacionadas
        if (isset($data['loja_nome'])) $this->loja_nome = $data['loja_nome'];
        if (isset($data['cliente_nome'])) $this->cliente_nome = $data['cliente_nome'];
        if (isset($data['codigo_transacao'])) $this->codigo_transacao = $data['codigo_transacao'];
    }

    /**
     * Converte o objeto para array
     * 
     * @return array Representação do objeto em array
     */
    public function toArray() {
        return [
            'id' => $this->id,
            'tipo_usuario' => $this->tipo_usuario,
            'usuario_id' => $this->usuario_id,
            'loja_id' => $this->loja_id,
            'transacao_id' => $this->transacao_id,
            'valor_total' => $this->valor_total,
            'valor_comissao' => $this->valor_comissao,
            'data_transacao' => $this->data_transacao,
            'status' => $this->status,
            'loja_nome' => $this->loja_nome,
            'cliente_nome' => $this->cliente_nome,
            'codigo_transacao' => $this->codigo_transacao
        ];
    }

    /**
     * Salva (insere ou atualiza) a comissão no banco de dados
     * 
     * @return bool|int ID da comissão em caso de sucesso, false em caso de erro
     */
    public function save() {
        try {
            $db = Database::getConnection();
            
            if ($this->id) {
                // Atualizar registro existente
                $stmt = $db->prepare("
                    UPDATE transacoes_comissao 
                    SET tipo_usuario = :tipo_usuario,
                        usuario_id = :usuario_id,
                        loja_id = :loja_id,
                        transacao_id = :transacao_id,
                        valor_total = :valor_total,
                        valor_comissao = :valor_comissao,
                        data_transacao = :data_transacao,
                        status = :status
                    WHERE id = :id
                ");
                $stmt->bindParam(':id', $this->id);
            } else {
                // Inserir novo registro
                $stmt = $db->prepare("
                    INSERT INTO transacoes_comissao (
                        tipo_usuario, usuario_id, loja_id, transacao_id,
                        valor_total, valor_comissao, data_transacao, status
                    ) VALUES (
                        :tipo_usuario, :usuario_id, :loja_id, :transacao_id,
                        :valor_total, :valor_comissao, :data_transacao, :status
                    )
                ");
            }
            
            // Vincular parâmetros
            $stmt->bindParam(':tipo_usuario', $this->tipo_usuario);
            $stmt->bindParam(':usuario_id', $this->usuario_id);
            $stmt->bindParam(':loja_id', $this->loja_id);
            $stmt->bindParam(':transacao_id', $this->transacao_id);
            $stmt->bindParam(':valor_total', $this->valor_total);
            $stmt->bindParam(':valor_comissao', $this->valor_comissao);
            
            // Definir data de transação se não estiver definida
            if (empty($this->data_transacao)) {
                $this->data_transacao = date('Y-m-d H:i:s');
            }
            $stmt->bindParam(':data_transacao', $this->data_transacao);
            
            // Definir status padrão se não estiver definido
            if (empty($this->status)) {
                $this->status = TRANSACTION_PENDING;
            }
            $stmt->bindParam(':status', $this->status);
            
            // Executar
            $result = $stmt->execute();
            
            if ($result) {
                if (!$this->id) {
                    $this->id = $db->lastInsertId();
                }
                return $this->id;
            }
            
            return false;
            
        } catch (PDOException $e) {
            error_log('Erro ao salvar comissão: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Atualiza o status da comissão
     * 
     * @param string $newStatus Novo status
     * @param string $observacao Observação sobre a mudança
     * @param int $usuario_id ID do usuário que fez a alteração
     * @return bool Verdadeiro se a atualização foi bem-sucedida
     */
    public function updateStatus($newStatus, $observacao = '', $usuario_id = null) {
        try {
            if (empty($newStatus) || $this->status == $newStatus) {
                return false;
            }
            
            $db = Database::getConnection();
            
            // Iniciar transação
            $db->beginTransaction();
            
            // Salvar status anterior
            $oldStatus = $this->status;
            
            // Atualizar status
            $updateStmt = $db->prepare("
                UPDATE transacoes_comissao 
                SET status = :new_status 
                WHERE id = :id
            ");
            $updateStmt->bindParam(':new_status', $newStatus);
            $updateStmt->bindParam(':id', $this->id);
            $updateSuccess = $updateStmt->execute();
            
            if ($updateSuccess) {
                $this->status = $newStatus;
                
                // Registrar no histórico de status
                $historyStmt = $db->prepare("
                    INSERT INTO comissoes_status_historico (
                        comissao_id, status_anterior, status_novo, 
                        observacao, data_alteracao, usuario_id
                    ) VALUES (
                        :comissao_id, :status_anterior, :status_novo,
                        :observacao, NOW(), :usuario_id
                    )
                ");
                
                $historyStmt->bindParam(':comissao_id', $this->id);
                $historyStmt->bindParam(':status_anterior', $oldStatus);
                $historyStmt->bindParam(':status_novo', $newStatus);
                $historyStmt->bindParam(':observacao', $observacao);
                $historyStmt->bindParam(':usuario_id', $usuario_id);
                $historySuccess = $historyStmt->execute();
                
                if ($historySuccess) {
                    $db->commit();
                    return true;
                }
            }
            
            // Rollback em caso de erro
            $db->rollBack();
            return false;
            
        } catch (PDOException $e) {
            // Rollback em caso de exceção
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            
            error_log('Erro ao atualizar status da comissão: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Carrega os dados da comissão pelo ID
     * 
     * @param int $id ID da comissão
     * @return bool Verdadeiro se o carregamento foi bem-sucedido
     */
    public function loadById($id) {
        try {
            $db = Database::getConnection();
            
            $stmt = $db->prepare("
                SELECT tc.*, l.nome_fantasia as loja_nome, u.nome as cliente_nome, t.codigo_transacao
                FROM transacoes_comissao tc
                JOIN lojas l ON tc.loja_id = l.id
                JOIN transacoes_cashback t ON tc.transacao_id = t.id
                JOIN usuarios u ON t.usuario_id = u.id
                WHERE tc.id = :id
            ");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($data) {
                $this->fillFromArray($data);
                
                // Carregar histórico de status
                $this->loadStatusHistory();
                
                return true;
            }
            
            return false;
            
        } catch (PDOException $e) {
            error_log('Erro ao carregar comissão: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Carrega o histórico de status da comissão
     * 
     * @return array Histórico de status
     */
    public function loadStatusHistory() {
        try {
            if (!$this->id) {
                return [];
            }
            
            $db = Database::getConnection();
            
            $stmt = $db->prepare("
                SELECT h.*, u.nome as usuario_nome
                FROM comissoes_status_historico h
                LEFT JOIN usuarios u ON h.usuario_id = u.id
                WHERE h.comissao_id = :comissao_id
                ORDER BY h.data_alteracao DESC
            ");
            $stmt->bindParam(':comissao_id', $this->id);
            $stmt->execute();
            
            $this->historico_status = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $this->historico_status;
            
        } catch (PDOException $e) {
            error_log('Erro ao carregar histórico de status: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca comissões com filtros e paginação
     * 
     * @param array $filters Filtros para busca
     * @param int $page Página atual
     * @param int $perPage Itens por página
     * @return array Lista de comissões e informações de paginação
     */
    public static function find($filters = [], $page = 1, $perPage = ITEMS_PER_PAGE) {
        try {
            $db = Database::getConnection();
            
            // Construir consulta base
            $query = "
                SELECT tc.*, l.nome_fantasia as loja_nome, u.nome as cliente_nome, t.codigo_transacao
                FROM transacoes_comissao tc
                JOIN lojas l ON tc.loja_id = l.id
                JOIN transacoes_cashback t ON tc.transacao_id = t.id
                JOIN usuarios u ON t.usuario_id = u.id
                WHERE 1=1
            ";
            
            $params = [];
            
            // Aplicar filtros
            if (!empty($filters)) {
                // Filtro por tipo de usuário
                if (isset($filters['tipo_usuario']) && !empty($filters['tipo_usuario'])) {
                    $query .= " AND tc.tipo_usuario = :tipo_usuario";
                    $params[':tipo_usuario'] = $filters['tipo_usuario'];
                }
                
                // Filtro por ID de usuário
                if (isset($filters['usuario_id']) && !empty($filters['usuario_id'])) {
                    $query .= " AND tc.usuario_id = :usuario_id";
                    $params[':usuario_id'] = $filters['usuario_id'];
                }
                
                // Filtro por ID de loja
                if (isset($filters['loja_id']) && !empty($filters['loja_id'])) {
                    $query .= " AND tc.loja_id = :loja_id";
                    $params[':loja_id'] = $filters['loja_id'];
                }
                
                // Filtro por status
                if (isset($filters['status']) && !empty($filters['status'])) {
                    $query .= " AND tc.status = :status";
                    $params[':status'] = $filters['status'];
                }
                
                // Filtro por período
                if (isset($filters['data_inicio']) && !empty($filters['data_inicio'])) {
                    $query .= " AND tc.data_transacao >= :data_inicio";
                    $params[':data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
                }
                
                if (isset($filters['data_fim']) && !empty($filters['data_fim'])) {
                    $query .= " AND tc.data_transacao <= :data_fim";
                    $params[':data_fim'] = $filters['data_fim'] . ' 23:59:59';
                }
                
                // Filtro por valor mínimo
                if (isset($filters['valor_min']) && !empty($filters['valor_min'])) {
                    $query .= " AND tc.valor_comissao >= :valor_min";
                    $params[':valor_min'] = $filters['valor_min'];
                }
                
                // Filtro por valor máximo
                if (isset($filters['valor_max']) && !empty($filters['valor_max'])) {
                    $query .= " AND tc.valor_comissao <= :valor_max";
                    $params[':valor_max'] = $filters['valor_max'];
                }
            }
            
            // Adicionar ordenação
            $query .= " ORDER BY tc.data_transacao DESC";
            
            // Contagem total para paginação
            $countQuery = str_replace("tc.*, l.nome_fantasia as loja_nome, u.nome as cliente_nome, t.codigo_transacao", "COUNT(*) as total", $query);
            $countStmt = $db->prepare($countQuery);
            
            foreach ($params as $param => $value) {
                $countStmt->bindValue($param, $value);
            }
            
            $countStmt->execute();
            $totalResults = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Calcular paginação
            $totalPages = ceil($totalResults / $perPage);
            $page = max(1, min($page, $totalPages));
            $offset = ($page - 1) * $perPage;
            
            // Adicionar paginação à consulta
            $query .= " LIMIT :offset, :limit";
            $params[':offset'] = $offset;
            $params[':limit'] = $perPage;
            
            // Executar consulta
            $stmt = $db->prepare($query);
            
            // Bindagem manual para LIMIT e OFFSET (precisam ser inteiros)
            foreach ($params as $param => $value) {
                if ($param === ':offset' || $param === ':limit') {
                    $stmt->bindValue($param, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($param, $value);
                }
            }
            
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Converter resultados em objetos Commission
            $commissions = [];
            foreach ($results as $data) {
                $commission = new Commission();
                $commission->fillFromArray($data);
                $commissions[] = $commission;
            }
            
            // Retornar resultados com informações de paginação
            return [
                'commissions' => $commissions,
                'pagination' => [
                    'total' => $totalResults,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'total_pages' => $totalPages
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao buscar comissões: ' . $e->getMessage());
            return [
                'commissions' => [],
                'pagination' => [
                    'total' => 0,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'total_pages' => 0
                ]
            ];
        }
    }

    /**
     * Calcula estatísticas de comissões com base em filtros
     * 
     * @param array $filters Filtros para cálculo
     * @return array Estatísticas calculadas
     */
    public static function calculateStats($filters = []) {
        try {
            $db = Database::getConnection();
            
            // Preparar condições de filtro
            $conditions = "WHERE 1=1";
            $params = [];
            
            if (!empty($filters)) {
                // Filtro por tipo de usuário
                if (isset($filters['tipo_usuario']) && !empty($filters['tipo_usuario'])) {
                    $conditions .= " AND tc.tipo_usuario = :tipo_usuario";
                    $params[':tipo_usuario'] = $filters['tipo_usuario'];
                }
                
                // Filtro por ID de loja
                if (isset($filters['loja_id']) && !empty($filters['loja_id'])) {
                    $conditions .= " AND tc.loja_id = :loja_id";
                    $params[':loja_id'] = $filters['loja_id'];
                }
                
                // Filtro por status
                if (isset($filters['status']) && !empty($filters['status'])) {
                    $conditions .= " AND tc.status = :status";
                    $params[':status'] = $filters['status'];
                }
                
                // Filtro por período
                if (isset($filters['data_inicio']) && !empty($filters['data_inicio'])) {
                    $conditions .= " AND tc.data_transacao >= :data_inicio";
                    $params[':data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
                }
                
                if (isset($filters['data_fim']) && !empty($filters['data_fim'])) {
                    $conditions .= " AND tc.data_transacao <= :data_fim";
                    $params[':data_fim'] = $filters['data_fim'] . ' 23:59:59';
                }
            }
            
            // Consulta para estatísticas
            $query = "
                SELECT 
                    COUNT(*) as total_comissoes,
                    SUM(tc.valor_comissao) as valor_total,
                    AVG(tc.valor_comissao) as valor_medio,
                    SUM(CASE WHEN tc.status = 'aprovado' THEN tc.valor_comissao ELSE 0 END) as valor_aprovado,
                    SUM(CASE WHEN tc.status = 'pendente' THEN tc.valor_comissao ELSE 0 END) as valor_pendente,
                    SUM(CASE WHEN tc.status = 'cancelado' THEN tc.valor_comissao ELSE 0 END) as valor_cancelado,
                    COUNT(CASE WHEN tc.status = 'aprovado' THEN 1 END) as count_aprovado,
                    COUNT(CASE WHEN tc.status = 'pendente' THEN 1 END) as count_pendente,
                    COUNT(CASE WHEN tc.status = 'cancelado' THEN 1 END) as count_cancelado
                FROM transacoes_comissao tc
                $conditions
            ";
            
            $stmt = $db->prepare($query);
            
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log('Erro ao calcular estatísticas: ' . $e->getMessage());
            return [
                'total_comissoes' => 0,
                'valor_total' => 0,
                'valor_medio' => 0,
                'valor_aprovado' => 0,
                'valor_pendente' => 0,
                'valor_cancelado' => 0,
                'count_aprovado' => 0,
                'count_pendente' => 0,
                'count_cancelado' => 0
            ];
        }
    }

    /**
    * Obtém os valores de distribuição de comissão padrão ou específicos da loja
    * 
    * @param int $loja_id ID da loja (opcional)
    * @return array Valores de porcentagem para distribuição
    */
    public static function getCommissionDistribution($loja_id = null) {
        try {
            $db = Database::getConnection();
            
            // Obter configurações gerais
            $configQuery = "SELECT * FROM configuracoes_cashback ORDER BY id DESC LIMIT 1";
            $configStmt = $db->query($configQuery);
            
            if ($configStmt->rowCount() > 0) {
                $config = $configStmt->fetch(PDO::FETCH_ASSOC);
                $distribution = [
                    'porcentagem_total' => ($config['porcentagem_cliente'] ?? DEFAULT_CASHBACK_CLIENT) + ($config['porcentagem_admin'] ?? DEFAULT_CASHBACK_ADMIN),
                    'porcentagem_cliente' => $config['porcentagem_cliente'] ?? DEFAULT_CASHBACK_CLIENT,
                    'porcentagem_admin' => $config['porcentagem_admin'] ?? DEFAULT_CASHBACK_ADMIN,
                    'porcentagem_loja' => 0.00 // Loja sempre 0%
                ];
            } else {
                // Usar valores padrão
                $distribution = [
                    'porcentagem_total' => DEFAULT_CASHBACK_TOTAL,
                    'porcentagem_cliente' => DEFAULT_CASHBACK_CLIENT,
                    'porcentagem_admin' => DEFAULT_CASHBACK_ADMIN,
                    'porcentagem_loja' => 0.00 // Loja sempre 0%
                ];
            }
            
            // Se um ID de loja foi fornecido, verificar se tem configuração específica
            if ($loja_id) {
                $storeStmt = $db->prepare("SELECT porcentagem_cashback FROM lojas WHERE id = :loja_id");
                $storeStmt->bindParam(':loja_id', $loja_id);
                $storeStmt->execute();
                
                if ($storeStmt->rowCount() > 0) {
                    $store = $storeStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($store['porcentagem_cashback'] > 0) {
                        // Recalcular proporcionalmente mas mantendo loja em 0%
                        $fator = $store['porcentagem_cashback'] / DEFAULT_CASHBACK_TOTAL;
                        
                        $distribution = [
                            'porcentagem_total' => $store['porcentagem_cashback'],
                            'porcentagem_cliente' => DEFAULT_CASHBACK_CLIENT * $fator,
                            'porcentagem_admin' => DEFAULT_CASHBACK_ADMIN * $fator,
                            'porcentagem_loja' => 0.00 // Loja sempre 0%
                        ];
                    }
                }
            }
            
            return $distribution;
            
        } catch (PDOException $e) {
            error_log('Erro ao obter distribuição de comissão: ' . $e->getMessage());
            
            // Retornar valores padrão em caso de erro
            return [
                'porcentagem_total' => DEFAULT_CASHBACK_TOTAL,
                'porcentagem_cliente' => DEFAULT_CASHBACK_CLIENT,
                'porcentagem_admin' => DEFAULT_CASHBACK_ADMIN,
                'porcentagem_loja' => 0.00 // Loja sempre 0%
            ];
        }
    }

    // Métodos getters e setters
    public function getId() { return $this->id; }
    public function setId($id) { $this->id = $id; }
    
    public function getTipoUsuario() { return $this->tipo_usuario; }
    public function setTipoUsuario($tipo_usuario) { $this->tipo_usuario = $tipo_usuario; }
    
    public function getUsuarioId() { return $this->usuario_id; }
    public function setUsuarioId($usuario_id) { $this->usuario_id = $usuario_id; }
    
    public function getLojaId() { return $this->loja_id; }
    public function setLojaId($loja_id) { $this->loja_id = $loja_id; }
    
    public function getTransacaoId() { return $this->transacao_id; }
    public function setTransacaoId($transacao_id) { $this->transacao_id = $transacao_id; }
    
    public function getValorTotal() { return $this->valor_total; }
    public function setValorTotal($valor_total) { $this->valor_total = $valor_total; }
    
    public function getValorComissao() { return $this->valor_comissao; }
    public function setValorComissao($valor_comissao) { $this->valor_comissao = $valor_comissao; }
    
    public function getDataTransacao() { return $this->data_transacao; }
    public function setDataTransacao($data_transacao) { $this->data_transacao = $data_transacao; }
    
    public function getStatus() { return $this->status; }
    public function setStatus($status) { $this->status = $status; }
    
    public function getLojaNome() { return $this->loja_nome; }
    public function setLojaNome($loja_nome) { $this->loja_nome = $loja_nome; }
    
    public function getClienteNome() { return $this->cliente_nome; }
    public function setClienteNome($cliente_nome) { $this->cliente_nome = $cliente_nome; }
    
    public function getCodigoTransacao() { return $this->codigo_transacao; }
    public function setCodigoTransacao($codigo_transacao) { $this->codigo_transacao = $codigo_transacao; }
    
    public function getHistoricoStatus() { return $this->historico_status; }
}
?>