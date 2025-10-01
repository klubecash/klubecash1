<?php
/**
 * Perfil do Cliente - Versão PWA
 * Interface otimizada para dispositivos móveis
 * 
 * Funcionalidades:
 * - Layout responsivo mobile-first
 * - Upload de foto nativo com preview
 * - Menu estilo aplicativo
 * - Gestos touch-friendly
 * - Animações suaves
 */

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/ClientController.php';
require_once __DIR__ . '/../../utils/Security.php';

// Verificar autenticação e tipo de usuário
$authResult = AuthController::checkAuth();
if (!$authResult['authenticated'] || $authResult['user_type'] !== 'cliente') {
    if (isset($_SESSION['auth_error'])) {
        unset($_SESSION['auth_error']);
    }
    header('Location: ' . BASE_URL . '/login');
    exit;
}

$userId = $authResult['user_id'];

// Processar upload de foto de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'upload_photo':
            if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                try {
                    $result = ClientController::uploadProfilePhoto($userId, $_FILES['profile_photo']);
                    echo json_encode($result);
                } catch (Exception $e) {
                    echo json_encode([
                        'status' => false,
                        'message' => 'Erro ao fazer upload da foto: ' . $e->getMessage()
                    ]);
                }
            } else {
                echo json_encode([
                    'status' => false,
                    'message' => 'Nenhuma foto foi selecionada ou erro no upload'
                ]);
            }
            exit;
            
        case 'update_profile':
            try {
                $data = [
                    'nome' => $_POST['nome'] ?? '',
                    'telefone' => $_POST['telefone'] ?? '',
                    'data_nascimento' => $_POST['data_nascimento'] ?? null
                ];
                
                $result = ClientController::updateProfile($userId, $data);
                echo json_encode($result);
            } catch (Exception $e) {
                echo json_encode([
                    'status' => false,
                    'message' => 'Erro ao atualizar perfil: ' . $e->getMessage()
                ]);
            }
            exit;
    }
}

// Carregar dados do perfil
$profileData = [];
$error = false;

try {
    $profileResult = ClientController::getProfileData($userId);
    
    if ($profileResult['status']) {
        $profileData = $profileResult['data'];
    } else {
        $error = true;
        $errorMessage = $profileResult['message'];
    }
} catch (Exception $e) {
    $error = true;
    $errorMessage = 'Erro ao carregar dados do perfil';
}

