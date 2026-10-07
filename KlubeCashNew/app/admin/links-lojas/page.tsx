"use client";

import { useEffect, useState } from "react";
import { adminFetch, mutationHeaders } from "@/lib/admin-client";
import { useAdminContext } from "@/components/admin/AdminProviders";

type LinkRow = { storeId: number; storeName: string; status: string; token: string | null; enabled: boolean; version: number };
type Listing = { items: LinkRow[]; total: number; page: number };

export default function StoreLinksAdminPage() {
  const { csrfToken } = useAdminContext();
  const [search, setSearch] = useState("");
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(1);
  const [listing, setListing] = useState<Listing | null>(null);
  const [busy, setBusy] = useState<number | null>(null);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const load = async () => setListing(await adminFetch<Listing>(`store-wallet-links?search=${encodeURIComponent(query)}&page=${page}`));
  useEffect(() => {
    let active = true;
    void adminFetch<Listing>(`store-wallet-links?search=${encodeURIComponent(query)}&page=${page}`).then((data) => { if (active) setListing(data); }).catch((cause) => { if (active) setError(cause instanceof Error ? cause.message : "Não foi possível carregar os links."); });
    return () => { active = false; };
  }, [query, page]);
  const link = (row: LinkRow) => row.token ? `${window.location.origin}/saldo/${row.token}` : "";
  const mutate = async (row: LinkRow, action: "enable" | "disable" | "rotate") => {
    if (action === "rotate" && !window.confirm(`Gerar outro link para ${row.storeName}? O link antigo deixará de funcionar e as placas já impressas precisarão ser substituídas.`)) return;
    setBusy(row.storeId); setError(""); setMessage("");
    try {
      await adminFetch(`store-wallet-links/${row.storeId}/${action}`, { method: "POST", headers: mutationHeaders(csrfToken, true), body: JSON.stringify({ version: row.version }) });
      await load();
      setMessage(action === "rotate" ? "Novo link gerado. Atualize as placas e artes que usam o link antigo." : "Configuração atualizada.");
    } catch (cause) { setError(cause instanceof Error ? cause.message : "Falha ao alterar o link."); }
    finally { setBusy(null); }
  };
  return <div style={{ display: "grid", gap: 18 }}>
    <section className="admin-panel" style={{ padding: 24, borderRadius: 16 }}><h2>Links das lojas</h2><p>Cada loja aprovada tem um link para usar na arte do próprio QR code. O saldo só aparece depois que o cliente se autentica.</p>
      <form onSubmit={(event) => { event.preventDefault(); setPage(1); setQuery(search); }} style={{ display: "flex", gap: 8, flexWrap: "wrap" }}><input aria-label="Pesquisar loja" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Pesquisar loja" style={{ padding: 12, minWidth: 240, borderRadius: 8, border: "1px solid #bacdc5" }} /><button type="submit">Pesquisar</button></form>
    </section>
    {error && <p role="alert" style={{ color: "#a12b25" }}>{error}</p>}{message && <p role="status">{message}</p>}
    <section className="admin-panel" style={{ padding: 24, borderRadius: 16 }}>
      {!listing ? <p>Carregando...</p> : listing.items.length === 0 ? <p>Nenhuma loja encontrada.</p> : listing.items.map((row) => <article key={row.storeId} style={{ padding: "18px 0", borderBottom: "1px solid #dce8e1" }}>
        <div style={{ display: "flex", justifyContent: "space-between", gap: 12, flexWrap: "wrap" }}><strong>{row.storeName}</strong><span>{row.status === "aprovado" ? row.enabled ? "Ativo" : "Desativado" : "Loja não aprovada"}</span></div>
        {row.token && <><code style={{ display: "block", overflowWrap: "anywhere", margin: "10px 0" }}>{link(row)}</code><div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}><button type="button" onClick={() => void navigator.clipboard.writeText(link(row)).then(() => setMessage("Link copiado."))}>Copiar link</button><button type="button" disabled={busy === row.storeId} onClick={() => void mutate(row, row.enabled ? "disable" : "enable")}>{row.enabled ? "Desativar" : "Ativar"}</button><button type="button" disabled={busy === row.storeId} onClick={() => void mutate(row, "rotate")}>Gerar outro link</button></div></>}
      </article>)}
      {listing && listing.total > 20 && <div style={{ display: "flex", gap: 12, marginTop: 18 }}><button disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>Anterior</button><span>Página {page}</span><button disabled={page * 20 >= listing.total} onClick={() => setPage((value) => value + 1)}>Próxima</button></div>}
    </section>
  </div>;
}
