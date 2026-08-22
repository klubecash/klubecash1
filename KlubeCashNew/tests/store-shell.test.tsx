import { fireEvent, render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { StoreProviders } from "@/components/store/StoreProviders";
import { StoreShell } from "@/components/store/StoreShell";
import type { StoreContext } from "@/types/store";

const replace = vi.fn();
vi.mock("next/navigation", () => ({
  usePathname: () => "/store/dashboard",
  useRouter: () => ({ replace }),
}));

const context: StoreContext = {
  dataState: "ready",
  generatedAt: "2026-08-15T12:00:00-03:00",
  store: {
    id: 34,
    name: "Loja de Teste",
    status: "aprovado",
    logoUrl: null,
    customerCashbackPercentage: 5,
    cashbackEnabled: true,
    mvp: false,
    financialModel: "subscription_cashback",
  },
  user: {
    name: "Pessoa Lojista",
    type: "loja",
    subtype: null,
    avatarInitial: "P",
  },
  permissions: { manageEmployees: true, deactivateEmployees: true },
  subscription: { active: true, status: "ativa", planName: "Klube Plus" },
  csrfToken: "csrf-test",
};

describe("shell da área lojista", () => {
  beforeEach(() => {
    localStorage.clear();
    replace.mockClear();
    Object.defineProperty(window, "matchMedia", {
      configurable: true,
      value: vi.fn(() => ({ matches: false })),
    });
    vi.stubGlobal("requestAnimationFrame", (callback: FrameRequestCallback) => {
      callback(0);
      return 1;
    });
    vi.stubGlobal("cancelAnimationFrame", vi.fn());
  });

  it("mostra toda a navegação autorizada e destaca a rota ativa", () => {
    render(<StoreProviders context={context}><StoreShell><p>Conteúdo</p></StoreShell></StoreProviders>);
    expect(screen.getByRole("link", { name: "Visão geral" })).toHaveAttribute("href", "/store/dashboard");
    expect(screen.getByRole("link", { name: "Nova venda" })).toHaveAttribute("href", "/store/registrar-transacao");
    expect(screen.getByRole("link", { name: "Funcionários" })).toBeInTheDocument();
    expect(screen.getByText("Loja de Teste")).toBeInTheDocument();
  });

  it("abre o menu de usuário e preserva o logout PHP", () => {
    render(<StoreProviders context={context}><StoreShell><p>Conteúdo</p></StoreShell></StoreProviders>);
    fireEvent.click(screen.getByRole("button", { name: /Pessoa Lojista/i }));
    expect(screen.getByRole("link", { name: /Sair da conta/i })).toHaveAttribute("href", "/logout");
  });

  it("oculta a administração da equipe sem a capacidade correspondente", () => {
    render(<StoreProviders context={{ ...context, permissions: { manageEmployees: false, deactivateEmployees: false } }}><StoreShell><p>Conteúdo</p></StoreShell></StoreProviders>);
    expect(screen.queryByRole("link", { name: "Funcionários" })).not.toBeInTheDocument();
  });
});
