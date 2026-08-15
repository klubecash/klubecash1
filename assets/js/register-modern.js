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
            // The selected theme remains active for the current page.
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
            themeToggle.setAttribute('aria-label', isDark ? 'Ativar modo claro' : 'Ativar modo noturno');

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
                applyTheme(root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark', true);
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

    function createAlert(type, message) {
        var alert = document.createElement('div');
        alert.className = 'alert alert-' + type;
        alert.setAttribute('role', type === 'error' ? 'alert' : 'status');
        alert.textContent = message;
        return alert;
    }

    function showAlert(type, message, replaceExisting) {
        var feedbackRegion = document.getElementById('feedbackRegion');
        var form = document.getElementById('register-form');

        if (!message || (!feedbackRegion && !form)) {
            return null;
        }

        if (replaceExisting) {
            var existingAlert = document.querySelector('.alert-' + type);
            if (existingAlert) {
                existingAlert.remove();
            }
        }

        var alert = createAlert(type, message);
        if (feedbackRegion) {
            feedbackRegion.appendChild(alert);
        } else {
            form.parentNode.insertBefore(alert, form);
        }

        return alert;
    }

    function initRegisterForm() {
        var form = document.getElementById('register-form');
        var nomeField = document.getElementById('nome');
        var emailField = document.getElementById('email');
        var telefoneField = document.getElementById('telefone');
        var senhaField = document.getElementById('senha');
        var progressIndicator = document.getElementById('progressIndicator');
        var passwordToggle = document.getElementById('passwordToggle');
        var strengthIndicator = document.getElementById('passwordStrength');
        var strengthFill = document.getElementById('strengthFill');
        var strengthText = document.getElementById('strengthText');
        var submitButton = document.getElementById('submitButton');
        var buttonText = document.getElementById('buttonText');
        var loadingSpinner = document.getElementById('loadingSpinner');
        var minimumPasswordLength = Number.parseInt(senhaField && senhaField.getAttribute('minlength'), 10) || 8;

        if (!form || !nomeField || !emailField || !telefoneField || !senhaField) {
            return;
        }

        function updateProgress() {
            var steps = document.querySelectorAll('.progress-step');
            var nome = nomeField.value;
            var email = emailField.value;
            var telefone = telefoneField.value;
            var senha = senhaField.value;
            var activeSteps = 0;

            if (nome && email) {
                activeSteps = 1;
            }
            if (nome && email && telefone) {
                activeSteps = 2;
            }
            if (nome && email && telefone && senha) {
                activeSteps = 3;
            }

            Array.prototype.forEach.call(steps, function (step, index) {
                step.classList.toggle('active', index < activeSteps);
            });

            if (progressIndicator) {
                progressIndicator.setAttribute('aria-valuenow', String(activeSteps));
            }
        }

        function checkPasswordStrength(password) {
            if (!strengthIndicator || !strengthFill || !strengthText) {
                return;
            }

            if (!password) {
                strengthIndicator.classList.remove('show');
                strengthFill.className = 'strength-fill';
                strengthText.textContent = 'Digite uma senha para ver a força';
                return;
            }

            strengthIndicator.classList.add('show');

            var strength = 0;
            var feedback = [];

            if (password.length >= minimumPasswordLength) {
                strength += 1;
            } else {
                feedback.push('pelo menos ' + minimumPasswordLength + ' caracteres');
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

            // Score 5 intentionally maps to the strongest existing visual state.
            var levels = ['weak', 'weak', 'fair', 'good', 'strong', 'strong'];
            var texts = ['Muito fraca', 'Fraca', 'Regular', 'Boa', 'Muito forte', 'Muito forte'];
            strengthFill.className = 'strength-fill ' + levels[strength];

            if (strength < 3 && feedback.length > 0) {
                strengthText.textContent = 'Adicione: ' + feedback.slice(0, 2).join(', ');
            } else {
                strengthText.textContent = texts[strength];
            }
        }

        if (passwordToggle) {
            passwordToggle.addEventListener('click', function () {
                var isPassword = senhaField.type === 'password';
                var icon = passwordToggle.querySelector('[aria-hidden="true"]');

                senhaField.type = isPassword ? 'text' : 'password';
                passwordToggle.setAttribute('aria-pressed', String(isPassword));
                passwordToggle.setAttribute('aria-label', isPassword ? 'Ocultar senha' : 'Mostrar senha');

                if (icon) {
                    icon.textContent = isPassword ? '🙈' : '👁️';
                }
            });
        }

        telefoneField.addEventListener('input', function (event) {
            var value = event.target.value.replace(/\D/g, '');

            if (value.length <= 11) {
                if (value.length > 2) {
                    value = '(' + value.substring(0, 2) + ') ' + value.substring(2);
                }
                if (value.length > 10) {
                    value = value.substring(0, 10) + '-' + value.substring(10);
                }
            }

            event.target.value = value;
            updateProgress();
        });

        [nomeField, emailField, telefoneField, senhaField].forEach(function (field) {
            field.addEventListener('input', function () {
                updateProgress();
                field.removeAttribute('aria-invalid');

                if (field === senhaField) {
                    checkPasswordStrength(field.value);
                }
            });

            field.addEventListener('focus', function () {
                if (field.parentElement) {
                    field.parentElement.classList.add('is-focused');
                }
            });

            field.addEventListener('blur', function () {
                if (field.parentElement) {
                    field.parentElement.classList.remove('is-focused');
                }
            });
        });

        form.addEventListener('submit', function (event) {
            var email = emailField.value;
            var nome = nomeField.value;
            var telefone = telefoneField.value;
            var senha = senhaField.value;
            var isValid = true;
            var errorMessage = '';
            var invalidField = null;

            if (!email || !isValidEmail(email)) {
                errorMessage = 'Por favor, informe um email válido.';
                isValid = false;
                invalidField = emailField;
            }

            if (!nome || nome.length < 3) {
                errorMessage = 'Por favor, informe seu nome completo (mínimo 3 caracteres).';
                isValid = false;
                invalidField = nomeField;
            }

            if (!telefone || telefone.replace(/\D/g, '').length < 10) {
                errorMessage = 'Por favor, informe um telefone válido.';
                isValid = false;
                invalidField = telefoneField;
            }

            if (!senha || senha.length < minimumPasswordLength) {
                errorMessage = 'A senha deve ter no mínimo ' + minimumPasswordLength + ' caracteres.';
                isValid = false;
                invalidField = senhaField;
            }

            if (!isValid) {
                event.preventDefault();

                if (invalidField) {
                    invalidField.setAttribute('aria-invalid', 'true');
                }

                var alert = showAlert('error', errorMessage, true);
                if (alert) {
                    alert.scrollIntoView({
                        behavior: reducedMotionQuery && reducedMotionQuery.matches ? 'auto' : 'smooth',
                        block: 'center'
                    });
                }
                return;
            }

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.classList.add('is-loading');
                submitButton.setAttribute('aria-busy', 'true');
            }
            if (buttonText) {
                buttonText.textContent = 'Criando sua conta...';
            }
            if (loadingSpinner) {
                loadingSpinner.style.display = 'block';
            }
        });

        updateProgress();

        try {
            var urlParams = new URLSearchParams(window.location.search);
            var successMessage = urlParams.get('success');
            var errorMessage = urlParams.get('error');

            if (successMessage) {
                showAlert('success', successMessage, false);
            }
            if (errorMessage) {
                showAlert('error', errorMessage, false);
            }
        } catch (error) {
            // Query-string feedback is a progressive enhancement.
        }
    }

    function init() {
        initTheme();
        initRegisterForm();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
