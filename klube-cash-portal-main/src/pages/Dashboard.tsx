import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { DollarSign, ShoppingCart, Clock, TrendingUp, Eye, EyeOff } from "lucide-react";
import { PageHeader } from "@/components/PageHeader";
import { KPICard } from "@/components/KPICard";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";

// Mock data
const kpiData = {
  totalVendas: 248,
  valorTotal: 125480.50,
  pendentes: 12,
  comissoes: 8450.20,
};

const recentTransactions = [
  { id: 1, date: "2025-01-05", client: "João Silva", code: "TRX001", value: 1250.00, status: "aprovado" },
  { id: 2, date: "2025-01-05", client: "Maria Santos", code: "TRX002", value: 890.50, status: "pendente" },
  { id: 3, date: "2025-01-04", client: "Pedro Costa", code: "TRX003", value: 2100.00, status: "aprovado" },
  { id: 4, date: "2025-01-04", client: "Ana Oliveira", code: "TRX004", value: 450.00, status: "pago" },
  { id: 5, date: "2025-01-03", client: "Carlos Souza", code: "TRX005", value: 1680.00, status: "aprovado" },
];

const statusColors = {
  pendente: "bg-amber-100 text-amber-800",
  aprovado: "bg-blue-100 text-blue-800",
  pago: "bg-green-100 text-green-800",
  cancelado: "bg-red-100 text-red-800",
};

export default function Dashboard() {
  const navigate = useNavigate();
  const [showValues, setShowValues] = useState(true);

  const formatCurrency = (value: number) => {
    if (!showValues) return "••••••";
    return new Intl.NumberFormat("pt-BR", {
      style: "currency",
      currency: "BRL",
    }).format(value);
  };

  return (
    <div>
      <PageHeader
        title="Dashboard da Loja"
        subtitle="Bem-vindo(a), Loja Exemplo"
        action={{
          label: showValues ? "Ocultar valores" : "Mostrar valores",
          onClick: () => setShowValues(!showValues),
          icon: showValues ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />,
        }}
      />

      <div className="p-4 lg:p-6 space-y-6">
        {/* KPI Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <KPICard
            title="Total de Vendas"
            value={kpiData.totalVendas}
            icon={ShoppingCart}
            variant="info"
          />
          <KPICard
            title="Valor Total"
            value={formatCurrency(kpiData.valorTotal)}
            icon={DollarSign}
            variant="success"
          />
          <KPICard
            title="Transações Pendentes"
            value={kpiData.pendentes}
            icon={Clock}
            variant="warning"
          />
          <KPICard
            title="Total de Comissões"
            value={formatCurrency(kpiData.comissoes)}
            icon={TrendingUp}
            variant="default"
          />
        </div>

        {/* Sales Chart Placeholder */}
        <Card>
          <CardHeader>
            <CardTitle>Vendas dos Últimos 6 Meses</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="h-64 flex items-center justify-center bg-muted/20 rounded-lg">
              <p className="text-muted-foreground">Gráfico de vendas (placeholder)</p>
            </div>
          </CardContent>
        </Card>

        {/* Recent Transactions */}
        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle>Últimas Transações</CardTitle>
            <Button variant="outline" size="sm" onClick={() => navigate("/transacoes")}>
              Ver todas
            </Button>
          </CardHeader>
          <CardContent>
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Data</TableHead>
                    <TableHead>Cliente</TableHead>
                    <TableHead>Código</TableHead>
                    <TableHead>Valor</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Ações</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {recentTransactions.map((transaction) => (
                    <TableRow key={transaction.id}>
                      <TableCell>{new Date(transaction.date).toLocaleDateString("pt-BR")}</TableCell>
                      <TableCell className="font-medium">{transaction.client}</TableCell>
                      <TableCell className="font-mono text-sm">{transaction.code}</TableCell>
                      <TableCell>{formatCurrency(transaction.value)}</TableCell>
                      <TableCell>
                        <Badge className={statusColors[transaction.status as keyof typeof statusColors]}>
                          {transaction.status}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <Button variant="ghost" size="sm">
                          Ver detalhes
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          </CardContent>
        </Card>

        {/* Quick Actions */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <Button
            onClick={() => navigate("/nova-venda")}
            className="h-20 text-lg"
            size="lg"
          >
            <ShoppingCart className="mr-2 h-5 w-5" />
            Nova Venda
          </Button>
          <Button
            onClick={() => navigate("/transacoes")}
            variant="outline"
            className="h-20 text-lg"
            size="lg"
          >
            Ver Transações
          </Button>
          <Button
            onClick={() => navigate("/pagamentos-pix")}
            variant="outline"
            className="h-20 text-lg"
            size="lg"
          >
            Pagamentos (Pix)
          </Button>
        </div>
      </div>
    </div>
  );
}
