"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { Eye, Filter, Plus, X } from "lucide-react";
import { FormEvent, useEffect, useState } from "react";
import { storeFetch } from "@/lib/client-api";
import { dateTime, moneyFromCents, number } from "@/lib/format";
import {
  EmptyState,
  ErrorState,
  LoadingState,
} from "@/components/store/PageState";
import type { Pagination } from "@/types/store";
import { useStoreContext, useStoreView } from "@/components/store/StoreProviders";

type Transaction = {
  id: number;
  code: string;
  description: string;
  customerName: string;
  customerEmail: string;
  grossAmountCents: number;
  balanceUsedCents: number;
  paidAmountCents: number;
  cashbackGrantedCents: number;
  status: string;
  financialModel: "commission_legacy" | "subscription_cashback";
  occurredAt: string;
  storeName: string;
  sellerName: string;
  recordedByName: string;
  sourceChannel?: string;
  items?: Array<{ name: string; quantity: number; unitPriceCents: number; totalCents: number }>;
  attributionEvents?: Array<{ id: number; previousSellerId: number | null; newSellerId: number; occurredAt: string }>;
  giftbackMovements?: Array<{ id: number; type: string; amountCents: number; deltaCents: number; storeName: string; occurredAt: string }>;
  lifecycleEvents?: Array<{ id: number; type: string; occurredAt: string }>;
};
type TransactionsData = {
  dataState: "ready" | "empty";
  generatedAt: string;
  items: Transaction[];
  summary: {
    salesCount: number;
    grossAmountCents: number;
    cashbackGrantedCents: number;
    balanceUsedCents: number;
  };
  pagination: Pagination;
};

