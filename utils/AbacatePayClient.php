<?php
/**
 * Cliente HTTP para integração com Abacate Pay
 * Gerencia pagamentos PIX para assinaturas do Klube Cash
 *
 * Documentação: https://docs.abacatepay.com
 */

class AbacatePayClient {
    private $apiBase;
    private $apiKey;
    private $timeout;

    // Endpoints da API (Abacate Pay v1)
    const ENDPOINTS = [
        'create_charge' => '/v1/pixQrCode/create',
        'get_charge' => '/v1/pixQrCode/check',
        'list_charges' => '/v1/billing/list',
    ];

    // Status possíveis
    const STATUS = [
        'pending' => 'Pendente',
        'paid' => 'Pago',
        'expired' => 'Expirado',
        'failed' => 'Falhou',
        'canceled' => 'Cancelado'
    ];

    public function __construct() {
        $this->apiBase = ABACATE_API_BASE;
        $this->apiKey = ABACATE_API_KEY;
        $this->timeout = ABACATE_TIMEOUT ?? 30;

        if (empty($this->apiKey)) {
            error_log('ABACATEPAY CLIENT - API KEY NÃO CONFIGURADA!');
            throw new Exception('ABACATE_API_KEY não configurada em constants.php');
        }

        if (empty($this->apiBase)) {
            error_log('ABACATEPAY CLIENT - API BASE URL NÃO CONFIGURADA!');
            throw new Exception('ABACATE_API_BASE não configurada em constants.php');
        }

        error_log("ABACATEPAY CLIENT - Inicializado com sucesso. API Base: {$this->apiBase}");
    }

    /**
     * Cria uma cobrança PIX
     *
     * @param array $payload Dados da cobrança
     * @return array Resposta com qr_code, copia_cola, etc
     * @throws Exception
     */
    public function createPixCharge(array $payload) {
        $this->validateChargePayload($payload);

        $endpoint = $this->apiBase . self::ENDPOINTS['create_charge'];

        // Sanitizar taxId antes de montar o payload
        $taxId = $this->sanitizeCpfCnpj($payload['customer']['cpf_cnpj'] ?? '');

        error_log("ABACATEPAY CLIENT - Preparando payload com taxId: '{$taxId}'");

        // Formato correto para AbacatePay /pixQrCode/create
        $data = [
            'amount' => $payload['amount'], // Em centavos
            'description' => $payload['description'] ?? 'Assinatura Klube Cash',
            'expiresIn' => 86400, // 24 horas em segundos (padrão)
            'customer' => [
                'name' => $payload['customer']['name'],
                'cellphone' => $payload['customer']['phone'] ?? '',
                'email' => $payload['customer']['email'],
                'taxId' => $taxId
            ]
        ];

        // Se foi passado expires_at, calcular expiresIn em segundos
        if (isset($payload['expires_at'])) {
            $expiresTimestamp = strtotime($payload['expires_at']);
            $data['expiresIn'] = max(60, $expiresTimestamp - time()); // Mínimo 60 segundos
        }

        $this->log('Creating PIX charge', $data);
        error_log("ABACATEPAY CLIENT - Payload final: " . json_encode($data));

        $response = $this->makeRequest('POST', $endpoint, $data);

        error_log("ABACATEPAY CLIENT - Resposta recebida: " . json_encode($response));

        // Verificar se a resposta tem o ID (pode vir como 'id' ou dentro de 'data')
        $responseData = $response['data'] ?? $response;
        $pixId = $responseData['id'] ?? null;

        if (!$pixId) {
            error_log("ABACATEPAY CLIENT - Resposta inválida, sem ID: " . json_encode($response));
            throw new Exception('Resposta inválida do Abacate Pay (sem ID): ' . json_encode($response));
        }

        // Extrair dados do QR Code conforme documentação oficial AbacatePay
        // Resposta contém: brCode (PIX copia e cola) e brCodeBase64 (QR Code base64)
        $qrCodeBase64 = $responseData['brCodeBase64'] ??
                       $responseData['qrCodeBase64'] ??
                       ($responseData['qrcode']['base64'] ?? null);

        $copiaECola = $responseData['brCode'] ??
                     $responseData['pixCode'] ??
                     ($responseData['qrcode']['qrcode'] ?? null);

        return [
            'gateway_charge_id' => $pixId,
            'qr_code_base64' => $qrCodeBase64,
            'copia_cola' => $copiaECola,
            'expires_at' => isset($responseData['expiresAt']) ? date('Y-m-d H:i:s', $responseData['expiresAt']) :
                           (isset($responseData['expires_at']) ? date('Y-m-d H:i:s', strtotime($responseData['expires_at'])) : null),
            'status' => $responseData['status'] ?? 'pending',
            'amount' => $responseData['amount'] ?? $payload['amount']
        ];
    }

