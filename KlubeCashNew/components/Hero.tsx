import Image from "next/image";
import type { HomeContext } from "@/types/home";

type HeroProps = Pick<HomeContext, "authenticated" | "user" | "links">;

export function Hero({ authenticated, user, links }: HeroProps) {
  return (
    <section className="hero" aria-labelledby="hero-title">
      <div className="container">
        <div className="hero-layout">
          <div className="hero-copy fade-in">
            {authenticated && user ? (
              <>
                <div className="hero-welcome">
                  <h1 id="hero-title">Bem-vindo de volta, {user.name}! 👋</h1>
                  {user.type === "funcionario" && user.employeeSubtype ? (
                    <>
                      <div className="employee-badge">
                        🎯 Acesso como: {user.employeeSubtypeLabel ?? "Funcionário"}
                      </div>
                      <p>Gerencie as operações da sua loja com eficiência através do painel administrativo.</p>
                    </>
                  ) : (
                    <p>Continue economizando com inteligência. Explore suas oportunidades de cashback e descubra novas formas de economizar.</p>
                  )}
                </div>

                <div className="hero-actions">
                  <a href={user.dashboardUrl} className="btn btn-primary">{user.dashboardLabel}</a>
                  <a href="#parceiros" className="btn btn-ghost">Explorar Parceiros</a>
                </div>
              </>
            ) : (
              <>
                <h1 id="hero-title">Transforme suas compras em dinheiro de volta</h1>
                <p>O programa de cashback mais inteligente do Brasil. Cadastre-se gratuitamente e comece a receber dinheiro de volta em todas as suas compras.</p>
                <div className="hero-actions">
                  <a href={links.register} className="btn btn-primary">Começar Agora - É Grátis</a>
                  <a href="#como-funciona" className="btn btn-ghost">Como Funciona?</a>
                </div>
              </>
            )}
          </div>

          <div className="hero-visual fade-in" aria-hidden="true">
            <div className="hero-orbit">
              <span className="hero-orbit-dot hero-orbit-dot-one" />
              <span className="hero-orbit-dot hero-orbit-dot-two" />
              <span className="hero-orbit-dot hero-orbit-dot-three" />
            </div>
            <div className="hero-brand-card">
              <Image
                src="/assets/images/logobranco.png"
                alt=""
                className="hero-brand-logo"
                width={991}
                height={383}
                unoptimized
              />
            </div>
            <div className="hero-data-card hero-data-card-top">
              <span className="data-dot" />
              <span className="data-line data-line-long" />
              <span className="data-line data-line-short" />
            </div>
            <div className="hero-data-card hero-data-card-bottom">
              <span className="data-dot" />
              <span className="data-line data-line-short" />
              <span className="data-line data-line-long" />
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
