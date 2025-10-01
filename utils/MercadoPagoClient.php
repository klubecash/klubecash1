<?php
// utils/MercadoPagoClient.php

/**
 * Cliente otimizado para integração com a API do Mercado Pago
 * Implementa todas as boas práticas para máxima taxa de aprovação
 * 
 * Fluxo do Mercado Pago:
 * 1. Enviamos dados completos do pagamento (payer, items, device_id)
 * 2. MP retorna QR Code PIX com alta qualidade de aprovação
 * 3. Cliente paga o PIX
 * 4. MP nos notifica via webhook seguro
 * 5. Podemos solicitar devoluções se necessário
 */
class MercadoPagoClient {
    // Configurações da API
    private $accessToken;
    private $baseUrl = 'https://api.mercadopago.com';
    private $timeout = 30;
    
    // Endpoints da API organizados por funcionalidade
    const ENDPOINTS = [
        'payments' => '/v1/payments',
        'payment_methods' => '/v1/payment_methods',
        'refunds' => '/v1/payments/{payment_id}/refunds',
        'refund_detail' => '/v1/payments/{payment_id}/refunds/{refund_id}'
    ];
    
    // Status possíveis dos pagamentos no MP
    const PAYMENT_STATUS = [
        'pending' => 'Pendente',
        'approved' => 'Aprovado',
        'authorized' => 'Autorizado',
        'in_process' => 'Em processamento',
        'in_mediation' => 'Em mediação',
        'rejected' => 'Rejeitado',
        'cancelled' => 'Cancelado',
        'refunded' => 'Estornado',
        'charged_back' => 'Chargeback'
    ];
    
    // Status possíveis das devoluções no MP
    const REFUND_STATUS = [
        'pending' => 'Pendente',
        'approved' => 'Aprovado',
        'rejected' => 'Rejeitado',
        'cancelled' => 'Cancelado'
    ];
    
    public function __construct() {
        // Verificar se as constantes estão definidas antes de usar
        if (!defined('MP_ACCESS_TOKEN')) {
            throw new Exception('MP_ACCESS_TOKEN não está definido. Verifique o arquivo constants.php');
        }
        
        $this->accessToken = MP_ACCESS_TOKEN;
        
        // Validar se o token não está vazio
        if (empty($this->accessToken)) {
            throw new Exception('MP_ACCESS_TOKEN está vazio. Configure suas credenciais do Mercado Pago.');
        }
        
        // Validar formato básico do token (deve começar com APP_USR)
        if (!str_starts_with($this->accessToken, 'APP_USR-')) {
            throw new Exception('MP_ACCESS_TOKEN parece inválido. Verifique se está usando o token correto.');
        }
        
        // Log de inicialização para debug (mascarando dados sensíveis)
        error_log("MercadoPagoClient inicializado com token: " . substr($this->accessToken, 0, 20) . "...");
    }
    
