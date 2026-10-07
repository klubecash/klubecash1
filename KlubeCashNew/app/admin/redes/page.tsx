"use client";

import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { adminFetch, mutationHeaders } from "@/lib/admin-client";
import { useAdminContext } from "@/components/admin/AdminProviders";
import { moneyFromCents } from "@/lib/format";

type Network = { id: number; name: string; status: string; active_stores?: number; suspended_stores?: number;
  stores?: Array<{ store_id: number; store_name: string; status: string; cnpj: string }>;
  managers?: Array<{ user_id: number; nome: string; email: string; account_status?: string }>;
  events?: Array<{ id: number; action: string; reason: string; occurred_at: string; store_id: number | null; actor_id: number }> };
type CandidateStore = { id: number; name: string; cnpj: string; existing_network_id: number | null };
type CandidateManager = { id: number; name: string; email: string; account_type: string };
type WalletCase = { userId: number; storeId: number; issues: string[]; walletCents: number | null; creditCents: number; lastMovementCents: number | null; canRepairMissingWallet: boolean };
type WalletHealth = { healthy: boolean; caseCount: number; cases: WalletCase[]; truncated?: boolean; storeIds: number[]; repairReady?: boolean;
  repairEvents?: Array<{ id: number; user_id: number; store_id: number; action: string; reason: string; occurred_at: string; actor_name: string; before_json: string; after_json: string }> };
type Reconciliation = { storeId: number; crossBranchUsage: Array<{ origin_store_id: number; redemption_store_id: number; sales: number; net_used_cents: number }> };

