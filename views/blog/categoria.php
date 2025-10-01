<?php
// views/blog/categoria.php
// Página de categoria otimizada para SEO e experiência do usuário

require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../models/BlogPost.php';

// Capturar slug da categoria da URL
$categorySlug = $_GET['slug'] ?? '';

if (empty($categorySlug)) {
    header("HTTP/1.0 404 Not Found");
    include '../../views/errors/404.php';
    exit;
}

try {
    $blogModel = new BlogPost();
    
    // Buscar informações da categoria
    $category = $blogModel->getCategoryBySlug($categorySlug);
    
    if (!$category) {
        header("HTTP/1.0 404 Not Found");
        include '../../views/errors/404.php';
        exit;
    }
    
    // Configurar paginação
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $postsPerPage = 12;
    $offset = ($page - 1) * $postsPerPage;
    
    // Buscar posts da categoria
    $posts = $blogModel->getPostsByCategory($category['id'], $postsPerPage, $offset);
    $totalPosts = $blogModel->getTotalPostsByCategory($category['id']);
    $totalPages = ceil($totalPosts / $postsPerPage);
    
    // Posts em destaque da categoria
    $featuredPosts = $blogModel->getFeaturedPostsByCategory($category['id'], 3);
    
    // Subcategorias relacionadas (se houver)
    $relatedCategories = $blogModel->getRelatedCategories($category['id']);
    
} catch (Exception $e) {
    error_log('Erro ao carregar categoria: ' . $e->getMessage());
    header("HTTP/1.0 500 Internal Server Error");
    include '../../views/errors/500.php';
    exit;
}

// Configurações SEO dinâmicas baseadas na categoria
$categoryDescriptions = [
    'economia' => 'Aprenda as melhores estratégias para economizar dinheiro no dia a dia. Dicas práticas, métodos comprovados e técnicas simples para reduzir gastos e aumentar sua poupança.',
    'cashback' => 'Tudo sobre cashback: como funciona, melhores práticas, dicas para maximizar ganhos e estratégias inteligentes para usar cashback a seu favor.',
    'financas' => 'Educação financeira completa: planejamento, investimentos, controle de gastos e dicas para conquistar sua independência financeira.',
    'dicas' => 'Dicas práticas e acionáveis para melhorar sua vida financeira. Conselhos testados e aprovados para economizar mais e gastar melhor.'
];

