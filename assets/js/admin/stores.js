// assets/js/admin/stores.js

// Variáveis globais
let selectedStores = [];
let currentStoreData = null;

// Inicialização quando a página carrega
document.addEventListener('DOMContentLoaded', function() {
    initializeStoresPage();
});

/**
 * Inicializa a página de lojas
 */
function initializeStoresPage() {
    // Inicializar tooltips se necessário
    initializeTooltips();
    
    // Configurar eventos
    setupEventListeners();
    
    // Atualizar contadores se há stores selecionadas
    updateBulkActions();
    
    console.log('Página de lojas inicializada');
}

/**
 * Configura os event listeners
 */
function setupEventListeners() {
    // Checkbox "Selecionar Todos"
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', toggleSelectAll);
    }
    
    // Checkboxes individuais
    const storeCheckboxes = document.querySelectorAll('.store-checkbox');
    storeCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkActions);
    });
    
    // Fechar modais ao clicar fora
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            hideAllModals();
        }
    });
    
    // ESC para fechar modais
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            hideAllModals();
        }
    });
}

/**
 * Alterna seleção de todas as lojas
 */
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.store-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
    
    updateBulkActions();
}

/**
 * Atualiza ações em lote baseado na seleção
 */
function updateBulkActions() {
    const checkboxes = document.querySelectorAll('.store-checkbox:checked');
    const bulkApproveBtn = document.getElementById('bulkApproveBtn');
    const selectAllCheckbox = document.getElementById('selectAll');
    
    selectedStores = Array.from(checkboxes).map(cb => parseInt(cb.value));
    
    // Mostrar/esconder botão de aprovação em lote
    if (bulkApproveBtn) {
        if (selectedStores.length > 0) {
            bulkApproveBtn.style.display = 'inline-flex';
            bulkApproveBtn.textContent = `Aprovar ${selectedStores.length} Selecionada(s)`;
        } else {
            bulkApproveBtn.style.display = 'none';
        }
    }
    
    // Atualizar estado do checkbox "Selecionar Todos"
    if (selectAllCheckbox) {
        const allCheckboxes = document.querySelectorAll('.store-checkbox');
        if (selectedStores.length === 0) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = false;
        } else if (selectedStores.length === allCheckboxes.length) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = true;
        } else {
            selectAllCheckbox.indeterminate = true;
        }
    }
}

/**
 * Visualizar detalhes da loja
 */
function viewStoreDetails(storeId) {
    if (!storeId) {
        showAlert('erro', 'ID da loja não fornecido');
        return;
    }
    
    // Mostrar loading
    const modal = document.getElementById('storeDetailsModal');
    const content = document.getElementById('storeDetailsContent');
    const title = document.getElementById('storeDetailsTitle');
    
    if (!modal || !content) {
        showAlert('erro', 'Modal não encontrado');
        return;
    }
    
    // Resetar conteúdo
    content.innerHTML = '<div class="loading-state">Carregando detalhes da loja...</div>';
    title.textContent = 'Carregando...';
    
    // Mostrar modal
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    // Fazer requisição AJAX
    fetch('../../controllers/AjaxStoreController.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=store_details&store_id=${storeId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status) {
            renderStoreDetails(data.data);
        } else {
            content.innerHTML = `<div class="error-state">Erro: ${data.message}</div>`;
        }
    })
    .catch(error => {
        console.error('Erro ao carregar detalhes da loja:', error);
        content.innerHTML = '<div class="error-state">Erro ao carregar dados da loja.</div>';
    });
}

/**
 * Renderiza os detalhes da loja no modal
 */
