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
        $eventId = trim((string) ($event['id'] ?? ($event['payload']['id'] ?? '')));
        $idempotencyKey = trim($requestId) !== '' ? trim($requestId) : $eventId;
        if ($idempotencyKey === '' || $eventId === '') return ['status' => 400, 'body' => ['success' => false, 'error' => 'Identificador ausente.']];
        $fromMe = ($event['payload']['fromMe'] ?? false) === true;
        $created = $this->store->enqueue($idempotencyKey, $eventId, $type, $rawBody, $fromMe);
        return ['status' => 200, 'body' => ['success' => true, 'duplicate' => !$created, 'ignored' => $fromMe && $type === 'message']];
    }
    public function validSignature(string $rawBody, string $signature): bool
    {
        $signature = strtolower(trim($signature));
        if (str_starts_with($signature, 'sha512=')) $signature = substr($signature, 7);
        $expected = hash_hmac('sha512', $rawBody, $this->config->webhookHmacKey);
        return strlen($signature) === strlen($expected) && hash_equals($expected, $signature);
    }
}
