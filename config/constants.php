<?php
/**
 * Constantes do sistema - Klube Cash v2.1
 * Configurações otimizadas para Mercado Pago com qualidade máxima
 */
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', '/home/u383946504/domains/klubecash.com/public_html/'); // SEU CAMINHO ABSOLUTO
}


// === TIPOS DE CLIENTE ===
define('CLIENT_TYPE_COMPLETE', 'completo');      // Cliente com cadastro completo
define('CLIENT_TYPE_VISITOR', 'visitante');      // Cliente visitante (sem senha)

// === STATUS DE RESPOSTA PARA BUSCA DE CLIENTE ===
define('CLIENT_SEARCH_FOUND', 'found');          // Cliente encontrado
define('CLIENT_SEARCH_NOT_FOUND', 'not_found');  // Cliente não encontrado
define('CLIENT_SEARCH_INACTIVE', 'inactive');    // Cliente inativo

// === CONFIGURAÇÕES DE CLIENTE VISITANTE ===
define('VISITOR_NAME_MAX_LENGTH', 100);          // Tamanho máximo do nome do visitante
define('VISITOR_PHONE_MIN_LENGTH', 10);          // Tamanho mínimo do telefone

// === MENSAGENS DE CLIENTE VISITANTE ===
define('MSG_VISITOR_CREATED', 'Cliente visitante criado com sucesso');
define('MSG_VISITOR_EXISTS', 'Já existe um cliente visitante com este telefone nesta loja');
define('MSG_VISITOR_INVALID_DATA', 'Dados inválidos para criar cliente visitante');

// === INFORMAÇÕES DO SISTEMA ===
define('SYSTEM_NAME', 'Klube Cash');
define('SYSTEM_VERSION', '2.1.0');
define('SITE_URL', 'https://klubecash.com');
define('ADMIN_EMAIL', 'contato@klubecash.com');
// === SMTP CONFIGURAÇÕES ===
define('SMTP_HOST_HOSTINGER', 'smtp.hostinger.com');
define('SMTP_PORT_HOSTINGER', 465);
define('SMTP_USER_HOSTINGER', 'klubecash@klubecash.com');
define('SMTP_PASS_HOSTINGER', 'Aaku_2004@');
define('SMTP_FROM_HOSTINGER', 'klubecash@klubecash.com');
define('SMTP_NAME_HOSTINGER', 'Klube Cash');
// === CORES DO TEMA ===
define('PRIMARY_COLOR', '#FF7A00');
define('SECONDARY_COLOR', '#1A1A1A');
define('SUCCESS_COLOR', '#10B981');
define('WARNING_COLOR', '#F59E0B');
define('DANGER_COLOR', '#EF4444');
define('INFO_COLOR', '#3B82F6');

// === DIRETÓRIOS ===
define('ROOT_DIR', dirname(__DIR__));
define('VIEWS_DIR', ROOT_DIR . '/views');
define('UPLOADS_DIR', ROOT_DIR . '/uploads');
define('LOGS_DIR', ROOT_DIR . '/logs');
define('ASSETS_DIR', ROOT_DIR . '/assets');
define('DISPLAY_DEBUG_LOGS', false);

// === CONFIGURAÇÕES DE CASHBACK ===
define('DEFAULT_CASHBACK_TOTAL', 10.00);
define('DEFAULT_CASHBACK_CLIENT', 5.00);
define('DEFAULT_CASHBACK_ADMIN', 5.00);
define('DEFAULT_CASHBACK_STORE', 0.00);

// === STATUS ===
define('TRANSACTION_PENDING', 'pendente');
define('TRANSACTION_APPROVED', 'aprovado');
define('TRANSACTION_CANCELED', 'cancelado');
define('TRANSACTION_PAYMENT_PENDING', 'pagamento_pendente');

define('USER_ACTIVE', 'ativo');
define('USER_INACTIVE', 'inativo');
define('USER_BLOCKED', 'bloqueado');

define('USER_TYPE_CLIENT', 'cliente');
define('USER_TYPE_ADMIN', 'admin');
define('USER_TYPE_STORE', 'loja');
define('USER_TYPE_EMPLOYEE', 'funcionario');


