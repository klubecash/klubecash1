<?php
// models/User.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../utils/Validator.php';

/**
 * Classe User - Modelo de Usuário
 * 
 * Esta classe representa um usuário no sistema Klube Cash e contém
 * todos os métodos para gerenciar usuários no banco de dados.
 */
class User {
    // Propriedades do usuário
    private $id;
    private $nome;
    private $email;
    private $senha;
    private $telefone;
    private $tipo;
    private $status;
    private $dataCriacao;
    private $ultimoLogin;
    
    // Conexão com o banco de dados
    private $db;
    
    /**
     * Construtor da classe
     * 
     * @param int $id ID do usuário (opcional)
     */
    public function __construct($id = null) {
        $this->db = Database::getConnection();
        
        if ($id) {
            $this->id = $id;
            $this->loadUserData();
        }
    }
    
    /**
     * Carrega os dados do usuário do banco de dados
     * 
     * @return bool Verdadeiro se o usuário foi encontrado
     */
    private function loadUserData() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE id = :id");
            $stmt->bindParam(':id', $this->id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                $this->nome = $userData['nome'];
                $this->email = $userData['email'];
                $this->telefone = $userData['telefone'] ?? null;
                $this->tipo = $userData['tipo'];
                $this->status = $userData['status'];
                $this->dataCriacao = $userData['data_criacao'];
                $this->ultimoLogin = $userData['ultimo_login'];
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log('Erro ao carregar dados do usuário: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Salva os dados do usuário no banco de dados
     * 
     * @return bool Verdadeiro se os dados foram salvos com sucesso
     */
    public function save() {
        try {
            // Se já existe um ID, atualizar o registro
            if ($this->id) {
                $stmt = $this->db->prepare("
                    UPDATE usuarios 
                    SET nome = :nome, email = :email, telefone = :telefone,
                        tipo = :tipo, status = :status
                    WHERE id = :id
                ");
                $stmt->bindParam(':id', $this->id);
            } else {
                // Caso contrário, inserir novo registro
                $stmt = $this->db->prepare("
                    INSERT INTO usuarios (nome, email, telefone, senha_hash, tipo, status, data_criacao)
                    VALUES (:nome, :email, :telefone, :senha_hash, :tipo, :status, NOW())
                ");
                
                // Hash da senha se fornecida
                $senha_hash = $this->senha ? password_hash($this->senha, PASSWORD_DEFAULT) : null;
                $stmt->bindParam(':senha_hash', $senha_hash);
            }
            
            // Bind dos parâmetros comuns
            $stmt->bindParam(':nome', $this->nome);
            $stmt->bindParam(':email', $this->email);
            $stmt->bindParam(':telefone', $this->telefone);
            $stmt->bindParam(':tipo', $this->tipo);
            $stmt->bindParam(':status', $this->status);
            
            $result = $stmt->execute();
            
            // Se for um novo registro, obter o ID gerado
            if (!$this->id && $result) {
                $this->id = $this->db->lastInsertId();
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log('Erro ao salvar usuário: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Altera a senha do usuário
     * 
     * @param string $novaSenha Nova senha
     * @return bool Verdadeiro se a senha foi alterada com sucesso
     */
    public function updatePassword($novaSenha) {
        try {
            $senha_hash = password_hash($novaSenha, PASSWORD_DEFAULT);
            
            $stmt = $this->db->prepare("
                UPDATE usuarios
                SET senha_hash = :senha_hash
                WHERE id = :id
            ");
            $stmt->bindParam(':senha_hash', $senha_hash);
            $stmt->bindParam(':id', $this->id);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Erro ao atualizar senha: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Atualiza o último login do usuário
     * 
     * @return bool Verdadeiro se atualizado com sucesso
     */
    public function updateLastLogin() {
        try {
            $stmt = $this->db->prepare("
                UPDATE usuarios
                SET ultimo_login = NOW()
                WHERE id = :id
            ");
            $stmt->bindParam(':id', $this->id);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Erro ao atualizar último login: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verifica se a senha fornecida está correta
     * 
     * @param string $senha Senha a verificar
     * @return bool Verdadeiro se a senha estiver correta
     */
    public function verifyPassword($senha) {
        try {
            $stmt = $this->db->prepare("
                SELECT senha_hash FROM usuarios WHERE id = :id
            ");
            $stmt->bindParam(':id', $this->id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                return password_verify($senha, $userData['senha_hash']);
            }
            
            return false;
        } catch (PDOException $e) {
            error_log('Erro ao verificar senha: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Desativa o usuário
     * 
     * @return bool Verdadeiro se desativado com sucesso
     */
    public function deactivate() {
        $this->status = 'inativo';
        return $this->save();
    }
    
    /**
     * Bloqueia o usuário
     * 
     * @return bool Verdadeiro se bloqueado com sucesso
     */
    public function block() {
        $this->status = 'bloqueado';
        return $this->save();
    }
    
    /**
     * Ativa o usuário
     * 
     * @return bool Verdadeiro se ativado com sucesso
     */
    public function activate() {
        $this->status = 'ativo';
        return $this->save();
    }
    
    /**
     * Busca um usuário pelo email
     * 
     * @param string $email Email do usuário
     * @return User|null Objeto usuário ou null se não encontrado
     */
    public static function findByEmail($email) {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                return new User($userData['id']);
            }
            
            return null;
        } catch (PDOException $e) {
            error_log('Erro ao buscar usuário por email: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Lista todos os usuários com filtros opcionais
     * 
     * @param array $filters Filtros para a listagem
     * @param int $page Página atual
     * @param int $perPage Itens por página
     * @return array Lista de usuários e informações de paginação
     */
    public static function getAll($filters = [], $page = 1, $perPage = null) {
        try {
            if ($perPage === null) {
                $perPage = ITEMS_PER_PAGE;
            }
            
            $db = Database::getConnection();
            
            // Construir consulta base
            $query = "SELECT * FROM usuarios WHERE 1=1";
            $params = [];
            
            // Aplicar filtros
            if (!empty($filters)) {
                if (isset($filters['tipo']) && !empty($filters['tipo'])) {
                    $query .= " AND tipo = :tipo";
                    $params[':tipo'] = $filters['tipo'];
                }
                
                if (isset($filters['status']) && !empty($filters['status'])) {
                    $query .= " AND status = :status";
                    $params[':status'] = $filters['status'];
                }
                
                if (isset($filters['busca']) && !empty($filters['busca'])) {
                    $query .= " AND (nome LIKE :busca OR email LIKE :busca)";
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
            $query .= " ORDER BY nome ASC";
            $offset = ($page - 1) * $perPage;
            $query .= " LIMIT $offset, $perPage";
            
            // Executar consulta
            $stmt = $db->prepare($query);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular páginas
            $totalPages = ceil($totalCount / $perPage);
            
            return [
                'usuarios' => $users,
                'paginacao' => [
                    'total' => $totalCount,
                    'por_pagina' => $perPage,
                    'pagina_atual' => $page,
                    'total_paginas' => $totalPages
                ]
            ];
        } catch (PDOException $e) {
            error_log('Erro ao listar usuários: ' . $e->getMessage());
            return [
                'usuarios' => [],
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
     * Conta o número de usuários por tipo
     * 
     * @return array Total de usuários por tipo
     */
    public static function countByType() {
        try {
            $db = Database::getConnection();
            $stmt = $db->query("
                SELECT tipo, COUNT(*) as total
                FROM usuarios
                GROUP BY tipo
            ");
            
            $result = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $result[$row['tipo']] = $row['total'];
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log('Erro ao contar usuários por tipo: ' . $e->getMessage());
            return [];
        }
    }
    
    // Métodos Getters e Setters
    
    public function getId() {
        return $this->id;
    }
    
    public function getNome() {
        return $this->nome;
    }
    
    public function setNome($nome) {
        $this->nome = $nome;
    }
    
    public function getEmail() {
        return $this->email;
    }
    
    public function setEmail($email) {
        $this->email = $email;
    }
    
    public function setSenha($senha) {
        $this->senha = $senha;
    }
    
    public function getTelefone() {
        return $this->telefone;
    }
    
    public function setTelefone($telefone) {
        $this->telefone = $telefone;
    }
    
    public function getTipo() {
        return $this->tipo;
    }
    
    public function setTipo($tipo) {
        $this->tipo = $tipo;
    }
    
    public function getStatus() {
        return $this->status;
    }
    
    public function setStatus($status) {
        $this->status = $status;
    }
    
    public function getDataCriacao() {
        return $this->dataCriacao;
    }
    
    public function getUltimoLogin() {
        return $this->ultimoLogin;
    }
}
?>