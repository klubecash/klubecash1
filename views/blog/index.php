<?php
// views/blog/index.php
// Página principal do blog otimizada para SEO

$pageTitle = "Blog Klube Cash - Dicas de Economia e Educação Financeira";
$pageDescription = "Aprenda a economizar dinheiro, usar cashback de forma inteligente e melhorar sua vida financeira. Dicas práticas, tutoriais e novidades do mundo do cashback.";
$pageKeywords = "blog cashback, dicas economia, educação financeira, como economizar dinheiro, finanças pessoais";
$pageUrl = "https://klubecash.com/blog/";
$pageImage = "https://klubecash.com/assets/images/seo/blog-og.jpg";

require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../models/BlogPost.php';

// Buscar posts mais recentes com paginação
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$postsPerPage = 12;
$offset = ($page - 1) * $postsPerPage;

try {
    $blogModel = new BlogPost();
    $posts = $blogModel->getPublishedPosts($postsPerPage, $offset);
    $totalPosts = $blogModel->getTotalPublishedPosts();
    $totalPages = ceil($totalPosts / $postsPerPage);
    
    // Posts em destaque para a homepage do blog
    $featuredPosts = $blogModel->getFeaturedPosts(3);
    
    // Categorias mais populares
    $categories = $blogModel->getPopularCategories();
    
} catch (Exception $e) {
    error_log('Erro ao carregar blog: ' . $e->getMessage());
    $posts = [];
    $featuredPosts = [];
    $categories = [];
    $totalPages = 1;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Meta Tags SEO otimizadas para blog -->
    <title><?php echo $pageTitle; ?></title>
    <meta name="description" content="<?php echo $pageDescription; ?>">
    <meta name="keywords" content="<?php echo $pageKeywords; ?>">
    <meta name="author" content="Klube Cash">
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large">
    
    <!-- URLs Canônicas com paginação -->
    <link rel="canonical" href="<?php echo $pageUrl . ($page > 1 ? '?page=' . $page : ''); ?>">
    <?php if ($page > 1): ?>
        <link rel="prev" href="<?php echo $pageUrl . ($page > 2 ? '?page=' . ($page - 1) : ''); ?>">
    <?php endif; ?>
    <?php if ($page < $totalPages): ?>
        <link rel="next" href="<?php echo $pageUrl . '?page=' . ($page + 1); ?>">
    <?php endif; ?>
    
    <!-- Open Graph para compartilhamento social -->
    <meta property="og:title" content="<?php echo $pageTitle; ?>">
    <meta property="og:description" content="<?php echo $pageDescription; ?>">
    <meta property="og:image" content="<?php echo $pageImage; ?>">
    <meta property="og:url" content="<?php echo $pageUrl; ?>">
    <meta property="og:type" content="blog">
    <meta property="og:site_name" content="Klube Cash">
    
    <!-- Schema.org para blog -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Blog",
        "name": "Blog Klube Cash",
        "description": "<?php echo $pageDescription; ?>",
        "url": "<?php echo $pageUrl; ?>",
        "author": {
            "@type": "Organization",
            "name": "Klube Cash"
        },
        "publisher": {
            "@type": "Organization",
            "name": "Klube Cash",
            "logo": {
                "@type": "ImageObject",
                "url": "https://klubecash.com/assets/images/logo.png"
            }
        }
    }
    </script>
    
    <!-- RSS Feed para o blog -->
    <link rel="alternate" type="application/rss+xml" title="Blog Klube Cash RSS" href="/blog/feed.xml">
    
    <!-- CSS -->
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/blog.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <!-- Header do blog -->
    <header class="blog-header">
        <nav class="navbar">
            <div class="container">
                <a href="/" class="logo">
                    <img src="../../assets/images/logo.webp" alt="Klube Cash" width="150" height="40">
                </a>
                <ul class="nav-menu">
                    <li><a href="/">Início</a></li>
                    <li><a href="/como-funciona/">Como Funciona</a></li>
                    <li><a href="/vantagens-cashback/">Vantagens</a></li>
                    <li><a href="/lojas-parceiras/">Lojas</a></li>
                    <li><a href="/blog/" class="active">Blog</a></li>
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
                    <span itemprop="name">Blog</span>
                    <meta itemprop="position" content="2" />
                </li>
            </ol>
        </div>
    </nav>

    <!-- Hero section do blog -->
    <section class="blog-hero">
        <div class="container">
            <div class="hero-content">
                <h1>Blog Klube Cash</h1>
                <p class="hero-subtitle">Aprenda a economizar dinheiro, usar cashback de forma inteligente e conquistar sua independência financeira. Conteúdo gratuito e atualizado semanalmente.</p>
                
                <!-- Newsletter signup integrado -->
                <div class="newsletter-signup">
                    <h3>📧 Receba dicas de economia por email</h3>
                    <form class="newsletter-form" action="/newsletter/subscribe/" method="POST">
                        <input type="email" name="email" placeholder="Seu melhor email" required>
                        <button type="submit" class="btn-newsletter">Quero Receber Dicas</button>
                    </form>
                    <small>Enviamos apenas conteúdo de qualidade. Sem spam.</small>
                </div>
            </div>
        </div>
    </section>

    <!-- Posts em destaque -->
    <?php if (!empty($featuredPosts)): ?>
    <section class="featured-posts">
        <div class="container">
            <h2>📌 Posts em Destaque</h2>
            <div class="featured-grid">
                <?php foreach ($featuredPosts as $post): ?>
                <article class="featured-post-card">
                    <div class="post-image">
                        <a href="/blog/<?php echo $post['slug']; ?>/">
                            <img src="<?php echo $post['featured_image']; ?>" 
                                 alt="<?php echo htmlspecialchars($post['title']); ?>" 
                                 width="400" height="250" loading="lazy">
                        </a>
                        <div class="post-category">
                            <a href="/categoria/<?php echo $post['category_slug']; ?>/">
                                <?php echo $post['category_name']; ?>
                            </a>
                        </div>
                    </div>
                    <div class="post-content">
                        <h3><a href="/blog/<?php echo $post['slug']; ?>/"><?php echo htmlspecialchars($post['title']); ?></a></h3>
                        <p class="post-excerpt"><?php echo htmlspecialchars($post['excerpt']); ?></p>
                        <div class="post-meta">
                            <span class="post-date">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('d/m/Y', strtotime($post['published_at'])); ?>
                            </span>
                            <span class="read-time">
                                <i class="fas fa-clock"></i>
                                <?php echo $post['read_time']; ?> min
                            </span>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Seção principal com posts e sidebar -->
    <main class="blog-main">
        <div class="container">
            <div class="blog-layout">
                
                <!-- Lista de posts -->
                <section class="posts-section">
                    <div class="section-header">
                        <h2>Últimos Artigos</h2>
                        <p>Conteúdo atualizado para te ajudar a economizar mais e viver melhor</p>
                    </div>

                    <?php if (!empty($posts)): ?>
                    <div class="posts-grid">
                        <?php foreach ($posts as $post): ?>
                        <article class="post-card" itemscope itemtype="https://schema.org/BlogPosting">
                            <div class="post-image">
                                <a href="/blog/<?php echo $post['slug']; ?>/">
                                    <img itemprop="image" 
                                         src="<?php echo $post['featured_image']; ?>" 
                                         alt="<?php echo htmlspecialchars($post['title']); ?>" 
                                         width="350" height="200" loading="lazy">
                                </a>
                                <div class="post-category">
                                    <a href="/categoria/<?php echo $post['category_slug']; ?>/">
                                        <?php echo $post['category_name']; ?>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="post-content">
                                <h3 itemprop="headline">
                                    <a href="/blog/<?php echo $post['slug']; ?>/" itemprop="url">
                                        <?php echo htmlspecialchars($post['title']); ?>
                                    </a>
                                </h3>
                                
                                <p class="post-excerpt" itemprop="description">
                                    <?php echo htmlspecialchars($post['excerpt']); ?>
                                </p>
                                
                                <div class="post-meta">
                                    <span class="post-author" itemprop="author" itemscope itemtype="https://schema.org/Person">
                                        <i class="fas fa-user"></i>
                                        <span itemprop="name"><?php echo $post['author_name']; ?></span>
                                    </span>
                                    <span class="post-date">
                                        <i class="fas fa-calendar"></i>
                                        <time itemprop="datePublished" datetime="<?php echo $post['published_at']; ?>">
                                            <?php echo date('d/m/Y', strtotime($post['published_at'])); ?>
                                        </time>
                                    </span>
                                    <span class="read-time">
                                        <i class="fas fa-clock"></i>
                                        <?php echo $post['read_time']; ?> min
                                    </span>
                                </div>
                                
                                <a href="/blog/<?php echo $post['slug']; ?>/" class="read-more">
                                    Continuar lendo <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>

                    <!-- Paginação SEO-friendly -->
                    <?php if ($totalPages > 1): ?>
                    <nav class="pagination" aria-label="Navegação entre páginas">
                        <?php if ($page > 1): ?>
                            <a href="<?php echo $pageUrl . ($page > 2 ? '?page=' . ($page - 1) : ''); ?>" 
                               class="pagination-link pagination-prev">
                                <i class="fas fa-chevron-left"></i> Anterior
                            </a>
                        <?php endif; ?>

                        <div class="pagination-numbers">
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="pagination-link pagination-current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="<?php echo $pageUrl . ($i > 1 ? '?page=' . $i : ''); ?>" 
                                       class="pagination-link"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>

                        <?php if ($page < $totalPages): ?>
                            <a href="<?php echo $pageUrl . '?page=' . ($page + 1); ?>" 
                               class="pagination-link pagination-next">
                                Próximo <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                    <?php endif; ?>

                    <?php else: ?>
                    <div class="no-posts">
                        <i class="fas fa-edit"></i>
                        <h3>Em breve</h3>
                        <p>Estamos preparando conteúdo incrível para você. Volte em breve!</p>
                    </div>
                    <?php endif; ?>
                </section>

                <!-- Sidebar com conteúdo complementar -->
                <aside class="blog-sidebar">
                    
                    <!-- Categorias populares -->
                    <?php if (!empty($categories)): ?>
                    <div class="sidebar-widget">
                        <h3>📂 Categorias</h3>
                        <ul class="categories-list">
                            <?php foreach ($categories as $category): ?>
                            <li>
                                <a href="/categoria/<?php echo $category['slug']; ?>/">
                                    <?php echo $category['name']; ?>
                                    <span class="post-count">(<?php echo $category['post_count']; ?>)</span>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <!-- CTA para cadastro -->
                    <div class="sidebar-widget cta-widget">
                        <h3>💰 Comece a Economizar</h3>
                        <p>Cadastre-se no Klube Cash e ganhe cashback em todas suas compras</p>
                        <a href="/registro/" class="btn-sidebar-cta">Cadastrar Grátis</a>
                    </div>

                    <!-- Posts mais lidos -->
                    <div class="sidebar-widget">
                        <h3>🔥 Mais Lidos</h3>
                        <div class="popular-posts">
                            <!-- Aqui viriam os posts mais lidos - pode ser implementado depois -->
                            <div class="popular-post">
                                <a href="/blog/como-economizar-dinheiro-mes/">
                                    <h4>10 Dicas para Economizar R$ 500 por Mês</h4>
                                    <span class="view-count">15.2k visualizações</span>
                                </a>
                            </div>
                            <div class="popular-post">
                                <a href="/blog/cashback-vale-pena/">
                                    <h4>Cashback Vale a Pena? Tudo que Você Precisa Saber</h4>
                                    <span class="view-count">12.8k visualizações</span>
                                </a>
                            </div>
                            <div class="popular-post">
                                <a href="/blog/organizar-financas-pessoais/">
                                    <h4>Como Organizar suas Finanças em 7 Passos</h4>
                                    <span class="view-count">9.4k visualizações</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Newsletter sidebar -->
                    <div class="sidebar-widget newsletter-widget">
                        <h3>📨 Newsletter</h3>
                        <p>Receba dicas de economia e cashback toda semana</p>
                        <form class="sidebar-newsletter-form" action="/newsletter/subscribe/" method="POST">
                            <input type="email" name="email" placeholder="Seu email" required>
                            <button type="submit">Inscrever</button>
                        </form>
                    </div>
                </aside>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>Klube Cash</h4>
                    <p>Transformando compras em economia através do cashback inteligente.</p>
                </div>
                <div class="footer-section">
                    <h4>Blog</h4>
                    <ul>
                        <li><a href="/categoria/economia/">Economia</a></li>
                        <li><a href="/categoria/cashback/">Cashback</a></li>
                        <li><a href="/categoria/financas/">Finanças</a></li>
                        <li><a href="/categoria/dicas/">Dicas</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Plataforma</h4>
                    <ul>
                        <li><a href="/como-funciona/">Como Funciona</a></li>
                        <li><a href="/vantagens-cashback/">Vantagens</a></li>
                        <li><a href="/lojas-parceiras/">Lojas</a></li>
                        <li><a href="/registro/">Cadastrar</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Contato</h4>
                    <ul>
                        <li><a href="/contato/">Fale Conosco</a></li>
                        <li><a href="/sobre-klube-cash/">Sobre</a></li>
                        <li><a href="/blog/feed.xml">RSS</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Klube Cash. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/blog.js"></script>
    
    <!-- Google Analytics com eventos específicos do blog -->
    <script>
        // Tracking de engajamento no blog
        gtag('event', 'blog_visit', {
            'event_category': 'content',
            'event_label': 'blog_index',
            'page_number': <?php echo $page; ?>
        });

        // Tracking de newsletter
        document.querySelectorAll('.newsletter-form, .sidebar-newsletter-form').forEach(form => {
            form.addEventListener('submit', function() {
                gtag('event', 'newsletter_signup', {
                    'event_category': 'conversion',
                    'event_label': 'blog_newsletter'
                });
            });
        });
    </script>
</body>
</html>