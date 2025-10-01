<?php
// controllers/AjaxStoreController.php - Versão melhorada

// Iniciar sessão
session_start();

// Headers obrigatórios
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');

// Incluir dependências
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/AuthController.php';

// Verificar autenticação
if (!AuthController::isAuthenticated() || !AuthController::isAdmin()) {
    echo json_encode(['status' => false, 'message' => 'Acesso não autorizado']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'test_ajax':
            echo json_encode([
                'status' => true,
                'message' => 'AJAX funcionando perfeitamente!',
                'timestamp' => date('Y-m-d H:i:s'),
                'user_id' => $_SESSION['user_id'] ?? null,
                'user_type' => $_SESSION['user_type'] ?? null
            ]);
            break;
            
        case 'store_details':
            $storeId = isset($_POST['store_id']) ? intval($_POST['store_id']) : 0;
            
            if ($storeId <= 0) {
                echo json_encode(['status' => false, 'message' => 'ID da loja inválido']);
                exit;
            }
            
            $db = Database::getConnection();
            
            // QUERY CORRIGIDA - Qualificação adequada das colunas
            $stmt = $db->prepare("
                SELECT 
                    l.id,
                    l.usuario_id,
                    l.nome_fantasia,
                    l.razao_social,
                    l.cnpj,
                    l.email,
                    l.telefone,
                    l.categoria,
                    l.porcentagem_cashback,
                    l.descricao,
                    l.website,
                    l.logo,
                    l.status,
                    l.observacao,
                    l.data_cadastro,
                    l.data_aprovacao,
                    -- Informações de saldo (QUALIFICADO)
                    COALESCE(saldo_info.clientes_com_saldo, 0) as clientes_com_saldo,
                    COALESCE(saldo_info.total_saldo_clientes, 0) as total_saldo_clientes,
                    -- Informações de transações (QUALIFICADO)
                    COALESCE(trans_info.total_transacoes, 0) as total_transacoes,
                    COALESCE(trans_info.transacoes_com_saldo, 0) as transacoes_com_saldo,
                    -- Informações do usuário
                    u.nome as usuario_nome,
                    u.status as usuario_status
                FROM lojas l
                LEFT JOIN usuarios u ON l.usuario_id = u.id
                LEFT JOIN (
                    SELECT 
                        cs.loja_id,
                        COUNT(DISTINCT cs.usuario_id) as clientes_com_saldo,
                        SUM(cs.saldo_disponivel) as total_saldo_clientes
                    FROM cashback_saldos cs 
                    WHERE cs.saldo_disponivel > 0
                    GROUP BY cs.loja_id
                ) saldo_info ON l.id = saldo_info.loja_id
                LEFT JOIN (
                    SELECT 
                        tc.loja_id,
                        COUNT(*) as total_transacoes,
                        COUNT(CASE WHEN tsu.valor_usado > 0 THEN 1 END) as transacoes_com_saldo
                    FROM transacoes_cashback tc
                    LEFT JOIN transacoes_saldo_usado tsu ON tc.id = tsu.transacao_id
                    WHERE tc.status = 'aprovado'
                    GROUP BY tc.loja_id
                ) trans_info ON l.id = trans_info.loja_id
                WHERE l.id = ?
            ");
            $stmt->execute([$storeId]);
            $store = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$store) {
                echo json_encode(['status' => false, 'message' => 'Loja não encontrada']);
                exit;
            }
            
            // Buscar estatísticas da loja (QUALIFICADO)
            $statsStmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_transacoes,
                    COALESCE(SUM(tc.valor_total), 0) as total_vendas,
                    COALESCE(SUM(tc.valor_cliente), 0) as total_cashback
                FROM transacoes_cashback tc
                WHERE tc.loja_id = ? AND tc.status = 'aprovado'
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
            break;

        case 'test_connection':
            $db = Database::getConnection();
            $stmt = $db->query("SELECT COUNT(*) as total FROM lojas");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'status' => true,
                'message' => 'Conexão funcionando!',
                'total_lojas' => $result['total'],
                'user_type' => $_SESSION['user_type'] ?? 'não definido',
                'user_id' => $_SESSION['user_id'] ?? 'não definido'
            ]);
            break;
            
        default:
            echo json_encode(['status' => false, 'message' => 'Ação não encontrada: ' . $action]);
    }
    
} catch (Exception $e) {
    error_log('Erro em AjaxStoreController: ' . $e->getMessage());
    echo json_encode([
        'status' => false,
        'message' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>