<?php
// views/blog/post.php
// Template para posts individuais otimizado para SEO

require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../models/BlogPost.php';

// Capturar slug do post da URL
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    header("HTTP/1.0 404 Not Found");
    include '../../views/errors/404.php';
    exit;
}

try {
    $blogModel = new BlogPost();
    $post = $blogModel->getPostBySlug($slug);
    
    if (!$post) {
        header("HTTP/1.0 404 Not Found");
        include '../../views/errors/404.php';
        exit;
    }
    
    // Incrementar visualizações
    $blogModel->incrementViews($post['id']);
    
    // Posts relacionados
    $relatedPosts = $blogModel->getRelatedPosts($post['id'], $post['category_id'], 3);
    
    // Próximo e anterior posts
    $prevPost = $blogModel->getPreviousPost($post['id']);
    $nextPost = $blogModel->getNextPost($post['id']);
    
} catch (Exception $e) {
    error_log('Erro ao carregar post: ' . $e->getMessage());
    header("HTTP/1.0 500 Internal Server Error");
    include '../../views/errors/500.php';
    exit;
}

// Configurações SEO específicas do post
$pageTitle = $post['seo_title'] ?: $post['title'] . " - Blog Klube Cash";
$pageDescription = $post['meta_description'] ?: $post['excerpt'];
$pageKeywords = $post['keywords'] ?: '';
$pageUrl = "https://klubecash.com/blog/" . $post['slug'] . "/";
$pageImage = $post['featured_image'] ?: "https://klubecash.com/assets/images/seo/blog-og.jpg";
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Meta Tags SEO otimizadas para artigo -->
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <?php if ($pageKeywords): ?>
    <meta name="keywords" content="<?php echo htmlspecialchars($pageKeywords); ?>">
    <?php endif; ?>
    <meta name="author" content="<?php echo htmlspecialchars($post['author_name']); ?>">
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large">
    <meta name="article:published_time" content="<?php echo $post['published_at']; ?>">
    <meta name="article:modified_time" content="<?php echo $post['updated_at']; ?>">
    
    <!-- URLs Canônicas -->
    <link rel="canonical" href="<?php echo $pageUrl; ?>">
    
    <!-- Open Graph otimizado para artigos -->
    <meta property="og:title" content="<?php echo htmlspecialchars($post['title']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta property="og:image" content="<?php echo $pageImage; ?>">
    <meta property="og:url" content="<?php echo $pageUrl; ?>">
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="Klube Cash">
    <meta property="article:author" content="<?php echo htmlspecialchars($post['author_name']); ?>">
    <meta property="article:published_time" content="<?php echo $post['published_at']; ?>">
    <meta property="article:section" content="<?php echo $post['category_name']; ?>">
    
    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($post['title']); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta name="twitter:image" content="<?php echo $pageImage; ?>">
    
    <!-- Schema.org JSON-LD para artigo -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BlogPosting",
        "headline": "<?php echo htmlspecialchars($post['title']); ?>",
        "description": "<?php echo htmlspecialchars($pageDescription); ?>",
        "image": "<?php echo $pageImage; ?>",
        "author": {
            "@type": "Person",
            "name": "<?php echo htmlspecialchars($post['author_name']); ?>"
        },
        "publisher": {
            "@type": "Organization",
            "name": "Klube Cash",
            "logo": {
                "@type": "ImageObject",
                "url": "https://klubecash.com/assets/images/logo.png"
            }
        },
        "datePublished": "<?php echo $post['published_at']; ?>",
        "dateModified": "<?php echo $post['updated_at']; ?>",
        "mainEntityOfPage": {
            "@type": "WebPage",
            "@id": "<?php echo $pageUrl; ?>"
        },
        "wordCount": "<?php echo str_word_count(strip_tags($post['content'])); ?>",
        "articleBody": "<?php echo htmlspecialchars(strip_tags($post['content'])); ?>"
    }
    </script>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/blog.css">
    <link rel="stylesheet" href="../../assets/css/article.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/themes/prism.min.css">
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
                    <a itemprop="item" href="/categoria/<?php echo $post['category_slug']; ?>/"><span itemprop="name"><?php echo $post['category_name']; ?></span></a>
                    <meta itemprop="position" content="3" />
                </li>
                <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                    <span itemprop="name"><?php echo htmlspecialchars($post['title']); ?></span>
                    <meta itemprop="position" content="4" />
                </li>
            </ol>
        </div>
    </nav>

    <!-- Artigo principal -->
    <main class="article-main">
        <div class="container">
            <article class="article-content" itemscope itemtype="https://schema.org/BlogPosting">
                
                <!-- Header do artigo -->
                <header class="article-header">
                    <div class="article-category">
                        <a href="/categoria/<?php echo $post['category_slug']; ?>/">
                            <?php echo $post['category_name']; ?>
                        </a>
                    </div>
                    
                    <h1 itemprop="headline"><?php echo htmlspecialchars($post['title']); ?></h1>
                    
                    <div class="article-meta">
                        <div class="author-info" itemprop="author" itemscope itemtype="https://schema.org/Person">
                            <img src="<?php echo $post['author_avatar'] ?: '/assets/images/default-avatar.jpg'; ?>" 
                                 alt="<?php echo htmlspecialchars($post['author_name']); ?>" 
                                 width="40" height="40" class="author-avatar">
                            <div class="author-details">
                                <span class="author-name" itemprop="name"><?php echo htmlspecialchars($post['author_name']); ?></span>
                                <span class="author-title"><?php echo htmlspecialchars($post['author_title'] ?: 'Especialista em Finanças'); ?></span>
                            </div>
                        </div>
                        
                        <div class="article-stats">
                            <span class="publish-date">
                                <i class="fas fa-calendar"></i>
                                <time itemprop="datePublished" datetime="<?php echo $post['published_at']; ?>">
                                    <?php echo date('d/m/Y', strtotime($post['published_at'])); ?>
                                </time>
                            </span>
                            <span class="read-time">
                                <i class="fas fa-clock"></i>
                                <?php echo $post['read_time']; ?> min de leitura
                            </span>
                            <span class="view-count">
                                <i class="fas fa-eye"></i>
                                <?php echo number_format($post['views']); ?> visualizações
                            </span>
                        </div>
                    </div>
                    
                    <!-- Botões de compartilhamento -->
                    <div class="share-buttons">
                        <span class="share-label">Compartilhar:</span>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($pageUrl); ?>" 
                           class="share-btn facebook" target="_blank" rel="noopener">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($pageUrl); ?>&text=<?php echo urlencode($post['title']); ?>" 
                           class="share-btn twitter" target="_blank" rel="noopener">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="https://api.whatsapp.com/send?text=<?php echo urlencode($post['title'] . ' ' . $pageUrl); ?>" 
                           class="share-btn whatsapp" target="_blank" rel="noopener">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                        <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode($pageUrl); ?>" 
                           class="share-btn linkedin" target="_blank" rel="noopener">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                    </div>
                </header>

                <!-- Imagem destacada -->
                <?php if ($post['featured_image']): ?>
                <div class="article-featured-image">
                    <img itemprop="image" 
                         src="<?php echo $post['featured_image']; ?>" 
                         alt="<?php echo htmlspecialchars($post['image_alt'] ?: $post['title']); ?>" 
                         width="800" height="450">
                    <?php if ($post['image_caption']): ?>
                    <figcaption class="image-caption">
                        <?php echo htmlspecialchars($post['image_caption']); ?>
                    </figcaption>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Conteúdo do artigo -->
                <div class="article-body" itemprop="articleBody">
                    <?php echo $post['content']; ?>
                </div>

                <!-- Tags do artigo -->
                <?php if (!empty($post['tags'])): ?>
                <div class="article-tags">
                    <strong>Tags:</strong>
                    <?php 
                    $tags = explode(',', $post['tags']);
                    foreach ($tags as $tag): 
                        $tag = trim($tag);
                    ?>
                        <a href="/tag/<?php echo urlencode(strtolower($tag)); ?>/" class="tag-link">
                            #<?php echo htmlspecialchars($tag); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Data de atualização -->
                <div class="article-updated">
                    <small>
                        <i class="fas fa-sync"></i>
                        Última atualização: 
                        <time itemprop="dateModified" datetime="<?php echo $post['updated_at']; ?>">
                            <?php echo date('d/m/Y H:i', strtotime($post['updated_at'])); ?>
                        </time>
                    </small>
                </div>
            </article>

            <!-- Seção de autor -->
            <section class="author-bio">
                <div class="author-bio-content">
                    <img src="<?php echo $post['author_avatar'] ?: '/assets/images/default-avatar.jpg'; ?>" 
                         alt="<?php echo htmlspecialchars($post['author_name']); ?>" 
                         width="80" height="80" class="author-bio-avatar">
                    <div class="author-bio-text">
                        <h3>Sobre <?php echo htmlspecialchars($post['author_name']); ?></h3>
                        <p><?php echo htmlspecialchars($post['author_bio'] ?: 'Especialista em educação financeira e economia doméstica, apaixonado por ajudar pessoas a conquistarem sua independência financeira.'); ?></p>
                        <?php if ($post['author_social']): ?>
                        <div class="author-social">
                            <!-- Links sociais do autor podem ser implementados aqui -->
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- Navegação entre posts -->
            <?php if ($prevPost || $nextPost): ?>
            <nav class="post-navigation">
                <?php if ($prevPost): ?>
                <div class="nav-previous">
                    <a href="/blog/<?php echo $prevPost['slug']; ?>/">
                        <span class="nav-label">← Artigo Anterior</span>
                        <span class="nav-title"><?php echo htmlspecialchars($prevPost['title']); ?></span>
                    </a>
                </div>
                <?php endif; ?>

                <?php if ($nextPost): ?>
                <div class="nav-next">
                    <a href="/blog/<?php echo $nextPost['slug']; ?>/">
                        <span class="nav-label">Próximo Artigo →</span>
                        <span class="nav-title"><?php echo htmlspecialchars($nextPost['title']); ?></span>
                    </a>
                </div>
                <?php endif; ?>
            </nav>
            <?php endif; ?>

            <!-- Posts relacionados -->
            <?php if (!empty($relatedPosts)): ?>
            <section class="related-posts">
                <h3>Artigos Relacionados</h3>
                <div class="related-posts-grid">
                    <?php foreach ($relatedPosts as $relatedPost): ?>
                    <article class="related-post-card">
                        <a href="/blog/<?php echo $relatedPost['slug']; ?>/">
                            <img src="<?php echo $relatedPost['featured_image']; ?>" 
                                 alt="<?php echo htmlspecialchars($relatedPost['title']); ?>" 
                                 width="250" height="150" loading="lazy">
                            <div class="related-post-content">
                                <h4><?php echo htmlspecialchars($relatedPost['title']); ?></h4>
                                <span class="related-post-date">
                                    <?php echo date('d/m/Y', strtotime($relatedPost['published_at'])); ?>
                                </span>
                            </div>
                        </a>
                    </article>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- CTA para cadastro -->
            <section class="article-cta">
                <div class="cta-content">
                    <h3>💰 Gostou das dicas? Comece a economizar agora!</h3>
                    <p>Cadastre-se no Klube Cash e transforme suas compras em economia real com cashback automático.</p>
                    <a href="/registro/" class="btn-cta-large">Quero Economizar com Cashback</a>
                </div>
            </section>
        </div>
    </main>

    <!-- Footer -->
    <footer class="site-footer">
        <!-- Mesmo footer dos outros templates -->
    </footer>

    <!-- Scripts -->
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/article.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/prism.min.js"></script>
    
    <!-- Google Analytics com eventos específicos do artigo -->
    <script>
        // Tracking de leitura do artigo
        gtag('event', 'article_view', {
            'event_category': 'content',
            'event_label': '<?php echo $post['slug']; ?>',
            'article_title': '<?php echo htmlspecialchars($post['title']); ?>'
        });

        // Tracking de scroll depth para artigos
        let scrollMilestones = [25, 50, 75, 90];
        let scrollTracked = [];

        window.addEventListener('scroll', function() {
            const scrollTop = window.pageYOffset;
            const docHeight = document.documentElement.scrollHeight - window.innerHeight;
            const scrollPercent = Math.floor((scrollTop / docHeight) * 100);
            
            scrollMilestones.forEach(milestone => {
                if (scrollPercent >= milestone && !scrollTracked.includes(milestone)) {
                    scrollTracked.push(milestone);
                    gtag('event', 'article_scroll', {
                        'event_category': 'engagement',
                        'event_label': '<?php echo $post['slug']; ?>',
                        'scroll_depth': milestone
                    });
                }
            });
        });

        // Tracking de compartilhamento
        document.querySelectorAll('.share-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const platform = this.classList.contains('facebook') ? 'facebook' :
                               this.classList.contains('twitter') ? 'twitter' :
                               this.classList.contains('whatsapp') ? 'whatsapp' : 'linkedin';
                
                gtag('event', 'share', {
                    'event_category': 'social',
                    'event_label': '<?php echo $post['slug']; ?>',
                    'platform': platform
                });
            });
        });
    </script>
</body>
</html>