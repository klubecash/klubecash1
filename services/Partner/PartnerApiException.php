<?php

declare(strict_types=1);

namespace App\Services\Partner;

use RuntimeException;

final class PartnerApiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 422, public readonly string $errorCode = 'invalid_request')
    {
        parent::__construct($message);
    }
}
