<?php
// api/users.php
// API para gerenciar usuários do sistema Klube Cash
ob_start();

// Configurações iniciais
ini_set('log_errors', 1); 
ini_set('error_reporting', E_ALL); 
ini_set('display_errors', 0); 

$log_file_path = dirname(__DIR__) . '/logs/php_debug.log'; 
ini_set('error_log', $log_file_path);

$log_dir = dirname($log_file_path);
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0750, true); 
}

error_log("KlubeCash Debug - API USERS.PHP INICIADA - Request URI: " . $_SERVER['REQUEST_URI'] . " - Tentando logar em: " . $log_file_path); 

header('Content-Type: application/json; charset=UTF-8');

$allowed_origins = [
    'https://klubecash.com',     
    'https://www.klubecash.com',
    'https://sest-senat.klubecash.com',
    'https://sdk.mercadopago.com'
];

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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ BLOCO DE INCLUDES SEGUROS: Carrega apenas o que é essencial e não trava.
try {
    require_once __DIR__ . '/../config/database.php';
    error_log("KlubeCash Debug - database.php CARREGADO.");
    
    require_once __DIR__ . '/../config/constants.php';
    error_log("KlubeCash Debug - constants.php CARREGADO.");
    
    require_once __DIR__ . '/../controllers/AuthController.php';
    error_log("KlubeCash Debug - AuthController.php CARREGADO.");
    
    require_once __DIR__ . '/../controllers/ClientController.php';
    error_log("KlubeCash Debug - ClientController.php CARREGADO.");

    require_once __DIR__ . '/../utils/Security.php';
    error_log("KlubeCash Debug - Security.php CARREGADO.");
    
    // AdminController e Validator (que estavam causando o crash) são removidos daqui.
    // Eles serão carregados apenas dentro das funções que os utilizam.

} catch (Throwable $t) {
    // Se qualquer include essencial falhar
    error_log("KlubeCash Debug - ERRO FATAL AO INCLUIR ARQUIVO: " . $t->getMessage() . " no arquivo " . $t->getFile() . " na linha " . $t->getLine());
    
    if (!headers_sent()) {
        http_response_code(500);
        if (ob_get_level() > 0) { ob_clean(); } 
        echo json_encode(['status' => false, 'message' => 'Erro interno crítico do servidor ao carregar módulos.']);
    }
    ob_end_flush();
    exit;
}


// Função UNIFICADA para validar token JWT e sincronizar a sessão COM LOGS
function validateToken() {
    // Se a sessão já for válida, retorna os dados da sessão.
    if (isset($_SESSION['user_id'])) {
        error_log("KlubeCash Debug (Auth) [users.php] - Autenticação via SESSÃO PHP OK para user ID: " . $_SESSION['user_id']);
        return ['id' => $_SESSION['user_id'], 'tipo' => $_SESSION['user_type'] ?? null];
    }

    $token = $_COOKIE['jwt_token'] ?? '';
    error_log("KlubeCash Debug (Auth) [users.php] - Tentando autenticação via JWT. Cookie 'jwt_token' " . (empty($token) ? 'NÃO encontrado.' : 'encontrado.'));

    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['status' => false, 'message' => 'Token de autenticação não fornecido no cookie.']);
        exit;
    }
    
    error_log("KlubeCash Debug (Auth) [users.php] - Chamando Security::validateJWT...");
    $validationResult = Security::validateJWT($token); 
    error_log("KlubeCash Debug (Auth) [users.php] - Resultado de Security::validateJWT: " . print_r($validationResult, true));
    
    if (!$validationResult) {
        http_response_code(401);
        $tokenParts = explode('.', $token);
        if (count($tokenParts) === 3) {
            $payload = json_decode(Security::base64UrlDecode($tokenParts[1])); 
            if ($payload && isset($payload->exp)) {
                 date_default_timezone_set('America/Sao_Paulo'); 
                 $currentTime = time();
                 if($payload->exp < $currentTime) {
                    error_log("KlubeCash Debug (Auth) [users.php] - FALHA DETECTADA AQUI: Token JWT expirado. Exp: " . $payload->exp . " (" . date('Y-m-d H:i:s',$payload->exp) . "), Now: " . $currentTime . " (" . date('Y-m-d H:i:s',$currentTime) . ")");
                    echo json_encode(['status' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
                    exit;
                 }
            }
        }
        error_log("KlubeCash Debug (Auth) [users.php] - FALHA DETECTADA AQUI: Token JWT com assinatura inválida ou malformado.");
        echo json_encode(['status' => false, 'message' => 'Token com assinatura inválida.']);
        exit;
    }
    
    $tokenData = (array) $validationResult;

    // Sincroniza a sessão PHP com os dados do token validado
    $_SESSION['user_id'] = $tokenData['id'];
    $_SESSION['user_type'] = $tokenData['tipo'] ?? null; 
    
    error_log("KlubeCash Debug (Auth) [users.php] - SUCESSO: Token JWT validado e sessão sincronizada para user ID: " . $tokenData['id']);
    return $tokenData;
}

// --- ROTEAMENTO PRINCIPAL ---
$action = $_GET['action'] ?? '';

// Rotas Específicas (Login e Recover não precisam de validação prévia)
if ($action === 'login') {
    loginUser();
    exit;
}
if ($action === 'recover') {
    recoverPassword();
    exit;
}

// Para todas as outras ações, a autenticação é necessária PRIMEIRO.
$tokenData = validateToken(); 
$userId = $tokenData['id'];
$userType = $tokenData['tipo'] ?? null;

// Rotas específicas do React (Não precisam de AdminController)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get_details') {
    $result = ClientController::getProfileData($userId); 
    echo json_encode($result);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_details') {
    $postedData = json_decode(file_get_contents('php://input'), true);
    $result = ClientController::updateProfile($userId, $postedData);
    echo json_encode($result);
    exit;
}

