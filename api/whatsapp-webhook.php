<?php
// api/whatsapp-webhook.php
// Recebe eventos do WPPConnect e responde a consultas de saldo via WhatsApp

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../models/CashbackBalance.php';
require_once __DIR__ . '/../utils/WhatsAppBot.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['status' => true, 'message' => 'OK']);
    exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'GET'], true)) {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Metodo nao suportado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['status' => true, 'message' => 'WhatsApp webhook ativo']);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode([
        'status' => false,
        'message' => 'Payload invalido',
        'raw' => $rawBody !== '' ? substr($rawBody, 0, 500) : null
    ]);
    exit;
}

try {
    $db = Database::getConnection();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Falha ao conectar no banco']);
    exit;
}

$messages = extractMessages($payload);
$handled = [];
$skipped = 0;
$processedContacts = [];

foreach ($messages as $message) {
    if (!is_array($message)) {
        $skipped++;
        continue;
    }

    if (!empty($message['fromMe']) || !empty($message['self'])) {
        $skipped++;
        continue;
    }

    if (isGroupMessage($message)) {
        $skipped++;
        continue;
    }

    $phoneDigits = extractPhoneFromMessage($message);
    if ($phoneDigits === null) {
        $skipped++;
        continue;
    }

    if (isset($processedContacts[$phoneDigits])) {
        $skipped++;
        continue;
    }

    $body = extractMessageBody($message);
    if ($body === '') {
        $skipped++;
        continue;
    }

    $normalized = normalizeCommand($body);
    if ($normalized === '' || !isBalanceCommand($normalized)) {
        $skipped++;
        continue;
    }

    $handled[] = handleBalanceInquiry($db, $phoneDigits, $body, $normalized, $message);
    $processedContacts[$phoneDigits] = true;
}

