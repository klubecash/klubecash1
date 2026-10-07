import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { beforeEach, describe, expect, it, vi } from "vitest";
import NetworksAdminPage from "@/app/admin/redes/page";

const fetchAdmin = vi.hoisted(() => vi.fn());
vi.mock("@/lib/admin-client", () => ({ adminFetch: fetchAdmin, mutationHeaders: () => ({ "x-csrf-token": "test" }) }));
vi.mock("@/components/admin/AdminProviders", () => ({ useAdminContext: () => ({ csrfToken: "test" }) }));

describe("central administrativa de redes", () => {
  beforeEach(() => {
    fetchAdmin.mockReset();
    fetchAdmin.mockImplementation(async (resource: string) => {
      if (resource === "store-networks") return { items: [{ id: 8, name: "Rede Sol", status: "active", active_stores: 1 }] };
      if (resource === "store-networks/8") return { id: 8, name: "Rede Sol", status: "active", stores: [{ store_id: 1, store_name: "Centro", status: "active", cnpj: "111" }], managers: [], events: [] };
      if (resource.startsWith("store-networks/8/wallet-health")) return { healthy: false, caseCount: 1, storeIds: [1, 2], cases: [{ userId: 7, storeId: 2, issues: ["balance_mismatch"], walletCents: 100, creditCents: 200, lastMovementCents: 200, canRepairMissingWallet: false }] };
      if (resource.startsWith("store-networks/candidates?kind=stores")) return { items: [{ id: 2, name: "Shopping", cnpj: "222", existing_network_id: null }] };
      throw new Error(`Consulta inesperada: ${resource}`);
    });
  });

  it("busca filial por nome e impede vínculo enquanto houver divergência", async () => {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    render(<QueryClientProvider client={client}><NetworksAdminPage /></QueryClientProvider>);
    fireEvent.click(await screen.findByRole("button", { name: /Rede Sol.*ativa/i }));
    expect(await screen.findByText(/1 carteira.*exigem revisão/i)).toBeInTheDocument();
    fireEvent.change(screen.getByLabelText("Buscar filial"), { target: { value: "Shopping" } });
    fireEvent.click(screen.getAllByRole("button", { name: "Buscar" })[0]);
    fireEvent.click(await screen.findByRole("button", { name: /Shopping.*222/i }));
    fireEvent.change(screen.getByLabelText("Motivo da alteração"), { target: { value: "Filial verificada" } });
    await waitFor(() => expect(screen.getByRole("button", { name: /Vincular Shopping/i })).toBeDisabled());
    expect(screen.queryByLabelText("ID da filial")).not.toBeInTheDocument();
  });
});
