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
        $conditions = ["loja_vinculada_id=:store_id", "tipo='funcionario'"];
        $params = [':store_id' => $storeId];
        if (($filters['subtype'] ?? '') !== '') {
            $conditions[] = 'subtipo_funcionario=:subtype';
            $params[':subtype'] = $filters['subtype'];
        }
        if (($filters['status'] ?? '') !== '') {
            $conditions[] = 'status=:status';
            $params[':status'] = $filters['status'];
        }
        if (($filters['search'] ?? '') !== '') {
            $conditions[] = '(nome LIKE :search OR email LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        $where = implode(' AND ', $conditions);
        $count = $this->db->prepare('SELECT COUNT(*) FROM usuarios WHERE ' . $where);
        $count->execute($params);
        $totalItems = (int) $count->fetchColumn();
        $totalPages = max(1, (int) ceil($totalItems / $pageSize));
        $page = max(1, min($page, $totalPages));

        $statement = $this->db->prepare(
            'SELECT id,nome,email,telefone,subtipo_funcionario,status,data_criacao,ultimo_login '
            . 'FROM usuarios WHERE ' . $where . ' ORDER BY data_criacao DESC,id DESC LIMIT :limit OFFSET :offset'
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
            'status' => (string) $row['status'],
            'createdAt' => $this->iso($row['data_criacao']),
            'lastLoginAt' => $this->iso($row['ultimo_login']),
        ], $statement->fetchAll(PDO::FETCH_ASSOC));

        $stats = $this->db->prepare(
            "SELECT COUNT(*) total,SUM(status='ativo') active,SUM(status='inativo') inactive,"
            . "SUM(subtipo_funcionario='gerente') managers,SUM(subtipo_funcionario='financeiro') financial,"
            . "SUM(subtipo_funcionario='vendedor') sales FROM usuarios WHERE loja_vinculada_id=:store_id AND tipo='funcionario'"
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
        return ['id' => (int) $this->db->lastInsertId()];
    }

    /** @param array<string, mixed> $input */
    public function updateEmployee(int $storeId, bool $actorIsOwner, int $employeeId, array $input): void
    {
        $data = $this->employeeInput($input, false);
        $employee = $this->employeeOwnedByStore($storeId, $employeeId);
        if (!$actorIsOwner && ($data['subtype'] === 'gerente' || $employee['subtipo_funcionario'] === 'gerente')) {
            throw new StoreApiException('Apenas o titular pode alterar gerentes.', 403);
        }
        $this->assertEmailAvailable($data['email'], $employeeId);
        $sets = ['nome=:name', 'email=:email', 'telefone=:phone', 'subtipo_funcionario=:subtype'];
        $params = [
            ':name' => $data['name'], ':email' => $data['email'], ':phone' => $data['phone'],
            ':subtype' => $data['subtype'], ':id' => $employeeId, ':store_id' => $storeId,
        ];
        if ($data['password'] !== '') {
            $sets[] = 'senha_hash=:password';
            $params[':password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        $statement = $this->db->prepare(
            'UPDATE usuarios SET ' . implode(',', $sets) . " WHERE id=:id AND loja_vinculada_id=:store_id AND tipo='funcionario'"
        );
        $statement->execute($params);
        $this->revokeEmployeeSessions($employeeId);
    }

    public function deactivateEmployee(int $storeId, int $employeeId): void
    {
        $this->employeeOwnedByStore($storeId, $employeeId);
        $statement = $this->db->prepare(
            "UPDATE usuarios SET status='inativo' WHERE id=:id AND loja_vinculada_id=:store_id AND tipo='funcionario'"
        );
        $statement->execute([':id' => $employeeId, ':store_id' => $storeId]);
        $this->revokeEmployeeSessions($employeeId);
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
            "SELECT id,subtipo_funcionario FROM usuarios WHERE id=:id AND loja_vinculada_id=:store_id AND tipo='funcionario' LIMIT 1"
        );
        $statement->execute([':id' => $employeeId, ':store_id' => $storeId]);
        $employee = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$employee) {
            throw new StoreApiException('Funcionário não encontrado.', 404);
        }
        return $employee;
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
