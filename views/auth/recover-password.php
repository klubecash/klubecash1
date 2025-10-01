<?php
// Incluir arquivos de configuração
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../controllers/AuthController.php';
require_once '../../utils/Email.php';

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

$error = '';
$success = '';
$token = '';
$validToken = false;
$userInfo = null;

// Verificar se é uma solicitação de redefinição (com token)
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    
    try {
        $db = Database::getConnection();
        
        // Verificar se o token é válido
        $stmt = $db->prepare("
            SELECT rs.*, u.nome, u.email 
            FROM recuperacao_senha rs
            JOIN usuarios u ON rs.usuario_id = u.id
            WHERE rs.token = :token 
            AND rs.usado = 0 
            AND rs.data_expiracao > NOW()
        ");
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        $tokenInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tokenInfo) {
            $validToken = true;
            $userInfo = $tokenInfo;
            
            // Processar o formulário de redefinição de senha
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset') {
                $newPassword = $_POST['password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                
                // Validar senha
                if (empty($newPassword)) {
                    $error = 'Por favor, informe a nova senha.';
                } else if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
                    $error = 'A senha deve ter no mínimo ' . PASSWORD_MIN_LENGTH . ' caracteres.';
                } else if ($newPassword !== $confirmPassword) {
                    $error = 'As senhas não coincidem.';
                } else {
                    // Atualizar a senha do usuário
                    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updateStmt = $db->prepare("UPDATE usuarios SET senha_hash = :senha_hash WHERE id = :id");
                    $updateStmt->bindParam(':senha_hash', $passwordHash);
                    $updateStmt->bindParam(':id', $tokenInfo['usuario_id']);
                    
                    if ($updateStmt->execute()) {
                        // Marcar o token como usado
                        $usedStmt = $db->prepare("UPDATE recuperacao_senha SET usado = 1 WHERE id = :id");
                        $usedStmt->bindParam(':id', $tokenInfo['id']);
                        $usedStmt->execute();
                        
                        $success = 'Sua senha foi atualizada com sucesso! Você já pode fazer login.';
                    } else {
                        $error = 'Erro ao atualizar a senha. Por favor, tente novamente.';
                    }
                }
            }
        } else {
            $error = 'Token inválido ou expirado. Por favor, solicite uma nova recuperação de senha.';
        }
    } catch (PDOException $e) {
        $error = 'Erro ao processar a solicitação. Tente novamente.';
        error_log('Erro na validação do token: ' . $e->getMessage());
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request') {
    // USAR O AUTHCONTROLLER QUE JÁ FUNCIONA
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Por favor, informe um email válido.';
    } else {
        // Usar o AuthController que já está funcionando
        $result = AuthController::recoverPassword($email);
        
        if ($result['status']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $validToken ? 'Redefinir Senha' : 'Recuperar Senha'; ?> - Klube Cash</title>
    <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Reset e variáveis CSS (igual à página de registro) */
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
            --info: #3B82F6;
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
        .recover-container {
            background: var(--white);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            width: 100%;
            max-width: 480px;
            padding: 40px;
            position: relative;
            overflow: hidden;
            animation: slideUp 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .recover-container::before {
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

        /* Header */
        .recover-header {
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
            line-height: 1.5;
        }

        /* Estado especial para redefinição */
        .user-context {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: 16px;
            margin-bottom: 24px;
            text-align: center;
        }

        .user-context .user-email {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 16px;
        }

        .user-context .context-text {
            font-size: 14px;
            color: var(--gray-600);
            margin-top: 4px;
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

        /* Ícone de estado */
        .state-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            animation: pulse 2s ease-in-out infinite;
        }

        .state-icon.request {
            background: linear-gradient(135deg, #E0F2FE, #BAE6FD);
        }

        .state-icon.reset {
            background: linear-gradient(135deg, #F0FDF4, #BBF7D0);
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        /* Formulários */
        .recover-form {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

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
            transform: translateY(-1px);
        }

        .form-input:valid:not(:placeholder-shown) {
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

        /* Password toggle */
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

        /* Password strength indicator */
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

        /* Validation indicator */
        .password-match {
            margin-top: 8px;
            font-size: 12px;
            display: none;
        }

        .password-match.show {
            display: block;
        }

        .password-match.valid {
            color: var(--success);
        }

        .password-match.invalid {
            color: var(--error);
        }

        /* Botões */
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
            display: flex;
            align-items: center;
            gap: 12px;
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

        .alert-info {
            background: #EFF6FF;
            border-color: #DBEAFE;
            color: #1E40AF;
        }

        .alert-icon {
            font-size: 20px;
            flex-shrink: 0;
        }

        /* Instruções de processo */
        .process-steps {
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-top: 24px;
        }

        .process-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 16px;
            text-align: center;
        }

        .process-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .process-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            color: var(--gray-600);
        }

        .process-number {
            background: var(--primary-color);
            color: var(--white);
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            flex-shrink: 0;
        }

        /* Dicas de segurança */
        .security-tips {
            background: linear-gradient(135deg, #FEF3C7, #FDE68A);
            border: 1px solid #FBBF24;
            border-radius: var(--radius-lg);
            padding: 16px;
            margin-top: 24px;
        }

        .security-title {
            font-size: 14px;
            font-weight: 600;
            color: #92400E;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .security-text {
            font-size: 13px;
            color: #92400E;
            line-height: 1.4;
        }

        /* Responsividade */
        @media (max-width: 640px) {
            body {
                padding: 16px;
            }

            .recover-container {
                padding: 24px;
                max-width: 100%;
            }

            .main-title {
                font-size: 24px;
            }

            .subtitle {
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            .recover-container {
                padding: 20px;
                border-radius: var(--radius-lg);
            }

            .main-title {
                font-size: 22px;
            }

            .form-input {
                padding: 14px;
                font-size: 16px;
            }

            .submit-button {
                padding: 16px 20px;
            }
        }

        /* Estados específicos para formulários diferentes */
        .form-request .state-icon {
            background: linear-gradient(135deg, #E0F2FE, #BAE6FD);
        }

        .form-reset .state-icon {
            background: linear-gradient(135deg, #F0FDF4, #BBF7D0);
        }

        /* Animações específicas */
        .input-group:focus-within .input-label {
            color: var(--primary-color);
        }

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
    <div class="recover-container">
        <!-- Header com estado dinâmico -->
        <div class="recover-header">
            <div class="brand-logo">
                <img src="../../assets/images/logo-icon.png" alt="Klube Cash">
            </div>
            
            <?php if ($validToken): ?>
                <div class="state-icon reset">🔐</div>
                <h1 class="main-title">Criar <span class="highlight">nova senha</span></h1>
                <p class="subtitle">Sua nova senha deve ser segura e fácil de lembrar</p>
            <?php else: ?>
                <div class="state-icon request">🔑</div>
                <h1 class="main-title">Recuperar <span class="highlight">senha</span></h1>
                <p class="subtitle">Não se preocupe! Vamos ajudar você a recuperar o acesso à sua conta</p>
            <?php endif; ?>
        </div>

        <!-- Context do usuário (quando redefinindo) -->
        <?php if ($validToken && $userInfo): ?>
            <div class="user-context">
                <div class="user-email"><?php echo htmlspecialchars($userInfo['email']); ?></div>
                <div class="context-text">Redefinindo senha para esta conta</div>
            </div>
        <?php endif; ?>

        <!-- Link para login -->
        <?php if (!$validToken): ?>
            <div class="login-prompt">
                <span>Lembrou da senha?</span>
                <a href="<?php echo LOGIN_URL; ?>">Fazer login</a>
            </div>
        <?php endif; ?>

        <!-- Mensagens de feedback -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <span class="alert-icon">⚠️</span>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <span class="alert-icon">✅</span>
                <div>
                    <?php echo htmlspecialchars($success); ?>
                    <?php if (strpos($success, 'atualizada com sucesso') !== false): ?>
                        <br><br><a href="<?php echo LOGIN_URL; ?>" style="color: #166534; font-weight: 600; text-decoration: underline;">Fazer login agora</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($validToken): ?>
            <!-- Formulário de redefinição de senha -->
            <form method="post" action="" id="reset-form" class="recover-form form-reset">
                <input type="hidden" name="action" value="reset">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="input-group">
                    <label for="password" class="input-label">Nova senha</label>
                    <div class="password-wrapper">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-input" 
                            placeholder="Digite sua nova senha"
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
                
                <div class="input-group">
                    <label for="confirm_password" class="input-label">Confirmar nova senha</label>
                    <div class="password-wrapper">
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            class="form-input" 
                            placeholder="Digite novamente sua nova senha"
                            required
                        >
                        <button type="button" class="password-toggle" id="confirmToggle">
                            👁️
                        </button>
                    </div>
                    
                    <div class="password-match" id="passwordMatch">
                        <span id="matchText">As senhas precisam ser iguais</span>
                    </div>
                </div>

                <button type="submit" class="submit-button" id="resetButton">
                    <div class="button-content">
                        <div class="loading-spinner" id="resetSpinner"></div>
                        <span id="resetButtonText">Alterar minha senha</span>
                    </div>
                </button>
            </form>

            <!-- Dicas de segurança -->
            <div class="security-tips">
                <div class="security-title">
                    <span>🛡️</span>
                    <span>Dicas para uma senha segura</span>
                </div>
                <div class="security-text">
                    Use pelo menos 8 caracteres, inclua letras maiúsculas e minúsculas, números e símbolos. Evite informações pessoais óbvias.
                </div>
            </div>

        <?php else: ?>
            <!-- Formulário de solicitação de recuperação -->
            <form method="post" action="" id="request-form" class="recover-form form-request">
                <input type="hidden" name="action" value="request">
                
                <div class="input-group">
                    <label for="email" class="input-label">Email da sua conta</label>
                    <div class="input-wrapper">
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-input" 
                            placeholder="Digite o email da sua conta"
                            required
                        >
                        <span class="input-icon">📧</span>
                    </div>
                </div>

                <button type="submit" class="submit-button" id="requestButton">
                    <div class="button-content">
                        <div class="loading-spinner" id="requestSpinner"></div>
                        <span id="requestButtonText">Enviar instruções</span>
                    </div>
                </button>
            </form>

            <!-- Processo explicado -->
            <div class="process-steps">
                <div class="process-title">Como funciona?</div>
                <div class="process-list">
                    <div class="process-item">
                        <div class="process-number">1</div>
                        <span>Digite o email da sua conta</span>
                    </div>
                    <div class="process-item">
                        <div class="process-number">2</div>
                        <span>Receba o link de recuperação por email</span>
                    </div>
                    <div class="process-item">
                        <div class="process-number">3</div>
                        <span>Crie uma nova senha segura</span>
                    </div>
                    <div class="process-item">
                        <div class="process-number">4</div>
                        <span>Faça login com sua nova senha</span>
                    </div>
                </div>
            </div>

            <!-- Informação de segurança -->
            <div class="alert alert-info">
                <span class="alert-icon">ℹ️</span>
                <span>O link de recuperação expira em 2 horas por segurança. Se não receber o email, verifique sua caixa de spam.</span>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Verificador de força da senha (igual ao registro)
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

        // Verificador de confirmação de senha
        function checkPasswordMatch() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            const matchIndicator = document.getElementById('passwordMatch');
            const matchText = document.getElementById('matchText');

            if (!password || !confirmPassword || !password.value || !confirmPassword.value) {
                if (matchIndicator) matchIndicator.classList.remove('show');
                return;
            }

            if (matchIndicator) {
                matchIndicator.classList.add('show');
                
                if (password.value === confirmPassword.value) {
                    matchIndicator.className = 'password-match show valid';
                    matchText.textContent = '✓ Senhas coincidem';
                } else {
                    matchIndicator.className = 'password-match show invalid';
                    matchText.textContent = '✗ Senhas não coincidem';
                }
            }
        }

        // Toggle de visibilidade das senhas
        function setupPasswordToggles() {
            const passwordToggle = document.getElementById('passwordToggle');
            const confirmToggle = document.getElementById('confirmToggle');

            if (passwordToggle) {
                passwordToggle.addEventListener('click', function() {
                    const passwordField = document.getElementById('password');
                    const isPassword = passwordField.type === 'password';
                    
                    passwordField.type = isPassword ? 'text' : 'password';
                    this.textContent = isPassword ? '🙈' : '👁️';
                });
            }

            if (confirmToggle) {
                confirmToggle.addEventListener('click', function() {
                    const confirmField = document.getElementById('confirm_password');
                    const isPassword = confirmField.type === 'password';
                    
                    confirmField.type = isPassword ? 'text' : 'password';
                    this.textContent = isPassword ? '🙈' : '👁️';
                });
            }
        }

        // Event listeners para formulário de redefinição
        if (document.getElementById('reset-form')) {
            const passwordField = document.getElementById('password');
            const confirmField = document.getElementById('confirm_password');

            passwordField.addEventListener('input', function() {
                checkPasswordStrength(this.value);
                checkPasswordMatch();
            });

            if (confirmField) {
                confirmField.addEventListener('input', checkPasswordMatch);
            }

            // Validação do formulário de redefinição
            document.getElementById('reset-form').addEventListener('submit', function(event) {
                const password = passwordField.value;
                const confirmPassword = confirmField.value;
                const button = document.getElementById('resetButton');
                const buttonText = document.getElementById('resetButtonText');
                const spinner = document.getElementById('resetSpinner');

                let isValid = true;
                let errorMessage = '';

                if (!password) {
                    errorMessage = 'Por favor, informe sua nova senha.';
                    isValid = false;
                } else if (password.length < <?php echo PASSWORD_MIN_LENGTH; ?>) {
                    errorMessage = 'A senha deve ter no mínimo <?php echo PASSWORD_MIN_LENGTH; ?> caracteres.';
                    isValid = false;
                } else if (password !== confirmPassword) {
                    errorMessage = 'As senhas não coincidem.';
                    isValid = false;
                }

                if (!isValid) {
                    event.preventDefault();
                    alert(errorMessage);
                    return;
                }

                // Mostrar loading
                button.disabled = true;
                buttonText.textContent = 'Alterando senha...';
                spinner.style.display = 'block';
            });

            setupPasswordToggles();
        }

        // Event listeners para formulário de solicitação
        if (document.getElementById('request-form')) {
            document.getElementById('request-form').addEventListener('submit', function(event) {
                const email = document.getElementById('email').value;
                const button = document.getElementById('requestButton');
                const buttonText = document.getElementById('requestButtonText');
                const spinner = document.getElementById('requestSpinner');

                if (!email || !isValidEmail(email)) {
                    event.preventDefault();
                    alert('Por favor, informe um email válido.');
                    return;
                }

                // Mostrar loading
                button.disabled = true;
                buttonText.textContent = 'Enviando...';
                spinner.style.display = 'block';
            });
        }

        // Função de validação de email
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        // Animações suaves nos inputs
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