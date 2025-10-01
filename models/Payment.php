<?php
// models/Payment.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/Commission.php';

/**
 * Modelo de Pagamento
 * Representa e manipula os dados relacionados aos pagamentos de comissões no sistema de cashback
 */
class Payment {
    // Propriedades correspondentes às colunas da tabela pagamentos_comissao
    private $id;
    private $loja_id;
    private $valor_total;
    private $metodo_pagamento;
    private $numero_referencia;
    private $comprovante;
    private $observacao;
    private $observacao_admin;
    private $data_registro;
    private $data_aprovacao;
    private $status;

    // Propriedades adicionais para relacionamentos e dados complementares
    private $loja_nome;
    private $transacoes = []; // Array para armazenar transações associadas
    private $qtd_transacoes = 0;

    /**
     * Construtor
     * 
     * @param array $data Dados do pagamento (opcional)
     */
    public function __construct($data = null) {
        if ($data) {
            $this->fillFromArray($data);
        }
    }

    /**
     * Preenche as propriedades do objeto a partir de um array
     * 
     * @param array $data Dados do pagamento
     * @return void
     */
    public function fillFromArray($data) {
        if (isset($data['id'])) $this->id = $data['id'];
        if (isset($data['loja_id'])) $this->loja_id = $data['loja_id'];
        if (isset($data['valor_total'])) $this->valor_total = $data['valor_total'];
        if (isset($data['metodo_pagamento'])) $this->metodo_pagamento = $data['metodo_pagamento'];
        if (isset($data['numero_referencia'])) $this->numero_referencia = $data['numero_referencia'];
        if (isset($data['comprovante'])) $this->comprovante = $data['comprovante'];
        if (isset($data['observacao'])) $this->observacao = $data['observacao'];
        if (isset($data['observacao_admin'])) $this->observacao_admin = $data['observacao_admin'];
        if (isset($data['data_registro'])) $this->data_registro = $data['data_registro'];
        if (isset($data['data_aprovacao'])) $this->data_aprovacao = $data['data_aprovacao'];
        if (isset($data['status'])) $this->status = $data['status'];
        
        // Propriedades relacionadas
        if (isset($data['loja_nome'])) $this->loja_nome = $data['loja_nome'];
        if (isset($data['qtd_transacoes'])) $this->qtd_transacoes = $data['qtd_transacoes'];
    }

    /**
     * Converte o objeto para array
     * 
     * @param bool $includeTransactions Incluir detalhes das transações associadas
     * @return array Representação do objeto em array
     */
    public function toArray($includeTransactions = false) {
        $data = [
            'id' => $this->id,
            'loja_id' => $this->loja_id,
            'loja_nome' => $this->loja_nome,
            'valor_total' => $this->valor_total,
            'metodo_pagamento' => $this->metodo_pagamento,
            'numero_referencia' => $this->numero_referencia,
            'comprovante' => $this->comprovante,
            'observacao' => $this->observacao,
            'observacao_admin' => $this->observacao_admin,
            'data_registro' => $this->data_registro,
            'data_aprovacao' => $this->data_aprovacao,
            'status' => $this->status,
            'qtd_transacoes' => $this->qtd_transacoes
        ];
        
        // Incluir transações associadas se solicitado
        if ($includeTransactions && !empty($this->transacoes)) {
            $data['transacoes'] = [];
            foreach ($this->transacoes as $transacao) {
                $data['transacoes'][] = is_array($transacao) ? $transacao : $transacao->toArray();
            }
        }
        
        return $data;
    }

