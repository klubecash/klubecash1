<?php
/**
 * Session Guardian - Sistema de proteção e restauração de sessão para funcionários
 * Este arquivo garante que as variáveis específicas de funcionários sejam sempre
 * preservadas, independentemente de interferências no fluxo web
 */

function ensureEmployeeSessionIntegrity() {
    // Primeiro, verificar se temos uma sessão ativa
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verificar se é um funcionário sem variáveis específicas definidas
    if (isset($_SESSION['user_type']) && 
        $_SESSION['user_type'] === 'funcionario' && 
        !isset($_SESSION['employee_subtype'])) {
        
        // Log para debug - isso nos ajudará a entender quando o problema ocorre
        error_log('Session Guardian: Detectando sessão de funcionário incompleta para user_id: ' . $_SESSION['user_id']);
        
        try {
            // Reconectar com o banco e recarregar dados do funcionário
            require_once __DIR__ . '/config/database.php';
            require_once __DIR__ . '/config/constants.php';
            
            $db = Database::getConnection();
            
            // Buscar dados completos do funcionário
            $stmt = $db->prepare("
                SELECT u.*, l.nome_fantasia as loja_nome, l.status as loja_status
                FROM usuarios u
                INNER JOIN lojas l ON u.loja_vinculada_id = l.id
                WHERE u.id = ? AND u.tipo = 'funcionario' AND u.status = 'ativo' AND l.status = 'aprovado'
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($funcionario) {
                // Restaurar todas as variáveis específicas de funcionário
                $_SESSION['employee_subtype'] = $funcionario['subtipo_funcionario'];
                $_SESSION['store_id'] = $funcionario['loja_vinculada_id'];
                $_SESSION['store_name'] = $funcionario['loja_nome'];
                
                // Definir permissões baseadas no subtipo
                switch($funcionario['subtipo_funcionario']) {
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
                
                // Marcar que a sessão foi restaurada
                $_SESSION['session_restored_at'] = date('Y-m-d H:i:s');
                
                // Log de sucesso
                error_log('Session Guardian: Sessão de funcionário restaurada com sucesso para: ' . $funcionario['subtipo_funcionario']);
                
                return true;
            } else {
                error_log('Session Guardian: Dados de funcionário não encontrados para user_id: ' . $_SESSION['user_id']);
                return false;
            }
            
        } catch (Exception $e) {
            error_log('Session Guardian: Erro ao restaurar sessão: ' . $e->getMessage());
            return false;
        }
    }
    
    // Se chegou até aqui, a sessão está íntegra ou não é de funcionário
    return true;
}

// Executar automaticamente quando o arquivo for incluído
ensureEmployeeSessionIntegrity();
?>