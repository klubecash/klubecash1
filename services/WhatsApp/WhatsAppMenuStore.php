<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use PDO;
use PDOException;

final class WhatsAppMenuStore
{
    public function __construct(private PDO $db, private WhatsAppMenuConfig $config)
    {
    }

    /** @return array<string,mixed> */
    public function conversation(string $senderKey): array
    {
        $insert = $this->db->prepare(
            "INSERT IGNORE INTO whatsapp_conversations (sender_key,status,state) VALUES (:sender,'closed','idle')"
        );
        $insert->execute([':sender' => $senderKey]);
        $statement = $this->db->prepare('SELECT * FROM whatsapp_conversations WHERE sender_key=:sender LIMIT 1');
        $statement->execute([':sender' => $senderKey]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string,mixed> $payload */
    public function setState(
        string $senderKey,
        string $state,
        array $payload = [],
        string $status = 'open',
        int $menuMinutes = 10
    ): void {
        $minutes = max(1, $menuMinutes);
        $statement = $this->db->prepare(
            'UPDATE whatsapp_conversations SET status=:status,state=:state,state_payload=:payload,'
            . "menu_expires_at=DATE_ADD(NOW(),INTERVAL {$minutes} MINUTE),last_activity_at=NOW(),invalid_attempts=0 "
            . 'WHERE sender_key=:sender'
        );
        $statement->bindValue(':status', $status);
        $statement->bindValue(':state', $state);
        $statement->bindValue(':payload', $payload === [] ? null : $this->config->encrypt($payload), $payload === [] ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':sender', $senderKey);
        $statement->execute();
    }

    public function touch(string $senderKey): void
    {
        $this->db->prepare(
            'UPDATE whatsapp_conversations SET menu_expires_at=DATE_ADD(NOW(),INTERVAL 10 MINUTE),'
            . 'last_activity_at=NOW(),auth_idle_expires_at=IF(authenticated_user_id IS NULL,NULL,DATE_ADD(NOW(),INTERVAL 30 MINUTE)) '
            . 'WHERE sender_key=:sender'
        )->execute([':sender' => $senderKey]);
    }

    public function close(string $senderKey, bool $pauseForHuman = false): void
    {
        $statement = $this->db->prepare(
            "UPDATE whatsapp_conversations SET status=:status,state='idle',state_payload=NULL,"
            . 'authenticated_user_id=NULL,loja_id=NULL,auth_idle_expires_at=NULL,auth_absolute_expires_at=NULL,'
            . 'menu_expires_at=NULL,last_activity_at=NOW() WHERE sender_key=:sender'
        );
        $statement->execute([':status' => $pauseForHuman ? 'human_paused' : 'closed', ':sender' => $senderKey]);
    }

    public function clearAuthentication(string $senderKey): void
    {
        $this->db->prepare(
            "UPDATE whatsapp_conversations SET authenticated_user_id=NULL,loja_id=NULL,auth_idle_expires_at=NULL,"
            . "auth_absolute_expires_at=NULL,state='main_menu',state_payload=NULL,status='open',"
            . 'menu_expires_at=DATE_ADD(NOW(),INTERVAL 10 MINUTE),last_activity_at=NOW() WHERE sender_key=:sender'
        )->execute([':sender' => $senderKey]);
    }

    public function incrementInvalid(string $senderKey): int
    {
        $this->db->prepare(
            'UPDATE whatsapp_conversations SET invalid_attempts=invalid_attempts+1,'
            . "blocked_until=IF(invalid_attempts+1>=5,DATE_ADD(NOW(),INTERVAL 15 MINUTE),blocked_until) "
            . 'WHERE sender_key=:sender'
        )->execute([':sender' => $senderKey]);
        $statement = $this->db->prepare('SELECT invalid_attempts FROM whatsapp_conversations WHERE sender_key=:sender');
        $statement->execute([':sender' => $senderKey]);
        return (int) $statement->fetchColumn();
    }

    public function allowInbound(string $senderKey): bool
    {
        $conversation = $this->conversation($senderKey);
        if (!empty($conversation['blocked_until']) && strtotime((string) $conversation['blocked_until']) > time()) {
            return false;
        }
        $windowStart = !empty($conversation['rate_window_started_at'])
            ? strtotime((string) $conversation['rate_window_started_at'])
            : 0;
        if ($windowStart < time() - 60) {
            $this->db->prepare(
                'UPDATE whatsapp_conversations SET rate_window_started_at=NOW(),rate_count=1 WHERE sender_key=:sender'
            )->execute([':sender' => $senderKey]);
            return true;
        }
        if ((int) ($conversation['rate_count'] ?? 0) >= 30) {
            return false;
        }
        $this->db->prepare(
            'UPDATE whatsapp_conversations SET rate_count=rate_count+1 WHERE sender_key=:sender'
        )->execute([':sender' => $senderKey]);
        return true;
    }

    public function createChallenge(string $senderKey): string
    {
        $count = $this->db->prepare(
            'SELECT COUNT(*) FROM whatsapp_auth_challenges WHERE sender_key=:sender '
            . 'AND created_at>=DATE_SUB(NOW(),INTERVAL 15 MINUTE)'
        );
        $count->execute([':sender' => $senderKey]);
        if ((int) $count->fetchColumn() >= 3) {
            throw new WahaException('Limite temporario de autenticacoes atingido.', 429);
        }
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->db->prepare(
            'UPDATE whatsapp_auth_challenges SET consumed_at=COALESCE(consumed_at,NOW()) '
            . 'WHERE sender_key=:sender AND consumed_at IS NULL'
        )->execute([':sender' => $senderKey]);
        $insert = $this->db->prepare(
            'INSERT INTO whatsapp_auth_challenges (token_hash,sender_key,expires_at) '
            . 'VALUES (:token,:sender,DATE_ADD(NOW(),INTERVAL 5 MINUTE))'
        );
        $insert->execute([':token' => hash('sha256', $token), ':sender' => $senderKey]);
        return $token;
    }

    /** @return array<string,mixed>|null */
    public function challenge(string $token, bool $forUpdate = false): ?array
    {
        if (strlen($token) < 32 || strlen($token) > 128) {
            return null;
        }
        $statement = $this->db->prepare(
            'SELECT * FROM whatsapp_auth_challenges WHERE token_hash=:token LIMIT 1'
            . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $statement->execute([':token' => hash('sha256', $token)]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function authorize(string $senderKey, int $userId, int $storeId, int $challengeId): void
    {
        $this->db->prepare(
            'UPDATE whatsapp_auth_challenges SET consumed_at=NOW() WHERE id=:id AND consumed_at IS NULL'
        )->execute([':id' => $challengeId]);
        $statement = $this->db->prepare(
            "UPDATE whatsapp_conversations SET status='open',state='merchant_menu',state_payload=NULL,"
            . 'authenticated_user_id=:user,loja_id=:store,auth_idle_expires_at=DATE_ADD(NOW(),INTERVAL 30 MINUTE),'
            . 'auth_absolute_expires_at=DATE_ADD(NOW(),INTERVAL 8 HOUR),menu_expires_at=DATE_ADD(NOW(),INTERVAL 10 MINUTE),'
            . 'last_activity_at=NOW(),invalid_attempts=0 WHERE sender_key=:sender'
        );
        $statement->execute([':user' => $userId, ':store' => $storeId, ':sender' => $senderKey]);
    }

    public function botMessageStatus(string $actionKey): ?string
    {
        $statement = $this->db->prepare('SELECT status FROM whatsapp_bot_messages WHERE action_key=:action LIMIT 1');
        $statement->execute([':action' => $actionKey]);
        $value = $statement->fetchColumn();
        return $value === false ? null : (string) $value;
    }

    public function beginBotMessage(
        string $actionKey,
        ?int $eventId,
        string $senderKey,
        string $phone,
        string $message
    ): bool
    {
        try {
            $statement = $this->db->prepare(
                "INSERT INTO whatsapp_bot_messages (action_key,source_event_id,sender_key,delivery_payload,status) "
                . "VALUES (:action,:event,:sender,:payload,'pending')"
            );
            $statement->bindValue(':action', $actionKey);
            $statement->bindValue(':event', $eventId, $eventId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $statement->bindValue(':sender', $senderKey);
            $statement->bindValue(':payload', $this->config->encrypt(['phone' => $phone, 'message' => $message]));
            $statement->execute();
            return true;
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                return false;
            }
            throw $exception;
        }
    }

    public function finishBotMessage(string $actionKey, string $status, ?string $providerId, ?string $error): void
    {
        $statement = $this->db->prepare(
            'UPDATE whatsapp_bot_messages SET status=:status,provider_message_id=:provider,last_error_code=:error,'
            . "delivery_payload=IF(:clear_payload=1,NULL,delivery_payload) "
            . 'WHERE action_key=:action'
        );
        $statement->execute([
            ':status' => $status,
            ':provider' => $providerId,
            ':error' => $error,
            ':clear_payload' => in_array($status, ['sent', 'failed', 'delivery_unknown'], true) ? 1 : 0,
            ':action' => $actionKey,
        ]);
    }

    /** @return list<array{actionKey:string,phone:string,message:string}> */
    public function pendingBotMessages(int $limit = 20, ?string $senderKey = null, ?int $sourceEventId = null): array
    {
        $limit = max(1, min(100, $limit));
        $statement = $this->db->prepare(
            "SELECT action_key,delivery_payload FROM whatsapp_bot_messages WHERE status='pending' "
            . "AND delivery_payload IS NOT NULL AND (:sender IS NULL OR sender_key=:sender_key) "
            . "AND (:event IS NULL OR source_event_id=:event_id) ORDER BY id LIMIT {$limit}"
        );
        $statement->bindValue(':sender', $senderKey, $senderKey === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':sender_key', $senderKey, $senderKey === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':event', $sourceEventId, $sourceEventId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':event_id', $sourceEventId, $sourceEventId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $payload = $this->config->decrypt((string) $row['delivery_payload']);
            if (!is_string($payload['phone'] ?? null) || !is_string($payload['message'] ?? null)) {
                continue;
            }
            $result[] = [
                'actionKey' => (string) $row['action_key'],
                'phone' => $payload['phone'],
                'message' => $payload['message'],
            ];
        }
        return $result;
    }

    public function isBotProviderMessage(?string $providerId): bool
    {
        if ($providerId === null || $providerId === '') {
            return false;
        }
        $statement = $this->db->prepare(
            'SELECT 1 FROM whatsapp_bot_messages WHERE provider_message_id=:provider '
            . "UNION SELECT 1 FROM store_whatsapp_deliveries WHERE provider_message_id=:provider_sale LIMIT 1"
        );
        $statement->execute([':provider' => $providerId, ':provider_sale' => $providerId]);
        return (bool) $statement->fetchColumn();
    }

    public function audit(
        string $senderKey,
        string $action,
        string $result,
        ?int $userId = null,
        ?int $storeId = null,
        ?int $transactionId = null,
        ?string $requestId = null,
        ?string $actionKey = null
    ): void {
        $statement = $this->db->prepare(
            'INSERT IGNORE INTO whatsapp_action_audit '
            . '(action_key,sender_key,usuario_id,loja_id,transacao_id,action,result,request_id) '
            . 'VALUES (:action_key,:sender,:user,:store,:transaction,:action,:result,:request)'
        );
        $statement->execute([
            ':action_key' => $actionKey,
            ':sender' => $senderKey,
            ':user' => $userId,
            ':store' => $storeId,
            ':transaction' => $transactionId,
            ':action' => $action,
            ':result' => $result,
            ':request' => $requestId,
        ]);
    }

    public function purge(): void
    {
        $this->db->exec(
            "DELETE FROM waha_webhook_events WHERE (status IN ('processed','ignored') AND created_at<DATE_SUB(NOW(),INTERVAL 7 DAY)) "
            . "OR (status='failed' AND created_at<DATE_SUB(NOW(),INTERVAL 30 DAY))"
        );
        $this->db->exec('DELETE FROM whatsapp_auth_challenges WHERE created_at<DATE_SUB(NOW(),INTERVAL 1 DAY)');
        $this->db->exec("DELETE FROM whatsapp_bot_messages WHERE status<>'pending' AND created_at<DATE_SUB(NOW(),INTERVAL 7 DAY)");
        $this->db->exec(
            "UPDATE whatsapp_conversations SET state_payload=NULL,state='idle',status='closed' "
            . 'WHERE last_activity_at<DATE_SUB(NOW(),INTERVAL 1 DAY) AND state_payload IS NOT NULL'
        );
    }
}