$pageTitle = $category['seo_title'] ?: "Artigos sobre " . $category['name'] . " - Blog Klube Cash";
$pageDescription = $category['description'] ?: ($categoryDescriptions[$categorySlug] ?? "Conheça nossos artigos sobre " . $category['name'] . " e aprenda a economizar dinheiro de forma inteligente.");
$pageKeywords = $category['keywords'] ?: $category['name'] . ", dicas " . strtolower($category['name']) . ", como " . strtolower($category['name']);
$pageUrl = "https://klubecash.com/categoria/" . $category['slug'] . "/";
$pageImage = $category['featured_image'] ?: "https://klubecash.com/assets/images/seo/categoria-" . $categorySlug . "-og.jpg";
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Meta Tags SEO otimizadas para categoria -->
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($pageKeywords); ?>">
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
    
    <!-- Open Graph para categorias -->
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta property="og:image" content="<?php echo $pageImage; ?>">
    <meta property="og:url" content="<?php echo $pageUrl; ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Klube Cash">
    
    <!-- Schema.org para página de categoria -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "CollectionPage",
        "name": "<?php echo htmlspecialchars($category['name']); ?>",
        "description": "<?php echo htmlspecialchars($pageDescription); ?>",
        "url": "<?php echo $pageUrl; ?>",
        "mainEntity": {
            "@type": "ItemList",
            "numberOfItems": <?php echo $totalPosts; ?>,
            "itemListElement": [
                <?php foreach ($posts as $index => $post): ?>
                {
                    "@type": "ListItem",
                    "position": <?php echo $index + 1; ?>,
                    "item": {
                        "@type": "BlogPosting",
                        "headline": "<?php echo htmlspecialchars($post['title']); ?>",
                        "url": "https://klubecash.com/blog/<?php echo $post['slug']; ?>/"
                    }
                }<?php echo ($index < count($posts) - 1) ? ',' : ''; ?>
                <?php endforeach; ?>
            ]
        },
        "breadcrumb": {
            "@type": "BreadcrumbList",
            "itemListElement": [
                {
                    "@type": "ListItem",
                    "position": 1,
                    "name": "Início",
                    "item": "https://klubecash.com/"
                },
                {
                    "@type": "ListItem",
                    "position": 2,
                    "name": "Blog",
                    "item": "https://klubecash.com/blog/"
                },
                {
                    "@type": "ListItem",
                    "position": 3,
                    "name": "<?php echo htmlspecialchars($category['name']); ?>"
                }
            ]
        }
    }
    </script>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/blog.css">
    <link rel="stylesheet" href="../../assets/css/category.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <!-- Header -->
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

    <!-- Breadcrumbs estruturados -->
    <nav class="breadcrumbs">
        <div class="container">
            <ol itemscope itemtype="https://schema.org/BreadcrumbList">
                <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                    <a itemprop="item" href="/"><span itemprop="name">Início</span></a>
                    <meta itemprop="position" content="1" />
                </li>
                <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                    <a itemprop="item" href="/blog/"><span itemprop="name">Blog</span></a>
                    <meta itemprop="position" content="2" />
                </li>
                <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                    <span itemprop="name"><?php echo htmlspecialchars($category['name']); ?></span>
                    <meta itemprop="position" content="3" />
                </li>
            </ol>
        </div>
    </nav>

    <!-- Hero da categoria com informações contextuais -->
    <section class="category-hero">
        <div class="container">
            <div class="category-header">
                <div class="category-icon">
                    <i class="<?php echo $category['icon'] ?: 'fas fa-folder'; ?>"></i>
                </div>
                <div class="category-info">
                    <h1><?php echo htmlspecialchars($category['name']); ?></h1>
                    <p class="category-description"><?php echo htmlspecialchars($pageDescription); ?></p>
                    
                    <!-- Estatísticas da categoria -->
                    <div class="category-stats">
                        <span class="stat-item">
                            <strong><?php echo number_format($totalPosts); ?></strong> 
                            <?php echo $totalPosts == 1 ? 'artigo' : 'artigos'; ?>
                        </span>
                        <span class="stat-item">
                            <strong><?php echo $category['total_views'] ? number_format($category['total_views']) : '0'; ?></strong> 
                            visualizações
                        </span>
                        <span class="stat-item">
                            <strong>Atualizado</strong> 
                            <?php echo $category['last_updated'] ? date('d/m/Y', strtotime($category['last_updated'])) : 'recentemente'; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Navegação entre categorias -->
            <?php if (!empty($relatedCategories)): ?>
            <div class="category-navigation">
                <h3>Explore outras categorias:</h3>
                <div class="category-links">
                    <?php foreach ($relatedCategories as $relatedCategory): ?>
                    <a href="/categoria/<?php echo $relatedCategory['slug']; ?>/" class="category-link">
                        <i class="<?php echo $relatedCategory['icon'] ?: 'fas fa-folder'; ?>"></i>
                        <?php echo htmlspecialchars($relatedCategory['name']); ?>
                        <span class="post-count">(<?php echo $relatedCategory['post_count']; ?>)</span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Posts em destaque da categoria -->
    <?php if (!empty($featuredPosts)): ?>
    <section class="category-featured">
        <div class="container">
            <h2>✨ Artigos em Destaque sobre <?php echo htmlspecialchars($category['name']); ?></h2>
            <div class="featured-grid">
                <?php foreach ($featuredPosts as $post): ?>
                <article class="featured-post-card">
                    <div class="post-image">
                        <a href="/blog/<?php echo $post['slug']; ?>/">
                            <img src="<?php echo $post['featured_image']; ?>" 
                                 alt="<?php echo htmlspecialchars($post['title']); ?>" 
                                 width="400" height="250" loading="lazy">
                        </a>
                        <div class="featured-badge">Destaque</div>
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
                            <span class="view-count">
                                <i class="fas fa-eye"></i>
                                <?php echo number_format($post['views']); ?>
                            </span>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Lista principal de posts da categoria -->
    <main class="category-main">
        <div class="container">
            <div class="content-layout">
                
                <!-- Posts da categoria -->
                <section class="posts-section">
                    <div class="section-header">
                        <h2>Todos os artigos sobre <?php echo htmlspecialchars($category['name']); ?></h2>
                        <div class="sort-options">
                            <label for="sort">Ordenar por:</label>
                            <select id="sort" onchange="sortPosts(this.value)">
                                <option value="recent">Mais recentes</option>
                                <option value="popular">Mais populares</option>
                                <option value="alphabetical">A-Z</option>
                            </select>
                        </div>
                    </div>

                    <?php if (!empty($posts)): ?>
                    <div class="posts-grid" id="postsGrid">
                        <?php foreach ($posts as $post): ?>
                        <article class="post-card" itemscope itemtype="https://schema.org/BlogPosting">
                            <div class="post-image">
                                <a href="/blog/<?php echo $post['slug']; ?>/">
                                    <img itemprop="image" 
                                         src="<?php echo $post['featured_image']; ?>" 
                                         alt="<?php echo htmlspecialchars($post['title']); ?>" 
                                         width="350" height="200" loading="lazy">
                                </a>
                                
                                <!-- Badges especiais -->
                                <?php if ($post['is_trending']): ?>
                                <div class="post-badge trending">🔥 Trending</div>
                                <?php endif; ?>
                                
                                <?php if ($post['difficulty_level']): ?>
                                <div class="post-badge difficulty <?php echo $post['difficulty_level']; ?>">
                                    <?php 
                                    $levels = ['beginner' => '👶 Iniciante', 'intermediate' => '👨‍🎓 Intermediário', 'advanced' => '🧠 Avançado'];
                                    echo $levels[$post['difficulty_level']] ?? $post['difficulty_level'];
                                    ?>
                                </div>
                                <?php endif; ?>
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
                                
                                <!-- Meta informações estendidas -->
                                <div class="post-meta-extended">
                                    <div class="primary-meta">
                                        <span class="post-author" itemprop="author" itemscope itemtype="https://schema.org/Person">
                                            <img src="<?php echo $post['author_avatar'] ?: '/assets/images/default-avatar.jpg'; ?>" 
                                                 alt="<?php echo htmlspecialchars($post['author_name']); ?>" 
                                                 width="24" height="24">
                                            <span itemprop="name"><?php echo $post['author_name']; ?></span>
                                        </span>
                                        <span class="post-date">
                                            <i class="fas fa-calendar"></i>
                                            <time itemprop="datePublished" datetime="<?php echo $post['published_at']; ?>">
                                                <?php echo date('d/m/Y', strtotime($post['published_at'])); ?>
                                            </time>
                                        </span>
                                    </div>
                                    
                                    <div class="secondary-meta">
                                        <span class="read-time">
                                            <i class="fas fa-clock"></i>
                                            <?php echo $post['read_time']; ?> min
                                        </span>
                                        <span class="view-count">
                                            <i class="fas fa-eye"></i>
                                            <?php echo number_format($post['views']); ?>
                                        </span>
                                        
                                        <!-- Rating de utilidade (se disponível) -->
                                        <?php if ($post['usefulness_rating']): ?>
                                        <span class="usefulness-rating">
                                            <i class="fas fa-thumbs-up"></i>
                                            <?php echo round($post['usefulness_rating'] * 100); ?>% útil
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Tags relacionadas -->
                                <?php if (!empty($post['tags'])): ?>
                                <div class="post-tags">
                                    <?php 
                                    $tags = array_slice(explode(',', $post['tags']), 0, 3); // Máximo 3 tags
                                    foreach ($tags as $tag): 
                                        $tag = trim($tag);
                                    ?>
                                        <span class="tag"><?php echo htmlspecialchars($tag); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                
                                <a href="/blog/<?php echo $post['slug']; ?>/" class="read-more">
                                    Ler artigo completo <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>

                    <!-- Paginação avançada -->
                    <?php if ($totalPages > 1): ?>
                    <nav class="pagination" aria-label="Navegação entre páginas da categoria">
                        <div class="pagination-info">
                            Página <?php echo $page; ?> de <?php echo $totalPages; ?> 
                            (<?php echo number_format($totalPosts); ?> artigos total)
                        </div>
                        
                        <div class="pagination-controls">
                            <?php if ($page > 1): ?>
                                <a href="<?php echo $pageUrl . ($page > 2 ? '?page=' . ($page - 1) : ''); ?>" 
                                   class="pagination-link pagination-prev">
                                    <i class="fas fa-chevron-left"></i> Anterior
                                </a>
                            <?php endif; ?>

                            <div class="pagination-numbers">
                                <?php
                                // Lógica de paginação inteligente
                                $start = max(1, $page - 3);
                                $end = min($totalPages, $page + 3);
                                
                                if ($start > 1): ?>
                                    <a href="<?php echo $pageUrl; ?>" class="pagination-link">1</a>
                                    <?php if ($start > 2): ?>
                                        <span class="pagination-dots">...</span>
                                    <?php endif; ?>
                                <?php endif;
                                
                                for ($i = $start; $i <= $end; $i++): ?>
                                    <?php if ($i == $page): ?>
                                        <span class="pagination-link pagination-current"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="<?php echo $pageUrl . ($i > 1 ? '?page=' . $i : ''); ?>" 
                                           class="pagination-link"><?php echo $i; ?></a>
                                    <?php endif; ?>
                                <?php endfor;
                                
                                if ($end < $totalPages): ?>
                                    <?php if ($end < $totalPages - 1): ?>
                                        <span class="pagination-dots">...</span>
                                    <?php endif; ?>
                                    <a href="<?php echo $pageUrl . '?page=' . $totalPages; ?>" class="pagination-link"><?php echo $totalPages; ?></a>
                                <?php endif; ?>
                            </div>

                            <?php if ($page < $totalPages): ?>
                                <a href="<?php echo $pageUrl . '?page=' . ($page + 1); ?>" 
                                   class="pagination-link pagination-next">
                                    Próximo <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </nav>
                    <?php endif; ?>

                    <?php else: ?>
                    <div class="no-posts">
                        <i class="fas fa-folder-open"></i>
                        <h3>Nenhum artigo encontrado</h3>
                        <p>Esta categoria ainda não possui artigos publicados. Volte em breve para conferir o conteúdo!</p>
                        <a href="/blog/" class="btn-outline">Ver Todos os Artigos</a>
                    </div>
                    <?php endif; ?>
                </section>

                <!-- Sidebar específica da categoria -->
                <aside class="category-sidebar">
                    <!-- Resumo da categoria -->
                    <div class="sidebar-widget category-summary">
                        <h3>Sobre <?php echo htmlspecialchars($category['name']); ?></h3>
                        <p><?php echo htmlspecialchars($category['description'] ?? $pageDescription); ?></p>
                        
                        <?php if ($category['learning_path']): ?>
                        <div class="learning-path">
                            <h4>📚 Trilha de Aprendizado</h4>
                            <p>Para aproveitar melhor o conteúdo, sugerimos esta ordem de leitura:</p>
                            <ol class="learning-steps">
                                <li><a href="/blog/<?php echo $category['beginner_post_slug']; ?>/">Conceitos Básicos</a></li>
                                <li><a href="/blog/<?php echo $category['intermediate_post_slug']; ?>/">Aplicação Prática</a></li>
                                <li><a href="/blog/<?php echo $category['advanced_post_slug']; ?>/">Técnicas Avançadas</a></li>
                            </ol>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- CTA específico da categoria -->
                    <div class="sidebar-widget cta-widget category-cta">
                        <?php if ($categorySlug === 'cashback'): ?>
                            <h3>💰 Teste o Cashback na Prática</h3>
                            <p>Que tal experimentar tudo que você aprendeu sobre cashback?</p>
                            <a href="/registro/" class="btn-sidebar-cta">Começar a Ganhar Cashback</a>
                        <?php elseif ($categorySlug === 'economia'): ?>
                            <h3>🎯 Economize de Verdade</h3>
                            <p>Transforme estas dicas em economia real com cashback automático</p>
                            <a href="/registro/" class="btn-sidebar-cta">Quero Economizar Mais</a>
                        <?php else: ?>
                            <h3>🚀 Aplique o que Aprendeu</h3>
                            <p>Coloque em prática suas novas habilidades financeiras</p>
                            <a href="/registro/" class="btn-sidebar-cta">Começar Agora</a>
                        <?php endif; ?>
                    </div>

                    <!-- Newsletter temática -->
                    <div class="sidebar-widget newsletter-widget category-newsletter">
                        <h3>📧 Newsletter de <?php echo htmlspecialchars($category['name']); ?></h3>
                        <p>Receba conteúdo exclusivo sobre <?php echo strtolower($category['name']); ?> toda semana</p>
                        <form class="category-newsletter-form" action="/newsletter/subscribe/" method="POST">
                            <input type="hidden" name="category" value="<?php echo $categorySlug; ?>">
                            <input type="email" name="email" placeholder="Seu melhor email" required>
                            <button type="submit">Quero Receber</button>
                        </form>
                        <small>Conteúdo especializado + dicas exclusivas</small>
                    </div>
                </aside>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="site-footer">
        <!-- Mesmo footer das outras páginas -->
    </footer>

    <!-- Scripts específicos da categoria -->
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/category.js"></script>
    
    <script>
        // Função para ordenar posts
        function sortPosts(sortBy) {
            const grid = document.getElementById('postsGrid');
            const posts = Array.from(grid.children);
            
            posts.sort((a, b) => {
                switch(sortBy) {
                    case 'popular':
                        const viewsA = parseInt(a.querySelector('.view-count').textContent.replace(/\D/g, ''));
                        const viewsB = parseInt(b.querySelector('.view-count').textContent.replace(/\D/g, ''));
                        return viewsB - viewsA;
                    case 'alphabetical':
                        const titleA = a.querySelector('h3 a').textContent.toLowerCase();
                        const titleB = b.querySelector('h3 a').textContent.toLowerCase();
                        return titleA.localeCompare(titleB);
                    default: // recent
                        return 0; // Mantém ordem original (já ordenada por data)
                }
            });
            
            posts.forEach(post => grid.appendChild(post));
            
            // Analytics
            gtag('event', 'category_sort', {
                'event_category': 'user_interaction',
                'event_label': '<?php echo $categorySlug; ?>',
                'sort_method': sortBy
            });
        }

        // Analytics para categoria
        gtag('event', 'category_view', {
            'event_category': 'content',
            'event_label': '<?php echo $categorySlug; ?>',
            'page_number': <?php echo $page; ?>,
            'total_posts': <?php echo $totalPosts; ?>
        });
    </script>
</body>
</html>