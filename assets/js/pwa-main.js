/**
 * Klube Cash PWA - JavaScript Principal
 * Sistema completo para Progressive Web App
 * Versão: 2.1.0
 * Data: 2025
 */

// === CONFIGURAÇÕES GLOBAIS PWA ===
const PWA_CONFIG = {
    serviceworker: {
        url: '/pwa/sw.js',
        scope: '/'
    },
    manifest: '/pwa/manifest.json',
    offline_page: '/pwa/offline.html',
    cache_version: 'klube-cash-v2.1.0',
    debug: true // Alterar para false em produção
};

// === VARIÁVEIS GLOBAIS ===
let deferredPrompt = null; // Para o prompt de instalação
let isInstalled = false;
let isOnline = navigator.onLine;
let swRegistration = null;

// === LOGGING SYSTEM ===
function log(message, type = 'info') {
    if (!PWA_CONFIG.debug) return;
    
    const timestamp = new Date().toLocaleTimeString();
    const prefix = '🚀 PWA Klube Cash';
    
    switch(type) {
        case 'error':
            console.error(`${prefix} [${timestamp}] ❌`, message);
            break;
        case 'warn':
            console.warn(`${prefix} [${timestamp}] ⚠️`, message);
            break;
        case 'success':
            console.log(`${prefix} [${timestamp}] ✅`, message);
            break;
        default:
            console.log(`${prefix} [${timestamp}] ℹ️`, message);
    }
}

// === INICIALIZAÇÃO PRINCIPAL ===
document.addEventListener('DOMContentLoaded', function() {
    initializePWA();
});

window.addEventListener('load', function() {
    // Executa após o load completo da página
    registerServiceWorker();
    checkInstallStatus();
    initializeOfflineDetection();
    initializeNotifications();
});

// === FUNÇÃO PRINCIPAL DE INICIALIZAÇÃO ===
function initializePWA() {
    log('Inicializando PWA Klube Cash...');
    
    // Verificar suporte a PWA
    if (!isPWASupported()) {
        log('PWA não suportado neste navegador', 'warn');
        return;
    }
    
    // Verificar se já está instalado
    detectInstallation();
    
    // Configurar eventos PWA
    setupPWAEvents();
    
    // Configurar prompt de instalação
    setupInstallPrompt();
    
    // Inicializar interface PWA
    initializePWAInterface();
    
    log('PWA inicializado com sucesso!', 'success');
}

// === VERIFICAÇÃO DE SUPORTE PWA ===
function isPWASupported() {
    return (
        'serviceWorker' in navigator &&
        'Promise' in window &&
        'fetch' in window &&
        'Cache' in window &&
        'caches' in window
    );
}

// === REGISTRO DO SERVICE WORKER ===
function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        log('Service Worker não suportado', 'warn');
        return;
    }

    navigator.serviceWorker.register(PWA_CONFIG.serviceworker.url, {
        scope: PWA_CONFIG.serviceworker.scope
    })
    .then(function(registration) {
        swRegistration = registration;
        log('Service Worker registrado com sucesso', 'success');
        
        // Verificar atualizações
        registration.addEventListener('updatefound', function() {
            log('Nova versão do app disponível!');
            handleServiceWorkerUpdate(registration);
        });
        
        // Verificar se há service worker ativo
        if (registration.active) {
            log('Service Worker ativo e funcionando');
        }
        
        // Configurar sincronização em background
        setupBackgroundSync(registration);
        
    })
    .catch(function(error) {
        log('Falha ao registrar Service Worker: ' + error.message, 'error');
    });

    // Escutar mensagens do service worker
    navigator.serviceWorker.addEventListener('message', function(event) {
        handleServiceWorkerMessage(event);
    });
}

// === GERENCIAMENTO DE ATUALIZAÇÕES ===
function handleServiceWorkerUpdate(registration) {
    const newWorker = registration.installing;
    
    newWorker.addEventListener('statechange', function() {
        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
            // Nova versão instalada e pronta
            showUpdateNotification();
        }
    });
}

