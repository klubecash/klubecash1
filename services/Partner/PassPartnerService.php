<?php

declare(strict_types=1);

namespace App\Services\Partner;

use App\Services\Giftback\GiftbackException;
use App\Services\Giftback\GiftbackLedger;
use App\Services\Store\StoreMoney;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use Throwable;

require_once __DIR__ . '/PartnerApiException.php';
require_once __DIR__ . '/PartnerKeyService.php';
require_once __DIR__ . '/PassCreditNoticeService.php';
require_once __DIR__ . '/../Giftback/GiftbackLedger.php';
require_once __DIR__ . '/../store/StoreMoney.php';

/**
 * Klube Pass operations on KlubeCash wallets. The promoter store (tipo_parceiro
 * promotor_eventos) is found by the organizer id; the wallet is the customer's
 * wallet at that store. Lock order matches sales: store, usage rows, wallet.
 */
final class PassPartnerService
{
    public const MAX_BATCH = 500;
    public const MAX_AMOUNT_CENTS = 5_000_000;

    private DateTimeZone $zone;

    public function __construct(private PDO $db, private ?DateTimeImmutable $now = null)
    {
        $this->zone = new DateTimeZone('America/Sao_Paulo');
    }

    public function today(): string
    {
        return ($this->now ?? new DateTimeImmutable('now', $this->zone))->setTimezone($this->zone)->format('Y-m-d');
    }

    public static function userDailyDebitLimitCents(): int
    {
        $value = trim((string) getenv('PASS_USER_DAILY_DEBIT_LIMIT_CENTS'));
        return ctype_digit($value) && (int) $value > 0 ? (int) $value : 200000;
    }

    private function ledger(): GiftbackLedger
    {
        return new GiftbackLedger($this->db, $this->now);
    }

    // ---- input -------------------------------------------------------------

    private static function positiveInt(array $input, string $field, int $max = PHP_INT_MAX): int
    {
        $value = $input[$field] ?? null;
        if (!is_int($value) || $value <= 0 || $value > $max) { throw new PartnerApiException("{$field} deve ser um inteiro positivo.", 422, 'invalid_' . $field); }
        return $value;
    }

    public static function passRef(mixed $value): string
    {
        if (!is_string($value) || preg_match('/^[A-Za-z0-9._-]{8,80}$/D', $value) !== 1) {
            throw new PartnerApiException('pass_ref deve ter de 8 a 80 caracteres [A-Za-z0-9._-].', 422, 'invalid_pass_ref');
        }
        return $value;
    }

    private static function organizerId(array $input): string
    {
        $value = $input['organizer_id'] ?? null;
        if (!is_string($value) || preg_match('/^[A-Za-z0-9-]{8,36}$/D', $value) !== 1) { throw new PartnerApiException('organizer_id inválido.', 422, 'invalid_organizer_id'); }
        return $value;
    }

