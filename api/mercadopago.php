<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../utils/MercadoPagoClient.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * @param array<string, mixed> $payload
 */
function mercadoPagoJsonResponse(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function mercadoPagoRequireStoreId(): int
{
    if (!AuthController::hasStoreAccess()) {
        mercadoPagoJsonResponse([
            'status' => false,
            'message' => 'Autenticação necessária.',
        ], 401);
    }

    $storeId = (int) AuthController::getStoreId();
    if ($storeId <= 0) {
        mercadoPagoJsonResponse([
            'status' => false,
            'message' => 'A sessão não está associada a uma loja.',
        ], 403);
    }

    return $storeId;
}

/**
 * @return array<string, mixed>
 */
function mercadoPagoJsonInput(): array
{
    $decoded = json_decode((string) file_get_contents('php://input'), true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * @param array<string, mixed> $payment
 * @return array<string, mixed>
 */
function mercadoPagoBuildPaymentData(array $payment, array $input): array
{
    $document = preg_replace('/\D/', '', (string) ($payment['loja_proprietario_cpf'] ?? ''));
    if (!in_array(strlen($document), [11, 14], true)) {
        $document = preg_replace('/\D/', '', (string) ($payment['cnpj'] ?? ''));
    }
    if (!in_array(strlen($document), [11, 14], true)) {
        $document = '';
    }

    $payerName = trim((string) ($payment['loja_proprietario_nome'] ?? $payment['nome_fantasia'] ?? ''));
    $payerEmail = trim((string) ($payment['loja_proprietario_email'] ?? $payment['email'] ?? ''));
    $payerPhone = trim((string) ($payment['loja_proprietario_telefone'] ?? $payment['telefone'] ?? ''));

    $clientDeviceId = preg_replace('/[^a-zA-Z0-9_.:-]/', '', (string) ($input['device_id'] ?? ''));
    $deviceId = $clientDeviceId !== ''
        ? substr($clientDeviceId, 0, 128)
        : 'server_' . hash('sha256', $payment['loja_id'] . ':' . $payment['id']);

    $data = [
        'amount' => (float) $payment['valor_total'],
        'description' => 'Comissão Klube Cash - Pagamento #' . $payment['id'],
        'external_reference' => 'payment_' . $payment['id'],
        'payment_id' => (int) $payment['id'],
        'store_id' => (int) $payment['loja_id'],
        'idempotency_key' => MercadoPagoClient::buildPaymentIdempotencyKey(
            (int) $payment['loja_id'],
            (int) $payment['id']
        ),
        'device_id' => $deviceId,
        'payer_email' => $payerEmail,
        'payer_name' => $payerName,
        'payer_lastname' => $payerName,
        'payer_phone' => $payerPhone,
        'payer_cpf' => $document,
        'payer_registration_date' => date('Y-m-d\TH:i:s', strtotime('-1 year')),
        'item_id' => 'COMISSAO_KC_' . $payment['id'],
        'item_title' => 'Comissão Klube Cash',
        'item_description' => 'Pagamento de comissão para liberação de cashback aos clientes',
        'item_category' => 'services',
    ];

    $address = array_filter([
        'zip_code' => preg_replace('/\D/', '', (string) ($payment['cep'] ?? '')),
        'street_name' => trim((string) ($payment['logradouro'] ?? '')),
        'street_number' => (int) ($payment['numero'] ?? 0),
        'neighborhood' => trim((string) ($payment['bairro'] ?? '')),
        'city' => trim((string) ($payment['cidade'] ?? '')),
        'federal_unit' => strtoupper(trim((string) ($payment['estado'] ?? ''))),
    ], static fn ($value): bool => $value !== '' && $value !== 0);

    if ($address !== []) {
        $data['payer_address'] = $address;
    }

    return $data;
}

function mercadoPagoCreatePayment(int $storeId): never
{
    if (!defined('MP_ACCESS_TOKEN') || trim((string) MP_ACCESS_TOKEN) === '') {
        mercadoPagoJsonResponse([
            'status' => false,
            'message' => 'Integração Mercado Pago indisponível no momento.',
        ], 503);
    }

    $input = mercadoPagoJsonInput();
    $paymentId = filter_var($input['payment_id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    if ($paymentId === false) {
        mercadoPagoJsonResponse([
            'status' => false,
            'message' => 'payment_id obrigatório.',
        ], 400);
    }

    $db = null;
    try {
        $db = Database::getConnection();
        $db->beginTransaction();

        $stmt = $db->prepare("
            SELECT p.*, l.nome_fantasia, l.email, l.telefone, l.cnpj,
                   le.cep, le.logradouro, le.numero, le.bairro, le.cidade, le.estado,
                   u.nome AS loja_proprietario_nome,
                   u.telefone AS loja_proprietario_telefone,
                   u.cpf AS loja_proprietario_cpf,
                   u.email AS loja_proprietario_email
            FROM pagamentos_comissao p
            INNER JOIN lojas l ON p.loja_id = l.id
            LEFT JOIN lojas_endereco le ON l.id = le.loja_id
            LEFT JOIN usuarios u ON l.usuario_id = u.id
            WHERE p.id = ?
              AND p.loja_id = ?
              AND p.status = 'pendente'
            FOR UPDATE
        ");
        $stmt->execute([(int) $paymentId, $storeId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            $db->rollBack();
            mercadoPagoJsonResponse([
                'status' => false,
                'message' => 'Pagamento não encontrado ou já processado.',
            ], 404);
        }

        if ((float) $payment['valor_total'] <= 0) {
            $db->rollBack();
            mercadoPagoJsonResponse([
                'status' => false,
                'message' => 'O pagamento possui valor inválido.',
            ], 422);
        }

        $mpClient = new MercadoPagoClient();
        $response = $mpClient->createPixPayment(mercadoPagoBuildPaymentData($payment, $input));
        $mpPayment = $response['data'] ?? null;

        if (
            empty($response['status'])
            || !is_array($mpPayment)
            || empty($mpPayment['mp_payment_id'])
            || empty($mpPayment['qr_code'])
            || empty($mpPayment['qr_code_base64'])
        ) {
            throw new RuntimeException('Mercado Pago não retornou uma cobrança PIX completa.');
        }

        $updateStmt = $db->prepare("
            UPDATE pagamentos_comissao
            SET mp_payment_id = ?,
                mp_qr_code = ?,
                mp_qr_code_base64 = ?,
                metodo_pagamento = 'pix_mercadopago',
                status = 'pix_aguardando'
            WHERE id = ?
              AND loja_id = ?
              AND status = 'pendente'
        ");
        $updateStmt->execute([
            (string) $mpPayment['mp_payment_id'],
            (string) $mpPayment['qr_code'],
            (string) $mpPayment['qr_code_base64'],
            (int) $payment['id'],
            $storeId,
        ]);

        if ($updateStmt->rowCount() !== 1) {
            throw new RuntimeException('A cobrança foi alterada durante a geração do PIX.');
        }

        $db->commit();

        mercadoPagoJsonResponse([
            'status' => true,
            'data' => [
                'mp_payment_id' => (string) $mpPayment['mp_payment_id'],
                'qr_code' => (string) $mpPayment['qr_code'],
                'qr_code_base64' => (string) $mpPayment['qr_code_base64'],
                'status' => (string) ($mpPayment['status'] ?? 'pending'),
            ],
        ], 201);
    } catch (Throwable $e) {
        if ($db instanceof PDO && $db->inTransaction()) {
            $db->rollBack();
        }

        error_log('Mercado Pago create payment error: ' . $e->getMessage());
        mercadoPagoJsonResponse([
            'status' => false,
            'message' => 'Não foi possível gerar o PIX. Tente novamente.',
        ], 502);
    }
}

function mercadoPagoCheckStatus(int $storeId): never
{
    $mpPaymentId = trim((string) ($_GET['mp_payment_id'] ?? ''));
    if ($mpPaymentId === '' || strlen($mpPaymentId) > 100) {
        mercadoPagoJsonResponse([
            'status' => false,
            'message' => 'mp_payment_id obrigatório.',
        ], 400);
    }

    if (!defined('MP_ACCESS_TOKEN') || trim((string) MP_ACCESS_TOKEN) === '') {
        mercadoPagoJsonResponse([
            'status' => false,
            'message' => 'Integração Mercado Pago indisponível no momento.',
        ], 503);
    }

    try {
        $db = Database::getConnection();
        $ownershipStmt = $db->prepare("
            SELECT id
            FROM pagamentos_comissao
            WHERE mp_payment_id = ?
              AND loja_id = ?
            LIMIT 1
        ");
        $ownershipStmt->execute([$mpPaymentId, $storeId]);
        if (!$ownershipStmt->fetchColumn()) {
            mercadoPagoJsonResponse([
                'status' => false,
                'message' => 'Pagamento não encontrado.',
            ], 404);
        }

        $response = (new MercadoPagoClient())->getPaymentStatus($mpPaymentId);
        if (empty($response['status'])) {
            throw new RuntimeException('Falha ao consultar a cobrança no Mercado Pago.');
        }

        mercadoPagoJsonResponse($response);
    } catch (Throwable $e) {
        error_log('Mercado Pago payment status error: ' . $e->getMessage());
        mercadoPagoJsonResponse([
            'status' => false,
            'message' => 'Não foi possível consultar o pagamento.',
        ], 502);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$storeId = mercadoPagoRequireStoreId();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? '');

if ($method === 'POST' && $action === 'create_payment') {
    mercadoPagoCreatePayment($storeId);
}

if ($method === 'GET' && $action === 'status') {
    mercadoPagoCheckStatus($storeId);
}

if ($action === 'test') {
    mercadoPagoJsonResponse([
        'status' => false,
        'message' => 'Recurso não encontrado.',
    ], 404);
}

mercadoPagoJsonResponse([
    'status' => false,
    'message' => $method === 'GET' || $method === 'POST'
        ? 'Ação inválida.'
        : 'Método não permitido.',
], $method === 'GET' || $method === 'POST' ? 400 : 405);
