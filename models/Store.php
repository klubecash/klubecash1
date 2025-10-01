<?php
// models/Store.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../utils/Validator.php';

/**
 * Classe Store - Modelo de Loja Parceira
 * 
 * Esta classe representa uma loja parceira no sistema Klube Cash
 * e contém métodos para gerenciar lojas e seus respectivos dados.
 */
class Store {
    // Propriedades da loja
    private $id;
    private $nomeFantasia;
    private $razaoSocial;
    private $cnpj;
    private $email;
    private $telefone;
    private $porcentagemCashback;
    private $categoria;
    private $descricao;
    private $website;
    private $logo;
    private $status;
    private $dataCadastro;
    private $dataAprovacao;
    private $observacao;
    private $endereco;
    
    // Conexão com o banco de dados
    private $db;
    
    /**
     * Construtor da classe
     * 
     * @param int $id ID da loja (opcional)
     */
    public function __construct($id = null) {
        $this->db = Database::getConnection();
        
        if ($id) {
            $this->id = $id;
            $this->loadStoreData();
        } else {
            // Valores padrão para nova loja
            $this->status = STORE_PENDING;
            $this->porcentagemCashback = DEFAULT_CASHBACK_TOTAL;
            $this->dataCadastro = date('Y-m-d H:i:s');
        }
    }
    
