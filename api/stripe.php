<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../utils/StripePayClient.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * @param array<string, mixed> $payload
 */
function stripeJsonResponse(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function stripeRequireStoreId(): int
{
    if (!AuthController::hasStoreAccess()) {
        stripeJsonResponse([
            'success' => false,
            'error' => 'Autenticação necessária.',
        ], 401);
    }

    $storeId = (int) AuthController::getStoreId();
    if ($storeId <= 0) {
        stripeJsonResponse([
            'success' => false,
            'error' => 'A sessão não está associada a uma loja.',
        ], 403);
    }

    return $storeId;
}

function stripeInvoiceId(mixed $value): int
{
    $invoiceId = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($invoiceId === false) {
        stripeJsonResponse([
            'success' => false,
            'error' => 'invoice_id é obrigatório.',
        ], 400);
    }

    return (int) $invoiceId;
}

function stripePaymentIntentId(mixed $value): string
{
    $paymentIntentId = trim((string) $value);
    if (!preg_match('/^pi_[A-Za-z0-9_]{3,252}$/', $paymentIntentId)) {
        stripeJsonResponse([
            'success' => false,
            'error' => 'payment_intent_id inválido.',
        ], 400);
    }

    return $paymentIntentId;
}

function stripeRequireConfiguration(): void
{
    if (!defined('STRIPE_SECRET_KEY') || trim((string) STRIPE_SECRET_KEY) === '') {
        stripeJsonResponse([
            'success' => false,
            'error' => 'Integração com cartão indisponível no momento.',
        ], 503);
    }
}

function stripeCreatePaymentIntent(int $storeId): never
{
    stripeRequireConfiguration();
    $invoiceId = stripeInvoiceId($_GET['invoice_id'] ?? null);
    $db = null;

    try {
        $db = Database::getConnection();
        $db->beginTransaction();

        $stmt = $db->prepare("
            SELECT f.id AS fatura_id,
                   f.assinatura_id,
                   f.numero AS fatura_numero,
                   f.amount,
                   f.status AS fatura_status,
                   f.gateway_charge_id,
                   l.id AS loja_id,
                   l.nome_fantasia,
                   l.email,
                   p.nome AS plano_nome,
                   a.ciclo
            FROM faturas f
            INNER JOIN assinaturas a ON f.assinatura_id = a.id
            INNER JOIN lojas l ON a.loja_id = l.id
            INNER JOIN planos p ON a.plano_id = p.id
            WHERE f.id = ?
              AND a.loja_id = ?
            FOR UPDATE
        ");
        $stmt->execute([$invoiceId, $storeId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            $db->rollBack();
            stripeJsonResponse([
                'success' => false,
                'error' => 'Fatura não encontrada.',
            ], 404);
        }

        if (in_array((string) $invoice['fatura_status'], ['paid', 'canceled', 'refunded'], true)) {
            $db->rollBack();
            stripeJsonResponse([
                'success' => false,
                'error' => 'Esta fatura não pode receber um novo pagamento.',
            ], 409);
        }

        $stripeClient = new StripePayClient();
        $existingPaymentIntentId = (string) ($invoice['gateway_charge_id'] ?? '');
        if (str_starts_with($existingPaymentIntentId, 'pi_')) {
            // Falhas na consulta não criam uma segunda cobrança: o operador pode
            // tentar novamente quando a Stripe estiver disponível.
            $existingPaymentIntent = $stripeClient->getPaymentIntent($existingPaymentIntentId);
            $existingStatus = (string) ($existingPaymentIntent['status'] ?? '');

            if ($existingStatus !== 'canceled') {
                $db->commit();
                stripeJsonResponse([
                    'success' => true,
                    'payment_intent_id' => (string) $existingPaymentIntent['id'],
                    'client_secret' => null,
                    'status' => $existingStatus,
                    'amount' => (int) $existingPaymentIntent['amount'],
                    'currency' => (string) $existingPaymentIntent['currency'],
                    'message' => 'Já existe um pagamento Stripe para esta fatura.',
                ]);
            }
        }

        $amountInCents = StripePayClient::toCents($invoice['amount']);
        if ($amountInCents <= 0) {
            $db->rollBack();
            stripeJsonResponse([
                'success' => false,
                'error' => 'A fatura possui valor inválido.',
            ], 422);
        }

        $cycleLabel = $invoice['ciclo'] === 'yearly' ? 'anual' : 'mensal';
        $paymentIntent = $stripeClient->createPaymentIntent([
            'amount' => $amountInCents,
            'currency' => 'brl',
            'description' => "Klube Cash - {$invoice['plano_nome']} ({$cycleLabel}) - Fatura #{$invoice['fatura_numero']}",
            'customer_email' => $invoice['email'],
            'metadata' => [
                'invoice_id' => (string) $invoice['fatura_id'],
                'invoice_number' => (string) $invoice['fatura_numero'],
                'subscription_id' => (string) $invoice['assinatura_id'],
                'store_id' => (string) $storeId,
                'store_name' => (string) $invoice['nome_fantasia'],
                'plan_name' => (string) $invoice['plano_nome'],
                'billing_cycle' => (string) $invoice['ciclo'],
            ],
        ]);

        if (empty($paymentIntent['id']) || empty($paymentIntent['client_secret'])) {
            throw new RuntimeException('A Stripe não retornou um Payment Intent completo.');
        }

        $updateStmt = $db->prepare("
            UPDATE faturas f
            INNER JOIN assinaturas a ON f.assinatura_id = a.id
            SET f.gateway = 'stripe',
                f.gateway_charge_id = ?,
                f.payment_method = 'card',
                f.updated_at = NOW()
            WHERE f.id = ?
              AND a.loja_id = ?
        ");
        $updateStmt->execute([(string) $paymentIntent['id'], $invoiceId, $storeId]);
        if ($updateStmt->rowCount() !== 1) {
            throw new RuntimeException('A fatura foi alterada durante a criação do pagamento.');
        }

        $db->commit();

        stripeJsonResponse([
            'success' => true,
            'payment_intent_id' => (string) $paymentIntent['id'],
            'client_secret' => (string) $paymentIntent['client_secret'],
            'status' => (string) $paymentIntent['status'],
            'amount' => (int) $paymentIntent['amount'],
            'currency' => (string) $paymentIntent['currency'],
        ], 201);
    } catch (Throwable $e) {
        if ($db instanceof PDO && $db->inTransaction()) {
            $db->rollBack();
        }

        error_log('Stripe create Payment Intent error: ' . $e->getMessage());
        stripeJsonResponse([
            'success' => false,
            'error' => 'Não foi possível iniciar o pagamento com cartão.',
        ], 502);
    }
}

function stripePaymentStatus(int $storeId): never
{
    stripeRequireConfiguration();
    $paymentIntentId = stripePaymentIntentId($_GET['payment_intent_id'] ?? null);

    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT f.id, f.status, f.paid_at
            FROM faturas f
            INNER JOIN assinaturas a ON f.assinatura_id = a.id
            WHERE f.gateway_charge_id = ?
              AND a.loja_id = ?
            LIMIT 1
        ");
        $stmt->execute([$paymentIntentId, $storeId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            stripeJsonResponse([
                'success' => false,
                'error' => 'Pagamento não encontrado.',
            ], 404);
        }

        $paymentIntent = (new StripePayClient())->getPaymentIntent($paymentIntentId);
        stripeJsonResponse([
            'success' => true,
            'payment_intent_id' => (string) $paymentIntent['id'],
            'status' => (string) $paymentIntent['status'],
            'amount' => (int) $paymentIntent['amount'],
            'currency' => (string) $paymentIntent['currency'],
            'invoice_status' => (string) $invoice['status'],
            'paid_at' => $invoice['paid_at'],
        ]);
    } catch (Throwable $e) {
        error_log('Stripe payment status error: ' . $e->getMessage());
        stripeJsonResponse([
            'success' => false,
            'error' => 'Não foi possível consultar o pagamento com cartão.',
        ], 502);
    }
}

function stripeCancelPayment(int $storeId): never
{
    stripeRequireConfiguration();
    $paymentIntentId = stripePaymentIntentId($_GET['payment_intent_id'] ?? null);

    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT f.id, f.status
            FROM faturas f
            INNER JOIN assinaturas a ON f.assinatura_id = a.id
            WHERE f.gateway_charge_id = ?
              AND a.loja_id = ?
            LIMIT 1
        ");
        $stmt->execute([$paymentIntentId, $storeId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            stripeJsonResponse([
                'success' => false,
                'error' => 'Pagamento não encontrado.',
            ], 404);
        }

        if ($invoice['status'] !== 'pending') {
            stripeJsonResponse([
                'success' => false,
                'error' => 'Apenas pagamentos pendentes podem ser cancelados.',
            ], 409);
        }

        $result = (new StripePayClient())->cancelPaymentIntent($paymentIntentId);

        $updateStmt = $db->prepare("
            UPDATE faturas f
            INNER JOIN assinaturas a ON f.assinatura_id = a.id
            SET f.status = 'canceled',
                f.updated_at = NOW()
            WHERE f.id = ?
              AND a.loja_id = ?
              AND f.status = 'pending'
        ");
        $updateStmt->execute([(int) $invoice['id'], $storeId]);

        if ($updateStmt->rowCount() !== 1) {
            stripeJsonResponse([
                'success' => false,
                'error' => 'O estado da fatura mudou durante o cancelamento.',
            ], 409);
        }

        stripeJsonResponse([
            'success' => true,
            'payment_intent_id' => (string) $result['id'],
            'status' => (string) $result['status'],
            'message' => 'Pagamento cancelado com sucesso.',
        ]);
    } catch (Throwable $e) {
        error_log('Stripe cancel Payment Intent error: ' . $e->getMessage());
        stripeJsonResponse([
            'success' => false,
            'error' => 'Não foi possível cancelar o pagamento com cartão.',
        ], 502);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$storeId = stripeRequireStoreId();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? '');

if ($method === 'POST' && $action === 'create_payment_intent') {
    stripeCreatePaymentIntent($storeId);
}

if ($method === 'GET' && $action === 'payment_status') {
    stripePaymentStatus($storeId);
}

if ($method === 'POST' && $action === 'cancel_payment') {
    stripeCancelPayment($storeId);
}

stripeJsonResponse([
    'success' => false,
    'error' => $method === 'GET' || $method === 'POST'
        ? 'Ação inválida.'
        : 'Método não permitido.',
], $method === 'GET' || $method === 'POST' ? 400 : 405);
