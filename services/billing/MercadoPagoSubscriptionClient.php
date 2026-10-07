<?php

declare(strict_types=1);

namespace App\Services\Billing;

use RuntimeException;

final class MercadoPagoSubscriptionClient
{
    private string $token;
    private string $baseUrl;

    public function __construct(?string $token = null, ?string $baseUrl = null)
    {
        $this->token = trim($token ?? (defined('MP_ACCESS_TOKEN') ? (string) MP_ACCESS_TOKEN : (getenv('MP_ACCESS_TOKEN') ?: '')));
        $this->baseUrl = rtrim($baseUrl ?? (defined('MP_BASE_URL') ? (string) MP_BASE_URL : 'https://api.mercadopago.com'), '/');
    }

    /** @return array<string, mixed> */
    public function createSubscription(array $payload, string $idempotencyKey): array
    {
        return $this->request('POST', '/preapproval', $payload, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function getSubscription(string $externalId): array
    {
        return $this->request('GET', '/preapproval/' . rawurlencode($externalId));
    }

    /** @return array<string, mixed> */
    public function updateSubscription(string $externalId, array $payload, string $idempotencyKey): array
    {
        return $this->request('PUT', '/preapproval/' . rawurlencode($externalId), $payload, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function createRecoveryPreference(array $payload, string $idempotencyKey): array
    {
        return $this->request('POST', '/checkout/preferences', $payload, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function createPayment(array $payload, string $idempotencyKey): array
    {
        return $this->request('POST', '/v1/payments', $payload, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function getPayment(string $paymentId): array
    {
        return $this->request('GET', '/v1/payments/' . rawurlencode($paymentId));
    }

    /** @return array<string, mixed> */
    private function request(string $method, string $path, ?array $payload = null, ?string $idempotencyKey = null): array
    {
        if ($this->token === '') {
            throw new RuntimeException('Mercado Pago não está configurado.');
        }

        $ch = curl_init($this->baseUrl . $path);
        if ($ch === false) {
            throw new RuntimeException('Não foi possível iniciar a comunicação com o gateway.');
        }
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json',
            'User-Agent: KlubeCash/3.0 subscriptions',
        ];
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $headers[] = 'X-Idempotency-Key: ' . substr($idempotencyKey, 0, 128);
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => defined('MP_TIMEOUT') ? (int) MP_TIMEOUT : 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if ($payload !== null && $method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($body === false || $error !== '') {
            throw new RuntimeException('Mercado Pago indisponível no momento.');
        }
        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Mercado Pago retornou uma resposta inválida.');
        }
        if ($status < 200 || $status >= 300) {
            $detail = $this->safeGatewayDetail($decoded);
            throw new RuntimeException('Mercado Pago recusou a operação (' . $status . ')' . ($detail !== '' ? ': ' . $detail : '.') );
        }
        return $decoded;
    }

    /** @param array<string, mixed> $response */
    private function safeGatewayDetail(array $response): string
    {
        $parts = [];
        foreach (['message', 'error', 'description'] as $key) {
            if (isset($response[$key]) && is_scalar($response[$key])) {
                $parts[] = trim((string) $response[$key]);
            }
        }
        if (isset($response['cause']) && is_array($response['cause'])) {
            foreach (array_slice($response['cause'], 0, 2) as $cause) {
                if (is_array($cause)) {
                    foreach (['code', 'description', 'message'] as $key) {
                        if (isset($cause[$key]) && is_scalar($cause[$key])) {
                            $parts[] = trim((string) $cause[$key]);
                            break;
                        }
                    }
                }
            }
        }
        $detail = implode('; ', array_values(array_filter($parts, static fn (string $part): bool => $part !== '')));
        return function_exists('mb_substr') ? mb_substr($detail, 0, 240) : substr($detail, 0, 240);
    }
}
