<?php
declare(strict_types=1);
namespace App\Services\WhatsApp;
final class CurlWahaHttpClient implements WahaHttpClient
{
    public function request(string $method, string $url, array $headers, ?string $body, int $timeoutSeconds): array
    {
        $handle = curl_init($url);
        curl_setopt_array($handle, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => min(5, $timeoutSeconds), CURLOPT_TIMEOUT => $timeoutSeconds, CURLOPT_HTTPHEADER => $headers]);
        if ($body !== null) curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        $response = curl_exec($handle);
        $errorNumber = curl_errno($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);
        if ($response === false) {
            $kind = $errorNumber === CURLE_OPERATION_TIMEDOUT ? 'timeout' : 'network';
            // Em um POST a conexao pode cair depois de o WAHA aceitar a mensagem.
            // Marcar a entrega como incerta impede uma repeticao automatica indevida.
            $deliveryUnknown = strtoupper($method) !== 'GET';
            throw new WahaException("Falha transitoria de {$kind} no WAHA.", 503, true, $deliveryUnknown);
        }
        return ['status' => $status, 'body' => (string) $response];
    }
}
