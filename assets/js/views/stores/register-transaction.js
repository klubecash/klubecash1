// ========================================
// VARIÁVEIS GLOBAIS
// ========================================

let currentStep = 1;
let clientData = null;
let clientBalance = 0;

// Detectar store_id dinamicamente
function getStoreId() {
    // Você pode passar o storeId via data attribute no HTML ou definir globalmente
    const storeIdElement = document.getElementById('storeIdData');
    return storeIdElement ? parseInt(storeIdElement.value) : 1; // fallback para 1
}

// ========================================
// INICIALIZAÇÃO
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    initializeEventListeners();
    updateProgressBar();
});

function initializeEventListeners() {
    // Navegação entre passos
    document.getElementById('nextToStep2').addEventListener('click', () => goToStep(2));
    document.getElementById('nextToStep3').addEventListener('click', () => goToStep(3));
    document.getElementById('nextToStep4').addEventListener('click', () => goToStep(4));
    document.getElementById('backToStep1').addEventListener('click', () => goToStep(1));
    document.getElementById('backToStep2').addEventListener('click', () => goToStep(2));
    document.getElementById('backToStep3').addEventListener('click', () => goToStep(3));

    // Busca de cliente
    document.getElementById('searchClientBtn').addEventListener('click', buscarCliente);
    document.getElementById('search_term').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            buscarCliente();
        }
    });

    // Eventos do formulário
    document.getElementById('valor_total').addEventListener('input', updateSimulation);
    document.getElementById('codigo_transacao').addEventListener('input', updateSummary);
    document.getElementById('generateCodeBtn').addEventListener('click', gerarCodigoTransacao);

    // Eventos de saldo
    document.getElementById('usarSaldoCheck').addEventListener('change', toggleUsarSaldo);
    document.getElementById('valorSaldoUsado').addEventListener('input', updateBalancePreview);
    document.getElementById('usarTodoSaldo').addEventListener('click', () => useBalanceAmount(1));
    document.getElementById('usar50Saldo').addEventListener('click', () => useBalanceAmount(0.5));
    document.getElementById('limparSaldo').addEventListener('click', () => useBalanceAmount(0));

    // Validação do formulário
    document.getElementById('transactionForm').addEventListener('submit', validateForm);
}

// ========================================
// NAVEGAÇÃO ENTRE PASSOS
// ========================================

