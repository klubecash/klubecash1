<?php
/**
 * Configuracao da integracao com WhatsApp via WPPConnect.
 *
 * A integracao fica desativada por padrao. Quando habilitada, a URL HTTPS e
 * o token devem vir exclusivamente de variaveis de ambiente.
 */

$whatsappEnabledValue = getenv('WHATSAPP_ENABLED');
$whatsappRequested = false;

if ($whatsappEnabledValue !== false && trim((string) $whatsappEnabledValue) !== '') {
    $parsedEnabled = filter_var(
        $whatsappEnabledValue,
        FILTER_VALIDATE_BOOLEAN,
        FILTER_NULL_ON_FAILURE
    );

    if ($parsedEnabled === null) {
        error_log('WHATSAPP_ENABLED invalido; integracao mantida desativada.');
    } else {
        $whatsappRequested = $parsedEnabled;
    }
}

$whatsappBaseUrl = rtrim(trim((string) (getenv('WHATSAPP_BASE_URL') ?: '')), '/');
$whatsappApiToken = trim((string) (getenv('WHATSAPP_TOKEN') ?: ''));
$whatsappUrlParts = $whatsappBaseUrl !== '' ? parse_url($whatsappBaseUrl) : false;
$whatsappHasSecureUrl = is_array($whatsappUrlParts)
    && strtolower((string) ($whatsappUrlParts['scheme'] ?? '')) === 'https'
    && !empty($whatsappUrlParts['host']);
$whatsappEnabled = $whatsappRequested
    && $whatsappHasSecureUrl
    && $whatsappApiToken !== '';

if ($whatsappRequested && !$whatsappEnabled) {
    error_log(
        'Integracao WhatsApp desativada: configure WHATSAPP_BASE_URL com HTTPS e WHATSAPP_TOKEN.'
    );
}

$whatsappSessionName = trim((string) (getenv('WHATSAPP_SESSION') ?: 'default'));
$whatsappLogPath = trim((string) (getenv('WHATSAPP_LOG_PATH') ?: ''));
if (in_array(strtolower($whatsappLogPath), ['stderr', 'php://stderr'], true)) {
    $whatsappLogPath = '';
}

$whatsappTempRoot = rtrim(sys_get_temp_dir(), '/\\')
    . DIRECTORY_SEPARATOR
    . 'klubecash';
$whatsappMediaDir = trim((string) (getenv('WHATSAPP_MEDIA_DIR') ?: ''));
if ($whatsappMediaDir === '') {
    $whatsappMediaDir = $whatsappTempRoot . DIRECTORY_SEPARATOR . 'whatsapp';
}

define('WHATSAPP_ENABLED', $whatsappEnabled);
define('WHATSAPP_BASE_URL', $whatsappHasSecureUrl ? $whatsappBaseUrl : '');
define('WHATSAPP_SESSION_NAME', $whatsappSessionName !== '' ? $whatsappSessionName : 'default');
define('WHATSAPP_API_TOKEN', $whatsappApiToken);
define('WHATSAPP_HTTP_TIMEOUT', max(1, (int) (getenv('WHATSAPP_HTTP_TIMEOUT') ?: 20)));
define('WHATSAPP_CONNECT_RETRIES', max(1, (int) (getenv('WHATSAPP_CONNECT_RETRIES') ?: 1)));
define('WHATSAPP_ACK_TIMEOUT', max(1, (int) (getenv('WHATSAPP_ACK_TIMEOUT') ?: 10)));
define('WHATSAPP_LOG_PATH', $whatsappLogPath);
define('WHATSAPP_MEDIA_DIR', $whatsappMediaDir);
define('WHATSAPP_TEMPLATE_LANGUAGE', getenv('WHATSAPP_TEMPLATE_LANGUAGE') ?: 'pt_BR');

define(
    'WHATSAPP_DEFAULT_FALLBACK_MESSAGE',
    'Nao foi possivel completar o envio pelo WhatsApp. Tente novamente mais tarde.'
);
