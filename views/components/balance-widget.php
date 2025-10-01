<?php
// views/components/balance-widget.php
/**
 * Widget para exibir e usar saldo de cashback
 * Deve ser incluído em telas onde o cliente pode usar seu saldo de cashback recebido
 * 
 * IMPORTANTE: O saldo só pode ser usado na mesma loja que gerou o cashback
 * A loja não recebe cashback - ela apenas paga 10% de comissão (5% cliente + 5% admin)
 * 
 * @param int $userId ID do usuário
 * @param int $storeId ID da loja (opcional, para filtrar saldo específico)
 * @param bool $allowUse Permite usar o saldo (padrão: false)
 * @param string $useCallback JavaScript callback para quando usar saldo
 */

if (!isset($userId)) {
    echo '<div class="alert alert-danger">Erro: ID do usuário não informado</div>';
    return;
}

// Incluir modelo de saldo
require_once __DIR__ . '/../../models/CashbackBalance.php';
$balanceModel = new CashbackBalance();

if (isset($storeId)) {
    // Saldo específico de uma loja - APENAS o que o cliente tem disponível
    $storeBalance = $balanceModel->getStoreBalance($userId, $storeId);
    $balances = [];
    if ($storeBalance > 0) {
        // Buscar dados da loja
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT nome_fantasia, logo FROM lojas WHERE id = :store_id");
        $stmt->bindParam(':store_id', $storeId);
        $stmt->execute();
        $storeData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $balances = [[
            'loja_id' => $storeId,
            'nome_fantasia' => $storeData['nome_fantasia'],
            'logo' => $storeData['logo'],
            'saldo_disponivel' => $storeBalance // Saldo que o CLIENTE pode usar
        ]];
    }
} else {
    // Todos os saldos do usuário - APENAS o que o cliente recebeu/pode usar
    $balances = $balanceModel->getAllUserBalances($userId);
}

// Total disponível para o cliente
$totalBalance = $balanceModel->getTotalBalance($userId);
$allowUse = $allowUse ?? false;
$useCallback = $useCallback ?? 'onBalanceUsed';
?>

