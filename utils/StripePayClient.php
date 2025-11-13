<?php
/**
 * Cliente para integração com Stripe Payment Gateway
 *
 * Este cliente gerencia pagamentos com cartão de crédito via Stripe para o sistema de assinaturas.
 * Suporta Payment Intents (método recomendado), 3D Secure (SCA), e webhooks.
 *
 * @package KlubeCash
 * @version 1.0.0
 */

require_once __DIR__ . '/../config/constants.php';

class StripePayClient
{
    private $apiKey;
    private $apiBase;
    private $webhookSecret;
    private $timeout;

    /**
     * Construtor - inicializa cliente Stripe com configurações
     */
    public function __construct()
    {
        $this->apiKey = defined('STRIPE_SECRET_KEY') ? STRIPE_SECRET_KEY : '';
        $this->apiBase = defined('STRIPE_API_BASE') ? STRIPE_API_BASE : 'https://api.stripe.com';
        $this->webhookSecret = defined('STRIPE_WEBHOOK_SECRET') ? STRIPE_WEBHOOK_SECRET : '';
        $this->timeout = defined('STRIPE_TIMEOUT') ? STRIPE_TIMEOUT : 30;

        if (empty($this->apiKey)) {
            throw new Exception('STRIPE_SECRET_KEY não está configurada em constants.php');
        }
    }

    /**
     * Cria um Payment Intent para pagamento com cartão
     *
     * Payment Intent é o método recomendado pela Stripe, suporta:
     * - 3D Secure (SCA) automático
     * - Múltiplas tentativas de pagamento
     * - Confirmação assíncrona via webhook
     *
     * @param array $payload Dados do pagamento
     *   - amount (int): Valor em centavos (ex: 4990 = R$ 49,90)
     *   - currency (string): Moeda (padrão: 'brl')
     *   - description (string): Descrição do pagamento
     *   - customer_email (string): Email do cliente
     *   - metadata (array): Dados extras (invoice_id, subscription_id, etc)
     *
     * @return array Resposta da API Stripe
     *   - id (string): payment_intent_id
     *   - client_secret (string): Usado no frontend para confirmar pagamento
     *   - status (string): 'requires_payment_method', 'requires_confirmation', etc
     *   - amount (int): Valor em centavos
     *
     * @throws Exception Em caso de erro na API
     */
    public function createPaymentIntent($payload)
    {
        // Validação de campos obrigatórios
        if (!isset($payload['amount']) || $payload['amount'] <= 0) {
            throw new Exception('Campo "amount" é obrigatório e deve ser maior que zero');
        }

        // Preparar dados para API Stripe
        $data = [
            'amount' => (int)$payload['amount'], // Centavos
            'currency' => $payload['currency'] ?? 'brl',
            'description' => $payload['description'] ?? 'Pagamento Klube Cash',
            'automatic_payment_methods' => [
                'enabled' => true, // Habilita cartões automaticamente
            ],
            'metadata' => $payload['metadata'] ?? [],
        ];

        // Adicionar email do cliente se fornecido
        if (isset($payload['customer_email'])) {
            $data['receipt_email'] = $payload['customer_email'];
        }

        // Adicionar ID do cliente Stripe se existir
        if (isset($payload['customer_id'])) {
            $data['customer'] = $payload['customer_id'];
        }

        // Fazer requisição à API Stripe
        $response = $this->makeRequest('POST', '/v1/payment_intents', $data);

        return [
            'id' => $response['id'],
            'client_secret' => $response['client_secret'],
            'status' => $response['status'],
            'amount' => $response['amount'],
            'currency' => $response['currency'],
        ];
    }

    /**
     * Confirma um Payment Intent já criado
     *
     * Usado quando o pagamento requer confirmação manual.
     * Na maioria dos casos, a confirmação é feita no frontend via Stripe.js
     *
     * @param string $paymentIntentId ID do Payment Intent
     * @param array $options Opções adicionais
     *
     * @return array Status do pagamento
     */
    public function confirmPaymentIntent($paymentIntentId, $options = [])
    {
        $data = array_merge([
            'payment_method' => null, // Se não fornecido, usa o já anexado
        ], $options);

        $response = $this->makeRequest('POST', "/v1/payment_intents/{$paymentIntentId}/confirm", $data);

        return [
            'id' => $response['id'],
            'status' => $response['status'],
            'amount' => $response['amount'],
        ];
    }

    /**
     * Consulta status de um Payment Intent existente
     *
     * @param string $paymentIntentId ID do Payment Intent
     * @return array Dados do Payment Intent
     */
    public function getPaymentIntent($paymentIntentId)
    {
        $response = $this->makeRequest('GET', "/v1/payment_intents/{$paymentIntentId}");

        return [
            'id' => $response['id'],
            'status' => $response['status'],
            'amount' => $response['amount'],
            'currency' => $response['currency'],
            'payment_method' => $response['payment_method'] ?? null,
            'charges' => $response['charges']['data'] ?? [],
            'metadata' => $response['metadata'] ?? [],
        ];
    }

