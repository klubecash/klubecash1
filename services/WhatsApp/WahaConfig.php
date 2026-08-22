<?php
declare(strict_types=1);
namespace App\Services\WhatsApp;
use RuntimeException;
final class WahaConfig
{
    public function __construct(public readonly string $baseUrl, public readonly string $apiKey, public readonly string $session, public readonly string $webhookHmacKey, public readonly int $timeoutSeconds = 15)
    {
        $parts = parse_url($baseUrl);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) throw new RuntimeException('WAHA_BASE_URL deve ser uma URL HTTPS valida.');
        if ($apiKey === '') throw new RuntimeException('WAHA_API_KEY nao configurada.');
        if ($session === '' || preg_match('/^[A-Za-z0-9_-]+$/', $session) !== 1) throw new RuntimeException('WAHA_SESSION invalida.');
        if ($webhookHmacKey === '') throw new RuntimeException('WAHA_WEBHOOK_HMAC_KEY nao configurada.');
    }
    public static function fromEnvironment(): self
    {
        $values = [];
        foreach (['WAHA_BASE_URL', 'WAHA_API_KEY', 'WAHA_SESSION', 'WAHA_WEBHOOK_HMAC_KEY'] as $name) {
            $values[$name] = trim((string) getenv($name));
            if ($values[$name] === '') throw new RuntimeException("Variavel de ambiente obrigatoria ausente: {$name}.");
        }
        return new self(rtrim($values['WAHA_BASE_URL'], '/'), $values['WAHA_API_KEY'], $values['WAHA_SESSION'], $values['WAHA_WEBHOOK_HMAC_KEY']);
    }
}
