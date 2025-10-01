<?php
// api/mp-integration-check.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/constants.php';

// Função para verificar se arquivo existe e é legível
function checkFile($path, $description) {
    $fullPath = __DIR__ . '/../' . $path;
    return [
        'description' => $description,
        'path' => $path,
        'exists' => file_exists($fullPath),
        'readable' => is_readable($fullPath),
        'size' => file_exists($fullPath) ? filesize($fullPath) : 0,
        'status' => file_exists($fullPath) && is_readable($fullPath) ? '✅' : '❌'
    ];
}

// Função para verificar constantes
function checkConstants() {
    $required = [
        'MP_ACCESS_TOKEN' => 'Token de acesso do Mercado Pago',
        'MP_PUBLIC_KEY' => 'Chave pública do Mercado Pago', 
        'MP_WEBHOOK_URL' => 'URL do webhook',
        'MP_WEBHOOK_SECRET' => 'Secret do webhook',
        'SITE_URL' => 'URL do site'
    ];
    
    $results = [];
    foreach ($required as $constant => $description) {
        $defined = defined($constant);
        $value = $defined ? constant($constant) : null;
        $hasValue = !empty($value);
        
        $results[$constant] = [
            'description' => $description,
            'defined' => $defined,
            'has_value' => $hasValue,
            'preview' => $hasValue ? substr($value, 0, 20) . '...' : 'VAZIO',
            'status' => $defined && $hasValue ? '✅' : '❌'
        ];
    }
    
    return $results;
}

// Função para testar MercadoPago Client
function testMPClient() {
    try {
        if (!file_exists(__DIR__ . '/../utils/MercadoPagoClient.php')) {
            return ['status' => '❌', 'error' => 'Arquivo MercadoPagoClient.php não encontrado'];
        }
        
        require_once __DIR__ . '/../utils/MercadoPagoClient.php';
        
        if (!class_exists('MercadoPagoClient')) {
            return ['status' => '❌', 'error' => 'Classe MercadoPagoClient não encontrada'];
        }
        
        $client = new MercadoPagoClient();
        $test = $client->testConnection();
        
        return [
            'status' => $test['status'] ? '✅' : '❌',
            'message' => $test['message'],
            'details' => $test
        ];
        
    } catch (Exception $e) {
        return [
            'status' => '❌',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ];
    }
}

// Função para verificar database
function testDatabase() {
    try {
        if (!file_exists(__DIR__ . '/../config/database.php')) {
            return ['status' => '❌', 'error' => 'Arquivo database.php não encontrado'];
        }
        
        require_once __DIR__ . '/../config/database.php';
        
        if (!class_exists('Database')) {
            return ['status' => '❌', 'error' => 'Classe Database não encontrada'];
        }
        
        $db = Database::getConnection();
        
        // Testar algumas tabelas importantes
        $tables = ['usuarios', 'lojas', 'pagamentos_comissao', 'transacoes_cashback'];
        $tableStatus = [];
        
        foreach ($tables as $table) {
            try {
                $stmt = $db->query("SELECT COUNT(*) FROM {$table}");
                $count = $stmt->fetchColumn();
                $tableStatus[$table] = ['status' => '✅', 'records' => $count];
            } catch (Exception $e) {
                $tableStatus[$table] = ['status' => '❌', 'error' => $e->getMessage()];
            }
        }
        
        return [
            'status' => '✅',
            'connection' => 'OK',
            'tables' => $tableStatus
        ];
        
    } catch (Exception $e) {
        return [
            'status' => '❌',
            'error' => $e->getMessage()
        ];
    }
}

// Função para verificar permissões de arquivos
function checkPermissions() {
    $paths = [
        'uploads/' => 'Diretório de uploads',
        'logs/' => 'Diretório de logs', 
        'api/' => 'Diretório da API',
        'assets/js/' => 'JavaScript assets'
    ];
    
    $results = [];
    foreach ($paths as $path => $description) {
        $fullPath = __DIR__ . '/../' . $path;
        $results[$path] = [
            'description' => $description,
            'exists' => is_dir($fullPath),
            'writable' => is_writable($fullPath),
            'status' => is_dir($fullPath) && is_writable($fullPath) ? '✅' : '❌'
        ];
    }
    
    return $results;
}

