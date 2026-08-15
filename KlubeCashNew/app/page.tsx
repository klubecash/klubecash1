import type { Metadata } from "next";
import { AboutAndCta, HowItWorksAndBenefits } from "@/components/MarketingSections";
import { Footer } from "@/components/Footer";
import { Header } from "@/components/Header";
import { Hero } from "@/components/Hero";
import { HomepageInteractions } from "@/components/HomepageInteractions";
import { PartnerStores } from "@/components/PartnerStores";
import { getHomeContext } from "@/lib/home-context";
import styles from "./home.module.css";

export const dynamic = "force-dynamic";

export async function generateMetadata(): Promise<Metadata> {
  const context = await getHomeContext();
  return {
    title: context.authenticated && context.user
      ? `Bem-vindo ao Klube Cash, ${context.user.name}`
      : "Klube Cash - Transforme suas Compras em Dinheiro de Volta",
  };
}

export default async function HomePage() {
  const context = await getHomeContext();

  return (
    <div className={styles.root}>
      <div className="page-atmosphere" aria-hidden="true">
        <span className="atmosphere-orb atmosphere-orb-one" />
        <span className="atmosphere-orb atmosphere-orb-two" />
        <span className="atmosphere-orb atmosphere-orb-three" />
      </div>

      <Header authenticated={context.authenticated} user={context.user} links={context.links} />

      <main className="main-content">
        <Hero authenticated={context.authenticated} user={context.user} links={context.links} />
        <HowItWorksAndBenefits />
        <PartnerStores partnerStores={context.partnerStores} links={context.links} />
        <AboutAndCta registerUrl={context.links.register} />
      </main>

      <Footer currentYear={context.currentYear} storeRegisterUrl={context.links.storeRegister} />
      <HomepageInteractions />
    </div>
  );
}
