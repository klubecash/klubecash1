<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Core\Logger;
use App\Services\Store\StoreApiException;
use App\Services\Store\StoreCustomerService;
use App\Services\Store\StoreIdempotencyService;
use App\Services\Store\StoreMoney;
use App\Services\Store\StoreTransactionService;
use App\Services\Store\StoreWhatsAppNotificationService;
use PDO;
use Throwable;

final class WhatsAppMenuService
{
    private WhatsAppMenuStore $store;

    public function __construct(
        private PDO $db,
        private WahaService $waha,
        private WhatsAppMenuConfig $config
    ) {
        $this->store = new WhatsAppMenuStore($db, $config);
    }

    /** @param array<string,mixed> $event */
    public function process(int $eventId, array $event, string $requestId = ''): ?int
    {
        if (!$this->config->menuEnabled) {
            return $this->matchUniqueClient($this->canonicalFromEvent($event))['id'] ?? null;
        }

        $fromMe = ($event['payload']['fromMe'] ?? $event['payload']['key']['fromMe'] ?? false) === true;
        $chatId = $this->chatId($event, $fromMe);
        if ($chatId === null || $this->isNonPrivateChat($chatId)) {
            return null;
        }
        $canonical = $this->canonicalPhone($event, $chatId, $fromMe);
        if ($canonical === null) {
            return null;
        }
        $senderKey = $this->config->senderKey($canonical);
        $this->store->conversation($senderKey);

        if ($fromMe) {
            $this->handleOwnMessage($event, $senderKey);
            return null;
        }
        if (!$this->isTextMessage($event)) {
            return null;
        }

        $body = trim((string) ($event['payload']['body'] ?? $event['payload']['text'] ?? ''));
        if ($body === '') {
            return null;
        }
        $normalized = $this->normalizeCommand($body);
        if (!$this->store->allowInbound($senderKey)) {
            return null;
        }

        $conversation = $this->store->conversation($senderKey);
        if ($normalized === '/klube') {
            $this->store->setState($senderKey, 'main_menu');
            $this->reply($canonical, $senderKey, $eventId, 'menu', $this->mainMenu());
            $this->store->audit($senderKey, 'menu.open', 'success', requestId: $requestId, actionKey: 'menu:' . $eventId);
            return $this->matchUniqueClient($canonical)['id'] ?? null;
        }

        if ($normalized === '/sair') {
            $this->store->close($senderKey);
            $this->reply($canonical, $senderKey, $eventId, 'exit', "Menu Klube Cash encerrado.\nPara abrir novamente, envie */klube*.");
            $this->store->audit($senderKey, 'menu.close', 'success', requestId: $requestId, actionKey: 'exit:' . $eventId);
            return null;
        }

        $active = ($conversation['status'] ?? 'closed') === 'open'
            && !empty($conversation['menu_expires_at'])
            && strtotime((string) $conversation['menu_expires_at']) >= time();
        if (!$active) {
            return null;
        }

        if ($normalized === '/cancelar') {
            $authenticated = $this->merchant($conversation);
            $state = $authenticated === null ? 'main_menu' : 'merchant_menu';
            $this->store->setState($senderKey, $state);
            $this->reply(
                $canonical,
                $senderKey,
                $eventId,
                'cancel',
                "Operacao cancelada.\n\n" . ($authenticated === null ? $this->mainMenu() : $this->merchantMenu($authenticated['storeName']))
            );
            return $authenticated['userId'] ?? null;
        }

        $this->store->touch($senderKey);
        $conversation = $this->store->conversation($senderKey);
        $state = (string) ($conversation['state'] ?? 'main_menu');
        $payload = $this->config->decrypt($conversation['state_payload'] ?? null);

        try {
            return $this->dispatch($eventId, $requestId, $canonical, $senderKey, $body, $normalized, $state, $payload, $conversation);
        } catch (StoreApiException $exception) {
            $this->store->incrementInvalid($senderKey);
            $this->reply($canonical, $senderKey, $eventId, 'validation', "Nao foi possivel concluir: " . $exception->getMessage() . "\n\nEnvie */cancelar* para voltar.");
            return isset($conversation['authenticated_user_id']) ? (int) $conversation['authenticated_user_id'] : null;
        }
    }

    /** @return array{available:int,sent:int,pending:int,failed:int} */
    public function processPendingReplies(int $limit = 20): array
    {
        $items = $this->store->pendingBotMessages($limit);
        $stats = ['available' => count($items), 'sent' => 0, 'pending' => 0, 'failed' => 0];
        foreach ($items as $item) {
            try {
                $response = $this->waha->sendText($this->phoneDigits($item['phone']), $item['message']);
                $this->store->finishBotMessage($item['actionKey'], 'sent', $this->providerMessageId($response), null);
                $stats['sent']++;
            } catch (WahaException $exception) {
                if ($exception->deliveryUnknown) {
                    $this->store->finishBotMessage($item['actionKey'], 'delivery_unknown', null, 'delivery_unknown');
                    $stats['failed']++;
                } elseif ($exception->transient) {
                    $this->store->finishBotMessage($item['actionKey'], 'pending', null, 'provider_unavailable');
                    $stats['pending']++;
                } else {
                    $this->store->finishBotMessage($item['actionKey'], 'failed', null, 'provider_rejected');
                    $stats['failed']++;
                }
            }
        }
        return $stats;
    }

