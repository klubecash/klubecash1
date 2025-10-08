import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { AlertCircle, DollarSign, Clock } from "lucide-react";
import { PageHeader } from "@/components/PageHeader";
import { KPICard } from "@/components/KPICard";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Card, CardContent } from "@/components/ui/card";
import { Alert, AlertDescription } from "@/components/ui/alert";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";

const mockPendentes = Array.from({ length: 12 }, (_, i) => ({
  id: i + 1,
  date: new Date(2025, 0, Math.floor(Math.random() * 7) + 1).toISOString(),
  client: `Cliente ${i + 1}`,
  value: Math.random() * 3000 + 200,
  cashback: Math.random() * 300 + 20,
  ref: `REF${String(i + 1).padStart(3, "0")}`,
}));

export default function Pendentes() {
  const navigate = useNavigate();
  const [selectedIds, setSelectedIds] = useState<number[]>([]);

  const totalValue = mockPendentes.reduce((sum, p) => sum + p.value, 0);
  const selectedValue = mockPendentes
    .filter((p) => selectedIds.includes(p.id))
    .reduce((sum, p) => sum + p.value, 0);

  const toggleSelect = (id: number) => {
    setSelectedIds((prev) =>
      prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]
    );
  };

  const toggleSelectAll = () => {
    setSelectedIds((prev) =>
      prev.length === mockPendentes.length ? [] : mockPendentes.map((p) => p.id)
    );
  };

  const handlePayWithPix = () => {
    navigate("/pagamentos-pix", { state: { selectedIds } });
  };

  return (
    <div>
      <PageHeader
        title="Pendentes de Pagamento"
        subtitle="Comissões aguardando pagamento via Pix"
      />

      <div className="p-4 lg:p-6 space-y-6">
        {/* KPI Cards */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <KPICard
            title="Total Pendente"
            value={mockPendentes.length}
            icon={Clock}
            variant="warning"
          />
          <KPICard
            title="Valor Total"
            value={`R$ ${totalValue.toFixed(2)}`}
            icon={DollarSign}
            variant="default"
          />
          <KPICard
            title="Selecionado"
            value={`R$ ${selectedValue.toFixed(2)}`}
            icon={DollarSign}
            variant="info"
          />
        </div>

        {/* Alert */}
        <Alert>
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>
            Para liberar as comissões, selecione as transações e realize o pagamento via Pix.
            O processamento pode levar até 1 hora útil.
          </AlertDescription>
        </Alert>

        {/* Actions Bar */}
        {selectedIds.length > 0 && (
          <Card>
            <CardContent className="p-4">
              <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
                <p className="text-sm">
                  <span className="font-semibold">{selectedIds.length}</span> item(ns) selecionado(s) -{" "}
                  <span className="font-semibold">R$ {selectedValue.toFixed(2)}</span>
                </p>
                <Button onClick={handlePayWithPix}>
                  Pagar por Pix
                </Button>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Table */}
        <Card>
          <CardContent className="p-0">
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="w-12">
                      <Checkbox
                        checked={selectedIds.length === mockPendentes.length}
                        onCheckedChange={toggleSelectAll}
                      />
                    </TableHead>
                    <TableHead>Data</TableHead>
                    <TableHead>Cliente</TableHead>
                    <TableHead>Valor Total</TableHead>
                    <TableHead>Cashback</TableHead>
                    <TableHead>Referência</TableHead>
                    <TableHead>Ações</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {mockPendentes.map((item) => (
                    <TableRow key={item.id}>
                      <TableCell>
                        <Checkbox
                          checked={selectedIds.includes(item.id)}
                          onCheckedChange={() => toggleSelect(item.id)}
                        />
                      </TableCell>
                      <TableCell>
                        {new Date(item.date).toLocaleDateString("pt-BR")}
                      </TableCell>
                      <TableCell className="font-medium">{item.client}</TableCell>
                      <TableCell>R$ {item.value.toFixed(2)}</TableCell>
                      <TableCell className="text-primary">
                        R$ {item.cashback.toFixed(2)}
                      </TableCell>
                      <TableCell className="font-mono text-sm">{item.ref}</TableCell>
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
      </div>
    </div>
  );
}