    /**
     * Carrega os dados da loja do banco de dados
     * 
     * @return bool Verdadeiro se a loja foi encontrada
     */
    private function loadStoreData() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM lojas WHERE id = :id");
            $stmt->bindParam(':id', $this->id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $storeData = $stmt->fetch(PDO::FETCH_ASSOC);
                $this->nomeFantasia = $storeData['nome_fantasia'];
                $this->razaoSocial = $storeData['razao_social'];
                $this->cnpj = $storeData['cnpj'];
                $this->email = $storeData['email'];
                $this->telefone = $storeData['telefone'];
                $this->porcentagemCashback = $storeData['porcentagem_cashback'];
                $this->categoria = $storeData['categoria'] ?? null;
                $this->descricao = $storeData['descricao'] ?? null;
                $this->website = $storeData['website'] ?? null;
                $this->logo = $storeData['logo'] ?? null;
                $this->status = $storeData['status'];
                $this->dataCadastro = $storeData['data_cadastro'];
                $this->dataAprovacao = $storeData['data_aprovacao'] ?? null;
                $this->observacao = $storeData['observacao'] ?? null;
                
                // Carregar dados de endereço se existirem
                $this->loadStoreAddress();
                
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log('Erro ao carregar dados da loja: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Carrega o endereço da loja
     * 
     * @return bool Verdadeiro se o endereço foi encontrado
     */
    private function loadStoreAddress() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM lojas_endereco WHERE loja_id = :loja_id");
            $stmt->bindParam(':loja_id', $this->id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $this->endereco = $stmt->fetch(PDO::FETCH_ASSOC);
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log('Erro ao carregar endereço da loja: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Salva os dados da loja no banco de dados
     * 
     * @return bool Verdadeiro se os dados foram salvos com sucesso
     */
    public function save() {
        try {
            // Validar dados básicos
            if (empty($this->nomeFantasia) || empty($this->razaoSocial) || 
                empty($this->cnpj) || empty($this->email) || empty($this->telefone)) {
                throw new Exception("Todos os campos obrigatórios devem ser preenchidos");
            }
            
            // Iniciar transação
            $this->db->beginTransaction();
            
            // Se já existe um ID, atualizar o registro
            if ($this->id) {
                $stmt = $this->db->prepare("
                    UPDATE lojas 
                    SET nome_fantasia = :nome_fantasia,
                        razao_social = :razao_social,
                        cnpj = :cnpj,
                        email = :email,
                        telefone = :telefone,
                        porcentagem_cashback = :porcentagem_cashback,
                        categoria = :categoria,
                        descricao = :descricao,
                        website = :website,
                        logo = :logo,
                        status = :status,
                        observacao = :observacao
                    WHERE id = :id
                ");
                $stmt->bindParam(':id', $this->id);
            } else {
                // Caso contrário, inserir novo registro
                $stmt = $this->db->prepare("
                    INSERT INTO lojas (
                        nome_fantasia, razao_social, cnpj, email, telefone,
                        porcentagem_cashback, categoria, descricao, website,
                        logo, status, data_cadastro, observacao
                    ) VALUES (
                        :nome_fantasia, :razao_social, :cnpj, :email, :telefone,
                        :porcentagem_cashback, :categoria, :descricao, :website,
                        :logo, :status, :data_cadastro, :observacao
                    )
                ");
                $stmt->bindParam(':data_cadastro', $this->dataCadastro);
            }
            
            // Bind dos parâmetros comuns
            $stmt->bindParam(':nome_fantasia', $this->nomeFantasia);
            $stmt->bindParam(':razao_social', $this->razaoSocial);
            $stmt->bindParam(':cnpj', $this->cnpj);
            $stmt->bindParam(':email', $this->email);
            $stmt->bindParam(':telefone', $this->telefone);
            $stmt->bindParam(':porcentagem_cashback', $this->porcentagemCashback);
            $stmt->bindParam(':categoria', $this->categoria);
            $stmt->bindParam(':descricao', $this->descricao);
            $stmt->bindParam(':website', $this->website);
            $stmt->bindParam(':logo', $this->logo);
            $stmt->bindParam(':status', $this->status);
            $stmt->bindParam(':observacao', $this->observacao);
            
            $result = $stmt->execute();
            
            // Se for um novo registro, obter o ID gerado
            if (!$this->id && $result) {
                $this->id = $this->db->lastInsertId();
            }
            
            // Se houver dados de endereço, salvá-los
            if ($this->endereco) {
                $this->saveAddress();
            }
            
            // Se status for aprovado e não tiver data de aprovação, definir agora
            if ($this->status === STORE_APPROVED && !$this->dataAprovacao) {
                $approveStmt = $this->db->prepare("
                    UPDATE lojas SET data_aprovacao = NOW() WHERE id = :id AND data_aprovacao IS NULL
                ");
                $approveStmt->bindParam(':id', $this->id);
                $approveStmt->execute();
            }
            
            // Commit da transação
            $this->db->commit();
            
            return $result;
        } catch (Exception $e) {
            // Rollback em caso de erro
            $this->db->rollBack();
            error_log('Erro ao salvar loja: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Salva o endereço da loja
     * 
     * @return bool Verdadeiro se o endereço foi salvo com sucesso
     */
    private function saveAddress() {
        try {
            // Verificar se já existe um endereço para esta loja
            $checkStmt = $this->db->prepare("SELECT id FROM lojas_endereco WHERE loja_id = :loja_id");
            $checkStmt->bindParam(':loja_id', $this->id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                // Atualizar endereço existente
                $addressData = $checkStmt->fetch(PDO::FETCH_ASSOC);
                $stmt = $this->db->prepare("
                    UPDATE lojas_endereco 
                    SET cep = :cep,
                        logradouro = :logradouro,
                        numero = :numero,
                        complemento = :complemento,
                        bairro = :bairro,
                        cidade = :cidade,
                        estado = :estado
                    WHERE id = :id
                ");
                $stmt->bindParam(':id', $addressData['id']);
            } else {
                // Inserir novo endereço
                $stmt = $this->db->prepare("
                    INSERT INTO lojas_endereco (
                        loja_id, cep, logradouro, numero, complemento,
                        bairro, cidade, estado
                    ) VALUES (
                        :loja_id, :cep, :logradouro, :numero, :complemento,
                        :bairro, :cidade, :estado
                    )
                ");
                $stmt->bindParam(':loja_id', $this->id);
            }
            
            // Bind dos parâmetros de endereço
            $stmt->bindParam(':cep', $this->endereco['cep']);
            $stmt->bindParam(':logradouro', $this->endereco['logradouro']);
            $stmt->bindParam(':numero', $this->endereco['numero']);
            $stmt->bindParam(':complemento', $this->endereco['complemento']);
            $stmt->bindParam(':bairro', $this->endereco['bairro']);
            $stmt->bindParam(':cidade', $this->endereco['cidade']);
            $stmt->bindParam(':estado', $this->endereco['estado']);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Erro ao salvar endereço da loja: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Aprova uma loja pendente
     * 
     * @return bool Verdadeiro se aprovada com sucesso
     */
    public function aprovar() {
        if ($this->status !== STORE_PENDING) {
            return false;
        }
        
        $this->status = STORE_APPROVED;
        $this->dataAprovacao = date('Y-m-d H:i:s');
        return $this->save();
    }
    
    /**
     * Rejeita uma loja pendente
     * 
     * @param string $observacao Motivo da rejeição
     * @return bool Verdadeiro se rejeitada com sucesso
     */
    public function rejeitar($observacao = '') {
        if ($this->status !== STORE_PENDING) {
            return false;
        }
        
        $this->status = STORE_REJECTED;
        $this->observacao = $observacao;
        return $this->save();
    }
    
    /**
    * Obtém estatísticas da loja
    * 
    * @param array $filters Filtros para as estatísticas
    * @return array Estatísticas da loja
    */
    public function getEstatisticas($filters = []) {
        try {
            $query = "
                SELECT 
                    COUNT(*) as total_transacoes,
                    SUM(valor_total) as total_vendas,
                    SUM(valor_cashback) as total_cashback,
                    SUM(valor_cliente) as total_cashback_clientes,
                    0.00 as total_recebido,
                    AVG(valor_total) as ticket_medio
                FROM transacoes_cashback
                WHERE loja_id = :loja_id
                AND status = :status
            ";
            
            $params = [
                ':loja_id' => $this->id,
                ':status' => TRANSACTION_APPROVED
            ];
            
            // Aplicar filtros adicionais
            if (!empty($filters)) {
                if (isset($filters['data_inicio']) && $filters['data_inicio']) {
                    $query .= " AND data_transacao >= :data_inicio";
                    $params[':data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
                }
                
                if (isset($filters['data_fim']) && $filters['data_fim']) {
                    $query .= " AND data_transacao <= :data_fim";
                    $params[':data_fim'] = $filters['data_fim'] . ' 23:59:59';
                }
            }
            
            $stmt = $this->db->prepare($query);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            $stmt->execute();
            
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Adicionar dados de clientes únicos
            $clienteQuery = "
                SELECT COUNT(DISTINCT usuario_id) as total_clientes
                FROM transacoes_cashback
                WHERE loja_id = :loja_id
                AND status = :status
            ";
            
            $clienteStmt = $this->db->prepare($clienteQuery);
            $clienteStmt->bindParam(':loja_id', $this->id);
            $clienteStmt->bindParam(':status', $params[':status']);
            $clienteStmt->execute();
            
            $clientesData = $clienteStmt->fetch(PDO::FETCH_ASSOC);
            $stats['total_clientes'] = $clientesData['total_clientes'];
            
            return $stats;
        } catch (PDOException $e) {
            error_log('Erro ao obter estatísticas da loja: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtém transações da loja
     * 
     * @param array $filters Filtros para as transações
     * @param int $page Página atual
     * @param int $perPage Itens por página
     * @return array Lista de transações e informações de paginação
     */
    public function getTransacoes($filters = [], $page = 1, $perPage = null) {
        try {
            if ($perPage === null) {
                $perPage = ITEMS_PER_PAGE;
            }
            
            $query = "
                SELECT t.*, u.nome as nome_usuario
                FROM transacoes_cashback t
                JOIN usuarios u ON t.usuario_id = u.id
                WHERE t.loja_id = :loja_id
            ";
            
            $params = [':loja_id' => $this->id];
            
            // Aplicar filtros adicionais
            if (!empty($filters)) {
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
            }
            
            // Calcular total para paginação
            $countQuery = str_replace("t.*, u.nome as nome_usuario", "COUNT(*) as total", $query);
            $countStmt = $this->db->prepare($countQuery);
            foreach ($params as $param => $value) {
                $countStmt->bindValue($param, $value);
            }
            $countStmt->execute();
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Adicionar ordenação e paginação
            $query .= " ORDER BY t.data_transacao DESC";
            $offset = ($page - 1) * $perPage;
            $query .= " LIMIT $offset, $perPage";
            
            // Executar consulta principal
            $stmt = $this->db->prepare($query);
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
            error_log('Erro ao obter transações da loja: ' . $e->getMessage());
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
     * Carrega uma loja pelo CNPJ
     * 
     * @param string $cnpj CNPJ da loja
     * @return Store|null Objeto loja ou null se não encontrada
     */
    public static function findByCNPJ($cnpj) {
        try {
            // Remover caracteres especiais do CNPJ
            $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
            
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT id FROM lojas WHERE cnpj = :cnpj");
            $stmt->bindParam(':cnpj', $cnpj);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $storeData = $stmt->fetch(PDO::FETCH_ASSOC);
                return new Store($storeData['id']);
            }
            
            return null;
        } catch (PDOException $e) {
            error_log('Erro ao buscar loja por CNPJ: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Lista todas as lojas com filtros opcionais
     * 
     * @param array $filters Filtros para a listagem
     * @param int $page Página atual
     * @param int $perPage Itens por página
     * @return array Lista de lojas e informações de paginação
     */
    public static function getAll($filters = [], $page = 1, $perPage = null) {
        try {
            if ($perPage === null) {
                $perPage = ITEMS_PER_PAGE;
            }
            
            $db = Database::getConnection();
            
            // Construir consulta base
            $query = "SELECT * FROM lojas WHERE 1=1";
            $params = [];
            
            // Aplicar filtros
            if (!empty($filters)) {
                if (isset($filters['status']) && !empty($filters['status'])) {
                    $query .= " AND status = :status";
                    $params[':status'] = $filters['status'];
                }
                
                if (isset($filters['categoria']) && !empty($filters['categoria'])) {
                    $query .= " AND categoria = :categoria";
                    $params[':categoria'] = $filters['categoria'];
                }
                
                if (isset($filters['busca']) && !empty($filters['busca'])) {
                    $query .= " AND (nome_fantasia LIKE :busca OR razao_social LIKE :busca OR cnpj LIKE :busca)";
                    $params[':busca'] = '%' . $filters['busca'] . '%';
                }
            }
            
            // Calcular total para paginação
            $countStmt = $db->prepare(str_replace('*', 'COUNT(*) as total', $query));
            foreach ($params as $param => $value) {
                $countStmt->bindValue($param, $value);
            }
            $countStmt->execute();
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Adicionar ordenação e paginação
            $query .= " ORDER BY nome_fantasia ASC";
            $offset = ($page - 1) * $perPage;
            $query .= " LIMIT $offset, $perPage";
            
            // Executar consulta
            $stmt = $db->prepare($query);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            $stmt->execute();
            $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Para cada loja, carregar endereço
            foreach ($stores as &$store) {
                $addrStmt = $db->prepare("SELECT * FROM lojas_endereco WHERE loja_id = :loja_id");
                $addrStmt->bindParam(':loja_id', $store['id']);
                $addrStmt->execute();
                
                if ($addrStmt->rowCount() > 0) {
                    $store['endereco'] = $addrStmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $store['endereco'] = null;
                }
            }
            
            // Obter categorias disponíveis para filtro
            $categoriesStmt = $db->query("SELECT DISTINCT categoria FROM lojas WHERE categoria IS NOT NULL ORDER BY categoria");
            $categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Calcular páginas
            $totalPages = ceil($totalCount / $perPage);
            
            return [
                'lojas' => $stores,
                'categorias' => $categories,
                'paginacao' => [
                    'total' => $totalCount,
                    'por_pagina' => $perPage,
                    'pagina_atual' => $page,
                    'total_paginas' => $totalPages
                ]
            ];
        } catch (PDOException $e) {
            error_log('Erro ao listar lojas: ' . $e->getMessage());
            return [
                'lojas' => [],
                'categorias' => [],
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
     * Valida o CNPJ
     * 
     * @param string $cnpj CNPJ para validar
     * @return bool Verdadeiro se o CNPJ for válido
     */
    public static function validaCNPJ($cnpj) {
        // Remover caracteres especiais
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        
        // Verificar tamanho
        if (strlen($cnpj) != 14) {
            return false;
        }
        
        // Verificar dígitos repetidos
        if (preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }
        
        // Calcular primeiro dígito verificador
        $soma = 0;
        for ($i = 0, $j = 5; $i < 12; $i++) {
            $soma += $cnpj[$i] * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }
        $resto = $soma % 11;
        $dv1 = ($resto < 2) ? 0 : 11 - $resto;
        
        // Verificar primeiro dígito
        if ($cnpj[12] != $dv1) {
            return false;
        }
        
        // Calcular segundo dígito verificador
        $soma = 0;
        for ($i = 0, $j = 6; $i < 13; $i++) {
            $soma += $cnpj[$i] * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }
        $resto = $soma % 11;
        $dv2 = ($resto < 2) ? 0 : 11 - $resto;
        
        // Verificar segundo dígito
        return ($cnpj[13] == $dv2);
    }
    
    // Métodos Getters e Setters
    
    public function getId() {
        return $this->id;
    }
    
    public function getNomeFantasia() {
        return $this->nomeFantasia;
    }
    
    public function setNomeFantasia($nomeFantasia) {
        $this->nomeFantasia = $nomeFantasia;
    }
    
    public function getRazaoSocial() {
        return $this->razaoSocial;
    }
    
    public function setRazaoSocial($razaoSocial) {
        $this->razaoSocial = $razaoSocial;
    }
    
    public function getCnpj() {
        return $this->cnpj;
    }
    
    public function setCnpj($cnpj) {
        // Remover caracteres especiais
        $this->cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    }
    
    public function getEmail() {
        return $this->email;
    }
    
    public function setEmail($email) {
        $this->email = $email;
    }
    
    public function getTelefone() {
        return $this->telefone;
    }
    
    public function setTelefone($telefone) {
        $this->telefone = $telefone;
    }
    
    public function getPorcentagemCashback() {
        return $this->porcentagemCashback;
    }
    
    public function setPorcentagemCashback($porcentagemCashback) {
        $this->porcentagemCashback = $porcentagemCashback;
    }
    
    public function getCategoria() {
        return $this->categoria;
    }
    
    public function setCategoria($categoria) {
        $this->categoria = $categoria;
    }
    
    public function getDescricao() {
        return $this->descricao;
    }
    
    public function setDescricao($descricao) {
        $this->descricao = $descricao;
    }
    
    public function getWebsite() {
        return $this->website;
    }
    
    public function setWebsite($website) {
        $this->website = $website;
    }
    
    public function getLogo() {
        return $this->logo;
    }
    
    public function setLogo($logo) {
        $this->logo = $logo;
    }
    
    public function getStatus() {
        return $this->status;
    }
    
    public function setStatus($status) {
        $this->status = $status;
    }
    
    public function getDataCadastro() {
        return $this->dataCadastro;
    }
    
    public function getDataAprovacao() {
        return $this->dataAprovacao;
    }
    
    public function getObservacao() {
        return $this->observacao;
    }
    
    public function setObservacao($observacao) {
        $this->observacao = $observacao;
    }
    
    public function getEndereco() {
        return $this->endereco;
    }
    
    public function setEndereco($endereco) {
        $this->endereco = $endereco;
    }
}
?>