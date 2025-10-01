<?php
// controllers/OpenPixController.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

class OpenPixController {
    
    /**
     * Criar cobrança PIX na OpenPix
     */
    public static function createCharge($paymentId, $value, $correlationID) {
        try {
            $endpoint = '/api/v1/charge';
            
            $payload = [
                'value' => (int)($value * 100), // Converter para centavos
                'correlationID' => $correlationID,
                'comment' => "Comissão Klube Cash - Pagamento #{$paymentId}",
                'customer' => [
                    'name' => 'Loja Parceira',
                    'email' => 'loja@klubecash.com'
                ]
            ];
            
            $response = self::makeApiRequest('POST', $endpoint, $payload);
            
            if ($response['success'] && isset($response['data']['charge'])) {
                return [
                    'success' => true,
                    'data' => [
                        'charge_id' => $response['data']['charge']['identifier'],
                        'qr_code' => $response['data']['brCode'],
                        'qr_code_image' => $response['data']['charge']['qrCodeImage'],
                        'payment_link' => $response['data']['charge']['paymentLinkUrl'] ?? null
                    ]
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Erro ao criar cobrança: ' . ($response['message'] ?? 'Erro desconhecido')
            ];
            
        } catch (Exception $e) {
            error_log("OpenPix createCharge error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro interno ao processar cobrança'
            ];
        }
    }
    
    /**
     * Verificar status de uma cobrança
     */
    public static function getChargeStatus($chargeId) {
        try {
            $endpoint = "/api/v1/charge/{$chargeId}";
            $response = self::makeApiRequest('GET', $endpoint);
            
            if ($response['success'] && isset($response['data']['charge'])) {
                return [
                    'success' => true,
                    'data' => [
                        'status' => $response['data']['charge']['status'],
                        'value' => $response['data']['charge']['value'],
                        'paid_at' => $response['data']['charge']['paidAt'] ?? null
                    ]
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Cobrança não encontrada'
            ];
            
        } catch (Exception $e) {
            error_log("OpenPix getChargeStatus error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao consultar status'
            ];
        }
    }
    
    /**
     * Processar webhook da OpenPix
     */
    public static function processWebhook($data) {
        try {
            if (!isset($data['charge'])) {
                return ['success' => false, 'message' => 'Dados inválidos'];
            }
            
            $charge = $data['charge'];
            $chargeId = $charge['identifier'] ?? null;
            $status = $charge['status'] ?? null;
            $correlationID = $charge['correlationID'] ?? null;
            
            if (!$chargeId || !$status || !$correlationID) {
                return ['success' => false, 'message' => 'Dados incompletos'];
            }
            
            // Extrair payment_id do correlationID
            if (preg_match('/payment_(\d+)_/', $correlationID, $matches)) {
                $paymentId = $matches[1];
            } else {
                error_log("OpenPix Webhook: CorrelationID inválido: {$correlationID}");
                return ['success' => false, 'message' => 'CorrelationID inválido'];
            }
            
            $db = Database::getConnection();
            
            // Buscar pagamento
            $stmt = $db->prepare("
                SELECT p.*, l.nome_fantasia as loja_nome 
                FROM pagamentos_comissao p
                LEFT JOIN lojas l ON p.loja_id = l.id
                WHERE p.id = ?
            ");
            $stmt->execute([$paymentId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payment) {
                error_log("OpenPix Webhook: Pagamento não encontrado - ID: {$paymentId}, ChargeID: {$chargeId}");
                return ['success' => false, 'message' => 'Pagamento não encontrado'];
            }
            
            // Se o pagamento foi completado
            if ($status === 'COMPLETED' && $payment['status'] === 'openpix_aguardando') {
                
                // Atualizar pagamento
                $updateStmt = $db->prepare("
                    UPDATE pagamentos_comissao 
                    SET status = 'aprovado',
                        openpix_status = 'COMPLETED',
                        openpix_paid_at = NOW(),
                        observacao_admin = 'Pagamento PIX aprovado automaticamente via OpenPix'
                    WHERE id = ?
                ");
                $updateStmt->execute([$paymentId]);
                
                // Aprovar transações pendentes da loja
                $approveTransStmt = $db->prepare("
                    UPDATE transacoes_cashback 
                    SET status = 'aprovado'
                    WHERE loja_id = ? AND status = 'pendente'
                ");
                $approveTransStmt->execute([$payment['loja_id']]);
                
                $cashbackStmt = $db->prepare("
                    INSERT INTO cashback_saldos (usuario_id, loja_id, saldo_disponivel) 
                    SELECT t.usuario_id, t.loja_id, t.valor_total * 0.05 
                    FROM transacoes_cashback t
                    WHERE t.loja_id = ? AND t.status = 'aprovado' 
                    AND NOT EXISTS (
                        SELECT 1 FROM cashback_saldos cs 
                        WHERE cs.usuario_id = t.usuario_id 
                        AND cs.loja_id = t.loja_id 
                        AND cs.origem_transacao_id = t.id
                    )
                    ON DUPLICATE KEY UPDATE saldo_disponivel = saldo_disponivel + VALUES(saldo_disponivel)
                ");
                $cashbackStmt->execute([$payment['loja_id']]);
                
                error_log("OpenPix Webhook: ✅ Pagamento aprovado - ID: {$paymentId}");
                return [
                    'success' => true,
                    'message' => 'Pagamento processado com sucesso'
                ];
            }
            
            // Atualizar apenas o status se não for COMPLETED
            $updateStmt = $db->prepare("
                UPDATE pagamentos_comissao 
                SET openpix_status = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$status, $paymentId]);
            
            return ['success' => true, 'message' => 'Status atualizado'];
            
        } catch (Exception $e) {
            error_log("OpenPix processWebhook error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno'];
        }
    }
    
    /**
     * Fazer requisição para API da OpenPix
     */
    private static function makeApiRequest($method, $endpoint, $data = null) {
        try {
            $url = OPENPIX_API_URL . $endpoint;
            
            $headers = [
                'Content-Type: application/json',
                'Authorization: ' . OPENPIX_APP_ID,
                'User-Agent: KlubeCash/1.0'
            ];
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true
            ]);
            
            if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new Exception("cURL Error: {$error}");
            }
            
            $decodedResponse = json_decode($response, true);
            
            if ($httpCode >= 200 && $httpCode < 300) {
                return [
                    'success' => true,
                    'data' => $decodedResponse
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $decodedResponse['message'] ?? "HTTP Error: {$httpCode}",
                    'http_code' => $httpCode
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
?>