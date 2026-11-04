"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  CheckCircle2,
  ChevronDown,
  Info,
  Minus,
  Plus,
  Search,
  ShieldAlert,
  Trash2,
  UserPlus,
} from "lucide-react";
import Link from "next/link";
import { useEffect, useId, useRef, useState } from "react";
import { storeFetch } from "@/lib/client-api";
import { moneyFromCents } from "@/lib/format";
import { useStoreContext } from "@/components/store/StoreProviders";
import styles from "./sale.module.css";

type Customer = {
  id: number;
  name: string;
  email: string | null;
  phone: string;
  cpf: string | null;
  needsCpf: boolean;
  type: "visitor" | "registered";
  createdByThisStore: boolean;
  balanceCents: number;
  purchaseCount: number;
  spentAmountCents: number;
};
type CustomerSearch = {
  dataState: "ready" | "empty";
  customer: Customer | null;
  canCreateVisitor: boolean;
  suggestedPhone: string;
};
type SaleResult = {
  id: number;
  status: "approved";
  grossAmountCents: number;
  paidAmountCents: number;
  balanceUsedCents: number;
  cashbackGrantedCents: number;
  customerBalanceCents: number;
  replayed: boolean;
};
type SaleItem = { name: string; quantity: string; unitPrice: string };

const MIN_SALE_CENTS = 500;
const pad = (value: number) => String(value).padStart(2, "0");
const saleCode = () => `VENDA-${Date.now().toString(36).toUpperCase()}`;
/** Valor de <input type="datetime-local">: horário local, e não UTC. */
const saleDate = () => {
  const now = new Date();
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
};
const cents = (value: number) => Math.round(value * 100);
/** Aceita só dígitos e um separador decimal (vírgula ou ponto), com até duas casas. */
const cleanMoney = (value: string) => {
  const raw = value.replace(/[^\d.,]/g, "");
  const at = raw.search(/[.,]/);
  if (at < 0) return raw;
  return `${raw.slice(0, at)}${raw[at]}${raw.slice(at + 1).replace(/[.,]/g, "").slice(0, 2)}`;
};
const parseMoney = (value: string) => {
  const parsed = Number(value.replace(",", "."));
  return Number.isFinite(parsed) ? parsed : 0;
};
const formatInput = (value: number) => (value > 0 ? value.toFixed(2).replace(".", ",") : "");
const initials = (name: string) =>
  name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join("") || "?";
const firstName = (name: string) => name.trim().split(/\s+/)[0] ?? "";

