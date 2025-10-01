<?php
// index.php - Versão Corrigida e Simplificada

// Inicialização da sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Lógica de logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Limpar variáveis de sessão
    $_SESSION = array();
    
    // Limpar cookies
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destruir a sessão
    session_destroy();
    
    // Redirecionar para a página inicial
    header('Location: ./');
    exit;
}

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'funcionario' && !isset($_SESSION['employee_subtype'])) {
    try {
        require_once './config/database.php';
        
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT u.subtipo_funcionario, u.loja_vinculada_id, l.nome_fantasia as loja_nome
            FROM usuarios u
            INNER JOIN lojas l ON u.loja_vinculada_id = l.id
            WHERE u.id = ? AND u.tipo = 'funcionario' AND u.status = 'ativo'
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($data) {
            $_SESSION['employee_subtype'] = $data['subtipo_funcionario'];
            $_SESSION['store_id'] = $data['loja_vinculada_id'];
            $_SESSION['store_name'] = $data['loja_nome'];
            
            switch($data['subtipo_funcionario']) {
                case 'gerente':
                    $_SESSION['employee_permissions'] = ['dashboard', 'transacoes', 'funcionarios', 'relatorios'];
                    break;
                case 'financeiro':
                    $_SESSION['employee_permissions'] = ['dashboard', 'comissoes', 'pagamentos', 'relatorios'];
                    break;
                case 'vendedor':
                    $_SESSION['employee_permissions'] = ['dashboard', 'transacoes'];
                    break;
                default:
                    $_SESSION['employee_permissions'] = ['dashboard'];
            }
        }
    } catch (Exception $e) {
        error_log('Erro ao corrigir sessão: ' . $e->getMessage());
    }
}

require_once './config/constants.php';
require_once './config/database.php';
require_once './session-guardian.php'; // ADICIONAR ESTA LINHA
/**
 * Função para renderizar logo da loja (mantida igual)
 */
function renderStoreLogo($store) {
    static $logoCache = [];
    
    $nomeFantasia = htmlspecialchars($store['nome_fantasia']);
    $primeiraLetra = strtoupper(substr($nomeFantasia, 0, 1));
    
    if (!empty($store['logo'])) {
        $logoFilename = $store['logo'];
        
        if (!isset($logoCache[$logoFilename])) {
            if (preg_match('/^[a-zA-Z0-9_.-]+\.(jpg|jpeg|png|gif)$/i', $logoFilename)) {
                $fullPath = __DIR__ . '/uploads/store_logos/' . $logoFilename;
                $logoCache[$logoFilename] = file_exists($fullPath);
            } else {
                $logoCache[$logoFilename] = false;
                error_log("Arquivo suspeito detectado: " . $logoFilename);
            }
        }
        
        if ($logoCache[$logoFilename]) {
            $logoPath = '/uploads/store_logos/' . htmlspecialchars($logoFilename);
            return '<img src="' . $logoPath . '" alt="Logo ' . $nomeFantasia . '" class="store-logo-image" loading="lazy">';
        }
    }
    
    $corDeFundo = generateColorFromName($nomeFantasia);
    return '<div class="store-logo-fallback" style="background: linear-gradient(135deg, ' . $corDeFundo . ', ' . adjustBrightness($corDeFundo, -20) . ')" title="' . $nomeFantasia . '">' . $primeiraLetra . '</div>';
}

function generateColorFromName($name) {
    $colors = [
        '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FECA57',
        '#FF9FF3', '#54A0FF', '#5F27CD', '#FF3838', '#00D2D3',
        '#FF6348', '#7bed9f', '#70a1ff', '#dda0dd', '#ffb142',
        '#ff7675', '#74b9ff', '#0984e3', '#00b894', '#fdcb6e'
    ];
    
    $hash = crc32($name);
    $index = abs($hash) % count($colors);
    return $colors[$index];
}

