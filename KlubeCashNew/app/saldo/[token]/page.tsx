"use client";

import Image from "next/image";
import Link from "next/link";
import { FormEvent, useCallback, useEffect, useState } from "react";
import { useParams } from "next/navigation";
import { Klubinho } from "@/components/Klubinho";
import styles from "./wallet-link.module.css";

type Store = { id: number; name: string; logo: string | null; description: string | null; networkName: string | null };
type Context = {
  store: Store;
  csrfToken: string;
  account: { name: string; email: string } | null;
  visitor: { name: string; phone: string; expiresAt: string } | null;
  hasPreviousPhoneBalance: boolean;
};
type Credit = { id: number; storeName?: string; remainingCents: number; validUntil: string | null; creditedAt: string; status: string };
type Wallet = {
  store: Store;
  customerName: string;
  visitor: boolean;
  wallet: { availableCents: number; nextExpirationDate: string | null; nextExpirationCents: number; credits: Credit[]; scope?: "store" | "network" };
};
type Mode = "none" | "login" | "phone" | "signup" | "claim";
type Stage = "details" | "code" | "form";

const money = (cents: number) => new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(cents / 100);
function date(value: string) {
  const [year, month, day] = value.slice(0, 10).split("-");
  return `${day}/${month}/${year}`;
}

