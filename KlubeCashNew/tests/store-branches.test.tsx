import { render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { StoreSelector } from "@/components/store/StoreSelector";

const fetchMock = vi.fn();
vi.mock("@/lib/client-api", () => ({ storeFetch: (...args: unknown[]) => fetchMock(...args) }));

describe("seleção de filial", () => {
  beforeEach(() => { fetchMock.mockReset(); });

  it("mostra somente as filiais devolvidas para a conta e suas funções", async () => {
    fetchMock.mockResolvedValue({ csrfToken: "token", stores: [
      { id: 1, name: "Centro", role: "vendedor", networkId: 8 },
      { id: 2, name: "Shopping", role: "gerente", networkId: 8 },
    ] });
    render(<StoreSelector />);
    expect(await screen.findByRole("button", { name: /Centro.*vendedor/i })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /Shopping.*gerente/i })).toBeInTheDocument();
    expect(screen.queryByText("Outra rede")).not.toBeInTheDocument();
  });

  it("exibe erro de carregamento sem sugerir uma filial arbitrária", async () => {
    fetchMock.mockRejectedValue(new Error("Sessão expirada"));
    render(<StoreSelector />);
    expect(await screen.findByRole("alert")).toHaveTextContent("Sessão expirada");
  });
});
