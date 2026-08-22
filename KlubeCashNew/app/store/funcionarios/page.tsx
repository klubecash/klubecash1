"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Edit3, Plus, Search, Trash2, X } from "lucide-react";
import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { storeFetch } from "@/lib/client-api";
import { dateTime, number } from "@/lib/format";
import { EmptyState, ErrorState, LoadingState } from "@/components/store/PageState";
import { useStoreContext } from "@/components/store/StoreProviders";
import type { Pagination } from "@/types/store";

type Employee = {
  id: number;
  name: string;
  email: string;
  phone: string;
  subtype: "gerente" | "financeiro" | "vendedor";
  status: string;
  createdAt: string;
  lastLoginAt: string | null;
};
type EmployeeData = {
  dataState: "ready" | "empty";
  generatedAt: string;
  items: Employee[];
  summary: { total: number; active: number; inactive: number; managers: number; financial: number; sales: number };
  pagination: Pagination;
};
const employeeSchema = z.object({
  name: z.string().trim().min(3, "Informe pelo menos três caracteres.").max(100),
  email: z.email("Informe um e-mail válido."),
  phone: z.string().refine((value) => value === "" || /^\d{10,11}$/.test(value.replace(/\D/g, "")), "Informe um telefone válido."),
  subtype: z.enum(["gerente", "financeiro", "vendedor"]),
  password: z.string(),
});
type EmployeeForm = z.infer<typeof employeeSchema>;
const emptyForm: EmployeeForm = { name: "", email: "", phone: "", subtype: "vendedor", password: "" };

