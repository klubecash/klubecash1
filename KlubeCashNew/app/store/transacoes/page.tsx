"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { Eye, Filter, Plus, X } from "lucide-react";
import { FormEvent, useState } from "react";
import { storeFetch } from "@/lib/client-api";
import { dateTime, moneyFromCents, number } from "@/lib/format";
import {
  EmptyState,
  ErrorState,
  LoadingState,
} from "@/components/store/PageState";
import type { Pagination } from "@/types/store";

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
  const [page, setPage] = useState(1);
  const [filters, setFilters] = useState<Record<string, string>>({});
  const [draft, setDraft] = useState<Record<string, string>>({});
  const [filterOpen, setFilterOpen] = useState(false);
  const [detailId, setDetailId] = useState<number | null>(null);
  const params = new URLSearchParams({ page: String(page) });
  Object.entries(filters).forEach(([key, value]) => {
    if (!value) return;
    if (key === "minimum" || key === "maximum") {
      params.set(`${key}Cents`, String(Math.round(Number(value) * 100)));
    } else {
      params.set(key, value);
    }
  });
  const query = useQuery({
    queryKey: ["transactions", page, filters],
    queryFn: () =>
      storeFetch<TransactionsData>(
        `transactions&${params.toString().replaceAll("&", "&")}`,
      ),
  });
  const detail = useQuery({
    queryKey: ["transaction", detailId],
    queryFn: () => storeFetch<Transaction>(`transactions/${detailId}`),
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
            Consulte o valor pago, saldo utilizado e cashback concedido em cada
            venda.
          </p>
        </div>
        <div className="store-head-actions">
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
        <Stat label="Cashback concedido" value={moneyFromCents(data.summary.cashbackGrantedCents)} />
      </section>
      <section className="store-panel">
        <div className="store-panel-head">
          <div>
            <h3>Histórico de vendas</h3>
            <p>{number(data.pagination.totalItems)} registro(s) encontrado(s)</p>
          </div>
        </div>
        {data.items.length ? (
          <div className="store-table-wrap">
            <table className="store-table">
              <thead>
                <tr>
                  <th>Cliente</th>
                  <th>Código</th>
                  <th>Data</th>
                  <th>Valor</th>
                  <th>Saldo usado</th>
                  <th>Cashback</th>
                  <th>Status</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {data.items.map((item) => (
                  <tr key={item.id}>
                    <td><strong>{item.customerName}</strong><small>{item.customerEmail}</small></td>
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
          </div>
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
                <FilterField label="Data inicial" type="date" value={draft.startDate} onChange={(value) => setDraft({ ...draft, startDate: value })} />
                <FilterField label="Data final" type="date" value={draft.endDate} onChange={(value) => setDraft({ ...draft, endDate: value })} />
                <label className="store-field">
                  <span>Status</span>
                  <select className="store-select" value={draft.status ?? ""} onChange={(event) => setDraft({ ...draft, status: event.target.value })}>
                    <option value="">Todos</option>
                    <option value="aprovado">Aprovado</option>
                    <option value="pendente">Legado pendente</option>
                    <option value="cancelado">Cancelado</option>
                  </select>
                </label>
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
                <Row label="Código" value={detail.data.code} />
                <Row label="Data" value={dateTime(detail.data.occurredAt)} />
                <Row label="Valor da venda" value={moneyFromCents(detail.data.grossAmountCents)} />
                <Row label="Saldo usado" value={moneyFromCents(detail.data.balanceUsedCents)} />
                <Row label="Valor pago" value={moneyFromCents(detail.data.paidAmountCents)} />
                <Row label="Cashback do cliente" value={moneyFromCents(detail.data.cashbackGrantedCents)} />
                <Row label="Status" value={statusLabel(detail.data)} />
                {detail.data.description && <Row label="Descrição" value={detail.data.description} />}
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
