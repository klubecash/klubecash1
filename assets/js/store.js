/**
 * JavaScript para funcionalidades da área da loja
 * Klube Cash
 */

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar qualquer funcionalidade específica da loja
    initModals();
    initTransactionForms();
    initBatchUpload();
    initPaymentForms();
});

/**
 * Inicializa os modais utilizados na área da loja
 */
function initModals() {
    // Processa os modais na página
    const modals = document.querySelectorAll('.modal');
    const closeBtns = document.querySelectorAll('.modal .close');
    
    // Fechar modais ao clicar no botão fechar
    closeBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
            }
        });
    });
    
    // Fechar modais ao clicar fora deles
    window.addEventListener('click', function(event) {
        modals.forEach(modal => {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        });
    });
}

/**
 * Inicializa os formulários de registro de transação
 */
function initTransactionForms() {
    const transactionForm = document.getElementById('transactionForm');
    if (!transactionForm) return;
    
    // Campos do formulário
    const valorTotal = document.getElementById('valor_total');
    const emailCliente = document.getElementById('email_cliente');
    const calcCashback = document.getElementById('calc_cashback');
    const calcComissao = document.getElementById('calc_comissao');
    const calcTotal = document.getElementById('calc_total');
    
    // Calcular valores de cashback e comissão ao digitar
    valorTotal?.addEventListener('input', function() {
        calcularValores(this.value);
    });
    
    // Função para calcular valores
    function calcularValores(valor) {
        if (!valor || isNaN(valor) || valor <= 0) {
            // Limpar calculadora
            if (calcCashback) calcCashback.textContent = '0,00';
            if (calcComissao) calcComissao.textContent = '0,00';
            if (calcTotal) calcTotal.textContent = '0,00';
            return;
        }
        
        // Converter para número
        const valorNumerico = parseFloat(valor);
        
        // Calcular valores (10% total, 5% cliente, 5% admin)
        const valorTotal = valorNumerico * 0.1;
        const valorCashback = valorNumerico * 0.05;
        const valorComissao = valorNumerico * 0.05;
        
        // Atualizar exibição
        if (calcCashback) calcCashback.textContent = valorCashback.toFixed(2).replace('.', ',');
        if (calcComissao) calcComissao.textContent = valorComissao.toFixed(2).replace('.', ',');
        if (calcTotal) calcTotal.textContent = valorTotal.toFixed(2).replace('.', ',');
    }
    
    // Validação do formulário
    transactionForm.addEventListener('submit', function(e) {
        let valid = true;
        let message = '';
        
        // Validar valor
        if (!valorTotal.value || isNaN(valorTotal.value) || parseFloat(valorTotal.value) <= 0) {
            valid = false;
            message = 'Por favor, informe um valor válido para a transação.';
        }
        
        // Validar email
        if (!emailCliente.value || !validateEmail(emailCliente.value)) {
            valid = false;
            message = 'Por favor, informe um email válido do cliente.';
        }
        
        if (!valid) {
            e.preventDefault();
            alert(message);
        }
    });
    
    // Função para validar email
    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(String(email).toLowerCase());
    }
}

/**
 * Inicializa a funcionalidade de upload em lote
 */
function initBatchUpload() {
    const batchForm = document.getElementById('batchUploadForm');
    const fileInput = document.getElementById('batchFile');
    const preview = document.getElementById('filePreview');
    
    if (!batchForm || !fileInput) return;
    
    // Exibir preview quando arquivo for selecionado
    fileInput.addEventListener('change', function() {
        if (!this.files || !this.files[0]) {
            if (preview) preview.innerHTML = '<p>Nenhum arquivo selecionado</p>';
            return;
        }
        
        const file = this.files[0];
        
        // Verificar tipo de arquivo
        if (file.type !== 'text/csv' && !file.name.endsWith('.csv')) {
            if (preview) preview.innerHTML = '<p class="error">Apenas arquivos CSV são permitidos</p>';
            this.value = '';
            return;
        }
        
        // Exibir informações do arquivo
        if (preview) {
            preview.innerHTML = `
                <div class="file-info">
                    <p><strong>Arquivo:</strong> ${file.name}</p>
                    <p><strong>Tamanho:</strong> ${formatFileSize(file.size)}</p>
                </div>
            `;
        }
    });
    
    // Função para formatar tamanho do arquivo
    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' bytes';
        else if (bytes < 1048576) return (bytes / 1024).toFixed(2) + ' KB';
        else return (bytes / 1048576).toFixed(2) + ' MB';
    }
}