function adjustBrightness($hex, $percent) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    $r = max(0, min(255, $r + ($r * $percent / 100)));
    $g = max(0, min(255, $g + ($g * $percent / 100)));
    $b = max(0, min(255, $b + ($b * $percent / 100)));
    
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

// Sessão já foi inicializada no início do arquivo

// Verificação do usuário logado (mantida igual)
$isLoggedIn = isset($_SESSION['user_id']);
$userType = $isLoggedIn ? ($_SESSION['user_type'] ?? '') : '';
$userName = $isLoggedIn ? ($_SESSION['user_name'] ?? '') : '';

// Determinação da URL do dashboard (mantida igual)
$dashboardURL = '';
if ($isLoggedIn) {
    switch ($userType) {
        case 'admin':
            $dashboardURL = ADMIN_DASHBOARD_URL;
            break;
        case 'cliente':
            $dashboardURL = CLIENT_DASHBOARD_URL;
            break;
        case 'loja':
            $dashboardURL = STORE_DASHBOARD_URL;
            break;
        case 'funcionario':
            // Por enquanto, funcionários vão para o dashboard da loja
            $dashboardURL = STORE_DASHBOARD_URL;
            break;
    }
}

// Busca das lojas parceiras (mantida igual)
$partnerStores = [];
try {
    $db = Database::getConnection();
    
    $stmt = $db->query("
        SELECT 
            nome_fantasia, 
            logo, 
            categoria,
            descricao,
            porcentagem_cashback
        FROM lojas 
        WHERE status = 'aprovado' 
        ORDER BY RAND() 
        LIMIT 8
    ");
    $partnerStores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Lojas parceiras carregadas: " . count($partnerStores));
    
} catch (PDOException $e) {
    error_log("Erro ao buscar lojas parceiras: " . $e->getMessage());
    $partnerStores = [];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isLoggedIn ? "Bem-vindo ao Klube Cash, " . htmlspecialchars($userName) : "Klube Cash - Transforme suas Compras em Dinheiro de Volta"; ?></title>
    
    <!-- Meta tags otimizadas -->
    <meta name="description" content="Klube Cash - O programa de cashback mais inteligente do Brasil. Receba dinheiro de volta em todas as suas compras. Cadastre-se grátis e comece a economizar hoje mesmo!">
    <meta name="keywords" content="cashback, dinheiro de volta, economia, programa de fidelidade, compras online, desconto, lojas parceiras">
    <meta name="author" content="Klube Cash">
    <meta name="robots" content="index, follow">
    
    <!-- Favicons -->
    <link rel="icon" type="image/x-icon" href="assets/images/icons/KlubeCashLOGO.ico">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- CSS INLINE SIMPLIFICADO -->
    <style>
        /* === RESET E BASE === */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: #333;
            background: #fff;
        }

        /* === HEADER === */
        .modern-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 80px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid #e0e0e0;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            height: 100%;
        }

        .main-navigation {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 100%;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .logo-image {
            height: 40px;
            width: auto;
        }

        .desktop-menu {
            display: flex;
            list-style: none;
            gap: 30px;
        }

        .nav-link {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-link:hover {
            color: #FF7A00;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        /* User Menu */
        .user-menu {
            position: relative;
        }

        .user-button {
            display: flex;
            align-items: center;
            gap: 10px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #FF7A00, #FF9A40);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 200px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 8px;
            display: none;
        }

        .user-dropdown.show {
            display: block;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            text-decoration: none;
            color: #333;
            border-radius: 6px;
            transition: background 0.2s ease;
        }

        .dropdown-item:hover {
            background: #f5f5f5;
        }

        /* Mobile Menu */
        .mobile-menu-toggle {
            display: none;
            flex-direction: column;
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px;
        }

        .hamburger-line {
            width: 25px;
            height: 3px;
            background: #333;
            margin: 3px 0;
            transition: 0.3s;
        }

        .mobile-menu {
            display: none;
            position: fixed;
            top: 80px;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #e0e0e0;
            padding: 20px;
        }

        .mobile-menu.show {
            display: block;
        }

        .mobile-nav-list {
            list-style: none;
        }

        .mobile-nav-list li {
            margin: 15px 0;
        }

        .mobile-nav-link {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            font-size: 18px;
        }

        /* === BOTÕES === */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #FF7A00, #FF9A40);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 122, 0, 0.3);
        }

        .btn-ghost {
            background: transparent;
            color: #333;
            border: 2px solid #e0e0e0;
        }

        .btn-ghost:hover {
            border-color: #FF7A00;
            color: #FF7A00;
        }

        /* === LAYOUT PRINCIPAL === */
        .main-content {
            padding-top: 80px;
        }

        .section {
            padding: 80px 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* === HERO === */
        .hero {
            background: linear-gradient(135deg, #FF7A00 0%, #FF9A40 50%, #FFB366 100%);
            color: white;
            text-align: center;
            padding: 120px 0;
        }

        .hero h1 {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .hero p {
            font-size: 1.2rem;
            margin-bottom: 40px;
            opacity: 0.95;
        }

        .hero-actions {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 60px;
        }

        .hero-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 40px;
            max-width: 600px;
            margin: 0 auto;
            padding-top: 40px;
            border-top: 1px solid rgba(255,255,255,0.2);
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: #FFD700;
            display: block;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        /* === SEÇÕES === */
        .section-header {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-badge {
            display: inline-block;
            padding: 8px 16px;
            background: rgba(255, 122, 0, 0.1);
            color: #FF7A00;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 20px;
            color: #333;
        }

        .section-description {
            font-size: 1.1rem;
            color: #666;
            max-width: 600px;
            margin: 0 auto;
        }

        /* === GRID === */
        .grid {
            display: grid;
            gap: 30px;
        }

        .grid-3 {
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        }

        .grid-4 {
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        }

        /* === CARDS === */
        .card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border-color: #FF7A00;
        }

        .card-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 122, 0, 0.1);
            color: #FF7A00;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
        }

        .card h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: #333;
        }

        .card p {
            color: #666;
            line-height: 1.6;
        }

        /* === LOJAS PARCEIRAS === */
        .partner-item {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .partner-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .partner-logo {
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }

        .store-logo-image {
            max-width: 70px;
            max-height: 70px;
            border-radius: 8px;
            object-fit: contain;
        }

        .store-logo-fallback {
            width: 70px;
            height: 70px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 800;
            color: white;
            margin: 0 auto;
        }

        .partner-info h4 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }

        .partner-category {
            display: inline-block;
            padding: 4px 12px;
            background: #f5f5f5;
            color: #666;
            border-radius: 12px;
            font-size: 0.8rem;
            margin-bottom: 10px;
        }

        .partner-cashback {
            color: #FF7A00;
            font-weight: 700;
        }

        /* === CTA === */
        .cta {
            background: linear-gradient(135deg, #1a1a1a, #333);
            color: white;
            text-align: center;
            padding: 100px 0;
        }

        .cta h2 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 20px;
        }

        .cta p {
            font-size: 1.2rem;
            margin-bottom: 40px;
            opacity: 0.9;
        }

        /* === FOOTER === */
        .footer {
            background: #1a1a1a;
            color: white;
            padding: 60px 0 20px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }

        .footer h4 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            color: #FF7A00;
        }

        .footer ul {
            list-style: none;
        }

        .footer ul li {
            margin-bottom: 10px;
        }

        .footer a {
            color: #ccc;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer a:hover {
            color: #FF7A00;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #333;
            color: #999;
        }

        /* === RESPONSIVO === */
        @media (max-width: 768px) {
            .desktop-menu {
                display: none;
            }

            .mobile-menu-toggle {
                display: flex;
            }

            .hero h1 {
                font-size: 2rem;
            }

            .hero-actions {
                flex-direction: column;
                align-items: center;
            }

            .hero-stats {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .section-title {
                font-size: 2rem;
            }

            .btn {
                width: 100%;
                max-width: 300px;
            }
        }

        /* === UTILITÁRIOS === */
        .text-center { text-align: center; }
        .mb-0 { margin-bottom: 0; }
        .mb-20 { margin-bottom: 20px; }
        .mt-20 { margin-top: 20px; }

        /* === ANIMAÇÕES SIMPLES === */
        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .bg-light {
            background: #f8f9fa;
        }

        /* === HERO WELCOME === */
        .hero-welcome {
            margin-bottom: 40px;
        }

        .hero-welcome h1 {
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .hero-welcome p {
            font-size: 1.1rem;
            opacity: 0.95;
            max-width: 600px;
            margin: 0 auto;
        }


        /* === ESTILOS PARA FUNCIONÁRIOS === */
        .employee-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 215, 0, 0.15);
            color: #FF8C00;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 15px;
            border: 1px solid rgba(255, 215, 0, 0.3);
        }

        .employee-badge::before {
            content: "👔";
            font-size: 1rem;
        }

        /* Destaque especial para funcionários no hero */
        .hero .employee-badge {
            background: rgba(255, 255, 255, 0.2);
            color: #FFD700;
            border-color: rgba(255, 255, 255, 0.3);
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header class="modern-header" id="mainHeader">
        <div class="header-container">
            <nav class="main-navigation">
                <!-- Logo -->
                <a href="<?php echo SITE_URL; ?>" class="brand-logo">
                    <img src="assets/images/logolaranja.png" alt="Klube Cash" class="logo-image">
                </a>
                
                <!-- Menu Desktop -->
                <ul class="desktop-menu">
                    <li><a href="#como-funciona" class="nav-link">Como Funciona</a></li>
                    <li><a href="#vantagens" class="nav-link">Vantagens</a></li>
                    <li><a href="#parceiros" class="nav-link">Parceiros</a></li>
                    <li><a href="#sobre" class="nav-link">Sobre</a></li>
                </ul>
                
                <!-- Ações do Header -->
                <div class="header-actions">
                    <?php if ($isLoggedIn): ?>
                        <div class="user-menu">
                            <button class="user-button" id="userMenuBtn">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($userName, 0, 1)); ?>
                                </div>
                                <span style="font-weight: 600; color: #333;">
                                    <?php echo htmlspecialchars($userName); ?>
                                </span>
                            </button>
                            <div class="user-dropdown" id="userDropdown">
                                <a href="<?php echo htmlspecialchars($dashboardURL); ?>" class="dropdown-item">
                                    <span>🏠</span>
                                    <?php echo ($userType === 'funcionario') ? 'Painel da Loja' : 'Minha Conta'; ?>
                                </a>
                                <a href="#parceiros" class="dropdown-item">
                                    <span>🏪</span>
                                    Lojas Parceiras
                                </a>
                                <a href="?action=logout" class="dropdown-item">
                                    <span>🚪</span>
                                    Sair
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="<?php echo LOGIN_URL; ?>" class="btn btn-ghost">Entrar</a>
                        
                    <?php endif; ?>
                </div>
                
                <!-- Botão Mobile -->
                <button class="mobile-menu-toggle" id="mobileMenuBtn">
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                </button>
            </nav>
        </div>
        
        <!-- Menu Mobile -->
        <div class="mobile-menu" id="mobileMenu">
            <ul class="mobile-nav-list">
                <li><a href="#como-funciona" class="mobile-nav-link">Como Funciona</a></li>
                <li><a href="#vantagens" class="mobile-nav-link">Vantagens</a></li>
                <li><a href="#parceiros" class="mobile-nav-link">Parceiros</a></li>
                <li><a href="#sobre" class="mobile-nav-link">Sobre</a></li>
            </ul>
            
            <?php if (!$isLoggedIn): ?>
                <div style="margin-top: 20px;">
                    <a href="<?php echo LOGIN_URL; ?>" class="btn btn-ghost" style="margin-bottom: 10px;">Entrar</a>
                    <a href="<?php echo REGISTER_URL; ?>" class="btn btn-primary">Cadastrar Grátis</a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Conteúdo Principal -->
    <main class="main-content">
        <!-- Hero Section -->
        <section class="hero">
            <div class="container">
                <?php if ($isLoggedIn): ?>
                    <div class="hero-welcome">
                        <h1>Bem-vindo de volta, <?php echo htmlspecialchars($userName); ?>! 👋</h1>
                        
                        <?php if ($userType === 'funcionario' && isset($_SESSION['employee_subtype'])): ?>
                            <?php 
                            $subtypeMap = ['gerente' => 'Gerente', 'financeiro' => 'Financeiro', 'vendedor' => 'Vendedor'];
                            $subtypeDisplay = $subtypeMap[$_SESSION['employee_subtype']] ?? 'Funcionário';
                            ?>
                            <div class="employee-badge">
                                🎯 Acesso como: <?php echo $subtypeDisplay; ?>
                            </div>
                            <p>Gerencie as operações da sua loja com eficiência através do painel administrativo.</p>
                        <?php else: ?>
                            <p>Continue economizando com inteligência. Explore suas oportunidades de cashback e descubra novas formas de economizar.</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="hero-actions">
                        <a href="<?php echo htmlspecialchars($dashboardURL); ?>" class="btn btn-primary">
                            <?php echo ($userType === 'funcionario') ? 'Acessar Painel da Loja' : 'Acessar Minha Conta'; ?>
                        </a>
                        <a href="#parceiros" class="btn btn-ghost">Explorar Parceiros</a>
                    </div>
                <?php else: ?>
                    <h1>Transforme suas compras em dinheiro de volta</h1>
                    <p>O programa de cashback mais inteligente do Brasil. Cadastre-se gratuitamente e comece a receber dinheiro de volta em todas as suas compras.</p>
                    <div class="hero-actions">
                        <a href="<?php echo REGISTER_URL; ?>" class="btn btn-primary">
                            Começar Agora - É Grátis
                        </a>
                        <a href="#como-funciona" class="btn btn-ghost">Como Funciona?</a>
                    </div>
                <?php endif; ?>
                
                
            </div>
        </section>

        <!-- Como Funciona -->
        <section id="como-funciona" class="section">
            <div class="container">
                <div class="section-header">
                    <span class="section-badge">Processo Simples</span>
                    <h2 class="section-title">Como a Klube Cash Funciona?</h2>
                    <p class="section-description">
                        3 passos simples para começar a receber dinheiro de volta em todas as suas compras.
                    </p>
                </div>
                
                <div class="grid grid-3">
                    <div class="card fade-in">
                        <div class="card-icon">1</div>
                        <h3>Cadastre-se Gratuitamente</h3>
                        <p>Crie sua conta em menos de 2 minutos. É 100% gratuito e você não paga nada para participar do programa.</p>
                    </div>
                    
                    <div class="card fade-in">
                        <div class="card-icon">2</div>
                        <h3>Compre e Se Identifique</h3>
                        <p>Faça suas compras normalmente nas lojas parceiras e se identifique como membro Klube Cash no momento da compra.</p>
                    </div>
                    
                    <div class="card fade-in">
                        <div class="card-icon">3</div>
                        <h3>Receba Seu Cashback</h3>
                        <p>Uma porcentagem do valor das suas compras volta para sua conta Klube Cash. É crédito real que você pode usar!</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Vantagens -->
        <section id="vantagens" class="section bg-light">
            <div class="container">
                <div class="section-header">
                    <span class="section-badge">Por Que Escolher?</span>
                    <h2 class="section-title">Vantagens Exclusivas do Klube Cash</h2>
                    <p class="section-description">
                        Descubra porque somos a escolha número 1 de quem quer economizar de verdade
                    </p>
                </div>
                
                <div class="grid grid-3">
                    <div class="card fade-in">
                        <div class="card-icon">💰</div>
                        <h3>Cashback Real</h3>
                        <p>Crédito real que você terá na sua conta, não pontos que expiram ou vales que complicam sua vida.</p>
                    </div>
                    
                    <div class="card fade-in">
                        <div class="card-icon">🔒</div>
                        <h3>100% Seguro</h3>
                        <p>Plataforma criptografada e dados protegidos. Sua segurança é nossa prioridade máxima, e conformidade com a LGPD.</p>
                    </div>
                    
                    <div class="card fade-in">
                        <div class="card-icon">⚡</div>
                        <h3>Instantâneo</h3>
                        <p>Cashback processado rapidamente. Você vê o retorno do seu crédito em tempo real.</p>
                    </div>
                    
                    <div class="card fade-in">
                        <div class="card-icon">🛠️</div>
                        <h3>Suporte 24/7</h3>
                        <p>Equipe especializada sempre pronta para ajudar você com qualquer dúvida ou problema.</p>
                    </div>
                    
                    <div class="card fade-in">
                        <div class="card-icon">❤️</div>
                        <h3>Pagou, usou</h3>
                        <p>Use quando quiser, como quiser. Sem contratos longos ou obrigações chatas.</p>
                    </div>
                    
                    <div class="card fade-in">
                        <div class="card-icon">🏪</div>
                        <h3>Diversas Categorias em Expansão</h3>
                        <p>A cada dia, mais lojas estão chegando para ampliar suas escolhas.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Lojas Parceiras -->
        <section id="parceiros" class="section">
            <div class="container">
                <div class="section-header">
                    <span class="section-badge">Nossos Parceiros</span>
                    <h2 class="section-title">Onde Você Pode Usar o Klube Cash</h2>
                    <p class="section-description">
                        Descubra algumas das incríveis lojas parceiras onde você pode ganhar cashback
                    </p>
                </div>
                
                <?php if (!empty($partnerStores)): ?>
                    <div class="grid grid-4">
                        <?php foreach ($partnerStores as $store): ?>
                            <div class="partner-item fade-in">
                                <div class="partner-logo">
                                    <?php echo renderStoreLogo($store); ?>
                                </div>
                                <div class="partner-info">
                                    <h4><?php echo htmlspecialchars($store['nome_fantasia']); ?></h4>
                                    <?php if (!empty($store['categoria'])): ?>
                                        <span class="partner-category"><?php echo htmlspecialchars($store['categoria']); ?></span>
                                    <?php endif; ?>
                                    <!--<div class="partner-cashback">
                                        Cashback: <?php echo number_format($store['porcentagem_cashback'] ?? 5, 1); ?>%
                                    </div>-->
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="text-center mt-20">
                        
                        <a href="<?php echo STORE_REGISTER_URL; ?>" class="btn btn-primary">Quero Ser Parceiro</a>
                    </div>
                <?php else: ?>
                    <div class="text-center">
                        <h3>Em Breve: Lojas Incríveis!</h3>
                        <p>Estamos fechando parcerias com as melhores lojas para você.</p>
                        <a href="<?php echo STORE_REGISTER_URL; ?>" class="btn btn-primary">Seja o Primeiro Parceiro</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- CTA -->
        <section class="cta">
            <div class="container">
                <h2>Pronto para Começar a economizar Dinheiro?</h2>
                <p>Junte-se a milhares de brasileiros que já descobriram o segredo de transformar gastos em ganhos.</p>
                <a href="<?php echo REGISTER_URL; ?>" class="btn btn-primary">
                    Quero Meu Cashback Agora!
                </a>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <h4>Klube Cash</h4>
                    <p>Transformando suas compras em oportunidades de economia. O programa de cashback mais inteligente e confiável do Brasil.</p>
                </div>
                
                <div>
                    <h4>Links Rápidos</h4>
                    <ul>
                        <li><a href="#como-funciona">Como Funciona</a></li>
                        <li><a href="#vantagens">Vantagens</a></li>
                        <li><a href="#parceiros">Lojas Parceiras</a></li>
                        <li><a href="<?php echo STORE_REGISTER_URL; ?>">Seja Parceiro</a></li>
                    </ul>
                </div>
                
                <div>
                    <h4>Legal</h4>
                    <ul>
                        <li><a href="termos-de-uso.php">Termos de Uso</a></li>
                        <li><a href="politica-de-privacidade.php">Política de Privacidade</a></li>
                        <li><a href="#">Política de Cookies</a></li>
                    </ul>
                </div>
                
                <div>
                    <h4>Contato</h4>
                    <ul>
                        <li><a href="mailto:contato@klubecash.com">contato@klubecash.com</a></li>
                        <li><a href="tel:+55343030-1344">(34) 3030-1314</a></li>
                        <li>Patos de Minas, MG</li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Klube Cash. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <!-- JavaScript SIMPLIFICADO -->
    <script>
        // === FUNCIONALIDADES BÁSICAS ===
        document.addEventListener('DOMContentLoaded', function() {
            initMobileMenu();
            initUserMenu();
            initSmoothScroll();
        });

        // Menu Mobile
        function initMobileMenu() {
            const menuToggle = document.getElementById('mobileMenuBtn');
            const mobileMenu = document.getElementById('mobileMenu');
            
            if (!menuToggle || !mobileMenu) return;
            
            let isOpen = false;
            
            menuToggle.addEventListener('click', function() {
                isOpen = !isOpen;
                mobileMenu.classList.toggle('show', isOpen);
                
                // Animar hamburger
                const lines = menuToggle.querySelectorAll('.hamburger-line');
                if (isOpen) {
                    lines[0].style.transform = 'rotate(45deg) translate(5px, 5px)';
                    lines[1].style.opacity = '0';
                    lines[2].style.transform = 'rotate(-45deg) translate(7px, -6px)';
                } else {
                    lines[0].style.transform = '';
                    lines[1].style.opacity = '';
                    lines[2].style.transform = '';
                }
            });
            
            // Fechar ao clicar em links
            const mobileLinks = document.querySelectorAll('.mobile-nav-link');
            mobileLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (isOpen) {
                        menuToggle.click();
                    }
                });
            });
        }

        // Menu do Usuário
        function initUserMenu() {
            const userButton = document.getElementById('userMenuBtn');
            const userDropdown = document.getElementById('userDropdown');
            
            if (!userButton || !userDropdown) return;
            
            userButton.addEventListener('click', function(e) {
                e.stopPropagation();
                userDropdown.classList.toggle('show');
            });
            
            // Fechar ao clicar fora
            document.addEventListener('click', function() {
                userDropdown.classList.remove('show');
            });
        }

        // Scroll Suave
        function initSmoothScroll() {
            const links = document.querySelectorAll('a[href^="#"]');
            
            links.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const targetId = this.getAttribute('href');
                    const targetElement = document.querySelector(targetId);
                    
                    if (targetElement) {
                        const headerHeight = 80;
                        const targetPosition = targetElement.offsetTop - headerHeight;
                        
                        window.scrollTo({
                            top: targetPosition,
                            behavior: 'smooth'
                        });
                    }
                });
            });
        }

        // Animações simples on scroll
        function animateOnScroll() {
            const elements = document.querySelectorAll('.fade-in');
            
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, { threshold: 0.1 });
            
            elements.forEach(function(element) {
                element.style.opacity = '0';
                element.style.transform = 'translateY(20px)';
                element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(element);
            });
        }

        // Inicializar animações
        if ('IntersectionObserver' in window) {
            animateOnScroll();
        }

        console.log('✅ Klube Cash carregado com sucesso!');
    </script>
</body>
</html>