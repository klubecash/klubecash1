<?php

declare(strict_types=1);

namespace App\Services\Store;

use PDO;

final class StoreManagementService
{
    public function __construct(private PDO $db)
    {
    }

    /** @param array<string, string> $filters
     *  @return array<string, mixed>
     */
    public function employees(int $storeId, array $filters, int $page, int $pageSize = 10): array
    {
        $conditions = ["m.store_id=:store_id", "u.tipo='funcionario'"];
        $params = [':store_id' => $storeId];
        if (($filters['subtype'] ?? '') !== '') {
            $conditions[] = 'm.role=:subtype';
            $params[':subtype'] = $filters['subtype'];
        }
        if (($filters['status'] ?? '') !== '') {
            $conditions[] = 'm.status=:status';
            $params[':status'] = ['ativo' => 'active', 'inativo' => 'inactive', 'pendente' => 'pending'][$filters['status']] ?? $filters['status'];
        }
        if (($filters['search'] ?? '') !== '') {
            $conditions[] = '(u.nome LIKE :search OR u.email LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        $where = implode(' AND ', $conditions);
        $count = $this->db->prepare('SELECT COUNT(*) FROM store_user_memberships m JOIN usuarios u ON u.id=m.user_id WHERE ' . $where);
        $count->execute($params);
        $totalItems = (int) $count->fetchColumn();
        $totalPages = max(1, (int) ceil($totalItems / $pageSize));
        $page = max(1, min($page, $totalPages));

        $statement = $this->db->prepare(
            'SELECT u.id,u.nome,u.email,u.telefone,m.role subtipo_funcionario,m.status,u.data_criacao,u.ultimo_login,'
            . 'EXISTS(SELECT 1 FROM store_network_memberships nm JOIN store_network_managers gm ON gm.network_id=nm.network_id '
            . 'WHERE nm.store_id=m.store_id AND gm.user_id=u.id) network_manager '
            . 'FROM store_user_memberships m JOIN usuarios u ON u.id=m.user_id WHERE ' . $where . ' ORDER BY u.data_criacao DESC,u.id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $pageSize, PDO::PARAM_INT);
        $statement->execute();
        $items = array_map(fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['nome'],
            'email' => (string) $row['email'],
            'phone' => (string) ($row['telefone'] ?? ''),
            'subtype' => (string) $row['subtipo_funcionario'],
            'status' => ['active' => 'ativo', 'inactive' => 'inativo', 'pending' => 'pendente'][$row['status']] ?? (string) $row['status'],
            'networkManager' => (bool) $row['network_manager'],
            'createdAt' => $this->iso($row['data_criacao']),
            'lastLoginAt' => $this->iso($row['ultimo_login']),
        ], $statement->fetchAll(PDO::FETCH_ASSOC));

        $stats = $this->db->prepare(
            "SELECT COUNT(*) total,SUM(m.status='active') active,SUM(m.status='inactive') inactive,"
            . "SUM(m.role='gerente') managers,SUM(m.role='financeiro') financial,"
            . "SUM(m.role='vendedor') sales FROM store_user_memberships m JOIN usuarios u ON u.id=m.user_id WHERE m.store_id=:store_id AND u.tipo='funcionario'"
        );
        $stats->execute([':store_id' => $storeId]);
        $statsData = $stats->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'dataState' => $totalItems > 0 ? 'ready' : 'empty',
            'generatedAt' => date(DATE_ATOM),
            'items' => $items,
            'summary' => [
                'total' => (int) ($statsData['total'] ?? 0),
                'active' => (int) ($statsData['active'] ?? 0),
                'inactive' => (int) ($statsData['inactive'] ?? 0),
                'managers' => (int) ($statsData['managers'] ?? 0),
                'financial' => (int) ($statsData['financial'] ?? 0),
                'sales' => (int) ($statsData['sales'] ?? 0),
            ],
            'pagination' => compact('page', 'pageSize', 'totalItems', 'totalPages'),
        ];
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function createEmployee(int $storeId, bool $actorIsOwner, array $input): array
    {
        $data = $this->employeeInput($input, true);
        if (!$actorIsOwner && $data['subtype'] === 'gerente') {
            throw new StoreApiException('Apenas o titular pode cadastrar outro gerente.', 403);
        }
        $this->assertEmailAvailable($data['email']);
        $this->db->beginTransaction();
        try {
        $statement = $this->db->prepare(
            "INSERT INTO usuarios (nome,email,telefone,senha_hash,tipo,status,loja_vinculada_id,subtipo_funcionario,provider,email_verified) "
            . "VALUES (:name,:email,:phone,:password,'funcionario','ativo',:store_id,:subtype,'local',1)"
        );
        $statement->execute([
            ':name' => $data['name'],
            ':email' => $data['email'],
            ':phone' => $data['phone'],
            ':password' => password_hash($data['password'], PASSWORD_DEFAULT),
            ':store_id' => $storeId,
            ':subtype' => $data['subtype'],
        ]);
        $employeeId = (int) $this->db->lastInsertId();
        $this->db->prepare("INSERT INTO store_user_memberships(user_id,store_id,role,status,accepted_at) VALUES(?,?,?,'active',NOW())")
            ->execute([$employeeId, $storeId, $data['subtype']]);
        $this->db->prepare("INSERT INTO store_user_membership_events(user_id,store_id,actor_id,action,new_role,reason)
            VALUES(?,?,?,'created',?,'Conta criada pela gestão da filial')")
            ->execute([$employeeId, $storeId, (int) ($_SESSION['user_id'] ?? 0), $data['subtype']]);
        $this->db->commit();
        return ['id' => $employeeId];
        } catch (\Throwable $error) { $this->db->rollBack(); throw $error; }
    }

    public function inviteExisting(int $storeId, int $actorId, string $email, string $role): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, ['gerente','financeiro','vendedor'], true)) {
            throw new StoreApiException('E-mail ou função inválidos.', 422);
        }
        $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE LOWER(email)=? AND tipo='funcionario' AND status='ativo' LIMIT 1");
        $stmt->execute([$email]);
        $employeeId = (int) ($stmt->fetchColumn() ?: 0);
        if (!$employeeId) { throw new StoreApiException('Conta de funcionário não encontrada. Cadastre um novo funcionário primeiro.', 404); }
        $this->assertNotNetworkManager($storeId, $employeeId);
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT status FROM store_user_memberships WHERE user_id=? AND store_id=? FOR UPDATE');
            $stmt->execute([$employeeId, $storeId]);
            $existing = $stmt->fetchColumn();
            if ($existing === 'active' || $existing === 'pending') { throw new StoreApiException('Funcionário já vinculado ou com convite pendente.', 409); }
            $stmt = $this->db->prepare("INSERT INTO store_user_memberships(user_id,store_id,role,status,invited_by)
                VALUES(?,?,?,'pending',?) ON DUPLICATE KEY UPDATE role=VALUES(role),status='pending',invited_by=VALUES(invited_by),accepted_at=NULL");
            $stmt->execute([$employeeId, $storeId, $role, $actorId]);
            $this->db->prepare("INSERT INTO store_user_membership_events(user_id,store_id,actor_id,action,new_role,reason)
                VALUES(?,?,?,'invited',?,'Convite para filial')")->execute([$employeeId, $storeId, $actorId, $role]);
            $this->db->commit();
            return ['userId' => $employeeId, 'storeId' => $storeId, 'status' => 'pending'];
        } catch (\Throwable $error) { $this->db->rollBack(); throw $error; }
    }