    /** @param array<string,mixed> $payload @param array<string,mixed> $conversation */
    private function dispatch(
        int $eventId,
        string $requestId,
        string $canonical,
        string $senderKey,
        string $body,
        string $normalized,
        string $state,
        array $payload,
        array $conversation
    ): ?int {
        if ($state === 'main_menu') {
            return $this->handleMainMenu($eventId, $requestId, $canonical, $senderKey, $normalized, $conversation);
        }
        if ($state === 'store_search_term') {
            return $this->handleStoreSearch($eventId, $canonical, $senderKey, $body, 1);
        }
        if ($state === 'store_search_results') {
            if ($normalized === 'mais') {
                return $this->handleStoreSearch(
                    $eventId,
                    $canonical,
                    $senderKey,
                    (string) ($payload['query'] ?? ''),
                    ((int) ($payload['page'] ?? 1)) + 1
                );
            }
            $this->store->setState($senderKey, 'store_search_term');
            $this->reply($canonical, $senderKey, $eventId, 'store-search-again', 'Digite outro nome, categoria ou cidade para pesquisar.');
            return null;
        }
        if ($state === 'merchant_waiting_auth') {
            $this->reply($canonical, $senderKey, $eventId, 'auth-waiting', "O acesso ainda nao foi autorizado. Abra o link enviado ou digite */cancelar*.");
            return null;
        }

        $merchant = $this->merchant($conversation);
        if ($merchant === null) {
            $this->store->clearAuthentication($senderKey);
            $this->reply($canonical, $senderKey, $eventId, 'auth-expired', "Seu acesso lojista expirou ou deixou de ser valido.\nAbra novamente em *3 - Acesso do lojista*.");
            return null;
        }

        return match ($state) {
            'merchant_menu' => $this->handleMerchantMenu($eventId, $canonical, $senderKey, $normalized, $merchant),
            'customer_phone' => $this->handleCustomerPhone($eventId, $canonical, $senderKey, $body, $payload, $merchant),
            'visitor_offer' => $this->handleVisitorOffer($eventId, $canonical, $senderKey, $normalized, $payload, $merchant),
            'visitor_name' => $this->handleVisitorName($eventId, $canonical, $senderKey, $body, $payload, $merchant),
            'visitor_confirm' => $this->handleVisitorConfirm($eventId, $requestId, $canonical, $senderKey, $normalized, $payload, $merchant),
            'sale_amount' => $this->handleSaleAmount($eventId, $canonical, $senderKey, $body, $payload, $merchant),
            'sale_balance' => $this->handleSaleBalance($eventId, $canonical, $senderKey, $body, $payload, $merchant),
            'sale_confirm' => $this->handleSaleConfirm($eventId, $requestId, $canonical, $senderKey, $normalized, $payload, $merchant),
            default => $this->resetMerchant($eventId, $canonical, $senderKey, $merchant),
        };
    }

    /** @param array<string,mixed> $conversation */
    private function handleMainMenu(
        int $eventId,
        string $requestId,
        string $canonical,
        string $senderKey,
        string $choice,
        array $conversation
    ): ?int {
        if ($choice === '1') {
            $client = $this->matchUniqueClient($canonical);
            if (($client['status'] ?? '') === 'duplicate') {
                $this->reply($canonical, $senderKey, $eventId, 'balance-duplicate', 'Este telefone esta vinculado a mais de uma conta de cliente. Por seguranca, nenhum saldo foi exibido. Atualize o cadastro ou fale com o suporte.');
                $this->store->audit($senderKey, 'balance.lookup', 'duplicate_identity', requestId: $requestId, actionKey: 'balance:' . $eventId);
                return null;
            }
            if (empty($client['id'])) {
                $this->reply($canonical, $senderKey, $eventId, 'balance-missing', 'Nao encontramos um cliente ativo com este numero. A consulta so funciona pelo telefone cadastrado na sua propria conta.');
                $this->store->audit($senderKey, 'balance.lookup', 'not_found', requestId: $requestId, actionKey: 'balance:' . $eventId);
                return null;
            }
            $this->reply($canonical, $senderKey, $eventId, 'balance', $this->balanceMessage((int) $client['id']));
            $this->store->audit($senderKey, 'balance.lookup', 'success', (int) $client['id'], requestId: $requestId, actionKey: 'balance:' . $eventId);
            return (int) $client['id'];
        }
        if ($choice === '2') {
            $this->store->setState($senderKey, 'store_search_term');
            $this->reply($canonical, $senderKey, $eventId, 'store-search-prompt', "Digite o *nome, categoria ou cidade* da loja que deseja encontrar.\nExemplo: Alimentacao ou Patos de Minas");
            return null;
        }
        if ($choice === '3') {
            if (!$this->config->merchantAuthEnabled) {
                $this->reply($canonical, $senderKey, $eventId, 'auth-disabled', 'O acesso do lojista pelo WhatsApp ainda nao esta disponivel.');
                return null;
            }
            $existing = $this->merchant($conversation);
            if ($existing !== null) {
                $this->store->setState($senderKey, 'merchant_menu');
                $this->reply($canonical, $senderKey, $eventId, 'merchant-menu', $this->merchantMenu($existing['storeName']));
                return $existing['userId'];
            }
            try {
                $token = $this->store->createChallenge($senderKey);
            } catch (WahaException) {
                $this->reply($canonical, $senderKey, $eventId, 'auth-limit', 'Muitas tentativas de acesso. Aguarde 15 minutos antes de solicitar outro link.');
                return null;
            }
            $this->store->setState($senderKey, 'merchant_waiting_auth');
            $url = $this->config->siteUrl . '/whatsapp/autenticar?token=' . rawurlencode($token);
            $this->reply($canonical, $senderKey, $eventId, 'auth-link', "🔐 *Acesso seguro do lojista*\n\nAbra o link abaixo e entre com as credenciais oficiais do Klube Cash:\n{$url}\n\nO link expira em *5 minutos*. Sua senha nunca deve ser enviada nesta conversa.");
            $this->store->audit($senderKey, 'merchant.challenge', 'created', requestId: $requestId, actionKey: 'challenge:' . $eventId);
            return null;
        }
        if ($choice === '0') {
            $this->store->close($senderKey);
            $this->reply($canonical, $senderKey, $eventId, 'menu-close', 'Menu encerrado. Quando precisar, envie */klube*.');
            return null;
        }

        $this->invalidChoice($canonical, $senderKey, $eventId, $this->mainMenu());
        return null;
    }

