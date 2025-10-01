<?php
// views/landing/lojas.php
// Landing page otimizada para "cashback para lojas" e "sistema cashback empresas"

$pageTitle = "Cashback para Lojas - Sistema que Aumenta Vendas e Fideliza Clientes";
$pageDescription = "Aumente suas vendas em até 40% com o sistema de cashback do Klube Cash. Ferramenta gratuita para atrair e fidelizar clientes. Cadastre sua loja hoje mesmo!";
$pageKeywords = "cashback para lojas, sistema cashback empresas, aumentar vendas loja, fidelizar clientes, programa fidelidade";
$pageUrl = "https://klubecash.com/cashback-para-lojas/";
$pageImage = "https://klubecash.com/assets/images/seo/lojas-og.jpg";

require_once '../../config/constants.php';
require_once '../../config/database.php';

// Dados dinâmicos para credibilidade (podem vir do banco de dados)
$totalLojasParceiras = 5247;
$crescimentoVendas = 38;
$clientesFidelizados = 92;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Meta Tags SEO Otimizadas para B2B -->
    <title><?php echo $pageTitle; ?></title>
    <meta name="description" content="<?php echo $pageDescription; ?>">
    <meta name="keywords" content="<?php echo $pageKeywords; ?>">
    <meta name="author" content="Klube Cash">
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large">
    
    <!-- URLs Canônicas -->
    <link rel="canonical" href="<?php echo $pageUrl; ?>">
    <link rel="alternate" hreflang="pt-BR" href="<?php echo $pageUrl; ?>">
    
    <!-- Open Graph otimizado para compartilhamento B2B -->
    <meta property="og:title" content="<?php echo $pageTitle; ?>">
    <meta property="og:description" content="<?php echo $pageDescription; ?>">
    <meta property="og:image" content="<?php echo $pageImage; ?>">
    <meta property="og:url" content="<?php echo $pageUrl; ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Klube Cash">
    
    <!-- Schema.org para negócios locais e serviços -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Service",
        "name": "Sistema de Cashback para Lojas",
        "description": "Plataforma que ajuda lojas a aumentar vendas e fidelizar clientes através de cashback automático",
        "provider": {
            "@type": "Organization",
            "name": "Klube Cash",
            "url": "https://klubecash.com"
        },
        "serviceType": "Marketing e Fidelização",
        "areaServed": "Brasil",
        "hasOfferCatalog": {
            "@type": "OfferCatalog",
            "name": "Planos de Cashback",
            "itemListElement": [
                {
                    "@type": "Offer",
                    "itemOffered": {
                        "@type": "Service",
                        "name": "Cadastro Gratuito"
                    },
                    "price": "0",
                    "priceCurrency": "BRL"
                }
            ]
        }
    }
    </script>
    
    <!-- CSS específico para landing pages B2B -->
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/landing.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Preload de recursos críticos -->
    <link rel="preload" href="../../assets/images/hero-loja.webp" as="image">
</head>

