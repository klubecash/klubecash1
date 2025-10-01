/**
 * KLUBE CASH - OFFLINE STORAGE SYSTEM
 * Sistema de armazenamento offline com IndexedDB
 * Sincronização automática e cache management
 * 
 * @version 2.0
 * @author Klube Cash Development Team
 */

class KlubeCashOfflineStorage {
    constructor() {
        this.dbName = 'KlubeCashDB';
        this.dbVersion = 2;
        this.db = null;
        this.isOnline = navigator.onLine;
        this.syncQueue = [];
        this.syncInProgress = false;
        
        // Configurações de sincronização
        this.syncConfig = {
            retryAttempts: 3,
            retryDelay: 5000, // 5 segundos
            batchSize: 50,
            autoSyncInterval: 30000 // 30 segundos
        };
        
        // Schemas das tabelas IndexedDB
        this.schemas = {
            userProfile: 'id, nome, email, cpf, saldo_disponivel, saldo_pendente, foto_perfil, ultimo_sync',
            transactions: 'id, usuario_id, loja_id, loja_nome, valor_total, valor_cashback, data_transacao, status, sincronizado',
            stores: 'id, nome, logo, categoria, porcentagem_cashback, descricao, ativo, ultimo_sync',
            cashbackHistory: 'id, transacao_id, valor, data_liberacao, status, loja_nome, sincronizado',
            notifications: 'id, titulo, mensagem, tipo, lida, data_criacao, sincronizado',
            syncQueue: '++id, table_name, operation, data, timestamp, retry_count',
            appConfig: 'key, value, ultimo_sync'
        };
        
        this.init();
    }
    
    /**
     * Inicialização do sistema de storage offline
     */
    async init() {
        try {
            await this.openDatabase();
            this.setupEventListeners();
            this.startAutoSync();
            
            console.log('✅ Sistema de storage offline inicializado');
            this.logEvent('offline_storage_initialized');
        } catch (error) {
            console.error('❌ Erro ao inicializar storage offline:', error);
            this.logError('offline_storage_init_error', error);
        }
    }
    
