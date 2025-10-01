<?php
// simple_store_details.php
session_start();
header('Content-Type: application/json');

try {
    require_once 'config/database.php';
    require_once 'config/constants.php';

    // Verificar autenticação básica
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'cliente') {
        echo json_encode(['status' => false, 'message' => 'Usuário não autenticado']);
        exit;
    }

    $userId = $_SESSION['user_id'];
    $lojaId = isset($_GET['loja_id']) ? intval($_GET['loja_id']) : 0;

    if ($lojaId <= 0) {
        echo json_encode(['status' => false, 'message' => 'ID da loja inválido']);
        exit;
    }

    $db = Database::getConnection();

    // Buscar dados da loja
    $storeStmt = $db->prepare("
        SELECT id, nome_fantasia, categoria, porcentagem_cashback, website, descricao, logo
        FROM lojas 
        WHERE id = ? AND status = 'aprovado'
    ");
    $storeStmt->execute([$lojaId]);
    $loja = $storeStmt->fetch(PDO::FETCH_ASSOC);

    if (!$loja) {
        echo json_encode(['status' => false, 'message' => 'Loja não encontrada']);
        exit;
    }

    // Buscar saldo do cliente nesta loja
    $saldoStmt = $db->prepare("
        SELECT 
            saldo_disponivel,
            total_creditado,
            total_usado,
            data_criacao,
            ultima_atualizacao
        FROM cashback_saldos 
        WHERE usuario_id = ? AND loja_id = ?
    ");
    $saldoStmt->execute([$userId, $lojaId]);
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

    // Buscar movimentações recentes
    $movimentacoesStmt = $db->prepare("
        SELECT 
            id,
            tipo_operacao,
            valor,
            saldo_anterior,
            saldo_atual,
            descricao,
            data_operacao
        FROM cashback_movimentacoes 
        WHERE usuario_id = ? AND loja_id = ?
        ORDER BY data_operacao DESC
        LIMIT 10
    ");
    $movimentacoesStmt->execute([$userId, $lojaId]);
    $movimentacoes = $movimentacoesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Retornar dados
    echo json_encode([
        'status' => true,
        'data' => [
            'loja' => $loja,
            'saldo' => $saldo,
            'movimentacoes' => $movimentacoes,
            'estatisticas' => [
                'total_movimentacoes' => count($movimentacoes)
            ]
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => false, 
        'message' => 'Erro: ' . $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
?>