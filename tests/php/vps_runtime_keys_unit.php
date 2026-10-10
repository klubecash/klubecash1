<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/services/Security/VpsRuntimeKeys.php';
require dirname(__DIR__, 2) . '/services/WhatsApp/WhatsAppMenuConfig.php';

use App\Services\Security\VpsRuntimeKeys;
use App\Services\WhatsApp\WhatsAppMenuConfig;

function assertKey(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

putenv('VPS_DERIVE_RUNTIME_KEYS=true');
putenv('JWT_SECRET=' . str_repeat('j', 64));
putenv('CRON_SECRET=' . str_repeat('p', 40));
putenv('WHATSAPP_MENU_HASH_KEY=' . str_repeat('p', 40));
$cron = VpsRuntimeKeys::cronSecret();
$menu = VpsRuntimeKeys::whatsAppMenuHashKey();
assertKey(strlen($cron) === 64 && strlen($menu) === 64, 'Chaves derivadas invalidas.');
assertKey($cron !== $menu && $cron !== str_repeat('p', 40), 'Chaves sem separacao de finalidade.');
assertKey(hash_equals($cron, VpsRuntimeKeys::cronSecret()), 'Derivacao instavel.');
putenv('WHATSAPP_MENU_ENABLED=true');
assertKey(WhatsAppMenuConfig::fromEnvironment()->hashKey === $menu, 'Menu nao usou a chave isolada da VPS.');

putenv('JWT_SECRET=curto');
assertKey(VpsRuntimeKeys::cronSecret() === '' && VpsRuntimeKeys::whatsAppMenuHashKey() === '', 'Segredo fraco aceito.');
try {
    WhatsAppMenuConfig::fromEnvironment();
    throw new RuntimeException('Menu aceitou segredo raiz curto.');
} catch (RuntimeException $error) {
    assertKey($error->getMessage() !== 'Menu aceitou segredo raiz curto.', 'Menu aceitou segredo raiz curto.');
}

putenv('VPS_DERIVE_RUNTIME_KEYS=false');
assertKey(VpsRuntimeKeys::cronSecret() === str_repeat('p', 40), 'Compatibilidade antiga do cron quebrada.');
assertKey(VpsRuntimeKeys::whatsAppMenuHashKey() === str_repeat('p', 40), 'Compatibilidade antiga do WhatsApp quebrada.');

echo "OK: chaves internas da VPS separadas e compatibilidade preservada.\n";