    /**
     * Abertura e configuração do banco IndexedDB
     */
    async openDatabase() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);
            
            request.onerror = () => {
                reject(new Error('Erro ao abrir IndexedDB: ' + request.error));
            };
            
            request.onsuccess = (event) => {
                this.db = event.target.result;
                console.log('📦 IndexedDB conectado com sucesso');
                resolve(this.db);
            };
            
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                // Criar ou atualizar object stores
                this.createObjectStores(db);
                
                console.log('🔄 Banco IndexedDB atualizado para versão', this.dbVersion);
            };
        });
    }
    
    /**
     * Criação das object stores (tabelas) do IndexedDB
     */
    createObjectStores(db) {
        // Object store para perfil do usuário
        if (!db.objectStoreNames.contains('userProfile')) {
            const userStore = db.createObjectStore('userProfile', { keyPath: 'id' });
            userStore.createIndex('email', 'email', { unique: true });
        }
        
        // Object store para transações
        if (!db.objectStoreNames.contains('transactions')) {
            const transStore = db.createObjectStore('transactions', { keyPath: 'id' });
            transStore.createIndex('usuario_id', 'usuario_id');
            transStore.createIndex('data_transacao', 'data_transacao');
            transStore.createIndex('sincronizado', 'sincronizado');
        }
        
        // Object store para lojas parceiras
        if (!db.objectStoreNames.contains('stores')) {
            const storesStore = db.createObjectStore('stores', { keyPath: 'id' });
            storesStore.createIndex('categoria', 'categoria');
            storesStore.createIndex('ativo', 'ativo');
        }
        
        // Object store para histórico de cashback
        if (!db.objectStoreNames.contains('cashbackHistory')) {
            const cashbackStore = db.createObjectStore('cashbackHistory', { keyPath: 'id' });
            cashbackStore.createIndex('transacao_id', 'transacao_id');
            cashbackStore.createIndex('data_liberacao', 'data_liberacao');
            cashbackStore.createIndex('sincronizado', 'sincronizado');
        }
        
        // Object store para notificações
        if (!db.objectStoreNames.contains('notifications')) {
            const notifStore = db.createObjectStore('notifications', { keyPath: 'id' });
            notifStore.createIndex('lida', 'lida');
            notifStore.createIndex('data_criacao', 'data_criacao');
            notifStore.createIndex('sincronizado', 'sincronizado');
        }
        
        // Object store para fila de sincronização
        if (!db.objectStoreNames.contains('syncQueue')) {
            const syncStore = db.createObjectStore('syncQueue', { keyPath: 'id', autoIncrement: true });
            syncStore.createIndex('table_name', 'table_name');
            syncStore.createIndex('timestamp', 'timestamp');
        }
        
        // Object store para configurações da app
        if (!db.objectStoreNames.contains('appConfig')) {
            db.createObjectStore('appConfig', { keyPath: 'key' });
        }
    }
    
    /**
     * Setup dos event listeners para monitoramento de conectividade
     */
    setupEventListeners() {
        // Detectar mudanças na conectividade
        window.addEventListener('online', () => {
            this.isOnline = true;
            console.log('🌐 Conexão restaurada - iniciando sincronização');
            this.triggerSync();
            this.showToast('Conexão restaurada. Sincronizando dados...', 'success');
        });
        
        window.addEventListener('offline', () => {
            this.isOnline = false;
            console.log('📡 Aplicação em modo offline');
            this.showToast('Modo offline ativado. Seus dados serão salvos localmente.', 'info');
        });
        
        // Listener para antes de fechar a página
        window.addEventListener('beforeunload', () => {
            if (this.syncQueue.length > 0) {
                this.triggerSync();
            }
        });
    }
    
    /**
     * Salvamento de dados do perfil do usuário
     */
    async saveUserProfile(userData) {
        try {
            // Adicionar timestamp de sincronização
            userData.ultimo_sync = new Date().toISOString();
            
            await this.setData('userProfile', userData);
            
            // Adicionar à fila de sincronização se estiver offline
            if (!this.isOnline) {
                await this.addToSyncQueue('userProfile', 'UPDATE', userData);
            }
            
            console.log('👤 Perfil do usuário salvo offline');
            return true;
        } catch (error) {
            console.error('❌ Erro ao salvar perfil offline:', error);
            return false;
        }
    }
    
    /**
     * Recuperação de dados do perfil do usuário
     */
    async getUserProfile(userId) {
        try {
            const profile = await this.getData('userProfile', userId);
            return profile || null;
        } catch (error) {
            console.error('❌ Erro ao recuperar perfil offline:', error);
            return null;
        }
    }
    
    /**
     * Salvamento de transações offline
     */
    async saveTransaction(transactionData) {
        try {
            // Marcar como não sincronizado
            transactionData.sincronizado = false;
            transactionData.data_criacao_offline = new Date().toISOString();
            
            await this.setData('transactions', transactionData);
            
            // Adicionar à fila de sincronização
            await this.addToSyncQueue('transactions', 'INSERT', transactionData);
            
            console.log('💰 Transação salva offline:', transactionData.id);
            
            // Atualizar saldo local se possível
            await this.updateLocalBalance(transactionData);
            
            return true;
        } catch (error) {
            console.error('❌ Erro ao salvar transação offline:', error);
            return false;
        }
    }
    
    /**
     * Recuperação de transações offline
     */
    async getTransactions(filters = {}) {
        try {
            const allTransactions = await this.getAllData('transactions');
            
            // Aplicar filtros se fornecidos
            if (Object.keys(filters).length === 0) {
                return allTransactions;
            }
            
            return allTransactions.filter(transaction => {
                if (filters.usuario_id && transaction.usuario_id !== filters.usuario_id) {
                    return false;
                }
                
                if (filters.status && transaction.status !== filters.status) {
                    return false;
                }
                
                if (filters.data_inicio) {
                    const transDate = new Date(transaction.data_transacao);
                    const startDate = new Date(filters.data_inicio);
                    if (transDate < startDate) return false;
                }
                
                if (filters.data_fim) {
                    const transDate = new Date(transaction.data_transacao);
                    const endDate = new Date(filters.data_fim);
                    if (transDate > endDate) return false;
                }
                
                return true;
            });
        } catch (error) {
            console.error('❌ Erro ao recuperar transações offline:', error);
            return [];
        }
    }
    
    /**
     * Salvamento de lojas parceiras
     */
    async saveStores(storesData) {
        try {
            const transaction = this.db.transaction(['stores'], 'readwrite');
            const store = transaction.objectStore('stores');
            
            // Limpar lojas antigas
            await store.clear();
            
            // Salvar novas lojas
            for (const storeData of storesData) {
                storeData.ultimo_sync = new Date().toISOString();
                await store.add(storeData);
            }
            
            console.log(`🏪 ${storesData.length} lojas salvas offline`);
            return true;
        } catch (error) {
            console.error('❌ Erro ao salvar lojas offline:', error);
            return false;
        }
    }
    
    /**
     * Recuperação de lojas parceiras
     */
    async getStores(filters = {}) {
        try {
            const allStores = await this.getAllData('stores');
            
            if (Object.keys(filters).length === 0) {
                return allStores.filter(store => store.ativo);
            }
            
            return allStores.filter(store => {
                if (!store.ativo) return false;
                
                if (filters.categoria && store.categoria !== filters.categoria) {
                    return false;
                }
                
                if (filters.busca) {
                    const searchTerm = filters.busca.toLowerCase();
                    if (!store.nome.toLowerCase().includes(searchTerm)) {
                        return false;
                    }
                }
                
                return true;
            });
        } catch (error) {
            console.error('❌ Erro ao recuperar lojas offline:', error);
            return [];
        }
    }
    
    /**
     * Salvamento de histórico de cashback
     */
    async saveCashbackHistory(cashbackData) {
        try {
            const transaction = this.db.transaction(['cashbackHistory'], 'readwrite');
            const store = transaction.objectStore('cashbackHistory');
            
            for (const item of cashbackData) {
                item.sincronizado = true;
                item.ultimo_sync = new Date().toISOString();
                await store.put(item);
            }
            
            console.log(`💸 ${cashbackData.length} registros de cashback salvos offline`);
            return true;
        } catch (error) {
            console.error('❌ Erro ao salvar histórico de cashback:', error);
            return false;
        }
    }
    
    /**
     * Recuperação de histórico de cashback
     */
    async getCashbackHistory(filters = {}) {
        try {
            const allCashback = await this.getAllData('cashbackHistory');
            
            if (Object.keys(filters).length === 0) {
                return allCashback.sort((a, b) => new Date(b.data_liberacao) - new Date(a.data_liberacao));
            }
            
            const filtered = allCashback.filter(item => {
                if (filters.status && item.status !== filters.status) {
                    return false;
                }
                
                if (filters.data_inicio) {
                    const itemDate = new Date(item.data_liberacao);
                    const startDate = new Date(filters.data_inicio);
                    if (itemDate < startDate) return false;
                }
                
                if (filters.data_fim) {
                    const itemDate = new Date(item.data_liberacao);
                    const endDate = new Date(filters.data_fim);
                    if (itemDate > endDate) return false;
                }
                
                return true;
            });
            
            return filtered.sort((a, b) => new Date(b.data_liberacao) - new Date(a.data_liberacao));
        } catch (error) {
            console.error('❌ Erro ao recuperar histórico de cashback:', error);
            return [];
        }
    }
    
    /**
     * Adição de item à fila de sincronização
     */
    async addToSyncQueue(tableName, operation, data) {
        try {
            const queueItem = {
                table_name: tableName,
                operation: operation, // INSERT, UPDATE, DELETE
                data: data,
                timestamp: new Date().toISOString(),
                retry_count: 0
            };
            
            await this.setData('syncQueue', queueItem);
            this.syncQueue.push(queueItem);
            
            console.log(`📋 Item adicionado à fila de sincronização: ${tableName}/${operation}`);
        } catch (error) {
            console.error('❌ Erro ao adicionar à fila de sincronização:', error);
        }
    }
    
    /**
     * Sincronização automática dos dados
     */
    async triggerSync() {
        if (this.syncInProgress || !this.isOnline) {
            return;
        }
        
        this.syncInProgress = true;
        console.log('🔄 Iniciando sincronização offline...');
        
        try {
            // Carregar fila de sincronização
            await this.loadSyncQueue();
            
            if (this.syncQueue.length === 0) {
                console.log('✅ Nenhum item para sincronizar');
                this.syncInProgress = false;
                return;
            }
            
            // Processar itens em lotes
            await this.processSyncQueue();
            
            // Sincronizar dados do servidor
            await this.syncFromServer();
            
            console.log('✅ Sincronização completada');
            this.showToast('Dados sincronizados com sucesso!', 'success');
            
        } catch (error) {
            console.error('❌ Erro na sincronização:', error);
            this.showToast('Erro na sincronização. Tentaremos novamente.', 'error');
        } finally {
            this.syncInProgress = false;
        }
    }
    
    /**
     * Carregamento da fila de sincronização
     */
    async loadSyncQueue() {
        try {
            this.syncQueue = await this.getAllData('syncQueue');
            console.log(`📋 ${this.syncQueue.length} itens na fila de sincronização`);
        } catch (error) {
            console.error('❌ Erro ao carregar fila de sincronização:', error);
            this.syncQueue = [];
        }
    }
    
    /**
     * Processamento da fila de sincronização
     */
    async processSyncQueue() {
        const batchSize = this.syncConfig.batchSize;
        
        for (let i = 0; i < this.syncQueue.length; i += batchSize) {
            const batch = this.syncQueue.slice(i, i + batchSize);
            
            await Promise.all(batch.map(item => this.syncQueueItem(item)));
            
            // Pequena pausa entre lotes
            await this.delay(100);
        }
    }
    
    /**
     * Sincronização de um item individual da fila
     */
    async syncQueueItem(queueItem) {
        try {
            const endpoint = this.getSyncEndpoint(queueItem.table_name, queueItem.operation);
            
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    operation: queueItem.operation,
                    data: queueItem.data,
                    offline_sync: true
                })
            });
            
            if (response.ok) {
                // Remover da fila local
                await this.removeFromSyncQueue(queueItem.id);
                console.log(`✅ Item sincronizado: ${queueItem.table_name}/${queueItem.operation}`);
            } else {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
        } catch (error) {
            console.error(`❌ Erro ao sincronizar item:`, error);
            
            // Incrementar contador de tentativas
            queueItem.retry_count++;
            
            if (queueItem.retry_count >= this.syncConfig.retryAttempts) {
                console.warn(`⚠️ Item removido da fila após ${this.syncConfig.retryAttempts} tentativas`);
                await this.removeFromSyncQueue(queueItem.id);
            } else {
                // Atualizar item na fila
                await this.setData('syncQueue', queueItem);
            }
        }
    }
    
    /**
     * Sincronização de dados do servidor
     */
    async syncFromServer() {
        try {
            // Buscar dados atualizados do servidor
            const endpoints = [
                { name: 'userProfile', url: '/api/client/profile' },
                { name: 'transactions', url: '/api/client/transactions' },
                { name: 'stores', url: '/api/client/stores' },
                { name: 'cashbackHistory', url: '/api/client/cashback' }
            ];
            
            for (const endpoint of endpoints) {
                try {
                    const response = await fetch(endpoint.url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    
                    if (response.ok) {
                        const data = await response.json();
                        
                        switch (endpoint.name) {
                            case 'userProfile':
                                if (data.profile) {
                                    await this.saveUserProfile(data.profile);
                                }
                                break;
                            case 'transactions':
                                if (data.transactions) {
                                    await this.updateTransactions(data.transactions);
                                }
                                break;
                            case 'stores':
                                if (data.stores) {
                                    await this.saveStores(data.stores);
                                }
                                break;
                            case 'cashbackHistory':
                                if (data.cashback) {
                                    await this.saveCashbackHistory(data.cashback);
                                }
                                break;
                        }
                    }
                } catch (error) {
                    console.warn(`⚠️ Erro ao sincronizar ${endpoint.name}:`, error);
                }
            }
            
        } catch (error) {
            console.error('❌ Erro na sincronização do servidor:', error);
        }
    }
    
    /**
     * Atualização de transações locais com dados do servidor
     */
    async updateTransactions(serverTransactions) {
        try {
            const transaction = this.db.transaction(['transactions'], 'readwrite');
            const store = transaction.objectStore('transactions');
            
            for (const serverTrans of serverTransactions) {
                const localTrans = await store.get(serverTrans.id);
                
                if (localTrans) {
                    // Atualizar transação existente
                    const updated = { ...localTrans, ...serverTrans, sincronizado: true };
                    await store.put(updated);
                } else {
                    // Adicionar nova transação
                    serverTrans.sincronizado = true;
                    await store.add(serverTrans);
                }
            }
            
            console.log(`✅ ${serverTransactions.length} transações atualizadas`);
        } catch (error) {
            console.error('❌ Erro ao atualizar transações:', error);
        }
    }
    
    /**
     * Atualização do saldo local baseado em transação
     */
    async updateLocalBalance(transactionData) {
        try {
            const userProfile = await this.getUserProfile(transactionData.usuario_id);
            
            if (userProfile) {
                // Adicionar ao saldo pendente
                userProfile.saldo_pendente = (parseFloat(userProfile.saldo_pendente) || 0) + parseFloat(transactionData.valor_cashback);
                userProfile.ultimo_sync = new Date().toISOString();
                
                await this.saveUserProfile(userProfile);
                
                console.log('💰 Saldo local atualizado');
            }
        } catch (error) {
            console.error('❌ Erro ao atualizar saldo local:', error);
        }
    }
    
    /**
     * Salvamento de notificações
     */
    async saveNotifications(notifications) {
        try {
            const transaction = this.db.transaction(['notifications'], 'readwrite');
            const store = transaction.objectStore('notifications');
            
            for (const notification of notifications) {
                notification.sincronizado = true;
                await store.put(notification);
            }
            
            console.log(`🔔 ${notifications.length} notificações salvas offline`);
            return true;
        } catch (error) {
            console.error('❌ Erro ao salvar notificações:', error);
            return false;
        }
    }
    
    /**
     * Recuperação de notificações
     */
    async getNotifications(onlyUnread = false) {
        try {
            const allNotifications = await this.getAllData('notifications');
            
            if (onlyUnread) {
                return allNotifications.filter(notif => !notif.lida)
                    .sort((a, b) => new Date(b.data_criacao) - new Date(a.data_criacao));
            }
            
            return allNotifications.sort((a, b) => new Date(b.data_criacao) - new Date(a.data_criacao));
        } catch (error) {
            console.error('❌ Erro ao recuperar notificações:', error);
            return [];
        }
    }
    
    /**
     * Marcação de notificação como lida
     */
    async markNotificationAsRead(notificationId) {
        try {
            const notification = await this.getData('notifications', notificationId);
            
            if (notification) {
                notification.lida = true;
                notification.sincronizado = false;
                await this.setData('notifications', notification);
                
                // Adicionar à fila de sincronização
                await this.addToSyncQueue('notifications', 'UPDATE', notification);
                
                console.log('✅ Notificação marcada como lida');
                return true;
            }
            
            return false;
        } catch (error) {
            console.error('❌ Erro ao marcar notificação como lida:', error);
            return false;
        }
    }
    
    /**
     * Estatísticas de uso offline
     */
    async getOfflineStats() {
        try {
            const stats = {
                userProfile: await this.getDataCount('userProfile'),
                transactions: await this.getDataCount('transactions'),
                stores: await this.getDataCount('stores'),
                cashbackHistory: await this.getDataCount('cashbackHistory'),
                notifications: await this.getDataCount('notifications'),
                syncQueue: await this.getDataCount('syncQueue'),
                lastSync: await this.getConfig('last_sync_timestamp'),
                dbSize: await this.estimateDbSize()
            };
            
            return stats;
        } catch (error) {
            console.error('❌ Erro ao obter estatísticas offline:', error);
            return null;
        }
    }
    
    /**
     * Limpeza de dados antigos
     */
    async cleanupOldData() {
        try {
            const cutoffDate = new Date();
            cutoffDate.setMonth(cutoffDate.getMonth() - 6); // Dados de 6 meses atrás
            
            // Limpar transações antigas sincronizadas
            const oldTransactions = await this.getTransactions({});
            const toDelete = oldTransactions.filter(trans => 
                trans.sincronizado && 
                new Date(trans.data_transacao) < cutoffDate
            );
            
            for (const trans of toDelete) {
                await this.deleteData('transactions', trans.id);
            }
            
            // Limpar notificações antigas lidas
            const oldNotifications = await this.getNotifications();
            const oldReadNotifications = oldNotifications.filter(notif =>
                notif.lida &&
                new Date(notif.data_criacao) < cutoffDate
            );
            
            for (const notif of oldReadNotifications) {
                await this.deleteData('notifications', notif.id);
            }
            
            console.log(`🧹 Limpeza concluída: ${toDelete.length} transações e ${oldReadNotifications.length} notificações removidas`);
            
        } catch (error) {
            console.error('❌ Erro na limpeza de dados:', error);
        }
    }
    
    // === MÉTODOS AUXILIARES ===
    
    /**
     * Método genérico para salvar dados
     */
    async setData(storeName, data) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.put(data);
            
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }
    
    /**
     * Método genérico para recuperar dados
     */
    async getData(storeName, key) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.get(key);
            
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }
    
    /**
     * Método genérico para recuperar todos os dados
     */
    async getAllData(storeName) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.getAll();
            
            request.onsuccess = () => resolve(request.result || []);
            request.onerror = () => reject(request.error);
        });
    }
    
    /**
     * Método genérico para deletar dados
     */
    async deleteData(storeName, key) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.delete(key);
            
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }
    
    /**
     * Contagem de registros em uma store
     */
    async getDataCount(storeName) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.count();
            
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }
    
    /**
     * Estimativa do tamanho do banco
     */
    async estimateDbSize() {
        try {
            if ('storage' in navigator && 'estimate' in navigator.storage) {
                const estimate = await navigator.storage.estimate();
                return {
                    usage: estimate.usage,
                    quota: estimate.quota,
                    usageDetails: estimate.usageDetails
                };
            }
            return { usage: 0, quota: 0 };
        } catch (error) {
            console.warn('⚠️ Não foi possível estimar o tamanho do banco:', error);
            return { usage: 0, quota: 0 };
        }
    }
    
    /**
     * Configurações da aplicação
     */
    async setConfig(key, value) {
        await this.setData('appConfig', { key, value, ultimo_sync: new Date().toISOString() });
    }
    
    async getConfig(key) {
        const config = await this.getData('appConfig', key);
        return config ? config.value : null;
    }
    
    /**
     * Remoção de item da fila de sincronização
     */
    async removeFromSyncQueue(queueId) {
        await this.deleteData('syncQueue', queueId);
        this.syncQueue = this.syncQueue.filter(item => item.id !== queueId);
    }
    
    /**
     * Determinação do endpoint de sincronização
     */
    getSyncEndpoint(tableName, operation) {
        const baseUrl = '/api/sync';
        
        const endpoints = {
            userProfile: `${baseUrl}/profile`,
            transactions: `${baseUrl}/transactions`,
            stores: `${baseUrl}/stores`,
            cashbackHistory: `${baseUrl}/cashback`,
            notifications: `${baseUrl}/notifications`
        };
        
        return endpoints[tableName] || `${baseUrl}/generic`;
    }
    
    /**
     * Início da sincronização automática
     */
    startAutoSync() {
        setInterval(() => {
            if (this.isOnline && !this.syncInProgress) {
                this.triggerSync();
            }
        }, this.syncConfig.autoSyncInterval);
        
        console.log(`⏰ Sincronização automática configurada (${this.syncConfig.autoSyncInterval}ms)`);
    }
    
    /**
     * Utilitários
     */
    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
    
    showToast(message, type = 'info') {
        if (window.KlubeCash && window.KlubeCash.showToast) {
            window.KlubeCash.showToast(message, type);
        } else {
            console.log(`[${type.toUpperCase()}] ${message}`);
        }
    }
    
    logEvent(eventName, data = {}) {
        if (window.KlubeCash && window.KlubeCash.trackEvent) {
            window.KlubeCash.trackEvent(eventName, data);
        }
    }
    
    logError(errorName, error, data = {}) {
        console.error(`[${errorName}]`, error);
        this.logEvent('error', { 
            name: errorName, 
            message: error.message, 
            stack: error.stack,
            ...data 
        });
    }
}

// === INICIALIZAÇÃO GLOBAL ===
let klubeCashOfflineStorage = null;

// Aguardar DOM estar pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initOfflineStorage);
} else {
    initOfflineStorage();
}

function initOfflineStorage() {
    try {
        klubeCashOfflineStorage = new KlubeCashOfflineStorage();
        
        // Disponibilizar globalmente
        window.KlubeCashOffline = klubeCashOfflineStorage;
        
        console.log('🚀 Sistema de storage offline do Klube Cash inicializado');
    } catch (error) {
        console.error('❌ Erro crítico ao inicializar storage offline:', error);
    }
}

// === EXPORT PARA MÓDULOS ===
if (typeof module !== 'undefined' && module.exports) {
    module.exports = KlubeCashOfflineStorage;
}