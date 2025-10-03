// assets/js/admin/users.js

// Variáveis globais
let currentUserId = null;
let selectedUsers = [];
let availableStores = [];
let isStoreUser = false;
let isEditMode = false;

// Inicialização quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    initializeUserManagement();
});

/**
 * Inicializa o sistema de gerenciamento de usuários
 */
function initializeUserManagement() {
    // Event listeners para filtros
    setupFilterListeners();
    
    // Event listeners para formulários
    setupFormListeners();
    
    // Event listeners para modais
    setupModalListeners();
    
    // Carregar lojas disponíveis
    loadAvailableStores();
    
    // Configurar máscaras de input
    setupInputMasks();
}

/**
 * Configura os event listeners para filtros
 */
function setupFilterListeners() {
    const tipoFilter = document.getElementById('tipoFilter');
    const statusFilter = document.getElementById('statusFilter');
    const searchInput = document.getElementById('searchInput');
    
    if (tipoFilter) {
        tipoFilter.addEventListener('change', applyFilters);
    }
    
    if (statusFilter) {
        statusFilter.addEventListener('change', applyFilters);
    }
    
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(applyFilters, 500);
        });
    }
}

/**
 * Configura os event listeners para formulários
 */
function setupFormListeners() {
    const userTypeSelect = document.getElementById('userType');
    const emailSelect = document.getElementById('userEmailSelect');
    
    if (userTypeSelect) {
        userTypeSelect.addEventListener('change', function() {
            handleUserTypeChange(this.value);
        });
    }
    
    if (emailSelect) {
        emailSelect.addEventListener('change', function() {
            handleStoreEmailChange(this.value);
        });
    }
}

/**
 * Configura os event listeners para modais
 */
function setupModalListeners() {
    // Fechar modal ao clicar fora
    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            hideUserModal();
            hideViewUserModal();
        }
    });
    
    // Fechar modal com ESC
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            hideUserModal();
            hideViewUserModal();
        }
    });
}

/**
 * Configura máscaras de input
 */
function setupInputMasks() {
    const phoneInput = document.getElementById('userPhone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{5})(\d)/, '$1-$2');
            e.target.value = value;
        });
    }
}

/**
 * Aplica filtros à listagem
 */
function applyFilters() {
    const form = document.getElementById('filtersForm');
    if (form) {
        form.submit();
    }
}

/**
 * Limpa todos os filtros
 */
function clearFilters() {
    const url = new URL(window.location);
    url.searchParams.delete('busca');
    url.searchParams.delete('tipo');
    url.searchParams.delete('status');
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

/**
 * Exibe mensagem para o usuário
 */
function showMessage(message, type = 'success') {
    const messageContainer = document.getElementById('messageContainer');
    if (!messageContainer) return;
    
    const alertClass = `alert-${type}`;
    const iconClass = type === 'success' ? 'fa-check-circle' : 
                     type === 'error' ? 'fa-exclamation-triangle' : 
                     type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
    
    messageContainer.innerHTML = `
        <div class="alert ${alertClass}">
            <i class="fas ${iconClass}"></i>
            ${message}
        </div>
    `;
    
    // Rolar para a mensagem
    messageContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    
    // Remover mensagem após 5 segundos
    setTimeout(() => {
        messageContainer.innerHTML = '';
    }, 5000);
}

/**
 * Exibe loading overlay
 */
function showLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.style.display = 'flex';
    }
}

/**
 * Esconde loading overlay
 */
function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
}

/**
 * Carrega lojas disponíveis para vinculação
 */
function loadAvailableStores() {
    fetch('/controllers/AdminController.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_available_stores'
    })
    .then(response => response.json())
    .then(data => {
        if (data.status && data.data) {
            availableStores = data.data;
            populateStoreSelect();
        } else {
            console.warn('Nenhuma loja disponível encontrada');
            availableStores = [];
        }
    })
    .catch(error => {
        console.error('Erro ao carregar lojas:', error);
        availableStores = [];
    });
}

/**
 * Popula o select de lojas
 */
