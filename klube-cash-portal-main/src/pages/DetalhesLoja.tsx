import { useNavigate } from "react-router-dom";
import { Edit } from "lucide-react";
import { PageHeader } from "@/components/PageHeader";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

export default function DetalhesLoja() {
  const navigate = useNavigate();

  return (
    <div>
      <PageHeader
        title="Detalhes da Loja"
        subtitle="Visão geral das informações da loja"
        action={{
          label: "Editar Perfil",
          onClick: () => navigate("/perfil"),
          icon: <Edit className="h-4 w-4" />,
        }}
      />

      <div className="p-4 lg:p-6">
        <div className="max-w-3xl mx-auto space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Identidade</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="grid grid-cols-2 gap-2">
                <span className="text-sm text-muted-foreground">Nome Fantasia:</span>
                <span className="font-medium">Loja Exemplo</span>
                <span className="text-sm text-muted-foreground">Razão Social:</span>
                <span className="font-medium">Loja Exemplo LTDA</span>
                <span className="text-sm text-muted-foreground">CNPJ:</span>
                <span className="font-medium">12.345.678/0001-90</span>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Contatos</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="grid grid-cols-2 gap-2">
                <span className="text-sm text-muted-foreground">E-mail:</span>
                <span className="font-medium">contato@lojaexemplo.com</span>
                <span className="text-sm text-muted-foreground">Telefone:</span>
                <span className="font-medium">(11) 98765-4321</span>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Endereço</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="font-medium">Rua Exemplo, 123</p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Configurações de Cashback</CardTitle>
            </CardHeader>
            <CardContent>
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
        </div>
      </div>
    </div>
  );
}
