import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import RecoveryExperience, { type RecoveryContext } from "@/components/RecoveryExperience";

const requestContext: RecoveryContext = {
  csrfToken: "a".repeat(64),
  validToken: false,
  maskedEmail: null,
  expirationHours: 2,
  error: null,
  success: null,
};

const resetContext: RecoveryContext = {
  csrfToken: "b".repeat(64),
  validToken: true,
  maskedEmail: "m****@exemplo.com",
  expirationHours: 2,
  error: null,
  success: null,
};

function mediaQuery(matches = false): MediaQueryList {
  return {
    matches,
    media: "",
    onchange: null,
    addListener: vi.fn(),
    removeListener: vi.fn(),
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    dispatchEvent: vi.fn(),
  };
}

describe("experiência de recuperação de senha", () => {
  beforeEach(() => {
    window.localStorage.clear();
    document.documentElement.setAttribute("data-theme", "light");
    Object.defineProperty(window, "matchMedia", {
      configurable: true,
      value: vi.fn().mockImplementation(() => mediaQuery(false)),
    });
    vi.restoreAllMocks();
  });

  it("preserva o estado de solicitação e seu passo a passo", () => {
    render(<RecoveryExperience token={null} requestSent={false} initialContext={requestContext} />);

    expect(screen.getByRole("heading", { name: "Recuperar senha" })).toBeInTheDocument();
    expect(screen.getByText("Não se preocupe! Vamos ajudar você a recuperar o acesso à sua conta")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Fazer login" })).toHaveAttribute("href", "/login");
    expect(screen.getByText("Digite o e-mail da sua conta", { selector: "p" })).toBeInTheDocument();
    expect(screen.getByText("Receba o link de recuperação por e-mail")).toBeInTheDocument();
    expect(screen.getByText(/expira em 2 horas por segurança/)).toBeInTheDocument();
  });

  it("valida o e-mail antes de enviar ao PHP", () => {
    render(<RecoveryExperience token={null} requestSent={false} initialContext={requestContext} />);
    const email = screen.getByLabelText("E-mail da sua conta");

    fireEvent.change(email, { target: { value: "email-inválido" } });
    fireEvent.click(screen.getByRole("button", { name: "Enviar instruções" }));
    expect(screen.getByRole("alert")).toHaveTextContent("Por favor, informe um email válido.");
    expect(email).toHaveFocus();
    expect(email).toHaveAttribute("aria-invalid", "true");
  });

  it("mostra a resposta de negócio devolvida pelo backend", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({
      ok: false,
      json: async () => ({ status: false, message: "Sua sessão expirou. Atualize a página e tente novamente." }),
    }));
    render(<RecoveryExperience token={null} requestSent={false} initialContext={requestContext} />);

    fireEvent.change(screen.getByLabelText("E-mail da sua conta"), { target: { value: "pessoa@exemplo.com" } });
    fireEvent.click(screen.getByRole("button", { name: "Enviar instruções" }));

    await waitFor(() => expect(screen.getByRole("alert")).toHaveTextContent("Sua sessão expirou."));
    expect(fetch).toHaveBeenCalledWith(
      "/api/auth/recovery",
      expect.objectContaining({ method: "POST", credentials: "same-origin" }),
    );
  });

  it("renderiza o estado de redefinição e o e-mail mascarado", () => {
    render(<RecoveryExperience token={"c".repeat(64)} requestSent={false} initialContext={resetContext} />);

    expect(screen.getByRole("heading", { name: "Criar nova senha" })).toBeInTheDocument();
    expect(screen.getByText("m****@exemplo.com")).toBeInTheDocument();
    expect(screen.getByText("Redefinindo senha para esta conta")).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Dicas para uma senha segura" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Alterar minha senha" })).toBeInTheDocument();
  });

  it("mede a força e compara as duas senhas", () => {
    render(<RecoveryExperience token={"c".repeat(64)} requestSent={false} initialContext={resetContext} />);
    fireEvent.change(screen.getByLabelText("Nova senha"), { target: { value: "Senha@123" } });
    fireEvent.change(screen.getByLabelText("Confirmar nova senha"), { target: { value: "Senha@123" } });

    expect(screen.getByRole("progressbar", { name: "Força da senha" })).toHaveAttribute("aria-valuenow", "5");
    expect(screen.getByText("Muito forte")).toBeInTheDocument();
    expect(screen.getByText("✓ Senhas coincidem")).toBeInTheDocument();
  });

  it("bloqueia senhas curtas ou divergentes", () => {
    render(<RecoveryExperience token={"c".repeat(64)} requestSent={false} initialContext={resetContext} />);
    const password = screen.getByLabelText("Nova senha");
    const confirmation = screen.getByLabelText("Confirmar nova senha");

    fireEvent.change(password, { target: { value: "curta" } });
    fireEvent.change(confirmation, { target: { value: "outra" } });
    fireEvent.click(screen.getByRole("button", { name: "Alterar minha senha" }));
    expect(screen.getByRole("alert")).toHaveTextContent("A senha deve ter no mínimo 8 caracteres.");
    expect(password).toHaveFocus();

    fireEvent.change(password, { target: { value: "Senha@123" } });
    fireEvent.click(screen.getByRole("button", { name: "Alterar minha senha" }));
    expect(screen.getByRole("alert")).toHaveTextContent("As senhas não coincidem.");
    expect(confirmation).toHaveFocus();
  });

  it("mantém o formulário de solicitação quando o token é inválido", () => {
    render(
      <RecoveryExperience
        token={"d".repeat(64)}
        requestSent={false}
        initialContext={{ ...requestContext, error: "Token inválido ou expirado. Por favor, solicite uma nova recuperação de senha." }}
      />,
    );
    expect(screen.getByRole("alert")).toHaveTextContent("Token inválido ou expirado.");
    expect(screen.getByLabelText("E-mail da sua conta")).toBeInTheDocument();
  });
});