function populateStoreSelect() {
    const select = document.getElementById('userEmailSelect');
    if (!select) return;
    
    select.innerHTML = '<option value="">Selecione uma loja...</option>';
    
    availableStores.forEach(store => {
        const option = document.createElement('option');
        option.value = store.email;
        option.textContent = `${store.nome_fantasia} (${store.email})`;
        option.dataset.storeData = JSON.stringify(store);
        select.appendChild(option);
    });
}

/**
 * Manipula mudança no tipo de usuário
 */
function handleUserTypeChange(type) {
    const isStore = type === 'loja';
    isStoreUser = isStore;

    const emailContainer = document.getElementById('emailSelectContainer');
    const emailInput = document.getElementById('userEmail');
    const storeFields = document.getElementById('storeDataFields');
    const storeMvpField = document.getElementById('storeMvpField');

    if (isStore) {
        if (storeMvpField) storeMvpField.style.display = 'block';

        if (!isEditMode) {
            if (emailContainer) emailContainer.style.display = 'block';
            if (emailInput) {
                emailInput.style.display = 'none';
                emailInput.required = false;
            }
            if (storeFields) storeFields.style.display = 'block';

            if (availableStores.length === 0) {
                loadAvailableStores();
            }
        } else {
            if (emailContainer) emailContainer.style.display = 'none';
            if (emailInput) {
                emailInput.style.display = 'block';
                emailInput.required = true;
                emailInput.readOnly = false;
            }
            if (storeFields) storeFields.style.display = 'none';
        }
    } else {
        if (storeMvpField) storeMvpField.style.display = 'none';
        resetStoreFields();
    }
}

/**
 * Manipula mudança na seleção de loja
 */
function handleStoreEmailChange(email) {
    if (!email) {
        clearStoreFields();
        return;
    }
    
    // Buscar dados da loja selecionada
    fetch('/controllers/AdminController.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_store_by_email&email=${encodeURIComponent(email)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status && data.data) {
            fillStoreFields(data.data);
        } else {
            showMessage(data.message || 'Erro ao carregar dados da loja', 'error');
            clearStoreFields();
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showMessage('Erro ao carregar dados da loja: ' + error.message, 'error');
    });
}

/**
 * Preenche os campos com dados da loja
 */
function fillStoreFields(store) {
    const emailInput = document.getElementById('userEmail');
    const nameInput = document.getElementById('userName');
    const phoneInput = document.getElementById('userPhone');
    const storeNameInput = document.getElementById('storeName');
    const storeDocumentInput = document.getElementById('storeDocument');
    const storeCategoryInput = document.getElementById('storeCategory');
    
    if (emailInput) emailInput.value = store.email;
    if (nameInput) nameInput.value = store.nome_fantasia;
    if (phoneInput) phoneInput.value = store.telefone || '';
    if (storeNameInput) storeNameInput.value = store.nome_fantasia;
    if (storeDocumentInput) storeDocumentInput.value = store.cnpj;
    if (storeCategoryInput) storeCategoryInput.value = store.categoria || 'Nao informado';
    
    // Tornar campos principais read-only
    if (emailInput) emailInput.readOnly = true;
    if (nameInput) nameInput.readOnly = true;
    if (phoneInput) phoneInput.readOnly = true;
}

/**
 * Limpa os campos de loja
 */
function clearStoreFields() {
    const emailInput = document.getElementById('userEmail');
    const nameInput = document.getElementById('userName');
    const phoneInput = document.getElementById('userPhone');
    const storeNameInput = document.getElementById('storeName');
    const storeDocumentInput = document.getElementById('storeDocument');
    const storeCategoryInput = document.getElementById('storeCategory');
    const storeMvpSelect = document.getElementById('storeMvp');

    if (emailInput) emailInput.value = '';
    if (nameInput) nameInput.value = '';
    if (phoneInput) phoneInput.value = '';
    if (storeNameInput) storeNameInput.value = '';
    if (storeDocumentInput) storeDocumentInput.value = '';
    if (storeCategoryInput) storeCategoryInput.value = '';
    if (storeMvpSelect) storeMvpSelect.value = 'nao';

    // Reabilitar edição
    if (emailInput) emailInput.readOnly = false;
    if (nameInput) nameInput.readOnly = false;
    if (phoneInput) phoneInput.readOnly = false;
}

/**
 * Reseta campos relacionados a loja
 */
