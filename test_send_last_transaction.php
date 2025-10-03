<?php
require_once __DIR__ . '/config/whatsapp.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/utils/WhatsAppBot.php';

try {
    if (!WHATSAPP_ENABLED) {
        throw new RuntimeException('WHATSAPP_ENABLED está falso no ambiente atual.');
    }

    $db = Database::getConnection();
    $sql = "
        SELECT tc.id, tc.valor_total, tc.valor_cashback, tc.codigo_transacao, tc.status,
               u.id as usuario_id, u.telefone, u.nome as cliente_nome,
               l.nome_fantasia as loja_nome
        FROM transacoes_cashback tc
        INNER JOIN usuarios u ON tc.usuario_id = u.id
        INNER JOIN lojas l ON tc.loja_id = l.id
        ORDER BY tc.id DESC
        LIMIT 1
    ";

    $stmt = $db->query($sql);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        throw new RuntimeException('Nenhuma transação encontrada.');
    }

    if (empty($transaction['telefone'])) {
        throw new RuntimeException('Cliente da transação não possui telefone cadastrado.');
    }

    $payload = [
        'nome_loja' => $transaction['loja_nome'] ?? 'Loja parceira',
        'valor_total' => $transaction['valor_total'],
        'valor_cashback' => $transaction['valor_cashback'],
        'codigo_transacao' => $transaction['codigo_transacao'],
        'cliente_nome' => $transaction['cliente_nome'] ?? null,
    ];

    $options = [
        'custom_footer' => 'Teste de envio manual da última transação.',
        'tag' => 'transaction:test_last',
    ];

    $result = WhatsAppBot::sendNewTransactionNotification($transaction['telefone'], $payload, $options);

    echo json_encode([
        'transaction_id' => $transaction['id'],
        'telefone' => $transaction['telefone'],
        'status' => $result['success'],
        'message' => $result['message'] ?? null,
        'ack' => $result['ack'] ?? null,
        'response' => $result['response'] ?? null,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

} catch (Throwable $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
