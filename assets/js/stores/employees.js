// assets/js/stores/employees.js - VERSÃO CORRIGIDA

// Variáveis globais
let isEditing = false;

// Função para mostrar modal de funcionário
function showEmployeeModal() {
    isEditing = false;
    document.getElementById('employeeModalTitle').innerHTML = '<i class="fas fa-user-plus"></i> Adicionar Funcionário';
    document.getElementById('employeeForm').reset();
    document.getElementById('employeeId').value = '';
    
    // Tornar senha obrigatória para novo funcionário
    const passwordField = document.getElementById('employeePassword');
    if (passwordField) {
        passwordField.required = true;
        // Mostrar grupo de senha
        const passwordGroup = document.getElementById('passwordGroup');
        if (passwordGroup) {
            passwordGroup.style.display = 'block';
        }
    }
    
    document.getElementById('employeeModal').style.display = 'flex';
}

// Função para esconder modal
function hideEmployeeModal() {
    document.getElementById('employeeModal').style.display = 'none';
}

// Função para editar funcionário
function editEmployee(employeeId) {
    isEditing = true;
    document.getElementById('employeeModalTitle').innerHTML = '<i class="fas fa-edit"></i> Editar Funcionário';
    
    // Tornar senha opcional para edição
    const passwordField = document.getElementById('employeePassword');
    if (passwordField) {
        passwordField.required = false;
        // Ocultar grupo de senha na edição
        const passwordGroup = document.getElementById('passwordGroup');
        if (passwordGroup) {
            passwordGroup.style.display = 'none';
        }
    }
    
    showLoading();
    
    fetch(`../../api/employees.php?id=${employeeId}`)
        .then(response => response.json())
        .then(data => {
            hideLoading();
            
            if (data.status) {
                const employee = data.data.funcionario;
                
                document.getElementById('employeeId').value = employee.id;
                document.getElementById('employeeName').value = employee.nome;
                document.getElementById('employeeEmail').value = employee.email;
                document.getElementById('employeePhone').value = employee.telefone || '';
                document.getElementById('employeeType').value = employee.subtipo_funcionario;
                
                document.getElementById('employeeModal').style.display = 'flex';
            } else {
                showMessage(data.message, 'error');
            }
        })
        .catch(error => {
            hideLoading();
            showMessage('Erro ao carregar dados do funcionário', 'error');
            console.error('Erro:', error);
        });
}

// Função para deletar funcionário
function deleteEmployee(employeeId, employeeName) {
    if (confirm(`Tem certeza que deseja desativar o funcionário "${employeeName}"?`)) {
        showLoading();
        
        fetch(`../../api/employees.php?id=${employeeId}`, {
            method: 'DELETE'
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            
            if (data.status) {
                showMessage(data.message, 'success');
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                showMessage(data.message, 'error');
            }
        })
        .catch(error => {
            hideLoading();
            showMessage('Erro ao desativar funcionário', 'error');
            console.error('Erro:', error);
        });
    }
}

// Função para submeter formulário
function submitEmployeeForm(event) {
    event.preventDefault();
    
    const formData = new FormData(document.getElementById('employeeForm'));
    const data = {};
    
    formData.forEach((value, key) => {
        if (value.trim() !== '') { // Não incluir campos vazios
            data[key] = value;
        }
    });
    
    // Validações básicas
    if (!data.nome || data.nome.length < 3) {
        showMessage('Nome deve ter pelo menos 3 caracteres', 'error');
        return;
    }
    
    if (!data.email || !isValidEmail(data.email)) {
        showMessage('E-mail inválido', 'error');
        return;
    }
    
    if (!data.subtipo_funcionario) {
        showMessage('Selecione o tipo de funcionário', 'error');
        return;
    }
    
    // Validar senha apenas se for novo funcionário ou se senha foi preenchida
    if (!isEditing && (!data.senha || data.senha.length < 8)) {
        showMessage('Senha deve ter pelo menos 8 caracteres', 'error');
        return;
    }
    
    // Se está editando e senha não foi preenchida, remover do objeto
    if (isEditing && (!data.senha || data.senha.trim() === '')) {
        delete data.senha;
    }
    
    showLoading();
    
    const url = isEditing ? `../../api/employees.php?id=${data.id}` : '../../api/employees.php';
    const method = isEditing ? 'PUT' : 'POST';
    
    fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.status) {
            showMessage(data.message, 'success');
            hideEmployeeModal();
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showMessage(data.message, 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showMessage('Erro ao processar solicitação', 'error');
        console.error('Erro:', error);
    });
}

// Função para alternar visibilidade da senha
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    const button = input.nextElementSibling;
    if (!button) return;
    
    const icon = button.querySelector('i');
    if (!icon) return;
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

// Função para limpar filtros
function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('subtipoFilter').value = 'todos';
    document.getElementById('statusFilter').value = 'todos';
    document.getElementById('filtersForm').submit();
}

// Função para validar email
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Função para mostrar loading
function showLoading() {
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) {
        loadingOverlay.style.display = 'flex';
    }
}

// Função para esconder loading
function hideLoading() {
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) {
        loadingOverlay.style.display = 'none';
    }
}

// Função para mostrar mensagens
function showMessage(message, type) {
    const container = document.getElementById('messageContainer');
    if (!container) {
        // Se não existe container, criar um temporário
        const tempDiv = document.createElement('div');
        tempDiv.style.position = 'fixed';
        tempDiv.style.top = '20px';
        tempDiv.style.right = '20px';
        tempDiv.style.zIndex = '9999';
        tempDiv.style.padding = '15px';
        tempDiv.style.borderRadius = '5px';
        tempDiv.style.color = 'white';
        tempDiv.style.background = type === 'success' ? '#28a745' : '#dc3545';
        tempDiv.textContent = message;
        document.body.appendChild(tempDiv);
        
        setTimeout(() => {
            document.body.removeChild(tempDiv);
        }, 5000);
        return;
    }
    
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const icon = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle';
    
    container.innerHTML = `
        <div class="alert ${alertClass}">
            <i class="${icon}"></i>
            ${message}
        </div>
    `;
    
    // Remover mensagem após 5 segundos
    setTimeout(() => {
        container.innerHTML = '';
    }, 5000);
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Fechar modal ao clicar fora
    const modal = document.getElementById('employeeModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                hideEmployeeModal();
            }
        });
    }
    
    // Submissão automática de filtros
    const subtipoFilter = document.getElementById('subtipoFilter');
    if (subtipoFilter) {
        subtipoFilter.addEventListener('change', function() {
            document.getElementById('filtersForm').submit();
        });
    }
    
    const statusFilter = document.getElementById('statusFilter');
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            document.getElementById('filtersForm').submit();
        });
    }
});