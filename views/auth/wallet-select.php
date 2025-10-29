<?php
// views/auth/wallet-select.php
// Página de seleção de carteira para clientes com senat = 'Sim'

ob_start();

require_once '../../config/constants.php';
require_once '../../config/database.php';

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'cliente') {
    header('Location: ' . LOGIN_URL);
    exit;
}

// Verificar se o usuário tem senat = 'Sim'
if (!isset($_SESSION['user_senat']) || $_SESSION['user_senat'] !== 'Sim') {
    // Se não tem senat, redirecionar direto para dashboard
    header('Location: ' . CLIENT_DASHBOARD_URL);
    exit;
}

// Processar logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Limpar variáveis de sessão
    $_SESSION = array();

    // Limpar cookies
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    // Destruir a sessão
    session_destroy();

    // Redirecionar para a página de login
    header('Location: ' . LOGIN_URL);
    exit;
}

// Processar seleção de carteira
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wallet'])) {
    $wallet = $_POST['wallet'];

    if ($wallet === 'klubecash') {
        // Redirecionar para dashboard normal
        header('Location: ' . CLIENT_DASHBOARD_URL);
        exit;
    } elseif ($wallet === 'senat') {
        // Redirecionar para subdomínio sest-senat
        header('Location: https://sest-senat.klubecash.com/');
        exit;
    }
}

$userName = $_SESSION['user_name'] ?? 'Usuário';

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selecione sua Carteira - Klube Cash</title>
    <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* === VARIÁVEIS CSS === */
        :root {
            --primary-color: #FF7A00;
            --primary-dark: #E86E00;
            --secondary-color: #1A1A1A;
            --senat-blue: #003DA5;
            --senat-blue-dark: #002B75;
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
            --border-radius: 16px;
            --border-radius-lg: 24px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --gradient-primary: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            --gradient-senat: linear-gradient(135deg, var(--senat-blue) 0%, var(--senat-blue-dark) 100%);
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        /* === CONTAINER PRINCIPAL === */
        .wallet-wrapper {
            width: 100%;
            max-width: 900px;
            background: var(--white);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            padding: 3rem;
        }

        /* === HEADER === */
        .wallet-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .logo-container {
            margin-bottom: 1.5rem;
        }

        .logo-container img {
            height: 50px;
            width: auto;
        }

        .welcome-message {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .welcome-subtitle {
            font-size: 1.1rem;
            color: var(--gray-500);
        }

        /* === WALLET CARDS === */
        .wallet-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .wallet-card {
            position: relative;
            background: var(--white);
            border: 3px solid var(--gray-200);
            border-radius: var(--border-radius);
            padding: 2.5rem 2rem;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            overflow: hidden;
        }

        .wallet-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: var(--gradient-primary);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .wallet-card.senat::before {
            background: var(--gradient-senat);
        }

        .wallet-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
        }

        .wallet-card.senat:hover {
            border-color: var(--senat-blue);
        }

        .wallet-card:hover::before {
            transform: scaleX(1);
        }

        .wallet-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            background: var(--gray-100);
            transition: var(--transition);
        }

        .wallet-card:hover .wallet-icon {
            transform: scale(1.1);
        }

        .wallet-card.klubecash .wallet-icon {
            background: linear-gradient(135deg, rgba(255, 122, 0, 0.1), rgba(255, 122, 0, 0.2));
        }

        .wallet-card.senat .wallet-icon {
            background: linear-gradient(135deg, rgba(0, 61, 165, 0.1), rgba(0, 61, 165, 0.2));
        }

        .wallet-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.75rem;
        }

        .wallet-description {
            font-size: 0.95rem;
            color: var(--gray-600);
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }

        .wallet-features {
            list-style: none;
            text-align: left;
            margin-bottom: 1.5rem;
        }

        .wallet-features li {
            display: flex;
            align-items: center;
            padding: 0.5rem 0;
            font-size: 0.9rem;
            color: var(--gray-700);
        }

        .wallet-features li::before {
            content: '✓';
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            font-size: 0.75rem;
            font-weight: 700;
            margin-right: 0.75rem;
            flex-shrink: 0;
        }

        .wallet-card.senat .wallet-features li::before {
            background: var(--senat-blue);
        }

        /* === BOTÃO DE SELEÇÃO === */
        .select-wallet-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 1rem 2rem;
            border: none;
            border-radius: var(--border-radius);
            background: var(--gradient-primary);
            color: white;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .wallet-card.senat .select-wallet-btn {
            background: var(--gradient-senat);
        }

        .select-wallet-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 122, 0, 0.3);
        }

        .wallet-card.senat .select-wallet-btn:hover {
            box-shadow: 0 10px 20px rgba(0, 61, 165, 0.3);
        }

        /* === FOOTER === */
        .wallet-footer {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid var(--gray-200);
        }

        .wallet-footer p {
            color: var(--gray-500);
            font-size: 0.9rem;
        }

        .wallet-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .wallet-footer a:hover {
            color: var(--primary-dark);
        }

        /* === RESPONSIVIDADE === */
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .wallet-wrapper {
                padding: 2rem 1.5rem;
            }

            .wallet-cards {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .wallet-card {
                padding: 2rem 1.5rem;
            }

            .wallet-header,
            .wallet-footer,
            .wallet-description,
            .wallet-features,
            .wallet-icon {
                display: none;
            }
        }

        /* === ANIMAÇÕES === */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .wallet-wrapper {
            animation: fadeIn 0.6s ease-out;
        }

        .wallet-card {
            animation: fadeIn 0.8s ease-out;
        }

        .wallet-card:nth-child(2) {
            animation-delay: 0.1s;
        }
    </style>