    /** Network managers change a branch link, never the employee identity. */
    public function changeBranchRole(int $storeId, int $employeeId, int $actorId, string $role, string $expectedRole): array
    {
        if (!in_array($role, ['gerente', 'financeiro', 'vendedor'], true)) {
            throw new StoreApiException('Função inválida.', 422);
        }
        $this->assertNotNetworkManager($storeId, $employeeId);
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT m.role,m.status FROM store_user_memberships m
                JOIN usuarios u ON u.id=m.user_id AND u.tipo='funcionario' AND u.status='ativo'
                WHERE m.user_id=? AND m.store_id=? FOR UPDATE");
            $stmt->execute([$employeeId, $storeId]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$current || $current['status'] !== 'active') { throw new StoreApiException('Vínculo ativo não encontrado.', 404); }
            if ($current['role'] !== $expectedRole) { throw new StoreApiException('A função mudou. Atualize a equipe.', 409); }
            if ($role !== $current['role']) {
                $this->db->prepare('UPDATE store_user_memberships SET role=? WHERE user_id=? AND store_id=?')
                    ->execute([$role, $employeeId, $storeId]);
                $this->db->prepare("INSERT INTO store_user_membership_events(user_id,store_id,actor_id,action,old_role,new_role,reason)
                    VALUES(?,?,?,'role_changed',?,?,'Alteração pela gestão da rede')")
                    ->execute([$employeeId, $storeId, $actorId, $current['role'], $role]);
                $this->revokeEmployeeSessions($employeeId);
            }
            $this->db->commit();
        } catch (\Throwable $error) { $this->db->rollBack(); throw $error; }
        return ['userId' => $employeeId, 'storeId' => $storeId, 'role' => $role];
    }

