import { Klubinho } from "./Klubinho";

type AboutAndCtaProps = {
  registerUrl: string;
};

export function HowItWorksAndBenefits() {
  return (
    <>
      <section id="como-funciona" className="section">
        <div className="container">
          <div className="section-header fade-in">
            <span className="section-badge">Processo Simples</span>
            <h2 className="section-title">Como a KlubeCash Funciona?</h2>
            <p className="section-description">
              3 passos simples para começar a receber dinheiro de volta em todas as suas compras.
            </p>
          </div>

          <div className="grid grid-3 steps-grid">
            <article className="card step-card fade-in">
              <div className="card-icon">1</div>
              <h3>Cadastre-se Gratuitamente</h3>
              <p>Crie sua conta em menos de 2 minutos. É 100% gratuito e você não paga nada para participar do programa.</p>
            </article>
            <article className="card step-card fade-in">
              <div className="card-icon">2</div>
              <h3>Compre e Se Identifique</h3>
              <p>Faça suas compras normalmente nas lojas parceiras e se identifique como membro KlubeCash no momento da compra.</p>
            </article>
            <article className="card step-card step-card-giftback fade-in">
              <div className="card-icon">3</div>
              <h3>Receba Seu Giftback</h3>
              <p>Uma porcentagem do valor das suas compras volta para sua conta KlubeCash. É crédito real que você pode usar!</p>
              <Klubinho pose="comemora" size={130} className="steps-klubinho" floatAnimation={false} />
            </article>
          </div>
        </div>
      </section>

      <section id="klubinho" className="section klubinho-section">
        <div className="container">
          <div className="klubinho-showcase fade-in">
            <div className="klubinho-showcase-copy">
              <span className="section-badge">O Mascote KlubeCash</span>
              <h2 className="section-title">O giftback tem uma cara.</h2>
              <p>
                O Klubinho é a personificação da KlubeCash: simpático, reconhecível e sempre presente quando o seu dinheiro volta.
              </p>
              <div className="klubinho-points">
                <div><span>✓</span><p>Comemora cada giftback liberado</p></div>
                <div><span>✓</span><p>Acompanha você em cada compra</p></div>
                <div><span>✓</span><p>Representa um benefício real, não pontos complicados</p></div>
              </div>
            </div>
            <div className="klubinho-showcase-art" aria-label="Apresentação do Klubinho">
              <div className="klubinho-showcase-orbit" aria-hidden="true" />
              <Klubinho pose="comemora" size={355} className="klubinho-showcase-mascot" />
              <span className="klubinho-showcase-caption">Giftback local com propósito.</span>
            </div>
          </div>
        </div>
      </section>

      <section id="vantagens" className="section bg-light">
        <div className="container">
          <div className="section-header fade-in">
            <span className="section-badge">Por Que Escolher?</span>
            <h2 className="section-title">Vantagens Exclusivas do KlubeCash</h2>
            <p className="section-description">
              Descubra porque somos a escolha número 1 de quem quer economizar de verdade
            </p>
          </div>

          <div className="grid grid-3 benefits-grid">
            <article className="card benefit-card fade-in">
              <div className="card-icon">💰</div>
              <h3>Giftback Real</h3>
              <p>Crédito real que você terá na sua conta, não pontos que expiram ou vales que complicam sua vida.</p>
            </article>
            <article className="card benefit-card fade-in">
              <div className="card-icon">🔒</div>
              <h3>100% Seguro</h3>
              <p>Plataforma criptografada e dados protegidos. Sua segurança é nossa prioridade máxima, e conformidade com a LGPD.</p>
            </article>
            <article className="card benefit-card fade-in">
              <div className="card-icon">⚡</div>
              <h3>Instantâneo</h3>
              <p>Giftback processado rapidamente. Você vê o retorno do seu crédito em tempo real.</p>
            </article>
            <article className="card benefit-card fade-in">
              <div className="card-icon">🛠️</div>
              <h3>Suporte 24/7</h3>
              <p>Equipe especializada sempre pronta para ajudar você com qualquer dúvida ou problema.</p>
            </article>
            <article className="card benefit-card fade-in">
              <div className="card-icon">❤️</div>
              <h3>Pagou, usou</h3>
              <p>Use quando quiser, como quiser. Sem contratos longos ou obrigações chatas.</p>
            </article>
            <article className="card benefit-card fade-in">
              <div className="card-icon">🏪</div>
              <h3>Diversas Categorias em Expansão</h3>
              <p>A cada dia, mais lojas estão chegando para ampliar suas escolhas.</p>
            </article>
          </div>
        </div>
      </section>
    </>
  );
}

export function AboutAndCta({ registerUrl }: AboutAndCtaProps) {
  return (
    <>
      <section id="sobre" className="section bg-light">
        <div className="container">
          <div className="section-header fade-in">
            <span className="section-badge">Quem Somos</span>
            <h2 className="section-title">Sobre o KlubeCash</h2>
            <p className="section-description">
              Conheça nossa história e missão de transformar a forma como você economiza
            </p>
          </div>

          <div className="grid grid-3 about-grid">
            <article className="card about-card fade-in">
              <div className="card-icon">🎯</div>
              <h3>Nossa Missão</h3>
              <p>Democratizar o acesso ao giftback no Brasil, oferecendo uma plataforma intuitiva, segura e que realmente coloca dinheiro de volta no bolso dos nossos usuários.</p>
            </article>
            <article className="card about-card fade-in">
              <div className="card-icon">👁️</div>
              <h3>Nossa Visão</h3>
              <p>Ser a maior e mais confiável plataforma de giftback do Brasil, reconhecida pela transparência, inovação e pelo compromisso com a satisfação dos nossos clientes.</p>
            </article>
            <article className="card about-card fade-in">
              <div className="card-icon">💎</div>
              <h3>Nossos Valores</h3>
              <p>Transparência total, segurança em primeiro lugar, compromisso com o cliente, inovação constante e parcerias justas para todos.</p>
            </article>
          </div>

          <div className="about-story fade-in">
            <h3>Por Que KlubeCash?</h3>
            <p>
              Nascemos da vontade de criar algo diferente no mercado de giftback brasileiro. Cansados de sistemas complicados,
              taxas escondidas e benefícios que nunca se concretizam, decidimos criar uma plataforma onde o cliente é realmente valorizado.
            </p>
            <p>
              Hoje, ajudamos milhares de brasileiros a economizar todos os dias, conectando consumidores a lojas parceiras
              de forma simples, rápida e 100% transparente. Seu dinheiro de volta, do jeito que deveria ser.
            </p>
          </div>
        </div>
      </section>

      <section className="cta">
        <div className="container">
          <div className="cta-inner fade-in">
            <h2>Pronto para Começar a economizar Dinheiro?</h2>
            <p>Junte-se a milhares de brasileiros que já descobriram o segredo de transformar gastos em ganhos.</p>
            <Klubinho pose="aceno" size={180} className="cta-klubinho" floatAnimation={false} />
            <a href={registerUrl} className="btn btn-primary cta-button">Quero Meu Giftback Agora!</a>
          </div>
        </div>
      </section>
    </>
  );
}