    /**
     * Cancela um Payment Intent pendente
     *
     * @param string $paymentIntentId ID do Payment Intent
     * @return array Confirmação do cancelamento
     */
    public function cancelPaymentIntent($paymentIntentId)
    {
        $response = $this->makeRequest('POST', "/v1/payment_intents/{$paymentIntentId}/cancel");

        return [
            'id' => $response['id'],
            'status' => $response['status'],
            'canceled_at' => $response['canceled_at'] ?? null,
        ];
    }

    /**
     * Valida assinatura de webhook Stripe
     *
     * IMPORTANTE: Sempre validar webhooks em produção para evitar fraudes!
     *
     * @param string $payload Corpo da requisição (raw JSON)
     * @param string $signature Header "Stripe-Signature"
     *
     * @return bool True se assinatura válida
     * @throws Exception Se assinatura inválida
     */
    public function validateWebhookSignature($payload, $signature)
    {
        if (empty($this->webhookSecret)) {
            throw new Exception('STRIPE_WEBHOOK_SECRET não está configurado');
        }

        // Extrair timestamp e assinatura do header
        $elements = explode(',', $signature);
        $timestamp = null;
        $signatures = [];

        foreach ($elements as $element) {
            $parts = explode('=', $element, 2);
            if (count($parts) !== 2) continue;

            if ($parts[0] === 't') {
                $timestamp = $parts[1];
            } elseif ($parts[0] === 'v1') {
                $signatures[] = $parts[1];
            }
        }

        if ($timestamp === null || empty($signatures)) {
            throw new Exception('Header Stripe-Signature inválido');
        }

        // Verificar se timestamp não é muito antigo (tolerância de 5 minutos)
        $currentTime = time();
        if (abs($currentTime - $timestamp) > 300) {
            throw new Exception('Timestamp do webhook expirado');
        }

        // Calcular assinatura esperada
        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

        // Comparar assinaturas (timing-safe comparison)
        $isValid = false;
        foreach ($signatures as $sig) {
            if (hash_equals($expectedSignature, $sig)) {
                $isValid = true;
                break;
            }
        }

        if (!$isValid) {
            throw new Exception('Assinatura do webhook inválida');
        }

        return true;
    }

    /**
     * Extrai informações do cartão de um Payment Method
     *
     * @param string $paymentMethodId ID do Payment Method
     * @return array Dados do cartão (brand, last4, exp_month, exp_year)
     */
    public function getPaymentMethodDetails($paymentMethodId)
    {
        $response = $this->makeRequest('GET', "/v1/payment_methods/{$paymentMethodId}");

        if ($response['type'] === 'card' && isset($response['card'])) {
            return [
                'type' => 'card',
                'brand' => $response['card']['brand'], // visa, mastercard, etc
                'last4' => $response['card']['last4'],
                'exp_month' => $response['card']['exp_month'],
                'exp_year' => $response['card']['exp_year'],
                'country' => $response['card']['country'] ?? null,
            ];
        }

        return [
            'type' => $response['type'],
        ];
    }

    /**
     * Faz requisição HTTP à API Stripe
     *
     * @param string $method GET, POST, DELETE, etc
     * @param string $endpoint Endpoint da API (ex: /v1/payment_intents)
     * @param array $data Dados a enviar (form-encoded)
     *
     * @return array Resposta decodificada da API
     * @throws Exception Em caso de erro HTTP ou da API
     */
    private function makeRequest($method, $endpoint, $data = [])
    {
        $url = $this->apiBase . $endpoint;

        $ch = curl_init();

        // Configurar método HTTP
        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        } elseif ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        }

        // Headers da requisição
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/x-www-form-urlencoded',
            'Stripe-Version: 2023-10-16', // API version
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        // Erro de conexão
        if ($response === false) {
            throw new Exception("Erro na conexão com Stripe: {$error}");
        }

        // Decodificar resposta
        $decoded = json_decode($response, true);

        if ($decoded === null) {
            throw new Exception("Resposta inválida da API Stripe: {$response}");
        }

        // Erro da API Stripe
        if ($httpCode >= 400) {
            $message = $decoded['error']['message'] ?? 'Erro desconhecido';
            $type = $decoded['error']['type'] ?? 'api_error';
            $code = $decoded['error']['code'] ?? null;

            throw new Exception("Erro Stripe [{$type}] ({$httpCode}): {$message}" . ($code ? " - {$code}" : ''));
        }

        return $decoded;
    }

    /**
     * Converte valor em reais para centavos
     *
     * @param float $value Valor em reais (ex: 49.90)
     * @return int Valor em centavos (ex: 4990)
     */
    public static function toCents($value)
    {
        return (int)round($value * 100);
    }

    /**
     * Converte valor em centavos para reais
     *
     * @param int $cents Valor em centavos (ex: 4990)
     * @return float Valor em reais (ex: 49.90)
     */
    public static function fromCents($cents)
    {
        return $cents / 100;
    }
}
