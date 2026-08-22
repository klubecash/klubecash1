"use client";

import Image from "next/image";
import Link from "next/link";
import { CheckCircle2, LoaderCircle, LockKeyhole, LogIn, MessageCircle, ShieldCheck, Store } from "lucide-react";
import { useEffect, useState } from "react";
import styles from "@/app/whatsapp/autenticar/whatsapp-auth.module.css";

type AuthorizationContext = {
  canAuthorize: boolean;
  user: { name: string; type: string; maskedPhone: string };
  store: { id: number; name: string };
  expiresAt: string;
  message: string;
  csrfToken: string;
};

type ApiResponse<T> = { status: "success" | "error"; data?: T; message?: string; requestId: string };
type Result = { authorized: true; store: { id: number; name: string }; expiresAt: string; returnToWhatsAppUrl: string };

export default function WhatsAppAuthorization({ token }: { token: string }) {
  const [context, setContext] = useState<AuthorizationContext | null>(null);
  const [result, setResult] = useState<Result | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    const url = new URL(window.location.href);
    url.searchParams.delete("token");
    window.history.replaceState({}, "", `${url.pathname}${url.search}${url.hash}`);
    const controller = new AbortController();
    void fetch(`/api/whatsapp/auth/approve?token=${encodeURIComponent(token)}`, {
      credentials: "same-origin",
      cache: "no-store",
      signal: controller.signal,
    })
      .then(async (response) => {
        const payload = (await response.json()) as ApiResponse<AuthorizationContext>;
        if (!response.ok || payload.status !== "success" || !payload.data) throw new Error(payload.message ?? "Nao foi possivel validar o link.");
        setContext(payload.data);
      })
      .catch((reason: unknown) => {
        if (!controller.signal.aborted) setError(reason instanceof Error ? reason.message : "Nao foi possivel validar o link.");
      });
    return () => controller.abort();
  }, [token]);

  async function authorize() {
    if (!context || !context.canAuthorize || submitting) return;
    setSubmitting(true);
    setError(null);
    try {
      const response = await fetch("/api/whatsapp/auth/approve", {
        method: "POST",
        credentials: "same-origin",
        headers: { "content-type": "application/json", "x-csrf-token": context.csrfToken },
        body: JSON.stringify({ token }),
      });
      const payload = (await response.json()) as ApiResponse<Result>;
      if (!response.ok || payload.status !== "success" || !payload.data) throw new Error(payload.message ?? "Nao foi possivel autorizar o WhatsApp.");
      setResult(payload.data);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : "Nao foi possivel autorizar o WhatsApp.");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <main className={styles.page}>
      <section className={styles.card} aria-live="polite">
        <Link href="/" className={styles.brand} aria-label="Voltar para Klube Cash">
          <Image src="/assets/images/logolaranja.png" alt="Klube Cash" width={210} height={81} unoptimized priority />
        </Link>

        {result ? (
          <div className={styles.state}>
            <span className={styles.successIcon}><CheckCircle2 aria-hidden="true" /></span>
            <p className={styles.eyebrow}>Autorizacao concluida</p>
            <h1>WhatsApp conectado com seguranca</h1>
            <p>O acesso da loja <strong>{result.store.name}</strong> foi liberado. A confirmacao tambem foi enviada para a conversa.</p>
            <a className={styles.primaryButton} href={result.returnToWhatsAppUrl} rel="noreferrer">
              <MessageCircle aria-hidden="true" /> Voltar ao WhatsApp
            </a>
          </div>
        ) : error ? (
          <div className={styles.state}>
            <span className={styles.errorIcon}><LockKeyhole aria-hidden="true" /></span>
            <p className={styles.eyebrow}>Autorizacao interrompida</p>
            <h1>Nao foi possivel liberar o acesso</h1>
            <p>{error}</p>
            <p className={styles.hint}>Volte ao WhatsApp e envie <strong>/klube</strong> para solicitar um novo link.</p>
          </div>
        ) : !context ? (
          <div className={styles.state} role="status">
            <LoaderCircle className={styles.spinner} aria-hidden="true" />
            <h1>Validando seu acesso...</h1>
            <p>Estamos conferindo a sessao, o telefone e o vinculo com a loja.</p>
          </div>
        ) : (
          <div className={styles.state}>
            <span className={styles.securityIcon}><ShieldCheck aria-hidden="true" /></span>
            <p className={styles.eyebrow}>Acesso lojista pelo WhatsApp</p>
            <h1>Confirme esta autorizacao</h1>
            <p>Nenhuma senha sera compartilhada com o WhatsApp. Confirme apenas se voce iniciou esta solicitacao.</p>
            <div className={styles.summary}>
              <div><Store aria-hidden="true" /><span><small>Loja</small><strong>{context.store.name}</strong></span></div>
              <div><LogIn aria-hidden="true" /><span><small>Conta</small><strong>{context.user.name}</strong><em>{context.user.maskedPhone}</em></span></div>
            </div>
            {!context.canAuthorize && <p className={styles.warning}>{context.message}</p>}
            <button className={styles.primaryButton} type="button" disabled={!context.canAuthorize || submitting} onClick={authorize}>
              {submitting ? <LoaderCircle className={styles.spinnerSmall} aria-hidden="true" /> : <ShieldCheck aria-hidden="true" />}
              {submitting ? "Autorizando..." : "Autorizar este WhatsApp"}
            </button>
            <Link className={styles.secondaryButton} href={`/login?force_login=1&returnTo=${encodeURIComponent(`/whatsapp/autenticar?token=${token}`)}`}>
              Entrar com outra conta
            </Link>
          </div>
        )}
      </section>
    </main>
  );
}
