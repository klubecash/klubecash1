<?php
/**
 * API Abacate Pay - Gerenciamento de Pagamentos PIX para Assinaturas
 *
 * Endpoints:
 * - POST ?action=create_invoice_pix&invoice_id=X
 * - GET  ?action=status&charge_id=X
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../utils/AbacatePayClient.php';

// Iniciar sessão se necessário
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Função para resposta JSON
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Verificar método HTTP
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $db = (new Database())->getConnection();
    $abacateClient = new AbacatePayClient();

    // =====================================================
    // POST: Criar PIX para uma fatura
    // =====================================================
    if ($method === 'POST' && $action === 'create_invoice_pix') {
        // Verificar autenticação
        if (!isset($_SESSION['user_id'])) {
            jsonResponse(['success' => false, 'message' => 'Não autenticado'], 401);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $invoiceId = $input['invoice_id'] ?? $_GET['invoice_id'] ?? null;

        if (!$invoiceId) {
            jsonResponse(['success' => false, 'message' => 'invoice_id obrigatório'], 400);
        }

        // Buscar fatura com dados completos
        $sql = "SELECT f.*, a.loja_id, l.nome_loja, l.email, l.cpf_cnpj, l.telefone
                FROM faturas f
                JOIN assinaturas a ON f.assinatura_id = a.id
                JOIN lojas l ON a.loja_id = l.id
                WHERE f.id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$invoiceId]);
        $fatura = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fatura) {
            jsonResponse(['success' => false, 'message' => 'Fatura não encontrada'], 404);
        }

        // Verificar se já existe PIX gerado
        if (!empty($fatura['gateway_charge_id']) && !empty($fatura['pix_qr_code'])) {
            jsonResponse([
                'success' => true,
                'message' => 'PIX já gerado anteriormente',
                'pix' => [
                    'qr_code' => $fatura['pix_qr_code'],
                    'copia_cola' => $fatura['pix_copia_cola'],
                    'expires_at' => $fatura['pix_expires_at']
                ]
            ]);
        }

        // Preparar payload para Abacate Pay
        $amountInCents = (int)($fatura['amount'] * 100); // Converter para centavos

        $payload = [
            'amount' => $amountInCents,
            'description' => "Assinatura Klube Cash - Fatura {$fatura['numero']}",
            'reference_id' => $fatura['numero'],
            'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
            'customer' => [
                'name' => $fatura['nome_loja'],
                'email' => $fatura['email'],
                'cpf_cnpj' => $fatura['cpf_cnpj'] ?? ''
            ]
        ];

        // Criar cobrança no Abacate Pay
        $pixData = $abacateClient->createPixCharge($payload);

        // Atualizar fatura com dados do PIX
        $sqlUpdate = "UPDATE faturas SET
                      gateway_charge_id = ?,
                      pix_qr_code = ?,
                      pix_copia_cola = ?,
                      pix_expires_at = ?,
                      updated_at = NOW()
                      WHERE id = ?";
        $stmtUpdate = $db->prepare($sqlUpdate);
        $stmtUpdate->execute([
            $pixData['gateway_charge_id'],
            $pixData['qr_code_base64'],
            $pixData['copia_cola'],
            $pixData['expires_at'],
            $invoiceId
        ]);

        jsonResponse([
            'success' => true,
            'message' => 'PIX gerado com sucesso',
            'pix' => [
                'qr_code' => $pixData['qr_code_base64'],
                'copia_cola' => $pixData['copia_cola'],
                'expires_at' => $pixData['expires_at'],
                'amount' => $fatura['amount']
            ]
        ]);
    }

    // =====================================================
    // GET: Consultar status de cobrança
    // =====================================================
    elseif ($method === 'GET' && $action === 'status') {
        $chargeId = $_GET['charge_id'] ?? null;

        if (!$chargeId) {
            jsonResponse(['success' => false, 'message' => 'charge_id obrigatório'], 400);
        }

        // Consultar status no Abacate Pay
        $statusData = $abacateClient->getChargeStatus($chargeId);

        // Atualizar fatura se estiver paga
        if ($statusData['status'] === 'paid' && $statusData['paid_at']) {
            $sqlUpdate = "UPDATE faturas SET
                          status = 'paid',
                          paid_at = ?,
                          updated_at = NOW()
                          WHERE gateway_charge_id = ? AND status = 'pending'";
            $stmtUpdate = $db->prepare($sqlUpdate);
            $stmtUpdate->execute([$statusData['paid_at'], $chargeId]);

            // Se atualizou alguma linha, processar avanço de período
            if ($stmtUpdate->rowCount() > 0) {
                require_once __DIR__ . '/../controllers/SubscriptionController.php';
                $subscriptionController = new SubscriptionController($db);

                // Buscar ID da fatura
                $sqlFatura = "SELECT id FROM faturas WHERE gateway_charge_id = ?";
                $stmtFatura = $db->prepare($sqlFatura);
                $stmtFatura->execute([$chargeId]);
                $faturaId = $stmtFatura->fetchColumn();

                if ($faturaId) {
                    $subscriptionController->advancePeriodOnPaid($faturaId);
                }
            }
        }

        jsonResponse([
            'success' => true,
            'status' => $statusData['status'],
            'paid_at' => $statusData['paid_at'],
            'data' => $statusData
        ]);
    }

    // =====================================================
    // Ação inválida
    // =====================================================
    else {
        jsonResponse([
            'success' => false,
            'message' => 'Ação inválida ou método não permitido',
            'available_actions' => ['create_invoice_pix', 'status']
        ], 400);
    }

} catch (Exception $e) {
    error_log("Erro AbacatePay API: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => 'Erro ao processar requisição',
        'error' => $e->getMessage()
    ], 500);
}
