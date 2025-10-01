<?php
// api/ssl-check.php
header('Content-Type: application/json');

// Verificar status SSL/TLS
$sslInfo = [
    'https_enabled' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'ssl_protocol' => $_SERVER['SSL_PROTOCOL'] ?? 'none',
    'ssl_cipher' => $_SERVER['SSL_CIPHER'] ?? 'none',
    'ssl_version' => $_SERVER['SSL_TLS_SNI'] ?? 'unknown',
    'server_port' => $_SERVER['SERVER_PORT'] ?? 'unknown',
    'request_scheme' => $_SERVER['REQUEST_SCHEME'] ?? 'unknown',
    'headers' => [
        'strict_transport_security' => $_SERVER['HTTP_STRICT_TRANSPORT_SECURITY'] ?? 'not_set',
        'x_forwarded_proto' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'not_set'
    ]
];

// Verificar se atende aos requisitos do MP
$mpCompliant = [
    'https_required' => $sslInfo['https_enabled'],
    'tls_12_plus' => strpos($sslInfo['ssl_protocol'], '1.2') !== false || 
                    strpos($sslInfo['ssl_protocol'], '1.3') !== false,
    'secure_port' => $sslInfo['server_port'] == '443',
    'hsts_enabled' => !empty($sslInfo['headers']['strict_transport_security'])
];

$allCompliant = array_reduce($mpCompliant, function($carry, $item) {
    return $carry && $item;
}, true);

echo json_encode([
    'ssl_info' => $sslInfo,
    'mp_compliant' => $mpCompliant,
    'all_requirements_met' => $allCompliant,
    'timestamp' => date('Y-m-d H:i:s'),
    'recommendations' => $allCompliant ? [] : [
        'Certifique-se de que o site está acessível apenas via HTTPS',
        'Verifique se o certificado SSL é válido e confiável',
        'Configure TLS 1.2 ou superior no servidor',
        'Habilite HSTS (Strict-Transport-Security)',
        'Use uma porta segura (443) para HTTPS'
    ]
]);
?>