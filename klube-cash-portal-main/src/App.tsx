import { Toaster } from "@/components/ui/toaster";
import { Toaster as Sonner } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Routes, Route } from "react-router-dom";
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

const queryClient = new QueryClient();

const App = () => (
  <QueryClientProvider client={queryClient}>
    <TooltipProvider>
      <Toaster />
      <Sonner />
      <BrowserRouter>
        <Routes>
          <Route path="/" element={<Layout><Dashboard /></Layout>} />
          <Route path="/nova-venda" element={<Layout><NovaVenda /></Layout>} />
          <Route path="/transacoes" element={<Layout><Transacoes /></Layout>} />
          <Route path="/pendentes" element={<Layout><Pendentes /></Layout>} />
          <Route path="/pagamentos-pix" element={<Layout><PagamentosPix /></Layout>} />
          <Route path="/historico-pagamentos" element={<Layout><HistoricoPagamentos /></Layout>} />
          <Route path="/funcionarios" element={<Layout><Funcionarios /></Layout>} />
          <Route path="/perfil" element={<Layout><Perfil /></Layout>} />
          <Route path="/detalhes-loja" element={<Layout><DetalhesLoja /></Layout>} />
          <Route path="/importacao" element={<Layout><Importacao /></Layout>} />
          <Route path="*" element={<NotFound />} />
        </Routes>
      </BrowserRouter>
    </TooltipProvider>
  </QueryClientProvider>
);

export default App;
