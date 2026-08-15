<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$indexes = [
    'uq_recuperacao_senha_token' =>
        'ALTER TABLE recuperacao_senha ADD UNIQUE KEY uq_recuperacao_senha_token (token)',
    'idx_recuperacao_senha_expiracao' =>
        'ALTER TABLE recuperacao_senha ADD KEY idx_recuperacao_senha_expiracao (data_expiracao)',
];

try {
    $db = Database::getConnection();
    $check = $db->prepare(
        'SELECT COUNT(*)
         FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = :table_name
           AND index_name = :index_name'
    );

    foreach ($indexes as $indexName => $statement) {
        $check->execute([
            ':table_name' => 'recuperacao_senha',
            ':index_name' => $indexName,
        ]);

        if ((int) $check->fetchColumn() === 0) {
            $db->exec($statement);
            echo "Criado: {$indexName}" . PHP_EOL;
        } else {
            echo "Já existente: {$indexName}" . PHP_EOL;
        }
    }

    foreach (array_keys($indexes) as $indexName) {
        $check->execute([
            ':table_name' => 'recuperacao_senha',
            ':index_name' => $indexName,
        ]);

        if ((int) $check->fetchColumn() !== 1) {
            throw new RuntimeException("Índice não confirmado: {$indexName}");
        }
    }

    echo 'OK: índices da recuperação de senha confirmados.' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Falha na migração: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
