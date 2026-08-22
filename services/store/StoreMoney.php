<?php

declare(strict_types=1);

namespace App\Services\Store;

final class StoreMoney
{
    public static function toCents(int|float|string|null $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }

        return (int) round(((float) $value) * 100, 0, PHP_ROUND_HALF_UP);
    }

    public static function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    public static function percentage(int $baseCents, int|float|string $percentage): int
    {
        $basisPoints = (int) round(((float) $percentage) * 100, 0, PHP_ROUND_HALF_UP);
        return intdiv(($baseCents * $basisPoints) + 5000, 10000);
    }
}
