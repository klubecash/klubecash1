import Image from "next/image";
import type { HomeContext } from "@/types/home";

type PartnerStoresProps = Pick<HomeContext, "partnerStores" | "links">;

export function PartnerStores({ partnerStores, links }: PartnerStoresProps) {
  return (
    <section id="parceiros" className="section">
      <div className="container">
        <div className="section-header fade-in">
          <span className="section-badge">Nossos Parceiros</span>
          <h2 className="section-title">Onde Você Pode Usar o Klube Cash</h2>
          <p className="section-description">
            Descubra algumas das incríveis lojas parceiras onde você pode ganhar cashback
          </p>
        </div>

        {partnerStores.length > 0 ? (
          <>
            <div className="grid grid-4 partners-grid">
              {partnerStores.map((store, index) => (
                <div className="partner-item fade-in" key={`${store.name}-${index}`}>
                  <div className="partner-logo">
                    {store.logoUrl ? (
                      <Image
                        src={store.logoUrl}
                        alt={`Logo ${store.name}`}
                        className="store-logo-image"
                        width={240}
                        height={120}
                        sizes="(max-width: 720px) 50vw, 25vw"
                        unoptimized
                      />
                    ) : (
                      <div
                        className="store-logo-fallback"
                        style={{
                          background: `linear-gradient(135deg, ${store.fallback.startColor}, ${store.fallback.endColor})`,
                        }}
                        title={store.name}
                      >
                        {store.fallback.initial}
                      </div>
                    )}
                  </div>
                  <div className="partner-info">
                    <h4>{store.name}</h4>
                    {store.category ? <span className="partner-category">{store.category}</span> : null}
                  </div>
                </div>
              ))}
            </div>

            <div className="text-center mt-20">
              <a href={links.storeRegister} className="btn btn-primary">Quero Ser Parceiro</a>
            </div>
          </>
        ) : (
          <div className="text-center">
            <h3>Em Breve: Lojas Incríveis!</h3>
            <p>Estamos fechando parcerias com as melhores lojas para você.</p>
            <a href={links.storeRegister} className="btn btn-primary">Seja o Primeiro Parceiro</a>
          </div>
        )}
      </div>
    </section>
  );
}
