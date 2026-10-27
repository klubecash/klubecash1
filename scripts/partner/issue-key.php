<?php
declare(strict_types=1);

// Emite uma chave de parceiro. A chave aparece uma única vez; guarde-a direto no
// ambiente do parceiro (nunca em chat, log ou repositório).
//   php scripts/partner/issue-key.php --partner="Klube Pass" \
//     --permissions=pass:balance,pass:debit,pass:credit,pass:reverse \
//     --daily-requests=20000 --daily-debit-cents=5000000 --daily-credit-cents=20000000 [--expires=2027-12-31] [--by="Seu nome"]
// Revogar: UPDATE partner_api_keys SET is_active=0,revoked_at=NOW() WHERE public_id='...'
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/Partner/PartnerKeyService.php';

$options = getopt('', ['partner:', 'permissions:', 'daily-requests:', 'daily-debit-cents:', 'daily-credit-cents:', 'expires:', 'by:']);
try {
    foreach (['partner', 'permissions', 'daily-debit-cents', 'daily-credit-cents'] as $required) {
        if (!isset($options[$required]) || $options[$required] === '') { throw new InvalidArgumentException("Informe --{$required}."); }
    }
    $expires = $options['expires'] ?? null;
    if ($expires !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $expires) !== 1) { throw new InvalidArgumentException('--expires deve ser AAAA-MM-DD.'); }
    $issued = \App\Services\Partner\PartnerKeyService::fromEnvironment(Database::getConnection())->issue(
        (string) $options['partner'],
        explode(',', (string) $options['permissions']),
        (int) ($options['daily-requests'] ?? 20000),
        (int) $options['daily-debit-cents'],
        (int) $options['daily-credit-cents'],
        $expires !== null ? $expires . ' 23:59:59' : null,
        isset($options['by']) ? (string) $options['by'] : null
    );
    fwrite(STDERR, "Chave criada (id público {$issued['publicId']}). Ela não será mostrada de novo:\n");
    echo $issued['key'], PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'partner.key.failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
