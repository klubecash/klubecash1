"use client";

import Link from "next/link";
import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { storeFetch } from "@/lib/client-api";
import { moneyFromCents } from "@/lib/format";
import { ErrorState, LoadingState } from "@/components/store/PageState";
import { useStoreContext, useStoreView } from "@/components/store/StoreProviders";

type Branch = { id: number; name: string; cnpj: string; activeStaff: number; pendingStaff: number; approvedSales: number; approvedAmountCents: number };
type TeamMember = { id: number; name: string; email: string; accountStatus: string; networkManager: boolean; branches: Array<{ storeId: number; storeName: string; role: string; status: string }> };
type TeamData = { items: TeamMember[]; pagination: { page: number; totalPages: number; totalItems: number } };
type Role = "gerente" | "financeiro" | "vendedor";

export default function NetworkHubPage() {
  const context = useStoreContext();
  const view = useStoreView();
  const client = useQueryClient();
  const [search, setSearch] = useState("");
  const [applied, setApplied] = useState("");
  const [page, setPage] = useState(1);
  const [invite, setInvite] = useState({ email: "", storeId: context.store.id, role: "vendedor" as Role });
  const [message, setMessage] = useState("");
  const overview = useQuery({ queryKey: ["network-overview", context.networkId],
    queryFn: () => storeFetch<{ networkName: string; branches: Branch[]; managers: Array<{ id: number; name: string }> }>("network/overview"), enabled: Boolean(context.canViewNetwork) });
  const team = useQuery({ queryKey: ["network-team", context.networkId, applied, page],
    queryFn: () => storeFetch<TeamData>(`network/team&search=${encodeURIComponent(applied)}&page=${page}`), enabled: Boolean(context.canViewNetwork) });
  const change = useMutation({
    mutationFn: async (input: { action: "invite" | "role" | "deactivate"; storeId: number; userId?: number; email?: string; role?: Role; expectedRole?: string }) => {
      const path = input.action === "invite" ? "network/team/invite" : input.action === "role" ? "network/team/role" : "network/team/deactivate";
      return storeFetch(path, { method: input.action === "role" ? "PATCH" : "POST", headers: { "X-CSRF-Token": context.csrfToken },
        body: JSON.stringify({ ...input, csrfToken: context.csrfToken }) });
    },
    onSuccess: (_, input) => { setMessage(input.action === "invite" ? "Convite criado. A pessoa precisa aceitá-lo ao entrar." : "Vínculo atualizado.");
      setInvite((current) => ({ ...current, email: "" })); void client.invalidateQueries({ queryKey: ["network-team"] }); void client.invalidateQueries({ queryKey: ["network-overview"] }); },
    onError: () => setMessage(""),
  });

  if (!context.canViewNetwork) return <div className="store-page"><section className="store-panel"><h2>Visão de filial</h2><p>A central consolidada é exclusiva de gestores designados pela KlubeCash.</p><Link href="/store/dashboard">Voltar à filial</Link></section></div>;
  if (overview.isLoading || team.isLoading) return <LoadingState />;
  if (overview.isError || team.isError || !overview.data || !team.data) return <ErrorState message={overview.error?.message ?? team.error?.message ?? "Não foi possível carregar a rede."} retry={() => { void overview.refetch(); void team.refetch(); }} />;
  const branches = overview.data.branches;
  return <div className="store-page store-stack">
    <section className="store-page-head"><div><h2>{overview.data.networkName}</h2><p>Filiais e pessoas ligadas à mesma rede. Cada venda continua pertencendo à filial onde ocorreu.</p></div>
      <Link className="store-button store-button-primary" href="/store/relatorios">Ver métricas</Link></section>
    <section className="store-network-grid" aria-label="Filiais da rede">{branches.map((branch) => <article className="store-network-card" key={branch.id}>
      <h3>{branch.name}</h3><p>CNPJ {branch.cnpj}</p><p>Histórico total: {branch.approvedSales} venda(s) aprovadas · {moneyFromCents(branch.approvedAmountCents)}</p>
      <p>{branch.activeStaff} vínculo(s) ativos · {branch.pendingStaff} convite(s) pendentes</p>
      <div className="store-network-card-actions"><Link className="store-button" href="/store/transacoes" onClick={() => view.setView({ branch: branch.id })}>Vendas</Link><Link className="store-button" href="/store/relatorios" onClick={() => view.setView({ branch: branch.id })}>Métricas</Link></div>
    </article>)}</section>
    <section className="store-panel"><h3>Gestores designados pela KlubeCash</h3><p>{overview.data.managers?.map((manager) => manager.name).join(", ") || "Nenhum gestor ativo identificado."} Somente o admin KlubeCash pode conceder ou revogar esse acesso.</p></section>
    <section className="store-panel store-stack"><div className="store-panel-head"><div><h3>Equipe da rede</h3><p>Uma conta, funções e estados separados por filial.</p></div>
      <form onSubmit={(event) => { event.preventDefault(); setApplied(search); setPage(1); }} className="store-actions"><input className="store-input" aria-label="Buscar pessoa" placeholder="Nome ou e-mail" value={search} onChange={(event) => setSearch(event.target.value)} /><button className="store-button">Buscar</button></form></div>
      <div className="store-table-wrap"><table className="store-table"><thead><tr><th>Pessoa</th><th>Filiais e funções</th></tr></thead><tbody>
        {team.data.items.map((member) => <tr key={member.id}><td><strong>{member.name}</strong><small>{member.email}</small><small>Conta: {member.accountStatus}</small>{member.networkManager && <small>Gestor da rede · acesso alterado somente pela KlubeCash</small>}</td><td>
          {member.branches.map((link) => <div className="store-actions" key={link.storeId} style={{ marginBottom: 8 }}>
            <strong>{link.storeName}</strong><span>{link.status === "pending" ? "Convite pendente" : link.status === "inactive" ? "Inativo" : "Ativo"}</span>
            {link.status === "active" && !member.networkManager && <><select className="store-select" aria-label={`Função de ${member.name} em ${link.storeName}`} value={link.role}
              disabled={change.isPending} onChange={(event) => change.mutate({ action: "role", userId: member.id, storeId: link.storeId, role: event.target.value as Role, expectedRole: link.role })}>
              <option value="vendedor">Vendedor</option><option value="financeiro">Financeiro</option><option value="gerente">Gerente</option></select>
              <button className="store-button store-button-danger" disabled={change.isPending} onClick={() => { if (confirm(`Desativar o vínculo de ${member.name} com ${link.storeName}?`)) change.mutate({ action: "deactivate", userId: member.id, storeId: link.storeId }); }}>Desativar</button></>}
          </div>)}
        </td></tr>)}
      </tbody></table></div>
      {!team.data.items.length && <p>Nenhuma pessoa encontrada.</p>}
      {team.data.pagination.totalPages > 1 && <div className="store-pagination"><button className="store-button" disabled={page <= 1} onClick={() => setPage(page - 1)}>Anterior</button><span>Página {page} de {team.data.pagination.totalPages}</span><button className="store-button" disabled={page >= team.data.pagination.totalPages} onClick={() => setPage(page + 1)}>Próxima</button></div>}
    </section>
    <section className="store-panel store-stack"><h3>Convidar conta existente para uma filial</h3><p>O funcionário receberá um vínculo pendente e precisará aceitá-lo. Para criar uma conta nova, use a equipe da filial ativa.</p>
      <form className="store-actions" onSubmit={(event) => { event.preventDefault(); change.mutate({ action: "invite", ...invite }); }}>
        <input className="store-input" type="email" required aria-label="E-mail do funcionário" placeholder="E-mail do funcionário" value={invite.email} onChange={(event) => setInvite({ ...invite, email: event.target.value })} />
        <select className="store-select" aria-label="Filial do convite" value={invite.storeId} onChange={(event) => setInvite({ ...invite, storeId: Number(event.target.value) })}>{branches.map((branch) => <option key={branch.id} value={branch.id}>{branch.name}</option>)}</select>
        <select className="store-select" aria-label="Função do convite" value={invite.role} onChange={(event) => setInvite({ ...invite, role: event.target.value as Role })}><option value="vendedor">Vendedor</option><option value="financeiro">Financeiro</option><option value="gerente">Gerente</option></select>
        <button className="store-button store-button-primary" disabled={change.isPending}>Criar convite</button>
      </form><Link href="/store/funcionarios">Gerenciar equipe da filial ativa</Link>
      {message && <p className="store-alert">{message}</p>}{change.isError && <p className="store-alert store-alert-error" role="alert">{change.error.message}</p>}
    </section>
    <section className="store-panel"><h3>Giftback compartilhado</h3><p>Créditos válidos podem ser usados em filiais ativas da rede. A origem, a validade e o histórico de cada crédito permanecem preservados.</p><Link href="/store/relatorios">Ver emissão e uso por filial</Link></section>
  </div>;
}
