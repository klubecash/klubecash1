<?php
/**
 * Script para criar tabela de histórico de assinaturas
 * Execute: php run_history_table_migration.php
 */

try {
    $db = new PDO('mysql:host=localhost;dbname=klubecash', 'root', '123456');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "🔄 Criando tabela de histórico...\n\n";

    $sql = file_get_contents(__DIR__ . '/create_subscription_history_table.sql');

    try {
        $db->exec($sql);
        echo "✓ Tabela 'assinaturas_historico' criada com sucesso\n";
        echo "\n✅ Migration concluída!\n";

    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "⚠ Tabela 'assinaturas_historico' já existe\n";
            echo "\n✅ Migration já foi executada anteriormente\n";
        } else {
            echo "✗ Erro: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

} catch (Exception $e) {
    echo "❌ Erro fatal: " . $e->getMessage() . "\n";
    exit(1);
}
