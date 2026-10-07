<?php

declare(strict_types=1);

namespace App\Services\Admin;

use PDO;
use Throwable;

use App\Services\Billing\SubscriptionService;
use App\Services\Giftback\GiftbackLedger;

final class AdminMutationService
{
    private AdminAuditService $audit;
    private AdminIdempotencyService $idempotency;

    public function __construct(private PDO $db, private int $actorId)
    {
        $this->audit = new AdminAuditService($db, $actorId);
        $this->idempotency = new AdminIdempotencyService($db, $actorId);
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function createUser(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $phone = preg_replace('/[^0-9+]/', '', (string) ($input['phone'] ?? '')) ?: '';
        $type = (string) ($input['type'] ?? 'cliente');
        if ($name === '' || strlen($name) < 2) { throw new AdminApiException('Informe o nome.', 422, ['name' => ['Nome obrigatório.']]); }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new AdminApiException('Informe um e-mail válido.', 422, ['email' => ['E-mail inválido.']]); }
        if (!in_array($type, ['cliente', 'loja', 'funcionario'], true)) { throw new AdminApiException('Não é permitido criar novos administradores.', 403); }
        if ($type === 'funcionario' && (int) ($input['linkedStoreId'] ?? 0) <= 0) { throw new AdminApiException('Selecione a loja do funcionário.', 422, ['linkedStoreId' => ['Loja obrigatória.']]); }
        $exists = $this->db->prepare('SELECT COUNT(*) FROM usuarios WHERE email=:email'); $exists->execute([':email' => $email]);
        if ((int) $exists->fetchColumn() > 0) { throw new AdminApiException('Já existe um usuário com este e-mail.', 409); }
        $password = bin2hex(random_bytes(16));
        $this->db->beginTransaction();
        try {
        $stmt = $this->db->prepare(
            "INSERT INTO usuarios (nome,email,telefone,senha_hash,status,tipo,tipo_cliente,loja_vinculada_id,subtipo_funcionario) "
            . "VALUES (:name,:email,:phone,:password,'ativo',:type,:customer_type,:store,:subtype)"
        );
        $stmt->execute([
            ':name' => $name, ':email' => $email, ':phone' => $phone, ':password' => password_hash($password, PASSWORD_DEFAULT), ':type' => $type,
            ':customer_type' => $type === 'cliente' ? (string) ($input['customerType'] ?? 'completo') : 'completo',
            ':store' => $type === 'funcionario' ? (int) $input['linkedStoreId'] : null,
            ':subtype' => $type === 'funcionario' ? (string) ($input['employeeSubtype'] ?? 'funcionario') : 'funcionario',
        ]);
        $id = (int) $this->db->lastInsertId();
        if ($type === 'funcionario') {
            $role = (string) ($input['employeeSubtype'] ?? 'vendedor');
            if (!in_array($role, ['gerente','financeiro','vendedor'], true)) { $role = 'vendedor'; }
            $this->db->prepare("INSERT INTO store_user_memberships(user_id,store_id,role,status,accepted_at)
                VALUES(?,?,?,'active',NOW())")->execute([$id, (int) $input['linkedStoreId'], $role]);
            $this->db->prepare("INSERT INTO store_user_membership_events(user_id,store_id,actor_id,action,new_role,reason)
                VALUES(?,?,?,'admin_created',?,'Conta criada pela KlubeCash')")
                ->execute([$id, (int) $input['linkedStoreId'], $this->actorId, $role]);
        }
        $after = ['id' => $id, 'name' => $name, 'email' => $email, 'type' => $type, 'status' => 'ativo'];
        $this->audit->record('user.create', 'user', $id, null, $after);
        $this->db->commit();
        return $after + ['passwordResetRequired' => true];
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $error;
        }
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function updateUser(int $id, array $input): array
    {
        $this->db->beginTransaction();
        try {
        $before = $this->lockUser($id);
        $this->assertCurrentVersion($before, $input['updatedAt'] ?? null);
        if ($before['tipo'] === 'admin') {
            throw new AdminApiException('As contas administrativas existentes são protegidas.', 403);
        }
        $name = trim((string) ($input['name'] ?? $before['nome']));
        $email = strtolower(trim((string) ($input['email'] ?? $before['email'])));
        $phone = preg_replace('/[^0-9+]/', '', (string) ($input['phone'] ?? $before['telefone'])) ?: '';
        $type = (string) ($input['type'] ?? $before['tipo']);
        if (!in_array($type, ['cliente', 'loja', 'funcionario'], true)) { throw new AdminApiException('Tipo de usuário inválido.', 422); }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new AdminApiException('Informe um e-mail válido.', 422, ['email' => ['E-mail inválido.']]); }
        $duplicate = $this->db->prepare('SELECT COUNT(*) FROM usuarios WHERE email=:email AND id<>:id'); $duplicate->execute([':email' => $email, ':id' => $id]);
        if ((int) $duplicate->fetchColumn() > 0) { throw new AdminApiException('O e-mail já está em uso.', 409); }
        $storeId = $type === 'funcionario' ? (int) ($input['linkedStoreId'] ?? $before['loja_vinculada_id'] ?? 0) : null;
        if ($type === 'funcionario' && $storeId <= 0) { throw new AdminApiException('Selecione a loja do funcionário.', 422); }
        $stmt = $this->db->prepare('UPDATE usuarios SET nome=:name,email=:email,telefone=:phone,tipo=:type,loja_vinculada_id=:store,subtipo_funcionario=:subtype WHERE id=:id');
        $stmt->execute([':name' => $name, ':email' => $email, ':phone' => $phone, ':type' => $type, ':store' => $storeId, ':subtype' => (string) ($input['employeeSubtype'] ?? $before['subtipo_funcionario']), ':id' => $id]);
        if ($type === 'funcionario' && $storeId !== null) {
            $role = (string) ($input['employeeSubtype'] ?? $before['subtipo_funcionario'] ?? 'vendedor');
            if (!in_array($role, ['gerente','financeiro','vendedor'], true)) { $role = 'vendedor'; }
            $prior = $this->db->prepare('SELECT role,status FROM store_user_memberships WHERE user_id=? AND store_id=? FOR UPDATE');
            $prior->execute([$id, $storeId]);
            $oldMembership = $prior->fetch(PDO::FETCH_ASSOC) ?: null;
            $this->db->prepare("INSERT INTO store_user_memberships(user_id,store_id,role,status,accepted_at)
                VALUES(?,?,?,'active',NOW()) ON DUPLICATE KEY UPDATE role=VALUES(role),status='active'")
                ->execute([$id, $storeId, $role]);
            if (!$oldMembership || $oldMembership['role'] !== $role || $oldMembership['status'] !== 'active') {
                $this->db->prepare("INSERT INTO store_user_membership_events(user_id,store_id,actor_id,action,old_role,new_role,reason)
                    VALUES(?,?,?,'admin_updated',?,?, 'Vínculo alterado pela KlubeCash')")
                    ->execute([$id, $storeId, $this->actorId, $oldMembership['role'] ?? null, $role]);
            }
        } elseif ($before['tipo'] === 'funcionario') {
            $links = $this->db->prepare("SELECT store_id,role FROM store_user_memberships WHERE user_id=? AND status='active' FOR UPDATE");
            $links->execute([$id]);
            $oldLinks = $links->fetchAll(PDO::FETCH_ASSOC);
            $this->db->prepare("UPDATE store_user_memberships SET status='inactive' WHERE user_id=?")->execute([$id]);
            $event = $this->db->prepare("INSERT INTO store_user_membership_events(user_id,store_id,actor_id,action,old_role,reason)
                VALUES(?,?,?,'admin_deactivated',?,'Tipo da conta alterado pela KlubeCash')");
            foreach ($oldLinks as $link) {
                $event->execute([$id, (int) $link['store_id'], $this->actorId, $link['role']]);
            }
        }
        $after = ['id' => $id, 'name' => $name, 'email' => $email, 'phone' => $phone, 'type' => $type, 'linkedStoreId' => $storeId];
        $this->audit->record('user.update', 'user', $id, $before, $after);
        $this->db->commit();
        return $after;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $error;
        }
    }

    /** @return array<string, mixed> */
    public function updateUserStatus(int $id, string $status): array
    {
        if ($id === $this->actorId) { throw new AdminApiException('Você não pode alterar o status da própria conta.', 403); }
        if (!in_array($status, ['ativo', 'inativo', 'bloqueado'], true)) { throw new AdminApiException('Status inválido.', 422); }
        $before = $this->lockUser($id);
        if ($before['tipo'] === 'admin') { throw new AdminApiException('As contas administrativas existentes são protegidas.', 403); }
        $this->db->prepare('UPDATE usuarios SET status=:status WHERE id=:id')->execute([':status' => $status, ':id' => $id]);
        $after = $before; $after['status'] = $status;
        $this->audit->record('user.status', 'user', $id, $before, $after);
        return ['id' => $id, 'status' => $status];
    }

    /** @return array<string, mixed> */
    public function requestUserPasswordReset(int $id): array
    {
        $stmt = $this->db->prepare('SELECT id,nome,email,status FROM usuarios WHERE id=:id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) { throw new AdminApiException('Usuário não encontrado.', 404); }
        if ($user['status'] !== 'ativo') { throw new AdminApiException('Ative o usuário antes de solicitar a recuperação.', 409); }
        require_once __DIR__ . '/../../utils/Email.php';
        $token = bin2hex(random_bytes(32));
        $tokenHash = 'sha256:' . hash('sha256', $token);
        $expiry = date('Y-m-d H:i:s', time() + (defined('TOKEN_EXPIRATION') ? (int) TOKEN_EXPIRATION : 3600));
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) { $this->db->beginTransaction(); }
        try {
            $lock = $this->db->prepare('SELECT id FROM usuarios WHERE id=:id AND status=\'ativo\' FOR UPDATE');
            $lock->execute([':id' => $id]);
            if (!$lock->fetchColumn()) { throw new AdminApiException('O usuário não está mais ativo.', 409); }
            $recentThreshold = date('Y-m-d H:i:s', time() + (defined('TOKEN_EXPIRATION') ? (int) TOKEN_EXPIRATION : 3600) - 60);
            $recent = $this->db->prepare("SELECT id FROM recuperacao_senha WHERE usuario_id=:user AND usado=0 AND data_expiracao>:threshold LIMIT 1");
            $recent->execute([':user' => $id, ':threshold' => $recentThreshold]);
            if ($recent->fetchColumn() !== false) {
                if ($ownsTransaction) { $this->db->commit(); }
                return ['id' => $id, 'queued' => true, 'replayed' => true];
            }
            $insert = $this->db->prepare('INSERT INTO recuperacao_senha (usuario_id,token,data_expiracao) VALUES (:user,:token,:expiry)');
            $insert->execute([':user' => $id, ':token' => $tokenHash, ':expiry' => $expiry]);
            $recoveryId = (int) $this->db->lastInsertId();
            if ($ownsTransaction) { $this->db->commit(); }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) { $this->db->rollBack(); }
            throw $exception;
        }
        $siteUrl = rtrim(defined('SITE_URL') ? (string) SITE_URL : '', '/');
        $recoveryPath = defined('RECOVER_PASSWORD_URL') ? (string) RECOVER_PASSWORD_URL : '/recuperar-senha';
        $link = $siteUrl . $recoveryPath . '?token=' . rawurlencode($token);
        $safeName = htmlspecialchars((string) $user['nome'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeLink = htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $message = "<h2>Olá, {$safeName}!</h2><p>Um administrador iniciou o fluxo seguro de recuperação da sua conta Klube Cash.</p>"
            . "<p><a href=\"{$safeLink}\">Redefinir minha senha</a></p><p>Se você não esperava esta mensagem, ignore-a.</p>";
        if (!\Email::queueEmail((string) $user['email'], 'Recuperação de Senha - Klube Cash', $message, (string) $user['nome'])) {
            $this->db->prepare('DELETE FROM recuperacao_senha WHERE id=:id')->execute([':id' => $recoveryId]);
            throw new AdminApiException('Não foi possível adicionar a recuperação à fila.', 503);
        }
        $this->db->prepare('DELETE FROM recuperacao_senha WHERE usuario_id=:user AND id<>:id AND usado=0')->execute([':user' => $id, ':id' => $recoveryId]);
        $response = ['id' => $id, 'queued' => true, 'expiresAt' => date(DATE_ATOM, strtotime($expiry))];
        $this->audit->record('user.password_recovery_requested', 'user', $id, null, $response);
        return $response;
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function updateStore(int $id, array $input): array
    {
        $this->assertActiveAdministrator();
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) { $this->db->beginTransaction(); }
        try {
        $before = $this->lockStore($id);
        $this->assertCurrentVersion($before, $input['updatedAt'] ?? null);
        $name = trim((string) ($input['name'] ?? $before['nome_fantasia']));
        $legalName = trim((string) ($input['legalName'] ?? $before['razao_social']));
        $email = strtolower(trim((string) ($input['email'] ?? $before['email'])));
        $phone = trim((string) ($input['phone'] ?? $before['telefone']));
        $category = trim((string) ($input['category'] ?? $before['categoria']));
        $percentage = (float) ($input['customerCashbackPercentage'] ?? $before['porcentagem_cliente']);
        $expirationDays = $before['giftback_expiration_days'] ?? null;
        if (array_key_exists('giftbackExpirationDays', $input)) {
            $expirationDays = $input['giftbackExpirationDays'];
            if ($expirationDays !== null && (!is_int($expirationDays) || $expirationDays < 1 || $expirationDays > 3650)) {
                throw new AdminApiException('A validade deve ser um número inteiro de 1 a 3650 dias, ou sem expiração.', 422, ['giftbackExpirationDays' => ['Informe de 1 a 3650 dias.']]);
            }
        }
        if ($name === '' || $legalName === '') { throw new AdminApiException('Nome fantasia e razão social são obrigatórios.', 422); }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new AdminApiException('E-mail da loja inválido.', 422); }
        if ($percentage < 0 || $percentage > 100) { throw new AdminApiException('A porcentagem deve estar entre 0 e 100.', 422); }
        $stmt = $this->db->prepare(
            'UPDATE lojas SET nome_fantasia=:name,razao_social=:legal,email=:email,telefone=:phone,categoria=:category,descricao=:description,website=:website,'
            . 'porcentagem_cliente=:customer_percentage,porcentagem_admin=0,porcentagem_cashback=:cashback_percentage,cashback_ativo=:enabled,giftback_expiration_days=:expiration_days,data_config_cashback=NOW() WHERE id=:id'
        );
        $stmt->execute([
            ':name' => $name, ':legal' => $legalName, ':email' => $email, ':phone' => $phone, ':category' => $category,
            ':description' => (string) ($input['description'] ?? $before['descricao']), ':website' => (string) ($input['website'] ?? $before['website']),
            ':customer_percentage' => $percentage, ':cashback_percentage' => $percentage,
            ':enabled' => (bool) ($input['cashbackEnabled'] ?? $before['cashback_ativo']) ? 1 : 0, ':id' => $id,
            ':expiration_days' => $expirationDays,
        ]);
        $after = ['id' => $id, 'name' => $name, 'email' => $email, 'customerCashbackPercentage' => $percentage, 'cashbackEnabled' => (bool) ($input['cashbackEnabled'] ?? $before['cashback_ativo']), 'giftbackExpirationDays' => $expirationDays === null ? null : (int) $expirationDays];
        $this->audit->record('store.update', 'store', $id, $before, $after);
        if (($before['giftback_expiration_days'] ?? null) != $expirationDays) {
            $this->audit->record('giftback.policy.update', 'store', $id, ['giftbackExpirationDays' => $before['giftback_expiration_days'] ?? null], ['giftbackExpirationDays' => $expirationDays, 'appliesTo' => 'new_credits_only']);
        }
        if ($ownsTransaction) { $this->db->commit(); }
        return $after;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) { $this->db->rollBack(); }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function changeGiftbackCredit(int $id, string $action, array $input, string $key): array
    {
        $this->assertActiveAdministrator();
        $userId = $input['userId'] ?? null;
        $storeId = $input['storeId'] ?? null;
        $version = $input['expectedVersion'] ?? null;
        if (!is_int($userId) || $userId <= 0 || !is_int($storeId) || $storeId <= 0 || !is_int($version) || $version < 1) {
            throw new AdminApiException('Informe o cliente, a loja e a versão do crédito selecionado.', 422);
        }
        $ledger = new GiftbackLedger($this->db);
        $credit = $ledger->creditDetail($id, $userId);
        if ((int) $credit['storeId'] !== $storeId) {
            throw new AdminApiException('O crédito não pertence ao cliente nesta loja.', 404);
        }
        if ($action === 'extend') {
            return $ledger->extendCredit($id, $this->actorId, (string) ($input['validUntil'] ?? ''), (string) ($input['reason'] ?? ''), $version, $key);
        }
        if ($action !== 'restore' || !is_int($input['expirationEventId'] ?? null) || $input['expirationEventId'] <= 0) {
            throw new AdminApiException('Selecione a expiração individual que deseja reverter.', 422);
        }
        return $ledger->restoreCredit($id, $input['expirationEventId'], $this->actorId, (string) ($input['validUntil'] ?? ''), (string) ($input['reason'] ?? ''), $version, $key);
    }

    private function assertActiveAdministrator(): void
    {
        $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE id=:actor AND tipo='admin' AND status='ativo'");
        $stmt->execute([':actor' => $this->actorId]);
        if (!$stmt->fetchColumn()) { throw new AdminApiException('Acesso restrito a administradores ativos da KlubeCash.', 403); }
    }

    /** @return array<string, mixed> */
    public function updateStoreStatus(int $id, string $status, string $notes, string $key, ?string $expectedUpdatedAt = null): array
    {
        if (!in_array($status, ['pendente', 'aprovado', 'rejeitado'], true)) { throw new AdminApiException('Status de loja inválido.', 422); }
        $payload = ['id' => $id, 'status' => $status, 'notes' => $notes];
        $state = $this->idempotency->begin('store_status', $key, $payload);
        if ($state['replayed']) { return [...($state['data'] ?? []), 'replayed' => true]; }
        try {
            $before = $this->lockStore($id);
            $this->assertCurrentVersion($before, $expectedUpdatedAt);
            $stmt = $this->db->prepare("UPDATE lojas SET status=:status,observacao=:notes,data_aprovacao=CASE WHEN :is_approved=1 THEN COALESCE(data_aprovacao,NOW()) ELSE data_aprovacao END WHERE id=:id");
            $stmt->execute([':status' => $status, ':is_approved' => $status === 'aprovado' ? 1 : 0, ':notes' => $notes, ':id' => $id]);
            if ($status === 'aprovado') {
                require_once __DIR__ . '/../StoreWallet/StoreWalletService.php';
                (new \App\Services\StoreWallet\StoreWalletService($this->db))->provision($id);
                $this->db->prepare("INSERT IGNORE INTO store_user_memberships(user_id,store_id,role,status,accepted_at)
                    SELECT u.id,l.id,'titular','active',NOW() FROM lojas l JOIN usuarios u ON u.id=l.usuario_id
                    WHERE l.id=? AND u.tipo='loja'")->execute([$id]);
            }
            $result = ['id' => $id, 'status' => $status, 'replayed' => false];
            $this->audit->record('store.status', 'store', $id, $before, $result);
            $this->idempotency->complete('store_status', $key, $result);
            return $result;
        } catch (Throwable $exception) {
            $this->idempotency->fail('store_status', $key); throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function legacyTransactionStatus(int $id, string $status, string $notes, string $key): array
    {
        if (!in_array($status, ['aprovado', 'cancelado'], true)) { throw new AdminApiException('Ação inválida.', 422); }
        $payload = ['id' => $id, 'status' => $status, 'notes' => $notes];
        $state = $this->idempotency->begin('legacy_transaction_status', $key, $payload);
        if ($state['replayed']) { return [...($state['data'] ?? []), 'replayed' => true]; }
        try {
            $this->db->beginTransaction();
            $row = $this->lockTransaction($id);
            if (($row['financial_model'] ?? 'commission_legacy') !== 'commission_legacy') { throw new AdminApiException('Use o fluxo de estorno para vendas do modelo atual.', 409); }
            if (!in_array($row['status'], ['pendente', 'pagamento_pendente'], true)) { throw new AdminApiException('A transação não está pendente.', 409); }
            if ($status === 'aprovado') {
                $this->creditLegacyCashback($row);
                $this->db->prepare("UPDATE transacoes_comissao SET status='aprovado' WHERE transacao_id=:id AND status='pendente'")->execute([':id' => $id]);
            } else {
                $this->db->prepare("UPDATE transacoes_comissao SET status='cancelado' WHERE transacao_id=:id AND status='pendente'")->execute([':id' => $id]);
            }
            $this->db->prepare('UPDATE transacoes_cashback SET status=:status WHERE id=:id')->execute([':status' => $status, ':id' => $id]);
            $result = ['id' => $id, 'status' => $status, 'replayed' => false];
            $this->audit->record('transaction.legacy_status', 'transaction', $id, $row, $result + ['notes' => $notes]);
            $this->idempotency->complete('legacy_transaction_status', $key, $result);
            $this->db->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            $this->idempotency->fail('legacy_transaction_status', $key); throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function reverseCurrentTransaction(int $id, string $reason, string $key): array
    {
        $this->assertActiveAdministrator();
        if (strlen(trim($reason)) < 5) { throw new AdminApiException('Informe o motivo do estorno.', 422, ['reason' => ['Motivo obrigatório.']]); }
        $payload = ['id' => $id, 'reason' => trim($reason)];
        $ownsTransaction = !$this->db->inTransaction();
        try {
            if ($ownsTransaction) { $this->db->beginTransaction(); }
            $state = $this->idempotency->begin('transaction_reverse', $key, $payload);
            if ($state['replayed']) {
                if ($ownsTransaction) { $this->db->commit(); }
                return [...($state['data'] ?? []), 'replayed' => true];
            }
            $row = $this->lockTransaction($id);
            if (($row['financial_model'] ?? '') !== 'subscription_cashback' || $row['status'] !== 'aprovado') { throw new AdminApiException('Somente vendas aprovadas do modelo atual podem ser estornadas.', 409); }
            $ledger = (new GiftbackLedger($this->db))->reverseSale((int) $row['usuario_id'], (int) $row['loja_id'], $id, trim($reason), $this->actorId);
            $this->db->prepare("UPDATE transacoes_cashback SET status='cancelado' WHERE id=:id")->execute([':id' => $id]);
            $result = ['id' => $id, 'status' => 'cancelado', 'restoredBalanceUsedCents' => $ledger['restoredBalanceUsedCents'], 'reversedCashbackCents' => $ledger['reversedCashbackCents'], 'replayed' => false];
            $this->audit->record('transaction.reverse', 'transaction', $id, $row, $result + ['reason' => trim($reason)]);
            $this->idempotency->complete('transaction_reverse', $key, $result);
            if ($ownsTransaction) { $this->db->commit(); }
            return $result;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) { $this->db->rollBack(); }
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function processLegacyPayment(string $kind, int $id, string $decision, string $notes, string $key): array
    {
        if (!in_array($kind, ['commission', 'balance_refund'], true) || !in_array($decision, ['approve', 'reject'], true)) { throw new AdminApiException('Operação financeira inválida.', 422); }
        $payload = compact('kind', 'id', 'decision', 'notes');
        $state = $this->idempotency->begin('legacy_payment', $key, $payload);
        if ($state['replayed']) { return [...($state['data'] ?? []), 'replayed' => true]; }
        try {
            $this->db->beginTransaction();
            $table = $kind === 'commission' ? 'pagamentos_comissao' : 'store_balance_payments';
            $stmt = $this->db->prepare("SELECT * FROM {$table} WHERE id=:id LIMIT 1 FOR UPDATE"); $stmt->execute([':id' => $id]); $before = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$before || !in_array($before['status'], ['pendente', 'em_processamento'], true)) { throw new AdminApiException('Pendência não encontrada ou já processada.', 409); }
            if ($decision === 'approve') {
                if ((float) $before['valor_total'] <= 0) {
                    throw new AdminApiException('Pendência com valor inválido. Encaminhada para revisão.', 409);
                }
                $store = $this->db->prepare('SELECT COUNT(*) FROM lojas WHERE id=:id');
                $store->execute([':id' => (int) $before['loja_id']]);
                if ((int) $store->fetchColumn() !== 1) {
                    throw new AdminApiException('Pendência vinculada a uma loja inexistente. Encaminhada para revisão.', 409);
                }
                if ($kind === 'commission') {
                    $links = $this->db->prepare(
                        'SELECT COUNT(*) total_links,
                                SUM(CASE WHEN t.id IS NOT NULL AND t.loja_id=:store THEN 1 ELSE 0 END) valid_links
                         FROM pagamentos_transacoes pt
                         LEFT JOIN transacoes_cashback t ON t.id=pt.transacao_id
                         WHERE pt.pagamento_id=:id'
                    );
                    $links->execute([':store' => (int) $before['loja_id'], ':id' => $id]);
                    $linkState = $links->fetch(PDO::FETCH_ASSOC) ?: [];
                    if ((int) ($linkState['total_links'] ?? 0) === 0 || (int) ($linkState['valid_links'] ?? 0) !== (int) ($linkState['total_links'] ?? 0)) {
                        throw new AdminApiException('Pagamento sem transações válidas da mesma loja. Encaminhado para revisão.', 409);
                    }
                } else {
                    $reference = trim((string) ($before['numero_referencia'] ?? ''));
                    if ($reference !== '') {
                        $duplicate = $this->db->prepare(
                            "SELECT COUNT(*) FROM store_balance_payments
                             WHERE loja_id=:store AND numero_referencia=:reference AND status='aprovado' AND id<>:id"
                        );
                        $duplicate->execute([':store' => (int) $before['loja_id'], ':reference' => $reference, ':id' => $id]);
                        if ((int) $duplicate->fetchColumn() > 0) {
                            throw new AdminApiException('A referência deste reembolso já foi aprovada. Encaminhado para revisão.', 409);
                        }
                    }
                }
            }
            $status = $decision === 'approve' ? 'aprovado' : ($kind === 'commission' ? 'rejeitado' : 'rejeitado');
            if ($kind === 'commission') {
                $this->db->prepare('UPDATE pagamentos_comissao SET status=:status,observacao_admin=:notes,data_aprovacao=CASE WHEN :is_approved=1 THEN NOW() ELSE data_aprovacao END WHERE id=:id')->execute([':status' => $status, ':is_approved' => $status === 'aprovado' ? 1 : 0, ':notes' => trim($notes), ':id' => $id]);
            } else {
                $this->db->prepare('UPDATE store_balance_payments SET status=:status,observacao=:notes,data_processamento=NOW() WHERE id=:id')->execute([':status' => $status, ':notes' => trim($notes), ':id' => $id]);
            }
            $this->db->commit();
            $result = ['id' => $id, 'kind' => $kind, 'status' => $status, 'replayed' => false];
            $this->audit->record('finance.legacy_process', $kind, $id, $before, $result + ['notes' => trim($notes)]);
            $this->idempotency->complete('legacy_payment', $key, $result);
            return $result;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            $this->idempotency->fail('legacy_payment', $key); throw $exception;
        }
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function updateSettings(array $input): array
    {
        $customer = (float) ($input['customerPercentage'] ?? 5);
        $minimum = AdminMoney::decimal(max(0, (int) ($input['minimumUseCents'] ?? 100)));
        $maximum = (float) ($input['maximumPurchasePercentage'] ?? 100);
        if ($customer < 0 || $customer > 100 || $maximum < 0 || $maximum > 100) { throw new AdminApiException('Percentuais inválidos.', 422); }
        $before = ['cashback' => $this->db->query('SELECT * FROM configuracoes_cashback ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC), 'balance' => $this->db->query('SELECT * FROM configuracoes_saldo ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC)];
        $this->db->beginTransaction();
        try {
            $cashback = $this->db->prepare('INSERT INTO configuracoes_cashback (porcentagem_cliente,porcentagem_admin,porcentagem_loja) VALUES (:customer,0,0)'); $cashback->execute([':customer' => $customer]);
            $this->db->prepare('UPDATE configuracoes_saldo SET permitir_uso_saldo=:enabled,valor_minimo_uso=:minimum,percentual_maximo_uso=:maximum,notificar_saldo_baixo=:low_enabled,limite_saldo_baixo=:threshold WHERE id=(SELECT id FROM (SELECT id FROM configuracoes_saldo ORDER BY id DESC LIMIT 1) current_balance)')->execute([
                ':enabled' => !empty($input['balanceEnabled']) ? 1 : 0, ':minimum' => $minimum, ':maximum' => $maximum,
                ':low_enabled' => !empty($input['lowBalanceNotification']) ? 1 : 0, ':threshold' => AdminMoney::decimal((int) ($input['lowBalanceThresholdCents'] ?? 1000)),
            ]);
            $this->db->prepare('UPDATE configuracoes_notificacao SET email_nova_transacao=:transaction,email_pagamento_aprovado=:payment,email_saldo_disponivel=:balance,email_saldo_baixo=:low,email_saldo_expirado=:expired WHERE id=(SELECT id FROM (SELECT id FROM configuracoes_notificacao ORDER BY id DESC LIMIT 1) current_notification)')->execute([
                ':transaction' => !empty($input['newTransactionEmail']) ? 1 : 0, ':payment' => !empty($input['approvedPaymentEmail']) ? 1 : 0,
                ':balance' => !empty($input['availableBalanceEmail']) ? 1 : 0, ':low' => !empty($input['lowBalanceEmail']) ? 1 : 0, ':expired' => !empty($input['expiredBalanceEmail']) ? 1 : 0,
            ]);
            $this->db->commit();
        } catch (Throwable $exception) { if ($this->db->inTransaction()) { $this->db->rollBack(); } throw $exception; }
        $after = ['customerPercentage' => $customer, 'balanceEnabled' => !empty($input['balanceEnabled']), 'minimumUseCents' => AdminMoney::cents($minimum), 'maximumPurchasePercentage' => $maximum];
        $this->audit->record('settings.update', 'settings', 1, $before, $after);
        return $after;
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function updatePlan(int $id, array $input): array
    {
        $stmt = $this->db->prepare('SELECT * FROM planos WHERE id=:id LIMIT 1 FOR UPDATE'); $stmt->execute([':id' => $id]); $before = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$before) { throw new AdminApiException('Plano não encontrado.', 404); }
        $this->assertCurrentVersion($before, $input['updatedAt'] ?? null);
        $code = strtoupper(trim((string) ($input['code'] ?? $before['codigo'])));
        if ($code !== '' && !preg_match('/^[A-Z0-9-]{4,32}$/', $code)) { throw new AdminApiException('Código de plano inválido.', 422); }
        $features = array_values(array_filter(array_map('trim', (array) ($input['features'] ?? json_decode((string) ($before['features_json'] ?? '[]'), true) ?? []))));
        $update = $this->db->prepare('UPDATE planos SET nome=:name,codigo=:code,descricao=:description,preco_mensal=:monthly,preco_anual=:annual,trial_dias=:trial,recorrencia=:recurrence,features_json=:features,ativo=:active WHERE id=:id');
        $update->execute([
            ':name' => trim((string) ($input['name'] ?? $before['nome'])), ':code' => $code === '' ? null : $code, ':description' => (string) ($input['description'] ?? $before['descricao']),
            ':monthly' => AdminMoney::decimal((int) ($input['monthlyPriceCents'] ?? AdminMoney::cents($before['preco_mensal']))), ':annual' => AdminMoney::decimal((int) ($input['annualPriceCents'] ?? AdminMoney::cents($before['preco_anual']))),
            ':trial' => max(0, min(90, (int) ($input['trialDays'] ?? $before['trial_dias']))), ':recurrence' => (string) ($input['recurrence'] ?? $before['recorrencia']),
            ':features' => json_encode($features, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ':active' => !empty($input['active']) ? 1 : 0, ':id' => $id,
        ]);
        $after = ['id' => $id, 'name' => (string) ($input['name'] ?? $before['nome']), 'code' => $code, 'active' => !empty($input['active'])];
        $this->audit->record('plan.update', 'plan', $id, $before, $after);
        return $after;
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function assignSubscription(array $input, string $key): array
    {
        $storeId = (int) ($input['storeId'] ?? 0); $planSlug = trim((string) ($input['planSlug'] ?? ''));
        if ($storeId <= 0 || $planSlug === '') { throw new AdminApiException('Loja e plano são obrigatórios.', 422); }
        if ((int) ($input['existingSubscriptionId'] ?? 0) > 0) {
            $current = $this->db->prepare('SELECT id,loja_id,updated_at FROM assinaturas WHERE id=:id LIMIT 1');
            $current->execute([':id' => (int) $input['existingSubscriptionId']]);
            $currentRow = $current->fetch(PDO::FETCH_ASSOC);
            if (!$currentRow || (int) $currentRow['loja_id'] !== $storeId) { throw new AdminApiException('Assinatura não pertence à loja informada.', 409); }
            $this->assertCurrentVersion($currentRow, $input['updatedAt'] ?? null);
        }
        $state = $this->idempotency->begin('subscription_assign', $key, $input);
        if ($state['replayed']) { return [...($state['data'] ?? []), 'replayed' => true]; }
        require_once __DIR__ . '/../../controllers/SubscriptionController.php';
        try {
            $controller = new \SubscriptionController($this->db);
            $result = $controller->assignPlanToStore($storeId, $planSlug, $input['trialDays'] ?? null, (string) ($input['cycle'] ?? 'monthly'));
            if (empty($result['success'])) { throw new AdminApiException('Não foi possível atribuir o plano selecionado.', 422); }
            $response = ['id' => (int) $result['assinatura_id'], 'storeId' => $storeId, 'planSlug' => $planSlug, 'replayed' => false];
            $this->audit->record('subscription.assign', 'subscription', $response['id'], null, $response);
            $this->idempotency->complete('subscription_assign', $key, $response);
            return $response;
        } catch (Throwable $exception) { $this->idempotency->fail('subscription_assign', $key); throw $exception; }
    }

    /** @return array<string, mixed> */
    public function subscriptionStatus(int $id, string $action, string $key, ?string $expectedUpdatedAt = null): array
    {
        if (!in_array($action, ['suspend', 'cancel'], true)) { throw new AdminApiException('Ação de assinatura inválida.', 422); }
        $payload = ['id' => $id, 'action' => $action]; $state = $this->idempotency->begin('subscription_status', $key, $payload);
        if ($state['replayed']) { return [...($state['data'] ?? []), 'replayed' => true]; }
        require_once __DIR__ . '/../../controllers/SubscriptionController.php';
        try {
            $beforeStmt = $this->db->prepare('SELECT * FROM assinaturas WHERE id=:id LIMIT 1'); $beforeStmt->execute([':id' => $id]); $before = $beforeStmt->fetch(PDO::FETCH_ASSOC);
            if (!$before) { throw new AdminApiException('Assinatura não encontrada.', 404); }
            $this->assertCurrentVersion($before, $expectedUpdatedAt);
            $controller = new \SubscriptionController($this->db);
            $ok = $action === 'suspend' ? $controller->suspendSubscription($id) : $controller->cancelSubscription($id);
            if (!$ok) { throw new AdminApiException('Não foi possível alterar a assinatura.', 422); }
            $response = ['id' => $id, 'status' => $action === 'suspend' ? 'suspensa' : 'cancelada', 'replayed' => false];
            $this->audit->record('subscription.' . $action, 'subscription', $id, $before, $response);
            $this->idempotency->complete('subscription_status', $key, $response); return $response;
        } catch (Throwable $exception) { $this->idempotency->fail('subscription_status', $key); throw $exception; }
    }

    /**
     * New subscription lifecycle action. Unlike the legacy status endpoint,
     * this action keeps read access intact and records a reason for every
     * commercial block or manual activation.
     *
     * @return array<string, mixed>
     */
    public function subscriptionAction(int $id, string $action, string $reason, string $until, string $key, ?string $expectedUpdatedAt = null): array
    {
        if ($key === '') {
            throw new AdminApiException('A chave de idempotência é obrigatória.', 400);
        }
        if (!in_array($action, ['activate', 'block', 'unblock', 'pause', 'cancel', 'reactivate'], true)) {
            throw new AdminApiException('Ação de assinatura inválida.', 422);
        }
        $payload = ['id' => $id, 'action' => $action, 'reason' => trim($reason), 'until' => trim($until)];
        $state = $this->idempotency->begin('subscription_action', $key, $payload);
        if ($state['replayed']) {
            return [...($state['data'] ?? []), 'replayed' => true];
        }
        try {
            $service = new SubscriptionService($this->db);
            $result = $service->manualAction($id, $action, $this->actorId, $reason, $until !== '' ? $until : null, $expectedUpdatedAt);
            $this->audit->record('subscription.' . $action, 'subscription', $id, null, $result);
            $this->idempotency->complete('subscription_action', $key, $result);
            return $result + ['replayed' => false];
        } catch (Throwable $exception) {
            $this->idempotency->fail('subscription_action', $key);
            if ($exception instanceof AdminApiException) {
                throw $exception;
            }
            throw new AdminApiException($exception->getMessage(), 422);
        }
    }

    /** @return array<string, mixed> */
    public function scheduleSubscriptionPlanChange(int $id, string $planSlug, string $cycle, string $key, ?string $expectedUpdatedAt = null): array
    {
        if ($key === '') { throw new AdminApiException('A chave de idempotência é obrigatória.', 400); }
        $state = $this->idempotency->begin('subscription_plan_change', $key, ['id' => $id, 'planSlug' => $planSlug, 'cycle' => $cycle]);
        if ($state['replayed']) { return [...($state['data'] ?? []), 'replayed' => true]; }
        try {
            $current = $this->db->prepare('SELECT loja_id,updated_at FROM assinaturas WHERE id=:id LIMIT 1'); $current->execute([':id' => $id]); $row = $current->fetch(PDO::FETCH_ASSOC);
            if (!$row) { throw new AdminApiException('Assinatura não encontrada.', 404); }
            $this->assertCurrentVersion($row, $expectedUpdatedAt);
            $result = (new SubscriptionService($this->db))->requestPlanChange((int) $row['loja_id'], $planSlug, $cycle, $this->actorId);
            $this->audit->record('subscription.plan_change_scheduled', 'subscription', $id, null, $result);
            $this->idempotency->complete('subscription_plan_change', $key, $result);
            return $result + ['replayed' => false];
        } catch (Throwable $exception) {
            $this->idempotency->fail('subscription_plan_change', $key);
            if ($exception instanceof AdminApiException) { throw $exception; }
            throw new AdminApiException($exception->getMessage(), 422);
        }
    }

    /** @return array<string, mixed> */
    private function lockUser(int $id): array
    {
        $stmt = $this->db->prepare('SELECT id,nome,email,telefone,status,tipo,loja_vinculada_id,subtipo_funcionario,updated_at FROM usuarios WHERE id=:id LIMIT 1 FOR UPDATE'); $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC); if (!$row) { throw new AdminApiException('Usuário não encontrado.', 404); } return $row;
    }

    /** @return array<string, mixed> */
    private function lockStore(int $id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM lojas WHERE id=:id LIMIT 1 FOR UPDATE'); $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC); if (!$row) { throw new AdminApiException('Loja não encontrada.', 404); } return $row;
    }

    /** @return array<string, mixed> */
    private function lockTransaction(int $id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM transacoes_cashback WHERE id=:id LIMIT 1 FOR UPDATE'); $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC); if (!$row) { throw new AdminApiException('Transação não encontrada.', 404); } return $row;
    }

    private function assertCurrentVersion(array $row, mixed $expectedUpdatedAt): void
    {
        $expected = trim((string) $expectedUpdatedAt);
        if ($expected === '') {
            return;
        }
        $expectedTime = strtotime($expected);
        $currentTime = strtotime((string) ($row['updated_at'] ?? ''));
        if ($expectedTime === false || $currentTime === false || $expectedTime !== $currentTime) {
            throw new AdminApiException('Este registro foi alterado em outra sessão. Atualize a página antes de tentar novamente.', 409);
        }
    }

    /** @param array<string, mixed> $transaction */
    private function creditLegacyCashback(array $transaction): void
    {
        $duplicate = $this->db->prepare("SELECT COUNT(*) FROM cashback_movimentacoes WHERE transacao_origem_id=:id AND tipo_operacao='credito'"); $duplicate->execute([':id' => $transaction['id']]);
        if ((int) $duplicate->fetchColumn() > 0) { return; }
        $amount = AdminMoney::cents($transaction['valor_cliente']);
        if ($amount <= 0) { return; }
        (new GiftbackLedger($this->db))->credit(
            (int) $transaction['usuario_id'], (int) $transaction['loja_id'], $amount,
            'Crédito de giftback legado aprovado pelo administrador', (int) $transaction['id'], $this->actorId,
            'transaction-credit:' . (int) $transaction['id']
        );
    }
}
