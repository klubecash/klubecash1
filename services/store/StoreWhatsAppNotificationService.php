<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Logger;
use App\Services\WhatsApp\CurlWahaHttpClient;
use App\Services\WhatsApp\WahaConfig;
use App\Services\WhatsApp\WahaException;
use App\Services\WhatsApp\WahaSchemaManager;
use App\Services\WhatsApp\WahaService;
use InvalidArgumentException;
use PDO;
use Throwable;

final class StoreWhatsAppNotificationService
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(private PDO $db)
    {
    }

    public function queue(int $transactionId, int $storeId): string
    {
        if (!$this->enabled()) {
            return 'disabled';
        }

        (new WahaSchemaManager($this->db))->migrate();
        $statement = $this->db->prepare(
            "INSERT IGNORE INTO store_whatsapp_deliveries
                (transaction_id,loja_id,status,attempts,available_at)
             VALUES (:transaction_id,:store_id,'pending',0,NOW())"
        );
        $statement->execute([':transaction_id' => $transactionId, ':store_id' => $storeId]);

        $status = $this->db->prepare(
            'SELECT status FROM store_whatsapp_deliveries WHERE transaction_id=:transaction_id LIMIT 1'
        );
        $status->execute([':transaction_id' => $transactionId]);

        return (string) ($status->fetchColumn() ?: 'pending');
    }

    public function queueAndProcess(int $transactionId, int $storeId): string
    {
        $status = $this->queue($transactionId, $storeId);
        if ($status !== 'pending') {
            return $status;
        }

        $delivery = $this->db->prepare(
            'SELECT id FROM store_whatsapp_deliveries WHERE transaction_id=:transaction_id LIMIT 1'
        );
        $delivery->execute([':transaction_id' => $transactionId]);
        $deliveryId = (int) ($delivery->fetchColumn() ?: 0);

        return $deliveryId > 0 ? $this->processOne($deliveryId) : 'failed';
    }

    /** @return array{available:int,processed:int,sent:int,pending:int,ignored:int,failed:int} */
    public function processPending(int $limit = 50): array
    {
        if (!$this->enabled()) {
            return ['available' => 0, 'processed' => 0, 'sent' => 0, 'pending' => 0, 'ignored' => 0, 'failed' => 0];
        }

        (new WahaSchemaManager($this->db))->migrate();
        $limit = max(1, min(100, $limit));
        $items = $this->db->query(
            "SELECT id FROM store_whatsapp_deliveries
             WHERE status='pending' AND available_at<=NOW()
             ORDER BY available_at,id LIMIT {$limit}"
        )->fetchAll(PDO::FETCH_COLUMN);

        $stats = ['available' => count($items), 'processed' => 0, 'sent' => 0, 'pending' => 0, 'ignored' => 0, 'failed' => 0];
        foreach ($items as $id) {
            $status = $this->processOne((int) $id);
            if ($status === 'skipped') {
                continue;
            }
            $stats['processed']++;
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
        }

        return $stats;
    }

    private function processOne(int $deliveryId): string
    {
        $claim = $this->db->prepare(
            "UPDATE store_whatsapp_deliveries
             SET status='processing',attempts=attempts+1
             WHERE id=:id AND status='pending' AND available_at<=NOW()"
        );
        $claim->execute([':id' => $deliveryId]);
        if ($claim->rowCount() !== 1) {
            return 'skipped';
        }

        $details = $this->db->prepare(
            'SELECT d.attempts,t.codigo_transacao,t.valor_total,t.valor_cliente,
                    u.nome customer_name,u.telefone customer_phone,l.nome_fantasia store_name
             FROM store_whatsapp_deliveries d
             JOIN transacoes_cashback t ON t.id=d.transaction_id AND t.loja_id=d.loja_id
             JOIN usuarios u ON u.id=t.usuario_id
             JOIN lojas l ON l.id=t.loja_id
             WHERE d.id=:id LIMIT 1'
        );
        $details->execute([':id' => $deliveryId]);
        $row = $details->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $this->finish($deliveryId, 'failed', null, 'sale_not_found');
            return 'failed';
        }

        $phone = trim((string) ($row['customer_phone'] ?? ''));
        if ($phone === '') {
            $this->finish($deliveryId, 'ignored', null, 'phone_missing');
            return 'ignored';
        }

        try {
            $message = $this->message($row);
            $response = (new WahaService(WahaConfig::fromEnvironment(), new CurlWahaHttpClient()))
                ->sendText($phone, $message);
            $providerId = $this->providerMessageId($response);
            $this->finish($deliveryId, 'sent', $providerId, null);
            Logger::info('waha.sale_notification.sent', ['delivery_id' => $deliveryId]);
            return 'sent';
        } catch (InvalidArgumentException) {
            $this->finish($deliveryId, 'failed', null, 'invalid_phone');
            return 'failed';
        } catch (WahaException $exception) {
            if ($exception->deliveryUnknown) {
                $this->finish($deliveryId, 'failed', null, 'delivery_unknown');
                Logger::warning('waha.sale_notification.uncertain', ['delivery_id' => $deliveryId]);
                return 'failed';
            }

            $attempts = (int) ($row['attempts'] ?? 1);
            if ($exception->transient && $attempts < self::MAX_ATTEMPTS) {
                $delay = min(60, 2 ** max(1, $attempts));
                $retry = $this->db->prepare(
                    "UPDATE store_whatsapp_deliveries
                     SET status='pending',available_at=DATE_ADD(NOW(),INTERVAL {$delay} MINUTE),last_error_code='provider_unavailable'
                     WHERE id=:id"
                );
                $retry->execute([':id' => $deliveryId]);
                return 'pending';
            }

            $this->finish($deliveryId, 'failed', null, 'provider_rejected');
            return 'failed';
        } catch (Throwable $exception) {
            // Nao repetir automaticamente uma falha desconhecida: ela pode ter
            // acontecido depois de o provedor aceitar a mensagem.
            $this->finish($deliveryId, 'failed', null, 'internal_error');
            Logger::warning('waha.sale_notification.failed', [
                'delivery_id' => $deliveryId,
                'exception' => get_class($exception),
            ]);
            return 'failed';
        }
    }

    /** @param array<string,mixed> $row */
    private function message(array $row): string
    {
        $name = trim((string) $row['customer_name']);
        $store = trim((string) $row['store_name']);
        $code = trim((string) $row['codigo_transacao']);
        $purchase = number_format((float) $row['valor_total'], 2, ',', '.');
        $cashback = number_format((float) $row['valor_cliente'], 2, ',', '.');

        return "Olá, {$name}! Sua compra na {$store} foi aprovada.\n\n"
            . "Código: {$code}\n"
            . "Valor da compra: R$ {$purchase}\n"
            . "Cashback creditado: R$ {$cashback}\n\n"
            . 'Obrigado por usar o Klube Cash.';
    }

    /** @param array<string,mixed> $response */
    private function providerMessageId(array $response): ?string
    {
        $candidates = [
            $response['id'] ?? null,
            $response['key']['id'] ?? null,
            $response['_data']['id']['_serialized'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            if (is_string($candidate) || is_int($candidate)) {
                $value = trim((string) $candidate);
                if ($value !== '') {
                    return substr($value, 0, 191);
                }
            }
        }
        return null;
    }

    private function finish(int $deliveryId, string $status, ?string $providerId, ?string $errorCode): void
    {
        $statement = $this->db->prepare(
            'UPDATE store_whatsapp_deliveries
             SET status=:status,provider_message_id=:provider_id,last_error_code=:error_code,processed_at=NOW()
             WHERE id=:id'
        );
        $statement->bindValue(':status', $status);
        $statement->bindValue(':provider_id', $providerId, $providerId === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':error_code', $errorCode, $errorCode === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':id', $deliveryId, PDO::PARAM_INT);
        $statement->execute();
    }

    private function enabled(): bool
    {
        return trim((string) getenv('WAHA_BASE_URL')) !== ''
            && trim((string) getenv('WAHA_API_KEY')) !== ''
            && trim((string) getenv('WAHA_SESSION')) !== ''
            && trim((string) getenv('WAHA_WEBHOOK_HMAC_KEY')) !== '';
    }
}
