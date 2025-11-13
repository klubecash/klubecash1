import React, { createContext, useContext, useState, useEffect } from 'react';
import { authService } from '../services/authService';

const AuthContext = createContext();

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
      authService.logout();
    } finally {
      setLoading(false);
    }
  };

  const logout = () => {
    setUser(null);
    authService.logout();
  };

  const value = {
    user,
    loading,
    error,
    logout,
    refreshUser: checkAuth,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};
