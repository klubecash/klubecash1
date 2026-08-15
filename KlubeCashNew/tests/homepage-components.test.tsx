import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { Header } from "@/components/Header";
import { Hero } from "@/components/Hero";
import { PartnerStores } from "@/components/PartnerStores";
import type { HomeContext, HomeUser } from "@/types/home";

const links: HomeContext["links"] = {
  login: "/login",
  register: "/registro",
  storeRegister: "/lojas/cadastro",
  logout: "/logout",
};

const user = (overrides: Partial<HomeUser> = {}): HomeUser => ({
  name: "Maria",
  type: "cliente",
  avatarInitial: "M",
  employeeSubtype: null,
  employeeSubtypeLabel: null,
  dashboardUrl: "/cliente/dashboard",
  dashboardLabel: "Acessar Minha Conta",
  ...overrides,
});

describe("homepage por tipo de usuário", () => {
  it("preserva o hero e as ações do visitante", () => {
    render(<Hero authenticated={false} user={null} links={links} />);

    expect(screen.getByRole("heading", { name: "Transforme suas compras em dinheiro de volta" })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Começar Agora - É Grátis" })).toHaveAttribute("href", "/registro");
  });

  it.each([
    ["cliente", "/cliente/dashboard"],
    ["admin", "/admin/dashboard"],
    ["loja", "/store/dashboard"],
  ])("renderiza o estado autenticado de %s", (type, dashboardUrl) => {
    render(<Hero authenticated user={user({ type, dashboardUrl })} links={links} />);

    expect(screen.getByRole("heading", { name: "Bem-vindo de volta, Maria! 👋" })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Acessar Minha Conta" })).toHaveAttribute("href", dashboardUrl);
  });

  it("preserva o painel e o badge de funcionário", () => {
    render(
      <Hero
        authenticated
        user={user({
          type: "funcionario",
          employeeSubtype: "gerente",
          employeeSubtypeLabel: "Gerente",
          dashboardUrl: "/store/dashboard",
          dashboardLabel: "Acessar Painel da Loja",
        })}
        links={links}
      />,
    );

    expect(screen.getByText("🎯 Acesso como: Gerente")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Acessar Painel da Loja" })).toHaveAttribute("href", "/store/dashboard");
  });

  it("mantém o destino SENAT fornecido pelo PHP", () => {
    render(
      <Hero
        authenticated
        user={user({ dashboardUrl: "/views/auth/wallet-select.php" })}
        links={links}
      />,
    );

    expect(screen.getByRole("link", { name: "Acessar Minha Conta" })).toHaveAttribute("href", "/views/auth/wallet-select.php");
  });
});

describe("header e parceiros", () => {
  it("preserva ações mobile do visitante", () => {
    render(<Header authenticated={false} user={null} links={links} />);
    expect(screen.getByText("Cadastrar Grátis").closest("a")).toHaveAttribute("href", "/registro");
    expect(screen.getByRole("button", { name: "Abrir menu de navegação" })).toBeInTheDocument();
  });

  it("preserva menu e logout do funcionário", () => {
    render(<Header authenticated user={user({ type: "funcionario" })} links={links} />);
    expect(screen.getByText("Painel da Loja").closest("a")).toBeInTheDocument();
    expect(screen.getByText("Sair").closest("a")).toHaveAttribute("href", "/logout");
  });

  it("renderiza logo e fallback de parceiros", () => {
    render(
      <PartnerStores
        links={links}
        partnerStores={[
          {
            name: "Loja Logo",
            category: "Moda",
            logoUrl: "/uploads/store_logos/logo.png",
            fallback: { initial: "L", startColor: "#FF6B6B", endColor: "#cc5656" },
          },
          {
            name: "Loja Fallback",
            category: null,
            logoUrl: null,
            fallback: { initial: "F", startColor: "#4ECDC4", endColor: "#3ea49d" },
          },
        ]}
      />,
    );

    expect(screen.getByAltText("Logo Loja Logo")).toBeInTheDocument();
    expect(screen.getByTitle("Loja Fallback")).toHaveTextContent("F");
    expect(screen.getByRole("link", { name: "Quero Ser Parceiro" })).toHaveAttribute("href", "/lojas/cadastro");
  });

  it("preserva o estado sem parceiros", () => {
    render(<PartnerStores links={links} partnerStores={[]} />);
    expect(screen.getByRole("heading", { name: "Em Breve: Lojas Incríveis!" })).toBeInTheDocument();
  });
});
