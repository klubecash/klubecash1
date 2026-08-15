import { fireEvent, render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { HomepageInteractions } from "@/components/HomepageInteractions";

function mediaQuery(matches = false): MediaQueryList {
  return {
    matches,
    media: "",
    onchange: null,
    addListener: vi.fn(),
    removeListener: vi.fn(),
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    dispatchEvent: vi.fn(),
  };
}

describe("interações da homepage", () => {
  beforeEach(() => {
    window.localStorage.clear();
    Object.defineProperty(window, "matchMedia", {
      configurable: true,
      value: vi.fn().mockImplementation(() => mediaQuery(false)),
    });
    document.documentElement.setAttribute("data-theme", "light");
  });

  it("alterna e persiste o tema", () => {
    render(
      <>
        <meta id="themeColor" data-light="#FFF8F3" data-dark="#0B0D12" />
        <button id="themeToggle" type="button" aria-label="Alternar tema">
          <span className="theme-icon-sun" />
          <span className="theme-icon-moon" />
        </button>
        <HomepageInteractions />
      </>,
    );

    fireEvent.click(screen.getByRole("button"));
    expect(document.documentElement).toHaveAttribute("data-theme", "dark");
    expect(window.localStorage.getItem("klubecash-theme")).toBe("dark");
    expect(screen.getByRole("button")).toHaveAttribute("aria-label", "Ativar modo claro");
  });

  it("abre e fecha o menu mobile com foco e Escape", () => {
    render(
      <>
        <button id="mobileMenuBtn" type="button" aria-label="Abrir menu de navegação" />
        <div id="mobileMenu" aria-hidden="true">
          <a href="#como-funciona">Como Funciona</a>
        </div>
        <section id="como-funciona">Conteúdo</section>
        <HomepageInteractions />
      </>,
    );

    const button = screen.getByRole("button");
    const menu = document.getElementById("mobileMenu") as HTMLElement;
    expect(menu).toHaveAttribute("hidden");

    fireEvent.click(button);
    expect(button).toHaveAttribute("aria-expanded", "true");
    expect(menu).not.toHaveAttribute("hidden");
    expect(document.body).toHaveClass("menu-open");

    fireEvent.keyDown(document, { key: "Escape" });
    expect(button).toHaveAttribute("aria-expanded", "false");
    expect(menu).toHaveAttribute("hidden");
    expect(button).toHaveFocus();
  });

  it("oferece navegação por teclado no menu do usuário", () => {
    render(
      <>
        <button id="userMenuBtn" type="button">Maria</button>
        <div id="userDropdown" aria-hidden="true">
          <a href="/cliente/dashboard">Minha Conta</a>
          <a href="/logout">Sair</a>
        </div>
        <HomepageInteractions />
      </>,
    );

    const button = screen.getByRole("button", { name: "Maria" });
    const dropdown = document.getElementById("userDropdown") as HTMLElement;
    fireEvent.keyDown(button, { key: "ArrowDown" });

    expect(button).toHaveAttribute("aria-expanded", "true");
    expect(dropdown).not.toHaveAttribute("hidden");
    expect(screen.getByText("Minha Conta")).toHaveFocus();

    fireEvent.keyDown(dropdown, { key: "Escape" });
    expect(button).toHaveAttribute("aria-expanded", "false");
    expect(button).toHaveFocus();
  });
});
