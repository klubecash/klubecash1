<?php
// views/auth/login.php - VERSÃO FINAL CORRIGIDA E REESTRUTURADA

// Inicia o buffer de saída para prevenir erros de "headers already sent"
ob_start();

require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../controllers/AuthController.php';


if (session_status() === PHP_SESSION_NONE) {
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => getenv('VERCEL') === '1'
        || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https',
    'httponly' => true,
    'samesite' => 'Lax'
]);
}

// Inicia a sessão apenas se não houver uma ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_log('auth.login.session_started');
// 1. VERIFICAR SE O UTILIZADOR JÁ ESTÁ LOGADO E REDIRECIONAR
if (isset($_SESSION['user_id']) && !isset($_GET['force_login'])) {
    $userType = $_SESSION['user_type'] ?? '';

    if ($userType == 'admin') {
        header('Location: ' . ADMIN_DASHBOARD_URL);
    } else if ($userType == 'loja' || $userType == 'funcionario') {
        header('Location: ' . STORE_DASHBOARD_URL);
    } else {
        header('Location: ' . CLIENT_DASHBOARD_URL);
    }
    exit;
}

// 2. PROCESSAR O FORMULÁRIO DE LOGIN (SE FOI ENVIADO)
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Por favor, preencha todos os campos.';
        header('Content-Type: application/json');
        echo json_encode([
            'status' => false,
            'message' => $error
        ]);
        exit;
    }

    $result = AuthController::login($email, $password, false);

    if ($result['status']) {
        $userType = $_SESSION['user_type'] ?? '';
        $token = $result['token'] ?? '';

        if ($token) {
            // Define o cookie JWT
            setcookie('jwt_token', $token, [
                'expires' => time() + (60 * 60 * 24),
                'path' => '/',
                'domain' => '',
                'secure' => getenv('VERCEL') === '1'
                    || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }

        // Persiste a sessão no banco antes de responder ao fetch. Em runtimes
        // serverless, o redirecionamento seguinte pode cair em outra instância.
        if (session_status() === PHP_SESSION_ACTIVE && !session_write_close()) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'status' => false,
                'message' => 'Não foi possível concluir sua sessão. Tente novamente.'
            ]);
            exit;
        }

        // Definir URL de redirecionamento
        $redirectUrl = CLIENT_DASHBOARD_URL; // padrão

        if ($userType == 'admin') {
            $redirectUrl = ADMIN_DASHBOARD_URL;
        } else if ($userType == 'loja' || $userType == 'funcionario') {
            $redirectUrl = STORE_DASHBOARD_URL;
        }

        // Retorna JSON para o front
        header('Content-Type: application/json');
        echo json_encode([
            'status' => true,
            'redirect' => $redirectUrl,
            'message' => 'Login efetuado com sucesso.'
        ]);
        exit;

    } else {
        // Caso login falhe
        header('Content-Type: application/json');
        echo json_encode([
            'status' => false,
            'message' => $result['message']
        ]);
        exit;
    }
}
// 3. SE NÃO HOUVE REDIRECIONAMENTO, PREPARAMOS AS VARIÁVEIS PARA MOSTRAR A PÁGINA HTML
$urlError = $_GET['error'] ?? '';
$urlSuccess = $_GET['success'] ?? '';
if (!empty($urlError)) {
    $error = urldecode($urlError);
}

$loginCssFile = __DIR__ . '/../../assets/css/views/auth/login-modern.css';
$loginJsFile = __DIR__ . '/../../assets/js/login-modern.js';
$loginCssVersion = is_file($loginCssFile) ? filemtime($loginCssFile) : 1;
$loginJsVersion = is_file($loginJsFile) ? filemtime($loginJsFile) : 1;
$loginFeedback = [
    'error' => $error !== '' ? $error : null,
    'success' => $urlSuccess !== '' ? $urlSuccess : null,
];

// Descarrega o buffer de saída (se nada foi enviado, não faz nada)
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta
        name="theme-color"
        id="themeColor"
        content="#F7F8FC"
        data-theme-light="#F7F8FC"
        data-theme-dark="#090B10"
    >
    <title>Entrar - Klube Cash</title>
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo rtrim(SITE_URL, '/'); ?>/assets/images/icons/KlubeCashLOGO.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        (function () {
            var root = document.documentElement;
            var theme = 'light';

            try {
                var savedTheme = window.localStorage.getItem('klubecash-theme');
                if (savedTheme === 'light' || savedTheme === 'dark') {
                    theme = savedTheme;
                } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    theme = 'dark';
                }
            } catch (error) {
                if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    theme = 'dark';
                }
            }

            root.setAttribute('data-theme', theme);
            root.style.colorScheme = theme;

            var themeColor = document.getElementById('themeColor');
            if (themeColor) {
                themeColor.setAttribute(
                    'content',
                    theme === 'dark'
                        ? themeColor.getAttribute('data-theme-dark')
                        : themeColor.getAttribute('data-theme-light')
                );
            }
        }());
    </script>
    <link
        rel="stylesheet"
        href="<?php echo rtrim(SITE_URL, '/'); ?>/assets/css/views/auth/login-modern.css?v=<?php echo $loginCssVersion; ?>"
    >
