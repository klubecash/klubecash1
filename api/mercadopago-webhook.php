<?php
// api/mercadopago-webhook.php
header('Content-Type: application/json');

// Log para debug - registra tudo que chega do Mercado Pago
$input_raw = file_get_contents('php://input');
$headers = getallheaders();

error_log("=== WEBHOOK MP RECEBIDO ===");
error_log("Timestamp: " . date('Y-m-d H:i:s'));
error_log("Payload: " . $input_raw);
error_log("Headers: " . print_r($headers, true));

// Notificações processadas retornam 200; falhas financeiras retornam 503 para nova tentativa.
http_response_code(200);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../controllers/TransactionController.php';

// Verificar se o arquivo MercadoPagoClient existe
if (!file_exists(__DIR__ . '/../utils/MercadoPagoClient.php')) {
    error_log("WEBHOOK: MercadoPagoClient não encontrado, usando método alternativo");
    
    // Função alternativa para buscar status do pagamento
    function getPaymentStatusAlternative($mpPaymentId) {
        $url = "https://api.mercadopago.com/v1/payments/{$mpPaymentId}";
        $headers = [
            'Authorization: Bearer ' . MP_ACCESS_TOKEN,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return ['status' => true, 'data' => json_decode($response, true)];
        } else {
            return ['status' => false, 'message' => 'Erro ao consultar MP'];
        }
    }
} else {
    require_once __DIR__ . '/../utils/MercadoPagoClient.php';
}

$input = json_decode($input_raw, true);

// Validação básica do payload
if (!$input) {
    error_log("WEBHOOK: Payload JSON inválido");
    echo json_encode(['status' => 'ok', 'message' => 'Webhook recebido - JSON inválido']);
    exit;
}

// Se não tem o ID, é apenas um teste do MP
if (!isset($input['data']['id'])) {
    error_log("WEBHOOK: Teste do MP ou payload sem ID");
    echo json_encode(['status' => 'ok', 'message' => 'Webhook recebido - teste']);
    exit;
}

try {
    $mpPaymentId = $input['data']['id'];
    $action = $input['action'] ?? '';
    
    error_log("WEBHOOK: Processando Payment ID: {$mpPaymentId}, Action: {$action}");
    
    // Processar apenas pagamentos
    if ($action === 'payment.updated' || $action === 'payment.created') {
        
        // Buscar status atual no Mercado Pago
        if (class_exists('MercadoPagoClient')) {
            $mpClient = new MercadoPagoClient();
            $paymentResponse = $mpClient->getPaymentStatus($mpPaymentId);
        } else {
            $paymentResponse = getPaymentStatusAlternative($mpPaymentId);
        }
        
        error_log("WEBHOOK: Resposta do MP: " . print_r($paymentResponse, true));
        
        if ($paymentResponse['status'] && isset($paymentResponse['data']['status'])) {
            $mpStatus = $paymentResponse['data']['status'];
            $mpStatusDetail = $paymentResponse['data']['status_detail'] ?? '';
            
            error_log("WEBHOOK: Status do pagamento no MP: {$mpStatus} - {$mpStatusDetail}");
            
            if ($mpStatus === 'approved') {
                $db = Database::getConnection();
                
                // Buscar pagamento na nossa base pelo mp_payment_id
                $stmt = $db->prepare("
                    SELECT p.*, l.nome_fantasia as loja_nome 
                    FROM pagamentos_comissao p
                    LEFT JOIN lojas l ON p.loja_id = l.id
                    WHERE p.mp_payment_id = ?
                ");
                $stmt->execute([$mpPaymentId]);
                $payment = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$payment) {
                    error_log("WEBHOOK: Pagamento não encontrado na base local para MP ID: {$mpPaymentId}");
                    echo json_encode(['status' => 'ok', 'message' => 'Pagamento não encontrado']);
                    exit;
                }
                
                error_log("WEBHOOK: Pagamento encontrado - ID: {$payment['id']}, Status atual: {$payment['status']}");
                
                // Verificar se o pagamento ainda precisa ser processado
                if (in_array($payment['status'], ['pendente', 'pix_aguardando'], true)) {
                    $result = TransactionController::approvePaymentAutomatically(
                        (int) $payment['id'],
                        'Pagamento PIX aprovado via Mercado Pago - ID MP: ' . $mpPaymentId
                    );
                    if (!$result['status']) {
                        $check = $db->prepare('SELECT status FROM pagamentos_comissao WHERE id=?');
                        $check->execute([$payment['id']]);
                        if ($check->fetchColumn() !== 'aprovado') {
                            throw new RuntimeException('A aprovação financeira não foi concluída; tentar novamente.');
                        }
                    }
                    $db->prepare("UPDATE pagamentos_comissao SET mp_status='approved',pix_paid_at=COALESCE(pix_paid_at,NOW()) WHERE id=?")->execute([$payment['id']]);
                }
                
            } elseif ($mpStatus === 'rejected' || $mpStatus === 'cancelled') {
                
                // Lidar com pagamentos rejeitados/cancelados
                error_log("WEBHOOK: Pagamento rejeitado/cancelado no MP: {$mpStatus}");
                
                $db = Database::getConnection();
                $updateStmt = $db->prepare("
                    UPDATE pagamentos_comissao 
                    SET mp_status = ?,
                        observacao_admin = CONCAT(COALESCE(observacao_admin, ''), ' - PIX rejeitado/cancelado no MP: {$mpStatus}')
                    WHERE mp_payment_id = ?
                ");
                $updateStmt->execute([$mpStatus, $mpPaymentId]);
                
            } else {
                error_log("WEBHOOK: Status do pagamento não requer ação: {$mpStatus}");
            }
            
        } else {
            error_log("WEBHOOK: Erro ao consultar status no MP: " . ($paymentResponse['message'] ?? 'Erro desconhecido'));
        }
    }
    
    echo json_encode(['status' => 'ok', 'message' => 'Webhook processado com sucesso']);
    
} catch (Exception $e) {
    // Registra a falha e solicita nova tentativa ao Mercado Pago.
    error_log("WEBHOOK: ❌ ERRO CRÍTICO: " . $e->getMessage());
    error_log("WEBHOOK: Stack trace: " . $e->getTraceAsString());
    
    // Tentar salvar o erro no banco para análise posterior
    try {
        $db = Database::getConnection();
        
        // Criar tabela de erros se não existir
        $createTableStmt = $db->prepare("
            CREATE TABLE IF NOT EXISTS webhook_errors (
                id INT AUTO_INCREMENT PRIMARY KEY,
                mp_payment_id VARCHAR(255),
                error_message TEXT,
                payload LONGTEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $createTableStmt->execute();
        
        $errorStmt = $db->prepare("
            INSERT INTO webhook_errors (mp_payment_id, error_message, payload) 
            VALUES (?, ?, ?)
        ");
        $errorStmt->execute([
            $mpPaymentId ?? 'unknown',
            $e->getMessage(),
            $input_raw
        ]);
        
        error_log("WEBHOOK: Erro salvo na tabela webhook_errors");
        
    } catch (Exception $dbError) {
        error_log("WEBHOOK: Erro ao salvar erro no banco: " . $dbError->getMessage());
    }
    
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'Processamento temporariamente indisponível.']);
}

error_log("=== FIM WEBHOOK MP ===");
?>