function resetStoreFields() {
    const emailContainer = document.getElementById('emailSelectContainer');
    const emailInput = document.getElementById('userEmail');
    const storeFields = document.getElementById('storeDataFields');
    const storeMvpField = document.getElementById('storeMvpField');
    const storeMvpSelect = document.getElementById('storeMvp');
    
    if (emailContainer) emailContainer.style.display = 'none';
    if (emailInput) {
        emailInput.style.display = 'block';
        emailInput.required = true;
        emailInput.readOnly = false;
    }
    if (storeFields) storeFields.style.display = 'none';
    if (storeMvpField) storeMvpField.style.display = 'none';
    if (storeMvpSelect) storeMvpSelect.value = 'nao';
    
    clearStoreFields();
    isStoreUser = false;
}

/**
 * Exibe modal de adicionar usuário
 */
function showUserModal() {
    const modal = document.getElementById('userModal');
    const title = document.getElementById('userModalTitle');
    const form = document.getElementById('userForm');
    const passwordGroup = document.getElementById('passwordGroup');
    const passwordField = document.getElementById('userPassword');
    const passwordHelp = document.getElementById('passwordHelp');
    
    if (!modal) return;
    
    // Configurar modal para criação
    if (title) title.innerHTML = '<i class="fas fa-user-plus"></i> Adicionar Usuário';
    if (form) form.reset();
    document.getElementById('userId').value = '';
    currentUserId = null;
    isEditMode = false;
    
    // Configurar campo de senha
    if (passwordGroup) passwordGroup.style.display = 'block';
    if (passwordField) passwordField.required = true;
    if (passwordHelp) passwordHelp.textContent = 'Mínimo de 8 caracteres';
    
    // Resetar campos de loja
    resetStoreFields();
    
    // Mostrar modal
    modal.classList.add('show');
    
    // Focar no primeiro campo
    const firstInput = modal.querySelector('input, select');
    if (firstInput) {
        setTimeout(() => firstInput.focus(), 100);
    }
}

/**
 * Esconde modal de usuário
 */
function hideUserModal() {
    const modal = document.getElementById('userModal');
    if (modal) {
        modal.classList.remove('show');
    }
}

/**
 * Edita usuário
 */
function editUser(userId) {
    if (!userId) return;
    
    currentUserId = userId;
    isEditMode = true;
    
    const modal = document.getElementById('userModal');
    const title = document.getElementById('userModalTitle');
    const form = document.getElementById('userForm');
    const passwordGroup = document.getElementById('passwordGroup');
    const passwordField = document.getElementById('userPassword');
    const passwordHelp = document.getElementById('passwordHelp');
    
    if (!modal) return;
    
    // Configurar modal para edição
    if (title) title.innerHTML = '<i class="fas fa-user-edit"></i> Editar Usuário';
    if (form) form.reset();
    
    // Configurar campo de senha (opcional na edição)
    if (passwordGroup) passwordGroup.style.display = 'block';
    if (passwordField) passwordField.required = false;
    if (passwordHelp) passwordHelp.textContent = 'Mínimo de 8 caracteres (deixe em branco para manter a senha atual)';
    
    // Resetar campos de loja
    resetStoreFields();
    
    // Mostrar modal
    modal.classList.add('show');
    
    // Carregar dados do usuário
    showLoading();
    
    fetch('/controllers/AdminController.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=getUserDetails&user_id=${userId}`
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Erro na requisição: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        hideLoading();
        
        if (data.status && data.data && data.data.usuario) {
            fillUserForm(data.data.usuario);
        } else {
            hideUserModal();
            showMessage(data.message || 'Erro ao carregar dados do usuário', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Erro:', error);
        hideUserModal();
        showMessage('Erro ao carregar dados do usuário: ' + error.message, 'error');
    });
}

/**
 * Preenche o formulário com dados do usuário
 */
function fillUserForm(userData) {
    document.getElementById('userId').value = userData.id;
    document.getElementById('userName').value = userData.nome;
    document.getElementById('userEmail').value = userData.email;
    document.getElementById('userType').value = userData.tipo;
    document.getElementById('userStatus').value = userData.status;
    
    if (userData.telefone) {
        document.getElementById('userPhone').value = userData.telefone;
    }
    
    handleUserTypeChange(userData.tipo);

    if (userData.tipo === 'loja') {
        const storeMvpField = document.getElementById('storeMvpField');
        const storeMvpSelect = document.getElementById('storeMvp');
        if (storeMvpField) storeMvpField.style.display = 'block';
        if (storeMvpSelect) storeMvpSelect.value = (userData.loja_mvp === 'sim') ? 'sim' : 'nao';
    } else {
        const storeMvpField = document.getElementById('storeMvpField');
        if (storeMvpField) storeMvpField.style.display = 'none';
    }
    
    // Limpar campo de senha
    document.getElementById('userPassword').value = '';
}

/**
 * Visualiza detalhes do usuário
 */
function viewUser(userId) {
    if (!userId) return;
    
    const modal = document.getElementById('viewUserModal');
    const content = document.getElementById('userViewContent');
    
    if (!modal || !content) return;
    
    modal.classList.add('show');
    content.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Carregando...</div>';
    
    fetch('/controllers/AdminController.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=getUserDetails&user_id=${userId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status && data.data && data.data.usuario) {
            displayUserDetails(data.data.usuario);
        } else {
            content.innerHTML = '<div class="alert alert-danger">Erro ao carregar dados do usuário</div>';
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        content.innerHTML = '<div class="alert alert-danger">Erro ao carregar dados do usuário</div>';
    });
}

