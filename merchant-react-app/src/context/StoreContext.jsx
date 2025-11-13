import React, { createContext, useContext, useState, useEffect } from 'react';
import { storeService } from '../services/storeService';
import { useAuth } from './AuthContext';

const StoreContext = createContext();

export const useStore = () => {
  const context = useContext(StoreContext);
  if (!context) {
    throw new Error('useStore deve ser usado dentro de um StoreProvider');
  }
  return context;
};

export const StoreProvider = ({ children }) => {
  const { user } = useAuth();
  const [storeId, setStoreId] = useState(null);
  const [storeData, setStoreData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (user) {
      fetchStoreData();
    }
  }, [user]);

  const fetchStoreData = async () => {
    try {
      setLoading(true);

      // Obter ID da loja
      const id = await storeService.getStoreId();
      setStoreId(id);

      // Obter dados da loja
      const data = await storeService.getStoreData(id);
      setStoreData(data);

      setError(null);
    } catch (err) {
      console.error('Erro ao buscar dados da loja:', err);
      setError(err.message || 'Erro ao buscar dados da loja');
    } finally {
      setLoading(false);
    }
  };

  const refreshStoreData = async () => {
    if (storeId) {
      try {
        const data = await storeService.getStoreData(storeId);
        setStoreData(data);
      } catch (err) {
        console.error('Erro ao atualizar dados da loja:', err);
      }
    }
  };

  const value = {
    storeId,
    storeData,
    loading,
    error,
    refreshStoreData,
  };

  return <StoreContext.Provider value={value}>{children}</StoreContext.Provider>;
};
