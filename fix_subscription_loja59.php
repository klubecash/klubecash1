<?php
/**
 * Script para criar assinatura para a Loja ID 59
 * Acesse: https://klubecash.com/fix_subscription_loja59.php
 */
require_once 'config/database.php';
require_once 'config/constants.php';

session_start();

// Verificar se é admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    die('Acesso negado. Apenas admins podem acessar.');
}

$db = (new Database())->getConnection();

echo "<h1>Criando Assinatura para Loja ID 59</h1>";

// 1. Ver dados da Loja ID 59
echo "<h2>Dados da Loja ID 59:</h2>";
$sqlLoja = "SELECT id, nome_fantasia, razao_social, email, cnpj FROM lojas WHERE id = 59";
$stmtLoja = $db->prepare($sqlLoja);
$stmtLoja->execute();
$loja59 = $stmtLoja->fetch(PDO::FETCH_ASSOC);

if (!$loja59) {
    die("<p style='color: red;'>❌ Loja ID 59 não encontrada!</p>");
}

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Nome Fantasia</th><th>Razão Social</th><th>Email</th><th>CNPJ</th></tr>";
echo "<tr>";
echo "<td>{$loja59['id']}</td>";
echo "<td>{$loja59['nome_fantasia']}</td>";
echo "<td>{$loja59['razao_social']}</td>";
echo "<td>{$loja59['email']}</td>";
echo "<td>{$loja59['cnpj']}</td>";
echo "</tr>";
echo "</table>";

// 2. Verificar se já tem assinatura
echo "<h2>Verificando Assinaturas Existentes:</h2>";
$sqlExist = "SELECT * FROM assinaturas WHERE loja_id = 59";
$stmtExist = $db->prepare($sqlExist);
$stmtExist->execute();
$existentes = $stmtExist->fetchAll(PDO::FETCH_ASSOC);

if (!empty($existentes)) {
    echo "<p style='color: orange;'>⚠️ Loja já tem " . count($existentes) . " assinatura(s):</p>";
    echo "<pre>";
    print_r($existentes);
    echo "</pre>";

    echo "<form method='POST'>";
    echo "<p><strong>Deseja remover as assinaturas antigas e criar uma nova?</strong></p>";
    echo "<button type='submit' name='remover' value='sim' style='padding: 10px 20px; background: #dc3545; color: white; border: none; cursor: pointer;'>Sim, remover e criar nova</button>";
    echo " ";
    echo "<a href='admin/assinaturas' style='padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; display: inline-block;'>Cancelar</a>";
    echo "</form>";

    if (isset($_POST['remover']) && $_POST['remover'] === 'sim') {
        // Remover faturas antigas
        $sqlDelFat = "DELETE FROM faturas WHERE assinatura_id IN (SELECT id FROM assinaturas WHERE loja_id = 59)";
        $db->exec($sqlDelFat);

        // Remover assinaturas antigas
        $sqlDelAssin = "DELETE FROM assinaturas WHERE loja_id = 59";
        $db->exec($sqlDelAssin);

        echo "<p style='color: green;'>✅ Assinaturas antigas removidas!</p>";
    } else {
        exit;
    }
}

// 3. Obter ID do plano Start
$planoId = $db->query("SELECT id FROM planos WHERE slug = 'klube-start' LIMIT 1")->fetchColumn();

if (!$planoId) {
    die("<p style='color: red;'>❌ Plano Start não encontrado! Execute seeds_planos.sql primeiro.</p>");
}

echo "<p style='color: green;'>✅ Plano Start encontrado (ID: {$planoId})</p>";

// 4. Criar assinatura trial
echo "<h2>Criando Assinatura Trial:</h2>";

try {
    $db->beginTransaction();

    // Criar assinatura
    $sqlAssin = "INSERT INTO assinaturas (
        tipo,
        loja_id,
        plano_id,
        status,
        ciclo,
        trial_end,
        current_period_start,
        current_period_end,
        next_invoice_date,
        gateway,
        created_at,
        updated_at
    ) VALUES (
        'loja',
        59,
        :plano_id,
        'trial',
        'monthly',
        DATE_ADD(CURDATE(), INTERVAL 7 DAY),
        CURDATE(),
        DATE_ADD(CURDATE(), INTERVAL 1 MONTH),
        DATE_ADD(CURDATE(), INTERVAL 7 DAY),
        'abacate',
        NOW(),
        NOW()
    )";

    $stmtAssin = $db->prepare($sqlAssin);
    $stmtAssin->execute(['plano_id' => $planoId]);
    $assinaturaId = $db->lastInsertId();

    echo "<p style='color: green;'>✅ Assinatura criada (ID: {$assinaturaId})</p>";

    // Criar fatura pendente
    $numeroFatura = 'INV-LOJA59-' . time();
    $sqlFatura = "INSERT INTO faturas (
        assinatura_id,
        numero,
        amount,
        currency,
        status,
        due_date,
        gateway,
        created_at,
        updated_at
    ) VALUES (
        :assinatura_id,
        :numero,
        149.00,
        'BRL',
        'pending',
        DATE_ADD(CURDATE(), INTERVAL 7 DAY),
        'abacate',
        NOW(),
        NOW()
    )";

    $stmtFatura = $db->prepare($sqlFatura);
    $stmtFatura->execute([
        'assinatura_id' => $assinaturaId,
        'numero' => $numeroFatura
    ]);
    $faturaId = $db->lastInsertId();

    echo "<p style='color: green;'>✅ Fatura pendente criada (ID: {$faturaId}, Número: {$numeroFatura})</p>";

    $db->commit();

    echo "<hr>";
    echo "<h2 style='color: green;'>✅ Sucesso!</h2>";
    echo "<p><strong>Assinatura criada para Loja ID 59</strong></p>";
    echo "<ul>";
    echo "<li>Status: Trial (7 dias)</li>";
    echo "<li>Plano: Klube Start (R$ 149,00/mês)</li>";
    echo "<li>Fatura pendente: {$numeroFatura}</li>";
    echo "<li>Vencimento: " . date('d/m/Y', strtotime('+7 days')) . "</li>";
    echo "</ul>";

    echo "<p><a href='admin/assinaturas' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; display: inline-block; margin-top: 20px;'>Ver Assinaturas</a></p>";

    echo "<hr>";
    echo "<h3>Próximos Passos:</h3>";
    echo "<ol>";
    echo "<li>Faça logout e login novamente como Loja ID 59</li>";
    echo "<li>Acesse o menu \"Meu Plano\"</li>";
    echo "<li>Verifique se a assinatura trial aparece</li>";
    echo "<li>Teste gerar PIX para pagamento</li>";
    echo "</ol>";

} catch (Exception $e) {
    $db->rollBack();
    echo "<p style='color: red;'>❌ Erro ao criar assinatura: " . $e->getMessage() . "</p>";
}
?>