// === AUTENTICAÇÃO (ADIÇÃO) ===
define('JWT_SECRET', 'klube_cash_secret_key_2025_secure');
define('SESSION_LIFETIME', 3600); // 1 hora
define('TOKEN_EXPIRATION', 7200); // 2 horas para recuperação de senha




// === CONFIGURAÇÕES NOTIFICAÇÕES CASHBACK ===
define('CASHBACK_NOTIFICATIONS_ENABLED', true);
define('CASHBACK_NOTIFICATION_API_URL', SITE_URL . '/api/cashback-notificacao.php');

// Configurações de retry para notificações falhadas
define('CASHBACK_NOTIFICATION_MAX_RETRIES', 3);
define('CASHBACK_NOTIFICATION_RETRY_INTERVAL', 3600); // 1 hora em segundos

// === TIPOS DE MENSAGEM PARA CUSTOMIZAÇÃO ===
define('NOTIFICATION_TYPE_FIRST_PURCHASE', 'first_purchase');
define('NOTIFICATION_TYPE_BIG_PURCHASE', 'big_purchase');
define('NOTIFICATION_TYPE_VIP_CLIENT', 'vip_client');
define('NOTIFICATION_TYPE_REGULAR_CLIENT', 'regular_client');

// Valores para determinação de perfil do cliente
define('VIP_CLIENT_MIN_CASHBACK', 500.00);
define('VIP_CLIENT_MIN_TRANSACTIONS', 20);
define('BIG_PURCHASE_THRESHOLD', 200.00);


// === SISTEMA DE USUÁRIOS SIMPLIFICADO ===
// Todos os funcionários têm acesso igual ao lojista
define('STORE_ACCESS_TYPES', [
    USER_TYPE_STORE => 'Lojista',
    USER_TYPE_EMPLOYEE => 'Funcionário'
]);

// === RASTREAMENTO DE AÇÕES ===
define('TRACK_USER_ACTIONS', true);
define('LOG_TRANSACTION_CREATOR', true);
define('LOG_PAYMENT_CREATOR', true);
define('LOG_EMPLOYEE_CREATOR', true);

// === CAMPOS DE AUDITORIA ===
define('AUDIT_CREATED_BY', 'criado_por');
define('AUDIT_UPDATED_BY', 'atualizado_por');
define('AUDIT_CREATED_AT', 'data_criacao');
define('AUDIT_UPDATED_AT', 'data_atualizacao');

// === FUNCIONÁRIOS SIMPLIFICADO ===
// Todos funcionários têm acesso igual, diferenciados apenas para exibição
define('EMPLOYEE_DISPLAY_TYPES', [
    'funcionario' => 'Funcionário',
    'gerente' => 'Gerente', 
    'coordenador' => 'Coordenador',
    'assistente' => 'Assistente',
    'vendedor' => 'Vendedor',
    'financeiro' => 'Financeiro'
]);

// Campo opcional para organização interna (não afeta permissões)
define('EMPLOYEE_POSITION_FIELD', 'cargo_display');


// === URLs DE REDIRECIONAMENTO ===




define('STORE_PENDING', 'pendente');
define('STORE_APPROVED', 'aprovado');
define('STORE_REJECTED', 'rejeitado');

// === CONFIGURAÇÕES DE SEGURANÇA ===
define('PASSWORD_MIN_LENGTH', 8);


define('CPF_REQUIRED', true); // Novo: Indica se CPF é obrigatório

// === MENSAGENS DE VALIDAÇÃO ===
define('MSG_CPF_REQUIRED', 'CPF é obrigatório para completar seu perfil');
define('MSG_CPF_INVALID', 'CPF informado é inválido');
define('MSG_CPF_EXISTS', 'Este CPF já está cadastrado no sistema');
// === MERCADO PAGO CONFIGURAÇÕES OTIMIZADAS ===
define('MP_PUBLIC_KEY', 'APP_USR-60bd9502-2ea5-46c8-80b5-765f10277949'); // Chave pública de Produção
define('MP_ACCESS_TOKEN', 'APP_USR-8622491157025652-060223-01208b007f3c9b708958e846841e0a63-2320640278'); // Access token de produção
define('MP_WEBHOOK_URL', SITE_URL . '/api/mercadopago-webhook');
define('MP_WEBHOOK_SECRET', '21c03ffb0010adca8e57a0b9fcf30855191d44008baa16b757d9104ed5bfce5b'); // Secret do webhook

