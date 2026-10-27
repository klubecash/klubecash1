<?php

declare(strict_types=1);

namespace App\Services\Partner;

use App\Services\WhatsApp\WahaException;
use App\Services\WhatsApp\WahaService;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use PDOException;
use Throwable;

/**
 * Avisa o cliente quando a sobra de um evento vira saldo KlubeCash. A fila
 * pass_credit_notices tem uma linha por movimento e canal; o WhatsApp segue as
 * regras dos avisos de vencimento (janela 09–20 h, 5 tentativas, entrega
 * incerta nunca é reenviada) e o e-mail entra na email_queue existente.
 * Desligado por padrão (PASS_CREDIT_NOTICES_ENABLED).
 */
final class PassCreditNoticeService
{
    private const SEND_FROM_HOUR = 9;
    private const SEND_UNTIL_HOUR = 20;
    private const MAX_ATTEMPTS = 5;
    private const STUCK_MINUTES = 15;

    private DateTimeZone $zone;

    public function __construct(private PDO $db, private ?WahaService $waha = null, private ?DateTimeImmutable $now = null)
    {
        $this->zone = new DateTimeZone('America/Sao_Paulo');
    }

    public static function enabled(): bool
    {
        return filter_var(getenv('PASS_CREDIT_NOTICES_ENABLED') ?: 'false', FILTER_VALIDATE_BOOL);
    }

    /** Called inside the credit transaction: the notice exists only if the credit commits. */
    public static function enqueue(PDO $db, int $movementId, int $userId, int $storeId, int $cents, ?string $expiresAt): void
    {
        if (!self::enabled()) { return; }
        $now = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');
        $insert = $db->prepare('INSERT IGNORE INTO pass_credit_notices(movement_id,usuario_id,loja_id,channel,amount_cents,expires_at,scheduled_for,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)');
        foreach (['whatsapp', 'email'] as $channel) {
            $insert->execute([$movementId, $userId, $storeId, $channel, $cents, $expiresAt, $now, $now, $now]);
        }
    }

    public static function message(string $name, string $storeName, int $cents, ?string $validUntil): string
    {
        $first = trim($name) !== '' ? trim(explode(' ', trim($name))[0]) : 'cliente';
        $money = 'R$ ' . number_format($cents / 100, 2, ',', '.');
        $deadline = $validUntil !== null ? " Ele vale ate {$validUntil}." : '';
        return "Ola, {$first}! A sobra do seu credito no evento virou *{$money}* de saldo KlubeCash na *{$storeName}*.{$deadline}\n\nUse no proximo evento ou consulte em klubecash.com";
    }

    private function now(): DateTimeImmutable
    {
        return ($this->now ?? new DateTimeImmutable('now', $this->zone))->setTimezone($this->zone);
    }

    /** @return array<string,mixed> */
    public function run(int $limit = 30): array
    {
        if (!self::enabled()) { return ['enabled' => false]; }
        try {
            return ['enabled' => true] + $this->process(max(1, min(100, $limit)));
        } catch (PDOException $error) {
            error_log('pass.notice.failed ' . get_class($error) . ' ' . $error->getCode());
            return ['enabled' => true, 'error' => 'migration_or_database_unavailable'];
        }
    }

    /** @return array<string,int|bool> */
    private function process(int $limit): array
    {
        $now = $this->now();
        $stamp = $now->format('Y-m-d H:i:s');
        $stats = ['sent' => 0, 'queuedEmail' => 0, 'skipped' => 0, 'pending' => 0, 'failed' => 0, 'outsideWindow' => false];
        $this->db->prepare("UPDATE pass_credit_notices SET status='delivery_unknown',updated_at=? WHERE status='processing' AND updated_at<?")
            ->execute([$stamp, $now->sub(new DateInterval('PT' . self::STUCK_MINUTES . 'M'))->format('Y-m-d H:i:s')]);

        // E-mail goes to the existing queue at any hour; its own worker delivers it.
        foreach ($this->due('email', $stamp, $limit) as $row) {
            if (!$this->claim((int) $row['id'], $stamp)) { continue; }
            $email = trim((string) $row['email']);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $this->finish((int) $row['id'], 'skipped', 'email_missing', $stamp); $stats['skipped']++; continue; }
            $validUntil = $this->validUntil($row['expires_at']);
            $body = nl2br(htmlspecialchars(self::message((string) $row['nome'], (string) $row['store_name'], (int) $row['amount_cents'], $validUntil), ENT_QUOTES, 'UTF-8'));
            $this->db->prepare("INSERT INTO email_queue(to_email,to_name,subject,message,status) VALUES(?,?,?,?,'pending')")
                ->execute([$email, (string) $row['nome'], 'Sua sobra do evento virou saldo KlubeCash', str_replace(['*'], '', $body)]);
            $this->finish((int) $row['id'], 'sent', null, $stamp);
            $stats['queuedEmail']++;
        }

