"use client";

import { useQuery } from "@tanstack/react-query";
import { storeFetch } from "@/lib/client-api";
import { useStoreContext, useStoreView } from "./StoreProviders";

export function StoreViewBar({ showStatus = false }: { showStatus?: boolean }) {
  const context = useStoreContext();
  const view = useStoreView();
  const sellers = useQuery({
    queryKey: ["view-sellers", context.store.id, context.networkId, view.branch],
    queryFn: () => storeFetch<{ items: Array<{ id: number; name: string }> }>(`sellers${view.branch === "network" ? "&scope=network" : context.canViewNetwork && view.branch !== context.store.id ? `&scope=network&storeId=${view.branch}` : ""}`),
    enabled: context.user.subtype !== "vendedor",
  });
  return <section className="store-view-bar" aria-label="Visão dos dados">
    <div className="store-view-bar-heading"><strong>Visualizando</strong><span>Vendas sempre são registradas na filial ativa: {context.store.name}.</span></div>
    <label><span>Filial</span><select aria-label="Filial dos dados" value={view.branch} onChange={(event) => view.setView({ branch: event.target.value === "network" ? "network" : Number(event.target.value), sellerId: "" })}>
      {context.canViewNetwork && <option value="network">Rede toda</option>}
      {(context.canViewNetwork ? context.stores?.filter((store) => store.networkId === context.networkId) : [context.store])?.map((store) =>
        <option key={store.id} value={store.id}>{store.name}</option>)}
    </select></label>
    {context.user.subtype !== "vendedor" && <label><span>Vendedor</span><select aria-label="Vendedor dos dados" value={view.sellerId} onChange={(event) => view.setView({ sellerId: event.target.value })}>
      <option value="">Todos</option>{sellers.data?.items.map((seller) => <option key={seller.id} value={seller.id}>{seller.name}</option>)}
    </select></label>}
    <label><span>De</span><input aria-label="Data inicial dos dados" type="date" value={view.startDate} onChange={(event) => view.setView({ startDate: event.target.value })} /></label>
    <label><span>Até</span><input aria-label="Data final dos dados" type="date" value={view.endDate} onChange={(event) => view.setView({ endDate: event.target.value })} /></label>
    {showStatus && <label><span>Status</span><select aria-label="Status das vendas" value={view.status} onChange={(event) => view.setView({ status: event.target.value })}>
      <option value="">Todos</option><option value="aprovado">Aprovadas</option><option value="cancelado">Canceladas</option><option value="pendente">Pendentes</option>
    </select></label>}
  </section>;
}
