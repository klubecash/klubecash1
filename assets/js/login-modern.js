(function () {
    'use strict';

    var root = document.documentElement;
    var themeStorageKey = 'klubecash-theme';
    var systemThemeQuery = typeof window.matchMedia === 'function'
        ? window.matchMedia('(prefers-color-scheme: dark)')
        : null;
    var reducedMotionQuery = typeof window.matchMedia === 'function'
        ? window.matchMedia('(prefers-reduced-motion: reduce)')
        : null;
    var hasStoredTheme = false;

    root.classList.add('js');

    function isValidTheme(theme) {
        return theme === 'light' || theme === 'dark';
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

    function updateThemeInterface(theme) {
        var isDark = theme === 'dark';
        var themeToggle = document.getElementById('themeToggle');
        var themeColor = document.getElementById('themeColor');

        if (themeToggle) {
            themeToggle.setAttribute('aria-pressed', String(isDark));
            themeToggle.setAttribute(
                'aria-label',
                isDark ? 'Ativar modo claro' : 'Ativar modo noturno'
            );

            var sunIcon = themeToggle.querySelector('.theme-icon-sun');
            var moonIcon = themeToggle.querySelector('.theme-icon-moon');

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
            var color = isDark
                ? (themeColor.getAttribute('data-dark')
                    || themeColor.getAttribute('data-theme-dark')
                    || '#0d0f14')
                : (themeColor.getAttribute('data-light')
                    || themeColor.getAttribute('data-theme-light')
                    || '#fffaf6');
            themeColor.setAttribute('content', color);
        }
    }

    function applyTheme(theme, persist) {
        var nextTheme = isValidTheme(theme) ? theme : getSystemTheme();

        root.setAttribute('data-theme', nextTheme);
        root.style.colorScheme = nextTheme;
        updateThemeInterface(nextTheme);

        if (persist) {
            hasStoredTheme = true;
            storeTheme(nextTheme);
        }
    }

    function initTheme() {
        var storedTheme = getStoredTheme();
        var themeToggle = document.getElementById('themeToggle');

        hasStoredTheme = Boolean(storedTheme);
        applyTheme(storedTheme || root.getAttribute('data-theme') || getSystemTheme(), false);

        if (themeToggle) {
            themeToggle.addEventListener('click', function () {
                var currentTheme = root.getAttribute('data-theme');
                applyTheme(currentTheme === 'dark' ? 'light' : 'dark', true);
            });
        }

        if (systemThemeQuery) {
            var handleSystemThemeChange = function (event) {
                if (!hasStoredTheme) {
                    applyTheme(event.matches ? 'dark' : 'light', false);
                }
            };

            if (typeof systemThemeQuery.addEventListener === 'function') {
                systemThemeQuery.addEventListener('change', handleSystemThemeChange);
            } else if (typeof systemThemeQuery.addListener === 'function') {
                systemThemeQuery.addListener(handleSystemThemeChange);
            }
        }
    }

    function createElement(tagName, className, textValue) {
        var element = document.createElement(tagName);

        if (className) {
            element.className = className;
        }

        if (typeof textValue === 'string') {
            element.textContent = textValue;
        }

        return element;
    }

    function ToastManager(container) {
        this.container = container;
        this.timers = new WeakMap();
    }

    ToastManager.prototype.getTitle = function (type, title) {
        if (title) {
            return title;
        }

        var titles = {
            success: 'Sucesso!',
            error: 'Erro!',
            warning: 'Atenção!',
            info: 'Informação'
        };

        return titles[type] || titles.info;
    };

    ToastManager.prototype.getIcon = function (type) {
        var icons = {
            success: '✓',
            error: '×',
            warning: '!',
            info: 'i'
        };

        return icons[type] || icons.info;
    };

    ToastManager.prototype.show = function (message, type, title, duration) {
        if (!this.container || !message) {
            return null;
        }

        var toastType = ['success', 'error', 'warning', 'info'].indexOf(type) !== -1
            ? type
            : 'info';
        var toast = createElement('div', 'toast ' + toastType);
        var icon = createElement('span', 'toast-icon', this.getIcon(toastType));
        var content = createElement('div', 'toast-content');
        var toastTitle = createElement('div', 'toast-title', this.getTitle(toastType, title));
        var toastMessage = createElement('div', 'toast-message', String(message));
        var closeButton = createElement('button', 'toast-close', '×');
        var manager = this;

        toast.setAttribute('role', toastType === 'error' || toastType === 'warning' ? 'alert' : 'status');
        toast.setAttribute('aria-live', toastType === 'error' || toastType === 'warning' ? 'assertive' : 'polite');
        toast.setAttribute('aria-atomic', 'true');
        icon.setAttribute('aria-hidden', 'true');
        closeButton.type = 'button';
        closeButton.setAttribute('aria-label', 'Fechar notificação');

        content.appendChild(toastTitle);
        content.appendChild(toastMessage);
        toast.appendChild(icon);
        toast.appendChild(content);
        toast.appendChild(closeButton);
        this.container.appendChild(toast);

        closeButton.addEventListener('click', function () {
            manager.hide(toast);
        });

        window.requestAnimationFrame(function () {
            toast.classList.add('show');
        });

        var timeout = window.setTimeout(function () {
            manager.hide(toast);
        }, typeof duration === 'number' ? duration : 5000);
        this.timers.set(toast, timeout);

        return toast;
    };

    ToastManager.prototype.hide = function (toast) {
        if (!toast || !toast.parentNode) {
            return;
        }

        var timer = this.timers.get(toast);
        if (timer) {
            window.clearTimeout(timer);
        }

        toast.classList.remove('show');
        toast.classList.add('hide');

        var removeToast = function () {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        };

        if (reducedMotionQuery && reducedMotionQuery.matches) {
            removeToast();
        } else {
            window.setTimeout(removeToast, 260);
        }
    };

    ToastManager.prototype.hideAll = function () {
        var manager = this;

        if (!this.container) {
            return;
        }

        Array.prototype.forEach.call(this.container.querySelectorAll('.toast'), function (toast) {
            manager.hide(toast);
        });
    };

    ToastManager.prototype.success = function (message, title) {
        return this.show(message, 'success', title);
    };

    ToastManager.prototype.error = function (message, title) {
        return this.show(message, 'error', title);
    };

    ToastManager.prototype.warning = function (message, title) {
        return this.show(message, 'warning', title);
    };

    ToastManager.prototype.info = function (message, title) {
        return this.show(message, 'info', title);
    };

    function SpinnerManager(overlay, form, button) {
        this.overlay = overlay;
        this.form = form;
        this.button = button;
    }

    SpinnerManager.prototype.show = function () {
        if (this.overlay) {
            this.overlay.classList.add('show');
            this.overlay.setAttribute('aria-hidden', 'false');
        }

        if (this.form) {
            this.form.setAttribute('aria-busy', 'true');
        }

        if (this.button) {
            this.button.setAttribute('aria-busy', 'true');
        }
    };

    SpinnerManager.prototype.hide = function () {
        if (this.overlay) {
            this.overlay.classList.remove('show');
            this.overlay.setAttribute('aria-hidden', 'true');
        }

        if (this.form) {
            this.form.setAttribute('aria-busy', 'false');
        }

        if (this.button) {
            this.button.setAttribute('aria-busy', 'false');
        }
    };

    function updatePasswordVisibility(passwordField, toggleButton, eyeIcon) {
        var showPassword = passwordField.type === 'password';

        passwordField.type = showPassword ? 'text' : 'password';
        toggleButton.setAttribute('aria-pressed', String(showPassword));
        toggleButton.setAttribute('aria-label', showPassword ? 'Ocultar senha' : 'Mostrar senha');
        toggleButton.classList.toggle('is-visible', showPassword);

        if (eyeIcon) {
            eyeIcon.innerHTML = showPassword
                ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>'
                : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>';
        }
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function focusInvalidField(field) {
        if (!field) {
            return;
        }

        field.setAttribute('aria-invalid', 'true');
        field.focus();
    }

    function readInitialFeedback() {
        var feedbackElement = document.getElementById('loginFeedback')
            || document.getElementById('login-feedback');

        if (!feedbackElement) {
            return {};
        }

        try {
            var feedback = JSON.parse(feedbackElement.textContent || '{}');
            return feedback && typeof feedback === 'object' ? feedback : {};
        } catch (error) {
            return {};
        }
    }

    function cleanFeedbackParameters() {
        try {
            var url = new URL(window.location.href);
            var shouldReplace = url.searchParams.has('error') || url.searchParams.has('success');

            if (!shouldReplace) {
                return;
            }

            url.searchParams.delete('error');
            url.searchParams.delete('success');
            window.history.replaceState({}, document.title, url.toString());
        } catch (error) {
            // URL cleanup is a progressive enhancement only.
        }
    }

    function initLogin() {
        var form = document.getElementById('login-form');
        var emailField = document.getElementById('email');
        var passwordField = document.getElementById('password');
        var loginButton = document.getElementById('login-btn');
        var buttonText = document.getElementById('btn-text');
        var passwordToggle = document.querySelector('.password-toggle');
        var eyeIcon = document.getElementById('eye-icon');
        var toastManager = new ToastManager(document.getElementById('toast-container'));
        var spinnerManager = new SpinnerManager(
            document.getElementById('spinner-overlay'),
            form,
            loginButton
        );
        var submitting = false;

        window.toastManager = toastManager;

        if (passwordToggle && passwordField) {
            passwordToggle.removeAttribute('onclick');
            passwordToggle.setAttribute('aria-pressed', 'false');
            passwordToggle.setAttribute('aria-label', 'Mostrar senha');
            passwordToggle.addEventListener('click', function () {
                updatePasswordVisibility(passwordField, passwordToggle, eyeIcon);
            });
        }

        [emailField, passwordField].forEach(function (field) {
            if (field) {
                field.addEventListener('input', function () {
                    field.removeAttribute('aria-invalid');
                });
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                toastManager.hideAll();
            }
        });

        var initialFeedback = readInitialFeedback();
        if (typeof initialFeedback.error === 'string' && initialFeedback.error) {
            toastManager.error(initialFeedback.error);
        }
        if (typeof initialFeedback.success === 'string' && initialFeedback.success) {
            toastManager.success(initialFeedback.success);
        }
        cleanFeedbackParameters();

        if (!form || !emailField || !passwordField || !loginButton || !buttonText) {
            return;
        }

        function finishSubmission() {
            submitting = false;
            loginButton.disabled = false;
            loginButton.classList.remove('is-loading');
            buttonText.textContent = 'Entrar';
            spinnerManager.hide();
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            if (submitting) {
                return;
            }

            var email = emailField.value.trim();
            var password = passwordField.value;

            if (!email) {
                toastManager.error('Por favor, informe seu e-mail.');
                focusInvalidField(emailField);
                return;
            }

            if (!isValidEmail(email)) {
                toastManager.error('Por favor, informe um e-mail válido.');
                focusInvalidField(emailField);
                return;
            }

            if (!password) {
                toastManager.error('Por favor, informe sua senha.');
                focusInvalidField(passwordField);
                return;
            }

            emailField.value = email;
            submitting = true;
            loginButton.disabled = true;
            loginButton.classList.add('is-loading');
            buttonText.textContent = 'Entrando...';
            spinnerManager.show();

            var formData = new FormData(form);
            var requestBody = new URLSearchParams();
            formData.forEach(function (value, key) {
                requestBody.append(key, String(value));
            });

            window.fetch(form.getAttribute('action') || window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'Accept': 'application/json'
                },
                body: requestBody.toString(),
                credentials: 'same-origin'
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(function (data) {
                    if (data && data.status) {
                        spinnerManager.hide();
                        loginButton.classList.remove('is-loading');
                        buttonText.textContent = 'Entrar';
                        toastManager.success(data.message || 'Login efetuado com sucesso!');

                        window.setTimeout(function () {
                            window.location.assign(data.redirect || window.location.href);
                        }, reducedMotionQuery && reducedMotionQuery.matches ? 0 : 500);
                        return;
                    }

                    finishSubmission();
                    toastManager.error(data && data.message ? data.message : 'Erro ao efetuar login.');
                })
                .catch(function (error) {
                    finishSubmission();
                    toastManager.error('Erro de comunicação. Tente novamente.');
                    window.console.error(error);
                });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initTheme();
            initLogin();
        });
    } else {
        initTheme();
        initLogin();
    }
}());
