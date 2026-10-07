<?php

declare(strict_types=1);

namespace App\Services\StoreWallet;

use App\Services\Giftback\GiftbackClientReadService;
use App\Services\Giftback\GiftbackLedger;
use App\Services\Store\StoreNetworkAccess;
use PDO;
use RuntimeException;
use Throwable;

require_once __DIR__ . '/../Giftback/GiftbackClientReadService.php';
require_once __DIR__ . '/../store/StoreNetworkAccess.php';

final class StoreWalletError extends RuntimeException
{
    public function __construct(string $message, public int $httpStatus = 422) { parent::__construct($message); }
}

final class StoreWalletService
{
    public function __construct(private PDO $db) {}

    private function row(string $sql, array $args = []): ?array
    {
        $stmt = $this->db->prepare($sql); $stmt->execute($args);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function run(string $sql, array $args = []): int
    {
        $stmt = $this->db->prepare($sql); $stmt->execute($args); return $stmt->rowCount();
    }

    private static function phone(string $input): string
    {
        $digits = preg_replace('/\D+/', '', $input) ?? '';
        if (strlen($digits) === 10 || strlen($digits) === 11) { return '55' . $digits; }
        if ((strlen($digits) === 12 || strlen($digits) === 13) && str_starts_with($digits, '55')) { return $digits; }
        throw new StoreWalletError('Informe um celular com DDD.', 422);
    }

    private static function localPhone(string $phone): string { return substr($phone, 2); }
    private static function token(): string { return bin2hex(random_bytes(24)); }
    private static function sessionHash(): string { return hash('sha256', session_id()); }

    public function provision(int $storeId): void
    {
        $store = $this->row('SELECT status FROM lojas WHERE id=?', [$storeId]);
        if (!$store || $store['status'] !== 'aprovado') { return; }
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->run('INSERT IGNORE INTO store_wallet_links(store_id,token,enabled,created_at,updated_at) VALUES(?,?,1,NOW(),NOW())', [$storeId, self::token()]);
            if ($this->row('SELECT store_id FROM store_wallet_links WHERE store_id=?', [$storeId])) { return; }
        }
        throw new StoreWalletError('Não foi possível gerar um link único.', 503);
    }

    public function adminLinks(string $search, int $page): array
    {
        $page = max(1, $page); $limit = 20; $pattern = '%' . substr(trim($search), 0, 100) . '%';
        $approved = $this->db->query("SELECT l.id FROM lojas l LEFT JOIN store_wallet_links w ON w.store_id=l.id WHERE l.status='aprovado' AND w.store_id IS NULL")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($approved as $id) { $this->provision((int) $id); }
        $count = $this->row('SELECT COUNT(*) total FROM lojas l WHERE l.nome_fantasia LIKE ?', [$pattern]);
        $stmt = $this->db->prepare('SELECT l.id,l.nome_fantasia,l.status,w.token,w.enabled,w.version FROM lojas l LEFT JOIN store_wallet_links w ON w.store_id=l.id WHERE l.nome_fantasia LIKE ? ORDER BY l.nome_fantasia LIMIT 20 OFFSET ' . (($page - 1) * $limit));
        $stmt->execute([$pattern]);
        return ['items' => array_map(static fn (array $r): array => ['storeId' => (int) $r['id'], 'storeName' => $r['nome_fantasia'], 'status' => $r['status'], 'token' => $r['token'], 'enabled' => (bool) $r['enabled'], 'version' => (int) $r['version']], $stmt->fetchAll(PDO::FETCH_ASSOC)), 'total' => (int) $count['total'], 'page' => $page];
    }

    public function ownLink(int $storeId): array
    {
        $this->provision($storeId);
        $row = $this->row('SELECT w.token,w.enabled,w.version,l.status FROM lojas l LEFT JOIN store_wallet_links w ON w.store_id=l.id WHERE l.id=?', [$storeId]);
        return ['token' => $row['token'] ?? null, 'enabled' => ($row['status'] ?? '') === 'aprovado' && (bool) ($row['enabled'] ?? false), 'version' => (int) ($row['version'] ?? 0)];
    }

    public function changeLink(int $storeId, string $action, int $version, int $actor): array
    {
        if (!in_array($action, ['enable', 'disable', 'rotate'], true)) { throw new StoreWalletError('Ação inválida.'); }
        $this->db->beginTransaction();
        try {
            $store = $this->row('SELECT status FROM lojas WHERE id=? FOR UPDATE', [$storeId]);
            if (!$store || $store['status'] !== 'aprovado') { throw new StoreWalletError('Somente lojas aprovadas podem ter link ativo.', 409); }
            $this->provision($storeId);
            $row = $this->row('SELECT * FROM store_wallet_links WHERE store_id=? FOR UPDATE', [$storeId]);
            if (!$row || (int) $row['version'] !== $version) { throw new StoreWalletError('Link atualizado por outra pessoa. Recarregue a lista.', 409); }
            $token = $action === 'rotate' ? self::token() : $row['token'];
            $enabled = $action === 'disable' ? 0 : ($action === 'enable' ? 1 : (int) $row['enabled']);
            $this->run('UPDATE store_wallet_links SET token=?,enabled=?,version=version+1,updated_at=NOW(),updated_by=? WHERE store_id=?', [$token, $enabled, $actor, $storeId]);
            $this->run('INSERT INTO store_wallet_link_events(store_id,actor_id,action,previous_token_hash,new_token_hash,previous_enabled,new_enabled,occurred_at) VALUES(?,?,?,?,?,?,?,NOW())', [$storeId, $actor, $action, hash('sha256', $row['token']), hash('sha256', $token), (int) $row['enabled'], $enabled]);
            $this->db->commit();
            return ['token' => $token, 'enabled' => (bool) $enabled, 'version' => $version + 1];
        } catch (Throwable $e) { $this->db->rollBack(); throw $e; }
    }