function goToStep(step) {
    // Validar passo atual antes de prosseguir
    if (step > currentStep && !validateCurrentStep()) {
        return;
    }

    // Esconder todos os cards
    document.querySelectorAll('.step-card').forEach(card => {
        card.classList.remove('active');
    });

    // Mostrar card do passo atual
    document.getElementById(`stepCard${step}`).classList.add('active');

    // Atualizar progresso
    currentStep = step;
    updateProgressBar();

    // Atualizar resumo se for o último passo
    if (step === 4) {
        updateSummary();
    }

    // Scroll para o topo
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function validateCurrentStep() {
    switch (currentStep) {
        case 1:
            if (!clientData) {
                showNotification('Por favor, busque e selecione um cliente primeiro', 'warning');
                return false;
            }
            return true;

        case 2:
            const valorTotal = parseFloat(document.getElementById('valor_total').value) || 0;
            const codigoTransacao = document.getElementById('codigo_transacao').value.trim();

            if (valorTotal <= 0) {
                showNotification('Por favor, informe o valor total da venda', 'warning');
                document.getElementById('valor_total').focus();
                return false;
            }

            if (!codigoTransacao) {
                showNotification('Por favor, informe o código da transação', 'warning');
                document.getElementById('codigo_transacao').focus();
                return false;
            }
            return true;

        case 3:
            return true; // Passo de saldo é opcional

        default:
            return true;
    }
}

function updateProgressBar() {
    const progressLine = document.getElementById('progressLine');
    const progressSteps = document.querySelectorAll('.progress-step');
    const progressLabels = document.querySelectorAll('.progress-label');

    // Calcular porcentagem de progresso
    const progressPercent = ((currentStep - 1) / 3) * 100;
    progressLine.style.width = `${progressPercent}%`;

    // Atualizar status dos passos
    progressSteps.forEach((step, index) => {
        const stepNumber = index + 1;
        step.classList.remove('active', 'completed');
        
        if (stepNumber < currentStep) {
            step.classList.add('completed');
            step.innerHTML = '✓';
        } else if (stepNumber === currentStep) {
            step.classList.add('active');
            step.innerHTML = stepNumber;
        } else {
            step.innerHTML = stepNumber;
        }
    });

    // Atualizar labels
    progressLabels.forEach((label, index) => {
        label.classList.remove('active');
        if (index + 1 === currentStep) {
            label.classList.add('active');
        }
    });
}

// ========================================
// BUSCA DE CLIENTE
// ========================================

async function buscarCliente() {
    const searchTerm = document.getElementById('search_term').value.trim();
    const searchBtn = document.getElementById('searchClientBtn');
    const clientInfoCard = document.getElementById('clientInfoCard');

    if (!searchTerm) {
        showNotification('Por favor, digite um email, CPF ou telefone válido', 'warning');
        return;
    }

    // Estado de loading
    searchBtn.disabled = true;
    searchBtn.querySelector('.btn-text').textContent = 'Buscando...';
    searchBtn.querySelector('.loading-spinner').style.display = 'inline-block';

    try {
        const response = await fetch('../../api/store-client-search.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'search_client',
                search_term: searchTerm,
                store_id: getStoreId()
            })
        });

        const data = await response.json();

        if (data.status) {
            clientData = data.data;
            clientBalance = data.data.saldo || 0;
            mostrarInfoCliente(data.data);
            hideVisitorSection(); // Esconder seção de visitante
            document.getElementById('nextToStep2').disabled = false;
        } else {
            mostrarErroCliente(data.message);
            
            // Mostrar opção de criar visitante se disponível
            if (data.can_create_visitor) {
                currentSearchTerm = data.search_term;
                currentSearchType = data.search_type;
                showVisitorOption();
            }
            
            document.getElementById('nextToStep2').disabled = true;
        }
    } catch (error) {
        console.error('Erro ao buscar cliente:', error);
        mostrarErroCliente('Erro ao buscar cliente. Tente novamente.');
        document.getElementById('nextToStep2').disabled = true;
    } finally {
        searchBtn.disabled = false;
        searchBtn.querySelector('.btn-text').textContent = 'Buscar Cliente';
        searchBtn.querySelector('.loading-spinner').style.display = 'none';
    }
}

function mostrarInfoCliente(client) {
    const clientInfoCard = document.getElementById('clientInfoCard');
    const clientInfoTitle = document.getElementById('clientInfoTitle');
    const clientInfoDetails = document.getElementById('clientInfoDetails');

    clientInfoCard.className = 'client-info-card success';
    clientInfoCard.style.display = 'block';
    clientInfoTitle.textContent = '✅ Cliente Encontrado';

    // Determinar tipo e ícone
    let typeIcon = '👤';
    let typeLabel = 'Cliente';
    let statusMessage = '';

    if (client.tipo_cliente === 'cadastrado') {
        typeIcon = '🏆';
        typeLabel = 'Cliente Cadastrado';
    } else if (client.tipo_cliente === 'visitante_proprio') {
        typeIcon = '🏪';
        typeLabel = 'Cliente Visitante';
    } else if (client.tipo_cliente === 'visitante_universal') {
        typeIcon = '🌐';
        typeLabel = 'Cliente Visitante (Universal)';
        statusMessage = client.is_first_purchase_in_store ? 
            '<div class="universal-notice">🎉 Primeira compra nesta loja!</div>' : '';
    }

    // Email simplificado (só mostrar se não for fictício)
    const emailDisplay = (client.email && !client.email.includes('@klubecash.local')) ? 
        `<div class="client-info-row">
            <span class="label">Email:</span>
            <span class="value email-text">${client.email}</span>
        </div>` : '';

    clientInfoDetails.innerHTML = `
        <div class="client-info-compact">
            <div class="client-header">
                <div class="client-name">${client.nome}</div>
                <div class="client-type">${typeIcon} ${typeLabel}</div>
            </div>
            
            <div class="client-details">
                ${emailDisplay}
                
                <div class="client-info-row">
                    <span class="label">Telefone:</span>
                    <span class="value">${formatPhone(client.telefone)}</span>
                </div>
                
                <div class="client-info-row">
                    <span class="label">Saldo:</span>
                    <span class="value saldo-value">
                        ${client.saldo > 0 ? 'R$ ' + formatCurrency(client.saldo) : 'R$ 0,00'}
                    </span>
                </div>
                
                <div class="client-info-row">
                    <span class="label">Compras aqui:</span>
                    <span class="value">${client.estatisticas.total_compras}</span>
                </div>
            </div>
            
            ${statusMessage}
        </div>
    `;

    document.getElementById('cliente_id_hidden').value = client.id;
    showNotification('Cliente encontrado!', 'success');
}

