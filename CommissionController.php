<?php
// controllers/CommissionController.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/AuthController.php';
require_once __DIR__ . '/TransactionController.php';

/**
 * Controlador de Comissões
 * Gerencia operações relacionadas às comissões das transações, distribuição de valores,
 * relatórios e pagamentos de comissões para o sistema Klube Cash
 */
class CommissionController {
    
    /**
     * Obtém um resumo das comissões do sistema
     * 
     * @param array $filters Filtros para o resumo (período, status, etc)
     * @return array Resumo das comissões
     */
    public static function getCommissionSummary($filters = []) {
        try {
            // Verificar se o usuário está autenticado e é administrador
            if (!AuthController::isAuthenticated() || !AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            $db = Database::getConnection();
            
            // Preparar condições de filtro
            $conditions = "WHERE 1=1";
            $params = [];
            
            if (!empty($filters)) {
                // Filtro por período
                if (isset($filters['data_inicio']) && !empty($filters['data_inicio'])) {
                    $conditions .= " AND tc.data_transacao >= :data_inicio";
                    $params[':data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
                }
                
                if (isset($filters['data_fim']) && !empty($filters['data_fim'])) {
                    $conditions .= " AND tc.data_transacao <= :data_fim";
                    $params[':data_fim'] = $filters['data_fim'] . ' 23:59:59';
                }
                
                // Filtro por tipo de usuário (admin/loja)
                if (isset($filters['tipo_usuario']) && !empty($filters['tipo_usuario'])) {
                    $conditions .= " AND tc.tipo_usuario = :tipo_usuario";
                    $params[':tipo_usuario'] = $filters['tipo_usuario'];
                }
                
                // Filtro por status
                if (isset($filters['status']) && !empty($filters['status'])) {
                    $conditions .= " AND tc.status = :status";
                    $params[':status'] = $filters['status'];
                }
                
                // Filtro por loja
                if (isset($filters['loja_id']) && !empty($filters['loja_id'])) {
                    $conditions .= " AND tc.loja_id = :loja_id";
                    $params[':loja_id'] = $filters['loja_id'];
                }
            }
            
            // Consulta para obter totais gerais
            $query = "
                SELECT 
                    COUNT(*) as total_comissoes,
                    SUM(tc.valor_comissao) as valor_total_comissoes,
                    SUM(CASE WHEN tc.status = 'aprovado' THEN tc.valor_comissao ELSE 0 END) as valor_comissoes_aprovadas,
                    SUM(CASE WHEN tc.status = 'pendente' THEN tc.valor_comissao ELSE 0 END) as valor_comissoes_pendentes,
                    SUM(CASE WHEN tc.tipo_usuario = 'admin' THEN tc.valor_comissao ELSE 0 END) as valor_comissoes_admin,
                    SUM(CASE WHEN tc.tipo_usuario = 'loja' THEN tc.valor_comissao ELSE 0 END) as valor_comissoes_lojas,
                    AVG(tc.valor_comissao) as media_valor_comissao
                FROM transacoes_comissao tc
                $conditions
            ";
            
            $stmt = $db->prepare($query);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            $stmt->execute();
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Consulta para obter comissões por mês
            $monthlyQuery = "
                SELECT 
                    DATE_FORMAT(tc.data_transacao, '%Y-%m') as mes,
                    COUNT(*) as total_comissoes,
                    SUM(tc.valor_comissao) as valor_total_comissoes,
                    SUM(CASE WHEN tc.tipo_usuario = 'admin' THEN tc.valor_comissao ELSE 0 END) as valor_comissoes_admin,
                    SUM(CASE WHEN tc.tipo_usuario = 'loja' THEN tc.valor_comissao ELSE 0 END) as valor_comissoes_lojas
                FROM transacoes_comissao tc
                $conditions
                GROUP BY DATE_FORMAT(tc.data_transacao, '%Y-%m')
                ORDER BY mes DESC
                LIMIT 12
            ";
            
            $monthlyStmt = $db->prepare($monthlyQuery);
            foreach ($params as $param => $value) {
                $monthlyStmt->bindValue($param, $value);
            }
            $monthlyStmt->execute();
            $monthlySummary = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Consulta para obter comissões por loja
            $storesQuery = "
                SELECT 
                    l.id as loja_id,
                    l.nome_fantasia,
                    COUNT(tc.id) as total_comissoes,
                    SUM(tc.valor_comissao) as valor_total_comissoes,
                    SUM(CASE WHEN tc.status = 'aprovado' THEN tc.valor_comissao ELSE 0 END) as valor_comissoes_aprovadas,
                    SUM(CASE WHEN tc.status = 'pendente' THEN tc.valor_comissao ELSE 0 END) as valor_comissoes_pendentes
                FROM transacoes_comissao tc
                JOIN lojas l ON tc.loja_id = l.id
                $conditions
                GROUP BY l.id
                ORDER BY valor_total_comissoes DESC
                LIMIT 10
            ";
            
            $storesStmt = $db->prepare($storesQuery);
            foreach ($params as $param => $value) {
                $storesStmt->bindValue($param, $value);
            }
            $storesStmt->execute();
            $storesSummary = $storesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Consulta para obter status de pagamentos
            $paymentsQuery = "
                SELECT 
                    status,
                    COUNT(*) as total_pagamentos,
                    SUM(valor_total) as valor_total_pagamentos
                FROM pagamentos_comissao
                GROUP BY status
            ";
            
            $paymentsStmt = $db->prepare($paymentsQuery);
            $paymentsStmt->execute();
            $paymentsSummary = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Organizar dados de pagamentos em um array associativo
            $paymentsData = [
                'pendente' => ['total' => 0, 'valor' => 0],
                'aprovado' => ['total' => 0, 'valor' => 0],
                'rejeitado' => ['total' => 0, 'valor' => 0]
            ];
            
            foreach ($paymentsSummary as $payment) {
                $paymentsData[$payment['status']] = [
                    'total' => $payment['total_pagamentos'],
                    'valor' => $payment['valor_total_pagamentos']
                ];
            }
            
            // Retornar dados consolidados
            return [
                'status' => true,
                'data' => [
                    'resumo_geral' => $summary,
                    'resumo_mensal' => $monthlySummary,
                    'resumo_por_loja' => $storesSummary,
                    'resumo_pagamentos' => $paymentsData
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao obter resumo de comissões: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao obter resumo de comissões. Tente novamente.'];
        }
    }
    
    /**
     * Obtém as comissões de uma loja específica
     * 
     * @param int $storeId ID da loja
     * @param array $filters Filtros adicionais
     * @param int $page Página atual
     * @return array Lista de comissões da loja
     */
    public static function getStoreCommissions($storeId, $filters = [], $page = 1) {
        try {
            // Verificar se o usuário está autenticado
            if (!AuthController::isAuthenticated()) {
                return ['status' => false, 'message' => 'Usuário não autenticado.'];
            }
            
            // Verificar se é admin ou a própria loja
            if (!AuthController::isAdmin() && (!AuthController::isStore() || AuthController::getCurrentUserId() != $storeId)) {
                return ['status' => false, 'message' => 'Acesso não autorizado.'];
            }
            
            $db = Database::getConnection();
            
            // Verificar se a loja existe
            $storeStmt = $db->prepare("SELECT * FROM lojas WHERE id = :loja_id");
            $storeStmt->bindParam(':loja_id', $storeId);
            $storeStmt->execute();
            $store = $storeStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$store) {
                return ['status' => false, 'message' => 'Loja não encontrada.'];
            }
            
            // Preparar consulta
            $query = "
                SELECT tc.*, t.codigo_transacao, t.valor_total as valor_venda, t.data_transacao,
                       u.nome as cliente_nome
                FROM transacoes_comissao tc
                JOIN transacoes_cashback t ON tc.transacao_id = t.id
                JOIN usuarios u ON t.usuario_id = u.id
                WHERE tc.loja_id = :loja_id AND tc.tipo_usuario = 'loja'
            ";
            
            $params = [':loja_id' => $storeId];
            
            // Aplicar filtros
            if (!empty($filters)) {
                // Filtro por status
                if (isset($filters['status']) && !empty($filters['status'])) {
                    $query .= " AND tc.status = :status";
                    $params[':status'] = $filters['status'];
                }
                
                // Filtro por período
                if (isset($filters['data_inicio']) && !empty($filters['data_inicio'])) {
                    $query .= " AND t.data_transacao >= :data_inicio";
                    $params[':data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
                }
                
                if (isset($filters['data_fim']) && !empty($filters['data_fim'])) {
                    $query .= " AND t.data_transacao <= :data_fim";
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
                
                // Filtro por cliente
                if (isset($filters['cliente']) && !empty($filters['cliente'])) {
                    $query .= " AND u.nome LIKE :cliente";
                    $params[':cliente'] = '%' . $filters['cliente'] . '%';
                }
            }
            
            // Ordenação (padrão: data da transação decrescente)
            $query .= " ORDER BY t.data_transacao DESC";
            
            // Contagem total para paginação
            $countQuery = str_replace("tc.*, t.codigo_transacao, t.valor_total as valor_venda, t.data_transacao, u.nome as cliente_nome", "COUNT(*) as total", $query);
            $countStmt = $db->prepare($countQuery);
            
            foreach ($params as $param => $value) {
                $countStmt->bindValue($param, $value);
            }
            
            $countStmt->execute();
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Paginação
            $perPage = ITEMS_PER_PAGE;
            $totalPages = ceil($totalCount / $perPage);
            $page = max(1, min($page, $totalPages));
            $offset = ($page - 1) * $perPage;
            
            $query .= " LIMIT :offset, :limit";
            $params[':offset'] = $offset;
            $params[':limit'] = $perPage;
            
            // Executar consulta
            $stmt = $db->prepare($query);
            
            // Bind manual para offset e limit
            foreach ($params as $param => $value) {
                if ($param == ':offset' || $param == ':limit') {
                    $stmt->bindValue($param, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($param, $value);
                }
            }
            
            $stmt->execute();
            $commissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular totais
            $totalValorComissoes = 0;
            $totalComissoesAprovadas = 0;
            $totalComissoesPendentes = 0;
            $totalValorComissoesAprovadas = 0;
            $totalValorComissoesPendentes = 0;
            
            foreach ($commissions as $commission) {
                $totalValorComissoes += $commission['valor_comissao'];
                
                if ($commission['status'] == 'aprovado') {
                    $totalComissoesAprovadas++;
                    $totalValorComissoesAprovadas += $commission['valor_comissao'];
                } elseif ($commission['status'] == 'pendente') {
                    $totalComissoesPendentes++;
                    $totalValorComissoesPendentes += $commission['valor_comissao'];
                }
            }
            
            // Obter resumo de pagamentos da loja
            $paymentsQuery = "
                SELECT 
                    status,
                    COUNT(*) as total_pagamentos,
                    SUM(valor_total) as valor_total_pagamentos
                FROM pagamentos_comissao
                WHERE loja_id = :loja_id
                GROUP BY status
            ";
            
            $paymentsStmt = $db->prepare($paymentsQuery);
            $paymentsStmt->bindParam(':loja_id', $storeId);
            $paymentsStmt->execute();
            $paymentsSummary = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Organizar dados de pagamentos em um array associativo
            $paymentsData = [
                'pendente' => ['total' => 0, 'valor' => 0],
                'aprovado' => ['total' => 0, 'valor' => 0],
                'rejeitado' => ['total' => 0, 'valor' => 0]
            ];
            
            foreach ($paymentsSummary as $payment) {
                $paymentsData[$payment['status']] = [
                    'total' => $payment['total_pagamentos'],
                    'valor' => $payment['valor_total_pagamentos']
                ];
            }
            
            return [
                'status' => true,
                'data' => [
                    'loja' => [
                        'id' => $store['id'],
                        'nome_fantasia' => $store['nome_fantasia'],
                        'porcentagem_cashback' => $store['porcentagem_cashback']
                    ],
                    'comissoes' => $commissions,
                    'totais' => [
                        'total_comissoes' => count($commissions),
                        'total_valor' => $totalValorComissoes,
                        'total_aprovadas' => $totalComissoesAprovadas,
                        'total_valor_aprovadas' => $totalValorComissoesAprovadas,
                        'total_pendentes' => $totalComissoesPendentes,
                        'total_valor_pendentes' => $totalValorComissoesPendentes
                    ],
                    'pagamentos' => $paymentsData,
                    'paginacao' => [
                        'total' => $totalCount,
                        'por_pagina' => $perPage,
                        'pagina_atual' => $page,
                        'total_paginas' => $totalPages
                    ]
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao obter comissões da loja: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao obter comissões da loja. Tente novamente.'];
        }
    }
    
    /**
     * Obtém as comissões do administrador
     * 
     * @param array $filters Filtros para as comissões
     * @param int $page Página atual
     * @return array Lista de comissões do administrador
     */
    public static function getAdminCommissions($filters = [], $page = 1) {
        try {
            // Verificar se o usuário está autenticado e é administrador
            if (!AuthController::isAuthenticated() || !AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            $db = Database::getConnection();
            
            // Preparar consulta
            $query = "
                SELECT tc.*, t.codigo_transacao, t.valor_total as valor_venda, t.data_transacao,
                       u.nome as cliente_nome, l.nome_fantasia as loja_nome
                FROM transacoes_comissao tc
                JOIN transacoes_cashback t ON tc.transacao_id = t.id
                JOIN usuarios u ON t.usuario_id = u.id
                JOIN lojas l ON tc.loja_id = l.id
                WHERE tc.tipo_usuario = 'admin'
            ";
            
            $params = [];
            
            // Aplicar filtros
            if (!empty($filters)) {
                // Filtro por status
                if (isset($filters['status']) && !empty($filters['status'])) {
                    $query .= " AND tc.status = :status";
                    $params[':status'] = $filters['status'];
                }
                
                // Filtro por período
                if (isset($filters['data_inicio']) && !empty($filters['data_inicio'])) {
                    $query .= " AND t.data_transacao >= :data_inicio";
                    $params[':data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
                }
                
                if (isset($filters['data_fim']) && !empty($filters['data_fim'])) {
                    $query .= " AND t.data_transacao <= :data_fim";
                    $params[':data_fim'] = $filters['data_fim'] . ' 23:59:59';
                }
                
                // Filtro por loja
                if (isset($filters['loja_id']) && !empty($filters['loja_id'])) {
                    $query .= " AND tc.loja_id = :loja_id";
                    $params[':loja_id'] = $filters['loja_id'];
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
            
            // Ordenação (padrão: data da transação decrescente)
            $query .= " ORDER BY t.data_transacao DESC";
            
            // Contagem total para paginação
            $countQuery = str_replace("tc.*, t.codigo_transacao, t.valor_total as valor_venda, t.data_transacao, u.nome as cliente_nome, l.nome_fantasia as loja_nome", "COUNT(*) as total", $query);
            $countStmt = $db->prepare($countQuery);
            
            foreach ($params as $param => $value) {
                $countStmt->bindValue($param, $value);
            }
            
            $countStmt->execute();
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Paginação
            $perPage = ITEMS_PER_PAGE;
            $totalPages = ceil($totalCount / $perPage);
            $page = max(1, min($page, $totalPages));
            $offset = ($page - 1) * $perPage;
            
            $query .= " LIMIT :offset, :limit";
            $params[':offset'] = $offset;
            $params[':limit'] = $perPage;
            
            // Executar consulta
            $stmt = $db->prepare($query);
            
            // Bind manual para offset e limit
            foreach ($params as $param => $value) {
                if ($param == ':offset' || $param == ':limit') {
                    $stmt->bindValue($param, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($param, $value);
                }
            }
            
            $stmt->execute();
            $commissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular totais
            $totalValorComissoes = 0;
            $totalComissoesAprovadas = 0;
            $totalComissoesPendentes = 0;
            $totalValorComissoesAprovadas = 0;
            $totalValorComissoesPendentes = 0;
            
            foreach ($commissions as $commission) {
                $totalValorComissoes += $commission['valor_comissao'];
                
                if ($commission['status'] == 'aprovado') {
                    $totalComissoesAprovadas++;
                    $totalValorComissoesAprovadas += $commission['valor_comissao'];
                } elseif ($commission['status'] == 'pendente') {
                    $totalComissoesPendentes++;
                    $totalValorComissoesPendentes += $commission['valor_comissao'];
                }
            }
            
            // Obter resumo por loja
            $storesSummaryQuery = "
                SELECT 
                    l.id as loja_id,
                    l.nome_fantasia,
                    COUNT(tc.id) as total_comissoes,
                    SUM(tc.valor_comissao) as valor_total_comissoes,
                    SUM(CASE WHEN tc.status = 'aprovado' THEN tc.valor_comissao ELSE 0 END) as valor_aprovado,
                    SUM(CASE WHEN tc.status = 'pendente' THEN tc.valor_comissao ELSE 0 END) as valor_pendente
                FROM transacoes_comissao tc
                JOIN lojas l ON tc.loja_id = l.id
                WHERE tc.tipo_usuario = 'admin'
                GROUP BY l.id
                ORDER BY valor_total_comissoes DESC
                LIMIT 10
            ";
            
            $storesSummaryStmt = $db->prepare($storesSummaryQuery);
            $storesSummaryStmt->execute();
            $storesSummary = $storesSummaryStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Obter resumo mensal
            $monthlySummaryQuery = "
                SELECT 
                    DATE_FORMAT(t.data_transacao, '%Y-%m') as mes,
                    COUNT(tc.id) as total_comissoes,
                    SUM(tc.valor_comissao) as valor_total_comissoes,
                    SUM(CASE WHEN tc.status = 'aprovado' THEN tc.valor_comissao ELSE 0 END) as valor_aprovado,
                    SUM(CASE WHEN tc.status = 'pendente' THEN tc.valor_comissao ELSE 0 END) as valor_pendente
                FROM transacoes_comissao tc
                JOIN transacoes_cashback t ON tc.transacao_id = t.id
                WHERE tc.tipo_usuario = 'admin'
                GROUP BY DATE_FORMAT(t.data_transacao, '%Y-%m')
                ORDER BY mes DESC
                LIMIT 12
            ";
            
            $monthlySummaryStmt = $db->prepare($monthlySummaryQuery);
            $monthlySummaryStmt->execute();
            $monthlySummary = $monthlySummaryStmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'status' => true,
                'data' => [
                    'comissoes' => $commissions,
                    'totais' => [
                        'total_comissoes' => count($commissions),
                        'total_valor' => $totalValorComissoes,
                        'total_aprovadas' => $totalComissoesAprovadas,
                        'total_valor_aprovadas' => $totalValorComissoesAprovadas,
                        'total_pendentes' => $totalComissoesPendentes,
                        'total_valor_pendentes' => $totalValorComissoesPendentes
                    ],
                    'resumo_por_loja' => $storesSummary,
                    'resumo_mensal' => $monthlySummary,
                    'paginacao' => [
                        'total' => $totalCount,
                        'por_pagina' => $perPage,
                        'pagina_atual' => $page,
                        'total_paginas' => $totalPages
                    ]
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao obter comissões do administrador: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao obter comissões do administrador. Tente novamente.'];
        }
    }
    
    /**
    * Calcula a distribuição de comissões para uma transação
    * 
    * @param float $valorTotal Valor total da transação
    * @param float $porcentagemCashback Porcentagem de cashback da loja (opcional)
    * @return array Valores calculados (cliente, admin, loja)
    */
    public static function calculateCommissionDistribution($valorTotal, $porcentagemCashback = null) {
        try {
            $db = Database::getConnection();
            
            // Obter configurações de cashback
            $configStmt = $db->prepare("SELECT * FROM configuracoes_cashback ORDER BY id DESC LIMIT 1");
            $configStmt->execute();
            $config = $configStmt->fetch(PDO::FETCH_ASSOC);
            
            // Usar valores padrão: 10% total, divididos em 5% cliente e 5% admin
            $porcentagemTotal = DEFAULT_CASHBACK_TOTAL; // Sempre 10%
            $porcentagemCliente = DEFAULT_CASHBACK_CLIENT; // 5%
            $porcentagemAdmin = DEFAULT_CASHBACK_ADMIN; // 5%
            $porcentagemLoja = 0.00; // Loja sempre recebe 0%
            
            // Se existirem configurações personalizadas, usar elas
            if ($config && isset($config['porcentagem_cliente']) && isset($config['porcentagem_admin'])) {
                $porcentagemCliente = $config['porcentagem_cliente'];
                $porcentagemAdmin = $config['porcentagem_admin'];
                
                // Recalcular porcentagem total (deve ser sempre 10%)
                $porcentagemTotal = $porcentagemCliente + $porcentagemAdmin;
                
                // Se o total for diferente de 10%, ajustar proporcionalmente
                if ($porcentagemTotal != 10.00) {
                    $fator = 10.00 / $porcentagemTotal;
                    $porcentagemCliente = $porcentagemCliente * $fator;
                    $porcentagemAdmin = $porcentagemAdmin * $fator;
                    $porcentagemTotal = 10.00;
                }
            }
            
            // CORREÇÃO: Ignorar porcentagem específica da loja, sempre usar 10% total
            // A divisão é sempre proporcional entre cliente e admin, loja sempre 0%
            
            // Calcular valores
            $valorCashbackTotal = ($valorTotal * $porcentagemTotal) / 100;
            $valorCashbackCliente = ($valorTotal * $porcentagemCliente) / 100;
            $valorCashbackAdmin = ($valorTotal * $porcentagemAdmin) / 100;
            $valorCashbackLoja = 0.00; // Loja não recebe cashback
            
            return [
                'valor_total' => $valorTotal,
                'porcentagem_total' => $porcentagemTotal,
                'valor_cashback_total' => $valorCashbackTotal,
                'valores' => [
                    'cliente' => [
                        'porcentagem' => $porcentagemCliente,
                        'valor' => $valorCashbackCliente
                    ],
                    'admin' => [
                        'porcentagem' => $porcentagemAdmin,
                        'valor' => $valorCashbackAdmin
                    ],
                    'loja' => [
                        'porcentagem' => $porcentagemLoja,
                        'valor' => $valorCashbackLoja
                    ]
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao calcular distribuição de comissões: ' . $e->getMessage());
            
            // Retornar valores padrão em caso de erro
            return [
                'valor_total' => $valorTotal,
                'porcentagem_total' => DEFAULT_CASHBACK_TOTAL,
                'valor_cashback_total' => ($valorTotal * DEFAULT_CASHBACK_TOTAL) / 100,
                'valores' => [
                    'cliente' => [
                        'porcentagem' => DEFAULT_CASHBACK_CLIENT,
                        'valor' => ($valorTotal * DEFAULT_CASHBACK_CLIENT) / 100
                    ],
                    'admin' => [
                        'porcentagem' => DEFAULT_CASHBACK_ADMIN,
                        'valor' => ($valorTotal * DEFAULT_CASHBACK_ADMIN) / 100
                    ],
                    'loja' => [
                        'porcentagem' => 0.00,
                        'valor' => 0.00
                    ]
                ]
            ];
        }
    }
    
    /**
     * Atualiza o status de uma comissão
     * 
     * @param int $commissionId ID da comissão
     * @param string $newStatus Novo status (aprovado, pendente, cancelado)
     * @param string $observacao Observação sobre a atualização
     * @return array Resultado da operação
     */
    public static function updateCommissionStatus($commissionId, $newStatus, $observacao = '') {
        try {
            // Verificar se o usuário está autenticado e é administrador
            if (!AuthController::isAuthenticated() || !AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            // Validar status
            $validStatus = [TRANSACTION_APPROVED, TRANSACTION_PENDING, TRANSACTION_CANCELED];
            if (!in_array($newStatus, $validStatus)) {
                return ['status' => false, 'message' => 'Status inválido.'];
            }
            
            $db = Database::getConnection();
            
            // Verificar se a comissão existe
            $commissionStmt = $db->prepare("
                SELECT tc.*, t.id as transacao_id, l.id as loja_id, l.nome_fantasia as loja_nome
                FROM transacoes_comissao tc
                JOIN transacoes_cashback t ON tc.transacao_id = t.id
                JOIN lojas l ON tc.loja_id = l.id
                WHERE tc.id = :commission_id
            ");
            $commissionStmt->bindParam(':commission_id', $commissionId);
            $commissionStmt->execute();
            $commission = $commissionStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$commission) {
                return ['status' => false, 'message' => 'Comissão não encontrada.'];
            }
            
            // Se o status já for o mesmo, não faz nada
            if ($commission['status'] == $newStatus) {
                return ['status' => true, 'message' => 'O status já é ' . $newStatus . '.'];
            }
            
            // Iniciar transação
            $db->beginTransaction();
            
            // Salvar status anterior
            $oldStatus = $commission['status'];
            
            // Atualizar status da comissão
            $updateStmt = $db->prepare("
                UPDATE transacoes_comissao 
                SET status = :new_status 
                WHERE id = :commission_id
            ");
            $updateStmt->bindParam(':new_status', $newStatus);
            $updateStmt->bindParam(':commission_id', $commissionId);
            $updateStmt->execute();
            
            // Registrar histórico de alteração
            $historyStmt = $db->prepare("
                INSERT INTO comissoes_status_historico (
                    comissao_id, status_anterior, status_novo, 
                    observacao, data_alteracao, usuario_id
                ) VALUES (
                    :comissao_id, :status_anterior, :status_novo,
                    :observacao, NOW(), :usuario_id
                )
            ");
            
            $historyStmt->bindParam(':comissao_id', $commissionId);
            $historyStmt->bindParam(':status_anterior', $oldStatus);
            $historyStmt->bindParam(':status_novo', $newStatus);
            $historyStmt->bindParam(':observacao', $observacao);
            $userId = AuthController::getCurrentUserId();
            $historyStmt->bindParam(':usuario_id', $userId);
            $historyStmt->execute();
            
            // Se a comissão for do tipo 'admin', notificar administrador
            if ($commission['tipo_usuario'] == 'admin') {
                // Notificar apenas se houver alteração significativa (ex: para aprovado)
                if ($newStatus == TRANSACTION_APPROVED && $oldStatus != TRANSACTION_APPROVED) {
                    $userId = $commission['usuario_id']; // ID do admin
                    
                    // Criar notificação
                    $titulo = 'Comissão aprovada';
                    $mensagem = 'Uma comissão de R$ ' . number_format($commission['valor_comissao'], 2, ',', '.') . 
                                ' da loja ' . $commission['loja_nome'] . ' foi aprovada.';
                    $tipo = 'success';
                    
                    self::createNotification($userId, $titulo, $mensagem, $tipo);
                }
            }
            
            // Se a comissão for do tipo 'loja', notificar loja
            if ($commission['tipo_usuario'] == 'loja') {
                // Verificar se a loja tem usuário associado
                $storeUserStmt = $db->prepare("SELECT usuario_id FROM lojas WHERE id = :loja_id");
                $storeUserStmt->bindParam(':loja_id', $commission['loja_id']);
                $storeUserStmt->execute();
                $storeUser = $storeUserStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($storeUser && !empty($storeUser['usuario_id'])) {
                    $userId = $storeUser['usuario_id'];
                    
                    // Criar notificação baseada no novo status
                    $titulo = '';
                    $mensagem = '';
                    $tipo = '';
                    
                    if ($newStatus == TRANSACTION_APPROVED) {
                        $titulo = 'Comissão aprovada';
                        $mensagem = 'Uma comissão de R$ ' . number_format($commission['valor_comissao'], 2, ',', '.') . 
                                    ' foi aprovada.';
                        $tipo = 'success';
                    } elseif ($newStatus == TRANSACTION_PENDING) {
                        $titulo = 'Comissão aguardando aprovação';
                        $mensagem = 'Uma comissão de R$ ' . number_format($commission['valor_comissao'], 2, ',', '.') . 
                                    ' está aguardando aprovação.';
                        $tipo = 'info';
                    } elseif ($newStatus == TRANSACTION_CANCELED) {
                        $titulo = 'Comissão cancelada';
                        $mensagem = 'Uma comissão de R$ ' . number_format($commission['valor_comissao'], 2, ',', '.') . 
                                    ' foi cancelada.';
                        $tipo = 'error';
                    }
                    
                    if (!empty($titulo)) {
                        self::createNotification($userId, $titulo, $mensagem, $tipo);
                    }
                }
            }
            
            // Confirmar transação
            $db->commit();
            
            return [
                'status' => true, 
                'message' => 'Status da comissão atualizado com sucesso.',
                'data' => [
                    'id' => $commissionId,
                    'status_anterior' => $oldStatus,
                    'status_novo' => $newStatus
                ]
            ];
            
        } catch (PDOException $e) {
            // Reverter transação em caso de erro
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            
            error_log('Erro ao atualizar status da comissão: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao atualizar status da comissão. Tente novamente.'];
        }
    }
    
    /**
     * Gera um relatório financeiro de comissões
     * 
     * @param array $filters Filtros para o relatório
     * @return array Dados do relatório
     */
    public static function generateCommissionReport($filters = []) {
        try {
            // Verificar se o usuário está autenticado e é administrador
            if (!AuthController::isAuthenticated() || !AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            $db = Database::getConnection();
            
            // Preparar condições de filtro
            $conditions = "WHERE 1=1";
            $params = [];
            
            if (!empty($filters)) {
                // Filtro por período
                if (isset($filters['data_inicio']) && !empty($filters['data_inicio'])) {
                    $conditions .= " AND tc.data_transacao >= :data_inicio";
                    $params[':data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
                }
                
                if (isset($filters['data_fim']) && !empty($filters['data_fim'])) {
                    $conditions .= " AND tc.data_transacao <= :data_fim";
                    $params[':data_fim'] = $filters['data_fim'] . ' 23:59:59';
                }
                
                // Filtro por tipo de usuário (admin/loja)
                if (isset($filters['tipo_usuario']) && !empty($filters['tipo_usuario'])) {
                    $conditions .= " AND tc.tipo_usuario = :tipo_usuario";
                    $params[':tipo_usuario'] = $filters['tipo_usuario'];
                }
                
                // Filtro por status
                if (isset($filters['status']) && !empty($filters['status'])) {
                    $conditions .= " AND tc.status = :status";
                    $params[':status'] = $filters['status'];
                }
                
                // Filtro por loja
                if (isset($filters['loja_id']) && !empty($filters['loja_id'])) {
                    $conditions .= " AND tc.loja_id = :loja_id";
                    $params[':loja_id'] = $filters['loja_id'];
                }
            }
            
            // Relatório geral
            $generalQuery = "
                SELECT 
                    COUNT(*) as total_comissoes,
                    SUM(tc.valor_comissao) as valor_total_comissoes,
                    SUM(CASE WHEN tc.status = 'aprovado' THEN tc.valor_comissao ELSE 0 END) as valor_comissoes_aprovadas,
                    SUM(CASE WHEN tc.status = 'pendente' THEN tc.valor_comissao ELSE 0 END) as valor_comissoes_pendentes,
                    SUM(CASE WHEN tc.tipo_usuario = 'admin' THEN tc.valor_comissao ELSE 0 END) as valor_comissoes_admin,
                    SUM(CASE WHEN tc.tipo_usuario = 'loja' THEN tc.valor_comissao ELSE 0 END) as valor_comissoes_lojas,
                    AVG(tc.valor_comissao) as media_valor_comissao
                FROM transacoes_comissao tc
                $conditions
            ";
            
            $generalStmt = $db->prepare($generalQuery);
            foreach ($params as $param => $value) {
                $generalStmt->bindValue($param, $value);
            }
            $generalStmt->execute();
            $generalData = $generalStmt->fetch(PDO::FETCH_ASSOC);
            
            // Relatório por mês
            $monthlyQuery = "
                SELECT 
                    DATE_FORMAT(tc.data_transacao, '%Y-%m') as mes,
                    COUNT(*) as total_comissoes,
                    SUM(tc.valor_comissao) as valor_total_comissoes,
                    SUM(CASE WHEN tc.tipo_usuario = 'admin' THEN tc.valor_comissao ELSE 0 END) as valor_comissoes_admin,
                    SUM(CASE WHEN tc.tipo_usuario = 'loja' THEN tc.valor_comissao ELSE 0 END) as valor_comissoes_lojas
                FROM transacoes_comissao tc
                $conditions
                GROUP BY DATE_FORMAT(tc.data_transacao, '%Y-%m')
                ORDER BY mes ASC
            ";
            
            $monthlyStmt = $db->prepare($monthlyQuery);
            foreach ($params as $param => $value) {
                $monthlyStmt->bindValue($param, $value);
            }
            $monthlyStmt->execute();
            $monthlyData = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Relatório por loja
            $storesQuery = "
                SELECT 
                    l.id as loja_id,
                    l.nome_fantasia,
                    COUNT(tc.id) as total_comissoes,
                    SUM(tc.valor_comissao) as valor_total_comissoes,
                    SUM(CASE WHEN tc.tipo_usuario = 'admin' THEN tc.valor_comissao ELSE 0 END) as valor_comissoes_admin,
                    SUM(CASE WHEN tc.tipo_usuario = 'loja' THEN tc.valor_comissao ELSE 0 END) as valor_comissoes_lojas,
                    SUM(CASE WHEN tc.status = 'aprovado' THEN tc.valor_comissao ELSE 0 END) as valor_comissoes_aprovadas,
                    SUM(CASE WHEN tc.status = 'pendente' THEN tc.valor_comissao ELSE 0 END) as valor_comissoes_pendentes
                FROM transacoes_comissao tc
                JOIN lojas l ON tc.loja_id = l.id
                $conditions
                GROUP BY l.id
                ORDER BY valor_total_comissoes DESC
            ";
            
            $storesStmt = $db->prepare($storesQuery);
            foreach ($params as $param => $value) {
                $storesStmt->bindValue($param, $value);
            }
            $storesStmt->execute();
            $storesData = $storesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Relatório de pagamentos
            $paymentsQuery = "
                SELECT 
                    status,
                    COUNT(*) as total_pagamentos,
                    SUM(valor_total) as valor_total_pagamentos
                FROM pagamentos_comissao
                GROUP BY status
            ";
            
            $paymentsStmt = $db->prepare($paymentsQuery);
            $paymentsStmt->execute();
            $paymentsData = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Consolidar resultados
            $report = [
                'geral' => $generalData,
                'mensal' => $monthlyData,
                'por_loja' => $storesData,
                'pagamentos' => $paymentsData,
                'filtros_aplicados' => $filters,
                'data_geracao' => date('Y-m-d H:i:s')
            ];
            
            return [
                'status' => true,
                'data' => $report
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao gerar relatório de comissões: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao gerar relatório de comissões. Tente novamente.'];
        }
    }
    
    /**
     * Exporta dados de comissões para CSV
     * 
     * @param array $filters Filtros para os dados
     * @return array Caminho do arquivo gerado ou erro
     */
    public static function exportCommissionsCSV($filters = []) {
        try {
            // Verificar se o usuário está autenticado e é administrador
            if (!AuthController::isAuthenticated() || !AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            $db = Database::getConnection();
            
            // Preparar condições de filtro
            $conditions = "WHERE 1=1";
            $params = [];
            
            if (!empty($filters)) {
                // Filtro por período
                if (isset($filters['data_inicio']) && !empty($filters['data_inicio'])) {
                    $conditions .= " AND tc.data_transacao >= :data_inicio";
                    $params[':data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
                }
                
                if (isset($filters['data_fim']) && !empty($filters['data_fim'])) {
                    $conditions .= " AND tc.data_transacao <= :data_fim";
                    $params[':data_fim'] = $filters['data_fim'] . ' 23:59:59';
                }
                
                // Filtro por tipo de usuário (admin/loja)
                if (isset($filters['tipo_usuario']) && !empty($filters['tipo_usuario'])) {
                    $conditions .= " AND tc.tipo_usuario = :tipo_usuario";
                    $params[':tipo_usuario'] = $filters['tipo_usuario'];
                }
                
                // Filtro por status
                if (isset($filters['status']) && !empty($filters['status'])) {
                    $conditions .= " AND tc.status = :status";
                    $params[':status'] = $filters['status'];
                }
                
                // Filtro por loja
                if (isset($filters['loja_id']) && !empty($filters['loja_id'])) {
                    $conditions .= " AND tc.loja_id = :loja_id";
                    $params[':loja_id'] = $filters['loja_id'];
                }
            }
            
            // Consulta para dados de exportação
            $query = "
                SELECT 
                    tc.id as id_comissao,
                    t.codigo_transacao,
                    tc.tipo_usuario,
                    l.nome_fantasia as loja,
                    u.nome as cliente,
                    t.valor_total as valor_venda,
                    tc.valor_comissao,
                    tc.status,
                    tc.data_transacao,
                    (SELECT p.id FROM pagamentos_comissao p 
                     JOIN pagamentos_transacoes pt ON p.id = pt.pagamento_id 
                     WHERE pt.transacao_id = t.id LIMIT 1) as id_pagamento
                FROM transacoes_comissao tc
                JOIN transacoes_cashback t ON tc.transacao_id = t.id
                JOIN lojas l ON tc.loja_id = l.id
                JOIN usuarios u ON t.usuario_id = u.id
                $conditions
                ORDER BY tc.data_transacao DESC
            ";
            
            $stmt = $db->prepare($query);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            $stmt->execute();
            
            // Verificar se há resultados
            if ($stmt->rowCount() == 0) {
                return ['status' => false, 'message' => 'Nenhum dado encontrado para exportação.'];
            }
            
            // Criar diretório de exportação se não existir
            $exportDir = ROOT_DIR . '/exports';
            if (!file_exists($exportDir)) {
                mkdir($exportDir, 0755, true);
            }
            
            // Nome do arquivo CSV
            $filename = 'comissoes_' . date('Ymd_His') . '.csv';
            $filepath = $exportDir . '/' . $filename;
            
            // Criar arquivo CSV
            $file = fopen($filepath, 'w');
            
            // UTF-8 BOM (para suporte de acentuação no Excel)
            fputs($file, "\xEF\xBB\xBF");
            
            // Cabeçalho
            $header = [
                'ID', 'Código Transação', 'Tipo', 'Loja', 'Cliente', 
                'Valor Venda (R$)', 'Valor Comissão (R$)', 'Status', 'Data', 'ID Pagamento'
            ];
            fputcsv($file, $header);
            
            // Dados
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $csvRow = [
                    $row['id_comissao'],
                    $row['codigo_transacao'],
                    $row['tipo_usuario'],
                    $row['loja'],
                    $row['cliente'],
                    number_format($row['valor_venda'], 2, ',', '.'),
                    number_format($row['valor_comissao'], 2, ',', '.'),
                    $row['status'],
                    date('d/m/Y H:i', strtotime($row['data_transacao'])),
                    $row['id_pagamento'] ?: 'N/A'
                ];
                
                fputcsv($file, $csvRow);
            }
            
            fclose($file);
            
            // Retornar caminho do arquivo
            return [
                'status' => true,
                'message' => 'Arquivo CSV gerado com sucesso.',
                'data' => [
                    'filename' => $filename,
                    'filepath' => str_replace(ROOT_DIR, SITE_URL, $filepath),
                    'total_registros' => $stmt->rowCount()
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao exportar comissões para CSV: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao exportar comissões. Tente novamente.'];
        }
    }
    
    /**
    * Atualiza as configurações de distribuição de comissões
    * 
    * @param array $data Novos valores de configuração
    * @return array Resultado da operação
    */
    public static function updateCommissionSettings($data) {
        try {
            // Verificar se o usuário está autenticado e é administrador
            if (!AuthController::isAuthenticated() || !AuthController::isAdmin()) {
                return ['status' => false, 'message' => 'Acesso restrito a administradores.'];
            }
            
            // Validar dados - remover validação da loja pois sempre será 0
            if (!isset($data['porcentagem_cliente']) || !is_numeric($data['porcentagem_cliente']) ||
                !isset($data['porcentagem_admin']) || !is_numeric($data['porcentagem_admin'])) {
                return ['status' => false, 'message' => 'Valores de porcentagem inválidos.'];
            }
            
            // Converter para float para evitar problemas com strings
            $porcentagemCliente = floatval($data['porcentagem_cliente']);
            $porcentagemAdmin = floatval($data['porcentagem_admin']);
            
            // Forçar porcentagem da loja como 0
            $data['porcentagem_loja'] = 0.00;
            
            // Calcular total
            $porcentagemTotal = $porcentagemCliente + $porcentagemAdmin;
            
            // CORREÇÃO: Validar que o total seja 10%
            if (abs($porcentagemTotal - 10.00) > 0.01) {
                // Ajustar proporcionalmente para somar 10%
                $fator = 10.00 / $porcentagemTotal;
                $porcentagemCliente = round($porcentagemCliente * $fator, 2);
                $porcentagemAdmin = round($porcentagemAdmin * $fator, 2);
                $porcentagemTotal = 10.00;
                
                // Atualizar os valores no array de dados
                $data['porcentagem_cliente'] = $porcentagemCliente;
                $data['porcentagem_admin'] = $porcentagemAdmin;
            }
            
            $db = Database::getConnection();
            
            // Verificar se a tabela existe
            $tableStmt = $db->prepare("SHOW TABLES LIKE 'configuracoes_cashback'");
            $tableStmt->execute();
            
            if ($tableStmt->rowCount() == 0) {
                // Criar tabela
                $createTableQuery = "
                    CREATE TABLE configuracoes_cashback (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        porcentagem_total DECIMAL(5,2) NOT NULL,
                        porcentagem_cliente DECIMAL(5,2) NOT NULL,
                        porcentagem_admin DECIMAL(5,2) NOT NULL,
                        porcentagem_loja DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                        data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ";
                $db->exec($createTableQuery);
            }
            
            // Inserir nova configuração
            $insertStmt = $db->prepare("
                INSERT INTO configuracoes_cashback (
                    porcentagem_total, porcentagem_cliente, porcentagem_admin, porcentagem_loja
                ) VALUES (
                    :porcentagem_total, :porcentagem_cliente, :porcentagem_admin, 0.00
                )
            ");
            
            $insertStmt->bindParam(':porcentagem_total', $porcentagemTotal);
            $insertStmt->bindParam(':porcentagem_cliente', $data['porcentagem_cliente']);
            $insertStmt->bindParam(':porcentagem_admin', $data['porcentagem_admin']);
            $insertStmt->execute();
            
            return [
                'status' => true,
                'message' => 'Configurações de comissão atualizadas com sucesso.',
                'data' => [
                    'porcentagem_total' => $porcentagemTotal,
                    'porcentagem_cliente' => $data['porcentagem_cliente'],
                    'porcentagem_admin' => $data['porcentagem_admin'],
                    'porcentagem_loja' => 0.00
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao atualizar configurações de comissão: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao atualizar configurações. Tente novamente.'];
        }
    }
    
    /**
     * Cria uma notificação para um usuário
     * 
     * @param int $userId ID do usuário
     * @param string $titulo Título da notificação
     * @param string $mensagem Mensagem da notificação
     * @param string $tipo Tipo da notificação (info, success, warning, error)
     * @return bool Verdadeiro se a notificação foi criada
     */
    private static function createNotification($userId, $titulo, $mensagem, $tipo = 'info') {
        try {
            $db = Database::getConnection();
            
            // Verificar se a tabela existe, criar se não existir
            $tableCheckStmt = $db->prepare("SHOW TABLES LIKE 'notificacoes'");
            $tableCheckStmt->execute();
            
            if ($tableCheckStmt->rowCount() == 0) {
                $createTableQuery = "
                    CREATE TABLE notificacoes (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        usuario_id INT NOT NULL,
                        titulo VARCHAR(100) NOT NULL,
                        mensagem TEXT NOT NULL,
                        tipo ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
                        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        lida TINYINT(1) DEFAULT 0,
                        data_leitura TIMESTAMP NULL,
                        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
                    )
                ";
                $db->exec($createTableQuery);
            }
            
            $stmt = $db->prepare("
                INSERT INTO notificacoes (usuario_id, titulo, mensagem, tipo, data_criacao, lida)
                VALUES (:usuario_id, :titulo, :mensagem, :tipo, NOW(), 0)
            ");
            
            $stmt->bindParam(':usuario_id', $userId);
            $stmt->bindParam(':titulo', $titulo);
            $stmt->bindParam(':mensagem', $mensagem);
            $stmt->bindParam(':tipo', $tipo);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log('Erro ao criar notificação: ' . $e->getMessage());
            return false;
        }
    }
}

// Processar requisições diretas de acesso ao controlador
if (basename($_SERVER['PHP_SELF']) === 'CommissionController.php') {
    // Verificar se o usuário está autenticado
    if (!AuthController::isAuthenticated()) {
        header('Location: ' . LOGIN_URL . '?error=' . urlencode('Você precisa fazer login para acessar esta página.'));
        exit;
    }
    
    $action = $_REQUEST['action'] ?? '';
    
    switch ($action) {
        case 'summary':
            if (!AuthController::isAdmin()) {
                echo json_encode(['status' => false, 'message' => 'Acesso restrito a administradores.']);
                exit;
            }
            
            $filters = $_POST['filters'] ?? [];
            $result = CommissionController::getCommissionSummary($filters);
            echo json_encode($result);
            break;
            
        case 'store_commissions':
            $storeId = isset($_POST['loja_id']) ? intval($_POST['loja_id']) : 0;
            $filters = $_POST['filters'] ?? [];
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $result = CommissionController::getStoreCommissions($storeId, $filters, $page);
            echo json_encode($result);
            break;
            
        case 'admin_commissions':
            if (!AuthController::isAdmin()) {
                echo json_encode(['status' => false, 'message' => 'Acesso restrito a administradores.']);
                exit;
            }
            
            $filters = $_POST['filters'] ?? [];
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $result = CommissionController::getAdminCommissions($filters, $page);
            echo json_encode($result);
            break;
            
        case 'calculate':
            $valorTotal = isset($_POST['valor_total']) ? floatval($_POST['valor_total']) : 0;
            $porcentagemCashback = isset($_POST['porcentagem_cashback']) ? floatval($_POST['porcentagem_cashback']) : null;
            $result = CommissionController::calculateCommissionDistribution($valorTotal, $porcentagemCashback);
            echo json_encode(['status' => true, 'data' => $result]);
            break;
            
        case 'update_status':
            if (!AuthController::isAdmin()) {
                echo json_encode(['status' => false, 'message' => 'Acesso restrito a administradores.']);
                exit;
            }
            
            $commissionId = isset($_POST['commission_id']) ? intval($_POST['commission_id']) : 0;
            $newStatus = $_POST['status'] ?? '';
            $observacao = $_POST['observacao'] ?? '';
            $result = CommissionController::updateCommissionStatus($commissionId, $newStatus, $observacao);
            echo json_encode($result);
            break;
            
        case 'report':
            if (!AuthController::isAdmin()) {
                echo json_encode(['status' => false, 'message' => 'Acesso restrito a administradores.']);
                exit;
            }
            
            $filters = $_POST['filters'] ?? [];
            $result = CommissionController::generateCommissionReport($filters);
            echo json_encode($result);
            break;
            
        case 'export_csv':
            if (!AuthController::isAdmin()) {
                echo json_encode(['status' => false, 'message' => 'Acesso restrito a administradores.']);
                exit;
            }
            
            $filters = $_POST['filters'] ?? [];
            $result = CommissionController::exportCommissionsCSV($filters);
            echo json_encode($result);
            break;
            
        case 'update_settings':
            if (!AuthController::isAdmin()) {
                echo json_encode(['status' => false, 'message' => 'Acesso restrito a administradores.']);
                exit;
            }
            
            $data = $_POST;
            $result = CommissionController::updateCommissionSettings($data);
            echo json_encode($result);
            break;
            
        default:
            // Acesso inválido ao controlador
            if (AuthController::isAdmin()) {
                header('Location: ' . ADMIN_DASHBOARD_URL);
            } elseif (AuthController::isStore()) {
                header('Location: ' . STORE_DASHBOARD_URL);
            } else {
                header('Location: ' . CLIENT_DASHBOARD_URL);
            }
            exit;
    }
}
?>