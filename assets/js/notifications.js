/**
 * KLUBE CASH - SISTEMA DE NOTIFICAÇÕES PUSH
 * Sistema completo de push notifications para PWA
 * Registro de tokens, gerenciamento de permissões e interações
 * 
 * @version 2.0
 * @author Klube Cash Development Team
 */

class KlubeCashNotifications {
    constructor() {
        this.isSupported = 'serviceWorker' in navigator && 'PushManager' in window;
        this.subscription = null;
        this.swRegistration = null;
        this.vapidPublicKey = null;
        this.userId = null;
        this.deviceId = null;
        
        // Configurações de notificação
        this.config = {
            applicationServerKey: null, // Será carregado dinamicamente
            userVisibleOnly: true,
            endpoint: '/api/pwa/notifications',
            retryAttempts: 3,
            retryDelay: 2000,
            permissionTimeout: 10000,
            showBadge: true,
            enableVibration: true,
            enableSound: true
        };
        
        // Status do sistema
        this.status = {
            permission: 'default',
            subscribed: false,
            initialized: false,
            lastSync: null
        };
        
        // Tipos de notificação
        this.notificationTypes = {
            CASHBACK_RECEIVED: 'cashback_received',
            CASHBACK_AVAILABLE: 'cashback_available',
            PAYMENT_CONFIRMED: 'payment_confirmed',
            TRANSACTION_PROCESSED: 'transaction_processed',
            PROMOTIONAL: 'promotional',
            SYSTEM_ALERT: 'system_alert',
            STORE_UPDATE: 'store_update',
            ACCOUNT_UPDATE: 'account_update'
        };
        
        // Configurações por tipo de notificação
        this.typeConfigs = {
            [this.notificationTypes.CASHBACK_RECEIVED]: {
                icon: '/assets/icons/cashback-icon.png',
                badge: '/assets/icons/badge-cashback.png',
                vibrate: [200, 100, 200],
                requireInteraction: false,
                silent: false,
                tag: 'cashback'
            },
            [this.notificationTypes.CASHBACK_AVAILABLE]: {
                icon: '/assets/icons/money-icon.png',
                badge: '/assets/icons/badge-money.png',
                vibrate: [300, 200, 300],
                requireInteraction: true,
                silent: false,
                tag: 'available'
            },
            [this.notificationTypes.PAYMENT_CONFIRMED]: {
                icon: '/assets/icons/check-icon.png',
                badge: '/assets/icons/badge-success.png',
                vibrate: [100, 50, 100],
                requireInteraction: false,
                silent: false,
                tag: 'payment'
            },
            [this.notificationTypes.PROMOTIONAL]: {
                icon: '/assets/icons/promo-icon.png',
                badge: '/assets/icons/badge-promo.png',
                vibrate: [200],
                requireInteraction: false,
                silent: true,
                tag: 'promo'
            }
        };
        
        // Estatísticas
        this.stats = {
            sent: 0,
            clicked: 0,
            dismissed: 0,
            errors: 0
        };
        
        this.init();
    }
    
    /**
     * Inicialização do sistema de notificações
     */
    async init() {
        try {
            if (!this.isSupported) {
                console.warn('⚠️ Push notifications não suportadas neste navegador');
                return false;
            }
            
            // Carregar configurações do servidor
            await this.loadServerConfig();
            
            // Obter informações do usuário
            await this.loadUserInfo();
            
            // Verificar status atual das permissões
            await this.checkPermissionStatus();
            
            // Registrar service worker se necessário
            await this.registerServiceWorker();
            
            // Verificar subscrição existente
            await this.checkExistingSubscription();
            
            // Setup de event listeners
            this.setupEventListeners();
            
            this.status.initialized = true;
            console.log('✅ Sistema de notificações inicializado');
            
            // Disparar evento personalizado
            this.dispatchEvent('notificationsReady', { status: this.status });
            
            return true;
            
        } catch (error) {
            console.error('❌ Erro ao inicializar notificações:', error);
            this.logError('notifications_init_error', error);
            return false;
        }
    }
    
