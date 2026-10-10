<?php

declare(strict_types=1);

namespace App\Services\Security;

final class VpsRuntimeKeys
{
    public static function cronSecret(): string
    {
        return self::key('cron', 'CRON_SECRET');
    }

    public static function whatsAppMenuHashKey(): string
    {
        return self::key('whatsapp-menu', 'WHATSAPP_MENU_HASH_KEY');
    }

    private static function key(string $purpose, string $legacyEnvironmentName): string
    {
        if (getenv('VPS_DERIVE_RUNTIME_KEYS') !== 'true') {
            return trim((string) getenv($legacyEnvironmentName));
        }

        $root = (string) getenv('JWT_SECRET');
        if (strlen($root) < 32) {
            // Never fall back to the old, publicly known deployment keys.
            return '';
        }

        return hash_hmac('sha256', 'klubecash/vps/' . $purpose . '/v1', $root);
    }
}
