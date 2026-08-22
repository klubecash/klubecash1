<?php
declare(strict_types=1);
namespace App\Services\WhatsApp;
use RuntimeException;
final class WahaException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 502, public readonly bool $transient = false) { parent::__construct($message); }
}
