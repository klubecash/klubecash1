<?php
// api/openpix.php

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/OpenPixController.php';

// Verificar autenticação
if (!AuthController::isAuthenticated() || !AuthController::isStore()) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Acesso negado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

switch ($action) {
    case 'create_charge':
        handleCreateCharge($input);
        break;
    case 'check_status':
        handleCheckStatus($input);
        break;
    default:
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Ação inválida']);
}

function handleCreateCharge($input) {
    try {
        $paymentId = $input['payment_id'] ?? 0;
        
        if (!$paymentId) {
            echo json_encode(['status' => false, 'message' => 'ID do pagamento é obrigatório']);
            return;
        }
        
        $db = Database::getConnection();
        
        // Buscar dados do pagamento
        $stmt = $db->prepare("
            SELECT * FROM pagamentos_comissao 
            WHERE id = ? AND status = 'pendente'
        ");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment) {
            echo json_encode(['status' => false, 'message' => 'Pagamento não encontrado']);
            return;
        }
        
        // Criar correlationID único
        $correlationID = "payment_{$paymentId}_" . time() . "_openpix";
        
        // Criar cobrança na OpenPix
        $result = OpenPixController::createCharge(
            $payment['id'],
            $payment['valor_total'],
            $correlationID
        );
        
        if ($result['success']) {
            // Salvar dados da cobrança no banco
            $updateStmt = $db->prepare("
                UPDATE pagamentos_comissao 
                SET openpix_charge_id = ?,
                    openpix_qr_code = ?,
                    openpix_qr_code_image = ?,
                    openpix_correlation_id = ?,
                    metodo_pagamento = 'pix_openpix',
                    status = 'openpix_aguardando'
                WHERE id = ?
            ");
            $updateStmt->execute([
                $result['data']['charge_id'],
                $result['data']['qr_code'],
                $result['data']['qr_code_image'],
                $correlationID,
                $paymentId
            ]);
            
            echo json_encode([
                'status' => true,
                'data' => $result['data']
            ]);
        } else {
            echo json_encode([
                'status' => false,
                'message' => $result['message']
            ]);
        }
        
    } catch (Exception $e) {
        error_log("API OpenPix createCharge error: " . $e->getMessage());
        echo json_encode(['status' => false, 'message' => 'Erro interno']);
    }
}

function handleCheckStatus($input) {
    try {
        $chargeId = $input['charge_id'] ?? '';
        
        if (!$chargeId) {
            echo json_encode(['status' => false, 'message' => 'ID da cobrança é obrigatório']);
            return;
        }
        
        $result = OpenPixController::getChargeStatus($chargeId);
        echo json_encode($result);
        
    } catch (Exception $e) {
        error_log("API OpenPix checkStatus error: " . $e->getMessage());
        echo json_encode(['status' => false, 'message' => 'Erro interno']);
    }
}
?>