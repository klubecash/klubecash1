// assets/js/mercadopago-device.js
// Script para gerar Device ID conforme recomendações do Mercado Pago

(function() {
    'use strict';
    
    // Função para gerar device ID único
    function generateDeviceId() {
        // Coletando informações do browser para device fingerprinting
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        ctx.textBaseline = 'top';
        ctx.font = '14px Arial';
        ctx.fillText('Device fingerprint', 2, 2);
        
        const deviceInfo = {
            screen: screen.width + 'x' + screen.height,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            language: navigator.language,
            platform: navigator.platform,
            canvas: canvas.toDataURL(),
            timestamp: Date.now(),
            random: Math.random().toString(36).substr(2, 9)
        };
        
        // Criar hash único
        const deviceString = JSON.stringify(deviceInfo);
        const deviceId = 'web_' + btoa(deviceString).substr(0, 32).replace(/[^a-zA-Z0-9]/g, '');
        
        return deviceId;
    }
    
    // Função para armazenar device ID
    function storeDeviceId(deviceId) {
        try {
            // Armazenar no localStorage
            localStorage.setItem('mp_device_id', deviceId);
            
            // Armazenar também no sessionStorage como fallback
            sessionStorage.setItem('mp_device_id', deviceId);
            
            // Criar cookie como último fallback
            const expires = new Date();
            expires.setFullYear(expires.getFullYear() + 1);
            document.cookie = `mp_device_id=${deviceId}; expires=${expires.toUTCString()}; path=/; secure; samesite=strict`;
            
            return true;
        } catch (error) {
            console.warn('Erro ao armazenar device ID:', error);
            return false;
        }
    }
    
    // Função para recuperar device ID
    function getDeviceId() {
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
            console.warn('Erro ao recuperar device ID:', error);
            return null;
        }
    }
    
    // Função principal para inicializar device ID
    function initializeDeviceId() {
        let deviceId = getDeviceId();
        
        if (!deviceId) {
            deviceId = generateDeviceId();
            storeDeviceId(deviceId);
        }
        
        // Disponibilizar globalmente
        window.MPDeviceId = deviceId;
        
        // Adicionar a formulários automaticamente
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
        
        console.log('Mercado Pago Device ID inicializado:', deviceId);
        
        return deviceId;
    }
    
    // Função para validar device ID
    function validateDeviceId(deviceId) {
        if (!deviceId || typeof deviceId !== 'string') {
            return false;
        }
        
        // Verificar tamanho mínimo
        if (deviceId.length < 10) {
            return false;
        }
        
        // Verificar caracteres válidos
        if (!/^[a-zA-Z0-9_]+$/.test(deviceId)) {
            return false;
        }
        
        return true;
    }
    
    // Função para logging de device ID
    function logDeviceMetrics(deviceId) {
        if (window.console && console.log) {
            console.log('=== Mercado Pago Device Metrics ===');
            console.log('Device ID:', deviceId);
            console.log('Browser:', navigator.userAgent);
            console.log('Screen:', screen.width + 'x' + screen.height);
            console.log('Timezone:', Intl.DateTimeFormat().resolvedOptions().timeZone);
            console.log('Language:', navigator.language);
            console.log('=====================================');
        }
    }
    
    // API pública
    window.MercadoPagoDevice = {
        init: initializeDeviceId,
        get: getDeviceId,
        generate: generateDeviceId,
        store: storeDeviceId,
        validate: validateDeviceId,
        log: logDeviceMetrics
    };
    
    // Auto-inicializar quando DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            const deviceId = initializeDeviceId();
            logDeviceMetrics(deviceId);
        });
    } else {
        const deviceId = initializeDeviceId();
        logDeviceMetrics(deviceId);
    }
    
})();

// Função helper para usar em pagamentos
function getPaymentDeviceId() {
    return window.MPDeviceId || window.MercadoPagoDevice.get() || window.MercadoPagoDevice.generate();
}