import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import StoreWalletLinkPage from "@/app/saldo/[token]/page";

vi.mock("next/navigation", () => ({ useParams: () => ({ token: "a".repeat(48) }) }));

const context = { store: { id: 1, name: "Loja Teste", logo: null, description: null }, csrfToken: "csrf-test", account: null, visitor: null, hasPreviousPhoneBalance: false };
const wallet = { store: context.store, customerName: "Cliente", visitor: false, wallet: { availableCents: 12500, nextExpirationDate: "2026-10-31", nextExpirationCents: 2500, credits: [{ id: 7, remainingCents: 12500, validUntil: "2026-10-31", creditedAt: "2026-10-01", status: "active" }] } };

function reply(data: unknown) { return { ok: true, status: 200, json: async () => ({ status: "success", data }) }; }

describe("consulta por link da loja", () => {
  beforeEach(() => { vi.restoreAllMocks(); });

  it("não mostra saldo antes de autenticar", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(reply(context)));
    render(<StoreWalletLinkPage />);
    expect(await screen.findByRole("heading", { name: "Loja Teste" })).toBeInTheDocument();
    expect(screen.getByAltText("KlubeCash")).toBeInTheDocument();
    expect(screen.getByAltText("Klubinho, o mascote da KlubeCash")).toBeInTheDocument();
    expect(screen.queryByText("R$ 125,00")).not.toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Entrar e consultar saldo" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Recebi pelo telefone" })).toBeInTheDocument();
  });

  it("mostra saldo e vencimento apenas da loja após login", async () => {
    const logged = { ...context, account: { name: "Cliente", email: "cliente@example.test" } };
    const fetchMock = vi.fn().mockResolvedValueOnce(reply(context)).mockResolvedValueOnce(reply({ name: "Cliente" })).mockResolvedValueOnce(reply(logged)).mockResolvedValueOnce(reply(wallet));
    vi.stubGlobal("fetch", fetchMock);
    render(<StoreWalletLinkPage />);
    fireEvent.change(await screen.findByLabelText("E-mail"), { target: { value: "cliente@example.test" } });
    fireEvent.change(screen.getByLabelText("Senha"), { target: { value: "Senha-segura-123" } });
    fireEvent.click(screen.getByRole("button", { name: "Entrar e consultar saldo" }));
    expect((await screen.findAllByText("R$ 125,00")).length).toBeGreaterThan(0);
    expect(screen.getByText(/vencem em 31\/10\/2026/)).toBeInTheDocument();
    expect(fetchMock.mock.calls.some(([url]) => String(url).endsWith("/wallet"))).toBe(true);
    expect(screen.queryByRole("button", { name: "Confirmar telefone e vincular" })).not.toBeInTheDocument();
  });

  it("oferece vinculação somente quando há indício de saldo anterior", async () => {
    const logged = { ...context, account: { name: "Cliente", email: "cliente@example.test" }, hasPreviousPhoneBalance: true };
    vi.stubGlobal("fetch", vi.fn().mockResolvedValueOnce(reply(logged)).mockResolvedValueOnce(reply(wallet)));
    render(<StoreWalletLinkPage />);
    expect(await screen.findByRole("button", { name: "Confirmar telefone e vincular" })).toBeInTheDocument();
    expect(screen.getByText("Dúvidas sobre esta carteira?")).toBeInTheDocument();
  });

  it("mostra saldo zerado sem sugerir vínculo para toda conta", async () => {
    const logged = { ...context, account: { name: "Cliente", email: "cliente@example.test" } };
    const emptyWallet = { ...wallet, wallet: { ...wallet.wallet, availableCents: 0, nextExpirationCents: 0, nextExpirationDate: null, credits: [] } };
    vi.stubGlobal("fetch", vi.fn().mockResolvedValueOnce(reply(logged)).mockResolvedValueOnce(reply(emptyWallet)));
    render(<StoreWalletLinkPage />);
    expect(await screen.findByText("Você ainda não tem giftback disponível nesta loja.")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Confirmar telefone e vincular" })).not.toBeInTheDocument();
  });

  it("bloqueia cadastro quando as duas senhas divergem", async () => {
    const verified = { ...context, visitor: { name: "Visitante", phone: "11999999999", expiresAt: "2026-10-01T12:30:00" } };
    vi.stubGlobal("fetch", vi.fn().mockResolvedValueOnce(reply(verified)).mockResolvedValueOnce(reply(wallet)));
    render(<StoreWalletLinkPage />);
    fireEvent.click(await screen.findByRole("button", { name: "Criar minha conta" }));
    fireEvent.change(screen.getByLabelText("Nome completo"), { target: { value: "Cliente Novo" } });
    fireEvent.change(screen.getByLabelText("E-mail"), { target: { value: "novo@example.test" } });
    fireEvent.change(screen.getByLabelText("Senha"), { target: { value: "Senha-segura-123" } });
    fireEvent.change(screen.getByLabelText("Confirme a senha"), { target: { value: "Outra-senha-123" } });
    await waitFor(() => expect(screen.getByRole("button", { name: "Criar conta e manter meu saldo" })).toBeDisabled());
    expect(screen.getByText("As senhas não coincidem.")).toBeInTheDocument();
  });

  it("mostra erro de link indisponível sem revelar formulário ou saldo", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ ok: false, status: 404, json: async () => ({ status: "error", message: "Link indisponível." }) }));
    render(<StoreWalletLinkPage />);
    expect(await screen.findByText("Não foi possível abrir esta carteira")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Entrar e consultar saldo" })).not.toBeInTheDocument();
  });
});