/**
 * Exibe detalhes do usuário
 */
function displayUserDetails(userData) {
    const content = document.getElementById('userViewContent');
    if (!content) return;

    const tipoLabels = {
        'cliente': 'Cliente',
        'loja': 'Loja',
        'admin': 'Administrador'
    };

    const statusLabels = {
        'ativo': 'Ativo',
        'inativo': 'Inativo',
        'bloqueado': 'Bloqueado'
    };

    const statusClass = {
        'ativo': 'badge-success',
        'inativo': 'badge-warning',
        'bloqueado': 'badge-danger'
    };

    const storeNameDetail = userData.tipo === 'loja' && userData.loja_nome
        ? `<div class="detail-item">
                <label>Loja:</label>
                <span>${userData.loja_nome}</span>
           </div>`
        : '';

    const storeMvpDetail = userData.tipo === 'loja'
        ? `<div class="detail-item">
                <label>Loja MVP:</label>
                <span>${userData.loja_mvp === 'sim' ? 'Sim' : 'Nao'}</span>
           </div>`
        : '';

    const linkedStoreDetail = userData.loja_vinculada_nome
        ? `<div class="detail-item">
                <label>Loja Vinculada:</label>
                <span>${userData.loja_vinculada_nome}${userData.loja_vinculada_mvp ? ' • ' + (userData.loja_vinculada_mvp === 'sim' ? 'MVP' : 'Convencional') : ''}</span>
           </div>`
        : '';

    content.innerHTML = `
        <div class="user-details">
            <div class="user-detail-header">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-basic-info">
                    <h4>${userData.nome}</h4>
                    <p>${userData.email}</p>
                </div>
            </div>
            
            <div class="user-detail-grid">
                <div class="detail-item">
                    <label>Tipo:</label>
                    <span class="type-badge type-${userData.tipo}">
                        ${tipoLabels[userData.tipo] || userData.tipo}
                    </span>
                </div>
                ${storeNameDetail}
                ${storeMvpDetail}
                ${linkedStoreDetail}
                <div class="detail-item">
                    <label>Status:</label>
                    <span class="badge ${statusClass[userData.status] || 'badge-secondary'}">
                        ${statusLabels[userData.status] || userData.status}
                    </span>
                </div>
                
                <div class="detail-item">
                    <label>Telefone:</label>
                    <span>${userData.telefone || 'Nao informado'}</span>
                </div>
                
                <div class="detail-item">
                    <label>Data de Cadastro:</label>
                    <span>${new Date(userData.data_criacao).toLocaleString('pt-BR')}</span>
                </div>
                
                <div class="detail-item">
                    <label>Ultimo Login:</label>
                    <span>${userData.ultimo_login ? new Date(userData.ultimo_login).toLocaleString('pt-BR') : 'Nunca'}</span>
                </div>
            </div>
        </div>
    `;
}

}

/**
 * Esconde modal de visualização
 */
function hideViewUserModal() {
    const modal = document.getElementById('viewUserModal');
    if (modal) {
        modal.classList.remove('show');
    }
}