/**
 * Inicializa os formulários de pagamento
 */
function initPaymentForms() {
    const paymentForm = document.getElementById('paymentForm');
    const selectAllCheckbox = document.getElementById('selectAll');
    const transactionCheckboxes = document.querySelectorAll('.transaction-checkbox');
    const totalElement = document.getElementById('totalAmount');
    
    if (!paymentForm) return;
    
    // Selecionar/deselecionar todas as transações
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const isChecked = this.checked;
            transactionCheckboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
            calculateTotal();
        });
    }
    
    // Calcular total ao selecionar/deselecionar
    transactionCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', calculateTotal);
    });
    
    // Calcular e exibir total selecionado
    function calculateTotal() {
        let total = 0;
        
        transactionCheckboxes.forEach(checkbox => {
            if (checkbox.checked) {
                const value = parseFloat(checkbox.getAttribute('data-value') || 0);
                total += value;
            }
        });
        
        if (totalElement) {
            totalElement.textContent = total.toFixed(2).replace('.', ',');
        }
    }
    
    // Validação do formulário de pagamento
    paymentForm.addEventListener('submit', function(e) {
        // Verificar se pelo menos uma transação foi selecionada
        let anySelected = false;
        transactionCheckboxes.forEach(checkbox => {
            if (checkbox.checked) anySelected = true;
        });
        
        if (!anySelected) {
            e.preventDefault();
            alert('Por favor, selecione pelo menos uma transação para pagar.');
            return;
        }
        
        // Verificar método de pagamento
        const metodoPagamento = document.querySelector('input[name="metodo_pagamento"]:checked');
        if (!metodoPagamento) {
            e.preventDefault();
            alert('Por favor, selecione um método de pagamento.');
            return;
        }
    });
}

// === VARIÁVEIS GLOBAIS PARA CLIENTE VISITANTE ===
let visitorClientMode = false;
let currentSearchTerm = '';
let currentSearchType = '';

// === FUNÇÃO PARA BUSCAR CLIENTE (ATUALIZADA) ===
function searchClient() {
    const searchTerm = document.getElementById('client-search').value.trim();
    const storeId = window.storeId; // Deve estar definido na página
    
    if (!searchTerm) {
        showAlert('Por favor, informe o email, CPF ou telefone do cliente.', 'warning');
        return;
    }
    
    showLoading('Buscando cliente...');
    
    fetch('/api/store-client-search.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'search_client',
            search_term: searchTerm,
            store_id: storeId
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.status) {
            // Cliente encontrado
            displayClientFound(data.data);
            hideVisitorSection();
        } else {
            // Cliente não encontrado
            displayClientNotFound(data);
            
            // Se pode criar visitante, mostrar opção
            if (data.can_create_visitor) {
                currentSearchTerm = data.search_term;
                currentSearchType = data.search_type;
                showVisitorOption();
            }
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Erro:', error);
        showAlert('Erro ao buscar cliente. Tente novamente.', 'error');
    });
}

