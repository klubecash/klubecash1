import Image from "next/image";
import Link from "next/link";
import type { HomeContext } from "@/types/home";

type HeaderProps = Pick<HomeContext, "authenticated" | "user" | "links">;

export function Header({ authenticated, user, links }: HeaderProps) {
  return (
    <header className="modern-header" id="mainHeader">
      <div className="header-container">
        <nav className="main-navigation" aria-label="Navegação principal">
          <Link href="/" className="brand-logo">
            <Image
              src="/assets/images/logolaranja.png"
              alt="Klube Cash"
              className="logo-image"
              width={791}
              height={247}
              unoptimized
            />
          </Link>

          <ul className="desktop-menu">
            <li><a href="#como-funciona" className="nav-link">Como Funciona</a></li>
            <li><a href="#vantagens" className="nav-link">Vantagens</a></li>
            <li><a href="#parceiros" className="nav-link">Parceiros</a></li>
            <li><a href="#sobre" className="nav-link">Sobre</a></li>
          </ul>

          <div className="header-actions">
            <button
              type="button"
              className="theme-toggle"
              id="themeToggle"
              aria-label="Alternar tema"
              aria-pressed="false"
            >
              <span className="theme-icon-sun" aria-hidden="true" />
              <span className="theme-icon-moon" aria-hidden="true" />
            </button>

            {authenticated && user ? (
              <div className="user-menu">
                <button
                  type="button"
                  className="user-button"
                  id="userMenuBtn"
                  aria-haspopup="true"
                  aria-expanded="false"
                  aria-controls="userDropdown"
                >
                  <div className="user-avatar">{user.avatarInitial}</div>
                  <span className="user-name">{user.name}</span>
                </button>
                <div className="user-dropdown" id="userDropdown" role="menu" aria-hidden="true">
                  <a href={user.dashboardUrl} className="dropdown-item" role="menuitem">
                    <span aria-hidden="true">🏠</span>
                    {user.type === "funcionario" ? "Painel da Loja" : "Minha Conta"}
                  </a>
                  <a href="#parceiros" className="dropdown-item" role="menuitem">
                    <span aria-hidden="true">🏪</span>
                    Lojas Parceiras
                  </a>
                  <a href={links.logout} className="dropdown-item" role="menuitem">
                    <span aria-hidden="true">🚪</span>
                    Sair
                  </a>
                </div>
              </div>
            ) : (
              <a href={links.login} className="btn btn-ghost">Entrar</a>
            )}
          </div>

          <button
            type="button"
            className="mobile-menu-toggle"
            id="mobileMenuBtn"
            aria-label="Abrir menu de navegação"
            aria-expanded="false"
            aria-controls="mobileMenu"
          >
            <span className="hamburger-line" />
            <span className="hamburger-line" />
            <span className="hamburger-line" />
          </button>
        </nav>
      </div>

      <div className="mobile-menu" id="mobileMenu" aria-hidden="true">
        <ul className="mobile-nav-list">
          <li><a href="#como-funciona" className="mobile-nav-link">Como Funciona</a></li>
          <li><a href="#vantagens" className="mobile-nav-link">Vantagens</a></li>
          <li><a href="#parceiros" className="mobile-nav-link">Parceiros</a></li>
          <li><a href="#sobre" className="mobile-nav-link">Sobre</a></li>
        </ul>

        {!authenticated ? (
          <div className="mobile-menu-actions">
            <a href={links.login} className="btn btn-ghost">Entrar</a>
            <a href={links.register} className="btn btn-primary">Cadastrar Grátis</a>
          </div>
        ) : null}
      </div>
    </header>
  );
}
