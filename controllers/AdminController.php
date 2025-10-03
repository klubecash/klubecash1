<?php

// controllers/AdminController.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/AuthController.php';



if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se é uma requisição AJAX válida
$isAjaxRequest = (
    isset($_POST['action']) || 
    isset($_GET['action']) ||
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
);

// Se for acesso direto ao arquivo (não AJAX), redirecionar
if (!$isAjaxRequest && basename($_SERVER['PHP_SELF']) === 'AdminController.php') {
    // Verificar autenticação
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== USER_TYPE_ADMIN) {
        header('Location: /login?error=' . urlencode('Você precisa fazer login.'));
    } else {
        header('Location: /admin/dashboard');
    }
    exit;
}

// Verificar autenticação para requisições AJAX
if ($isAjaxRequest) {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== USER_TYPE_ADMIN) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['status' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
        exit;
    }
}

// No início do arquivo, após os includes
ini_set('display_errors', 0);
error_reporting(E_ALL);

function sendJsonResponse($data) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}

// Adicione um manipulador de erros para registrar erros sem exibi-los
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Erro PHP [$errno]: $errstr em $errfile:$errline");
    return true;
});
/**
 * Controlador de Administração
 * Gerencia operações administrativas como gerenciamento de usuários,
 * lojas, transações e configurações do sistema
 */
