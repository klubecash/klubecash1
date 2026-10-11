import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { StoreProviders, useStoreView } from "@/components/store/StoreProviders";
import { StoreViewBar } from "@/components/store/StoreViewBar";
import type { StoreContext } from "@/types/store";

const fetchStore = vi.hoisted(() => vi.fn());
vi.mock("@/lib/client-api", () => ({ storeFetch: fetchStore }));

const context: StoreContext = {
  dataState: "ready", generatedAt: "2026-10-05T12:00:00-03:00",
  store: { id: 1, name: "Centro", status: "aprovado", logoUrl: null, customerCashbackPercentage: 5, cashbackEnabled: true, mvp: false, financialModel: "subscription_cashback" },
  user: { id: 10, name: "Gestora", type: "funcionario", subtype: "gestor_rede", avatarInitial: "G" },
  permissions: { manageEmployees: true, deactivateEmployees: true }, subscription: { active: true, status: "active", planName: "Plus" },
  csrfToken: "test", networkId: 8, networkName: "Rede Sol", canViewNetwork: true,
  stores: [{ id: 1, name: "Centro", role: "gestor_rede", networkId: 8 }, { id: 2, name: "Shopping", role: "gestor_rede", networkId: 8 }],
};

function QueryPreview() { const view = useStoreView(); return <output data-testid="query">{view.query(true)}</output>; }

describe("visão compartilhada das filiais", () => {
  beforeEach(() => { sessionStorage.clear(); fetchStore.mockReset(); fetchStore.mockResolvedValue({ items: [{ id: 22, name: "Vendedora" }] }); });

  it("mantém rede, filial, vendedor e período entre montagens", async () => {
    const first = render(<StoreProviders context={context}><StoreViewBar showStatus /><QueryPreview /></StoreProviders>);
    fireEvent.change(screen.getByLabelText("Filial dos dados"), { target: { value: "network" } });
    await waitFor(() => expect(fetchStore).toHaveBeenCalledWith("sellers&history=1&scope=network"));
    await screen.findByRole("option", { name: "Vendedora" });
    fireEvent.change(screen.getByLabelText("Vendedor dos dados"), { target: { value: "22" } });
    fireEvent.change(screen.getByLabelText("Data inicial dos dados"), { target: { value: "2026-10-01" } });
    fireEvent.change(screen.getByLabelText("Status das vendas"), { target: { value: "aprovado" } });
    expect(screen.getByTestId("query")).toHaveTextContent("scope=network");
    expect(screen.getByTestId("query")).toHaveTextContent("sellerId=22");
    first.unmount();
    render(<StoreProviders context={context}><StoreViewBar showStatus /><QueryPreview /></StoreProviders>);
    await waitFor(() => expect(screen.getByLabelText("Filial dos dados")).toHaveValue("network"));
    expect(screen.getByTestId("query")).toHaveTextContent("startDate=2026-10-01");
    expect(screen.getByTestId("query")).toHaveTextContent("status=aprovado");
  });

  it("não oferece consolidado a quem só vê uma filial", () => {
    render(<StoreProviders context={{ ...context, canViewNetwork: false, user: { ...context.user, subtype: "vendedor" }, stores: [context.stores![0]] }}><StoreViewBar /><QueryPreview /></StoreProviders>);
    expect(screen.queryByRole("option", { name: "Rede toda" })).not.toBeInTheDocument();
    expect(screen.queryByLabelText("Vendedor dos dados")).not.toBeInTheDocument();
    expect(screen.getByTestId("query")).toHaveTextContent("");
  });
});
