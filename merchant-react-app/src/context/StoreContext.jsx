import React, { createContext, useContext, useState, useEffect } from 'react';
import { storeService } from '../services/storeService';
import { useAuth } from './AuthContext';

const StoreContext = createContext();

// MODO DESENVOLVIMENTO - Dados fake da loja
const DEV_MODE = process.env.REACT_APP_DEV_MODE === 'true';
const MOCK_STORE_DATA = {
  id: 1,
  nome_fantasia: 'Loja Teste',
  razao_social: 'Loja Teste LTDA',
  cnpj: '12.345.678/0001-90',
  email: 'contato@lojateste.com',
  telefone: '11999999999',
  categoria: 'Alimentação',
  porcentagem_cashback: 5.0,
  descricao: 'Loja de teste para desenvolvimento',
  website: 'https://lojateste.com',
  logo: null,
  status: 'aprovado',
  porcentagem_cliente: 70,
  porcentagem_admin: 30,
  cashback_ativo: true,
  data_cadastro: '2024-01-01',
};

export const useStore = () => {
  const context = useContext(StoreContext);
  if (!context) {
    throw new Error('useStore deve ser usado dentro de um StoreProvider');
  }
  return context;
};

export const StoreProvider = ({ children }) => {
  const { user, isDev } = useAuth();
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

      // MODO DESENVOLVIMENTO - Usa dados fake
      if (DEV_MODE || isDev) {
        console.log('🔧 MODO DESENVOLVIMENTO: Usando dados fake da loja');
        setStoreId(1);
        setStoreData(MOCK_STORE_DATA);
        setError(null);
        setLoading(false);
        return;
      }

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

      // Em modo dev, usa dados fake mesmo em caso de erro
      if (DEV_MODE || isDev) {
        setStoreId(1);
        setStoreData(MOCK_STORE_DATA);
      }
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