// Função para verificar configurações PHP necessárias
function checkPHPConfig() {
    $requirements = [
        'curl' => extension_loaded('curl'),
        'json' => extension_loaded('json'),
        'openssl' => extension_loaded('openssl'),
        'pdo' => extension_loaded('pdo'),
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'session' => extension_loaded('session'),
        'allow_url_fopen' => ini_get('allow_url_fopen'),
        'ssl_verify_peer' => true // Sempre deve ser true para produção
    ];
    
    $results = [];
    foreach ($requirements as $req => $status) {
        $results[$req] = [
            'required' => true,
            'status' => $status ? '✅' : '❌',
            'value' => $status
        ];
    }
    
    $results['php_version'] = [
        'current' => PHP_VERSION,
        'minimum' => '7.4',
        'status' => version_compare(PHP_VERSION, '7.4', '>=') ? '✅' : '❌'
    ];
    
    return $results;
}

// Função para verificar URLs importantes
function checkURLs() {
    $urls = [
        'MP_WEBHOOK_URL' => defined('MP_WEBHOOK_URL') ? MP_WEBHOOK_URL : null,
        'MP_CREATE_PAYMENT_URL' => defined('MP_CREATE_PAYMENT_URL') ? MP_CREATE_PAYMENT_URL : null,
        'MP_CHECK_STATUS_URL' => defined('MP_CHECK_STATUS_URL') ? MP_CHECK_STATUS_URL : null
    ];
    
    $results = [];
    foreach ($urls as $name => $url) {
        if ($url) {
            // Verificar se a URL é acessível
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Para teste
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            $results[$name] = [
                'url' => $url,
                'http_code' => $httpCode,
                'accessible' => $httpCode > 0 && $httpCode < 500,
                'error' => $error,
                'status' => ($httpCode > 0 && $httpCode < 500) ? '✅' : '❌'
            ];
        } else {
            $results[$name] = [
                'url' => null,
                'status' => '❌',
                'error' => 'URL não definida'
            ];
        }
    }
    
    return $results;
}

// Executar todos os testes
$diagnostics = [
    'timestamp' => date('Y-m-d H:i:s'),
    'server_info' => [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
        'https' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'ssl_protocol' => $_SERVER['SSL_PROTOCOL'] ?? 'none'
    ],
    'files_check' => [
        'mercadopago_client' => checkFile('utils/MercadoPagoClient.php', 'Cliente do Mercado Pago'),
        'mercadopago_api' => checkFile('api/mercadopago.php', 'API do Mercado Pago'),
        'webhook' => checkFile('api/mercadopago-webhook.php', 'Webhook do Mercado Pago'),
        'constants' => checkFile('config/constants.php', 'Constantes do sistema'),
        'database' => checkFile('config/database.php', 'Configuração do banco'),
        'htaccess' => checkFile('.htaccess', 'Configuração do Apache'),
        'mp_sdk_js' => checkFile('assets/js/mercadopago-sdk.js', 'SDK JavaScript do MP')
    ],
    'constants_check' => checkConstants(),
    'php_config' => checkPHPConfig(),
    'database_test' => testDatabase(),
    'mercadopago_test' => testMPClient(),
    'permissions' => checkPermissions(),
    'urls_check' => checkURLs()
];

// Calcular score geral
function calculateScore($diagnostics) {
    $total = 0;
    $passed = 0;
    
    // Contar files
    foreach ($diagnostics['files_check'] as $file) {
        $total++;
        if ($file['status'] === '✅') $passed++;
    }
    
    // Contar constants
    foreach ($diagnostics['constants_check'] as $const) {
        $total++;
        if ($const['status'] === '✅') $passed++;
    }
    
    // Contar PHP config
    foreach ($diagnostics['php_config'] as $config) {
        $total++;
        if ($config['status'] === '✅') $passed++;
    }
    
    // Contar URLs
    foreach ($diagnostics['urls_check'] as $url) {
        $total++;
        if ($url['status'] === '✅') $passed++;
    }
    
    // Database e MP
    $total += 2;
    if ($diagnostics['database_test']['status'] === '✅') $passed++;
    if ($diagnostics['mercadopago_test']['status'] === '✅') $passed++;
    
    return [
        'total' => $total,
        'passed' => $passed,
        'percentage' => round(($passed / $total) * 100, 1),
        'status' => $passed === $total ? '✅ TUDO OK' : '⚠️ ATENÇÃO NECESSÁRIA'
    ];
}

$diagnostics['overall_score'] = calculateScore($diagnostics);

echo json_encode($diagnostics, JSON_PRETTY_PRINT);
?>