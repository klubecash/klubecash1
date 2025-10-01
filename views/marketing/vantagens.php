<?php
// views/marketing/vantagens.php
// Página otimizada para "vantagens cashback" e "benefícios cashback"

$pageTitle = "Vantagens do Cashback - Todos os Benefícios do Klube Cash";
$pageDescription = "Descubra todas as vantagens de ganhar cashback: economia automática, dinheiro extra, compras mais inteligentes e muito mais. Conheça os benefícios exclusivos.";
$pageKeywords = "vantagens cashback, benefícios cashback, economia dinheiro, dinheiro extra, compras inteligentes";
$pageUrl = "https://klubecash.com/vantagens-cashback/";
$pageImage = "https://klubecash.com/assets/images/seo/vantagens-og.jpg";

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
    
    <!-- URLs Canônicas -->
    <link rel="canonical" href="<?php echo $pageUrl; ?>">
    <link rel="alternate" hreflang="pt-BR" href="<?php echo $pageUrl; ?>">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo $pageTitle; ?>">
    <meta property="og:description" content="<?php echo $pageDescription; ?>">
    <meta property="og:image" content="<?php echo $pageImage; ?>">
    <meta property="og:url" content="<?php echo $pageUrl; ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Klube Cash">
    
    <!-- Schema.org para página de benefícios -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebPage",
        "name": "Vantagens e Benefícios do Cashback",
        "description": "Conheça todas as vantagens de usar o sistema de cashback do Klube Cash",
        "url": "<?php echo $pageUrl; ?>",
        "mainEntity": {
            "@type": "ItemList",
            "itemListElement": [
                {
                    "@type": "ListItem",
                    "position": 1,
                    "name": "Economia Automática",
                    "description": "Ganhe dinheiro de volta automaticamente em todas suas compras"
                },
                {
                    "@type": "ListItem", 
                    "position": 2,
                    "name": "Sem Custos",
                    "description": "Sistema 100% gratuito sem taxas ou mensalidades"
                },
                {
                    "@type": "ListItem",
                    "position": 3,
                    "name": "Segurança Garantida",
                    "description": "Tecnologia bancária para proteger seus dados"
                }
            ]
        }
    }
    </script>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/marketing.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <!-- Header -->
    <header class="marketing-header">
        <nav class="navbar">
            <div class="container">
                <a href="/" class="logo">
                    <img src="../../assets/images/logo.webp" alt="Klube Cash" width="150" height="40">
                </a>
                <ul class="nav-menu">
                    <li><a href="/">Início</a></li>
                    <li><a href="/como-funciona/">Como Funciona</a></li>
                    <li><a href="/vantagens-cashback/" class="active">Vantagens</a></li>
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

    <!-- Breadcrumbs -->
    <nav class="breadcrumbs">
        <div class="container">
            <ol itemscope itemtype="https://schema.org/BreadcrumbList">
                <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                    <a itemprop="item" href="/"><span itemprop="name">Início</span></a>
                    <meta itemprop="position" content="1" />
                </li>
                <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                    <span itemprop="name">Vantagens do Cashback</span>
                    <meta itemprop="position" content="2" />
                </li>
            </ol>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section benefits-hero">
        <div class="container">
            <div class="hero-content">
                <h1>Todas as Vantagens de Ganhar Cashback</h1>
                <p class="hero-subtitle">Transforme cada compra em uma oportunidade de economia. Descubra por que mais de 150 mil pessoas escolheram o Klube Cash para economizar dinheiro.</p>
                <div class="value-proposition">
                    <div class="value-item">
                        <span class="value-icon">💰</span>
                        <span>Dinheiro extra todo mês</span>
                    </div>
                    <div class="value-item">
                        <span class="value-icon">🚀</span>
                        <span>Automático e sem esforço</span>
                    </div>
                    <div class="value-item">
                        <span class="value-icon">🔒</span>
                        <span>100% seguro e gratuito</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Principais Vantagens -->
    <section class="benefits-section">
        <div class="container">
            <h2>Por Que Usar o Klube Cash?</h2>
            <div class="benefits-grid">
                
                <!-- Vantagem 1: Economia Automática -->
                <div class="benefit-card featured">
                    <div class="benefit-icon">
                        <i class="fas fa-robot"></i>
                    </div>
                    <h3>Economia Automática</h3>
                    <p>Você não precisa fazer nada além de comprar normalmente. O cashback é calculado e creditado automaticamente em sua conta a cada compra.</p>
                    <div class="benefit-stats">
                        <span class="stat">Até 15% de volta</span>
                        <span class="stat">Em milhares de lojas</span>
                    </div>
                </div>

                <!-- Vantagem 2: Sem Custos -->
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-gift"></i>
                    </div>
                    <h3>100% Gratuito</h3>
                    <p>Não cobramos taxas de cadastro, mensalidades ou qualquer outro custo. Você só ganha dinheiro, nunca perde.</p>
                    <div class="benefit-highlight">
                        <span>✓ Sem taxas escondidas</span>
                        <span>✓ Sem mensalidades</span>
                        <span>✓ Cadastro gratuito</span>
                    </div>
                </div>

                <!-- Vantagem 3: Segurança -->
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Máxima Segurança</h3>
                    <p>Utilizamos a mesma tecnologia de segurança dos bancos, com criptografia de dados e certificações internacionais.</p>
                    <div class="security-badges">
                        <span class="badge">SSL 256-bit</span>
                        <span class="badge">PCI Compliant</span>
                    </div>
                </div>

                <!-- Vantagem 4: Facilidade -->
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3>Super Fácil de Usar</h3>
                    <p>Interface intuitiva e amigável. Acompanhe seus ganhos em tempo real pelo computador ou celular.</p>
                    <div class="feature-list">
                        <span>• Dashboard simples</span>
                        <span>• App móvel otimizado</span>
                        <span>• Relatórios detalhados</span>
                    </div>
                </div>

                <!-- Vantagem 5: Variedade -->
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-store"></i>
                    </div>
                    <h3>Milhares de Lojas</h3>
                    <p>Mais de 5.000 lojas parceiras em todas as categorias: moda, eletrônicos, casa, saúde, alimentação e muito mais.</p>
                    <div class="categories">
                        <span>🛍️ Moda</span>
                        <span>📱 Tecnologia</span>
                        <span>🏠 Casa & Decoração</span>
                        <span>🍔 Alimentação</span>
                    </div>
                </div>

                <!-- Vantagem 6: Controle Total -->
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Controle Total</h3>
                    <p>Acompanhe todos os seus ganhos, histórico de compras e projeções futuras em tempo real.</p>
                    <div class="control-features">
                        <span>📊 Relatórios detalhados</span>
                        <span>📈 Gráficos de evolução</span>
                        <span>🎯 Metas de economia</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Comparativo com outras soluções -->
    <section class="comparison-section">
        <div class="container">
            <h2>Klube Cash vs. Outros Sistemas</h2>
            <div class="comparison-table">
                <table>
                    <thead>
                        <tr>
                            <th>Característica</th>
                            <th class="highlight-column">Klube Cash</th>
                            <th>Cartões Cashback</th>
                            <th>Programas de Pontos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Taxa de adesão</td>
                            <td class="highlight-column">✅ Grátis</td>
                            <td>❌ Anuidade alta</td>
                            <td>⚠️ Varia</td>
                        </tr>
                        <tr>
                            <td>Cashback direto</td>
                            <td class="highlight-column">✅ Dinheiro real</td>
                            <td>⚠️ Desconto na fatura</td>
                            <td>❌ Apenas pontos</td>
                        </tr>
                        <tr>
                            <td>Número de lojas</td>
                            <td class="highlight-column">✅ 5.000+</td>
                            <td>⚠️ Limitado</td>
                            <td>⚠️ Poucos parceiros</td>
                        </tr>
                        <tr>
                            <td>Facilidade de uso</td>
                            <td class="highlight-column">✅ Muito fácil</td>
                            <td>⚠️ Moderado</td>
                            <td>❌ Complicado</td>
                        </tr>
                        <tr>
                            <td>Validade dos benefícios</td>
                            <td class="highlight-column">✅ Não expira</td>
                            <td>⚠️ Varia</td>
                            <td>❌ Expira</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Testemunhos e casos de sucesso -->
    <section class="testimonials-section">
        <div class="container">
            <h2>O Que Nossos Usuários Dizem</h2>
            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <div class="testimonial-content">
                        <p>"Em 6 meses usando o Klube Cash já economizei mais de R$ 500. É dinheiro que volta para o meu bolso sem eu precisar fazer nada diferente!"</p>
                    </div>
                    <div class="testimonial-author">
                        <div class="author-info">
                            <h4>Maria Silva</h4>
                            <span>Cliente desde 2023</span>
                        </div>
                        <div class="savings">
                            <span class="amount">R$ 547</span>
                            <span class="label">economizados</span>
                        </div>
                    </div>
                </div>

                <div class="testimonial-card">
                    <div class="testimonial-content">
                        <p>"O que mais gosto é a facilidade. Compro normalmente e o cashback aparece na minha conta. Uso o dinheiro para compras futuras!"</p>
                    </div>
                    <div class="testimonial-author">
                        <div class="author-info">
                            <h4>João Santos</h4>
                            <span>Cliente desde 2023</span>
                        </div>
                        <div class="savings">
                            <span class="amount">R$ 892</span>
                            <span class="label">economizados</span>
                        </div>
                    </div>
                </div>

                <div class="testimonial-card">
                    <div class="testimonial-content">
                        <p>"Recomendo para todo mundo! É uma forma inteligente de economizar dinheiro sem mudar seus hábitos de compra."</p>
                    </div>
                    <div class="testimonial-author">
                        <div class="author-info">
                            <h4>Ana Costa</h4>
                            <span>Cliente desde 2022</span>
                        </div>
                        <div class="savings">
                            <span class="amount">R$ 1.234</span>
                            <span class="label">economizados</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Final -->
    <section class="final-cta">
        <div class="container">
            <h2>Comece a Economizar Hoje Mesmo</h2>
            <p>Junte-se a milhares de pessoas que já descobriram as vantagens do cashback</p>
            <div class="cta-buttons">
                <a href="/registro/" class="btn-primary-large">Quero Economizar Agora</a>
                <a href="/como-funciona/" class="btn-outline-large">Entender Como Funciona</a>
            </div>
            <div class="guarantee">
                <i class="fas fa-shield-check"></i>
                <span>100% gratuito • Sem riscos • Comece em 2 minutos</span>
            </div>
        </div>
    </section>

    <!-- Footer igual ao anterior -->
    <footer class="site-footer">
        <!-- Mesmo conteúdo do footer anterior -->
    </footer>

    <!-- Scripts -->
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/marketing.js"></script>
</body>
</html>