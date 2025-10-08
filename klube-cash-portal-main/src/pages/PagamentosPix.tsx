import { useState, useEffect } from "react";
import { useNavigate, useLocation } from "react-router-dom";
import { Copy, QrCode, CheckCircle2, Clock } from "lucide-react";
import { PageHeader } from "@/components/PageHeader";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { useToast } from "@/hooks/use-toast";

export default function PagamentosPix() {
  const navigate = useNavigate();
  const location = useLocation();
  const { toast } = useToast();
  const [paymentStatus, setPaymentStatus] = useState<"pending" | "processing" | "confirmed">("pending");
  const [countdown, setCountdown] = useState(600); // 10 minutes

  const selectedIds = (location.state as any)?.selectedIds || [];
  const transactionCount = selectedIds.length || 5; // Mock
  const totalAmount = 2450.80; // Mock
  const pixCode = "00020126580014br.gov.bcb.pix013684b9d7f9-5c8d-4e6f-b1a2-3c4d5e6f7a8b5204000053039865802BR5925KLUBECASH PAGAMENTOS LTDA6009SAO PAULO62070503***6304ABCD";

  useEffect(() => {
    if (paymentStatus === "pending" && countdown > 0) {
      const timer = setInterval(() => {
        setCountdown((prev) => prev - 1);
      }, 1000);
      return () => clearInterval(timer);
    }
  }, [paymentStatus, countdown]);

  const formatTime = (seconds: number) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${String(secs).padStart(2, "0")}`;
  };

  const handleCopyPix = () => {
    navigator.clipboard.writeText(pixCode);
    toast({
      title: "Código copiado!",
      description: "O código Pix foi copiado para a área de transferência.",
    });
  };

  const simulatePayment = () => {
    setPaymentStatus("processing");
    setTimeout(() => {
      setPaymentStatus("confirmed");
    }, 3000);
  };

  if (paymentStatus === "confirmed") {
    return (
      <div>
        <PageHeader title="Pagamento Confirmado" />
        <div className="p-4 lg:p-6 flex items-center justify-center min-h-[60vh]">
          <Card className="max-w-md w-full">
            <CardContent className="p-8 text-center space-y-6">
              <div className="w-20 h-20 mx-auto rounded-full bg-green-100 flex items-center justify-center">
                <CheckCircle2 className="h-10 w-10 text-green-600" />
              </div>
              <div>
                <h2 className="text-2xl font-bold text-foreground mb-2">
                  Pagamento Confirmado!
                </h2>
                <p className="text-muted-foreground">
                  Seu pagamento foi processado com sucesso.
                </p>
              </div>
              <div className="p-4 bg-muted rounded-lg space-y-2">
                <div className="flex justify-between text-sm">
                  <span>Transações:</span>
                  <span className="font-semibold">{transactionCount}</span>
                </div>
                <div className="flex justify-between text-sm">
                  <span>Valor Total:</span>
                  <span className="font-semibold">R$ {totalAmount.toFixed(2)}</span>
                </div>
                <div className="flex justify-between text-sm">
                  <span>Método:</span>
                  <span className="font-semibold">Pix</span>
                </div>
              </div>
              <div className="flex gap-2">
                <Button
                  variant="outline"
                  className="flex-1"
                  onClick={() => navigate("/historico-pagamentos")}
                >
                  Ir para Histórico
                </Button>
                <Button
                  className="flex-1"
                  onClick={() => navigate("/")}
                >
                  Voltar ao Dashboard
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader title="Pagamentos (Pix)" subtitle="Realize o pagamento via Pix" />

      <div className="p-4 lg:p-6 space-y-6">
        <div className="max-w-3xl mx-auto space-y-6">
          {/* Summary */}
          <Card>
            <CardHeader>
              <CardTitle>Resumo do Pagamento</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="flex justify-between">
                <span className="text-muted-foreground">Transações selecionadas:</span>
                <span className="font-semibold">{transactionCount}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Taxas:</span>
                <span className="font-semibold">R$ 0,00</span>
              </div>
              <div className="flex justify-between text-lg pt-3 border-t">
                <span className="font-semibold">Total a pagar:</span>
                <span className="font-bold text-primary">R$ {totalAmount.toFixed(2)}</span>
              </div>
            </CardContent>
          </Card>

          {/* Pix Payment */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center justify-between">
                <span>Pagamento via Pix</span>
                {paymentStatus === "pending" && (
                  <div className="flex items-center gap-2 text-sm font-normal text-muted-foreground">
                    <Clock className="h-4 w-4" />
                    Expira em {formatTime(countdown)}
                  </div>
                )}
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
              {paymentStatus === "processing" ? (
                <div className="text-center py-8">
                  <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
                  <p className="text-muted-foreground">Verificando pagamento...</p>
                </div>
              ) : (
                <>
                  <Alert>
                    <AlertDescription>
                      <ol className="list-decimal list-inside space-y-1 text-sm">
                        <li>Abra o app do seu banco</li>
                        <li>Escolha pagar com Pix QR Code ou Pix Copia e Cola</li>
                        <li>Escaneie o código ou cole o código Pix</li>
                        <li>Confirme o pagamento</li>
                      </ol>
                    </AlertDescription>
                  </Alert>

                  {/* QR Code */}
                  <div className="flex flex-col items-center gap-4">
                    <div className="w-64 h-64 bg-white border-4 border-border rounded-lg flex items-center justify-center">
                      <QrCode className="h-48 w-48 text-foreground" />
                    </div>
                    <p className="text-sm text-muted-foreground">
                      Escaneie o QR Code com o app do seu banco
                    </p>
                  </div>

                  {/* Pix Code */}
                  <div className="space-y-2">
                    <label className="text-sm font-medium">Código Pix Copia e Cola</label>
                    <div className="flex gap-2">
                      <input
                        type="text"
                        value={pixCode}
                        readOnly
                        className="flex-1 px-3 py-2 text-sm border rounded-md bg-muted font-mono"
                      />
                      <Button onClick={handleCopyPix} size="icon">
                        <Copy className="h-4 w-4" />
                      </Button>
                    </div>
                  </div>

                  {/* Simulate Payment (dev only) */}
                  <Button
                    onClick={simulatePayment}
                    variant="outline"
                    className="w-full"
                  >
                    Simular Pagamento (Dev)
                  </Button>
                </>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
