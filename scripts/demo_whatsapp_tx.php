<?php
require_once __DIR__ . '/../config/whatsapp.php';
require_once __DIR__ . '/../utils/WhatsAppBot.php';

$argv = $_SERVER['argv'];
$script = array_shift($argv);

if (!WHATSAPP_ENABLED) {
    fwrite(STDERR, "WhatsApp integration is disabled. Check config/whatsapp.php or environment variables.\n");
    exit(1);
}

if (empty($argv)) {
    fwrite(STDERR, "Uso: php {$script} <telefone> [tipo] [extra]\n");
    fwrite(STDERR, "Tipos: transaction (padrão), cashback, media\n");
    fwrite(STDERR, "Exemplos:\n");
    fwrite(STDERR, "  php {$script} 5531999999999\n");
    fwrite(STDERR, "  php {$script} 5531999999999 cashback\n");
    fwrite(STDERR, "  php {$script} 5531999999999 media https://exemplo.com/imagem.jpg\n");
    exit(1);
}

$phone = $argv[0];
$type = $argv[1] ?? 'transaction';
$extra = $argv[2] ?? null;

switch ($type) {
    case 'cashback':
        $data = [
            'nome_loja' => 'Klube Cash - Loja Demo',
            'valor_cashback' => 15.75,
        ];
        $result = WhatsAppBot::sendCashbackReleasedNotification($phone, $data);
        break;

    case 'media':
        if (!$extra) {
            fwrite(STDERR, "Informe a URL ou base64 (prefixada com data:) da mídia após o tipo media.\n");
            exit(1);
        }

        $options = [];
        if (strpos($extra, 'data:') === 0) {
            $options['base64'] = substr($extra, strpos($extra, ',') + 1);
            $mediaSource = 'inline-base64';
        } else {
            $mediaSource = $extra;
        }

        $result = WhatsAppBot::sendMediaMessage($phone, $mediaSource, 'Confira sua nova compra na Klube Cash!', 'image', $options);
        break;

    case 'transaction':
    default:
        $data = [
            'nome_loja' => 'Klube Cash - Loja Demo',
            'valor_total' => 150.40,
            'valor_cashback' => 7.52,
            'codigo_transacao' => 'DEMO-' . date('Ymd-His'),
        ];
        $result = WhatsAppBot::sendNewTransactionNotification($phone, $data);
        break;
}

fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);

if (!$result['success']) {
    exit(1);
}
?>