    /**
     * Criar um pagamento PIX no Mercado Pago com TODOS os campos para máxima aprovação
     * 
     * @param array $data Dados do pagamento contendo:
     *                   - amount: valor em reais (obrigatório)
     *                   - payer_email: email do pagador (obrigatório)  
     *                   - payer_name: nome do pagador (opcional)
     *                   - payer_lastname: sobrenome do pagador (opcional)
     *                   - payer_phone: telefone do pagador (opcional)
     *                   - payer_cpf: CPF do pagador (opcional)
     *                   - payer_address: endereço do pagador (opcional)
     *                   - description: descrição do pagamento (opcional)
     *                   - external_reference: referência externa (opcional)
     *                   - payment_id: ID do nosso pagamento interno (opcional)
     *                   - store_id: ID da loja (opcional)
     *                   - device_id: ID do dispositivo (recomendado)
     * 
     * @return array Resultado da operação com status e dados do pagamento
     */
    public function createPixPayment($data) {
        try {
            // Validar dados obrigatórios
            $validation = $this->validatePaymentData($data);
            if (!$validation['valid']) {
                return ['status' => false, 'message' => $validation['message']];
            }
            
            // GARANTIR QUE SEMPRE TEMOS NOMES VÁLIDOS
            $payerFirstName = $this->extractFirstName($data['payer_name'] ?? 'Cliente Klube Cash');
            $payerLastName = $this->extractLastName($data['payer_lastname'] ?? $data['payer_name'] ?? 'Sistema Klube Cash');
            
            // Log para debug
            error_log("MP: Nome extraído - First: '{$payerFirstName}', Last: '{$payerLastName}'");
            
            // Validação final dos nomes
            if (empty($payerFirstName) || strlen($payerFirstName) < 2) {
                $payerFirstName = 'Cliente';
            }
            if (empty($payerLastName) || strlen($payerLastName) < 2) {
                $payerLastName = 'KlubeCash';
            }
            
            // Montar o payload COMPLETO para enviar ao Mercado Pago
            $payload = [
                // Valor do pagamento (deve ser um número decimal)
                'transaction_amount' => (float) $data['amount'],
                
                // Especifica que queremos um pagamento PIX
                'payment_method_id' => 'pix',
                
                // DADOS COMPLETOS DO PAGADOR (NOMES OBRIGATÓRIOS E VÁLIDOS)
                'payer' => [
                    'email' => trim($data['payer_email']),
                    'first_name' => $payerFirstName, // NUNCA VAZIO
                    'last_name' => $payerLastName,   // NUNCA VAZIO
                    'type' => 'customer'
                ],
                
                // ITENS DETALHADOS (TODOS OS CAMPOS OBRIGATÓRIOS)
                'additional_info' => [
                    'items' => [
                        [
                            'id' => $data['item_id'] ?? 'COMISSAO_KC_' . ($data['payment_id'] ?? uniqid()),
                            'title' => $data['item_title'] ?? 'Comissão Klube Cash',
                            'description' => $data['item_description'] ?? 'Pagamento de comissão para liberação de cashback aos clientes',
                            'category_id' => $data['item_category'] ?? 'services', // OBRIGATÓRIO
                            'quantity' => 1,
                            'unit_price' => (float) $data['amount'],
                            'picture_url' => 'https://klubecash.com/assets/images/logo.png',
                            'warranty' => false
                        ]
                    ]
                ],
                
                // Configurações adicionais para PIX
                'date_of_expiration' => gmdate('Y-m-d\TH:i:s.000\Z', strtotime('+30 minutes')),
                'statement_descriptor' => 'KLUBECASH',
                'notification_url' => defined('MP_WEBHOOK_URL') && !empty(MP_WEBHOOK_URL) ? MP_WEBHOOK_URL : null
            ];
            
            // ADICIONAR TELEFONE SE DISPONÍVEL (MELHORA APROVAÇÃO)
            if (!empty($data['payer_phone'])) {
                $phoneData = $this->parsePhoneNumber($data['payer_phone']);
                $payload['payer']['phone'] = $phoneData;
                error_log("MP: Telefone adicionado: " . json_encode($phoneData));
            }
            
            // ADICIONAR CPF/CNPJ SE DISPONÍVEL (MELHORA MUITO A APROVAÇÃO)
            if (!empty($data['payer_cpf'])) {
                $payload['payer']['identification'] = [
                    'type' => strlen(preg_replace('/\D/', '', $data['payer_cpf'])) <= 11 ? 'CPF' : 'CNPJ',
                    'number' => preg_replace('/\D/', '', $data['payer_cpf'])
                ];
                error_log("MP: Identificação adicionada: " . json_encode($payload['payer']['identification']));
            }
            
            // ADICIONAR ENDEREÇO SE DISPONÍVEL (MELHORA APROVAÇÃO)
            if (!empty($data['payer_address'])) {
                $payload['payer']['address'] = $this->parseAddress($data['payer_address']);
                error_log("MP: Endereço adicionado: " . json_encode($payload['payer']['address']));
            }
            
            // ADICIONAR DATA DE REGISTRO SE DISPONÍVEL
            if (!empty($data['payer_registration_date'])) {
                if (!isset($payload['additional_info']['payer'])) {
                    $payload['additional_info']['payer'] = [];
                }
                $payload['additional_info']['payer']['registration_date'] = $data['payer_registration_date'];
            }
            
            // DEVICE ID PARA PIX VAI NOS METADADOS (NÃO NO PAYLOAD PRINCIPAL)
            if (!empty($data['device_id'])) {
                error_log("MP: Device ID será adicionado aos metadados: " . $data['device_id']);
            }
            
            // Adicionar campos opcionais se foram fornecidos
            if (!empty($data['description'])) {
                $payload['description'] = substr(trim($data['description']), 0, 255);
            }
            
            if (!empty($data['external_reference'])) {
                $payload['external_reference'] = trim($data['external_reference']);
            }
            
            // Metadados para armazenar informações extras (INCLUINDO DEVICE_ID)
            $payload['metadata'] = [
                'integration' => 'KlubeCash_v2.1',
                'payment_type' => 'commission',
                'created_at' => date('Y-m-d H:i:s'),
                'source' => 'store_payment'
            ];
            
            // DEVICE ID VAI NOS METADADOS PARA PIX
            if (!empty($data['device_id'])) {
                $payload['metadata']['device_id'] = $data['device_id'];
                $payload['metadata']['device_source'] = 'javascript_sdk';
            }
            
            if (!empty($data['payment_id'])) {
                $payload['metadata']['payment_id'] = (string) $data['payment_id'];
            }
            if (!empty($data['store_id'])) {
                $payload['metadata']['store_id'] = (string) $data['store_id'];
            }
            
            // Log final para debug (mascarando dados sensíveis)
            $logPayload = $this->maskSensitiveData($payload);
            error_log("MP createPixPayment - Payload CORRIGIDO: " . json_encode($logPayload, JSON_PRETTY_PRINT));
            
            // Fazer a requisição para o Mercado Pago
            $response = $this->makeRequest('POST', self::ENDPOINTS['payments'], $payload);
            
            // Se a requisição foi bem-sucedida, extrair os dados do PIX
            if ($response['status'] && isset($response['data'])) {
                return $this->processPixPaymentResponse($response['data']);
            }
            
            return $response;
            
        } catch (Exception $e) {
            error_log("MP createPixPayment Exception: " . $e->getMessage());
            return [
                'status' => false,
                'message' => 'Erro interno ao criar pagamento PIX: ' . $e->getMessage(),
                'error_type' => 'internal_error'
            ];
        }
    }
    
