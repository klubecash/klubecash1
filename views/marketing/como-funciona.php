<?php
// views/marketing/como-funciona.php
// Página otimizada para "como funciona cashback"

// Configurações SEO específicas desta página
$pageTitle = "Como Funciona o Cashback - Guia Completo do Klube Cash";
$pageDescription = "Descubra como ganhar dinheiro de volta em todas suas compras com o Klube Cash. Sistema gratuito, seguro e fácil de usar. Comece hoje mesmo!";
$pageKeywords = "como funciona cashback, ganhar dinheiro compras, cashback gratis, sistema cashback brasil, dinheiro de volta";
$pageUrl = "https://klubecash.com/como-funciona/";
$pageImage = "https://klubecash.com/assets/images/seo/como-funciona-og.jpg";

// Incluir configurações globais
require_once '../../config/constants.php';
require_once '../../config/database.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Meta Tags SEO Otimizadas -->
    <title><?php echo $pageTitle; ?></title>
    <meta name="description" content="<?php echo $pageDescription; ?>">
    <meta name="keywords" content="<?php echo $pageKeywords; ?>">
    <meta name="author" content="Klube Cash">
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large">
    
    <!-- URLs Canônicas para evitar conteúdo duplicado -->
    <link rel="canonical" href="<?php echo $pageUrl; ?>">
    <link rel="alternate" hreflang="pt-BR" href="<?php echo $pageUrl; ?>">
    
    <!-- Open Graph para compartilhamento social -->
    <meta property="og:title" content="<?php echo $pageTitle; ?>">
    <meta property="og:description" content="<?php echo $pageDescription; ?>">
    <meta property="og:image" content="<?php echo $pageImage; ?>">
    <meta property="og:url" content="<?php echo $pageUrl; ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Klube Cash">
    <meta property="og:locale" content="pt_BR">
    
    <!-- Twitter Cards para melhor compartilhamento -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo $pageTitle; ?>">
    <meta name="twitter:description" content="<?php echo $pageDescription; ?>">
    <meta name="twitter:image" content="<?php echo $pageImage; ?>">
    
    <!-- Schema.org JSON-LD para estruturação semântica -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "HowTo",
        "name": "Como Funciona o Cashback no Klube Cash",
        "description": "Guia passo a passo para ganhar cashback em todas suas compras",
        "image": "<?php echo $pageImage; ?>",
        "totalTime": "PT5M",
        "supply": [],
        "tool": [],
        "step": [
            {
                "@type": "HowToStep",
                "name": "Cadastre-se gratuitamente",
                "text": "Crie sua conta no Klube Cash em menos de 2 minutos",
                "image": "https://klubecash.com/assets/images/step1.jpg"
            },
            {
                "@type": "HowToStep", 
                "name": "Faça compras em lojas parceiras",
                "text": "Compre normalmente nas milhares de lojas parceiras do Klube Cash",
                "image": "https://klubecash.com/assets/images/step2.jpg"
            },
            {
                "@type": "HowToStep",
                "name": "Receba cashback automaticamente",
                "text": "Ganhe dinheiro de volta automaticamente em sua conta",
                "image": "https://klubecash.com/assets/images/step3.jpg"
            }
        ]
    }
    </script>
    
    <!-- Preload de recursos críticos para velocidade -->
    <link rel="preload" href="../../assets/css/main.css" as="style">
    <link rel="preload" href="../../assets/css/marketing.css" as="style">
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" as="style" crossorigin>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/marketing.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Favicon e ícones -->
    <link rel="icon" type="image/x-icon" href="../../assets/images/favicon.ico">
    <link rel="apple-touch-icon" href="../../assets/images/apple-touch-icon.png">
</head>

