"use client";

import Link from "next/link";
import Image from "next/image";
import { Suspense, useCallback, useMemo, useState } from "react";
import { CheckCircle2, Clipboard, CreditCard, LoaderCircle, ShieldCheck } from "lucide-react";
import { useQuery } from "@tanstack/react-query";
import { useSearchParams } from "next/navigation";
import { storeFetch } from "@/lib/client-api";
import { moneyFromCents } from "@/lib/format";
import { ErrorState, LoadingState } from "@/components/store/PageState";
import { useStoreContext } from "@/components/store/StoreProviders";
import PaymentBrick from "./PaymentBrick";

type Invoice = { id: number; number: string; amountCents: number; status: string; dueDate: string | null };
type SubscriptionData = {
  checkout?: { mode: "transparent" | "legacy"; manualBilling: boolean; publicKey: string };
  subscription: { planName: string; cycle: string } | null;
  invoices: Invoice[];
};
type PaymentResult = { invoiceId: number; paymentId: string; status: string; statusDetail?: string | null; pixQrCode?: string | null; pixCopyPaste?: string | null };

const statusLabels: Record<string, string> = { pending: "Aguardando confirmação", paid: "Pagamento aprovado", approved: "Pagamento aprovado", failed: "Pagamento recusado", rejected: "Pagamento recusado", cancelled: "Pagamento cancelado" };

function PaymentPageContent() {
  const context = useStoreContext();
  const searchParams = useSearchParams();
  const requestedId = Number(searchParams.get("invoiceId") ?? 0);
  const query = useQuery({ queryKey: ["subscription"], queryFn: () => storeFetch<SubscriptionData>("subscription") });
  const [payment, setPayment] = useState<PaymentResult | null>(null);
  const [copied, setCopied] = useState(false);
  const invoice = useMemo(() => query.data?.invoices.find((item) => item.id === requestedId && item.status === "pending") ?? query.data?.invoices.find((item) => item.status === "pending"), [query.data?.invoices, requestedId]);
  const paymentStatus = useQuery({
    queryKey: ["subscription-payment", payment?.paymentId],
    queryFn: () => storeFetch<PaymentResult>(`subscription/payment-status/${payment?.invoiceId}`),
    enabled: Boolean(payment?.paymentId && payment.status === "pending"),
    refetchInterval: payment?.status === "pending" ? 5000 : false,
  });
  const result = paymentStatus.data ?? payment;
  const onResult = useCallback((next: PaymentResult) => setPayment(next), []);
  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState message={query.error?.message ?? "Não foi possível carregar a fatura."} retry={() => query.refetch()} />;
  if (!invoice) return <div className="store-page store-panel store-empty-state"><CreditCard size={28} /><h2>Nenhuma fatura pendente</h2><p className="store-muted">Sua assinatura não possui pagamentos aguardando confirmação.</p><Link className="store-button store-button-primary" href="/store/meu-plano">Voltar para meu plano</Link></div>;
  const finalStatus = result?.status ?? "";
  const copyPix = async () => { if (!result?.pixCopyPaste) return; await navigator.clipboard.writeText(result.pixCopyPaste); setCopied(true); window.setTimeout(() => setCopied(false), 1800); };
  return <div className="store-page store-stack">
    <div className="store-page-head"><div><h2>Pagamento da assinatura</h2><p>Conclua sua fatura com segurança pelo Mercado Pago.</p></div><Link className="store-button" href="/store/meu-plano">Voltar</Link></div>
    <section className="store-panel store-payment-layout">
      <div className="store-payment-summary"><span className="store-stat-icon"><CreditCard size={20} /></span><h3>{query.data.subscription?.planName ?? "Assinatura KlubeCash"}</h3><p className="store-muted">Fatura {invoice.number || `#${invoice.id}`}</p><strong className="store-payment-amount">{moneyFromCents(invoice.amountCents)}</strong><p className="store-muted">Vencimento: {invoice.dueDate ? new Intl.DateTimeFormat("pt-BR", { dateStyle: "medium" }).format(new Date(invoice.dueDate)) : "—"}</p><div className="store-payment-safe"><ShieldCheck size={16} /> Seus dados de cartão são tokenizados pelo Mercado Pago.</div></div>
      <div className="store-payment-form">{!result || ["failed", "rejected", "cancelled"].includes(finalStatus) ? <PaymentBrick publicKey={query.data.checkout?.publicKey ?? ""} amountCents={invoice.amountCents} invoiceId={invoice.id} csrfToken={context.csrfToken} onResult={onResult} /> : <div className={`store-payment-result ${finalStatus === "paid" || finalStatus === "approved" ? "success" : "pending"}`}><CheckCircle2 size={30} /><h3>{statusLabels[finalStatus] ?? "Processando pagamento"}</h3><p>{result.statusDetail ?? (finalStatus === "pending" ? "Aguardando confirmação do Mercado Pago. Esta página será atualizada automaticamente." : "Você já pode voltar ao seu plano.")}</p>{result.pixQrCode && <Image className="store-pix-qr" src={`data:image/png;base64,${result.pixQrCode}`} width={190} height={190} unoptimized alt="QR Code Pix para pagamento" />}{result.pixCopyPaste && <button type="button" className="store-button" onClick={copyPix}><Clipboard size={16} />{copied ? "Copiado" : "Copiar código Pix"}</button>}<Link className="store-button store-button-primary" href="/store/meu-plano">Voltar para meu plano</Link> </div>}{paymentStatus.isFetching && finalStatus === "pending" && <p className="store-muted store-payment-refresh"><LoaderCircle size={15} className="store-spin" /> Verificando pagamento...</p>}</div>
    </section>
  </div>;
}

export default function PaymentPage() {
  return <Suspense fallback={<LoadingState />}><PaymentPageContent /></Suspense>;
}
