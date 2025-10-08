import { useState } from "react";
import { Filter } from "lucide-react";
import { PageHeader } from "@/components/PageHeader";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
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
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

const mockPayments = Array.from({ length: 15 }, (_, i) => ({
  id: i + 1,
  date: new Date(2025, 0, Math.floor(Math.random() * 7) + 1).toISOString(),
  reference: `PIX${String(i + 1).padStart(5, "0")}`,
  value: Math.random() * 5000 + 500,
  status: ["pago", "processando", "recusado"][Math.floor(Math.random() * 3)],
  method: "Pix",
  transactionCount: Math.floor(Math.random() * 10) + 1,
}));

const statusColors = {
  pago: "bg-green-100 text-green-800",
  processando: "bg-blue-100 text-blue-800",
  recusado: "bg-red-100 text-red-800",
};

export default function HistoricoPagamentos() {
  const [selectedPayment, setSelectedPayment] = useState<any>(null);
  const [filters, setFilters] = useState({
    status: "",
    dataInicio: "",
    dataFim: "",
  });

  return (
    <div>
      <PageHeader
        title="Histórico de Pagamentos"
        subtitle="Acompanhe todos os pagamentos realizados"
      />

      <div className="p-4 lg:p-6 space-y-6">
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
                    Refine sua busca de pagamentos
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
                        <SelectItem value="pago">Pago</SelectItem>
                        <SelectItem value="processando">Processando</SelectItem>
                        <SelectItem value="recusado">Recusado</SelectItem>
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

                  <div className="flex gap-2 pt-4">
                    <Button
                      variant="outline"
                      className="flex-1"
                      onClick={() => setFilters({
                        status: "",
                        dataInicio: "",
                        dataFim: "",
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

        {/* Payments Table */}
        <Card>
          <CardContent className="p-0">
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Data</TableHead>
                    <TableHead>Referência</TableHead>
                    <TableHead>Valor</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Método</TableHead>
                    <TableHead>Transações</TableHead>
                    <TableHead>Ações</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {mockPayments.map((payment) => (
                    <TableRow key={payment.id}>
                      <TableCell>
                        {new Date(payment.date).toLocaleDateString("pt-BR")}
                      </TableCell>
                      <TableCell className="font-mono text-sm">
                        {payment.reference}
                      </TableCell>
                      <TableCell className="font-semibold">
                        R$ {payment.value.toFixed(2)}
                      </TableCell>
                      <TableCell>
                        <Badge className={statusColors[payment.status as keyof typeof statusColors]}>
                          {payment.status}
                        </Badge>
                      </TableCell>
                      <TableCell>{payment.method}</TableCell>
                      <TableCell>{payment.transactionCount}</TableCell>
                      <TableCell>
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => setSelectedPayment(payment)}
                        >
                          Visualizar
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Payment Details Dialog */}
      <Dialog open={!!selectedPayment} onOpenChange={() => setSelectedPayment(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Detalhes do Pagamento</DialogTitle>
            <DialogDescription>
              Informações completas sobre o pagamento
            </DialogDescription>
          </DialogHeader>
          {selectedPayment && (
            <div className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <p className="text-sm text-muted-foreground">Referência</p>
                  <p className="font-mono font-medium">{selectedPayment.reference}</p>
                </div>
                <div>
                  <p className="text-sm text-muted-foreground">Data</p>
                  <p className="font-medium">
                    {new Date(selectedPayment.date).toLocaleDateString("pt-BR")}
                  </p>
                </div>
                <div>
                  <p className="text-sm text-muted-foreground">Valor</p>
                  <p className="font-semibold text-lg">
                    R$ {selectedPayment.value.toFixed(2)}
                  </p>
                </div>
                <div>
                  <p className="text-sm text-muted-foreground">Status</p>
                  <Badge className={statusColors[selectedPayment.status as keyof typeof statusColors]}>
                    {selectedPayment.status}
                  </Badge>
                </div>
                <div>
                  <p className="text-sm text-muted-foreground">Método</p>
                  <p className="font-medium">{selectedPayment.method}</p>
                </div>
                <div>
                  <p className="text-sm text-muted-foreground">Transações</p>
                  <p className="font-medium">{selectedPayment.transactionCount}</p>
                </div>
              </div>
              <div className="pt-4 border-t">
                <p className="text-sm text-muted-foreground mb-2">Comprovante</p>
                <div className="p-4 bg-muted rounded-lg">
                  <p className="text-sm">
                    Comprovante disponível após confirmação do pagamento
                  </p>
                </div>
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
}
