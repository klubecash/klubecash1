<?php
/**
 * Script de Debug - Verificar funcionalidade SENAT
 * Testa se o lojista tem senat='sim' e se a atualização do cliente funciona
 */

require_once 'config/database.php';
require_once 'config/constants.php';

echo "<h1>🔍 Debug - Funcionalidade SENAT</h1>";
echo "<hr>";

try {
    $db = Database::getConnection();

    // ========================================
    // 1. VERIFICAR ESTRUTURA DA TABELA
    // ========================================
    echo "<h2>1️⃣ Verificando estrutura da tabela usuarios</h2>";

    $columnsQuery = $db->query("SHOW COLUMNS FROM usuarios LIKE 'senat'");
    $senatColumn = $columnsQuery->fetch(PDO::FETCH_ASSOC);

    if ($senatColumn) {
        echo "✅ Coluna 'senat' existe na tabela usuarios<br>";
        echo "Tipo: {$senatColumn['Type']}<br>";
        echo "Nulo: {$senatColumn['Null']}<br>";
        echo "Default: {$senatColumn['Default']}<br>";
    } else {
        echo "❌ Coluna 'senat' NÃO existe na tabela usuarios<br>";
        echo "<strong>PROBLEMA ENCONTRADO: A coluna senat não existe!</strong><br>";
    }

    echo "<hr>";

    // ========================================
    // 2. LISTAR LOJISTAS COM SENAT='SIM'
    // ========================================
    echo "<h2>2️⃣ Lojistas com senat='sim'</h2>";

    $lojistasQuery = $db->query("
        SELECT u.id, u.nome, u.email, u.tipo, u.senat, l.id as loja_id, l.nome_fantasia
        FROM usuarios u
        LEFT JOIN lojas l ON l.usuario_id = u.id
        WHERE u.tipo = 'loja' AND u.senat = 'sim'
    ");

    $lojistas = $lojistasQuery->fetchAll(PDO::FETCH_ASSOC);

    if (count($lojistas) > 0) {
        echo "✅ Encontrados " . count($lojistas) . " lojista(s) com senat='sim':<br><br>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>User ID</th><th>Nome</th><th>Email</th><th>Loja ID</th><th>Nome Fantasia</th><th>Senat</th></tr>";
        foreach ($lojistas as $lojista) {
            echo "<tr>";
            echo "<td>{$lojista['id']}</td>";
            echo "<td>{$lojista['nome']}</td>";
            echo "<td>{$lojista['email']}</td>";
            echo "<td>" . ($lojista['loja_id'] ?? 'N/A') . "</td>";
            echo "<td>" . ($lojista['nome_fantasia'] ?? 'N/A') . "</td>";
            echo "<td><strong>{$lojista['senat']}</strong></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "⚠️ Nenhum lojista encontrado com senat='sim'<br>";
    }

    echo "<hr>";

    // ========================================
    // 3. LISTAR ÚLTIMAS TRANSAÇÕES
    // ========================================
    echo "<h2>3️⃣ Últimas 10 transações registradas</h2>";

    $transacoesQuery = $db->query("
        SELECT
            t.id,
            t.usuario_id,
            t.loja_id,
            t.valor_total,
            t.data_transacao,
            u.nome as cliente_nome,
            u.senat as cliente_senat,
            l.nome_fantasia as loja_nome,
            u_loja.senat as lojista_senat
        FROM transacoes_cashback t
        INNER JOIN usuarios u ON t.usuario_id = u.id
        INNER JOIN lojas l ON t.loja_id = l.id
        INNER JOIN usuarios u_loja ON l.usuario_id = u_loja.id
        ORDER BY t.data_transacao DESC
        LIMIT 10
    ");

    $transacoes = $transacoesQuery->fetchAll(PDO::FETCH_ASSOC);

    if (count($transacoes) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Trans ID</th><th>Data</th><th>Cliente</th><th>Cliente Senat</th><th>Loja</th><th>Lojista Senat</th><th>Valor</th><th>Status</th></tr>";
        foreach ($transacoes as $t) {
            $statusIcon = ($t['lojista_senat'] === 'sim' && $t['cliente_senat'] !== 'sim') ? '❌' : '✅';
            echo "<tr>";
            echo "<td>{$t['id']}</td>";
            echo "<td>" . date('d/m/Y H:i', strtotime($t['data_transacao'])) . "</td>";
            echo "<td>{$t['cliente_nome']}</td>";
            echo "<td><strong>{$t['cliente_senat']}</strong></td>";
            echo "<td>{$t['loja_nome']}</td>";
            echo "<td><strong>{$t['lojista_senat']}</strong></td>";
            echo "<td>R$ " . number_format($t['valor_total'], 2, ',', '.') . "</td>";
            echo "<td>{$statusIcon}</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<br><small>❌ = Cliente deveria ter senat='sim' mas não tem<br>✅ = OK</small>";
    } else {
        echo "⚠️ Nenhuma transação encontrada<br>";
    }

    echo "<hr>";

    // ========================================
    // 4. SIMULAR ATUALIZAÇÃO
    // ========================================
    echo "<h2>4️⃣ Teste de Query de Atualização</h2>";

    // Pegar um cliente qualquer que não seja senat
    $clienteTesteQuery = $db->query("
        SELECT id, nome, email, senat
        FROM usuarios
        WHERE tipo = 'cliente' AND (senat IS NULL OR senat = 'nao' OR senat = '')
        LIMIT 1
    ");
    $clienteTeste = $clienteTesteQuery->fetch(PDO::FETCH_ASSOC);

    if ($clienteTeste) {
        echo "Cliente de teste encontrado:<br>";
        echo "ID: {$clienteTeste['id']}<br>";
        echo "Nome: {$clienteTeste['nome']}<br>";
        echo "Senat atual: " . ($clienteTeste['senat'] ?? 'NULL') . "<br><br>";

        echo "Query que seria executada:<br>";
        echo "<code>UPDATE usuarios SET senat = 'sim' WHERE id = {$clienteTeste['id']}</code><br><br>";

        echo "<strong>⚠️ Não vou executar a atualização neste script de teste</strong><br>";
    } else {
        echo "⚠️ Nenhum cliente disponível para teste<br>";
    }

    echo "<hr>";

    // ========================================
    // 5. VERIFICAR LOGS
    // ========================================
    echo "<h2>5️⃣ Últimas linhas do log (se existir)</h2>";

    $logFile = __DIR__ . '/logs/app.log';
    $phpLogFile = __DIR__ . '/php_errors.log';

    if (file_exists($logFile)) {
        $lines = file($logFile);
        $lastLines = array_slice($lines, -20);
        echo "<pre style='background: #f5f5f5; padding: 10px; overflow-x: auto;'>";
        foreach ($lastLines as $line) {
            if (stripos($line, 'SENAT') !== false) {
                echo "<strong style='color: blue;'>$line</strong>";
            } else {
                echo htmlspecialchars($line);
            }
        }
        echo "</pre>";
    } else {
        echo "⚠️ Arquivo de log não encontrado em: $logFile<br>";
    }

    echo "<hr>";

    // ========================================
    // 6. DIAGNÓSTICO FINAL
    // ========================================
    echo "<h2>📋 Diagnóstico Final</h2>";

    $problemas = [];

    if (!$senatColumn) {
        $problemas[] = "❌ Coluna 'senat' não existe na tabela usuarios";
    }

    if (count($lojistas) === 0) {
        $problemas[] = "⚠️ Nenhum lojista com senat='sim' encontrado";
    }

    if (count($problemas) > 0) {
        echo "<div style='background: #ffebee; padding: 15px; border-left: 4px solid #f44336;'>";
        echo "<strong>Problemas encontrados:</strong><br>";
        foreach ($problemas as $problema) {
            echo "$problema<br>";
        }
        echo "</div>";
    } else {
        echo "<div style='background: #e8f5e9; padding: 15px; border-left: 4px solid #4caf50;'>";
        echo "✅ Estrutura básica está OK<br>";
        echo "✅ Existem lojistas com senat='sim'<br>";
        echo "<br><strong>Próximos passos:</strong><br>";
        echo "1. Verificar os logs do PHP para mensagens 'SENAT UPDATE'<br>";
        echo "2. Fazer uma transação teste com um lojista senat='sim'<br>";
        echo "3. Verificar se o cliente foi atualizado<br>";
        echo "</div>";
    }

} catch (Exception $e) {
    echo "<div style='background: #ffebee; padding: 15px; border-left: 4px solid #f44336;'>";
    echo "<strong>❌ ERRO:</strong><br>";
    echo $e->getMessage();
    echo "</div>";
}

echo "<hr>";
echo "<p><small>Script executado em: " . date('d/m/Y H:i:s') . "</small></p>";
?>
