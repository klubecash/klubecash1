<?php
/**
 * Script de Debug - Verificar Assinaturas
 * Acesse: https://klubecash.com/debug_subscription.php
 */
require_once 'config/database.php';
require_once 'config/constants.php';

session_start();

// Verificar se é admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    die('Acesso negado. Apenas admins podem acessar este debug.');
}

$db = (new Database())->getConnection();

// Buscar todas as assinaturas
$sql = "SELECT a.*, l.nome_fantasia, l.email, p.nome as plano_nome
        FROM assinaturas a
        LEFT JOIN lojas l ON a.loja_id = l.id
        LEFT JOIN planos p ON a.plano_id = p.id
        ORDER BY a.created_at DESC
        LIMIT 20";
$stmt = $db->prepare($sql);
$stmt->execute();
$assinaturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h1>Debug - Assinaturas</h1>";
echo "<p>Total de assinaturas encontradas: " . count($assinaturas) . "</p>";

if (empty($assinaturas)) {
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<strong>⚠️ Nenhuma assinatura encontrada no banco!</strong><br>";
    echo "Execute o SQL <code>database/test_subscription_loja34.sql</code> para criar dados de teste.";
    echo "</div>";
} else {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr>";
    echo "<th>ID</th>";
    echo "<th>Loja ID</th>";
    echo "<th>Loja Nome</th>";
    echo "<th>Email</th>";
    echo "<th>Plano</th>";
    echo "<th>Status</th>";
    echo "<th>Trial End</th>";
    echo "<th>Criado Em</th>";
    echo "</tr>";

    foreach ($assinaturas as $assinatura) {
        $statusColor = [
            'trial' => '#ffc107',
            'ativa' => '#28a745',
            'active' => '#28a745',
            'inadimplente' => '#dc3545',
            'suspensa' => '#6c757d',
            'cancelada' => '#6c757d'
        ];
        $color = $statusColor[$assinatura['status']] ?? '#6c757d';

        echo "<tr>";
        echo "<td>{$assinatura['id']}</td>";
        echo "<td>{$assinatura['loja_id']}</td>";
        echo "<td>" . htmlspecialchars($assinatura['nome_fantasia'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($assinatura['email'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($assinatura['plano_nome'] ?? 'N/A') . "</td>";
        echo "<td style='background: {$color}; color: white; font-weight: bold;'>{$assinatura['status']}</td>";
        echo "<td>" . ($assinatura['trial_end'] ? date('d/m/Y', strtotime($assinatura['trial_end'])) : '-') . "</td>";
        echo "<td>" . date('d/m/Y H:i', strtotime($assinatura['created_at'])) . "</td>";
        echo "</tr>";
    }

    echo "</table>";
}

// Buscar faturas pendentes
echo "<h2 style='margin-top: 40px;'>Faturas Pendentes</h2>";
$sqlFaturas = "SELECT f.*, a.loja_id, l.nome_fantasia
               FROM faturas f
               JOIN assinaturas a ON f.assinatura_id = a.id
               JOIN lojas l ON a.loja_id = l.id
               WHERE f.status = 'pending'
               ORDER BY f.due_date ASC";
$stmtFaturas = $db->prepare($sqlFaturas);
$stmtFaturas->execute();
$faturas = $stmtFaturas->fetchAll(PDO::FETCH_ASSOC);

if (empty($faturas)) {
    echo "<p>Nenhuma fatura pendente.</p>";
} else {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr>";
    echo "<th>ID</th>";
    echo "<th>Loja</th>";
    echo "<th>Número</th>";
    echo "<th>Valor</th>";
    echo "<th>Vencimento</th>";
    echo "<th>Status</th>";
    echo "</tr>";

    foreach ($faturas as $fatura) {
        $isOverdue = strtotime($fatura['due_date']) < time();
        $bgColor = $isOverdue ? '#fff5f5' : 'white';

        echo "<tr style='background: {$bgColor};'>";
        echo "<td>{$fatura['id']}</td>";
        echo "<td>" . htmlspecialchars($fatura['nome_fantasia']) . " (ID: {$fatura['loja_id']})</td>";
        echo "<td>{$fatura['numero']}</td>";
        echo "<td>R$ " . number_format($fatura['amount'], 2, ',', '.') . "</td>";
        echo "<td>" . date('d/m/Y', strtotime($fatura['due_date']));
        if ($isOverdue) echo " <strong style='color: red;'>(VENCIDA)</strong>";
        echo "</td>";
        echo "<td>{$fatura['status']}</td>";
        echo "</tr>";
    }

    echo "</table>";
}

// Buscar dados da sessão se tiver loja logada
echo "<h2 style='margin-top: 40px;'>Debug de Sessão</h2>";
if (isset($_GET['loja_id'])) {
    $lojaId = (int)$_GET['loja_id'];
    echo "<p>Buscando assinatura para Loja ID: <strong>{$lojaId}</strong></p>";

    $sqlDebug = "SELECT * FROM assinaturas
                 WHERE loja_id = ? AND tipo = 'loja'
                 AND status NOT IN ('cancelada')
                 ORDER BY created_at DESC LIMIT 1";
    $stmtDebug = $db->prepare($sqlDebug);
    $stmtDebug->execute([$lojaId]);
    $resultado = $stmtDebug->fetch(PDO::FETCH_ASSOC);

    echo "<pre>";
    print_r($resultado ?: 'Nenhuma assinatura encontrada');
    echo "</pre>";
}

echo "<hr>";
echo "<p><a href='?'>Recarregar</a> | ";
echo "<a href='admin/assinaturas'>Ver Assinaturas</a></p>";
?>