export default function EmployeesPage() {
  const context = useStoreContext();
  const queryClient = useQueryClient();
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [filter, setFilter] = useState("");
  const [editing, setEditing] = useState<Employee | null | undefined>(undefined);
  const form = useForm<EmployeeForm>({ resolver: zodResolver(employeeSchema), defaultValues: emptyForm });
  const query = useQuery({
    queryKey: ["employees", page, filter],
    queryFn: () => storeFetch<EmployeeData>(`employees&page=${page}&search=${encodeURIComponent(filter)}`),
    enabled: context.permissions.manageEmployees,
  });
  useEffect(() => {
    if (editing === undefined) return;
    form.reset(editing ? { name: editing.name, email: editing.email, phone: editing.phone, subtype: editing.subtype, password: "" } : emptyForm);
  }, [editing, form]);

  const save = useMutation({
    mutationFn: (values: EmployeeForm) => {
      if (!editing && values.password.length < 8) {
        throw new Error("A senha provisória deve ter pelo menos oito caracteres.");
      }
      return storeFetch(editing ? `employees/${editing.id}` : "employees", {
        method: editing ? "PATCH" : "POST",
        headers: { "X-CSRF-Token": context.csrfToken },
        body: JSON.stringify({ ...values, csrfToken: context.csrfToken }),
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["employees"] });
      setEditing(undefined);
    },
  });
  const remove = useMutation({
    mutationFn: (id: number) =>
      storeFetch(`employees/${id}`, {
        method: "DELETE",
        headers: { "X-CSRF-Token": context.csrfToken },
        body: JSON.stringify({ csrfToken: context.csrfToken }),
      }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["employees"] }),
  });

  if (!context.permissions.manageEmployees)
    return <div className="store-page"><section className="store-panel store-empty"><h3>Acesso de gestão necessário</h3><p>Apenas o titular e gerentes podem administrar funcionários.</p></section></div>;
  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data)
    return <ErrorState message={query.error?.message ?? "Erro inesperado."} retry={() => query.refetch()} />;
  const data = query.data;

  return (
    <div className="store-page store-stack">
      <section className="store-page-head">
        <div><h2>Sua equipe</h2><p>Cadastre e organize quem pode operar a conta da loja.</p></div>
        <button className="store-button store-button-primary" onClick={() => setEditing(null)}><Plus size={17} /> Novo funcionário</button>
      </section>
      <section className="store-grid store-grid-4">
        <Stat label="Total" value={number(data.summary.total)} />
        <Stat label="Ativos" value={number(data.summary.active)} />
        <Stat label="Inativos" value={number(data.summary.inactive)} />
        <Stat label="Gerentes" value={number(data.summary.managers)} />
      </section>
      <section className="store-panel">
        <div className="store-panel-head">
          <div><h3>Funcionários</h3><p>{number(data.pagination.totalItems)} pessoa(s) cadastrada(s)</p></div>
          <div style={{ display: "flex", gap: 8 }}>
            <input className="store-input" aria-label="Buscar funcionários" placeholder="Nome ou e-mail" value={search} onChange={(event) => setSearch(event.target.value)} onKeyDown={(event) => { if (event.key === "Enter") { setFilter(search); setPage(1); } }} />
            <button className="store-button store-icon-button" onClick={() => { setFilter(search); setPage(1); }}><Search size={16} /></button>
          </div>
        </div>
        {remove.isError && <div className="store-alert store-alert-error">{remove.error.message}</div>}
        {data.items.length ? (
          <div className="store-table-wrap">
            <table className="store-table">
              <thead><tr><th>Funcionário</th><th>Telefone</th><th>Função</th><th>Cadastro</th><th>Status</th><th /></tr></thead>
              <tbody>{data.items.map((item) => (
                <tr key={item.id}>
                  <td><strong>{item.name}</strong><small>{item.email}</small></td>
                  <td>{item.phone || "—"}</td>
                  <td style={{ textTransform: "capitalize" }}>{item.subtype}</td>
                  <td>{dateTime(item.createdAt)}</td>
                  <td><span className={`store-status ${item.status}`}>{item.status}</span></td>
                  <td><div style={{ display: "flex", gap: 6 }}>
                    <button className="store-button store-icon-button" aria-label="Editar" onClick={() => setEditing(item)}><Edit3 size={15} /></button>
                    {context.permissions.deactivateEmployees && item.status === "ativo" && (
                      <button className="store-button store-icon-button store-button-danger" aria-label="Desativar" onClick={() => { if (confirm(`Desativar ${item.name}?`)) remove.mutate(item.id); }}><Trash2 size={15} /></button>
                    )}
                  </div></td>
                </tr>
              ))}</tbody>
            </table>
          </div>
        ) : <EmptyState title="Nenhum funcionário encontrado" message="A integração está ativa. Cadastre a primeira pessoa ou ajuste a busca." />}
        {data.pagination.totalPages > 1 && <div className="store-pagination"><button className="store-button" disabled={page <= 1} onClick={() => setPage(page - 1)}>Anterior</button><span>Página {data.pagination.page} de {data.pagination.totalPages}</span><button className="store-button" disabled={page >= data.pagination.totalPages} onClick={() => setPage(page + 1)}>Próxima</button></div>}
      </section>

      {editing !== undefined && (
        <div className="store-modal-backdrop" role="dialog" aria-modal="true">
          <div className="store-modal">
            <div className="store-modal-head"><h3>{editing ? "Editar funcionário" : "Novo funcionário"}</h3><button className="store-button store-icon-button" onClick={() => setEditing(undefined)}><X size={17} /></button></div>
            <form className="store-form" onSubmit={form.handleSubmit((values) => save.mutate(values))}>
              <div className="store-form-grid">
                <Field label="Nome" error={form.formState.errors.name?.message}><input className="store-input" {...form.register("name")} /></Field>
                <Field label="E-mail" error={form.formState.errors.email?.message}><input className="store-input" type="email" {...form.register("email")} /></Field>
                <Field label="Telefone" error={form.formState.errors.phone?.message}><input className="store-input" {...form.register("phone")} /></Field>
                <Field label="Função" error={form.formState.errors.subtype?.message}>
                  <select className="store-select" {...form.register("subtype")}>
                    <option value="vendedor">Vendedor</option>
                    <option value="financeiro">Financeiro</option>
                    {context.user.type === "loja" && <option value="gerente">Gerente</option>}
                  </select>
                </Field>
                <Field label={editing ? "Nova senha (opcional)" : "Senha provisória"} error={form.formState.errors.password?.message}><input className="store-input" type="password" {...form.register("password")} /></Field>
              </div>
              {save.isError && <div className="store-alert store-alert-error">{save.error.message}</div>}
              <div className="store-form-actions"><button type="button" className="store-button" onClick={() => setEditing(undefined)}>Cancelar</button><button className="store-button store-button-primary" disabled={save.isPending}>{save.isPending ? "Salvando..." : "Salvar funcionário"}</button></div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}

function Stat({ label, value }: { label: string; value: string }) {
  return <div className="store-stat"><span className="store-stat-label">{label}</span><strong className="store-stat-value">{value}</strong></div>;
}
function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
  return <label className="store-field"><span>{label}</span>{children}{error && <small className="store-error">{error}</small>}</label>;
}
