<?php
declare(strict_types=1);
namespace App\Services\WhatsApp;
interface WahaWebhookStore
{
    /** Retorna false quando a chave ja existe. */
    public function enqueue(string $requestId, string $eventId, string $eventType, string $payloadJson, bool $fromMe): bool;
}