export default function TransactionsPage() {
  const context = useStoreContext();
  const view = useStoreView();
  const [page, setPage] = useState(1);
  const [filters, setFilters] = useState<Record<string, string>>({});
  const [draft, setDraft] = useState<Record<string, string>>({});
  const [filterOpen, setFilterOpen] = useState(false);
  const [detailId, setDetailId] = useState<number | null>(null);
  useEffect(() => {
    const frame = requestAnimationFrame(() => { setPage(1); setDetailId(null); });
    return () => cancelAnimationFrame(frame);
  }, [view.branch, view.sellerId, view.startDate, view.endDate, view.status]);
  const params = new URLSearchParams(view.query(true).replace(/^&/, ""));
  params.set("page", String(page));
  Object.entries(filters).forEach(([key, value]) => {
    if (!value) return;
    if (key === "minimum" || key === "maximum") {
      params.set(`${key}Cents`, String(Math.round(Number(value) * 100)));
    } else {
      params.set(key, value);
    }
  });
  const query = useQuery({
    queryKey: ["transactions", context.store.id, page, filters, view.branch, view.sellerId, view.startDate, view.endDate, view.status],
    queryFn: () =>
      storeFetch<TransactionsData>(
        `transactions&${params.toString().replaceAll("&", "&")}`,
      ),
  });
  const detail = useQuery({
    queryKey: ["transaction", context.store.id, detailId, view.branch],
    queryFn: () => storeFetch<Transaction>(`transactions/${detailId}${view.query()}`),
    enabled: detailId !== null,
  });

  function submit(event: FormEvent) {
    event.preventDefault();
    setPage(1);
    setFilters(Object.fromEntries(Object.entries(draft).filter(([, value]) => value)));
    setFilterOpen(false);
  }

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data)
    return (
      <ErrorState
        message={query.error?.message ?? "Erro inesperado."}
        retry={() => query.refetch()}
      />
    );
  const data = query.data;

  return (
    <div className="store-page store-stack">
      <section className="store-page-head">
        <div>
          <h2>Todas as suas vendas</h2>
          <p>
            Consulte o valor pago, saldo utilizado e giftback concedido em cada
            venda.
          </p>
        </div>
        <div className="store-head-actions">
          <a className="store-button" href={`/api/store/v2/transactions/export?${params.toString()}&format=sales`}>Exportar vendas</a>
          <a className="store-button" href={`/api/store/v2/transactions/export?${params.toString()}&format=items`}>Exportar itens</a>
          <button className="store-button" onClick={() => setFilterOpen(true)}>
            <Filter size={16} /> Filtros
          </button>
          <Link className="store-button store-button-primary" href="/store/registrar-transacao">
            <Plus size={17} /> Nova venda
          </Link>
        </div>
      </section>
      <section className="store-grid store-grid-4">
        <Stat label="Vendas" value={number(data.summary.salesCount)} />
        <Stat label="Valor movimentado" value={moneyFromCents(data.summary.grossAmountCents)} />
        <Stat label="Saldo utilizado" value={moneyFromCents(data.summary.balanceUsedCents)} />
        <Stat label="Giftback concedido" value={moneyFromCents(data.summary.cashbackGrantedCents)} />
      </section>
      <section className="store-panel">
        <div className="store-panel-head">
          <div>
            <h3>Histórico de vendas</h3>
            <p>{number(data.pagination.totalItems)} registro(s) encontrado(s)</p>
          </div>
        </div>
        {data.items.length ? (
          <><div className="store-mobile-only">
            {data.items.map((item) => <article className="store-customer-card" key={item.id}>
              <h4>{item.customerName} · {moneyFromCents(item.grossAmountCents)}</h4>
              <p>{item.storeName} · {dateTime(item.occurredAt)}</p>
              <p>Vendeu: {item.sellerName}<br />Registrou: {item.recordedByName}</p>
              <p>Saldo usado: {moneyFromCents(item.balanceUsedCents)} · Giftback: {moneyFromCents(item.cashbackGrantedCents)}</p>
              <button className="store-button" onClick={() => setDetailId(item.id)}>Ver venda completa</button>
            </article>)}
          </div><div className="store-table-wrap store-desktop-only">
            <table className="store-table">
              <thead>
                <tr>
                  <th>Cliente</th>
                  <th>Filial</th>
                  <th>Vendeu</th>
                  <th>Registrou</th>
                  <th>Código</th>
                  <th>Data</th>
                  <th>Valor</th>
                  <th>Saldo usado</th>
                  <th>Giftback</th>
                  <th>Status</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {data.items.map((item) => (
                  <tr key={item.id}>
                    <td><strong>{item.customerName}</strong><small>{item.customerEmail}</small></td>
                    <td>{item.storeName}</td>
                    <td>{item.sellerName}</td>
                    <td>{item.recordedByName}</td>
                    <td className="store-code">{item.code}</td>
                    <td>{dateTime(item.occurredAt)}</td>
                    <td><strong>{moneyFromCents(item.grossAmountCents)}</strong></td>
                    <td>{moneyFromCents(item.balanceUsedCents)}</td>
                    <td>{moneyFromCents(item.cashbackGrantedCents)}</td>
                    <td>
                      <span className={`store-status ${item.status}`}>{statusLabel(item)}</span>
                    </td>
                    <td>
                      <button
                        className="store-button store-icon-button"
                        aria-label="Ver detalhes"
                        onClick={() => setDetailId(item.id)}
                      >
                        <Eye size={16} />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div></>
        ) : (
          <EmptyState
            title="Nenhuma venda encontrada"
            message="A integração está ativa. Ajuste os filtros ou registre a primeira venda."
          />
        )}
        <PaginationBar pagination={data.pagination} setPage={setPage} />
      </section>

      {filterOpen && (
        <div className="store-modal-backdrop" role="dialog" aria-modal="true">
          <div className="store-modal">
            <div className="store-modal-head">
              <h3>Filtrar vendas</h3>
              <button className="store-button store-icon-button" onClick={() => setFilterOpen(false)}>
                <X size={17} />
              </button>
            </div>
            <form className="store-form" onSubmit={submit}>
              <div className="store-form-grid">
                <FilterField label="Cliente" value={draft.customer} onChange={(value) => setDraft({ ...draft, customer: value })} />
                <FilterField label="Valor mínimo" type="number" value={draft.minimum} onChange={(value) => setDraft({ ...draft, minimum: value })} />
                <FilterField label="Valor máximo" type="number" value={draft.maximum} onChange={(value) => setDraft({ ...draft, maximum: value })} />
              </div>
              <div className="store-form-actions">
                <button type="button" className="store-button" onClick={() => { setDraft({}); setFilters({}); setPage(1); setFilterOpen(false); }}>
                  Limpar
                </button>
                <button className="store-button store-button-primary">Aplicar filtros</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {detailId !== null && (
        <div className="store-modal-backdrop" role="dialog" aria-modal="true">
          <div className="store-modal">
            <div className="store-modal-head">
              <h3>Detalhes da venda</h3>
              <button className="store-button store-icon-button" onClick={() => setDetailId(null)}><X size={17} /></button>
            </div>
            {detail.isLoading && <LoadingState />}
            {detail.isError && <div className="store-alert store-alert-error">{detail.error.message}</div>}
            {detail.data && (
              <div className="store-summary-list">
                <Row label="Cliente" value={detail.data.customerName} />
                <Row label="Filial" value={detail.data.storeName} />
                <Row label="Vendedor" value={detail.data.sellerName} />
                <Row label="Registrado por" value={detail.data.recordedByName} />
                <Row label="Código" value={detail.data.code} />
                <Row label="Canal" value={({ manual: "Manual", csv: "Importação CSV", whatsapp: "WhatsApp" } as Record<string, string>)[detail.data.sourceChannel ?? ""] ?? "Registro legado"} />
                <Row label="Data" value={dateTime(detail.data.occurredAt)} />
                <Row label="Valor da venda" value={moneyFromCents(detail.data.grossAmountCents)} />
                <Row label="Saldo usado" value={moneyFromCents(detail.data.balanceUsedCents)} />
                <Row label="Valor fora do saldo" value={moneyFromCents(detail.data.paidAmountCents)} />
                <Row label="Giftback do cliente" value={moneyFromCents(detail.data.cashbackGrantedCents)} />
                <Row label="Status" value={statusLabel(detail.data)} />
                {detail.data.description && <Row label="Descrição" value={detail.data.description} />}
                <h4>Itens informados</h4>
                {detail.data.items?.length ? detail.data.items.map((item, index) =>
                  <Row key={index} label={`${item.quantity} × ${item.name} · ${moneyFromCents(item.unitPriceCents)} cada`} value={moneyFromCents(item.totalCents)} />)
                  : <p>Itens não informados nesta venda.</p>}
                {Boolean(detail.data.attributionEvents?.length) && <><h4>Correções registradas</h4>
                  {detail.data.attributionEvents?.map((event) => <p key={event.id}>Vendedor corrigido em {dateTime(event.occurredAt)}. Registro auditado pela KlubeCash.</p>)}</>}
                {Boolean(detail.data.lifecycleEvents?.length) && <><h4>Histórico da venda</h4>
                  {detail.data.lifecycleEvents?.map((event) => <p key={event.id}>{event.type === "transaction.reverse" ? "Estorno" : "Alteração de status legado"} em {dateTime(event.occurredAt)}.</p>)}</>}
                <h4>Movimentações de giftback relacionadas</h4>
                {detail.data.giftbackMovements?.length ? detail.data.giftbackMovements.map((event) =>
                  <Row key={event.id} label={`${event.type} · ${event.storeName} · ${dateTime(event.occurredAt)}`} value={`${event.deltaCents < 0 ? "−" : "+"}${moneyFromCents(Math.abs(event.deltaCents))}`} />)
                  : <p>Sem movimentações registradas para esta venda.</p>}
                <small>Valor fora do saldo = valor da venda menos giftback utilizado; não comprova a liquidação do pagamento.</small>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

function statusLabel(item: Transaction) {
  if (item.financialModel === "commission_legacy" && item.status === "pendente") return "Registro legado";
  return ({ aprovado: "Aprovado", cancelado: "Cancelado", pendente: "Pendente" } as Record<string, string>)[item.status] ?? item.status;
}
function Stat({ label, value }: { label: string; value: string }) {
  return <div className="store-stat"><span className="store-stat-label">{label}</span><strong className="store-stat-value">{value}</strong></div>;
}
function Row({ label, value }: { label: string; value: string }) {
  return <div className="store-summary-row"><span>{label}</span><strong>{value}</strong></div>;
}
function FilterField({ label, type = "text", value = "", onChange }: { label: string; type?: string; value?: string; onChange: (value: string) => void }) {
  return <label className="store-field"><span>{label}</span><input className="store-input" type={type} min={type === "number" ? "0" : undefined} step={type === "number" ? "0.01" : undefined} value={value} onChange={(event) => onChange(event.target.value)} /></label>;
}
function PaginationBar({ pagination, setPage }: { pagination: Pagination; setPage: (page: number) => void }) {
  if (pagination.totalPages <= 1) return null;
  return <div className="store-pagination"><button className="store-button" disabled={pagination.page <= 1} onClick={() => setPage(pagination.page - 1)}>Anterior</button><span>Página {pagination.page} de {pagination.totalPages}</span><button className="store-button" disabled={pagination.page >= pagination.totalPages} onClick={() => setPage(pagination.page + 1)}>Próxima</button></div>;
}
