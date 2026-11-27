"use client";

import { useEffect, useState } from "react";
import { iconeDoApp } from "@/lib/app-icons";
import styles from "./apps-menu.module.css";

interface MenuItem {
  slug: string;
  nome: string;
  url: string;
  atual: boolean;
}

interface Menu {
  portalUrl: string;
  itens: MenuItem[];
}

let pedido: Promise<Menu | null> | undefined;

/** Pede a lista uma vez por carregamento de pagina. Sem lista (login por senha, sessao antiga) ou com erro: nada. */
function carregar(): Promise<Menu | null> {
  pedido ??= fetch("/api/store/apps", { cache: "no-store", credentials: "same-origin" })
    .then((resposta) => (resposta.ok ? resposta.json() : null))
    .then((corpo) => (corpo && typeof corpo === "object" && corpo.menu && Array.isArray(corpo.menu.itens) ? (corpo.menu as Menu) : null))
    .catch(() => null);
  return pedido;
}

function Logo({ slug, nome }: { slug: string; nome: string }) {
  const icone = iconeDoApp(slug);
  return icone ? (
    <span className={`${styles.icone} ${styles.logo}`} aria-hidden="true" dangerouslySetInnerHTML={{ __html: icone.svg }} />
  ) : (
    <span className={styles.icone} aria-hidden="true">{nome.slice(0, 1).toUpperCase()}</span>
  );
}

/**
 * Menu "Apps" do Grupo Klube (trocar de aplicativo e voltar ao Portal) para os paineis do lojista e do admin.
 * So aparece para quem abre 2 ou mais aplicativos; a lista vem do login unico, guardada na sessao no login.
 */
export function AppsMenu({ tone }: { tone: "store" | "admin" }) {
  const [menu, setMenu] = useState<Menu | null>(null);

  useEffect(() => {
    let ativo = true;
    void carregar().then((resultado) => {
      if (ativo) setMenu(resultado);
    });
    return () => {
      ativo = false;
    };
  }, []);

  if (!menu) return null;
  return (
    <details className={`${styles.menu} ${tone === "admin" ? styles.admin : styles.store}`}>
      <summary className={styles.botao} aria-label="Trocar de aplicativo">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          {[5, 12, 19].flatMap((x) => [5, 12, 19].map((y) => <circle key={`${x}-${y}`} cx={x} cy={y} r="1.8" />))}
        </svg>
        <span className={styles.botaoTexto}>Apps</span>
      </summary>
      <div className={styles.painel} role="menu" aria-label="Aplicativos do Grupo Klube">
        <a role="menuitem" className={styles.item} href={menu.portalUrl} rel="noopener noreferrer">
          <Logo slug="klube-intranet" nome="Portal" />
          <span>Portal Grupo Klube<small>Todos os seus aplicativos</small></span>
        </a>
        <div className={styles.divisor} role="separator" />
        {menu.itens.map((item) => (
          <a
            key={item.slug}
            role="menuitem"
            className={styles.item}
            href={item.url}
            aria-current={item.atual ? "true" : undefined}
            {...(item.atual ? {} : { rel: "noopener noreferrer" })}
          >
            <Logo slug={item.slug} nome={item.nome} />
            <span>{item.nome}{item.atual ? <small>Você está aqui</small> : null}</span>
          </a>
        ))}
      </div>
    </details>
  );
}
