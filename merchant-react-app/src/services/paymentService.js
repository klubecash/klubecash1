import api from './api';

export const paymentService = {
  /**
   * Lista pagamentos da loja
   */
  getPayments: async (storeId, filters = {}) => {
    try {
      const params = {
        loja_id: storeId,
        ...filters,
      };

      const response = await api.get('/payments.php', { params });
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Obtém detalhes de um pagamento
   */
  getPaymentById: async (id) => {
    try {
      const response = await api.get(`/payments.php?id=${id}`);
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Solicita novo pagamento
   */
  requestPayment: async (data) => {
    try {
      const response = await api.post('/payments.php', data);
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Gera cobrança PIX (AbacatePay)
   */
  generatePIX: async (paymentId, amount, gateway = 'abacate') => {
    try {
      const endpoint = gateway === 'abacate' ? '/abacatepay.php' : '/openpix.php';

      const response = await api.post(endpoint, {
        payment_id: paymentId,
        amount,
      });

      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Verifica status do pagamento PIX
   */
  checkPaymentStatus: async (paymentId) => {
    try {
      const response = await api.get(`/payments.php?id=${paymentId}`);
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Obtém saldo disponível para saque
   */
  getBalance: async (storeId) => {
    try {
      const response = await api.get('/balance.php', {
        params: { loja_id: storeId },
      });
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Obtém comissões pendentes
   */
  getPendingCommissions: async (storeId) => {
    try {
      const response = await api.get('/commissions.php', {
        params: {
          loja_id: storeId,
          status: 'pendente',
        },
      });
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Gera MercadoPago QR Code
   */
  generateMercadoPagoPIX: async (paymentId, amount) => {
    try {
      const response = await api.post('/mercadopago.php', {
        payment_id: paymentId,
        amount,
      });
      return response.data;
    } catch (error) {
      throw error;
    }
  },
};
