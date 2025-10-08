<?php
/**
 * Dist Asset Loader
 * - Detecta /dist/manifest.json (padrão Vite) e injeta CSS/JS gerados.
 * - Controlado por USE_DIST_ASSETS (auto true se manifest existir).
 * - Seguro para incluir múltiplas vezes (idempotente) e em qualquer ponto do body/head.
 */

if (!defined('USE_DIST_ASSETS') || USE_DIST_ASSETS !== true) {
    return; // Nada a fazer
}

// Evita injeção duplicada
if (defined('DIST_LOADER_ALREADY_RENDERED')) {
    return;
}
define('DIST_LOADER_ALREADY_RENDERED', true);

$manifestPath = DIST_DIR . '/manifest.json';
if (!file_exists($manifestPath)) {
    return;
}

$json = @file_get_contents($manifestPath);
if ($json === false) {
    return;
}

$manifest = json_decode($json, true);
if (!is_array($manifest) || empty($manifest)) {
    return;
}

// Coleta entradas principais (isEntry=true) e seus CSS/JS
$entries = [];
foreach ($manifest as $key => $entry) {
    if (!is_array($entry)) continue;
    $isEntry = isset($entry['isEntry']) ? (bool)$entry['isEntry'] : false;
    // Fallback: considerar como entry se tiver 'file' js em raiz e não for chunk dinâmico
    $hasJs = isset($entry['file']) && is_string($entry['file']) && str_ends_with($entry['file'], '.js');
    if ($isEntry || $hasJs) {
        $entries[] = $entry;
    }
}

// Função helper para escapar URLs de forma simples
$e = function ($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
};

// Render: CSS primeiro, depois JS type=module
echo "\n<!-- dist-loader: begin -->\n";
foreach ($entries as $entry) {
    if (!empty($entry['css']) && is_array($entry['css'])) {
        foreach ($entry['css'] as $css) {
            $href = DIST_URL . '/' . ltrim($css, '/');
            echo '<link rel="stylesheet" href="' . $e($href) . '">' . "\n";
        }
    }
}

foreach ($entries as $entry) {
    if (!empty($entry['file']) && is_string($entry['file'])) {
        $src = DIST_URL . '/' . ltrim($entry['file'], '/');
        echo '<script type="module" src="' . $e($src) . '"></script>' . "\n";
    }
    if (!empty($entry['imports']) && is_array($entry['imports'])) {
        // Pré-carrega chunks importantes (opcional)
        foreach ($entry['imports'] as $imp) {
            $href = DIST_URL . '/' . ltrim($imp, '/');
            echo '<link rel="modulepreload" href="' . $e($href) . '">' . "\n";
        }
    }
}
echo "<!-- dist-loader: end -->\n";

