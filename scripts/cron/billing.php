<?php
/**
 * Cron Job: Billing - Geração Automática de Faturas
 *
 * Execução: Diariamente
 * Função: Criar faturas para assinaturas que precisam renovar
 *
 * Configurar no crontab:
 * 0 0 * * * php /caminho/para/klubecash1/scripts/cron/billing.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../controllers/SubscriptionController.php';

// Log de execução
function logCron($message, $data = []) {
    $logFile = __DIR__ . '/../../logs/cron_billing.log';
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
    logCron("========== INICIANDO BILLING CRON ==========");

    $db = (new Database())->getConnection();
    $subscriptionController = new SubscriptionController($db);

    // Buscar assinaturas que precisam gerar fatura
    // next_invoice_date <= hoje E status ativo/trial E não cancelada
    $today = date('Y-m-d');

    $sql = "SELECT a.*, p.preco_mensal, p.preco_anual, l.nome_loja, l.email
            FROM assinaturas a
            JOIN planos p ON a.plano_id = p.id
            LEFT JOIN lojas l ON a.loja_id = l.id
            WHERE a.status IN ('ativa', 'trial')
            AND a.next_invoice_date <= ?
            AND a.cancel_at IS NULL
            AND a.id NOT IN (
                -- Evitar duplicar faturas pendentes
                SELECT DISTINCT assinatura_id
                FROM faturas
                WHERE status = 'pending'
                AND due_date >= ?
            )
            ORDER BY a.next_invoice_date ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute([$today, $today]);
    $subscriptionsToInvoice = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalProcessed = count($subscriptionsToInvoice);
    $successCount = 0;
    $errorCount = 0;

    logCron("Assinaturas a processar: {$totalProcessed}");

    foreach ($subscriptionsToInvoice as $subscription) {
        $assinaturaId = $subscription['id'];
        $lojaName = $subscription['nome_loja'] ?? 'Loja #' . $subscription['loja_id'];

        logCron("Processando assinatura #{$assinaturaId} - {$lojaName}");

        // Verificar se ainda está em trial
        if ($subscription['status'] === 'trial' && !empty($subscription['trial_end'])) {
            if (strtotime($subscription['trial_end']) >= time()) {
                logCron("Assinatura #{$assinaturaId} ainda em trial até " . $subscription['trial_end'], ['skip' => true]);
                continue; // Pular, ainda no período de trial
            }
        }

        // Calcular valor da fatura
        $amount = ($subscription['ciclo'] === 'yearly')
            ? $subscription['preco_anual']
            : $subscription['preco_mensal'];

        // Data de vencimento (próximo período)
        $dueDate = $subscription['next_invoice_date'];

        // Gerar fatura
        $result = $subscriptionController->generateInvoiceForSubscription(
            $assinaturaId,
            $dueDate,
            $amount
        );

        if ($result['success']) {
            $successCount++;
            logCron("Fatura gerada com sucesso", [
                'assinatura_id' => $assinaturaId,
                'fatura_id' => $result['fatura_id'],
                'numero' => $result['numero'],
                'amount' => $amount,
                'due_date' => $dueDate
            ]);

            // Atualizar next_invoice_date da assinatura
            $nextInvoiceDate = ($subscription['ciclo'] === 'yearly')
                ? date('Y-m-d', strtotime($dueDate . ' +1 year'))
                : date('Y-m-d', strtotime($dueDate . ' +1 month'));

            $sqlUpdate = "UPDATE assinaturas SET next_invoice_date = ?, updated_at = NOW() WHERE id = ?";
            $stmtUpdate = $db->prepare($sqlUpdate);
            $stmtUpdate->execute([$nextInvoiceDate, $assinaturaId]);

            // TODO: Enviar email/notificação para lojista
            // Exemplo: Email::send($subscription['email'], 'Nova fatura disponível', ...);

        } else {
            $errorCount++;
            logCron("ERRO ao gerar fatura", [
                'assinatura_id' => $assinaturaId,
                'error' => $result['message']
            ]);
        }
    }

    logCron("========== BILLING CRON FINALIZADO ==========", [
        'total' => $totalProcessed,
        'success' => $successCount,
        'errors' => $errorCount
    ]);

    exit(0);

} catch (Exception $e) {
    logCron("ERRO FATAL no billing cron", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit(1);
}
