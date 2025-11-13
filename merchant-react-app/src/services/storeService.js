import api from './api';

export const storeService = {
  /**
   * Obtém o ID da loja do usuário logado
   */
  getStoreId: async () => {
    try {
      const response = await api.get('/get-store-id.php');
      return response.data.store_id;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Obtém dados da loja
   */
  getStoreData: async (storeId) => {
    try {
      const response = await api.get(`/stores.php?id=${storeId}`);
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Obtém dados do dashboard
   */
  getDashboardData: async (storeId) => {
    try {
      const response = await api.get(`/stores.php`, {
        params: {
          action: 'dashboard',
          id: storeId,
        },
      });
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Atualiza dados da loja
   */
  updateStore: async (storeId, data) => {
    try {
      const response = await api.put(`/stores.php?id=${storeId}`, data);
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Faz upload do logo da loja
   */
  uploadLogo: async (storeId, file) => {
    try {
      const formData = new FormData();
      formData.append('logo', file);
      formData.append('store_id', storeId);

      const response = await api.post('/upload.php', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });

      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Obtém detalhes completos da loja
   */
  getStoreDetails: async (storeId) => {
    try {
      const response = await api.get(`/store_details.php?id=${storeId}`);
      return response.data;
    } catch (error) {
      throw error;
    }
  },
};
