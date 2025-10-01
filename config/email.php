<?php
// config/email.php - APENAS CONFIGURAÇÕES (SEM CLASSE)
/**
 * Configuração de envio de emails
 * Klube Cash - Sistema de Cashback
 */

// Configurações SMTP - Apenas definições de constantes
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', 'smtp.hostinger.com');
}
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', 587); // CORRIGIDO: 587 ao invés de 465
}
if (!defined('SMTP_USERNAME')) {
    define('SMTP_USERNAME', 'klubecash@klubecash.com');
}
if (!defined('SMTP_PASSWORD')) {
    define('SMTP_PASSWORD', 'Aaku_2004@');
}
if (!defined('SMTP_FROM_EMAIL')) {
    define('SMTP_FROM_EMAIL', 'klubecash@klubecash.com'); // CORRIGIDO: klubecash@ ao invés de noreply@
}
if (!defined('SMTP_FROM_NAME')) {
    define('SMTP_FROM_NAME', 'Klube Cash');
}
if (!defined('SMTP_ENCRYPTION')) {
    define('SMTP_ENCRYPTION', 'tls'); // CORRIGIDO: tls ao invés de ssl
}

// Constantes essenciais
if (!defined('CLIENT_DASHBOARD_URL')) {
    define('CLIENT_DASHBOARD_URL', SITE_URL . '/cliente/dashboard');
}
if (!defined('ADMIN_EMAIL')) {
    define('ADMIN_EMAIL', 'contato@klubecash.com');
}
if (!defined('SITE_URL')) {
    define('SITE_URL', 'https://klubecash.com');
}

// IMPORTANTE: A classe Email está em utils/Email.php
// Este arquivo contém apenas configurações
?>