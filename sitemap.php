<?php
// sitemap.php - Sitemap principal otimizado para SEO
header('Content-Type: application/xml; charset=UTF-8');

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$domain = $protocol . $_SERVER['HTTP_HOST'];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">' . "\n";

// Array de páginas principais com prioridades SEO
$pages = [
    // Páginas principais (alta prioridade)
    ['url' => '/', 'priority' => '1.0', 'changefreq' => 'daily'],
    ['url' => '/sistema-cashback/', 'priority' => '1.0', 'changefreq' => 'weekly'],
    ['url' => '/cashback-brasil/', 'priority' => '0.9', 'changefreq' => 'weekly'],
    ['url' => '/como-funciona/', 'priority' => '0.9', 'changefreq' => 'monthly'],
    
    // Páginas de marketing (média-alta prioridade)
    ['url' => '/vantagens-cashback/', 'priority' => '0.8', 'changefreq' => 'monthly'],
    ['url' => '/lojas-parceiras/', 'priority' => '0.8', 'changefreq' => 'weekly'],
    ['url' => '/sobre-klube-cash/', 'priority' => '0.7', 'changefreq' => 'monthly'],
    ['url' => '/contato/', 'priority' => '0.6', 'changefreq' => 'monthly'],
    
    // Landing pages para conversão
    ['url' => '/cashback-para-lojas/', 'priority' => '0.8', 'changefreq' => 'weekly'],
    ['url' => '/cashback-para-clientes/', 'priority' => '0.8', 'changefreq' => 'weekly'],
    ['url' => '/sistema-cashback-empresas/', 'priority' => '0.7', 'changefreq' => 'weekly'],
    
    // Páginas de conversão
    ['url' => '/cadastro-loja/', 'priority' => '0.9', 'changefreq' => 'weekly'],
    ['url' => '/seja-parceiro/', 'priority' => '0.8', 'changefreq' => 'weekly'],
    ['url' => '/registro/', 'priority' => '0.7', 'changefreq' => 'weekly'],
    
    // Blog (se existir)
    ['url' => '/blog/', 'priority' => '0.7', 'changefreq' => 'daily'],
];

foreach ($pages as $page) {
    echo "<url>\n";
    echo "  <loc>{$domain}{$page['url']}</loc>\n";
    echo "  <lastmod>" . date('c') . "</lastmod>\n";
    echo "  <changefreq>{$page['changefreq']}</changefreq>\n";
    echo "  <priority>{$page['priority']}</priority>\n";
    echo "</url>\n";
}

echo '</urlset>';
?>