    public function deactivateBranchMember(int $storeId, int $employeeId, int $actorId): array
    {
        $this->assertNotNetworkManager($storeId, $employeeId);
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT m.role,m.status FROM store_user_memberships m
                JOIN usuarios u ON u.id=m.user_id AND u.tipo='funcionario'
                WHERE m.user_id=? AND m.store_id=? FOR UPDATE");
            $stmt->execute([$employeeId, $storeId]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$current) { throw new StoreApiException('Vínculo não encontrado.', 404); }
            if ($current['status'] !== 'inactive') {
                $this->db->prepare("UPDATE store_user_memberships SET status='inactive' WHERE user_id=? AND store_id=?")
                    ->execute([$employeeId, $storeId]);
                $this->db->prepare("INSERT INTO store_user_membership_events(user_id,store_id,actor_id,action,old_role,reason)
                    VALUES(?,?,?,'deactivated',?,'Desativação pela gestão da rede')")
                    ->execute([$employeeId, $storeId, $actorId, $current['role']]);
                $this->revokeEmployeeSessions($employeeId);
            }
            $this->db->commit();
        } catch (\Throwable $error) { $this->db->rollBack(); throw $error; }
        return ['userId' => $employeeId, 'storeId' => $storeId, 'status' => 'inactive'];
    }

    public function invitations(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT m.store_id,l.nome_fantasia store_name,m.role,m.created_at
            FROM store_user_memberships m JOIN lojas l ON l.id=m.store_id
            WHERE m.user_id=? AND m.status='pending' AND l.status='aprovado' ORDER BY m.created_at DESC");
        $stmt->execute([$userId]);
        return ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    public function acceptInvitation(int $userId, int $storeId): array
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT m.role FROM store_user_memberships m JOIN usuarios u ON u.id=m.user_id
                JOIN lojas l ON l.id=m.store_id WHERE m.user_id=? AND m.store_id=? AND m.status='pending'
                AND u.status='ativo' AND l.status='aprovado' FOR UPDATE");
            $stmt->execute([$userId, $storeId]);
            $role = $stmt->fetchColumn();
            if (!$role) { throw new StoreApiException('Convite indisponível.', 404); }
            $this->db->prepare("UPDATE store_user_memberships SET status='active',accepted_at=NOW() WHERE user_id=? AND store_id=?")
                ->execute([$userId, $storeId]);
            $this->db->prepare("INSERT INTO store_user_membership_events(user_id,store_id,actor_id,action,new_role,reason)
                VALUES(?,?,?,'accepted',?,'Aceito pela conta convidada')")->execute([$userId, $storeId, $userId, $role]);
            $this->db->commit();
            return ['storeId' => $storeId, 'role' => $role, 'status' => 'active'];
        } catch (\Throwable $error) { $this->db->rollBack(); throw $error; }
    }

