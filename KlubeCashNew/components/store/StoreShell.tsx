"use client";

import Link from "next/link";
import Image from "next/image";
import { usePathname, useRouter } from "next/navigation";
import {
  BadgeDollarSign,
  ChevronLeft,
  ChevronRight,
  CircleUserRound,
  LayoutDashboard,
  LogOut,
  Menu,
  Moon,
  PlusCircle,
  ReceiptText,
  Settings,
  Store,
  Sun,
  Upload,
  Users,
  X,
} from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import { useStoreContext } from "./StoreProviders";
import styles from "./store-shell.module.css";

const items = [
  { href: "/store/dashboard", label: "Visão geral", icon: LayoutDashboard },
  {
    href: "/store/registrar-transacao",
    label: "Nova venda",
    icon: PlusCircle,
    accent: true,
  },
  { href: "/store/transacoes", label: "Transações", icon: ReceiptText },
  { href: "/store/upload-lote", label: "Upload em lote", icon: Upload },
  {
    href: "/store/funcionarios",
    label: "Funcionários",
    icon: Users,
    permission: "manageEmployees" as const,
  },
  { href: "/store/meu-plano", label: "Meu plano", icon: BadgeDollarSign },
];

const titles: Record<string, { title: string; eyebrow: string }> = {
  "/store/dashboard": { title: "Visão geral", eyebrow: "Dashboard" },
  "/store/registrar-transacao": {
    title: "Registrar nova venda",
    eyebrow: "Vendas",
  },
  "/store/transacoes": { title: "Minhas transações", eyebrow: "Vendas" },
  "/store/upload-lote": { title: "Upload em lote", eyebrow: "Vendas" },
  "/store/funcionarios": { title: "Equipe da loja", eyebrow: "Gestão" },
  "/store/perfil": { title: "Perfil da loja", eyebrow: "Configurações" },
  "/store/meu-plano": { title: "Meu plano", eyebrow: "Assinatura" },
};