    /**
     * Extrair primeiro nome de um nome completo
     */
    private function extractFirstName($fullName) {
        if (empty($fullName) || trim($fullName) === '') {
            return 'Cliente'; // Fallback obrigatório
        }
        
        $names = array_filter(explode(' ', trim($fullName)));
        $firstName = !empty($names[0]) ? trim($names[0]) : 'Cliente';
        
        // Validar se tem pelo menos 2 caracteres
        if (strlen($firstName) < 2) {
            return 'Cliente';
        }
        
        // Remover caracteres especiais, manter apenas letras
        $firstName = preg_replace('/[^a-zA-ZÀ-ÿ\s]/', '', $firstName);
        
        return !empty($firstName) ? $firstName : 'Cliente';
    }
    
    /**
     * Extrair sobrenome de um nome completo
     */
    private function extractLastName($fullName) {
        if (empty($fullName) || trim($fullName) === '') {
            return 'KlubeCash'; // Fallback obrigatório
        }
        
        $names = array_filter(explode(' ', trim($fullName)));
        
        if (count($names) <= 1) {
            return 'KlubeCash';
        }
        
        $lastName = implode(' ', array_slice($names, 1));
        
        // Validar se tem pelo menos 2 caracteres
        if (strlen($lastName) < 2) {
            return 'KlubeCash';
        }
        
        // Remover caracteres especiais, manter apenas letras
        $lastName = preg_replace('/[^a-zA-ZÀ-ÿ\s]/', '', $lastName);
        
        return !empty($lastName) ? $lastName : 'KlubeCash';
    }
    
