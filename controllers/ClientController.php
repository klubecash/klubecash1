<?php
// controllers/ClientController.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/AuthController.php';

/**
 * Controlador do Cliente
 * Gerencia operações relacionadas a clientes como obtenção de extrato,
 * visualização de cashback, perfil e interação com lojas parceiras
 */
class ClientController {
    
    /**
    * Obtém detalhes específicos de saldo de uma loja para o cliente (CORRIGIDO)
    */
    public static function getStoreBalanceDetails($userId, $lojaId) {
        try {
            if (!self::validateClient($userId)) {
                return ['status' => false, 'message' => 'Cliente não encontrado ou inativo.'];
            }
            
            $db = Database::getConnection();
            
            // Verificar se a loja existe
            $storeStmt = $db->prepare("
                SELECT id, nome_fantasia, categoria, porcentagem_cashback, website, descricao, logo
                FROM lojas 
                WHERE id = :loja_id AND status = :status
            ");
            $storeStmt->bindParam(':loja_id', $lojaId);
            $status = STORE_APPROVED;
            $storeStmt->bindParam(':status', $status);
            $storeStmt->execute();
            $loja = $storeStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$loja) {
                return ['status' => false, 'message' => 'Loja não encontrada.'];
            }
            
            // Obter saldo do cliente nesta loja
            $saldoStmt = $db->prepare("
                SELECT 
                    saldo_disponivel,
                    total_creditado,
                    total_usado,
                    data_criacao,
                    ultima_atualizacao
                FROM cashback_saldos 
                WHERE usuario_id = :user_id AND loja_id = :loja_id
            ");
            $saldoStmt->bindParam(':user_id', $userId);
            $saldoStmt->bindParam(':loja_id', $lojaId);
            $saldoStmt->execute();
            $saldo = $saldoStmt->fetch(PDO::FETCH_ASSOC);
            
            // Se não existe saldo, criar array padrão
            if (!$saldo) {
                $saldo = [
                    'saldo_disponivel' => 0,
                    'total_creditado' => 0,
                    'total_usado' => 0,
                    'data_criacao' => null,
                    'ultima_atualizacao' => null
                ];
            }
            
            // Obter movimentações recentes (últimas 10)
            $movimentacoesStmt = $db->prepare("
                SELECT 
                    id,
                    tipo_operacao,
                    valor,
                    saldo_anterior,
                    saldo_atual,
                    descricao,
                    data_operacao,
                    transacao_origem_id,
                    transacao_uso_id
                FROM cashback_movimentacoes 
                WHERE usuario_id = :user_id AND loja_id = :loja_id
                ORDER BY data_operacao DESC
                LIMIT 10
            ");
            $movimentacoesStmt->bindParam(':user_id', $userId);
            $movimentacoesStmt->bindParam(':loja_id', $lojaId);
            $movimentacoesStmt->execute();
            $movimentacoes = $movimentacoesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Obter estatísticas gerais
            $estatisticasStmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_movimentacoes,
                    COUNT(CASE WHEN tipo_operacao = 'credito' THEN 1 END) as total_creditos,
                    COUNT(CASE WHEN tipo_operacao = 'uso' THEN 1 END) as total_usos,
                    COUNT(CASE WHEN tipo_operacao = 'estorno' THEN 1 END) as total_estornos,
                    MIN(data_operacao) as primeira_movimentacao,
                    MAX(data_operacao) as ultima_movimentacao
                FROM cashback_movimentacoes 
                WHERE usuario_id = :user_id AND loja_id = :loja_id
            ");
            $estatisticasStmt->bindParam(':user_id', $userId);
            $estatisticasStmt->bindParam(':loja_id', $lojaId);
            $estatisticasStmt->execute();
            $estatisticas = $estatisticasStmt->fetch(PDO::FETCH_ASSOC);
            
            // Obter dados mensais para gráfico (últimos 6 meses)
            $dadosMensaisStmt = $db->prepare("
                SELECT 
                    DATE_FORMAT(data_operacao, '%Y-%m') as mes,
                    SUM(CASE WHEN tipo_operacao = 'credito' THEN valor ELSE 0 END) as creditos,
                    SUM(CASE WHEN tipo_operacao = 'uso' THEN valor ELSE 0 END) as usos,
                    SUM(CASE WHEN tipo_operacao = 'estorno' THEN valor ELSE 0 END) as estornos
                FROM cashback_movimentacoes
                WHERE usuario_id = :user_id AND loja_id = :loja_id
                AND data_operacao >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(data_operacao, '%Y-%m')
                ORDER BY mes ASC
            ");
            $dadosMensaisStmt->bindParam(':user_id', $userId);
            $dadosMensaisStmt->bindParam(':loja_id', $lojaId);
            $dadosMensaisStmt->execute();
            $dadosMensais = $dadosMensaisStmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'status' => true,
                'data' => [
                    'loja' => $loja,
                    'saldo' => $saldo,
                    'movimentacoes' => $movimentacoes,
                    'estatisticas' => $estatisticas,
                    'dados_mensais' => $dadosMensais
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao obter detalhes do saldo da loja: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao carregar detalhes da loja.'];
        }
    }
    /**
     * Verifica se o CPF pode ser editado (não foi validado/salvo anteriormente)
     * 
     * @param int $userId ID do usuário
     * @return bool true se pode editar, false se está fixo
     */
    private static function canEditCPF($userId) {
        try {
            $db = Database::getConnection();
            
            // Verificar se já existe CPF válido salvo
            $stmt = $db->prepare("SELECT cpf FROM usuarios WHERE id = :user_id");
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Se CPF existe e não está vazio, não pode mais ser editado
            return empty($user['cpf']);
            
        } catch (Exception $e) {
            error_log('Erro ao verificar edição de CPF: ' . $e->getMessage());
            return true; // Em caso de erro, permitir edição por segurança
        }
    }

    /**
    * Simula o uso de saldo de uma loja específica
    * 
    * @param int $userId ID do cliente
    * @param int $lojaId ID da loja
    * @param float $valor Valor a ser usado
    * @return array Resultado da simulação
    */
    public static function simulateBalanceUse($userId, $lojaId, $valor) {
        try {
            if (!self::validateClient($userId)) {
                return ['status' => false, 'message' => 'Cliente não encontrado ou inativo.'];
            }
            
            $db = Database::getConnection();
            
            // Obter saldo atual
            $saldoStmt = $db->prepare("
                SELECT saldo_disponivel 
                FROM cashback_saldos 
                WHERE usuario_id = :user_id AND loja_id = :loja_id
            ");
            $saldoStmt->bindParam(':user_id', $userId);
            $saldoStmt->bindParam(':loja_id', $lojaId);
            $saldoStmt->execute();
            $saldo = $saldoStmt->fetch(PDO::FETCH_ASSOC);
            
            $saldoAtual = $saldo ? floatval($saldo['saldo_disponivel']) : 0;
            $valorSolicitado = floatval($valor);
            
            $podeUsar = $saldoAtual >= $valorSolicitado && $valorSolicitado > 0;
            $saldoRestante = $podeUsar ? ($saldoAtual - $valorSolicitado) : $saldoAtual;
            
            return [
                'status' => true,
                'data' => [
                    'pode_usar' => $podeUsar,
                    'saldo_atual' => $saldoAtual,
                    'valor_solicitado' => $valorSolicitado,
                    'saldo_restante' => $saldoRestante,
                    'mensagem' => $podeUsar ? 
                        'Valor disponível para uso' : 
                        'Saldo insuficiente. Disponível: R$ ' . number_format($saldoAtual, 2, ',', '.')
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao simular uso de saldo: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao processar simulação.'];
        }
    }
    /**
    * Obtém os dados do dashboard do cliente
    * 
    * @param int $userId ID do cliente
    * @return array Dados do dashboard
    */
    public static function getDashboardData($userId) {
        try {
            // Verificar se é um cliente válido
            if (!self::validateClient($userId)) {
                return ['status' => false, 'message' => 'Cliente não encontrado ou inativo.'];
            }
            
            $db = Database::getConnection();

            // Verificar e criar tabelas necessárias
            self::createFavoritesTableIfNotExists($db);
            self::createNotificationsTableIfNotExists($db);

            // Verificar se o perfil está incompleto e não há notificação recente
            if (self::isProfileIncomplete($userId) && !self::hasRecentProfileNotification($userId)) {
                // Enviar notificação para completar perfil
                self::notifyClient(
                    $userId, 
                    'Complete seu perfil', 
                    'Complete seus dados cadastrais para aproveitar melhor sua experiência no Klube Cash. Clique aqui para atualizar seu perfil agora.',
                    'warning',
                    CLIENT_PROFILE_URL
                );
            }

            // Obter saldo total de cashback
            $balanceStmt = $db->prepare("
                SELECT SUM(valor_cashback) as saldo_total
                FROM transacoes_cashback
                WHERE usuario_id = :user_id AND status = :status
            ");
            $balanceStmt->bindParam(':user_id', $userId);
            $status = TRANSACTION_APPROVED;
            $balanceStmt->bindParam(':status', $status);
            $balanceStmt->execute();
            $balanceData = $balanceStmt->fetch(PDO::FETCH_ASSOC);
            $totalBalance = $balanceData['saldo_total'] ?? 0;
            
            // Obter transações recentes
            $transactionsStmt = $db->prepare("
                SELECT t.*, l.nome_fantasia as loja_nome
                FROM transacoes_cashback t
                JOIN lojas l ON t.loja_id = l.id
                WHERE t.usuario_id = :user_id
                ORDER BY t.data_transacao DESC
                LIMIT 5
            ");
            $transactionsStmt->bindParam(':user_id', $userId);
            $transactionsStmt->execute();
            $recentTransactions = $transactionsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Obter estatísticas de cashback
            $statisticsStmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_transacoes,
                    SUM(valor_total) as total_compras,
                    SUM(valor_cashback) as total_cashback,
                    MAX(data_transacao) as ultima_transacao
                FROM transacoes_cashback
                WHERE usuario_id = :user_id AND status = :status
            ");
            $statisticsStmt->bindParam(':user_id', $userId);
            $statisticsStmt->bindParam(':status', $status);
            $statisticsStmt->execute();
            $statistics = $statisticsStmt->fetch(PDO::FETCH_ASSOC);
            
            // Obter lojas favoritas/mais utilizadas
            $favoritesStmt = $db->prepare("
                SELECT 
                    l.id, l.nome_fantasia, 
                    COUNT(t.id) as total_compras,
                    SUM(t.valor_cashback) as total_cashback,
                    l.porcentagem_cashback
                FROM transacoes_cashback t
                JOIN lojas l ON t.loja_id = l.id
                WHERE t.usuario_id = :user_id AND t.status = :status
                GROUP BY l.id
                ORDER BY total_compras DESC
                LIMIT 3
            ");
            $favoritesStmt->bindParam(':user_id', $userId);
            $favoritesStmt->bindParam(':status', $status);
            $favoritesStmt->execute();
            $favoriteStores = $favoritesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Obter notificações do cliente
            $notifications = self::getClientNotifications($userId);
            
            // Consolidar dados
            return [
                'status' => true,
                'data' => [
                    'saldo_total' => $totalBalance,
                    'transacoes_recentes' => $recentTransactions,
                    'estatisticas' => $statistics,
                    'lojas_favoritas' => $favoriteStores,
                    'notificacoes' => $notifications
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao obter dados do dashboard: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao carregar dados do dashboard: ' . $e->getMessage()];
        }
    }
    
    /**
     * Obtém o extrato de transações do cliente
     * 
     * @param int $userId ID do cliente
     * @param array $filters Filtros para o extrato (período, loja, etc)
     * @param int $page Página atual
     * @return array Extrato de transações
     */
    public static function getStatement($userId, $filters = [], $page = 1, $limit = null) {
        try {
            // Verificar se é um cliente válido
            if (!self::validateClient($userId)) {
                return ['status' => false, 'message' => 'Cliente não encontrado ou inativo.'];
            }
            
            $db = Database::getConnection();
            
            // Preparar consulta base
            $query = "
                SELECT t.*, l.nome_fantasia as loja_nome
                FROM transacoes_cashback t
                JOIN lojas l ON t.loja_id = l.id
                WHERE t.usuario_id = :user_id
            ";
            
            $params = [':user_id' => $userId];
            
            // Aplicar filtros
            if (!empty($filters)) {
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
                
                // Filtro por loja
                if (isset($filters['loja_id']) && !empty($filters['loja_id'])) {
                    $query .= " AND t.loja_id = :loja_id";
                    $params[':loja_id'] = $filters['loja_id'];
                }
                
                // Filtro por status
                if (isset($filters['status']) && !empty($filters['status'])) {
                    $query .= " AND t.status = :status";
                    $params[':status'] = $filters['status'];
                }
            }
            
            // Adicionar ordenação
            $query .= " ORDER BY t.data_transacao DESC";
            
            // Calcular total de registros para paginação
            $countStmt = $db->prepare(str_replace('t.*, l.nome_fantasia as loja_nome', 'COUNT(*) as total', $query));
            foreach ($params as $param => $value) {
                $countStmt->bindValue($param, $value);
            }
            $countStmt->execute();
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Adicionar paginação
            $perPage = isset($limit) ? $limit : ITEMS_PER_PAGE;
            $offset = ($page - 1) * $perPage;
            $query .= " LIMIT $offset, $perPage";
            
            // Executar consulta
            $stmt = $db->prepare($query);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            $stmt->execute();
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular estatísticas
            $statisticsQuery = "
                SELECT
                    SUM(valor_total) as total_compras,
                    SUM(CASE WHEN status = :status_approved THEN valor_cashback ELSE 0 END) as total_cashback, /* Esta linha já considera o valor_cashback (do cliente) */
                    COUNT(*) as total_transacoes
                FROM transacoes_cashback
                WHERE usuario_id = :user_id
            ";
            
            // Aplicar os mesmos filtros nas estatísticas
            if (!empty($filters)) {
                if (isset($filters['data_inicio']) && !empty($filters['data_inicio'])) {
                    $statisticsQuery .= " AND data_transacao >= :data_inicio";
                }

                if (isset($filters['data_fim']) && !empty($filters['data_fim'])) {
                    $statisticsQuery .= " AND data_transacao <= :data_fim";
                }

                if (isset($filters['loja_id']) && !empty($filters['loja_id'])) {
                    $statisticsQuery .= " AND loja_id = :loja_id";
                }

                // If status filter is 'aprovado', it will be applied here too
                // If status filter is 'todos' or not present, only 'aprovado' cashback will be summed for total_cashback
                if (isset($filters['status']) && !empty($filters['status'])) {
                    $statisticsQuery .= " AND status = :status";
                }
            }
            
            $statsStmt = $db->prepare($statisticsQuery);
            foreach ($params as $param => $value) {
                $statsStmt->bindValue($param, $value);
            }
            // Bind the new parameter for approved status
            $approvedStatus = TRANSACTION_APPROVED; // ou 'aprovado' dependendo da sua constante
            $statsStmt->bindValue(':status_approved', $approvedStatus);


            $statsStmt->execute();
            $statistics = $statsStmt->fetch(PDO::FETCH_ASSOC);
            
            // Calcular informações de paginação
            $totalPages = ceil($totalCount / $perPage);
            
            return [
                'status' => true,
                'data' => [
                    'transacoes' => $transactions,
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
            error_log('Erro ao obter extrato: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao carregar extrato. Tente novamente.'];
        }
    }
    
    /**
     * Obtém lista de lojas parceiras para o cliente
     * 
     * @param int $userId ID do cliente
     * @param array $filters Filtros para as lojas
     * @param int $page Página atual
     * @return array Lista de lojas parceiras
     */
    public static function getPartnerStores($userId, $filters = [], $page = 1) {
        try {
            // Verificar se é um cliente válido
            if (!self::validateClient($userId)) {
                return ['status' => false, 'message' => 'Cliente não encontrado ou inativo.'];
            }
            
            $db = Database::getConnection();
            
            // Preparar consulta base (CORRIGIDA para evitar duplicatas)
            $query = "
                SELECT DISTINCT l.*, 
                    COALESCE(cs.saldo_disponivel, 0) as saldo_disponivel,
                    COALESCE(cs.total_creditado, 0) as cashback_recebido,
                    COALESCE(cs.total_usado, 0) as total_usado,
                    (SELECT COUNT(*) 
                        FROM transacoes_cashback t 
                        WHERE t.loja_id = l.id AND t.usuario_id = :user_id_count AND t.status = 'aprovado') as compras_realizadas,
                    COALESCE(
                        (SELECT SUM(t.valor_cliente) 
                            FROM transacoes_cashback t 
                            WHERE t.loja_id = l.id AND t.usuario_id = :user_id_pending AND t.status = 'pendente'), 
                        0
                    ) as cashback_pendente,
                    COALESCE(f.id, 0) as is_favorite
                FROM lojas l
                LEFT JOIN cashback_saldos cs ON l.id = cs.loja_id AND cs.usuario_id = :user_id
                LEFT JOIN lojas_favoritas f ON l.id = f.loja_id AND f.usuario_id = :user_id_fav
                WHERE l.status = :status
            ";
            
            $params = [
                ':user_id' => $userId,
                ':user_id_count' => $userId,
                ':user_id_pending' => $userId,
                ':user_id_fav' => $userId,
                ':status' => defined('STORE_APPROVED') ? STORE_APPROVED : 'aprovado'
            ];
            
            // Aplicar filtros
            if (!empty($filters)) {
                // Filtro por categoria
                if (isset($filters['categoria']) && !empty($filters['categoria'])) {
                    $query .= " AND l.categoria = :categoria";
                    $params[':categoria'] = $filters['categoria'];
                }
                
                // Filtro por nome
                if (isset($filters['nome']) && !empty($filters['nome'])) {
                    $query .= " AND (l.nome_fantasia LIKE :nome OR l.razao_social LIKE :nome)";
                    $params[':nome'] = '%' . $filters['nome'] . '%';
                }
                
                // Filtro por porcentagem de cashback
                if (isset($filters['cashback_min']) && !empty($filters['cashback_min'])) {
                    $query .= " AND l.porcentagem_cashback >= :cashback_min";
                    $params[':cashback_min'] = $filters['cashback_min'];
                }
            }
            
            // Adicionar ordenação
            $orderBy = isset($filters['ordenar']) ? $filters['ordenar'] : 'nome';
            $orderDir = isset($filters['order_dir']) && strtolower($filters['order_dir']) == 'desc' ? 'DESC' : 'ASC';
            
            // Mapear opções de ordenação
            $orderMapping = [
                'nome' => 'l.nome_fantasia',
                'cashback' => 'l.porcentagem_cashback',
                'categoria' => 'l.categoria'
            ];
            
            $orderColumn = isset($orderMapping[$orderBy]) ? $orderMapping[$orderBy] : 'l.nome_fantasia';
            $query .= " ORDER BY $orderColumn $orderDir";
            
            // Calcular total de registros para paginação (SIMPLIFICADO)
            $countQuery = "
                SELECT COUNT(DISTINCT l.id) as total
                FROM lojas l
                WHERE l.status = :status
            ";
            
            // Aplicar os mesmos filtros na contagem
            if (!empty($filters)) {
                if (isset($filters['categoria']) && !empty($filters['categoria'])) {
                    $countQuery .= " AND l.categoria = :categoria";
                }
                if (isset($filters['nome']) && !empty($filters['nome'])) {
                    $countQuery .= " AND (l.nome_fantasia LIKE :nome OR l.razao_social LIKE :nome)";
                }
                if (isset($filters['cashback_min']) && !empty($filters['cashback_min'])) {
                    $countQuery .= " AND l.porcentagem_cashback >= :cashback_min";
                }
            }
            
            $countStmt = $db->prepare($countQuery);
            foreach ($params as $param => $value) {
                if ($param !== ':user_id' && $param !== ':user_id_count' && $param !== ':user_id_pending' && $param !== ':user_id_fav') {
                    $countStmt->bindValue($param, $value);
                }
            }
            $countStmt->execute();
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Adicionar paginação
            $perPage = defined('ITEMS_PER_PAGE') ? ITEMS_PER_PAGE : 10;
            $offset = ($page - 1) * $perPage;
            $query .= " LIMIT $offset, $perPage";
            
            // Executar consulta
            $stmt = $db->prepare($query);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            $stmt->execute();
            $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Enriquecer dados com informações adicionais
            foreach ($stores as &$store) {
                // Buscar último uso se houver saldo usado
                if ($store['total_usado'] > 0) {
                    $ultimoUsoStmt = $db->prepare("
                        SELECT MAX(data_operacao) as ultima_data 
                        FROM cashback_movimentacoes 
                        WHERE usuario_id = ? AND loja_id = ? AND tipo_operacao = 'uso'
                    ");
                    $ultimoUsoStmt->execute([$userId, $store['id']]);
                    $ultimoUso = $ultimoUsoStmt->fetch(PDO::FETCH_ASSOC);
                    $store['ultimo_uso'] = $ultimoUso['ultima_data'] ?? null;
                    
                    // Buscar total de usos
                    $totalUsosStmt = $db->prepare("
                        SELECT COUNT(*) as total_usos 
                        FROM cashback_movimentacoes 
                        WHERE usuario_id = ? AND loja_id = ? AND tipo_operacao = 'uso'
                    ");
                    $totalUsosStmt->execute([$userId, $store['id']]);
                    $totalUsos = $totalUsosStmt->fetch(PDO::FETCH_ASSOC);
                    $store['total_usos'] = $totalUsos['total_usos'] ?? 0;
                } else {
                    $store['ultimo_uso'] = null;
                    $store['total_usos'] = 0;
                }
                
                // Converter is_favorite para boolean
                $store['is_favorite'] = $store['is_favorite'] > 0 ? 1 : 0;
            }
            
            // Obter categorias disponíveis para filtro
            $categoriesStmt = $db->prepare("SELECT DISTINCT categoria FROM lojas WHERE status = :status ORDER BY categoria");
            $categoriesStmt->bindValue(':status', defined('STORE_APPROVED') ? STORE_APPROVED : 'aprovado');
            $categoriesStmt->execute();
            $categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Obter estatísticas
            $statsStmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_lojas,
                    COALESCE(AVG(porcentagem_cashback), 0) as media_cashback,
                    COALESCE(MAX(porcentagem_cashback), 0) as maior_cashback
                FROM lojas
                WHERE status = :status
            ");
            $statsStmt->bindValue(':status', defined('STORE_APPROVED') ? STORE_APPROVED : 'aprovado');
            $statsStmt->execute();
            $statistics = $statsStmt->fetch(PDO::FETCH_ASSOC);
            
            // Calcular informações de paginação
            $totalPages = ceil($totalCount / $perPage);
            
            return [
                'status' => true,
                'data' => [
                    'lojas' => $stores,
                    'categorias' => $categories,
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
            error_log('Erro ao obter lojas parceiras: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao carregar lojas parceiras. Tente novamente.'];
        }
    }
    
    /**
     * Obtém detalhes do perfil do cliente
     * 
     * @param int $userId ID do cliente
     * @return array Dados do perfil
     */
    public static function getProfileData($userId) {
        try {
            error_log('Iniciando getProfileData para usuário ID: ' . $userId);
            
            $db = Database::getConnection();
            
            // NOVO: Verificar se a coluna CPF existe antes de tentar consultar
            try {
                $checkColumnStmt = $db->prepare("SHOW COLUMNS FROM usuarios LIKE 'cpf'");
                $checkColumnStmt->execute();
                $cpfColumnExists = $checkColumnStmt->rowCount() > 0;
                error_log('Coluna CPF existe na consulta getProfileData: ' . ($cpfColumnExists ? 'SIM' : 'NÃO'));
                
                if (!$cpfColumnExists) {
                    error_log('Criando coluna CPF durante getProfileData...');
                    $db->exec("ALTER TABLE usuarios ADD COLUMN cpf VARCHAR(14) NULL AFTER telefone");
                    error_log('Coluna CPF criada com sucesso durante getProfileData');
                }
            } catch (Exception $e) {
                error_log('Erro ao verificar/criar coluna CPF durante getProfileData: ' . $e->getMessage());
            }
            
            // Buscar dados principais (ATUALIZADO para incluir CPF)
            $sql = "
                SELECT u.nome, u.email, u.cpf, u.telefone, u.status, u.data_criacao, u.ultimo_login
                FROM usuarios u 
                WHERE u.id = :id
            ";
            
            error_log('SQL para buscar dados do usuário: ' . $sql);
            
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $userId);
            $executeResult = $stmt->execute();
            
            error_log('Resultado da execução da consulta principal: ' . ($executeResult ? 'SUCESSO' : 'FALHA'));
            
            if (!$executeResult) {
                $errorInfo = $stmt->errorInfo();
                error_log('Erro na consulta principal: ' . print_r($errorInfo, true));
                return ['status' => false, 'message' => 'Erro na consulta principal: ' . $errorInfo[2]];
            }
            
            if ($stmt->rowCount() === 0) {
                error_log('Usuário não encontrado com ID: ' . $userId);
                return ['status' => false, 'message' => 'Usuário não encontrado'];
            }
            
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log('Dados do usuário encontrados: ' . print_r($usuario, true));
            
            // NOVO: Verificar se CPF pode ser editado
            $cpfEditavel = self::canEditCPF($userId);
            $usuario['cpf_editavel'] = $cpfEditavel;
            error_log('CPF editável: ' . ($cpfEditavel ? 'SIM' : 'NÃO'));
            
            // Buscar dados de contato
            try {
                $contatoStmt = $db->prepare("
                    SELECT telefone, celular, email_alternativo 
                    FROM usuarios_contato 
                    WHERE usuario_id = :id
                ");
                $contatoStmt->bindParam(':id', $userId);
                $contatoStmt->execute();
                $contato = $contatoStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                error_log('Dados de contato: ' . print_r($contato, true));
            } catch (Exception $e) {
                error_log('Erro ao buscar contato: ' . $e->getMessage());
                $contato = [];
            }
            
            // Buscar endereço
            try {
                $enderecoStmt = $db->prepare("
                    SELECT cep, logradouro, numero, complemento, bairro, cidade, estado 
                    FROM usuarios_endereco 
                    WHERE usuario_id = :id AND principal = 1
                ");
                $enderecoStmt->bindParam(':id', $userId);
                $enderecoStmt->execute();
                $endereco = $enderecoStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                error_log('Dados de endereço: ' . print_r($endereco, true));
            } catch (Exception $e) {
                error_log('Erro ao buscar endereço: ' . $e->getMessage());
                $endereco = [];
            }
            
            // Buscar estatísticas
            try {
                $statsStmt = $db->prepare("
                    SELECT 
                        COALESCE(SUM(valor_cashback), 0) as total_cashback,
                        COUNT(*) as total_transacoes,
                        COALESCE(SUM(valor_total), 0) as total_compras,
                        COUNT(DISTINCT loja_id) as total_lojas_utilizadas
                    FROM transacoes_cashback 
                    WHERE usuario_id = :id AND status = 'aprovado'
                ");
                $statsStmt->bindParam(':id', $userId);
                $statsStmt->execute();
                $estatisticas = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [
                    'total_cashback' => 0,
                    'total_transacoes' => 0,
                    'total_compras' => 0,
                    'total_lojas_utilizadas' => 0
                ];
                error_log('Estatísticas: ' . print_r($estatisticas, true));
            } catch (Exception $e) {
                error_log('Erro ao buscar estatísticas: ' . $e->getMessage());
                $estatisticas = [
                    'total_cashback' => 0,
                    'total_transacoes' => 0,
                    'total_compras' => 0,
                    'total_lojas_utilizadas' => 0
                ];
            }
            
            $resultado = [
                'status' => true,
                'data' => [
                    'perfil' => $usuario,
                    'contato' => $contato,
                    'endereco' => $endereco,
                    'estatisticas' => $estatisticas
                ]
            ];
            
            error_log('Dados completos do perfil: ' . print_r($resultado, true));
            
            return $resultado;
            
        } catch (Exception $e) {
            error_log('ERRO DETALHADO em getProfileData: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return ['status' => false, 'message' => 'Erro ao carregar dados do perfil: ' . $e->getMessage()];
        }
    }
    
    /**
     * Atualiza os dados do perfil do cliente
     * 
     * @param int $userId ID do cliente
     * @param array $data Dados a serem atualizados
     * @return array Resultado da operação
     */
    public static function updateProfile($userId, $data) {
        try {
            // Registrar os dados recebidos para diagnóstico
            error_log('Tentando atualizar perfil para usuário ID: ' . $userId);
            error_log('Dados recebidos: ' . print_r($data, true));
            
            // Verificar se é um cliente válido
            if (!self::validateClient($userId)) {
                return ['status' => false, 'message' => 'Cliente não encontrado ou inativo.'];
            }
            
            $db = Database::getConnection();
            
            // Verificar se a coluna CPF existe na tabela usuarios
            try {
                $checkColumnStmt = $db->prepare("SHOW COLUMNS FROM usuarios LIKE 'cpf'");
                $checkColumnStmt->execute();
                $cpfColumnExists = $checkColumnStmt->rowCount() > 0;
                error_log('Coluna CPF existe na tabela usuarios: ' . ($cpfColumnExists ? 'SIM' : 'NÃO'));
                
                if (!$cpfColumnExists) {
                    error_log('ATENÇÃO: Coluna CPF não existe! Criando coluna...');
                    $db->exec("ALTER TABLE usuarios ADD COLUMN cpf VARCHAR(14) NULL AFTER telefone");
                    error_log('Coluna CPF criada com sucesso');
                }
            } catch (Exception $e) {
                error_log('Erro ao verificar/criar coluna CPF: ' . $e->getMessage());
            }
            
            // Verificar se as tabelas necessárias existem
            self::ensureTablesExist($db);
            
            // CORREÇÃO PRINCIPAL: Buscar CPF atual do usuário ANTES de processar
            $currentCpfStmt = $db->prepare("SELECT cpf FROM usuarios WHERE id = :user_id");
            $currentCpfStmt->bindParam(':user_id', $userId);
            $currentCpfStmt->execute();
            $currentUserData = $currentCpfStmt->fetch(PDO::FETCH_ASSOC);
            $currentCpf = $currentUserData['cpf'] ?? '';
            
            error_log('CPF atual no banco: ' . var_export($currentCpf, true));
            
            // Iniciar transação
            $db->beginTransaction();
            
            // Atualizar dados básicos
            $updateBasicData = false;
            $basicFields = [];
            $basicParams = [':user_id' => $userId];
            
            if (isset($data['nome']) && !empty($data['nome'])) {
                error_log('Atualizando nome do usuário: ' . $data['nome']);
                $basicFields[] = 'nome = :nome';
                $basicParams[':nome'] = $data['nome'];
                $updateBasicData = true;
            }
            
            // CORREÇÃO: Processar CPF apenas se foi REALMENTE alterado
            if (isset($data['cpf'])) {
                $newCpf = preg_replace('/\D/', '', $data['cpf']); // Remove caracteres não numéricos
                $currentCpfClean = preg_replace('/\D/', '', $currentCpf); // Limpar CPF atual também
                
                error_log('CPF enviado no formulário (limpo): ' . var_export($newCpf, true));
                error_log('CPF atual no banco (limpo): ' . var_export($currentCpfClean, true));
                
                // NOVA LÓGICA: Só aplicar restrições se o CPF foi REALMENTE alterado
                if ($newCpf !== $currentCpfClean) {
                    error_log('CPF foi alterado, aplicando validações...');
                    
                    // Verificar se CPF pode ser editado
                    if (!self::canEditCPF($userId)) {
                        error_log('Tentativa de alterar CPF já validado - operação negada');
                        $db->rollBack();
                        return ['status' => false, 'message' => 'CPF já foi validado e não pode ser alterado. Entre em contato com o suporte se necessário.'];
                    }
                    
                    if (!empty($newCpf)) {
                        error_log('Processando novo CPF: ' . $newCpf);
                        
                        // Validar CPF usando a classe Validator
                        $cpfValido = Validator::validaCPF($newCpf);
                        error_log('CPF é válido: ' . ($cpfValido ? 'SIM' : 'NÃO'));
                        
                        if (!$cpfValido) {
                            $db->rollBack();
                            return ['status' => false, 'message' => 'CPF informado é inválido.'];
                        }
                        
                        // Verificar se o CPF já existe para outro usuário
                        $checkCpfStmt = $db->prepare("SELECT id, nome FROM usuarios WHERE cpf = :cpf AND id != :user_id");
                        $checkCpfStmt->bindParam(':cpf', $newCpf);
                        $checkCpfStmt->bindParam(':user_id', $userId);
                        $checkCpfStmt->execute();
                        
                        if ($checkCpfStmt->rowCount() > 0) {
                            $existingUser = $checkCpfStmt->fetch(PDO::FETCH_ASSOC);
                            error_log('CPF já existe para outro usuário: ID ' . $existingUser['id'] . ' - ' . $existingUser['nome']);
                            $db->rollBack();
                            return ['status' => false, 'message' => 'Este CPF já está cadastrado para outro usuário.'];
                        }
                        
                        $basicFields[] = 'cpf = :cpf';
                        $basicParams[':cpf'] = $newCpf;
                        $updateBasicData = true;
                        error_log('CPF validado e será atualizado: ' . $newCpf);
                    }
                } else {
                    error_log('CPF não foi alterado, mantendo valor atual');
                    // CPF não foi alterado, não fazer nada
                }
            } else {
                error_log('CPF não foi enviado nos dados');
            }
            
            // Executar atualização dos dados básicos se houver campos para atualizar
            if ($updateBasicData && !empty($basicFields)) {
                $sql = "UPDATE usuarios SET " . implode(', ', $basicFields) . " WHERE id = :user_id";
                error_log('SQL de atualização básica: ' . $sql);
                error_log('Parâmetros: ' . print_r($basicParams, true));
                
                $updateStmt = $db->prepare($sql);
                foreach ($basicParams as $key => $value) {
                    $updateStmt->bindValue($key, $value);
                    error_log("Binding $key = " . var_export($value, true));
                }
                
                $executeResult = $updateStmt->execute();
                error_log('Resultado da execução: ' . ($executeResult ? 'SUCESSO' : 'FALHA'));
                
                if (!$executeResult) {
                    $errorInfo = $updateStmt->errorInfo();
                    error_log('Erro na atualização básica: ' . print_r($errorInfo, true));
                    $db->rollBack();
                    return ['status' => false, 'message' => 'Erro ao atualizar dados básicos: ' . ($errorInfo[2] ?? 'Erro desconhecido')];
                }
                
                error_log('Dados básicos atualizados com sucesso');
            } else {
                error_log('Nenhum dado básico para atualizar');
            }
            
            // Atualizar dados de contato
            if (isset($data['contato']) && is_array($data['contato'])) {
                error_log('Processando dados de contato...');
                
                $telefone = $data['contato']['telefone'] ?? '';
                $celular = $data['contato']['celular'] ?? '';
                $emailAlternativo = $data['contato']['email_alternativo'] ?? '';
                
                // Verificar se já existe registro de contato
                $checkContatoStmt = $db->prepare("SELECT id FROM usuarios_contato WHERE usuario_id = :user_id");
                $checkContatoStmt->bindParam(':user_id', $userId);
                $checkContatoStmt->execute();
                
                if ($checkContatoStmt->rowCount() > 0) {
                    // Atualizar registro existente
                    $updateContatoStmt = $db->prepare("
                        UPDATE usuarios_contato 
                        SET telefone = :telefone, celular = :celular, email_alternativo = :email_alternativo 
                        WHERE usuario_id = :user_id
                    ");
                } else {
                    // Inserir novo registro
                    $updateContatoStmt = $db->prepare("
                        INSERT INTO usuarios_contato (usuario_id, telefone, celular, email_alternativo) 
                        VALUES (:user_id, :telefone, :celular, :email_alternativo)
                    ");
                }
                
                $updateContatoStmt->bindParam(':user_id', $userId);
                $updateContatoStmt->bindParam(':telefone', $telefone);
                $updateContatoStmt->bindParam(':celular', $celular);
                $updateContatoStmt->bindParam(':email_alternativo', $emailAlternativo);
                
                $contatoResult = $updateContatoStmt->execute();
                
                if (!$contatoResult) {
                    $errorInfo = $updateContatoStmt->errorInfo();
                    error_log('Erro ao atualizar contato: ' . print_r($errorInfo, true));
                    $db->rollBack();
                    return ['status' => false, 'message' => 'Erro ao atualizar dados de contato: ' . ($errorInfo[2] ?? 'Erro desconhecido')];
                }
                
                error_log('Dados de contato atualizados com sucesso');
            }
            
            // Atualizar endereço (se fornecido)
            if (isset($data['endereco']) && is_array($data['endereco'])) {
                error_log('Processando dados de endereço...');
                
                $cep = $data['endereco']['cep'] ?? '';
                $logradouro = $data['endereco']['logradouro'] ?? '';
                $numero = $data['endereco']['numero'] ?? '';
                $complemento = $data['endereco']['complemento'] ?? '';
                $bairro = $data['endereco']['bairro'] ?? '';
                $cidade = $data['endereco']['cidade'] ?? '';
                $estado = $data['endereco']['estado'] ?? '';
                $principal = $data['endereco']['principal'] ?? 1;
                
                // Verificar se já existe endereço principal
                $checkEnderecoStmt = $db->prepare("SELECT id FROM usuarios_endereco WHERE usuario_id = :user_id AND principal = 1");
                $checkEnderecoStmt->bindParam(':user_id', $userId);
                $checkEnderecoStmt->execute();
                
                if ($checkEnderecoStmt->rowCount() > 0) {
                    // Atualizar endereço existente
                    $updateEnderecoStmt = $db->prepare("
                        UPDATE usuarios_endereco 
                        SET cep = :cep, logradouro = :logradouro, numero = :numero, 
                            complemento = :complemento, bairro = :bairro, cidade = :cidade, estado = :estado 
                        WHERE usuario_id = :user_id AND principal = 1
                    ");
                } else {
                    // Inserir novo endereço
                    $updateEnderecoStmt = $db->prepare("
                        INSERT INTO usuarios_endereco (usuario_id, cep, logradouro, numero, complemento, bairro, cidade, estado, principal) 
                        VALUES (:user_id, :cep, :logradouro, :numero, :complemento, :bairro, :cidade, :estado, :principal)
                    ");
                    $updateEnderecoStmt->bindParam(':principal', $principal);
                }
                
                $updateEnderecoStmt->bindParam(':user_id', $userId);
                $updateEnderecoStmt->bindParam(':cep', $cep);
                $updateEnderecoStmt->bindParam(':logradouro', $logradouro);
                $updateEnderecoStmt->bindParam(':numero', $numero);
                $updateEnderecoStmt->bindParam(':complemento', $complemento);
                $updateEnderecoStmt->bindParam(':bairro', $bairro);
                $updateEnderecoStmt->bindParam(':cidade', $cidade);
                $updateEnderecoStmt->bindParam(':estado', $estado);
                
                $enderecoResult = $updateEnderecoStmt->execute();
                
                if (!$enderecoResult) {
                    $errorInfo = $updateEnderecoStmt->errorInfo();
                    error_log('Erro ao atualizar endereço: ' . print_r($errorInfo, true));
                    $db->rollBack();
                    return ['status' => false, 'message' => 'Erro ao atualizar dados de endereço: ' . ($errorInfo[2] ?? 'Erro desconhecido')];
                }
                
                error_log('Dados de endereço atualizados com sucesso');
            }
            
            // Atualizar senha (se fornecida)
            if (isset($data['senha_atual']) && isset($data['nova_senha'])) {
                error_log('Processando alteração de senha...');
                
                // Verificar senha atual
                $checkSenhaStmt = $db->prepare("SELECT senha_hash FROM usuarios WHERE id = :user_id");
                $checkSenhaStmt->bindParam(':user_id', $userId);
                $checkSenhaStmt->execute();
                $userData = $checkSenhaStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$userData || !password_verify($data['senha_atual'], $userData['senha_hash'])) {
                    $db->rollBack();
                    return ['status' => false, 'message' => 'Senha atual incorreta.'];
                }
                
                // Atualizar para nova senha
                $novaSenhaHash = password_hash($data['nova_senha'], PASSWORD_DEFAULT);
                $updateSenhaStmt = $db->prepare("UPDATE usuarios SET senha_hash = :senha_hash WHERE id = :user_id");
                $updateSenhaStmt->bindParam(':senha_hash', $novaSenhaHash);
                $updateSenhaStmt->bindParam(':user_id', $userId);
                
                $senhaResult = $updateSenhaStmt->execute();
                
                if (!$senhaResult) {
                    $errorInfo = $updateSenhaStmt->errorInfo();
                    error_log('Erro ao atualizar senha: ' . print_r($errorInfo, true));
                    $db->rollBack();
                    return ['status' => false, 'message' => 'Erro ao atualizar senha: ' . ($errorInfo[2] ?? 'Erro desconhecido')];
                }
                
                error_log('Senha atualizada com sucesso');
            }
            
            // Confirmar transação
            $db->commit();
            error_log('Transação confirmada com sucesso');
            
            return ['status' => true, 'message' => 'Perfil atualizado com sucesso!'];
            
        } catch (Exception $e) {
            if (isset($db)) {
                $db->rollBack();
            }
            error_log('Erro ao atualizar perfil: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro interno do servidor. Tente novamente.'];
        }
    }



    
   public static function getProfileCompletionStatus($userId) {
    try {
        $db = Database::getConnection();
        
        // Verificar dados do usuário
        $userStmt = $db->prepare("SELECT nome, email, cpf, telefone FROM usuarios WHERE id = :user_id");
        $userStmt->bindParam(':user_id', $userId);
        $userStmt->execute();
        $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        // Verificar endereço
        $addrStmt = $db->prepare("SELECT * FROM usuarios_endereco WHERE usuario_id = :user_id LIMIT 1");
        $addrStmt->bindParam(':user_id', $userId);
        $addrStmt->execute();
        $endereco = $addrStmt->fetch(PDO::FETCH_ASSOC);
        
        // Verificar contato
        $contactStmt = $db->prepare("SELECT * FROM usuarios_contato WHERE usuario_id = :user_id LIMIT 1");
        $contactStmt->bindParam(':user_id', $userId);
        $contactStmt->execute();
        $contato = $contactStmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'status' => true,
            'data' => [
                'usuario' => $userData,
                'endereco' => $endereco,
                'contato' => $contato
            ]
        ];
        
    } catch (Exception $e) {
        error_log('Erro ao verificar status do perfil: ' . $e->getMessage());
        return ['status' => false, 'message' => 'Erro ao verificar perfil'];
    }
} 
    
    /**
     * Verifica e cria as tabelas necessárias se não existirem
     * 
     * @param PDO $db Conexão com o banco de dados
     * @return void
     */
    private static function ensureTablesExist($db) {
        try {
            // Verificar e criar tabela de endereço
            $stmt = $db->query("SHOW TABLES LIKE 'usuarios_endereco'");
            if ($stmt->rowCount() == 0) {
                error_log('Criando tabela usuarios_endereco');
                $createTable = "CREATE TABLE usuarios_endereco (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    usuario_id INT NOT NULL,
                    cep VARCHAR(10) DEFAULT NULL,
                    logradouro VARCHAR(255) DEFAULT NULL,
                    numero VARCHAR(20) DEFAULT NULL,
                    complemento VARCHAR(100) DEFAULT NULL,
                    bairro VARCHAR(100) DEFAULT NULL,
                    cidade VARCHAR(100) DEFAULT NULL,
                    estado VARCHAR(50) DEFAULT NULL,
                    principal TINYINT(1) DEFAULT 1,
                    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
                )";
                
                $db->exec($createTable);
            }
            
            // Verificar e criar tabela de contato
            $stmt = $db->query("SHOW TABLES LIKE 'usuarios_contato'");
            if ($stmt->rowCount() == 0) {
                error_log('Criando tabela usuarios_contato');
                $createTable = "CREATE TABLE usuarios_contato (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    usuario_id INT NOT NULL,
                    telefone VARCHAR(20) DEFAULT NULL,
                    celular VARCHAR(20) DEFAULT NULL,
                    email_alternativo VARCHAR(100) DEFAULT NULL,
                    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
                )";
                
                $db->exec($createTable);
            }
        } catch (PDOException $e) {
            error_log('Erro ao verificar/criar tabelas: ' . $e->getMessage());
            // Não é necessário relançar a exceção, apenas registrar o erro
        }
    }
    /**
     * Valida se o cliente existe e está ativo
     * 
     * @param int $userId ID do cliente
     * @return bool Verdadeiro se o cliente é válido, falso caso contrário
     */
    private static function isProfileIncomplete($userId) {
        try {
            $db = Database::getConnection();
            
            // Verificar se o usuário tem dados de contato
            $contactStmt = $db->prepare("
                SELECT id FROM usuarios_contato
                WHERE usuario_id = :user_id
                LIMIT 1
            ");
            $contactStmt->bindParam(':user_id', $userId);
            $contactStmt->execute();
            $hasContact = $contactStmt->rowCount() > 0;
            
            // Verificar se o usuário tem dados de endereço
            $addressStmt = $db->prepare("
                SELECT id FROM usuarios_endereco
                WHERE usuario_id = :user_id
                LIMIT 1
            ");
            $addressStmt->bindParam(':user_id', $userId);
            $addressStmt->execute();
            $hasAddress = $addressStmt->rowCount() > 0;
            
            // Verificar se o usuário tem número de telefone no cadastro principal
            $phoneStmt = $db->prepare("
                SELECT telefone FROM usuarios
                WHERE id = :user_id AND (telefone IS NOT NULL AND telefone != '')
            ");
            $phoneStmt->bindParam(':user_id', $userId);
            $phoneStmt->execute();
            $hasPhone = $phoneStmt->rowCount() > 0;
            
            // Perfil está incompleto se faltar um desses dados
            return !($hasContact && $hasAddress && $hasPhone);
            
        } catch (PDOException $e) {
            error_log('Erro ao verificar perfil incompleto: ' . $e->getMessage());
            return false; // Em caso de erro, assumimos que não está incompleto para não enviar notificações desnecessárias
        }
    }
    /**
    * Verifica se já existe uma notificação recente sobre completar o perfil
    * 
    * @param int $userId ID do usuário
    * @param int $dias Número de dias para considerar uma notificação recente
    * @return bool true se existe uma notificação recente
    */
    private static function hasRecentProfileNotification($userId, $dias = 7) {
        try {
            $db = Database::getConnection();
            
            // Criar tabela de notificações se não existir
            self::createNotificationsTableIfNotExists($db);
            
            // Calcular data limite para notificações recentes
            $dataLimite = date('Y-m-d H:i:s', strtotime("-$dias days"));
            
            // Verificar se existe notificação recente sobre preenchimento de perfil
            $stmt = $db->prepare("
                SELECT id FROM notificacoes
                WHERE usuario_id = :user_id 
                AND (titulo LIKE '%perfil%' OR mensagem LIKE '%perfil%')
                AND data_criacao >= :data_limite
                LIMIT 1
            ");
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':data_limite', $dataLimite);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
            
        } catch (PDOException $e) {
            error_log('Erro ao verificar notificações recentes: ' . $e->getMessage());
            return true; // Em caso de erro, assumimos que já existe para evitar enviar duplicadas
        }
    }
    /**
     * Registra uma nova transação de cashback
     * 
     * @param array $data Dados da transação
     * @return array Resultado da operação
     */
    public static function registerTransaction($data) {
        try {
            // Validar dados obrigatórios
            $requiredFields = ['usuario_id', 'loja_id', 'valor_total', 'codigo_transacao'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return ['status' => false, 'message' => 'Dados da transação incompletos. Campo faltante: ' . $field];
                }
            }
            
            // Verificar se é um cliente válido
            if (!self::validateClient($data['usuario_id'])) {
                return ['status' => false, 'message' => 'Cliente não encontrado ou inativo.'];
            }
            
            $db = Database::getConnection();
            
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
            // CORREÇÃO: Loja sempre recebe 0%
            $porcentagemLoja = 0.00;
            // Verificar se a loja tem porcentagem específica
            if (isset($store['porcentagem_cashback']) && $store['porcentagem_cashback'] > 0) {
                $porcentagemTotal = $store['porcentagem_cashback'];
                // Ajustar proporcionalmente mas loja continua em 0%
                $fator = $porcentagemTotal / DEFAULT_CASHBACK_TOTAL;
                $porcentagemCliente = DEFAULT_CASHBACK_CLIENT * $fator;
                $porcentagemAdmin = DEFAULT_CASHBACK_ADMIN * $fator;
                $porcentagemLoja = 0.00; // Loja sempre 0%
            }
            
            // Calcular valores
            $valorCashbackTotal = ($data['valor_total'] * $porcentagemTotal) / 100;
            $valorCashbackCliente = ($data['valor_total'] * $porcentagemCliente) / 100;
            $valorCashbackAdmin = ($data['valor_total'] * $porcentagemAdmin) / 100;
            $valorCashbackLoja = 0.00; // CORREÇÃO: Loja não recebe nada
            
            // Iniciar transação
            $db->beginTransaction();
            
            // Registrar transação principal
            $stmt = $db->prepare("
                INSERT INTO transacoes_cashback (
                    usuario_id, loja_id, valor_total, valor_cashback,
                    codigo_transacao, data_transacao, status, descricao
                ) VALUES (
                    :usuario_id, :loja_id, :valor_total, :valor_cashback,
                    :codigo_transacao, NOW(), :status, :descricao
                )
            ");
            
            $stmt->bindParam(':usuario_id', $data['usuario_id']);
            $stmt->bindParam(':loja_id', $data['loja_id']);
            $stmt->bindParam(':valor_total', $data['valor_total']);
            $stmt->bindParam(':valor_cashback', $valorCashbackCliente);
            $stmt->bindParam(':codigo_transacao', $data['codigo_transacao']);
            $status = TRANSACTION_APPROVED; // Ou TRANSACTION_PENDING dependendo da lógica de negócio
            $stmt->bindParam(':status', $status);
            $descricao = $data['descricao'] ?? 'Compra na ' . $store['nome_fantasia'];
            $stmt->bindParam(':descricao', $descricao);
            $stmt->execute();
            
            $transactionId = $db->lastInsertId();

            // === INTEGRAÇÃO AUTOMÁTICA: Sistema de Notificação Corrigido ===
            try {
                error_log("[FIXED] ClientController - Disparando notificação para transação {$transactionId} criada via interface do cliente");

                require_once __DIR__ . '/../classes/FixedBrutalNotificationSystem.php';
                $notificationSystem = new FixedBrutalNotificationSystem();
                $result = $notificationSystem->forceNotifyTransaction($transactionId);

                if ($result['success']) {
                    error_log("[FIXED] ClientController - Notificação enviada com sucesso: " . $result['message']);
                } else {
                    error_log("[FIXED] ClientController - Falha na notificação: " . $result['message']);
                }

            } catch (Exception $e) {
                error_log("[FIXED] ClientController - Erro na notificação para transação {$transactionId}: " . $e->getMessage());
            }
            // Registrar transação para o administrador (comissão admin)
            if ($valorCashbackAdmin > 0) {
                $adminStmt = $db->prepare("
                    INSERT INTO transacoes_comissao (
                        tipo_usuario, usuario_id, loja_id, transacao_id,
                        valor_total, valor_comissao, data_transacao, status
                    ) VALUES (
                        :tipo_usuario, :usuario_id, :loja_id, :transacao_id,
                        :valor_total, :valor_comissao, NOW(), :status
                    )
                ");
                
                $tipoAdmin = USER_TYPE_ADMIN;
                $adminStmt->bindParam(':tipo_usuario', $tipoAdmin);
                $adminId = 1; // Administrador padrão
                $adminStmt->bindParam(':usuario_id', $adminId);
                $adminStmt->bindParam(':loja_id', $data['loja_id']);
                $adminStmt->bindParam(':transacao_id', $transactionId);
                $adminStmt->bindParam(':valor_total', $data['valor_total']);
                $adminStmt->bindParam(':valor_comissao', $valorCashbackAdmin);
                $adminStmt->bindParam(':status', $status);
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
                        :valor_total, :valor_comissao, NOW(), :status
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
                $storeStmt->bindParam(':status', $status);
                $storeStmt->execute();
            }
            
            // Enviar notificação ao cliente
            self::notifyClient($data['usuario_id'], 'Nova transação de cashback', 'Você recebeu R$ ' . number_format($valorCashbackCliente, 2, ',', '.') . ' de cashback na loja ' . $store['nome_fantasia']);
            
            // Enviar email de confirmação ao cliente
            $userStmt = $db->prepare("SELECT nome, email FROM usuarios WHERE id = :user_id");
            $userStmt->bindParam(':user_id', $data['usuario_id']);
            $userStmt->execute();
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $transactionData = [
                    'nome_loja' => $store['nome_fantasia'],
                    'valor_total' => $data['valor_total'],
                    'valor_cashback' => $valorCashbackCliente,
                    'data_transacao' => date('Y-m-d H:i:s')
                ];
                
                Email::sendTransactionNotification($user['email'], $user['nome'], $transactionData);
            }
            
            // Confirmar transação
            $db->commit();

            // Logar transação como JSON para usuários MVP
            self::logTransactionAsJson($transactionId, $data, $store, $valorCashbackCliente);
            
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

    private static function logTransactionAsJson($transactionId, $data, $store, $valorCashbackCliente) {
        try {
            $db = Database::getConnection();
            $userStmt = $db->prepare("SELECT * FROM usuarios WHERE id = :user_id");
            $userStmt->bindParam(':user_id', $data['usuario_id']);
            $userStmt->execute();
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);

            $logData = [
                'transaction_id' => $transactionId,
                'transaction_data' => $data,
                'user_data' => $user,
                'store_data' => $store,
                'cashback_amount' => $valorCashbackCliente,
                'timestamp' => date('Y-m-d H:i:s')
            ];

            $jsonLogData = json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $logFileName = 'transaction_' . $transactionId . '.json';
            $logFilePath = __DIR__ . '/../transaction_json_logs/' . $logFileName;

            file_put_contents($logFilePath, $jsonLogData);
        } catch (Exception $e) {
            error_log('Erro ao logar transação como JSON: ' . $e->getMessage());
        }
    }

    
    /**
     * Obtém detalhes de uma transação específica
     * 
     * @param int $userId ID do cliente
     * @param int $transactionId ID da transação
     * @return array Dados da transação
     */
    public static function getTransactionDetails($userId, $transactionId) {
        try {
            // Verificar se é um cliente válido
            if (!self::validateClient($userId)) {
                return ['status' => false, 'message' => 'Cliente não encontrado ou inativo.'];
            }
            
            $db = Database::getConnection();
            
            // Obter detalhes da transação
            $stmt = $db->prepare("
                SELECT t.*, l.nome_fantasia as loja_nome, l.logo as loja_logo, l.categoria as loja_categoria
                FROM transacoes_cashback t
                JOIN lojas l ON t.loja_id = l.id
                WHERE t.id = :transaction_id AND t.usuario_id = :user_id
            ");
            $stmt->bindParam(':transaction_id', $transactionId);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$transaction) {
                return ['status' => false, 'message' => 'Transação não encontrada ou não pertence a este usuário.'];
            }
            
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
                    'historico_status' => $statusHistory
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao obter detalhes da transação: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao carregar detalhes da transação. Tente novamente.'];
        }
    }
    
    /**
     * Gera relatório de cashback para o cliente
     * 
     * @param int $userId ID do cliente
     * @param array $filters Filtros para o relatório
     * @return array Dados do relatório
     */
    public static function generateCashbackReport($userId, $filters = []) {
        try {
            // Verificar se é um cliente válido
            if (!self::validateClient($userId)) {
                return ['status' => false, 'message' => 'Cliente não encontrado ou inativo.'];
            }
            
            $db = Database::getConnection();
            
            // Preparar condições da consulta
            $conditions = "WHERE t.usuario_id = :user_id";
            $params = [':user_id' => $userId];
            
            // Aplicar filtros de data
            if (isset($filters['data_inicio']) && !empty($filters['data_inicio'])) {
                $conditions .= " AND t.data_transacao >= :data_inicio";
                $params[':data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
            }
            
            if (isset($filters['data_fim']) && !empty($filters['data_fim'])) {
                $conditions .= " AND t.data_transacao <= :data_fim";
                $params[':data_fim'] = $filters['data_fim'] . ' 23:59:59';
            }
            
            // Estatísticas gerais
            $statsQuery = "
                SELECT 
                    COUNT(*) as total_transacoes,
                    SUM(valor_total) as total_compras,
                    SUM(valor_cashback) as total_cashback,
                    AVG(valor_cashback) as media_cashback
                FROM transacoes_cashback t
                $conditions
                AND t.status = :status
            ";
            
            $statsStmt = $db->prepare($statsQuery);
            foreach ($params as $param => $value) {
                $statsStmt->bindValue($param, $value);
            }
            $status = TRANSACTION_APPROVED;
            $statsStmt->bindValue(':status', $status);
            $statsStmt->execute();
            $statistics = $statsStmt->fetch(PDO::FETCH_ASSOC);
            
            // Cashback por loja
            $storesQuery = "
                SELECT 
                    l.id, l.nome_fantasia, l.categoria,
                    COUNT(t.id) as total_transacoes,
                    SUM(t.valor_total) as total_compras,
                    SUM(t.valor_cashback) as total_cashback
                FROM transacoes_cashback t
                JOIN lojas l ON t.loja_id = l.id
                $conditions
                AND t.status = :status
                GROUP BY l.id
                ORDER BY total_cashback DESC
            ";
            
            $storesStmt = $db->prepare($storesQuery);
            foreach ($params as $param => $value) {
                $storesStmt->bindValue($param, $value);
            }
            $storesStmt->bindValue(':status', $status);
            $storesStmt->execute();
            $storesData = $storesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Cashback por mês
            $monthlyQuery = "
                SELECT 
                    DATE_FORMAT(t.data_transacao, '%Y-%m') as mes,
                    COUNT(t.id) as total_transacoes,
                    SUM(t.valor_total) as total_compras,
                    SUM(t.valor_cashback) as total_cashback
                FROM transacoes_cashback t
                $conditions
                AND t.status = :status
                GROUP BY DATE_FORMAT(t.data_transacao, '%Y-%m')
                ORDER BY mes DESC
            ";
            
            $monthlyStmt = $db->prepare($monthlyQuery);
            foreach ($params as $param => $value) {
                $monthlyStmt->bindValue($param, $value);
            }
            $monthlyStmt->bindValue(':status', $status);
            $monthlyStmt->execute();
            $monthlyData = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Cashback por categoria
            $categoryQuery = "
                SELECT 
                    l.categoria,
                    COUNT(t.id) as total_transacoes,
                    SUM(t.valor_total) as total_compras,
                    SUM(t.valor_cashback) as total_cashback
                FROM transacoes_cashback t
                JOIN lojas l ON t.loja_id = l.id
                $conditions
                AND t.status = :status
                GROUP BY l.categoria
                ORDER BY total_cashback DESC
            ";
            
            $categoryStmt = $db->prepare($categoryQuery);
            foreach ($params as $param => $value) {
                $categoryStmt->bindValue($param, $value);
            }
            $categoryStmt->bindValue(':status', $status);
            $categoryStmt->execute();
            $categoryData = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'status' => true,
                'data' => [
                    'estatisticas' => $statistics,
                    'por_loja' => $storesData,
                    'por_mes' => $monthlyData,
                    'por_categoria' => $categoryData
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao gerar relatório: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao gerar relatório. Tente novamente.'];
        }
    }
    /**
    * Obtém o saldo completo do cliente com detalhes por loja
    * 
    * @param int $userId ID do cliente
    * @return array Resultado da operação
    */
    public static function getClientBalanceDetails($userId) {
        try {
            if (!self::validateClient($userId)) {
                return ['status' => false, 'message' => 'Cliente não encontrado ou inativo.'];
            }
            
            require_once __DIR__ . '/../models/CashbackBalance.php';
            $balanceModel = new CashbackBalance();
            
            $balances = $balanceModel->getAllUserBalances($userId);
            $totalBalance = $balanceModel->getTotalBalance($userId);
            
            // Enriquecer dados com estatísticas
            foreach ($balances as &$balance) {
                $stats = $balanceModel->getBalanceStatistics($userId, $balance['loja_id']);
                $balance['estatisticas'] = $stats;
            }
            
            return [
                'status' => true,
                'data' => [
                    'saldo_total' => $totalBalance,
                    'saldos_por_loja' => $balances,
                    'total_lojas' => count($balances)
                ]
            ];
            
        } catch (Exception $e) {
            error_log('Erro ao obter detalhes do saldo: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao carregar saldo.'];
        }
    }

    /**
    * Usa saldo do cliente em uma loja específica
    * 
    * @param int $userId ID do cliente
    * @param int $storeId ID da loja
    * @param float $amount Valor a ser usado
    * @param string $description Descrição do uso
    * @return array Resultado da operação
    */
    public static function useClientBalance($userId, $storeId, $amount, $description = '', $transactionId = null) {
        try {
            if (!self::validateClient($userId)) {
                return ['status' => false, 'message' => 'Cliente não encontrado ou inativo.'];
            }
            
            require_once __DIR__ . '/../models/CashbackBalance.php';
            $balanceModel = new CashbackBalance();
            
            $currentBalance = $balanceModel->getStoreBalance($userId, $storeId);
            
            if ($currentBalance < $amount) {
                return [
                    'status' => false, 
                    'message' => 'Saldo insuficiente. Disponível: R$ ' . number_format($currentBalance, 2, ',', '.')
                ];
            }
            
            // Adicionar ID da transação na descrição se não fornecida
            if (empty($description) && $transactionId) {
                $description = "Uso do saldo - Transação #" . $transactionId;
            } elseif (empty($description)) {
                $description = "Uso do saldo de cashback";
            }
            
            if ($balanceModel->useBalance($userId, $storeId, $amount, $description, $transactionId)) {
                $newBalance = $balanceModel->getStoreBalance($userId, $storeId);
                
                return [
                    'status' => true,
                    'message' => 'Saldo usado com sucesso!',
                    'data' => [
                        'valor_usado' => $amount,
                        'saldo_anterior' => $currentBalance,
                        'saldo_atual' => $newBalance,
                        'transacao_id' => $transactionId
                    ]
                ];
            } else {
                return ['status' => false, 'message' => 'Erro ao usar saldo. Tente novamente.'];
            }
            
        } catch (Exception $e) {
            error_log('Erro ao usar saldo: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao processar uso do saldo.'];
        }
    }

    /**
    * Obtém o histórico de movimentações do saldo de uma loja
    * 
    * @param int $userId ID do cliente
    * @param int $storeId ID da loja
    * @param int $page Página atual
    * @param int $limit Itens por página
    * @return array Resultado da operação
    */
    public static function getBalanceHistory($userId, $storeId, $page = 1, $limit = 20) {
        try {
            if (!self::validateClient($userId)) {
                return ['status' => false, 'message' => 'Cliente não encontrado ou inativo.'];
            }
            
            require_once __DIR__ . '/../models/CashbackBalance.php';
            $balanceModel = new CashbackBalance();
            
            $offset = ($page - 1) * $limit;
            $history = $balanceModel->getMovementHistory($userId, $storeId, $limit, $offset);
            
            return [
                'status' => true,
                'data' => [
                    'movimentacoes' => $history,
                    'pagina_atual' => $page,
                    'itens_por_pagina' => $limit
                ]
            ];
            
        } catch (Exception $e) {
            error_log('Erro ao obter histórico: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao carregar histórico.'];
        }
    }




    /**
     * Marca uma loja como favorita
     * 
     * @param int $userId ID do cliente
     * @param int $storeId ID da loja
     * @param bool $favorite true para favoritar, false para desfavoritar
     * @return array Resultado da operação
     */
    public static function toggleFavoriteStore($userId, $storeId, $favorite = true) {
        try {
            // Verificar se é um cliente válido
            if (!self::validateClient($userId)) {
                return ['status' => false, 'message' => 'Cliente não encontrado ou inativo.'];
            }
            
            $db = Database::getConnection();
            
            // Verificar se a loja existe e está aprovada
            $storeStmt = $db->prepare("SELECT id FROM lojas WHERE id = :store_id AND status = :status");
            $storeStmt->bindParam(':store_id', $storeId);
            $status = STORE_APPROVED;
            $storeStmt->bindParam(':status', $status);
            $storeStmt->execute();
            
            if ($storeStmt->rowCount() == 0) {
                return ['status' => false, 'message' => 'Loja não encontrada ou não aprovada.'];
            }
            
            // Verificar se a tabela de favoritos existe, se não, criar
            self::createFavoritesTableIfNotExists($db);
            
            // Verificar se já está favoritada
            $checkStmt = $db->prepare("
                SELECT id FROM lojas_favoritas
                WHERE usuario_id = :user_id AND loja_id = :store_id
            ");
            $checkStmt->bindParam(':user_id', $userId);
            $checkStmt->bindParam(':store_id', $storeId);
            $checkStmt->execute();
            $isFavorite = $checkStmt->rowCount() > 0;
            
            if ($favorite && !$isFavorite) {
                // Adicionar aos favoritos
                $addStmt = $db->prepare("
                    INSERT INTO lojas_favoritas (usuario_id, loja_id, data_criacao)
                    VALUES (:user_id, :store_id, NOW())
                ");
                $addStmt->bindParam(':user_id', $userId);
                $addStmt->bindParam(':store_id', $storeId);
                $addStmt->execute();
                
                return ['status' => true, 'message' => 'Loja adicionada aos favoritos.'];
            } else if (!$favorite && $isFavorite) {
                // Remover dos favoritos
                $removeStmt = $db->prepare("
                    DELETE FROM lojas_favoritas
                    WHERE usuario_id = :user_id AND loja_id = :store_id
                ");
                $removeStmt->bindParam(':user_id', $userId);
                $removeStmt->bindParam(':store_id', $storeId);
                $removeStmt->execute();
                
                return ['status' => true, 'message' => 'Loja removida dos favoritos.'];
            }
            
            return ['status' => true, 'message' => 'Nenhuma alteração necessária.'];
            
        } catch (PDOException $e) {
            error_log('Erro ao atualizar favoritos: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao atualizar favoritos. Tente novamente.'];
        }
    }
    
    /**
     * Obtém as lojas favoritas do cliente
     * 
     * @param int $userId ID do cliente
     * @return array Lista de lojas favoritas
     */
    public static function getFavoriteStores($userId) {
        try {
            // Verificar se é um cliente válido
            if (!self::validateClient($userId)) {
                return ['status' => false, 'message' => 'Cliente não encontrado ou inativo.'];
            }
            
            $db = Database::getConnection();
            
            // Verificar se a tabela de favoritos existe
            self::createFavoritesTableIfNotExists($db);
            
            // Obter lojas favoritas
            $stmt = $db->prepare("
                SELECT l.*, f.data_criacao as data_favoritado
                FROM lojas_favoritas f
                JOIN lojas l ON f.loja_id = l.id
                WHERE f.usuario_id = :user_id AND l.status = :status
                ORDER BY f.data_criacao DESC
            ");
            $stmt->bindParam(':user_id', $userId);
            $status = STORE_APPROVED;
            $stmt->bindParam(':status', $status);
            $stmt->execute();
            $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'status' => true,
                'data' => $favorites
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao obter lojas favoritas: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao carregar lojas favoritas. Tente novamente.'];
        }
    }
    
    /**
     * Valida se o usuário é um cliente ativo
     * 
     * @param int $userId ID do usuário
     * @return bool true se for cliente ativo, false caso contrário
     */
    private static function validateClient($userId) {
        try {
            $db = Database::getConnection();
            
            $stmt = $db->prepare("
                SELECT id FROM usuarios
                WHERE id = :user_id AND tipo = :tipo AND status = :status
            ");
            $stmt->bindParam(':user_id', $userId);
            $tipo = USER_TYPE_CLIENT;
            $stmt->bindParam(':tipo', $tipo);
            $status = USER_ACTIVE;
            $stmt->bindParam(':status', $status);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('Erro ao validar cliente: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtém notificações para o cliente
     * 
     * @param int $userId ID do cliente
     * @param int $limit Limite de notificações
     * @return array Lista de notificações
     */
    private static function getClientNotifications($userId, $limit = 5, $onlyUnread = true) {
        try {
            $db = Database::getConnection();
            
            // Verificar se a tabela de notificações existe
            self::createNotificationsTableIfNotExists($db);
            
            // Obter notificações
            $query = "
                SELECT *
                FROM notificacoes
                WHERE usuario_id = :user_id
            ";
            
            if ($onlyUnread) {
                $query .= " AND lida = 0";
            }
            
            $query .= " ORDER BY data_criacao DESC LIMIT :limit";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Erro ao obter notificações: ' . $e->getMessage());
            return [];
        }
    }
    /**
    * Obtém o saldo de cashback do cliente por loja
    * 
    * @param int $userId ID do cliente
    * @return array Saldos por loja e total
    */
    public static function getClientBalance($userId) {
        try {
            // Verificar se é um cliente válido
            if (!self::validateClient($userId)) {
                return ['status' => false, 'message' => 'Cliente não encontrado ou inativo.'];
            }
            
            $db = Database::getConnection();
            
            // Obter saldo por loja - apenas transações aprovadas
            $balanceQuery = "
                SELECT 
                    l.id as loja_id,
                    l.nome_fantasia,
                    l.logo,
                    l.categoria,
                    l.porcentagem_cashback,
                    SUM(t.valor_cashback) as saldo_disponivel,
                    COUNT(t.id) as total_transacoes,
                    MAX(t.data_transacao) as ultima_transacao,
                    SUM(t.valor_total) as total_compras
                FROM transacoes_cashback t
                JOIN lojas l ON t.loja_id = l.id
                WHERE t.usuario_id = :user_id 
                AND t.status = :status
                GROUP BY l.id, l.nome_fantasia, l.logo, l.categoria, l.porcentagem_cashback
                HAVING saldo_disponivel > 0
                ORDER BY saldo_disponivel DESC
            ";
            
            $balanceStmt = $db->prepare($balanceQuery);
            $balanceStmt->bindParam(':user_id', $userId);
            $status = TRANSACTION_APPROVED;
            $balanceStmt->bindParam(':status', $status);
            $balanceStmt->execute();
            $saldosPorLoja = $balanceStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular saldo total
            $saldoTotal = 0;
            $totalTransacoes = 0;
            $totalCompras = 0;
            
            foreach ($saldosPorLoja as $loja) {
                $saldoTotal += $loja['saldo_disponivel'];
                $totalTransacoes += $loja['total_transacoes'];
                $totalCompras += $loja['total_compras'];
            }
            
            // Obter estatísticas gerais
            $estatisticasQuery = "
                SELECT 
                    COUNT(DISTINCT loja_id) as total_lojas_utilizadas,
                    COUNT(*) as total_transacoes_historico,
                    SUM(valor_total) as total_compras_historico,
                    SUM(valor_cashback) as total_cashback_historico,
                    AVG(valor_cashback) as media_cashback
                FROM transacoes_cashback
                WHERE usuario_id = :user_id
                AND status = :status
            ";
            
            $estatisticasStmt = $db->prepare($estatisticasQuery);
            $estatisticasStmt->bindParam(':user_id', $userId);
            $estatisticasStmt->bindParam(':status', $status);
            $estatisticasStmt->execute();
            $estatisticas = $estatisticasStmt->fetch(PDO::FETCH_ASSOC);
            
            // Obter saldo pendente (aguardando aprovação de pagamento)
            $saldoPendenteQuery = "
                SELECT 
                    l.nome_fantasia,
                    SUM(t.valor_cashback) as saldo_pendente
                FROM transacoes_cashback t
                JOIN lojas l ON t.loja_id = l.id
                WHERE t.usuario_id = :user_id 
                AND t.status = :status_pendente
                GROUP BY l.id, l.nome_fantasia
                HAVING saldo_pendente > 0
                ORDER BY saldo_pendente DESC
            ";
            
            $saldoPendenteStmt = $db->prepare($saldoPendenteQuery);
            $saldoPendenteStmt->bindParam(':user_id', $userId);
            $statusPendente = TRANSACTION_PENDING;
            $saldoPendenteStmt->bindParam(':status_pendente', $statusPendente);
            $saldoPendenteStmt->execute();
            $saldosPendentes = $saldoPendenteStmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'status' => true,
                'data' => [
                    'saldo_total' => $saldoTotal,
                    'saldos_por_loja' => $saldosPorLoja,
                    'saldos_pendentes' => $saldosPendentes,
                    'estatisticas' => [
                        'total_lojas_com_saldo' => count($saldosPorLoja),
                        'total_transacoes' => $totalTransacoes,
                        'total_compras' => $totalCompras,
                        'total_lojas_utilizadas' => $estatisticas['total_lojas_utilizadas'],
                        'total_transacoes_historico' => $estatisticas['total_transacoes_historico'],
                        'total_compras_historico' => $estatisticas['total_compras_historico'],
                        'total_cashback_historico' => $estatisticas['total_cashback_historico'],
                        'media_cashback' => $estatisticas['media_cashback']
                    ]
                ]
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao obter saldo do cliente: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Erro ao carregar saldo. Tente novamente.'];
        }
    }
    /**
    * Envia uma notificação para o cliente
    * 
    * @param int $userId ID do cliente
    * @param string $titulo Título da notificação
    * @param string $mensagem Mensagem da notificação
    * @param string $tipo Tipo da notificação (info, success, warning, error)
    * @param string $link Link associado à notificação (opcional)
    * @return bool Resultado da operação
    */
    private static function notifyClient($userId, $titulo, $mensagem, $tipo = 'info', $link = '') {
        try {
            $db = Database::getConnection();
            
            // Verificar se a tabela de notificações existe
            self::createNotificationsTableIfNotExists($db);
            
            // Inserir notificação
            $stmt = $db->prepare("
                INSERT INTO notificacoes (usuario_id, titulo, mensagem, tipo, link, data_criacao, lida)
                VALUES (:user_id, :titulo, :mensagem, :tipo, :link, NOW(), 0)
            ");
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':titulo', $titulo);
            $stmt->bindParam(':mensagem', $mensagem);
            $stmt->bindParam(':tipo', $tipo);
            $stmt->bindParam(':link', $link);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Erro ao enviar notificação: ' . $e->getMessage());
            return false;
        }
    }
    /**
    * Gera HTML editável do extrato de cashback
    */
    private static function generateStatementHTML($userData, $statementData, $filters) {
    // Definir período do relatório
    $periodo = '';
    if (!empty($filters['data_inicio']) && !empty($filters['data_fim'])) {
        $periodo = "Período: " . date('d/m/Y', strtotime($filters['data_inicio'])) . 
                    " a " . date('d/m/Y', strtotime($filters['data_fim']));
    } elseif (!empty($filters['data_inicio'])) {
        $periodo = "A partir de: " . date('d/m/Y', strtotime($filters['data_inicio']));
    } elseif (!empty($filters['data_fim'])) {
        $periodo = "Até: " . date('d/m/Y', strtotime($filters['data_fim']));
    } else {
        $periodo = "Todas as transações";
    }

    // Calcular totais
    $totalCompras = $statementData['estatisticas']['total_compras'] ?? 0;
    $totalCashback = $statementData['estatisticas']['total_cashback'] ?? 0;
    $totalTransacoes = $statementData['estatisticas']['total_transacoes'] ?? 0;

    // Definir cabeçalhos HTTP para download
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="extrato_cashback_' . date('Y-m-d') . '.html"');

    // Gerar HTML
    echo '<!DOCTYPE html>
    <html lang="pt-BR">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Extrato de Cashback - Klube Cash</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #fff;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #FF7A00;
            padding-bottom: 20px;
        }
        
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #FF7A00;
            margin-bottom: 10px;
        }
        
        .client-info {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .period-info {
            text-align: center;
            font-size: 16px;
            margin-bottom: 20px;
            color: #666;
        }
        
        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background-color: #FFF0E6;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #FF7A00;
        }
        
        .summary-title {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .summary-value {
            font-size: 20px;
            font-weight: bold;
            color: #FF7A00;
        }
        
        .transactions-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .transactions-table th,
        .transactions-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .transactions-table th {
            background-color: #f2f2f2;
            font-weight: bold;
            color: #333;
        }
        
        .transactions-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-aprovado {
            background-color: #E6F7E6;
            color: #4CAF50;
        }
        
        .status-pendente {
            background-color: #FFF8E6;
            color: #FFC107;
        }
        
        .status-cancelado {
            background-color: #FFEAE6;
            color: #F44336;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            color: #666;
            font-size: 12px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        
        .no-print {
            margin-bottom: 20px;
        }
        
        .edit-notice {
            background-color: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #2196f3;
        }
        
        @media print {
            .no-print,
            .edit-notice {
                display: none;
            }
            
            body {
                padding: 0;
            }
        }
        
        @media (max-width: 768px) {
            .summary {
                grid-template-columns: 1fr;
            }
            
            .transactions-table {
                font-size: 12px;
            }
            
            .transactions-table th,
            .transactions-table td {
                padding: 8px 4px;
            }
        }
    </style>
    </head>
    <body>
    <div class="edit-notice no-print">
        <strong>📝 Aviso:</strong> Este é um arquivo HTML editável. Você pode modificar o conteúdo, estilo e layout conforme necessário. Para imprimir, use Ctrl+P ou Cmd+P.
    </div>

    <div class="header">
        <div class="logo">KLUBE CASH</div>
        <h1>Extrato de Cashback</h1>
        <div class="period-info">' . $periodo . '</div>
        <div class="period-info">Gerado em: ' . date('d/m/Y H:i:s') . '</div>
    </div>

    <div class="client-info">
        <h3>Dados do Cliente</h3>
        <p><strong>Nome:</strong> ' . htmlspecialchars($userData['nome']) . '</p>
        <p><strong>Email:</strong> ' . htmlspecialchars($userData['email']) . '</p>
    </div>

    <div class="summary">
        <div class="summary-card">
            <div class="summary-title">Total de Compras</div>
            <div class="summary-value">R$ ' . number_format($totalCompras, 2, ',', '.') . '</div>
        </div>
        
        <div class="summary-card">
            <div class="summary-title">Total de Cashback</div>
            <div class="summary-value">R$ ' . number_format($totalCashback, 2, ',', '.') . '</div>
        </div>
        
        <div class="summary-card">
            <div class="summary-title">Total de Transações</div>
            <div class="summary-value">' . $totalTransacoes . '</div>
        </div>
    </div>

    <h2>Detalhamento das Transações</h2>

    <table class="transactions-table">
        <thead>
            <tr>
                <th>Data</th>
                <th>Loja</th>
                <th>Valor Total</th>
                <th>Cashback</th>
                <th>Status</th>
                <th>Código</th>
            </tr>
        </thead>
        <tbody>';

    // Adicionar linhas das transações
    if (!empty($statementData['transacoes'])) {
        foreach ($statementData['transacoes'] as $transacao) {
            $statusClass = '';
            switch ($transacao['status']) {
                case 'aprovado':
                    $statusClass = 'status-aprovado';
                    break;
                case 'pendente':
                    $statusClass = 'status-pendente';
                    break;
                case 'cancelado':
                    $statusClass = 'status-cancelado';
                    break;
            }
            
            echo '<tr>
                <td>' . date('d/m/Y', strtotime($transacao['data_transacao'])) . '</td>
                <td>' . htmlspecialchars($transacao['loja_nome']) . '</td>
                <td>R$ ' . number_format($transacao['valor_total'], 2, ',', '.') . '</td>
                <td>R$ ' . number_format($transacao['valor_cliente'], 2, ',', '.') . '</td>
                <td><span class="status-badge ' . $statusClass . '">' . ucfirst($transacao['status']) . '</span></td>
                <td>' . htmlspecialchars($transacao['codigo_transacao']) . '</td>
            </tr>';
        }
    } else {
        echo '<tr>
            <td colspan="6" style="text-align: center; padding: 30px; color: #666;">
                Nenhuma transação encontrada para o período selecionado.
            </td>
        </tr>';
    }

    echo '</tbody>
    </table>

    <div class="footer">
        <p>Este documento foi gerado automaticamente pelo sistema Klube Cash</p>
        <p>© ' . date('Y') . ' Klube Cash - Sistema de Cashback</p>
        <p style="margin-top: 10px; font-size: 10px;">
            Para dúvidas ou suporte, entre em contato conosco através do email: contato@klubecash.com
        </p>
    </div>

    <script>
        // Script para permitir edição inline
        document.addEventListener("DOMContentLoaded", function() {
            // Tornar elementos editáveis ao clicar duas vezes
            const editableElements = document.querySelectorAll("h1, h2, h3, p, td, th");
            
            editableElements.forEach(element => {
                element.addEventListener("dblclick", function() {
                    if (this.contentEditable === "true") {
                        this.contentEditable = "false";
                        this.style.backgroundColor = "";
                        this.style.border = "";
                    } else {
                        this.contentEditable = "true";
                        this.style.backgroundColor = "#fff3cd";
                        this.style.border = "1px dashed #856404";
                        this.focus();
                    }
                });
            });
            
            // Adicionar indicação visual de elementos editáveis
            const style = document.createElement("style");
            style.textContent = `
                .no-print::after {
                    content: " (Clique duas vezes em qualquer texto para editá-lo)";
                    font-style: italic;
                    color: #666;
                }
            `;
            document.head.appendChild(style);
        });
    </script>
    </body>
    </html>';
    }
        /**
         * Cria a tabela de favoritos se não existir
         * 
         * @param PDO $db Conexão com o banco de dados
         * @return void
         */
        private static function createFavoritesTableIfNotExists($db) {
            try {
                // Verificar se a tabela existe
                $stmt = $db->prepare("SHOW TABLES LIKE 'lojas_favoritas'");
                $stmt->execute();
                
                if ($stmt->rowCount() == 0) {
                    // Criar a tabela
                    $createTable = "CREATE TABLE lojas_favoritas (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        usuario_id INT NOT NULL,
                        loja_id INT NOT NULL,
                        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY unique_favorite (usuario_id, loja_id),
                        FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
                        FOREIGN KEY (loja_id) REFERENCES lojas(id)
                    )";
                    
                    $db->exec($createTable);
                }
            } catch (PDOException $e) {
                error_log('Erro ao criar tabela de favoritos: ' . $e->getMessage());
            }
        }
    
    /**
     * Cria a tabela de notificações se não existir
     * 
     * @param PDO $db Conexão com o banco de dados
     * @return void
     */
    private static function createNotificationsTableIfNotExists($db) {
        try {
            // Verificar se a tabela existe
            $stmt = $db->prepare("SHOW TABLES LIKE 'notificacoes'");
            $stmt->execute();
            
            if ($stmt->rowCount() == 0) {
                // Criar a tabela com a coluna 'link'
                $createTable = "CREATE TABLE notificacoes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    usuario_id INT NOT NULL,
                    titulo VARCHAR(100) NOT NULL,
                    mensagem TEXT NOT NULL,
                    tipo ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
                    link VARCHAR(255) DEFAULT '',
                    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    lida TINYINT(1) DEFAULT 0,
                    data_leitura TIMESTAMP NULL,
                    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
                )";
                
                $db->exec($createTable);
                error_log('Tabela notificacoes criada com sucesso');
            } else {
                // Verificar se a coluna 'link' existe
                $columnCheckStmt = $db->prepare("SHOW COLUMNS FROM notificacoes LIKE 'link'");
                $columnCheckStmt->execute();
                
                // Se a coluna não existir, adicionar
                if ($columnCheckStmt->rowCount() == 0) {
                    $db->exec("ALTER TABLE notificacoes ADD COLUMN link VARCHAR(255) DEFAULT ''");
                    error_log('Coluna link adicionada à tabela notificacoes');
                }
            }
        } catch (PDOException $e) {
            error_log('Erro ao criar/verificar tabela de notificações: ' . $e->getMessage());
        }
    }
}

// Processar requisições diretas de acesso ao controlador
if (basename($_SERVER['PHP_SELF']) === 'ClientController.php') {
    
    $isAuthenticated = false;

    // 1. Tenta autenticar via SESSÃO (método antigo)
    if (AuthController::isAuthenticated()) {
        $isAuthenticated = true;
    } 
    // 2. Se a sessão falhar, TENTA AUTENTICAR VIA TOKEN JWT (método novo)
    else {
        $token = $_COOKIE['jwt_token'] ?? '';
        if (!empty($token)) {
            $tokenData = Security::validateJWT($token);
            if ($tokenData) {
                // Se o token for válido, recria a sessão para esta requisição
                $_SESSION['user_id'] = $tokenData->id;
                $_SESSION['user_type'] = $tokenData->tipo;
                $isAuthenticated = true;
            }
        }
    }

    // 3. Se NENHUM dos métodos funcionar, redireciona para o login
    if (!$isAuthenticated) {
        header('Location: ' . LOGIN_URL . '?error=' . urlencode('Sessão inválida ou expirada.'));
        exit;
    }
    
    // 4. A partir daqui, o resto do código funciona, pois a sessão está garantida
    if (AuthController::isAdmin() || AuthController::isStore()) {
        header('Location: ' . LOGIN_URL . '?error=' . urlencode('Acesso restrito a clientes.'));
        exit;
    }
    
    $userId = AuthController::getCurrentUserId();
    $action = $_REQUEST['action'] ?? '';
    
    switch ($action) {


        case 'get_balance_details':
            $result = self::getClientBalanceDetails($userId);
            echo json_encode($result);
            break;
            
        case 'get_store_balance':
            $storeId = intval($_GET['store_id'] ?? $_POST['store_id'] ?? 0);
            
            if ($storeId <= 0) {
                echo json_encode(['status' => false, 'message' => 'ID da loja inválido']);
                return;
            }
            
            try {
                require_once __DIR__ . '/../models/CashbackBalance.php';
                $balanceModel = new CashbackBalance();
                $balance = $balanceModel->getStoreBalance($userId, $storeId);
                $statistics = $balanceModel->getBalanceStatistics($userId, $storeId);
                
                echo json_encode([
                    'status' => true,
                    'data' => [
                        'saldo_disponivel' => $balance,
                        'estatisticas' => $statistics
                    ]
                ]);
            } catch (Exception $e) {
                echo json_encode(['status' => false, 'message' => 'Erro ao obter saldo da loja']);
            }
            break;
            
        case 'get_total_balance':
            try {
                require_once __DIR__ . '/../models/CashbackBalance.php';
                $balanceModel = new CashbackBalance();
                $totalBalance = $balanceModel->getTotalBalance($userId);
                
                echo json_encode([
                    'status' => true,
                    'data' => ['saldo_total' => $totalBalance]
                ]);
            } catch (Exception $e) {
                echo json_encode(['status' => false, 'message' => 'Erro ao obter saldo total']);
            }
            break;
            
        case 'use_balance':
            $storeId = intval($_POST['store_id'] ?? 0);
            $amount = floatval($_POST['amount'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $transactionId = intval($_POST['transaction_id'] ?? 0) ?: null;
            
            if ($storeId <= 0) {
                echo json_encode(['status' => false, 'message' => 'ID da loja inválido']);
                return;
            }
            
            if ($amount <= 0) {
                echo json_encode(['status' => false, 'message' => 'Valor deve ser maior que zero']);
                return;
            }
            
            $result = self::useClientBalance($userId, $storeId, $amount, $description, $transactionId);
            echo json_encode($result);
            break;
            
        case 'get_balance_history':
            $storeId = intval($_GET['store_id'] ?? 0);
            $page = intval($_GET['page'] ?? 1);
            $limit = intval($_GET['limit'] ?? 20);
            
            if ($storeId <= 0) {
                echo json_encode(['status' => false, 'message' => 'ID da loja inválido']);
                return;
            }
            
            $result = self::getBalanceHistory($userId, $storeId, $page, $limit);
            echo json_encode($result);
            break;
            
        case 'simulate_balance_use':
            // Simular uso do saldo (para calculadoras em formulários)
            $storeId = intval($_POST['store_id'] ?? 0);
            $amount = floatval($_POST['amount'] ?? 0);
            
            if ($storeId <= 0 || $amount <= 0) {
                echo json_encode(['status' => false, 'message' => 'Parâmetros inválidos']);
                return;
            }
            
            try {
                require_once __DIR__ . '/../models/CashbackBalance.php';
                $balanceModel = new CashbackBalance();
                $currentBalance = $balanceModel->getStoreBalance($userId, $storeId);
                
                $canUse = $currentBalance >= $amount;
                $remainingBalance = $canUse ? ($currentBalance - $amount) : $currentBalance;
                
                echo json_encode([
                    'status' => true,
                    'data' => [
                        'pode_usar' => $canUse,
                        'saldo_atual' => $currentBalance,
                        'valor_solicitado' => $amount,
                        'saldo_restante' => $remainingBalance,
                        'valor_maximo_permitido' => $currentBalance
                    ]
                ]);
            } catch (Exception $e) {
                echo json_encode(['status' => false, 'message' => 'Erro ao simular uso do saldo']);
            }
            break;
            
        case 'refresh_balances':
            // Sincronizar saldos com base nas transações (útil para correções)
            try {
                require_once __DIR__ . '/../models/CashbackBalance.php';
                $balanceModel = new CashbackBalance();
                
                if ($balanceModel->syncBalancesFromTransactions($userId)) {
                    echo json_encode([
                        'status' => true,
                        'message' => 'Saldos sincronizados com sucesso'
                    ]);
                } else {
                    echo json_encode([
                        'status' => false,
                        'message' => 'Erro ao sincronizar saldos'
                    ]);
                }
            } catch (Exception $e) {
                echo json_encode(['status' => false, 'message' => 'Erro ao sincronizar saldos']);
            }
            break;
            
        case 'validate_balance_use':
            // Validar se o cliente pode usar determinado valor
            $storeId = intval($_POST['store_id'] ?? 0);
            $amount = floatval($_POST['amount'] ?? 0);
            
            try {
                require_once __DIR__ . '/../models/CashbackBalance.php';
                $balanceModel = new CashbackBalance();
                $currentBalance = $balanceModel->getStoreBalance($userId, $storeId);
                
                $validation = [
                    'valido' => $currentBalance >= $amount && $amount > 0,
                    'saldo_disponivel' => $currentBalance,
                    'valor_solicitado' => $amount,
                    'mensagem' => ''
                ];
                
                if ($amount <= 0) {
                    $validation['mensagem'] = 'Valor deve ser maior que zero';
                } elseif ($currentBalance < $amount) {
                    $validation['mensagem'] = 'Saldo insuficiente. Disponível: R$ ' . number_format($currentBalance, 2, ',', '.');
                } else {
                    $validation['mensagem'] = 'Valor válido para uso';
                }
                
                echo json_encode(['status' => true, 'data' => $validation]);
            } catch (Exception $e) {
                echo json_encode(['status' => false, 'message' => 'Erro ao validar uso do saldo']);
            }
            break;
            
        case 'get_balance_widget_data':
            // Dados específicos para o widget de saldo
            $storeId = isset($_GET['store_id']) ? intval($_GET['store_id']) : null;
            
            try {
                require_once __DIR__ . '/../models/CashbackBalance.php';
                $balanceModel = new CashbackBalance();
                
                if ($storeId) {
                    // Dados de uma loja específica
                    $balance = $balanceModel->getStoreBalance($userId, $storeId);
                    $statistics = $balanceModel->getBalanceStatistics($userId, $storeId);
                    
                    // Buscar dados da loja
                    $db = Database::getConnection();
                    $stmt = $db->prepare("SELECT nome_fantasia, logo, categoria FROM lojas WHERE id = :store_id");
                    $stmt->bindParam(':store_id', $storeId);
                    $stmt->execute();
                    $storeData = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $data = [
                        'tipo' => 'loja_especifica',
                        'loja' => array_merge($storeData, [
                            'id' => $storeId,
                            'saldo_disponivel' => $balance,
                            'estatisticas' => $statistics
                        ]),
                        'saldo_total' => $balance
                    ];
                } else {
                    // Dados de todas as lojas
                    $balances = $balanceModel->getAllUserBalances($userId);
                    $totalBalance = $balanceModel->getTotalBalance($userId);
                    
                    $data = [
                        'tipo' => 'todas_lojas',
                        'lojas' => $balances,
                        'saldo_total' => $totalBalance,
                        'total_lojas' => count($balances)
                    ];
                }
                
                echo json_encode(['status' => true, 'data' => $data]);
            } catch (Exception $e) {
                echo json_encode(['status' => false, 'message' => 'Erro ao obter dados do widget']);
            }
            break;
            
        case 'export_balance_history':
            // Exportar histórico de saldo em CSV
            $storeId = intval($_GET['store_id'] ?? 0);
            
            if ($storeId <= 0) {
                echo json_encode(['status' => false, 'message' => 'ID da loja inválido']);
                return;
            }
            
            try {
                require_once __DIR__ . '/../models/CashbackBalance.php';
                $balanceModel = new CashbackBalance();
                
                // Obter todo o histórico (sem limite)
                $history = $balanceModel->getMovementHistory($userId, $storeId, 999999, 0);
                
                // Gerar CSV
                $filename = 'historico_saldo_loja_' . $storeId . '_' . date('Y-m-d') . '.csv';
                
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: no-cache');
                
                $output = fopen('php://output', 'w');
                
                // Cabeçalho CSV
                fputcsv($output, [
                    'Data/Hora',
                    'Tipo',
                    'Valor',
                    'Saldo Anterior',
                    'Saldo Atual',
                    'Descrição'
                ]);
                
                // Dados
                foreach ($history as $movement) {
                    $type = '';
                    switch ($movement['tipo_operacao']) {
                        case 'credito': $type = 'Crédito'; break;
                        case 'uso': $type = 'Uso'; break;
                        case 'estorno': $type = 'Estorno'; break;
                    }
                    
                    fputcsv($output, [
                        date('d/m/Y H:i:s', strtotime($movement['data_operacao'])),
                        $type,
                        'R$ ' . number_format($movement['valor'], 2, ',', '.'),
                        'R$ ' . number_format($movement['saldo_anterior'], 2, ',', '.'),
                        'R$ ' . number_format($movement['saldo_atual'], 2, ',', '.'),
                        $movement['descricao']
                    ]);
                }
                
                fclose($output);
                exit;
            } catch (Exception $e) {
                echo json_encode(['status' => false, 'message' => 'Erro ao exportar histórico']);
            }
            break;


        case 'dashboard':
            $result = ClientController::getDashboardData($userId);
            echo json_encode($result);
            break;
            
        case 'statement':
            $filters = $_POST['filters'] ?? [];
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $result = ClientController::getStatement($userId, $filters, $page);
            echo json_encode($result);
            break;
            
        case 'profile':
            $result = ClientController::getProfileData($userId);
            echo json_encode($result);
            break;
            
        case 'update_profile':
            $data = $_POST;
            $result = ClientController::updateProfile($userId, $data);
            echo json_encode($result);
            break;
            
        case 'stores':
            $filters = $_POST['filters'] ?? [];
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $result = ClientController::getPartnerStores($userId, $filters, $page);
            echo json_encode($result);
            break;
            
        case 'favorite_store':
            $storeId = isset($_POST['store_id']) ? intval($_POST['store_id']) : 0;
            $favorite = isset($_POST['favorite']) ? (bool)$_POST['favorite'] : true;
            $result = ClientController::toggleFavoriteStore($userId, $storeId, $favorite);
            echo json_encode($result);
            break;
            
        case 'favorites':
            $result = ClientController::getFavoriteStores($userId);
            echo json_encode($result);
            break;
            
        case 'transaction':
            $transactionId = isset($_POST['transaction_id']) ? intval($_POST['transaction_id']) : 0;
            $result = ClientController::getTransactionDetails($userId, $transactionId);
            echo json_encode($result);
            break;
            
        case 'report':
            $filters = $_POST['filters'] ?? [];
            $result = ClientController::generateCashbackReport($userId, $filters);
            echo json_encode($result);
            break;
        case 'export_statement':
            // Verificar permissão
            if (!AuthController::isClient()) {
                header('Location: ' . LOGIN_URL . '?error=acesso_negado');
                exit;
            }
            
            // Obter filtros da URL
            $filters = [];
            if (!empty($_GET['data_inicio'])) $filters['data_inicio'] = $_GET['data_inicio'];
            if (!empty($_GET['data_fim'])) $filters['data_fim'] = $_GET['data_fim'];
            if (!empty($_GET['loja_id']) && $_GET['loja_id'] !== 'todas') $filters['loja_id'] = $_GET['loja_id'];
            if (!empty($_GET['status']) && $_GET['status'] !== 'todos') $filters['status'] = $_GET['status'];
            if (!empty($_GET['tipo_transacao']) && $_GET['tipo_transacao'] !== 'todos') $filters['tipo_transacao'] = $_GET['tipo_transacao'];
            
            // Obter dados do extrato
            $result = ClientController::getStatement($userId, $filters, 1, 999999); // Página 1 com limite alto para pegar tudo
            
            if (!$result['status']) {
                echo "<h1>Erro ao gerar extrato</h1><p>" . $result['message'] . "</p>";
                exit;
            }
            
            $statementData = $result['data'];
            
            // Obter dados do usuário
            $db = Database::getConnection();
            $userStmt = $db->prepare("SELECT nome, email FROM usuarios WHERE id = ?");
            $userStmt->execute([$userId]);
            $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            // Gerar HTML do extrato
            self::generateStatementHTML($userData, $statementData, $filters);
            exit;   
            break;

        case 'balance':
            $result = ClientController::getClientBalance($userId);
            echo json_encode($result);
            break;
        case 'store_balance_details':
            $lojaId = isset($_GET['loja_id']) ? intval($_GET['loja_id']) : 0;
            
            if ($lojaId <= 0) {
                echo json_encode(['status' => false, 'message' => 'ID da loja inválido']);
                return;
            }
            
            $result = ClientController::getStoreBalanceDetails($userId, $lojaId);
            echo json_encode($result);
            break;
            
        case 'simulate_balance_use':
            $lojaId = isset($_POST['loja_id']) ? intval($_POST['loja_id']) : 0;
            $valor = isset($_POST['valor']) ? floatval($_POST['valor']) : 0;
            
            if ($lojaId <= 0) {
                echo json_encode(['status' => false, 'message' => 'ID da loja inválido']);
                return;
            }
            
            $result = ClientController::simulateBalanceUse($userId, $lojaId, $valor);
            echo json_encode($result);
            break;
        default:
            // Acesso inválido ao controlador
            header('Location: ' . CLIENT_DASHBOARD_URL);
            exit;
    }

}


?>