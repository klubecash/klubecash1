<?php
/**
 * Webhook Handler para eventos da Stripe
 *
 * Este endpoint recebe notificações da Stripe sobre eventos de pagamento.
 * IMPORTANTE: Sempre valide assinaturas de webhook em produção!
 *
 * Eventos tratados:
 * - payment_intent.succeeded - Pagamento com cartão confirmado
 * - payment_intent.payment_failed - Pagamento falhou
 * - payment_intent.canceled - Pagamento cancelado
 *
 * Configuração no Stripe Dashboard:
 * 1. Acesse: https://dashboard.stripe.com/webhooks
 * 2. Adicione endpoint: https://klubecash.com/api/stripe-webhook.php
 * 3. Selecione eventos: payment_intent.succeeded, payment_intent.payment_failed
 * 4. Copie o Webhook Secret para constants.php (STRIPE_WEBHOOK_SECRET)
 *
 * @package KlubeCash
 * @version 1.0.0
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/StripePayClient.php';
require_once __DIR__ . '/../controllers/SubscriptionController.php';
require_once __DIR__ . '/../config/constants.php';

// Função para retornar resposta JSON
function jsonResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Função para log detalhado
function logWebhook($message, $context = [])
{
    $logFile = __DIR__ . '/../logs/stripe_webhook.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    $logMessage = "[{$timestamp}] {$message} {$contextStr}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

try {
    // Obter corpo da requisição (raw JSON)
    $rawPayload = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

    if (empty($rawPayload)) {
        logWebhook("Webhook recebido sem payload");
        jsonResponse(['error' => 'Payload vazio'], 400);
    }

    logWebhook("Webhook recebido", [
        'payload_size' => strlen($rawPayload),
        'has_signature' => !empty($signature)
    ]);

    // Decodificar payload
    $event = json_decode($rawPayload, true);

    if ($event === null) {
        logWebhook("Erro ao decodificar JSON do webhook");
        jsonResponse(['error' => 'JSON inválido'], 400);
    }

    // ============================================================================
    // VALIDAÇÃO DE ASSINATURA (CRÍTICO PARA SEGURANÇA)
    // ============================================================================
    // ATENÇÃO: Em produção, SEMPRE habilite a validação!
    // Em desenvolvimento/testes, pode ser desabilitada temporariamente
    $validateSignature = defined('STRIPE_VALIDATE_WEBHOOK') ? STRIPE_VALIDATE_WEBHOOK : true;

    if ($validateSignature) {
        try {
            $stripeClient = new StripePayClient();
            $stripeClient->validateWebhookSignature($rawPayload, $signature);
            logWebhook("Assinatura do webhook validada com sucesso");
        } catch (Exception $e) {
            logWebhook("ERRO: Assinatura inválida - " . $e->getMessage());
            jsonResponse(['error' => 'Assinatura inválida'], 403);
        }
    } else {
        logWebhook("AVISO: Validação de assinatura DESABILITADA (apenas para desenvolvimento)");
    }

    // ============================================================================
    // REGISTRO DO EVENTO (Idempotência)
    // ============================================================================
    $db = Database::getConnection();
    $eventId = $event['id'] ?? null;
    $eventType = $event['type'] ?? 'unknown';

    if (!$eventId) {
        logWebhook("Evento sem ID, ignorando");
        jsonResponse(['error' => 'Evento sem ID'], 400);
    }

    // Verificar se evento já foi processado (proteção contra duplicatas)
    $stmt = $db->prepare("
        SELECT id, processed_at
        FROM webhook_events
        WHERE gateway = 'stripe' AND external_id = ?
    ");
    $stmt->execute([$eventId]);
    $existingEvent = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingEvent && $existingEvent['processed_at']) {
        logWebhook("Evento já processado anteriormente", [
            'event_id' => $eventId,
            'processed_at' => $existingEvent['processed_at']
        ]);
        jsonResponse(['success' => true, 'message' => 'Evento já processado'], 200);
    }

    // Registrar evento no banco
    if (!$existingEvent) {
        $insertStmt = $db->prepare("
            INSERT INTO webhook_events (gateway, event_type, external_id, payload_json, created_at)
            VALUES ('stripe', ?, ?, ?, NOW())
        ");
        $insertStmt->execute([
            $eventType,
            $eventId,
            $rawPayload
        ]);
        $webhookEventId = $db->lastInsertId();
        logWebhook("Evento registrado no banco", ['webhook_event_id' => $webhookEventId]);
    } else {
        $webhookEventId = $existingEvent['id'];
    }

    // ============================================================================
    // PROCESSAMENTO POR TIPO DE EVENTO
    // ============================================================================

    $paymentIntent = $event['data']['object'] ?? [];
    $paymentIntentId = $paymentIntent['id'] ?? null;

    logWebhook("Processando evento", [
        'event_type' => $eventType,
        'payment_intent_id' => $paymentIntentId
    ]);

    switch ($eventType) {
        // ========================================================================
        // PAGAMENTO CONFIRMADO
        // ========================================================================
        case 'payment_intent.succeeded':
            if (!$paymentIntentId) {
                logWebhook("ERRO: payment_intent.succeeded sem ID");
                jsonResponse(['error' => 'Payment Intent ID não encontrado'], 400);
            }

            // Buscar fatura pelo Payment Intent ID
            $stmt = $db->prepare("
                SELECT f.id, f.assinatura_id, f.status, f.amount
                FROM faturas f
                WHERE f.gateway_charge_id = ? AND f.gateway = 'stripe'
            ");
            $stmt->execute([$paymentIntentId]);
            $fatura = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$fatura) {
                logWebhook("ERRO: Fatura não encontrada para Payment Intent", [
                    'payment_intent_id' => $paymentIntentId
                ]);
                jsonResponse(['error' => 'Fatura não encontrada'], 404);
            }

            // Se fatura já foi paga, não processar novamente
            if ($fatura['status'] === 'paid') {
                logWebhook("Fatura já estava paga, ignorando", ['fatura_id' => $fatura['id']]);
                jsonResponse(['success' => true, 'message' => 'Fatura já estava paga'], 200);
            }

            // Extrair informações do cartão
            $paymentMethodId = $paymentIntent['payment_method'] ?? null;
            $cardBrand = null;
            $cardLast4 = null;

            if ($paymentMethodId) {
                try {
                    $stripeClient = new StripePayClient();
                    $paymentMethodDetails = $stripeClient->getPaymentMethodDetails($paymentMethodId);

                    if ($paymentMethodDetails['type'] === 'card') {
                        $cardBrand = $paymentMethodDetails['brand'];
                        $cardLast4 = $paymentMethodDetails['last4'];
                    }
                } catch (Exception $e) {
                    logWebhook("Erro ao buscar detalhes do método de pagamento: " . $e->getMessage());
                }
            }

            // Atualizar fatura para PAGA
            $updateStmt = $db->prepare("
                UPDATE faturas SET
                    status = 'paid',
                    paid_at = NOW(),
                    card_brand = ?,
                    card_last4 = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([
                $cardBrand,
                $cardLast4,
                $fatura['id']
            ]);

            logWebhook("Fatura marcada como PAGA", [
                'fatura_id' => $fatura['id'],
                'card_brand' => $cardBrand,
                'card_last4' => $cardLast4
            ]);

            // Avançar período da assinatura
            try {
                $subscriptionController = new SubscriptionController();
                $result = $subscriptionController->advancePeriodOnPaid($fatura['id']);

                if ($result['success']) {
                    logWebhook("Período da assinatura avançado com sucesso", [
                        'assinatura_id' => $fatura['assinatura_id']
                    ]);
                } else {
                    logWebhook("ERRO ao avançar período: " . ($result['message'] ?? 'Erro desconhecido'));
                }
            } catch (Exception $e) {
                logWebhook("EXCEÇÃO ao avançar período: " . $e->getMessage());
            }

            // Marcar webhook como processado
            $db->prepare("UPDATE webhook_events SET processed_at = NOW() WHERE id = ?")
                ->execute([$webhookEventId]);

            jsonResponse([
                'success' => true,
                'message' => 'Pagamento confirmado',
                'fatura_id' => $fatura['id']
            ]);
            break;

        // ========================================================================
        // PAGAMENTO FALHOU
        // ========================================================================
        case 'payment_intent.payment_failed':
            if (!$paymentIntentId) {
                logWebhook("ERRO: payment_intent.payment_failed sem ID");
                jsonResponse(['error' => 'Payment Intent ID não encontrado'], 400);
            }

            // Buscar fatura
            $stmt = $db->prepare("
                SELECT f.id, f.status
                FROM faturas f
                WHERE f.gateway_charge_id = ? AND f.gateway = 'stripe'
            ");
            $stmt->execute([$paymentIntentId]);
            $fatura = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$fatura) {
                logWebhook("AVISO: Fatura não encontrada para pagamento falho", [
                    'payment_intent_id' => $paymentIntentId
                ]);
                jsonResponse(['success' => true, 'message' => 'Fatura não encontrada'], 200);
            }

            // Atualizar status para FALHOU
            $updateStmt = $db->prepare("
                UPDATE faturas SET
                    status = 'failed',
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$fatura['id']]);

            logWebhook("Fatura marcada como FALHOU", [
                'fatura_id' => $fatura['id'],
                'error_message' => $paymentIntent['last_payment_error']['message'] ?? 'Desconhecido'
            ]);

            // Marcar webhook como processado
            $db->prepare("UPDATE webhook_events SET processed_at = NOW() WHERE id = ?")
                ->execute([$webhookEventId]);

            jsonResponse([
                'success' => true,
                'message' => 'Pagamento falhou registrado',
                'fatura_id' => $fatura['id']
            ]);
            break;

        // ========================================================================
        // PAGAMENTO CANCELADO
        // ========================================================================
        case 'payment_intent.canceled':
            if (!$paymentIntentId) {
                logWebhook("ERRO: payment_intent.canceled sem ID");
                jsonResponse(['error' => 'Payment Intent ID não encontrado'], 400);
            }

            // Buscar fatura
            $stmt = $db->prepare("
                SELECT f.id, f.status
                FROM faturas f
                WHERE f.gateway_charge_id = ? AND f.gateway = 'stripe'
            ");
            $stmt->execute([$paymentIntentId]);
            $fatura = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$fatura) {
                logWebhook("AVISO: Fatura não encontrada para cancelamento", [
                    'payment_intent_id' => $paymentIntentId
                ]);
                jsonResponse(['success' => true, 'message' => 'Fatura não encontrada'], 200);
            }

            // Atualizar status para CANCELADO
            $updateStmt = $db->prepare("
                UPDATE faturas SET
                    status = 'canceled',
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$fatura['id']]);

            logWebhook("Fatura marcada como CANCELADA", ['fatura_id' => $fatura['id']]);

            // Marcar webhook como processado
            $db->prepare("UPDATE webhook_events SET processed_at = NOW() WHERE id = ?")
                ->execute([$webhookEventId]);

            jsonResponse([
                'success' => true,
                'message' => 'Cancelamento registrado',
                'fatura_id' => $fatura['id']
            ]);
            break;

        // ========================================================================
        // EVENTO NÃO TRATADO
        // ========================================================================
        default:
            logWebhook("Evento não tratado", ['event_type' => $eventType]);

            jsonResponse([
                'success' => true,
                'message' => 'Evento recebido mas não processado',
                'event_type' => $eventType
            ]);
            break;
    }
} catch (Exception $e) {
    logWebhook("ERRO CRÍTICO no webhook: " . $e->getMessage(), [
        'trace' => $e->getTraceAsString()
    ]);

    jsonResponse([
        'error' => 'Erro ao processar webhook',
        'message' => $e->getMessage()
    ], 500);
}
