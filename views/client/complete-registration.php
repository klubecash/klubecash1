<?php
// views/client/complete-registration.php
// First thing a customer without CPF sees after signing in: the panel opens only after a valid CPF is saved
// (the Kernel sends every other client page here until then).

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../utils/Security.php';
require_once __DIR__ . '/../../utils/Validator.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== USER_TYPE_CLIENT) {
    header('Location: ' . LOGIN_URL . '?error=acesso_restrito');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$db = Database::getConnection();

$current = $db->prepare('SELECT nome, cpf FROM usuarios WHERE id = ? LIMIT 1');
$current->execute([$userId]);
$user = $current->fetch(PDO::FETCH_ASSOC) ?: ['nome' => '', 'cpf' => null];
if (!empty($user['cpf'])) {
    $_SESSION['client_cpf_ok'] = $userId;
    header('Location: ' . CLIENT_DASHBOARD_URL);
    exit;
}

$error = '';
$typed = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $typed = substr(preg_replace('/\D+/', '', (string) ($_POST['cpf'] ?? '')) ?? '', 0, 11);
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? null)) {
        $error = 'Sua sessão expirou. Tente de novo.';
    } elseif (!Validator::validaCPF($typed)) {
        $error = 'CPF inválido. Confira os 11 números.';
    } else {
        $owner = $db->prepare('SELECT id FROM usuarios WHERE cpf = ? AND id <> ? LIMIT 1');
        $owner->execute([$typed, $userId]);
        if ($owner->fetchColumn()) {
            $error = 'Este CPF já está cadastrado em outra conta. Se for seu, fale com o suporte.';
        } else {
            try {
                $save = $db->prepare("UPDATE usuarios SET cpf = ? WHERE id = ? AND (cpf IS NULL OR cpf = '')");
                $save->execute([$typed, $userId]);
                $_SESSION['client_cpf_ok'] = $userId;
                header('Location: ' . CLIENT_DASHBOARD_URL);
                exit;
            } catch (PDOException $exception) {
                if ($exception->getCode() !== '23000') {
                    throw $exception;
                }
                $error = 'Este CPF já está cadastrado em outra conta. Se for seu, fale com o suporte.';
            }
        }
    }
}

$csrfToken = Security::generateCSRFToken();
$firstName = trim(explode(' ', trim((string) $user['nome']))[0] ?? '');
$masked = $typed === '' ? '' : preg_replace(
    ['/^(\d{3})(\d)/', '/^(\d{3})\.(\d{3})(\d)/', '/\.(\d{3})(\d{1,2})$/'],
    ['$1.$2', '$1.$2.$3', '.$1-$2'],
    $typed
);
$e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Completar cadastro - Klube Cash</title>
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo rtrim(SITE_URL, '/'); ?>/assets/images/icons/KlubeCashLOGO.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand: #FC6914; --brand-dark: #A93800; --page-bg: #F7F8FC; --surface: #FFFFFF; --input-bg: #FBFBFD;
            --text: #241C19; --text-muted: #6D625D; --border: rgba(54, 38, 31, 0.13); --focus-ring: rgba(252, 105, 20, 0.22);
            --error: #C52C3D; --error-bg: rgba(197, 44, 61, 0.08); --shadow: 0 24px 70px rgba(53, 31, 21, 0.12);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --brand-dark: #FFA06C; --page-bg: #090B10; --surface: #12151C; --input-bg: #0D1016;
                --text: #F3EEEA; --text-muted: #A99F99; --border: rgba(255, 255, 255, 0.12);
                --error: #FF7D8B; --error-bg: rgba(255, 125, 139, 0.1); --shadow: 0 24px 70px rgba(0, 0, 0, 0.45);
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px;
            background: var(--page-bg); color: var(--text); font-family: Inter, system-ui, -apple-system, "Segoe UI", sans-serif;
        }
        .card {
            width: 100%; max-width: 440px; background: var(--surface); border: 1px solid var(--border);
            border-radius: 22px; box-shadow: var(--shadow); padding: 32px 28px;
        }
        .logo { height: 34px; margin-bottom: 24px; }
        h1 { font-size: 1.45rem; line-height: 1.25; margin: 0 0 10px; }
        p { color: var(--text-muted); line-height: 1.55; margin: 0 0 22px; }
        label { display: block; font-weight: 600; font-size: 0.92rem; margin-bottom: 8px; }
        input {
            width: 100%; font: inherit; font-size: 1.15rem; letter-spacing: 0.04em; padding: 14px 16px;
            border-radius: 14px; border: 1px solid var(--border); background: var(--input-bg); color: var(--text);
        }
        input:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 4px var(--focus-ring); }
        .hint { font-size: 0.85rem; color: var(--text-muted); margin: 8px 0 0; }
        .error { background: var(--error-bg); color: var(--error); border-radius: 12px; padding: 12px 14px; margin-bottom: 18px; font-size: 0.92rem; }
        button {
            width: 100%; margin-top: 22px; padding: 14px 18px; border: 0; border-radius: 14px; cursor: pointer;
            background: var(--brand); color: #fff; font: inherit; font-weight: 700; font-size: 1rem;
        }
        button:hover { filter: brightness(0.95); }
        .out { display: block; text-align: center; margin-top: 18px; color: var(--text-muted); font-size: 0.9rem; }
        .out:hover { color: var(--brand-dark); }
    </style>
</head>
<body>
    <main class="card">
        <img class="logo" src="<?php echo rtrim(SITE_URL, '/'); ?>/assets/images/logolaranja.png" alt="Klube Cash" onerror="this.remove()">
        <h1><?php echo $firstName !== '' ? 'Olá, ' . $e($firstName) . '! ' : ''; ?>Falta só o seu CPF</h1>
        <p>Informe seu CPF para completar o cadastro e liberar seu painel, seu saldo e seus giftbacks.</p>
        <?php if ($error !== ''): ?>
            <div class="error" role="alert"><?php echo $e($error); ?></div>
        <?php endif; ?>
        <form method="post" action="/cliente/completar-cadastro" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
            <label for="cpf">CPF</label>
            <input id="cpf" name="cpf" inputmode="numeric" autocomplete="off" maxlength="14" required autofocus
                placeholder="000.000.000-00" value="<?php echo $e((string) $masked); ?>">
            <p class="hint">Depois de salvo, o CPF não pode ser trocado por aqui.</p>
            <button type="submit">Salvar e continuar</button>
        </form>
        <a class="out" href="<?php echo LOGOUT_URL; ?>">Sair</a>
    </main>
    <script>
        (function () {
            var input = document.getElementById('cpf');
            input.addEventListener('input', function () {
                var digits = input.value.replace(/\D/g, '').slice(0, 11);
                input.value = digits
                    .replace(/^(\d{3})(\d)/, '$1.$2')
                    .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
                    .replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2');
            });
        }());
    </script>
</body>
</html>
