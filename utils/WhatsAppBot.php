<?php
/**
 * Utilitario para integrar com WPPConnect (WhatsApp) usando cURL nativo.
 */
if (!defined('WHATSAPP_ENABLED')) {
    require_once __DIR__ . '/../config/whatsapp.php';
}

class WhatsAppBot
{
    private const API_PREFIX = '/api';

    private function __construct()
    {
    }

    /**
     * Envia a notificacao padrao de nova transacao.
     */
    public static function sendNewTransactionNotification(string $phone, array $transactionData, array $options = []): array
    {
        $message = self::buildNewTransactionMessage($transactionData, $options);
        return self::sendTextMessage($phone, $message, ['tag' => 'transaction:new'] + $options);
    }

    /**
     * Envia a notificacao padrao informando cashback liberado.
     */
    public static function sendCashbackReleasedNotification(string $phone, array $transactionData, array $options = []): array
    {
        $message = self::buildCashbackMessage($transactionData, $options);
        return self::sendTextMessage($phone, $message, ['tag' => 'cashback:released'] + $options);
    }

    /**
     * Envia mensagem de texto simples.
     */
    public static function sendTextMessage(string $phone, string $message, array $options = []): array
    {
        $payload = array_filter([
            'phone' => self::normalizePhone($phone),
            'message' => trim($message),
            'isGroup' => (bool)($options['is_group'] ?? false),
            'linkPreview' => (bool)($options['link_preview'] ?? false),
        ], static fn ($value) => $value !== null && $value !== '');

        return self::callEndpoint('/send-message', $payload, $options);
    }

    /**
     * Envia template com suporte a botoes e listas.
     */
    public static function sendTemplateMessage(string $phone, string $templateName, array $components = [], array $options = []): array
    {
        $payload = [
            'phone' => self::normalizePhone($phone),
            'name' => $templateName,
            'language' => [
                'policy' => 'deterministic',
                'code' => $options['language'] ?? WHATSAPP_TEMPLATE_LANGUAGE,
            ],
            'parameters' => self::normalizeTemplateComponents($components),
        ];

        return self::callEndpoint('/send-template', $payload, $options);
    }

    /**
     * Envia arquivos ou imagens usando URL ou base64 inline.
     */
    public static function sendMediaMessage(string $phone, string $mediaSource, string $caption = '', string $mediaType = 'image', array $options = []): array
    {
        $normalizedPhone = self::normalizePhone($phone);
        $payload = [
            'phone' => $normalizedPhone,
            'type' => strtolower($mediaType),
            'caption' => $caption,
        ];

        if (!empty($options['base64'])) {
            $payload['base64'] = $options['base64'];
            $payload['fileName'] = $options['file_name'] ?? ('media_' . time());
        } else {
            $payload['path'] = $mediaSource;
            $payload['fileName'] = $options['file_name'] ?? basename(parse_url($mediaSource, PHP_URL_PATH) ?? $mediaSource);
        }

        if (isset($options['view_once'])) {
            $payload['viewOnce'] = (bool)$options['view_once'];
        }

        return self::callEndpoint('/send-media', $payload, $options);
    }

    private static function callEndpoint(string $endpoint, array $payload, array $options = []): array
    {
        $result = [
            'success' => false,
            'message' => 'WhatsApp integration disabled',
            'payload' => $payload,
            'response' => null,
            'http_code' => null,
            'ack' => null,
        ];

        try {
            if (!WHATSAPP_ENABLED) {
                return $result;
            }

            $url = self::buildUrl($endpoint);
            $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($jsonPayload === false) {
                throw new RuntimeException('Falha ao gerar payload JSON: ' . json_last_error_msg());
            }

            $headers = ['Content-Type: application/json'];
            if (WHATSAPP_API_TOKEN !== '') {
                $headers[] = 'Authorization: Bearer ' . WHATSAPP_API_TOKEN;
            }

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $jsonPayload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => (int)($options['timeout'] ?? WHATSAPP_HTTP_TIMEOUT),
                CURLOPT_CONNECTTIMEOUT => WHATSAPP_CONNECT_RETRIES,
                CURLOPT_HTTPHEADER => $headers,
            ]);

            $rawResponse = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $decoded = null;
            if ($rawResponse !== false && $rawResponse !== '') {
                $decoded = json_decode($rawResponse, true);
            }

            if ($curlError) {
                throw new RuntimeException($curlError);
            }

            $success = $httpCode >= 200 && $httpCode < 300;

            if (!$success) {
                $errorMessage = $decoded['message'] ?? $decoded['error'] ?? 'Falha ao enviar mensagem via WhatsApp.';
                throw new RuntimeException($errorMessage, $httpCode);
            }

            $result['success'] = true;
            $result['message'] = $decoded['status'] ?? 'Mensagem enviada para processamento.';
            $result['response'] = $decoded;
            $result['http_code'] = $httpCode;
            $result['ack'] = self::extractAck($decoded);

            self::log('info', 'WhatsApp API call succeeded', [
                'endpoint' => $endpoint,
                'tag' => $options['tag'] ?? null,
                'phone' => $payload['phone'] ?? null,
                'ack' => $result['ack'],
            ]);

