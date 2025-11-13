import axios from 'axios';
import { API_BASE_URL, SITE_URL } from '../utils/constants';

// Criar instância do axios
const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
  },
  withCredentials: true, // Para enviar cookies (sessão PHP)
});

// Interceptor para adicionar token JWT às requisições
api.interceptors.request.use(
  (config) => {
    // Tentar obter token do localStorage
    const token = localStorage.getItem('jwt_token');

    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Interceptor para tratar respostas e erros
api.interceptors.response.use(
  (response) => {
    return response;
  },
  (error) => {
    // Token expirado ou inválido (401)
    if (error.response?.status === 401) {
      localStorage.removeItem('jwt_token');
      // Redirecionar para login PHP
      window.location.href = `${SITE_URL}/views/auth/login.php`;
      return Promise.reject(error);
    }

    // Acesso negado (403)
    if (error.response?.status === 403) {
      console.error('Acesso negado:', error.response.data);
    }

    // Erro no servidor (5xx)
    if (error.response?.status >= 500) {
      console.error('Erro no servidor:', error.response.data);
    }

    return Promise.reject(error);
  }
);

export default api;
