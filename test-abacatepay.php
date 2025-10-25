<?php
/**
 * TESTE RÁPIDO - AbacatePay API
 * Acesse: https://klubecash.com/test-abacatepay.php
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configurações
$apiBase = 'https://api.abacatepay.com';
$apiKey = 'abc_prod_HQDz0bBuxAEPA6WCqZ5cJP4r';
$endpoint = '/v1/pixQrCode/create';

// Dados de teste
$payload = [
    'amount' => 100, // R$ 1,00
    'description' => 'Teste Klube Cash',
    'expiresIn' => 3600, // 1 hora
    'customer' => [
        'name' => 'Teste Cliente',
        'cellphone' => '34999999999',
        'email' => 'teste@klubecash.com',
        'taxId' => '12345678909' // CPF de teste
    ]
];

echo "=== TESTE ABACATE PAY ===\n\n";
echo "Endpoint: {$apiBase}{$endpoint}\n";
echo "Payload: " . json_encode($payload, JSON_PRETTY_PRINT) . "\n\n";

// Fazer requisição
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $apiBase . $endpoint,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json'
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "=== RESULTADO ===\n\n";
echo "HTTP Code: {$httpCode}\n";

if ($error) {
    echo "CURL Error: {$error}\n";
}

echo "Response: " . $response . "\n\n";

$decoded = json_decode($response, true);

if ($httpCode == 200 || $httpCode == 201) {
    echo "✅ SUCESSO! PIX gerado.\n";
    echo "ID: " . ($decoded['id'] ?? 'N/A') . "\n";
    echo "Status: " . ($decoded['status'] ?? 'N/A') . "\n";
} else {
    echo "❌ ERRO!\n";
    echo "Mensagem: " . ($decoded['message'] ?? $decoded['error'] ?? 'Erro desconhecido') . "\n";
}

// JSON final para debug
echo "\n\n=== JSON COMPLETO ===\n";
echo json_encode([
    'http_code' => $httpCode,
    'curl_error' => $error,
    'response' => $decoded
], JSON_PRETTY_PRINT);
