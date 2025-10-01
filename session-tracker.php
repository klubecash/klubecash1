<?php
/**
 * Sistema de Rastreamento de Sessão - Monitora mudanças na sessão
 * Este arquivo nos ajudará a entender exatamente quando e onde
 * as variáveis de funcionário estão sendo perdidas
 */

// Iniciar a sessão se ainda não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Função para registrar o estado atual da sessão
function logSessionState($context = 'unknown') {
    $logFile = __DIR__ . '/session_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $sessionData = $_SESSION;
    
    // Criar uma entrada de log detalhada
    $logEntry = "\n=== SESSION LOG ===\n";
    $logEntry .= "Timestamp: {$timestamp}\n";
    $logEntry .= "Context: {$context}\n";
    $logEntry .= "User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
    $logEntry .= "User Type: " . ($_SESSION['user_type'] ?? 'NOT SET') . "\n";
    $logEntry .= "Employee Subtype: " . ($_SESSION['employee_subtype'] ?? 'NOT SET') . "\n";
    $logEntry .= "Store ID: " . ($_SESSION['store_id'] ?? 'NOT SET') . "\n";
    $logEntry .= "Store Name: " . ($_SESSION['store_name'] ?? 'NOT SET') . "\n";
    $logEntry .= "Full Session Data: " . print_r($sessionData, true) . "\n";
    $logEntry .= "===================\n";
    
    // Escrever no arquivo de log
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Registrar o estado atual quando este arquivo for incluído
logSessionState('session-tracker inclusion');

echo "<h1>Rastreador de Sessão Ativo</h1>";
echo "<p>O sistema está monitorando mudanças na sessão.</p>";
echo "<p>Estado atual da sessão:</p>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Verificar se é funcionário sem variáveis específicas
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'funcionario') {
    if (!isset($_SESSION['employee_subtype'])) {
        echo "<p style='color: red;'><strong>PROBLEMA DETECTADO:</strong> Funcionário sem variáveis específicas!</p>";
        
        // Tentar corrigir imediatamente
        try {
            require_once __DIR__ . '/config/database.php';
            
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT u.*, l.nome_fantasia as loja_nome
                FROM usuarios u
                INNER JOIN lojas l ON u.loja_vinculada_id = l.id
                WHERE u.id = ? AND u.tipo = 'funcionario'
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($userData) {
                // Forçar definição das variáveis
                $_SESSION['employee_subtype'] = $userData['subtipo_funcionario'];
                $_SESSION['store_id'] = $userData['loja_vinculada_id'];
                $_SESSION['store_name'] = $userData['loja_nome'];
                
                // Definir permissões
                switch($userData['subtipo_funcionario']) {
                    case 'gerente':
                        $_SESSION['employee_permissions'] = ['dashboard', 'transacoes', 'funcionarios', 'relatorios'];
                        break;
                    case 'financeiro':
                        $_SESSION['employee_permissions'] = ['dashboard', 'comissoes', 'pagamentos', 'relatorios'];
                        break;
                    case 'vendedor':
                        $_SESSION['employee_permissions'] = ['dashboard', 'transacoes'];
                        break;
                    default:
                        $_SESSION['employee_permissions'] = ['dashboard'];
                }
                
                $_SESSION['session_fixed_by_tracker'] = date('Y-m-d H:i:s');
                
                logSessionState('session fixed by tracker');
                
                echo "<p style='color: green;'><strong>CORRIGIDO:</strong> Variáveis de funcionário restauradas!</p>";
                echo "<p>Sessão corrigida:</p>";
                echo "<pre>";
                print_r($_SESSION);
                echo "</pre>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>Erro ao corrigir sessão: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: green;'><strong>OK:</strong> Variáveis de funcionário estão presentes!</p>";
    }
}
?>