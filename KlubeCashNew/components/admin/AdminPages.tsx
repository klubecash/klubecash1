"use client";

import Link from "next/link";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  ArrowRight,
  BadgeDollarSign,
  BarChart3,
  Check,
  CircleDollarSign,
  Clock3,
  Download,
  Edit3,
  Eye,
  Plus,
  ReceiptText,
  RotateCcw,
  Save,
  Search,
  Send,
  ShieldCheck,
  ShoppingBag,
  Users,
  XCircle,
} from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { useForm, useWatch } from "react-hook-form";
import { z } from "zod";
import { adminFetch, mutationHeaders } from "@/lib/admin-client";
import { dateTime, moneyFromCents, monthLabel, number } from "@/lib/format";
import { useAdminContext } from "./AdminProviders";
import {
  EmptyState,
  ErrorState,
  LoadingState,
  Modal,
  PaginationBar,
  Status,
} from "./AdminStates";
import type {
  AuditItem,
  CampaignItem,
  DashboardData,
  FinanceData,
  PageData,
  PlanItem,
  SettingsData,
  StoreItem,
  SubscriptionItem,
  TemplateItem,
  TransactionItem,
  UserItem,
} from "@/types/admin";

function PageHead({
  title,
  description,
  children,
}: {
  title: string;
  description: string;
  children?: React.ReactNode;
}) {
  return (
    <section className="admin-head">
      <div>
        <h2>{title}</h2>
        <p>{description}</p>
      </div>
      {children && <div className="admin-actions">{children}</div>}
    </section>
  );
}

function Stat({
  label,
  value,
  note,
  icon,
}: {
  label: string;
  value: string;
  note: string;
  icon: React.ReactNode;
}) {
  return (
    <div className="admin-stat">
      <div className="admin-stat-top">
        <span className="admin-stat-label">{label}</span>
        <span className="admin-stat-icon">{icon}</span>
      </div>
      <strong className="admin-stat-value">{value}</strong>
      <span className="admin-stat-note">{note}</span>
    </div>
  );
}

function mutationMessage(
  mutation: { isError: boolean; isSuccess: boolean; error: Error | null },
  success: string,
) {
  if (mutation.isError)
    return (
      <div className="admin-alert admin-alert-danger">
        <strong>A operação não foi concluída.</strong>
        {mutation.error?.message}
      </div>
    );
  if (mutation.isSuccess)
    return (
      <div className="admin-alert admin-alert-success">
        <strong>Alteração salva.</strong>
        {success}
      </div>
    );
  return null;
}