export default function NetworksAdminPage() {
  const { csrfToken } = useAdminContext();
  const client = useQueryClient();
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [networkSearch, setNetworkSearch] = useState("");
  const [newName, setNewName] = useState("");
  const [reason, setReason] = useState("");
  const [evidence, setEvidence] = useState("");
  const [storeLookup, setStoreLookup] = useState("");
  const [storeTerm, setStoreTerm] = useState("");
  const [managerLookup, setManagerLookup] = useState("");
  const [managerTerm, setManagerTerm] = useState("");
  const [candidateStore, setCandidateStore] = useState<CandidateStore | null>(null);
  const [reportStoreId, setReportStoreId] = useState<number | null>(null);
  const [notice, setNotice] = useState("");
  useEffect(() => {
    const frame = requestAnimationFrame(() => {
      const params = new URLSearchParams(window.location.search);
      const requested = Number(params.get("network"));
      if (Number.isSafeInteger(requested) && requested > 0) setSelectedId(requested);
      const store = params.get("store")?.trim() ?? "";
      if (store.length >= 2) { setStoreLookup(store); setStoreTerm(store); }
    });
    return () => cancelAnimationFrame(frame);
  }, []);
  const networks = useQuery({ queryKey: ["networks"], queryFn: () => adminFetch<{ items: Network[] }>("store-networks") });
  const detail = useQuery({ queryKey: ["network", selectedId], queryFn: () => adminFetch<Network>(`store-networks/${selectedId}`), enabled: selectedId !== null });
  const stores = useQuery({ queryKey: ["network-store-candidates", storeTerm], queryFn: () => adminFetch<{ items: CandidateStore[] }>(`store-networks/candidates?kind=stores&search=${encodeURIComponent(storeTerm)}`), enabled: storeTerm.length >= 2 });
  const managers = useQuery({ queryKey: ["network-manager-candidates", managerTerm], queryFn: () => adminFetch<{ items: CandidateManager[] }>(`store-networks/candidates?kind=managers&search=${encodeURIComponent(managerTerm)}`), enabled: managerTerm.length >= 2 });
  const health = useQuery({ queryKey: ["network-wallet-health", selectedId, candidateStore?.id],
    queryFn: () => adminFetch<WalletHealth>(`store-networks/${selectedId}/wallet-health${candidateStore ? `?storeId=${candidateStore.id}` : ""}`), enabled: selectedId !== null });
  const report = useQuery({ queryKey: ["network-reconciliation", selectedId, reportStoreId],
    queryFn: () => adminFetch<Reconciliation>(`store-networks/${selectedId}/stores/${reportStoreId}`), enabled: selectedId !== null && reportStoreId !== null });
  const post = (path: string, payload: object) => adminFetch<Network>(path, { method: "POST", headers: mutationHeaders(csrfToken, true), body: JSON.stringify({ ...payload, csrfToken }) });
  const mutation = useMutation({ mutationFn: async (action: () => Promise<unknown>) => action(),
    onSuccess: (result) => { const changed = result as Network | undefined; if (changed?.id) setSelectedId(changed.id);
      setNotice("Operação concluída e registrada no histórico."); setReason(""); setEvidence(""); setCandidateStore(null);
      void client.invalidateQueries({ queryKey: ["networks"] }); void client.invalidateQueries({ queryKey: ["network"] });
      void client.invalidateQueries({ queryKey: ["network-wallet-health"] }); void client.invalidateQueries({ queryKey: ["network-reconciliation"] }); },
    onError: () => setNotice("") });
  const run = (action: () => Promise<unknown>) => { mutation.reset(); mutation.mutate(action); };
  const selected = detail.data;
  const visible = networks.data?.items.filter((item) => item.name.toLocaleLowerCase("pt-BR").includes(networkSearch.toLocaleLowerCase("pt-BR"))) ?? [];
  return <div className="admin-page">
    <section className="admin-head"><div><h2>Central de redes</h2><p>Conecte filiais e gestores, acompanhe vínculos e confira a saúde das carteiras antes de compartilhar giftback.</p></div></section>
    {mutation.isError && <div className="admin-alert admin-alert-danger" role="alert">{mutation.error.message}</div>}
    {notice && <div className="admin-alert admin-alert-success" role="status">{notice}</div>}
    <section className="admin-grid admin-grid-2">
      <div className="admin-panel"><div className="admin-panel-head"><h3>Redes</h3></div>
        <input className="admin-input" aria-label="Pesquisar redes" placeholder="Pesquisar pelo nome" value={networkSearch} onChange={(event) => setNetworkSearch(event.target.value)} />
        {networks.isError && <p className="admin-alert admin-alert-danger">{networks.error.message}</p>}
        <div className="admin-network-list">{visible.map((item) => <button type="button" className={`admin-button ${selectedId === item.id ? "admin-button-primary" : ""}`} key={item.id} onClick={() => { setSelectedId(item.id); setCandidateStore(null); setReportStoreId(null); }}>
          {item.name} · {item.active_stores ?? 0} ativa(s){(item.suspended_stores ?? 0) > 0 ? ` · ${item.suspended_stores} suspensa(s)` : ""}</button>)}</div>
      </div>
      <form className="admin-panel admin-form" onSubmit={(event) => { event.preventDefault(); run(() => post("store-networks", { name: newName, reason })); }}>
        <div className="admin-panel-head"><h3>Criar rede</h3></div>
        <label className="admin-field"><span>Nome</span><input className="admin-input" required minLength={3} maxLength={160} value={newName} onChange={(event) => setNewName(event.target.value)} /></label>
        <label className="admin-field"><span>Motivo administrativo</span><input className="admin-input" required minLength={5} value={reason} onChange={(event) => setReason(event.target.value)} /></label>
        <button className="admin-button admin-button-primary" disabled={mutation.isPending}>Criar rede</button>
      </form>
    </section>
    {selectedId !== null && <>
      {detail.isError && <p className="admin-alert admin-alert-danger">{detail.error.message}</p>}
      {selected && <section className="admin-panel"><div className="admin-panel-head"><div><h3>{selected.name}</h3><p>Vínculos ativos compartilham saldo válido; CNPJ, assinatura e política de giftback permanecem por filial.</p></div></div>
        <div className="admin-actions"><span>Estado: {selected.status}</span><span>Filiais ativas: {selected.stores?.filter((item) => item.status === "active").length ?? 0}</span><span>Gestores: {selected.managers?.length ?? 0}</span></div>
      </section>}
      <section className="admin-panel admin-form"><div className="admin-panel-head"><div><h3>Conciliação das carteiras</h3><p>Inclui as filiais ativas e, se selecionada, a candidata. Casos ambíguos impedem a ativação.</p></div></div>
        {health.isLoading && <p>Verificando carteiras...</p>}{health.isError && <p className="admin-alert admin-alert-danger">{health.error.message}</p>}
        {health.data && <><div className={`admin-alert ${health.data.healthy ? "admin-alert-success" : "admin-alert-danger"}`}><strong>{health.data.healthy ? "Filiais conferidas" : `${health.data.caseCount} carteira(s) exigem revisão`}</strong>
          Filiais verificadas: {health.data.storeIds.join(", ") || "nenhuma"}. {health.data.truncated ? "Mostrando os primeiros 100 casos." : ""}</div>
          {!health.data.repairReady && <p className="admin-alert">A migração da auditoria de reparos ainda não foi instalada. O diagnóstico continua disponível, mas reparos estão bloqueados.</p>}
          {health.data.cases.length > 0 && <><div className="admin-table-wrap"><table className="admin-table"><thead><tr><th>Cliente / filial</th><th>Problema</th><th>Agregado</th><th>Créditos</th><th>Última movimentação</th><th>Ação</th></tr></thead><tbody>
            {health.data.cases.map((item) => <tr key={`${item.userId}-${item.storeId}`}><td>Cliente #{item.userId}<small>Filial #{item.storeId}</small></td><td>{item.issues.map(issueLabel).join(", ")}</td>
              <td>{item.walletCents === null ? "Ausente" : moneyFromCents(item.walletCents)}</td><td>{moneyFromCents(item.creditCents)}</td><td>{item.lastMovementCents === null ? "Sem registro" : moneyFromCents(item.lastMovementCents)}</td>
              <td>{item.canRepairMissingWallet ? <button className="admin-button" disabled={mutation.isPending || !health.data.repairReady || evidence.trim().length < 15} onClick={() => { if (confirm("A evidência confirma os créditos e a última movimentação desta carteira?")) run(() => adminFetch(`store-networks/${selectedId}/repair-wallet/${item.storeId}`, { method: "POST", headers: mutationHeaders(csrfToken, true), body: JSON.stringify({ userId: item.userId, expectedCents: item.creditCents, evidence, csrfToken }) })); }}>Restaurar agregado</button> : "Revisão manual"}</td></tr>)}
          </tbody></table></div><label className="admin-field"><span>Evidência para reparo determinístico</span><textarea className="admin-textarea" maxLength={1000} value={evidence} onChange={(event) => setEvidence(event.target.value)} placeholder="Explique a comprovação do saldo e da última movimentação (mínimo 15 caracteres)." /></label></>}
          {(health.data.repairEvents?.length ?? 0) > 0 && <div className="admin-table-wrap"><h4>Reparos auditados</h4><table className="admin-table"><thead><tr><th>Quando</th><th>Carteira</th><th>Responsável</th><th>Antes → depois</th><th>Evidência</th></tr></thead><tbody>{health.data.repairEvents?.map((event) => <tr key={event.id}><td>{new Date(event.occurred_at).toLocaleString("pt-BR")}</td><td>Cliente #{event.user_id} · filial #{event.store_id}</td><td>{event.actor_name}</td><td>Ausente → {repairAmount(event.after_json)}</td><td>{event.reason}</td></tr>)}</tbody></table></div>}
        </>}
      </section>
      {selected && <section className="admin-grid admin-grid-2">
        <div className="admin-panel admin-form"><div className="admin-panel-head"><div><h3>Filiais</h3><p>Busque por nome ou CNPJ; a entrada exige carteiras conferidas.</p></div></div>
          <form className="admin-actions" onSubmit={(event) => { event.preventDefault(); setStoreTerm(storeLookup.trim()); }}><input className="admin-input" aria-label="Buscar filial" placeholder="Nome ou CNPJ" minLength={2} value={storeLookup} onChange={(event) => setStoreLookup(event.target.value)} /><button className="admin-button">Buscar</button></form>
          {stores.data?.items.map((item) => <button key={item.id} className="admin-button" disabled={item.existing_network_id !== null} onClick={() => setCandidateStore(item)}>{item.name} · {item.cnpj}{item.existing_network_id !== null ? " · já vinculada" : ""}</button>)}
          {candidateStore && <div className="admin-alert"><strong>Candidata: {candidateStore.name}</strong>CNPJ {candidateStore.cnpj}. Confirme o diagnóstico acima antes de vincular.</div>}
          <label className="admin-field"><span>Motivo da alteração</span><input className="admin-input" minLength={5} value={reason} onChange={(event) => setReason(event.target.value)} /></label>
          {candidateStore && <button className="admin-button admin-button-primary" disabled={mutation.isPending || !health.data?.healthy || reason.trim().length < 5} onClick={() => run(() => post(`store-networks/${selectedId}/stores/${candidateStore.id}`, { action: "join", reason }))}>Vincular {candidateStore.name}</button>}
          <div className="admin-network-list">{selected.stores?.map((item) => <article key={item.store_id} className="admin-network-card"><strong>{item.store_name}</strong><small>{item.cnpj} · {item.status === "active" ? "Ativa" : "Suspensa"}</small>
            <div className="admin-actions"><button className="admin-button" onClick={() => setReportStoreId(item.store_id)}>Conciliação entre filiais</button>
              {item.status === "active" ? <button className="admin-button admin-button-danger" disabled={mutation.isPending || reason.trim().length < 5} onClick={() => run(() => post(`store-networks/${selectedId}/stores/${item.store_id}`, { action: "suspend", reason }))}>Suspender</button>
                : <><button className="admin-button" disabled={mutation.isPending || reason.trim().length < 5} onClick={() => run(() => post(`store-networks/${selectedId}/stores/${item.store_id}`, { action: "resume", reason }))}>Reativar</button>
                  <button className="admin-button admin-button-danger" disabled={mutation.isPending || reason.trim().length < 5 || reportStoreId !== item.store_id || !report.data} onClick={() => { if (confirm("Separar esta filial após revisar a conciliação? O histórico original será preservado.")) run(() => post(`store-networks/${selectedId}/stores/${item.store_id}`, { action: "detach", reason, reconciled: true })); }}>Separar</button></>}
            </div></article>)}</div>
          {report.data && <div className="admin-alert"><strong>Conciliação da filial #{report.data.storeId}</strong>{report.data.crossBranchUsage.length === 0 ? "Sem uso cruzado registrado." : report.data.crossBranchUsage.map((row) => <div key={`${row.origin_store_id}-${row.redemption_store_id}`}>Origem #{row.origin_store_id} → uso #{row.redemption_store_id}: {moneyFromCents(Number(row.net_used_cents))} em {row.sales} venda(s)</div>)}</div>}
        </div>
        <div className="admin-panel admin-form"><div className="admin-panel-head"><div><h3>Gestores da rede</h3><p>Somente administradores ativos da KlubeCash concedem este acesso.</p></div></div>
          <form className="admin-actions" onSubmit={(event) => { event.preventDefault(); setManagerTerm(managerLookup.trim()); }}><input className="admin-input" aria-label="Buscar gestor" placeholder="Nome ou e-mail" minLength={2} value={managerLookup} onChange={(event) => setManagerLookup(event.target.value)} /><button className="admin-button">Buscar</button></form>
          {managers.data?.items.map((item) => <div className="admin-network-card" key={item.id}><strong>{item.name}</strong><small>{item.email} · {item.account_type}</small><button className="admin-button" disabled={mutation.isPending || reason.trim().length < 5 || selected.managers?.some((manager) => manager.user_id === item.id)} onClick={() => run(() => post(`store-networks/${selectedId}/managers/${item.id}`, { grant: true, reason }))}>Conceder acesso</button></div>)}
          {selected.managers?.map((item) => <div className="admin-network-card" key={item.user_id}><strong>{item.nome}</strong><small>{item.email} · conta {item.account_status ?? "não verificada"}</small><button className="admin-button admin-button-danger" disabled={mutation.isPending || reason.trim().length < 5} onClick={() => run(() => post(`store-networks/${selectedId}/managers/${item.user_id}`, { grant: false, reason }))}>Revogar acesso</button></div>)}
          <p className="admin-alert">Use o campo “Motivo da alteração” ao lado para registrar concessões e revogações.</p>
        </div>
      </section>}
      {selected && <section className="admin-panel"><div className="admin-panel-head"><h3>Histórico da rede</h3></div><div className="admin-table-wrap"><table className="admin-table"><thead><tr><th>Quando</th><th>Ação</th><th>Filial</th><th>Responsável</th><th>Motivo</th></tr></thead><tbody>
        {selected.events?.map((event) => <tr key={event.id}><td>{new Date(event.occurred_at).toLocaleString("pt-BR")}</td><td>{event.action}</td><td>{event.store_id ?? "Rede"}</td><td>Admin #{event.actor_id}</td><td>{event.reason}</td></tr>)}
      </tbody></table></div></section>}
    </>}
  </div>;
}

function issueLabel(issue: string): string {
  return ({ missing_wallet: "Agregado ausente", missing_marker: "Migração incompleta", invalid_credit: "Crédito inconsistente", balance_mismatch: "Saldo divergente", movement_mismatch: "Movimentação divergente" } as Record<string, string>)[issue] ?? issue;
}

function repairAmount(value: string): string {
  try {
    const parsed = JSON.parse(value) as { walletCents?: number };
    return parsed.walletCents === undefined ? "valor indisponível" : moneyFromCents(parsed.walletCents);
  } catch { return "valor indisponível"; }
}
