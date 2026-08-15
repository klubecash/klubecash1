(function () {
    'use strict';

    var root = document.documentElement;
    var themeStorageKey = 'klubecash-theme';
    var validThemes = ['light', 'dark'];
    var systemThemeQuery = typeof window.matchMedia === 'function'
        ? window.matchMedia('(prefers-color-scheme: dark)')
        : null;
    var reducedMotionQuery = typeof window.matchMedia === 'function'
        ? window.matchMedia('(prefers-reduced-motion: reduce)')
        : null;
    var hasManualTheme = false;

    root.classList.add('js');

    function isValidTheme(theme) {
        return validThemes.indexOf(theme) !== -1;
    }

    function getStoredTheme() {
        try {
            var storedTheme = window.localStorage.getItem(themeStorageKey);
            return isValidTheme(storedTheme) ? storedTheme : null;
        } catch (error) {
            return null;
        }
    }

    function storeTheme(theme) {
        try {
            window.localStorage.setItem(themeStorageKey, theme);
        } catch (error) {
            // The selected theme still applies for the current page session.
        }
    }

    function getSystemTheme() {
        return systemThemeQuery && systemThemeQuery.matches ? 'dark' : 'light';
    }

    function updateThemeControls(theme) {
        var isDark = theme === 'dark';
        var toggle = document.getElementById('themeToggle');
        var themeColor = document.getElementById('themeColor');

        if (toggle) {
            var label = isDark ? 'Ativar modo claro' : 'Ativar modo noturno';
            toggle.setAttribute('aria-pressed', String(isDark));
            toggle.setAttribute('aria-label', label);

            var sunIcon = toggle.querySelector('.theme-icon-sun');
            var moonIcon = toggle.querySelector('.theme-icon-moon');

            if (sunIcon) {
                sunIcon.setAttribute('aria-hidden', 'true');
                sunIcon.classList.toggle('is-visible', isDark);
            }

            if (moonIcon) {
                moonIcon.setAttribute('aria-hidden', 'true');
                moonIcon.classList.toggle('is-visible', !isDark);
            }
        }

        if (themeColor) {
            var lightColor = themeColor.getAttribute('data-light')
                || themeColor.getAttribute('data-theme-light')
                || '#fffaf6';
            var darkColor = themeColor.getAttribute('data-dark')
                || themeColor.getAttribute('data-theme-dark')
                || '#101116';

            themeColor.setAttribute('content', isDark ? darkColor : lightColor);
        }
    }

    function applyTheme(theme, persist) {
        var nextTheme = isValidTheme(theme) ? theme : getSystemTheme();

        root.setAttribute('data-theme', nextTheme);
        root.style.colorScheme = nextTheme;
        updateThemeControls(nextTheme);

        if (persist) {
            hasManualTheme = true;
            storeTheme(nextTheme);
        }
    }

    var storedTheme = getStoredTheme();
    hasManualTheme = Boolean(storedTheme);
    applyTheme(storedTheme || getSystemTheme(), false);

    function handleSystemThemeChange(event) {
        if (!hasManualTheme) {
            applyTheme(event.matches ? 'dark' : 'light', false);
        }
    }

    if (systemThemeQuery) {
        if (typeof systemThemeQuery.addEventListener === 'function') {
            systemThemeQuery.addEventListener('change', handleSystemThemeChange);
        } else if (typeof systemThemeQuery.addListener === 'function') {
            systemThemeQuery.addListener(handleSystemThemeChange);
        }
    }

    function initThemeToggle() {
        var toggle = document.getElementById('themeToggle');

        updateThemeControls(root.getAttribute('data-theme') || getSystemTheme());

        if (!toggle) {
            return;
        }

        toggle.addEventListener('click', function () {
            var currentTheme = root.getAttribute('data-theme');
            applyTheme(currentTheme === 'dark' ? 'light' : 'dark', true);
        });
    }

    function initHeaderScrollState() {
        var header = document.getElementById('mainHeader');

        if (!header) {
            return;
        }

        var scheduled = false;

        function updateHeaderState() {
            scheduled = false;
            header.classList.toggle('is-scrolled', window.scrollY > 12);
        }

        function scheduleUpdate() {
            if (!scheduled) {
                scheduled = true;
                window.requestAnimationFrame(updateHeaderState);
            }
        }

        updateHeaderState();
        window.addEventListener('scroll', scheduleUpdate, { passive: true });
    }

    function getFocusableElements(container) {
        if (!container) {
            return [];
        }

        return Array.prototype.slice.call(container.querySelectorAll(
            'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), ' +
            'textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )).filter(function (element) {
            return !element.hasAttribute('hidden') && element.getAttribute('aria-hidden') !== 'true';
        });
    }

    function initMobileMenu(onOpen) {
        var button = document.getElementById('mobileMenuBtn');
        var menu = document.getElementById('mobileMenu');

        if (!button || !menu) {
            return null;
        }

        var isOpen = false;
        var scrollLocked = false;
        var previousBodyOverflow = '';
        var desktopQuery = typeof window.matchMedia === 'function'
            ? window.matchMedia('(min-width: 960px)')
            : null;

        if (!menu.id) {
            menu.id = 'mobileMenu';
        }

        button.setAttribute('type', 'button');
        button.setAttribute('aria-controls', menu.id);

        function setScrollLock(shouldLock) {
            if (shouldLock && !scrollLocked) {
                previousBodyOverflow = document.body.style.overflow;
                document.body.style.overflow = 'hidden';
                document.body.classList.add('menu-open');
                root.classList.add('menu-open');
                scrollLocked = true;
            } else if (!shouldLock && scrollLocked) {
                document.body.style.overflow = previousBodyOverflow;
                document.body.classList.remove('menu-open');
                root.classList.remove('menu-open');
                scrollLocked = false;
            }
        }

        function restoreTriggerFocus() {
            if (button.getClientRects().length) {
                button.focus();
                return;
            }

            var brandLink = document.querySelector('.brand-logo');
            if (brandLink) {
                brandLink.focus();
            }
        }

        function renderMenuState() {
            button.setAttribute('aria-expanded', String(isOpen));
            button.setAttribute('aria-label', isOpen ? 'Fechar menu principal' : 'Abrir menu principal');
            menu.setAttribute('aria-hidden', String(!isOpen));
            menu.hidden = !isOpen;
            menu.classList.toggle('show', isOpen);
            menu.classList.toggle('is-open', isOpen);
            button.classList.toggle('is-open', isOpen);
            setScrollLock(isOpen);
        }

        function openMenu() {
            if (isOpen) {
                return;
            }

            if (typeof onOpen === 'function') {
                onOpen();
            }

            isOpen = true;
            renderMenuState();

            var focusableElements = getFocusableElements(menu);
            if (focusableElements.length) {
                focusableElements[0].focus();
            } else {
                menu.setAttribute('tabindex', '-1');
                menu.focus();
            }
        }

        function closeMenu(restoreFocus) {
            var wasOpen = isOpen;
            isOpen = false;
            renderMenuState();

            if (wasOpen && restoreFocus) {
                restoreTriggerFocus();
            }
        }

        button.addEventListener('click', function () {
            if (isOpen) {
                closeMenu(true);
            } else {
                openMenu();
            }
        });

        menu.addEventListener('click', function (event) {
            var link = event.target.closest ? event.target.closest('a[href]') : null;
            if (link && menu.contains(link)) {
                closeMenu(true);
            }
        });

        document.addEventListener('pointerdown', function (event) {
            if (isOpen && !menu.contains(event.target) && !button.contains(event.target)) {
                closeMenu(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (isOpen && event.key === 'Escape') {
                event.preventDefault();
                closeMenu(true);
            }
        });

        function handleDesktopChange(event) {
            if (event.matches && isOpen) {
                closeMenu(true);
            }
        }

        if (desktopQuery) {
            if (typeof desktopQuery.addEventListener === 'function') {
                desktopQuery.addEventListener('change', handleDesktopChange);
            } else if (typeof desktopQuery.addListener === 'function') {
                desktopQuery.addListener(handleDesktopChange);
            }
        }

        renderMenuState();

        return {
            close: closeMenu,
            isOpen: function () {
                return isOpen;
            }
        };
    }

    function initUserMenu(onOpen) {
        var button = document.getElementById('userMenuBtn');
        var dropdown = document.getElementById('userDropdown');

        if (!button || !dropdown) {
            return null;
        }

        var isOpen = false;

        if (!dropdown.id) {
            dropdown.id = 'userDropdown';
        }

        button.setAttribute('type', 'button');
        button.setAttribute('aria-haspopup', 'menu');
        button.setAttribute('aria-controls', dropdown.id);
        dropdown.setAttribute('role', 'menu');

        var items = getFocusableElements(dropdown);
        items.forEach(function (item) {
            item.setAttribute('role', 'menuitem');
        });

        function renderDropdownState() {
            button.setAttribute('aria-expanded', String(isOpen));
            dropdown.setAttribute('aria-hidden', String(!isOpen));
            dropdown.hidden = !isOpen;
            dropdown.classList.toggle('show', isOpen);
            dropdown.classList.toggle('is-open', isOpen);
            button.classList.toggle('is-open', isOpen);
        }

        function focusItem(index) {
            if (!items.length) {
                return;
            }

            var normalizedIndex = (index + items.length) % items.length;
            items[normalizedIndex].focus();
        }

        function openDropdown(focusIndex) {
            if (!isOpen) {
                if (typeof onOpen === 'function') {
                    onOpen();
                }

                isOpen = true;
                renderDropdownState();
            }

            if (typeof focusIndex === 'number') {
                focusItem(focusIndex);
            }
        }

        function closeDropdown(restoreFocus) {
            var wasOpen = isOpen;
            isOpen = false;
            renderDropdownState();

            if (wasOpen && restoreFocus) {
                button.focus();
            }
        }

        button.addEventListener('click', function () {
            if (isOpen) {
                closeDropdown(true);
            } else {
                openDropdown(0);
            }
        });

        button.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                openDropdown(0);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                openDropdown(items.length - 1);
            } else if (event.key === 'Home') {
                event.preventDefault();
                openDropdown(0);
            } else if (event.key === 'End') {
                event.preventDefault();
                openDropdown(items.length - 1);
            } else if (event.key === 'Escape' && isOpen) {
                event.preventDefault();
                closeDropdown(true);
            }
        });

        dropdown.addEventListener('keydown', function (event) {
            var currentIndex = items.indexOf(document.activeElement);

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                focusItem(currentIndex < 0 ? 0 : currentIndex + 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                focusItem(currentIndex < 0 ? items.length - 1 : currentIndex - 1);
            } else if (event.key === 'Home') {
                event.preventDefault();
                focusItem(0);
            } else if (event.key === 'End') {
                event.preventDefault();
                focusItem(items.length - 1);
            } else if (event.key === 'Escape') {
                event.preventDefault();
                closeDropdown(true);
            } else if (event.key === 'Tab') {
                window.setTimeout(function () {
                    closeDropdown(false);
                }, 0);
            }
        });

        dropdown.addEventListener('click', function (event) {
            var item = event.target.closest ? event.target.closest('a[href], button') : null;
            if (item && dropdown.contains(item)) {
                var href = item.getAttribute('href') || '';
                closeDropdown(href.charAt(0) === '#');
            }
        });

        document.addEventListener('pointerdown', function (event) {
            if (isOpen && !dropdown.contains(event.target) && !button.contains(event.target)) {
                closeDropdown(false);
            }
        });

        window.addEventListener('resize', function () {
            if (isOpen) {
                closeDropdown(document.activeElement && dropdown.contains(document.activeElement));
            }
        }, { passive: true });

        renderDropdownState();

        return {
            close: closeDropdown,
            isOpen: function () {
                return isOpen;
            }
        };
    }

    function getHeaderHeight() {
        var header = document.getElementById('mainHeader');
        return header ? Math.ceil(header.getBoundingClientRect().height) : 0;
    }

    function initSmoothScroll() {
        var links = document.querySelectorAll('a[href^="#"]');

        Array.prototype.forEach.call(links, function (link) {
            link.addEventListener('click', function (event) {
                if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                    return;
                }

                var href = link.getAttribute('href');

                if (!href || href === '#') {
                    event.preventDefault();
                    return;
                }

                var targetId;
                try {
                    targetId = decodeURIComponent(href.slice(1));
                } catch (error) {
                    targetId = href.slice(1);
                }

                if (!targetId) {
                    event.preventDefault();
                    return;
                }

                var target = document.getElementById(targetId);
                if (!target) {
                    return;
                }

                event.preventDefault();

                var targetTop = target.getBoundingClientRect().top + window.scrollY - getHeaderHeight();
                var reduceMotion = reducedMotionQuery && reducedMotionQuery.matches;

                window.scrollTo({
                    top: Math.max(0, targetTop),
                    behavior: reduceMotion ? 'auto' : 'smooth'
                });
            });
        });
    }

    function initActiveSectionNavigation() {
        var sectionIds = ['como-funciona', 'vantagens', 'parceiros', 'sobre'];
        var sections = sectionIds.map(function (id) {
            return document.getElementById(id);
        }).filter(Boolean);
        var navigationLinks = Array.prototype.slice.call(document.querySelectorAll(
            '.nav-link[href^="#"], .mobile-nav-link[href^="#"]'
        ));

        if (!sections.length || !navigationLinks.length) {
            return;
        }

        var activeId = null;
        var scheduled = false;

        function setActiveSection(nextId) {
            if (nextId === activeId) {
                return;
            }

            activeId = nextId;

            navigationLinks.forEach(function (link) {
                var isActive = link.getAttribute('href') === '#' + nextId;
                link.classList.toggle('active', isActive);
                link.classList.toggle('is-active', isActive);

                if (isActive) {
                    link.setAttribute('aria-current', 'location');
                } else {
                    link.removeAttribute('aria-current');
                }
            });
        }

        function updateActiveSection() {
            scheduled = false;

            var activationLine = getHeaderHeight() + Math.min(window.innerHeight * 0.28, 220);
            var nextId = null;

            sections.forEach(function (section) {
                if (section.getBoundingClientRect().top <= activationLine) {
                    nextId = section.id;
                }
            });

            setActiveSection(nextId);
        }

        function scheduleUpdate() {
            if (!scheduled) {
                scheduled = true;
                window.requestAnimationFrame(updateActiveSection);
            }
        }

        updateActiveSection();
        window.addEventListener('scroll', scheduleUpdate, { passive: true });
        window.addEventListener('resize', scheduleUpdate, { passive: true });
    }

    function initRevealAnimations() {
        var elements = Array.prototype.slice.call(document.querySelectorAll('.fade-in'));

        if (!elements.length) {
            return;
        }

        function reveal(element) {
            element.classList.add('is-visible');
            element.classList.add('visible');
        }

        function revealAll() {
            root.classList.remove('reveal-ready');
            elements.forEach(reveal);
        }

        if ((reducedMotionQuery && reducedMotionQuery.matches) || !('IntersectionObserver' in window)) {
            revealAll();
            return;
        }

        var observer;

        try {
            observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        reveal(entry.target);
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.12,
                rootMargin: '0px 0px -8% 0px'
            });

            root.classList.add('reveal-ready');
            elements.forEach(function (element) {
                observer.observe(element);
            });
        } catch (error) {
            revealAll();
            return;
        }

        function handleReducedMotionChange(event) {
            if (event.matches) {
                observer.disconnect();
                revealAll();
            }
        }

        if (reducedMotionQuery) {
            if (typeof reducedMotionQuery.addEventListener === 'function') {
                reducedMotionQuery.addEventListener('change', handleReducedMotionChange, { once: true });
            } else if (typeof reducedMotionQuery.addListener === 'function') {
                reducedMotionQuery.addListener(handleReducedMotionChange);
            }
        }
    }

    function recoverDisclosure(menuId, buttonId) {
        var menu = document.getElementById(menuId);
        var button = document.getElementById(buttonId);

        if (menu) {
            menu.hidden = false;
            menu.removeAttribute('aria-hidden');
            menu.classList.add('show');
            menu.classList.add('is-open');
        }

        if (button) {
            button.setAttribute('aria-expanded', 'true');
        }
    }

    function runSafely(initializer, fallback) {
        try {
            return initializer();
        } catch (error) {
            if (typeof fallback === 'function') {
                fallback();
            }
            return null;
        }
    }

    function initializePage() {
        var mobileMenuApi = null;
        var userMenuApi = null;

        runSafely(initThemeToggle);
        runSafely(initHeaderScrollState);

        mobileMenuApi = runSafely(function () {
            return initMobileMenu(function () {
                if (userMenuApi) {
                    userMenuApi.close(false);
                }
            });
        }, function () {
            recoverDisclosure('mobileMenu', 'mobileMenuBtn');
        });

        userMenuApi = runSafely(function () {
            return initUserMenu(function () {
                if (mobileMenuApi) {
                    mobileMenuApi.close(false);
                }
            });
        }, function () {
            recoverDisclosure('userDropdown', 'userMenuBtn');
        });

        runSafely(initSmoothScroll);
        runSafely(initActiveSectionNavigation);
        runSafely(initRevealAnimations, function () {
            root.classList.remove('reveal-ready');
            Array.prototype.forEach.call(document.querySelectorAll('.fade-in'), function (element) {
                element.classList.add('is-visible');
                element.classList.add('visible');
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializePage, { once: true });
    } else {
        initializePage();
    }
}());
