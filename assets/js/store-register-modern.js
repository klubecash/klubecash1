(function () {
    'use strict';

    const THEME_KEY = 'klubecash-theme';
    const FORM_STORAGE_KEY = 'storeRegistrationForm';
    const SENSITIVE_FIELDS = ['senha', 'confirma_senha'];
    const totalSteps = 7;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    const body = document.body;
    const form = document.getElementById('store-form');

    if (!body || !body.classList.contains('store-registration-page') || !form) {
        return;
    }

    let currentStep = 1;
    let stepTimer = 0;
    const formData = {};
    const steps = Array.from(document.querySelectorAll('.step'));
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');
    const progressFill = document.getElementById('progressFill');
    const progressBar = progressFill ? progressFill.parentElement : null;
    const progressSteps = Array.from(document.querySelectorAll('.progress-step'));
    const formStatus = document.getElementById('formStatus');

    function safeReadStorage(key) {
        try {
            return window.localStorage.getItem(key);
        } catch (error) {
            return null;
        }
    }

    function safeWriteStorage(key, value) {
        try {
            window.localStorage.setItem(key, value);
            return true;
        } catch (error) {
            return false;
        }
    }

    function safeRemoveStorage(key) {
        try {
            window.localStorage.removeItem(key);
        } catch (error) {
            // O formulário continua funcional quando o armazenamento é bloqueado.
        }
    }

    function preferredTheme() {
        const savedTheme = safeReadStorage(THEME_KEY);
        if (savedTheme === 'light' || savedTheme === 'dark') {
            return savedTheme;
        }

        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function applyTheme(theme, persist) {
        const normalizedTheme = theme === 'dark' ? 'dark' : 'light';
        document.documentElement.dataset.theme = normalizedTheme;
        document.documentElement.style.colorScheme = normalizedTheme;

        const themeColor = document.querySelector('meta[name="theme-color"]');
        if (themeColor) {
            themeColor.setAttribute('content', normalizedTheme === 'dark' ? '#0B0D12' : '#FFF8F3');
        }

        const toggle = document.getElementById('themeToggle');
        if (toggle) {
            const isDark = normalizedTheme === 'dark';
            toggle.setAttribute('aria-pressed', String(isDark));
            toggle.setAttribute('aria-label', isDark ? 'Ativar modo claro' : 'Ativar modo noturno');
        }

        if (persist) {
            safeWriteStorage(THEME_KEY, normalizedTheme);
        }
    }

    function setupTheme() {
        applyTheme(preferredTheme(), false);

        const toggle = document.getElementById('themeToggle');
        if (toggle) {
            toggle.addEventListener('click', function () {
                const nextTheme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
                applyTheme(nextTheme, true);
            });
        }

        const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');
        const followSystemTheme = function (event) {
            if (!safeReadStorage(THEME_KEY)) {
                applyTheme(event.matches ? 'dark' : 'light', false);
            }
        };

        if (typeof systemTheme.addEventListener === 'function') {
            systemTheme.addEventListener('change', followSystemTheme);
        }

        window.addEventListener('storage', function (event) {
            if (event.key === THEME_KEY && (event.newValue === 'light' || event.newValue === 'dark')) {
                applyTheme(event.newValue, false);
            }
        });
    }

    function announce(message) {
        if (!formStatus) return;
        formStatus.textContent = '';
        window.requestAnimationFrame(function () {
            formStatus.textContent = message;
        });
    }

    function saveDataToLocalStorage() {
        const data = new FormData(form);
        const object = {};

        data.forEach(function (value, key) {
            if (!(value instanceof File) && !SENSITIVE_FIELDS.includes(key)) {
                object[key] = value;
            }
        });

        form.querySelectorAll('[name]:disabled').forEach(function (field) {
            if (field.type !== 'file' && !SENSITIVE_FIELDS.includes(field.name)) {
                object[field.name] = field.type === 'checkbox' ? field.checked : field.value;
            }
        });

        safeWriteStorage(FORM_STORAGE_KEY, JSON.stringify(object));
    }

    function loadDataFromLocalStorage() {
        const savedData = safeReadStorage(FORM_STORAGE_KEY);
        if (!savedData) return;

        let data;
        try {
            data = JSON.parse(savedData);
        } catch (error) {
            safeRemoveStorage(FORM_STORAGE_KEY);
            return;
        }

        if (!data || typeof data !== 'object' || Array.isArray(data)) return;

        let removedSensitiveData = false;
        SENSITIVE_FIELDS.forEach(function (fieldName) {
            if (Object.prototype.hasOwnProperty.call(data, fieldName)) {
                delete data[fieldName];
                removedSensitiveData = true;
            }
        });

        if (removedSensitiveData) {
            safeWriteStorage(FORM_STORAGE_KEY, JSON.stringify(data));
        }

        Object.keys(data).forEach(function (key) {
            const field = form.elements.namedItem(key);
            if (!field || field.type === 'file') return;

            if (field.type === 'checkbox') {
                field.checked = Boolean(data[key]);
            } else {
                field.value = String(data[key]);
            }
        });
    }

    function clearDataFromLocalStorage() {
        safeRemoveStorage(FORM_STORAGE_KEY);
    }

    function updateProgress(step) {
        const progress = (step / totalSteps) * 100;

        if (progressFill) {
            progressFill.style.width = progress + '%';
        }

        if (progressBar) {
            progressBar.setAttribute('aria-valuenow', String(step));
            progressBar.setAttribute('aria-valuetext', step + ' de ' + totalSteps);
        }

        progressSteps.forEach(function (progressStep, index) {
            const current = index + 1 === step;
            const completed = index + 1 < step;
            progressStep.classList.toggle('active', current);
            progressStep.classList.toggle('completed', completed);

            if (current) {
                progressStep.setAttribute('aria-current', 'step');
                if (window.innerWidth <= 920) {
                    const progressTrack = progressStep.parentElement;
                    const targetLeft = progressStep.offsetLeft
                        - ((progressTrack.clientWidth - progressStep.offsetWidth) / 2);

                    if (typeof progressTrack.scrollTo === 'function') {
                        progressTrack.scrollTo({
                            left: Math.max(0, targetLeft),
                            behavior: reducedMotion.matches ? 'auto' : 'smooth'
                        });
                    } else {
                        progressTrack.scrollLeft = Math.max(0, targetLeft);
                    }
                }
            } else {
                progressStep.removeAttribute('aria-current');
            }
        });
    }

    function updateNavigationButtons(step) {
        if (prevBtn) prevBtn.hidden = step <= 1;
        if (nextBtn) nextBtn.hidden = step >= totalSteps;
        if (submitBtn) submitBtn.hidden = step !== totalSteps;
    }

    function activateStep(step, shouldFocus) {
        steps.forEach(function (stepElement, index) {
            const active = index === step - 1;
            stepElement.classList.remove('exiting');
            stepElement.classList.toggle('active', active);
            stepElement.setAttribute('aria-hidden', String(!active));
            stepElement.toggleAttribute('inert', !active);
        });

        if (shouldFocus) {
            const firstInput = steps[step - 1].querySelector('input, select, textarea');
            if (firstInput) firstInput.focus({ preventScroll: false });
        }
    }

    function showStep(step, shouldFocus) {
        window.clearTimeout(stepTimer);

        steps.forEach(function (stepElement) {
            if (stepElement.classList.contains('active')) {
                stepElement.classList.add('exiting');
            }
            stepElement.classList.remove('active');
            stepElement.setAttribute('aria-hidden', 'true');
        });

        updateProgress(step);
        updateNavigationButtons(step);

        stepTimer = window.setTimeout(function () {
            activateStep(step, shouldFocus);
        }, reducedMotion.matches ? 0 : 140);

        if (step === totalSteps) {
            fillSummary();
        }
    }

    function nextStep() {
        if (!validateCurrentStep()) return;
        saveCurrentStepData();

        if (currentStep < totalSteps) {
            currentStep += 1;
            showStep(currentStep, true);
        }
    }

    function prevStep() {
        if (currentStep > 1) {
            currentStep -= 1;
            showStep(currentStep, true);
        }
    }

    function validateCurrentStep() {
        const currentStepElement = steps[currentStep - 1];
        const requiredFields = Array.from(currentStepElement.querySelectorAll('[required]'));
        let isValid = true;
        let firstInvalidField = null;

        requiredFields.forEach(function (field) {
            if (!String(field.value).trim()) {
                showFieldError(field, 'Este campo é obrigatório');
                firstInvalidField = firstInvalidField || field;
                isValid = false;
            } else {
                clearFieldError(field);

                if (field.type === 'email' && !isValidEmail(field.value)) {
                    showFieldError(field, 'Email inválido');
                    firstInvalidField = firstInvalidField || field;
                    isValid = false;
                }
            }
        });

        if (currentStep === 4) {
            const senha = document.getElementById('senha');
            const confirmaSenha = document.getElementById('confirma_senha');

            if (senha.value !== confirmaSenha.value) {
                showFieldError(confirmaSenha, 'As senhas não coincidem');
                firstInvalidField = firstInvalidField || confirmaSenha;
                isValid = false;
            }

            if (senha.value.length < 8) {
                showFieldError(senha, 'A senha deve ter pelo menos 8 caracteres');
                firstInvalidField = firstInvalidField || senha;
                isValid = false;
            }
        }

        if (currentStep === 6) {
            const termos = document.getElementById('aceite_termos');
            if (!termos.checked) {
                termos.setAttribute('aria-invalid', 'true');
                window.alert('Você precisa aceitar os termos e condições para continuar');
                termos.focus();
                isValid = false;
            } else {
                termos.removeAttribute('aria-invalid');
            }
        }

        if (!isValid && firstInvalidField) {
            firstInvalidField.focus();
        }

        return isValid;
    }

    function saveCurrentStepData() {
        const inputs = steps[currentStep - 1].querySelectorAll('input, select, textarea');
        inputs.forEach(function (input) {
            if (input.type !== 'file') {
                formData[input.name] = input.type === 'checkbox' ? input.checked : input.value;
            }
        });
    }

    function createSummaryItem(label, value) {
        const item = document.createElement('div');
        const labelElement = document.createElement('div');
        const valueElement = document.createElement('div');

        item.className = 'summary-item';
        labelElement.className = 'summary-label';
        valueElement.className = 'summary-value';
        labelElement.textContent = label;
        valueElement.textContent = value;
        item.append(labelElement, valueElement);
        return item;
    }

    function fillSummary() {
        const summaryContent = document.getElementById('summaryContent');
        if (!summaryContent) return;

        const valueFor = function (name) {
            const field = document.getElementById(name);
            return formData[name] || (field ? field.value : '');
        };

        const endereco = valueFor('logradouro') + ', ' + valueFor('numero') + ' - ' + valueFor('cidade') + '/' + valueFor('estado');
        const summaryItems = [
            ['Empresa', valueFor('nome_fantasia')],
            ['CNPJ', valueFor('cnpj')],
            ['Email', valueFor('email')],
            ['Telefone', valueFor('telefone')],
            ['Categoria', valueFor('categoria')],
            ['Endereço', endereco]
        ];

        summaryContent.replaceChildren();
        summaryItems.forEach(function (item) {
            summaryContent.appendChild(createSummaryItem(item[0], item[1]));
        });
    }

    function setupInputMasks() {
        document.getElementById('cnpj').addEventListener('input', function (event) {
            let value = event.target.value.replace(/\D/g, '');

            if (value.length <= 14) {
                value = value.replace(/^(\d{2})(\d)/, '$1.$2');
                value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
                value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
                value = value.replace(/(\d{4})(\d)/, '$1-$2');
            }

            event.target.value = value;
        });

        document.getElementById('telefone').addEventListener('input', function (event) {
            let value = event.target.value.replace(/\D/g, '');

            if (value.length <= 11) {
                if (value.length > 2) {
                    value = '(' + value.substring(0, 2) + ') ' + value.substring(2);
                }
                if (value.length > 10) {
                    value = value.substring(0, 10) + '-' + value.substring(10);
                }
            }

            event.target.value = value;
        });

        document.getElementById('cep').addEventListener('input', function (event) {
            let value = event.target.value.replace(/\D/g, '');
            if (value.length > 5) {
                value = value.replace(/^(\d{5})(\d)/, '$1-$2');
            }
            event.target.value = value;
        });
    }

    function setupLogoUpload() {
        const logoInput = document.getElementById('logo');
        const uploadContainer = document.getElementById('logoUploadContainer');
        const preview = document.getElementById('logoPreview');
        const previewImg = document.getElementById('logoPreviewImg');

        if (!logoInput || !uploadContainer || !preview || !previewImg) return;

        function validateLogoFile(file) {
            if (file.size > 2 * 1024 * 1024) {
                window.alert('Arquivo muito grande! O tamanho máximo é 2MB.');
                return false;
            }

            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                window.alert('Tipo de arquivo não permitido! Use apenas JPG, PNG ou GIF.');
                return false;
            }

            return true;
        }

        function showLogoPreview(file) {
            if (!validateLogoFile(file)) return false;

            const reader = new FileReader();
            reader.addEventListener('load', function (event) {
                previewImg.src = event.target.result;
                preview.classList.add('active');
                uploadContainer.classList.add('has-file');
            });
            reader.readAsDataURL(file);
            return true;
        }

        uploadContainer.addEventListener('click', function (event) {
            if (event.target === logoInput) return;
            logoInput.click();
        });

        uploadContainer.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                logoInput.click();
            }
        });

        uploadContainer.addEventListener('dragover', function (event) {
            event.preventDefault();
            uploadContainer.classList.add('is-dragover');
        });

        uploadContainer.addEventListener('dragleave', function (event) {
            event.preventDefault();
            uploadContainer.classList.remove('is-dragover');
        });

        uploadContainer.addEventListener('drop', function (event) {
            event.preventDefault();
            uploadContainer.classList.remove('is-dragover');

            const droppedFile = event.dataTransfer && event.dataTransfer.files[0];
            if (!droppedFile || !validateLogoFile(droppedFile)) return;

            try {
                const transfer = new DataTransfer();
                transfer.items.add(droppedFile);
                logoInput.files = transfer.files;
            } catch (error) {
                try {
                    logoInput.files = event.dataTransfer.files;
                } catch (assignmentError) {
                    return;
                }
            }

            showLogoPreview(droppedFile);
        });

        logoInput.addEventListener('change', function (event) {
            if (event.target.files.length > 0) {
                showLogoPreview(event.target.files[0]);
            }
        });
    }

    function setupCepSearch() {
        const cepInput = document.getElementById('cep');
        const logradouroInput = document.getElementById('logradouro');
        const bairroInput = document.getElementById('bairro');
        const cidadeInput = document.getElementById('cidade');
        const estadoInput = document.getElementById('estado');
        const cepMsgElement = document.getElementById('cep_msg');

        if (cepMsgElement && !cepMsgElement.dataset.originalText) {
            cepMsgElement.dataset.originalText = cepMsgElement.textContent;
        }

        function clearAddressFields() {
            logradouroInput.value = '';
            bairroInput.value = '';
            cidadeInput.value = '';
            estadoInput.value = '';
        }

        cepInput.addEventListener('blur', function () {
            const cep = this.value.replace(/\D/g, '');

            if (cep.length !== 8) {
                clearAddressFields();
                if (cep.length > 0) {
                    showFieldError(cepInput, 'CEP inválido. Digite 8 dígitos.');
                } else {
                    clearFieldError(cepInput);
                }
                return;
            }

            cepInput.classList.add('loading-cep');
            cepInput.disabled = true;
            cepInput.setAttribute('aria-busy', 'true');
            showFieldError(cepInput, 'Buscando CEP...', 'info');

            fetch('https://viacep.com.br/ws/' + cep + '/json/')
                .then(function (response) {
                    if (!response.ok) throw new Error('Erro de rede ou servidor ViaCEP');
                    return response.json();
                })
                .then(function (data) {
                    if (!data.erro) {
                        logradouroInput.value = data.logradouro || '';
                        bairroInput.value = data.bairro || '';
                        cidadeInput.value = data.localidade || '';
                        estadoInput.value = data.uf || '';
                        clearFieldError(cepInput);
                        saveDataToLocalStorage();

                        if (data.logradouro) {
                            document.getElementById('numero').focus();
                        }
                    } else {
                        clearAddressFields();
                        showFieldError(cepInput, 'CEP não encontrado. Verifique se o CEP está correto.');
                    }
                })
                .catch(function (error) {
                    console.error('Erro ao buscar CEP:', error);
                    clearAddressFields();
                    showFieldError(cepInput, 'Erro ao buscar CEP. Verifique sua conexão ou tente novamente.');
                })
                .finally(function () {
                    cepInput.classList.remove('loading-cep');
                    cepInput.disabled = false;
                    cepInput.removeAttribute('aria-busy');
                    if (!cepInput.classList.contains('error')) {
                        clearFieldError(cepInput);
                    }
                });
        });
    }

    function setupPasswordValidation() {
        const senha = document.getElementById('senha');
        const confirmaSenha = document.getElementById('confirma_senha');

        function validatePasswords() {
            if (confirmaSenha.value.length === 0) {
                clearFieldError(confirmaSenha);
                return true;
            }

            if (senha.value !== confirmaSenha.value) {
                showFieldError(confirmaSenha, 'As senhas não coincidem');
                return false;
            }

            clearFieldError(confirmaSenha);
            return true;
        }

        confirmaSenha.addEventListener('input', validatePasswords);
        senha.addEventListener('input', validatePasswords);
    }

    function setupRealtimeValidation() {
        const inputs = document.querySelectorAll('.form-input, .form-select');

        inputs.forEach(function (input) {
            const message = input.parentNode.querySelector('.validation-message');
            if (message) {
                if (!message.id && input.id) message.id = input.id + '_message';
                if (message.id) input.setAttribute('aria-describedby', message.id);
            }

            input.addEventListener('blur', function () {
                if (this.hasAttribute('required') && !this.value.trim()) {
                    showFieldError(this, 'Este campo é obrigatório');
                } else {
                    clearFieldError(this);

                    if (this.type === 'email' && this.value && !isValidEmail(this.value)) {
                        showFieldError(this, 'Email inválido');
                    }

                    if (this.id === 'website' && this.value && !isValidWebsite(this.value)) {
                        showFieldError(this, 'Website inválido. Use o formato: exemplo.com.br ou https://exemplo.com.br');
                    }
                }
            });

            input.addEventListener('input', function () {
                if (this.classList.contains('error')) clearFieldError(this);
            });
        });
    }

    function handleFormSubmit(event) {
        if (!validateCurrentStep()) {
            event.preventDefault();
            return;
        }

        submitBtn.classList.add('loading');
        submitBtn.disabled = true;
        submitBtn.setAttribute('aria-busy', 'true');
    }

    function showFieldError(field, message, type) {
        const statusType = type || 'error';
        const msgElement = field.parentNode.querySelector('.validation-message');

        if (msgElement) {
            if (!Object.prototype.hasOwnProperty.call(msgElement.dataset, 'originalText')) {
                msgElement.dataset.originalText = msgElement.textContent;
            }
            msgElement.textContent = message;
            msgElement.classList.remove('error', 'success', 'info');
            msgElement.classList.add(statusType);
        }

        if (statusType === 'error') {
            field.classList.add('error');
            field.classList.remove('valid');
            field.setAttribute('aria-invalid', 'true');
        } else {
            field.classList.remove('error');
            field.removeAttribute('aria-invalid');
        }

        announce(message);
    }

    function clearFieldError(field) {
        field.classList.remove('error');
        field.classList.add('valid');
        field.removeAttribute('aria-invalid');

        const msgElement = field.parentNode.querySelector('.validation-message');
        if (!msgElement) return;

        msgElement.classList.remove('error', 'success', 'info');
        msgElement.textContent = Object.prototype.hasOwnProperty.call(msgElement.dataset, 'originalText')
            ? msgElement.dataset.originalText
            : '';
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function isValidWebsite(website) {
        if (!website || !website.trim()) return true;
        let normalizedWebsite = website.trim();
        if (!/^https?:\/\//.test(normalizedWebsite)) normalizedWebsite = 'https://' + normalizedWebsite;
        return /^https?:\/\/([\w-]+\.)+[\w-]+(\/.*)?$/.test(normalizedWebsite);
    }

    function setupKeyboardNavigation() {
        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' || event.shiftKey) return;

            const activeElement = document.activeElement;
            if (!activeElement || activeElement.tagName === 'TEXTAREA' || activeElement.type === 'submit') return;
            if (activeElement.closest('button, a, [role="button"]')) return;

            event.preventDefault();
            if (currentStep < totalSteps) nextStep();
        });
    }

    function setupEventListeners() {
        if (nextBtn) nextBtn.addEventListener('click', nextStep);
        if (prevBtn) prevBtn.addEventListener('click', prevStep);
        setupInputMasks();
        setupLogoUpload();
        setupCepSearch();
        setupPasswordValidation();
        setupRealtimeValidation();
        setupKeyboardNavigation();
        form.addEventListener('submit', handleFormSubmit);
        form.addEventListener('input', saveDataToLocalStorage);
        form.addEventListener('change', saveDataToLocalStorage);
    }

    setupTheme();

    if (body.dataset.formSubmitted === 'true') {
        clearDataFromLocalStorage();
    }

    loadDataFromLocalStorage();
    setupEventListeners();
    showStep(currentStep, false);
}());
