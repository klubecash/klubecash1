<?php
// config/email.php - APENAS CONFIGURAÇÕES (SEM CLASSE)
/**
 * Configuração de envio de emails
 * Klube Cash - Sistema de Cashback
 */

// Configurações SMTP - Apenas definições de constantes
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', getenv('SMTP_HOST') ?: '');
}
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', (int) (getenv('SMTP_PORT') ?: 587));
}
if (!defined('SMTP_USERNAME')) {
    define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
}
if (!defined('SMTP_PASSWORD')) {
    define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
}
if (!defined('SMTP_FROM_EMAIL')) {
    define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: '');
}
if (!defined('SMTP_FROM_NAME')) {
    define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'Klube Cash');
}
if (!defined('SMTP_ENCRYPTION')) {
    define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: 'tls');
}

// Constantes essenciais
if (!defined('CLIENT_DASHBOARD_URL')) {
    define('CLIENT_DASHBOARD_URL', SITE_URL . '/cliente/dashboard');
}
if (!defined('ADMIN_EMAIL')) {
    define('ADMIN_EMAIL', 'contato@klubecash.com');
}
if (!defined('SITE_URL')) {
    define('SITE_URL', rtrim(getenv('SITE_URL') ?: 'https://www.klubecash.com', '/'));
}

// IMPORTANTE: A classe Email está em utils/Email.php
// Este arquivo contém apenas configurações