<body>
    <!-- Header com navegação principal -->
    <header class="marketing-header">
        <nav class="navbar">
            <div class="container">
                <a href="/" class="logo">
                    <img src="../../assets/images/logo.webp" alt="Klube Cash - Sistema de Cashback" width="150" height="40">
                </a>
                <ul class="nav-menu">
                    <li><a href="/">Início</a></li>
                    <li><a href="/como-funciona/" class="active">Como Funciona</a></li>
                    <li><a href="/vantagens-cashback/">Vantagens</a></li>
                    <li><a href="/lojas-parceiras/">Lojas Parceiras</a></li>
                    <li><a href="/blog/">Blog</a></li>
                    <li><a href="/contato/">Contato</a></li>
                </ul>
                <div class="nav-actions">
                    <a href="/login/" class="btn-outline">Entrar</a>
                    <a href="/registro/" class="btn-primary">Cadastrar</a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Breadcrumbs para navegação estruturada -->
    <nav class="breadcrumbs" aria-label="Navegação estruturada">
        <div class="container">
            <ol itemscope itemtype="https://schema.org/BreadcrumbList">
                <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                    <a itemprop="item" href="/"><span itemprop="name">Início</span></a>
                    <meta itemprop="position" content="1" />
                </li>
                <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                    <span itemprop="name">Como Funciona</span>
                    <meta itemprop="position" content="2" />
                </li>
            </ol>
        </div>
    </nav>

    <!-- Seção Hero otimizada para conversão -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content">
                <h1>Como Funciona o Cashback no Klube Cash</h1>
                <p class="hero-subtitle">Ganhe dinheiro de volta em todas suas compras de forma simples, segura e gratuita. Descubra como transformar cada compra em uma oportunidade de economizar.</p>
                <div class="hero-stats">
                    <div class="stat">
                        <span class="number">R$ 2.5M+</span>
                        <span class="label">Em cashback pago</span>
                    </div>
                    <div class="stat">
                        <span class="number">150mil+</span>
                        <span class="label">Usuários ativos</span>
                    </div>
                    <div class="stat">
                        <span class="number">5mil+</span>
                        <span class="label">Lojas parceiras</span>
                    </div>
                </div>
                <a href="/registro/" class="cta-button">Começar Agora - É Grátis</a>
            </div>
            <div class="hero-image">
                <img src="../../assets/images/como-funciona-hero.webp" alt="Como funciona o cashback - Pessoa usando smartphone" width="600" height="400">
            </div>
        </div>
    </section>

    <!-- Seção explicativa passo a passo -->
    <section class="steps-section">
        <div class="container">
            <h2>Como Ganhar Cashback em 3 Passos Simples</h2>
            <p class="section-subtitle">O sistema mais fácil e seguro para ganhar dinheiro de volta em suas compras online e físicas</p>
            
            <div class="steps-grid">
                <!-- Passo 1 -->
                <div class="step-card">
                    <div class="step-number">1</div>
                    <div class="step-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h3>Cadastre-se Gratuitamente</h3>
                    <p>Crie sua conta no Klube Cash em menos de 2 minutos. É 100% gratuito e sem taxas escondidas. Você só precisa de um email válido e CPF.</p>
                    <ul class="step-benefits">
                        <li>✓ Cadastro rápido e seguro</li>
                        <li>✓ Sem taxas ou mensalidades</li>
                        <li>✓ Dados protegidos com criptografia</li>
                    </ul>
                </div>

                <!-- Passo 2 -->
                <div class="step-card">
                    <div class="step-number">2</div>
                    <div class="step-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h3>Compre em Lojas Parceiras</h3>
                    <p>Faça suas compras normalmente em qualquer uma das milhares de lojas parceiras. O cashback é calculado automaticamente sobre o valor da compra.</p>
                    <ul class="step-benefits">
                        <li>✓ Mais de 5.000 lojas parceiras</li>
                        <li>✓ Compras online e físicas</li>
                        <li>✓ Cashback de 1% a 15%</li>
                    </ul>
                </div>

                <!-- Passo 3 -->
                <div class="step-card">
                    <div class="step-number">3</div>
                    <div class="step-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <h3>Receba Seu Dinheiro</h3>
                    <p>O cashback fica disponível em sua conta e pode ser usado nas próximas compras na mesma loja. É dinheiro real que você pode usar quando quiser.</p>
                    <ul class="step-benefits">
                        <li>✓ Cashback liberado automaticamente</li>
                        <li>✓ Use nas próximas compras</li>
                        <li>✓ Sem limite mínimo</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Seção de perguntas frequentes -->
    <section class="faq-section">
        <div class="container">
            <h2>Perguntas Frequentes sobre Cashback</h2>
            <div class="faq-grid">
                <div class="faq-item">
                    <h3>O que é cashback?</h3>
                    <p>Cashback significa "dinheiro de volta". É um sistema onde você recebe uma porcentagem do valor gasto de volta em sua conta a cada compra realizada.</p>
                </div>
                
                <div class="faq-item">
                    <h3>Como o Klube Cash ganha dinheiro?</h3>
                    <p>Recebemos uma comissão das lojas parceiras e dividimos esse valor com você. É um sistema onde todos ganham: você economiza, as lojas vendem mais e nós crescemos juntos.</p>
                </div>

                <div class="faq-item">
                    <h3>Tem algum custo para usar?</h3>
                    <p>Não! O Klube Cash é 100% gratuito para clientes. Não cobramos taxas de cadastro, mensalidade ou qualquer outro custo. Você só ganha dinheiro.</p>
                </div>

                <div class="faq-item">
                    <h3>Como posso usar meu cashback?</h3>
                    <p>O cashback fica disponível em sua conta e pode ser usado como desconto em futuras compras na mesma loja onde foi gerado.</p>
                </div>

                <div class="faq-item">
                    <h3>Quanto tempo demora para receber?</h3>
                    <p>O cashback fica disponível assim que a loja confirma o pagamento da compra, normalmente entre 1 a 3 dias úteis após a compra.</p>
                </div>

                <div class="faq-item">
                    <h3>É seguro usar o Klube Cash?</h3>
                    <p>Sim! Utilizamos a mesma tecnologia de segurança dos bancos, com criptografia de dados e protocolos de segurança certificados internacionalmente.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Seção call-to-action final -->
    <section class="final-cta">
        <div class="container">
            <h2>Pronto para Começar a Ganhar Cashback?</h2>
            <p>Junte-se a mais de 150 mil pessoas que já descobriram como economizar dinheiro em todas as compras</p>
            <div class="cta-buttons">
                <a href="/registro/" class="btn-primary-large">Criar Conta Grátis</a>
                <a href="/vantagens-cashback/" class="btn-outline-large">Ver Todas as Vantagens</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>Klube Cash</h4>
                    <p>O sistema de cashback que transforma suas compras em economia real.</p>
                </div>
                <div class="footer-section">
                    <h4>Para Você</h4>
                    <ul>
                        <li><a href="/como-funciona/">Como Funciona</a></li>
                        <li><a href="/vantagens-cashback/">Vantagens</a></li>
                        <li><a href="/lojas-parceiras/">Lojas Parceiras</a></li>
                        <li><a href="/registro/">Cadastrar</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Para Empresas</h4>
                    <ul>
                        <li><a href="/cashback-para-lojas/">Seja Parceiro</a></li>
                        <li><a href="/sistema-cashback-empresas/">Para Empresas</a></li>
                        <li><a href="/contato/">Contato</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Suporte</h4>
                    <ul>
                        <li><a href="/contato/">Central de Ajuda</a></li>
                        <li><a href="/blog/">Blog</a></li>
                        <li><a href="/sobre-klube-cash/">Sobre Nós</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Klube Cash. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <!-- Scripts otimizados -->
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/marketing.js"></script>
    
    <!-- Google Analytics 4 -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=GA_MEASUREMENT_ID"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'GA_MEASUREMENT_ID');
        
        // Evento personalizado para interesse em cadastro
        document.querySelectorAll('a[href="/registro/"]').forEach(link => {
            link.addEventListener('click', () => {
                gtag('event', 'interesse_cadastro', {
                    'event_category': 'conversion',
                    'event_label': 'como_funciona_page'
                });
            });
        });
    </script>
</body>
</html>