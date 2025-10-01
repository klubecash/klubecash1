<?php
/**
 * PWAUtils.php
 * Klube Cash - Sistema de Cashback
 * 
 * Utilitários para Progressive Web App (PWA)
 * Inclui detecção de dispositivos, validações mobile e helpers específicos
 * 
 * @author Klube Cash Team
 * @version 2.0
 * @since 2024
 */

class PWAUtils {
    
    // Constantes para detecção de dispositivos
    const DEVICE_MOBILE = 'mobile';
    const DEVICE_TABLET = 'tablet';
    const DEVICE_DESKTOP = 'desktop';
    
    // User agents para detecção
    private static $mobileUserAgents = [
        'Mobile', 'Android', 'iPhone', 'iPad', 'iPod', 'BlackBerry', 
        'Windows Phone', 'Opera Mini', 'IEMobile', 'Mobile Safari'
    ];
    
    private static $tabletUserAgents = [
        'iPad', 'Android.*Tablet', 'Tablet', 'Kindle', 'Silk/', 'GT-P'
    ];
    
    /**
     * Detecta o tipo de dispositivo baseado no User-Agent
     * 
     * @return string Tipo do dispositivo (mobile, tablet, desktop)
     */
    public static function detectDevice() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Verificar se é tablet primeiro (iPad tem 'Mobile' no UA)
        foreach (self::$tabletUserAgents as $tablet) {
            if (preg_match('/' . $tablet . '/i', $userAgent)) {
                return self::DEVICE_TABLET;
            }
        }
        
        // Verificar se é mobile
        foreach (self::$mobileUserAgents as $mobile) {
            if (preg_match('/' . $mobile . '/i', $userAgent)) {
                return self::DEVICE_MOBILE;
            }
        }
        
