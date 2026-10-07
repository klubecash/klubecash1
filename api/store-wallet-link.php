<?php

declare(strict_types=1);

use App\Services\StoreWallet\StoreWalletError;
use App\Services\StoreWallet\StoreWalletService;

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Vary: Cookie');
require_once __DIR__ . '/../utils/Security.php';
require_once __DIR__ . '/../services/StoreWallet/StoreWalletService.php';

function walletLinkResponse(int $status, mixed $data = null, string $message = ''): never
{
    http_response_code($status);
    echo json_encode(['status' => $status < 400 ? 'success' : 'error', 'data' => $data, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $token = strtolower(trim((string) ($_GET['token'] ?? '')));
    $action = trim((string) ($_GET['action'] ?? 'context'));
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $wallet = new StoreWalletService(Database::getConnection());
    if ($method === 'GET' && $action === 'context') { walletLinkResponse(200, $wallet->context($token) + ['csrfToken' => Security::generateCSRFToken()]); }
    if ($method === 'GET' && $action === 'wallet') { walletLinkResponse(200, $wallet->wallet($token)); }
    if ($method !== 'POST') { walletLinkResponse(405, null, 'Método não permitido.'); }
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) { walletLinkResponse(400, null, 'Dados inválidos.'); }
    $csrf = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrfToken'] ?? '');
    if (!Security::validateCSRFToken($csrf)) { walletLinkResponse(419, null, 'Atualize a página e tente novamente.'); }
    if ($action === 'login') { walletLinkResponse(200, $wallet->login($token, (string) ($input['email'] ?? ''), (string) ($input['password'] ?? ''))); }
    if ($action === 'request-code') { walletLinkResponse(200, $wallet->requestCode($token, (string) ($input['phone'] ?? ''), (string) ($input['purpose'] ?? ''))); }
    if ($action === 'verify-code') { walletLinkResponse(200, $wallet->verifyCode($token, (string) ($input['phone'] ?? ''), (string) ($input['purpose'] ?? ''), (string) ($input['code'] ?? ''), (string) ($input['name'] ?? ''))); }
    if ($action === 'signup') { walletLinkResponse(200, $wallet->signup($token, $input)); }
    if ($action === 'claim') { walletLinkResponse(200, $wallet->claim($token)); }
    walletLinkResponse(404, null, 'Ação não encontrada.');
} catch (StoreWalletError $e) {
    walletLinkResponse($e->httpStatus, null, $e->getMessage());
} catch (Throwable $e) {
    error_log('store_wallet_link: ' . $e->getMessage());
    walletLinkResponse(503, null, 'Consulta temporariamente indisponível.');
}
