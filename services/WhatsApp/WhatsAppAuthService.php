<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Services\Store\StoreApiException;
use PDO;
use Throwable;

final class WhatsAppAuthService
{
    private WhatsAppMenuStore $store;

    public function __construct(
        private PDO $db,
        private WahaService $waha,
        private WhatsAppMenuConfig $config
    ) {
        $this->store = new WhatsAppMenuStore($db, $config);
    }

    /** @return array<string,mixed> */
    public function context(string $token, int $userId, int $storeId): array
    {
        if (!$this->config->merchantAuthEnabled) {
            throw new StoreApiException('O acesso do lojista pelo WhatsApp ainda nao esta disponivel.', 503);
        }
        $challenge = $this->validChallenge($token);
        $account = $this->account($userId, $storeId);
        $canonical = $this->canonicalAccountPhone((string) ($account['phone'] ?? ''));
        $phoneMatches = $canonical !== null
            && hash_equals((string) $challenge['sender_key'], $this->config->senderKey($canonical));

        return [
            'dataState' => 'ready',
            'authorized' => false,
            'canAuthorize' => $phoneMatches,
            'user' => [
                'name' => (string) $account['user_name'],
                'type' => (string) $account['user_type'],
                'maskedPhone' => $this->maskedPhone((string) $account['phone']),
            ],
            'store' => [
                'id' => (int) $account['store_id'],
                'name' => (string) $account['store_name'],
            ],
            'expiresAt' => date(DATE_ATOM, strtotime((string) $challenge['expires_at'])),
            'message' => $phoneMatches
                ? 'Confirme para liberar o menu desta loja no WhatsApp.'
                : 'O telefone desta conta nao corresponde ao numero que solicitou o acesso.',
        ];
    }

    /** @return array<string,mixed> */
    public function approve(string $token, int $userId, int $storeId, string $requestId): array
    {
        if (!$this->config->merchantAuthEnabled) {
            throw new StoreApiException('O acesso do lojista pelo WhatsApp ainda nao esta disponivel.', 503);
        }

        $this->db->beginTransaction();
        try {
            $challenge = $this->validChallenge($token, true);
            $account = $this->account($userId, $storeId);
            $canonical = $this->canonicalAccountPhone((string) ($account['phone'] ?? ''));
            if ($canonical === null || !hash_equals((string) $challenge['sender_key'], $this->config->senderKey($canonical))) {
                throw new StoreApiException('O telefone cadastrado nesta conta nao corresponde ao WhatsApp solicitante.', 403);
            }
            $this->store->conversation((string) $challenge['sender_key']);
            $this->store->authorize(
                (string) $challenge['sender_key'],
                (int) $account['user_id'],
                (int) $account['store_id'],
                (int) $challenge['id']
            );
            $this->store->audit(
                (string) $challenge['sender_key'],
                'merchant.authorize',
                'success',
                (int) $account['user_id'],
                (int) $account['store_id'],
                requestId: $requestId,
                actionKey: 'auth:' . (int) $challenge['id']
            );
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        $actionKey = 'auth-approved:' . (int) $challenge['id'];
        if ($this->store->botMessageStatus($actionKey) === null) {
            $message = "✅ *Acesso lojista autorizado!*\n\nLoja: *" . $this->safeText((string) $account['store_name'])
                . "*\n\nVolte a conversa para usar o menu:\n\n1️⃣ Registrar venda\n2️⃣ Consultar cliente nesta loja\n3️⃣ Ultimas vendas registradas\n4️⃣ Encerrar acesso";
            $this->store->beginBotMessage($actionKey, null, (string) $challenge['sender_key'], $canonical, $message);
            try {
                $response = $this->waha->sendText(
                    preg_replace('/\D+/', '', preg_replace('/@.+$/', '', $canonical) ?? '') ?? '',
                    $message
                );
                $this->store->finishBotMessage($actionKey, 'sent', $this->providerMessageId($response), null);
            } catch (WahaException $exception) {
                $this->store->finishBotMessage(
                    $actionKey,
                    $exception->deliveryUnknown ? 'delivery_unknown' : ($exception->transient ? 'pending' : 'failed'),
                    null,
                    'provider_unavailable'
                );
            }
        }

        return [
            'authorized' => true,
            'store' => ['id' => (int) $account['store_id'], 'name' => (string) $account['store_name']],
            'expiresAt' => date(DATE_ATOM, strtotime('+30 minutes')),
            'returnToWhatsAppUrl' => $this->returnUrl(),
        ];
    }

    /** @return array<string,mixed> */
    private function validChallenge(string $token, bool $forUpdate = false): array
    {
        $challenge = $this->store->challenge(trim($token), $forUpdate);
        if ($challenge === null || !empty($challenge['consumed_at'])) {
            throw new StoreApiException('Este link de autorizacao e invalido ou ja foi utilizado.', 410);
        }
        if (strtotime((string) $challenge['expires_at']) < time()) {
            throw new StoreApiException('Este link de autorizacao expirou. Solicite outro pelo WhatsApp.', 410);
        }
        return $challenge;
    }

    /** @return array<string,mixed> */
    private function account(int $userId, int $storeId): array
    {
        if ($userId <= 0 || $storeId <= 0) {
            throw new StoreApiException('Conta sem loja associada.', 422);
        }
        $statement = $this->db->prepare(
            "SELECT u.id user_id,u.nome user_name,u.tipo user_type,u.telefone phone,"
            . 'l.id store_id,l.nome_fantasia store_name FROM usuarios u '
            . "JOIN lojas l ON l.id=:store AND l.status='aprovado' "
            . "WHERE u.id=:user AND u.status='ativo' AND u.tipo IN ('loja','funcionario') "
            . "AND ((u.tipo='loja' AND l.usuario_id=u.id) OR (u.tipo='funcionario' AND u.loja_vinculada_id=l.id)) LIMIT 1"
        );
        $statement->execute([':store' => $storeId, ':user' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new StoreApiException('A conta ou a loja nao esta ativa para este acesso.', 403);
        }
        return $row;
    }

    private function canonicalAccountPhone(string $phone): ?string
    {
        try {
            return WahaService::normalizePhone($phone);
        } catch (Throwable) {
            return null;
        }
    }

    private function returnUrl(): string
    {
        if ($this->config->publicNumber === '') {
            return 'https://wa.me/?text=' . rawurlencode('/klube');
        }
        return 'https://wa.me/' . $this->config->publicNumber . '?text=' . rawurlencode('/klube');
    }

    private function maskedPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        return strlen($digits) >= 4 ? '(**) *****-' . substr($digits, -4) : 'Nao informado';
    }

    private function safeText(string $value): string
    {
        return trim(str_replace(['*', '_', '~', '`'], '', $value));
    }

    /** @param array<string,mixed> $response */
    private function providerMessageId(array $response): ?string
    {
        foreach ([$response['id'] ?? null, $response['key']['id'] ?? null, $response['_data']['id']['_serialized'] ?? null] as $candidate) {
            if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                return substr(trim((string) $candidate), 0, 191);
            }
        }
        return null;
    }
}
