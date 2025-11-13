import api from './api';
import { SITE_URL } from '../utils/constants';

export const authService = {
  /**
   * Verifica se o usuário está autenticado
   */
  checkAuth: async () => {
    try {
      const response = await api.get('/validate-token.php');
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Obtém dados do usuário pelo token
   */
  getUserByToken: async () => {
    try {
      const response = await api.get('/get-user-by-token.php');
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Faz logout
   */
  logout: () => {
    localStorage.removeItem('jwt_token');
    window.location.href = `${SITE_URL}/views/auth/login.php`;
  },

  /**
   * Verifica se o token ainda é válido
   */
  isTokenValid: () => {
    const token = localStorage.getItem('jwt_token');
    return !!token;
  },
};
