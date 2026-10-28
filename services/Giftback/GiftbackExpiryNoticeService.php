<?php

declare(strict_types=1);

namespace App\Services\Giftback;

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
 * Avisos de vencimento de giftback pelo WhatsApp.
 *
 * Somente le cashback_creditos; nunca altera saldo. Cada credito com validade
 * recebe uma agenda de avisos (20, 14, 7 e 1 dia antes, limitada a metade da
 * validade original). A tabela cashback_credito_avisos torna o agendamento
 * idempotente e registra o resultado de cada envio.
 */
final class GiftbackExpiryNoticeService
{
    /** Marcos em dias antes do ultimo dia de validade, do mais distante ao mais proximo. */
    public const MILESTONES = [20, 14, 7, 1];
    private const SEND_FROM_HOUR = 9;
    private const SEND_UNTIL_HOUR = 20;
    private const NOTICE_HOUR = 10;
    private const MAX_ATTEMPTS = 5;
    private const MAX_LINES = 5;
    private const STUCK_MINUTES = 15;

    private DateTimeZone $zone;

    public function __construct(private PDO $db, private ?WahaService $waha = null, private ?DateTimeImmutable $now = null)
    {
        $this->zone = new DateTimeZone('America/Sao_Paulo');
    }

    public static function enabled(): bool
    {
        return filter_var(getenv('GIFTBACK_NOTICES_ENABLED') ?: 'false', FILTER_VALIDATE_BOOL);
    }

    /** @return list<int> Marcos aplicaveis a uma validade em dias (somente ate metade do prazo). */
    public static function milestones(?int $policyDays): array
    {
        if ($policyDays === null || $policyDays < 2) {
            return [];
        }
        return array_values(array_filter(self::MILESTONES, static fn (int $days): bool => $days * 2 <= $policyDays));
    }

    /**
     * Agenda de um credito: marcos futuros ficam pendentes na data propria; entre os marcos que
     * ja passaram so o mais proximo e enviado (agora), os demais sao descartados.
     *
     * @return list<array{marco:int,scheduledFor:string,status:string}>
     */
    public static function plan(?int $policyDays, string $expiresAt, DateTimeImmutable $now): array
    {
        $zone = new DateTimeZone('America/Sao_Paulo');
        $now = $now->setTimezone($zone);
        $expires = new DateTimeImmutable($expiresAt, $zone);
        if ($expires <= $now) {
            return [];
        }
        $lastDay = $expires->sub(new DateInterval('P1D'))->setTime(self::NOTICE_HOUR, 0);
        $items = [];
        $lastPast = null;
        foreach (self::milestones($policyDays) as $days) {
            $when = $lastDay->sub(new DateInterval('P' . $days . 'D'));
            if ($when <= $now) {
                $lastPast = count($items);
                $items[] = ['marco' => $days, 'scheduledFor' => $now->format('Y-m-d H:i:s'), 'status' => 'skipped'];
            } else {
                $items[] = ['marco' => $days, 'scheduledFor' => $when->format('Y-m-d H:i:s'), 'status' => 'pending'];
            }
        }
        if ($lastPast !== null) {
            $items[$lastPast]['status'] = 'pending';
        }
        return $items;
    }

    /** @param list<array{storeName:string,cents:int,validUntil:string,daysLeft:int}> $lines */
    public static function message(string $name, array $lines): string
    {
        $name = trim($name) !== '' ? trim(explode(' ', trim($name))[0]) : 'cliente';
        $money = static fn (int $cents): string => 'R$ ' . number_format($cents / 100, 2, ',', '.');
        $when = static fn (int $days): string => $days <= 0 ? 'vence *hoje*' : ($days === 1 ? 'vence *amanha*' : "vence em *{$days} dias*");
        $shown = array_slice($lines, 0, self::MAX_LINES);
        if (count($lines) === 1) {
            $line = $lines[0];
            $body = "Seu giftback na *{$line['storeName']}* ({$money($line['cents'])}) {$when($line['daysLeft'])}, em {$line['validUntil']}.";
        } else {
            $items = array_map(static fn (array $line): string => "• *{$line['storeName']}*: {$money($line['cents'])}, {$when($line['daysLeft'])} ({$line['validUntil']})", $shown);
            $more = count($lines) > count($shown) ? "\n• e mais " . (count($lines) - count($shown)) . ' loja(s).' : '';
            $body = "Voce tem giftback perto de vencer:\n" . implode("\n", $items) . $more;
        }
        return "Ola, {$name}! {$body}\n\nUse seu saldo antes do prazo. Consulte em klubecash.com";
    }

