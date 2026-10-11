"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { storeFetch } from "@/lib/client-api";
import { moneyFromCents } from "@/lib/format";
import { ErrorState, LoadingState } from "@/components/store/PageState";
import { useStoreContext, useStoreView } from "@/components/store/StoreProviders";

type Branch = { storeId: number; storeName: string; salesCount: number; grossAmountCents: number; balanceRedeemedCents: number; giftbackIssuedCents: number; cancelledCount: number };
type Performance = { sellerId: number | null; sellerName: string;
  summary: { salesCount: number; recordedSalesCount: number | null; grossAmountCents: number; outsideBalanceCents: number;
    balanceRedeemedCents: number; giftbackIssuedCents: number; averageTicketCents: number; customersCount: number; cancelledCount: number };
  branches: Branch[]; monthlySales: Array<{ month: string; salesCount: number; grossAmountCents: number }> };

export default function PersonPerformancePage() {
  const { id } = useParams<{ id: string }>();
  const context = useStoreContext();
  const view = useStoreView();
  const params = new URLSearchParams(view.query().replace(/^&/, ""));
  params.delete("sellerId");
  const query = useQuery({ queryKey: ["person-performance", context.store.id, id, view.branch, view.startDate, view.endDate],
    queryFn: () => storeFetch<Performance>(`reports/people/${encodeURIComponent(id)}&${params.toString()}`) });
  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState message={query.error?.message ?? "Não foi possível carregar o desempenho."} retry={() => query.refetch()} />;
  const data = query.data;
  const s = data.summary;
  return <div className="store-page store-stack">
    <section className="store-page-head"><div><h2>Desempenho: {data.sellerName}</h2><p>Vendas atribuídas a esta pessoa na visão e no período selecionados.</p></div>
      <Link className="store-button" href="/store/relatorios">Voltar aos relatórios</Link></section>
    <section className="store-grid store-grid-4">
      <Stat label="Vendas aprovadas" value={String(s.salesCount)} /><Stat label="Valor das vendas" value={moneyFromCents(s.grossAmountCents)} />
      <Stat label="Fora do saldo" value={moneyFromCents(s.outsideBalanceCents)} /><Stat label="Saldo utilizado" value={moneyFromCents(s.balanceRedeemedCents)} />
      <Stat label="Giftback concedido" value={moneyFromCents(s.giftbackIssuedCents)} /><Stat label="Ticket médio" value={moneyFromCents(s.averageTicketCents)} />
      <Stat label="Clientes únicos" value={String(s.customersCount)} /><Stat label="Cancelamentos" value={String(s.cancelledCount)} />
    </section>
    {s.recordedSalesCount !== null && <section className="store-panel"><h3>Vendeu × registrou</h3><p>{s.salesCount} venda(s) atribuída(s) como vendedor; {s.recordedSalesCount} venda(s) aprovada(s) registrada(s) por esta conta, inclusive para outros vendedores. São papéis diferentes; não somamos os dois números.</p></section>}
    <section className="store-panel"><h3>Por filial</h3><div className="store-table-wrap"><table className="store-table"><thead><tr><th>Filial</th><th>Vendas</th><th>Valor</th><th>Saldo usado</th><th>Giftback</th><th>Cancelamentos</th></tr></thead><tbody>
      {data.branches.map((branch) => <tr key={branch.storeId}><td>{branch.storeName}</td><td>{branch.salesCount}</td><td>{moneyFromCents(branch.grossAmountCents)}</td><td>{moneyFromCents(branch.balanceRedeemedCents)}</td><td>{moneyFromCents(branch.giftbackIssuedCents)}</td><td>{branch.cancelledCount}</td></tr>)}
    </tbody></table></div></section>
    <section className="store-panel"><h3>Evolução mensal</h3><div className="store-table-wrap"><table className="store-table"><thead><tr><th>Mês</th><th>Vendas</th><th>Valor</th></tr></thead><tbody>
      {data.monthlySales.map((month) => <tr key={month.month}><td>{month.month}</td><td>{month.salesCount}</td><td>{moneyFromCents(month.grossAmountCents)}</td></tr>)}
    </tbody></table></div>{!data.monthlySales.length && <p>Sem vendas aprovadas neste período.</p>}</section>
    <Link className="store-button store-button-primary" href="/store/transacoes" onClick={() => view.setView({ sellerId: id })}>Abrir vendas deste vendedor</Link>
    <small>“Fora do saldo” não confirma que o pagamento foi liquidado.</small>
  </div>;
}

function Stat({ label, value }: { label: string; value: string }) {
  return <div className="store-stat"><span className="store-stat-label">{label}</span><strong className="store-stat-value">{value}</strong></div>;
}
