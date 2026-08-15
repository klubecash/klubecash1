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
    $nomeFantasia = htmlspecialchars($store['nome_fantasia']);
    $logoUrl = '';

    if (!empty($store['logo'])) {
        $logoFilename = $store['logo'];
        // Basic validation to prevent directory traversal
        if (preg_match('/^[a-zA-Z0-9_.-]+\.(jpg|jpeg|png|gif)$/i', $logoFilename)) {
            $logoPath = '/uploads/store_logos/' . $logoFilename;
            $fullPath = __DIR__ . $logoPath;
            if (file_exists($fullPath)) {
                $logoUrl = $logoPath;
            }
        }
    }

    if ($logoUrl) {
        return '<img src="' . htmlspecialchars($logoUrl) . '" alt="Logo ' . $nomeFantasia . '" class="store-logo-image" loading="lazy">';
    } else {
        // Fallback to a placeholder or the initial-based div
        $primeiraLetra = strtoupper(substr($nomeFantasia, 0, 1));
        $corDeFundo = generateColorFromName($nomeFantasia);
        return '<div class="store-logo-fallback" style="background: linear-gradient(135deg, ' . $corDeFundo . ', ' . adjustBrightness($corDeFundo, -20) . ')" title="' . $nomeFantasia . '">' . $primeiraLetra . '</div>';
    }
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

// Determinação da URL do dashboard
$dashboardURL = '';

if ($isLoggedIn) {
    // Define URL do dashboard baseado no tipo de usuário
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
        LIMIT 12
    ");
    $partnerStores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Lojas parceiras carregadas: " . count($partnerStores));
    
} catch (Throwable $e) {
    error_log("Erro ao buscar lojas parceiras: " . $e->getMessage());
    $partnerStores = [];
}
?>

<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
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
    <link rel="icon" type="image/x-icon" href="<?php echo rtrim(SITE_URL, '/'); ?>/assets/images/icons/KlubeCashLOGO.ico">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tema inicial sem flash -->
    <meta name="theme-color" id="themeColor" content="#FFF8F3" data-light="#FFF8F3" data-dark="#0B0D12">
    <script>
        (function (document, window) {
            var root = document.documentElement;
            var savedTheme = null;
            var prefersDark = false;

            root.classList.add('js');

            try {
                prefersDark = window.matchMedia
                    && window.matchMedia('(prefers-color-scheme: dark)').matches;
            } catch (error) {
                prefersDark = false;
            }

            try {
                savedTheme = window.localStorage.getItem('klubecash-theme');
            } catch (error) {
                savedTheme = null;
            }

            var theme = savedTheme === 'light' || savedTheme === 'dark'
                ? savedTheme
                : (prefersDark ? 'dark' : 'light');

            root.setAttribute('data-theme', theme);
            root.style.colorScheme = theme;

            var themeColor = document.getElementById('themeColor');
            if (themeColor) {
                themeColor.setAttribute('content', theme === 'dark' ? '#0B0D12' : '#FFF8F3');
            }
        }(document, window));
    </script>

    <!-- Estilos da homepage -->
    <link rel="stylesheet" href="<?php echo rtrim(SITE_URL, '/'); ?>/assets/css/index-modern.css?v=<?php echo filemtime(__DIR__ . '/assets/css/index-modern.css'); ?>">
</head>

