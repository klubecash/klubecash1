<?php
declare(strict_types=1);
namespace App\Services\WhatsApp;
use PDO;
use PDOException;
final class PdoWahaWebhookStore implements WahaWebhookStore
{
    public function __construct(private PDO $db) {}
    public function enqueue(string $requestId, string $eventId, string $eventType, string $payloadJson, bool $fromMe): int|false
    {
        try {
            $statement = $this->db->prepare("INSERT INTO waha_webhook_events (request_id,event_id,event_type,payload_json,from_me,status,available_at) VALUES (:request_id,:event_id,:event_type,:payload,:from_me,:status,NOW())");
            // Mensagens fromMe tambem entram na fila: o processador diferencia
            // mensagens da API de uma resposta humana e pausa o bot quando necessario.
            $statement->execute([':request_id' => $requestId, ':event_id' => $eventId, ':event_type' => $eventType, ':payload' => $payloadJson, ':from_me' => $fromMe ? 1 : 0, ':status' => 'pending']);
            return (int) $this->db->lastInsertId();
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') return false;
            throw $exception;
        }
    }
}
