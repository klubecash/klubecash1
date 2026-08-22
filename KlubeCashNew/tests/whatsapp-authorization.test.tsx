import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import WhatsAppAuthorization from "@/components/WhatsAppAuthorization";

const readyContext = {
  canAuthorize: true,
  user: { name: "Kaua Matheus", type: "loja", maskedPhone: "(**) *****-5205" },
  store: { id: 34, name: "Grupo Kore" },
  expiresAt: "2026-08-22T12:05:00-03:00",
  message: "Confirme para liberar.",
  csrfToken: "csrf-test",
};

describe("autorizacao segura do WhatsApp", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    window.history.replaceState({}, "", "/whatsapp/autenticar?token=token-secreto");
  });

  it("mostra a conta e a loja antes da confirmacao", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ status: "success", data: readyContext, requestId: "req-1" }),
    }));
    render(<WhatsAppAuthorization token="token-de-teste-com-tamanho-suficiente-123" />);

    expect(await screen.findByRole("heading", { name: "Confirme esta autorizacao" })).toBeInTheDocument();
    expect(screen.getByText("Grupo Kore")).toBeInTheDocument();
    expect(screen.getByText("Kaua Matheus")).toBeInTheDocument();
    expect(screen.getByText("(**) *****-5205")).toBeInTheDocument();
    expect(window.location.search).not.toContain("token");
  });

  it("envia token e CSRF e apresenta o retorno ao WhatsApp", async () => {
    const fetchMock = vi.fn()
      .mockResolvedValueOnce({ ok: true, json: async () => ({ status: "success", data: readyContext, requestId: "req-1" }) })
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          status: "success",
          data: { authorized: true, store: readyContext.store, expiresAt: readyContext.expiresAt, returnToWhatsAppUrl: "https://wa.me/5538999999999?text=%2Fklube" },
          requestId: "req-2",
        }),
      });
    vi.stubGlobal("fetch", fetchMock);
    render(<WhatsAppAuthorization token="token-de-teste-com-tamanho-suficiente-123" />);

    fireEvent.click(await screen.findByRole("button", { name: "Autorizar este WhatsApp" }));
    expect(await screen.findByRole("heading", { name: "WhatsApp conectado com seguranca" })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Voltar ao WhatsApp" })).toHaveAttribute("href", "https://wa.me/5538999999999?text=%2Fklube");
    await waitFor(() => expect(fetchMock).toHaveBeenLastCalledWith(
      "/api/whatsapp/auth/approve",
      expect.objectContaining({
        method: "POST",
        headers: expect.objectContaining({ "x-csrf-token": "csrf-test" }),
        body: JSON.stringify({ token: "token-de-teste-com-tamanho-suficiente-123" }),
      }),
    ));
  });

  it("bloqueia a autorizacao quando o telefone nao coincide", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ status: "success", data: { ...readyContext, canAuthorize: false, message: "Telefone diferente." }, requestId: "req-3" }),
    }));
    render(<WhatsAppAuthorization token="token-de-teste-com-tamanho-suficiente-123" />);

    expect(await screen.findByText("Telefone diferente.")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Autorizar este WhatsApp" })).toBeDisabled();
  });
});
