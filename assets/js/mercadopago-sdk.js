// assets/js/mercadopago-sdk.js
// Implementação oficial do MercadoPago.js V2

(function() {
    'use strict';
    
    // Configuração do SDK
    const MP_CONFIG = {
        public_key: 'APP_USR-60bd9502-2ea5-46c8-80b5-765f10277949',
        environment: 'production', // ou 'sandbox' para testes
        locale: 'pt-BR',
        debug: false
    };
    
    // Função para carregar o SDK do Mercado Pago dinamicamente
    function loadMercadoPagoSDK() {
        return new Promise((resolve, reject) => {
            // Verificar se já está carregado
            if (window.MercadoPago && typeof window.MercadoPago === 'function') {
                resolve(window.MercadoPago);
                return;
            }
            
            // Criar script tag para carregar o SDK
            const script = document.createElement('script');
            script.src = 'https://sdk.mercadopago.com/js/v2';
            script.async = true;
            script.defer = true;
            
            script.onload = function() {
                if (window.MercadoPago) {
                    resolve(window.MercadoPago);
                } else {
                    reject(new Error('MercadoPago SDK não carregou corretamente'));
                }
            };
            
            script.onerror = function() {
                reject(new Error('Erro ao carregar MercadoPago SDK'));
            };
            
            document.head.appendChild(script);
        });
    }
    
    // Função para inicializar o SDK
    async function initMercadoPagoSDK() {
        try {
            console.log('Carregando MercadoPago SDK...');
            
            const MercadoPago = await loadMercadoPagoSDK();
            
            // Inicializar com a chave pública
            const mp = new MercadoPago(MP_CONFIG.public_key, {
                locale: MP_CONFIG.locale
            });
            
            console.log('✅ MercadoPago SDK carregado com sucesso');
            
            // Disponibilizar globalmente
            window.MPInstance = mp;
            window.MPConfig = MP_CONFIG;
            
            // Criar device session (para segurança)
            if (mp.deviceSessionId) {
                try {
                    const deviceSessionId = await mp.deviceSessionId();
                    window.MPDeviceSessionId = deviceSessionId;
                    console.log('✅ Device Session ID criado:', deviceSessionId);
                } catch (deviceError) {
                    console.warn('⚠️ Erro ao criar Device Session ID:', deviceError);
                }
            }
            
            return mp;
            
        } catch (error) {
            console.error('❌ Erro ao inicializar MercadoPago SDK:', error);
            throw error;
        }
    }
    
    // Função para obter informações do dispositivo (melhorada)
    function getDeviceInformation() {
        const deviceInfo = {
            // Informações básicas do browser
            userAgent: navigator.userAgent,
            language: navigator.language,
            platform: navigator.platform,
            
            // Informações de tela
            screenWidth: screen.width,
            screenHeight: screen.height,
            screenColorDepth: screen.colorDepth,
            
            // Informações de janela
            windowWidth: window.innerWidth,
            windowHeight: window.innerHeight,
            
            // Timezone
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            timezoneOffset: new Date().getTimezoneOffset(),
            
            // Timestamp
            timestamp: Date.now(),
            
            // Canvas fingerprint (para device ID único)
            canvasFingerprint: generateCanvasFingerprint(),
            
            // Informações de conexão (se disponível)
            connection: navigator.connection ? {
                effectiveType: navigator.connection.effectiveType,
                downlink: navigator.connection.downlink,
                rtt: navigator.connection.rtt
            } : null
        };
        
        return deviceInfo;
    }
    
    // Função para gerar fingerprint do canvas
    function generateCanvasFingerprint() {
        try {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            // Configurar canvas
            canvas.width = 200;
            canvas.height = 50;
            
            // Desenhar texto com diferentes configurações
            ctx.textBaseline = 'top';
            ctx.font = '14px Arial';
            ctx.fillStyle = '#f60';
            ctx.fillText('MercadoPago Device Fingerprint 🔒', 2, 2);
            
            ctx.fillStyle = '#069';
            ctx.font = '11px Times';
            ctx.fillText('KlubeCash Security Layer', 4, 20);
            
            // Retornar hash do canvas
            return canvas.toDataURL();
        } catch (error) {
            console.warn('Erro ao gerar canvas fingerprint:', error);
            return 'canvas_error_' + Date.now();
        }
    }
    
    // Função para gerar Device ID otimizado
    function generateOptimizedDeviceId() {
        const deviceInfo = getDeviceInformation();
        
        // Criar string única baseada nas informações do dispositivo
        const deviceString = JSON.stringify({
            ua: deviceInfo.userAgent.substr(0, 100), // Limitar tamanho
            lang: deviceInfo.language,
            screen: `${deviceInfo.screenWidth}x${deviceInfo.screenHeight}`,
            tz: deviceInfo.timezone,
            canvas: deviceInfo.canvasFingerprint.substr(-50), // Últimos 50 chars
            connection: deviceInfo.connection ? deviceInfo.connection.effectiveType : 'unknown'
        });
        
        // Gerar hash simples (não criptográfico, só para device ID)
        let hash = 0;
        for (let i = 0; i < deviceString.length; i++) {
            const char = deviceString.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash; // Convert to 32-bit integer
        }
        
        // Criar device ID no formato esperado pelo MP
        const deviceId = 'web_klube_' + Math.abs(hash).toString(16) + '_' + Date.now().toString(36);
        
        return deviceId;
    }
    
    // Função para validar e armazenar device ID
    function storeDeviceId() {
        const deviceId = generateOptimizedDeviceId();
        
        try {
            // Armazenar em múltiplos locais
            localStorage.setItem('mp_device_id', deviceId);
            sessionStorage.setItem('mp_device_id', deviceId);
            
            // Cookie com expiração de 1 ano
            const expires = new Date();
            expires.setFullYear(expires.getFullYear() + 1);
            document.cookie = `mp_device_id=${deviceId}; expires=${expires.toUTCString()}; path=/; secure; samesite=strict`;
            
            // Disponibilizar globalmente
            window.MPCurrentDeviceId = deviceId;
            
            console.log('✅ Device ID armazenado:', deviceId);
            return deviceId;
            
        } catch (error) {
            console.error('❌ Erro ao armazenar Device ID:', error);
            return deviceId; // Retorna mesmo se não conseguir armazenar
        }
    }
    
    // Função para recuperar device ID armazenado
    function getStoredDeviceId() {
        try {
            // Tentar localStorage primeiro
            let deviceId = localStorage.getItem('mp_device_id');
            if (deviceId) return deviceId;
            
            // Tentar sessionStorage
            deviceId = sessionStorage.getItem('mp_device_id');
            if (deviceId) return deviceId;
            
            // Tentar cookie
            const cookies = document.cookie.split(';');
            for (let cookie of cookies) {
                const [name, value] = cookie.trim().split('=');
                if (name === 'mp_device_id') {
                    return value;
                }
            }
            
            return null;
        } catch (error) {
            console.warn('Erro ao recuperar Device ID:', error);
            return null;
        }
    }
    
    // Função principal de inicialização
    async function initialize() {
        try {
            console.log('🚀 Inicializando MercadoPago Integration...');
            
            // 1. Carregar SDK
            const mp = await initMercadoPagoSDK();
            
            // 2. Configurar Device ID
            let deviceId = getStoredDeviceId();
            if (!deviceId) {
                deviceId = storeDeviceId();
            } else {
                window.MPCurrentDeviceId = deviceId;
                console.log('✅ Device ID recuperado:', deviceId);
            }
            
            // 3. Adicionar device ID aos formulários
            addDeviceIdToForms(deviceId);
            
            // 4. Configurar event listeners para novos formulários
            observeNewForms(deviceId);
            
            console.log('✅ MercadoPago Integration inicializada com sucesso');
            
            return {
                mp: mp,
                deviceId: deviceId,
                deviceInfo: getDeviceInformation()
            };
            
        } catch (error) {
            console.error('❌ Erro na inicialização do MercadoPago:', error);
            throw error;
        }
    }
    
    // Função para adicionar device ID a formulários
    function addDeviceIdToForms(deviceId) {
        const forms = document.querySelectorAll('form');
        
        forms.forEach(form => {
            // Remover field existente se houver
            const existingField = form.querySelector('input[name="device_id"]');
            if (existingField) {
                existingField.remove();
            }
            
            // Adicionar novo field
            const deviceField = document.createElement('input');
            deviceField.type = 'hidden';
            deviceField.name = 'device_id';
            deviceField.value = deviceId;
            form.appendChild(deviceField);
        });
        
        console.log(`✅ Device ID adicionado a ${forms.length} formulários`);
    }
    
    // Função para observar novos formulários (usando MutationObserver)
    function observeNewForms(deviceId) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) { // Element node
                        // Verificar se é um formulário
                        if (node.tagName === 'FORM') {
                            addDeviceIdToForms(deviceId);
                        }
                        // Verificar se contém formulários
                        const forms = node.querySelectorAll ? node.querySelectorAll('form') : [];
                        if (forms.length > 0) {
                            addDeviceIdToForms(deviceId);
                        }
                    }
                });
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
    
    // API pública
    window.KlubeCashMP = {
        init: initialize,
        getDeviceId: () => window.MPCurrentDeviceId || getStoredDeviceId() || storeDeviceId(),
        getMP: () => window.MPInstance,
        getConfig: () => window.MPConfig,
        getDeviceInfo: getDeviceInformation,
        addDeviceIdToForms: addDeviceIdToForms
    };
    
    // Auto-inicializar quando DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
    
})();

// Função helper para compatibilidade
function getPaymentDeviceId() {
    return window.KlubeCashMP ? window.KlubeCashMP.getDeviceId() : 'fallback_' + Date.now();
}