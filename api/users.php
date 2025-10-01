<?php
// api/users.php
// API para gerenciar usuários do sistema Klube Cash

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
    
    // Validar token (usando uma função de exemplo, você precisará implementar seu próprio validador)
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

// Função para tratar requisições GET (consulta de usuários)
function handleGetRequest() {
    // Validar token
    $userData = validateToken();
    
    // Verificar se é administrador (só admins podem listar usuários)
    if ($userData['tipo'] !== USER_TYPE_ADMIN) {
        // Se não for admin, só pode ver seu próprio perfil
        if (isset($_GET['id']) && intval($_GET['id']) === $userData['id']) {
            $result = ['status' => true, 'data' => ['usuario' => $userData]];
        } else {
            http_response_code(403);
            echo json_encode(['status' => false, 'message' => 'Acesso não autorizado']);
            exit;
        }
    } else {
        // É admin, pode ver qualquer usuário
        $userId = isset($_GET['id']) ? intval($_GET['id']) : null;
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $filters = [];
        
        // Aplicar filtros se fornecidos
        if (isset($_GET['tipo'])) $filters['tipo'] = $_GET['tipo'];
        if (isset($_GET['status'])) $filters['status'] = $_GET['status'];
        if (isset($_GET['busca'])) $filters['busca'] = $_GET['busca'];
        
        if ($userId) {
            $result = AdminController::getUserDetails($userId);
        } else {
            $result = AdminController::manageUsers($filters, $page);
        }
    }
    
    // Retornar resultado
    echo json_encode($result);
}

// Função para tratar requisições POST (criar novo usuário)
function handlePostRequest() {
    // Validar token para criar usuários (exceto para registro público)
    $isPublicRegistration = isset($_GET['public']) && $_GET['public'] === 'true';
    
    if (!$isPublicRegistration) {
        $userData = validateToken();
        
        // Verificar se é administrador (só admins podem criar usuários via API)
        if ($userData['tipo'] !== USER_TYPE_ADMIN) {
            http_response_code(403);
            echo json_encode(['status' => false, 'message' => 'Apenas administradores podem criar usuários via API']);
            exit;
        }
    }
    
    // Obter dados do corpo da requisição
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validar dados básicos
    if (!$data || !isset($data['nome']) || !isset($data['email']) || (!$isPublicRegistration && !isset($data['senha']))) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Dados incompletos para criar usuário']);
        exit;
    }
    
    // Registrar novo usuário
    $result = AuthController::register(
        $data['nome'],
        $data['email'],
        $data['telefone'] ?? '',
        $data['senha'],
        $isPublicRegistration ? USER_TYPE_CLIENT : ($data['tipo'] ?? USER_TYPE_CLIENT)
    );
    
    // Retornar resultado
    echo json_encode($result);
}

// Função para tratar requisições PUT (atualizar usuário existente)
function handlePutRequest() {
    // Validar token
    $userData = validateToken();
    
    // Obter dados do corpo da requisição
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validar dados básicos
    if (!$data || !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'ID do usuário não fornecido']);
        exit;
    }
    
    $userId = intval($data['id']);
    
    // Verificar permissões: admin pode editar qualquer usuário, outros só podem editar a si mesmos
    if ($userData['tipo'] !== USER_TYPE_ADMIN && $userId !== $userData['id']) {
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Você não tem permissão para editar este usuário']);
        exit;
    }
    
    // Preparar dados para atualização
    $updateData = [];
    
    // Campos permitidos para atualização
    $allowedFields = ['nome', 'email', 'telefone'];
    
    // Admin pode alterar outros campos
    if ($userData['tipo'] === USER_TYPE_ADMIN) {
        $allowedFields = array_merge($allowedFields, ['tipo', 'status']);
    }
    
    // Copiar apenas os campos permitidos
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateData[$field] = $data[$field];
        }
    }
    
    // Senha pode ser alterada se fornecida
    if (isset($data['senha']) && !empty($data['senha'])) {
        // Para não-admin, exigir senha atual
        if ($userData['tipo'] !== USER_TYPE_ADMIN && (!isset($data['senha_atual']) || empty($data['senha_atual']))) {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Senha atual obrigatória para alteração de senha']);
            exit;
        }
        
        $updateData['senha'] = $data['senha'];
        
        if (isset($data['senha_atual'])) {
            $updateData['senha_atual'] = $data['senha_atual'];
        }
    }
    
    // Atualizar usuário
    if ($userData['tipo'] === USER_TYPE_ADMIN) {
        $result = AdminController::updateUser($userId, $updateData);
    } else {
        // Implementar atualização para cliente/loja (usar ClientController ou criar método específico)
        $result = ['status' => false, 'message' => 'Função não implementada'];
    }
    
    // Retornar resultado
    echo json_encode($result);
}

// Função para tratar requisições DELETE (desativar/remover usuário)
function handleDeleteRequest() {
    // Validar token
    $userData = validateToken();
    
    // Apenas administradores podem desativar/remover usuários
    if ($userData['tipo'] !== USER_TYPE_ADMIN) {
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Apenas administradores podem desativar usuários']);
        exit;
    }
    
    // Obter ID do usuário da URL
    $userId = isset($_GET['id']) ? intval($_GET['id']) : null;
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'ID do usuário não fornecido']);
        exit;
    }
    
    // Não permitir que um administrador desative a si mesmo
    if ($userId === $userData['id']) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Não é possível desativar seu próprio usuário']);
        exit;
    }
    
    // Desativar usuário (status = inativo)
    $result = AdminController::updateUserStatus($userId, 'inativo');
    
    // Retornar resultado
    echo json_encode($result);
}

// Rota específica para autenticação e obtenção de token JWT
if (isset($_GET['action']) && $_GET['action'] === 'login') {
    // Bypass do fluxo normal para processar login
    loginUser();
    exit;
}

// Função para processar login e gerar token JWT
function loginUser() {
    // Verificar se é uma requisição POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => false, 'message' => 'Método não permitido para login']);
        exit;
    }
    
    // Obter dados do corpo da requisição
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validar dados básicos
    if (!$data || !isset($data['email']) || !isset($data['senha'])) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Email e senha são obrigatórios']);
        exit;
    }
    
    // Autenticar usuário
    $result = AuthController::login($data['email'], $data['senha']);
    
    if ($result['status']) {
        // Gerar token JWT
        $token = Security::generateJWT([
            'id' => $result['user']['id'],
            'nome' => $result['user']['name'],
            'tipo' => $result['user']['type'],
            'exp' => time() + SESSION_LIFETIME // Tempo de expiração
        ]);
        
        $result['token'] = $token;
    }
    
    // Retornar resultado
    echo json_encode($result);
}

// Rota específica para recuperação de senha
if (isset($_GET['action']) && $_GET['action'] === 'recover') {
    // Bypass do fluxo normal para processar recuperação de senha
    recoverPassword();
    exit;
}

// Função para processar solicitação de recuperação de senha
function recoverPassword() {
    // Verificar se é uma requisição POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => false, 'message' => 'Método não permitido para recuperação de senha']);
        exit;
    }
    
    // Obter dados do corpo da requisição
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validar dados básicos
    if (!$data || !isset($data['email'])) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Email obrigatório']);
        exit;
    }
    
    // Solicitar recuperação de senha
    $result = AuthController::recoverPassword($data['email']);
    
    // Retornar resultado
    echo json_encode($result);
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