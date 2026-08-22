<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use RuntimeException;

final class WhatsAppMenuConfig
{
    public function __construct(
        public readonly bool $menuEnabled,
        public readonly bool $merchantAuthEnabled,
        public readonly bool $salesEnabled,
        public readonly string $hashKey,
        public readonly string $siteUrl,
        public readonly string $publicNumber = ''
    ) {
        if (($menuEnabled || $merchantAuthEnabled || $salesEnabled) && strlen($hashKey) < 32) {
            throw new RuntimeException('WHATSAPP_MENU_HASH_KEY deve possuir pelo menos 32 caracteres.');
        }
    }

    public static function fromEnvironment(): self
    {
        $menu = self::flag('WHATSAPP_MENU_ENABLED');
        $merchant = $menu && self::flag('WHATSAPP_MERCHANT_AUTH_ENABLED');
        $sales = $merchant && self::flag('WHATSAPP_SALES_ENABLED');
        $hashKey = trim((string) getenv('WHATSAPP_MENU_HASH_KEY'));
        if ($hashKey === '') {
            $hashKey = trim((string) getenv('WAHA_WEBHOOK_HMAC_KEY'));
        }
        $siteUrl = rtrim((string) (getenv('SITE_URL') ?: 'https://www.klubecash.com'), '/');
        $publicNumber = preg_replace('/\D+/', '', (string) getenv('WHATSAPP_PUBLIC_NUMBER')) ?? '';

        return new self($menu, $merchant, $sales, $hashKey, $siteUrl, $publicNumber);
    }

    public function senderKey(string $canonicalPhone): string
    {
        return hash_hmac('sha256', $canonicalPhone, $this->hashKey);
    }

    public function encrypt(array $payload): string
    {
        $plain = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (function_exists('openssl_encrypt')) {
            $iv = random_bytes(12);
            $tag = '';
            $ciphertext = \openssl_encrypt(
                $plain,
                'aes-256-gcm',
                hash('sha256', $this->hashKey, true),
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );
            if ($ciphertext === false) {
                throw new RuntimeException('Nao foi possivel proteger o estado do WhatsApp.');
            }
            return self::base64Url("\x01" . $iv . $tag . $ciphertext);
        }

        // Fallback autenticado para instalacoes PHP sem a extensao OpenSSL.
        // Uma chave e usada para o fluxo de cifra e outra para o MAC.
        $nonce = random_bytes(16);
        $encryptionKey = hash_hmac('sha256', 'whatsapp-state-encryption', $this->hashKey, true);
        $macKey = hash_hmac('sha256', 'whatsapp-state-authentication', $this->hashKey, true);
        $ciphertext = self::xorStream($plain, $encryptionKey, $nonce);
        $tag = hash_hmac('sha256', $nonce . $ciphertext, $macKey, true);
        return self::base64Url("\x02" . $nonce . $tag . $ciphertext);
    }

    /** @return array<string,mixed> */
    public function decrypt(?string $payload): array
    {
        if ($payload === null || trim($payload) === '') {
            return [];
        }
        $raw = self::base64UrlDecode($payload);
        if ($raw === false || strlen($raw) < 2) {
            return [];
        }
        $version = ord($raw[0]);
        $plain = false;
        if ($version === 1 && function_exists('openssl_decrypt') && strlen($raw) >= 30) {
            $iv = substr($raw, 1, 12);
            $tag = substr($raw, 13, 16);
            $plain = \openssl_decrypt(
                substr($raw, 29),
                'aes-256-gcm',
                hash('sha256', $this->hashKey, true),
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );
        } elseif ($version === 2 && strlen($raw) >= 50) {
            $nonce = substr($raw, 1, 16);
            $tag = substr($raw, 17, 32);
            $ciphertext = substr($raw, 49);
            $macKey = hash_hmac('sha256', 'whatsapp-state-authentication', $this->hashKey, true);
            $expected = hash_hmac('sha256', $nonce . $ciphertext, $macKey, true);
            if (hash_equals($expected, $tag)) {
                $encryptionKey = hash_hmac('sha256', 'whatsapp-state-encryption', $this->hashKey, true);
                $plain = self::xorStream($ciphertext, $encryptionKey, $nonce);
            }
        }
        if (!is_string($plain)) {
            return [];
        }
        $decoded = json_decode($plain, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function flag(string $name): bool
    {
        return in_array(strtolower(trim((string) getenv($name))), ['1', 'true', 'yes', 'on'], true);
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string|false
    {
        $padding = strlen($value) % 4;
        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }
        return base64_decode(strtr($value, '-_', '+/'), true);
    }

    private static function xorStream(string $value, string $key, string $nonce): string
    {
        $result = '';
        $length = strlen($value);
        for ($offset = 0, $counter = 0; $offset < $length; $offset += 32, $counter++) {
            $stream = hash_hmac('sha256', $nonce . pack('N', $counter), $key, true);
            $chunk = substr($value, $offset, 32);
            $result .= $chunk ^ substr($stream, 0, strlen($chunk));
        }
        return $result;
    }
}
