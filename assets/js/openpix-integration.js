// assets/js/openpix-integration.js

class OpenPixIntegration {
    constructor() {
        this.isProcessing = false;
        this.pollInterval = null;
        this.maxPollAttempts = 60; // 5 minutos (5s * 60)
        this.currentPollAttempts = 0;
    }

    /**
     * Criar cobrança PIX OpenPix
     */
    async createCharge(paymentId) {
        if (this.isProcessing) {
            this.showMessage('Aguarde, processando...', 'warning');
            return;
        }

        this.isProcessing = true;
        this.showMessage('Gerando PIX OpenPix...', 'info');

        try {
            const response = await fetch('/api/openpix', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'create_charge',
                    payment_id: paymentId
                })
            });

            const data = await response.json();

            if (data.status) {
                this.showPixModal(data.data, paymentId);
                this.startPolling(data.data.charge_id);
            } else {
                this.showMessage('Erro: ' + data.message, 'error');
            }
        } catch (error) {
            this.showMessage('Erro de conexão. Tente novamente.', 'error');
            console.error('OpenPix error:', error);
        } finally {
            this.isProcessing = false;
        }
    }

    /**
     * Mostrar modal com QR Code
     */
    showPixModal(pixData, paymentId) {
        const modal = document.createElement('div');
        modal.className = 'openpix-modal';
        modal.innerHTML = `
            <div class="openpix-modal-content">
                <div class="openpix-header">
                    <h3>🔥 Pagar via PIX OpenPix</h3>
                    <button class="openpix-close" onclick="this.closest('.openpix-modal').remove()">&times;</button>
                </div>
                
                <div class="openpix-body">
                    <div class="openpix-status" id="openpixStatus">
                        <div class="status-waiting">
                            <div class="pulse-icon">📱</div>
                            <p>Aguardando pagamento...</p>
                        </div>
                    </div>
                    
                    <div class="openpix-qr">
                        <img src="${pixData.qr_code_image}" alt="QR Code PIX" class="qr-image">
                    </div>
                    
                    <div class="openpix-code">
                        <p>Ou copie o código PIX:</p>
                        <div class="code-container">
                            <input type="text" value="${pixData.qr_code}" readonly id="pixCode">
                            <button onclick="openPixIntegration.copyCode()" class="copy-btn">Copiar</button>
                        </div>
                    </div>
                    
                    <div class="openpix-instructions">
                        <h4>Como pagar:</h4>
                        <ol>
                            <li>Abra o app do seu banco</li>
                            <li>Escolha PIX</li>
                            <li>Escaneie o QR Code ou cole o código</li>
                            <li>Confirme o pagamento</li>
                        </ol>
                    </div>
                    
                    <div class="openpix-footer">
                        <p class="security-text">🔒 Pagamento seguro via OpenPix</p>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        modal.style.display = 'flex';
    }

    /**
     * Copiar código PIX
     */
    copyCode() {
        const codeInput = document.getElementById('pixCode');
        codeInput.select();
        document.execCommand('copy');
        this.showMessage('Código PIX copiado!', 'success');
    }

    /**
     * Iniciar polling do status
     */
    startPolling(chargeId) {
        this.currentPollAttempts = 0;
        this.pollInterval = setInterval(() => {
            this.checkPaymentStatus(chargeId);
        }, 5000); // Verificar a cada 5 segundos
    }

    /**
     * Verificar status do pagamento
     */
    async checkPaymentStatus(chargeId) {
        this.currentPollAttempts++;

        if (this.currentPollAttempts > this.maxPollAttempts) {
            this.stopPolling();
            this.showMessage('Tempo limite excedido. Recarregue a página para verificar o status.', 'warning');
            return;
        }

        try {
            const response = await fetch('/api/openpix', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'check_status',
                    charge_id: chargeId
                })
            });

            const data = await response.json();

            if (data.success && data.data.status === 'COMPLETED') {
                this.stopPolling();
                this.showPaymentSuccess();
                setTimeout(() => {
                    window.location.reload();
                }, 3000);
            }
        } catch (error) {
            console.error('Erro ao verificar status:', error);
        }
    }

    /**
     * Mostrar sucesso do pagamento
     */
    showPaymentSuccess() {
        const statusElement = document.getElementById('openpixStatus');
        if (statusElement) {
            statusElement.innerHTML = `
                <div class="status-success">
                    <div class="success-icon">✅</div>
                    <p>Pagamento aprovado!</p>
                    <p class="small">Atualizando página...</p>
                </div>
            `;
        }
        this.showMessage('Pagamento aprovado com sucesso!', 'success');
    }

    /**
     * Parar polling
     */
    stopPolling() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
    }

    /**
     * Mostrar mensagem
     */
    showMessage(message, type = 'info') {
        // Remover mensagem anterior se existir
        const existingMessage = document.querySelector('.openpix-message');
        if (existingMessage) {
            existingMessage.remove();
        }

        const messageDiv = document.createElement('div');
        messageDiv.className = `openpix-message message-${type}`;
        messageDiv.textContent = message;

        document.body.appendChild(messageDiv);

        // Remover após 5 segundos
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
    }
}

// Instância global
const openPixIntegration = new OpenPixIntegration();

// Limpar polling ao sair da página
window.addEventListener('beforeunload', () => {
    openPixIntegration.stopPolling();
});