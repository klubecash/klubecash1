"use client";

import Image from "next/image";
import Link from "next/link";
import { FormEvent, useEffect, useRef, useState } from "react";
import styles from "@/app/login/login.module.css";

type Theme = "light" | "dark";
type Feedback = { type: "error" | "success"; message: string } | null;

type LoginExperienceProps = {
  initialError: string | null;
  initialSuccess: string | null;
  forceLogin: boolean;
};

type LoginResponse = {
  status: boolean;
  message?: string;
  redirect?: string;
};

const features = [
  "Cashback real",
  "Muitas lojas parceiras",
  "Sem taxas ou anuidades",
  "Utilize em lojas que ele foi gerado",
];

function applyTheme(theme: Theme) {
  const root = document.documentElement;
  root.dataset.theme = theme;
  root.style.colorScheme = theme;
  const themeColor = document.querySelector<HTMLMetaElement>('meta[name="theme-color"]');
  themeColor?.setAttribute("content", theme === "dark" ? "#0B0D12" : "#FFF8F3");
}

export default function LoginExperience({
  initialError,
  initialSuccess,
  forceLogin,
}: LoginExperienceProps) {
  const [theme, setTheme] = useState<Theme>(() =>
    typeof document !== "undefined" && document.documentElement.dataset.theme === "dark"
      ? "dark"
      : "light",
  );
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [emailError, setEmailError] = useState("");
  const [passwordError, setPasswordError] = useState("");
  const [feedback, setFeedback] = useState<Feedback>(() =>
    initialError
      ? { type: "error", message: initialError }
      : initialSuccess
        ? { type: "success", message: initialSuccess }
        : null,
  );
  const emailRef = useRef<HTMLInputElement>(null);
  const passwordRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    const currentTheme = document.documentElement.dataset.theme === "dark" ? "dark" : "light";
    applyTheme(currentTheme);

    if (initialError || initialSuccess) {
      const url = new URL(window.location.href);
      url.searchParams.delete("error");
      url.searchParams.delete("success");
      window.history.replaceState({}, "", `${url.pathname}${url.search}${url.hash}`);
    }

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
  }, [initialError, initialSuccess]);

  useEffect(() => {
    if (!feedback) return;
    const timeout = window.setTimeout(() => setFeedback(null), 5500);
    return () => window.clearTimeout(timeout);
  }, [feedback]);

  useEffect(() => {
    const closeOnEscape = (event: KeyboardEvent) => {
      if (event.key === "Escape") setFeedback(null);
    };
    document.addEventListener("keydown", closeOnEscape);
    return () => document.removeEventListener("keydown", closeOnEscape);
  }, []);

  const toggleTheme = () => {
    const nextTheme: Theme = theme === "dark" ? "light" : "dark";
    setTheme(nextTheme);
    applyTheme(nextTheme);
    window.localStorage.setItem("klubecash-theme", nextTheme);
  };

  const validate = () => {
    const normalizedEmail = email.trim();
    let nextEmailError = "";
    let nextPasswordError = "";

    if (!normalizedEmail) {
      nextEmailError = "Por favor, informe seu e-mail.";
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalizedEmail)) {
      nextEmailError = "Por favor, informe um e-mail válido.";
    }
    if (!password) nextPasswordError = "Por favor, informe sua senha.";

    setEmailError(nextEmailError);
    setPasswordError(nextPasswordError);
    if (nextEmailError) emailRef.current?.focus();
    else if (nextPasswordError) passwordRef.current?.focus();
    return !nextEmailError && !nextPasswordError;
  };

  const submitLogin = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setFeedback(null);
    if (!validate()) return;

    setSubmitting(true);
    try {
      const endpoint = forceLogin ? "/api/auth/login?force_login=1" : "/api/auth/login";
      const response = await fetch(endpoint, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
        },
        body: new URLSearchParams({ email: email.trim(), password }).toString(),
      });
      const payload = (await response.json()) as LoginResponse;

      if (!response.ok || !payload.status) {
        setFeedback({
          type: "error",
          message: payload.message ?? "Não foi possível entrar. Verifique seus dados e tente novamente.",
        });
        return;
      }

      setFeedback({ type: "success", message: payload.message ?? "Login efetuado com sucesso." });
      window.setTimeout(() => {
        window.location.assign(payload.redirect ?? "/");
      }, window.matchMedia("(prefers-reduced-motion: reduce)").matches ? 0 : 350);
    } catch {
      setFeedback({ type: "error", message: "Erro de comunicação. Tente novamente." });
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <main className={styles.root}>
      <div className={styles.ambientGlow} aria-hidden="true" />
      <header className={styles.topbar}>
        <Link href="/" className={styles.brandLink} aria-label="Klube Cash - página inicial">
          <Image
            src="/assets/images/logolaranja.png"
            alt="Klube Cash"
            width={991}
            height={383}
            unoptimized
            priority
          />
        </Link>
        <div className={styles.topbarActions}>
          <Link href="/" className={styles.backLink}>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
            <span>Voltar ao início</span>
          </Link>
          <button
            type="button"
            className={styles.themeToggle}
            onClick={toggleTheme}
            aria-label={theme === "dark" ? "Ativar modo claro" : "Ativar modo noturno"}
            aria-pressed={theme === "dark"}
            suppressHydrationWarning
          >
            <svg className={styles.sunIcon} viewBox="0 0 24 24" aria-hidden="true">
              <circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.65 17.65l1.42 1.42M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.65 6.35l1.42-1.42" />
            </svg>
            <svg className={styles.moonIcon} viewBox="0 0 24 24" aria-hidden="true">
              <path d="M20.5 14.2A8.5 8.5 0 0 1 9.8 3.5 8.5 8.5 0 1 0 20.5 14.2Z" />
            </svg>
          </button>
        </div>
      </header>

      <section className={styles.loginShell} aria-labelledby="login-title">
        <aside className={styles.brandPanel}>
          <div className={styles.brandContent}>
            <span className={styles.secureBadge}>
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 4.6 2.8 8.4 7 10 4.2-1.6 7-5.4 7-10V6l-7-3Z" /><path d="m9 12 2 2 4-4" /></svg>
              Acesso seguro
            </span>
            <Image
              src="/assets/images/logobranco.png"
              alt="Klube Cash"
              className={styles.whiteLogo}
              width={991}
              height={383}
              unoptimized
              priority
            />
            <div className={styles.welcomeCopy}>
              <h1>Bem-vindo de volta!</h1>
              <p>Entre na sua conta e continue transformando suas compras em dinheiro de volta.</p>
            </div>
            <ul className={styles.featureList}>
              {features.map((feature) => (
                <li key={feature}>
                  <span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m7 12 3 3 7-7" /></svg></span>
                  {feature}
                </li>
              ))}
            </ul>
          </div>
          <div className={styles.decoration} aria-hidden="true">
            <span /><span /><span /><span />
          </div>
        </aside>

        <div className={styles.formPanel}>
          <div className={styles.formContent}>
            <span className={styles.formEyebrow}>ÁREA DO CLIENTE</span>
            <header className={styles.formHeader}>
              <h2 id="login-title">Entrar</h2>
              <p>Não tem conta? <a href="/registro">Cadastre-se grátis</a></p>
            </header>

            <form className={styles.form} onSubmit={submitLogin} noValidate>
              <div className={styles.inputGroup}>
                <label htmlFor="email">E-mail</label>
                <div className={`${styles.inputWrapper} ${emailError ? styles.invalid : ""}`}>
                  <span className={styles.inputIcon} aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M4 6h16v12H4z" /><path d="m4 7 8 6 8-6" /></svg>
                  </span>
                  <input
                    ref={emailRef}
                    id="email"
                    name="email"
                    type="email"
                    inputMode="email"
                    autoComplete="email"
                    placeholder="Digite seu e-mail"
                    value={email}
                    onChange={(event) => { setEmail(event.target.value); if (emailError) setEmailError(""); }}
                    aria-invalid={Boolean(emailError)}
                    aria-describedby="email-error"
                  />
                </div>
                <span className={styles.fieldError} id="email-error" aria-live="polite">{emailError}</span>
              </div>

              <div className={styles.inputGroup}>
                <div className={styles.labelRow}>
                  <label htmlFor="password">Senha</label>
                  <a href="/recuperar-senha">Esqueci minha senha</a>
                </div>
                <div className={`${styles.inputWrapper} ${passwordError ? styles.invalid : ""}`}>
                  <span className={styles.inputIcon} aria-hidden="true">
                    <svg viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="10" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3" /></svg>
                  </span>
                  <input
                    ref={passwordRef}
                    id="password"
                    name="password"
                    type={showPassword ? "text" : "password"}
                    autoComplete="current-password"
                    placeholder="Digite sua senha"
                    value={password}
                    onChange={(event) => { setPassword(event.target.value); if (passwordError) setPasswordError(""); }}
                    aria-invalid={Boolean(passwordError)}
                    aria-describedby="password-error"
                  />
                  <button
                    type="button"
                    className={styles.passwordToggle}
                    onClick={() => setShowPassword((visible) => !visible)}
                    aria-label={showPassword ? "Ocultar senha" : "Mostrar senha"}
                    aria-controls="password"
                    aria-pressed={showPassword}
                  >
                    {showPassword ? (
                      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 4.3A10.9 10.9 0 0 1 12 4c6 0 9.5 8 9.5 8a17 17 0 0 1-2 3.1M6.2 6.2C3.8 8 2.5 12 2.5 12s3.5 8 9.5 8a9.8 9.8 0 0 0 3.3-.6" /></svg>
                    ) : (
                      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" /><circle cx="12" cy="12" r="3" /></svg>
                    )}
                  </button>
                </div>
                <span className={styles.fieldError} id="password-error" aria-live="polite">{passwordError}</span>
              </div>

              <button type="submit" className={styles.submitButton} disabled={submitting}>
                {submitting ? <span className={styles.spinner} aria-hidden="true" /> : null}
                <span>{submitting ? "Entrando..." : "Entrar"}</span>
                {!submitting ? <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M14 7l5 5-5 5" /></svg> : null}
              </button>
            </form>

            <div className={styles.trustNote}>
              <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3" /></svg>
              <span>Ambiente protegido. Seus dados continuam seguros.</span>
            </div>
          </div>
        </div>
      </section>

      {feedback ? (
        <div className={`${styles.toast} ${feedback.type === "success" ? styles.toastSuccess : styles.toastError}`} role={feedback.type === "error" ? "alert" : "status"} aria-live="polite">
          <span className={styles.toastIcon} aria-hidden="true">
            {feedback.type === "success" ? "✓" : "!"}
          </span>
          <span>{feedback.message}</span>
          <button type="button" onClick={() => setFeedback(null)} aria-label="Fechar mensagem">×</button>
        </div>
      ) : null}
    </main>
  );
}
