import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { GiftbackCreditsPanel } from "@/components/admin/GiftbackCreditsPanel";
import type { GiftbackCredit } from "@/types/admin";

const fetchAdmin = vi.hoisted(() => vi.fn());
vi.mock("@/lib/admin-client", () => ({
  adminFetch: fetchAdmin,
  mutationHeaders: (token: string) => ({ "x-csrf-token": token }),
}));
vi.mock("@/components/admin/AdminProviders", () => ({ useAdminContext: () => ({ csrfToken: "admin-csrf" }) }));

const pagination = { page: 1, pageSize: 20, totalItems: 1, totalPages: 1 };
const credit: GiftbackCredit = {
  id: 91, userId: 7, storeId: 3, customerName: "Ana", storeName: "Loja Teste",
  originalCents: 10000, remainingCents: 0, consumedCents: 2500, expiredCents: 7500, revokedCents: 0,
  creditedAt: "2026-01-20T12:00:00-03:00", validUntil: "2026-02-19", expiresAt: "2026-02-20T00:00:00-03:00",
  version: 4, kind: "grant", status: "expired",
  events: [
    { id: 201, type: "expiration", amountCents: 7500, previousCents: 7500, currentCents: 0, oldValidUntil: "2026-02-19", newValidUntil: "2026-02-19", actorId: null, actorName: null, reason: "Validade encerrada", occurredAt: "2026-02-20T00:00:00-03:00", reversibleCents: 7500 },
    { id: 202, type: "expiration", amountCents: 2000, previousCents: 2000, currentCents: 0, oldValidUntil: "2026-02-19", newValidUntil: "2026-02-19", actorId: null, actorName: null, reason: "Já revertida", occurredAt: "2026-02-20T00:00:00-03:00", reversibleCents: 0 },
  ],
};

function setup(item: GiftbackCredit = credit, failFirstMutation = false) {
  let failed = false;
  fetchAdmin.mockImplementation(async (resource: string, init?: RequestInit) => {
    if (init?.method === "POST") {
      if (failFirstMutation && !failed) { failed = true; throw new Error("A conexão foi interrompida. Tente novamente."); }
      return { restored: true };
    }
    if (resource.includes("giftback-customers")) return { items: [{ id: 7, name: "Ana", email: "ana@example.test" }], pagination };
    if (resource.startsWith("stores/3/giftback-credits")) return { items: [item], pagination };
    if (resource.startsWith("giftback-credits/91?")) return { item };
    throw new Error(`Consulta inesperada: ${resource}`);
  });
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });
  render(<QueryClientProvider client={client}><GiftbackCreditsPanel store={{ id: 3, name: "Loja Teste" }} onClose={vi.fn()} /></QueryClientProvider>);
}

async function selectCredit() {
  fireEvent.click(await screen.findByRole("button", { name: "Ver créditos de Ana" }));
  fireEvent.click(await screen.findByRole("button", { name: "Detalhar crédito #91" }));
  await screen.findByRole("heading", { name: "Histórico deste crédito" });
}

function fillReasonAndDate() {
  fireEvent.change(screen.getByLabelText("Nova data de validade"), { target: { value: "2030-03-22" } });
  fireEvent.change(screen.getByLabelText("Motivo da alteração"), { target: { value: "Atendimento individual solicitado pelo cliente" } });
}

describe("gestão individual de expiração pelo admin", () => {
  beforeEach(() => { fetchAdmin.mockReset(); });

  it("exige selecionar o cliente antes de consultar seus créditos", async () => {
    setup();
    await screen.findByRole("button", { name: "Ver créditos de Ana" });
    expect(fetchAdmin.mock.calls.some(([resource]) => String(resource).includes("/giftback-credits"))).toBe(false);
    fireEvent.click(screen.getByRole("button", { name: "Ver créditos de Ana" }));
    await screen.findByRole("button", { name: "Detalhar crédito #91" });
    expect(fetchAdmin).toHaveBeenCalledWith("stores/3/giftback-credits?userId=7&page=1&status=");
    expect(screen.getByText("19/02/2026")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /reverter todas/i })).not.toBeInTheDocument();
  });

  it("reverte somente o evento ainda reversível do crédito e cliente escolhidos", async () => {
    setup(); await selectCredit();
    expect(screen.queryByRole("button", { name: "Reverter expiração #202" })).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Reverter expiração #201" }));
    expect(screen.getByText(/somente para Ana, nesta loja/)).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Confirmar reversão individual" })).toBeDisabled();
    fillReasonAndDate();
    fireEvent.click(screen.getByRole("button", { name: "Confirmar reversão individual" }));
    await screen.findByRole("status");
    const call = fetchAdmin.mock.calls.find(([resource, init]) => resource === "giftback-credits/91/restore" && init?.method === "POST");
    expect(call).toBeDefined();
    expect(JSON.parse(call![1].body)).toEqual({ userId: 7, storeId: 3, expectedVersion: 4, validUntil: "2030-03-22", reason: "Atendimento individual solicitado pelo cliente", expirationEventId: 201 });
    expect(call![1].headers).toMatchObject({ "x-csrf-token": "admin-csrf", "x-idempotency-key": expect.any(String) });
  });

  it("reutiliza a chave da mesma reversão quando a resposta falha", async () => {
    setup(credit, true); await selectCredit();
    fireEvent.click(screen.getByRole("button", { name: "Reverter expiração #201" })); fillReasonAndDate();
    fireEvent.click(screen.getByRole("button", { name: "Confirmar reversão individual" }));
    await screen.findByRole("alert");
    fireEvent.click(screen.getByRole("button", { name: "Confirmar reversão individual" }));
    await screen.findByRole("status");
    const calls = fetchAdmin.mock.calls.filter(([, init]) => init?.method === "POST");
    expect(calls).toHaveLength(2);
    expect(calls[0][1].headers["x-idempotency-key"]).toBe(calls[1][1].headers["x-idempotency-key"]);
  });

  it("estende o crédito disponível selecionado, sem enviar evento de reversão", async () => {
    setup({ ...credit, status: "active", remainingCents: 7500, expiredCents: 0, validUntil: "2028-03-22", events: [] });
    await selectCredit();
    fireEvent.click(screen.getByRole("button", { name: "Estender validade deste crédito" })); fillReasonAndDate();
    fireEvent.click(screen.getByRole("button", { name: "Confirmar extensão" }));
    await waitFor(() => expect(fetchAdmin.mock.calls.some(([resource]) => resource === "giftback-credits/91/extend")).toBe(true));
    const call = fetchAdmin.mock.calls.find(([resource]) => resource === "giftback-credits/91/extend");
    expect(JSON.parse(call![1].body)).toMatchObject({ userId: 7, storeId: 3, expectedVersion: 4, validUntil: "2030-03-22" });
    expect(JSON.parse(call![1].body)).not.toHaveProperty("expirationEventId");
  });
});