    public static function migrate(PDO $db, bool $apply = false): array
    {
        $sql = "CREATE TABLE IF NOT EXISTS cashback_credito_avisos (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, credit_id BIGINT UNSIGNED NOT NULL, usuario_id INT NOT NULL,
            marco_dias SMALLINT UNSIGNED NOT NULL, expires_at DATETIME NOT NULL, scheduled_for DATETIME NOT NULL,
            status ENUM('pending','processing','sent','skipped','failed','delivery_unknown') NOT NULL DEFAULT 'pending',
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0, provider_message_id VARCHAR(191) NULL, last_error_code VARCHAR(64) NULL,
            sent_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            UNIQUE KEY uk_giftback_notice(credit_id,marco_dias,expires_at),
            KEY idx_giftback_notice_queue(status,scheduled_for), KEY idx_giftback_notice_user(usuario_id,status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $exists = (int) $db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cashback_credito_avisos'")->fetchColumn() === 1;
        if ($apply && !$exists) {
            $db->exec($sql);
        }
        return ['apply' => $apply, 'table' => 'cashback_credito_avisos', 'existed' => $exists, 'created' => $apply && !$exists];
    }

    /**
     * @return array<string,mixed>
     */
    public function run(int $limit = 30, bool $dryRun = false): array
    {
        if (!self::enabled()) {
            return ['enabled' => false];
        }
        try {
            return ['enabled' => true, 'dryRun' => $dryRun] + $this->process(max(1, min(100, $limit)), $dryRun);
        } catch (PDOException $error) {
            error_log('giftback.notice.failed ' . get_class($error) . ' ' . $error->getCode());
            return ['enabled' => true, 'error' => 'migration_or_database_unavailable'];
        }
    }

    private function now(): DateTimeImmutable
    {
        return ($this->now ?? new DateTimeImmutable('now', $this->zone))->setTimezone($this->zone);
    }

    /** @return array<string,mixed> */
    private function process(int $limit, bool $dryRun): array
    {
        $now = $this->now();
        $stats = ['scheduled' => 0, 'candidates' => 0, 'due' => 0, 'sent' => 0, 'skipped' => 0, 'pending' => 0, 'failed' => 0, 'outsideWindow' => false];
        if (!$dryRun) {
            $this->db->prepare("UPDATE cashback_credito_avisos SET status='delivery_unknown',updated_at=? WHERE status='processing' AND updated_at<?")
                ->execute([$now->format('Y-m-d H:i:s'), $now->sub(new DateInterval('PT' . self::STUCK_MINUTES . 'M'))->format('Y-m-d H:i:s')]);
        }
        $this->schedule($now, $dryRun, $stats);

        $hour = (int) $now->format('G');
        if ($hour < self::SEND_FROM_HOUR || $hour >= self::SEND_UNTIL_HOUR) {
            $stats['outsideWindow'] = true;
            return $stats;
        }
        $this->deliver($now, $limit, $dryRun, $stats);
        return $stats;
    }

    /** @param array<string,mixed> $stats */
    private function schedule(DateTimeImmutable $now, bool $dryRun, array &$stats): void
    {
        $credits = $this->db->prepare(
            "SELECT c.id,c.usuario_id,c.policy_days,c.expires_at FROM cashback_creditos c
             WHERE c.remaining_cents>0 AND c.expires_at>? AND c.policy_days>=2
               AND NOT EXISTS (SELECT 1 FROM cashback_credito_avisos a WHERE a.credit_id=c.id AND a.expires_at=c.expires_at)
             ORDER BY c.expires_at,c.id LIMIT 500"
        );
        $credits->execute([$now->format('Y-m-d H:i:s')]);
        $insert = $this->db->prepare(
            'INSERT IGNORE INTO cashback_credito_avisos(credit_id,usuario_id,marco_dias,expires_at,scheduled_for,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?)'
        );
        foreach ($credits->fetchAll(PDO::FETCH_ASSOC) as $credit) {
            $plan = self::plan((int) $credit['policy_days'], (string) $credit['expires_at'], $now);
            $stats['candidates']++;
            if ($dryRun) {
                continue;
            }
            foreach ($plan as $item) {
                $insert->execute([$credit['id'], $credit['usuario_id'], $item['marco'], $credit['expires_at'], $item['scheduledFor'], $item['status'], $now->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s')]);
                $stats['scheduled'] += $insert->rowCount();
            }
        }
    }

    /** @param array<string,mixed> $stats */
    private function deliver(DateTimeImmutable $now, int $limit, bool $dryRun, array &$stats): void
    {
        $stamp = $now->format('Y-m-d H:i:s');
        $users = $this->db->prepare("SELECT DISTINCT usuario_id FROM cashback_credito_avisos WHERE status='pending' AND scheduled_for<=? ORDER BY usuario_id LIMIT " . $limit);
        $users->execute([$stamp]);
        foreach ($users->fetchAll(PDO::FETCH_COLUMN) as $userId) {
            $due = $this->db->prepare(
                "SELECT a.id notice_id,a.marco_dias,a.attempts,a.expires_at notice_expires,c.id credit_id,c.remaining_cents,c.expires_at,l.nome_fantasia store_name,u.nome,u.telefone
                 FROM cashback_credito_avisos a
                 JOIN cashback_creditos c ON c.id=a.credit_id
                 JOIN lojas l ON l.id=c.loja_id JOIN usuarios u ON u.id=a.usuario_id
                 WHERE a.usuario_id=? AND a.status='pending' AND a.scheduled_for<=? ORDER BY c.expires_at,c.id"
            );
            $due->execute([$userId, $stamp]);
            $rows = $due->fetchAll(PDO::FETCH_ASSOC);
            $stats['due'] += count($rows);

            $valid = [];
            foreach ($rows as $row) {
                $stillValid = (int) $row['remaining_cents'] > 0 && (string) $row['expires_at'] === (string) $row['notice_expires'] && new DateTimeImmutable((string) $row['expires_at'], $this->zone) > $now;
                if (!$stillValid) {
                    if (!$dryRun) { $this->finish((int) $row['notice_id'], 'skipped', null, 'credit_changed', $now); }
                    $stats['skipped']++;
                    continue;
                }
                // Se o worker ficou parado, varios marcos do mesmo credito vencem juntos: vale so o mais proximo.
                $creditId = (int) $row['credit_id'];
                if (isset($valid[$creditId])) {
                    $closer = (int) $row['marco_dias'] < (int) $valid[$creditId]['marco_dias'];
                    $stale = $closer ? $valid[$creditId] : $row;
                    if ($closer) { $valid[$creditId] = $row; }
                    if (!$dryRun) { $this->finish((int) $stale['notice_id'], 'skipped', null, 'superseded', $now); }
                    $stats['skipped']++;
                    continue;
                }
                $valid[$creditId] = $row;
            }
            $valid = array_values($valid);
            if ($valid === []) {
                continue;
            }
            if ($dryRun) {
                $stats['pending'] += count($valid);
                continue;
            }
            $this->send($valid, $now, $stats);
        }
    }

    /**
     * @param non-empty-list<array<string,mixed>> $rows
     * @param array<string,mixed> $stats
     */
    private function send(array $rows, DateTimeImmutable $now, array &$stats): void
    {
        $ids = array_map(static fn (array $row): int => (int) $row['notice_id'], $rows);
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $claim = $this->db->prepare("UPDATE cashback_credito_avisos SET status='processing',attempts=attempts+1,updated_at=? WHERE status='pending' AND id IN ($marks)");
        $claim->execute([$now->format('Y-m-d H:i:s'), ...$ids]);
        if ($claim->rowCount() !== count($ids)) {
            // Outro processo pegou parte dos avisos; devolve o que foi reservado e tenta na proxima rodada.
            $this->db->prepare("UPDATE cashback_credito_avisos SET status='pending' WHERE status='processing' AND updated_at=? AND id IN ($marks)")->execute([$now->format('Y-m-d H:i:s'), ...$ids]);
            return;
        }

        $phone = trim((string) ($rows[0]['telefone'] ?? ''));
        if ($phone === '') {
            $this->finishAll($ids, 'skipped', null, 'phone_missing', $now);
            $stats['skipped'] += count($ids);
            return;
        }
        $lines = [];
        foreach ($rows as $row) {
            $expires = new DateTimeImmutable((string) $row['expires_at'], $this->zone);
            $lastDay = $expires->sub(new DateInterval('P1D'));
            $left = (int) $now->setTime(0, 0)->diff($lastDay->setTime(0, 0))->format('%r%a');
            $lines[] = ['storeName' => (string) $row['store_name'], 'cents' => (int) $row['remaining_cents'], 'validUntil' => $lastDay->format('d/m/Y'), 'daysLeft' => max(0, $left)];
        }
        try {
            if ($this->waha === null) {
                throw new WahaException('WhatsApp nao configurado.', 503, true);
            }
            $response = $this->waha->sendText($phone, self::message((string) ($rows[0]['nome'] ?? ''), $lines));
            $this->finishAll($ids, 'sent', self::providerId($response), null, $now);
            $stats['sent'] += count($ids);
        } catch (InvalidArgumentException) {
            $this->finishAll($ids, 'skipped', null, 'invalid_phone', $now);
            $stats['skipped'] += count($ids);
        } catch (WahaException $error) {
            if ($error->deliveryUnknown) {
                // Pode ter sido entregue: nunca reenviar automaticamente.
                $this->finishAll($ids, 'delivery_unknown', null, 'delivery_unknown', $now);
                $stats['failed'] += count($ids);
                return;
            }
            $attempts = (int) $rows[0]['attempts'] + 1;
            if ($error->transient && $attempts < self::MAX_ATTEMPTS) {
                $delay = min(60, 2 ** max(1, $attempts));
                $this->db->prepare("UPDATE cashback_credito_avisos SET status='pending',scheduled_for=?,last_error_code='provider_unavailable',updated_at=? WHERE id IN ($marks)")
                    ->execute([$now->add(new DateInterval('PT' . $delay . 'M'))->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s'), ...$ids]);
                $stats['pending'] += count($ids);
                return;
            }
            $this->finishAll($ids, 'failed', null, 'provider_rejected', $now);
            $stats['failed'] += count($ids);
        } catch (Throwable $error) {
            // Falha desconhecida pode ter ocorrido depois de o provedor aceitar a mensagem.
            error_log('giftback.notice.send_failed ' . get_class($error));
            $this->finishAll($ids, 'failed', null, 'internal_error', $now);
            $stats['failed'] += count($ids);
        }
    }

    /** @param list<int> $ids */
    private function finishAll(array $ids, string $status, ?string $providerId, ?string $error, DateTimeImmutable $now): void
    {
        foreach ($ids as $id) {
            $this->finish($id, $status, $providerId, $error, $now);
        }
    }

    private function finish(int $id, string $status, ?string $providerId, ?string $error, DateTimeImmutable $now): void
    {
        $stamp = $now->format('Y-m-d H:i:s');
        $this->db->prepare('UPDATE cashback_credito_avisos SET status=?,provider_message_id=?,last_error_code=?,sent_at=?,updated_at=? WHERE id=?')
            ->execute([$status, $providerId, $error, $status === 'sent' ? $stamp : null, $stamp, $id]);
    }

    /** @param array<string,mixed> $response */
    private static function providerId(array $response): ?string
    {
        foreach ([$response['id'] ?? null, $response['key']['id'] ?? null, $response['_data']['id']['_serialized'] ?? null] as $candidate) {
            if ((is_string($candidate) || is_int($candidate)) && trim((string) $candidate) !== '') {
                return substr(trim((string) $candidate), 0, 191);
            }
        }
        return null;
    }
}
