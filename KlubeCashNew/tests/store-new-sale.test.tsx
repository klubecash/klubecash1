import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import RegisterTransactionPage from "@/app/store/registrar-transacao/page";
import { StoreProviders } from "@/components/store/StoreProviders";
import type { StoreContext } from "@/types/store";

const fetchStore = vi.hoisted(() => vi.fn());
vi.mock("@/lib/client-api", () => ({ storeFetch: fetchStore }));

const context: StoreContext = {
  dataState: "ready",
  generatedAt: "2026-11-04T12:00:00-03:00",
  store: { id: 1, name: "Loja Centro", status: "aprovado", logoUrl: null, customerCashbackPercentage: 5, cashbackEnabled: true, mvp: false, financialModel: "subscription_cashback" },
  user: { id: 10, name: "Bia Vendedora", type: "funcionario", subtype: "vendedor", avatarInitial: "B" },
  permissions: { manageEmployees: false, deactivateEmployees: false },
  subscription: { active: true, status: "active", planName: "Plus" },
  csrfToken: "csrf-teste",
};

type Customer = {
  id: number; name: string; email: string | null; phone: string; cpf: string | null; needsCpf: boolean;
  type: "visitor" | "registered"; createdByThisStore: boolean; balanceCents: number; purchaseCount: number; spentAmountCents: number;
};

const ana: Customer = {
  id: 7, name: "Ana Souza", email: "ana@exemplo.com", phone: "", cpf: "12345678901", needsCpf: false,
  type: "registered" as const, createdByThisStore: false, balanceCents: 2000, purchaseCount: 3, spentAmountCents: 30000,
};

const renderPage = (custom: Partial<StoreContext> = {}) =>
  render(<StoreProviders context={{ ...context, ...custom }}><RegisterTransactionPage /></StoreProviders>);

async function findAna(customer = ana) {
  fetchStore.mockResolvedValueOnce({ dataState: "ready", customer });
  fireEvent.change(screen.getByLabelText("E-mail do cliente"), { target: { value: "ana@exemplo.com" } });
  fireEvent.click(screen.getByRole("button", { name: /Buscar/ }));
  await screen.findByText(customer.name);
}

describe("tela Nova venda", () => {
  beforeEach(() => fetchStore.mockReset());

  it("explica o que falta e só libera a revisão com cliente e valor", async () => {
    renderPage();
    const review = screen.getByRole("button", { name: "Revisar e confirmar" });
    expect(review).toBeDisabled();
    expect(screen.getByText("Busque o cliente pelo e-mail para continuar.")).toBeInTheDocument();

    await findAna();
    expect(screen.getByText("Informe o valor da venda (mínimo R$ 5,00).")).toBeInTheDocument();
    expect(screen.getByText("3 compras aqui")).toBeInTheDocument();

    fireEvent.change(screen.getByLabelText("Valor total"), { target: { value: "100" } });
    expect(review).toBeEnabled();
  });

  it("calcula o giftback sobre o valor pago e limita o saldo ao disponível", async () => {
    renderPage();
    await findAna();
    fireEvent.change(screen.getByLabelText("Valor total"), { target: { value: "100,00" } });
    expect(screen.getByText("R$ 5,00")).toBeInTheDocument(); // 5% de R$ 100,00

    fireEvent.click(screen.getByRole("button", { name: /Usar tudo/ }));
    expect(screen.getByLabelText("Usar saldo do cliente")).toHaveValue("20,00");
    expect(screen.getByText("R$ 4,00")).toBeInTheDocument(); // 5% de R$ 80,00 pagos
    expect(screen.getByText("R$ 80,00")).toBeInTheDocument();

    fireEvent.change(screen.getByLabelText("Usar saldo do cliente"), { target: { value: "500" } });
    expect(screen.getAllByText("R$ 20,00").length).toBeGreaterThan(0); // nunca passa do saldo
  });

  it("pede o CPF antes de liberar a venda", async () => {
    renderPage();
    await findAna({ ...ana, cpf: null, needsCpf: true });
    expect(screen.getByLabelText("CPF do cliente")).toBeInTheDocument();
    expect(screen.getByText("Salve o CPF do cliente para continuar.")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Revisar e confirmar" })).toBeDisabled();
  });

  it("registra a venda depois da revisão, com chave de idempotência e valores em centavos", async () => {
    renderPage();
    await findAna();
    fireEvent.change(screen.getByLabelText("Valor total"), { target: { value: "100" } });
    fireEvent.click(screen.getByRole("button", { name: /Usar tudo/ }));
    fireEvent.click(screen.getByRole("button", { name: "Revisar e confirmar" }));

    const dialog = await screen.findByRole("dialog");
    expect(dialog).toHaveTextContent("Confira e confirme");
    fetchStore.mockResolvedValueOnce({
      id: 99, status: "approved", grossAmountCents: 10000, paidAmountCents: 8000, balanceUsedCents: 2000,
      cashbackGrantedCents: 400, customerBalanceCents: 400, replayed: false,
    });
    fireEvent.click(screen.getByRole("button", { name: "Confirmar venda" }));

    await screen.findByText("Venda registrada!");
    expect(screen.getByText("Giftback creditado")).toBeInTheDocument();
    const call = fetchStore.mock.calls.at(-1)!;
    expect(call[0]).toBe("transactions");
    expect(call[1].headers["X-Idempotency-Key"]).toMatch(/[0-9a-f-]{36}/);
    expect(call[1].headers["X-CSRF-Token"]).toBe("csrf-teste");
    const body = JSON.parse(call[1].body);
    expect(body).toMatchObject({ customerId: 7, grossAmountCents: 10000, balanceUsedCents: 2000, sellerId: 10 });
    expect(body.items).toEqual([]);

    fireEvent.click(screen.getByRole("button", { name: /Registrar outra venda/ }));
    await waitFor(() => expect(screen.getByLabelText("E-mail do cliente")).toHaveValue(""));
  });

  it("soma os itens detalhados no total", async () => {
    renderPage();
    await findAna();
    fireEvent.click(screen.getByRole("button", { name: /Mais detalhes/ }));
    fireEvent.click(screen.getByRole("button", { name: "Detalhar itens" }));
    fireEvent.change(screen.getByLabelText("Nome do item 1"), { target: { value: "Camiseta" } });
    fireEvent.change(screen.getByLabelText("Preço unitário do item 1"), { target: { value: "30" } });
    fireEvent.click(screen.getByRole("button", { name: "Aumentar quantidade" }));
    expect(screen.getByLabelText("Valor total")).toHaveValue("60,00");
    expect(screen.getByLabelText("Valor total")).toHaveAttribute("readonly");
    expect(screen.getByRole("button", { name: "Revisar e confirmar" })).toBeEnabled();
  });

  it("mostra o aviso de plano quando as vendas estão bloqueadas", () => {
    renderPage({ subscription: { ...context.subscription, salesBlocked: true } });
    expect(screen.getByText("Novas vendas estão pausadas")).toBeInTheDocument();
    expect(screen.queryByLabelText("E-mail do cliente")).not.toBeInTheDocument();
  });
});