    /** @param array<string, mixed> $input */
    public function updateEmployee(int $storeId, bool $actorIsOwner, int $employeeId, array $input): void
    {
        $this->assertNotNetworkManager($storeId, $employeeId);
        $data = $this->employeeInput($input, false);
        $employee = $this->employeeOwnedByStore($storeId, $employeeId);
        if (!$actorIsOwner && ($data['subtype'] === 'gerente' || $employee['subtipo_funcionario'] === 'gerente')) {
            throw new StoreApiException('Apenas o titular pode alterar gerentes.', 403);
        }
        $identity = $this->db->prepare('SELECT nome,email,telefone FROM usuarios WHERE id=?');
        $identity->execute([$employeeId]);
        $old = $identity->fetch(PDO::FETCH_ASSOC);
        if ($data['name'] !== $old['nome'] || $data['email'] !== $old['email'] || $data['phone'] !== ($old['telefone'] ?? '') || $data['password'] !== '') {
            throw new StoreApiException('Altere apenas a função nesta filial. O funcionário controla seus dados pessoais e sua senha.', 409);
        }
        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE store_user_memberships SET role=? WHERE user_id=? AND store_id=?')->execute([$data['subtype'], $employeeId, $storeId]);
            $this->db->prepare("INSERT INTO store_user_membership_events(user_id,store_id,actor_id,action,old_role,new_role,reason)
                VALUES(?,?,?,'role_changed',?,?,'Alteração pela gestão da filial')")
                ->execute([$employeeId, $storeId, (int) ($_SESSION['user_id'] ?? 0), $employee['subtipo_funcionario'], $data['subtype']]);
            $this->revokeEmployeeSessions($employeeId);
            $this->db->commit();
        } catch (\Throwable $error) { $this->db->rollBack(); throw $error; }
    }

    public function deactivateEmployee(int $storeId, int $employeeId): void
    {
        $this->assertNotNetworkManager($storeId, $employeeId);
        $employee = $this->employeeOwnedByStore($storeId, $employeeId);
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare("UPDATE store_user_memberships SET status='inactive' WHERE user_id=:id AND store_id=:store_id");
            $statement->execute([':id' => $employeeId, ':store_id' => $storeId]);
            $this->db->prepare("INSERT INTO store_user_membership_events(user_id,store_id,actor_id,action,old_role,reason)
                VALUES(?,?,?,'deactivated',?,'Desativação pela gestão da filial')")
                ->execute([$employeeId, $storeId, (int) ($_SESSION['user_id'] ?? 0), $employee['subtipo_funcionario']]);
            $this->revokeEmployeeSessions($employeeId);
            $this->db->commit();
        } catch (\Throwable $error) { $this->db->rollBack(); throw $error; }
    }