// Rotas Genéricas (Admin) - Carregam os controllers problemáticos apenas quando chamadas
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetRequest($tokenData); // Passa os dados já validados
        break;
    case 'POST':
        handlePostRequest($tokenData); // Passa os dados já validados
        break;
    case 'PUT':
        handlePutRequest($tokenData); // Passa os dados já validados
        break;
    case 'DELETE':
        handleDeleteRequest($tokenData); // Passa os dados já validados
        break;
    default:
        http_response_code(405);
        echo json_encode(['status' => false, 'message' => 'Método não permitido']);
        break;
}

// --- Funções Handler Refatoradas ---

function handleGetRequest($userData) { 
    // ✅ Inclusão condicional: Carrega os controllers de Admin apenas aqui
    require_once __DIR__ . '/../controllers/AdminController.php';
    require_once __DIR__ . '/../utils/Validator.php';

    if (($userData['tipo'] ?? null) !== USER_TYPE_ADMIN) {
         http_response_code(403);
         echo json_encode(['status' => false, 'message' => 'Acesso não autorizado para esta operação GET.']);
         exit;
    }
    
    $targetUserId = isset($_GET['id']) ? intval($_GET['id']) : null;
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $filters = [];
    if (isset($_GET['tipo'])) $filters['tipo'] = $_GET['tipo'];
    if (isset($_GET['status'])) $filters['status'] = $_GET['status'];
    if (isset($_GET['busca'])) $filters['busca'] = $_GET['busca'];
    
    if ($targetUserId) {
        $result = AdminController::getUserDetails($targetUserId);
    } else {
        $result = AdminController::manageUsers($filters, $page);
    }
    echo json_encode($result);
}

function handlePostRequest($userData) { 
    $isPublicRegistration = isset($_GET['public']) && $_GET['public'] === 'true';
    if (!$isPublicRegistration) {
        // ✅ Inclusão condicional
        require_once __DIR__ . '/../controllers/AdminController.php'; 
        require_once __DIR__ . '/../utils/Validator.php';
        if (($userData['tipo'] ?? null) !== USER_TYPE_ADMIN) {
            http_response_code(403);
            echo json_encode(['status' => false, 'message' => 'Apenas administradores podem criar usuários via API']);
            exit;
        }
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['nome']) || !isset($data['email']) || (!$isPublicRegistration && !isset($data['senha']))) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Dados incompletos para criar usuário']);
        exit;
    }
    $result = AuthController::register(
        $data['nome'], $data['email'], $data['telefone'] ?? '', $data['senha'],
        $isPublicRegistration ? USER_TYPE_CLIENT : ($data['tipo'] ?? USER_TYPE_CLIENT)
    );
    echo json_encode($result);
}

