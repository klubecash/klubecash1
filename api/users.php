<?php
// api/users.php
// API para gerenciar usuários do sistema Klube Cash

// Configurações iniciais
header('Content-Type: application/json; charset=UTF-8');
// O 'Access-Control-Allow-Origin' PRECISA ser o domínio exato, não '*'

$allowed_origins = [
    'https://sest-senat.klubecash.com',
    'https://sdk.mercadopago.com'
    // Adicione outros domínios de desenvolvimento se necessário
];

// Verifica a origem da requisição e define o cabeçalho CORS dinamicamente
if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '.klubecash.com',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'None' 
]);

// Responde à requisição pre-flight do navegador
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Inicia a sessão para ler o cookie PHPSESSID
session_start();
// Incluir arquivos necessários
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/AdminController.php';
require_once __DIR__ . '/../controllers/ClientController.php';
require_once __DIR__ . '/../utils/Security.php';
require_once __DIR__ . '/../utils/Validator.php';


// Função para validar token JWT
function validateToken() {
    $token = $_COOKIE['jwt_token'] ?? '';
    
    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['status' => false, 'message' => 'Token de autenticação não fornecido no cookie.']);
        exit;
    }
    
    // Tenta validar o token e captura o motivo da falha.
    // O segundo parâmetro (true) pode ser necessário dependendo da sua implementação de Security::validateJWT
    // para retornar um erro detalhado. Assumindo que a função foi adaptada para isso.
    $validationResult = Security::validateJWT($token);
    
    if (!$validationResult) {
        http_response_code(401);
        // Tente decodificar para dar um erro mais específico
        $tokenParts = explode('.', $token);
        if (count($tokenParts) === 3) {
            $payload = json_decode(base64_decode(strtr($tokenParts[1], '-_', '+/')));
            if ($payload && isset($payload->exp) && $payload->exp < time()) {
                echo json_encode(['status' => false, 'message' => 'Token expirado.']);
                exit;
            }
        }
        echo json_encode(['status' => false, 'message' => 'Token com assinatura inválida.']);
        exit;
    }
    
    // Retorna os dados do token como um array
    return (array) $validationResult;
}


// ROTEAMENTO SIMPLIFICADO PARA O APP REACT (SEST-SENAT)
$action = $_GET['action'] ?? '';

// Rota para buscar os detalhes do usuário logado (usado pelo React)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get_details') {
    $tokenData = validateToken();
    $userId = $tokenData['id'];

    // Repopular a sessão PHP com base no token JWT válido.
    $_SESSION['user_id'] = $tokenData['id'];
    if (isset($tokenData['tipo'])) {
        $_SESSION['user_type'] = $tokenData['tipo'];
    }

    $result = ClientController::getProfileData($userId); 
    
    echo json_encode($result);
    exit;
}


// Rota para atualizar os detalhes do usuário (usado pelo React)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_details') {
    $tokenData = validateToken();
    $userId = $tokenData['id'];
    
    // ✅ CORREÇÃO: Repopular a sessão PHP aqui também para consistência.
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['user_id'] = $tokenData['id'];
        if (isset($tokenData['tipo'])) {
            $_SESSION['user_type'] = $tokenData['tipo'];
        }
    }

    $postedData = json_decode(file_get_contents('php://input'), true);


    $result = ClientController::updateProfile($userId, $postedData);
    
    echo json_encode($result);
    exit;
}

// ========
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