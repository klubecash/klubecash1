<?php
/**
 * Script para executar migration de indexes
 * Execute: php run_indexes_migration.php
 */

try {
    $db = new PDO('mysql:host=localhost;dbname=klubecash', 'root', '123456');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "🔄 Executando migration de indexes...\n\n";

    $sql = file_get_contents(__DIR__ . '/add_subscription_indexes.sql');

    // Dividir por ponto e vírgula
    $commands = explode(';', $sql);

    $executed = 0;
    $skipped = 0;
    $errors = 0;

    foreach ($commands as $command) {
        $command = trim($command);

        // Ignorar comentários e linhas vazias
        if (empty($command) || strpos($command, '--') === 0) {
            continue;
        }

        try {
            $db->exec($command);
            $executed++;

            // Extrair nome do index
            if (preg_match('/ADD INDEX `([^`]+)`/', $command, $matches)) {
                echo "✓ Index '{$matches[1]}' criado com sucesso\n";
            } else {
                echo "✓ Comando executado\n";
            }

        } catch (PDOException $e) {
            // Se o erro for que o index já existe, ignorar
            if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                $skipped++;
                if (preg_match('/ADD INDEX `([^`]+)`/', $command, $matches)) {
                    echo "⚠ Index '{$matches[1]}' já existe (ignorado)\n";
                }
            } else {
                $errors++;
                echo "✗ Erro: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "\n📊 Resumo:\n";
    echo "  ✓ Executados: $executed\n";
    echo "  ⚠ Ignorados: $skipped\n";
    echo "  ✗ Erros: $errors\n";

    if ($errors === 0) {
        echo "\n✅ Migration de indexes concluída com sucesso!\n";
    } else {
        echo "\n⚠️ Migration concluída com erros. Verifique acima.\n";
    }

} catch (Exception $e) {
    echo "❌ Erro fatal: " . $e->getMessage() . "\n";
    exit(1);
}