function showUpdateNotification() {
    // Criar notificação de atualização
    const updateBanner = document.createElement('div');
    updateBanner.className = 'pwa-update-banner';
    updateBanner.innerHTML = `
        <div class="update-content">
            <span>🔄 Nova versão disponível!</span>
            <button onclick="PWA.reloadApp()" class="btn-update">Atualizar</button>
            <button onclick="PWA.dismissUpdate()" class="btn-dismiss">×</button>
        </div>
    `;
    
    document.body.appendChild(updateBanner);
    
    // Auto-remover após 10 segundos se não interagir
    setTimeout(function() {
        if (updateBanner.parentNode) {
            updateBanner.remove();
        }
    }, 10000);
}

// === DETECÇÃO DE INSTALAÇÃO ===
function detectInstallation() {
    // Verificar se está rodando em modo standalone (já instalado)
    if (window.matchMedia('(display-mode: standalone)').matches || 
        window.navigator.standalone === true) {
        isInstalled = true;
        log('App está instalado e rodando em modo standalone', 'success');
        hideInstallElements();
    }
    
    // Verificar se foi instalado via beforeinstallprompt
    if (localStorage.getItem('pwa-installed') === 'true') {
        isInstalled = true;
        hideInstallElements();
    }
}

function checkInstallStatus() {
    // Verificação mais detalhada do status de instalação
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches;
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
    const isIOSStandalone = window.navigator.standalone === true;
    
    if (isStandalone || isIOSStandalone) {
        isInstalled = true;
        onAppInstalled();
    }
}

// === PROMPT DE INSTALAÇÃO ===
function setupInstallPrompt() {
    // Capturar o evento beforeinstallprompt
    window.addEventListener('beforeinstallprompt', function(e) {
        e.preventDefault();
        deferredPrompt = e;
        log('Prompt de instalação capturado');
        showInstallButton();
    });
    
    // Escutar evento de instalação
    window.addEventListener('appinstalled', function(e) {
        log('App instalado com sucesso!', 'success');
        onAppInstalled();
    });
}

function showInstallButton() {
    if (isInstalled) return;
    
    const installButton = document.querySelector('.pwa-install-btn');
    if (installButton) {
        installButton.style.display = 'block';
        installButton.addEventListener('click', promptInstall);
    }
    
    // Criar banner de instalação se não existir
    if (!document.querySelector('.pwa-install-banner')) {
        createInstallBanner();
    }
}

function createInstallBanner() {
    const banner = document.createElement('div');
    banner.className = 'pwa-install-banner';
    banner.innerHTML = `
        <div class="install-content">
            <div class="install-icon">📱</div>
            <div class="install-text">
                <strong>Instalar Klube Cash</strong>
                <p>Adicione à tela inicial para acesso rápido</p>
            </div>
            <button onclick="PWA.install()" class="btn-install">Instalar</button>
            <button onclick="PWA.dismissInstall()" class="btn-dismiss">×</button>
        </div>
    `;
    
    document.body.appendChild(banner);
    
    // Auto-remover após 30 segundos
    setTimeout(function() {
        if (banner.parentNode && !isInstalled) {
            banner.remove();
        }
    }, 30000);
}

function promptInstall() {
    if (!deferredPrompt) {
        log('Prompt de instalação não disponível', 'warn');
        return;
    }
    
    deferredPrompt.prompt();
    
    deferredPrompt.userChoice.then(function(choiceResult) {
        if (choiceResult.outcome === 'accepted') {
            log('Usuário aceitou a instalação', 'success');
            // Analytics
            trackEvent('pwa_install_accepted');
        } else {
            log('Usuário rejeitou a instalação');
            // Analytics
            trackEvent('pwa_install_rejected');
        }
        deferredPrompt = null;
    });
}

