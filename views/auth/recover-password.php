<?php
// Incluir arquivos de configuração
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../controllers/AuthController.php';
require_once '../../utils/Email.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow, noarchive');

// Verificar se já existe uma sessão ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    // Redirecionar com base no tipo de usuário
    if ($_SESSION['user_type'] == USER_TYPE_ADMIN) {
        header('Location: ' . ADMIN_DASHBOARD_URL);
    } else if (in_array($_SESSION['user_type'], [USER_TYPE_STORE, USER_TYPE_EMPLOYEE], true)) {
        header('Location: ' . STORE_DASHBOARD_URL);
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
$maskedEmail = '';
$submittedEmail = '';
$requestSent = isset($_GET['enviado']) && $_GET['enviado'] === '1';
$recoveryExpirationHours = max(1, (int) ceil(TOKEN_EXPIRATION / 3600));

if ($requestSent) {
    $success = 'Se existir uma conta com este e-mail, enviaremos as instruções. Verifique também as pastas Spam e Promoções.';
}

$csrfToken = Security::getRecoveryCSRFToken();

// Verificar se é uma solicitação de redefinição (com token)
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = trim((string) $_GET['token']);
    $userInfo = AuthController::getRecoveryTokenInfo($token);

    if ($userInfo) {
        $validToken = true;
        [$emailLocal, $emailDomain] = array_pad(explode('@', $userInfo['email'], 2), 2, '');
        $maskedEmail = substr($emailLocal, 0, 1)
            . str_repeat('*', max(2, strlen($emailLocal) - 1))
            . ($emailDomain !== '' ? '@' . $emailDomain : '');

        // Processar o formulário de redefinição de senha
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset') {
            $newPassword = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (!Security::validateRecoveryCSRFToken($_POST['csrf_token'] ?? '')) {
                $error = 'Sua sessão expirou. Atualize a página e tente novamente.';
            } else if (empty($newPassword)) {
                $error = 'Por favor, informe a nova senha.';
            } else if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
                $error = 'A senha deve ter no mínimo ' . PASSWORD_MIN_LENGTH . ' caracteres.';
            } else if ($newPassword !== $confirmPassword) {
                $error = 'As senhas não coincidem.';
            } else {
                $result = AuthController::resetPassword($token, $newPassword);

                if ($result['status']) {
                    Security::rotateRecoveryCSRFToken();
                    header('Location: ' . LOGIN_URL . '?success=' . urlencode($result['message']));
                    exit;
                }

                $error = $result['message'];
            }
        }
    } else {
        $error = 'Token inválido ou expirado. Por favor, solicite uma nova recuperação de senha.';
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $submittedEmail = $email;

    if (!Security::validateRecoveryCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } else if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Por favor, informe um e-mail válido.';
    } else {
        $result = AuthController::recoverPassword($email);

        if ($result['status']) {
            Security::rotateRecoveryCSRFToken();
            header('Location: ' . RECOVER_PASSWORD_URL . '?enviado=1');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}


$recoverCssFile = __DIR__ . '/../../assets/css/views/auth/recover-password-modern.css';
$recoverJsFile = __DIR__ . '/../../assets/js/recover-password-modern.js';
$recoverCssVersion = is_file($recoverCssFile) ? filemtime($recoverCssFile) : 1;
$recoverJsVersion = is_file($recoverJsFile) ? filemtime($recoverJsFile) : 1;
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
    <title><?php echo $validToken ? 'Redefinir Senha' : 'Recuperar Senha'; ?> - Klube Cash</title>
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
        href="<?php echo rtrim(SITE_URL, '/'); ?>/assets/css/views/auth/recover-password-modern.css?v=<?php echo $recoverCssVersion; ?>"
    >
</head>
<body>
    <div class="auth-background" aria-hidden="true">
        <span class="auth-orb auth-orb-one"></span>
        <span class="auth-orb auth-orb-two"></span>
        <span class="auth-grid"></span>
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

    <main class="recovery-shell">
        <section class="recovery-card <?php echo $validToken ? 'is-reset-state' : 'is-request-state'; ?>" aria-labelledby="recovery-title">
            <div class="brand-logo">
                <img
                    src="<?php echo rtrim(SITE_URL, '/'); ?>/assets/images/logo-icon.png"
                    alt="Klube Cash"
                    width="40"
                    height="40"
                >
            </div>

            <div class="recovery-action">
                <div class="action-content">
                    <header class="recover-header">
                        <?php if ($validToken): ?>
                            <div class="state-icon reset" aria-hidden="true">🔐</div>
                            <h1 class="main-title" id="recovery-title">Criar <span class="highlight">nova senha</span></h1>
                            <p class="subtitle">Sua nova senha deve ser segura e fácil de lembrar</p>
                        <?php else: ?>
                            <div class="state-icon request" aria-hidden="true">🔑</div>
                            <h1 class="main-title" id="recovery-title">Recuperar <span class="highlight">senha</span></h1>
                            <p class="subtitle">Não se preocupe! Vamos ajudar você a recuperar o acesso à sua conta</p>
                        <?php endif; ?>
                    </header>

                    <?php if ($validToken && $userInfo): ?>
                        <div class="user-context">
                            <div class="user-email"><?php echo htmlspecialchars($maskedEmail, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="context-text">Redefinindo senha para esta conta</div>
                        </div>
                    <?php endif; ?>

                    <?php if (!$validToken): ?>
                        <div class="login-prompt">
                            <span>Lembrou da senha?</span>
                            <a href="<?php echo LOGIN_URL; ?>">Fazer login</a>
                        </div>
                    <?php endif; ?>

                    <div class="client-feedback" id="clientFeedback" role="alert" aria-live="assertive" tabindex="-1" hidden></div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error server-feedback" role="alert" tabindex="-1">
                            <span class="alert-icon" aria-hidden="true">⚠️</span>
                            <span><?php echo htmlspecialchars($error); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success server-feedback" role="status" aria-live="polite" tabindex="-1">
                            <span class="alert-icon" aria-hidden="true">✅</span>
                            <div>
                                <?php echo htmlspecialchars($success); ?>
                                <?php if (strpos($success, 'atualizada com sucesso') !== false): ?>
                                    <br><br><a href="<?php echo LOGIN_URL; ?>" class="alert-link">Fazer login agora</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($validToken): ?>
                        <form
                            method="post"
                            action="/recuperar-senha?token=<?php echo rawurlencode($token); ?>"
                            id="reset-form"
                            class="recover-form form-reset"
                            data-password-min-length="<?php echo PASSWORD_MIN_LENGTH; ?>"
                        >
                            <input type="hidden" name="action" value="reset">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

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
                                        autocomplete="new-password"
                                        aria-describedby="strengthText clientFeedback"
                                    >
                                    <button
                                        type="button"
                                        class="password-toggle"
                                        id="passwordToggle"
                                        aria-label="Mostrar senha"
                                        aria-controls="password"
                                        aria-pressed="false"
                                    ><span aria-hidden="true">👁️</span></button>
                                </div>

                                <div class="password-strength" id="passwordStrength">
                                    <div
                                        class="strength-bar"
                                        id="strengthBar"
                                        role="progressbar"
                                        aria-label="Força da senha"
                                        aria-valuemin="0"
                                        aria-valuemax="5"
                                        aria-valuenow="0"
                                    >
                                        <div class="strength-fill" id="strengthFill"></div>
                                    </div>
                                    <p class="strength-text" id="strengthText" aria-live="polite">Digite uma senha para ver a força</p>
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
                                        autocomplete="new-password"
                                        aria-describedby="matchText clientFeedback"
                                    >
                                    <button
                                        type="button"
                                        class="password-toggle"
                                        id="confirmToggle"
                                        aria-label="Mostrar senha"
                                        aria-controls="confirm_password"
                                        aria-pressed="false"
                                    ><span aria-hidden="true">👁️</span></button>
                                </div>

                                <div class="password-match" id="passwordMatch" aria-live="polite">
                                    <span id="matchText">As senhas precisam ser iguais</span>
                                </div>
                            </div>

                            <button type="submit" class="submit-button" id="resetButton">
                                <span class="button-content">
                                    <span class="loading-spinner" id="resetSpinner" aria-hidden="true"></span>
                                    <span id="resetButtonText">Alterar minha senha</span>
                                </span>
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="/recuperar-senha" id="request-form" class="recover-form form-request">
                            <input type="hidden" name="action" value="request">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                            <div class="input-group">
                                <label for="email" class="input-label">E-mail da sua conta</label>
                                <div class="input-wrapper">
                                    <input
                                        type="email"
                                        id="email"
                                        name="email"
                                        class="form-input"
                                        placeholder="Digite o e-mail da sua conta"
                                        value="<?php echo htmlspecialchars($submittedEmail, ENT_QUOTES, 'UTF-8'); ?>"
                                        required
                                        autocomplete="email"
                                        inputmode="email"
                                        aria-describedby="clientFeedback"
                                    >
                                    <span class="input-icon" aria-hidden="true">📧</span>
                                </div>
                            </div>

                            <button type="submit" class="submit-button" id="requestButton">
                                <span class="button-content">
                                    <span class="loading-spinner" id="requestSpinner" aria-hidden="true"></span>
                                    <span id="requestButtonText">Enviar instruções</span>
                                </span>
                            </button>
                        </form>

                        <div class="alert alert-info">
                            <span class="alert-icon" aria-hidden="true">ℹ️</span>
                            <span>O link de recuperação expira em <?php echo $recoveryExpirationHours; ?> horas por segurança. Se não receber o e-mail, verifique as pastas Spam e Promoções.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="recovery-story" aria-label="Informações de recuperação de senha">
                <div class="story-decoration" aria-hidden="true">
                    <span class="tech-ring tech-ring-one"></span>
                    <span class="tech-ring tech-ring-two"></span>
                    <span class="tech-dot tech-dot-one"></span>
                    <span class="tech-dot tech-dot-two"></span>
                    <span class="tech-line"></span>
                </div>

                <?php if ($validToken): ?>
                    <div class="security-tips">
                        <div class="security-title">
                            <span aria-hidden="true">🛡️</span>
                            <span>Dicas para uma senha segura</span>
                        </div>
                        <div class="security-text">
                            Use pelo menos 8 caracteres, inclua letras maiúsculas e minúsculas, números e símbolos. Evite informações pessoais óbvias.
                        </div>
                    </div>
                <?php else: ?>
                    <div class="process-steps">
                        <div class="process-title">Como funciona?</div>
                        <div class="process-list">
                            <div class="process-item">
                                <div class="process-number">1</div>
                                <span>Digite o e-mail da sua conta</span>
                            </div>
                            <div class="process-item">
                                <div class="process-number">2</div>
                                <span>Receba o link de recuperação por e-mail</span>
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
                <?php endif; ?>
            </aside>
        </section>
    </main>

    <script
        src="<?php echo rtrim(SITE_URL, '/'); ?>/assets/js/recover-password-modern.js?v=<?php echo $recoverJsVersion; ?>"
        defer
    ></script>
</body>
</html>
