"use client";

import Image from "next/image";
import Link from "next/link";
import { FormEvent, useEffect, useMemo, useRef, useState } from "react";
import styles from "@/app/registro/register.module.css";

type Theme = "light" | "dark";
type Feedback = { type: "error" | "success"; message: string } | null;
type FieldName = "nome" | "email" | "telefone" | "senha";
type FieldErrors = Partial<Record<FieldName, string>>;

type RegisterExperienceProps = {
  initialError: string | null;
  initialSuccess: string | null;
};

type RegisterResponse = {
  status: boolean;
  message: string;
  redirect?: string;
};

const minimumPasswordLength = 8;

const benefits = [
  { label: "Cashback real", icon: "cash" },
  { label: "Processo rápido e seguro", icon: "bolt" },
  { label: "Muitas de lojas parceiras", icon: "target" },
] as const;

function applyTheme(theme: Theme) {
  const root = document.documentElement;
  root.dataset.theme = theme;
  root.style.colorScheme = theme;
  document.querySelector<HTMLMetaElement>('meta[name="theme-color"]')
    ?.setAttribute("content", theme === "dark" ? "#0B0D12" : "#FFF8F3");
}

function formatPhone(value: string) {
  const digits = value.replace(/\D/g, "").slice(0, 11);
  if (!digits) return "";
  if (digits.length <= 2) return `(${digits}`;
  if (digits.length <= 6) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
  if (digits.length <= 10) return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
  return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
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

function BenefitIcon({ icon }: { icon: (typeof benefits)[number]["icon"] }) {
  if (icon === "cash") {
    return <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2" /><circle cx="12" cy="12" r="2.5" /><path d="M7 9H6v1M17 15h1v-1" /></svg>;
  }
  if (icon === "bolt") {
    return <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m13 2-8 12h7l-1 8 8-12h-7l1-8Z" /></svg>;
  }
  return <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" /><circle cx="12" cy="12" r="4" /><path d="M12 2v3M22 12h-3M12 22v-3M2 12h3" /></svg>;
}

export default function RegisterExperience({ initialError, initialSuccess }: RegisterExperienceProps) {
  const [theme, setTheme] = useState<Theme>(() =>
    typeof document !== "undefined" && document.documentElement.dataset.theme === "dark" ? "dark" : "light",
  );
  const [nome, setNome] = useState("");
  const [email, setEmail] = useState("");
  const [telefone, setTelefone] = useState("");
  const [senha, setSenha] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [errors, setErrors] = useState<FieldErrors>({});
  const [feedback, setFeedback] = useState<Feedback>(() =>
    initialError
      ? { type: "error", message: initialError }
      : initialSuccess
        ? { type: "success", message: initialSuccess }
        : null,
  );
  const nomeRef = useRef<HTMLInputElement>(null);
  const emailRef = useRef<HTMLInputElement>(null);
  const telefoneRef = useRef<HTMLInputElement>(null);
  const senhaRef = useRef<HTMLInputElement>(null);

  const strength = useMemo(() => passwordStrength(senha), [senha]);
  const completedSteps = useMemo(() => {
    let steps = 0;
    if (nome && email) steps = 1;
    if (nome && email && telefone) steps = 2;
    if (nome && email && telefone && senha) steps = 3;
    return steps;
  }, [nome, email, telefone, senha]);

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
    const timeout = window.setTimeout(() => setFeedback(null), 6500);
    return () => window.clearTimeout(timeout);
  }, [feedback]);

  const toggleTheme = () => {
    const nextTheme: Theme = theme === "dark" ? "light" : "dark";
    setTheme(nextTheme);
    applyTheme(nextTheme);
    window.localStorage.setItem("klubecash-theme", nextTheme);
  };

  const clearError = (field: FieldName) => {
    if (errors[field]) setErrors((current) => ({ ...current, [field]: undefined }));
  };

  const validate = () => {
    const nextErrors: FieldErrors = {};
    if (!nome.trim() || nome.trim().length < 3) {
      nextErrors.nome = "Por favor, informe seu nome completo (mínimo 3 caracteres).";
    }
    if (!email.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) {
      nextErrors.email = "Por favor, informe um email válido.";
    }
    if (telefone.replace(/\D/g, "").length < 10) {
      nextErrors.telefone = "Por favor, informe um telefone válido.";
    }
    if (senha.length < minimumPasswordLength) {
      nextErrors.senha = `A senha deve ter no mínimo ${minimumPasswordLength} caracteres.`;
    }

    setErrors(nextErrors);
    const firstInvalid = (["nome", "email", "telefone", "senha"] as FieldName[])
      .find((field) => Boolean(nextErrors[field]));
    if (firstInvalid) {
      const invalidRef = {
        nome: nomeRef,
        email: emailRef,
        telefone: telefoneRef,
        senha: senhaRef,
      }[firstInvalid];
      invalidRef.current?.focus();
      setFeedback({ type: "error", message: nextErrors[firstInvalid] as string });
    }
    return !firstInvalid;
  };

  const submitRegistration = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setFeedback(null);
    if (!validate()) return;

    setSubmitting(true);
    try {
      const response = await fetch("/api/auth/register", {
        method: "POST",
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
        },
        body: new URLSearchParams({
          nome: nome.trim(),
          email: email.trim(),
          telefone,
          senha,
        }).toString(),
      });
      const payload = (await response.json()) as RegisterResponse;
      if (!response.ok || !payload.status) {
        setFeedback({ type: "error", message: payload.message });
        return;
      }

      setFeedback({ type: "success", message: payload.message });
      window.setTimeout(() => {
        window.location.assign(payload.redirect ?? "/login?success=cadastro_realizado");
      }, window.matchMedia("(prefers-reduced-motion: reduce)").matches ? 0 : 450);
    } catch {
      setFeedback({ type: "error", message: "Erro de comunicação. Tente novamente." });
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <main className={styles.root}>
      <div className={styles.background} aria-hidden="true"><span /><span /><span /></div>

      <header className={styles.topbar}>
        <Link href="/" className={styles.brandLink} aria-label="Klube Cash - página inicial">
          <Image src="/assets/images/logolaranja.png" alt="Klube Cash" width={991} height={383} unoptimized priority />
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
            <svg className={styles.sunIcon} viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.65 17.65l1.42 1.42M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.65 6.35l1.42-1.42" /></svg>
            <svg className={styles.moonIcon} viewBox="0 0 24 24" aria-hidden="true"><path d="M20.5 14.2A8.5 8.5 0 0 1 9.8 3.5 8.5 8.5 0 1 0 20.5 14.2Z" /></svg>
          </button>
        </div>
      </header>

      <section className={styles.registerShell} aria-labelledby="register-title">
        <div className={styles.formPanel}>
          <div className={styles.formContent}>
            <span className={styles.eyebrow}>CONTA GRATUITA</span>
            <header className={styles.registerHeader}>
              <h1 id="register-title">Crie sua <span>conta</span></h1>
              <p>Comece a ganhar dinheiro de volta em suas compras</p>
            </header>

            <div className={styles.loginPrompt}>
              <span>Já tem uma conta?</span>
              <Link href="/login">Fazer login</Link>
            </div>

            <div
              className={styles.progress}
              role="progressbar"
              aria-label="Progresso do cadastro"
              aria-valuemin={0}
              aria-valuemax={3}
              aria-valuenow={completedSteps}
            >
              {[1, 2, 3].map((step) => <span key={step} className={completedSteps >= step ? styles.progressActive : ""} />)}
            </div>

            <form className={styles.form} onSubmit={submitRegistration} noValidate>
              <fieldset className={styles.formSection}>
                <legend><span>1</span> Suas informações</legend>
                <div className={styles.fieldsGrid}>
                  <div className={`${styles.inputGroup} ${styles.fullField}`}>
                    <label htmlFor="nome">Nome completo</label>
                    <div className={`${styles.inputWrapper} ${errors.nome ? styles.invalid : ""}`}>
                      <span className={styles.inputIcon} aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" /></svg></span>
                      <input ref={nomeRef} id="nome" name="nome" type="text" autoComplete="name" placeholder="Digite seu nome completo" value={nome} onChange={(event) => { setNome(event.target.value); clearError("nome"); }} aria-invalid={Boolean(errors.nome)} aria-describedby="nome-error" />
                    </div>
                    <span className={styles.fieldError} id="nome-error" aria-live="polite">{errors.nome}</span>
                  </div>

                  <div className={styles.inputGroup}>
                    <label htmlFor="email">Email</label>
                    <div className={`${styles.inputWrapper} ${errors.email ? styles.invalid : ""}`}>
                      <span className={styles.inputIcon} aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 6h16v12H4z" /><path d="m4 7 8 6 8-6" /></svg></span>
                      <input ref={emailRef} id="email" name="email" type="email" inputMode="email" autoComplete="email" placeholder="seu@email.com" value={email} onChange={(event) => { setEmail(event.target.value); clearError("email"); }} aria-invalid={Boolean(errors.email)} aria-describedby="register-email-error" />
                    </div>
                    <span className={styles.fieldError} id="register-email-error" aria-live="polite">{errors.email}</span>
                  </div>

                  <div className={styles.inputGroup}>
                    <label htmlFor="telefone">Telefone</label>
                    <div className={`${styles.inputWrapper} ${errors.telefone ? styles.invalid : ""}`}>
                      <span className={styles.inputIcon} aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M7 3h3l1.5 4-2 1.5a16 16 0 0 0 6 6l1.5-2L21 14v3c0 2.2-1.8 4-4 4C9.3 21 3 14.7 3 7c0-2.2 1.8-4 4-4Z" /></svg></span>
                      <input ref={telefoneRef} id="telefone" name="telefone" type="tel" inputMode="tel" autoComplete="tel" placeholder="(00) 00000-0000" value={telefone} onChange={(event) => { setTelefone(formatPhone(event.target.value)); clearError("telefone"); }} aria-invalid={Boolean(errors.telefone)} aria-describedby="telefone-error" />
                    </div>
                    <span className={styles.fieldError} id="telefone-error" aria-live="polite">{errors.telefone}</span>
                  </div>
                </div>
              </fieldset>

              <fieldset className={styles.formSection}>
                <legend><span>2</span> Crie sua senha</legend>
                <div className={styles.inputGroup}>
                  <label htmlFor="senha">Senha</label>
                  <div className={`${styles.inputWrapper} ${errors.senha ? styles.invalid : ""}`}>
                    <span className={styles.inputIcon} aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="10" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3" /></svg></span>
                    <input ref={senhaRef} id="senha" name="senha" type={showPassword ? "text" : "password"} autoComplete="new-password" placeholder="Crie uma senha segura" value={senha} onChange={(event) => { setSenha(event.target.value); clearError("senha"); }} aria-invalid={Boolean(errors.senha)} aria-describedby="senha-error password-strength" />
                    <button type="button" className={styles.passwordToggle} onClick={() => setShowPassword((visible) => !visible)} aria-label={showPassword ? "Ocultar senha" : "Mostrar senha"} aria-controls="senha" aria-pressed={showPassword}>
                      {showPassword ? <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 4.3A10.9 10.9 0 0 1 12 4c6 0 9.5 8 9.5 8a17 17 0 0 1-2 3.1M6.2 6.2C3.8 8 2.5 12 2.5 12s3.5 8 9.5 8a9.8 9.8 0 0 0 3.3-.6" /></svg> : <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" /><circle cx="12" cy="12" r="3" /></svg>}
                    </button>
                  </div>
                  <span className={styles.fieldError} id="senha-error" aria-live="polite">{errors.senha}</span>
                  <div className={styles.strength} id="password-strength" aria-live="polite" data-level={strength.level}>
                    <div className={styles.strengthTrack} aria-hidden="true"><span style={{ width: senha ? `${Math.max(strength.score, 1) * 20}%` : "0%" }} /></div>
                    <div className={styles.strengthCopy}><span>{strength.text}</span><span>Mínimo de 8 caracteres</span></div>
                  </div>
                </div>
              </fieldset>

              <button type="submit" className={styles.submitButton} disabled={submitting} aria-busy={submitting}>
                {submitting ? <span className={styles.spinner} aria-hidden="true" /> : <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4 5v6c0 5.1 3.3 9.4 8 11 4.7-1.6 8-5.9 8-11V5l-8-3Z" /><path d="m9 12 2 2 4-4" /></svg>}
                <span>{submitting ? "Criando sua conta..." : "Criar minha conta gratuita"}</span>
                {!submitting ? <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M14 7l5 5-5 5" /></svg> : null}
              </button>
            </form>

            <p className={styles.privacyNote}>Cadastro seguro e gratuito. Seus dados são usados apenas para sua experiência no Klube Cash.</p>
          </div>
        </div>

        <aside className={styles.brandPanel}>
          <div className={styles.brandContent}>
            <span className={styles.brandBadge}>SEU CASHBACK COMEÇA AQUI</span>
            <Image src="/assets/images/logobranco.png" alt="Klube Cash" className={styles.whiteLogo} width={991} height={383} unoptimized priority />
            <div className={styles.brandCopy}>
              <h2>Comprar bem é receber de volta.</h2>
              <p>Uma conta. Muitas lojas. Mais valor em cada compra.</p>
            </div>
            <div className={styles.benefits}>
              <h3>Por que escolher o Klube Cash?</h3>
              <ul>{benefits.map((benefit) => <li key={benefit.label}><span><BenefitIcon icon={benefit.icon} /></span><strong>{benefit.label}</strong></li>)}</ul>
            </div>
          </div>
          <div className={styles.decoration} aria-hidden="true"><span /><span /><span /><span /></div>
        </aside>
      </section>

      {feedback ? (
        <div className={`${styles.toast} ${feedback.type === "success" ? styles.toastSuccess : styles.toastError}`} role={feedback.type === "error" ? "alert" : "status"} aria-live="polite">
          <span className={styles.toastIcon} aria-hidden="true">{feedback.type === "success" ? "✓" : "!"}</span>
          <span>{feedback.message}</span>
          <button type="button" onClick={() => setFeedback(null)} aria-label="Fechar mensagem">×</button>
        </div>
      ) : null}
    </main>
  );
}