// === FUNÇÃO PARA EXIBIR CLIENTE ENCONTRADO (ATUALIZADA) ===
function displayClientFound(clientData) {
    const resultDiv = document.getElementById('client-search-result');
    
    // Determinar o ícone do tipo de cliente
    const typeIcon = clientData.tipo_cliente === 'visitante' ? 'fas fa-user-clock' : 'fas fa-user-check';
    const typeClass = clientData.tipo_cliente === 'visitante' ? 'visitor' : 'complete';
    
    resultDiv.innerHTML = `
        <div class="client-result-card">
            <div class="client-result-header">
                <h4 class="client-result-name">
                    <i class="fas fa-user-check text-success"></i>
                    ${clientData.nome}
                </h4>
                <span class="client-type-badge ${typeClass}">
                    <i class="${typeIcon}"></i>
                    ${clientData.tipo_cliente_label}
                </span>
            </div>
            
            <div class="client-info-grid">
                ${clientData.email ? `
                <div class="client-info-item">
                    <div class="client-info-label">Email</div>
                    <div class="client-info-value">${clientData.email}</div>
                </div>
                ` : ''}
                
                ${clientData.telefone ? `
                <div class="client-info-item">
                    <div class="client-info-label">Telefone</div>
                    <div class="client-info-value">${formatPhone(clientData.telefone)}</div>
                </div>
                ` : ''}
                
                ${clientData.cpf ? `
                <div class="client-info-item">
                    <div class="client-info-label">CPF</div>
                    <div class="client-info-value">${formatCPF(clientData.cpf)}</div>
                </div>
                ` : ''}
                
                <div class="client-info-item">
                    <div class="client-info-label">Saldo Disponível</div>
                    <div class="client-info-value text-success">
                        <i class="fas fa-wallet"></i>
                        R$ ${formatCurrency(clientData.saldo)}
                    </div>
                </div>
                
                <div class="client-info-item">
                    <div class="client-info-label">Total de Compras</div>
                    <div class="client-info-value">${clientData.estatisticas.total_compras}</div>
                </div>
                
                <div class="client-info-item">
                    <div class="client-info-label">Cliente desde</div>
                    <div class="client-info-value">${clientData.data_cadastro}</div>
                </div>
            </div>
        </div>
    `;
    
    resultDiv.style.display = 'block';
    
    // Atualizar campos do formulário
    document.getElementById('cliente_id_hidden').value = clientData.id;
    document.getElementById('cliente_nome_display').value = clientData.nome;
    document.getElementById('client-balance').textContent = formatCurrency(clientData.saldo);
    
    // Atualizar seção de saldo se existir
    updateBalanceSection(clientData.saldo);
}

// === FUNÇÃO PARA EXIBIR CLIENTE NÃO ENCONTRADO ===
function displayClientNotFound(data) {
    const resultDiv = document.getElementById('client-search-result');
    
    resultDiv.innerHTML = `
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            ${data.message}
        </div>
    `;
    
    resultDiv.style.display = 'block';
    
    // Limpar campos do formulário
    document.getElementById('cliente_id_hidden').value = '';
    document.getElementById('cliente_nome_display').value = '';
}

// === FUNÇÃO PARA MOSTRAR OPÇÃO DE CLIENTE VISITANTE ===
function showVisitorOption() {
    const visitorSection = document.getElementById('visitor-client-section');
    if (visitorSection) {
        visitorSection.classList.add('show');
        
        // Preparar o campo de acordo com o tipo de busca
        const visitorPhoneInput = document.getElementById('visitor-phone');
        if (currentSearchType === 'telefone') {
            visitorPhoneInput.value = formatPhone(currentSearchTerm);
            visitorPhoneInput.readOnly = true;
        } else {
            visitorPhoneInput.value = '';
            visitorPhoneInput.readOnly = false;
        }
    }
}

// === FUNÇÃO PARA ESCONDER SEÇÃO DE VISITANTE ===
function hideVisitorSection() {
    const visitorSection = document.getElementById('visitor-client-section');
    if (visitorSection) {
        visitorSection.classList.remove('show');
        
        // Limpar campos
        document.getElementById('visitor-name').value = '';
        document.getElementById('visitor-phone').value = '';
        document.getElementById('visitor-phone').readOnly = false;
    }
}

// === FUNÇÃO PARA CRIAR CLIENTE VISITANTE ===
function createVisitorClient() {
    const nome = document.getElementById('visitor-name').value.trim();
    const telefone = document.getElementById('visitor-phone').value.trim();
    const storeId = window.storeId;
    
    // Validações
    if (!nome || nome.length < 2) {
        showAlert('Nome é obrigatório e deve ter pelo menos 2 caracteres.', 'warning');
        return;
    }
    
    const phoneClean = telefone.replace(/[^0-9]/g, '');
    if (!phoneClean || phoneClean.length < 10) {
        showAlert('Telefone é obrigatório e deve ter pelo menos 10 dígitos.', 'warning');
        return;
    }
    
    showLoading('Criando cliente visitante...');
    
    fetch('/api/store-client-search.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'create_visitor_client',
            nome: nome,
            telefone: phoneClean,
            store_id: storeId
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.status) {
            // Cliente visitante criado com sucesso
            showAlert('Cliente visitante criado com sucesso!', 'success');
            displayClientFound(data.data);
            hideVisitorSection();
            
            // Limpar campo de busca
            document.getElementById('client-search').value = telefone;
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Erro:', error);
        showAlert('Erro ao criar cliente visitante. Tente novamente.', 'error');
    });
}

