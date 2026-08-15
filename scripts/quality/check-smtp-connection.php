<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../utils/Email.php';

$result = Email::testConnection();

if (!empty($result['status'])) {
    echo 'OK: SMTP autenticado com sucesso.' . PHP_EOL;
    exit(0);
}

fwrite(STDERR, 'ERRO: ' . ($result['message'] ?? 'Falha desconhecida no SMTP.') . PHP_EOL);
exit(1);
