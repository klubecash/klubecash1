<?php
declare(strict_types=1);
namespace App\Services\WhatsApp;
use InvalidArgumentException;
final class WahaService
{
    public function __construct(private WahaConfig $config, private WahaHttpClient $http) {}
    /** @return array{available:bool,status:string} */
    public function connectionStatus(): array
    {
        $response = $this->request('GET', '/api/sessions/' . rawurlencode($this->config->session), null, true);
        $status = is_array($response) ? (string) ($response['status'] ?? 'UNKNOWN') : 'UNKNOWN';
        return ['available' => $status === 'WORKING', 'status' => $status];
    }
    /** @return array<string,mixed> */
    public function sendText(string $phone, string $text): array
    {
        $text = trim($text);
        if ($text === '') throw new InvalidArgumentException('A mensagem nao pode estar vazia.');
        $result = $this->request('POST', '/api/sendText', ['session' => $this->config->session, 'chatId' => self::normalizePhone($phone), 'text' => $text], false);
        return is_array($result) ? $result : [];
    }
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '00')) $digits = substr($digits, 2);
        if (in_array(strlen($digits), [10, 11], true)) {
            $digits = '55' . $digits;
        } elseif (!str_starts_with($digits, '55')) {
            throw new InvalidArgumentException('Telefone brasileiro deve conter DDI 55, DDD e numero valido.');
        }
        $national = substr($digits, 2);
        if (!in_array(strlen($national), [10, 11], true)) throw new InvalidArgumentException('Telefone brasileiro deve conter DDD e 10 ou 11 digitos.');
        $ddd = (int) substr($national, 0, 2);
        $invalidDdds = [20,23,25,26,29,30,36,39,40,50,52,56,57,58,59,60,70,72,76,78,80,90];
        if ($ddd < 11 || $ddd > 99 || in_array($ddd, $invalidDdds, true)) throw new InvalidArgumentException('DDD brasileiro invalido.');
        if (strlen($national) === 11 && $national[2] !== '9') throw new InvalidArgumentException('Celular brasileiro invalido.');
        if (strlen($national) === 10 && !in_array($national[2], ['2', '3', '4', '5'], true)) throw new InvalidArgumentException('Telefone fixo brasileiro invalido.');
        return $digits . '@c.us';
    }
    private function request(string $method, string $path, ?array $payload, bool $retryTransient): mixed
    {
        $body = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $attempts = $retryTransient ? 3 : 1;
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $this->http->request($method, $this->config->baseUrl . $path, ['X-Api-Key: ' . $this->config->apiKey, 'Content-Type: application/json', 'Accept: application/json'], $body, $this->config->timeoutSeconds);
                if ($response['status'] >= 200 && $response['status'] < 300) return $response['body'] === '' ? [] : json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
                $exception = $this->mapHttpError($response['status']);
                if (!$exception->transient || $attempt === $attempts) throw $exception;
            } catch (WahaException $exception) {
                if (!$retryTransient || !$exception->transient || $attempt === $attempts) throw $exception;
            }
            usleep(100000 * (2 ** ($attempt - 1)));
        }
        throw new WahaException('WAHA indisponivel.', 503, true);
    }
    private function mapHttpError(int $status): WahaException
    {
        return match ($status) {
            401 => new WahaException('Credenciais do WhatsApp rejeitadas.', 502),
            404 => new WahaException('Sessao do WhatsApp nao encontrada.', 502),
            422 => new WahaException('Mensagem ou destinatario invalido.', 422),
            429 => new WahaException('Limite temporario do WhatsApp atingido.', 503, true),
            default => $status >= 500 ? new WahaException('Servico de WhatsApp temporariamente indisponivel.', 503, true) : new WahaException('Falha ao comunicar com o WhatsApp.', 502),
        };
    }
}