    public function publicStore(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) { throw new StoreWalletError('Link indisponível.', 404); }
        $row = $this->row("SELECT l.id,l.nome_fantasia,l.logo,l.descricao,n.network_id,r.name network_name
            FROM store_wallet_links w JOIN lojas l ON l.id=w.store_id
            LEFT JOIN store_network_memberships n ON n.store_id=l.id AND n.status='active'
            LEFT JOIN store_networks r ON r.id=n.network_id AND r.status='active'
            WHERE w.token=? AND w.enabled=1 AND l.status='aprovado'", [$token]);
        if (!$row) { throw new StoreWalletError('Link indisponível.', 404); }
        return ['id' => (int) $row['id'], 'name' => $row['nome_fantasia'], 'logo' => $row['logo'], 'description' => $row['descricao'],
            'networkName' => $row['network_name'] ?: null];
    }

    private function account(): ?array
    {
        $id = (int) ($_SESSION['user_id'] ?? 0);
        if (!$id || ($_SESSION['user_type'] ?? '') !== 'cliente') { return null; }
        return $this->row("SELECT id,nome,email,telefone,tipo_cliente FROM usuarios WHERE id=? AND tipo='cliente' AND status='ativo' AND tipo_cliente='completo'", [$id]);
    }

    public function context(string $token): array
    {
        $store = $this->publicStore($token); $account = $this->account();
        $visitor = $this->verifiedVisitor((int) $store['id']);
        return ['store' => $store, 'account' => $account ? ['name' => $account['nome'], 'email' => $account['email']] : null,
            'visitor' => $visitor ? ['name' => $visitor['nome'], 'phone' => $visitor['telefone'], 'expiresAt' => date(DATE_ATOM, (int) $_SESSION['wallet_visitor_until'])] : null,
            'hasPreviousPhoneBalance' => $account ? $this->hasPreviousPhoneBalance((int) $store['id'], (string) ($account['telefone'] ?? '')) : false];
    }

