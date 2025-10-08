import { useState } from "react";
import { Save } from "lucide-react";
import { PageHeader } from "@/components/PageHeader";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { useToast } from "@/hooks/use-toast";

export default function Perfil() {
  const { toast } = useToast();
  const [hideValues, setHideValues] = useState(false);
  const [formData, setFormData] = useState({
    nomeFantasia: "Loja Exemplo",
    razaoSocial: "Loja Exemplo LTDA",
    cnpj: "12.345.678/0001-90",
    endereco: "Rua Exemplo, 123",
    telefone: "(11) 98765-4321",
    email: "contato@lojaexemplo.com",
    banco: "Banco do Brasil",
    agencia: "1234-5",
    conta: "12345-6",
    chavePix: "12345678000190",
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    toast({
      title: "Perfil atualizado",
      description: "Suas alterações foram salvas com sucesso.",
    });
  };

  return (
    <div>
      <PageHeader
        title="Perfil da Loja"
        subtitle="Gerencie as informações da sua loja"
      />

      <div className="p-4 lg:p-6">
        <form onSubmit={handleSubmit} className="max-w-3xl mx-auto space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Dados da Loja</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="nomeFantasia">Nome Fantasia *</Label>
                  <Input
                    id="nomeFantasia"
                    value={formData.nomeFantasia}
                    onChange={(e) => setFormData({ ...formData, nomeFantasia: e.target.value })}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="razaoSocial">Razão Social *</Label>
                  <Input
                    id="razaoSocial"
                    value={formData.razaoSocial}
                    onChange={(e) => setFormData({ ...formData, razaoSocial: e.target.value })}
                  />
                </div>
              </div>
              <div className="space-y-2">
                <Label htmlFor="cnpj">CNPJ *</Label>
                <Input
                  id="cnpj"
                  value={formData.cnpj}
                  onChange={(e) => setFormData({ ...formData, cnpj: e.target.value })}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="endereco">Endereço</Label>
                <Input
                  id="endereco"
                  value={formData.endereco}
                  onChange={(e) => setFormData({ ...formData, endereco: e.target.value })}
                />
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="telefone">Telefone</Label>
                  <Input
                    id="telefone"
                    value={formData.telefone}
                    onChange={(e) => setFormData({ ...formData, telefone: e.target.value })}
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
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Configurações de Cashback</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <p className="text-sm text-muted-foreground">
                As configurações de cashback são gerenciadas pelo administrador do sistema.
              </p>
              <div className="grid grid-cols-2 gap-4 p-4 bg-muted rounded-lg">
                <div>
                  <p className="text-sm text-muted-foreground">Cliente</p>
                  <p className="text-2xl font-semibold">5%</p>
                </div>
                <div>
                  <p className="text-sm text-muted-foreground">Admin</p>
                  <p className="text-2xl font-semibold">2%</p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Dados Bancários</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="banco">Banco</Label>
                  <Input
                    id="banco"
                    value={formData.banco}
                    onChange={(e) => setFormData({ ...formData, banco: e.target.value })}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="agencia">Agência</Label>
                  <Input
                    id="agencia"
                    value={formData.agencia}
                    onChange={(e) => setFormData({ ...formData, agencia: e.target.value })}
                  />
                </div>
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="conta">Conta</Label>
                  <Input
                    id="conta"
                    value={formData.conta}
                    onChange={(e) => setFormData({ ...formData, conta: e.target.value })}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="chavePix">Chave Pix</Label>
                  <Input
                    id="chavePix"
                    value={formData.chavePix}
                    onChange={(e) => setFormData({ ...formData, chavePix: e.target.value })}
                  />
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Preferências</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <Label htmlFor="hideValues">Ocultar valores por padrão</Label>
                  <p className="text-sm text-muted-foreground">
                    Valores monetários serão ocultados ao carregar as páginas
                  </p>
                </div>
                <Switch id="hideValues" checked={hideValues} onCheckedChange={setHideValues} />
              </div>
            </CardContent>
          </Card>

          <div className="flex justify-end">
            <Button type="submit">
              <Save className="mr-2 h-4 w-4" />
              Salvar alterações
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
