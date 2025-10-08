import { Toaster } from "@/components/ui/toaster";
import { Toaster as Sonner } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";
import { Layout } from "@/components/Layout";
import Dashboard from "./pages/Dashboard";
import NovaVenda from "./pages/NovaVenda";
import Transacoes from "./pages/Transacoes";
import Pendentes from "./pages/Pendentes";
import PagamentosPix from "./pages/PagamentosPix";
import HistoricoPagamentos from "./pages/HistoricoPagamentos";
import Funcionarios from "./pages/Funcionarios";
import Perfil from "./pages/Perfil";
import DetalhesLoja from "./pages/DetalhesLoja";
import Importacao from "./pages/Importacao";
import NotFound from "./pages/NotFound";
import Login from "./pages/Login";
import { authService } from "@/services/authService";

const queryClient = new QueryClient();

// Protected Route Component
const ProtectedRoute = ({ children }: { children: React.ReactNode }) => {
  const isAuthenticated = authService.isAuthenticated();

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  return <>{children}</>;
};

const App = () => (
  <QueryClientProvider client={queryClient}>
    <TooltipProvider>
      <Toaster />
      <Sonner />
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route path="/" element={<ProtectedRoute><Layout><Dashboard /></Layout></ProtectedRoute>} />
          <Route path="/nova-venda" element={<ProtectedRoute><Layout><NovaVenda /></Layout></ProtectedRoute>} />
          <Route path="/transacoes" element={<ProtectedRoute><Layout><Transacoes /></Layout></ProtectedRoute>} />
          <Route path="/pendentes" element={<ProtectedRoute><Layout><Pendentes /></Layout></ProtectedRoute>} />
          <Route path="/pagamentos-pix" element={<ProtectedRoute><Layout><PagamentosPix /></Layout></ProtectedRoute>} />
          <Route path="/historico-pagamentos" element={<ProtectedRoute><Layout><HistoricoPagamentos /></Layout></ProtectedRoute>} />
          <Route path="/funcionarios" element={<ProtectedRoute><Layout><Funcionarios /></Layout></ProtectedRoute>} />
          <Route path="/perfil" element={<ProtectedRoute><Layout><Perfil /></Layout></ProtectedRoute>} />
          <Route path="/detalhes-loja" element={<ProtectedRoute><Layout><DetalhesLoja /></Layout></ProtectedRoute>} />
          <Route path="/importacao" element={<ProtectedRoute><Layout><Importacao /></Layout></ProtectedRoute>} />
          <Route path="*" element={<NotFound />} />
        </Routes>
      </BrowserRouter>
    </TooltipProvider>
  </QueryClientProvider>
);

export default App;