</head>
<body>
    <div class="auth-background" aria-hidden="true">
        <span class="auth-orb auth-orb-one"></span>
        <span class="auth-orb auth-orb-two"></span>
        <span class="auth-grid"></span>
    </div>

    <div class="toast-container" id="toast-container" aria-live="polite" aria-atomic="false"></div>

    <div class="spinner-overlay" id="spinner-overlay" role="status" aria-live="polite" aria-hidden="true">
        <div class="spinner"></div>
        <span class="sr-only">Carregando...</span>
    </div>

    <button
        type="button"
        class="theme-toggle"
        id="themeToggle"
        aria-label="Ativar modo noturno"
        aria-pressed="false"
    >
        <svg class="theme-icon theme-icon-sun" viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="4"></circle>
            <path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.65 17.65l1.42 1.42M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.65 6.35l1.42-1.42"></path>
        </svg>
        <svg class="theme-icon theme-icon-moon" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M20.5 14.2A8.5 8.5 0 0 1 9.8 3.5 8.5 8.5 0 1 0 20.5 14.2Z"></path>
        </svg>
    </button>

    <main class="login-shell">
        <section class="login-wrapper" aria-labelledby="login-title">
            <div class="welcome-section">
                <div class="welcome-content">
                    <div class="logo-container">
                        <img
                            src="<?php echo rtrim(SITE_URL, '/'); ?>/assets/images/logobranco.png"
                            alt="Klube Cash"
                            width="991"
                            height="383"
                        >
                    </div>

                    <div class="welcome-copy">
                        <h1 class="welcome-title">Bem-vindo de volta!</h1>
                        <p class="welcome-subtitle">
                            Entre na sua conta e continue transformando suas compras em dinheiro de volta.
                        </p>
                    </div>

                    <ul class="features-list">
                        <li><span class="feature-check" aria-hidden="true">✓</span><span>Cashback real</span></li>
                        <li><span class="feature-check" aria-hidden="true">✓</span><span>Muitas lojas parceiras</span></li>
                        <li><span class="feature-check" aria-hidden="true">✓</span><span>Sem taxas ou anuidades</span></li>
                        <li><span class="feature-check" aria-hidden="true">✓</span><span>Utilize em lojas que ele foi gerado</span></li>
                    </ul>
                </div>

                <div class="welcome-decoration" aria-hidden="true">
                    <span class="tech-ring tech-ring-one"></span>
                    <span class="tech-ring tech-ring-two"></span>
                    <span class="tech-dot tech-dot-one"></span>
                    <span class="tech-dot tech-dot-two"></span>
                    <span class="tech-line"></span>
                </div>
            </div>

            <div class="form-section">
                <div class="form-content">
                    <header class="form-header">
                        <h2 class="form-title" id="login-title">Entrar</h2>
                        <p class="form-subtitle">
                            Não tem conta? <a href="<?php echo REGISTER_URL; ?>">Cadastre-se grátis</a>
                        </p>
                    </header>

                    <form method="post" action="" class="login-form" id="login-form">
                        <div class="input-group">
                            <label for="email" class="input-label">E-mail</label>
                            <div class="input-wrapper">
                                <span class="input-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M4 6h16v12H4z"></path>
                                        <path d="m4 7 8 6 8-6"></path>
                                    </svg>
                                </span>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    class="input-field"
                                    placeholder="Digite seu e-mail"
                                    required
                                    autocomplete="email"
                                    inputmode="email"
                                    aria-describedby="email-error"
                                >
                            </div>
                            <span class="field-error" id="email-error" aria-live="polite"></span>
                        </div>

                        <div class="input-group">
                            <label for="password" class="input-label">Senha</label>
                            <div class="input-wrapper">
                                <span class="input-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24">
                                        <rect x="5" y="10" width="14" height="10" rx="2"></rect>
                                        <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                                    </svg>
                                </span>
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="input-field"
                                    placeholder="Digite sua senha"
                                    required
                                    autocomplete="current-password"
                                    aria-describedby="password-error"
                                >
                                <button
                                    type="button"
                                    class="password-toggle"
                                    id="passwordToggle"
                                    aria-label="Mostrar senha"
                                    aria-controls="password"
                                    aria-pressed="false"
                                >
                                    <svg id="eye-icon" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </div>
                            <span class="field-error" id="password-error" aria-live="polite"></span>
                        </div>

                        <div class="forgot-password">
                            <a href="<?php echo RECOVER_PASSWORD_URL; ?>">Esqueci minha senha</a>
                        </div>

                        <button type="submit" class="btn btn-primary" id="login-btn">
                            <span id="btn-text">Entrar</span>
                            <svg class="button-arrow" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M5 12h14M14 7l5 5-5 5"></path>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </main>

    <script type="application/json" id="loginFeedback"><?php
        echo json_encode(
            $loginFeedback,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
        );
    ?></script>
    <script
        src="<?php echo rtrim(SITE_URL, '/'); ?>/assets/js/login-modern.js?v=<?php echo $loginJsVersion; ?>"
        defer
    ></script>
</body>
</html>
