import { useState } from "react";
import { Download, Upload } from "lucide-react";
import { PageHeader } from "@/components/PageHeader";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Progress } from "@/components/ui/progress";

export default function Importacao() {
  const [file, setFile] = useState<File | null>(null);
  const [processing, setProcessing] = useState(false);
  const [progress, setProgress] = useState(0);
  const [result, setResult] = useState<any>(null);

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      setFile(e.target.files[0]);
    }
  };

  const handleProcess = () => {
    setProcessing(true);
    setProgress(0);
    
    const interval = setInterval(() => {
      setProgress((prev) => {
        if (prev >= 100) {
          clearInterval(interval);
          setProcessing(false);
          setResult({
            total: 100,
            inserted: 95,
            ignored: 3,
            errors: 2,
          });
          return 100;
        }
        return prev + 10;
      });
    }, 200);
  };

  return (
    <div>
      <PageHeader title="Importação em Lote" subtitle="Importe múltiplas transações via CSV" />

      <div className="p-4 lg:p-6">
        <div className="max-w-3xl mx-auto space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Orientações</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <Alert>
                <AlertDescription>
                  <ol className="list-decimal list-inside space-y-1 text-sm">
                    <li>Baixe o modelo CSV abaixo</li>
                    <li>Preencha com os dados das transações</li>
                    <li>Faça upload do arquivo preenchido</li>
                    <li>Aguarde o processamento</li>
                  </ol>
                </AlertDescription>
              </Alert>
              <Button variant="outline">
                <Download className="mr-2 h-4 w-4" />
                Baixar Modelo CSV
              </Button>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Upload de Arquivo</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="border-2 border-dashed rounded-lg p-8 text-center">
                <Upload className="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                <input
                  type="file"
                  accept=".csv"
                  onChange={handleFileChange}
                  className="hidden"
                  id="file-upload"
                />
                <label htmlFor="file-upload" className="cursor-pointer">
                  <Button variant="outline" asChild>
                    <span>Selecionar arquivo CSV</span>
                  </Button>
                </label>
                {file && (
                  <p className="text-sm text-muted-foreground mt-2">
                    Arquivo selecionado: {file.name}
                  </p>
                )}
              </div>

              {file && !processing && !result && (
                <div className="flex gap-2">
                  <Button onClick={() => setFile(null)} variant="outline" className="flex-1">
                    Cancelar
                  </Button>
                  <Button onClick={handleProcess} className="flex-1">
                    Processar
                  </Button>
                </div>
              )}

              {processing && (
                <div className="space-y-2">
                  <p className="text-sm text-muted-foreground">Processando arquivo...</p>
                  <Progress value={progress} />
                </div>
              )}

              {result && (
                <Card>
                  <CardHeader>
                    <CardTitle className="text-base">Resultado do Processamento</CardTitle>
                  </CardHeader>
                  <CardContent className="space-y-2">
                    <div className="grid grid-cols-2 gap-2 text-sm">
                      <span className="text-muted-foreground">Total processado:</span>
                      <span className="font-medium">{result.total}</span>
                      <span className="text-muted-foreground">Inseridos:</span>
                      <span className="font-medium text-green-600">{result.inserted}</span>
                      <span className="text-muted-foreground">Ignorados:</span>
                      <span className="font-medium text-amber-600">{result.ignored}</span>
                      <span className="text-muted-foreground">Erros:</span>
                      <span className="font-medium text-red-600">{result.errors}</span>
                    </div>
                    {result.errors > 0 && (
                      <Button variant="outline" className="w-full mt-4">
                        <Download className="mr-2 h-4 w-4" />
                        Baixar Relatório de Erros
                      </Button>
                    )}
                  </CardContent>
                </Card>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
