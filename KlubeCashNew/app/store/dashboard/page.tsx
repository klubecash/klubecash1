"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import {
  ArrowRight,
  BadgeDollarSign,
  HandCoins,
  ShoppingBag,
  Users,
} from "lucide-react";
import { storeFetch } from "@/lib/client-api";
import { dateTime, moneyFromCents, monthLabel, number } from "@/lib/format";
import type { DashboardData } from "@/types/store";
import {
  EmptyState,
  ErrorState,
  LoadingState,
} from "@/components/store/PageState";
import { useStoreContext } from "@/components/store/StoreProviders";

export default function DashboardPage() {
  const context = useStoreContext();
  const searchParams = useSearchParams();
  const query = useQuery({
    queryKey: ["store-dashboard"],
    queryFn: () => storeFetch<DashboardData>("dashboard"),
  });
  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data)
    return (
      <ErrorState
        message={query.error?.message ?? "Erro inesperado."}
        retry={() => query.refetch()}
      />
    );
  const { summary, recentTransactions, monthlySales } = query.data;
  const retiredNotice = retiredMessage(searchParams.get("notice"));
  const maximum = Math.max(
    1,
    ...monthlySales.map((item) => item.grossAmountCents),
  );
  return (
    <div className="store-page store-stack">
      <section className="store-page-head">
        <div>
          <h2>Olá, {context.user.name.split(" ")[0]}.</h2>
          <p>
            Acompanhe o desempenho da {context.store.name} e encontre
            rapidamente o que precisa.
          </p>
        </div>
        <div className="store-head-actions">
          <Link
            className="store-button store-button-primary"
            href="/store/registrar-transacao"
          >
            <ShoppingBag size={17} /> Registrar venda
          </Link>
        </div>
      </section>
      {retiredNotice && <div className="store-alert">{retiredNotice}</div>}
      {query.data.dataState === "empty" && (
        <div className="store-alert store-alert-success">
          <strong>Integração ativa.</strong> Sua loja ainda não registrou vendas.
          Assim que a primeira venda for aprovada, os indicadores aparecerão
          aqui automaticamente.
        </div>
      )}
      <section className="store-grid store-grid-4">
        <Stat
          label="Total de vendas"
          value={number(summary.salesCount)}
          note="Transações registradas"
          icon={<ShoppingBag size={20} />}
        />
        <Stat
          label="Valor movimentado"
          value={moneyFromCents(summary.grossAmountCents)}
          note="Em vendas processadas"
          icon={<HandCoins size={20} />}
        />
        <Stat
          label="Clientes atendidos"
          value={number(summary.customersCount)}
          note="Clientes únicos em vendas"
          icon={<Users size={20} />}
        />
        <Stat
          label="Cashback gerado"
          value={moneyFromCents(summary.cashbackGrantedCents)}
          note="Creditado aos clientes"
          icon={<BadgeDollarSign size={20} />}
        />
      </section>
      <section className="store-grid store-grid-2">
        <div className="store-panel">
          <div className="store-panel-head">
            <div>
              <h3>Vendas nos últimos meses</h3>
              <p>Evolução do valor movimentado</p>
            </div>
          </div>
          {query.data.dataState === "ready" ? (
            <div className="store-chart" aria-label="Gráfico de vendas">
              {monthlySales.map((item) => (
                <div className="store-chart-column" key={item.month}>
                  <div
                    title={moneyFromCents(item.grossAmountCents)}
                    className="store-chart-bar"
                    style={{
                      height: `${Math.max(4, (item.grossAmountCents / maximum) * 100)}%`,
                    }}
                  />
                  <span>{monthLabel(item.month)}</span>
                </div>
              ))}
            </div>
          ) : (
            <EmptyState
              title="Sem dados ainda"
              message="As vendas registradas aparecerão neste gráfico."
            />
          )}
        </div>
        <div className="store-panel">
          <div className="store-panel-head">
            <div>
              <h3>Atalhos</h3>
              <p>Ações mais usadas pela sua equipe</p>
            </div>
          </div>
          <div className="store-stack" style={{ gap: 10 }}>
            <QuickLink
              href="/store/registrar-transacao"
              title="Nova venda"
              text="Registre uma compra e gere cashback."
            />
            <QuickLink
              href="/store/transacoes"
              title="Histórico de vendas"
              text="Consulte vendas, saldo usado e cashback concedido."
            />
            <QuickLink
              href="/store/upload-lote"
              title="Importar vendas"
              text="Processe várias transações por CSV."
            />
          </div>
        </div>
      </section>
      <section className="store-panel">
        <div className="store-panel-head">
          <div>
            <h3>Transações recentes</h3>
            <p>Últimas movimentações da loja</p>
          </div>
          <Link className="store-link" href="/store/transacoes">
            Ver todas <ArrowRight size={14} />
          </Link>
        </div>
        {recentTransactions.length ? (
          <div className="store-table-wrap">
            <table className="store-table">
              <thead>
                <tr>
                  <th>Cliente</th>
                  <th>Código</th>
                  <th>Data</th>
                  <th>Valor</th>
                  <th>Cashback</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                {recentTransactions.map((item) => (
                  <tr key={item.id}>
                    <td>
                      <strong>{item.customerName}</strong>
                    </td>
                    <td className="store-code">{item.code}</td>
                    <td>{dateTime(item.occurredAt)}</td>
                    <td>
                      <strong>{moneyFromCents(item.grossAmountCents)}</strong>
                    </td>
                    <td>{moneyFromCents(item.cashbackGrantedCents)}</td>
                    <td>
                      <span className={`store-status ${item.status}`}>
                        {item.status}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <EmptyState
            title="Nenhuma venda registrada"
            message="Registre a primeira venda para iniciar o histórico da loja."
          />
        )}
      </section>
    </div>
  );
}

function retiredMessage(notice: string | null) {
  if (notice === "pix-not-available")
    return "O PIX ainda não está disponível. Quando for implementado, será exclusivo para assinaturas.";
  if (
    notice === "commission-flow-retired" ||
    notice === "financial-history-retired"
  )
    return "O fluxo antigo de comissões e pagamentos foi descontinuado. As novas vendas são aprovadas imediatamente.";
  return null;
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
    <div className="store-stat">
      <div className="store-stat-top">
        <span className="store-stat-label">{label}</span>
        <span className="store-stat-icon">{icon}</span>
      </div>
      <strong className="store-stat-value">{value}</strong>
      <span className="store-stat-note">{note}</span>
    </div>
  );
}
function QuickLink({
  href,
  title,
  text,
}: {
  href: string;
  title: string;
  text: string;
}) {
  return (
    <Link
      href={href}
      className="store-customer-card"
      style={{ display: "flex", alignItems: "center", gap: 12 }}
    >
      <span className="store-stat-icon">
        <ArrowRight size={18} />
      </span>
      <span>
        <h4>{title}</h4>
        <p>{text}</p>
      </span>
    </Link>
  );
}
