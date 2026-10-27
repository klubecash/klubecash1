<?php

declare(strict_types=1);

namespace App\Services\Partner;

/** Return address after a KlubeCash signup started in the Klube Pass. Only listed origins pass. */
final class PassReturnUrl
{
    /** @return list<string> */
    public static function allowedOrigins(): array
    {
        $configured = trim((string) getenv('PASS_RETURN_ORIGINS'));
        $origins = array_filter(array_map('trim', explode(',', $configured !== '' ? $configured : 'https://pass.grupoklube.online')));
        return array_values(array_map(static fn (string $origin): string => rtrim(strtolower($origin), '/'), $origins));
    }

    public static function safe(mixed $value): ?string
    {
        if (!is_string($value) || $value === '' || strlen($value) > 500 || preg_match('/[\x00-\x1F\x7F\\\\]/', $value) === 1) { return null; }
        $parts = parse_url($value);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) { return null; }
        $origin = strtolower($parts['scheme'] . '://' . $parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');
        return in_array($origin, self::allowedOrigins(), true) ? $value : null;
    }
}