    /**
     * Consulta status de uma cobrança
     *
     * @param string $chargeId ID da cobrança no Abacate Pay
     * @return array Status e dados da cobrança
     * @throws Exception
     */
    public function getChargeStatus(string $chargeId) {
        $endpoint = $this->apiBase . str_replace('{id}', $chargeId, self::ENDPOINTS['get_charge']);

        $this->log('Getting charge status', ['charge_id' => $chargeId]);

        $response = $this->makeRequest('GET', $endpoint);

        return [
            'id' => $response['id'] ?? $chargeId,
            'status' => $response['status'] ?? 'unknown',
            'amount' => $response['amount'] ?? 0,
            'paid_at' => isset($response['paidAt']) ? date('Y-m-d H:i:s', $response['paidAt']) : null,
            'external_id' => $response['externalId'] ?? null,
            'raw' => $response
        ];
    }

    /**
     * Valida assinatura do webhook
     *
     * @param array $headers Headers HTTP da requisição
     * @param string $rawBody Corpo bruto da requisição
     * @return bool True se assinatura é válida
     */
    public function validateWebhookSignature(array $headers, string $rawBody) {
        $secret = ABACATE_WEBHOOK_SECRET;

        if (empty($secret)) {
            $this->log('Webhook secret not configured', [], 'WARNING');
            return false; // Sem segredo configurado = rejeitar
        }

        // Abacate Pay envia a assinatura no header X-Webhook-Signature
        $receivedSignature = $headers['X-Webhook-Signature']
            ?? $headers['x-webhook-signature']
            ?? $_SERVER['HTTP_X_WEBHOOK_SIGNATURE']
            ?? null;

        if (!$receivedSignature) {
            $this->log('No webhook signature in headers', $headers, 'WARNING');
            return false;
        }

        // Calcular HMAC SHA256
        $expectedSignature = hash_hmac('sha256', $rawBody, $secret);

        $isValid = hash_equals($expectedSignature, $receivedSignature);

        if (!$isValid) {
            $this->log('Invalid webhook signature', [
                'expected' => $expectedSignature,
                'received' => $receivedSignature
            ], 'ERROR');
        }

        return $isValid;
    }

    /**
     * Faz requisição HTTP para a API
     *
     * @param string $method GET|POST|PUT|DELETE
     * @param string $url URL completa
     * @param array|null $data Dados para enviar
     * @return array Resposta decodificada
     * @throws Exception
     */
    private function makeRequest(string $method, string $url, array $data = null) {
        $ch = curl_init();

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: KlubeCash/2.1 (Subscription System)'
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->log('cURL error', ['error' => $error, 'url' => $url], 'ERROR');
            throw new Exception('Erro na requisição: ' . $error);
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMsg = $decoded['message'] ?? $decoded['error'] ?? 'Erro desconhecido';
            $this->log('API error', [
                'http_code' => $httpCode,
                'response' => $decoded
            ], 'ERROR');
            throw new Exception("Erro Abacate Pay [{$httpCode}]: {$errorMsg}");
        }

        return $decoded ?? [];
    }

    /**
     * Valida payload de criação de cobrança
     */
    private function validateChargePayload(array $payload) {
        $required = ['amount', 'customer'];
        foreach ($required as $field) {
            if (empty($payload[$field])) {
                throw new Exception("Campo obrigatório ausente: {$field}");
            }
        }

        if (empty($payload['customer']['name'])) {
            throw new Exception('Nome do cliente é obrigatório');
        }

        if (empty($payload['customer']['email'])) {
            throw new Exception('Email do cliente é obrigatório');
        }

        if ($payload['amount'] <= 0) {
            throw new Exception('Valor deve ser maior que zero');
        }
    }

    /**
     * Sanitiza e valida CPF/CNPJ
     * Retorna apenas números e valida tamanho (11 para CPF, 14 para CNPJ)
     */
    private function sanitizeCpfCnpj(string $value) {
        // Remove tudo que não é número
        $cleaned = preg_replace('/[^0-9]/', '', $value);

        // Se vazio ou inválido, usar CPF de teste válido
        if (empty($cleaned) || (strlen($cleaned) != 11 && strlen($cleaned) != 14)) {
            error_log("ABACATEPAY CLIENT - TaxId inválido ou vazio: '{$value}', usando CPF de teste");
            // CPF válido para testes: 123.456.789-09
            return '12345678909';
        }

        error_log("ABACATEPAY CLIENT - TaxId sanitizado: '{$value}' => '{$cleaned}'");
        return $cleaned;
    }

    /**
     * Log de operações
     */
    private function log(string $message, array $data = [], string $level = 'INFO') {
        if (defined('LOG_LEVEL') && LOG_LEVEL === 'DEBUG') {
            error_log("[AbacatePayClient][{$level}] {$message}: " . json_encode($data));
        }

        // Salvar em arquivo de log se necessário
        if (defined('LOGS_DIR')) {
            $logFile = LOGS_DIR . '/abacatepay.log';
            $logLine = date('Y-m-d H:i:s') . " [{$level}] {$message} " . json_encode($data) . PHP_EOL;
            @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
        }
    }
}