function mostrarErroCliente(message) {
    const clientInfoCard = document.getElementById('clientInfoCard');
    const clientInfoTitle = document.getElementById('clientInfoTitle');
    const clientInfoDetails = document.getElementById('clientInfoDetails');

    clientInfoCard.className = 'client-info-card error';
    clientInfoCard.style.display = 'block';
    clientInfoTitle.textContent = '❌ Cliente Não Encontrado';

    clientInfoDetails.innerHTML = `
        <div class="client-info-item">
            <span class="client-info-value">${message}</span>
        </div>
        <div class="client-info-item">
            <span class="client-info-value">🔍 Verifique se o email/CPF está correto e se o cliente está cadastrado no Klube Cash.</span>
        </div>
    `;

    clientData = null;
    clientBalance = 0;
    document.getElementById('cliente_id_hidden').value = '';
}

// ========================================
// GERENCIAMENTO DE SALDO
// ========================================

function toggleUsarSaldo() {
    const usarSaldoCheck = document.getElementById('usarSaldoCheck');
    const balanceControls = document.getElementById('balanceControls');
    const usarSaldoHidden = document.getElementById('usar_saldo');

    if (usarSaldoCheck.checked) {
        balanceControls.style.display = 'block';
        usarSaldoHidden.value = 'sim';
        
        // Auto-usar todo o saldo disponível
        const valorTotal = parseFloat(document.getElementById('valor_total').value) || 0;
        if (valorTotal > 0 && clientBalance > 0) {
            const maxUsable = Math.min(clientBalance, valorTotal);
            document.getElementById('valorSaldoUsado').value = maxUsable.toFixed(2);
            updateBalancePreview();
        }
    } else {
        balanceControls.style.display = 'none';
        usarSaldoHidden.value = 'nao';
        document.getElementById('valorSaldoUsado').value = 0;
        document.getElementById('valor_saldo_usado_hidden').value = '0';
        updateBalancePreview();
    }
}

function useBalanceAmount(percentage) {
    const valor = clientBalance * percentage;
    document.getElementById('valorSaldoUsado').value = valor.toFixed(2);
    updateBalancePreview();
}

function updateBalancePreview() {
    const valorSaldoUsado = parseFloat(document.getElementById('valorSaldoUsado').value) || 0;
    document.getElementById('valor_saldo_usado_hidden').value = valorSaldoUsado;
    updateSimulation();
}

// ========================================
// GERAÇÃO DE CÓDIGO
// ========================================

function gerarCodigoTransacao() {
    const generateBtn = document.getElementById('generateCodeBtn');
    const codigoInput = document.getElementById('codigo_transacao');

    generateBtn.disabled = true;
    generateBtn.querySelector('.btn-text').textContent = 'Gerando...';

    setTimeout(() => {
        const agora = new Date();
        const ano = agora.getFullYear().toString().slice(-2);
        const mes = String(agora.getMonth() + 1).padStart(2, '0');
        const dia = String(agora.getDate()).padStart(2, '0');
        const hora = String(agora.getHours()).padStart(2, '0');
        const minuto = String(agora.getMinutes()).padStart(2, '0');
        const segundo = String(agora.getSeconds()).padStart(2, '0');
        const random = Math.floor(Math.random() * 100000).toString().padStart(5, '0');

        const codigo = `KC${ano}${mes}${dia}${hora}${minuto}${segundo}${random}`;
        codigoInput.value = codigo;

        generateBtn.disabled = false;
        generateBtn.querySelector('.btn-text').textContent = 'Gerar';

        showNotification('Código gerado com sucesso!', 'success');
    }, 800);
}

// ========================================
// SIMULAÇÃO E RESUMO
// ========================================

function updateSimulation() {
    const valorTotal = parseFloat(document.getElementById('valor_total').value) || 0;
    const usarSaldo = document.getElementById('usar_saldo').value === 'sim';
    const valorSaldoUsado = parseFloat(document.getElementById('valor_saldo_usado_hidden').value) || 0;

    let valorPago = valorTotal;
    if (usarSaldo && valorSaldoUsado > 0) {
        valorPago = Math.max(0, valorTotal - valorSaldoUsado);
    }

    // Atualizar seção de saldo se cliente tem saldo
    if (clientBalance > 0) {
        document.getElementById('balanceSection').style.display = 'block';
        document.getElementById('saldoDisponivel').textContent = 'R$ ' + formatCurrency(clientBalance);
        document.getElementById('maxSaldo').textContent = 'R$ ' + formatCurrency(clientBalance);
        document.getElementById('valorSaldoUsado').max = clientBalance;
    } else {
        document.getElementById('balanceSection').style.display = 'none';
    }
}

