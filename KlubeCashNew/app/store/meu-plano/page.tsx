"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { BadgeCheck, Check, KeyRound, Sparkles } from "lucide-react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { storeFetch } from "@/lib/client-api";
import { moneyFromCents } from "@/lib/format";
import { ErrorState, LoadingState } from "@/components/store/PageState";
import { useStoreContext } from "@/components/store/StoreProviders";

type SubscriptionData = {
  dataState: "ready" | "empty";
  generatedAt: string;
  subscription: null | {
    status: string;
    cycle: string;
    planName: string;
    planSlug: string;
    currentPeriodEnd: string | null;
    trialEnd: string | null;
    monthlyPriceCents: number;
    features: Array<string> | Record<string, unknown>;
  };
  plans: Array<{
    name: string;
    slug: string;
    monthlyPriceCents: number;
    annualPriceCents: number;
    trialDays: number;
    features: Array<string> | Record<string, unknown>;
  }>;
};
const codeSchema = z.object({ code: z.string().trim().min(4, "Informe o código recebido.").max(32) });

export default function SubscriptionPage() {
  const context = useStoreContext();
  const queryClient = useQueryClient();
  const form = useForm<z.infer<typeof codeSchema>>({ resolver: zodResolver(codeSchema), defaultValues: { code: "" } });
  const query = useQuery({ queryKey: ["subscription"], queryFn: () => storeFetch<SubscriptionData>("subscription") });
  const redeem = useMutation({
    mutationFn: ({ code }: z.infer<typeof codeSchema>) =>
      storeFetch<{ planName: string; status: string }>("subscription/redeem", {
        method: "POST",
        headers: { "X-CSRF-Token": context.csrfToken },
        body: JSON.stringify({ code: code.toUpperCase(), csrfToken: context.csrfToken }),
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["subscription"] });
      form.reset();
    },
  });
  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data)
    return <ErrorState message={query.error?.message ?? "Erro inesperado."} retry={() => query.refetch()} />;
  const { subscription, plans } = query.data;
  const features = subscription ? normalizeFeatures(subscription.features) : [];

  return (
    <div className="store-page store-stack">
      <section className="store-page-head">
        <div><h2>Plano da sua loja</h2><p>Consulte os recursos ativos e resgate um código fornecido pela equipe Klube Cash.</p></div>
        {subscription && <span className={`store-status ${["ativa", "trial"].includes(subscription.status) ? "ativo" : "pendente"}`}>{subscription.status}</span>}
      </section>
      <div className="store-alert">
        Cobranças e PIX de assinatura não estão disponíveis nesta etapa. Nenhuma cobrança será iniciada por esta tela.
      </div>
      {!subscription && (
        <section className="store-panel" style={{ background: "linear-gradient(135deg,var(--store-card),var(--store-soft))" }}>
          <div className="store-grid store-grid-2">
            <div><span className="store-stat-icon"><KeyRound size={20} /></span><h3 style={{ fontSize: 24, marginBottom: 8 }}>Ative seu plano com um código</h3><p style={{ color: "var(--store-muted)", fontSize: 13, lineHeight: 1.6 }}>Use somente o código fornecido pelo administrador.</p></div>
            <form className="store-form" onSubmit={form.handleSubmit((values) => redeem.mutate(values))}>
              <label className="store-field"><span>Código do plano</span><input className="store-input store-code" {...form.register("code")} placeholder="KLUBE-XXXX" />{form.formState.errors.code && <small className="store-error">{form.formState.errors.code.message}</small>}</label>
              {redeem.isError && <div className="store-alert store-alert-error">{redeem.error.message}</div>}
              {redeem.isSuccess && <div className="store-alert store-alert-success">Plano ativado com sucesso.</div>}
              <button className="store-button store-button-primary" disabled={redeem.isPending}><Sparkles size={16} />{redeem.isPending ? "Ativando..." : "Ativar plano"}</button>
            </form>
          </div>
        </section>
      )}
      {subscription && (
        <section className="store-panel">
          <div className="store-panel-head"><div><h3><BadgeCheck size={18} /> {subscription.planName}</h3><p>Plano atual da sua loja</p></div><strong style={{ fontSize: 24 }}>{moneyFromCents(subscription.monthlyPriceCents)}<small style={{ fontSize: 11, color: "var(--store-muted)" }}>/mês</small></strong></div>
          <div className="store-grid store-grid-3">
            {features.map((feature) => <div className="store-customer-card" key={feature}><Check size={16} style={{ color: "var(--store-green)" }} /><h4 style={{ marginTop: 8 }}>{feature}</h4></div>)}
          </div>
        </section>
      )}
      <section>
        <div className="store-panel-head"><div><h3>Planos disponíveis</h3><p>Visão informativa; mudanças são feitas por código ou pelo suporte.</p></div></div>
        <div className="store-grid store-grid-3">
          {plans.map((plan) => <article className="store-panel" key={plan.slug}><span className="store-stat-label">{plan.trialDays > 0 ? `${plan.trialDays} dias de teste` : "Plano Klube Cash"}</span><h3 style={{ fontSize: 20, marginBottom: 4 }}>{plan.name}</h3><strong style={{ fontSize: 24 }}>{moneyFromCents(plan.monthlyPriceCents)}<small style={{ fontSize: 11, color: "var(--store-muted)" }}>/mês</small></strong></article>)}
        </div>
      </section>
    </div>
  );
}

function normalizeFeatures(features: Array<string> | Record<string, unknown>) {
  return Array.isArray(features)
    ? features.map(String)
    : Object.entries(features).map(([key, value]) => `${key.replaceAll("_", " ")}: ${String(value)}`);
}