export function DashboardPage() {
  const admin = useAdminContext();
  const query = useQuery({
    queryKey: ["admin-dashboard"],
    queryFn: () => adminFetch<DashboardData>("dashboard"),
  });
  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data)
    return <ErrorState error={query.error} retry={() => query.refetch()} />;
  const { summary, recentTransactions, pendingStores, monthly } = query.data;
  const maximum = Math.max(1, ...monthly.map((item) => item.grossAmountCents));
  return (
    <div className="admin-page">
      <PageHead
        title={`Olá, ${admin.user.name.split(" ")[0]}.`}
        description="Acompanhe a operação completa do Klube Cash sem misturar o modelo atual com obrigações financeiras históricas."
      >
        <Link href="/admin/relatorios" className="admin-button">
          <BarChart3 size={16} />
          Ver relatórios
        </Link>
      </PageHead>
      <div className="admin-alert admin-alert-success">
        <strong>Modelo financeiro atual: cashback por assinatura.</strong>Novas
        vendas creditam apenas o benefício do cliente e não geram comissão ou
        repasse.
      </div>
      <section className="admin-grid admin-grid-4">
        <Stat
          label="Vendas atuais"
          value={moneyFromCents(summary.currentGrossAmountCents)}
          note={`${number(summary.currentSalesCount)} no modelo por assinatura`}
          icon={<CircleDollarSign size={19} />}
        />
        <Stat
          label="Cashback atual"
          value={moneyFromCents(summary.currentCashbackAmountCents)}
          note="Sem comissão ou repasse"
          icon={<BadgeDollarSign size={19} />}
        />
        <Stat
          label="Base ativa"
          value={number(summary.customers)}
          note={`${number(summary.approvedStores)} lojas aprovadas`}
          icon={<Users size={19} />}
        />
        <Stat
          label="Alertas operacionais"
          value={number(summary.pendingLegacyItems + summary.pendingStores)}
          note={`${summary.pendingLegacyItems} financeiros · ${summary.pendingStores} lojas`}
          icon={<Clock3 size={19} />}
        />
      </section>
      <div className="admin-alert">
        <strong>Histórico financeiro legado separado.</strong>
        {number(summary.legacySalesCount)} vendas · {moneyFromCents(summary.legacyGrossAmountCents)} movimentados · {moneyFromCents(summary.legacyCashbackAmountCents)} em benefício do cliente.
      </div>
      <section className="admin-grid admin-grid-2">
        <div className="admin-panel">
          <div className="admin-panel-head">
            <div>
              <h3>Movimentação mensal</h3>
              <p>Somente vendas do modelo atual nos últimos doze meses</p>
            </div>
          </div>
          {monthly.length ? (
            <div
              className="admin-chart"
              aria-label="Gráfico de movimentação mensal"
            >
              {monthly.map((item) => (
                <div className="admin-chart-column" key={item.month}>
                  <div
                    className="admin-chart-bar"
                    title={moneyFromCents(item.grossAmountCents)}
                    style={{
                      height: `${Math.max(4, (item.grossAmountCents / maximum) * 100)}%`,
                    }}
                  />
                  <span>{monthLabel(item.month)}</span>
                </div>
              ))}
            </div>
          ) : (
            <EmptyState title="Sem vendas no período" />
          )}
        </div>
        <div className="admin-panel">
          <div className="admin-panel-head">
            <div>
              <h3>Lojas aguardando análise</h3>
              <p>Cadastros pendentes mais antigos primeiro</p>
            </div>
            <Link className="admin-link" href="/admin/lojas?status=pendente">
              Ver todas <ArrowRight size={13} />
            </Link>
          </div>
          {pendingStores.length ? (
            <div>
              {pendingStores.map((item) => (
                <Link
                  href={`/admin/lojas?search=${encodeURIComponent(item.name)}`}
                  key={item.id}
                  className="admin-detail"
                  style={{
                    display: "flex",
                    justifyContent: "space-between",
                    marginBottom: 8,
                  }}
                >
                  <span>
                    <strong>{item.name}</strong>
                    <small>
                      {item.category} · {item.cnpj}
                    </small>
                  </span>
                  <ArrowRight size={15} />
                </Link>
              ))}
            </div>
          ) : (
            <EmptyState
              title="Nenhuma loja pendente"
              message="Todos os cadastros foram analisados."
            />
          )}
        </div>
      </section>
      <section className="admin-panel">
        <div className="admin-panel-head">
          <div>
            <h3>Transações recentes</h3>
            <p>Dados reais do backend PHP</p>
          </div>
          <Link className="admin-link" href="/admin/transacoes">
            Ver histórico <ArrowRight size={13} />
          </Link>
        </div>
        {recentTransactions.length ? (
          <div className="admin-table-wrap">
            <table className="admin-table">
              <thead>
                <tr>
                  <th>Cliente / loja</th>
                  <th>Código</th>
                  <th>Valor</th>
                  <th>Cashback</th>
                  <th>Modelo</th>
                  <th>Status</th>
                  <th>Data</th>
                </tr>
              </thead>
              <tbody>
                {recentTransactions.map((item) => (
                  <tr key={item.id}>
                    <td>
                      <strong>{item.customerName}</strong>
                      <small>{item.storeName}</small>
                    </td>
                    <td className="admin-code">{item.code}</td>
                    <td>
                      <strong>{moneyFromCents(item.grossAmountCents)}</strong>
                    </td>
                    <td>{moneyFromCents(item.cashbackAmountCents)}</td>
                    <td>
                      <span
                        className={`admin-model ${item.financialModel === "commission_legacy" ? "legacy" : ""}`}
                      >
                        {item.financialModel === "commission_legacy"
                          ? "Legado"
                          : "Atual"}
                      </span>
                    </td>
                    <td>
                      <Status value={item.status} />
                    </td>
                    <td>{dateTime(item.occurredAt)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <EmptyState />
        )}
      </section>
    </div>
  );
}

const userSchema = z.object({
  name: z.string().min(2, "Informe o nome."),
  email: z.email("E-mail inválido."),
  phone: z.string(),
  type: z.enum(["cliente", "loja", "funcionario"]),
  status: z.enum(["ativo", "inativo", "bloqueado"]),
  linkedStoreId: z.number().optional(),
  employeeSubtype: z.string().optional(),
  updatedAt: z.string().optional(),
});
type UserForm = z.infer<typeof userSchema>;

export function UsersPage() {
  const context = useAdminContext();
  const client = useQueryClient();
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [type, setType] = useState("");
  const [status, setStatus] = useState("");
  const [editing, setEditing] = useState<UserItem | null | undefined>(
    undefined,
  );
  const queryString = new URLSearchParams({
    page: String(page),
    ...(search && { search }),
    ...(type && { type }),
    ...(status && { status }),
  }).toString();
  const query = useQuery({
    queryKey: ["admin-users", page, search, type, status],
    queryFn: () => adminFetch<PageData<UserItem>>(`users?${queryString}`),
  });
  const stores = useQuery({
    queryKey: ["admin-store-options"],
    queryFn: () => adminFetch<PageData<StoreItem>>("stores?pageSize=100"),
  });
  const form = useForm<UserForm>({
    resolver: zodResolver(userSchema),
    defaultValues: {
      name: "",
      email: "",
      phone: "",
      type: "cliente",
      status: "ativo",
    },
  });
  const userType = useWatch({ control: form.control, name: "type" });
  const save = useMutation({
    mutationFn: (values: UserForm) =>
      editing
        ? adminFetch(`users/${editing.id}`, {
            method: "PATCH",
            headers: mutationHeaders(context.csrfToken),
            body: JSON.stringify(values),
          })
        : adminFetch("users", {
            method: "POST",
            headers: mutationHeaders(context.csrfToken),
            body: JSON.stringify(values),
          }),
    onSuccess: async () => {
      await client.invalidateQueries({ queryKey: ["admin-users"] });
      setEditing(undefined);
      form.reset();
    },
  });
  const changeStatus = useMutation({
    mutationFn: ({ id, next }: { id: number; next: string }) =>
      adminFetch(`users/${id}/status`, {
        method: "POST",
        headers: mutationHeaders(context.csrfToken),
        body: JSON.stringify({ status: next }),
      }),
    onSuccess: () => client.invalidateQueries({ queryKey: ["admin-users"] }),
  });
  const passwordReset = useMutation({
    mutationFn: (id: number) =>
      adminFetch(`users/${id}/password-reset`, {
        method: "POST",
        headers: mutationHeaders(context.csrfToken),
        body: "{}",
      }),
  });
  function open(item: UserItem | null) {
    setEditing(item);
    form.reset(
      item
        ? {
            name: item.name,
            email: item.email,
            phone: item.phone,
            type: item.type as UserForm["type"],
            status: item.status as UserForm["status"],
            linkedStoreId: item.linkedStoreId ?? undefined,
            employeeSubtype: item.employeeSubtype ?? "funcionario",
            updatedAt: item.updatedAt,
          }
        : { name: "", email: "", phone: "", type: "cliente", status: "ativo" },
    );
  }
  return (
    <div className="admin-page">
      <PageHead
        title="Usuários"
        description="Gerencie clientes, lojistas e funcionários. As duas contas administrativas existentes permanecem protegidas."
      >
        <button
          className="admin-button admin-button-primary"
          onClick={() => open(null)}
        >
          <Plus size={16} />
          Novo usuário
        </button>
      </PageHead>
      <div className="admin-toolbar">
        <div className="admin-field">
          <label>Pesquisar</label>
          <div style={{ position: "relative" }}>
            <Search
              size={15}
              style={{ position: "absolute", left: 11, top: 13 }}
            />
            <input
              className="admin-input"
              style={{ paddingLeft: 34 }}
              value={search}
              onChange={(e) => {
                setSearch(e.target.value);
                setPage(1);
              }}
              placeholder="Nome, e-mail ou telefone"
            />
          </div>
        </div>
        <div className="admin-field admin-field-small">
          <label>Tipo</label>
          <select
            className="admin-select"
            value={type}
            onChange={(e) => {
              setType(e.target.value);
              setPage(1);
            }}
          >
            <option value="">Todos</option>
            <option value="cliente">Clientes</option>
            <option value="loja">Lojistas</option>
            <option value="funcionario">Funcionários</option>
            <option value="admin">Administradores</option>
          </select>
        </div>
        <div className="admin-field admin-field-small">
          <label>Status</label>
          <select
            className="admin-select"
            value={status}
            onChange={(e) => {
              setStatus(e.target.value);
              setPage(1);
            }}
          >
            <option value="">Todos</option>
            <option value="ativo">Ativos</option>
            <option value="inativo">Inativos</option>
            <option value="bloqueado">Bloqueados</option>
          </select>
        </div>
      </div>
      {changeStatus.isError && mutationMessage(changeStatus, "")}
      {mutationMessage(passwordReset, "A recuperação de senha foi adicionada à fila segura.")}
      {query.isLoading ? (
        <LoadingState />
      ) : query.isError || !query.data ? (
        <ErrorState error={query.error} retry={() => query.refetch()} />
      ) : (
        <section className="admin-panel">
          {query.data.items.length ? (
            <>
              <div className="admin-table-wrap">
                <table className="admin-table">
                  <thead>
                    <tr>
                      <th>Usuário</th>
                      <th>Tipo</th>
                      <th>Vínculo</th>
                      <th>Status</th>
                      <th>Cadastro</th>
                      <th>Ações</th>
                    </tr>
                  </thead>
                  <tbody>
                    {query.data.items.map((item) => (
                      <tr key={item.id}>
                        <td>
                          <strong>{item.name}</strong>
                          <small>
                            {item.email} · {item.phone || "Sem telefone"}
                          </small>
                        </td>
                        <td>
                          {item.type}
                          {item.employeeSubtype ? (
                            <small>{item.employeeSubtype}</small>
                          ) : null}
                        </td>
                        <td>{item.linkedStoreName ?? "—"}</td>
                        <td>
                          <Status value={item.status} />
                        </td>
                        <td>{dateTime(item.createdAt)}</td>
                        <td>
                          <div className="admin-actions">
                            <button
                              className="admin-button"
                              disabled={item.status !== "ativo" || passwordReset.isPending}
                              onClick={() => passwordReset.mutate(item.id)}
                            >
                              <ShieldCheck size={14} />
                              Recuperar senha
                            </button>
                            <button
                              className="admin-button"
                              disabled={item.type === "admin"}
                              onClick={() => open(item)}
                            >
                              <Edit3 size={14} />
                              Editar
                            </button>
                            {item.type !== "admin" && (
                              <button
                                className="admin-button"
                                onClick={() =>
                                  changeStatus.mutate({
                                    id: item.id,
                                    next:
                                      item.status === "ativo"
                                        ? "inativo"
                                        : "ativo",
                                  })
                                }
                              >
                                {item.status === "ativo" ? (
                                  <XCircle size={14} />
                                ) : (
                                  <Check size={14} />
                                )}
                                {item.status === "ativo"
                                  ? "Desativar"
                                  : "Ativar"}
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <PaginationBar value={query.data.pagination} onPage={setPage} />
            </>
          ) : (
            <EmptyState />
          )}
        </section>
      )}
      {editing !== undefined && (
        <Modal
          title={editing ? "Editar usuário" : "Novo usuário"}
          subtitle="Senhas são definidas pelo fluxo seguro de recuperação."
          onClose={() => setEditing(undefined)}
        >
          <form
            className="admin-form"
            onSubmit={form.handleSubmit((values) => save.mutate(values))}
          >
            <div className="admin-form-grid">
              <Field label="Nome" error={form.formState.errors.name?.message}>
                <input className="admin-input" {...form.register("name")} />
              </Field>
              <Field
                label="E-mail"
                error={form.formState.errors.email?.message}
              >
                <input
                  className="admin-input"
                  type="email"
                  {...form.register("email")}
                />
              </Field>
              <Field label="Telefone">
                <input className="admin-input" {...form.register("phone")} />
              </Field>
              <Field label="Tipo">
                <select className="admin-select" {...form.register("type")}>
                  <option value="cliente">Cliente</option>
                  <option value="loja">Lojista</option>
                  <option value="funcionario">Funcionário</option>
                </select>
              </Field>
              {userType === "funcionario" && (
                <>
                  <Field label="Loja vinculada">
                    <select
                      className="admin-select"
                      {...form.register("linkedStoreId", {
                        setValueAs: (value) =>
                          value === "" ? undefined : Number(value),
                      })}
                    >
                      <option value="">Selecione</option>
                      {stores.data?.items.map((store) => (
                        <option value={store.id} key={store.id}>
                          {store.name}
                        </option>
                      ))}
                    </select>
                  </Field>
                  <Field label="Função">
                    <select
                      className="admin-select"
                      {...form.register("employeeSubtype")}
                    >
                      <option value="funcionario">Funcionário</option>
                      <option value="gerente">Gerente</option>
                      <option value="financeiro">Financeiro</option>
                      <option value="vendedor">Vendedor</option>
                    </select>
                  </Field>
                </>
              )}
            </div>
            {save.isError && mutationMessage(save, "")}
            <div className="admin-modal-actions">
              <button
                type="button"
                className="admin-button"
                onClick={() => setEditing(undefined)}
              >
                Cancelar
              </button>
              <button
                className="admin-button admin-button-primary"
                disabled={save.isPending}
              >
                <Save size={15} />
                {save.isPending ? "Salvando..." : "Salvar"}
              </button>
            </div>
          </form>
        </Modal>
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
    <label className="admin-field">
      <span>{label}</span>
      {children}
      {error && <small className="admin-error-text">{error}</small>}
    </label>
  );
}

export function StoresPage() {
  const context = useAdminContext();
  const client = useQueryClient();
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [editing, setEditing] = useState<StoreItem | undefined>();
  const params = new URLSearchParams({
    page: String(page),
    ...(search && { search }),
    ...(status && { status }),
  });
  const query = useQuery({
    queryKey: ["admin-stores", page, search, status],
    queryFn: () => adminFetch<PageData<StoreItem>>(`stores?${params}`),
  });
  const loadStore = useMutation({
    mutationFn: (id: number) => adminFetch<{ item: StoreItem }>(`stores/${id}`),
    onSuccess: (data) => setEditing(data.item),
  });
  const storeMutation = useMutation({
    mutationFn: ({
      id,
      next,
      notes,
      updatedAt,
    }: {
      id: number;
      next: string;
      notes: string;
      updatedAt: string;
    }) =>
      adminFetch(`stores/${id}/status`, {
        method: "POST",
        headers: mutationHeaders(context.csrfToken, true),
        body: JSON.stringify({ status: next, notes, updatedAt }),
      }),
    onSuccess: () => client.invalidateQueries({ queryKey: ["admin-stores"] }),
  });
  const save = useMutation({
    mutationFn: (item: StoreItem) =>
      adminFetch(`stores/${item.id}`, {
        method: "PATCH",
        headers: mutationHeaders(context.csrfToken),
        body: JSON.stringify({
          name: item.name,
          legalName: item.legalName,
          email: item.email,
          phone: item.phone,
          category: item.category,
          description: item.description,
          website: item.website,
          customerCashbackPercentage: item.customerCashbackPercentage,
          cashbackEnabled: item.cashbackEnabled,
          updatedAt: item.updatedAt,
        }),
      }),
    onSuccess: () => {
      client.invalidateQueries({ queryKey: ["admin-stores"] });
      setEditing(undefined);
    },
  });
  function statusAction(item: StoreItem, next: string) {
    const notes =
      window.prompt(
        next === "rejeitado" ? "Motivo da rejeição:" : "Observação (opcional):",
        "",
      ) ?? null;
    if (notes !== null)
      storeMutation.mutate({
        id: item.id,
        next,
        notes,
        updatedAt: item.updatedAt,
      });
  }
  return (
    <div className="admin-page">
      <PageHead
        title="Lojas parceiras"
        description="Aprove cadastros, revise dados comerciais e configure o cashback financiado pela própria loja."
      />
      <div className="admin-toolbar">
        <Field label="Pesquisar">
          <input
            className="admin-input"
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
            placeholder="Nome, CNPJ ou e-mail"
          />
        </Field>
        <label className="admin-field admin-field-small">
          <span>Status</span>
          <select
            className="admin-select"
            value={status}
            onChange={(e) => {
              setStatus(e.target.value);
              setPage(1);
            }}
          >
            <option value="">Todas</option>
            <option value="pendente">Pendentes</option>
            <option value="aprovado">Aprovadas</option>
            <option value="rejeitado">Rejeitadas</option>
          </select>
        </label>
      </div>
      {storeMutation.isError && mutationMessage(storeMutation, "")}
      {loadStore.isError && mutationMessage(loadStore, "")}
      {query.isLoading ? (
        <LoadingState />
      ) : query.isError || !query.data ? (
        <ErrorState error={query.error} retry={() => query.refetch()} />
      ) : (
        <section className="admin-panel">
          {query.data.items.length ? (
            <>
              <div className="admin-table-wrap">
                <table className="admin-table">
                  <thead>
                    <tr>
                      <th>Loja</th>
                      <th>Categoria</th>
                      <th>Cashback</th>
                      <th>Operação</th>
                      <th>Status</th>
                      <th>Ações</th>
                    </tr>
                  </thead>
                  <tbody>
                    {query.data.items.map((item) => (
                      <tr key={item.id}>
                        <td>
                          <strong>{item.name}</strong>
                          <small>
                            {item.cnpj} · {item.email}
                          </small>
                        </td>
                        <td>{item.category}</td>
                        <td>
                          <strong>{item.customerCashbackPercentage}%</strong>
                          <small>
                            {item.cashbackEnabled ? "Ativo" : "Desativado"}
                          </small>
                        </td>
                        <td>
                          {number(item.transactionsCount)} vendas
                          <small>{moneyFromCents(item.grossAmountCents)}</small>
                        </td>
                        <td>
                          <Status value={item.status} />
                        </td>
                        <td>
                          <div className="admin-actions">
                            <button
                              className="admin-button"
                              onClick={() => loadStore.mutate(item.id)}
                              disabled={loadStore.isPending}
                            >
                              <Edit3 size={14} />
                              Editar
                            </button>
                            {item.status !== "aprovado" && (
                              <button
                                className="admin-button admin-button-success"
                                onClick={() => statusAction(item, "aprovado")}
                              >
                                <Check size={14} />
                                Aprovar
                              </button>
                            )}
                            {item.status !== "rejeitado" && (
                              <button
                                className="admin-button admin-button-danger"
                                onClick={() => statusAction(item, "rejeitado")}
                              >
                                <XCircle size={14} />
                                Rejeitar
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <PaginationBar value={query.data.pagination} onPage={setPage} />
            </>
          ) : (
            <EmptyState />
          )}
        </section>
      )}
      {editing && (
        <Modal
          title={`Editar ${editing.name}`}
          subtitle="A porcentagem do cliente é a única ativa no modelo atual."
          onClose={() => setEditing(undefined)}
        >
          <section className="admin-grid admin-grid-2">
            <div className="admin-detail">
              <span>Proprietário</span>
              <strong>{editing.owner?.name || editing.ownerName || "—"}</strong>
              <small>{editing.owner?.email}</small>
            </div>
            <div className="admin-detail">
              <span>Assinatura</span>
              <strong>{editing.subscription?.planName ?? "Sem assinatura"}</strong>
              <small>{editing.subscription?.status ?? "—"}</small>
            </div>
            <div className="admin-detail">
              <span>Endereço</span>
              <strong>{editing.address ? `${editing.address.street}, ${editing.address.number}` : "Não informado"}</strong>
              <small>{editing.address ? `${editing.address.city}/${editing.address.state} · ${editing.address.postalCode}` : ""}</small>
            </div>
            <div className="admin-detail">
              <span>Equipe</span>
              <strong>{number(editing.employees?.length ?? editing.employeesCount ?? 0)} funcionários</strong>
              <small>{editing.employees?.map((employee) => `${employee.name} (${employee.subtype})`).join(" · ") || "Nenhum vínculo"}</small>
            </div>
          </section>
          <form
            className="admin-form"
            onSubmit={(e) => {
              e.preventDefault();
              save.mutate(editing);
            }}
          >
            <div className="admin-form-grid">
              <Field label="Nome fantasia">
                <input
                  className="admin-input"
                  value={editing.name}
                  onChange={(e) =>
                    setEditing({ ...editing, name: e.target.value })
                  }
                />
              </Field>
              <Field label="Razão social">
                <input
                  className="admin-input"
                  value={editing.legalName}
                  onChange={(e) =>
                    setEditing({ ...editing, legalName: e.target.value })
                  }
                />
              </Field>
              <Field label="E-mail">
                <input
                  className="admin-input"
                  value={editing.email}
                  onChange={(e) =>
                    setEditing({ ...editing, email: e.target.value })
                  }
                />
              </Field>
              <Field label="Telefone">
                <input
                  className="admin-input"
                  value={editing.phone}
                  onChange={(e) =>
                    setEditing({ ...editing, phone: e.target.value })
                  }
                />
              </Field>
              <Field label="Categoria">
                <input
                  className="admin-input"
                  value={editing.category}
                  onChange={(e) =>
                    setEditing({ ...editing, category: e.target.value })
                  }
                />
              </Field>
              <Field label="Website">
                <input
                  className="admin-input"
                  value={editing.website ?? ""}
                  onChange={(e) => setEditing({ ...editing, website: e.target.value })}
                />
              </Field>
              <Field label="Descrição">
                <textarea
                  className="admin-textarea"
                  value={editing.description ?? ""}
                  onChange={(e) => setEditing({ ...editing, description: e.target.value })}
                />
              </Field>
              <Field label="Cashback do cliente (%)">
                <input
                  className="admin-input"
                  type="number"
                  min="0"
                  max="100"
                  step="0.01"
                  value={editing.customerCashbackPercentage}
                  onChange={(e) =>
                    setEditing({
                      ...editing,
                      customerCashbackPercentage: Number(e.target.value),
                    })
                  }
                />
              </Field>
              <label className="admin-check admin-field-full">
                <input
                  type="checkbox"
                  checked={editing.cashbackEnabled}
                  onChange={(e) =>
                    setEditing({
                      ...editing,
                      cashbackEnabled: e.target.checked,
                    })
                  }
                />
                Cashback ativo para novas vendas
              </label>
            </div>
            {save.isError && mutationMessage(save, "")}
            <div className="admin-modal-actions">
              <button
                type="button"
                className="admin-button"
                onClick={() => setEditing(undefined)}
              >
                Cancelar
              </button>
              <button
                className="admin-button admin-button-primary"
                disabled={save.isPending}
              >
                <Save size={15} />
                Salvar loja
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}

export function TransactionsPage() {
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [model, setModel] = useState("");
  const [balance, setBalance] = useState("");
  const [startDate, setStartDate] = useState("");
  const [endDate, setEndDate] = useState("");
  const [storeId, setStoreId] = useState("");
  const stores = useQuery({
    queryKey: ["admin-store-options"],
    queryFn: () => adminFetch<PageData<StoreItem>>("stores?pageSize=100"),
  });
  const params = new URLSearchParams({
    page: String(page),
    ...(search && { search }),
    ...(status && { status }),
    ...(model && { model }),
    ...(balance && { balance }),
    ...(startDate && { startDate }),
    ...(endDate && { endDate }),
    ...(storeId && { storeId }),
  });
  const query = useQuery({
    queryKey: ["admin-transactions", page, search, status, model, balance, startDate, endDate, storeId],
    queryFn: () =>
      adminFetch<PageData<TransactionItem>>(`transactions?${params}`),
  });
  return (
    <div className="admin-page">
      <PageHead
        title="Transações"
        description="Consulte todas as vendas com separação explícita entre o modelo atual e o histórico de comissões."
      >
        <a
          className="admin-button"
          href={`/api/admin/transactions/export?${params}`}
        >
          <Download size={15} />
          Exportar CSV
        </a>
      </PageHead>
      <div className="admin-toolbar">
        <Field label="Pesquisar">
          <input
            className="admin-input"
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
            placeholder="Código, cliente ou loja"
          />
        </Field>
        <Field label="Status">
          <select
            className="admin-select"
            value={status}
            onChange={(e) => {
              setStatus(e.target.value);
              setPage(1);
            }}
          >
            <option value="">Todos</option>
            <option value="aprovado">Aprovado</option>
            <option value="pendente">Pendente</option>
            <option value="pagamento_pendente">Pagamento pendente</option>
            <option value="cancelado">Cancelado</option>
          </select>
        </Field>
        <Field label="Modelo">
          <select
            className="admin-select"
            value={model}
            onChange={(e) => {
              setModel(e.target.value);
              setPage(1);
            }}
          >
            <option value="">Todos</option>
            <option value="subscription_cashback">Atual</option>
            <option value="commission_legacy">Legado</option>
          </select>
        </Field>
        <Field label="Saldo">
          <select
            className="admin-select"
            value={balance}
            onChange={(e) => {
              setBalance(e.target.value);
              setPage(1);
            }}
          >
            <option value="">Todas</option>
            <option value="used">Com saldo usado</option>
          </select>
        </Field>
        <Field label="Data inicial">
          <input className="admin-input" type="date" value={startDate} onChange={(event) => { setStartDate(event.target.value); setPage(1); }} />
        </Field>
        <Field label="Data final">
          <input className="admin-input" type="date" value={endDate} onChange={(event) => { setEndDate(event.target.value); setPage(1); }} />
        </Field>
        <Field label="Loja">
          <select className="admin-select" value={storeId} onChange={(event) => { setStoreId(event.target.value); setPage(1); }}>
            <option value="">Todas as lojas</option>
            {stores.data?.items.map((store) => <option value={store.id} key={store.id}>{store.name}</option>)}
          </select>
        </Field>
      </div>
      {query.isLoading ? (
        <LoadingState />
      ) : query.isError || !query.data ? (
        <ErrorState error={query.error} retry={() => query.refetch()} />
      ) : (
        <section className="admin-panel">
          {query.data.items.length ? (
            <>
              <div className="admin-table-wrap">
                <table className="admin-table">
                  <thead>
                    <tr>
                      <th>Compra</th>
                      <th>Cliente / loja</th>
                      <th>Valores</th>
                      <th>Cashback</th>
                      <th>Modelo</th>
                      <th>Status</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    {query.data.items.map((item) => (
                      <tr key={item.id}>
                        <td>
                          <strong>#{item.id}</strong>
                          <small className="admin-code">{item.code}</small>
                        </td>
                        <td>
                          <strong>{item.customerName}</strong>
                          <small>
                            {item.storeName} · {dateTime(item.occurredAt)}
                          </small>
                        </td>
                        <td>
                          <strong>
                            {moneyFromCents(item.grossAmountCents)}
                          </strong>
                          <small>
                            Saldo {moneyFromCents(item.balanceUsedCents)} · pago{" "}
                            {moneyFromCents(item.paidAmountCents)}
                          </small>
                        </td>
                        <td>{moneyFromCents(item.cashbackAmountCents)}</td>
                        <td>
                          <span
                            className={`admin-model ${item.financialModel === "commission_legacy" ? "legacy" : ""}`}
                          >
                            {item.financialModel === "commission_legacy"
                              ? "Legado"
                              : "Atual"}
                          </span>
                        </td>
                        <td>
                          <Status value={item.status} />
                        </td>
                        <td>
                          <Link
                            className="admin-button"
                            href={`/admin/transacao/${item.id}`}
                          >
                            <Eye size={14} />
                            Detalhes
                          </Link>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <PaginationBar value={query.data.pagination} onPage={setPage} />
            </>
          ) : (
            <EmptyState />
          )}
        </section>
      )}
    </div>
  );
}

export function TransactionDetailsPage({ id }: { id: number }) {
  const context = useAdminContext();
  const client = useQueryClient();
  const query = useQuery({
    queryKey: ["admin-transaction", id],
    queryFn: () =>
      adminFetch<
        { item: TransactionItem } & { dataState: string; generatedAt: string }
      >(`transactions/${id}`),
  });
  const action = useMutation({
    mutationFn: ({
      path,
      payload,
    }: {
      path: string;
      payload: Record<string, string>;
    }) =>
      adminFetch(path, {
        method: "POST",
        headers: mutationHeaders(context.csrfToken, true),
        body: JSON.stringify(payload),
      }),
    onSuccess: async () => {
      await client.invalidateQueries({ queryKey: ["admin-transaction", id] });
      await client.invalidateQueries({ queryKey: ["admin-transactions"] });
    },
  });
  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data)
    return <ErrorState error={query.error} retry={() => query.refetch()} />;
  const item = query.data.item;
  function legacyStatus(status: string) {
    const notes = window.prompt("Observação da alteração:", "") ?? null;
    if (notes !== null)
      action.mutate({
        path: `transactions/${id}/status`,
        payload: { status, notes },
      });
  }
  function reverse() {
    const reason = window.prompt("Motivo obrigatório do estorno:", "") ?? null;
    if (reason)
      action.mutate({
        path: `transactions/${id}/reverse`,
        payload: { reason },
      });
  }
  return (
    <div className="admin-page">
      <PageHead
        title={`Transação #${item.id}`}
        description={`${item.customerName} em ${item.storeName} · ${dateTime(item.occurredAt)}`}
      >
        <Link className="admin-button" href="/admin/transacoes">
          Voltar
        </Link>
        {item.financialModel === "commission_legacy" &&
          ["pendente", "pagamento_pendente"].includes(item.status) && (
            <>
              <button
                className="admin-button admin-button-success"
                onClick={() => legacyStatus("aprovado")}
              >
                <Check size={15} />
                Aprovar legado
              </button>
              <button
                className="admin-button admin-button-danger"
                onClick={() => legacyStatus("cancelado")}
              >
                <XCircle size={15} />
                Cancelar
              </button>
            </>
          )}
        {item.financialModel === "subscription_cashback" &&
          item.status === "aprovado" && (
            <button
              className="admin-button admin-button-danger"
              onClick={reverse}
            >
              <RotateCcw size={15} />
              Estornar
            </button>
          )}
      </PageHead>
      {mutationMessage(action, "A transação e seus saldos foram atualizados.")}
      <section className="admin-grid admin-grid-4">
        <Stat
          label="Valor da compra"
          value={moneyFromCents(item.grossAmountCents)}
          note={`Pago: ${moneyFromCents(item.paidAmountCents)}`}
          icon={<ShoppingBag size={18} />}
        />
        <Stat
          label="Saldo usado"
          value={moneyFromCents(item.balanceUsedCents)}
          note="Restaurado em estorno válido"
          icon={<CircleDollarSign size={18} />}
        />
        <Stat
          label="Cashback"
          value={moneyFromCents(item.cashbackAmountCents)}
          note="Benefício do cliente"
          icon={<BadgeDollarSign size={18} />}
        />
        <Stat
          label="Status"
          value={item.status}
          note={
            item.financialModel === "commission_legacy"
              ? "Modelo legado"
              : "Modelo atual"
          }
          icon={<ReceiptText size={18} />}
        />
      </section>
      <section className="admin-grid admin-grid-2">
        <div className="admin-panel">
          <div className="admin-panel-head">
            <div>
              <h3>Detalhes</h3>
              <p>Dados persistidos da venda</p>
            </div>
          </div>
          <div className="admin-details">
            <Detail label="Código" value={item.code} />
            <Detail
              label="Cliente"
              value={`${item.customerName}${item.customerEmail ? ` · ${item.customerEmail}` : ""}`}
            />
            <Detail label="Loja" value={item.storeName} />
            <Detail label="Descrição" value={item.description || "—"} />
            <Detail label="Modelo" value={item.financialModel} />
            <Detail label="Status" value={item.status} />
          </div>
        </div>
        <div className="admin-panel">
          <div className="admin-panel-head">
            <div>
              <h3>Distribuição financeira</h3>
              <p>Valores em centavos normalizados pela API</p>
            </div>
          </div>
          <div className="admin-details">
            <Detail
              label="Cashback cliente"
              value={moneyFromCents(item.cashbackAmountCents)}
            />
            <Detail
              label="Admin legado"
              value={moneyFromCents(item.adminAmountCents ?? 0)}
            />
            <Detail
              label="Loja legado"
              value={moneyFromCents(item.storeAmountCents ?? 0)}
            />
            <Detail
              label="Valor efetivamente pago"
              value={moneyFromCents(item.paidAmountCents)}
            />
          </div>
        </div>
      </section>
      <section className="admin-panel">
        <div className="admin-panel-head">
          <div>
            <h3>Movimentações de saldo</h3>
            <p>Créditos, usos e estornos relacionados</p>
          </div>
        </div>
        {item.movements?.length ? (
          <div className="admin-table-wrap">
            <table className="admin-table">
              <thead>
                <tr>
                  <th>Tipo</th>
                  <th>Valor</th>
                  <th>Saldo anterior</th>
                  <th>Saldo atual</th>
                  <th>Descrição</th>
                  <th>Data</th>
                </tr>
              </thead>
              <tbody>
                {item.movements.map((move) => (
                  <tr key={move.id}>
                    <td>
                      <Status value={move.type} />
                    </td>
                    <td>{moneyFromCents(move.amountCents)}</td>
                    <td>{moneyFromCents(move.previousCents)}</td>
                    <td>
                      <strong>{moneyFromCents(move.currentCents)}</strong>
                    </td>
                    <td>{move.description}</td>
                    <td>{dateTime(move.occurredAt)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <EmptyState title="Sem movimentações relacionadas" />
        )}
      </section>
    </div>
  );
}

function Detail({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="admin-detail">
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  );
}

export function FinancePage() {
  const context = useAdminContext();
  const client = useQueryClient();
  const [tab, setTab] = useState<"commission" | "balance_refund">("commission");
  const [status, setStatus] = useState("");
  const query = useQuery({
    queryKey: ["admin-finance", status],
    queryFn: () =>
      adminFetch<FinanceData>(`finance${status ? `?status=${status}` : ""}`),
  });
  const mutation = useMutation({
    mutationFn: ({
      item,
      decision,
    }: {
      item: FinanceData["commissionPayments"][number];
      decision: string;
    }) => {
      const notes = window.prompt("Observação administrativa:", "") ?? "";
      return adminFetch(`finance/${item.kind}/${item.id}`, {
        method: "POST",
        headers: mutationHeaders(context.csrfToken, true),
        body: JSON.stringify({ decision, notes }),
      });
    },
    onSuccess: () => client.invalidateQueries({ queryKey: ["admin-finance"] }),
  });
  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data)
    return <ErrorState error={query.error} retry={() => query.refetch()} />;
  const items =
    tab === "commission"
      ? query.data.commissionPayments
      : query.data.balancePayments;
  return (
    <div className="admin-page">
      <PageHead
        title="Financeiro legado"
        description="Histórico preservado para auditoria. Somente pendências antigas e íntegras podem ser concluídas."
      />
      <div className="admin-alert">
        <strong>Nenhum registro novo será criado.</strong>Vendas do modelo por
        assinatura não geram comissão, repasse ou reembolso.
      </div>
      <section className="admin-grid admin-grid-4">
        <Stat
          label="Comissões pagas"
          value={moneyFromCents(query.data.summary.commissionPaidCents)}
          note="Histórico legado"
          icon={<Check size={18} />}
        />
        <Stat
          label="Comissões pendentes"
          value={moneyFromCents(query.data.summary.commissionPendingCents)}
          note="Exigem integridade referencial"
          icon={<Clock3 size={18} />}
        />
        <Stat
          label="Reembolsos pagos"
          value={moneyFromCents(query.data.summary.balancePaidCents)}
          note="Histórico legado"
          icon={<CircleDollarSign size={18} />}
        />
        <Stat
          label="Reembolsos pendentes"
          value={moneyFromCents(query.data.summary.balancePendingCents)}
          note="Exigem revisão"
          icon={<ShieldCheck size={18} />}
        />
      </section>
      {mutationMessage(mutation, "A pendência foi processada e auditada.")}
      <section className="admin-panel">
        <div className="admin-panel-head">
          <div className="admin-tabs">
            <button
              className={`admin-tab ${tab === "commission" ? "active" : ""}`}
              onClick={() => setTab("commission")}
            >
              Pagamentos de comissão
            </button>
            <button
              className={`admin-tab ${tab === "balance_refund" ? "active" : ""}`}
              onClick={() => setTab("balance_refund")}
            >
              Reembolsos de saldo
            </button>
          </div>
          <select
            className="admin-select"
            style={{ width: 170 }}
            value={status}
            onChange={(e) => setStatus(e.target.value)}
          >
            <option value="">Todos os status</option>
            <option value="pendente">Pendentes</option>
            <option value="aprovado">Aprovados</option>
            <option value="rejeitado">Rejeitados</option>
          </select>
        </div>
        {items.length ? (
          <div className="admin-table-wrap">
            <table className="admin-table">
              <thead>
                <tr>
                  <th>Registro</th>
                  <th>Loja</th>
                  <th>Valor</th>
                  <th>Método</th>
                  <th>Status</th>
                  <th>Data</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody>
                {items.map((item) => (
                  <tr key={`${item.kind}-${item.id}`}>
                    <td>
                      <strong>#{item.id}</strong>
                      <small>
                        {item.kind === "commission"
                          ? `${item.transactionCount ?? 0} transações`
                          : "Reembolso legado"}
                      </small>
                    </td>
                    <td>{item.storeName}</td>
                    <td>
                      <strong>{moneyFromCents(item.amountCents)}</strong>
                    </td>
                    <td>
                      {item.method}
                      <small>{item.reference ?? "Sem referência"}</small>
                    </td>
                    <td>
                      <Status value={item.status} />
                      {item.reviewRequired && (
                        <small className="admin-review-warning">
                          Revisão: {item.reviewReason ?? "inconsistência detectada"}
                        </small>
                      )}
                    </td>
                    <td>{dateTime(item.createdAt)}</td>
                    <td>
                      {["pendente", "em_processamento"].includes(
                        item.status,
                      ) ? (
                        <div className="admin-actions">
                          <button
                            className="admin-button admin-button-success"
                            onClick={() =>
                              mutation.mutate({ item, decision: "approve" })
                            }
                            disabled={item.reviewRequired || mutation.isPending}
                            title={
                              item.reviewRequired
                                ? "A aprovação foi bloqueada até a correção da inconsistência."
                                : undefined
                            }
                          >
                            <Check size={14} />
                            Aprovar
                          </button>
                          <button
                            className="admin-button admin-button-danger"
                            onClick={() =>
                              mutation.mutate({ item, decision: "reject" })
                            }
                            disabled={mutation.isPending}
                          >
                            <XCircle size={14} />
                            Rejeitar
                          </button>
                        </div>
                      ) : (
                        "—"
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <EmptyState />
        )}
      </section>
    </div>
  );
}

type ReportsData = {
  dataState: "ready" | "empty";
  generatedAt: string;
  monthly: Array<{
    month: string;
    model: string;
    salesCount: number;
    grossAmountCents: number;
    cashbackAmountCents: number;
  }>;
  stores: Array<{
    id: number;
    name: string;
    salesCount: number;
    grossAmountCents: number;
    cashbackAmountCents: number;
  }>;
};
export function ReportsPage() {
  const [startDate, setStart] = useState("");
  const [endDate, setEnd] = useState("");
  const [storeId, setStoreId] = useState("");
  const stores = useQuery({
    queryKey: ["admin-store-options"],
    queryFn: () => adminFetch<PageData<StoreItem>>("stores?pageSize=100"),
  });
  const params = new URLSearchParams({
    ...(startDate && { startDate }),
    ...(endDate && { endDate }),
    ...(storeId && { storeId }),
  });
  const query = useQuery({
    queryKey: ["admin-reports", startDate, endDate, storeId],
    queryFn: () => adminFetch<ReportsData>(`reports?${params}`),
  });
  const totals = useMemo(
    () =>
      query.data?.monthly.reduce(
        (sum, row) => ({
          sales: sum.sales + row.salesCount,
          gross: sum.gross + row.grossAmountCents,
          cashback: sum.cashback + row.cashbackAmountCents,
        }),
        { sales: 0, gross: 0, cashback: 0 },
      ) ?? { sales: 0, gross: 0, cashback: 0 },
    [query.data],
  );
  function exportCsv() {
    if (!query.data) return;
    const rows = [
      ["Mês", "Modelo", "Vendas", "Valor centavos", "Cashback centavos"],
      ...query.data.monthly.map((row) => [
        row.month,
        row.model,
        String(row.salesCount),
        String(row.grossAmountCents),
        String(row.cashbackAmountCents),
      ]),
    ];
    const blob = new Blob(
      [
        "\uFEFF" +
          rows
            .map((row) =>
              row
                .map((value) => `"${String(value).replaceAll('"', '""')}"`)
                .join(","),
            )
            .join("\n"),
      ],
      { type: "text/csv;charset=utf-8" },
    );
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement("a");
    anchor.href = url;
    anchor.download = "relatorio-klubecash.csv";
    anchor.click();
    URL.revokeObjectURL(url);
  }
  return (
    <div className="admin-page">
      <PageHead
        title="Relatórios"
        description="Compare o cashback atual com o histórico legado sem misturar receitas ou obrigações."
      >
        <button
          className="admin-button"
          disabled={!query.data}
          onClick={exportCsv}
        >
          <Download size={15} />
          Exportar CSV
        </button>
      </PageHead>
      <div className="admin-toolbar">
        <Field label="Data inicial">
          <input
            className="admin-input"
            type="date"
            value={startDate}
            onChange={(e) => setStart(e.target.value)}
          />
        </Field>
        <Field label="Data final">
          <input
            className="admin-input"
            type="date"
            value={endDate}
            onChange={(e) => setEnd(e.target.value)}
          />
        </Field>
        <Field label="Loja">
          <select className="admin-select" value={storeId} onChange={(event) => setStoreId(event.target.value)}>
            <option value="">Todas as lojas</option>
            {stores.data?.items.map((store) => <option value={store.id} key={store.id}>{store.name}</option>)}
          </select>
        </Field>
      </div>
      {query.isLoading ? (
        <LoadingState />
      ) : query.isError || !query.data ? (
        <ErrorState error={query.error} retry={() => query.refetch()} />
      ) : (
        <>
          <section className="admin-grid admin-grid-3">
            <Stat
              label="Vendas"
              value={number(totals.sales)}
              note="No período selecionado"
              icon={<ReceiptText size={18} />}
            />
            <Stat
              label="Valor movimentado"
              value={moneyFromCents(totals.gross)}
              note="Vendas aprovadas"
              icon={<CircleDollarSign size={18} />}
            />
            <Stat
              label="Cashback"
              value={moneyFromCents(totals.cashback)}
              note="Concedido aos clientes"
              icon={<BadgeDollarSign size={18} />}
            />
          </section>
          <section className="admin-grid admin-grid-2">
            <div className="admin-panel">
              <div className="admin-panel-head">
                <div>
                  <h3>Evolução por modelo</h3>
                  <p>Atual e legado aparecem em séries separadas</p>
                </div>
              </div>
              {query.data.monthly.length ? (
                <div className="admin-table-wrap">
                  <table className="admin-table">
                    <thead>
                      <tr>
                        <th>Mês</th>
                        <th>Modelo</th>
                        <th>Vendas</th>
                        <th>Valor</th>
                        <th>Cashback</th>
                      </tr>
                    </thead>
                    <tbody>
                      {query.data.monthly.map((row) => (
                        <tr key={`${row.month}-${row.model}`}>
                          <td>{monthLabel(row.month)}</td>
                          <td>
                            <span
                              className={`admin-model ${row.model === "commission_legacy" ? "legacy" : ""}`}
                            >
                              {row.model === "commission_legacy"
                                ? "Legado"
                                : "Atual"}
                            </span>
                          </td>
                          <td>{number(row.salesCount)}</td>
                          <td>{moneyFromCents(row.grossAmountCents)}</td>
                          <td>{moneyFromCents(row.cashbackAmountCents)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <EmptyState />
              )}
            </div>
            <div className="admin-panel">
              <div className="admin-panel-head">
                <div>
                  <h3>Lojas por movimentação</h3>
                  <p>Ranking do período</p>
                </div>
              </div>
              {query.data.stores.map((store, index) => (
                <div
                  className="admin-detail"
                  key={store.id}
                  style={{
                    marginBottom: 8,
                    display: "flex",
                    justifyContent: "space-between",
                  }}
                >
                  <span>
                    <strong>
                      {index + 1}. {store.name}
                    </strong>
                    <small>{number(store.salesCount)} vendas</small>
                  </span>
                  <strong>{moneyFromCents(store.grossAmountCents)}</strong>
                </div>
              ))}
            </div>
          </section>
        </>
      )}
    </div>
  );
}

const settingsSchema = z.object({
  customerPercentage: z.coerce.number<number>().min(0).max(100),
  balanceEnabled: z.boolean(),
  minimumUseCents: z.coerce.number<number>().min(0),
  maximumPurchasePercentage: z.coerce.number<number>().min(0).max(100),
  lowBalanceNotification: z.boolean(),
  lowBalanceThresholdCents: z.coerce.number<number>().min(0),
  newTransactionEmail: z.boolean(),
  approvedPaymentEmail: z.boolean(),
  availableBalanceEmail: z.boolean(),
  lowBalanceEmail: z.boolean(),
  expiredBalanceEmail: z.boolean(),
});
type SettingsForm = z.infer<typeof settingsSchema>;
export function SettingsPage() {
  const context = useAdminContext();
  const client = useQueryClient();
  const query = useQuery({
    queryKey: ["admin-settings"],
    queryFn: () => adminFetch<SettingsData>("settings"),
  });
  const form = useForm<SettingsForm>({ resolver: zodResolver(settingsSchema) });
  const loaded = useMemo(
    () =>
      query.data
        ? {
            customerPercentage: query.data.cashback.customerPercentage,
            balanceEnabled: query.data.balance.enabled,
            minimumUseCents: query.data.balance.minimumUseCents,
            maximumPurchasePercentage:
              query.data.balance.maximumPurchasePercentage,
            lowBalanceNotification: query.data.balance.lowBalanceNotification,
            lowBalanceThresholdCents:
              query.data.balance.lowBalanceThresholdCents,
            ...query.data.notifications,
          }
        : null,
    [query.data],
  );
  useEffect(() => {
    if (loaded) form.reset(loaded);
  }, [loaded, form]);
  const save = useMutation({
    mutationFn: (values: SettingsForm) =>
      adminFetch("settings", {
        method: "POST",
        headers: mutationHeaders(context.csrfToken),
        body: JSON.stringify(values),
      }),
    onSuccess: () => client.invalidateQueries({ queryKey: ["admin-settings"] }),
  });
  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data)
    return <ErrorState error={query.error} retry={() => query.refetch()} />;
  return (
    <div className="admin-page">
      <PageHead
        title="Configurações"
        description="Somente controles com efeito real no backend são exibidos como ativos."
      />
      {mutationMessage(
        save,
        "As regras já estão sendo aplicadas nas novas vendas.",
      )}
      <form
        className="admin-form"
        onSubmit={form.handleSubmit((values) => save.mutate(values))}
      >
        <section className="admin-grid admin-grid-2">
          <div className="admin-panel">
            <div className="admin-panel-head">
              <div>
                <h3>Cashback atual</h3>
                <p>A loja financia somente o benefício do cliente</p>
              </div>
            </div>
            <Field label="Porcentagem padrão do cliente">
              <input
                className="admin-input"
                type="number"
                step="0.01"
                {...form.register("customerPercentage")}
              />
            </Field>
            <div className="admin-alert" style={{ marginTop: 14 }}>
              <strong>Campos legados em somente leitura</strong>Admin:{" "}
              {query.data.cashback.legacyAdminPercentage}% · Loja:{" "}
              {query.data.cashback.legacyStorePercentage}%. Novas vendas sempre
              persistem ambos como zero.
            </div>
          </div>
          <div className="admin-panel">
            <div className="admin-panel-head">
              <div>
                <h3>Uso de saldo</h3>
                <p>Regras agora validadas no serviço de vendas</p>
              </div>
            </div>
            <div className="admin-form-grid">
              <label className="admin-check admin-field-full">
                <input type="checkbox" {...form.register("balanceEnabled")} />
                Permitir uso de saldo
              </label>
              <Field label="Uso mínimo (centavos)">
                <input
                  className="admin-input"
                  type="number"
                  {...form.register("minimumUseCents")}
                />
              </Field>
              <Field label="Máximo da compra (%)">
                <input
                  className="admin-input"
                  type="number"
                  {...form.register("maximumPurchasePercentage")}
                />
              </Field>
              <label className="admin-check admin-field-full">
                <input
                  type="checkbox"
                  {...form.register("lowBalanceNotification")}
                />
                Notificar saldo baixo
              </label>
              <Field label="Limite de saldo baixo (centavos)">
                <input
                  className="admin-input"
                  type="number"
                  {...form.register("lowBalanceThresholdCents")}
                />
              </Field>
            </div>
          </div>
        </section>
        <section className="admin-panel">
          <div className="admin-panel-head">
            <div>
              <h3>Notificações por e-mail</h3>
              <p>Preferências conectadas à fila persistente</p>
            </div>
          </div>
          <div className="admin-grid admin-grid-3">
            {(
              [
                ["newTransactionEmail", "Nova transação"],
                ["approvedPaymentEmail", "Pagamento legado aprovado"],
                ["availableBalanceEmail", "Saldo disponível"],
                ["lowBalanceEmail", "Saldo baixo"],
                ["expiredBalanceEmail", "Saldo expirado"],
              ] as const
            ).map(([name, label]) => (
              <label className="admin-check admin-detail" key={name}>
                <input type="checkbox" {...form.register(name)} />
                {label}
              </label>
            ))}
          </div>
        </section>
        <div className="admin-actions" style={{ justifyContent: "flex-end" }}>
          <button
            className="admin-button admin-button-primary"
            disabled={save.isPending}
          >
            <Save size={15} />
            Salvar configurações
          </button>
        </div>
      </form>
    </div>
  );
}

export function SubscriptionsPage() {
  const context = useAdminContext();
  const client = useQueryClient();
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [assignOpen, setAssignOpen] = useState(false);
  const [assignment, setAssignment] = useState({
    storeId: "",
    planSlug: "",
    cycle: "monthly",
    trialDays: "",
  });
  const params = new URLSearchParams({
    page: String(page),
    ...(search && { search }),
    ...(status && { status }),
  });
  const query = useQuery({
    queryKey: ["admin-subscriptions", page, search, status],
    queryFn: () =>
      adminFetch<PageData<SubscriptionItem>>(`subscriptions?${params}`),
  });
  const stores = useQuery({
    queryKey: ["admin-store-options"],
    queryFn: () =>
      adminFetch<PageData<StoreItem>>("stores?pageSize=100&status=aprovado"),
  });
  const plans = useQuery({
    queryKey: ["admin-plans"],
    queryFn: () => adminFetch<{ items: PlanItem[] }>("plans"),
  });
  const assign = useMutation({
    mutationFn: () =>
      adminFetch("subscriptions", {
        method: "POST",
        headers: mutationHeaders(context.csrfToken, true),
        body: JSON.stringify({
          storeId: Number(assignment.storeId),
          planSlug: assignment.planSlug,
          cycle: assignment.cycle,
          trialDays: assignment.trialDays ? Number(assignment.trialDays) : null,
        }),
      }),
    onSuccess: () => {
      client.invalidateQueries({ queryKey: ["admin-subscriptions"] });
      setAssignOpen(false);
    },
  });
  return (
    <div className="admin-page">
      <PageHead
        title="Assinaturas"
        description="Atribua planos por código ou manualmente. Faturas antigas permanecem apenas para consulta."
      >
        <button
          className="admin-button admin-button-primary"
          onClick={() => setAssignOpen(true)}
        >
          <Plus size={15} />
          Atribuir plano
        </button>
      </PageHead>
      <div className="admin-toolbar">
        <Field label="Pesquisar">
          <input
            className="admin-input"
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
            placeholder="Loja, e-mail ou plano"
          />
        </Field>
        <Field label="Status">
          <select
            className="admin-select"
            value={status}
            onChange={(e) => {
              setStatus(e.target.value);
              setPage(1);
            }}
          >
            <option value="">Todos</option>
            <option value="trial">Trial</option>
            <option value="ativa">Ativa</option>
            <option value="inadimplente">Inadimplente</option>
            <option value="suspensa">Suspensa</option>
            <option value="cancelada">Cancelada</option>
          </select>
        </Field>
      </div>
      {query.isLoading ? (
        <LoadingState />
      ) : query.isError || !query.data ? (
        <ErrorState error={query.error} retry={() => query.refetch()} />
      ) : (
        <section className="admin-panel">
          {query.data.items.length ? (
            <>
              <div className="admin-table-wrap">
                <table className="admin-table">
                  <thead>
                    <tr>
                      <th>Loja</th>
                      <th>Plano</th>
                      <th>Ciclo</th>
                      <th>Período</th>
                      <th>Status</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    {query.data.items.map((item) => (
                      <tr key={item.id}>
                        <td>
                          <strong>{item.storeName}</strong>
                          <small>Loja #{item.storeId}</small>
                        </td>
                        <td>
                          {item.planName}
                          <small>{item.planSlug}</small>
                        </td>
                        <td>{item.cycle === "yearly" ? "Anual" : "Mensal"}</td>
                        <td>
                          {dateTime(item.periodStart)}
                          <small>até {dateTime(item.periodEnd)}</small>
                        </td>
                        <td>
                          <Status value={item.status} />
                        </td>
                        <td>
                          <Link
                            className="admin-button"
                            href={`/admin/assinaturas/${item.id}`}
                          >
                            <Eye size={14} />
                            Detalhes
                          </Link>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <PaginationBar value={query.data.pagination} onPage={setPage} />
            </>
          ) : (
            <EmptyState />
          )}
        </section>
      )}
      {assignOpen && (
        <Modal
          title="Atribuir plano"
          subtitle="Nenhuma fatura será gerada automaticamente."
          onClose={() => setAssignOpen(false)}
        >
          <form
            className="admin-form"
            onSubmit={(e) => {
              e.preventDefault();
              assign.mutate();
            }}
          >
            <Field label="Loja">
              <select
                className="admin-select"
                required
                value={assignment.storeId}
                onChange={(e) =>
                  setAssignment({ ...assignment, storeId: e.target.value })
                }
              >
                <option value="">Selecione</option>
                {stores.data?.items.map((store) => (
                  <option value={store.id} key={store.id}>
                    {store.name}
                  </option>
                ))}
              </select>
            </Field>
            <Field label="Plano">
              <select
                className="admin-select"
                required
                value={assignment.planSlug}
                onChange={(e) => {
                  const plan = plans.data?.items.find(
                    (item) => item.slug === e.target.value,
                  );
                  setAssignment({
                    ...assignment,
                    planSlug: e.target.value,
                    cycle: plan?.recurrence === "yearly" ? "yearly" : "monthly",
                  });
                }}
              >
                <option value="">Selecione</option>
                {plans.data?.items
                  .filter((plan) => plan.active)
                  .map((plan) => (
                    <option value={plan.slug} key={plan.id}>
                      {plan.name} {plan.code ? `(${plan.code})` : ""}
                    </option>
                  ))}
              </select>
            </Field>
            <div className="admin-form-grid">
              <Field label="Ciclo">
                <select
                  className="admin-select"
                  value={assignment.cycle}
                  onChange={(e) =>
                    setAssignment({ ...assignment, cycle: e.target.value })
                  }
                >
                  <option value="monthly">Mensal</option>
                  <option value="yearly">Anual</option>
                </select>
              </Field>
              <Field label="Trial em dias">
                <input
                  className="admin-input"
                  type="number"
                  min="0"
                  max="90"
                  value={assignment.trialDays}
                  onChange={(e) =>
                    setAssignment({ ...assignment, trialDays: e.target.value })
                  }
                />
              </Field>
            </div>
            {mutationMessage(assign, "")}
            <div className="admin-modal-actions">
              <button
                type="button"
                className="admin-button"
                onClick={() => setAssignOpen(false)}
              >
                Cancelar
              </button>
              <button
                className="admin-button admin-button-primary"
                disabled={assign.isPending}
              >
                Atribuir plano
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}

export function SubscriptionDetailsPage({ id }: { id: number }) {
  const context = useAdminContext();
  const client = useQueryClient();
  const query = useQuery({
    queryKey: ["admin-subscription", id],
    queryFn: () =>
      adminFetch<{ item: SubscriptionItem }>(`subscriptions/${id}`),
  });
  const plans = useQuery({
    queryKey: ["admin-plans"],
    queryFn: () => adminFetch<{ items: PlanItem[] }>("plans"),
  });
  const action = useMutation({
    mutationFn: (next: "suspend" | "cancel") => {
      const current = query.data?.item;
      if (!current) throw new Error("Assinatura não encontrada no estado atual.");
      return adminFetch(`subscriptions/${id}/status`, {
        method: "POST",
        headers: mutationHeaders(context.csrfToken, true),
        body: JSON.stringify({ action: next, updatedAt: current.updatedAt }),
      });
    },
    onSuccess: () => {
      client.invalidateQueries({ queryKey: ["admin-subscription", id] });
      client.invalidateQueries({ queryKey: ["admin-subscriptions"] });
    },
  });
  const changePlan = useMutation({
    mutationFn: () => {
      const current = query.data?.item;
      if (!current) throw new Error("Assinatura não encontrada no estado atual.");
      const choices = plans.data?.items.filter((plan) => plan.active) ?? [];
      const slug = window.prompt(`Novo plano (${choices.map((plan) => plan.slug).join(", ")}):`, current.planSlug);
      if (!slug) throw new Error("Troca de plano cancelada.");
      if (!choices.some((plan) => plan.slug === slug)) throw new Error("Plano ativo inválido.");
      return adminFetch("subscriptions", {
        method: "POST",
        headers: mutationHeaders(context.csrfToken, true),
        body: JSON.stringify({ storeId: current.storeId, planSlug: slug, cycle: current.cycle, existingSubscriptionId: current.id, updatedAt: current.updatedAt }),
      });
    },
    onSuccess: () => {
      client.invalidateQueries({ queryKey: ["admin-subscription", id] });
      client.invalidateQueries({ queryKey: ["admin-subscriptions"] });
    },
  });
  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data)
    return <ErrorState error={query.error} retry={() => query.refetch()} />;
  const item = query.data.item;
  return (
    <div className="admin-page">
      <PageHead
        title={item.storeName}
        description={`${item.planName} · ${item.cycle === "yearly" ? "ciclo anual" : "ciclo mensal"}`}
      >
        <Link className="admin-button" href="/admin/assinaturas">
          Voltar
        </Link>
        {item.status !== "cancelada" && (
          <button className="admin-button" onClick={() => changePlan.mutate()} disabled={!plans.data || changePlan.isPending}>
            Trocar plano
          </button>
        )}
        {item.status !== "suspensa" && item.status !== "cancelada" && (
          <button
            className="admin-button"
            onClick={() => action.mutate("suspend")}
          >
            Suspender
          </button>
        )}
        {item.status !== "cancelada" && (
          <button
            className="admin-button admin-button-danger"
            onClick={() => action.mutate("cancel")}
          >
            Cancelar
          </button>
        )}
      </PageHead>
      {mutationMessage(action, "A assinatura foi atualizada.")}
      {mutationMessage(changePlan, "O plano da assinatura foi alterado sem gerar fatura.")}
      <section className="admin-grid admin-grid-2">
        <div className="admin-panel">
          <div className="admin-panel-head">
            <div>
              <h3>Assinatura</h3>
              <p>Estado atual no backend</p>
            </div>
            <Status value={item.status} />
          </div>
          <div className="admin-details">
            <Detail label="Plano" value={item.planName} />
            <Detail label="Loja" value={item.storeName} />
            <Detail label="Ciclo" value={item.cycle} />
            <Detail
              label="Trial até"
              value={item.trialEnd ? dateTime(item.trialEnd) : "Sem trial"}
            />
            <Detail label="Início" value={dateTime(item.periodStart)} />
            <Detail label="Fim" value={dateTime(item.periodEnd)} />
          </div>
        </div>
        <div className="admin-panel">
          <div className="admin-panel-head">
            <div>
              <h3>Regra comercial</h3>
              <p>Checkout permanece desativado</p>
            </div>
          </div>
          <div className="admin-alert admin-alert-success">
            <strong>Ativação por código ou atribuição.</strong>Não há geração de
            fatura, PIX ou cartão no novo Admin.
          </div>
        </div>
      </section>
      <section className="admin-panel">
        <div className="admin-panel-head">
          <div>
            <h3>Faturas históricas</h3>
            <p>Somente leitura</p>
          </div>
        </div>
        {item.invoices?.length ? (
          <div className="admin-table-wrap">
            <table className="admin-table">
              <thead>
                <tr>
                  <th>Número</th>
                  <th>Valor</th>
                  <th>Status</th>
                  <th>Vencimento</th>
                  <th>Pagamento</th>
                </tr>
              </thead>
              <tbody>
                {item.invoices.map((invoice) => (
                  <tr key={invoice.id}>
                    <td className="admin-code">{invoice.number}</td>
                    <td>{moneyFromCents(invoice.amountCents)}</td>
                    <td>
                      <Status value={invoice.status} />
                    </td>
                    <td>{dateTime(invoice.dueDate)}</td>
                    <td>{invoice.paidAt ? dateTime(invoice.paidAt) : "—"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <EmptyState title="Nenhuma fatura histórica" />
        )}
      </section>
    </div>
  );
}

export function PlansPage() {
  const context = useAdminContext();
  const client = useQueryClient();
  const [editing, setEditing] = useState<PlanItem | undefined>();
  const query = useQuery({
    queryKey: ["admin-plans"],
    queryFn: () => adminFetch<{ items: PlanItem[] }>("plans"),
  });
  const save = useMutation({
    mutationFn: (plan: PlanItem) =>
      adminFetch(`plans/${plan.id}`, {
        method: "PATCH",
        headers: mutationHeaders(context.csrfToken),
        body: JSON.stringify(plan),
      }),
    onSuccess: () => {
      client.invalidateQueries({ queryKey: ["admin-plans"] });
      setEditing(undefined);
    },
  });
  return (
    <div className="admin-page">
      <PageHead
        title="Planos e códigos"
        description="Gerencie benefícios, preços de referência e códigos usados pelo lojista para ativação."
      />
      {query.isLoading ? (
        <LoadingState />
      ) : query.isError || !query.data ? (
        <ErrorState error={query.error} retry={() => query.refetch()} />
      ) : (
        <section className="admin-grid admin-grid-3">
          {query.data.items.map((plan) => (
            <article className="admin-panel" key={plan.id}>
              <div className="admin-panel-head">
                <div>
                  <h3>{plan.name}</h3>
                  <p>{plan.slug}</p>
                </div>
                <Status value={plan.active ? "ativo" : "inativo"} />
              </div>
              <strong style={{ fontSize: 23 }}>
                {moneyFromCents(plan.monthlyPriceCents)}
                <small style={{ fontSize: 10, color: "var(--admin-muted)" }}>
                  /mês
                </small>
              </strong>
              <div className="admin-detail" style={{ margin: "15px 0" }}>
                <span>Código de resgate</span>
                <strong className="admin-code">
                  {plan.code ?? "Não configurado"}
                </strong>
              </div>
              <p
                style={{
                  color: "var(--admin-muted)",
                  fontSize: 11,
                  minHeight: 36,
                }}
              >
                {plan.description}
              </p>
              <button className="admin-button" onClick={() => setEditing(plan)}>
                <Edit3 size={14} />
                Editar plano
              </button>
            </article>
          ))}
        </section>
      )}
      {editing && (
        <Modal
          title={`Editar ${editing.name}`}
          subtitle="O código deve ser único e pode ser fornecido ao lojista."
          onClose={() => setEditing(undefined)}
        >
          <form
            className="admin-form"
            onSubmit={(e) => {
              e.preventDefault();
              save.mutate(editing);
            }}
          >
            <div className="admin-form-grid">
              <Field label="Nome">
                <input
                  className="admin-input"
                  value={editing.name}
                  onChange={(e) =>
                    setEditing({ ...editing, name: e.target.value })
                  }
                />
              </Field>
              <Field label="Código">
                <input
                  className="admin-input admin-code"
                  value={editing.code ?? ""}
                  onChange={(e) =>
                    setEditing({
                      ...editing,
                      code: e.target.value.toUpperCase(),
                    })
                  }
                />
              </Field>
              <Field label="Preço mensal (centavos)">
                <input
                  className="admin-input"
                  type="number"
                  value={editing.monthlyPriceCents}
                  onChange={(e) =>
                    setEditing({
                      ...editing,
                      monthlyPriceCents: Number(e.target.value),
                    })
                  }
                />
              </Field>
              <Field label="Preço anual (centavos)">
                <input
                  className="admin-input"
                  type="number"
                  value={editing.annualPriceCents}
                  onChange={(e) =>
                    setEditing({
                      ...editing,
                      annualPriceCents: Number(e.target.value),
                    })
                  }
                />
              </Field>
              <Field label="Trial em dias">
                <input
                  className="admin-input"
                  type="number"
                  min="0"
                  max="90"
                  value={editing.trialDays}
                  onChange={(e) =>
                    setEditing({
                      ...editing,
                      trialDays: Number(e.target.value),
                    })
                  }
                />
              </Field>
              <Field label="Recorrência">
                <select
                  className="admin-select"
                  value={editing.recurrence}
                  onChange={(e) =>
                    setEditing({ ...editing, recurrence: e.target.value })
                  }
                >
                  <option value="monthly">Mensal</option>
                  <option value="yearly">Anual</option>
                  <option value="both">Ambos</option>
                </select>
              </Field>
              <Field label="Descrição">
                <textarea
                  className="admin-textarea"
                  value={editing.description ?? ""}
                  onChange={(e) =>
                    setEditing({ ...editing, description: e.target.value })
                  }
                />
              </Field>
              <Field label="Benefícios (um por linha)">
                <textarea
                  className="admin-textarea"
                  value={editing.features.join("\n")}
                  onChange={(e) =>
                    setEditing({
                      ...editing,
                      features: e.target.value.split("\n"),
                    })
                  }
                />
              </Field>
              <label className="admin-check admin-field-full">
                <input
                  type="checkbox"
                  checked={editing.active}
                  onChange={(e) =>
                    setEditing({ ...editing, active: e.target.checked })
                  }
                />
                Plano ativo
              </label>
            </div>
            {mutationMessage(save, "")}
            <div className="admin-modal-actions">
              <button
                type="button"
                className="admin-button"
                onClick={() => setEditing(undefined)}
              >
                Cancelar
              </button>
              <button className="admin-button admin-button-primary">
                <Save size={14} />
                Salvar plano
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}

type CampaignForm = {
  title: string;
  subject: string;
  html: string;
  text: string;
  types: Array<"cliente" | "loja" | "funcionario">;
  status: string;
  registeredAfter: string;
};
const campaignSchema = z.object({
  title: z.string().min(3),
  subject: z.string().min(3),
  html: z.string().min(10),
  text: z.string(),
  types: z.array(z.enum(["cliente", "loja", "funcionario"])).min(1),
  status: z.string(),
  registeredAfter: z.string(),
});
export function CampaignsPage() {
  const context = useAdminContext();
  const client = useQueryClient();
  const [page, setPage] = useState(1);
  const [open, setOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [editingVersion, setEditingVersion] = useState("");
  const query = useQuery({
    queryKey: ["admin-campaigns", page],
    queryFn: () => adminFetch<PageData<CampaignItem>>(`campaigns?page=${page}`),
  });
  const form = useForm<CampaignForm>({
    resolver: zodResolver(campaignSchema),
    defaultValues: {
      title: "",
      subject: "",
      html: "<h1>Olá!</h1><p>Escreva sua mensagem.</p>",
      text: "",
      types: ["cliente"],
      status: "ativo",
      registeredAfter: "",
    },
  });
  const save = useMutation({
    mutationFn: (values: CampaignForm) =>
      adminFetch<{ id: number }>(editingId ? `campaigns/${editingId}` : "campaigns", {
        method: editingId ? "PATCH" : "POST",
        headers: mutationHeaders(context.csrfToken),
        body: JSON.stringify({
          title: values.title,
          subject: values.subject,
          html: values.html,
          text: values.text,
          audience: {
            types: values.types,
            status: values.status,
            registeredAfter: values.registeredAfter,
          },
          updatedAt: editingVersion || undefined,
        }),
      }),
    onSuccess: () => {
      client.invalidateQueries({ queryKey: ["admin-campaigns"] });
      setOpen(false);
      setEditingId(null);
      setEditingVersion("");
      form.reset();
    },
  });
  const loadEditor = useMutation({
    mutationFn: async ({ item, duplicate }: { item: CampaignItem; duplicate: boolean }) => {
      const detail = await adminFetch<{
        item: CampaignItem & {
          html: string;
          text: string;
          audience: { types?: CampaignForm["types"]; status?: string; registeredAfter?: string };
        };
      }>(`campaigns/${item.id}`);
      return { detail: detail.item, duplicate };
    },
    onSuccess: ({ detail, duplicate }) => {
      form.reset({
        title: duplicate ? `Cópia de ${detail.title}` : detail.title,
        subject: detail.subject,
        html: detail.html,
        text: detail.text,
        types: detail.audience.types ?? ["cliente"],
        status: detail.audience.status ?? "",
        registeredAfter: detail.audience.registeredAfter ?? "",
      });
      setEditingId(duplicate ? null : detail.id);
      setEditingVersion(duplicate ? "" : detail.updatedAt);
      setOpen(true);
    },
  });
  const previewAudience = useMutation({
    mutationFn: () => {
      const values = form.getValues();
      return adminFetch<{ recipientCount: number }>("marketing/audience", {
        method: "POST",
        headers: mutationHeaders(context.csrfToken),
        body: JSON.stringify({
          audience: {
            types: values.types,
            status: values.status,
            registeredAfter: values.registeredAfter,
          },
        }),
      });
    },
  });
  const action = useMutation({
    mutationFn: ({
      id,
      kind,
    }: {
      id: number;
      kind: "schedule" | "cancel" | "test";
    }) => {
      const item = query.data?.items.find((campaign) => campaign.id === id);
      if (!item) throw new Error("Campanha não encontrada no estado atual.");
      if (kind === "cancel")
        return adminFetch(`campaigns/${id}/cancel`, {
          method: "POST",
          headers: mutationHeaders(context.csrfToken),
          body: JSON.stringify({ updatedAt: item.updatedAt }),
        });
      if (kind === "test") {
        const email = window.prompt(
          "E-mail que receberá o teste:",
          context.user.email,
        );
        if (!email) throw new Error("Teste cancelado.");
        return adminFetch(`campaigns/${id}/test`, {
          method: "POST",
          headers: mutationHeaders(context.csrfToken),
          body: JSON.stringify({ email, updatedAt: item.updatedAt }),
        });
      }
      const input = window.prompt(
        "Agendar para (AAAA-MM-DD HH:MM):",
        new Date(Date.now() + 3600000)
          .toISOString()
          .slice(0, 16)
          .replace("T", " "),
      );
      if (!input) throw new Error("Agendamento cancelado.");
      return adminFetch(`campaigns/${id}/schedule`, {
        method: "POST",
        headers: mutationHeaders(context.csrfToken, true),
        body: JSON.stringify({ scheduledAt: input, updatedAt: item.updatedAt }),
      });
    },
    onSuccess: () =>
      client.invalidateQueries({ queryKey: ["admin-campaigns"] }),
  });
  return (
    <div className="admin-page">
      <PageHead
        title="Campanhas"
        description="Crie segmentos, agende envios e acompanhe a fila sem disparar mensagens antigas automaticamente."
      >
        <button
          className="admin-button admin-button-primary"
          onClick={() => {
            setEditingId(null);
            setEditingVersion("");
            form.reset();
            setOpen(true);
          }}
        >
          <Plus size={15} />
          Nova campanha
        </button>
      </PageHead>
      <div className="admin-alert">
        <strong>Entrega externa protegida.</strong>O worker só envia quando
        EMAIL_DELIVERY_ENABLED estiver explicitamente habilitado; campanhas
        antigas exigem revisão.
      </div>
      {mutationMessage(action, "A campanha foi atualizada.")}
      {loadEditor.isError && mutationMessage(loadEditor, "")}
      {query.isLoading ? (
        <LoadingState />
      ) : query.isError || !query.data ? (
        <ErrorState error={query.error} retry={() => query.refetch()} />
      ) : (
        <section className="admin-panel">
          {query.data.items.length ? (
            <>
              <div className="admin-table-wrap">
                <table className="admin-table">
                  <thead>
                    <tr>
                      <th>Campanha</th>
                      <th>Público</th>
                      <th>Entregas</th>
                      <th>Agendamento</th>
                      <th>Status</th>
                      <th>Ações</th>
                    </tr>
                  </thead>
                  <tbody>
                    {query.data.items.map((item) => (
                      <tr key={item.id}>
                        <td>
                          <strong>{item.title}</strong>
                          <small>{item.subject}</small>
                        </td>
                        <td>{number(item.totalRecipients)}</td>
                        <td>
                          {number(item.sent)} enviados
                          <small>{number(item.failed)} falhas</small>
                        </td>
                        <td>
                          {item.scheduledAt ? dateTime(item.scheduledAt) : "—"}
                          {item.requiresReview && (
                            <small style={{ color: "var(--admin-red)" }}>
                              Revisão obrigatória
                            </small>
                          )}
                        </td>
                        <td>
                          <Status value={item.status} />
                        </td>
                        <td>
                          <div className="admin-actions">
                            {item.status === "rascunho" && (
                              <button className="admin-button" onClick={() => loadEditor.mutate({ item, duplicate: false })}>
                                <Edit3 size={14} />
                                Editar
                              </button>
                            )}
                            <button className="admin-button" onClick={() => loadEditor.mutate({ item, duplicate: true })}>
                              <Plus size={14} />
                              Duplicar
                            </button>
                            <button
                              className="admin-button"
                              onClick={() =>
                                action.mutate({ id: item.id, kind: "test" })
                              }
                            >
                              <Send size={14} />
                              Testar
                            </button>
                            {item.status === "rascunho" && (
                              <button
                                className="admin-button"
                                onClick={() =>
                                  action.mutate({
                                    id: item.id,
                                    kind: "schedule",
                                  })
                                }
                              >
                                <Send size={14} />
                                Agendar
                              </button>
                            )}
                            {["rascunho", "agendado"].includes(item.status) && (
                              <button
                                className="admin-button admin-button-danger"
                                onClick={() =>
                                  action.mutate({ id: item.id, kind: "cancel" })
                                }
                              >
                                Cancelar
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <PaginationBar value={query.data.pagination} onPage={setPage} />
            </>
          ) : (
            <EmptyState title="Nenhuma campanha" />
          )}
        </section>
      )}
      {open && (
        <Modal
          title={editingId ? "Editar campanha" : "Nova campanha"}
          subtitle="O segmento será calculado novamente no momento do agendamento."
          onClose={() => {
            setOpen(false);
            setEditingId(null);
            setEditingVersion("");
          }}
        >
          <form
            className="admin-form"
            onSubmit={form.handleSubmit((values) => save.mutate(values))}
          >
            <Field label="Título interno">
              <input className="admin-input" {...form.register("title")} />
            </Field>
            <Field label="Assunto">
              <input className="admin-input" {...form.register("subject")} />
            </Field>
            <Field label="Conteúdo HTML">
              <textarea
                className="admin-textarea"
                style={{ minHeight: 190 }}
                {...form.register("html")}
              />
            </Field>
            <div className="admin-form-grid">
              <div className="admin-field">
                <span>Públicos</span>
                {(["cliente", "loja", "funcionario"] as const).map((type) => (
                  <label className="admin-check" key={type}>
                    <input
                      type="checkbox"
                      value={type}
                      {...form.register("types")}
                    />
                    {type}
                  </label>
                ))}
              </div>
              <div className="admin-form">
                <Field label="Status do usuário">
                  <select className="admin-select" {...form.register("status")}>
                    <option value="">Todos</option>
                    <option value="ativo">Ativos</option>
                    <option value="inativo">Inativos</option>
                  </select>
                </Field>
                <Field label="Cadastrado após">
                  <input
                    className="admin-input"
                    type="date"
                    {...form.register("registeredAfter")}
                  />
                </Field>
              </div>
            </div>
            <div className="admin-actions">
              <button type="button" className="admin-button" onClick={() => previewAudience.mutate()} disabled={previewAudience.isPending}>
                <Users size={14} />
                Calcular público
              </button>
              {previewAudience.data && <span className="admin-code">{number(previewAudience.data.recipientCount)} destinatários válidos e únicos</span>}
            </div>
            {previewAudience.isError && mutationMessage(previewAudience, "")}
            {mutationMessage(save, "")}
            <div className="admin-modal-actions">
              <button
                type="button"
                className="admin-button"
                onClick={() => setOpen(false)}
              >
                Cancelar
              </button>
              <button
                className="admin-button admin-button-primary"
                disabled={save.isPending}
              >
                <Save size={14} />
                Salvar rascunho
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}

export function TemplatesPage() {
  const context = useAdminContext();
  const client = useQueryClient();
  const [editing, setEditing] = useState<TemplateItem | null | undefined>(
    undefined,
  );
  const query = useQuery({
    queryKey: ["admin-templates"],
    queryFn: () => adminFetch<{ items: TemplateItem[] }>("templates"),
  });
  const save = useMutation({
    mutationFn: (item: TemplateItem) =>
      adminFetch(editing ? `templates/${editing.id}` : "templates", {
        method: editing ? "PATCH" : "POST",
        headers: mutationHeaders(context.csrfToken),
        body: JSON.stringify(item),
      }),
    onSuccess: () => {
      client.invalidateQueries({ queryKey: ["admin-templates"] });
      setEditing(undefined);
    },
  });
  const archive = useMutation({
    mutationFn: (id: number) => {
      const item = query.data?.items.find((template) => template.id === id);
      if (!item) throw new Error("Template não encontrado no estado atual.");
      return adminFetch(`templates/${id}`, {
        method: "DELETE",
        headers: mutationHeaders(context.csrfToken),
        body: JSON.stringify({ updatedAt: item.updatedAt }),
      });
    },
    onSuccess: () =>
      client.invalidateQueries({ queryKey: ["admin-templates"] }),
  });
  const draft: TemplateItem = {
    id: 0,
    name: "",
    subject: "",
    html: "<h1>Klube Cash</h1><p>Conteúdo do e-mail.</p>",
    type: "newsletter",
    active: true,
    updatedAt: "",
  };
  return (
    <div className="admin-page">
      <PageHead
        title="Templates de e-mail"
        description="Crie modelos reutilizáveis com prévia segura e arquivamento reversível no banco."
      >
        <button
          className="admin-button admin-button-primary"
          onClick={() => setEditing(null)}
        >
          <Plus size={15} />
          Novo template
        </button>
      </PageHead>
      {archive.isError && mutationMessage(archive, "")}
      {query.isLoading ? (
        <LoadingState />
      ) : query.isError || !query.data ? (
        <ErrorState error={query.error} retry={() => query.refetch()} />
      ) : query.data.items.length ? (
        <section className="admin-grid admin-grid-3">
          {query.data.items.map((item) => (
            <article className="admin-panel" key={item.id}>
              <div className="admin-panel-head">
                <div>
                  <h3>{item.name}</h3>
                  <p>{item.subject || "Sem assunto padrão"}</p>
                </div>
                <Status value={item.active ? "ativo" : "inativo"} />
              </div>
              <div
                className="admin-detail"
                style={{ minHeight: 95, overflow: "hidden" }}
              >
                <span>Prévia textual</span>
                <strong>
                  {item.html
                    .replace(/<[^>]+>/g, " ")
                    .replace(/\s+/g, " ")
                    .slice(0, 130)}
                </strong>
              </div>
              <div className="admin-actions" style={{ marginTop: 13 }}>
                <button
                  className="admin-button"
                  onClick={() => setEditing(item)}
                >
                  <Edit3 size={14} />
                  Editar
                </button>
                <button
                  className="admin-button admin-button-danger"
                  onClick={() =>
                    window.confirm("Arquivar este template?") &&
                    archive.mutate(item.id)
                  }
                >
                  Arquivar
                </button>
              </div>
            </article>
          ))}
        </section>
      ) : (
        <section className="admin-panel">
          <EmptyState title="Nenhum template" />
        </section>
      )}
      {editing !== undefined && (
        <Modal
          title={editing ? "Editar template" : "Novo template"}
          subtitle="Scripts e manipuladores de evento serão removidos no backend."
          onClose={() => setEditing(undefined)}
        >
          {(() => {
            const item = editing ?? draft;
            return (
              <form
                className="admin-form"
                onSubmit={(e) => {
                  e.preventDefault();
                  save.mutate(item);
                }}
              >
                <div className="admin-form-grid">
                  <Field label="Nome">
                    <input
                      className="admin-input"
                      value={item.name}
                      onChange={(e) =>
                        setEditing({ ...item, name: e.target.value })
                      }
                    />
                  </Field>
                  <Field label="Tipo">
                    <select
                      className="admin-select"
                      value={item.type}
                      onChange={(e) =>
                        setEditing({ ...item, type: e.target.value })
                      }
                    >
                      <option value="newsletter">Newsletter</option>
                      <option value="promocional">Promocional</option>
                      <option value="informativo">Informativo</option>
                    </select>
                  </Field>
                  <Field label="Assunto">
                    <input
                      className="admin-input"
                      value={item.subject}
                      onChange={(e) =>
                        setEditing({ ...item, subject: e.target.value })
                      }
                    />
                  </Field>
                  <label className="admin-check">
                    <input
                      type="checkbox"
                      checked={item.active}
                      onChange={(e) =>
                        setEditing({ ...item, active: e.target.checked })
                      }
                    />
                    Template ativo
                  </label>
                  <Field label="HTML">
                    <textarea
                      className="admin-textarea"
                      style={{ minHeight: 220 }}
                      value={item.html}
                      onChange={(e) =>
                        setEditing({ ...item, html: e.target.value })
                      }
                    />
                  </Field>
                  <div className="admin-field">
                    <span>Prévia</span>
                    <iframe
                      title="Prévia do template"
                      sandbox=""
                      srcDoc={item.html}
                      style={{
                        width: "100%",
                        minHeight: 220,
                        background: "white",
                        border: "1px solid var(--admin-border)",
                        borderRadius: 9,
                      }}
                    />
                  </div>
                </div>
                {mutationMessage(save, "")}
                <div className="admin-modal-actions">
                  <button
                    type="button"
                    className="admin-button"
                    onClick={() => setEditing(undefined)}
                  >
                    Cancelar
                  </button>
                  <button className="admin-button admin-button-primary">
                    <Save size={14} />
                    Salvar template
                  </button>
                </div>
              </form>
            );
          })()}
        </Modal>
      )}
    </div>
  );
}

export function AuditPage() {
  const [page, setPage] = useState(1);
  const [action, setAction] = useState("");
  const [entityType, setEntityType] = useState("");
  const params = new URLSearchParams({
    page: String(page),
    ...(action && { action }),
    ...(entityType && { entityType }),
  });
  const query = useQuery({
    queryKey: ["admin-audit", page, action, entityType],
    queryFn: () => adminFetch<PageData<AuditItem>>(`audit?${params}`),
  });
  return (
    <div className="admin-page">
      <PageHead
        title="Auditoria"
        description="Rastreabilidade das ações sensíveis sem registrar senhas, tokens ou comprovantes."
      />
      <div className="admin-toolbar">
        <Field label="Ação">
          <input
            className="admin-input"
            value={action}
            onChange={(e) => {
              setAction(e.target.value);
              setPage(1);
            }}
            placeholder="Ex.: store.status"
          />
        </Field>
        <Field label="Entidade">
          <select
            className="admin-select"
            value={entityType}
            onChange={(e) => {
              setEntityType(e.target.value);
              setPage(1);
            }}
          >
            <option value="">Todas</option>
            <option value="user">Usuário</option>
            <option value="store">Loja</option>
            <option value="transaction">Transação</option>
            <option value="subscription">Assinatura</option>
            <option value="campaign">Campanha</option>
            <option value="settings">Configurações</option>
          </select>
        </Field>
      </div>
      {query.isLoading ? (
        <LoadingState />
      ) : query.isError || !query.data ? (
        <ErrorState error={query.error} retry={() => query.refetch()} />
      ) : (
        <section className="admin-panel">
          {query.data.items.length ? (
            <>
              <div className="admin-table-wrap">
                <table className="admin-table">
                  <thead>
                    <tr>
                      <th>Ação</th>
                      <th>Entidade</th>
                      <th>Administrador</th>
                      <th>Resultado</th>
                      <th>Request ID</th>
                      <th>Data</th>
                    </tr>
                  </thead>
                  <tbody>
                    {query.data.items.map((item) => (
                      <tr key={item.id}>
                        <td>
                          <strong>{item.action}</strong>
                        </td>
                        <td>
                          {item.entityType}
                          <small>#{item.entityId ?? "—"}</small>
                        </td>
                        <td>{item.actorName}</td>
                        <td>
                          <Status value={item.result} />
                        </td>
                        <td className="admin-code">{item.requestId}</td>
                        <td>{dateTime(item.createdAt)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <PaginationBar value={query.data.pagination} onPage={setPage} />
            </>
          ) : (
            <EmptyState title="Nenhuma ação auditada" />
          )}
        </section>
      )}
    </div>
  );
}
