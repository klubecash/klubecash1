type FooterProps = {
  currentYear: number;
  storeRegisterUrl: string;
};

export function Footer({ currentYear, storeRegisterUrl }: FooterProps) {
  return (
    <footer className="footer">
      <div className="container">
        <div className="footer-grid">
          <div className="footer-brand">
            <h4>Klube Cash</h4>
            <p>Transformando suas compras em oportunidades de economia. O programa de cashback mais inteligente e confiável do Brasil.</p>
          </div>

          <div>
            <h4>Links Rápidos</h4>
            <ul>
              <li><a href="#como-funciona">Como Funciona</a></li>
              <li><a href="#vantagens">Vantagens</a></li>
              <li><a href="#parceiros">Lojas Parceiras</a></li>
              <li><a href={storeRegisterUrl}>Seja Parceiro</a></li>
            </ul>
          </div>

          <div>
            <h4>Legal</h4>
            <ul>
              <li><a href="termos-de-uso.php">Termos de Uso</a></li>
              <li><a href="politica-de-privacidade.php">Política de Privacidade</a></li>
              <li><a href="#">Política de Cookies</a></li>
            </ul>
          </div>

          <div>
            <h4>Contato</h4>
            <ul>
              <li><a href="mailto:contato@klubecash.com">contato@klubecash.com</a></li>
              <li><a href="tel:+55343030-1344">(34) 3030-1314</a></li>
              <li>Patos de Minas, MG</li>
            </ul>
          </div>
        </div>

        <div className="footer-bottom">
          <p>© {currentYear} Klube Cash. Todos os direitos reservados.</p>
        </div>
      </div>
    </footer>
  );
}
