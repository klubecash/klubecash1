"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  ArrowLeft,
  ArrowRight,
  CheckCircle2,
  Search,
  ShieldAlert,
  UserPlus,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { useForm, useWatch } from "react-hook-form";
import { z } from "zod";
import { storeFetch } from "@/lib/client-api";
import { moneyFromCents } from "@/lib/format";
import { useStoreContext } from "@/components/store/StoreProviders";
import Link from "next/link";

type Customer = {
  id: number;
  name: string;
  email: string | null;
  phone: string;
  cpf: string | null;
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

const schema = z.object({
  total: z.coerce.number().min(5, "O valor mínimo da venda é R$ 5,00."),
  code: z.string().trim().min(3, "Informe o código da venda.").max(50),
  date: z.string().min(1, "Informe a data."),
  description: z.string().max(500).optional(),
  balanceAmount: z.coerce.number().min(0).default(0),
});
type SaleForm = z.infer<typeof schema>;
type SaleInput = z.input<typeof schema>;
type SaleItem = { name: string; quantity: string; unitPrice: string };

const saleCode = () => `VENDA-${Date.now().toString(36).toUpperCase()}`;
const saleDate = () => new Date().toISOString().slice(0, 16);
const cents = (value: number) => Math.round(value * 100);

export default function RegisterTransactionPage() {
  const context = useStoreContext();
  const queryClient = useQueryClient();
  const idempotencyKey = useRef<string | null>(null);
  const [step, setStep] = useState(1);
  const [search, setSearch] = useState("");
  const [customer, setCustomer] = useState<Customer | null>(null);
  const [notice, setNotice] = useState("");
  const [canCreate, setCanCreate] = useState(false);
  const [visitor, setVisitor] = useState({ name: "", phone: "" });
  const [result, setResult] = useState<SaleResult | null>(null);
  const [sellerId, setSellerId] = useState<number | null>(null);
  const [items, setItems] = useState<SaleItem[]>([]);
  const [itemsError, setItemsError] = useState("");
  const sellers = useQuery({ queryKey: ["store-sellers", context.store.id],
    queryFn: () => storeFetch<{ items: Array<{ id: number; name: string; role: string }> }>("sellers"),
    enabled: Boolean(context.permissions.assignSeller) });
  const form = useForm<SaleInput, unknown, SaleForm>({
    resolver: zodResolver(schema),
    defaultValues: {
      total: 0,
      code: saleCode(),
      date: saleDate(),
      description: "",
      balanceAmount: 0,
    },
  });
  const totalCents = cents(
    Number(useWatch({ control: form.control, name: "total" }) || 0),
  );
  useEffect(() => {
    if (items.length) {
      const sum = items.reduce((amount, item) => amount + Number(item.quantity || 0) * cents(Number(item.unitPrice || 0)), 0);
      form.setValue("total", sum / 100, { shouldValidate: true });
    }
  }, [items, form]);
  const balanceCents = cents(
    Number(useWatch({ control: form.control, name: "balanceAmount" }) || 0),
  );
  const paidCents = Math.max(0, totalCents - balanceCents);
  const cashbackCents = Math.round(
    (paidCents * context.store.customerCashbackPercentage) / 100,
  );

  const searchMutation = useMutation({
    mutationFn: () =>
      storeFetch<CustomerSearch>(
        `customers/search&query=${encodeURIComponent(search.trim())}`,
      ),
    onSuccess: (data) => {
      setCustomer(data.customer);
      setCanCreate(data.canCreateVisitor);
      setVisitor((current) => ({
        ...current,
        phone: data.suggestedPhone || current.phone,
      }));
      setNotice(
        data.customer
          ? ""
          : "Cliente não encontrado. Você pode cadastrá-lo como visitante.",
      );
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
    mutationFn: (values: SaleForm) => {
      idempotencyKey.current ??= crypto.randomUUID();
      return storeFetch<SaleResult>("transactions", {
        method: "POST",
        headers: {
          "X-CSRF-Token": context.csrfToken,
          "X-Idempotency-Key": idempotencyKey.current,
        },
        body: JSON.stringify({
          customerId: customer?.id,
          grossAmountCents: cents(values.total),
          balanceUsedCents: cents(values.balanceAmount),
          code: values.code,
          occurredAt: new Date(values.date).toISOString(),
          description: values.description,
          items: items.map((item) => ({ name: item.name.trim(), quantity: Number(item.quantity), unitPriceCents: cents(Number(item.unitPrice)) })),
          sellerId: context.permissions.assignSeller ? (sellerId ?? context.user.id) : context.user.id,
          csrfToken: context.csrfToken,
        }),
      });
    },
    onSuccess: (data) => {
      setResult(data);
      setStep(4);
      queryClient.invalidateQueries({ queryKey: ["store-dashboard"] });
      queryClient.invalidateQueries({ queryKey: ["transactions"] });
    },
  });

  if (context.subscription.salesBlocked) {
    return (
      <div className="store-page store-stack">
        <section className="store-panel store-empty">
          <ShieldAlert size={52} style={{ color: "var(--store-warning, #d97706)" }} />
          <h2>Registro de vendas temporariamente bloqueado</h2>
          <p>O histórico, clientes e demais recursos continuam disponíveis. Regularize ou reative o plano para registrar novas vendas.</p>
          <Link className="store-button store-button-primary" href="/store/meu-plano">Ver plano e faturas</Link>
        </section>
      </div>
    );
  }

  const reset = () => {
    setStep(1);
    setCustomer(null);
    setResult(null);
    setSearch("");
    setNotice("");
    idempotencyKey.current = null;
    setSellerId(null);
    setItems([]);
    setItemsError("");
    form.reset({
      total: 0,
      code: saleCode(),
      date: saleDate(),
      description: "",
      balanceAmount: 0,
    });
  };

  return (
    <div className="store-page store-stack">
      <section className="store-page-head">
        <div>
          <h2>{result ? "Venda aprovada!" : "Nova venda"}</h2>
          <p>
            O giftback do cliente é aprovado e creditado imediatamente, sem
            comissão ou cobrança posterior.
          </p>
        </div>
      </section>
      <div className="store-alert" role="status">Filial desta venda: <strong>{context.store.name}</strong>. Registro feito por <strong>{context.user.name}</strong>. Confira a filial ativa no menu antes de continuar.</div>
      <div className="store-steps" aria-label={`Etapa ${step} de 4`}>
        {[1, 2, 3, 4].map((item) => (
          <span
            key={item}
            className={`store-step ${item <= step ? "done" : ""}`}
          />
        ))}
      </div>

      {step === 1 && (
        <section className="store-panel store-form">
          <div className="store-panel-head">
            <div>
              <h3>1. Encontre o cliente</h3>
              <p>Busque por e-mail, CPF ou telefone.</p>
            </div>
          </div>
          <label className="store-field">
            <span>E-mail, CPF ou telefone</span>
            <div style={{ display: "flex", gap: 8 }}>
              <input
                className="store-input"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                onKeyDown={(event) => {
                  if (event.key === "Enter" && search.trim().length >= 3)
                    searchMutation.mutate();
                }}
              />
              <button
                type="button"
                className="store-button store-button-primary"
                disabled={search.trim().length < 3 || searchMutation.isPending}
                onClick={() => searchMutation.mutate()}
              >
                <Search size={16} /> Buscar
              </button>
            </div>
          </label>
          {notice && <div className="store-alert">{notice}</div>}
          {canCreate && (
            <div className="store-form-grid">
              <label className="store-field">
                <span>Nome do visitante</span>
                <input
                  className="store-input"
                  value={visitor.name}
                  onChange={(event) =>
                    setVisitor({ ...visitor, name: event.target.value })
                  }
                />
              </label>
              <label className="store-field">
                <span>Telefone</span>
                <input
                  className="store-input"
                  value={visitor.phone}
                  onChange={(event) =>
                    setVisitor({ ...visitor, phone: event.target.value })
                  }
                />
              </label>
              <button
                type="button"
                className="store-button"
                disabled={visitorMutation.isPending}
                onClick={() => visitorMutation.mutate()}
              >
                <UserPlus size={16} /> Criar visitante
              </button>
              {visitorMutation.isError && (
                <div className="store-alert store-alert-error">
                  {visitorMutation.error.message}
                </div>
              )}
            </div>
          )}
          {customer && (
            <div className="store-customer-card">
              <h4>{customer.name}</h4>
              <p>{customer.email ?? customer.phone}</p>
              <p>
                Saldo disponível: {moneyFromCents(customer.balanceCents)} · {" "}
                {customer.purchaseCount} compra(s)
              </p>
            </div>
          )}
          <div className="store-form-actions">
            <button
              className="store-button store-button-primary"
              disabled={!customer}
              onClick={() => setStep(2)}
            >
              Continuar <ArrowRight size={16} />
            </button>
          </div>
        </section>
      )}

      {step === 2 && (
        <form
          className="store-panel store-form"
          onSubmit={form.handleSubmit(() => {
            if (items.some((item) => !item.name.trim() || !Number.isInteger(Number(item.quantity)) || Number(item.quantity) < 1 || cents(Number(item.unitPrice)) < 1)) {
              setItemsError("Preencha nome, quantidade inteira e preço positivo em cada item.");
              return;
            }
            setItemsError(""); setStep(3);
          })}
        >
          <div className="store-panel-head">
            <div>
              <h3>2. Dados da venda</h3>
              <p>
                Giftback atual: {context.store.customerCashbackPercentage}% do
                valor efetivamente pago.
              </p>
            </div>
          </div>
          <div className="store-form-grid">
            {context.permissions.assignSeller && <label className="store-field store-field-full">
              <span>Quem realizou a venda</span>
              <select className="store-select" value={sellerId ?? context.user.id ?? ""}
                onChange={(event) => setSellerId(Number(event.target.value))}>
                {sellers.data?.items.map((seller) => <option key={seller.id} value={seller.id}>{seller.name}</option>)}
              </select>
              <small className="store-help">O responsável pelo registro também ficará identificado separadamente.</small>
            </label>}
            <Field label="Valor total" error={form.formState.errors.total?.message}>
              <input
                className="store-input"
                type="number"
                min="5"
                step="0.01"
                readOnly={items.length > 0}
                {...form.register("total")}
              />
              {items.length > 0 && <small className="store-help">Calculado pela soma dos itens.</small>}
            </Field>
            <Field label="Código da venda" error={form.formState.errors.code?.message}>
              <input className="store-input" {...form.register("code")} />
            </Field>
            <Field label="Data e hora" error={form.formState.errors.date?.message}>
              <input
                className="store-input"
                type="datetime-local"
                {...form.register("date")}
              />
            </Field>
            <Field label="Usar saldo do cliente">
              <input
                className="store-input"
                type="number"
                min="0"
                max={Math.min(totalCents, customer?.balanceCents ?? 0) / 100}
                step="0.01"
                {...form.register("balanceAmount")}
              />
              <small className="store-help">
                Disponível: {moneyFromCents(customer?.balanceCents)}
              </small>
            </Field>
            <label className="store-field store-field-full">
              <span>Descrição</span>
              <textarea
                className="store-textarea"
                {...form.register("description")}
                placeholder={`Compra na ${context.store.name}`}
              />
            </label>
            <div className="store-field store-field-full">
              <span>Itens da venda (opcional)</span>
              <small className="store-help">Cada linha tem nome livre, quantidade e preço unitário. Sem itens, informe o total acima.</small>
              {items.map((item, index) => <div className="store-actions" key={index} style={{ marginTop: 8 }}>
                <input className="store-input" aria-label={`Nome do item ${index + 1}`} placeholder="Item ou serviço" maxLength={200} value={item.name}
                  onChange={(event) => setItems((current) => current.map((line, position) => position === index ? { ...line, name: event.target.value } : line))} />
                <input className="store-input" aria-label={`Quantidade do item ${index + 1}`} type="number" min="1" step="1" value={item.quantity}
                  onChange={(event) => setItems((current) => current.map((line, position) => position === index ? { ...line, quantity: event.target.value } : line))} />
                <input className="store-input" aria-label={`Preço unitário do item ${index + 1}`} type="number" min="0.01" step="0.01" value={item.unitPrice}
                  onChange={(event) => setItems((current) => current.map((line, position) => position === index ? { ...line, unitPrice: event.target.value } : line))} />
                <button type="button" className="store-button" onClick={() => setItems((current) => current.filter((_, position) => position !== index))}>Remover</button>
              </div>)}
              {itemsError && <small className="store-error" role="alert">{itemsError}</small>}
              <button type="button" className="store-button" style={{ marginTop: 8 }} disabled={items.length >= 100}
                onClick={() => setItems((current) => [...current, { name: "", quantity: "1", unitPrice: "" }])}>Adicionar item</button>
            </div>
          </div>
          <div className="store-form-actions">
            <button type="button" className="store-button" onClick={() => setStep(1)}>
              <ArrowLeft size={16} /> Voltar
            </button>
            <button className="store-button store-button-primary">
              Revisar venda <ArrowRight size={16} />
            </button>
          </div>
        </form>
      )}

      {step === 3 && (
        <section className="store-grid store-grid-2">
          <div className="store-panel">
            <div className="store-panel-head">
              <div>
                <h3>3. Confirme os dados</h3>
                <p>A aprovação e o crédito acontecerão juntos.</p>
              </div>
            </div>
            <div className="store-summary-list">
              <Row label="Cliente" value={customer?.name ?? ""} />
              <Row label="Filial" value={context.store.name} />
              <Row label="Vendedor" value={sellers.data?.items.find((item) => item.id === (sellerId ?? context.user.id))?.name ?? context.user.name} />
              <Row label="Registrado por" value={context.user.name} />
              <Row label="Código" value={form.getValues("code")} />
              <Row label="Valor da compra" value={moneyFromCents(totalCents)} />
              {items.map((item, index) => <Row key={index} label={`${item.quantity} × ${item.name}`} value={moneyFromCents(Number(item.quantity) * cents(Number(item.unitPrice)))} />)}
              <Row label="Saldo utilizado" value={moneyFromCents(balanceCents)} />
              <Row label="Valor efetivamente pago" value={moneyFromCents(paidCents)} />
            </div>
          </div>
          <div className="store-panel">
            <div className="store-panel-head">
              <div>
                <h3>Giftback do cliente</h3>
                <p>Não existe comissão ou parcela administrativa.</p>
              </div>
            </div>
            <div className="store-summary-list">
              <Row
                label={`Giftback (${context.store.customerCashbackPercentage}%)`}
                value={moneyFromCents(cashbackCents)}
              />
              <Row label="Status após confirmar" value="Aprovado" />
            </div>
            {saleMutation.isError && (
              <div className="store-alert store-alert-error" style={{ marginTop: 14 }}>
                {saleMutation.error.message}
              </div>
            )}
            <div className="store-form-actions">
              <button className="store-button" onClick={() => setStep(2)}>
                <ArrowLeft size={16} /> Corrigir
              </button>
              <button
                className="store-button store-button-primary"
                disabled={saleMutation.isPending}
                onClick={form.handleSubmit((values) => saleMutation.mutate(values))}
              >
                {saleMutation.isPending ? "Aprovando..." : "Confirmar venda"}
              </button>
            </div>
          </div>
        </section>
      )}

      {step === 4 && result && (
        <section className="store-panel store-empty">
          <CheckCircle2 size={58} style={{ color: "var(--store-green)" }} />
          <h3>Venda aprovada e giftback creditado</h3>
          <p>
            Giftback: <strong>{moneyFromCents(result.cashbackGrantedCents)}</strong>
            {result.replayed ? " · confirmação recuperada com segurança" : ""}
          </p>
          <button
            className="store-button store-button-primary"
            style={{ marginTop: 18 }}
            onClick={reset}
          >
            Registrar outra venda
          </button>
        </section>
      )}
    </div>
  );
}

function Field({
  label,
  error,
  children,
}: {
  label: string;
  error?: string;
  children: React.ReactNode;
}) {
  return (
    <label className="store-field">
      <span>{label}</span>
      {children}
      {error && <small className="store-error">{error}</small>}
    </label>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="store-summary-row">
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  );
}