    /**
     * Carregamento das configurações do servidor
     */
    async loadServerConfig() {
        try {
            const response = await fetch('/api/pwa/config', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (response.ok) {
                const config = await response.json();
                this.vapidPublicKey = config.vapidPublicKey;
                this.config.applicationServerKey = this.urlBase64ToUint8Array(config.vapidPublicKey);
                
                console.log('📡 Configurações do servidor carregadas');
            } else {
                throw new Error('Falha ao carregar configurações do servidor');
            }
            
        } catch (error) {
            console.error('❌ Erro ao carregar configurações:', error);
            // Usar chave padrão como fallback
            this.setDefaultVapidKey();
        }
    }
    
    /**
     * Definição de chave VAPID padrão como fallback
     */
    setDefaultVapidKey() {
        // Esta seria uma chave pública gerada no servidor
        const defaultKey = 'BMqSvZe8dGSiQEQYpzJu1h7WGDcfGcHsZVKdayQFjhE9MNbv1tsMfKg2h8YrIFQrPQBJX9jVtLfNPqCt9x4fQ3M';
        this.vapidPublicKey = defaultKey;
        this.config.applicationServerKey = this.urlBase64ToUint8Array(defaultKey);
        console.log('🔑 Usando chave VAPID padrão');
    }
    
    /**
     * Carregamento de informações do usuário
     */
    async loadUserInfo() {
        try {
            // Tentar obter ID do usuário do localStorage ou sessionStorage
            this.userId = localStorage.getItem('user_id') || sessionStorage.getItem('user_id');
            
            // Gerar ou recuperar device ID único
            this.deviceId = this.getOrCreateDeviceId();
            
            console.log(`👤 Usuário: ${this.userId}, Dispositivo: ${this.deviceId}`);
            
        } catch (error) {
            console.warn('⚠️ Não foi possível carregar informações do usuário:', error);
        }
    }
    
    /**
     * Geração ou recuperação de device ID único
     */
    getOrCreateDeviceId() {
        let deviceId = localStorage.getItem('klube_device_id');
        
        if (!deviceId) {
            // Gerar novo device ID baseado em características do dispositivo
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            ctx.textBaseline = 'top';
            ctx.font = '14px Arial';
            ctx.fillText('Klube Cash Device ID', 2, 2);
            
            const fingerprint = [
                navigator.userAgent,
                navigator.language,
                screen.width + 'x' + screen.height,
                new Date().getTimezoneOffset(),
                canvas.toDataURL()
            ].join('|');
            
            // Criar hash simples
            let hash = 0;
            for (let i = 0; i < fingerprint.length; i++) {
                const char = fingerprint.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash; // Converter para 32bit
            }
            
            deviceId = 'klube_' + Math.abs(hash).toString(36) + '_' + Date.now().toString(36);
            localStorage.setItem('klube_device_id', deviceId);
        }
        
        return deviceId;
    }
    
    /**
     * Verificação do status atual das permissões
     */
    async checkPermissionStatus() {
        if ('Notification' in window) {
            this.status.permission = Notification.permission;
            console.log(`🔔 Status da permissão: ${this.status.permission}`);
        }
    }
    
    /**
     * Registro do service worker
     */
    async registerServiceWorker() {
        try {
            if (navigator.serviceWorker.controller) {
                this.swRegistration = await navigator.serviceWorker.ready;
            } else {
                this.swRegistration = await navigator.serviceWorker.register('/sw.js', {
                    scope: '/'
                });
            }
            
            console.log('👷 Service Worker registrado para notificações');
            return this.swRegistration;
            
        } catch (error) {
            console.error('❌ Erro ao registrar Service Worker:', error);
            throw error;
        }
    }
    
    /**
     * Verificação de subscrição existente
     */
    async checkExistingSubscription() {
        try {
            if (!this.swRegistration) return false;
            
            this.subscription = await this.swRegistration.pushManager.getSubscription();
            
            if (this.subscription) {
                this.status.subscribed = true;
                console.log('📱 Subscrição push existente encontrada');
                
                // Verificar se a subscrição está válida no servidor
                await this.validateSubscriptionOnServer();
            }
            
            return !!this.subscription;
            
        } catch (error) {
            console.error('❌ Erro ao verificar subscrição existente:', error);
            return false;
        }
    }
    
    /**
     * Validação da subscrição no servidor
     */
    async validateSubscriptionOnServer() {
        try {
            const response = await fetch('/api/pwa/validate-subscription', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    subscription: this.subscription,
                    userId: this.userId,
                    deviceId: this.deviceId
                })
            });
            
            if (!response.ok) {
                console.warn('⚠️ Subscrição inválida no servidor, renovando...');
                await this.unsubscribe();
                return false;
            }
            
