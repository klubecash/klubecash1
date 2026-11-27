<?php

declare(strict_types=1);

namespace App\Services\Partner;

use PDO;

require_once __DIR__ . '/PartnerApiException.php';
require_once __DIR__ . '/StoreSalesPartnerService.php';

/**
 * Klube Ops: lista as lojas APROVADAS de uma pessoa para ela escolher em vez de digitar o número.
 * A pessoa é a do login único: pelo código (identity_sub, só em lojas criadas pela Loja do Grupo) e/ou pelo e-mail da conta dona da loja.
 * Nunca lista loja de outra pessoa e só devolve id, nome e se a chave já pode vender nela (PARTNER_SALES_STORES).
 */
final class StoreListPartnerService
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(private PDO $db)
    {
    }

    /** @return array{identity_sub:?string,email:?string} */
    public static function parseQuery(array $query): array
    {
        $sub = strtolower(trim((string) ($query['identity_sub'] ?? '')));
        $email = strtolower(trim((string) ($query['email'] ?? '')));
        if ($sub !== '' && !preg_match(self::UUID, $sub)) { throw new PartnerApiException('identity_sub inválido.', 400, 'invalid_identity'); }
        if ($email !== '' && (strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) { throw new PartnerApiException('email inválido.', 400, 'invalid_email'); }
        if ($sub === '' && $email === '') { throw new PartnerApiException('Informe identity_sub ou email da pessoa.', 400, 'identity_required'); }
        return ['identity_sub' => $sub === '' ? null : $sub, 'email' => $email === '' ? null : $email];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<int> $allowed
     * @return list<array{storeId:int,name:string,allowed:bool}>
     */
    public static function shape(array $rows, array $allowed): array
    {
        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            if ($id <= 0 || isset($out[$id])) { continue; }
            $name = trim((string) ($r['nome_fantasia'] ?? ''));
            if ($name === '') { $name = trim((string) ($r['razao_social'] ?? '')); }
            if ($name === '') { $name = 'Loja ' . $id; }
            $out[$id] = ['storeId' => $id, 'name' => $name, 'allowed' => in_array($id, $allowed, true)];
        }
        return array_values($out);
    }

    /**
     * @param array{id:int} $key
     * @return array{stores:list<array{storeId:int,name:string,allowed:bool}>}
     */
    public function listFor(array $key, array $query): array
    {
        $who = self::parseQuery($query);
        $base = "SELECT DISTINCT l.id, l.nome_fantasia, l.razao_social FROM lojas l WHERE l.status = 'aprovado' AND (";
        $tail = ') ORDER BY l.nome_fantasia, l.id LIMIT 100';
        $bySub = 'l.id IN (SELECT store_id FROM loja_assinatura_externa WHERE identity_sub = ?)';
        $byEmail = 'l.usuario_id IN (SELECT id FROM usuarios WHERE LOWER(email) = ?)';
        $parts = [];
        $params = [];
        if ($who['identity_sub'] !== null) { $parts[] = $bySub; $params[] = $who['identity_sub']; }
        if ($who['email'] !== null) { $parts[] = $byEmail; $params[] = $who['email']; }
        try {
            $stmt = $this->db->prepare($base . implode(' OR ', $parts) . $tail);
            $stmt->execute($params);
        } catch (\PDOException $error) {
            // Ainda não existe a tabela de lojas provisionadas (nenhuma loja do Grupo criada): cai só no e-mail.
            if ($who['email'] === null) { return ['stores' => []]; }
            $stmt = $this->db->prepare($base . $byEmail . $tail);
            $stmt->execute([$who['email']]);
        }
        $bindings = StoreSalesPartnerService::parseBindings((string) (getenv('PARTNER_SALES_STORES') ?: ''));
        return ['stores' => self::shape($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], $bindings[$key['id']] ?? [])];
    }
}
