"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { storeFetch } from "@/lib/client-api";
import { moneyFromCents } from "@/lib/format";
import { useStoreContext, useStoreView } from "@/components/store/StoreProviders";
import { LoadingState, ErrorState } from "@/components/store/PageState";

type SellerRow = { storeId: number; storeName: string; sellerId: number | null; sellerName: string;
  salesCount: number; grossAmountCents: number; giftbackIssuedCents: number; balanceRedeemedCents: number; cancelledCount: number };
type PersonRow = { sellerId: number | null; sellerName: string; salesCount: number; grossAmountCents: number; giftbackIssuedCents: number; balanceRedeemedCents: number; cancelledCount: number };
type GiftbackReport = { issuedByOrigin: Array<{ loja_id: number; store_name: string; issued_cents: number; remaining_cents: number; expired_cents: number }>;
  usageByOriginAndRedemption: Array<{ origin_store_id: number; origin_store_name: string; redemption_store_id: number; redemption_store_name: string; used_cents: number }> };

export default function StoreReportsPage() {
  const context = useStoreContext();
  const view = useStoreView();
  const scope = view.query();
  const sellers = useQuery({ queryKey: ["seller-report", context.store.id, view.branch, view.sellerId, view.startDate, view.endDate],
    queryFn: () => storeFetch<{ items: SellerRow[]; people: PersonRow[] }>(`reports/sellers${scope}`) });
  const giftback = useQuery({ queryKey: ["giftback-report", context.store.id, view.branch],
    queryFn: () => storeFetch<GiftbackReport>(`reports/giftback${scope}`),
    enabled: context.user.subtype !== "vendedor" });
  if (sellers.isLoading) return <LoadingState />;
  if (sellers.isError) return <ErrorState message={sellers.error.message} retry={() => sellers.refetch()} />;
  return <div className="store-page store-stack">
    <section className="store-page-head"><div><h2>Métricas por filial e vendedor</h2>
      <p>Vendas aprovadas atribuídas a quem vendeu; quem registrou aparece nas transações. O saldo de giftback abaixo é atual, não limitado pelo período.</p></div></section>
    <section className="store-panel"><h3>Desempenho consolidado por pessoa</h3><p>Uma pessoa pode vender em mais de uma filial, sem duplicar suas vendas.</p><div className="store-table-wrap"><table className="store-table">
      <thead><tr><th>Vendedor</th><th>Vendas</th><th>Valor</th><th>Fora do saldo</th><th>Cancelamentos</th><th></th></tr></thead><tbody>
      {sellers.data?.people.map((person) => <tr key={person.sellerId ?? "unknown"}><td>{person.sellerName}</td><td>{person.salesCount}</td>
        <td>{moneyFromCents(person.grossAmountCents)}</td><td>{moneyFromCents(person.grossAmountCents - person.balanceRedeemedCents)}</td><td>{person.cancelledCount}</td>
        <td><Link className="store-link" href={`/store/desempenho/${person.sellerId ?? "unknown"}`}>Ver desempenho</Link></td></tr>)}
      </tbody></table></div></section>
    <section className="store-panel"><h3>Desempenho dos vendedores</h3><div className="store-table-wrap"><table className="store-table">
      <thead><tr><th>Filial</th><th>Vendedor</th><th>Vendas</th><th>Valor</th><th>Giftback emitido</th><th>Saldo utilizado</th></tr></thead>
      <tbody>{sellers.data?.items.map((row) => <tr key={`${row.storeId}-${row.sellerId ?? "unknown"}`}>
        <td>{row.storeName}</td><td>{row.sellerName}</td><td>{row.salesCount}</td><td>{moneyFromCents(row.grossAmountCents)}</td>
        <td>{moneyFromCents(row.giftbackIssuedCents)}</td><td>{moneyFromCents(row.balanceRedeemedCents)}</td>
      </tr>)}</tbody></table></div>{!sellers.data?.items.length && <p>Nenhuma venda aprovada neste escopo.</p>}</section>
    {context.user.subtype !== "vendedor" && <section className="store-panel"><h3>Giftback por origem e utilização</h3>
      {giftback.isError && <p className="store-alert store-alert-error">{giftback.error.message}</p>}
      <div className="store-table-wrap"><table className="store-table"><thead><tr><th>Filial de origem</th><th>Emitido</th><th>Disponível</th><th>Expirado</th></tr></thead>
        <tbody>{giftback.data?.issuedByOrigin.map((row) => <tr key={row.loja_id}><td>{row.store_name}</td>
          <td>{moneyFromCents(Number(row.issued_cents))}</td><td>{moneyFromCents(Number(row.remaining_cents))}</td>
          <td>{moneyFromCents(Number(row.expired_cents))}</td></tr>)}</tbody></table></div>
      <h4>Uso entre filiais</h4><div className="store-table-wrap"><table className="store-table"><thead><tr><th>Origem</th><th>Utilização</th><th>Valor líquido</th></tr></thead>
        <tbody>{giftback.data?.usageByOriginAndRedemption.map((row) => <tr key={`${row.origin_store_id}-${row.redemption_store_id}`}>
          <td>{row.origin_store_name}</td><td>{row.redemption_store_name}</td><td>{moneyFromCents(Number(row.used_cents))}</td>
        </tr>)}</tbody></table></div></section>}
  </div>;
}
