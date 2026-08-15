<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../utils/AbacatePayClient.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * @param array<string, mixed> $payload
 */
function abacateJsonResponse(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function abacateRequireStoreId(): int
{
    if (!AuthController::hasStoreAccess()) {
        abacateJsonResponse([
            'success' => false,
            'message' => 'Autenticação necessária.',
        ], 401);
    }

    $storeId = (int) AuthController::getStoreId();
    if ($storeId <= 0) {
        abacateJsonResponse([
            'success' => false,
            'message' => 'A sessão não está associada a uma loja.',
        ], 403);
    }

    return $storeId;
}

/**
 * @return array<string, mixed>
 */
function abacateJsonInput(): array
{
    $decoded = json_decode((string) file_get_contents('php://input'), true);
    return is_array($decoded) ? $decoded : [];
}

function abacateInvoiceId(mixed $value): int
{
    $invoiceId = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($invoiceId === false) {
        abacateJsonResponse([
            'success' => false,
            'message' => 'invoice_id obrigatório.',
        ], 400);
    }

    return (int) $invoiceId;
}

function abacateCreateInvoicePix(int $storeId): never
{
    if (!defined('ABACATE_API_KEY') || trim((string) ABACATE_API_KEY) === '') {
        abacateJsonResponse([
            'success' => false,
            'message' => 'Integração PIX indisponível no momento.',
        ], 503);
    }

    $input = abacateJsonInput();
    $invoiceId = abacateInvoiceId($input['invoice_id'] ?? $_GET['invoice_id'] ?? null);
    $db = null;

    try {
        $db = Database::getConnection();
        $db->beginTransaction();

        $stmt = $db->prepare("
            SELECT f.*, a.loja_id,
                   l.nome_fantasia, l.razao_social, l.email, l.cnpj, l.telefone
            FROM faturas f
            INNER JOIN assinaturas a ON f.assinatura_id = a.id
            INNER JOIN lojas l ON a.loja_id = l.id
            WHERE f.id = ?
              AND a.loja_id = ?
            FOR UPDATE
        ");
        $stmt->execute([$invoiceId, $storeId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            $db->rollBack();
            abacateJsonResponse([
                'success' => false,
                'message' => 'Fatura não encontrada.',
            ], 404);
        }

        if (in_array((string) $invoice['status'], ['paid', 'canceled', 'refunded'], true)) {
            $db->rollBack();
            abacateJsonResponse([
                'success' => false,
                'message' => 'Esta fatura não pode receber um novo pagamento.',
            ], 409);
        }

        if (!empty($invoice['gateway_charge_id']) && !empty($invoice['pix_qr_code'])) {
            $db->commit();
            abacateJsonResponse([
                'success' => true,
                'message' => 'PIX já gerado anteriormente.',
                'pix' => [
                    'qr_code' => $invoice['pix_qr_code'],
                    'copia_cola' => $invoice['pix_copia_cola'],
                    'expires_at' => $invoice['pix_expires_at'],
                    'amount' => $invoice['amount'],
                ],
            ]);
        }

        $amountInCents = (int) round((float) $invoice['amount'] * 100);
        if ($amountInCents <= 0) {
            $db->rollBack();
            abacateJsonResponse([
                'success' => false,
                'message' => 'A fatura possui valor inválido.',
            ], 422);
        }

        $document = preg_replace('/[^0-9]/', '', (string) ($invoice['cnpj'] ?? ''));
        $payload = [
            'amount' => $amountInCents,
            'description' => 'Assinatura Klube Cash - Fatura ' . $invoice['numero'],
            'reference_id' => (string) $invoice['numero'],
            'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
            'customer' => [
                'name' => (string) ($invoice['nome_fantasia'] ?: $invoice['razao_social']),
                'email' => (string) $invoice['email'],
                'phone' => (string) ($invoice['telefone'] ?? ''),
                'cpf_cnpj' => $document,
            ],
        ];

        $pixData = (new AbacatePayClient())->createPixCharge($payload);
        if (
            empty($pixData['gateway_charge_id'])
            || empty($pixData['qr_code_base64'])
            || empty($pixData['copia_cola'])
        ) {
            throw new RuntimeException('Abacate Pay não retornou os dados completos do PIX.');
        }

        $updateStmt = $db->prepare("
            UPDATE faturas f
            INNER JOIN assinaturas a ON f.assinatura_id = a.id
            SET f.gateway = 'abacate',
                f.payment_method = 'pix',
                f.gateway_charge_id = ?,
                f.pix_qr_code = ?,
                f.pix_copia_cola = ?,
                f.pix_expires_at = ?,
                f.updated_at = NOW()
            WHERE f.id = ?
              AND a.loja_id = ?
        ");
        $updateStmt->execute([
            (string) $pixData['gateway_charge_id'],
            (string) $pixData['qr_code_base64'],
            (string) $pixData['copia_cola'],
            (string) $pixData['expires_at'],
            $invoiceId,
            $storeId,
        ]);

        if ($updateStmt->rowCount() !== 1) {
            throw new RuntimeException('A fatura foi alterada durante a geração do PIX.');
        }

        $db->commit();

        abacateJsonResponse([
            'success' => true,
            'message' => 'PIX gerado com sucesso.',
            'pix' => [
                'qr_code' => (string) $pixData['qr_code_base64'],
                'copia_cola' => (string) $pixData['copia_cola'],
                'expires_at' => (string) $pixData['expires_at'],
                'amount' => $invoice['amount'],
            ],
        ], 201);
    } catch (Throwable $e) {
        if ($db instanceof PDO && $db->inTransaction()) {
            $db->rollBack();
        }

        error_log('AbacatePay create invoice PIX error: ' . $e->getMessage());
        abacateJsonResponse([
            'success' => false,
            'message' => 'Não foi possível gerar o PIX. Tente novamente.',
        ], 502);
    }
}

function abacateStatus(int $storeId): never
{
    $chargeId = trim((string) ($_GET['charge_id'] ?? ''));
    if ($chargeId === '' || strlen($chargeId) > 255) {
        abacateJsonResponse([
            'success' => false,
            'message' => 'charge_id obrigatório.',
        ], 400);
    }

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
        $stmt->execute([$chargeId, $storeId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            abacateJsonResponse([
                'success' => false,
                'message' => 'Fatura não encontrada.',
            ], 404);
        }

        abacateJsonResponse([
            'success' => true,
            'status' => (string) $invoice['status'],
            'paid_at' => $invoice['paid_at'],
            'data' => [
                'status' => (string) $invoice['status'],
                'paid_at' => $invoice['paid_at'],
            ],
        ]);
    } catch (Throwable $e) {
        error_log('AbacatePay status error: ' . $e->getMessage());
        abacateJsonResponse([
            'success' => false,
            'message' => 'Não foi possível consultar a cobrança.',
        ], 500);
    }
}

function abacateCheckPayment(int $storeId): never
{
    $invoiceId = abacateInvoiceId($_GET['invoice_id'] ?? null);

    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT f.id, f.status, f.paid_at
            FROM faturas f
            INNER JOIN assinaturas a ON f.assinatura_id = a.id
            WHERE f.id = ?
              AND a.loja_id = ?
            LIMIT 1
        ");
        $stmt->execute([$invoiceId, $storeId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            abacateJsonResponse([
                'success' => false,
                'message' => 'Fatura não encontrada.',
            ], 404);
        }

        abacateJsonResponse([
            'success' => true,
            'status' => (string) $invoice['status'],
            'paid_at' => $invoice['paid_at'],
            'is_paid' => $invoice['status'] === 'paid',
        ]);
    } catch (Throwable $e) {
        error_log('AbacatePay check payment error: ' . $e->getMessage());
        abacateJsonResponse([
            'success' => false,
            'message' => 'Não foi possível consultar a fatura.',
        ], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$storeId = abacateRequireStoreId();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? '');

if ($method === 'POST' && $action === 'create_invoice_pix') {
    abacateCreateInvoicePix($storeId);
}

if ($method === 'GET' && $action === 'status') {
    abacateStatus($storeId);
}

if ($method === 'GET' && $action === 'check_payment') {
    abacateCheckPayment($storeId);
}

abacateJsonResponse([
    'success' => false,
    'message' => $method === 'GET' || $method === 'POST'
        ? 'Ação inválida.'
        : 'Método não permitido.',
], $method === 'GET' || $method === 'POST' ? 400 : 405);