/**
 * Altera status do usuário
 */
function changeUserStatus(userId, newStatus, userName) {
    if (!userId || !newStatus) return;
    
    const actionText = newStatus === 'ativo' ? 'ativar' : 
                      newStatus === 'inativo' ? 'desativar' : 'bloquear';
    
    if (!confirm(`Tem certeza que deseja ${actionText} o usuário "${userName}"?`)) {
        return;
    }
    
    showLoading();
    
    fetch('/controllers/AdminController.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update_user_status&user_id=${userId}&status=${newStatus}`
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.status) {
            showMessage(`Usuário ${actionText} com sucesso!`);
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showMessage(data.message || `Erro ao ${actionText} usuário`, 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Erro:', error);
        showMessage(`Erro ao processar a solicitação: ${error.message}`, 'error');
    });
}

/**
 * Submete formulário de usuário
 */
function submitUserForm(event) {
    event.preventDefault();
    
    const form = document.getElementById('userForm');
    const submitBtn = document.getElementById('submitBtn');
    
    if (!form || !validateForm(form)) {
        return;
    }
    
    const formData = new FormData(form);
    const userId = formData.get('id');
    const isEditing = userId !== '';
    
    // Validação específica para senha
    const senha = formData.get('senha');
    if (!isEditing && (!senha || senha.trim() === '')) {
        showMessage('Senha é obrigatória para criar um novo usuário', 'error');
        document.getElementById('userPassword').focus();
        return;
    }
    
    if (senha && senha.trim() !== '' && senha.length < 8) {
        showMessage('A senha deve ter no mínimo 8 caracteres', 'error');
        document.getElementById('userPassword').focus();
        return;
    }
    
    // Se for usuário do tipo loja, usar email selecionado
    if (isStoreUser && !isEditing) {
        const selectedEmail = document.getElementById('userEmailSelect').value;
        if (selectedEmail) {
            formData.set('email', selectedEmail);
        } else {
            showMessage('Por favor, selecione uma loja antes de continuar.', 'error');
            return;
        }
    }
    
    // Desabilitar botão e mostrar loading
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
    }
    
    // Converter FormData para URLSearchParams
    const data = new URLSearchParams();
    
    if (isEditing) {
        data.append('action', 'update_user');
        data.append('user_id', userId);
    } else {
        data.append('action', 'register');
        data.append('ajax', '1');
    }
    
    // Adicionar dados do formulário
    for (let [key, value] of formData.entries()) {
        if (key !== 'id') {
            data.append(key, value);
        }
    }
    
    const url = isEditing ? '/controllers/AdminController.php' : '/controllers/AuthController.php';
    
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: data
    })
    .then(response => response.text())
    .then(text => {
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error("Resposta inválida:", text);
            throw new Error("Resposta do servidor não é JSON válido");
        }
        return data;
    })
    .then(data => {
        if (data.status) {
            hideUserModal();
            let message = isEditing ? 'Usuário atualizado com sucesso!' : 'Usuário adicionado com sucesso!';
            
            if (data.store_linked) {
                message += ' A loja foi vinculada automaticamente ao usuário.';
            }
            
            showMessage(message);
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showMessage(data.message || 'Erro ao processar solicitação', 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showMessage('Erro ao processar a solicitação: ' + error.message, 'error');
    })
    .finally(() => {
        // Reabilitar botão
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Salvar';
        }
    });
}

/**
 * Valida formulário
 */
function validateForm(form) {
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
        } else {
            field.classList.remove('error');
        }
    });
    
    // Validação específica de email
    const emailField = document.getElementById('userEmail');
    if (emailField && emailField.value && !isValidEmail(emailField.value)) {
        emailField.classList.add('error');
        showMessage('Por favor, insira um email válido', 'error');
        isValid = false;
    }
    
    return isValid;
}

/**
 * Valida email
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Alterna visibilidade da senha
 */
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const toggleBtn = field.nextElementSibling;
    
    if (field.type === 'password') {
        field.type = 'text';
        toggleBtn.innerHTML = '<i class="fas fa-eye-slash"></i>';
    } else {
        field.type = 'password';
        toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
    }
}