<body class="home-page">
    <div class="page-atmosphere" aria-hidden="true">
        <span class="atmosphere-orb atmosphere-orb-one"></span>
        <span class="atmosphere-orb atmosphere-orb-two"></span>
        <span class="atmosphere-orb atmosphere-orb-three"></span>
    </div>

    <!-- Header -->
    <header class="modern-header" id="mainHeader">
        <div class="header-container">
            <nav class="main-navigation" aria-label="Navegação principal">
                <!-- Logo -->
                <a href="<?php echo SITE_URL; ?>" class="brand-logo">
                    <img src="<?php echo rtrim(SITE_URL, '/'); ?>/assets/images/logolaranja.png" alt="Klube Cash" class="logo-image">
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
                    <button
                        type="button"
                        class="theme-toggle"
                        id="themeToggle"
                        aria-label="Alternar tema"
                        aria-pressed="false"
                    >
                        <span class="theme-icon-sun" aria-hidden="true"></span>
                        <span class="theme-icon-moon" aria-hidden="true"></span>
                    </button>

                    <?php if ($isLoggedIn): ?>
                        <div class="user-menu">
                            <button
                                type="button"
                                class="user-button"
                                id="userMenuBtn"
                                aria-haspopup="true"
                                aria-expanded="false"
                                aria-controls="userDropdown"
                            >
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($userName, 0, 1)); ?>
                                </div>
                                <span class="user-name">
                                    <?php echo htmlspecialchars($userName); ?>
                                </span>
                            </button>
                            <div class="user-dropdown" id="userDropdown" role="menu" aria-hidden="true">
                                <a href="<?php echo htmlspecialchars($dashboardURL); ?>" class="dropdown-item" role="menuitem">
                                    <span aria-hidden="true">🏠</span>
                                    <?php echo ($userType === 'funcionario') ? 'Painel da Loja' : 'Minha Conta'; ?>
                                </a>
                                <a href="#parceiros" class="dropdown-item" role="menuitem">
                                    <span aria-hidden="true">🏪</span>
                                    Lojas Parceiras
                                </a>
                                <a href="?action=logout" class="dropdown-item" role="menuitem">
                                    <span aria-hidden="true">🚪</span>
                                    Sair
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="<?php echo LOGIN_URL; ?>" class="btn btn-ghost">Entrar</a>

                    <?php endif; ?>
                </div>

                <!-- Botão Mobile -->
                <button
                    type="button"
                    class="mobile-menu-toggle"
                    id="mobileMenuBtn"
                    aria-label="Abrir menu de navegação"
                    aria-expanded="false"
                    aria-controls="mobileMenu"
                >
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                </button>
            </nav>
        </div>

        <!-- Menu Mobile -->
        <div class="mobile-menu" id="mobileMenu" aria-hidden="true">
            <ul class="mobile-nav-list">
                <li><a href="#como-funciona" class="mobile-nav-link">Como Funciona</a></li>
                <li><a href="#vantagens" class="mobile-nav-link">Vantagens</a></li>
                <li><a href="#parceiros" class="mobile-nav-link">Parceiros</a></li>
                <li><a href="#sobre" class="mobile-nav-link">Sobre</a></li>
            </ul>

            <?php if (!$isLoggedIn): ?>
                <div class="mobile-menu-actions">
                    <a href="<?php echo LOGIN_URL; ?>" class="btn btn-ghost">Entrar</a>
                    <a href="<?php echo REGISTER_URL; ?>" class="btn btn-primary">Cadastrar Grátis</a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Conteúdo Principal -->
    <main class="main-content">
        <!-- Hero Section -->
        <section class="hero" aria-labelledby="hero-title">
            <div class="container">
                <div class="hero-layout">
                    <div class="hero-copy fade-in">
                        <?php if ($isLoggedIn): ?>
                            <div class="hero-welcome">
                                <h1 id="hero-title">Bem-vindo de volta, <?php echo htmlspecialchars($userName); ?>! 👋</h1>

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
                            <h1 id="hero-title">Transforme suas compras em dinheiro de volta</h1>
                            <p>O programa de cashback mais inteligente do Brasil. Cadastre-se gratuitamente e comece a receber dinheiro de volta em todas as suas compras.</p>
                            <div class="hero-actions">
                                <a href="<?php echo REGISTER_URL; ?>" class="btn btn-primary">
                                    Começar Agora - É Grátis
                                </a>
                                <a href="#como-funciona" class="btn btn-ghost">Como Funciona?</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="hero-visual fade-in" aria-hidden="true">
                        <div class="hero-orbit">
                            <span class="hero-orbit-dot hero-orbit-dot-one"></span>
                            <span class="hero-orbit-dot hero-orbit-dot-two"></span>
                            <span class="hero-orbit-dot hero-orbit-dot-three"></span>
                        </div>
                        <div class="hero-brand-card">
                            <img src="<?php echo rtrim(SITE_URL, '/'); ?>/assets/images/logobranco.png" alt="" class="hero-brand-logo">
                        </div>
                        <div class="hero-data-card hero-data-card-top">
                            <span class="data-dot"></span>
                            <span class="data-line data-line-long"></span>
                            <span class="data-line data-line-short"></span>
                        </div>
                        <div class="hero-data-card hero-data-card-bottom">
                            <span class="data-dot"></span>
                            <span class="data-line data-line-short"></span>
                            <span class="data-line data-line-long"></span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Como Funciona -->
        <section id="como-funciona" class="section">
            <div class="container">
                <div class="section-header fade-in">
                    <span class="section-badge">Processo Simples</span>
                    <h2 class="section-title">Como a Klube Cash Funciona?</h2>
                    <p class="section-description">
                        3 passos simples para começar a receber dinheiro de volta em todas as suas compras.
                    </p>
                </div>

                <div class="grid grid-3 steps-grid">
                    <article class="card step-card fade-in">
                        <div class="card-icon">1</div>
                        <h3>Cadastre-se Gratuitamente</h3>
                        <p>Crie sua conta em menos de 2 minutos. É 100% gratuito e você não paga nada para participar do programa.</p>
                    </article>

                    <article class="card step-card fade-in">
                        <div class="card-icon">2</div>
                        <h3>Compre e Se Identifique</h3>
                        <p>Faça suas compras normalmente nas lojas parceiras e se identifique como membro Klube Cash no momento da compra.</p>
                    </article>

                    <article class="card step-card fade-in">
                        <div class="card-icon">3</div>
                        <h3>Receba Seu Cashback</h3>
                        <p>Uma porcentagem do valor das suas compras volta para sua conta Klube Cash. É crédito real que você pode usar!</p>
                    </article>
                </div>
            </div>
        </section>

        <!-- Vantagens -->
        <section id="vantagens" class="section bg-light">
            <div class="container">
                <div class="section-header fade-in">
                    <span class="section-badge">Por Que Escolher?</span>
                    <h2 class="section-title">Vantagens Exclusivas do Klube Cash</h2>
                    <p class="section-description">
                        Descubra porque somos a escolha número 1 de quem quer economizar de verdade
                    </p>
                </div>

                <div class="grid grid-3 benefits-grid">
                    <article class="card benefit-card fade-in">
                        <div class="card-icon">💰</div>
                        <h3>Cashback Real</h3>
                        <p>Crédito real que você terá na sua conta, não pontos que expiram ou vales que complicam sua vida.</p>
                    </article>

                    <article class="card benefit-card fade-in">
                        <div class="card-icon">🔒</div>
                        <h3>100% Seguro</h3>
                        <p>Plataforma criptografada e dados protegidos. Sua segurança é nossa prioridade máxima, e conformidade com a LGPD.</p>
                    </article>

                    <article class="card benefit-card fade-in">
                        <div class="card-icon">⚡</div>
                        <h3>Instantâneo</h3>
                        <p>Cashback processado rapidamente. Você vê o retorno do seu crédito em tempo real.</p>
                    </article>

                    <article class="card benefit-card fade-in">
                        <div class="card-icon">🛠️</div>
                        <h3>Suporte 24/7</h3>
                        <p>Equipe especializada sempre pronta para ajudar você com qualquer dúvida ou problema.</p>
                    </article>

                    <article class="card benefit-card fade-in">
                        <div class="card-icon">❤️</div>
                        <h3>Pagou, usou</h3>
                        <p>Use quando quiser, como quiser. Sem contratos longos ou obrigações chatas.</p>
                    </article>

                    <article class="card benefit-card fade-in">
                        <div class="card-icon">🏪</div>
                        <h3>Diversas Categorias em Expansão</h3>
                        <p>A cada dia, mais lojas estão chegando para ampliar suas escolhas.</p>
                    </article>
                </div>
            </div>
        </section>

        <!-- Lojas Parceiras -->
        <section id="parceiros" class="section">
            <div class="container">
                <div class="section-header fade-in">
                    <span class="section-badge">Nossos Parceiros</span>
                    <h2 class="section-title">Onde Você Pode Usar o Klube Cash</h2>
                    <p class="section-description">
                        Descubra algumas das incríveis lojas parceiras onde você pode ganhar cashback
                    </p>
                </div>

                <?php if (!empty($partnerStores)): ?>
                    <div class="grid grid-4 partners-grid">
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

        <!-- Sobre -->
        <section id="sobre" class="section bg-light">
            <div class="container">
                <div class="section-header fade-in">
                    <span class="section-badge">Quem Somos</span>
                    <h2 class="section-title">Sobre o Klube Cash</h2>
                    <p class="section-description">
                        Conheça nossa história e missão de transformar a forma como você economiza
                    </p>
                </div>

                <div class="grid grid-3 about-grid">
                    <article class="card about-card fade-in">
                        <div class="card-icon">🎯</div>
                        <h3>Nossa Missão</h3>
                        <p>Democratizar o acesso ao cashback no Brasil, oferecendo uma plataforma intuitiva, segura e que realmente coloca dinheiro de volta no bolso dos nossos usuários.</p>
                    </article>

                    <article class="card about-card fade-in">
                        <div class="card-icon">👁️</div>
                        <h3>Nossa Visão</h3>
                        <p>Ser a maior e mais confiável plataforma de cashback do Brasil, reconhecida pela transparência, inovação e pelo compromisso com a satisfação dos nossos clientes.</p>
                    </article>

                    <article class="card about-card fade-in">
                        <div class="card-icon">💎</div>
                        <h3>Nossos Valores</h3>
                        <p>Transparência total, segurança em primeiro lugar, compromisso com o cliente, inovação constante e parcerias justas para todos.</p>
                    </article>
                </div>

                <div class="about-story fade-in">
                    <h3>Por Que Klube Cash?</h3>
                    <p>
                        Nascemos da vontade de criar algo diferente no mercado de cashback brasileiro. Cansados de sistemas complicados,
                        taxas escondidas e benefícios que nunca se concretizam, decidimos criar uma plataforma onde o cliente é realmente valorizado.
                    </p>
                    <p>
                        Hoje, ajudamos milhares de brasileiros a economizar todos os dias, conectando consumidores a lojas parceiras
                        de forma simples, rápida e 100% transparente. Seu dinheiro de volta, do jeito que deveria ser.
                    </p>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="cta">
            <div class="container">
                <div class="cta-inner fade-in">
                    <h2>Pronto para Começar a economizar Dinheiro?</h2>
                    <p>Junte-se a milhares de brasileiros que já descobriram o segredo de transformar gastos em ganhos.</p>
                    <a href="<?php echo REGISTER_URL; ?>" class="btn btn-primary">
                        Quero Meu Cashback Agora!
                    </a>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
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

    <script src="<?php echo rtrim(SITE_URL, '/'); ?>/assets/js/index-modern.js?v=<?php echo filemtime(__DIR__ . '/assets/js/index-modern.js'); ?>" defer></script>
</body>
</html>
