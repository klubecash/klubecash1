<?php
/**
 * Webhook Handler para Abacate Pay
 *
 * Processa eventos do Abacate Pay:
 * - charge.paid: Pagamento confirmado
 * - charge.expired: Cobrança expirou
 * - charge.failed: Falha no pagamento
 *
 * IMPORTANTE: Configurar este endpoint no painel Abacate Pay
 * URL: https://klubecash.com/api/abacatepay-webhook
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../utils/AbacatePayClient.php';
require_once __DIR__ . '/../controllers/SubscriptionController.php';

// Log de webhook
function logWebhook($message, $data = [], $level = 'INFO') {
    $logFile = __DIR__ . '/../logs/abacate_webhook.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}][{$level}] {$message}: " . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);

    if ($level === 'ERROR') {
        error_log($logMessage);
    }
}

// Resposta HTTP
function respond($statusCode = 200, $message = 'OK') {
    http_response_code($statusCode);
    echo json_encode(['status' => $statusCode, 'message' => $message]);
    exit;
}

try {
    logWebhook('Webhook recebido', [
        'method' => $_SERVER['REQUEST_METHOD'],
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'unknown'
    ]);

    // Apenas aceitar POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        logWebhook('Método inválido', ['method' => $_SERVER['REQUEST_METHOD']], 'WARNING');
        respond(405, 'Method Not Allowed');
    }

    // Ler corpo da requisição
    $rawBody = file_get_contents('php://input');

    if (empty($rawBody)) {
        logWebhook('Corpo vazio', [], 'WARNING');
        respond(400, 'Empty body');
    }

    // Validar assinatura do webhook
    $abacateClient = new AbacatePayClient();
    $headers = getallheaders();

    if (!$abacateClient->validateWebhookSignature($headers, $rawBody)) {
        logWebhook('Assinatura inválida', ['headers' => $headers], 'ERROR');
        respond(401, 'Invalid signature');
    }

    // Decodificar evento
    $event = json_decode($rawBody, true);

    if (!$event || !isset($event['type'])) {
        logWebhook('JSON inválido', ['raw' => $rawBody], 'ERROR');
        respond(400, 'Invalid JSON');
    }

    $eventType = $event['type'];
    $eventId = $event['id'] ?? uniqid('evt_');
    $chargeData = $event['data'] ?? [];

    logWebhook('Processando evento', [
        'type' => $eventType,
        'id' => $eventId,
        'charge_id' => $chargeData['id'] ?? 'unknown'
    ]);

    // Conectar ao banco
    $db = (new Database())->getConnection();

    // ========================================
    // Verificar idempotência (evitar duplicados)
    // ========================================
    $sqlCheck = "SELECT id FROM webhook_events WHERE external_id = ? AND gateway = 'abacate'";
    $stmtCheck = $db->prepare($sqlCheck);
    $stmtCheck->execute([$eventId]);

    if ($stmtCheck->fetch()) {
        logWebhook('Evento já processado (idempotência)', ['event_id' => $eventId], 'INFO');
        respond(200, 'Already processed');
    }

    // ========================================
    // Salvar evento no banco
    // ========================================
    $sqlInsert = "INSERT INTO webhook_events (gateway, event_type, external_id, payload_json, created_at)
                  VALUES ('abacate', ?, ?, ?, NOW())";
    $stmtInsert = $db->prepare($sqlInsert);
    $stmtInsert->execute([$eventType, $eventId, $rawBody]);
    $webhookEventId = $db->lastInsertId();

    // ========================================
    // Processar eventos específicos
    // ========================================
    $subscriptionController = new SubscriptionController($db);
    $chargeId = $chargeData['id'] ?? null;

    switch ($eventType) {
        // -----------------------------------------
        // Pagamento confirmado
        // -----------------------------------------
        case 'charge.paid':
            if (!$chargeId) {
                logWebhook('Charge ID ausente no evento charge.paid', $event, 'ERROR');
                break;
            }

            // Buscar fatura pelo charge_id
            $sqlFatura = "SELECT f.id, f.assinatura_id, f.status
                          FROM faturas f
                          WHERE f.gateway_charge_id = ?";
            $stmtFatura = $db->prepare($sqlFatura);
            $stmtFatura->execute([$chargeId]);
            $fatura = $stmtFatura->fetch(PDO::FETCH_ASSOC);

            if (!$fatura) {
                logWebhook('Fatura não encontrada', ['charge_id' => $chargeId], 'WARNING');
                break;
            }

            // Evitar processar fatura já paga
            if ($fatura['status'] === 'paid') {
                logWebhook('Fatura já marcada como paga', ['fatura_id' => $fatura['id']], 'INFO');
                break;
            }

            // Marcar fatura como paga
            $paidAt = isset($chargeData['paidAt'])
                ? date('Y-m-d H:i:s', $chargeData['paidAt'])
                : date('Y-m-d H:i:s');

            $sqlUpdate = "UPDATE faturas SET
                          status = 'paid',
                          paid_at = ?,
                          updated_at = NOW()
                          WHERE id = ?";
            $stmtUpdate = $db->prepare($sqlUpdate);
            $stmtUpdate->execute([$paidAt, $fatura['id']]);

            logWebhook('Fatura marcada como paga', [
                'fatura_id' => $fatura['id'],
                'paid_at' => $paidAt
            ]);

            // Avançar período da assinatura
            $result = $subscriptionController->advancePeriodOnPaid($fatura['id']);

            logWebhook('Período avançado', [
                'fatura_id' => $fatura['id'],
                'result' => $result
            ]);

            // TODO: Enviar notificação/email para lojista

            break;

        // -----------------------------------------
        // Cobrança expirou
        // -----------------------------------------
        case 'charge.expired':
            if (!$chargeId) {
                logWebhook('Charge ID ausente no evento charge.expired', $event, 'ERROR');
                break;
            }

            $sqlExpired = "UPDATE faturas SET
                           status = 'failed',
                           updated_at = NOW()
                           WHERE gateway_charge_id = ? AND status = 'pending'";
            $stmtExpired = $db->prepare($sqlExpired);
            $stmtExpired->execute([$chargeId]);

            logWebhook('Fatura marcada como expirada', [
                'charge_id' => $chargeId,
                'rows_affected' => $stmtExpired->rowCount()
            ]);

            break;

        // -----------------------------------------
        // Falha no pagamento
        // -----------------------------------------
        case 'charge.failed':
            if (!$chargeId) {
                logWebhook('Charge ID ausente no evento charge.failed', $event, 'ERROR');
                break;
            }

            $sqlFailed = "UPDATE faturas SET
                          status = 'failed',
                          updated_at = NOW()
                          WHERE gateway_charge_id = ? AND status = 'pending'";
            $stmtFailed = $db->prepare($sqlFailed);
            $stmtFailed->execute([$chargeId]);

            logWebhook('Fatura marcada como falha', [
                'charge_id' => $chargeId,
                'rows_affected' => $stmtFailed->rowCount()
            ]);

            break;

        // -----------------------------------------
        // Evento não tratado
        // -----------------------------------------
        default:
            logWebhook('Evento não tratado', ['type' => $eventType], 'WARNING');
            break;
    }

    // Marcar evento como processado
    $sqlProcessed = "UPDATE webhook_events SET processed_at = NOW() WHERE id = ?";
    $stmtProcessed = $db->prepare($sqlProcessed);
    $stmtProcessed->execute([$webhookEventId]);

    logWebhook('Webhook processado com sucesso', ['webhook_event_id' => $webhookEventId]);
    respond(200, 'Webhook processed');

} catch (Exception $e) {
    logWebhook('Erro ao processar webhook', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], 'ERROR');

    respond(500, 'Internal error');
}
