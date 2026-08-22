<?php
declare(strict_types=1);

use App\Core\Logger;
use App\Services\WhatsApp\CurlWahaHttpClient;
use App\Services\WhatsApp\WahaConfig;
use App\Services\WhatsApp\WahaService;

if (!defined('WHATSAPP_ENABLED')) require_once __DIR__ . '/../config/whatsapp.php';

/** Adaptador para preservar os gatilhos comerciais existentes. */
final class WhatsAppBot
{
    private static function service(): WahaService
    {
        return new WahaService(WahaConfig::fromEnvironment(), new CurlWahaHttpClient());
    }

    public static function sendTextMessage(string $phone, string $message, array $options = []): array
    {
        if (!WHATSAPP_ENABLED) return ['success' => false, 'message' => 'WhatsApp desabilitado', 'ack' => null];
        try {
            $response = self::service()->sendText($phone, $message);
            Logger::info('whatsapp.send.succeeded', ['tag' => $options['tag'] ?? null, 'message_id' => $response['id'] ?? null]);
            return ['success' => true, 'message' => 'Mensagem aceita pelo WhatsApp.', 'response' => $response, 'ack' => $response['id'] ?? null];
        } catch (Throwable $exception) {
            Logger::warning('whatsapp.send.failed', ['tag' => $options['tag'] ?? null, 'exception' => get_class($exception)]);
            return ['success' => false, 'message' => 'Nao foi possivel enviar a mensagem.', 'ack' => null];
        }
    }

    public static function sendNewTransactionNotification(string $phone, array $data, array $options = []): array
    {
        $store = $data['nome_loja'] ?? $data['loja_nome'] ?? 'sua loja parceira';
        $name = trim((string) ($data['cliente_nome'] ?? $data['cliente'] ?? 'cliente')) ?: 'cliente';
        $cashback = isset($data['valor_cashback']) ? 'R$ ' . number_format((float) $data['valor_cashback'], 2, ',', '.') : 'Giftback';
        $message = "Ola, {$name}! Sua compra no {$store} gerou {$cashback} de Giftback no seu saldo KlubeCash. O valor pode ser reutilizado nessa loja.";
        return self::sendTextMessage($phone, $message, ['tag' => 'transaction:new'] + $options);
    }

    public static function sendCashbackReleasedNotification(string $phone, array $data, array $options = []): array
    {
        $store = $data['nome_loja'] ?? $data['loja_nome'] ?? 'nossa loja parceira';
        $value = isset($data['valor_cashback']) ? ' de R$ ' . number_format((float) $data['valor_cashback'], 2, ',', '.') : '';
        return self::sendTextMessage($phone, "Seu cashback{$value} da loja {$store} foi liberado e esta disponivel para uso.", ['tag' => 'cashback:released'] + $options);
    }
}