<div class="balance-widget" id="balanceWidget">
    <div class="balance-widget-header">
        <h4 class="balance-widget-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M12 6v6l4 2"></path>
            </svg>
            Seu Saldo de Cashback
        </h4>

        <!-- ADICIONADO: Informação importante -->
        <?php if (!isset($storeId)): ?>
        <div class="balance-info-text">
            <small class="text-muted">
                💡 Seu saldo só pode ser usado na loja onde foi gerado
            </small>
        </div>
        <?php endif; ?>
        
        <?php if (!isset($storeId)): ?>
        <div class="balance-total">
            <span class="balance-total-label">Total:</span>
            <span class="balance-total-value">R$ <?php echo number_format($totalBalance, 2, ',', '.'); ?></span>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="balance-widget-content">
        <?php if (empty($balances)): ?>
            <div class="balance-empty">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <p>
                    Você não possui saldo de cashback<?php echo isset($storeId) ? ' nesta loja' : ''; ?>
                    <?php if (!isset($storeId)): ?>
                    <br><small class="text-muted">Faça compras nas lojas parceiras para receber cashback!</small>
                    <?php endif; ?>
                </p>
            </div>
            <!-- ADICIONADO: Informação sobre como funciona o cashback -->
            <div class="cashback-info">
                <small class="info-text">
                    💡 Você recebe 5% de cashback nas compras desta loja (pode variar por loja)
                </small>
            </div>
        <?php else: ?>
            <div class="balance-stores">
                <?php foreach ($balances as $balance): ?>
                <div class="balance-store-item" data-store-id="<?php echo $balance['loja_id']; ?>" data-balance="<?php echo $balance['saldo_disponivel']; ?>">
                    <div class="balance-store-info">
                        <?php if (!empty($balance['logo'])): ?>
                            <img src="../../uploads/store_logos/<?php echo htmlspecialchars($balance['logo']); ?>" 
                                 alt="<?php echo htmlspecialchars($balance['nome_fantasia']); ?>" 
                                 class="balance-store-logo">
                        <?php else: ?>
                            <div class="balance-store-logo-placeholder">
                                <?php echo strtoupper(substr($balance['nome_fantasia'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="balance-store-details">
                            <span class="balance-store-name"><?php echo htmlspecialchars($balance['nome_fantasia']); ?></span>
                            <span class="balance-store-amount">R$ <?php echo number_format($balance['saldo_disponivel'], 2, ',', '.'); ?></span>
                        </div>
                    </div>
                    
                    <?php if ($allowUse): ?>
                    <div class="balance-store-actions">
                        <button class="btn btn-outline btn-sm use-balance-btn" 
                                data-store-id="<?php echo $balance['loja_id']; ?>"
                                data-store-name="<?php echo htmlspecialchars($balance['nome_fantasia']); ?>"
                                data-balance="<?php echo $balance['saldo_disponivel']; ?>">
                            Usar Saldo
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="balance-widget-footer">
        <a href="<?php echo CLIENT_BALANCE_URL; ?>" class="balance-widget-link">
            Ver saldo completo
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 18l6-6-6-6"></path>
            </svg>
        </a>
    </div>
</div>

<!-- Modal para usar saldo -->
<?php if ($allowUse): ?>
<div id="useBalanceModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="useBalanceModalTitle">Usar Saldo de Cashback</h3>
            <button class="modal-close" onclick="closeUseBalanceModal()">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <form id="useBalanceForm">
                <input type="hidden" id="useBalanceStoreId" name="store_id">
                
                <div class="form-group">
                    <label>Loja: <span id="useBalanceStoreName"></span></label>
                    <p class="form-help">Saldo disponível: <strong id="useBalanceAvailable"></strong></p>
                </div>
                
                <div class="form-group">
                    <label for="useBalanceAmount">Valor a usar</label>
                    <div class="input-group">
                        <span class="input-group-text">R$</span>
                        <input type="number" id="useBalanceAmount" name="amount" 
                               step="0.01" min="0.01" max="999999.99" required 
                               class="form-control" placeholder="0,00">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="useBalanceDescription">Descrição (opcional)</label>
                    <input type="text" id="useBalanceDescription" name="description" 
                           class="form-control" placeholder="Descreva o uso do saldo">
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeUseBalanceModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Usar Saldo</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* Estilos do widget de saldo */
.balance-widget {
    background-color: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

.balance-widget-header {
    padding: 16px 20px;
    background-color: #f8f9fa;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.balance-widget-title {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: #2d3748;
    display: flex;
    align-items: center;
    gap: 8px;
}

.balance-total {
    display: flex;
    align-items: center;
    gap: 8px;
}

.balance-total-label {
    font-size: 0.875rem;
    color: #4a5568;
}

.balance-total-value {
    font-size: 1.1rem;
    font-weight: 700;
    color: #FF7A00;
}

.balance-widget-content {
    padding: 16px 20px;
}

.balance-empty {
    text-align: center;
    padding: 20px;
    color: #718096;
}

.balance-empty svg {
    margin-bottom: 12px;
}

.balance-empty p {
    margin: 0;
    font-size: 0.9rem;
}

.balance-stores {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.balance-store-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.balance-store-item:hover {
    background-color: #f8f9fa;
    border-color: #FF7A00;
}

.balance-store-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.balance-store-logo {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    object-fit: cover;
}

.balance-store-logo-placeholder {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background-color: #FF7A00;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1rem;
}

.balance-store-details {
    display: flex;
    flex-direction: column;
}

.balance-store-name {
    font-weight: 500;
    color: #2d3748;
    font-size: 0.9rem;
}

.balance-store-amount {
    font-weight: 700;
    color: #FF7A00;
    font-size: 1rem;
}

.balance-widget-footer {
    padding: 12px 20px;
    border-top: 1px solid #e2e8f0;
    background-color: #f8f9fa;
}

.balance-widget-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #FF7A00;
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    transition: color 0.3s ease;
}

.balance-widget-link:hover {
    color: #e56500;
}

.use-balance-btn {
    padding: 6px 12px;
    font-size: 0.8rem;
}
/* ADICIONADO: Estilo para informação do widget */
.balance-info-text {
    padding: 8px 12px;
    background-color: #f8f9fa;
    border-radius: 6px;
    margin-bottom: 12px;
    text-align: center;
}

.balance-info-text .text-muted {
    color: #6c757d;
    font-size: 0.875rem;
}

.cashback-info {
    margin-top: 8px;
    padding: 6px 8px;
    background-color: #e7f3ff;
    border-radius: 4px;
    text-align: center;
}

.cashback-info .info-text {
    color: #2c5aa0;
    font-size: 0.8rem;
}

/* Responsividade do widget */
@media (max-width: 768px) {
    .balance-store-item {
        flex-direction: column;
        gap: 12px;
        text-align: center;
    }
    
    .balance-store-actions {
        width: 100%;
    }
    
    .use-balance-btn {
        width: 100%;
    }
}
</style>

<script>
// JavaScript para gerenciar o uso do saldo
<?php if ($allowUse): ?>
document.addEventListener('DOMContentLoaded', function() {
    // Event listeners para botões de usar saldo
    document.querySelectorAll('.use-balance-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const storeId = this.dataset.storeId;
            const storeName = this.dataset.storeName;
            const balance = parseFloat(this.dataset.balance);
            
            openUseBalanceModal(storeId, storeName, balance);
        });
    });
    
    // Submit do formulário de uso de saldo
    document.getElementById('useBalanceForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitUseBalance();
    });
});

function openUseBalanceModal(storeId, storeName, balance) {
    document.getElementById('useBalanceStoreId').value = storeId;
    document.getElementById('useBalanceStoreName').textContent = storeName;
    document.getElementById('useBalanceAvailable').textContent = 'R$ ' + balance.toFixed(2).replace('.', ',');
    document.getElementById('useBalanceAmount').max = balance;
    document.getElementById('useBalanceModal').style.display = 'block';
}

function closeUseBalanceModal() {
    document.getElementById('useBalanceModal').style.display = 'none';
    document.getElementById('useBalanceForm').reset();
}

function submitUseBalance() {
    const formData = new FormData(document.getElementById('useBalanceForm'));
    
    // Converter valor para formato correto
    const amount = parseFloat(formData.get('amount'));
    if (isNaN(amount) || amount <= 0) {
        alert('Valor inválido');
        return;
    }
    
    // Fazer requisição para usar o saldo
    fetch('../../api/balance.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'use_balance',
            store_id: formData.get('store_id'),
            amount: amount,
            description: formData.get('description')
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status) {
            closeUseBalanceModal();
            
            // Callback personalizado
            if (typeof <?php echo $useCallback; ?> === 'function') {
                <?php echo $useCallback; ?>(data);
            } else {
                alert('Saldo usado com sucesso!');
                location.reload();
            }
        } else {
            alert('Erro: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao usar saldo. Tente novamente.');
    });
}

// Fechar modal ao clicar fora
window.onclick = function(event) {
    const modal = document.getElementById('useBalanceModal');
    if (event.target === modal) {
        closeUseBalanceModal();
    }
}
<?php endif; ?>
</script>