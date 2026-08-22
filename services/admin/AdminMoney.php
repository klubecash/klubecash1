<?php

declare(strict_types=1);

namespace App\Services\Admin;

final class AdminMoney
{
    public static function cents(int|float|string|null $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        return (int) round((float) $value * 100);
    }

    public static function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
