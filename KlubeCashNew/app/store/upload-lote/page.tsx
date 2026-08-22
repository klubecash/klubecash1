"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Download, FileSpreadsheet, UploadCloud } from "lucide-react";
import { useRef, useState } from "react";
import { storeFetch } from "@/lib/client-api";
import { useStoreContext } from "@/components/store/StoreProviders";

type BatchResult = {
  dataState: "ready" | "empty";
  generatedAt: string;
  replayed: boolean;
  summary: {
    total: number;
    processed: number;
    skipped: number;
    failed: number;
  };
  items: Array<{
    line: number;
    status: string;
    message?: string;
    transactionId?: number;
  }>;
};
export default function BatchUploadPage() {
  const context = useStoreContext();
  const queryClient = useQueryClient();
  const input = useRef<HTMLInputElement>(null);
  const idempotencyKey = useRef<string | null>(null);
  const [file, setFile] = useState<File | null>(null);
  const mutation = useMutation({
    mutationFn: async () => {
      const body = new FormData();
      body.set("csrfToken", context.csrfToken);
      body.set("file", file as File);
      idempotencyKey.current ??= crypto.randomUUID();
      return storeFetch<BatchResult>("transactions/batch", {
        method: "POST",
        headers: {
          "X-CSRF-Token": context.csrfToken,
          "X-Idempotency-Key": idempotencyKey.current,
        },
        body,
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["store-dashboard"] });
      queryClient.invalidateQueries({ queryKey: ["transactions"] });
    },
  });
  function choose(candidate?: File) {
    if (!candidate) return;
    if (candidate.size > 10 * 1024 * 1024) {
      alert("O arquivo excede 10 MB.");
      return;
    }
    if (!candidate.name.toLowerCase().endsWith(".csv")) {
      alert("Selecione um arquivo CSV.");
      return;
    }
    setFile(candidate);
    idempotencyKey.current = null;
  }
  return (
    <div className="store-page store-stack">
      <section className="store-page-head">
        <div>
          <h2>Importe várias vendas</h2>
          <p>
            Envie um CSV padronizado para registrar até 500 transações com um
            relatório detalhado por linha.
          </p>
        </div>
        <a
          className="store-button"
          href="/assets/downloads/template-upload-lote.csv"
          download
        >
          <Download size={16} />
          Baixar modelo
        </a>
      </section>
      <section className="store-grid store-grid-3">
        <div className="store-stat">
          <span className="store-stat-label">1. Prepare</span>
          <strong className="store-stat-value" style={{ fontSize: 17 }}>
            Use o modelo CSV
          </strong>
          <span className="store-stat-note">
            Não altere os nomes das colunas.
          </span>
        </div>
        <div className="store-stat">
          <span className="store-stat-label">2. Valide</span>
          <strong className="store-stat-value" style={{ fontSize: 17 }}>
            Revise os clientes
          </strong>
          <span className="store-stat-note">
            O e-mail deve existir e estar ativo.
          </span>
        </div>
        <div className="store-stat">
          <span className="store-stat-label">3. Importe</span>
          <strong className="store-stat-value" style={{ fontSize: 17 }}>
            Até 500 vendas
          </strong>
          <span className="store-stat-note">Tamanho máximo de 10 MB.</span>
        </div>
      </section>
      <section className="store-panel">
        <input
          ref={input}
          hidden
          type="file"
          accept=".csv,text/csv"
          onChange={(e) => choose(e.target.files?.[0])}
        />
        <div
          className="store-dropzone"
          tabIndex={0}
          role="button"
          onClick={() => input.current?.click()}
          onKeyDown={(e) => {
            if (e.key === "Enter") input.current?.click();
          }}
          onDragOver={(e) => e.preventDefault()}
          onDrop={(e) => {
            e.preventDefault();
            choose(e.dataTransfer.files[0]);
          }}
        >
          <UploadCloud size={42} />
          <h3>{file ? file.name : "Arraste seu CSV para cá"}</h3>
          <p>
            {file
              ? `${(file.size / 1024).toFixed(1)} KB — clique para trocar o arquivo`
              : "ou clique para selecionar no computador"}
          </p>
        </div>
        {mutation.isError && (
          <div
            className="store-alert store-alert-error"
            style={{ marginTop: 14 }}
          >
            {mutation.error.message}
          </div>
        )}
        <div className="store-form-actions" style={{ marginTop: 16 }}>
          <button
            className="store-button store-button-primary"
            disabled={!file || mutation.isPending}
            onClick={() => mutation.mutate()}
          >
            <FileSpreadsheet size={17} />
            {mutation.isPending
              ? "Processando arquivo..."
              : "Processar transações"}
          </button>
        </div>
      </section>
      {mutation.data && (
        <>
          <section className="store-grid store-grid-4">
            <Stat label="Total" value={mutation.data.summary.total} />
            <Stat label="Processadas" value={mutation.data.summary.processed} />
            <Stat label="Ignoradas" value={mutation.data.summary.skipped} />
            <Stat label="Com erro" value={mutation.data.summary.failed} />
          </section>
          <section className="store-panel">
            <div className="store-panel-head">
              <div>
                <h3>Relatório do processamento</h3>
                <p>Resultado detalhado de cada linha.</p>
              </div>
            </div>
            <div className="store-table-wrap">
              <table className="store-table">
                <thead>
                  <tr>
                    <th>Linha</th>
                    <th>Resultado</th>
                    <th>Mensagem</th>
                  </tr>
                </thead>
                <tbody>
                  {mutation.data.items.map((item) => (
                    <tr key={`${item.line}-${item.transactionId ?? item.message}`}>
                      <td>{item.line}</td>
                      <td>
                        <span
                          className={`store-status ${item.status === "success" ? "aprovado" : item.status === "error" ? "rejeitado" : "pendente"}`}
                        >
                          {item.status}
                        </span>
                      </td>
                      <td>
                        {item.message ?? `Venda #${item.transactionId} aprovada`}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
        </>
      )}
    </div>
  );
}
function Stat({ label, value }: { label: string; value: number }) {
  return (
    <div className="store-stat">
      <span className="store-stat-label">{label}</span>
      <strong className="store-stat-value">{value}</strong>
    </div>
  );
}
