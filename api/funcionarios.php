<?php
// api/funcionarios.php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/StoreController.php';
require_once __DIR__ . '/../utils/PermissionManager.php';
require_once __DIR__ . '/../utils/Security.php';

session_start();

// Verificar se é lojista
if (!AuthController::isStoreOwner()) {
    http_response_code(403);
    echo json_encode(['status' => false, 'message' => 'Acesso restrito a lojistas.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
if ($method === 'POST' && !Security::validateCSRFToken((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
    http_response_code(419);
    echo json_encode(['status' => false, 'message' => 'Sessão de segurança expirada. Use a área atual da loja.']);
    exit;
}

switch ($method) {
    case 'GET':
        if ($action === 'get_permissions') {
            handleGetPermissions();
        } elseif ($action === 'list') {
            handleListEmployees();
        }
        break;
        
    case 'POST':
        if ($action === 'create') {
            handleCreateEmployee();
        } elseif ($action === 'update_permissions') {
            handleUpdatePermissions();
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['status' => false, 'message' => 'Método não permitido.']);
}

function handleGetPermissions() {
    $funcionarioId = (int)$_GET['id'];
    
    if (!$funcionarioId) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'ID do funcionário não fornecido.']);
        return;
    }
    
    $lojaId = (int) (AuthController::getStoreId() ?? 0);
    $db = Database::getConnection();
    $linked = $db->prepare("SELECT 1 FROM store_user_memberships WHERE user_id=? AND store_id=? AND status='active'");
    $linked->execute([$funcionarioId, $lojaId]);
    if (!$linked->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['status' => false, 'message' => 'Funcionário não pertence à filial ativa.']);
        return;
    }
    $permissions = PermissionManager::getUserPermissions($funcionarioId);
    
    echo json_encode([
        'status' => true,
        'permissions' => $permissions['permissions'] ?? []
    ]);
}

function handleListEmployees() {
    echo json_encode(StoreController::getEmployees([], max(1, (int) ($_GET['page'] ?? 1))));
}

function handleCreateEmployee() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validações básicas
    if (empty($data['nome']) || empty($data['email']) || empty($data['senha']) || empty($data['subtipo_funcionario'])) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Dados obrigatórios não fornecidos.']);
        return;
    }
    
    $created = StoreController::createEmployee($data);
    echo json_encode($created);
    return;
    try {
        $db = Database::getConnection();
        
        // Verificar se email já existe
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$data['email']]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => false, 'message' => 'Este e-mail já está cadastrado.']);
            return;
        }
        
        // Criar funcionário
        $senhaHash = password_hash($data['senha'], PASSWORD_DEFAULT);
        $lojaId = (int) (AuthController::getStoreId() ?? 0);
        
        $insertStmt = $db->prepare("
            INSERT INTO usuarios (nome, email, telefone, senha_hash, tipo, subtipo_funcionario, loja_vinculada_id, status, data_criacao) 
            VALUES (?, ?, ?, ?, 'funcionario', ?, ?, 'ativo', NOW())
        ");
        
        $success = $insertStmt->execute([
            $data['nome'],
            $data['email'],
            $data['telefone'] ?? '',
            $senhaHash,
            $data['subtipo_funcionario'],
            $lojaId
        ]);
        
        if ($success) {
            $funcionarioId = $db->lastInsertId();
            $role = in_array((string) $data['subtipo_funcionario'], ['gerente','financeiro','vendedor'], true)
                ? (string) $data['subtipo_funcionario'] : 'vendedor';
            $db->prepare("INSERT INTO store_user_memberships(user_id,store_id,role,status,accepted_at)
                VALUES(?,?,?,'active',NOW())")->execute([$funcionarioId, $lojaId, $role]);
            
            // Aplicar permissões padrão
            PermissionManager::applyDefaultPermissions($funcionarioId, $lojaId, $data['subtipo_funcionario']);
            
            echo json_encode(['status' => true, 'message' => 'Funcionário criado com sucesso!']);
        } else {
            echo json_encode(['status' => false, 'message' => 'Erro ao criar funcionário.']);
        }
        
    } catch (PDOException $e) {
        error_log('Erro ao criar funcionário: ' . $e->getMessage());
        echo json_encode(['status' => false, 'message' => 'Erro interno do servidor.']);
    }
}

function handleUpdatePermissions() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $funcionarioId = (int)$data['funcionario_id'];
    $permissions = $data['permissions'] ?? [];
    $lojaId = (int) (AuthController::getStoreId() ?? 0);
    $db = Database::getConnection();
    $linked = $db->prepare("SELECT 1 FROM store_user_memberships WHERE user_id=? AND store_id=? AND status='active'");
    $linked->execute([$funcionarioId, $lojaId]);
    if (!$linked->fetchColumn()) {
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Funcionário não vinculado à filial ativa.']);
        return;
    }
    
    if (!$funcionarioId) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'ID do funcionário não fornecido.']);
        return;
    }
    
    try {
        // Atualizar todas as permissões
        foreach ($permissions as $modulo => $acoes) {
            foreach ($acoes as $acao => $permitido) {
                PermissionManager::setPermission($funcionarioId, $lojaId, $modulo, $acao, $permitido);
            }
        }
        
        echo json_encode(['status' => true, 'message' => 'Permissões atualizadas com sucesso!']);
        
    } catch (Exception $e) {
        error_log('Erro ao atualizar permissões: ' . $e->getMessage());
        echo json_encode(['status' => false, 'message' => 'Erro ao atualizar permissões.']);
    }
}
?>
