<?php
// api/dashboard.php
// API para obter dados do dashboard

// Remover headers CORS existentes para evitar duplicação
header_remove('Access-Control-Allow-Origin');
header_remove('Access-Control-Allow-Methods');
header_remove('Access-Control-Allow-Headers');
header_remove('Access-Control-Allow-Credentials');

// Definir headers CORS corretos
header('Content-Type: application/json; charset=UTF-8');

$allowedOrigins = ['http://localhost:5173', 'http://localhost:3000', 'https://klubecash.com'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin", true);
    header('Access-Control-Allow-Credentials: true', true);
} else {
    header('Access-Control-Allow-Origin: *', true);
}

header('Access-Control-Allow-Methods: GET, OPTIONS', true);
header('Access-Control-Allow-Headers: Content-Type, Authorization', true);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

session_start();

require_once __DIR__ . '/../config/database.php';

try {
    // Verificar autenticação básica
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['status' => false, 'message' => 'Usuário não autenticado']);
        exit;
    }

    $userId = $_SESSION['user_id'];
    $userType = $_SESSION['user_type'] ?? '';

    $db = Database::getConnection();

    // Endpoint específico
    $endpoint = $_GET['endpoint'] ?? '';

    if ($endpoint === 'kpi') {
        // Obter KPIs do dashboard
        getKPIData($db, $userId, $userType);
    } else {
        // Obter todos os dados do dashboard
        getAllDashboardData($db, $userId, $userType);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => false,
        'message' => 'Erro ao processar requisição: ' . $e->getMessage()
    ]);
}

function getKPIData($db, $userId, $userType) {
    if ($userType === 'loja') {
        // KPIs para loja
        $stmt = $db->prepare("
            SELECT
                COUNT(DISTINCT t.id) as total_vendas,
                COALESCE(SUM(t.valor_total), 0) as valor_total,
                COUNT(CASE WHEN t.status = 'pendente' THEN 1 END) as pendentes,
                COALESCE(SUM(CASE WHEN t.status = 'aprovado' THEN t.comissao_loja ELSE 0 END), 0) as comissoes
            FROM transacoes t
            WHERE t.loja_id IN (
                SELECT id FROM lojas WHERE responsavel_id = ?
            )
        ");
        $stmt->execute([$userId]);
        $kpi = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => true,
            'data' => [
                'totalVendas' => (int)$kpi['total_vendas'],
                'valorTotal' => (float)$kpi['valor_total'],
                'pendentes' => (int)$kpi['pendentes'],
                'comissoes' => (float)$kpi['comissoes']
            ]
        ]);
    } else {
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Acesso não autorizado']);
    }
}

function getAllDashboardData($db, $userId, $userType) {
    if ($userType === 'loja') {
        // Obter ID da loja
        $lojaStmt = $db->prepare("SELECT id FROM lojas WHERE responsavel_id = ? LIMIT 1");
        $lojaStmt->execute([$userId]);
        $loja = $lojaStmt->fetch(PDO::FETCH_ASSOC);

        if (!$loja) {
            echo json_encode(['status' => false, 'message' => 'Loja não encontrada']);
            return;
        }

        $lojaId = $loja['id'];

        // KPIs
        $kpiStmt = $db->prepare("
            SELECT
                COUNT(DISTINCT t.id) as total_vendas,
                COALESCE(SUM(t.valor_total), 0) as valor_total,
                COUNT(CASE WHEN t.status = 'pendente' THEN 1 END) as pendentes,
                COALESCE(SUM(CASE WHEN t.status = 'aprovado' THEN t.comissao_loja ELSE 0 END), 0) as comissoes
            FROM transacoes t
            WHERE t.loja_id = ?
        ");
        $kpiStmt->execute([$lojaId]);
        $kpi = $kpiStmt->fetch(PDO::FETCH_ASSOC);

        // Transações recentes
        $transactionsStmt = $db->prepare("
            SELECT
                t.id,
                t.data_transacao as date,
                u.nome as client,
                t.codigo_transacao as code,
                t.valor_total as value,
                t.status
            FROM transacoes t
            LEFT JOIN usuarios u ON t.usuario_id = u.id
            WHERE t.loja_id = ?
            ORDER BY t.data_transacao DESC
            LIMIT 5
        ");
        $transactionsStmt->execute([$lojaId]);
        $transactions = $transactionsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Formatar transações
        $formattedTransactions = array_map(function($transaction) {
            return [
                'id' => (int)$transaction['id'],
                'date' => $transaction['date'],
                'client' => $transaction['client'] ?? 'Cliente não identificado',
                'code' => $transaction['code'],
                'value' => (float)$transaction['value'],
                'status' => $transaction['status']
            ];
        }, $transactions);

        echo json_encode([
            'status' => true,
            'data' => [
                'kpi' => [
                    'totalVendas' => (int)$kpi['total_vendas'],
                    'valorTotal' => (float)$kpi['valor_total'],
                    'pendentes' => (int)$kpi['pendentes'],
                    'comissoes' => (float)$kpi['comissoes']
                ],
                'recentTransactions' => $formattedTransactions
            ]
        ]);
    } else {
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Acesso não autorizado']);
    }
}
?>
