import { useState } from "react";
import { UserPlus, MoreVertical } from "lucide-react";
import { PageHeader } from "@/components/PageHeader";
import { KPICard } from "@/components/KPICard";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Users } from "lucide-react";
import { useToast } from "@/hooks/use-toast";

const mockFuncionarios = [
  { id: 1, name: "João Silva", email: "joao@exemplo.com", role: "Gerente", status: "ativo", lastAccess: "2025-01-05" },
  { id: 2, name: "Maria Santos", email: "maria@exemplo.com", role: "Atendente", status: "ativo", lastAccess: "2025-01-04" },
  { id: 3, name: "Pedro Costa", email: "pedro@exemplo.com", role: "Atendente", status: "inativo", lastAccess: "2024-12-20" },
];

export default function Funcionarios() {
  const { toast } = useToast();
  const [showDialog, setShowDialog] = useState(false);
  const [editingEmployee, setEditingEmployee] = useState<any>(null);
  const [formData, setFormData] = useState({
    name: "",
    email: "",
    role: "",
    permissions: {
      registerSale: false,
      viewPayments: false,
      viewTransactions: false,
    },
  });

  const activeCount = mockFuncionarios.filter((f) => f.status === "ativo").length;
  const inactiveCount = mockFuncionarios.filter((f) => f.status === "inativo").length;

  const handleOpenDialog = (employee?: any) => {
    if (employee) {
      setEditingEmployee(employee);
      setFormData({
        name: employee.name,
        email: employee.email,
        role: employee.role,
        permissions: {
          registerSale: true,
          viewPayments: false,
          viewTransactions: true,
        },
      });
    } else {
      setEditingEmployee(null);
      setFormData({
        name: "",
        email: "",
        role: "",
        permissions: {
          registerSale: false,
          viewPayments: false,
          viewTransactions: false,
        },
      });
    }
    setShowDialog(true);
  };

  const handleSubmit = () => {
    toast({
      title: editingEmployee ? "Funcionário atualizado" : "Funcionário adicionado",
      description: `${formData.name} foi ${editingEmployee ? "atualizado" : "adicionado"} com sucesso.`,
    });
    setShowDialog(false);
  };

  return (
    <div>
      <PageHeader
        title="Funcionários"
        subtitle="Gerencie sua equipe"
        action={{
          label: "Adicionar Funcionário",
          onClick: () => handleOpenDialog(),
          icon: <UserPlus className="h-4 w-4" />,
        }}
      />

      <div className="p-4 lg:p-6 space-y-6">
        {/* KPI Cards */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <KPICard
            title="Total de Funcionários"
            value={mockFuncionarios.length}
            icon={Users}
            variant="info"
          />
          <KPICard
            title="Ativos"
            value={activeCount}
            icon={Users}
            variant="success"
          />
          <KPICard
            title="Inativos"
            value={inactiveCount}
            icon={Users}
            variant="default"
          />
        </div>

        {/* Employees Table */}
        <Card>
          <CardContent className="p-0">
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Nome</TableHead>
                    <TableHead>E-mail</TableHead>
                    <TableHead>Papel</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Último Acesso</TableHead>
                    <TableHead>Ações</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {mockFuncionarios.map((employee) => (
                    <TableRow key={employee.id}>
                      <TableCell className="font-medium">{employee.name}</TableCell>
                      <TableCell>{employee.email}</TableCell>
                      <TableCell>{employee.role}</TableCell>
                      <TableCell>
                        <Badge
                          className={
                            employee.status === "ativo"
                              ? "bg-green-100 text-green-800"
                              : "bg-gray-100 text-gray-800"
                          }
                        >
                          {employee.status}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        {new Date(employee.lastAccess).toLocaleDateString("pt-BR")}
                      </TableCell>
                      <TableCell>
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon">
                              <MoreVertical className="h-4 w-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            <DropdownMenuItem onClick={() => handleOpenDialog(employee)}>
                              Editar
                            </DropdownMenuItem>
                            <DropdownMenuItem>
                              {employee.status === "ativo" ? "Desativar" : "Reativar"}
                            </DropdownMenuItem>
                            <DropdownMenuItem>Resetar senha</DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Add/Edit Dialog */}
      <Dialog open={showDialog} onOpenChange={setShowDialog}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>
              {editingEmployee ? "Editar Funcionário" : "Adicionar Funcionário"}
            </DialogTitle>
            <DialogDescription>
              Preencha os dados do funcionário
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="name">Nome *</Label>
              <Input
                id="name"
                value={formData.name}
                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="email">E-mail *</Label>
              <Input
                id="email"
                type="email"
                value={formData.email}
                onChange={(e) => setFormData({ ...formData, email: e.target.value })}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="role">Papel *</Label>
              <Select value={formData.role} onValueChange={(value) => setFormData({ ...formData, role: value })}>
                <SelectTrigger id="role">
                  <SelectValue placeholder="Selecione" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="gerente">Gerente</SelectItem>
                  <SelectItem value="atendente">Atendente</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-3">
              <Label>Permissões</Label>
              <div className="space-y-2">
                <div className="flex items-center space-x-2">
                  <Checkbox
                    id="registerSale"
                    checked={formData.permissions.registerSale}
                    onCheckedChange={(checked) =>
                      setFormData({
                        ...formData,
                        permissions: { ...formData.permissions, registerSale: checked as boolean },
                      })
                    }
                  />
                  <label htmlFor="registerSale" className="text-sm">
                    Registrar venda
                  </label>
                </div>
                <div className="flex items-center space-x-2">
                  <Checkbox
                    id="viewPayments"
                    checked={formData.permissions.viewPayments}
                    onCheckedChange={(checked) =>
                      setFormData({
                        ...formData,
                        permissions: { ...formData.permissions, viewPayments: checked as boolean },
                      })
                    }
                  />
                  <label htmlFor="viewPayments" className="text-sm">
                    Ver pagamentos
                  </label>
                </div>
                <div className="flex items-center space-x-2">
                  <Checkbox
                    id="viewTransactions"
                    checked={formData.permissions.viewTransactions}
                    onCheckedChange={(checked) =>
                      setFormData({
                        ...formData,
                        permissions: { ...formData.permissions, viewTransactions: checked as boolean },
                      })
                    }
                  />
                  <label htmlFor="viewTransactions" className="text-sm">
                    Ver transações
                  </label>
                </div>
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowDialog(false)}>
              Cancelar
            </Button>
            <Button onClick={handleSubmit}>Salvar</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
