<?php

declare(strict_types=1);

namespace App\Services\Giftback;

final class GiftbackException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 422)
    {
        parent::__construct($message);
    }
}