function handlePutRequest($userData) { 
    // ✅ Inclusão condicional
    require_once __DIR__ . '/../controllers/AdminController.php';
    require_once __DIR__ . '/../utils/Validator.php';

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'ID do usuário não fornecido']);
        exit;
    }
    
    $targetUserId = intval($data['id']);
    if (($userData['tipo'] ?? null) !== USER_TYPE_ADMIN && $targetUserId !== $userData['id']) {
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Você não tem permissão para editar este usuário']);
        exit;
    }
    
    $updateData = [];
    $allowedFields = ['nome', 'email', 'telefone'];
    if (($userData['tipo'] ?? null) === USER_TYPE_ADMIN) {
        $allowedFields = array_merge($allowedFields, ['tipo', 'status']);
    }
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) { $updateData[$field] = $data[$field]; }
    }
    if (isset($data['senha']) && !empty($data['senha'])) {
        if (($userData['tipo'] ?? null) !== USER_TYPE_ADMIN && empty($data['senha_atual'])) {
             http_response_code(400);
             echo json_encode(['status' => false, 'message' => 'Senha atual obrigatória para alteração de senha']);
             exit;
        }
        $updateData['senha'] = $data['senha'];
        if (isset($data['senha_atual'])) { $updateData['senha_atual'] = $data['senha_atual']; }
    }
    
    if (($userData['tipo'] ?? null) === USER_TYPE_ADMIN) {
        $result = AdminController::updateUser($targetUserId, $updateData);
    } else {
        $result = ClientController::updateProfile($targetUserId, $updateData); 
    }
    echo json_encode($result);
}

function handleDeleteRequest($userData) { 
    // ✅ Inclusão condicional
    require_once __DIR__ . '/../controllers/AdminController.php';
    require_once __DIR__ . '/../utils/Validator.php';

    if (($userData['tipo'] ?? null) !== USER_TYPE_ADMIN) {
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Apenas administradores podem desativar usuários']);
        exit;
    }
    
    $targetUserId = isset($_GET['id']) ? intval($_GET['id']) : null;
    if (!$targetUserId) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'ID do usuário não fornecido']);
        exit;
    }
    if ($targetUserId === $userData['id']) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Não é possível desativar seu próprio usuário']);
        exit;
    }
    
    $result = AdminController::updateUserStatus($targetUserId, 'inativo');
    echo json_encode($result);
}

// --- Funções de Login e Recover ---

function loginUser() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); echo json_encode(['status' => false, 'message' => 'Método não permitido para login']); exit;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['email']) || !isset($data['senha'])) {
        http_response_code(400); echo json_encode(['status' => false, 'message' => 'Email e senha são obrigatórios']); exit;
    }
    
    if (!class_exists('AuthController')) {
        require_once __DIR__ . '/../controllers/AuthController.php';
    }
    
    $result = AuthController::login($data['email'], $data['senha'], false, ($data['origem'] ?? '')); 
    
    if ($result['status']) {
        if (session_status() === PHP_SESSION_ACTIVE) { session_destroy(); }
        session_start();
        session_regenerate_id(true);

        $_SESSION['user_id'] = $result['user_data']['id'];
        $_SESSION['user_type'] = $result['user_data']['tipo'];

        $token = $result['token'] ?? null; 
        
        if($token){
             if (!class_exists('Security')) { require_once __DIR__ . '/../utils/Security.php'; }
             setcookie(
                'jwt_token', $token,
                [
                    'expires'  => time() + (defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 86400),
                    'path'     => '/', 'domain'   => '.klubecash.com',
                    'secure'   => true, 'httponly' => true, 'samesite' => 'None'
                ]
            );
        }
        unset($result['token']); 
    }
    echo json_encode($result);
}

function recoverPassword() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); echo json_encode(['status' => false, 'message' => 'Método não permitido']); exit;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['email'])) {
        http_response_code(400); echo json_encode(['status' => false, 'message' => 'Email obrigatório']); exit;
    }
    
    if (!class_exists('AuthController')) {
        require_once __DIR__ . '/../controllers/AuthController.php';
    }
    $result = AuthController::recoverPassword($data['email']);
    echo json_encode($result);
}

// Manipulador de Erros Genérico
function handleError($errno, $errstr, $errfile, $errline) {
    error_log("Erro PHP: [$errno] $errstr em $errfile na linha $errline"); 

    if (!headers_sent() && ($errno === E_ERROR || $errno === E_PARSE || $errno === E_CORE_ERROR || $errno === E_COMPILE_ERROR || $errno === E_USER_ERROR)) {
       http_response_code(500);
       if (ob_get_level() > 0) { ob_clean(); } 
       echo json_encode(['status' => false, 'message' => 'Erro interno crítico do servidor.']);
       exit; 
    }
}
set_error_handler('handleError');

ob_end_flush();
?>

