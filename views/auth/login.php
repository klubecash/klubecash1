<?php
// views/auth/login.php - VERSÃO CORRIGIDA E REESTRUTURADA

require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../controllers/AuthController.php';

// Captura a origem da URL (se houver)
$origem = $_GET['origem'] ?? '';


session_set_cookie_params([
    'lifetime' => 0, // A sessão dura até o navegador fechar
    'path'     => '/',
    'domain'   => '.klubecash.com', // O PONTO é crucial para incluir subdomínios
    'secure'   => true,   // Apenas sobre HTTPS
    'httponly' => true, // Impede acesso via JavaScript (mais seguro)
    'samesite' => 'none' // Segurança contra ataques CSRF
]);

// Iniciar a sessão apenas se não houver uma ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. VERIFICAR SE O UTILIZADOR JÁ ESTÁ LOGADO E REDIRECIONAR
if (isset($_SESSION['user_id']) && !isset($_GET['force_login'])) {
    $userType = $_SESSION['user_type'] ?? '';
    if ($userType == 'admin') {
        header('Location: ' . ADMIN_DASHBOARD_URL);
        exit;
    } else if ($userType == 'loja' || $userType == 'funcionario') {
        header('Location: ' . STORE_DASHBOARD_URL);
        exit;
    } else {
        header('Location: ' . CLIENT_DASHBOARD_URL);
        exit;
    }
}

// 2. PROCESSAR O FORMULÁRIO DE LOGIN (SE FOI ENVIADO)
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $origem_post = $_POST['origem'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Por favor, preencha todos os campos.';
    } else {
        // ✅ ALTERAÇÃO: Passar a origem para a função de login
        $result = AuthController::login($email, $password, false, $origem_post);
        
        if ($result['status']) {
            // Login bem-sucedido
            $userType = $_SESSION['user_type'] ?? '';
            $userData = $result['user_data'] ?? [];
            
            $token = $result['token'] ?? '';
            if ($token) {
                setcookie('jwt_token', $token, [
                    'expires' => time() + (60 * 60 * 24), // 24 horas
                    'path' => '/',
                    'domain' => '.klubecash.com',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'none'
                ]);
            }
            
            // A lógica de redirecionamento agora funciona, pois o AuthController já atualizou o 'senat'
            if ($origem_post === 'sest-senat' && !empty($userData['senat']) && in_array(strtolower($userData['senat']), ['true', '1', 'sim'])) {
                header('Location: https://sest-senat.klubecash.com/');
                exit;
            }

            // Lógica de redirecionamento padrão para outros utilizadores
            if ($userType == 'admin') {
                header('Location: ' . ADMIN_DASHBOARD_URL);
            } else if ($userType == 'loja' || $userType == 'funcionario') {
                header('Location: ' . STORE_DASHBOARD_URL);
            } else {
                header('Location: ' . CLIENT_DASHBOARD_URL);
            }
            exit;

        } else {
            $error = $result['message'];
        }
    }
}

