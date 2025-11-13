import React from 'react';
import { Outlet } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import { useStore } from '../../../context/StoreContext';
import LoadingScreen from '../../common/LoadingScreen';

const MainLayout = () => {
  const { user, loading: authLoading } = useAuth();
  const { loading: storeLoading } = useStore();

  if (authLoading || storeLoading) {
    return <LoadingScreen />;
  }

  if (!user) {
    return null;
  }

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Sidebar será implementado depois */}
      <div className="flex">
        <aside className="w-64 bg-white shadow-lg min-h-screen">
          <div className="p-6">
            <h2 className="text-xl font-bold text-primary-500">Klube Cash</h2>
            <p className="text-sm text-gray-600 mt-2">Sistema de Lojistas</p>
          </div>
          <nav className="mt-6">
            {/* Menu items serão implementados depois */}
            <div className="px-4 py-2 text-gray-700 hover:bg-primary-50 cursor-pointer">
              Dashboard
            </div>
          </nav>
        </aside>

        <div className="flex-1">
          <header className="bg-white shadow-sm">
            <div className="px-8 py-4">
              <h1 className="text-2xl font-semibold text-gray-800">
                Bem-vindo, {user.nome}
              </h1>
            </div>
          </header>

          <main className="p-8">
            <Outlet />
          </main>
        </div>
      </div>
    </div>
  );
};

export default MainLayout;