// === CONFIGURAÇÕES AVANÇADAS MERCADO PAGO ===
define('MP_ENVIRONMENT', 'production'); // production ou sandbox
define('MP_PLATFORM_ID', 'mp-ecom'); // Identificador da plataforma
define('MP_CORPORATION_ID', 'klubecash'); // ID da corporação
define('MP_INTEGRATION_TYPE', 'direct'); // Tipo de integração
define('MP_MAX_RETRIES', 3); // Máximo de tentativas
define('MP_TIMEOUT', 30); // Timeout em segundos
define('MP_USER_AGENT', 'KlubeCash/2.1 (Mercado Pago Integration Optimized)');

// === URLs MERCADO PAGO ===
define('MP_CREATE_PAYMENT_URL', SITE_URL . '/api/mercadopago?action=create_payment');
define('MP_CHECK_STATUS_URL', SITE_URL . '/api/mercadopago?action=status');
define('MP_BASE_URL', 'https://api.mercadopago.com');

// === CONFIGURAÇÕES DE QUALIDADE MP ===
define('MP_ENABLE_DEVICE_ID', true); // Habilitar device ID
define('MP_ENABLE_FRAUD_PREVENTION', true); // Habilitar prevenção de fraude
define('MP_REQUIRE_PAYER_INFO', true); // Exigir informações completas do pagador
define('MP_ENABLE_ADDRESS_VALIDATION', true); // Habilitar validação de endereço
define('MP_ENABLE_PHONE_VALIDATION', true); // Habilitar validação de telefone

// === PAGINAÇÃO ===
define('ITEMS_PER_PAGE', 10);

// === LIMITES ===
define('MIN_TRANSACTION_VALUE', 5.00);
define('MIN_WITHDRAWAL_VALUE', 20.00);

// === URLs PRINCIPAIS ===
define('LOGIN_URL', SITE_URL . '/login');
define('REGISTER_URL', SITE_URL . '/registro');
define('RECOVER_PASSWORD_URL', SITE_URL . '/recuperar-senha');
// === URLs DE AUTENTICAÇÃO ===

define('LOGOUT_URL', SITE_URL . '/logout'); // ADICIONAR ESTA LINHA

// === URLs DO CLIENTE ===
define('CLIENT_DASHBOARD_URL', SITE_URL . '/cliente/dashboard');
define('CLIENT_STATEMENT_URL', SITE_URL . '/cliente/extrato');
define('CLIENT_STORES_URL', SITE_URL . '/cliente/lojas-parceiras');
define('CLIENT_PROFILE_URL', SITE_URL . '/cliente/perfil');
define('CLIENT_BALANCE_URL', SITE_URL . '/cliente/saldo');
define('CLIENT_ACTIONS_URL', SITE_URL . '/cliente/actions');


// === CONFIGURAÇÕES DE CPF ===
define('CPF_TESTE_VALIDO', '00000000191'); // CPF válido para testes quando loja não tem CPF
define('CPF_OBRIGATORIO_PIX', true); // Exigir CPF para pagamentos PIX


// === URLs DO ADMIN ===
define('ADMIN_DASHBOARD_URL', SITE_URL . '/admin/dashboard');
define('ADMIN_USERS_URL', SITE_URL . '/admin/usuarios');
define('ADMIN_STORES_URL', SITE_URL . '/admin/lojas');
define('ADMIN_TRANSACTIONS_URL', SITE_URL . '/admin/transacoes');
define('ADMIN_SETTINGS_URL', SITE_URL . '/admin/configuracoes');
define('ADMIN_TRANSACTION_DETAILS_URL', SITE_URL . '/admin/transacao');
define('ADMIN_REPORTS_URL', SITE_URL . '/admin/relatorios');
define('ADMIN_COMMISSIONS_URL', SITE_URL . '/admin/comissoes');
define('ADMIN_PAYMENTS_URL', SITE_URL . '/admin/pagamentos');
define('ADMIN_BALANCE_URL', SITE_URL . '/admin/saldo');

