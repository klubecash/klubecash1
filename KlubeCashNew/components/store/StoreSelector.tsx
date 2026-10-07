"use client";

import { useEffect, useState } from "react";
import { storeFetch } from "@/lib/client-api";

type AvailableStore = { id: number; name: string; role: string; networkId: number | null };

export function StoreSelector() {
  const [stores, setStores] = useState<AvailableStore[]>([]);
  const [csrfToken, setCsrfToken] = useState("");
  const [error, setError] = useState("");
  const [pending, setPending] = useState<number | null>(null);

  useEffect(() => {
    storeFetch<{ stores: AvailableStore[]; csrfToken: string }>("stores")
      .then((result) => { setStores(result.stores); setCsrfToken(result.csrfToken); })
      .catch((reason: Error) => setError(reason.message));
  }, []);

  async function select(storeId: number) {
    setPending(storeId); setError("");
    try {
      await storeFetch("active-store", { method: "POST", headers: { "X-CSRF-Token": csrfToken },
        body: JSON.stringify({ storeId, csrfToken }) });
      window.location.reload();
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : "Não foi possível escolher a filial.");
      setPending(null);
    }
  }

  return <main className="store-select-page"><section className="store-panel">
    <h1>Escolha a filial</h1>
    <p>Suas vendas e métricas serão identificadas pela filial selecionada. Você pode trocar depois.</p>
    {error && <p className="store-alert store-alert-error" role="alert">{error}</p>}
    {!error && stores.length === 0 && <p>Carregando suas filiais…</p>}
    <div className="store-select-list">{stores.map((store) => <button key={store.id} type="button"
      className="store-button" disabled={pending !== null || !csrfToken} onClick={() => select(store.id)}>
      {store.name} <small>· {store.role.replaceAll("_", " ")}</small>
    </button>)}</div>
  </section></main>;
}
