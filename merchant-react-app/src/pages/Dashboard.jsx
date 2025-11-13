import React from 'react';
import { useStore } from '../context/StoreContext';

const Dashboard = () => {
  const { storeData } = useStore();

  return (
    <div>
      <h2 className="text-3xl font-bold text-gray-900 mb-6">Dashboard</h2>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div className="bg-white rounded-lg shadow-md p-6">
          <h3 className="text-sm font-medium text-gray-500 mb-2">Loja</h3>
          <p className="text-2xl font-bold text-gray-900">
            {storeData?.nome_fantasia || 'Carregando...'}
          </p>
        </div>

        <div className="bg-white rounded-lg shadow-md p-6">
          <h3 className="text-sm font-medium text-gray-500 mb-2">Status</h3>
          <p className="text-2xl font-bold text-green-600">
            {storeData?.status || '-'}
          </p>
        </div>

        <div className="bg-white rounded-lg shadow-md p-6">
          <h3 className="text-sm font-medium text-gray-500 mb-2">Cashback</h3>
          <p className="text-2xl font-bold text-primary-500">
            {storeData?.porcentagem_cashback || '0'}%
          </p>
        </div>

        <div className="bg-white rounded-lg shadow-md p-6">
          <h3 className="text-sm font-medium text-gray-500 mb-2">Categoria</h3>
          <p className="text-2xl font-bold text-gray-900">
            {storeData?.categoria || '-'}
          </p>
        </div>
      </div>

      <div className="bg-white rounded-lg shadow-md p-6">
        <p className="text-gray-600">
          Dashboard completo será implementado nas próximas etapas.
        </p>
      </div>
    </div>
  );
};

export default Dashboard;
