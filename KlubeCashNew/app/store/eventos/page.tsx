"use client";

import { useQuery } from "@tanstack/react-query";
import { storeFetch } from "@/lib/client-api";
import { moneyFromCents } from "@/lib/format";
import { useStoreContext } from "@/components/store/StoreProviders";
import { LoadingState, ErrorState } from "@/components/store/PageState";

type PassReport = {
  leftoverCreditedCents: number; leftoverReversedCents: number; usedAtEventsCents: number; giftbackOnCashCents: number;
  customers: number; openLeftoverCents: number; consumedLeftoverCents: number; expiredLeftoverCents: number;
  events: Array<{ name: string; credits: number; customers: number; totalCents: number; firstAt: string; lastAt: string }>;
  recent: Array<{ id: number; type: string; amountCents: number; occurredAt: string; customerName: string }>;
};

const date = (value: string) => new Date(value.replace(" ", "T")).toLocaleString("pt-BR", { dateStyle: "short", timeStyle: "short" });

export default function StorePassEventsPage() {
  const context = useStoreContext();
  const report = useQuery({ queryKey: ["pass-report", context.store.id], queryFn: () => storeFetch<PassReport>("reports/pass"),
    enabled: Boolean(context.store.eventPromoter) });
  if (!context.store.eventPromoter) return <ErrorState message="Esta área é só para promotores de eventos do Klube Pass." />;
  if (report.isLoading) return <LoadingState />;
  if (report.isError) return <ErrorState message={report.error.message} retry={() => report.refetch()} />;
  const data = report.data;
  if (!data) return null;
  return <div className="store-page store-stack">
    <section className="store-page-head"><div><h2>Sobras dos eventos</h2>
      <p>Quando um evento fecha, o crédito que sobrou na carteira do participante pode virar saldo KlubeCash nesta loja. Esse saldo não gera reembolso: o dinheiro já está com você. A parte paga com saldo nunca gera giftback.</p></div></section>
    <section className="store-panel"><h3>Resumo</h3><div className="store-table-wrap"><table className="store-table"><tbody>
      <tr><th>Sobras creditadas</th><td>{moneyFromCents(data.leftoverCreditedCents - data.leftoverReversedCents)}</td></tr>
      <tr><th>Saldo em aberto (ainda pode ser usado)</th><td>{moneyFromCents(data.openLeftoverCents)}</td></tr>
      <tr><th>Saldo usado em recargas de eventos</th><td>{moneyFromCents(data.usedAtEventsCents)}</td></tr>
      <tr><th>Sobras vencidas</th><td>{moneyFromCents(data.expiredLeftoverCents)}</td></tr>
      <tr><th>Giftback dado sobre dinheiro ou Pix</th><td>{moneyFromCents(data.giftbackOnCashCents)}</td></tr>
      <tr><th>Clientes com sobra</th><td>{data.customers}</td></tr>
    </tbody></table></div></section>
    <section className="store-panel"><h3>Por evento</h3><div className="store-table-wrap"><table className="store-table">
      <thead><tr><th>Evento</th><th>Clientes</th><th>Créditos</th><th>Total</th><th>Último crédito</th></tr></thead>
      <tbody>{data.events.map((event) => <tr key={event.name}><td>{event.name}</td><td>{event.customers}</td><td>{event.credits}</td>
        <td>{moneyFromCents(event.totalCents)}</td><td>{date(event.lastAt)}</td></tr>)}</tbody></table></div>
      {!data.events.length && <p>Nenhuma sobra recebida ainda.</p>}</section>
    <section className="store-panel"><h3>Últimas operações</h3><div className="store-table-wrap"><table className="store-table">
      <thead><tr><th>Quando</th><th>Cliente</th><th>Operação</th><th>Valor</th></tr></thead>
      <tbody>{data.recent.map((item) => <tr key={item.id}><td>{date(item.occurredAt)}</td><td>{item.customerName}</td>
        <td>{item.type}</td><td>{moneyFromCents(item.amountCents)}</td></tr>)}</tbody></table></div>
      {!data.recent.length && <p>Sem operações do Klube Pass.</p>}</section>
  </div>;
}
