<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Vary: Cookie');

require_once dirname(__DIR__) . '/session-guardian.php';

/**
 * Retorna a primeira letra usada nos avatares e placeholders da homepage.
 */
function homepageInitial(string $name): string
{
    if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
        return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
    }

    return strtoupper(substr($name, 0, 1));
}

function homepageColorFromName(string $name): string
{
    $colors = [
        '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FECA57',
        '#FF9FF3', '#54A0FF', '#5F27CD', '#FF3838', '#00D2D3',
        '#FF6348', '#7bed9f', '#70a1ff', '#dda0dd', '#ffb142',
        '#ff7675', '#74b9ff', '#0984e3', '#00b894', '#fdcb6e',
    ];

    $index = abs((int) crc32($name)) % count($colors);
    return $colors[$index];
}

function homepageAdjustBrightness(string $hex, float $percent): string
{
    $hex = ltrim($hex, '#');
    $red = hexdec(substr($hex, 0, 2));
    $green = hexdec(substr($hex, 2, 2));
    $blue = hexdec(substr($hex, 4, 2));

    $red = max(0, min(255, $red + ($red * $percent / 100)));
    $green = max(0, min(255, $green + ($green * $percent / 100)));
    $blue = max(0, min(255, $blue + ($blue * $percent / 100)));

    return sprintf('#%02x%02x%02x', $red, $green, $blue);
}

function homepageStorePresentation(array $store): array
{
    $name = (string) ($store['nome_fantasia'] ?? '');
    $logoUrl = null;
    $logoFilename = (string) ($store['logo'] ?? '');

    if (
        $logoFilename !== ''
        && preg_match('/^[a-zA-Z0-9_.-]+\.(jpg|jpeg|png|gif)$/i', $logoFilename) === 1
    ) {
        $candidatePath = '/uploads/store_logos/' . $logoFilename;
        if (is_file(dirname(__DIR__) . $candidatePath)) {
            $logoUrl = $candidatePath;
        }
    }

    $startColor = homepageColorFromName($name);

    return [
        'name' => $name,
        'category' => ($store['categoria'] ?? '') !== ''
            ? (string) $store['categoria']
            : null,
        'logoUrl' => $logoUrl,
        'fallback' => [
            'initial' => homepageInitial($name),
            'startColor' => $startColor,
            'endColor' => homepageAdjustBrightness($startColor, -20),
        ],
    ];
}

$authenticated = isset($_SESSION['user_id']);
$userType = $authenticated ? (string) ($_SESSION['user_type'] ?? '') : '';
$userName = $authenticated ? (string) ($_SESSION['user_name'] ?? '') : '';
$database = null;
$dashboardUrl = $authenticated
    ? match ($userType) {
        'admin' => ADMIN_DASHBOARD_URL,
        'cliente' => CLIENT_DASHBOARD_URL,
        'loja', 'funcionario' => STORE_DASHBOARD_URL,
        default => '',
    }
    : '';

$employeeSubtype = $userType === 'funcionario'
    ? (string) ($_SESSION['employee_subtype'] ?? '')
    : '';
$employeeSubtypeLabels = [
    'gerente' => 'Gerente',
    'financeiro' => 'Financeiro',
    'vendedor' => 'Vendedor',
];

$partnerStores = [];

try {
    $database ??= Database::getConnection();
    $statement = $database->query(
        "SELECT nome_fantasia, logo, categoria, descricao, porcentagem_cashback
         FROM lojas
         WHERE status = 'aprovado'
         ORDER BY RAND()
         LIMIT 12"
    );
    $partnerStores = array_map(
        'homepageStorePresentation',
        $statement->fetchAll(PDO::FETCH_ASSOC)
    );
} catch (Throwable $error) {
    error_log('Homepage context: erro ao buscar lojas parceiras: ' . $error->getMessage());
}

$user = null;
if ($authenticated) {
    $user = [
        'name' => $userName,
        'type' => $userType,
        'avatarInitial' => homepageInitial($userName),
        'employeeSubtype' => $employeeSubtype !== '' ? $employeeSubtype : null,
        'employeeSubtypeLabel' => $employeeSubtype !== ''
            ? ($employeeSubtypeLabels[$employeeSubtype] ?? 'Funcionário')
            : null,
        'dashboardUrl' => $dashboardUrl,
        'dashboardLabel' => $userType === 'funcionario'
            ? 'Acessar Painel da Loja'
            : 'Acessar Minha Conta',
    ];
}

echo json_encode([
    'authenticated' => $authenticated,
    'user' => $user,
    'partnerStores' => $partnerStores,
    'links' => [
        'login' => LOGIN_URL,
        'register' => REGISTER_URL,
        'storeRegister' => STORE_REGISTER_URL,
        'logout' => LOGOUT_URL,
    ],
    'currentYear' => (int) date('Y'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
