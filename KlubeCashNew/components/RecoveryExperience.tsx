"use client";

import Image from "next/image";
import Link from "next/link";
import { FormEvent, useEffect, useMemo, useRef, useState } from "react";
import styles from "@/app/recuperar-senha/recovery.module.css";

type Theme = "light" | "dark";
type Feedback = { type: "error" | "success"; message: string } | null;

export type RecoveryContext = {
  csrfToken: string;
  validToken: boolean;
  maskedEmail: string | null;
  expirationHours: number;
  error: string | null;
  success: string | null;
};

type RecoveryExperienceProps = {
  token: string | null;
  requestSent: boolean;
  initialContext?: RecoveryContext;
};

type RecoveryResponse = {
  status: boolean;
  message: string;
  redirect?: string;
};

const contextRequests = new Map<string, Promise<RecoveryContext>>();
const minimumPasswordLength = 8;

function applyTheme(theme: Theme) {
  const root = document.documentElement;
  root.dataset.theme = theme;
  root.style.colorScheme = theme;
  document.querySelector<HTMLMetaElement>('meta[name="theme-color"]')
    ?.setAttribute("content", theme === "dark" ? "#0B0D12" : "#FFF8F3");
}

function loadRecoveryContext(token: string | null, requestSent: boolean) {
  const params = new URLSearchParams();
  if (token) params.set("token", token);
  if (requestSent) params.set("enviado", "1");
  const url = `/api/auth/recovery/context${params.size ? `?${params}` : ""}`;
  const existing = contextRequests.get(url);
  if (existing) return existing;

  const request = fetch(url, { credentials: "same-origin", cache: "no-store" })
    .then(async (response) => {
      if (!response.ok) {
        const payload = (await response.json().catch(() => null)) as { message?: string } | null;
        throw new Error(payload?.message ?? "Não foi possível preparar a recuperação de senha.");
      }
      return response.json() as Promise<RecoveryContext>;
    })
    .catch((error) => {
      contextRequests.delete(url);
      throw error;
    });
  contextRequests.set(url, request);
  return request;
}

function passwordStrength(password: string) {
  if (!password) return { score: 0, level: "empty", text: "Digite uma senha para ver a força" };
  let score = 0;
  const missing: string[] = [];
  if (password.length >= minimumPasswordLength) score += 1;
  else missing.push(`pelo menos ${minimumPasswordLength} caracteres`);
  if (/[a-z]/.test(password)) score += 1;
  else missing.push("letras minúsculas");
  if (/[A-Z]/.test(password)) score += 1;
  else missing.push("letras maiúsculas");
  if (/[0-9]/.test(password)) score += 1;
  else missing.push("números");
  if (/[^a-zA-Z0-9]/.test(password)) score += 1;
  else missing.push("símbolos");
  const levels = ["weak", "weak", "fair", "good", "strong", "strong"] as const;
  const labels = ["Muito fraca", "Fraca", "Regular", "Boa", "Muito forte", "Muito forte"];
  return {
    score,
    level: levels[score],
    text: score < 3 && missing.length ? `Adicione: ${missing.slice(0, 2).join(", ")}` : labels[score],
  };
}

function EyeIcon({ crossed = false }: { crossed?: boolean }) {
  return crossed
    ? <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 4.3A10.9 10.9 0 0 1 12 4c6 0 9.5 8 9.5 8a17 17 0 0 1-2 3.1M6.2 6.2C3.8 8 2.5 12 2.5 12s3.5 8 9.5 8a9.8 9.8 0 0 0 3.3-.6" /></svg>
    : <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" /><circle cx="12" cy="12" r="3" /></svg>;
}

