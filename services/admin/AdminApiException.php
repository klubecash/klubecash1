<?php

declare(strict_types=1);

namespace App\Services\Admin;

use RuntimeException;

final class AdminApiException extends RuntimeException
{
    /** @param array<string, string[]> $errors */
    public function __construct(
        string $message,
        public readonly int $httpStatus = 422,
        public readonly array $errors = []
    ) {
        parent::__construct($message);
    }
}
