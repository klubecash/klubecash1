import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import LoginExperience from "@/components/LoginExperience";

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

describe("experiência de login", () => {
  beforeEach(() => {
    window.localStorage.clear();
    document.documentElement.setAttribute("data-theme", "light");
    Object.defineProperty(window, "matchMedia", {
      configurable: true,
      value: vi.fn().mockImplementation(() => mediaQuery(false)),
    });
    vi.restoreAllMocks();
  });

  it("preserva textos, benefícios e destinos do PHP", () => {
    render(<LoginExperience initialError={null} initialSuccess={null} forceLogin={false} />);

    expect(screen.getByRole("heading", { name: "Bem-vindo de volta!" })).toBeInTheDocument();
    expect(screen.getByText("Cashback real")).toBeInTheDocument();
    expect(screen.getByText("Muitas lojas parceiras")).toBeInTheDocument();
    expect(screen.getByText("Sem taxas ou anuidades")).toBeInTheDocument();
    expect(screen.getByText("Utilize em lojas que ele foi gerado")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Cadastre-se grátis" })).toHaveAttribute("href", "/registro");
    expect(screen.getByRole("link", { name: "Esqueci minha senha" })).toHaveAttribute("href", "/recuperar-senha");
  });

  it("valida os campos e direciona o foco para o primeiro erro", () => {
    render(<LoginExperience initialError={null} initialSuccess={null} forceLogin={false} />);

    fireEvent.click(screen.getByRole("button", { name: "Entrar" }));
    expect(screen.getByText("Por favor, informe seu e-mail.")).toBeInTheDocument();
    expect(screen.getByText("Por favor, informe sua senha.")).toBeInTheDocument();
    expect(screen.getByLabelText("E-mail")).toHaveFocus();

    fireEvent.change(screen.getByLabelText("E-mail"), { target: { value: "email-inválido" } });
    fireEvent.change(screen.getByLabelText("Senha"), { target: { value: "senha" } });
    fireEvent.click(screen.getByRole("button", { name: "Entrar" }));
    expect(screen.getByText("Por favor, informe um e-mail válido.")).toBeInTheDocument();
  });

  it("permite mostrar e ocultar a senha", () => {
    render(<LoginExperience initialError={null} initialSuccess={null} forceLogin={false} />);
    const password = screen.getByLabelText("Senha");

    expect(password).toHaveAttribute("type", "password");
    fireEvent.click(screen.getByRole("button", { name: "Mostrar senha" }));
    expect(password).toHaveAttribute("type", "text");
    expect(screen.getByRole("button", { name: "Ocultar senha" })).toHaveAttribute("aria-pressed", "true");
  });

  it("exibe a mensagem devolvida pelo backend sem perder o formulário", async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ status: false, message: "E-mail ou senha inválidos." }),
    });
    vi.stubGlobal("fetch", fetchMock);
    render(<LoginExperience initialError={null} initialSuccess={null} forceLogin={false} />);

    fireEvent.change(screen.getByLabelText("E-mail"), { target: { value: "pessoa@exemplo.com" } });
    fireEvent.change(screen.getByLabelText("Senha"), { target: { value: "senha-incorreta" } });
    fireEvent.click(screen.getByRole("button", { name: "Entrar" }));

    await waitFor(() => expect(screen.getByRole("alert")).toHaveTextContent("E-mail ou senha inválidos."));
    expect(fetchMock).toHaveBeenCalledWith(
      "/api/auth/login",
      expect.objectContaining({ method: "POST", credentials: "same-origin" }),
    );
    expect(screen.getByRole("button", { name: "Entrar" })).toBeEnabled();
  });

  it("alterna e persiste o tema da mesma forma que a homepage", () => {
    render(<LoginExperience initialError={null} initialSuccess={null} forceLogin={false} />);
    fireEvent.click(screen.getByRole("button", { name: "Ativar modo noturno" }));

    expect(document.documentElement).toHaveAttribute("data-theme", "dark");
    expect(window.localStorage.getItem("klubecash-theme")).toBe("dark");
    expect(screen.getByRole("button", { name: "Ativar modo claro" })).toBeInTheDocument();
  });
});
