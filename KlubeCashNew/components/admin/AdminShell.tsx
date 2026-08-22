"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import {
  BarChart3,
  Building2,
  ChevronLeft,
  ChevronRight,
  CircleUserRound,
  ClipboardList,
  CreditCard,
  FileText,
  LayoutDashboard,
  LogOut,
  Mail,
  Menu,
  Moon,
  ReceiptText,
  Search,
  Settings,
  ShieldCheck,
  Sun,
  Tags,
  Users,
  X,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { useAdminContext } from "./AdminProviders";
import styles from "./admin-shell.module.css";

const groups = [
  {
    label: "Operação",
    items: [
      { href: "/admin/dashboard", label: "Visão geral", icon: LayoutDashboard },
      { href: "/admin/usuarios", label: "Usuários", icon: Users },
      { href: "/admin/lojas", label: "Lojas", icon: Building2 },
      { href: "/admin/transacoes", label: "Transações", icon: ReceiptText },
    ],
  },
  {
    label: "Negócio",
    items: [
      {
        href: "/admin/financeiro",
        label: "Financeiro legado",
        icon: CreditCard,
      },
      { href: "/admin/relatorios", label: "Relatórios", icon: BarChart3 },
      { href: "/admin/assinaturas", label: "Assinaturas", icon: ClipboardList },
      { href: "/admin/planos", label: "Planos e códigos", icon: Tags },
    ],
  },
  {
    label: "Comunicação",
    items: [
      { href: "/admin/email-marketing", label: "Campanhas", icon: Mail },
      { href: "/admin/email-templates", label: "Templates", icon: FileText },
    ],
  },
  {
    label: "Sistema",
    items: [
      { href: "/admin/auditoria", label: "Auditoria", icon: ShieldCheck },
      { href: "/admin/configuracoes", label: "Configurações", icon: Settings },
    ],
  },
];

const pageTitles: Array<[RegExp, string, string]> = [
  [/^\/admin\/dashboard$|^\/admin$/, "Dashboard", "Visão geral"],
  [/^\/admin\/usuarios/, "Operação", "Usuários"],
  [/^\/admin\/lojas/, "Operação", "Lojas parceiras"],
  [/^\/admin\/transa/, "Operação", "Transações"],
  [/^\/admin\/financeiro/, "Financeiro", "Histórico legado"],
  [/^\/admin\/relatorios/, "Inteligência", "Relatórios"],
  [/^\/admin\/assinaturas/, "Receita", "Assinaturas"],
  [/^\/admin\/planos/, "Receita", "Planos e códigos"],
  [/^\/admin\/email-marketing/, "Comunicação", "Campanhas"],
  [/^\/admin\/email-templates/, "Comunicação", "Templates"],
  [/^\/admin\/auditoria/, "Segurança", "Auditoria"],
  [/^\/admin\/configuracoes/, "Sistema", "Configurações"],
];

