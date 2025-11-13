import api from './api';

export const subscriptionService = {
  /**
   * Obtém assinatura atual da loja
   */
  getCurrentSubscription: async (storeId) => {
    try {
      const response = await api.get('/subscriptions.php', {
        params: { loja_id: storeId },
      });
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Lista planos disponíveis
   */
  getPlans: async () => {
    try {
      const response = await api.get('/subscriptions.php', {
        params: { action: 'plans' },
      });
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Faz upgrade/downgrade de plano
   */
  changePlan: async (subscriptionId, planId, ciclo) => {
    try {
      const response = await api.post('/subscriptions.php?action=upgrade', {
        subscription_id: subscriptionId,
        plan_id: planId,
        ciclo,
      });
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Resgata código de plano promocional
   */
  redeemCode: async (code) => {
    try {
      const response = await api.post('/subscriptions.php?action=redeem', {
        code,
      });
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Obtém histórico de faturas
   */
  getInvoices: async (subscriptionId) => {
    try {
      const response = await api.get('/subscriptions.php', {
        params: {
          action: 'invoices',
          id: subscriptionId,
        },
      });
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Cancela assinatura
   */
  cancelSubscription: async (subscriptionId, reason) => {
    try {
      const response = await api.post('/subscriptions.php?action=cancel', {
        subscription_id: subscriptionId,
        reason,
      });
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Reativa assinatura cancelada
   */
  reactivateSubscription: async (subscriptionId) => {
    try {
      const response = await api.post('/subscriptions.php?action=reactivate', {
        subscription_id: subscriptionId,
      });
      return response.data;
    } catch (error) {
      throw error;
    }
  },
};
