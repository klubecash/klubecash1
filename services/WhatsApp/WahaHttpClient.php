<?php
declare(strict_types=1);
namespace App\Services\WhatsApp;
interface WahaHttpClient
{
    /** @return array{status:int,body:string} */
    public function request(string $method, string $url, array $headers, ?string $body, int $timeoutSeconds): array;
}
