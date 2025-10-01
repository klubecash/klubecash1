<!-- views/components/complete-profile-modal.php -->
<div id="completeProfileModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Complete seu Perfil</h2>
            <span class="close">&times;</span>
        </div>
        
        <div class="modal-body">
            <p>Para aproveitar melhor o Klube Cash, complete algumas informações:</p>
            
            <form id="completeProfileForm">
                <div class="input-group">
                    <label for="telefone">Telefone</label>
                    <input type="tel" id="telefone" name="telefone" placeholder="(XX) XXXXX-XXXX">
                </div>
                
                <div class="input-group">
                    <label for="data_nascimento">Data de Nascimento</label>
                    <input type="date" id="data_nascimento" name="data_nascimento">
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="skipProfile()">Pular por Agora</button>
                    <button type="submit" class="btn-primary">Completar Perfil</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #fefefe;
    margin: 15% auto;
    padding: 20px;
    border-radius: 10px;
    width: 90%;
    max-width: 500px;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.close {
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.modal-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}
</style>

<script>
// Mostrar modal se for um novo usuário
function showCompleteProfileModal() {
    document.getElementById('completeProfileModal').style.display = 'block';
}

// Fechar modal
document.querySelector('.close').onclick = function() {
    document.getElementById('completeProfileModal').style.display = 'none';
}

// Pular preenchimento
function skipProfile() {
    document.getElementById('completeProfileModal').style.display = 'none';
}

// Verificar se deve mostrar modal para novos usuários Google
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const isNewUser = urlParams.get('new_user');
    
    if (isNewUser === 'true') {
        setTimeout(showCompleteProfileModal, 1000);
    }
});
</script>