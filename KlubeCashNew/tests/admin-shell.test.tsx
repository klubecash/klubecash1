import { fireEvent, render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { AdminProviders } from "@/components/admin/AdminProviders";
import { AdminShell } from "@/components/admin/AdminShell";
import type { AdminContext } from "@/types/admin";

const push = vi.fn();
vi.mock("next/navigation", () => ({
  usePathname: () => "/admin/dashboard",
  useRouter: () => ({ push }),
}));

const context: AdminContext = {
  dataState: "ready",
  generatedAt: "2026-08-21T12:00:00-03:00",
  user: {
    id: 11,
    name: "Admin de Teste",
    email: "admin@example.test",
    avatarInitial: "A",
  },
  permissions: {
    manageUsers: true,
    manageStores: true,
    manageLegacyFinance: true,
    manageSubscriptions: true,
    manageMarketing: true,
  },
  financialModel: "subscription_cashback",
  csrfToken: "csrf-admin-test",
};

describe("shell do Admin Master", () => {
  beforeEach(() => {
    localStorage.clear();
    push.mockClear();
    document.body.style.overflow = "";
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

  it("expõe todas as áreas canônicas e mantém o logout PHP", () => {
    render(
      <AdminProviders context={context}>
        <AdminShell>
          <p>Conteúdo</p>
        </AdminShell>
      </AdminProviders>,
    );

    expect(screen.getByRole("link", { name: "Visão geral" })).toHaveAttribute(
      "href",
      "/admin/dashboard",
    );
    expect(
      screen.getByRole("link", { name: "Financeiro legado" }),
    ).toHaveAttribute("href", "/admin/financeiro");
    expect(screen.getByRole("link", { name: "Campanhas" })).toHaveAttribute(
      "href",
      "/admin/email-marketing",
    );
    expect(screen.getByRole("link", { name: "Auditoria" })).toHaveAttribute(
      "href",
      "/admin/auditoria",
    );

    fireEvent.click(screen.getByRole("button", { name: /Admin de Teste/i }));
    expect(
      screen.getByRole("link", { name: /Sair da conta/i }),
    ).toHaveAttribute("href", "/logout");
  });

  it("persiste sidebar e tema sem perder o conteúdo", () => {
    render(
      <AdminProviders context={context}>
        <AdminShell>
          <p>Conteúdo administrativo</p>
        </AdminShell>
      </AdminProviders>,
    );

    fireEvent.click(screen.getByRole("button", { name: "Recolher menu" }));
    expect(localStorage.getItem("klube-admin-sidebar")).toBe("collapsed");

    fireEvent.click(screen.getByRole("button", { name: "Ativar tema escuro" }));
    expect(localStorage.getItem("klube-theme")).toBe("dark");
    expect(document.documentElement.dataset.adminTheme).toBe("dark");
    expect(screen.getByText("Conteúdo administrativo")).toBeInTheDocument();
  });

  it("fecha drawer e menu do usuário com Escape", () => {
    render(
      <AdminProviders context={context}>
        <AdminShell>
          <p>Conteúdo</p>
        </AdminShell>
      </AdminProviders>,
    );

    fireEvent.click(screen.getByRole("button", { name: "Abrir menu" }));
    expect(
      screen.getByRole("button", { name: "Fechar navegação" }),
    ).toBeInTheDocument();
    fireEvent.keyDown(document, { key: "Escape" });
    expect(
      screen.queryByRole("button", { name: "Fechar navegação" }),
    ).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole("button", { name: /Admin de Teste/i }));
    expect(screen.getByText("admin@example.test")).toBeInTheDocument();
    fireEvent.keyDown(document, { key: "Escape" });
    expect(screen.queryByText("admin@example.test")).not.toBeInTheDocument();
  });

  it("usa a busca da topbar para navegar sem recarregar o documento", () => {
    render(
      <AdminProviders context={context}>
        <AdminShell>
          <p>Conteúdo</p>
        </AdminShell>
      </AdminProviders>,
    );
    fireEvent.change(
      screen.getByRole("combobox", { name: "Buscar no Admin" }),
      { target: { value: "Relatórios" } },
    );
    fireEvent.submit(screen.getByRole("search"));
    expect(push).toHaveBeenCalledWith("/admin/relatorios");
  });
});