function updateSummary() {
    if (!clientData) return;

    const valorTotal = parseFloat(document.getElementById('valor_total').value) || 0;
    const usarSaldo = document.getElementById('usar_saldo').value === 'sim';
    const valorSaldoUsado = parseFloat(document.getElementById('valor_saldo_usado_hidden').value) || 0;
    const codigoTransacao = document.getElementById('codigo_transacao').value;

    let valorPago = valorTotal;
    if (usarSaldo && valorSaldoUsado > 0) {
        valorPago = Math.max(0, valorTotal - valorSaldoUsado);
    }

    // Usar percentuais da configuração PHP (devem ser passados via data attributes)
    const percentualCliente = parseFloat(document.getElementById('percentualCliente').value) || 5.0;
    const percentualAdmin = parseFloat(document.getElementById('percentualAdmin').value) || 5.0;
    const percentualTotal = parseFloat(document.getElementById('percentualTotal').value) || 10.0;
    
    const cashbackCliente = valorPago * (percentualCliente / 100);
    const receitaAdmin = valorPago * (percentualAdmin / 100);
    const comissaoTotal = valorPago * (percentualTotal / 100);

    // Atualizar resumo
    document.getElementById('resumoCliente').textContent = clientData.nome;
    document.getElementById('resumoCodigo').textContent = codigoTransacao || 'Não informado';
    document.getElementById('resumoValorVenda').textContent = 'R$ ' + formatCurrency(valorTotal);
    document.getElementById('resumoValorPago').textContent = 'R$ ' + formatCurrency(valorPago);
    document.getElementById('resumoCashbackCliente').textContent = 'R$ ' + formatCurrency(cashbackCliente);
    document.getElementById('resumoReceitaAdmin').textContent = 'R$ ' + formatCurrency(receitaAdmin);
    document.getElementById('resumoComissaoTotal').textContent = 'R$ ' + formatCurrency(comissaoTotal);

    // Mostrar/esconder linha de saldo usado
    const resumoSaldoRow = document.getElementById('resumoSaldoRow');
    if (usarSaldo && valorSaldoUsado > 0) {
        resumoSaldoRow.style.display = 'flex';
        document.getElementById('resumoSaldoUsado').textContent = 'R$ ' + formatCurrency(valorSaldoUsado);
    } else {
        resumoSaldoRow.style.display = 'none';
    }
}

// ========================================
// UTILITÁRIOS
// ========================================

function formatCurrency(value) {
    return parseFloat(value).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    
    const icons = {
        success: '✅',
        warning: '⚠️',
        error: '❌',
        info: 'ℹ️'
    };

    notification.innerHTML = `
        <span style="font-size: 1.2rem; margin-right: 0.5rem;">${icons[type] || icons.info}</span>
        <span>${message}</span>
    `;

    const colors = {
        success: '#28A745',
        warning: '#FFC107',
        error: '#DC3545',
        info: '#17A2B8'
    };

    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${colors[type] || colors.info};
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        font-weight: 600;
        max-width: 350px;
        animation: slideInRight 0.3s ease-out;
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease-in forwards';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 4000);
}