    /** @param array<string, mixed> $input */
    public function updateContact(int $storeId, array $input): void
    {
        $phone = preg_replace('/\D+/', '', (string) ($input['phone'] ?? '')) ?? '';
        $website = trim((string) ($input['website'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $errors = [];
        if (strlen($phone) < 10 || strlen($phone) > 11) {
            $errors['phone'] = ['Informe um telefone válido com DDD.'];
        }
        if ($website !== '' && filter_var($website, FILTER_VALIDATE_URL) === false) {
            $errors['website'] = ['Informe uma URL completa e válida.'];
        }
        if (strlen($description) > 1000) {
            $errors['description'] = ['Use no máximo 1000 caracteres.'];
        }
        if ($errors !== []) {
            throw new StoreApiException('Revise os dados de contato.', 422, $errors);
        }
        $statement = $this->db->prepare(
            'UPDATE lojas SET telefone=:phone,website=:website,descricao=:description WHERE id=:store_id'
        );
        $statement->execute([':phone' => $phone, ':website' => $website, ':description' => $description, ':store_id' => $storeId]);
    }

    /** @param array<string, mixed> $input */
    public function updateAddress(int $storeId, array $input): void
    {
        $data = [
            'postalCode' => preg_replace('/\D+/', '', (string) ($input['postalCode'] ?? '')) ?? '',
            'street' => trim((string) ($input['street'] ?? '')),
            'number' => trim((string) ($input['number'] ?? '')),
            'complement' => trim((string) ($input['complement'] ?? '')),
            'neighborhood' => trim((string) ($input['neighborhood'] ?? '')),
            'city' => trim((string) ($input['city'] ?? '')),
            'state' => strtoupper(substr(trim((string) ($input['state'] ?? '')), 0, 2)),
        ];
        $errors = [];
        foreach (['street', 'number', 'neighborhood', 'city', 'state'] as $field) {
            if ($data[$field] === '') {
                $errors[$field] = ['Campo obrigatório.'];
            }
        }
        if (strlen($data['postalCode']) !== 8) {
            $errors['postalCode'] = ['Informe um CEP com oito dígitos.'];
        }
        if ($errors !== []) {
            throw new StoreApiException('Revise o endereço.', 422, $errors);
        }
        $statement = $this->db->prepare(
            'INSERT INTO lojas_endereco (loja_id,cep,logradouro,numero,complemento,bairro,cidade,estado) '
            . 'VALUES (:store_id,:postal_code,:street,:number,:complement,:neighborhood,:city,:state) '
            . 'ON DUPLICATE KEY UPDATE cep=VALUES(cep),logradouro=VALUES(logradouro),numero=VALUES(numero),'
            . 'complemento=VALUES(complemento),bairro=VALUES(bairro),cidade=VALUES(cidade),estado=VALUES(estado)'
        );
        $statement->execute([
            ':store_id' => $storeId, ':postal_code' => $data['postalCode'], ':street' => $data['street'],
            ':number' => $data['number'], ':complement' => $data['complement'], ':neighborhood' => $data['neighborhood'],
            ':city' => $data['city'], ':state' => $data['state'],
        ]);
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword, string $confirmation): void
    {
        $errors = [];
        if (strlen($newPassword) < 8) {
            $errors['newPassword'] = ['A nova senha deve ter pelo menos oito caracteres.'];
        }
        if (!hash_equals($newPassword, $confirmation)) {
            $errors['confirmation'] = ['As senhas não coincidem.'];
        }
        if ($errors !== []) {
            throw new StoreApiException('Revise as senhas informadas.', 422, $errors);
        }
        $statement = $this->db->prepare('SELECT senha_hash FROM usuarios WHERE id=:id LIMIT 1');
        $statement->execute([':id' => $userId]);
        $hash = (string) ($statement->fetchColumn() ?: '');
        if ($hash === '' || !password_verify($currentPassword, $hash)) {
            throw new StoreApiException('A senha atual está incorreta.', 422, ['currentPassword' => ['Senha incorreta.']]);
        }
        $update = $this->db->prepare('UPDATE usuarios SET senha_hash=:password WHERE id=:id');
        $update->execute([':password' => password_hash($newPassword, PASSWORD_DEFAULT), ':id' => $userId]);
    }

    /** @return array<string, mixed> */
    public function redeemPlan(int $storeId, string $code): array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            throw new StoreApiException('Informe o código do plano.', 422, ['code' => ['Campo obrigatório.']]);
        }
        $controller = new \SubscriptionController($this->db);
        if ($controller->getActiveSubscriptionByStore($storeId)) {
            throw new StoreApiException('A loja já possui um plano ativo.', 409);
        }
        $statement = $this->db->prepare('SELECT slug,nome,recorrencia FROM planos WHERE codigo=:code AND ativo=1 LIMIT 1');
        $statement->execute([':code' => $code]);
        $plan = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$plan) {
            throw new StoreApiException('Código de plano inválido ou inativo.', 422, ['code' => ['Código inválido.']]);
        }
        $cycle = $plan['recorrencia'] === 'yearly' ? 'yearly' : 'monthly';
        $result = $controller->assignPlanToStore($storeId, (string) $plan['slug'], null, $cycle);
        if (!($result['success'] ?? false)) {
            throw new StoreApiException((string) ($result['message'] ?? 'Não foi possível ativar o plano.'), 422);
        }
        \FeatureGate::clearCache($storeId);
        return ['planName' => (string) $plan['nome'], 'status' => 'active'];
    }

