<?php
/**
 * Recursos compartilhados da aplicação do lojista.
 *
 * Este fragmento deve ser incluído no final do <head> das telas /store.
 */
$storeAppCssPath = __DIR__ . '/../../assets/css/store-app-modern.css';
$storeAppJsPath = __DIR__ . '/../../assets/js/sidebar-lojista.js';
$storeAppCssVersion = file_exists($storeAppCssPath) ? (string) filemtime($storeAppCssPath) : '1';
$storeAppJsVersion = file_exists($storeAppJsPath) ? (string) filemtime($storeAppJsPath) : '1';
?>
<meta name="theme-color" id="storeThemeColor" content="#F7F8FC" data-light="#F7F8FC" data-dark="#0C0D12">
<meta name="color-scheme" content="light dark">
<meta name="view-transition" content="same-origin">
<script>
    (function () {
        var theme = 'light';
        try {
            var storedTheme = window.localStorage.getItem('klubecash-theme');
            if (storedTheme === 'light' || storedTheme === 'dark') {
                theme = storedTheme;
            } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                theme = 'dark';
            }
        } catch (error) {
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                theme = 'dark';
            }
        }
        document.documentElement.classList.add('store-app-root');
        document.documentElement.dataset.theme = theme;
        document.documentElement.style.colorScheme = theme;
    }());
</script>
<link rel="stylesheet" href="<?php echo htmlspecialchars('/assets/css/store-app-modern.css?v=' . rawurlencode($storeAppCssVersion)); ?>">
<script src="<?php echo htmlspecialchars('/assets/js/sidebar-lojista.js?v=' . rawurlencode($storeAppJsVersion)); ?>" defer></script>
