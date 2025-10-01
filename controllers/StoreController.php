<?php
// controllers/StoreController.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/AuthController.php';
require_once dirname(__DIR__) . '/utils/Validator.php';

/**
 * Controlador de Lojas
 * Gerencia operações relacionadas a lojas parceiras
 */
class StoreController {
    
    /**
    * Aprova uma loja pendente E ativa o usuário associado
    * 
    * Esta função agora realiza uma operação dupla:
    * 1. Aprova a loja (como antes)
    * 2. Ativa o usuário associado (nova funcionalidade)
    * 
    * @param int $storeId ID da loja
    * @return array Resultado da operação
    */
    public static function approveStore($storeId) {
        try {
            // Verificar se é um administrador
            if (!AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            $db = Database::getConnection();
            
            // PASSO 1: Buscar dados completos da loja, incluindo usuario_id
            // Isso é como verificar se temos todos os documentos necessários
            $stmt = $db->prepare("SELECT * FROM lojas WHERE id = ? AND status = ?");
            $stmt->execute([$storeId, STORE_PENDING]);
            $store = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$store) {
                return ['status' => false, 'message' => 'Loja não encontrada ou não está pendente.'];
            }
            
            // PASSO 2: INICIAR TRANSAÇÃO para garantir que ambas operações sejam feitas juntas
            // Se uma falhar, ambas são canceladas - como um contrato que só vale se todas as partes concordarem
            $db->beginTransaction();
            
            // PASSO 3: Aprovar a loja (como antes)
            $updateStoreStmt = $db->prepare("UPDATE lojas SET status = ?, data_aprovacao = NOW() WHERE id = ?");
            $storeResult = $updateStoreStmt->execute([STORE_APPROVED, $storeId]);
            
            if (!$storeResult) {
                $db->rollBack();
                return ['status' => false, 'message' => 'Erro ao aprovar loja.'];
            }
            
            // PASSO 4: Ativar o usuário associado (NOVA FUNCIONALIDADE)
            // Aqui está a peça que estava faltando!
            if (!empty($store['usuario_id'])) {
                $updateUserStmt = $db->prepare("UPDATE usuarios SET status = ? WHERE id = ?");
                $userResult = $updateUserStmt->execute([USER_ACTIVE, $store['usuario_id']]);
                
                if (!$userResult) {
                    // Se não conseguir ativar o usuário, cancelar tudo
                    $db->rollBack();
                    return ['status' => false, 'message' => 'Erro ao ativar usuário da loja aprovada.'];
                }
            } else {
                // Se não houver usuário associado, isso pode ser um problema
                $db->rollBack();
                return ['status' => false, 'message' => 'Loja não possui usuário associado para ativar.'];
            }
            
            // PASSO 5: Confirmar todas as alterações
            // Como assinar o contrato final - só agora as mudanças são permanentes
            $db->commit();
            
            // PASSO 6: Enviar email de notificação (melhorado)
            if (!empty($store['email']) && class_exists('Email')) {
                $subject = 'Loja Aprovada - Klube Cash';
                $message = "
                    <h3>🎉 Parabéns, {$store['nome_fantasia']}!</h3>
                    <p>Sua loja foi <strong>aprovada</strong> no sistema Klube Cash!</p>
                    
                    <h4>✅ O que aconteceu agora:</h4>
                    <ul>
                        <li>Sua loja está <strong>ativa</strong> no sistema</li>
                        <li>Sua conta de usuário foi <strong>ativada</strong></li>
                        <li>Você já pode fazer login no sistema</li>
                        <li>Sua loja será exibida para os clientes</li>
                    </ul>
                    
                    <h4>🚀 Próximos passos:</h4>
                    <ul>
                        <li>Acesse o sistema com seu email e senha: <a href='" . LOGIN_URL . "'>Fazer Login</a></li>
                        <li>Configure seu painel de controle</li>
                        <li>Comece a registrar vendas e oferecer cashback</li>
                    </ul>
                    
                    <p><strong>Dados de acesso:</strong></p>
                    <ul>
                        <li><strong>Email:</strong> {$store['email']}</li>
                        <li><strong>Senha:</strong> A mesma que você cadastrou</li>
                    </ul>
                    
                    <p>Agora seus clientes podem começar a receber cashback em suas compras!</p>
                    <p>Atenciosamente,<br>Equipe Klube Cash</p>
                ";
                Email::send($store['email'], $subject, $message, $store['nome_fantasia']);
            }
            
            return [
                'status' => true, 
                'message' => 'Loja aprovada e usuário ativado com sucesso!',
                'data' => [
                    'store_id' => $storeId,
                    'user_id' => $store['usuario_id'],
                    'store_status' => STORE_APPROVED,
                    'user_status' => USER_ACTIVE
                ]
            ];
            
        } catch (PDOException $e) {
            // Se algo der errado, cancelar todas as alterações
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            
            error_log('Erro ao aprovar loja: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao aprovar loja. Por favor, tente novamente.'];
        }
    }
    