export function AdminShell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const router = useRouter();
  const context = useAdminContext();
  const [collapsed, setCollapsed] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);
  const [profileOpen, setProfileOpen] = useState(false);
  const [dark, setDark] = useState(false);
  const [themeReady, setThemeReady] = useState(false);
  const [search, setSearch] = useState("");
  const profile = useRef<HTMLDivElement>(null);
  const title = pageTitles.find(([pattern]) => pattern.test(pathname)) ?? [
    /.*/,
    "Klube Cash",
    "Admin Master",
  ];

  useEffect(() => {
    const frame = requestAnimationFrame(() => {
      setCollapsed(localStorage.getItem("klube-admin-sidebar") === "collapsed");
      const theme = localStorage.getItem("klube-theme");
      setDark(
        theme === "dark" ||
          (!theme && matchMedia("(prefers-color-scheme: dark)").matches),
      );
      setThemeReady(true);
    });
    return () => cancelAnimationFrame(frame);
  }, []);

  useEffect(() => {
    if (!themeReady) return;
    document.documentElement.dataset.adminTheme = dark ? "dark" : "light";
    localStorage.setItem("klube-theme", dark ? "dark" : "light");
  }, [dark, themeReady]);

  useEffect(() => {
    const frame = requestAnimationFrame(() => {
      setMobileOpen(false);
      setProfileOpen(false);
    });
    return () => cancelAnimationFrame(frame);
  }, [pathname]);

  useEffect(() => {
    document.body.style.overflow = mobileOpen ? "hidden" : "";
    const key = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setMobileOpen(false);
        setProfileOpen(false);
      }
    };
    const click = (event: MouseEvent) => {
      if (profile.current && !profile.current.contains(event.target as Node))
        setProfileOpen(false);
    };
    document.addEventListener("keydown", key);
    document.addEventListener("mousedown", click);
    return () => {
      document.body.style.overflow = "";
      document.removeEventListener("keydown", key);
      document.removeEventListener("mousedown", click);
    };
  }, [mobileOpen]);

  function toggleCollapsed() {
    setCollapsed((current) => {
      localStorage.setItem(
        "klube-admin-sidebar",
        current ? "expanded" : "collapsed",
      );
      return !current;
    });
  }

  function submitSearch(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const term = search.trim().toLocaleLowerCase("pt-BR");
    const target = groups
      .flatMap((group) => group.items)
      .find((item) => item.label.toLocaleLowerCase("pt-BR").includes(term));
    if (target) {
      setSearch("");
      router.push(target.href);
    }
  }

  return (
    <div className={`${styles.app} ${collapsed ? styles.collapsed : ""}`}>
      {mobileOpen && (
        <button
          className={styles.overlay}
          aria-label="Fechar navegação"
          onClick={() => setMobileOpen(false)}
        />
      )}
      <aside
        className={`${styles.sidebar} ${mobileOpen ? styles.open : ""}`}
        aria-label="Navegação administrativa"
      >
        <div className={styles.brand}>
          <Link
            href="/admin/dashboard"
            className={styles.brandMark}
            aria-label="Klube Cash Admin"
          >
            K
          </Link>
          <div className={styles.brandText}>
            <strong>KlubeCash</strong>
            <span>Admin Master</span>
          </div>
          <button
            className={styles.mobileClose}
            onClick={() => setMobileOpen(false)}
            aria-label="Fechar menu"
          >
            <X size={20} />
          </button>
        </div>
        <div className={styles.securityCard}>
          <ShieldCheck size={18} />
          <div>
            <span>Ambiente protegido</span>
            <strong>Modelo sem comissão</strong>
          </div>
        </div>
        <nav className={styles.nav}>
          {groups.map((group) => (
            <div className={styles.navGroup} key={group.label}>
              <span className={styles.navLabel}>{group.label}</span>
              {group.items.map(({ href, label, icon: Icon }) => {
                const active =
                  pathname === href ||
                  pathname.startsWith(`${href}/`) ||
                  (href === "/admin/dashboard" && pathname === "/admin");
                return (
                  <Link
                    key={href}
                    href={href}
                    prefetch
                    className={`${styles.navItem} ${active ? styles.active : ""}`}
                    title={collapsed ? label : undefined}
                  >
                    <Icon size={19} strokeWidth={1.9} />
                    <span>{label}</span>
                  </Link>
                );
              })}
            </div>
          ))}
        </nav>
        <button
          className={styles.collapseButton}
          onClick={toggleCollapsed}
          aria-label={collapsed ? "Expandir menu" : "Recolher menu"}
        >
          {collapsed ? <ChevronRight size={18} /> : <ChevronLeft size={18} />}
          <span>Recolher menu</span>
        </button>
      </aside>
      <div className={styles.workspace}>
        <header className={styles.topbar}>
          <button
            className={styles.mobileMenu}
            onClick={() => setMobileOpen(true)}
            aria-label="Abrir menu"
          >
            <Menu size={22} />
          </button>
          <div className={styles.pageTitle}>
            <span>{title[1] as string}</span>
            <h1>{title[2] as string}</h1>
          </div>
          <form className={styles.search} role="search" onSubmit={submitSearch}>
            <Search size={16} aria-hidden="true" />
            <input
              aria-label="Buscar no Admin"
              list="admin-page-options"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Ir para uma área..."
            />
            <datalist id="admin-page-options">
              {groups
                .flatMap((group) => group.items)
                .map((item) => (
                  <option value={item.label} key={item.href} />
                ))}
            </datalist>
          </form>
          <div className={styles.topActions}>
            <button
              className={styles.iconButton}
              onClick={() => setDark((value) => !value)}
              aria-label={dark ? "Ativar tema claro" : "Ativar tema escuro"}
            >
              {dark ? <Sun size={19} /> : <Moon size={19} />}
            </button>
            <div className={styles.profile} ref={profile}>
              <button
                className={styles.profileButton}
                onClick={() => setProfileOpen((value) => !value)}
                aria-expanded={profileOpen}
              >
                <span className={styles.avatar}>
                  {context.user.avatarInitial}
                </span>
                <span className={styles.profileText}>
                  <strong>{context.user.name}</strong>
                  <small>Administrador</small>
                </span>
              </button>
              {profileOpen && (
                <div className={styles.dropdown}>
                  <span>
                    <CircleUserRound size={17} />
                    {context.user.email}
                  </span>
                  <a href="/logout">
                    <LogOut size={17} />
                    Sair da conta
                  </a>
                </div>
              )}
            </div>
          </div>
        </header>
        <main className={styles.main}>{children}</main>
      </div>
    </div>
  );
}