// 3. SE NÃO HOUVE REDIRECIONAMENTO, PREPARAMOS AS VARIÁVEIS PARA MOSTRAR A PÁGINA HTML
$urlError = $_GET['error'] ?? '';
$urlSuccess = $_GET['success'] ?? '';
if (!empty($urlError)) {
    $error = urldecode($urlError);
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar - Klube Cash</title>
    <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* === VARIÁVEIS CSS === */
        :root {
            --primary-color: #FF7A00;
            --primary-dark: #E86E00;
            --secondary-color: #1A1A1A;
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
            --success-color: #10B981;
            --error-color: #EF4444;
            --warning-color: #F59E0B;
            --info-color: #3B82F6;
            --border-radius: 12px;
            --border-radius-lg: 16px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --gradient-primary: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* === RESET E BASE === */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: var(--gray-800);
            background: var(--gradient-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        /* === CONTAINER PRINCIPAL === */
        .login-wrapper {
            width: 100%;
            max-width: 1200px;
            background: var(--white);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr;
            min-height: 600px;
        }

        /* === SEÇÃO DE BOAS-VINDAS === */
        .welcome-section {
            background: var(--gradient-primary);
            padding: 3rem 2rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: var(--white);
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.1;
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .logo-container {
            margin-bottom: 2rem;
            z-index: 1;
            position: relative;
        }

        .logo-container img {
            height: 60px;
            width: auto;
            filter: brightness(0) invert(1);
        }

        .welcome-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            z-index: 1;
            position: relative;
        }

        .welcome-subtitle {
            font-size: 1.125rem;
            opacity: 0.9;
            margin-bottom: 2rem;
            max-width: 400px;
            z-index: 1;
            position: relative;
        }

        .features-list {
            list-style: none;
            text-align: left;
            z-index: 1;
            position: relative;
        }

        .features-list li {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
        }

        .features-list li::before {
            content: '✓';
            background: rgba(255, 255, 255, 0.2);
            color: var(--white);
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            font-weight: 600;
            font-size: 12px;
        }

        /* === SEÇÃO DO FORMULÁRIO === */
        .form-section {
            padding: 3rem 2rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .form-subtitle {
            color: var(--gray-500);
            font-size: 1rem;
        }

        .form-subtitle a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .form-subtitle a:hover {
            color: var(--primary-dark);
        }

        /* === FORMULÁRIO === */
        .login-form {
            max-width: 400px;
            width: 100%;
            margin: 0 auto;
        }

        .input-group {
            margin-bottom: 1.5rem;
        }

        .input-label {
            display: block;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-field {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
            background: var(--white);
        }

        .input-field:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 122, 0, 0.1);
        }

        .input-field:invalid:not(:placeholder-shown) {
            border-color: var(--error-color);
        }

        .password-toggle {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--gray-400);
            padding: 0.25rem;
            border-radius: 4px;
            transition: var(--transition);
        }

        .password-toggle:hover {
            color: var(--gray-600);
            background: var(--gray-100);
        }

        .password-toggle svg {
            width: 20px;
            height: 20px;
        }

        .forgot-password {
            text-align: right;
            margin-bottom: 2rem;
        }

        .forgot-password a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .forgot-password a:hover {
            color: var(--primary-dark);
        }

        /* === BOTÕES === */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.875rem 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 1rem;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            min-height: 48px;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: var(--white);
            width: 100%;
            margin-bottom: 1.5rem;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: var(--white);
            color: var(--gray-700);
            border: 2px solid var(--gray-200);
            width: 100%;
        }

        .btn-secondary:hover {
            background: var(--gray-50);
            border-color: var(--gray-300);
        }

        /* === DIVISOR === */
        .divider {
            display: flex;
            align-items: center;
            margin: 1.5rem 0;
            color: var(--gray-400);
            font-size: 0.875rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--gray-200);
        }

        .divider span {
            padding: 0 1rem;
        }

        /* === LOADING === */
        .loading-spinner {
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 0.5rem;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* === TOAST MESSAGES === */
        .toast-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 9999;
            max-width: 400px;
            width: 100%;
        }

        .toast {
            display: flex;
            align-items: flex-start;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            backdrop-filter: blur(10px);
            transform: translateX(100%);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }

        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }

        .toast.hide {
            transform: translateX(100%);
            opacity: 0;
        }

        .toast.success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.95), rgba(5, 150, 105, 0.95));
            color: var(--white);
            border-left: 4px solid var(--success-color);
        }

        .toast.error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.95), rgba(220, 38, 38, 0.95));
            color: var(--white);
            border-left: 4px solid var(--error-color);
        }

        .toast.warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.95), rgba(217, 119, 6, 0.95));
            color: var(--white);
            border-left: 4px solid var(--warning-color);
        }

        .toast.info {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.95), rgba(37, 99, 235, 0.95));
            color: var(--white);
            border-left: 4px solid var(--info-color);
        }

        .toast-icon {
            font-size: 1.25rem;
            margin-right: 0.75rem;
            flex-shrink: 0;
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .toast-message {
            font-size: 0.875rem;
            opacity: 0.9;
            line-height: 1.4;
        }

        .toast-close {
            background: none;
            border: none;
            color: inherit;
            font-size: 1.25rem;
            cursor: pointer;
            opacity: 0.7;
            margin-left: 0.75rem;
            padding: 0.25rem;
            border-radius: 4px;
            transition: var(--transition);
        }

        .toast-close:hover {
            opacity: 1;
            background: rgba(255, 255, 255, 0.2);
        }

        /* === SPINNER OVERLAY === */
        .spinner-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            backdrop-filter: blur(5px);
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
        }

        .spinner-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .spinner {
            width: 48px;
            height: 48px;
            border: 3px solid var(--gray-200);
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        /* === RESPONSIVIDADE === */
        @media (min-width: 768px) {
            .login-wrapper {
                grid-template-columns: 1fr 1fr;
                max-height: 700px;
            }

            .welcome-section {
                padding: 4rem 3rem;
            }

            .form-section {
                padding: 4rem 3rem;
            }

            .welcome-title {
                font-size: 3rem;
            }

            .welcome-subtitle {
                font-size: 1.25rem;
            }
        }

        @media (max-width: 767px) {
            body {
                padding: 0.5rem;
            }

            .login-wrapper {
                border-radius: var(--border-radius);
            }

            .welcome-section {
                padding: 2rem 1.5rem;
            }

            .form-section {
                padding: 2rem 1.5rem;
            }

            .welcome-title {
                font-size: 2rem;
            }

            .welcome-subtitle {
                font-size: 1rem;
            }

            .form-title {
                font-size: 1.75rem;
            }

            .toast-container {
                top: 0.5rem;
                right: 0.5rem;
                left: 0.5rem;
                max-width: none;
            }

            .toast {
                transform: translateY(-100%);
            }

            .toast.show {
                transform: translateY(0);
            }

            .toast.hide {
                transform: translateY(-100%);
            }
        }

        @media (max-width: 480px) {
            .input-field {
                font-size: 16px; /* Previne zoom no iOS */
            }
        }
    </style>
</head>
<body>
    <!-- Container para Toast Messages -->
    <div class="toast-container" id="toast-container"></div>
    
    <!-- Spinner Overlay -->
    <div class="spinner-overlay" id="spinner-overlay">
        <div class="spinner"></div>
    </div>

    <!-- Container Principal -->
    <div class="login-wrapper">
        <!-- Seção de Boas-vindas -->
        <div class="welcome-section">
            <div class="logo-container">
                <img src="../../assets/images/logobranco.png" alt="Klube Cash">
            </div>
            <h1 class="welcome-title">Bem-vindo de volta!</h1>
            <p class="welcome-subtitle">
                Entre na sua conta e continue transformando suas compras em dinheiro de volta.
            </p>
            <ul class="features-list">
                <li>Cashback real</li>
                <li>Muitas lojas parceiras</li>
                <li>Sem taxas ou anuidades</li>
                <li>Utilize em lojas que ele foi gerado</li>
            </ul>
        </div>

        <!-- Seção do Formulário -->
        <div class="form-section">
            <div class="form-header">
                <h2 class="form-title">Entrar</h2>
                <p class="form-subtitle">
                    Não tem conta? <a href="<?php echo REGISTER_URL; ?>">Cadastre-se grátis</a>
                </p>
            </div>

            <form method="post" action="" class="login-form" id="login-form">
                <input type="hidden" name="origem" value="<?php echo htmlspecialchars($origem); ?>">
                <div class="input-group">
                    <label for="email" class="input-label">E-mail</label>
                    <div class="input-wrapper">
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="input-field"
                            placeholder="Digite seu e-mail"
                            required
                            autocomplete="email"
                        >
                    </div>
                </div>

                <div class="input-group">
                    <label for="password" class="input-label">Senha</label>
                    <div class="input-wrapper">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="input-field" 
                            placeholder="Digite sua senha"
                            required
                            autocomplete="current-password"
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword()" aria-label="Mostrar/ocultar senha">
                            <svg id="eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="forgot-password">
                    <a href="<?php echo RECOVER_PASSWORD_URL; ?>">Esqueci minha senha</a>
                </div>

                <button type="submit" class="btn btn-primary" id="login-btn">
                    <span id="btn-text">Entrar</span>
                </button>
            </form>
        </div>
    </div>

    <script>
        // === SISTEMA DE TOAST MESSAGES ===
        class ToastManager {
            constructor() {
                this.container = document.getElementById('toast-container');
                this.toasts = new Map();
            }

            show(message, type = 'info', title = '', duration = 5000) {
                const toast = this.createToast(message, type, title, duration);
                this.container.appendChild(toast);
                
                // Forçar reflow para animação
                toast.offsetHeight;
                toast.classList.add('show');
                
                // Auto remove
                setTimeout(() => {
                    this.hide(toast);
                }, duration);
                
                return toast;
            }

            createToast(message, type, title, duration) {
                const toast = document.createElement('div');
                toast.className = `toast ${type}`;
                
                const icons = {
                    success: '✓',
                    error: '✕',
                    warning: '⚠',
                    info: 'ℹ'
                };

                const titles = {
                    success: title || 'Sucesso!',
                    error: title || 'Erro!',
                    warning: title || 'Atenção!',
                    info: title || 'Informação'
                };

                toast.innerHTML = `
                    <div class="toast-icon">${icons[type] || icons.info}</div>
                    <div class="toast-content">
                        <div class="toast-title">${titles[type]}</div>
                        <div class="toast-message">${message}</div>
                    </div>
                    <button class="toast-close" onclick="toastManager.hide(this.parentElement)">×</button>
                `;

                return toast;
            }

            hide(toast) {
                if (toast && toast.parentElement) {
                    toast.classList.remove('show');
                    toast.classList.add('hide');
                    setTimeout(() => {
                        if (toast.parentElement) {
                            toast.parentElement.removeChild(toast);
                        }
                    }, 400);
                }
            }

            success(message, title) {
                return this.show(message, 'success', title);
            }

            error(message, title) {
                return this.show(message, 'error', title);
            }

            warning(message, title) {
                return this.show(message, 'warning', title);
            }

            info(message, title) {
                return this.show(message, 'info', title);
            }
        }

        // === SISTEMA DE SPINNER ===
        class SpinnerManager {
            constructor() {
                this.overlay = document.getElementById('spinner-overlay');
            }

            show() {
                this.overlay.classList.add('show');
            }

            hide() {
                this.overlay.classList.remove('show');
            }
        }

        // === INSTANCIAR GERENCIADORES ===
        const toastManager = new ToastManager();
        const spinnerManager = new SpinnerManager();

        // === FUNÇÃO PARA ALTERNAR VISIBILIDADE DA SENHA ===
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                eyeIcon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>
                `;
            } else {
                passwordField.type = 'password';
                eyeIcon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                `;
            }
        }

        // === VALIDAÇÃO DO FORMULÁRIO ===
        document.getElementById('login-form').addEventListener('submit', function(event) {
            event.preventDefault();
            
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const loginBtn = document.getElementById('login-btn');
            const btnText = document.getElementById('btn-text');
            
            // Validação básica
            if (!email) {
                toastManager.error('Por favor, informe seu e-mail.');
                return;
            }
            
            if (!isValidEmail(email)) {
                toastManager.error('Por favor, informe um e-mail válido.');
                return;
            }
            
            if (!password) {
                toastManager.error('Por favor, informe sua senha.');
                return;
            }
            
            // Mostrar loading
            const originalHTML = btnText.innerHTML;
            btnText.innerHTML = '<div class="loading-spinner"></div>Entrando...';
            loginBtn.disabled = true;
            spinnerManager.show();
            
            // Simular delay para mostrar o loading
            setTimeout(() => {
                this.submit();
            }, 1000);
        });

        // === FUNÇÃO DE VALIDAÇÃO DE EMAIL ===
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        // === VERIFICAR MENSAGENS NA URL ===
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const errorParam = urlParams.get('error');
            const successParam = urlParams.get('success');

            // Mensagens do PHP
            <?php if (!empty($error)): ?>
                toastManager.error('<?php echo addslashes($error); ?>');
            <?php endif; ?>

            <?php if (!empty($urlSuccess)): ?>
                toastManager.success('<?php echo addslashes($urlSuccess); ?>');
            <?php endif; ?>

            // Mensagens da URL
            if (errorParam) {
                toastManager.error(decodeURIComponent(errorParam));
            }
            
            if (successParam) {
                toastManager.success(decodeURIComponent(successParam));
            }

            // Limpar URL após mostrar as mensagens
            if (errorParam || successParam) {
                const url = new URL(window.location);
                url.searchParams.delete('error');
                url.searchParams.delete('success');
                window.history.replaceState({}, document.title, url);
            }
        });

        // === MELHORIAS DE ACESSIBILIDADE ===
        document.addEventListener('keydown', function(event) {
            // Permitir fechar toast com ESC
            if (event.key === 'Escape') {
                const toasts = document.querySelectorAll('.toast.show');
                toasts.forEach(toast => toastManager.hide(toast));
            }
        });

        // === PREVENÇÃO DE DUPLO SUBMIT ===
        let formSubmitted = false;
        document.getElementById('login-form').addEventListener('submit', function() {
            if (formSubmitted) {
                event.preventDefault();
                return;
            }
            formSubmitted = true;
        });
    </script>
</body>
</html>