function onAppInstalled() {
    isInstalled = true;
    localStorage.setItem('pwa-installed', 'true');
    hideInstallElements();
    showWelcomeMessage();
    
    // Analytics
    trackEvent('pwa_installed');
    
    log('App instalado e configurado!', 'success');
}

function hideInstallElements() {
    const elements = document.querySelectorAll('.pwa-install-banner, .pwa-install-btn');
    elements.forEach(el => el.remove());
}

function showWelcomeMessage() {
    const welcome = document.createElement('div');
    welcome.className = 'pwa-welcome-message';
    welcome.innerHTML = `
        <div class="welcome-content">
            🎉 <strong>Bem-vindo ao Klube Cash!</strong>
            <p>App instalado com sucesso na sua tela inicial</p>
        </div>
    `;
    
    document.body.appendChild(welcome);
    
    setTimeout(() => welcome.remove(), 5000);
}

// === DETECÇÃO DE CONECTIVIDADE ===
function initializeOfflineDetection() {
    window.addEventListener('online', function() {
        isOnline = true;
        log('Conectividade restaurada', 'success');
        onBackOnline();
    });
    
    window.addEventListener('offline', function() {
        isOnline = false;
        log('Modo offline detectado', 'warn');
        onGoOffline();
    });
}

function onBackOnline() {
    // Remover banner offline se existir
    const offlineBanner = document.querySelector('.offline-banner');
    if (offlineBanner) {
        offlineBanner.remove();
    }
    
    // Mostrar notificação de reconexão
    showToast('Conectividade restaurada!', 'success');
    
    // Sincronizar dados pendentes
    syncPendingData();
}

function onGoOffline() {
    // Mostrar banner de modo offline
    const banner = document.createElement('div');
    banner.className = 'offline-banner';
    banner.innerHTML = `
        <div class="offline-content">
            📡 Modo offline - Algumas funcionalidades podem estar limitadas
        </div>
    `;
    
    document.body.insertBefore(banner, document.body.firstChild);
    
    showToast('Modo offline ativo', 'warning');
}

// === NOTIFICAÇÕES PWA ===
function initializeNotifications() {
    if (!('Notification' in window)) {
        log('Notificações não suportadas', 'warn');
        return;
    }
    
    // Verificar permissão atual
    if (Notification.permission === 'default') {
        // Aguardar interação do usuário para solicitar
        setupNotificationPrompt();
    } else if (Notification.permission === 'granted') {
        log('Permissão de notificação concedida');
        setupPushNotifications();
    }
}

function setupNotificationPrompt() {
    // Criar prompt discreta para notificações
    const prompt = document.createElement('div');
    prompt.className = 'notification-prompt';
    prompt.innerHTML = `
        <div class="prompt-content">
            <span>🔔 Receber notificações de cashback?</span>
            <button onclick="PWA.requestNotifications()" class="btn-allow">Permitir</button>
            <button onclick="PWA.dismissNotifications()" class="btn-dismiss">Agora não</button>
        </div>
    `;
    
    // Mostrar apenas se o usuário estiver engajado (após 30 segundos)
    setTimeout(() => {
        if (!isInstalled) return; // Só mostrar se instalado
        document.body.appendChild(prompt);
    }, 30000);
}

function requestNotificationPermission() {
    Notification.requestPermission().then(function(permission) {
        if (permission === 'granted') {
            log('Permissão de notificação concedida', 'success');
            showToast('Notificações ativadas!', 'success');
            setupPushNotifications();
        } else {
            log('Permissão de notificação negada');
        }
        
        // Remover prompt
        const prompt = document.querySelector('.notification-prompt');
        if (prompt) prompt.remove();
    });
}

function setupPushNotifications() {
    if (!swRegistration) {
        log('Service Worker não disponível para push notifications', 'warn');
        return;
    }
    
    // Configurar push notifications se suportado
    if ('PushManager' in window) {
        // Implementar registro de push subscription
        log('Push notifications configuradas');
    }
}