            console.log('✅ Subscrição válida no servidor');
            return true;
            
        } catch (error) {
            console.warn('⚠️ Erro ao validar subscrição no servidor:', error);
            return false;
        }
    }
    
    /**
     * Solicitação de permissão para notificações
     */
    async requestPermission() {
        try {
            if (!('Notification' in window)) {
                throw new Error('Notificações não suportadas neste navegador');
            }
            
            if (this.status.permission === 'granted') {
                return true;
            }
            
            if (this.status.permission === 'denied') {
                throw new Error('Permissão para notificações foi negada');
            }
            
            // Mostrar UI explicativa antes de solicitar permissão
            const userApproval = await this.showPermissionDialog();
            
            if (!userApproval) {
                return false;
            }
            
            // Solicitar permissão
            const permission = await this.requestNotificationPermission();
            
            this.status.permission = permission;
            
            if (permission === 'granted') {
                console.log('✅ Permissão para notificações concedida');
                this.showToast('Notificações ativadas com sucesso!', 'success');
                this.trackEvent('notification_permission_granted');
                return true;
            } else {
                console.log('❌ Permissão para notificações negada');
                this.showToast('Você pode ativar as notificações nas configurações do navegador.', 'info');
                this.trackEvent('notification_permission_denied');
                return false;
            }
            
        } catch (error) {
            console.error('❌ Erro ao solicitar permissão:', error);
            this.logError('permission_request_error', error);
            return false;
        }
    }
    
    /**
     * Exibição de diálogo explicativo antes da solicitação de permissão
     */
    async showPermissionDialog() {
        return new Promise((resolve) => {
            // Criar modal personalizado
            const modal = document.createElement('div');
            modal.className = 'notification-permission-modal';
            modal.innerHTML = `
                <div class="modal-backdrop"></div>
                <div class="modal-content">
                    <div class="modal-header">
                        <div class="modal-icon">🔔</div>
                        <h3>Ativar Notificações</h3>
                    </div>
                    <div class="modal-body">
                        <p>Receba notificações sobre:</p>
                        <ul>
                            <li>💰 Novo cashback disponível</li>
                            <li>✅ Pagamentos confirmados</li>
                            <li>🎯 Ofertas exclusivas</li>
                            <li>📢 Atualizações importantes</li>
                        </ul>
                        <p><small>Você pode desativar a qualquer momento nas configurações.</small></p>
                    </div>
                    <div class="modal-actions">
                        <button class="btn-secondary" data-action="cancel">Agora Não</button>
                        <button class="btn-primary" data-action="allow">Ativar Notificações</button>
                    </div>
                </div>
            `;
            
            // Event listeners
            modal.addEventListener('click', (e) => {
                const action = e.target.getAttribute('data-action');
                if (action === 'allow') {
                    document.body.removeChild(modal);
                    resolve(true);
                } else if (action === 'cancel' || e.target.classList.contains('modal-backdrop')) {
                    document.body.removeChild(modal);
                    resolve(false);
                }
            });
            
            document.body.appendChild(modal);
            
            // Auto-remover após timeout
            setTimeout(() => {
                if (document.body.contains(modal)) {
                    document.body.removeChild(modal);
                    resolve(false);
                }
            }, this.config.permissionTimeout);
        });
    }
    
    /**
     * Solicitação nativa de permissão
     */
    async requestNotificationPermission() {
        // Para navegadores modernos que suportam Promises
        if ('permission' in Notification && typeof Notification.requestPermission === 'function') {
            try {
                return await Notification.requestPermission();
            } catch (error) {
                // Fallback para versão callback
                return new Promise((resolve) => {
                    Notification.requestPermission(resolve);
                });
            }
        }
        
        // Fallback para navegadores mais antigos
        return new Promise((resolve) => {
            Notification.requestPermission(resolve);
        });
    }
    
    /**
     * Criação de subscrição push
     */
    async subscribe() {
        try {
            if (!this.swRegistration) {
                throw new Error('Service Worker não registrado');
            }
            
            if (this.status.permission !== 'granted') {
                const permitted = await this.requestPermission();
                if (!permitted) {
                    return false;
                }
            }
            
            // Criar subscrição
            this.subscription = await this.swRegistration.pushManager.subscribe({
                userVisibleOnly: this.config.userVisibleOnly,
                applicationServerKey: this.config.applicationServerKey
            });
            
            console.log('📱 Subscrição push criada');
            
            // Registrar no servidor
            const registered = await this.registerSubscriptionOnServer();
            
            if (registered) {
                this.status.subscribed = true;
                this.showToast('Notificações ativadas com sucesso!', 'success');
                this.trackEvent('push_subscription_created');
                return true;
            } else {
                await this.unsubscribe();
                return false;
            }
            
        } catch (error) {
            console.error('❌ Erro ao criar subscrição:', error);
            this.logError('subscription_error', error);
            
            if (error.name === 'NotSupportedError') {
                this.showToast('Push notifications não suportadas neste dispositivo.', 'error');
            } else if (error.name === 'NotAllowedError') {
                this.showToast('Permissão para notificações foi negada.', 'error');
            } else {
                this.showToast('Erro ao ativar notificações. Tente novamente.', 'error');
            }
            
            return false;
        }
    }
    
    /**
     * Registro da subscrição no servidor
     */
    async registerSubscriptionOnServer() {
        try {
            const subscriptionData = {
                subscription: this.subscription.toJSON(),
                userId: this.userId,
                deviceId: this.deviceId,
                userAgent: navigator.userAgent,
                timestamp: new Date().toISOString(),
                preferences: this.getNotificationPreferences()
            };
            
            const response = await fetch('/api/pwa/register-subscription', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(subscriptionData)
            });
            
            if (response.ok) {
                const result = await response.json();
                console.log('✅ Subscrição registrada no servidor:', result.subscriptionId);
                
                // Salvar ID da subscrição localmente
                localStorage.setItem('push_subscription_id', result.subscriptionId);
                
                return true;
            } else {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
        } catch (error) {
            console.error('❌ Erro ao registrar subscrição no servidor:', error);
            return false;
        }
    }
    
    /**
     * Obtenção das preferências de notificação do usuário
     */
    getNotificationPreferences() {
        const defaultPreferences = {
            cashback: true,
            payments: true,
            promotions: true,
            systemAlerts: true,
            storeUpdates: false,
            accountUpdates: true
        };
        
        try {
            const saved = localStorage.getItem('notification_preferences');
            return saved ? { ...defaultPreferences, ...JSON.parse(saved) } : defaultPreferences;
        } catch (error) {
            return defaultPreferences;
        }
    }
    
    /**
     * Cancelamento de subscrição
     */
    async unsubscribe() {
        try {
            if (this.subscription) {
                await this.subscription.unsubscribe();
                console.log('📱 Subscrição push cancelada');
            }
            
            // Remover do servidor
            await this.unregisterSubscriptionOnServer();
            
            this.subscription = null;
            this.status.subscribed = false;
            
            this.showToast('Notificações desativadas.', 'info');
            this.trackEvent('push_subscription_cancelled');
            
            return true;
            
        } catch (error) {
            console.error('❌ Erro ao cancelar subscrição:', error);
            this.logError('unsubscription_error', error);
            return false;
        }
    }
    
    /**
     * Remoção da subscrição do servidor
     */
    async unregisterSubscriptionOnServer() {
        try {
            const subscriptionId = localStorage.getItem('push_subscription_id');
            
            if (subscriptionId) {
                await fetch('/api/pwa/unregister-subscription', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        subscriptionId: subscriptionId,
                        deviceId: this.deviceId
                    })
                });
                
                localStorage.removeItem('push_subscription_id');
                console.log('✅ Subscrição removida do servidor');
            }
            
        } catch (error) {
            console.warn('⚠️ Erro ao remover subscrição do servidor:', error);
        }
    }
    
    /**
     * Exibição de notificação local
     */
    async showLocalNotification(title, options = {}) {
        try {
            if (this.status.permission !== 'granted') {
                console.warn('⚠️ Permissão para notificações não concedida');
                return false;
            }
            
            const defaultOptions = {
                icon: '/assets/icons/icon-192x192.png',
                badge: '/assets/icons/badge-72x72.png',
                image: null,
                vibrate: [200, 100, 200],
                requireInteraction: false,
                silent: false,
                tag: 'klube-cash',
                renotify: false,
                timestamp: Date.now(),
                data: {}
            };
            
            const notificationOptions = { ...defaultOptions, ...options };
            
            // Aplicar configurações específicas do tipo
            if (options.type && this.typeConfigs[options.type]) {
                const typeConfig = this.typeConfigs[options.type];
                Object.assign(notificationOptions, typeConfig);
            }
            
            const notification = new Notification(title, notificationOptions);
            
            // Event listeners
            notification.onclick = (event) => {
                event.preventDefault();
                this.handleNotificationClick(notification, notificationOptions.data);
            };
            
            notification.onclose = () => {
                this.stats.dismissed++;
                this.trackEvent('notification_dismissed', { tag: notificationOptions.tag });
            };
            
            notification.onerror = (error) => {
                this.stats.errors++;
                this.logError('notification_display_error', error);
            };
            
            this.stats.sent++;
            this.trackEvent('notification_shown', { 
                tag: notificationOptions.tag, 
                type: options.type 
            });
            
            console.log('🔔 Notificação local exibida:', title);
            return true;
            
        } catch (error) {
            console.error('❌ Erro ao exibir notificação local:', error);
            this.logError('local_notification_error', error);
            return false;
        }
    }
    
    /**
     * Tratamento de clique em notificação
     */
    handleNotificationClick(notification, data = {}) {
        this.stats.clicked++;
        this.trackEvent('notification_clicked', { 
            tag: notification.tag, 
            data: data 
        });
        
        // Focar na janela da aplicação
        if (window.focus) {
            window.focus();
        }
        
        // Fechar a notificação
        notification.close();
        
        // Executar ação baseada nos dados
        if (data.action) {
            this.executeNotificationAction(data.action, data);
        }
        
        console.log('👆 Notificação clicada:', notification.tag);
    }
    
    /**
     * Execução de ação da notificação
     */
    executeNotificationAction(action, data) {
        switch (action) {
            case 'open_dashboard':
                window.location.href = '/client/dashboard';
                break;
                
            case 'open_cashback':
                window.location.href = '/client/cashback-history';
                break;
                
            case 'open_transaction':
                if (data.transactionId) {
                    window.location.href = `/client/transaction/${data.transactionId}`;
                }
                break;
                
            case 'open_store':
                if (data.storeId) {
                    window.location.href = `/client/store/${data.storeId}`;
                }
                break;
                
            case 'open_profile':
                window.location.href = '/client/profile';
                break;
                
            default:
                console.log('Ação de notificação desconhecida:', action);
        }
    }
    
    /**
     * Atualização das preferências de notificação
     */
    async updatePreferences(preferences) {
        try {
            // Salvar localmente
            localStorage.setItem('notification_preferences', JSON.stringify(preferences));
            
            // Enviar para o servidor
            const response = await fetch('/api/pwa/update-preferences', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    userId: this.userId,
                    deviceId: this.deviceId,
                    preferences: preferences
                })
            });
            
            if (response.ok) {
                console.log('✅ Preferências de notificação atualizadas');
                this.trackEvent('notification_preferences_updated', preferences);
                return true;
            } else {
                throw new Error('Erro ao atualizar preferências no servidor');
            }
            
        } catch (error) {
            console.error('❌ Erro ao atualizar preferências:', error);
            return false;
        }
    }
    
    /**
     * Obtenção de estatísticas de notificação
     */
    getStats() {
        return {
            ...this.stats,
            isSupported: this.isSupported,
            permission: this.status.permission,
            subscribed: this.status.subscribed,
            lastSync: this.status.lastSync
        };
    }
    
    /**
     * Setup de event listeners
     */
    setupEventListeners() {
        // Listener para mudanças de visibilidade da página
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && this.status.subscribed) {
                // Sincronizar notificações quando a página se torna visível
                this.syncNotifications();
            }
        });
        
        // Listener para mudanças na conectividade
        window.addEventListener('online', () => {
            if (this.status.subscribed) {
                this.syncNotifications();
            }
        });
        
        // Listener para atualizações do service worker
        if (navigator.serviceWorker) {
            navigator.serviceWorker.addEventListener('message', (event) => {
                if (event.data && event.data.type === 'PUSH_RECEIVED') {
                    this.handlePushMessage(event.data.payload);
                }
            });
        }
    }
    
    /**
     * Sincronização de notificações com o servidor
     */
    async syncNotifications() {
        try {
            const response = await fetch('/api/pwa/sync-notifications', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    userId: this.userId,
                    deviceId: this.deviceId,
                    lastSync: this.status.lastSync
                })
            });
            
            if (response.ok) {
                const data = await response.json();
                
                if (data.notifications && data.notifications.length > 0) {
                    // Processar notificações perdidas
                    for (const notification of data.notifications) {
                        await this.showLocalNotification(
                            notification.title,
                            {
                                body: notification.body,
                                type: notification.type,
                                data: notification.data
                            }
                        );
                    }
                }
                
                this.status.lastSync = new Date().toISOString();
                console.log('🔄 Notificações sincronizadas');
            }
            
        } catch (error) {
            console.warn('⚠️ Erro na sincronização de notificações:', error);
        }
    }
    
    /**
     * Tratamento de mensagem push recebida
     */
    handlePushMessage(payload) {
        console.log('📨 Mensagem push recebida:', payload);
        
        // Salvar notificação offline se necessário
        if (window.KlubeCashOffline) {
            window.KlubeCashOffline.saveNotifications([{
                id: payload.id || Date.now(),
                titulo: payload.title,
                mensagem: payload.body,
                tipo: payload.type || 'system_alert',
                lida: false,
                data_criacao: new Date().toISOString(),
                sincronizado: true
            }]);
        }
        
        this.trackEvent('push_message_received', { type: payload.type });
    }
    
    /**
     * Teste de notificação
     */
    async testNotification() {
        return await this.showLocalNotification(
            'Teste - Klube Cash',
            {
                body: 'Suas notificações estão funcionando perfeitamente! 🎉',
                icon: '/assets/icons/icon-192x192.png',
                badge: '/assets/icons/badge-72x72.png',
                tag: 'test',
                requireInteraction: false,
                data: { action: 'open_dashboard' }
            }
        );
    }
    
    // === MÉTODOS AUXILIARES ===
    
    /**
     * Conversão de base64 para Uint8Array
     */
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');
        
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        
        return outputArray;
    }
    
    /**
     * Dispatch de eventos personalizados
     */
    dispatchEvent(eventName, detail = {}) {
        const event = new CustomEvent(`klubeCash:${eventName}`, { detail });
        window.dispatchEvent(event);
    }
    
    /**
     * Utilitários de interface
     */
    showToast(message, type = 'info') {
        if (window.KlubeCash && window.KlubeCash.showToast) {
            window.KlubeCash.showToast(message, type);
        } else {
            console.log(`[${type.toUpperCase()}] ${message}`);
        }
    }
    
    trackEvent(eventName, data = {}) {
        if (window.KlubeCash && window.KlubeCash.trackEvent) {
            window.KlubeCash.trackEvent(eventName, data);
        }
    }
    
    logError(errorName, error, data = {}) {
        console.error(`[${errorName}]`, error);
        this.trackEvent('error', { 
            name: errorName, 
            message: error.message, 
            stack: error.stack,
            ...data 
        });
    }
}

