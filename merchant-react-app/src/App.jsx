import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import { StoreProvider } from './context/StoreContext';
import { NotificationProvider } from './context/NotificationContext';

// Placeholder para os componentes (serão criados depois)
import Dashboard from './pages/Dashboard';
import TransactionsPage from './pages/TransactionsPage';
import RegisterTransactionPage from './pages/RegisterTransactionPage';
import PaymentsPage from './pages/PaymentsPage';
import RequestPaymentPage from './pages/RequestPaymentPage';
import SubscriptionPage from './pages/SubscriptionPage';
import ProfilePage from './pages/ProfilePage';
import EmployeesPage from './pages/EmployeesPage';

// Import do layout
import MainLayout from './components/layout/MainLayout/MainLayout';

// Componente de loading
import LoadingScreen from './components/common/LoadingScreen';

function App() {
  return (
    <Router>
      <AuthProvider>
        <StoreProvider>
          <NotificationProvider>
            <Routes>
              {/* Rota raiz redireciona para dashboard */}
              <Route path="/" element={<Navigate to="/stores/dashboard" replace />} />

              {/* Rotas do sistema de lojista */}
              <Route path="/stores" element={<MainLayout />}>
                <Route path="dashboard" element={<Dashboard />} />
                <Route path="transactions" element={<TransactionsPage />} />
                <Route path="register-transaction" element={<RegisterTransactionPage />} />
                <Route path="payments" element={<PaymentsPage />} />
                <Route path="request-payment" element={<RequestPaymentPage />} />
                <Route path="subscription" element={<SubscriptionPage />} />
                <Route path="profile" element={<ProfilePage />} />
                <Route path="employees" element={<EmployeesPage />} />
              </Route>

              {/* Rota 404 */}
              <Route path="*" element={<Navigate to="/stores/dashboard" replace />} />
            </Routes>
          </NotificationProvider>
        </StoreProvider>
      </AuthProvider>
    </Router>
  );
}

export default App;