export default function StoreWalletLinkPage() {
  const { token } = useParams<{ token: string }>();
  const [context, setContext] = useState<Context | null>(null);
  const [wallet, setWallet] = useState<Wallet | null>(null);
  const [mode, setMode] = useState<Mode>("login");
  const [stage, setStage] = useState<Stage>("details");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [fields, setFields] = useState({ name: "", phone: "", email: "", password: "", confirmation: "", code: "" });
  const update = (key: keyof typeof fields, value: string) => setFields((current) => ({ ...current, [key]: value }));
  const endpoint = useCallback((action: string) => `/api/wallet-link/${token}/${action}`, [token]);
  const call = useCallback(async <T,>(action: string, data?: object, csrf?: string): Promise<T> => {
    const response = await fetch(endpoint(action), {
      method: data ? "POST" : "GET",
      headers: { Accept: "application/json", ...(data ? { "Content-Type": "application/json", "X-CSRF-Token": csrf ?? "" } : {}) },
      body: data ? JSON.stringify(data) : undefined,
      cache: "no-store",
    });
    const payload = await response.json();
    if (!response.ok || payload.status !== "success") throw new Error(payload.message || "Não foi possível concluir a solicitação.");
    return payload.data as T;
  }, [endpoint]);
  const refresh = useCallback(async () => {
    const current = await call<Context>("context");
    setContext(current);
    if (current.visitor) {
      setFields((values) => ({ ...values, name: values.name || current.visitor!.name, phone: values.phone || current.visitor!.phone }));
    }
    if (current.account || current.visitor) setWallet(await call<Wallet>("wallet"));
    else setWallet(null);
  }, [call]);

  useEffect(() => {
    let active = true;
    void call<Context>("context").then(async (current) => {
      if (!active) return;
      setContext(current);
      if (current.account || current.visitor) setMode("none");
      if (current.visitor) setFields((values) => ({ ...values, name: current.visitor!.name, phone: current.visitor!.phone }));
      if (current.account || current.visitor) {
        const currentWallet = await call<Wallet>("wallet");
        if (active) setWallet(currentWallet);
      }
    }).catch((cause) => { if (active) setError(cause instanceof Error ? cause.message : "Consulta indisponível."); });
    return () => { active = false; };
  }, [call]);

  const submit = async (action: string, data: object, after?: () => void) => {
    if (!context || busy) return;
    setBusy(true); setError(""); setNotice("");
    try {
      await call(action, data, context.csrfToken);
      after?.();
      await refresh();
    } catch (cause) { setError(cause instanceof Error ? cause.message : "Tente novamente."); }
    finally { setBusy(false); }
  };
  const changeMode = (next: Mode) => { setMode(next); setStage("details"); setError(""); setNotice(""); update("code", ""); };
  const purpose = mode === "claim" ? "claim" : mode === "signup" ? "signup" : "visitor";
  const requestCode = (event: FormEvent) => {
    event.preventDefault();
    void submit("request-code", { phone: fields.phone, purpose }, () => { setStage("code"); setNotice("Enviamos um código pelo WhatsApp. Ele vale por 5 minutos."); });
  };
  const verifyCode = (event: FormEvent) => {
    event.preventDefault();
    void submit("verify-code", { phone: fields.phone, purpose, code: fields.code, name: fields.name }, () => {
      if (mode === "phone") { setMode("none"); setStage("details"); setNotice("Telefone confirmado. Confira seu saldo abaixo."); }
      else { setStage("form"); setNotice("Telefone confirmado."); }
    });
  };

  const authenticated = Boolean(context?.account || context?.visitor);
  const visibleCredits = wallet?.wallet.credits.filter((credit) => credit.remainingCents > 0) ?? [];
  const showAccess = context && (!authenticated || mode === "claim" || (context.visitor && (mode === "signup" || mode === "login")));

  return <main className={styles.page}>
    <div className={styles.wrap}>
      <header className={styles.header}>
        <Link href="/" aria-label="KlubeCash — página inicial" className={styles.brand}>
          <Image src="/brand/klubecash-logo.png" alt="KlubeCash" width={791} height={247} unoptimized priority />
        </Link>
        <span className={styles.headerLabel}>Consulta segura de giftback</span>
      </header>

      {context ? <>
        <section className={styles.hero} aria-labelledby="store-title">
          <div className={styles.heroCopy}>
            <span className={styles.eyebrow}>{context.store.networkName ? `Filial da rede ${context.store.networkName}` : "Sua carteira nesta loja"}</span>
            <h1 id="store-title">{context.store.name}</h1>
            <p>{context.store.networkName
              ? "Consulte o giftback válido que pode usar nesta filial e nas demais filiais ativas da rede. Cada crédito mostra sua loja de origem."
              : "Entre ou confirme seu telefone para ver o giftback disponível neste estabelecimento."}</p>
          </div>
          <Klubinho pose="aceno" size={170} className={styles.mascot} priority />
        </section>

        {wallet && <section className={styles.wallet} aria-live="polite">
          <div className={styles.walletHeading}>
            <div><span className={styles.eyebrow}>Carteira de {wallet.customerName}</span><h2>Seu saldo disponível</h2></div>
            {wallet.visitor && <span className={styles.temporaryBadge}>Acesso pelo telefone</span>}
          </div>
          <strong className={styles.balanceAmount}>{money(wallet.wallet.availableCents)}</strong>
          {wallet.wallet.availableCents === 0 && <p className={styles.emptyBalance}>Você ainda não tem giftback disponível {context.store.networkName ? "nesta rede" : "nesta loja"}.</p>}
          {wallet.wallet.nextExpirationDate && wallet.wallet.nextExpirationCents > 0 && <p className={styles.expiry}>
            <span aria-hidden="true">◷</span> {money(wallet.wallet.nextExpirationCents)} vencem em {date(wallet.wallet.nextExpirationDate)}. Você pode usar até o fim desse dia.
          </p>}
          <div className={styles.creditSection}>
            <h3>Créditos disponíveis</h3>
            {visibleCredits.length ? <ul className={styles.creditList}>{visibleCredits.map((credit) => <li key={credit.id} className={styles.credit}>
              <span>{credit.storeName ? `${credit.storeName} · ` : ""}Recebido em {date(credit.creditedAt)}<small>{credit.validUntil ? `Válido até ${date(credit.validUntil)}` : "Sem vencimento"}</small></span>
              <strong>{money(credit.remainingCents)}</strong>
            </li>)}</ul> : <p className={styles.noCredits}>Os próximos créditos recebidos {context.store.networkName ? "nesta rede" : "nesta loja"} aparecerão aqui.</p>}
          </div>
        </section>}

        {context.visitor && !context.account && mode === "none" && <section className={styles.panel}>
          <h2>Quer guardar seu acesso?</h2>
          <p>Crie sua conta aqui mesmo para consultar seu saldo sempre que quiser. O giftback que você já tem será preservado.</p>
          <div className={styles.actions}><button type="button" className={styles.primary} onClick={() => { changeMode("signup"); setStage("form"); }}>Criar minha conta</button>
            <button type="button" className={styles.textButton} onClick={() => changeMode("login")}>Já tenho conta</button></div>
        </section>}

        {context.account && mode === "none" && <>
          {context.hasPreviousPhoneBalance && <section className={styles.panel}>
            <h2>Giftback recebido antes do cadastro</h2>
            <p>Encontramos um possível saldo anterior {context.store.networkName ? "nesta rede" : "nesta loja"} associado ao telefone da sua conta. Confirme o número pelo WhatsApp para vinculá-lo.</p>
            <button type="button" className={styles.secondary} onClick={() => changeMode("claim")}>Confirmar telefone e vincular</button>
          </section>}
          <details className={styles.help}><summary>Dúvidas sobre esta carteira?</summary>
            <p>Se recebeu giftback {context.store.networkName ? "nesta rede" : "nesta loja"} usando outro telefone, confirme esse número para procurar e vincular o saldo à sua conta.</p>
            <button type="button" className={styles.textButton} onClick={() => changeMode("claim")}>Usar outro telefone</button>
          </details>
        </>}

        {showAccess && <section className={styles.panel} aria-labelledby="access-title">
          {!authenticated && <div className={styles.tabs} aria-label="Como consultar seu saldo">
            <button type="button" aria-pressed={mode === "login"} className={mode === "login" ? styles.active : ""} onClick={() => changeMode("login")}>Já tenho conta</button>
            <button type="button" aria-pressed={mode === "phone"} className={mode === "phone" ? styles.active : ""} onClick={() => changeMode("phone")}>Recebi pelo telefone</button>
            <button type="button" aria-pressed={mode === "signup"} className={mode === "signup" ? styles.active : ""} onClick={() => changeMode("signup")}>Criar conta</button>
          </div>}
          <div className={styles.panelHeading}><h2 id="access-title">{mode === "login" ? "Entre para ver seu saldo" : mode === "phone" ? "Confirme seu telefone" : mode === "signup" ? "Cadastre-se aqui" : "Recuperar giftback anterior"}</h2>
            {authenticated && <button type="button" className={styles.textButton} onClick={() => changeMode("none")}>Fechar</button>}</div>
          {mode === "login" ? <form className={styles.form} onSubmit={(event) => { event.preventDefault(); void submit("login", { email: fields.email, password: fields.password }, () => { setMode("none"); setNotice("Login realizado."); }); }}>
            <label>E-mail<input type="email" autoComplete="email" required value={fields.email} onChange={(event) => update("email", event.target.value)} /></label>
            <label>Senha<input type="password" autoComplete="current-password" required value={fields.password} onChange={(event) => update("password", event.target.value)} /></label>
            <button className={styles.primary} disabled={busy}>Entrar e consultar saldo</button>
          </form> : <>
            {stage === "details" && <form className={styles.form} onSubmit={requestCode}>
              {mode === "phone" && <label>Seu nome<input required minLength={3} autoComplete="name" value={fields.name} onChange={(event) => update("name", event.target.value)} /></label>}
              <label>Celular com DDD<input type="tel" required autoComplete="tel" inputMode="tel" value={fields.phone} onChange={(event) => update("phone", event.target.value)} placeholder="(11) 99999-9999" /></label>
              <p className={styles.formHint}>Enviaremos um código pelo WhatsApp para proteger seu saldo.</p>
              <button className={styles.primary} disabled={busy}>Receber código</button>
            </form>}
            {stage === "code" && <form className={styles.form} onSubmit={verifyCode}>
              <p>Digite o código enviado para {fields.phone}.</p>
              <label>Código de 6 dígitos<input required inputMode="numeric" pattern="[0-9]{6}" maxLength={6} value={fields.code} onChange={(event) => update("code", event.target.value)} /></label>
              <button className={styles.primary} disabled={busy}>Confirmar telefone</button>
              <button type="button" className={styles.textButton} onClick={() => setStage("details")}>Alterar telefone ou reenviar código</button>
            </form>}
            {stage === "form" && mode === "signup" && <form className={styles.form} onSubmit={(event) => { event.preventDefault(); void submit("signup", fields, () => { setMode("none"); setNotice("Conta criada. Seu saldo foi preservado."); }); }}>
              <label>Nome completo<input required minLength={3} autoComplete="name" value={fields.name} onChange={(event) => update("name", event.target.value)} /></label>
              <label>Celular confirmado<input type="tel" value={fields.phone} readOnly /></label>
              <label>E-mail<input required type="email" autoComplete="email" value={fields.email} onChange={(event) => update("email", event.target.value)} /></label>
              <label>Senha<input required minLength={8} type="password" autoComplete="new-password" value={fields.password} onChange={(event) => update("password", event.target.value)} /></label>
              <label>Confirme a senha<input required minLength={8} type="password" autoComplete="new-password" value={fields.confirmation} onChange={(event) => update("confirmation", event.target.value)} /></label>
              <button className={styles.primary} disabled={busy || fields.password !== fields.confirmation}>Criar conta e manter meu saldo</button>
              {fields.confirmation && fields.password !== fields.confirmation && <p className={styles.inlineError}>As senhas não coincidem.</p>}
            </form>}
            {stage === "form" && mode === "claim" && <div className={styles.form}>
              <p>Somente os créditos recebidos com esse telefone {context.store.networkName ? "nas filiais desta rede" : "nesta loja"} serão vinculados. Outras redes não serão alteradas.</p>
              <button type="button" className={styles.primary} disabled={busy} onClick={() => void submit("claim", {}, () => { setMode("none"); setNotice("Saldo anterior vinculado à sua conta."); })}>Vincular saldo {context.store.networkName ? "desta rede" : "desta loja"}</button>
            </div>}
          </>}
        </section>}
        {error && <p role="alert" className={styles.error}>{error}</p>}
        {notice && <p role="status" className={styles.notice}>{notice}</p>}
        <footer className={styles.footer}>Seu saldo só é mostrado após login ou confirmação do telefone. <Link href="/">Voltar à KlubeCash</Link></footer>
      </> : <section className={styles.loading} role="status">
        <h1>{error ? "Não foi possível abrir esta carteira" : "Preparando sua carteira"}</h1>
        <p>{error || "Estamos verificando o link da loja com segurança..."}</p>
        {error && <Link href="/">Voltar à KlubeCash</Link>}
      </section>}
    </div>
  </main>;
}
