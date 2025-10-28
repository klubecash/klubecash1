<?php
// views/stores/register.php - Versão Progressiva e Intuitiva
// Mantendo toda a lógica original, apenas reestruturando a apresentação

// Primeira camada: Ativar exibição de erros para desenvolvimento
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Segunda camada: Função de log personalizada para rastrear cada passo
function debug_log($message) {
    error_log("[STORE_REGISTER] " . $message);
    if (isset($_GET['debug'])) {
        echo "<!-- DEBUG: $message -->\n";
    }
}

debug_log("Iniciando carregamento da página de registro de loja");

// Terceira camada: Carregamento seguro dos arquivos essenciais
$required_files = [
    '../../config/constants.php' => 'Constantes do sistema',
    '../../config/database.php' => 'Conexão com banco de dados', 
    '../../config/email.php' => 'Configurações de email',
    '../../controllers/StoreController.php' => 'Controlador de lojas',
    '../../utils/Validator.php' => 'Validador de dados'
];

// Função para criar diretório se não existir
function createUploadDir($path) {
    if (!file_exists($path)) {
        if (!mkdir($path, 0755, true)) {
            error_log("Não foi possível criar diretório: $path");
            return false;
        }
        debug_log("Diretório criado: $path");
    }
    return true;
}

// Configurar diretórios de upload
$uploadsDir = __DIR__ . '/../../uploads';
$storeLogosDir = $uploadsDir . '/store_logos';

// Criar diretórios se não existirem
createUploadDir($uploadsDir);
createUploadDir($storeLogosDir);

debug_log("Diretórios de upload preparados");

foreach ($required_files as $file => $description) {
    if (file_exists($file)) {
        require_once $file;
        debug_log("✓ Carregado: $description");
    } else {
        die("❌ Erro crítico: Não foi possível carregar $description ($file)");
    }
}

// Quarta camada: Verificação de classes essenciais
$required_classes = ['StoreController', 'Validator', 'Database', 'Email'];
foreach ($required_classes as $class) {
    if (!class_exists($class)) {
        die("❌ Erro crítico: Classe $class não encontrada");
    }
    debug_log("✓ Classe $class verificada");
}

// Quinta camada: Inicialização segura da sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    debug_log("Sessão iniciada com sucesso");
}

if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Sexta camada: Verificação de estado de autenticação
$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = $isLoggedIn && isset($_SESSION['user_type']) && $_SESSION['user_type'] == USER_TYPE_ADMIN;

debug_log("Estado de autenticação - Logado: " . ($isLoggedIn ? 'Sim' : 'Não') . ", Admin: " . ($isAdmin ? 'Sim' : 'Não'));

// Sétima camada: Inicialização de variáveis de controle
$error = '';
$success = '';
$data = []; // Array para manter dados do formulário

debug_log("Variáveis de controle inicializadas");

// Função para normalizar URL do website
function normalizeWebsiteUrl($url) {
    if (empty($url)) {
        return '';
    }

    $url = trim($url);

    // Se não começar com protocolo, adiciona https://
    if (!preg_match('/^https?:\/\//', $url)) {
        $url = 'https://' . $url;
    }

    // Sanitizar e validar
    $sanitized = filter_var($url, FILTER_SANITIZE_URL);

    // Validar se é uma URL válida
    if (filter_var($sanitized, FILTER_VALIDATE_URL)) {
        return $sanitized;
    }

    return '';
}

// Função de processamento de upload (mantida original)
function processLogoUpload($file, $storeLogosDir) {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['status' => true, 'filename' => null, 'message' => 'Nenhum arquivo enviado'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'Arquivo muito grande (limite do servidor)',
            UPLOAD_ERR_FORM_SIZE => 'Arquivo muito grande (limite do formulário)',
            UPLOAD_ERR_PARTIAL => 'Upload incompleto',
            UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário não encontrado',
            UPLOAD_ERR_CANT_WRITE => 'Erro de escrita no disco',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão'
        ];
        
        $message = isset($errorMessages[$file['error']]) ? $errorMessages[$file['error']] : 'Erro desconhecido no upload';
        return ['status' => false, 'message' => $message];
    }
    
    $maxSize = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $maxSize) {
        return ['status' => false, 'message' => 'Arquivo muito grande. Máximo: 2MB'];
    }
    
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['status' => false, 'message' => 'Tipo de arquivo não permitido. Use JPG, PNG ou GIF'];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $uniqueName = 'logo_' . uniqid() . '_' . time() . '.' . strtolower($extension);
    $destinationPath = $storeLogosDir . '/' . $uniqueName;
    
    if (!move_uploaded_file($file['tmp_name'], $destinationPath)) {
        return ['status' => false, 'message' => 'Erro ao salvar arquivo no servidor'];
    }
    
    if (!file_exists($destinationPath)) {
        return ['status' => false, 'message' => 'Arquivo não foi salvo corretamente'];
    }
    
    return [
        'status' => true, 
        'filename' => $uniqueName,
        'path' => $destinationPath,
        'url' => '/uploads/store_logos/' . $uniqueName,
        'message' => 'Logo enviada com sucesso'
    ];
}