$response = [
    'status' => true,
    'handled' => $handled,
    'skipped' => $skipped,
    'total_messages' => count($messages)
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;

function extractMessages(array $payload): array
{
    if (isset($payload['messages']) && is_array($payload['messages'])) {
        return $payload['messages'];
    }

    if (isset($payload['data']['messages']) && is_array($payload['data']['messages'])) {
        return $payload['data']['messages'];
    }

    if (isset($payload['body']['messages']) && is_array($payload['body']['messages'])) {
        return $payload['body']['messages'];
    }

    return [];
}

function extractMessageBody(array $message): string
{
    $candidates = [];

    if (isset($message['body']) && is_string($message['body'])) {
        $candidates[] = $message['body'];
    }

    if (isset($message['text']['body']) && is_string($message['text']['body'])) {
        $candidates[] = $message['text']['body'];
    }

    if (isset($message['selectedDisplayText']) && is_string($message['selectedDisplayText'])) {
        $candidates[] = $message['selectedDisplayText'];
    }

    if (isset($message['selectedButtonId']) && is_string($message['selectedButtonId'])) {
        $candidates[] = $message['selectedButtonId'];
    }

    if (isset($message['interactiveResponseMessage']['body']['text']) && is_string($message['interactiveResponseMessage']['body']['text'])) {
        $candidates[] = $message['interactiveResponseMessage']['body']['text'];
    }

    foreach ($candidates as $value) {
        $trimmed = trim($value);
        if ($trimmed !== '') {
            return $trimmed;
        }
    }

    return '';
}

function extractPhoneFromMessage(array $message): ?string
{
    $raw = $message['from'] ?? $message['chatId'] ?? '';
    if (!is_string($raw) || $raw === '') {
        return null;
    }

    $digits = preg_replace('/\D+/', '', $raw);
    if ($digits === '' || strlen($digits) < 10) {
        return null;
    }

    return $digits;
}

function normalizeCommand(string $text): string
{
    $normalized = trim($text);
    if ($normalized === '') {
        return '';
    }

    $normalized = trim($normalized, " \t\n\r\0\x0B.,!?");
    if ($normalized === '') {
        return '';
    }

    if (function_exists('mb_strtolower')) {
        $normalized = mb_strtolower($normalized, 'UTF-8');
    } else {
        $normalized = strtolower($normalized);
    }

    $normalized = preg_replace('/\s+/', ' ', $normalized);

    return $normalized ?? '';
}

function isBalanceCommand(string $command): bool
{
    if (preg_match('/^(#|\*)?(meu\s+)?saldo(\s+total)?$/', $command)) {
        return true;
    }

    if (preg_match('/^(consultar|ver|mostrar)\s+saldo$/', $command)) {
        return true;
    }

    if (in_array($command, ['giftback', 'consultar giftback', 'ver giftback'], true)) {
        return true;
    }

    return false;
}

function isGroupMessage(array $message): bool
{
    if (!empty($message['isGroupMsg'])) {
        return true;
    }

    $chatId = $message['chatId'] ?? $message['from'] ?? '';
    if (is_string($chatId) && stripos($chatId, '@g.us') !== false) {
        return true;
    }

    return false;
}

function handleBalanceInquiry(PDO $db, string $phoneDigits, string $body, string $normalizedCommand, array $message): array
{
    $result = [
        'phone' => $phoneDigits,
        'command' => $normalizedCommand,
        'status' => 'ignored'
    ];

    try {
        $user = findActiveClientByPhone($db, $phoneDigits);
        $balanceModel = new CashbackBalance();

        if ($user) {
            $storeBalances = $balanceModel->getAllUserBalances((int)$user['id']);
            $totalBalance = $balanceModel->getTotalBalance((int)$user['id']);

            $storesFormatted = [];
            foreach ($storeBalances as $store) {
                $storesFormatted[] = [
                    'nome' => $store['nome_fantasia'] ?? ($store['loja'] ?? 'Loja parceira'),
                    'saldo' => (float)($store['saldo_disponivel'] ?? 0)
                ];
            }

            $summary = [
                'nome' => $user['nome'] ?? 'Cliente',
                'total' => (float)$totalBalance,
                'lojas' => $storesFormatted
            ];

            WhatsAppBot::sendBalanceSummary($phoneDigits, $summary);

            logBotConsulta($db, $phoneDigits, true, [
                'usuario_id' => (int)$user['id'],
                'saldo_total' => (float)$totalBalance,
                'lojas' => $storesFormatted,
                'mensagem' => $body,
                'message_id' => $message['id'] ?? null
            ]);

            $result['status'] = 'sent';
            $result['user_id'] = (int)$user['id'];
            $result['lojas'] = count($storesFormatted);
        } else {
            $reply = implode("\n", [
                'Nao encontramos um cadastro ativo para este telefone.',
                'Verifique se o numero esta atualizado na sua conta Klube Cash.',
                'Se precisar de ajuda fale com a loja parceira ou com o suporte Klube Cash.'
            ]);

            WhatsAppBot::sendTextMessage($phoneDigits, $reply, ['tag' => 'balance:not_found']);

            logBotConsulta($db, $phoneDigits, false, [
                'mensagem' => $body,
                'message_id' => $message['id'] ?? null
            ]);

            $result['status'] = 'not_found';
        }
    } catch (Throwable $e) {
        error_log('[WhatsAppWebhook] Falha ao processar saldo: ' . $e->getMessage());
        $result['status'] = 'error';
        $result['error'] = $e->getMessage();
    }

    return $result;
}

function findActiveClientByPhone(PDO $db, string $phoneDigits): ?array
{
    $variants = buildPhoneVariants($phoneDigits);
    if ($variants === []) {
        return null;
    }

    $conditions = [];
    $params = [
        ':tipo' => USER_TYPE_CLIENT,
        ':status' => USER_ACTIVE
    ];

    $index = 0;
    foreach ($variants as $variant) {
        $index++;
        $param = ':phone' . $index;
        $conditions[] = "REPLACE(REPLACE(REPLACE(REPLACE(telefone, '+', ''), '-', ''), ' ', ''), '(', '') = {$param}";
        $params[$param] = $variant;
    }

    $sql = "SELECT id, nome, telefone FROM usuarios WHERE tipo = :tipo AND status = :status AND (" . implode(' OR ', $conditions) . ") ORDER BY ultimo_login DESC LIMIT 1";

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function buildPhoneVariants(string $digits): array
{
    $variants = [];
    $digits = preg_replace('/\D+/', '', $digits);
    if ($digits === '') {
        return [];
    }

    $variants[] = $digits;

    if (strlen($digits) > 2 && strpos($digits, '55') === 0) {
        $variants[] = substr($digits, 2);
    } else {
        $variants[] = '55' . $digits;
    }

    if (strlen($digits) > 11) {
        $variants[] = substr($digits, -11);
    }

    if (strlen($digits) > 10) {
        $variants[] = substr($digits, -10);
    }

    $variants = array_filter(array_unique($variants), static function ($value) {
        return is_string($value) && strlen($value) >= 10;
    });

    return array_values($variants);
}

function logBotConsulta(PDO $db, string $phoneDigits, bool $found, array $extra = []): void
{
    try {
        $stmt = $db->prepare('INSERT INTO bot_consultas (telefone, tipo_consulta, usuario_encontrado, dados_capturados) VALUES (:telefone, :tipo, :encontrado, :dados)');
        $stmt->bindValue(':telefone', $phoneDigits);
        $stmt->bindValue(':tipo', 'consulta_saldo');
        $stmt->bindValue(':encontrado', $found ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':dados', $extra !== [] ? json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null);
        $stmt->execute();
    } catch (Throwable $e) {
        error_log('[WhatsAppWebhook] Falha ao registrar consulta: ' . $e->getMessage());
    }
}