// === URLs DA LOJA ===
define('STORE_REGISTER_URL', SITE_URL . '/lojas/cadastro');
define('STORE_DASHBOARD_URL', SITE_URL . '/store/dashboard');
define('STORE_TRANSACTIONS_URL', SITE_URL . '/store/transacoes');
define('STORE_PENDING_TRANSACTIONS_URL', SITE_URL . '/store/transacoes-pendentes');
define('STORE_REGISTER_TRANSACTION_URL', SITE_URL . '/store/registrar-transacao');
define('STORE_BATCH_UPLOAD_URL', SITE_URL . '/store/upload-lote');
define('STORE_PAYMENT_URL', SITE_URL . '/store/pagamento');
define('STORE_PAYMENT_HISTORY_URL', SITE_URL . '/store/historico-pagamentos');
define('STORE_PROFILE_URL', SITE_URL . '/store/perfil');
define('STORE_PAYMENT_PIX_URL', SITE_URL . '/store/pagamento-pix');
define('STORE_SALDOS_URL', SITE_URL . '/store/saldos');
define('STORE_BALANCE_REPASSES_URL', SITE_URL . '/store/repasses-saldo');
// Adicionar esta linha se não existir
define('STORE_EMPLOYEES_URL', SITE_URL . '/store/funcionarios');


// === CONFIGURAÇÕES DE ASSETS ===
define('ASSETS_VERSION', '2.1.0'); // Para cache busting
define('CDN_URL', SITE_URL); // Para futuros CDNs
define('CSS_URL', SITE_URL . '/assets/css');
define('JS_URL', SITE_URL . '/assets/js');
define('IMG_URL', SITE_URL . '/assets/images');

// === GOOGLE OAUTH ===
define('GOOGLE_CLIENT_ID', '662122339659-cj38e31a45cghrmnt4qq9slkroqh24n4s.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URI', 'https://klubecash.com/auth/google/callback');

define('GOOGLE_AUTH_URL', 'https://accounts.google.com/o/oauth2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('GOOGLE_USER_INFO_URL', 'https://www.googleapis.com/oauth2/v2/userinfo');
define('GOOGLE_PEOPLE_API_URL', 'https://people.googleapis.com/v1/people/me');

define('GOOGLE_AUTH_ENDPOINT', SITE_URL . '/auth/google/auth');
define('GOOGLE_CALLBACK_ENDPOINT', SITE_URL . '/auth/google/callback');


// === EMAIL PERSONALIZADO (PÚBLICO) - RAIZ ===
define('PUBLIC_EMAIL_SEND_URL', SITE_URL . '/enviar-email');
define('PUBLIC_EMAIL_SEND_ALT_URL', SITE_URL . '/email');
define('PUBLIC_EMAIL_SEND_ALT2_URL', SITE_URL . '/send-email');
define('EMAIL_ACCESS_PASSWORD', 'klube2024@!'); // Senha de acesso



// === EMAIL PERSONALIZADO (ADMIN) ===
define('ADMIN_EMAIL_SEND_URL', SITE_URL . '/admin/enviar-email');
define('EMAIL_SEND_BATCH_SIZE', 50);
define('EMAIL_SEND_DELAY_MS', 200);
// Configurações de envio em lote
define('EMAIL_BATCH_SIZE', 50);
define('EMAIL_SEND_DELAY', 200000); // 0.2 segundo
define('EMAIL_MAX_RETRIES', 3);

// === CONFIGURAÇÕES DE EMAIL ===
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', 'smtp.hostinger.com');
    define('SMTP_PORT', 587); // MUDANÇA: 465 → 587
    define('SMTP_USERNAME', 'klubecash@klubecash.com');
    define('SMTP_PASSWORD', 'Aaku_2004@');
    define('SMTP_FROM_EMAIL', 'klubecash@klubecash.com');
    define('SMTP_FROM_NAME', 'Klube Cash');
    define('SMTP_ENCRYPTION', 'tls'); // MUDANÇA: 'ssl' → 'tls'
}
// === OPENPIX CONFIGURAÇÕES (NOVA) ===
define('OPENPIX_API_URL', 'https://api.openpix.com.br');
define('OPENPIX_APP_ID', 'Q2xpZW50X0lkXzIzOTVjYmMzLWYyOGItNGJmYi04MWE3LWNkZWIzYzJkYTI4ZTpDbGllbnRfU2VjcmV0X3JYOFRxM016ZWdoNUY5YnVnempJeHl1VlBsRkg2QkNubm0yRFFzUWxQU1E9'); // Substitua pelo seu App ID real
define('OPENPIX_WEBHOOK_AUTH', 'klube_cash_webhook_2025'); // Chave de autorização do webhook
define('OPENPIX_WEBHOOK_URL', SITE_URL . '/webhook/openpix');

