(function () {
    'use strict';

    if (window.__KLUBE_STORE_APP_LOADED__) {
        return;
    }
    window.__KLUBE_STORE_APP_LOADED__ = true;

    var THEME_KEY = 'klubecash-theme';
    var SIDEBAR_KEY = 'klube-sidebar-lojista-colapsada';
    var MOBILE_BREAKPOINT = 960;
    var speculationTimers = new WeakMap();
    var warmedUrls = new Set();
    var navigationTimeout = 0;

    function safeStorageGet(key) {
        try {
            return window.localStorage.getItem(key);
        } catch (error) {
            return null;
        }
    }

    function safeStorageSet(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch (error) {
            // A preferência continua válida durante a sessão atual.
        }
    }

    function getPreferredTheme() {
        var stored = safeStorageGet(THEME_KEY);
        if (stored === 'light' || stored === 'dark') {
            return stored;
        }
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
            ? 'dark'
            : 'light';
    }

    function applyTheme(theme, persist) {
        var normalizedTheme = theme === 'dark' ? 'dark' : 'light';
        var root = document.documentElement;
        var toggle = document.getElementById('storeThemeToggle');
        var themeColor = document.getElementById('storeThemeColor');

        root.dataset.theme = normalizedTheme;
        root.style.colorScheme = normalizedTheme;

        if (themeColor) {
            themeColor.setAttribute(
                'content',
                normalizedTheme === 'dark'
                    ? (themeColor.dataset.dark || '#0C0D12')
                    : (themeColor.dataset.light || '#F7F8FC')
            );
        }

        if (toggle) {
            var isDark = normalizedTheme === 'dark';
            toggle.setAttribute('aria-pressed', String(isDark));
            toggle.setAttribute('aria-label', isDark ? 'Ativar modo claro' : 'Ativar modo noturno');
            toggle.title = isDark ? 'Ativar modo claro' : 'Ativar modo noturno';
        }

        if (persist) {
            safeStorageSet(THEME_KEY, normalizedTheme);
        }
    }

    function isMobile() {
        return window.matchMedia('(max-width: ' + MOBILE_BREAKPOINT + 'px)').matches;
    }

    function isStoreUrl(value) {
        try {
            var url = new URL(value, window.location.href);
            return url.origin === window.location.origin
                && (url.pathname === '/store' || url.pathname.indexOf('/store/') === 0);
        } catch (error) {
            return false;
        }
    }

    function canNavigate(link, event) {
        if (!link || !link.href || !isStoreUrl(link.href)) return false;
        if (link.target && link.target !== '_self') return false;
        if (link.hasAttribute('download') || link.dataset.noStoreTransition === 'true') return false;
        if (event && (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button > 0)) return false;
        return true;
    }

    function normalizePath(value) {
        try {
            var url = new URL(value, window.location.href);
            return url.pathname.replace(/\/$/, '') || '/';
        } catch (error) {
            return '';
        }
    }

    function canSpeculate(link) {
        if (!canNavigate(link) || link.dataset.storeSpeculate !== 'safe') return false;
        if (navigator.connection && navigator.connection.saveData) return false;
        if (navigator.connection && /(^|-)2g$/.test(navigator.connection.effectiveType || '')) return false;

        var target = new URL(link.href, window.location.href);
        var current = new URL(window.location.href);
        return target.pathname !== current.pathname || target.search !== current.search;
    }

    function warmLink(link, mode) {
        if (!canSpeculate(link)) return;

        var target = new URL(link.href, window.location.href);
        var key = mode + ':' + target.href;
        if (warmedUrls.has(key)) return;
        warmedUrls.add(key);

        if (window.HTMLScriptElement
            && typeof window.HTMLScriptElement.supports === 'function'
            && window.HTMLScriptElement.supports('speculationrules')) {
            var rule = document.createElement('script');
            var rules = {};
            rules[mode] = [{
                source: 'list',
                urls: [target.pathname + target.search],
                eagerness: 'immediate'
            }];
            rule.type = 'speculationrules';
            rule.textContent = JSON.stringify(rules);
            rule.dataset.storeSpeculation = mode;
            document.head.appendChild(rule);
            return;
        }

        if (mode === 'prefetch' || !document.prerendering) {
            var hint = document.createElement('link');
            hint.rel = 'prefetch';
            hint.as = 'document';
            hint.href = target.href;
            hint.dataset.storePrefetch = 'true';
            document.head.appendChild(hint);
        }
    }

    function scheduleSpeculation(link) {
        if (!canSpeculate(link) || speculationTimers.has(link)) return;
        var timer = window.setTimeout(function () {
            speculationTimers.delete(link);
            warmLink(link, 'prerender');
        }, 120);
        speculationTimers.set(link, timer);
    }

    function cancelSpeculation(link) {
        var timer = speculationTimers.get(link);
        if (timer) {
            window.clearTimeout(timer);
            speculationTimers.delete(link);
        }
    }

    function clearNavigationState() {
        window.clearTimeout(navigationTimeout);
        navigationTimeout = 0;
        delete document.documentElement.dataset.storeNavigating;
        document.documentElement.removeAttribute('aria-busy');
        document.querySelectorAll('.link-menu-sidebar.carregando').forEach(function (link) {
            link.classList.remove('carregando');
        });
    }

    function startNavigation(link, status) {
        var root = document.documentElement;
        root.dataset.storeNavigating = 'true';
        root.setAttribute('aria-busy', 'true');
        link.classList.add('carregando');
        if (status) status.textContent = 'Abrindo ' + (link.textContent || 'tela').trim();

        window.clearTimeout(navigationTimeout);
        navigationTimeout = window.setTimeout(clearNavigationState, 12000);
    }

    function markActiveNavigation(sidebar) {
        var currentPath = normalizePath(window.location.href);
        var links = sidebar.querySelectorAll('.link-menu-sidebar[href]');
        var bestMatch = null;

        if (currentPath === '/store') {
            currentPath = '/store/dashboard';
        }

        links.forEach(function (link) {
            var linkPath = normalizePath(link.href);
            var matches = linkPath === currentPath;
            if (matches && !bestMatch) bestMatch = link;
        });

        if (bestMatch) {
            links.forEach(function (link) {
                link.classList.remove('menu-ativo');
                link.removeAttribute('aria-current');
            });
            bestMatch.classList.add('menu-ativo');
            bestMatch.setAttribute('aria-current', 'page');
        }
    }

    function init() {
        var root = document.documentElement;
        var body = document.body;
        var sidebar = document.getElementById('sidebarLojistaResponsiva');
        var collapseButton = document.getElementById('botaoColapsarSidebar');
        var mobileButton = document.getElementById('botaoToggleMobile');
        var overlay = document.getElementById('overlaySidebarMobile');
        var themeToggle = document.getElementById('storeThemeToggle');
        var status = document.getElementById('storeAppStatus');
        var progress = document.querySelector('.store-app-progress');
        var lastFocusedElement = null;

        if (!progress) {
            progress = document.createElement('div');
            progress.className = 'store-app-progress';
            progress.setAttribute('aria-hidden', 'true');
            body.prepend(progress);
        }

        applyTheme(getPreferredTheme(), false);

        if (themeToggle) {
            themeToggle.addEventListener('click', function () {
                var nextTheme = root.dataset.theme === 'dark' ? 'light' : 'dark';
                applyTheme(nextTheme, true);
                if (status) status.textContent = nextTheme === 'dark' ? 'Modo noturno ativado' : 'Modo claro ativado';
            });
        }

        if (!sidebar) return;

        function updateLayout() {
            var collapsed = !isMobile() && safeStorageGet(SIDEBAR_KEY) === 'true';
            sidebar.classList.toggle('colapsada', collapsed);
            document.querySelectorAll('.main-content, .conteudo-principal, main').forEach(function (content) {
                content.classList.add('conteudo-principal-ajustado');
                content.classList.toggle('sidebar-colapsada', collapsed);
            });
            if (collapseButton) {
                collapseButton.setAttribute('aria-expanded', String(!collapsed));
                collapseButton.setAttribute('aria-label', collapsed ? 'Expandir menu' : 'Minimizar menu');
            }
        }

        function openMobile() {
            if (!isMobile()) return;
            lastFocusedElement = document.activeElement;
            sidebar.classList.add('aberta');
            overlay && overlay.classList.add('ativo');
            body.classList.add('sidebar-mobile-aberta');
            mobileButton && mobileButton.setAttribute('aria-expanded', 'true');
            mobileButton && mobileButton.setAttribute('aria-label', 'Fechar menu');
            var current = sidebar.querySelector('[aria-current="page"]') || sidebar.querySelector('.link-menu-sidebar');
            window.setTimeout(function () { current && current.focus(); }, 120);
        }

        function closeMobile(restoreFocus) {
            sidebar.classList.remove('aberta');
            overlay && overlay.classList.remove('ativo');
            body.classList.remove('sidebar-mobile-aberta');
            mobileButton && mobileButton.setAttribute('aria-expanded', 'false');
            mobileButton && mobileButton.setAttribute('aria-label', 'Abrir menu');
            if (restoreFocus && lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
                lastFocusedElement.focus();
            }
        }

        updateLayout();
        markActiveNavigation(sidebar);

        collapseButton && collapseButton.addEventListener('click', function () {
            if (isMobile()) return;
            var nextCollapsed = !sidebar.classList.contains('colapsada');
            safeStorageSet(SIDEBAR_KEY, String(nextCollapsed));
            updateLayout();
            window.dispatchEvent(new CustomEvent('sidebarLojistaToggle', {
                detail: { colapsada: nextCollapsed, largura: nextCollapsed ? 88 : 282, mobile: false }
            }));
            if (status) status.textContent = nextCollapsed ? 'Menu minimizado' : 'Menu expandido';
        });

        mobileButton && mobileButton.addEventListener('click', function () {
            if (sidebar.classList.contains('aberta')) closeMobile(true);
            else openMobile();
        });

        overlay && overlay.addEventListener('click', function () { closeMobile(true); });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && sidebar.classList.contains('aberta')) {
                closeMobile(true);
            }
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'b') {
                event.preventDefault();
                if (isMobile()) {
                    sidebar.classList.contains('aberta') ? closeMobile(true) : openMobile();
                } else {
                    collapseButton && collapseButton.click();
                }
            }
        });

        window.addEventListener('resize', function () {
            closeMobile(false);
            updateLayout();
        }, { passive: true });

        document.addEventListener('pointerover', function (event) {
            var link = event.target.closest('a[href]');
            if (link) scheduleSpeculation(link);
        }, { passive: true });

        document.addEventListener('pointerout', function (event) {
            var link = event.target.closest('a[href]');
            if (link) cancelSpeculation(link);
        }, { passive: true });

        document.addEventListener('focusin', function (event) {
            var link = event.target.closest('a[href]');
            if (link) warmLink(link, 'prefetch');
        });

        document.addEventListener('pointerdown', function (event) {
            var link = event.target.closest('a[href]');
            if (link) warmLink(link, 'prerender');
        }, { passive: true });

        document.addEventListener('click', function (event) {
            var link = event.target.closest('a[href]');
            if (!canNavigate(link, event)) return;

            var current = new URL(window.location.href);
            var target = new URL(link.href);
            if (current.pathname === target.pathname && current.search === target.search && target.hash) return;

            startNavigation(link, status);
            if (isMobile()) closeMobile(false);
        });

        window.addEventListener('pageshow', clearNavigationState);

        window.sidebarLojistaResponsiva = {
            abrirMobile: openMobile,
            fecharMobile: closeMobile,
            ajustarConteudoPrincipal: updateLayout,
            alternarMobile: function () {
                sidebar.classList.contains('aberta') ? closeMobile(true) : openMobile();
            },
            alternarColapsarDesktop: function () {
                collapseButton && collapseButton.click();
            }
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
}());