    private function hasPreviousPhoneBalance(int $storeId, string $accountPhone): bool
    {
        try { $phone = self::phone($accountPhone); } catch (StoreWalletError) { return false; }
        $stores = (new StoreNetworkAccess($this->db))->walletStores($storeId);
        $stmt = $this->db->prepare("SELECT id,telefone FROM usuarios WHERE loja_criadora_id IN (" . implode(',', array_map('intval', $stores)) . ") AND tipo='cliente' AND tipo_cliente='visitante' AND status='ativo'");
        $stmt->execute();
        $matchingIds = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $candidate) {
            try {
                if (self::phone((string) $candidate['telefone']) === $phone) { $matchingIds[] = (int) $candidate['id']; }
            } catch (StoreWalletError) {}
        }
        if (count($matchingIds) !== 1) { return false; }
        return (new GiftbackClientReadService($this->db))->wallet($matchingIds[0], $storeId)['availableCents'] > 0;
    }

    private function verifiedVisitor(int $storeId): ?array
    {
        $id = (int) ($_SESSION['wallet_visitor_id'] ?? 0);
        if ($id <= 0 || (int) ($_SESSION['wallet_visitor_until'] ?? 0) <= time()) { return null; }
        $network = (new StoreNetworkAccess($this->db))->networkId($storeId);
        if ($network !== null && $network === (int) ($_SESSION['wallet_network_id'] ?? 0)) {
            return $this->row("SELECT id,nome,telefone FROM usuarios WHERE id=? AND tipo='cliente' AND tipo_cliente='visitante' AND status='ativo'", [$id]);
        }
        if ((int) ($_SESSION['wallet_store_id'] ?? 0) !== $storeId) { return null; }
        return $this->row("SELECT id,nome,telefone FROM usuarios WHERE id=? AND loja_criadora_id=? AND tipo='cliente' AND tipo_cliente='visitante' AND status='ativo'", [$id, $storeId]);
    }

    public function login(string $token, string $email, string $password): array
    {
        $this->publicStore($token);
        $email = strtolower(trim($email));
        $fingerprint = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $email);
        $tries = $this->row('SELECT COUNT(*) total FROM store_wallet_login_attempts WHERE fingerprint=? AND attempted_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)', [$fingerprint]);
        if ((int) $tries['total'] >= 8) { throw new StoreWalletError('Muitas tentativas de login. Aguarde 15 minutos.', 429); }
        $row = $this->row("SELECT id,nome,email,senha_hash FROM usuarios WHERE email=? AND tipo='cliente' AND tipo_cliente='completo' AND status='ativo'", [$email]);
        if (!$row || !$row['senha_hash'] || !password_verify($password, $row['senha_hash'])) {
            $this->run('INSERT INTO store_wallet_login_attempts(fingerprint,attempted_at) VALUES(?,NOW())', [$fingerprint]);
            throw new StoreWalletError('E-mail ou senha incorretos.', 401);
        }
        if (!session_regenerate_id(true)) { throw new StoreWalletError('Não foi possível iniciar a sessão.', 503); }
        $_SESSION['user_id'] = (int) $row['id']; $_SESSION['user_type'] = 'cliente';
        $_SESSION['user_name'] = $row['nome']; $_SESSION['user_email'] = $row['email']; $_SESSION['last_activity'] = time();
        unset($_SESSION['store_id'], $_SESSION['store_name'], $_SESSION['loja_vinculada_id'], $_SESSION['employee_subtype']);
        unset($_SESSION['wallet_visitor_id'], $_SESSION['wallet_store_id'], $_SESSION['wallet_network_id'], $_SESSION['wallet_visitor_until'], $_SESSION['wallet_phone_proof']);
        return ['name' => $row['nome'], 'email' => $row['email']];
    }

    public function requestCode(string $token, string $phone, string $purpose): array
    {
        $store = $this->publicStore($token);
        if (!in_array($purpose, ['visitor', 'signup', 'claim'], true)) { throw new StoreWalletError('Finalidade inválida.'); }
        if ($purpose === 'claim' && !$this->account()) { throw new StoreWalletError('Entre na sua conta antes de vincular o saldo.', 401); }
        if ($purpose !== 'claim' && isset($_SESSION['user_type'])) {
            throw new StoreWalletError('Saia da conta atual para entrar como visitante ou cadastrar outra conta.', 403);
        }
        $phone = self::phone($phone); $storeId = (int) $store['id']; $session = self::sessionHash();
        $recent = $this->row('SELECT sent_at>DATE_SUB(NOW(),INTERVAL 60 SECOND) is_recent FROM store_wallet_challenges WHERE store_id=? AND session_hash=? AND purpose=? ORDER BY id DESC LIMIT 1', [$storeId, $session, $purpose]);
        if ($recent && (int) $recent['is_recent'] === 1) { throw new StoreWalletError('Aguarde um minuto antes de pedir outro código.', 429); }
        $count = $this->row('SELECT COUNT(*) total FROM store_wallet_challenges WHERE phone=? AND sent_at>DATE_SUB(NOW(),INTERVAL 1 HOUR)', [$phone]);
        if ((int) $count['total'] >= 5) { throw new StoreWalletError('Limite de códigos atingido. Tente novamente mais tarde.', 429); }
        $sessionCount = $this->row('SELECT COUNT(*) total FROM store_wallet_challenges WHERE session_hash=? AND sent_at>DATE_SUB(NOW(),INTERVAL 1 HOUR)', [$session]);
        if ((int) $sessionCount['total'] >= 8) { throw new StoreWalletError('Muitas solicitações nesta sessão. Tente mais tarde.', 429); }
        $code = (string) random_int(100000, 999999);
        $secret = (string) (getenv('APP_KEY') ?: getenv('JWT_SECRET') ?: '');
        if (strlen($secret) < 16) { throw new StoreWalletError('A confirmação por telefone está indisponível.', 503); }
        $hash = hash_hmac('sha256', $code, $secret);
        $this->run('INSERT INTO store_wallet_challenges(store_id,session_hash,purpose,phone,code_hash,expires_at,sent_at) VALUES(?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 5 MINUTE),NOW())', [$storeId, $session, $purpose, $phone, $hash]);
        $id = (int) $this->db->lastInsertId();
        try {
            require_once __DIR__ . '/../WhatsApp/WahaConfig.php';
            require_once __DIR__ . '/../WhatsApp/WahaHttpClient.php';
            require_once __DIR__ . '/../WhatsApp/CurlWahaHttpClient.php';
            require_once __DIR__ . '/../WhatsApp/WahaService.php';
            $waha = new \App\Services\WhatsApp\WahaService(\App\Services\WhatsApp\WahaConfig::fromEnvironment(), new \App\Services\WhatsApp\CurlWahaHttpClient());
            $waha->sendText($phone, "KlubeCash: seu código para consultar {$store['name']} é {$code}. Válido por 5 minutos. Não compartilhe.");
        } catch (Throwable $e) {
            $this->run('DELETE FROM store_wallet_challenges WHERE id=?', [$id]);
            throw new StoreWalletError('Não foi possível enviar o código pelo WhatsApp. Tente novamente.', 503);
        }
        return ['sent' => true, 'expiresInSeconds' => 300];
    }

    public function verifyCode(string $token, string $phone, string $purpose, string $code, string $name = ''): array
    {
        $store = $this->publicStore($token); $storeId = (int) $store['id']; $phone = self::phone($phone);
        if (!in_array($purpose, ['visitor', 'signup', 'claim'], true) || !preg_match('/^\d{6}$/', $code)) { throw new StoreWalletError('Código inválido.', 422); }
        if ($purpose === 'claim' && !$this->account()) { throw new StoreWalletError('Entre na sua conta antes de vincular.', 401); }
        if ($purpose !== 'claim' && isset($_SESSION['user_type'])) { throw new StoreWalletError('Saia da conta atual para usar o acesso de visitante.', 403); }
        $this->db->beginTransaction();
        try {
            $challenge = $this->row('SELECT *,expires_at>NOW() is_valid FROM store_wallet_challenges WHERE store_id=? AND session_hash=? AND purpose=? AND phone=? ORDER BY id DESC LIMIT 1 FOR UPDATE', [$storeId, self::sessionHash(), $purpose, $phone]);
            if (!$challenge || $challenge['verified_at'] || (int) $challenge['is_valid'] !== 1 || (int) $challenge['attempts'] >= 5) { throw new StoreWalletError('Código expirado ou indisponível. Peça outro.', 422); }
            $this->run('UPDATE store_wallet_challenges SET attempts=attempts+1 WHERE id=?', [$challenge['id']]);
            $secret = (string) (getenv('APP_KEY') ?: getenv('JWT_SECRET') ?: '');
            if (!hash_equals($challenge['code_hash'], hash_hmac('sha256', $code, $secret))) {
                $this->db->commit(); throw new StoreWalletError('Código incorreto.', 422);
            }
            $this->run('UPDATE store_wallet_challenges SET verified_at=NOW() WHERE id=?', [$challenge['id']]);
            $this->db->commit();
        } catch (Throwable $e) { if ($this->db->inTransaction()) { $this->db->rollBack(); } throw $e; }
        if ($purpose === 'visitor') {
            $name = trim($name);
            if (strlen($name) < 3 || strlen($name) > 100) { throw new StoreWalletError('Informe seu nome para consultar.', 422); }
            $access = new StoreNetworkAccess($this->db);
            $networkId = $access->networkId($storeId);
            $stores = $access->walletStores($storeId);
            $this->db->beginTransaction();
            try {
            $matches = $networkId !== null ? $this->networkVisitors($stores, $phone) : [];
            $visitor = $matches[0] ?? $this->findVisitor($storeId, $phone);
            if (!$visitor) {
                if ($this->row('SELECT id FROM store_wallet_claims WHERE store_id=? AND phone=? LIMIT 1', [$storeId, $phone])) {
                    throw new StoreWalletError('Este telefone já foi vinculado. Entre com e-mail e senha para consultar.', 409);
                }
                if ($this->row("SELECT id FROM usuarios WHERE tipo='cliente' AND tipo_cliente='completo' AND status='ativo' AND telefone IN (?,?) LIMIT 1", [self::localPhone($phone), $phone])) {
                    throw new StoreWalletError('Este telefone já possui uma conta. Entre com e-mail e senha para consultar.', 409);
                }
                $email = 'visitante_' . self::localPhone($phone) . '_loja_' . $storeId . '@klubecash.local';
                try {
                    $this->run("INSERT INTO usuarios(nome,email,telefone,tipo,tipo_cliente,loja_criadora_id,status,provider,email_verified) VALUES(?,?,?,'cliente','visitante',?,'ativo','local',0)", [$name, $email, self::localPhone($phone), $storeId]);
                } catch (Throwable $e) { throw new StoreWalletError('Cadastro ambíguo. Solicite revisão administrativa.', 409); }
                $visitor = ['id' => (int) $this->db->lastInsertId(), 'nome' => $name];
            }
            if ($networkId !== null) {
                $this->mergeNetworkVisitors($networkId, $stores, $phone, (int) $visitor['id'], (int) $challenge['id']);
                $this->run("INSERT INTO network_visitor_verifications(network_id,user_id,challenge_id,verified_until)
                    VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE)) ON DUPLICATE KEY UPDATE
                    challenge_id=VALUES(challenge_id),verified_until=VALUES(verified_until),verified_at=NOW()",
                    [$networkId, (int) $visitor['id'], (int) $challenge['id']]);
            }
            $this->db->commit();
            } catch (Throwable $e) { if ($this->db->inTransaction()) { $this->db->rollBack(); } throw $e; }
            if (!session_regenerate_id(true)) { throw new StoreWalletError('Não foi possível iniciar a sessão temporária.', 503); }
            $_SESSION['wallet_visitor_id'] = (int) $visitor['id']; $_SESSION['wallet_store_id'] = $storeId; $_SESSION['wallet_visitor_until'] = time() + 1800;
            $_SESSION['wallet_network_id'] = $networkId;
            $_SESSION['wallet_phone_proof'] = ['storeId' => $storeId, 'phone' => $phone, 'purpose' => 'signup', 'challengeId' => (int) $challenge['id'], 'until' => time() + 1800];
            (new GiftbackLedger($this->db))->settleWallet((int) $visitor['id'], $storeId);
        } else {
            $_SESSION['wallet_phone_proof'] = ['storeId' => $storeId, 'phone' => $phone, 'purpose' => $purpose, 'challengeId' => (int) $challenge['id'], 'accountId' => $purpose === 'claim' ? (int) ($this->account()['id'] ?? 0) : null, 'until' => time() + 300];
        }
        return ['verified' => true, 'purpose' => $purpose];
    }

    private function findVisitor(int $storeId, string $phone): ?array
    {
        $stmt = $this->db->prepare("SELECT id,nome,telefone FROM usuarios WHERE loja_criadora_id=? AND tipo='cliente' AND tipo_cliente='visitante' AND status='ativo' FOR UPDATE");
        $stmt->execute([$storeId]); $matches = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            try { if (self::phone((string) $row['telefone']) === $phone) { $matches[] = $row; } } catch (StoreWalletError) {}
        }
        if (count($matches) > 1) { throw new StoreWalletError('Há cadastros ambíguos para este telefone. Solicite revisão administrativa.', 409); }
        return $matches[0] ?? null;
    }

    /** Only a verified challenge may call this network-wide lookup. */
    private function networkVisitors(array $storeIds, string $phone): array
    {
        $ids = array_values(array_unique(array_map('intval', $storeIds)));
        $stmt = $this->db->prepare("SELECT id,nome,telefone,loja_criadora_id FROM usuarios WHERE loja_criadora_id IN (" . implode(',', $ids) . ")
            AND tipo='cliente' AND tipo_cliente='visitante' AND status='ativo' ORDER BY id FOR UPDATE");
        $stmt->execute(); $matches = []; $perStore = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            try { if (self::phone((string) $row['telefone']) !== $phone) { continue; } }
            catch (StoreWalletError) { continue; }
            $store = (int) $row['loja_criadora_id'];
            $perStore[$store] = ($perStore[$store] ?? 0) + 1;
            if ($perStore[$store] > 1) { throw new StoreWalletError('Cadastros ambíguos. Solicite revisão administrativa.', 409); }
            $matches[] = $row;
        }
        return $matches;
    }

    /** Reassigns financial rows without changing credit IDs, expiry or allocation links. Caller owns transaction. */
    private function mergeNetworkVisitors(int $networkId, array $stores, string $phone, int $targetId, int $challengeId): void
    {
        $visitors = $this->networkVisitors($stores, $phone);
        $sources = array_values(array_filter($visitors, static fn (array $row): bool => (int) $row['id'] !== $targetId));
        if ($sources === []) { return; }
        $userIds = array_values(array_unique(array_merge([$targetId], array_map(static fn (array $row): int => (int) $row['id'], $sources))));
        sort($userIds, SORT_NUMERIC);
        foreach ($userIds as $id) { $this->row('SELECT id FROM usuarios WHERE id=? FOR UPDATE', [$id]); }
        $stores = array_values(array_unique(array_map('intval', $stores))); sort($stores, SORT_NUMERIC);
        $ledger = new GiftbackLedger($this->db);
        $secret = (string) (getenv('APP_KEY') ?: getenv('JWT_SECRET') ?: '');
        if (strlen($secret) < 16) { throw new StoreWalletError('Confirmação indisponível.', 503); }
        $phoneHash = hash_hmac('sha256', $phone, $secret);
        foreach ($sources as $visitor) {
            $from = (int) $visitor['id']; $summary = [];
            $existing = $this->row('SELECT target_user_id FROM network_wallet_merges WHERE network_id=? AND source_user_id=? FOR UPDATE', [$networkId, $from]);
            if ($existing) { throw new StoreWalletError('Cadastro já vinculado. Atualize a página.', 409); }
            foreach ($stores as $storeId) {
                $wallet = $this->row('SELECT id FROM cashback_saldos WHERE usuario_id=? AND loja_id=?', [$from, $storeId]);
                if (!$wallet) { continue; }
                foreach ([$from, $targetId] as $id) { $ledger->settleWallet($id, $storeId); }
                $source = $this->row('SELECT * FROM cashback_saldos WHERE usuario_id=? AND loja_id=? FOR UPDATE', [$from, $storeId]);
                $target = $this->row('SELECT * FROM cashback_saldos WHERE usuario_id=? AND loja_id=? FOR UPDATE', [$targetId, $storeId]);
                $duplicate = $this->row("SELECT a.id FROM cashback_creditos a JOIN cashback_creditos b
                    ON b.usuario_id=? AND b.loja_id=a.loja_id AND b.source_key=a.source_key
                    WHERE a.usuario_id=? AND a.loja_id=? AND a.source_key<>'opening' LIMIT 1", [$targetId, $from, $storeId]);
                if ($duplicate) { throw new StoreWalletError('Origens financeiras ambíguas. Solicite revisão administrativa.', 409); }
                $this->run("UPDATE cashback_creditos SET source_key=? WHERE usuario_id=? AND loja_id=? AND source_key='opening'", ['network-opening:' . $from, $from, $storeId]);
                $this->run('UPDATE cashback_creditos SET usuario_id=? WHERE usuario_id=? AND loja_id=?', [$targetId, $from, $storeId]);
                $this->run('UPDATE cashback_movimentacoes SET usuario_id=? WHERE usuario_id=? AND loja_id=?', [$targetId, $from, $storeId]);
                $this->run('UPDATE transacoes_cashback SET usuario_id=? WHERE usuario_id=? AND loja_id=?', [$targetId, $from, $storeId]);
                $this->run('UPDATE transacoes_saldo_usado SET usuario_id=? WHERE usuario_id=? AND loja_id=?', [$targetId, $from, $storeId]);
                $marker = $this->row('SELECT cutoff_movement_id FROM cashback_credito_carteiras WHERE usuario_id=? AND loja_id=?', [$from, $storeId]);
                $targetMarker = $this->row('SELECT cutoff_movement_id FROM cashback_credito_carteiras WHERE usuario_id=? AND loja_id=?', [$targetId, $storeId]);
                $unsafe = $this->row("SELECT m.id FROM cashback_movimentacoes m LEFT JOIN cashback_credito_alocacoes a ON a.movement_id=m.id
                    WHERE m.usuario_id=? AND m.loja_id=? AND m.tipo_operacao='uso' AND m.id>? AND m.id<=? AND a.id IS NULL LIMIT 1",
                    [$targetId, $storeId, (int) ($targetMarker['cutoff_movement_id'] ?? 0), (int) ($marker['cutoff_movement_id'] ?? 0)]);
                if ($unsafe) { throw new StoreWalletError('Histórico de uso ambíguo. Solicite revisão administrativa.', 409); }
                $this->run('UPDATE cashback_credito_carteiras SET cutoff_movement_id=GREATEST(cutoff_movement_id,?) WHERE usuario_id=? AND loja_id=?',
                    [(int) ($marker['cutoff_movement_id'] ?? 0), $targetId, $storeId]);
                $this->run('DELETE FROM cashback_credito_carteiras WHERE usuario_id=? AND loja_id=?', [$from, $storeId]);
                $this->run('UPDATE cashback_saldos SET saldo_disponivel=saldo_disponivel+?,total_creditado=total_creditado+?,total_usado=total_usado+? WHERE usuario_id=? AND loja_id=?',
                    [$source['saldo_disponivel'], $source['total_creditado'], $source['total_usado'], $targetId, $storeId]);
                $this->run('DELETE FROM cashback_saldos WHERE usuario_id=? AND loja_id=?', [$from, $storeId]);
                $ledger->settleWallet($targetId, $storeId);
                $summary[] = ['storeId' => $storeId, 'availableCents' => GiftbackLedger::cents($source['saldo_disponivel'])];
            }
            $this->run("UPDATE usuarios SET status='inativo' WHERE id=? AND tipo_cliente='visitante'", [$from]);
            $this->run('INSERT INTO network_wallet_merges(network_id,source_user_id,target_user_id,phone_hash,challenge_id,balances_json) VALUES(?,?,?,?,?,?)',
                [$networkId, $from, $targetId, $phoneHash, $challengeId, json_encode($summary, JSON_UNESCAPED_UNICODE)]);
        }
    }

    private function proof(int $storeId, string $purpose): string
    {
        $p = $_SESSION['wallet_phone_proof'] ?? null;
        if (!is_array($p) || (int) ($p['storeId'] ?? 0) !== $storeId || ($p['purpose'] ?? '') !== $purpose || (int) ($p['until'] ?? 0) <= time()) { throw new StoreWalletError('Confirme seu telefone pelo WhatsApp.', 403); }
        return (string) $p['phone'];
    }

    public function signup(string $token, array $input): array
    {
        $store = $this->publicStore($token); $storeId = (int) $store['id'];
        if (isset($_SESSION['user_type'])) { throw new StoreWalletError('Saia da conta atual antes de criar outra.', 403); }
        $phone = $this->proof($storeId, 'signup');
        $name = trim((string) ($input['name'] ?? '')); $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        if (strlen($name) < 3 || strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8 || $password !== (string) ($input['confirmation'] ?? '')) { throw new StoreWalletError('Confira nome, e-mail e as duas senhas (mínimo 8 caracteres).', 422); }
        $this->db->beginTransaction();
        try {
            if ($this->row('SELECT id FROM usuarios WHERE email=? FOR UPDATE', [$email])) { throw new StoreWalletError('Este e-mail já possui uma conta. Entre com sua senha.', 409); }
            $access = new StoreNetworkAccess($this->db);
            $networkId = $access->networkId($storeId);
            $stores = $access->walletStores($storeId);
            $networkGuests = $networkId !== null ? $this->networkVisitors($stores, $phone) : [];
            $visitor = $networkGuests[0] ?? $this->findVisitor($storeId, $phone);
            if ($visitor) {
                if ($networkId !== null) { $this->mergeNetworkVisitors($networkId, $stores, $phone, (int) $visitor['id'], (int) ($_SESSION['wallet_phone_proof']['challengeId'] ?? 0)); }
                $this->run("UPDATE usuarios SET nome=?,email=?,telefone=?,senha_hash=?,tipo_cliente='completo',email_verified=0 WHERE id=? AND status='ativo'", [$name, $email, self::localPhone($phone), password_hash($password, PASSWORD_DEFAULT), $visitor['id']]);
                $id = (int) $visitor['id'];
            } else {
                if ($this->row('SELECT id FROM store_wallet_claims WHERE store_id=? AND phone=? LIMIT 1', [$storeId, $phone])) {
                    throw new StoreWalletError('Este telefone já possui saldo vinculado. Entre na conta existente.', 409);
                }
                $this->run("INSERT INTO usuarios(nome,email,telefone,senha_hash,tipo,tipo_cliente,status,provider,email_verified) VALUES(?,?,?,?,'cliente','completo','ativo','local',0)", [$name, $email, self::localPhone($phone), password_hash($password, PASSWORD_DEFAULT)]);
                $id = (int) $this->db->lastInsertId();
            }
            (new GiftbackLedger($this->db))->settleWallet($id, $storeId);
            $this->db->commit();
        } catch (Throwable $e) { if ($this->db->inTransaction()) { $this->db->rollBack(); } throw $e; }
        if (!session_regenerate_id(true)) { throw new StoreWalletError('Não foi possível iniciar a sessão.', 503); }
        $_SESSION['user_id'] = $id; $_SESSION['user_type'] = 'cliente'; $_SESSION['user_name'] = $name; $_SESSION['user_email'] = $email;
        $_SESSION['last_activity'] = time();
        unset($_SESSION['store_id'], $_SESSION['store_name'], $_SESSION['loja_vinculada_id'], $_SESSION['employee_subtype']);
        unset($_SESSION['wallet_phone_proof'], $_SESSION['wallet_visitor_id'], $_SESSION['wallet_store_id'], $_SESSION['wallet_network_id'], $_SESSION['wallet_visitor_until']);
        return ['name' => $name, 'email' => $email];
    }

    public function wallet(string $token): array
    {
        $store = $this->publicStore($token); $storeId = (int) $store['id'];
        $account = $this->account(); $visitor = $account ? null : $this->verifiedVisitor($storeId);
        $user = $account ?: $visitor;
        if (!$user) { throw new StoreWalletError('Entre ou confirme seu telefone para consultar o saldo.', 401); }
        $wallet = (new GiftbackClientReadService($this->db))->wallet((int) $user['id'], $storeId);
        return ['store' => $store, 'customerName' => $user['nome'], 'visitor' => !$account, 'wallet' => $wallet];
    }

    public function claim(string $token): array
    {
        $store = $this->publicStore($token); $storeId = (int) $store['id']; $account = $this->account();
        if (!$account) { throw new StoreWalletError('Entre na sua conta antes de vincular.', 401); }
        $phone = $this->proof($storeId, 'claim');
        if ((int) ($_SESSION['wallet_phone_proof']['accountId'] ?? 0) !== (int) $account['id']) { throw new StoreWalletError('Confirme o telefone novamente nesta conta.', 403); }
        $access = new StoreNetworkAccess($this->db);
        $networkId = $access->networkId($storeId);
        if ($networkId !== null) {
            $stores = $access->walletStores($storeId);
            $this->db->beginTransaction();
            try {
                $visitors = $this->networkVisitors($stores, $phone);
                if ($visitors === []) {
                    $secret = (string) (getenv('APP_KEY') ?: getenv('JWT_SECRET') ?: '');
                    $hash = hash_hmac('sha256', $phone, $secret);
                    $prior = $this->row('SELECT id FROM network_wallet_merges WHERE network_id=? AND target_user_id=? AND phone_hash=? LIMIT 1', [$networkId, (int) $account['id'], $hash]);
                    if (!$prior) { throw new StoreWalletError('Nenhum saldo de visitante encontrado nesta rede.', 404); }
                    $replayed = true;
                } else {
                    $this->mergeNetworkVisitors($networkId, $stores, $phone, (int) $account['id'], (int) ($_SESSION['wallet_phone_proof']['challengeId'] ?? 0));
                    $replayed = false;
                }
                $balance = (new GiftbackLedger($this->db))->networkWallet((int) $account['id'], $stores)['availableCents'];
                $this->db->commit();
                return ['claimed' => true, 'balanceCents' => $balance, 'replayed' => $replayed];
            } catch (Throwable $e) { if ($this->db->inTransaction()) { $this->db->rollBack(); } throw $e; }
        }
        $this->db->beginTransaction();
        try {
            $visitor = $this->findVisitor($storeId, $phone);
            if (!$visitor) {
                $prior = $this->row('SELECT account_id FROM store_wallet_claims WHERE store_id=? AND phone=? ORDER BY id DESC LIMIT 1', [$storeId, $phone]);
                if ($prior && (int) $prior['account_id'] === (int) $account['id']) {
                    $balance = (new GiftbackLedger($this->db))->settleWallet((int) $account['id'], $storeId);
                    $this->db->commit();
                    return ['claimed' => true, 'balanceCents' => $balance, 'replayed' => true];
                }
                throw new StoreWalletError('Nenhum saldo de visitante encontrado nesta loja.', 404);
            }
            $from = (int) $visitor['id']; $to = (int) $account['id'];
            if ($from === $to) { throw new StoreWalletError('A conta já está vinculada.', 409); }
            $claim = $this->row('SELECT id,account_id FROM store_wallet_claims WHERE store_id=? AND visitor_id=? FOR UPDATE', [$storeId, $from]);
            if ($claim) { throw new StoreWalletError('Saldo já vinculado. Atualize a página.', 409); }
            $ids = [$from, $to]; sort($ids, SORT_NUMERIC);
            foreach ($ids as $id) { $this->row('SELECT id FROM usuarios WHERE id=? FOR UPDATE', [$id]); }
            $ledger = new GiftbackLedger($this->db);
            foreach ($ids as $id) { $ledger->settleWallet($id, $storeId); }
            $source = $this->row('SELECT * FROM cashback_saldos WHERE usuario_id=? AND loja_id=? FOR UPDATE', [$from, $storeId]);
            $target = $this->row('SELECT * FROM cashback_saldos WHERE usuario_id=? AND loja_id=? FOR UPDATE', [$to, $storeId]);
            if (!$source || !$target) { throw new StoreWalletError('Carteira indisponível para vinculação.', 409); }
            $duplicate = $this->row('SELECT a.id FROM cashback_creditos a JOIN cashback_creditos b ON b.usuario_id=? AND b.loja_id=a.loja_id AND b.source_key=a.source_key WHERE a.usuario_id=? AND a.loja_id=? AND a.source_key<>\'opening\' LIMIT 1', [$to, $from, $storeId]);
            if ($duplicate) { throw new StoreWalletError('Origens financeiras ambíguas. Solicite revisão administrativa.', 409); }
            $this->run('UPDATE cashback_creditos SET source_key=? WHERE usuario_id=? AND loja_id=? AND source_key=\'opening\'', ['claimed-opening:' . $from, $from, $storeId]);
            $creditsMoved = $this->run('UPDATE cashback_creditos SET usuario_id=? WHERE usuario_id=? AND loja_id=?', [$to, $from, $storeId]);
            $movementsMoved = $this->run('UPDATE cashback_movimentacoes SET usuario_id=? WHERE usuario_id=? AND loja_id=?', [$to, $from, $storeId]);
            $this->run('UPDATE transacoes_cashback SET usuario_id=? WHERE usuario_id=? AND loja_id=?', [$to, $from, $storeId]);
            $this->run('UPDATE transacoes_saldo_usado SET usuario_id=? WHERE usuario_id=? AND loja_id=?', [$to, $from, $storeId]);
            $marker = $this->row('SELECT cutoff_movement_id FROM cashback_credito_carteiras WHERE usuario_id=? AND loja_id=?', [$from, $storeId]);
            $targetMarker = $this->row('SELECT cutoff_movement_id FROM cashback_credito_carteiras WHERE usuario_id=? AND loja_id=?', [$to, $storeId]);
            $unsafe = $this->row("SELECT m.id FROM cashback_movimentacoes m LEFT JOIN cashback_credito_alocacoes a ON a.movement_id=m.id WHERE m.usuario_id=? AND m.loja_id=? AND m.tipo_operacao='uso' AND m.id>? AND m.id<=? AND a.id IS NULL LIMIT 1", [$to, $storeId, (int) ($targetMarker['cutoff_movement_id'] ?? 0), (int) ($marker['cutoff_movement_id'] ?? 0)]);
            if ($unsafe) { throw new StoreWalletError('Histórico de uso ambíguo. Solicite revisão administrativa.', 409); }
            $this->run('UPDATE cashback_credito_carteiras SET cutoff_movement_id=GREATEST(cutoff_movement_id,?) WHERE usuario_id=? AND loja_id=?', [(int) ($marker['cutoff_movement_id'] ?? 0), $to, $storeId]);
            $this->run('DELETE FROM cashback_credito_carteiras WHERE usuario_id=? AND loja_id=?', [$from, $storeId]);
            $this->run('UPDATE cashback_saldos SET saldo_disponivel=saldo_disponivel+?,total_creditado=total_creditado+?,total_usado=total_usado+? WHERE usuario_id=? AND loja_id=?', [$source['saldo_disponivel'], $source['total_creditado'], $source['total_usado'], $to, $storeId]);
            $this->run('DELETE FROM cashback_saldos WHERE usuario_id=? AND loja_id=?', [$from, $storeId]);
            $this->run("UPDATE usuarios SET status='inativo' WHERE id=? AND tipo_cliente='visitante'", [$from]);
            $proof = $_SESSION['wallet_phone_proof'];
            $this->run('INSERT INTO store_wallet_claims(store_id,visitor_id,account_id,phone,available_cents,target_before_cents,target_after_cents,credits_moved,movements_moved,claimed_at,challenge_id) VALUES(?,?,?,?,?,?,?,?,?,NOW(),?)', [$storeId, $from, $to, $phone, GiftbackLedger::cents($source['saldo_disponivel']), GiftbackLedger::cents($target['saldo_disponivel']), GiftbackLedger::cents($source['saldo_disponivel']) + GiftbackLedger::cents($target['saldo_disponivel']), $creditsMoved, $movementsMoved, (int) $proof['challengeId']]);
            $balance = $ledger->settleWallet($to, $storeId);
            $this->db->commit();
            return ['claimed' => true, 'balanceCents' => $balance];
        } catch (Throwable $e) { if ($this->db->inTransaction()) { $this->db->rollBack(); } throw $e; }
    }
}
