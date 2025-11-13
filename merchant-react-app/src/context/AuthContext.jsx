import React, { createContext, useContext, useState, useEffect } from 'react';
import { authService } from '../services/authService';

const AuthContext = createContext();

// MODO DESENVOLVIMENTO - Usuário fake
const DEV_MODE = process.env.REACT_APP_DEV_MODE === 'true';
const MOCK_USER = {
  id: 1,
  nome: 'Lojista Teste',
  email: 'lojista@teste.com',
  tipo: 'loja',
  telefone: '11999999999',
  store_id: 1,
  store_name: 'Loja Teste'
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth deve ser usado dentro de um AuthProvider');
  }
  return context;
};

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    checkAuth();
  }, []);

  const checkAuth = async () => {
    try {
      setLoading(true);

      // MODO DESENVOLVIMENTO - Usa usuário fake
      if (DEV_MODE) {
        console.log('🔧 MODO DESENVOLVIMENTO: Usando usuário fake');
        setUser(MOCK_USER);
        setError(null);
        setLoading(false);
        return;
      }

      // Verificar se tem token
      const token = localStorage.getItem('jwt_token');
      if (!token) {
        authService.logout();
        return;
      }

      // Obter dados do usuário
      const userData = await authService.getUserByToken();

      // Verificar se é lojista ou funcionário
      if (userData.tipo !== 'loja' && userData.tipo !== 'funcionario') {
        setError('Acesso não autorizado');
        authService.logout();
        return;
      }

      setUser(userData);
      setError(null);
    } catch (err) {
      console.error('Erro ao verificar autenticação:', err);
      setError(err.message || 'Erro ao verificar autenticação');

      // Em modo dev, não faz logout em caso de erro
      if (!DEV_MODE) {
        authService.logout();
      }
    } finally {
      setLoading(false);
    }
  };

  const logout = () => {
    setUser(null);
    if (!DEV_MODE) {
      authService.logout();
    }
  };

  const value = {
    user,
    loading,
    error,
    logout,
    refreshUser: checkAuth,
    isDev: DEV_MODE,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};
