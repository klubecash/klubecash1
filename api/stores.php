<?php
// api/stores.php
// API para gerenciar lojas parceiras do sistema Klube Cash

// Configurações iniciais
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Se for requisição OPTIONS (preflight), encerra a execução
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Incluir arquivos necessários
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/StoreController.php';
require_once __DIR__ . '/../controllers/AdminController.php';
require_once __DIR__ . '/../utils/Security.php';
require_once __DIR__ . '/../utils/Validator.php';

// Função para validar token JWT
function validateToken() {
    // Obter token do cabeçalho Authorization
    $headers = getallheaders();
    $auth = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
    if (empty($auth) || !preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
        http_response_code(401);
        echo json_encode(['status' => false, 'message' => 'Token de autenticação não fornecido']);
        exit;
    }
    
    $token = $matches[1];
    
    // Validar token
    $decoded = Security::validateJWT($token);
    
    if (!$decoded) {
        http_response_code(401);
        echo json_encode(['status' => false, 'message' => 'Token de autenticação inválido ou expirado']);
        exit;
    }
    
    return $decoded;
}

// Processar a requisição com base no método HTTP
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetRequest();
        break;
    case 'POST':
        handlePostRequest();
        break;
    case 'PUT':
        handlePutRequest();
        break;
    case 'DELETE':
        handleDeleteRequest();
        break;
    default:
        http_response_code(405);
        echo json_encode(['status' => false, 'message' => 'Método não permitido']);
        break;
}

// Função para tratar requisições GET (consulta de lojas)
function handleGetRequest() {
    // Validar token
    $userData = validateToken();
    
    // Obter parâmetros da URL
    $storeId = isset($_GET['id']) ? intval($_GET['id']) : null;
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $filters = [];
    
    // Aplicar filtros se fornecidos
    if (isset($_GET['status'])) $filters['status'] = $_GET['status'];
    if (isset($_GET['categoria'])) $filters['categoria'] = $_GET['categoria'];
    if (isset($_GET['busca'])) $filters['busca'] = $_GET['busca'];
    
    // Verificar tipo de usuário e redirecionar para o controlador apropriado
    if ($userData['tipo'] === USER_TYPE_ADMIN) {
        // Administrador pode ver todas as lojas ou detalhes de uma loja específica
        if ($storeId) {
            $result = AdminController::getStoreDetails($storeId);
        } else {
            $result = AdminController::manageStores($filters, $page);
        }
    } else if ($userData['tipo'] === USER_TYPE_CLIENT) {
        // Cliente só pode ver lojas aprovadas
        if ($storeId) {
            // Verificar se a loja está aprovada
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT status FROM lojas WHERE id = ? AND status = ?");
            $status = STORE_APPROVED;
            $stmt->execute([$storeId, $status]);
            
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['status' => false, 'message' => 'Loja não encontrada ou não aprovada']);
                exit;
            }
            
            // Obter detalhes da loja usando StoreController
            $result = StoreController::getStores(['id' => $storeId]);
        } else {
            // Listar lojas aprovadas para clientes
            $filters['status'] = STORE_APPROVED;
            $result = StoreController::getStores($filters, $page);
        }
    } else {
        // Loja só pode ver seu próprio perfil
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id FROM lojas WHERE usuario_id = ?");
        $stmt->execute([$userData['id']]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$store) {
            http_response_code(404);
            echo json_encode(['status' => false, 'message' => 'Perfil de loja não encontrado']);
            exit;
        }
        
        $result = AdminController::getStoreDetails($store['id']);
    }
    
    // Retornar resultado
    echo json_encode($result);
}

// Função para tratar requisições POST (criar nova loja)
function handlePostRequest() {
    // Verificar se é registro público
    $isPublicRegistration = isset($_GET['public']) && $_GET['public'] === 'true';
    
    if (!$isPublicRegistration) {
        // Validar token
        $userData = validateToken();
        
        // Apenas administradores podem criar lojas via API privada
        if ($userData['tipo'] !== USER_TYPE_ADMIN) {
            http_response_code(403);
            echo json_encode(['status' => false, 'message' => 'Apenas administradores podem criar lojas via API']);
            exit;
        }
    }
    
    // Obter dados do corpo da requisição
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validar dados básicos
    if (!$data || !isset($data['nome_fantasia']) || !isset($data['razao_social']) || 
        !isset($data['cnpj']) || !isset($data['email']) || !isset($data['telefone'])) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Dados incompletos para registro de loja']);
        exit;
    }
    
    // Validar CNPJ
    if (!Validator::validaCNPJ($data['cnpj'])) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'CNPJ inválido']);
        exit;
    }
    
    // Validar email
    if (!Validator::validaEmail($data['email'])) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Email inválido']);
        exit;
    }
    
    // Registrar nova loja
    $result = StoreController::registerStore($data);
    
    // Retornar resultado
    echo json_encode($result);
}