    private static function text(array $input, string $field, string $default, int $max): string
    {
        $value = $input[$field] ?? null;
        if ($value === null || $value === '') { return $default; }
        if (!is_string($value) || iconv_strlen(trim($value), 'UTF-8') > $max) { throw new PartnerApiException("{$field} deve ter até {$max} caracteres.", 422, 'invalid_' . $field); }
        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    // ---- lookups -----------------------------------------------------------

    /** @return array{id:int,name:string,giftbackOnCash:bool,customerPercentage:string} */
    private function store(string $organizerId, bool $lock = false): array
    {
        $stmt = $this->db->prepare('SELECT id,nome_fantasia,status,tipo_parceiro,giftback_sobre_dinheiro,COALESCE(porcentagem_cliente,0) customer_percentage FROM lojas WHERE pass_organizer_id=? LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute([$organizerId]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$store) { throw new PartnerApiException('Nenhuma loja KlubeCash está ligada a este organizador.', 404, 'organizer_not_linked'); }
        if ($store['tipo_parceiro'] !== 'promotor_eventos' || $store['status'] !== 'aprovado') {
            throw new PartnerApiException('A loja ligada a este organizador não é um promotor de eventos aprovado.', 409, 'store_not_promoter');
        }
        return ['id' => (int) $store['id'], 'name' => (string) $store['nome_fantasia'], 'giftbackOnCash' => (int) $store['giftback_sobre_dinheiro'] === 1, 'customerPercentage' => (string) $store['customer_percentage']];
    }

    private function customer(int $userId): void
    {
        $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE id=? AND tipo='cliente' AND status='ativo' LIMIT 1");
        $stmt->execute([$userId]);
        if (!$stmt->fetchColumn()) { throw new PartnerApiException('Cliente KlubeCash não encontrado ou inativo.', 404, 'customer_not_found'); }
    }

    private function movementByRef(string $passRef): ?array
    {
        $stmt = $this->db->prepare('SELECT id,usuario_id,loja_id,tipo_operacao,valor FROM cashback_movimentacoes WHERE pass_ref=? LIMIT 1');
        $stmt->execute([$passRef]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Daily caps per key and per customer, locked inside the caller's transaction. */
    private function reserve(array $key, ?int $userId, int $debitCents, int $creditCents): void
    {
        $today = $this->today();
        $this->db->prepare('INSERT INTO partner_api_usage_daily(api_key_id,usage_date) VALUES(?,?) ON DUPLICATE KEY UPDATE api_key_id=api_key_id')->execute([$key['id'], $today]);
        $stmt = $this->db->prepare('SELECT debit_cents,credit_cents FROM partner_api_usage_daily WHERE api_key_id=? AND usage_date=? FOR UPDATE');
        $stmt->execute([$key['id'], $today]);
        $usage = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($debitCents > 0 && $key['dailyDebitLimitCents'] > 0 && (int) $usage['debit_cents'] + $debitCents > $key['dailyDebitLimitCents']) {
            throw new PartnerApiException('Limite diário de débitos desta chave atingido.', 429, 'daily_debit_limit');
        }
        if ($creditCents > 0 && $key['dailyCreditLimitCents'] > 0 && (int) $usage['credit_cents'] + $creditCents > $key['dailyCreditLimitCents']) {
            throw new PartnerApiException('Limite diário de créditos desta chave atingido.', 429, 'daily_credit_limit');
        }
        if ($userId !== null && $debitCents > 0) {
            $this->db->prepare('INSERT INTO pass_user_daily_usage(usuario_id,usage_date) VALUES(?,?) ON DUPLICATE KEY UPDATE usuario_id=usuario_id')->execute([$userId, $today]);
            $user = $this->db->prepare('SELECT debit_cents FROM pass_user_daily_usage WHERE usuario_id=? AND usage_date=? FOR UPDATE');
            $user->execute([$userId, $today]);
            if ((int) $user->fetchColumn() + $debitCents > self::userDailyDebitLimitCents()) {
                throw new PartnerApiException('Limite diário de uso de saldo deste cliente atingido.', 429, 'customer_daily_limit');
            }
            $this->db->prepare('UPDATE pass_user_daily_usage SET debit_cents=debit_cents+? WHERE usuario_id=? AND usage_date=?')->execute([$debitCents, $userId, $today]);
        }
        $this->db->prepare('UPDATE partner_api_usage_daily SET debit_cents=debit_cents+?,credit_cents=credit_cents+? WHERE api_key_id=? AND usage_date=?')->execute([$debitCents, $creditCents, $key['id'], $today]);
    }

    private function transaction(callable $action): mixed
    {
        $this->db->beginTransaction();
        try {
            $result = $action();
            $this->db->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            if ($error instanceof PDOException && (string) $error->getCode() === '23000') {
                throw new PartnerApiException('Este pass_ref já pertence a outra operação.', 409, 'pass_ref_conflict');
            }
            throw $error;
        }
    }

    // ---- operations --------------------------------------------------------

    public function balance(array $query): array
    {
        $raw = (string) ($query['klubecash_user_id'] ?? '');
        if (!ctype_digit($raw) || (int) $raw <= 0) { throw new PartnerApiException('klubecash_user_id deve ser um inteiro positivo.', 422, 'invalid_klubecash_user_id'); }
        $userId = (int) $raw;
        $organizer = self::organizerId($query);
        $store = $this->store($organizer);
        $this->customer($userId);
        $wallet = $this->ledger()->wallet($userId, $store['id']);
        return [
            'klubecash_user_id' => $userId, 'organizer_id' => $organizer, 'store_name' => $store['name'],
            'balance_cents' => $wallet['availableCents'], 'next_expiration_date' => $wallet['nextExpirationDate'],
            'next_expiration_cents' => $wallet['nextExpirationCents'],
            'giftback_on_cash' => $store['giftbackOnCash'],
            'giftback_percentage' => $store['giftbackOnCash'] ? (float) $store['customerPercentage'] : 0.0,
        ];
    }

    public function debit(array $key, array $input): array
    {
        $userId = self::positiveInt($input, 'klubecash_user_id');
        $organizer = self::organizerId($input);
        $amount = self::positiveInt($input, 'amount_cents', self::MAX_AMOUNT_CENTS);
        $cash = $input['cash_cents'] ?? 0;
        if (!is_int($cash) || $cash < 0 || $cash > self::MAX_AMOUNT_CENTS) { throw new PartnerApiException('cash_cents deve ser um inteiro maior ou igual a zero.', 422, 'invalid_cash_cents'); }
        $passRef = self::passRef($input['pass_ref'] ?? null);
        $description = self::text($input, 'description', 'Recarga Klube Pass', 120);

        return $this->transaction(function () use ($key, $userId, $organizer, $amount, $cash, $passRef, $description): array {
            $store = $this->store($organizer, true);
            $this->customer($userId);
            $prior = $this->movementByRef($passRef);
            if ($prior !== null && ((int) $prior['usuario_id'] !== $userId || (int) $prior['loja_id'] !== $store['id'] || $prior['tipo_operacao'] !== 'uso')) {
                throw new PartnerApiException('Este pass_ref já pertence a outra operação.', 409, 'pass_ref_conflict');
            }
            if ($prior === null) { $this->reserve($key, $userId, $amount, 0); }
            $ledger = $this->ledger();
            $spent = $ledger->spendPass($userId, $store['id'], $amount, $passRef, $description);
            // Giftback only over money that is not KlubeCash balance, and only if the promoter offers it.
            $giftback = $store['giftbackOnCash'] && $cash > 0 ? StoreMoney::percentage($cash, $store['customerPercentage']) : 0;
            if ($prior !== null) {
                $granted = $this->movementByRef($passRef . ':giftback');
                $giftback = $granted ? GiftbackLedger::cents($granted['valor']) : 0;
            } elseif ($giftback > 0) {
                $ledger->creditPassGiftback($userId, $store['id'], $giftback, $passRef, 'Giftback Klube Pass sobre ' . StoreMoney::decimal($cash));
            }
            return [
                'pass_ref' => $passRef, 'klubecash_user_id' => $userId, 'organizer_id' => $organizer,
                'debited_cents' => $amount, 'cash_cents' => $cash, 'giftback_cents' => $giftback,
                'balance_cents' => $ledger->settleWallet($userId, $store['id']),
                'replayed' => $prior !== null || (bool) ($spent['replayed'] ?? false),
                '_log' => ['userId' => $userId, 'storeId' => $store['id'], 'amountCents' => $amount],
            ];
        });
    }

    public function credit(array $key, array $input): array
    {
        $organizer = self::organizerId($input);
        $eventName = self::text($input, 'event_name', '', 80);
        $items = $input['items'] ?? null;
        if (!is_array($items) || !array_is_list($items) || $items === [] || count($items) > self::MAX_BATCH) {
            throw new PartnerApiException('items deve ter de 1 a ' . self::MAX_BATCH . ' créditos.', 422, 'invalid_items');
        }
        $clean = []; $seen = [];
        foreach ($items as $index => $item) {
            if (!is_array($item)) { throw new PartnerApiException("items[{$index}] inválido.", 422, 'invalid_items'); }
            try {
                $ref = self::passRef($item['pass_ref'] ?? null);
                $validUntil = $item['valid_until'] ?? null;
                if ($validUntil !== null && (!is_string($validUntil) || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $validUntil) !== 1)) {
                    throw new PartnerApiException('valid_until deve estar no formato AAAA-MM-DD.', 422, 'invalid_valid_until');
                }
                $clean[] = ['userId' => self::positiveInt($item, 'klubecash_user_id'), 'amount' => self::positiveInt($item, 'amount_cents', self::MAX_AMOUNT_CENTS), 'ref' => $ref, 'validUntil' => $validUntil];
            } catch (PartnerApiException $error) {
                throw new PartnerApiException("items[{$index}]: " . $error->getMessage(), 422, $error->errorCode);
            }
            if (isset($seen[$ref])) { throw new PartnerApiException("pass_ref repetido no lote: {$ref}.", 422, 'duplicate_pass_ref'); }
            $seen[$ref] = true;
        }
        $this->store($organizer);
        $description = $eventName !== '' ? 'Sobra do evento ' . $eventName : 'Sobra de evento Klube Pass';

        $results = []; $counts = ['credited' => 0, 'replayed' => 0, 'rejected' => 0]; $total = 0;
        foreach ($clean as $item) {
            try {
                $done = $this->transaction(function () use ($key, $organizer, $item, $description): array {
                    $store = $this->store($organizer, true);
                    $this->customer($item['userId']);
                    $prior = $this->movementByRef($item['ref']);
                    if ($prior !== null && ((int) $prior['usuario_id'] !== $item['userId'] || (int) $prior['loja_id'] !== $store['id'] || $prior['tipo_operacao'] !== 'credito_sobra_evento')) {
                        throw new PartnerApiException('Este pass_ref já pertence a outra operação.', 409, 'pass_ref_conflict');
                    }
                    if ($prior === null) { $this->reserve($key, null, 0, $item['amount']); }
                    $credit = $this->ledger()->creditPassLeftover($item['userId'], $store['id'], $item['amount'], $item['ref'], $item['validUntil'], $description);
                    if (!$credit['replayed']) {
                        PassCreditNoticeService::enqueue($this->db, $credit['movementId'], $item['userId'], $store['id'], $item['amount'], $credit['expiresAt']);
                    }
                    return $credit;
                });
                $status = $done['replayed'] ? 'replayed' : 'credited';
                $counts[$status]++;
                $total += $status === 'credited' ? $item['amount'] : 0;
                $results[] = ['pass_ref' => $item['ref'], 'status' => $status, 'credit_id' => $done['creditId'], 'balance_cents' => $done['balanceCents']];
            } catch (PartnerApiException $error) {
                $counts['rejected']++;
                $results[] = ['pass_ref' => $item['ref'], 'status' => 'rejected', 'error' => $error->errorCode, 'message' => $error->getMessage()];
            } catch (GiftbackException $error) {
                $counts['rejected']++;
                $results[] = ['pass_ref' => $item['ref'], 'status' => 'rejected', 'error' => 'ledger_rejected', 'message' => $error->getMessage()];
            }
        }
        return ['organizer_id' => $organizer, ...$counts, 'credited_cents' => $total, 'items' => $results,
            '_log' => ['amountCents' => $total, 'items' => count($clean)]];
    }

    public function reverse(array $key, array $input): array
    {
        $passRef = self::passRef($input['pass_ref'] ?? null);
        $reason = self::text($input, 'reason', '', 200);
        if (iconv_strlen($reason, 'UTF-8') < 5) { throw new PartnerApiException('Informe o motivo do estorno (5 a 200 caracteres).', 422, 'invalid_reason'); }
        return $this->transaction(function () use ($passRef, $reason): array {
            $stmt = $this->db->prepare("SELECT m.usuario_id,m.loja_id,l.tipo_parceiro FROM cashback_movimentacoes m JOIN lojas l ON l.id=m.loja_id WHERE m.pass_ref=? AND m.origem='klube_pass' LIMIT 1");
            $stmt->execute([$passRef]);
            $found = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$found) { throw new PartnerApiException('Operação do Klube Pass não encontrada.', 404, 'not_found'); }
            if ($found['tipo_parceiro'] !== 'promotor_eventos') { throw new PartnerApiException('A loja desta operação não é promotora de eventos.', 409, 'store_not_promoter'); }
            $this->db->prepare('SELECT id FROM lojas WHERE id=? FOR UPDATE')->execute([$found['loja_id']]);
            $result = $this->ledger()->reversePass($passRef, $reason);
            return ['pass_ref' => $passRef, 'type' => $result['type'] === 'uso' ? 'debit' : 'leftover_credit',
                'reversed_cents' => $result['reversedCents'], 'balance_cents' => $result['balanceCents'], 'replayed' => $result['replayed'],
                '_log' => ['userId' => (int) $found['usuario_id'], 'storeId' => (int) $found['loja_id'], 'amountCents' => $result['reversedCents']]];
        });
    }
}
