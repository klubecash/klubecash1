<?php

declare(strict_types=1);

namespace App\Services\Billing;

use RuntimeException;

final class TransparentPaymentException extends RuntimeException
{
    /** @param array<string, string[]> $errors */
    public function __construct(
        string $message,
        public readonly int $httpStatus = 422,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }
}