    /**
    * Rejeita uma loja pendente e mantém o usuário inativo
    * 
    * @param int $storeId ID da loja
    * @param string $observacao Motivo da rejeição
    * @return array Resultado da operação
    */
    public static function rejectStore($storeId, $observacao = '') {
        try {
            // Verificar se é um administrador
            if (!AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            $db = Database::getConnection();
            
            // Buscar dados completos da loja
            $stmt = $db->prepare("SELECT * FROM lojas WHERE id = ? AND status = ?");
            $stmt->execute([$storeId, STORE_PENDING]);
            $store = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$store) {
                return ['status' => false, 'message' => 'Loja não encontrada ou não está pendente.'];
            }
            
            // INICIAR TRANSAÇÃO para operações em loja e usuário
            $db->beginTransaction();
            
            // Rejeitar a loja
            $updateStoreStmt = $db->prepare("UPDATE lojas SET status = ?, observacao = ? WHERE id = ?");
            $storeResult = $updateStoreStmt->execute([STORE_REJECTED, $observacao, $storeId]);
            
            if (!$storeResult) {
                $db->rollBack();
                return ['status' => false, 'message' => 'Erro ao rejeitar loja.'];
            }
            
            // OPCIONAL: Manter usuário inativo ou até mesmo bloqueá-lo
            // Neste caso, vamos apenas garantir que ele permaneça inativo
            if (!empty($store['usuario_id'])) {
                $updateUserStmt = $db->prepare("UPDATE usuarios SET status = ? WHERE id = ?");
                $userResult = $updateUserStmt->execute([USER_INACTIVE, $store['usuario_id']]);
                
                if (!$userResult) {
                    $db->rollBack();
                    return ['status' => false, 'message' => 'Erro ao atualizar status do usuário.'];
                }
            }
            
            // Confirmar alterações
            $db->commit();
            
            // Enviar email de notificação (melhorado)
            if (!empty($store['email']) && class_exists('Email')) {
                $subject = 'Solicitação de Loja Rejeitada - Klube Cash';
                $message = "
                    <h3>Prezado(a), {$store['nome_fantasia']}!</h3>
                    <p>Infelizmente, sua solicitação para se tornar uma loja parceira no Klube Cash foi <strong>rejeitada</strong>.</p>
                ";
                
                if (!empty($observacao)) {
                    $message .= "<p><strong>Motivo da rejeição:</strong><br>" . nl2br(htmlspecialchars($observacao)) . "</p>";
                }
                
                $message .= "
                    <h4>📧 Entre em contato conosco:</h4>
                    <p>Se você tem dúvidas sobre esta decisão ou gostaria de mais informações para uma nova solicitação, entre em contato com nosso suporte:</p>
                    <ul>
                        <li><strong>Email:</strong> " . ADMIN_EMAIL . "</li>
                        <li><strong>Assunto:</strong> Dúvida sobre Rejeição de Loja - {$store['nome_fantasia']}</li>
                    </ul>
                    
                    <p>Agradecemos seu interesse em fazer parte do Klube Cash.</p>
                    <p>Atenciosamente,<br>Equipe Klube Cash</p>
                ";
                Email::send($store['email'], $subject, $message, $store['nome_fantasia']);
            }
            
            return [
                'status' => true, 
                'message' => 'Loja rejeitada com sucesso.',
                'data' => [
                    'store_id' => $storeId,
                    'user_id' => $store['usuario_id'],
                    'reason' => $observacao
                ]
            ];
            
        } catch (PDOException $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            
            error_log('Erro ao rejeitar loja: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao rejeitar loja. Por favor, tente novamente.'];
        }
    }
    
