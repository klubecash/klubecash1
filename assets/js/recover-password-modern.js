(function () {
    'use strict';

    var root = document.documentElement;
    var themeStorageKey = 'klubecash-theme';
    var systemThemeQuery = typeof window.matchMedia === 'function'
        ? window.matchMedia('(prefers-color-scheme: dark)')
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
                sunIcon.classList.toggle('is-visible', isDark);
            }

            if (moonIcon) {
                moonIcon.classList.toggle('is-visible', !isDark);
            }
        }

        if (themeColor) {
            var color = isDark
                ? (themeColor.getAttribute('data-theme-dark') || '#090B10')
                : (themeColor.getAttribute('data-theme-light') || '#F7F8FC');
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

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function getClientFeedback() {
        return document.getElementById('clientFeedback');
    }

    function clearClientFeedback() {
        var feedback = getClientFeedback();

        if (feedback) {
            feedback.hidden = true;
            feedback.textContent = '';
            feedback.className = 'client-feedback';
            delete feedback.dataset.fieldId;
        }
    }

    function showClientFeedback(message, field) {
        var feedback = getClientFeedback();

        if (feedback) {
            feedback.textContent = message;
            feedback.className = 'client-feedback feedback-error';
            feedback.hidden = false;
            feedback.dataset.fieldId = field && field.id ? field.id : '';
        }

        if (field) {
            field.setAttribute('aria-invalid', 'true');
            field.focus();
        } else if (feedback) {
            feedback.focus();
        }
    }

    function clearFieldError(field) {
        if (field) {
            field.removeAttribute('aria-invalid');
        }

        var feedback = getClientFeedback();
        if (feedback && (!feedback.dataset.fieldId || (field && feedback.dataset.fieldId === field.id))) {
            clearClientFeedback();
        }
    }

    function setLoading(button, buttonText, loadingText) {
        if (!button || !buttonText) {
            return;
        }

        button.disabled = true;
        button.classList.add('is-loading');
        button.setAttribute('aria-busy', 'true');
        buttonText.textContent = loadingText;
    }

    function checkPasswordStrength(password, minimumLength) {
        var strengthIndicator = document.getElementById('passwordStrength');
        var strengthFill = document.getElementById('strengthFill');
        var strengthText = document.getElementById('strengthText');
        var strengthBar = document.getElementById('strengthBar');

        if (!strengthIndicator || !strengthFill || !strengthText) {
            return;
        }

        if (!password) {
            strengthIndicator.classList.remove('show');
            strengthFill.className = 'strength-fill';
            strengthText.textContent = 'Digite uma senha para ver a força';
            if (strengthBar) {
                strengthBar.setAttribute('aria-valuenow', '0');
            }
            return;
        }

        strengthIndicator.classList.add('show');

        var strength = 0;
        var feedback = [];

        if (password.length >= minimumLength) {
            strength += 1;
        } else {
            feedback.push('pelo menos ' + minimumLength + ' caracteres');
        }

        if (/[a-z]/.test(password)) {
            strength += 1;
        } else {
            feedback.push('letras minúsculas');
        }

        if (/[A-Z]/.test(password)) {
            strength += 1;
        } else {
            feedback.push('letras maiúsculas');
        }

        if (/[0-9]/.test(password)) {
            strength += 1;
        } else {
            feedback.push('números');
        }

        if (/[^a-zA-Z0-9]/.test(password)) {
            strength += 1;
        } else {
            feedback.push('símbolos');
        }

        var levels = ['weak', 'weak', 'fair', 'good', 'strong', 'strong'];
        var texts = ['Muito fraca', 'Fraca', 'Regular', 'Boa', 'Muito forte', 'Muito forte'];

        strengthFill.className = 'strength-fill ' + levels[strength];

        if (strengthBar) {
            strengthBar.setAttribute('aria-valuenow', String(strength));
        }

        if (strength < 3 && feedback.length > 0) {
            strengthText.textContent = 'Adicione: ' + feedback.slice(0, 2).join(', ');
        } else {
            strengthText.textContent = texts[strength];
        }
    }

    function checkPasswordMatch() {
        var password = document.getElementById('password');
        var confirmPassword = document.getElementById('confirm_password');
        var matchIndicator = document.getElementById('passwordMatch');
        var matchText = document.getElementById('matchText');

        if (!password || !confirmPassword || !matchIndicator || !matchText) {
            return;
        }

        if (!password.value || !confirmPassword.value) {
            matchIndicator.className = 'password-match';
            matchText.textContent = 'As senhas precisam ser iguais';
            if (!confirmPassword.value) {
                confirmPassword.removeAttribute('aria-invalid');
            }
            return;
        }

        if (password.value === confirmPassword.value) {
            matchIndicator.className = 'password-match show valid';
            matchText.textContent = '✓ Senhas coincidem';
            clearFieldError(confirmPassword);
        } else {
            matchIndicator.className = 'password-match show invalid';
            matchText.textContent = '✗ Senhas não coincidem';
            confirmPassword.setAttribute('aria-invalid', 'true');
        }
    }

    function setupPasswordToggle(toggleId, fieldId) {
        var toggle = document.getElementById(toggleId);
        var field = document.getElementById(fieldId);

        if (!toggle || !field) {
            return;
        }

        toggle.addEventListener('click', function () {
            var showPassword = field.type === 'password';
            var symbol = toggle.querySelector('span');

            field.type = showPassword ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', String(showPassword));
            toggle.setAttribute('aria-label', showPassword ? 'Ocultar senha' : 'Mostrar senha');
            toggle.classList.toggle('is-visible', showPassword);

            if (symbol) {
                symbol.textContent = showPassword ? '🙈' : '👁️';
            }
        });
    }

    function initResetForm() {
        var form = document.getElementById('reset-form');

        if (!form) {
            return;
        }

        var passwordField = document.getElementById('password');
        var confirmField = document.getElementById('confirm_password');
        var button = document.getElementById('resetButton');
        var buttonText = document.getElementById('resetButtonText');
        var minimumLength = passwordField && passwordField.minLength > 0
            ? passwordField.minLength
            : 8;

        if (!Number.isFinite(minimumLength) || minimumLength < 1) {
            minimumLength = 8;
        }

        setupPasswordToggle('passwordToggle', 'password');
        setupPasswordToggle('confirmToggle', 'confirm_password');

        if (passwordField) {
            passwordField.addEventListener('input', function () {
                clearFieldError(passwordField);
                checkPasswordStrength(passwordField.value, minimumLength);
                checkPasswordMatch();
            });
        }

        if (confirmField) {
            confirmField.addEventListener('input', function () {
                clearFieldError(confirmField);
                checkPasswordMatch();
            });
        }

        form.addEventListener('submit', function (event) {
            var password = passwordField ? passwordField.value : '';
            var confirmPassword = confirmField ? confirmField.value : '';

            if (!password) {
                event.preventDefault();
                showClientFeedback('Por favor, informe sua nova senha.', passwordField);
                return;
            }

            if (password.length < minimumLength) {
                event.preventDefault();
                showClientFeedback('A senha deve ter no mínimo ' + minimumLength + ' caracteres.', passwordField);
                return;
            }

            if (password !== confirmPassword) {
                event.preventDefault();
                showClientFeedback('As senhas não coincidem.', confirmField);
                return;
            }

            clearClientFeedback();
            setLoading(button, buttonText, 'Alterando senha...');
        });
    }

    function initRequestForm() {
        var form = document.getElementById('request-form');

        if (!form) {
            return;
        }

        var emailField = document.getElementById('email');
        var button = document.getElementById('requestButton');
        var buttonText = document.getElementById('requestButtonText');

        if (emailField) {
            emailField.addEventListener('input', function () {
                clearFieldError(emailField);
            });
        }

        form.addEventListener('submit', function (event) {
            var email = emailField ? emailField.value.trim() : '';

            if (!email || !isValidEmail(email)) {
                event.preventDefault();
                showClientFeedback('Por favor, informe um email válido.', emailField);
                return;
            }

            emailField.value = email;
            clearClientFeedback();
            setLoading(button, buttonText, 'Enviando...');
        });
    }

    function initServerFeedback() {
        var serverFeedback = document.querySelector('.server-feedback');

        if (serverFeedback) {
            window.requestAnimationFrame(function () {
                serverFeedback.focus({ preventScroll: true });
            });
        }
    }

    function init() {
        initTheme();
        initResetForm();
        initRequestForm();
        initServerFeedback();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
