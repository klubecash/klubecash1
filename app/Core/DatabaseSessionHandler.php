<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

/**
 * Persists PHP sessions in MySQL so authentication survives between
 * independent serverless function invocations.
 */
final class DatabaseSessionHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
    private ?string $lockName = null;

    public function __construct(
        private PDO $database,
        private int $lifetime
    ) {
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        $this->releaseLock();
        return true;
    }

    public function read(string $id): string|false
    {
        try {
            if (!$this->acquireLock($id)) {
                return false;
            }

            $statement = $this->database->prepare(
                'SELECT payload FROM app_sessions WHERE id = :id AND last_activity >= :cutoff LIMIT 1'
            );
            $statement->execute([
                ':id' => $id,
                ':cutoff' => time() - $this->lifetime,
            ]);
            $payload = $statement->fetchColumn();
            return is_string($payload) ? $payload : '';
        } catch (Throwable $exception) {
            $this->logFailure('read', $exception);
            return false;
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $userId = $this->extractUserId($data);
            $statement = $this->database->prepare(
                'INSERT INTO app_sessions (id, user_id, payload, last_activity) '
                . 'VALUES (:id, :user_id, :payload, :last_activity) '
                . 'ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), '
                . 'payload = VALUES(payload), last_activity = VALUES(last_activity)'
            );
            return $statement->execute([
                ':id' => $id,
                ':user_id' => $userId,
                ':payload' => $data,
                ':last_activity' => time(),
            ]);
        } catch (Throwable $exception) {
            $this->logFailure('write', $exception);
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $statement = $this->database->prepare('DELETE FROM app_sessions WHERE id = :id');
            return $statement->execute([':id' => $id]);
        } catch (Throwable $exception) {
            $this->logFailure('destroy', $exception);
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            $statement = $this->database->prepare('DELETE FROM app_sessions WHERE last_activity < :cutoff');
            $statement->execute([':cutoff' => time() - max($max_lifetime, $this->lifetime)]);
            return $statement->rowCount();
        } catch (Throwable $exception) {
            $this->logFailure('gc', $exception);
            return false;
        }
    }

    public function validateId(string $id): bool
    {
        try {
            $statement = $this->database->prepare(
                'SELECT 1 FROM app_sessions WHERE id = :id AND last_activity >= :cutoff LIMIT 1'
            );
            $statement->execute([
                ':id' => $id,
                ':cutoff' => time() - $this->lifetime,
            ]);
            return (bool) $statement->fetchColumn();
        } catch (Throwable $exception) {
            $this->logFailure('validate', $exception);
            return false;
        }
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        return $this->write($id, $data);
    }

    private function acquireLock(string $id): bool
    {
        if ($this->lockName !== null) {
            return true;
        }

        $lockName = 'klc_sess_' . substr(hash('sha256', $id), 0, 55);
        $statement = $this->database->prepare('SELECT GET_LOCK(:lock_name, 5)');
        $statement->execute([':lock_name' => $lockName]);
        if ((int) $statement->fetchColumn() !== 1) {
            $this->logFailure('lock', new \RuntimeException('Tempo esgotado ao bloquear a sessao.'));
            return false;
        }

        $this->lockName = $lockName;
        return true;
    }

    private function releaseLock(): void
    {
        if ($this->lockName === null) {
            return;
        }

        try {
            $statement = $this->database->prepare('SELECT RELEASE_LOCK(:lock_name)');
            $statement->execute([':lock_name' => $this->lockName]);
        } catch (Throwable $exception) {
            $this->logFailure('unlock', $exception);
        } finally {
            $this->lockName = null;
        }
    }

    private function extractUserId(string $payload): ?int
    {
        if (preg_match('/(?:^|;)user_id\|i:(\d+);/', $payload, $matches) !== 1) {
            return null;
        }

        $userId = (int) $matches[1];
        return $userId > 0 ? $userId : null;
    }

    private function logFailure(string $operation, Throwable $exception): void
    {
        Logger::error('session.database_failure', [
            'operation' => $operation,
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
        ]);
    }
}