function renderStoreDetails(data) {
    const title = document.getElementById('storeDetailsTitle');
    const content = document.getElementById('storeDetailsContent');
    const editBtn = document.getElementById('editStoreBtn');
    
    const loja = data.loja;
    const stats = data.estatisticas;
    
    // Atualizar título
    title.textContent = `Detalhes - ${loja.nome_fantasia}`;
    
    // Renderizar conteúdo
    content.innerHTML = `
        <div class="store-details-grid">
            <div class="detail-section">
                <h4>Informações Básicas</h4>
                <div class="detail-item">
                    <span class="detail-label">Nome Fantasia:</span>
                    <span class="detail-value">${loja.nome_fantasia}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Razão Social:</span>
                    <span class="detail-value">${loja.razao_social}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">CNPJ:</span>
                    <span class="detail-value">${formatCNPJ(loja.cnpj)}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value">${loja.email}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Telefone:</span>
                    <span class="detail-value">${loja.telefone}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Categoria:</span>
                    <span class="detail-value">${loja.categoria || 'Não informada'}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Cashback:</span>
                    <span class="detail-value">${loja.porcentagem_cashback}%</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value">
                        <span class="status-badge status-${loja.status}">
                            ${getStatusText(loja.status)}
                        </span>
                    </span>
                </div>
            </div>
            
            <div class="detail-section">
                <h4>Estatísticas de Cashback</h4>
                <div class="detail-item">
                    <span class="detail-label">Total de Vendas:</span>
                    <span class="detail-value">R$ ${formatMoney(stats.total_vendas || 0)}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Total de Transações:</span>
                    <span class="detail-value">${stats.total_transacoes || 0}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Cashback Distribuído:</span>
                    <span class="detail-value">R$ ${formatMoney(stats.total_cashback || 0)}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Clientes com Saldo:</span>
                    <span class="detail-value">${loja.clientes_com_saldo || 0}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Saldo Total dos Clientes:</span>
                    <span class="detail-value">R$ ${formatMoney(loja.total_saldo_clientes || 0)}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Taxa de Uso do Saldo:</span>
                    <span class="detail-value">
                        ${loja.total_transacoes > 0 ? 
                            Math.round((loja.transacoes_com_saldo / loja.total_transacoes) * 100) : 0}%
                    </span>
                </div>
            </div>
            
            ${loja.endereco ? `
            <div class="detail-section">
                <h4>Endereço</h4>
                <div class="detail-item">
                    <span class="detail-label">CEP:</span>
                    <span class="detail-value">${formatCEP(loja.endereco.cep)}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Logradouro:</span>
                    <span class="detail-value">${loja.endereco.logradouro}, ${loja.endereco.numero}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Bairro:</span>
                    <span class="detail-value">${loja.endereco.bairro}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Cidade/UF:</span>
                    <span class="detail-value">${loja.endereco.cidade}/${loja.endereco.estado}</span>
                </div>
            </div>
            ` : ''}
            
            <div class="detail-section">
                <h4>Datas</h4>
                <div class="detail-item">
                    <span class="detail-label">Data de Cadastro:</span>
                    <span class="detail-value">${formatDateTime(loja.data_cadastro)}</span>
                </div>
                ${loja.data_aprovacao ? `
                <div class="detail-item">
                    <span class="detail-label">Data de Aprovação:</span>
                    <span class="detail-value">${formatDateTime(loja.data_aprovacao)}</span>
                </div>
                ` : ''}
            </div>
        </div>
    `;
    
    // Configurar botão de editar
    if (editBtn) {
        currentStoreData = loja;
        editBtn.onclick = () => editStore(loja.id);
    }
}

/**
 * Aprovar uma loja
 */
function approveStore(storeId) {
    if (!confirm('Tem certeza que deseja aprovar esta loja?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'approve');
    formData.append('id', storeId);
    
    fetch('../../api/stores.php?action=approve', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${storeId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status) {
            showAlert('sucesso', data.message);
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert('erro', data.message);
        }
    })
    .catch(error => {
        console.error('Erro ao aprovar loja:', error);
        showAlert('erro', 'Erro ao aprovar loja. Tente novamente.');
    });
}

/**
 * Rejeitar uma loja
 */
function rejectStore(storeId) {
    const observacao = prompt('Motivo da rejeição (opcional):');
    
    if (observacao === null) return; // Usuário cancelou
    
    if (!confirm('Tem certeza que deseja rejeitar esta loja?')) {
        return;
    }
    
    fetch('../../api/stores.php?action=reject', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${storeId}&observacao=${encodeURIComponent(observacao)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status) {
            showAlert('sucesso', data.message);
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert('erro', data.message);
        }
    })
    .catch(error => {
        console.error('Erro ao rejeitar loja:', error);
        showAlert('erro', 'Erro ao rejeitar loja. Tente novamente.');
    });
}

/**
 * Aprovação em lote
 */