    /**
     * Salva (insere ou atualiza) o pagamento no banco de dados
     * 
     * @return bool|int ID do pagamento em caso de sucesso, false em caso de erro
     */
    public function save() {
        try {
            $db = Database::getConnection();
            
            if ($this->id) {
                // Atualizar registro existente
                $stmt = $db->prepare("
                    UPDATE pagamentos_comissao 
                    SET loja_id = :loja_id,
                        valor_total = :valor_total,
                        metodo_pagamento = :metodo_pagamento,
                        numero_referencia = :numero_referencia,
                        comprovante = :comprovante,
                        observacao = :observacao,
                        observacao_admin = :observacao_admin,
                        status = :status
                    WHERE id = :id
                ");
                $stmt->bindParam(':id', $this->id);
            } else {
                // Inserir novo registro
                $stmt = $db->prepare("
                    INSERT INTO pagamentos_comissao (
                        loja_id, valor_total, metodo_pagamento, numero_referencia,
                        comprovante, observacao, data_registro, status
                    ) VALUES (
                        :loja_id, :valor_total, :metodo_pagamento, :numero_referencia,
                        :comprovante, :observacao, NOW(), :status
                    )
                ");
            }
            
            // Vincular parâmetros comuns
            $stmt->bindParam(':loja_id', $this->loja_id);
            $stmt->bindParam(':valor_total', $this->valor_total);
            $stmt->bindParam(':metodo_pagamento', $this->metodo_pagamento);
            $stmt->bindParam(':numero_referencia', $this->numero_referencia);
            $stmt->bindParam(':comprovante', $this->comprovante);
            $stmt->bindParam(':observacao', $this->observacao);
            
            // Definir status padrão se não estiver definido
            if (empty($this->status)) {
                $this->status = 'pendente';
            }
            $stmt->bindParam(':status', $this->status);
            
            // Vincular parâmetro específico para atualização
            if ($this->id) {
                $stmt->bindParam(':observacao_admin', $this->observacao_admin);
            }
            
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
            error_log('Erro ao salvar pagamento: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Associa transações ao pagamento
     * 
     * @param array $transacaoIds Array de IDs de transações
     * @return bool Verdadeiro se a operação foi bem-sucedida
     */
    public function associateTransactions($transacaoIds) {
        try {
            if (!$this->id) {
                return false;
            }
            
            $db = Database::getConnection();
            
            // Iniciar transação
            $db->beginTransaction();
            
            // Preparar consulta de inserção
            $stmt = $db->prepare("
                INSERT INTO pagamentos_transacoes (pagamento_id, transacao_id)
                VALUES (:pagamento_id, :transacao_id)
                ON DUPLICATE KEY UPDATE pagamento_id = VALUES(pagamento_id)
            ");
            
            $stmt->bindParam(':pagamento_id', $this->id);
            $paramTransacaoId = 0;
            $stmt->bindParam(':transacao_id', $paramTransacaoId);
            
            // Inserir cada transação
            $success = true;
            foreach ($transacaoIds as $transacaoId) {
                $paramTransacaoId = $transacaoId;
                if (!$stmt->execute()) {
                    $success = false;
                    break;
                }
                
                // Atualizar status da transação para 'pagamento_pendente'
                $updateTransStmt = $db->prepare("
                    UPDATE transacoes_cashback 
                    SET status = :novo_status 
                    WHERE id = :transacao_id
                ");
                $novoStatus = TRANSACTION_PAYMENT_PENDING;
                $updateTransStmt->bindParam(':novo_status', $novoStatus);
                $updateTransStmt->bindParam(':transacao_id', $transacaoId);
                
                if (!$updateTransStmt->execute()) {
                    $success = false;
                    break;
                }
            }
            
            // Confirmar ou reverter transação
            if ($success) {
                $db->commit();
                $this->qtd_transacoes = count($transacaoIds);
                return true;
            } else {
                $db->rollBack();
                return false;
            }
            
        } catch (PDOException $e) {
            // Reverter transação em caso de exceção
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            
            error_log('Erro ao associar transações ao pagamento: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Carrega as transações associadas ao pagamento
     * 
     * @return array Transações associadas
     */
    public function loadTransactions() {
        try {
            if (!$this->id) {
                return [];
            }
            
            $db = Database::getConnection();
            
            $stmt = $db->prepare("
                SELECT t.*, pt.id as pt_id, u.nome as cliente_nome, u.email as cliente_email
                FROM pagamentos_transacoes pt
                JOIN transacoes_cashback t ON pt.transacao_id = t.id
                JOIN usuarios u ON t.usuario_id = u.id
                WHERE pt.pagamento_id = :pagamento_id
                ORDER BY t.data_transacao DESC
            ");
            $stmt->bindParam(':pagamento_id', $this->id);
            $stmt->execute();
            
            $this->transacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->qtd_transacoes = count($this->transacoes);
            
            return $this->transacoes;
            
        } catch (PDOException $e) {
            error_log('Erro ao carregar transações do pagamento: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Aprova o pagamento
     * 
     * @param string $observacao Observação do administrador
     * @return bool Verdadeiro se a aprovação foi bem-sucedida
     */
    public function approve($observacao = '') {
        try {
            if (!$this->id || $this->status == 'aprovado') {
                return false;
            }
            
            $db = Database::getConnection();
            
            // Iniciar transação
            $db->beginTransaction();
            
            // Atualizar status do pagamento
            $updateStmt = $db->prepare("
                UPDATE pagamentos_comissao
                SET status = 'aprovado', 
                    data_aprovacao = NOW(), 
                    observacao_admin = :observacao
                WHERE id = :id
            ");
            $updateStmt->bindParam(':observacao', $observacao);
            $updateStmt->bindParam(':id', $this->id);
            
            if (!$updateStmt->execute()) {
                $db->rollBack();
                return false;
            }
            
            // Carregar transações associadas se ainda não carregadas
            if (empty($this->transacoes)) {
                $this->loadTransactions();
            }
            
            // Atualizar status das transações e comissões
            if (!empty($this->transacoes)) {
                $transactionIds = array_column($this->transacoes, 'id');
                $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
                
                // Atualizar transações
                $updateTransStmt = $db->prepare("
                    UPDATE transacoes_cashback 
                    SET status = ? 
                    WHERE id IN ($placeholders)
                ");
                
                $newStatus = TRANSACTION_APPROVED;
                $params = array_merge([$newStatus], $transactionIds);
                
                for ($i = 0; $i < count($params); $i++) {
                    $updateTransStmt->bindValue($i + 1, $params[$i]);
                }
                
                if (!$updateTransStmt->execute()) {
                    $db->rollBack();
                    return false;
                }
                
                // Atualizar comissões
                $updateCommissionStmt = $db->prepare("
                    UPDATE transacoes_comissao 
                    SET status = ? 
                    WHERE transacao_id IN ($placeholders)
                ");
                
                for ($i = 0; $i < count($params); $i++) {
                    $updateCommissionStmt->bindValue($i + 1, $params[$i]);
                }
                
                if (!$updateCommissionStmt->execute()) {
                    $db->rollBack();
                    return false;
                }
            }
            
            // Confirmar transação
            $db->commit();
            
            // Atualizar propriedades do objeto
            $this->status = 'aprovado';
            $this->data_aprovacao = date('Y-m-d H:i:s');
            $this->observacao_admin = $observacao;
            
            return true;
            
        } catch (PDOException $e) {
            // Reverter transação em caso de exceção
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            
            error_log('Erro ao aprovar pagamento: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Rejeita o pagamento
     * 
     * @param string $motivo Motivo da rejeição
     * @return bool Verdadeiro se a rejeição foi bem-sucedida
     */
    public function reject($motivo) {
        try {
            if (!$this->id || $this->status == 'rejeitado' || empty($motivo)) {
                return false;
            }
            
            $db = Database::getConnection();
            
            // Iniciar transação
            $db->beginTransaction();
            
            // Atualizar status do pagamento
            $updateStmt = $db->prepare("
                UPDATE pagamentos_comissao
                SET status = 'rejeitado', 
                    data_aprovacao = NOW(), 
                    observacao_admin = :motivo
                WHERE id = :id
            ");
            $updateStmt->bindParam(':motivo', $motivo);
            $updateStmt->bindParam(':id', $this->id);
            
            if (!$updateStmt->execute()) {
                $db->rollBack();
                return false;
            }
            
            // Carregar transações associadas se ainda não carregadas
            if (empty($this->transacoes)) {
                $this->loadTransactions();
            }
            
            // Restaurar status das transações para pendente
            if (!empty($this->transacoes)) {
                $transactionIds = array_column($this->transacoes, 'id');
                $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
                
                $updateTransStmt = $db->prepare("
                    UPDATE transacoes_cashback 
                    SET status = ? 
                    WHERE id IN ($placeholders)
                ");
                
                $newStatus = TRANSACTION_PENDING;
                $params = array_merge([$newStatus], $transactionIds);
                
                for ($i = 0; $i < count($params); $i++) {
                    $updateTransStmt->bindValue($i + 1, $params[$i]);
                }
                
                if (!$updateTransStmt->execute()) {
                    $db->rollBack();
                    return false;
                }
            }
            
            // Confirmar transação
            $db->commit();
            
            // Atualizar propriedades do objeto
            $this->status = 'rejeitado';
            $this->data_aprovacao = date('Y-m-d H:i:s');
            $this->observacao_admin = $motivo;
            
            return true;
            
        } catch (PDOException $e) {
            // Reverter transação em caso de exceção
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            
            error_log('Erro ao rejeitar pagamento: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Carrega os dados do pagamento pelo ID
     * 
     * @param int $id ID do pagamento
     * @param bool $loadTransactions Carregar transações associadas
     * @return bool Verdadeiro se o carregamento foi bem-sucedido
     */
    public function loadById($id, $loadTransactions = false) {
        try {
            $db = Database::getConnection();
            
            $stmt = $db->prepare("
                SELECT p.*, l.nome_fantasia as loja_nome,
                       (SELECT COUNT(*) FROM pagamentos_transacoes WHERE pagamento_id = p.id) as qtd_transacoes
                FROM pagamentos_comissao p
                JOIN lojas l ON p.loja_id = l.id
                WHERE p.id = :id
            ");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($data) {
                $this->fillFromArray($data);
                
                // Carregar transações associadas, se solicitado
                if ($loadTransactions) {
                    $this->loadTransactions();
                }
                
                return true;
            }
            
            return false;
            
        } catch (PDOException $e) {
            error_log('Erro ao carregar pagamento: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Carrega o comprovante associado ao pagamento
     * 
     * @return string|null Caminho do arquivo ou null se não existir
     */
    public function getComprovantePath() {
        if (empty($this->comprovante)) {
            return null;
        }
        
        $basePath = ROOT_DIR . '/uploads/comprovantes/';
        $filePath = $basePath . $this->comprovante;
        
        if (file_exists($filePath)) {
            return $filePath;
        }
        
        return null;
    }

    /**
     * Registra upload de comprovante
     * 
     * @param array $file Arquivo enviado ($_FILES['comprovante'])
     * @return bool|string Nome do arquivo ou false em caso de erro
     */
    public function uploadComprovante($file) {
        try {
            if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
                return false;
            }
            
            // Verificar extensão
            $fileInfo = pathinfo($file['name']);
            $extension = strtolower($fileInfo['extension']);
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
            
            if (!in_array($extension, $allowedExtensions)) {
                return false;
            }
            
            // Criar diretório de upload se não existir
            $uploadDir = ROOT_DIR . '/uploads/comprovantes';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Gerar nome único para o arquivo
            $fileName = 'comprovante_' . ($this->id ?: uniqid()) . '_' . date('YmdHis') . '.' . $extension;
            $filePath = $uploadDir . '/' . $fileName;
            
            // Mover arquivo
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                $this->comprovante = $fileName;
                return $fileName;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log('Erro ao fazer upload de comprovante: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca pagamentos com filtros e paginação
     * 
     * @param array $filters Filtros para busca
     * @param int $page Página atual
     * @param int $perPage Itens por página
     * @return array Lista de pagamentos e informações de paginação
     */
    public static function find($filters = [], $page = 1, $perPage = ITEMS_PER_PAGE) {
        try {
            $db = Database::getConnection();
            
            // Construir consulta base
            $query = "
                SELECT p.*, l.nome_fantasia as loja_nome,
                       (SELECT COUNT(*) FROM pagamentos_transacoes WHERE pagamento_id = p.id) as qtd_transacoes
                FROM pagamentos_comissao p
                JOIN lojas l ON p.loja_id = l.id
                WHERE 1=1
            ";
            
            $params = [];
            
            // Aplicar filtros
            if (!empty($filters)) {
                // Filtro por loja
                if (isset($filters['loja_id']) && !empty($filters['loja_id'])) {
                    $query .= " AND p.loja_id = :loja_id";
                    $params[':loja_id'] = $filters['loja_id'];
                }
                
                // Filtro por status
                if (isset($filters['status']) && !empty($filters['status'])) {
                    $query .= " AND p.status = :status";
                    $params[':status'] = $filters['status'];
                }
                
                // Filtro por método de pagamento
                if (isset($filters['metodo_pagamento']) && !empty($filters['metodo_pagamento'])) {
                    $query .= " AND p.metodo_pagamento = :metodo_pagamento";
                    $params[':metodo_pagamento'] = $filters['metodo_pagamento'];
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
                
                // Filtro por valor mínimo
                if (isset($filters['valor_min']) && !empty($filters['valor_min'])) {
                    $query .= " AND p.valor_total >= :valor_min";
                    $params[':valor_min'] = $filters['valor_min'];
                }
                
                // Filtro por valor máximo
                if (isset($filters['valor_max']) && !empty($filters['valor_max'])) {
                    $query .= " AND p.valor_total <= :valor_max";
                    $params[':valor_max'] = $filters['valor_max'];
                }
            }
            
            // Adicionar ordenação
            $query .= " ORDER BY p.data_registro DESC";
            
            // Contagem total para paginação
            $countQuery = str_replace("p.*, l.nome_fantasia as loja_nome, (SELECT COUNT(*) FROM pagamentos_transacoes WHERE pagamento_id = p.id) as qtd_transacoes", "COUNT(*) as total", $query);
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
            
            // Converter resultados em objetos Payment
            $payments = [];
            foreach ($results as $data) {
                $payment = new Payment();
                $payment->fillFromArray($data);
                $payments[] = $payment;
            }
            
            // Retornar resultados com informações de paginação
            return [
                'payments' => $payments,
                'pagination' => [
                    'total' => $totalResults,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'total_pages' => $totalPages
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao buscar pagamentos: ' . $e->getMessage());
            return [
                'payments' => [],
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
     * Calcula estatísticas de pagamentos com base em filtros
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
                // Filtro por loja
                if (isset($filters['loja_id']) && !empty($filters['loja_id'])) {
                    $conditions .= " AND p.loja_id = :loja_id";
                    $params[':loja_id'] = $filters['loja_id'];
                }
                
                // Filtro por status
                if (isset($filters['status']) && !empty($filters['status'])) {
                    $conditions .= " AND p.status = :status";
                    $params[':status'] = $filters['status'];
                }
                
                // Filtro por período
                if (isset($filters['data_inicio']) && !empty($filters['data_inicio'])) {
                    $conditions .= " AND p.data_registro >= :data_inicio";
                    $params[':data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
                }
                
                if (isset($filters['data_fim']) && !empty($filters['data_fim'])) {
                    $conditions .= " AND p.data_registro <= :data_fim";
                    $params[':data_fim'] = $filters['data_fim'] . ' 23:59:59';
                }
            }
            
            // Consulta para estatísticas
            $query = "
                SELECT 
                    COUNT(*) as total_pagamentos,
                    SUM(p.valor_total) as valor_total,
                    AVG(p.valor_total) as valor_medio,
                    SUM(CASE WHEN p.status = 'aprovado' THEN p.valor_total ELSE 0 END) as valor_aprovado,
                    SUM(CASE WHEN p.status = 'pendente' THEN p.valor_total ELSE 0 END) as valor_pendente,
                    SUM(CASE WHEN p.status = 'rejeitado' THEN p.valor_total ELSE 0 END) as valor_rejeitado,
                    COUNT(CASE WHEN p.status = 'aprovado' THEN 1 END) as count_aprovado,
                    COUNT(CASE WHEN p.status = 'pendente' THEN 1 END) as count_pendente,
                    COUNT(CASE WHEN p.status = 'rejeitado' THEN 1 END) as count_rejeitado,
                    (SELECT COUNT(*) FROM pagamentos_transacoes) as total_transacoes_associadas
                FROM pagamentos_comissao p
                $conditions
            ";
            
            $stmt = $db->prepare($query);
            
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log('Erro ao calcular estatísticas de pagamentos: ' . $e->getMessage());
            return [
                'total_pagamentos' => 0,
                'valor_total' => 0,
                'valor_medio' => 0,
                'valor_aprovado' => 0,
                'valor_pendente' => 0,
                'valor_rejeitado' => 0,
                'count_aprovado' => 0,
                'count_pendente' => 0,
                'count_rejeitado' => 0,
                'total_transacoes_associadas' => 0
            ];
        }
    }

    /**
     * Retorna os métodos de pagamento disponíveis
     * 
     * @return array Lista de métodos de pagamento
     */
    public static function getPaymentMethods() {
        return [
            'pix' => 'PIX',
            'transferencia' => 'Transferência Bancária',
            'boleto' => 'Boleto Bancário',
            'cartao' => 'Cartão de Crédito',
            'outro' => 'Outro'
        ];
    }

    // Métodos getters e setters
    public function getId() { return $this->id; }
    public function setId($id) { $this->id = $id; }
    
    public function getLojaId() { return $this->loja_id; }
    public function setLojaId($loja_id) { $this->loja_id = $loja_id; }
    
    public function getValorTotal() { return $this->valor_total; }
    public function setValorTotal($valor_total) { $this->valor_total = $valor_total; }
    
    public function getMetodoPagamento() { return $this->metodo_pagamento; }
    public function setMetodoPagamento($metodo_pagamento) { $this->metodo_pagamento = $metodo_pagamento; }
    
    public function getNumeroReferencia() { return $this->numero_referencia; }
    public function setNumeroReferencia($numero_referencia) { $this->numero_referencia = $numero_referencia; }
    
    public function getComprovante() { return $this->comprovante; }
    public function setComprovante($comprovante) { $this->comprovante = $comprovante; }
    
    public function getObservacao() { return $this->observacao; }
    public function setObservacao($observacao) { $this->observacao = $observacao; }
    
    public function getObservacaoAdmin() { return $this->observacao_admin; }
    public function setObservacaoAdmin($observacao_admin) { $this->observacao_admin = $observacao_admin; }
    
    public function getDataRegistro() { return $this->data_registro; }
    public function setDataRegistro($data_registro) { $this->data_registro = $data_registro; }
    
    public function getDataAprovacao() { return $this->data_aprovacao; }
    public function setDataAprovacao($data_aprovacao) { $this->data_aprovacao = $data_aprovacao; }
    
    public function getStatus() { return $this->status; }
    public function setStatus($status) { $this->status = $status; }
    
    public function getLojaNome() { return $this->loja_nome; }
    public function setLojaNome($loja_nome) { $this->loja_nome = $loja_nome; }
    
    public function getTransacoes() { return $this->transacoes; }
    public function setTransacoes($transacoes) { $this->transacoes = $transacoes; }
    
    public function getQtdTransacoes() { return $this->qtd_transacoes; }
    public function setQtdTransacoes($qtd_transacoes) { $this->qtd_transacoes = $qtd_transacoes; }
}
?>