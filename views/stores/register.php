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
<html lang="pt-BR" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Loja Parceira - Klube Cash</title>
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars(
        SITE_URL . '/assets/images/icons/KlubeCashLOGO.ico?v=' .
        (file_exists(__DIR__ . '/../../assets/images/icons/KlubeCashLOGO.ico')
            ? filemtime(__DIR__ . '/../../assets/images/icons/KlubeCashLOGO.ico')
            : '1'),
        ENT_QUOTES,
        'UTF-8'
    ); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <meta name="theme-color" id="themeColor" content="#FFF8F3" data-theme-light="#FFF8F3" data-theme-dark="#0B0D12">
    <script>
        (function () {
            var theme = 'light';
            try {
                var savedTheme = localStorage.getItem('klubecash-theme');
                theme = savedTheme === 'light' || savedTheme === 'dark'
                    ? savedTheme
                    : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            } catch (error) {
                theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            document.documentElement.dataset.theme = theme;
            document.documentElement.style.colorScheme = theme;
            document.querySelector('meta[name="theme-color"]').setAttribute(
                'content',
                theme === 'dark' ? '#0B0D12' : '#FFF8F3'
            );
        }());
    </script>

    <link rel="stylesheet" href="<?php echo htmlspecialchars(
        SITE_URL . '/assets/css/views/stores/register-modern.css?v=' .
        (file_exists(__DIR__ . '/../../assets/css/views/stores/register-modern.css')
            ? filemtime(__DIR__ . '/../../assets/css/views/stores/register-modern.css')
            : '1'),
        ENT_QUOTES,
        'UTF-8'
    ); ?>">
    <script defer src="<?php echo htmlspecialchars(
        SITE_URL . '/assets/js/store-register-modern.js?v=' .
        (file_exists(__DIR__ . '/../../assets/js/store-register-modern.js')
            ? filemtime(__DIR__ . '/../../assets/js/store-register-modern.js')
            : '1'),
        ENT_QUOTES,
        'UTF-8'
    ); ?>"></script>
</head>

<body class="store-registration-page" data-form-submitted="<?php echo isset($_SESSION['form_submitted']) ? 'true' : 'false'; ?>">
    <?php if (isset($_SESSION['form_submitted'])) unset($_SESSION['form_submitted']); ?>
    <div class="registration-atmosphere" aria-hidden="true"></div>
    <!-- Container principal do cadastro -->
    <div class="registration-container">
        <div class="registration-shell">
            <div class="registration-topbar">
                <div class="registration-brand">
                    <img src="<?php echo htmlspecialchars(
                        SITE_URL . '/assets/images/logolaranja.png?v=' .
                        (file_exists(__DIR__ . '/../../assets/images/logolaranja.png')
                            ? filemtime(__DIR__ . '/../../assets/images/logolaranja.png')
                            : '1'),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>" alt="Klube Cash">
                </div>
                <button type="button" class="theme-toggle" id="themeToggle" aria-label="Ativar modo noturno" aria-pressed="false">
                    <span class="theme-icon theme-icon-sun" aria-hidden="true">☀</span>
                    <span class="theme-icon theme-icon-moon" aria-hidden="true">☾</span>
                </button>
            </div>
        <div class="registration-card">
            <!-- Header com progresso -->
            <div class="registration-header">
                <h1 class="registration-title">Cadastro de Loja Parceira</h1>
                <p class="registration-subtitle">Torne-se nosso parceiro em poucos passos simples</p>
                
                <div class="progress-container">
                    <div class="progress-bar" role="progressbar" aria-label="Progresso do cadastro" aria-valuemin="1" aria-valuemax="7" aria-valuenow="1">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                    <div class="progress-steps" role="list">
                        <span class="progress-step active" data-step="1" role="listitem" aria-current="step">Empresa</span>
                        <span class="progress-step" data-step="2" role="listitem">Contato</span>
                        <span class="progress-step" data-step="3" role="listitem">Logo</span>
                        <span class="progress-step" data-step="4" role="listitem">Acesso</span>
                        <span class="progress-step" data-step="5" role="listitem">Endereço</span>
                        <span class="progress-step" data-step="6" role="listitem">Termos</span>
                        <span class="progress-step" data-step="7" role="listitem">Revisão</span>
                    </div>
                </div>
            </div>

            <!-- Conteúdo do formulário -->
            <div class="registration-content">
                <!-- Alertas de erro/sucesso -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <span class="alert-icon">⚠️</span>
                        <div><?php echo $error; ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success" role="status">
                        <span class="alert-icon">✅</span>
                        <div>
                            <?php echo htmlspecialchars($success); ?>
                            <div class="success-next-steps">
                                <strong>Próximos passos:</strong><br>
                                • Sua solicitação foi recebida e está em análise<br>
                                • Você receberá um email quando sua loja for aprovada<br>
                                • Após aprovação, poderá fazer login no sistema
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Formulário progressivo -->
                <div class="sr-only" id="formStatus" aria-live="polite" aria-atomic="true"></div>
                <form method="post" action="" id="store-form" enctype="multipart/form-data">
                    
                    <!-- Etapa 1: Informações da Empresa -->
                    <div class="step active" data-step="1" id="registration-step-1" aria-hidden="false">
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
                    <div class="step" data-step="2" id="registration-step-2" aria-hidden="true">
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
                    <div class="step" data-step="3" id="registration-step-3" aria-hidden="true">
                        <div class="step-header">
                            <h2 class="step-title">Logo da Sua Loja</h2>
                            <p class="step-description">Adicione a logo da sua loja para deixar seu perfil mais profissional</p>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="logo">Logo da Loja (opcional)</label>
                            <div class="logo-upload-container" id="logoUploadContainer" role="button" tabindex="0" aria-controls="logo" aria-describedby="logoUploadHint">
                                <div class="upload-icon">📷</div>
                                <div class="upload-text">Clique para escolher ou arraste sua logo aqui</div>
                                <div class="upload-hint" id="logoUploadHint">Formatos aceitos: JPG, PNG, GIF • Máximo: 2MB</div>
                                <input type="file" id="logo" name="logo" accept="image/*" class="file-input-hidden" tabindex="-1">
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
                    <div class="step" data-step="4" id="registration-step-4" aria-hidden="true">
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
                    <div class="step" data-step="5" id="registration-step-5" aria-hidden="true">
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
                    <div class="step" data-step="6" id="registration-step-6" aria-hidden="true">
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
                                <ul class="terms-list">
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
                    <div class="step" data-step="7" id="registration-step-7" aria-hidden="true">
                        <div class="step-header">
                            <h2 class="step-title">Revisar e Confirmar</h2>
                            <p class="step-description">Verifique se todas as informações estão corretas antes de enviar</p>
                        </div>

                        <div class="summary-section">
                            <h3 class="summary-title">📋 Resumo do Cadastro</h3>
                            <div class="summary-grid" id="summaryContent" aria-live="polite">
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
                        <button type="button" class="btn btn-secondary" id="prevBtn" hidden>
                            ← Voltar
                        </button>
                        
                        <div class="navigation-spacer" aria-hidden="true"></div>
                        
                        <button type="button" class="btn btn-primary" id="nextBtn">
                            Próximo →
                        </button>
                        
                        <button type="submit" class="btn btn-primary" id="submitBtn" hidden>
                            🚀 Cadastrar Loja
                        </button>
                    </div>
                </form>
            </div>
        </div>
        </div>
    </div>


    <?php debug_log("Página renderizada com sucesso"); ?>
</body>
</html>