    /**
     * Parse número de telefone para formato do MP
     */
    private function parsePhoneNumber($phone) {
        // Remove caracteres não numéricos
        $cleanPhone = preg_replace('/\D/', '', $phone);
        
        // Se tem 11 dígitos (celular com DDD)
        if (strlen($cleanPhone) === 11) {
            return [
                'area_code' => substr($cleanPhone, 0, 2),
                'number' => substr($cleanPhone, 2)
            ];
        }
        
        // Se tem 10 dígitos (fixo com DDD)
        if (strlen($cleanPhone) === 10) {
            return [
                'area_code' => substr($cleanPhone, 0, 2),
                'number' => substr($cleanPhone, 2)
            ];
        }
        
        // Fallback para formato padrão
        return [
            'area_code' => '11',
            'number' => substr($cleanPhone, -8)
        ];
    }
    
    /**
     * Parse endereço para formato do MP
     */
    private function parseAddress($address) {
        // Se é um array, usar diretamente
        if (is_array($address)) {
            return [
                'zip_code' => $address['zip_code'] ?? $address['cep'] ?? '',
                'street_name' => $address['street_name'] ?? $address['logradouro'] ?? '',
                'street_number' => (int)($address['street_number'] ?? $address['numero'] ?? 0),
                'neighborhood' => $address['neighborhood'] ?? $address['bairro'] ?? '',
                'city' => $address['city'] ?? $address['cidade'] ?? '',
                'federal_unit' => $address['federal_unit'] ?? $address['estado'] ?? ''
            ];
        }
        
        // Se é string, retornar formato básico
        return [
            'zip_code' => '',
            'street_name' => (string) $address,
            'street_number' => 0,
            'neighborhood' => '',
            'city' => 'Patos de Minas',
            'federal_unit' => 'MG'
        ];
    }
    
    /**
     * Consultar o status de um pagamento no Mercado Pago
     */
    public function getPaymentStatus($paymentId) {
        if (empty($paymentId)) {
            return ['status' => false, 'message' => 'ID do pagamento é obrigatório'];
        }
        
        $endpoint = str_replace('{payment_id}', $paymentId, self::ENDPOINTS['payments'] . '/{payment_id}');
        
        error_log("MP getPaymentStatus - Consultando pagamento: {$paymentId}");
        
        $response = $this->makeRequest('GET', $endpoint);
        
        if ($response['status'] && isset($response['data'])) {
            $mpData = $response['data'];
            
            return [
                'status' => true,
                'data' => [
                    'id' => $mpData['id'],
                    'status' => $mpData['status'],
                    'status_detail' => $mpData['status_detail'] ?? '',
                    'status_description' => self::PAYMENT_STATUS[$mpData['status']] ?? $mpData['status'],
                    'amount' => $mpData['transaction_amount'],
                    'date_created' => $mpData['date_created'] ?? '',
                    'date_approved' => $mpData['date_approved'] ?? '',
                    'date_last_updated' => $mpData['date_last_updated'] ?? '',
                    'external_reference' => $mpData['external_reference'] ?? '',
                    'metadata' => $mpData['metadata'] ?? [],
                    'payment_method_id' => $mpData['payment_method_id'] ?? '',
                    'payment_type_id' => $mpData['payment_type_id'] ?? ''
                ]
            ];
        }
        
        return $response;
    }
    
    
    
    
    