<body class="landing-page">
    <!-- Header simplificado para foco na conversão -->
    <header class="landing-header">
        <nav class="navbar">
            <div class="container">
                <a href="/" class="logo">
                    <img src="../../assets/images/logo.webp" alt="Klube Cash" width="150" height="40">
                </a>
                <div class="header-contact">
                    <span class="phone">📞 (11) 99999-9999</span>
                    <a href="/contato/" class="btn-outline-small">Falar com Consultor</a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Hero Section otimizada para conversão B2B -->
    <section class="hero-landing">
        <div class="container">
            <div class="hero-content">
                <div class="hero-text">
                    <!-- Headline focado no principal benefício -->
                    <h1>Aumente suas Vendas em até <?php echo $crescimentoVendas; ?>% com Cashback Automático</h1>
                    
                    <!-- Subheadline explicando o mecanismo -->
                    <p class="hero-subtitle">Sistema gratuito que transforma cada cliente em cliente fiel. Seus clientes ganham dinheiro de volta e voltam para comprar mais na sua loja.</p>
                    
                    <!-- Prova social imediata -->
                    <div class="social-proof">
                        <span class="proof-item">
                            <strong><?php echo number_format($totalLojasParceiras); ?>+</strong> lojas parceiras
                        </span>
                        <span class="proof-item">
                            <strong><?php echo $clientesFidelizados; ?>%</strong> dos clientes voltam a comprar
                        </span>
                        <span class="proof-item">
                            <strong>R$ 2.5M+</strong> em cashback pago
                        </span>
                    </div>
                    
                    <!-- CTAs principais com urgência e benefício -->
                    <div class="hero-ctas">
                        <a href="#cadastro" class="btn-primary-xl">Cadastrar Minha Loja Grátis</a>
                        <a href="#como-funciona-loja" class="btn-outline-xl">Ver Como Funciona</a>
                    </div>
                    
                    <!-- Garantias para reduzir objeções -->
                    <div class="guarantees">
                        <span class="guarantee-item">✅ 100% Gratuito</span>
                        <span class="guarantee-item">✅ Sem Taxas Escondidas</span>
                        <span class="guarantee-item">✅ Configuração em 5 minutos</span>
                    </div>
                </div>
                
                <div class="hero-image">
                    <img src="../../assets/images/hero-loja.webp" alt="Lojista feliz usando sistema de cashback" width="600" height="400">
                    
                    <!-- Depoimento em destaque -->
                    <div class="hero-testimonial">
                        <blockquote>
                            "Minhas vendas aumentaram 35% desde que comecei a usar o Klube Cash. Os clientes adoram ganhar cashback!"
                        </blockquote>
                        <cite>
                            <img src="../../assets/images/cliente-exemplo.jpg" alt="Foto do cliente" width="40" height="40">
                            <span>Carlos Silva - Loja de Roupas SP</span>
                        </cite>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Seção de problemas que resolve (importante para B2B) -->
    <section class="problems-solution">
        <div class="container">
            <h2>Problemas que Todo Lojista Enfrenta</h2>
            <div class="problems-grid">
                <div class="problem-card">
                    <div class="problem-icon">😔</div>
                    <h3>Clientes não voltam</h3>
                    <p>Você conquista um cliente com muito esforço, mas ele compra uma vez e desaparece. Não há fidelização.</p>
                </div>
                
                <div class="problem-card">
                    <div class="problem-icon">📉</div>
                    <h3>Vendas estagnadas</h3>
                    <p>Mesmo investindo em marketing, as vendas não crescem como deveriam. Falta algo para se destacar da concorrência.</p>
                </div>
                
                <div class="problem-card">
                    <div class="problem-icon">💸</div>
                    <h3>Marketing caro</h3>
                    <p>Campanhas de Facebook e Google custam caro e nem sempre trazem o retorno esperado. Você precisa de uma forma mais eficiente.</p>
                </div>
            </div>
            
            <!-- Transição para solução -->
            <div class="solution-intro">
                <h3>E se existisse uma forma de resolver todos esses problemas de uma vez?</h3>
                <p>Com o sistema de cashback do Klube Cash, você transforma cada venda em uma oportunidade de fidelização automática.</p>
            </div>
        </div>
    </section>

    <!-- Como funciona para lojas (explicação técnica mas simples) -->
    <section class="how-it-works-business" id="como-funciona-loja">
        <div class="container">
            <h2>Como o Klube Cash Revoluciona sua Loja</h2>
            <div class="business-flow">
                
                <!-- Passo 1 -->
                <div class="flow-step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3>Cliente Compra na Sua Loja</h3>
                        <p>Seu cliente faz uma compra normalmente, seja online ou física. O processo é exatamente o mesmo de sempre.</p>
                        <div class="step-visual">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                </div>

                <!-- Passo 2 -->
                <div class="flow-step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3>Sistema Registra Automaticamente</h3>
                        <p>Nosso sistema identifica a compra e calcula automaticamente o cashback que o cliente vai receber (você define a porcentagem).</p>
                        <div class="step-visual">
                            <i class="fas fa-calculator"></i>
                        </div>
                    </div>
                </div>

                <!-- Passo 3 -->
                <div class="flow-step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h3>Cliente Recebe Cashback</h3>
                        <p>O cliente recebe uma notificação de que ganhou dinheiro de volta. Esse dinheiro fica disponível para usar na próxima compra na SUA loja.</p>
                        <div class="step-visual">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                </div>

                <!-- Passo 4 -->
                <div class="flow-step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h3>Cliente Volta para Usar o Cashback</h3>
                        <p>Como o cashback só pode ser usado na sua loja, o cliente tem um incentivo poderoso para voltar e comprar novamente.</p>
                        <div class="step-visual">
                            <i class="fas fa-repeat"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Resultado final -->
            <div class="result-highlight">
                <h3>Resultado: Ciclo de Fidelização Automático</h3>
                <p>Cada venda gera uma nova venda futura. Seus clientes se tornam fiéis naturalmente porque têm um incentivo real para voltar.</p>
            </div>
        </div>
    </section>

    <!-- Benefícios específicos para negócios -->
    <section class="business-benefits">
        <div class="container">
            <h2>Benefícios Comprovados para Sua Loja</h2>
            <div class="benefits-showcase">
                
                <div class="benefit-large">
                    <div class="benefit-stat">+<?php echo $crescimentoVendas; ?>%</div>
                    <h3>Aumento nas Vendas</h3>
                    <p>Lojas parceiras registram em média 38% de aumento nas vendas nos primeiros 6 meses usando o sistema.</p>
                </div>

                <div class="benefit-large">
                    <div class="benefit-stat"><?php echo $clientesFidelizados; ?>%</div>
                    <h3>Taxa de Retorno</h3>
                    <p>92% dos clientes que recebem cashback voltam para fazer pelo menos mais uma compra na mesma loja.</p>
                </div>

                <div class="benefit-large">
                    <div class="benefit-stat">R$ 0</div>
                    <h3>Custo para Aderir</h3>
                    <p>Sistema completamente gratuito. Você só "paga" o cashback quando realmente vende, e ainda assim aumenta o lucro.</p>
                </div>
            </div>

            <!-- Lista de benefícios específicos -->
            <div class="detailed-benefits">
                <div class="benefit-column">
                    <h4>💰 Benefícios Financeiros</h4>
                    <ul>
                        <li>Aumento comprovado nas vendas</li>
                        <li>Maior ticket médio por cliente</li>
                        <li>Redução no custo de aquisição</li>
                        <li>ROI positivo desde o primeiro mês</li>
                    </ul>
                </div>

                <div class="benefit-column">
                    <h4>👥 Benefícios para Clientes</h4>
                    <ul>
                        <li>Fidelização automática e natural</li>
                        <li>Maior satisfação do cliente</li>
                        <li>Indicações espontâneas</li>
                        <li>Relacionamento de longo prazo</li>
                    </ul>
                </div>

                <div class="benefit-column">
                    <h4>⚙️ Benefícios Operacionais</h4>
                    <ul>
                        <li>Implementação em 5 minutos</li>
                        <li>Sistema totalmente automatizado</li>
                        <li>Relatórios detalhados</li>
                        <li>Suporte técnico incluído</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Casos de sucesso reais -->
    <section class="success-cases">
        <div class="container">
            <h2>Cases de Sucesso de Lojas Parceiras</h2>
            <div class="cases-grid">
                
                <div class="case-card">
                    <div class="case-header">
                        <img src="../../assets/images/case-moda.jpg" alt="Loja de moda" width="100" height="60">
                        <div class="case-info">
                            <h4>Loja de Moda Feminina</h4>
                            <span>São Paulo - SP</span>
                        </div>
                    </div>
                    <div class="case-results">
                        <div class="result-item">
                            <span class="number">+45%</span>
                            <span class="label">Vendas</span>
                        </div>
                        <div class="result-item">
                            <span class="number">89%</span>
                            <span class="label">Retorno</span>
                        </div>
                    </div>
                    <blockquote>"O cashback mudou completamente meu negócio. Minhas clientes agora voltam sempre para usar o dinheiro que ganharam."</blockquote>
                </div>

                <div class="case-card">
                    <div class="case-header">
                        <img src="../../assets/images/case-restaurante.jpg" alt="Restaurante" width="100" height="60">
                        <div class="case-info">
                            <h4>Restaurante Família</h4>
                            <span>Rio de Janeiro - RJ</span>
                        </div>
                    </div>
                    <div class="case-results">
                        <div class="result-item">
                            <span class="number">+32%</span>
                            <span class="label">Frequência</span>
                        </div>
                        <div class="result-item">
                            <span class="number">R$ 1.200</span>
                            <span class="label">Ticket médio</span>
                        </div>
                    </div>
                    <blockquote>"Nossos clientes frequentam mais o restaurante porque sabem que estão ganhando dinheiro para a próxima refeição."</blockquote>
                </div>

                <div class="case-card">
                    <div class="case-header">
                        <img src="../../assets/images/case-farmacia.jpg" alt="Farmácia" width="100" height="60">
                        <div class="case-info">
                            <h4>Farmácia Central</h4>
                            <span>Belo Horizonte - MG</span>
                        </div>
                    </div>
                    <div class="case-results">
                        <div class="result-item">
                            <span class="number">+28%</span>
                            <span class="label">Faturamento</span>
                        </div>
                        <div class="result-item">
                            <span class="number">94%</span>
                            <span class="label">Satisfação</span>
                        </div>
                    </div>
                    <blockquote>"O sistema é muito simples de usar. Os clientes ficam felizes e nós vendemos mais. Todo mundo sai ganhando."</blockquote>
                </div>
            </div>
        </div>
    </section>

    <!-- Preços e planos (transparência total) -->
    <section class="pricing-section">
        <div class="container">
            <h2>Investimento para Sua Loja</h2>
            <div class="pricing-explanation">
                <h3>Como funciona o modelo de negócio:</h3>
                <p>O Klube Cash é completamente gratuito para sua loja. Você define qual porcentagem de cashback quer oferecer (recomendamos entre 3% e 8%), e esse valor é descontado apenas quando há venda confirmada.</p>
            </div>

            <div class="pricing-example">
                <h4>Exemplo Prático:</h4>
                <div class="example-calculation">
                    <div class="calc-step">
                        <span class="calc-label">Venda:</span>
                        <span class="calc-value">R$ 100,00</span>
                    </div>
                    <div class="calc-step">
                        <span class="calc-label">Cashback (5%):</span>
                        <span class="calc-value">R$ 5,00</span>
                    </div>
                    <div class="calc-step">
                        <span class="calc-label">Você recebe:</span>
                        <span class="calc-value">R$ 95,00</span>
                    </div>
                    <div class="calc-step highlight">
                        <span class="calc-label">Cliente volta e gasta:</span>
                        <span class="calc-value">R$ 120,00</span>
                    </div>
                    <div class="calc-result">
                        <strong>Resultado: R$ 220,00 em vendas vs. R$ 100,00 que teria sem o cashback</strong>
                    </div>
                </div>
            </div>

            <div class="pricing-benefits">
                <h4>Incluso no plano gratuito:</h4>
                <div class="included-features">
                    <div class="feature">✅ Dashboard completo</div>
                    <div class="feature">✅ Relatórios detalhados</div>
                    <div class="feature">✅ Suporte técnico</div>
                    <div class="feature">✅ Integração fácil</div>
                    <div class="feature">✅ App móvel</div>
                    <div class="feature">✅ Sem limites de transações</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Formulário de cadastro integrado -->
    <section class="signup-section" id="cadastro">
        <div class="container">
            <div class="signup-content">
                <div class="signup-info">
                    <h2>Cadastre Sua Loja Agora</h2>
                    <p>Processo simples e rápido. Sua loja pode estar operando com cashback em menos de 24 horas.</p>
                    
                    <div class="signup-steps">
                        <div class="signup-step">
                            <span class="step-number">1</span>
                            <span>Preencha o formulário</span>
                        </div>
                        <div class="signup-step">
                            <span class="step-number">2</span>
                            <span>Nossa equipe entra em contato</span>
                        </div>
                        <div class="signup-step">
                            <span class="step-number">3</span>
                            <span>Ativação em até 24h</span>
                        </div>
                    </div>
                </div>

                <div class="signup-form">
                    <form class="store-registration-form" action="/store/register-lead/" method="POST">
                        <div class="form-group">
                            <label for="nome_loja">Nome da Loja*</label>
                            <input type="text" id="nome_loja" name="nome_loja" required placeholder="Digite o nome da sua loja">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="nome_responsavel">Seu Nome*</label>
                                <input type="text" id="nome_responsavel" name="nome_responsavel" required placeholder="Seu nome completo">
                            </div>
                            <div class="form-group">
                                <label for="telefone">Telefone*</label>
                                <input type="tel" id="telefone" name="telefone" required placeholder="(11) 99999-9999">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">Email*</label>
                            <input type="email" id="email" name="email" required placeholder="seu@email.com">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="categoria">Categoria da Loja*</label>
                                <select id="categoria" name="categoria" required>
                                    <option value="">Selecione...</option>
                                    <option value="moda">Moda e Vestuário</option>
                                    <option value="alimentacao">Alimentação</option>
                                    <option value="saude">Saúde e Beleza</option>
                                    <option value="tecnologia">Tecnologia</option>
                                    <option value="casa">Casa e Decoração</option>
                                    <option value="servicos">Serviços</option>
                                    <option value="outros">Outros</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="faturamento">Faturamento Mensal</label>
                                <select id="faturamento" name="faturamento">
                                    <option value="">Selecione...</option>
                                    <option value="ate_10k">Até R$ 10.000</option>
                                    <option value="10k_50k">R$ 10.000 - R$ 50.000</option>
                                    <option value="50k_100k">R$ 50.000 - R$ 100.000</option>
                                    <option value="100k_mais">Acima de R$ 100.000</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="objetivo">Principal Objetivo</label>
                            <select id="objetivo" name="objetivo">
                                <option value="">O que mais te interessa?</option>
                                <option value="aumentar_vendas">Aumentar vendas</option>
                                <option value="fidelizar_clientes">Fidelizar clientes</option>
                                <option value="competir_mercado">Competir melhor no mercado</option>
                                <option value="reduzir_custos">Reduzir custos de marketing</option>
                            </select>
                        </div>

                        <div class="form-group checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="aceita_termos" required>
                                <span class="checkbox-custom"></span>
                                Aceito os <a href="/termos/" target="_blank">termos de uso</a> e <a href="/privacidade/" target="_blank">política de privacidade</a>
                            </label>
                        </div>

                        <button type="submit" class="btn-submit-large">
                            <i class="fas fa-rocket"></i>
                            Quero Aumentar Minhas Vendas
                        </button>

                        <div class="form-security">
                            <i class="fas fa-shield-alt"></i>
                            <span>Seus dados estão seguros e protegidos</span>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ específico para lojistas -->
    <section class="faq-business">
        <div class="container">
            <h2>Perguntas Frequentes de Lojistas</h2>
            <div class="faq-grid">
                <div class="faq-item">
                    <h3>Como faço para começar?</h3>
                    <p>Basta preencher o formulário de cadastro. Nossa equipe entra em contato em até 2 horas para configurar tudo para você.</p>
                </div>

                <div class="faq-item">
                    <h3>Preciso mudar meu sistema atual?</h3>
                    <p>Não! Nosso sistema se integra com qualquer plataforma existente. Você continua vendendo normalmente.</p>
                </div>

                <div class="faq-item">
                    <h3>Como defino a porcentagem de cashback?</h3>
                    <p>Você tem total controle. Recomendamos entre 3% e 8%, mas pode ajustar conforme sua margem e estratégia.</p>
                </div>

                <div class="faq-item">
                    <h3>Quando pago o cashback?</h3>
                    <p>Apenas quando o cliente usar o cashback em uma nova compra. É um investimento em fidelização com retorno garantido.</p>
                </div>

                <div class="faq-item">
                    <h3>Posso cancelar a qualquer momento?</h3>
                    <p>Sim, não há fidelidade ou multas. Você pode pausar ou cancelar o serviço quando quiser.</p>
                </div>

                <div class="faq-item">
                    <h3>Como acompanho os resultados?</h3>
                    <p>Dashboard completo com relatórios em tempo real: vendas, cashback pago, taxa de retorno e muito mais.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer simplificado para landing -->
    <footer class="landing-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-main">
                    <img src="../../assets/images/logo.webp" alt="Klube Cash" width="120" height="32">
                    <p>Transformando lojas em marcas preferidas através do cashback inteligente.</p>
                </div>
                <div class="footer-contact">
                    <h4>Fale Conosco</h4>
                    <p>📞 (11) 99999-9999</p>
                    <p>✉️ lojas@klubecash.com</p>
                    <p>💬 Chat online 24/7</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Klube Cash. Sistema de cashback líder no Brasil.</p>
            </div>
        </div>
    </footer>

    <!-- Scripts otimizados para conversão -->
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/landing.js"></script>
    
    <!-- Tracking específico para B2B -->
    <script>
        // Google Analytics com eventos customizados para B2B
        gtag('event', 'view_landing_lojas', {
            'event_category': 'b2b',
            'event_label': 'cashback_para_lojas'
        });

        // Tracking de scroll depth para otimização
        let scrollPercentage = 0;
        window.addEventListener('scroll', function() {
            const scrollTop = window.pageYOffset;
            const docHeight = document.documentElement.scrollHeight - window.innerHeight;
            const currentScrollPercentage = Math.floor((scrollTop / docHeight) * 100);
            
            // Enviar evento a cada 25% de scroll
            if (currentScrollPercentage >= scrollPercentage + 25) {
                scrollPercentage = currentScrollPercentage;
                gtag('event', 'scroll_depth', {
                    'event_category': 'engagement',
                    'event_label': scrollPercentage + '%'
                });
            }
        });

        // Tracking de formulário
        document.querySelector('.store-registration-form').addEventListener('submit', function() {
            gtag('event', 'form_submit_loja', {
                'event_category': 'conversion',
                'event_label': 'cadastro_loja_interesse'
            });
        });
    </script>
</body>
</html>