export default function RecoveryExperience({ token, requestSent, initialContext }: RecoveryExperienceProps) {
  const [theme, setTheme] = useState<Theme>(() =>
    typeof document !== "undefined" && document.documentElement.dataset.theme === "dark" ? "dark" : "light",
  );
  const [context, setContext] = useState<RecoveryContext | null>(initialContext ?? null);
  const [loadingContext, setLoadingContext] = useState(!initialContext);
  const [feedback, setFeedback] = useState<Feedback>(() =>
    initialContext?.error
      ? { type: "error", message: initialContext.error }
      : initialContext?.success
        ? { type: "success", message: initialContext.success }
        : null,
  );
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmation, setShowConfirmation] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [emailInvalid, setEmailInvalid] = useState(false);
  const [passwordInvalid, setPasswordInvalid] = useState(false);
  const [confirmationInvalid, setConfirmationInvalid] = useState(false);
  const emailRef = useRef<HTMLInputElement>(null);
  const passwordRef = useRef<HTMLInputElement>(null);
  const confirmationRef = useRef<HTMLInputElement>(null);
  const strength = useMemo(() => passwordStrength(password), [password]);
  const passwordsMatch = Boolean(password && confirmation && password === confirmation);
  const passwordsDiffer = Boolean(password && confirmation && password !== confirmation);

  useEffect(() => {
    const currentTheme = document.documentElement.dataset.theme === "dark" ? "dark" : "light";
    applyTheme(currentTheme);
    const media = window.matchMedia("(prefers-color-scheme: dark)");
    const onSystemThemeChange = (event: MediaQueryListEvent) => {
      if (!window.localStorage.getItem("klubecash-theme")) {
        const nextTheme = event.matches ? "dark" : "light";
        setTheme(nextTheme);
        applyTheme(nextTheme);
      }
    };
    media.addEventListener?.("change", onSystemThemeChange);
    return () => media.removeEventListener?.("change", onSystemThemeChange);
  }, []);

  useEffect(() => {
    if (initialContext) return;

    loadRecoveryContext(token, requestSent)
      .then((nextContext) => {
        setContext(nextContext);
        setFeedback(nextContext.error
          ? { type: "error", message: nextContext.error }
          : nextContext.success
            ? { type: "success", message: nextContext.success }
            : null);
      })
      .catch((error: unknown) => {
        setFeedback({
          type: "error",
          message: error instanceof Error ? error.message : "Erro de comunicação. Tente novamente.",
        });
      })
      .finally(() => setLoadingContext(false));
  }, [initialContext, requestSent, token]);

  useEffect(() => {
    if (context) {
      document.title = `${context.validToken ? "Redefinir Senha" : "Recuperar Senha"} - Klube Cash`;
    }
  }, [context]);

  const toggleTheme = () => {
    const nextTheme: Theme = theme === "dark" ? "light" : "dark";
    setTheme(nextTheme);
    applyTheme(nextTheme);
    window.localStorage.setItem("klubecash-theme", nextTheme);
  };

  const postRecovery = async (body: URLSearchParams) => {
    const response = await fetch("/api/auth/recovery", {
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
      },
      body: body.toString(),
    });
    const payload = (await response.json()) as RecoveryResponse;
    if (!response.ok || !payload.status) throw new Error(payload.message);
    setFeedback({ type: "success", message: payload.message });
    window.setTimeout(() => window.location.assign(payload.redirect ?? "/login"),
      window.matchMedia("(prefers-reduced-motion: reduce)").matches ? 0 : 450);
  };

  const submitRequest = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const normalizedEmail = email.trim();
    if (!normalizedEmail || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalizedEmail)) {
      setEmailInvalid(true);
      setFeedback({ type: "error", message: "Por favor, informe um email válido." });
      emailRef.current?.focus();
      return;
    }
    if (!context?.csrfToken) {
      setFeedback({ type: "error", message: "A sessão segura ainda não está pronta. Tente novamente." });
      return;
    }

    setSubmitting(true);
    setFeedback(null);
    try {
      await postRecovery(new URLSearchParams({
        action: "request",
        csrf_token: context.csrfToken,
        email: normalizedEmail,
      }));
    } catch (error) {
      setFeedback({ type: "error", message: error instanceof Error ? error.message : "Erro de comunicação. Tente novamente." });
    } finally {
      setSubmitting(false);
    }
  };

  const submitReset = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!password) {
      setPasswordInvalid(true);
      setFeedback({ type: "error", message: "Por favor, informe sua nova senha." });
      passwordRef.current?.focus();
      return;
    }
    if (password.length < minimumPasswordLength) {
      setPasswordInvalid(true);
      setFeedback({ type: "error", message: `A senha deve ter no mínimo ${minimumPasswordLength} caracteres.` });
      passwordRef.current?.focus();
      return;
    }
    if (password !== confirmation) {
      setConfirmationInvalid(true);
      setFeedback({ type: "error", message: "As senhas não coincidem." });
      confirmationRef.current?.focus();
      return;
    }
    if (!context?.csrfToken || !token) {
      setFeedback({ type: "error", message: "Token inválido ou expirado. Por favor, solicite uma nova recuperação de senha." });
      return;
    }

    setSubmitting(true);
    setFeedback(null);
    try {
      await postRecovery(new URLSearchParams({
        action: "reset",
        csrf_token: context.csrfToken,
        token,
        password,
        confirm_password: confirmation,
      }));
    } catch (error) {
      setFeedback({ type: "error", message: error instanceof Error ? error.message : "Erro de comunicação. Tente novamente." });
    } finally {
      setSubmitting(false);
    }
  };

  const validToken = Boolean(context?.validToken && token);

  return (
    <main className={styles.root}>
      <div className={styles.background} aria-hidden="true"><span /><span /><span /></div>
      <header className={styles.topbar}>
        <Link href="/" className={styles.brandLink} aria-label="Klube Cash - página inicial">
          <Image src="/assets/images/logolaranja.png" alt="Klube Cash" width={991} height={383} unoptimized priority />
        </Link>
        <div className={styles.topbarActions}>
          <Link href="/login" className={styles.backLink}><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg><span>Voltar ao login</span></Link>
          <button type="button" className={styles.themeToggle} onClick={toggleTheme} aria-label={theme === "dark" ? "Ativar modo claro" : "Ativar modo noturno"} aria-pressed={theme === "dark"} suppressHydrationWarning>
            <svg className={styles.sunIcon} viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.65 17.65l1.42 1.42M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.65 6.35l1.42-1.42" /></svg>
            <svg className={styles.moonIcon} viewBox="0 0 24 24" aria-hidden="true"><path d="M20.5 14.2A8.5 8.5 0 0 1 9.8 3.5 8.5 8.5 0 1 0 20.5 14.2Z" /></svg>
          </button>
        </div>
      </header>

      <section className={`${styles.recoveryCard} ${validToken ? styles.resetState : styles.requestState}`} aria-labelledby="recovery-title">
        <div className={styles.actionPanel}>
          <div className={styles.actionContent}>
            {loadingContext ? (
              <div className={styles.contextLoading} role="status"><span /><p>Preparando ambiente seguro...</p></div>
            ) : (
              <>
                <span className={styles.eyebrow}>{validToken ? "REDEFINIÇÃO SEGURA" : "RECUPERAÇÃO DE ACESSO"}</span>
                <header className={styles.recoveryHeader}>
                  <span className={styles.stateIcon} aria-hidden="true">
                    {validToken
                      ? <svg viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="10" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v2" /></svg>
                      : <svg viewBox="0 0 24 24"><circle cx="8" cy="15" r="4" /><path d="m11 12 8-8M16 7l2 2M14 9l2 2" /></svg>}
                  </span>
                  {validToken ? (
                    <><h1 id="recovery-title">Criar <span>nova senha</span></h1><p>Sua nova senha deve ser segura e fácil de lembrar</p></>
                  ) : (
                    <><h1 id="recovery-title">Recuperar <span>senha</span></h1><p>Não se preocupe! Vamos ajudar você a recuperar o acesso à sua conta</p></>
                  )}
                </header>

                {validToken && context?.maskedEmail ? (
                  <div className={styles.userContext}><span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v12H4z" /><path d="m4 7 8 6 8-6" /></svg></span><div><strong>{context.maskedEmail}</strong><small>Redefinindo senha para esta conta</small></div></div>
                ) : null}

                {!validToken ? <div className={styles.loginPrompt}><span>Lembrou da senha?</span><Link href="/login">Fazer login</Link></div> : null}

                {feedback ? (
                  <div className={`${styles.feedback} ${feedback.type === "success" ? styles.feedbackSuccess : styles.feedbackError}`} role={feedback.type === "error" ? "alert" : "status"} aria-live="polite">
                    <span aria-hidden="true">{feedback.type === "success" ? "✓" : "!"}</span><p>{feedback.message}</p>
                  </div>
                ) : null}

                {validToken ? (
                  <form className={styles.form} onSubmit={submitReset} noValidate>
                    <div className={styles.inputGroup}>
                      <label htmlFor="password">Nova senha</label>
                      <div className={`${styles.inputWrapper} ${passwordInvalid ? styles.invalid : ""}`}>
                        <span className={styles.inputIcon} aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="10" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3" /></svg></span>
                        <input ref={passwordRef} id="password" name="password" type={showPassword ? "text" : "password"} autoComplete="new-password" placeholder="Digite sua nova senha" value={password} onChange={(event) => { setPassword(event.target.value); setPasswordInvalid(false); setConfirmationInvalid(false); }} aria-invalid={passwordInvalid} aria-describedby="strength-text" />
                        <button type="button" className={styles.passwordToggle} onClick={() => setShowPassword((visible) => !visible)} aria-label={showPassword ? "Ocultar senha" : "Mostrar senha"} aria-controls="password" aria-pressed={showPassword}><EyeIcon crossed={showPassword} /></button>
                      </div>
                      <div className={styles.strength} data-level={strength.level}>
                        <div className={styles.strengthTrack} role="progressbar" aria-label="Força da senha" aria-valuemin={0} aria-valuemax={5} aria-valuenow={strength.score}><span style={{ width: password ? `${Math.max(strength.score, 1) * 20}%` : "0%" }} /></div>
                        <p id="strength-text" aria-live="polite">{strength.text}</p>
                      </div>
                    </div>

                    <div className={styles.inputGroup}>
                      <label htmlFor="confirm_password">Confirmar nova senha</label>
                      <div className={`${styles.inputWrapper} ${confirmationInvalid || passwordsDiffer ? styles.invalid : ""} ${passwordsMatch ? styles.valid : ""}`}>
                        <span className={styles.inputIcon} aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 5 6v5c0 4.6 2.8 8.4 7 10 4.2-1.6 7-5.4 7-10V6l-7-3Z" /><path d="m9 12 2 2 4-4" /></svg></span>
                        <input ref={confirmationRef} id="confirm_password" name="confirm_password" type={showConfirmation ? "text" : "password"} autoComplete="new-password" placeholder="Digite novamente sua nova senha" value={confirmation} onChange={(event) => { setConfirmation(event.target.value); setConfirmationInvalid(false); }} aria-invalid={confirmationInvalid || passwordsDiffer} aria-describedby="match-text" />
                        <button type="button" className={styles.passwordToggle} onClick={() => setShowConfirmation((visible) => !visible)} aria-label={showConfirmation ? "Ocultar confirmação de senha" : "Mostrar confirmação de senha"} aria-controls="confirm_password" aria-pressed={showConfirmation}><EyeIcon crossed={showConfirmation} /></button>
                      </div>
                      <p className={`${styles.matchText} ${passwordsMatch ? styles.matchValid : passwordsDiffer ? styles.matchInvalid : ""}`} id="match-text" aria-live="polite">{passwordsMatch ? "✓ Senhas coincidem" : passwordsDiffer ? "✗ Senhas não coincidem" : "As senhas precisam ser iguais"}</p>
                    </div>

                    <button type="submit" className={styles.submitButton} disabled={submitting} aria-busy={submitting}>{submitting ? <span className={styles.spinner} aria-hidden="true" /> : <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 4.6 2.8 8.4 7 10 4.2-1.6 7-5.4 7-10V6l-7-3Z" /><path d="m9 12 2 2 4-4" /></svg>}<span>{submitting ? "Alterando senha..." : "Alterar minha senha"}</span></button>
                  </form>
                ) : (
                  <>
                    <form className={styles.form} onSubmit={submitRequest} noValidate>
                      <div className={styles.inputGroup}>
                        <label htmlFor="recovery-email">E-mail da sua conta</label>
                        <div className={`${styles.inputWrapper} ${emailInvalid ? styles.invalid : ""}`}>
                          <span className={styles.inputIcon} aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 6h16v12H4z" /><path d="m4 7 8 6 8-6" /></svg></span>
                          <input ref={emailRef} id="recovery-email" name="email" type="email" inputMode="email" autoComplete="email" placeholder="Digite o e-mail da sua conta" value={email} onChange={(event) => { setEmail(event.target.value); setEmailInvalid(false); }} aria-invalid={emailInvalid} />
                        </div>
                      </div>
                      <button type="submit" className={styles.submitButton} disabled={submitting || !context?.csrfToken} aria-busy={submitting}>{submitting ? <span className={styles.spinner} aria-hidden="true" /> : <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v12H4z" /><path d="m4 7 8 6 8-6" /></svg>}<span>{submitting ? "Enviando..." : "Enviar instruções"}</span></button>
                    </form>
                    <div className={styles.infoNote}><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 11v5M12 8h.01" /></svg><span>O link de recuperação expira em {context?.expirationHours ?? 2} horas por segurança. Se não receber o e-mail, verifique as pastas Spam e Promoções.</span></div>
                  </>
                )}
              </>
            )}
          </div>
        </div>

        <aside className={styles.storyPanel} aria-label="Informações de recuperação de senha">
          <div className={styles.storyContent}>
            <Image src="/assets/images/logobranco.png" alt="Klube Cash" width={991} height={383} className={styles.whiteLogo} unoptimized priority />
            {validToken ? (
              <div className={styles.securityTips}><span className={styles.storyIcon}><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 4.6 2.8 8.4 7 10 4.2-1.6 7-5.4 7-10V6l-7-3Z" /><path d="m9 12 2 2 4-4" /></svg></span><h2>Dicas para uma senha segura</h2><p>Use pelo menos 8 caracteres, inclua letras maiúsculas e minúsculas, números e símbolos. Evite informações pessoais óbvias.</p><ul><li><span />Combine tipos de caracteres</li><li><span />Use uma senha exclusiva</li><li><span />Não compartilhe sua senha</li></ul></div>
            ) : (
              <div className={styles.processSteps}><span className={styles.storyEyebrow}>PASSO A PASSO</span><h2>Como funciona?</h2><ol><li><span>1</span><p>Digite o e-mail da sua conta</p></li><li><span>2</span><p>Receba o link de recuperação por e-mail</p></li><li><span>3</span><p>Crie uma nova senha segura</p></li><li><span>4</span><p>Faça login com sua nova senha</p></li></ol></div>
            )}
          </div>
          <div className={styles.decoration} aria-hidden="true"><span /><span /><span /><span /></div>
        </aside>
      </section>
    </main>
  );
}
