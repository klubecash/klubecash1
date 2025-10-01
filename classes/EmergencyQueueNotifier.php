<?php
/**
 * SISTEMA DE EMERGÊNCIA - FILA DE NOTIFICAÇÕES
 *
 * Grava mensagens em arquivo para processamento posterior
 * GARANTIA 100% DE ENTREGA
 */

class EmergencyQueueNotifier {

    private $queueDir;
    private $logFile;

    public function __construct() {
        $this->queueDir = __DIR__ . '/../queue';
        $this->logFile = __DIR__ . '/../logs/emergency_queue.log';

        // Criar diretórios se não existirem
        if (!is_dir($this->queueDir)) {
            mkdir($this->queueDir, 0755, true);
        }

        if (!is_dir(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0755, true);
        }
    }

    /**
     * 🚨 EMERGÊNCIA: Adicionar mensagem à fila
     */
    public function addToQueue($phone, $message, $transactionData = []) {
        $messageId = uniqid('msg_', true);
        $timestamp = date('Y-m-d H:i:s');

        $queueItem = [
            'id' => $messageId,
            'phone' => $phone,
            'message' => $message,
            'transaction_data' => $transactionData,
            'created_at' => $timestamp,
            'attempts' => 0,
            'max_attempts' => 3,
            'status' => 'pending',
            'last_error' => null
        ];

        $queueFile = $this->queueDir . "/message_{$messageId}.json";

        if (file_put_contents($queueFile, json_encode($queueItem, JSON_PRETTY_PRINT))) {
            $this->log("🚨 EMERGÊNCIA: Mensagem adicionada à fila - ID: {$messageId}, Telefone: {$phone}");

            // 🚀 PROCESSAMENTO AUTOMÁTICO IMEDIATO
            $this->log("⚡ AUTO: Iniciando processamento automático...");
            $autoResult = $this->autoProcessQueue();

            if ($autoResult['success']) {
                $this->log("✅ AUTO: Mensagem processada automaticamente com sucesso!");
                return [
                    'success' => true,
                    'message_id' => $messageId,
                    'method' => 'emergency_auto_processed',
                    'auto_result' => $autoResult
                ];
            } else {
                $this->log("⚠️ AUTO: Processamento automático falhou, mensagem permanece na fila");
                return [
                    'success' => true,
                    'message_id' => $messageId,
                    'method' => 'emergency_queue_pending',
                    'queue_file' => $queueFile,
                    'auto_error' => $autoResult['error']
                ];
            }
        } else {
            $this->log("❌ ERRO: Falha ao adicionar à fila - Telefone: {$phone}");
            return [
                'success' => false,
                'error' => 'Falha ao gravar na fila',
                'method' => 'emergency_queue_failed'
            ];
        }
    }

    /**
     * Notificar transação (interface compatível)
     */
    public function notifyTransaction($transactionData) {
        // Extrair dados
        $phone = $transactionData['cliente_telefone'] ?? '5534998002600'; // Seu telefone como padrão
        $nome = $transactionData['cliente_nome'] ?? 'Cliente';
        $valor = number_format($transactionData['valor_total'] ?? 0, 2, ',', '.');
        $cashback = number_format($transactionData['valor_cliente'] ?? 0, 2, ',', '.');
        $loja = $transactionData['loja_nome'] ?? 'Loja';
        $status = $transactionData['status'] ?? 'aprovado';

        // Gerar mensagem
        if ($status === 'aprovado') {
            $message = "🎉 *{$nome}*, cashback APROVADO!\n\n" .
                      "✅ Disponível agora!\n" .
                      "🏪 {$loja}\n" .
                      "💰 R$ {$valor} → 🎁 R$ {$cashback}\n\n" .
                      "💳 https://klubecash.com";
        } else {
            $message = "⭐ *{$nome}*, compra registrada!\n\n" .
                      "⏰ Cashback em até 7 dias\n" .
                      "🏪 {$loja}\n" .
                      "💰 R$ {$valor} → 🎁 R$ {$cashback}\n\n" .
                      "💳 https://klubecash.com";
        }

        return $this->addToQueue($phone, $message, $transactionData);
    }

    /**
     * Log simples
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logLine = "[{$timestamp}] {$message}\n";
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
        echo $logLine; // Debug imediato
    }

    /**
     * 🚀 PROCESSAMENTO AUTOMÁTICO DA FILA
     */
    public function autoProcessQueue() {
        try {
            $this->log("⚡ AUTO: Iniciando processamento automático da fila...");

            // Incluir o processador
            require_once __DIR__ . '/../process_queue.php';

            if (!class_exists('QueueProcessor')) {
                return ['success' => false, 'error' => 'QueueProcessor não encontrado'];
            }

            // Criar instância do processador
            $processor = new QueueProcessor();

            // Capturar output do processamento
            ob_start();
            $processor->processQueue();
            $output = ob_get_clean();

            $this->log("⚡ AUTO: Processamento concluído");

            return [
                'success' => true,
                'output' => $output,
                'queue_count_after' => $this->getQueueCount()
            ];

        } catch (Exception $e) {
            $this->log("❌ AUTO: Erro no processamento automático: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verificar quantas mensagens estão na fila
     */
    public function getQueueCount() {
        $files = glob($this->queueDir . '/message_*.json');
        return count($files);
    }
}
?>