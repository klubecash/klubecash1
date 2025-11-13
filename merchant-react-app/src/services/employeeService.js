import api from './api';

export const employeeService = {
  /**
   * Lista funcionários da loja
   */
  getEmployees: async (storeId) => {
    try {
      const response = await api.get('/employees.php', {
        params: { loja_id: storeId },
      });
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Obtém dados de um funcionário
   */
  getEmployeeById: async (id) => {
    try {
      const response = await api.get(`/employees.php?id=${id}`);
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Cria novo funcionário
   */
  createEmployee: async (data) => {
    try {
      const response = await api.post('/employees.php', data);
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Atualiza funcionário
   */
  updateEmployee: async (id, data) => {
    try {
      const response = await api.put(`/employees.php?id=${id}`, data);
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Exclui/desativa funcionário
   */
  deleteEmployee: async (id) => {
    try {
      const response = await api.delete(`/employees.php?id=${id}`);
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Reativa funcionário
   */
  reactivateEmployee: async (id) => {
    try {
      const response = await api.post(`/employees.php?id=${id}&action=reactivate`);
      return response.data;
    } catch (error) {
      throw error;
    }
  },
};