// === SINCRONIZAÇÃO EM BACKGROUND ===
function setupBackgroundSync(registration) {
    if ('sync' in window.ServiceWorkerRegistration.prototype) {
        // Registrar eventos de sincronização
        registration.sync.register('background-sync')
            .then(() => log('Background sync registrado'))
            .catch(err => log('Erro ao registrar background sync: ' + err.message, 'error'));
    }
}

function syncPendingData() {
    // Enviar dados pendentes quando voltar online
    if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
        navigator.serviceWorker.controller.postMessage({
            action: 'sync-data'
        });
    }
}

// === EVENTOS PWA ===
function setupPWAEvents() {
    // Evento de mudança de orientação
    window.addEventListener('orientationchange', function() {
        setTimeout(function() {
            log('Orientação alterada para: ' + screen.orientation.angle);
        }, 100);
    });
    
    // Evento de mudança de visibilidade da página
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            log('App foi para background');
        } else {
            log('App voltou para foreground');
            // Verificar atualizações quando voltar
            checkForUpdates();
        }
    });
}

function checkForUpdates() {
    if (swRegistration) {
        swRegistration.update();
    }
}

// === INTERFACE PWA ===
function initializePWAInterface() {
    // Adicionar classes CSS específicas para PWA
    if (isInstalled) {
        document.body.classList.add('pwa-installed');
    }
    
    // Configurar viewport para mobile
    const viewport = document.querySelector('meta[name=viewport]');
    if (viewport && isInstalled) {
        viewport.setAttribute('content', 
            'width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover'
        );
    }
}

// === MENSAGENS DO SERVICE WORKER ===
function handleServiceWorkerMessage(event) {
    const { action, data } = event.data;
    
    switch(action) {
        case 'cache-updated':
            log('Cache atualizado');
            break;
        case 'offline-page':
            log('Página offline carregada');
            break;
        case 'sync-complete':
            log('Sincronização completa');
            showToast('Dados sincronizados!', 'success');
            break;
        default:
            log(`Mensagem do SW: ${action}`);
    }
}

// === UTILITÁRIOS ===
function showToast(message, type = 'info', duration = 3000) {
    const toast = document.createElement('div');
    toast.className = `pwa-toast pwa-toast-${type}`;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    // Animação de entrada
    requestAnimationFrame(() => {
        toast.classList.add('pwa-toast-show');
    });
    
    // Remover após duração especificada
    setTimeout(() => {
        toast.classList.remove('pwa-toast-show');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

function trackEvent(eventName, parameters = {}) {
    // Integração com Google Analytics ou outro sistema
    if (typeof gtag !== 'undefined') {
        gtag('event', eventName, {
            ...parameters,
            app_name: 'Klube Cash PWA',
            app_version: PWA_CONFIG.cache_version
        });
    }
    
    log(`Evento rastreado: ${eventName}`, 'info');
}

// === API PÚBLICA PWA ===
window.PWA = {
    // Métodos públicos para uso em outros scripts
    install: promptInstall,
    
    dismissInstall: function() {
        const banner = document.querySelector('.pwa-install-banner');
        if (banner) banner.remove();
        localStorage.setItem('install-dismissed', Date.now());
    },
    
    requestNotifications: requestNotificationPermission,
    
    dismissNotifications: function() {
        const prompt = document.querySelector('.notification-prompt');
        if (prompt) prompt.remove();
    },
    
    reloadApp: function() {
        if (swRegistration && swRegistration.waiting) {
            swRegistration.waiting.postMessage({ action: 'skip-waiting' });
        }
        window.location.reload();
    },
    
    dismissUpdate: function() {
        const banner = document.querySelector('.pwa-update-banner');
        if (banner) banner.remove();
    },
    
    isInstalled: function() {
        return isInstalled;
    },
    
    isOnline: function() {
        return isOnline;
    },
    
    getInstallPrompt: function() {
        return deferredPrompt;
    }
};

// === LOG FINAL ===
log('PWA Main script carregado e pronto!', 'success');