class AdminController {
    private static function sendJsonResponse($data) {
    // Limpar qualquer output anterior
    ob_clean();
    
    // Definir headers corretos
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Enviar resposta e finalizar
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
    /**
     * Obtém os dados do dashboard do administrador
     * 
     * @return array Dados do dashboard
     */
    public static function getDashboardData() {
        try {
            // Verificar se é um administrador
            if (!self::validateAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            $db = Database::getConnection();
            
            // Total de usuários
            $userCountStmt = $db->query("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'cliente'");
            $totalUsers = $userCountStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Total de lojas
            $storeStmt = $db->query("SELECT COUNT(*) as total FROM lojas WHERE status = 'aprovado'");
            $totalStores = $storeStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Total de cashback
            $cashbackStmt = $db->query("SELECT SUM(valor_cashback) as total FROM transacoes_cashback");
            $totalCashback = $cashbackStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Faturamento total (vendas processadas)
            $salesStmt = $db->query("SELECT SUM(valor_total) as total FROM transacoes_cashback WHERE status = 'aprovado'");
            $totalSales = $salesStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Comissão do admin
            $comissionStmt = $db->query("
                SELECT SUM(valor_comissao) as total 
                FROM transacoes_comissao 
                WHERE tipo_usuario = 'admin' AND status = 'aprovado'
            ");
            $adminComission = $comissionStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Usuários recentes
            $recentUsersStmt = $db->query("
                SELECT id, nome, email, data_criacao 
                FROM usuarios 
                WHERE tipo = 'cliente'
                ORDER BY data_criacao DESC
                LIMIT 5
            ");
            $recentUsers = $recentUsersStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Lojas pendentes de aprovação
            $pendingStoresStmt = $db->query("
                SELECT id, nome_fantasia, razao_social, cnpj, data_cadastro
                FROM lojas 
                WHERE status = 'pendente' 
                ORDER BY data_cadastro DESC
                LIMIT 5
            ");
            $pendingStores = $pendingStoresStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Últimas transações
            $recentTransactionsStmt = $db->query("
                SELECT t.id, t.valor_total, t.valor_cashback, t.data_transacao, t.status,
                       u.nome as cliente, l.nome_fantasia as loja
                FROM transacoes_cashback t
                JOIN usuarios u ON t.usuario_id = u.id
                JOIN lojas l ON t.loja_id = l.id
                ORDER BY t.data_transacao DESC
                LIMIT 5
            ");
            $recentTransactions = $recentTransactionsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Estatísticas diárias/semanais/mensais
            $today = date('Y-m-d');
            $firstDayOfWeek = date('Y-m-d', strtotime('monday this week'));
            $firstDayOfMonth = date('Y-m-01');
            
            // Transações hoje
            $todayTransactionsStmt = $db->prepare("
                SELECT COUNT(*) as total, SUM(valor_cashback) as cashback
                FROM transacoes_cashback
                WHERE DATE(data_transacao) = :today
            ");
            $todayTransactionsStmt->bindParam(':today', $today);
            $todayTransactionsStmt->execute();
            $todayStats = $todayTransactionsStmt->fetch(PDO::FETCH_ASSOC);
            
            // Transações esta semana
            $weekTransactionsStmt = $db->prepare("
                SELECT COUNT(*) as total, SUM(valor_cashback) as cashback
                FROM transacoes_cashback
                WHERE DATE(data_transacao) >= :first_day_of_week
            ");
            $weekTransactionsStmt->bindParam(':first_day_of_week', $firstDayOfWeek);
            $weekTransactionsStmt->execute();
            $weekStats = $weekTransactionsStmt->fetch(PDO::FETCH_ASSOC);
            
            // Transações este mês
            $monthTransactionsStmt = $db->prepare("
                SELECT COUNT(*) as total, SUM(valor_cashback) as cashback
                FROM transacoes_cashback
                WHERE DATE(data_transacao) >= :first_day_of_month
            ");
            $monthTransactionsStmt->bindParam(':first_day_of_month', $firstDayOfMonth);
            $monthTransactionsStmt->execute();
            $monthStats = $monthTransactionsStmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'status' => true,
                'data' => [
                    'total_usuarios' => $totalUsers,
                    'total_lojas' => $totalStores,
                    'total_cashback' => $totalCashback,
                    'total_vendas' => $totalSales,
                    'comissao_admin' => $adminComission,
                    'usuarios_recentes' => $recentUsers,
                    'lojas_pendentes' => $pendingStores,
                    'transacoes_recentes' => $recentTransactions,
                    'estatisticas' => [
                        'hoje' => $todayStats,
                        'semana' => $weekStats,
                        'mes' => $monthStats
                    ]
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao obter dados do dashboard admin: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao carregar dados do dashboard. Tente novamente.'];
        }
    }
    // Adicionar este método no AdminController.php

/**
* Gerenciar transações com informações de saldo
* 
* @param array $filters Filtros de busca
* @param int $page Página atual
* @return array Resultado da operação
*/
public static function manageTransactionsWithBalance($filters = [], $page = 1) {
    try {
        $db = Database::getConnection();
        $limit = ITEMS_PER_PAGE;
        $offset = ($page - 1) * $limit;
        
        // Construir condições WHERE
        $whereConditions = ["1=1"];
        $params = [];
        
        // Aplicar filtros
        if (!empty($filters['busca'])) {
            $whereConditions[] = "(u.nome LIKE :busca OR u.email LIKE :busca OR l.nome_fantasia LIKE :busca OR t.codigo_transacao LIKE :busca)";
            $params[':busca'] = '%' . $filters['busca'] . '%';
        }
        
        if (!empty($filters['loja_id'])) {
            $whereConditions[] = "t.loja_id = :loja_id";
            $params[':loja_id'] = $filters['loja_id'];
        }
        
        if (!empty($filters['status'])) {
            $whereConditions[] = "t.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['data_inicio'])) {
            $whereConditions[] = "DATE(t.data_transacao) >= :data_inicio";
            $params[':data_inicio'] = $filters['data_inicio'];
        }
        
        if (!empty($filters['data_fim'])) {
            $whereConditions[] = "DATE(t.data_transacao) <= :data_fim";
            $params[':data_fim'] = $filters['data_fim'];
        }
        
        $whereClause = "WHERE " . implode(" AND ", $whereConditions);
        
        // Query corrigida com todos os campos necessários
        $transactionsQuery = "
            SELECT 
                t.id,
                t.usuario_id,
                t.loja_id,
                t.valor_total,
                t.valor_cashback,
                t.valor_cliente,
                t.valor_admin,
                t.valor_loja,
                t.codigo_transacao,
                t.descricao,
                t.data_transacao,
                t.status,
                u.nome as cliente_nome,
                u.email as cliente_email,
                l.nome_fantasia as loja_nome,
                COALESCE(tsu.valor_usado, 0) as saldo_usado,
                pc.id as pagamento_id,
                pc.status as status_pagamento
            FROM transacoes_cashback t
            INNER JOIN usuarios u ON t.usuario_id = u.id
            INNER JOIN lojas l ON t.loja_id = l.id
            LEFT JOIN transacoes_saldo_usado tsu ON t.id = tsu.transacao_id
            LEFT JOIN pagamentos_transacoes pt ON t.id = pt.transacao_id
            LEFT JOIN pagamentos_comissao pc ON pt.pagamento_id = pc.id
            $whereClause
            ORDER BY t.data_transacao DESC
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $db->prepare($transactionsQuery);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Garantir que valores numéricos sejam float
        foreach ($transactions as &$transaction) {
            $transaction['valor_total'] = floatval($transaction['valor_total']);
            $transaction['valor_cashback'] = floatval($transaction['valor_cashback']);
            $transaction['valor_cliente'] = floatval($transaction['valor_cliente']);
            $transaction['valor_admin'] = floatval($transaction['valor_admin']);
            $transaction['valor_loja'] = floatval($transaction['valor_loja']);
            $transaction['saldo_usado'] = floatval($transaction['saldo_usado']);
        }
        
        // Query para contar total de transações
        $countQuery = "
            SELECT COUNT(DISTINCT t.id) as total
            FROM transacoes_cashback t
            INNER JOIN usuarios u ON t.usuario_id = u.id
            INNER JOIN lojas l ON t.loja_id = l.id
            LEFT JOIN transacoes_saldo_usado tsu ON t.id = tsu.transacao_id
            $whereClause
        ";
        
        $countStmt = $db->prepare($countQuery);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Obter lista de lojas para filtros
        $storesStmt = $db->query("
            SELECT id, nome_fantasia 
            FROM lojas 
            WHERE status = 'aprovado' 
            ORDER BY nome_fantasia
        ");
        $stores = $storesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular estatísticas corrigidas
        $statsQuery = "
            SELECT 
                COUNT(DISTINCT t.id) as total_transacoes,
                COALESCE(SUM(t.valor_total), 0) as valor_vendas_originais,
                COALESCE(SUM(t.valor_cliente), 0) as total_cashback,
                COALESCE(SUM(t.valor_admin), 0) as total_comissao_admin,
                COALESCE(SUM(t.valor_loja), 0) as total_comissao_loja,
                COALESCE(SUM(tsu.valor_usado), 0) as total_saldo_usado,
                COUNT(DISTINCT CASE WHEN tsu.valor_usado > 0 THEN t.id END) as transacoes_com_saldo
            FROM transacoes_cashback t
            INNER JOIN usuarios u ON t.usuario_id = u.id
            INNER JOIN lojas l ON t.loja_id = l.id
            LEFT JOIN transacoes_saldo_usado tsu ON t.id = tsu.transacao_id
            $whereClause
        ";
        
        $statsStmt = $db->prepare($statsQuery);
        foreach ($params as $key => $value) {
            $statsStmt->bindValue($key, $value);
        }
        $statsStmt->execute();
        $statistics = $statsStmt->fetch(PDO::FETCH_ASSOC);
        
        // Converter valores para float
        foreach ($statistics as $key => $value) {
            if (is_numeric($value)) {
                $statistics[$key] = floatval($value);
            }
        }
        
        // Calcular valores derivados
        $statistics['valor_liquido_pago'] = $statistics['valor_vendas_originais'] - $statistics['total_saldo_usado'];
        $statistics['percentual_uso_saldo'] = $statistics['total_transacoes'] > 0 ? 
            ($statistics['transacoes_com_saldo'] / $statistics['total_transacoes']) * 100 : 0;
        
        // Calcular paginação
        $totalPages = ceil($totalCount / $limit);
        
        return [
            'status' => true,
            'data' => [
                'transacoes' => $transactions,
                'lojas' => $stores,
                'estatisticas' => $statistics,
                'paginacao' => [
                    'pagina_atual' => $page,
                    'total_paginas' => $totalPages,
                    'total_itens' => $totalCount,
                    'itens_por_pagina' => $limit
                ]
            ]
        ];
        
    } catch (PDOException $e) {
        error_log('Erro SQL ao gerenciar transações: ' . $e->getMessage());
        error_log('SQL State: ' . $e->errorInfo[0]);
        error_log('Driver Error Code: ' . $e->errorInfo[1]);
        return ['status' => false, 'message' => 'Erro ao carregar dados das transações.'];
    } catch (Exception $e) {
        error_log('Erro geral ao gerenciar transações: ' . $e->getMessage());
        return ['status' => false, 'message' => 'Erro inesperado ao carregar transações.'];
    }
}

    /**
 * Obtém detalhes de transação com saldo usado
 */
public static function getTransactionDetailsWithBalance($transactionId) {
    try {
        if (!AuthController::isAuthenticated() || !AuthController::isAdmin()) {
            return ['status' => false, 'message' => 'Acesso negado'];
        }
        
        $db = Database::getConnection();
        
        $stmt = $db->prepare("
            SELECT 
                t.*, 
                u.nome as cliente_nome, 
                u.email as cliente_email,
                l.nome_fantasia as loja_nome,
                COALESCE(tsu.valor_usado, 0) as saldo_usado,
                pc.id as pagamento_id,
                pc.status as status_pagamento,
                pc.metodo_pagamento,
                pc.data_aprovacao as data_pagamento
            FROM transacoes_cashback t
            JOIN usuarios u ON t.usuario_id = u.id
            JOIN lojas l ON t.loja_id = l.id
            LEFT JOIN transacoes_saldo_usado tsu ON t.id = tsu.transacao_id
            LEFT JOIN pagamentos_transacoes pt ON t.id = pt.transacao_id
            LEFT JOIN pagamentos_comissao pc ON pt.pagamento_id = pc.id
            WHERE t.id = ?
        ");
        
        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$transaction) {
            return ['status' => false, 'message' => 'Transação não encontrada'];
        }
        
        return ['status' => true, 'data' => $transaction];
        
    } catch (Exception $e) {
        error_log('Erro getTransactionDetailsWithBalance: ' . $e->getMessage());
        return ['status' => false, 'message' => 'Erro interno'];
    }
}

    /**
    * Gerencia usuários do sistema incluindo funcionários vinculados às lojas
    * 
    * @param array $filters Filtros para a listagem
    * @param int $page Página atual
    * @return array Lista de usuários
    */
    public static function manageUsers($filters = [], $page = 1) {
        try {
            // Verificar se é um administrador
            if (!self::validateAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            $db = Database::getConnection();
            
            // Construir condições WHERE
            $whereConditions = [];
            $params = [];
            
            // Aplicar filtros
            if (!empty($filters['tipo']) && $filters['tipo'] !== 'todos') {
                $whereConditions[] = "u.tipo = ?";
                $params[] = $filters['tipo'];
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
            
            $whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);
            
            // Query principal com JOIN para obter informações da loja vinculada
            $query = "
                SELECT 
                    u.id, 
                    u.nome, 
                    u.email, 
                    u.tipo, 
                    u.status, 
                    u.data_criacao, 
                    u.ultimo_login,
                    u.subtipo_funcionario,
                    u.loja_vinculada_id,
                    loja_vinculada.nome_fantasia as nome_loja_vinculada,
                    loja_vinculada.mvp as loja_vinculada_mvp,
                    loja_propria.id as loja_propria_id,
                    loja_propria.nome_fantasia as nome_loja_propria,
                    loja_propria.mvp as loja_mvp
                FROM usuarios u
                LEFT JOIN lojas loja_vinculada ON u.loja_vinculada_id = loja_vinculada.id
                LEFT JOIN lojas loja_propria ON loja_propria.usuario_id = u.id
                $whereClause
                ORDER BY u.data_criacao DESC
            ";
            
            // Calcular total de registros para paginação
            $countQuery = "SELECT COUNT(*) as total FROM usuarios u $whereClause";
            $countStmt = $db->prepare($countQuery);
            $countStmt->execute($params);
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Adicionar paginação
            $perPage = defined('ITEMS_PER_PAGE') ? ITEMS_PER_PAGE : 10;
            $page = max(1, (int)$page);
            $offset = ($page - 1) * $perPage;
            $query .= " LIMIT $offset, $perPage";
            
            // Executar consulta
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($users as &$user) {
                if ($user['tipo'] === USER_TYPE_STORE) {
                    $user['loja_mvp'] = $user['loja_mvp'] ?? 'nao';
                }
                if (!empty($user['loja_vinculada_id'])) {
                    $user['loja_vinculada_mvp'] = $user['loja_vinculada_mvp'] ?? 'nao';
                }
            }
            unset($user);
            
            // Estatísticas dos usuários incluindo funcionários
            $statsQuery = "
                SELECT 
                    COUNT(*) as total_usuarios,
                    SUM(CASE WHEN tipo = 'cliente' THEN 1 ELSE 0 END) as total_clientes,
                    SUM(CASE WHEN tipo = 'admin' THEN 1 ELSE 0 END) as total_admins,
                    SUM(CASE WHEN tipo = 'loja' THEN 1 ELSE 0 END) as total_lojas,
                    SUM(CASE WHEN tipo = 'funcionario' THEN 1 ELSE 0 END) as total_funcionarios,
                    SUM(CASE WHEN tipo = 'funcionario' AND subtipo_funcionario = 'financeiro' THEN 1 ELSE 0 END) as total_funcionarios_financeiro,
                    SUM(CASE WHEN tipo = 'funcionario' AND subtipo_funcionario = 'gerente' THEN 1 ELSE 0 END) as total_funcionarios_gerente,
                    SUM(CASE WHEN tipo = 'funcionario' AND subtipo_funcionario = 'vendedor' THEN 1 ELSE 0 END) as total_funcionarios_vendedor,
                    SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) as total_ativos,
                    SUM(CASE WHEN status = 'inativo' THEN 1 ELSE 0 END) as total_inativos,
                    SUM(CASE WHEN status = 'bloqueado' THEN 1 ELSE 0 END) as total_bloqueados
                FROM usuarios
            ";
            
            $statsStmt = $db->prepare($statsQuery);
            $statsStmt->execute();
            $statistics = $statsStmt->fetch(PDO::FETCH_ASSOC);
            
            // Calcular informações de paginação
            $totalPages = ceil($totalCount / $perPage);
            
            return [
                'status' => true,
                'data' => [
                    'usuarios' => $users,
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
            error_log('Erro ao gerenciar usuários: ' . $e->getMessage());
            return [
                'status' => false, 
                'message' => 'Erro ao carregar usuários: ' . $e->getMessage()
            ];
        }
    }
    
    /**
 * Obtém detalhes de um usuário específico
 * 
 * @param int $userId ID do usuário
 * @return array Dados do usuário
 */
public static function getUserDetails($userId) {
    try {
        // Verificar se é um administrador
        if (!self::validateAdmin()) {
            return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
        }
        
        $db = Database::getConnection();
        
        // Obter dados do usuário
        $stmt = $db->prepare("
            SELECT 
                u.id,
                u.nome,
                u.email,
                u.telefone,
                u.tipo,
                u.status,
                u.data_criacao,
                u.ultimo_login,
                loja_propria.id AS loja_id,
                loja_propria.nome_fantasia AS loja_nome,
                loja_propria.mvp AS loja_mvp,
                loja_vinculada.id AS loja_vinculada_id,
                loja_vinculada.nome_fantasia AS loja_vinculada_nome,
                loja_vinculada.mvp AS loja_vinculada_mvp
            FROM usuarios u
            LEFT JOIN lojas loja_propria ON loja_propria.usuario_id = u.id
            LEFT JOIN lojas loja_vinculada ON u.loja_vinculada_id = loja_vinculada.id
            WHERE u.id = :user_id
        ");
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['status' => false, 'message' => 'Usuário não encontrado.'];
        }
        
        if ($user['tipo'] === USER_TYPE_STORE) {
            $user['loja_mvp'] = $user['loja_mvp'] ?? 'nao';
        }

        if (!empty($user['loja_vinculada_id'])) {
            $user['loja_vinculada_mvp'] = $user['loja_vinculada_mvp'] ?? 'nao';
        }

        return [
            'status' => true,
            'data' => [
                'usuario' => $user
            ]
        ];
        
    } catch (PDOException $e) {
        error_log('Erro ao obter detalhes do usuário: ' . $e->getMessage());
        return ['status' => false, 'message' => 'Erro ao carregar dados do usuário: ' . $e->getMessage()];
    }
}
    
     
    // Adicionar este método no AdminController.php

    /**
    * Gerenciar lojas com informações de saldo
    * 
    * @param array $filters Filtros de busca
    * @param int $page Página atual
    * @return array Resultado da operação
    */
    /**
    * Gerenciar lojas com informações básicas (método corrigido)
    * 
    * @param array $filters Filtros de busca
    * @param int $page Página atual
    * @return array Resultado da operação
    */
    /**
 * Gerencia lojas com informações detalhadas de saldo e uso
 * 
 * @param array $filters Filtros para a listagem
 * @param int $page Página atual
 * @return array Resultado com dados das lojas e estatísticas
 */
/**
 * Gerencia lojas com informações detalhadas de saldo e uso (VERSÃO CORRIGIDA)
 */
public static function manageStoresWithBalance($filters = [], $page = 1) {
    try {
        if (!AuthController::isAdmin()) {
            return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
        }
        
        $db = Database::getConnection();
        $limit = defined('ITEMS_PER_PAGE') ? ITEMS_PER_PAGE : 10;
        $offset = ($page - 1) * $limit;
        
        // Construir condições WHERE
        $whereConditions = ["1=1"];
        $params = [];
        
        // Aplicar filtros
        if (!empty($filters['busca'])) {
            $whereConditions[] = "(l.nome_fantasia LIKE ? OR l.razao_social LIKE ? OR l.cnpj LIKE ? OR l.email LIKE ?)";
            $searchTerm = '%' . $filters['busca'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['status'])) {
            $whereConditions[] = "l.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['categoria'])) {
            $whereConditions[] = "l.categoria = ?";
            $params[] = $filters['categoria'];
        }
        
        $whereClause = "WHERE " . implode(" AND ", $whereConditions);
        
        // QUERY CORRIGIDA - Usando a mesma que funcionou no debug
        $query = "
            SELECT 
                l.*,
                -- Saldo dos clientes (CORRIGIDO)
                COALESCE(saldo_info.clientes_com_saldo, 0) as clientes_com_saldo,
                COALESCE(saldo_info.total_saldo_clientes, 0) as total_saldo_clientes,
                -- Transações (CORRIGIDO)
                COALESCE(trans_info.total_transacoes, 0) as total_transacoes,
                COALESCE(trans_info.transacoes_com_saldo, 0) as transacoes_com_saldo,
                COALESCE(trans_info.total_saldo_usado, 0) as total_saldo_usado,
                -- Informações do usuário
                u.nome as usuario_nome,
                u.status as usuario_status
            FROM lojas l
            LEFT JOIN usuarios u ON l.usuario_id = u.id
            LEFT JOIN (
                SELECT 
                    loja_id,
                    COUNT(DISTINCT usuario_id) as clientes_com_saldo,
                    SUM(saldo_disponivel) as total_saldo_clientes
                FROM cashback_saldos 
                WHERE saldo_disponivel > 0
                GROUP BY loja_id
            ) saldo_info ON l.id = saldo_info.loja_id
            LEFT JOIN (
                SELECT 
                    tc.loja_id,
                    COUNT(DISTINCT tc.id) as total_transacoes,
                    COUNT(DISTINCT CASE WHEN tsu.valor_usado > 0 THEN tc.id END) as transacoes_com_saldo,
                    COALESCE(SUM(tsu.valor_usado), 0) as total_saldo_usado
                FROM transacoes_cashback tc
                LEFT JOIN transacoes_saldo_usado tsu ON tc.id = tsu.transacao_id
                WHERE tc.status = 'aprovado'
                GROUP BY tc.loja_id
            ) trans_info ON l.id = trans_info.loja_id
            $whereClause
            ORDER BY l.data_cadastro DESC
            LIMIT ? OFFSET ?
        ";
        
        // Executar query principal
        $allParams = array_merge($params, [$limit, $offset]);
        $stmt = $db->prepare($query);
        $stmt->execute($allParams);
        $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug: log dos dados retornados
        error_log("STORES DEBUG: " . json_encode($stores));
        
        // Query para contar total (SEM LIMIT)
        $countQuery = str_replace(
            ["LIMIT ? OFFSET ?"],
            [""],
            $query
        );
        $countStmt = $db->prepare($countQuery);
        $countStmt->execute($params);
        $totalCount = count($countStmt->fetchAll());
        
        // ESTATÍSTICAS CORRIGIDAS - Usando a mesma lógica do debug
        $statsQuery = "
            SELECT 
                COUNT(DISTINCT l.id) as total_lojas,
                COUNT(DISTINCT CASE WHEN cs.saldo_disponivel > 0 THEN cs.loja_id END) as lojas_com_saldo,
                COALESCE(SUM(cs.saldo_disponivel), 0) as total_saldo_acumulado,
                COALESCE(SUM(tsu.valor_usado), 0) as total_saldo_usado,
                COUNT(DISTINCT CASE WHEN l.status = 'aprovado' THEN l.id END) as lojas_aprovadas,
                COUNT(DISTINCT CASE WHEN l.status = 'pendente' THEN l.id END) as lojas_pendentes
            FROM lojas l
            LEFT JOIN cashback_saldos cs ON l.id = cs.loja_id
            LEFT JOIN transacoes_cashback tc ON l.id = tc.loja_id AND tc.status = 'aprovado'
            LEFT JOIN transacoes_saldo_usado tsu ON tc.id = tsu.transacao_id
            $whereClause
        ";
        
        $statsStmt = $db->prepare($statsQuery);
        $statsStmt->execute($params);
        $statistics = $statsStmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug das estatísticas
        error_log("STATS DEBUG: " . json_encode($statistics));
        
        // Obter categorias distintas
        $categoriesQuery = "SELECT DISTINCT categoria FROM lojas WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria";
        $categoriesStmt = $db->query($categoriesQuery);
        $categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Calcular paginação
        $totalPages = ceil($totalCount / $limit);
        
        return [
            'status' => true,
            'data' => [
                'lojas' => $stores,
                'estatisticas' => $statistics,
                'categorias' => $categories,
                'paginacao' => [
                    'pagina_atual' => $page,
                    'total_paginas' => $totalPages,
                    'total_itens' => $totalCount,
                    'itens_por_pagina' => $limit
                ]
            ]
        ];
        
    } catch (PDOException $e) {
        error_log('Erro ao gerenciar lojas com saldo: ' . $e->getMessage());
        return ['status' => false, 'message' => 'Erro ao carregar dados das lojas.'];
    }
}

    /**
    * Obter detalhes de uma loja com informações de saldo
    * 
    * @param int $storeId ID da loja
    * @return array Resultado da operação
    */
    public static function getStoreDetailsWithBalance($storeId) {
        try {
            // Verificar se é um administrador
            if (!self::validateAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            $db = Database::getConnection();
            
            // Buscar dados da loja
            $storeStmt = $db->prepare("SELECT * FROM lojas WHERE id = ?");
            $storeStmt->execute([$storeId]);
            $store = $storeStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$store) {
                return ['status' => false, 'message' => 'Loja não encontrada.'];
            }
            
            // Buscar endereço da loja
            $addressStmt = $db->prepare("SELECT * FROM lojas_endereco WHERE loja_id = ?");
            $addressStmt->execute([$storeId]);
            $address = $addressStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($address) {
                $store['endereco'] = $address;
            }
            
            // Buscar estatísticas básicas da loja
            $statsStmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_transacoes,
                    COALESCE(SUM(valor_total), 0) as total_vendas,
                    COALESCE(SUM(valor_cliente), 0) as total_cashback,
                    COALESCE(AVG(valor_total), 0) as ticket_medio
                FROM transacoes_cashback
                WHERE loja_id = ? AND status = 'aprovado'
            ");
            $statsStmt->execute([$storeId]);
            $statistics = $statsStmt->fetch(PDO::FETCH_ASSOC);
            
            // Estatísticas de saldo (valores padrão por enquanto)
            $balanceStats = [
                'total_saldo_clientes' => 0,
                'clientes_com_saldo' => 0,
                'total_saldo_usado' => 0,
                'total_transacoes' => $statistics['total_transacoes'] ?? 0,
                'transacoes_com_saldo' => 0
            ];
            
            // Buscar transações recentes
            $transStmt = $db->prepare("
                SELECT t.*, u.nome as cliente_nome
                FROM transacoes_cashback t
                LEFT JOIN usuarios u ON t.usuario_id = u.id
                WHERE t.loja_id = ?
                ORDER BY t.data_transacao DESC
                LIMIT 5
            ");
            $transStmt->execute([$storeId]);
            $transactions = $transStmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'status' => true,
                'data' => [
                    'loja' => $store,
                    'estatisticas' => $statistics,
                    'estatisticas_saldo' => $balanceStats,
                    'transacoes' => $transactions
                ]
            ];
            
        } catch (Exception $e) {
            error_log('Erro ao obter detalhes da loja: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao carregar detalhes da loja.'];
        }
    }

    /**
    * Atualiza dados de um usuário
    * 
    * @param int $userId ID do usuário
    * @param array $data Novos dados do usuário
    * @return array Resultado da operação
    */
    public static function updateUser($userId, $data) {
        try {
            if (!self::validateAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }

            $db = Database::getConnection();

            $checkStmt = $db->prepare("SELECT id FROM usuarios WHERE id = :user_id");
            $checkStmt->bindParam(':user_id', $userId);
            $checkStmt->execute();

            if ($checkStmt->rowCount() == 0) {
                return ['status' => false, 'message' => 'Usuário não encontrado.'];
            }

            $updateFields = [];
            $params = [':user_id' => $userId];

            if (isset($data['nome']) && !empty($data['nome'])) {
                $updateFields[] = "nome = :nome";
                $params[':nome'] = trim($data['nome']);
            }

            if (isset($data['email']) && !empty($data['email'])) {
                $emailCheckStmt = $db->prepare("SELECT id FROM usuarios WHERE email = :email AND id != :user_id");
                $emailCheckStmt->bindParam(':email', $data['email']);
                $emailCheckStmt->bindParam(':user_id', $userId);
                $emailCheckStmt->execute();

                if ($emailCheckStmt->rowCount() > 0) {
                    return ['status' => false, 'message' => 'Este email já está sendo usado por outro usuário.'];
                }

                $updateFields[] = "email = :email";
                $params[':email'] = trim($data['email']);
            }

            if (isset($data['telefone'])) {
                $updateFields[] = "telefone = :telefone";
                $params[':telefone'] = trim($data['telefone']);
            }

            if (isset($data['tipo']) && !empty($data['tipo'])) {
                $validTypes = [USER_TYPE_CLIENT, USER_TYPE_ADMIN, USER_TYPE_STORE];
                if (in_array($data['tipo'], $validTypes)) {
                    $updateFields[] = "tipo = :tipo";
                    $params[':tipo'] = $data['tipo'];
                }
            }

            if (isset($data['status']) && !empty($data['status'])) {
                $validStatus = [USER_ACTIVE, USER_INACTIVE, USER_BLOCKED];
                if (in_array($data['status'], $validStatus)) {
                    $updateFields[] = "status = :status";
                    $params[':status'] = $data['status'];
                }
            }

            if (isset($data['senha']) && !empty(trim($data['senha']))) {
                $senha = trim($data['senha']);

                if (strlen($senha) < PASSWORD_MIN_LENGTH) {
                    return ['status' => false, 'message' => 'A senha deve ter no mínimo ' . PASSWORD_MIN_LENGTH . ' caracteres.'];
                }

                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                $updateFields[] = "senha_hash = :senha_hash";
                $params[':senha_hash'] = $senha_hash;
            }

            $storeMvpValue = null;
            if (isset($data['loja_mvp']) && $data['loja_mvp'] !== '') {
                $storeMvpValue = strtolower($data['loja_mvp']) === 'sim' ? 'sim' : 'nao';
            }

            if (empty($updateFields) && $storeMvpValue === null) {
                return ['status' => false, 'message' => 'Nenhum dado válido para atualizar.'];
            }

            $db->beginTransaction();

            if (!empty($updateFields)) {
                $query = "UPDATE usuarios SET " . implode(', ', $updateFields) . " WHERE id = :user_id";
                $stmt = $db->prepare($query);

                foreach ($params as $param => $value) {
                    $stmt->bindValue($param, $value);
                }

                if (!$stmt->execute()) {
                    $db->rollBack();
                    return ['status' => false, 'message' => 'Falha ao atualizar usuário no banco de dados.'];
                }
            }

            if ($storeMvpValue !== null) {
                $storeStmt = $db->prepare("SELECT id FROM lojas WHERE usuario_id = :user_id LIMIT 1");
                $storeStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $storeStmt->execute();
                $store = $storeStmt->fetch(PDO::FETCH_ASSOC);

                if ($store) {
                    $updateStoreStmt = $db->prepare("UPDATE lojas SET mvp = :mvp WHERE id = :store_id");
                    $updateStoreStmt->bindParam(':mvp', $storeMvpValue);
                    $updateStoreStmt->bindParam(':store_id', $store['id'], PDO::PARAM_INT);

                    if (!$updateStoreStmt->execute()) {
                        $db->rollBack();
                        return ['status' => false, 'message' => 'Falha ao atualizar configuracao MVP da loja.'];
                    }
                } else {
                    $db->rollBack();
                    return ['status' => false, 'message' => 'Loja vinculada nao encontrada para atualizar o MVP.'];
                }
            }

            $db->commit();

            return ['status' => true, 'message' => 'Usuário atualizado com sucesso.'];

        } catch (PDOException $e) {
            if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Erro ao atualizar usuário: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao atualizar usuário: ' . $e->getMessage()];
        }
    }
    public static function updateUserStatus($userId, $status) {
        try {
            // Verificar se é um administrador
            if (!self::validateAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            // Validar status
            $validStatus = [USER_ACTIVE, 'inativo', 'bloqueado'];
            if (!in_array($status, $validStatus)) {
                return ['status' => false, 'message' => 'Status inválido.'];
            }
            
            $db = Database::getConnection();
            
            // Verificar se o usuário existe
            $checkStmt = $db->prepare("SELECT id FROM usuarios WHERE id = :user_id");
            $checkStmt->bindParam(':user_id', $userId);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() == 0) {
                return ['status' => false, 'message' => 'Usuário não encontrado.'];
            }
            
            // Atualizar status
            $updateStmt = $db->prepare("UPDATE usuarios SET status = :status WHERE id = :user_id");
            $updateStmt->bindParam(':status', $status);
            $updateStmt->bindParam(':user_id', $userId);
            $success = $updateStmt->execute();
            
            if ($success) {
                return ['status' => true, 'message' => 'Status do usuário atualizado com sucesso.'];
            } else {
                return ['status' => false, 'message' => 'Falha ao atualizar status do usuário.'];
            }
            
        } catch (PDOException $e) {
            error_log('Erro ao atualizar status do usuário: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao atualizar status do usuário: ' . $e->getMessage()];
        }
    }
    
    /**
     * Gerencia lojas do sistema
     * 
     * @param array $filters Filtros para a listagem
     * @param int $page Página atual
     * @return array Lista de lojas
     */
    public static function manageStores($filters = [], $page = 1) {
        try {
            // Verificar se é um administrador
            if (!self::validateAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            $db = Database::getConnection();
            
            // Debug
            error_log("manageStores - Filtros recebidos: " . print_r($filters, true));
            
            // Preparar consulta base
            $query = "
                SELECT *
                FROM lojas
                WHERE 1=1
            ";
            
            $params = [];
            
            // Aplicar filtros
            if (!empty($filters)) {
                // Filtro por status
                if (isset($filters['status']) && !empty($filters['status'])) {
                    $status = strtolower($filters['status']); // Normalizar para minúsculo
                    $query .= " AND status = :status";
                    $params[':status'] = $status;
                }
                
                // Filtro por categoria
                if (isset($filters['categoria']) && !empty($filters['categoria'])) {
                    $query .= " AND categoria = :categoria";
                    $params[':categoria'] = $filters['categoria'];
                }
                
                // Filtro por busca (nome, razão social ou CNPJ)
                if (isset($filters['busca']) && !empty($filters['busca'])) {
                    $query .= " AND (nome_fantasia LIKE :busca OR razao_social LIKE :busca OR cnpj LIKE :busca)";
                    $params[':busca'] = '%' . $filters['busca'] . '%';
                }
            }
            
            // Log da consulta para debug
            error_log("Query SQL: " . $query);
            error_log("Params: " . print_r($params, true));
            
            // Calcular total de registros para paginação
            $countQuery = "
                SELECT COUNT(*) as total 
                FROM lojas 
                WHERE 1=1
            ";
            
            // Aplicar os mesmos filtros à consulta de contagem
            if (!empty($filters)) {
                if (isset($filters['status']) && !empty($filters['status'])) {
                    $countQuery .= " AND status = :status";
                }
                
                if (isset($filters['categoria']) && !empty($filters['categoria'])) {
                    $countQuery .= " AND categoria = :categoria";
                }
                
                if (isset($filters['busca']) && !empty($filters['busca'])) {
                    $countQuery .= " AND (nome_fantasia LIKE :busca OR razao_social LIKE :busca OR cnpj LIKE :busca)";
                }
            }
            
            $countStmt = $db->prepare($countQuery);
            
            // Bind params para consulta de contagem
            foreach ($params as $param => $value) {
                $countStmt->bindValue($param, $value);
            }
            
            $countStmt->execute();
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Log da contagem
            error_log("Total de registros: " . $totalCount);
            
            // Ordenação (padrão: nome_fantasia)
            $orderBy = isset($filters['order_by']) ? $filters['order_by'] : 'nome_fantasia';
            $orderDir = isset($filters['order_dir']) && strtolower($filters['order_dir']) == 'desc' ? 'DESC' : 'ASC';
            $query .= " ORDER BY $orderBy $orderDir";
            
            // Adicionar paginação
            $perPage = ITEMS_PER_PAGE;
            $offset = ($page - 1) * $perPage;
            $query .= " LIMIT $offset, $perPage";
            
            // Executar consulta
            $stmt = $db->prepare($query);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            
            $stmt->execute();
            $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Log dos resultados
            error_log("Lojas encontradas: " . count($stores));
            
            // Estatísticas das lojas - simplificado para evitar erros
            $statistics = [
                'total_lojas' => $totalCount,
                'total_aprovadas' => 0,
                'total_pendentes' => 0,
                'total_rejeitadas' => 0
            ];
            
            // Obter categorias disponíveis para filtro
            $categoriesStmt = $db->query("SELECT DISTINCT categoria FROM lojas WHERE categoria IS NOT NULL ORDER BY categoria");
            $categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Calcular informações de paginação
            $totalPages = ceil($totalCount / $perPage);
            
            return [
                'status' => true,
                'data' => [
                    'lojas' => $stores,
                    'estatisticas' => $statistics,
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
            // Log detalhado do erro
            error_log('Erro ao gerenciar lojas (PDOException): ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao carregar lojas: ' . $e->getMessage()];
        } catch (Exception $e) {
            // Log detalhado do erro
            error_log('Erro ao gerenciar lojas (Exception): ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao carregar lojas: ' . $e->getMessage()];
        }
    }
    // Adicionar este método no AdminController.php

/**
 * Obtém lojas aprovadas sem usuário vinculado
 * 
 * @return array Lista de lojas sem usuário
 */
public static function getAvailableStores() {
    try {
        // Verificar se é um administrador
        if (!self::validateAdmin()) {
            return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
        }
        
        $db = Database::getConnection();
        
        // Buscar lojas aprovadas sem usuário vinculado
        $stmt = $db->prepare("
            SELECT id, nome_fantasia, razao_social, cnpj, email, telefone, categoria, mvp
            FROM lojas 
            WHERE status = :status AND (usuario_id IS NULL OR usuario_id = 0)
            ORDER BY nome_fantasia ASC
        ");
        $status = STORE_APPROVED;
        $stmt->bindParam(':status', $status);
        $stmt->execute();
        
        $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'status' => true,
            'data' => $stores
        ];
        
    } catch (PDOException $e) {
        error_log('Erro ao obter lojas disponíveis: ' . $e->getMessage());
        return ['status' => false, 'message' => 'Erro ao carregar lojas disponíveis.'];
    }
}

    /**
    * Obtém dados de uma loja específica pelo email
    * 
    * @param string $email Email da loja
    * @return array Dados da loja
    */
    public static function getStoreByEmail($email) {
        try {
            // Verificar se é um administrador
            if (!self::validateAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            $db = Database::getConnection();
            
            // Buscar loja pelo email
            $stmt = $db->prepare("
                SELECT id, nome_fantasia, razao_social, cnpj, email, telefone, categoria, mvp
                FROM lojas 
                WHERE email = :email AND status = :status AND (usuario_id IS NULL OR usuario_id = 0)
            ");
            $stmt->bindParam(':email', $email);
            $status = STORE_APPROVED;
            $stmt->bindParam(':status', $status);
            $stmt->execute();
            
            $store = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$store) {
                return ['status' => false, 'message' => 'Loja não encontrada ou já vinculada a um usuário.'];
            }
            
            return [
                'status' => true,
                'data' => $store
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao obter dados da loja: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao carregar dados da loja.'];
        }
    }
    /**
    * Obtém detalhes de uma loja específica
    * 
    * @param int $storeId ID da loja
    * @return array Dados da loja
    */
    public static function getStoreDetails($storeId) {
        try {
            // Verificar se é um administrador
            if (!self::validateAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            $db = Database::getConnection();
            
            // Obter dados da loja
            $stmt = $db->prepare("SELECT * FROM lojas WHERE id = :store_id");
            $stmt->bindParam(':store_id', $storeId);
            $stmt->execute();
            $store = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$store) {
                return ['status' => false, 'message' => 'Loja não encontrada.'];
            }
            
            // Estatísticas da loja
            $statsStmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_transacoes,
                    SUM(valor_total) as total_vendas,
                    SUM(valor_cashback) as total_cashback
                FROM transacoes_cashback
                WHERE loja_id = :store_id
            ");
            $statsStmt->bindParam(':store_id', $storeId);
            $statsStmt->execute();
            $statistics = $statsStmt->fetch(PDO::FETCH_ASSOC);
            
            // Comissões da loja
            $comissionStmt = $db->prepare("
                SELECT SUM(valor_comissao) as total_comissao
                FROM transacoes_comissao
                WHERE loja_id = :store_id AND tipo_usuario = :tipo AND status = :status
            ");
            $comissionStmt->bindParam(':store_id', $storeId);
            $tipo = USER_TYPE_STORE;
            $comissionStmt->bindParam(':tipo', $tipo);
            $status = TRANSACTION_APPROVED;
            $comissionStmt->bindParam(':status', $status);
            $comissionStmt->execute();
            $comission = $comissionStmt->fetch(PDO::FETCH_ASSOC);
            
            // Últimas transações da loja
            $transStmt = $db->prepare("
                SELECT t.*, u.nome as cliente_nome
                FROM transacoes_cashback t
                JOIN usuarios u ON t.usuario_id = u.id
                WHERE t.loja_id = :store_id
                ORDER BY t.data_transacao DESC
                LIMIT 5
            ");
            $transStmt->bindParam(':store_id', $storeId);
            $transStmt->execute();
            $transactions = $transStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Endereço da loja, se existir
            $addressStmt = $db->prepare("
                SELECT *
                FROM lojas_endereco
                WHERE loja_id = :store_id
                LIMIT 1
            ");
            $addressStmt->bindParam(':store_id', $storeId);
            $addressStmt->execute();
            $address = $addressStmt->fetch(PDO::FETCH_ASSOC);
            
            // Contatos adicionais da loja, se existirem
            $contactsStmt = $db->prepare("
                SELECT *
                FROM lojas_contato
                WHERE loja_id = :store_id
            ");
            $contactsStmt->bindParam(':store_id', $storeId);
            $contactsStmt->execute();
            $contacts = $contactsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'status' => true,
                'data' => [
                    'loja' => $store,
                    'estatisticas' => $statistics,
                    'comissao' => $comission,
                    'transacoes' => $transactions,
                    'endereco' => $address ?: null,
                    'contatos' => $contacts ?: []
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao obter detalhes da loja: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao carregar detalhes da loja. Tente novamente.'];
        }
    }
    
    /**
     * Aprova ou rejeita uma loja
     * 
     * @param int $storeId ID da loja
     * @param string $status Novo status (aprovado, rejeitado)
     * @param string $observacao Observação sobre a decisão
     * @return array Resultado da operação
     */
    public static function updateStoreStatus($storeId, $status, $observacao = '') {
        try {
            // Verificar se é um administrador
            if (!self::validateAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            // Validar status
            $validStatus = [STORE_APPROVED, STORE_REJECTED];
            if (!in_array($status, $validStatus)) {
                return ['status' => false, 'message' => 'Status inválido.'];
            }
            
            $db = Database::getConnection();
            
            // Buscar dados da loja incluindo usuario_id
            $checkStmt = $db->prepare("SELECT * FROM lojas WHERE id = :store_id");
            $checkStmt->bindParam(':store_id', $storeId);
            $checkStmt->execute();
            $store = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$store) {
                return ['status' => false, 'message' => 'Loja não encontrada.'];
            }
            
            // INICIAR TRANSAÇÃO PARA ATUALIZAR LOJA E USUÁRIO JUNTOS
            $db->beginTransaction();
            
            // 1. ATUALIZAR STATUS DA LOJA
            $updateStoreStmt = $db->prepare("
                UPDATE lojas 
                SET status = :status, observacao = :observacao, data_aprovacao = :data_aprovacao
                WHERE id = :store_id
            ");
            $updateStoreStmt->bindParam(':status', $status);
            $updateStoreStmt->bindParam(':observacao', $observacao);
            $dataAprovacao = ($status === STORE_APPROVED) ? date('Y-m-d H:i:s') : null;
            $updateStoreStmt->bindParam(':data_aprovacao', $dataAprovacao);
            $updateStoreStmt->bindParam(':store_id', $storeId);
            
            if (!$updateStoreStmt->execute()) {
                $db->rollBack();
                return ['status' => false, 'message' => 'Erro ao atualizar status da loja.'];
            }
            
            // 2. ATUALIZAR STATUS DO USUÁRIO CORRESPONDENTE
            if ($store['usuario_id']) {
                $userStatus = ($status === STORE_APPROVED) ? USER_ACTIVE : USER_INACTIVE;
                
                $updateUserStmt = $db->prepare("
                    UPDATE usuarios 
                    SET status = :status 
                    WHERE id = :user_id
                ");
                $updateUserStmt->bindParam(':status', $userStatus);
                $updateUserStmt->bindParam(':user_id', $store['usuario_id']);
                
                if (!$updateUserStmt->execute()) {
                    $db->rollBack();
                    return ['status' => false, 'message' => 'Erro ao atualizar status do usuário da loja.'];
                }
            }
            
            // CONFIRMAR TODAS AS ALTERAÇÕES
            $db->commit();
            
            // Preparar notificação por email
            $statusLabels = [
                STORE_APPROVED => 'aprovada',
                STORE_REJECTED => 'rejeitada'
            ];
            
            $subject = 'Atualização de status da sua loja - Klube Cash';
            $message = "
                <h3>Olá, {$store['razao_social']}!</h3>
                <p>Informamos que sua loja <strong>{$store['nome_fantasia']}</strong> foi <strong>{$statusLabels[$status]}</strong> no Klube Cash.</p>
            ";
            
            if ($status == STORE_APPROVED) {
                $message .= "
                    <p>🎉 <strong>Parabéns! Sua loja foi aprovada!</strong></p>
                    <p>A partir de agora você pode:</p>
                    <ul>
                        <li>Fazer login no sistema com seu email e senha</li>
                        <li>Acessar seu painel de controle</li>
                        <li>Registrar vendas e gerenciar cashback</li>
                        <li>Sua loja já está visível para os clientes</li>
                    </ul>
                    <p><strong>Para acessar:</strong> <a href='" . LOGIN_URL . "'>Clique aqui para fazer login</a></p>
                    <p>Use o mesmo email e senha que cadastrou durante o registro da loja.</p>
                ";
            } else if ($status == STORE_REJECTED) {
                $message .= "<p>Infelizmente, sua solicitação não foi aprovada neste momento.</p>";
                
                if (!empty($observacao)) {
                    $message .= "<p><strong>Motivo:</strong> " . nl2br(htmlspecialchars($observacao)) . "</p>";
                }
                
                $message .= "<p>Você pode entrar em contato com nosso suporte para mais informações ou fazer uma nova solicitação.</p>";
            }
            
            $message .= "<p>Atenciosamente,<br>Equipe Klube Cash</p>";
            
            Email::send($store['email'], $subject, $message, $store['nome_fantasia']);
            
            return ['status' => true, 'message' => 'Status da loja e usuário atualizados com sucesso.'];
            
        } catch (PDOException $e) {
            // Reverter alterações em caso de erro
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            
            error_log('Erro ao atualizar status da loja: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao atualizar status da loja. Tente novamente.'];
        }
    }
    
    /**
     * Atualiza dados de uma loja
     * 
     * @param int $storeId ID da loja
     * @param array $data Novos dados da loja
     * @return array Resultado da operação
     */
    public static function updateStore($storeId, $data) {
        try {
            // Verificar se é um administrador
            if (!self::validateAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            $db = Database::getConnection();
            
            // Verificar se a loja existe
            $checkStmt = $db->prepare("SELECT id FROM lojas WHERE id = :store_id");
            $checkStmt->bindParam(':store_id', $storeId);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() == 0) {
                return ['status' => false, 'message' => 'Loja não encontrada.'];
            }
            
            // Iniciar transação
            $db->beginTransaction();
            
            // Preparar campos para atualização
            $updateFields = [];
            $params = [':store_id' => $storeId];
            
            // Campos básicos
            $basicFields = [
                'nome_fantasia', 'razao_social', 'cnpj', 'email', 'telefone',
                'categoria', 'porcentagem_cashback', 'descricao', 'website'
            ];
            
            foreach ($basicFields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "$field = :$field";
                    $params[":$field"] = $data[$field];
                }
            }
            
            // Se houver campos para atualizar
            if (!empty($updateFields)) {
                $updateQuery = "UPDATE lojas SET " . implode(', ', $updateFields) . " WHERE id = :store_id";
                $updateStmt = $db->prepare($updateQuery);
                foreach ($params as $param => $value) {
                    $updateStmt->bindValue($param, $value);
                }
                $updateStmt->execute();
            }
            
            // Atualizar endereço, se fornecido
            if (isset($data['endereco']) && !empty($data['endereco'])) {
                // Verificar se já existe endereço
                $checkAddrStmt = $db->prepare("SELECT id FROM lojas_endereco WHERE loja_id = :store_id LIMIT 1");
                $checkAddrStmt->bindParam(':store_id', $storeId);
                $checkAddrStmt->execute();
                
                if ($checkAddrStmt->rowCount() > 0) {
                    // Atualizar endereço existente
                    $addrId = $checkAddrStmt->fetch(PDO::FETCH_ASSOC)['id'];
                    $updateAddrStmt = $db->prepare("
                        UPDATE lojas_endereco 
                        SET 
                            cep = :cep,
                            logradouro = :logradouro,
                            numero = :numero,
                            complemento = :complemento,
                            bairro = :bairro,
                            cidade = :cidade,
                            estado = :estado
                        WHERE id = :id
                    ");
                    $updateAddrStmt->bindParam(':id', $addrId);
                } else {
                    // Inserir novo endereço
                    $updateAddrStmt = $db->prepare("
                        INSERT INTO lojas_endereco 
                        (loja_id, cep, logradouro, numero, complemento, bairro, cidade, estado)
                        VALUES
                        (:store_id, :cep, :logradouro, :numero, :complemento, :bairro, :cidade, :estado)
                    ");
                    $updateAddrStmt->bindParam(':store_id', $storeId);
                }
                
                // Bind dos parâmetros comuns
                $updateAddrStmt->bindParam(':cep', $data['endereco']['cep']);
                $updateAddrStmt->bindParam(':logradouro', $data['endereco']['logradouro']);
                $updateAddrStmt->bindParam(':numero', $data['endereco']['numero']);
                $updateAddrStmt->bindParam(':complemento', $data['endereco']['complemento'] ?? '');
                $updateAddrStmt->bindParam(':bairro', $data['endereco']['bairro']);
                $updateAddrStmt->bindParam(':cidade', $data['endereco']['cidade']);
                $updateAddrStmt->bindParam(':estado', $data['endereco']['estado']);
                $updateAddrStmt->execute();
            }
            
            // Confirmar transação
            $db->commit();
            
            return ['status' => true, 'message' => 'Dados da loja atualizados com sucesso.'];
            
        } catch (PDOException $e) {
            // Reverter transação em caso de erro
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            
            error_log('Erro ao atualizar loja: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao atualizar loja. Tente novamente.'];
        }
    }
    
    /**
     * Gerencia transações do sistema
     * 
     * @param array $filters Filtros para a listagem
     * @param int $page Página atual
     * @return array Lista de transações
     */
    public static function manageTransactions($filters = [], $page = 1) {
        try {
            // Verificar se é um administrador
            if (!self::validateAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            $db = Database::getConnection();
            
            // Preparar consulta base
            $query = "
                SELECT t.*, u.nome as cliente_nome, l.nome_fantasia as loja_nome
                FROM transacoes_cashback t
                JOIN usuarios u ON t.usuario_id = u.id
                JOIN lojas l ON t.loja_id = l.id
                WHERE 1=1
            ";
            
            $params = [];
            
            // Aplicar filtros
            if (!empty($filters)) {
                // Filtro por status
                if (isset($filters['status']) && !empty($filters['status'])) {
                    $query .= " AND t.status = :status";
                    $params[':status'] = $filters['status'];
                }
                
                // Filtro por loja
                if (isset($filters['loja_id']) && !empty($filters['loja_id'])) {
                    $query .= " AND t.loja_id = :loja_id";
                    $params[':loja_id'] = $filters['loja_id'];
                }
                
                // Filtro por cliente
                if (isset($filters['usuario_id']) && !empty($filters['usuario_id'])) {
                    $query .= " AND t.usuario_id = :usuario_id";
                    $params[':usuario_id'] = $filters['usuario_id'];
                }
                
                // Filtro por valor mínimo
                if (isset($filters['valor_min']) && !empty($filters['valor_min'])) {
                    $query .= " AND t.valor_total >= :valor_min";
                    $params[':valor_min'] = $filters['valor_min'];
                }
                
                // Filtro por valor máximo
                if (isset($filters['valor_max']) && !empty($filters['valor_max'])) {
                    $query .= " AND t.valor_total <= :valor_max";
                    $params[':valor_max'] = $filters['valor_max'];
                }
                
                // Filtro por data inicial
                if (isset($filters['data_inicio']) && !empty($filters['data_inicio'])) {
                    $query .= " AND t.data_transacao >= :data_inicio";
                    $params[':data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
                }
                
                // Filtro por data final
                if (isset($filters['data_fim']) && !empty($filters['data_fim'])) {
                    $query .= " AND t.data_transacao <= :data_fim";
                    $params[':data_fim'] = $filters['data_fim'] . ' 23:59:59';
                }
                
                // Filtro por busca (código da transação)
                if (isset($filters['busca']) && !empty($filters['busca'])) {
                    $query .= " AND t.codigo_transacao LIKE :busca";
                    $params[':busca'] = '%' . $filters['busca'] . '%';
                }
            }
            
            // Ordenação (padrão: data da transação decrescente)
            $orderBy = isset($filters['order_by']) ? $filters['order_by'] : 't.data_transacao';
            $orderDir = isset($filters['order_dir']) && strtolower($filters['order_dir']) == 'asc' ? 'ASC' : 'DESC';
            $query .= " ORDER BY $orderBy $orderDir";
            
            $countStmt = $db->prepare(str_replace('id, nome, email, tipo, status, data_criacao, ultimo_login', 'COUNT(*) as total', $query));
            
            // Adicionar paginação
            $perPage = ITEMS_PER_PAGE;
            $offset = ($page - 1) * $perPage;
            $query .= " LIMIT $offset, $perPage";
            
            // Executar consulta
            $stmt = $db->prepare($query);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            $stmt->execute();
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Estatísticas das transações
            $statsQuery = "
                SELECT 
                    COUNT(*) as total_transacoes,
                    SUM(valor_total) as total_vendas,
                    SUM(valor_cashback) as total_cashback,
                    AVG(valor_cashback) as media_cashback,
                    SUM(CASE WHEN status = 'aprovado' THEN 1 ELSE 0 END) as total_aprovadas,
                    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as total_pendentes,
                    SUM(CASE WHEN status = 'cancelado' THEN 1 ELSE 0 END) as total_canceladas
                FROM transacoes_cashback t
                WHERE 1=1
            ";
            
            // Aplicar mesmos filtros nas estatísticas
            if (!empty($filters)) {
                $statsQuery = str_replace('1=1', '1=1 ' . substr($query, strpos($query, 'WHERE 1=1') + 8, strpos($query, 'ORDER BY') - strpos($query, 'WHERE 1=1') - 8), $statsQuery);
            }
            
            $statsStmt = $db->prepare($statsQuery);
            foreach ($params as $param => $value) {
                $statsStmt->bindValue($param, $value);
            }
            $statsStmt->execute();
            $statistics = $statsStmt->fetch(PDO::FETCH_ASSOC);
            
            // Obter lojas para filtro
            $storesStmt = $db->query("SELECT id, nome_fantasia FROM lojas WHERE status = 'aprovado' ORDER BY nome_fantasia");
            $stores = $storesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular informações de paginação
            $totalPages = ceil($totalCount / $perPage);
            
            return [
                'status' => true,
                'data' => [
                    'transacoes' => $transactions,
                    'estatisticas' => $statistics,
                    'lojas' => $stores,
                    'paginacao' => [
                        'total' => $totalCount,
                        'por_pagina' => $perPage,
                        'pagina_atual' => $page,
                        'total_paginas' => $totalPages
                    ]
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao gerenciar transações: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao carregar transações. Tente novamente.'];
        }
    }
    
    /**
     * Obtém detalhes de uma transação específica
     * 
     * @param int $transactionId ID da transação
     * @return array Dados da transação
     */
    public static function getTransactionDetails($transactionId) {
        try {
            // Verificar se é um administrador
            if (!self::validateAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            $db = Database::getConnection();
            
            // Obter detalhes da transação
            $stmt = $db->prepare("
                SELECT t.*, u.nome as cliente_nome, u.email as cliente_email, 
                       l.nome_fantasia as loja_nome, l.razao_social as loja_razao_social
                FROM transacoes_cashback t
                JOIN usuarios u ON t.usuario_id = u.id
                JOIN lojas l ON t.loja_id = l.id
                WHERE t.id = :transaction_id
            ");
            $stmt->bindParam(':transaction_id', $transactionId);
            $stmt->execute();
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$transaction) {
                return ['status' => false, 'message' => 'Transação não encontrada.'];
            }
            
            // Obter comissões relacionadas
            $comissionsStmt = $db->prepare("
                SELECT *
                FROM transacoes_comissao
                WHERE transacao_id = :transaction_id
            ");
            $comissionsStmt->bindParam(':transaction_id', $transactionId);
            $comissionsStmt->execute();
            $comissions = $comissionsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Obter histórico de status, se existir
            $historyStmt = $db->prepare("
                SELECT *
                FROM transacoes_status_historico
                WHERE transacao_id = :transaction_id
                ORDER BY data_alteracao DESC
            ");
            $historyStmt->bindParam(':transaction_id', $transactionId);
            $historyStmt->execute();
            $statusHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'status' => true,
                'data' => [
                    'transacao' => $transaction,
                    'comissoes' => $comissions,
                    'historico_status' => $statusHistory
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao obter detalhes da transação: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao carregar detalhes da transação. Tente novamente.'];
        }
    }
    
    /**
     * Atualiza o status de uma transação
     * 
     * @param int $transactionId ID da transação
     * @param string $status Novo status (aprovado, cancelado)
     * @param string $observacao Observação sobre a mudança
     * @return array Resultado da operação
     */
    public static function updateTransactionStatus($transactionId, $status, $observacao = '') {
        try {
            // Verificar se é um administrador
            if (!self::validateAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            // Validar status
            $validStatus = [TRANSACTION_APPROVED, TRANSACTION_PENDING, TRANSACTION_CANCELED];
            if (!in_array($status, $validStatus)) {
                return ['status' => false, 'message' => 'Status inválido.'];
            }
            
            $db = Database::getConnection();
            
            // Iniciar transação
            $db->beginTransaction();
            
            // Verificar se a transação existe
            $checkStmt = $db->prepare("
                SELECT t.*, u.nome as cliente_nome, u.email as cliente_email, l.nome_fantasia as loja_nome
                FROM transacoes_cashback t
                JOIN usuarios u ON t.usuario_id = u.id
                JOIN lojas l ON t.loja_id = l.id
                WHERE t.id = :transaction_id
            ");
            $checkStmt->bindParam(':transaction_id', $transactionId);
            $checkStmt->execute();
            $transaction = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$transaction) {
                return ['status' => false, 'message' => 'Transação não encontrada.'];
            }
            
            // Se o status for diferente, atualizar
            if ($transaction['status'] != $status) {
                // Atualizar status da transação
                $updateStmt = $db->prepare("UPDATE transacoes_cashback SET status = :status WHERE id = :transaction_id");
                $updateStmt->bindParam(':status', $status);
                $updateStmt->bindParam(':transaction_id', $transactionId);
                $updateStmt->execute();
                
                // Atualizar status das comissões relacionadas
                $updateComissionStmt = $db->prepare("UPDATE transacoes_comissao SET status = :status WHERE transacao_id = :transaction_id");
                $updateComissionStmt->bindParam(':status', $status);
                $updateComissionStmt->bindParam(':transaction_id', $transactionId);
                $updateComissionStmt->execute();
                
                // Registrar no histórico de status
                $historyStmt = $db->prepare("
                    INSERT INTO transacoes_status_historico (
                        transacao_id, status_anterior, status_novo, observacao, data_alteracao
                    ) VALUES (
                        :transaction_id, :status_anterior, :status_novo, :observacao, NOW()
                    )
                ");
                $historyStmt->bindParam(':transaction_id', $transactionId);
                $historyStmt->bindParam(':status_anterior', $transaction['status']);
                $historyStmt->bindParam(':status_novo', $status);
                $historyStmt->bindParam(':observacao', $observacao);
                $historyStmt->execute();
                
                // Enviar notificação para o cliente
                $statusLabels = [
                    TRANSACTION_APPROVED => 'aprovada',
                    TRANSACTION_PENDING => 'pendente',
                    TRANSACTION_CANCELED => 'cancelada'
                ];
                
                $notifyStmt = $db->prepare("
                    INSERT INTO notificacoes (
                        usuario_id, titulo, mensagem, tipo, data_criacao, lida
                    ) VALUES (
                        :usuario_id, :titulo, :mensagem, :tipo, NOW(), 0
                    )
                ");
                
                $titulo = "Status da transação atualizado";
                $mensagem = "Sua transação de cashback na loja {$transaction['loja_nome']} foi {$statusLabels[$status]}.";
                $tipo = $status === TRANSACTION_APPROVED ? 'success' : ($status === TRANSACTION_CANCELED ? 'error' : 'info');
                
                $notifyStmt->bindParam(':usuario_id', $transaction['usuario_id']);
                $notifyStmt->bindParam(':titulo', $titulo);
                $notifyStmt->bindParam(':mensagem', $mensagem);
                $notifyStmt->bindParam(':tipo', $tipo);
                $notifyStmt->execute();
                
                // Enviar email para o cliente
                $subject = "Atualização de Transação - Klube Cash";
                $message = "
                    <h3>Olá, {$transaction['cliente_nome']}!</h3>
                    <p>Informamos que sua transação de cashback no valor de R$ " . number_format($transaction['valor_cashback'], 2, ',', '.') . " na loja <strong>{$transaction['loja_nome']}</strong> foi <strong>{$statusLabels[$status]}</strong>.</p>
                ";
                
                if (!empty($observacao)) {
                    $message .= "<p><strong>Observação:</strong> " . nl2br(htmlspecialchars($observacao)) . "</p>";
                }
                
                $message .= "<p>Para mais detalhes, acesse seu extrato de transações.</p>";
                $message .= "<p>Atenciosamente,<br>Equipe Klube Cash</p>";
                
                Email::send($transaction['cliente_email'], $subject, $message, $transaction['cliente_nome']);
            }
            
            // Confirmar transação
            $db->commit();
            
            return ['status' => true, 'message' => 'Status da transação atualizado com sucesso.'];
            
        } catch (PDOException $e) {
            // Reverter transação em caso de erro
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            
            error_log('Erro ao atualizar status da transação: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao atualizar status da transação. Tente novamente.'];
        }
    }
    
    /**
     * Gerencia configurações do sistema
     * 
     * @return array Configurações atuais
     */
    public static function getSettings() {
        try {
            // Verificar se é um administrador
            if (!self::validateAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            $db = Database::getConnection();
            
            // Obter configurações
            $stmt = $db->query("SELECT * FROM configuracoes_cashback ORDER BY id DESC LIMIT 1");
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Se não existirem configurações, usar valores padrão
            if (!$settings) {
                $settings = [
                    'porcentagem_total' => DEFAULT_CASHBACK_TOTAL,
                    'porcentagem_cliente' => DEFAULT_CASHBACK_CLIENT,
                    'porcentagem_admin' => DEFAULT_CASHBACK_ADMIN,
                    'porcentagem_loja' => DEFAULT_CASHBACK_STORE
                ];
            }
            
            return [
                'status' => true,
                'data' => $settings
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao obter configurações: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao carregar configurações. Tente novamente.'];
        }
    }
    // Adicionar TEMPORARIAMENTE no ClientController.php
    public static function getPartnerStores($userId, $filters = [], $page = 1) {
        try {
            $db = Database::getConnection();
            
            $offset = ($page - 1) * (defined('ITEMS_PER_PAGE') ? ITEMS_PER_PAGE : 10);
            $limit = defined('ITEMS_PER_PAGE') ? ITEMS_PER_PAGE : 10;
            
            // Query base
            $baseWhere = " WHERE l.status = 'aprovado'";
            $params = [];
            
            // Aplicar filtros
            if (!empty($filters['categoria'])) {
                $baseWhere .= " AND l.categoria = :categoria";
                $params[':categoria'] = $filters['categoria'];
            }
            
            if (!empty($filters['nome'])) {
                $baseWhere .= " AND l.nome_fantasia LIKE :nome";
                $params[':nome'] = '%' . $filters['nome'] . '%';
            }
            
            if (!empty($filters['cashback_min'])) {
                $baseWhere .= " AND l.porcentagem_cashback >= :cashback_min";
                $params[':cashback_min'] = floatval($filters['cashback_min']);
            }
            
            // Query principal
            $query = "
                SELECT 
                    l.*,
                    CASE WHEN f.id IS NOT NULL THEN 1 ELSE 0 END as is_favorite
                FROM lojas l
                LEFT JOIN favorites f ON f.store_id = l.id AND f.user_id = :user_id
                " . $baseWhere . "
                ORDER BY l.nome_fantasia
                LIMIT :limit OFFSET :offset
            ";
            
            $params[':user_id'] = $userId;
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;
            
            $stmt = $db->prepare($query);
            foreach ($params as $key => $value) {
                if (in_array($key, [':limit', ':offset'])) {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $value);
                }
            }
            $stmt->execute();
            $lojas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Buscar categorias disponíveis
            $categoriesQuery = "SELECT DISTINCT categoria FROM lojas WHERE status = 'aprovado' ORDER BY categoria";
            $categoriesStmt = $db->query($categoriesQuery);
            $categorias = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Estatísticas
            $statsQuery = "
                SELECT 
                    COUNT(*) as total_lojas,
                    COALESCE(AVG(porcentagem_cashback), 0) as media_cashback,
                    COALESCE(MAX(porcentagem_cashback), 0) as maior_cashback,
                    COALESCE(MIN(porcentagem_cashback), 0) as menor_cashback
                FROM lojas 
                WHERE status = 'aprovado'
            ";
            $statsStmt = $db->query($statsQuery);
            $estatisticas = $statsStmt->fetch(PDO::FETCH_ASSOC);
            
            // Contar total para paginação
            $countQuery = "SELECT COUNT(*) as total FROM lojas l" . $baseWhere;
            $countStmt = $db->prepare($countQuery);
            foreach ($params as $key => $value) {
                if (!in_array($key, [':limit', ':offset', ':user_id'])) {
                    $countStmt->bindValue($key, $value);
                }
            }
            $countStmt->execute();
            $totalItems = $countStmt->fetch()['total'];
            
            $totalPages = ceil($totalItems / $limit);
            
            return [
                'status' => true,
                'data' => [
                    'lojas' => $lojas,
                    'categorias' => $categorias,
                    'estatisticas' => $estatisticas,
                    'paginacao' => [
                        'pagina_atual' => $page,
                        'total_paginas' => $totalPages,
                        'total_itens' => $totalItems,
                        'itens_por_pagina' => $limit
                    ]
                ]
            ];
            
        } catch (Exception $e) {
            error_log('Erro em getPartnerStores: ' . $e->getMessage());
            return [
                'status' => false,
                'message' => 'Erro ao carregar lojas: ' . $e->getMessage()
            ];
        }
    }
    /**
    * Atualiza o saldo do administrador
    * 
    * @param float $valor Valor a ser adicionado
    * @param int $transacaoId ID da transação relacionada
    * @param string $descricao Descrição da operação
    * @return bool Sucesso da operação
    */
    public static function updateAdminBalance($valor, $transacaoId = null, $descricao = '') {
        try {
            if ($valor == 0) {
                return true; // Não há nada para atualizar se o valor for zero
            }
            
            $db = Database::getConnection();
            
            if (!$db) {
                error_log('SALDO ADMIN: Erro - Não foi possível obter conexão com banco');
                return false;
            }
            
            // Verificar se já existe registro de saldo
            $checkStmt = $db->query("SELECT COUNT(*) as total FROM admin_saldo WHERE id = 1");
            $checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);
            $exists = $checkResult['total'] > 0;
            
            if (!$exists) {
                // Criar registro inicial se não existir
                $initStmt = $db->prepare("
                    INSERT INTO admin_saldo (id, valor_total, valor_disponivel, valor_pendente)
                    VALUES (1, 0.00, 0.00, 0.00)
                ");
                $initResult = $initStmt->execute();
                
                if (!$initResult) {
                    error_log('SALDO ADMIN: Erro ao criar registro inicial');
                    return false;
                }
            }
            
            // Iniciar transação do banco de dados
            $db->beginTransaction();
            
            // Determinar tipo de operação e valor absoluto
            $tipo = $valor > 0 ? 'credito' : 'debito';
            $valorAbs = abs($valor);
            
            // Atualizar saldo principal
            $updateStmt = $db->prepare("
                UPDATE admin_saldo
                SET valor_total = valor_total + ?,
                    valor_disponivel = valor_disponivel + ?,
                    ultima_atualizacao = NOW()
                WHERE id = 1
            ");
            
            $updateResult = $updateStmt->execute([$valor, $valor]);
            
            if (!$updateResult) {
                $db->rollBack();
                error_log('SALDO ADMIN: Erro ao executar atualização do saldo');
                return false;
            }
            
            // Registrar movimentação no histórico
            $movStmt = $db->prepare("
                INSERT INTO admin_saldo_movimentacoes 
                (transacao_id, valor, tipo, descricao, data_operacao)
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $movResult = $movStmt->execute([$transacaoId, $valorAbs, $tipo, $descricao]);
            
            if (!$movResult) {
                $db->rollBack();
                error_log('SALDO ADMIN: Erro ao registrar movimentação');
                return false;
            }
            
            // Confirmar todas as alterações
            $db->commit();
            
            // Log de sucesso para auditoria
            error_log("SALDO ADMIN: Saldo atualizado com sucesso - Valor: $valor, Tipo: $tipo, Transação: $transacaoId");
            
            return true;
            
        } catch (Exception $e) {
            // Reverter alterações em caso de erro
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            
            error_log('SALDO ADMIN: Exceção ao atualizar saldo - ' . $e->getMessage());
            return false;
        }
    }
    /**
    * Obtém os dados de saldo do administrador
    * 
    * @return array Dados do saldo do administrador
    */
    public static function getAdminBalance() {
        try {
            // Verificar se é um administrador
            if (!self::validateAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            $db = Database::getConnection();
            
            // Obter dados de comissões (método antigo)
            $totalBalanceStmt = $db->query("
                SELECT SUM(valor_comissao) as total
                FROM transacoes_comissao
                WHERE tipo_usuario = 'admin' AND status = 'aprovado'
            ");
            $totalBalance = $totalBalanceStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            $pendingBalanceStmt = $db->query("
                SELECT SUM(valor_comissao) as total
                FROM transacoes_comissao
                WHERE tipo_usuario = 'admin' AND status = 'pendente'
            ");
            $pendingBalance = $pendingBalanceStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Obter saldo real da Klube Cash (novo método)
            $adminBalanceStmt = $db->query("SELECT * FROM admin_saldo WHERE id = 1");
            $adminBalance = $adminBalanceStmt->fetch(PDO::FETCH_ASSOC);
            
            // Log para debug
            error_log("SALDO ADMIN DEBUG: Dados recuperados - " . print_r($adminBalance, true));
            
            if (!$adminBalance) {
                // Se não existir, criar registro inicial
                $createStmt = $db->prepare("
                    INSERT INTO admin_saldo (id, valor_total, valor_disponivel, valor_pendente)
                    VALUES (1, 0.00, 0.00, 0.00)
                ");
                $createStmt->execute();
                
                $adminBalance = [
                    'valor_total' => 0.00,
                    'valor_disponivel' => 0.00,
                    'valor_pendente' => 0.00
                ];
                
                error_log("SALDO ADMIN DEBUG: Registro criado com valores zerados");
            }
            
            // Obter movimentações do saldo
            $movimentacoesStmt = $db->query("
                SELECT 
                    asm.*,
                    tc.codigo_transacao,
                    l.nome_fantasia as loja_nome
                FROM admin_saldo_movimentacoes asm
                LEFT JOIN transacoes_cashback tc ON asm.transacao_id = tc.id
                LEFT JOIN lojas l ON tc.loja_id = l.id
                ORDER BY asm.data_operacao DESC
                LIMIT 50
            ");
            $movimentacoes = $movimentacoesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Obter histórico de comissões
            $historyStmt = $db->query("
                SELECT 
                    tc.id,
                    tc.valor_comissao,
                    tc.status,
                    tc.data_transacao,
                    t.codigo_transacao,
                    t.valor_total as valor_venda,
                    l.nome_fantasia as loja_nome,
                    u.nome as cliente_nome
                FROM transacoes_comissao tc
                JOIN transacoes_cashback t ON tc.transacao_id = t.id
                JOIN lojas l ON tc.loja_id = l.id
                JOIN usuarios u ON t.usuario_id = u.id
                WHERE tc.tipo_usuario = 'admin'
                ORDER BY tc.data_transacao DESC
                LIMIT 50
            ");
            $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Estatísticas mensais
            $monthlyStmt = $db->query("
                SELECT 
                    DATE_FORMAT(tc.data_transacao, '%Y-%m') as mes,
                    SUM(tc.valor_comissao) as total,
                    COUNT(tc.id) as quantidade
                FROM transacoes_comissao tc
                WHERE tc.tipo_usuario = 'admin'
                GROUP BY DATE_FORMAT(tc.data_transacao, '%Y-%m')
                ORDER BY mes DESC
                LIMIT 12
            ");
            $monthly = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Top lojas
            $topStoresStmt = $db->query("
                SELECT 
                    l.nome_fantasia,
                    SUM(tc.valor_comissao) as total,
                    COUNT(tc.id) as quantidade
                FROM transacoes_comissao tc
                JOIN lojas l ON tc.loja_id = l.id
                WHERE tc.tipo_usuario = 'admin' AND tc.status = 'aprovado'
                GROUP BY l.id
                ORDER BY total DESC
                LIMIT 5
            ");
            $topStores = $topStoresStmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'status' => true,
                'data' => [
                    'saldo_total' => $totalBalance,
                    'saldo_pendente' => $pendingBalance,
                    'saldo_admin' => $adminBalance,
                    'movimentacoes' => $movimentacoes,
                    'historico' => $history,
                    'mensal' => $monthly,
                    'top_lojas' => $topStores
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao obter saldo do administrador: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao carregar dados do saldo. Tente novamente.'];
        }
    }

    /**
    * Atualiza configurações do sistema
    * 
    * @param array $data Novas configurações
    * @return array Resultado da operação
    */
    public static function updateSettings($data) {
    try {
        // Verificar se é um administrador
        if (!self::validateAdmin()) {
            return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
        }
        
        // Validar apenas cliente e admin, loja sempre será 0
        $requiredFields = ['porcentagem_cliente', 'porcentagem_admin'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || !is_numeric($data[$field])) {
                return ['status' => false, 'message' => 'Campos inválidos ou incompletos.'];
            }
            
            $data[$field] = floatval($data[$field]);
            
            if ($data[$field] < 0 || $data[$field] > 10) {
                return ['status' => false, 'message' => 'Porcentagens devem estar entre 0 e 10.'];
            }
        }
        
        // Forçar porcentagem da loja como 0
        $data['porcentagem_loja'] = 0.00;
        
        // Calcular total e validar que seja exatamente 10%
        $porcentagemTotal = $data['porcentagem_cliente'] + $data['porcentagem_admin'];
        
        if (abs($porcentagemTotal - 10.00) > 0.01) {
            return ['status' => false, 'message' => 'A soma das porcentagens deve ser exatamente 10% (cliente + admin).'];
        }
        
        $db = Database::getConnection();
        
        // Verificar se a tabela existe
        self::createSettingsTableIfNotExists($db);
        
        // Inserir novas configurações
        $stmt = $db->prepare("
            INSERT INTO configuracoes_cashback (
                porcentagem_cliente, porcentagem_admin, porcentagem_loja, data_atualizacao
            ) VALUES (
                :porcentagem_cliente, :porcentagem_admin, 0.00, NOW()
            )
        ");
        
        $stmt->bindParam(':porcentagem_cliente', $data['porcentagem_cliente']);
        $stmt->bindParam(':porcentagem_admin', $data['porcentagem_admin']);
        $stmt->execute();
        
        return ['status' => true, 'message' => 'Configurações atualizadas com sucesso.'];
        
    } catch (PDOException $e) {
        error_log('Erro ao atualizar configurações: ' . $e->getMessage());
        return ['status' => false, 'message' => 'Erro ao atualizar configurações. Tente novamente.'];
    }
}
    /**
    * Obtém dados completos para relatórios financeiros
    * 
    * @param array $filters Filtros de período
    * @return array Dados dos relatórios
    */
    /**
 * Obtém dados completos para relatórios financeiros
 */
    /**
 * Obtém dados completos para relatórios financeiros
 */
public static function getFinancialReports($filters = []) {
    try {
        if (!self::validateAdmin()) {
            return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
        }
        
        $db = Database::getConnection();
        
        // Preparar condições de filtro
        $params = [];
        
        // 1. ESTATÍSTICAS DE SALDO
        $saldoQuery = "
            SELECT 
                COUNT(DISTINCT cs.usuario_id) as total_clientes_com_saldo,
                COUNT(DISTINCT cs.loja_id) as total_lojas_com_saldo,
                COALESCE(SUM(cs.saldo_disponivel), 0) as total_saldo_disponivel,
                COALESCE(SUM(cs.total_creditado), 0) as total_saldo_creditado,
                COALESCE(SUM(cs.total_usado), 0) as total_saldo_usado,
                COALESCE(AVG(cs.saldo_disponivel), 0) as media_saldo_por_cliente
            FROM cashback_saldos cs
            WHERE cs.saldo_disponivel > 0
        ";
        
        $saldoStmt = $db->query($saldoQuery);
        $saldoStats = $saldoStmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_clientes_com_saldo' => 0, 'total_lojas_com_saldo' => 0,
            'total_saldo_disponivel' => 0, 'total_saldo_creditado' => 0,
            'total_saldo_usado' => 0, 'media_saldo_por_cliente' => 0
        ];
        
        // 2. MOVIMENTAÇÕES DO PERÍODO (CORRIGIDO)
        $movCondition = "1=1";
        $movParams = [];
        
        if (!empty($filters['data_inicio'])) {
            $movCondition .= " AND cm.data_operacao >= :data_inicio";
            $movParams[':data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
        }
        
        if (!empty($filters['data_fim'])) {
            $movCondition .= " AND cm.data_operacao <= :data_fim";
            $movParams[':data_fim'] = $filters['data_fim'] . ' 23:59:59';
        }
        
        $movQuery = "
            SELECT 
                COALESCE(SUM(CASE WHEN cm.tipo_operacao = 'credito' THEN cm.valor ELSE 0 END), 0) as creditos_periodo,
                COALESCE(SUM(CASE WHEN cm.tipo_operacao = 'uso' THEN cm.valor ELSE 0 END), 0) as usos_periodo,
                COALESCE(SUM(CASE WHEN cm.tipo_operacao = 'estorno' THEN cm.valor ELSE 0 END), 0) as estornos_periodo,
                COUNT(CASE WHEN cm.tipo_operacao = 'credito' THEN 1 END) as qtd_creditos,
                COUNT(CASE WHEN cm.tipo_operacao = 'uso' THEN 1 END) as qtd_usos,
                COUNT(CASE WHEN cm.tipo_operacao = 'estorno' THEN 1 END) as qtd_estornos
            FROM cashback_movimentacoes cm
            WHERE $movCondition
        ";
        
        $movStmt = $db->prepare($movQuery);
        foreach ($movParams as $param => $value) {
            $movStmt->bindValue($param, $value);
        }
        $movStmt->execute();
        $movimentacoesStats = $movStmt->fetch(PDO::FETCH_ASSOC) ?: [
            'creditos_periodo' => 0, 'usos_periodo' => 0, 'estornos_periodo' => 0,
            'qtd_creditos' => 0, 'qtd_usos' => 0, 'qtd_estornos' => 0
        ];
        
        // 3. VENDAS ORIGINAIS VS LÍQUIDAS (CORRIGIDO)
        $vendasCondition = "t.status = 'aprovado'";
        $vendasParams = [];
        
        if (!empty($filters['data_inicio'])) {
            $vendasCondition .= " AND t.data_transacao >= :data_inicio";
            $vendasParams[':data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
        }
        
        if (!empty($filters['data_fim'])) {
            $vendasCondition .= " AND t.data_transacao <= :data_fim";
            $vendasParams[':data_fim'] = $filters['data_fim'] . ' 23:59:59';
        }
        
        $vendasQuery = "
            SELECT 
                DATE_FORMAT(t.data_transacao, '%Y-%m') as mes,
                COALESCE(SUM(t.valor_total), 0) as vendas_originais,
                COALESCE(SUM(tsu.valor_usado), 0) as total_saldo_usado_mes,
                COALESCE(SUM(t.valor_total - COALESCE(tsu.valor_usado, 0)), 0) as vendas_liquidas
            FROM transacoes_cashback t
            LEFT JOIN transacoes_saldo_usado tsu ON t.id = tsu.transacao_id
            WHERE $vendasCondition
            GROUP BY DATE_FORMAT(t.data_transacao, '%Y-%m')
            ORDER BY mes ASC
        ";
        
        $vendasStmt = $db->prepare($vendasQuery);
        foreach ($vendasParams as $param => $value) {
            $vendasStmt->bindValue($param, $value);
        }
        $vendasStmt->execute();
        $vendasComparacao = $vendasStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        // 4. RANKING LOJAS
        $lojasQuery = "
            SELECT 
                l.nome_fantasia,
                COALESCE(SUM(cs.saldo_disponivel), 0) as saldo_atual,
                COALESCE(SUM(cs.total_creditado), 0) as total_creditado,
                COALESCE(SUM(cs.total_usado), 0) as total_usado,
                COUNT(DISTINCT cs.usuario_id) as clientes_com_saldo,
                CASE 
                    WHEN SUM(cs.total_creditado) > 0 
                    THEN (SUM(cs.total_usado) / SUM(cs.total_creditado)) * 100 
                    ELSE 0 
                END as percentual_uso
            FROM lojas l
            LEFT JOIN cashback_saldos cs ON l.id = cs.loja_id
            WHERE l.status = 'aprovado'
            GROUP BY l.id, l.nome_fantasia
            HAVING total_creditado > 0
            ORDER BY total_usado DESC
            LIMIT 10
        ";
        
        $lojasStmt = $db->query($lojasQuery);
        $lojasSaldo = $lojasStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        // 5. DADOS FINANCEIROS GERAIS
        $financeQuery = "
            SELECT 
                COALESCE(SUM(valor_total), 0) as total_vendas,
                COALESCE(SUM(valor_cliente), 0) as total_cashback,
                COUNT(*) as total_transacoes
            FROM transacoes_cashback t
            WHERE $vendasCondition
        ";
        
        $financeStmt = $db->prepare($financeQuery);
        foreach ($vendasParams as $param => $value) {
            $financeStmt->bindValue($param, $value);
        }
        $financeStmt->execute();
        $financialData = $financeStmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_vendas' => 0, 'total_cashback' => 0, 'total_transacoes' => 0
        ];
        
        // 6. COMISSÃO DO ADMIN (CORRIGIDO)
        $adminCondition = "tc.tipo_usuario = 'admin' AND tc.status = 'aprovado'";
        $adminParams = [];
        
        if (!empty($filters['data_inicio'])) {
            $adminCondition .= " AND tc.data_transacao >= :data_inicio";
            $adminParams[':data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
        }
        
        if (!empty($filters['data_fim'])) {
            $adminCondition .= " AND tc.data_transacao <= :data_fim";
            $adminParams[':data_fim'] = $filters['data_fim'] . ' 23:59:59';
        }
        
        $adminQuery = "
            SELECT COALESCE(SUM(valor_comissao), 0) as total_comissao
            FROM transacoes_comissao tc
            WHERE $adminCondition
        ";
        
        $adminStmt = $db->prepare($adminQuery);
        foreach ($adminParams as $param => $value) {
            $adminStmt->bindValue($param, $value);
        }
        $adminStmt->execute();
        $adminComission = $adminStmt->fetch(PDO::FETCH_ASSOC)['total_comissao'] ?? 0;
        
        // 7. DADOS MENSAIS (CORRIGIDO)
        $monthlyQuery = "
            SELECT 
                DATE_FORMAT(tc.data_transacao, '%Y-%m') as mes,
                COALESCE(SUM(CASE WHEN tc.tipo_usuario = 'admin' THEN tc.valor_comissao ELSE 0 END), 0) as comissao_admin,
                COALESCE(SUM(t.valor_cliente), 0) as total_cashback
            FROM transacoes_comissao tc
            JOIN transacoes_cashback t ON tc.transacao_id = t.id
            WHERE tc.status = 'aprovado' AND $vendasCondition
            GROUP BY DATE_FORMAT(tc.data_transacao, '%Y-%m')
            ORDER BY mes ASC
        ";
        
        $monthlyStmt = $db->prepare($monthlyQuery);
        foreach ($vendasParams as $param => $value) {
            $monthlyStmt->bindValue($param, $value);
        }
        $monthlyStmt->execute();
        $monthlyData = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        return [
            'status' => true,
            'data' => [
                'saldo_stats' => $saldoStats,
                'movimentacoes_stats' => $movimentacoesStats,
                'vendas_comparacao' => $vendasComparacao,
                'lojas_saldo' => $lojasSaldo,
                'financial_data' => $financialData,
                'admin_comission' => $adminComission,
                'monthly_data' => $monthlyData
            ]
        ];
        
    } catch (PDOException $e) {
        error_log('Erro SQL relatórios: ' . $e->getMessage());
        return ['status' => false, 'message' => 'Erro na consulta: ' . $e->getMessage()];
    }
}
    /**
     * Gera relatórios administrativos
     * 
     * @param string $type Tipo de relatório
     * @param array $filters Filtros para o relatório
     * @return array Dados do relatório
     */
    public static function generateReport($type, $filters = []) {
        try {
            // Verificar se é um administrador
            if (!self::validateAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            $db = Database::getConnection();
            
            // Preparar condições de filtro comuns
            $conditions = "WHERE 1=1";
            $params = [];
            
            if (isset($filters['data_inicio']) && !empty($filters['data_inicio'])) {
                $conditions .= " AND data_transacao >= :data_inicio";
                $params[':data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
            }
            
            if (isset($filters['data_fim']) && !empty($filters['data_fim'])) {
                $conditions .= " AND data_transacao <= :data_fim";
                $params[':data_fim'] = $filters['data_fim'] . ' 23:59:59';
            }
            
            $reportData = [];
            
            switch ($type) {
               case 'financial_reports':
                header('Content-Type: application/json');
                $filters = $_GET; // Usar GET em vez de POST
                $result = AdminController::getFinancialReports($filters);
                echo json_encode($result);
                exit;

                case 'create_store':
                    header('Content-Type: application/json; charset=UTF-8');
                    
                    $data = $_POST;
                    unset($data['action']);
                    
                    // Validar dados obrigatórios
                    $required = ['nome_fantasia', 'razao_social', 'cnpj', 'email', 'telefone'];
                    foreach ($required as $field) {
                        if (!isset($data[$field]) || empty(trim($data[$field]))) {
                            echo json_encode(['status' => false, 'message' => "Campo '$field' é obrigatório"]);
                            exit;
                        }
                    }
                    
                    try {
                        $db = Database::getConnection();
                        
                        // Verificar duplicatas
                        $checkStmt = $db->prepare("SELECT id FROM lojas WHERE cnpj = ? OR email = ?");
                        $checkStmt->execute([$data['cnpj'], $data['email']]);
                        
                        if ($checkStmt->rowCount() > 0) {
                            echo json_encode(['status' => false, 'message' => 'Já existe uma loja com este CNPJ ou email']);
                            exit;
                        }
                        
                        // Inserir nova loja
                        $stmt = $db->prepare("
                            INSERT INTO lojas (
                                nome_fantasia, razao_social, cnpj, email, telefone,
                                categoria, porcentagem_cashback, status, data_cadastro
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        
                        $success = $stmt->execute([
                            $data['nome_fantasia'],
                            $data['razao_social'],
                            $data['cnpj'],
                            $data['email'],
                            $data['telefone'],
                            $data['categoria'] ?? 'Outros',
                            $data['porcentagem_cashback'] ?? 5.00,
                            $data['status'] ?? 'pendente'
                        ]);
                        
                        if ($success) {
                            echo json_encode(['status' => true, 'message' => 'Loja criada com sucesso!']);
                        } else {
                            echo json_encode(['status' => false, 'message' => 'Erro ao criar loja']);
                        }
                        
                    } catch (Exception $e) {
                        echo json_encode(['status' => false, 'message' => 'Erro no banco de dados']);
                    }
                    exit;
                    break;

                case 'transaction_details_with_balance':
                    header('Content-Type: application/json');

                    $transactionId = intval($_POST['transaction_id'] ?? 0);

                    if ($transactionId <= 0) {
                        echo json_encode(['status' => false, 'message' => 'ID inválido']);
                        exit;
                    }

                    echo json_encode([
                        'status' => true, 
                        'data' => [
                            'id' => $transactionId,
                            'teste' => 'Funcionando!',
                            'data_atual' => date('Y-m-d H:i:s')
                        ]
                    ]);
                    exit;
                    break;

                case 'store_details':
                    // Forçar output JSON
                    ob_clean();
                    header('Content-Type: application/json; charset=UTF-8');
                    header('Cache-Control: no-cache, must-revalidate');
                    
                    try {
                        // Verificar autenticação
                        if (!AuthController::isAuthenticated() || !AuthController::isAdmin()) {
                            echo json_encode(['status' => false, 'message' => 'Acesso não autorizado']);
                            exit;
                        }
                        
                        // Validar parâmetros
                        $storeId = isset($_POST['store_id']) ? intval($_POST['store_id']) : 0;
                        if ($storeId <= 0) {
                            echo json_encode(['status' => false, 'message' => 'ID da loja inválido']);
                            exit;
                        }
                        
                        // Buscar dados básicos da loja
                        $db = Database::getConnection();
                        $stmt = $db->prepare("SELECT * FROM lojas WHERE id = ?");
                        $stmt->execute([$storeId]);
                        $store = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$store) {
                            echo json_encode(['status' => false, 'message' => 'Loja não encontrada']);
                            exit;
                        }
                        
                        // Buscar estatísticas básicas
                        $statsStmt = $db->prepare("
                            SELECT 
                                COUNT(*) as total_transacoes,
                                COALESCE(SUM(valor_total), 0) as total_vendas,
                                COALESCE(SUM(valor_cliente), 0) as total_cashback
                            FROM transacoes_cashback
                            WHERE loja_id = ? AND status = 'aprovado'
                        ");
                        $statsStmt->execute([$storeId]);
                        $statistics = $statsStmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Buscar endereço se existir
                        $addrStmt = $db->prepare("SELECT * FROM lojas_endereco WHERE loja_id = ?");
                        $addrStmt->execute([$storeId]);
                        $address = $addrStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($address) {
                            $store['endereco'] = $address;
                        }
                        
                        echo json_encode([
                            'status' => true,
                            'data' => [
                                'loja' => $store,
                                'estatisticas' => $statistics
                            ]
                        ]);
                        
                    } catch (Exception $e) {
                        error_log('Erro em store_details: ' . $e->getMessage());
                        echo json_encode([
                            'status' => false,
                            'message' => 'Erro interno: ' . $e->getMessage()
                        ]);
                    }
                    exit;
                    break;
                    
                case 'test_store_connection':
                    header('Content-Type: application/json; charset=UTF-8');
                    
                    try {
                        $db = Database::getConnection();
                        $stmt = $db->query("SELECT COUNT(*) as total FROM lojas");
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        echo json_encode([
                            'status' => true,
                            'message' => 'Conexão OK',
                            'total_lojas' => $result['total'],
                            'session_user_id' => $_SESSION['user_id'] ?? 'não definido',
                            'session_user_type' => $_SESSION['user_type'] ?? 'não definido'
                        ]);
                    } catch (Exception $e) {
                        echo json_encode([
                            'status' => false,
                            'message' => 'Erro: ' . $e->getMessage()
                        ]);
                    }
                    exit;
                    break;
                case 'test_ajax':
                    header('Content-Type: application/json; charset=UTF-8');
                    echo json_encode([
                        'status' => true,
                        'message' => 'AJAX funcionando!',
                        'timestamp' => date('Y-m-d H:i:s'),
                        'user_id' => $_SESSION['user_id'] ?? null,
                        'user_type' => $_SESSION['user_type'] ?? null
                    ]);
                    exit;
                case 'test_connection':
                    header('Content-Type: application/json; charset=UTF-8');

                    try {
                        $db = Database::getConnection();
                        $stmt = $db->query("SELECT COUNT(*) as total FROM lojas");
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        echo json_encode([
                            'status' => true,
                            'message' => 'Conexão OK',
                            'total_lojas' => $result['total'],
                            'user_type' => $_SESSION['user_type'] ?? 'não definido',
                            'user_id' => $_SESSION['user_id'] ?? 'não definido'
                        ]);
                    } catch (Exception $e) {
                        echo json_encode([
                            'status' => false,
                            'message' => 'Erro de conexão: ' . $e->getMessage()
                        ]);
                    }
                    exit;
                    break;

                case 'financeiro':
                    // Dados financeiros gerais
                    $financeQuery = "
                        SELECT 
                            SUM(valor_total) as total_vendas,
                            SUM(valor_cashback) as total_cashback,
                            COUNT(*) as total_transacoes,
                            AVG(valor_cashback) as media_cashback
                        FROM transacoes_cashback
                        $conditions
                        AND status = :status
                    ";
                    
                    $financeStmt = $db->prepare($financeQuery);
                    foreach ($params as $param => $value) {
                        $financeStmt->bindValue($param, $value);
                    }
                    $status = TRANSACTION_APPROVED;
                    $financeStmt->bindValue(':status', $status);
                    $financeStmt->execute();
                    $financialData = $financeStmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Comissões do admin
                    $adminComissionQuery = "
                        SELECT SUM(valor_comissao) as total_comissao
                        FROM transacoes_comissao
                        $conditions
                        AND tipo_usuario = :tipo AND status = :status
                    ";
                    
                    $adminComissionStmt = $db->prepare($adminComissionQuery);
                    foreach ($params as $param => $value) {
                        $adminComissionStmt->bindValue($param, $value);
                    }
                    $tipo = USER_TYPE_ADMIN;
                    $adminComissionStmt->bindValue(':tipo', $tipo);
                    $adminComissionStmt->bindValue(':status', $status);
                    $adminComissionStmt->execute();
                    $adminComission = $adminComissionStmt->fetch(PDO::FETCH_ASSOC)['total_comissao'] ?? 0;
                    
                    // Dados por loja
                    $storeQuery = "
                        SELECT 
                            l.id, l.nome_fantasia,
                            COUNT(t.id) as total_transacoes,
                            SUM(t.valor_total) as total_vendas,
                            SUM(t.valor_cashback) as total_cashback
                        FROM transacoes_cashback t
                        JOIN lojas l ON t.loja_id = l.id
                        $conditions
                        AND t.status = :status
                        GROUP BY l.id
                        ORDER BY total_vendas DESC
                    ";
                    
                    $storeStmt = $db->prepare($storeQuery);
                    foreach ($params as $param => $value) {
                        $storeStmt->bindValue($param, $value);
                    }
                    $storeStmt->bindValue(':status', $status);
                    $storeStmt->execute();
                    $storeData = $storeStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Dados por mês
                    $monthlyQuery = "
                        SELECT 
                            DATE_FORMAT(data_transacao, '%Y-%m') as mes,
                            COUNT(*) as total_transacoes,
                            SUM(valor_total) as total_vendas,
                            SUM(valor_cashback) as total_cashback
                        FROM transacoes_cashback
                        $conditions
                        AND status = :status
                        GROUP BY DATE_FORMAT(data_transacao, '%Y-%m')
                        ORDER BY mes ASC
                    ";
                    
                    $monthlyStmt = $db->prepare($monthlyQuery);
                    foreach ($params as $param => $value) {
                        $monthlyStmt->bindValue($param, $value);
                    }
                    $monthlyStmt->bindValue(':status', $status);
                    $monthlyStmt->execute();
                    $monthlyData = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $reportData = [
                        'financeiro' => $financialData,
                        'comissao_admin' => $adminComission,
                        'por_loja' => $storeData,
                        'por_mes' => $monthlyData
                    ];
                    break;
                    
                case 'usuarios':
                    // Estatísticas gerais de usuários
                    $userStatsQuery = "
                        SELECT 
                            COUNT(*) as total_usuarios,
                            SUM(CASE WHEN tipo = 'cliente' THEN 1 ELSE 0 END) as total_clientes,
                            SUM(CASE WHEN tipo = 'admin' THEN 1 ELSE 0 END) as total_admins,
                            SUM(CASE WHEN tipo = 'loja' THEN 1 ELSE 0 END) as total_lojas,
                            SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) as total_ativos,
                            SUM(CASE WHEN status = 'inativo' THEN 1 ELSE 0 END) as total_inativos,
                            SUM(CASE WHEN status = 'bloqueado' THEN 1 ELSE 0 END) as total_bloqueados
                        FROM usuarios
                    ";
                    
                    $userStatsStmt = $db->prepare($userStatsQuery);
                    $userStatsStmt->execute();
                    $userStats = $userStatsStmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Novos usuários por mês
                    $newUsersQuery = "
                        SELECT 
                            DATE_FORMAT(data_criacao, '%Y-%m') as mes,
                            COUNT(*) as total_novos
                        FROM usuarios
                        WHERE tipo = 'cliente'
                        GROUP BY DATE_FORMAT(data_criacao, '%Y-%m')
                        ORDER BY mes ASC
                    ";
                    
                    $newUsersStmt = $db->prepare($newUsersQuery);
                    $newUsersStmt->execute();
                    $newUsers = $newUsersStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Usuários mais ativos (com mais transações)
                    $activeUsersQuery = "
                        SELECT 
                            u.id, u.nome, u.email,
                            COUNT(t.id) as total_transacoes,
                            SUM(t.valor_total) as total_compras,
                            SUM(t.valor_cashback) as total_cashback
                        FROM usuarios u
                        JOIN transacoes_cashback t ON u.id = t.usuario_id
                        WHERE u.tipo = 'cliente'
                        GROUP BY u.id
                        ORDER BY total_transacoes DESC
                        LIMIT 10
                    ";
                    
                    $activeUsersStmt = $db->prepare($activeUsersQuery);
                    $activeUsersStmt->execute();
                    $activeUsers = $activeUsersStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $reportData = [
                        'estatisticas' => $userStats,
                        'novos_por_mes' => $newUsers,
                        'mais_ativos' => $activeUsers
                    ];
                    break;
                    
                case 'lojas':
                    // Estatísticas gerais de lojas
                    $storeStatsQuery = "
                        SELECT 
                            COUNT(*) as total_lojas,
                            SUM(CASE WHEN status = 'aprovado' THEN 1 ELSE 0 END) as total_aprovadas,
                            SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as total_pendentes,
                            SUM(CASE WHEN status = 'rejeitado' THEN 1 ELSE 0 END) as total_rejeitadas,
                            AVG(porcentagem_cashback) as media_cashback
                        FROM lojas
                    ";
                    
                    $storeStatsStmt = $db->prepare($storeStatsQuery);
                    $storeStatsStmt->execute();
                    $storeStats = $storeStatsStmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Lojas por categoria
                    $categoryQuery = "
                        SELECT 
                            categoria,
                            COUNT(*) as total_lojas
                        FROM lojas
                        WHERE status = 'aprovado'
                        GROUP BY categoria
                        ORDER BY total_lojas DESC
                    ";
                    
                    $categoryStmt = $db->prepare($categoryQuery);
                    $categoryStmt->execute();
                    $storeCategories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Lojas mais ativas (com mais transações)
                    $activeStoresQuery = "
                        SELECT 
                            l.id, l.nome_fantasia, l.categoria,
                            COUNT(t.id) as total_transacoes,
                            SUM(t.valor_total) as total_vendas,
                            SUM(t.valor_cashback) as total_cashback,
                            l.porcentagem_cashback
                        FROM lojas l
                        JOIN transacoes_cashback t ON l.id = t.loja_id
                        WHERE l.status = 'aprovado'
                        AND t.status = 'aprovado'
                        GROUP BY l.id
                        ORDER BY total_transacoes DESC
                        LIMIT 10
                    ";
                    
                    $activeStoresStmt = $db->prepare($activeStoresQuery);
                    $activeStoresStmt->execute();
                    $activeStores = $activeStoresStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $reportData = [
                        'estatisticas' => $storeStats,
                        'por_categoria' => $storeCategories,
                        'mais_ativas' => $activeStores
                    ];
                    break;
                
                default:
                    return ['status' => false, 'message' => 'Tipo de relatório inválido.'];
            }
            
            return [
                'status' => true,
                'data' => [
                    'tipo' => $type,
                    'filtros' => $filters,
                    'resultados' => $reportData
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao gerar relatório: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao gerar relatório. Tente novamente.'];
        }
    }
    
    /**
     * Valida se o usuário é um administrador
     * 
     * @return bool true se for administrador, false caso contrário
     */
    private static function validateAdmin() {
        return AuthController::isAdmin();
    }
    
    /**
     * Cria a tabela de configurações se não existir
     * 
     * @param PDO $db Conexão com o banco de dados
     * @return void
     */
    private static function createSettingsTableIfNotExists($db) {
        try {
            // Verificar se a tabela existe
            $stmt = $db->prepare("SHOW TABLES LIKE 'configuracoes_cashback'");
            $stmt->execute();
            
            if ($stmt->rowCount() == 0) {
                // Criar a tabela
                $createTable = "CREATE TABLE configuracoes_cashback (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    porcentagem_total DECIMAL(5,2) NOT NULL,
                    porcentagem_cliente DECIMAL(5,2) NOT NULL,
                    porcentagem_admin DECIMAL(5,2) NOT NULL,
                    porcentagem_loja DECIMAL(5,2) NOT NULL,
                    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
                
                $db->exec($createTable);
                
                // Inserir configurações padrão
                $insertStmt = $db->prepare("
                    INSERT INTO configuracoes_cashback (
                        porcentagem_total, porcentagem_cliente, porcentagem_admin, porcentagem_loja
                    ) VALUES (
                        :porcentagem_total, :porcentagem_cliente, :porcentagem_admin, :porcentagem_loja
                    )
                ");
                
                $total = DEFAULT_CASHBACK_TOTAL;
                $cliente = DEFAULT_CASHBACK_CLIENT;
                $admin = DEFAULT_CASHBACK_ADMIN;
                $loja = DEFAULT_CASHBACK_STORE;
                
                $insertStmt->bindParam(':porcentagem_total', $total);
                $insertStmt->bindParam(':porcentagem_cliente', $cliente);
                $insertStmt->bindParam(':porcentagem_admin', $admin);
                $insertStmt->bindParam(':porcentagem_loja', $loja);
                $insertStmt->execute();
            }
        } catch (PDOException $e) {
            error_log('Erro ao criar tabela de configurações: ' . $e->getMessage());
        }
    }
    
    /**
     * Cria um backup do banco de dados
     * 
     * @return array Resultado da operação
     */
    public static function createDatabaseBackup() {
        try {
            // Verificar se é um administrador
            if (!self::validateAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            // Verificar se o diretório de backups existe
            $backupDir = __DIR__ . '/../backups';
            if (!file_exists($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            
            // Nome do arquivo de backup
            $backupFile = $backupDir . '/backup_' . date('Y-m-d_H-i-s') . '.sql';
            
            // Comando mysqldump
            $command = sprintf(
                'mysqldump --host=%s --user=%s --password=%s %s > %s',
                escapeshellarg(DB_HOST),
                escapeshellarg(DB_USER),
                escapeshellarg(DB_PASS),
                escapeshellarg(DB_NAME),
                escapeshellarg($backupFile)
            );
            
            // Executar comando
            exec($command, $output, $returnVar);
            
            if ($returnVar !== 0) {
                return ['status' => false, 'message' => 'Erro ao criar backup do banco de dados.'];
            }
            
            // Registrar backup no banco
            $db = Database::getConnection();
            
            // Verificar se a tabela de backups existe
            self::createBackupTableIfNotExists($db);
            
            // Registrar backup
            $stmt = $db->prepare("
                INSERT INTO sistema_backups (arquivo, tamanho, data_criacao)
                VALUES (:arquivo, :tamanho, NOW())
            ");
            
            $filename = basename($backupFile);
            $filesize = filesize($backupFile);
            
            $stmt->bindParam(':arquivo', $filename);
            $stmt->bindParam(':tamanho', $filesize);
            $stmt->execute();
            
            return [
                'status' => true, 
                'message' => 'Backup criado com sucesso.',
                'data' => [
                    'arquivo' => $filename,
                    'tamanho' => $filesize,
                    'data' => date('Y-m-d H:i:s')
                ]
            ];
            
        } catch (Exception $e) {
            error_log('Erro ao criar backup: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao criar backup. Tente novamente.'];
        }
    }
    
    /**
     * Cria a tabela de backups se não existir
     * 
     * @param PDO $db Conexão com o banco de dados
     * @return void
     */
    private static function createBackupTableIfNotExists($db) {
        try {
            // Verificar se a tabela existe
            $stmt = $db->prepare("SHOW TABLES LIKE 'sistema_backups'");
            $stmt->execute();
            
            if ($stmt->rowCount() == 0) {
                // Criar a tabela
                $createTable = "CREATE TABLE sistema_backups (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    arquivo VARCHAR(255) NOT NULL,
                    tamanho INT NOT NULL,
                    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
                
                $db->exec($createTable);
            }
        } catch (PDOException $e) {
            error_log('Erro ao criar tabela de backups: ' . $e->getMessage());
        }
    }
    
    /**
     * Registra uma nova transação no sistema
     * 
     * @param array $data Dados da transação
     * @return array Resultado da operação
     */
    public static function registerTransaction($data) {
        try {
            // Verificar se é um administrador
            if (!self::validateAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            // Validar dados obrigatórios
            $requiredFields = ['usuario_id', 'loja_id', 'valor_total', 'codigo_transacao'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return ['status' => false, 'message' => 'Dados da transação incompletos. Campo faltante: ' . $field];
                }
            }
            
            $db = Database::getConnection();
            
            // Verificar se o usuário existe e é um cliente
            $userStmt = $db->prepare("
                SELECT id, nome, email, tipo 
                FROM usuarios 
                WHERE id = :usuario_id AND tipo = :tipo AND status = :status
            ");
            $userStmt->bindParam(':usuario_id', $data['usuario_id']);
            $tipo = USER_TYPE_CLIENT;
            $userStmt->bindParam(':tipo', $tipo);
            $status = USER_ACTIVE;
            $userStmt->bindParam(':status', $status);
            $userStmt->execute();
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['status' => false, 'message' => 'Cliente não encontrado ou inativo.'];
            }
            
            // Verificar se a loja existe e está aprovada
            $storeStmt = $db->prepare("SELECT * FROM lojas WHERE id = :loja_id AND status = :status");
            $storeStmt->bindParam(':loja_id', $data['loja_id']);
            $status = STORE_APPROVED;
            $storeStmt->bindParam(':status', $status);
            $storeStmt->execute();
            $store = $storeStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$store) {
                return ['status' => false, 'message' => 'Loja não encontrada ou não aprovada.'];
            }
            
            // Verificar se o valor da transação é válido
            if ($data['valor_total'] < MIN_TRANSACTION_VALUE) {
                return ['status' => false, 'message' => 'Valor mínimo para transação é R$ ' . number_format(MIN_TRANSACTION_VALUE, 2, ',', '.')];
            }
            
            // Verificar se já existe uma transação com o mesmo código
            $checkStmt = $db->prepare("
                SELECT id FROM transacoes_cashback 
                WHERE codigo_transacao = :codigo_transacao AND loja_id = :loja_id
            ");
            $checkStmt->bindParam(':codigo_transacao', $data['codigo_transacao']);
            $checkStmt->bindParam(':loja_id', $data['loja_id']);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                return ['status' => false, 'message' => 'Já existe uma transação com este código.'];
            }
            
            // Obter configurações de cashback
            $configStmt = $db->prepare("SELECT * FROM configuracoes_cashback ORDER BY id DESC LIMIT 1");
            $configStmt->execute();
            $config = $configStmt->fetch(PDO::FETCH_ASSOC);
            
            // Calcular valores de cashback
            $porcentagemTotal = isset($config['porcentagem_total']) ? $config['porcentagem_total'] : DEFAULT_CASHBACK_TOTAL;
            $porcentagemCliente = isset($config['porcentagem_cliente']) ? $config['porcentagem_cliente'] : DEFAULT_CASHBACK_CLIENT;
            $porcentagemAdmin = isset($config['porcentagem_admin']) ? $config['porcentagem_admin'] : DEFAULT_CASHBACK_ADMIN;
            $porcentagemLoja = isset($config['porcentagem_loja']) ? $config['porcentagem_loja'] : DEFAULT_CASHBACK_STORE;
            
            // Verificar se a loja tem porcentagem específica
            if (isset($store['porcentagem_cashback']) && $store['porcentagem_cashback'] > 0) {
                $porcentagemTotal = $store['porcentagem_cashback'];
                // Ajustar proporcionalmente
                $fator = $porcentagemTotal / DEFAULT_CASHBACK_TOTAL;
                $porcentagemCliente = DEFAULT_CASHBACK_CLIENT * $fator;
                $porcentagemAdmin = DEFAULT_CASHBACK_ADMIN * $fator;
                $porcentagemLoja = DEFAULT_CASHBACK_STORE * $fator;
            }
            
            // Calcular valores
            $valorCashbackTotal = ($data['valor_total'] * $porcentagemTotal) / 100;
            $valorCashbackCliente = ($data['valor_total'] * $porcentagemCliente) / 100;
            $valorCashbackAdmin = ($data['valor_total'] * $porcentagemAdmin) / 100;
            // CORREÇÃO: Loja não recebe cashback
            $valorCashbackLoja = 0.00;

            
            // Iniciar transação
            $db->beginTransaction();
            
            // Definir o status da transação
            $transactionStatus = isset($data['status']) ? $data['status'] : TRANSACTION_APPROVED;
            
            // Registrar transação principal
            $stmt = $db->prepare("
                INSERT INTO transacoes_cashback (
                    usuario_id, loja_id, valor_total, valor_cashback,
                    codigo_transacao, data_transacao, status, descricao
                ) VALUES (
                    :usuario_id, :loja_id, :valor_total, :valor_cashback,
                    :codigo_transacao, :data_transacao, :status, :descricao
                )
            ");
            
            $stmt->bindParam(':usuario_id', $data['usuario_id']);
            $stmt->bindParam(':loja_id', $data['loja_id']);
            $stmt->bindParam(':valor_total', $data['valor_total']);
            $stmt->bindParam(':valor_cashback', $valorCashbackCliente);
            $stmt->bindParam(':codigo_transacao', $data['codigo_transacao']);
            $dataTransacao = isset($data['data_transacao']) ? $data['data_transacao'] : date('Y-m-d H:i:s');
            $stmt->bindParam(':data_transacao', $dataTransacao);
            $stmt->bindParam(':status', $transactionStatus);
            $stmt->bindParam(':descricao', $data['descricao'] ?? 'Transação cadastrada pelo administrador');
            $stmt->execute();
            
            $transactionId = $db->lastInsertId();

            // === INTEGRAÇÃO AUTOMÁTICA: Sistema de Notificação Corrigido ===
            try {
                error_log("[FIXED] AdminController - Disparando notificação para transação {$transactionId} criada via painel admin");

                require_once __DIR__ . '/../classes/FixedBrutalNotificationSystem.php';
                $notificationSystem = new FixedBrutalNotificationSystem();
                $result = $notificationSystem->forceNotifyTransaction($transactionId);

                if ($result['success']) {
                    error_log("[FIXED] AdminController - Notificação enviada com sucesso: " . $result['message']);
                } else {
                    error_log("[FIXED] AdminController - Falha na notificação: " . $result['message']);
                }

            } catch (Exception $e) {
                error_log("[FIXED] AdminController - Erro na notificação para transação {$transactionId}: " . $e->getMessage());
            }

            // Registrar transação para o administrador (comissão admin)
            if ($valorCashbackAdmin > 0) {
                $adminStmt = $db->prepare("
                    INSERT INTO transacoes_comissao (
                        tipo_usuario, usuario_id, loja_id, transacao_id,
                        valor_total, valor_comissao, data_transacao, status
                    ) VALUES (
                        :tipo_usuario, :usuario_id, :loja_id, :transacao_id,
                        :valor_total, :valor_comissao, :data_transacao, :status
                    )
                ");
                
                $tipoAdmin = USER_TYPE_ADMIN;
                $adminStmt->bindParam(':tipo_usuario', $tipoAdmin);
                $adminId = 1; // Administrador padrão (ajustar conforme necessário)
                $adminStmt->bindParam(':usuario_id', $adminId);
                $adminStmt->bindParam(':loja_id', $data['loja_id']);
                $adminStmt->bindParam(':transacao_id', $transactionId);
                $adminStmt->bindParam(':valor_total', $data['valor_total']);
                $adminStmt->bindParam(':valor_comissao', $valorCashbackAdmin);
                $adminStmt->bindParam(':data_transacao', $dataTransacao);
                $adminStmt->bindParam(':status', $transactionStatus);
                $adminStmt->execute();
            }
            
            // Registrar transação para a loja (comissão loja)
            if ($valorCashbackLoja > 0) {
                $storeStmt = $db->prepare("
                    INSERT INTO transacoes_comissao (
                        tipo_usuario, usuario_id, loja_id, transacao_id,
                        valor_total, valor_comissao, data_transacao, status
                    ) VALUES (
                        :tipo_usuario, :usuario_id, :loja_id, :transacao_id,
                        :valor_total, :valor_comissao, :data_transacao, :status
                    )
                ");
                
                $tipoLoja = USER_TYPE_STORE;
                $storeStmt->bindParam(':tipo_usuario', $tipoLoja);
                $storeUserId = $store['usuario_id'] ?? $store['id']; // ID do usuário da loja ou da própria loja
                $storeStmt->bindParam(':usuario_id', $storeUserId);
                $storeStmt->bindParam(':loja_id', $data['loja_id']);
                $storeStmt->bindParam(':transacao_id', $transactionId);
                $storeStmt->bindParam(':valor_total', $data['valor_total']);
                $storeStmt->bindParam(':valor_comissao', $valorCashbackLoja);
                $storeStmt->bindParam(':data_transacao', $dataTransacao);
                $storeStmt->bindParam(':status', $transactionStatus);
                $storeStmt->execute();
            }
            
            // Enviar notificação ao cliente
            $notifyStmt = $db->prepare("
                INSERT INTO notificacoes (
                    usuario_id, titulo, mensagem, tipo, data_criacao, lida
                ) VALUES (
                    :usuario_id, :titulo, :mensagem, :tipo, NOW(), 0
                )
            ");
            
            $titulo = "Nova transação de cashback";
            $mensagem = "Você recebeu R$ " . number_format($valorCashbackCliente, 2, ',', '.') . " de cashback na loja {$store['nome_fantasia']}.";
            $tipo = 'success';
            
            $notifyStmt->bindParam(':usuario_id', $data['usuario_id']);
            $notifyStmt->bindParam(':titulo', $titulo);
            $notifyStmt->bindParam(':mensagem', $mensagem);
            $notifyStmt->bindParam(':tipo', $tipo);
            $notifyStmt->execute();
            
            // Enviar email ao cliente
            $transactionData = [
                'loja' => $store['nome_fantasia'],
                'valor_total' => $data['valor_total'],
                'valor_cashback' => $valorCashbackCliente,
                'data_transacao' => $dataTransacao
            ];
            
            Email::sendTransactionConfirmation($user['email'], $user['nome'], $transactionData);
            
            // Confirmar transação
            $db->commit();
            
            return [
                'status' => true, 
                'message' => 'Transação registrada com sucesso.',
                'data' => [
                    'transaction_id' => $transactionId,
                    'cashback_value' => $valorCashbackCliente
                ]
            ];
            
        } catch (PDOException $e) {
            // Reverter transação em caso de erro
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            
            error_log('Erro ao registrar transação: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao registrar transação. Tente novamente.'];
        }
    }
}

// Processar requisições diretas de acesso ao controlador
if (basename($_SERVER['PHP_SELF']) === 'AdminController.php') {
    // Verificar se o usuário está autenticado
    if (!AuthController::isAuthenticated()) {
        header('Location: ' . LOGIN_URL . '?error=' . urlencode('Você precisa fazer login para acessar esta página.'));
        exit;
    }
    
    // Verificar se é um administrador
    if (!AuthController::isAdmin()) {
        header('Location: ' . LOGIN_URL . '?error=' . urlencode('Acesso restrito a administradores.'));
        exit;
    }
    
    $action = $_REQUEST['action'] ?? '';
    
    switch ($action) {
        
        case 'dashboard':
            $result = AdminController::getDashboardData();
            echo json_encode($result);
            break;
            
        case 'users':
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $result = AdminController::manageUsers([], $page);
            echo json_encode($result);
            break;
            
        
        case 'getUserDetails':
            // Garantir que a resposta seja JSON
            header('Content-Type: application/json');
            $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
            $result = AdminController::getUserDetails($userId);
            echo json_encode($result);
            exit; // Garantir que nada mais seja executado
            break;   
        case 'update_user_status':
            $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
            $status = $_POST['status'] ?? '';
            $result = AdminController::updateUserStatus($userId, $status);
            echo json_encode($result);
            break;
        case 'update_user':
            $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
            $result = AdminController::updateUser($userId, $_POST);
            echo json_encode($result);
            break;    
        case 'stores':
            $filters = $_POST['filters'] ?? [];
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $result = AdminController::manageStores($filters, $page);
            echo json_encode($result);
            break;
            
        case 'store_details':
            try {
                header('Content-Type: application/json; charset=UTF-8');
                
                if (!AuthController::isAuthenticated() || !AuthController::isAdmin()) {
                    echo json_encode(['status' => false, 'message' => 'Acesso não autorizado']);
                    exit;
                }
                
                $storeId = isset($_POST['store_id']) ? intval($_POST['store_id']) : 0;
                if ($storeId <= 0) {
                    echo json_encode(['status' => false, 'message' => 'ID da loja inválido']);
                    exit;
                }
                
                $result = AdminController::getStoreDetails($storeId);
                echo json_encode($result);
                
            } catch (Exception $e) {
                error_log('Erro em store_details: ' . $e->getMessage());
                echo json_encode([
                    'status' => false, 
                    'message' => 'Erro interno do servidor'
                ]);
            }
            exit;
            break;
            
        case 'update_store_status':
            try {
                $storeId = isset($_POST['store_id']) ? intval($_POST['store_id']) : 0;
                $status = $_POST['status'] ?? '';
                $observacao = $_POST['observacao'] ?? '';
                $result = self::updateStoreStatus($storeId, $status, $observacao);
                self::sendJsonResponse($result);
            } catch (Exception $e) {
                error_log('Erro na action update_store_status: ' . $e->getMessage());
                self::sendJsonResponse(['status' => false, 'message' => 'Erro interno do servidor']);
            }
            break;
            
        case 'update_store':
            $storeId = isset($_POST['store_id']) ? intval($_POST['store_id']) : 0;
            $data = $_POST;
            $result = AdminController::updateStore($storeId, $data);
            echo json_encode($result);
            break;
            
        case 'transactions':
            $filters = $_POST['filters'] ?? [];
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $result = AdminController::manageTransactions($filters, $page);
            echo json_encode($result);
            break;
            
        case 'transaction_details':
            $transactionId = isset($_POST['transaction_id']) ? intval($_POST['transaction_id']) : 0;
            $result = AdminController::getTransactionDetails($transactionId);
            echo json_encode($result);
            break;
            
        case 'update_transaction_status':
            $transactionId = isset($_POST['transaction_id']) ? intval($_POST['transaction_id']) : 0;
            $status = $_POST['status'] ?? '';
            $observacao = $_POST['observacao'] ?? '';
            $result = AdminController::updateTransactionStatus($transactionId, $status, $observacao);
            echo json_encode($result);
            break;
            
        case 'register_transaction':
            $data = $_POST;
            $result = AdminController::registerTransaction($data);
            echo json_encode($result);
            break;
            
        case 'settings':
            $result = AdminController::getSettings();
            echo json_encode($result);
            break;
            
        case 'update_settings':
            $data = $_POST;
            $result = AdminController::updateSettings($data);
            echo json_encode($result);
            break;
            
        case 'report':
            $type = $_POST['type'] ?? 'financeiro';
            $filters = $_POST['filters'] ?? [];
            $result = AdminController::generateReport($type, $filters);
            echo json_encode($result);
            break;
            
        case 'backup':
            $result = AdminController::createDatabaseBackup();
            echo json_encode($result);
            break;
      
        case 'get_available_stores':
            $result = AdminController::getAvailableStores();
            echo json_encode($result);
            break;
            
        case 'get_store_by_email':
            $email = $_POST['email'] ?? '';
            $result = AdminController::getStoreByEmail($email);
            echo json_encode($result);
            break;
        default:
            // Acesso inválido ao controlador
            header('Location: ' . ADMIN_DASHBOARD_URL);
            exit;
    }
}
?>