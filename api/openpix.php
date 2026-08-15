<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../controllers/AuthController.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * @param array<string, mixed> $payload
 */
function openPixJsonResponse(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function openPixRequireStoreId(): int
{
    if (!AuthController::hasStoreAccess()) {
        openPixJsonResponse([
            'status' => false,
            'message' => 'Autenticação necessária.',
        ], 401);
    }

    $storeId = (int) AuthController::getStoreId();
    if ($storeId <= 0) {
        openPixJsonResponse([
            'status' => false,
            'message' => 'A sessão não está associada a uma loja.',
        ], 403);
    }

    return $storeId;
}

/**
 * Executa uma chamada autenticada à API OpenPix. O AppID nunca é retornado
 * ao cliente nem incluído nos logs.
 *
 * @param array<string, mixed>|null $payload
 * @return array<string, mixed>
 */
function openPixRequest(string $method, string $path, ?array $payload = null): array
{
    if (!defined('OPENPIX_APP_ID') || trim((string) OPENPIX_APP_ID) === '') {
        throw new RuntimeException('OPENPIX_APP_ID não configurado.');
    }

    $baseUrl = rtrim((string) OPENPIX_API_URL, '/');
    $curl = curl_init($baseUrl . $path);
    if ($curl === false) {
        throw new RuntimeException('Não foi possível inicializar a conexão OpenPix.');
    }

    $headers = [
        'Accept: application/json',
        'Authorization: ' . OPENPIX_APP_ID,
        'Content-Type: application/json',
    ];

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    if ($payload !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    $rawResponse = curl_exec($curl);
    $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($rawResponse === false) {
        throw new RuntimeException('Falha de conexão com a OpenPix: ' . $curlError);
    }

    $decoded = json_decode((string) $rawResponse, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('A OpenPix retornou uma resposta inválida.');
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException('A OpenPix recusou a operação (HTTP ' . $httpCode . ').');
    }

    return $decoded;
}

/**
 * @return array<string, mixed>
 */
function openPixJsonInput(): array
{
    $decoded = json_decode((string) file_get_contents('php://input'), true);
    return is_array($decoded) ? $decoded : [];
}

function openPixCreateCharge(int $storeId, array $input): never
{
    $paymentId = filter_var($input['payment_id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    if ($paymentId === false) {
        openPixJsonResponse([
            'status' => false,
            'message' => 'payment_id obrigatório.',
        ], 400);
    }

    if (!defined('OPENPIX_APP_ID') || trim((string) OPENPIX_APP_ID) === '') {
        openPixJsonResponse([
            'status' => false,
            'message' => 'Integração OpenPix indisponível no momento.',
        ], 503);
    }

    $db = null;
    try {
        $db = Database::getConnection();
        $db->beginTransaction();

        $paymentStmt = $db->prepare("
            SELECT id, loja_id, valor_total
            FROM pagamentos_comissao
            WHERE id = ?
              AND loja_id = ?
              AND status = 'pendente'
            FOR UPDATE
        ");
        $paymentStmt->execute([(int) $paymentId, $storeId]);
        $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            $db->rollBack();
            openPixJsonResponse([
                'status' => false,
                'message' => 'Pagamento não encontrado ou já processado.',
            ], 404);
        }

        $valueInCents = (int) round((float) $payment['valor_total'] * 100);
        if ($valueInCents <= 0) {
            $db->rollBack();
            openPixJsonResponse([
                'status' => false,
                'message' => 'O pagamento possui valor inválido.',
            ], 422);
        }

        // Correlação determinística facilita idempotência e reconciliação de
        // uma cobrança que tenha sido criada no provedor antes de uma falha local.
        $correlationId = 'klubecash-payment-' . $storeId . '-' . (int) $payment['id'];
        $providerResponse = openPixRequest('POST', '/api/v1/charge', [
            'correlationID' => $correlationId,
            'value' => $valueInCents,
            'comment' => 'Comissão Klube Cash - Pagamento #' . (int) $payment['id'],
        ]);

        $charge = isset($providerResponse['charge']) && is_array($providerResponse['charge'])
            ? $providerResponse['charge']
            : $providerResponse;
        $qrCode = (string) ($charge['brCode'] ?? $providerResponse['brCode'] ?? '');
        $qrCodeImage = (string) ($charge['qrCodeImage'] ?? $providerResponse['qrCodeImage'] ?? '');
        $providerChargeId = (string) (
            $charge['globalID']
            ?? $charge['correlationID']
            ?? $providerResponse['correlationID']
            ?? $correlationId
        );

        if ($qrCode === '' || $qrCodeImage === '') {
            throw new RuntimeException('A OpenPix não retornou os dados completos do QR Code.');
        }

        $updateStmt = $db->prepare("
            UPDATE pagamentos_comissao
            SET openpix_charge_id = ?,
                openpix_qr_code = ?,
                openpix_qr_code_image = ?,
                openpix_correlation_id = ?,
                openpix_status = ?,
                metodo_pagamento = 'pix_openpix',
                status = 'pix_aguardando'
            WHERE id = ?
              AND loja_id = ?
              AND status = 'pendente'
        ");
        $updateStmt->execute([
            $providerChargeId,
            $qrCode,
            $qrCodeImage,
            $correlationId,
            strtoupper((string) ($charge['status'] ?? 'ACTIVE')),
            (int) $payment['id'],
            $storeId,
        ]);

        if ($updateStmt->rowCount() !== 1) {
            throw new RuntimeException('A cobrança foi alterada durante a geração do PIX.');
        }

        $db->commit();

        openPixJsonResponse([
            'status' => true,
            'data' => [
                // A correlação é aceita pelo endpoint de consulta da OpenPix e
                // é o identificador que o frontend deve devolver no polling.
                'charge_id' => $correlationId,
                'qr_code' => $qrCode,
                'qr_code_image' => $qrCodeImage,
                'status' => strtoupper((string) ($charge['status'] ?? 'ACTIVE')),
            ],
        ], 201);
    } catch (Throwable $e) {
        if ($db instanceof PDO && $db->inTransaction()) {
            $db->rollBack();
        }

        error_log('OpenPix create charge error: ' . $e->getMessage());
        openPixJsonResponse([
            'status' => false,
            'message' => 'Não foi possível gerar a cobrança OpenPix. Tente novamente.',
        ], 502);
    }
}

function openPixCheckStatus(int $storeId, array $input): never
{
    $requestedChargeId = trim((string) ($input['charge_id'] ?? ''));
    if ($requestedChargeId === '' || strlen($requestedChargeId) > 255) {
        openPixJsonResponse([
            'status' => false,
            'message' => 'charge_id obrigatório.',
        ], 400);
    }

    if (!defined('OPENPIX_APP_ID') || trim((string) OPENPIX_APP_ID) === '') {
        openPixJsonResponse([
            'status' => false,
            'message' => 'Integração OpenPix indisponível no momento.',
        ], 503);
    }

    try {
        $db = Database::getConnection();
        $paymentStmt = $db->prepare("
            SELECT id, openpix_correlation_id, openpix_charge_id
            FROM pagamentos_comissao
            WHERE loja_id = ?
              AND (openpix_correlation_id = ? OR openpix_charge_id = ?)
            LIMIT 1
        ");
        $paymentStmt->execute([$storeId, $requestedChargeId, $requestedChargeId]);
        $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            openPixJsonResponse([
                'status' => false,
                'message' => 'Cobrança não encontrada.',
            ], 404);
        }

        $providerLookupId = (string) ($payment['openpix_correlation_id'] ?: $payment['openpix_charge_id']);
        $providerResponse = openPixRequest('GET', '/api/v1/charge/' . rawurlencode($providerLookupId));
        $charge = isset($providerResponse['charge']) && is_array($providerResponse['charge'])
            ? $providerResponse['charge']
            : $providerResponse;
        $status = strtoupper((string) ($charge['status'] ?? 'UNKNOWN'));

        $updateStmt = $db->prepare("
            UPDATE pagamentos_comissao
            SET openpix_status = ?,
                openpix_paid_at = CASE
                    WHEN ? = 'COMPLETED' THEN COALESCE(openpix_paid_at, NOW())
                    ELSE openpix_paid_at
                END
            WHERE id = ?
              AND loja_id = ?
        ");
        $updateStmt->execute([$status, $status, (int) $payment['id'], $storeId]);

        openPixJsonResponse([
            'success' => true,
            'status' => true,
            'data' => [
                'status' => $status,
            ],
        ]);
    } catch (Throwable $e) {
        error_log('OpenPix check status error: ' . $e->getMessage());
        openPixJsonResponse([
            'status' => false,
            'message' => 'Não foi possível consultar a cobrança OpenPix.',
        ], 502);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    openPixJsonResponse([
        'status' => false,
        'message' => 'Método não permitido.',
    ], 405);
}

$storeId = openPixRequireStoreId();
$input = openPixJsonInput();
$action = (string) ($input['action'] ?? '');

if ($action === 'create_charge') {
    openPixCreateCharge($storeId, $input);
}

if ($action === 'check_status') {
    openPixCheckStatus($storeId, $input);
}

openPixJsonResponse([
    'status' => false,
    'message' => 'Ação inválida.',
], 400);
