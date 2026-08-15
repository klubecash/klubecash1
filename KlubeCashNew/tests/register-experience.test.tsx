import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import RegisterExperience from "@/components/RegisterExperience";

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

describe("experiência de cadastro", () => {
  beforeEach(() => {
    window.localStorage.clear();
    document.documentElement.setAttribute("data-theme", "light");
    Object.defineProperty(window, "matchMedia", {
      configurable: true,
      value: vi.fn().mockImplementation(() => mediaQuery(false)),
    });
    vi.restoreAllMocks();
  });

  it("preserva conteúdo, benefícios e link para o login", () => {
    render(<RegisterExperience initialError={null} initialSuccess={null} />);

    expect(screen.getByRole("heading", { name: "Crie sua conta" })).toBeInTheDocument();
    expect(screen.getByText("Comece a ganhar dinheiro de volta em suas compras")).toBeInTheDocument();
    expect(screen.getByText("Cashback real")).toBeInTheDocument();
    expect(screen.getByText("Processo rápido e seguro")).toBeInTheDocument();
    expect(screen.getByText("Muitas de lojas parceiras")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Fazer login" })).toHaveAttribute("href", "/login");
  });

  it("mantém máscara de telefone e progresso do preenchimento", () => {
    render(<RegisterExperience initialError={null} initialSuccess={null} />);
    const progress = screen.getByRole("progressbar", { name: "Progresso do cadastro" });

    fireEvent.change(screen.getByLabelText("Nome completo"), { target: { value: "Maria Cliente" } });
    fireEvent.change(screen.getByLabelText("Email"), { target: { value: "maria@exemplo.com" } });
    expect(progress).toHaveAttribute("aria-valuenow", "1");

    fireEvent.change(screen.getByLabelText("Telefone"), { target: { value: "11987654321" } });
    expect(screen.getByLabelText("Telefone")).toHaveValue("(11) 98765-4321");
    expect(progress).toHaveAttribute("aria-valuenow", "2");

    fireEvent.change(screen.getByLabelText("Senha"), { target: { value: "Senha@123" } });
    expect(progress).toHaveAttribute("aria-valuenow", "3");
    expect(screen.getByText("Muito forte")).toBeInTheDocument();
  });

  it("valida todos os campos e leva o foco ao primeiro erro", () => {
    render(<RegisterExperience initialError={null} initialSuccess={null} />);
    fireEvent.click(screen.getByRole("button", { name: "Criar minha conta gratuita" }));

    expect(screen.getAllByText("Por favor, informe seu nome completo (mínimo 3 caracteres).")).toHaveLength(2);
    expect(screen.getByText("Por favor, informe um email válido.")).toBeInTheDocument();
    expect(screen.getByText("Por favor, informe um telefone válido.")).toBeInTheDocument();
    expect(screen.getByText("A senha deve ter no mínimo 8 caracteres.")).toBeInTheDocument();
    expect(screen.getByLabelText("Nome completo")).toHaveFocus();
  });

  it("permite mostrar e ocultar a senha", () => {
    render(<RegisterExperience initialError={null} initialSuccess={null} />);
    const password = screen.getByLabelText("Senha");
    expect(password).toHaveAttribute("type", "password");

    fireEvent.click(screen.getByRole("button", { name: "Mostrar senha" }));
    expect(password).toHaveAttribute("type", "text");
    expect(screen.getByRole("button", { name: "Ocultar senha" })).toBeInTheDocument();
  });

  it("mostra a mensagem de negócio devolvida pelo PHP", async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: false,
      json: async () => ({ status: false, message: "Este email já está cadastrado. Por favor, use outro ou faça login." }),
    });
    vi.stubGlobal("fetch", fetchMock);
    render(<RegisterExperience initialError={null} initialSuccess={null} />);

    fireEvent.change(screen.getByLabelText("Nome completo"), { target: { value: "Maria Cliente" } });
    fireEvent.change(screen.getByLabelText("Email"), { target: { value: "maria@exemplo.com" } });
    fireEvent.change(screen.getByLabelText("Telefone"), { target: { value: "11987654321" } });
    fireEvent.change(screen.getByLabelText("Senha"), { target: { value: "Senha@123" } });
    fireEvent.click(screen.getByRole("button", { name: "Criar minha conta gratuita" }));

    await waitFor(() => expect(screen.getByRole("alert")).toHaveTextContent("Este email já está cadastrado."));
    expect(fetchMock).toHaveBeenCalledWith(
      "/api/auth/register",
      expect.objectContaining({ method: "POST", credentials: "same-origin" }),
    );
    expect(screen.getByRole("button", { name: "Criar minha conta gratuita" })).toBeEnabled();
  });
});