// Processamento do formulário (mantido original)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    debug_log("Processando envio do formulário com possível upload de logo");
    
    try {
        $logoResult = processLogoUpload($_FILES['logo'] ?? null, $storeLogosDir);
        
        if (!$logoResult['status'] && $logoResult['filename'] !== null) {
            $error = "Erro no upload da logo: " . $logoResult['message'];
            debug_log("Erro no upload: " . $logoResult['message']);
        } else {
            debug_log("Upload processado: " . ($logoResult['filename'] ? 'Arquivo salvo' : 'Nenhum arquivo'));
            
            if ($logoResult['filename']) {
                $data['logo'] = $logoResult['filename'];
                $data['logo_url'] = $logoResult['url'];
                debug_log("Logo será salva como: " . $logoResult['filename']);
            }
        }

        // Capturar e sanitizar dados
        $data = [
            'nome_fantasia' => trim(htmlspecialchars($_POST['nome_fantasia'] ?? '', ENT_QUOTES, 'UTF-8')),
            'razao_social' => trim(htmlspecialchars($_POST['razao_social'] ?? '', ENT_QUOTES, 'UTF-8')),
            'cnpj' => trim(htmlspecialchars($_POST['cnpj'] ?? '', ENT_QUOTES, 'UTF-8')),
            'email' => trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL)),
            'telefone' => trim(htmlspecialchars($_POST['telefone'] ?? '', ENT_QUOTES, 'UTF-8')),
            'senha' => $_POST['senha'] ?? '',
            'confirma_senha' => $_POST['confirma_senha'] ?? '',
            'categoria' => trim(htmlspecialchars($_POST['categoria'] ?? '', ENT_QUOTES, 'UTF-8')),
            'descricao' => trim(htmlspecialchars($_POST['descricao'] ?? '', ENT_QUOTES, 'UTF-8')),
            'website' => normalizeWebsiteUrl(trim($_POST['website'] ?? '')),
            'endereco' => [
                'cep' => trim(htmlspecialchars($_POST['cep'] ?? '', ENT_QUOTES, 'UTF-8')),
                'logradouro' => trim(htmlspecialchars($_POST['logradouro'] ?? '', ENT_QUOTES, 'UTF-8')),
                'numero' => trim(htmlspecialchars($_POST['numero'] ?? '', ENT_QUOTES, 'UTF-8')),
                'complemento' => trim(htmlspecialchars($_POST['complemento'] ?? '', ENT_QUOTES, 'UTF-8')),
                'bairro' => trim(htmlspecialchars($_POST['bairro'] ?? '', ENT_QUOTES, 'UTF-8')),
                'cidade' => trim(htmlspecialchars($_POST['cidade'] ?? '', ENT_QUOTES, 'UTF-8')),
                'estado' => trim(htmlspecialchars($_POST['estado'] ?? '', ENT_QUOTES, 'UTF-8'))
            ]
        ];
        
        debug_log("Dados do formulário capturados e sanitizados");
        
        // Validações (mantidas originais)
        $errors = [];
        
        if (empty($data['nome_fantasia'])) $errors[] = 'Nome fantasia é obrigatório';
        if (empty($data['razao_social'])) $errors[] = 'Razão social é obrigatória';
        if (empty($data['cnpj'])) $errors[] = 'CNPJ é obrigatório';
        
        if (empty($data['email'])) {
            $errors[] = 'Email é obrigatório';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email inválido';
        }
        
        if (empty($data['telefone'])) $errors[] = 'Telefone é obrigatório';
        if (empty($data['categoria'])) $errors[] = 'Categoria é obrigatória';
        
        if (empty($data['senha'])) {
            $errors[] = 'Senha é obrigatória';
        } elseif (strlen($data['senha']) < 8) {
            $errors[] = 'A senha deve ter pelo menos 8 caracteres';
        }
        
        if (empty($data['confirma_senha'])) {
            $errors[] = 'Confirmação de senha é obrigatória';
        } elseif ($data['senha'] !== $data['confirma_senha']) {
            $errors[] = 'As senhas não coincidem';
        }
        
        $endereco_obrigatorios = ['cep', 'logradouro', 'numero', 'bairro', 'cidade', 'estado'];
        foreach ($endereco_obrigatorios as $campo) {
            if (empty($data['endereco'][$campo])) {
                $errors[] = ucfirst($campo) . ' é obrigatório';
            }
        }
        
        debug_log("Validação concluída. Erros encontrados: " . count($errors));
        
        if (empty($errors)) {
            debug_log("Iniciando processo de registro da loja");
            
            $data['cnpj'] = preg_replace('/[^0-9]/', '', $data['cnpj']);
            
            $result = StoreController::registerStore($data);
            
            debug_log("Resultado do registro: " . ($result['status'] ? 'Sucesso' : 'Falha'));
            
            if ($result['status']) {
                $_SESSION['success_message'] = $result['message'];
                $_SESSION['form_submitted'] = true;
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;
            } else {
                $error = $result['message'];
                debug_log("Erro no cadastro: " . $result['message']);
            }
        } else {
            $error = implode('<br>', $errors);
            debug_log("Erros de validação: " . implode(', ', $errors));
        }
        
    } catch (Exception $e) {
        if (isset($logoResult['path']) && file_exists($logoResult['path'])) {
            unlink($logoResult['path']);
            debug_log("Arquivo de logo removido devido a erro no cadastro");
        }
        
        $error = "Erro interno: " . $e->getMessage();
        debug_log("Exceção capturada: " . $e->getMessage());
        error_log("Erro no cadastro de loja: " . $e->getMessage());
    }
}

// Preparar dados para os elementos de seleção
$categorias = [
    'Alimentação', 'Vestuário', 'Eletrônicos', 'Casa e Decoração', 
    'Beleza e Saúde', 'Serviços', 'Educação', 'Entretenimento', 'Outros'
];

$estados = [
    'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
    'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
    'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
    'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
    'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
    'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
    'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins'
];

