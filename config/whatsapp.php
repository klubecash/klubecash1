<?php
/**
 * Configurações de integração com WhatsApp via WPPConnect.
 * Ajuste os valores conforme o ambiente local ou produção.
 */

$whatsappEnabled = getenv('WHATSAPP_ENABLED');
if ($whatsappEnabled === false) {
    $whatsappEnabled = true; // habilitado por padrão quando o arquivo é incluído
}

define('WHATSAPP_ENABLED', filter_var($whatsappEnabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool)$whatsappEnabled);
define('WHATSAPP_BASE_URL', rtrim(getenv('WHATSAPP_BASE_URL') ?: 'http://localhost:21465', '/'));
define('WHATSAPP_SESSION_NAME', getenv('WHATSAPP_SESSION') ?: 'mySession');
define('WHATSAPP_API_TOKEN', getenv('WHATSAPP_TOKEN') ?: '');
define('WHATSAPP_HTTP_TIMEOUT', (int)(getenv('WHATSAPP_HTTP_TIMEOUT') ?: 20));
define('WHATSAPP_CONNECT_RETRIES', (int)(getenv('WHATSAPP_CONNECT_RETRIES') ?: 1));
define('WHATSAPP_ACK_TIMEOUT', (int)(getenv('WHATSAPP_ACK_TIMEOUT') ?: 10));
define('WHATSAPP_LOG_PATH', getenv('WHATSAPP_LOG_PATH') ?: dirname(__DIR__) . '/logs/whatsapp.log');

define('WHATSAPP_MEDIA_DIR', getenv('WHATSAPP_MEDIA_DIR') ?: dirname(__DIR__) . '/uploads/whatsapp');
define('WHATSAPP_TEMPLATE_LANGUAGE', getenv('WHATSAPP_TEMPLATE_LANGUAGE') ?: 'pt_BR');

define('WHATSAPP_DEFAULT_FALLBACK_MESSAGE', 'Não foi possível completar o envio pelo WhatsApp. Tente novamente mais tarde.');
?>