function bulkApprove() {
    if (selectedStores.length === 0) {
        showAlert('aviso', 'Selecione pelo menos uma loja');
        return;
    }
    
    if (!confirm(`Tem certeza que deseja aprovar ${selectedStores.length} loja(s)?`)) {
        return;
    }
    
    const requests = selectedStores.map(storeId => 
        fetch('../../api/stores.php?action=approve', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${storeId}`
        }).then(response => response.json())
    );
    
    Promise.all(requests)
        .then(results => {
            const successful = results.filter(r => r.status).length;
            const failed = results.filter(r => !r.status).length;
            
            if (successful > 0) {
                showAlert('sucesso', `${successful} loja(s) aprovada(s) com sucesso`);
            }
            
            if (failed > 0) {
                showAlert('erro', `${failed} loja(s) falharam na aprovação`);
            }
            
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        })
        .catch(error => {
            console.error('Erro na aprovação em lote:', error);
            showAlert('erro', 'Erro na aprovação em lote. Tente novamente.');
        });
}

/**
 * Mostrar modal de nova loja
 */
function showStoreModal(storeData = null) {
    const modal = document.getElementById('storeModal');
    const title = document.getElementById('storeModalTitle');
    const form = document.getElementById('storeForm');
    
    if (!modal) return;
    
    // Resetar formulário
    form.reset();
    
    if (storeData) {
        // Edição
        title.textContent = 'Editar Loja';
        fillStoreForm(storeData);
    } else {
        // Nova loja
        title.textContent = 'Nova Loja';
    }
    
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

/**
 * Esconder modal de loja
 */
function hideStoreModal() {
    const modal = document.getElementById('storeModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
}

/**
 * Esconder modal de detalhes
 */
function hideStoreDetailsModal() {
    const modal = document.getElementById('storeDetailsModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
}

/**
 * Esconder todos os modais
 */
function hideAllModals() {
    hideStoreModal();
    hideStoreDetailsModal();
}

/**
 * Editar loja
 */
function editStore(storeId) {
    hideStoreDetailsModal();
    
    if (currentStoreData) {
        showStoreModal(currentStoreData);
    } else {
        showAlert('erro', 'Dados da loja não disponíveis');
    }
}

/**
 * Preencher formulário com dados da loja
 */
function fillStoreForm(storeData) {
    const fields = [
        'id', 'nome_fantasia', 'razao_social', 'cnpj', 
        'email', 'telefone', 'categoria', 'porcentagem_cashback', 'status'
    ];
    
    fields.forEach(field => {
        const element = document.getElementById(field);
        if (element && storeData[field] !== undefined) {
            element.value = storeData[field];
        }
    });
}

/**
 * Submeter formulário da loja
 */
function submitStoreForm(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const storeId = formData.get('id');
    
    // Determinar se é criação ou edição
    const isEdit = storeId && storeId !== '';
    const url = isEdit ? '../../api/stores.php' : '../../api/stores.php';
    const method = isEdit ? 'PUT' : 'POST';
    
    // Converter FormData para URLencoded string
    const data = new URLSearchParams(formData).toString();
    
    fetch(url, {
        method: 'POST', // Sempre POST pois PHP não suporta PUT diretamente
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: data
    })
    .then(response => response.json())
    .then(result => {
        if (result.status) {
            showAlert('sucesso', result.message);
            hideStoreModal();
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert('erro', result.message);
        }
    })
    .catch(error => {
        console.error('Erro ao salvar loja:', error);
        showAlert('erro', 'Erro ao salvar loja. Tente novamente.');
    });
}

/**
 * Funções utilitárias
 */

function formatMoney(value) {
    return parseFloat(value || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function formatCNPJ(cnpj) {
    if (!cnpj) return '';
    const digits = cnpj.replace(/\D/g, '');
    return digits.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
}

function formatCEP(cep) {
    if (!cep) return '';
    const digits = cep.replace(/\D/g, '');
    return digits.replace(/^(\d{5})(\d{3})$/, '$1-$2');
}

function formatDateTime(dateString) {
    if (!dateString) return 'Não informado';
    const date = new Date(dateString);
    return date.toLocaleString('pt-BR');
}

function getStatusText(status) {
    const statusMap = {
        'aprovado': 'Aprovado',
        'pendente': 'Pendente',
        'rejeitado': 'Rejeitado'
    };
    return statusMap[status] || status;
}

function initializeTooltips() {
    // Inicializar tooltips se necessário
}

function showAlert(type, message) {
    // Implementar sistema de alertas
    const alertClass = {
        'sucesso': 'success',
        'erro': 'danger',
        'aviso': 'warning',
        'info': 'info'
    }[type] || 'info';
    
    // Criar elemento de alerta
    const alert = document.createElement('div');
    alert.className = `alert alert-${alertClass}`;
    alert.style.position = 'fixed';
    alert.style.top = '20px';
    alert.style.right = '20px';
    alert.style.zIndex = '9999';
    alert.style.maxWidth = '400px';
    alert.textContent = message;
    
    document.body.appendChild(alert);
    
    // Remover após 5 segundos
    setTimeout(() => {
        if (alert.parentNode) {
            alert.parentNode.removeChild(alert);
        }
    }, 5000);
}