export default function RegisterTransactionPage() {
  const context = useStoreContext();
  const queryClient = useQueryClient();
  const idempotencyKey = useRef<string | null>(null);
  const amountRef = useRef<HTMLInputElement>(null);
  const confirmRef = useRef<HTMLButtonElement>(null);
  const modalTitle = useId();

  const [search, setSearch] = useState("");
  const [customer, setCustomer] = useState<Customer | null>(null);
  const [notice, setNotice] = useState("");
  const [canCreate, setCanCreate] = useState(false);
  const [visitor, setVisitor] = useState({ name: "", phone: "" });
  const [amountText, setAmountText] = useState("");
  const [balanceText, setBalanceText] = useState("");
  const [description, setDescription] = useState("");
  const [code, setCode] = useState(saleCode);
  const [date, setDate] = useState(saleDate);
  const [sellerId, setSellerId] = useState<number | null>(null);
  const [items, setItems] = useState<SaleItem[]>([]);
  const [moreOpen, setMoreOpen] = useState(false);
  const [reviewing, setReviewing] = useState(false);
  const [result, setResult] = useState<SaleResult | null>(null);
  const [soldTo, setSoldTo] = useState("");

  const sellers = useQuery({
    queryKey: ["store-sellers", context.store.id],
    queryFn: () => storeFetch<{ items: Array<{ id: number; name: string; role: string }> }>("sellers"),
    enabled: Boolean(context.permissions.assignSeller),
  });

  // Valores da venda. Com itens, o total é a soma deles.
  const itemsInvalid = items.some(
    (item) =>
      !item.name.trim() ||
      !Number.isInteger(Number(item.quantity)) ||
      Number(item.quantity) < 1 ||
      cents(parseMoney(item.unitPrice)) < 1,
  );
  const itemsTotalCents = items.reduce(
    (sum, item) => sum + Number(item.quantity || 0) * cents(parseMoney(item.unitPrice)),
    0,
  );
  const totalCents = items.length ? itemsTotalCents : cents(parseMoney(amountText));
  const balanceLimitCents = Math.min(totalCents, customer?.balanceCents ?? 0);
  const balanceCents = Math.min(cents(parseMoney(balanceText)), balanceLimitCents);
  const paidCents = Math.max(0, totalCents - balanceCents);
  // Mesma regra de PassStorePolicy::saleGiftbackCents no servidor.
  const giftbackApplies = context.store.eventPromoter ? Boolean(context.store.giftbackOnCash) : true;
  const cashbackCents = giftbackApplies
    ? Math.round((paidCents * context.store.customerCashbackPercentage) / 100)
    : 0;

  const blockReason = !customer
    ? "Busque o cliente pelo celular para continuar."
    : totalCents < MIN_SALE_CENTS
        ? "Informe o valor da venda (mínimo R$ 5,00)."
        : code.trim().length < 3
          ? "Abra “Mais detalhes” e informe o código da venda."
          : !date
            ? "Abra “Mais detalhes” e informe a data da venda."
            : itemsInvalid
              ? "Revise os itens: nome, quantidade inteira e preço."
              : "";

  useEffect(() => {
    if (customer) amountRef.current?.focus();
  }, [customer]);

  useEffect(() => {
    if (!reviewing) return;
    confirmRef.current?.focus();
    const close = (event: KeyboardEvent) => {
      if (event.key === "Escape") setReviewing(false);
    };
    window.addEventListener("keydown", close);
    return () => window.removeEventListener("keydown", close);
  }, [reviewing]);

  // A venda começa pela busca do cliente por celular, e-mail ou CPF; sem cadastro, cria-se o visitante.
  const searchMutation = useMutation({
    mutationFn: () => storeFetch<CustomerSearch>(`customers/search&query=${encodeURIComponent(search.trim())}`),
    onSuccess: (data) => {
      setCustomer(data.customer);
      setCanCreate(data.canCreateVisitor);
      setVisitor((current) => ({ ...current, phone: data.suggestedPhone || current.phone }));
      setNotice(data.customer ? "" : "Cliente não encontrado. Cadastre-o como visitante abaixo.");
    },
    onError: (error) => {
      setCustomer(null);
      setCanCreate(false);
      setNotice(error.message);
    },
  });

  const visitorMutation = useMutation({
    mutationFn: () =>
      storeFetch<{ customer: Customer }>("customers/visitor", {
        method: "POST",
        headers: { "X-CSRF-Token": context.csrfToken },
        body: JSON.stringify({
          name: visitor.name,
          phone: visitor.phone,
          csrfToken: context.csrfToken,
        }),
      }),
    onSuccess: (data) => {
      setCustomer(data.customer);
      setCanCreate(false);
      setNotice("");
    },
  });

  const saleMutation = useMutation({
    mutationFn: () => {
      idempotencyKey.current ??= crypto.randomUUID();
      return storeFetch<SaleResult>("transactions", {
        method: "POST",
        headers: {
          "X-CSRF-Token": context.csrfToken,
          "X-Idempotency-Key": idempotencyKey.current,
        },
        body: JSON.stringify({
          customerId: customer?.id,
          grossAmountCents: totalCents,
          balanceUsedCents: balanceCents,
          code: code.trim(),
          occurredAt: new Date(date).toISOString(),
          description,
          items: items.map((item) => ({
            name: item.name.trim(),
            quantity: Number(item.quantity),
            unitPriceCents: cents(parseMoney(item.unitPrice)),
          })),
          sellerId: context.permissions.assignSeller ? (sellerId ?? context.user.id) : context.user.id,
          csrfToken: context.csrfToken,
        }),
      });
    },
    onSuccess: (data) => {
      setSoldTo(customer?.name ?? "");
      setResult(data);
      setReviewing(false);
      queryClient.invalidateQueries({ queryKey: ["store-dashboard"] });
      queryClient.invalidateQueries({ queryKey: ["transactions"] });
    },
    onError: () => setReviewing(false),
  });

  const changeSearch = (value: string) => {
    setSearch(value);
    setCustomer(null);
    setCanCreate(false);
    setNotice("");
    setBalanceText("");
    visitorMutation.reset();
    saleMutation.reset();
  };

  const reset = () => {
    setSearch("");
    setCustomer(null);
    setNotice("");
    setCanCreate(false);
    setVisitor({ name: "", phone: "" });
    setAmountText("");
    setBalanceText("");
    setDescription("");
    setCode(saleCode());
    setDate(saleDate());
    setSellerId(null);
    setItems([]);
    setMoreOpen(false);
    setResult(null);
    setSoldTo("");
    idempotencyKey.current = null;
    visitorMutation.reset();
    saleMutation.reset();
  };

  const updateItem = (index: number, change: Partial<SaleItem>) =>
    setItems((current) => current.map((line, position) => (position === index ? { ...line, ...change } : line)));

  if (context.subscription.salesBlocked) {
    return (
      <div className={styles.page}>
        <section className={`${styles.card} ${styles.blocked}`}>
          <ShieldAlert size={52} style={{ color: "var(--store-warning, #d97706)" }} />
          <h2>Novas vendas estão pausadas</h2>
          <p>
            O histórico e os clientes continuam disponíveis. Regularize ou reative o plano para voltar a registrar vendas.
          </p>
          <Link className={`${styles.btn} ${styles.btnPrimary}`} href="/store/meu-plano">
            Ver plano e faturas
          </Link>
        </section>
      </div>
    );
  }

  if (result) {
    return (
      <div className={styles.page}>
        <section className={`${styles.card} ${styles.done}`}>
          <CheckCircle2 size={64} className={styles.doneIcon} aria-hidden="true" />
          <h3>Venda registrada!</h3>
          <p>
            {soldTo ? `${firstName(soldTo)} comprou ${moneyFromCents(result.grossAmountCents)}` : "A venda foi registrada"}
            {result.balanceUsedCents > 0 ? ` e usou ${moneyFromCents(result.balanceUsedCents)} de saldo` : ""}.
            {result.replayed ? " Recuperamos a confirmação com segurança, sem duplicar a venda." : ""}
          </p>
          <div className={styles.doneGift}>
            <span>Giftback creditado</span>
            <strong>{moneyFromCents(result.cashbackGrantedCents)}</strong>
          </div>
          <div className={styles.doneActions}>
            <button type="button" className={`${styles.btn} ${styles.btnPrimary}`} onClick={reset}>
              <Plus size={18} aria-hidden="true" /> Registrar outra venda
            </button>
            <Link className={styles.btn} href="/store/transacoes">
              Ver vendas
            </Link>
          </div>
        </section>
      </div>
    );
  }

  const multiBranch = (context.stores?.length ?? 0) > 1;

  return (
    <div className={styles.page}>
      <section className={styles.head}>
        <h2>Nova venda</h2>
        <p>Registre a compra e o cliente recebe o giftback na hora.</p>
        <div className={styles.chips}>
          <span className={styles.chip}>
            Filial: <strong>{context.store.name}</strong>
          </span>
          <span className={styles.chip}>
            Por: <strong>{context.user.name}</strong>
          </span>
        </div>
        {multiBranch && (
          <p className={styles.help} style={{ marginTop: 8 }}>
            Esta venda entra na filial acima. Para trocar, use o seletor de filial no menu.
          </p>
        )}
      </section>

      <div className={styles.layout}>
        <div className={styles.main}>
          {/* 1. Cliente */}
          <section className={styles.card} aria-labelledby="cliente-titulo">
            <div className={styles.cardHead}>
              <span className={`${styles.badge} ${customer ? styles.badgeDone : ""}`} aria-hidden="true">
                {customer ? "✓" : "1"}
              </span>
              <div>
                <h3 id="cliente-titulo">Quem está comprando?</h3>
                <p>Digite o celular do cliente. Também vale o e-mail ou o CPF.</p>
              </div>
            </div>

            <div className={styles.field}>
              <label htmlFor="cliente-busca">Celular, e-mail ou CPF</label>
              <div className={styles.row}>
                <input
                  id="cliente-busca"
                  className={styles.input}
                  type="text"
                  autoComplete="off"
                  autoCapitalize="off"
                  autoFocus
                  placeholder="(34) 99999-9999"
                  value={search}
                  onChange={(event) => changeSearch(event.target.value)}
                  onKeyDown={(event) => {
                    if (event.key === "Enter" && search.trim().length >= 3 && !searchMutation.isPending) {
                      event.preventDefault();
                      searchMutation.mutate();
                    }
                  }}
                />
                <button
                  type="button"
                  className={`${styles.btn} ${styles.btnPrimary}`}
                  disabled={search.trim().length < 3 || searchMutation.isPending}
                  onClick={() => searchMutation.mutate()}
                >
                  <Search size={18} aria-hidden="true" /> {searchMutation.isPending ? "Buscando..." : "Buscar"}
                </button>
              </div>
            </div>

            {notice && (
              <div className={styles.alert} role="status">
                <Info size={18} aria-hidden="true" style={{ flex: "none", marginTop: 1 }} />
                <span>{notice}</span>
              </div>
            )}

            {canCreate && (
              <div style={{ marginTop: 16 }}>
                <div className={styles.grid2}>
                  <div className={styles.field}>
                    <label htmlFor="visitante-nome">Nome do visitante</label>
                    <input
                      id="visitante-nome"
                      className={styles.input}
                      autoComplete="off"
                      value={visitor.name}
                      onChange={(event) => setVisitor({ ...visitor, name: event.target.value })}
                    />
                  </div>
                  <div className={styles.field}>
                    <label htmlFor="visitante-telefone">Telefone</label>
                    <input
                      id="visitante-telefone"
                      className={styles.input}
                      inputMode="tel"
                      autoComplete="off"
                      value={visitor.phone}
                      onChange={(event) => setVisitor({ ...visitor, phone: event.target.value })}
                    />
                  </div>
                </div>
                <p className={styles.help} style={{ margin: "8px 0 12px" }}>
                  O giftback fica guardado no celular. Quando o cliente criar a conta com o mesmo número, o saldo vai junto.
                </p>
                <button
                  type="button"
                  className={styles.btn}
                  disabled={visitorMutation.isPending}
                  onClick={() => visitorMutation.mutate()}
                >
                  <UserPlus size={18} aria-hidden="true" /> {visitorMutation.isPending ? "Criando..." : "Criar visitante"}
                </button>
                {visitorMutation.isError && (
                  <div className={`${styles.alert} ${styles.alertError}`} role="alert">
                    {visitorMutation.error.message}
                  </div>
                )}
              </div>
            )}

            {customer && (
              <div className={styles.customer}>
                <span className={styles.avatar} aria-hidden="true">
                  {initials(customer.name)}
                </span>
                <div className={styles.customerInfo}>
                  <strong>{customer.name}</strong>
                  <span>{customer.phone || customer.email}</span>
                  <span>
                    {customer.purchaseCount} {customer.purchaseCount === 1 ? "compra" : "compras"} aqui
                  </span>
                </div>
                <div className={styles.customerBalance}>
                  <span>Saldo disponível</span>
                  <strong>{moneyFromCents(customer.balanceCents)}</strong>
                </div>
              </div>
            )}
          </section>

          {/* 2. Valor */}
          <section className={styles.card} aria-labelledby="valor-titulo">
            <div className={styles.cardHead}>
              <span className={`${styles.badge} ${totalCents >= MIN_SALE_CENTS ? styles.badgeDone : ""}`} aria-hidden="true">
                {totalCents >= MIN_SALE_CENTS ? "✓" : "2"}
              </span>
              <div>
                <h3 id="valor-titulo">Quanto foi a compra?</h3>
                <p>
                  {giftbackApplies
                    ? `O cliente ganha ${context.store.customerCashbackPercentage}% de giftback sobre o que pagar${context.store.eventPromoter ? " em dinheiro ou Pix" : ""}.`
                    : "Esta loja não gera giftback nas vendas."}
                </p>
              </div>
            </div>

            <div className={styles.field}>
              <label htmlFor="venda-valor">Valor total</label>
              <div className={styles.amount}>
                <span className={styles.amountPrefix} aria-hidden="true">
                  R$
                </span>
                <input
                  id="venda-valor"
                  ref={amountRef}
                  className={`${styles.input} ${styles.amountInput}`}
                  inputMode="decimal"
                  autoComplete="off"
                  placeholder="0,00"
                  readOnly={items.length > 0}
                  value={items.length ? formatInput(itemsTotalCents / 100) : amountText}
                  onChange={(event) => setAmountText(cleanMoney(event.target.value))}
                  onBlur={() => amountText && setAmountText(formatInput(parseMoney(amountText)))}
                />
              </div>
              {items.length > 0 ? (
                <span className={styles.help}>Calculado pela soma dos itens.</span>
              ) : (
                totalCents > 0 &&
                totalCents < MIN_SALE_CENTS && <span className={styles.error}>O valor mínimo da venda é R$ 5,00.</span>
              )}
            </div>

            <div className={styles.field} style={{ marginTop: 18 }}>
              <label htmlFor="venda-saldo">Usar saldo do cliente</label>
              <input
                id="venda-saldo"
                className={styles.input}
                inputMode="decimal"
                autoComplete="off"
                placeholder="0,00"
                disabled={!customer || customer.balanceCents <= 0}
                value={balanceText}
                onChange={(event) => setBalanceText(cleanMoney(event.target.value))}
                onBlur={() => balanceText && setBalanceText(formatInput(balanceCents / 100))}
              />
              <div className={styles.quick}>
                <button
                  type="button"
                  className={`${styles.btn} ${styles.btnSmall}`}
                  disabled={balanceLimitCents <= 0}
                  onClick={() => setBalanceText(formatInput(balanceLimitCents / 100))}
                >
                  Usar tudo ({moneyFromCents(balanceLimitCents)})
                </button>
                <button
                  type="button"
                  className={`${styles.btn} ${styles.btnSmall}`}
                  disabled={!balanceText}
                  onClick={() => setBalanceText("")}
                >
                  Sem saldo
                </button>
              </div>
              <span className={styles.help}>
                {customer
                  ? customer.balanceCents > 0
                    ? `O cliente tem ${moneyFromCents(customer.balanceCents)}. A parte paga com saldo não gera giftback.`
                    : "Este cliente ainda não tem saldo para usar."
                  : "Busque o cliente para ver o saldo disponível."}
              </span>
            </div>

            {context.permissions.assignSeller && (
              <div className={styles.field} style={{ marginTop: 18 }}>
                <label htmlFor="venda-vendedor">Quem vendeu</label>
                <select
                  id="venda-vendedor"
                  className={styles.input}
                  value={sellerId ?? context.user.id ?? ""}
                  onChange={(event) => setSellerId(Number(event.target.value))}
                >
                  {sellers.data?.items.map((seller) => (
                    <option key={seller.id} value={seller.id}>
                      {seller.name}
                    </option>
                  ))}
                </select>
                <span className={styles.help}>Quem registrou a venda também fica identificado, separadamente.</span>
              </div>
            )}

            <div className={styles.field} style={{ marginTop: 18 }}>
              <label htmlFor="venda-descricao">Observação (opcional)</label>
              <textarea
                id="venda-descricao"
                className={`${styles.input} ${styles.textarea}`}
                maxLength={500}
                value={description}
                onChange={(event) => setDescription(event.target.value)}
                placeholder={`Compra na ${context.store.name}`}
              />
            </div>
          </section>

          {/* Mais detalhes (recolhido) */}
          <section className={styles.more}>
            <button
              type="button"
              className={styles.moreToggle}
              aria-expanded={moreOpen}
              aria-controls="venda-detalhes"
              onClick={() => setMoreOpen((open) => !open)}
            >
              <span>
                Mais detalhes
                <small>Itens, código e data da venda. Já vêm preenchidos.</small>
              </span>
              <ChevronDown size={20} aria-hidden="true" className={`${styles.chev} ${moreOpen ? styles.chevOpen : ""}`} />
            </button>
            {moreOpen && (
              <div className={styles.moreBody} id="venda-detalhes">
                <div className={styles.grid2}>
                  <div className={styles.field}>
                    <label htmlFor="venda-codigo">Código da venda</label>
                    <input
                      id="venda-codigo"
                      className={styles.input}
                      maxLength={50}
                      value={code}
                      onChange={(event) => setCode(event.target.value)}
                    />
                  </div>
                  <div className={styles.field}>
                    <label htmlFor="venda-data">Data e hora</label>
                    <input
                      id="venda-data"
                      className={styles.input}
                      type="datetime-local"
                      value={date}
                      onChange={(event) => setDate(event.target.value)}
                    />
                  </div>
                </div>

                <div className={styles.field}>
                  <span className={styles.label}>Itens da venda (opcional)</span>
                  <span className={styles.help}>
                    Quer detalhar o que foi vendido? Adicione os itens e o total é calculado sozinho.
                  </span>
                  <div className={styles.items}>
                    {items.map((item, index) => (
                      <div className={styles.item} key={index}>
                        <input
                          className={`${styles.input} ${styles.itemName}`}
                          aria-label={`Nome do item ${index + 1}`}
                          placeholder="Item ou serviço"
                          maxLength={200}
                          value={item.name}
                          onChange={(event) => updateItem(index, { name: event.target.value })}
                        />
                        <div className={styles.itemQty} role="group" aria-label={`Quantidade do item ${index + 1}`}>
                          <button
                            type="button"
                            aria-label="Diminuir quantidade"
                            onClick={() => updateItem(index, { quantity: String(Math.max(1, Number(item.quantity || 1) - 1)) })}
                          >
                            <Minus size={16} aria-hidden="true" />
                          </button>
                          <input
                            inputMode="numeric"
                            aria-label={`Quantidade do item ${index + 1}`}
                            value={item.quantity}
                            onChange={(event) => updateItem(index, { quantity: event.target.value.replace(/\D/g, "").slice(0, 4) })}
                          />
                          <button
                            type="button"
                            aria-label="Aumentar quantidade"
                            onClick={() => updateItem(index, { quantity: String(Number(item.quantity || 0) + 1) })}
                          >
                            <Plus size={16} aria-hidden="true" />
                          </button>
                        </div>
                        <div className={styles.itemPrice}>
                          <input
                            className={styles.input}
                            inputMode="decimal"
                            aria-label={`Preço unitário do item ${index + 1}`}
                            placeholder="Preço (R$)"
                            value={item.unitPrice}
                            onChange={(event) => updateItem(index, { unitPrice: cleanMoney(event.target.value) })}
                          />
                          <button
                            type="button"
                            className={styles.iconBtn}
                            aria-label={`Remover item ${index + 1}`}
                            onClick={() => setItems((current) => current.filter((_, position) => position !== index))}
                          >
                            <Trash2 size={18} aria-hidden="true" />
                          </button>
                        </div>
                      </div>
                    ))}
                  </div>
                  {items.length > 0 && itemsInvalid && (
                    <span className={styles.error} role="alert">
                      Preencha nome, quantidade inteira e preço maior que zero em cada item.
                    </span>
                  )}
                  <div>
                    <button
                      type="button"
                      className={`${styles.btn} ${styles.btnSmall}`}
                      disabled={items.length >= 100}
                      onClick={() => setItems((current) => [...current, { name: "", quantity: "1", unitPrice: "" }])}
                    >
                      <Plus size={16} aria-hidden="true" /> {items.length ? "Adicionar outro item" : "Detalhar itens"}
                    </button>
                  </div>
                </div>
              </div>
            )}
          </section>
        </div>

        {/* Resumo ao vivo */}
        <aside className={styles.aside} aria-label="Resumo da venda">
          <div className={styles.summary}>
            <h3>Resumo da venda</h3>
            <div className={styles.sumList} aria-live="polite">
              <div className={styles.sumRow}>
                <span>Cliente</span>
                <strong>{customer ? firstName(customer.name) : "—"}</strong>
              </div>
              <div className={styles.sumRow}>
                <span>Valor da compra</span>
                <strong>{moneyFromCents(totalCents)}</strong>
              </div>
              <div className={styles.sumRow}>
                <span>Pago com saldo</span>
                <strong>{moneyFromCents(balanceCents)}</strong>
              </div>
              <div className={`${styles.sumRow} ${styles.sumTotal}`}>
                <span>O cliente paga</span>
                <strong>{moneyFromCents(paidCents)}</strong>
              </div>
            </div>
            <div className={styles.gift}>
              <span>Giftback que o cliente ganha</span>
              <strong>{moneyFromCents(cashbackCents)}</strong>
              <small>
                {giftbackApplies ? `${context.store.customerCashbackPercentage}% sobre o valor pago` : "Esta loja não gera giftback"}
              </small>
            </div>

            {saleMutation.isError && (
              <div className={`${styles.alert} ${styles.alertError}`} role="alert">
                {saleMutation.error.message}
              </div>
            )}

            <div className={styles.actionBar}>
              {blockReason && <p className={styles.reason}>{blockReason}</p>}
              <button
                type="button"
                className={`${styles.btn} ${styles.btnPrimary}`}
                disabled={Boolean(blockReason) || saleMutation.isPending}
                onClick={() => setReviewing(true)}
              >
                Revisar e confirmar
              </button>
            </div>
          </div>
        </aside>
      </div>

      {reviewing && customer && (
        <div className={styles.backdrop} onClick={() => !saleMutation.isPending && setReviewing(false)}>
          <div
            className={styles.modal}
            role="dialog"
            aria-modal="true"
            aria-labelledby={modalTitle}
            onClick={(event) => event.stopPropagation()}
          >
            <h3 id={modalTitle}>Confira e confirme</h3>
            <p>O giftback é creditado junto com a venda.</p>
            <div className={styles.sumList}>
              <div className={styles.sumRow}>
                <span>Cliente</span>
                <strong>{customer.name}</strong>
              </div>
              <div className={styles.sumRow}>
                <span>Valor da compra</span>
                <strong>{moneyFromCents(totalCents)}</strong>
              </div>
              {items.map((item, index) => (
                <div className={styles.sumRow} key={index}>
                  <span>
                    {item.quantity} × {item.name}
                  </span>
                  <strong>{moneyFromCents(Number(item.quantity) * cents(parseMoney(item.unitPrice)))}</strong>
                </div>
              ))}
              <div className={styles.sumRow}>
                <span>Pago com saldo</span>
                <strong>{moneyFromCents(balanceCents)}</strong>
              </div>
              <div className={`${styles.sumRow} ${styles.sumTotal}`}>
                <span>O cliente paga</span>
                <strong>{moneyFromCents(paidCents)}</strong>
              </div>
              <div className={styles.sumRow}>
                <span>Vendedor</span>
                <strong>
                  {sellers.data?.items.find((seller) => seller.id === (sellerId ?? context.user.id))?.name ?? context.user.name}
                </strong>
              </div>
            </div>
            <div className={styles.gift}>
              <span>Giftback que o cliente ganha</span>
              <strong>{moneyFromCents(cashbackCents)}</strong>
            </div>
            <div className={styles.modalActions}>
              <button type="button" className={styles.btn} disabled={saleMutation.isPending} onClick={() => setReviewing(false)}>
                Corrigir
              </button>
              <button
                type="button"
                ref={confirmRef}
                className={`${styles.btn} ${styles.btnPrimary}`}
                disabled={saleMutation.isPending}
                onClick={() => saleMutation.mutate()}
              >
                {saleMutation.isPending ? "Registrando..." : "Confirmar venda"}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
