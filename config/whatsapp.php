<?php
/**
 * Configura��es de integra��o com WhatsApp via WPPConnect.
 * Ajuste os valores conforme o ambiente local ou produ��o.
 */

$whatsappEnabled = getenv('WHATSAPP_ENABLED');
if ($whatsappEnabled === false) {
    $whatsappEnabled = true; // habilitado por padr�o quando o arquivo � inclu�do
}

define('WHATSAPP_ENABLED', true);
define('WHATSAPP_BASE_URL', rtrim(getenv('WHATSAPP_BASE_URL') ?: 'http://klubezap.klubecash.com:8080', '/'));
define('WHATSAPP_SESSION_NAME', getenv('WHATSAPP_SESSION') ?: 'mySession');
define('WHATSAPP_API_TOKEN', getenv('WHATSAPP_TOKEN') ?: '$2b$10$rsFvwXQLcTF4HrybqB0SOegy12CEfxPRgANclGhBWCWWbZTxMbf3m');
define('WHATSAPP_HTTP_TIMEOUT', (int)(getenv('WHATSAPP_HTTP_TIMEOUT') ?: 20));
define('WHATSAPP_CONNECT_RETRIES', (int)(getenv('WHATSAPP_CONNECT_RETRIES') ?: 1));
define('WHATSAPP_ACK_TIMEOUT', (int)(getenv('WHATSAPP_ACK_TIMEOUT') ?: 10));
define('WHATSAPP_LOG_PATH', getenv('WHATSAPP_LOG_PATH') ?: dirname(__DIR__) . '/logs/whatsapp.log');

define('WHATSAPP_MEDIA_DIR', getenv('WHATSAPP_MEDIA_DIR') ?: dirname(__DIR__) . '/uploads/whatsapp');
define('WHATSAPP_TEMPLATE_LANGUAGE', getenv('WHATSAPP_TEMPLATE_LANGUAGE') ?: 'pt_BR');

define('WHATSAPP_DEFAULT_FALLBACK_MESSAGE', 'N�o foi poss�vel completar o envio pelo WhatsApp. Tente novamente mais tarde.');
?>