    private function handleStoreSearch(int $eventId, string $canonical, string $senderKey, string $query, int $page): ?int
    {
        $query = trim($query);
        if (mb_strlen($query) < 2 || mb_strlen($query) > 80) {
            $this->invalidChoice($canonical, $senderKey, $eventId, 'Digite pelo menos duas letras para pesquisar uma loja.');
            return null;
        }
        $page = max(1, $page);
        $offset = ($page - 1) * 5;
        $statement = $this->db->prepare(
            "SELECT l.nome_fantasia,l.categoria,l.cashback_ativo,COALESCE(l.porcentagem_cliente,0) percentage,"
            . "COALESCE(e.cidade,'') city,COALESCE(e.estado,'') state FROM lojas l "
            . 'LEFT JOIN lojas_endereco e ON e.loja_id=l.id '
            . "WHERE l.status='aprovado' AND (l.nome_fantasia LIKE :term OR l.categoria LIKE :term_category "
            . 'OR e.cidade LIKE :term_city) ORDER BY l.nome_fantasia LIMIT 6 OFFSET ' . $offset
        );
        $term = '%' . $query . '%';
        $statement->execute([':term' => $term, ':term_category' => $term, ':term_city' => $term]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $hasMore = count($rows) > 5;
        $rows = array_slice($rows, 0, 5);
        if ($rows === []) {
            $this->store->setState($senderKey, 'store_search_term');
            $this->reply($canonical, $senderKey, $eventId, 'store-search-empty', "Nenhuma loja aprovada foi encontrada para *{$this->safeText($query)}*.\nDigite outro termo ou */cancelar*.");
            return null;
        }
        $lines = ["🏪 *Lojas parceiras*", ''];
        foreach ($rows as $row) {
            $location = trim((string) $row['city']);
            if (trim((string) $row['state']) !== '') {
                $location .= ($location !== '' ? '/' : '') . trim((string) $row['state']);
            }
            $lines[] = '*' . $this->safeText((string) $row['nome_fantasia']) . '*';
            $lines[] = 'Categoria: ' . $this->safeText((string) ($row['categoria'] ?: 'Outros'));
            if ($location !== '') {
                $lines[] = 'Local: ' . $this->safeText($location);
            }
            $lines[] = (int) $row['cashback_ativo'] === 1
                ? 'Cashback: ' . $this->formatPercentage((float) $row['percentage'])
                : 'Cashback temporariamente indisponivel';
            $lines[] = '';
        }
        $lines[] = $hasMore ? 'Envie *mais* para ver outros resultados.' : 'Fim dos resultados.';
        $lines[] = 'Ou digite uma nova pesquisa.';
        $this->store->setState($senderKey, 'store_search_results', ['query' => $query, 'page' => $page]);
        $this->reply($canonical, $senderKey, $eventId, 'store-search-' . $page, implode("\n", $lines));
        return null;
    }

    /** @param array{userId:int,storeId:int,storeName:string,userName:string} $merchant */
    private function handleMerchantMenu(int $eventId, string $canonical, string $senderKey, string $choice, array $merchant): int
    {
        if ($choice === '1') {
            if (!$this->config->salesEnabled) {
                $this->reply($canonical, $senderKey, $eventId, 'sales-disabled', 'O registro de vendas pelo WhatsApp ainda nao esta ativado.');
                return $merchant['userId'];
            }
            $this->store->setState($senderKey, 'customer_phone', ['mode' => 'sale']);
            $this->reply($canonical, $senderKey, $eventId, 'sale-customer', 'Informe o telefone do cliente com DDD.');
            return $merchant['userId'];
        }
        if ($choice === '2') {
            $this->store->setState($senderKey, 'customer_phone', ['mode' => 'lookup']);
            $this->reply($canonical, $senderKey, $eventId, 'lookup-customer', 'Informe o telefone do cliente com DDD. O saldo exibido sera somente desta loja.');
            return $merchant['userId'];
        }
        if ($choice === '3') {
            $statement = $this->db->prepare(
                'SELECT codigo_transacao,valor_total,valor_cliente,status,data_transacao FROM transacoes_cashback '
                . 'WHERE loja_id=:store AND criado_por=:user ORDER BY data_transacao DESC,id DESC LIMIT 5'
            );
            $statement->execute([':store' => $merchant['storeId'], ':user' => $merchant['userId']]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            $lines = ["🧾 *Ultimas vendas registradas por voce*", ''];
            if ($rows === []) {
                $lines[] = 'Nenhuma venda encontrada.';
            } else {
                foreach ($rows as $row) {
                    $lines[] = '*' . $this->safeText((string) $row['codigo_transacao']) . '* — ' . $this->money(StoreMoney::toCents($row['valor_total'])) . ' — ' . $this->safeText((string) $row['status']);
                }
            }
            $this->reply($canonical, $senderKey, $eventId, 'recent-sales', implode("\n", $lines));
            return $merchant['userId'];
        }
        if ($choice === '4' || $choice === '0') {
            $this->store->clearAuthentication($senderKey);
            $this->reply($canonical, $senderKey, $eventId, 'merchant-logout', "Acesso lojista encerrado com seguranca.\n\n" . $this->mainMenu());
            return $merchant['userId'];
        }
        $this->invalidChoice($canonical, $senderKey, $eventId, $this->merchantMenu($merchant['storeName']));
        return $merchant['userId'];
    }

    /** @param array<string,mixed> $payload @param array{userId:int,storeId:int,storeName:string,userName:string} $merchant */
    private function handleCustomerPhone(int $eventId, string $canonical, string $senderKey, string $body, array $payload, array $merchant): int
    {
        $phone = $this->canonicalInputPhone($body);
        if ($phone === null) {
            $this->invalidChoice($canonical, $senderKey, $eventId, 'Telefone invalido. Informe DDD e numero, por exemplo: 38999999999.');
            return $merchant['userId'];
        }
        $customers = $this->customersByPhone($phone, $merchant['storeId']);
        if (count($customers) > 1) {
            $this->reply($canonical, $senderKey, $eventId, 'customer-duplicate', 'Existem cadastros duplicados com esse telefone. Nenhum cliente foi selecionado; corrija o cadastro antes de continuar.');
            return $merchant['userId'];
        }
        if ($customers === []) {
            if (($payload['mode'] ?? '') !== 'sale') {
                $this->store->setState($senderKey, 'merchant_menu');
                $this->reply($canonical, $senderKey, $eventId, 'customer-empty', "Cliente nao encontrado.\n\n" . $this->merchantMenu($merchant['storeName']));
                return $merchant['userId'];
            }
            $this->store->setState($senderKey, 'visitor_offer', ['phone' => $this->nationalDigits($phone)]);
            $this->reply($canonical, $senderKey, $eventId, 'visitor-offer', "Cliente nao encontrado.\n\n1️⃣ Cadastrar como visitante\n0️⃣ Voltar");
            return $merchant['userId'];
        }
        $customer = $customers[0];
        $summary = "👤 *Cliente encontrado*\n" . $this->maskedName((string) $customer['name'])
            . "\nSaldo nesta loja: *" . $this->money((int) $customer['balanceCents']) . '*';
        if (($payload['mode'] ?? '') === 'lookup') {
            $this->store->setState($senderKey, 'merchant_menu');
            $this->reply($canonical, $senderKey, $eventId, 'customer-result', $summary . "\n\n" . $this->merchantMenu($merchant['storeName']));
            return $merchant['userId'];
        }
        $this->store->setState($senderKey, 'sale_amount', [
            'customerId' => (int) $customer['id'],
            'customerName' => (string) $customer['name'],
            'balanceCents' => (int) $customer['balanceCents'],
        ]);
        $this->reply($canonical, $senderKey, $eventId, 'sale-amount-prompt', $summary . "\n\nInforme o *valor total da compra*.\nExemplo: 100,00");
        return $merchant['userId'];
    }

    /** @param array<string,mixed> $payload @param array{userId:int,storeId:int,storeName:string,userName:string} $merchant */
    private function handleVisitorOffer(int $eventId, string $canonical, string $senderKey, string $choice, array $payload, array $merchant): int
    {
        if ($choice === '1') {
            $this->store->setState($senderKey, 'visitor_name', $payload);
            $this->reply($canonical, $senderKey, $eventId, 'visitor-name', 'Digite o nome completo do visitante.');
            return $merchant['userId'];
        }
        $this->store->setState($senderKey, 'merchant_menu');
        $this->reply($canonical, $senderKey, $eventId, 'visitor-cancel', $this->merchantMenu($merchant['storeName']));
        return $merchant['userId'];
    }

    /** @param array<string,mixed> $payload @param array{userId:int,storeId:int,storeName:string,userName:string} $merchant */
    private function handleVisitorName(int $eventId, string $canonical, string $senderKey, string $body, array $payload, array $merchant): int
    {
        $name = trim(preg_replace('/\s+/u', ' ', $body) ?? '');
        if (mb_strlen($name) < 3 || mb_strlen($name) > 100) {
            $this->invalidChoice($canonical, $senderKey, $eventId, 'Informe um nome entre 3 e 100 caracteres.');
            return $merchant['userId'];
        }
        $confirmation = (string) random_int(1000, 9999);
        $operationKey = 'wa-visitor-' . bin2hex(random_bytes(12));
        $next = [...$payload, 'name' => $name, 'confirmation' => $confirmation, 'operationKey' => $operationKey];
        $this->store->setState($senderKey, 'visitor_confirm', $next);
        $this->reply(
            $canonical,
            $senderKey,
            $eventId,
            'visitor-preview',
            "👤 *Revisao do visitante*\nNome: " . $this->safeText($name) . "\nTelefone: " . $this->maskedPhone((string) $payload['phone'])
            . "\n\nPara cadastrar, envie: *CONFIRMAR {$confirmation}*"
        );
        return $merchant['userId'];
    }

    /** @param array<string,mixed> $payload @param array{userId:int,storeId:int,storeName:string,userName:string} $merchant */
    private function handleVisitorConfirm(int $eventId, string $requestId, string $canonical, string $senderKey, string $choice, array $payload, array $merchant): int
    {
        if ($choice !== 'confirmar ' . ($payload['confirmation'] ?? '')) {
            $this->invalidChoice($canonical, $senderKey, $eventId, 'Confirmacao incorreta. Copie exatamente o codigo mostrado ou envie */cancelar*.');
            return $merchant['userId'];
        }
        $key = (string) ($payload['operationKey'] ?? '');
        $idempotency = new StoreIdempotencyService($this->db);
        $request = ['name' => (string) ($payload['name'] ?? ''), 'phone' => (string) ($payload['phone'] ?? '')];
        $attempt = $idempotency->begin('whatsapp_visitor', $merchant['storeId'], $merchant['userId'], $key, $request);
        try {
            if ($attempt['replayed']) {
                $customer = $attempt['data']['customer'] ?? null;
            } else {
                $created = (new StoreCustomerService($this->db))->createVisitor(
                    $merchant['storeId'],
                    $request['name'],
                    $request['phone']
                );
                $customer = $created['customer'] ?? null;
                $idempotency->complete('whatsapp_visitor', $merchant['storeId'], $key, ['customer' => $customer]);
            }
        } catch (Throwable $exception) {
            $idempotency->fail('whatsapp_visitor', $merchant['storeId'], $key);
            throw $exception;
        }
        if (!is_array($customer)) {
            throw new StoreApiException('Nao foi possivel carregar o visitante criado.', 500);
        }
        $this->store->audit($senderKey, 'visitor.create', 'success', $merchant['userId'], $merchant['storeId'], requestId: $requestId, actionKey: $key);
        $this->store->setState($senderKey, 'sale_amount', [
            'customerId' => (int) $customer['id'],
            'customerName' => (string) $customer['name'],
            'balanceCents' => (int) $customer['balanceCents'],
        ]);
        $this->reply($canonical, $senderKey, $eventId, 'visitor-created', "Visitante cadastrado com sucesso. ✅\n\nInforme agora o *valor total da compra*.\nExemplo: 100,00");
        return $merchant['userId'];
    }

    /** @param array<string,mixed> $payload @param array{userId:int,storeId:int,storeName:string,userName:string} $merchant */
    private function handleSaleAmount(int $eventId, string $canonical, string $senderKey, string $body, array $payload, array $merchant): int
    {
        $gross = $this->parseMoney($body);
        if ($gross === null || $gross <= 0) {
            $this->invalidChoice($canonical, $senderKey, $eventId, 'Valor invalido. Digite, por exemplo: *100,00*.');
            return $merchant['userId'];
        }
        $payload['grossAmountCents'] = $gross;
        $this->store->setState($senderKey, 'sale_balance', $payload);
        $this->reply(
            $canonical,
            $senderKey,
            $eventId,
            'sale-balance-prompt',
            'Saldo disponivel nesta loja: *' . $this->money((int) ($payload['balanceCents'] ?? 0))
            . "*\n\nQuanto do saldo sera utilizado?\nDigite *0* para nao usar saldo."
        );
        return $merchant['userId'];
    }

    /** @param array<string,mixed> $payload @param array{userId:int,storeId:int,storeName:string,userName:string} $merchant */
    private function handleSaleBalance(int $eventId, string $canonical, string $senderKey, string $body, array $payload, array $merchant): int
    {
        $balanceUsed = $this->parseMoney($body);
        if ($balanceUsed === null || $balanceUsed < 0) {
            $this->invalidChoice($canonical, $senderKey, $eventId, 'Valor de saldo invalido. Digite *0* ou um valor como *20,00*.');
            return $merchant['userId'];
        }
        $gross = (int) ($payload['grossAmountCents'] ?? 0);
        if ($balanceUsed > $gross || $balanceUsed > (int) ($payload['balanceCents'] ?? 0)) {
            $this->invalidChoice($canonical, $senderKey, $eventId, 'O saldo informado supera a compra ou o saldo disponivel nesta loja.');
            return $merchant['userId'];
        }
        $storeStatement = $this->db->prepare(
            'SELECT cashback_ativo,COALESCE(porcentagem_cliente,0) percentage FROM lojas WHERE id=:store LIMIT 1'
        );
        $storeStatement->execute([':store' => $merchant['storeId']]);
        $storeData = $storeStatement->fetch(PDO::FETCH_ASSOC) ?: [];
        $paid = $gross - $balanceUsed;
        $cashback = (int) ($storeData['cashback_ativo'] ?? 0) === 1
            ? StoreMoney::percentage($paid, (float) ($storeData['percentage'] ?? 0))
            : 0;
        $confirmation = (string) random_int(1000, 9999);
        $payload = [...$payload,
            'balanceUsedCents' => $balanceUsed,
            'cashbackPreviewCents' => $cashback,
            'confirmation' => $confirmation,
            'code' => 'WPP-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(3))),
            'operationKey' => 'wa-sale-' . bin2hex(random_bytes(16)),
        ];
        $this->store->setState($senderKey, 'sale_confirm', $payload);
        $this->reply(
            $canonical,
            $senderKey,
            $eventId,
            'sale-preview',
            "🧾 *Revise a venda*\n\nLoja: *" . $this->safeText($merchant['storeName']) . "*\nCliente: "
            . $this->maskedName((string) ($payload['customerName'] ?? 'Cliente'))
            . "\nValor da compra: " . $this->money($gross)
            . "\nSaldo utilizado: " . $this->money($balanceUsed)
            . "\nValor pago: " . $this->money($paid)
            . "\nCashback previsto: " . $this->money($cashback)
            . "\n\nPara registrar, envie: *CONFIRMAR {$confirmation}*"
        );
        return $merchant['userId'];
    }

    /** @param array<string,mixed> $payload @param array{userId:int,storeId:int,storeName:string,userName:string} $merchant */
    private function handleSaleConfirm(int $eventId, string $requestId, string $canonical, string $senderKey, string $choice, array $payload, array $merchant): int
    {
        if ($choice !== 'confirmar ' . ($payload['confirmation'] ?? '')) {
            $this->invalidChoice($canonical, $senderKey, $eventId, 'Confirmacao incorreta. Copie exatamente o codigo mostrado ou envie */cancelar*.');
            return $merchant['userId'];
        }
        $sale = (new StoreTransactionService($this->db))->create(
            $merchant['storeId'],
            $merchant['userId'],
            [
                'customerId' => (int) ($payload['customerId'] ?? 0),
                'grossAmountCents' => (int) ($payload['grossAmountCents'] ?? 0),
                'balanceUsedCents' => (int) ($payload['balanceUsedCents'] ?? 0),
                'code' => (string) ($payload['code'] ?? ''),
                'description' => 'Venda registrada pelo WhatsApp',
                'occurredAt' => date(DATE_ATOM),
            ],
            (string) ($payload['operationKey'] ?? '')
        );
        try {
            (new StoreWhatsAppNotificationService($this->db))->queueAndProcess((int) $sale['id'], $merchant['storeId']);
        } catch (Throwable $exception) {
            Logger::warning('waha.menu.sale_notification_failed', [
                'transaction_id' => (int) $sale['id'],
                'exception' => get_class($exception),
            ]);
        }
        $this->store->audit(
            $senderKey,
            'sale.create',
            !empty($sale['replayed']) ? 'replayed' : 'success',
            $merchant['userId'],
            $merchant['storeId'],
            (int) $sale['id'],
            $requestId,
            (string) ($payload['operationKey'] ?? '')
        );
        $this->store->setState($senderKey, 'merchant_menu');
        $this->reply(
            $canonical,
            $senderKey,
            $eventId,
            'sale-complete',
            "✅ *Venda aprovada!*\n\nCodigo: *" . $this->safeText((string) ($payload['code'] ?? ''))
            . "*\nValor: " . $this->money((int) $sale['grossAmountCents'])
            . "\nSaldo utilizado: " . $this->money((int) $sale['balanceUsedCents'])
            . "\nCashback creditado: " . $this->money((int) $sale['cashbackGrantedCents'])
            . "\nSaldo atual do cliente nesta loja: *" . $this->money((int) $sale['customerBalanceCents']) . "*\n\n"
            . $this->merchantMenu($merchant['storeName'])
        );
        return $merchant['userId'];
    }

    /** @param array<string,mixed> $conversation @return array{userId:int,storeId:int,storeName:string,userName:string}|null */
    private function merchant(array $conversation): ?array
    {
        $userId = (int) ($conversation['authenticated_user_id'] ?? 0);
        $storeId = (int) ($conversation['loja_id'] ?? 0);
        if ($userId <= 0 || $storeId <= 0) {
            return null;
        }
        if (empty($conversation['auth_idle_expires_at']) || strtotime((string) $conversation['auth_idle_expires_at']) < time()) {
            return null;
        }
        if (empty($conversation['auth_absolute_expires_at']) || strtotime((string) $conversation['auth_absolute_expires_at']) < time()) {
            return null;
        }
        $statement = $this->db->prepare(
            "SELECT u.id user_id,u.nome user_name,u.tipo,l.id store_id,l.nome_fantasia store_name "
            . 'FROM usuarios u JOIN lojas l ON l.id=:store AND l.status=\'aprovado\' '
            . "WHERE u.id=:user AND u.status='ativo' AND u.tipo IN ('loja','funcionario') "
            . "AND ((u.tipo='loja' AND l.usuario_id=u.id) OR (u.tipo='funcionario' AND u.loja_vinculada_id=l.id)) LIMIT 1"
        );
        $statement->execute([':store' => $storeId, ':user' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return [
            'userId' => (int) $row['user_id'],
            'storeId' => (int) $row['store_id'],
            'storeName' => (string) $row['store_name'],
            'userName' => (string) $row['user_name'],
        ];
    }

    /** @return array{id?:int,status:string} */
    private function matchUniqueClient(?string $canonical): array
    {
        if ($canonical === null) {
            return ['status' => 'not_found'];
        }
        $full = $this->phoneDigits($canonical);
        $national = str_starts_with($full, '55') ? substr($full, 2) : $full;
        $statement = $this->db->prepare(
            "SELECT id FROM usuarios WHERE tipo='cliente' AND status='ativo' AND telefone IS NOT NULL AND telefone<>'' "
            . "AND REGEXP_REPLACE(telefone,'[^0-9]','') IN (:full,:national) ORDER BY id LIMIT 2"
        );
        $statement->execute([':full' => $full, ':national' => $national]);
        $ids = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
        if (count($ids) > 1) {
            return ['status' => 'duplicate'];
        }
        return $ids === [] ? ['status' => 'not_found'] : ['status' => 'ready', 'id' => $ids[0]];
    }

    private function balanceMessage(int $userId): string
    {
        $statement = $this->db->prepare(
            'SELECT l.nome_fantasia,l.status,cs.saldo_disponivel FROM cashback_saldos cs '
            . 'JOIN lojas l ON l.id=cs.loja_id WHERE cs.usuario_id=:user '
            . 'ORDER BY (l.status=\'aprovado\') DESC,cs.saldo_disponivel DESC,l.nome_fantasia'
        );
        $statement->execute([':user' => $userId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            return "💰 *Seus saldos por loja*\n\nVoce ainda nao possui saldo registrado em nenhuma loja.\n\nCada saldo pertence exclusivamente a respectiva loja.";
        }
        $lines = ["💰 *Seus saldos por loja*", ''];
        foreach ($rows as $row) {
            $lines[] = '🏪 *' . $this->safeText((string) $row['nome_fantasia']) . '*';
            $lines[] = 'Disponivel: *' . $this->money(StoreMoney::toCents($row['saldo_disponivel'])) . '*';
            if ($row['status'] !== 'aprovado') {
                $lines[] = '_Uso temporariamente indisponivel_';
            }
            $lines[] = '';
        }
        $lines[] = 'Cada saldo so pode ser usado na respectiva loja. Os valores nao sao somados.';
        return implode("\n", $lines);
    }

    /** @return list<array{id:int,name:string,balanceCents:int}> */
    private function customersByPhone(string $canonical, int $storeId): array
    {
        $full = $this->phoneDigits($canonical);
        $national = str_starts_with($full, '55') ? substr($full, 2) : $full;
        $statement = $this->db->prepare(
            "SELECT u.id,u.nome,COALESCE(cs.saldo_disponivel,0) balance FROM usuarios u "
            . 'LEFT JOIN cashback_saldos cs ON cs.usuario_id=u.id AND cs.loja_id=:store '
            . "WHERE u.tipo='cliente' AND u.status='ativo' AND REGEXP_REPLACE(u.telefone,'[^0-9]','') IN (:full,:national) "
            . 'ORDER BY (u.loja_criadora_id=:priority) DESC,u.id LIMIT 2'
        );
        $statement->execute([':store' => $storeId, ':full' => $full, ':national' => $national, ':priority' => $storeId]);
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['nome'],
            'balanceCents' => StoreMoney::toCents($row['balance']),
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @param array<string,mixed> $event */
    private function handleOwnMessage(array $event, string $senderKey): void
    {
        $source = strtolower((string) ($event['payload']['source'] ?? ''));
        $providerId = $this->providerMessageId($event['payload'] ?? []);
        if ($source === 'api' || $this->store->isBotProviderMessage($providerId)) {
            return;
        }
        $this->store->close($senderKey, true);
        $this->store->audit($senderKey, 'conversation.human_takeover', 'success');
    }

    private function resetMerchant(int $eventId, string $canonical, string $senderKey, array $merchant): int
    {
        $this->store->setState($senderKey, 'merchant_menu');
        $this->reply($canonical, $senderKey, $eventId, 'merchant-reset', $this->merchantMenu($merchant['storeName']));
        return (int) $merchant['userId'];
    }

    private function invalidChoice(string $canonical, string $senderKey, int $eventId, string $message): void
    {
        $attempts = $this->store->incrementInvalid($senderKey);
        if ($attempts >= 5) {
            $this->reply($canonical, $senderKey, $eventId, 'blocked', 'Muitas tentativas invalidas. O menu foi pausado por 15 minutos.');
            return;
        }
        $this->reply($canonical, $senderKey, $eventId, 'invalid', "Opcao nao reconhecida.\n\n" . $message);
    }

    private function reply(string $canonical, string $senderKey, int $eventId, string $kind, string $message): void
    {
        $actionKey = 'menu:' . $eventId . ':' . $kind;
        $status = $this->store->botMessageStatus($actionKey);
        if (in_array($status, ['sent', 'delivery_unknown'], true)) {
            return;
        }
        if ($status === null) {
            $this->store->beginBotMessage($actionKey, $eventId, $senderKey, $canonical, $message);
        } else {
            $this->store->finishBotMessage($actionKey, 'pending', null, null);
        }
        try {
            $response = $this->waha->sendText($this->phoneDigits($canonical), $message);
            $this->store->finishBotMessage($actionKey, 'sent', $this->providerMessageId($response), null);
        } catch (WahaException $exception) {
            $status = $exception->deliveryUnknown ? 'delivery_unknown' : ($exception->transient ? 'pending' : 'failed');
            $this->store->finishBotMessage($actionKey, $status, null, $exception->transient ? 'provider_unavailable' : 'provider_rejected');
        }
    }

    private function mainMenu(): string
    {
        return "💚 *Klube Cash*\n\nOla! Como podemos ajudar?\n\n1️⃣ Consultar meus saldos\n2️⃣ Encontrar lojas parceiras\n3️⃣ Acesso do lojista\n0️⃣ Encerrar menu";
    }

    private function merchantMenu(string $storeName): string
    {
        return "🔐 *Area do lojista — " . $this->safeText($storeName) . "*\n\n1️⃣ Registrar venda\n2️⃣ Consultar cliente nesta loja\n3️⃣ Ultimas vendas registradas\n4️⃣ Encerrar acesso";
    }

    /** @param array<string,mixed> $event */
    private function canonicalFromEvent(array $event): ?string
    {
        $chatId = $this->chatId($event, false);
        if ($chatId === null || $this->isNonPrivateChat($chatId)) {
            return null;
        }
        return $this->canonicalPhone($event, $chatId, false);
    }

    /** @param array<string,mixed> $event */
    private function canonicalPhone(array $event, string $chatId, bool $fromMe): ?string
    {
        $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
        $info = is_array($payload['_data']['Info'] ?? null) ? $payload['_data']['Info'] : [];
        $alternatives = $fromMe
            ? [$info['RecipientAlt'] ?? null]
            : [$info['SenderAlt'] ?? null];

        foreach ($alternatives as $alternative) {
            if (!is_scalar($alternative) || trim((string) $alternative) === '') {
                continue;
            }
            try {
                return WahaService::normalizePhone(preg_replace('/@.+$/', '', trim((string) $alternative)) ?? '');
            } catch (Throwable) {
                // Continua para a resolucao oficial do LID na WAHA.
            }
        }

        return $this->waha->resolveSenderPhone($chatId);
    }

    /** @param array<string,mixed> $event */
    private function chatId(array $event, bool $fromMe): ?string
    {
        $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
        $candidates = [
            $payload['chatId'] ?? null,
            $payload['key']['remoteJid'] ?? null,
            $fromMe ? ($payload['to'] ?? null) : ($payload['from'] ?? null),
        ];
        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                return trim((string) $candidate);
            }
        }
        return null;
    }

    private function isNonPrivateChat(string $chatId): bool
    {
        return str_ends_with($chatId, '@g.us')
            || str_ends_with($chatId, '@newsletter')
            || str_contains($chatId, 'status@broadcast')
            || (!str_ends_with($chatId, '@c.us') && !str_ends_with($chatId, '@lid'));
    }

    /** @param array<string,mixed> $event */
    private function isTextMessage(array $event): bool
    {
        $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
        $type = strtolower((string) ($payload['type'] ?? 'chat'));
        return in_array($type, ['', 'chat', 'text'], true) && isset($payload['body']);
    }

    private function normalizeCommand(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value), 'UTF-8');
    }

    private function canonicalInputPhone(string $value): ?string
    {
        try {
            return WahaService::normalizePhone($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function parseMoney(string $value): ?int
    {
        $value = trim(str_ireplace(['R$', ' '], '', $value));
        if ($value === '0' || $value === '0,00' || $value === '0.00') {
            return 0;
        }
        if (preg_match('/^\d{1,7}(?:[.,]\d{1,2})?$/', $value) !== 1) {
            return null;
        }
        $normalized = str_replace(',', '.', $value);
        return (int) round((float) $normalized * 100);
    }

    private function money(int $cents): string
    {
        return 'R$ ' . number_format($cents / 100, 2, ',', '.');
    }

    private function formatPercentage(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',') . '%';
    }

    private function safeText(string $value): string
    {
        return trim(str_replace(['*', '_', '~', '`'], '', preg_replace('/[\r\n\t]+/u', ' ', $value) ?? $value));
    }

    private function maskedName(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        if ($parts === []) {
            return 'Cliente';
        }
        $first = array_shift($parts);
        $initials = array_map(static fn (string $part): string => mb_substr($part, 0, 1, 'UTF-8') . '.', $parts);
        return trim($first . ' ' . implode(' ', $initials));
    }

    private function maskedPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        return strlen($digits) >= 4 ? '(**) *****-' . substr($digits, -4) : 'telefone protegido';
    }

    private function phoneDigits(string $canonical): string
    {
        return preg_replace('/\D+/', '', preg_replace('/@.+$/', '', $canonical) ?? '') ?? '';
    }

    private function nationalDigits(string $canonical): string
    {
        $digits = $this->phoneDigits($canonical);
        return str_starts_with($digits, '55') ? substr($digits, 2) : $digits;
    }

    /** @param array<string,mixed> $payload */
    private function providerMessageId(array $payload): ?string
    {
        $candidates = [$payload['id'] ?? null, $payload['key']['id'] ?? null, $payload['_data']['id']['_serialized'] ?? null];
        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                return substr(trim((string) $candidate), 0, 191);
            }
        }
        return null;
    }
}
