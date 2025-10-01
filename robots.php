<?php
// robots.php - Robots.txt dinâmico para SEO otimizado
header('Content-Type: text/plain');

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$domain = $protocol . $_SERVER['HTTP_HOST'];

echo "User-agent: *\n";
echo "Allow: /\n\n";

// Bloquear áreas privadas do Google
echo "# Áreas privadas - não indexar\n";
echo "Disallow: /cliente/\n";
echo "Disallow: /admin/\n";
echo "Disallow: /store/\n";
echo "Disallow: /api/\n";
echo "Disallow: /controllers/\n";
echo "Disallow: /config/\n";
echo "Disallow: /vendor/\n";
echo "Disallow: /uploads/\n\n";

// Bloquear URLs com parâmetros sensíveis
echo "# URLs com parâmetros sensíveis\n";
echo "Disallow: /*?cpf=*\n";
echo "Disallow: /*?senha=*\n";
echo "Disallow: /*?token=*\n\n";

// Permitir explicitamente áreas públicas importantes
echo "# Áreas públicas importantes para SEO\n";
echo "Allow: /sistema-cashback/\n";
echo "Allow: /cashback-brasil/\n";
echo "Allow: /como-funciona/\n";
echo "Allow: /vantagens-cashback/\n";
echo "Allow: /lojas-parceiras/\n";
echo "Allow: /blog/\n";
echo "Allow: /cadastro-loja/\n\n";

// Sitemaps
echo "# Sitemaps\n";
echo "Sitemap: {$domain}/sitemap.xml\n";
echo "Sitemap: {$domain}/sitemap-posts.xml\n";
echo "Sitemap: {$domain}/sitemap-lojas.xml\n\n";

// Crawl-delay para não sobrecarregar o servidor
echo "# Configurações de crawling\n";
echo "Crawl-delay: 1\n";
?>