export function StoreShell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const router = useRouter();
  const context = useStoreContext();
  const [collapsed, setCollapsed] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);
  const [menuOpen, setMenuOpen] = useState(false);
  const [dark, setDark] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);
  const current = titles[pathname] ?? {
    title: "Área da loja",
    eyebrow: "Klube Cash",
  };

  useEffect(() => {
    const frame = requestAnimationFrame(() => {
      const savedCollapsed =
        localStorage.getItem("klube-store-sidebar") === "collapsed";
      const savedTheme = localStorage.getItem("klube-theme");
      const shouldDark =
        savedTheme === "dark" ||
        (!savedTheme && matchMedia("(prefers-color-scheme: dark)").matches);
      setCollapsed(savedCollapsed);
      setDark(shouldDark);
    });
    return () => cancelAnimationFrame(frame);
  }, []);

  useEffect(() => {
    document.documentElement.dataset.storeTheme = dark ? "dark" : "light";
    localStorage.setItem("klube-theme", dark ? "dark" : "light");
  }, [dark]);

  useEffect(() => {
    const frame = requestAnimationFrame(() => {
      setMobileOpen(false);
      setMenuOpen(false);
    });
    return () => cancelAnimationFrame(frame);
  }, [pathname]);

  useEffect(() => {
    document.body.style.overflow = mobileOpen ? "hidden" : "";
    const close = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setMobileOpen(false);
        setMenuOpen(false);
      }
    };
    document.addEventListener("keydown", close);
    return () => {
      document.body.style.overflow = "";
      document.removeEventListener("keydown", close);
    };
  }, [mobileOpen]);

  useEffect(() => {
    const allowedWithoutPlan = [
      "/store/registrar-transacao",
      "/store/meu-plano",
    ];
    if (
      !context.subscription.active &&
      !allowedWithoutPlan.includes(pathname)
    ) {
      router.replace("/store/meu-plano?notice=plan-required");
    }
  }, [context.subscription.active, pathname, router]);

  const navItems = useMemo(
    () =>
      items.filter(
        (item) => !item.permission || context.permissions[item.permission],
      ),
    [context.permissions],
  );

  function toggleCollapsed() {
    setCollapsed((value) => {
      localStorage.setItem(
        "klube-store-sidebar",
        value ? "expanded" : "collapsed",
      );
      return !value;
    });
  }

  return (
    <div className={`${styles.app} ${collapsed ? styles.collapsed : ""}`}>
      {mobileOpen && (
        <button
          className={styles.overlay}
          aria-label="Fechar menu"
          onClick={() => setMobileOpen(false)}
        />
      )}
      <aside
        className={`${styles.sidebar} ${mobileOpen ? styles.open : ""}`}
        aria-label="Navegação da loja"
      >
        <div className={styles.brand}>
          <Link
            href="/store/dashboard"
            className={styles.brandMark}
            aria-label="Klube Cash - Visão geral"
          >
            K
          </Link>
          <div className={styles.brandText}>
            <strong>Klube</strong>
            <span>Cash</span>
          </div>
          <button
            className={styles.mobileClose}
            onClick={() => setMobileOpen(false)}
            aria-label="Fechar menu"
          >
            <X size={20} />
          </button>
        </div>

        <div className={styles.storeCard}>
          <div className={styles.storeLogo}>
            {context.store.logoUrl ? (
              <Image
                src={context.store.logoUrl}
                alt=""
                width={38}
                height={38}
                unoptimized
              />
            ) : (
              <Store size={19} />
            )}
          </div>
          <div>
            <span>Sua loja</span>
            <strong title={context.store.name}>{context.store.name}</strong>
          </div>
        </div>

        <nav className={styles.nav}>
          <span className={styles.navLabel}>Navegação</span>
          {navItems.map(({ href, label, icon: Icon, accent }) => {
            const active =
              pathname === href ||
              (href === "/store/dashboard" && pathname === "/store");
            return (
              <Link
                key={href}
                href={href}
                prefetch
                className={`${styles.navItem} ${active ? styles.active : ""} ${accent ? styles.accent : ""}`}
                title={collapsed ? label : undefined}
              >
                <Icon size={20} strokeWidth={1.9} />
                <span>{label}</span>
              </Link>
            );
          })}
        </nav>

        <div className={styles.sideFooter}>
          <Link
            href="/store/perfil"
            className={`${styles.navItem} ${pathname === "/store/perfil" ? styles.active : ""}`}
          >
            <Settings size={20} />
            <span>Configurações</span>
          </Link>
          <button
            className={styles.collapseButton}
            onClick={toggleCollapsed}
            aria-label={collapsed ? "Expandir menu" : "Recolher menu"}
          >
            {collapsed ? <ChevronRight size={18} /> : <ChevronLeft size={18} />}
            <span>Recolher menu</span>
          </button>
        </div>
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
            <span>{current.eyebrow}</span>
            <h1>{current.title}</h1>
          </div>
          <div className={styles.topActions}>
            <button
              className={styles.iconButton}
              onClick={() => setDark((value) => !value)}
              aria-label={dark ? "Ativar tema claro" : "Ativar tema escuro"}
            >
              {dark ? <Sun size={19} /> : <Moon size={19} />}
            </button>
            <div className={styles.userMenu} ref={menuRef}>
              <button
                className={styles.userButton}
                onClick={() => setMenuOpen((value) => !value)}
                aria-expanded={menuOpen}
              >
                <span className={styles.avatar}>
                  {context.user.avatarInitial}
                </span>
                <span className={styles.userText}>
                  <strong>{context.user.name}</strong>
                  <small>{context.user.subtype ?? "Lojista"}</small>
                </span>
              </button>
              {menuOpen && (
                <div className={styles.dropdown}>
                  <Link href="/store/perfil">
                    <CircleUserRound size={18} /> Perfil da loja
                  </Link>
                  <a href="/logout">
                    <LogOut size={18} /> Sair da conta
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
