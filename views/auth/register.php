<?php header("Content-Security-Policy: connect-src 'self' https://viacep.com.br"); ?>
<?php
// Arquivo: views/auth/register.php
// Incluir arquivos de configuração
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../config/email.php';
require_once '../../utils/Validator.php';

// Verificar se já existe uma sessão ativa
session_start();
if (isset($_SESSION['user_id'])) {
    // Redirecionar com base no tipo de usuário
    if ($_SESSION['user_type'] == USER_TYPE_ADMIN) {
        header('Location: ' . ADMIN_DASHBOARD_URL);
    } else {
        header('Location: ' . CLIENT_DASHBOARD_URL);
    }
    exit;
}

// Processar o formulário de registro
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Capturar e sanitizar dados do formulário
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_STRING);
    $telefone = filter_input(INPUT_POST, 'telefone', FILTER_SANITIZE_STRING);
    $senha = $_POST['senha'] ?? '';

    // Validar campos
    $errors = [];

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email inválido';
    }

    if (empty($nome) || strlen($nome) < 3) {
        $errors[] = 'Nome precisa ter pelo menos 3 caracteres';
    }

    if (empty($telefone) || strlen($telefone) < 10) {
        $errors[] = 'Telefone inválido';
    }

    if (empty($senha) || strlen($senha) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'A senha deve ter no mínimo ' . PASSWORD_MIN_LENGTH . ' caracteres';
    }

    // Se não houver erros, prosseguir com o registro
    if (empty($errors)) {
        try {
            $db = Database::getConnection();

            // Verificar se o email já existe
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $error = 'Este email já está cadastrado. Por favor, use outro ou faça login.';
            } else {
                // Hash da senha
                $senha_hash = password_hash($senha, PASSWORD_BCRYPT);

                // Inserir novo usuário
                $stmt = $db->prepare("INSERT INTO usuarios (nome, email, senha_hash, tipo, telefone, status, data_criacao) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $result = $stmt->execute([$nome, $email, $senha_hash, USER_TYPE_CLIENT, $telefone, USER_ACTIVE]);

                if ($result) {
                    $user_id = $db->lastInsertId();

                    // Tentar enviar email (não crítico)
                    try {
                        if (class_exists('Email')) {
                            Email::sendWelcome($email, $nome);
                        }
                    } catch (Exception $e) {
                        error_log("Erro email: " . $e->getMessage());
                    }

                    // Redirecionar para login
                    header('Location: /login?success=cadastro_realizado');
                    exit;
                }
            }
        } catch (PDOException $e) {
            $error = 'Erro ao processar o cadastro. Tente novamente.';
            // Log do erro para debug
            error_log('Erro no registro: ' . $e->getMessage());
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta - Klube Cash</title>
    <link rel="stylesheet" href="../../assets/css/auth.css">
    <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Reset e variáveis */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #FF7A00;
            --primary-dark: #E86E00;
            --white: #FFFFFF;
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-300: #D1D5DB;
            --gray-400: #9CA3AF;
            --gray-500: #6B7280;
            --gray-600: #4B5563;
            --gray-700: #374151;
            --gray-800: #1F2937;
            --gray-900: #111827;
            --success: #10B981;
            --error: #EF4444;
            --warning: #F59E0B;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --radius-sm: 6px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--primary-color) 0%, #FF9500 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            line-height: 1.6;
        }

        /* Container principal */
        .register-container {
            background: var(--white);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            width: 100%;
            max-width: 480px;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }

        .register-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), #FF9500, var(--primary-color));
            animation: gradientShift 3s ease-in-out infinite;
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        /* Header */
        .register-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .brand-logo {
            width: 60px;
            height: 60px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, var(--primary-color), #FF9500);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-md);
        }

        .brand-logo img {
            width: 36px;
            height: 36px;
            filter: brightness(0) invert(1);
        }

        .main-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 8px;
            letter-spacing: -0.025em;
        }

        .main-title .highlight {
            background: linear-gradient(135deg, var(--primary-color), #FF9500);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .subtitle {
            font-size: 16px;
            color: var(--gray-500);
            font-weight: 400;
        }

        /* Login link */
        .login-prompt {
            text-align: center;
            margin-bottom: 32px;
            padding: 16px;
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200);
        }

        .login-prompt span {
            color: var(--gray-600);
            font-size: 14px;
        }

        .login-prompt a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            margin-left: 4px;
            transition: color 0.2s ease;
        }

        .login-prompt a:hover {
            color: var(--primary-dark);
        }

        /* Progress indicator */
        .progress-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 32px;
        }

        .progress-step {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--gray-200);
            margin: 0 4px;
            transition: all 0.3s ease;
        }

        .progress-step.active {
            background: var(--primary-color);
            transform: scale(1.2);
        }

        /* Formulário */
        .register-form {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .form-section {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-700);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
        }

        .section-number {
            background: var(--primary-color);
            color: var(--white);
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            margin-right: 8px;
        }

        /* Input groups */
        .input-group {
            position: relative;
        }

        .input-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 16px;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-lg);
            font-size: 16px;
            font-weight: 400;
            color: var(--gray-900);
            background: var(--white);
            transition: all 0.2s ease;
            outline: none;
        }

        .form-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 122, 0, 0.1);
        }

        .form-input:valid {
            border-color: var(--success);
        }

        .form-input.error {
            border-color: var(--error);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        /* Input icons */
        .input-icon {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            transition: color 0.2s ease;
        }

        .form-input:focus + .input-icon {
            color: var(--primary-color);
        }

        /* Senha com indicador de força */
        .password-wrapper {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-400);
            cursor: pointer;
            padding: 4px;
            border-radius: var(--radius-sm);
            transition: all 0.2s ease;
        }

        .password-toggle:hover {
            color: var(--gray-600);
            background: var(--gray-100);
        }

        .password-strength {
            margin-top: 8px;
            display: none;
        }

        .password-strength.show {
            display: block;
        }

        .strength-bar {
            height: 4px;
            background: var(--gray-200);
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .strength-fill {
            height: 100%;
            background: var(--error);
            border-radius: 2px;
            transition: all 0.3s ease;
            width: 0%;
        }

        .strength-fill.weak { background: var(--error); width: 25%; }
        .strength-fill.fair { background: var(--warning); width: 50%; }
        .strength-fill.good { background: var(--primary-color); width: 75%; }
        .strength-fill.strong { background: var(--success); width: 100%; }

        .strength-text {
            font-size: 12px;
            color: var(--gray-500);
        }

        /* Grid para campos lado a lado */
        .input-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        @media (max-width: 640px) {
            .input-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }

        /* Botão de submit */
        .submit-button {
            background: linear-gradient(135deg, var(--primary-color), #FF9500);
            color: var(--white);
            border: none;
            border-radius: var(--radius-lg);
            padding: 18px 24px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-top: 8px;
        }

        .submit-button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .submit-button:active {
            transform: translateY(0);
        }

        .submit-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .button-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .loading-spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid var(--white);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: none;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Mensagens de feedback */
        .alert {
            padding: 16px;
            border-radius: var(--radius-lg);
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 500;
            border: 1px solid;
        }

        .alert-success {
            background: #F0FDF4;
            border-color: #BBF7D0;
            color: #166534;
        }

        .alert-error {
            background: #FEF2F2;
            border-color: #FECACA;
            color: #991B1B;
        }

        /* Benefícios */
        .benefits {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid var(--gray-200);
        }

        .benefits-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 16px;
            text-align: center;
        }

        .benefits-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .benefit-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: var(--gray-50);
            border-radius: var(--radius-md);
        }

        .benefit-icon {
            width: 24px;
            height: 24px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 12px;
            flex-shrink: 0;
        }

        .benefit-text {
            font-size: 13px;
            color: var(--gray-600);
            font-weight: 500;
        }

        /* Responsividade */
        @media (max-width: 640px) {
            body {
                padding: 16px;
            }

            .register-container {
                padding: 24px;
                max-width: 100%;
            }

            .main-title {
                font-size: 24px;
            }

            .subtitle {
                font-size: 14px;
            }

            .input-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .register-container {
                padding: 20px;
                border-radius: var(--radius-lg);
            }

            .main-title {
                font-size: 22px;
            }

            .form-input {
                padding: 14px;
                font-size: 16px; /* Evita zoom no iOS */
            }

            .submit-button {
                padding: 16px 20px;
            }
        }

        /* Animações */
        .register-container {
            animation: slideUp 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-section {
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Estados de foco melhorados */
        .form-input:focus {
            transform: translateY(-1px);
        }

        .input-group:focus-within .input-label {
            color: var(--primary-color);
        }

        /* Indicador visual para campo preenchido */
        .form-input:valid:not(:placeholder-shown) {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%2310B981' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 16px;
            padding-right: 48px;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <!-- Header com logo e títulos -->
        <div class="register-header">
            <div class="brand-logo">
                <img src="../../assets/images/logo-icon.png" alt="Klube Cash">
            </div>
            <h1 class="main-title">Crie sua <span class="highlight">conta</span></h1>
            <p class="subtitle">Comece a ganhar dinheiro de volta em suas compras</p>
        </div>

        <!-- Link para login -->
        <div class="login-prompt">
            <span>Já tem uma conta?</span>
            <a href="<?php echo LOGIN_URL; ?>">Fazer login</a>
        </div>

        <!-- Indicador de progresso visual -->
        <div class="progress-indicator">
            <div class="progress-step active"></div>
            <div class="progress-step"></div>
            <div class="progress-step"></div>
        </div>

        <!-- Mensagens de feedback -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
                <br><a href="<?php echo LOGIN_URL; ?>" style="color: #166534; font-weight: 600;">Clique aqui para fazer login</a>
            </div>
        <?php endif; ?>

        <!-- Formulário principal -->
        <form method="post" action="" id="register-form" class="register-form">
            <!-- Seção 1: Informações básicas -->
            <div class="form-section">
                <h3 class="section-title">
                    <span class="section-number">1</span>
                    Suas informações
                </h3>

                <div class="input-group">
                    <label for="nome" class="input-label">Nome completo</label>
                    <div class="input-wrapper">
                        <input 
                            type="text" 
                            id="nome" 
                            name="nome" 
                            class="form-input" 
                            placeholder="Digite seu nome completo"
                            required 
                            minlength="3"
                            value="<?php echo isset($nome) ? htmlspecialchars($nome) : ''; ?>"
                        >
                        <span class="input-icon">👤</span>
                    </div>
                </div>

                <div class="input-group">
                    <label for="email" class="input-label">Email</label>
                    <div class="input-wrapper">
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-input" 
                            placeholder="seu@email.com"
                            required
                            value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>"
                        >
                        <span class="input-icon">📧</span>
                    </div>
                </div>

                <div class="input-group">
                    <label for="telefone" class="input-label">Telefone</label>
                    <div class="input-wrapper">
                        <input 
                            type="tel" 
                            id="telefone" 
                            name="telefone" 
                            class="form-input" 
                            placeholder="(00) 00000-0000"
                            required
                            value="<?php echo isset($telefone) ? htmlspecialchars($telefone) : ''; ?>"
                        >
                        <span class="input-icon">📱</span>
                    </div>
                </div>
            </div>

            <!-- Seção 2: Segurança -->
            <div class="form-section">
                <h3 class="section-title">
                    <span class="section-number">2</span>
                    Crie sua senha
                </h3>

                <div class="input-group">
                    <label for="senha" class="input-label">Senha</label>
                    <div class="password-wrapper">
                        <input 
                            type="password" 
                            id="senha" 
                            name="senha" 
                            class="form-input" 
                            placeholder="Crie uma senha segura"
                            required
                            minlength="<?php echo PASSWORD_MIN_LENGTH; ?>"
                        >
                        <button type="button" class="password-toggle" id="passwordToggle">
                            👁️
                        </button>
                    </div>
                    
                    <div class="password-strength" id="passwordStrength">
                        <div class="strength-bar">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                        <p class="strength-text" id="strengthText">Digite uma senha para ver a força</p>
                    </div>
                </div>
            </div>

            <!-- Botão de submit -->
            <button type="submit" class="submit-button" id="submitButton">
                <div class="button-content">
                    <div class="loading-spinner" id="loadingSpinner"></div>
                    <span id="buttonText">Criar minha conta gratuita</span>
                </div>
            </button>
        </form>

        <!-- Benefícios -->
        <div class="benefits">
            <h4 class="benefits-title">Por que escolher o Klube Cash?</h4>
            <div class="benefits-list">
                <div class="benefit-item">
                    <div class="benefit-icon">💰</div>
                    <span class="benefit-text">Cashback real</span>
                </div>
                <div class="benefit-item">
                    <div class="benefit-icon">⚡</div>
                    <span class="benefit-text">Processo rápido e seguro</span>
                </div>
                <div class="benefit-item">
                    <div class="benefit-icon">🎯</div>
                    <span class="benefit-text">Muitas de lojas parceiras</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Indicador de progresso do formulário
        function updateProgress() {
            const steps = document.querySelectorAll('.progress-step');
            const nome = document.getElementById('nome').value;
            const email = document.getElementById('email').value;
            const telefone = document.getElementById('telefone').value;
            const senha = document.getElementById('senha').value;

            let activeSteps = 0;

            if (nome && email) activeSteps = 1;
            if (nome && email && telefone) activeSteps = 2;
            if (nome && email && telefone && senha) activeSteps = 3;

            steps.forEach((step, index) => {
                step.classList.toggle('active', index < activeSteps);
            });
        }

        // Verificador de força da senha
        function checkPasswordStrength(password) {
            const strengthIndicator = document.getElementById('passwordStrength');
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');

            if (!password) {
                strengthIndicator.classList.remove('show');
                return;
            }

            strengthIndicator.classList.add('show');

            let strength = 0;
            let feedback = [];

            // Critérios de força
            if (password.length >= 8) strength++;
            else feedback.push('pelo menos 8 caracteres');

            if (/[a-z]/.test(password)) strength++;
            else feedback.push('letras minúsculas');

            if (/[A-Z]/.test(password)) strength++;
            else feedback.push('letras maiúsculas');

            if (/[0-9]/.test(password)) strength++;
            else feedback.push('números');

            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            else feedback.push('símbolos');

            // Atualizar visual
            const levels = ['weak', 'weak', 'fair', 'good', 'strong'];
            const texts = ['Muito fraca', 'Fraca', 'Regular', 'Boa', 'Muito forte'];
            
            strengthFill.className = `strength-fill ${levels[strength]}`;
            
            if (strength < 3 && feedback.length > 0) {
                strengthText.textContent = `Adicione: ${feedback.slice(0, 2).join(', ')}`;
            } else {
                strengthText.textContent = texts[strength];
            }
        }

        // Toggle de visibilidade da senha
        document.getElementById('passwordToggle').addEventListener('click', function() {
            const passwordField = document.getElementById('senha');
            const isPassword = passwordField.type === 'password';
            
            passwordField.type = isPassword ? 'text' : 'password';
            this.textContent = isPassword ? '🙈' : '👁️';
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
            updateProgress();
        });

        // Event listeners para atualizações
        ['nome', 'email', 'telefone', 'senha'].forEach(id => {
            document.getElementById(id).addEventListener('input', function() {
                updateProgress();
                if (id === 'senha') {
                    checkPasswordStrength(this.value);
                }
            });
        });

        // Validação do formulário
        document.getElementById('register-form').addEventListener('submit', function(event) {
            const button = document.getElementById('submitButton');
            const buttonText = document.getElementById('buttonText');
            const spinner = document.getElementById('loadingSpinner');
            
            const email = document.getElementById('email').value;
            const nome = document.getElementById('nome').value;
            const telefone = document.getElementById('telefone').value;
            const senha = document.getElementById('senha').value;

            let isValid = true;
            let errorMessage = '';

            // Validações básicas (mantendo a lógica original)
            if (!email || !isValidEmail(email)) {
                errorMessage = 'Por favor, informe um email válido.';
                isValid = false;
            }

            if (!nome || nome.length < 3) {
                errorMessage = 'Por favor, informe seu nome completo (mínimo 3 caracteres).';
                isValid = false;
            }

            if (!telefone || telefone.replace(/\D/g, '').length < 10) {
                errorMessage = 'Por favor, informe um telefone válido.';
                isValid = false;
            }

            if (!senha || senha.length < <?php echo PASSWORD_MIN_LENGTH; ?>) {
                errorMessage = 'A senha deve ter no mínimo <?php echo PASSWORD_MIN_LENGTH; ?> caracteres.';
                isValid = false;
            }

            if (!isValid) {
                event.preventDefault();
                
                // Criar alerta customizado
                const existingAlert = document.querySelector('.alert-error');
                if (existingAlert) existingAlert.remove();
                
                const alert = document.createElement('div');
                alert.className = 'alert alert-error';
                alert.textContent = errorMessage;
                
                const form = document.getElementById('register-form');
                form.parentNode.insertBefore(alert, form);
                
                // Scroll suave para o topo
                alert.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            // Mostrar loading
            button.disabled = true;
            buttonText.textContent = 'Criando sua conta...';
            spinner.style.display = 'block';
        });

        // Função de validação de email (mantendo original)
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        // Verificar mensagens da URL (mantendo lógica original)
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const successMsg = urlParams.get('success');
            const errorMsg = urlParams.get('error');

            if (successMsg) {
                const alert = document.createElement('div');
                alert.className = 'alert alert-success';
                alert.textContent = successMsg;
                
                const form = document.getElementById('register-form');
                form.parentNode.insertBefore(alert, form);
            }

            if (errorMsg) {
                const alert = document.createElement('div');
                alert.className = 'alert alert-error';
                alert.textContent = errorMsg;
                
                const form = document.getElementById('register-form');
                form.parentNode.insertBefore(alert, form);
            }
        });

        // Animação suave nos inputs
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-2px)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>