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
<?php
$registerCssFile = __DIR__ . '/../../assets/css/views/auth/register-modern.css';
$registerJsFile = __DIR__ . '/../../assets/js/register-modern.js';
$registerCssVersion = is_file($registerCssFile) ? filemtime($registerCssFile) : 1;
$registerJsVersion = is_file($registerJsFile) ? filemtime($registerJsFile) : 1;
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
    <title>Criar Conta - Klube Cash</title>
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
        href="<?php echo rtrim(SITE_URL, '/'); ?>/assets/css/views/auth/register-modern.css?v=<?php echo $registerCssVersion; ?>"
    >
</head>
<body class="register-page">
    <div class="register-background" aria-hidden="true">
        <span class="register-orb register-orb-one"></span>
        <span class="register-orb register-orb-two"></span>
        <span class="register-grid"></span>
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

    <main class="register-shell">
        <section class="register-wrapper" aria-labelledby="register-title">
            <div class="form-panel">
                <div class="form-panel-content">
                    <header class="register-header">
                        <h1 class="main-title" id="register-title">Crie sua <span class="highlight">conta</span></h1>
                        <p class="subtitle">Comece a ganhar dinheiro de volta em suas compras</p>
                    </header>

                    <div class="login-prompt">
                        <span>Já tem uma conta?</span>
                        <a href="<?php echo LOGIN_URL; ?>">Fazer login</a>
                    </div>

                    <div
                        class="progress-indicator"
                        id="progressIndicator"
                        role="progressbar"
                        aria-label="Progresso do cadastro"
                        aria-valuemin="0"
                        aria-valuemax="3"
                        aria-valuenow="0"
                    >
                        <div class="progress-step active"></div>
                        <div class="progress-step"></div>
                        <div class="progress-step"></div>
                    </div>

                    <div class="feedback-region" id="feedbackRegion" aria-live="polite">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-error" role="alert">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success" role="status">
                                <?php echo $success; ?>
                                <br><a href="<?php echo LOGIN_URL; ?>">Clique aqui para fazer login</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <form method="post" action="" id="register-form" class="register-form">
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
                                        autocomplete="name"
                                        value="<?php echo isset($nome) ? htmlspecialchars($nome) : ''; ?>"
                                    >
                                    <span class="input-icon" aria-hidden="true">👤</span>
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
                                        autocomplete="email"
                                        inputmode="email"
                                        value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>"
                                    >
                                    <span class="input-icon" aria-hidden="true">📧</span>
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
                                        autocomplete="tel"
                                        inputmode="tel"
                                        value="<?php echo isset($telefone) ? htmlspecialchars($telefone) : ''; ?>"
                                    >
                                    <span class="input-icon" aria-hidden="true">📱</span>
                                </div>
                            </div>
                        </div>

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
                                        autocomplete="new-password"
                                    >
                                    <button
                                        type="button"
                                        class="password-toggle"
                                        id="passwordToggle"
                                        aria-label="Mostrar senha"
                                        aria-controls="senha"
                                        aria-pressed="false"
                                    >
                                        <span aria-hidden="true">👁️</span>
                                    </button>
                                </div>

                                <div class="password-strength" id="passwordStrength" aria-live="polite">
                                    <div class="strength-bar" aria-hidden="true">
                                        <div class="strength-fill" id="strengthFill"></div>
                                    </div>
                                    <p class="strength-text" id="strengthText">Digite uma senha para ver a força</p>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="submit-button" id="submitButton">
                            <span class="button-content">
                                <span class="loading-spinner" id="loadingSpinner" aria-hidden="true"></span>
                                <span id="buttonText">Criar minha conta gratuita</span>
                            </span>
                        </button>
                    </form>
                </div>
            </div>

            <aside class="brand-panel">
                <div class="brand-content">
                    <div class="brand-logo">
                        <img
                            src="<?php echo rtrim(SITE_URL, '/'); ?>/assets/images/logo-icon.png"
                            alt="Klube Cash"
                            width="65"
                            height="65"
                        >
                    </div>

                    <div class="benefits">
                        <h4 class="benefits-title">Por que escolher o Klube Cash?</h4>
                        <div class="benefits-list">
                            <div class="benefit-item">
                                <div class="benefit-icon" aria-hidden="true">💰</div>
                                <span class="benefit-text">Cashback real</span>
                            </div>
                            <div class="benefit-item">
                                <div class="benefit-icon" aria-hidden="true">⚡</div>
                                <span class="benefit-text">Processo rápido e seguro</span>
                            </div>
                            <div class="benefit-item">
                                <div class="benefit-icon" aria-hidden="true">🎯</div>
                                <span class="benefit-text">Muitas de lojas parceiras</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="brand-decoration" aria-hidden="true">
                    <span class="brand-ring brand-ring-one"></span>
                    <span class="brand-ring brand-ring-two"></span>
                    <span class="brand-dot brand-dot-one"></span>
                    <span class="brand-dot brand-dot-two"></span>
                    <span class="brand-line"></span>
                </div>
            </aside>
        </section>
    </main>

    <script
        src="<?php echo rtrim(SITE_URL, '/'); ?>/assets/js/register-modern.js?v=<?php echo $registerJsVersion; ?>"
        defer
    ></script>
</body>
</html>
