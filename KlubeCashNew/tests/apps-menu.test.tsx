import { render, screen, waitFor } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";

const menu = {
  portalUrl: "https://intranet.example.com/",
  itens: [
    { slug: "klubecash", nome: "KlubeCash", url: "/", atual: true },
    { slug: "klube-pass", nome: "Klube Pass", url: "https://pass.example.com/auth/login", atual: false },
  ],
};

async function carregarComponente() {
  // O pedido fica guardado por carregamento de pagina: cada teste precisa de um modulo novo.
  vi.resetModules();
  return (await import("@/components/AppsMenu")).AppsMenu;
}

describe("menu Apps dos paineis", () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("mostra o Portal e os apps liberados, marcando o atual", async () => {
    vi.stubGlobal("fetch", vi.fn(async () => new Response(JSON.stringify({ status: true, menu }), { status: 200 })));
    const AppsMenu = await carregarComponente();
    render(<AppsMenu tone="store" />);

    expect(await screen.findByLabelText("Trocar de aplicativo")).toBeTruthy();
    const portal = screen.getByRole("menuitem", { name: /Portal Grupo Klube/ });
    expect(portal.getAttribute("href")).toBe("https://intranet.example.com/");
    const atual = screen.getByRole("menuitem", { name: /KlubeCash/ });
    expect(atual.getAttribute("aria-current")).toBe("true");
    expect(screen.getByRole("menuitem", { name: /Klube Pass/ }).getAttribute("href")).toBe("https://pass.example.com/auth/login");
  });

  it("nao desenha nada quando nao ha lista (login por senha ou sessao antiga)", async () => {
    const pedir = vi.fn(async (_url: string) => new Response(JSON.stringify({ status: true, menu: null }), { status: 200 }));
    vi.stubGlobal("fetch", pedir);
    const AppsMenu = await carregarComponente();
    const { container } = render(<AppsMenu tone="admin" />);

    await waitFor(() => expect(pedir).toHaveBeenCalledTimes(1));
    expect(pedir.mock.calls[0]?.[0]).toBe("/api/store/apps");
    expect(container.querySelector("details")).toBeNull();
  });

  it("nao quebra a pagina quando o servidor responde erro ou cai", async () => {
    vi.stubGlobal("fetch", vi.fn(async () => new Response("", { status: 401 })));
    const Erro = await carregarComponente();
    const primeiro = render(<Erro tone="store" />);
    await waitFor(() => expect(primeiro.container.querySelector("details")).toBeNull());
    primeiro.unmount();

    vi.stubGlobal("fetch", vi.fn(async () => { throw new Error("rede"); }));
    const Cai = await carregarComponente();
    const segundo = render(<Cai tone="store" />);
    await waitFor(() => expect(segundo.container.querySelector("details")).toBeNull());
  });
});
