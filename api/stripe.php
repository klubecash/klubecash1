<?php
/**
 * API Endpoint para pagamentos com cartão via Stripe
 *
 * Endpoints disponíveis:
 * - POST ?action=create_payment_intent&invoice_id=X - Cria Payment Intent para pagamento com cartão
 * - GET ?action=payment_status&payment_intent_id=X - Consulta status de um pagamento
 * - POST ?action=cancel_payment&payment_intent_id=X - Cancela um pagamento pendente
 *
 * @package KlubeCash
 * @version 1.0.0
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Tratar preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/StripePayClient.php';
require_once __DIR__ . '/../config/constants.php';

// Função para retornar resposta JSON
function jsonResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Função para log de erros
function logError($message, $context = [])
{
    $logFile = __DIR__ . '/../logs/stripe_api.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? json_encode($context) : '';
    $logMessage = "[{$timestamp}] {$message} {$contextStr}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Obter ação e método HTTP
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    // Inicializar cliente Stripe
    $stripeClient = new StripePayClient();
    $db = Database::getConnection();

    // ============================================================================
    // POST /api/stripe.php?action=create_payment_intent&invoice_id=123
    // Cria Payment Intent para fatura específica
    // ============================================================================
    if ($method === 'POST' && $action === 'create_payment_intent') {
        $invoiceId = $_GET['invoice_id'] ?? null;

        if (!$invoiceId) {
            jsonResponse(['error' => 'invoice_id é obrigatório'], 400);
        }

        // Buscar dados da fatura com informações da loja
        $stmt = $db->prepare("
            SELECT
                f.id as fatura_id,
                f.assinatura_id,
                f.numero as fatura_numero,
                f.amount,
                f.status as fatura_status,
                f.gateway_charge_id,
                l.id as loja_id,
                l.nome_fantasia,
                l.email,
                l.cnpj,
                a.loja_id,
                p.nome as plano_nome,
                a.ciclo
            FROM faturas f
            INNER JOIN assinaturas a ON f.assinatura_id = a.id
            INNER JOIN lojas l ON a.loja_id = l.id
            INNER JOIN planos p ON a.plano_id = p.id
            WHERE f.id = ?
        ");
        $stmt->execute([$invoiceId]);
        $fatura = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fatura) {
            jsonResponse(['error' => 'Fatura não encontrada'], 404);
        }

        // Verificar se fatura já foi paga
        if ($fatura['fatura_status'] === 'paid') {
            jsonResponse(['error' => 'Fatura já foi paga'], 400);
        }

        // Verificar se já existe Payment Intent criado para esta fatura
        if ($fatura['gateway_charge_id'] && strpos($fatura['gateway_charge_id'], 'pi_') === 0) {
            // Payment Intent já existe, retornar dados existentes
            try {
                $existingPI = $stripeClient->getPaymentIntent($fatura['gateway_charge_id']);

                // Se o Payment Intent ainda está pendente, retornar ele
                if (in_array($existingPI['status'], ['requires_payment_method', 'requires_confirmation', 'requires_action'])) {
                    jsonResponse([
                        'success' => true,
                        'payment_intent_id' => $existingPI['id'],
                        'client_secret' => null, // Por segurança, não expor client_secret de PIs antigos
                        'status' => $existingPI['status'],
                        'amount' => $existingPI['amount'],
                        'message' => 'Payment Intent já existe para esta fatura'
                    ]);
                }
            } catch (Exception $e) {
                // Se Payment Intent não existe mais, criar novo
                logError("Payment Intent anterior não encontrado: " . $e->getMessage());
            }
        }

        // Preparar payload para Stripe
        $cicloTexto = $fatura['ciclo'] === 'yearly' ? 'anual' : 'mensal';
        $payload = [
            'amount' => StripePayClient::toCents($fatura['amount']),
            'currency' => 'brl',
            'description' => "Klube Cash - {$fatura['plano_nome']} ({$cicloTexto}) - Fatura #{$fatura['fatura_numero']}",
            'customer_email' => $fatura['email'],
            'metadata' => [
                'invoice_id' => $fatura['fatura_id'],
                'invoice_number' => $fatura['fatura_numero'],
                'subscription_id' => $fatura['assinatura_id'],
                'store_id' => $fatura['loja_id'],
                'store_name' => $fatura['nome_fantasia'],
                'plan_name' => $fatura['plano_nome'],
                'billing_cycle' => $fatura['ciclo'],
            ],
        ];

        // Criar Payment Intent na Stripe
        $paymentIntent = $stripeClient->createPaymentIntent($payload);

        // Atualizar fatura com dados do Payment Intent
        $updateStmt = $db->prepare("
            UPDATE faturas SET
                gateway = 'stripe',
                gateway_charge_id = ?,
                payment_method = 'card',
                updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([
            $paymentIntent['id'],
            $invoiceId
        ]);

        logError("Payment Intent criado com sucesso", [
            'invoice_id' => $invoiceId,
            'payment_intent_id' => $paymentIntent['id'],
            'amount' => $paymentIntent['amount']
        ]);

        jsonResponse([
            'success' => true,
            'payment_intent_id' => $paymentIntent['id'],
            'client_secret' => $paymentIntent['client_secret'],
            'status' => $paymentIntent['status'],
            'amount' => $paymentIntent['amount'],
            'currency' => $paymentIntent['currency'],
        ]);
    }

    // ============================================================================
    // GET /api/stripe.php?action=payment_status&payment_intent_id=pi_xxx
    // Consulta status de pagamento
    // ============================================================================
    elseif ($method === 'GET' && $action === 'payment_status') {
        $paymentIntentId = $_GET['payment_intent_id'] ?? null;

        if (!$paymentIntentId) {
            jsonResponse(['error' => 'payment_intent_id é obrigatório'], 400);
        }

        // Consultar Payment Intent na Stripe
        $paymentIntent = $stripeClient->getPaymentIntent($paymentIntentId);

        // Buscar fatura associada
        $stmt = $db->prepare("
            SELECT id, status, paid_at
            FROM faturas
            WHERE gateway_charge_id = ?
        ");
        $stmt->execute([$paymentIntentId]);
        $fatura = $stmt->fetch(PDO::FETCH_ASSOC);

        jsonResponse([
            'success' => true,
            'payment_intent_id' => $paymentIntent['id'],
            'status' => $paymentIntent['status'],
            'amount' => $paymentIntent['amount'],
            'currency' => $paymentIntent['currency'],
            'invoice_status' => $fatura ? $fatura['status'] : null,
            'paid_at' => $fatura ? $fatura['paid_at'] : null,
        ]);
    }

    // ============================================================================
    // POST /api/stripe.php?action=cancel_payment&payment_intent_id=pi_xxx
    // Cancela Payment Intent pendente
    // ============================================================================
    elseif ($method === 'POST' && $action === 'cancel_payment') {
        $paymentIntentId = $_GET['payment_intent_id'] ?? null;

        if (!$paymentIntentId) {
            jsonResponse(['error' => 'payment_intent_id é obrigatório'], 400);
        }

        // Cancelar na Stripe
        $result = $stripeClient->cancelPaymentIntent($paymentIntentId);

        // Atualizar fatura para status 'canceled'
        $stmt = $db->prepare("
            UPDATE faturas SET
                status = 'canceled',
                updated_at = NOW()
            WHERE gateway_charge_id = ?
        ");
        $stmt->execute([$paymentIntentId]);

        logError("Payment Intent cancelado", [
            'payment_intent_id' => $paymentIntentId,
            'status' => $result['status']
        ]);

        jsonResponse([
            'success' => true,
            'payment_intent_id' => $result['id'],
            'status' => $result['status'],
            'message' => 'Pagamento cancelado com sucesso'
        ]);
    }

    // ============================================================================
    // Ação não reconhecida
    // ============================================================================
    else {
        jsonResponse([
            'error' => 'Ação não reconhecida',
            'available_actions' => [
                'create_payment_intent' => 'POST ?action=create_payment_intent&invoice_id=X',
                'payment_status' => 'GET ?action=payment_status&payment_intent_id=pi_xxx',
                'cancel_payment' => 'POST ?action=cancel_payment&payment_intent_id=pi_xxx',
            ]
        ], 400);
    }
} catch (Exception $e) {
    logError("Erro na API Stripe: " . $e->getMessage(), [
        'action' => $action,
        'method' => $method,
        'trace' => $e->getTraceAsString()
    ]);

    jsonResponse([
        'error' => 'Erro ao processar requisição',
        'message' => $e->getMessage()
    ], 500);
}
