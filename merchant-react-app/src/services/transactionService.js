import api from './api';

export const transactionService = {
  /**
   * Lista transações da loja
   */
  getTransactions: async (storeId, filters = {}, page = 1, limit = 20) => {
    try {
      const params = {
        loja_id: storeId,
        page,
        limit,
        ...filters,
      };

      const response = await api.get('/transactions.php', { params });
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Cria nova transação
   */
  createTransaction: async (data) => {
    try {
      const response = await api.post('/transactions.php', data);
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Obtém detalhes de uma transação
   */
  getTransactionById: async (id) => {
    try {
      const response = await api.get(`/transactions.php?id=${id}`);
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Atualiza uma transação
   */
  updateTransaction: async (id, data) => {
    try {
      const response = await api.put(`/transactions.php?id=${id}`, data);
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Busca cliente por telefone ou CPF
   */
  searchCustomer: async (query) => {
    try {
      const response = await api.get('/store-client-search.php', {
        params: { q: query },
      });
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Cancela uma transação
   */
  cancelTransaction: async (id, reason) => {
    try {
      const response = await api.post(`/transactions.php?id=${id}&action=cancel`, {
        reason,
      });
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Upload de transações em lote (CSV)
   */
  batchUpload: async (file, storeId) => {
    try {
      const formData = new FormData();
      formData.append('file', file);
      formData.append('store_id', storeId);

      const response = await api.post('/transactions.php?action=batch', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });

      return response.data;
    } catch (error) {
      throw error;
    }
  },
};