function validateForm(e) {
    console.log('🔍 VALIDAÇÃO INICIADA');
    console.log('Step atual:', currentStep);
    console.log('Cliente atual:', clientData);
    
    // Só validar se estamos no último step (4)
    if (currentStep !== 4) {
        console.log('❌ Não está no step final, impedindo submissão');
        e.preventDefault();
        showNotification('Complete todos os passos antes de registrar a venda', 'warning');
        return false;
    }
    
    // Validação 1: Cliente selecionado
    if (!clientData) {
        e.preventDefault();
        console.log('❌ Cliente não selecionado');
        showNotification('Por favor, selecione um cliente primeiro', 'error');
        goToStep(1);
        return false;
    }

    // Validação 2: Valor total
    const valorTotalField = document.getElementById('valor_total');
    const valorTotal = parseFloat(valorTotalField.value) || 0;
    
    if (valorTotal <= 0) {
        e.preventDefault();
        console.log('❌ Valor inválido:', valorTotal);
        showNotification('Por favor, informe o valor total da venda', 'error');
        goToStep(2);
        // Focar no campo após mostrar o step
        setTimeout(() => {
            valorTotalField.focus();
            valorTotalField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 300);
        return false;
    }
    
    if (valorTotal < 5) {
        e.preventDefault();
        console.log('❌ Valor menor que mínimo:', valorTotal);
        showNotification('Valor mínimo da venda é R$ 5,00', 'error');
        goToStep(2);
        setTimeout(() => {
            valorTotalField.focus();
            valorTotalField.select();
        }, 300);
        return false;
    }

    // Validação 3: Código da transação
    const codigoTransacaoField = document.getElementById('codigo_transacao');
    const codigoTransacao = codigoTransacaoField.value.trim();
    
    if (!codigoTransacao) {
        e.preventDefault();
        console.log('❌ Código não informado');
        showNotification('Por favor, informe o código da transação', 'error');
        goToStep(2);
        setTimeout(() => {
            codigoTransacaoField.focus();
        }, 300);
        return false;
    }

    console.log('✅ VALIDAÇÃO PASSOU - Enviando formulário');
    showNotification('Registrando venda...', 'info');
    return true;
}

// Adicionar estilos de animação
const animationStyles = document.createElement('style');
animationStyles.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(animationStyles);

// === FUNÇÕES PARA CLIENTE VISITANTE ===
let currentSearchTerm = '';
let currentSearchType = '';

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

async function createVisitorClient() {
    const nome = document.getElementById('visitor-name').value.trim();
    const telefone = document.getElementById('visitor-phone').value.trim();

    // Validações
    if (!nome || nome.length < 2) {
        showNotification('Nome é obrigatório e deve ter pelo menos 2 caracteres.', 'warning');
        return;
    }

    const phoneClean = telefone.replace(/[^0-9]/g, '');
    if (!phoneClean || phoneClean.length < 10) {
        showNotification('Telefone é obrigatório e deve ter pelo menos 10 dígitos.', 'warning');
        return;
    }

    try {
        const response = await fetch('../../api/store-client-search.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'create_visitor_client',
                nome: nome,
                telefone: phoneClean,
                store_id: getStoreId()
            })
        });

        const data = await response.json();

        if (data.status) {
            // Cliente visitante criado com sucesso
            showNotification('Cliente visitante criado com sucesso!', 'success');
            clientData = data.data;
            clientBalance = 0;
            mostrarInfoCliente(data.data);
            hideVisitorSection();
            document.getElementById('nextToStep2').disabled = false;
            
            // Atualizar campo de busca
            document.getElementById('search_term').value = telefone;
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Erro:', error);
        showNotification('Erro ao criar cliente visitante. Tente novamente.', 'error');
    }
}

function cancelVisitorCreation() {
    hideVisitorSection();
    document.getElementById('search_term').focus();
}

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

// === CORREÇÃO PARA VALIDAÇÃO DE CAMPOS OCULTOS ===
function toggleVisitorFieldsRequired(required) {
    const visitorName = document.getElementById('visitor-name');
    const visitorPhone = document.getElementById('visitor-phone');
    
    if (visitorName && visitorPhone) {
        if (required) {
            visitorName.setAttribute('required', 'required');
            visitorPhone.setAttribute('required', 'required');
        } else {
            visitorName.removeAttribute('required');
            visitorPhone.removeAttribute('required');
        }
    }
}

// ATUALIZAR AS FUNÇÕES EXISTENTES:
function showVisitorOption() {
    const visitorSection = document.getElementById('visitor-client-section');
    if (visitorSection) {
        visitorSection.classList.add('show');
        toggleVisitorFieldsRequired(true); // Ativar required quando mostrar
        
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

function hideVisitorSection() {
    const visitorSection = document.getElementById('visitor-client-section');
    if (visitorSection) {
        visitorSection.classList.remove('show');
        toggleVisitorFieldsRequired(false); // Desativar required quando esconder
        
        document.getElementById('visitor-name').value = '';
        document.getElementById('visitor-phone').value = '';
        document.getElementById('visitor-phone').readOnly = false;
    }
}

// GARANTIR QUE OS CAMPOS COMEÇEM SEM REQUIRED
document.addEventListener('DOMContentLoaded', function() {
    toggleVisitorFieldsRequired(false); // Começar sempre sem required
});