<?php
/**
 * TESTE V2 - Com CPF Válido
 */

header('Content-Type: text/plain');

$apiBase = 'https://api.abacatepay.com';
$apiKey = 'abc_prod_HQDz0bBuxAEPA6WCqZ5cJP4r';

// CPF VÁLIDO com dígito verificador correto: 111.444.777-35
$payload = [
    'amount' => 100,
    'description' => 'Teste Klube Cash v2',
    'expiresIn' => 3600,
    'customer' => [
        'name' => 'Teste Cliente',
        'cellphone' => '34999999999',
        'email' => 'teste@klubecash.com',
        'taxId' => '11144477735' // CPF VÁLIDO
    ]
];

echo "=== TESTE COM CPF VÁLIDO ===\n\n";
echo "CPF: 111.444.777-35\n";
echo "Payload: " . json_encode($payload, JSON_PRETTY_PRINT) . "\n\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $apiBase . '/v1/pixQrCode/create',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "Response: {$response}\n\n";

if ($httpCode == 200 || $httpCode == 201) {
    echo "✅ SUCESSO! PIX gerado com CPF válido!\n";
} else {
    echo "❌ ERRO mesmo com CPF válido!\n";
}
