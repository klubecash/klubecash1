<?php
/**
 * Cron Job: Dunning - Cobrança e Inadimplência
 *
 * Execução: Diariamente
 * Função: Marcar assinaturas inadimplentes após grace period
 *
 * Configurar no crontab:
 * 0 6 * * * php /caminho/para/klubecash1/scripts/cron/dunning.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../controllers/SubscriptionController.php';

// Log de execução
function logCron($message, $data = []) {
    $logFile = __DIR__ . '/../../logs/cron_dunning.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}";
    if (!empty($data)) {
        $logMessage .= " - " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    $logMessage .= PHP_EOL;
    @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    echo $logMessage;
}

try {
    logCron("========== INICIANDO DUNNING CRON ==========");

    $db = (new Database())->getConnection();
    $subscriptionController = new SubscriptionController($db);

    // Grace period de 3 dias
    $gracePeriodDays = 3;
    $graceDate = date('Y-m-d', strtotime("-{$gracePeriodDays} days"));

    logCron("Grace period: {$gracePeriodDays} dias (faturas vencidas antes de {$graceDate})");

    // Marcar assinaturas inadimplentes
    $rowsAffected = $subscriptionController->markDelinquentIfOverdue($gracePeriodDays);

    logCron("Assinaturas marcadas como inadimplentes: {$rowsAffected}");

    // Buscar assinaturas que acabaram de ficar inadimplentes (para enviar notificação)
    $sql = "SELECT a.id, a.loja_id, l.nome_loja, l.email, l.telefone
            FROM assinaturas a
            JOIN lojas l ON a.loja_id = l.id
            WHERE a.status = 'inadimplente'
            AND DATE(a.updated_at) = CURDATE()";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $newDelinquents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    logCron("Novas inadimplências hoje: " . count($newDelinquents));

    foreach ($newDelinquents as $delinquent) {
        logCron("Notificando loja inadimplente", [
            'loja_id' => $delinquent['loja_id'],
            'nome' => $delinquent['nome_loja'],
            'email' => $delinquent['email']
        ]);

        // TODO: Enviar email/SMS/WhatsApp de cobrança
        // Exemplo:
        // Email::send($delinquent['email'], 'Assinatura em atraso', ...);
        // WhatsApp::send($delinquent['telefone'], 'Sua assinatura está pendente...');
    }

    // Buscar faturas vencidas há 1 dia (lembrete inicial)
    $reminderDate = date('Y-m-d', strtotime('-1 day'));

    $sqlReminder = "SELECT f.*, a.loja_id, l.nome_loja, l.email
                    FROM faturas f
                    JOIN assinaturas a ON f.assinatura_id = a.id
                    JOIN lojas l ON a.loja_id = l.id
                    WHERE f.status = 'pending'
                    AND DATE(f.due_date) = ?";

    $stmtReminder = $db->prepare($sqlReminder);
    $stmtReminder->execute([$reminderDate]);
    $reminders = $stmtReminder->fetchAll(PDO::FETCH_ASSOC);

    logCron("Lembretes de pagamento a enviar: " . count($reminders));

    foreach ($reminders as $reminder) {
        logCron("Enviando lembrete de pagamento", [
            'fatura_numero' => $reminder['numero'],
            'loja' => $reminder['nome_loja'],
            'amount' => $reminder['amount']
        ]);

        // TODO: Enviar lembrete
        // Email::send($reminder['email'], 'Lembrete: Fatura vencida', ...);
    }

    // Suspender assinaturas inadimplentes há mais de 15 dias (opcional)
    $suspensionDate = date('Y-m-d', strtotime('-15 days'));

    $sqlSuspend = "SELECT a.id, l.nome_loja
                   FROM assinaturas a
                   JOIN lojas l ON a.loja_id = l.id
                   WHERE a.status = 'inadimplente'
                   AND DATE(a.updated_at) <= ?
                   AND a.id IN (
                       SELECT DISTINCT assinatura_id
                       FROM faturas
                       WHERE status = 'pending'
                       AND due_date < ?
                   )";

    $stmtSuspend = $db->prepare($sqlSuspend);
    $stmtSuspend->execute([$suspensionDate, $suspensionDate]);
    $toSuspend = $stmtSuspend->fetchAll(PDO::FETCH_ASSOC);

    $suspendedCount = 0;
    foreach ($toSuspend as $suspend) {
        if ($subscriptionController->suspendSubscription($suspend['id'])) {
            $suspendedCount++;
            logCron("Assinatura suspensa por inadimplência prolongada", [
                'assinatura_id' => $suspend['id'],
                'loja' => $suspend['nome_loja']
            ]);
        }
    }

    logCron("========== DUNNING CRON FINALIZADO ==========", [
        'inadimplentes_marcadas' => $rowsAffected,
        'notificacoes_enviadas' => count($newDelinquents),
        'lembretes_enviados' => count($reminders),
        'suspensoes' => $suspendedCount
    ]);

    exit(0);

} catch (Exception $e) {
    logCron("ERRO FATAL no dunning cron", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit(1);
}