            return $result;
        } catch (Throwable $exception) {
            $result['message'] = $exception->getMessage();
            $result['http_code'] = $exception->getCode() ?: 500;

            self::log('error', 'WhatsApp API call failed', [
                'endpoint' => $endpoint,
                'tag' => $options['tag'] ?? null,
                'payload' => $payload,
                'error' => $exception->getMessage(),
                'code' => $result['http_code'],
            ]);

            return $result;
        }
    }

    private static function buildNewTransactionMessage(array $data, array $options = []): string
    {
        $storeName = $data['nome_loja'] ?? $data['loja_nome'] ?? $data['loja'] ?? 'sua loja parceira';
        $cashback = isset($data['valor_cashback']) ? number_format((float)$data['valor_cashback'], 2, ',', '.') : null;
        $clientName = trim($data['cliente_nome'] ?? $data['cliente'] ?? '');
        $clientDisplay = $clientName !== '' ? $clientName : 'cliente';

        if ($cashback) {
            $giftbackLine = "Sua compra no {$storeName} voltou como R$ {$cashback} de Giftback direto no seu saldo KlubeCash.";
        } else {
            $giftbackLine = "Sua compra no {$storeName} voltou como Giftback direto no seu saldo KlubeCash.";
        }

        $lines = [
            "Olha so, {$clientDisplay}!",
            $giftbackLine,
            "Voce pode reutilizar esse valor em proximas compras no {$storeName}, continue colecionando beneficios!",
            '',
            "Lembrando: esse giftback esta disponivel apenas onde voce comprou."
        ];

        return implode("\n", $lines);
    }



    private static function buildCashbackMessage(array $data, array $options = []): string
    {
        $storeName = $data['nome_loja'] ?? $data['loja_nome'] ?? 'nossa rede parceira';
        $cashback = isset($data['valor_cashback']) ? number_format((float)$data['valor_cashback'], 2, ',', '.') : null;

        $lines = [
            '*Cashback liberado!*',
            "Loja: {$storeName}",
        ];

        if ($cashback) {
            $lines[] = "Voce recebeu: R$ {$cashback}";
        }

        $lines[] = $options['custom_footer'] ?? 'O valor ja esta disponivel para usar nas proximas compras!';

        return implode("\n", $lines);
    }

    private static function normalizeTemplateComponents(array $components): array
    {
        if ($components === []) {
            return [];
        }

        $normalized = [];

        foreach ($components as $component) {
            if (!is_array($component)) {
                continue;
            }

            if (!isset($component['type'])) {
                continue;
            }

            $item = ['type' => $component['type']];

            if (isset($component['sub_type'])) {
                $item['sub_type'] = $component['sub_type'];
            }

            if (isset($component['parameters']) && is_array($component['parameters'])) {
                $item['parameters'] = [];
                foreach ($component['parameters'] as $parameter) {
                    if (is_array($parameter)) {
                        $item['parameters'][] = $parameter;
                    } else {
                        $item['parameters'][] = ['type' => 'text', 'text' => (string)$parameter];
                    }
                }
            }

            $normalized[] = $item;
        }

        if ($normalized === [] && $components !== []) {
            $normalized[] = [
                'type' => 'body',
                'parameters' => array_map(static fn ($value) => ['type' => 'text', 'text' => (string)$value], $components),
            ];
        }

        return $normalized;
    }

    private static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (strlen($digits) < 10) {
            throw new InvalidArgumentException('Telefone invalido para envio via WhatsApp.');
        }

        if (strlen($digits) === 10) {
            $digits = preg_replace('/^(\d{2})(\d{4})(\d{4})$/', '$19$2$3', $digits);
        }

        if (strlen($digits) === 11 && strpos($digits, '55') !== 0) {
            $digits = '55' . $digits;
        }

        return $digits;
    }

    private static function extractAck(?array $response): ?array
    {
        if (!$response) {
            return null;
        }

        
        $candidates = [$response];
        if (isset($response['response']) && is_array($response['response'])) {
            $candidates[] = $response['response'];
        }
        if (isset($response['data']) && is_array($response['data'])) {
            $candidates[] = $response['data'];
        }

        $ackData = [];
        $keys = ['ack', 'status', 'statusText', 'statusMessage', 'messageId', 'id'];

        foreach ($candidates as $candidate) {
            foreach ($keys as $key) {
                if (is_array($candidate) && array_key_exists($key, $candidate)) {
                    $ackData[$key] = $candidate[$key];
                }
            }

            if (is_array($candidate) && isset($candidate['ackResult']) && is_array($candidate['ackResult'])) {
                $ackData['ackResult'] = $candidate['ackResult'];
            }
        }

        return $ackData !== [] ? $ackData : null;
    }

    private static function buildUrl(string $endpoint): string
    {
        $prefix = rtrim(WHATSAPP_BASE_URL, '/');
        $session = rawurlencode(WHATSAPP_SESSION_NAME);
        $path = '/' . ltrim($endpoint, '/');

        return $prefix . self::API_PREFIX . '/' . $session . $path;
    }

    private static function log(string $level, string $message, array $context = []): void
    {
        $logLine = sprintf('[WhatsApp][%s] %s', strtoupper($level), $message);
        if ($context) {
            $logLine .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (defined('WHATSAPP_LOG_PATH') && WHATSAPP_LOG_PATH) {
            $logDir = dirname(WHATSAPP_LOG_PATH);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0775, true);
            }
            error_log($logLine . PHP_EOL, 3, WHATSAPP_LOG_PATH);
        } else {
            error_log($logLine);
        }
    }
}
?>