        return self::DEVICE_DESKTOP;
    }
    
    /**
     * Verifica se o dispositivo é mobile
     * 
     * @return bool
     */
    public static function isMobile() {
        return self::detectDevice() === self::DEVICE_MOBILE;
    }
    
    /**
     * Verifica se o dispositivo é tablet
     * 
     * @return bool
     */
    public static function isTablet() {
        return self::detectDevice() === self::DEVICE_TABLET;
    }
    
    /**
     * Verifica se o dispositivo é desktop
     * 
     * @return bool
     */
    public static function isDesktop() {
        return self::detectDevice() === self::DEVICE_DESKTOP;
    }
    
    /**
     * Verifica se o dispositivo suporta touch
     * 
     * @return bool
     */
    public static function isTouchDevice() {
        return self::isMobile() || self::isTablet();
    }
    
    /**
     * Detecta se está sendo executado como PWA instalado
     * 
     * @return bool
     */
    public static function isInstalled() {
        // Verifica headers que indicam PWA instalado
        $displayMode = $_SERVER['HTTP_SEC_CH_UA_MOBILE'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Safari PWA
        if (strpos($userAgent, 'Safari') !== false && 
            !strpos($userAgent, 'Chrome') && 
            !isset($_SERVER['HTTP_SEC_FETCH_SITE'])) {
            return true;
        }
        
        // Chrome PWA
        if (isset($_SERVER['HTTP_SEC_FETCH_SITE']) && 
            $_SERVER['HTTP_SEC_FETCH_SITE'] === 'none') {
            return true;
        }
        
        return false;
    }
    
    /**
     * Detecta o sistema operacional
     * 
     * @return string
     */
    public static function getOperatingSystem() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $os = [
            'iOS' => '/iPad|iPhone|iPod/',
            'Android' => '/Android/',
            'Windows' => '/Windows/',
            'macOS' => '/Macintosh|Mac OS X/',
            'Linux' => '/Linux/'
        ];
        
        foreach ($os as $name => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return $name;
            }
        }
        
        return 'Unknown';
    }
    
    /**
     * Detecta o navegador
     * 
     * @return array [name, version]
     */
    public static function getBrowser() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $browsers = [
            'Safari' => '/Version\/([\d\.]+).*Safari/',
            'Chrome' => '/Chrome\/([\d\.]+)/',
            'Firefox' => '/Firefox\/([\d\.]+)/',
            'Edge' => '/Edge\/([\d\.]+)/',
            'Opera' => '/Opera\/([\d\.]+)/',
            'Samsung Internet' => '/SamsungBrowser\/([\d\.]+)/'
        ];
        
        foreach ($browsers as $name => $pattern) {
            if (preg_match($pattern, $userAgent, $matches)) {
                return [
                    'name' => $name,
                    'version' => $matches[1] ?? 'unknown'
                ];
            }
        }
        
        return ['name' => 'Unknown', 'version' => 'unknown'];
    }
    
    /**
     * Valida se o navegador suporta PWA
     * 
     * @return bool
     */
    public static function supportsPWA() {
        $browser = self::getBrowser();
        $browserName = $browser['name'];
        $version = floatval($browser['version']);
        
        // Navegadores que suportam PWA e versões mínimas
        $supportedBrowsers = [
            'Chrome' => 40,
            'Firefox' => 44,
            'Safari' => 11.1,
            'Edge' => 17,
            'Samsung Internet' => 4.0,
            'Opera' => 32
        ];
        
        return isset($supportedBrowsers[$browserName]) && 
               $version >= $supportedBrowsers[$browserName];
    }
    
    /**
     * Verifica se o dispositivo suporta notificações push
     * 
     * @return bool
     */
    public static function supportsPushNotifications() {
        $os = self::getOperatingSystem();
        $browser = self::getBrowser();
        
        // iOS Safari não suporta push notifications via web
        if ($os === 'iOS' && $browser['name'] === 'Safari') {
            return false;
        }
        
        return self::supportsPWA();
    }
    
    /**
     * Gera um Device ID único baseado no dispositivo
     * 
     * @return string
     */
    public static function generateDeviceId() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // Usar apenas partes não sensíveis para gerar ID
        $fingerprint = $userAgent . $acceptLanguage . $acceptEncoding;
        
        return DEVICE_ID_PREFIX . md5($fingerprint);
    }
    
    /**
     * Retorna configurações específicas para o dispositivo
     * 
     * @return array
     */
    public static function getDeviceConfig() {
        $device = self::detectDevice();
        $os = self::getOperatingSystem();
        
        $config = [
            'device_type' => $device,
            'is_touch' => self::isTouchDevice(),
            'is_installed' => self::isInstalled(),
            'supports_pwa' => self::supportsPWA(),
            'supports_push' => self::supportsPushNotifications(),
            'device_id' => self::generateDeviceId(),
            'os' => $os,
            'browser' => self::getBrowser()
        ];
        
        // Configurações específicas por dispositivo
        switch ($device) {
            case self::DEVICE_MOBILE:
                $config['viewport'] = 'width=device-width, initial-scale=1.0, user-scalable=no';
                $config['theme_color'] = THEME_COLOR_PRIMARY;
                $config['display'] = 'standalone';
                $config['max_touch_targets'] = 44; // iOS guidelines
                break;
                
            case self::DEVICE_TABLET:
                $config['viewport'] = 'width=device-width, initial-scale=1.0';
                $config['theme_color'] = THEME_COLOR_PRIMARY;
                $config['display'] = 'standalone';
                $config['max_touch_targets'] = 44;
                break;
                
            case self::DEVICE_DESKTOP:
                $config['viewport'] = 'width=device-width, initial-scale=1.0';
                $config['theme_color'] = THEME_COLOR_PRIMARY;
                $config['display'] = 'browser';
                $config['max_touch_targets'] = null;
                break;
        }
        
        return $config;
    }
    
    /**
     * Valida campos específicos para mobile
     * 
     * @param string $field Nome do campo
     * @param mixed $value Valor a validar
     * @return array [valid => bool, message => string]
     */
    public static function validateMobileField($field, $value) {
        $result = ['valid' => true, 'message' => ''];
        
        switch ($field) {
            case 'phone':
                if (!preg_match('/^\([0-9]{2}\)\s[0-9]{4,5}-[0-9]{4}$/', $value)) {
                    $result = [
                        'valid' => false,
                        'message' => 'Formato de telefone inválido. Use (XX) XXXXX-XXXX'
                    ];
                }
                break;
                
            case 'cpf':
                $cpf = preg_replace('/[^0-9]/', '', $value);
                if (strlen($cpf) != 11 || !self::validateCPF($cpf)) {
                    $result = [
                        'valid' => false,
                        'message' => 'CPF inválido'
                    ];
                }
                break;
                
            case 'password':
                if (strlen($value) < 8) {
                    $result = [
                        'valid' => false,
                        'message' => 'Senha deve ter pelo menos 8 caracteres'
                    ];
                } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $value)) {
                    $result = [
                        'valid' => false,
                        'message' => 'Senha deve conter letras maiúsculas, minúsculas e números'
                    ];
                }
                break;
                
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $result = [
                        'valid' => false,
                        'message' => 'Email inválido'
                    ];
                }
                break;
        }
        
        return $result;
    }
    
    /**
     * Valida CPF usando algoritmo oficial
     * 
     * @param string $cpf CPF apenas números
     * @return bool
     */
    private static function validateCPF($cpf) {
        // Verifica se tem 11 dígitos
        if (strlen($cpf) != 11) return false;
        
        // Verifica se todos os dígitos são iguais
        if (preg_match('/(\d)\1{10}/', $cpf)) return false;
        
        // Validação dos dígitos verificadores
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) return false;
        }
        
        return true;
    }
    
    /**
     * Formata valores monetários para exibição mobile
     * 
     * @param float $value Valor monetário
     * @param bool $compact Usar formato compacto (1,2K)
     * @return string
     */
    public static function formatCurrency($value, $compact = false) {
        if ($compact && abs($value) >= 1000) {
            if (abs($value) >= 1000000) {
                return 'R$ ' . number_format($value / 1000000, 1, ',', '.') . 'M';
            } else {
                return 'R$ ' . number_format($value / 1000, 1, ',', '.') . 'K';
            }
        }
        
        return 'R$ ' . number_format($value, 2, ',', '.');
    }
    
    /**
     * Gera meta tags específicas para PWA
     * 
     * @return string HTML das meta tags
     */
    public static function generatePWAMetaTags() {
        $config = self::getDeviceConfig();
        $os = $config['os'];
        
        $tags = [
            // Viewport otimizado
            '<meta name="viewport" content="' . $config['viewport'] . '">',
            
            // PWA básico
            '<meta name="mobile-web-app-capable" content="yes">',
            '<meta name="apple-mobile-web-app-capable" content="yes">',
            '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">',
            '<meta name="apple-mobile-web-app-title" content="' . APP_NAME . '">',
            
            // Theme color
            '<meta name="theme-color" content="' . $config['theme_color'] . '">',
            '<meta name="msapplication-navbutton-color" content="' . $config['theme_color'] . '">',
            '<meta name="apple-mobile-web-app-status-bar-style" content="' . $config['theme_color'] . '">',
            
            // Manifest
            '<link rel="manifest" href="' . SITE_URL . '/pwa/manifest.json">',
            
            // Icons para iOS
            '<link rel="apple-touch-icon" href="' . SITE_URL . '/assets/icons/icon-192x192.png">',
            '<link rel="apple-touch-icon" sizes="152x152" href="' . SITE_URL . '/assets/icons/icon-152x152.png">',
            '<link rel="apple-touch-icon" sizes="180x180" href="' . SITE_URL . '/assets/icons/icon-180x180.png">',
            '<link rel="apple-touch-icon" sizes="167x167" href="' . SITE_URL . '/assets/icons/icon-167x167.png">',
            
            // Preconnect para recursos externos
            '<link rel="preconnect" href="https://fonts.googleapis.com">',
            '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>',
            '<link rel="preconnect" href="https://sdk.mercadopago.com">',
            
            // DNS prefetch
            '<link rel="dns-prefetch" href="//fonts.googleapis.com">',
            '<link rel="dns-prefetch" href="//sdk.mercadopago.com">',
            '<link rel="dns-prefetch" href="//api.mercadopago.com">'
        ];
        
        // Tags específicas para iOS
        if ($os === 'iOS') {
            $tags[] = '<meta name="apple-mobile-web-app-capable" content="yes">';
            $tags[] = '<meta name="apple-touch-fullscreen" content="yes">';
        }
        
        return implode("\n    ", $tags);
    }
    
    /**
     * Retorna configurações de cache baseadas no dispositivo
     * 
     * @return array
     */
    public static function getCacheConfig() {
        $isMobile = self::isTouchDevice();
        
        return [
            'static_cache_duration' => $isMobile ? 86400 : 604800, // 1 dia mobile, 1 semana desktop
            'api_cache_duration' => $isMobile ? 300 : 600, // 5 min mobile, 10 min desktop
            'enable_service_worker' => $isMobile,
            'cache_images' => true,
            'cache_css' => true,
            'cache_js' => true,
            'max_cache_size' => $isMobile ? 50 * 1024 * 1024 : 100 * 1024 * 1024 // 50MB mobile, 100MB desktop
        ];
    }
    
    /**
     * Gera configuração JavaScript para o frontend
     * 
     * @return string JSON com configurações
     */
    public static function getJavaScriptConfig() {
        $config = self::getDeviceConfig();
        $cacheConfig = self::getCacheConfig();
        
        $jsConfig = [
            'device' => $config,
            'cache' => $cacheConfig,
            'api' => [
                'base_url' => API_BASE_URL,
                'version' => API_VERSION,
                'timeout' => self::isMobile() ? 10000 : 15000
            ],
            'features' => [
                'push_notifications' => $config['supports_push'],
                'offline_mode' => true,
                'background_sync' => $config['supports_pwa'],
                'install_prompt' => PWA_INSTALL_PROMPT_ENABLED && !$config['is_installed']
            ],
            'mercado_pago' => [
                'public_key' => MP_PUBLIC_KEY,
                'enabled' => MP_FRONTEND_SDK_ENABLED,
                'device_id' => $config['device_id']
            ]
        ];
        
        return json_encode($jsConfig, JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Log específico para PWA com informações do dispositivo
     * 
     * @param string $message Mensagem do log
     * @param array $context Contexto adicional
     * @param string $level Nível do log (info, warning, error)
     */
    public static function log($message, $context = [], $level = 'info') {
        if (!LOG_PWA_EVENTS) return;
        
        $deviceInfo = [
            'device_type' => self::detectDevice(),
            'os' => self::getOperatingSystem(),
            'browser' => self::getBrowser(),
            'is_installed' => self::isInstalled(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $logData = array_merge($deviceInfo, $context);
        $logMessage = "[PWA-{$level}] {$message} - " . json_encode($logData);
        
        error_log($logMessage);
        
        // Salvar em arquivo específico se configurado
        if (defined('PWA_LOG_FILE') && PWA_LOG_FILE) {
            file_put_contents(PWA_LOG_FILE, $logMessage . "\n", FILE_APPEND | LOCK_EX);
        }
    }
}

// Helpers globais para facilitar o uso
if (!function_exists('is_mobile_device')) {
    function is_mobile_device() {
        return PWAUtils::isMobile();
    }
}

if (!function_exists('is_pwa_installed')) {
    function is_pwa_installed() {
        return PWAUtils::isInstalled();
    }
}

if (!function_exists('device_supports_pwa')) {
    function device_supports_pwa() {
        return PWAUtils::supportsPWA();
    }
}

if (!function_exists('get_device_config')) {
    function get_device_config() {
        return PWAUtils::getDeviceConfig();
    }
}

if (!function_exists('format_mobile_currency')) {
    function format_mobile_currency($value, $compact = false) {
        return PWAUtils::formatCurrency($value, $compact);
    }
}
?>