    /** @param array<string, mixed> $input
     *  @return array{name:string,email:string,phone:string,subtype:string,password:string}
     */
    private function employeeInput(array $input, bool $passwordRequired): array
    {
        $data = [
            'name' => trim((string) ($input['name'] ?? '')),
            'email' => strtolower(trim((string) ($input['email'] ?? ''))),
            'phone' => preg_replace('/\D+/', '', (string) ($input['phone'] ?? '')) ?? '',
            'subtype' => trim((string) ($input['subtype'] ?? '')),
            'password' => (string) ($input['password'] ?? ''),
        ];
        $errors = [];
        if (strlen($data['name']) < 3 || strlen($data['name']) > 100) {
            $errors['name'] = ['Informe um nome entre 3 e 100 caracteres.'];
        }
        if (filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = ['Informe um e-mail válido.'];
        }
        if ($data['phone'] !== '' && (strlen($data['phone']) < 10 || strlen($data['phone']) > 11)) {
            $errors['phone'] = ['Informe um telefone válido com DDD.'];
        }
        if (!in_array($data['subtype'], ['gerente', 'financeiro', 'vendedor'], true)) {
            $errors['subtype'] = ['Selecione uma função válida.'];
        }
        if (($passwordRequired || $data['password'] !== '') && strlen($data['password']) < 8) {
            $errors['password'] = ['A senha deve ter pelo menos oito caracteres.'];
        }
        if ($errors !== []) {
            throw new StoreApiException('Revise os dados do funcionário.', 422, $errors);
        }
        return $data;
    }

    private function assertEmailAvailable(string $email, int $exceptId = 0): void
    {
        $statement = $this->db->prepare('SELECT id FROM usuarios WHERE email=:email AND id<>:except_id LIMIT 1');
        $statement->execute([':email' => $email, ':except_id' => $exceptId]);
        if ($statement->fetchColumn()) {
            throw new StoreApiException('Este e-mail já está cadastrado.', 409, ['email' => ['E-mail já cadastrado.']]);
        }
    }

    /** @return array<string, mixed> */
    private function employeeOwnedByStore(int $storeId, int $employeeId): array
    {
        $statement = $this->db->prepare(
            "SELECT u.id,m.role subtipo_funcionario FROM usuarios u JOIN store_user_memberships m ON m.user_id=u.id WHERE u.id=:id AND m.store_id=:store_id AND u.tipo='funcionario' LIMIT 1"
        );
        $statement->execute([':id' => $employeeId, ':store_id' => $storeId]);
        $employee = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$employee) {
            throw new StoreApiException('Funcionário não encontrado.', 404);
        }
        return $employee;
    }

    private function assertNotNetworkManager(int $storeId, int $employeeId): void
    {
        $stmt = $this->db->prepare("SELECT 1 FROM store_network_memberships nm
            JOIN store_network_managers gm ON gm.network_id=nm.network_id AND gm.user_id=?
            WHERE nm.store_id=? LIMIT 1");
        $stmt->execute([$employeeId, $storeId]);
        if ($stmt->fetchColumn()) { throw new StoreApiException('O acesso do gestor da rede é definido somente pela KlubeCash.', 403); }
    }

    private function revokeEmployeeSessions(int $employeeId): void
    {
        foreach (['sessoes', 'app_sessions'] as $table) {
            $sql = $table === 'sessoes'
                ? 'DELETE FROM sessoes WHERE usuario_id=:user_id'
                : 'DELETE FROM app_sessions WHERE user_id=:user_id';
            $statement = $this->db->prepare($sql);
            $statement->execute([':user_id' => $employeeId]);
        }
    }

    private function iso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $timestamp = strtotime((string) $value);
        return $timestamp === false ? null : date(DATE_ATOM, $timestamp);
    }
}
