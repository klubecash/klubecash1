import { useState } from "react";
import { Download, Filter } from "lucide-react";
import { PageHeader } from "@/components/PageHeader";
import { KPICard } from "@/components/KPICard";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent } from "@/components/ui/card";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@/components/ui/sheet";
import { Badge } from "@/components/ui/badge";
import { DollarSign, ShoppingCart, Clock, TrendingUp } from "lucide-react";

const mockTransactions = Array.from({ length: 25 }, (_, i) => ({
  id: i + 1,
  date: new Date(2025, 0, Math.floor(Math.random() * 7) + 1).toISOString(),
  client: `Cliente ${i + 1}`,
  code: `TRX${String(i + 1).padStart(3, "0")}`,
  value: Math.random() * 5000 + 100,
  cashback: Math.random() * 500 + 10,
  status: ["pendente", "aprovado", "pago", "cancelado"][Math.floor(Math.random() * 4)],
}));

const statusColors = {
  pendente: "bg-amber-100 text-amber-800",
  aprovado: "bg-blue-100 text-blue-800",
  pago: "bg-green-100 text-green-800",
  cancelado: "bg-red-100 text-red-800",
};

export default function Transacoes() {
  const [currentPage, setCurrentPage] = useState(1);
  const [filters, setFilters] = useState({
    status: "",
    dataInicio: "",
    dataFim: "",
    valorMin: "",
    valorMax: "",
  });

  const itemsPerPage = 10;
  const totalPages = Math.ceil(mockTransactions.length / itemsPerPage);
  const startIndex = (currentPage - 1) * itemsPerPage;
  const endIndex = startIndex + itemsPerPage;
  const currentTransactions = mockTransactions.slice(startIndex, endIndex);

  const handleExport = () => {
    // TODO: Implement CSV export
    console.log("Exportar CSV");
  };

  return (
    <div>
      <PageHeader
        title="Minhas Transações"
        subtitle="Loja Exemplo"
        action={{
          label: "Exportar CSV",
          onClick: handleExport,
          icon: <Download className="h-4 w-4" />,
        }}
      />

      <div className="p-4 lg:p-6 space-y-6">
        {/* KPI Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <KPICard
            title="Total de Transações"
            value={mockTransactions.length}
            icon={ShoppingCart}
            variant="info"
          />
          <KPICard
            title="Valor Total"
            value={`R$ ${mockTransactions.reduce((sum, t) => sum + t.value, 0).toFixed(2)}`}
            icon={DollarSign}
            variant="success"
          />
          <KPICard
            title="Pendentes"
            value={mockTransactions.filter((t) => t.status === "pendente").length}
            icon={Clock}
            variant="warning"
          />
          <KPICard
            title="Comissões"
            value={`R$ ${mockTransactions.reduce((sum, t) => sum + t.cashback, 0).toFixed(2)}`}
            icon={TrendingUp}
            variant="default"
          />
        </div>

        {/* Filters */}
        <Card>
          <CardContent className="p-4">
            <Sheet>
              <SheetTrigger asChild>
                <Button variant="outline" className="w-full sm:w-auto">
                  <Filter className="mr-2 h-4 w-4" />
                  Filtros
                </Button>
              </SheetTrigger>
              <SheetContent>
                <SheetHeader>
                  <SheetTitle>Filtros</SheetTitle>
                  <SheetDescription>
                    Refine sua busca de transações
                  </SheetDescription>
                </SheetHeader>
                <div className="space-y-4 mt-6">
                  <div className="space-y-2">
                    <Label htmlFor="status">Status</Label>
                    <Select
                      value={filters.status}
                      onValueChange={(value) => setFilters({ ...filters, status: value })}
                    >
                      <SelectTrigger id="status">
                        <SelectValue placeholder="Todos" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="">Todos</SelectItem>
                        <SelectItem value="pendente">Pendente</SelectItem>
                        <SelectItem value="aprovado">Aprovado</SelectItem>
                        <SelectItem value="pago">Pago</SelectItem>
                        <SelectItem value="cancelado">Cancelado</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="dataInicio">Data Início</Label>
                    <Input
                      id="dataInicio"
                      type="date"
                      value={filters.dataInicio}
                      onChange={(e) => setFilters({ ...filters, dataInicio: e.target.value })}
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="dataFim">Data Fim</Label>
                    <Input
                      id="dataFim"
                      type="date"
                      value={filters.dataFim}
                      onChange={(e) => setFilters({ ...filters, dataFim: e.target.value })}
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="valorMin">Valor Mínimo</Label>
                    <Input
                      id="valorMin"
                      type="number"
                      placeholder="0,00"
                      value={filters.valorMin}
                      onChange={(e) => setFilters({ ...filters, valorMin: e.target.value })}
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="valorMax">Valor Máximo</Label>
                    <Input
                      id="valorMax"
                      type="number"
                      placeholder="0,00"
                      value={filters.valorMax}
                      onChange={(e) => setFilters({ ...filters, valorMax: e.target.value })}
                    />
                  </div>

                  <div className="flex gap-2 pt-4">
                    <Button
                      variant="outline"
                      className="flex-1"
                      onClick={() => setFilters({
                        status: "",
                        dataInicio: "",
                        dataFim: "",
                        valorMin: "",
                        valorMax: "",
                      })}
                    >
                      Limpar
                    </Button>
                    <Button className="flex-1">Aplicar</Button>
                  </div>
                </div>
              </SheetContent>
            </Sheet>
          </CardContent>
        </Card>

        {/* Transactions Table */}
        <Card>
          <CardContent className="p-0">
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Data</TableHead>
                    <TableHead>Cliente</TableHead>
                    <TableHead>Código</TableHead>
                    <TableHead>Valor Total</TableHead>
                    <TableHead>Cashback</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Ações</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {currentTransactions.map((transaction) => (
                    <TableRow key={transaction.id}>
                      <TableCell>
                        {new Date(transaction.date).toLocaleDateString("pt-BR")}
                      </TableCell>
                      <TableCell className="font-medium">{transaction.client}</TableCell>
                      <TableCell className="font-mono text-sm">{transaction.code}</TableCell>
                      <TableCell>R$ {transaction.value.toFixed(2)}</TableCell>
                      <TableCell className="text-primary">
                        R$ {transaction.cashback.toFixed(2)}
                      </TableCell>
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

            {/* Pagination */}
            <div className="flex items-center justify-between p-4 border-t">
              <p className="text-sm text-muted-foreground">
                Mostrando {startIndex + 1} a {Math.min(endIndex, mockTransactions.length)} de{" "}
                {mockTransactions.length} transações
              </p>
              <div className="flex gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                  disabled={currentPage === 1}
                >
                  Anterior
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                  disabled={currentPage === totalPages}
                >
                  Próxima
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