// Função para tratar requisições PUT (atualizar loja existente)
function handlePutRequest() {
    // Validar token
    $userData = validateToken();
    
    // Obter dados do corpo da requisição
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validar dados básicos
    if (!$data || !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'ID da loja não fornecido']);
        exit;
    }
    
    $storeId = intval($data['id']);
    
    // Verificar permissões
    if ($userData['tipo'] === USER_TYPE_ADMIN) {
        // Admin pode atualizar qualquer loja
        $result = AdminController::updateStore($storeId, $data);
    } else if ($userData['tipo'] === USER_TYPE_STORE) {
        // Loja só pode atualizar a si mesma
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id FROM lojas WHERE usuario_id = ?");
        $stmt->execute([$userData['id']]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$store || $store['id'] !== $storeId) {
            http_response_code(403);
            echo json_encode(['status' => false, 'message' => 'Você não tem permissão para atualizar esta loja']);
            exit;
        }
        
        // Limitar campos que a loja pode atualizar
        $allowedFields = ['telefone', 'email', 'website', 'descricao'];
        $updateData = [];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }
        
        // Se houver endereço para atualizar
        if (isset($data['endereco'])) {
            $updateData['endereco'] = $data['endereco'];
        }
        
        $result = AdminController::updateStore($storeId, $updateData);
    } else {
        // Clientes não podem atualizar lojas
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Permissão negada']);
        exit;
    }
    
    // Retornar resultado
    echo json_encode($result);
}

// Função para tratar requisições DELETE (desativar loja)
function handleDeleteRequest() {
    // Validar token
    $userData = validateToken();
    
    // Apenas administradores podem desativar lojas
    if ($userData['tipo'] !== USER_TYPE_ADMIN) {
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Apenas administradores podem desativar lojas']);
        exit;
    }
    
    // Obter ID da loja da URL
    $storeId = isset($_GET['id']) ? intval($_GET['id']) : null;
    
    if (!$storeId) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'ID da loja não fornecido']);
        exit;
    }
    
    // Rejeitar loja (alterar status para rejeitado)
    $observacao = 'Loja desativada via API';
    $result = StoreController::rejectStore($storeId, $observacao);
    
    // Retornar resultado
    echo json_encode($result);
}

// Função para aprovar loja (rota específica)
function approveStore() {
    // Validar token
    $userData = validateToken();
    
    // Apenas administradores podem aprovar lojas
    if ($userData['tipo'] !== USER_TYPE_ADMIN) {
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Apenas administradores podem aprovar lojas']);
        exit;
    }
    
    // Obter dados do corpo da requisição
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validar dados básicos
    if (!$data || !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'ID da loja não fornecido']);
        exit;
    }
    
    $storeId = intval($data['id']);
    
    // Aprovar loja
    $result = StoreController::approveStore($storeId);
    
    // Retornar resultado
    echo json_encode($result);
}

// Rota específica para aprovação de loja
if (isset($_GET['action']) && $_GET['action'] === 'approve') {
    // Bypass do fluxo normal para aprovar loja
    approveStore();
    exit;
}

// Rota específica para rejeição de loja
if (isset($_GET['action']) && $_GET['action'] === 'reject') {
    // Validar token
    $userData = validateToken();
    
    // Apenas administradores podem rejeitar lojas
    if ($userData['tipo'] !== USER_TYPE_ADMIN) {
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Apenas administradores podem rejeitar lojas']);
        exit;
    }
    
    // Obter dados do corpo da requisição
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validar dados básicos
    if (!$data || !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'ID da loja não fornecido']);
        exit;
    }
    
    $storeId = intval($data['id']);
    $observacao = isset($data['observacao']) ? $data['observacao'] : '';
    
    // Rejeitar loja
    $result = StoreController::rejectStore($storeId, $observacao);
    
    // Retornar resultado
    echo json_encode($result);
    exit;
}

// Função para obter categorias de lojas
function getStoreCategories() {
    // Validar token
    $userData = validateToken();
    
    // Consultar categorias de lojas
    $db = Database::getConnection();
    $query = "SELECT DISTINCT categoria FROM lojas WHERE status = ? ORDER BY categoria";
    $stmt = $db->prepare($query);
    $status = STORE_APPROVED;
    $stmt->execute([$status]);
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Retornar resultado
    echo json_encode(['status' => true, 'data' => $categories]);
}

// Rota específica para obter categorias de lojas
if (isset($_GET['action']) && $_GET['action'] === 'categories') {
    getStoreCategories();
    exit;
}

// Para qualquer erro não tratado anteriormente
function handleError($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'status' => false, 
        'message' => 'Erro interno do servidor',
        'error' => $errstr,
        'file' => $errfile,
        'line' => $errline
    ]);
    exit;
}

// Registrar manipulador de erros
set_error_handler('handleError');