// === INICIALIZAÇÃO GLOBAL ===
let klubeCashNotifications = null;

// Aguardar DOM estar pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNotifications);
} else {
    initNotifications();
}

function initNotifications() {
    try {
        klubeCashNotifications = new KlubeCashNotifications();
        
        // Disponibilizar globalmente
        window.KlubeCashNotifications = klubeCashNotifications;
        
        console.log('🚀 Sistema de notificações do Klube Cash inicializado');
    } catch (error) {
        console.error('❌ Erro crítico ao inicializar notificações:', error);
    }
}

// === API PÚBLICA ===
window.klube = window.klube || {};
window.klube.notifications = {
    // Métodos públicos para uso em outras partes da aplicação
    async subscribe() {
        if (klubeCashNotifications) {
            return await klubeCashNotifications.subscribe();
        }
        return false;
    },
    
    async unsubscribe() {
        if (klubeCashNotifications) {
            return await klubeCashNotifications.unsubscribe();
        }
        return false;
    },
    
    async requestPermission() {
        if (klubeCashNotifications) {
            return await klubeCashNotifications.requestPermission();
        }
        return false;
    },
    
    async showNotification(title, options) {
        if (klubeCashNotifications) {
            return await klubeCashNotifications.showLocalNotification(title, options);
        }
        return false;
    },
    
    async updatePreferences(preferences) {
        if (klubeCashNotifications) {
            return await klubeCashNotifications.updatePreferences(preferences);
        }
        return false;
    },
    
    getStats() {
        if (klubeCashNotifications) {
            return klubeCashNotifications.getStats();
        }
        return null;
    },
    
    async test() {
        if (klubeCashNotifications) {
            return await klubeCashNotifications.testNotification();
        }
        return false;
    }
};

// === EXPORT PARA MÓDULOS ===
if (typeof module !== 'undefined' && module.exports) {
    module.exports = KlubeCashNotifications;
}