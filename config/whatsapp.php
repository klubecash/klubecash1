<?php
declare(strict_types=1);
/** Compatibilidade dos gatilhos legados; segredos permanecem no ambiente. */
$wahaConfigured = trim((string) getenv('WAHA_BASE_URL')) !== ''
    && trim((string) getenv('WAHA_API_KEY')) !== ''
    && trim((string) getenv('WAHA_SESSION')) !== ''
    && trim((string) getenv('WAHA_WEBHOOK_HMAC_KEY')) !== '';
defined('WHATSAPP_ENABLED') || define('WHATSAPP_ENABLED', $wahaConfigured);