    /**
     * Validar assinatura do webhook do Mercado Pago
     */
    public function validateWebhookSignature($headers, $body) {
        try {
            // MP envia a assinatura nos headers (normalizar nomes dos headers)
            $normalizedHeaders = array_change_key_case($headers, CASE_LOWER);
            $signature = $normalizedHeaders['x-signature'] ?? '';
            $requestId = $normalizedHeaders['x-request-id'] ?? '';
            
            if (empty($signature) || empty($requestId)) {
                error_log("MP Webhook - Headers de assinatura ausentes");
                return false;
            }
            
            // Separar timestamp e hash da assinatura
            $parts = explode(',', $signature);
            $timestamp = '';
            $hash = '';
            
            foreach ($parts as $part) {
                $keyValue = explode('=', trim($part), 2);
                if (count($keyValue) === 2) {
                    $key = trim($keyValue[0]);
                    $value = trim($keyValue[1]);
                    
                    if ($key === 'ts') {
                        $timestamp = $value;
                    } elseif ($key === 'v1') {
                        $hash = $value;
                    }
                }
            }
            
            if (empty($timestamp) || empty($hash)) {
                error_log("MP Webhook - Formato de assinatura inválido: {$signature}");
                return false;
            }
            
            // Verificar se o timestamp não é muito antigo (máximo 15 minutos)
            $currentTime = time();
            $timestampInt = (int)$timestamp;
            if (abs($currentTime - $timestampInt) > 900) {
                error_log("MP Webhook - Timestamp muito antigo: {$timestamp}, atual: {$currentTime}");
                return false;
            }
            
            // Construir string para validação
            $dataToSign = $requestId . $timestamp . $body;
            
            // Calcular hash esperado usando a chave secreta
            if (defined('MP_WEBHOOK_SECRET') && !empty(MP_WEBHOOK_SECRET)) {
                $expectedHash = hash_hmac('sha256', $dataToSign, MP_WEBHOOK_SECRET);
                
                $isValid = hash_equals($expectedHash, $hash);
                
                if (!$isValid) {
                    error_log("MP Webhook - Assinatura inválida. Esperado: {$expectedHash}, Recebido: {$hash}");
                }
                
                return $isValid;
            }
            
            error_log("MP Webhook - MP_WEBHOOK_SECRET não definido, pulando validação");
            return true; // Se não tem secret configurado, aceita (não recomendado para produção)
            
        } catch (Exception $e) {
            error_log("MP Webhook - Erro na validação: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Método para testar a conectividade com o Mercado Pago
     */
    public function testConnection() {
        try {
            $response = $this->makeRequest('GET', self::ENDPOINTS['payment_methods']);
            
            if ($response['status']) {
                return [
                    'status' => true, 
                    'message' => 'Conexão com Mercado Pago funcionando corretamente',
                    'token_valid' => true,
                    'response_time' => $response['response_time'] ?? 'unknown'
                ];
            } else {
                return [
                    'status' => false,
                    'message' => 'Erro na conexão: ' . $response['message'],
                    'token_valid' => false
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => false,
                'message' => 'Erro no teste: ' . $e->getMessage(),
                'token_valid' => false
            ];
        }
    }
    
    /**
     * Validar dados obrigatórios para criação de pagamento
     */
    private function validatePaymentData($data) {
        // Verificar valor
        if (empty($data['amount']) || !is_numeric($data['amount']) || $data['amount'] <= 0) {
            return ['valid' => false, 'message' => 'Valor do pagamento é obrigatório e deve ser maior que zero'];
        }
        
        // Verificar limites do valor
        if ($data['amount'] < 0.01) {
            return ['valid' => false, 'message' => 'Valor mínimo é R$ 0,01'];
        }
        
        if ($data['amount'] > 1000000) {
            return ['valid' => false, 'message' => 'Valor máximo é R$ 1.000.000,00'];
        }
        
        // Verificar email
        if (empty($data['payer_email']) || !filter_var($data['payer_email'], FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'message' => 'Email do pagador é obrigatório e deve ser válido'];
        }
        
        // Verificar tamanho da descrição
        if (!empty($data['description']) && strlen($data['description']) > 255) {
            return ['valid' => false, 'message' => 'Descrição não pode ter mais que 255 caracteres'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Processar resposta de criação de pagamento PIX
     */
    private function processPixPaymentResponse($mpData) {
        // Verificar se recebemos os dados necessários do PIX
        $qrCode = '';
        $qrCodeBase64 = '';
        
        // O MP retorna os dados do PIX dentro de point_of_interaction
        if (isset($mpData['point_of_interaction']['transaction_data'])) {
            $transactionData = $mpData['point_of_interaction']['transaction_data'];
            $qrCode = $transactionData['qr_code'] ?? '';
            $qrCodeBase64 = $transactionData['qr_code_base64'] ?? '';
        }
        
        // Validar se recebemos os dados essenciais
        if (empty($qrCode) || empty($qrCodeBase64)) {
            error_log("MP createPixPayment - ERRO: QR Code não foi gerado. Resposta: " . json_encode($mpData));
            return [
                'status' => false, 
                'message' => 'Mercado Pago não gerou o QR Code PIX. Tente novamente.',
                'error_type' => 'qr_code_error',
                'mp_payment_id' => $mpData['id'] ?? null
            ];
        }
        
        // Retornar os dados organizados
        return [
            'status' => true,
            'data' => [
                'mp_payment_id' => $mpData['id'],
                'qr_code' => $qrCode,
                'qr_code_base64' => $qrCodeBase64,
                'status' => $mpData['status'],
                'status_detail' => $mpData['status_detail'] ?? '',
                'amount' => $mpData['transaction_amount'],
                'currency_id' => $mpData['currency_id'] ?? 'BRL',
                'date_created' => $mpData['date_created'] ?? '',
                'date_of_expiration' => $mpData['date_of_expiration'] ?? '',
                'external_reference' => $mpData['external_reference'] ?? '',
                'description' => $mpData['description'] ?? ''
            ]
        ];
    }
    
    /**
     * Mascarar dados sensíveis para logs
     */
    private function maskSensitiveData($data) {
        $masked = $data;
        
        // Mascarar email
        if (isset($masked['payer']['email'])) {
            $email = $masked['payer']['email'];
            $atPos = strpos($email, '@');
            if ($atPos !== false) {
                $masked['payer']['email'] = substr($email, 0, 3) . '***' . substr($email, $atPos);
            }
        }
        
        // Mascarar outros dados sensíveis se necessário
        if (isset($masked['payer']['first_name'])) {
            $masked['payer']['first_name'] = substr($masked['payer']['first_name'], 0, 3) . '***';
        }
        
        // Mascarar phone
        if (isset($masked['payer']['phone']['number'])) {
            $phone = $masked['payer']['phone']['number'];
            $masked['payer']['phone']['number'] = substr($phone, 0, 3) . '***' . substr($phone, -2);
        }
        
        // Mascarar CPF
        if (isset($masked['payer']['identification']['number'])) {
            $doc = $masked['payer']['identification']['number'];
            $masked['payer']['identification']['number'] = substr($doc, 0, 3) . '***' . substr($doc, -2);
        }
        
        return $masked;
    }
    
    /**
     * Método central para fazer requisições HTTP para o Mercado Pago
     */
    private function makeRequest($method, $endpoint, $data = null) {
        $startTime = microtime(true);
        $url = $this->baseUrl . $endpoint;
        
        // Configurar headers necessários COM TODAS AS OTIMIZAÇÕES
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: KlubeCash/2.1 (PHP/' . PHP_VERSION . '; MP-Integration-Quality-Optimized)',
            'X-Idempotency-Key: ' . uniqid('klube_' . time() . '_', true),
            'X-meli-session-id: ' . uniqid('session_', true),
            'X-Product-Id: KLUBE_CASH_CASHBACK_SYSTEM'
        ];
        
        // ADICIONAR TRACKING DE ORIGEM MELHORADO
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $headers[] = 'X-Tracking-Id: ' . md5($_SERVER['HTTP_USER_AGENT'] . date('Y-m-d'));
        }
        
        // Headers específicos para melhorar aprovação
        $headers[] = 'X-Integrator-Id: klube_cash_v2';
        $headers[] = 'X-Platform-Id: custom_integration';
        
        error_log("MP Request: {$method} {$url}");
        
        // Inicializar cURL com configurações otimizadas
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_USERAGENT => 'KlubeCash/2.1 MP-Quality-Optimized',
            CURLOPT_VERBOSE => false,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_FORBID_REUSE => true,
        ]);
        
        // Adicionar dados se necessário
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            
            // Log dos dados (mascarados)
            $logData = $this->maskSensitiveData($data);
            error_log("MP Request Data: " . json_encode($logData));
        }
        
        // Executar requisição
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        $responseTime = microtime(true) - $startTime;
        
        // Log da resposta
        error_log("MP Response: HTTP {$httpCode} em {$responseTime}s");
        
        // Verificar erros de conexão
        if ($curlError) {
            error_log("MP cURL Error: {$curlError}");
            return [
                'status' => false, 
                'message' => 'Erro de conexão com Mercado Pago: ' . $curlError,
                'error_type' => 'connection_error',
                'response_time' => $responseTime
            ];
        }
        
        // Verificar resposta vazia
        if (empty($response)) {
            error_log("MP Empty Response - HTTP Code: {$httpCode}");
            return [
                'status' => false,
                'message' => 'Mercado Pago retornou resposta vazia',
                'error_type' => 'empty_response',
                'http_code' => $httpCode,
                'response_time' => $responseTime
            ];
        }
        
        // Log da resposta (truncado)
        $logResponse = strlen($response) > 1000 ? substr($response, 0, 1000) . '...' : $response;
        error_log("MP Response Body: {$logResponse}");
        
        // Decodificar JSON
        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("MP JSON Decode Error: " . json_last_error_msg());
            return [
                'status' => false,
                'message' => 'Resposta inválida do Mercado Pago: ' . json_last_error_msg(),
                'error_type' => 'json_error',
                'raw_response' => substr($response, 0, 500),
                'response_time' => $responseTime
            ];
        }
        
        // Verificar sucesso HTTP
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'status' => true, 
                'data' => $decodedResponse,
                'response_time' => $responseTime
            ];
        }
        