/**
 * Seleciona/deseleciona todos os usuários
 */
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.user-checkbox');
    
    selectedUsers = [];
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
        if (selectAll.checked) {
            const userId = parseInt(checkbox.value);
            if (!selectedUsers.includes(userId)) {
                selectedUsers.push(userId);
            }
        }
    });
    
    updateBulkActionBar();
}

/**
 * Alterna seleção de usuário individual
 */
function toggleUserSelection(checkbox, userId) {
    if (checkbox.checked) {
        if (!selectedUsers.includes(userId)) {
            selectedUsers.push(userId);
        }
    } else {
        selectedUsers = selectedUsers.filter(id => id !== userId);
        const selectAllCheckbox = document.getElementById('selectAll');
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = false;
        }
    }
    
    updateBulkActionBar();
}

/**
 * Atualiza barra de ações em massa
 */
function updateBulkActionBar() {
    const bulkActionBar = document.getElementById('bulkActionBar');
    const selectedCount = document.getElementById('selectedCount');
    
    if (!bulkActionBar || !selectedCount) return;
    
    selectedCount.textContent = selectedUsers.length;
    
    if (selectedUsers.length > 0) {
        bulkActionBar.style.display = 'flex';
    } else {
        bulkActionBar.style.display = 'none';
    }
}

/**
 * Executa ação em massa
 */
function bulkAction(status) {
    if (selectedUsers.length === 0) return;
    
    const actionText = status === 'ativo' ? 'ativar' : 
                      status === 'inativo' ? 'desativar' : 'bloquear';
    
    if (!confirm(`Tem certeza que deseja ${actionText} ${selectedUsers.length} usuários selecionados?`)) {
        return;
    }
    
    const bulkActionBar = document.getElementById('bulkActionBar');
    if (bulkActionBar) {
        bulkActionBar.innerHTML = `<div class="bulk-info">Processando ${selectedUsers.length} usuários...</div>`;
    }
    
    let processed = 0;
    let successful = 0;
    
    const processUser = (userId) => {
        return fetch('/controllers/AdminController.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=update_user_status&user_id=${userId}&status=${status}`
        })
        .then(response => response.json())
        .then(data => {
            processed++;
            if (data.status) successful++;
            
            if (processed === selectedUsers.length) {
                const successMessage = `${successful} usuários foram ${actionText === 'ativar' ? 'ativados' : actionText === 'desativar' ? 'desativados' : 'bloqueados'} com sucesso!`;
                showMessage(successMessage);
                setTimeout(() => {
                    location.reload();
                }, 1500);
            }
        })
        .catch(() => {
            processed++;
            if (processed === selectedUsers.length) {
                const successMessage = `${successful} usuários foram processados com sucesso!`;
                showMessage(successMessage);
                setTimeout(() => {
                    location.reload();
                }, 1500);
            }
        });
    };
    
    // Processar todos os usuários selecionados
    selectedUsers.forEach(processUser);
}

/**
 * Exporta usuários
 */
function exportUsers() {
    showMessage('Função de exportação será implementada em breve', 'info');
}

// Adicionar estilos CSS para campos com erro
const style = document.createElement('style');
style.textContent = `
    .form-control.error,
    .form-select.error {
        border-color: var(--danger-color) !important;
        box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1) !important;
    }
    
    .user-details {
        max-width: 500px;
    }
    
    .user-detail-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #E9ECEF;
    }
    
    .user-detail-header .user-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: var(--primary-light);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary-color);
        font-size: 1.5rem;
    }
    
    .user-basic-info h4 {
        margin: 0 0 0.25rem 0;
        color: var(--dark-gray);
        font-size: 1.25rem;
        font-weight: 600;
    }
    
    .user-basic-info p {
        margin: 0;
        color: var(--medium-gray);
        font-size: 0.875rem;
    }
    
    .user-detail-grid {
        display: grid;
        gap: 1rem;
    }
    
    .detail-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 0;
        border-bottom: 1px solid #F8F9FA;
    }
    
    .detail-item:last-child {
        border-bottom: none;
    }
    
    .detail-item label {
        font-weight: 600;
        color: var(--dark-gray);
        margin: 0;
    }
    
    .detail-item span {
        color: var(--medium-gray);
        text-align: right;
    }
`;
document.head.appendChild(style);