<?php
// models/CashbackConfig.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

/**
 * Classe CashbackConfig - Modelo de Configuração de Cashback
 * 
 * Esta classe gerencia as configurações de cashback e as regras de 
 * distribuição entre cliente, administrador e loja parceira.
 */
class CashbackConfig {
    // Propriedades da configuração
    private $id;
    private $porcentagemCliente;
    private $porcentagemAdmin;
    private $porcentagemLoja;
    private $dataAtualizacao;
    
    // Conexão com o banco de dados
    private $db;
    
    /**
     * Construtor da classe
     * 
     * @param int $id ID da configuração (opcional)
     */
    public function __construct($id = null) {
        $this->db = Database::getConnection();
        
        if ($id) {
            $this->id = $id;
            $this->loadConfig();
        } else {
            // Carregar a configuração atual (mais recente)
            $this->loadCurrentConfig();
        }
    }
    
    /**
     * Carrega os dados da configuração especificada
     * 
     * @return bool Verdadeiro se a configuração foi encontrada
     */
    private function loadConfig() {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM configuracoes_cashback
                WHERE id = :id
            ");
            $stmt->bindParam(':id', $this->id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $config = $stmt->fetch(PDO::FETCH_ASSOC);
                $this->porcentagemCliente = $config['porcentagem_cliente'];
                $this->porcentagemAdmin = $config['porcentagem_admin'];
                $this->porcentagemLoja = $config['porcentagem_loja'];
                $this->dataAtualizacao = $config['data_atualizacao'];
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log('Erro ao carregar configuração de cashback: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Carrega a configuração mais recente
     * 
     * @return bool Verdadeiro se uma configuração foi encontrada
     */
    private function loadCurrentConfig() {
        try {
            $stmt = $this->db->query("
                SELECT * FROM configuracoes_cashback
                ORDER BY id DESC LIMIT 1
            ");
            
            if ($stmt->rowCount() > 0) {
                $config = $stmt->fetch(PDO::FETCH_ASSOC);
                $this->id = $config['id'];
                $this->porcentagemCliente = $config['porcentagem_cliente'];
                $this->porcentagemAdmin = $config['porcentagem_admin'];
                $this->porcentagemLoja = $config['porcentagem_loja'];
                $this->dataAtualizacao = $config['data_atualizacao'];
                return true;
            } else {
                // Se não houver configuração, usar os valores padrão definidos nas constantes
                $this->porcentagemCliente = DEFAULT_CASHBACK_CLIENT;
                $this->porcentagemAdmin = DEFAULT_CASHBACK_ADMIN;
                $this->porcentagemLoja = DEFAULT_CASHBACK_STORE;
                $this->dataAtualizacao = date('Y-m-d H:i:s');
            }
            
            return false;
        } catch (PDOException $e) {
            error_log('Erro ao carregar configuração atual de cashback: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Salva a configuração no banco de dados
     * 
     * @return bool Verdadeiro se os dados foram salvos com sucesso
     */
    public function save() {
        try {
            // Validar porcentagens
            if (!$this->validarPorcentagens()) {
                throw new Exception('A soma das porcentagens deve ser menor ou igual a 100%');
            }
            
            // Sempre criar um novo registro para manter histórico
            $stmt = $this->db->prepare("
                INSERT INTO configuracoes_cashback (
                    porcentagem_cliente, porcentagem_admin, porcentagem_loja, data_atualizacao
                ) VALUES (
                    :porcentagem_cliente, :porcentagem_admin, :porcentagem_loja, NOW()
                )
            ");
            
            $stmt->bindParam(':porcentagem_cliente', $this->porcentagemCliente);
            $stmt->bindParam(':porcentagem_admin', $this->porcentagemAdmin);
            $stmt->bindParam(':porcentagem_loja', $this->porcentagemLoja);
            
            $result = $stmt->execute();
            
            if ($result) {
                $this->id = $this->db->lastInsertId();
                $this->dataAtualizacao = date('Y-m-d H:i:s');
            }
            
            return $result;
        } catch (Exception $e) {
            error_log('Erro ao salvar configuração de cashback: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
    * Valida se as porcentagens estão corretas
    * 
    * @return bool Verdadeiro se as porcentagens são válidas
    */
    private function validarPorcentagens() {
        // Verificar se todas as porcentagens são números positivos
        if ($this->porcentagemCliente < 0 || $this->porcentagemAdmin < 0) {
            return false;
        }
        
        // Porcentagem da loja deve sempre ser 0
        if ($this->porcentagemLoja != 0) {
            return false;
        }
        
        // Verificar se a soma cliente + admin é exatamente 10%
        $total = $this->porcentagemCliente + $this->porcentagemAdmin;
        return abs($total - 10.00) <= 0.01;
    }
    
    /**
     * Obtém a porcentagem total de cashback
     * 
     * @return float Porcentagem total de cashback
     */
    public function getPorcentagemTotal() {
        return $this->porcentagemCliente + $this->porcentagemAdmin + $this->porcentagemLoja;
    }
    
    /**
     * Calcula o valor de cashback para uma transação
     * 
     * @param float $valorTotal Valor total da transação
     * @return array Valores calculados de cashback
     */
    public function calcularCashback($valorTotal) {
        $porcentagemTotal = $this->getPorcentagemTotal();
        
        $valorCashbackTotal = round($valorTotal * ($porcentagemTotal / 100), 2);
        $valorCliente = round($valorTotal * ($this->porcentagemCliente / 100), 2);
        $valorAdmin = round($valorTotal * ($this->porcentagemAdmin / 100), 2);
        $valorLoja = round($valorTotal * ($this->porcentagemLoja / 100), 2);
        
        return [
            'valor_total' => $valorTotal,
            'porcentagem_total' => $porcentagemTotal,
            'valor_cashback' => $valorCashbackTotal,
            'valor_cliente' => $valorCliente,
            'valor_admin' => $valorAdmin,
            'valor_loja' => $valorLoja
        ];
    }
    
    /**
     * Obtém o histórico de configurações
     * 
     * @param int $limit Limite de registros a retornar
     * @return array Histórico de configurações
     */
    public static function getHistorico($limit = 10) {
        try {
            $db = Database::getConnection();
            
            $stmt = $db->prepare("
                SELECT * FROM configuracoes_cashback
                ORDER BY id DESC
                LIMIT :limit
            ");
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Erro ao obter histórico de configurações: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtém a configuração atual (mais recente)
     * 
     * @return CashbackConfig Objeto com a configuração atual
     */
    public static function getCurrentConfig() {
        return new CashbackConfig();
    }
    
    /**
     * Simula o cashback para diferentes valores
     * 
     * @param array $valores Array de valores para simular
     * @return array Resultados da simulação
     */
    public function simularCashback($valores) {
        $resultados = [];
        
        foreach ($valores as $valor) {
            $resultados[] = $this->calcularCashback($valor);
        }
        
        return $resultados;
    }
    
    // Métodos Getters e Setters
    
    public function getId() {
        return $this->id;
    }
    
    public function getPorcentagemCliente() {
        return $this->porcentagemCliente;
    }
    
    public function setPorcentagemCliente($porcentagem) {
        if ($porcentagem >= 0) {
            $this->porcentagemCliente = $porcentagem;
            return true;
        }
        return false;
    }
    
    public function getPorcentagemAdmin() {
        return $this->porcentagemAdmin;
    }
    
    public function setPorcentagemAdmin($porcentagem) {
        if ($porcentagem >= 0) {
            $this->porcentagemAdmin = $porcentagem;
            return true;
        }
        return false;
    }
    
    public function getPorcentagemLoja() {
        return $this->porcentagemLoja;
    }
    
    public function setPorcentagemLoja($porcentagem) {
        if ($porcentagem >= 0) {
            $this->porcentagemLoja = $porcentagem;
            return true;
        }
        return false;
    }
    
    public function getDataAtualizacao() {
        return $this->dataAtualizacao;
    }
    
    /**
    * Atualiza todas as porcentagens de uma vez
    * 
    * @param float $porcentagemCliente Porcentagem para o cliente
    * @param float $porcentagemAdmin Porcentagem para o administrador
    * @param float $porcentagemLoja Porcentagem para a loja (sempre será 0)
    * @return bool Verdadeiro se as porcentagens foram atualizadas com sucesso
    */
    public function atualizarPorcentagens($porcentagemCliente, $porcentagemAdmin, $porcentagemLoja = 0.00) {
        // Validar que todas as porcentagens são positivas
        if ($porcentagemCliente < 0 || $porcentagemAdmin < 0) {
            return false;
        }
        
        // Forçar porcentagem da loja como 0
        $porcentagemLoja = 0.00;
        
        // Validar que a soma seja exatamente 10%
        $total = $porcentagemCliente + $porcentagemAdmin;
        if (abs($total - 10.00) > 0.01) {
            return false;
        }
        
        $this->porcentagemCliente = $porcentagemCliente;
        $this->porcentagemAdmin = $porcentagemAdmin;
        $this->porcentagemLoja = 0.00;
        
        return true;
    }
}
?>