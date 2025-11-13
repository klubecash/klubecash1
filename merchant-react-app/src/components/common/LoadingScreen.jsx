import React from 'react';

const LoadingScreen = () => {
  return (
    <div className="fixed inset-0 bg-white flex items-center justify-center z-50">
      <div className="text-center">
        <div className="spinner w-16 h-16 border-4 border-primary-500 mx-auto mb-4"></div>
        <p className="text-gray-600 font-medium">Carregando...</p>
      </div>
    </div>
  );
};

export default LoadingScreen;
