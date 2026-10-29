<?php

declare(strict_types=1);

namespace App\Services\Auth;

use PDO;

/**
 * Which KlubeCash account a "Entrar com Grupo Klube" login opens.
 *
 *  - the central login already knows the KlubeCash account (claim klubecash_user_id): that one;
 *  - it does not, and this e-mail has no KlubeCash account: a customer account is created right here and tied to
 *    the login único (the central login accepts it only for an account made minutes ago with the same e-mail);
 *  - it does not, and this e-mail already has a KlubeCash account: nothing is matched by e-mail. The person goes
 *    back to the central login once to prove that account's password, which links it.
 */
final class GrupoKlubeAccounts
{
    public const EXISTING = 'existing';
    public const CREATED = 'created';
    public const NEEDS_PASSWORD = 'needs_password';

    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @param array{klubecashUserId: ?int, sub: string, email: string, name: string, linkAttempted: bool} $login
     * @param callable(string, int): void $link registers the new account with the central login (throws to refuse)
     * @return array{outcome: string, user: ?array<string, mixed>}
     */
    public function forLogin(array $login, callable $link): array
    {
        if ($login['klubecashUserId'] !== null) {
            return ['outcome' => self::EXISTING, 'user' => $this->byId($login['klubecashUserId'])];
        }

        $email = strtolower(trim($login['email']));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 255) {
            throw new GrupoKlubeSsoException('Sua conta do Grupo Klube precisa de um e-mail válido para criar a conta KlubeCash.');
        }

        $stmt = $this->db->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() !== false) {
            if ($login['linkAttempted']) {
                // Came back from proving the password and still not linked: do not loop.
                throw new GrupoKlubeSsoException('Já existe uma conta KlubeCash com este e-mail, mas não foi possível ligá-la. Entre com e-mail e senha do KlubeCash.');
            }
            return ['outcome' => self::NEEDS_PASSWORD, 'user' => null];
        }

        $id = $this->createCustomer($login['name'], $email);
        try {
            $link($login['sub'], $id);
        } catch (\Throwable $error) {
            // The account was made a moment ago, has no password and no activity: remove it so a retry starts clean.
            $this->discard($id);
            throw $error;
        }
        return ['outcome' => self::CREATED, 'user' => $this->byId($id)];
    }

    private function createCustomer(string $name, string $email): int
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($name === '' || mb_strlen($name) < 2) {
            $name = ucfirst((string) strstr($email, '@', true));
        }
        $name = mb_substr($name, 0, 100);
        // Same shape as the Google sign-up: a customer, active at once, no password of its own.
        $stmt = $this->db->prepare("
            INSERT INTO usuarios (nome, email, provider, tipo, status, data_criacao)
            VALUES (?, ?, 'local', 'cliente', 'ativo', NOW())
        ");
        $stmt->execute([$name, $email]);
        return (int) $this->db->lastInsertId();
    }

    private function discard(int $id): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM usuarios
            WHERE id = ? AND tipo = 'cliente' AND provider = 'local' AND senha_hash IS NULL
              AND ultimo_login IS NULL AND data_criacao >= NOW() - INTERVAL 10 MINUTE
        ");
        $stmt->execute([$id]);
    }

    /** @return ?array<string, mixed> */
    private function byId(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, nome, email, tipo, tipo_cliente, status
            FROM usuarios
            WHERE id = ? AND tipo IN ('cliente', 'admin', 'loja', 'funcionario')
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }
}