// === FUNÇÃO PARA CANCELAR CRIAÇÃO DE VISITANTE ===
function cancelVisitorCreation() {
    hideVisitorSection();
    document.getElementById('client-search').focus();
}

// === FUNÇÕES AUXILIARES DE FORMATAÇÃO ===
function formatPhone(phone) {
    if (!phone) return '';
    const cleaned = phone.replace(/[^0-9]/g, '');
    
    if (cleaned.length === 11) {
        return `(${cleaned.slice(0, 2)}) ${cleaned.slice(2, 7)}-${cleaned.slice(7)}`;
    } else if (cleaned.length === 10) {
        return `(${cleaned.slice(0, 2)}) ${cleaned.slice(2, 6)}-${cleaned.slice(6)}`;
    }
    
    return phone;
}

function formatCPF(cpf) {
    if (!cpf) return '';
    const cleaned = cpf.replace(/[^0-9]/g, '');
    
    if (cleaned.length === 11) {
        return `${cleaned.slice(0, 3)}.${cleaned.slice(3, 6)}.${cleaned.slice(6, 9)}-${cleaned.slice(9)}`;
    }
    
    return cpf;
}

function formatCurrency(value) {
    return parseFloat(value || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// === FUNÇÃO PARA ATUALIZAR SEÇÃO DE SALDO ===
function updateBalanceSection(saldo) {
    const balanceSection = document.getElementById('balance-section');
    if (balanceSection && saldo > 0) {
        balanceSection.style.display = 'block';
        
        // Atualizar valor máximo do slider
        const saldoSlider = document.getElementById('valor-saldo-slider');
        const saldoInput = document.getElementById('valor_saldo_usado');
        
        if (saldoSlider && saldoInput) {
            saldoSlider.max = saldo;
            saldoSlider.value = 0;
            saldoInput.value = 0;
        }
    } else if (balanceSection) {
        balanceSection.style.display = 'none';
    }
}

// === FUNÇÃO PARA MOSTRAR ALERTA ===
function showAlert(message, type = 'info') {
    console.log(`[${type.toUpperCase()}] ${message}`);
    
    if (type === 'error') {
        alert('❌ ' + message);
    } else if (type === 'success') {
        alert('✅ ' + message);
    } else if (type === 'warning') {
        alert('⚠️ ' + message);
    } else {
        alert('ℹ️ ' + message);
    }
}

// === FUNÇÕES DE LOADING ===
function showLoading(message = 'Carregando...') {
    console.log('Loading:', message);
}

function hideLoading() {
    console.log('Loading finished');
}

// === INICIALIZAÇÃO ===
document.addEventListener('DOMContentLoaded', function() {
    // Aplicar máscara de telefone no campo de visitante
    const visitorPhoneInput = document.getElementById('visitor-phone');
    if (visitorPhoneInput) {
        visitorPhoneInput.addEventListener('input', function(e) {
            if (!e.target.readOnly) {
                e.target.value = formatPhone(e.target.value);
            }
        });
    }
    
    // Aplicar máscara no campo de busca de cliente
    const clientSearchInput = document.getElementById('client-search');
    if (clientSearchInput) {
        clientSearchInput.addEventListener('input', function(e) {
            const value = e.target.value;
            
            // Aplicar formatação automática conforme o tipo detectado
            if (value.length > 0) {
                const cleaned = value.replace(/[^0-9]/g, '');
                
                if (cleaned.length === 11 && !value.includes('@')) {
                    // Pode ser telefone ou CPF
                    if (cleaned.startsWith('1') || cleaned.startsWith('2') || cleaned.startsWith('3') || 
                        cleaned.startsWith('4') || cleaned.startsWith('5') || cleaned.startsWith('6') || 
                        cleaned.startsWith('7') || cleaned.startsWith('8') || cleaned.startsWith('9')) {
                        // Provável telefone
                        e.target.value = formatPhone(cleaned);
                    }
                }
            }
        });
    }
});