debug_log("Dados de seleção preparados, iniciando renderização da página");
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Loja Parceira - Klube Cash</title>
    <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
    
    <style>
        /* Reset e variáveis modernas */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #FF7A00;
            --primary-light: #FFF0E6;
            --primary-dark: #E06E00;
            --white: #FFFFFF;
            --light-gray: #F8F9FA;
            --medium-gray: #6C757D;
            --dark-gray: #333333;
            --success-color: #28A745;
            --danger-color: #DC3545;
            --border-radius: 12px;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --shadow-strong: 0 8px 30px rgba(0, 0, 0, 0.12);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --gradient: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: var(--dark-gray);
            line-height: 1.6;
        }

        /* Container principal */
        .registration-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .registration-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-strong);
            width: 100%;
            max-width: 800px;
            overflow: hidden;
            position: relative;
        }

        /* Header com progresso */
        .registration-header {
            background: var(--gradient);
            color: var(--white);
            padding: 30px;
            text-align: center;
            position: relative;
        }

        .registration-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="25" cy="75" r="1" fill="white" opacity="0.05"/><circle cx="75" cy="25" r="1" fill="white" opacity="0.05"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            pointer-events: none;
        }

        .registration-header > * {
            position: relative;
            z-index: 1;
        }

        .registration-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .registration-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 30px;
        }

        /* Barra de progresso */
        .progress-container {
            margin-bottom: 20px;
        }

        .progress-bar {
            background: rgba(255, 255, 255, 0.2);
            height: 6px;
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 15px;
        }

        .progress-fill {
            background: var(--white);
            height: 100%;
            border-radius: 3px;
            transition: width 0.5s ease;
            width: 14.28%; /* 1/7 das etapas */
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .progress-step {
            position: relative;
            transition: var(--transition);
        }

        .progress-step.active {
            font-weight: 600;
            transform: scale(1.05);
        }

        /* Conteúdo do formulário */
        .registration-content {
            padding: 40px;
        }

        /* Sistema de etapas */
        .step {
            display: none;
            animation: slideInRight 0.5s ease-out;
        }

        .step.active {
            display: block;
        }

        .step.exiting {
            animation: slideOutLeft 0.3s ease-in;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideOutLeft {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(-30px);
            }
        }

        /* Título da etapa */
        .step-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .step-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 10px;
        }

        .step-description {
            color: var(--medium-gray);
            font-size: 0.95rem;
        }

        /* Formulário */
        .form-grid {
            display: grid;
            gap: 20px;
            margin-bottom: 30px;
        }

        .form-grid.two-columns {
            grid-template-columns: 1fr 1fr;
        }

        .form-group {
            position: relative;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--dark-gray);
            font-size: 0.9rem;
        }

        .form-label .required {
            color: var(--danger-color);
            margin-left: 3px;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #E9ECEF;
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
            background: var(--white);
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 122, 0, 0.1);
        }

        .form-input.error, .form-select.error, .form-textarea.error {
            border-color: var(--danger-color);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        /* Upload de logo com preview */
        .logo-upload-container {
            border: 2px dashed #D1D5DB;
            border-radius: var(--border-radius);
            padding: 30px;
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
            position: relative;
        }

        .logo-upload-container:hover {
            border-color: var(--primary-color);
            background-color: var(--primary-light);
        }

        .logo-upload-container.has-file {
            border-color: var(--success-color);
            background-color: #F0F9FF;
        }

        .upload-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .upload-text {
            font-weight: 500;
            margin-bottom: 5px;
        }

        .upload-hint {
            font-size: 0.85rem;
            color: var(--medium-gray);
        }

        .logo-preview {
            display: none;
            margin-top: 20px;
        }

        .logo-preview.active {
            display: block;
        }

        .logo-preview img {
            max-width: 200px;
            max-height: 120px;
            border-radius: 8px;
            box-shadow: var(--shadow);
        }

        /* Informações importantes */
        .info-card {
            background: linear-gradient(135deg, #EBF8FF, #F0F9FF);
            border: 1px solid #B3D9FF;
            border-radius: var(--border-radius);
            padding: 20px;
            margin: 20px 0;
        }

        .info-card h4 {
            color: var(--primary-color);
            margin-bottom: 10px;
            font-size: 1.1rem;
        }

        .info-list {
            list-style: none;
            space-y: 8px;
        }

        .info-list li {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .info-list li::before {
            content: '✓';
            color: var(--success-color);
            font-weight: bold;
            margin-right: 10px;
            flex-shrink: 0;
        }

        /* Termos e condições */
        .terms-section {
            background: var(--light-gray);
            border-radius: var(--border-radius);
            padding: 25px;
            margin: 20px 0;
        }

        .terms-title {
            font-size: 1.2rem;
            margin-bottom: 15px;
            color: var(--dark-gray);
        }

        .terms-content {
            max-height: 200px;
            overflow-y: auto;
            font-size: 0.9rem;
            color: var(--medium-gray);
            margin-bottom: 20px;
            padding-right: 10px;
        }

        .terms-content::-webkit-scrollbar {
            width: 6px;
        }

        .terms-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .terms-content::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 3px;
        }

        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .checkbox-group input[type="checkbox"] {
            margin-top: 3px;
            accent-color: var(--primary-color);
        }

        /* Resumo final */
        .summary-section {
            background: var(--light-gray);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
        }

        .summary-title {
            font-size: 1.3rem;
            margin-bottom: 20px;
            color: var(--dark-gray);
            text-align: center;
        }

        .summary-grid {
            display: grid;
            gap: 15px;
        }

        .summary-item {
            background: var(--white);
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }

        .summary-label {
            font-size: 0.8rem;
            color: var(--medium-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .summary-value {
            font-weight: 500;
            color: var(--dark-gray);
        }

        /* Botões de navegação */
        .step-navigation {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--gradient);
            color: var(--white);
            box-shadow: 0 4px 15px rgba(255, 122, 0, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 122, 0, 0.4);
        }

        .btn-secondary {
            background: var(--white);
            color: var(--medium-gray);
            border: 2px solid #E9ECEF;
        }

        .btn-secondary:hover {
            background: var(--light-gray);
            border-color: var(--medium-gray);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Alertas */
        .alert {
            padding: 15px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .alert-success {
            background: #D4EDDA;
            border: 1px solid #C3E6CB;
            color: #155724;
        }

        .alert-danger {
            background: #F8D7DA;
            border: 1px solid #F5C6CB;
            color: #721C24;
        }

        .alert-icon {
            font-size: 1.2rem;
            flex-shrink: 0;
            margin-top: 1px;
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .registration-container {
                padding: 10px;
            }

            .registration-header {
                padding: 20px;
            }

            .registration-title {
                font-size: 1.5rem;
            }

            .registration-subtitle {
                font-size: 1rem;
            }

            .registration-content {
                padding: 20px;
            }

            .form-grid.two-columns {
                grid-template-columns: 1fr;
            }

            .step-navigation {
                flex-direction: column;
            }

            .progress-steps {
                font-size: 0.7rem;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .registration-title {
                font-size: 1.3rem;
            }

            .registration-content {
                padding: 15px;
            }

            .btn {
                padding: 10px 20px;
                font-size: 0.9rem;
            }
        }

        /* Loading state for CEP input */
        .form-input.loading-cep {
            opacity: 0.7;
            cursor: wait;
            background-image: url('data:image/svg+xml;charset=UTF-8,%3csvg version="1.1" id="L9" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 100 100" enable-background="new 0 0 0 0" xml:space="preserve"%3e%3cpath fill="%23FF7A00" d="M73,50c0-12.7-10.3-23-23-23S27,37.3,27,50c0,12.7,10.3,23,23,23S73,62.7,73,50z"%3e%3canimateTransform attributeName="transform" attributeType="XML" type="rotate" dur="1s" from="0 50 50" to="360 50 50" repeatCount="indefinite" /%3e%3c/path%3e%3c/svg%3e');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 20px 20px;
        }

        /* Animações de validação */
        .form-input.valid {
            border-color: var(--success-color);
        }

        .validation-message {
            font-size: 0.8rem;
            margin-top: 5px;
            min-height: 1.2rem;
        }

        .validation-message.error {
            color: var(--danger-color);
        }

        .validation-message.success {
            color: var(--success-color);
        }

        /* Loading state */
        .btn.loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .btn.loading::after {
            content: '';
            width: 16px;
            height: 16px;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 8px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <!-- Container principal do cadastro -->
    <div class="registration-container">
        <div class="registration-card">
            <!-- Header com progresso -->
            <div class="registration-header">
                <h1 class="registration-title">Cadastro de Loja Parceira</h1>
                <p class="registration-subtitle">Torne-se nosso parceiro em poucos passos simples</p>
                
                <div class="progress-container">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                    <div class="progress-steps">
                        <span class="progress-step active" data-step="1">Empresa</span>
                        <span class="progress-step" data-step="2">Contato</span>
                        <span class="progress-step" data-step="3">Logo</span>
                        <span class="progress-step" data-step="4">Acesso</span>
                        <span class="progress-step" data-step="5">Endereço</span>
                        <span class="progress-step" data-step="6">Termos</span>
                        <span class="progress-step" data-step="7">Revisão</span>
                    </div>
                </div>
            </div>

            <!-- Conteúdo do formulário -->
            <div class="registration-content">
                <!-- Alertas de erro/sucesso -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <span class="alert-icon">⚠️</span>
                        <div><?php echo $error; ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <span class="alert-icon">✅</span>
                        <div>
                            <?php echo htmlspecialchars($success); ?>
                            <div style="margin-top: 10px; font-size: 0.9rem;">
                                <strong>Próximos passos:</strong><br>
                                • Sua solicitação foi recebida e está em análise<br>
                                • Você receberá um email quando sua loja for aprovada<br>
                                • Após aprovação, poderá fazer login no sistema
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Formulário progressivo -->
                <form method="post" action="" id="store-form" enctype="multipart/form-data">
                    
                    <!-- Etapa 1: Informações da Empresa -->
                    <div class="step active" data-step="1">
                        <div class="step-header">
                            <h2 class="step-title">Informações da Empresa</h2>
                            <p class="step-description">Vamos começar com os dados básicos da sua empresa</p>
                        </div>

                        <div class="form-grid two-columns">
                            <div class="form-group">
                                <label class="form-label" for="nome_fantasia">
                                    Nome Fantasia <span class="required">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="nome_fantasia" 
                                    name="nome_fantasia" 
                                    class="form-input" 
                                    required 
                                    value="<?php echo isset($data['nome_fantasia']) ? htmlspecialchars($data['nome_fantasia']) : ''; ?>"
                                    placeholder="Ex: Minha Loja Incrível"
                                >
                                <div class="validation-message" id="nome_fantasia_msg"></div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="razao_social">
                                    Razão Social <span class="required">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="razao_social" 
                                    name="razao_social" 
                                    class="form-input" 
                                    required 
                                    value="<?php echo isset($data['razao_social']) ? htmlspecialchars($data['razao_social']) : ''; ?>"
                                    placeholder="Ex: Minha Loja Incrível LTDA"
                                >
                                <div class="validation-message" id="razao_social_msg"></div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="cnpj">
                                    CNPJ <span class="required">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="cnpj" 
                                    name="cnpj" 
                                    class="form-input" 
                                    required 
                                    value="<?php echo isset($data['cnpj']) ? htmlspecialchars($data['cnpj']) : ''; ?>"
                                    placeholder="00.000.000/0000-00"
                                >
                                <div class="validation-message" id="cnpj_msg"></div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="categoria">
                                    Categoria/Segmento <span class="required">*</span>
                                </label>
                                <select id="categoria" name="categoria" class="form-select" required>
                                    <option value="">Selecione sua categoria...</option>
                                    <?php foreach ($categorias as $categoria): ?>
                                        <option 
                                            value="<?php echo htmlspecialchars($categoria); ?>" 
                                            <?php echo (isset($data['categoria']) && $data['categoria'] == $categoria) ? 'selected' : ''; ?>
                                        >
                                            <?php echo htmlspecialchars($categoria); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="validation-message" id="categoria_msg"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Etapa 2: Dados de Contato -->
                    <div class="step" data-step="2">
                        <div class="step-header">
                            <h2 class="step-title">Dados de Contato</h2>
                            <p class="step-description">Como poderemos entrar em contato com você?</p>
                        </div>

                        <div class="form-grid two-columns">
                            <div class="form-group">
                                <label class="form-label" for="email">
                                    E-mail <span class="required">*</span>
                                </label>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    class="form-input" 
                                    required 
                                    value="<?php echo isset($data['email']) ? htmlspecialchars($data['email']) : ''; ?>"
                                    placeholder="contato@minhaloja.com.br"
                                >
                                <div class="validation-message" id="email_msg">Este será seu email de acesso ao sistema</div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="telefone">
                                    Telefone <span class="required">*</span>
                                </label>
                                <input 
                                    type="tel" 
                                    id="telefone" 
                                    name="telefone" 
                                    class="form-input" 
                                    required 
                                    value="<?php echo isset($data['telefone']) ? htmlspecialchars($data['telefone']) : ''; ?>"
                                    placeholder="(11) 99999-9999"
                                >
                                <div class="validation-message" id="telefone_msg"></div>
                            </div>
                            
                            <div class="form-group full-width">
                                <label class="form-label" for="website">Website (opcional)</label>
                                <input
                                    type="text"
                                    id="website"
                                    name="website"
                                    class="form-input"
                                    value="<?php echo isset($data['website']) ? htmlspecialchars($data['website']) : ''; ?>"
                                    placeholder="cleacasamentos.com.br ou https://www.minhaloja.com.br"
                                >
                                <div class="validation-message" id="website_msg"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Etapa 3: Logo da Loja -->
                    <div class="step" data-step="3">
                        <div class="step-header">
                            <h2 class="step-title">Logo da Sua Loja</h2>
                            <p class="step-description">Adicione a logo da sua loja para deixar seu perfil mais profissional</p>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Logo da Loja (opcional)</label>
                            <div class="logo-upload-container" id="logoUploadContainer">
                                <div class="upload-icon">📷</div>
                                <div class="upload-text">Clique para escolher ou arraste sua logo aqui</div>
                                <div class="upload-hint">Formatos aceitos: JPG, PNG, GIF • Máximo: 2MB</div>
                                <input type="file" id="logo" name="logo" accept="image/*" style="display: none;">
                            </div>
                            
                            <div class="logo-preview" id="logoPreview">
                                <h4>Preview da Logo:</h4>
                                <img id="logoPreviewImg" alt="Preview da logo">
                            </div>

                            <div class="info-card">
                                <h4>💡 Dica sobre a Logo:</h4>
                                <ul class="info-list">
                                    <li>Dimensões recomendadas: 300x300px (quadrada) ou 400x200px (retangular)</li>
                                    <li>A logo será exibida no catálogo de lojas parceiras</li>
                                    <li>Você pode adicionar ou alterar sua logo a qualquer momento</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Etapa 4: Dados de Acesso -->
                    <div class="step" data-step="4">
                        <div class="step-header">
                            <h2 class="step-title">Dados de Acesso</h2>
                            <p class="step-description">Crie suas credenciais para acessar o sistema</p>
                        </div>

                        <div class="form-grid two-columns">
                            <div class="form-group">
                                <label class="form-label" for="senha">
                                    Senha de Acesso <span class="required">*</span>
                                </label>
                                <input 
                                    type="password" 
                                    id="senha" 
                                    name="senha" 
                                    class="form-input" 
                                    required 
                                    minlength="8"
                                    placeholder="Mínimo 8 caracteres"
                                >
                                <div class="validation-message" id="senha_msg">Use letras, números e símbolos para maior segurança</div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="confirma_senha">
                                    Confirme a Senha <span class="required">*</span>
                                </label>
                                <input 
                                    type="password" 
                                    id="confirma_senha" 
                                    name="confirma_senha" 
                                    class="form-input" 
                                    required 
                                    minlength="8"
                                    placeholder="Digite novamente sua senha"
                                >
                                <div class="validation-message" id="confirma_senha_msg"></div>
                            </div>
                        </div>

                        <div class="info-card">
                            <h4>🔐 Sobre sua Conta:</h4>
                            <ul class="info-list">
                                <li>Use o mesmo email e senha para fazer login no sistema</li>
                                <li>Sua conta será ativada automaticamente quando a loja for aprovada</li>
                                <li>Você poderá alterar sua senha a qualquer momento</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Etapa 5: Endereço -->
                    <div class="step" data-step="5">
                        <div class="step-header">
                            <h2 class="step-title">Endereço da Loja</h2>
                            <p class="step-description">Informe onde sua loja está localizada</p>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="cep">
                                    CEP <span class="required">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="cep" 
                                    name="cep" 
                                    class="form-input" 
                                    value="<?php echo isset($data['endereco']['cep']) ? htmlspecialchars($data['endereco']['cep']) : ''; ?>"
                                    placeholder="00000-000"
                                >
                                <div class="validation-message" id="cep_msg"></div>
                            </div>
                        </div>

                        <div class="form-grid two-columns">
                            <div class="form-group">
                                <label class="form-label" for="logradouro">
                                    Logradouro <span class="required">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="logradouro" 
                                    name="logradouro" 
                                    class="form-input" 
                                    value="<?php echo isset($data['endereco']['logradouro']) ? htmlspecialchars($data['endereco']['logradouro']) : ''; ?>"
                                    placeholder="Rua, Avenida, etc."
                                >
                                <div class="validation-message" id="logradouro_msg"></div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="numero">
                                    Número <span class="required">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="numero" 
                                    name="numero" 
                                    class="form-input" 
                                    value="<?php echo isset($data['endereco']['numero']) ? htmlspecialchars($data['endereco']['numero']) : ''; ?>"
                                    placeholder="123"
                                >
                                <div class="validation-message" id="numero_msg"></div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="complemento">Complemento</label>
                                <input 
                                    type="text" 
                                    id="complemento" 
                                    name="complemento" 
                                    class="form-input" 
                                    value="<?php echo isset($data['endereco']['complemento']) ? htmlspecialchars($data['endereco']['complemento']) : ''; ?>"
                                    placeholder="Sala, Andar, etc."
                                >
                                <div class="validation-message" id="complemento_msg"></div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="bairro">
                                    Bairro <span class="required">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="bairro" 
                                    name="bairro" 
                                    class="form-input" 
                                    value="<?php echo isset($data['endereco']['bairro']) ? htmlspecialchars($data['endereco']['bairro']) : ''; ?>"
                                    placeholder="Centro, Vila Nova, etc."
                                >
                                <div class="validation-message" id="bairro_msg"></div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="cidade">
                                    Cidade <span class="required">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="cidade" 
                                    name="cidade" 
                                    class="form-input" 
                                    value="<?php echo isset($data['endereco']['cidade']) ? htmlspecialchars($data['endereco']['cidade']) : ''; ?>"
                                    placeholder="São Paulo"
                                >
                                <div class="validation-message" id="cidade_msg"></div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="estado">
                                    Estado <span class="required">*</span>
                                </label>
                                <select id="estado" name="estado" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($estados as $uf => $nomeEstado): ?>
                                        <option 
                                            value="<?php echo $uf; ?>" 
                                            <?php echo (isset($data['endereco']['estado']) && $data['endereco']['estado'] == $uf) ? 'selected' : ''; ?>
                                        >
                                            <?php echo $nomeEstado; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="validation-message" id="estado_msg"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Etapa 6: Termos e Configurações -->
                    <div class="step" data-step="6">
                        <div class="step-header">
                            <h2 class="step-title">Informações Finais</h2>
                            <p class="step-description">Conte-nos mais sobre sua loja e aceite nossos termos</p>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="descricao">Descrição da Loja (opcional)</label>
                            <textarea 
                                id="descricao" 
                                name="descricao" 
                                class="form-textarea" 
                                rows="4" 
                                placeholder="Conte um pouco sobre sua loja, produtos oferecidos, diferenciais..."
                            ><?php echo isset($data['descricao']) ? htmlspecialchars($data['descricao']) : ''; ?></textarea>
                            <div class="validation-message">Esta descrição será exibida para os clientes no catálogo de lojas parceiras</div>
                        </div>

                        <div class="info-card">
                            <h4>📊 Como Funciona o Cashback:</h4>
                            <ul class="info-list">
                                <li><strong>Comissão:</strong> Você paga 10% sobre cada venda</li>
                                <li><strong>Distribuição:</strong> 5% para o cliente (cashback) + 5% para o Klube Cash</li>
                                <li><strong>Fidelização:</strong> O cashback do cliente só pode ser usado na sua loja</li>
                                <li><strong>Gestão:</strong> Você terá acesso a um painel para gerenciar tudo</li>
                            </ul>
                        </div>

                        <div class="terms-section">
                            <h3 class="terms-title">Termos e Condições</h3>
                            <div class="terms-content">
                                <p><strong>Ao se cadastrar como loja parceira, você concorda com:</strong></p>
                                <ul style="margin-left: 20px; margin-top: 10px;">
                                    <li>• O Klube Cash analisará sua solicitação conforme nossos critérios de aprovação</li>
                                    <li>• Oferecimento de cashback conforme a porcentagem cadastrada (10% padrão)</li>
                                    <li>• Exibição da sua loja no catálogo de parceiros após aprovação</li>
                                    <li>• Processamento de todas as transações através do nosso sistema</li>
                                    <li>• Acesso ao painel para gerenciar transações e relatórios</li>
                                    <li>• Ativação automática da conta quando a loja for aprovada</li>
                                    <li>• Possibilidade de cancelamento da parceria por qualquer parte</li>
                                    <li>• Cumprimento das políticas de conduta e regulamentações aplicáveis</li>
                                </ul>
                            </div>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="aceite_termos" name="aceite_termos">
                                <label for="aceite_termos">
                                    Li e concordo com os termos e condições acima <span class="required">*</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Etapa 7: Resumo e Confirmação -->
                    <div class="step" data-step="7">
                        <div class="step-header">
                            <h2 class="step-title">Revisar e Confirmar</h2>
                            <p class="step-description">Verifique se todas as informações estão corretas antes de enviar</p>
                        </div>

                        <div class="summary-section">
                            <h3 class="summary-title">📋 Resumo do Cadastro</h3>
                            <div class="summary-grid" id="summaryContent">
                                <!-- Conteúdo será preenchido via JavaScript -->
                            </div>
                        </div>

                        <div class="info-card">
                            <h4>✅ Próximos Passos:</h4>
                            <ul class="info-list">
                                <li>Analisaremos sua solicitação em até 2 dias úteis</li>
                                <li>Você receberá um email com o resultado da análise</li>
                                <li>Se aprovado, sua conta será ativada automaticamente</li>
                                <li>Você poderá fazer login e começar a registrar vendas</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Navegação entre etapas -->
                    <div class="step-navigation">
                        <button type="button" class="btn btn-secondary" id="prevBtn" style="display: none;">
                            ← Voltar
                        </button>
                        
                        <div style="flex: 1;"></div>
                        
                        <button type="button" class="btn btn-primary" id="nextBtn">
                            Próximo →
                        </button>
                        
                        <button type="submit" class="btn btn-primary" id="submitBtn" style="display: none;">
                            🚀 Cadastrar Loja
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Estado do formulário progressivo
        let currentStep = 1;
        const totalSteps = 7;
        
        // Elementos do DOM
        const form = document.getElementById('store-form');
        const steps = document.querySelectorAll('.step');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const submitBtn = document.getElementById('submitBtn');
        const progressFill = document.getElementById('progressFill');
        const progressSteps = document.querySelectorAll('.progress-step');

        // Dados do formulário para navegação
        const formData = {};

        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            const formSubmitted = <?php echo isset($_SESSION['form_submitted']) ? 'true' : 'false'; ?>;
            if (formSubmitted) {
                clearDataFromLocalStorage();
                <?php unset($_SESSION['form_submitted']); ?>
            }

            loadDataFromLocalStorage();
            showStep(currentStep);
            setupEventListeners();
        });

        // Configurar event listeners
        function setupEventListeners() {
            // Navegação
            nextBtn.addEventListener('click', nextStep);
            prevBtn.addEventListener('click', prevStep);
            
            // Máscaras de entrada (mantidas originais)
            setupInputMasks();
            
            // Upload de logo
            setupLogoUpload();
            
            // Busca de CEP
            setupCepSearch();
            
            // Validação de senhas
            setupPasswordValidation();
            
            // Validação em tempo real
            setupRealtimeValidation();

            // Submit do formulário
            form.addEventListener('submit', handleFormSubmit);

            // Salvar dados no localStorage a cada alteração
            form.addEventListener('input', saveDataToLocalStorage);
        }

        // Salvar dados no localStorage
        function saveDataToLocalStorage() {
            const data = new FormData(form);
            const object = {};
            data.forEach((value, key) => {
                object[key] = value;
            });
            localStorage.setItem('storeRegistrationForm', JSON.stringify(object));
        }

        // Carregar dados do localStorage
        function loadDataFromLocalStorage() {
            const savedData = localStorage.getItem('storeRegistrationForm');
            if (savedData) {
                const data = JSON.parse(savedData);
                for (const key in data) {
                    if (form.elements[key]) {
                        if (form.elements[key].type !== 'file') {
                            form.elements[key].value = data[key];
                        }
                    }
                }
            }
        }

        // Limpar dados do localStorage
        function clearDataFromLocalStorage() {
            localStorage.removeItem('storeRegistrationForm');
        }

        // Mostrar etapa específica
        function showStep(step) {
            // Esconder todas as etapas
            steps.forEach(s => {
                s.classList.remove('active');
                s.classList.add('exiting');
            });
            
            // Mostrar etapa atual após um pequeno delay para animação
            setTimeout(() => {
                steps.forEach(s => s.classList.remove('exiting'));
                steps[step - 1].classList.add('active');
            }, 150);

            // Atualizar progresso
            updateProgress(step);
            
            // Atualizar botões de navegação
            updateNavigationButtons(step);
            
            // Focar no primeiro campo da etapa
            setTimeout(() => {
                const firstInput = steps[step - 1].querySelector('input, select, textarea');
                if (firstInput) firstInput.focus();
            }, 200);

            // Se for a última etapa, preencher resumo
            if (step === totalSteps) {
                fillSummary();
            }
        }

        // Atualizar barra de progresso
        function updateProgress(step) {
            const progress = (step / totalSteps) * 100;
            progressFill.style.width = progress + '%';
            
            progressSteps.forEach((ps, index) => {
                if (index + 1 <= step) {
                    ps.classList.add('active');
                } else {
                    ps.classList.remove('active');
                }
            });
        }

        // Atualizar botões de navegação
        function updateNavigationButtons(step) {
            prevBtn.style.display = step > 1 ? 'inline-flex' : 'none';
            nextBtn.style.display = step < totalSteps ? 'inline-flex' : 'none';
            submitBtn.style.display = step === totalSteps ? 'inline-flex' : 'none';
        }

        // Avançar para próxima etapa
        function nextStep() {
            if (validateCurrentStep()) {
                saveCurrentStepData();
                if (currentStep < totalSteps) {
                    currentStep++;
                    showStep(currentStep);
                }
            }
        }

        // Voltar para etapa anterior
        function prevStep() {
            if (currentStep > 1) {
                currentStep--;
                showStep(currentStep);
            }
        }

        // Validar etapa atual
        function validateCurrentStep() {
            const currentStepElement = steps[currentStep - 1];
            const requiredFields = currentStepElement.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    showFieldError(field, 'Este campo é obrigatório');
                    isValid = false;
                } else {
                    clearFieldError(field);
                    
                    // Validações específicas
                    if (field.type === 'email' && !isValidEmail(field.value)) {
                        showFieldError(field, 'Email inválido');
                        isValid = false;
                    }
                }
            });

            // Validação específica para etapa de senhas
            if (currentStep === 4) {
                const senha = document.getElementById('senha');
                const confirmaSenha = document.getElementById('confirma_senha');
                
                if (senha.value !== confirmaSenha.value) {
                    showFieldError(confirmaSenha, 'As senhas não coincidem');
                    isValid = false;
                }
                
                if (senha.value.length < 8) {
                    showFieldError(senha, 'A senha deve ter pelo menos 8 caracteres');
                    isValid = false;
                }
            }

            // Validação para etapa de termos
            if (currentStep === 6) {
                const termos = document.getElementById('aceite_termos');
                if (!termos.checked) {
                    alert('Você precisa aceitar os termos e condições para continuar');
                    isValid = false;
                }
            }

            return isValid;
        }

        // Salvar dados da etapa atual
        function saveCurrentStepData() {
            const currentStepElement = steps[currentStep - 1];
            const inputs = currentStepElement.querySelectorAll('input, select, textarea');
            
            inputs.forEach(input => {
                if (input.type !== 'file') {
                    formData[input.name] = input.value;
                }
            });
        }

        // Preencher resumo final
        function fillSummary() {
            const summaryContent = document.getElementById('summaryContent');
            
            const empresa = formData.nome_fantasia || document.getElementById('nome_fantasia').value;
            const cnpj = formData.cnpj || document.getElementById('cnpj').value;
            const email = formData.email || document.getElementById('email').value;
            const telefone = formData.telefone || document.getElementById('telefone').value;
            const categoria = formData.categoria || document.getElementById('categoria').value;
            const endereco = `${formData.logradouro || document.getElementById('logradouro').value}, ${formData.numero || document.getElementById('numero').value} - ${formData.cidade || document.getElementById('cidade').value}/${formData.estado || document.getElementById('estado').value}`;

            summaryContent.innerHTML = `
                <div class="summary-item">
                    <div class="summary-label">Empresa</div>
                    <div class="summary-value">${empresa}</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">CNPJ</div>
                    <div class="summary-value">${cnpj}</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Email</div>
                    <div class="summary-value">${email}</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Telefone</div>
                    <div class="summary-value">${telefone}</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Categoria</div>
                    <div class="summary-value">${categoria}</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Endereço</div>
                    <div class="summary-value">${endereco}</div>
                </div>
            `;
        }

        // Configurar máscaras de entrada (mantidas originais)
        function setupInputMasks() {
            // Máscara para CNPJ
            document.getElementById('cnpj').addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                
                if (value.length <= 14) {
                    value = value.replace(/^(\d{2})(\d)/, '$1.$2');
                    value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
                    value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
                    value = value.replace(/(\d{4})(\d)/, '$1-$2');
                }
                
                e.target.value = value;
            });

            // Máscara para telefone
            document.getElementById('telefone').addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                
                if (value.length <= 11) {
                    if (value.length > 2) {
                        value = '(' + value.substring(0, 2) + ') ' + value.substring(2);
                    }
                    if (value.length > 10) {
                        value = value.substring(0, 10) + '-' + value.substring(10);
                    }
                }
                
                e.target.value = value;
            });

            // Máscara para CEP
            document.getElementById('cep').addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                
                if (value.length > 5) {
                    value = value.replace(/^(\d{5})(\d)/, '$1-$2');
                }
                
                e.target.value = value;
            });
        }

        // Configurar upload de logo (mantido original)
        function setupLogoUpload() {
            const logoInput = document.getElementById('logo');
            const uploadContainer = document.getElementById('logoUploadContainer');
            const preview = document.getElementById('logoPreview');
            const previewImg = document.getElementById('logoPreviewImg');

            uploadContainer.addEventListener('click', () => logoInput.click());

            uploadContainer.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadContainer.style.borderColor = 'var(--primary-color)';
                uploadContainer.style.backgroundColor = 'var(--primary-light)';
            });

            uploadContainer.addEventListener('dragleave', (e) => {
                e.preventDefault();
                uploadContainer.style.borderColor = '#D1D5DB';
                uploadContainer.style.backgroundColor = '';
            });

            uploadContainer.addEventListener('drop', (e) => {
                e.preventDefault();
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    handleLogoFile(files[0]);
                }
            });

            logoInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    handleLogoFile(e.target.files[0]);
                }
            });

            function handleLogoFile(file) {
                // Validar tamanho
                if (file.size > 2 * 1024 * 1024) {
                    alert('Arquivo muito grande! O tamanho máximo é 2MB.');
                    return;
                }

                // Validar tipo
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Tipo de arquivo não permitido! Use apenas JPG, PNG ou GIF.');
                    return;
                }

                // Mostrar preview
                const reader = new FileReader();
                reader.onload = (e) => {
                    previewImg.src = e.target.result;
                    preview.classList.add('active');
                    uploadContainer.classList.add('has-file');
                };
                reader.readAsDataURL(file);

                logoInput.files = e.dataTransfer ? e.dataTransfer.files : logoInput.files;
            }
        }

        // Configurar busca de CEP
        function setupCepSearch() {
            const cepInput = document.getElementById('cep');
            const logradouroInput = document.getElementById('logradouro');
            const bairroInput = document.getElementById('bairro');
            const cidadeInput = document.getElementById('cidade');
            const estadoInput = document.getElementById('estado');

            // Armazenar o texto original da mensagem de validação do CEP
            const cepMsgElement = document.getElementById('cep_msg');
            if (cepMsgElement && !cepMsgElement.dataset.originalText) {
                cepMsgElement.dataset.originalText = cepMsgElement.textContent;
            }

            cepInput.addEventListener('blur', function() {
                const cep = this.value.replace(/\D/g, '');

                // Limpa campos de endereço se o CEP for inválido ou vazio
                if (cep.length !== 8) {
                    clearAddressFields();
                    if (cep.length > 0) {
                        showFieldError(cepInput, 'CEP inválido. Digite 8 dígitos.');
                    } else {
                        clearFieldError(cepInput);
                    }
                    return;
                }

                // Adiciona classe de loading e desabilita o campo
                cepInput.classList.add('loading-cep');
                cepInput.disabled = true;
                showFieldError(cepInput, 'Buscando CEP...', 'info'); // Mensagem de busca

                fetch(`https://viacep.com.br/ws/${cep}/json/`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Erro de rede ou servidor ViaCEP');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (!data.erro) {
                            logradouroInput.value = data.logradouro || '';
                            bairroInput.value = data.bairro || '';
                            cidadeInput.value = data.localidade || '';
                            estadoInput.value = data.uf || '';
                            clearFieldError(cepInput); // Limpa qualquer erro anterior

                            // Foca no número se o logradouro foi preenchido
                            if (data.logradouro) {
                                document.getElementById('numero').focus();
                            }
                        } else {
                            clearAddressFields();
                            showFieldError(cepInput, 'CEP não encontrado. Verifique se o CEP está correto.');
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao buscar CEP:', error);
                        clearAddressFields();
                        showFieldError(cepInput, 'Erro ao buscar CEP. Verifique sua conexão ou tente novamente.');
                    })
                    .finally(() => {
                        cepInput.classList.remove('loading-cep');
                        cepInput.disabled = false;
                        // Restaura a mensagem original se não houver erro
                        if (!cepInput.classList.contains('error')) {
                            clearFieldError(cepInput);
                        }
                    });
            });

            function clearAddressFields() {
                logradouroInput.value = '';
                bairroInput.value = '';
                cidadeInput.value = '';
                estadoInput.value = '';
            }
        }

        // Configurar validação de senhas (mantida original)
        function setupPasswordValidation() {
            const senha = document.getElementById('senha');
            const confirmaSenha = document.getElementById('confirma_senha');

            function validatePasswords() {
                if (confirmaSenha.value.length === 0) {
                    clearFieldError(confirmaSenha);
                    return true;
                }
                
                if (senha.value !== confirmaSenha.value) {
                    showFieldError(confirmaSenha, 'As senhas não coincidem');
                    return false;
                } else {
                    clearFieldError(confirmaSenha);
                    return true;
                }
            }

            confirmaSenha.addEventListener('input', validatePasswords);
            senha.addEventListener('input', validatePasswords);
        }

        // Configurar validação em tempo real
        function setupRealtimeValidation() {
            const inputs = document.querySelectorAll('.form-input, .form-select');

            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    if (this.hasAttribute('required') && !this.value.trim()) {
                        showFieldError(this, 'Este campo é obrigatório');
                    } else {
                        clearFieldError(this);

                        // Validações específicas
                        if (this.type === 'email' && this.value && !isValidEmail(this.value)) {
                            showFieldError(this, 'Email inválido');
                        }

                        // Validação para website
                        if (this.id === 'website' && this.value && !isValidWebsite(this.value)) {
                            showFieldError(this, 'Website inválido. Use o formato: exemplo.com.br ou https://exemplo.com.br');
                        }
                    }
                });

                input.addEventListener('input', function() {
                    if (this.classList.contains('error')) {
                        clearFieldError(this);
                    }
                });
            });
        }

        // Lidar com envio do formulário
        function handleFormSubmit(e) {
            if (!validateCurrentStep()) {
                e.preventDefault();
                return;
            }

            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
        }

        // Funções utilitárias
        function showFieldError(field, message, type = 'error') {
            const msgElement = field.parentNode.querySelector('.validation-message');
            if (msgElement) {
                // Salva o texto original se ainda não foi salvo
                if (!msgElement.dataset.originalText) {
                    msgElement.dataset.originalText = msgElement.textContent;
                }
                msgElement.textContent = message;
                msgElement.classList.remove('error', 'success', 'info');
                msgElement.classList.add(type);
            }
            if (type === 'error') {
                field.classList.add('error');
                field.classList.remove('valid');
            } else {
                field.classList.remove('error');
                field.classList.add('valid');
            }
        }

        function clearFieldError(field) {
            field.classList.remove('error');
            field.classList.add('valid');
            const msgElement = field.parentNode.querySelector('.validation-message');
            if (msgElement) {
                msgElement.classList.remove('error', 'success', 'info');
                // Restaura o texto original se existir
                if (msgElement.dataset.originalText) {
                    msgElement.textContent = msgElement.dataset.originalText;
                } else {
                    msgElement.textContent = '';
                }
            }
        }

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        function isValidWebsite(website) {
            if (!website || !website.trim()) return true; // Campo opcional

            // Remove espaços
            website = website.trim();

            // Se não começar com protocolo, adiciona https://
            if (!website.match(/^https?:\/\//)) {
                website = 'https://' + website;
            }

            // Validação de URL mais permissiva
            const urlRegex = /^https?:\/\/([\w-]+\.)+[\w-]+(\/.*)?$/;
            return urlRegex.test(website);
        }

        // Navegação por teclado
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                const activeElement = document.activeElement;
                if (activeElement.tagName !== 'TEXTAREA' && activeElement.type !== 'submit') {
                    e.preventDefault();
                    if (currentStep < totalSteps) {
                        nextStep();
                    }
                }
            }
        });


    </script>

    <?php debug_log("Página renderizada com sucesso"); ?>
</body>
</html>