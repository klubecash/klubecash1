<?php
declare(strict_types=1);
namespace App\Services\WhatsApp;
final class WahaWebhookHandler
{
    private const EVENTS = ['message', 'message.ack', 'session.status'];
    public function __construct(private WahaConfig $config, private WahaWebhookStore $store) {}
    /** @return array{status:int,body:array<string,mixed>} */
    public function handle(string $rawBody, string $signature, string $requestId = ''): array
    {
        if (!$this->validSignature($rawBody, $signature)) return ['status' => 401, 'body' => ['success' => false, 'error' => 'Assinatura invalida.']];
        try { $event = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { return ['status' => 400, 'body' => ['success' => false, 'error' => 'JSON invalido.']]; }
        if (!is_array($event) || ($event['session'] ?? null) !== $this->config->session) return ['status' => 403, 'body' => ['success' => false, 'error' => 'Sessao rejeitada.']];
        $type = (string) ($event['event'] ?? '');
        if (!in_array($type, self::EVENTS, true)) return ['status' => 200, 'body' => ['success' => true, 'ignored' => true]];
        $eventId = $this->eventId($event, $rawBody);
        $idempotencyKey = trim($requestId) !== '' ? trim($requestId) : $eventId;
        $fromMe = ($event['payload']['fromMe'] ?? $event['payload']['key']['fromMe'] ?? $event['payload']['_data']['id']['fromMe'] ?? false) === true;
        $queueId = $this->store->enqueue($idempotencyKey, $eventId, $type, $rawBody, $fromMe);
        return ['status' => 200, 'body' => [
            'success' => true,
            'duplicate' => $queueId === false,
            'ignored' => $fromMe && $type === 'message',
            '_queueId' => $queueId === false ? null : $queueId,
        ]];
    }
    public function validSignature(string $rawBody, string $signature): bool
    {
        $signature = strtolower(trim($signature));
        if (str_starts_with($signature, 'sha512=')) $signature = substr($signature, 7);
        $expected = hash_hmac('sha512', $rawBody, $this->config->webhookHmacKey);
        return strlen($signature) === strlen($expected) && hash_equals($expected, $signature);
    }

    /** @param array<string,mixed> $event */
    private function eventId(array $event, string $rawBody): string
    {
        $candidates = [
            $event['id'] ?? null,
            $event['payload']['id'] ?? null,
            $event['payload']['key']['id'] ?? null,
            $event['payload']['_data']['id']['_serialized'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            if (is_string($candidate) || is_int($candidate)) {
                $value = trim((string) $candidate);
                if ($value !== '') {
                    return substr($value, 0, 191);
                }
            }
        }

        // Alguns eventos do WAHA, como session.status, nao possuem id proprio.
        // O hash do corpo permanece igual nas retentativas e preserva idempotencia.
        return hash('sha256', $rawBody);
    }
}
