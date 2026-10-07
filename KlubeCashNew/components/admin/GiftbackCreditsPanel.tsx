"use client";

import { useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { adminFetch, mutationHeaders } from "@/lib/admin-client";
import { dateTime, moneyFromCents } from "@/lib/format";
import type { GiftbackCredit, GiftbackCreditEvent, GiftbackCustomer, PageData, StoreItem } from "@/types/admin";
import { useAdminContext } from "./AdminProviders";
import { EmptyState, ErrorState, LoadingState, Modal, PaginationBar } from "./AdminStates";

const statusLabels: Record<string, string> = {
  active: "Disponível", expired: "Expirado", exhausted: "Utilizado", revoked: "Estornado",
};
const eventLabels: Record<string, string> = {
  credit: "Crédito recebido", grant: "Crédito recebido", opening: "Saldo anterior preservado",
  usage: "Utilização", consume: "Utilização", debit: "Utilização", expiration: "Expiração",
  expire: "Expiração", extension: "Validade estendida", extend: "Validade estendida",
  restoration: "Expiração revertida", restore: "Expiração revertida", reversal: "Estorno",
  revoke: "Crédito estornado", refund: "Utilização devolvida",
  credito: "Crédito recebido", uso: "Utilização", expiracao: "Expiração", prorrogacao: "Validade estendida",
  reversao_expiracao: "Expiração revertida", estorno: "Utilização devolvida", revogacao: "Crédito estornado",
};

function calendarDate(value: string | null) {
  if (!value) return "Sem expiração";
  const [year, month, day] = value.slice(0, 10).split("-");
  return `${day}/${month}/${year}`;
}

function tomorrowInBrazil() {
  const today = new Intl.DateTimeFormat("en-CA", { timeZone: "America/Sao_Paulo", year: "numeric", month: "2-digit", day: "2-digit" }).format(new Date());
  return nextCalendarDay(today);
}

function nextCalendarDay(date: string) {
  return new Date(new Date(`${date}T12:00:00Z`).valueOf() + 86_400_000).toISOString().slice(0, 10);
}

type Action = { type: "extend" | "restore"; event?: GiftbackCreditEvent };

export function GiftbackCreditsPanel({ store, onClose }: { store: Pick<StoreItem, "id" | "name">; onClose: () => void }) {
  const context = useAdminContext();
  const client = useQueryClient();
  const [search, setSearch] = useState("");
  const [customerPage, setCustomerPage] = useState(1);
  const [customer, setCustomer] = useState<GiftbackCustomer | null>(null);
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState("");
  const [creditId, setCreditId] = useState<number | null>(null);
  const [action, setAction] = useState<Action | null>(null);
  const [validUntil, setValidUntil] = useState("");
  const [reason, setReason] = useState("");
  const [success, setSuccess] = useState("");
  const requestKey = useRef<{ signature: string; key: string } | null>(null);
  const customers = useQuery({
    queryKey: ["admin-giftback-customers", store.id, search, customerPage],
    queryFn: () => adminFetch<PageData<GiftbackCustomer>>(`stores/${store.id}/giftback-customers?${new URLSearchParams({ search, page: String(customerPage) })}`),
    enabled: !customer,
  });
  const credits = useQuery({
    queryKey: ["admin-giftback-credits", store.id, customer?.id, page, status],
    queryFn: () => adminFetch<PageData<GiftbackCredit>>(`stores/${store.id}/giftback-credits?${new URLSearchParams({ userId: String(customer?.id), page: String(page), status })}`),
    enabled: !!customer,
  });
  const detail = useQuery({
    queryKey: ["admin-giftback-credit", store.id, customer?.id, creditId],
    queryFn: () => adminFetch<{ item: GiftbackCredit }>(`giftback-credits/${creditId}?userId=${customer?.id}&storeId=${store.id}`),
    enabled: !!customer && creditId !== null,
  });
  const mutation = useMutation({
    mutationFn: async () => {
      if (!customer || !detail.data || !action) throw new Error("Selecione um crédito deste cliente.");
      const payload = {
        userId: customer.id, storeId: store.id, expectedVersion: detail.data.item.version,
        validUntil, reason: reason.trim(), ...(action.event ? { expirationEventId: action.event.id } : {}),
      };
      const signature = JSON.stringify({ creditId, action: action.type, ...payload });
      if (requestKey.current?.signature !== signature) requestKey.current = { signature, key: crypto.randomUUID() };
      return adminFetch(`giftback-credits/${creditId}/${action.type}`, {
        method: "POST", headers: { ...mutationHeaders(context.csrfToken), "x-idempotency-key": requestKey.current.key }, body: JSON.stringify(payload),
      });
    },
    onSuccess: async () => {
      setSuccess(action?.type === "restore" ? `A expiração deste crédito de ${customer?.name} foi revertida.` : `A validade deste crédito de ${customer?.name} foi estendida.`);
      setAction(null); setReason(""); setValidUntil(""); requestKey.current = null;
      await Promise.all([
        client.invalidateQueries({ queryKey: ["admin-giftback-credits", store.id, customer?.id] }),
        client.invalidateQueries({ queryKey: ["admin-giftback-credit", store.id, customer?.id, creditId] }),
        client.invalidateQueries({ queryKey: ["admin-audit"] }),
      ]);
    },
  });

  function beginAction(next: Action) {
    mutation.reset(); setAction(next); setReason(""); setValidUntil(""); setSuccess(""); requestKey.current = null;
  }
  function resetDetail() {
    setCreditId(null); setAction(null); setSuccess(""); mutation.reset(); requestKey.current = null;
  }
  const credit = detail.data?.item;
  const tomorrow = tomorrowInBrazil();
  const creditMinimum = credit?.validUntil && action?.type === "extend" ? nextCalendarDay(credit.validUntil) : credit && credit.remainingCents > 0 ? credit.validUntil : null;
  const minimumDate = creditMinimum && creditMinimum > tomorrow ? creditMinimum : tomorrow;

  return (
    <Modal title={`Giftback · ${store.name}`} subtitle="Selecione o cliente e o crédito para consultar a validade ou reverter uma expiração individual." onClose={() => { if (!mutation.isPending) onClose(); }}>
      {!customer ? <>
        <label className="admin-field"><span>Pesquisar cliente da loja</span><input className="admin-input" value={search} onChange={(e) => { setSearch(e.target.value); setCustomerPage(1); }} placeholder="Nome, e-mail ou código do cliente" /></label>
        {customers.isLoading ? <LoadingState label="Carregando clientes..." /> : customers.isError ? <ErrorState error={customers.error} retry={() => customers.refetch()} /> : customers.data?.items.length ? <>
          <div className="admin-table-wrap"><table className="admin-table"><thead><tr><th>Cliente</th><th>Ação</th></tr></thead><tbody>{customers.data.items.map((item) => <tr key={item.id}><td><strong>{item.name}</strong><small>#{item.id} · {item.email}</small></td><td><button className="admin-button" onClick={() => { setCustomer(item); setPage(1); setStatus(""); }}>Ver créditos de {item.name}</button></td></tr>)}</tbody></table></div>
          <PaginationBar value={customers.data.pagination} onPage={setCustomerPage} />
        </> : <EmptyState title="Nenhum cliente com carteira nesta loja" message="Pesquise outro nome ou aguarde um crédito nesta loja." />}
      </> : <>
        <div className="admin-detail"><span>Cliente selecionado</span><strong>{customer.name}</strong><small>#{customer.id} · {customer.email}</small></div>
        <div className="admin-actions"><button className="admin-button" disabled={mutation.isPending} onClick={() => { setCustomer(null); resetDetail(); }}>Trocar cliente</button>{creditId !== null && <button className="admin-button" disabled={mutation.isPending} onClick={resetDetail}>Voltar aos créditos</button>}</div>
        {success && <p role="status" className="admin-success-text">{success}</p>}
        {creditId === null ? <>
          <label className="admin-field"><span>Status do crédito</span><select className="admin-select" value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }}><option value="">Todos</option>{Object.entries(statusLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>
          {credits.isLoading ? <LoadingState label="Carregando créditos..." /> : credits.isError ? <ErrorState error={credits.error} retry={() => credits.refetch()} /> : credits.data?.items.length ? <>
            <div className="admin-table-wrap"><table className="admin-table"><thead><tr><th>Crédito</th><th>Valores</th><th>Validade</th><th>Ação</th></tr></thead><tbody>{credits.data.items.map((item) => <tr key={item.id}><td><strong>#{item.id}</strong><small>{dateTime(item.creditedAt)}</small><small>{statusLabels[item.status] ?? item.status}</small></td><td><strong>{moneyFromCents(item.remainingCents)} disponível</strong><small>{moneyFromCents(item.originalCents)} recebido</small><small>{moneyFromCents(item.expiredCents)} expirado</small></td><td>{calendarDate(item.validUntil)}{item.validUntil && <small>Válido até o fim do dia</small>}</td><td><button className="admin-button" onClick={() => { resetDetail(); setCreditId(item.id); }}>Detalhar crédito #{item.id}</button></td></tr>)}</tbody></table></div>
            <PaginationBar value={credits.data.pagination} onPage={setPage} />
          </> : <EmptyState title="Nenhum crédito encontrado" message="Este cliente não possui créditos neste filtro." />}
        </> : detail.isLoading ? <LoadingState label="Carregando histórico do crédito..." /> : detail.isError ? <ErrorState error={detail.error} retry={() => detail.refetch()} /> : credit && <>
          <h4>Crédito #{credit.id} de {customer.name}</h4>
          <div className="admin-grid admin-grid-2"><div className="admin-detail"><span>Recebido em {dateTime(credit.creditedAt)}</span><strong>{moneyFromCents(credit.originalCents)}</strong><small>Disponível: {moneyFromCents(credit.remainingCents)}</small><small>Utilizado: {moneyFromCents(credit.consumedCents)} · Expirado: {moneyFromCents(credit.expiredCents)}</small></div><div className="admin-detail"><span>Validade deste crédito</span><strong>{calendarDate(credit.validUntil)}</strong>{credit.validUntil && <small>Até 23h59 em Brasília</small>}<small>{statusLabels[credit.status] ?? credit.status}</small></div></div>
          {!action && credit.status === "active" && credit.remainingCents > 0 && credit.validUntil && <button className="admin-button" onClick={() => beginAction({ type: "extend" })}>Estender validade deste crédito</button>}
          {action && <form className="admin-form" onSubmit={(e) => { e.preventDefault(); mutation.mutate(); }}>
            <p>{action.type === "restore" ? `Reverter ${moneyFromCents(action.event?.reversibleCents)} da expiração #${action.event?.id}, somente para ${customer.name}, nesta loja.` : `Estender a validade do saldo restante deste crédito de ${customer.name}.`}</p>
            <div className="admin-field"><label htmlFor="giftback-valid-until">Nova data de validade</label><input id="giftback-valid-until" aria-describedby="giftback-valid-until-help" className="admin-input" type="date" min={minimumDate} required value={validUntil} disabled={mutation.isPending} onChange={(e) => setValidUntil(e.target.value)} /><small id="giftback-valid-until-help">Escolha uma data futura. O crédito poderá ser usado até o fim desse dia, horário de Brasília.</small></div>
            <label className="admin-field"><span>Motivo da alteração</span><textarea className="admin-textarea" required minLength={5} maxLength={500} value={reason} disabled={mutation.isPending} onChange={(e) => setReason(e.target.value)} /></label>
            {mutation.isError && <p role="alert" className="admin-error-text">{mutation.error.message}</p>}
            <div className="admin-actions"><button type="button" className="admin-button" disabled={mutation.isPending} onClick={() => { setAction(null); mutation.reset(); }}>Cancelar alteração</button><button className="admin-button admin-button-primary" disabled={mutation.isPending || !validUntil || reason.trim().length < 5}>{mutation.isPending ? "Salvando..." : action.type === "restore" ? "Confirmar reversão individual" : "Confirmar extensão"}</button></div>
          </form>}
          <h4>Histórico deste crédito</h4>
          <div className="admin-table-wrap"><table className="admin-table"><thead><tr><th>Evento</th><th>Valor e validade</th><th>Responsável / motivo</th><th>Ação</th></tr></thead><tbody>{(credit.events ?? []).map((event) => <tr key={event.id}><td><strong>{eventLabels[event.type] ?? event.type} #{event.id}</strong><small>{dateTime(event.occurredAt)}</small></td><td>{moneyFromCents(event.amountCents)}<small>Saldo: {moneyFromCents(event.previousCents)} → {moneyFromCents(event.currentCents)}</small>{event.oldValidUntil !== event.newValidUntil && <small>{calendarDate(event.oldValidUntil)} → {calendarDate(event.newValidUntil)}</small>}</td><td>{event.actorName ?? "Sistema"}<small>{event.reason}</small></td><td>{event.reversibleCents > 0 && <button className="admin-button" disabled={mutation.isPending || !!action} onClick={() => beginAction({ type: "restore", event })}>Reverter expiração #{event.id}</button>}</td></tr>)}</tbody></table></div>
          {!credit.events?.length && <p>Sem eventos adicionais para este crédito.</p>}
        </>}
      </>}
    </Modal>
  );
}