// === ABACATE PAY CONFIGURAÇÕES (ASSINATURAS - PIX) ===
define('ABACATE_API_BASE', 'https://api.abacatepay.com');
define('ABACATE_API_KEY', 'abc_prod_HQDz0bBuxAEPA6WCqZ5cJP4r'); // DEFINIR: Sua chave de API do Abacate Pay
define('ABACATE_WEBHOOK_SECRET', 'klubecash2025'); // DEFINIR: Segredo do webhook (gerar no painel)
define('ABACATE_WEBHOOK_URL', SITE_URL . '/api/abacatepay-webhook');
define('ABACATE_TIMEOUT', 30); // Timeout em segundos

// === STRIPE CONFIGURAÇÕES (ASSINATURAS - CARTÃO DE CRÉDITO) ===
define('STRIPE_API_BASE', 'https://api.stripe.com');
define('STRIPE_SECRET_KEY', 'sk_test_COLOCAR_SUA_CHAVE_SECRETA_AQUI'); // DEFINIR: Chave secreta do Stripe (sk_test_... ou sk_live_...)
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_COLOCAR_SUA_CHAVE_PUBLICA_AQUI'); // DEFINIR: Chave pública do Stripe (pk_test_... ou pk_live_...)
define('STRIPE_WEBHOOK_SECRET', 'whsec_COLOCAR_SEU_WEBHOOK_SECRET_AQUI'); // DEFINIR: Secret do webhook (gerar no painel)
define('STRIPE_WEBHOOK_URL', SITE_URL . '/api/stripe-webhook');
define('STRIPE_TIMEOUT', 30); // Timeout em segundos
define('STRIPE_VALIDATE_WEBHOOK', true); // IMPORTANTE: true em produção, false apenas para testes

// URLs de Assinaturas
define('ADMIN_SUBSCRIPTIONS_URL', SITE_URL . '/admin/assinaturas');
define('ADMIN_PLANS_URL', SITE_URL . '/admin/planos');
define('STORE_SUBSCRIPTION_URL', SITE_URL . '/store/meu-plano');
define('STORE_INVOICE_PIX_URL', SITE_URL . '/store/fatura-pix');

// === AMBIENTE ===
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'production');
    define('LOG_LEVEL', 'INFO');
}

// === EXPORTAÇÕES ===
define('EXPORTS_DIR', ROOT_DIR . '/exports');

// === CONFIGURAÇÕES DE SESSÃO SEGURA ===
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 0);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1); // HTTPS obrigatório
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// === EMAIL MARKETING ===
define('ADMIN_EMAIL_MARKETING_URL', SITE_URL . '/admin/email-marketing');
define('ADMIN_EMAIL_TEMPLATES_URL', SITE_URL . '/admin/email-templates');
define('ADMIN_EMAIL_CAMPAIGNS_URL', SITE_URL . '/admin/email-campanhas');



// === PERFORMANCE CONFIGS ===
define('CACHE_DURATION', 3600);
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'webp', 'gif']);

// === SEO E META ===
define('DEFAULT_META_TITLE', 'Klube Cash - Transforme suas Compras em Dinheiro de Volta');
define('DEFAULT_META_DESCRIPTION', 'O programa de cashback mais inteligente do Brasil. Receba dinheiro de volta em todas as suas compras. Cadastre-se grátis!');
define('DEFAULT_META_KEYWORDS', 'cashback, dinheiro de volta, economia, programa de fidelidade, compras online, desconto, lojas parceiras');