        $hour = (int) $now->format('G');
        if ($hour < self::SEND_FROM_HOUR || $hour >= self::SEND_UNTIL_HOUR) { $stats['outsideWindow'] = true; return $stats; }
        foreach ($this->due('whatsapp', $stamp, $limit) as $row) {
            if (!$this->claim((int) $row['id'], $stamp)) { continue; }
            $phone = trim((string) $row['telefone']);
            if ($phone === '') { $this->finish((int) $row['id'], 'skipped', 'phone_missing', $stamp); $stats['skipped']++; continue; }
            try {
                if ($this->waha === null) { throw new WahaException('WhatsApp nao configurado.', 503, true); }
                $this->waha->sendText($phone, self::message((string) $row['nome'], (string) $row['store_name'], (int) $row['amount_cents'], $this->validUntil($row['expires_at'])));
                $this->finish((int) $row['id'], 'sent', null, $stamp);
                $stats['sent']++;
            } catch (InvalidArgumentException) {
                $this->finish((int) $row['id'], 'skipped', 'invalid_phone', $stamp); $stats['skipped']++;
            } catch (WahaException $error) {
                if ($error->deliveryUnknown) { $this->finish((int) $row['id'], 'delivery_unknown', 'delivery_unknown', $stamp); $stats['failed']++; continue; }
                $attempts = (int) $row['attempts'] + 1;
                if ($error->transient && $attempts < self::MAX_ATTEMPTS) {
                    $delay = min(60, 2 ** max(1, $attempts));
                    $this->db->prepare("UPDATE pass_credit_notices SET status='pending',scheduled_for=?,last_error_code='provider_unavailable',updated_at=? WHERE id=?")
                        ->execute([$now->add(new DateInterval('PT' . $delay . 'M'))->format('Y-m-d H:i:s'), $stamp, $row['id']]);
                    $stats['pending']++;
                    continue;
                }
                $this->finish((int) $row['id'], 'failed', 'provider_rejected', $stamp); $stats['failed']++;
            } catch (Throwable $error) {
                error_log('pass.notice.send_failed ' . get_class($error));
                $this->finish((int) $row['id'], 'failed', 'internal_error', $stamp); $stats['failed']++;
            }
        }
        return $stats;
    }

    /** @return list<array<string,mixed>> */
    private function due(string $channel, string $stamp, int $limit): array
    {
        $stmt = $this->db->prepare("SELECT n.id,n.amount_cents,n.expires_at,n.attempts,u.nome,u.email,u.telefone,l.nome_fantasia store_name
            FROM pass_credit_notices n JOIN usuarios u ON u.id=n.usuario_id JOIN lojas l ON l.id=n.loja_id
            WHERE n.channel=? AND n.status='pending' AND n.scheduled_for<=? ORDER BY n.id LIMIT " . $limit);
        $stmt->execute([$channel, $stamp]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function claim(int $id, string $stamp): bool
    {
        $stmt = $this->db->prepare("UPDATE pass_credit_notices SET status='processing',attempts=attempts+1,updated_at=? WHERE id=? AND status='pending'");
        $stmt->execute([$stamp, $id]);
        return $stmt->rowCount() === 1;
    }

    private function finish(int $id, string $status, ?string $error, string $stamp): void
    {
        $this->db->prepare('UPDATE pass_credit_notices SET status=?,last_error_code=?,sent_at=?,updated_at=? WHERE id=?')
            ->execute([$status, $error, $status === 'sent' ? $stamp : null, $stamp, $id]);
    }

    private function validUntil(?string $expiresAt): ?string
    {
        return $expiresAt ? (new DateTimeImmutable($expiresAt, $this->zone))->sub(new DateInterval('P1D'))->format('d/m/Y') : null;
    }
}