        // Processar erro da API
        $errorMessage = $this->extractErrorMessage($decodedResponse, $httpCode);
        
        error_log("MP API Error: {$errorMessage}");
        
        return [
            'status' => false,
            'message' => $errorMessage,
            'error_type' => 'api_error',
            'http_code' => $httpCode,
            'response_data' => $decodedResponse,
            'response_time' => $responseTime
        ];
    }
    
    /**
     * Extrair mensagem de erro mais legível da resposta do MP
     */
    private function extractErrorMessage($response, $httpCode) {
        $errorMessage = 'Erro HTTP ' . $httpCode;
        
        if (!is_array($response)) {
            return $errorMessage;
        }
        
        // Diferentes formatos de erro do MP
        if (isset($response['message'])) {
            $errorMessage .= ': ' . $response['message'];
        } elseif (isset($response['error'])) {
            $errorMessage .= ': ' . $response['error'];
        } elseif (isset($response['cause'])) {
            $causes = is_array($response['cause']) ? $response['cause'] : [$response['cause']];
            $causeMessages = [];
            
            foreach ($causes as $cause) {
                if (is_array($cause)) {
                    $causeMessages[] = $cause['description'] ?? $cause['code'] ?? 'Erro desconhecido';
                } else {
                    $causeMessages[] = $cause;
                }
            }
            
            $errorMessage .= ': ' . implode(', ', $causeMessages);
        } elseif (isset($response['errors'])) {
            // Formato alternativo de erros
            $errorMessages = [];
            foreach ($response['errors'] as $error) {
                if (is_array($error)) {
                    $errorMessages[] = $error['message'] ?? $error['description'] ?? 'Erro desconhecido';
                }
            }
            if (!empty($errorMessages)) {
                $errorMessage .= ': ' . implode(', ', $errorMessages);
            }
        }
        
        return $errorMessage;
    }
}
?>