// === CONFIGURAÇÕES DE LOGS AVANÇADAS ===
define('LOG_MP_REQUESTS', true); // Log de todas as requisições MP
define('LOG_MP_RESPONSES', true); // Log de todas as respostas MP
define('LOG_WEBHOOK_EVENTS', true); // Log de eventos de webhook
define('LOG_QUALITY_METRICS', true); // Log de métricas de qualidade

// === MERCADO PAGO SDK CONFIGURATION ===
define('MP_SDK_VERSION', 'v2');
define('MP_SDK_URL', 'https://sdk.mercadopago.com/js/v2');
define('MP_FRONTEND_SDK_ENABLED', true);
define('MP_BACKEND_SDK_ENABLED', true);
define('MP_PCI_COMPLIANCE_MODE', true);

// === CERTIFICADOS E SEGURANÇA ===
define('SSL_ENABLED', true);
define('TLS_VERSION', '1.2+');
define('PCI_DSS_COMPLIANT', true);
define('HTTPS_ONLY', true);

// === DEVICE ID CONFIGURATION ===
define('DEVICE_ID_PREFIX', 'klube_web_');
define('DEVICE_ID_ALGORITHM', 'enhanced');
define('DEVICE_ID_STORAGE', 'multi'); // localStorage + sessionStorage + cookie

// Funções helper para certificados
function is_ssl_enabled() {
    return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

function get_tls_version() {
    return $_SERVER['SSL_PROTOCOL'] ?? 'unknown';
}

function validate_pci_compliance() {
    return is_ssl_enabled() && 
           (strpos(get_tls_version(), '1.2') !== false || 
            strpos(get_tls_version(), '1.3') !== false);
}



// === FUNÇÕES HELPER OTIMIZADAS ===
function asset($path, $versioned = true) {
    $url = SITE_URL . '/assets/' . ltrim($path, '/');
    return $versioned ? $url . '?v=' . ASSETS_VERSION : $url;
}

function route($name, $params = []) {
    $routes = [
        'home' => SITE_URL,
        'login' => LOGIN_URL,
        'register' => REGISTER_URL,
        'client.dashboard' => CLIENT_DASHBOARD_URL,
        'admin.dashboard' => ADMIN_DASHBOARD_URL,
        'store.dashboard' => STORE_DASHBOARD_URL,
    ];
    
    $url = $routes[$name] ?? SITE_URL;
    
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    return $url;
}

function cdn($path) {
    return CDN_URL . '/' . ltrim($path, '/');
}

// === VALIDAÇÕES ===
function is_production() {
    return ENVIRONMENT === 'production';
}

function is_development() {
    return ENVIRONMENT === 'development';
}

function get_asset_url($file) {
    $hash = is_production() ? md5_file(ROOT_DIR . '/assets/' . $file) : time();
    return asset($file) . '?v=' . substr($hash, 0, 8);
}

// === FUNÇÕES MERCADO PAGO ===
function mp_log($message, $data = null) {
    if (LOG_MP_REQUESTS) {
        $logMessage = "[MP] " . $message;
        if ($data) {
            $logMessage .= " - Data: " . json_encode($data);
        }
        error_log($logMessage);
    }
}

function mp_is_enabled() {
    return defined('MP_ACCESS_TOKEN') && !empty(MP_ACCESS_TOKEN);
}

function mp_get_device_id() {
    if (!MP_ENABLE_DEVICE_ID) return null;
    
    // Gerar device ID baseado em informações do cliente
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $timestamp = time();
    
    return 'device_' . md5($userAgent . $ip . $timestamp);
}
// === CORREÇÕES PARA REGISTRO ===
if (!defined('PASSWORD_DEFAULT')) {
    define('PASSWORD_DEFAULT', PASSWORD_BCRYPT);
}

// === VALIDAÇÃO DE ESTRUTURA ===
if (!defined('PASSWORD_MIN_LENGTH')) {
    define('PASSWORD_MIN_LENGTH', 8);
}
?>