// Valores padrão se não carregou
if (empty($profileData)) {
    $profileData = [
        'perfil' => [
            'nome' => '',
            'email' => '',
            'cpf' => '',
            'telefone' => '',
            'data_nascimento' => '',
            'foto_perfil' => null,
            'data_criacao' => date('Y-m-d')
        ],
        'estatisticas' => [
            'total_cashback' => 0,
            'total_transacoes' => 0,
            'cashback_disponivel' => 0,
            'cashback_pendente' => 0
        ]
    ];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="theme-color" content="#2E7D32">
    
    <title>Meu Perfil - Klube Cash</title>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="<?= BASE_URL ?>/pwa/manifest.json">
    
    <!-- Ícones PWA -->
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/icons/icon-192x192.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/assets/icons/icon-32x32.png">
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/pwa.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/mobile-first.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/animations.css">
    
    <!-- Font Awesome para ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Estilos específicos do perfil PWA */
        .profile-pwa {
            background: linear-gradient(135deg, #2E7D32 0%, #388E3C 100%);
            min-height: 100vh;
            padding-bottom: 80px; /* Espaço para bottom nav */
        }
        
        .profile-header {
            background: linear-gradient(135deg, #2E7D32 0%, #388E3C 100%);
            padding: 20px 16px 30px;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }
        
        .profile-avatar-container {
            position: relative;
            width: 100px;
            height: 100px;
            margin: 0 auto 20px;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.3);
            object-fit: cover;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: rgba(255, 255, 255, 0.8);
            transition: all 0.3s ease;
        }
        
        .avatar-upload-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 32px;
            height: 32px;
            background: #4CAF50;
            border: 3px solid white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        
        .avatar-upload-btn:active {
            transform: scale(0.95);
        }
        
        .avatar-upload-btn i {
            color: white;
            font-size: 12px;
        }
        
        .profile-info {
            text-align: center;
        }
        
        .profile-name {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .profile-email {
            font-size: 14px;
            opacity: 0.8;
            margin-bottom: 8px;
        }
        
        .member-since {
            font-size: 12px;
            opacity: 0.7;
        }
        
        .profile-content {
            background: #f8f9fa;
            min-height: calc(100vh - 180px);
        }
        
        .stats-container {
            padding: 20px 16px;
            background: white;
            margin-bottom: 8px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .stat-item {
            text-align: center;
            padding: 16px;
            background: linear-gradient(135deg, #E8F5E8 0%, #F1F8E9 100%);
            border-radius: 12px;
            border-left: 4px solid #4CAF50;
        }
        
        .stat-value {
            font-size: 20px;
            font-weight: 700;
            color: #2E7D32;
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .menu-section {
            background: white;
            margin-bottom: 8px;
        }
        
        .menu-title {
            padding: 16px;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            padding: 16px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            color: inherit;
        }
        
        .menu-item:hover,
        .menu-item:active {
            background: #f8f9fa;
            transform: translateX(4px);
        }
        
        .menu-item:last-child {
            border-bottom: none;
        }
        
        .menu-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 16px;
            font-size: 16px;
        }
        
        .menu-icon.blue { background: #E3F2FD; color: #1976D2; }
        .menu-icon.green { background: #E8F5E8; color: #388E3C; }
        .menu-icon.orange { background: #FFF3E0; color: #F57C00; }
        .menu-icon.purple { background: #F3E5F5; color: #7B1FA2; }
        .menu-icon.red { background: #FFEBEE; color: #D32F2F; }
        
        .menu-content {
            flex: 1;
        }
        
        .menu-text {
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 2px;
        }
        
        .menu-description {
            font-size: 12px;
            color: #666;
        }
        
        .menu-arrow {
            color: #ccc;
            font-size: 14px;
        }
        
        .hidden-input {
            display: none;
        }
        
        /* Loading overlay para upload */
        .upload-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .upload-loader {
            background: white;
            padding: 30px;
            border-radius: 16px;
            text-align: center;
            max-width: 280px;
            margin: 0 20px;
        }
        
        .upload-loader .spinner {
            width: 40px;
            height: 40px;
            margin: 0 auto 16px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #4CAF50;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        
        /* Responsividade */
        @media (max-width: 360px) {
            .profile-avatar-container {
                width: 80px;
                height: 80px;
            }
            
            .profile-avatar {
                width: 80px;
                height: 80px;
                font-size: 32px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .profile-content {
                background: #121212;
            }
            
            .menu-section {
                background: #1e1e1e;
            }
            
            .menu-title {
                background: #2d2d2d;
                color: #aaa;
            }
            
            .menu-item {
                color: #fff;
                border-bottom-color: #333;
            }
            
            .menu-item:hover {
                background: #2d2d2d;
            }
            
            .menu-description {
                color: #999;
            }
        }
    </style>
</head>

<body class="profile-pwa">
    <!-- Upload Overlay -->
    <div class="upload-overlay" id="uploadOverlay">
        <div class="upload-loader">
            <div class="spinner"></div>
            <p>Enviando foto...</p>
        </div>
    </div>

    <!-- Input oculto para upload de foto -->
    <input type="file" id="photoInput" class="hidden-input" accept="image/*" capture="user">

    <!-- Header do Perfil -->
    <div class="profile-header">
        <div class="profile-avatar-container">
            <?php if (!empty($profileData['perfil']['foto_perfil'])): ?>
                <img src="<?= BASE_URL ?>/uploads/avatars/<?= $profileData['perfil']['foto_perfil'] ?>" 
                     alt="Foto do perfil" class="profile-avatar" id="profileAvatar">
            <?php else: ?>
                <div class="profile-avatar" id="profileAvatar">
                    <i class="fas fa-user"></i>
                </div>
            <?php endif; ?>
            
            <div class="avatar-upload-btn" onclick="triggerPhotoUpload()">
                <i class="fas fa-camera"></i>
            </div>
        </div>
        
        <div class="profile-info">
            <div class="profile-name" id="profileName">
                <?= htmlspecialchars($profileData['perfil']['nome'] ?: 'Nome não informado') ?>
            </div>
            <div class="profile-email">
                <?= htmlspecialchars($profileData['perfil']['email'] ?: 'Email não informado') ?>
            </div>
            <div class="member-since">
                Membro desde <?= date('m/Y', strtotime($profileData['perfil']['data_criacao'] ?? 'now')) ?>
            </div>
        </div>
    </div>

    <!-- Conteúdo Principal -->
    <div class="profile-content">
        <!-- Estatísticas -->
        <div class="stats-container">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value">
                        R$ <?= number_format($profileData['estatisticas']['cashback_disponivel'] ?? 0, 2, ',', '.') ?>
                    </div>
                    <div class="stat-label">Disponível</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">
                        R$ <?= number_format($profileData['estatisticas']['cashback_pendente'] ?? 0, 2, ',', '.') ?>
                    </div>
                    <div class="stat-label">Pendente</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">
                        <?= number_format($profileData['estatisticas']['total_transacoes'] ?? 0, 0, ',', '.') ?>
                    </div>
                    <div class="stat-label">Transações</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">
                        R$ <?= number_format($profileData['estatisticas']['total_cashback'] ?? 0, 2, ',', '.') ?>
                    </div>
                    <div class="stat-label">Total Cashback</div>
                </div>
            </div>
        </div>

        <!-- Menu Dados Pessoais -->
        <div class="menu-section">
            <div class="menu-title">Dados Pessoais</div>
            
            <a href="#" class="menu-item" onclick="editProfile()">
                <div class="menu-icon blue">
                    <i class="fas fa-user-edit"></i>
                </div>
                <div class="menu-content">
                    <div class="menu-text">Editar Perfil</div>
                    <div class="menu-description">Nome, telefone, data de nascimento</div>
                </div>
                <div class="menu-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </a>
            
            <a href="<?= BASE_URL ?>/client/change-password" class="menu-item">
                <div class="menu-icon green">
                    <i class="fas fa-lock"></i>
                </div>
                <div class="menu-content">
                    <div class="menu-text">Alterar Senha</div>
                    <div class="menu-description">Mantenha sua conta segura</div>
                </div>
                <div class="menu-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </a>
        </div>

        <!-- Menu Cashback -->
        <div class="menu-section">
            <div class="menu-title">Cashback</div>
            
            <a href="<?= BASE_URL ?>/client/statement" class="menu-item">
                <div class="menu-icon green">
                    <i class="fas fa-history"></i>
                </div>
                <div class="menu-content">
                    <div class="menu-text">Histórico Completo</div>
                    <div class="menu-description">Todas suas transações de cashback</div>
                </div>
                <div class="menu-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </a>
            
            <a href="<?= BASE_URL ?>/client/partner-stores" class="menu-item">
                <div class="menu-icon orange">
                    <i class="fas fa-store"></i>
                </div>
                <div class="menu-content">
                    <div class="menu-text">Lojas Parceiras</div>
                    <div class="menu-description">Descubra onde ganhar cashback</div>
                </div>
                <div class="menu-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </a>
        </div>

        <!-- Menu Configurações -->
        <div class="menu-section">
            <div class="menu-title">Configurações</div>
            
            <a href="#" class="menu-item" onclick="toggleNotifications()">
                <div class="menu-icon purple">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="menu-content">
                    <div class="menu-text">Notificações</div>
                    <div class="menu-description">Gerencie suas preferências</div>
                </div>
                <div class="menu-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </a>
            
            <a href="#" class="menu-item" onclick="shareApp()">
                <div class="menu-icon blue">
                    <i class="fas fa-share-alt"></i>
                </div>
                <div class="menu-content">
                    <div class="menu-text">Indicar Amigos</div>
                    <div class="menu-description">Compartilhe o Klube Cash</div>
                </div>
                <div class="menu-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </a>
        </div>

        <!-- Menu Suporte -->
        <div class="menu-section">
            <div class="menu-title">Suporte</div>
            
            <a href="#" class="menu-item" onclick="openHelp()">
                <div class="menu-icon orange">
                    <i class="fas fa-question-circle"></i>
                </div>
                <div class="menu-content">
                    <div class="menu-text">Central de Ajuda</div>
                    <div class="menu-description">Tire suas dúvidas</div>
                </div>
                <div class="menu-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </a>
            
            <a href="#" class="menu-item" onclick="contactSupport()">
                <div class="menu-icon blue">
                    <i class="fas fa-headset"></i>
                </div>
                <div class="menu-content">
                    <div class="menu-text">Fale Conosco</div>
                    <div class="menu-description">Atendimento direto</div>
                </div>
                <div class="menu-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </a>
            
            <a href="<?= BASE_URL ?>/terms" class="menu-item">
                <div class="menu-icon purple">
                    <i class="fas fa-file-contract"></i>
                </div>
                <div class="menu-content">
                    <div class="menu-text">Termos de Uso</div>
                    <div class="menu-description">Políticas e privacidade</div>
                </div>
                <div class="menu-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </a>
        </div>

        <!-- Menu Logout -->
        <div class="menu-section">
            <a href="#" class="menu-item" onclick="confirmLogout()">
                <div class="menu-icon red">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <div class="menu-content">
                    <div class="menu-text">Sair da Conta</div>
                    <div class="menu-description">Fazer logout do aplicativo</div>
                </div>
                <div class="menu-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </a>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <?php include __DIR__ . '/../components/bottom-nav-pwa.php'; ?>

    <!-- Scripts -->
    <script src="<?= BASE_URL ?>/assets/js/main.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/pwa-main.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/ui-interactions.js"></script>
    
    <script>
        // Configurações globais
        const BASE_URL = '<?= BASE_URL ?>';
        
        /**
         * Trigger para seleção de foto do perfil
         * Usa a API nativa de camera/galeria do dispositivo
         */
        function triggerPhotoUpload() {
            const input = document.getElementById('photoInput');
            
            // Vibração tátil se disponível
            if (navigator.vibrate) {
                navigator.vibrate(50);
            }
            
            input.click();
        }
        
        /**
         * Processa o upload da foto selecionada
         */
        document.getElementById('photoInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            // Validações
            if (!file.type.startsWith('image/')) {
                showToast('Por favor, selecione apenas arquivos de imagem', 'error');
                return;
            }
            
            if (file.size > 5 * 1024 * 1024) { // 5MB
                showToast('A imagem deve ter no máximo 5MB', 'error');
                return;
            }
            
            // Preview imediato
            const reader = new FileReader();
            reader.onload = function(e) {
                const avatar = document.getElementById('profileAvatar');
                avatar.innerHTML = '';
                
                const img = document.createElement('img');
                img.src = e.target.result;
                img.style.width = '100%';
                img.style.height = '100%';
                img.style.objectFit = 'cover';
                img.style.borderRadius = '50%';
                
                avatar.appendChild(img);
            };
            reader.readAsDataURL(file);
            
            // Upload
            uploadPhoto(file);
        });
        
        /**
         * Realiza o upload da foto para o servidor
         */
        async function uploadPhoto(file) {
            const overlay = document.getElementById('uploadOverlay');
            overlay.style.display = 'flex';
            
            try {
                const formData = new FormData();
                formData.append('profile_photo', file);
                formData.append('action', 'upload_photo');
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.status) {
                    showToast('Foto atualizada com sucesso!', 'success');
                    
                    // Atualizar avatar se retornou nova URL
                    if (result.photo_url) {
                        const avatar = document.getElementById('profileAvatar');
                        avatar.innerHTML = `<img src="${result.photo_url}" alt="Foto do perfil" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">`;
                    }
                } else {
                    showToast(result.message || 'Erro ao atualizar foto', 'error');
                }
            } catch (error) {
                console.error('Erro no upload:', error);
                showToast('Erro de conexão. Tente novamente.', 'error');
            } finally {
                overlay.style.display = 'none';
            }
        }
        
        /**
         * Modal para edição do perfil
         */
        function editProfile() {
            // Vibração tátil
            if (navigator.vibrate) {
                navigator.vibrate(50);
            }
            
            // Implementar modal de edição aqui
            // Por enquanto, redireciona para página de edição
            window.location.href = BASE_URL + '/client/edit-profile';
        }
        
        /**
         * Toggle das configurações de notificação
         */
        function toggleNotifications() {
            if (navigator.vibrate) {
                navigator.vibrate(50);
            }
            
            showToast('Funcionalidade em desenvolvimento', 'info');
        }
        
        /**
         * Compartilhamento nativo do app
         */
        async function shareApp() {
            if (navigator.vibrate) {
                navigator.vibrate(50);
            }
            
            const shareData = {
                title: 'Klube Cash',
                text: 'Ganhe cashback em suas compras! Baixe o Klube Cash agora:',
                url: window.location.origin
            };
            
            try {
                if (navigator.share) {
                    await navigator.share(shareData);
                } else {
                    // Fallback para dispositivos sem API de compartilhamento
                    const text = `${shareData.text} ${shareData.url}`;
                    
                    if (navigator.clipboard) {
                        await navigator.clipboard.writeText(text);
                        showToast('Link copiado para a área de transferência!', 'success');
                    } else {
                        showToast('Compartilhe: ' + shareData.url, 'info');
                    }
                }
            } catch (error) {
                console.log('Erro ao compartilhar:', error);
            }
        }
        
        /**
         * Abrir central de ajuda
         */
        function openHelp() {
            if (navigator.vibrate) {
                navigator.vibrate(50);
            }
            
            // Implementar modal de ajuda ou redirecionar
            window.open(BASE_URL + '/help', '_blank');
        }
        
        /**
         * Contato com suporte
         */
        function contactSupport() {
            if (navigator.vibrate) {
                navigator.vibrate(50);
            }
            
            // Tentar abrir WhatsApp, email ou telefone
            const whatsapp = 'https://wa.me/5511999999999?text=Olá, preciso de ajuda com o Klube Cash';
            window.open(whatsapp, '_blank');
        }
        
        /**
         * Confirmar logout
         */
        function confirmLogout() {
            if (navigator.vibrate) {
                navigator.vibrate([100, 50, 100]);
            }
            
            if (confirm('Tem certeza que deseja sair da sua conta?')) {
                window.location.href = BASE_URL + '/logout';
            }
        }
        
        /**
         * Função auxiliar para mostrar toasts
         */
        function showToast(message, type = 'info') {
            // Implementação básica de toast
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: ${type === 'error' ? '#f44336' : type === 'success' ? '#4caf50' : '#2196f3'};
                color: white;
                padding: 12px 24px;
                border-radius: 24px;
                z-index: 10000;
                font-size: 14px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                animation: slideInDown 0.3s ease;
            `;
            
            toast.textContent = message;
            document.body.appendChild(toast);
            
            // Adicionar animação CSS se não existir
            if (!document.querySelector('#toast-styles')) {
                const style = document.createElement('style');
                style.id = 'toast-styles';
                style.textContent = `
                    @keyframes slideInDown {
                        from { opacity: 0; transform: translateX(-50%) translateY(-20px); }
                        to { opacity: 1; transform: translateX(-50%) translateY(0); }
                    }
                `;
                document.head.appendChild(style);
            }
            
            setTimeout(() => {
                toast.style.animation = 'slideInDown 0.3s ease reverse';
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            }, 3000);
        }
        
        // Adicionar listeners para gestos
        document.addEventListener('DOMContentLoaded', function() {
            // Suporte a pull-to-refresh
            let startY = 0;
            let pullDistance = 0;
            const refreshThreshold = 80;
            
            document.addEventListener('touchstart', function(e) {
                if (window.scrollY === 0) {
                    startY = e.touches[0].clientY;
                }
            });
            
            document.addEventListener('touchmove', function(e) {
                if (window.scrollY === 0 && startY > 0) {
                    pullDistance = e.touches[0].clientY - startY;
                    
                    if (pullDistance > 0 && pullDistance < refreshThreshold) {
                        e.preventDefault();
                    }
                }
            });
            
            document.addEventListener('touchend', function() {
                if (pullDistance > refreshThreshold) {
                    window.location.reload();
                }
                startY = 0;
                pullDistance = 0;
            });
        });
    </script>
</body>
</html>