    /**
     * Cadastra uma nova loja com senha
     * 
     * Esta é a função principal que estava causando o erro.
     * Vou explicar cada correção aplicada.
     * 
     * @param array $data Dados da loja
     * @return array Resultado da operação
     */
    public static function registerStore($data) {
        try {
            // Validar dados obrigatórios (incluindo senha)
            $requiredFields = ['nome_fantasia', 'razao_social', 'cnpj', 'email', 'telefone', 'senha'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return ['status' => false, 'message' => 'Preencha todos os campos obrigatórios.'];
                }
            }
            
            // Validar confirmação de senha
            if (!isset($data['confirma_senha']) || $data['senha'] !== $data['confirma_senha']) {
                return ['status' => false, 'message' => 'As senhas não coincidem.'];
            }
            
            // Validar força da senha
            if (strlen($data['senha']) < PASSWORD_MIN_LENGTH) {
                return ['status' => false, 'message' => 'A senha deve ter pelo menos ' . PASSWORD_MIN_LENGTH . ' caracteres.'];
            }
            
            $db = Database::getConnection();
            
            // CORREÇÃO: Limpar CNPJ antes de verificar duplicatas
            // Isso garante que comparamos apenas números
            $cnpjLimpo = preg_replace('/[^0-9]/', '', $data['cnpj']);
            
            // CORREÇÃO: Usar execute() direto em vez de bindParam()
            $stmt = $db->prepare("SELECT id FROM lojas WHERE cnpj = ?");
            $stmt->execute([$cnpjLimpo]);
            
            if ($stmt->rowCount() > 0) {
                return ['status' => false, 'message' => 'Já existe uma loja cadastrada com este CNPJ.'];
            }
            
            // Verificar se já existe um usuário com este email
            $userStmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $userStmt->execute([$data['email']]);
            
            if ($userStmt->rowCount() > 0) {
                return ['status' => false, 'message' => 'Já existe um usuário cadastrado com este email.'];
            }
            
            // INICIAR TRANSAÇÃO PARA CRIAR USUÁRIO E LOJA JUNTOS
            $db->beginTransaction();
            
            // 1. CRIAR O USUÁRIO PRIMEIRO
            // PRINCIPAL CORREÇÃO: Preparar todas as variáveis com valores concretos
            // antes de usar no banco de dados. Isso evita o erro de referência.
            $nomeUsuario = $data['nome_fantasia'];
            $emailUsuario = $data['email'];
            $telefoneUsuario = $data['telefone'];
            $senhaHash = password_hash($data['senha'], PASSWORD_DEFAULT);
            $tipoUsuario = USER_TYPE_STORE;
            $statusUsuario = USER_INACTIVE; // Inativo até loja ser aprovada
            
            $userInsertStmt = $db->prepare("
                INSERT INTO usuarios (nome, email, telefone, senha_hash, tipo, status, data_criacao)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            // CORREÇÃO PRINCIPAL: Usar execute() com array de valores
            // Isso é mais seguro que bindParam() porque não precisa de referências
            $userResult = $userInsertStmt->execute([
                $nomeUsuario,
                $emailUsuario,
                $telefoneUsuario,
                $senhaHash,
                $tipoUsuario,
                $statusUsuario
            ]);
            
            if (!$userResult) {
                $db->rollBack();
                return ['status' => false, 'message' => 'Erro ao criar usuário da loja.'];
            }
            
            $userId = $db->lastInsertId();
            
            // 2. CRIAR A LOJA VINCULADA AO USUÁRIO
            // CORREÇÃO: Preparar todas as variáveis com valores definitivos
            // Isso é como preparar todos os ingredientes antes de cozinhar
            $nomeFantasia = $data['nome_fantasia'];
            $razaoSocial = $data['razao_social'];
            $logoFilename = isset($data['logo']) ? $data['logo'] : null;
            $emailLoja = $data['email'];
            $telefoneLoja = $data['telefone'];
            $categoria = isset($data['categoria']) && !empty($data['categoria']) ? $data['categoria'] : 'Outros';
            $porcentagemCashback = 10.00; // Valor fixo de 10%
            $descricao = isset($data['descricao']) ? $data['descricao'] : '';
            $website = isset($data['website']) ? $data['website'] : '';
            $statusLoja = STORE_PENDING;
            
            $storeInsertStmt = $db->prepare("
                INSERT INTO lojas (
                    nome_fantasia, razao_social, cnpj, email, telefone,
                    categoria, porcentagem_cashback, descricao, website,
                    logo, usuario_id, status, data_cadastro
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            // CORREÇÃO: Usar execute() direto com array organizado
            $storeResult = $storeInsertStmt->execute([
                $nomeFantasia,
                $razaoSocial,
                $cnpjLimpo,
                $emailLoja,
                $telefoneLoja,
                $categoria,
                $porcentagemCashback,
                $descricao,
                $website,
                $logoFilename,  // NOVO: Adicionar logo
                $userId,
                $statusLoja
            ]);
            
            if (!$storeResult) {
                $db->rollBack();
                return ['status' => false, 'message' => 'Erro ao cadastrar loja.'];
            }
            
            $storeId = $db->lastInsertId();
            
            // 3. PROCESSAR ENDEREÇO SE FORNECIDO
            if (isset($data['endereco']) && is_array($data['endereco'])) {
                $endereco = $data['endereco'];
                
                // CORREÇÃO: Preparar todas as variáveis de endereço
                // Em vez de usar ?? diretamente no bind, preparamos valores concretos
                $cep = isset($endereco['cep']) ? $endereco['cep'] : '';
                $logradouro = isset($endereco['logradouro']) ? $endereco['logradouro'] : '';
                $numero = isset($endereco['numero']) ? $endereco['numero'] : '';
                $complemento = isset($endereco['complemento']) ? $endereco['complemento'] : '';
                $bairro = isset($endereco['bairro']) ? $endereco['bairro'] : '';
                $cidade = isset($endereco['cidade']) ? $endereco['cidade'] : '';
                $estado = isset($endereco['estado']) ? $endereco['estado'] : '';
                
                $enderecoStmt = $db->prepare("
                    INSERT INTO lojas_endereco (
                        loja_id, cep, logradouro, numero, complemento, bairro, cidade, estado
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                // CORREÇÃO: Execute direto com valores preparados
                $enderecoResult = $enderecoStmt->execute([
                    $storeId,
                    $cep,
                    $logradouro,
                    $numero,
                    $complemento,
                    $bairro,
                    $cidade,
                    $estado
                ]);
                
                if (!$enderecoResult) {
                    $db->rollBack();
                    return ['status' => false, 'message' => 'Erro ao cadastrar endereço da loja.'];
                }
            }
            
            // CONFIRMAR TODAS AS OPERAÇÕES
            $db->commit();
            
            // Enviar email de notificação (com verificação de classe)
            if (!empty($data['email']) && class_exists('Email')) {
                $subject = 'Cadastro Recebido - Klube Cash';
                $message = "
                    <h3>Olá, {$data['nome_fantasia']}!</h3>
                    <p>Recebemos sua solicitação para se tornar uma loja parceira do Klube Cash.</p>
                    <p>Criamos sua conta de acesso com as seguintes informações:</p>
                    <ul>
                        <li><strong>Email:</strong> {$data['email']}</li>
                        <li><strong>Tipo de conta:</strong> Loja Parceira</li>
                    </ul>
                    <p>Sua solicitação está sob análise. Assim que for aprovada:</p>
                    <ul>
                        <li>Sua conta será ativada automaticamente</li>
                        <li>Você poderá fazer login no sistema</li>
                        <li>Sua loja será exibida no catálogo para clientes</li>
                    </ul>
                    <p>Em breve entraremos em contato com o resultado da análise.</p>
                    <p>Atenciosamente,<br>Equipe Klube Cash</p>
                ";
                Email::send($data['email'], $subject, $message, $data['nome_fantasia']);
            }
            
            return [
                'status' => true, 
                'message' => 'Loja e usuário cadastrados com sucesso! Aguarde a aprovação para acessar o sistema.',
                'data' => [
                    'store_id' => $storeId,
                    'user_id' => $userId,
                    'awaiting_approval' => true
                ]
            ];
            
        } catch (PDOException $e) {
            // Reverter todas as alterações em caso de erro
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            
            error_log('Erro ao cadastrar loja: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao processar cadastro. Tente novamente.'];
            
        } catch (Exception $e) {
            // Capturar outros tipos de erro que podem ocorrer
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            
            error_log('Erro geral ao cadastrar loja: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro inesperado no sistema. Tente novamente.'];
        }
    }
    
    /**
     * Valida CNPJ
     * @param string $cnpj CNPJ com ou sem máscara
     * @return bool Verdadeiro se o CNPJ for válido
     */
    public static function validaCNPJ($cnpj) {
        // Remover caracteres especiais
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        
        // Verificar se tem 14 dígitos
        if (strlen($cnpj) != 14) {
            return false;
        }
        
        // Verificar se todos os dígitos são iguais
        if (preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }
        
        // Validação do primeiro dígito verificador
        $soma = 0;
        $multiplicador = 5;
        
        for ($i = 0; $i < 12; $i++) {
            $soma += $cnpj[$i] * $multiplicador;
            $multiplicador = ($multiplicador == 2) ? 9 : $multiplicador - 1;
        }
        
        $resto = $soma % 11;
        $dv1 = ($resto < 2) ? 0 : 11 - $resto;
        
        // Validação do segundo dígito verificador
        $soma = 0;
        $multiplicador = 6;
        
        for ($i = 0; $i < 13; $i++) {
            $soma += $cnpj[$i] * $multiplicador;
            $multiplicador = ($multiplicador == 2) ? 9 : $multiplicador - 1;
        }
        
        $resto = $soma % 11;
        $dv2 = ($resto < 2) ? 0 : 11 - $resto;
        
        // Verificar se os dígitos verificadores são válidos
        return ($cnpj[12] == $dv1 && $cnpj[13] == $dv2);
    }
    
    /**
     * Obtém lista de lojas
     * 
     * @param array $filters Filtros para a listagem
     * @param int $page Página atual
     * @return array Lista de lojas
     */
    public static function getStores($filters = [], $page = 1) {
        try {
            $db = Database::getConnection();
            
            // Preparar consulta base
            $query = "SELECT * FROM lojas WHERE 1=1";
            $params = [];
            
            // Aplicar filtros usando array de parâmetros em vez de bindParam
            if (!empty($filters)) {
                // Filtro por status
                if (isset($filters['status']) && !empty($filters['status'])) {
                    $query .= " AND status = ?";
                    $params[] = $filters['status'];
                }
                
                // Filtro por categoria
                if (isset($filters['categoria']) && !empty($filters['categoria'])) {
                    $query .= " AND categoria = ?";
                    $params[] = $filters['categoria'];
                }
                
                // Filtro por busca (nome, razão social ou CNPJ)
                if (isset($filters['busca']) && !empty($filters['busca'])) {
                    $query .= " AND (nome_fantasia LIKE ? OR razao_social LIKE ? OR cnpj LIKE ?)";
                    $searchTerm = '%' . $filters['busca'] . '%';
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                }
            }
            
            // Padrão de busca para não-admins: apenas lojas aprovadas
            if (!AuthController::isAdmin()) {
                $query .= " AND status = ?";
                $params[] = STORE_APPROVED;
            }
            
            // Ordenação segura
            $orderBy = isset($filters['order_by']) ? $filters['order_by'] : 'nome_fantasia';
            $orderDir = isset($filters['order_dir']) && strtolower($filters['order_dir']) == 'desc' ? 'DESC' : 'ASC';
            $query .= " ORDER BY $orderBy $orderDir";
            
            // Calcular total de registros para paginação
            $countQuery = str_replace('SELECT *', 'SELECT COUNT(*) as total', $query);
            $countStmt = $db->prepare($countQuery);
            $countStmt->execute($params);
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Adicionar paginação
            $perPage = defined('ITEMS_PER_PAGE') ? ITEMS_PER_PAGE : 10;
            $offset = ($page - 1) * $perPage;
            $query .= " LIMIT $offset, $perPage";
            
            // Executar consulta principal
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Obter categorias disponíveis para filtro
            $categoriesStmt = $db->query("SELECT DISTINCT categoria FROM lojas WHERE categoria IS NOT NULL ORDER BY categoria");
            $categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Calcular informações de paginação
            $totalPages = ceil($totalCount / $perPage);
            
            return [
                'status' => true,
                'data' => [
                    'lojas' => $stores,
                    'categorias' => $categories,
                    'paginacao' => [
                        'total' => $totalCount,
                        'por_pagina' => $perPage,
                        'pagina_atual' => $page,
                        'total_paginas' => $totalPages
                    ]
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao listar lojas: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao carregar lojas. Tente novamente.'];
        }
    }
    /**
     * Valida se o usuário é uma loja
     */
    private static function validateStore() {
        // Verificar se existe sessão
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Verificar se está logado e é loja
        return isset($_SESSION['user_id']) && 
               isset($_SESSION['user_type']) && 
               $_SESSION['user_type'] === USER_TYPE_STORE;
    }
    
    /**
     * Obtém o ID da loja vinculada ao usuário logado
     */
    private static function getStoreId() {
        if (!self::validateStore()) {
            return null;
        }
        
        try {
            $db = Database::getConnection();
            $userId = $_SESSION['user_id'];
            
            $stmt = $db->prepare("SELECT id FROM lojas WHERE usuario_id = ?");
            $stmt->execute([$userId]);
            
            $store = $stmt->fetch(PDO::FETCH_ASSOC);
            return $store ? $store['id'] : null;
            
        } catch (PDOException $e) {
            error_log('Erro ao obter ID da loja: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Lista todos os funcionários de uma loja
     */
    public static function getEmployees($filters = [], $page = 1) {
        try {
            if (!self::validateStore()) {
                return ['status' => false, 'message' => 'Acesso restrito a lojistas.'];
            }
            
            $storeId = self::getStoreId();
            if (!$storeId) {
                return ['status' => false, 'message' => 'Loja não encontrada.'];
            }
            
            $db = Database::getConnection();
            
            // Construir condições WHERE
            $whereConditions = ["u.loja_vinculada_id = ? AND u.tipo = 'funcionario'"];
            $params = [$storeId];
            
            // Aplicar filtros
            if (!empty($filters['subtipo']) && $filters['subtipo'] !== 'todos') {
                $whereConditions[] = "u.subtipo_funcionario = ?";
                $params[] = $filters['subtipo'];
            }
            
            if (!empty($filters['status']) && $filters['status'] !== 'todos') {
                $whereConditions[] = "u.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['busca'])) {
                $whereConditions[] = "(u.nome LIKE ? OR u.email LIKE ?)";
                $searchTerm = '%' . $filters['busca'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
            
            // Query principal
            $query = "
                SELECT 
                    u.id,
                    u.nome,
                    u.email,
                    u.telefone,
                    u.subtipo_funcionario,
                    u.status,
                    u.data_criacao,
                    u.ultimo_login
                FROM usuarios u
                $whereClause
                ORDER BY u.data_criacao DESC
            ";
            
            // Contar total para paginação
            $countQuery = "SELECT COUNT(*) as total FROM usuarios u $whereClause";
            $countStmt = $db->prepare($countQuery);
            $countStmt->execute($params);
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Aplicar paginação
            $perPage = defined('ITEMS_PER_PAGE') ? ITEMS_PER_PAGE : 10;
            $page = max(1, (int)$page);
            $offset = ($page - 1) * $perPage;
            $query .= " LIMIT $offset, $perPage";
            
            // Executar query
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Estatísticas
            $statsQuery = "
                SELECT 
                    COUNT(*) as total_funcionarios,
                    SUM(CASE WHEN subtipo_funcionario = 'financeiro' THEN 1 ELSE 0 END) as total_financeiro,
                    SUM(CASE WHEN subtipo_funcionario = 'gerente' THEN 1 ELSE 0 END) as total_gerente,
                    SUM(CASE WHEN subtipo_funcionario = 'vendedor' THEN 1 ELSE 0 END) as total_vendedor,
                    SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) as total_ativos,
                    SUM(CASE WHEN status = 'inativo' THEN 1 ELSE 0 END) as total_inativos
                FROM usuarios
                WHERE loja_vinculada_id = ? AND tipo = 'funcionario'
            ";
            
            $statsStmt = $db->prepare($statsQuery);
            $statsStmt->execute([$storeId]);
            $statistics = $statsStmt->fetch(PDO::FETCH_ASSOC);
            
            // Informações de paginação
            $totalPages = ceil($totalCount / $perPage);
            
            return [
                'status' => true,
                'data' => [
                    'funcionarios' => $employees,
                    'estatisticas' => $statistics,
                    'paginacao' => [
                        'total' => $totalCount,
                        'por_pagina' => $perPage,
                        'pagina_atual' => $page,
                        'total_paginas' => $totalPages
                    ]
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao listar funcionários: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao carregar funcionários.'];
        }
    }
    
    /**
     * Cria um novo funcionário
     */
    public static function createEmployee($data) {
        try {
            // NOVA VERIFICAÇÃO
            if (!AuthController::hasStoreAccess()) {
                return ['status' => false, 'message' => 'Acesso negado.'];
            }
            
            if (!AuthController::canManageEmployees()) {
                return ['status' => false, 'message' => 'Apenas lojistas e gerentes podem criar funcionários.'];
            }
            
            // USAR NOVO MÉTODO
            $storeId = AuthController::getStoreId();
            if (!$storeId) {
                return ['status' => false, 'message' => 'Loja não encontrada.'];
            }
            
            // Validações existentes continuam...
            $errors = [];
            
            if (empty($data['nome']) || strlen(trim($data['nome'])) < 3) {
                $errors[] = 'Nome deve ter pelo menos 3 caracteres.';
            }
            
            if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'E-mail inválido.';
            }
            
            if (empty($data['subtipo_funcionario']) || !in_array($data['subtipo_funcionario'], [
                EMPLOYEE_TYPE_MANAGER, 
                EMPLOYEE_TYPE_FINANCIAL, 
                EMPLOYEE_TYPE_SALESPERSON
            ])) {
                $errors[] = 'Tipo de funcionário inválido.';
            }
            
            if (empty($data['senha']) || strlen($data['senha']) < PASSWORD_MIN_LENGTH) {
                $errors[] = 'Senha deve ter pelo menos ' . PASSWORD_MIN_LENGTH . ' caracteres.';
            }
            
            if (!empty($errors)) {
                return ['status' => false, 'message' => implode(' ', $errors)];
            }
            
            $db = Database::getConnection();
            
            // Verificar se email já existe
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$data['email']]);
            
            if ($stmt->rowCount() > 0) {
                return ['status' => false, 'message' => 'Este e-mail já está cadastrado.'];
            }
            
            // CORREÇÃO: Verificar se loja existe na tabela lojas
            $checkLoja = $db->prepare("SELECT id FROM lojas WHERE id = ?");
            $checkLoja->execute([$storeId]);
            $lojaExists = $checkLoja->rowCount() > 0;
            
            $finalStoreId = $storeId;
            
            // Se loja não existe, criar na tabela lojas
            if (!$lojaExists) {
                $lojista = $db->prepare("SELECT * FROM usuarios WHERE id = ? AND tipo = 'loja'");
                $lojista->execute([$storeId]);
                $lojistaData = $lojista->fetch();
                
                if ($lojistaData) {
                    $createLoja = $db->prepare("
                        INSERT INTO lojas (
                            usuario_id, nome_fantasia, razao_social, cnpj, 
                            email, telefone, porcentagem_cashback, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'aprovado')
                    ");
                    
                    $createLoja->execute([
                        $storeId,
                        $lojistaData['nome'],
                        $lojistaData['nome'],
                        '00000000000191', // CNPJ temporário
                        $lojistaData['email'],
                        $lojistaData['telefone'] ?? '11999999999',
                        10.00
                    ]);
                    
                    $finalStoreId = $db->lastInsertId();
                }
            }
            
            // Criar funcionário
            $senhaHash = password_hash($data['senha'], PASSWORD_DEFAULT);
            
            $insertStmt = $db->prepare("
                INSERT INTO usuarios (
                    nome, email, telefone, senha_hash, tipo, 
                    subtipo_funcionario, loja_vinculada_id, status, data_criacao
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $success = $insertStmt->execute([
                trim($data['nome']),
                trim($data['email']),
                trim($data['telefone'] ?? ''),
                $senhaHash,
                USER_TYPE_EMPLOYEE,
                $data['subtipo_funcionario'],
                $finalStoreId,
                USER_ACTIVE
            ]);
            
            if ($success) {
                $funcionarioId = $db->lastInsertId();
                
                // Log da criação
                error_log("Funcionário criado - ID: {$funcionarioId}, Loja: {$finalStoreId}, Criado por: {$_SESSION['user_id']}");
                
                return ['status' => true, 'message' => 'Funcionário criado com sucesso!', 'id' => $funcionarioId];
            } else {
                return ['status' => false, 'message' => 'Erro ao criar funcionário.'];
            }
            
        } catch (Exception $e) {
            error_log('Erro ao criar funcionário: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro interno do servidor.'];
        }
    }
    
    /**
     * Atualiza dados de um funcionário
     */
    public static function updateEmployee($employeeId, $data) {
        try {
            if (!self::validateStore()) {
                return ['status' => false, 'message' => 'Acesso restrito a lojistas.'];
            }
            
            $storeId = self::getStoreId();
            if (!$storeId) {
                return ['status' => false, 'message' => 'Loja não encontrada.'];
            }
            
            $db = Database::getConnection();
            
            // Verificar se o funcionário pertence a esta loja
            $checkStmt = $db->prepare("
                SELECT id FROM usuarios 
                WHERE id = ? AND loja_vinculada_id = ? AND tipo = 'funcionario'
            ");
            $checkStmt->execute([$employeeId, $storeId]);
            
            if ($checkStmt->rowCount() === 0) {
                return ['status' => false, 'message' => 'Funcionário não encontrado.'];
            }
            
            // Construir campos para atualização
            $updateFields = [];
            $params = [];
            
            if (!empty($data['nome'])) {
                $updateFields[] = "nome = ?";
                $params[] = $data['nome'];
            }
            
            if (!empty($data['email'])) {
                // Verificar se email já existe em outro usuário
                $emailCheckStmt = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                $emailCheckStmt->execute([$data['email'], $employeeId]);
                
                if ($emailCheckStmt->rowCount() > 0) {
                    return ['status' => false, 'message' => 'Este e-mail já está em uso.'];
                }
                
                $updateFields[] = "email = ?";
                $params[] = $data['email'];
            }
            
            if (isset($data['telefone'])) {
                $updateFields[] = "telefone = ?";
                $params[] = $data['telefone'];
            }
            
            if (!empty($data['subtipo_funcionario'])) {
                $updateFields[] = "subtipo_funcionario = ?";
                $params[] = $data['subtipo_funcionario'];
            }
            
            if (!empty($data['status'])) {
                $updateFields[] = "status = ?";
                $params[] = $data['status'];
            }
            
            if (!empty($data['senha'])) {
                $updateFields[] = "senha_hash = ?";
                $params[] = password_hash($data['senha'], PASSWORD_DEFAULT);
            }
            
            if (empty($updateFields)) {
                return ['status' => false, 'message' => 'Nenhum dado para atualizar.'];
            }
            
            // Executar atualização
            $params[] = $employeeId;
            $updateQuery = "UPDATE usuarios SET " . implode(', ', $updateFields) . " WHERE id = ?";
            
            $updateStmt = $db->prepare($updateQuery);
            $success = $updateStmt->execute($params);
            
            if ($success) {
                return ['status' => true, 'message' => 'Funcionário atualizado com sucesso!'];
            } else {
                return ['status' => false, 'message' => 'Erro ao atualizar funcionário.'];
            }
            
        } catch (PDOException $e) {
            error_log('Erro ao atualizar funcionário: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro interno do servidor.'];
        }
    }
    
    /**
     * Remove/desativa um funcionário
     */
    public static function deleteEmployee($employeeId) {
        try {
            if (!self::validateStore()) {
                return ['status' => false, 'message' => 'Acesso restrito a lojistas.'];
            }
            
            $storeId = self::getStoreId();
            if (!$storeId) {
                return ['status' => false, 'message' => 'Loja não encontrada.'];
            }
            
            $db = Database::getConnection();
            
            // Verificar se o funcionário pertence a esta loja
            $checkStmt = $db->prepare("
                SELECT id FROM usuarios 
                WHERE id = ? AND loja_vinculada_id = ? AND tipo = 'funcionario'
            ");
            $checkStmt->execute([$employeeId, $storeId]);
            
            if ($checkStmt->rowCount() === 0) {
                return ['status' => false, 'message' => 'Funcionário não encontrado.'];
            }
            
            // Desativar funcionário (não deletar fisicamente)
            $updateStmt = $db->prepare("UPDATE usuarios SET status = 'inativo' WHERE id = ?");
            $success = $updateStmt->execute([$employeeId]);
            
            if ($success) {
                return ['status' => true, 'message' => 'Funcionário desativado com sucesso!'];
            } else {
                return ['status' => false, 'message' => 'Erro ao desativar funcionário.'];
            }
            
        } catch (PDOException $e) {
            error_log('Erro ao desativar funcionário: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro interno do servidor.'];
        }
    }




}

// Processar requisições diretas de acesso ao controlador
if (basename($_SERVER['PHP_SELF']) === 'StoreController.php') {
    // Verificar se o usuário está autenticado
    if (!AuthController::isAuthenticated()) {
        header('Location: ' . LOGIN_URL . '?error=' . urlencode('Você precisa fazer login para acessar esta página.'));
        exit;
    }
    
    $action = $_REQUEST['action'] ?? '';
    
    switch ($action) {
        case 'approve':
            if (!AuthController::isAdmin()) {
                echo json_encode(['status' => false, 'message' => 'Acesso restrito a administradores.']);
                exit;
            }
            
            $storeId = isset($_POST['id']) ? intval($_POST['id']) : 0;
            $result = StoreController::approveStore($storeId);
            echo json_encode($result);
            break;
            
        case 'reject':
            if (!AuthController::isAdmin()) {
                echo json_encode(['status' => false, 'message' => 'Acesso restrito a administradores.']);
                exit;
            }
            
            $storeId = isset($_POST['id']) ? intval($_POST['id']) : 0;
            $observacao = $_POST['observacao'] ?? '';
            $result = StoreController::rejectStore($storeId, $observacao);
            echo json_encode($result);
            break;
            
        case 'register':
            $data = $_POST;
            $result = StoreController::registerStore($data);
            echo json_encode($result);
            break;
            
        case 'list':
            $filters = $_POST['filters'] ?? [];
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $result = StoreController::getStores($filters, $page);
            echo json_encode($result);
            break;
            
        default:
            if (AuthController::isAdmin()) {
                header('Location: ' . ADMIN_STORES_URL);
            } else {
                header('Location: ' . CLIENT_STORES_URL);
            }
            exit;
    }
}
?>