"use client";

import { AlertTriangle } from "lucide-react";
import { useEffect } from "react";

export default function StoreError({ error, reset }: { error: Error & { digest?: string }; reset: () => void }) {
  useEffect(() => {
    console.error("Falha ao carregar a área lojista.", { digest: error.digest });
  }, [error]);

  return (
    <div className="store-page">
      <section className="store-panel store-empty">
        <AlertTriangle size={46} />
        <h3>Não foi possível carregar esta tela</h3>
        <p>Confira sua conexão e tente novamente. Se o erro persistir, informe o código exibido ao suporte.</p>
        {error.digest && <span className="store-code">Código: {error.digest}</span>}
        <button className="store-button store-button-primary" onClick={reset}>Tentar novamente</button>
      </section>
    </div>
  );
}