</head>
<body>
    <div class="wallet-wrapper">
        <!-- Header -->
        <div class="wallet-header">
            <div class="logo-container">
                <img src="../../assets/images/logolaranja.png" alt="Klube Cash">
            </div>
            <h1 class="welcome-message">Bem-vindo, <?php echo htmlspecialchars($userName); ?>! 👋</h1>
            <p class="welcome-subtitle">Selecione a carteira que deseja acessar</p>
        </div>

        <!-- Wallet Cards -->
        <form method="POST" action="" id="wallet-form">
            <div class="wallet-cards">
                <!-- Carteira Klube Cash -->
                <div class="wallet-card klubecash" onclick="selectWallet('klubecash')">
                    <div class="wallet-icon">
                        💳
                    </div>
                    <h2 class="wallet-title">Klube Cash</h2>
                    <p class="wallet-description">
                        Sua carteira principal com cashback em todas as lojas parceiras
                    </p>
                    <ul class="wallet-features">
                        <li>Cashback em lojas parceiras</li>
                        <li>Gestão de saldo e transações</li>
                        <li>Histórico completo</li>
                        <li>Resgate facilitado</li>
                    </ul>
                    <button type="button" class="select-wallet-btn" onclick="selectWallet('klubecash')">
                        Acessar Klube Cash
                    </button>
                </div>

                <!-- Carteira SEST SENAT -->
                <div class="wallet-card senat" onclick="selectWallet('senat')">
                    <div class="wallet-icon">
                        🏢
                    </div>
                    <h2 class="wallet-title">SEST SENAT</h2>
                    <p class="wallet-description">
                        Carteira exclusiva para benefícios do programa SEST SENAT
                    </p>
                    <ul class="wallet-features">
                        <li>Benefícios exclusivos SEST SENAT</li>
                        <li>Saldo dedicado</li>
                        <li>Ofertas especiais parceiras</li>
                        <li>Gestão independente</li>
                    </ul>
                    <button type="button" class="select-wallet-btn" onclick="selectWallet('senat')">
                        Acessar SEST SENAT
                    </button>
                </div>
            </div>

            <input type="hidden" name="wallet" id="wallet-input">
        </form>

        <!-- Footer -->
        <div class="wallet-footer">
            <p>Você pode trocar de carteira a qualquer momento através do menu do seu perfil. | <a href="?action=logout">Sair</a></p>
        </div>
    </div>

    <script>
        function selectWallet(walletType) {
            // Definir o valor do input hidden
            document.getElementById('wallet-input').value = walletType;

            // Submeter o formulário
            document.getElementById('wallet-form').submit();
        }

        // Prevenir duplo clique
        let isSubmitting = false;
        document.getElementById('wallet-form').addEventListener('submit', function() {
            if (isSubmitting) {
                event.preventDefault();
                return false;
            }
            isSubmitting = true;
        });
    </script>
</body>
</html>
