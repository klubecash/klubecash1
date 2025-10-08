import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { Search, Plus } from "lucide-react";
import { PageHeader } from "@/components/PageHeader";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Switch } from "@/components/ui/switch";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { useToast } from "@/hooks/use-toast";

export default function NovaVenda() {
  const navigate = useNavigate();
  const { toast } = useToast();
  const [showSuccessModal, setShowSuccessModal] = useState(false);
  const [useSaldo, setUseSaldo] = useState(false);
  
  const [formData, setFormData] = useState({
    cliente: "",
    valorTotal: "",
    codigo: "",
    descricao: "",
    dataHora: new Date().toISOString().slice(0, 16),
    valorSaldo: "",
  });

  // Mock percentages
  const cashbackCliente = 5;
  const cashbackAdmin = 2;
  const totalCashback = cashbackCliente + cashbackAdmin;

  const calcularCashback = () => {
    const valor = parseFloat(formData.valorTotal) || 0;
    const saldo = useSaldo ? parseFloat(formData.valorSaldo) || 0 : 0;
    const valorComCashback = valor - saldo;
    
    return {
      cliente: (valorComCashback * cashbackCliente) / 100,
      admin: (valorComCashback * cashbackAdmin) / 100,
      total: (valorComCashback * totalCashback) / 100,
    };
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!formData.cliente || !formData.valorTotal) {
      toast({
        title: "Erro",
        description: "Cliente e valor total são obrigatórios",
        variant: "destructive",
      });
      return;
    }

    const valor = parseFloat(formData.valorTotal);
    if (valor <= 0) {
      toast({
        title: "Erro",
        description: "O valor deve ser maior que zero",
        variant: "destructive",
      });
      return;
    }

    if (useSaldo) {
      const saldo = parseFloat(formData.valorSaldo) || 0;
      if (saldo > valor) {
        toast({
          title: "Erro",
          description: "O saldo usado não pode exceder o valor total",
          variant: "destructive",
        });
        return;
      }
    }

    setShowSuccessModal(true);
  };

  const cashback = calcularCashback();

  return (
    <div>
      <PageHeader
        title="Nova Venda"
        subtitle="Registre uma nova transação"
      />

      <div className="p-4 lg:p-6">
        <form onSubmit={handleSubmit} className="max-w-4xl mx-auto space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {/* Form Section */}
            <div className="lg:col-span-2 space-y-6">
              <Card>
                <CardHeader>
                  <CardTitle>Informações da Venda</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  {/* Cliente */}
                  <div className="space-y-2">
                    <Label htmlFor="cliente">Cliente *</Label>
                    <div className="flex gap-2">
                      <Input
                        id="cliente"
                        placeholder="Buscar cliente..."
                        value={formData.cliente}
                        onChange={(e) => setFormData({ ...formData, cliente: e.target.value })}
                      />
                      <Button type="button" variant="outline" size="icon">
                        <Search className="h-4 w-4" />
                      </Button>
                    </div>
                  </div>

                  {/* Valor Total */}
                  <div className="space-y-2">
                    <Label htmlFor="valor">Valor Total *</Label>
                    <Input
                      id="valor"
                      type="number"
                      step="0.01"
                      placeholder="0,00"
                      value={formData.valorTotal}
                      onChange={(e) => setFormData({ ...formData, valorTotal: e.target.value })}
                    />
                  </div>

                  {/* Código */}
                  <div className="space-y-2">
                    <Label htmlFor="codigo">Código da Transação</Label>
                    <Input
                      id="codigo"
                      placeholder="Ex: TRX001"
                      value={formData.codigo}
                      onChange={(e) => setFormData({ ...formData, codigo: e.target.value })}
                    />
                  </div>

                  {/* Data/Hora */}
                  <div className="space-y-2">
                    <Label htmlFor="dataHora">Data e Hora da Venda</Label>
                    <Input
                      id="dataHora"
                      type="datetime-local"
                      value={formData.dataHora}
                      onChange={(e) => setFormData({ ...formData, dataHora: e.target.value })}
                    />
                  </div>

                  {/* Descrição */}
                  <div className="space-y-2">
                    <Label htmlFor="descricao">Descrição</Label>
                    <Textarea
                      id="descricao"
                      placeholder="Detalhes adicionais..."
                      rows={3}
                      value={formData.descricao}
                      onChange={(e) => setFormData({ ...formData, descricao: e.target.value })}
                    />
                  </div>

                  {/* Usar Saldo */}
                  <div className="flex items-center justify-between p-4 rounded-lg border">
                    <div className="space-y-0.5">
                      <Label htmlFor="usar-saldo">Usar Saldo do Cliente</Label>
                      <p className="text-sm text-muted-foreground">
                        Descontar do saldo disponível do cliente
                      </p>
                    </div>
                    <Switch
                      id="usar-saldo"
                      checked={useSaldo}
                      onCheckedChange={setUseSaldo}
                    />
                  </div>

                  {useSaldo && (
                    <div className="space-y-2">
                      <Label htmlFor="valorSaldo">Valor do Saldo a Usar</Label>
                      <Input
                        id="valorSaldo"
                        type="number"
                        step="0.01"
                        placeholder="0,00"
                        value={formData.valorSaldo}
                        onChange={(e) => setFormData({ ...formData, valorSaldo: e.target.value })}
                      />
                    </div>
                  )}
                </CardContent>
              </Card>
            </div>

            {/* Preview Section */}
            <div className="space-y-4">
              <Card>
                <CardHeader>
                  <CardTitle className="text-base">Preview de Cashback</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div>
                    <p className="text-sm text-muted-foreground">Cliente</p>
                    <p className="text-2xl font-semibold text-primary">
                      {cashbackCliente}%
                    </p>
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">Admin</p>
                    <p className="text-2xl font-semibold text-secondary">
                      {cashbackAdmin}%
                    </p>
                  </div>
                  <div className="pt-4 border-t">
                    <p className="text-sm text-muted-foreground">Total Cashback</p>
                    <p className="text-3xl font-bold">
                      {totalCashback}%
                    </p>
                  </div>
                  {formData.valorTotal && (
                    <div className="pt-4 border-t space-y-2">
                      <div className="flex justify-between text-sm">
                        <span>Cashback Cliente:</span>
                        <span className="font-medium">
                          R$ {cashback.cliente.toFixed(2)}
                        </span>
                      </div>
                      <div className="flex justify-between text-sm">
                        <span>Cashback Admin:</span>
                        <span className="font-medium">
                          R$ {cashback.admin.toFixed(2)}
                        </span>
                      </div>
                      <div className="flex justify-between font-semibold">
                        <span>Total:</span>
                        <span className="text-primary">
                          R$ {cashback.total.toFixed(2)}
                        </span>
                      </div>
                    </div>
                  )}
                </CardContent>
              </Card>
            </div>
          </div>

          {/* Actions */}
          <div className="flex gap-4 justify-end">
            <Button
              type="button"
              variant="outline"
              onClick={() => navigate("/")}
            >
              Cancelar
            </Button>
            <Button type="submit">
              <Plus className="mr-2 h-4 w-4" />
              Registrar Venda
            </Button>
          </div>
        </form>
      </div>

      {/* Success Modal */}
      <Dialog open={showSuccessModal} onOpenChange={setShowSuccessModal}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Venda Registrada com Sucesso!</DialogTitle>
            <DialogDescription>
              A transação foi processada e o cashback será creditado.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-2 py-4">
            <div className="flex justify-between">
              <span className="text-sm text-muted-foreground">ID:</span>
              <span className="font-mono font-medium">TRX{Date.now()}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-sm text-muted-foreground">Valor Total:</span>
              <span className="font-medium">R$ {parseFloat(formData.valorTotal || "0").toFixed(2)}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-sm text-muted-foreground">Cashback Total:</span>
              <span className="font-medium text-primary">R$ {cashback.total.toFixed(2)}</span>
            </div>
          </div>
          <DialogFooter className="gap-2 sm:gap-0">
            <Button
              variant="outline"
              onClick={() => {
                setShowSuccessModal(false);
                setFormData({
                  cliente: "",
                  valorTotal: "",
                  codigo: "",
                  descricao: "",
                  dataHora: new Date().toISOString().slice(0, 16),
                  valorSaldo: "",
                });
                setUseSaldo(false);
              }}
            >
              Nova Venda
            </Button>
            <Button onClick={() => navigate("/transacoes")}>
              Ver Transações
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
